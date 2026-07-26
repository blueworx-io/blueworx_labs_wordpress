// `test` comes from helpers.js, not '@playwright/test': it carries the fixture
// that opts out of core's wp-admin view transitions, which otherwise freeze
// rendering in headless Chromium and hang every actionability check.
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login, restoreAll } from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

async function gotoSettings(page) {
  await login(page);
  await page.goto(SETTINGS_PATH);
}

test.describe('BlueWorx on-page translation — settings', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('Translation section exposes the toggle and its detail panel', async ({ page }) => {
    await gotoSettings(page);

    await expect(page.getByRole('heading', { name: 'Translation' })).toBeVisible();
    await expect(
      page.locator('input.blueworx-feature-toggle[data-blueworx-feature="translate"]')
    ).toBeVisible();

    const detail = page.locator('[data-blueworx-detail="translate"]');
    await expect(detail).toBeVisible();
    // Defaults: French, German and Spanish are the shipped target languages.
    await expect(detail.locator('input[name="blueworx_translate_languages[]"][value="fr"]')).toBeChecked();
    await expect(detail.locator('input[name="blueworx_translate_languages[]"][value="de"]')).toBeChecked();
    await expect(detail.locator('input[name="blueworx_translate_languages[]"][value="es"]')).toBeChecked();
    // The site's own language is never offered as a target.
    await expect(detail.locator('input[name="blueworx_translate_languages[]"][value="en"]')).toHaveCount(0);
    await expect(detail.locator('select[name="blueworx_translate_position"]')).toHaveValue('bottom-right');
    await expect(detail.locator('input[name="blueworx_translate_label"]')).toHaveValue('Language');
  });

  test('settings persist after save, and invalid values are rejected', async ({ page }) => {
    await gotoSettings(page);
    const detail = page.locator('[data-blueworx-detail="translate"]');

    await detail.locator('input[name="blueworx_translate_languages[]"][value="de"]').setChecked(false);
    await detail.locator('input[name="blueworx_translate_languages[]"][value="ja"]').setChecked(true);
    await detail.locator('select[name="blueworx_translate_position"]').selectOption('top-left');
    await detail.locator('input[name="blueworx_translate_label"]').fill('Read in');
    await detail.locator('textarea[name="blueworx_translate_exclusions"]').fill('.site-brand\n  \n.sku');
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('.notice-success').first()).toContainText('Settings saved');

    await expect(detail.locator('input[name="blueworx_translate_languages[]"][value="de"]')).not.toBeChecked();
    await expect(detail.locator('input[name="blueworx_translate_languages[]"][value="ja"]')).toBeChecked();
    await expect(detail.locator('select[name="blueworx_translate_position"]')).toHaveValue('top-left');
    await expect(detail.locator('input[name="blueworx_translate_label"]')).toHaveValue('Read in');
    // Blank lines are dropped; the two real selectors survive in order.
    await expect(detail.locator('textarea[name="blueworx_translate_exclusions"]')).toHaveValue('.site-brand\n.sku');

    await restoreAll([
      ['translation settings', async () => {
        await detail.locator('input[name="blueworx_translate_languages[]"][value="de"]').setChecked(true);
        await detail.locator('input[name="blueworx_translate_languages[]"][value="ja"]').setChecked(false);
        await detail.locator('select[name="blueworx_translate_position"]').selectOption('bottom-right');
        await detail.locator('input[name="blueworx_translate_label"]').fill('Language');
        await detail.locator('textarea[name="blueworx_translate_exclusions"]').fill('');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
      }],
    ]);
  });
});
