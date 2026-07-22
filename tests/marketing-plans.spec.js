// Public marketing pages — logged out — so every navigation goes through
// cacheBust(). See tests/helpers.js. Covers Pricing and Toolbox, which share
// the plan-cards, comparison-table and billing-toggle structure.
import { expect } from '@playwright/test';
import { test, isPlaceholder, cacheBust } from './helpers.js';

test.describe('Marketing pricing page', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('renders three plan cards, comparison table, calculator placeholder and FAQ', async ({
    page,
  }) => {
    await page.goto(cacheBust('/pricing/'));

    await expect(page.locator('.bw-page')).toHaveCount(1);
    await expect(page.locator('.plans .plan-card')).toHaveCount(3);
    // Exactly one featured plan.
    await expect(page.locator('.plans .plan-card.feat')).toHaveCount(1);
    // Prices carry both monthly and annual data for the Plan 3 toggle to swap.
    await expect(page.locator('.plan-price[data-price-m][data-price-a]')).toHaveCount(3);
    await expect(page.locator('table.cmp')).toHaveCount(1);
    await expect(page.locator('[data-widget="pricing-calc"]')).toBeVisible();
    await expect(page.locator('.faq-list details.faq-item')).toHaveCount(5);
  });

  test('the billing toggle renders with monthly selected', async ({ page }) => {
    await page.goto(cacheBust('/pricing/'));
    await expect(page.locator('.bill-toggle button.on')).toContainText('Monthly');
  });

  test('the Pricing nav link is marked active', async ({ page }) => {
    await page.goto(cacheBust('/pricing/'));
    await expect(page.locator('nav .nav-links a.active')).toContainText('Pricing');
  });
});

test.describe('Marketing toolbox page', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('renders plan cards, comparison, savings placeholder, FAQ and the tool grid', async ({
    page,
  }) => {
    await page.goto(cacheBust('/toolbox/'));

    await expect(page.locator('.plans .plan-card')).toHaveCount(3);
    await expect(page.locator('table.cmp')).toHaveCount(1);
    // The savings calculator lives in the #savings section (an anchor the
    // Services page links to).
    await expect(page.locator('#savings [data-widget="savings-calc"]')).toBeVisible();
    // The dark toolbox grid lists all 12 tools with bundled favicons.
    await expect(page.locator('.tbx .tbx-card')).toHaveCount(12);
    const srcs = await page
      .locator('.tbx-card img')
      .evaluateAll((imgs) => imgs.map((i) => i.getAttribute('src') || ''));
    expect(srcs.every((s) => /\/assets\/img\/tools\//.test(s))).toBe(true);
  });

  test('the Toolbox nav link is marked active', async ({ page }) => {
    await page.goto(cacheBust('/toolbox/'));
    await expect(page.locator('nav .nav-links a.active')).toContainText('Toolbox');
  });
});
