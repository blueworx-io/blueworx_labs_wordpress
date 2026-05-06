<?php
/**
 * Shared helper functions.
 *
 * @package BlueWorxEnhancements
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirects the visitor to the site homepage and halts execution.
 *
 * @return void
 */
function blueworx_redirect_home() {
	wp_safe_redirect( home_url( '/' ), 301 );
	exit;
}
