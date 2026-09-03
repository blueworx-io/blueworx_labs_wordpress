# BlueWorx: External viewer role — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `BlueWorx: External` role that mirrors Administrator but can change nothing, plus an invite screen that emails a named person a link to set their own password, with access that expires.

**Architecture:** The read-only enforcement already in `includes/support-access.php` is extracted, unchanged in behaviour, into `includes/readonly-access.php` and keyed on "does the current user hold a read-only role" instead of "is this the support account". Support access keeps its key, window and login. External access is a second consumer of that guard, with its own role, per-person accounts, expiry and console screen.

**Tech Stack:** WordPress plugin PHP (procedural, `blueworx_` prefixed functions, WordPress Coding Standards via `phpcs.xml.dist`), the plugin's own design-system helpers in `includes/admin-design.php`, Playwright for browser tests, plain PHP scripts under `tests/php/` for pure-logic tests.

**Spec:** `docs/superpowers/specs/2026-09-02-external-viewer-role-design.md`

## Global Constraints

- Every function is prefixed `blueworx_`. No classes; this codebase is procedural.
- Every file starts with `if ( ! defined( 'ABSPATH' ) ) { exit; }` except test scripts.
- Text domain is `blueworx-labs-wordpress` on every translatable string.
- Tabs for indentation in PHP, per `phpcs.xml.dist`.
- Every feature is gated by `blueworx_feature_enabled( '<key>' )`.
- The `external_access` feature default is `'0'` — off until a site owner switches it on.
- Role slug is exactly `blueworx_external`. Role display name is exactly `BlueWorx: External`.
- Default invite duration is 30 days; the offered durations are 7, 30 and 90.
- Playwright tests run against the local `.wp-test` harness on **port 8882**, never the staging URL in `.env`.
- After the harness `up`, delete the duplicate plugin symlink it creates — two copies of the plugin break `plugin_basename()` and the custom login path.
- CI requires: lint passes, build passes, version bumped on the PR, changelog updated alongside it.
- Work happens on branch `add-external-viewer-role`, which already exists and holds the spec commit.
- Run the linter once, at the end. Do not loop lint → fix → lint. Present findings to Luke and wait.

---

### Task 1: Extract the read-only guard

Behaviour-preserving move. Nothing about support access may change. `tests/support-access.spec.js` is the proof and must not be edited.

**Files:**
- Create: `includes/readonly-access.php`
- Modify: `includes/support-access.php` (remove the moved functions, add thin wrappers)
- Modify: `blueworx-labs-wordpress.php:118` (require the new file before `support-access.php`)
- Test: `tests/php/readonly-access-test.php`
- Modify: `package.json` (`test:php` script), `tests/php-checks.spec.js` (`SCRIPTS` array)

**Interfaces:**
- Consumes: `blueworx_support_role_slug()`, `blueworx_support_data_open()`, `blueworx_support_log_event()` — all already in `includes/support-access.php`.
- Produces:
  - `blueworx_readonly_roles() : array` — role slugs treated as read-only.
  - `blueworx_readonly_current_user() : WP_User|null`
  - `blueworx_readonly_user_has_role( $user, $slug ) : bool`
  - `blueworx_readonly_removed_caps() : array`
  - `blueworx_readonly_build_caps() : array`
  - `blueworx_readonly_data_allowed( $user ) : bool`
  - `blueworx_readonly_log_event( $user, $type ) : void`
  - `blueworx_external_role_slug() : string` — defined in Task 2; Task 1 guards its use with `function_exists()`.

- [ ] **Step 1: Write the failing test**

Create `tests/php/readonly-access-test.php`. It stands WordPress up in stubs, exactly as `tests/php/view-as-access-test.php` does, and checks the pure parts of the guard.

```php
<?php
/**
 * Which accounts the read-only guard applies to, and what it strips.
 *
 * The guard is what makes both BlueWorx support access and BlueWorx: External
 * read-only. Its rules are pure — a user and a role table in, a decision out —
 * so they are checked here rather than by driving a browser. That the block
 * actually fires on a live request is covered by tests/support-access.spec.js
 * and tests/external-readonly.spec.js.
 *
 * Run with: php tests/php/readonly-access-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Same: the text domain is part of the signature.

/** The administrator role this pretend site clones from. */
$GLOBALS['roles'] = array(
	'administrator' => array(
		'name'         => 'Administrator',
		'capabilities' => array(
			'read'             => true,
			'edit_posts'       => true,
			'delete_posts'     => true,
			'manage_options'   => true,
			'install_plugins'  => true,
			'unfiltered_html'  => true,
			'edit_users'       => true,
			'promote_users'    => true,
			'manage_woocommerce' => true,
		),
	),
);

class WP_User {
	public $ID = 0;
	public $roles = array();
	public $user_login = '';
	private $exists = true;

	public function __construct( $id = 0, $roles = array(), $login = '' ) {
		$this->ID         = $id;
		$this->roles      = $roles;
		$this->user_login = $login;
	}

	public function exists() {
		return $this->exists;
	}
}

class WP_Role {
	public $capabilities = array();

	public function __construct( $capabilities ) {
		$this->capabilities = $capabilities;
	}
}

function get_role( $slug ) {
	return isset( $GLOBALS['roles'][ $slug ] )
		? new WP_Role( $GLOBALS['roles'][ $slug ]['capabilities'] )
		: null;
}

function wp_get_current_user() {
	return isset( $GLOBALS['current_user'] ) ? $GLOBALS['current_user'] : new WP_User( 0, array() );
}

function get_current_user_id() {
	$user = wp_get_current_user();

	return (int) $user->ID;
}

// The support half of the pair, which the guard asks about by name.
function blueworx_support_role_slug() {
	return 'blueworx_support';
}

function blueworx_support_data_open() {
	return ! empty( $GLOBALS['support_data_open'] );
}

function blueworx_support_log_event( $type ) {
	$GLOBALS['support_log'][] = $type;
}

function blueworx_external_role_slug() {
	return 'blueworx_external';
}

require __DIR__ . '/../../includes/readonly-access.php';

echo "Who the guard applies to\n";

$GLOBALS['current_user'] = new WP_User( 1, array( 'administrator' ), 'luke' );
check( 'an administrator is not read-only', null === blueworx_readonly_current_user(), true );

$GLOBALS['current_user'] = new WP_User( 2, array( 'blueworx_support' ), 'blueworx_support' );
check( 'the support account is', blueworx_readonly_current_user() instanceof WP_User, true );

$GLOBALS['current_user'] = new WP_User( 3, array( 'blueworx_external' ), 'client' );
check( 'and an external viewer is', blueworx_readonly_current_user() instanceof WP_User, true );

$GLOBALS['current_user'] = new WP_User( 0, array() );
check( 'a signed-out visitor is not', null === blueworx_readonly_current_user(), true );

echo "\nWhat the cloned role loses\n";

$caps = blueworx_readonly_build_caps();

check( 'installing plugins is gone', isset( $caps['install_plugins'] ), false );
check( 'raw HTML is gone', isset( $caps['unfiltered_html'] ), false );
check( 'managing users is gone', isset( $caps['edit_users'] ), false );
check( 'promoting users is gone', isset( $caps['promote_users'] ), false );
check( 'deleting posts is gone', isset( $caps['delete_posts'] ), false );
check( 'but reading the settings screens is kept', ! empty( $caps['manage_options'] ), true );
check( 'and whatever the shop added is kept', ! empty( $caps['manage_woocommerce'] ), true );
check( 'and read is always present', ! empty( $caps['read'] ), true );

echo "\nWho may see personal data\n";

$support  = new WP_User( 2, array( 'blueworx_support' ), 'blueworx_support' );
$external = new WP_User( 3, array( 'blueworx_external' ), 'client' );

$GLOBALS['support_data_open'] = false;
check( 'support cannot, with the switch off', blueworx_readonly_data_allowed( $support ), false );

$GLOBALS['support_data_open'] = true;
check( 'support can, with the switch on', blueworx_readonly_data_allowed( $support ), true );
check( 'an external viewer never can', blueworx_readonly_data_allowed( $external ), false );

finish();
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/php/readonly-access-test.php`
Expected: FAIL — `includes/readonly-access.php` does not exist, so the `require` errors.

- [ ] **Step 3: Create the guard file**

Create `includes/readonly-access.php`. Move these functions out of `includes/support-access.php` verbatim, renaming them and swapping their `blueworx_support_is_support_user()` opening check for the new one. Do not rewrite their bodies or their docblocks beyond the renames — the comments in them explain decisions that still hold, and losing them loses the reasoning.

Functions to move and rename:

| From `support-access.php` | To `readonly-access.php` |
|---|---|
| `blueworx_support_removed_caps` | `blueworx_readonly_removed_caps` |
| `blueworx_support_denied_meta_caps` | `blueworx_readonly_denied_meta_caps` |
| `blueworx_support_deny_meta_caps` | `blueworx_readonly_deny_meta_caps` |
| `blueworx_support_build_caps` | `blueworx_readonly_build_caps` |
| `blueworx_support_denied_screens` | `blueworx_readonly_denied_screens` |
| `blueworx_support_woocommerce_active` | `blueworx_readonly_woocommerce_active` |
| `blueworx_support_surecart_active` | `blueworx_readonly_surecart_active` |
| `blueworx_support_denied_admin_pages` | `blueworx_readonly_denied_admin_pages` |
| `blueworx_support_denied_post_types` | `blueworx_readonly_denied_post_types` |
| `blueworx_support_denied_routes` | `blueworx_readonly_denied_routes` |
| `blueworx_support_screen_is_denied` | `blueworx_readonly_screen_is_denied` |
| `blueworx_support_gate_data_screens` | `blueworx_readonly_gate_data_screens` |
| `blueworx_support_action_screens` | `blueworx_readonly_action_screens` |
| `blueworx_support_readonly_actions` | `blueworx_readonly_allowed_actions` |
| `blueworx_support_gate_write_actions` | `blueworx_readonly_gate_write_actions` |
| `blueworx_support_route_is_own_record` | `blueworx_readonly_route_is_own_record` |
| `blueworx_support_gate_data_routes` | `blueworx_readonly_gate_data_routes` |
| `blueworx_support_is_heartbeat_request` | `blueworx_readonly_is_heartbeat_request` |
| `blueworx_support_disable_heartbeat` | `blueworx_readonly_disable_heartbeat` |
| `blueworx_support_block_writes` | `blueworx_readonly_block_writes` |
| `blueworx_support_block_rest_writes` | `blueworx_readonly_block_rest_writes` |

The file's own new code, at the top:

```php
<?php
/**
 * The shared read-only guard.
 *
 * Two roles in this plugin are read-only: the BlueWorx support account, which
 * signs in with a key inside a window, and BlueWorx: External, which is a named
 * client account with an expiry date. What "read-only" MEANS is the same for
 * both, and it lives here so there is one implementation to review and one
 * place a gap gets closed.
 *
 * The guarantee is not the capability map. It is blueworx_readonly_block_writes(),
 * which refuses every non-GET request these accounts make. Third-party plugins
 * routinely write through their own AJAX and REST endpoints without checking a
 * meaningful capability, so a rule that depends on plugin authors behaving
 * correctly is not a safety model. A method-level block does not depend on them.
 *
 * The capability map still does a job the block cannot: WordPress trashes,
 * deletes and activates through nonce'd GET links, which the method block never
 * sees, and capabilities such as unfiltered_html and install_plugins are onward
 * access rather than a write.
 *
 * Known gap, disclosed on both consoles: a plugin that writes in response to an
 * ordinary GET request is not caught here.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The roles this guard applies to.
 *
 * blueworx_external_role_slug() is guarded because includes/external-access.php
 * loads after this file, and because a build that ships only support access
 * must still work.
 *
 * @return array Role slugs.
 */
function blueworx_readonly_roles() {
	$roles = array( blueworx_support_role_slug() );

	if ( function_exists( 'blueworx_external_role_slug' ) ) {
		$roles[] = blueworx_external_role_slug();
	}

	/**
	 * Filters the roles treated as read-only.
	 *
	 * Adding a role here subjects it to the write block, the GET-action gate and
	 * the personal-data screens gate. It does not create the role or grant it
	 * anything.
	 *
	 * @param array $roles Role slugs.
	 */
	return (array) apply_filters( 'blueworx_readonly_roles', $roles );
}

/**
 * Whether a user holds a given role.
 *
 * @param mixed  $user User to test.
 * @param string $slug Role slug.
 * @return bool True when the user holds it.
 */
function blueworx_readonly_user_has_role( $user, $slug ) {
	return $user instanceof WP_User && in_array( $slug, (array) $user->roles, true );
}

/**
 * The current user, when this request is a read-only one.
 *
 * Returns the user rather than a boolean because every caller that needs the
 * answer also needs to know WHICH read-only account is asking — the two roles
 * differ on personal data and on where their events are logged.
 *
 * @return WP_User|null Read-only user, or null.
 */
function blueworx_readonly_current_user() {
	$user = wp_get_current_user();

	if ( ! $user instanceof WP_User || ! $user->exists() ) {
		return null;
	}

	foreach ( blueworx_readonly_roles() as $slug ) {
		if ( blueworx_readonly_user_has_role( $user, $slug ) ) {
			return $user;
		}
	}

	return null;
}

/**
 * Whether this read-only user may see personal data.
 *
 * Support access has a switch for it, opened deliberately and for a window.
 * External access has none: a client looking round a demo never needs the
 * customer list, so the answer is always no rather than a setting somebody
 * could leave on.
 *
 * @param mixed $user Read-only user.
 * @return bool True when personal-data screens are allowed.
 */
function blueworx_readonly_data_allowed( $user ) {
	if ( blueworx_readonly_user_has_role( $user, blueworx_support_role_slug() ) ) {
		return blueworx_support_data_open();
	}

	return false;
}

/**
 * Records a refusal against whichever account caused it.
 *
 * Support access keeps an audit log, because a BlueWorx agent working on
 * somebody else's site has to be accountable to its owner. External access does
 * not: it is the site owner's own demo, the account is named, and a log of every
 * blocked click on it would be noise nobody reads.
 *
 * @param mixed  $user Read-only user.
 * @param string $type Event type.
 * @return void
 */
function blueworx_readonly_log_event( $user, $type ) {
	if ( blueworx_readonly_user_has_role( $user, blueworx_support_role_slug() ) ) {
		blueworx_support_log_event( $type );
	}

	/**
	 * Fires when a read-only account is refused something.
	 *
	 * @param string $type Event type.
	 * @param mixed  $user The refused user.
	 */
	do_action( 'blueworx_readonly_event', $type, $user );
}
```

Rules for the moved bodies:

1. Each moved function opens by resolving the user once:

```php
$user = blueworx_readonly_current_user();

if ( ! $user ) {
	return;
}
```

   — replacing `if ( ! blueworx_support_is_support_user() ) { return; }`. The REST filters return `$result` instead of returning bare.

2. The two data gates swap `blueworx_support_data_open()` for `blueworx_readonly_data_allowed( $user )`.

3. Every `blueworx_support_log_event( 'blocked_write' )` becomes `blueworx_readonly_log_event( $user, 'blocked_write' )`.

4. `blueworx_readonly_route_is_own_record()` currently resolves the support account by login. It must compare against the calling user instead, so it works for both:

```php
function blueworx_readonly_route_is_own_record( $route, $user ) {
	if ( '/wp/v2/users/me' === $route ) {
		return true;
	}

	if ( ! preg_match( '#^/wp/v2/users/(\d+)$#', $route, $matches ) ) {
		return false;
	}

	return $user instanceof WP_User && (int) $user->ID === (int) $matches[1];
}
```

5. Every `wp_die()` and `WP_Error` message that says "BlueWorx support access is read-only" becomes wording that fits either account. Use:
   - Write block: `__( 'This account is read-only. Nothing on this site can be changed from it.', 'blueworx-labs-wordpress' )`
   - GET action gate: `__( 'This account is read-only: this action is refused.', 'blueworx-labs-wordpress' )`
   - Data gate: `__( 'This screen holds personal data and is not available to this account.', 'blueworx-labs-wordpress' )`
   - Data route gate: `__( 'This route returns personal data and is not available to this account.', 'blueworx-labs-wordpress' )`
   - Title on every `wp_die()`: `__( 'Read-only access', 'blueworx-labs-wordpress' )`
   - `WP_Error` codes stay `blueworx_support_read_only` and `blueworx_support_no_data`, because something outside may match on them.

6. The public filters keep their existing names AND gain the new ones, so no site's customisation breaks. Pattern, applied to all five of `denied_screens`, `denied_admin_pages`, `denied_post_types`, `denied_routes`, `action_screens`, and `readonly_actions`:

```php
	/** This filter is documented in includes/readonly-access.php */
	$screens = (array) apply_filters( 'blueworx_support_denied_screens', $screens );

	/**
	 * Filters the screens hidden from read-only accounts.
	 *
	 * @param array $screens $pagenow values.
	 */
	return (array) apply_filters( 'blueworx_readonly_denied_screens', $screens );
```

7. The `add_action`/`add_filter` registrations move with their functions, keeping identical hook names and priorities: `map_meta_cap` 10, `admin_init` 0 for both gates, `init` 0 for the write block, `rest_pre_dispatch` 10 for the write block and 11 for the data gate, `admin_enqueue_scripts`/`wp_enqueue_scripts` 1 for heartbeat.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php tests/php/readonly-access-test.php`
Expected: PASS, every line ticked.

- [ ] **Step 5: Leave the support wrapper behind**

In `includes/support-access.php`, delete the moved functions and their `add_action`/`add_filter` lines, and keep this so `includes/view-as-role.php:blueworx_view_as_available()` and the console keep working unchanged:

```php
/**
 * Whether the current request is running as the support user.
 *
 * Both the login AND the role are required. The login alone is not an identity
 * claim anyone else is stopped from making: on a site with open registration a
 * visitor could once have signed up as "blueworx_support" and bypassed Site
 * Protection with no key and no window.
 *
 * @return bool True for the support account.
 */
function blueworx_support_is_support_user() {
	$user = wp_get_current_user();

	return $user instanceof WP_User
		&& $user->exists()
		&& 'blueworx_support' === $user->user_login
		&& blueworx_readonly_user_has_role( $user, blueworx_support_role_slug() );
}
```

`blueworx_support_ensure_account()` now calls `blueworx_readonly_build_caps()` where it called `blueworx_support_build_caps()`. Nothing else in that file changes.

- [ ] **Step 6: Load the guard before its callers**

In `blueworx-labs-wordpress.php`, add above the existing `support-access.php` require on line 118:

```php
require_once BLUEWORX_LABS_PATH . 'includes/readonly-access.php';
```

- [ ] **Step 7: Wire the new PHP test into both runners**

In `package.json`, append to the `test:php` script: ` && php tests/php/readonly-access-test.php`

In `tests/php-checks.spec.js`, add `'readonly-access-test.php',` to the `SCRIPTS` array.

- [ ] **Step 8: Prove support access is unchanged**

Run: `npx playwright test tests/support-access.spec.js`
Expected: PASS, with `tests/support-access.spec.js` unedited. If it needs editing, the extraction was not behaviour-preserving — that is the bug, fix the extraction rather than the test.

- [ ] **Step 9: Commit**

```bash
git add includes/readonly-access.php includes/support-access.php blueworx-labs-wordpress.php tests/php/readonly-access-test.php tests/php-checks.spec.js package.json
git commit -m "Move the read-only guard out of support access so a second role can use it

Behaviour-preserving: support access keeps its own key, window and log, and
its browser tests are unchanged.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: The External role and its feature toggle

**Files:**
- Create: `includes/external-access.php`
- Modify: `includes/features.php` (new `external_access` entry)
- Modify: `blueworx-labs-wordpress.php` (require; deactivation hook)
- Test: `tests/php/external-access-test.php`
- Modify: `package.json`, `tests/php-checks.spec.js`

**Interfaces:**
- Consumes: `blueworx_readonly_build_caps()`, `blueworx_feature_enabled()`, `blueworx_set_feature_enabled()`.
- Produces:
  - `blueworx_external_role_slug() : string` → `'blueworx_external'`
  - `blueworx_external_role_name() : string` → `'BlueWorx: External'`
  - `blueworx_external_register_role() : void`
  - `blueworx_external_remove_role() : void`
  - `blueworx_external_is_external_user( $user ) : bool`
  - `blueworx_external_durations() : array` → `array( 7, 30, 90 )`
  - `blueworx_external_default_duration() : int` → `30`
  - `blueworx_external_sanitize_duration( $days ) : int`
  - `blueworx_external_expiry_from( $now, $days ) : int`
  - Meta key constants `BLUEWORX_EXTERNAL_META_*` as listed below.

- [ ] **Step 1: Write the failing test**

Create `tests/php/external-access-test.php`:

```php
<?php
/**
 * The pure rules behind an external invitation.
 *
 * Durations, expiry maths and the username derived from an email address are
 * decided without WordPress, so they are checked without it. Creating the
 * account, sending the mail and refusing an expired sign-in need a running
 * site and are covered by tests/external-access.spec.js.
 *
 * Run with: php tests/php/external-access-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Same: the text domain is part of the signature.

class WP_User {
	public $ID = 0;
	public $roles = array();

	public function __construct( $id = 0, $roles = array() ) {
		$this->ID    = $id;
		$this->roles = $roles;
	}

	public function exists() {
		return $this->ID > 0;
	}
}

function blueworx_feature_enabled( $key ) {
	return empty( $GLOBALS['feature_off'] );
}

function blueworx_readonly_build_caps() {
	return array( 'read' => true, 'manage_options' => true );
}

function blueworx_readonly_user_has_role( $user, $slug ) {
	return $user instanceof WP_User && in_array( $slug, (array) $user->roles, true );
}

require __DIR__ . '/../../includes/external-access.php';

echo "The role is named the way every other role in this plugin is\n";

check( 'the slug', blueworx_external_role_slug(), 'blueworx_external' );
check( 'the name', blueworx_external_role_name(), 'BlueWorx: External' );

echo "\nWho counts as an external viewer\n";

check(
	'an account holding the role does',
	blueworx_external_is_external_user( new WP_User( 4, array( 'blueworx_external' ) ) ),
	true
);
check(
	'an administrator does not',
	blueworx_external_is_external_user( new WP_User( 1, array( 'administrator' ) ) ),
	false
);
check( 'and nor does nobody at all', blueworx_external_is_external_user( null ), false );

echo "\nHow long an invitation lasts\n";

check( 'the default is thirty days', blueworx_external_default_duration(), 30 );
check( 'seven is offered', in_array( 7, blueworx_external_durations(), true ), true );
check( 'ninety is offered', in_array( 90, blueworx_external_durations(), true ), true );

echo "\nA duration nobody offered falls back rather than being honoured\n";

check( 'a value off the list', blueworx_external_sanitize_duration( 3650 ), 30 );
check( 'a negative value', blueworx_external_sanitize_duration( -1 ), 30 );
check( 'junk', blueworx_external_sanitize_duration( 'forever' ), 30 );
check( 'and a value on the list is kept', blueworx_external_sanitize_duration( '7' ), 7 );

echo "\nExpiry is counted from now, not from midnight\n";

$now = 1000000;

check( 'thirty days on', blueworx_external_expiry_from( $now, 30 ), $now + ( 30 * DAY_IN_SECONDS ) );
check( 'seven days on', blueworx_external_expiry_from( $now, 7 ), $now + ( 7 * DAY_IN_SECONDS ) );

echo "\nA username is derived from the email address\n";

check( 'the local part is used', blueworx_external_username_from_email( 'jane.doe@example.com' ), 'jane.doe' );
check( 'punctuation nobody can type is dropped', blueworx_external_username_from_email( 'a+tag@example.com' ), 'atag' );
check( 'and an unusable address still yields something', blueworx_external_username_from_email( '@@@' ), 'external' );

finish();
```

Add `define( 'DAY_IN_SECONDS', 86400 );` to `tests/php/stubs.php` if it is not already there, and a `sanitize_user()` stub already exists.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/php/external-access-test.php`
Expected: FAIL — `includes/external-access.php` does not exist.

- [ ] **Step 3: Write the role half of the file**

Create `includes/external-access.php` with everything below. Later tasks append to this same file.

```php
<?php
/**
 * BlueWorx: External — a read-only viewer account for people you invite.
 *
 * The point is showing somebody round the backend of a site without handing
 * them the ability to change it: a client evaluating the work, a contractor
 * being briefed, a colleague who needs to see rather than do.
 *
 * The role is a clone of the LIVE administrator role minus the capabilities
 * that are destructive or that grant onward access, and — as with support
 * access — the read-only guarantee is not that list. It is the request-layer
 * block in includes/readonly-access.php, which this role opts into by being
 * named in blueworx_readonly_roles().
 *
 * One account per invited person, never a shared login: a shared one cannot be
 * traced to anybody and cannot be withdrawn from one person without withdrawing
 * it from everyone.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User meta: who issued the invitation.
 */
const BLUEWORX_EXTERNAL_META_INVITED_BY = '_blueworx_external_invited_by';

/**
 * User meta: when the invitation was issued.
 */
const BLUEWORX_EXTERNAL_META_INVITED_AT = '_blueworx_external_invited_at';

/**
 * User meta: when access ends.
 */
const BLUEWORX_EXTERNAL_META_EXPIRES_AT = '_blueworx_external_expires_at';

/**
 * User meta: a free-text note about who this person is.
 */
const BLUEWORX_EXTERNAL_META_NOTE = '_blueworx_external_note';

/**
 * User meta: when they last signed in.
 */
const BLUEWORX_EXTERNAL_META_LAST_SEEN = '_blueworx_external_last_seen';

/**
 * Gets the external role slug.
 *
 * @return string Role slug.
 */
function blueworx_external_role_slug() {
	return 'blueworx_external';
}

/**
 * Gets the external role's displayed name.
 *
 * "BlueWorx: External" follows the convention in includes/display-names.php,
 * where every role says where it belongs before it says what it is — Site:
 * Editor, Commerce: Manager. Registering it under that name means it needs no
 * entry in the relabelling map.
 *
 * @return string Role name.
 */
function blueworx_external_role_name() {
	return 'BlueWorx: External';
}

/**
 * Registers the role, rebuilding its capabilities from the live administrator.
 *
 * Rebuilt rather than created-once because the role it clones changes: a
 * commerce or booking plugin installed next month adds its capabilities to
 * administrator, and a role frozen at first registration would quietly stop
 * showing the screens this feature exists to show.
 *
 * @return void
 */
function blueworx_external_register_role() {
	remove_role( blueworx_external_role_slug() );
	add_role(
		blueworx_external_role_slug(),
		blueworx_external_role_name(),
		blueworx_readonly_build_caps()
	);
}

/**
 * Removes the role, unless somebody still holds it.
 *
 * The same rule the plugin's earlier role sweeps use: a role with a user
 * assigned is left standing and recorded, because deleting it would strip that
 * account of every capability while leaving it able to sign in — a broken
 * account is worse than a tidy database.
 *
 * @return void
 */
function blueworx_external_remove_role() {
	$holders = get_users(
		array(
			'role'   => blueworx_external_role_slug(),
			'number' => 1,
			'fields' => 'ID',
		)
	);

	if ( ! empty( $holders ) ) {
		$skipped = (array) get_option( 'blueworx_orphaned_roles_skipped', array() );

		if ( ! in_array( blueworx_external_role_slug(), $skipped, true ) ) {
			$skipped[] = blueworx_external_role_slug();
			update_option( 'blueworx_orphaned_roles_skipped', $skipped );
		}

		return;
	}

	remove_role( blueworx_external_role_slug() );
}

/**
 * Whether a user is an external viewer.
 *
 * @param mixed $user User to test.
 * @return bool True when they hold the external role.
 */
function blueworx_external_is_external_user( $user ) {
	return blueworx_readonly_user_has_role( $user, blueworx_external_role_slug() );
}

/**
 * The invitation lengths on offer, in days.
 *
 * @return array Whole days.
 */
function blueworx_external_durations() {
	return array( 7, 30, 90 );
}

/**
 * The length used when nobody chooses one.
 *
 * @return int Whole days.
 */
function blueworx_external_default_duration() {
	return 30;
}

/**
 * Reduces a submitted duration to one that was actually offered.
 *
 * An allow-list rather than a range check: the form offers three values, and
 * anything else arriving is either a stale form or a hand-crafted POST. Falling
 * back to the default is safe in both cases, where honouring the number is not.
 *
 * @param mixed $days Submitted duration.
 * @return int Whole days.
 */
function blueworx_external_sanitize_duration( $days ) {
	$days = (int) $days;

	return in_array( $days, blueworx_external_durations(), true )
		? $days
		: blueworx_external_default_duration();
}

/**
 * Works out when access ends.
 *
 * Counted from the moment of invitation rather than from midnight, so "30 days"
 * is thirty days and does not silently become twenty-nine.
 *
 * @param int $now  Starting timestamp.
 * @param int $days Whole days.
 * @return int Expiry timestamp.
 */
function blueworx_external_expiry_from( $now, $days ) {
	return (int) $now + ( (int) $days * DAY_IN_SECONDS );
}

/**
 * Derives a username from an email address.
 *
 * The local part, stripped to what WordPress accepts. Uniquifying it against
 * accounts that already exist happens at invitation time, where the database
 * is available; this half is pure so it can be reasoned about on its own.
 *
 * @param string $email Email address.
 * @return string Username stem.
 */
function blueworx_external_username_from_email( $email ) {
	$local = (string) strstr( (string) $email, '@', true );

	if ( '' === $local ) {
		$local = (string) $email;
	}

	$name = sanitize_user( $local, true );

	return '' === $name ? 'external' : $name;
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php tests/php/external-access-test.php`
Expected: PASS.

- [ ] **Step 5: Register the feature**

In `includes/features.php`, add to `blueworx_get_feature_definitions()`, immediately after the `support_access` entry:

```php
		'external_access'       => array(
			'label'       => __( 'External viewer access', 'blueworx-labs-wordpress' ),
			'description' => __( 'Lets you invite somebody to look round the backend without being able to change anything. Each person gets their own sign-in, which they set themselves, and their access ends on a date you choose.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => 'external_access',
			'default'     => '0',
		),
```

- [ ] **Step 6: Load the file and register the role when the feature is on**

At the end of `includes/external-access.php`:

```php
if ( blueworx_feature_enabled( 'external_access' ) ) {
	// Registered on admin_init rather than at load: add_role() writes an option,
	// and doing that on every front-end request of every site is a database
	// write nobody asked for. The role only has to exist where it is assigned
	// and where capabilities are resolved, and admin_init covers both.
	add_action( 'admin_init', 'blueworx_external_register_role', 1 );
}
```

In `blueworx-labs-wordpress.php`, add after the `support-access.php` require:

```php
require_once BLUEWORX_LABS_PATH . 'includes/external-access.php';
```

and extend the deactivation hook. `register_deactivation_hook` takes one callback, so add a wrapper next to the existing line:

```php
/**
 * Tears down everything that must not outlive the plugin being switched off.
 *
 * Both read-only roles are near-administrator accounts whose safety comes from
 * the request-layer block in includes/readonly-access.php. With the plugin off
 * that block does not run, so the accounts must not be left standing.
 *
 * @return void
 */
function blueworx_labs_on_deactivate() {
	blueworx_support_on_deactivate();
	blueworx_external_on_deactivate();
}
register_deactivation_hook( __FILE__, 'blueworx_labs_on_deactivate' );
```

replacing line 138's existing `register_deactivation_hook( __FILE__, 'blueworx_support_on_deactivate' );`.

And in `includes/external-access.php`:

```php
/**
 * Withdraws external access when the plugin is switched off.
 *
 * Every invited account is deleted, not merely expired. Expiry is enforced by
 * this plugin; with it switched off, an account left standing is a working
 * administrator-shaped login that nothing is narrowing.
 *
 * @return void
 */
function blueworx_external_on_deactivate() {
	if ( ! function_exists( 'wp_delete_user' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}

	foreach ( blueworx_external_invitations() as $user ) {
		wp_delete_user( $user->ID );
	}

	blueworx_external_remove_role();
}
```

`blueworx_external_invitations()` is written in Task 3. Write this function now and accept that it is unused until then; it is registered on a hook that only fires on deactivation.

- [ ] **Step 7: Wire the test into both runners**

In `package.json`, append to `test:php`: ` && php tests/php/external-access-test.php`
In `tests/php-checks.spec.js`, add `'external-access-test.php',` to `SCRIPTS`.

- [ ] **Step 8: Commit**

```bash
git add includes/external-access.php includes/features.php blueworx-labs-wordpress.php tests/php/external-access-test.php tests/php/stubs.php tests/php-checks.spec.js package.json
git commit -m "Add the BlueWorx: External role, off until a site owner switches it on

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Issuing an invitation and sending the email

**Files:**
- Modify: `includes/external-access.php` (append)
- Test: `tests/php/external-access-test.php` (append)

**Interfaces:**
- Consumes: `blueworx_external_sanitize_duration()`, `blueworx_external_expiry_from()`, `blueworx_external_username_from_email()`, `blueworx_external_role_slug()`, the `BLUEWORX_EXTERNAL_META_*` constants.
- Produces:
  - `blueworx_external_invitations() : array` — `WP_User[]`, newest invitation first.
  - `blueworx_external_invite( array $args ) : int|WP_Error`
  - `blueworx_external_unique_username( $stem ) : string`
  - `blueworx_external_reset_url( WP_User $user ) : string`
  - `blueworx_external_send_invite( $user_id ) : bool`
  - `blueworx_external_expires_at( $user_id ) : int`

- [ ] **Step 1: Write the failing test**

Append to `tests/php/external-access-test.php`, before `finish();`. Add these stubs above the `require` of the plugin file:

```php
$GLOBALS['users']      = array();
$GLOBALS['next_id']    = 100;
$GLOBALS['mail_sent']  = array();
$GLOBALS['mail_fails'] = false;

function username_exists( $name ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( $user['login'] === $name ) {
			return $user['id'];
		}
	}

	return false;
}

function email_exists( $email ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( $user['email'] === $email ) {
			return $user['id'];
		}
	}

	return false;
}

function wp_insert_user( $data ) {
	$id = $GLOBALS['next_id']++;

	$GLOBALS['users'][ $id ] = array(
		'id'    => $id,
		'login' => $data['user_login'],
		'email' => $data['user_email'],
		'name'  => $data['display_name'],
		'role'  => $data['role'],
		'meta'  => array(),
	);

	return $id;
}

function update_user_meta( $id, $key, $value ) {
	$GLOBALS['users'][ $id ]['meta'][ $key ] = $value;

	return true;
}

function get_user_meta( $id, $key, $single = false ) {
	return isset( $GLOBALS['users'][ $id ]['meta'][ $key ] )
		? $GLOBALS['users'][ $id ]['meta'][ $key ]
		: '';
}

function wp_mail( $to, $subject, $message ) {
	if ( $GLOBALS['mail_fails'] ) {
		return false;
	}

	$GLOBALS['mail_sent'][] = array( 'to' => $to, 'subject' => $subject, 'message' => $message );

	return true;
}

function sanitize_email( $email ) {
	return trim( (string) $email );
}

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

function get_bloginfo( $what ) {
	return 'Demo Site';
}

function home_url( $path = '/' ) {
	return 'https://demo.example.com' . $path;
}

function network_site_url( $path = '', $scheme = null ) {
	// The plugin's custom-login filter rewrites this in a live site; the point
	// checked here is that whatever it returns is what the email carries.
	return 'https://demo.example.com/' . ltrim( (string) $path, '/' );
}

function get_password_reset_key( $user ) {
	return 'RESETKEY123';
}

function get_userdata( $id ) {
	if ( ! isset( $GLOBALS['users'][ $id ] ) ) {
		return false;
	}

	$row              = $GLOBALS['users'][ $id ];
	$user             = new WP_User( $row['id'], array( $row['role'] ) );
	$user->user_login = $row['login'];
	$user->user_email = $row['email'];
	$user->display_name = $row['name'];

	return $user;
}

function wp_get_current_user() {
	$user = new WP_User( 1, array( 'administrator' ) );
	$user->display_name = 'Luke';

	return $user;
}

function get_current_user_id() {
	return 1;
}

function current_time( $type = 'timestamp' ) {
	return 1000000;
}
```

`WP_User` in this file needs `$user_login`, `$user_email` and `$display_name` public properties added.

Then the checks:

```php
echo "\nAn invitation creates one account, with an expiry on it\n";

$id = blueworx_external_invite(
	array(
		'name'  => 'Jane Doe',
		'email' => 'jane@example.com',
		'note'  => 'Prospect, seen the pitch',
		'days'  => 30,
	)
);

check( 'the account was created', is_int( $id ) && $id > 0, true );
check( 'in the external role', $GLOBALS['users'][ $id ]['role'], 'blueworx_external' );
check( 'with a username off the address', $GLOBALS['users'][ $id ]['login'], 'jane' );
check(
	'and an expiry thirty days out',
	blueworx_external_expires_at( $id ),
	blueworx_external_expiry_from( 1000000, 30 )
);
check(
	'the note is kept',
	get_user_meta( $id, BLUEWORX_EXTERNAL_META_NOTE, true ),
	'Prospect, seen the pitch'
);
check(
	'and so is who invited them',
	(int) get_user_meta( $id, BLUEWORX_EXTERNAL_META_INVITED_BY, true ),
	1
);

echo "\nThe same address is not invited twice\n";

$again = blueworx_external_invite(
	array(
		'name'  => 'Jane Doe',
		'email' => 'jane@example.com',
		'days'  => 30,
	)
);

check( 'a duplicate is refused', is_wp_error( $again ), true );

echo "\nAnd an address that is not one is refused before an account exists\n";

$bad = blueworx_external_invite( array( 'name' => 'Nobody', 'email' => 'not-an-address', 'days' => 30 ) );

check( 'junk is refused', is_wp_error( $bad ), true );

echo "\nThe email carries a link to set a password, and no password\n";

$mail = end( $GLOBALS['mail_sent'] );

check( 'one was sent', is_array( $mail ), true );
check( 'to the person invited', $mail['to'], 'jane@example.com' );
check( 'carrying a reset link', false !== strpos( $mail['message'], 'action=rp' ), true );
check( 'and the key', false !== strpos( $mail['message'], 'RESETKEY123' ), true );
check( 'saying the access is view-only', false !== stripos( $mail['message'], 'view-only' ), true );

echo "\nA send that fails is reported rather than swallowed\n";

$GLOBALS['mail_fails'] = true;

$failed = blueworx_external_invite(
	array( 'name' => 'Sam', 'email' => 'sam@example.com', 'days' => 7 )
);

check( 'the account is still created', is_int( $failed ) && $failed > 0, true );
check( 'and the failure is visible to the caller', blueworx_external_send_invite( $failed ), false );

$GLOBALS['mail_fails'] = false;
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/php/external-access-test.php`
Expected: FAIL — `blueworx_external_invite()` is not defined.

- [ ] **Step 3: Write the invitation code**

Append to `includes/external-access.php`:

```php
/**
 * Every current invitation, newest first.
 *
 * The account IS the invitation record — there is no separate table — so
 * deleting the user withdraws the invitation completely and nothing can be
 * orphaned in a table the Users screen does not know about.
 *
 * @return array WP_User objects.
 */
function blueworx_external_invitations() {
	return (array) get_users(
		array(
			'role'    => blueworx_external_role_slug(),
			'orderby' => 'registered',
			'order'   => 'DESC',
		)
	);
}

/**
 * Makes a username nobody already holds.
 *
 * @param string $stem Username stem.
 * @return string Free username.
 */
function blueworx_external_unique_username( $stem ) {
	$stem = '' === (string) $stem ? 'external' : (string) $stem;
	$name = $stem;
	$n    = 2;

	while ( username_exists( $name ) ) {
		$name = $stem . $n;
		++$n;
	}

	return $name;
}

/**
 * Gets when an invitation ends.
 *
 * @param int $user_id Invited account.
 * @return int Timestamp, or 0 when none is recorded.
 */
function blueworx_external_expires_at( $user_id ) {
	return (int) get_user_meta( (int) $user_id, BLUEWORX_EXTERNAL_META_EXPIRES_AT, true );
}

/**
 * Invites somebody.
 *
 * The password set here is random and never communicated. The invitation email
 * carries a password-reset link instead, so the person chooses their own and no
 * credential ever sits in an inbox. That is also why the account is usable
 * immediately: a reset link is not a pending state, it is the way in.
 *
 * @param array $args name, email, note, days.
 * @return int|WP_Error New user ID, or the reason it was refused.
 */
function blueworx_external_invite( $args ) {
	$name  = sanitize_text_field( isset( $args['name'] ) ? $args['name'] : '' );
	$email = sanitize_email( isset( $args['email'] ) ? $args['email'] : '' );
	$note  = sanitize_text_field( isset( $args['note'] ) ? $args['note'] : '' );
	$days  = blueworx_external_sanitize_duration( isset( $args['days'] ) ? $args['days'] : 0 );

	if ( '' === $email || ! is_email( $email ) ) {
		return new WP_Error(
			'blueworx_external_bad_email',
			__( 'That is not an email address anything could be sent to.', 'blueworx-labs-wordpress' )
		);
	}

	if ( email_exists( $email ) ) {
		// Named rather than silent: an administrator who cannot see why nothing
		// happened will invite the same person again, and again.
		return new WP_Error(
			'blueworx_external_email_taken',
			__( 'Somebody with that email address already has an account on this site.', 'blueworx-labs-wordpress' )
		);
	}

	if ( '' === $name ) {
		$name = $email;
	}

	$user_id = wp_insert_user(
		array(
			'user_login'   => blueworx_external_unique_username( blueworx_external_username_from_email( $email ) ),
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 64, true, true ),
			'display_name' => $name,
			'role'         => blueworx_external_role_slug(),
		)
	);

	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	$user_id = (int) $user_id;
	$now     = (int) current_time( 'timestamp' );

	update_user_meta( $user_id, BLUEWORX_EXTERNAL_META_INVITED_BY, get_current_user_id() );
	update_user_meta( $user_id, BLUEWORX_EXTERNAL_META_INVITED_AT, $now );
	update_user_meta( $user_id, BLUEWORX_EXTERNAL_META_EXPIRES_AT, blueworx_external_expiry_from( $now, $days ) );
	update_user_meta( $user_id, BLUEWORX_EXTERNAL_META_NOTE, $note );

	blueworx_external_send_invite( $user_id );

	return $user_id;
}

/**
 * Builds the link that lets an invited person set their own password.
 *
 * Nothing here handles the plugin's custom login URL, and nothing needs to:
 * blueworx_replace_generated_login_url() in includes/login-security.php filters
 * network_site_url(), keeps the query string and swaps the path for the custom
 * slug. Building the address any other way would step around that filter and
 * send people to a URL this plugin blocks.
 *
 * @param WP_User $user Invited account.
 * @return string Reset URL.
 */
function blueworx_external_reset_url( $user ) {
	$key = get_password_reset_key( $user );

	if ( is_wp_error( $key ) ) {
		return '';
	}

	return network_site_url(
		'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ),
		'login'
	);
}

/**
 * Sends, or re-sends, the invitation email.
 *
 * Plain text. It says who invited them, that the access is view-only, when it
 * ends, and gives one link. It contains no password, because there is no
 * password to contain.
 *
 * @param int $user_id Invited account.
 * @return bool True when the mail was handed off successfully.
 */
function blueworx_external_send_invite( $user_id ) {
	$user = get_userdata( (int) $user_id );

	if ( ! $user instanceof WP_User ) {
		return false;
	}

	$link = blueworx_external_reset_url( $user );

	if ( '' === $link ) {
		return false;
	}

	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$host    = wp_get_current_user();
	$expires = blueworx_external_expires_at( $user_id );

	$subject = sprintf(
		/* translators: %s: site name. */
		__( 'You have been given a look round %s', 'blueworx-labs-wordpress' ),
		$site
	);

	$lines = array(
		sprintf(
			/* translators: 1: inviter's name, 2: site name. */
			__( '%1$s has given you view-only access to the back end of %2$s.', 'blueworx-labs-wordpress' ),
			$host instanceof WP_User && '' !== $host->display_name ? $host->display_name : $site,
			$site
		),
		'',
		__( 'You can look at everything an administrator sees. You cannot change anything, and nothing you click will alter the site.', 'blueworx-labs-wordpress' ),
		'',
		__( 'Choose a password to get started:', 'blueworx-labs-wordpress' ),
		$link,
		'',
		sprintf(
			/* translators: %s: date. */
			__( 'Your access ends on %s.', 'blueworx-labs-wordpress' ),
			date_i18n( get_option( 'date_format' ), $expires )
		),
		'',
		sprintf(
			/* translators: %s: username. */
			__( 'Your username is %s.', 'blueworx-labs-wordpress' ),
			$user->user_login
		),
	);

	return (bool) wp_mail( $user->user_email, $subject, implode( "\n", $lines ) );
}
```

Add `wp_specialchars_decode`, `date_i18n` and `ENT_QUOTES` handling to the test stubs — `function wp_specialchars_decode( $t, $q = 0 ) { return $t; }` and `function date_i18n( $f, $t ) { return gmdate( 'j F Y', (int) $t ); }`, plus `function get_option( $k, $d = false ) { return 'j F Y'; }` is already stubbed generically in `stubs.php`, so pass the format through as-is.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php tests/php/external-access-test.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/external-access.php tests/php/external-access-test.php
git commit -m "Invite an external viewer by email, with a link to set their own password

No credential is ever put in the email.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Expiry, extending and revoking

**Files:**
- Modify: `includes/external-access.php` (append)
- Test: `tests/php/external-access-test.php` (append)
- Create: `tests/fixtures/force-external-expiry.php`

**Interfaces:**
- Consumes: `blueworx_external_expires_at()`, `blueworx_external_is_external_user()`, `blueworx_external_sanitize_duration()`.
- Produces:
  - `blueworx_external_is_expired( $user_id ) : bool`
  - `blueworx_external_extend( $user_id, $days ) : bool`
  - `blueworx_external_revoke( $user_id ) : bool`
  - `blueworx_external_block_expired_login( $user, $username, $password ) : mixed` — `authenticate` filter callback.
  - `blueworx_external_enforce_expiry() : void` — `init` priority 2.
  - `blueworx_external_record_sign_in( $login, $user ) : void` — `wp_login` callback.

- [ ] **Step 1: Write the failing test**

Append to `tests/php/external-access-test.php`, before `finish();`:

```php
echo "\nAccess stops when it runs out\n";

$live = blueworx_external_invite(
	array( 'name' => 'Live', 'email' => 'live@example.com', 'days' => 30 )
);

check( 'a fresh invitation is not expired', blueworx_external_is_expired( $live ), false );

// Pushed into the past the way the console's own Extend does it, in reverse.
update_user_meta( $live, BLUEWORX_EXTERNAL_META_EXPIRES_AT, 1000000 - 1 );

check( 'one whose date has passed is', blueworx_external_is_expired( $live ), true );

echo "\nExtending it puts the clock forward from now\n";

blueworx_external_extend( $live, 7 );

check(
	'seven days from now, not from when it lapsed',
	blueworx_external_expires_at( $live ),
	blueworx_external_expiry_from( 1000000, 7 )
);
check( 'and it is live again', blueworx_external_is_expired( $live ), false );

echo "\nAn expired account cannot sign back in\n";

update_user_meta( $live, BLUEWORX_EXTERNAL_META_EXPIRES_AT, 1000000 - 1 );

$refused = blueworx_external_block_expired_login( get_userdata( $live ), 'live', 'whatever' );

check( 'authentication is refused', is_wp_error( $refused ), true );

blueworx_external_extend( $live, 30 );

$allowed = blueworx_external_block_expired_login( get_userdata( $live ), 'live', 'whatever' );

check( 'and a live one is not', is_wp_error( $allowed ), false );

echo "\nAn account that is not external is never touched by any of this\n";

$admin = new WP_User( 1, array( 'administrator' ) );

check(
	'an administrator authenticates normally',
	is_wp_error( blueworx_external_block_expired_login( $admin, 'luke', 'whatever' ) ),
	false
);
```

Add to the stubs, above the plugin `require`:

```php
class WP_Error {
	public $code;
	public $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
}
```

and replace the `is_wp_error()` stub inherited from `stubs.php` if it does not already test for `WP_Error`.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/php/external-access-test.php`
Expected: FAIL — `blueworx_external_is_expired()` is not defined.

- [ ] **Step 3: Write the expiry code**

Append to `includes/external-access.php`:

```php
/**
 * Whether an invitation has run out.
 *
 * An account with no expiry recorded is treated as expired rather than as
 * permanent. The only way to hold this role is to have been invited, and every
 * invitation writes a date; an account without one has been tampered with or
 * half-created, and the safe reading of a missing date is "no".
 *
 * @param int $user_id Invited account.
 * @return bool True when access has ended.
 */
function blueworx_external_is_expired( $user_id ) {
	$expires = blueworx_external_expires_at( $user_id );

	return $expires <= 0 || $expires <= (int) current_time( 'timestamp' );
}

/**
 * Puts the end date forward.
 *
 * Counted from now rather than from the existing date, so extending something
 * that lapsed last month gives the full period rather than a date still in the
 * past.
 *
 * @param int $user_id Invited account.
 * @param int $days    Whole days.
 * @return bool True when written.
 */
function blueworx_external_extend( $user_id, $days ) {
	$days = blueworx_external_sanitize_duration( $days );

	return (bool) update_user_meta(
		(int) $user_id,
		BLUEWORX_EXTERNAL_META_EXPIRES_AT,
		blueworx_external_expiry_from( (int) current_time( 'timestamp' ), $days )
	);
}

/**
 * Withdraws an invitation by deleting the account.
 *
 * Nothing is reassigned, because there is nothing to reassign: an external
 * account cannot write, so it cannot have authored anything.
 *
 * @param int $user_id Invited account.
 * @return bool True when the account was removed.
 */
function blueworx_external_revoke( $user_id ) {
	$user = get_userdata( (int) $user_id );

	if ( ! $user instanceof WP_User || ! blueworx_external_is_external_user( $user ) ) {
		return false;
	}

	if ( ! function_exists( 'wp_delete_user' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}

	return (bool) wp_delete_user( (int) $user_id );
}

/**
 * Refuses an expired account at the door.
 *
 * @param mixed  $user     User or error so far.
 * @param string $username Submitted username.
 * @param string $password Submitted password.
 * @return mixed The user, or a WP_Error.
 */
function blueworx_external_block_expired_login( $user, $username = '', $password = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Both are required by the "authenticate" filter signature; the decision is made from the resolved user alone.
	if ( ! $user instanceof WP_User || ! blueworx_external_is_external_user( $user ) ) {
		return $user;
	}

	if ( ! blueworx_external_is_expired( $user->ID ) ) {
		return $user;
	}

	return new WP_Error(
		'blueworx_external_expired',
		__( 'This access has ended. Ask whoever invited you to extend it.', 'blueworx-labs-wordpress' )
	);
}

/**
 * Ends a session that is already open when the invitation runs out.
 *
 * Refusing the login alone is not enough: somebody signed in on the last day of
 * their access would otherwise keep that session for as long as the cookie
 * lasted. Mirrors blueworx_support_enforce_window() and runs at the same point
 * for the same reasons.
 *
 * @return void
 */
function blueworx_external_enforce_expiry() {
	$user = wp_get_current_user();

	if ( ! blueworx_external_is_external_user( $user ) || ! blueworx_external_is_expired( $user->ID ) ) {
		return;
	}

	wp_destroy_current_session();
	wp_clear_auth_cookie();
	wp_set_current_user( 0 );

	wp_die(
		esc_html__( 'This access has ended. Ask whoever invited you to extend it.', 'blueworx-labs-wordpress' ),
		esc_html__( 'Access ended', 'blueworx-labs-wordpress' ),
		array( 'response' => 403 )
	);
}

/**
 * Notes when an invited person actually used their access.
 *
 * The console shows it so an administrator can tell a live demo from an
 * invitation nobody ever opened.
 *
 * @param string  $login Username.
 * @param mixed   $user  Signed-in user.
 * @return void
 */
function blueworx_external_record_sign_in( $login, $user = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $login is required by the "wp_login" action signature; the user object is what this needs.
	if ( ! blueworx_external_is_external_user( $user ) ) {
		return;
	}

	update_user_meta( $user->ID, BLUEWORX_EXTERNAL_META_LAST_SEEN, (int) current_time( 'timestamp' ) );
}
```

and extend the registration block at the end of the file:

```php
if ( blueworx_feature_enabled( 'external_access' ) ) {
	add_action( 'admin_init', 'blueworx_external_register_role', 1 );
	add_filter( 'authenticate', 'blueworx_external_block_expired_login', 30, 3 );
	// Priority 2, matching blueworx_support_enforce_window(): after the write
	// block at priority 0, so a session found stale here is destroyed before
	// anything else inspects it.
	add_action( 'init', 'blueworx_external_enforce_expiry', 2 );
	add_action( 'wp_login', 'blueworx_external_record_sign_in', 10, 2 );
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php tests/php/external-access-test.php`
Expected: PASS.

- [ ] **Step 5: Add the browser-test fixture**

Create `tests/fixtures/force-external-expiry.php`, modelled on `tests/fixtures/force-support-access-expiry.php`:

```php
<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Pushes an external invitation's end date into the past, so a browser test can
 * check what an expired viewer meets without waiting thirty days.
 *
 * Usage: php force-external-expiry.php /absolute/path/to/wp-load.php <user-login>
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$login   = isset( $argv[2] ) ? $argv[2] : '';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// See tests/fixtures/impostor-support-user.php for why the CLI context is
// declared before wp-load: Site Protection wp_die()s an anonymous request the
// moment WordPress finishes loading, and everything below would silently never
// run while the process still exited 0.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;

$user = get_user_by( 'login', $login );

if ( ! $user ) {
	fwrite( STDERR, 'No such user: ' . $login . "\n" );
	exit( 1 );
}

update_user_meta( $user->ID, '_blueworx_external_expires_at', time() - 60 );

echo "expired\n";
```

- [ ] **Step 6: Commit**

```bash
git add includes/external-access.php tests/php/external-access-test.php tests/fixtures/force-external-expiry.php
git commit -m "External access ends on its date, and ends any session already open

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: The External access console screen

**Files:**
- Modify: `includes/external-access.php` (append the panel renderer and the form handlers)
- Modify: `includes/admin-settings.php` (register the submenu page, after the Support access block at line 59-67)
- Modify: `includes/admin-pages.php` (the page renderer and the feature toggle handler, after `blueworx_render_support_page()`)

**Interfaces:**
- Consumes: `blueworx_external_invitations()`, `blueworx_external_invite()`, `blueworx_external_extend()`, `blueworx_external_revoke()`, `blueworx_external_send_invite()`, `blueworx_external_expires_at()`, `blueworx_external_is_expired()`, `blueworx_external_durations()`, and the design-system helpers `blueworx_ds_card`, `blueworx_ds_input`, `blueworx_ds_select`, `blueworx_ds_button`, `blueworx_ds_notice`, `blueworx_ds_badge`, `blueworx_ds_empty_state`, `blueworx_ds_allowed_html`, `blueworx_open_admin_page`, `blueworx_close_admin_page`.
- Produces:
  - `blueworx_external_handle_actions() : void` — `admin_init`.
  - `blueworx_external_render_panel() : void`
  - `blueworx_render_external_page() : void`
  - `blueworx_handle_external_feature_toggle() : void` — `admin_post_blueworx_toggle_external_feature`.
  - Page slug: `blueworx-external`.
  - `data-testid` attributes Task 7 depends on: `bw-external-feature`, `bw-external-name`, `bw-external-email`, `bw-external-note`, `bw-external-days`, `bw-external-invite`, `bw-external-row`, `bw-external-revoke`, `bw-external-extend`, `bw-external-resend`, `bw-external-empty`.

- [ ] **Step 1: Write the form handler**

Append to `includes/external-access.php`:

```php
/**
 * Message to show at the top of the panel after an action.
 *
 * @var array
 */
$GLOBALS['blueworx_external_notice'] = array();

/**
 * Records what to tell the administrator after a redirect.
 *
 * A transient rather than a query argument: the message can name an email
 * address, and addresses do not belong in a URL that ends up in a browser
 * history or a server log.
 *
 * @param string $tone  Notice tone.
 * @param string $title Notice title.
 * @param string $text  Notice body.
 * @return void
 */
function blueworx_external_set_notice( $tone, $title, $text = '' ) {
	set_transient(
		'blueworx_external_notice_' . get_current_user_id(),
		array(
			'tone'  => $tone,
			'title' => $title,
			'text'  => $text,
		),
		60
	);
}

/**
 * Reads and clears the pending message.
 *
 * @return array Notice arguments, or an empty array.
 */
function blueworx_external_take_notice() {
	$key    = 'blueworx_external_notice_' . get_current_user_id();
	$notice = get_transient( $key );

	delete_transient( $key );

	return is_array( $notice ) ? $notice : array();
}

/**
 * Handles the panel's form submissions.
 *
 * Every branch requires promote_users AND its own nonce. promote_users rather
 * than manage_options because what these forms do is create and withdraw
 * accounts, which is the capability WordPress already gates that on.
 *
 * @return void
 */
function blueworx_external_handle_actions() {
	if ( ! isset( $_POST['blueworx_external_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Each branch below verifies its own nonce before acting.
		return;
	}

	if ( ! current_user_can( 'promote_users' ) ) {
		return;
	}

	$action  = sanitize_key( wp_unslash( $_POST['blueworx_external_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below, per branch.
	$self    = admin_url( 'admin.php?page=blueworx-external' );
	$user_id = isset( $_POST['blueworx_external_user'] ) ? absint( $_POST['blueworx_external_user'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Same.

	if ( 'invite' === $action ) {
		check_admin_referer( 'blueworx_external_invite' );

		$result = blueworx_external_invite(
			array(
				'name'  => isset( $_POST['blueworx_external_name'] ) ? wp_unslash( $_POST['blueworx_external_name'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized inside blueworx_external_invite().
				'email' => isset( $_POST['blueworx_external_email'] ) ? wp_unslash( $_POST['blueworx_external_email'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Same.
				'note'  => isset( $_POST['blueworx_external_note'] ) ? wp_unslash( $_POST['blueworx_external_note'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Same.
				'days'  => isset( $_POST['blueworx_external_days'] ) ? wp_unslash( $_POST['blueworx_external_days'] ) : 0, // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reduced to an offered value by blueworx_external_sanitize_duration().
			)
		);

		if ( is_wp_error( $result ) ) {
			blueworx_external_set_notice(
				'error',
				__( 'Nobody was invited.', 'blueworx-labs-wordpress' ),
				$result->get_error_message()
			);
		} elseif ( ! blueworx_external_send_invite( $result ) ) {
			// The account is real and usable; only the email failed. Saying so
			// is the point — a demo site with broken mail must not look like it
			// worked.
			blueworx_external_set_notice(
				'warning',
				__( 'The account was created, but the email did not send.', 'blueworx-labs-wordpress' ),
				__( 'Check that this site can send email, then use Resend on their row.', 'blueworx-labs-wordpress' )
			);
		} else {
			blueworx_external_set_notice(
				'success',
				__( 'Invitation sent.', 'blueworx-labs-wordpress' ),
				__( 'They will get an email with a link to choose a password.', 'blueworx-labs-wordpress' )
			);
		}
	}

	if ( 'revoke' === $action && $user_id ) {
		check_admin_referer( 'blueworx_external_revoke_' . $user_id );

		blueworx_external_revoke( $user_id );
		blueworx_external_set_notice( 'success', __( 'Access withdrawn.', 'blueworx-labs-wordpress' ) );
	}

	if ( 'extend' === $action && $user_id ) {
		check_admin_referer( 'blueworx_external_extend_' . $user_id );

		blueworx_external_extend( $user_id, isset( $_POST['blueworx_external_days'] ) ? wp_unslash( $_POST['blueworx_external_days'] ) : 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reduced to an offered value by blueworx_external_sanitize_duration().
		blueworx_external_set_notice( 'success', __( 'Access extended.', 'blueworx-labs-wordpress' ) );
	}

	if ( 'resend' === $action && $user_id ) {
		check_admin_referer( 'blueworx_external_resend_' . $user_id );

		if ( blueworx_external_send_invite( $user_id ) ) {
			blueworx_external_set_notice( 'success', __( 'Invitation sent again.', 'blueworx-labs-wordpress' ) );
		} else {
			blueworx_external_set_notice(
				'error',
				__( 'That email did not send.', 'blueworx-labs-wordpress' ),
				__( 'This site could not hand the message to a mail server.', 'blueworx-labs-wordpress' )
			);
		}
	}

	wp_safe_redirect( $self );
	exit;
}
```

and register it in the feature block:

```php
	add_action( 'admin_init', 'blueworx_external_handle_actions' );
```

- [ ] **Step 2: Write the panel renderer**

Append to `includes/external-access.php`:

```php
/**
 * The state badge for one invitation.
 *
 * Three states rather than two: "ends soon" is the one an administrator needs
 * to see before it matters, and a list that only distinguishes live from dead
 * never shows it.
 *
 * @param int $user_id Invited account.
 * @return string Badge markup.
 */
function blueworx_external_state_badge( $user_id ) {
	$expires = blueworx_external_expires_at( $user_id );
	$now     = (int) current_time( 'timestamp' );

	if ( blueworx_external_is_expired( $user_id ) ) {
		return blueworx_ds_badge( __( 'Ended', 'blueworx-labs-wordpress' ), 'neutral', true );
	}

	if ( ( $expires - $now ) <= ( 3 * DAY_IN_SECONDS ) ) {
		return blueworx_ds_badge( __( 'Ends soon', 'blueworx-labs-wordpress' ), 'warning', true );
	}

	return blueworx_ds_badge( __( 'Active', 'blueworx-labs-wordpress' ), 'success', true );
}

/**
 * Renders the invite form and the list of people invited.
 *
 * @return void
 */
function blueworx_external_render_panel() {
	$notice = blueworx_external_take_notice();
	$self   = admin_url( 'admin.php?page=blueworx-external' );

	if ( ! empty( $notice ) ) {
		echo wp_kses( blueworx_ds_notice( $notice ), blueworx_ds_allowed_html() );
	}

	echo wp_kses(
		blueworx_ds_notice(
			array(
				'tone'  => 'info',
				'title' => __( 'What an external viewer can do', 'blueworx-labs-wordpress' ),
				'text'  => __( 'They see the back end the way an administrator does, and can change nothing: every save, delete and setting is refused. Customer and order screens stay hidden from them. One thing this cannot catch is a plugin that changes something in response to an ordinary page view rather than a save — rare, but worth knowing before you invite somebody into a live site.', 'blueworx-labs-wordpress' ),
			)
		),
		blueworx_ds_allowed_html()
	);

	$days = array();

	foreach ( blueworx_external_durations() as $option ) {
		$days[ (string) $option ] = sprintf(
			/* translators: %d: number of days. */
			_n( '%d day', '%d days', $option, 'blueworx-labs-wordpress' ),
			$option
		);
	}

	echo '<form method="post" action="' . esc_url( $self ) . '" class="bw-fields bw-fields--single">';
	wp_nonce_field( 'blueworx_external_invite' );
	echo '<input type="hidden" name="blueworx_external_action" value="invite" />';

	echo blueworx_ds_input( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helper escapes everything it emits.
		array(
			'name'  => 'blueworx_external_name',
			'label' => __( 'Their name', 'blueworx-labs-wordpress' ),
			'attrs' => array( 'data-testid' => 'bw-external-name' ),
		)
	);

	echo blueworx_ds_input( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same.
		array(
			'name'     => 'blueworx_external_email',
			'type'     => 'email',
			'label'    => __( 'Their email address', 'blueworx-labs-wordpress' ),
			'help'     => __( 'This is where the invitation goes, and it is how they sign in.', 'blueworx-labs-wordpress' ),
			'required' => true,
			'attrs'    => array( 'data-testid' => 'bw-external-email' ),
		)
	);

	echo blueworx_ds_input( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same.
		array(
			'name'  => 'blueworx_external_note',
			'label' => __( 'A note for you', 'blueworx-labs-wordpress' ),
			'help'  => __( 'Only you see this. Handy for remembering which conversation somebody came from.', 'blueworx-labs-wordpress' ),
			'attrs' => array( 'data-testid' => 'bw-external-note' ),
		)
	);

	echo blueworx_ds_select( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same.
		array(
			'name'    => 'blueworx_external_days',
			'label'   => __( 'How long they get', 'blueworx-labs-wordpress' ),
			'options' => $days,
			'value'   => (string) blueworx_external_default_duration(),
			'attrs'   => array( 'data-testid' => 'bw-external-days' ),
		)
	);

	echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same.
		array(
			'label'   => __( 'Send invitation', 'blueworx-labs-wordpress' ),
			'variant' => 'primary',
			'type'    => 'submit',
			'attrs'   => array( 'data-testid' => 'bw-external-invite' ),
		)
	);

	echo '</form>';

	$invitations = blueworx_external_invitations();

	if ( empty( $invitations ) ) {
		echo wp_kses(
			blueworx_ds_empty_state(
				array(
					'title' => __( 'Nobody has been invited yet', 'blueworx-labs-wordpress' ),
					'text'  => __( 'Whoever you invite appears here, with when their access ends.', 'blueworx-labs-wordpress' ),
					'attrs' => array( 'data-testid' => 'bw-external-empty' ),
				)
			),
			blueworx_ds_allowed_html()
		);

		return;
	}

	echo '<table class="widefat striped bw-external-table"><thead><tr>';
	echo '<th>' . esc_html__( 'Who', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th>' . esc_html__( 'Last seen', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th>' . esc_html__( 'Access ends', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th>' . esc_html__( 'Actions', 'blueworx-labs-wordpress' ) . '</th>';
	echo '</tr></thead><tbody>';

	$format = get_option( 'date_format' );

	foreach ( $invitations as $user ) {
		$note      = (string) get_user_meta( $user->ID, BLUEWORX_EXTERNAL_META_NOTE, true );
		$last_seen = (int) get_user_meta( $user->ID, BLUEWORX_EXTERNAL_META_LAST_SEEN, true );

		echo '<tr data-testid="bw-external-row" data-external-user="' . esc_attr( $user->ID ) . '">';

		echo '<td><strong>' . esc_html( $user->display_name ) . '</strong><br />';
		echo '<span class="bw-muted">' . esc_html( $user->user_email ) . '</span>';

		if ( '' !== $note ) {
			echo '<br /><span class="bw-muted">' . esc_html( $note ) . '</span>';
		}

		echo '</td>';

		echo '<td>' . esc_html(
			$last_seen > 0
				? date_i18n( $format, $last_seen )
				: __( 'Never', 'blueworx-labs-wordpress' )
		) . '</td>';

		echo '<td>' . esc_html( date_i18n( $format, blueworx_external_expires_at( $user->ID ) ) ) . ' ';
		echo wp_kses( blueworx_external_state_badge( $user->ID ), blueworx_ds_allowed_html() );
		echo '</td>';

		echo '<td>';

		// Three separate forms rather than one with several submits: each
		// carries its own nonce, tied to its own action and this one account, so
		// a nonce lifted from the page cannot be replayed against a different
		// person or a different action.
		echo '<form method="post" action="' . esc_url( $self ) . '" class="bw-external-rowform">';
		wp_nonce_field( 'blueworx_external_extend_' . $user->ID );
		echo '<input type="hidden" name="blueworx_external_action" value="extend" />';
		echo '<input type="hidden" name="blueworx_external_user" value="' . esc_attr( $user->ID ) . '" />';
		echo '<input type="hidden" name="blueworx_external_days" value="' . esc_attr( blueworx_external_default_duration() ) . '" />';
		echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helper escapes everything it emits.
			array(
				'label' => __( 'Extend', 'blueworx-labs-wordpress' ),
				'type'  => 'submit',
				'size'  => 'sm',
				'attrs' => array( 'data-testid' => 'bw-external-extend' ),
			)
		);
		echo '</form>';

		echo '<form method="post" action="' . esc_url( $self ) . '" class="bw-external-rowform">';
		wp_nonce_field( 'blueworx_external_resend_' . $user->ID );
		echo '<input type="hidden" name="blueworx_external_action" value="resend" />';
		echo '<input type="hidden" name="blueworx_external_user" value="' . esc_attr( $user->ID ) . '" />';
		echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same.
			array(
				'label' => __( 'Resend', 'blueworx-labs-wordpress' ),
				'type'  => 'submit',
				'size'  => 'sm',
				'attrs' => array( 'data-testid' => 'bw-external-resend' ),
			)
		);
		echo '</form>';

		echo '<form method="post" action="' . esc_url( $self ) . '" class="bw-external-rowform">';
		wp_nonce_field( 'blueworx_external_revoke_' . $user->ID );
		echo '<input type="hidden" name="blueworx_external_action" value="revoke" />';
		echo '<input type="hidden" name="blueworx_external_user" value="' . esc_attr( $user->ID ) . '" />';
		echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same.
			array(
				'label'   => __( 'Withdraw', 'blueworx-labs-wordpress' ),
				'variant' => 'danger',
				'type'    => 'submit',
				'size'    => 'sm',
				'attrs'   => array( 'data-testid' => 'bw-external-revoke' ),
			)
		);
		echo '</form>';

		echo '</td></tr>';
	}

	echo '</tbody></table>';
}
```

If `blueworx_ds_input`, `blueworx_ds_select` or `blueworx_ds_empty_state` do not accept an `attrs` key, read their signatures in `includes/admin-design.php` and pass the `data-testid` the way that helper already supports. Do not add an `attrs` parameter to a shared helper for this — match what is there.

- [ ] **Step 3: Register the page**

In `includes/admin-settings.php`, after the Support access `add_submenu_page()` block (currently lines 59-67), add:

```php
	// Registered under the BlueWorx menu while the function is on, and under no
	// parent while it is off — the same treatment single sign-on gets, and for
	// the same reason: the address keeps working so the screen can explain
	// itself, without listing a settings page for something that is not running.
	add_submenu_page(
		blueworx_feature_enabled( 'external_access' ) ? 'blueworx-labs-wordpress' : null,
		esc_html__( 'External access', 'blueworx-labs-wordpress' ),
		esc_html__( 'External access', 'blueworx-labs-wordpress' ),
		'promote_users',
		'blueworx-external',
		'blueworx_render_external_page'
	);
```

In `includes/admin-pages.php`, after `blueworx_render_support_page()`, add the toggle handler and the page, modelled directly on the support pair:

```php
/**
 * Saves the External access on/off switch.
 *
 * @return void
 */
function blueworx_handle_external_feature_toggle() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_toggle_external_feature' );

	$on = ! empty( $_POST['blueworx_external_feature'] );

	blueworx_set_feature_enabled( 'external_access', $on );

	if ( $on && function_exists( 'blueworx_external_register_role' ) ) {
		// Registered here as well as on admin_init so the role exists for the
		// very first invitation, which can be made before the next page load.
		blueworx_external_register_role();
	}

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-external' ) );
	exit;
}
add_action( 'admin_post_blueworx_toggle_external_feature', 'blueworx_handle_external_feature_toggle' );

/**
 * Renders the External access screen.
 *
 * @return void
 */
function blueworx_render_external_page() {
	$on    = blueworx_feature_enabled( 'external_access' );
	$count = $on ? count( blueworx_external_invitations() ) : 0;

	blueworx_open_admin_page(
		array(
			'title'   => __( 'External access', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'Invite somebody to look round the back end of this site without being able to change anything.', 'blueworx-labs-wordpress' ),
			'actions' => blueworx_ds_badge(
				$count > 0
					/* translators: %d: number of people invited. */
					? sprintf( _n( '%d person invited', '%d people invited', $count, 'blueworx-labs-wordpress' ), $count )
					: __( 'Nobody invited', 'blueworx-labs-wordpress' ),
				$count > 0 ? 'success' : 'neutral',
				true
			),
		)
	);

	$switch = sprintf(
		'<form method="post" action="%1$s">%2$s<input type="hidden" name="action" value="blueworx_toggle_external_feature" /><label class="bw-switch bw-switch--bare"><input type="checkbox" role="switch" name="blueworx_external_feature" value="1"%3$s data-testid="bw-external-feature" /><span class="bw-switch__track"><span class="bw-switch__thumb"></span></span><span class="screen-reader-text">%4$s</span></label>%5$s</form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		wp_nonce_field( 'blueworx_toggle_external_feature', '_wpnonce', true, false ),
		checked( $on, true, false ),
		esc_html__( 'External access is available on this site', 'blueworx-labs-wordpress' ),
		blueworx_ds_button(
			array(
				'label' => __( 'Save', 'blueworx-labs-wordpress' ),
				'type'  => 'submit',
				'size'  => 'sm',
			)
		)
	);

	echo '<section class="bw-card"><div class="bw-card__head"><div class="bw-card__titles">';
	echo '<p class="bw-card__eyebrow">' . esc_html__( 'They can look at everything and change nothing', 'blueworx-labs-wordpress' ) . '</p>';
	echo '<h2 class="bw-card__title">' . esc_html__( 'External viewer access', 'blueworx-labs-wordpress' ) . '</h2>';
	echo '</div><div class="bw-card__actions">';
	echo $switch; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from escaped parts.
	echo '</div></div><div class="bw-card__body">';

	if ( ! $on ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone'  => 'info',
					'title' => __( 'External access is switched off', 'blueworx-labs-wordpress' ),
					'text'  => __( 'Nobody can be invited while it is off, and anybody already invited cannot sign in. Switch it on above to invite somebody.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);

		echo '</div></section>';

		blueworx_close_admin_page();
		return;
	}

	blueworx_external_render_panel();

	echo '</div></section>';

	blueworx_close_admin_page();
}
```

- [ ] **Step 4: Look at it**

Bring the harness up on port 8882 and delete the duplicate plugin symlink it creates. Sign in, switch the feature on at BlueWorx → External access, invite an address you control, and confirm: the row appears with an Active badge, the notice says the invitation sent (or names the mail failure), and the empty state is gone.

- [ ] **Step 5: Commit**

```bash
git add includes/external-access.php includes/admin-settings.php includes/admin-pages.php
git commit -m "Add the External access screen: invite, extend, resend, withdraw

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: Site Protection, the migration and uninstall

**Files:**
- Modify: `includes/external-access.php` (append)
- Modify: `includes/upgrade.php` (new migration; bump `blueworx_get_labs_db_version()` from 10 to 11)
- Modify: `uninstall.php`

**Interfaces:**
- Consumes: `blueworx_get_site_protection_roles()` and the `blueworx_<area>_protection_roles` options from `includes/admin-settings.php`.
- Produces: `blueworx_external_allow_in_site_protection() : void`, `blueworx_migrate_external_access_default() : void`.

- [ ] **Step 1: Add the Site Protection default**

Append to `includes/external-access.php`:

```php
/**
 * Adds the external role to any Site Protection allow-list already in force.
 *
 * Site Protection only lets named roles view the site at all, so an invited
 * viewer on a protected site would meet a 403 before ever seeing the back end —
 * and nothing on screen would explain why. Adding the role when the feature is
 * switched on makes the invitation work.
 *
 * A default, not an override: the role is listed in the Site Protection pickers
 * like any other and can be taken out again. Nothing is done to a list that is
 * empty, because an empty list means that area is not restricted by role.
 *
 * External accounts deliberately get no blanket exemption of the kind the
 * BlueWorx support account holds. That exemption exists because a support
 * session is authenticated by a key outside the site's own roles; an external
 * viewer is an ordinary account and Site Protection should be able to exclude
 * it.
 *
 * @return void
 */
function blueworx_external_allow_in_site_protection() {
	foreach ( array( 'frontend', 'backend' ) as $area ) {
		$key   = 'blueworx_' . $area . '_protection_roles';
		$roles = get_option( $key, array() );

		if ( ! is_array( $roles ) || array() === $roles ) {
			continue;
		}

		if ( in_array( blueworx_external_role_slug(), $roles, true ) ) {
			continue;
		}

		$roles[] = blueworx_external_role_slug();

		update_option( $key, array_values( $roles ) );
	}
}
```

Call it from `blueworx_handle_external_feature_toggle()` in `includes/admin-pages.php`, immediately after `blueworx_external_register_role()`:

```php
		blueworx_external_allow_in_site_protection();
```

Confirm the option names against `blueworx_get_site_protection_roles()` in `includes/admin-settings.php` before writing this — it reads `'blueworx_' . $area . '_protection_roles'`, and the areas it is called with are the two above. If the areas are named differently, use the names that file uses.

- [ ] **Step 2: Add the migration**

In `includes/upgrade.php`, change `blueworx_get_labs_db_version()` to return `11`, and add:

```php
/**
 * Keeps External access switched off on a site that already runs this plugin.
 *
 * The feature is registered default-off, so this is belt and braces rather than
 * a correction: it writes the option explicitly, so no later change to the
 * registry's defaults can switch on a feature that creates near-administrator
 * accounts underneath somebody who never asked for it.
 *
 * @return void
 */
function blueworx_migrate_external_access_default() {
	if ( false === get_option( 'blueworx_feature_external_access', false ) ) {
		update_option( 'blueworx_feature_external_access', '0' );
	}
}
```

and in `blueworx_run_pending_labs_migrations()`, after the version-10 block:

```php
	if ( $stored_version < 11 ) {
		blueworx_migrate_external_access_default();
	}
```

Check the option-name convention used by `blueworx_feature_enabled()` in `includes/features.php` before writing this — if features are stored under a different key shape than `blueworx_feature_<key>`, use that shape.

- [ ] **Step 3: Clean up on uninstall**

In `uninstall.php`, beside the existing `blueworx_support_*` deletions:

```php
delete_option( 'blueworx_feature_external_access' );

// The role definition goes; the accounts do not. Deleting somebody's user
// account is not a decision an uninstall should take on its owner's behalf,
// and an account left without this role simply has no capabilities until an
// administrator gives it some or removes it.
remove_role( 'blueworx_external' );

delete_metadata( 'user', 0, '_blueworx_external_invited_by', '', true );
delete_metadata( 'user', 0, '_blueworx_external_invited_at', '', true );
delete_metadata( 'user', 0, '_blueworx_external_expires_at', '', true );
delete_metadata( 'user', 0, '_blueworx_external_note', '', true );
delete_metadata( 'user', 0, '_blueworx_external_last_seen', '', true );
```

The role slug and meta keys are written as literals here on purpose: `uninstall.php` runs without the plugin loaded, so the constants and functions that define them do not exist.

- [ ] **Step 4: Check the migration runs once**

On the harness, with the plugin active, run:

```bash
wp option get blueworx_labs_db_version
wp option get blueworx_feature_external_access
```

Expected: version 11, feature option `0`.

- [ ] **Step 5: Commit**

```bash
git add includes/external-access.php includes/admin-pages.php includes/upgrade.php uninstall.php
git commit -m "Let invited viewers past Site Protection, and clean up on uninstall

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 7: Browser tests

**Files:**
- Create: `tests/external-access.spec.js`
- Create: `tests/external-readonly.spec.js`
- Create: `tests/fixtures/external-viewer.php`

**Interfaces:**
- Consumes: the `data-testid` attributes listed in Task 5, `tests/helpers.js` (`test`, `expect`, `login`, `ADMIN_USER`, `ADMIN_PASS`, `isPlaceholder`), and `tests/fixtures/force-external-expiry.php` from Task 4.
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Write the fixture that makes a signable-in viewer**

An invited account has a random password nobody knows, so a browser test cannot sign in as one through the invite flow. Create `tests/fixtures/external-viewer.php`:

```php
<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Creates an external viewer with a KNOWN password, so a browser test can sign
 * in as one. The real invite flow deliberately sets a random password and mails
 * a reset link, which a test cannot follow — so the account is made here and the
 * flow itself is checked separately, by asserting on what the console shows.
 *
 * Usage: php external-viewer.php /absolute/path/to/wp-load.php create|delete
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$command = isset( $argv[2] ) ? $argv[2] : '';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// See tests/fixtures/impostor-support-user.php for why this comes before
// wp-load: Site Protection wp_die()s an anonymous request as soon as WordPress
// finishes loading, and everything below would silently never run.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;
require_once ABSPATH . 'wp-admin/includes/user.php';

$login = 'bw_external_test';
$pass  = 'ExternalTest!2026';
$user  = get_user_by( 'login', $login );

if ( 'delete' === $command ) {
	if ( $user ) {
		wp_delete_user( $user->ID );
	}

	echo "deleted\n";
	exit( 0 );
}

// The role has to exist before anybody can be put in it, and it is registered on
// admin_init — which this process never reaches.
if ( function_exists( 'blueworx_external_register_role' ) ) {
	blueworx_external_register_role();
}

if ( ! $user ) {
	$id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_pass'    => $pass,
			'user_email'   => 'external-test@example.invalid',
			'display_name' => 'External Test Viewer',
			'role'         => 'blueworx_external',
		)
	);
} else {
	$id = $user->ID;
	wp_set_password( $pass, $id );
	$refreshed = get_user_by( 'id', $id );
	$refreshed->set_role( 'blueworx_external' );
}

update_user_meta( $id, '_blueworx_external_invited_by', 1 );
update_user_meta( $id, '_blueworx_external_invited_at', time() );
update_user_meta( $id, '_blueworx_external_expires_at', time() + ( 30 * 86400 ) );
update_user_meta( $id, '_blueworx_external_note', 'Created by the test suite' );

echo "created\n";
```

- [ ] **Step 2: Write the console test**

Create `tests/external-access.spec.js`:

```js
// `test` comes from helpers.js (see feature-toggles.spec.js for why): it carries
// the fixture that opts out of core's wp-admin view transitions.
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

const PAGE = '/wp-admin/admin.php?page=blueworx-external';
const INVITEE = `bw-plan-test-${Date.now()}@example.invalid`;

async function openConsole(page) {
  await login(page);
  await page.goto(PAGE);
}

async function ensureFeatureOn(page) {
  const toggle = page.locator('[data-testid="bw-external-feature"]');

  if (!(await toggle.isChecked())) {
    await toggle.check();
    await page.getByRole('button', { name: 'Save' }).first().click();
    await page.waitForURL(/page=blueworx-external/);
  }
}

test.describe('External access console', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the screen explains itself while the feature is off', async ({ page }) => {
    await openConsole(page);

    const toggle = page.locator('[data-testid="bw-external-feature"]');

    if (await toggle.isChecked()) {
      await toggle.uncheck();
      await page.getByRole('button', { name: 'Save' }).first().click();
      await page.waitForURL(/page=blueworx-external/);
    }

    await expect(page.getByText('External access is switched off')).toBeVisible();
    await expect(page.locator('[data-testid="bw-external-invite"]')).toHaveCount(0);
  });

  test('inviting somebody puts them in the list with an end date', async ({ page }) => {
    await openConsole(page);
    await ensureFeatureOn(page);

    await page.locator('[data-testid="bw-external-name"]').fill('Plan Test Person');
    await page.locator('[data-testid="bw-external-email"]').fill(INVITEE);
    await page.locator('[data-testid="bw-external-note"]').fill('Created by the test suite');
    await page.locator('[data-testid="bw-external-invite"]').click();

    const row = page.locator('[data-testid="bw-external-row"]', { hasText: INVITEE });

    await expect(row).toHaveCount(1);
    // The badge says Active or Ends soon depending on the chosen length; what
    // matters here is that the row is not already Ended.
    await expect(row).not.toContainText('Ended');
  });

  test('the same address cannot be invited twice', async ({ page }) => {
    await openConsole(page);
    await ensureFeatureOn(page);

    await page.locator('[data-testid="bw-external-email"]').fill(INVITEE);
    await page.locator('[data-testid="bw-external-invite"]').click();

    await expect(page.getByText('Nobody was invited.')).toBeVisible();
  });

  test('withdrawing access removes the row', async ({ page }) => {
    await openConsole(page);
    await ensureFeatureOn(page);

    const row = page.locator('[data-testid="bw-external-row"]', { hasText: INVITEE });

    await row.locator('[data-testid="bw-external-revoke"]').click();

    await expect(
      page.locator('[data-testid="bw-external-row"]', { hasText: INVITEE })
    ).toHaveCount(0);
  });
});
```

- [ ] **Step 3: Write the read-only test**

Create `tests/external-readonly.spec.js`:

```js
import { execFileSync } from 'node:child_process';
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, baseURL } from './helpers.js';

const VIEWER = 'bw_external_test';
const VIEWER_PASS = 'ExternalTest!2026';

// The same shape support-access.spec.js uses to reach the harness's wp-load.php;
// copy the path resolution from there rather than inventing a second one.
function fixture(script, ...args) {
  return execFileSync('php', [`tests/fixtures/${script}`, process.env.WP_LOAD_PATH, ...args], {
    encoding: 'utf8',
  });
}

async function signInAsViewer(page) {
  // The custom login URL, not wp-login.php — includes/login-security.php blocks
  // the default path, and a test that used it would be testing that block.
  await page.goto('/');
  await page.goto(process.env.WP_LOGIN_PATH || '/bwlogin/');
  await page.fill('#user_login', VIEWER);
  await page.fill('#user_pass', VIEWER_PASS);
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/);
}

test.describe('An external viewer changes nothing', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test.beforeAll(() => {
    fixture('external-viewer.php', 'create');
  });

  test.afterAll(() => {
    fixture('external-viewer.php', 'delete');
  });

  test('the dashboard opens', async ({ page }) => {
    await signInAsViewer(page);

    await expect(page).toHaveURL(/wp-admin/);
  });

  test('a POST is refused', async ({ page, request }) => {
    await signInAsViewer(page);

    const cookies = await page.context().cookies();
    const jar = cookies.map((c) => `${c.name}=${c.value}`).join('; ');

    const response = await request.post(`${baseURL}/wp-admin/admin-post.php`, {
      headers: { cookie: jar },
      form: { action: 'blueworx_toggle_support_feature' },
      maxRedirects: 0,
    });

    expect(response.status()).toBe(403);
  });

  test('a REST write is refused', async ({ page, request }) => {
    await signInAsViewer(page);

    const cookies = await page.context().cookies();
    const jar = cookies.map((c) => `${c.name}=${c.value}`).join('; ');

    const response = await request.post(`${baseURL}/wp-json/wp/v2/posts`, {
      headers: { cookie: jar },
      data: { title: 'Should never exist' },
    });

    expect(response.status()).toBe(403);
  });

  test('the users screen is refused', async ({ page }) => {
    await signInAsViewer(page);
    await page.goto('/wp-admin/users.php');

    await expect(page.getByText('personal data')).toBeVisible();
  });

  test('there is no trash link on the posts list', async ({ page }) => {
    await signInAsViewer(page);
    await page.goto('/wp-admin/edit.php');

    await expect(page.locator('a.submitdelete')).toHaveCount(0);
  });

  test('an expired viewer cannot sign in', async ({ page }) => {
    fixture('force-external-expiry.php', VIEWER);

    await page.goto(process.env.WP_LOGIN_PATH || '/bwlogin/');
    await page.fill('#user_login', VIEWER);
    await page.fill('#user_pass', VIEWER_PASS);
    await page.click('#wp-submit');

    await expect(page.getByText('This access has ended')).toBeVisible();
  });
});
```

Before running, read `tests/support-access.spec.js` and copy its actual approach to `WP_LOAD_PATH`, the custom login path and `baseURL` — those three are environment-specific and the versions above are placeholders for whatever that file already does. If `helpers.js` does not export `baseURL`, take the base URL the way the other specs take it.

- [ ] **Step 4: Run the new tests**

```bash
npx playwright test tests/external-access.spec.js tests/external-readonly.spec.js
```

Expected: PASS. A failure in the read-only spec is a real finding — investigate it before adjusting the test.

- [ ] **Step 5: Run the whole suite**

```bash
npx playwright test
```

Expected: the known-good baseline plus the new tests, with no new failures. A failed run can leave features switched on; if the run fails, put the site back before re-running, or the next run's failures will look like new bugs.

- [ ] **Step 6: Commit**

```bash
git add tests/external-access.spec.js tests/external-readonly.spec.js tests/fixtures/external-viewer.php
git commit -m "Test that an external viewer can look and cannot touch

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 8: Version, changelog, docs and lint

**Files:**
- Modify: `blueworx-labs-wordpress.php` (header `Version:`), `package.json` (`version`), `readme.txt` (stable tag if it carries one), and any version constant in the main file
- Modify: `CHANGELOG.md`
- Modify: `docs/ase-parity-matrix.md` only if it lists roles

**Interfaces:** none.

- [ ] **Step 1: Bump the version**

1.76.2 → **1.77.0**. Minor, because this is a new feature. Change it everywhere it appears: the plugin header `Version:` at `blueworx-labs-wordpress.php:6`, the version constant in the same file, and `"version"` in `package.json`. Run `npm run version:check` and fix whatever it reports.

- [ ] **Step 2: Write the changelog entry**

At the top of `CHANGELOG.md`, in the style of the entries already there — what changed for the person using it, in their words:

```markdown
## [1.77.0] - 2026-09-02

### Added
- **Invite somebody to look round the back end without letting them change
  anything.** A new *External access* screen under BlueWorx takes a name and an
  email address and sends that person an invitation with a link to choose their
  own password. They see the site the way an administrator does — every screen,
  every setting — and nothing they click changes anything. Customer and order
  screens stay hidden from them. Off until you switch it on.
- **Access ends on a date you choose**, thirty days by default. The screen lists
  everyone invited, when they last signed in and when their access runs out, and
  you can extend it, send the invitation again, or withdraw it outright. When it
  runs out, anyone still signed in is signed out.

### Changed
- **Support access and external access now share one read-only guarantee.** The
  rule that refuses every change was written for BlueWorx support; both now use
  the same one, so anything tightened for one is tightened for both. Nothing
  about support access itself changes.
```

- [ ] **Step 3: Check the merge sanity script**

Run: `npm run check:merge`
It flags any edited line in `package.json` as a lost line even on a branch that never merged anything. If its only complaints are about `package.json` version lines you deliberately changed, that is the known false positive — say so rather than "acting" on it.

- [ ] **Step 4: Run the linter once**

Run: `npm run lint`
Then run `vendor/bin/phpcs` if it is installed, per `phpcs.xml.dist`.

Do **not** fix what they report yet. Do not loop. Collect the findings, present them to Luke in plain language at the end of the session, and wait for him to say which to action.

- [ ] **Step 5: Commit**

```bash
git add blueworx-labs-wordpress.php package.json readme.txt CHANGELOG.md
git commit -m "Release 1.77.0 with the External viewer role

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 6: Open the pull request**

```bash
git push -u origin add-external-viewer-role
gh pr create --title "Invite people to look round the back end without letting them change it" --body "$(cat <<'BODY'
Adds a **BlueWorx: External** role and an invite screen. You enter a name and
email, the person gets a link to set their own password, and they can see
everything an administrator sees while changing nothing. Access ends after 30
days by default; you can extend, resend or withdraw it.

Support access and external access now share one read-only implementation
rather than two, so a gap closed in one is closed in both. Support access
behaviour is unchanged and its tests were not edited.

**You need to decide:** the invite email needs the site to be able to send mail.
If the demo site's mail is not reliable, invitations will fail — the screen says
so rather than failing quietly, but it is worth checking before you send one.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
BODY
)"
```

---

## Self-Review

**Spec coverage:**

| Spec section | Task |
|---|---|
| Shared guard extraction, renames, filter compatibility | 1 |
| `blueworx_readonly_data_allowed` per-role split | 1 |
| `blueworx_support_is_support_user()` kept as wrapper | 1 |
| External role, slug, name, rebuild-on-enable, orphan protection | 2 |
| Feature registry entry, default `'0'` | 2 |
| Deactivation teardown | 2 |
| Invitation data model (five meta keys), no custom table | 2, 3 |
| Invite flow, duplicate email refusal, username derivation | 3 |
| Email content, reset link, custom-login-URL passthrough | 3 |
| Mail failure surfaced, resend | 3, 5 |
| Expiry enforcement at login and mid-session | 4 |
| Extend, revoke | 4, 5 |
| Console screen, page registration, GET-write gap disclosure | 5 |
| Site Protection default | 6 |
| Migration, version bump to 11 | 6 |
| Uninstall cleanup | 6 |
| PHP tests | 1, 2, 3, 4 |
| Playwright tests, support-access.spec.js untouched | 1, 7 |
| Version, changelog, lint-once | 8 |

No spec requirement is unclaimed.

**Placeholder scan:** Three steps deliberately say "check the existing file before writing this" — Task 5 Step 2 (design-system helper signatures), Task 6 Steps 1-2 (option-name conventions), Task 7 Step 3 (harness path resolution). These are not TODOs: the surrounding code is given in full, and the instruction is to match an existing convention rather than guess at it, with the file to read named in each case.

**Type consistency:** `blueworx_external_role_slug()`, `blueworx_readonly_user_has_role()`, `blueworx_readonly_current_user()`, `blueworx_readonly_data_allowed()`, the five `BLUEWORX_EXTERNAL_META_*` constants and the `data-testid` names are used identically across Tasks 1-7. `blueworx_external_invitations()` is written in Task 2 (needed by the deactivation hook) and used again in Tasks 5 and 6.
