import { test, expect } from '@playwright/test';

const baseURL =
  process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || 'https://staging.placeholder.blueworx.io';
const isPlaceholder = /placeholder/i.test(baseURL);
const ADMIN_USER = process.env.WP_ADMIN_USER;
const ADMIN_PASS = process.env.WP_ADMIN_PASS;

const EDIT_MENU_PATH = '/wp-admin/admin.php?page=blueworx-edit-menu';

async function login(page) {
  await page.goto('/wp-admin/');
  if (await page.locator('#user_login').count()) {
    await page.fill('#user_login', ADMIN_USER);
    await page.fill('#user_pass', ADMIN_PASS);
    await page.click('#wp-submit');
  }
}

test.describe('BlueWorx default admin-menu arrangement', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('BlueWorx sits directly below Dashboard in the admin menu', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/');
    const topLevel = page.locator('#adminmenu > li.menu-top:visible');
    const first = await topLevel.nth(0).innerText();
    const second = await topLevel.nth(1).innerText();
    expect(first).toContain('Dashboard');
    expect(second).toContain('BlueWorx');
  });

  test('More is the last visible top-level item', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/');
    const visible = page.locator('#adminmenu > li.menu-top:visible');
    const last = await visible.last().innerText();
    expect(last).toContain('More');
  });

  test('Edit Menu page shows the default split with items in the More column', async ({ page }) => {
    await login(page);
    await page.goto(EDIT_MENU_PATH);
    const moreColumn = page.locator('.blueworx-menu-order-list[data-blueworx-menu-section="toggle"]');
    // At least one core item (e.g. Settings/Tools/Appearance) defaults into More.
    await expect(moreColumn.locator('.blueworx-menu-order-item')).not.toHaveCount(0);
    // Hidden column is empty by default.
    const hiddenColumn = page.locator('.blueworx-menu-order-list[data-blueworx-menu-section="hidden"]');
    await expect(hiddenColumn.locator('.blueworx-menu-order-item')).toHaveCount(0);
  });

  test('saving the Edit Menu page freezes the arrangement', async ({ page }) => {
    await login(page);
    await page.goto(EDIT_MENU_PATH);
    await page.getByRole('button', { name: 'Save Menu Settings' }).click();
    await expect(page.locator('.notice-success').first()).toContainText('Menu settings saved');
    // After a save the same split still renders (defaults were persisted, not reset).
    await page.goto(EDIT_MENU_PATH);
    const moreColumn = page.locator('.blueworx-menu-order-list[data-blueworx-menu-section="toggle"]');
    await expect(moreColumn.locator('.blueworx-menu-order-item')).not.toHaveCount(0);
  });
});
