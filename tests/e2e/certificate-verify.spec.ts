import { test, expect } from '@playwright/test';

test.describe('Certificate Verification', () => {
  test('verification endpoint is accessible', async ({ page }) => {
    // Navigate to the verification page (no code = shows form or info page)
    const response = await page.goto('/index.php/testjournal/certificate/verify', {
      waitUntil: 'domcontentloaded',
    });
    await page.waitForTimeout(2000);

    // The endpoint should be accessible (may show a form, info page, or redirect)
    if (response) {
      expect(response.status()).toBeLessThan(500);
    }
  });

  test('invalid certificate code shows error message', async ({ page }) => {
    const response = await page.goto(
      '/index.php/testjournal/certificate/verify/FAKE-INVALID-CODE',
      { waitUntil: 'domcontentloaded' }
    );

    await page.waitForTimeout(2000);

    const bodyText = await page.textContent('body');
    const hasInvalidIndicator =
      bodyText?.toLowerCase().includes('invalid') ||
      bodyText?.toLowerCase().includes('not found') ||
      bodyText?.toLowerCase().includes('error') ||
      (response && response.status() >= 400);

    expect(hasInvalidIndicator).toBeTruthy();
  });
});
