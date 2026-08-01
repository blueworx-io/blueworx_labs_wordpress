<?php
/**
 * Headless REST layer — bootstrap.
 *
 * Loads the REST modules, authenticates bearer tokens via determine_current_user,
 * and registers every route under the blueworx/v1 namespace.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BLUEWORX_LABS_PATH . 'includes/rest/install.php';
require_once BLUEWORX_LABS_PATH . 'includes/rest/helpers-rest.php';
require_once BLUEWORX_LABS_PATH . 'includes/rest/jwt.php';
require_once BLUEWORX_LABS_PATH . 'includes/rest/tokens.php';
require_once BLUEWORX_LABS_PATH . 'includes/rest/rate-limit.php';
require_once BLUEWORX_LABS_PATH . 'includes/rest/auth.php';
require_once BLUEWORX_LABS_PATH . 'includes/rest/account.php';
require_once BLUEWORX_LABS_PATH . 'includes/rest/content.php';
require_once BLUEWORX_LABS_PATH . 'includes/rest/render.php';
require_once BLUEWORX_LABS_PATH . 'includes/rest/cors.php';
require_once BLUEWORX_LABS_PATH . 'includes/rest/revalidate.php';
require_once BLUEWORX_LABS_PATH . 'includes/rest/surecart.php';
require_once BLUEWORX_LABS_PATH . 'includes/rest/settings.php';

/**
 * Whether a REST route belongs to the headless namespace.
 *
 * @param string $route Route path, with or without a leading slash.
 * @return bool True when the route is ours.
 */
function blueworx_headless_route_is_ours( $route ) {
	$route     = ltrim( (string) $route, '/' );
	$namespace = BLUEWORX_HEADLESS_NAMESPACE;

	return $route === $namespace || 0 === strpos( $route, $namespace . '/' );
}

/**
 * Whether the current request targets the headless namespace.
 *
 * `determine_current_user` runs long before the REST server parses a route, so
 * there is nothing to ask — the request URL is the only thing available, and
 * both permalink shapes have to be read: `?rest_route=/blueworx/v1/…` when the
 * query arg is used, and `/wp-json/blueworx/v1/…` otherwise. The prefix is read
 * from rest_get_url_prefix() rather than hard-coded, because sites do filter it.
 *
 * Anything that is not recognisably one of ours answers false, so an unexpected
 * URL shape fails closed — no token, no grant.
 *
 * @return bool True when the request is for a blueworx/v1 route.
 */
function blueworx_headless_request_is_ours() {
	// Reading the route the request is addressed to, not accepting input: there
	// is no state change here and a nonce could not exist this early.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['rest_route'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return blueworx_headless_route_is_ours( sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ) );
	}

	$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

	if ( '' === $uri ) {
		return false;
	}

	$path   = (string) wp_parse_url( $uri, PHP_URL_PATH );
	$prefix = '/' . trim( rest_get_url_prefix(), '/' ) . '/';
	$at     = strpos( $path, $prefix );

	if ( false === $at ) {
		return false;
	}

	return blueworx_headless_route_is_ours( substr( $path, $at + strlen( $prefix ) ) );
}

/**
 * Authenticates a request from a JWT bearer token (authentication only).
 *
 * Runs only when no user has already been resolved, the request is for a route
 * in our own namespace, and a bearer token is present. Any validation failure
 * silently leaves the request anonymous so public routes and cookie-
 * authenticated core REST keep working.
 *
 * The namespace check is the point: this filter is consulted for EVERY request
 * WordPress serves, so without it a token minted for the headless front end
 * authenticates the whole of core `wp/v2` too — an administrator token could
 * read and rewrite `wp/v2/settings`. The headless contract never needs that.
 *
 * @param int|false $user_id Current user ID as resolved so far.
 * @return int|false Resolved user ID.
 */
function blueworx_headless_determine_current_user( $user_id ) {
	if ( ! empty( $user_id ) ) {
		return $user_id;
	}

	if ( ! blueworx_headless_request_is_ours() ) {
		return $user_id;
	}

	$token = blueworx_headless_get_bearer_token();

	if ( '' === $token ) {
		return $user_id;
	}

	$claims = blueworx_headless_jwt_decode( $token );

	if ( null === $claims || empty( $claims['sub'] ) ) {
		return $user_id;
	}

	$claimed_user = (int) $claims['sub'];
	$claimed_tv   = isset( $claims['tv'] ) ? (int) $claims['tv'] : -1;

	if ( 0 >= $claimed_user || blueworx_headless_token_version( $claimed_user ) !== $claimed_tv ) {
		return $user_id;
	}

	return $claimed_user;
}
add_filter( 'determine_current_user', 'blueworx_headless_determine_current_user', 20 );

/**
 * Registers all headless REST routes.
 *
 * @return void
 */
function blueworx_headless_register_routes() {
	blueworx_headless_register_auth_routes();
	blueworx_headless_register_account_routes();
	blueworx_headless_register_content_routes();
	blueworx_headless_register_render_routes();
	blueworx_headless_register_surecart_routes();
}
add_action( 'rest_api_init', 'blueworx_headless_register_routes' );
