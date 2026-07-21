<?php
/**
 * Public front-end layer — asset enqueueing.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues public styles on plugin-owned pages only.
 *
 * Scoped deliberately: these styles carry a reset, so loading them on a page
 * the plugin does not own would restyle someone else's content.
 *
 * @return void
 */
function blueworx_enqueue_public_assets() {
	if ( ! blueworx_public_is_owned_page() ) {
		return;
	}

	wp_enqueue_style(
		'blueworx-fonts',
		BLUEWORX_LABS_URL . 'assets/css/blueworx-fonts.css',
		array(),
		blueworx_get_admin_asset_version( 'assets/css/blueworx-fonts.css' )
	);

	wp_enqueue_style(
		'blueworx-public',
		BLUEWORX_LABS_URL . 'assets/css/public.css',
		array( 'blueworx-fonts' ),
		blueworx_get_admin_asset_version( 'assets/css/public.css' )
	);

	wp_enqueue_script(
		'blueworx-public-nav',
		BLUEWORX_LABS_URL . 'assets/js/public-nav.js',
		array(),
		blueworx_get_admin_asset_version( 'assets/js/public-nav.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'blueworx_enqueue_public_assets' );
