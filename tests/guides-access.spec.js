import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  test,
  expect,
  baseURL,
  isPlaceholder,
  login,
  cacheBust,
  LOGIN_PATH,
} from './helpers.js';

/**
 * Guides, from the reader's side.
 *
 * Nobody should be reading instructions for a screen they cannot open, so the
 * page shows a section or a topic only to somebody who could act on it. The
 * rule itself is checked in tests/php/guides-access-test.php; what only a real
 * site can prove is that a real editor signing in gets the narrowed screen —
 * the capability filter runs against live roles, and a mistake there reads as a
 * page that looks perfectly normal to whoever wrote it.
 */

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const GUIDES = '/wp-admin/admin.php?page=blueworx-guides';

/**
 * Creates or deletes an account in one role. See tests/fixtures/guide-reader.php.
 *
 * @param {'create'|'delete'} command Fixture command.
 * @param {string} role Role slug.
 * @return {string} Fixture stdout — "login password" for "create".
 */
function guideReader(command, role) {
  const fixture = path.join(__dirname, 'fixtures', 'guide-reader.php');
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');

  return execFileSync('php', [fixture, wpLoad, command, role], { encoding: 'utf8' }).trim();
}

/**
 * Signs a fresh browser in as somebody who is not the administrator.
 *
 * Its own context every time: the administrator cookie in the shared one would
 * mask the whole point of the test.
 *
 * @param {import('@playwright/test').Browser} browser Playwright browser.
 * @param {string} role Role slug.
 * @return {Promise<{context: import('@playwright/test').BrowserContext, page: import('@playwright/test').Page}>} The signed-in context.
 */
async function signInAs(browser, role) {
  const [user, password] = guideReader('create', role).split(' ');

  const context = await browser.newContext();
  const page = await context.newPage();

  // newContext() bypasses the page fixture, and with it the reduced-motion
  // opt-out that keeps headless Chromium painting after a form submit. See
  // helpers.js.
  await page.emulateMedia({ reducedMotion: 'reduce' });

  await page.goto(cacheBust(`${baseURL}${LOGIN_PATH}`));
  await page.fill('#user_login', user);
  await page.fill('#user_pass', password);
  await page.click('#wp-submit');
  await page.waitForLoadState('domcontentloaded');

  return { context, page };
}

test.describe('Guides — what each role is shown', () => {
  test.skip(isPlaceholder, 'No real site configured');

  test('an administrator sees every section and every topic', async ({ page }) => {
    await login(page);
    await page.goto(GUIDES);

    await expect(page.locator('[data-blueworx-guide-product="blueworx"]')).toHaveCount(1);
    await expect(page.locator('[data-blueworx-guide-product="wordpress"]')).toHaveCount(1);

    await page.goto(`${GUIDES}&product=wordpress`);
    for (const topic of ['wp-writing', 'wp-media', 'wp-people', 'wp-upkeep']) {
      await expect(
        page.locator(`[data-blueworx-guide-tab="${topic}"]`),
        `an administrator should see ${topic}`
      ).toHaveCount(1);
    }
  });

  test('an editor gets no BlueWorx section and no topics their role cannot reach', async ({
    browser,
  }) => {
    const { context, page } = await signInAs(browser, 'editor');

    try {
      await page.goto(`${baseURL}${GUIDES}`);

      // Every BlueWorx screen sits behind manage_options, so there is nothing
      // in that section an editor could act on.
      await expect(page.locator('[data-blueworx-guide-product="blueworx"]')).toHaveCount(0);
      await expect(page.locator('[data-blueworx-guide-product="wordpress"]')).toHaveCount(1);

      // Writing and media they do. Users and updates they do not.
      await expect(page.locator('[data-blueworx-guide-tab="wp-writing"]')).toHaveCount(1);
      await expect(page.locator('[data-blueworx-guide-tab="wp-media"]')).toHaveCount(1);
      await expect(page.locator('[data-blueworx-guide-tab="wp-people"]')).toHaveCount(0);
      await expect(page.locator('[data-blueworx-guide-tab="wp-upkeep"]')).toHaveCount(0);

      // And the cards behind the hidden topics are gone too, not merely
      // unreachable — every topic is rendered into the page at once.
      await expect(page.locator('[data-blueworx-guide^="wp-people"]')).toHaveCount(0);
      await expect(page.locator('[data-blueworx-guide^="wp-writing"]')).not.toHaveCount(0);

      // The screen is still theirs to open, and still in the menu.
      await expect(page.locator('#adminmenu a[href*="page=blueworx-guides"]')).toHaveCount(1);
    } finally {
      await context.close();
      guideReader('delete', 'editor');
    }
  });

  test('a subscriber is refused the screen outright', async ({ browser }) => {
    const { context, page } = await signInAs(browser, 'subscriber');

    try {
      // Below edit_posts there is nothing left to show, so the row is never
      // registered and the address answers for itself.
      const response = await page.goto(`${baseURL}${GUIDES}`);

      expect(response.status()).toBe(403);
      await expect(page.locator('#adminmenu a[href*="page=blueworx-guides"]')).toHaveCount(0);
    } finally {
      await context.close();
      guideReader('delete', 'subscriber');
    }
  });
});
