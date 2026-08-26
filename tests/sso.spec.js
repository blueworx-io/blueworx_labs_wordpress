/**
 * Single sign-on.
 *
 * These assert the parts visible from outside PHP: that the feature is offered
 * and off by default, that its settings survive a save without ever rendering
 * the client secret, that both entry points build a correct authorization
 * request, and that a callback with a bad state is refused without saying why.
 *
 * The crypto itself — signature and claim checks — is covered by the PHP scripts
 * in tests/php, which run without WordPress.
 */

import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  restoreAll,
  cacheBust,
  setFeature,
} from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const SSO_PATH = '/wp-admin/admin.php?page=blueworx-sso';

const toggleFor = (key) => `input.blueworx-feature-toggle[data-blueworx-feature="${key}"]`;

/**
 * Switches the function on or off.
 *
 * Enhancements carries the switch and nothing else — every setting lives on the
 * screen below, so the two steps cannot be done in one save any more.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @param {boolean} on Whether the function should end up on.
 * @return {Promise<void>} Resolves once the save has landed.
 */
async function setSso(page, on) {
  await page.goto(SETTINGS_PATH);
  await setFeature(page, 'sso', on);
  await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
  await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
}

/**
 * Saves the Single sign-on screen.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @return {Promise<void>} Resolves once the save has landed.
 */
async function save(page) {
  await page.getByRole('button', { name: 'Save changes', exact: true }).click();
  await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
}

test.describe('Single sign-on', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the feature is offered and is off by default', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await expect(page.locator(toggleFor('sso'))).toHaveCount(1);
    await expect(page.locator(toggleFor('sso'))).not.toBeChecked();
  });

  test('the switch on Enhancements carries no settings with it', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);

    // The card is the switch and a way through to the screen that owns the
    // settings. A provider box here would be a second place to change one
    // thing, and a second thing for a save to disagree with. Counted as nodes
    // rather than through the accessibility tree, which skips the panel while
    // the function is off.
    const panel = page.locator('[data-blueworx-detail="sso"]');
    await expect(panel.locator('input, select, textarea')).toHaveCount(0);
    await expect(panel.locator('a[href*="page=blueworx-sso"]')).toHaveCount(1);
  });

  test('saving Enhancements leaves the settings alone', async ({ page }) => {
    await login(page);
    await setSso(page, true);

    await page.goto(SSO_PATH);
    await page.fill('#blueworx_sso_client_id', 'kept-through-a-save');
    await save(page);

    // Enhancements no longer posts these fields, and a save that wrote them
    // from an empty POST would clear the connection without saying so.
    await page.goto(SETTINGS_PATH);
    await page.getByRole('button', { name: 'Save Changes', exact: true }).click();

    await page.goto(SSO_PATH);
    await expect(page.locator('#blueworx_sso_client_id')).toHaveValue('kept-through-a-save');

    await restoreAll([
      [
        'sso off',
        async () => {
          await page.goto(SSO_PATH);
          await page.fill('#blueworx_sso_client_id', '');
          await save(page);
          await setSso(page, false);
        },
      ],
    ]);
  });

  test('saving the screen does not switch anything else off', async ({ page }) => {
    await login(page);
    await setSso(page, true);

    await page.goto(SETTINGS_PATH);
    const before = await page
      .locator('.blueworx-feature-toggle')
      .evaluateAll((els) => els.filter((el) => el.checked).length);

    await page.goto(SSO_PATH);
    await save(page);

    // This screen used to post the Enhancements handler, where a missing
    // checkbox reads as an unticked one — so saving it switched off every
    // function in the plugin, itself included.
    await page.goto(SETTINGS_PATH);
    expect(
      await page.locator('.blueworx-feature-toggle').evaluateAll((els) =>
        els.filter((el) => el.checked).length
      )
    ).toBe(before);

    await restoreAll([['sso off', async () => setSso(page, false)]]);
  });

  test('the settings survive a save and the secret is never rendered', async ({ page }) => {
    await login(page);
    await setSso(page, true);

    await page.goto(SSO_PATH);
    await page.fill('#blueworx_sso_issuer', 'https://idp.test');
    await page.fill('#blueworx_sso_client_id', 'test-client');
    await page.fill('#blueworx_sso_client_secret', 'super-secret-value');
    await page.fill('#blueworx_sso_button_label', 'Sign in with Test IdP');
    await save(page);

    await page.goto(SSO_PATH);
    await expect(page.locator('#blueworx_sso_issuer')).toHaveValue('https://idp.test');
    await expect(page.locator('#blueworx_sso_client_id')).toHaveValue('test-client');

    // A secret that can be read back out of the screen is a secret anyone with
    // admin access — including a read-only support session — can walk off with.
    await expect(page.locator('#blueworx_sso_client_secret')).toHaveValue('');
    expect(await page.content()).not.toContain('super-secret-value');

    await restoreAll([['sso off', async () => setSso(page, false)]]);
  });

  test('the joining destinations survive a save', async ({ page }) => {
    await login(page);
    await setSso(page, true);

    await page.goto(SSO_PATH);
    await page.fill('#blueworx_sso_redirect_after_register', 'https://example.test/register-success/');
    await page.fill('#blueworx_sso_no_account_url', 'https://example.test/join/');
    await save(page);

    await page.goto(SSO_PATH);
    await expect(page.locator('#blueworx_sso_redirect_after_register')).toHaveValue(
      'https://example.test/register-success/'
    );
    await expect(page.locator('#blueworx_sso_no_account_url')).toHaveValue('https://example.test/join/');

    await restoreAll([
      [
        'joining destinations cleared',
        async () => {
          await page.goto(SSO_PATH);
          await page.fill('#blueworx_sso_redirect_after_register', '');
          await page.fill('#blueworx_sso_no_account_url', '');
          await save(page);
          await setSso(page, false);
        },
      ],
    ]);
  });

  test('the allowed domains and the sign-out switch survive a save', async ({ page }) => {
    await login(page);
    await setSso(page, true);

    await page.goto(SSO_PATH);
    await page.fill('#blueworx_sso_allowed_domains', 'example.com, example.co.uk');
    await page.locator('details.blueworx-sso-advanced summary').click();
    await page.locator('[data-testid="bw-sso-single-logout"]').check();
    await page.fill('#blueworx_sso_redirect_after_logout', 'https://example.test/goodbye/');
    await save(page);

    await page.goto(SSO_PATH);
    await expect(page.locator('#blueworx_sso_allowed_domains')).toHaveValue(
      'example.com, example.co.uk'
    );
    await page.locator('details.blueworx-sso-advanced summary').click();
    await expect(page.locator('[data-testid="bw-sso-single-logout"]')).toBeChecked();
    await expect(page.locator('#blueworx_sso_redirect_after_logout')).toHaveValue(
      'https://example.test/goodbye/'
    );

    await restoreAll([
      [
        'domains and sign-out cleared',
        async () => {
          await page.goto(SSO_PATH);
          await page.fill('#blueworx_sso_allowed_domains', '');
          await page.locator('details.blueworx-sso-advanced summary').click();
          await page.locator('[data-testid="bw-sso-single-logout"]').uncheck();
          await page.fill('#blueworx_sso_redirect_after_logout', '');
          await save(page);
          await setSso(page, false);
        },
      ],
    ]);
  });

  test('the callback URL is shown for copying', async ({ page }) => {
    await login(page);
    await setSso(page, true);

    await page.goto(SSO_PATH);
    // A read-only field with a copy button beside it now, not a <code> block:
    // the address exists to be pasted into somebody else's control panel.
    await expect(page.locator('[data-testid="blueworx-sso-callback-url"]')).toHaveValue(
      /blueworx_sso=callback/
    );

    await restoreAll([['sso off', async () => setSso(page, false)]]);
  });
});

test.describe('Single sign-on flow', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  // The provider address is never reachable from the test machine, so the
  // endpoints are set by hand. That exercises the override path too, which is
  // what any provider without a published configuration will use.
  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    // browser.newPage() bypasses the page fixture, and with it the reduced-motion
    // opt-out that keeps headless Chromium painting after a form submit. Setting
    // the function on is a submit, so without this every click after it hangs.
    // See helpers.js.
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await login(page);
    await setSso(page, true);
    await page.goto(SSO_PATH);
    await page.fill('#blueworx_sso_issuer', 'https://idp.test');
    await page.fill('#blueworx_sso_client_id', 'test-client');
    await page.fill('#blueworx_sso_client_secret', 'test-secret');
    await page.fill('#blueworx_sso_button_label', 'Sign in with Test IdP');
    await page.locator('details.blueworx-sso-advanced summary').click();
    await page.fill('#blueworx_sso_authorization_endpoint_override', 'https://idp.test/authorize');
    await page.fill('#blueworx_sso_token_endpoint_override', 'https://idp.test/token');
    await page.selectOption('#blueworx_sso_pkce', 'on');
    await save(page);
    await page.close();
  });

  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage();
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await login(page);
    await setSso(page, false);
    await page.close();
  });

  test('the trigger URL sends people to the provider with state, nonce and PKCE', async ({ page }) => {
    const response = await page.request.get('/?blueworx_sso=login', { maxRedirects: 0 });
    expect(response.status()).toBe(302);

    const target = new URL(response.headers().location);
    expect(target.origin + target.pathname).toBe('https://idp.test/authorize');
    expect(target.searchParams.get('response_type')).toBe('code');
    expect(target.searchParams.get('client_id')).toBe('test-client');
    expect(target.searchParams.get('state')).toHaveLength(43);
    expect(target.searchParams.get('nonce')).toHaveLength(43);
    expect(target.searchParams.get('code_challenge_method')).toBe('S256');
    expect(target.searchParams.get('code_challenge')).toHaveLength(43);
  });

  test('joining asks the provider for its signup screen, and signing in does not', async ({
    page,
  }) => {
    const join = await page.request.get('/?blueworx_sso=register', { maxRedirects: 0 });
    expect(join.status()).toBe(302);
    expect(new URL(join.headers().location).searchParams.get('prompt')).toBe('signup');

    // Sending the signup prompt on a plain sign-in would push people who already
    // have an account into creating a second one.
    const signIn = await page.request.get('/?blueworx_sso=login', { maxRedirects: 0 });
    expect(new URL(signIn.headers().location).searchParams.get('prompt')).toBeNull();
  });

  test('both entry points return to the same address', async ({ page }) => {
    const redirectOf = async (path) => {
      const response = await page.request.get(path, { maxRedirects: 0 });
      return new URL(response.headers().location).searchParams.get('redirect_uri');
    };

    // Providers match the return address exactly and most register only one, so
    // the two buttons must not drift apart here.
    expect(await redirectOf('/?blueworx_sso=register')).toBe(await redirectOf('/?blueworx_sso=login'));
  });

  test('a callback with an unknown state is refused', async ({ page }) => {
    const response = await page.request.get('/?blueworx_sso=callback&code=abc&state=nonsense', {
      maxRedirects: 0,
    });
    expect(response.status()).toBe(302);
    expect(response.headers().location).toContain('blueworx_sso_error=1');
  });

  test('a callback with no state at all is refused', async ({ page }) => {
    const response = await page.request.get('/?blueworx_sso=callback&code=abc', { maxRedirects: 0 });
    expect(response.status()).toBe(302);
    expect(response.headers().location).toContain('blueworx_sso_error=1');
  });

  test('a state can only be used once', async ({ page }) => {
    const started = await page.request.get('/?blueworx_sso=login', { maxRedirects: 0 });
    const state = new URL(started.headers().location).searchParams.get('state');

    // The first callback gets as far as the token exchange, which fails because
    // the provider does not exist. The point is that the second one does not get
    // that far: the state is spent either way.
    await page.request.get(`/?blueworx_sso=callback&code=abc&state=${state}`, { maxRedirects: 0 });
    const replay = await page.request.get(`/?blueworx_sso=callback&code=abc&state=${state}`, {
      maxRedirects: 0,
    });

    expect(replay.status()).toBe(302);
    expect(replay.headers().location).toContain('blueworx_sso_error=1');
  });

  test('the failure message on the login screen gives nothing away', async ({ page }) => {
    await page.goto(cacheBust('/admin_login?blueworx_sso_error=1'));
    const body = await page.locator('body').innerText();

    expect(body).toContain('could not sign you in');
    for (const leak of ['state', 'signature', 'nonce', 'token', 'issuer']) {
      expect(body.toLowerCase()).not.toContain(leak);
    }
  });

  test('the button renders on the login screen with the configured label', async ({ page }) => {
    await page.goto(cacheBust('/admin_login'));

    const button = page.locator('a.blueworx-sso-button');
    await expect(button).toBeVisible();
    await expect(button).toContainText('Sign in with Test IdP');
    await expect(button).toHaveAttribute('href', /blueworx_sso=login/);
  });

  test('no icon font is loaded for it', async ({ page }) => {
    await page.goto(cacheBust('/admin_login'));
    await expect(page.locator('link[href*="font-awesome"]')).toHaveCount(0);
  });

  test('another integration coming back is left alone', async ({ page }) => {
    // code and state belong to OAuth in general, not to this plugin. Treating
    // every request carrying them as ours breaks whichever other integration on
    // the site — payment, booking, another sign-in — returns the same way.
    const response = await page.request.get('/?code=abc&state=not-one-of-ours', {
      maxRedirects: 0,
    });

    expect(response.status()).toBe(200);
  });

  test('a sign-in ties itself to the browser that started it', async ({ page }) => {
    const started = await page.request.get('/?blueworx_sso=login', { maxRedirects: 0 });
    const cookie = started.headers()['set-cookie'] || '';

    expect(cookie).toContain('blueworx_sso_binder');
    expect(cookie).toContain('HttpOnly');
    // Strict would be dropped on the way back from the provider, which is a
    // top-level navigation from another site.
    expect(cookie).toContain('SameSite=Lax');
  });

  test('a callback in a different browser is refused', async ({ page, browser }) => {
    const started = await page.request.get('/?blueworx_sso=login', { maxRedirects: 0 });
    const state = new URL(started.headers().location).searchParams.get('state');

    // Same state, no binding cookie: this is somebody being handed a return
    // address for a sign-in they did not start, which would drop them inside
    // whoever did start it.
    const elsewhere = await browser.newContext();
    const response = await elsewhere.request.get(
      `/?blueworx_sso=callback&code=abc&state=${state}`,
      { maxRedirects: 0 }
    );
    await elsewhere.close();

    expect(response.status()).toBe(302);
    expect(response.headers().location).toContain('blueworx_sso_error=1');
  });

  test('two attempts never reuse a state', async ({ page }) => {
    const first = await page.request.get('/?blueworx_sso=login', { maxRedirects: 0 });
    const second = await page.request.get('/?blueworx_sso=login', { maxRedirects: 0 });

    const stateOf = (response) => new URL(response.headers().location).searchParams.get('state');
    expect(stateOf(first)).not.toBe(stateOf(second));
  });
});
