/**
 * The logged-out wp-admin redirect must never be cached.
 *
 * When someone hits a wp-admin URL signed out, the plugin sends them to the home
 * page. Where that redirect goes depends entirely on login state, so it can only
 * ever be temporary — a permanent one is cached by the browser against that exact
 * URL, and every later visit follows the cached copy without asking the site
 * again. Signing back in does not clear it; only clearing the browser cache does.
 *
 * Seen live on a demo site: "Add New Plugin" always landed on the home page while
 * the rest of wp-admin worked, because that one URL had been opened once while
 * signed out.
 *
 * The response test is the real guard — it fails against the old permanent
 * redirect, where the browser one does not. Headless Chromium on a fresh context
 * declines to reuse the cached 301 (no Last-Modified, so its heuristic freshness
 * is zero and it revalidates anyway), which is precisely why this shipped: the
 * bug needs a real browser with a warm profile to show itself. The browser test
 * is kept for the journey it covers end to end, not for the caching.
 */

// `test` comes from helpers.js, not '@playwright/test' — see the note there.
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

/**
 * An admin screen nothing else in the suite touches, so a cached redirect left
 * behind here cannot quietly break another spec.
 */
const ADMIN_PATH = '/wp-admin/plugin-install.php';

test.describe('Logged-out wp-admin redirect', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('answers a temporary redirect home, and forbids caching it', async ({ request, baseURL }) => {
    const response = await request.get(ADMIN_PATH, { maxRedirects: 0 });

    expect(
      response.status(),
      'the destination depends on login state, so the redirect must be temporary'
    ).toBe(302);

    const headers = response.headers();

    expect(headers.location, 'a signed-out admin request belongs on the home page').toBe(
      `${baseURL}/`
    );

    expect(
      headers['cache-control'],
      'without no-store the browser is free to reuse this redirect'
    ).toContain('no-store');
  });

  test('the same URL loads normally once signed in', async ({ browser }) => {
    // A fresh context so the signed-out visit below is genuinely this browser's
    // first sight of the URL — the whole bug is what it remembers afterwards.
    const context = await browser.newContext();

    try {
      const page = await context.newPage();
      await page.emulateMedia({ reducedMotion: 'reduce' });

      await page.goto(ADMIN_PATH);
      expect(page.url(), 'signed out, an admin URL should land on the home page').not.toContain(
        '/wp-admin/'
      );

      await login(page);
      await page.goto(ADMIN_PATH);

      expect(
        page.url(),
        'signed in, the same URL must reach the screen rather than a remembered redirect'
      ).toContain(ADMIN_PATH);
      await expect(page.locator('body.wp-admin')).toHaveCount(1);
    } finally {
      await context.close();
    }
  });
});
