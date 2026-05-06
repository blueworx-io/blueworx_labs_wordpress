<?php
/**
 * Custom login URL and default login blocking.
 *
 * @package BlueWorxEnhancements
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Intercepts incoming requests early in the WordPress bootstrap.
 *
 * @return void
 */
function blueworx_intercept_requests() {
	if (
		( defined( 'DOING_AJAX' ) && DOING_AJAX ) ||
		( defined( 'DOING_CRON' ) && DOING_CRON ) ||
		( defined( 'WP_CLI' ) && WP_CLI ) ||
		( defined( 'REST_REQUEST' ) && REST_REQUEST )
	) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path        = strtolower( wp_parse_url( sanitize_text_field( $request_uri ), PHP_URL_PATH ) );
	$path        = '/' . trim( $path, '/' );

	if ( $path === '/' . BLUEWORX_CUSTOM_LOGIN_SLUG ) {
		$action          = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$allowed_actions = array( 'logout', 'lostpassword', 'rp', 'resetpass', 'confirmaction', 'postpass' );
		$is_post         = ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] );
		$is_allowed      = $is_post || in_array( $action, $allowed_actions, true ) || '' === $action;

		if ( $is_allowed ) {
			$_SERVER['SCRIPT_FILENAME'] = ABSPATH . 'wp-login.php';
			$_SERVER['PHP_SELF']        = '/wp-login.php';
			$_SERVER['SCRIPT_NAME']     = '/wp-login.php';
			require_once ABSPATH . 'wp-login.php'; // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude.FileIncludeFound
			exit;
		}
	}

	$script_name  = isset( $_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : '';
	$is_login_php = (
		false !== strpos( $path, '/wp-login.php' ) ||
		false !== strpos( $script_name, 'wp-login.php' )
	);

	if ( $is_login_php ) {
		blueworx_redirect_home();
	}

	if ( 0 === strpos( $path, '/wp-admin' ) && ! is_user_logged_in() ) {
		blueworx_redirect_home();
	}
}
add_action( 'init', 'blueworx_intercept_requests', 1 );

/**
 * Replaces the default wp-login.php login URL with the custom slug URL.
 *
 * @param string $login_url    The original login URL.
 * @param string $redirect     URL to redirect to after login.
 * @param bool   $force_reauth Whether to force re-authentication.
 * @return string The filtered login URL.
 */
function blueworx_custom_login_url( $login_url, $redirect, $force_reauth ) {
	$custom = home_url( '/' . BLUEWORX_CUSTOM_LOGIN_SLUG . '/' );

	if ( $redirect ) {
		$custom = add_query_arg( 'redirect_to', $redirect, $custom );
	}
	if ( $force_reauth ) {
		$custom = add_query_arg( 'reauth', '1', $custom );
	}

	return $custom;
}
add_filter( 'login_url', 'blueworx_custom_login_url', 10, 3 );

/**
 * Replaces generated wp-login.php URLs with the custom login slug.
 *
 * @param string $url  The generated URL.
 * @param string $path The requested path.
 * @return string The filtered URL.
 */
function blueworx_replace_generated_login_url( $url, $path ) {
	$url  = (string) $url;
	$path = (string) $path;

	if ( false === strpos( $path, 'wp-login.php' ) && false === strpos( $url, 'wp-login.php' ) ) {
		return $url;
	}

	$query  = wp_parse_url( $url, PHP_URL_QUERY );
	$custom = home_url( '/' . BLUEWORX_CUSTOM_LOGIN_SLUG . '/' );

	if ( $query ) {
		$custom .= '?' . $query;
	}

	return $custom;
}
add_filter( 'site_url', 'blueworx_replace_generated_login_url', 10, 2 );
add_filter( 'network_site_url', 'blueworx_replace_generated_login_url', 10, 2 );

/**
 * Secondary safeguard to block direct wp-login.php access.
 *
 * @return void
 */
function blueworx_template_redirect_guard() {
	global $pagenow;

	if ( 'wp-login.php' !== $pagenow ) {
		return;
	}

	blueworx_redirect_home();
}
add_action( 'template_redirect', 'blueworx_template_redirect_guard' );
