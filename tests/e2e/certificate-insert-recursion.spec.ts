import { test, expect } from '@playwright/test';
import { loginAsAdmin, loginAsReviewerWithRetry, enablePlugin } from './helpers/ojs-auth';
import {
  truncateCertificates,
  completedReviewId,
  queryValue,
  errorLogMark,
  errorLogSince,
} from './helpers/ojs-db';

/**
 * Regression test for the CertificateDAO::getInsertId() infinite recursion.
 *
 * pkp-lib 3.4 declares DAO::_getInsertId() as a deprecated shim whose body is
 * `return $this->getInsertId();`. The plugin used to override getInsertId() and
 * call _getInsertId() from inside it, so the two bounced off each other until
 * PHP's stack was exhausted — ~47,000 frames and an HTTP 500.
 *
 * It only fired on the INSERT path, so it hit every reviewer's FIRST certificate
 * download and nothing afterwards. That is why the table is emptied first: with a
 * row already present the download takes the update path and the bug is invisible.
 *
 * Reported against OJS 3.4.0.10, which is exactly the image the ojs34 project runs.
 */
test.describe('Certificate creation on first download (getInsertId recursion)', () => {
  test.describe.configure({ timeout: 120_000 });

  test('first download creates the certificate and returns a PDF', async ({ page }, testInfo) => {
    const project = testInfo.project.name;

    await loginAsAdmin(page);
    await enablePlugin(page);
    await page.context().clearCookies();

    // Force the create path.
    truncateCertificates(project);
    expect(queryValue(project, 'SELECT COUNT(*) FROM reviewer_certificates;')).toBe('0');

    const reviewId = completedReviewId(project);
    expect(reviewId, 'seeded testreviewer should have a completed review').toBeGreaterThan(0);

    await loginAsReviewerWithRetry(page);

    // Only look at what THIS download writes to the error log.
    const logMark = errorLogMark(project);

    const response = await page.request.get(
      `/index.php/testjournal/certificate/download/${reviewId}`,
    );

    expect(
      response.status(),
      'first download must not 500 — that is the recursion crash',
    ).toBe(200);
    expect(response.headers()['content-type'] || '').toContain('application/pdf');

    const body = await response.body();
    expect(body.length, 'a real PDF should come back').toBeGreaterThan(1000);
    expect(body.slice(0, 5).toString()).toBe('%PDF-');

    // The row must exist AND carry a real primary key: before the fix the INSERT
    // committed but the ID was never retrieved.
    const certificateId = queryValue(
      project,
      `SELECT certificate_id FROM reviewer_certificates WHERE review_id = ${reviewId};`,
    );
    expect(parseInt(certificateId, 10)).toBeGreaterThan(0);

    // The crash used to write tens of thousands of frames per attempt. How it
    // surfaces depends on the PHP version: 8.3+ reports "Maximum call stack size"
    // (what the reporter saw), older builds simply exhaust the memory limit first.
    const newLogs = errorLogSince(project, logMark);
    expect(newLogs).not.toMatch(/Maximum call stack size/i);
    expect(newLogs).not.toMatch(/Infinite recursion/i);
    expect(newLogs).not.toMatch(/Allowed memory size/i);
    expect(newLogs).not.toMatch(/CertificateDAO\.php/i);
  });

  test('second download reuses the existing certificate', async ({ page }, testInfo) => {
    const project = testInfo.project.name;

    await loginAsReviewerWithRetry(page);
    const reviewId = completedReviewId(project);

    const first = await page.request.get(`/index.php/testjournal/certificate/download/${reviewId}`);
    expect(first.status()).toBe(200);

    const certificateId = queryValue(
      project,
      `SELECT certificate_id FROM reviewer_certificates WHERE review_id = ${reviewId};`,
    );

    const second = await page.request.get(`/index.php/testjournal/certificate/download/${reviewId}`);
    expect(second.status()).toBe(200);
    expect((second.headers()['content-type'] || '')).toContain('application/pdf');

    // Same row reused — the update path, not a second insert.
    expect(
      queryValue(project, `SELECT certificate_id FROM reviewer_certificates WHERE review_id = ${reviewId};`),
    ).toBe(certificateId);
    expect(
      queryValue(project, 'SELECT COUNT(*) FROM reviewer_certificates WHERE review_id = ' + reviewId + ';'),
    ).toBe('1');
  });
});
