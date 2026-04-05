import { test, expect } from '@playwright/test';
import { loginAsAdmin, loginAsReviewer, enablePlugin, detectOjsVersion } from './helpers/ojs-auth';

/**
 * Smoke tests for locale translation and page stability.
 * Covers:
 *  - Bug 1: Ukrainian locale keys showing as ##key## on reviewer dashboard
 *  - Bug 2: Memory exhaustion on settings page with large reviewer lists
 *  - Bug 3: Certificate download fatal errors
 * Tests run in both English and Ukrainian locales.
 */

/**
 * Get the Ukrainian locale code for the current OJS version.
 * OJS 3.3/3.4 use 'uk_UA', OJS 3.5 uses 'uk'.
 */
async function getUkrainianLocale(page: import('@playwright/test').Page): Promise<string> {
  const version = await detectOjsVersion(page);
  return version === '3.5' ? 'uk' : 'uk_UA';
}

/**
 * Switch to Ukrainian locale, handling OJS 3.5's URL-based locale routing.
 * OJS 3.5 puts the locale in the URL path, which can cause redirect loops
 * when using ?setLocale= from an admin page with /en/ in the URL.
 */
async function switchToUkrainian(page: import('@playwright/test').Page): Promise<void> {
  const version = await detectOjsVersion(page);
  if (version === '3.5') {
    // OJS 3.5: Navigate directly to Ukrainian-prefixed URL to avoid redirect loops
    await page.goto('/testjournal/uk', { waitUntil: 'domcontentloaded', timeout: 15000 });
  } else {
    const ukLocale = version === '3.5' ? 'uk' : 'uk_UA';
    await page.goto(`/index.php/testjournal?setLocale=${ukLocale}`, {
      waitUntil: 'domcontentloaded',
    });
  }
  await page.waitForTimeout(1000);
}

test.describe('Locale and stability smoke tests', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await enablePlugin(page);
    await page.context().clearCookies();
  });

  test('verification page shows no raw locale keys (English)', async ({ page }) => {
    await loginAsAdmin(page);
    const response = await page.goto(
      '/index.php/testjournal/certificate/verify',
      { waitUntil: 'domcontentloaded' }
    );
    if (response && response.status() < 500) {
      const bodyText = await page.textContent('body');
      expect(bodyText).not.toContain('##plugins.generic.reviewerCertificate.');
    }
  });

  test('verification page shows no raw locale keys (Ukrainian)', async ({ page }) => {
    await switchToUkrainian(page);

    const response = await page.goto(
      '/index.php/testjournal/certificate/verify',
      { waitUntil: 'domcontentloaded' }
    );
    if (response && response.status() < 500) {
      const bodyText = await page.textContent('body');
      expect(bodyText).not.toContain('##plugins.generic.reviewerCertificate.');
    }
  });

  test('pages load without 500 after locale switch to Ukrainian', async ({ page }) => {
    await loginAsAdmin(page);
    await switchToUkrainian(page);

    const version = await detectOjsVersion(page);
    // OJS 3.5 uses locale in URL path; 3.3/3.4 use index.php path
    const prefix = version === '3.5' ? '/testjournal/uk' : '/index.php/testjournal';

    const pageSuffixes = [
      '/submissions',
      '/certificate/verify',
      '/dashboard',
    ];

    for (const suffix of pageSuffixes) {
      const resp = await page.goto(`${prefix}${suffix}`, {
        waitUntil: 'domcontentloaded',
        timeout: 15000,
      });
      if (resp) {
        expect(resp.status()).toBeLessThan(500);
      }
      const text = await page.textContent('body');
      expect(text).not.toContain('Fatal error');
      expect(text).not.toContain('Cannot declare class');
    }
  });

  test('settings page loads without memory error', async ({ page }) => {
    await loginAsAdmin(page);
    await enablePlugin(page);

    // Navigate to plugin settings — this triggers getEligibleReviewers()
    const resp = await page.goto(
      '/index.php/testjournal/management/settings/website#plugins',
      { waitUntil: 'domcontentloaded', timeout: 30000 }
    );
    if (resp) {
      expect(resp.status()).toBeLessThan(500);
    }
    const text = await page.textContent('body');
    expect(text).not.toContain('memory exhausted');
    expect(text).not.toContain('Fatal error');
    expect(text).not.toContain('Allowed memory size');
  });

  test('certificate download returns no fatal errors (English)', async ({ page }) => {
    await loginAsReviewer(page);

    const resp = await page.goto(
      '/index.php/testjournal/certificate/download/1',
      { waitUntil: 'domcontentloaded' }
    );
    if (resp) {
      const contentType = resp.headers()['content-type'] || '';
      if (!contentType.includes('application/pdf')) {
        const text = await page.textContent('body');
        expect(text).not.toContain('Fatal error');
        expect(text).not.toContain('memory exhausted');
        expect(text).not.toContain('Stack trace');
      }
    }
  });

  test('certificate download returns no fatal errors (Ukrainian)', async ({ page }) => {
    // Login first, then switch locale — login creates new session losing pre-login locale
    await loginAsReviewer(page);
    await switchToUkrainian(page);

    const resp = await page.goto(
      '/index.php/testjournal/certificate/download/1',
      { waitUntil: 'domcontentloaded' }
    );
    if (resp) {
      const contentType = resp.headers()['content-type'] || '';
      if (!contentType.includes('application/pdf')) {
        const text = await page.textContent('body');
        expect(text).not.toContain('Fatal error');
        expect(text).not.toContain('memory exhausted');
      }
    }
  });
});
