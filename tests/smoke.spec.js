import { test, expect } from '@playwright/test';

const baseURL =
  process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || 'https://staging.placeholder.blueworx.io';
const isPlaceholder = /placeholder/i.test(baseURL);

test('site responds at the base URL', async ({ page }) => {
  test.skip(isPlaceholder, 'No real staging/preview URL configured yet (placeholder in use).');
  const response = await page.goto('/');
  expect(response, 'expected a response from the base URL').toBeTruthy();
  expect(response.status(), 'expected a non-error HTTP status').toBeLessThan(400);
});
