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
add_action( 'init', 'blueworx_enable_page_excerpts' );
