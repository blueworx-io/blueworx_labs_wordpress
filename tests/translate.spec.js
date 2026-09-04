// `test` comes from helpers.js, not '@playwright/test': it carries the fixture
// that opts out of core's wp-admin view transitions, which otherwise freeze
// rendering in headless Chromium and hang every actionability check.
import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  baseURL,
  login,
  restoreAll,
  openSectionFor,
} from './helpers.js';

// The section nav opens whichever section the URL names, so every navigation
// in this spec lands with the Translation panel already open.
const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress&section=translation';

async function gotoSettings(page) {
  await login(page);
  await page.goto(SETTINGS_PATH);
  // Translation is behind the section nav now; everything this spec touches
  // lives in that panel.
  await openSectionFor(page, 'translate');
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
    await expect(detail.locator('select[name="blueworx_translate_display"]')).toHaveValue('text');
    // Everyone by default: an install already using the switcher keeps showing it.
    await expect(detail.locator('input[name="blueworx_translate_audience"][value="everyone"]')).toBeChecked();
  });

  test('the display style offers exactly the three documented options', async ({ page }) => {
    await gotoSettings(page);
    const display = page.locator('[data-blueworx-detail="translate"] select[name="blueworx_translate_display"]');

    await expect(display.locator('option')).toHaveCount(3);
    expect(await display.locator('option').evaluateAll((options) => options.map((o) => o.value))).toEqual([
      'text',
      'text-flags',
      'flags',
    ]);
  });

  test('the display style persists after save', async ({ page }) => {
    await gotoSettings(page);
    const detail = page.locator('[data-blueworx-detail="translate"]');

    await detail.locator('select[name="blueworx_translate_display"]').selectOption('flags');
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
    await expect(detail.locator('select[name="blueworx_translate_display"]')).toHaveValue('flags');

    await restoreAll([
      ['switcher display style', async () => {
        await detail.locator('select[name="blueworx_translate_display"]').selectOption('text');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
      }],
    ]);
  });

  test('settings persist after save, and invalid values are rejected', async ({ page }) => {
    await gotoSettings(page);
    const detail = page.locator('[data-blueworx-detail="translate"]');

    // All five defaults are on, so persistence is exercised by unchecking one
    // rather than toggling an unchecked box on.
    await detail.locator('input[name="blueworx_translate_languages[]"][value="ar"]').setChecked(false);
    await detail.locator('select[name="blueworx_translate_position"]').selectOption('top-left');
    await detail.locator('textarea[name="blueworx_translate_exclusions"]').fill('.site-brand\n  \n.sku');
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');

    await expect(detail.locator('input[name="blueworx_translate_languages[]"][value="ar"]')).not.toBeChecked();
    await expect(detail.locator('select[name="blueworx_translate_position"]')).toHaveValue('top-left');
    // Blank lines are dropped; the two real selectors survive in order.
    await expect(detail.locator('textarea[name="blueworx_translate_exclusions"]')).toHaveValue('.site-brand\n.sku');

    await restoreAll([
      ['translation settings', async () => {
        await detail.locator('input[name="blueworx_translate_languages[]"][value="ar"]').setChecked(true);
        await detail.locator('select[name="blueworx_translate_position"]').selectOption('bottom-right');
        await detail.locator('textarea[name="blueworx_translate_exclusions"]').fill('');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
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
    // The switcher carries no wording of its own; these two are the only strings
    // it is given, and neither is a site setting.
    expect(config.toggleLabel).toBe('Choose language');
    expect(config.busyLabel).toBe('Translating…');
    expect(config.label).toBeUndefined();
    expect(Array.isArray(config.exclude)).toBe(true);
    expect(config.languages.map((l) => l.code)).toEqual(['ar', 'zh', 'fr', 'de', 'es']);
    expect(config.languages.every((l) => typeof l.label === 'string' && l.label.length > 0)).toBe(true);
    // Every offered language, and the source language, carries a flag; without
    // one the flags-only style and the narrow-screen fallback show nothing.
    expect(config.languages.every((l) => typeof l.flag === 'string' && l.flag.length > 0)).toBe(true);
    expect(config.sourceFlag).toBe('🇬🇧');
    expect(config.display).toBe('text');
    expect(config.errorLabel).toBe("Couldn't load that language.");
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
 * @param {{unavailable?: string[], failCreate?: boolean, failTranslate?: string, delay?: number}} options
 *   Stub behaviour. `failTranslate`, when given, makes translate() reject for
 *   that exact input string only — every other string still resolves — so
 *   tests can exercise the per-target catch in translateTargets() without
 *   breaking the rest of a pass. `delay`, when given, makes every translate()
 *   call wait that many milliseconds before resolving (or rejecting), so a
 *   test can reliably catch a pass while it is still in flight.
 */
async function installTranslatorStub(page, options = {}) {
  await page.addInitScript((opts) => {
    const unavailable = opts.unavailable || [];
    const delay = opts.delay || 0;
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

            if (delay > 0) {
              await new Promise((resolve) => setTimeout(resolve, delay));
            }

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

    const toggle = page.getByRole('button', { name: /Choose language/ });
    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    const options = page.locator('.blueworx-translate__option');
    // Five configured targets plus the source language.
    await expect(options).toHaveCount(6);
    await expect(options.first().locator('.blueworx-translate__name')).toHaveText('English');
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

    await page.getByRole('button', { name: /Choose language/ }).click();
    const options = page.locator('.blueworx-translate__option');
    await expect(options).toHaveCount(5);
    await expect(page.locator('.blueworx-translate__option[data-lang="de"]')).toHaveCount(0);
    await expect(page.locator('.blueworx-translate__option[data-lang="fr"]')).toHaveCount(1);
  });
});

test.describe('BlueWorx on-page translation — who sees the switcher', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  const AUDIENCE = 'input[name="blueworx_translate_audience"]';
  const ROLES = 'input[name="blueworx_translate_roles[]"]';

  /**
   * Puts the audience back to everyone, which is the shipped default.
   *
   * @param {import('@playwright/test').Page} page Playwright page.
   */
  async function restoreEveryone(page) {
    await restoreAll([
      ['translation audience', async () => {
        await page.goto(SETTINGS_PATH);
        await openSectionFor(page, 'translate');
        const detail = page.locator('[data-blueworx-detail="translate"]');
        await detail.locator(AUDIENCE + '[value="everyone"]').check();
        await detail.locator(ROLES + '[value="editor"]').setChecked(false);
        await detail.locator(ROLES + '[value="administrator"]').setChecked(true);
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
      }],
    ]);
  }

  test('the panel offers both audiences and a tick box for every role', async ({ page }) => {
    await gotoSettings(page);
    const detail = page.locator('[data-blueworx-detail="translate"]');

    await expect(detail.locator(AUDIENCE + '[value="everyone"]')).toBeChecked();
    await expect(detail.locator(AUDIENCE + '[value="roles"]')).not.toBeChecked();
    // Core's roles at a minimum; a site with extra roles gets those too.
    await expect(detail.locator(ROLES + '[value="administrator"]')).toBeVisible();
    await expect(detail.locator(ROLES + '[value="editor"]')).toBeVisible();
    await expect(detail.locator(ROLES + '[value="subscriber"]')).toBeVisible();
    // Administrators are the fallback until roles are picked.
    await expect(detail.locator(ROLES + '[value="administrator"]')).toBeChecked();
  });

  test('more than one role can be ticked, and the choice survives a save', async ({ page }) => {
    await gotoSettings(page);
    const detail = page.locator('[data-blueworx-detail="translate"]');

    await detail.locator(AUDIENCE + '[value="roles"]').check();
    await detail.locator(ROLES + '[value="administrator"]').setChecked(true);
    await detail.locator(ROLES + '[value="editor"]').setChecked(true);
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');

    try {
      await expect(detail.locator(AUDIENCE + '[value="roles"]')).toBeChecked();
      await expect(detail.locator(ROLES + '[value="administrator"]')).toBeChecked();
      await expect(detail.locator(ROLES + '[value="editor"]')).toBeChecked();
      await expect(detail.locator(ROLES + '[value="subscriber"]')).not.toBeChecked();
    } finally {
      await restoreEveryone(page);
    }
  });

  test('restricted to roles, the switcher is sent to a matching user and to nobody else', async ({ page, browser }) => {
    await gotoSettings(page);
    const detail = page.locator('[data-blueworx-detail="translate"]');

    await detail.locator(AUDIENCE + '[value="roles"]').check();
    await detail.locator(ROLES + '[value="administrator"]').setChecked(true);
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
    await expect(detail.locator(AUDIENCE + '[value="roles"]')).toBeChecked();

    try {
      // The signed-in administrator holds a ticked role, so they get the whole
      // feature.
      await page.goto('/');
      await expect(page.locator('#blueworx-translate-root')).toHaveCount(1);
      expect(await page.evaluate(() => window.blueworxTranslate)).toBeTruthy();

      // A separate context is a genuinely logged-out visitor — not the same
      // session with a cookie deleted, which would still hit whatever the
      // logged-in page left cached.
      const anonContext = await browser.newContext({ baseURL });
      const anon = await anonContext.newPage();

      try {
        await anon.goto('/');
        // Nothing is merely hidden: the root, the config and the assets are
        // all absent from the response.
        await expect(anon.locator('#blueworx-translate-root')).toHaveCount(0);
        await expect(anon.locator('.blueworx-translate')).toHaveCount(0);
        expect(await anon.evaluate(() => window.blueworxTranslate)).toBeUndefined();
        await expect(anon.locator('script[src*="translate-widget.js"]')).toHaveCount(0);
        await expect(anon.locator('link[href*="translate-widget.css"]')).toHaveCount(0);
      } finally {
        await anonContext.close();
      }
    } finally {
      await restoreEveryone(page);
    }
  });

  test('restricted to a role the viewer does not hold, an administrator gets nothing either', async ({ page }) => {
    await gotoSettings(page);
    const detail = page.locator('[data-blueworx-detail="translate"]');

    await detail.locator(AUDIENCE + '[value="roles"]').check();
    await detail.locator(ROLES + '[value="administrator"]').setChecked(false);
    await detail.locator(ROLES + '[value="editor"]').setChecked(true);
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');

    try {
      await page.goto('/');
      await expect(page.locator('#blueworx-translate-root')).toHaveCount(0);
      expect(await page.evaluate(() => window.blueworxTranslate)).toBeUndefined();
    } finally {
      await restoreEveryone(page);
    }
  });

  test('set to everyone, a logged-out visitor gets the switcher', async ({ browser }) => {
    // The default state, asserted from a clean context so the restores above
    // are proven to have actually restored something.
    const anonContext = await browser.newContext({ baseURL });
    const anon = await anonContext.newPage();

    try {
      await anon.goto('/');
      await expect(anon.locator('#blueworx-translate-root')).toHaveCount(1);
      expect(await anon.evaluate(() => window.blueworxTranslate)).toBeTruthy();
    } finally {
      await anonContext.close();
    }
  });
});

test.describe('BlueWorx on-page translation — display style', () => {
  test.skip(isPlaceholder, 'No real staging/preview URL configured yet.');

  /**
   * Forces a display style into the config before the widget reads it.
   *
   * The style is a site setting, but which of three CSS classes it produces is
   * the whole of its frontend behaviour — injecting it here exercises that
   * without three round trips through wp-admin.
   *
   * @param {import('@playwright/test').Page} page  Playwright page.
   * @param {string}                          style One of text, text-flags, flags.
   */
  async function useDisplay(page, style) {
    await page.addInitScript((chosen) => {
      const apply = () => {
        if (window.blueworxTranslate) {
          window.blueworxTranslate.display = chosen;
          return true;
        }
        return false;
      };
      if (!apply()) {
        document.addEventListener('readystatechange', apply);
      }
    }, style);
  }

  /**
   * Measures a widget element's painted width.
   *
   * The visually-hidden treatment clips an element to 1px rather than removing
   * it — deliberately, so it keeps its place in the accessibility tree — and a
   * 1px box still counts as "visible" to Playwright. Width is what actually
   * distinguishes shown from hidden here.
   *
   * @param {import('@playwright/test').Page} page     Playwright page.
   * @param {string}                          selector Element to measure.
   * @return {Promise<number>} Painted width in pixels.
   */
  function widthOf(page, selector) {
    return page.locator(selector).evaluate((element) => element.getBoundingClientRect().width);
  }

  const PILL_NAME = '.blueworx-translate__toggle .blueworx-translate__name';
  const PILL_FLAG = '.blueworx-translate__toggle .blueworx-translate__flag';
  const PILL_LABEL = '.blueworx-translate__toggle .blueworx-translate__label';

  test('text only shows the language name and no flag', async ({ page }) => {
    await installTranslatorStub(page);
    await useDisplay(page, 'text');
    await page.goto('/');

    await expect(page.locator('.blueworx-translate')).toHaveClass(/blueworx-translate--display-text\b/);
    await expect(page.locator(PILL_FLAG)).toBeHidden();
    expect(await widthOf(page, PILL_NAME)).toBeGreaterThan(1);
    // The pill carries no wording of its own in any style — the name it shows
    // is the current language, never a caption.
    expect(await widthOf(page, PILL_LABEL)).toBeLessThanOrEqual(1);
    await expect(page.locator('.blueworx-translate__toggle')).toHaveAccessibleName(/Choose language/);
  });

  test('text and flags shows both', async ({ page }) => {
    await installTranslatorStub(page);
    await useDisplay(page, 'text-flags');
    await page.goto('/');

    await expect(page.locator(PILL_FLAG)).toBeVisible();
    await expect(page.locator(PILL_FLAG)).toHaveText('🇬🇧');
    expect(await widthOf(page, PILL_NAME)).toBeGreaterThan(1);
  });

  test('flags only shows the flag and keeps the name for screen readers', async ({ page }) => {
    await installTranslatorStub(page);
    await useDisplay(page, 'flags');
    await page.goto('/');

    await expect(page.locator(PILL_FLAG)).toBeVisible();
    // Nothing on the pill but the flag...
    expect(await widthOf(page, PILL_NAME)).toBeLessThanOrEqual(1);
    // ...and yet the button still says what it is and what it is set to.
    await expect(page.locator('.blueworx-translate__toggle')).toHaveAccessibleName(/Choose language/);
    await expect(page.locator('.blueworx-translate__toggle')).toHaveAccessibleName(/English/);

    await page.locator('.blueworx-translate__toggle').click();
    await expect(page.locator('.blueworx-translate__option[data-lang="fr"]')).toHaveAccessibleName(/French/);
  });

  test('a phone-width screen falls back to flags on the pill, whatever the site chose', async ({ page }) => {
    await installTranslatorStub(page);
    await useDisplay(page, 'text');
    await page.setViewportSize({ width: 375, height: 720 });
    await page.goto('/');

    // Text-only is the site setting, and on a wide screen the flag would be
    // hidden; at phone width it is the only thing on the pill.
    await expect(page.locator(PILL_FLAG)).toBeVisible();
    expect(await widthOf(page, PILL_NAME)).toBeLessThanOrEqual(1);

    // The open list still reads in words — the space saving is the pill's, and
    // a flag-only menu would make choosing a language a guessing game.
    await page.locator('.blueworx-translate__toggle').click();
    const optionName = '.blueworx-translate__option[data-lang="fr"] .blueworx-translate__name';
    await expect(page.locator(optionName)).toHaveText('French');
    expect(await widthOf(page, optionName)).toBeGreaterThan(1);
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

    await page.getByRole('button', { name: /Choose language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();

    await expect(page.locator('#bw-text')).toHaveText('[fr] Hello world');
    await expect(page.locator('#bw-img')).toHaveAttribute('alt', '[fr] A photo');
    await expect(page.locator('#bw-input')).toHaveAttribute('placeholder', '[fr] Your email');
    // <code> is always excluded, and a purely numeric string is not worth a call.
    await expect(page.locator('#bw-code')).toHaveText('const x = 1;');
    await expect(page.locator('#bw-number')).toHaveText('2026');
    // The widget must never translate its own controls.
    await expect(page.getByRole('button', { name: /Choose language/ })).not.toContainText('[fr]');
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

    await page.getByRole('button', { name: /Choose language/ }).click();
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

    await page.getByRole('button', { name: /Choose language/ }).click();
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

    await page.getByRole('button', { name: /Choose language/ }).click();
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

    await page.getByRole('button', { name: /Choose language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();
    await expect(page.locator('body')).toContainText('[fr]');

    await page.getByRole('button', { name: /Choose language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="en"]').click();

    await expect(page.locator('body')).not.toContainText('[fr]');
    expect(await page.locator('body').innerText()).toBe(before);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  });

  test('the chosen language is re-applied on the next page load', async ({ page }) => {
    await installTranslatorStub(page);
    await page.goto('/');

    await page.getByRole('button', { name: /Choose language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();
    await expect(page.locator('body')).toContainText('[fr]');

    await page.reload();

    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(page.locator('body')).toContainText('[fr]');
    await expect(page.locator('.blueworx-translate__current .blueworx-translate__name')).toHaveText('French');
  });

  test('a stored language that is no longer offered is discarded', async ({ page }) => {
    await installTranslatorStub(page, { unavailable: ['fr'] });
    await page.addInitScript(() => {
      window.localStorage.setItem('blueworxTranslateLang', 'fr');
    });
    await page.goto('/');

    await expect(page.locator('.blueworx-translate__current .blueworx-translate__name')).toHaveText('English');
    await expect(page.locator('body')).not.toContainText('[fr]');
    expect(await page.evaluate(() => window.localStorage.getItem('blueworxTranslateLang'))).toBeNull();
  });

  /**
   * Plants known content on the page so assertions do not depend on whatever
   * the site's front page happens to say. A local copy of the fixture used by
   * the "translating" describe block above, since that one is not exported.
   */
  async function plantFixture(page) {
    await page.evaluate(() => {
      const box = document.createElement('div');
      box.id = 'bw-fixture';
      box.innerHTML =
        '<p id="bw-text">Hello world</p>' +
        '<img id="bw-img" alt="A photo" src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" />' +
        '<input id="bw-input" placeholder="Your email" />';
      document.body.appendChild(box);
    });
  }

  test('switching directly from one target language to another leaves no trace of the first', async ({ page }) => {
    await installTranslatorStub(page);
    await page.goto('/');
    await plantFixture(page);

    await page.getByRole('button', { name: /Choose language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(page.locator('#bw-text')).toHaveText('[fr] Hello world');
    await expect(page.locator('#bw-img')).toHaveAttribute('alt', '[fr] A photo');
    await expect(page.locator('#bw-input')).toHaveAttribute('placeholder', '[fr] Your email');

    // Straight to German, without going via English first.
    await page.getByRole('button', { name: /Choose language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="de"]').click();

    await expect(page.locator('html')).toHaveAttribute('lang', 'de');
    await expect(page.locator('body')).not.toContainText('[fr]');
    await expect(page.locator('#bw-text')).toHaveText('[de] Hello world');
    await expect(page.locator('#bw-img')).toHaveAttribute('alt', '[de] A photo');
    await expect(page.locator('#bw-input')).toHaveAttribute('placeholder', '[de] Your email');
  });
});

test.describe('BlueWorx on-page translation — dynamic content', () => {
  test.skip(isPlaceholder, 'No real staging/preview URL configured yet.');

  test('content added after translating is translated too', async ({ page }) => {
    await installTranslatorStub(page);
    await page.goto('/');

    await page.getByRole('button', { name: /Choose language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');

    await page.evaluate(() => {
      const late = document.createElement('p');
      late.id = 'bw-late';
      late.textContent = 'Loaded later';
      document.body.appendChild(late);
    });

    await expect(page.locator('#bw-late')).toHaveText('[fr] Loaded later');
  });

  test('the observer does not re-translate its own output', async ({ page }) => {
    await installTranslatorStub(page);
    await page.goto('/');

    await page.getByRole('button', { name: /Choose language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');

    const callsAfterFirstPass = await page.evaluate(() => window.__bwTranslateCalls);
    // Give the debounced observer more than one window to misbehave in.
    await page.waitForTimeout(1000);

    expect(await page.evaluate(() => window.__bwTranslateCalls)).toBe(callsAfterFirstPass);
    await expect(page.locator('body')).not.toContainText('[fr] [fr]');
  });

  test('switching language while a background pass is still in flight leaves no stale translation', async ({ page }) => {
    // A slow stub is required to reliably catch the debounced background pass
    // (translatePending()) mid-flight: fast enough calls would always finish
    // before the test could react.
    await installTranslatorStub(page, { delay: 300 });
    await page.goto('/');

    const toggle = page.getByRole('button', { name: /Choose language/ });
    await toggle.click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr', { timeout: 20000 });

    const callsBeforeLate = await page.evaluate(() => window.__bwTranslateCalls);

    await page.evaluate(() => {
      const late = document.createElement('p');
      late.id = 'bw-race';
      late.textContent = 'Loaded later during a pass';
      document.body.appendChild(late);
    });

    // Wait until the debounced background pass has actually started
    // translating the new node. translate() increments the counter
    // synchronously, before its artificial delay resolves, so this proves a
    // worker is now genuinely in flight rather than still just debouncing.
    await page.waitForFunction(
      (before) => window.__bwTranslateCalls > before,
      callsBeforeLate,
      { timeout: 5000 }
    );

    // Switch back to the source language while that worker's translate()
    // call is still pending.
    await toggle.click();
    await page.locator('.blueworx-translate__option[data-lang="en"]').click();

    // Give the in-flight worker's delayed translate() call time to resolve
    // and, pre-fix, write its stale result.
    await page.waitForTimeout(600);

    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    // The late node must never end up with a stale translation: the pass that
    // was already resolving when the switch happened must not be allowed to
    // write over a node that switching back to the source language just
    // restored.
    await expect(page.locator('#bw-race')).toHaveText('Loaded later during a pass');
  });

  test('nothing is translated after returning to the source language', async ({ page }) => {
    await installTranslatorStub(page);
    await page.goto('/');

    await page.getByRole('button', { name: /Choose language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');

    await page.getByRole('button', { name: /Choose language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="en"]').click();

    await page.evaluate(() => {
      const late = document.createElement('p');
      late.id = 'bw-late-2';
      late.textContent = 'Loaded later';
      document.body.appendChild(late);
    });
    await page.waitForTimeout(600);

    await expect(page.locator('#bw-late-2')).toHaveText('Loaded later');
  });
});

test.describe('BlueWorx on-page translation — keyboard and failures', () => {
  test.skip(isPlaceholder, 'No real staging/preview URL configured yet.');

  test('the switcher is fully operable from the keyboard', async ({ page }) => {
    await installTranslatorStub(page);
    await page.goto('/');

    const toggle = page.getByRole('button', { name: /Choose language/ });
    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    // Opening focuses the selected option (English). The configured target
    // order is Arabic, Chinese, French, German, Spanish, so it takes three
    // ArrowDown presses from English to reach French.
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('ArrowDown');
    await expect(page.locator('.blueworx-translate__option[data-lang="fr"]')).toBeFocused();

    await page.keyboard.press('Enter');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    await page.keyboard.press('Enter');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.press('Escape');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(toggle).toBeFocused();
  });

  test('a failed language load leaves the page and the widget usable', async ({ page }) => {
    await installTranslatorStub(page, { failCreate: true });
    await page.goto('/');
    const before = await page.locator('body').innerText();

    const toggle = page.getByRole('button', { name: /Choose language/ });
    await toggle.click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();

    await expect(page.locator('.blueworx-translate__status')).toContainText("Couldn't load");
    await expect(toggle).toHaveAttribute('aria-busy', 'false');
    await expect(toggle).toBeEnabled();
    await expect(page.locator('.blueworx-translate__current .blueworx-translate__name')).toHaveText('English');
    expect(await page.locator('body').innerText()).toContain(before.slice(0, 40));
    await expect(page.locator('html')).not.toHaveAttribute('lang', 'fr');
  });

  test('a failed switch away from an already-translated page restores the pill and html lang to the source', async ({ page }) => {
    // A custom stub, not installTranslatorStub(): the first create() must
    // succeed (so a restore-before-translate guard actually has something to
    // restore) and only the second must fail.
    await page.addInitScript(() => {
      let createCount = 0;
      window.Translator = {
        availability: async () => 'available',
        create: async ({ targetLanguage }) => {
          createCount += 1;

          if (createCount > 1) {
            throw new Error('stub refused second create');
          }

          return {
            translate: async (text) => `[${targetLanguage}] ${text}`,
          };
        },
      };
    });
    await page.goto('/');

    const toggle = page.getByRole('button', { name: /Choose language/ });
    await toggle.click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(page.locator('.blueworx-translate__current .blueworx-translate__name')).toHaveText('French');

    await toggle.click();
    await page.locator('.blueworx-translate__option[data-lang="de"]').click();

    await expect(page.locator('.blueworx-translate__status')).toContainText("Couldn't load");
    // The restore already put the text back in English; the pill and
    // html[lang] must say so too, not still claim French.
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('.blueworx-translate__current .blueworx-translate__name')).toHaveText('English');
    // ...and so must the remembered choice — otherwise a reload silently
    // brings French back even though the widget just showed English.
    expect(await page.evaluate(() => window.localStorage.getItem('blueworxTranslateLang'))).toBeNull();
  });

  test('a mouse selection does not move focus to the toggle', async ({ page }) => {
    await installTranslatorStub(page);
    await page.goto('/');

    const toggle = page.getByRole('button', { name: /Choose language/ });
    await toggle.click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();

    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    // Focus-return-to-toggle is a keyboard-only affordance; a pointer
    // selection must never jerk focus onto the toggle once the pass settles.
    await expect(toggle).not.toBeFocused();
  });
});
