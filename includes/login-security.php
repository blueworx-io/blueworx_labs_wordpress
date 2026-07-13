<?php
/**
 * Custom login URL and default login blocking.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes a custom login slug, falling back to the default when unusable.
 *
 * @param string $raw Raw slug input.
 * @return string A safe, non-reserved slug.
 */
function blueworx_sanitize_login_slug( $raw ) {
	$slug     = sanitize_title( (string) $raw );
	$reserved = array( 'wp-admin', 'wp-login', 'admin', 'wp-content', 'wp-includes' );

	if ( '' === $slug || in_array( $slug, $reserved, true ) ) {
		return 'admin_login';
	}

	return $slug;
}

/**
 * Gets the active custom login slug.
 *
 * @return string The configured slug, or the default when unset.
 */
function blueworx_login_slug() {
	return blueworx_sanitize_login_slug( get_option( 'blueworx_login_slug', BLUEWORX_CUSTOM_LOGIN_SLUG ) );
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

	$login_on    = blueworx_feature_enabled( 'login' );
	$sp_backend  = blueworx_feature_enabled( 'site_protection' ) && blueworx_site_protection_is_enabled( 'backend' );
	$sp_frontend = blueworx_feature_enabled( 'site_protection' ) && blueworx_site_protection_is_enabled( 'frontend' );

	if ( $login_on && blueworx_is_custom_login_request_path( $path ) ) {
		$_SERVER['SCRIPT_FILENAME'] = ABSPATH . 'wp-login.php';
		$_SERVER['PHP_SELF']        = '/wp-login.php';
		$_SERVER['SCRIPT_NAME']     = '/wp-login.php';
		require_once ABSPATH . 'wp-login.php'; // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude.FileIncludeFound
		exit;
	}

	$script_name  = isset( $_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : '';
	$is_login_php = (
		false !== strpos( $path, '/wp-login.php' ) ||
		false !== strpos( $script_name, 'wp-login.php' )
	);

	if ( $login_on && $is_login_php ) {
		blueworx_redirect_home();
	}

	if ( 0 === strpos( $path, '/wp-admin' ) ) {
		if ( ! is_user_logged_in() ) {
			if ( $sp_backend ) {
				blueworx_site_protection_die( __( 'Please log in to view this site.', 'blueworx-labs-wordpress' ) );
			}

			if ( $login_on ) {
				blueworx_redirect_home();
			}

			return;
		}

		if ( $sp_backend && ! blueworx_current_user_has_site_protection_role( 'backend' ) ) {
			blueworx_site_protection_die( __( 'You do not have access to view this area.', 'blueworx-labs-wordpress' ) );
		}

		return;
	}

	if ( $sp_frontend ) {
		if ( ! is_user_logged_in() ) {
			blueworx_site_protection_die( __( 'Please log in to view this site.', 'blueworx-labs-wordpress' ) );
		}

		if ( ! blueworx_current_user_has_site_protection_role( 'frontend' ) ) {
			blueworx_site_protection_die( __( 'You do not have access to view this area.', 'blueworx-labs-wordpress' ) );
		}
	}
}

if ( blueworx_feature_enabled( 'login' ) || blueworx_feature_enabled( 'site_protection' ) ) {
	add_action( 'init', 'blueworx_intercept_requests', 1 );
}

/**
 * Checks whether site protection is enabled for an area.
 *
 * @param string $area Protected area.
 * @return bool True when enabled.
 */
function blueworx_site_protection_is_enabled( $area ) {
	return '1' === get_option( 'blueworx_' . $area . '_protection_enabled', '0' );
}

/**
 * Gets selected roles for a protected area.
 *
 * @param string $area Protected area.
 * @return array Role slugs.
 */
function blueworx_get_site_protection_allowed_roles( $area ) {
	$roles = get_option( 'blueworx_' . $area . '_protection_roles', array() );

	if ( ! is_array( $roles ) ) {
		return array();
	}

	return array_values( array_unique( array_filter( array_map( 'sanitize_key', $roles ) ) ) );
}

/**
 * Checks whether the current user has one of the selected roles.
 *
 * @param string $area Protected area.
 * @return bool True when allowed.
 */
function blueworx_current_user_has_site_protection_role( $area ) {
	$user = wp_get_current_user();

	if ( ! $user || empty( $user->roles ) ) {
		return false;
	}

	return (bool) array_intersect( blueworx_get_site_protection_allowed_roles( $area ), (array) $user->roles );
}

/**
 * Shows a plain browser message for blocked users.
 *
 * @param string $message Message to show.
 * @return void
 */
function blueworx_site_protection_die( $message ) {
	wp_die( esc_html( $message ), esc_html__( 'Site Protection', 'blueworx-labs-wordpress' ), array( 'response' => 403 ) );
}

/**
 * Checks whether the current path is one of the plugin's custom login paths.
 *
 * @param string $path Normalized request path.
 * @return bool True when this is the custom login URL.
 */
function blueworx_is_custom_login_request_path( $path ) {
	$path      = '/' . trim( strtolower( (string) $path ), '/' );
	$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$home_path = '/' . trim( strtolower( (string) $home_path ), '/' );
	$bases     = array( '/' . blueworx_login_slug() );

	if ( '/' !== $home_path ) {
		$bases[] = $home_path . '/' . blueworx_login_slug();
		$bases[] = $home_path . '/index.php/' . blueworx_login_slug();
	}

	foreach ( array_unique( $bases ) as $base ) {
		$base = '/' . trim( $base, '/' );

		if ( $path === $base || $path === $base . '/wp-login.php' ) {
			return true;
		}
	}

	return false;
}

/**
 * Replaces the default wp-login.php login URL with the custom slug URL.
 *
 * @param string $login_url    The original login URL.
 * @param string $redirect     URL to redirect to after login.
 * @param bool   $force_reauth Whether to force re-authentication.
 * @return string The filtered login URL.
 */
function blueworx_custom_login_url( $login_url, $redirect, $force_reauth ) {
	$custom = home_url( '/' . blueworx_login_slug() . '/' );

	if ( $redirect ) {
		$custom = add_query_arg( 'redirect_to', $redirect, $custom );
	}
	if ( $force_reauth ) {
		$custom = add_query_arg( 'reauth', '1', $custom );
	}

	return $custom;
}

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
	$custom = home_url( '/' . blueworx_login_slug() . '/' );

	if ( $query ) {
		$custom .= '?' . $query;
	}

	return $custom;
}

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

if ( blueworx_feature_enabled( 'login' ) ) {
	add_filter( 'login_url', 'blueworx_custom_login_url', 10, 3 );
	add_filter( 'site_url', 'blueworx_replace_generated_login_url', 10, 2 );
	add_filter( 'network_site_url', 'blueworx_replace_generated_login_url', 10, 2 );
	add_action( 'template_redirect', 'blueworx_template_redirect_guard' );
}
