<?php
/**
 * Feature registry and per-feature on/off gate.
 *
 * Single source of truth for which enhancement functions exist, how they are
 * grouped on the settings page, and whether each is enabled. Every feature
 * defaults to on: an absent option is treated as enabled, so a fresh install
 * behaves exactly as before this settings page existed.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the ordered settings sections.
 *
 * @return array Section labels keyed by section id, in display order.
 */
function blueworx_get_feature_sections() {
	return array(
		'security'      => __( 'Security & Access', 'blueworx-labs-wordpress' ),
		'content'       => __( 'Content', 'blueworx-labs-wordpress' ),
		'notifications' => __( 'Notifications & Cleanup', 'blueworx-labs-wordpress' ),
		'performance'   => __( 'Performance', 'blueworx-labs-wordpress' ),
		'admin_menu'    => __( 'Admin Menu', 'blueworx-labs-wordpress' ),
		'appearance'    => __( 'Appearance', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Gets the feature registry.
 *
 * @return array Feature definitions keyed by feature key, in display order.
 */
function blueworx_get_feature_definitions() {
	return array(
		'login'                 => array(
			'label'       => __( 'Custom login & protection', 'blueworx-labs-wordpress' ),
			'description' => __( 'Moves login to a custom URL and blocks the default WordPress login and admin paths.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => 'login',
		),
		'site_protection'       => array(
			'label'       => __( 'Site Protection', 'blueworx-labs-wordpress' ),
			'description' => __( 'Only lets logged-in users with selected roles view the frontend or backend.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => 'site_protection',
		),
		'client_roles'          => array(
			'label'       => __( 'Client Roles', 'blueworx-labs-wordpress' ),
			'description' => __( 'Adds Business Owner, External Dev and Content Editor roles that show or hide backend areas for client accounts.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => 'client_roles',
		),
		'application_passwords' => array(
			'label'       => __( 'Application Passwords', 'blueworx-labs-wordpress' ),
			'description' => __( 'Hidden by default. When enabled, only admins can see Application Passwords on admin user profiles.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => 'application_passwords',
		),
		'comments'              => array(
			'label'       => __( 'Comments disabled', 'blueworx-labs-wordpress' ),
			'description' => __( 'Turns comments off and removes comment areas from the admin screens.', 'blueworx-labs-wordpress' ),
			'section'     => 'content',
			'detail'      => null,
		),
		'page_excerpts'         => array(
			'label'       => __( 'Page excerpts', 'blueworx-labs-wordpress' ),
			'description' => __( 'Adds excerpt support to Pages, the same way Posts already have it.', 'blueworx-labs-wordpress' ),
			'section'     => 'content',
			'detail'      => null,
		),
		'emails'                => array(
			'label'       => __( 'Email notifications reduced', 'blueworx-labs-wordpress' ),
			'description' => __( 'Stops extra admin emails for user, password, plugin, and theme changes.', 'blueworx-labs-wordpress' ),
			'section'     => 'notifications',
			'detail'      => null,
		),
		'profile_cleanup'       => array(
			'label'       => __( 'Profile cleanup', 'blueworx-labs-wordpress' ),
			'description' => __( 'Hides unused profile options, Elementor AI, and Elementor Notes.', 'blueworx-labs-wordpress' ),
			'section'     => 'notifications',
			'detail'      => null,
		),
		'cache_auto'            => array(
			'label'       => __( 'Automatic cache refresh', 'blueworx-labs-wordpress' ),
			'description' => __( 'Refreshes cache when pages or posts are changed.', 'blueworx-labs-wordpress' ),
			'section'     => 'performance',
			'detail'      => null,
		),
		'cache_manual'          => array(
			'label'       => __( 'Manual cache refresh', 'blueworx-labs-wordpress' ),
			'description' => __( 'Adds a Cache page where cache can be refreshed manually.', 'blueworx-labs-wordpress' ),
			'section'     => 'performance',
			'detail'      => 'cache_manual',
		),
		'menu_editor'           => array(
			'label'       => __( 'Menu editor', 'blueworx-labs-wordpress' ),
			'description' => __( 'Lets you reorder menu items, hide them, or move them into More.', 'blueworx-labs-wordpress' ),
			'section'     => 'admin_menu',
			'detail'      => 'menu_editor',
		),
		'admin_theme'           => array(
			'label'       => __( 'BlueWorx admin theme', 'blueworx-labs-wordpress' ),
			'description' => __( 'Restyles the WordPress admin and login screens with the BlueWorx look. Purely visual; turn off to return to the standard WordPress appearance.', 'blueworx-labs-wordpress' ),
			'section'     => 'appearance',
			'detail'      => null,
		),
		'public_site'           => array(
			'label'       => __( 'BlueWorx public site', 'blueworx-labs-wordpress' ),
			'description' => __( 'Renders the BlueWorx marketing site from this plugin, independently of the active theme. Turn off to hand the front end back to WordPress.', 'blueworx-labs-wordpress' ),
			'section'     => 'appearance',
			'detail'      => null,
		),
	);
}

/**
 * Checks whether a feature is enabled.
 *
 * Absent option means enabled: features default on so existing installs keep
 * their current behavior without a migration.
 *
 * @param string $key Feature key from blueworx_get_feature_definitions().
 * @return bool True when the feature is enabled.
 */
function blueworx_feature_enabled( $key ) {
	return '0' !== get_option( 'blueworx_feature_' . $key, '1' );
}
