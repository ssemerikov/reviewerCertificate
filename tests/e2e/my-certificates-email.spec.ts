import { test, expect, Page } from '@playwright/test';
import { loginAsAdmin, loginAsReviewer, enablePlugin, detectOjsVersion } from './helpers/ojs-auth';

/**
 * E2E for the Email Certificate feature (My Certificates → "Email me the
 * certificate"). Requires the Mailpit sink from ojs-test/docker-compose.yml
 * (run ojs-test/setup-smtp.sh once after starting the containers).
 *
 * Verifies on every OJS version:
 *  - the email button exists next to the download link
 *  - clicking it redirects back with the success banner
 *  - the message actually arrives (Mailpit API) with a PDF attachment
 */

const MAILPIT_API = 'http://localhost:8125/api/v1';
const REVIEWER_EMAIL = 'reviewer@test.local';

async function countMessagesToReviewer(page: Page): Promise<number> {
  const res = await page.request.get(
    `${MAILPIT_API}/search?query=${encodeURIComponent('to:"' + REVIEWER_EMAIL + '"')}`
  );
  if (!res.ok()) return 0;
  const data = await res.json();
  return data.messages_count ?? data.total ?? 0;
}

async function latestMessageToReviewer(page: Page): Promise<any | null> {
  const res = await page.request.get(
    `${MAILPIT_API}/search?query=${encodeURIComponent('to:"' + REVIEWER_EMAIL + '"')}`
  );
  if (!res.ok()) return null;
  const data = await res.json();
  return data.messages?.[0] ?? null;
}

test.describe('Email certificate from My Certificates', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await enablePlugin(page);
    await page.context().clearCookies();
  });

  test('email button sends the acknowledgement letter with PDF attached', async ({ page }) => {
    const version = await detectOjsVersion(page);
    await loginAsReviewer(page);

    // Ensure a certificate exists (creates the DB row on first download)
    const reviewId = version === '3.4' ? 5 : 4;
    await page.request.get(
      `/index.php/testjournal/certificate/download/${reviewId}`,
      { maxRedirects: 5, timeout: 30000 }
    );

    await page.goto('/index.php/testjournal/certificate/myCertificates', {
      waitUntil: 'domcontentloaded',
      timeout: 15000,
    });

    const emailButton = page.locator('.certificate-email-form button').first();
    await expect(emailButton).toBeVisible({ timeout: 10000 });

    const before = await countMessagesToReviewer(page);

    await emailButton.click();
    await page.waitForLoadState('domcontentloaded');

    // Back on My Certificates with the success banner
    expect(page.url()).toContain('myCertificates');
    await expect(page.locator('.certificate-email-banner-success')).toBeVisible({
      timeout: 15000,
    });

    // The message must actually arrive at the SMTP sink
    await expect
      .poll(async () => countMessagesToReviewer(page), {
        timeout: 20000,
        message: 'Mailpit should receive the acknowledgement email',
      })
      .toBeGreaterThan(before);

    const summary = await latestMessageToReviewer(page);
    expect(summary).not.toBeNull();
    expect(summary.Attachments).toBeGreaterThan(0);

    // Full message: subject rendered (no raw placeholders), PDF attached
    const detailRes = await page.request.get(`${MAILPIT_API}/message/${summary.ID}`);
    expect(detailRes.ok()).toBeTruthy();
    const detail = await detailRes.json();

    expect(detail.Subject).not.toContain('{{$');
    expect(detail.Text ?? detail.HTML ?? '').not.toContain('{{$');

    const attachments = detail.Attachments ?? [];
    expect(attachments.length).toBeGreaterThan(0);
    const pdf = attachments.find(
      (a: any) =>
        (a.ContentType ?? '').includes('pdf') ||
        (a.FileName ?? '').endsWith('.pdf')
    );
    expect(pdf).toBeTruthy();
    console.log(
      `Received "${detail.Subject}" with attachment ${pdf.FileName} (${pdf.Size} bytes)`
    );
  });

  test('oversized background image still emails a small PDF attachment', async ({ page }) => {
    // iitlt regression (SendPulse SMTP 552): a multi-MB background used to
    // produce a multi-MB attachment that mail relays reject. The generator
    // must downscale the background so the emailed PDF stays small.
    const { execFileSync } = require('child_process');
    const fs = require('fs');
    const path = require('path');
    const assetsDir = path.join(__dirname, '../../ojs-test/cert-assets');

    // Fixture: an oversized (>1 MB, >2200 px) background. ojs-test/ is not
    // tracked in git — regenerate via tests/e2e/helpers/make-big-bg.py.
    test.skip(
      !fs.existsSync(path.join(assetsDir, 'cert_bg_big.png')),
      'cert_bg_big.png fixture missing — run: python3 tests/e2e/helpers/make-big-bg.py'
    );

    const version = await detectOjsVersion(page);
    const project = test.info().project.name; // ojs33 | ojs34 | ojs35
    const contextId = { ojs33: 2, ojs34: 3, ojs35: 2 }[project];
    const bgPath = '/var/www/html/files/journals/cert-assets/cert_bg_big.png';

    // execFileSync with an argument array: no shell involved, all values are
    // test constants
    const sql = (query: string): string =>
      execFileSync(
        'docker',
        ['exec', 'ojs-test-db-1', 'mysql', '-uojs', '-pojs_test_pass', project, '-N', '-e', query],
        { stdio: 'pipe' }
      ).toString();

    // OJS caches plugin settings on disk — a direct DB write is invisible
    // until the cache is dropped
    const clearSettingsCache = () =>
      execFileSync(
        'docker',
        ['exec', `ojs-test-${project}-1`, 'sh', '-c', 'rm -f /var/www/html/cache/fc-pluginSettings-*'],
        { stdio: 'pipe' }
      );

    const settingWhere = (name: string) => `plugin_name = 'reviewercertificateplugin'
           AND context_id = ${contextId}
           AND setting_name = '${name}'`;
    const setSetting = (name: string, value: string) =>
      sql(
        `INSERT INTO plugin_settings (plugin_name, context_id, setting_name, setting_value, setting_type)
         VALUES ('reviewercertificateplugin', ${contextId}, '${name}', '${value}', 'string')
         ON DUPLICATE KEY UPDATE setting_value = '${value}'`
      );
    const readBg = () =>
      sql(`SELECT setting_value FROM plugin_settings WHERE ${settingWhere('backgroundImage')}`).trim();
    const priorBg = readBg();

    // All three OJS projects share one Mailpit and one reviewer login, so
    // "latest message to the reviewer" is racy under parallel runs. Give the
    // reviewer a unique per-run address instead — nothing else touches
    // users.email, so Mailpit's to:-search identifies OUR message reliably.
    const uniqueEmail = `reviewer+${project}-${Date.now()}@test.local`;
    const priorEmail = sql(`SELECT email FROM users WHERE username = 'testreviewer'`).trim();
    sql(`UPDATE users SET email = '${uniqueEmail}' WHERE username = 'testreviewer'`);

    // Newest-first message list for the unique address
    const listMessages = async (): Promise<any[]> => {
      const res = await page.request.get(
        `${MAILPIT_API}/search?query=${encodeURIComponent('to:"' + uniqueEmail + '"')}`
      );
      if (!res.ok()) return [];
      const data = await res.json();
      return data.messages ?? [];
    };

    try {
      await loginAsReviewer(page);

      const reviewId = version === '3.4' ? 5 : 4;
      await page.request.get(
        `/index.php/testjournal/certificate/download/${reviewId}`,
        { maxRedirects: 5, timeout: 30000 }
      );

      // The concurrently-running plugin-settings spec saves the settings
      // form on the same journal, which can overwrite our injected
      // backgroundImage between our write and the send. Verify after the
      // send that the setting survived; if it was clobbered, re-apply and
      // send again.
      let pdf: any = null;
      for (let attempt = 0; attempt < 3 && !pdf; attempt++) {
        setSetting('backgroundImage', bgPath);
        clearSettingsCache();

        await page.goto('/index.php/testjournal/certificate/myCertificates', {
          waitUntil: 'domcontentloaded',
          timeout: 30000,
        });

        const emailButton = page.locator('.certificate-email-form button').first();
        await expect(emailButton).toBeVisible({ timeout: 15000 });
        await emailButton.click();
        await page.waitForLoadState('domcontentloaded');

        await expect(page.locator('.certificate-email-banner-success')).toBeVisible({
          timeout: 20000,
        });

        // Each attempt must produce one more message than the previous one
        await expect
          .poll(async () => (await listMessages()).length, {
            timeout: 20000,
            message: 'Mailpit should receive the acknowledgement email for the unique address',
          })
          .toBeGreaterThan(attempt);

        if (readBg() !== bgPath) {
          console.log(`attempt ${attempt + 1}: backgroundImage was overwritten mid-flight, retrying`);
          continue;
        }

        const summary = (await listMessages())[0];
        const detailRes = await page.request.get(`${MAILPIT_API}/message/${summary.ID}`);
        expect(detailRes.ok()).toBeTruthy();
        const detail = await detailRes.json();
        pdf = (detail.Attachments ?? []).find(
          (a: any) =>
            (a.ContentType ?? '').includes('pdf') ||
            (a.FileName ?? '').endsWith('.pdf')
        );
      }

      expect(pdf).toBeTruthy();

      // Big enough to prove the background was embedded, small enough to
      // pass any sane relay size limit
      expect(pdf.Size).toBeGreaterThan(50000);
      expect(pdf.Size).toBeLessThan(1000000);
      console.log(`Attachment with downscaled background: ${pdf.Size} bytes`);
    } finally {
      sql(`UPDATE users SET email = '${priorEmail}' WHERE username = 'testreviewer'`);
      // Restore whatever background the journal had before this test
      if (priorBg) {
        setSetting('backgroundImage', priorBg);
      } else {
        sql(`DELETE FROM plugin_settings WHERE ${settingWhere('backgroundImage')}`);
      }
      clearSettingsCache();
    }
  });

  test('email endpoint does not send on GET requests', async ({ page }) => {
    const version = await detectOjsVersion(page);
    await loginAsReviewer(page);

    const before = await countMessagesToReviewer(page);

    const reviewId = version === '3.4' ? 5 : 4;
    const res = await page.request.get(
      `/index.php/testjournal/certificate/emailCertificate/${reviewId}`,
      { maxRedirects: 0, timeout: 15000 }
    );
    // 405 from the handler (3.3/3.4) or a framework redirect/error page (3.5)
    // — anything but a 200 that silently sent mail
    expect(res.status()).not.toBe(200);

    await page.waitForTimeout(3000);
    const after = await countMessagesToReviewer(page);
    expect(after).toBe(before);
  });

  test('email button is absent for unauthenticated users', async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('/index.php/testjournal/certificate/myCertificates', {
      waitUntil: 'domcontentloaded',
      timeout: 15000,
    });
    const isLogin = page.url().includes('login');
    const buttonCount = await page.locator('.certificate-email-form').count();
    expect(isLogin || buttonCount === 0).toBeTruthy();
  });
});
