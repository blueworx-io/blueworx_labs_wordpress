import { test, expect, baseURL, isPlaceholder, login } from './helpers.js';

test.describe('Support access — key lifecycle', () => {
  test.skip(isPlaceholder, 'No real site configured');

  test('feature is registered on the console', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await expect(page.getByText('BlueWorx support access')).toBeVisible();
  });

  test('no support account exists before a key is generated', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/users.php');
    await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
  });

  test('generating a key shows it once and creates the account', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');

    await page.getByRole('button', { name: 'Generate key' }).click();

    const key = await page.locator('[data-testid="bw-support-key"]').innerText();
    expect(key.trim()).toMatch(/^[0-9a-f]{64}$/);

    // Shown once: a reload must not render it again.
    await page.reload();
    await expect(page.locator('[data-testid="bw-support-key"]')).toHaveCount(0);

    await page.goto('/wp-admin/users.php');
    await expect(page.locator('#the-list')).toContainText('blueworx_support');

    // Restore: revoking must remove the account again.
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Revoke key' }).click();
    await page.goto('/wp-admin/users.php');
    await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
  });

  test('browser key login is refused while the window is shut', async ({ page, context }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();

    // A separate, logged-out context: the admin cookie must not mask the result.
    const fresh = await context.browser().newContext();
    const anon = await fresh.newPage();

    const closed = await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
    expect(closed.status()).toBe(403);

    // Open the window, then the same key works.
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
    await expect(anon.locator('body.wp-admin')).toHaveCount(1);

    await fresh.close();

    // Restore. noWaitAfter: the extra cross-context navigations above
    // (fresh.close(), the anon logins) leave headless Chromium's renderer in
    // the frozen state documented in helpers.js — rAF stops, so Playwright's
    // post-click "wait for navigation" never resolves even though the server
    // processes the request. This is cleanup only, not an assertion, so skip
    // that wait and confirm the restored state on a fresh navigation instead.
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Revoke key' }).click({ noWaitAfter: true });
    await page.waitForTimeout(1000);
    await page.goto('/wp-admin/users.php');
    await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
  });
});
