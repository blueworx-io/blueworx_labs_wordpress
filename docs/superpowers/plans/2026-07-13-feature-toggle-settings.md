# Feature Toggle Settings Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the static BlueWorx → Enhancements status table into a fully controlled settings page with a per-function on/off toggle, grouped into five sections, where every currently-always-on function defaults to on.

**Architecture:** A central feature registry (`includes/features.php`) is the single source of truth for feature keys, labels, descriptions, sections, and defaults. A getter `blueworx_feature_enabled($key)` reads option `blueworx_feature_{key}`, defaulting to on. Each feature file gates its hook registration on that getter. The Enhancements page is rebuilt into one settings form that renders the registry as sections of toggles with nested detail controls, saved through a single `admin-post` handler.

**Tech Stack:** PHP (WordPress plugin), vanilla admin JS (no new deps), Playwright for functional tests, `php -l` + `npm run version:check` for local verification.

## Global Constraints

- Slug/text domain: `blueworx-labs-wordpress`; constant prefix `BLUEWORX_LABS_`.
- Requires PHP 8.0+, WordPress 5.0+.
- No new runtime dependencies (not in `approved-deps.json`).
- Every toggle defaults **on** (absent option = on) — no DB migration.
- Scope: Enhancements bundle only. Do NOT touch `includes/rest/*` or the Headless tab.
- Version: minor bump with matching CHANGELOG entry (project guardrail).
- Feature keys (exact) and sections:
  - `login`, `site_protection`, `application_passwords` → section `security`
  - `comments`, `page_excerpts` → section `content`
  - `emails`, `profile_cleanup` → section `notifications`
  - `cache_auto`, `cache_manual` → section `performance`
  - `menu_editor` → section `admin_menu`
- Section order + labels: `security`→"Security & Access", `content`→"Content", `notifications`→"Notifications & Cleanup", `performance`→"Performance", `admin_menu`→"Admin Menu".
- **No PHPUnit harness exists.** Pure-logic functions are verified with `php -r` stub harnesses (shown per task); behavior is verified with Playwright; syntax with `php -l`.

---

### Task 1: Feature registry + getter

**Files:**
- Create: `includes/features.php`
- Modify: `blueworx-labs-wordpress.php:40` (add require after `helpers.php`)

**Interfaces:**
- Produces:
  - `blueworx_get_feature_sections(): array` — ordered `['security'=>'Security & Access', 'content'=>'Content', 'notifications'=>'Notifications & Cleanup', 'performance'=>'Performance', 'admin_menu'=>'Admin Menu']`.
  - `blueworx_get_feature_definitions(): array` — ordered, keyed by feature key → `['label'=>string, 'description'=>string, 'section'=>string, 'detail'=>string|null]`. `detail` is the detail-control identifier (`'login'`, `'site_protection'`, `'application_passwords'`, `'cache_manual'`, `'menu_editor'`) or `null`.
  - `blueworx_feature_enabled(string $key): bool` — `get_option('blueworx_feature_'.$key)` is on unless stored value is exactly `'0'`. Absent → `true`.

- [ ] **Step 1: Write the failing test (getter defaults on; off only when '0')**

Create a temporary harness and run it:

```bash
cat > /tmp/bw-feat-test.php <<'PHP'
<?php
define( 'ABSPATH', '/tmp/' );
$GLOBALS['__opts'] = array();
function get_option( $name, $default = false ) {
    return array_key_exists( $name, $GLOBALS['__opts'] ) ? $GLOBALS['__opts'][ $name ] : $default;
}
function __( $t, $d = null ) { return $t; }
require '/c/Users/LukeMcfarland/Documents/GitHub/blueworx_labs_wordpress/includes/features.php';

assert( blueworx_feature_enabled( 'login' ) === true );        // absent -> on
$GLOBALS['__opts']['blueworx_feature_login'] = '0';
assert( blueworx_feature_enabled( 'login' ) === false );       // '0' -> off
$GLOBALS['__opts']['blueworx_feature_login'] = '1';
assert( blueworx_feature_enabled( 'login' ) === true );        // '1' -> on

$defs = blueworx_get_feature_definitions();
assert( count( $defs ) === 10 );
assert( $defs['login']['section'] === 'security' );
assert( array_keys( blueworx_get_feature_sections() ) === array( 'security','content','notifications','performance','admin_menu' ) );
echo "OK\n";
PHP
php /tmp/bw-feat-test.php
```

Expected: FAIL — `require ... features.php` fails (file does not exist yet).

- [ ] **Step 2: Create `includes/features.php`**

```php
<?php
/**
 * Feature registry and per-feature on/off gate.
 *
 * Single source of truth for which enhancement functions exist, how they are
 * grouped on the settings page, and whether each is enabled. Every feature
 * defaults to on: an absent option is treated as enabled, so a fresh install
 * behaves exactly as before this settings page existed.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the ordered settings sections.
 *
 * @return array Section labels keyed by section id, in display order.
 */
function blueworx_get_feature_sections() {
	return array(
		'security'      => __( 'Security & Access', 'blueworx-labs-wordpress' ),
		'content'       => __( 'Content', 'blueworx-labs-wordpress' ),
		'notifications' => __( 'Notifications & Cleanup', 'blueworx-labs-wordpress' ),
		'performance'   => __( 'Performance', 'blueworx-labs-wordpress' ),
		'admin_menu'    => __( 'Admin Menu', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Gets the feature registry.
 *
 * @return array Feature definitions keyed by feature key, in display order.
 */
function blueworx_get_feature_definitions() {
	return array(
		'login'                 => array(
			'label'       => __( 'Custom login & protection', 'blueworx-labs-wordpress' ),
			'description' => __( 'Moves login to a custom URL and blocks the default WordPress login and admin paths.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => 'login',
		),
		'site_protection'       => array(
			'label'       => __( 'Site Protection', 'blueworx-labs-wordpress' ),
			'description' => __( 'Only lets logged-in users with selected roles view the frontend or backend.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => 'site_protection',
		),
		'application_passwords' => array(
			'label'       => __( 'Application Passwords', 'blueworx-labs-wordpress' ),
			'description' => __( 'Hidden by default. When enabled, only admins can see Application Passwords on admin user profiles.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => 'application_passwords',
		),
		'comments'              => array(
			'label'       => __( 'Comments disabled', 'blueworx-labs-wordpress' ),
			'description' => __( 'Turns comments off and removes comment areas from the admin screens.', 'blueworx-labs-wordpress' ),
			'section'     => 'content',
			'detail'      => null,
		),
		'page_excerpts'         => array(
			'label'       => __( 'Page excerpts', 'blueworx-labs-wordpress' ),
			'description' => __( 'Adds excerpt support to Pages, the same way Posts already have it.', 'blueworx-labs-wordpress' ),
			'section'     => 'content',
			'detail'      => null,
		),
		'emails'                => array(
			'label'       => __( 'Email notifications reduced', 'blueworx-labs-wordpress' ),
			'description' => __( 'Stops extra admin emails for user, password, plugin, and theme changes.', 'blueworx-labs-wordpress' ),
			'section'     => 'notifications',
			'detail'      => null,
		),
		'profile_cleanup'       => array(
			'label'       => __( 'Profile cleanup', 'blueworx-labs-wordpress' ),
			'description' => __( 'Hides unused profile options, Elementor AI, and Elementor Notes.', 'blueworx-labs-wordpress' ),
			'section'     => 'notifications',
			'detail'      => null,
		),
		'cache_auto'            => array(
			'label'       => __( 'Automatic cache refresh', 'blueworx-labs-wordpress' ),
			'description' => __( 'Refreshes cache when pages or posts are changed.', 'blueworx-labs-wordpress' ),
			'section'     => 'performance',
			'detail'      => null,
		),
		'cache_manual'          => array(
			'label'       => __( 'Manual cache refresh', 'blueworx-labs-wordpress' ),
			'description' => __( 'Adds a Cache page where cache can be refreshed manually.', 'blueworx-labs-wordpress' ),
			'section'     => 'performance',
			'detail'      => 'cache_manual',
		),
		'menu_editor'           => array(
			'label'       => __( 'Menu editor', 'blueworx-labs-wordpress' ),
			'description' => __( 'Lets you reorder menu items, hide them, or move them into More.', 'blueworx-labs-wordpress' ),
			'section'     => 'admin_menu',
			'detail'      => 'menu_editor',
		),
	);
}

/**
 * Checks whether a feature is enabled.
 *
 * Absent option means enabled: features default on so existing installs keep
 * their current behavior without a migration.
 *
 * @param string $key Feature key from blueworx_get_feature_definitions().
 * @return bool True when the feature is enabled.
 */
function blueworx_feature_enabled( $key ) {
	return '0' !== get_option( 'blueworx_feature_' . $key, '1' );
}
```

- [ ] **Step 3: Add the require in the main plugin file**

Modify `blueworx-labs-wordpress.php` — insert the features require immediately after the `helpers.php` line (line 40):

```php
require_once BLUEWORX_LABS_PATH . 'includes/helpers.php';
require_once BLUEWORX_LABS_PATH . 'includes/features.php';
require_once BLUEWORX_LABS_PATH . 'includes/upgrade.php';
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php /tmp/bw-feat-test.php`
Expected: `OK`

- [ ] **Step 5: Syntax check**

Run: `php -l includes/features.php && php -l blueworx-labs-wordpress.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 6: Commit**

```bash
git add includes/features.php blueworx-labs-wordpress.php
git commit -m "Add feature registry and per-feature enabled gate"
```

---

### Task 2: Editable login slug + gate login-security.php

`login-security.php`'s `blueworx_intercept_requests()` (hooked on `init`) serves BOTH the `login` feature (custom-login serving, wp-login/​wp-admin blocking) and `site_protection` (frontend/backend role gating). It cannot be split by unregistration alone, so it gets internal flag checks; the init hook is registered only when at least one of the two features is on. The login-only filters register only when `login` is on.

**Files:**
- Modify: `includes/login-security.php`

**Interfaces:**
- Consumes: `blueworx_feature_enabled()` (Task 1).
- Produces:
  - `blueworx_sanitize_login_slug(string $raw): string` — `sanitize_title` result; falls back to `admin_login` when empty or a reserved path (`wp-admin`, `wp-login`, `admin`, `wp-content`, `wp-includes`).
  - `blueworx_login_slug(): string` — sanitized value of option `blueworx_login_slug`, default `admin_login`.

- [ ] **Step 1: Write the failing test (slug sanitizer)**

```bash
cat > /tmp/bw-slug-test.php <<'PHP'
<?php
define( 'ABSPATH', '/tmp/' );
// Minimal WP stubs used by login-security.php at include time and by the sanitizer.
function get_option( $n, $d = false ) { return $d; }
function add_action() {}
function add_filter() {}
function __( $t, $d = null ) { return $t; }
function sanitize_title( $s ) {
    $s = strtolower( trim( (string) $s ) );
    $s = preg_replace( '/[^a-z0-9_\-]+/', '-', $s );
    return trim( $s, '-' );
}
if ( ! defined( 'BLUEWORX_CUSTOM_LOGIN_SLUG' ) ) { define( 'BLUEWORX_CUSTOM_LOGIN_SLUG', 'admin_login' ); }
require '/c/Users/LukeMcfarland/Documents/GitHub/blueworx_labs_wordpress/includes/login-security.php';

assert( blueworx_sanitize_login_slug( 'My Secret Door' ) === 'my-secret-door' );
assert( blueworx_sanitize_login_slug( '' ) === 'admin_login' );          // empty -> default
assert( blueworx_sanitize_login_slug( 'wp-admin' ) === 'admin_login' );  // reserved -> default
assert( blueworx_sanitize_login_slug( 'wp-login' ) === 'admin_login' );  // reserved -> default
echo "OK\n";
PHP
php /tmp/bw-slug-test.php
```

Expected: FAIL — `blueworx_sanitize_login_slug` not defined.

- [ ] **Step 2: Add the slug functions to `includes/login-security.php`**

Insert after the direct-access guard (after line 11), before `blueworx_intercept_requests`:

```php
/**
 * Sanitizes a custom login slug, falling back to the default when unusable.
 *
 * @param string $raw Raw slug input.
 * @return string A safe, non-reserved slug.
 */
function blueworx_sanitize_login_slug( $raw ) {
	$slug     = sanitize_title( (string) $raw );
	$reserved = array( 'wp-admin', 'wp-login', 'admin', 'wp-content', 'wp-includes' );

	if ( '' === $slug || in_array( $slug, $reserved, true ) ) {
		return 'admin_login';
	}

	return $slug;
}

/**
 * Gets the active custom login slug.
 *
 * @return string The configured slug, or the default when unset.
 */
function blueworx_login_slug() {
	return blueworx_sanitize_login_slug( get_option( 'blueworx_login_slug', BLUEWORX_CUSTOM_LOGIN_SLUG ) );
}
```

- [ ] **Step 3: Replace every `BLUEWORX_CUSTOM_LOGIN_SLUG` read with `blueworx_login_slug()`**

In `includes/login-security.php`, replace the 5 usages (lines 140, 143, 144, 167, 196) so each reads the function. Exact replacements:

Line 140: `$bases     = array( '/' . blueworx_login_slug() );`
Lines 143-144:
```php
		$bases[] = $home_path . '/' . blueworx_login_slug();
		$bases[] = $home_path . '/index.php/' . blueworx_login_slug();
```
Line 167: `$custom = home_url( '/' . blueworx_login_slug() . '/' );`
Line 196: `$custom = home_url( '/' . blueworx_login_slug() . '/' );`

(Leave the `BLUEWORX_CUSTOM_LOGIN_SLUG` constant defined in the main file as the ultimate default.)

- [ ] **Step 4: Add internal feature flags to `blueworx_intercept_requests()`**

Replace the body of `blueworx_intercept_requests()` (lines 18-75) with the flag-gated version. Insert the flag reads right after the `$path` normalization (after line 30) and guard each branch:

```php
	$login_on    = blueworx_feature_enabled( 'login' );
	$sp_backend  = blueworx_feature_enabled( 'site_protection' ) && blueworx_site_protection_is_enabled( 'backend' );
	$sp_frontend = blueworx_feature_enabled( 'site_protection' ) && blueworx_site_protection_is_enabled( 'frontend' );

	if ( $login_on && blueworx_is_custom_login_request_path( $path ) ) {
		$_SERVER['SCRIPT_FILENAME'] = ABSPATH . 'wp-login.php';
		$_SERVER['PHP_SELF']        = '/wp-login.php';
		$_SERVER['SCRIPT_NAME']     = '/wp-login.php';
		require_once ABSPATH . 'wp-login.php'; // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude.FileIncludeFound
		exit;
	}

	$script_name  = isset( $_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : '';
	$is_login_php = (
		false !== strpos( $path, '/wp-login.php' ) ||
		false !== strpos( $script_name, 'wp-login.php' )
	);

	if ( $login_on && $is_login_php ) {
		blueworx_redirect_home();
	}

	if ( 0 === strpos( $path, '/wp-admin' ) ) {
		if ( ! is_user_logged_in() ) {
			if ( $sp_backend ) {
				blueworx_site_protection_die( __( 'Please log in to view this site.', 'blueworx-labs-wordpress' ) );
			}

			if ( $login_on ) {
				blueworx_redirect_home();
			}

			return;
		}

		if ( $sp_backend && ! blueworx_current_user_has_site_protection_role( 'backend' ) ) {
			blueworx_site_protection_die( __( 'You do not have access to view this area.', 'blueworx-labs-wordpress' ) );
		}

		return;
	}

	if ( $sp_frontend ) {
		if ( ! is_user_logged_in() ) {
			blueworx_site_protection_die( __( 'Please log in to view this site.', 'blueworx-labs-wordpress' ) );
		}

		if ( ! blueworx_current_user_has_site_protection_role( 'frontend' ) ) {
			blueworx_site_protection_die( __( 'You do not have access to view this area.', 'blueworx-labs-wordpress' ) );
		}
	}
```

- [ ] **Step 5: Gate the hook registrations**

- Replace `add_action( 'init', 'blueworx_intercept_requests', 1 );` (line 76) with:

```php
if ( blueworx_feature_enabled( 'login' ) || blueworx_feature_enabled( 'site_protection' ) ) {
	add_action( 'init', 'blueworx_intercept_requests', 1 );
}
```

- Wrap the three login-only filters (lines 178, 204-205) and the template guard (line 221). Replace those `add_filter`/`add_action` lines with a single guarded block placed where line 221 currently sits (keep the function definitions above unchanged):

```php
if ( blueworx_feature_enabled( 'login' ) ) {
	add_filter( 'login_url', 'blueworx_custom_login_url', 10, 3 );
	add_filter( 'site_url', 'blueworx_replace_generated_login_url', 10, 2 );
	add_filter( 'network_site_url', 'blueworx_replace_generated_login_url', 10, 2 );
	add_action( 'template_redirect', 'blueworx_template_redirect_guard' );
}
```

(Remove the original standalone `add_filter( 'login_url' ... )`, `add_filter( 'site_url' ... )`, `add_filter( 'network_site_url' ... )`, and `add_action( 'template_redirect' ... )` lines so they are not double-registered.)

- [ ] **Step 6: Run the slug test to verify it passes**

Run: `php /tmp/bw-slug-test.php`
Expected: `OK`

- [ ] **Step 7: Syntax check**

Run: `php -l includes/login-security.php`
Expected: `No syntax errors detected`.

- [ ] **Step 8: Commit**

```bash
git add includes/login-security.php
git commit -m "Gate login + site protection on feature flags; make login slug editable"
```

---

### Task 3: Gate the remaining feature files

Wrap each file's top-level hook registration in its feature flag. `cache-refresh.php` and `profile-cleanup.php` each host two features — gate them independently.

**Files:**
- Modify: `includes/disable-comments.php`, `includes/email-notifications.php`, `includes/page-excerpts.php`, `includes/profile-cleanup.php`, `includes/cache-refresh.php`

**Interfaces:**
- Consumes: `blueworx_feature_enabled()` (Task 1).

- [ ] **Step 1: Gate `disable-comments.php`**

All ten `add_filter`/`add_action` calls (lines 22, 23, 35, 45, 59, 69, 80, 92, 93, 109) belong to `comments`. Wrap them in one guard. At the point of the first registration, open:

```php
if ( blueworx_feature_enabled( 'comments' ) ) {
```

and after the last registration (line 109) close with `}`. Indent the enclosed `add_*` lines one level.

- [ ] **Step 2: Gate `email-notifications.php`**

All six registrations (lines 22, 33, 37, 38, 62, 75) belong to `emails`. Wrap them:

```php
if ( blueworx_feature_enabled( 'emails' ) ) {
	// ... the six add_filter/add_action lines ...
}
```

- [ ] **Step 3: Gate `page-excerpts.php`**

Wrap the single registration (line 21):

```php
if ( blueworx_feature_enabled( 'page_excerpts' ) ) {
	add_action( 'init', 'blueworx_enable_page_excerpts' );
}
```

- [ ] **Step 4: Gate `profile-cleanup.php` (two features)**

The application-passwords filter (line 40) belongs to `application_passwords`; any other profile-screen registrations belong to `profile_cleanup`. Wrap the application-passwords registration:

```php
if ( blueworx_feature_enabled( 'application_passwords' ) ) {
	add_filter( 'wp_is_application_passwords_available_for_user', 'blueworx_filter_application_passwords_available', 10, 2 );
}
```

Wrap every other top-level `add_action`/`add_filter` in this file (the profile-cleanup registrations) in:

```php
if ( blueworx_feature_enabled( 'profile_cleanup' ) ) {
	// ... profile-cleanup registrations ...
}
```

(Read the file first; group by which callback implements profile cleanup vs application passwords. If a registration is shared, keep it under `profile_cleanup`.)

- [ ] **Step 5: Gate `cache-refresh.php` (two features)**

- `cache_auto`: wrap `save_post` (68), `trashed_post` (85), `untrashed_post` (86), `before_delete_post` (87):

```php
if ( blueworx_feature_enabled( 'cache_auto' ) ) {
	add_action( 'save_post', 'blueworx_refresh_cache_on_save', 20, 3 );
	add_action( 'trashed_post', 'blueworx_refresh_cache_on_trash_change' );
	add_action( 'untrashed_post', 'blueworx_refresh_cache_on_trash_change' );
	add_action( 'before_delete_post', 'blueworx_refresh_cache_on_trash_change' );
}
```

- `cache_manual`: wrap the manual handler (line 51):

```php
if ( blueworx_feature_enabled( 'cache_manual' ) ) {
	add_action( 'admin_post_blueworx_clear_cache_now', 'blueworx_handle_manual_cache_refresh' );
}
```

- [ ] **Step 6: Syntax check all five files**

Run:
```bash
for f in disable-comments email-notifications page-excerpts profile-cleanup cache-refresh; do php -l "includes/$f.php"; done
```
Expected: `No syntax errors detected` for each.

- [ ] **Step 7: Commit**

```bash
git add includes/disable-comments.php includes/email-notifications.php includes/page-excerpts.php includes/profile-cleanup.php includes/cache-refresh.php
git commit -m "Gate comments, emails, excerpts, profile cleanup, app passwords, and cache on feature flags"
```

---

### Task 4: Gate admin submenu pages + menu editor hooks

**Files:**
- Modify: `includes/admin-settings.php` (submenu registration in `blueworx_register_settings_page`, lines 29-63)
- Modify: `includes/admin-menu-order.php` (menu editor hooks: 141, 168, 244, 277, 309)

**Interfaces:**
- Consumes: `blueworx_feature_enabled()` (Task 1).

- [ ] **Step 1: Gate the Cache and Edit Menu submenu pages**

In `blueworx_register_settings_page()`, wrap the **Edit Menu** `add_submenu_page(...)` (currently lines 38-45) in `if ( blueworx_feature_enabled( 'menu_editor' ) ) { ... }`, and the **Cache** `add_submenu_page(...)` (currently lines 47-63 region — the Cache one) in `if ( blueworx_feature_enabled( 'cache_manual' ) ) { ... }`. Leave the top-level menu and the main Enhancements submenu unconditional.

- [ ] **Step 2: Gate the menu-editor hooks in `admin-menu-order.php`**

Wrap each top-level registration (lines 141, 168, 244, 277, 309) in `blueworx_feature_enabled( 'menu_editor' )`. Because these are spread through the file, guard each individually, e.g.:

```php
if ( blueworx_feature_enabled( 'menu_editor' ) ) {
	add_filter( 'custom_menu_order', 'blueworx_enable_admin_menu_order' );
}
```

Repeat the wrapping pattern for the `menu_order` filter, the `admin_menu` visibility action, the `admin_head` hide action, and the `admin_footer` inline action.

- [ ] **Step 3: Syntax check**

Run: `php -l includes/admin-settings.php && php -l includes/admin-menu-order.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add includes/admin-settings.php includes/admin-menu-order.php
git commit -m "Hide Cache and Edit Menu pages and menu-editor hooks when disabled"
```

---

### Task 5: Rebuild the Enhancements page as a grouped settings form

Replace `blueworx_render_enhancements_page()` (currently lines 255-360 region) and delete the now-unused `blueworx_get_active_features()` registry (replaced by `blueworx_get_feature_definitions()`). Detail controls are rendered by a dispatcher that reuses the existing site-protection and application-passwords helpers.

**Files:**
- Modify: `includes/admin-settings.php`

**Interfaces:**
- Consumes: `blueworx_get_feature_sections()`, `blueworx_get_feature_definitions()`, `blueworx_feature_enabled()` (Task 1); `blueworx_login_slug()` (Task 2); existing `blueworx_get_site_protection_role_choices()`, `blueworx_get_site_protection_roles()`, `blueworx_site_protection_enabled()`, `blueworx_show_application_passwords_for_admins()`.
- Produces: `blueworx_render_feature_detail(string $key): void` (echoes nested detail markup for a feature, or nothing).

- [ ] **Step 1: Replace `blueworx_render_enhancements_page()`**

```php
/**
 * Renders the BlueWorx feature settings page.
 *
 * @return void
 */
function blueworx_render_enhancements_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-labs-wordpress' ) );
	}

	$sections    = blueworx_get_feature_sections();
	$features    = blueworx_get_feature_definitions();
	$notice      = get_transient( 'blueworx_labs_notice' );
	$login_url   = home_url( '/' . blueworx_login_slug() . '/' );

	if ( $notice ) {
		delete_transient( 'blueworx_labs_notice' );
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Turn each function on or off. Functions left on behave exactly as before.', 'blueworx-labs-wordpress' ); ?></p>
		<?php if ( blueworx_feature_enabled( 'login' ) ) : ?>
			<p><strong><?php esc_html_e( 'Active login URL:', 'blueworx-labs-wordpress' ); ?></strong>
				<a href="<?php echo esc_url( $login_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $login_url ); ?></a></p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="blueworx_save_feature_settings" />
			<?php wp_nonce_field( 'blueworx_save_feature_settings' ); ?>

			<?php foreach ( $sections as $section_id => $section_label ) : ?>
				<div class="postbox blueworx-feature-section">
					<div class="postbox-header"><h2 class="hndle"><?php echo esc_html( $section_label ); ?></h2></div>
					<div class="inside">
						<table class="form-table" role="presentation"><tbody>
						<?php
						foreach ( $features as $key => $feature ) :
							if ( $feature['section'] !== $section_id ) {
								continue;
							}
							$enabled = blueworx_feature_enabled( $key );
							?>
							<tr>
								<th scope="row">
									<label>
										<input type="checkbox" name="<?php echo esc_attr( 'blueworx_feature[' . $key . ']' ); ?>" value="1" <?php checked( $enabled ); ?> class="blueworx-feature-toggle" data-blueworx-feature="<?php echo esc_attr( $key ); ?>" />
										<?php echo esc_html( $feature['label'] ); ?>
									</label>
								</th>
								<td>
									<p class="description"><?php echo esc_html( $feature['description'] ); ?></p>
									<?php if ( ! empty( $feature['detail'] ) ) : ?>
										<div class="blueworx-feature-detail" data-blueworx-detail="<?php echo esc_attr( $key ); ?>" <?php echo $enabled ? '' : 'hidden'; ?>>
											<?php blueworx_render_feature_detail( $key ); ?>
										</div>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody></table>
					</div>
				</div>
			<?php endforeach; ?>

			<?php submit_button( esc_html__( 'Save Changes', 'blueworx-labs-wordpress' ) ); ?>
		</form>
	</div>
	<?php
}
```

- [ ] **Step 2: Add the detail dispatcher**

Add after `blueworx_render_enhancements_page()`:

```php
/**
 * Renders the nested detail controls for a feature.
 *
 * @param string $key Feature key.
 * @return void
 */
function blueworx_render_feature_detail( $key ) {
	if ( 'login' === $key ) {
		?>
		<p>
			<label for="blueworx_login_slug"><?php esc_html_e( 'Login slug', 'blueworx-labs-wordpress' ); ?></label><br />
			<input type="text" id="blueworx_login_slug" name="blueworx_login_slug" class="regular-text" value="<?php echo esc_attr( blueworx_login_slug() ); ?>" />
			<span class="description"><?php echo esc_html( home_url( '/' ) ); ?>&hellip;</span>
		</p>
		<?php
		return;
	}

	if ( 'site_protection' === $key ) {
		$role_choices = blueworx_get_site_protection_role_choices();
		foreach ( array(
			'frontend' => __( 'Frontend protection', 'blueworx-labs-wordpress' ),
			'backend'  => __( 'Backend protection', 'blueworx-labs-wordpress' ),
		) as $area => $label ) :
			$selected_roles = blueworx_get_site_protection_roles( $area );
			?>
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( 'blueworx_' . $area . '_protection_enabled' ); ?>" value="1" <?php checked( blueworx_site_protection_enabled( $area ) ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			</p>
			<p>
				<select name="<?php echo esc_attr( 'blueworx_' . $area . '_protection_roles[]' ); ?>" multiple size="4" aria-label="<?php echo esc_attr( $label ); ?>">
					<?php foreach ( $role_choices as $role_slug => $role_label ) : ?>
						<option value="<?php echo esc_attr( $role_slug ); ?>" <?php selected( in_array( $role_slug, $selected_roles, true ) ); ?>><?php echo esc_html( $role_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php
		endforeach;
		return;
	}

	if ( 'application_passwords' === $key ) {
		?>
		<p>
			<label>
				<input type="checkbox" name="blueworx_show_application_passwords" value="1" <?php checked( blueworx_show_application_passwords_for_admins() ); ?> />
				<?php esc_html_e( 'Show Application Passwords for admins', 'blueworx-labs-wordpress' ); ?>
			</label>
		</p>
		<?php
		return;
	}

	if ( 'cache_manual' === $key ) {
		?>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=blueworx-cache' ) ); ?>"><?php esc_html_e( 'Open Cache page', 'blueworx-labs-wordpress' ); ?></a></p>
		<?php
		return;
	}

	if ( 'menu_editor' === $key ) {
		?>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=blueworx-edit-menu' ) ); ?>"><?php esc_html_e( 'Open Edit Menu page', 'blueworx-labs-wordpress' ); ?></a></p>
		<?php
	}
}
```

- [ ] **Step 3: Delete `blueworx_get_active_features()`**

Remove the entire `blueworx_get_active_features()` function (and its docblock) — it is superseded by the registry. Verify nothing else calls it:

Run: `grep -rn "blueworx_get_active_features" includes`
Expected: no results.

- [ ] **Step 4: Syntax check**

Run: `php -l includes/admin-settings.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add includes/admin-settings.php
git commit -m "Rebuild Enhancements page as grouped feature-toggle settings form"
```

---

### Task 6: Unified save handler

Replace the two old inline handlers (`blueworx_save_site_protection_settings`, `blueworx_save_application_passwords_setting`) with a single `blueworx_save_feature_settings()` that writes every toggle plus the nested detail options in one submit.

**Files:**
- Modify: `includes/admin-settings.php`

**Interfaces:**
- Consumes: `blueworx_get_feature_definitions()`, `blueworx_sanitize_login_slug()`, `blueworx_get_site_protection_role_choices()`.
- Produces: `blueworx_save_feature_settings(): void` on `admin_post_blueworx_save_feature_settings`.

- [ ] **Step 1: Add the unified handler**

```php
/**
 * Saves all feature toggles and their detail settings from the settings page.
 *
 * @return void
 */
function blueworx_save_feature_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_save_feature_settings' );

	$posted = isset( $_POST['blueworx_feature'] ) ? (array) wp_unslash( $_POST['blueworx_feature'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	foreach ( array_keys( blueworx_get_feature_definitions() ) as $key ) {
		update_option( 'blueworx_feature_' . $key, isset( $posted[ $key ] ) ? '1' : '0' );
	}

	// Login detail: editable slug.
	$raw_slug = isset( $_POST['blueworx_login_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['blueworx_login_slug'] ) ) : '';
	update_option( 'blueworx_login_slug', blueworx_sanitize_login_slug( $raw_slug ) );

	// Site Protection detail: per-area enable + roles.
	$choices = blueworx_get_site_protection_role_choices();
	foreach ( array( 'frontend', 'backend' ) as $area ) {
		$enabled = isset( $_POST[ 'blueworx_' . $area . '_protection_enabled' ] ) ? '1' : '0';
		$roles   = isset( $_POST[ 'blueworx_' . $area . '_protection_roles' ] ) ? (array) wp_unslash( $_POST[ 'blueworx_' . $area . '_protection_roles' ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$roles   = array_values( array_intersect( array_unique( array_map( 'sanitize_key', $roles ) ), array_keys( $choices ) ) );

		update_option( 'blueworx_' . $area . '_protection_enabled', $enabled );
		update_option( 'blueworx_' . $area . '_protection_roles', $roles, false );
	}

	// Application Passwords detail.
	update_option( 'blueworx_show_application_passwords', isset( $_POST['blueworx_show_application_passwords'] ) ? '1' : '0' );

	set_transient( 'blueworx_labs_notice', __( 'Settings saved.', 'blueworx-labs-wordpress' ), 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-labs-wordpress' ) );
	exit;
}
add_action( 'admin_post_blueworx_save_feature_settings', 'blueworx_save_feature_settings' );
```

- [ ] **Step 2: Remove the two superseded handlers**

Delete `blueworx_save_site_protection_settings()` + its `add_action`, and `blueworx_save_application_passwords_setting()` + its `add_action`, from `includes/admin-settings.php`. Then confirm nothing references the removed action names:

Run: `grep -rn "blueworx_save_site_protection_settings\|blueworx_save_application_passwords_setting" includes`
Expected: no results.

- [ ] **Step 3: Verify option-write parity (no unintended orphan reads)**

Run: `grep -rn "get_option( 'blueworx_show_application_passwords'\|_protection_enabled\|blueworx_login_slug" includes | grep -v feature`
Expected: readers exist in `login-security.php`/`admin-settings.php`/`profile-cleanup.php` and all now have matching writers.

- [ ] **Step 4: Syntax check**

Run: `php -l includes/admin-settings.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add includes/admin-settings.php
git commit -m "Add unified feature-settings save handler; remove old inline handlers"
```

---

### Task 7: Settings-page JS (show/hide nested details)

**Files:**
- Create: `assets/js/feature-settings.js`
- Modify: `includes/admin-assets.php` (enqueue on the top-level page)

**Interfaces:**
- Consumes: markup from Task 5 (`.blueworx-feature-toggle[data-blueworx-feature]`, `.blueworx-feature-detail[data-blueworx-detail]`).

- [ ] **Step 1: Create `assets/js/feature-settings.js`**

```js
( function () {
	'use strict';

	function sync( toggle ) {
		var key = toggle.getAttribute( 'data-blueworx-feature' );
		var detail = document.querySelector( '.blueworx-feature-detail[data-blueworx-detail="' + key + '"]' );
		if ( detail ) {
			detail.hidden = ! toggle.checked;
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var toggles = document.querySelectorAll( '.blueworx-feature-toggle' );
		Array.prototype.forEach.call( toggles, function ( toggle ) {
			sync( toggle );
			toggle.addEventListener( 'change', function () {
				sync( toggle );
			} );
		} );
	} );
}() );
```

- [ ] **Step 2: Enqueue it on the top-level Enhancements screen**

In `includes/admin-assets.php`, inside `blueworx_enqueue_admin_assets()`, add a block that enqueues the script when `$hook_suffix === 'toplevel_page_blueworx-labs-wordpress'`:

```php
	if ( 'toplevel_page_blueworx-labs-wordpress' === $hook_suffix ) {
		wp_enqueue_script(
			'blueworx-labs-wordpress-feature-settings',
			BLUEWORX_LABS_URL . 'assets/js/feature-settings.js',
			array(),
			blueworx_get_admin_asset_version( 'assets/js/feature-settings.js' ),
			true
		);
	}
```

- [ ] **Step 3: Lint the JS**

Run: `npm run lint`
Expected: no errors for `assets/js/feature-settings.js` (present findings to the user; do not auto-fix per project rule).

- [ ] **Step 4: Syntax check + commit**

Run: `php -l includes/admin-assets.php`
Expected: `No syntax errors detected`.

```bash
git add assets/js/feature-settings.js includes/admin-assets.php
git commit -m "Show or hide nested feature details as toggles change"
```

---

### Task 8: Playwright functional test

**Files:**
- Create: `tests/feature-toggles.spec.js`

**Interfaces:**
- Consumes: a running WordPress instance with the plugin active (the CI `preview_url`; locally, a WP with an admin login). The test is written to be skipped gracefully if `WP_BASE_URL`/`WP_ADMIN_USER`/`WP_ADMIN_PASS` env vars are not set, matching the existing specs' style.

- [ ] **Step 1: Inspect the existing spec conventions**

Run: `sed -n '1,40p' tests/smoke.spec.js`
Expected: shows how the suite reads base URL/credentials and guards when unset. Mirror that pattern (env var names, `test.skip`).

- [ ] **Step 2: Write the spec**

```js
import { test, expect } from '@playwright/test';

const BASE = process.env.WP_BASE_URL;
const USER = process.env.WP_ADMIN_USER;
const PASS = process.env.WP_ADMIN_PASS;

test.describe( 'BlueWorx feature toggles', () => {
	test.skip( ! BASE || ! USER || ! PASS, 'WP_BASE_URL / WP_ADMIN_USER / WP_ADMIN_PASS not set' );

	test( 'settings page shows grouped sections and a Comments toggle', async ( { page } ) => {
		await page.goto( `${ BASE }/wp-admin/admin.php?page=blueworx-labs-wordpress` );
		// Log in if redirected to the login screen.
		if ( await page.locator( '#user_login' ).count() ) {
			await page.fill( '#user_login', USER );
			await page.fill( '#user_pass', PASS );
			await page.click( '#wp-submit' );
			await page.goto( `${ BASE }/wp-admin/admin.php?page=blueworx-labs-wordpress` );
		}
		await expect( page.getByRole( 'heading', { name: 'Security & Access' } ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'Performance' } ) ).toBeVisible();
		await expect( page.locator( 'input.blueworx-feature-toggle[data-blueworx-feature="comments"]' ) ).toBeVisible();
	} );

	test( 'toggling a feature persists after save', async ( { page } ) => {
		await page.goto( `${ BASE }/wp-admin/admin.php?page=blueworx-labs-wordpress` );
		const toggle = page.locator( 'input.blueworx-feature-toggle[data-blueworx-feature="page_excerpts"]' );
		const wasChecked = await toggle.isChecked();
		await toggle.setChecked( ! wasChecked );
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();
		await expect( page.locator( '.notice-success' ) ).toContainText( 'Settings saved' );
		await expect( page.locator( 'input.blueworx-feature-toggle[data-blueworx-feature="page_excerpts"]' ) ).toBeChecked( { checked: ! wasChecked } );
		// Restore original state.
		await page.locator( 'input.blueworx-feature-toggle[data-blueworx-feature="page_excerpts"]' ).setChecked( wasChecked );
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();
	} );
} );
```

- [ ] **Step 3: Run the spec locally if a WP instance is available**

Run: `WP_BASE_URL=... WP_ADMIN_USER=... WP_ADMIN_PASS=... npx playwright test tests/feature-toggles.spec.js`
Expected: PASS, or SKIPPED when env vars are unset. If no local WP, rely on CI against the preview URL.

- [ ] **Step 4: Commit**

```bash
git add tests/feature-toggles.spec.js
git commit -m "Add Playwright test for feature-toggle settings page"
```

---

### Task 9: Version bump, changelog, verification

**Files:**
- Modify: `blueworx-labs-wordpress.php` (header `Version:` + `BLUEWORX_LABS_VERSION`), `package.json` (`version`), `readme.txt` (`Stable tag`), `CHANGELOG.md`

**Interfaces:** none (release metadata).

- [ ] **Step 1: Bump version to 1.9.0 in all four locations**

- `blueworx-labs-wordpress.php`: header ` * Version:           1.9.0` and `define( 'BLUEWORX_LABS_VERSION', '1.9.0' );`
- `package.json`: `"version": "1.9.0",`
- `readme.txt`: `Stable tag:        1.9.0`

- [ ] **Step 2: Add the CHANGELOG entry**

Insert above the top existing entry:

```markdown
## [1.9.0] - 2026-07-13

### Added
- **Feature settings page.** BlueWorx → Enhancements is now a grouped settings
  form with an on/off toggle for every enhancement function, organized into
  Security & Access, Content, Notifications & Cleanup, Performance, and Admin
  Menu sections. Disabling a function makes it fully inert and hides its page
  and detail controls; every function defaults on, so existing sites are
  unchanged.
- **Editable login slug.** The custom login path is now configurable on the
  settings page (was the fixed `admin_login`). Turning the login function off
  restores the standard WordPress login.
```

- [ ] **Step 3: Verify version sync**

Run: `node scripts/version-check.mjs`
Expected: `version:check OK — plugin header and package.json agree (1.9.0).`

- [ ] **Step 4: Full syntax sweep**

Run: `for f in blueworx-labs-wordpress.php includes/*.php; do php -l "$f" >/dev/null && echo "OK $f" || echo "FAIL $f"; done`
Expected: `OK` for every file.

- [ ] **Step 5: Drive it in a real WP instance (verification skill)**

Load the built zip into a test WordPress, open BlueWorx → Enhancements, and confirm: five sections render; toggling `comments` off reopens comments; toggling `login` off restores `/wp-login.php`; editing the login slug changes the active login URL shown; Cache and Edit Menu pages disappear when their toggles are off. Fix anything that does not behave as specified before claiming completion.

- [ ] **Step 6: Commit**

```bash
git add blueworx-labs-wordpress.php package.json readme.txt CHANGELOG.md
git commit -m "Bump to 1.9.0 for feature settings page"
```

---

## Self-Review

**Spec coverage:**
- Per-function toggles → Tasks 2–5 (gating) + Task 5 (form). ✓
- Default on / no migration → Task 1 getter (`'0' !== …` default `'1'`). ✓
- Five grouped sections → Task 1 registry + Task 5 render. ✓
- Fully inert + hidden off-behavior → Tasks 2–4 (hook + submenu gating), Task 5/7 (hidden detail). ✓
- Merged login + editable slug, safe fallback → Task 2. ✓
- Detail options preserved (never deleted) → Task 6 writes, never deletes. ✓
- `cache-refresh.php` / `profile-cleanup.php` dual features → Task 3. ✓
- `login-security.php` dual feature (login + site protection) → Task 2 (documented nuance). ✓
- Playwright test → Task 8. ✓
- Version bump + changelog → Task 9. ✓
- Headless untouched → no task modifies `includes/rest/*`. ✓

**Placeholder scan:** No TBD/TODO; all code blocks are concrete.

**Type/name consistency:** Feature keys (`login`, `site_protection`, `application_passwords`, `comments`, `page_excerpts`, `emails`, `profile_cleanup`, `cache_auto`, `cache_manual`, `menu_editor`) are identical across Tasks 1, 3, 5, 6. Option names (`blueworx_feature_{key}`, `blueworx_login_slug`, `blueworx_{area}_protection_enabled/roles`, `blueworx_show_application_passwords`) match between writers (Task 6) and readers (Tasks 2, 5, existing code). Handler/action `blueworx_save_feature_settings` matches between form (Task 5) and handler (Task 6).

**Note for the implementer:** Task 3 Step 4 and Task 4 Step 2 require reading the target file first to place guards precisely, because the registrations are interleaved with function definitions. The exact hook lines are listed; wrap only the top-level `add_action`/`add_filter` calls, never the function bodies.
