// Public marketing page — logged out — so every navigation goes through
// cacheBust(). See tests/helpers.js.
import { expect } from '@playwright/test';
import { test, isPlaceholder, cacheBust } from './helpers.js';

test.describe('Marketing AI page', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('renders the Claude hero, model guidance, stack and offerings', async ({ page }) => {
    await page.goto(cacheBust('/ai/'));

    await expect(page.locator('.bw-page')).toHaveCount(1);
    await expect(page.locator('.claude-badge')).toContainText('Claude');
    await expect(page.locator('.ai-models .ai-model')).toHaveCount(4);
    await expect(page.locator('.ai-stack .ai-chip')).toHaveCount(10);
    await expect(page.locator('.ai-off-grid .fc')).toHaveCount(5);
  });

  test('the AiDemo and AiPipeline interactive widgets render', async ({ page }) => {
    await page.goto(cacheBust('/ai/'));
    await expect(page.locator('[data-widget="ai-demo"]')).toBeVisible();
    await expect(page.locator('[data-widget="ai-pipeline"]')).toBeVisible();
  });

  test('the AI Powered nav link is marked active', async ({ page }) => {
    await page.goto(cacheBust('/ai/'));
    await expect(page.locator('nav .nav-links a.active')).toContainText('AI Powered');
  });
});
