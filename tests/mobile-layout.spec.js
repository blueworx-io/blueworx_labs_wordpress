import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

/**
 * Every screen the plugin renders from the design system.
 *
 * All four share one layout wrapper, so a layout bug in that wrapper is a bug
 * on all four at once — which is how the section nav kept a 220px column on a
 * phone and squeezed the panels beside it down to about 76px.
 */
const SCREENS = [
  ['/wp-admin/admin.php?page=blueworx-labs-wordpress', 'Enhancements'],
  ['/wp-admin/admin.php?page=blueworx-guides', 'Guides'],
  ['/wp-admin/admin.php?page=blueworx-cache', 'Cache'],
  ['/wp-admin/admin.php?page=blueworx-edit-menu', 'Edit Menu'],
];

/** A small phone, a large phone, and the last width before the desktop layout. */
const WIDTHS = [390, 430, 782];

test.describe('the screens fit on a phone', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('nothing pushes the page sideways at any phone width', async ({ page }) => {
    test.slow();

    await login(page);

    for (const width of WIDTHS) {
      await page.setViewportSize({ width, height: 844 });

      for (const [url, name] of SCREENS) {
        await page.goto(url);

        // A horizontally scrolling admin screen is the whole of what "mobile
        // does not work" means in practice: the save bar drifts off to the
        // right, and half the controls go with it.
        const overflow = await page.evaluate(() => {
          const root = document.documentElement;
          return root.scrollWidth - root.clientWidth;
        });

        expect(overflow, `${name} scrolls sideways at ${width}px`).toBeLessThanOrEqual(1);
      }
    }
  });

  test('a wide tab strip scrolls inside itself rather than stretching the page', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-guides');

    const tabs = page.locator('.bw-tabs').first();
    await expect(tabs).toBeVisible();

    // The strip is allowed to be wider than the screen — that is what its own
    // overflow is for. What it may not do is make its container wider, which
    // is what happens if the layout lets a flex child size to its content.
    const fits = await tabs.evaluate((el) => el.clientWidth <= document.documentElement.clientWidth);

    expect(fits, 'the tab strip is wider than the screen').toBe(true);
  });
});
