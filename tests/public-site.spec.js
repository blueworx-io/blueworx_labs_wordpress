// Public-site specs are logged out by definition, so every navigation goes
// through cacheBust() — Cloudways Varnish caches logged-out responses and a
// stale hit makes a passing build look broken (see tests/helpers.js:88-105).
import { expect } from '@playwright/test';
import { test, isPlaceholder, cacheBust, login, ADMIN_USER, ADMIN_PASS } from './helpers.js';

test.describe('Public site', () => {
  test.skip(isPlaceholder || !ADMIN_USER || !ADMIN_PASS, 'No real WordPress target configured.');

  test('the public_site feature is registered and on by default', async ({ page }) => {
    await login(page);
    await page.goto(cacheBust('/wp-admin/admin.php?page=blueworx-labs-wordpress'));

    const toggle = page.locator('[data-blueworx-feature="public_site"]');
    await expect(toggle, 'the feature must appear on the Enhancements screen').toHaveCount(1);
    // Absent option means enabled (features.php:125-127) — a fresh install
    // must ship with the public site on, or activation renders nothing.
    await expect(toggle).toBeChecked();
  });

  test('the public stylesheet loads on the front page', async ({ page }) => {
    await page.goto(cacheBust('/'));
    const href = await page
      .locator('link[rel="stylesheet"][href*="public.css"]')
      .first()
      .getAttribute('href');

    expect(href, 'public.css must be enqueued').toBeTruthy();
    // Cache-busting is a house rule, not a nicety: without it a CSS change
    // silently does not reach anyone until their browser cache expires.
    expect(href, 'stylesheet must be versioned').toMatch(/[?&]ver=/);
  });
});
