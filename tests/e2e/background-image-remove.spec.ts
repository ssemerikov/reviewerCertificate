import { test, expect } from '@playwright/test';
import * as path from 'path';
import { loginAsAdmin, enablePlugin, navigateToPlugins, findPluginRow } from './helpers/ojs-auth';
import {
  getPluginSetting,
  deletePluginSetting,
  fileExistsInContainer,
  clearSettingsCache,
} from './helpers/ojs-db';

/**
 * Issue #73 — "Cannot remove background image once set".
 *
 * readInputData() used to restore the stored path unconditionally, so the setting
 * could only ever be overwritten by another upload and never cleared. Despite the
 * issue title this affected every OJS version, not just 3.5.
 *
 * Driven through the real settings form, because the bug lived entirely in the
 * form's read/execute cycle.
 */
const FIXTURE = path.resolve(__dirname, '../../ojs-test/cert-assets/cert_bg_portrait.png');

async function openPluginSettings(page) {
  await navigateToPlugins(page);

  // The grid row must be expanded before its actions are rendered. Note the
  // collapsed row also contains an <a class="show_extras"> whose screen-reader
  // text is "Settings" — that is the expander, not the plugin's settings action.
  const pluginRow = await findPluginRow(page);
  await pluginRow.locator('a.show_extras').first().click();

  const settingsLink = page
    .locator('a[href*="verb=settings"][href*="plugin=reviewercertificateplugin"]')
    .first();
  await settingsLink.waitFor({ state: 'attached', timeout: 30000 });
  await settingsLink.click();

  // The modal is fetched over AJAX; on the slower 3.5 image the first click can
  // land before the grid has finished wiring its handlers, so retry once.
  try {
    await page.waitForSelector('#backgroundImage', { state: 'attached', timeout: 30000 });
  } catch {
    await settingsLink.click();
    await page.waitForSelector('#backgroundImage', { state: 'attached', timeout: 45000 });
  }

  // bodyTemplate is a required field. On a journal that has never saved these
  // settings it renders empty, and the form would be rejected before execute()
  // ever runs — so populate it the way a real journal manager would.
  const required: Array<[string, string]> = [
    ['bodyTemplate', 'Awarded to {{$reviewerName}} for reviewing for {{$journalName}}.'],
    // headerText is optional as of Issue #74, but fill it anyway so this spec
    // isolates the background-removal behaviour rather than tripping over
    // unrelated validation.
    ['headerText', 'Certificate of Recognition'],
  ];

  for (const [field, value] of required) {
    const input = page.locator(`#certificateSettingsForm [id^="${field}"]`).first();
    if (await input.count()) {
      const current = await input.inputValue().catch(() => '');
      if (!current.trim()) {
        await input.fill(value);
      }
    }
  }
}

async function submitSettingsForm(page) {
  // Native submit, bypassing the AjaxFormHandler the template installs — this is
  // the same multipart POST the page itself performs when a file is chosen.
  const submitted = await page.evaluate(() => {
    const form = document.querySelector('#certificateSettingsForm') as HTMLFormElement | null;
    if (!form) {
      return false;
    }
    form.submit();
    return true;
  });
  expect(submitted, 'certificate settings form should be present').toBe(true);
  await page.waitForLoadState('domcontentloaded').catch(() => {});
  await page.waitForTimeout(4000);
}

test.describe('Background image removal (Issue #73)', () => {
  // Each test opens the plugin grid and its AJAX settings modal twice, uploads a
  // file and submits the form twice. On the slower 3.5 image, late in a full
  // suite run, that legitimately exceeds the 60s default.
  test.describe.configure({ timeout: 180_000 });

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await enablePlugin(page);
  });

  test.afterEach(async ({}, testInfo) => {
    deletePluginSetting(testInfo.project.name, 'backgroundImage');
  });

  test('a background can be uploaded and then removed without replacing it', async ({ page }, testInfo) => {
    const project = testInfo.project.name;

    // --- upload -------------------------------------------------------------
    await openPluginSettings(page);
    await page.locator('#backgroundImage').setInputFiles(FIXTURE);
    await submitSettingsForm(page);

    clearSettingsCache(project);
    const uploadedPath = getPluginSetting(project, 'backgroundImage');
    expect(uploadedPath, 'upload should store a path in the backgroundImage setting').not.toBe('');
    expect(fileExistsInContainer(project, uploadedPath)).toBe(true);

    // --- remove -------------------------------------------------------------
    await openPluginSettings(page);

    const removeCheckbox = page.locator('#certificateSettingsForm [id^="removeBackgroundImage"]').first();
    await expect(
      removeCheckbox,
      'a remove control must be offered once a background is set (Issue #73)',
    ).toBeAttached({ timeout: 15000 });

    await removeCheckbox.check({ force: true });
    await submitSettingsForm(page);

    clearSettingsCache(project);
    expect(
      getPluginSetting(project, 'backgroundImage'),
      'ticking remove must clear the setting, with no replacement upload',
    ).toBe('');

    // The orphaned file should be gone too, not left behind in the files dir.
    expect(fileExistsInContainer(project, uploadedPath)).toBe(false);
  });

  test('the remove control is not offered when no background is set', async ({ page }, testInfo) => {
    deletePluginSetting(testInfo.project.name, 'backgroundImage');

    await openPluginSettings(page);

    await expect(
      page.locator('#certificateSettingsForm [id^="removeBackgroundImage"]'),
    ).toHaveCount(0);
  });
});
