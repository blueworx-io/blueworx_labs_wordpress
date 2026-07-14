<?php
/**
 * BlueWorx admin & login re-skin (CSS-first).
 *
 * Restyles WordPress's own admin and login markup to the BlueWorx design system.
 * No frameworks and no replacement markup — the only custom markup is the
 * Dashboard hero-tiles widget below. Everything here is gated on the
 * `admin_theme` feature flag (default on) so it can be switched off from
 * BlueWorx > Enhancements.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the BlueWorx admin theme is active.
 *
 * @return bool True when the admin_theme feature is enabled.
 */
function blueworx_admin_theme_enabled() {
	return blueworx_feature_enabled( 'admin_theme' );
}

/**
 * Enqueues the admin re-skin on every admin screen.
 *
 * @return void
 */
function blueworx_enqueue_admin_theme() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	wp_enqueue_style(
		'blueworx-admin-fonts',
		BLUEWORX_LABS_URL . 'assets/css/blueworx-fonts.css',
		array(),
		blueworx_get_admin_asset_version( 'assets/css/blueworx-fonts.css' )
	);

	wp_enqueue_style(
		'blueworx-admin-theme',
		BLUEWORX_LABS_URL . 'assets/css/admin-theme.css',
		array( 'blueworx-admin-fonts' ),
		blueworx_get_admin_asset_version( 'assets/css/admin-theme.css' )
	);
}
add_action( 'admin_enqueue_scripts', 'blueworx_enqueue_admin_theme' );

/**
 * Enqueues the login re-skin.
 *
 * @return void
 */
function blueworx_enqueue_login_theme() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	wp_enqueue_style(
		'blueworx-admin-fonts',
		BLUEWORX_LABS_URL . 'assets/css/blueworx-fonts.css',
		array(),
		blueworx_get_admin_asset_version( 'assets/css/blueworx-fonts.css' )
	);

	wp_enqueue_style(
		'blueworx-login-theme',
		BLUEWORX_LABS_URL . 'assets/css/login-theme.css',
		array( 'blueworx-admin-fonts' ),
		blueworx_get_admin_asset_version( 'assets/css/login-theme.css' )
	);
}
add_action( 'login_enqueue_scripts', 'blueworx_enqueue_login_theme' );
