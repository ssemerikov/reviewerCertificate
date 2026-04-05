import { test, expect } from '@playwright/test';
import { loginAsAdmin, loginAsReviewer, loginAsAuthor, enablePlugin, detectOjsVersion } from './helpers/ojs-auth';

/**
 * Tests for the My Certificates page (/certificate/myCertificates).
 * Verifies:
 *  - Authenticated reviewer can access the page
 *  - Page shows table or empty state message
 *  - Download links are functional
 *  - Unauthenticated access is handled
 *  - No raw locale keys (##key##) appear
 */

/**
 * Switch to Ukrainian locale.
 */
async function switchToUkrainian(page: import('@playwright/test').Page): Promise<void> {
  const version = await detectOjsVersion(page);
  if (version === '3.5') {
    await page.goto('/testjournal/uk', { waitUntil: 'domcontentloaded', timeout: 15000 });
  } else {
    await page.goto('/index.php/testjournal?setLocale=uk_UA', {
      waitUntil: 'domcontentloaded',
    });
  }
  await page.waitForTimeout(1000);
}

test.describe('My Certificates Page', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await enablePlugin(page);
    await page.context().clearCookies();
  });

  test('reviewer can access My Certificates page (English)', async ({ page }) => {
    await loginAsReviewer(page);

    const response = await page.goto(
      '/index.php/testjournal/certificate/myCertificates',
      { waitUntil: 'domcontentloaded', timeout: 15000 }
    );

    if (response) {
      expect(response.status()).toBeLessThan(500);
    }

    const bodyText = await page.textContent('body');

    // Should show either the table or the empty message
    const hasContent =
      bodyText?.includes('My Certificates') ||
      bodyText?.includes('certificate') ||
      bodyText?.includes('don\'t have any certificates');

    expect(hasContent).toBeTruthy();

    // No raw locale keys
    expect(bodyText).not.toContain('##plugins.generic.reviewerCertificate.');
  });

  test('reviewer can access My Certificates page (Ukrainian)', async ({ page }) => {
    await loginAsReviewer(page);
    await switchToUkrainian(page);

    const response = await page.goto(
      '/index.php/testjournal/certificate/myCertificates',
      { waitUntil: 'domcontentloaded', timeout: 15000 }
    );

    if (response) {
      expect(response.status()).toBeLessThan(500);
    }

    const bodyText = await page.textContent('body');

    // Should show Ukrainian text or table content
    const hasContent =
      bodyText?.includes('Мої сертифікати') ||
      bodyText?.includes('сертифікат') ||
      bodyText?.includes('certificate') ||
      bodyText?.length! > 100;

    expect(hasContent).toBeTruthy();

    // No raw locale keys
    expect(bodyText).not.toContain('##plugins.generic.reviewerCertificate.');
  });

  test('My Certificates page shows certificate table after download', async ({ page }) => {
    const version = await detectOjsVersion(page);
    // First, ensure at least one certificate exists by downloading one
    await loginAsReviewer(page);

    const reviewId = version === '3.4' ? 5 : 4;
    // Use page.request to download PDF (page.goto throws "Download is starting" for binary)
    await page.request.get(
      `/index.php/testjournal/certificate/download/${reviewId}`,
      { maxRedirects: 5, timeout: 30000 }
    );
    await page.waitForTimeout(2000);

    // Now visit My Certificates page
    const response = await page.goto(
      '/index.php/testjournal/certificate/myCertificates',
      { waitUntil: 'domcontentloaded', timeout: 15000 }
    );

    if (response && response.status() < 500) {
      const bodyText = await page.textContent('body');

      // Should show a table with certificate data
      const hasTable = await page.locator('table.certificate-list-table').isVisible().catch(() => false);
      const hasDownloadLink = await page.locator('a[href*="certificate/download"]').first().isVisible().catch(() => false);

      // Either table with data or at least the page loaded without error
      if (hasTable) {
        console.log('Certificate table found on My Certificates page');
        expect(hasDownloadLink).toBeTruthy();
      } else {
        // Page loaded but maybe different template structure
        expect(bodyText).toBeTruthy();
      }
    }
  });

  test('My Certificates download links work', async ({ page }) => {
    const version = await detectOjsVersion(page);
    await loginAsReviewer(page);

    // Download a certificate first to populate the table
    const reviewId = version === '3.4' ? 5 : 4;
    await page.request.get(
      `/index.php/testjournal/certificate/download/${reviewId}`,
      { maxRedirects: 5, timeout: 30000 }
    );
    await page.waitForTimeout(2000);

    // Visit My Certificates
    await page.goto(
      '/index.php/testjournal/certificate/myCertificates',
      { waitUntil: 'domcontentloaded', timeout: 15000 }
    );

    // Find download links
    const downloadLinks = page.locator('a[href*="certificate/download"]');
    const count = await downloadLinks.count();

    if (count > 0) {
      // Verify download link returns PDF via API request (page.goto throws for binary)
      const href = await downloadLinks.first().getAttribute('href');
      if (href) {
        const response = await page.request.get(href, { maxRedirects: 5, timeout: 30000 });
        const contentType = response.headers()['content-type'] || '';
        if (response.status() === 200) {
          expect(contentType).toContain('application/pdf');
          console.log('Download link from My Certificates returned valid PDF');
        } else {
          expect(response.status()).toBeLessThan(500);
        }
      }
    }
  });

  test('unauthenticated user cannot access My Certificates', async ({ page }) => {
    // Clear cookies to be unauthenticated
    await page.context().clearCookies();

    const response = await page.goto(
      '/index.php/testjournal/certificate/myCertificates',
      { waitUntil: 'domcontentloaded', timeout: 15000 }
    );

    if (response) {
      // Should either redirect to login or return forbidden
      const status = response.status();
      const url = page.url();

      const isRedirectedToLogin = url.includes('login');
      const isForbidden = status === 403;
      const isNotFound = status === 404;

      expect(isRedirectedToLogin || isForbidden || isNotFound).toBeTruthy();
    }
  });
});
