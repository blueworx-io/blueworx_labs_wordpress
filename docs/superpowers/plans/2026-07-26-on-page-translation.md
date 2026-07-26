# On-Page Translation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `translate` feature to the BlueWorx Labs WordPress plugin that puts a floating language switcher on the frontend and translates the page on the visitor's own device via the Chrome built-in Translator API, replacing the paid Weglot plugin at zero recurring cost.

**Architecture:** One new PHP file (`includes/translate.php`) owns the settings panel, sanitisation, and frontend enqueue; two new asset files own the widget and its styling; one new Playwright spec covers it. Nothing is stored per-visitor server-side and nothing leaves the browser — `Translator` is a browser API, not a network service. `admin-settings.php` gains only two delegating lines.

**Tech Stack:** PHP 8.0+ (WordPress 5.0+ APIs, procedural `blueworx_` prefixed functions), vanilla browser JS (ES2021, no build step, no framework), plain CSS, Playwright for tests.

**Spec:** `docs/superpowers/specs/2026-07-26-on-page-translation-design.md`

## Global Constraints

- **Branch:** `add-page-translation` (already created off `main`). Never commit to `main`.
- **Version:** the final task bumps to **1.37.0** (minor) in `blueworx-labs-wordpress.php` header, `BLUEWORX_LABS_VERSION`, `package.json`, and `readme.txt` `Stable tag` — all four must match or `npm run version:check` fails.
- **No new dependencies.** `approved-deps.json` governs; this feature adds none, in PHP or JS.
- **All PHP functions are procedural and prefixed `blueworx_translate_`.** No classes — the codebase has none.
- **Every PHP file starts with the `ABSPATH` guard** (`if ( ! defined( 'ABSPATH' ) ) { exit; }`) and a `@package BlueWorxLabs` docblock.
- **Escape on output:** `esc_html`, `esc_attr`, `esc_url`, `wp_json_encode`. Sanitise on input: `sanitize_text_field`, `sanitize_key`, plus the allowlists in this plan.
- **Text domain is `blueworx-labs-wordpress`** on every translatable string.
- **JS must pass `npm run lint`** (`eslint assets/js`). Config is `sourceType: 'script'`, `ecmaVersion: 2021`, browser globals — so no ES modules, no top-level `await`, and unused vars warn.
- **Tests are Playwright only.** There is no PHP unit-test framework in this repo. Specs import `test` from `./helpers.js`, never from `@playwright/test` (the fixture there prevents a headless rendering freeze).
- **The real on-device translation model cannot run in CI.** Every translation test injects a stubbed `window.Translator` via `page.addInitScript()`.
- **Chrome/Edge 138+ only.** Where the API is absent the widget must render nothing at all — no fallback engine, no error message, no console noise.
- **Feature defaults ON**, consistent with every other feature: `blueworx_feature_enabled()` treats an absent option as enabled.

## File Structure

| File | Status | Responsibility |
|------|--------|----------------|
| `includes/translate.php` | Create | Language maps, option getters, sanitise + save, settings detail panel, frontend enqueue, widget root markup, config payload. |
| `assets/js/translate-widget.js` | Create | Capability detection, widget UI, DOM collection, translate/restore, mutation handling, keyboard + busy states. |
| `assets/css/translate-widget.css` | Create | Pill and dropdown styling, four corner positions, focus states, reduced-motion. |
| `tests/translate.spec.js` | Create | Admin settings panel + frontend widget behaviour against the stub. |
| `includes/features.php` | Modify | One new section (`translation`), one new definition (`translate`). |
| `includes/admin-settings.php` | Modify | Two delegating lines: render detail, save settings. |
| `blueworx-labs-wordpress.php` | Modify | One `require_once`; version bump. |
| `uninstall.php` | Modify | Delete the four new options. |
| `tests/feature-toggles.spec.js` | Modify | Assert the Translation section renders. |
| `CHANGELOG.md`, `readme.txt`, `package.json` | Modify | 1.37.0 release notes and version sync. |

---

### Task 1: Feature registry, language maps, and settings panel

**Files:**
- Create: `includes/translate.php`
- Modify: `includes/features.php` (sections map ~line 24-32, definitions map ~line 40-113)
- Modify: `includes/admin-settings.php` (`blueworx_save_feature_settings()` ~line 168-212, `blueworx_render_feature_detail()` ~line 296-377)
- Modify: `blueworx-labs-wordpress.php` (require block ~line 40-56)
- Modify: `uninstall.php`
- Test: `tests/translate.spec.js` (create)

**Interfaces:**
- Consumes: `blueworx_feature_enabled( $key )` from `includes/features.php`.
- Produces:
  - `blueworx_translate_language_labels(): array<string,string>` — BCP-47 base tag → English label.
  - `blueworx_translate_source_language(): string` — 2-letter base tag from `get_locale()`.
  - `blueworx_translate_supported_languages(): array<string,string>` — labels minus the source language.
  - `blueworx_translate_languages(): array<int,string>` — saved, validated target codes.
  - `blueworx_translate_position(): string` — one of the four corner keys.
  - `blueworx_translate_label(): string` — button label.
  - `blueworx_translate_exclusions(): array<int,string>` — saved CSS selectors.
  - `blueworx_translate_render_detail(): void` — prints the settings panel.
  - `blueworx_translate_save_settings( array $post ): void` — sanitises and writes the four options.

- [ ] **Step 1: Write the failing test**

Create `tests/translate.spec.js`:

```javascript
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx playwright test tests/translate.spec.js --reporter=line`
Expected: FAIL — `getByRole('heading', { name: 'Translation' })` not found (the section does not exist yet). If it reports SKIPPED instead, the environment has no WordPress target: set `PLAYWRIGHT_BASE_URL`, `WP_ADMIN_USER`, `WP_ADMIN_PASS` and `WP_LOGIN_PATH=admin_login` in `.env` first, because a skipped test proves nothing.

- [ ] **Step 3: Create `includes/translate.php`**

```php
<?php
/**
 * On-page translation.
 *
 * A floating language switcher that translates the current page in the visitor's
 * own browser using the Chrome built-in Translator API. There is no translation
 * service, no API key and no server-side translation store: this file only
 * decides which languages to offer and hands that list to the frontend script.
 *
 * Deliberately not an SEO feature — no translated URLs and no hreflang. See
 * docs/superpowers/specs/2026-07-26-on-page-translation-design.md.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget corner positions.
 *
 * @return array Position labels keyed by position id.
 */
function blueworx_translate_positions() {
	return array(
		'bottom-right' => __( 'Bottom right', 'blueworx-labs-wordpress' ),
		'bottom-left'  => __( 'Bottom left', 'blueworx-labs-wordpress' ),
		'top-right'    => __( 'Top right', 'blueworx-labs-wordpress' ),
		'top-left'     => __( 'Top left', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Gets every language this feature knows a label for.
 *
 * Includes the site's own language: the switcher needs a label for the source
 * language to offer "back to original", and a non-English site needs English as
 * a target.
 *
 * @return array English language names keyed by BCP-47 base tag.
 */
function blueworx_translate_language_labels() {
	return array(
		'ar' => __( 'Arabic', 'blueworx-labs-wordpress' ),
		'bn' => __( 'Bengali', 'blueworx-labs-wordpress' ),
		'de' => __( 'German', 'blueworx-labs-wordpress' ),
		'en' => __( 'English', 'blueworx-labs-wordpress' ),
		'es' => __( 'Spanish', 'blueworx-labs-wordpress' ),
		'fr' => __( 'French', 'blueworx-labs-wordpress' ),
		'hi' => __( 'Hindi', 'blueworx-labs-wordpress' ),
		'it' => __( 'Italian', 'blueworx-labs-wordpress' ),
		'ja' => __( 'Japanese', 'blueworx-labs-wordpress' ),
		'ko' => __( 'Korean', 'blueworx-labs-wordpress' ),
		'nl' => __( 'Dutch', 'blueworx-labs-wordpress' ),
		'pl' => __( 'Polish', 'blueworx-labs-wordpress' ),
		'pt' => __( 'Portuguese', 'blueworx-labs-wordpress' ),
		'ru' => __( 'Russian', 'blueworx-labs-wordpress' ),
		'tr' => __( 'Turkish', 'blueworx-labs-wordpress' ),
		'vi' => __( 'Vietnamese', 'blueworx-labs-wordpress' ),
		'zh' => __( 'Chinese', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Gets the source language: the site's own locale as a BCP-47 base tag.
 *
 * get_locale() returns forms like "en_GB" and "pt_BR"; the Translator API takes
 * base tags, and a site whose locale is not in the label map still translates
 * correctly from its own language.
 *
 * @return string Two-letter language tag, or "en" when the locale is unusable.
 */
function blueworx_translate_source_language() {
	$base = strtolower( substr( (string) get_locale(), 0, 2 ) );

	return preg_match( '/^[a-z]{2}$/', $base ) ? $base : 'en';
}

/**
 * Gets the languages that may be offered as translation targets.
 *
 * The site's own language is removed: translating English into English is not a
 * choice worth offering, and it must never be savable.
 *
 * @return array English language names keyed by BCP-47 base tag.
 */
function blueworx_translate_supported_languages() {
	$labels = blueworx_translate_language_labels();
	unset( $labels[ blueworx_translate_source_language() ] );

	return $labels;
}

/**
 * Gets the saved target languages.
 *
 * Validated on read as well as on write, so a value that predates a change to
 * the supported list — or one written directly to the database — cannot reach
 * the frontend.
 *
 * @return array Ordered list of BCP-47 base tags.
 */
function blueworx_translate_languages() {
	$saved     = get_option( 'blueworx_translate_languages', array( 'fr', 'de', 'es' ) );
	$supported = blueworx_translate_supported_languages();

	if ( ! is_array( $saved ) ) {
		return array();
	}

	$codes = array_map( 'sanitize_key', $saved );

	return array_values( array_intersect( array_unique( $codes ), array_keys( $supported ) ) );
}

/**
 * Gets the widget corner position.
 *
 * @return string One of the keys of blueworx_translate_positions().
 */
function blueworx_translate_position() {
	$saved = sanitize_key( (string) get_option( 'blueworx_translate_position', 'bottom-right' ) );

	return isset( blueworx_translate_positions()[ $saved ] ) ? $saved : 'bottom-right';
}

/**
 * Gets the switcher button label.
 *
 * @return string Non-empty label.
 */
function blueworx_translate_label() {
	$saved = trim( (string) get_option( 'blueworx_translate_label', '' ) );

	return '' === $saved ? __( 'Language', 'blueworx-labs-wordpress' ) : $saved;
}

/**
 * Gets the extra CSS selectors whose contents must never be translated.
 *
 * @return array Ordered list of selectors.
 */
function blueworx_translate_exclusions() {
	$saved = get_option( 'blueworx_translate_exclusions', array() );

	return is_array( $saved ) ? array_values( array_filter( array_map( 'strval', $saved ) ) ) : array();
}

/**
 * Sanitises and saves the translation settings.
 *
 * Called from blueworx_save_feature_settings(), which has already performed the
 * capability check, the client-role console check and the nonce check. This
 * function does no permission checking of its own and must never be wired to a
 * request on its own.
 *
 * @param array $post Raw $_POST.
 * @return void
 */
function blueworx_translate_save_settings( $post ) {
	$supported = blueworx_translate_supported_languages();
	$raw_langs = isset( $post['blueworx_translate_languages'] ) ? (array) wp_unslash( $post['blueworx_translate_languages'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with sanitize_key and intersected against the supported allowlist.
	$languages = array_values( array_intersect( array_unique( array_map( 'sanitize_key', $raw_langs ) ), array_keys( $supported ) ) );

	$raw_position = isset( $post['blueworx_translate_position'] ) ? sanitize_key( wp_unslash( $post['blueworx_translate_position'] ) ) : '';
	$position     = isset( blueworx_translate_positions()[ $raw_position ] ) ? $raw_position : 'bottom-right';

	$raw_label = isset( $post['blueworx_translate_label'] ) ? sanitize_text_field( wp_unslash( $post['blueworx_translate_label'] ) ) : '';
	$label     = trim( mb_substr( $raw_label, 0, 40 ) );

	// One selector per line, blank lines dropped, and hard caps on both the line
	// length and the number of lines so a paste accident cannot bloat the option
	// or the inline config payload on every page load.
	$raw_exclusions = isset( $post['blueworx_translate_exclusions'] ) ? (string) wp_unslash( $post['blueworx_translate_exclusions'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below, line by line, with sanitize_text_field.
	$exclusions     = array();

	foreach ( preg_split( '/\r\n|\r|\n/', $raw_exclusions ) as $line ) {
		$line = trim( sanitize_text_field( $line ) );

		if ( '' === $line ) {
			continue;
		}

		$exclusions[] = mb_substr( $line, 0, 200 );

		if ( count( $exclusions ) >= 50 ) {
			break;
		}
	}

	update_option( 'blueworx_translate_languages', $languages, false );
	update_option( 'blueworx_translate_position', $position );
	update_option( 'blueworx_translate_label', $label );
	update_option( 'blueworx_translate_exclusions', array_values( array_unique( $exclusions ) ), false );
}

/**
 * Renders the nested settings panel shown under the feature toggle.
 *
 * @return void
 */
function blueworx_translate_render_detail() {
	$supported  = blueworx_translate_supported_languages();
	$selected   = blueworx_translate_languages();
	$positions  = blueworx_translate_positions();
	$position   = blueworx_translate_position();
	$exclusions = blueworx_translate_exclusions();
	?>
	<p class="description">
		<?php esc_html_e( 'Adds a floating language button to the site. Translation happens in the visitor\'s own browser, so there is no cost and no data leaves their device. Works in Chrome and Edge 138 or newer; in other browsers the button does not appear. Search engines still only see the original language.', 'blueworx-labs-wordpress' ); ?>
	</p>
	<fieldset>
		<legend><?php esc_html_e( 'Languages offered', 'blueworx-labs-wordpress' ); ?></legend>
		<?php foreach ( $supported as $code => $label ) : ?>
			<label style="display:inline-block;min-width:9em;">
				<input type="checkbox" name="blueworx_translate_languages[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $selected, true ) ); ?> />
				<?php echo esc_html( $label ); ?>
			</label>
		<?php endforeach; ?>
	</fieldset>
	<p>
		<label for="blueworx_translate_position"><?php esc_html_e( 'Button position', 'blueworx-labs-wordpress' ); ?></label><br />
		<select id="blueworx_translate_position" name="blueworx_translate_position">
			<?php foreach ( $positions as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $position, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="blueworx_translate_label"><?php esc_html_e( 'Button label', 'blueworx-labs-wordpress' ); ?></label><br />
		<input type="text" id="blueworx_translate_label" name="blueworx_translate_label" class="regular-text" maxlength="40" value="<?php echo esc_attr( blueworx_translate_label() ); ?>" />
	</p>
	<p>
		<label for="blueworx_translate_exclusions"><?php esc_html_e( 'Never translate (one CSS selector per line)', 'blueworx-labs-wordpress' ); ?></label><br />
		<textarea id="blueworx_translate_exclusions" name="blueworx_translate_exclusions" class="large-text code" rows="4"><?php echo esc_textarea( implode( "\n", $exclusions ) ); ?></textarea>
		<span class="description"><?php esc_html_e( 'Use for brand names, product codes and anything that must stay as written. Code, pre and elements marked translate="no" or .notranslate are always skipped.', 'blueworx-labs-wordpress' ); ?></span>
	</p>
	<?php
}
```

- [ ] **Step 4: Register the section and the feature in `includes/features.php`**

In `blueworx_get_feature_sections()`, add the section after `'content'`:

```php
		'content'       => __( 'Content', 'blueworx-labs-wordpress' ),
		'translation'   => __( 'Translation', 'blueworx-labs-wordpress' ),
```

In `blueworx_get_feature_definitions()`, add the definition after the `page_excerpts` entry:

```php
			'translate'             => array(
				'label'       => __( 'On-page translation', 'blueworx-labs-wordpress' ),
				'description' => __( 'Adds a floating language button that translates the page on the visitor\'s own device. Chrome and Edge only; no effect on search engines.', 'blueworx-labs-wordpress' ),
				'section'     => 'translation',
				'detail'      => 'translate',
			),
```

- [ ] **Step 5: Wire the two delegating lines in `includes/admin-settings.php`**

In `blueworx_render_feature_detail()`, add this branch immediately before the `if ( 'cache_manual' === $key )` branch:

```php
	if ( 'translate' === $key ) {
		blueworx_translate_render_detail();
		return;
	}
```

In `blueworx_save_feature_settings()`, add this immediately after the Client Roles detail line and before the `blueworx_client_roles_maybe_ensure()` call:

```php
	// Translation detail: languages, position, label and exclusions.
	blueworx_translate_save_settings( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() ran at the top of this handler; the callee sanitizes every field.
```

- [ ] **Step 6: Load the file and clean up on uninstall**

In `blueworx-labs-wordpress.php`, add after the `page-excerpts.php` require:

```php
require_once BLUEWORX_LABS_PATH . 'includes/translate.php';
```

In `uninstall.php`, add at the end:

```php
delete_option( 'blueworx_translate_languages' );
delete_option( 'blueworx_translate_position' );
delete_option( 'blueworx_translate_label' );
delete_option( 'blueworx_translate_exclusions' );
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `npx playwright test tests/translate.spec.js --reporter=line`
Expected: PASS (2 tests). If the second test fails on the exclusions textarea value, check that `esc_textarea( implode( "\n", ... ) )` is used — the assertion depends on blank lines being dropped on save, not on display.

- [ ] **Step 8: Commit**

```bash
git add includes/translate.php includes/features.php includes/admin-settings.php blueworx-labs-wordpress.php uninstall.php tests/translate.spec.js
git commit -m "feat: add translation feature registry and settings panel"
```

---

### Task 2: Frontend enqueue, widget root, and config payload

**Files:**
- Modify: `includes/translate.php` (append)
- Create: `assets/js/translate-widget.js`
- Create: `assets/css/translate-widget.css`
- Test: `tests/translate.spec.js` (append a describe block)

**Interfaces:**
- Consumes: `blueworx_feature_enabled( 'translate' )`, `blueworx_translate_languages()`, `blueworx_translate_position()`, `blueworx_translate_label()`, `blueworx_translate_exclusions()`, `blueworx_translate_source_language()`, `blueworx_translate_language_labels()` (Task 1); `blueworx_get_admin_asset_version( $relative_path )` from `includes/admin-assets.php` (already loaded before this file — it is a plain version helper and works for frontend assets too).
- Produces:
  - `blueworx_translate_should_load(): bool`
  - `blueworx_translate_config(): array` — the payload written to `window.blueworxTranslate`, with keys `source`, `sourceLabel`, `languages` (list of `{ code, label }`), `position`, `label`, `exclude`.
  - `blueworx_translate_enqueue_assets(): void` — hooked to `wp_enqueue_scripts`.
  - `blueworx_translate_render_root(): void` — hooked to `wp_footer`, prints `<div id="blueworx-translate-root"></div>`.

- [ ] **Step 1: Write the failing test**

Append to `tests/translate.spec.js`:

```javascript
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
    expect(config.languages.map((l) => l.code)).toEqual(['fr', 'de', 'es']);
    expect(config.languages.every((l) => typeof l.label === 'string' && l.label.length > 0)).toBe(true);
  });

  test('the stylesheet is enqueued on the front end', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('link[href*="translate-widget.css"]')).toHaveCount(1);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx playwright test tests/translate.spec.js --reporter=line -g "frontend delivery"`
Expected: FAIL — `#blueworx-translate-root` has count 0.

- [ ] **Step 3: Append the enqueue code to `includes/translate.php`**

```php
/**
 * Decides whether the frontend widget should load at all.
 *
 * @return bool True when the feature is on, this is a frontend request, and at
 *              least one target language is configured.
 */
function blueworx_translate_should_load() {
	if ( is_admin() || ! blueworx_feature_enabled( 'translate' ) ) {
		return false;
	}

	return array() !== blueworx_translate_languages();
}

/**
 * Builds the config handed to the frontend script.
 *
 * @return array Config payload.
 */
function blueworx_translate_config() {
	$labels    = blueworx_translate_language_labels();
	$source    = blueworx_translate_source_language();
	$languages = array();

	foreach ( blueworx_translate_languages() as $code ) {
		$languages[] = array(
			'code'  => $code,
			'label' => isset( $labels[ $code ] ) ? $labels[ $code ] : strtoupper( $code ),
		);
	}

	return array(
		'source'      => $source,
		'sourceLabel' => isset( $labels[ $source ] ) ? $labels[ $source ] : strtoupper( $source ),
		'languages'   => $languages,
		'position'    => blueworx_translate_position(),
		'label'       => blueworx_translate_label(),
		'exclude'     => blueworx_translate_exclusions(),
	);
}

/**
 * Enqueues the frontend widget script and stylesheet.
 *
 * @return void
 */
function blueworx_translate_enqueue_assets() {
	if ( ! blueworx_translate_should_load() ) {
		return;
	}

	wp_enqueue_style(
		'blueworx-translate-widget',
		BLUEWORX_LABS_URL . 'assets/css/translate-widget.css',
		array(),
		blueworx_get_admin_asset_version( 'assets/css/translate-widget.css' )
	);

	wp_enqueue_script(
		'blueworx-translate-widget',
		BLUEWORX_LABS_URL . 'assets/js/translate-widget.js',
		array(),
		blueworx_get_admin_asset_version( 'assets/js/translate-widget.js' ),
		true
	);

	wp_add_inline_script(
		'blueworx-translate-widget',
		'window.blueworxTranslate = ' . wp_json_encode( blueworx_translate_config() ) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'blueworx_translate_enqueue_assets' );

/**
 * Prints the empty root the script builds the widget inside.
 *
 * Kept empty on purpose: a browser without the Translator API leaves it empty
 * and invisible, so an unsupported browser shows no control at all rather than
 * one that cannot work.
 *
 * @return void
 */
function blueworx_translate_render_root() {
	if ( ! blueworx_translate_should_load() ) {
		return;
	}

	echo '<div id="blueworx-translate-root"></div>';
}
add_action( 'wp_footer', 'blueworx_translate_render_root' );
```

- [ ] **Step 4: Create `assets/js/translate-widget.js`**

```javascript
/**
 * On-page translation widget.
 *
 * Translates the current page in the visitor's own browser using the Chrome
 * built-in Translator API. There is no network request and no translation
 * service: if the API is not there, this file does nothing and renders nothing.
 */
(function () {
  'use strict';

  var config = window.blueworxTranslate;

  /**
   * Reports whether this browser exposes the built-in Translator API.
   *
   * Chrome/Edge 138+ only. Everywhere else the widget must render nothing at
   * all — a language button that cannot translate is worse than no button.
   *
   * @return {boolean} True when the API is available.
   */
  function isSupported() {
    return typeof self !== 'undefined' && 'Translator' in self;
  }

  /**
   * Boots the widget.
   */
  function init() {
    if (!config || !config.languages || config.languages.length === 0) {
      return;
    }

    if (!isSupported()) {
      return;
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
```

- [ ] **Step 5: Create `assets/css/translate-widget.css`**

```css
/**
 * On-page translation widget.
 *
 * The root ships empty and stays invisible until the script builds the widget
 * inside it, so an unsupported browser renders nothing.
 */

#blueworx-translate-root:empty {
  display: none;
}

.blueworx-translate {
  --bw-translate-bg: #ffffff;
  --bw-translate-fg: #14202e;
  --bw-translate-border: #c9d3de;
  --bw-translate-accent: #1f5fbf;

  position: fixed;
  z-index: 99000;
  font-family: inherit;
  font-size: 14px;
  line-height: 1.4;
}

.blueworx-translate--bottom-right {
  right: 20px;
  bottom: 20px;
}

.blueworx-translate--bottom-left {
  left: 20px;
  bottom: 20px;
}

.blueworx-translate--top-right {
  right: 20px;
  top: 20px;
}

.blueworx-translate--top-left {
  left: 20px;
  top: 20px;
}

.blueworx-translate__toggle {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 10px 16px;
  border: 1px solid var(--bw-translate-border);
  border-radius: 999px;
  background: var(--bw-translate-bg);
  color: var(--bw-translate-fg);
  font: inherit;
  cursor: pointer;
  box-shadow: 0 2px 10px rgba(20, 32, 46, 0.15);
}

.blueworx-translate__toggle:hover {
  border-color: var(--bw-translate-accent);
}

.blueworx-translate__toggle:focus-visible {
  outline: 2px solid var(--bw-translate-accent);
  outline-offset: 2px;
}

.blueworx-translate__toggle[aria-busy='true'] {
  cursor: progress;
}

.blueworx-translate__label {
  opacity: 0.7;
}

.blueworx-translate__current {
  font-weight: 600;
}

.blueworx-translate__list {
  position: absolute;
  right: 0;
  bottom: calc(100% + 8px);
  min-width: 180px;
  max-height: 60vh;
  margin: 0;
  padding: 6px;
  overflow-y: auto;
  list-style: none;
  border: 1px solid var(--bw-translate-border);
  border-radius: 10px;
  background: var(--bw-translate-bg);
  box-shadow: 0 6px 24px rgba(20, 32, 46, 0.18);
}

.blueworx-translate--top-right .blueworx-translate__list,
.blueworx-translate--top-left .blueworx-translate__list {
  top: calc(100% + 8px);
  bottom: auto;
}

.blueworx-translate--bottom-left .blueworx-translate__list,
.blueworx-translate--top-left .blueworx-translate__list {
  right: auto;
  left: 0;
}

.blueworx-translate__option {
  padding: 8px 12px;
  border-radius: 6px;
  color: var(--bw-translate-fg);
  cursor: pointer;
}

.blueworx-translate__option:hover,
.blueworx-translate__option:focus-visible {
  outline: none;
  background: rgba(31, 95, 191, 0.1);
}

.blueworx-translate__option[aria-selected='true'] {
  font-weight: 600;
}

.blueworx-translate__status {
  display: block;
  margin-top: 6px;
  text-align: right;
  color: var(--bw-translate-fg);
  font-size: 12px;
}

.blueworx-translate__status:empty {
  display: none;
}
```

- [ ] **Step 6: Run the test and the linter to verify they pass**

Run: `npx playwright test tests/translate.spec.js --reporter=line -g "frontend delivery"`
Expected: PASS (2 tests)

Run: `npm run lint`
Expected: exit 0, no errors (a `no-unused-vars` warning for `isSupported` is not possible — it is called in `init`).

- [ ] **Step 7: Commit**

```bash
git add includes/translate.php assets/js/translate-widget.js assets/css/translate-widget.css tests/translate.spec.js
git commit -m "feat: enqueue translation widget assets and config payload"
```

---

### Task 3: Capability detection and switcher UI

**Files:**
- Modify: `assets/js/translate-widget.js`
- Test: `tests/translate.spec.js` (append a describe block)

**Interfaces:**
- Consumes: `window.blueworxTranslate` (Task 2), `#blueworx-translate-root` (Task 2).
- Produces, inside the IIFE:
  - `availableLanguages(): Promise<Array<{code: string, label: string}>>`
  - `buildWidget(languages): void` — creates the pill, the listbox and the status line inside the root; sets `state.root`, `state.toggle`, `state.list`, `state.status`, `state.current`.
  - `setCurrent(code): void` — updates the pill text and `aria-selected`.
  - `openList(): void` / `closeList(): void`
  - A module-level `state` object: `{ root, toggle, list, status, current, translator: null, targetCode: null }`.

- [ ] **Step 1: Write the failing test**

Append to `tests/translate.spec.js`:

```javascript
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
    // Three configured targets plus the source language.
    await expect(options).toHaveCount(4);
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
    await expect(options).toHaveCount(3);
    await expect(page.locator('.blueworx-translate__option[data-lang="de"]')).toHaveCount(0);
    await expect(page.locator('.blueworx-translate__option[data-lang="fr"]')).toHaveCount(1);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx playwright test tests/translate.spec.js --reporter=line -g "switcher UI"`
Expected: FAIL on the first and third tests — `.blueworx-translate` has count 0. The "renders nothing" test passes already; that is correct and it must keep passing.

- [ ] **Step 3: Replace the body of `assets/js/translate-widget.js` with the UI implementation**

```javascript
/**
 * On-page translation widget.
 *
 * Translates the current page in the visitor's own browser using the Chrome
 * built-in Translator API. There is no network request and no translation
 * service: if the API is not there, this file does nothing and renders nothing.
 */
(function () {
  'use strict';

  var config = window.blueworxTranslate;

  var state = {
    root: null,
    toggle: null,
    list: null,
    status: null,
    current: null,
    translator: null,
    targetCode: null,
  };

  /**
   * Reports whether this browser exposes the built-in Translator API.
   *
   * Chrome/Edge 138+ only. Everywhere else the widget must render nothing at
   * all — a language button that cannot translate is worse than no button.
   *
   * @return {boolean} True when the API is available.
   */
  function isSupported() {
    return typeof self !== 'undefined' && 'Translator' in self;
  }

  /**
   * Filters the configured languages down to the ones this browser will accept.
   *
   * The browser is the authority, not the admin setting: a pair it reports as
   * unavailable is dropped from the menu rather than offered and then failing.
   *
   * @return {Promise<Array>} Usable languages, in configured order.
   */
  function availableLanguages() {
    var checks = config.languages.map(function (language) {
      return Promise.resolve()
        .then(function () {
          return self.Translator.availability({
            sourceLanguage: config.source,
            targetLanguage: language.code,
          });
        })
        .then(function (availability) {
          return availability === 'unavailable' ? null : language;
        })
        .catch(function () {
          return null;
        });
    });

    return Promise.all(checks).then(function (results) {
      return results.filter(Boolean);
    });
  }

  /**
   * Sets the language shown on the pill and selected in the list.
   *
   * @param {string} code Language code, or the source code for "original".
   */
  function setCurrent(code) {
    state.targetCode = code === config.source ? null : code;

    var options = state.list.querySelectorAll('.blueworx-translate__option');

    for (var i = 0; i < options.length; i += 1) {
      var isActive = options[i].getAttribute('data-lang') === code;
      options[i].setAttribute('aria-selected', isActive ? 'true' : 'false');

      if (isActive) {
        state.current.textContent = options[i].textContent;
      }
    }
  }

  /**
   * Opens the language list.
   */
  function openList() {
    state.list.hidden = false;
    state.toggle.setAttribute('aria-expanded', 'true');
  }

  /**
   * Closes the language list.
   */
  function closeList() {
    state.list.hidden = true;
    state.toggle.setAttribute('aria-expanded', 'false');
  }

  /**
   * Builds the widget inside the root element.
   *
   * @param {Array} languages Usable languages.
   */
  function buildWidget(languages) {
    var root = document.getElementById('blueworx-translate-root');

    if (!root) {
      return;
    }

    var widget = document.createElement('div');
    widget.className = 'blueworx-translate blueworx-translate--' + config.position;

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'blueworx-translate__toggle';
    toggle.id = 'blueworx-translate-toggle';
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-haspopup', 'listbox');

    var label = document.createElement('span');
    label.className = 'blueworx-translate__label';
    label.textContent = config.label;

    var current = document.createElement('span');
    current.className = 'blueworx-translate__current';

    toggle.appendChild(label);
    toggle.appendChild(current);

    var list = document.createElement('ul');
    list.className = 'blueworx-translate__list';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-labelledby', 'blueworx-translate-toggle');
    list.hidden = true;

    // The source language leads the list: it is how a visitor gets back to the
    // page as written.
    var choices = [{ code: config.source, label: config.sourceLabel }].concat(languages);

    choices.forEach(function (choice) {
      var option = document.createElement('li');
      option.className = 'blueworx-translate__option';
      option.setAttribute('role', 'option');
      option.setAttribute('tabindex', '-1');
      option.setAttribute('data-lang', choice.code);
      option.setAttribute('aria-selected', 'false');
      option.textContent = choice.label;
      list.appendChild(option);
    });

    var status = document.createElement('span');
    status.className = 'blueworx-translate__status';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');

    widget.appendChild(list);
    widget.appendChild(toggle);
    widget.appendChild(status);
    root.appendChild(widget);

    state.root = widget;
    state.toggle = toggle;
    state.list = list;
    state.status = status;
    state.current = current;

    setCurrent(config.source);

    toggle.addEventListener('click', function () {
      if (state.list.hidden) {
        openList();
      } else {
        closeList();
      }
    });

    document.addEventListener('click', function (event) {
      if (!state.list.hidden && !widget.contains(event.target)) {
        closeList();
      }
    });
  }

  /**
   * Boots the widget.
   */
  function init() {
    if (!config || !config.languages || config.languages.length === 0) {
      return;
    }

    if (!isSupported()) {
      return;
    }

    availableLanguages().then(function (languages) {
      if (languages.length === 0) {
        return;
      }

      buildWidget(languages);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
```

- [ ] **Step 4: Run the test and the linter to verify they pass**

Run: `npx playwright test tests/translate.spec.js --reporter=line -g "switcher UI"`
Expected: PASS (3 tests)

Run: `npm run lint`
Expected: exit 0

- [ ] **Step 5: Commit**

```bash
git add assets/js/translate-widget.js tests/translate.spec.js
git commit -m "feat: render translation switcher with capability detection"
```

---

### Task 4: Translate the page — text nodes, attributes, exclusions

**Files:**
- Modify: `assets/js/translate-widget.js`
- Test: `tests/translate.spec.js` (append a describe block)

**Interfaces:**
- Consumes: `state`, `config`, `setCurrent`, `closeList` (Task 3).
- Produces, inside the IIFE:
  - `TRANSLATABLE_ATTRS` — `['alt', 'title', 'placeholder', 'aria-label']`.
  - `CONCURRENCY` — `4`.
  - `excludeSelector(): string` — base exclusions plus valid admin selectors, joined.
  - `recordOriginal(node, attr, value): boolean` — false when already recorded.
  - `collectTargets(scope): Array<{node: Node, attr: string, text: string}>` — `attr` is `''` for text nodes.
  - `translateTargets(targets): Promise<void>`
  - `applyLanguage(code): Promise<void>`
  - `setBusy(isBusy, message): void`

- [ ] **Step 1: Write the failing test**

Append to `tests/translate.spec.js`:

```javascript
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
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx playwright test tests/translate.spec.js --reporter=line -g "translating"`
Expected: FAIL — `#bw-text` still reads "Hello world"; clicking an option does nothing yet.

- [ ] **Step 3: Add the translation engine to `assets/js/translate-widget.js`**

Insert these constants directly below the `state` declaration:

```javascript
  var TRANSLATABLE_ATTRS = ['alt', 'title', 'placeholder', 'aria-label'];

  // Always skipped: markup where translated text would be wrong (code, script)
  // and the widget itself, which must never translate its own controls.
  var BASE_EXCLUDE = [
    'script',
    'style',
    'noscript',
    'code',
    'pre',
    'kbd',
    'samp',
    'textarea',
    '[translate="no"]',
    '.notranslate',
    '.blueworx-no-translate',
    '#blueworx-translate-root',
  ];

  // Four in-flight translate() calls: enough to keep the model busy, few enough
  // that the page fills in progressively instead of stalling on one long batch.
  var CONCURRENCY = 4;

  // node -> { '': originalText, alt: originalAlt, ... }. Weak so a node removed
  // from the document is not pinned in memory by this map.
  var originals = new WeakMap();

  // Every recorded { node, attr } pair, in the order it was translated, so the
  // source language can be restored without a reload.
  var touched = [];

  var excludeSelectorCache = null;
```

Insert these functions directly above `buildWidget`:

```javascript
  /**
   * Builds the joined selector of everything that must not be translated.
   *
   * Admin-supplied selectors are validated one at a time and a malformed one is
   * dropped: a typo in a site setting must not stop the rest of the page from
   * translating.
   *
   * @return {string} Joined CSS selector.
   */
  function excludeSelector() {
    if (excludeSelectorCache !== null) {
      return excludeSelectorCache;
    }

    var selectors = BASE_EXCLUDE.slice();
    var extra = Array.isArray(config.exclude) ? config.exclude : [];

    extra.forEach(function (selector) {
      var candidate = String(selector).trim();

      if (candidate === '') {
        return;
      }

      try {
        document.createDocumentFragment().querySelector(candidate);
        selectors.push(candidate);
      } catch (error) {
        // Malformed selector from the settings screen; skip it.
      }
    });

    excludeSelectorCache = selectors.join(',');

    return excludeSelectorCache;
  }

  /**
   * Reports whether an element sits inside excluded markup.
   *
   * @param {Element} element Element to test.
   * @return {boolean} True when the element must not be translated.
   */
  function isExcluded(element) {
    if (!element || typeof element.closest !== 'function') {
      return true;
    }

    try {
      return element.closest(excludeSelector()) !== null;
    } catch (error) {
      return false;
    }
  }

  /**
   * Records a value's original so it can be restored later.
   *
   * @param {Node}   node  Text node or element.
   * @param {string} attr  Attribute name, or '' for text content.
   * @param {string} value Current value.
   * @return {boolean} False when this pair was already recorded.
   */
  function recordOriginal(node, attr, value) {
    var entry = originals.get(node);

    if (!entry) {
      entry = {};
      originals.set(node, entry);
    }

    if (Object.prototype.hasOwnProperty.call(entry, attr)) {
      return false;
    }

    entry[attr] = value;
    touched.push({ node: node, attr: attr });

    return true;
  }

  /**
   * Collects every not-yet-translated string within a scope.
   *
   * @param {Node} scope Element or document fragment to walk.
   * @return {Array} Targets as { node, attr, text }.
   */
  function collectTargets(scope) {
    var targets = [];
    var walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT, {
      acceptNode: function (node) {
        if (node.nodeType === Node.TEXT_NODE) {
          var text = node.nodeValue;

          // Whitespace, digits and punctuation are not worth a model call, and
          // "2026" comes back unchanged at best.
          if (!text || text.trim() === '' || !/\p{L}/u.test(text)) {
            return NodeFilter.FILTER_REJECT;
          }

          return isExcluded(node.parentElement) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
        }

        return isExcluded(node) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
      },
    });

    var node = walker.currentNode;

    while (node) {
      if (node.nodeType === Node.TEXT_NODE) {
        if (recordOriginal(node, '', node.nodeValue)) {
          targets.push({ node: node, attr: '', text: node.nodeValue });
        }
      } else if (node.nodeType === Node.ELEMENT_NODE) {
        TRANSLATABLE_ATTRS.forEach(function (attr) {
          var value = node.getAttribute(attr);

          if (value && value.trim() !== '' && /\p{L}/u.test(value) && recordOriginal(node, attr, value)) {
            targets.push({ node: node, attr: attr, text: value });
          }
        });
      }

      node = walker.nextNode();
    }

    return targets;
  }

  /**
   * Writes a translated value back onto its node.
   *
   * @param {{node: Node, attr: string}} target Target record.
   * @param {string}                     value  Translated value.
   */
  function writeTarget(target, value) {
    if (target.attr === '') {
      target.node.nodeValue = value;
    } else {
      target.node.setAttribute(target.attr, value);
    }
  }

  /**
   * Translates a list of targets, four calls in flight at a time.
   *
   * A single failed call leaves that one string as written and the pass
   * continues: a partial translation beats a blank or reverted page.
   *
   * @param {Array} targets Targets from collectTargets().
   * @return {Promise} Resolves when every target has been attempted.
   */
  function translateTargets(targets) {
    if (!state.translator || targets.length === 0) {
      return Promise.resolve();
    }

    var next = 0;

    function worker() {
      if (next >= targets.length) {
        return Promise.resolve();
      }

      var target = targets[next];
      next += 1;

      return Promise.resolve()
        .then(function () {
          return state.translator.translate(target.text);
        })
        .then(function (translated) {
          if (typeof translated === 'string' && translated !== '') {
            writeTarget(target, translated);
          }
        })
        .catch(function () {
          // Leave this string as written and keep going.
        })
        .then(worker);
    }

    var workers = [];

    for (var i = 0; i < CONCURRENCY; i += 1) {
      workers.push(worker());
    }

    return Promise.all(workers);
  }

  /**
   * Puts the pill into or out of its busy state.
   *
   * @param {boolean} isBusy  Whether work is in progress.
   * @param {string}  message Status text, announced politely.
   */
  function setBusy(isBusy, message) {
    state.toggle.setAttribute('aria-busy', isBusy ? 'true' : 'false');
    state.toggle.disabled = isBusy;
    state.status.textContent = message || '';
  }

  /**
   * Translates the whole page into one language.
   *
   * @param {string} code Target language code.
   * @return {Promise} Resolves when the pass has finished.
   */
  function applyLanguage(code) {
    closeList();
    setBusy(true, config.label + '…');

    return Promise.resolve()
      .then(function () {
        return self.Translator.create({
          sourceLanguage: config.source,
          targetLanguage: code,
          monitor: function (monitor) {
            monitor.addEventListener('downloadprogress', function (event) {
              var percent = Math.round((event.loaded || 0) * 100);
              state.status.textContent = percent + '%';
            });
          },
        });
      })
      .then(function (translator) {
        state.translator = translator;

        return translateTargets(collectTargets(document.body));
      })
      .then(function () {
        document.documentElement.lang = code;
        setCurrent(code);
        setBusy(false, '');
      })
      .catch(function () {
        state.translator = null;
        setBusy(false, '');
      });
  }
```

Then wire selection: inside `buildWidget`, immediately after the `document.addEventListener('click', ...)` handler, add:

```javascript
    list.addEventListener('click', function (event) {
      var option = event.target.closest('.blueworx-translate__option');

      if (!option) {
        return;
      }

      var code = option.getAttribute('data-lang');

      if (code === config.source || code === state.targetCode) {
        closeList();
        return;
      }

      applyLanguage(code);
    });
```

- [ ] **Step 4: Run the test and the linter to verify they pass**

Run: `npx playwright test tests/translate.spec.js --reporter=line -g "translating"`
Expected: PASS (2 tests)

Run: `npm run lint`
Expected: exit 0

- [ ] **Step 5: Commit**

```bash
git add assets/js/translate-widget.js tests/translate.spec.js
git commit -m "feat: translate page text and attributes with exclusions"
```

---

### Task 5: Restore the original, and remember the choice across visits

**Files:**
- Modify: `assets/js/translate-widget.js`
- Test: `tests/translate.spec.js` (append a describe block)

**Interfaces:**
- Consumes: `state`, `originals`, `touched`, `applyLanguage`, `setCurrent`, `closeList` (Task 4).
- Produces, inside the IIFE:
  - `STORAGE_KEY` — `'blueworxTranslateLang'`.
  - `readStoredLang(): string` / `writeStoredLang(code): void` / `clearStoredLang(): void`
  - `restoreOriginals(): void`
  - `applySource(): void` — restore plus state and storage reset.

- [ ] **Step 1: Write the failing test**

Append to `tests/translate.spec.js`:

```javascript
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx playwright test tests/translate.spec.js --reporter=line -g "restore and persistence"`
Expected: FAIL — selecting English does nothing (the handler returns early for the source code) and no language is remembered across the reload.

- [ ] **Step 3: Add restore and persistence to `assets/js/translate-widget.js`**

Add below the `excludeSelectorCache` declaration:

```javascript
  var STORAGE_KEY = 'blueworxTranslateLang';
```

Add these functions directly above `applyLanguage`:

```javascript
  /**
   * Reads the remembered language.
   *
   * Storage access is wrapped: it throws outright in a browser with cookies and
   * site data blocked, and that must not stop the widget from working for the
   * rest of the visit.
   *
   * @return {string} Stored code, or '' when there is none.
   */
  function readStoredLang() {
    try {
      return window.localStorage.getItem(STORAGE_KEY) || '';
    } catch (error) {
      return '';
    }
  }

  /**
   * Remembers a language for future visits.
   *
   * @param {string} code Language code.
   */
  function writeStoredLang(code) {
    try {
      window.localStorage.setItem(STORAGE_KEY, code);
    } catch (error) {
      // Storage unavailable; the choice simply will not survive this page.
    }
  }

  /**
   * Forgets the remembered language.
   */
  function clearStoredLang() {
    try {
      window.localStorage.removeItem(STORAGE_KEY);
    } catch (error) {
      // Nothing to do: there is no storage to clear.
    }
  }

  /**
   * Puts every translated string back exactly as the page served it.
   *
   * In-memory and synchronous — no reload, no second model call.
   */
  function restoreOriginals() {
    for (var i = touched.length - 1; i >= 0; i -= 1) {
      var record = touched[i];
      var entry = originals.get(record.node);

      if (!entry || !Object.prototype.hasOwnProperty.call(entry, record.attr)) {
        continue;
      }

      if (record.attr === '') {
        record.node.nodeValue = entry[''];
      } else {
        record.node.setAttribute(record.attr, entry[record.attr]);
      }
    }

    touched = [];
    originals = new WeakMap();
  }

  /**
   * Returns the page to the language it was written in.
   */
  function applySource() {
    closeList();
    restoreOriginals();
    state.translator = null;
    document.documentElement.lang = config.source;
    setCurrent(config.source);
    clearStoredLang();
    setBusy(false, '');
  }
```

In `applyLanguage`, remember the choice: replace the `.then` that finishes the pass with

```javascript
      .then(function () {
        document.documentElement.lang = code;
        setCurrent(code);
        writeStoredLang(code);
        setBusy(false, '');
      })
```

In `buildWidget`, replace the list click handler added in Task 4 with one that handles the source language too:

```javascript
    list.addEventListener('click', function (event) {
      var option = event.target.closest('.blueworx-translate__option');

      if (!option) {
        return;
      }

      var code = option.getAttribute('data-lang');

      if (code === config.source) {
        applySource();
        return;
      }

      if (code === state.targetCode) {
        closeList();
        return;
      }

      applyLanguage(code);
    });
```

In `init`, re-apply a remembered language once the widget exists — replace the `availableLanguages().then(...)` block with:

```javascript
    availableLanguages().then(function (languages) {
      if (languages.length === 0) {
        return;
      }

      buildWidget(languages);

      var stored = readStoredLang();
      var offered = languages.some(function (language) {
        return language.code === stored;
      });

      if (stored === '' || !offered) {
        // A language that is no longer configured, or that this browser can no
        // longer translate, must not leave the visitor stuck on a dead choice.
        clearStoredLang();
        return;
      }

      applyLanguage(stored);
    });
```

- [ ] **Step 4: Run the test and the linter to verify they pass**

Run: `npx playwright test tests/translate.spec.js --reporter=line -g "restore and persistence"`
Expected: PASS (3 tests)

Run: `npm run lint`
Expected: exit 0

- [ ] **Step 5: Run the whole spec to confirm nothing regressed**

Run: `npx playwright test tests/translate.spec.js --reporter=line`
Expected: PASS (12 tests)

- [ ] **Step 6: Commit**

```bash
git add assets/js/translate-widget.js tests/translate.spec.js
git commit -m "feat: restore original text and remember the chosen language"
```

---

### Task 6: Translate content that arrives after load

**Files:**
- Modify: `assets/js/translate-widget.js`
- Test: `tests/translate.spec.js` (append a describe block)

**Interfaces:**
- Consumes: `state`, `collectTargets`, `translateTargets` (Task 4).
- Produces, inside the IIFE:
  - `OBSERVER_DEBOUNCE` — `250`.
  - `startObserver(): void` / `stopObserver(): void`
  - `translatePending(): void`

- [ ] **Step 1: Write the failing test**

Append to `tests/translate.spec.js`:

```javascript
test.describe('BlueWorx on-page translation — dynamic content', () => {
  test.skip(isPlaceholder, 'No real staging/preview URL configured yet.');

  test('content added after translating is translated too', async ({ page }) => {
    await installTranslatorStub(page);
    await page.goto('/');

    await page.getByRole('button', { name: /Language/ }).click();
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

    await page.getByRole('button', { name: /Language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');

    const callsAfterFirstPass = await page.evaluate(() => window.__bwTranslateCalls);
    // Give the debounced observer more than one window to misbehave in.
    await page.waitForTimeout(1000);

    expect(await page.evaluate(() => window.__bwTranslateCalls)).toBe(callsAfterFirstPass);
    await expect(page.locator('body')).not.toContainText('[fr] [fr]');
  });

  test('nothing is translated after returning to the source language', async ({ page }) => {
    await installTranslatorStub(page);
    await page.goto('/');

    await page.getByRole('button', { name: /Language/ }).click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');

    await page.getByRole('button', { name: /Language/ }).click();
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx playwright test tests/translate.spec.js --reporter=line -g "dynamic content"`
Expected: FAIL on the first test — `#bw-late` still reads "Loaded later". The second and third pass already (there is no observer to misbehave); they must keep passing.

- [ ] **Step 3: Add the observer to `assets/js/translate-widget.js`**

Add below the `STORAGE_KEY` declaration:

```javascript
  // Elementor popups and AJAX loads drop content in long after load, often in
  // bursts. A quarter second of quiet is enough to batch a burst into one pass.
  var OBSERVER_DEBOUNCE = 250;

  var observer = null;
  var observerTimer = null;
```

Add these functions directly above `applyLanguage`:

```javascript
  /**
   * Stops watching for new content.
   */
  function stopObserver() {
    if (observer) {
      observer.disconnect();
    }

    if (observerTimer) {
      window.clearTimeout(observerTimer);
      observerTimer = null;
    }
  }

  /**
   * Translates whatever has appeared since the last pass.
   *
   * The observer is disconnected while writing, so the widget's own writes
   * cannot queue another pass — and collectTargets() skips anything already
   * recorded, so even a missed disconnect could not translate a translation.
   */
  function translatePending() {
    observerTimer = null;

    if (!state.translator || !state.targetCode) {
      return;
    }

    var targets = collectTargets(document.body);

    if (targets.length === 0) {
      return;
    }

    stopObserver();
    translateTargets(targets).then(function () {
      startObserver();
    });
  }

  /**
   * Watches the document for content added after load.
   */
  function startObserver() {
    if (!state.translator || !state.targetCode) {
      return;
    }

    if (!observer) {
      observer = new MutationObserver(function () {
        if (observerTimer) {
          window.clearTimeout(observerTimer);
        }

        observerTimer = window.setTimeout(translatePending, OBSERVER_DEBOUNCE);
      });
    }

    observer.observe(document.body, { childList: true, subtree: true });
  }
```

In `applyLanguage`, wrap the pass so the observer is never live while the widget writes. Replace the `.then(function (translator) { ... })` and the following `.then` with:

```javascript
      .then(function (translator) {
        state.translator = translator;
        state.targetCode = code;
        stopObserver();

        return translateTargets(collectTargets(document.body));
      })
      .then(function () {
        document.documentElement.lang = code;
        setCurrent(code);
        writeStoredLang(code);
        setBusy(false, '');
        startObserver();
      })
```

Note that `setCurrent(code)` also sets `state.targetCode`; setting it early here is deliberate, because `startObserver()` and `translatePending()` both refuse to run without it.

In `applySource`, stop watching before restoring:

```javascript
  function applySource() {
    closeList();
    stopObserver();
    restoreOriginals();
    state.translator = null;
    state.targetCode = null;
    document.documentElement.lang = config.source;
    setCurrent(config.source);
    clearStoredLang();
    setBusy(false, '');
  }
```

- [ ] **Step 4: Run the test and the linter to verify they pass**

Run: `npx playwright test tests/translate.spec.js --reporter=line -g "dynamic content"`
Expected: PASS (3 tests)

Run: `npm run lint`
Expected: exit 0

- [ ] **Step 5: Commit**

```bash
git add assets/js/translate-widget.js tests/translate.spec.js
git commit -m "feat: translate content added after page load"
```

---

### Task 7: Keyboard access and failure handling

**Files:**
- Modify: `assets/js/translate-widget.js`
- Modify: `assets/css/translate-widget.css`
- Test: `tests/translate.spec.js` (append a describe block)

**Interfaces:**
- Consumes: `state`, `openList`, `closeList`, `applyLanguage`, `applySource`, `setBusy` (Tasks 3-6).
- Produces, inside the IIFE:
  - `focusOption(index): void`
  - `selectOption(option): void`
  - Keyboard handlers on the toggle and the list.
  - A failure message on the status line when `Translator.create()` rejects.

- [ ] **Step 1: Write the failing test**

Append to `tests/translate.spec.js`:

```javascript
test.describe('BlueWorx on-page translation — keyboard and failures', () => {
  test.skip(isPlaceholder, 'No real staging/preview URL configured yet.');

  test('the switcher is fully operable from the keyboard', async ({ page }) => {
    await installTranslatorStub(page);
    await page.goto('/');

    const toggle = page.getByRole('button', { name: /Language/ });
    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    // Opening focuses the selected option (English); one ArrowDown reaches French.
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

    const toggle = page.getByRole('button', { name: /Language/ });
    await toggle.click();
    await page.locator('.blueworx-translate__option[data-lang="fr"]').click();

    await expect(page.locator('.blueworx-translate__status')).toContainText("Couldn't load");
    await expect(toggle).toHaveAttribute('aria-busy', 'false');
    await expect(toggle).toBeEnabled();
    await expect(page.locator('.blueworx-translate__current')).toHaveText('English');
    expect(await page.locator('body').innerText()).toContain(before.slice(0, 40));
    await expect(page.locator('html')).not.toHaveAttribute('lang', 'fr');
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx playwright test tests/translate.spec.js --reporter=line -g "keyboard and failures"`
Expected: FAIL — `Enter` on the toggle does open the list (a button's default click), but `ArrowDown` moves nothing and no failure message is shown.

- [ ] **Step 3: Add keyboard handling and the failure message**

In `assets/js/translate-widget.js`, add these functions directly above `buildWidget`:

```javascript
  /**
   * Moves focus to one option, clamped to the ends of the list.
   *
   * @param {number} index Desired option index.
   */
  function focusOption(index) {
    var options = state.list.querySelectorAll('.blueworx-translate__option');

    if (options.length === 0) {
      return;
    }

    var clamped = Math.max(0, Math.min(index, options.length - 1));
    options[clamped].focus();
  }

  /**
   * Returns the index of the currently focused option, or the selected one.
   *
   * @return {number} Option index.
   */
  function focusedOptionIndex() {
    var options = state.list.querySelectorAll('.blueworx-translate__option');

    for (var i = 0; i < options.length; i += 1) {
      if (options[i] === document.activeElement) {
        return i;
      }
    }

    for (var j = 0; j < options.length; j += 1) {
      if (options[j].getAttribute('aria-selected') === 'true') {
        return j;
      }
    }

    return 0;
  }

  /**
   * Acts on a chosen option.
   *
   * @param {Element} option Option element.
   */
  function selectOption(option) {
    if (!option) {
      return;
    }

    var code = option.getAttribute('data-lang');

    if (code === config.source) {
      applySource();
      return;
    }

    if (code === state.targetCode) {
      closeList();
      return;
    }

    applyLanguage(code);
  }
```

Replace the list click handler from Task 5 with one that reuses `selectOption`, and add the keyboard handlers — inside `buildWidget`, after the outside-click handler:

```javascript
    list.addEventListener('click', function (event) {
      selectOption(event.target.closest('.blueworx-translate__option'));
    });

    toggle.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        openList();
        focusOption(focusedOptionIndex());
      }
    });

    list.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        focusOption(focusedOptionIndex() + 1);
        return;
      }

      if (event.key === 'ArrowUp') {
        event.preventDefault();
        focusOption(focusedOptionIndex() - 1);
        return;
      }

      if (event.key === 'Home') {
        event.preventDefault();
        focusOption(0);
        return;
      }

      if (event.key === 'End') {
        event.preventDefault();
        focusOption(Number.MAX_SAFE_INTEGER);
        return;
      }

      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        selectOption(event.target.closest('.blueworx-translate__option'));
        return;
      }

      if (event.key === 'Escape') {
        event.preventDefault();
        closeList();
        state.toggle.focus();
      }
    });
```

Also update the toggle's own click handler so opening with the pointer or with `Enter` both land focus on the list — replace it with:

```javascript
    toggle.addEventListener('click', function () {
      if (state.list.hidden) {
        openList();
        focusOption(focusedOptionIndex());
      } else {
        closeList();
      }
    });
```

Finally, give `applyLanguage`'s `catch` a visible, non-technical message — replace it with:

```javascript
      .catch(function () {
        state.translator = null;
        state.targetCode = null;
        setBusy(false, "Couldn't load that language.");
      });
```

- [ ] **Step 4: Add the reduced-motion and status-error styling to `assets/css/translate-widget.css`**

Append:

```css
.blueworx-translate__option:focus {
  outline: 2px solid var(--bw-translate-accent);
  outline-offset: -2px;
}

@media (prefers-reduced-motion: reduce) {
  .blueworx-translate__toggle,
  .blueworx-translate__option {
    transition: none;
  }
}

@media (prefers-color-scheme: dark) {
  .blueworx-translate {
    --bw-translate-bg: #14202e;
    --bw-translate-fg: #f2f6fa;
    --bw-translate-border: #33465c;
    --bw-translate-accent: #7fb0ff;
  }
}
```

- [ ] **Step 5: Run the test and the linter to verify they pass**

Run: `npx playwright test tests/translate.spec.js --reporter=line -g "keyboard and failures"`
Expected: PASS (2 tests)

Run: `npm run lint`
Expected: exit 0

- [ ] **Step 6: Commit**

```bash
git add assets/js/translate-widget.js assets/css/translate-widget.css tests/translate.spec.js
git commit -m "feat: keyboard access and failure handling for the translation widget"
```

---

### Task 8: Release — version, changelog, docs, and the deployment zip

**Files:**
- Modify: `blueworx-labs-wordpress.php` (header `Version:` line 6, `BLUEWORX_LABS_VERSION` line 25)
- Modify: `package.json` (`version`)
- Modify: `readme.txt` (`Stable tag`, description bullet, one FAQ entry)
- Modify: `CHANGELOG.md` (new 1.37.0 section at the top, above `## [1.36.0]`)
- Modify: `tests/feature-toggles.spec.js` (the section-headings assertion)

**Interfaces:**
- Consumes: everything from Tasks 1-7.
- Produces: `dist/blueworx-labs-wordpress.zip` and `../blueworx-labs-wordpress.zip`.

- [ ] **Step 1: Add the Translation section to the shared toggle test**

In `tests/feature-toggles.spec.js`, in the test named `settings page shows grouped sections and a Comments toggle`, add after the `Content` heading assertion:

```javascript
    await expect(page.getByRole('heading', { name: 'Translation' })).toBeVisible();
```

- [ ] **Step 2: Run it to verify it passes**

Run: `npx playwright test tests/feature-toggles.spec.js --reporter=line`
Expected: PASS — Task 1 already added the section, so this assertion locks it in place.

- [ ] **Step 3: Bump the version in all four places**

`blueworx-labs-wordpress.php` line 6: ` * Version:           1.37.0`
`blueworx-labs-wordpress.php` line 25: `	define( 'BLUEWORX_LABS_VERSION', '1.37.0' );`
`package.json`: `"version": "1.37.0",`
`readme.txt`: `Stable tag:        1.37.0`

- [ ] **Step 4: Verify the versions agree**

Run: `npm run version:check`
Expected: `version:check OK — plugin header and package.json agree (1.37.0).`

- [ ] **Step 5: Write the changelog entry**

Insert directly above `## [1.36.0] - 2026-07-23` in `CHANGELOG.md`:

```markdown
## [1.37.0] - 2026-07-26

### Added
- **On-page translation** (`includes/translate.php`, `assets/js/translate-widget.js`,
  `assets/css/translate-widget.css`) — a new `translate` feature, on by default, that
  adds a floating language switcher to the front end and translates the page in the
  visitor's own browser via the Chrome built-in Translator API. Replaces the Weglot
  plugin on BlueWorx-managed sites at no recurring cost: no API key, no third-party
  request, and no translation stored on the server.
- A new **Translation** settings section with an inline detail panel: languages
  offered (validated against an allowlist, never including the site's own language),
  button position (four corners), button label, and a per-site "never translate" list
  of CSS selectors. `code`, `pre`, `textarea`, `[translate="no"]` and `.notranslate`
  are always skipped, as is the widget itself.
- Text nodes and the `alt`, `title`, `placeholder` and `aria-label` attributes are
  translated; the visitor's choice is remembered in `localStorage` and re-applied on
  later pages; returning to the original language restores every string in place with
  no reload. Content added after load (Elementor popups, AJAX) is picked up by a
  debounced `MutationObserver`.
- `tests/translate.spec.js` — 14 Playwright tests covering the settings panel, the
  config payload, capability detection, translation and exclusions, restore and
  persistence, dynamic content, keyboard operation, and a failed language load. The
  real on-device model cannot run in CI, so the specs drive a stubbed
  `window.Translator`.

### Notes
- **Chrome and Edge 138 or newer only.** In any other browser the switcher renders
  nothing at all rather than a control that cannot work.
- **Not an SEO feature.** There are no translated URLs and no `hreflang`; crawlers see
  the source language only. That is the deliberate trade for removing the licence cost.
```

- [ ] **Step 6: Document the feature in `readme.txt`**

Add to the `== Description ==` bullet list, after the comments/emails bullet:

```
* Offering visitors an on-page translation switcher that runs entirely in their own browser (Chrome and Edge 138+).
```

Add to `== Frequently Asked Questions ==`:

```
= How does the translation feature work? =
A floating language button is added to the front end. When a visitor picks a language, their own browser translates the page using Chrome's built-in on-device translator - there is no translation service, no API key and no cost. The first use of a language downloads a small model into the browser. In browsers without that API, such as Safari and Firefox, the button does not appear. Search engines are unaffected: they still see the original language only.
```

- [ ] **Step 7: Run the full check set**

Run: `npm run lint`
Expected: exit 0

Run: `npm run version:check`
Expected: OK at 1.37.0

Run: `npx playwright test --reporter=line`
Expected: the whole suite passes, including all 14 tests in `tests/translate.spec.js`. Any pre-existing failure unrelated to this feature must be reported, not fixed silently.

- [ ] **Step 8: Commit**

```bash
git add blueworx-labs-wordpress.php package.json readme.txt CHANGELOG.md tests/feature-toggles.spec.js
git commit -m "chore: release 1.37.0 — on-page translation"
```

- [ ] **Step 9: Build and verify the deployment zip**

```bash
npm run build
cp dist/blueworx-labs-wordpress.zip ../blueworx-labs-wordpress.zip
unzip -l ../blueworx-labs-wordpress.zip | head -20
```

Expected: every entry reads `blueworx-labs-wordpress/...` with **forward slashes**, nested one level, and `blueworx-labs-wordpress/blueworx-labs-wordpress.php` is present along with `assets/js/translate-widget.js` and `assets/css/translate-widget.css`. If any entry contains a backslash the zip is broken — rebuild with bsdtar (`/c/Windows/System32/tar.exe -a -c -f ../blueworx-labs-wordpress.zip -C dist blueworx-labs-wordpress`) and never with PowerShell `Compress-Archive`.

- [ ] **Step 10: Open the pull request**

```bash
git push -u origin add-page-translation
gh pr create --title "feat: on-page translation (Weglot replacement)" --body "$(cat <<'EOF'
## Summary
Adds a `translate` feature: a floating language switcher that translates the page in the visitor's own browser via the Chrome built-in Translator API. Replaces Weglot on BlueWorx-managed sites at no recurring cost — no API key, no third-party request, nothing stored server-side.

Spec: `docs/superpowers/specs/2026-07-26-on-page-translation-design.md`
Plan: `docs/superpowers/plans/2026-07-26-on-page-translation.md`

## Trade-offs, stated plainly
- **Chrome/Edge 138+ only.** Elsewhere the switcher renders nothing rather than a control that cannot work.
- **Not an SEO feature.** No translated URLs, no `hreflang` — crawlers see the source language only. This is the deliberate trade for dropping the licence.
- CI cannot run the on-device model, so the 14 Playwright tests drive a stubbed `window.Translator`.

## Verification
- `npm run lint` — clean
- `npm run version:check` — 1.37.0 across header, constant and package.json
- `npx playwright test` — full suite green

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Self-Review

**Spec coverage:** §1 goal → Tasks 1-7. §2 D1-D10 → D1/D2 Task 3 (`isSupported`), D3 Task 2-3, D4 Task 4, D5 Task 5, D6 Task 1 (`blueworx_translate_source_language`), D7 Task 1 (registry), D8 Task 3 (renders-nothing test), D9 Task 4 (`TRANSLATABLE_ATTRS`), D10 Task 5 (`restoreOriginals`). §3.1 registry → Task 1 Steps 4-5. §3.2 options table → Task 1 Step 3 (`blueworx_translate_save_settings`) and uninstall in Step 6. §3.3 language maps → Task 1 Step 3. §3.4 enqueue → Task 2. §4.1 detection → Task 3. §4.2 translating → Task 4, with `monitor`/`downloadprogress` in `applyLanguage`. §4.3 exclusions → Task 4 (`BASE_EXCLUDE`, `excludeSelector`). §4.4 dynamic content → Task 6. §4.5 accessibility → Task 3 (roles) + Task 7 (keyboard, reduced motion). §5 error table → Task 3 (absent API, all-unavailable, one-unavailable), Task 4 (per-node failure), Task 5 (stale stored language), Task 7 (create rejection). §6 testing → the eight listed cases map onto Tasks 1-7's specs. §7 versioning → Task 8. §8 non-goals → nothing in the plan implements them.

**Placeholder scan:** no TBD/TODO; every code step carries the code; every test step carries the assertions and the command with its expected result.

**Type consistency:** `state.targetCode` is set by `setCurrent()` and explicitly in `applyLanguage` before `startObserver()` reads it, and cleared in `applySource()` and in `applyLanguage`'s `catch` — checked in all six call sites. `collectTargets()` returns `{node, attr, text}` and `writeTarget()`/`translateTargets()` consume exactly those keys. `recordOriginal(node, attr, value)` and `restoreOriginals()` use the same `''`-for-text-content convention. `blueworx_translate_*` PHP names are identical between their definitions in Task 1 and their call sites in Tasks 1-2. The stub's `installTranslatorStub` options (`unavailable`, `failCreate`) match its two consumers in Tasks 3, 5 and 7.
