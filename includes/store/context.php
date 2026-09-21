<?php
/**
 * Who this site is and who is signed in, for the store pages to draw.
 *
 * WordPress's own answers — the site's name, its icon, whoever is logged in —
 * and one filter over the top, so a plugin that knows better (a club with its
 * own crest and its own login page) can say so without this file knowing it
 * exists.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Who this site is and who is signed in, for the frame to draw.
 *
 * WordPress's own answers, then the blueworx_store_context filter, so a
 * plugin that knows better — a club with its own crest and login page —
 * can say so without this file knowing it exists.
 *
 * Answered once per request: the checkout draws it, the footer links read
 * it, and the dashboard asks again for every panel.
 *
 * @return array site_name, logo_url, home_url, home_label, login_url, logout_url, member_name, member_email.
 */
function blueworx_store_context() {
	static $context = null;
	if ( null !== $context ) {
		return $context;
	}
	$defaults = blueworx_store_default_context();
	$filtered = $defaults;
	if ( function_exists( 'apply_filters' ) ) {
		/**
		 * Filters the site and member details the store pages draw.
		 *
		 * @param array $context See blueworx_store_default_context().
		 */
		$filtered = apply_filters( 'blueworx_store_context', $defaults );
	}
	$context = array_merge( $defaults, (array) $filtered );
	return $context;
}

/**
 * What WordPress itself knows: the site's name and icon, where home is, how
 * to sign in and out, and who is signed in.
 *
 * Every call is guarded so the file loads under the CLI test stubs, where
 * none of these exist and the honest answer is an empty string.
 *
 * @return array{site_name:string, logo_url:string, home_url:string, home_label:string,
 *               login_url:string, logout_url:string, member_name:string, member_email:string}
 */
function blueworx_store_default_context() {
	$site_name = function_exists( 'get_bloginfo' ) ? trim( (string) get_bloginfo( 'name' ) ) : '';
	$home_url  = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';

	// The address being read, so a sign-in comes back to it. Built from the
	// request itself rather than home_url( add_query_arg( array() ) ), which
	// doubles the path on a site installed in a subdirectory.
	$current = '';
	if ( function_exists( 'set_url_scheme' ) && function_exists( 'esc_url_raw' ) && isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ) {
		$current = esc_url_raw( set_url_scheme( 'http://' . wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw() is the sanitizer.
	}
	$login_url = function_exists( 'wp_login_url' ) ? (string) wp_login_url( $current ) : '';

	$logout_url = function_exists( 'wp_logout_url' ) ? (string) wp_logout_url( $home_url ) : '';

	$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;

	return array(
		'site_name'    => $site_name,
		'logo_url'     => blueworx_store_default_logo_url(),
		'home_url'     => $home_url,
		'home_label'   => blueworx_store_back_label( $site_name ),
		'login_url'    => $login_url,
		'logout_url'   => $logout_url,
		'member_name'  => blueworx_store_member_name( $user ),
		'member_email' => blueworx_store_member_email( $user ),
	);
}

/**
 * The site's mark, or '' when it has set none.
 *
 * The site icon wins: it is the square, small-size mark, which is exactly
 * what a 34px or 44px corner box wants — a wide logo shrinks to nothing in
 * it. Falls back to the theme's custom logo, then to '' and the shell draws
 * the site's initials instead.
 *
 * @return string
 */
function blueworx_store_default_logo_url() {
	if ( function_exists( 'get_site_icon_url' ) ) {
		$icon = trim( (string) get_site_icon_url( 64 ) );
		if ( '' !== $icon ) {
			return $icon;
		}
	}
	if ( function_exists( 'get_theme_mod' ) && function_exists( 'wp_get_attachment_image_url' ) ) {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id > 0 ) {
			$logo = wp_get_attachment_image_url( $logo_id, 'thumbnail' );
			if ( is_string( $logo ) && '' !== trim( $logo ) ) {
				return trim( $logo );
			}
		}
	}
	return '';
}

/**
 * The signed-in member's name, or '' for nobody. Their display name, or
 * their login when they have not set one.
 *
 * @param object|null $user From wp_get_current_user(), or null off WordPress.
 * @return string
 */
function blueworx_store_member_name( $user ) {
	if ( ! is_object( $user ) ) {
		return '';
	}
	$name = trim( (string) ( $user->display_name ?? '' ) );
	return '' !== $name ? $name : trim( (string) ( $user->user_login ?? '' ) );
}

/**
 * The address they signed in with, which tells them which account this is.
 *
 * @param object|null $user From wp_get_current_user(), or null off WordPress.
 * @return string
 */
function blueworx_store_member_email( $user ) {
	return is_object( $user ) ? trim( (string) ( $user->user_email ?? '' ) ) : '';
}

/**
 * "Back to Crewe Vagrants", or the generic wording when a site has not named
 * itself yet. Pure.
 *
 * @param string $site_name Site name.
 * @return string
 */
function blueworx_store_back_label( $site_name ) {
	$site = trim( (string) $site_name );
	return '' !== $site ? 'Back to ' . $site : 'Back to the site';
}
