# Admin Re-skin Refinements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring the wp-admin sidebar to ~99% fidelity with the v2 design export (semantic groups, icons, badges, Log Out), fix three shipped bugs, and give settings screens card containers.

**Architecture:** The sidebar stops being a pure-CSS re-skin. Four new PHP modules compute group assignment, icons and badges from the live `$menu` global, and inject inert heading pseudo-items. The "More" menu is retired and its stored state migrated. The Edit Menu screen is rebuilt on the native HTML5 drag API. Containers are pure CSS.

**Tech Stack:** WordPress plugin (PHP 7.4+), vanilla JS (no jQuery UI), CSS custom properties, Playwright for E2E.

**Spec:** `docs/superpowers/specs/2026-07-15-admin-reskin-refinements-design.md` — read it first. Where this plan and the spec disagree, the spec wins; report the conflict.

---

## Global Constraints

- **Branch:** `admin-reskin-refinements`. Never commit to `main`.
- **Version: DO NOT BUMP.** The branch is already at `1.12.0` (commit `81d0599`); `main` is `1.11.0`. The bump guardrail is already satisfied. Extend the **existing** `## [1.12.0] - 2026-07-15` section in `CHANGELOG.md` — never add a second one.
- **No new dependencies.** `approved-deps.json` governs. jQuery UI `sortable` is being **removed**, not replaced with another library.
- **PHP style:** WordPress Coding Standards, tabs for indentation, Yoda conditions, `snake_case` with a `blueworx_` prefix. Every user-facing string wrapped in `__()` / `esc_html__()` with the `blueworx-labs-wordpress` text domain.
- **Escaping:** every echo escaped (`esc_html`, `esc_attr`, `esc_url`). SVG output uses `wp_kses` with an explicit SVG allowlist — never `echo` raw markup from a variable.
- **Direct `$menu` / `$submenu` mutation** requires the `phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited` comment with a justification, matching the existing style in `admin-menu-order.php:414`.
- **Feature flags:** `admin_theme` owns group structure/headings/icons/badges/Log Out. `menu_editor` owns user overrides. See spec §1's behaviour matrix.
- **Lint once, at the end.** Never loop lint → fix → lint. Present findings to Luke; only fix on approval.
- **Design tokens are fixed values from the export.** Do not invent colours. Exact values are given in each task.

### Test harness reality — read before Task 1

There is **no PHP unit test framework** (`composer.json` has phpcs only). **All tests are Playwright E2E against a live WordPress**, and they `test.skip(...)` unless all three of these are set:

```bash
export PLAYWRIGHT_BASE_URL="https://<the staging site>"
export WP_ADMIN_USER="<admin username>"
export WP_ADMIN_PASS="<admin password>"
```

**Consequence:** the red-green loop needs the plugin installed on a reachable WordPress. Without the env vars the tests report as *skipped*, not *failed* — a skipped test is **not** a red test and must never be treated as one.

Run tests with:
```bash
npx playwright test tests/<file> -g "<test name>"
```

**If you cannot reach a live WordPress:** stop and report it rather than writing tests you never saw fail. Say which tasks you could not verify. Do not claim a task passes on the strength of a skip.

---

## File Structure

**New:**

| File | Responsibility |
|---|---|
| `includes/admin-menu-groups.php` | Group keys/labels, default assignment rules, resolving slug → group. Pure logic, no output. |
| `includes/admin-menu-icons.php` | Slug → inline SVG map, and the `wp_kses` SVG allowlist. |
| `includes/admin-menu-badges.php` | Per-request count computation. |
| `assets/js/admin-menu-editor.js` | Edit Menu native drag + keyboard reorder. Replaces `admin-menu-order.js`. |
| `assets/css/admin-menu-editor.css` | Edit Menu screen styling. Loads on that screen regardless of `admin_theme`. |

**Modified:**

| File | Change |
|---|---|
| `includes/admin-menu-order.php` | Retire More; rewire ordering to groups. |
| `includes/admin-settings.php` | Rebuild Edit Menu render + save handler. |
| `includes/admin-assets.php` | Swap the editor JS/CSS enqueue. |
| `includes/upgrade.php` | Migration v4 (toggled → groups). |
| `includes/admin-theme.php` | Render group headings, icons, badges, Log Out. |
| `assets/css/admin-theme.css` | `box-sizing`, hover/active, headings, badges, Log Out, `.form-table` cards, Enhancements measure. |
| `blueworx-labs-wordpress.php` | `require_once` the three new modules. |
| `CHANGELOG.md`, `readme.txt` | Extend the existing 1.12.0 entry + Upgrade Notice. |
| `tests/admin-theme.spec.js` | Sidebar, bug regressions, containers. |
| `tests/admin-menu-defaults.spec.js` | Groups, migration, Edit Menu. |

**Deleted:** `assets/js/admin-menu-order.js` (replaced by `admin-menu-editor.js`).

---

## Phase 1 — Shipped bugs (CSS only, no PHP, independently shippable)

### Task 1: Fix the jutt (`box-sizing`)

**Files:**
- Modify: `assets/css/admin-theme.css:70-86` (`.bw-brand`), `:583-613` (783px block), `:616-643` (961px block)
- Test: `tests/admin-theme.spec.js`

**Interfaces:**
- Consumes: nothing.
- Produces: a `[class^="bw-"], [class*=" bw-"] { box-sizing: border-box }` rule that **every later task's `.bw-*` markup depends on**. Tasks 7–10 and 12–13 assume border-box.

**Background:** `.bw-brand` is `content-box`; `width: var(--bw-sidebar-w)` (232px) + `padding: 0 12px` renders 256px against `.bw-topbar { left: 232px }`, and `z-index: 9991` > `9990` paints 24px of charcoal over the top bar. See spec §5.

- [ ] **Step 1: Write the failing test**

Add to `tests/admin-theme.spec.js`, inside the existing `test.describe('BlueWorx admin theme', ...)`:

```js
  test('regression: brand block never overhangs the top bar (the jutt)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    // Expanded: the brand's rendered box must match the sidebar's exactly.
    const brand = await page.locator('.bw-brand').boundingBox();
    const menu = await page.locator('#adminmenuwrap').boundingBox();
    expect(brand.width).toBeCloseTo(menu.width, 0);

    // And it must not cross into the top bar.
    const topbar = await page.locator('.bw-topbar').boundingBox();
    expect(brand.x + brand.width).toBeLessThanOrEqual(topbar.x + 0.5);

    // Folded: same guarantee (this state had the same 24px overhang).
    await page.locator('#collapse-button').click();
    await expect(page.locator('body.folded')).toHaveCount(1);
    const fBrand = await page.locator('.bw-brand').boundingBox();
    const fMenu = await page.locator('#adminmenuwrap').boundingBox();
    expect(fBrand.width).toBeCloseTo(fMenu.width, 0);

    // The brand mark must still be visible when folded, not clipped to nothing.
    const mark = await page.locator('.bw-brand-mark').boundingBox();
    expect(mark.width).toBeGreaterThan(20);

    await page.locator('#collapse-button').click(); // restore
  });
```

- [ ] **Step 2: Run it and watch it fail**

```bash
npx playwright test tests/admin-theme.spec.js -g "the jutt"
```
Expected: **FAIL** — brand width 256 vs sidebar 232.
If it reports *skipped*, your env vars are missing. Fix that first; a skip is not a red test.

- [ ] **Step 3: Add the scoped box-sizing rule**

In `assets/css/admin-theme.css`, immediately after the `:root { … }` block (around line 35), add:

```css
/* ─── Box model ───
   Scoped to BlueWorx components only. The design export uses a global
   `* { box-sizing: border-box }`; applying that in wp-admin would restyle
   WordPress's own layout and every third-party plugin page. Without this,
   .bw-brand's 12px padding is ADDED to its 232px width and the block overhangs
   the top bar by 24px. */
[class^="bw-"],
[class*=" bw-"] {
	box-sizing: border-box;
}
```

- [ ] **Step 4: Fix the folded state's padding**

With border-box, `width: 36px` leaves a 12px content box, which would clip the 34px `.bw-brand-mark`. In the `@media only screen and (min-width: 783px)` block, replace:

```css
	.bw-brand {
		display: flex;
		width: 36px;
	}
```

with:

```css
	/* Folded: WordPress's folded menu is 36px. With border-box the 12px side
	   padding would leave a 12px content box and clip the 34px brand mark, so
	   the folded state drops the padding and centres the mark instead. */
	.bw-brand {
		display: flex;
		width: 36px;
		padding: 0;
		justify-content: center;
	}
```

- [ ] **Step 5: Restore padding when expanded**

In the `@media only screen and (min-width: 961px)` block, replace:

```css
	body:not(.folded) .bw-brand {
		width: var(--bw-sidebar-w);
	}
```

with:

```css
	body:not(.folded) .bw-brand {
		width: var(--bw-sidebar-w);
		padding: 0 12px;
		justify-content: flex-start;
	}
```

- [ ] **Step 6: Run the test — expect PASS**

```bash
npx playwright test tests/admin-theme.spec.js -g "the jutt"
```
Expected: **PASS**.

- [ ] **Step 7: Check the other `.bw-*` elements did not shift**

The new rule touches `.bw-topbar`, `.bw-stat-grid`, `.bw-user-menu`, `.bw-brand-mark`. Run the existing suite:

```bash
npx playwright test tests/admin-theme.spec.js
```
Expected: **all PASS**. Then look at the Dashboard, Enhancements and Cache screens at 1280px and confirm nothing shrank. `.bw-topbar` sets `left` + `right` with `width: auto`, so it is provably unaffected — but the stat tiles are not, so look at them.

- [ ] **Step 8: Commit**

```bash
git add assets/css/admin-theme.css tests/admin-theme.spec.js
git commit -m "fix: brand block overhanging the top bar (the jutt)

.bw-brand was content-box, so its 12px side padding was added to its 232px
width, rendering 256px against a top bar starting at left: 232px. With
z-index 9991 over the top bar's 9990, those 24px of charcoal painted over
it. The folded state (783-960px) carried the same overhang at 36px+24px.

Fixes box-sizing: border-box scoped to the .bw-* classes rather than
.bw-brand alone, so the .bw-* markup added later in this branch cannot
inherit the same trap. Scoped rather than the export's global * rule, which
would disturb WordPress's own layout.

Folded state drops to padding: 0 with the mark centred, since border-box
would otherwise clip the 34px mark inside a 12px content box.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Fix the sidebar hover/active colour overlap

**Files:**
- Modify: `assets/css/admin-theme.css:296-324`
- Test: `tests/admin-theme.spec.js`

**Interfaces:**
- Consumes: nothing.
- Produces: the canonical sidebar item state colours. Task 10 styles headings/badges **around** these and must not re-declare them.

**Background:** hover paints `rgba(255,255,255,.06)` on the `li`; active paints `rgba(79,70,229,.22)` on the nested `a`. On the current item both composite into a third colour. The export's real value is an **opaque `#4F46E5`**. See spec §4.

- [ ] **Step 1: Write the failing test**

```js
  test('regression: hovering the current item does not shift its colour', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    const currentLink = page.locator('#adminmenu li.current > a.menu-top').first();
    const before = await currentLink.evaluate((el) => getComputedStyle(el).backgroundColor);

    await currentLink.hover();
    const after = await currentLink.evaluate((el) => getComputedStyle(el).backgroundColor);

    // Hover must not composite a second translucent layer over the active pill.
    expect(after).toBe(before);
    // And the active pill is the design's opaque indigo, not a 22% wash.
    expect(before).toBe('rgb(79, 70, 229)');
  });
```

- [ ] **Step 2: Run it and watch it fail**

```bash
npx playwright test tests/admin-theme.spec.js -g "hovering the current item"
```
Expected: **FAIL** — background is `rgba(79, 70, 229, 0.22)`, not `rgb(79, 70, 229)`.

- [ ] **Step 3: Rewrite the state rules**

Replace `assets/css/admin-theme.css:296-324` (the hover block through the active-label block) with:

```css
/* ─── Sidebar item states ───
   All state colour lives on the anchor. The li carries layout only.
   Previously hover painted the li and the active pill painted the a, so on the
   current item two translucent layers composited into a third colour.
   Values are the v2 export's: idle rgba(255,255,255,.62), hover
   rgba(255,255,255,.06)/#fff, active #4F46E5 (opaque) /#fff/600. */

/* Hover — declared BEFORE active, and never applied to the current item, so the
   active pill always wins. */
#adminmenu li.menu-top:not(.current):not(.wp-has-current-submenu) > a.menu-top:hover,
#adminmenu li.menu-top:not(.current):not(.wp-has-current-submenu) > a.menu-top:focus,
#adminmenu li.opensub:not(.current) > a.menu-top {
	background: rgba(255, 255, 255, .06);
	color: #fff;
}

#adminmenu li.menu-top:not(.current):not(.wp-has-current-submenu) > a.menu-top:hover div.wp-menu-name,
#adminmenu li.menu-top:not(.current):not(.wp-has-current-submenu) > a.menu-top:focus div.wp-menu-name {
	color: #fff;
}

/* Active — opaque indigo. Nothing composites beneath it. */
#adminmenu li.current > a.menu-top,
#adminmenu li.wp-has-current-submenu > a.wp-has-current-submenu,
#adminmenu li.wp-has-current-submenu.wp-menu-open > a.wp-has-current-submenu,
#adminmenu li.current > a.menu-top:hover,
#adminmenu li.wp-has-current-submenu > a.wp-has-current-submenu:hover,
#adminmenu a.wp-has-current-submenu:focus {
	background: var(--bw-primary);
	color: #fff;
	border-radius: 10px;
	box-shadow: none;
}

#adminmenu li.current > a.menu-top div.wp-menu-name,
#adminmenu li.wp-has-current-submenu div.wp-menu-name {
	color: #fff;
	font-weight: 600;
}
```

- [ ] **Step 4: Remove the now-dead li hover rule**

The old `#adminmenu li.menu-top:hover { background: … }` must be **gone**, not overridden. Confirm:

```bash
grep -n "li.menu-top:hover," assets/css/admin-theme.css
```
Expected: **no output**. Any match means the li still paints and the bug survives.

- [ ] **Step 5: Run the test — expect PASS**

```bash
npx playwright test tests/admin-theme.spec.js -g "hovering the current item"
```
Expected: **PASS**.

- [ ] **Step 6: Verify by eye**

Hover a non-current item (white wash appears), hover the current item (stays indigo, no shift), tab through with the keyboard (focus states visible). Confirm the idle label colour still reads at AA on `#0A0C29`.

- [ ] **Step 7: Commit**

```bash
git add assets/css/admin-theme.css tests/admin-theme.spec.js
git commit -m "fix: sidebar hover and active states compositing into a third colour

Hover painted rgba(255,255,255,.06) onto the li while the active pill painted
rgba(79,70,229,.22) onto the nested a. On the current item both translucent
layers composited over the charcoal into a colour belonging to neither state.

All state colour now lives on the anchor; the li carries layout only. The
active pill also takes the export's real value — an opaque #4F46E5, not a 22%
wash — so nothing can composite beneath it.

Hover is declared before active and scoped off the current item, so hovering
the active row cannot replace the pill with translucent white. The export has
that latent bug via style-hover; not reproduced here.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Phase 2 — Containers (CSS only)

### Task 3: `.form-table` cards + Enhancements measure

**Files:**
- Modify: `assets/css/admin-theme.css` (append a new section before `/* ─── Responsive ─── */`)
- Test: `tests/admin-theme.spec.js`

**Interfaces:**
- Consumes: Task 1's `box-sizing`.
- Produces: `--bw-measure`, a max content width other screens may reuse.

**Background:** Cache, Headless and General Settings share one shape (`<h2>` + `.form-table` as a direct child of `.wrap` or `.wrap > form`), so one CSS rule cards all three plus third-party screens. Enhancements **already** has `.postbox` containers; its defect is measure. **The child combinators are load-bearing** — an unscoped `.form-table` rule would card Enhancements' table *inside* its already-carded `.postbox`. See spec §7.

- [ ] **Step 1: Write the failing test**

```js
  test('settings screens get card containers, without nesting cards', async ({ page }) => {
    await page.setViewportSize({ width: 1600, height: 900 });
    await login(page);

    const white = 'rgb(255, 255, 255)';

    // Cache: bare form-table gets carded.
    await page.goto('/wp-admin/admin.php?page=blueworx-cache');
    await expect(page.locator('.wrap > .form-table').first()).toHaveCSS('background-color', white);

    // General Settings (core markup) gets carded too.
    await page.goto('/wp-admin/options-general.php');
    await expect(page.locator('.wrap > form > .form-table').first()).toHaveCSS('background-color', white);

    // Enhancements: its form-table lives inside an already-carded .postbox.
    // A card here means the child combinators broke and cards are nesting.
    await page.goto(SETTINGS_PATH);
    const nested = page.locator('.postbox .inside > .form-table').first();
    await expect(nested).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');

    // And its cards must be constrained, not stretched edge-to-edge at 1600px.
    const box = await page.locator('.postbox').first().boundingBox();
    expect(box.width).toBeLessThan(1300);
  });
```

- [ ] **Step 2: Run it and watch it fail**

```bash
npx playwright test tests/admin-theme.spec.js -g "card containers"
```
Expected: **FAIL** — Cache's `.form-table` is transparent, and `.postbox` is ~1500px wide.

- [ ] **Step 3: Add the measure token**

In the `:root` block of `assets/css/admin-theme.css`, after `--bw-sidebar-w: 232px;`, add:

```css
	--bw-measure: 1200px;
```

- [ ] **Step 4: Add the container rules**

Append before the `/* ─── Responsive ─── */` section:

```css
/* ─── Settings-screen containers ───
   Cache, Headless, General Settings and third-party settings screens all share
   one shape: an <h2> followed by a .form-table that is a direct child of .wrap
   or .wrap > form. One rule cards all of them, with no PHP and no JS wrapping of
   markup we do not own.

   THE CHILD COMBINATORS ARE LOAD-BEARING. Enhancements' .form-table sits at
   .postbox > .inside > .form-table, which is already carded. An unscoped
   .form-table rule would paint a card inside that card. Do not relax `>` to a
   descendant combinator. */
.wrap > .form-table,
.wrap > form > .form-table {
	background: #fff;
	border-radius: var(--bw-radius-card);
	box-shadow: var(--bw-shadow-card);
	margin-top: 12px;
	max-width: var(--bw-measure);
	padding: 8px 24px;
}

.wrap > .form-table > tbody > tr > th,
.wrap > form > .form-table > tbody > tr > th,
.wrap > .form-table > tbody > tr > td,
.wrap > form > .form-table > tbody > tr > td {
	padding: 16px 10px;
}

/* Constrain every BlueWorx settings screen to a readable measure. Without this
   the cards stretch the full viewport and .form-table's 200px th strands each
   description far from its label — the "bad spacing" on Enhancements. */
.wrap > form > .postbox,
.wrap > .postbox {
	margin-bottom: 16px;
	max-width: var(--bw-measure);
}

.wrap > h2,
.wrap > form > h2 {
	max-width: var(--bw-measure);
}

.postbox-header {
	padding: 4px 8px;
}

.postbox .inside {
	padding: 4px 20px 16px;
}
```

- [ ] **Step 5: Run the test — expect PASS**

```bash
npx playwright test tests/admin-theme.spec.js -g "card containers"
```
Expected: **PASS**.

- [ ] **Step 6: Check the screens the rule reaches**

This rule hits **every** settings screen. Look at, at 1600px wide: Enhancements, Cache, Headless, Settings → General, Settings → Reading, Settings → Permalinks, and one third-party plugin's settings page. Confirm no card-in-card and no clipped controls.

- [ ] **Step 7: Commit**

```bash
git add assets/css/admin-theme.css tests/admin-theme.spec.js
git commit -m "feat: card containers on settings screens; fix Enhancements measure

Cache, Headless, General Settings and third-party settings screens share one
markup shape (<h2> + .form-table as a direct child of .wrap or .wrap > form),
so a single CSS rule cards all of them — no PHP, and no JS wrapping of markup
we do not own.

Enhancements needed no containers: it has used .postbox > .postbox-header >
.inside since before this branch, and .postbox is already card-styled. Its
actual defect was measure — nothing constrained the content width, so on a wide
viewport the cards stretched edge-to-edge and .form-table's 200px th stranded
each description far from its label. Constrained to --bw-measure.

The child combinators are load-bearing: Enhancements' .form-table sits inside
an already-carded .postbox, so an unscoped rule would nest cards. Test asserts
that table stays transparent.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Phase 3 — Sidebar data layer

### Task 4: Group definitions and assignment rules

**Files:**
- Create: `includes/admin-menu-groups.php`
- Modify: `blueworx-labs-wordpress.php` (add `require_once`)

**Interfaces:**
- Consumes: nothing.
- Produces — later tasks call exactly these:
  - `blueworx_get_admin_menu_groups(): array` — ordered `group_key => translated label`.
  - `blueworx_get_default_admin_menu_group( string $slug ): string` — rule-based group key.
  - `blueworx_get_admin_menu_group_assignments(): array` — `slug => group_key`, saved overrides layered over defaults (overrides only when `menu_editor` is on).
  - `blueworx_get_admin_menu_group_for_slug( string $slug ): string`.

- [ ] **Step 1: Create the module**

```php
<?php
/**
 * Admin menu semantic groups.
 *
 * The v2 design groups the sidebar by meaning (Overview / Content / Custom
 * Content / Site) rather than by user preference. This module owns the group
 * vocabulary and the rules that assign a menu slug to a group. It computes
 * only — it renders nothing and mutates no globals.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the sidebar groups, in render order.
 *
 * @return array Translated labels keyed by group key.
 */
function blueworx_get_admin_menu_groups() {
	return array(
		'overview' => __( 'Overview', 'blueworx-labs-wordpress' ),
		'content'  => __( 'Content', 'blueworx-labs-wordpress' ),
		'custom'   => __( 'Custom Content', 'blueworx-labs-wordpress' ),
		'site'     => __( 'Site', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Gets the fallback group for anything not matched by a rule.
 *
 * Unrecognised third-party menus land here. They are never dropped.
 *
 * @return string Group key.
 */
function blueworx_get_default_admin_menu_group_fallback() {
	return 'site';
}

/**
 * Gets the static slug => group map for core menus.
 *
 * @return array Group keys by slug.
 */
function blueworx_get_admin_menu_group_rules() {
	return array(
		'index.php'                => 'overview',
		'edit.php'                 => 'content',
		'upload.php'               => 'content',
		'edit.php?post_type=page'  => 'content',
		'edit-comments.php'        => 'content',
		'themes.php'               => 'site',
		'plugins.php'              => 'site',
		'users.php'                => 'site',
		'tools.php'                => 'site',
		'options-general.php'      => 'site',
		'blueworx-labs-wordpress'  => 'site',
	);
}

/**
 * Resolves the rule-based group for a menu slug.
 *
 * Custom post type menus (edit.php?post_type=X where X is neither post nor
 * page) are detected dynamically, so any CPT a site registers lands in Custom
 * Content without needing to be listed.
 *
 * @param string $slug Top-level menu slug.
 * @return string Group key.
 */
function blueworx_get_default_admin_menu_group( $slug ) {
	$slug  = (string) $slug;
	$rules = blueworx_get_admin_menu_group_rules();

	if ( isset( $rules[ $slug ] ) ) {
		return $rules[ $slug ];
	}

	if ( 0 === strpos( $slug, 'edit.php?post_type=' ) ) {
		return 'custom';
	}

	return blueworx_get_default_admin_menu_group_fallback();
}

/**
 * Gets the saved slug => group overrides.
 *
 * @return array Group keys by slug.
 */
function blueworx_get_saved_admin_menu_group_assignments() {
	$saved = get_option( 'blueworx_admin_menu_groups', array() );

	if ( ! is_array( $saved ) ) {
		return array();
	}

	$groups = blueworx_get_admin_menu_groups();
	$valid  = array();

	foreach ( $saved as $slug => $group ) {
		$slug  = sanitize_text_field( (string) $slug );
		$group = sanitize_key( (string) $group );

		if ( '' !== $slug && isset( $groups[ $group ] ) ) {
			$valid[ $slug ] = $group;
		}
	}

	return $valid;
}

/**
 * Gets the effective slug => group map for the live menu.
 *
 * Rule-based defaults, with the admin's saved overrides layered on top. The
 * overrides are only honoured when the menu editor feature is on: grouping is
 * owned by admin_theme, customisation of it by menu_editor.
 *
 * @return array Group keys by slug.
 */
function blueworx_get_admin_menu_group_assignments() {
	global $menu;

	$assignments = array();

	foreach ( (array) $menu as $menu_item ) {
		$slug = isset( $menu_item[2] ) ? (string) $menu_item[2] : '';

		if ( '' === $slug || 0 === strpos( $slug, 'separator' ) ) {
			continue;
		}

		$assignments[ $slug ] = blueworx_get_default_admin_menu_group( $slug );
	}

	if ( blueworx_feature_enabled( 'menu_editor' ) ) {
		foreach ( blueworx_get_saved_admin_menu_group_assignments() as $slug => $group ) {
			if ( isset( $assignments[ $slug ] ) ) {
				$assignments[ $slug ] = $group;
			}
		}
	}

	return $assignments;
}

/**
 * Gets the effective group for one slug.
 *
 * @param string $slug Top-level menu slug.
 * @return string Group key.
 */
function blueworx_get_admin_menu_group_for_slug( $slug ) {
	$assignments = blueworx_get_admin_menu_group_assignments();

	return isset( $assignments[ $slug ] ) ? $assignments[ $slug ] : blueworx_get_default_admin_menu_group( $slug );
}
```

- [ ] **Step 2: Wire the require**

In `blueworx-labs-wordpress.php`, alongside the other `require_once` lines, add **before** `admin-menu-order.php` (which will consume it):

```php
require_once BLUEWORX_LABS_PATH . 'includes/admin-menu-groups.php';
```

- [ ] **Step 3: Verify it loads**

```bash
php -l includes/admin-menu-groups.php
```
Expected: `No syntax errors detected`.

Then load any wp-admin page and confirm no fatal error / no notice in the PHP error log.

- [ ] **Step 4: Commit**

```bash
git add includes/admin-menu-groups.php blueworx-labs-wordpress.php
git commit -m "feat: admin menu semantic group rules

Group vocabulary (Overview/Content/Custom Content/Site) and the rules mapping a
menu slug to a group, per the v2 design. Custom post types are detected
dynamically from the edit.php?post_type= shape, so any CPT a site registers
lands in Custom Content without being listed. Unrecognised third-party menus
fall back to Site and are never dropped.

Saved overrides layer over the rule defaults, and only when menu_editor is on:
grouping belongs to admin_theme, customising it belongs to menu_editor.

Compute only — no output, no global mutation.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: Migration — retire More, convert to groups

**Files:**
- Modify: `includes/upgrade.php:20-22` (version), `:217-239` (runner); append the migration
- Modify: `includes/admin-settings.php:67-93` (save handler)
- Test: `tests/admin-menu-defaults.spec.js`

**Interfaces:**
- Consumes: `blueworx_get_default_admin_menu_group()` (Task 4).
- Produces: option `blueworx_admin_menu_groups` (`slug => group_key`); option `blueworx_toggled_admin_menu_items` **deleted**.

**Background:** `upgrade.php` already has a migration framework — `blueworx_get_labs_db_version()` and `if ( $stored_version < N )`. The run-once guarantee comes free from that; do **not** invent a new flag. See spec §6.

- [ ] **Step 1: Write the failing test**

Add to `tests/admin-menu-defaults.spec.js`:

```js
  test('migration: More items reappear in their natural group', async ({ page }) => {
    await login(page);

    // The More menu and its separator are gone from the sidebar entirely.
    await page.goto('/wp-admin/index.php');
    await expect(page.locator('#toplevel_page_blueworx-menu-toggle')).toHaveCount(0);
    await expect(page.locator('.blueworx-toggle-separator')).toHaveCount(0);

    // Items that used to live in More are visible top-level rows again.
    await expect(page.locator('#adminmenu a[href="tools.php"]')).toBeVisible();
    await expect(page.locator('#adminmenu a[href="options-general.php"]')).toBeVisible();
  });
```

- [ ] **Step 2: Run it and watch it fail**

```bash
npx playwright test tests/admin-menu-defaults.spec.js -g "More items reappear"
```
Expected: **FAIL** — `#toplevel_page_blueworx-menu-toggle` still present.

- [ ] **Step 3: Bump the migration version**

In `includes/upgrade.php`, change `blueworx_get_labs_db_version()`:

```php
function blueworx_get_labs_db_version() {
	return 4;
}
```

- [ ] **Step 4: Add the migration**

Append to `includes/upgrade.php`, before `blueworx_run_pending_labs_migrations()`:

```php
/**
 * Converts the retired More menu into semantic group assignments.
 *
 * The v2 design replaces the user-defined Main/More/Hidden split with four
 * semantic groups, so the More bucket has no equivalent and is retired.
 *
 * Items sitting in More are assigned to their rule-based group, which means they
 * REAPPEAR as top-level rows. This is deliberate: More was a grouping
 * affordance, not a hiding one — the plugin has always had a separate Hidden
 * bucket for hiding, so anyone wanting an item gone would have used it. Reading
 * More as "hide" would be the more destructive interpretation.
 *
 * Hidden items are left untouched. Order is preserved and reinterpreted as
 * order-within-group.
 *
 * @return void
 */
function blueworx_migrate_admin_menu_groups() {
	$toggled = get_option( 'blueworx_toggled_admin_menu_items', array() );
	$order   = get_option( 'blueworx_admin_menu_order', array() );
	$slugs   = array();

	foreach ( array( $toggled, $order ) as $source ) {
		if ( is_array( $source ) ) {
			$slugs = array_merge( $slugs, $source );
		}
	}

	$assignments = array();

	foreach ( array_unique( array_filter( $slugs ) ) as $slug ) {
		$slug = sanitize_text_field( (string) $slug );

		if ( '' === $slug || 0 === strpos( $slug, 'separator' ) || 'blueworx-menu-toggle' === $slug ) {
			continue;
		}

		$assignments[ $slug ] = blueworx_get_default_admin_menu_group( $slug );
	}

	if ( ! empty( $assignments ) ) {
		update_option( 'blueworx_admin_menu_groups', $assignments );
	}

	// Drop the retired More state and its synthetic rows from the saved order.
	delete_option( 'blueworx_toggled_admin_menu_items' );

	if ( is_array( $order ) ) {
		$cleaned = array_values(
			array_diff( $order, array( 'blueworx-menu-toggle', 'separator-blueworx-toggle' ) )
		);

		if ( $cleaned !== $order ) {
			update_option( 'blueworx_admin_menu_order', $cleaned );
		}
	}
}
```

- [ ] **Step 5: Register it in the runner**

In `blueworx_run_pending_labs_migrations()`, after the `< 3` block:

```php
	if ( $stored_version < 4 ) {
		blueworx_migrate_admin_menu_groups();
	}
```

- [ ] **Step 6: Delete the More implementation**

In `includes/admin-menu-order.php`, delete outright:
- `blueworx_render_toggle_menu_page()` (`:534-541`) and its `add_menu_page` call inside `blueworx_apply_admin_menu_visibility()` (`:413-434`)
- `blueworx_make_toggle_menu_inline()` and its `add_action` (`:484-511`)
- `blueworx_get_toggled_admin_menu_items()` (`:245-…`)
- The locked-item entries in `blueworx_get_locked_admin_menu_items()` (`:38-43`) — return an empty array; those two slugs existed only for More:

```php
function blueworx_get_locked_admin_menu_items() {
	return array();
}
```

In `blueworx_apply_admin_menu_visibility()`, drop `$toggled` entirely; only `$hidden` drives `$hidden_ids`.

**Leave `blueworx_get_admin_menu_slug_value_options()` alone** — it still lists `blueworx_toggled_admin_menu_items` because migrations 1 and 2 run *before* 4 on very old sites and must still remap that option's contents.

- [ ] **Step 7: Stop writing the retired option**

In `includes/admin-settings.php:67-93`, remove `$raw_toggled`, `$toggled`, and the `update_option( 'blueworx_toggled_admin_menu_items', … )` line. Task 14 rewrites this handler fully; this step only stops it resurrecting a deleted option.

- [ ] **Step 8: Run the test — expect PASS**

```bash
npx playwright test tests/admin-menu-defaults.spec.js -g "More items reappear"
```
Expected: **PASS**.

- [ ] **Step 9: Prove it is idempotent**

The `$stored_version < 4` guard means a second run is a no-op. Verify:

```bash
wp option get blueworx_labs_db_version
```
Expected: `4`. Reload wp-admin, run it again — still `4`, and `blueworx_admin_menu_groups` unchanged.

```bash
wp option get blueworx_toggled_admin_menu_items
```
Expected: an error / empty — the option is gone.

- [ ] **Step 10: Commit**

```bash
git add includes/upgrade.php includes/admin-menu-order.php includes/admin-settings.php tests/admin-menu-defaults.spec.js
git commit -m "feat!: retire the More menu, migrate its items into semantic groups

The v2 design replaces the Main/More/Hidden split with four semantic groups, so
More has no equivalent. Migration 4 assigns each item sitting in More to its
rule-based group, which means those items REAPPEAR as top-level rows.

That is deliberate. More was a grouping affordance, not a hiding one — there has
always been a separate Hidden bucket for hiding, so anyone who wanted an item
gone would have used it. Reading More as 'hide' would be the more destructive
interpretation of intent. Hidden items are untouched.

Run-once comes from the existing db_version framework in upgrade.php rather than
a new flag. blueworx_get_admin_menu_slug_value_options() still lists the retired
option on purpose: migrations 1 and 2 run before 4 on old sites and must still
remap its contents.

BREAKING: sites that had items in More will see them return to the sidebar.
Called out in the Upgrade Notice.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: Group ordering and heading injection

**Files:**
- Modify: `includes/admin-menu-order.php` (`blueworx_admin_menu_order()`, `:345-364`)
- Modify: `includes/admin-theme.php` (append)
- Test: `tests/admin-menu-defaults.spec.js`

**Interfaces:**
- Consumes: `blueworx_get_admin_menu_groups()`, `blueworx_get_admin_menu_group_assignments()` (Task 4).
- Produces: `$menu` rows ordered group-by-group; heading rows carrying class `bw-menu-group bw-menu-group-{key}`. Task 10 styles them.

**Background:** headings are inert pseudo-items in `$menu`, gated on `admin_theme`. Ordering is gated on `admin_theme` too (grouping is structure). **Validate core's renderer first** — see spec §1's fallback.

- [ ] **Step 1: Write the failing test**

```js
  test('sidebar renders semantic group headings in order', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/index.php');

    const headings = page.locator('#adminmenu .bw-menu-group');
    await expect(headings.first()).toBeVisible();

    const labels = await headings.allInnerTexts();
    const seen = labels.map((t) => t.trim().toUpperCase()).filter(Boolean);

    // Groups appear in the design's order, and only non-empty ones appear.
    const expected = ['OVERVIEW', 'CONTENT', 'CUSTOM CONTENT', 'SITE'];
    expect(seen).toEqual(expected.filter((g) => seen.includes(g)));
    expect(seen).toContain('OVERVIEW');
    expect(seen).toContain('SITE');

    // Headings are inert: not links, not focusable.
    await expect(page.locator('#adminmenu .bw-menu-group a')).toHaveCount(0);
  });
```

- [ ] **Step 2: Run it and watch it fail**

```bash
npx playwright test tests/admin-menu-defaults.spec.js -g "group headings in order"
```
Expected: **FAIL** — no `.bw-menu-group` elements.

- [ ] **Step 3: Validate core's renderer before building on it**

`_wp_menu_output()` in `wp-admin/menu-header.php` renders a row as a separator when its class field contains `wp-menu-separator`, and otherwise as an anchor. Confirm which branch a `bw-menu-group` row takes:

```bash
grep -n "wp-menu-separator" /path/to/wordpress/wp-admin/menu-header.php
```

Decide from what you observe:
- If a non-separator row with an empty URL renders as readable text without a focusable anchor → use the pseudo-item approach (Step 4).
- If it always emits a focusable `<a>` → **use the spec §1 fallback**: drop the pseudo-item, mark the first item of each group with `bw-group-start-{key}`, and render the heading from a `::before` whose label comes from an inline `--bw-group-label` custom property (keeps it translatable).

Record which route you took in the commit message.

- [ ] **Step 4: Order the menu by group**

Replace the body of `blueworx_admin_menu_order()` in `includes/admin-menu-order.php` with:

```php
function blueworx_admin_menu_order( $menu_order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by the "menu_order" filter signature; this implementation builds its own order from group assignments and stored settings.
	$assignments = blueworx_get_admin_menu_group_assignments();
	$saved       = blueworx_get_saved_admin_menu_order();
	$groups      = array_keys( blueworx_get_admin_menu_groups() );
	$ordered     = array();

	// Within a group, honour the admin's saved order; unsaved items follow.
	foreach ( $groups as $group ) {
		$in_group = array();

		foreach ( $saved as $slug ) {
			if ( isset( $assignments[ $slug ] ) && $group === $assignments[ $slug ] ) {
				$in_group[] = $slug;
			}
		}

		foreach ( $assignments as $slug => $slug_group ) {
			if ( $group === $slug_group && ! in_array( $slug, $in_group, true ) ) {
				$in_group[] = $slug;
			}
		}

		foreach ( $in_group as $slug ) {
			$ordered[] = 'bw-group-' . $group;
			$ordered[] = $slug;
		}
	}

	// Collapse repeated heading markers to one per group.
	$result = array();

	foreach ( $ordered as $entry ) {
		if ( 0 === strpos( $entry, 'bw-group-' ) && in_array( $entry, $result, true ) ) {
			continue;
		}

		$result[] = $entry;
	}

	return $result;
}
if ( blueworx_feature_enabled( 'admin_theme' ) ) {
	add_filter( 'menu_order', 'blueworx_admin_menu_order' );
	add_filter( 'custom_menu_order', '__return_true' );
}
```

- [ ] **Step 5: Inject the heading rows**

Append to `includes/admin-theme.php`:

```php
/**
 * Injects inert group heading rows into the admin menu.
 *
 * Headings are real $menu rows carrying a bw-menu-group class, not CSS
 * `content:` strings — hard-coding the labels in the stylesheet would make them
 * untranslatable. CSS renders them as headings and removes them from the tab
 * order.
 *
 * A group with no visible items emits no heading.
 *
 * @return void
 */
function blueworx_inject_admin_menu_group_headings() {
	global $menu;

	$assignments = blueworx_get_admin_menu_group_assignments();
	$hidden      = blueworx_get_hidden_admin_menu_items();
	$populated   = array();

	foreach ( $assignments as $slug => $group ) {
		if ( ! in_array( $slug, $hidden, true ) ) {
			$populated[ $group ] = true;
		}
	}

	$position = 1;

	foreach ( blueworx_get_admin_menu_groups() as $group => $label ) {
		if ( empty( $populated[ $group ] ) ) {
			continue;
		}

		$menu[ 'bw-group-' . $group ] = array( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Direct mutation of the $menu global inside the "admin_menu" action is the standard, documented way to insert admin menu rows; WordPress provides no API for this.
			$label,
			'read',
			'bw-group-' . $group,
			'',
			'bw-menu-group bw-menu-group-' . $group,
		);

		++$position;
	}
}
if ( blueworx_feature_enabled( 'admin_theme' ) ) {
	add_action( 'admin_menu', 'blueworx_inject_admin_menu_group_headings', 998 );
}
```

- [ ] **Step 6: Run the test — expect PASS**

```bash
npx playwright test tests/admin-menu-defaults.spec.js -g "group headings in order"
```
Expected: **PASS**. Headings are unstyled at this point — Task 10 styles them. Confirm the **order and inertness** here, not the looks.

- [ ] **Step 7: Verify the empty-group rule**

Turn on the `comments` feature ("Comments disabled") in Enhancements so `edit-comments.php` leaves the menu. Content still has Posts/Media/Pages, so its heading must remain. Then confirm no heading renders for a group with nothing in it.

- [ ] **Step 8: Commit**

```bash
git add includes/admin-menu-order.php includes/admin-theme.php tests/admin-menu-defaults.spec.js
git commit -m "feat: order the sidebar by semantic group and inject group headings

menu_order now emits items grouped Overview -> Content -> Custom Content -> Site,
honouring the admin's saved order within each group. Headings are injected as
inert $menu rows carrying a bw-menu-group class.

Real rows rather than CSS content: strings, so the labels stay translatable. A
group with no visible items emits no heading.

Both are gated on admin_theme, not menu_editor: grouping is part of the BlueWorx
sidebar's structure, and menu_editor only customises what the design establishes.
An unstyled heading in a stock WordPress menu would be a visual defect.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Phase 4 — Sidebar presentation

### Task 7: Icons

**Files:**
- Create: `includes/admin-menu-icons.php`
- Modify: `blueworx-labs-wordpress.php`, `includes/admin-theme.php`
- Test: `tests/admin-theme.spec.js`

**Interfaces:**
- Consumes: nothing.
- Produces: `blueworx_get_admin_menu_icon( string $slug ): string` (SVG markup or `''`), `blueworx_get_svg_kses_allowlist(): array`.

**Background:** exact SVGs lifted from the v2 export. All `viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"`, 19×19 (Log Out 18×18). Unmapped third-party menus keep their dashicon. See spec §2.

- [ ] **Step 1: Write the failing test**

```js
  test('core menu items use the design icon set, third-party keep dashicons', async ({ page }) => {
    await login(page);
    await page.goto(DASH_PATH);

    // Mapped core items get an inline SVG.
    const dash = page.locator('#adminmenu li a[href="index.php"] svg.bw-menu-icon');
    await expect(dash).toHaveCount(1);
    await expect(dash).toHaveAttribute('aria-hidden', 'true');

    // Icons inherit the label colour.
    await expect(dash).toHaveAttribute('stroke', 'currentColor');
  });
```

- [ ] **Step 2: Run it and watch it fail**

```bash
npx playwright test tests/admin-theme.spec.js -g "design icon set"
```
Expected: **FAIL** — no `svg.bw-menu-icon`.

- [ ] **Step 3: Create the icon module**

```php
<?php
/**
 * Admin menu icons.
 *
 * Inline SVGs lifted verbatim from the "WordPress Admin v2" design export. All
 * share viewBox 0 0 24 24, fill none, stroke currentColor, stroke-width 1.75, so
 * they inherit the menu label's colour in every state.
 *
 * Only mapped core slugs are swapped. Unrecognised third-party menus keep their
 * own dashicon — there is nothing to map them to, and blanking them would be
 * worse than an inconsistent glyph.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the allowlist for passing inline SVG through wp_kses().
 *
 * @return array Allowed tags and attributes.
 */
function blueworx_get_svg_kses_allowlist() {
	$shared = array(
		'fill'         => true,
		'stroke'       => true,
		'stroke-width' => true,
	);

	return array(
		'svg'    => array(
			'viewbox'         => true,
			'width'           => true,
			'height'          => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'class'           => true,
			'aria-hidden'     => true,
			'focusable'       => true,
		),
		'path'   => array_merge( $shared, array( 'd' => true ) ),
		'rect'   => array_merge( $shared, array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true ) ),
		'circle' => array_merge( $shared, array( 'cx' => true, 'cy' => true, 'r' => true ) ),
	);
}

/**
 * Gets the slug => SVG inner-markup map.
 *
 * @return array SVG inner markup keyed by menu slug.
 */
function blueworx_get_admin_menu_icon_paths() {
	return array(
		// Dashboard — grid.
		'index.php'               => '<rect x="3" y="3" width="8" height="8" rx="1.5"></rect><rect x="13" y="3" width="8" height="8" rx="1.5"></rect><rect x="3" y="13" width="8" height="8" rx="1.5"></rect><rect x="13" y="13" width="8" height="8" rx="1.5"></rect>',
		// Posts — document.
		'edit.php'                => '<path d="M6 3h9l4 4v14H6z"></path><path d="M9 12h7M9 16h7M9 8h3"></path>',
		// Media — image.
		'upload.php'              => '<rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="8.5" cy="9.5" r="1.5"></circle><path d="M21 16l-5.5-5.5L9 17"></path>',
		// Pages — layers.
		'edit.php?post_type=page' => '<path d="M12 3l9 5-9 5-9-5 9-5z"></path><path d="M3 13l9 5 9-5"></path>',
		// Appearance — palette.
		'themes.php'              => '<path d="M12 3a9 9 0 100 18 1.5 1.5 0 001.1-2.5 1.5 1.5 0 011.1-2.5H17a4 4 0 004-4c0-5-4-9-9-9z"></path><circle cx="7.5" cy="11.5" r="1"></circle><circle cx="10.5" cy="7.5" r="1"></circle><circle cx="15" cy="8" r="1"></circle>',
		// Plugins — puzzle.
		'plugins.php'             => '<path d="M9 4v2a2 2 0 002 2 2 2 0 002-2V4h4v4h-2a2 2 0 000 4h2v4h-4v-2a2 2 0 00-2-2 2 2 0 00-2 2v2H5v-4h2a2 2 0 000-4H5V8h4z"></path>',
		// Users — user.
		'users.php'               => '<circle cx="12" cy="8" r="3.5"></circle><path d="M4.5 20c1.5-4 4.5-6 7.5-6s6 2 7.5 6"></path>',
		// Tools — wrench.
		'tools.php'               => '<path d="M14.7 6.3a4 4 0 00-5.3 5.3L3 18v3h3l6.4-6.4a4 4 0 005.3-5.3l-2.9 2.9-2.1-2.1 2.9-2.9z"></path>',
		// Settings — gear.
		'options-general.php'     => '<circle cx="12" cy="12" r="3"></circle><path d="M19 12a7 7 0 00-.1-1.2l2-1.5-2-3.4-2.3.9a7 7 0 00-2-1.2L14 3h-4l-.6 2.6a7 7 0 00-2 1.2l-2.3-.9-2 3.4 2 1.5A7 7 0 005 12c0 .4 0 .8.1 1.2l-2 1.5 2 3.4 2.3-.9a7 7 0 002 1.2L10 21h4l.6-2.6a7 7 0 002-1.2l2.3.9 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z"></path>',
		// Custom post types — tag.
		'bw-custom-post-type'     => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 01-2.8 0l-7-7A2 2 0 013 12.2V5a2 2 0 012-2h7.2a2 2 0 011.4.6l7 7a2 2 0 010 2.8z"></path><circle cx="7.5" cy="7.5" r="1"></circle>',
		// Log Out — log-out.
		'bw-logout'               => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"></path><path d="M16 17l5-5-5-5M21 12H9"></path>',
	);
}

/**
 * Gets the inline SVG for a menu slug.
 *
 * @param string $slug Menu slug, or a bw-* pseudo key.
 * @param int    $size Pixel size.
 * @return string SVG markup, or an empty string when the slug is unmapped.
 */
function blueworx_get_admin_menu_icon( $slug, $size = 19 ) {
	$paths = blueworx_get_admin_menu_icon_paths();
	$slug  = (string) $slug;

	if ( ! isset( $paths[ $slug ] ) && 0 === strpos( $slug, 'edit.php?post_type=' ) ) {
		$slug = 'bw-custom-post-type';
	}

	if ( ! isset( $paths[ $slug ] ) ) {
		return '';
	}

	return sprintf(
		'<svg class="bw-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" width="%1$d" height="%1$d" aria-hidden="true" focusable="false">%2$s</svg>',
		(int) $size,
		$paths[ $slug ]
	);
}
```

- [ ] **Step 4: Wire the require**

In `blueworx-labs-wordpress.php`:

```php
require_once BLUEWORX_LABS_PATH . 'includes/admin-menu-icons.php';
```

- [ ] **Step 5: Swap the icons into the menu**

Append to `includes/admin-theme.php`:

```php
/**
 * Replaces dashicons with the design's icon set on mapped core menus.
 *
 * Unmapped menus are left alone, so third-party plugins keep their own glyph.
 *
 * @return void
 */
function blueworx_swap_admin_menu_icons() {
	global $menu;

	foreach ( (array) $menu as $index => $menu_item ) {
		$slug = isset( $menu_item[2] ) ? (string) $menu_item[2] : '';
		$icon = blueworx_get_admin_menu_icon( $slug );

		if ( '' === $icon || 0 === strpos( $slug, 'bw-group-' ) ) {
			continue;
		}

		// Field 4 is the class field, 6 the icon URL. "none" stops WordPress
		// printing its own dashicon span; our SVG is injected via the class.
		$menu[ $index ][4] = trim( ( isset( $menu_item[4] ) ? $menu_item[4] : '' ) . ' bw-has-icon' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Direct $menu mutation inside "admin_menu" is the documented way to alter admin menu rows.
		$menu[ $index ][6] = 'none'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
	}
}
if ( blueworx_feature_enabled( 'admin_theme' ) ) {
	add_action( 'admin_menu', 'blueworx_swap_admin_menu_icons', 997 );
}

/**
 * Prints the inline SVG icons into the rendered menu.
 *
 * WordPress renders the icon span before we can filter the row's markup, so the
 * SVG is injected client-side-free: we print a <style>-free inline block that
 * the CSS positions. Uses wp_kses with an explicit SVG allowlist.
 *
 * @return void
 */
function blueworx_print_admin_menu_icons() {
	global $menu;

	$icons = array();

	foreach ( (array) $menu as $menu_item ) {
		$slug = isset( $menu_item[2] ) ? (string) $menu_item[2] : '';
		$id   = isset( $menu_item[5] ) ? (string) $menu_item[5] : '';
		$icon = blueworx_get_admin_menu_icon( $slug );

		if ( '' === $icon || '' === $id ) {
			continue;
		}

		$icons[ blueworx_sanitize_admin_menu_id( $id ) ] = $icon;
	}

	if ( empty( $icons ) ) {
		return;
	}
	?>
	<script>
		( function () {
			var icons = <?php echo wp_json_encode( $icons ); ?>;

			Object.keys( icons ).forEach( function ( id ) {
				var row = document.getElementById( id );

				if ( ! row ) {
					return;
				}

				var slot = row.querySelector( '.wp-menu-image' );

				if ( slot ) {
					slot.innerHTML = icons[ id ];
				}
			} );
		}() );
	</script>
	<?php
}
if ( blueworx_feature_enabled( 'admin_theme' ) ) {
	add_action( 'admin_footer', 'blueworx_print_admin_menu_icons' );
}
```

> **Implementer note:** if you find a way to set the icon in `$menu` field 6 that renders the SVG server-side without the footer script, prefer it and delete `blueworx_print_admin_menu_icons()` — fewer moving parts, no flash. Report which route you took.

- [ ] **Step 6: Run the test — expect PASS**

```bash
npx playwright test tests/admin-theme.spec.js -g "design icon set"
```
Expected: **PASS**.

- [ ] **Step 7: Verify third-party menus keep their glyph**

Look at the sidebar: Clubhouse / Content / any third-party plugin must still show *an* icon, never an empty gap.

- [ ] **Step 8: Commit**

```bash
git add includes/admin-menu-icons.php includes/admin-theme.php blueworx-labs-wordpress.php tests/admin-theme.spec.js
git commit -m "feat: design icon set for core admin menu items

Inline SVGs lifted verbatim from the WordPress Admin v2 export, replacing
dashicons on the nine mapped core menus. All stroke currentColor, so they follow
the label colour through idle/hover/active without extra rules.

Custom post types share the export's tag glyph. Unmapped third-party menus keep
their own dashicon — there is nothing to map them to, and blanking them would be
worse than an inconsistent glyph.

SVG output passes through wp_kses with an explicit allowlist.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 8: Count badges

**Files:**
- Create: `includes/admin-menu-badges.php`
- Modify: `blueworx-labs-wordpress.php`, `includes/admin-theme.php`
- Test: `tests/admin-theme.spec.js`

**Interfaces:**
- Consumes: nothing.
- Produces: `blueworx_get_admin_menu_badge_counts(): array` (`slug => int`), statically cached per request.

**Background:** zero renders no badge. Where core already renders its own bubble (`.update-plugins`, `.awaiting-mod`), core wins — do not add a second. See spec §3.

- [ ] **Step 1: Write the failing test**

```js
  test('menu badges show real counts and are absent at zero', async ({ page }) => {
    await login(page);

    // Read the true published-post count from the Posts list table.
    await page.goto('/wp-admin/edit.php');
    const publishedText = await page.locator('.subsubsub .publish .count, .subsubsub li.publish a').first().innerText();
    const published = parseInt(publishedText.replace(/\D/g, ''), 10);

    await page.goto(DASH_PATH);
    const badge = page.locator('#adminmenu li a[href="edit.php"] .bw-badge');

    if (published > 0) {
      await expect(badge).toHaveText(String(published));
      await expect(badge).toHaveAttribute('aria-label', new RegExp(`${published}`));
    } else {
      await expect(badge).toHaveCount(0);
    }
  });
```

- [ ] **Step 2: Run it and watch it fail**

```bash
npx playwright test tests/admin-theme.spec.js -g "menu badges"
```
Expected: **FAIL** — no `.bw-badge`.

- [ ] **Step 3: Create the badge module**

```php
<?php
/**
 * Admin menu count badges.
 *
 * Real counts from core APIs, computed once per request. A zero count renders no
 * badge, matching the design (which badges only non-zero items).
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the badge count for every badgeable menu slug.
 *
 * Statically cached: called once per request even if read repeatedly.
 *
 * @return array Counts keyed by menu slug. Zero counts are omitted.
 */
function blueworx_get_admin_menu_badge_counts() {
	static $counts = null;

	if ( null !== $counts ) {
		return $counts;
	}

	$counts = array();

	$posts = (int) wp_count_posts( 'post' )->publish;
	if ( $posts > 0 ) {
		$counts['edit.php'] = $posts;
	}

	$pages = (int) wp_count_posts( 'page' )->publish;
	if ( $pages > 0 ) {
		$counts['edit.php?post_type=page'] = $pages;
	}

	$media = 0;
	foreach ( (array) wp_count_attachments() as $mime_count ) {
		$media += (int) $mime_count;
	}
	if ( $media > 0 ) {
		$counts['upload.php'] = $media;
	}

	foreach ( get_post_types( array( '_builtin' => false, 'show_ui' => true ), 'names' ) as $post_type ) {
		$cpt_count = (int) wp_count_posts( $post_type )->publish;

		if ( $cpt_count > 0 ) {
			$counts[ 'edit.php?post_type=' . $post_type ] = $cpt_count;
		}
	}

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$active = count( (array) get_option( 'active_plugins', array() ) );
	if ( $active > 0 ) {
		$counts['plugins.php'] = $active;
	}

	return $counts;
}
```

- [ ] **Step 4: Wire the require**

```php
require_once BLUEWORX_LABS_PATH . 'includes/admin-menu-badges.php';
```

- [ ] **Step 5: Render the badges**

Append to `includes/admin-theme.php`. Extend the footer script from Task 7 rather than adding a second one — replace `blueworx_print_admin_menu_icons()`'s body so it prints icons **and** badges in one pass:

```php
/**
 * Prints inline icons and count badges into the rendered menu.
 *
 * Where WordPress already renders its own bubble on a row (plugin updates,
 * comments awaiting moderation), core's bubble wins and no BlueWorx badge is
 * added — two count bubbles on one row would be worse than none.
 *
 * @return void
 */
function blueworx_print_admin_menu_decorations() {
	global $menu;

	$badges = blueworx_get_admin_menu_badge_counts();
	$rows   = array();

	foreach ( (array) $menu as $menu_item ) {
		$slug = isset( $menu_item[2] ) ? (string) $menu_item[2] : '';
		$id   = isset( $menu_item[5] ) ? (string) $menu_item[5] : '';

		if ( '' === $id ) {
			continue;
		}

		$icon  = blueworx_get_admin_menu_icon( $slug );
		$count = isset( $badges[ $slug ] ) ? (int) $badges[ $slug ] : 0;

		if ( '' === $icon && 0 === $count ) {
			continue;
		}

		$rows[ blueworx_sanitize_admin_menu_id( $id ) ] = array(
			'icon'  => $icon,
			'count' => $count,
			'label' => $count > 0
				/* translators: %d: number of items. */
				? sprintf( _n( '%d item', '%d items', $count, 'blueworx-labs-wordpress' ), $count )
				: '',
		);
	}

	if ( empty( $rows ) ) {
		return;
	}
	?>
	<script>
		( function () {
			var rows = <?php echo wp_json_encode( $rows ); ?>;

			Object.keys( rows ).forEach( function ( id ) {
				var row = document.getElementById( id );

				if ( ! row ) {
					return;
				}

				var data = rows[ id ];
				var slot = row.querySelector( '.wp-menu-image' );

				if ( slot && data.icon ) {
					slot.innerHTML = data.icon;
				}

				// Core's own bubble wins; never render two counts on one row.
				if ( data.count && ! row.querySelector( '.update-plugins, .awaiting-mod' ) ) {
					var name = row.querySelector( '.wp-menu-name' );

					if ( name && ! name.querySelector( '.bw-badge' ) ) {
						var badge = document.createElement( 'span' );
						badge.className = 'bw-badge';
						badge.textContent = String( data.count );
						badge.setAttribute( 'aria-label', data.label );
						name.appendChild( badge );
					}
				}
			} );
		}() );
	</script>
	<?php
}
if ( blueworx_feature_enabled( 'admin_theme' ) ) {
	add_action( 'admin_footer', 'blueworx_print_admin_menu_decorations' );
}
```

Delete `blueworx_print_admin_menu_icons()` and its `add_action` from Task 7 — this replaces it.

- [ ] **Step 6: Run the test — expect PASS**

```bash
npx playwright test tests/admin-theme.spec.js -g "menu badges"
```
Expected: **PASS**.

- [ ] **Step 7: Verify no double bubbles**

With a plugin update pending, Plugins must show **one** count (core's), not two.

- [ ] **Step 8: Commit**

```bash
git add includes/admin-menu-badges.php includes/admin-theme.php blueworx-labs-wordpress.php tests/admin-theme.spec.js
git commit -m "feat: real count badges on sidebar menu items

Published posts, pages, media, custom post types, and active plugins, from core
count APIs, computed once per request via a static cache. Zero renders no badge,
matching the design.

Where WordPress already renders its own bubble (plugin updates, comments awaiting
moderation) core's bubble wins and no BlueWorx badge is added — two counts on one
row would be worse than none.

Badges carry an accessible label ('24 items'), not a bare number.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 9: Log Out row

**Files:**
- Modify: `includes/admin-theme.php`
- Test: `tests/admin-theme.spec.js`

**Interfaces:**
- Consumes: `blueworx_get_admin_menu_icon( 'bw-logout', 18 )` (Task 7).
- Produces: `.bw-logout` markup at the sidebar's end.

- [ ] **Step 1: Write the failing test**

```js
  test('sidebar has a Log Out row with a nonced URL', async ({ page }) => {
    await login(page);
    await page.goto(DASH_PATH);

    const logout = page.locator('#adminmenu .bw-logout a');
    await expect(logout).toBeVisible();
    await expect(logout).toHaveAttribute('href', /action=logout/);
    await expect(logout).toHaveAttribute('href', /_wpnonce=/);
    await expect(page.locator('#adminmenu .bw-logout svg')).toHaveCount(1);
  });
```

- [ ] **Step 2: Run it and watch it fail**

```bash
npx playwright test tests/admin-theme.spec.js -g "Log Out row"
```
Expected: **FAIL**.

- [ ] **Step 3: Render it**

Append to `includes/admin-theme.php`:

```php
/**
 * Appends the design's Log Out row to the end of the sidebar.
 *
 * Duplicates the top bar's user menu logout. That is intentional — the v2 design
 * shows both.
 *
 * @return void
 */
function blueworx_print_admin_menu_logout() {
	$icon = blueworx_get_admin_menu_icon( 'bw-logout', 18 );
	?>
	<script>
		( function () {
			var menu = document.getElementById( 'adminmenu' );

			if ( ! menu || menu.querySelector( '.bw-logout' ) ) {
				return;
			}

			var item = document.createElement( 'li' );
			item.className = 'bw-logout';
			item.innerHTML = <?php echo wp_json_encode(
				sprintf(
					'<a href="%1$s">%2$s<span>%3$s</span></a>',
					esc_url( wp_logout_url() ),
					$icon,
					esc_html__( 'Log Out', 'blueworx-labs-wordpress' )
				)
			); ?>;
			menu.appendChild( item );
		}() );
	</script>
	<?php
}
if ( blueworx_feature_enabled( 'admin_theme' ) ) {
	add_action( 'admin_footer', 'blueworx_print_admin_menu_logout' );
}
```

- [ ] **Step 4: Run the test — expect PASS**

```bash
npx playwright test tests/admin-theme.spec.js -g "Log Out row"
```
Expected: **PASS**.

- [ ] **Step 5: Actually log out**

Click it. You must land on the login screen, with no nonce warning.

- [ ] **Step 6: Commit**

```bash
git add includes/admin-theme.php tests/admin-theme.spec.js
git commit -m "feat: Log Out row at the foot of the sidebar

Uses wp_logout_url(), which carries the nonce. Duplicates the top bar user
menu's logout by design — the v2 export shows both.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 10: Sidebar CSS — headings, badges, Log Out, item metrics

**Files:**
- Modify: `assets/css/admin-theme.css` (sidebar section)
- Test: `tests/admin-theme.spec.js`

**Interfaces:**
- Consumes: Tasks 1, 2, 6–9.
- Produces: final sidebar visuals. **Do not re-declare** the state colours from Task 2.

**Exact values from the v2 export — do not invent:**

| Element | Values |
|---|---|
| Nav container | `padding: 0 14px` |
| Group heading | `font-size: 10.5px; font-weight: 600; letter-spacing: 1.2px; color: rgba(255,255,255,.32); padding: 0 12px 8px` |
| Item | `gap: 12px; padding: 10px 12px; border-radius: 10px; margin-bottom: 2px; font-size: 14px; transition: background 200ms ease, color 200ms ease` |
| Badge (idle) | `font-size: 11px; font-weight: 600; border-radius: 9999px; padding: 2px 8px; background: rgba(255,255,255,.08); color: rgba(255,255,255,.55); margin-left: auto` |
| Badge (active row) | `background: rgba(255,255,255,.22); color: #fff` |

- [ ] **Step 1: Write the failing test**

```js
  test('group headings are styled and inert', async ({ page }) => {
    await login(page);
    await page.goto(DASH_PATH);

    const heading = page.locator('#adminmenu .bw-menu-group').first();
    await expect(heading).toHaveCSS('letter-spacing', '1.2px');
    await expect(heading).toHaveCSS('pointer-events', 'none');
  });
```

- [ ] **Step 2: Run it and watch it fail**

```bash
npx playwright test tests/admin-theme.spec.js -g "headings are styled"
```
Expected: **FAIL**.

- [ ] **Step 3: Add the sidebar CSS**

Append to the sidebar section of `assets/css/admin-theme.css`:

```css
/* ─── Sidebar: group headings ───
   Inert: a heading is not a destination. Removed from the tab order in markup
   (no anchor) and from pointer interaction here. */
#adminmenu li.bw-menu-group {
	background: none;
	cursor: default;
	margin: 14px 0 0;
	pointer-events: none;
}

#adminmenu li.bw-menu-group .wp-menu-name {
	color: rgba(255, 255, 255, .32);
	font-family: var(--bw-font-ui);
	font-size: 10.5px;
	font-weight: 600;
	letter-spacing: 1.2px;
	padding: 0 12px 8px;
	text-transform: uppercase;
}

#adminmenu li.bw-menu-group .wp-menu-image {
	display: none;
}

/* First heading needs no top gap — the brand block already spaces it. */
#adminmenu li.bw-menu-group:first-child {
	margin-top: 4px;
}

/* ─── Sidebar: item metrics (from the export) ─── */
#adminmenu a.menu-top {
	align-items: center;
	display: flex;
	gap: 12px;
	padding: 10px 12px;
	transition: background 200ms ease, color 200ms ease;
}

#adminmenu .bw-menu-icon {
	flex-shrink: 0;
}

/* ─── Sidebar: count badges ─── */
.bw-badge {
	background: rgba(255, 255, 255, .08);
	border-radius: 9999px;
	color: rgba(255, 255, 255, .55);
	font-size: 11px;
	font-weight: 600;
	margin-left: auto;
	padding: 2px 8px;
}

#adminmenu li.current .bw-badge,
#adminmenu li.wp-has-current-submenu .bw-badge {
	background: rgba(255, 255, 255, .22);
	color: #fff;
}

/* ─── Sidebar: Log Out ─── */
#adminmenu li.bw-logout {
	border-top: 1px solid rgba(255, 255, 255, .08);
	margin: 12px 8px 8px;
	padding-top: 8px;
}

#adminmenu li.bw-logout a {
	align-items: center;
	color: rgba(255, 255, 255, .62);
	display: flex;
	font-size: 14px;
	font-weight: 500;
	gap: 12px;
	padding: 10px 12px;
	border-radius: 10px;
}

#adminmenu li.bw-logout a:hover,
#adminmenu li.bw-logout a:focus {
	background: rgba(255, 255, 255, .06);
	color: #fff;
}

/* Folded: labels, badges and headings collapse; icons remain. */
@media only screen and (min-width: 783px) {
	body.folded #adminmenu li.bw-menu-group,
	body.folded .bw-badge,
	body.folded #adminmenu li.bw-logout a span {
		display: none;
	}
}
```

- [ ] **Step 4: Run the test — expect PASS**

```bash
npx playwright test tests/admin-theme.spec.js -g "headings are styled"
```
Expected: **PASS**.

- [ ] **Step 5: Compare against the design**

Open the export side by side:
```
"C:\Users\LukeMcfarland\Downloads\Reimagined WordPress Backend Design\WordPress Admin v2.dc.html"
```
Compare the sidebar at 1280px: heading colour/tracking, item padding/gap, badge pill, active indigo, Log Out. Note any gap you cannot close and report it — do not silently accept a mismatch.

- [ ] **Step 6: Check folded and mobile**

Fold the menu: icons only, no clipped labels or stray badges. At 480px the native admin bar returns and the menu toggle still opens the sidebar.

- [ ] **Step 7: Commit**

```bash
git add assets/css/admin-theme.css tests/admin-theme.spec.js
git commit -m "feat: sidebar styling for group headings, badges, and Log Out

Values taken from the v2 export rather than invented: heading 10.5px/600 with
1.2px tracking at 32% white, items 10px/12px padding with a 12px gap, badges as
11px/600 pills at 8% white (22% on the active row).

Headings are inert in markup (no anchor) and in CSS (pointer-events: none) — a
heading is not a destination.

Folded state collapses headings, badges and the Log Out label, leaving icons.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Phase 5 — Edit Menu

### Task 11: Diagnose the broken drag-and-drop

**Files:** none modified — this task produces a written finding.

**Background:** the obvious causes are **already ruled out**: `jquery-ui-sortable` *is* enqueued as a dependency (`admin-assets.php:90-96`) and the hook suffix `blueworx_page_blueworx-edit-menu` *is* correct. Do not re-check those and call it done. See spec §6.

**Why this task exists:** Task 13 replaces this code. If you skip the diagnosis, you may rebuild the same fault in new syntax.

- [ ] **Step 1: Reproduce**

Open `/wp-admin/admin.php?page=blueworx-edit-menu`. Try to drag an item by its `::` handle. Record exactly what happens: nothing at all, or does it lift and not drop.

- [ ] **Step 2: Check the console**

Open DevTools → Console. Reload. Record every error/warning.

- [ ] **Step 3: Check the script actually loaded**

Console:
```js
typeof jQuery.fn.sortable
```
Expected if loaded: `"function"`. If `"undefined"`, jQuery UI sortable never arrived despite the dependency — that is your answer.

- [ ] **Step 4: Check sortable initialised**

```js
jQuery('.blueworx-menu-order-list').length
jQuery('.blueworx-menu-order-list').hasClass('ui-sortable')
```
Expected if healthy: `3` and `true`. `hasClass` false ⇒ init never ran or threw.

- [ ] **Step 5: Check the handle is grabbable**

```js
var h = document.querySelector('.blueworx-menu-order-handle');
getComputedStyle(h).pointerEvents;
h.getBoundingClientRect();
```
A `pointerEvents: none`, or a zero-size box, means the handle cannot be grabbed — the plausible interaction between the v1.11.0 theme CSS and this unstyled screen.

- [ ] **Step 6: Write the finding down**

Record in the commit message: the symptom, what you ruled out, and the actual cause. If it remains unexplained after these steps, say so plainly — an honest "unexplained after checking X/Y/Z" is worth more than a guess, and Task 13 replaces the code regardless.

- [ ] **Step 7: Commit the finding**

```bash
git commit --allow-empty -m "docs: diagnosis of the broken Edit Menu drag-and-drop

Symptom: <what you saw>
Ruled out: jquery-ui-sortable IS enqueued as a dependency (admin-assets.php:90)
  and the hook suffix blueworx_page_blueworx-edit-menu IS correct.
Console: <errors, or none>
typeof jQuery.fn.sortable: <result>
.ui-sortable applied: <result>
Handle pointer-events / box: <result>
Cause: <the actual cause, or 'unexplained after the above'>

Recorded before Task 13 replaces this code, so the rebuild does not reproduce
the same fault.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 12: Rebuild the Edit Menu render

**Files:**
- Modify: `includes/admin-settings.php:394-506`
- Create: `assets/css/admin-menu-editor.css`
- Modify: `includes/admin-assets.php`

**Interfaces:**
- Consumes: `blueworx_get_admin_menu_groups()`, `blueworx_get_admin_menu_group_assignments()` (Task 4); `blueworx_get_editable_admin_menu_items()` (existing, `admin-menu-order.php:287`).
- Produces the contract Task 13's JS binds to, and Task 14's handler parses:
  - `.bw-menu-editor-group[data-group="<key>"]` — a section, `<key>` ∈ groups + `hidden`
  - `.bw-menu-editor-item[data-slug="<slug>"]` — a draggable row
  - `input[name="blueworx_admin_menu_groups[<slug>]"]` — group per item
  - `input[name="blueworx_admin_menu_order[]"]` — order
  - `input[name="blueworx_hidden_admin_menu_items[]"]` — hidden

- [ ] **Step 1: Write the failing test**

```js
  test('Edit Menu renders a section per group plus Hidden', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-edit-menu');

    for (const g of ['overview', 'content', 'custom', 'site', 'hidden']) {
      await expect(page.locator(`.bw-menu-editor-group[data-group="${g}"]`)).toHaveCount(1);
    }

    // Dashboard starts in Overview.
    await expect(
      page.locator('.bw-menu-editor-group[data-group="overview"] .bw-menu-editor-item[data-slug="index.php"]')
    ).toHaveCount(1);

    // Every row is keyboard-operable, not drag-only.
    const first = page.locator('.bw-menu-editor-item').first();
    await expect(first.locator('button.bw-menu-editor-up')).toBeVisible();
    await expect(first.locator('button.bw-menu-editor-down')).toBeVisible();
  });
```

- [ ] **Step 2: Run it and watch it fail**

```bash
npx playwright test tests/admin-menu-defaults.spec.js -g "Edit Menu renders a section"
```
Expected: **FAIL**.

- [ ] **Step 3: Replace the render functions**

Replace `blueworx_render_edit_menu_page()` and `blueworx_render_menu_editor_section()` (`admin-settings.php:394-506`) with:

```php
function blueworx_render_edit_menu_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-labs-wordpress' ) );
	}

	$menu_items  = blueworx_get_editable_admin_menu_items();
	$assignments = blueworx_get_admin_menu_group_assignments();
	$hidden      = blueworx_get_hidden_admin_menu_items();
	$saved_order = blueworx_get_saved_admin_menu_order();
	$groups      = blueworx_get_admin_menu_groups();
	$notice      = get_transient( 'blueworx_admin_menu_order_notice' );

	if ( $notice ) {
		delete_transient( 'blueworx_admin_menu_order_notice' );
	}

	// Bucket every editable item: hidden wins, otherwise its assigned group.
	$buckets = array_fill_keys( array_keys( $groups ), array() );
	$buckets['hidden'] = array();

	$ordered = array();
	foreach ( $saved_order as $slug ) {
		if ( isset( $menu_items[ $slug ] ) ) {
			$ordered[ $slug ] = $menu_items[ $slug ];
		}
	}
	foreach ( $menu_items as $slug => $label ) {
		if ( ! isset( $ordered[ $slug ] ) ) {
			$ordered[ $slug ] = $label;
		}
	}

	foreach ( $ordered as $slug => $label ) {
		if ( in_array( $slug, $hidden, true ) ) {
			$buckets['hidden'][ $slug ] = $label;
			continue;
		}

		$group = isset( $assignments[ $slug ] ) ? $assignments[ $slug ] : blueworx_get_default_admin_menu_group( $slug );

		if ( ! isset( $buckets[ $group ] ) ) {
			$group = 'site';
		}

		$buckets[ $group ][ $slug ] = $label;
	}

	$sections            = $groups;
	$sections['hidden']  = __( 'Hidden', 'blueworx-labs-wordpress' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Drag an item into another group to move it, or use the arrow buttons. Items in Hidden do not appear in the sidebar.', 'blueworx-labs-wordpress' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="blueworx_save_admin_menu_order" />
			<?php wp_nonce_field( 'blueworx_save_admin_menu_order' ); ?>

			<div class="bw-menu-editor">
				<?php foreach ( $sections as $group => $label ) : ?>
					<?php blueworx_render_menu_editor_group( $group, $label, $buckets[ $group ] ); ?>
				<?php endforeach; ?>
			</div>

			<?php submit_button( esc_html__( 'Save Menu Settings', 'blueworx-labs-wordpress' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Renders one Edit Menu group section.
 *
 * @param string $group Group key, or "hidden".
 * @param string $label Translated section label.
 * @param array  $items Menu labels keyed by slug.
 * @return void
 */
function blueworx_render_menu_editor_group( $group, $label, $items ) {
	?>
	<section class="bw-menu-editor-group" data-group="<?php echo esc_attr( $group ); ?>">
		<h2 class="bw-menu-editor-group-title"><?php echo esc_html( $label ); ?></h2>
		<ul class="bw-menu-editor-list">
			<?php foreach ( $items as $slug => $item_label ) : ?>
				<li class="bw-menu-editor-item" draggable="true" data-slug="<?php echo esc_attr( $slug ); ?>">
					<span class="bw-menu-editor-handle" aria-hidden="true">⠿</span>
					<span class="bw-menu-editor-label"><?php echo esc_html( $item_label ); ?></span>
					<button type="button" class="button-link bw-menu-editor-up"
						aria-label="<?php /* translators: %s: menu item name. */ echo esc_attr( sprintf( __( 'Move %s up', 'blueworx-labs-wordpress' ), $item_label ) ); ?>">▲</button>
					<button type="button" class="button-link bw-menu-editor-down"
						aria-label="<?php /* translators: %s: menu item name. */ echo esc_attr( sprintf( __( 'Move %s down', 'blueworx-labs-wordpress' ), $item_label ) ); ?>">▼</button>
					<input type="hidden" class="bw-menu-editor-order" name="blueworx_admin_menu_order[]" value="<?php echo esc_attr( $slug ); ?>" />
					<input type="hidden" class="bw-menu-editor-group-input" name="<?php echo esc_attr( 'blueworx_admin_menu_groups[' . $slug . ']' ); ?>" value="<?php echo esc_attr( $group ); ?>" />
					<?php if ( 'hidden' === $group ) : ?>
						<input type="hidden" class="bw-menu-editor-hidden-input" name="blueworx_hidden_admin_menu_items[]" value="<?php echo esc_attr( $slug ); ?>" />
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php
}
```

- [ ] **Step 4: Create the editor stylesheet**

```css
/*
 * BlueWorx Edit Menu screen.
 *
 * Loads on the Edit Menu screen regardless of the admin_theme flag: the screen
 * must be usable even with the theme off, so it cannot depend on admin-theme.css.
 */

.bw-menu-editor {
	max-width: 760px;
}

.bw-menu-editor-group {
	background: #fff;
	border: 1px solid #e0e0e5;
	border-radius: 12px;
	margin-bottom: 16px;
	padding: 12px 16px 16px;
}

.bw-menu-editor-group-title {
	color: #667085;
	font-size: 11px;
	font-weight: 600;
	letter-spacing: 1.2px;
	margin: 0 0 8px;
	text-transform: uppercase;
}

.bw-menu-editor-list {
	margin: 0;
	min-height: 40px;
}

.bw-menu-editor-item {
	align-items: center;
	background: #f7f7fb;
	border: 1px solid #e8e8ee;
	border-radius: 8px;
	cursor: grab;
	display: flex;
	gap: 10px;
	margin: 0 0 6px;
	padding: 8px 12px;
}

.bw-menu-editor-item:last-child {
	margin-bottom: 0;
}

.bw-menu-editor-item.is-dragging {
	opacity: .4;
}

.bw-menu-editor-list.is-drop-target {
	background: #eef0ff;
	border-radius: 8px;
	outline: 2px dashed #4f46e5;
}

.bw-menu-editor-handle {
	color: #98a2b3;
	cursor: grab;
	font-size: 16px;
	line-height: 1;
}

.bw-menu-editor-label {
	flex: 1;
}

.bw-menu-editor-up,
.bw-menu-editor-down {
	color: #667085;
	padding: 2px 6px;
	text-decoration: none;
}

.bw-menu-editor-up:focus,
.bw-menu-editor-down:focus {
	box-shadow: 0 0 0 2px #4f46e5;
	outline: none;
}
```

- [ ] **Step 5: Swap the enqueue**

In `includes/admin-assets.php`, replace the `blueworx_page_blueworx-edit-menu` block (`:89-99`) with:

```php
	if ( 'blueworx_page_blueworx-edit-menu' === $hook_suffix ) {
		wp_enqueue_style(
			'blueworx-labs-wordpress-admin-menu-editor',
			BLUEWORX_LABS_URL . 'assets/css/admin-menu-editor.css',
			array(),
			blueworx_get_admin_asset_version( 'assets/css/admin-menu-editor.css' )
		);

		wp_enqueue_script(
			'blueworx-labs-wordpress-admin-menu-editor',
			BLUEWORX_LABS_URL . 'assets/js/admin-menu-editor.js',
			array(),
			blueworx_get_admin_asset_version( 'assets/js/admin-menu-editor.js' ),
			true
		);

		return;
	}
```

Note: **no jQuery, no jQuery UI.**

- [ ] **Step 6: Delete the old script**

```bash
git rm assets/js/admin-menu-order.js
```

- [ ] **Step 7: Run the test — expect PASS**

```bash
npx playwright test tests/admin-menu-defaults.spec.js -g "Edit Menu renders a section"
```
Expected: **PASS**.

- [ ] **Step 8: Commit**

```bash
git add includes/admin-settings.php includes/admin-assets.php assets/css/admin-menu-editor.css tests/admin-menu-defaults.spec.js
git commit -m "feat: rebuild the Edit Menu screen as stacked group sections

One card per group plus Hidden, mirroring the sidebar's real shape, replacing
the three-column Main/More/Hidden table. Each row carries its group and order as
hidden inputs, so the form degrades to a plain POST.

Up/down buttons ship with the markup rather than being bolted on later: the old
screen was drag-only and therefore unusable by keyboard.

The editor stylesheet loads regardless of admin_theme — the screen must work
with the theme off, so it cannot depend on admin-theme.css.

Removes the jQuery + jQuery UI sortable enqueue; the rebuilt script (next
commit) is dependency-free.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 13: Edit Menu drag + keyboard behaviour

**Files:**
- Create: `assets/js/admin-menu-editor.js`
- Test: `tests/admin-menu-defaults.spec.js`

**Interfaces:**
- Consumes: Task 12's DOM contract.
- Produces: on drop/move, rewrites each row's `blueworx_admin_menu_groups[<slug>]` value and adds/removes its hidden input.

- [ ] **Step 1: Write the failing test**

```js
  test('Edit Menu: keyboard moves an item across a group boundary and persists', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-edit-menu');

    // Move the last Content item down, past the boundary, into Custom Content.
    const item = page.locator('.bw-menu-editor-group[data-group="content"] .bw-menu-editor-item').last();
    const slug = await item.getAttribute('data-slug');
    await item.locator('button.bw-menu-editor-down').click();

    // It left Content.
    await expect(
      page.locator(`.bw-menu-editor-group[data-group="content"] .bw-menu-editor-item[data-slug="${slug}"]`)
    ).toHaveCount(0);

    await page.getByRole('button', { name: 'Save Menu Settings' }).click();
    await expect(page.locator('.notice-success')).toContainText('Menu settings saved');

    // And it stayed moved after the round-trip.
    await expect(
      page.locator(`.bw-menu-editor-group[data-group="content"] .bw-menu-editor-item[data-slug="${slug}"]`)
    ).toHaveCount(0);
  });
```

- [ ] **Step 2: Run it and watch it fail**

```bash
npx playwright test tests/admin-menu-defaults.spec.js -g "keyboard moves an item"
```
Expected: **FAIL** — no script bound.

- [ ] **Step 3: Write the script**

```js
/**
 * BlueWorx Edit Menu.
 *
 * Native HTML5 drag-and-drop plus keyboard arrows. No jQuery, no jQuery UI.
 *
 * Drag is an enhancement; the arrow buttons are the accessible route and work
 * with the pointer too. Every move rewrites the row's hidden inputs so the form
 * stays a plain POST.
 */
( function () {
	var editor = document.querySelector( '.bw-menu-editor' );

	if ( ! editor ) {
		return;
	}

	var dragging = null;

	/**
	 * Syncs a row's hidden inputs to whichever group section it now sits in.
	 *
	 * @param {HTMLElement} item Row element.
	 */
	function syncItem( item ) {
		var section = item.closest( '.bw-menu-editor-group' );

		if ( ! section ) {
			return;
		}

		var group = section.getAttribute( 'data-group' );
		var slug = item.getAttribute( 'data-slug' );
		var groupInput = item.querySelector( '.bw-menu-editor-group-input' );
		var hiddenInput = item.querySelector( '.bw-menu-editor-hidden-input' );

		if ( groupInput ) {
			groupInput.name = 'blueworx_admin_menu_groups[' + slug + ']';
			groupInput.value = group;
		}

		// The hidden bucket is expressed by a second input, present only there.
		if ( 'hidden' === group && ! hiddenInput ) {
			var input = document.createElement( 'input' );
			input.type = 'hidden';
			input.className = 'bw-menu-editor-hidden-input';
			input.name = 'blueworx_hidden_admin_menu_items[]';
			input.value = slug;
			item.appendChild( input );
		} else if ( 'hidden' !== group && hiddenInput ) {
			hiddenInput.remove();
		}
	}

	function syncAll() {
		editor.querySelectorAll( '.bw-menu-editor-item' ).forEach( syncItem );
	}

	/**
	 * Gets every group list, in document order.
	 *
	 * @return {HTMLElement[]} Lists.
	 */
	function lists() {
		return Array.prototype.slice.call( editor.querySelectorAll( '.bw-menu-editor-list' ) );
	}

	/**
	 * Moves a row one step, crossing into the adjacent group at a boundary.
	 *
	 * @param {HTMLElement} item      Row element.
	 * @param {number}      direction -1 up, 1 down.
	 */
	function move( item, direction ) {
		var list = item.parentElement;
		var sibling = direction < 0 ? item.previousElementSibling : item.nextElementSibling;

		if ( sibling ) {
			if ( direction < 0 ) {
				list.insertBefore( item, sibling );
			} else {
				list.insertBefore( sibling, item );
			}
		} else {
			// At a boundary: hop into the neighbouring group.
			var all = lists();
			var index = all.indexOf( list );
			var target = all[ index + direction ];

			if ( ! target ) {
				return;
			}

			if ( direction < 0 ) {
				target.appendChild( item );
			} else {
				target.insertBefore( item, target.firstElementChild );
			}
		}

		syncItem( item );
	}

	editor.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.bw-menu-editor-up, .bw-menu-editor-down' );

		if ( ! button ) {
			return;
		}

		event.preventDefault();

		var item = button.closest( '.bw-menu-editor-item' );
		// classList.contains takes a class NAME, not a selector — no leading dot.
		move( item, button.classList.contains( 'bw-menu-editor-up' ) ? -1 : 1 );
		button.focus();
	} );

	editor.addEventListener( 'dragstart', function ( event ) {
		var item = event.target.closest( '.bw-menu-editor-item' );

		if ( ! item ) {
			return;
		}

		dragging = item;
		item.classList.add( 'is-dragging' );
		event.dataTransfer.effectAllowed = 'move';
		// Firefox will not start a drag without data set.
		event.dataTransfer.setData( 'text/plain', item.getAttribute( 'data-slug' ) );
	} );

	editor.addEventListener( 'dragend', function () {
		if ( dragging ) {
			dragging.classList.remove( 'is-dragging' );
			dragging = null;
		}

		editor.querySelectorAll( '.is-drop-target' ).forEach( function ( el ) {
			el.classList.remove( 'is-drop-target' );
		} );
	} );

	editor.addEventListener( 'dragover', function ( event ) {
		var list = event.target.closest( '.bw-menu-editor-list' );

		if ( ! list || ! dragging ) {
			return;
		}

		// Required, or the browser refuses the drop.
		event.preventDefault();
		event.dataTransfer.dropEffect = 'move';
		list.classList.add( 'is-drop-target' );

		var over = event.target.closest( '.bw-menu-editor-item' );

		if ( over && over !== dragging ) {
			var box = over.getBoundingClientRect();
			var after = event.clientY > box.top + box.height / 2;
			list.insertBefore( dragging, after ? over.nextElementSibling : over );
		} else if ( ! over ) {
			list.appendChild( dragging );
		}
	} );

	editor.addEventListener( 'dragleave', function ( event ) {
		var list = event.target.closest( '.bw-menu-editor-list' );

		if ( list && ! list.contains( event.relatedTarget ) ) {
			list.classList.remove( 'is-drop-target' );
		}
	} );

	editor.addEventListener( 'drop', function ( event ) {
		event.preventDefault();

		if ( dragging ) {
			syncItem( dragging );
		}
	} );

	syncAll();
}() );
```

- [ ] **Step 4: Cover the "up" direction too**

Step 1's test only exercises "down". A `classList.contains` typo (passing
`'.bw-menu-editor-up'` with a leading dot, which always returns false) would make
every arrow move down while that test still passed. Add the missing direction:

```js
  test('Edit Menu: the up button moves an item up', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-edit-menu');

    const list = page.locator('.bw-menu-editor-group[data-group="site"] .bw-menu-editor-item');
    const second = list.nth(1);
    const slug = await second.getAttribute('data-slug');

    await second.locator('button.bw-menu-editor-up').click();

    await expect(list.first()).toHaveAttribute('data-slug', slug);
  });
```

- [ ] **Step 5: Run both tests — expect PASS**

```bash
npx playwright test tests/admin-menu-defaults.spec.js -g "Edit Menu"
```
Expected: **PASS**.

- [ ] **Step 6: Test by hand, keyboard first**

Tab to an arrow button and move an item across a boundary — focus must stay on the button. Then drag with the mouse: the drop target highlights, the row lands where dropped. Confirm in Firefox too (it needs `setData`, which is why it is there).

- [ ] **Step 7: Lint**

```bash
npm run lint
```
Expected: clean. Present any findings to Luke; do not loop.

- [ ] **Step 8: Commit**

```bash
git add assets/js/admin-menu-editor.js tests/admin-menu-defaults.spec.js
git commit -m "feat: Edit Menu drag and keyboard reordering, without jQuery UI

Native HTML5 drag-and-drop plus arrow buttons, replacing jQuery UI sortable.
Drag is the enhancement; the arrows are the accessible route and cross group
boundaries, so the screen is fully keyboard-operable — the old one was
drag-only.

Every move rewrites the row's hidden inputs, so the form stays a plain POST with
no client-side state to desynchronise.

dataTransfer.setData is required or Firefox refuses to start a drag;
preventDefault on dragover is required or the browser refuses the drop.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 14: Edit Menu save handler

**Files:**
- Modify: `includes/admin-settings.php:67-93`
- Test: `tests/admin-menu-defaults.spec.js`

**Interfaces:**
- Consumes: Task 12's input names.
- Produces: writes `blueworx_admin_menu_groups`, `blueworx_admin_menu_order`, `blueworx_hidden_admin_menu_items`, `blueworx_admin_menu_customized`.

- [ ] **Step 1: Write the failing test**

```js
  test('Edit Menu: hiding an item removes it from the sidebar', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-edit-menu');

    // Drag is hard to script reliably; drive the same code path via the inputs.
    await page.evaluate(() => {
      const item = document.querySelector('.bw-menu-editor-group[data-group="site"] .bw-menu-editor-item[data-slug="tools.php"]');
      const hiddenList = document.querySelector('.bw-menu-editor-group[data-group="hidden"] .bw-menu-editor-list');
      hiddenList.appendChild(item);
      item.dispatchEvent(new Event('drop', { bubbles: true }));
    });

    await page.getByRole('button', { name: 'Save Menu Settings' }).click();
    await expect(page.locator('.notice-success')).toContainText('Menu settings saved');

    await page.goto('/wp-admin/index.php');
    await expect(page.locator('#adminmenu a[href="tools.php"]')).toHaveCount(0);

    // Restore, so the test is idempotent across runs.
    await page.goto('/wp-admin/admin.php?page=blueworx-edit-menu');
    await page.evaluate(() => {
      const item = document.querySelector('.bw-menu-editor-item[data-slug="tools.php"]');
      const siteList = document.querySelector('.bw-menu-editor-group[data-group="site"] .bw-menu-editor-list');
      siteList.appendChild(item);
      item.dispatchEvent(new Event('drop', { bubbles: true }));
    });
    await page.getByRole('button', { name: 'Save Menu Settings' }).click();
  });
```

- [ ] **Step 2: Run it and watch it fail**

```bash
npx playwright test tests/admin-menu-defaults.spec.js -g "hiding an item"
```
Expected: **FAIL** — groups are not persisted.

- [ ] **Step 3: Rewrite the handler**

Replace `blueworx_save_edit_menu_page()` (`admin-settings.php:67-90`) with:

```php
function blueworx_save_edit_menu_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_save_admin_menu_order' );

	$raw_order  = isset( $_POST['blueworx_admin_menu_order'] ) ? (array) wp_unslash( $_POST['blueworx_admin_menu_order'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with sanitize_text_field.
	$raw_hidden = isset( $_POST['blueworx_hidden_admin_menu_items'] ) ? (array) wp_unslash( $_POST['blueworx_hidden_admin_menu_items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
	$raw_groups = isset( $_POST['blueworx_admin_menu_groups'] ) ? (array) wp_unslash( $_POST['blueworx_admin_menu_groups'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.

	$order  = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $raw_order ) ) ) );
	$hidden = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $raw_hidden ) ) ) );

	// Only accept groups this build knows about; anything else is dropped rather
	// than stored, so a stale or forged POST cannot create a phantom group.
	$known  = blueworx_get_admin_menu_groups();
	$groups = array();

	foreach ( $raw_groups as $slug => $group ) {
		$slug  = sanitize_text_field( (string) $slug );
		$group = sanitize_key( (string) $group );

		if ( '' === $slug || 'hidden' === $group ) {
			continue;
		}

		if ( isset( $known[ $group ] ) ) {
			$groups[ $slug ] = $group;
		}
	}

	update_option( 'blueworx_admin_menu_order', $order );
	update_option( 'blueworx_hidden_admin_menu_items', $hidden );
	update_option( 'blueworx_admin_menu_groups', $groups );
	update_option( 'blueworx_admin_menu_customized', '1' );
	set_transient( 'blueworx_admin_menu_order_notice', __( 'Menu settings saved.', 'blueworx-labs-wordpress' ), 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-edit-menu' ) );
	exit;
}
```

- [ ] **Step 4: Run the test — expect PASS**

```bash
npx playwright test tests/admin-menu-defaults.spec.js -g "hiding an item"
```
Expected: **PASS**.

- [ ] **Step 5: Run the whole menu suite**

```bash
npx playwright test tests/admin-menu-defaults.spec.js
```
Expected: **all PASS**.

- [ ] **Step 6: Commit**

```bash
git add includes/admin-settings.php tests/admin-menu-defaults.spec.js
git commit -m "feat: persist Edit Menu group assignments

Saves slug => group alongside order and hidden. Group values are validated
against the known group keys and anything unrecognised is dropped rather than
stored, so a stale or forged POST cannot create a phantom group.

'hidden' is expressed by the hidden-items option, not by a group assignment, so
the two cannot contradict each other.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Phase 6 — Release

### Task 15: Flag matrix, changelog, and full verification

**Files:**
- Modify: `CHANGELOG.md`, `readme.txt`
- Test: `tests/admin-theme.spec.js`

- [ ] **Step 1: Write the flag-matrix test**

Spec §1's matrix is the contract. Add:

```js
  test('admin_theme off: no headings, no icons, no badges, stock menu', async ({ page }) => {
    await login(page);

    let toggle = await themeToggle(page);
    await toggle.setChecked(false);
    await saveSettings(page);

    await page.goto(DASH_PATH);
    await expect(page.locator('#adminmenu .bw-menu-group')).toHaveCount(0);
    await expect(page.locator('#adminmenu .bw-menu-icon')).toHaveCount(0);
    await expect(page.locator('#adminmenu .bw-badge')).toHaveCount(0);
    await expect(page.locator('#adminmenu .bw-logout')).toHaveCount(0);

    // Restore ON.
    toggle = await themeToggle(page);
    await toggle.setChecked(true);
    await saveSettings(page);
  });
```

- [ ] **Step 2: Run it**

```bash
npx playwright test tests/admin-theme.spec.js -g "admin_theme off"
```
Expected: **PASS** (every renderer is already gated). A failure means a gate is missing — fix the gate, not the test.

- [ ] **Step 3: Extend the existing changelog entry**

**Do not add a new version heading.** Under the existing `## [1.12.0] - 2026-07-15`, add to `### Added`:

```markdown
- **Sidebar semantic groups.** The menu is grouped Overview / Content / Custom
  Content / Site, matching the design. Custom post types are detected
  automatically; unrecognised plugin menus fall into Site.
- **Design icon set.** Core menu items use the design's stroked icons in place of
  dashicons. Third-party plugin menus keep their own icon.
- **Count badges.** Published posts, pages, media, custom post types, and active
  plugins show real counts. Items with a count of zero show no badge.
- **Log Out** at the foot of the sidebar.
```

To `### Changed`:

```markdown
- **Edit Menu rebuilt.** One section per group plus Hidden, with drag-and-drop
  and arrow buttons. The screen is now fully keyboard-operable; the previous one
  was drag-only. jQuery UI is no longer loaded on this screen.
- **Settings screens get card containers.** Cache, Headless, Settings → General
  and third-party settings screens render their settings tables as cards.
  Enhancements' sections are constrained to a readable width instead of
  stretching the full viewport.
```

To `### Removed`:

```markdown
- **The More menu.** Replaced by the semantic groups. Items previously in More
  return to the sidebar in their natural group — see the upgrade notice.
```

To `### Fixed`:

```markdown
- **Brand block overhanging the top bar.** `.bw-brand` was `content-box`, so its
  padding was added to its width and 24px of charcoal painted over the top bar.
  The folded menu had the same defect.
- **Sidebar hover and active states blending.** Hovering the current item
  composited a translucent white over the translucent indigo pill, producing a
  third colour. State colour now lives on one element, and the active pill is
  opaque.
```

- [ ] **Step 4: Add the upgrade notice**

In `readme.txt`, mirror the changelog and add:

```
== Upgrade Notice ==

= 1.12.0 =
The More menu has been replaced by grouped navigation. Any items you had moved
into More now appear in the sidebar again, in their matching group (Content,
Site, and so on). Items you had hidden stay hidden. You can regroup or hide
anything from BlueWorx > Edit Menu.
```

- [ ] **Step 5: Verify versions still agree**

```bash
npm run version:check
```
Expected: `version:check OK — plugin header and package.json agree (1.12.0)`.
If this reports anything other than 1.12.0, someone bumped it — revert that; see Global Constraints.

- [ ] **Step 6: Full suite**

```bash
npx playwright test
```
Expected: **all PASS**. Report anything skipped and why.

- [ ] **Step 7: Lint, once**

```bash
npm run lint
composer lint
```
Collect findings. **Present them to Luke; do not fix without approval.**

- [ ] **Step 8: Commit**

```bash
git add CHANGELOG.md readme.txt tests/admin-theme.spec.js
git commit -m "docs: changelog and upgrade notice for the re-skin refinements

Extends the existing unreleased 1.12.0 entry rather than opening a new one —
main is 1.11.0 and the bump already happened on this branch in 81d0599, so the
guardrail is satisfied and a second heading would be wrong.

Upgrade notice calls out the visible change: items previously in More return to
the sidebar. Hidden items stay hidden.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 9: Build and verify the zip**

```bash
npm run build
unzip -l ../blueworx-labs-wordpress.zip | head -20
```
Every entry must read `blueworx-labs-wordpress/...` with **forward slashes**, nested one level. Any `\` means the zip is broken — rebuild with bsdtar per the global rules. Never hand over a zip you have not listed.

---

## Self-Review

**Spec coverage:**

| Spec section | Task |
|---|---|
| §1 Sidebar groups | 4, 6 |
| §1 Flag matrix | 6, 15 |
| §2 Icons | 7 |
| §3 Badges, Log Out | 8, 9 |
| §4 Hover overlap | 2 |
| §5 The jutt | 1 |
| §6 Edit Menu + migration | 5, 11, 12, 13, 14 |
| §7 Containers | 3 |
| Accessibility | 6 (inert headings), 8 (badge labels), 9, 10, 13 (keyboard) |
| Testing 1–10 | 6, 6, 8, 9, 2, 1, 13, 5, 15, 3 |
| Versioning | 15 |

No spec requirement is unassigned. The storage meter is correctly absent (cut).

**Type/name consistency:** `blueworx_get_admin_menu_groups()`, `blueworx_get_default_admin_menu_group()`, `blueworx_get_admin_menu_group_assignments()`, `blueworx_get_admin_menu_icon()`, `blueworx_get_admin_menu_badge_counts()` are used with the same names and signatures across Tasks 4–14. `blueworx_print_admin_menu_icons()` (Task 7) is explicitly superseded by `blueworx_print_admin_menu_decorations()` (Task 8) — Task 8 Step 5 deletes it rather than leaving both bound to `admin_footer`.

**Known risks carried into implementation:**

1. **Task 6 Step 3 may force the fallback.** Core's `_wp_menu_output()` may refuse to render an inert heading row. The `::before` fallback is specced; the implementer must choose and report.
2. **Tasks 7–9 render via `admin_footer` scripts.** This works but is not elegant, and can flash. If a server-side route exists, take it (noted inline).
3. **Every test needs a live WordPress.** See Test Harness Reality. A skipped test is not a passing test.
