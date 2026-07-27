import { test, expect, baseURL, isPlaceholder, login, restoreAll } from './helpers.js';

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

  test('browser key login still works when Site Protection is on', async ({ page, context }) => {
    const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

    await login(page);
    await page.goto(SETTINGS_PATH);

    const frontendToggle = page.locator('input[name="blueworx_frontend_protection_enabled"]');
    const backendToggle = page.locator('input[name="blueworx_backend_protection_enabled"]');
    const frontendSelect = page.locator('select[name="blueworx_frontend_protection_roles[]"]');
    const backendSelect = page.locator('select[name="blueworx_backend_protection_roles[]"]');

    // Capture the operator's original Site Protection configuration so it can
    // be restored exactly, whatever it was, even if this test fails partway
    // through — leaving the harness protected would break every later test.
    const original = {
      frontendEnabled: await frontendToggle.isChecked(),
      backendEnabled: await backendToggle.isChecked(),
      frontendRoles: await frontendSelect.evaluate((el) => Array.from(el.selectedOptions).map((o) => o.value)),
      backendRoles: await backendSelect.evaluate((el) => Array.from(el.selectedOptions).map((o) => o.value)),
    };

    let key = '';

    try {
      // Turn both areas on, allow-listing only "administrator" — deliberately
      // NOT the support role — so the exemption under test is the support
      // account's identity, not an accidental role-list match.
      await frontendToggle.setChecked(true);
      await backendToggle.setChecked(true);
      await frontendSelect.selectOption(['administrator']);
      await backendSelect.selectOption(['administrator']);
      await page.getByRole('button', { name: 'Save Changes' }).click();
      await expect(page.locator('.notice-success').first()).toContainText('Settings saved');

      await page.goto(SETTINGS_PATH);
      await page.getByRole('button', { name: 'Generate key' }).click();
      key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();

      // Re-navigate before the next click: a second click on the same DOM
      // without an intervening goto is the headless-Chromium view-transition
      // freeze documented in helpers.js (login()) — the click "succeeds" from
      // Playwright's view but the server-side action never lands.
      await page.goto(SETTINGS_PATH);
      await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

      // A fresh, logged-out context: Site Protection must still refuse an
      // anonymous visitor everywhere else, but the key exchange must get
      // through on the front end, and the resulting session must not be
      // thrown back out of wp-admin by backend protection.
      const fresh = await context.browser().newContext();
      const anon = await fresh.newPage();
      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
      await expect(anon.locator('body.wp-admin')).toHaveCount(1);
      await fresh.close();
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            await page.goto(SETTINGS_PATH);
            if (key) {
              await page.getByRole('button', { name: 'Revoke key' }).click({ noWaitAfter: true });
              await page.waitForTimeout(1000);
            }
          },
        ],
        [
          'restore frontend protection',
          async () => {
            await page.goto(SETTINGS_PATH);
            await page
              .locator('input[name="blueworx_frontend_protection_enabled"]')
              .setChecked(original.frontendEnabled);
            await page
              .locator('select[name="blueworx_frontend_protection_roles[]"]')
              .selectOption(original.frontendRoles);
            await page.getByRole('button', { name: 'Save Changes' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);

            await page.goto(SETTINGS_PATH);
            await expect(
              page.locator('input[name="blueworx_frontend_protection_enabled"]')
            ).toBeChecked({ checked: original.frontendEnabled });
          },
        ],
        [
          'restore backend protection',
          async () => {
            await page.goto(SETTINGS_PATH);
            await page
              .locator('input[name="blueworx_backend_protection_enabled"]')
              .setChecked(original.backendEnabled);
            await page
              .locator('select[name="blueworx_backend_protection_roles[]"]')
              .selectOption(original.backendRoles);
            await page.getByRole('button', { name: 'Save Changes' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);

            await page.goto(SETTINGS_PATH);
            await expect(
              page.locator('input[name="blueworx_backend_protection_enabled"]')
            ).toBeChecked({ checked: original.backendEnabled });
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('repeated bad keys are locked out', async ({ page, request }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    const bad = 'f'.repeat(64);

    for (let i = 0; i < 5; i += 1) {
      await request.get(`${baseURL}/?blueworx_support_login=${bad}`);
    }

    // The real key is now refused too: the lockout is on the caller, not the key.
    const locked = await request.get(`${baseURL}/?blueworx_support_login=${key}`);
    expect(locked.status()).toBe(429);

    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Revoke key' }).click();
  });
});
