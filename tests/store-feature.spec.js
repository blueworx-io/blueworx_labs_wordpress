/**
 * Store pages feature registration.
 *
 * Task 1 only registers the feature, its Store section and its guide — there
 * is nothing user-facing yet beyond this row on the Enhancements screen. This
 * checks the feature is listed under its own Store section and is on by
 * default, the way the brief specifies.
 */
import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  openSectionFor,
  featureIsOn,
} from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

test.describe('Store pages feature', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('is listed under Store and is on by default', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await openSectionFor(page, 'store_pages');

    const toggle = page.locator(
      'input.blueworx-feature-toggle[data-blueworx-feature="store_pages"]'
    );
    await expect(toggle).toBeVisible();
    await expect(toggle).toBeChecked();
    expect(await featureIsOn(page, 'store_pages')).toBe(true);
  });
});
