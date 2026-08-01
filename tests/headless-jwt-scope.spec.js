/**
 * Bearer tokens must be scoped to the blueworx/v1 namespace.
 *
 * The headless layer hooks `determine_current_user`, which core consults for
 * EVERY request — not just ours. A valid token therefore used to authenticate
 * the whole of core `wp/v2` as well, which is a far wider grant than the
 * headless contract needs: a token minted so a front end can read its own
 * account could also read and write `wp/v2/settings`.
 *
 * These assert both halves of the fix — the grant still works where it should,
 * and stops where it should — plus the behaviour it must not break: core's own
 * cookie+nonce authentication is untouched.
 */

import { request } from '@playwright/test';
import { test, expect, baseURL, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

const ns = '/wp-json/blueworx/v1';
const core = '/wp-json/wp/v2';

test.describe('Bearer tokens are scoped to blueworx/v1', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'Needs a real site and admin credentials — a token has to be minted to test what it can reach.'
  );

  let api;
  let token = '';
  let userId = 0;

  test.beforeAll(async () => {
    api = await request.newContext({ baseURL });

    const res = await api.post(`${ns}/auth/login`, {
      data: { login: ADMIN_USER, password: ADMIN_PASS },
    });

    if (res.ok()) {
      const body = await res.json();
      token = body.access_token || '';
      userId = body.user ? Number(body.user.id) : 0;
    }
  });

  test.afterAll(async () => {
    if (api) {
      await api.dispose();
    }
  });

  /**
   * A site with no JWT secret answers 503 blueworx_auth_unconfigured and never
   * mints anything, so there is no grant to scope. That is an environment gap,
   * not a defect — skip loudly rather than report a red no code change fixes.
   */
  const requireToken = () => {
    test.skip(
      '' === token,
      'No access token could be minted (auth is probably unconfigured on this site), so token scope cannot be asserted.'
    );
  };

  test('a valid bearer token authenticates a blueworx/v1 route', async () => {
    requireToken();

    const res = await api.get(`${ns}/auth/me`, {
      headers: { Authorization: `Bearer ${token}` },
    });

    expect(res.status(), 'our own namespace is exactly what the token is for').toBe(200);
    expect((await res.json()).id).toBe(userId);
  });

  test('the same token does not authenticate core wp/v2', async () => {
    requireToken();

    const res = await api.get(`${core}/users/me`, {
      headers: { Authorization: `Bearer ${token}` },
    });

    expect(
      res.status(),
      'a blueworx token must leave core requests anonymous, so users/me is unauthorized'
    ).toBe(401);
  });

  // users/me only exposes the identity. This is the one that would hurt: an
  // administrator token reaching wp/v2/settings can rewrite the site.
  test('the same token grants no authority over core settings', async () => {
    requireToken();

    const res = await api.get(`${core}/settings`, {
      headers: { Authorization: `Bearer ${token}` },
    });

    expect(res.status(), 'core settings must not be reachable with a bearer token').toBe(401);
  });

  // The filter fails silently by design so cookie auth still resolves. Scoping
  // it must not turn that into a refusal for core's own authenticated requests.
  test('cookie-authenticated core REST still works', async ({ page }) => {
    await login(page);

    const result = await page.evaluate(async () => {
      const nonce = window.wpApiSettings && window.wpApiSettings.nonce;

      if (!nonce) {
        return { nonce: false };
      }

      const res = await fetch(`${window.wpApiSettings.root}wp/v2/users/me`, {
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': nonce },
      });

      return { nonce: true, status: res.status, body: await res.json() };
    });

    test.skip(
      false === result.nonce,
      'No wpApiSettings nonce on this admin screen, so a cookie-authenticated REST call cannot be made.'
    );

    expect(result.status, 'cookie + nonce auth is core behaviour and must be untouched').toBe(200);
    expect(result.body.id).toBeGreaterThan(0);
  });

  // A bearer token on a core route must not merely fail to authenticate — it
  // must not break the request either. Public core routes stay public.
  test('a bearer token leaves public core routes public', async () => {
    requireToken();

    const res = await api.get(`${core}/types`, {
      headers: { Authorization: `Bearer ${token}` },
    });

    expect(res.status(), 'an anonymous-readable core route stays readable').toBe(200);
  });
});
