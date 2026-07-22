import { expect } from '@playwright/test';
import { test, isPlaceholder } from './helpers.js';

test.describe('Showcase — contact-card accessibility', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('each contact card is a real, focusable link', async ({ page }) => {
    await page.goto('/contact');
    const links = page.locator('.contact-cards .cc a');
    await expect(links).toHaveCount(3);
    await expect(links.nth(0)).toHaveAttribute('href', /^tel:/);
    await expect(links.nth(1)).toHaveAttribute('href', /^https:\/\/wa\.me\//);
    await expect(links.nth(2)).toHaveAttribute('href', /^mailto:/);
  });
});

test.describe('Showcase — FAQ accordion', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('opening one FAQ closes the others in the same list', async ({ page }) => {
    await page.goto('/contact');
    const items = page.locator('.faq-list details.faq-item');
    await items.nth(0).locator('summary').click();
    await expect(items.nth(0)).toHaveAttribute('open', '');
    await items.nth(1).locator('summary').click();
    await expect(items.nth(1)).toHaveAttribute('open', '');
    await expect(items.nth(0)).not.toHaveAttribute('open', '');
  });
});

test.describe('Showcase — AI pipeline', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('renders six steps with exactly one active by default', async ({ page }) => {
    await page.goto('/ai');
    const shell = page.locator('[data-widget="ai-pipeline"]');
    await expect(shell.locator('.ai-pipe-step')).toHaveCount(6);
    await expect(shell.locator('.ai-pipe-step.on')).toHaveCount(1);
    await expect(shell.locator('.ai-pipe-step').first()).toHaveText(/Brief/);
  });
});

test.describe('Showcase — feature tabs', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('switching tabs swaps the panel heading', async ({ page }) => {
    await page.goto('/');
    const root = page.locator('[data-widget="feature-tabs"]');
    await expect(root.locator('.af-text h2')).toHaveText('Support Guides');

    await root.locator('.tab-bar .tab', { hasText: 'Toolbox' }).click();
    await expect(root.locator('.af-text h2')).toHaveText('Digital Toolbox');

    await root.locator('.tab-bar .tab', { hasText: 'Hosting' }).click();
    await expect(root.locator('.af-text h2')).toHaveText('Website Hosting');
  });
});

test.describe('Showcase — AI demo', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('renders the finished demo frame in the server HTML', async ({ page, request }) => {
    await page.goto('/ai');
    const root = page.locator('[data-widget="ai-demo"]');
    await expect(root.locator('.ai-typed')).toContainText('Build a booking website');
    // Finished-frame default (present regardless of JS animation state):
    const html = await (await request.get('/ai')).text();
    expect(html).toContain('ai-site in');
  });
});
