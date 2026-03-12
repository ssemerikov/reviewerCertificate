import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 60000,
  retries: 1,
  use: {
    headless: true,
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'ojs33', use: { baseURL: 'http://localhost:8033' } },
    { name: 'ojs34', use: { baseURL: 'http://localhost:8034' } },
    { name: 'ojs35', use: { baseURL: 'http://localhost:8035' } },
  ],
});
