import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

/**
 * Every guide is a task: where to go, numbered steps, what happens next.
 *
 * The PHP test checks the registry says so; this checks the page shows it,
 * with the styles loaded and the cards laid out.
 */

const GUIDES = '/wp-admin/admin.php?page=blueworx-guides';
const LATEPOINT_PATH = '/wp-admin/admin.php?page=latepoint';

/**
 * Whether LatePoint is actually reachable on this harness right now.
 *
 * Same signal as tests/latepoint-layout.spec.js: a real navigation to its
 * screen, not an env var, so the two LatePoint tests below run or skip
 * according to what the harness is actually running rather than what a
 * caller remembered to set.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @return {Promise<boolean>} True when LatePoint's own screen answered.
 */
async function latePointIsInstalled(page) {
  const response = await page.goto(LATEPOINT_PATH);
  return Boolean(
    response &&
      response.status() < 400 &&
      (await page.locator('body.latepoint-admin').count())
  );
}

test.describe('Guides — task format', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('every visible card has a Where line, numbered steps and a closing line', async ({ page }) => {
    await login(page);

    for (const product of ['blueworx', 'wordpress']) {
      await page.goto(`${GUIDES}&product=${product}`);

      const tabs = await page.locator('[data-blueworx-guide-tabs] .bw-tab').all();
      expect(tabs.length).toBeGreaterThan(0);

      for (const tab of tabs) {
        await tab.click();
        const cards = page.locator('.bw-guidegrid:not([hidden]) [data-blueworx-guide]');
        const count = await cards.count();
        expect(count).toBeGreaterThan(0);

        for (let i = 0; i < count; i++) {
          const card = cards.nth(i);
          await expect(card.locator('.bw-guide__where')).toHaveText(/^Where:/);
          await expect(card.locator('ol.bw-guide__steps li').first()).toBeVisible();
          await expect(card.locator('.bw-guide__then')).toBeVisible();
        }
      }
    }
  });

  test('the technical features are no longer on the page', async ({ page }) => {
    await login(page);
    await page.goto(`${GUIDES}&product=blueworx&tab=security`);

    for (const key of ['xmlrpc', 'rest_users', 'author_slugs', 'application_passwords']) {
      await expect(page.locator(`[data-blueworx-guide="feature-${key}"]`)).toHaveCount(0);
    }

    // And a client-facing one is, under the id it always had.
    await expect(page.locator('[data-blueworx-guide="feature-login"]')).toBeVisible();
    await expect(page.locator('[data-blueworx-guide="feature-login-changing"]')).toBeVisible();
  });

  test('WordPress has a Blog posts topic with its eight guides', async ({ page }) => {
    await login(page);
    await page.goto(`${GUIDES}&product=wordpress&tab=wp-posts`);

    await expect(page.locator('[data-blueworx-guide-tab="wp-posts"]')).toHaveClass(/is-active/);
    await expect(page.locator('.bw-guidegrid:not([hidden]) [data-blueworx-guide^="wp-posts-"]')).toHaveCount(8);

    // The action on a Blog posts card opens the Posts list. blueworx_ds_button()
    // (includes/admin-design.php) emits class "bw-btn", not "bw-button".
    const href = await page
      .locator('.bw-guidegrid:not([hidden]) [data-blueworx-guide="wp-posts-writing"] a.bw-btn')
      .first()
      .getAttribute('href');
    expect(href).toContain('edit.php');
    expect(href).not.toContain('post_type=page');
  });

  test('LatePoint is not offered when it is not installed', async ({ page }) => {
    await login(page);
    const installed = await latePointIsInstalled(page);
    test.skip(installed, 'LatePoint is installed and active on this harness');

    await page.goto(GUIDES);
    const labels = await page.locator('[data-blueworx-guide-products] .bw-prodtab').allInnerTexts();
    expect(labels.join(' ')).not.toMatch(/LatePoint|Bookings/);
  });

  test('LatePoint is offered when it is installed', async ({ page }) => {
    await login(page);
    const installed = await latePointIsInstalled(page);
    test.skip(!installed, 'LatePoint is not installed here — run node scripts/install-test-latepoint.mjs against the harness.');

    await page.goto(`${GUIDES}&product=latepoint`);
    await expect(page.locator('[data-blueworx-guide-product="latepoint"]')).toHaveClass(/is-active/);
    await expect(page.locator('.bw-guidegrid:not([hidden]) [data-blueworx-guide^="lp-"]').first()).toBeVisible();
  });
});
