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
    // Defaults: all five offered target languages are on.
    await expect(detail.locator('input[name="blueworx_translate_languages[]"][value="ar"]')).toBeChecked();
    await expect(detail.locator('input[name="blueworx_translate_languages[]"][value="zh"]')).toBeChecked();
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

    // All five defaults are on, so persistence is exercised by unchecking one
    // rather than toggling an unchecked box on.
    await detail.locator('input[name="blueworx_translate_languages[]"][value="ar"]').setChecked(false);
    await detail.locator('select[name="blueworx_translate_position"]').selectOption('top-left');
    await detail.locator('input[name="blueworx_translate_label"]').fill('Read in');
    await detail.locator('textarea[name="blueworx_translate_exclusions"]').fill('.site-brand\n  \n.sku');
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('.notice-success').first()).toContainText('Settings saved');

    await expect(detail.locator('input[name="blueworx_translate_languages[]"][value="ar"]')).not.toBeChecked();
    await expect(detail.locator('select[name="blueworx_translate_position"]')).toHaveValue('top-left');
    await expect(detail.locator('input[name="blueworx_translate_label"]')).toHaveValue('Read in');
    // Blank lines are dropped; the two real selectors survive in order.
    await expect(detail.locator('textarea[name="blueworx_translate_exclusions"]')).toHaveValue('.site-brand\n.sku');

    await restoreAll([
      ['translation settings', async () => {
        await detail.locator('input[name="blueworx_translate_languages[]"][value="ar"]').setChecked(true);
        await detail.locator('select[name="blueworx_translate_position"]').selectOption('bottom-right');
        await detail.locator('input[name="blueworx_translate_label"]').fill('Language');
        await detail.locator('textarea[name="blueworx_translate_exclusions"]').fill('');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
      }],
    ]);
  });
});

test.describe('BlueWorx on-page translation — frontend delivery', () => {
  test.skip(isPlaceholder, 'No real staging/preview URL configured yet.');

  test('config payload and widget root are present on the front end', async ({ page }) => {
    await page.goto('/');

    await expect(page.locator('#blueworx-translate-root')).toHaveCount(1);

    const config = await page.evaluate(() => window.blueworxTranslate);
    expect(config).toBeTruthy();
    expect(config.source).toBe('en');
    expect(config.sourceLabel).toBe('English');
    expect(config.position).toBe('bottom-right');
    expect(config.label).toBe('Language');
    expect(Array.isArray(config.exclude)).toBe(true);
    expect(config.languages.map((l) => l.code)).toEqual(['ar', 'zh', 'fr', 'de', 'es']);
    expect(config.languages.every((l) => typeof l.label === 'string' && l.label.length > 0)).toBe(true);
  });

  test('the stylesheet is enqueued on the front end', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('link[href*="translate-widget.css"]')).toHaveCount(1);
  });
});

/**
 * Installs a fake Translator API before any page script runs.
 *
 * The real API is an on-device model: it needs a supported Chrome build and a
 * multi-megabyte download, so it cannot run in CI. The stub keeps the contract
 * the widget depends on — availability(), create(), translate() — and marks its
 * output so tests can assert a translation happened without knowing any
 * language.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @param {{unavailable?: string[], failCreate?: boolean}} options Stub behaviour.
 */
async function installTranslatorStub(page, options = {}) {
  await page.addInitScript((opts) => {
    const unavailable = opts.unavailable || [];
    window.__bwTranslateCalls = 0;
    window.Translator = {
      availability: async ({ targetLanguage }) =>
        unavailable.includes(targetLanguage) ? 'unavailable' : 'available',
      create: async ({ targetLanguage }) => {
        if (opts.failCreate) {
          throw new Error('stub refused to create');
        }
        return {
          translate: async (text) => {
            window.__bwTranslateCalls += 1;
            return `[${targetLanguage}] ${text}`;
          },
        };
      },
    };
  }, options);
}

test.describe('BlueWorx on-page translation — switcher UI', () => {
  test.skip(isPlaceholder, 'No real staging/preview URL configured yet.');

  test('renders a pill in the configured corner when the API is available', async ({ page }) => {
    await installTranslatorStub(page);
    await page.goto('/');

    const widget = page.locator('.blueworx-translate');
    await expect(widget).toBeVisible();
    await expect(widget).toHaveClass(/blueworx-translate--bottom-right/);

    const toggle = page.getByRole('button', { name: /Language/ });
    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    const options = page.locator('.blueworx-translate__option');
    // Five configured targets plus the source language.
    await expect(options).toHaveCount(6);
    await expect(options.first()).toHaveText('English');
    await expect(options.first()).toHaveAttribute('aria-selected', 'true');
  });

  test('renders nothing when the Translator API is absent', async ({ page }) => {
    await page.goto('/');

    await expect(page.locator('#blueworx-translate-root')).toHaveCount(1);
    await expect(page.locator('.blueworx-translate')).toHaveCount(0);
  });

  test('drops a language the browser cannot translate', async ({ page }) => {
    await installTranslatorStub(page, { unavailable: ['de'] });
    await page.goto('/');

    await page.getByRole('button', { name: /Language/ }).click();
    const options = page.locator('.blueworx-translate__option');
    await expect(options).toHaveCount(5);
    await expect(page.locator('.blueworx-translate__option[data-lang="de"]')).toHaveCount(0);
    await expect(page.locator('.blueworx-translate__option[data-lang="fr"]')).toHaveCount(1);
  });
});
