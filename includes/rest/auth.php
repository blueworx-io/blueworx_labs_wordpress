<?php
/**
 * Headless REST layer — authentication endpoints (/auth/*).
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the /auth routes.
 *
 * @return void
 */
function blueworx_headless_register_auth_routes() {
	$ns = BLUEWORX_HEADLESS_NAMESPACE;

	register_rest_route(
		$ns,
		'/auth/login',
		array(
			'methods'             => 'POST',
			'callback'            => 'blueworx_headless_route_login',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/auth/refresh',
		array(
			'methods'             => 'POST',
			'callback'            => 'blueworx_headless_route_refresh',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/auth/logout',
		array(
			'methods'             => 'POST',
			'callback'            => 'blueworx_headless_route_logout',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/auth/logout-all',
		array(
			'methods'             => 'POST',
			'callback'            => 'blueworx_headless_route_logout_all',
			'permission_callback' => 'blueworx_headless_require_auth',
		)
	);

	register_rest_route(
		$ns,
		'/auth/me',
		array(
			'methods'             => 'GET',
			'callback'            => 'blueworx_headless_route_me',
			'permission_callback' => 'blueworx_headless_require_auth',
		)
	);
}

/**
 * Permission callback: requires an authenticated user.
 *
 * @return bool|WP_Error True when logged in, error otherwise.
 */
function blueworx_headless_require_auth() {
	if ( is_user_logged_in() ) {
		return true;
	}

	return blueworx_headless_error( 'blueworx_unauthorized', __( 'Authentication required.', 'blueworx-project-wordpress-labs' ), 401 );
}

/**
 * POST /auth/login — validate credentials, issue tokens.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_route_login( WP_REST_Request $request ) {
	if ( ! blueworx_headless_auth_ready() ) {
		return blueworx_headless_error( 'blueworx_auth_unconfigured', __( 'Authentication is not configured on this site.', 'blueworx-project-wordpress-labs' ), 503 );
	}

	$max    = (int) blueworx_headless_setting( 'login_max_attempts' );
	$window = (int) blueworx_headless_setting( 'login_window' );

	$blocked = blueworx_headless_rl_blocked( 'login', $max );
	if ( $blocked > 0 ) {
		return blueworx_headless_rl_error( $blocked );
	}

	$login    = trim( (string) $request->get_param( 'login' ) );
	$password = (string) $request->get_param( 'password' );

	if ( '' === $login || '' === $password ) {
		return blueworx_headless_error( 'blueworx_missing_credentials', __( 'A username/email and password are required.', 'blueworx-project-wordpress-labs' ), 400 );
	}

	$user = wp_authenticate( $login, $password );

	if ( is_wp_error( $user ) ) {
		$retry = blueworx_headless_rl_hit( 'login', $max, $window );

		if ( $retry > 0 ) {
			return blueworx_headless_rl_error( $retry );
		}

		return blueworx_headless_error( 'blueworx_invalid_login', __( 'Invalid username/email or password.', 'blueworx-project-wordpress-labs' ), 401 );
	}

	// Optionally block unverified accounts (set during registration).
	if ( '1' === get_user_meta( $user->ID, 'blueworx_headless_email_unverified', true ) ) {
		return blueworx_headless_error( 'blueworx_email_unverified', __( 'Please confirm your email address before signing in.', 'blueworx-project-wordpress-labs' ), 403 );
	}

	blueworx_headless_rl_clear( 'login' );

	return blueworx_headless_issue_session( $user );
}

/**
 * Issues an access token + refresh cookie for a user and builds the response.
 *
 * @param WP_User $user User.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_issue_session( $user ) {
	$access = blueworx_headless_issue_access_token( $user->ID );

	if ( null === $access ) {
		return blueworx_headless_error( 'blueworx_token_failed', __( 'Could not issue an access token.', 'blueworx-project-wordpress-labs' ), 500 );
	}

	$refresh = blueworx_headless_issue_refresh_token( $user->ID );

	if ( null === $refresh ) {
		return blueworx_headless_error( 'blueworx_session_failed', __( 'Could not start a session.', 'blueworx-project-wordpress-labs' ), 500 );
	}

	blueworx_headless_set_refresh_cookie( $refresh['token'], $refresh['expires'] );

	return new WP_REST_Response(
		array(
			'access_token' => $access,
			'token_type'   => 'Bearer',
			'expires_in'   => blueworx_headless_access_ttl(),
			'user'         => blueworx_headless_user_payload( $user ),
		),
		200
	);
}

/**
 * POST /auth/refresh — rotate the refresh family, issue a new access token.
 *
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_route_refresh() {
	$raw    = blueworx_headless_get_refresh_cookie();
	$result = blueworx_headless_rotate_refresh_token( $raw );

	if ( is_wp_error( $result ) ) {
		blueworx_headless_clear_refresh_cookie();

		return $result;
	}

	$user = get_user_by( 'id', $result['user_id'] );

	if ( ! $user ) {
		blueworx_headless_clear_refresh_cookie();

		return blueworx_headless_error( 'blueworx_invalid_refresh', __( 'Session is no longer valid.', 'blueworx-project-wordpress-labs' ), 401 );
	}

	$access = blueworx_headless_issue_access_token( $user->ID );

	if ( null === $access ) {
		return blueworx_headless_error( 'blueworx_token_failed', __( 'Could not issue an access token.', 'blueworx-project-wordpress-labs' ), 500 );
	}

	blueworx_headless_set_refresh_cookie( $result['token'], $result['expires'] );

	return new WP_REST_Response(
		array(
			'access_token' => $access,
			'token_type'   => 'Bearer',
			'expires_in'   => blueworx_headless_access_ttl(),
		),
		200
	);
}

/**
 * POST /auth/logout — revoke the current refresh family, clear the cookie.
 *
 * @return WP_REST_Response Response.
 */
function blueworx_headless_route_logout() {
	$raw = blueworx_headless_get_refresh_cookie();

	if ( '' !== $raw ) {
		$row = blueworx_headless_find_refresh_token( $raw );

		if ( $row ) {
			blueworx_headless_revoke_family( $row->family_id );
		}
	}

	blueworx_headless_clear_refresh_cookie();

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * POST /auth/logout-all — bump token version and revoke every session.
 *
 * @return WP_REST_Response Response.
 */
function blueworx_headless_route_logout_all() {
	$user_id = get_current_user_id();

	blueworx_headless_revoke_user_tokens( $user_id );
	blueworx_headless_bump_token_version( $user_id );
	blueworx_headless_clear_refresh_cookie();

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * GET /auth/me — return the current user.
 *
 * @return WP_REST_Response Response.
 */
function blueworx_headless_route_me() {
	$user = wp_get_current_user();

	$payload                 = blueworx_headless_user_payload( $user );
	$payload['capabilities'] = array_keys( array_filter( (array) $user->allcaps ) );

	return new WP_REST_Response( $payload, 200 );
}
