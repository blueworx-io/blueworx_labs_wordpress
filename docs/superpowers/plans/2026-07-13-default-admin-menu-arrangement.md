# Default Admin-Menu Arrangement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the WordPress admin menu a sensible default arrangement (BlueWorx pinned below Dashboard, only Posts/Media/Pages/Users kept visible, everything else core moved to More, plugin items below the defaults, all length-then-alphabetically sorted) that an admin can override permanently via the existing Edit Menu page.

**Architecture:** Add a request-scoped computed-default layer, gated by a new `blueworx_admin_menu_customized` flag option, inside the three existing menu-state getters in `includes/admin-menu-order.php`. Because every downstream consumer (the `menu_order` filter, the More/visibility builder, and the Edit Menu render) already reads through those getters, injecting defaults there needs no new plumbing. A versioned migration marks already-arranged sites as customised so their menus are not clobbered.

**Tech Stack:** PHP 8.0+ (WordPress plugin), Playwright integration tests (skip on placeholder URL, run against real staging).

## Global Constraints

- **Requires PHP:** 8.0 — use only 8.0-compatible syntax.
- **Text domain:** `blueworx-labs-wordpress` for every translatable string.
- **Coding standard:** WordPress Coding Standards (WPCS), enforced by `composer run` PHPCS (`phpcs.xml.dist`). Match the surrounding file's style: Yoda conditions, `array()` long syntax, tab indentation, full-sentence docblocks with `@param`/`@return`.
- **Direct `$menu`/`$submenu` mutation** must keep the existing `phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited` justification comments; do not add new mutations of those globals in this work (we only read `$menu`).
- **Version bump required on the PR:** 1.9.0 → **1.10.0** (minor) in all three places (plugin header, `BLUEWORX_LABS_VERSION`, `package.json`), kept in sync (`npm run version:check` must pass), plus a CHANGELOG entry.
- **New functionality requires a Playwright test** (CI guardrail).
- **Sort rule (verbatim from spec):** ascending by title length (`mb_strlen` of the visible label), tiebreak case-insensitive alphabetical (A–Z).
- **Keep-visible set (verbatim):** `index.php`, `blueworx-labs-wordpress`, `edit.php`, `upload.php`, `edit.php?post_type=page`, `users.php`.
- **Pinned order (verbatim):** Dashboard (`index.php`) first, BlueWorx (`blueworx-labs-wordpress`) second, More (`blueworx-menu-toggle`) last.

---

## File Structure

All changes are edits to existing files — no new source files.

- `includes/admin-menu-order.php` — add the customised-flag helper, the core-slug and keep-slug lists, the `blueworx_compute_default_admin_menu_arrangement()` computation, and the default branch inside the three getters. (Tasks 1–2)
- `includes/admin-settings.php` — set the customised flag in `blueworx_save_edit_menu_page()`. (Task 3)
- `includes/upgrade.php` — add migration v3 marking already-arranged sites as customised. (Task 4)
- `tests/admin-menu-defaults.spec.js` — **new** Playwright spec verifying the default arrangement and that saving freezes it. (Task 5)
- `blueworx-labs-wordpress.php`, `package.json`, `CHANGELOG.md` — version bump + changelog. (Task 5)

---

## Task 1: Default-arrangement helpers and computation

**Files:**
- Modify: `includes/admin-menu-order.php` (add functions after `blueworx_get_locked_admin_menu_items()`, around line 43)

**Interfaces:**
- Consumes: the live `$menu` global; existing `blueworx_get_locked_admin_menu_items()`, `blueworx_get_default_admin_menu_order()`.
- Produces:
  - `blueworx_admin_menu_is_customized(): bool`
  - `blueworx_get_core_admin_menu_slugs(): array` — indexed array of core slug strings
  - `blueworx_get_default_visible_admin_menu_slugs(): array` — indexed array of keep slug strings
  - `blueworx_compute_default_admin_menu_arrangement(): array` — `array( 'order' => string[], 'toggled' => string[], 'hidden' => string[] )`

- [ ] **Step 1: Write the failing test**

Add a new Playwright spec file `tests/admin-menu-defaults.spec.js` (full content lands in Task 5; for now create it with a single default-order assertion so there is a red to chase). Create the file with:

```javascript
import { test, expect } from '@playwright/test';

const baseURL =
  process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || 'https://staging.placeholder.blueworx.io';
const isPlaceholder = /placeholder/i.test(baseURL);
const ADMIN_USER = process.env.WP_ADMIN_USER;
const ADMIN_PASS = process.env.WP_ADMIN_PASS;

async function login(page) {
  await page.goto('/wp-admin/');
  if (await page.locator('#user_login').count()) {
    await page.fill('#user_login', ADMIN_USER);
    await page.fill('#user_pass', ADMIN_PASS);
    await page.click('#wp-submit');
  }
}

test.describe('BlueWorx default admin-menu arrangement', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('BlueWorx sits directly below Dashboard in the admin menu', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/');
    const topLevel = page.locator('#adminmenu > li.menu-top:visible');
    const first = await topLevel.nth(0).innerText();
    const second = await topLevel.nth(1).innerText();
    expect(first).toContain('Dashboard');
    expect(second).toContain('BlueWorx');
  });
});
```

- [ ] **Step 2: Run test to verify it fails (or skips locally)**

Run: `npx playwright test tests/admin-menu-defaults.spec.js`
Expected: locally **SKIP** (placeholder URL — no staging creds). Against staging without this feature it would **FAIL** because BlueWorx is not yet pinned second by default. This is the honest constraint of an integration-only harness; proceed on the skip.

- [ ] **Step 3: Add the customised-flag and list helpers**

In `includes/admin-menu-order.php`, immediately after `blueworx_get_locked_admin_menu_items()` (after line 43), add:

```php
/**
 * Checks whether an admin has saved a custom menu arrangement.
 *
 * Until the Edit Menu page is saved once, the plugin serves a computed default
 * arrangement instead of the stored options.
 *
 * @return bool True when a custom arrangement has been saved.
 */
function blueworx_admin_menu_is_customized() {
	return '1' === get_option( 'blueworx_admin_menu_customized', '0' );
}

/**
 * Gets the canonical top-level slugs registered by WordPress core.
 *
 * Used to tell core menu items (moved to More by default) apart from
 * plugin-added items (kept visible below the defaults).
 *
 * @return array Core top-level menu slugs.
 */
function blueworx_get_core_admin_menu_slugs() {
	return array(
		'index.php',
		'edit.php',
		'upload.php',
		'edit.php?post_type=page',
		'edit-comments.php',
		'themes.php',
		'plugins.php',
		'users.php',
		'tools.php',
		'options-general.php',
		'profile.php',
		'link-manager.php',
	);
}

/**
 * Gets the top-level slugs shown in the main menu by default.
 *
 * @return array Keep-visible menu slugs.
 */
function blueworx_get_default_visible_admin_menu_slugs() {
	return array(
		'index.php',
		'blueworx-labs-wordpress',
		'edit.php',
		'upload.php',
		'edit.php?post_type=page',
		'users.php',
	);
}
```

- [ ] **Step 4: Add the computation function**

Directly after the helpers from Step 3, add:

```php
/**
 * Computes the default admin menu arrangement from the live menu.
 *
 * Dashboard is pinned first and BlueWorx second; the remaining keep-visible
 * items follow (sorted by title length, then alphabetically), then plugin
 * items (same sort), then the core items moved into More (same sort). Nothing
 * is hidden by default. Falls back to the static default order when the menu
 * global is not yet populated.
 *
 * @return array {
 *     @type array $order   Ordered top-level slugs.
 *     @type array $toggled Slugs moved into More.
 *     @type array $hidden  Slugs hidden (always empty by default).
 * }
 */
function blueworx_compute_default_admin_menu_arrangement() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	global $menu;

	$locked = blueworx_get_locked_admin_menu_items();
	$labels = array();

	foreach ( (array) $menu as $menu_item ) {
		$slug  = isset( $menu_item[2] ) ? (string) $menu_item[2] : '';
		$label = isset( $menu_item[0] ) ? trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $menu_item[0] ) ) ) : '';

		if ( '' === $slug || 0 === strpos( $slug, 'separator' ) || in_array( $slug, $locked, true ) ) {
			continue;
		}

		$labels[ $slug ] = $label;
	}

	if ( empty( $labels ) ) {
		$cache = array(
			'order'   => blueworx_get_default_admin_menu_order(),
			'toggled' => array(),
			'hidden'  => array(),
		);

		return $cache;
	}

	$present = array_keys( $labels );
	$keep    = blueworx_get_default_visible_admin_menu_slugs();
	$core    = blueworx_get_core_admin_menu_slugs();

	$pinned_top = array();
	foreach ( array( 'index.php', 'blueworx-labs-wordpress' ) as $pinned_slug ) {
		if ( in_array( $pinned_slug, $present, true ) ) {
			$pinned_top[] = $pinned_slug;
		}
	}

	$keep_rest = array();
	$plugins   = array();
	$toggled   = array();

	foreach ( $present as $slug ) {
		if ( in_array( $slug, $pinned_top, true ) ) {
			continue;
		}

		if ( in_array( $slug, $keep, true ) ) {
			$keep_rest[] = $slug;
		} elseif ( in_array( $slug, $core, true ) ) {
			$toggled[] = $slug;
		} else {
			$plugins[] = $slug;
		}
	}

	$sort = function ( $slugs ) use ( $labels ) {
		usort(
			$slugs,
			function ( $a, $b ) use ( $labels ) {
				$len_a = mb_strlen( $labels[ $a ] );
				$len_b = mb_strlen( $labels[ $b ] );

				if ( $len_a !== $len_b ) {
					return $len_a - $len_b;
				}

				return strcasecmp( $labels[ $a ], $labels[ $b ] );
			}
		);

		return $slugs;
	};

	$keep_rest = $sort( $keep_rest );
	$plugins   = $sort( $plugins );
	$toggled   = $sort( $toggled );

	$cache = array(
		'order'   => array_values( array_merge( $pinned_top, $keep_rest, $plugins, $toggled ) ),
		'toggled' => array_values( $toggled ),
		'hidden'  => array(),
	);

	return $cache;
}
```

- [ ] **Step 5: Lint the changed file**

Run: `composer run phpcs -- includes/admin-menu-order.php` (or `vendor/bin/phpcs includes/admin-menu-order.php`)
Expected: no new errors on the added functions. Fix any WPCS spacing/Yoda issues inline before committing.

- [ ] **Step 6: Commit**

```bash
git add includes/admin-menu-order.php tests/admin-menu-defaults.spec.js
git commit -m "feat: compute default admin-menu arrangement helpers"
```

---

## Task 2: Wire computed defaults into the state getters

**Files:**
- Modify: `includes/admin-menu-order.php` (`blueworx_get_hidden_admin_menu_items` ~line 50, `blueworx_get_toggled_admin_menu_items` ~line 65, `blueworx_get_saved_admin_menu_order` ~line 80)

**Interfaces:**
- Consumes: `blueworx_admin_menu_is_customized()`, `blueworx_compute_default_admin_menu_arrangement()` (Task 1).
- Produces: unchanged public signatures — the three getters now return computed defaults when not customised.

- [ ] **Step 1: Add the default branch to the hidden getter**

In `blueworx_get_hidden_admin_menu_items()`, insert at the very top of the function body (before `$hidden = get_option(...)`):

```php
	if ( ! blueworx_admin_menu_is_customized() ) {
		$arrangement = blueworx_compute_default_admin_menu_arrangement();

		return $arrangement['hidden'];
	}
```

- [ ] **Step 2: Add the default branch to the toggled getter**

In `blueworx_get_toggled_admin_menu_items()`, insert at the very top of the function body (before `$toggled = get_option(...)`):

```php
	if ( ! blueworx_admin_menu_is_customized() ) {
		$arrangement = blueworx_compute_default_admin_menu_arrangement();

		return array_values( array_diff( $arrangement['toggled'], blueworx_get_locked_admin_menu_items() ) );
	}
```

- [ ] **Step 3: Add the default branch to the order getter**

In `blueworx_get_saved_admin_menu_order()`, insert at the very top of the function body (before `$saved_order = get_option(...)`):

```php
	if ( ! blueworx_admin_menu_is_customized() ) {
		$arrangement = blueworx_compute_default_admin_menu_arrangement();

		return $arrangement['order'];
	}
```

- [ ] **Step 4: Run the Playwright spec**

Run: `npx playwright test tests/admin-menu-defaults.spec.js`
Expected: locally **SKIP** (placeholder). Against staging it now **PASSES** — BlueWorx is pinned second and the default columns are computed.

- [ ] **Step 5: Lint**

Run: `composer run phpcs -- includes/admin-menu-order.php`
Expected: no new errors.

- [ ] **Step 6: Commit**

```bash
git add includes/admin-menu-order.php
git commit -m "feat: serve computed defaults from menu-state getters"
```

---

## Task 3: Freeze the arrangement on first save

**Files:**
- Modify: `includes/admin-settings.php` (`blueworx_save_edit_menu_page()`, ~lines 82-85)

**Interfaces:**
- Consumes: nothing new.
- Produces: sets `blueworx_admin_menu_customized = '1'` whenever the Edit Menu form is saved.

- [ ] **Step 1: Set the customised flag on save**

In `blueworx_save_edit_menu_page()`, immediately after the three existing `update_option()` calls (after the `blueworx_toggled_admin_menu_items` update, before the `set_transient()` line), add:

```php
	update_option( 'blueworx_admin_menu_customized', '1' );
```

- [ ] **Step 2: Lint**

Run: `composer run phpcs -- includes/admin-settings.php`
Expected: no new errors.

- [ ] **Step 3: Commit**

```bash
git add includes/admin-settings.php
git commit -m "feat: mark menu arrangement customised on save"
```

---

## Task 4: Migration — protect already-arranged sites

**Files:**
- Modify: `includes/upgrade.php` (`blueworx_get_labs_db_version()` line 20-22; add new function; `blueworx_run_pending_labs_migrations()` lines 195-212)

**Interfaces:**
- Consumes: `blueworx_get_admin_menu_slug_value_options()` (existing).
- Produces: `blueworx_migrate_mark_admin_menu_customized(): void`; bumped DB version `3`.

- [ ] **Step 1: Bump the migration version**

In `blueworx_get_labs_db_version()`, change the return value from `2` to `3`:

```php
function blueworx_get_labs_db_version() {
	return 3;
}
```

- [ ] **Step 2: Add the migration function**

After `blueworx_migrate_admin_menu_slug_labs_wordpress()` (after line 186), add:

```php
/**
 * Marks sites that already arranged their admin menu as customised, so the new
 * computed default arrangement does not overwrite an existing arrangement.
 *
 * A site counts as arranged if any of the three menu-state options holds a
 * non-empty array. Sites with no saved arrangement are left unmarked and adopt
 * the new defaults.
 *
 * @return void
 */
function blueworx_migrate_mark_admin_menu_customized() {
	foreach ( blueworx_get_admin_menu_slug_value_options() as $option_name ) {
		$value = get_option( $option_name, array() );

		if ( is_array( $value ) && ! empty( $value ) ) {
			update_option( 'blueworx_admin_menu_customized', '1' );

			return;
		}
	}
}
```

- [ ] **Step 3: Wire the migration into the runner**

In `blueworx_run_pending_labs_migrations()`, after the `if ( $stored_version < 2 ) { ... }` block (after line 209), add:

```php
	if ( $stored_version < 3 ) {
		blueworx_migrate_mark_admin_menu_customized();
	}
```

- [ ] **Step 4: Lint**

Run: `composer run phpcs -- includes/upgrade.php`
Expected: no new errors.

- [ ] **Step 5: Commit**

```bash
git add includes/upgrade.php
git commit -m "feat: migrate existing menu arrangements to customised flag"
```

---

## Task 5: Full test coverage, version bump, changelog

**Files:**
- Modify: `tests/admin-menu-defaults.spec.js` (expand from the Task 1 stub)
- Modify: `blueworx-labs-wordpress.php` (lines 6 and 25), `package.json` (line 3), `CHANGELOG.md` (top)

**Interfaces:**
- Consumes: the completed feature (Tasks 1–4).
- Produces: the deliverable test suite + version 1.10.0.

- [ ] **Step 1: Expand the Playwright spec**

Replace the body of the `test.describe(...)` block in `tests/admin-menu-defaults.spec.js` so it covers the default order, the More grouping, and that saving freezes the arrangement. Full file:

```javascript
import { test, expect } from '@playwright/test';

const baseURL =
  process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || 'https://staging.placeholder.blueworx.io';
const isPlaceholder = /placeholder/i.test(baseURL);
const ADMIN_USER = process.env.WP_ADMIN_USER;
const ADMIN_PASS = process.env.WP_ADMIN_PASS;

const EDIT_MENU_PATH = '/wp-admin/admin.php?page=blueworx-edit-menu';

async function login(page) {
  await page.goto('/wp-admin/');
  if (await page.locator('#user_login').count()) {
    await page.fill('#user_login', ADMIN_USER);
    await page.fill('#user_pass', ADMIN_PASS);
    await page.click('#wp-submit');
  }
}

test.describe('BlueWorx default admin-menu arrangement', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('BlueWorx sits directly below Dashboard in the admin menu', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/');
    const topLevel = page.locator('#adminmenu > li.menu-top:visible');
    const first = await topLevel.nth(0).innerText();
    const second = await topLevel.nth(1).innerText();
    expect(first).toContain('Dashboard');
    expect(second).toContain('BlueWorx');
  });

  test('More is the last visible top-level item', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/');
    const visible = page.locator('#adminmenu > li.menu-top:visible');
    const last = await visible.last().innerText();
    expect(last).toContain('More');
  });

  test('Edit Menu page shows the default split with items in the More column', async ({ page }) => {
    await login(page);
    await page.goto(EDIT_MENU_PATH);
    const moreColumn = page.locator('.blueworx-menu-order-list[data-blueworx-menu-section="toggle"]');
    // At least one core item (e.g. Settings/Tools/Appearance) defaults into More.
    await expect(moreColumn.locator('.blueworx-menu-order-item')).not.toHaveCount(0);
    // Hidden column is empty by default.
    const hiddenColumn = page.locator('.blueworx-menu-order-list[data-blueworx-menu-section="hidden"]');
    await expect(hiddenColumn.locator('.blueworx-menu-order-item')).toHaveCount(0);
  });

  test('saving the Edit Menu page freezes the arrangement', async ({ page }) => {
    await login(page);
    await page.goto(EDIT_MENU_PATH);
    await page.getByRole('button', { name: 'Save Menu Settings' }).click();
    await expect(page.locator('.notice-success').first()).toContainText('Menu settings saved');
    // After a save the same split still renders (defaults were persisted, not reset).
    await page.goto(EDIT_MENU_PATH);
    const moreColumn = page.locator('.blueworx-menu-order-list[data-blueworx-menu-section="toggle"]');
    await expect(moreColumn.locator('.blueworx-menu-order-item')).not.toHaveCount(0);
  });
});
```

- [ ] **Step 2: Run the full spec**

Run: `npx playwright test tests/admin-menu-defaults.spec.js`
Expected: locally all **SKIP** (placeholder). Against staging all **PASS**.

- [ ] **Step 3: Lint the JS**

Run: `npm run lint`
Expected: no errors in `tests/` is not linted by default (`eslint assets/js`), but run it to confirm nothing in `assets/js` regressed. Expected: clean.

- [ ] **Step 4: Bump the version in all three places**

`blueworx-labs-wordpress.php` line 6: `* Version:           1.10.0`
`blueworx-labs-wordpress.php` line 25: `define( 'BLUEWORX_LABS_VERSION', '1.10.0' );`
`package.json` line 3: `"version": "1.10.0",`

Run: `npm run version:check`
Expected: PASS (all versions in sync).

- [ ] **Step 5: Add the changelog entry**

Insert at the top of `CHANGELOG.md` (after the intro block, above `## [1.9.0]`):

```markdown
## [1.10.0] - 2026-07-13

### Added
- **Default admin-menu arrangement.** Out of the box the admin menu now pins
  BlueWorx directly below Dashboard, keeps only Posts, Media, Pages, and Users
  visible, moves every other core WordPress item into **More**, and leaves
  plugin-added items visible below the defaults. All items are ordered by title
  length (shortest first, alphabetical tiebreak); More stays last. Saving the
  Edit Menu page freezes the arrangement, after which saved choices always win.
  Sites that had already arranged their menu are detected on upgrade and keep
  their existing layout.
```

- [ ] **Step 6: Commit**

```bash
git add tests/admin-menu-defaults.spec.js blueworx-labs-wordpress.php package.json CHANGELOG.md
git commit -m "test: cover default menu arrangement; bump to 1.10.0"
```

---

## Self-Review

**Spec coverage:**
- BlueWorx below Dashboard → Task 1 `$pinned_top` + Task 2 order getter; asserted in Task 5 test 1. ✓
- More always last → existing `blueworx_admin_menu_order()` append (unchanged) + Task 5 test 2. ✓
- Only BlueWorx/Posts/Media/Pages/Users visible (plus Dashboard) → `blueworx_get_default_visible_admin_menu_slugs()` Task 1. ✓
- Other core items to More → `toggled` partition Task 1 + toggled getter Task 2. ✓
- Plugin items not hidden, below defaults → `$plugins` group placed after `$keep_rest` Task 1. ✓
- Length then A–Z sort → `$sort` closure Task 1. ✓
- Overridable defaults + freeze on save → customised flag Tasks 1/2/3. ✓
- Don't clobber existing sites → migration v3 Task 4. ✓
- Version + changelog → Task 5. ✓

**Placeholder scan:** No TBD/TODO; every code step shows full code. The only softness is the integration-test red/skip caveat, which is stated honestly rather than hidden. ✓

**Type consistency:** `blueworx_compute_default_admin_menu_arrangement()` returns keys `order`/`toggled`/`hidden` used identically in Tasks 1–2. `blueworx_admin_menu_is_customized()` and `blueworx_admin_menu_customized` option name spelled consistently across Tasks 1–4. Section `data-blueworx-menu-section` values (`toggle`, `hidden`) match the render markup in `admin-settings.php`. ✓
