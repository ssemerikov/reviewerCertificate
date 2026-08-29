import { test, expect } from '@playwright/test';
import { loginAsReviewerWithRetry } from './helpers/ojs-auth';
import {
  setPluginSetting,
  deletePluginSetting,
  completedReviewId,
} from './helpers/ojs-db';
import { pdfText, wordTopY } from './helpers/pdf-text';

/**
 * Issue #74 — "Body text first line fixed position and make Header text field optional".
 *
 * The header used to be drawn unconditionally into a fixed 20 mm cell followed by a
 * fixed 10 mm gap, so the body's first line was pinned at 45 mm on every certificate.
 * Blank lines typed at the top of the body template could not move it either, because
 * OJS trims user vars on save.
 *
 * These tests read the real generated PDF and assert where the body actually lands.
 */
const HEADER = 'ZZHEADERMARKER';
const BODY = 'ZZBODYMARKER awarded to a reviewer.';
const BODY_WORD = 'ZZBODYMARKER';

test.describe('Certificate layout (Issue #74)', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    const project = testInfo.project.name;
    setPluginSetting(project, 'bodyTemplate', BODY);
    setPluginSetting(project, 'bodyTopOffset', '0', 'int');
    await loginAsReviewerWithRetry(page);
  });

  test.afterEach(async ({}, testInfo) => {
    const project = testInfo.project.name;
    deletePluginSetting(project, 'bodyTopOffset');
    deletePluginSetting(project, 'headerText');
    deletePluginSetting(project, 'bodyTemplate');
  });

  async function certificatePdf(page, project: string): Promise<Buffer> {
    const reviewId = completedReviewId(project);
    const response = await page.request.get(
      `/index.php/testjournal/certificate/download/${reviewId}`,
    );
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type'] || '').toContain('application/pdf');
    return await response.body();
  }

  test('clearing the header removes it and lifts the body up the page', async ({ page }, testInfo) => {
    const project = testInfo.project.name;

    setPluginSetting(project, 'headerText', HEADER);
    const withHeader = await certificatePdf(page, project);

    expect(pdfText(withHeader)).toContain(HEADER);
    const bodyYWithHeader = wordTopY(withHeader, BODY_WORD);
    expect(bodyYWithHeader, 'body marker should be found in the PDF').not.toBeNull();

    // Explicitly empty: no heading at all.
    setPluginSetting(project, 'headerText', '');
    const withoutHeader = await certificatePdf(page, project);

    expect(
      pdfText(withoutHeader),
      'an empty header must not fall back to the default heading',
    ).not.toContain(HEADER);

    const bodyYWithoutHeader = wordTopY(withoutHeader, BODY_WORD);
    expect(bodyYWithoutHeader).not.toBeNull();

    // The 30 mm the header used to reserve is gone, so the body starts higher.
    expect(
      bodyYWithoutHeader!,
      'with no header the body should move up the page, not stay pinned',
    ).toBeLessThan(bodyYWithHeader!);
  });

  test('bodyTopOffset moves the body down the page', async ({ page }, testInfo) => {
    const project = testInfo.project.name;

    setPluginSetting(project, 'headerText', '');
    setPluginSetting(project, 'bodyTopOffset', '0', 'int');
    const noOffset = await certificatePdf(page, project);
    const yNoOffset = wordTopY(noOffset, BODY_WORD);
    expect(yNoOffset).not.toBeNull();

    setPluginSetting(project, 'bodyTopOffset', '40', 'int');
    const withOffset = await certificatePdf(page, project);
    const yWithOffset = wordTopY(withOffset, BODY_WORD);
    expect(yWithOffset).not.toBeNull();

    // 40 mm is ~113 pt; allow slack for rounding but require a real shift.
    expect(
      yWithOffset! - yNoOffset!,
      'a 40 mm offset should push the body meaningfully down the page',
    ).toBeGreaterThan(50);
  });

  /**
   * Guards the other direction of Issue #74: making the header optional must not
   * move it for the journals that DO set one.
   *
   * Caught a real regression during development — switching the header from Cell to
   * MultiCell (to let long headings wrap) lifted it ~13 pt on every certificate,
   * because the two align text differently inside the 20 mm band. The coordinates
   * below are the historic layout: header centred in a 20 mm cell starting at the
   * 15 mm top margin, body 10 mm below it.
   */
  test('a configured header leaves the layout exactly where it was', async ({ page }, testInfo) => {
    const project = testInfo.project.name;

    setPluginSetting(project, 'headerText', HEADER);
    const pdf = await certificatePdf(page, project);

    const headerY = wordTopY(pdf, HEADER);
    const bodyY = wordTopY(pdf, BODY_WORD);

    expect(headerY).not.toBeNull();
    expect(bodyY).not.toBeNull();

    // Tolerances are wide enough for font-metric noise but far tighter than the
    // ~13 pt shift a vertical-alignment change introduces.
    expect(headerY!).toBeGreaterThan(52);
    expect(headerY!).toBeLessThan(62);
    expect(bodyY!).toBeGreaterThan(123);
    expect(bodyY!).toBeLessThan(133);
  });

  test('an unset header still gets the historic default', async ({ page }, testInfo) => {
    const project = testInfo.project.name;

    // Never configured — existing journals must keep their heading.
    deletePluginSetting(project, 'headerText');
    const pdf = await certificatePdf(page, project);

    expect(pdfText(pdf)).toContain('Certificate of Recognition');
  });
});
