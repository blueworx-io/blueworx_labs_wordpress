# Client Roles Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add three assignable client roles (Business Owner, External Dev, Content Editor) that show/hide backend areas via WordPress capabilities plus the plugin's existing sidebar groups, behind a BlueWorx > Enhancements toggle.

**Architecture:** A new `includes/client-roles.php` owns role definitions (cloned from core roles at registration), an idempotent ensure/sync routine, a group-based `admin_menu` gating pass, an administrators-only console guard, and a `map_meta_cap` guard protecting admin accounts from Content Editors. Roles are registered on activation + a version-gated migration, persist across deactivate, and are removed (definitions only) on uninstall while user assignments in `wp_capabilities` meta are preserved so reinstalling restores them. Site Protection picks the roles up for free because it reads `wp_roles()` live.

**Tech Stack:** PHP 8.0 (WordPress plugin), Playwright (E2E, guarded/skippable), phpcs (WordPress-Extra ruleset), ESLint (JS only).

**Testing note:** This repo has **no PHP unit harness** — verification is Playwright E2E against a live WP (skipped when no staging is configured) plus manual reasoning. PHP tasks therefore implement-then-commit; the Playwright task (Task 7) covers the E2E-observable behaviour, and lint runs once at the end (Task 9). Do **not** lint-fix in a loop between tasks.

## Global Constraints

- Text domain: `blueworx-labs-wordpress` (every user-facing string).
- All output escaped (`esc_html`, `esc_attr`, `esc_url`); all input unslashed + sanitized; nonces on POST (follow existing `includes/admin-settings.php`).
- Every file starts with `if ( ! defined( 'ABSPATH' ) ) { exit; }` and a `@package BlueWorxLabs` docblock.
- Fresh role slugs only: `blueworx_client_owner`, `blueworx_client_dev`, `blueworx_client_editor`. Never re-register the retired `blueworx_business_owner` / `blueworx_external_admin` / `blueworx_content_editor`.
- Feature key `client_roles` defaults ON (absent `blueworx_feature_client_roles` option = enabled).
- Target version **1.16.0** (minor) across plugin header, `BLUEWORX_LABS_VERSION`, `package.json`, `readme.txt` stable tag, and `CHANGELOG.md`.
- Uninstall removes role **definitions** only — never iterate users or touch `wp_capabilities` meta.

---

### Task 1: Role definitions, caps builder, ensure/sync, remove

**Files:**
- Create: `includes/client-roles.php`

**Interfaces:**
- Produces:
  - `blueworx_client_role_slugs(): string[]`
  - `blueworx_client_editor_can_delete_users(): bool`
  - `blueworx_get_client_role_definitions(): array<string,array{label:string,clone:string,add:string[],remove:string[]}>`
  - `blueworx_build_client_role_caps(array $definition): array<string,bool>`
  - `blueworx_client_roles_signature(): string`
  - `blueworx_client_roles_ensure(): void`
  - `blueworx_client_roles_maybe_ensure(): void`
  - `blueworx_client_roles_remove_definitions(): void`

- [ ] **Step 1: Create the file with the header + role vocabulary + definitions**

```php
<?php
/**
 * Client Roles: three assignable roles that show/hide backend areas for
 * client accounts, built on core capabilities and the plugin's sidebar groups.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the three client role slugs.
 *
 * Fresh slugs, deliberately distinct from the retired role-editor slugs the
 * 1.15.0 migration swept up, so they never collide with the orphan skip-list.
 *
 * @return array Role slugs.
 */
function blueworx_client_role_slugs() {
	return array(
		'blueworx_client_owner',
		'blueworx_client_dev',
		'blueworx_client_editor',
	);
}

/**
 * Whether Content Editors may delete users.
 *
 * @return bool True when the BlueWorx setting is on.
 */
function blueworx_client_editor_can_delete_users() {
	return '1' === get_option( 'blueworx_client_editor_can_delete_users', '0' );
}

/**
 * Gets the client role definitions.
 *
 * Each role clones a live core role at registration time and adjusts caps, so
 * the definitions track whatever the site's administrator/editor roles hold.
 *
 * @return array Definitions keyed by slug.
 */
function blueworx_get_client_role_definitions() {
	$editor_add = array( 'list_users', 'edit_users' );

	if ( blueworx_client_editor_can_delete_users() ) {
		$editor_add[] = 'delete_users';
		$editor_add[] = 'remove_users';
	}

	return array(
		'blueworx_client_owner'  => array(
			'label'  => __( 'Admin — Business Owner', 'blueworx-labs-wordpress' ),
			'clone'  => 'administrator',
			'add'    => array(),
			'remove' => array(
				'activate_plugins',
				'install_plugins',
				'update_plugins',
				'delete_plugins',
				'edit_plugins',
				'install_themes',
				'update_themes',
				'delete_themes',
				'edit_themes',
				'edit_files',
				'update_core',
				'import',
				'export',
			),
		),
		'blueworx_client_dev'    => array(
			'label'  => __( 'External Dev', 'blueworx-labs-wordpress' ),
			'clone'  => 'administrator',
			'add'    => array(),
			'remove' => array(
				'list_users',
				'create_users',
				'edit_users',
				'delete_users',
				'promote_users',
				'remove_users',
				'edit_files',
				'edit_plugins',
				'edit_themes',
			),
		),
		'blueworx_client_editor' => array(
			'label'  => __( 'Content Editor', 'blueworx-labs-wordpress' ),
			'clone'  => 'editor',
			'add'    => $editor_add,
			'remove' => array(),
		),
	);
}
```

- [ ] **Step 2: Add the caps builder, signature, ensure, and remove routines**

Append to `includes/client-roles.php`:

```php
/**
 * Builds a role's capability map from its definition.
 *
 * Clones the live base role's caps, removes the listed caps, adds the listed
 * caps, and guarantees `read`.
 *
 * @param array $definition One entry from blueworx_get_client_role_definitions().
 * @return array Capability map (cap => true).
 */
function blueworx_build_client_role_caps( $definition ) {
	$base = get_role( $definition['clone'] );
	$caps = ( $base && is_array( $base->capabilities ) ) ? $base->capabilities : array();

	foreach ( $definition['remove'] as $cap ) {
		unset( $caps[ $cap ] );
	}

	foreach ( $definition['add'] as $cap ) {
		$caps[ $cap ] = true;
	}

	$caps['read'] = true;

	return $caps;
}

/**
 * Computes a signature of the roles' effective capabilities.
 *
 * Labels are excluded so translations never trigger a needless re-sync; only a
 * change in the actual capability set does.
 *
 * @return string Signature.
 */
function blueworx_client_roles_signature() {
	$data = array();

	foreach ( blueworx_get_client_role_definitions() as $slug => $definition ) {
		$caps = array_keys( array_filter( blueworx_build_client_role_caps( $definition ) ) );
		sort( $caps );
		$data[ $slug ] = $caps;
	}

	ksort( $data );

	return md5( (string) wp_json_encode( $data ) );
}

/**
 * Registers the client roles and keeps their caps in sync.
 *
 * Idempotent: does nothing when every role exists and the capability signature
 * is unchanged. When a role is missing or its caps changed, the role is
 * re-defined via remove_role() + add_role(). remove_role() does not touch users'
 * wp_capabilities meta, so any user assigned the role keeps the assignment and
 * regains its caps the instant the role is re-added.
 *
 * @return void
 */
function blueworx_client_roles_ensure() {
	$definitions = blueworx_get_client_role_definitions();
	$signature   = blueworx_client_roles_signature();
	$stored      = get_option( 'blueworx_client_roles_signature', '' );
	$all_exist   = true;

	foreach ( array_keys( $definitions ) as $slug ) {
		if ( ! get_role( $slug ) ) {
			$all_exist = false;
			break;
		}
	}

	if ( $all_exist && $stored === $signature ) {
		return;
	}

	foreach ( $definitions as $slug => $definition ) {
		$caps = blueworx_build_client_role_caps( $definition );

		if ( get_role( $slug ) ) {
			remove_role( $slug );
		}

		add_role( $slug, $definition['label'], $caps );
	}

	update_option( 'blueworx_client_roles_signature', $signature );
}

/**
 * Ensures the roles only when the feature is enabled.
 *
 * @return void
 */
function blueworx_client_roles_maybe_ensure() {
	if ( blueworx_feature_enabled( 'client_roles' ) ) {
		blueworx_client_roles_ensure();
	}
}

/**
 * Removes the client role definitions.
 *
 * Definition-only: users' wp_capabilities meta is deliberately left untouched so
 * that re-adding the plugin restores every assignment. Used by uninstall.
 *
 * @return void
 */
function blueworx_client_roles_remove_definitions() {
	foreach ( blueworx_client_role_slugs() as $slug ) {
		if ( get_role( $slug ) ) {
			remove_role( $slug );
		}
	}

	delete_option( 'blueworx_client_roles_signature' );
}
```

- [ ] **Step 3: Sanity-check PHP parses**

Run: `php -l includes/client-roles.php`
Expected: `No syntax errors detected in includes/client-roles.php`

- [ ] **Step 4: Commit**

```bash
git add includes/client-roles.php
git commit -m "feat: client role definitions, caps builder, ensure/sync"
```

---

### Task 2: Group-based menu gating, admins-only console, wiring

**Files:**
- Modify: `includes/client-roles.php`
- Modify: `blueworx-labs-wordpress.php` (require + activation hook + version stays until Task 8)

**Interfaces:**
- Consumes: `blueworx_client_role_slugs()`, `blueworx_client_roles_maybe_ensure()` (Task 1); `blueworx_feature_enabled()` (`includes/features.php`); `blueworx_get_admin_menu_group_for_slug()` (`includes/admin-menu-groups.php`).
- Produces:
  - `blueworx_current_user_client_role(): string`
  - `blueworx_user_is_administrator(WP_User|null $user): bool`
  - `blueworx_get_client_role_visible_groups(string $role_slug): string[]`
  - `blueworx_client_role_menu_exceptions(): string[]`
  - `blueworx_apply_client_role_menu_gating(): void`
  - `blueworx_client_roles_should_block_console(): bool`
  - `blueworx_block_console_page_access(): void`

- [ ] **Step 1: Add role-detection helpers and the visible-group map**

Append to `includes/client-roles.php`:

```php
/**
 * Gets the client role the current user holds, if any.
 *
 * @return string Role slug, or '' when the user holds none.
 */
function blueworx_current_user_client_role() {
	$user = wp_get_current_user();

	if ( ! $user || empty( $user->roles ) ) {
		return '';
	}

	foreach ( blueworx_client_role_slugs() as $slug ) {
		if ( in_array( $slug, (array) $user->roles, true ) ) {
			return $slug;
		}
	}

	return '';
}

/**
 * Whether a user holds the administrator role.
 *
 * @param WP_User|null $user User object.
 * @return bool True for administrators.
 */
function blueworx_user_is_administrator( $user ) {
	return $user instanceof WP_User && in_array( 'administrator', (array) $user->roles, true );
}

/**
 * Gets the sidebar groups a client role may see.
 *
 * Items whose group is not listed are removed from the sidebar; items still
 * appear only if the role's capabilities register them. Dashboard and Users are
 * handled as exceptions (see blueworx_client_role_menu_exceptions()).
 *
 * @param string $role_slug Client role slug.
 * @return array Group keys (from blueworx_get_admin_menu_groups()).
 */
function blueworx_get_client_role_visible_groups( $role_slug ) {
	$map = array(
		'blueworx_client_owner'  => array( 'overview', 'custom', 'content', 'site' ),
		'blueworx_client_dev'    => array( 'custom', 'content', 'site' ),
		'blueworx_client_editor' => array( 'custom', 'content' ),
	);

	return isset( $map[ $role_slug ] ) ? $map[ $role_slug ] : array();
}

/**
 * Top-level slugs shown regardless of group (still capability-bounded).
 *
 * Dashboard is universal; Users is surfaced for the roles that carry a user
 * capability (Business Owner, Content Editor), while External Dev has no user
 * capability so the item never registers for them.
 *
 * @return array Slugs.
 */
function blueworx_client_role_menu_exceptions() {
	return array( 'index.php', 'users.php' );
}
```

- [ ] **Step 2: Add the gating pass + console removal, hooked at admin_menu 9999**

Append to `includes/client-roles.php`:

```php
/**
 * Removes top-level sidebar items outside the current client role's groups.
 *
 * Runs only for non-administrator users holding a client role. The BlueWorx
 * console is removed unconditionally for them (administrators-only). Priority
 * 9999 so it runs after core, third-party plugins and the admin-theme passes
 * have registered and ordered the menu.
 *
 * @return void
 */
function blueworx_apply_client_role_menu_gating() {
	if ( ! blueworx_feature_enabled( 'client_roles' ) ) {
		return;
	}

	$user = wp_get_current_user();

	if ( ! $user instanceof WP_User || ! $user->exists() || blueworx_user_is_administrator( $user ) ) {
		return;
	}

	$role = blueworx_current_user_client_role();

	if ( '' === $role ) {
		return;
	}

	// Console is administrators-only; removing the parent drops its submenus too.
	remove_menu_page( 'blueworx-labs-wordpress' );

	$visible    = blueworx_get_client_role_visible_groups( $role );
	$exceptions = blueworx_client_role_menu_exceptions();

	global $menu;

	foreach ( (array) $menu as $item ) {
		$slug = isset( $item[2] ) ? (string) $item[2] : '';

		if ( '' === $slug || 0 === strpos( $slug, 'separator' ) || 'blueworx-labs-wordpress' === $slug ) {
			continue;
		}

		if ( in_array( $slug, $exceptions, true ) ) {
			continue;
		}

		if ( ! in_array( blueworx_get_admin_menu_group_for_slug( $slug ), $visible, true ) ) {
			remove_menu_page( $slug );
		}
	}
}
add_action( 'admin_menu', 'blueworx_apply_client_role_menu_gating', 9999 );

/**
 * Whether the BlueWorx console must be blocked for the current user.
 *
 * True when the feature is on and the user is a non-administrator holding a
 * client role. Other roles are unaffected.
 *
 * @return bool True when the console should be blocked.
 */
function blueworx_client_roles_should_block_console() {
	if ( ! blueworx_feature_enabled( 'client_roles' ) ) {
		return false;
	}

	$user = wp_get_current_user();

	if ( ! $user instanceof WP_User || blueworx_user_is_administrator( $user ) ) {
		return false;
	}

	return '' !== blueworx_current_user_client_role();
}

/**
 * Blocks direct URL access to the BlueWorx console pages for gated users.
 *
 * The menu is already removed for them; this stops hand-typed URLs.
 *
 * @return void
 */
function blueworx_block_console_page_access() {
	if ( ! blueworx_client_roles_should_block_console() ) {
		return;
	}

	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( in_array( $page, array( 'blueworx-labs-wordpress', 'blueworx-edit-menu', 'blueworx-cache' ), true ) ) {
		wp_die(
			esc_html__( 'You do not have access to this page.', 'blueworx-labs-wordpress' ),
			esc_html__( 'Client Roles', 'blueworx-labs-wordpress' ),
			array( 'response' => 403 )
		);
	}
}
add_action( 'admin_init', 'blueworx_block_console_page_access' );
```

- [ ] **Step 3: Require the new file and register the activation hook in the main plugin**

In `blueworx-labs-wordpress.php`, after the `admin-menu-order.php` require (line 48), add:

```php
require_once BLUEWORX_LABS_PATH . 'includes/client-roles.php';
```

Then, after the existing `register_activation_hook( __FILE__, 'blueworx_headless_install' );` line, add:

```php
register_activation_hook( __FILE__, 'blueworx_client_roles_maybe_ensure' );
```

- [ ] **Step 4: Sanity-check PHP parses**

Run: `php -l includes/client-roles.php && php -l blueworx-labs-wordpress.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Commit**

```bash
git add includes/client-roles.php blueworx-labs-wordpress.php
git commit -m "feat: group-based client-role menu gating + admins-only console"
```

---

### Task 3: Admin-protection guard for Content Editors

**Files:**
- Modify: `includes/client-roles.php`

**Interfaces:**
- Produces: `blueworx_protect_admins_from_content_editors(array $caps, string $cap, int $user_id, array $args): array` (a `map_meta_cap` filter).

- [ ] **Step 1: Add the map_meta_cap guard**

Append to `includes/client-roles.php`:

```php
/**
 * Stops a Content Editor from editing, deleting or promoting administrators.
 *
 * Content Editors carry edit_users so they can manage lesser accounts, but that
 * capability would otherwise let them reset an administrator's password and take
 * over the site. WordPress has no native "edit users except admins" capability,
 * so this denies the meta-cap when the target user is an administrator and the
 * acting user is a Content Editor (and not themselves also an administrator).
 *
 * @param array  $caps    Required primitive capabilities.
 * @param string $cap     Meta capability being checked.
 * @param int    $user_id Acting user ID.
 * @param array  $args    Extra args; $args[0] is the target user ID.
 * @return array Filtered primitive capabilities.
 */
function blueworx_protect_admins_from_content_editors( $caps, $cap, $user_id, $args ) {
	if ( ! blueworx_feature_enabled( 'client_roles' ) ) {
		return $caps;
	}

	if ( ! in_array( $cap, array( 'edit_user', 'delete_user', 'promote_user', 'remove_user' ), true ) ) {
		return $caps;
	}

	$actor = get_userdata( $user_id );

	if ( ! $actor
		|| ! in_array( 'blueworx_client_editor', (array) $actor->roles, true )
		|| in_array( 'administrator', (array) $actor->roles, true )
	) {
		return $caps;
	}

	$target_id = isset( $args[0] ) ? (int) $args[0] : 0;

	if ( $target_id && $target_id !== (int) $user_id ) {
		$target = get_userdata( $target_id );

		if ( $target && in_array( 'administrator', (array) $target->roles, true ) ) {
			$caps[] = 'do_not_allow';
		}
	}

	return $caps;
}
add_filter( 'map_meta_cap', 'blueworx_protect_admins_from_content_editors', 10, 4 );
```

- [ ] **Step 2: Sanity-check PHP parses**

Run: `php -l includes/client-roles.php`
Expected: `No syntax errors detected in includes/client-roles.php`

- [ ] **Step 3: Commit**

```bash
git add includes/client-roles.php
git commit -m "feat: protect admin accounts from Content Editor edit_users"
```

---

### Task 4: Feature registry entry + settings UI

**Files:**
- Modify: `includes/features.php:39-108` (registry)
- Modify: `includes/admin-settings.php` (detail renderer + save handling)

**Interfaces:**
- Consumes: `blueworx_get_client_role_definitions()`, `blueworx_client_editor_can_delete_users()`, `blueworx_client_roles_maybe_ensure()` (Task 1).
- Produces: feature key `client_roles`; option `blueworx_client_editor_can_delete_users`; a `client_roles` branch in `blueworx_render_feature_detail()`.

- [ ] **Step 1: Register the feature in the registry**

In `includes/features.php`, inside the array returned by `blueworx_get_feature_definitions()`, add this entry immediately after the `site_protection` entry:

```php
			'client_roles'          => array(
				'label'       => __( 'Client Roles', 'blueworx-labs-wordpress' ),
				'description' => __( 'Adds Business Owner, External Dev and Content Editor roles that show or hide backend areas for client accounts.', 'blueworx-labs-wordpress' ),
				'section'     => 'security',
				'detail'      => 'client_roles',
			),
```

- [ ] **Step 2: Persist the delete-users setting and re-sync roles on save**

In `includes/admin-settings.php`, in `blueworx_save_feature_settings()`, immediately after the Application Passwords line (`update_option( 'blueworx_show_application_passwords', ... );`), add:

```php
	// Client Roles detail: allow Content Editors to delete users.
	update_option( 'blueworx_client_editor_can_delete_users', isset( $_POST['blueworx_client_editor_can_delete_users'] ) ? '1' : '0' );

	// Re-sync role capabilities to match the current toggle + delete setting.
	blueworx_client_roles_maybe_ensure();
```

- [ ] **Step 3: Render the Client Roles detail controls**

In `includes/admin-settings.php`, in `blueworx_render_feature_detail()`, add this branch immediately before the `application_passwords` branch:

```php
	if ( 'client_roles' === $key ) {
		$definitions = blueworx_get_client_role_definitions();
		?>
		<p class="description">
			<?php esc_html_e( 'Three assignable roles. Areas are shown or hidden by role; the roles also appear in Site Protection. Roles are remembered if the plugin is disabled or removed, and restored when it is re-added.', 'blueworx-labs-wordpress' ); ?>
		</p>
		<ul>
			<?php foreach ( $definitions as $definition ) : ?>
				<li><?php echo esc_html( $definition['label'] ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p>
			<label>
				<input type="checkbox" name="blueworx_client_editor_can_delete_users" value="1" <?php checked( blueworx_client_editor_can_delete_users() ); ?> />
				<?php esc_html_e( 'Allow Content Editors to delete users', 'blueworx-labs-wordpress' ); ?>
			</label>
		</p>
		<?php
		return;
	}
```

- [ ] **Step 4: Sanity-check PHP parses**

Run: `php -l includes/features.php && php -l includes/admin-settings.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Commit**

```bash
git add includes/features.php includes/admin-settings.php
git commit -m "feat: Client Roles settings toggle + delete-users option"
```

---

### Task 5: Version-gated migration for existing sites

**Files:**
- Modify: `includes/upgrade.php:20-22` (db version), `:336-366` (runner)

**Interfaces:**
- Consumes: `blueworx_client_roles_maybe_ensure()` (Task 1).
- Produces: `blueworx_migrate_ensure_client_roles(): void`; db version 6.

- [ ] **Step 1: Bump the migration version**

In `includes/upgrade.php`, change `blueworx_get_labs_db_version()` to return `6`:

```php
function blueworx_get_labs_db_version() {
	return 6;
}
```

- [ ] **Step 2: Add the migration function**

In `includes/upgrade.php`, immediately before `blueworx_run_pending_labs_migrations()`, add:

```php
/**
 * Registers the client roles on sites upgrading to 1.16.0.
 *
 * Fresh activations get the roles from the activation hook; this covers existing
 * sites, where the activation hook does not fire on a plugin update. Idempotent
 * and gated on the feature being enabled (which it is by default).
 *
 * @return void
 */
function blueworx_migrate_ensure_client_roles() {
	blueworx_client_roles_maybe_ensure();
}
```

- [ ] **Step 3: Call it from the runner**

In `blueworx_run_pending_labs_migrations()`, immediately after the `if ( $stored_version < 5 ) { ... }` block, add:

```php
	if ( $stored_version < 6 ) {
		blueworx_migrate_ensure_client_roles();
	}
```

- [ ] **Step 4: Sanity-check PHP parses**

Run: `php -l includes/upgrade.php`
Expected: `No syntax errors detected in includes/upgrade.php`

- [ ] **Step 5: Commit**

```bash
git add includes/upgrade.php
git commit -m "feat: migration to register client roles on upgrade (db v6)"
```

---

### Task 6: Uninstall behaviour

**Files:**
- Create: `uninstall.php`

**Interfaces:**
- Self-contained: runs in WordPress's isolated uninstall context (plugin code not loaded), so slugs are inlined.

- [ ] **Step 1: Create uninstall.php**

```php
<?php
/**
 * Uninstall: remove the client role definitions.
 *
 * Definition-only. Users' wp_capabilities meta is deliberately preserved so that
 * reinstalling the plugin re-registers the roles and every assigned user regains
 * their role automatically. Slugs are inlined because the plugin's code is not
 * loaded during uninstall.
 *
 * @package BlueWorxLabs
 */

// Only run from WordPress's uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

foreach ( array( 'blueworx_client_owner', 'blueworx_client_dev', 'blueworx_client_editor' ) as $blueworx_role_slug ) {
	if ( get_role( $blueworx_role_slug ) ) {
		remove_role( $blueworx_role_slug );
	}
}

delete_option( 'blueworx_client_roles_signature' );
delete_option( 'blueworx_client_editor_can_delete_users' );
```

- [ ] **Step 2: Sanity-check PHP parses**

Run: `php -l uninstall.php`
Expected: `No syntax errors detected in uninstall.php`

- [ ] **Step 3: Commit**

```bash
git add uninstall.php
git commit -m "feat: uninstall removes client role definitions, keeps assignments"
```

---

### Task 7: Playwright tests

**Files:**
- Create: `tests/client-roles.spec.js`

**Interfaces:**
- Consumes: `tests/helpers.js` exports (`test`, `isPlaceholder`, `ADMIN_USER`, `ADMIN_PASS`, `login`) — same imports as `tests/feature-toggles.spec.js`.

- [ ] **Step 1: Write the spec**

```js
// `test` comes from helpers.js (see feature-toggles.spec.js for why): it carries
// the fixture that opts out of core's wp-admin view transitions.
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

async function gotoSettings(page) {
  await login(page);
  await page.goto(SETTINGS_PATH);
}

test.describe('BlueWorx Client Roles', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the Client Roles toggle renders and persists across save', async ({ page }) => {
    await gotoSettings(page);

    const toggle = page.locator('input.blueworx-feature-toggle[data-blueworx-feature="client_roles"]');
    await expect(toggle).toBeVisible();

    const wasChecked = await toggle.isChecked();

    await toggle.setChecked(!wasChecked);
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
    await expect(
      page.locator('input.blueworx-feature-toggle[data-blueworx-feature="client_roles"]')
    ).toBeChecked({ checked: !wasChecked });

    // Restore original state so the test is idempotent across runs.
    await page
      .locator('input.blueworx-feature-toggle[data-blueworx-feature="client_roles"]')
      .setChecked(wasChecked);
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
  });

  test('all three client roles are offered in the Site Protection role lists', async ({ page }) => {
    await gotoSettings(page);

    // Two selects (frontend + backend), so each registered role slug appears
    // twice. Site Protection reads wp_roles() live, so the roles show up here as
    // soon as they are registered.
    for (const slug of ['blueworx_client_owner', 'blueworx_client_dev', 'blueworx_client_editor']) {
      const count = await page.locator(`option[value="${slug}"]`).count();
      expect(count).toBeGreaterThan(0);
    }
  });
});
```

- [ ] **Step 2: Run the new spec**

Run: `npx playwright test tests/client-roles.spec.js`
Expected: PASS, or SKIPPED when no staging URL / admin creds are configured (`isPlaceholder`). Either outcome is acceptable for the commit; a hard FAIL is not.

- [ ] **Step 3: Commit**

```bash
git add tests/client-roles.spec.js
git commit -m "test: client roles toggle + Site Protection role listing"
```

---

### Task 8: Version bump, changelog, readme

**Files:**
- Modify: `blueworx-labs-wordpress.php:6` (header) and `:25` (constant)
- Modify: `package.json:3`
- Modify: `readme.txt:7` (stable tag)
- Modify: `CHANGELOG.md` (new entry at top)

**Interfaces:** none.

- [ ] **Step 1: Bump the plugin header and constant**

In `blueworx-labs-wordpress.php`, change ` * Version:           1.15.0` to ` * Version:           1.16.0`, and `define( 'BLUEWORX_LABS_VERSION', '1.15.0' );` to `define( 'BLUEWORX_LABS_VERSION', '1.16.0' );`.

- [ ] **Step 2: Bump package.json and readme stable tag**

In `package.json`, change `"version": "1.15.0",` to `"version": "1.16.0",`.
In `readme.txt`, change `Stable tag:        1.15.0` to `Stable tag:        1.16.0`.

- [ ] **Step 3: Add the changelog entry**

In `CHANGELOG.md`, insert immediately after the `versioning.` intro line and before `## [1.15.0] - 2026-07-20`:

```markdown
## [1.16.0] - 2026-07-20

### Added
- **Client Roles.** Three assignable roles for client accounts — **Admin —
  Business Owner**, **External Dev** and **Content Editor** — that show or hide
  whole backend areas, gated behind a new *Client Roles* toggle on BlueWorx >
  Enhancements (on by default). Areas are hidden by capability where possible and
  by the plugin's existing sidebar groups for third-party menus:
  - *Business Owner* — everything except Plugins and the file/code editors; Tools
    trimmed (no import/export). Keeps Settings, Appearance, Users and the store.
  - *External Dev* — plugins, appearance, tools and settings, but no Users
    management (own account only) and no file editors.
  - *Content Editor* — posts, pages, media and comments, plus editing other
    users' accounts (not deleting them, unless enabled below). Everything
    technical is hidden.
- **"Allow Content Editors to delete users"** setting under Client Roles, off by
  default, which grants Content Editors the delete-user capability.
- The three roles appear automatically in the Site Protection role lists.

### Security
- **Admin accounts protected from Content Editors.** A Content Editor's
  user-editing capability cannot be used to edit, delete or promote an
  administrator, closing a password-reset takeover path.
- **BlueWorx console is administrators-only** when Client Roles is on — hidden and
  URL-blocked for the client roles even though they may hold `manage_options`.

### Notes
- Client roles are registered on activation and via a one-time migration, persist
  across deactivation, and are removed (definitions only) on uninstall — user
  assignments are preserved, so reinstalling restores them.
```

- [ ] **Step 4: Verify the version-check script passes**

Run: `npm run version:check`
Expected: exit 0 (plugin header, constant, package.json and readme stable tag all agree on 1.16.0). If it reports a mismatch, fix the named file to `1.16.0`.

- [ ] **Step 5: Commit**

```bash
git add blueworx-labs-wordpress.php package.json readme.txt CHANGELOG.md
git commit -m "chore: bump to 1.16.0 + changelog for Client Roles"
```

---

### Task 9: Final lint check (report only — no auto-fix loop)

**Files:** none changed unless Luke approves fixes.

- [ ] **Step 1: Run ESLint once**

Run: `npm run lint`
Record any findings (this touches JS only; the feature is PHP, so expect none new).

- [ ] **Step 2: Run phpcs once against the changed PHP**

Run: `vendor/bin/phpcs --standard=phpcs.xml.dist includes/client-roles.php includes/features.php includes/admin-settings.php includes/upgrade.php uninstall.php blueworx-labs-wordpress.php`
Record findings.

- [ ] **Step 3: Report findings to Luke, do not auto-fix**

Present the ESLint + phpcs output. Per the repo's linting rule, wait for Luke to decide which (if any) to action before changing anything.

---

## Self-Review

**Spec coverage:**
- Roles + caps (spec §3) → Task 1. ✅
- Group gating + exceptions + console removal (spec §4) → Task 2. ✅
- Admin-protection guard (spec §3.4) → Task 3. ✅
- Settings toggle + delete-users setting (spec §6) → Task 4. ✅
- Registration/migration (spec §5) → Tasks 1 (ensure), 2 (activation), 5 (migration). ✅
- Uninstall/persistence (spec §5, D4) → Task 6. ✅
- Site Protection (spec §7) → free; asserted in Task 7. ✅
- Tests (spec §8) → Task 7. ✅
- Versioning/changelog/readme (spec §9) → Task 8. ✅
- Lint once, report only (spec §9) → Task 9. ✅

**Placeholder scan:** No TBD/TODO; every code step shows complete code. ✅

**Type consistency:** `blueworx_client_roles_maybe_ensure()` used in Tasks 2, 4, 5 matches its Task 1 definition. `blueworx_get_client_role_visible_groups()` returns group keys consumed by `blueworx_get_admin_menu_group_for_slug()`. `blueworx_client_editor_can_delete_users()` defined in Task 1, consumed in Tasks 1 and 4. Slug list identical across Tasks 1, 6, 7. ✅
