/**
 * Login session length.
 *
 * Measured from the auth cookie's own expiry rather than by waiting, which is
 * the only way to test this in finite time: the cookie carries the exact moment
 * the session ends, and that value IS the setting taking effect.
 */

import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login, restoreAll } from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const HOUR = 60 * 60;

async function save(page) {
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
}

/**
 * Hours until the WordPress auth cookie expires, from a freshly signed-in
 * context.
 *
 * Core writes the browser cookie 12 hours PAST the session it represents — its
 * own slack, so a session that has just lapsed can still be recognised and
 * refreshed rather than silently vanishing. So a 24-hour setting shows here as
 * roughly 36, and a 7-day one as roughly 180. The ranges below allow for that
 * and for a slow sign-in.
 *
 * @param {import('@playwright/test').BrowserContext} context Browser context.
 * @return {Promise<number>} Hours, rounded to the nearest whole hour.
 */
async function authCookieHours(context) {
  const cookies = await context.cookies();
  const auth = cookies.find((cookie) => cookie.name.startsWith('wordpress_logged_in_'));

  expect(auth, 'a signed-in context should carry a wordpress_logged_in_ cookie').toBeTruthy();
  expect(auth.expires, 'the auth cookie should carry an expiry, not be session-only').toBeGreaterThan(0);

  return Math.round((auth.expires - Date.now() / 1000) / HOUR);
}

test.describe('Login session length', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the setting is offered and the session lasts as long as it says', async ({ page, browser }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);

    const select = page.locator('#blueworx_login_session');
    await expect(select, 'the settings page should offer a session length').toHaveCount(1);

    const original = await select.inputValue();

    await restoreAll([
      [
        'a fresh sign-in under the 24 hour default lasts about a day',
        async () => {
          await select.selectOption('24h');
          await save(page);

          const context = await browser.newContext();

          try {
            const fresh = await context.newPage();
            await fresh.emulateMedia({ reducedMotion: 'reduce' });
            await login(fresh);

            const hours = await authCookieHours(context);
            expect(hours, '24 hours selected should give a roughly 24-hour session').toBeGreaterThan(32);
            expect(hours).toBeLessThan(40);
          } finally {
            await context.close();
          }
        },
      ],
      [
        'changing it to 7 days changes the session',
        async () => {
          await page.goto(SETTINGS_PATH);
          await page.locator('#blueworx_login_session').selectOption('7d');
          await save(page);

          const context = await browser.newContext();

          try {
            const fresh = await context.newPage();
            await fresh.emulateMedia({ reducedMotion: 'reduce' });
            await login(fresh);

            const hours = await authCookieHours(context);
            expect(hours, '7 days selected should give a roughly week-long session').toBeGreaterThan(174);
            expect(hours).toBeLessThan(186);
          } finally {
            await context.close();
          }
        },
      ],
      [
        'restore the original setting',
        async () => {
          await page.goto(SETTINGS_PATH);
          await page.locator('#blueworx_login_session').selectOption(original);
          await save(page);
        },
      ],
    ]);
  });
});
