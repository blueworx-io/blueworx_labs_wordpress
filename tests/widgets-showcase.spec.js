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

test.describe('Showcase — FAQ accordion', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('opening one FAQ closes the others in the same list', async ({ page }) => {
    await page.goto('/contact');
    const items = page.locator('.faq-list details.faq-item');
    await items.nth(0).locator('summary').click();
    await expect(items.nth(0)).toHaveAttribute('open', '');
    await items.nth(1).locator('summary').click();
    await expect(items.nth(1)).toHaveAttribute('open', '');
    await expect(items.nth(0)).not.toHaveAttribute('open', '');
  });
});
