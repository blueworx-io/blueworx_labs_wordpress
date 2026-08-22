// `test` comes from helpers.js, not '@playwright/test': it carries the fixture
// that opts out of core's wp-admin view transitions, which otherwise freeze
// rendering in headless Chromium and hang every actionability check.
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  restoreAll,
  setFeature,
} from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const GUIDES_PATH = '/wp-admin/admin.php?page=blueworx-guides';
const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

/**
 * Installs or removes the must-use plugin that registers third-party guides
 * through the public filters. See tests/fixtures/guides-third-party.php.
 *
 * @param {'install'|'remove'} command Fixture command.
 * @return {string} Fixture stdout.
 */
function thirdPartyGuides(command) {
  const fixture = path.join(__dirname, 'fixtures', 'guides-third-party.php');
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  return execFileSync('php', [fixture, wpLoad, command], { encoding: 'utf8' }).trim();
}

/**
 * Installs or removes the must-use plugin that empties the guide list, for the
 * "nothing is switched on" state. See tests/fixtures/guides-none.php.
 *
 * @param {'install'|'remove'} command Fixture command.
 * @return {string} Fixture stdout.
 */
function noGuides(command) {
  const fixture = path.join(__dirname, 'fixtures', 'guides-none.php');
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  return execFileSync('php', [fixture, wpLoad, command], { encoding: 'utf8' }).trim();
}

async function gotoGuides(page, tab) {
  await login(page);
  await page.goto(tab ? `${GUIDES_PATH}&tab=${tab}` : GUIDES_PATH);
}

async function saveSettings(page) {
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
}

test.describe('BlueWorx Guides page', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('page renders with a tab per populated section', async ({ page }) => {
    await gotoGuides(page);

    await expect(page.getByRole('heading', { name: 'Guides', level: 1 })).toBeVisible();
    await expect(page.locator('[data-blueworx-guide-tab="getting-started"]')).toBeVisible();
    await expect(page.locator('[data-blueworx-guide-tab="security"]')).toBeVisible();

    // No tab named in the query string means the first one, Getting started.
    await expect(page.locator('[data-blueworx-guide-tab="getting-started"]')).toHaveClass(
      /is-active/
    );
    await expect(page.locator('[data-blueworx-guide="basics-pages-and-posts"]')).toBeVisible();
  });

  test('each tab shows only its own guides', async ({ page }) => {
    await gotoGuides(page, 'security');

    await expect(page.locator('[data-blueworx-guide-tab="security"]')).toHaveClass(
      /is-active/
    );
    // A feature guide from this section is present...
    await expect(page.locator('[data-blueworx-guide="feature-login"]')).toBeVisible();
    // ...and one from another section is not rendered at all.
    await expect(page.locator('[data-blueworx-guide="basics-pages-and-posts"]')).toHaveCount(0);
  });

  test('an unknown tab in the URL falls back to the first tab rather than an empty page', async ({
    page,
  }) => {
    await gotoGuides(page, 'no-such-tab');

    await expect(page.locator('[data-blueworx-guide-tab="getting-started"]')).toHaveClass(
      /is-active/
    );
    await expect(page.locator('[data-blueworx-guide="basics-pages-and-posts"]')).toBeVisible();
  });

  test('a switched-off feature has no guide', async ({ page }) => {
    // page_excerpts is a plain on/off feature in the Content section with no
    // detail controls, so toggling it cannot disturb anything else.
    let restored = true;

    try {
      await login(page);
      await page.goto(SETTINGS_PATH);

      await setFeature(page, 'page_excerpts', true);
      await saveSettings(page);

      await page.goto(`${GUIDES_PATH}&tab=content`);
      await expect(page.locator('[data-blueworx-guide="feature-page_excerpts"]')).toBeVisible();

      // Flagged before the save, not after: if the save itself is what fails,
      // the setting may still have landed, so cleanup must assume the worst.
      await page.goto(SETTINGS_PATH);
      restored = false;
      await setFeature(page, 'page_excerpts', false);
      await saveSettings(page);

      await page.goto(`${GUIDES_PATH}&tab=content`);
      await expect(page.locator('[data-blueworx-guide="feature-page_excerpts"]')).toHaveCount(0);
    } finally {
      if (!restored) {
        await restoreAll([
          [
            'page_excerpts back on',
            async () => {
              await page.goto(SETTINGS_PATH);
              await setFeature(page, 'page_excerpts', true);
              await saveSettings(page);
            },
          ],
        ]);
      }
    }
  });

  test('another plugin can add a tab and guides, and cannot inject script', async ({ page }) => {
    thirdPartyGuides('install');

    try {
      await gotoGuides(page, 'acme');

      await expect(page.locator('[data-blueworx-guide-tab="acme"]')).toBeVisible();
      await expect(page.locator('[data-blueworx-guide="acme-shipping-zones"]')).toContainText(
        'Acme guide body.'
      );

      // The safe part of the unsafe guide survives; the script does not reach
      // the page at all, so it can never have run.
      await expect(page.locator('[data-blueworx-guide="acme-unsafe"]')).toContainText(
        'Acme safe text.'
      );
      await expect(page.locator('script#acme-xss')).toHaveCount(0);
      expect(await page.evaluate(() => window.acmeXss)).toBeUndefined();

      // A guide naming a tab nobody registered is collected under Other rather
      // than silently dropped — losing another plugin's content is worse than
      // losing its grouping.
      await page.goto(`${GUIDES_PATH}&tab=other`);
      await expect(page.locator('[data-blueworx-guide="acme-homeless"]')).toContainText(
        'Acme homeless body.'
      );
    } finally {
      thirdPartyGuides('remove');
    }
  });

  test('the third-party tab is gone once the plugin is', async ({ page }) => {
    await gotoGuides(page);
    await expect(page.locator('[data-blueworx-guide-tab="acme"]')).toHaveCount(0);
    await expect(page.locator('[data-blueworx-guide-tab="other"]')).toHaveCount(0);
  });

  test('tabs still work with JavaScript off', async ({ browser }) => {
    // The tabs look like the design system's Tabs component, which switches
    // panels client-side. Ours must not: each tab is a real URL a client can be
    // sent, and the screen has to work for someone with scripting disabled.
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();

    try {
      await login(page);
      await page.goto(GUIDES_PATH);

      const security = page.locator('[data-blueworx-guide-tab="security"]');
      await expect(security).toBeVisible();

      // Real navigation, not a click handler: the href alone gets you there.
      const href = await security.getAttribute('href');
      expect(href).toContain('tab=security');

      await page.goto(href);
      await expect(page.locator('[data-blueworx-guide-tab="security"]')).toHaveClass(/is-active/);
      await expect(page.locator('[data-blueworx-guide="feature-login"]')).toBeVisible();
    } finally {
      await context.close();
    }
  });

  test('says so plainly when there is nothing to show', async ({ page }) => {
    noGuides('install');

    try {
      await gotoGuides(page);

      const empty = page.locator('.bw-empty');
      await expect(empty).toBeVisible();
      await expect(empty).toContainText('Nothing is switched on yet');

      // The way out has to be on the screen: an empty page with no next step is
      // where people give up.
      const cta = empty.getByRole('link', { name: 'Go to Enhancements' });
      await expect(cta).toBeVisible();
      await expect(cta).toHaveAttribute('href', /page=blueworx-labs-wordpress/);

      // No tab bar to click into, since there is nothing behind it.
      await expect(page.locator('.bw-tabs')).toHaveCount(0);
    } finally {
      noGuides('remove');
    }
  });
});
