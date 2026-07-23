// Public marketing page — logged out — so every navigation goes through
// cacheBust(). See tests/helpers.js.
import { expect } from '@playwright/test';
import { test, isPlaceholder, cacheBust } from './helpers.js';

test.describe('Marketing contact page', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('renders the hero, contact grid, cards, FAQ and testimonials', async ({ page }) => {
    await page.goto(cacheBust('/contact/'));

    await expect(page.locator('.bw-page')).toHaveCount(1);
    await expect(page.locator('.tech-hero')).toContainText('With BlueWorx');
    await expect(page.locator('.contact-grid .contact-form')).toHaveCount(1);
    await expect(page.locator('.contact-cards .cc')).toHaveCount(3);
    // The FAQ list is native <details> so it works with no JavaScript.
    await expect(page.locator('.faq-list details.faq-item')).toHaveCount(5);
    await expect(page.locator('.tg .tc').first()).toBeVisible();
  });

  test('the form column shows a labelled placeholder when no shortcode is configured', async ({
    page,
  }) => {
    await page.goto(cacheBust('/contact/'));
    // Forms are third-party shortcodes; with none configured the column must
    // render an explicit placeholder, never a broken or empty box.
    await expect(
      page.locator('.contact-form [data-widget="contact-form"]')
    ).toBeVisible();
  });

  test('renders the nav with no active item (Contact is not a nav link)', async ({ page }) => {
    await page.goto(cacheBust('/contact/'));
    // Contact is deliberately not a top-level nav item in the source design,
    // so nothing in the nav should be marked active on this page — and the nav
    // must still render.
    await expect(page.locator('nav .nav-links a').first()).toBeVisible();
    await expect(page.locator('nav .nav-links a.active')).toHaveCount(0);
  });
});
