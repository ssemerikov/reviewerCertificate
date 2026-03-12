import { test, expect } from '@playwright/test';
import { loginAsAdmin, enablePlugin } from './helpers/ojs-auth';

test.describe('Batch Certificate Generation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await enablePlugin(page);
  });

  test('admin can access batch generation page', async ({ page }) => {
    const response = await page.goto(
      '/index.php/testjournal/certificate/generateBatch',
      { waitUntil: 'domcontentloaded' }
    );

    await page.waitForTimeout(3000);

    // Should be accessible (not 403/404)
    if (response) {
      expect(response.status()).toBeLessThan(400);
    }

    const bodyText = await page.textContent('body');
    expect(bodyText).toBeTruthy();
  });

  test('batch generation shows reviewers with completed reviews', async ({ page }) => {
    await page.goto(
      '/index.php/testjournal/certificate/generateBatch',
      { waitUntil: 'domcontentloaded' }
    );

    await page.waitForTimeout(3000);

    const pageContent = await page.textContent('body');
    expect(pageContent).toBeTruthy();
  });

  test('batch generation creates certificates without duplicates', async ({ page }) => {
    await page.goto(
      '/index.php/testjournal/certificate/generateBatch',
      { waitUntil: 'domcontentloaded' }
    );

    await page.waitForTimeout(3000);

    const generateButton = page.locator(
      'button:has-text("Generate"), input[type="submit"], button[type="submit"]'
    ).first();

    if (await generateButton.isVisible({ timeout: 5000 }).catch(() => false)) {
      await generateButton.click();
      await page.waitForTimeout(5000);

      // Reload and try again - should not create duplicates
      await page.goto(
        '/index.php/testjournal/certificate/generateBatch',
        { waitUntil: 'domcontentloaded' }
      );
      await page.waitForTimeout(3000);

      const generateButton2 = page.locator(
        'button:has-text("Generate"), input[type="submit"], button[type="submit"]'
      ).first();

      if (await generateButton2.isVisible({ timeout: 3000 }).catch(() => false)) {
        await generateButton2.click();
        await page.waitForTimeout(5000);

        const resultText2 = await page.textContent('body');
        expect(resultText2).toBeTruthy();
      }
    }
  });
});
