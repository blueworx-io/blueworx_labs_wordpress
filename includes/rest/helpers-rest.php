<?php
/**
 * Headless REST layer — shared helpers.
 *
 * Settings access, client-IP resolution, error envelope, bearer-token parsing,
 * and refresh-cookie handling. All settings live under the `blueworx_headless_`
 * option prefix; secrets come only from wp-config constants.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The REST namespace for every headless route.
 */
if ( ! defined( 'BLUEWORX_HEADLESS_NAMESPACE' ) ) {
	define( 'BLUEWORX_HEADLESS_NAMESPACE', 'blueworx/v1' );
}

/**
 * Returns the default settings for the headless layer.
 *
 * @return array Default settings keyed by option suffix.
 */
function blueworx_headless_default_settings() {
	return array(
		'registration_mode'           => 'closed',
		'email_verification_required' => '1',
		'default_role'                => 'subscriber',
		'access_ttl'                  => 3600,
		'refresh_ttl_days'            => 14,
		'login_max_attempts'          => 5,
		'login_window'                => 15 * MINUTE_IN_SECONDS,
		'login_lockout'               => 15 * MINUTE_IN_SECONDS,
		'allowed_origins'             => '',
		'frontend_url'                => '',
		'revalidate_enabled'          => '0',
		'revalidate_url'              => '',
		'surecart_enabled'            => '0',
		'cpts'                        => '',
		// Empty means the render endpoint refuses everything. do_shortcode() on
		// arbitrary public input would be remote code execution by proxy.
		'render_shortcodes'           => '',
	);
}

/**
 * Reads a headless setting, falling back to its documented default.
 *
 * @param string $key Option suffix (without the `blueworx_headless_` prefix).
 * @return mixed The stored value or the default.
 */
function blueworx_headless_setting( $key ) {
	$defaults = blueworx_headless_default_settings();
	$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';

	return get_option( 'blueworx_headless_' . $key, $default );
}

/**
 * Returns the configured access-token lifetime in seconds.
 *
 * @return int Seconds.
 */
function blueworx_headless_access_ttl() {
	$ttl = (int) blueworx_headless_setting( 'access_ttl' );

	if ( $ttl < 60 ) {
		$ttl = 3600;
	}

	/**
	 * Filters the access-token lifetime (seconds).
	 *
	 * @param int $ttl Lifetime in seconds.
	 */
	return (int) apply_filters( 'blueworx_headless_access_ttl', $ttl );
}

/**
 * Returns the configured refresh-token lifetime in seconds.
 *
 * @return int Seconds.
 */
function blueworx_headless_refresh_ttl() {
	$days = (int) blueworx_headless_setting( 'refresh_ttl_days' );

	if ( $days < 1 ) {
		$days = 14;
	}

	/**
	 * Filters the refresh-token lifetime (seconds).
	 *
	 * @param int $ttl Lifetime in seconds.
	 */
	return (int) apply_filters( 'blueworx_headless_refresh_ttl', $days * DAY_IN_SECONDS );
}

/**
 * Returns the JWT signing secret from wp-config, or an empty string.
 *
 * @return string Secret, or '' when the constant is undefined.
 */
function blueworx_headless_jwt_secret() {
	return defined( 'BLUEWORX_LABS_JWT_SECRET' ) ? (string) BLUEWORX_LABS_JWT_SECRET : '';
}

/**
 * Whether the auth core is configured (JWT secret present).
 *
 * @return bool True when a signing secret is available.
 */
function blueworx_headless_auth_ready() {
	return '' !== blueworx_headless_jwt_secret();
}

/**
 * Resolves the client IP address, filterable for proxy/CDN setups.
 *
 * Defaults to REMOTE_ADDR only (the sole value a client cannot spoof). Sites
 * behind Cloudflare/Cloudways can trust a forwarded header via the filter.
 *
 * @return string Client IP, or '' when unavailable.
 */
function blueworx_headless_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	/**
	 * Filters the resolved client IP address.
	 *
	 * @param string $ip The IP derived from REMOTE_ADDR.
	 */
	return (string) apply_filters( 'blueworx_headless_client_ip', $ip );
}

/**
 * Builds a standard error response.
 *
 * @param string $code    Machine error code.
 * @param string $message Human-readable message.
 * @param int    $status  HTTP status.
 * @return WP_Error Error carrying the HTTP status.
 */
function blueworx_headless_error( $code, $message, $status = 400 ) {
	return new WP_Error( $code, $message, array( 'status' => $status ) );
}

/**
 * Extracts a bearer token from the Authorization header.
 *
 * @return string The token, or '' when absent/malformed.
 */
function blueworx_headless_get_bearer_token() {
	$header = '';

	if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
		$header = wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	} elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
		$header = wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	} elseif ( function_exists( 'getallheaders' ) ) {
		$headers = getallheaders();

		if ( isset( $headers['Authorization'] ) ) {
			$header = $headers['Authorization'];
		} elseif ( isset( $headers['authorization'] ) ) {
			$header = $headers['authorization'];
		}
	}

	$header = trim( sanitize_text_field( (string) $header ) );

	if ( 0 === stripos( $header, 'Bearer ' ) ) {
		return trim( substr( $header, 7 ) );
	}

	return '';
}

/**
 * Returns the refresh-token cookie name.
 *
 * @return string Cookie name.
 */
function blueworx_headless_refresh_cookie_name() {
	return 'blueworx_headless_refresh';
}

/**
 * Sets the HttpOnly refresh-token cookie.
 *
 * @param string $token   Opaque refresh token.
 * @param int    $expires Absolute expiry timestamp.
 * @return void
 */
function blueworx_headless_set_refresh_cookie( $token, $expires ) {
	$path = wp_parse_url( rest_url( BLUEWORX_HEADLESS_NAMESPACE . '/auth/' ), PHP_URL_PATH );

	setcookie(
		blueworx_headless_refresh_cookie_name(),
		$token,
		array(
			'expires'  => $expires,
			'path'     => $path ? $path : '/',
			'secure'   => true,
			'httponly' => true,
			'samesite' => 'None',
		)
	);
}

/**
 * Clears the refresh-token cookie.
 *
 * @return void
 */
function blueworx_headless_clear_refresh_cookie() {
	$path = wp_parse_url( rest_url( BLUEWORX_HEADLESS_NAMESPACE . '/auth/' ), PHP_URL_PATH );

	setcookie(
		blueworx_headless_refresh_cookie_name(),
		'',
		array(
			'expires'  => time() - HOUR_IN_SECONDS,
			'path'     => $path ? $path : '/',
			'secure'   => true,
			'httponly' => true,
			'samesite' => 'None',
		)
	);
}

/**
 * Reads the refresh token from the request cookie.
 *
 * @return string The token, or '' when absent.
 */
function blueworx_headless_get_refresh_cookie() {
	$name = blueworx_headless_refresh_cookie_name();

	if ( isset( $_COOKIE[ $name ] ) ) {
		return sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
	}

	return '';
}

/**
 * Returns the configured headless frontend base URL (for email links),
 * falling back to the site home URL.
 *
 * @return string Frontend base URL without a trailing slash.
 */
function blueworx_headless_frontend_url() {
	$url = trim( (string) blueworx_headless_setting( 'frontend_url' ) );

	if ( '' === $url ) {
		$url = home_url();
	}

	return untrailingslashit( $url );
}

/**
 * Serialises a WP_User into the public account shape.
 *
 * @param WP_User $user User object.
 * @return array Account payload.
 */
function blueworx_headless_user_payload( $user ) {
	return array(
		'id'           => (int) $user->ID,
		'email'        => $user->user_email,
		'username'     => $user->user_login,
		'display_name' => $user->display_name,
		'first_name'   => $user->first_name,
		'last_name'    => $user->last_name,
		'roles'        => array_values( $user->roles ),
	);
}
