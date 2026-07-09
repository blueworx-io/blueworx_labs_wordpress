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
});
