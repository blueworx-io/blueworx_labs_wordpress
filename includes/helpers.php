<?php
/**
 * Shared helper functions.
 *
 * @package BlueWorxLabs
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

/**
 * Refuses a state-changing admin request that did not arrive as a POST.
 *
 * check_admin_referer() reads $_REQUEST, so a nonce presented in the query
 * string satisfies it just as well as one posted in a form body. Without a
 * method check an admin_post handler therefore runs happily on a plain GET —
 * every $_POST read comes back empty, and the handler writes those empty
 * values straight over real settings. Anyone who can load the screen the nonce
 * is printed on can scrape it and follow the link.
 *
 * Every handler registered on admin_post_* changes state, so every one of them
 * calls this first.
 *
 * @return void
 */
function blueworx_require_post_request() {
	$method = isset( $_SERVER['REQUEST_METHOD'] )
		? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
		: 'GET';

	if ( 'POST' === $method ) {
		return;
	}

	wp_die(
		esc_html__( 'This action must be submitted as a form post.', 'blueworx-labs-wordpress' ),
		esc_html__( 'BlueWorx', 'blueworx-labs-wordpress' ),
		array( 'response' => 403 )
	);
}
