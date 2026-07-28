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

  test('the support account cannot write', async ({ page, context }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    try {
      const fresh = await context.browser().newContext();
      const anon = await fresh.newPage();
      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
      await expect(anon.locator('body.wp-admin')).toHaveCount(1);

      // Reading is fine.
      const read = await anon.goto(`${baseURL}/wp-admin/options-general.php`);
      expect(read.status()).toBe(200);

      // Every write path is refused.
      for (const path of ['/wp-admin/options.php', '/wp-admin/admin-ajax.php', '/wp-admin/admin-post.php']) {
        const posted = await anon.request.post(`${baseURL}${path}`, { data: { probe: '1' } });
        expect(posted.status(), `POST ${path}`).toBe(403);
      }

      const rest = await anon.request.post(`${baseURL}/wp-json/wp/v2/posts`, { data: { title: 'nope' } });
      expect(rest.status()).toBe(403);

      await fresh.close();
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
            await page.getByRole('button', { name: 'Revoke key' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('an administrator can still write while the support feature is active', async ({ page }) => {
    // Proves the write block is scoped to the support account only — an
    // administrator's own POSTs must keep working exactly as before.
    await login(page);
    await page.goto('/wp-admin/options-general.php');
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.getByText(/Settings saved/i)).toBeVisible();
  });

  test('repeated bad keys are locked out', async ({ page, request }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    try {
      const bad = 'f'.repeat(64);

      for (let i = 0; i < 5; i += 1) {
        await request.get(`${baseURL}/?blueworx_support_login=${bad}`);
      }

      // The real key is now refused too: the lockout is on the caller, not the key.
      const locked = await request.get(`${baseURL}/?blueworx_support_login=${key}`);
      expect(locked.status()).toBe(429);

      // The console still shows the lockout is in effect.
      await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
      await expect(page.getByText(/temporarily blocked/i)).toBeVisible();
    } finally {
      // Revoking clears the throttle for this address (see
      // blueworx_support_handle_actions()) — without this, the 900-second
      // transient set above survives past the end of the test and locks out
      // every later run's login attempts from 127.0.0.1.
      await restoreAll([
        [
          'revoke support key and clear throttle',
          async () => {
            await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
            await page.getByRole('button', { name: 'Revoke key' }).click();
          },
        ],
      ]);

      await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
      await expect(page.getByText(/temporarily blocked/i)).toHaveCount(0);
    }
  });

  test('REST key header reads while open, is ignored while shut', async ({ page, request }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();

    const headers = { 'X-BlueWorx-Support-Key': key };

    try {
      // Shut: settings route is unauthorised.
      const shut = await request.get(`${baseURL}/wp-json/wp/v2/settings`, { headers });
      expect(shut.status()).toBe(401);

      await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

      // Open: the key authenticates and the route returns real data, not just a 200.
      const open = await request.get(`${baseURL}/wp-json/wp/v2/settings`, { headers });
      expect(open.status()).toBe(200);
      const body = await open.json();
      expect(body).toHaveProperty('title');
      expect(typeof body.title).toBe('string');
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
            await page.getByRole('button', { name: 'Revoke key' }).click();
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('REST key auth does not bypass the write block', async ({ page, request }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    const headers = { 'X-BlueWorx-Support-Key': key };

    try {
      const write = await request.post(`${baseURL}/wp-json/wp/v2/settings`, {
        headers,
        data: { title: 'hijacked' },
      });
      expect(write.status()).toBe(403);
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
            await page.getByRole('button', { name: 'Revoke key' }).click();
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('anonymous REST requests without a key never count toward the lockout', async ({ page, request }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    try {
      // No X-BlueWorx-Support-Key header at all — determine_current_user still
      // runs (possibly more than once) for each of these, but none of them may
      // count as a failed attempt.
      for (let i = 0; i < 10; i += 1) {
        await request.get(`${baseURL}/wp-json/wp/v2/settings`);
      }

      const stillOpen = await request.get(`${baseURL}/wp-json/wp/v2/settings`, {
        headers: { 'X-BlueWorx-Support-Key': key },
      });
      expect(stillOpen.status()).toBe(200);
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
            await page.getByRole('button', { name: 'Revoke key' }).click();
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('personal-data screens are denied unless data access is opened', async ({ page, context }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    let fresh;

    try {
      fresh = await context.browser().newContext();
      const anon = await fresh.newPage();
      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);

      // Denied without the data opt-in.
      expect((await anon.goto(`${baseURL}/wp-admin/users.php`)).status()).toBe(403);
      expect((await anon.goto(`${baseURL}/wp-admin/edit-comments.php`)).status()).toBe(403);
      // A non-data screen still reads fine.
      expect((await anon.goto(`${baseURL}/wp-admin/options-general.php`)).status()).toBe(200);

      // An administrator is unaffected by the gate — same screen, real session.
      expect((await page.goto('/wp-admin/users.php')).status()).toBe(200);
      await expect(page.locator('body.wp-admin')).toHaveCount(1);

      // Re-open with data access ticked.
      await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
      await page.getByRole('button', { name: 'Close support access' }).click();
      await page.getByLabel('Also allow access to personal data for this session').check();
      await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
      expect((await anon.goto(`${baseURL}/wp-admin/users.php`)).status()).toBe(200);
      expect((await anon.goto(`${baseURL}/wp-admin/edit-comments.php`)).status()).toBe(200);

      await fresh.close();
      fresh = undefined;
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            if (fresh) {
              await fresh.close();
            }
            await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
            await page.getByRole('button', { name: 'Revoke key' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test("the support account's own profile stays reachable with data access shut", async ({ page, context }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    // The support account's own ID, read from the users list row (id="user-<ID>")
    // while still logged in as the administrator.
    await page.goto('/wp-admin/users.php');
    const row = page.locator('#the-list tr', { hasText: 'blueworx_support' });
    const rowId = await row.getAttribute('id');
    const supportId = Number((rowId || '').replace('user-', ''));
    expect(supportId).toBeGreaterThan(0);

    let fresh;

    try {
      fresh = await context.browser().newContext();
      const anon = await fresh.newPage();
      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);

      // user-edit.php is a denied screen, but the support account's OWN
      // profile (its own user_id) must still be reachable even with data
      // access shut.
      expect((await anon.goto(`${baseURL}/wp-admin/user-edit.php?user_id=${supportId}`)).status()).toBe(200);

      // Someone ELSE's profile, addressed explicitly by user_id, is still denied.
      const otherId = supportId === 1 ? supportId + 1 : 1;
      expect((await anon.goto(`${baseURL}/wp-admin/user-edit.php?user_id=${otherId}`)).status()).toBe(403);

      await fresh.close();
      fresh = undefined;
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            if (fresh) {
              await fresh.close();
            }
            await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
            await page.getByRole('button', { name: 'Revoke key' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('repeated bad REST key headers are locked out too', async ({ page, request }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    try {
      const bad = 'f'.repeat(64);

      for (let i = 0; i < 5; i += 1) {
        await request.get(`${baseURL}/wp-json/wp/v2/settings`, {
          headers: { 'X-BlueWorx-Support-Key': bad },
        });
      }

      // The lockout is on the caller: the real key is refused too. The
      // resolver never errors, so a throttled caller simply stays anonymous —
      // the settings route then answers 401 rather than 429.
      const locked = await request.get(`${baseURL}/wp-json/wp/v2/settings`, {
        headers: { 'X-BlueWorx-Support-Key': key },
      });
      expect(locked.status()).toBe(401);

      await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
      await expect(page.getByText(/temporarily blocked/i)).toBeVisible();
    } finally {
      await restoreAll([
        [
          'revoke support key and clear throttle',
          async () => {
            await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
            await page.getByRole('button', { name: 'Revoke key' }).click();
          },
        ],
      ]);

      await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
      await expect(page.getByText(/temporarily blocked/i)).toHaveCount(0);
    }
  });

  test('the audit log records opening, login and a blocked write', async ({ page, context }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    try {
      const fresh = await context.browser().newContext();
      const anon = await fresh.newPage();
      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
      await anon.request.post(`${baseURL}/wp-admin/admin-post.php`, { data: { probe: '1' } });
      await fresh.close();

      // The log persists across earlier tests, so this only asserts these
      // events are present, not that the log started empty.
      await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
      const log = page.locator('[data-testid="bw-support-log"]');
      await expect(log).toContainText('access_opened');
      await expect(log).toContainText('login');
      await expect(log).toContainText('blocked_write');
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
            await page.getByRole('button', { name: 'Revoke key' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('closing the window logs out a live support session', async ({ page, context }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    let fresh;

    try {
      fresh = await context.browser().newContext();
      const anon = await fresh.newPage();
      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
      await expect(anon.locator('body.wp-admin')).toHaveCount(1);

      await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
      await page.getByRole('button', { name: 'Close support access' }).click();

      // The live session is over, not merely barred from new logins.
      const after = await anon.goto(`${baseURL}/wp-admin/options-general.php`);
      expect(after.status()).toBe(403);

      await fresh.close();
      fresh = undefined;
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            if (fresh) {
              await fresh.close();
            }
            await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
            await page.getByRole('button', { name: 'Revoke key' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('closing the window does not touch an administrator session', async ({ page, context }) => {
    // The enforcement must be scoped to the support account only — an
    // administrator's own session must survive a window close untouched.
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    let fresh;

    try {
      fresh = await context.browser().newContext();
      const anon = await fresh.newPage();
      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
      await expect(anon.locator('body.wp-admin')).toHaveCount(1);

      await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
      await page.getByRole('button', { name: 'Close support access' }).click();

      // The window closing must not log the operator's own admin session out.
      const still = await page.goto('/wp-admin/options-general.php');
      expect(still.status()).toBe(200);
      await expect(page.locator('body.wp-admin')).toHaveCount(1);

      await fresh.close();
      fresh = undefined;
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            if (fresh) {
              await fresh.close();
            }
            await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
            await page.getByRole('button', { name: 'Revoke key' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });
});
