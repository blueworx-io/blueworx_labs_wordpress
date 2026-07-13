<?php
/**
 * Page excerpt support.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enables excerpts for pages.
 *
 * @return void
 */
function blueworx_enable_page_excerpts() {
	add_post_type_support( 'page', 'excerpt' );
}
if ( blueworx_feature_enabled( 'page_excerpts' ) ) {
	add_action( 'init', 'blueworx_enable_page_excerpts' );
}
