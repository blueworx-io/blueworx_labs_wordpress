/**
 * Where a sign-in lands.
 *
 * The rules themselves are covered by tests/php/login-redirect-test.php, which
 * can pose a rival plugin's redirect directly. What that cannot show is a real
 * sign-in against a real WordPress, which is this file's only job: submit the
 * form and look at where the browser actually ends up.
 *
 * The shared login() helper is deliberately NOT used here — it navigates to the
 * dashboard itself after submitting, which is the exact thing under test.
 */

import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, LOGIN_PATH, cacheBust } from './helpers.js';

/**
 * Submits the login form in a clean context and returns where it landed.
 *
 * A fresh context per sign-in: an auth cookie left over from an earlier one
 * would send the login page straight to the dashboard and the assertion would
 * pass without testing anything.
 *
 * @param {import('@playwright/test').Browser} browser Playwright browser.
 * @param {string} [redirectTo] Destination to request, if any.
 * @return {Promise<string>} URL the browser ended on.
 */
async function signInAndLand(browser, redirectTo = '') {
  const context = await browser.newContext();

  try {
    const page = await context.newPage();
    await page.emulateMedia({ reducedMotion: 'reduce' });

    const path = redirectTo
      ? `${LOGIN_PATH}?redirect_to=${encodeURIComponent(redirectTo)}`
      : LOGIN_PATH;

    await page.goto(cacheBust(path));

    if (!(await page.locator('#user_login').count())) {
      throw new Error(
        `No login form at ${LOGIN_PATH}. If the site uses a custom login slug, set WP_LOGIN_PATH.`
      );
    }

    await page.fill('#user_login', ADMIN_USER);
    await page.fill('#user_pass', ADMIN_PASS);
    await page.click('#wp-submit');
    await page.waitForLoadState('domcontentloaded');

    const error = await page.locator('#login_error').innerText().catch(() => '');

    if (error.trim()) {
      throw new Error(`Login failed via ${LOGIN_PATH}. ${error.trim()}`);
    }

    return page.url();
  } finally {
    await context.close();
  }
}

test.describe('Login redirect', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('signing in with nothing requested lands on the dashboard', async ({ browser }) => {
    const landed = await signInAndLand(browser);

    expect(landed, 'a plain sign-in should land on the dashboard').toMatch(
      /\/wp-admin\/(index\.php)?(\?|$)/
    );
  });

  test('a requested page is still honoured', async ({ browser }) => {
    const landed = await signInAndLand(browser, '/wp-admin/options-general.php');

    expect(landed, 'a sign-in that asked for a page should go to that page').toContain(
      '/wp-admin/options-general.php'
    );
  });
});
