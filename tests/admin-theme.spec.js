import { test, expect } from '@playwright/test';

const baseURL =
  process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || 'https://staging.placeholder.blueworx.io';
const isPlaceholder = /placeholder/i.test(baseURL);
const ADMIN_USER = process.env.WP_ADMIN_USER;
const ADMIN_PASS = process.env.WP_ADMIN_PASS;

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const DASH_PATH = '/wp-admin/index.php';

/**
 * Log in if a fresh context lands on the login screen.
 */
async function login(page) {
  await page.goto(DASH_PATH);
  if (await page.locator('#user_login').count()) {
    await page.fill('#user_login', ADMIN_USER);
    await page.fill('#user_pass', ADMIN_PASS);
    await page.click('#wp-submit');
  }
}

/**
 * Go to the settings page and return the admin_theme toggle locator.
 */
async function themeToggle(page) {
  await page.goto(SETTINGS_PATH);
  return page.locator('input.blueworx-feature-toggle[data-blueworx-feature="admin_theme"]');
}

async function saveSettings(page) {
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
}

test.describe('BlueWorx admin theme', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('Appearance section and admin_theme toggle render', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await expect(page.getByRole('heading', { name: 'Appearance' })).toBeVisible();
    await expect(
      page.locator('input.blueworx-feature-toggle[data-blueworx-feature="admin_theme"]')
    ).toBeVisible();
  });

  test('theme stylesheet + hero tiles load when on, absent when off', async ({ page }) => {
    await login(page);

    // Ensure the theme is ON.
    let toggle = await themeToggle(page);
    if (!(await toggle.isChecked())) {
      await toggle.setChecked(true);
      await saveSettings(page);
    }

    await page.goto(DASH_PATH);
    await expect(page.locator('link#blueworx-admin-theme-css')).toHaveCount(1);
    await expect(page.locator('.bw-stat-grid')).toBeVisible();

    // Turn it OFF — stylesheet, hero tiles, and custom chrome disappear.
    toggle = await themeToggle(page);
    await toggle.setChecked(false);
    await saveSettings(page);

    await page.goto(DASH_PATH);
    await expect(page.locator('link#blueworx-admin-theme-css')).toHaveCount(0);
    await expect(page.locator('.bw-stat-grid')).toHaveCount(0);
    await expect(page.locator('.bw-topbar')).toHaveCount(0);

    // Restore ON so the test is idempotent across runs.
    toggle = await themeToggle(page);
    await toggle.setChecked(true);
    await saveSettings(page);
  });

  test('desktop chrome: BlueWorx top bar replaces the admin bar, footer hidden', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    // Custom top bar is present with the site link and user menu.
    await expect(page.locator('.bw-topbar')).toBeVisible();
    await expect(page.locator('.bw-topbar-site')).toBeVisible();
    await expect(page.locator('.bw-topbar-site')).toHaveAttribute('target', '_blank');
    await expect(page.locator('.bw-user-summary')).toBeVisible();
    await expect(page.locator('.bw-brand')).toBeVisible();

    // WordPress chrome we replaced is not visible on desktop.
    await expect(page.locator('#wpadminbar')).toBeHidden();
    await expect(page.locator('#wpfooter')).toBeHidden();

    // The user menu is a native <details> — opening it reveals profile + logout.
    await page.locator('.bw-user-summary').click();
    await expect(page.locator('.bw-user-menu a', { hasText: 'Log Out' })).toBeVisible();
  });

  test('mobile keeps the native admin bar so the menu toggle still works', async ({ page }) => {
    await login(page);
    await page.setViewportSize({ width: 480, height: 900 });
    await page.goto(DASH_PATH);

    await expect(page.locator('.bw-topbar')).toBeHidden();
    await expect(page.locator('#wpadminbar')).toBeVisible();
  });

  test('login screen is branded', async ({ page, context }) => {
    await context.clearCookies();
    await page.goto('/wp-login.php');

    // The WordPress logo is replaced by the site-name wordmark.
    const logo = page.locator('.login h1 a');
    await expect(logo).toBeVisible();
    await expect(logo).not.toHaveCSS('background-image', /wordpress-logo/);
    await expect(page.locator('link#blueworx-login-theme-css')).toHaveCount(1);
  });
});
