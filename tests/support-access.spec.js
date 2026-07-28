import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath, URL } from 'node:url';
import {
  test,
  expect,
  baseURL,
  isPlaceholder,
  login,
  restoreAll,
  cacheBust,
  LOGIN_PATH,
} from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Forces the live BlueWorx support access window into the past, simulating
 * natural 24-hour lapse rather than an operator clicking "Close support
 * access". Playwright has no way to reach the database directly, so this
 * shells out to a small PHP fixture (tests/fixtures/force-support-access-expiry.php)
 * run against the local .wp-test harness — the only environment these specs
 * ever execute against for real (see isPlaceholder above).
 */
function forceSupportAccessExpiry() {
  const fixture = path.join(__dirname, 'fixtures', 'force-support-access-expiry.php');
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  execFileSync('php', [fixture, wpLoad], { encoding: 'utf8' });
}

/**
 * Creates or deletes an IMPOSTOR account named "blueworx_support" that does not
 * hold the managed support role. See tests/fixtures/impostor-support-user.php —
 * there is no UI path that produces this account reliably, so it is made
 * directly against the local .wp-test harness.
 *
 * @param {'create'|'delete'} command Fixture command.
 * @return {string} Fixture stdout — the generated password for "create".
 */
function impostorSupportUser(command) {
  const fixture = path.join(__dirname, 'fixtures', 'impostor-support-user.php');
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  return execFileSync('php', [fixture, wpLoad, command], { encoding: 'utf8' }).trim();
}

const CONSOLE_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

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

  test('a natural window lapse refuses a previously-working session with 403', async ({ page, context }) => {
    // Task 9 only tested the operator explicitly clicking "Close support
    // access". blueworx_support_access_open() re-reads the option on every
    // call, so lapse and explicit-close are believed to be the same code
    // path — but that belief was never exercised by a natural expiry. This
    // forces blueworx_support_access_until into the past directly in the
    // database, which is the only way to simulate the window lapsing on its
    // own without waiting 24 real hours.
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

      // Confirm the live session genuinely works before forcing expiry.
      const before = await anon.goto(`${baseURL}/wp-admin/options-general.php`);
      expect(before.status()).toBe(200);

      // Simulate the window lapsing naturally — no click, no admin_init
      // action, just the clock passing the stored expiry.
      forceSupportAccessExpiry();

      // The same session, previously working, must now be refused.
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

  test('the support account can read plugins.php but cannot activate over GET', async ({
    page,
    context,
  }) => {
    // The support role keeps activate_plugins deliberately — it is what gates
    // VIEWING plugins.php, and the plugin list is a primary diagnostic. But
    // WordPress deactivates a single plugin through a nonce'd GET link, which
    // the read-only write block (non-GET methods only) never saw. The account
    // can scrape its own valid nonce off the page it is allowed to read.
    await login(page);
    await page.goto(CONSOLE_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    // How many plugins are active right now, seen as the administrator.
    await page.goto('/wp-admin/plugins.php');
    const activeBefore = await page.locator('#the-list tr.active').count();
    expect(activeBefore).toBeGreaterThan(0);

    let fresh;

    try {
      fresh = await context.browser().newContext();
      const anon = await fresh.newPage();
      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
      await expect(anon.locator('body.wp-admin')).toHaveCount(1);

      // Reading the plugin list still works — that is the whole point.
      const list = await anon.goto(`${baseURL}/wp-admin/plugins.php`);
      expect(list.status()).toBe(200);
      await expect(anon.locator('#the-list')).toBeVisible();

      // A real deactivate link, with a real nonce, harvested from that page.
      const href = await anon
        .locator('#the-list a[href*="action=deactivate"]')
        .first()
        .getAttribute('href');
      expect(href, 'a deactivate link with a nonce must be present').toBeTruthy();
      expect(href).toContain('_wpnonce=');

      const attack = new URL(href, `${baseURL}/wp-admin/plugins.php`).toString();
      const refused = await anon.goto(attack);
      expect(refused.status(), 'GET deactivate must be refused').toBe(403);

      // action2 — WordPress's bottom bulk selector — is refused as well.
      const refusedAction2 = await anon.goto(
        `${baseURL}/wp-admin/plugins.php?action2=deactivate-selected`
      );
      expect(refusedAction2.status()).toBe(403);

      // "-1" is WordPress's "no action selected" value and must not be treated
      // as an action, or the plain list screen would break.
      const noAction = await anon.goto(`${baseURL}/wp-admin/plugins.php?action=-1&action2=-1`);
      expect(noAction.status()).toBe(200);

      // themes.php is gated on exactly the same footing.
      const themeAttack = await anon.goto(
        `${baseURL}/wp-admin/themes.php?action=activate&stylesheet=twentytwentyfour`
      );
      expect(themeAttack.status()).toBe(403);

      await fresh.close();
      fresh = undefined;

      // Nothing was actually deactivated.
      await page.goto('/wp-admin/plugins.php');
      expect(await page.locator('#the-list tr.active').count()).toBe(activeBefore);
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            if (fresh) {
              await fresh.close();
            }
            await page.goto(CONSOLE_PATH);
            await page.getByRole('button', { name: 'Revoke key' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('the support account cannot fire an admin_post handler over GET', async ({
    page,
    context,
  }) => {
    // check_admin_referer() reads $_REQUEST, so a nonce in the query string
    // satisfied it and blueworx_save_feature_settings() ran on a GET — every
    // $_POST read empty, writing '0' over every feature option and both
    // site-protection options.
    await login(page);
    await page.goto(CONSOLE_PATH);

    const featureBoxes = page.locator('input.blueworx-feature-toggle');
    const featureCount = await featureBoxes.count();
    expect(featureCount).toBeGreaterThan(0);

    const before = {};
    for (let i = 0; i < featureCount; i += 1) {
      const box = featureBoxes.nth(i);
      before[await box.getAttribute('data-blueworx-feature')] = await box.isChecked();
    }
    const slugBefore = await page.locator('#blueworx_login_slug').inputValue();
    expect(slugBefore.length).toBeGreaterThan(0);

    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    let fresh;

    try {
      fresh = await context.browser().newContext();
      const anon = await fresh.newPage();
      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
      await expect(anon.locator('body.wp-admin')).toHaveCount(1);

      // The support account holds manage_options and is not blocked from the
      // console, so it can read the nonce straight off the page.
      const consoleRead = await anon.goto(`${baseURL}${CONSOLE_PATH}`);
      expect(consoleRead.status()).toBe(200);

      const nonce = await anon
        .locator('form[action*="admin-post.php"] input[name="_wpnonce"]')
        .first()
        .inputValue();
      expect(nonce, 'the feature-settings nonce must be scrapeable').toBeTruthy();

      const refused = await anon.goto(
        `${baseURL}/wp-admin/admin-post.php?action=blueworx_save_feature_settings&_wpnonce=${nonce}`
      );
      expect(refused.status(), 'GET to admin-post.php must be refused').toBe(403);

      await fresh.close();
      fresh = undefined;

      // And nothing was written: every feature toggle and the login slug are
      // exactly as they were.
      await page.goto(CONSOLE_PATH);
      for (const [feature, wasChecked] of Object.entries(before)) {
        await expect(
          page.locator(`input.blueworx-feature-toggle[data-blueworx-feature="${feature}"]`),
          `feature ${feature}`
        ).toBeChecked({ checked: wasChecked });
      }
      expect(await page.locator('#blueworx_login_slug').inputValue()).toBe(slugBefore);
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            if (fresh) {
              await fresh.close();
            }
            await page.goto(CONSOLE_PATH);
            await page.getByRole('button', { name: 'Revoke key' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('a user merely named blueworx_support gains no Site Protection exemption', async ({
    page,
    context,
  }) => {
    // blueworx_support_is_support_user() keyed on user_login alone, so any
    // account with that login — obtainable on a site with open registration —
    // bypassed Site Protection entirely, with no key and no window.
    await login(page);
    await page.goto('/wp-admin/users.php');
    await expect(page.locator('#the-list')).not.toContainText('blueworx_support');

    await page.goto(CONSOLE_PATH);
    const frontendToggle = page.locator('input[name="blueworx_frontend_protection_enabled"]');
    const backendToggle = page.locator('input[name="blueworx_backend_protection_enabled"]');
    const frontendSelect = page.locator('select[name="blueworx_frontend_protection_roles[]"]');
    const backendSelect = page.locator('select[name="blueworx_backend_protection_roles[]"]');

    const original = {
      frontendEnabled: await frontendToggle.isChecked(),
      backendEnabled: await backendToggle.isChecked(),
      frontendRoles: await frontendSelect.evaluate((el) =>
        Array.from(el.selectedOptions).map((o) => o.value)
      ),
      backendRoles: await backendSelect.evaluate((el) =>
        Array.from(el.selectedOptions).map((o) => o.value)
      ),
    };

    const password = impostorSupportUser('create');
    expect(password.length).toBeGreaterThan(0);

    let fresh;

    try {
      await frontendToggle.setChecked(true);
      await backendToggle.setChecked(true);
      await frontendSelect.selectOption(['administrator']);
      await backendSelect.selectOption(['administrator']);
      await page.getByRole('button', { name: 'Save Changes' }).click();
      await expect(page.locator('.notice-success').first()).toContainText('Settings saved');

      fresh = await context.browser().newContext();
      const impostor = await fresh.newPage();
      await impostor.emulateMedia({ reducedMotion: 'reduce' });

      await impostor.goto(cacheBust(`${baseURL}${LOGIN_PATH}`));
      await impostor.fill('#user_login', 'blueworx_support');
      await impostor.fill('#user_pass', password);
      await impostor.click('#wp-submit');
      await impostor.waitForLoadState('domcontentloaded');

      // Signed in as a subscriber — and Site Protection must treat them as one.
      //
      // The status alone is NOT enough to prove the fix, and this was verified
      // by reverting the role check and re-running: with the old login-only
      // check, blueworx_support_enforce_window() treated this account as the
      // support account, found the window shut and destroyed its session
      // during the login request itself — so the response was still 403, just
      // "Please log in to view this site." from the logged-out branch. Only the
      // message proves the refusal came from Site Protection applying this
      // account's OWN subscriber role.
      const backend = await impostor.goto(`${baseURL}/wp-admin/`);
      expect(backend.status(), 'wp-admin must be refused').toBe(403);
      await expect(impostor.locator('.wp-die-message')).toContainText(
        'You do not have access to view this area.'
      );

      const frontend = await impostor.goto(cacheBust(`${baseURL}/`));
      expect(frontend.status(), 'the frontend must be refused').toBe(403);
      await expect(impostor.locator('.wp-die-message')).toContainText(
        'You do not have access to view this area.'
      );

      await fresh.close();
      fresh = undefined;
    } finally {
      await restoreAll([
        [
          'close impostor context',
          async () => {
            if (fresh) {
              await fresh.close();
            }
          },
        ],
        [
          'delete the impostor account',
          async () => {
            impostorSupportUser('delete');
          },
        ],
        [
          'restore site protection',
          async () => {
            await page.goto(CONSOLE_PATH);
            await page
              .locator('input[name="blueworx_frontend_protection_enabled"]')
              .setChecked(original.frontendEnabled);
            await page
              .locator('select[name="blueworx_frontend_protection_roles[]"]')
              .selectOption(original.frontendRoles);
            await page
              .locator('input[name="blueworx_backend_protection_enabled"]')
              .setChecked(original.backendEnabled);
            await page
              .locator('select[name="blueworx_backend_protection_roles[]"]')
              .selectOption(original.backendRoles);
            await page.getByRole('button', { name: 'Save Changes' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);

            await page.goto(CONSOLE_PATH);
            await expect(
              page.locator('input[name="blueworx_frontend_protection_enabled"]')
            ).toBeChecked({ checked: original.frontendEnabled });
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

  test('REST personal-data routes are denied unless data access is opened', async ({
    page,
    request,
  }) => {
    // The screen-level gate has had a test since task 7; the REST route gate
    // (blueworx_support_gate_data_routes) never did.
    await login(page);
    await page.goto(CONSOLE_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
    await page.goto(CONSOLE_PATH);
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    const headers = { 'X-BlueWorx-Support-Key': key };

    try {
      const denied = await request.get(`${baseURL}/wp-json/wp/v2/users`, { headers });
      expect(denied.status()).toBe(403);
      expect((await denied.json()).code).toBe('blueworx_support_no_data');

      const deniedComments = await request.get(`${baseURL}/wp-json/wp/v2/comments`, { headers });
      expect(deniedComments.status()).toBe(403);

      // A non-data route is unaffected: the key still authenticates.
      const allowed = await request.get(`${baseURL}/wp-json/wp/v2/settings`, { headers });
      expect(allowed.status()).toBe(200);

      // Re-open with the personal-data opt-in ticked.
      await page.goto(CONSOLE_PATH);
      await page.getByRole('button', { name: 'Close support access' }).click();
      await page.getByLabel('Also allow access to personal data for this session').check();
      await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

      const opened = await request.get(`${baseURL}/wp-json/wp/v2/users`, { headers });
      expect(opened.status()).toBe(200);
    } finally {
      await restoreAll([
        [
          'revoke support key and close data access',
          async () => {
            await page.goto(CONSOLE_PATH);
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
