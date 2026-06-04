<?php
/**
 * Admin asset loading.
 *
 * @package BlueWorxEnhancements
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets an asset version that changes when the file changes.
 *
 * @param string $relative_path Relative asset path.
 * @return string Asset version.
 */
function blueworx_get_admin_asset_version( $relative_path ) {
	$path = BLUEWORX_ENHANCEMENTS_PATH . ltrim( $relative_path, '/' );

	if ( file_exists( $path ) ) {
		return BLUEWORX_ENHANCEMENTS_VERSION . '-' . filemtime( $path );
	}

	return BLUEWORX_ENHANCEMENTS_VERSION;
}

/**
 * Loads admin scripts only on screens touched by this plugin.
 *
 * @param string $hook_suffix Current admin screen hook.
 * @return void
 */
function blueworx_enqueue_admin_assets( $hook_suffix ) {
	$allowed_screens = array(
		'toplevel_page_blueworx-enhancements',
		'blueworx_page_blueworx-edit-menu',
		'blueworx_page_blueworx-edit-role',
		'blueworx_page_blueworx-cache',
		'profile.php',
		'user-edit.php',
	);

	if ( ! in_array( $hook_suffix, $allowed_screens, true ) ) {
		return;
	}

	if ( in_array( $hook_suffix, array( 'profile.php', 'user-edit.php' ), true ) ) {
		wp_enqueue_script(
			'blueworx-enhancements-profile-cleanup',
			BLUEWORX_ENHANCEMENTS_URL . 'assets/js/profile-cleanup.js',
			array(),
			blueworx_get_admin_asset_version( 'assets/js/profile-cleanup.js' ),
			true
		);

		if ( blueworx_should_hide_application_passwords_section() ) {
			wp_add_inline_script(
				'blueworx-enhancements-profile-cleanup',
				'window.blueworxHideApplicationPasswords = true;',
				'before'
			);
		}
	}

	if ( 'blueworx_page_blueworx-edit-menu' === $hook_suffix ) {
		wp_enqueue_script(
			'blueworx-enhancements-admin-menu-order',
			BLUEWORX_ENHANCEMENTS_URL . 'assets/js/admin-menu-order.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			blueworx_get_admin_asset_version( 'assets/js/admin-menu-order.js' ),
			true
		);

		return;
	}

	if ( 'blueworx_page_blueworx-edit-role' === $hook_suffix ) {
		wp_enqueue_script(
			'blueworx-enhancements-role-editor',
			BLUEWORX_ENHANCEMENTS_URL . 'assets/js/role-editor.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			blueworx_get_admin_asset_version( 'assets/js/role-editor.js' ),
			true
		);
	}
}
add_action( 'admin_enqueue_scripts', 'blueworx_enqueue_admin_assets' );
