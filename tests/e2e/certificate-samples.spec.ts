import { test } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { loginAsReviewerWithRetry } from './helpers/ojs-auth';
import { setPluginSetting, deletePluginSetting, completedReviewId } from './helpers/ojs-db';

const OUT = path.resolve(__dirname, '../../certificate-samples');

const BG = '/var/www/html/files/journals/cert-assets/cert_bg_portrait.png';

type Config = { name: string; label: string; settings: Record<string, [string, string]> };

const CONFIGS: Config[] = [
  { name: '01-default', label: 'Default layout (header + body, portrait)', settings: {
      headerText: ['Certificate of Recognition', 'string'],
      bodyTemplate: ['This certificate is awarded to\n{{$reviewerName}}\nin recognition of their valuable contribution as a peer reviewer for\n{{$journalName}}\nReview completed on {{$reviewDate}}', 'string'],
      footerText: ['Thank you for your service to the scholarly community', 'string'],
      pageOrientation: ['P', 'string'], bodyTopOffset: ['0', 'int'] } },

  { name: '02-no-header', label: 'Header cleared — body moves up (Issue #74)', settings: {
      headerText: ['', 'string'],
      bodyTemplate: ['This certificate is awarded to\n{{$reviewerName}}\nin recognition of their valuable contribution as a peer reviewer for\n{{$journalName}}', 'string'],
      footerText: ['Thank you for your service to the scholarly community', 'string'],
      pageOrientation: ['P', 'string'], bodyTopOffset: ['0', 'int'] } },

  { name: '03-body-offset-30mm', label: 'Header + 30 mm body top spacing (Issue #74)', settings: {
      headerText: ['Certificate of Recognition', 'string'],
      bodyTemplate: ['This certificate is awarded to\n{{$reviewerName}}\nfor peer review work for {{$journalName}}', 'string'],
      footerText: ['', 'string'],
      pageOrientation: ['P', 'string'], bodyTopOffset: ['30', 'int'] } },

  { name: '04-no-header-offset-25mm', label: 'No header + 25 mm spacing', settings: {
      headerText: ['', 'string'],
      bodyTemplate: ['This certificate is awarded to\n{{$reviewerName}}\nfor peer review work for {{$journalName}}', 'string'],
      footerText: ['', 'string'],
      pageOrientation: ['P', 'string'], bodyTopOffset: ['25', 'int'] } },

  { name: '05-landscape', label: 'Landscape orientation', settings: {
      headerText: ['Certificate of Appreciation', 'string'],
      bodyTemplate: ['Awarded to {{$reviewerName}}\nfor reviewing "{{$submissionTitle}}"\nfor {{$journalName}}', 'string'],
      footerText: ['Issued {{$currentDate}}', 'string'],
      pageOrientation: ['L', 'string'], bodyTopOffset: ['0', 'int'] } },

  { name: '06-background-image', label: 'With background image', settings: {
      headerText: ['Certificate of Recognition', 'string'],
      bodyTemplate: ['Awarded to {{$reviewerName}}\nfor peer review work for {{$journalName}}', 'string'],
      footerText: [''  , 'string'],
      backgroundImage: [BG, 'string'],
      pageOrientation: ['P', 'string'], bodyTopOffset: ['0', 'int'] } },

  { name: '07-ukrainian-cyrillic', label: 'Cyrillic content (auto font switch)', settings: {
      headerText: ['Сертифікат рецензента', 'string'],
      bodyTemplate: ['Цей сертифікат видано\n{{$reviewerName}}\nна знак визнання внеску як рецензента журналу\n{{$journalName}}', 'string'],
      footerText: ['Дякуємо за вашу працю', 'string'],
      pageOrientation: ['P', 'string'], bodyTopOffset: ['0', 'int'] } },

  { name: '08-custom-font-colour', label: 'Custom font, size and colour', settings: {
      headerText: ['Certificate of Excellence', 'string'],
      bodyTemplate: ['Awarded to {{$reviewerName}}\nfor peer review work for {{$journalName}}', 'string'],
      footerText: ['Certificate code: {{$certificateCode}}', 'string'],
      fontFamily: ['times', 'string'], fontSize: ['16', 'int'],
      textColorR: ['20', 'int'], textColorG: ['60', 'int'], textColorB: ['120', 'int'],
      pageOrientation: ['P', 'string'], bodyTopOffset: ['0', 'int'] } },
];

const TOUCHED = ['headerText', 'bodyTemplate', 'footerText', 'pageOrientation', 'bodyTopOffset',
                 'backgroundImage', 'fontFamily', 'fontSize', 'textColorR', 'textColorG', 'textColorB'];

/**
 * Utility, not a test: writes one PDF per configuration to certificate-samples/
 * so the rendering can be eyeballed across OJS 3.3, 3.4 and 3.5.
 *
 * Opt-in, because it rewrites the journal's plugin settings while it runs:
 *   GENERATE_SAMPLES=1 npx playwright test certificate-samples --project=ojs34
 */
test('generate certificate samples', async ({ page }, testInfo) => {
  test.skip(!process.env.GENERATE_SAMPLES, 'set GENERATE_SAMPLES=1 to regenerate the sample PDFs');
  test.setTimeout(600_000);
  const project = testInfo.project.name;
  const dir = path.join(OUT, project);
  fs.mkdirSync(dir, { recursive: true });

  await loginAsReviewerWithRetry(page);
  const reviewId = completedReviewId(project);
  const index: string[] = [];

  for (const config of CONFIGS) {
    for (const key of TOUCHED) {
      deletePluginSetting(project, key);
    }
    for (const [key, [value, type]] of Object.entries(config.settings)) {
      setPluginSetting(project, key, value, type);
    }

    const res = await page.request.get(`/index.php/testjournal/certificate/download/${reviewId}`);
    if (res.status() !== 200) {
      index.push(`${config.name}: FAILED (HTTP ${res.status()})`);
      continue;
    }
    const pdf = await res.body();
    const file = path.join(dir, `${config.name}.pdf`);
    fs.writeFileSync(file, pdf);
    index.push(`${config.name}.pdf  (${Math.round(pdf.length / 1024)} KB) — ${config.label}`);
    console.log(`SAMPLE ${project}/${config.name}.pdf ${pdf.length}`);
  }

  for (const key of TOUCHED) {
    deletePluginSetting(project, key);
  }
  fs.writeFileSync(path.join(dir, 'README.txt'),
    `Reviewer Certificate samples — ${project}\n\n${index.join('\n')}\n`);
});
