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
