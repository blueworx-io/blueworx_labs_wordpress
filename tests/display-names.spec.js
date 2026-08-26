/**
 * Friendlier names for roles and plugins.
 *
 * The rules themselves are checked in tests/php/display-names-test.php, which
 * is text in and text out. What is checked here is the part only a running site
 * can answer: that the new name reaches the screens people actually use, and
 * that switching it off really does put the original back — the whole promise
 * of a rename that changes nothing underneath.
 *
 * Roles are what this drives, because the test site has WordPress's own roles
 * and none of the plugins the rest of the list names. The plugin half of the
 * map is covered by the PHP checks until those plugins are on the test site
 * (issue #181).
 */
import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  setFeature,
  saveEnhancements,
  featureIsOn,
  restoreAll,
} from './helpers.js';

const ENHANCEMENTS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

test.describe('Friendlier names', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('a role reads as the job it does, and switching it off puts the old name back', async ({
    page,
  }) => {
    await login(page);
    await page.goto(ENHANCEMENTS_PATH);

    const wasOn = await featureIsOn(page, 'display_names');
    const roleFilter = page.locator('#new_role');

    try {
      await setFeature(page, 'display_names', true);
      await saveEnhancements(page);

      await page.goto('/wp-admin/users.php');

      // The dropdown every site owner uses to move somebody between roles. If
      // the new name is anywhere, it is here.
      await expect(roleFilter.locator('option', { hasText: /^Customer$/ })).toHaveCount(1);
      await expect(roleFilter.locator('option', { hasText: /^Subscriber$/ })).toHaveCount(0);

      // The four WordPress roles say what they are already, and are left alone.
      for (const kept of ['Administrator', 'Editor', 'Author', 'Contributor']) {
        await expect(
          roleFilter.locator('option', { hasText: new RegExp(`^${kept}$`) }),
          `${kept} must keep its own name`
        ).toHaveCount(1);
      }

      // Our own screens read the same name as core's, because both ask
      // WordPress rather than each keeping a list.
      await page.goto('/wp-admin/user-new.php');
      await expect(page.locator('.blueworx-role-choices')).toContainText('Customer');
      await expect(page.locator('.blueworx-role-choices')).not.toContainText('Subscriber');

      // Nothing was written anywhere: the slug behind the new name is still
      // the one WordPress has always used, so nobody's account moved.
      await expect(page.locator('.blueworx-role-choices input[value="subscriber"]')).toHaveCount(1);

      await page.goto(ENHANCEMENTS_PATH);
      await setFeature(page, 'display_names', false);
      await saveEnhancements(page);

      await page.goto('/wp-admin/users.php');
      await expect(roleFilter.locator('option', { hasText: /^Subscriber$/ })).toHaveCount(1);
      await expect(roleFilter.locator('option', { hasText: /^Customer$/ })).toHaveCount(0);
    } finally {
      await restoreAll([
        [
          'display_names back to how it was found',
          async () => {
            await page.goto(ENHANCEMENTS_PATH);
            await setFeature(page, 'display_names', wasOn);
            await saveEnhancements(page);
          },
        ],
      ]);
    }
  });
});
