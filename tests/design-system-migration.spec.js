import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

/**
 * The four screens the design system migration rebuilt.
 *
 * Each is named with the thing that proves it rendered from the system rather
 * than from the old stylesheet: a card, drawn by .bw-card, which admin-theme.css
 * knows nothing about.
 */
const REBUILT = [
  ['/wp-admin/admin.php?page=blueworx-labs-wordpress', 'Enhancements'],
  ['/wp-admin/admin.php?page=blueworx-guides', 'Guides'],
  ['/wp-admin/admin.php?page=blueworx-cache', 'Cache'],
  ['/wp-admin/admin.php?page=blueworx-edit-menu', 'Edit Menu'],
];

test.describe('the rebuilt screens no longer need admin-theme.css', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('each one renders from the design system with the old stylesheet blocked', async ({
    page,
  }) => {
    test.slow();

    // The point of the migration, stated as a request that never arrives. If a
    // screen still leans on admin-theme.css for its layout, blocking the file
    // is what shows it — the cards collapse or lose their surface.
    await page.route('**/assets/css/admin-theme.css*', (route) => route.abort());
    await page.setViewportSize({ width: 1600, height: 900 });
    await login(page);

    for (const [url, name] of REBUILT) {
      await page.goto(url);

      const card = page.locator('.bw-admin .bw-card').first();
      await expect(card, `${name} has no card`).toBeVisible();

      // A card with no surface of its own means the old stylesheet was painting
      // it, which is exactly what this is here to catch.
      await expect(card, `${name}'s card has no background of its own`).not.toHaveCSS(
        'background-color',
        'rgba(0, 0, 0, 0)'
      );

      // Deliberately no width assertion. How wide a card ends up depends on the
      // padding wp-admin puts around the content area, and blocking the re-skin
      // changes that — so a number measured here would be measuring the wrong
      // thing. tests/admin-theme.spec.js holds the width, with the file loaded.
    }
  });

  test('what is left in admin-theme.css is the re-skin, not our screens', async ({ page }) => {
    await login(page);

    // The file styles WordPress's own furniture — its menu, its top bar, its
    // list tables, its settings screens. Nothing in it is scoped to a BlueWorx
    // screen, which is why the migration removed no rules from it: there were
    // none to remove. This holds that, so a future rebuild does not quietly put
    // screen styling back here instead of into the design system.
    const css = await page.request.get('/wp-content/plugins/blueworx-labs-wordpress/assets/css/admin-theme.css');
    expect(css.status()).toBe(200);

    const text = await css.text();
    const scoped = text.match(/(toplevel_page_blueworx|blueworx_page_blueworx)[^{,\s]*/g) || [];
    expect(scoped, 'admin-theme.css has picked up a rule scoped to one of our screens').toEqual([]);
  });
});
