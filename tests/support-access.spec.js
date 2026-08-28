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
  readSupportKey,
  readCheckedGroup,
  setCheckedGroup,
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

/**
 * Runs tests/fixtures/support-access-probe.php.
 *
 * "ids" returns the IDs of a published post, page and approved comment, so a
 * test can address a single-item screen directly. "deny-pages"/"allow-pages"
 * install and remove a must-use plugin that adds "page" to the denied
 * personal-data post types — the real denied types are WooCommerce's, and
 * WooCommerce is not on the harness, so this drives the same branch with a
 * post type that exists.
 *
 * @param {'ids'|'deny-pages'|'allow-pages'} command Fixture command.
 * @return {string} Fixture stdout.
 */
function supportAccessProbe(command) {
  const fixture = path.join(__dirname, 'fixtures', 'support-access-probe.php');
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  return execFileSync('php', [fixture, wpLoad, command], { encoding: 'utf8' }).trim();
}

const ENHANCEMENTS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const SUPPORT_PATH = '/wp-admin/admin.php?page=blueworx-support';

/**
 * The support access card, as opposed to anything else on the screen.
 *
 * Every card draws from the same design system, so a bare `.bw-notice--info`
 * matches whichever ones happen to be showing a note today — and the second one
 * to appear turns a passing assertion into a strict mode violation halfway
 * through this test, which leaves a live key behind and takes the rest of the
 * file down with it.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @return {import('@playwright/test').Locator} The panel.
 */
const supportPanel = (page) => page.locator('.bw-supportcard');

test.describe('Support access — key lifecycle', () => {
  test.skip(isPlaceholder, 'No real site configured');

  test('Enhancements offers the switch and sends you here for the rest', async ({ page }) => {
    await login(page);
    await page.goto(ENHANCEMENTS_PATH);

    const card = page.locator('.bw-fncard:has([data-blueworx-feature="support_access"])');
    await expect(card).toContainText('BlueWorx support access');

    // No key, no window, no buttons — this screen switches the function on and
    // off and nothing else. Counted as nodes rather than through the
    // accessibility tree, which skips the panel while the function is off.
    await expect(card.locator('button')).toHaveCount(0);
    await expect(card.locator('a[href*="page=blueworx-support"]')).toHaveCount(1);
  });

  test('no support account exists before a key is generated', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/users.php');
    await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
  });

  test('generating a key shows it once and creates the account', async ({ page }) => {
    await login(page);
    await page.goto(SUPPORT_PATH);

    await page.getByRole('button', { name: 'Generate key' }).click();

    const key = await readSupportKey(page);
    expect(key.trim()).toMatch(/^[0-9a-f]{64}$/);

    // Shown once: a reload must not render it again.
    await page.reload();
    await expect(page.locator('[data-testid="bw-support-key"]')).toHaveCount(0);

    await page.goto('/wp-admin/users.php');
    await expect(page.locator('#the-list')).toContainText('blueworx_support');

    // Restore: revoking must remove the account again.
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Revoke access' }).click();
    await page.goto('/wp-admin/users.php');
    await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
  });

  test('the panel says which state it is in, and posts to its own handler', async ({ page }) => {
    await login(page);
    await page.goto(SUPPORT_PATH);

    // Before a key: one action, and nothing claiming a state it is not in.
    await expect(page.getByRole('button', { name: 'Generate key' })).toBeVisible();
    await expect(page.locator('[data-testid="bw-support-key"]')).toHaveCount(0);

    await page.getByRole('button', { name: 'Generate key' }).click();

    // The key is a field with a copy button, not a bare block of text, so it
    // can be selected and copied on a site served over plain HTTP.
    const field = page.locator('[data-testid="bw-support-key"]');
    await expect(field).toHaveValue(/^[0-9a-f]{64}$/);
    await expect(page.locator('[data-blueworx-copy="bw-support-key"]')).toBeVisible();

    // A key with the window shut is a real state and has to read as one.
    await expect(supportPanel(page).locator('.bw-notice--info')).toContainText('shut');

    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();
    await expect(page.locator('[data-testid="bw-support-expiry"]')).toContainText('open until');
    await expect(supportPanel(page).locator('.bw-notice--success .bw-badge')).toContainText('Open');

    // The buttons carry their own formaction. Without it they post to whatever
    // form encloses them, which redirects — the page would still look fine and
    // the action would never run.
    await expect(page.getByRole('button', { name: 'Revoke access' })).toHaveAttribute(
      'formaction',
      /page=blueworx-support/
    );

    await page.getByRole('button', { name: 'Revoke access' }).click();
    await expect(page.getByRole('button', { name: 'Generate key' })).toBeVisible();
    await page.goto('/wp-admin/users.php');
    await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
  });

  test('browser key login is refused while the window is shut', async ({ page, context }) => {
    await login(page);
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);

    // A separate, logged-out context: the admin cookie must not mask the result.
    const fresh = await context.browser().newContext();
    const anon = await fresh.newPage();

    const closed = await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
    expect(closed.status()).toBe(403);

    // Open the window, then the same key works.
    await page.goto(SUPPORT_PATH);
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
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
    await page.waitForTimeout(1000);
    await page.goto('/wp-admin/users.php');
    await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
  });

  test('browser key login still works when Site Protection is on', async ({ page, context }) => {

    await login(page);
    await page.goto(ENHANCEMENTS_PATH);

    const frontendToggle = page.locator('input[name="blueworx_frontend_protection_enabled"]');
    const backendToggle = page.locator('input[name="blueworx_backend_protection_enabled"]');

    // Capture the operator's original Site Protection configuration so it can
    // be restored exactly, whatever it was, even if this test fails partway
    // through — leaving the harness protected would break every later test.
    const original = {
      frontendEnabled: await frontendToggle.isChecked(),
      backendEnabled: await backendToggle.isChecked(),
      frontendRoles: await readCheckedGroup(page, 'blueworx_frontend_protection_roles'),
      backendRoles: await readCheckedGroup(page, 'blueworx_backend_protection_roles'),
    };

    let key = '';

    try {
      // Turn both areas on, allow-listing only "administrator" — deliberately
      // NOT the support role — so the exemption under test is the support
      // account's identity, not an accidental role-list match.
      await frontendToggle.setChecked(true);
      await backendToggle.setChecked(true);
      await setCheckedGroup(page, 'blueworx_frontend_protection_roles', ['administrator']);
      await setCheckedGroup(page, 'blueworx_backend_protection_roles', ['administrator']);
      await page.getByRole('button', { name: 'Save Changes' }).click();
      await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');

      await page.goto(SUPPORT_PATH);
      await page.getByRole('button', { name: 'Generate key' }).click();
      key = await readSupportKey(page);

      // Re-navigate before the next click: a second click on the same DOM
      // without an intervening goto is the headless-Chromium view-transition
      // freeze documented in helpers.js (login()) — the click "succeeds" from
      // Playwright's view but the server-side action never lands.
      await page.goto(SUPPORT_PATH);
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
            await page.goto(SUPPORT_PATH);
            if (key) {
              await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
              await page.waitForTimeout(1000);
            }
          },
        ],
        [
          'restore frontend protection',
          async () => {
            await page.goto(ENHANCEMENTS_PATH);
            await page
              .locator('input[name="blueworx_frontend_protection_enabled"]')
              .setChecked(original.frontendEnabled);
            await setCheckedGroup(page, 'blueworx_frontend_protection_roles', original.frontendRoles);
            await page.getByRole('button', { name: 'Save Changes' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);

            await page.goto(ENHANCEMENTS_PATH);
            await expect(
              page.locator('input[name="blueworx_frontend_protection_enabled"]')
            ).toBeChecked({ checked: original.frontendEnabled });
          },
        ],
        [
          'restore backend protection',
          async () => {
            await page.goto(ENHANCEMENTS_PATH);
            await page
              .locator('input[name="blueworx_backend_protection_enabled"]')
              .setChecked(original.backendEnabled);
            await setCheckedGroup(page, 'blueworx_backend_protection_roles', original.backendRoles);
            await page.getByRole('button', { name: 'Save Changes' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);

            await page.goto(ENHANCEMENTS_PATH);
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
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
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
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
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
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
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
      await page.goto(SUPPORT_PATH);
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
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click();
          },
        ],
      ]);

      await page.goto(SUPPORT_PATH);
      await expect(page.getByText(/temporarily blocked/i)).toHaveCount(0);
    }
  });

  test('REST key header reads while open, is ignored while shut', async ({ page, request }) => {
    await login(page);
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);

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
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click();
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('REST key auth does not bypass the write block', async ({ page, request }) => {
    await login(page);
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
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
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click();
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('anonymous REST requests without a key never count toward the lockout', async ({ page, request }) => {
    await login(page);
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
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
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click();
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('personal-data screens are denied unless data access is opened', async ({ page, context }) => {
    await login(page);
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
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
      await page.goto(SUPPORT_PATH);
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
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
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
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
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
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
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
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
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

      await page.goto(SUPPORT_PATH);
      await expect(page.getByText(/temporarily blocked/i)).toBeVisible();
    } finally {
      await restoreAll([
        [
          'revoke support key and clear throttle',
          async () => {
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click();
          },
        ],
      ]);

      await page.goto(SUPPORT_PATH);
      await expect(page.getByText(/temporarily blocked/i)).toHaveCount(0);
    }
  });

  test('the audit log records opening, login and a blocked write', async ({ page, context }) => {
    await login(page);
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    try {
      const fresh = await context.browser().newContext();
      const anon = await fresh.newPage();
      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
      await anon.request.post(`${baseURL}/wp-admin/admin-post.php`, { data: { probe: '1' } });
      await fresh.close();

      // The log persists across earlier tests, so this only asserts these
      // events are present, not that the log started empty.
      await page.goto(SUPPORT_PATH);
      const log = page.locator('[data-testid="bw-support-log"]');
      await expect(log).toContainText('access_opened');
      await expect(log).toContainText('login');
      await expect(log).toContainText('blocked_write');
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
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
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    let fresh;

    try {
      fresh = await context.browser().newContext();
      const anon = await fresh.newPage();
      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
      await expect(anon.locator('body.wp-admin')).toHaveCount(1);

      await page.goto(SUPPORT_PATH);
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
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
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
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
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
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
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
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    let fresh;

    try {
      fresh = await context.browser().newContext();
      const anon = await fresh.newPage();
      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
      await expect(anon.locator('body.wp-admin')).toHaveCount(1);

      await page.goto(SUPPORT_PATH);
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
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
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
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
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
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('the support account cannot trash or delete content over core GET links', async ({
    page,
    context,
  }) => {
    // Security review finding. The read-only block only refuses non-GET methods,
    // and WordPress trashes and deletes over nonce'd GET: post.php?action=trash
    // is a row-action anchor core renders into a page support is allowed to
    // read, and the bulk form on edit.php is method="get". Neither was covered
    // by the plugins.php/themes.php gate, and the role kept every delete_*
    // capability, so the read-only guarantee did not hold against stock core.
    const ids = JSON.parse(supportAccessProbe('ids'));

    await login(page);
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    let fresh;

    try {
      fresh = await context.browser().newContext();
      const anon = await fresh.newPage();
      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
      await expect(anon.locator('body.wp-admin')).toHaveCount(1);

      // The post list still reads — that is the diagnostic the feature exists
      // for, and the fix must not cost it.
      const list = await anon.goto(`${baseURL}/wp-admin/edit.php`);
      expect(list.status()).toBe(200);
      await expect(anon.locator('#the-list')).toBeVisible();

      // Capability layer: with the delete_* capabilities stripped, core does not
      // even render a Trash row action for this account.
      await expect(anon.locator('#the-list a[href*="action=trash"]')).toHaveCount(0);

      // Request layer, independently. A hand-built trash link is refused even
      // though it is a GET, and even with a scraped nonce.
      const nonce = await anon.evaluate(() => {
        const el = document.querySelector('#_wpnonce, input[name="_wpnonce"]');
        return el ? el.value : '';
      });
      const trash = await anon.goto(
        `${baseURL}/wp-admin/post.php?action=trash&post=${ids.postId}&_wpnonce=${nonce}`
      );
      expect(trash.status(), 'GET trash must be refused').toBe(403);

      // The bulk form on edit.php is method="get", so bulk trash arrives as GET.
      const bulk = await anon.goto(
        `${baseURL}/wp-admin/edit.php?action=trash&post%5B%5D=${ids.postId}&_wpnonce=${nonce}`
      );
      expect(bulk.status(), 'GET bulk trash must be refused').toBe(403);

      // action2 — the bottom bulk selector — on the same footing.
      const bulk2 = await anon.goto(
        `${baseURL}/wp-admin/edit.php?action2=trash&post%5B%5D=${ids.postId}&_wpnonce=${nonce}`
      );
      expect(bulk2.status()).toBe(403);

      // Media, taxonomy terms and comment moderation are the same shape.
      expect(
        (await anon.goto(`${baseURL}/wp-admin/upload.php?action=delete&media%5B%5D=1`)).status()
      ).toBe(403);
      expect(
        (await anon.goto(`${baseURL}/wp-admin/edit-tags.php?taxonomy=category&action=delete&tag_ID=1`)).status()
      ).toBe(403);
      expect(
        (await anon.goto(`${baseURL}/wp-admin/comment.php?action=approvecomment&c=${ids.commentId}`)).status()
      ).toBe(403);

      // Reading a single post is still allowed — action=edit renders the
      // editor, and saving it is a separate POSTed action that is refused.
      const read = await anon.goto(
        `${baseURL}/wp-admin/post.php?post=${ids.postId}&action=edit`
      );
      expect(read.status(), 'reading a post must still work').toBe(200);

      await fresh.close();
      fresh = undefined;

      // And nothing was actually trashed.
      await page.goto(`/wp-admin/post.php?post=${ids.postId}&action=edit`);
      await expect(page.locator('#original_post_status')).toHaveValue('publish');
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            if (fresh) {
              await fresh.close();
            }
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);
    }
  });

  test('personal-data screens are denied one item at a time, not just as lists', async ({
    page,
    context,
  }) => {
    // Security review finding. The data gate matched denied post types only on
    // edit.php, and denied screens only by exact $pagenow — so the single-item
    // editors for the same records (post.php?post=<id>, comment.php?c=<id>)
    // stayed readable by ID while the operator believed data access was off.
    const ids = JSON.parse(supportAccessProbe('ids'));
    supportAccessProbe('deny-pages');

    await login(page);
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);

    // Access open, personal data explicitly NOT opted in.
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    let fresh;

    try {
      fresh = await context.browser().newContext();
      const anon = await fresh.newPage();
      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
      await expect(anon.locator('body.wp-admin')).toHaveCount(1);

      // The list screen was already denied.
      expect((await anon.goto(`${baseURL}/wp-admin/edit.php?post_type=page`)).status()).toBe(403);

      // The single-item editor for the same record must be too. post.php
      // carries no post_type parameter, so the type is resolved from the ID.
      expect(
        (await anon.goto(`${baseURL}/wp-admin/post.php?post=${ids.pageId}&action=edit`)).status(),
        'a denied post type must be denied by ID as well as by list'
      ).toBe(403);

      // A post type that is NOT denied still reads, so the gate has not simply
      // closed the editor for everything.
      expect(
        (await anon.goto(`${baseURL}/wp-admin/post.php?post=${ids.postId}&action=edit`)).status()
      ).toBe(200);

      // The single-comment screen shows the commenter's email and IP; the list
      // it belongs to was denied, so this must be denied on the same footing.
      expect(
        (await anon.goto(`${baseURL}/wp-admin/comment.php?action=editcomment&c=${ids.commentId}`)).status()
      ).toBe(403);
    } finally {
      await restoreAll([
        ['remove the deny-pages fixture', async () => supportAccessProbe('allow-pages')],
        [
          'revoke support key',
          async () => {
            if (fresh) {
              await fresh.close();
            }
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);
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
    await page.goto(ENHANCEMENTS_PATH);

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

    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    let fresh;

    try {
      fresh = await context.browser().newContext();
      const anon = await fresh.newPage();
      await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
      await expect(anon.locator('body.wp-admin')).toHaveCount(1);

      // The support account holds manage_options and is not blocked from the
      // console, so it can read the nonce straight off the page.
      const consoleRead = await anon.goto(`${baseURL}${ENHANCEMENTS_PATH}`);
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
      await page.goto(ENHANCEMENTS_PATH);
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
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
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

    await page.goto(ENHANCEMENTS_PATH);
    const frontendToggle = page.locator('input[name="blueworx_frontend_protection_enabled"]');
    const backendToggle = page.locator('input[name="blueworx_backend_protection_enabled"]');

    const original = {
      frontendEnabled: await frontendToggle.isChecked(),
      backendEnabled: await backendToggle.isChecked(),
      frontendRoles: await readCheckedGroup(page, 'blueworx_frontend_protection_roles'),
      backendRoles: await readCheckedGroup(page, 'blueworx_backend_protection_roles'),
    };

    const password = impostorSupportUser('create');
    expect(password.length).toBeGreaterThan(0);

    let fresh;

    try {
      await frontendToggle.setChecked(true);
      await backendToggle.setChecked(true);
      await setCheckedGroup(page, 'blueworx_frontend_protection_roles', ['administrator']);
      await setCheckedGroup(page, 'blueworx_backend_protection_roles', ['administrator']);
      await page.getByRole('button', { name: 'Save Changes' }).click();
      await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');

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
            await page.goto(ENHANCEMENTS_PATH);
            await page
              .locator('input[name="blueworx_frontend_protection_enabled"]')
              .setChecked(original.frontendEnabled);
            await setCheckedGroup(page, 'blueworx_frontend_protection_roles', original.frontendRoles);
            await page
              .locator('input[name="blueworx_backend_protection_enabled"]')
              .setChecked(original.backendEnabled);
            await setCheckedGroup(page, 'blueworx_backend_protection_roles', original.backendRoles);
            await page.getByRole('button', { name: 'Save Changes' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);

            await page.goto(ENHANCEMENTS_PATH);
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
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
    await page.goto(SUPPORT_PATH);
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
      await page.goto(SUPPORT_PATH);
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
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('the support account can read its own record while other users stay denied', async ({
    page,
    request,
  }) => {
    // /wp/v2/users/me is the account's OWN record, not third-party personal
    // data, and wp-admin fetches it on every page load. Denying it by prefix
    // protects nobody and 403s the whole admin.
    await login(page);
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    const headers = { 'X-BlueWorx-Support-Key': key };

    try {
      const me = await request.get(`${baseURL}/wp-json/wp/v2/users/me?context=edit`, { headers });
      expect(me.status()).toBe(200);
      expect((await me.json()).slug).toBe('blueworx_support');

      const meId = (await me.json()).id;

      // The rest of the collection is untouched by the exemption.
      const list = await request.get(`${baseURL}/wp-json/wp/v2/users`, { headers });
      expect(list.status()).toBe(403);
      expect((await list.json()).code).toBe('blueworx_support_no_data');

      // Every administrator on the harness has a lower ID than the support
      // account, which is created last, so ID 1 is always somebody else.
      expect(meId).not.toBe(1);
      const other = await request.get(`${baseURL}/wp-json/wp/v2/users/1`, { headers });
      expect(other.status()).toBe(403);
      expect((await other.json()).code).toBe('blueworx_support_no_data');
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('heartbeat traffic does not erase the session evidence from the audit log', async ({
    page,
    request,
  }) => {
    // Heartbeat POSTs once a minute from any open tab and is refused as a
    // write. Logged one-per-refusal it evicts the whole 100-entry log —
    // including the events the log exists to prove — in under two hours.
    await login(page);
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    const headers = { 'X-BlueWorx-Support-Key': key };
    const log = page.locator('[data-testid="bw-support-log"] li');

    try {
      await page.goto(SUPPORT_PATH);
      const before = await log.count();

      for (let i = 0; i < 8; i += 1) {
        const beat = await request.post(`${baseURL}/wp-admin/admin-ajax.php?action=heartbeat`, {
          headers,
          form: { action: 'heartbeat', _nonce: 'x' },
        });
        // Still refused — the fix is to stop recording it, not to let it write.
        expect(beat.status()).toBe(403);
      }

      await page.goto(SUPPORT_PATH);
      const after = await log.count();

      // Eight refusals must not cost eight entries.
      expect(after - before).toBeLessThanOrEqual(1);
      await expect(page.locator('[data-testid="bw-support-log"]')).toContainText('access_opened');

      // A real blocked write is still recorded — this must not silence the log.
      await request.post(`${baseURL}/wp-admin/admin-post.php`, { headers, form: { probe: '1' } });
      await page.goto(SUPPORT_PATH);
      await expect(page.locator('[data-testid="bw-support-log"] li').first()).toContainText(
        'blocked_write'
      );
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('a repeated event collapses into one entry carrying a count', async ({ page, request }) => {
    // The general defence behind the Heartbeat fix: any chatty caller, known or
    // not, costs one row rather than one row per request.
    await login(page);
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Generate key' }).click();
    const key = await readSupportKey(page);
    await page.goto(SUPPORT_PATH);
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    const headers = { 'X-BlueWorx-Support-Key': key };

    try {
      await page.goto(SUPPORT_PATH);
      // The log persists across earlier tests, so this measures growth rather
      // than assuming it started empty.
      const before = await page.locator('[data-testid="bw-support-log"] li').count();

      // Each authenticated REST call logs rest_auth once, so four calls are
      // four consecutive identical events.
      for (let i = 0; i < 4; i += 1) {
        const res = await request.get(`${baseURL}/wp-json/wp/v2/settings`, { headers });
        expect(res.status()).toBe(200);
      }

      await page.goto(SUPPORT_PATH);
      const rows = page.locator('[data-testid="bw-support-log"] li');
      expect((await rows.count()) - before).toBeLessThanOrEqual(1);
      await expect(rows.first()).toContainText('rest_auth');
      await expect(rows.first()).toContainText('×4');
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });

  test('the panel offers a one-click Claude Code prompt carrying the fresh key', async ({
    page,
  }) => {
    await login(page);
    await page.goto(SUPPORT_PATH);

    // No key, nothing to connect with: the button must not be offered at all.
    await expect(page.locator('[data-testid="bw-support-copy-prompt"]')).toHaveCount(0);

    await page.getByRole('button', { name: 'Generate key' }).click();

    try {
      const key = await readSupportKey(page);
      const prompt = await page.locator('[data-testid="bw-support-prompt"]').inputValue();

      // The generation render is the ONLY moment the raw key exists, so this is
      // the only render whose prompt can be pasted with nothing left to fill in.
      expect(prompt).toContain(key);
      expect(prompt).not.toContain('<SUPPORT-KEY>');
      expect(prompt).toContain(baseURL);
      expect(prompt).toContain('X-Blueworx-Support-Key');
      expect(prompt).toContain('/wp-json/');
      expect(prompt).toContain('READ ONLY');

      const button = page.locator('[data-testid="bw-support-copy-prompt"]');
      await button.click();
      await expect(button).toHaveText('Copied');

      // Copying must not submit the form it sits inside.
      await expect(page).toHaveURL(new RegExp('page=blueworx-support'));

      // The key is shown once. Every later render still offers the prompt, but
      // with the key left as a placeholder rather than a wrong or stale value.
      await page.reload();
      const later = await page.locator('[data-testid="bw-support-prompt"]').inputValue();
      expect(later).toContain('<SUPPORT-KEY>');
      expect(later).not.toContain(key);
    } finally {
      await restoreAll([
        [
          'revoke support key',
          async () => {
            await page.goto(SUPPORT_PATH);
            await page.getByRole('button', { name: 'Revoke access' }).click({ noWaitAfter: true });
            await page.waitForTimeout(1000);
          },
        ],
      ]);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
    }
  });
});
