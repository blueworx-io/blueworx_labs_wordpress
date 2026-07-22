// Public marketing page — logged out — so every navigation goes through
// cacheBust() (Varnish caches logged-out responses; a stale hit reads as a
// broken build). See tests/helpers.js.
import { expect } from '@playwright/test';
import { test, isPlaceholder, cacheBust } from './helpers.js';

test.describe('Marketing services page', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('renders the two-column hero, Service 01 analytics panel, process and Service 02', async ({
    page,
  }) => {
    await page.goto(cacheBust('/services/'));

    // Plugin-rendered, theme-independent.
    await expect(page.locator('.bw-page')).toHaveCount(1);

    // Two-column hero with the glass card beside the copy.
    await expect(page.locator('.tech-hero .tech-2col')).toHaveCount(1);

    // Service 01's bespoke hand-authored sparkline SVG uses a linearGradient
    // with id="fsg" — assert it made it through rather than being dropped.
    await expect(page.locator('#fsg')).not.toHaveCount(0);

    // How It Works.
    await expect(page.locator('.proc-grid .proc')).toHaveCount(4);

    // Testimonials.
    await expect(page.locator('.tg .tc').first()).toBeVisible();
  });

  test('Service 02 tool favicons are served by the plugin, never Google', async ({ page }) => {
    await page.goto(cacheBust('/services/'));

    const srcs = await page
      .locator('img')
      .evaluateAll((imgs) => imgs.map((i) => i.getAttribute('src') || ''));

    // No third-party favicon calls (the whole point of bundling them).
    expect(srcs.some((s) => /google\.com|s2\/favicons/i.test(s))).toBe(false);
    // The Service 02 tool list references bundled favicons.
    expect(srcs.some((s) => /assets\/img\/tools\//i.test(s))).toBe(true);
  });

  test('the services nav link is marked active', async ({ page }) => {
    await page.goto(cacheBust('/services/'));
    await expect(page.locator('nav .nav-links a.active')).toContainText('Services');
  });
});
