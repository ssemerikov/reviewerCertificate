import { test, expect } from '@playwright/test';
import { loginAsAdmin, navigateToPlugins, findPluginRow, enablePlugin, detectOjsVersion } from './helpers/ojs-auth';

test.describe('Plugin Enable/Disable', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('can enable the Reviewer Certificate Plugin', async ({ page }) => {
    await enablePlugin(page);

    // Navigate back to plugins to verify current state
    await navigateToPlugins(page);

    const pluginRow = await findPluginRow(page);
    await expect(pluginRow).toBeVisible({ timeout: 10000 });

    // On all OJS versions, the checkbox should be checked
    const toggle = pluginRow.locator('input[type="checkbox"]').first();
    await expect(toggle).toBeChecked({ timeout: 5000 });
  });

  test('plugin stays enabled after page refresh', async ({ page }) => {
    // Ensure plugin is enabled first
    await enablePlugin(page);

    // Refresh and check 3 times to catch intermittent failures
    for (let i = 0; i < 3; i++) {
      await navigateToPlugins(page);

      const refreshedRow = await findPluginRow(page);
      const refreshedToggle = refreshedRow.locator('input[type="checkbox"]').first();

      await expect(refreshedToggle).toBeChecked({
        timeout: 10000,
      });
    }
  });
});
