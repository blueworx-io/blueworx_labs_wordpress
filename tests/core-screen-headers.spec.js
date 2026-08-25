import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
} from './helpers.js';

/**
 * WordPress's own screens keep their own heading, and nothing else.
 *
 * We used to add a BlueWorx eyebrow and a page-access row above the heading of
 * every core screen. It read as though the screen were ours when it is not, so
 * both were taken away again: those two belong on BlueWorx screens only.
 *
 * The rest of the re-skin on core screens stays — this is about what we add
 * above the heading, not about how the screen is styled.
 */

const CORE_SCREENS = [
  '/wp-admin/index.php',
  '/wp-admin/edit.php',
  '/wp-admin/upload.php',
  '/wp-admin/users.php',
  '/wp-admin/plugins.php',
  '/wp-admin/options-general.php',
];

test.describe('core screens carry no BlueWorx header', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  for (const path of CORE_SCREENS) {
    test(`${path} adds no title block and no page access`, async ({ page }) => {
      await login(page);
      await page.goto(path);

      // The re-skin is on: without this the screen would be bare of our markup
      // for the wrong reason and the assertions below would prove nothing.
      await expect(page.locator('link#blueworx-admin-theme-css')).toHaveCount(1);

      await expect(page.locator('.bw-pagehead__eyebrow')).toHaveCount(0);
      await expect(page.locator('.bw-pageaccess')).toHaveCount(0);

      // The screen's own heading is untouched.
      await expect(page.locator('.wrap > h1, .wrap > h2').first()).toBeVisible();
    });
  }

  test('a BlueWorx screen still has both', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');

    await expect(page.locator('.bw-pagehead__eyebrow')).toHaveCount(1);
    await expect(page.locator('.bw-pageaccess')).toHaveCount(1);
  });
});
