/**
 * Scanner bots and heavy crawlers are turned away cheaply, and nobody else is.
 *
 * A refused probe has a recognisable shape: a short plain-text body and an
 * X-Robots-Tag header, which no page WordPress builds in full carries. That is
 * how these tests tell the plugin's refusal from a 404 page, or from the web
 * server's own 404.
 */

import { request } from '@playwright/test';
import {
  test,
  expect,
  baseURL,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  LOGIN_PATH,
  login,
  restoreAll,
  setFeature,
  openSectionFor,
} from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

/*
 * Paths without a file extension. The test harness serves the site from PHP's
 * built-in server, which answers a missing path that ends in an extension
 * (backup.zip, db.sql) or a hidden folder with its own 404 and never hands it to
 * WordPress. A real web server passes those on; the extension and hidden-file
 * rules are covered by tests/php/bot-control-test.php.
 */
const PROBES = ['/phpmyadmin/', '/cgi-bin/', '/adminer', '/server-status', '/vendor/phpunit/'];

/*
 * The pause counts visitors by their address. Only a local site (or one behind
 * a proxy that is trusted) believes the X-Forwarded-For these tests send, so
 * on any other site every test client would be one visitor and one test would
 * pause the next. Those tests run on the local site only.
 */
const isLocal = /^https?:\/\/(127\.0\.0\.1|localhost)[:/]/.test(`${baseURL}/`);

/** A visitor address of its own, so one test never pauses another. */
function visitor() {
  return `93.184.${Math.floor(Math.random() * 200) + 20}.${Math.floor(Math.random() * 200) + 20}`;
}

async function save(page) {
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
}

test.describe('Bot control', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('probe paths are refused plainly, and normal pages still load', async () => {
    const api = await request.newContext({ baseURL, extraHTTPHeaders: { 'X-Forwarded-For': visitor() } });

    try {
      for (const path of PROBES) {
        const res = await api.get(path, { maxRedirects: 0 });
        const body = await res.text();

        expect(res.status(), path).toBe(404);
        expect(res.headers()['x-robots-tag'], `${path} should be answered by the plugin`).toBe('noindex');
        expect(res.headers()['content-type'], path).toContain('text/plain');
        expect(body, path).not.toContain('<html');
        expect(body, path).toContain('Not found');
      }

      const home = await api.get('/');
      expect(home.status()).toBe(200);
      expect(await home.text()).toContain('<html');

      const login = await api.get(`${LOGIN_PATH}/`);
      expect(login.status()).toBe(200);

      // A real page that merely does not exist is still a normal WordPress 404.
      const missing = await api.get('/a-page-that-is-not-there/');
      expect(missing.status()).toBe(404);
      expect(missing.headers()['x-robots-tag']).toBeUndefined();
      expect(await missing.text()).toContain('<html');
    } finally {
      await api.dispose();
    }
  });

  test('the answer can be a 403, and the whole function can be switched off', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);

    const api = await request.newContext({ baseURL, extraHTTPHeaders: { 'X-Forwarded-For': visitor() } });

    // Remember what the site had, and put it back afterwards.
    await openSectionFor(page, 'probe_block');
    const wasOn = await page.locator('input.blueworx-feature-toggle[data-blueworx-feature="probe_block"]').isChecked();
    const wasStatus = await page.locator('#blueworx_probe_status').inputValue();

    await restoreAll([
      [
        'answer with 403',
        async () => {
          await openSectionFor(page, 'probe_block');
          await page.locator('#blueworx_probe_status').selectOption('403');
          await save(page);

          const res = await api.get('/phpmyadmin/', { maxRedirects: 0 });
          expect(res.status()).toBe(403);
          expect(res.headers()['x-robots-tag']).toBe('noindex');
        },
      ],
      [
        'switched off, a probe falls through to WordPress',
        async () => {
          await page.goto(SETTINGS_PATH);
          await setFeature(page, 'probe_block', false);
          await save(page);

          const res = await api.get('/phpmyadmin/', { maxRedirects: 0 });
          expect(res.headers()['x-robots-tag']).toBeUndefined();
        },
      ],
      [
        'restore',
        async () => {
          await page.goto(SETTINGS_PATH);
          await setFeature(page, 'probe_block', wasOn);
          await openSectionFor(page, 'probe_block');
          await page.locator('#blueworx_probe_status').selectOption(wasStatus);
          await save(page);
          await api.dispose();
        },
      ],
    ]);
  });

  test('a visitor who keeps asking for missing pages is paused, and can still sign in', async ({ page }) => {
    test.skip(!isLocal, 'Needs a site that believes the visitor address the test sends.');

    const ip = visitor();
    const api = await request.newContext({ baseURL, extraHTTPHeaders: { 'X-Forwarded-For': ip } });
    const other = await request.newContext({ baseURL, extraHTTPHeaders: { 'X-Forwarded-For': visitor() } });

    try {
      let paused = null;

      // The default allowance is 30 in ten minutes.
      for (let i = 0; i < 40 && !paused; i++) {
        const res = await api.get(`/bw-missing-${i}/`, { maxRedirects: 0 });

        if (res.status() === 429) {
          paused = res;
        }
      }

      expect(paused, 'the visitor should have been paused').not.toBeNull();
      expect(Number(paused.headers()['retry-after'])).toBeGreaterThan(0);
      expect(await paused.text()).not.toContain('<html');

      // Paused means no pages, but sign-in is never part of it.
      const signIn = await api.get(`${LOGIN_PATH}/`);
      expect(signIn.status()).toBe(200);

      // The REST API is never paused, in either address format.
      const rest = await api.get('/?rest_route=/');
      expect(rest.status()).toBe(200);

      // Somebody signed in from the same address is not paused either.
      await page.context().setExtraHTTPHeaders({ 'X-Forwarded-For': ip });
      await login(page);
      const signedIn = await page.goto('/');
      expect(signedIn.status()).toBe(200);

      // A different visitor is not affected.
      const home = await other.get('/');
      expect(home.status()).toBe(200);
    } finally {
      await api.dispose();
      await other.dispose();
    }
  });

  test('visitors who share one address behind an unknown proxy are never paused', async () => {
    test.skip(!isLocal, 'Needs a site that believes the visitor address the test sends.');

    // Every request reaches the site from the same proxy, as it does behind a
    // content network nobody has told the site about.
    const proxy = visitor();
    const scanner = await request.newContext({
      baseURL,
      extraHTTPHeaders: { 'X-Forwarded-For': `${visitor()}, ${proxy}` },
    });
    const shopper = await request.newContext({
      baseURL,
      extraHTTPHeaders: { 'X-Forwarded-For': `${visitor()}, ${proxy}` },
    });

    try {
      for (let i = 0; i < 35; i++) {
        const res = await scanner.get(`/bw-shared-${i}/`, { maxRedirects: 0 });
        expect(res.status()).toBe(404);
      }

      const home = await shopper.get('/');
      expect(home.status()).toBe(200);
    } finally {
      await scanner.dispose();
      await shopper.dispose();
    }
  });

  test('missing images, scripts and manifests do not count towards the limit', async () => {
    test.skip(!isLocal, 'Needs a site that believes the visitor address the test sends.');

    const api = await request.newContext({ baseURL, extraHTTPHeaders: { 'X-Forwarded-For': visitor() } });

    try {
      /*
       * Through index.php, so the request reaches WordPress: the test server
       * answers a missing file that ends in an extension itself.
       */
      const kinds = ['jpg', 'mjs', 'webmanifest', 'css', 'json'];

      for (let i = 0; i < 40; i++) {
        const res = await api.get(`/index.php/bw-missing-${i}.${kinds[i % kinds.length]}`, { maxRedirects: 0 });
        expect(res.status()).not.toBe(429);
        expect(res.headers()['x-robots-tag']).toBeUndefined();
      }

      const home = await api.get('/');
      expect(home.status()).toBe(200);
    } finally {
      await api.dispose();
    }
  });

  test('robots.txt keeps crawlers off cart, checkout and filtered shop pages', async () => {
    const api = await request.newContext({ baseURL });

    try {
      // `?robots=1` is the query the pretty /robots.txt path rewrites onto.
      const res = await api.get('/?robots=1');
      expect(res.status()).toBe(200);

      const text = await res.text();
      expect(text).toContain('Disallow: /cart/');
      expect(text).toContain('Disallow: /checkout/');
      expect(text).toContain('Disallow: /*?*orderby=');
      expect(text.toLowerCase().match(/user-agent:/g)).toHaveLength(1);
    } finally {
      await api.dispose();
    }
  });
});
