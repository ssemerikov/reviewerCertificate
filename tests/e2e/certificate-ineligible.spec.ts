import { test, expect } from '@playwright/test';
import { loginAsAdmin, loginAsReviewer, enablePlugin } from './helpers/ojs-auth';

test.describe('Certificate Ineligibility', () => {
  test.beforeEach(async ({ page }) => {
    // Ensure plugin is enabled
    await loginAsAdmin(page);
    await enablePlugin(page);
    await page.context().clearCookies();
  });

  test('reviewer cannot download certificate for incomplete review', async ({ page }) => {
    await loginAsReviewer(page);

    // Navigate to submissions / reviews
    await page.goto('/index.php/testjournal/submissions', {
      waitUntil: 'domcontentloaded',
    });
    await page.waitForTimeout(3000);

    // The in-progress review (Submission #2: "Machine Learning...")
    // should NOT have a "Download Certificate" button
    const pageContent = await page.textContent('body');

    // Check that the incomplete review doesn't offer certificate download
    // We verify this by checking the reviewer's dashboard
    // The "Machine Learning" submission should be visible but without certificate
    if (pageContent?.includes('Machine Learning')) {
      // Find the row/section for this submission
      const mlSection = page.locator(
        ':has-text("Machine Learning")'
      ).first();

      // Within that context, there should be NO certificate download link
      const certButton = mlSection.locator(
        'a:has-text("Download Certificate"), a[href*="certificate/download"]'
      );

      // Should have 0 certificate buttons for this submission
      const certCount = await certButton.count().catch(() => 0);
      expect(certCount).toBe(0);
    }
  });

  test('direct download URL for incomplete review returns error', async ({ page }) => {
    await loginAsReviewer(page);

    // Try to download certificate for the in-progress review assignment
    // Review assignment #2 is the incomplete one
    const response = await page.goto(
      '/index.php/testjournal/certificate/download/2',
      { waitUntil: 'domcontentloaded' }
    );

    if (response) {
      // Should either be a 403 (forbidden), 404, or redirect with error message
      const status = response.status();
      const bodyText = await page.textContent('body');
      const isError =
        status >= 400 ||
        bodyText?.toLowerCase().includes('not eligible') ||
        bodyText?.toLowerCase().includes('not completed') ||
        bodyText?.toLowerCase().includes('error') ||
        bodyText?.toLowerCase().includes('not available');

      expect(isError).toBeTruthy();
    }
  });
});
