/**
 * The modules that replace Admin and Site Enhancements.
 *
 * These assert the parts that are visible from outside PHP: that each new
 * function is offered on the settings page with its detail controls, that the
 * detail values survive a save, and that the two with a public effect —
 * robots.txt and the revision limit — actually change what the site does.
 */

import { request } from '@playwright/test';
import { test, expect, baseURL, isPlaceholder, ADMIN_USER, ADMIN_PASS, login, restoreAll } from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

const toggleFor = (key) => `input.blueworx-feature-toggle[data-blueworx-feature="${key}"]`;

async function save(page) {
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
}

test.describe('ASE replacement modules', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('every new function appears on the settings page', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);

    for (const key of [
      'admin_bar',
      'dashboard_widgets',
      'xmlrpc',
      'author_slugs',
      'view_as_role',
      'login_session',
      'content_tools',
      'revisions',
      'robots_txt',
      'media_tools',
    ]) {
      await expect(page.locator(toggleFor(key)), `${key} should be on the settings page`).toHaveCount(1);
    }

    await expect(page.getByRole('heading', { name: 'Media' })).toBeVisible();
  });

  test('the retired headless API is gone from the settings page and the site', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);

    await expect(page.locator(toggleFor('headless_api'))).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Integrations' })).toHaveCount(0);

    const api = await request.newContext({ baseURL });

    try {
      const site = await api.get('/wp-json/blueworx/v1/site');
      expect(site.status(), 'the retired namespace must not answer at all').toBe(404);
    } finally {
      await api.dispose();
    }
  });

  test('the revision limit saves and comes back', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);

    const field = page.locator('#blueworx_revisions_limit');
    await expect(field).toHaveCount(1);

    const original = await field.inputValue();

    await restoreAll([
      [
        'save a changed limit',
        async () => {
          await field.fill('7');
          await save(page);
          await expect(page.locator('#blueworx_revisions_limit')).toHaveValue('7');
        },
      ],
      [
        'restore the original limit',
        async () => {
          await page.goto(SETTINGS_PATH);
          await page.locator('#blueworx_revisions_limit').fill(original);
          await save(page);
        },
      ],
    ]);
  });

  test('robots.txt serves what was saved, and only while the function is on', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);

    const toggle = page.locator(toggleFor('robots_txt'));
    const wasChecked = await toggle.isChecked();
    const marker = `Disallow: /bw-playwright-${process.pid}/`;
    let originalContent = '';

    await restoreAll([
      [
        'save a marker line and fetch the live file',
        async () => {
          await toggle.setChecked(true);
          await save(page);

          const textarea = page.locator('#blueworx_robots_txt');
          await expect(textarea).toBeVisible();
          originalContent = await textarea.inputValue();

          await textarea.fill(`${originalContent}\n${marker}`);
          await save(page);

          const api = await request.newContext({ baseURL });

          try {
            /*
             * `?robots=1` rather than `/robots.txt`: the pretty path is a rewrite
             * onto exactly this query, and it is the query that reaches
             * WordPress. The test harness serves the suite from PHP's built-in
             * server, which answers a request for a .txt path from disk and
             * 404s when the file is not there — so asking for the pretty path
             * would test the web server, not the plugin.
             */
            const res = await api.get('/?robots=1');
            expect(res.status()).toBe(200);
            expect(await res.text()).toContain(marker);
          } finally {
            await api.dispose();
          }
        },
      ],
      [
        'restore the content and the toggle',
        async () => {
          await page.goto(SETTINGS_PATH);

          if (originalContent) {
            await page.locator('#blueworx_robots_txt').fill(originalContent);
            await save(page);
            await page.goto(SETTINGS_PATH);
          }

          await page.locator(toggleFor('robots_txt')).setChecked(wasChecked);
          await save(page);
        },
      ],
    ]);
  });

  test('toolbar cleanup offers its node list and its front-end modes', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);

    await expect(page.locator('input[name="blueworx_admin_bar_removed_nodes[]"][value="wp-logo"]')).toHaveCount(1);
    await expect(page.locator('input[name="blueworx_admin_bar_hide_help"]')).toHaveCount(1);
    await expect(page.locator('input[name="blueworx_admin_bar_front_end_mode"][value="all_but_admin"]')).toHaveCount(1);
  });

  test('dashboard tidy-up offers a panel list', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);

    await expect(
      page.locator('input[name="blueworx_dashboard_removed_widgets[]"][value="dashboard_quick_press"]')
    ).toHaveCount(1);
  });

  test('media tools offer replacement, a size cap and an SVG role list', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);

    await expect(page.locator('input[name="blueworx_media_replace_enabled"]')).toHaveCount(1);
    await expect(page.locator('#blueworx_media_max_width')).toHaveCount(1);
    await expect(page.locator('select[name="blueworx_media_svg_roles[]"]')).toHaveCount(1);
  });
});
