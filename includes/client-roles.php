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
