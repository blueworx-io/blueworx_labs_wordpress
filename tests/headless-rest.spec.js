import { test, expect, request } from '@playwright/test';

const baseURL =
  process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || 'https://staging.placeholder.blueworx.io';
const isPlaceholder = /placeholder/i.test(baseURL);
const ns = '/wp-json/blueworx/v1';

test.describe('Headless REST layer', () => {
  test.skip(isPlaceholder, 'No real staging/preview URL configured yet (placeholder in use).');

  let api;

  test.beforeAll(async () => {
    api = await request.newContext({ baseURL });
  });

  test.afterAll(async () => {
    await api.dispose();
  });

  test('GET /site returns public settings', async () => {
    const res = await api.get(`${ns}/site`);
    expect(res.status(), 'site endpoint should be public').toBe(200);
    const body = await res.json();
    expect(body).toHaveProperty('name');
    expect(body).toHaveProperty('url');
  });

  test('GET /auth/me is unauthorized without a token', async () => {
    const res = await api.get(`${ns}/auth/me`);
    expect(res.status(), 'me requires authentication').toBe(401);
  });

  test('POST /auth/login rejects bad credentials without leaking existence', async () => {
    const res = await api.post(`${ns}/auth/login`, {
      data: { login: 'definitely-not-a-real-user@example.test', password: 'wrong-password' },
    });

    // A site with no JWT secret set answers 503 blueworx_auth_unconfigured and
    // never looks at the credentials, so there is no rejection behaviour here to
    // assert. That is an environment gap, not a defect, so skip loudly instead of
    // reporting a red that no code change can fix. Configure auth on the target
    // site to get real coverage. Any OTHER 503 still fails below.
    if (503 === res.status()) {
      const body = await res.json().catch(() => ({}));
      test.skip(
        'blueworx_auth_unconfigured' === body.code,
        'Auth is not configured on this site (503 blueworx_auth_unconfigured), so credential rejection cannot be asserted.'
      );
    }

    // 401 (invalid) or 429 (locked out after repeated runs) — never 200.
    expect([401, 429]).toContain(res.status());
  });

  test('GET /resolve maps a path to an object shape', async () => {
    const res = await api.get(`${ns}/resolve`, { params: { uri: '/' } });
    expect(res.status()).toBe(200);
    const body = await res.json();
    expect(body).toHaveProperty('type');
    expect(body).toHaveProperty('template');
  });

  test('CORS is not granted to a disallowed origin', async () => {
    const res = await api.get(`${ns}/site`, { headers: { Origin: 'https://not-allowed.example.com' } });
    const acao = res.headers()['access-control-allow-origin'];
    expect(acao, 'disallowed origins must not be echoed').not.toBe('https://not-allowed.example.com');
  });

  // wp/v2 matters as much as our own namespace: the headless front-end reads
  // content bodies from it, so a permissive handler there is just as exploitable.
  // Core's default echoes any origin with credentials, and removing it is the
  // only thing that closes this — an allowlist alongside it does nothing.
  test('CORS is not granted to a disallowed origin on core routes either', async () => {
    const res = await api.get('wp/v2/types', { headers: { Origin: 'https://not-allowed.example.com' } });
    const acao = res.headers()['access-control-allow-origin'];
    expect(acao, 'core routes must not echo disallowed origins').not.toBe(
      'https://not-allowed.example.com'
    );
  });

  // A denied request must still vary on Origin, or a shared cache can hand one
  // origin's response to another and undo the check above.
  test('responses vary on Origin even when CORS is denied', async () => {
    const res = await api.get(`${ns}/site`, { headers: { Origin: 'https://not-allowed.example.com' } });
    expect(String(res.headers().vary || '')).toContain('Origin');
  });

  // POST /render runs do_shortcode, so a shortcode tag is effectively a
  // function name an unauthenticated caller can invoke. These assert the gate,
  // not the rendering — they hold whether or not any tag is allowlisted on the
  // site under test, so they never depend on ambient configuration.
  test.describe('POST /render', () => {
    test('refuses a tag that is not on the allowlist', async () => {
      const res = await api.post(`${ns}/render`, {
        data: { content: '[bw_definitely_not_allowlisted]' },
      });

      // Three refusals are all correct here, and which one you get depends on
      // the site's configuration rather than on the property being guarded:
      //   403 blueworx_render_disabled     — no allowlist configured at all
      //   403 blueworx_render_not_allowed  — registered tag, not allowlisted
      //   400 blueworx_render_no_shortcode — tag isn't registered, so
      //       get_shortcode_regex() (which matches only registered tags) never
      //       sees it and do_shortcode would leave it as literal text
      // The property under test is that it never renders, so assert that.
      expect(res.status(), 'an arbitrary tag must never render').toBeGreaterThanOrEqual(400);

      const body = await res.json();
      expect(body).not.toHaveProperty('html');
    });

    test('rejects content with no shortcode in it', async () => {
      const res = await api.post(`${ns}/render`, { data: { content: 'just some text' } });

      // 403 when the endpoint is disabled entirely, 400 when it is on and the
      // content simply has nothing to render. Both are refusals; neither is 200.
      expect([400, 403]).toContain(res.status());
    });

    test('requires content', async () => {
      const res = await api.post(`${ns}/render`, { data: {} });
      expect(res.status()).toBeGreaterThanOrEqual(400);
    });
  });
});
