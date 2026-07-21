# Public Rendering Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the plugin the ability to render public pages at all — routing, a document shell, an asset pipeline, and the shared chrome (nav, footer, CTA band) — proven by one real page rendering end to end.

**Architecture:** The plugin owns its pages outright. Real WordPress Pages are created on activation so they exist for menus, SEO plugins and later editing, but `template_include` hands rendering to a plugin template that emits its **own complete HTML document** and never calls `get_header()`/`get_footer()`. That is what makes the site look identical regardless of which theme is active — which matters because hosting is undecided. `wp_head()`/`wp_footer()` are still called so other plugins and the admin bar work.

**Tech Stack:** Procedural PHP 8.0+ (no classes, matching the existing codebase), hand-written CSS and ES5-style vanilla JS shipped verbatim (no build step), Playwright for tests.

## Global Constraints

- **Prefix everything** `blueworx` / `BLUEWORX` — enforced by `WordPress.NamingConventions.PrefixAllGlobals` in `phpcs.xml.dist:32-39`.
- **Text domain** is `blueworx-labs-wordpress`, the only one PHPCS permits (`phpcs.xml.dist:24-30`).
- **PHPCS ruleset**: full `WordPress` + `PHPCompatibilityWP`, `testVersion 8.0-`. Tabs, Yoda conditions, `array()` long syntax, spaces inside parens. `assets/` is excluded from PHPCS and linted by ESLint only.
- **No build step exists.** CSS and JS are hand-written and shipped as-is. JS is ES5-flavoured IIFE (`( function () { 'use strict'; … }() );`), `var` not `let`, `sourceType: 'script'`, no jQuery.
- **Every enqueue** passes `blueworx_get_admin_asset_version( 'assets/…' )` as `$ver`. Reuse it; do not define a public twin.
- **Every PHP file** opens with a `@package BlueWorxLabs` docblock then `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- **Version bump + `CHANGELOG.md`** on the PR, or CI fails. Also bump `readme.txt`'s `Stable tag` — it is currently adrift at 1.16.0 and `scripts/version-check.mjs` does not check it.
- **New top-level directories must be added to `scripts/build-zip.mjs:12`** or they are silently missing from the deployment zip.
- **Do not touch** `includes/rest/`, the portal, or anything under CSS lines 373–526 / 947–962 in the source project. Out of scope.

---

## Context an implementer needs

**Source of truth for markup and styles** is `c:\Users\LukeMcfarland\Documents\GitHub\bluegroup_project_blueworx`, a Next.js app. You are porting its rendered output, not its React.

Three facts about the source stylesheet that drive design decisions:

1. `app/globals.css:1` is `* { box-sizing: border-box; margin: 0; padding: 0; }` — a nuclear reset that **will fight the WordPress admin bar and block styles** if shipped unscoped. Task 2 scopes it.
2. `--gut` (the page gutter) is redefined at five breakpoints and drives *all* horizontal rhythm. Never hard-code a gutter value.
3. `app/globals.css:325` is `main > div > .sec:last-child:not([style*="background"]) { padding-bottom: 0; }` — every page template must keep the `<main><div>…</div></main>` wrapper or CTA-band spacing breaks.

**A trap specific to this plugin:** `blueworx_intercept_requests()` runs on `init` priority 1 (`includes/login-security.php:44-113`) and can `wp_die()` front-end visitors outright when Site Protection's front-end area is enabled. Public pages are subject to it. Task 3 handles this explicitly — do not skip it and discover it on a client site.

---

## File Structure

| File | Responsibility |
|---|---|
| `includes/public/bootstrap.php` | Requires the public modules; single `require_once` from the main plugin file. Mirrors `includes/rest/bootstrap.php`. |
| `includes/public/pages.php` | The page registry (slug → title → template), page creation on activation, and the `template_include` routing. |
| `includes/public/assets.php` | Public CSS/JS registration and enqueueing, gated to plugin-owned pages only. |
| `includes/public/render.php` | Document shell (`blueworx_public_document_open/close`) and the partial loader. |
| `includes/public/helpers-public.php` | `blueworx_icon()`, `blueworx_blob()`, `blueworx_public_url()`, active-nav helper. |
| `templates/parts/nav.php` | Nav markup. |
| `templates/parts/footer.php` | CTA band + footer markup. |
| `templates/pages/home.php` | The one real page proving the stack. Remaining pages are Plan 2. |
| `assets/css/public.css` | Ported `globals.css`, scoped. |
| `assets/js/public-nav.js` | Nav behaviour: dropdown timers, scroll hide/reveal, mobile menu. |
| `tests/public-site.spec.js` | Public page coverage. |

---

## Task 1: Module skeleton, feature flag, and packaging

Nothing renders yet. This task makes the plugin *able* to have a public layer and ensures it would actually ship.

**Files:**
- Create: `includes/public/bootstrap.php`, `includes/public/pages.php` (stub), `includes/public/assets.php` (stub), `includes/public/render.php` (stub), `includes/public/helpers-public.php` (stub)
- Create: `templates/.gitkeep`
- Modify: `blueworx-labs-wordpress.php` (require the bootstrap), `includes/features.php` (register the flag), `scripts/build-zip.mjs:12` (allowlist `templates`), `readme.txt:7` (stable tag)

**Interfaces:**
- Consumes: `blueworx_feature_enabled( $key )` from `includes/features.php:125`.
- Produces: feature key `public_site`; constant-free module loading; all later tasks assume `includes/public/*.php` is loaded when the flag is on.

- [ ] **Step 1: Write the failing test**

Create `tests/public-site.spec.js`:

```js
// Public-site specs are logged out by definition, so every navigation goes
// through cacheBust() — Cloudways Varnish caches logged-out responses and a
// stale hit makes a passing build look broken (see tests/helpers.js:88-105).
import { test, expect } from '@playwright/test';
import { baseURL, isPlaceholder, cacheBust } from './helpers.js';

test.describe('Public site', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('the plugin declares a public_site feature', async ({ request }) => {
    // The flag must exist before anything can be gated on it. Asserted via the
    // home page rendering at all, which only happens when the flag is on.
    const res = await request.get(cacheBust('/'));
    expect(res.status()).toBeLessThan(500);
  });
});
```

- [ ] **Step 2: Run it to confirm the harness is wired**

```bash
node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up --plugin . --slug blueworx-labs-wordpress --dir .wp-test --port 8881
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8881 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw \
  npx playwright test tests/public-site.spec.js --workers=1
```

Expected: PASS (WordPress returns its default home page — a 200). This test is a tripwire for fatals, not a feature assertion.

- [ ] **Step 3: Register the feature flag**

In `includes/features.php`, inside the `appearance` section block of `blueworx_get_feature_definitions()`, add:

```php
'public_site' => array(
	'label'       => __( 'BlueWorx public site', 'blueworx-labs-wordpress' ),
	'description' => __( 'Renders the BlueWorx marketing site from this plugin, independently of the active theme. Turn off to hand the front end back to WordPress.', 'blueworx-labs-wordpress' ),
	'section'     => 'appearance',
	'detail'      => null,
),
```

No settings-page change is needed — `admin-settings.php:250-282` iterates the registry generically and `:181-183` saves it.

- [ ] **Step 4: Create the module stubs**

`includes/public/bootstrap.php`:

```php
<?php
/**
 * Public front-end layer — bootstrap.
 *
 * The plugin renders the marketing site itself rather than relying on a theme,
 * so the site is identical wherever it is hosted. Loaded only when the
 * public_site feature is on.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BLUEWORX_LABS_PATH . 'includes/public/helpers-public.php';
require_once BLUEWORX_LABS_PATH . 'includes/public/pages.php';
require_once BLUEWORX_LABS_PATH . 'includes/public/render.php';
require_once BLUEWORX_LABS_PATH . 'includes/public/assets.php';
```

Create the other four files with the same header docblock, the `ABSPATH` guard, and nothing else yet.

- [ ] **Step 5: Load it from the main plugin file**

In `blueworx-labs-wordpress.php`, after the existing `includes/rest/bootstrap.php` require, add:

```php
if ( blueworx_feature_enabled( 'public_site' ) ) {
	require_once BLUEWORX_LABS_PATH . 'includes/public/bootstrap.php';
}
```

This matches the file-scope gating idiom used at `includes/login-security.php:111-113`. It must come after `features.php` is loaded.

- [ ] **Step 6: Add `templates` to the zip allowlist**

`scripts/build-zip.mjs:12` — change:

```js
const REQUIRED = ['blueworx-labs-wordpress.php', 'uninstall.php', 'readme.txt', 'includes', 'assets'];
```

to:

```js
const REQUIRED = ['blueworx-labs-wordpress.php', 'uninstall.php', 'readme.txt', 'includes', 'assets', 'templates'];
```

`assets/` is already covered wholesale, so `assets/css/public.css` needs no change. `templates/` is **not** — without this the zip installs and then fatals on a missing template.

- [ ] **Step 7: Verify packaging actually includes it**

```bash
npm run build
unzip -l dist/blueworx-labs-wordpress.zip | grep templates
```

Expected: at least one `blueworx-labs-wordpress/templates/…` entry, with forward slashes.

- [ ] **Step 8: Fix the readme version drift and bump**

`readme.txt:7` → `Stable tag:        1.18.0`. Bump the plugin header, `BLUEWORX_LABS_VERSION` and `package.json` to `1.18.0`. Add a `## [1.18.0]` changelog entry.

```bash
node scripts/version-check.mjs
```

Expected: `version:check OK — plugin header and package.json agree (1.18.0).`

- [ ] **Step 9: Run the tripwire and commit**

```bash
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8881 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw \
  npx playwright test tests/public-site.spec.js --workers=1
php -l includes/public/bootstrap.php && npm run lint
git add -A && git commit -m "feat: public layer module skeleton and feature flag"
```

---

## Task 2: Public asset pipeline

**Files:**
- Create: `assets/css/public.css`
- Modify: `includes/public/assets.php`
- Test: `tests/public-site.spec.js`

**Interfaces:**
- Consumes: `blueworx_get_admin_asset_version()` (`includes/admin-assets.php:19`), `blueworx_public_is_owned_page()` from Task 3 — until Task 3 lands, gate on `is_front_page()`.
- Produces: `blueworx_enqueue_public_assets()` hooked to `wp_enqueue_scripts`; handles `blueworx-public` (CSS) and `blueworx-fonts` (dependency).

**Porting `globals.css`:** copy `app/globals.css` lines **1–372, 528–562, 576–697, 699–808, 809–945** into `assets/css/public.css`. **Omit** 373–526 (portal) and 947–962 (auth forms) — out of scope. Preserve the source order exactly; the AI-page block at 809–932 deliberately sits after the responsive block and carries its own media queries.

Then make two changes to the copied CSS:

1. **Scope the reset.** Replace line 1 (`* { box-sizing: border-box; margin: 0; padding: 0; }`) with:

```css
/* Scoped, not global. The source's `*` reset fights the WordPress admin bar
   and block styles when it escapes the page body. */
.bw-page,
.bw-page *,
.bw-page *::before,
.bw-page *::after {
	box-sizing: border-box;
	margin: 0;
	padding: 0;
}
```

2. **Namespace the body rule.** The source `body { … }` at line 16 becomes `.bw-page { … }`, keeping every declaration. Add the font stack fallback since `--font-sora` is no longer injected by Next:

```css
.bw-page {
	font-family: 'Sora', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
	/* …rest of the source body declarations verbatim… */
}
```

Every page template wraps its output in `<div class="bw-page">` (Task 4), so all existing selectors keep working.

- [ ] **Step 1: Write the failing test**

Add to `tests/public-site.spec.js`:

```js
test('the public stylesheet loads on the front page', async ({ page }) => {
  await page.goto(cacheBust('/'));
  const href = await page
    .locator('link[rel="stylesheet"][href*="public.css"]')
    .first()
    .getAttribute('href');

  expect(href, 'public.css must be enqueued').toBeTruthy();
  // Cache-busting is a house rule, not a nicety: without it a CSS change
  // silently does not reach anyone until their browser cache expires.
  expect(href, 'stylesheet must be versioned').toMatch(/[?&]ver=/);
});
```

- [ ] **Step 2: Run it to verify it fails**

```bash
npx playwright test tests/public-site.spec.js --workers=1 -g "stylesheet"
```

Expected: FAIL — `public.css must be enqueued` (received `null`).

- [ ] **Step 3: Create `assets/css/public.css`**

Copy the line ranges above from the source, apply the two scoping changes, and add a header comment matching house style (see `assets/css/admin-theme.css:1-10`):

```css
/*
 * BlueWorx public site styles.
 *
 * Ported from the headless front-end's globals.css. Scoped to .bw-page so the
 * reset cannot reach the admin bar or block styles.
 *
 * Portal (source 373-526) and auth (947-962) are deliberately excluded.
 */
```

- [ ] **Step 4: Write the enqueue**

`includes/public/assets.php`:

```php
/**
 * Enqueues public styles on plugin-owned pages only.
 *
 * Scoped deliberately: these styles carry a reset, so loading them on a page
 * the plugin does not own would restyle someone else's content.
 *
 * @return void
 */
function blueworx_enqueue_public_assets() {
	if ( ! blueworx_public_is_owned_page() ) {
		return;
	}

	wp_enqueue_style(
		'blueworx-fonts',
		BLUEWORX_LABS_URL . 'assets/css/blueworx-fonts.css',
		array(),
		blueworx_get_admin_asset_version( 'assets/css/blueworx-fonts.css' )
	);

	wp_enqueue_style(
		'blueworx-public',
		BLUEWORX_LABS_URL . 'assets/css/public.css',
		array( 'blueworx-fonts' ),
		blueworx_get_admin_asset_version( 'assets/css/public.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'blueworx_enqueue_public_assets' );
```

This is the plugin's **first ever** `wp_enqueue_scripts` hook. The `blueworx-fonts` dependency mirrors `includes/admin-theme.php:80-92`.

- [ ] **Step 5: Confirm Sora is available**

`assets/css/blueworx-fonts.css` already self-hosts Sora 400/600/700 (`assets/fonts/sora-*.woff2`). Confirm those three weights are declared:

```bash
grep -c "Sora" assets/css/blueworx-fonts.css
```

Expected: `3` or more. If Sora is absent, add `@font-face` blocks matching the existing Inter ones — do **not** add a Google Fonts request; fonts are self-hosted here deliberately.

- [ ] **Step 6: Run the test**

```bash
npx playwright test tests/public-site.spec.js --workers=1 -g "stylesheet"
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add assets/css/public.css includes/public/assets.php tests/public-site.spec.js
git commit -m "feat: public asset pipeline with scoped stylesheet"
```

---

## Task 3: Page registry, activation, and Site Protection cooperation

**Files:**
- Modify: `includes/public/pages.php`, `blueworx-labs-wordpress.php` (activation hook)
- Test: `tests/public-site.spec.js`

**Interfaces:**
- Produces:
  - `blueworx_public_pages(): array` — slug ⇒ `array( 'title' => string, 'template' => string )`
  - `blueworx_public_is_owned_page(): bool`
  - `blueworx_public_current_template(): string|null` — absolute path or null
  - `blueworx_public_install_pages(): void` — idempotent, safe to re-run

- [ ] **Step 1: Write the failing test**

```js
test('plugin-owned pages exist and are not 404s', async ({ page }) => {
  const res = await page.goto(cacheBust('/'));
  expect(res.status()).toBe(200);
  await expect(page.locator('body')).not.toHaveClass(/error404/);
});
```

- [ ] **Step 2: Run it to verify it fails**

Expected: FAIL — WordPress's default front page has no `.bw-page`, and once Task 4 lands the assertion is meaningful. At this step it may pass trivially; that is fine, it becomes load-bearing in Task 4.

- [ ] **Step 3: Write the registry**

In `includes/public/pages.php`:

```php
/**
 * The pages this plugin owns and renders.
 *
 * Real WordPress Pages are created for these so menus, SEO plugins and later
 * editing all work — but rendering is taken over in blueworx_public_template(),
 * so the active theme never gets a say in how they look.
 *
 * @return array Slug => array( title, template ).
 */
function blueworx_public_pages() {
	return (array) apply_filters(
		'blueworx_public_pages',
		array(
			'home' => array(
				'title'    => __( 'Home', 'blueworx-labs-wordpress' ),
				'template' => 'pages/home.php',
			),
		)
	);
}
```

Plan 2 adds the remaining eight entries. Keep this one-entry registry for now — YAGNI, and it keeps Task 7's assertions honest.

- [ ] **Step 4: Write page installation**

```php
/**
 * Creates the plugin's pages if they are absent. Idempotent.
 *
 * Pages are matched by the stored ID first and slug second, so a page the user
 * has renamed or moved is still recognised rather than silently duplicated on
 * every activation.
 *
 * @return void
 */
function blueworx_public_install_pages() {
	$map = (array) get_option( 'blueworx_public_page_ids', array() );

	foreach ( blueworx_public_pages() as $slug => $page ) {
		if ( isset( $map[ $slug ] ) && 'page' === get_post_type( $map[ $slug ] ) && 'trash' !== get_post_status( $map[ $slug ] ) ) {
			continue;
		}

		$existing = get_page_by_path( $slug );

		if ( $existing instanceof WP_Post ) {
			$map[ $slug ] = $existing->ID;
			continue;
		}

		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $page['title'],
				'post_name'    => $slug,
				'post_content' => '',
			)
		);

		if ( ! is_wp_error( $id ) ) {
			$map[ $slug ] = (int) $id;
		}
	}

	update_option( 'blueworx_public_page_ids', $map );
}
```

- [ ] **Step 5: Run it on activation**

In `blueworx-labs-wordpress.php`, extend the existing activation callback (`blueworx_headless_install`) — do not add a second `register_activation_hook`:

```php
if ( blueworx_feature_enabled( 'public_site' ) && function_exists( 'blueworx_public_install_pages' ) ) {
	blueworx_public_install_pages();
}
```

- [ ] **Step 6: Write the ownership check**

```php
/**
 * Whether the current request is a page this plugin renders.
 *
 * @return bool True when owned.
 */
function blueworx_public_is_owned_page() {
	return null !== blueworx_public_current_template();
}

/**
 * Absolute path to the template for the current request, or null.
 *
 * @return string|null Template path.
 */
function blueworx_public_current_template() {
	if ( is_admin() || ! is_page() ) {
		return null;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return null;
	}

	$pages = blueworx_public_pages();
	$map   = (array) get_option( 'blueworx_public_page_ids', array() );
	$slug  = array_search( $post->ID, $map, true );

	// Fall back to the slug so a fresh install works before the option is
	// written, and so a manually created page still resolves.
	if ( false === $slug ) {
		$slug = $post->post_name;
	}

	if ( ! isset( $pages[ $slug ] ) ) {
		return null;
	}

	$path = BLUEWORX_LABS_PATH . 'templates/' . $pages[ $slug ]['template'];

	return file_exists( $path ) ? $path : null;
}
```

- [ ] **Step 7: Exempt owned pages from the Site Protection front-end gate**

Read `includes/login-security.php:100-108`. Site Protection can `wp_die()` front-end visitors, which would take the public site down for logged-out users.

Add to `includes/public/pages.php`:

```php
/**
 * Keeps plugin-owned marketing pages public when Site Protection is on.
 *
 * Site Protection exists to hide a site in progress from the public. The
 * marketing site is the part that is deliberately public, so it is exempted —
 * otherwise turning that feature on takes the live site down.
 *
 * @param bool $protected Whether the request should be gated.
 * @return bool Filtered value.
 */
function blueworx_public_exempt_from_site_protection( $protected ) {
	return blueworx_public_is_owned_page() ? false : $protected;
}
```

`includes/login-security.php` has no filter at that point today. Add one there:

```php
$protected = apply_filters( 'blueworx_site_protection_applies', $protected );
```

then hook `blueworx_public_exempt_from_site_protection` to it at priority 10. **Read the surrounding function before editing** — the variable name and control flow must match what is actually there.

- [ ] **Step 8: Run the test and commit**

```bash
npx playwright test tests/public-site.spec.js --workers=1
git add -A && git commit -m "feat: plugin-owned page registry, activation and routing lookup"
```

---

## Task 4: Document shell and template routing

This is the task that makes a page actually render as the BlueWorx design.

**Files:**
- Modify: `includes/public/render.php`, `includes/public/pages.php`
- Create: `templates/pages/home.php` (placeholder body — real content is Plan 2)
- Test: `tests/public-site.spec.js`

**Interfaces:**
- Produces:
  - `blueworx_public_document_open( array $args = array() ): void`
  - `blueworx_public_document_close(): void`
  - `blueworx_public_part( string $relative, array $vars = array() ): void`

- [ ] **Step 1: Write the failing test**

```js
test('the front page renders from the plugin, not the theme', async ({ page }) => {
  await page.goto(cacheBust('/'));

  // .bw-page is the plugin's wrapper and the scope for every ported style.
  await expect(page.locator('.bw-page')).toHaveCount(1);
  // wp_head must still run or other plugins and the admin bar break.
  await expect(page.locator('head link[href*="public.css"]')).toHaveCount(1);
  await expect(page.locator('main > div')).toHaveCount(1);
});
```

The `main > div` assertion is not cosmetic — source `globals.css:325` targets `main > div > .sec:last-child`, so losing that wrapper breaks CTA-band spacing sitewide.

- [ ] **Step 2: Run it to verify it fails**

Expected: FAIL — `.bw-page` count 0.

- [ ] **Step 3: Write the document shell**

`includes/public/render.php`:

```php
/**
 * Opens a complete HTML document for a plugin-rendered page.
 *
 * Deliberately does NOT call get_header(): the plugin renders the whole
 * document so the site is identical regardless of the active theme, which
 * matters because hosting is not fixed. wp_head() is still called so other
 * plugins, the admin bar and SEO output all keep working.
 *
 * @param array $args Optional. 'body_class' => string.
 * @return void
 */
function blueworx_public_document_open( $args = array() ) {
	$body_class = isset( $args['body_class'] ) ? (string) $args['body_class'] : '';
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'bw-page ' . $body_class ); ?>>
	<?php
}

/**
 * Closes the document opened by blueworx_public_document_open().
 *
 * @return void
 */
function blueworx_public_document_close() {
	wp_footer();
	?>
</body>
</html>
	<?php
}

/**
 * Renders a template part with scoped variables.
 *
 * @param string $relative Path under templates/, e.g. 'parts/nav.php'.
 * @param array  $vars     Variables extracted into the part's scope.
 * @return void
 */
function blueworx_public_part( $relative, $vars = array() ) {
	$path = BLUEWORX_LABS_PATH . 'templates/' . ltrim( $relative, '/' );

	if ( ! file_exists( $path ) ) {
		return;
	}

	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- scoped to a template part with a caller-controlled array.
	extract( $vars, EXTR_SKIP );

	require $path;
}
```

- [ ] **Step 4: Hook `template_include`**

In `includes/public/pages.php`:

```php
/**
 * Hands rendering of owned pages to the plugin's own template.
 *
 * @param string $template Theme template WordPress resolved.
 * @return string Template path to load.
 */
function blueworx_public_template( $template ) {
	$own = blueworx_public_current_template();

	return null === $own ? $template : $own;
}
add_filter( 'template_include', 'blueworx_public_template' );
```

- [ ] **Step 5: Create the home template**

`templates/pages/home.php`:

```php
<?php
/**
 * Home page template.
 *
 * The <main><div> wrapper is required, not stylistic: globals.css targets
 * `main > div > .sec:last-child` to zero the final section's bottom padding.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

blueworx_public_document_open( array( 'body_class' => 'bw-home' ) );
blueworx_public_part( 'parts/nav.php' );
?>
<main>
	<div>
		<section class="sec">
			<div class="center-head">
				<h1 class="h1"><?php echo esc_html__( 'BlueWorx', 'blueworx-labs-wordpress' ); ?></h1>
			</div>
		</section>
	</div>
</main>
<?php
blueworx_public_part( 'parts/footer.php' );
blueworx_public_document_close();
```

- [ ] **Step 6: Point WordPress's front page at it**

The home page must be the site's front page. Extend `blueworx_public_install_pages()`:

```php
if ( isset( $map['home'] ) ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', (int) $map['home'] );
}
```

- [ ] **Step 7: Run the test**

Re-provision so activation runs:

```bash
node ../bluegroup_core_foundation/scripts/wp-test-env.mjs down --dir .wp-test
rm -rf .wp-test
node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up --plugin . --slug blueworx-labs-wordpress --dir .wp-test --port 8881
npx playwright test tests/public-site.spec.js --workers=1
```

Expected: PASS, all three assertions.

- [ ] **Step 8: Commit**

```bash
git add -A && git commit -m "feat: theme-independent document shell and template routing"
```

---

## Task 5: Shared helpers — icons, blobs, CTA band, footer

**Files:**
- Modify: `includes/public/helpers-public.php`
- Create: `templates/parts/footer.php`
- Test: `tests/public-site.spec.js`

**Interfaces:**
- Produces: `blueworx_icon( $name, $class = '', $style = '' )`, `blueworx_blob( $style = '' )`

**Critical detail:** the source `Icon.tsx` emits a wrapping `<span data-ic="{name}">` around an `<svg>` sized `100%/100%`. **The parent span controls the size**, and CSS targets `span[data-ic]` at ten places in the stylesheet. Emitting a bare `<svg>` collapses icon sizing sitewide.

- [ ] **Step 1: Write the failing test**

```js
test('icons render with the data-ic sizing hook', async ({ page }) => {
  await page.goto(cacheBust('/'));
  const icon = page.locator('[data-ic]').first();
  await expect(icon).toHaveCount(1);
  // The svg must fill its span — CSS sizes the span, not the svg.
  const fills = await icon.locator('svg').evaluate((s) => s.style.width === '100%');
  expect(fills, 'svg must be 100% of its span or icon sizing collapses').toBe(true);
});
```

- [ ] **Step 2: Run it to verify it fails**

Expected: FAIL — count 0.

- [ ] **Step 3: Port the icon set**

Copy the 21 path strings from `lib/icons.ts` into `includes/public/helpers-public.php` as:

```php
/**
 * Inline SVG path geometry, keyed by icon name.
 *
 * Ported verbatim from the front-end's lib/icons.ts. Values are the inner
 * markup of a 24x24 lucide-style icon, not complete <svg> elements.
 *
 * @return array Name => inner SVG markup.
 */
function blueworx_icon_paths() {
	return array(
		'chat'  => '<path d="…"/>',
		// …all 21: chat, mail, chart, clock, sms, doc, server, users, plug,
		// book, cart, calendar, phone, sparkles, code, zap, git, palette,
		// workflow, gauge, shield
	);
}
```

- [ ] **Step 4: Write the renderer**

```php
/**
 * Echoes an icon.
 *
 * The wrapping span carries data-ic and is what CSS sizes; the svg always
 * fills it. Emitting a bare svg breaks icon sizing across the site.
 *
 * @param string $name  Icon key.
 * @param string $class Optional CSS class for the span.
 * @param string $style Optional inline style for the span.
 * @return void
 */
function blueworx_icon( $name, $class = '', $style = '' ) {
	$paths = blueworx_icon_paths();

	if ( ! isset( $paths[ $name ] ) ) {
		return;
	}

	printf(
		'<span data-ic="%1$s"%2$s%3$s><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:100%%;height:100%%" aria-hidden="true" focusable="false">%4$s</svg></span>',
		esc_attr( $name ),
		'' === $class ? '' : ' class="' . esc_attr( $class ) . '"',
		'' === $style ? '' : ' style="' . esc_attr( $style ) . '"',
		wp_kses( $paths[ $name ], blueworx_icon_allowed_svg() )
	);
}
```

Add `blueworx_icon_allowed_svg()` returning a `wp_kses` allowlist permitting `path`, `circle`, `rect`, `line`, `polyline`, `polygon` and their geometry attributes. Do not use `wp_kses_post` — it strips SVG.

- [ ] **Step 5: Write the footer part**

`templates/parts/footer.php` — port `CtaBand.tsx` then `Footer.tsx` verbatim (structures are in the source files). Note: the CTA band renders on **every** page, outside `<main>`, before the footer. Three social links and Blog/Resources/Careers have no `href` in the source; keep them as non-links rather than inventing destinations. The newsletter form is non-functional in the source — render the markup but leave it inert; a real form plugin shortcode replaces it later.

- [ ] **Step 6: Run the test and commit**

```bash
npx playwright test tests/public-site.spec.js --workers=1
git add -A && git commit -m "feat: icon helper, blob helper and footer part"
```

---

## Task 6: Navigation

The single biggest port. Markup in PHP, behaviour in ~60 lines of vanilla JS.

**Files:**
- Create: `templates/parts/nav.php`, `assets/js/public-nav.js`
- Modify: `includes/public/assets.php`
- Test: `tests/public-site.spec.js`

**Interfaces:**
- Consumes: `blueworx_public_pages()` for active-state matching.
- Produces: nav markup with the class names the ported CSS already targets.

**Structure to reproduce** (from `components/Nav.tsx`, exact hierarchy in the source): `<nav>` containing `.nav-logo`, `.nav-links` (Home, Services, Toolbox+mega, Pricing, About+dropdown, AI Powered with `.nav-tag.tag-light`), `.nav-cta` (`.nav-sign-in`, `.nav-btn`), `.nav-sign-in-mobile`, and `.hamburger` with exactly **two** `<span>` bars. The mobile menu is a **sibling** of `<nav>`, not a child.

Active state: exact match for Home, prefix match for everything else.

- [ ] **Step 1: Write the failing test**

```js
test('navigation renders and marks the current page', async ({ page }) => {
  await page.goto(cacheBust('/'));
  await expect(page.locator('nav .nav-links a[href="/"]')).toHaveClass(/active/);
  await expect(page.locator('nav .hamburger span')).toHaveCount(2);
});

test('the mobile menu opens and closes', async ({ page }) => {
  await page.setViewportSize({ width: 900, height: 800 });
  await page.goto(cacheBust('/'));

  await expect(page.locator('.mobile-menu')).toBeHidden();
  await page.locator('.hamburger').click();
  await expect(page.locator('.mobile-menu')).toBeVisible();
  await page.locator('.hamburger').click();
  await expect(page.locator('.mobile-menu')).toBeHidden();
});
```

- [ ] **Step 2: Run to verify both fail**

Expected: FAIL — no `nav` element.

- [ ] **Step 3: Write the nav markup**

`templates/parts/nav.php`. The mega panel must be **present in the DOM** (hidden with a class) rather than conditionally absent, so CSS/JS can animate it — this differs from the React version, which unmounts it.

Keep `.nav-links a.mega-item { white-space: normal; }` working: the source notes at `globals.css:939` that this override is load-bearing, without it descriptions cannot wrap and the 760px panel overflows.

- [ ] **Step 4: Write the nav behaviour**

`assets/js/public-nav.js`, matching house ES5 IIFE style:

```js
/*
 * BlueWorx public navigation.
 *
 * Three behaviours ported from the front-end Nav component:
 *  - dropdown open/close with a 300ms close grace period, so moving the cursor
 *    from the trigger into the panel does not snap it shut
 *  - hide-on-scroll-down / reveal-on-scroll-up below 160px
 *  - mobile menu with a body scroll lock
 */
( function () {
	'use strict';

	var CLOSE_DELAY = 300;
	// …implementation
}() );
```

Scroll behaviour: rAF-throttled; add `nav-scrolled` past 8px; add `nav-hidden` when `y > 160` and moving down by more than 4px; remove on any upward movement or `y <= 160`; suppress entirely while the mobile menu is open.

- [ ] **Step 5: Enqueue it**

In `blueworx_enqueue_public_assets()`:

```php
wp_enqueue_script(
	'blueworx-public-nav',
	BLUEWORX_LABS_URL . 'assets/js/public-nav.js',
	array(),
	blueworx_get_admin_asset_version( 'assets/js/public-nav.js' ),
	true
);
```

- [ ] **Step 6: Run the tests**

```bash
npx playwright test tests/public-site.spec.js --workers=1
npm run lint
```

Expected: PASS, and ESLint clean (it will reject `let`/`const` — the config targets `sourceType: 'script'`).

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat: public navigation markup and behaviour"
```

---

## Task 7: Prove theme independence, then ship

The whole architecture rests on the claim that the site looks the same regardless of theme. Test it rather than assume it.

**Files:**
- Modify: `tests/public-site.spec.js`, `CHANGELOG.md`, `.github/workflows/ci.yml` (no change expected — confirm)

- [ ] **Step 1: Write the failing test**

```js
test('renders identically regardless of the active theme', async ({ page }) => {
  // The plugin emits its own document and never calls get_header(), so the
  // theme must not influence the output. If this fails, the claim that hosting
  // is interchangeable is false.
  await page.goto(cacheBust('/'));
  const before = await page.locator('.bw-page').innerHTML();

  await switchTheme(page, 'twentytwentyfour');
  await page.goto(cacheBust('/'));
  const after = await page.locator('.bw-page').innerHTML();

  expect(after).toBe(before);
});
```

Implement `switchTheme` as a helper that logs in and activates a theme via `/wp-admin/themes.php`. Restore the original theme in `afterAll` — this test mutates site state, so follow the idempotency convention at `tests/feature-toggles.spec.js:64-69`.

- [ ] **Step 2: Run it**

Expected: PASS if the architecture is right. **If it fails, stop and fix the architecture, not the test** — a theme leaking into the output means `get_header()` crept in or the theme is enqueueing over the plugin's styles.

- [ ] **Step 3: Full suite against the harness**

```bash
npx playwright test --workers=1
```

Expected: all pass, existing admin specs unaffected. The public specs must not disturb the admin ones — if the front-page change broke an admin test, that is a real regression.

- [ ] **Step 4: Verify the zip**

```bash
npm run build
unzip -l dist/blueworx-labs-wordpress.zip | grep -E "templates/|public.css|public-nav.js"
```

Expected: all three present, forward slashes, nested one level.

- [ ] **Step 5: Changelog, lint, commit, PR**

```bash
php -l includes/public/pages.php && npm run lint && node scripts/version-check.mjs
git add -A && git commit -m "test: prove public rendering is theme-independent"
git push -u origin public-rendering-foundation
gh pr create --base main --title "Public rendering foundation"
```

---

## Self-Review

**Spec coverage.** Routing ✓ (Task 3/4), document shell ✓ (4), asset pipeline ✓ (2), nav ✓ (6), footer + CTA band ✓ (5), portal-proofing ✓ (pages are real WP Pages behind native auth; no JWT dependency introduced), hosting-independence ✓ (7). Forms-as-shortcodes needs nothing here — `do_shortcode()` works natively inside a template, which is Plan 2's contact page.

**Placeholders.** Task 5 Step 3 and Task 6 Step 3 say "port from the source" rather than inlining ~200 lines of markup. That is deliberate: the source files are the specification and duplicating them here would create two things to keep in sync. Every *decision* about that markup is stated explicitly.

**Type consistency.** `blueworx_public_pages()`, `blueworx_public_current_template()`, `blueworx_public_is_owned_page()`, `blueworx_public_install_pages()`, `blueworx_public_document_open/close()`, `blueworx_public_part()`, `blueworx_icon()`, `blueworx_blob()` — names match across all tasks. Task 2 consumes `blueworx_public_is_owned_page()` before Task 3 defines it; Step 2 notes the interim `is_front_page()` gate.

**Known risk carried forward:** Task 3 Step 7 edits `includes/login-security.php`, which has no filter there today. That file is security-sensitive — read the whole function before changing it, and do not alter the gate's default behaviour, only add the filter.

---

## Plans 2 and 3 (not yet written)

- **Plan 2 — Marketing pages.** The eight remaining pages as templates, content hardcoded, contact form as a shortcode. Depends on Tasks 4–6 here.
- **Plan 3 — Interactive components.** Billing toggle, pricing calculator, savings calculator, FAQ accordion, feature tabs, AI demo. Independent of Plan 2 and can run in parallel once this lands.
