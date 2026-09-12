import { test, expect } from '@playwright/test';
import { loginAsAdmin, loginAsReviewer, enablePlugin } from './helpers/ojs-auth';
import { queryValue, truncateCertificates, setPluginSetting, getPluginSetting, fileExistsInContainer, fileModeInContainer } from './helpers/ojs-db';

let manage: string;
let pageBatch: string;
test.describe('Secure certificate batches', () => {
  test.beforeEach(async ({ page }, info) => {
    await loginAsAdmin(page);
    await enablePlugin(page);
    // Use the actual component handler and canonical URL (3.5 adds a locale).
    const settings = await page.request.get('/index.php/testjournal/$$$call$$$/grid/settings/plugins/settings-plugin-grid/manage?category=generic&plugin=reviewercertificateplugin&verb=settings');
    expect(settings.ok(), await settings.text()).toBeTruthy();
    manage = settings.url().replace('&verb=settings', '');
    pageBatch = '/testjournal/certificate/generateBatch';
    setPluginSetting(info.project.name, 'minimumReviews', '1', 'int');
  });

  test('settings route issues once and catch-up delivers once per certificate', async ({ page }, info) => {
    const project = info.project.name;
    truncateCertificates(project);
    const reviewer = queryValue(project, "SELECT user_id FROM users WHERE username='testreviewer'");
    const settings = await page.request.get(manage + '&verb=settings');
    expect(settings.ok()).toBeTruthy();
    const settingsJson = await settings.json();
    expect(settingsJson.status).toBe(true);
    expect(settingsJson.content).toContain('notificationReviewers');
    const token = settingsJson.content.match(/name="csrfToken"[^>]*value="([^"]+)"/)?.[1];
    expect(token).toBeTruthy();
    const form = { 'reviewerIds[]': reviewer, csrfToken: token };
    const generate = async () => (await (await page.request.post(manage + '&verb=generateBatch', { form })).json());
    const first = await generate();
    expect(first.status).toBe(true);
    expect(first.content.generated).toBe(2);
    expect(queryValue(project, 'SELECT COUNT(*) FROM reviewer_certificates')).toBe('2');
    const second = await generate();
    expect(second.content.generated).toBe(0);
    expect(second.content.skipped).toBe(2);

    // Raising the issuance threshold must not hide already-issued certificates
    // from the historical-notification picker or prevent their announcement.
    setPluginSetting(project, 'minimumReviews', '10', 'int');
    const raised = await (await page.request.get(manage + '&verb=settings')).json();
    const picker = raised.content.match(/id="notificationReviewers"[\s\S]*?<\/select>/)?.[0] || '';
    expect(picker).toContain('value="' + reviewer + '"');

    const recipient = 'reviewer-' + project + '@test.local';
    const mailpit = process.env.MAILPIT_API || 'http://localhost:8125/api/v1';
    const count = async () => {
      const response = await page.request.get(mailpit + '/search?query=' + encodeURIComponent('to:' + recipient));
      expect(response.ok()).toBeTruthy();
      return (await response.json()).messages_count;
    };
    const before = await count();
    const notify = async () => (await (await page.request.post(manage + '&verb=notifyBatch', { form })).json());
    const sent = await notify();
    expect(sent.status, JSON.stringify(sent)).toBe(true);
    expect(sent.content.sent).toBe(2);
    await expect.poll(count).toBe(before + 2);
    expect(queryValue(project, "SELECT COUNT(*) FROM reviewer_certificate_notifications WHERE status='sent'")).toBe('2');
    expect((await notify()).content.sent).toBe(0);
    expect(await count()).toBe(before + 2);
  });

  test('POST, CSRF and role checks prevent database writes on both routes', async ({ page }, info) => {
    const project = info.project.name;
    truncateCertificates(project);
    const reviewer = queryValue(project, "SELECT user_id FROM users WHERE username='testreviewer'");
    for (const url of [manage + '&verb=generateBatch', pageBatch]) {
      expect((await page.request.get(url)).status()).toBe(405);
      expect((await page.request.post(url, { form: { 'reviewerIds[]': reviewer } })).status()).toBe(403);
    }
    expect(queryValue(project, 'SELECT COUNT(*) FROM reviewer_certificates')).toBe('0');
    await page.context().clearCookies();
    await loginAsReviewer(page);
    const response = await page.request.post(manage + '&verb=generateBatch', { form: { 'reviewerIds[]': reviewer } });
    // OJS's component authorization runs before the plugin and may render
    // an HTTP 200 permission-denied page; it must never report batch success.
    const denied = await response.text();
    expect(response.status() === 403 || /permission|authorized|access/i.test(denied)).toBeTruthy();
    expect(denied).not.toContain('"status":true');
    expect(queryValue(project, 'SELECT COUNT(*) FROM reviewer_certificates')).toBe('0');
  });

  test('multipart validation preserves files and successful replacement honors core permissions', async ({ page }, info) => {
    const project = info.project.name;
    const settings = await (await page.request.get(manage + '&verb=settings')).json();
    const csrfToken = settings.content.match(/name="csrfToken"[^>]*value="([^"]+)"/)?.[1];
    const image = { name: 'same-name.png', mimeType: 'image/png',
      buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS9sAAAAASUVORK5CYII=', 'base64') };
    const save = async (bodyTemplate: string, upload = true) => {
      const response = await page.request.post(manage + '&verb=settings&save=1', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        multipart: { csrfToken, bodyTemplate, minimumReviews: '1', headerText: '',
          ...(upload ? { backgroundImage: image } : { removeBackgroundImage: '1' }) },
      });
      return response.json();
    };
    expect((await save('Certificate')).status).toBe(true);
    const first = getPluginSetting(project, 'backgroundImage');
    expect(fileExistsInContainer(project, first)).toBe(true);
    // Disposable setup deliberately uses [files].umask = 0027.
    expect(fileModeInContainer(project, first)).toBe('640');
    expect((await save('')).status).toBe(false);
    expect(getPluginSetting(project, 'backgroundImage')).toBe(first);
    expect(fileExistsInContainer(project, first)).toBe(true);
    expect((await save('Replacement')).status).toBe(true);
    const second = getPluginSetting(project, 'backgroundImage');
    expect(second).not.toBe(first);
    expect(fileExistsInContainer(project, first)).toBe(false);
    expect(fileModeInContainer(project, second)).toBe('640');
    expect((await save('Certificate', false)).status).toBe(true);
    expect(fileExistsInContainer(project, second)).toBe(false);
  });
});
