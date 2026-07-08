import { defineConfig } from '@playwright/test';

const baseURL =
  process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || 'https://staging.placeholder.blueworx.io';

export default defineConfig({
  testDir: './tests',
  timeout: 30_000,
  use: { baseURL },
  reporter: 'line',
});
