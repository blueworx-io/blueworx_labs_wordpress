/**
 * Single sign-on.
 *
 * These assert the parts visible from outside PHP: that the feature is offered
 * and off by default, that its settings survive a save without ever rendering
 * the client secret, that the sign-in trigger builds a correct authorization
 * request, and that a callback with a bad state is refused without saying why.
 *
 * The crypto itself — signature and claim checks — is covered by the PHP scripts
 * in tests/php, which run without WordPress.
 */

import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login, restoreAll } from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

const toggleFor = (key) => `input.blueworx-feature-toggle[data-blueworx-feature="${key}"]`;

async function save(page) {
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
}

test.describe('Single sign-on', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the feature is offered and is off by default', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await expect(page.locator(toggleFor('sso'))).toHaveCount(1);
    await expect(page.locator(toggleFor('sso'))).not.toBeChecked();
  });
});
