import { test, expect } from '@playwright/test';

const baseURL =
  process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || 'https://staging.placeholder.blueworx.io';
const isPlaceholder = /placeholder/i.test(baseURL);
const ADMIN_USER = process.env.WP_ADMIN_USER;
const ADMIN_PASS = process.env.WP_ADMIN_PASS;

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

/**
 * Navigate to the settings page, logging in first if WordPress redirects
 * to the login screen (fresh browser context has no session).
 */
async function gotoSettings(page) {
  await page.goto(SETTINGS_PATH);
  if (await page.locator('#user_login').count()) {
    await page.fill('#user_login', ADMIN_USER);
    await page.fill('#user_pass', ADMIN_PASS);
    await page.click('#wp-submit');
    await page.goto(SETTINGS_PATH);
  }
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
    await expect(page.locator('.notice-success')).toContainText('Settings saved');
    await expect(
      page.locator('input.blueworx-feature-toggle[data-blueworx-feature="page_excerpts"]')
    ).toBeChecked({ checked: !wasChecked });

    // Restore original state so the test is idempotent across runs.
    await page
      .locator('input.blueworx-feature-toggle[data-blueworx-feature="page_excerpts"]')
      .setChecked(wasChecked);
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('.notice-success')).toContainText('Settings saved');
  });
});
