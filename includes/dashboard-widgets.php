<?php
/**
 * Dashboard tidy-up.
 *
 * Removes chosen dashboard panels outright, which is a different thing from
 * what includes/admin-theme.php does. That file filters
 * `default_hidden_meta_boxes`, which only unticks a box in Screen Options for a
 * user who has never set their own — the panel is still registered, still
 * listed, and one tick brings it back. That is right for a design default. It is
 * wrong when a site owner has decided a panel should not be there, which is what
 * this feature is for.
 *
 * Replaces the Admin and Site Enhancements `disable_dashboard_widgets` module.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the dashboard panels this feature can remove.
 *
 * Keyed by meta box id, except the welcome panel, which is not a meta box at
 * all — it is printed by an action — and so carries the synthetic id
 * `welcome_panel`, handled separately below.
 *
 * Panels belonging to plugins are listed unconditionally. An inactive plugin
 * never registers its box, so asking to remove a box that does not exist is a
 * no-op rather than an error, and the setting survives the plugin being
 * switched off and on again.
 *
 * @return array Panel labels keyed by id.
 */
function blueworx_dashboard_removable_widgets() {
	return array(
		'welcome_panel'         => __( 'Welcome to WordPress', 'blueworx-labs-wordpress' ),
		'dashboard_activity'    => __( 'Activity', 'blueworx-labs-wordpress' ),
		'dashboard_quick_press' => __( 'Quick Draft', 'blueworx-labs-wordpress' ),
		'dashboard_primary'     => __( 'WordPress Events and News', 'blueworx-labs-wordpress' ),
		'dashboard_right_now'   => __( 'At a Glance', 'blueworx-labs-wordpress' ),
		'dashboard_site_health' => __( 'Site Health Status', 'blueworx-labs-wordpress' ),
		'e-dashboard-overview'  => __( 'Elementor Overview', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Gets the panels chosen for removal.
 *
 * The default matches what the BlueWorx admin theme already hides by default,
 * plus the welcome panel it already removes outright — so switching this
 * feature on changes nothing visible on a stock BlueWorx site, it just makes
 * those three stay gone instead of being one Screen Options tick away.
 *
 * Activity is NOT in the default list even though the reference site turned it
 * off. It is the only panel showing what has recently changed on the site, and
 * removing it for every client to match one client's preference is the wrong
 * default; that site ticks the box at cutover.
 *
 * @return array Panel ids.
 */
function blueworx_dashboard_removed_widgets() {
	$stored = get_option( 'blueworx_dashboard_removed_widgets', null );

	if ( ! is_array( $stored ) ) {
		return array( 'welcome_panel', 'dashboard_quick_press', 'e-dashboard-overview' );
	}

	return array_values(
		array_intersect(
			array_keys( blueworx_dashboard_removable_widgets() ),
			array_map( 'sanitize_key', $stored )
		)
	);
}

/**
 * Removes the chosen dashboard panels.
 *
 * Priority 30, after admin-theme.php's own dashboard pass at 20, so the hero
 * tiles it registers are already in place and this can remove them too if a site
 * asks for At a Glance gone.
 *
 * @return void
 */
function blueworx_dashboard_remove_widgets() {
	$removed = blueworx_dashboard_removed_widgets();

	if ( in_array( 'welcome_panel', $removed, true ) ) {
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
	}

	if ( in_array( 'dashboard_right_now', $removed, true ) ) {
		// The BlueWorx theme replaces At a Glance with its own hero tiles under a
		// different id; a site asking for that panel gone means both.
		remove_meta_box( 'blueworx_dashboard_stats', 'dashboard', 'normal' );
	}

	foreach ( $removed as $id ) {
		if ( 'welcome_panel' === $id ) {
			continue;
		}

		// A box can be registered in any context; core's remove_meta_box() only
		// clears the one it is told about, so all three are swept.
		foreach ( array( 'normal', 'side', 'column3', 'column4' ) as $context ) {
			remove_meta_box( $id, 'dashboard', $context );
		}
	}
}

if ( blueworx_feature_enabled( 'dashboard_widgets' ) ) {
	add_action( 'wp_dashboard_setup', 'blueworx_dashboard_remove_widgets', 30 );
}
