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
	// TODO(Task 3): replace with blueworx_public_is_owned_page() once page
	// routing exists — is_front_page() is a stand-in so this hook has
	// something narrower than "every front-end page" to gate on until then.
	if ( ! is_front_page() ) {
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
}
add_action( 'wp_enqueue_scripts', 'blueworx_enqueue_public_assets' );
