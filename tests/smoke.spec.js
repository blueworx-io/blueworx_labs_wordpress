import { test, expect } from '@playwright/test';
import { isPlaceholder } from './test-target.js';

test('site responds at the base URL', async ({ page }) => {
  test.skip(isPlaceholder, 'No real staging/preview URL configured yet (placeholder in use).');
  const response = await page.goto('/');
  expect(response, 'expected a response from the base URL').toBeTruthy();
  expect(response.status(), 'expected a non-error HTTP status').toBeLessThan(400);
});
