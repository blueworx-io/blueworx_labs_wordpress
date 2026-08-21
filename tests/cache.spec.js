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

  test('carries the design system, not the old form table', async ({ page }) => {
    await login(page);
    await page.goto(CACHE_PATH);

    await expect(page.locator('.bw-admin .bw-card')).toHaveCount(1);
    // The screen this replaced was a .form-table of read-only rows.
    await expect(page.locator('.bw-admin table.form-table')).toHaveCount(0);
  });
});
