# BlueWorx Support Access Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give BlueWorx a single per-site key that grants read-only wp-admin and REST access for remote troubleshooting, only inside a deliberately opened 24-hour window, with no password to rotate.

**Architecture:** A managed `blueworx_support` user and role are created when the key is generated and deleted when it is revoked. The key authenticates two entry points — a `determine_current_user` filter for REST and a query-arg login for the browser. Read-only is enforced at the request layer (`init` priority 0 rejects every non-`GET`/`HEAD` request for that user), not through capabilities, because WordPress gates screen *rendering* on the same capabilities it gates writes on. Personal-data screens and routes are denied wholesale unless a second, separate opt-in is active.

**Tech Stack:** WordPress plugin PHP (procedural, `blueworx_` prefix, WPCS), Playwright for tests. No new dependencies — `approved-deps.json` is not touched.

**Spec:** `docs/superpowers/specs/2026-07-27-admin-access-and-ui-design.md` §1

## Global Constraints

- PHP >= 8.0 (`Requires PHP` header); composer floor is 7.4 — write to 7.4-compatible syntax
- All functions prefixed `blueworx_support_`, in `includes/support-access.php`, loaded from `blueworx-labs-wordpress.php`
- WPCS: tabs for indent, Yoda conditions, `esc_*` on all output, `sanitize_*` on all input, docblock on every function
- Every feature is gated by `blueworx_feature_enabled( 'support_access' )`; absent option means enabled
- Text domain `blueworx-labs-wordpress` on every user-facing string
- Ship as version **1.38.0** — bump `blueworx-labs-wordpress.php` header, `BLUEWORX_LABS_VERSION`, `package.json`, `readme.txt` stable tag, and add a `CHANGELOG.md` entry. `npm run version:check` must pass.
- No new npm or composer dependency

## Test Harness

Every task's tests run against the **local** harness, never the staging URL in `.env`.

```bash
# Start (once per session; from the repo root)
node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up \
  --plugin . --slug blueworx-labs-wordpress --dir .wp-test --port 8892

# Run a spec
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8892 \
WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw WP_LOGIN_PATH=admin_login \
npx playwright test tests/support-access.spec.js

# Tear down
node ../bluegroup_core_foundation/scripts/wp-test-env.mjs down --dir .wp-test
```

The harness **symlinks** the repo into `wp-content/plugins`, so PHP, CSS and JS edits are live
immediately — no re-provision between tasks. Only a change to the plugin's activation hooks
needs the plugin deactivated and reactivated in wp-admin; nothing in this plan does that,
since the support account is provisioned on key generation rather than on activation.

Specs import `test` from `./helpers.js`, **never** from `@playwright/test` — see the long comment in `tests/helpers.js` explaining the headless view-transition freeze.

---

### Task 1: Options, key generation and verification

Pure data layer. No UI, no auth yet.

**Files:**
- Create: `includes/support-access.php`
- Modify: `blueworx-labs-wordpress.php` (add the `require_once`)
- Modify: `includes/features.php` (registry entry)
- Test: `tests/support-access.spec.js`

**Interfaces:**
- Consumes: `blueworx_feature_enabled()` from `includes/features.php`
- Produces:
  - `blueworx_support_generate_key(): string` — returns the raw key, stores only its hash
  - `blueworx_support_verify_key( string $raw ): bool`
  - `blueworx_support_revoke_key(): void`
  - `blueworx_support_has_key(): bool`
  - `blueworx_support_access_open(): bool`
  - `blueworx_support_data_open(): bool`
  - `blueworx_support_open_access( bool $with_data ): void`
  - `blueworx_support_close_access(): void`
  - Option names: `blueworx_support_key_hash`, `blueworx_support_access_until`, `blueworx_support_data_until`

- [ ] **Step 1: Write the failing test**

The data layer has no UI yet, so drive it through a temporary must-use probe. Create `tests/support-access.spec.js`:

```js
import { test, expect, baseURL, isPlaceholder, login } from './helpers.js';

test.describe('Support access — key lifecycle', () => {
  test.skip(isPlaceholder, 'No real site configured');

  test('feature is registered on the console', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await expect(page.getByText('BlueWorx support access')).toBeVisible();
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run the spec command above.
Expected: FAIL — the string "BlueWorx support access" is not on the console.

- [ ] **Step 3: Write minimal implementation**

Create `includes/support-access.php`:

```php
<?php
/**
 * BlueWorx Support Access — a key-gated, read-only access path for remote
 * troubleshooting.
 *
 * The key is stored only as a SHA-256 hash and is shown once, at generation.
 * Access is refused unless a deliberately opened window is still in effect, so
 * a leaked key is inert in the standing state of every site.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Length of the access window opened by the console toggle, in seconds.
 */
const BLUEWORX_SUPPORT_WINDOW = 86400;

/**
 * Whether a key currently exists for this site.
 *
 * @return bool True when a key hash is stored.
 */
function blueworx_support_has_key() {
	return '' !== (string) get_option( 'blueworx_support_key_hash', '' );
}

/**
 * Generates a new key, replacing any existing one.
 *
 * Only the hash is persisted; the raw key is returned to the caller once and
 * never stored, so it cannot be recovered from the database.
 *
 * @return string Raw key.
 */
function blueworx_support_generate_key() {
	$raw = bin2hex( random_bytes( 32 ) );

	update_option( 'blueworx_support_key_hash', hash( 'sha256', $raw ) );

	return $raw;
}

/**
 * Verifies a presented key against the stored hash.
 *
 * Uses hash_equals so a wrong key cannot be discovered by timing the response.
 *
 * @param string $raw Presented key.
 * @return bool True when the key matches.
 */
function blueworx_support_verify_key( $raw ) {
	$stored = (string) get_option( 'blueworx_support_key_hash', '' );

	if ( '' === $stored || '' === (string) $raw ) {
		return false;
	}

	return hash_equals( $stored, hash( 'sha256', (string) $raw ) );
}

/**
 * Removes the key and closes any open window.
 *
 * @return void
 */
function blueworx_support_revoke_key() {
	delete_option( 'blueworx_support_key_hash' );
	blueworx_support_close_access();
}

/**
 * Whether the access window is currently open.
 *
 * @return bool True when access is permitted right now.
 */
function blueworx_support_access_open() {
	return time() < (int) get_option( 'blueworx_support_access_until', 0 );
}

/**
 * Whether personal-data access is currently permitted.
 *
 * Never true when the access window itself is shut.
 *
 * @return bool True when data screens and routes are permitted.
 */
function blueworx_support_data_open() {
	if ( ! blueworx_support_access_open() ) {
		return false;
	}

	return time() < (int) get_option( 'blueworx_support_data_until', 0 );
}

/**
 * Opens the access window for BLUEWORX_SUPPORT_WINDOW seconds.
 *
 * The window lapses on its own; there is no scheduled task that could fail to
 * run and leave access standing open.
 *
 * @param bool $with_data Whether to also permit personal-data access.
 * @return void
 */
function blueworx_support_open_access( $with_data ) {
	$until = time() + BLUEWORX_SUPPORT_WINDOW;

	update_option( 'blueworx_support_access_until', $until );
	update_option( 'blueworx_support_data_until', $with_data ? $until : 0 );
}

/**
 * Closes the access window immediately.
 *
 * @return void
 */
function blueworx_support_close_access() {
	update_option( 'blueworx_support_access_until', 0 );
	update_option( 'blueworx_support_data_until', 0 );
}
```

Add to `blueworx-labs-wordpress.php`, after the `profile-cleanup.php` require:

```php
require_once BLUEWORX_LABS_PATH . 'includes/support-access.php';
```

Add to `blueworx_get_feature_definitions()` in `includes/features.php`, immediately after the `client_roles` entry:

```php
'support_access'        => array(
	'label'       => __( 'BlueWorx support access', 'blueworx-labs-wordpress' ),
	'description' => __( 'Lets BlueWorx open a read-only support session with one key, for a 24-hour window you control. No access is possible until you generate a key and switch the window on.', 'blueworx-labs-wordpress' ),
	'section'     => 'security',
	'detail'      => 'support_access',
),
```

- [ ] **Step 4: Run test to verify it passes**

Run the spec command.
Expected: PASS — the feature label renders on the console.

- [ ] **Step 5: Commit**

```bash
git add includes/support-access.php blueworx-labs-wordpress.php includes/features.php tests/support-access.spec.js
git commit -m "feat: support access key storage and feature registration"
```

---

### Task 2: Managed user and role provisioning

**Files:**
- Modify: `includes/support-access.php`
- Test: `tests/support-access.spec.js`

**Interfaces:**
- Consumes: `blueworx_support_has_key()`, `blueworx_support_generate_key()` from Task 1
- Produces:
  - `blueworx_support_role_slug(): string` — returns `'blueworx_support'`
  - `blueworx_support_ensure_account(): int` — provisions role + user, returns user ID
  - `blueworx_support_remove_account(): void`
  - `blueworx_support_get_user(): WP_User|null`
  - `blueworx_support_is_support_user(): bool`

- [ ] **Step 1: Write the regression guard**

This is deliberately **not** a red-then-green test. It pins the property that a site with no
key carries no support account — which is the whole reason provisioning moved off activation.
It passes now and must still pass after Task 3 wires provisioning to key generation, where it
would otherwise be easy to leave the account behind on revoke.

Append to `tests/support-access.spec.js`:

```js
test('no support account exists before a key is generated', async ({ page }) => {
  await login(page);
  await page.goto('/wp-admin/users.php');
  await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
});
```

- [ ] **Step 2: Run it to confirm it passes**

Expected: PASS. If it fails, an earlier run left a support account behind — delete it before
continuing, or every later assertion in this spec is testing dirty state.

- [ ] **Step 3: Write minimal implementation**

Append to `includes/support-access.php`:

```php
/**
 * Gets the support role slug.
 *
 * @return string Role slug.
 */
function blueworx_support_role_slug() {
	return 'blueworx_support';
}

/**
 * Capabilities removed from the administrator clone.
 *
 * These are the operations that are destructive or that grant onward access;
 * everything else is retained so admin screens still render, because WordPress
 * gates screen rendering on the same capabilities it gates writes on. The
 * read-only guarantee comes from the request-layer block, not from this list.
 *
 * @return array Capability names.
 */
function blueworx_support_removed_caps() {
	return array(
		'edit_files',
		'edit_plugins',
		'edit_themes',
		// Raw script saved into content executes later in a real administrator's
		// browser — onward access by another route.
		'unfiltered_html',
		'install_plugins',
		'install_themes',
		'update_plugins',
		'update_themes',
		'update_core',
		'delete_plugins',
		'delete_themes',
		'export',
		'import',
		'create_users',
		'edit_users',
		'delete_users',
		'promote_users',
		'remove_users',
	);
}

/**
 * Builds the support role's capability map from the live administrator role.
 *
 * @return array Capability map (cap => true).
 */
function blueworx_support_build_caps() {
	$base = get_role( 'administrator' );
	$caps = ( $base && is_array( $base->capabilities ) ) ? $base->capabilities : array();

	foreach ( blueworx_support_removed_caps() as $cap ) {
		unset( $caps[ $cap ] );
	}

	$caps['read'] = true;

	return $caps;
}

/**
 * Provisions the support role and user.
 *
 * Called only when a key is generated: a site that never uses support access
 * never carries a dormant account. The user's password is set to a value no
 * input can hash to, so the account cannot be signed into with a password at
 * all — the key is the only way in.
 *
 * @return int User ID, or 0 on failure.
 */
function blueworx_support_ensure_account() {
	global $wpdb;

	remove_role( blueworx_support_role_slug() );
	add_role(
		blueworx_support_role_slug(),
		__( 'BlueWorx Support (read-only)', 'blueworx-labs-wordpress' ),
		blueworx_support_build_caps()
	);

	$user = get_user_by( 'login', 'blueworx_support' );

	if ( $user instanceof WP_User ) {
		$user->set_role( blueworx_support_role_slug() );
		$user_id = (int) $user->ID;
	} else {
		$user_id = wp_insert_user(
			array(
				'user_login'   => 'blueworx_support',
				'user_pass'    => wp_generate_password( 64, true, true ),
				'user_email'   => 'support+' . wp_generate_password( 8, false ) . '@blueworx.invalid',
				'display_name' => __( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
				'role'         => blueworx_support_role_slug(),
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		$user_id = (int) $user_id;
	}

	// Make the password unusable. '!' is not a valid hash, so wp_check_password()
	// can never match it, whatever is submitted. This is why there is no
	// credential to leak, phish or rotate.
	$wpdb->update( $wpdb->users, array( 'user_pass' => '!' ), array( 'ID' => $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	clean_user_cache( $user_id );

	return $user_id;
}

/**
 * Deletes the support user and role.
 *
 * @return void
 */
function blueworx_support_remove_account() {
	$user = get_user_by( 'login', 'blueworx_support' );

	if ( $user instanceof WP_User ) {
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		wp_delete_user( $user->ID );
	}

	remove_role( blueworx_support_role_slug() );
}

/**
 * Gets the support user.
 *
 * @return WP_User|null Support user, or null when it does not exist.
 */
function blueworx_support_get_user() {
	$user = get_user_by( 'login', 'blueworx_support' );

	return $user instanceof WP_User ? $user : null;
}

/**
 * Whether the current request is running as the support user.
 *
 * @return bool True for the support account.
 */
function blueworx_support_is_support_user() {
	$user = wp_get_current_user();

	return $user instanceof WP_User
		&& $user->exists()
		&& 'blueworx_support' === $user->user_login;
}
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS — still no account, because nothing calls `blueworx_support_ensure_account()` yet.

- [ ] **Step 5: Commit**

```bash
git add includes/support-access.php tests/support-access.spec.js
git commit -m "feat: provision the read-only support role and passwordless account"
```

---

### Task 3: Console panel — generate, show once, revoke, toggle

**Files:**
- Modify: `includes/admin-settings.php`
- Modify: `includes/support-access.php`
- Test: `tests/support-access.spec.js`

**Interfaces:**
- Consumes: everything from Tasks 1–2
- Produces:
  - `blueworx_support_render_panel(): void` — renders the console section
  - `blueworx_support_handle_actions(): void` — hooked on `admin_init`, processes the panel's POSTs
  - Form actions: `blueworx_support_generate`, `blueworx_support_revoke`, `blueworx_support_toggle`
  - Nonce action: `blueworx_support_panel`

Read `includes/admin-settings.php` first and follow its existing section-rendering and nonce conventions rather than inventing new ones.

- [ ] **Step 1: Write the failing test**

Append to `tests/support-access.spec.js`:

```js
test('generating a key shows it once and creates the account', async ({ page }) => {
  await login(page);
  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');

  await page.getByRole('button', { name: 'Generate key' }).click();

  const key = await page.locator('[data-testid="bw-support-key"]').innerText();
  expect(key.trim()).toMatch(/^[0-9a-f]{64}$/);

  // Shown once: a reload must not render it again.
  await page.reload();
  await expect(page.locator('[data-testid="bw-support-key"]')).toHaveCount(0);

  await page.goto('/wp-admin/users.php');
  await expect(page.locator('#the-list')).toContainText('blueworx_support');

  // Restore: revoking must remove the account again.
  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Revoke key' }).click();
  await page.goto('/wp-admin/users.php');
  await expect(page.locator('#the-list')).not.toContainText('blueworx_support');
});
```

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — no "Generate key" button exists.

- [ ] **Step 3: Write minimal implementation**

Append to `includes/support-access.php`. The raw key is passed to the render in a request-scoped global, never persisted:

```php
/**
 * Raw key to display once, for the request that generated it.
 *
 * @var string
 */
$GLOBALS['blueworx_support_new_key'] = '';

/**
 * Processes the support panel's form submissions.
 *
 * @return void
 */
function blueworx_support_handle_actions() {
	if ( ! blueworx_feature_enabled( 'support_access' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$action = isset( $_POST['blueworx_support_action'] )
		? sanitize_key( wp_unslash( $_POST['blueworx_support_action'] ) )
		: '';

	if ( '' === $action ) {
		return;
	}

	check_admin_referer( 'blueworx_support_panel' );

	if ( 'generate' === $action ) {
		$GLOBALS['blueworx_support_new_key'] = blueworx_support_generate_key();
		blueworx_support_ensure_account();
		blueworx_support_log_event( 'key_generated' );
		return;
	}

	if ( 'revoke' === $action ) {
		blueworx_support_revoke_key();
		blueworx_support_remove_account();
		blueworx_support_log_event( 'key_revoked' );
		return;
	}

	if ( 'toggle' === $action ) {
		if ( blueworx_support_access_open() ) {
			blueworx_support_close_access();
			blueworx_support_log_event( 'access_closed' );
		} else {
			$with_data = ! empty( $_POST['blueworx_support_with_data'] );
			blueworx_support_open_access( $with_data );
			blueworx_support_log_event( $with_data ? 'data_opened' : 'access_opened' );
		}
	}
}
add_action( 'admin_init', 'blueworx_support_handle_actions' );
```

`blueworx_support_log_event()` lands in Task 7. Until then, add this stub immediately so the calls above do not fatal — Task 7 replaces the body:

```php
/**
 * Records an audit event. Body implemented in Task 7.
 *
 * @param string $type Event type.
 * @return void
 */
function blueworx_support_log_event( $type ) {
	unset( $type );
}
```

Render the panel from `includes/admin-settings.php`, in the `support_access` detail branch (match the file's existing detail-panel pattern). Markup:

```php
<?php if ( '' !== $GLOBALS['blueworx_support_new_key'] ) : ?>
	<p><strong><?php esc_html_e( 'Copy this key now — it is not shown again.', 'blueworx-labs-wordpress' ); ?></strong></p>
	<code data-testid="bw-support-key"><?php echo esc_html( $GLOBALS['blueworx_support_new_key'] ); ?></code>
<?php endif; ?>

<form method="post">
	<?php wp_nonce_field( 'blueworx_support_panel' ); ?>
	<?php if ( blueworx_support_has_key() ) : ?>
		<label>
			<input type="checkbox" name="blueworx_support_with_data" value="1" />
			<?php esc_html_e( 'Also allow access to personal data for this session', 'blueworx-labs-wordpress' ); ?>
		</label>
		<button type="submit" name="blueworx_support_action" value="toggle" class="button">
			<?php echo blueworx_support_access_open()
				? esc_html__( 'Close support access', 'blueworx-labs-wordpress' )
				: esc_html__( 'Allow support access for 24 hours', 'blueworx-labs-wordpress' ); ?>
		</button>
		<button type="submit" name="blueworx_support_action" value="revoke" class="button">
			<?php esc_html_e( 'Revoke key', 'blueworx-labs-wordpress' ); ?>
		</button>
	<?php else : ?>
		<button type="submit" name="blueworx_support_action" value="generate" class="button button-primary">
			<?php esc_html_e( 'Generate key', 'blueworx-labs-wordpress' ); ?>
		</button>
	<?php endif; ?>
</form>
```

Include this honest limitation notice in the panel — it is a spec requirement, not optional copy:

```php
<p class="description">
	<?php esc_html_e( 'Read-only is enforced by rejecting every write request from this account. A plugin that writes data in response to a plain page load is not caught by that rule, so only open this window when you have asked BlueWorx to look at something.', 'blueworx-labs-wordpress' ); ?>
</p>
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/support-access.php includes/admin-settings.php tests/support-access.spec.js
git commit -m "feat: support access console panel with one-time key display"
```

---

### Task 4: Browser login entry point

**Files:**
- Modify: `includes/support-access.php`
- Test: `tests/support-access.spec.js`

**Interfaces:**
- Consumes: `blueworx_support_verify_key()`, `blueworx_support_access_open()`, `blueworx_support_get_user()`
- Produces: `blueworx_support_handle_login(): void` — hooked on `init`, priority 1

- [ ] **Step 1: Write the failing test**

```js
test('browser key login is refused while the window is shut', async ({ page, context }) => {
  await login(page);
  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Generate key' }).click();
  const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();

  // A separate, logged-out context: the admin cookie must not mask the result.
  const fresh = await context.browser().newContext();
  const anon = await fresh.newPage();

  const closed = await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
  expect(closed.status()).toBe(403);

  // Open the window, then the same key works.
  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

  await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
  await expect(anon.locator('body.wp-admin')).toHaveCount(1);

  await fresh.close();

  // Restore.
  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Revoke key' }).click();
});
```

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — the query arg does nothing, so the first navigation returns 200, not 403.

- [ ] **Step 3: Write minimal implementation**

```php
/**
 * Signs the support user in from a key in the query string.
 *
 * Sets a session cookie only (no "remember me"), so the browser never keeps a
 * long-lived credential for this account.
 *
 * @return void
 */
function blueworx_support_handle_login() {
	if ( ! isset( $_GET['blueworx_support_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$key = sanitize_text_field( wp_unslash( $_GET['blueworx_support_login'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! blueworx_feature_enabled( 'support_access' )
		|| ! blueworx_support_access_open()
		|| ! blueworx_support_verify_key( $key )
	) {
		blueworx_support_log_event( 'login_refused' );
		wp_die(
			esc_html__( 'Support access is not available.', 'blueworx-labs-wordpress' ),
			esc_html__( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
			array( 'response' => 403 )
		);
	}

	$user = blueworx_support_get_user();

	if ( ! $user instanceof WP_User ) {
		wp_die(
			esc_html__( 'Support access is not available.', 'blueworx-labs-wordpress' ),
			esc_html__( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
			array( 'response' => 403 )
		);
	}

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, false );
	blueworx_support_log_event( 'login' );

	wp_safe_redirect( admin_url() );
	exit;
}
add_action( 'init', 'blueworx_support_handle_login', 1 );
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/support-access.php tests/support-access.spec.js
git commit -m "feat: browser key login for the support account"
```

---

### Task 4b: Throttle failed key attempts

Spec §1.3 requires rate limiting. `includes/rest/rate-limit.php` cannot serve here — it is
written for REST requests, and the browser login path is a plain front-end query arg. This task
adds throttling that covers **both** entry points.

**Files:**
- Modify: `includes/support-access.php`
- Test: `tests/support-access.spec.js`

**Interfaces:**
- Consumes: `blueworx_support_log_event()` (stubbed in Task 3, real in Task 8)
- Produces:
  - `blueworx_support_throttle_key(): string` — transient key for the caller's IP
  - `blueworx_support_is_throttled(): bool`
  - `blueworx_support_record_failure(): void`
  - `blueworx_support_clear_failures(): void`
  - Constants `BLUEWORX_SUPPORT_MAX_FAILURES` (5) and `BLUEWORX_SUPPORT_LOCKOUT` (900)

Wire these into **both** `blueworx_support_handle_login()` (Task 4) and
`blueworx_support_rest_auth()` (Task 6). Task 6 is written after this one, so its
implementation must include the throttle check — do not leave it for later.

- [ ] **Step 1: Write the failing test**

```js
test('repeated bad keys are locked out', async ({ page, request }) => {
  await login(page);
  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Generate key' }).click();
  const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
  await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

  const bad = 'f'.repeat(64);

  for (let i = 0; i < 5; i += 1) {
    await request.get(`${baseURL}/?blueworx_support_login=${bad}`);
  }

  // The real key is now refused too: the lockout is on the caller, not the key.
  const locked = await request.get(`${baseURL}/?blueworx_support_login=${key}`);
  expect(locked.status()).toBe(429);

  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Revoke key' }).click();
});
```

Note the assertion: a correct key presented from a locked-out caller is **still** refused.
Throttling that lets the right key through defeats itself, because the attacker's final guess
is by definition the right one.

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — the sixth request returns 302 (a successful login), not 429.

- [ ] **Step 3: Write minimal implementation**

```php
/**
 * Failed key attempts allowed before a caller is locked out.
 */
const BLUEWORX_SUPPORT_MAX_FAILURES = 5;

/**
 * Lockout duration in seconds.
 */
const BLUEWORX_SUPPORT_LOCKOUT = 900;

/**
 * Gets the throttle transient key for the calling address.
 *
 * The address is hashed, not stored raw: the throttle must not turn into an
 * incidental log of who tried.
 *
 * @return string Transient key.
 */
function blueworx_support_throttle_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] )
		? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
		: 'unknown';

	return 'blueworx_support_fail_' . md5( $ip );
}

/**
 * Whether the calling address is currently locked out.
 *
 * @return bool True when further attempts must be refused.
 */
function blueworx_support_is_throttled() {
	return (int) get_transient( blueworx_support_throttle_key() ) >= BLUEWORX_SUPPORT_MAX_FAILURES;
}

/**
 * Records a failed key attempt.
 *
 * @return void
 */
function blueworx_support_record_failure() {
	$key   = blueworx_support_throttle_key();
	$count = (int) get_transient( $key ) + 1;

	set_transient( $key, $count, BLUEWORX_SUPPORT_LOCKOUT );
}

/**
 * Clears the failure counter after a successful authentication.
 *
 * @return void
 */
function blueworx_support_clear_failures() {
	delete_transient( blueworx_support_throttle_key() );
}
```

In `blueworx_support_handle_login()` (Task 4), immediately after the key is read and **before**
any verification:

```php
	if ( blueworx_support_is_throttled() ) {
		blueworx_support_log_event( 'login_throttled' );
		wp_die(
			esc_html__( 'Too many attempts. Try again later.', 'blueworx-labs-wordpress' ),
			esc_html__( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
			array( 'response' => 429 )
		);
	}
```

In the same function, call `blueworx_support_record_failure()` on the refusal branch and
`blueworx_support_clear_failures()` immediately before `wp_set_auth_cookie()`.

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/support-access.php tests/support-access.spec.js
git commit -m "feat: throttle failed support key attempts"
```

---

### Task 5: Hard request-layer write block

The load-bearing control. Everything before this is plumbing.

**Files:**
- Modify: `includes/support-access.php`
- Test: `tests/support-access.spec.js`

**Interfaces:**
- Consumes: `blueworx_support_is_support_user()`
- Produces:
  - `blueworx_support_block_writes(): void` — hooked on `init` priority 0
  - `blueworx_support_block_rest_writes( $result, $server, $request )` — hooked on `rest_pre_dispatch`

`init` priority 0 is chosen because it fires for **every** entry point — wp-admin screens, `admin-ajax.php`, `admin-post.php`, front-end requests and REST — so one rule covers them all. `rest_pre_dispatch` is a second net for defence in depth.

- [ ] **Step 1: Write the failing test**

```js
test('the support account cannot write', async ({ page, context }) => {
  await login(page);
  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Generate key' }).click();
  const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
  await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

  const fresh = await context.browser().newContext();
  const anon = await fresh.newPage();
  await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
  await expect(anon.locator('body.wp-admin')).toHaveCount(1);

  // Reading is fine.
  const read = await anon.goto(`${baseURL}/wp-admin/options-general.php`);
  expect(read.status()).toBe(200);

  // Every write path is refused.
  for (const path of ['/wp-admin/options.php', '/wp-admin/admin-ajax.php', '/wp-admin/admin-post.php']) {
    const posted = await anon.request.post(`${baseURL}${path}`, { data: { probe: '1' } });
    expect(posted.status(), `POST ${path}`).toBe(403);
  }

  const rest = await anon.request.post(`${baseURL}/wp-json/wp/v2/posts`, { data: { title: 'nope' } });
  expect(rest.status()).toBe(403);

  await fresh.close();

  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Revoke key' }).click();
});
```

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — the POSTs return 200/302/400, not 403.

- [ ] **Step 3: Write minimal implementation**

```php
/**
 * Rejects every non-read request made by the support account.
 *
 * This — not the capability set — is what makes the account read-only.
 * Third-party plugins routinely write through their own AJAX and REST endpoints
 * without checking a meaningful capability, so a rule that depends on plugin
 * authors behaving correctly is not a safety model. A method-level block does
 * not.
 *
 * Known gap, documented in the console: a plugin that writes in response to a
 * GET request is not caught here.
 *
 * @return void
 */
function blueworx_support_block_writes() {
	if ( ! blueworx_support_is_support_user() ) {
		return;
	}

	$method = isset( $_SERVER['REQUEST_METHOD'] )
		? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
		: 'GET';

	if ( in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		return;
	}

	blueworx_support_log_event( 'blocked_write' );

	wp_die(
		esc_html__( 'BlueWorx support access is read-only.', 'blueworx-labs-wordpress' ),
		esc_html__( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
		array( 'response' => 403 )
	);
}
add_action( 'init', 'blueworx_support_block_writes', 0 );

/**
 * Second net: refuses non-read REST requests from the support account.
 *
 * @param mixed           $result  Pre-dispatch result.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Current request.
 * @return mixed Untouched result, or a WP_Error for a write.
 */
function blueworx_support_block_rest_writes( $result, $server, $request ) {
	unset( $server );

	if ( ! blueworx_support_is_support_user() ) {
		return $result;
	}

	if ( in_array( strtoupper( $request->get_method() ), array( 'GET', 'HEAD' ), true ) ) {
		return $result;
	}

	blueworx_support_log_event( 'blocked_write' );

	return new WP_Error(
		'blueworx_support_read_only',
		__( 'BlueWorx support access is read-only.', 'blueworx-labs-wordpress' ),
		array( 'status' => 403 )
	);
}
add_filter( 'rest_pre_dispatch', 'blueworx_support_block_rest_writes', 10, 3 );
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS, all four write paths 403.

- [ ] **Step 5: Commit**

```bash
git add includes/support-access.php tests/support-access.spec.js
git commit -m "feat: reject every write request from the support account"
```

---

### Task 6: REST key authentication

**Files:**
- Modify: `includes/support-access.php`
- Test: `tests/support-access.spec.js`

**Interfaces:**
- Consumes: `blueworx_support_verify_key()`, `blueworx_support_access_open()`, `blueworx_support_get_user()`
- Produces: `blueworx_support_rest_auth( $user_id )` — hooked on `determine_current_user`, priority 20

Priority 20 places it after the existing JWT resolver in `includes/rest/bootstrap.php`, and it returns `$user_id` untouched when anything fails, matching that file's established "never error, just stay anonymous" pattern.

- [ ] **Step 1: Write the failing test**

```js
test('REST key header reads while open, is ignored while shut', async ({ page, request }) => {
  await login(page);
  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Generate key' }).click();
  const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();

  const headers = { 'X-BlueWorx-Support-Key': key };

  // Shut: settings route is unauthorised.
  const shut = await request.get(`${baseURL}/wp-json/wp/v2/settings`, { headers });
  expect(shut.status()).toBe(401);

  await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

  const open = await request.get(`${baseURL}/wp-json/wp/v2/settings`, { headers });
  expect(open.status()).toBe(200);

  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Revoke key' }).click();
});
```

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — the open case returns 401 because the header means nothing yet.

- [ ] **Step 3: Write minimal implementation**

```php
/**
 * Resolves the support user from the access-key header.
 *
 * Any failure leaves the request as it was, so public routes and cookie or JWT
 * authentication keep working.
 *
 * @param int|false $user_id User ID resolved so far.
 * @return int|false Resolved user ID.
 */
function blueworx_support_rest_auth( $user_id ) {
	if ( ! empty( $user_id ) ) {
		return $user_id;
	}

	if ( empty( $_SERVER['HTTP_X_BLUEWORX_SUPPORT_KEY'] ) ) {
		return $user_id;
	}

	if ( ! blueworx_feature_enabled( 'support_access' ) || ! blueworx_support_access_open() ) {
		return $user_id;
	}

	$key = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_BLUEWORX_SUPPORT_KEY'] ) );

	if ( ! blueworx_support_verify_key( $key ) ) {
		return $user_id;
	}

	$user = blueworx_support_get_user();

	return $user instanceof WP_User ? (int) $user->ID : $user_id;
}
add_filter( 'determine_current_user', 'blueworx_support_rest_auth', 20 );
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/support-access.php tests/support-access.spec.js
git commit -m "feat: authenticate REST reads from the support access key"
```

---

### Task 7: Personal-data screen and route gating

**Files:**
- Modify: `includes/support-access.php`
- Test: `tests/support-access.spec.js`

**Interfaces:**
- Consumes: `blueworx_support_is_support_user()`, `blueworx_support_data_open()`
- Produces:
  - `blueworx_support_denied_screens(): array` — `$pagenow` values
  - `blueworx_support_denied_routes(): array` — route prefixes
  - `blueworx_support_gate_data_screens(): void` — hooked on `admin_init`
  - `blueworx_support_gate_data_routes( $result, $server, $request )` — hooked on `rest_pre_dispatch`, priority 11

No field-level masking is implemented — see spec §1.5 for why.

- [ ] **Step 1: Write the failing test**

```js
test('personal-data screens are denied unless data access is opened', async ({ page, context }) => {
  await login(page);
  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Generate key' }).click();
  const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
  await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

  const fresh = await context.browser().newContext();
  const anon = await fresh.newPage();
  await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);

  expect((await anon.goto(`${baseURL}/wp-admin/users.php`)).status()).toBe(403);
  expect((await anon.goto(`${baseURL}/wp-admin/edit-comments.php`)).status()).toBe(403);
  // A non-data screen still reads fine.
  expect((await anon.goto(`${baseURL}/wp-admin/options-general.php`)).status()).toBe(200);

  // Re-open with data access ticked.
  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Close support access' }).click();
  await page.getByLabel('Also allow access to personal data for this session').check();
  await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

  await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
  expect((await anon.goto(`${baseURL}/wp-admin/users.php`)).status()).toBe(200);

  await fresh.close();
  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Revoke key' }).click();
});
```

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — `users.php` returns 200 with data access shut.

- [ ] **Step 3: Write minimal implementation**

```php
/**
 * Admin screens denied to the support account without data access.
 *
 * @return array $pagenow values.
 */
function blueworx_support_denied_screens() {
	/**
	 * Filters the screens hidden from support access.
	 *
	 * Lets a site add the data screens of a plugin this list does not know about.
	 *
	 * @param array $screens $pagenow values.
	 */
	return (array) apply_filters(
		'blueworx_support_denied_screens',
		array( 'users.php', 'user-edit.php', 'edit-comments.php', 'export.php' )
	);
}

/**
 * REST route prefixes denied to the support account without data access.
 *
 * @return array Route prefixes.
 */
function blueworx_support_denied_routes() {
	/**
	 * Filters the REST routes hidden from support access.
	 *
	 * @param array $routes Route prefixes.
	 */
	return (array) apply_filters(
		'blueworx_support_denied_routes',
		array( '/wp/v2/users', '/wp/v2/comments', '/blueworx/v1/account', '/blueworx/v1/surecart' )
	);
}

/**
 * Denies personal-data admin screens unless data access is open.
 *
 * A 403 rather than a redirect, so the refusal is unambiguous.
 *
 * @return void
 */
function blueworx_support_gate_data_screens() {
	global $pagenow;

	if ( ! blueworx_support_is_support_user() || blueworx_support_data_open() ) {
		return;
	}

	if ( ! in_array( (string) $pagenow, blueworx_support_denied_screens(), true ) ) {
		return;
	}

	// The account's own profile is reachable; other users' are not.
	if ( 'user-edit.php' === $pagenow ) {
		$target = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $target === get_current_user_id() ) {
			return;
		}
	}

	wp_die(
		esc_html__( 'This screen holds personal data and is not available to BlueWorx support access.', 'blueworx-labs-wordpress' ),
		esc_html__( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
		array( 'response' => 403 )
	);
}
add_action( 'admin_init', 'blueworx_support_gate_data_screens' );

/**
 * Denies personal-data REST routes unless data access is open.
 *
 * @param mixed           $result  Pre-dispatch result.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Current request.
 * @return mixed Untouched result, or a WP_Error.
 */
function blueworx_support_gate_data_routes( $result, $server, $request ) {
	unset( $server );

	if ( ! blueworx_support_is_support_user() || blueworx_support_data_open() ) {
		return $result;
	}

	$route = (string) $request->get_route();

	foreach ( blueworx_support_denied_routes() as $prefix ) {
		if ( 0 === strpos( $route, $prefix ) ) {
			return new WP_Error(
				'blueworx_support_no_data',
				__( 'This route returns personal data and is not available to BlueWorx support access.', 'blueworx-labs-wordpress' ),
				array( 'status' => 403 )
			);
		}
	}

	return $result;
}
add_filter( 'rest_pre_dispatch', 'blueworx_support_gate_data_routes', 11, 3 );
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/support-access.php tests/support-access.spec.js
git commit -m "feat: deny personal-data screens and routes without an explicit opt-in"
```

---

### Task 8: Audit log

**Files:**
- Modify: `includes/support-access.php` (replace the Task 3 stub)
- Modify: `includes/admin-settings.php` (render the log)
- Test: `tests/support-access.spec.js`

**Interfaces:**
- Produces:
  - `blueworx_support_log_event( string $type ): void` — real implementation
  - `blueworx_support_get_log(): array` — newest first

- [ ] **Step 1: Write the failing test**

```js
test('the audit log records opening, login and a blocked write', async ({ page, context }) => {
  await login(page);
  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Generate key' }).click();
  const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
  await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

  const fresh = await context.browser().newContext();
  const anon = await fresh.newPage();
  await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
  await anon.request.post(`${baseURL}/wp-admin/admin-post.php`, { data: { probe: '1' } });
  await fresh.close();

  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  const log = page.locator('[data-testid="bw-support-log"]');
  await expect(log).toContainText('access_opened');
  await expect(log).toContainText('login');
  await expect(log).toContainText('blocked_write');

  await page.getByRole('button', { name: 'Revoke key' }).click();
});
```

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — no log element exists.

- [ ] **Step 3: Write minimal implementation**

Replace the Task 3 stub with:

```php
/**
 * Records an audit event, keeping the most recent 100.
 *
 * The log is what makes the access window verifiable rather than merely
 * claimed, so it records refusals as well as successes.
 *
 * @param string $type Event type.
 * @return void
 */
function blueworx_support_log_event( $type ) {
	$log = get_option( 'blueworx_support_log', array() );

	if ( ! is_array( $log ) ) {
		$log = array();
	}

	$ip = isset( $_SERVER['REMOTE_ADDR'] )
		? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
		: '';

	$log[] = array(
		'type' => sanitize_key( $type ),
		'time' => time(),
		'ip'   => $ip,
	);

	update_option( 'blueworx_support_log', array_slice( $log, -100 ) );
}

/**
 * Gets the audit log, newest first.
 *
 * @return array Log entries.
 */
function blueworx_support_get_log() {
	$log = get_option( 'blueworx_support_log', array() );

	return is_array( $log ) ? array_reverse( $log ) : array();
}
```

Render in the console panel:

```php
<ul data-testid="bw-support-log">
	<?php foreach ( blueworx_support_get_log() as $entry ) : ?>
		<li>
			<code><?php echo esc_html( $entry['type'] ); ?></code>
			<?php echo esc_html( date_i18n( 'Y-m-d H:i', (int) $entry['time'] ) ); ?>
			<?php echo esc_html( $entry['ip'] ); ?>
		</li>
	<?php endforeach; ?>
</ul>
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/support-access.php includes/admin-settings.php tests/support-access.spec.js
git commit -m "feat: audit log for support access events"
```

---

### Task 9: Closing the window ends live sessions

Without this, closing the toggle only blocks *new* logins — an already-signed-in session would survive, which defeats the control.

**Files:**
- Modify: `includes/support-access.php`
- Test: `tests/support-access.spec.js`

**Interfaces:**
- Produces: `blueworx_support_enforce_window(): void` — hooked on `init`, priority 2

- [ ] **Step 1: Write the failing test**

```js
test('closing the window logs out a live support session', async ({ page, context }) => {
  await login(page);
  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Generate key' }).click();
  const key = (await page.locator('[data-testid="bw-support-key"]').innerText()).trim();
  await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

  const fresh = await context.browser().newContext();
  const anon = await fresh.newPage();
  await anon.goto(`${baseURL}/?blueworx_support_login=${key}`);
  await expect(anon.locator('body.wp-admin')).toHaveCount(1);

  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Close support access' }).click();

  // The live session is over, not merely barred from new logins.
  const after = await anon.goto(`${baseURL}/wp-admin/options-general.php`);
  expect(after.status()).toBe(403);

  await fresh.close();
  await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
  await page.getByRole('button', { name: 'Revoke key' }).click();
});
```

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — the closed-window request still returns 200.

- [ ] **Step 3: Write minimal implementation**

```php
/**
 * Ends any support session once the window is shut or lapsed.
 *
 * The toggle would otherwise only bar new logins, leaving an already-open
 * session running for as long as its cookie lasted.
 *
 * @return void
 */
function blueworx_support_enforce_window() {
	if ( ! blueworx_support_is_support_user() || blueworx_support_access_open() ) {
		return;
	}

	wp_destroy_current_session();
	wp_clear_auth_cookie();
	wp_set_current_user( 0 );
	blueworx_support_log_event( 'access_expired' );

	wp_die(
		esc_html__( 'The BlueWorx support window has closed.', 'blueworx-labs-wordpress' ),
		esc_html__( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
		array( 'response' => 403 )
	);
}
add_action( 'init', 'blueworx_support_enforce_window', 2 );
```

Ordering matters: priority 1 is the login handler (Task 4), which sets the current user *and* only runs while the window is open, so priority 2 never fires on the login request itself.

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/support-access.php tests/support-access.spec.js
git commit -m "feat: end live support sessions when the window closes"
```

---

### Task 10: Uninstall cleanup, version bump, changelog

**Files:**
- Modify: `uninstall.php`
- Modify: `blueworx-labs-wordpress.php` (version header and constant)
- Modify: `package.json`, `readme.txt`, `CHANGELOG.md`

- [ ] **Step 1: Write the failing test**

Run the version guard, which is a real CI gate:

```bash
npm run version:check
```

- [ ] **Step 2: Run it to verify it fails**

Expected: FAIL — the plugin header still reads 1.36.0 while the changelog has no 1.38.0 entry. (If it passes, the bump has already been made; confirm before proceeding.)

- [ ] **Step 3: Write minimal implementation**

Set `1.38.0` in the plugin header, `BLUEWORX_LABS_VERSION`, `package.json`, and `readme.txt`'s stable tag. Add a `CHANGELOG.md` entry at the top:

```markdown
## 1.38.0

### Added
- BlueWorx support access: a single per-site key that opens a read-only wp-admin
  and REST session for 24 hours, controlled by a toggle in the console. The
  managed account has no usable password, so there is nothing to rotate.
- Personal data is out of reach unless separately opted in for that session.
- Audit log of every open, login, refusal and blocked write.
```

Add to `uninstall.php`, following the file's existing option-deletion pattern:

```php
require_once __DIR__ . '/includes/support-access.php';
blueworx_support_remove_account();

delete_option( 'blueworx_support_key_hash' );
delete_option( 'blueworx_support_access_until' );
delete_option( 'blueworx_support_data_until' );
delete_option( 'blueworx_support_log' );
```

- [ ] **Step 4: Verify**

```bash
npm run version:check
npx eslint assets/js
composer lint
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8892 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw \
WP_LOGIN_PATH=admin_login npx playwright test
```

Expected: version check passes, lint clean, full suite green. Per `CLAUDE.md`, **report lint findings rather than fixing them in a loop** — present them and wait for a decision.

- [ ] **Step 5: Commit and open the PR**

```bash
git add -A
git commit -m "chore: release 1.38.0 — BlueWorx support access"
git push -u origin support-access
gh pr create --title "feat: read-only BlueWorx support access" --body-file .wp-test/pr-body.md
```

- [ ] **Step 6: Run the security review**

```
/security-review
```

This is a spec requirement, not optional. Pay particular attention to the key comparison, the window arithmetic, and whether any write path reaches a handler before `init` priority 0.

---

## Self-Review

**Spec coverage:**

| Spec section | Task |
| --- | --- |
| §1.1 Account provisioning | 2, 3 (provisioning is triggered by key generation) |
| §1.2 Hard request block | 5 |
| §1.3 Key and access window | 1, 3 |
| §1.3 Rate limiting | 4b |
| §1.4 Entry points | 4 (browser), 6 (REST) |
| §1.5 Data gating | 7 |
| §1.6 Audit log | 8 |
| §1.7 Console UI | 3, 8 |
| Testing | every task |
| Security review | 10 |

Session termination on window close (Task 9) is not called out as its own spec section but is implied by §1.4's "the cookie is additionally invalidated when the window closes". Covered.

**Rate limiting** is implemented by Task 4b, covering both entry points. `rest-limit.php` was
not reused because it is REST-only and the browser login path is a plain query arg.

**Type consistency:** `blueworx_support_log_event()` is stubbed in Task 3 and implemented in Task 8 with the same one-string signature. `blueworx_support_ensure_account()` returns `int` and is only called for its side effect. Option names are identical across Tasks 1, 3, 8, 10.
