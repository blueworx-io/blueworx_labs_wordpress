import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  setFeature,
} from './helpers.js';

/**
 * The SSO Logs screen.
 *
 * It exists because the reason a sign-in failed was the one thing nobody could
 * see. So the checks worth having are about what it puts on the page rather than
 * how it looks: the fixed picture of how the site is wired up, and a table that
 * either has rows or says plainly that nothing has been tried yet.
 *
 * Read-only throughout. Nothing here switches a function on or off, so a failed
 * run cannot leave the site in a state that makes the next run lie.
 */

const LOGS = '/wp-admin/admin.php?page=blueworx-sso-logs';
const SSO = '/wp-admin/admin.php?page=blueworx-sso';

test.describe('SSO Logs', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the screen opens on its own address', async ({ page }) => {
    await login(page);
    await page.goto(LOGS);

    await expect(page.getByRole('heading', { name: 'SSO Logs' })).toBeVisible();
    await expect(page.locator('[data-testid="bw-sso-environment"]')).toBeVisible();
  });

  test('it states how the site is wired up before anything else', async ({ page }) => {
    await login(page);
    await page.goto(LOGS);

    const terms = await page
      .locator('[data-testid="bw-sso-environment"] dt')
      .allInnerTexts();
    const joined = terms.join(' ');

    // Each of these has silently broken a real sign-in, which is the whole
    // reason they are on the page together rather than in three places.
    expect(joined).toContain('Return address given to the provider');
    expect(joined).toContain('Address this site calls itself');
    expect(joined).toContain('Address you reached it on');
    expect(joined).toContain('Cookies are written for');
    expect(joined).toContain('Secure connection');
  });

  test('the address the site uses and the one you reached it on are compared', async ({
    page,
  }) => {
    await login(page);
    await page.goto(LOGS);

    const panel = page.locator('[data-testid="bw-sso-environment"]');

    // One or the other, never neither: a screen that shows two addresses and
    // does not say whether they agree leaves the reader to spot it themselves,
    // which is exactly the mistake this screen exists to stop.
    const verdict = panel.locator('.bw-badge', {
      hasText: /Matches|Does not match/,
    });

    await expect(verdict.first()).toBeVisible();
  });

  test('either there are events or it says there are none', async ({ page }) => {
    await login(page);
    await page.goto(LOGS);

    const table = page.locator('[data-testid="bw-sso-log-table"]');
    const empty = page.locator('.bw-empty');

    const hasTable = await table.count();

    if (hasTable > 0) {
      const headings = await table.locator('thead th').allInnerTexts();
      const joined = headings.join(' ');

      expect(joined).toContain('When');
      expect(joined).toContain('Outcome');
      expect(joined).toContain('Sign-in cookie');
      expect(joined).toContain('Address used');

      // Clearing is only offered when there is something to clear.
      await expect(page.locator('[data-testid="bw-sso-clear-log"]')).toBeVisible();
    } else {
      await expect(empty.first()).toBeVisible();
      await expect(page.locator('[data-testid="bw-sso-clear-log"]')).toHaveCount(0);
    }
  });

  test('no cookie value is ever printed on the page', async ({ page }) => {
    await login(page);
    await page.goto(LOGS);

    // The names are useful and the values would be a working sign-in, so the
    // screen carries one and never the other.
    const body = await page.locator('body').innerText();
    const cookies = await page.context().cookies();

    for (const cookie of cookies) {
      if (cookie.value && cookie.value.length > 12) {
        expect(body).not.toContain(cookie.value);
      }
    }
  });
});

/**
 * The way in from the settings screen.
 *
 * Its own group because the settings screen only draws its fields once the
 * function is on — switched off, it is a notice and nothing else. Turned on
 * around this one check and put back afterwards, so a failed run cannot leave
 * sign-on switched on for whatever runs next.
 */
test.describe('Getting to the log from the settings screen', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  const SETTINGS = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

  /**
   * Switches single sign-on on or off from Enhancements.
   *
   * @param {import('@playwright/test').Page} page Playwright page.
   * @param {boolean} on Whether the function should end up on.
   * @return {Promise<void>} Resolves once the save has landed.
   */
  async function setSso(page, on) {
    await page.goto(SETTINGS);
    await setFeature(page, 'sso', on);
    await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
    await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
  }

  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    // browser.newPage() bypasses the page fixture and its reduced-motion
    // opt-out, without which headless Chromium stops painting after a submit.
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await login(page);
    await setSso(page, true);
    await page.close();
  });

  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage();
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await login(page);
    await setSso(page, false);
    await page.close();
  });

  test('it links there rather than repeating a few lines of it', async ({ page }) => {
    await login(page);
    await page.goto(SSO);

    const link = page.locator('[data-testid="bw-sso-open-logs"]');

    await expect(link).toBeVisible();
    await expect(link).toHaveAttribute('href', /page=blueworx-sso-logs/);
  });
});
