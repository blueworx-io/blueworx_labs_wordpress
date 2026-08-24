import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  LOGIN_PATH,
} from './helpers.js';

/**
 * The design system's tokens must survive the re-skin.
 *
 * Both re-skins enqueue with the design system as a dependency, so they load
 * after it and anything they declare on :root wins. When they declared --bw-
 * names the system also declares, every component on the page quietly rendered
 * with the re-skin's values instead — the wrong green, the wrong border, and
 * body text in Sora rather than Inter.
 *
 * Nothing about the markup changes when that happens, which is why it survived
 * weeks of passing tests. Only the computed value moves, so that is what this
 * asserts.
 */

/** Token, and the value the design system defines for it. */
const SHARED_TOKENS = [
  ['--bw-success', '#00A32A'],
  ['--bw-warning', '#DBA617'],
  ['--bw-info', '#2271B1'],
  ['--bw-line', '#ECEDF3'],
];

/** The four the login screen's stylesheet also used to redeclare. */
const LOGIN_TOKENS = SHARED_TOKENS.filter(([name]) => name === '--bw-success' || name === '--bw-line');

const tokenValue = (page, name) =>
  page.evaluate(
    (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim(),
    name
  );

test.describe('the re-skin does not override the design system', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('an admin screen keeps the system values for the shared tokens', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');

    for (const [name, expected] of SHARED_TOKENS) {
      expect(
        (await tokenValue(page, name)).toUpperCase(),
        `${name} is not the design system's value`
      ).toBe(expected.toUpperCase());
    }
  });

  test('body text on an admin screen is the system body face', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');

    // Inter is the system's body face. Sora is its display face, and was what
    // the re-skin forced onto every component by redeclaring --bw-font-body.
    expect(await tokenValue(page, '--bw-font-body')).toContain('Inter');
  });

  test('the login screen keeps the system values too', async ({ page }) => {
    await page.goto(LOGIN_PATH);

    for (const [name, expected] of LOGIN_TOKENS) {
      expect(
        (await tokenValue(page, name)).toUpperCase(),
        `${name} is not the design system's value on the login screen`
      ).toBe(expected.toUpperCase());
    }

    expect(await tokenValue(page, '--bw-font-body')).toContain('Inter');
  });
});
