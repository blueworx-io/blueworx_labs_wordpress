// `test` comes from helpers.js, not '@playwright/test': it carries the fixture
// that opts out of core's wp-admin view transitions, which otherwise freeze
// rendering in headless Chromium and hang every actionability check.
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

/**
 * Navigate to the settings page, logging in first (a fresh context has no
 * session).
 */
async function gotoSettings(page) {
  await login(page);
  await page.goto(SETTINGS_PATH);
}

test.describe('BlueWorx feature toggles', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('settings page shows grouped sections and a Comments toggle', async ({ page }) => {
    await gotoSettings(page);
    await expect(page.getByRole('heading', { name: 'Security & Access' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Content' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Notifications & Cleanup' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Performance' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Admin Menu' })).toBeVisible();
    await expect(
      page.locator('input.blueworx-feature-toggle[data-blueworx-feature="comments"]')
    ).toBeVisible();
  });

  test('toggling a feature persists after save', async ({ page }) => {
    await gotoSettings(page);
    const toggle = page.locator('input.blueworx-feature-toggle[data-blueworx-feature="page_excerpts"]');
    const wasChecked = await toggle.isChecked();

    await toggle.setChecked(!wasChecked);
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
    await expect(
      page.locator('input.blueworx-feature-toggle[data-blueworx-feature="page_excerpts"]')
    ).toBeChecked({ checked: !wasChecked });

    // Restore original state so the test is idempotent across runs.
    await page
      .locator('input.blueworx-feature-toggle[data-blueworx-feature="page_excerpts"]')
      .setChecked(wasChecked);
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
  });
});
