<?php
/**
 * Admin menu semantic groups.
 *
 * The v2 design groups the sidebar by meaning (Overview / Custom Content /
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
		'custom'   => __( 'Custom Content', 'blueworx-labs-wordpress' ),
		'content'  => __( 'Content', 'blueworx-labs-wordpress' ),
		'site'     => __( 'Site', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Gets the fallback group for anything not matched by a rule.
 *
 * Unrecognised third-party menus land here. They are never dropped.
 *
 * Custom Content, not Site. A plugin's own top-level menu is nearly always the
 * content it manages — Clubhouse's Content menu and the collections nested under
 * it are the case in hand — whereas Site is core's housekeeping (Appearance,
 * Plugins, Users, Tools, Settings). Sending unknown menus to Site read as "the
 * bucket for everything we could not place"; Custom Content is what they
 * actually are.
 *
 * @return string Group key.
 */
function blueworx_get_default_admin_menu_group_fallback() {
	return 'custom';
}

/**
 * Gets the static slug => group map for core menus.
 *
 * Key order is load-bearing: blueworx_sort_admin_menu_group_by_design()
 * (includes/admin-menu-order.php) reads it as the intended order within a group,
 * so BlueWorx must sit directly after Dashboard here to render directly below it.
 *
 * @return array Group keys by slug.
 */
function blueworx_get_admin_menu_group_rules() {
	return array(
		'index.php'               => 'overview',
		// Overview, not Site. BlueWorx is this plugin's own console and belongs
		// beside the Dashboard it extends, not at the bottom of the sidebar.
		'blueworx-labs-wordpress' => 'overview',
		'edit.php'                => 'content',
		'upload.php'              => 'content',
		'edit.php?post_type=page' => 'content',
		'edit-comments.php'       => 'content',
		'themes.php'              => 'site',
		// Menus editor, promoted to a top-level Site row by
		// blueworx_register_menus_shortcut(). Sits directly after Appearance,
		// where WordPress nests it as a submenu.
		'nav-menus.php'           => 'site',
		'plugins.php'             => 'site',
		'users.php'               => 'site',
		'tools.php'               => 'site',
		'options-general.php'     => 'site',
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
