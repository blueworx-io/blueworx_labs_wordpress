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
});
