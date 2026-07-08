<?php
/**
 * One-time upgrade migrations.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the current migration version for this file's migrations.
 *
 * Bump this when a new migration is added below.
 *
 * @return int Current migration version.
 */
function blueworx_get_labs_db_version() {
	return 1;
}

/**
 * Gets the admin menu options that may contain a stored menu slug.
 *
 * Each of these options is a flat, indexed array of slug strings.
 *
 * @return array Option names.
 */
function blueworx_get_admin_menu_slug_options() {
	return array(
		'blueworx_admin_menu_order',
		'blueworx_hidden_admin_menu_items',
		'blueworx_toggled_admin_menu_items',
	);
}

/**
 * Remaps a slug value within a flat, indexed array of slug strings.
 *
 * @param array  $slugs    Indexed array of slug strings.
 * @param string $old_slug Slug to replace.
 * @param string $new_slug Replacement slug.
 * @return array Remapped, de-duplicated, re-indexed array.
 */
function blueworx_remap_admin_menu_slug_option( $slugs, $old_slug, $new_slug ) {
	if ( ! is_array( $slugs ) ) {
		return $slugs;
	}

	$remapped = array_map(
		function ( $slug ) use ( $old_slug, $new_slug ) {
			return $old_slug === $slug ? $new_slug : $slug;
		},
		$slugs
	);

	return array_values( array_unique( $remapped ) );
}

/**
 * Migrates the old plugin slug to the new plugin slug inside saved admin
 * menu settings, so admins' menu customizations survive the plugin rename
 * from "blueworx-enhancements" to "blueworx-project-wordpress-labs".
 *
 * @return void
 */
function blueworx_migrate_admin_menu_slug_rename() {
	$old_slug = 'blueworx-enhancements';
	$new_slug = 'blueworx-project-wordpress-labs';

	foreach ( blueworx_get_admin_menu_slug_options() as $option_name ) {
		$value = get_option( $option_name, null );

		if ( ! is_array( $value ) || ! in_array( $old_slug, $value, true ) ) {
			continue;
		}

		$remapped = blueworx_remap_admin_menu_slug_option( $value, $old_slug, $new_slug );

		if ( $remapped !== $value ) {
			update_option( $option_name, $remapped );
		}
	}
}

/**
 * Runs any pending one-time migrations.
 *
 * Cheap on every request: a single get_option compare when already current.
 *
 * @return void
 */
function blueworx_run_pending_labs_migrations() {
	$current_version = blueworx_get_labs_db_version();
	$stored_version  = (int) get_option( 'blueworx_labs_db_version', 0 );

	if ( $stored_version >= $current_version ) {
		return;
	}

	if ( $stored_version < 1 ) {
		blueworx_migrate_admin_menu_slug_rename();
	}

	update_option( 'blueworx_labs_db_version', $current_version );
}
add_action( 'plugins_loaded', 'blueworx_run_pending_labs_migrations' );
