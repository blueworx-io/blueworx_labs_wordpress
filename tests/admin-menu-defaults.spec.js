import { test, expect } from '@playwright/test';

const baseURL =
  process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || 'https://staging.placeholder.blueworx.io';
const isPlaceholder = /placeholder/i.test(baseURL);
const ADMIN_USER = process.env.WP_ADMIN_USER;
const ADMIN_PASS = process.env.WP_ADMIN_PASS;

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
});
