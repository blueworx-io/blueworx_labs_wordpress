# BlueWorx Admin Re-skin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (inline, chosen) to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Re-skin wp-admin + wp-login with the BlueWorx design system as a toggleable, CSS-first feature inside `blueworx_labs_wordpress`, with a hybrid custom/native Dashboard.

**Architecture:** A new `includes/admin-theme.php` registers a global (all-screens) admin stylesheet enqueue, a login stylesheet enqueue, and `wp_dashboard_setup` customisation — all gated on a new `admin_theme` feature flag (default on). Styling maps BlueWorx tokens onto WordPress's own native selectors; the only new markup is one Dashboard hero-tiles widget.

**Tech Stack:** WordPress plugin PHP (procedural, WPCS), CSS custom properties, self-hosted woff2 (Sora + Inter), Playwright (existing harness).

## Global Constraints

- WordPress plugin coding standards (WPCS / phpcs.xml.dist); every file starts with the `ABSPATH` guard.
- No new runtime dependency (nothing added to `approved-deps.json`); fonts are self-hosted static assets, not a package.
- Feature defaults **on**: `blueworx_feature_enabled()` treats an absent option as enabled.
- Version bump 1.10.1 → **1.11.0** (minor) across plugin header, `BLUEWORX_LABS_VERSION`, `package.json`, `readme.txt` Stable tag; CHANGELOG + readme.txt changelog updated alongside.
- Text domain `blueworx-labs-wordpress`; all user-facing strings translated + escaped on output.
- Work on branch `admin-reskin`; PR into `main`.

---

## File Structure

- `includes/features.php` (modify) — add `appearance` section + `admin_theme` feature.
- `includes/admin-theme.php` (create) — enqueue (admin + login) and Dashboard customisation, gated on the flag.
- `blueworx-labs-wordpress.php` (modify) — `require_once` the new module; bump version.
- `assets/css/blueworx-fonts.css` (create) — `@font-face` for self-hosted fonts.
- `assets/css/admin-theme.css` (create) — the wp-admin re-skin.
- `assets/css/login-theme.css` (create) — the login re-skin.
- `assets/fonts/*.woff2` (create) — Sora 400/600/700, Inter 400/500/600 (latin subset).
- `tests/admin-theme.spec.js` (create) — enqueue + toggle assertions.
- `CHANGELOG.md`, `readme.txt`, `package.json` (modify) — version + changelog.

---

### Task 1: Register the `admin_theme` feature flag

**Files:**
- Modify: `includes/features.php`
- Test: `tests/admin-theme.spec.js` (created in Task 7; behaviour verified there)

**Interfaces:**
- Produces: option key `blueworx_feature_admin_theme`, readable via existing `blueworx_feature_enabled( 'admin_theme' )`. New settings section id `appearance`.

- [ ] **Step 1:** In `blueworx_get_feature_sections()`, append after `admin_menu`:
```php
'appearance'    => __( 'Appearance', 'blueworx-labs-wordpress' ),
```

- [ ] **Step 2:** In `blueworx_get_feature_definitions()`, append after the `menu_editor` entry:
```php
'admin_theme'           => array(
	'label'       => __( 'BlueWorx admin theme', 'blueworx-labs-wordpress' ),
	'description' => __( 'Restyles the WordPress admin and login screens with the BlueWorx look. Purely visual; turn off to return to the standard WordPress appearance.', 'blueworx-labs-wordpress' ),
	'section'     => 'appearance',
	'detail'      => null,
),
```

- [ ] **Step 3:** Sanity-check no PHP syntax error:
```bash
php -l includes/features.php
```
Expected: `No syntax errors detected`. (If `php` is unavailable locally, rely on CI phpcs/lint.)

- [ ] **Step 4:** Commit:
```bash
git add includes/features.php
git commit -m "feat: register admin_theme feature flag and Appearance section"
```

---

### Task 2: Self-host Sora + Inter and declare `@font-face`

**Files:**
- Create: `assets/fonts/*.woff2`
- Create: `assets/css/blueworx-fonts.css`

**Interfaces:**
- Produces: font families `"Sora"` (400/600/700) and `"Inter"` (400/500/600), available once `blueworx-fonts.css` is enqueued.

- [ ] **Step 1:** Download the latin-subset woff2 for each weight. Fetch the Google Fonts CSS with a modern UA, extract the `latin` block URLs, and save with stable local names:
```bash
mkdir -p assets/fonts
UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36"
# Per family/weight: the /* latin */ block's woff2 (not latin-ext/cyrillic/greek/vietnamese).
# Save as: sora-400.woff2 sora-600.woff2 sora-700.woff2 inter-400.woff2 inter-500.woff2 inter-600.woff2
```
Use `scripts/fetch-fonts.sh` (create it — see Step 2) to make this reproducible.

- [ ] **Step 2:** Create `scripts/fetch-fonts.sh` that fetches each family/weight CSS individually (`&text=` omitted, `&subset=latin` not honoured by css2, so parse the `/* latin */` block) and downloads only the latin woff2:
```bash
#!/usr/bin/env bash
set -euo pipefail
UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36"
OUT="assets/fonts"; mkdir -p "$OUT"
fetch() { # family weight outname
  local css url
  css="$(curl -fsSL -A "$UA" "https://fonts.googleapis.com/css2?family=$1:wght@$2&display=swap")"
  # take the woff2 URL immediately following the "/* latin */" comment
  url="$(printf '%s\n' "$css" | awk '/\/\* latin \*\//{f=1} f&&/woff2/{match($0,/https:[^)]+woff2/); print substr($0,RSTART,RLENGTH); exit}')"
  curl -fsSL -A "$UA" "$url" -o "$OUT/$3"
  echo "saved $OUT/$3"
}
fetch Sora 400 sora-400.woff2
fetch Sora 600 sora-600.woff2
fetch Sora 700 sora-700.woff2
fetch Inter 400 inter-400.woff2
fetch Inter 500 inter-500.woff2
fetch Inter 600 inter-600.woff2
```
Run: `bash scripts/fetch-fonts.sh` and confirm 6 non-empty files:
```bash
ls -l assets/fonts/*.woff2
```
Expected: 6 files, each > 5 KB.

- [ ] **Step 3:** Create `assets/css/blueworx-fonts.css` (paths relative to `assets/css/`):
```css
/* BlueWorx self-hosted fonts — Sora + Inter (latin subset) */
@font-face{font-family:"Sora";font-style:normal;font-weight:400;font-display:swap;src:url("../fonts/sora-400.woff2") format("woff2");}
@font-face{font-family:"Sora";font-style:normal;font-weight:600;font-display:swap;src:url("../fonts/sora-600.woff2") format("woff2");}
@font-face{font-family:"Sora";font-style:normal;font-weight:700;font-display:swap;src:url("../fonts/sora-700.woff2") format("woff2");}
@font-face{font-family:"Inter";font-style:normal;font-weight:400;font-display:swap;src:url("../fonts/inter-400.woff2") format("woff2");}
@font-face{font-family:"Inter";font-style:normal;font-weight:500;font-display:swap;src:url("../fonts/inter-500.woff2") format("woff2");}
@font-face{font-family:"Inter";font-style:normal;font-weight:600;font-display:swap;src:url("../fonts/inter-600.woff2") format("woff2");}
```

- [ ] **Step 4:** Commit:
```bash
git add assets/fonts assets/css/blueworx-fonts.css scripts/fetch-fonts.sh
git commit -m "feat: self-host Sora + Inter woff2 with @font-face"
```

---

### Task 3: Enqueue module + wire into plugin

**Files:**
- Create: `includes/admin-theme.php`
- Modify: `blueworx-labs-wordpress.php` (add `require_once`)

**Interfaces:**
- Consumes: `blueworx_feature_enabled( 'admin_theme' )`, `blueworx_get_admin_asset_version()`, `BLUEWORX_LABS_URL`.
- Produces: enqueued handles `blueworx-admin-fonts`, `blueworx-admin-theme` (admin), `blueworx-login-theme` (login). Dashboard hook in Task 6 lives in this file.

- [ ] **Step 1:** Create `includes/admin-theme.php` with the ABSPATH guard, the enqueue functions, and hooks:
```php
<?php
/**
 * BlueWorx admin & login re-skin (CSS-first).
 *
 * @package BlueWorxLabs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the BlueWorx admin theme is active.
 *
 * @return bool
 */
function blueworx_admin_theme_enabled() {
	return blueworx_feature_enabled( 'admin_theme' );
}

/**
 * Enqueues the admin re-skin on every admin screen.
 *
 * @return void
 */
function blueworx_enqueue_admin_theme() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	wp_enqueue_style(
		'blueworx-admin-fonts',
		BLUEWORX_LABS_URL . 'assets/css/blueworx-fonts.css',
		array(),
		blueworx_get_admin_asset_version( 'assets/css/blueworx-fonts.css' )
	);

	wp_enqueue_style(
		'blueworx-admin-theme',
		BLUEWORX_LABS_URL . 'assets/css/admin-theme.css',
		array( 'blueworx-admin-fonts' ),
		blueworx_get_admin_asset_version( 'assets/css/admin-theme.css' )
	);
}
add_action( 'admin_enqueue_scripts', 'blueworx_enqueue_admin_theme' );

/**
 * Enqueues the login re-skin.
 *
 * @return void
 */
function blueworx_enqueue_login_theme() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	wp_enqueue_style(
		'blueworx-admin-fonts',
		BLUEWORX_LABS_URL . 'assets/css/blueworx-fonts.css',
		array(),
		blueworx_get_admin_asset_version( 'assets/css/blueworx-fonts.css' )
	);

	wp_enqueue_style(
		'blueworx-login-theme',
		BLUEWORX_LABS_URL . 'assets/css/login-theme.css',
		array( 'blueworx-admin-fonts' ),
		blueworx_get_admin_asset_version( 'assets/css/login-theme.css' )
	);
}
add_action( 'login_enqueue_scripts', 'blueworx_enqueue_login_theme' );
```

- [ ] **Step 2:** In `blueworx-labs-wordpress.php`, add after the `admin-assets.php` require (line ~43):
```php
require_once BLUEWORX_LABS_PATH . 'includes/admin-theme.php';
```

- [ ] **Step 3:** `php -l includes/admin-theme.php` → `No syntax errors detected` (or rely on CI).

- [ ] **Step 4:** Commit:
```bash
git add includes/admin-theme.php blueworx-labs-wordpress.php
git commit -m "feat: enqueue BlueWorx admin + login theme, gated on feature flag"
```

---

### Task 4: The wp-admin stylesheet

**Files:**
- Create: `assets/css/admin-theme.css`

**Interfaces:**
- Consumes: `"Sora"`, `"Inter"` families (Task 2).
- Produces: the visual re-skin. No JS. Uses `--bw-*` custom properties declared on `:root`.

Follow the token → selector map in the spec (`docs/superpowers/specs/2026-07-14-admin-reskin-design.md` §"Token → WordPress selector map"). Structure the file in labelled blocks:

- [ ] **Step 1:** Tokens block — `:root { --bw-primary:#4F46E5; --bw-primary-dark:#4338CA; --bw-charcoal:#0A0C29; --bw-lavender:#E8E7F7; --bw-surface:#F5F6FF; --bw-body:#4C4C4C; --bw-muted:#667085; --bw-border:#EFEFF0; --bw-success:#01824C; --bw-error:#FF302F; --bw-warning:#FFC107; --bw-info:#3686F7; --bw-radius-card:16px; --bw-radius-btn:11px; --bw-radius-input:8px; --bw-shadow-card:0 7px 7px rgba(0,0,0,.09),0 16px 9px rgba(0,0,0,.05); --bw-font-head:"Helvetica Neue",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; --bw-font-ui:"Inter",-apple-system,sans-serif; --bw-font-body:"Sora",-apple-system,sans-serif; }`

- [ ] **Step 2:** Base — `body.wp-admin{background:var(--bw-surface);font-family:var(--bw-font-ui);color:var(--bw-body);}` `#wpwrap,#wpbody-content{background:var(--bw-surface);}` `a{color:var(--bw-primary);}` `a:hover{color:var(--bw-primary-dark);}`

- [ ] **Step 3:** Sidebar (`#adminmenuback,#adminmenuwrap,#adminmenu{background:var(--bw-charcoal);}`), muted-white labels, indigo icons (`#adminmenu .wp-menu-image:before{color:var(--bw-primary);}`), rounded active pill (`#adminmenu li.menu-top.current, #adminmenu li.wp-has-current-submenu>a.wp-has-current-submenu{background:rgba(79,70,229,.22)!important;color:#fff!important;border-radius:10px;}`), submenu backgrounds, `#collapse-menu` colour. Keep hover states legible (AA).

- [ ] **Step 4:** Admin bar (`#wpadminbar{background:#fff;border-bottom:1px solid var(--bw-border);box-shadow:none;}` `#wpadminbar .ab-item,#wpadminbar a.ab-item,#wpadminbar>#wp-toolbar span.ab-label,#wpadminbar .ab-icon:before,#wpadminbar .ab-item:before{color:var(--bw-charcoal)!important;}` hover `#wpadminbar .ab-top-menu>li:hover>.ab-item{background:var(--bw-surface)!important;color:var(--bw-primary)!important;}`).

- [ ] **Step 5:** Cards & tables — `.postbox,#dashboard-widgets .postbox{background:#fff;border:none;border-radius:var(--bw-radius-card);box-shadow:var(--bw-shadow-card);}` `.wp-list-table{background:#fff;border:none;border-radius:var(--bw-radius-card);box-shadow:var(--bw-shadow-card);overflow:hidden;}` `.wp-list-table thead th,.wp-list-table thead td{background:var(--bw-surface);color:var(--bw-muted);}` row borders `#EFEFF0`.

- [ ] **Step 6:** Buttons — `.button-primary{background:var(--bw-charcoal);border-color:var(--bw-charcoal);border-radius:var(--bw-radius-btn);box-shadow:none;}` hover → `var(--bw-primary-dark)`; `.button,.button-secondary{border-radius:var(--bw-radius-btn);border-color:var(--bw-charcoal);color:var(--bw-charcoal);background:#fff;}` `.page-title-action` same as primary.

- [ ] **Step 7:** Inputs — `.form-table input[type=text],input[type=email],input[type=url],input[type=search],input[type=password],input[type=number],select,textarea{border:1px solid var(--bw-border);border-radius:var(--bw-radius-input);}` focus `:focus{border-color:var(--bw-primary);box-shadow:0 0 0 2px rgba(79,70,229,.25);outline:2px solid transparent;}` (keeps a visible focus indicator).

- [ ] **Step 8:** Headings & notices — `.wrap h1,.wrap h2,#wpbody h1{font-family:var(--bw-font-head);color:var(--bw-charcoal);}` `.notice{border-radius:12px;border-left-width:4px;box-shadow:var(--bw-shadow-card);}` map `.notice-success/-error/-warning/-info` left-border to semantic tokens.

- [ ] **Step 9:** Dashboard hero tiles (styles for markup added in Task 6) — `.bw-stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;}` `.bw-stat-card{background:#fff;border-radius:var(--bw-radius-card);box-shadow:var(--bw-shadow-card);padding:20px;}` `.bw-stat-num{font-family:var(--bw-font-head);font-weight:700;font-size:28px;color:var(--bw-charcoal);}` `.bw-stat-label{font-size:13px;color:var(--bw-muted);margin-top:4px;}` `#blueworx_dashboard_stats.postbox{background:transparent;box-shadow:none;}` `#blueworx_dashboard_stats .postbox-header{display:none;}`

- [ ] **Step 10:** Commit:
```bash
git add assets/css/admin-theme.css
git commit -m "feat: BlueWorx wp-admin stylesheet (native-selector re-skin)"
```

---

### Task 5: The login stylesheet

**Files:**
- Create: `assets/css/login-theme.css`

- [ ] **Step 1:** Create `assets/css/login-theme.css`:
```css
/* BlueWorx login re-skin — colours & layout only (default WP logo kept) */
:root{--bw-primary:#4F46E5;--bw-primary-dark:#4338CA;--bw-charcoal:#0A0C29;--bw-surface:#F5F6FF;--bw-border:#EFEFF0;}
body.login{background:var(--bw-surface);font-family:"Inter",-apple-system,sans-serif;}
body.login #login{padding-top:8vh;}
.login form{background:#fff;border:none;border-radius:16px;box-shadow:0 7px 7px rgba(0,0,0,.09),0 16px 9px rgba(0,0,0,.05);padding:26px 24px;}
.login label{color:var(--bw-charcoal);font-size:13px;}
.login input[type=text],.login input[type=password]{border:1px solid var(--bw-border);border-radius:8px;}
.login input[type=text]:focus,.login input[type=password]:focus{border-color:var(--bw-primary);box-shadow:0 0 0 2px rgba(79,70,229,.25);}
.wp-core-ui .button-primary{background:var(--bw-charcoal);border-color:var(--bw-charcoal);border-radius:11px;box-shadow:none;}
.wp-core-ui .button-primary:hover,.wp-core-ui .button-primary:focus{background:var(--bw-primary-dark);border-color:var(--bw-primary-dark);}
.login #nav a,.login #backtoblog a{color:var(--bw-primary)!important;}
.login #nav a:hover,.login #backtoblog a:hover{color:var(--bw-primary-dark)!important;}
```

- [ ] **Step 2:** Commit:
```bash
git add assets/css/login-theme.css
git commit -m "feat: BlueWorx login screen re-skin"
```

---

### Task 6: Hybrid Dashboard (hero tiles + native widget trim)

**Files:**
- Modify: `includes/admin-theme.php`

**Interfaces:**
- Consumes: `blueworx_admin_theme_enabled()`.
- Produces: dashboard widget `blueworx_dashboard_stats`; removal of Welcome / Events-News / At-a-Glance.

- [ ] **Step 1:** Append to `includes/admin-theme.php` a `wp_dashboard_setup` handler that removes the non-mockup widgets, registers the hero widget, and moves it to the top:
```php
/**
 * Customises the Dashboard to the BlueWorx layout.
 *
 * @return void
 */
function blueworx_customise_dashboard() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	remove_action( 'welcome_panel', 'wp_welcome_panel' );
	remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );   // WordPress Events & News.
	remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' ); // At a Glance (replaced by hero tiles).

	wp_add_dashboard_widget(
		'blueworx_dashboard_stats',
		__( 'At a Glance', 'blueworx-labs-wordpress' ),
		'blueworx_render_dashboard_stats'
	);

	// Move the hero tiles to the top of the normal column.
	global $wp_meta_boxes;
	if ( isset( $wp_meta_boxes['dashboard']['normal']['core']['blueworx_dashboard_stats'] ) ) {
		$widget = $wp_meta_boxes['dashboard']['normal']['core']['blueworx_dashboard_stats'];
		unset( $wp_meta_boxes['dashboard']['normal']['core']['blueworx_dashboard_stats'] );
		$wp_meta_boxes['dashboard']['normal']['core'] = array( 'blueworx_dashboard_stats' => $widget )
			+ $wp_meta_boxes['dashboard']['normal']['core'];
	}
}
add_action( 'wp_dashboard_setup', 'blueworx_customise_dashboard', 20 );

/**
 * Renders the four hero stat tiles with live counts.
 *
 * @return void
 */
function blueworx_render_dashboard_stats() {
	$posts       = (int) wp_count_posts( 'post' )->publish;
	$pages       = (int) wp_count_posts( 'page' )->publish;
	$comments    = (int) wp_count_comments()->approved;
	$attachments = array_sum( (array) wp_count_attachments() );

	$tiles = array(
		array( $posts, __( 'Posts', 'blueworx-labs-wordpress' ), admin_url( 'edit.php' ) ),
		array( $pages, __( 'Pages', 'blueworx-labs-wordpress' ), admin_url( 'edit.php?post_type=page' ) ),
		array( $comments, __( 'Comments', 'blueworx-labs-wordpress' ), admin_url( 'edit-comments.php' ) ),
		array( $attachments, __( 'Media Items', 'blueworx-labs-wordpress' ), admin_url( 'upload.php' ) ),
	);

	echo '<div class="bw-stat-grid">';
	foreach ( $tiles as $tile ) {
		printf(
			'<a class="bw-stat-card" href="%1$s"><div class="bw-stat-num">%2$s</div><div class="bw-stat-label">%3$s</div></a>',
			esc_url( $tile[2] ),
			esc_html( number_format_i18n( $tile[0] ) ),
			esc_html( $tile[1] )
		);
	}
	echo '</div>';
}
```

- [ ] **Step 2:** Add `.bw-stat-card` link styling to `assets/css/admin-theme.css` (append to the Task 4 hero block): `.bw-stat-card{display:block;text-decoration:none;transition:box-shadow .2s ease;} .bw-stat-card:hover{text-decoration:none;box-shadow:0 10px 20px rgba(10,12,41,.12);}`

- [ ] **Step 3:** `php -l includes/admin-theme.php` → no syntax errors (or rely on CI).

- [ ] **Step 4:** Commit:
```bash
git add includes/admin-theme.php assets/css/admin-theme.css
git commit -m "feat: hybrid BlueWorx dashboard with live hero stat tiles"
```

---

### Task 7: Playwright test

**Files:**
- Create: `tests/admin-theme.spec.js`

- [ ] **Step 1:** Create `tests/admin-theme.spec.js`, mirroring `feature-toggles.spec.js` (skip on placeholder / missing creds; login on redirect):
```js
import { test, expect } from '@playwright/test';

const baseURL =
  process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || 'https://staging.placeholder.blueworx.io';
const isPlaceholder = /placeholder/i.test(baseURL);
const ADMIN_USER = process.env.WP_ADMIN_USER;
const ADMIN_PASS = process.env.WP_ADMIN_PASS;

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const DASH_PATH = '/wp-admin/index.php';

async function login(page) {
  await page.goto(DASH_PATH);
  if (await page.locator('#user_login').count()) {
    await page.fill('#user_login', ADMIN_USER);
    await page.fill('#user_pass', ADMIN_PASS);
    await page.click('#wp-submit');
  }
}

async function themeToggle(page) {
  await page.goto(SETTINGS_PATH);
  return page.locator('input.blueworx-feature-toggle[data-blueworx-feature="admin_theme"]');
}

test.describe('BlueWorx admin theme', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('Appearance section and admin_theme toggle render', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await expect(page.getByRole('heading', { name: 'Appearance' })).toBeVisible();
    await expect(
      page.locator('input.blueworx-feature-toggle[data-blueworx-feature="admin_theme"]')
    ).toBeVisible();
  });

  test('theme stylesheet loads when on and is absent when off', async ({ page }) => {
    await login(page);

    // Ensure ON.
    let toggle = await themeToggle(page);
    if (!(await toggle.isChecked())) {
      await toggle.setChecked(true);
      await page.getByRole('button', { name: 'Save Changes' }).click();
    }
    await page.goto(DASH_PATH);
    await expect(page.locator('link#blueworx-admin-theme-css')).toHaveCount(1);
    await expect(page.locator('.bw-stat-grid')).toBeVisible();

    // Turn OFF and confirm the stylesheet is gone.
    toggle = await themeToggle(page);
    await toggle.setChecked(false);
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await page.goto(DASH_PATH);
    await expect(page.locator('link#blueworx-admin-theme-css')).toHaveCount(0);

    // Restore ON (idempotent).
    toggle = await themeToggle(page);
    await toggle.setChecked(true);
    await page.getByRole('button', { name: 'Save Changes' }).click();
  });
});
```
(WordPress prints enqueued styles with an `id` of `<handle>-css`, so the link is `#blueworx-admin-theme-css`.)

- [ ] **Step 2:** Verify the harness recognises the spec (skips locally, no live WP):
```bash
npx playwright test tests/admin-theme.spec.js --list
```
Expected: the two tests listed (they self-skip at runtime on placeholder URL).

- [ ] **Step 3:** Commit:
```bash
git add tests/admin-theme.spec.js
git commit -m "test: admin theme enqueue + toggle behaviour"
```

---

### Task 8: Version bump, changelog, deploy artifact

**Files:**
- Modify: `blueworx-labs-wordpress.php`, `package.json`, `readme.txt`, `CHANGELOG.md`

- [ ] **Step 1:** Bump version to `1.11.0` in: plugin header `Version:` line, `BLUEWORX_LABS_VERSION` define, `package.json` `"version"`, `readme.txt` `Stable tag:`.

- [ ] **Step 2:** Add a `1.11.0` entry to `CHANGELOG.md` and the `readme.txt` changelog: "Added: BlueWorx admin & login re-skin (feature-flagged, default on); hybrid Dashboard with live stat tiles; self-hosted Sora + Inter."

- [ ] **Step 3:** Run the repo lint once (report, don't loop):
```bash
npm run lint
```

- [ ] **Step 4:** Commit:
```bash
git add -A
git commit -m "chore: release 1.11.0 — BlueWorx admin re-skin"
```

- [ ] **Step 5:** Push branch and open PR into `main`:
```bash
git push -u origin admin-reskin
gh pr create --base main --title "BlueWorx admin & login re-skin" --body "<summary + spec link>"
```

- [ ] **Step 6:** Build the WordPress deployment zip per global rules (bsdtar, forward slashes, single top-level `blueworx-labs-wordpress/` folder, placed one level up from the repo; remove any older zip first), then verify with `unzip -l`.

---

## Self-Review

**Spec coverage:** feature flag (T1) ✓, self-host fonts (T2) ✓, global admin + login enqueue (T3) ✓, native selector map (T4/T5) ✓, hybrid dashboard incl. widget removal + live counts (T6) ✓, accessibility focus rings/contrast (T4 steps 3,7,8) ✓, Playwright test (T7) ✓, versioning + deploy (T8) ✓. No spec section unmapped.

**Placeholder scan:** No TBD/TODO; every code step shows real code. Exhaustive CSS rule-by-rule is delegated to the spec's selector map by design (asset file), with concrete representative rules given per block.

**Type/name consistency:** `blueworx_admin_theme_enabled()`, handle `blueworx-admin-theme` (→ DOM id `blueworx-admin-theme-css`), widget id `blueworx_dashboard_stats`, feature key `admin_theme`, section `appearance`, stat class `.bw-stat-grid`/`.bw-stat-card` are used identically across T1/T3/T4/T6/T7.
