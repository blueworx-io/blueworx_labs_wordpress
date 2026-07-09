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
require_once BLUEWORX_LABS_PATH . 'includes/rest/cors.php';
require_once BLUEWORX_LABS_PATH . 'includes/rest/revalidate.php';
require_once BLUEWORX_LABS_PATH . 'includes/rest/surecart.php';
require_once BLUEWORX_LABS_PATH . 'includes/rest/settings.php';

/**
 * Authenticates a request from a JWT bearer token (authentication only).
 *
 * Runs only when no user has already been resolved and a bearer token is
 * present. Any validation failure silently leaves the request anonymous so
 * public routes and cookie-authenticated core REST keep working.
 *
 * @param int|false $user_id Current user ID as resolved so far.
 * @return int|false Resolved user ID.
 */
function blueworx_headless_determine_current_user( $user_id ) {
	if ( ! empty( $user_id ) ) {
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
	blueworx_headless_register_surecart_routes();
}
add_action( 'rest_api_init', 'blueworx_headless_register_routes' );
