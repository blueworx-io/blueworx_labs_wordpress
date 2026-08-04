// `test` comes from helpers.js, not '@playwright/test': it carries the fixture
// that opts out of core's wp-admin view transitions, which otherwise freeze
// rendering in headless Chromium and hang every actionability check.
import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  baseURL,
  login,
} from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

// The route the whole feature is about. Fetched with `request` rather than the
// browser page, so no session cookie is carried and the call is genuinely the
// anonymous one a stranger would make.
const USERS_ROUTE = '/wp-json/wp/v2/users';

test.describe('Hide the user list from the public API', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('an anonymous caller cannot read the user list', async ({ request }) => {
    const response = await request.get(`${baseURL}${USERS_ROUTE}`);

    // 401 rather than 403: nobody was signed in, which is what core returns for
    // the same refusal and what a client library already knows how to handle.
    expect(response.status()).toBe(401);

    const body = await response.json();
    expect(body.code).toBe('blueworx_rest_users_forbidden');
  });

  test('a single user cannot be read by ID either', async ({ request }) => {
    // The list being shut is worth little if the same record is readable one at
    // a time by an ID that starts at 1 and counts up.
    const response = await request.get(`${baseURL}${USERS_ROUTE}/1`);

    expect(response.status()).toBe(401);
  });

  test('an administrator can still read the user list', async ({ page }) => {
    await login(page);

    const response = await page.request.get(`${baseURL}${USERS_ROUTE}`);

    expect(response.status()).toBe(200);
    expect(Array.isArray(await response.json())).toBe(true);
  });

  test('wp-admin still loads its own user record without a console error', async ({ page }) => {
    // /wp/v2/users/me is exempt for exactly this reason: the block editor
    // fetches it on every admin page load, and restricting it would put a 401
    // on every screen while protecting nobody.
    const failures = [];
    page.on('response', (response) => {
      if (response.url().includes('/wp/v2/users/me') && response.status() >= 400) {
        failures.push(`${response.status()} ${response.url()}`);
      }
    });

    await login(page);
    await page.goto(SETTINGS_PATH);

    expect(failures).toEqual([]);
  });

  test('the toggle is offered in the Security section and is on by default', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);

    const toggle = page.locator(
      'input.blueworx-feature-toggle[data-blueworx-feature="rest_users"]'
    );

    await expect(toggle).toBeVisible();
    await expect(toggle).toBeChecked();
  });
});
