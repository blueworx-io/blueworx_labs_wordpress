import { expect } from '@playwright/test';
import { test, isPlaceholder } from './helpers.js';

const BASE = process.env.PLAYWRIGHT_BASE_URL || '';

test.describe('Commerce widgets — billing toggle', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  for (const page of [
    { path: '/pricing', plan: 'Growth Support', m: '$500', a: '$400' },
    { path: '/toolbox', plan: 'Business', m: '$60', a: '$50' },
  ]) {
    test(`toggle swaps monthly/annual prices on ${page.path}`, async ({ page: pw }) => {
      await pw.goto(page.path);
      const card = pw.locator('.plan-card', { hasText: page.plan }).first();
      const price = card.locator('.plan-price b');
      const sub = card.locator('.plan-price em');

      await expect(price).toHaveText(page.m);
      await expect(sub).toHaveText('per month');

      await pw.locator('[data-widget="billing-toggle"] button', { hasText: 'Annual' }).click();
      await expect(price).toHaveText(page.a);
      await expect(sub).toHaveText('per month, billed yearly');

      await pw.locator('[data-widget="billing-toggle"] button', { hasText: 'Monthly' }).click();
      await expect(price).toHaveText(page.m);
      await expect(sub).toHaveText('per month');
    });
  }
});
