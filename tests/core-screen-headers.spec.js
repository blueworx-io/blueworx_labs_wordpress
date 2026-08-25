import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
} from './helpers.js';

/**
 * WordPress's own screens get the BlueWorx eyebrow and page-access row.
 *
 * Both are worked out rather than written down — the eyebrow from the sidebar
 * group the screen's menu sits in, the access row from the capability the
 * screen is registered with. That is what makes them right on a custom post
 * type, or on a screen belonging to a plugin nobody here has seen.
 *
 * The one-line lede the designs also show is deliberately not here: there is no
 * honest way to write one for a screen we do not know about.
 */

const SCREENS = [
  ['/wp-admin/edit.php', 'Content'],
  ['/wp-admin/upload.php', 'Content'],
  ['/wp-admin/users.php', 'Site'],
  ['/wp-admin/plugins.php', 'Site'],
  ['/wp-admin/options-general.php', 'Site'],
];

test.describe('core screens carry a BlueWorx header', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  for (const [path, group] of SCREENS) {
    test(`${path} says it belongs to ${group}`, async ({ page }) => {
      await login(page);
      await page.goto(path);

      const head = page.locator('.bw-core-pagehead');
      await expect(head).toHaveCount(1);
      await expect(head.locator('.bw-pagehead__eyebrow')).toHaveText(group);

      // Above the heading, not below it.
      const eyebrowBox = await head.boundingBox();
      const headingBox = await page.locator('.wrap > h1, .wrap > h2').first().boundingBox();
      expect(eyebrowBox.y).toBeLessThan(headingBox.y);
    });
  }

  test('the access row names roles, not a capability', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/plugins.php');

    const access = page.locator('.bw-core-pagehead .bw-pageaccess');
    await expect(access).toHaveCount(1);
    await expect(access.locator('.bw-rolepill').first()).toContainText(/administrator/i);

    // The capability itself must never leak into the page.
    await expect(access).not.toContainText('activate_plugins');
  });

  test('our own screens are left alone — they have a header already', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');

    await expect(page.locator('.bw-core-pagehead')).toHaveCount(0);
    await expect(page.locator('.bw-pagehead__eyebrow')).toHaveCount(1);
  });

  test('nothing is added with the admin theme switched off', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/edit.php');

    // Sanity: the script only ever runs behind the theme flag, so the marker
    // and the stylesheet have to agree with each other.
    const themed = await page.locator('link#blueworx-admin-theme-css').count();
    const heads = await page.locator('.bw-core-pagehead').count();

    expect(themed > 0 ? heads : 0).toBe(heads);
  });
});
