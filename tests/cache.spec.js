/**
 * BlueWorx > Cache.
 *
 * The screen is now built from the shared design system, so this covers the two
 * things the rebuild could plausibly have broken: the refresh still refreshes,
 * and it still says what it did afterwards. The button posts to admin-post.php
 * with a nonce — markup moved, that contract did not.
 */

import { test, expect, login } from './helpers.js';

const CACHE_PATH = '/wp-admin/admin.php?page=blueworx-cache';

test.describe('BlueWorx cache screen', () => {
  test('refreshing the cache reports what it did', async ({ page }) => {
    await login(page);
    await page.goto(CACHE_PATH);

    await expect(page.locator('.bw-pagehead__h1')).toHaveText('Cache');

    // Status is read-only information, so it reads as a description list now
    // rather than a form table pretending to be editable.
    await expect(page.locator('.bw-dl dt').first()).toHaveText(/Automatic refresh/i);

    const refresh = page.locator('form button[type="submit"].bw-btn--primary');
    await expect(refresh).toHaveText(/Refresh cache now/i);
    await refresh.click();

    // Back on the screen, the result arrives as a design system notice.
    const notice = page.locator('.bw-notice--success');
    await expect(notice).toBeVisible();
    await expect(notice).not.toBeEmpty();
  });

  test('the refresh is nonce-protected', async ({ page }) => {
    // The rebuild kept the form posting to admin-post.php rather than growing a
    // handler of its own, so the nonce has to still be in the payload.
    await login(page);
    await page.goto(CACHE_PATH);

    const form = page.locator('form').filter({ has: page.locator('input[value="blueworx_clear_cache_now"]') });
    await expect(form).toHaveCount(1);
    await expect(form.locator('input[name="_wpnonce"]')).toHaveCount(1);
    await expect(form).toHaveAttribute('method', /post/i);
  });

  test('a menu change refreshes the cache and the screen says so', async ({ page }) => {
    // The automatic refresh used to fire only when a page or post was saved,
    // so a changed menu stayed stale until the cache expired on its own. Any
    // change purges now, and the screen records the last one so somebody can
    // see it happening without reading server logs.
    await login(page);

    // Core answers this for any signed-in user; it is how the REST call below
    // is authorised without a form to scrape a nonce from.
    const nonce = (await (await page.request.get('/wp-admin/admin-ajax.php?action=rest-nonce')).text()).trim();
    const name = `bw-cache-test-${Date.now()}`;
    const created = await page.request.post('/wp-json/wp/v2/menus', {
      headers: { 'X-WP-Nonce': nonce },
      data: { name },
    });
    expect(created.ok(), await created.text()).toBe(true);
    const menuId = (await created.json()).id;

    try {
      await page.goto(CACHE_PATH);

      const row = page.locator('.bw-dl').filter({ hasText: /Last refreshed/i });
      await expect(row).toContainText(/ago/i);
      await expect(row).toContainText(/menu/i);
    } finally {
      await page.request.delete(`/wp-json/wp/v2/menus/${menuId}?force=true`, {
        headers: { 'X-WP-Nonce': nonce },
      });
    }
  });

  test('carries the design system, not the old form table', async ({ page }) => {
    await login(page);
    await page.goto(CACHE_PATH);

    await expect(page.locator('.bw-admin .bw-card')).toHaveCount(1);
    // The screen this replaced was a .form-table of read-only rows.
    await expect(page.locator('.bw-admin table.form-table')).toHaveCount(0);
  });
});
