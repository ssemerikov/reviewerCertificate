import { Page, expect } from '@playwright/test';

const TEST_PASSWORD = 'testpass123';

/**
 * Login to OJS with the given credentials.
 * Handles both OJS 3.3 (classic login form) and OJS 3.4+/3.5 (updated UI).
 */
async function login(page: Page, username: string, password: string = TEST_PASSWORD) {
  // Navigate to the site-level login (works for all versions)
  await page.goto('/index.php/index/login', { waitUntil: 'networkidle' });

  // Wait for form to be ready
  await page.waitForSelector('input[name="username"]', { timeout: 10000 });

  // Fill credentials
  await page.locator('input[name="username"]').fill(username);
  await page.locator('input[name="password"]').fill(password);

  // Submit the form via JavaScript (Playwright click on OJS buttons is unreliable)
  await page.evaluate(() => {
    const forms = document.querySelectorAll('form');
    for (const form of forms) {
      if (form.querySelector('input[name="username"]')) {
        form.submit();
        return;
      }
    }
  });

  // Wait for redirect after login (admin goes to dashboard or journal)
  await page.waitForNavigation({ timeout: 15000 }).catch(() => {});
  await page.waitForLoadState('domcontentloaded');
}

export async function loginAsAdmin(page: Page) {
  await login(page, 'testadmin');
}

export async function loginAsReviewer(page: Page) {
  await login(page, 'testreviewer');
}

export async function loginAsEditor(page: Page) {
  await login(page, 'testeditor');
}

export async function loginAsAuthor(page: Page) {
  await login(page, 'testauthor');
}

/**
 * Navigate to the plugin management page.
 * Handles navigation differences between OJS 3.3, 3.4, and 3.5.
 */
export async function navigateToPlugins(page: Page) {
  await page.goto(
    '/index.php/testjournal/management/settings/website#plugins',
    { waitUntil: 'domcontentloaded', timeout: 15000 }
  );

  // Wait for the plugin grid to load (it's AJAX-loaded)
  // The "Reviewer Certificate" text appears when the Generic Plugins section loads
  await page.waitForSelector('text=Reviewer Certificate', { state: 'attached', timeout: 15000 });
}

/**
 * Find the Reviewer Certificate Plugin in the plugin list and return its row locator.
 */
export async function findPluginRow(page: Page) {
  // OJS 3.3/3.4 use a grid table with tr rows
  // OJS 3.5 may use a different layout
  // Match the row containing both the plugin name and a checkbox
  const row = page.locator('tr:has-text("Reviewer Certificate Plugin")').first();
  return row;
}

/**
 * Enable the Reviewer Certificate Plugin if not already enabled.
 *
 * On OJS 3.4+, clicks the checkbox which triggers an AJAX enable request.
 * On OJS 3.3, the pkpHandler fails to initialize (backslashes in the
 * namespaced plugin ID break the jQuery selector — Issue #65), so we
 * fall back to calling the enable endpoint directly via jQuery.ajax().
 */
export async function enablePlugin(page: Page) {
  await navigateToPlugins(page);

  const pluginRow = await findPluginRow(page);
  const toggle = pluginRow.locator('input[type="checkbox"]').first();

  if (await toggle.isChecked()) {
    return; // Already enabled
  }

  // Check if the pkpHandler is initialized (works on OJS 3.4+, broken on 3.3)
  const handlerOk = await page.evaluate(() => {
    const cb = document.querySelector('input[id*="reviewercertificate"][id*="enabled"]') as HTMLInputElement;
    if (!cb) return false;
    const data = (window as any).jQuery(cb).data();
    return Object.keys(data).length > 0;
  });

  if (handlerOk) {
    // OJS 3.4+: Normal click triggers the AJAX enable
    await toggle.click();

    // Wait for the success notification
    await page.waitForSelector('text=has been enabled', { timeout: 10000 }).catch(() => {});
    await page.waitForTimeout(1000);
  } else {
    // OJS 3.3 workaround: call the enable endpoint directly via AJAX
    const enableResult = await page.evaluate(() => {
      const cb = document.querySelector('input[id*="reviewercertificate"][id*="enabled"]') as HTMLInputElement;
      if (!cb) return { error: 'checkbox not found' };

      // Extract the enable URL from the inline script
      const span = cb.closest('span');
      const script = span?.querySelector('script')?.textContent || '';
      const urlMatch = script.match(/"url":\s*"([^"]+)"/);
      if (!urlMatch) return { error: 'enable URL not found' };

      const enableUrl = urlMatch[1].replace(/\\\//g, '/');

      // Extract CSRF token
      const csrfMatch = script.match(/"csrfToken":\s*"([^"]+)"/);
      const csrfToken = csrfMatch ? csrfMatch[1] : '';

      return new Promise<any>((resolve) => {
        (window as any).jQuery.ajax({
          url: enableUrl,
          type: 'POST',
          data: { csrfToken },
          success: (data: any) => resolve({ status: 'success', data: JSON.stringify(data).substring(0, 200) }),
          error: (_xhr: any, _status: string, error: string) => resolve({ status: 'error', error }),
        });
      });
    });

    if (enableResult?.status === 'success') {
      // Refresh the grid to reflect the change
      await page.evaluate(() => {
        const grid = (window as any).jQuery('.pkp_controllers_grid').first();
        if (grid.length) {
          grid.trigger('dataChanged');
        }
      });
      await page.waitForTimeout(2000);
    }
  }

  // Handle potential confirmation modal (DB migration prompt)
  const confirmButton = page.locator('.pkpModalConfirmButton, button:has-text("OK"), button.ok').first();
  if (await confirmButton.isVisible({ timeout: 3000 }).catch(() => false)) {
    await confirmButton.click();
    await page.waitForTimeout(2000);
  }
}

/**
 * Detect the OJS major version from the current page URL or base URL.
 * Returns '3.3', '3.4', '3.5', or 'unknown'.
 */
export async function detectOjsVersion(page: Page): Promise<string> {
  // Check both the current page URL and the page's base URL (from Playwright config)
  const urls = [page.url(), (page as any)._browserContext?._options?.baseURL || ''];
  for (const url of urls) {
    if (url.includes(':8033')) return '3.3';
    if (url.includes(':8034')) return '3.4';
    if (url.includes(':8035')) return '3.5';
  }
  // Fallback: navigate to root and check
  if (page.url() === 'about:blank') {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    const url = page.url();
    if (url.includes(':8033')) return '3.3';
    if (url.includes(':8034')) return '3.4';
    if (url.includes(':8035')) return '3.5';
  }
  return 'unknown';
}
