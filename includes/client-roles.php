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
