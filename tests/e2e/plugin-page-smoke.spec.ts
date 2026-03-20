import { test, expect } from '@playwright/test';
import { loginAsAdmin, enablePlugin } from './helpers/ojs-auth';

/**
 * Smoke tests for Issue #68: compat_autoloader.php caused "Cannot declare class"
 * fatal errors on OJS 3.4 when included in the release package. These tests
 * verify that basic OJS pages load without 500 errors when the plugin is enabled.
 */
test.describe('Plugin page load smoke test (Issue #68)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await enablePlugin(page);
  });

  test('dashboard loads without 500 error', async ({ page }) => {
    const response = await page.goto(
      '/index.php/testjournal/dashboard',
      { waitUntil: 'domcontentloaded', timeout: 15000 }
    );
    expect(response!.status()).toBeLessThan(500);
    await expect(page.locator('body')).not.toContainText('Fatal error');
    await expect(page.locator('body')).not.toContainText('Cannot declare class');
  });

  test('website settings page loads without 500 error', async ({ page }) => {
    const response = await page.goto(
      '/index.php/testjournal/management/settings/website',
      { waitUntil: 'domcontentloaded', timeout: 15000 }
    );
    expect(response!.status()).toBeLessThan(500);
    await expect(page.locator('body')).not.toContainText('Fatal error');
    await expect(page.locator('body')).not.toContainText('Cannot declare class');
  });

  test('submissions page loads without 500 error', async ({ page }) => {
    const response = await page.goto(
      '/index.php/testjournal/submissions',
      { waitUntil: 'domcontentloaded', timeout: 15000 }
    );
    expect(response!.status()).toBeLessThan(500);
    await expect(page.locator('body')).not.toContainText('Fatal error');
    await expect(page.locator('body')).not.toContainText('Cannot declare class');
  });
});
