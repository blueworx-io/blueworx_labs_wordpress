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
 * REST namespaces this plugin sends CORS headers for.
 *
 * wp/v2 is included because the headless front-end fetches content bodies from
 * it — /resolve hands back a wp/v2 rest_url. Anything outside this list gets no
 * CORS headers at all, since core's permissive handler is removed below.
 *
 * @return array Namespace prefixes.
 */
function blueworx_headless_cors_namespaces() {
	return (array) apply_filters(
		'blueworx_headless_cors_namespaces',
		array( 'blueworx/v1', 'wp/v2' )
	);
}

/**
 * Whether a route falls within a namespace we handle CORS for.
 *
 * @param string $route REST route.
 * @return bool True when in scope.
 */
function blueworx_headless_route_in_cors_scope( $route ) {
	$route = ltrim( (string) $route, '/' );

	foreach ( blueworx_headless_cors_namespaces() as $namespace ) {
		if ( 0 === strpos( $route, ltrim( (string) $namespace, '/' ) ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Sends credentialed CORS headers, and only for allowed origins.
 *
 * @param bool             $served  Whether the request has been served.
 * @param WP_HTTP_Response $result  Result to send.
 * @param WP_REST_Request  $request Request.
 * @return bool The unchanged $served flag.
 */
function blueworx_headless_send_cors_headers( $served, $result, $request ) {
	// Sent whether or not the origin is allowed. A shared cache must never hand
	// one origin's response to another, and that is decided by this header, not
	// by whether the request happened to be permitted.
	header( 'Vary: Origin', false );

	if ( ! blueworx_headless_route_in_cors_scope( $request->get_route() ) ) {
		return $served;
	}

	$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

	// Fail closed. An empty allowlist denies every cross-origin caller rather
	// than defaulting to "anyone", which is what core does.
	if ( '' === $origin || ! blueworx_headless_origin_allowed( $origin ) ) {
		return $served;
	}

	header( 'Access-Control-Allow-Origin: ' . $origin );
	header( 'Access-Control-Allow-Credentials: true' );
	header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
	header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
	header( 'Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, Link' );
	header( 'Access-Control-Max-Age: 600' );

	return $served;
}

/**
 * Hooks CORS headers into REST serving, replacing core's.
 *
 * Core's rest_send_cors_headers echoes ANY Origin back with
 * Access-Control-Allow-Credentials: true. Left in place it runs first and
 * grants what the allowlist below then declines to grant — so the allowlist
 * reads as a security control while enforcing nothing. It has to be removed,
 * not merely supplemented.
 *
 * Runs at priority 15 so core's rest_api_default_filters() (priority 10) has
 * already registered the callback being removed.
 *
 * @return void
 */
function blueworx_headless_init_cors() {
	remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers', 10 );
	add_filter( 'rest_pre_serve_request', 'blueworx_headless_send_cors_headers', 10, 3 );
}
add_action( 'rest_api_init', 'blueworx_headless_init_cors', 15 );
