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
} from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

const toggleFor = (key) => `input.blueworx-feature-toggle[data-blueworx-feature="${key}"]`;

async function save(page) {
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
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

  test('the settings survive a save and the secret is never rendered', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await page.locator(toggleFor('sso')).setChecked(true);
    await page.fill('#blueworx_sso_issuer', 'https://idp.test');
    await page.fill('#blueworx_sso_client_id', 'test-client');
    await page.fill('#blueworx_sso_client_secret', 'super-secret-value');
    await page.fill('#blueworx_sso_button_label', 'Sign in with Test IdP');
    await save(page);

    await page.goto(SETTINGS_PATH);
    await expect(page.locator('#blueworx_sso_issuer')).toHaveValue('https://idp.test');
    await expect(page.locator('#blueworx_sso_client_id')).toHaveValue('test-client');

    // A secret that can be read back out of the screen is a secret anyone with
    // admin access — including a read-only support session — can walk off with.
    await expect(page.locator('#blueworx_sso_client_secret')).toHaveValue('');
    expect(await page.content()).not.toContain('super-secret-value');

    await restoreAll([
      [
        'sso off',
        async () => {
          await page.goto(SETTINGS_PATH);
          await page.locator(toggleFor('sso')).setChecked(false);
          await save(page);
        },
      ],
    ]);
  });

  test('the joining destinations survive a save', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await page.locator(toggleFor('sso')).setChecked(true);
    await page.fill('#blueworx_sso_redirect_after_register', 'https://example.test/register-success/');
    await page.fill('#blueworx_sso_no_account_url', 'https://example.test/join/');
    await save(page);

    await page.goto(SETTINGS_PATH);
    await expect(page.locator('#blueworx_sso_redirect_after_register')).toHaveValue(
      'https://example.test/register-success/'
    );
    await expect(page.locator('#blueworx_sso_no_account_url')).toHaveValue('https://example.test/join/');

    await restoreAll([
      [
        'joining destinations cleared',
        async () => {
          await page.goto(SETTINGS_PATH);
          await page.fill('#blueworx_sso_redirect_after_register', '');
          await page.fill('#blueworx_sso_no_account_url', '');
          await page.locator(toggleFor('sso')).setChecked(false);
          await save(page);
        },
      ],
    ]);
  });

  test('the callback URL is shown for copying', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await expect(page.locator('.blueworx-sso-callback-url')).toContainText('blueworx_sso=callback');
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
    await login(page);
    await page.goto(SETTINGS_PATH);
    await page.locator(toggleFor('sso')).setChecked(true);
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
    await login(page);
    await page.goto(SETTINGS_PATH);
    await page.locator(toggleFor('sso')).setChecked(false);
    await save(page);
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

  test('two attempts never reuse a state', async ({ page }) => {
    const first = await page.request.get('/?blueworx_sso=login', { maxRedirects: 0 });
    const second = await page.request.get('/?blueworx_sso=login', { maxRedirects: 0 });

    const stateOf = (response) => new URL(response.headers().location).searchParams.get('state');
    expect(stateOf(first)).not.toBe(stateOf(second));
  });
});
