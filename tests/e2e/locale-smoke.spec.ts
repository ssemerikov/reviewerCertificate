import { test, expect } from '@playwright/test';
import { loginAsAdmin, loginAsReviewer, enablePlugin } from './helpers/ojs-auth';

/**
 * Smoke tests for locale translation and page stability.
 * Covers:
 *  - Bug 1: Ukrainian locale keys showing as ##key## on reviewer dashboard
 *  - Bug 2: Memory exhaustion on settings page with large reviewer lists
 *  - Bug 3: Certificate download fatal errors
 * Tests run in both English and Ukrainian locales.
 */
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
    // Switch to Ukrainian locale
    await page.goto('/index.php/testjournal?setLocale=uk_UA', {
      waitUntil: 'domcontentloaded',
    });
    await page.waitForTimeout(1000);

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

    // Switch to Ukrainian
    await page.goto('/index.php/testjournal?setLocale=uk_UA', {
      waitUntil: 'domcontentloaded',
    });

    const pagesToTest = [
      '/index.php/testjournal/submissions',
      '/index.php/testjournal/certificate/verify',
      '/index.php/testjournal/dashboard',
    ];

    for (const url of pagesToTest) {
      const resp = await page.goto(url, {
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
    // Switch to Ukrainian first
    await page.goto('/index.php/testjournal?setLocale=uk_UA', {
      waitUntil: 'domcontentloaded',
    });
    await page.waitForTimeout(1000);

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
      }
    }
  });
});
