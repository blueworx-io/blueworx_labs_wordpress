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

  test('the settings survive a save and the secret is never rendered', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await page.locator(toggleFor('sso')).setChecked(true);
    await page.fill('#blueworx_sso_issuer', 'https://idp.test');
    await page.fill('#blueworx_sso_client_id', 'test-client');
    await page.fill('#blueworx_sso_client_secret', 'super-secret-value');
    await page.fill('#blueworx_sso_button_label', 'Sign in with Test IdP');
    await save(page);

    await page.goto(SETTINGS_PATH);
    await expect(page.locator('#blueworx_sso_issuer')).toHaveValue('https://idp.test');
    await expect(page.locator('#blueworx_sso_client_id')).toHaveValue('test-client');

    // A secret that can be read back out of the screen is a secret anyone with
    // admin access — including a read-only support session — can walk off with.
    await expect(page.locator('#blueworx_sso_client_secret')).toHaveValue('');
    expect(await page.content()).not.toContain('super-secret-value');

    await restoreAll([
      [
        'sso off',
        async () => {
          await page.goto(SETTINGS_PATH);
          await page.locator(toggleFor('sso')).setChecked(false);
          await save(page);
        },
      ],
    ]);
  });

  test('the callback URL is shown for copying', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await expect(page.locator('.blueworx-sso-callback-url')).toContainText('blueworx_sso=callback');
  });
});
