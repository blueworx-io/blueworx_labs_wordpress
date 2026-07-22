// Public marketing page — logged out — so every navigation goes through
// cacheBust(). See tests/helpers.js.
import { expect } from '@playwright/test';
import { test, isPlaceholder, cacheBust } from './helpers.js';

test.describe('Marketing work page', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('renders the two-column hero, six project cards, stats and testimonials', async ({
    page,
  }) => {
    await page.goto(cacheBust('/work/'));

    await expect(page.locator('.bw-page')).toHaveCount(1);
    await expect(page.locator('.tech-hero .tech-2col')).toHaveCount(1);
    // Six projects, rendered as plain (non-linked) cards in the source.
    await expect(page.locator('.work-grid .work-card')).toHaveCount(6);
    await expect(page.locator('.work-grid a.work-card')).toHaveCount(0);
    await expect(page.locator('.stats-band')).toHaveCount(1);
  });

  test('uses Work-specific testimonials and heading, not the shared reviews', async ({ page }) => {
    await page.goto(cacheBust('/work/'));
    // The testimonials part renders its heading in .center-head (a sibling of
    // the .tg card grid), so assert the override at the page level and the
    // Work-specific reviewer inside the grid.
    await expect(page.locator('.center-head')).toContainText("Partners Who'd Recommend Us");
    await expect(page.locator('.tg')).toContainText('Sarah Johnson');
  });

  test('project images are served by the plugin, not the front-end origin', async ({ page }) => {
    await page.goto(cacheBust('/work/'));
    const srcs = await page
      .locator('.work-grid img')
      .evaluateAll((imgs) => imgs.map((i) => i.getAttribute('src') || ''));
    expect(srcs).toHaveLength(6);
    expect(srcs.every((s) => /\/assets\/img\//.test(s))).toBe(true);
  });
});
