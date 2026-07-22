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

/**
 * Dequeues the active theme's own front-end stylesheet on plugin-owned
 * pages, so a theme with bare-element or `!important` rules cannot visibly
 * change a page this plugin renders even though the `.bw-page` DOM never
 * changes.
 *
 * Deliberately narrow: only the theme's own stylesheet handle(s) are
 * removed — get_stylesheet() . '-style' (and, for a child theme,
 * get_template() . '-style' for the parent) is the convention every default
 * WordPress theme since Twenty Ten uses for its main style.css, and it is
 * what a from-scratch block theme like Twenty Twenty-Five/Twenty
 * Twenty-Four registers too. Nothing else is touched: nothing enqueued by
 * OTHER plugins is dequeued, so their front-end CSS still loads on a page
 * that genuinely needs it — only the theme's own visual styling is removed.
 * Hooked late (priority 100) so it runs after the theme itself (and other
 * plugins) have had a chance to enqueue on the normal wp_enqueue_scripts
 * priority.
 *
 * @return void
 */
function blueworx_public_dequeue_theme_styles() {
	if ( ! blueworx_public_is_owned_page() ) {
		return;
	}

	$handles = array_unique(
		array(
			get_stylesheet() . '-style',
			get_template() . '-style',
		)
	);

	foreach ( $handles as $handle ) {
		wp_dequeue_style( $handle );
		wp_deregister_style( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'blueworx_public_dequeue_theme_styles', 100 );
