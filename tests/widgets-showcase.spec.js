import { expect } from '@playwright/test';
import { test, isPlaceholder } from './helpers.js';

test.describe('Showcase — contact-card accessibility', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('each contact card is a real, focusable link', async ({ page }) => {
    await page.goto('/contact');
    const links = page.locator('.contact-cards .cc a');
    await expect(links).toHaveCount(3);
    await expect(links.nth(0)).toHaveAttribute('href', /^tel:/);
    await expect(links.nth(1)).toHaveAttribute('href', /^https:\/\/wa\.me\//);
    await expect(links.nth(2)).toHaveAttribute('href', /^mailto:/);
  });
});
