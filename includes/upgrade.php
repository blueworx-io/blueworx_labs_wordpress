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
	return 4;
}

/**
 * Gets the admin menu options that store the menu slug as flat array VALUES.
 *
 * Each of these options is a flat, indexed array of slug strings.
 *
 * @return array Option names.
 */
function blueworx_get_admin_menu_slug_value_options() {
	return array(
		'blueworx_admin_menu_order',
		'blueworx_hidden_admin_menu_items',
		'blueworx_toggled_admin_menu_items',
	);
}

/**
 * Remaps a slug that appears as a VALUE within a flat, indexed array.
 *
 * @param array  $slugs    Indexed array of slug strings.
 * @param string $old_slug Slug to replace.
 * @param string $new_slug Replacement slug.
 * @return array Remapped, de-duplicated, re-indexed array.
 */
function blueworx_remap_admin_menu_slug_value_option( $slugs, $old_slug, $new_slug ) {
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
 * Remaps a slug that appears as an array KEY, preserving its value.
 *
 * If the new key does not already exist, the old key is renamed to the new
 * key while keeping its value (and the surrounding key order). If the new key
 * already exists (both old and new present), the existing new-key entry is
 * preferred and the stale old-key entry is dropped, since the new key reflects
 * the live menu slug.
 *
 * @param array  $items    Associative array keyed by slug.
 * @param string $old_slug Key to replace.
 * @param string $new_slug Replacement key.
 * @return array Remapped array.
 */
function blueworx_remap_admin_menu_slug_key_option( $items, $old_slug, $new_slug ) {
	if ( ! is_array( $items ) || ! array_key_exists( $old_slug, $items ) ) {
		return $items;
	}

	if ( array_key_exists( $new_slug, $items ) ) {
		unset( $items[ $old_slug ] );

		return $items;
	}

	$remapped = array();

	foreach ( $items as $key => $value ) {
		$remapped[ $old_slug === $key ? $new_slug : $key ] = $value;
	}

	return $remapped;
}

/**
 * Migrates the old plugin slug to the new plugin slug inside saved admin
 * settings, so admins' menu customizations survive the plugin rename from
 * "blueworx-enhancements" to "blueworx-project-wordpress-labs".
 *
 * Covers every stored option that can hold the renamed menu slug:
 *  - blueworx_admin_menu_order         (slug as array value)
 *  - blueworx_hidden_admin_menu_items  (slug as array value)
 *  - blueworx_toggled_admin_menu_items (slug as array value)
 *  - blueworx_admin_menu_item_labels   (slug as array KEY, value = label)
 *
 * The role-editor options (blueworx_role_backend_page_rules,
 * blueworx_backend_page_map) cannot contain the renamed slug: the BlueWorx
 * top-level page is a locked backend page and locked pages are excluded from
 * the role editor, so the slug can never be stored there. See task-10 report.
 *
 * @return void
 */
function blueworx_migrate_admin_menu_slug_rename() {
	$old_slug = 'blueworx-enhancements';
	$new_slug = 'blueworx-project-wordpress-labs';

	// Options that store the slug as flat array VALUES.
	foreach ( blueworx_get_admin_menu_slug_value_options() as $option_name ) {
		$value = get_option( $option_name, null );

		if ( ! is_array( $value ) || ! in_array( $old_slug, $value, true ) ) {
			continue;
		}

		$remapped = blueworx_remap_admin_menu_slug_value_option( $value, $old_slug, $new_slug );

		if ( $remapped !== $value ) {
			update_option( $option_name, $remapped );
		}
	}

	// Option that stores the slug as an array KEY (slug => label).
	$labels = get_option( 'blueworx_admin_menu_item_labels', null );

	if ( is_array( $labels ) && array_key_exists( $old_slug, $labels ) ) {
		$remapped = blueworx_remap_admin_menu_slug_key_option( $labels, $old_slug, $new_slug );

		if ( $remapped !== $labels ) {
			update_option( 'blueworx_admin_menu_item_labels', $remapped );
		}
	}
}

/**
 * Migrates the plugin slug again after the repo rename, so admins' menu
 * customizations survive the second rename from "blueworx-project-wordpress-labs"
 * to "blueworx-labs-wordpress" (aligning the slug with the renamed repo).
 *
 * Runs after blueworx_migrate_admin_menu_slug_rename(), so sites upgrading from
 * the "blueworx-enhancements" era are remapped in two steps
 * (enhancements -> project-wordpress-labs -> labs-wordpress). Covers the same
 * stored options as that migration.
 *
 * @return void
 */
function blueworx_migrate_admin_menu_slug_labs_wordpress() {
	$old_slug = 'blueworx-project-wordpress-labs';
	$new_slug = 'blueworx-labs-wordpress';

	// Options that store the slug as flat array VALUES.
	foreach ( blueworx_get_admin_menu_slug_value_options() as $option_name ) {
		$value = get_option( $option_name, null );

		if ( ! is_array( $value ) || ! in_array( $old_slug, $value, true ) ) {
			continue;
		}

		$remapped = blueworx_remap_admin_menu_slug_value_option( $value, $old_slug, $new_slug );

		if ( $remapped !== $value ) {
			update_option( $option_name, $remapped );
		}
	}

	// Option that stores the slug as an array KEY (slug => label).
	$labels = get_option( 'blueworx_admin_menu_item_labels', null );

	if ( is_array( $labels ) && array_key_exists( $old_slug, $labels ) ) {
		$remapped = blueworx_remap_admin_menu_slug_key_option( $labels, $old_slug, $new_slug );

		if ( $remapped !== $labels ) {
			update_option( 'blueworx_admin_menu_item_labels', $remapped );
		}
	}
}

/**
 * Marks sites that already arranged their admin menu as customised, so the new
 * computed default arrangement does not overwrite an existing arrangement.
 *
 * A site counts as arranged if any of the three menu-state options holds a
 * non-empty array. Sites with no saved arrangement are left unmarked and adopt
 * the new defaults.
 *
 * @return void
 */
function blueworx_migrate_mark_admin_menu_customized() {
	foreach ( blueworx_get_admin_menu_slug_value_options() as $option_name ) {
		$value = get_option( $option_name, array() );

		if ( is_array( $value ) && ! empty( $value ) ) {
			update_option( 'blueworx_admin_menu_customized', '1' );

			return;
		}
	}
}

/**
 * Converts the retired More menu into semantic group assignments.
 *
 * The v2 design replaces the user-defined Main/More/Hidden split with four
 * semantic groups, so the More bucket has no equivalent and is retired.
 *
 * Items sitting in More are assigned to their rule-based group, which means they
 * REAPPEAR as top-level rows. This is deliberate: More was a grouping
 * affordance, not a hiding one — the plugin has always had a separate Hidden
 * bucket for hiding, so anyone wanting an item gone would have used it. Reading
 * More as "hide" would be the more destructive interpretation.
 *
 * Hidden items are left untouched. Order is preserved and reinterpreted as
 * order-within-group.
 *
 * @return void
 */
function blueworx_migrate_admin_menu_groups() {
	$toggled = get_option( 'blueworx_toggled_admin_menu_items', array() );
	$order   = get_option( 'blueworx_admin_menu_order', array() );
	$slugs   = array();

	foreach ( array( $toggled, $order ) as $source ) {
		if ( is_array( $source ) ) {
			$slugs = array_merge( $slugs, $source );
		}
	}

	$assignments = array();

	foreach ( array_unique( array_filter( $slugs ) ) as $slug ) {
		$slug = sanitize_text_field( (string) $slug );

		if ( '' === $slug || 0 === strpos( $slug, 'separator' ) || 'blueworx-menu-toggle' === $slug ) {
			continue;
		}

		$assignments[ $slug ] = blueworx_get_default_admin_menu_group( $slug );
	}

	if ( ! empty( $assignments ) ) {
		update_option( 'blueworx_admin_menu_groups', $assignments );
	}

	// Drop the retired More state and its synthetic rows from the saved order.
	delete_option( 'blueworx_toggled_admin_menu_items' );

	if ( is_array( $order ) ) {
		$cleaned = array_values(
			array_diff( $order, array( 'blueworx-menu-toggle', 'separator-blueworx-toggle' ) )
		);

		if ( $cleaned !== $order ) {
			update_option( 'blueworx_admin_menu_order', $cleaned );
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

	if ( $stored_version < 2 ) {
		blueworx_migrate_admin_menu_slug_labs_wordpress();
	}

	if ( $stored_version < 3 ) {
		blueworx_migrate_mark_admin_menu_customized();
	}

	if ( $stored_version < 4 ) {
		blueworx_migrate_admin_menu_groups();
	}

	update_option( 'blueworx_labs_db_version', $current_version );
}
add_action( 'plugins_loaded', 'blueworx_run_pending_labs_migrations' );
