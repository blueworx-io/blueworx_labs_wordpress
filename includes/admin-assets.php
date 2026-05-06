<?php
/**
 * Admin styles and asset loading.
 *
 * @package BlueWorxEnhancements
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads admin CSS only on screens touched by this plugin.
 *
 * @param string $hook_suffix Current admin screen hook.
 * @return void
 */
function blueworx_enqueue_admin_assets( $hook_suffix ) {
	$allowed_screens = array(
		'settings_page_blueworx-enhancements',
		'profile.php',
		'user-edit.php',
	);

	if ( ! in_array( $hook_suffix, $allowed_screens, true ) ) {
		return;
	}

	wp_enqueue_style(
		'blueworx-enhancements-admin',
		BLUEWORX_ENHANCEMENTS_URL . 'assets/css/admin.css',
		array(),
		BLUEWORX_ENHANCEMENTS_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'blueworx_enqueue_admin_assets' );
