<?php
/**
 * Headless REST layer — CORS for the blueworx/v1 namespace.
 *
 * Credentialed CORS: the exact allowed origin is echoed (never a wildcard) so
 * the refresh cookie can be sent. Origins come from the settings list or the
 * BLUEWORX_LABS_ALLOWED_ORIGINS constant override.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the configured list of allowed origins (normalised, no trailing /).
 *
 * @return array Allowed origins.
 */
function blueworx_headless_allowed_origins() {
	if ( defined( 'BLUEWORX_LABS_ALLOWED_ORIGINS' ) && '' !== (string) BLUEWORX_LABS_ALLOWED_ORIGINS ) {
		$raw = (string) BLUEWORX_LABS_ALLOWED_ORIGINS;
	} else {
		$raw = (string) blueworx_headless_setting( 'allowed_origins' );
	}

	$origins = array_filter( array_map( 'trim', preg_split( '/[\s,]+/', $raw ) ) );

	return array_map( 'untrailingslashit', $origins );
}

/**
 * Whether an origin is allowed (case-insensitive on scheme + host).
 *
 * @param string $origin Origin header value.
 * @return bool True when allowed.
 */
function blueworx_headless_origin_allowed( $origin ) {
	$origin = strtolower( untrailingslashit( $origin ) );

	foreach ( blueworx_headless_allowed_origins() as $allowed ) {
		if ( strtolower( $allowed ) === $origin ) {
			return true;
		}
	}

	return false;
}

/**
 * Sends credentialed CORS headers for allowed origins on blueworx/v1 requests.
 *
 * @param bool             $served  Whether the request has been served.
 * @param WP_HTTP_Response $result  Result to send.
 * @param WP_REST_Request  $request Request.
 * @return bool The unchanged $served flag.
 */
function blueworx_headless_send_cors_headers( $served, $result, $request ) {
	if ( 0 !== strpos( ltrim( (string) $request->get_route(), '/' ), 'blueworx/v1' ) ) {
		return $served;
	}

	$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

	if ( '' === $origin || ! blueworx_headless_origin_allowed( $origin ) ) {
		return $served;
	}

	header( 'Access-Control-Allow-Origin: ' . $origin );
	header( 'Access-Control-Allow-Credentials: true' );
	header( 'Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS' );
	header( 'Access-Control-Allow-Headers: Authorization, Content-Type' );
	header( 'Access-Control-Max-Age: 600' );
	header( 'Vary: Origin', false );

	return $served;
}

/**
 * Hooks CORS headers into REST serving.
 *
 * @return void
 */
function blueworx_headless_init_cors() {
	add_filter( 'rest_pre_serve_request', 'blueworx_headless_send_cors_headers', 10, 3 );
}
add_action( 'rest_api_init', 'blueworx_headless_init_cors', 15 );
