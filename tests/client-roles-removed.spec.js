// `test` comes from helpers.js (see feature-toggles.spec.js for why): it carries
// the fixture that opts out of core's wp-admin view transitions.
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

// The three roles the Client Roles feature registered before 1.45.0.
const RETIRED_ROLE_SLUGS = ['blueworx_client_owner', 'blueworx_client_dev', 'blueworx_client_editor'];

async function gotoSettings(page) {
  await login(page);
  await page.goto(SETTINGS_PATH);
}

test.describe('Client Roles removal', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the Client Roles feature toggle is gone from the settings page', async ({ page }) => {
    await gotoSettings(page);

    await expect(
      page.locator('input.blueworx-feature-toggle[data-blueworx-feature="client_roles"]')
    ).toHaveCount(0);
  });

  test('the retired client roles are no longer offered in Site Protection', async ({ page }) => {
    await gotoSettings(page);

    // Site Protection reads wp_roles() live, so a role still registered in the
    // database would show up here — this is the check that the upgrade
    // migration actually removed the definitions, not just the code.
    for (const slug of RETIRED_ROLE_SLUGS) {
      await expect(page.locator(`option[value="${slug}"]`)).toHaveCount(0);
    }
  });

  test('the retired client roles cannot be assigned on the Add User screen', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/user-new.php');

    const roleSelect = page.locator('select#role');
    await expect(roleSelect).toBeVisible();

    for (const slug of RETIRED_ROLE_SLUGS) {
      await expect(roleSelect.locator(`option[value="${slug}"]`)).toHaveCount(0);
    }
  });
});
