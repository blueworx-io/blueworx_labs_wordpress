// `test` comes from helpers.js (see feature-toggles.spec.js for why): it carries
// the fixture that opts out of core's wp-admin view transitions.
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

async function gotoSettings(page) {
  await login(page);
  await page.goto(SETTINGS_PATH);
}

test.describe('BlueWorx Client Roles', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the Client Roles toggle renders and persists across save', async ({ page }) => {
    await gotoSettings(page);

    const toggle = page.locator('input.blueworx-feature-toggle[data-blueworx-feature="client_roles"]');
    await expect(toggle).toBeVisible();

    const wasChecked = await toggle.isChecked();

    await toggle.setChecked(!wasChecked);
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
    await expect(
      page.locator('input.blueworx-feature-toggle[data-blueworx-feature="client_roles"]')
    ).toBeChecked({ checked: !wasChecked });

    // Restore original state so the test is idempotent across runs.
    await page
      .locator('input.blueworx-feature-toggle[data-blueworx-feature="client_roles"]')
      .setChecked(wasChecked);
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
  });

  test('all three client roles are offered in the Site Protection role lists', async ({ page }) => {
    await gotoSettings(page);

    // Two selects (frontend + backend), so each registered role slug appears
    // twice. Site Protection reads wp_roles() live, so the roles show up here as
    // soon as they are registered.
    for (const slug of ['blueworx_client_owner', 'blueworx_client_dev', 'blueworx_client_editor']) {
      const count = await page.locator(`option[value="${slug}"]`).count();
      expect(count).toBeGreaterThan(0);
    }
  });
});
