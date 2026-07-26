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
 * @param {{unavailable?: string[], failCreate?: boolean, failTranslate?: string}} options
 *   Stub behaviour. `failTranslate`, when given, makes translate() reject for
 *   that exact input string only — every other string still resolves — so
 *   tests can exercise the per-target catch in translateTargets() without
 *   breaking the rest of a pass.
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

            if (opts.failTranslate && text === opts.failTranslate) {
              throw new Error('stub refused to translate this string');
            }

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

test.describe('BlueWorx on-page translation — translating', () => {
  test.skip(isPlaceholder, 'No real staging/preview URL configured yet.');

  /**
   * Plants known content on the page so assertions do not depend on whatever
   * the site's front page happens to say. Runs after load, and the widget's
   * mutation handling is not relied on here — the language is chosen after.
   */
  async function plantFixture(page) {
    await page.evaluate(() => {
      const box = document.createElement('div');
      box.id = 'bw-fixture';
      box.innerHTML =
        '<p id="bw-text">Hello world</p>' +
        '<p class="bw-keep">BlueWorx</p>' +
        '<img id="bw-img" alt="A photo" src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" />' +
        '<input id="bw-input" placeholder="Your email" />' +
        '<code id="bw-code">const x = 1;</code>' +
        '<p id="bw-number">2026</p>';
      document.body.appendChild(box);
    });
  }

  test('translates text and attributes, and honours exclusions', async ({ page }) => {
    await installTranslatorStub(page);
    await page.goto('/');
    await plantFixture(page);

    await page.getByRole('button', { name: /Language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();

    await expect(page.locator('#bw-text')).toHaveText('[fr] Hello world');
    await expect(page.locator('#bw-img')).toHaveAttribute('alt', '[fr] A photo');
    await expect(page.locator('#bw-input')).toHaveAttribute('placeholder', '[fr] Your email');
    // <code> is always excluded, and a purely numeric string is not worth a call.
    await expect(page.locator('#bw-code')).toHaveText('const x = 1;');
    await expect(page.locator('#bw-number')).toHaveText('2026');
    // The widget must never translate its own controls.
    await expect(page.getByRole('button', { name: /Language/ })).not.toContainText('[fr]');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
  });

  test('never translates content inside an admin-excluded selector', async ({ page }) => {
    await installTranslatorStub(page);
    // The exclusion list is a site setting; inject it into the config before the
    // widget reads it rather than round-tripping through wp-admin.
    await page.addInitScript(() => {
      const apply = () => {
        if (window.blueworxTranslate) {
          window.blueworxTranslate.exclude = ['.bw-keep', ':::not a selector:::'];
          return true;
        }
        return false;
      };
      if (!apply()) {
        document.addEventListener('readystatechange', apply);
      }
    });
    await page.goto('/');
    await plantFixture(page);

    await page.getByRole('button', { name: /Language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();

    await expect(page.locator('#bw-text')).toHaveText('[fr] Hello world');
    // Excluded by the admin selector; the malformed selector alongside it must
    // be skipped rather than breaking the whole pass.
    await expect(page.locator('.bw-keep')).toHaveText('BlueWorx');
  });

  test('excludes the scope root itself, not just its descendants', async ({ page }) => {
    await installTranslatorStub(page);
    // A TreeWalker never runs acceptNode() on its own root (document.body),
    // so a naive implementation can translate the root's own attributes even
    // when the root is excluded. 'body' as the admin exclusion selector
    // targets exactly that node.
    await page.addInitScript(() => {
      const apply = () => {
        if (window.blueworxTranslate) {
          window.blueworxTranslate.exclude = ['body'];
          return true;
        }
        return false;
      };
      if (!apply()) {
        document.addEventListener('readystatechange', apply);
      }
    });
    await page.goto('/');
    await page.evaluate(() => {
      document.body.setAttribute('aria-label', 'Body region');
    });

    await page.getByRole('button', { name: /Language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();

    // html[lang] flips only once the pass has finished; wait on it before
    // asserting the body's own attribute was left alone.
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(page.locator('body')).toHaveAttribute('aria-label', 'Body region');
  });

  test('a single failed translate() call leaves that string untouched and the pass continues', async ({ page }) => {
    await installTranslatorStub(page, { failTranslate: 'Hello world' });
    await page.goto('/');
    await plantFixture(page);

    await page.getByRole('button', { name: /Language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();

    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    // The failing string is left exactly as written...
    await expect(page.locator('#bw-text')).toHaveText('Hello world');
    // ...while the rest of the pass still completes.
    await expect(page.locator('#bw-img')).toHaveAttribute('alt', '[fr] A photo');
    await expect(page.locator('#bw-input')).toHaveAttribute('placeholder', '[fr] Your email');
  });
});

test.describe('BlueWorx on-page translation — restore and persistence', () => {
  test.skip(isPlaceholder, 'No real staging/preview URL configured yet.');

  test('choosing the source language restores the original text exactly', async ({ page }) => {
    await installTranslatorStub(page);
    await page.goto('/');
    const before = await page.locator('body').innerText();

    await page.getByRole('button', { name: /Language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();
    await expect(page.locator('body')).toContainText('[fr]');

    await page.getByRole('button', { name: /Language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="en"]').click();

    await expect(page.locator('body')).not.toContainText('[fr]');
    expect(await page.locator('body').innerText()).toBe(before);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  });

  test('the chosen language is re-applied on the next page load', async ({ page }) => {
    await installTranslatorStub(page);
    await page.goto('/');

    await page.getByRole('button', { name: /Language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();
    await expect(page.locator('body')).toContainText('[fr]');

    await page.reload();

    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(page.locator('body')).toContainText('[fr]');
    await expect(page.locator('.blueworx-translate__current')).toHaveText('French');
  });

  test('a stored language that is no longer offered is discarded', async ({ page }) => {
    await installTranslatorStub(page, { unavailable: ['fr'] });
    await page.addInitScript(() => {
      window.localStorage.setItem('blueworxTranslateLang', 'fr');
    });
    await page.goto('/');

    await expect(page.locator('.blueworx-translate__current')).toHaveText('English');
    await expect(page.locator('body')).not.toContainText('[fr]');
    expect(await page.evaluate(() => window.localStorage.getItem('blueworxTranslateLang'))).toBeNull();
  });
});
