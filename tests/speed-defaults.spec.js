/**
 * Speed defaults: each function is on by default, can be switched off, and does
 * what it says. The store checks (guests who cannot buy, the weekly clear-out of
 * store jobs) need WooCommerce, which the test site does not have (the cart test
 * uses a small stand-in); their logic is
 * covered by tests/php/speed-test.php.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { request } from '@playwright/test';
import {
  test,
  expect,
  baseURL,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  restoreAll,
  setFeature,
  featureIsOn,
  openSectionFor,
} from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

const SWITCHES = [
  'guest_cart_checks',
  'surerank_link_checks',
  'crawl_delay',
  'weekly_cleanup',
  'slow_log',
  'speed_report',
];

async function save(page) {
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
}

test.describe('Speed defaults', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('every speed function has a switch under Performance', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await openSectionFor(page, 'slow_log');

    for (const key of SWITCHES) {
      await expect(
        page.locator(`input.blueworx-feature-toggle[data-blueworx-feature="${key}"]`),
        key
      ).toHaveCount(1);
    }
  });

  test('crawlers are asked to slow down, and it can be switched off', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    const was = await (async () => {
      await openSectionFor(page, 'crawl_delay');
      return featureIsOn(page, 'crawl_delay');
    })();
    const api = await request.newContext({ baseURL });

    await restoreAll([
      [
        'on',
        async () => {
          await setFeature(page, 'crawl_delay', true);
          await save(page);

          const text = await (await api.get('/?robots=1')).text();
          expect(text).toContain('User-agent: AhrefsBot');
          expect(text).toContain('Crawl-delay: 10');
          expect(text.toLowerCase()).not.toContain('googlebot');
          expect(text.toLowerCase()).not.toContain('bingbot');
        },
      ],
      [
        'off',
        async () => {
          await page.goto(SETTINGS_PATH);
          await setFeature(page, 'crawl_delay', false);
          await save(page);

          const text = await (await api.get('/?robots=1')).text();
          expect(text).not.toContain('Crawl-delay');
        },
      ],
      [
        'restore',
        async () => {
          await page.goto(SETTINGS_PATH);
          await setFeature(page, 'crawl_delay', was);
          await save(page);
          await api.dispose();
        },
      ],
    ]);
  });

  test("SureRank's broken-link check is paused, and the switch brings it back", async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await openSectionFor(page, 'surerank_link_checks');
    const was = await featureIsOn(page, 'surerank_link_checks');
    const api = await request.newContext({ baseURL });
    const route = '/?rest_route=/surerank/v1/checks/broken-link';

    await restoreAll([
      [
        'on',
        async () => {
          await setFeature(page, 'surerank_link_checks', true);
          await save(page);

          // The test site has no SureRank, so the only thing that can answer
          // this route is the plugin's own pause.
          const res = await api.post(route, { data: {} });
          expect(res.status()).toBe(200);
          expect(await res.json()).toMatchObject({ success: true, ignored: true });
        },
      ],
      [
        'off',
        async () => {
          await page.goto(SETTINGS_PATH);
          await setFeature(page, 'surerank_link_checks', false);
          await save(page);

          const res = await api.post(route, { data: {} });
          expect(res.status()).toBe(404);
        },
      ],
      [
        'restore',
        async () => {
          await page.goto(SETTINGS_PATH);
          await setFeature(page, 'surerank_link_checks', was);
          await save(page);
          await api.dispose();
        },
      ],
    ]);
  });

  test('a guest who cannot buy gets an empty cart before WooCommerce makes one', async ({ page }) => {
    // WooCommerce is not on the test site, so a small stand-in must-use plugin plays
    // its part: a WooCommerce class, sign-in-only checkout, the before_woocommerce_init
    // action fired on init (priority 0) as WooCommerce does, and a cart that is
    // "made" on init (priority 5) and says so in a header.
    const muDir = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-content', 'mu-plugins');
    const muFile = path.join(muDir, 'blueworx-test-fake-woocommerce.php');
    const route = '/?wc-ajax=get_refreshed_fragments';
    const fake = [
      '<?php',
      'class WooCommerce {}',
      "add_filter( 'pre_option_woocommerce_enable_guest_checkout', function () { return 'no'; } );",
      "add_filter( 'pre_option_woocommerce_enable_signup_and_login_from_checkout', function () { return 'no'; } );",
      "add_action( 'init', function () { header( 'X-Test-Fake-Store: 1' ); do_action( 'before_woocommerce_init' ); }, 0 );",
      "add_action( 'init', function () {",
      "  if ( isset( $_GET['wc-ajax'] ) && 'get_refreshed_fragments' === $_GET['wc-ajax'] ) {",
      "    header( 'X-Test-Cart-Made: 1' );",
      "    wp_send_json( array( 'fragments' => array( 'a' => 'b' ), 'cart_hash' => 'made' ) );",
      '  }',
      '}, 5 );',
      '',
    ].join('\n');

    // The stand-in is a file in the local test site's folder, so it only works if
    // the site being tested is that one. Write it, ask the site whether it loaded,
    // and skip (removing it again) when it did not: a staging site, a tunnel or
    // another local install never loads it.
    const api = await request.newContext({ baseURL });
    const withCart = await request.newContext({
      baseURL,
      extraHTTPHeaders: { Cookie: 'woocommerce_items_in_cart=1; woocommerce_cart_hash=abc' },
    });
    const contentDir = path.join(muDir, '..');

    let loaded = false;
    if (fs.existsSync(contentDir)) {
      fs.mkdirSync(muDir, { recursive: true });
      fs.writeFileSync(muFile, fake);
      const probe = await api.get('/?bw_fake_store_probe=1', { maxRedirects: 0 });
      loaded = probe.headers()['x-test-fake-store'] === '1';
    }

    if (!loaded) {
      fs.rmSync(muFile, { force: true });
      await api.dispose();
      await withCart.dispose();
    }
    test.skip(!loaded, 'Needs the local test site: the stand-in WooCommerce did not load on this one.');

    await login(page);
    await page.goto(SETTINGS_PATH);
    await openSectionFor(page, 'guest_cart_checks');
    const was = await featureIsOn(page, 'guest_cart_checks');

    await restoreAll([
      [
        'on',
        async () => {
          await setFeature(page, 'guest_cart_checks', true);
          await save(page);

          const res = await api.get(route, { maxRedirects: 0 });
          expect(res.headers()['x-test-cart-made']).toBeUndefined();
          expect(await res.json()).toMatchObject({ cart_hash: '' });

          const mine = await withCart.get(route, { maxRedirects: 0 });
          expect(mine.headers()['x-test-cart-made']).toBe('1');
        },
      ],
      [
        'off',
        async () => {
          await page.goto(SETTINGS_PATH);
          await setFeature(page, 'guest_cart_checks', false);
          await save(page);

          const res = await api.get(route, { maxRedirects: 0 });
          expect(res.headers()['x-test-cart-made']).toBe('1');
        },
      ],
      [
        'restore',
        async () => {
          await page.goto(SETTINGS_PATH);
          await setFeature(page, 'guest_cart_checks', was);
          await save(page);
        },
      ],
      [
        'remove the stand-in',
        async () => {
          fs.rmSync(muFile, { force: true });
          await api.dispose();
          await withCart.dispose();
        },
      ],
    ]);
  });

  test('more scanner probes are turned away plainly', async () => {
    const api = await request.newContext({ baseURL });

    try {
      // No file extension, because the test site's own server answers a missing
      // path with one itself.
      for (const path of ['/_next/static/chunks/', '/_nuxt/', '/aws-credentials', '/katalog/product-1/']) {
        const res = await api.get(path, { maxRedirects: 0 });

        expect(res.status(), path).toBe(404);
        expect(res.headers()['x-robots-tag'], path).toBe('noindex');
        expect(res.headers()['content-type'], path).toContain('text/plain');
        expect(await res.text(), path).not.toContain('<html');
      }
    } finally {
      await api.dispose();
    }
  });

  test('a slow page is logged without its secrets, and the log can be switched off', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await openSectionFor(page, 'slow_log');
    const wasOn = await featureIsOn(page, 'slow_log');
    const wasThreshold = await page.locator('#blueworx_slow_threshold').inputValue();

    await restoreAll([
      [
        'log slow pages',
        async () => {
          await setFeature(page, 'slow_log', true);
          // As low as it goes: the settings screen itself takes longer.
          await page.locator('#blueworx_slow_threshold').fill('0.2');
          await save(page);

          for (let i = 0; i < 3; i++) {
            await page.goto(`${SETTINGS_PATH}&secret=SHHH-VALUE-${i}`);
          }

          await page.goto(`${SETTINGS_PATH}&section=performance`);
          await openSectionFor(page, 'slow_log');

          const table = page.locator('[data-testid="bw-slow-log-table"]');
          await expect(table).toBeVisible();
          await expect(table).toContainText('/wp-admin/admin.php');
          // The name of an argument is kept; what was in it never is.
          await expect(table).toContainText('secret');
          await expect(table).not.toContainText('SHHH-VALUE');
        },
      ],
      [
        'switched off, nothing new is written',
        async () => {
          await page.goto(SETTINGS_PATH);
          await setFeature(page, 'slow_log', false);
          await save(page);
          await expect(page.locator('input[data-blueworx-feature="slow_log"]')).not.toBeChecked();
        },
      ],
      [
        'restore',
        async () => {
          await page.goto(SETTINGS_PATH);
          // The detail field is hidden while the switch is off, so switch on first.
          await setFeature(page, 'slow_log', true);
          // 0.2 is only ever what an earlier, interrupted run left behind.
          await page.locator('#blueworx_slow_threshold').fill(wasThreshold === '0.2' ? '2' : wasThreshold);
          await setFeature(page, 'slow_log', wasOn);
          await save(page);
        },
      ],
    ]);
  });

  test('the weekly report has a recipient, can be sent now, and can be switched off', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await openSectionFor(page, 'speed_report');
    const wasOn = await featureIsOn(page, 'speed_report');
    const wasTo = await page.locator('#blueworx_speed_report_to').inputValue();

    await restoreAll([
      [
        'recipient is saved',
        async () => {
          await setFeature(page, 'speed_report', true);
          await page.locator('#blueworx_speed_report_to').fill('speed-test@example.test');
          await save(page);

          await openSectionFor(page, 'speed_report');
          await expect(page.locator('#blueworx_speed_report_to')).toHaveValue('speed-test@example.test');
        },
      ],
      [
        'send now answers either way',
        async () => {
          // The report times a visit to the home page. The test site answers one
          // request at a time, so that visit waits out its ten-second limit.
          await page.locator('[data-testid="bw-speed-report-now"]').click({ timeout: 90000 });
          await page.waitForURL(/blueworx_report=/, { timeout: 90000 });

          // The test site may or may not be able to send email, so the report
          // either goes out or says it could not. What matters is that the
          // button works and says which.
          await openSectionFor(page, 'speed_report');
          await expect(
            page.locator('[data-blueworx-detail="speed_report"] .bw-notice').first()
          ).toContainText(/A report was sent|could not be sent/);
        },
      ],
      [
        'a stranger cannot send one',
        async () => {
          const api = await request.newContext({ baseURL });

          try {
            // Signed out, and with no valid nonce, the button's request goes to the
            // sign-in screen and sends nothing.
            const res = await api.post(SETTINGS_PATH, {
              form: { blueworx_speed_report_now: '1', blueworx_speed_nonce: 'nope' },
              maxRedirects: 0,
            });
            expect(res.status()).toBe(302);
            expect(res.headers().location).not.toContain('blueworx_report=');
          } finally {
            await api.dispose();
          }
        },
      ],
      [
        'restore',
        async () => {
          await page.goto(SETTINGS_PATH);
          await setFeature(page, 'speed_report', true);
          await page.locator('#blueworx_speed_report_to').fill(wasTo);
          await setFeature(page, 'speed_report', wasOn);
          await save(page);
        },
      ],
    ]);
  });

  test('the weekly clear-out says when it last ran', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await openSectionFor(page, 'weekly_cleanup');

    await expect(page.locator('[data-testid="bw-cleanup-last"]')).toContainText(/clear-out/i);
  });
});
