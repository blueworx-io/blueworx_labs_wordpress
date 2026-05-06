<?php
/**
 * Admin menu ordering.
 *
 * @package BlueWorxEnhancements
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the default admin menu order.
 *
 * @return array Default menu slugs.
 */
function blueworx_get_default_admin_menu_order() {
	return array(
		'index.php',
		'edit.php',
		'upload.php',
		'edit.php?post_type=page',
		'themes.php',
		'plugins.php',
		'users.php',
		'tools.php',
		'options-general.php',
	);
}

/**
 * Gets the saved admin menu order.
 *
 * @return array Saved or default menu slugs.
 */
function blueworx_get_saved_admin_menu_order() {
	$saved_order = get_option( 'blueworx_admin_menu_order', array() );

	if ( ! is_array( $saved_order ) || empty( $saved_order ) ) {
		return blueworx_get_default_admin_menu_order();
	}

	return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $saved_order ) ) ) );
}

/**
 * Gets visible admin menu items for the Edit Menu page.
 *
 * @return array Menu items keyed by slug.
 */
function blueworx_get_editable_admin_menu_items() {
	global $menu;

	$items = array();

	foreach ( (array) $menu as $menu_item ) {
		$label = isset( $menu_item[0] ) ? wp_strip_all_tags( (string) $menu_item[0] ) : '';
		$slug  = isset( $menu_item[2] ) ? (string) $menu_item[2] : '';

		if ( '' === $slug || 0 === strpos( $slug, 'separator' ) ) {
			continue;
		}

		$items[ $slug ] = trim( preg_replace( '/\s+/', ' ', $label ) );
	}

	return $items;
}

/**
 * Enables a custom admin menu order.
 *
 * @return bool Always true.
 */
function blueworx_enable_admin_menu_order() {
	return true;
}
add_filter( 'custom_menu_order', 'blueworx_enable_admin_menu_order' );

/**
 * Sets the preferred left admin menu order.
 *
 * Unknown plugin menu items are left for WordPress to place after these items.
 *
 * @param array $menu_order Existing menu order.
 * @return array Preferred menu order.
 */
function blueworx_admin_menu_order( $menu_order ) {
	return blueworx_get_saved_admin_menu_order();
}
add_filter( 'menu_order', 'blueworx_admin_menu_order' );
