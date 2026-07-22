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

test.describe('Commerce widgets — pricing calculator', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('recomputes the monthly total from the controls', async ({ page }) => {
    await page.goto('/pricing');
    const total = page.locator('[data-testid="calc-total"]');
    const calc = page.locator('[data-widget="pricing-calc"]');

    await expect(total).toHaveText('$600'); // growth + 1 extra update pack + hosting

    await calc.locator('.opt', { hasText: 'Essential' }).click();
    await expect(total).toHaveText('$300'); // 200 + 60 + 40

    await calc.locator('.stepper[data-field="sites"] button', { hasText: '+' }).click();
    await expect(total).toHaveText('$420'); // 200 + 60 + 120 + 40

    await calc.locator('.toggle-pill').click();
    await expect(total).toHaveText('$380'); // hosting off
  });

  test('steppers clamp at their bounds', async ({ page }) => {
    await page.goto('/pricing');
    const updates = page.locator('.stepper[data-field="updates"]');
    for (let i = 0; i < 8; i++) await updates.locator('button', { hasText: '+' }).click();
    await expect(updates.locator('b')).toHaveText('6'); // max 6
  });
});

test.describe('Commerce widgets — savings calculator', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('default totals and per-tool recompute', async ({ page }) => {
    await page.goto('/toolbox');
    const solo = page.locator('[data-testid="solo-total"]');
    const save = page.locator('[data-testid="savings-line"]');

    await expect(solo).toHaveText('190');                       // sum 160 + 30 hosting
    await expect(save).toHaveText('You save $160/mo · $1,920/yr'); // 190 - 30

    // Toggle SureCart ($19) off.
    await page.locator('.sv-row[data-slug="surecart"] .toggle-pill').click();
    await expect(solo).toHaveText('171');                       // 190 - 19
    await expect(save).toHaveText('You save $141/mo · $1,692/yr'); // 171 - 30
  });
});
