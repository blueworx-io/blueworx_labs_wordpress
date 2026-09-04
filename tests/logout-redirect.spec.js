/**
 * Where a sign-out lands.
 *
 * The rule is covered by tests/php/logout-redirect-test.php. This does the one
 * thing that cannot: a real sign-out against a real WordPress, to see where the
 * browser actually ends up — and that a blank setting really does hand back to
 * WordPress.
 *
 * The setting is written and then put back to blank in a finally, so a failed
 * run cannot leave every later sign-out in the suite landing somewhere odd.
 */

import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  openSectionFor,
  saveEnhancements,
} from './helpers.js';

const SETTINGS = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const FIELD = '#blueworx_logout_redirect';

/**
 * Writes the landing page setting.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @param {string} value Address, or an empty string to hand back to WordPress.
 * @return {Promise<void>} Resolves once the save has landed.
 */
async function setLandingPage(page, value) {
  await page.goto(SETTINGS);
  await openSectionFor(page, 'login');
  await page.fill(FIELD, value);
  await saveEnhancements(page);
}

/**
 * Signs out through the admin bar and returns where the browser ended up.
 *
 * @param {import('@playwright/test').Page} page A signed-in page.
 * @return {Promise<string>} URL after the sign-out.
 */
async function signOutAndLand(page) {
  await page.goto('/wp-admin/');
  const link = page.locator('#wp-admin-bar-logout a');
  const href = await link.getAttribute('href');
  await page.goto(href);
  await page.waitForLoadState('domcontentloaded');
  return page.url();
}

test.describe('Logout landing page', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the setting sits under the custom login function', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS);
    await openSectionFor(page, 'login');

    await expect(page.locator(FIELD)).toBeVisible();
    // Directly below the address to use, as asked for.
    const slugBox = page.locator('#blueworx_login_slug');
    const slugY = (await slugBox.boundingBox())?.y ?? 0;
    const fieldY = (await page.locator(FIELD).boundingBox())?.y ?? 0;
    expect(fieldY).toBeGreaterThan(slugY);
  });

  test('a sign-out lands on the page the site named', async ({ page }) => {
    await login(page);

    try {
      await setLandingPage(page, '/?signed-out=1');
      const landed = await signOutAndLand(page);
      expect(landed).toContain('signed-out=1');
    } finally {
      await login(page);
      await setLandingPage(page, '');
    }
  });

  test('a blank setting hands back to WordPress', async ({ page }) => {
    await login(page);
    await setLandingPage(page, '');

    const landed = await signOutAndLand(page);
    expect(landed).not.toContain('signed-out=1');
    expect(landed).toMatch(/loggedout=true/);
  });
});
