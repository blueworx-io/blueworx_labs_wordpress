<?php
/**
 * Security hardening: XML-RPC and author URL obfuscation.
 *
 * Replaces the Admin and Site Enhancements `disable_xmlrpc` and
 * `obfuscate_author_slugs` modules.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns XML-RPC off.
 *
 * @return bool Always false.
 */
function blueworx_disable_xmlrpc() {
	return false;
}

/**
 * Drops the X-Pingback header XML-RPC advertises itself with.
 *
 * @param array $headers Response headers.
 * @return array Headers without X-Pingback.
 */
function blueworx_remove_pingback_header( $headers ) {
	if ( is_array( $headers ) ) {
		unset( $headers['X-Pingback'] );
	}

	return $headers;
}

/**
 * Removes the XML-RPC pingback method, so a disabled endpoint cannot still be
 * used as a reflector.
 *
 * `xmlrpc_enabled` only gates the methods that need authentication. Pingback is
 * unauthenticated by design and stays reachable through that filter alone,
 * which is exactly the method used to bounce traffic off a site at someone else.
 *
 * @param array $methods Registered XML-RPC methods.
 * @return array Methods without pingback.
 */
function blueworx_remove_xmlrpc_pingback_methods( $methods ) {
	if ( ! is_array( $methods ) ) {
		return $methods;
	}

	unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'], $methods['x.pingback.extensions.getPingbacks'] );

	return $methods;
}

if ( blueworx_feature_enabled( 'xmlrpc' ) ) {
	add_filter( 'xmlrpc_enabled', 'blueworx_disable_xmlrpc' );
	add_filter( 'pings_open', '__return_false', 20 );
	add_filter( 'wp_headers', 'blueworx_remove_pingback_header' );
	add_filter( 'xmlrpc_methods', 'blueworx_remove_xmlrpc_pingback_methods' );
	remove_action( 'wp_head', 'rsd_link' );
}

/**
 * The site-specific salt used to derive author slugs.
 *
 * Generated once and stored, rather than derived from a WordPress salt
 * constant: a host that rotates AUTH_SALT would otherwise silently change every
 * author URL on the site.
 *
 * @return string Salt.
 */
function blueworx_author_slug_salt() {
	$salt = get_option( 'blueworx_author_slug_salt', '' );

	if ( ! is_string( $salt ) || '' === $salt ) {
		$salt = wp_generate_password( 32, false, false );
		update_option( 'blueworx_author_slug_salt', $salt, false );
	}

	return $salt;
}

/**
 * Builds the public slug shown in place of a username.
 *
 * A keyed hash of the user ID, truncated to something that still reads as a URL
 * segment. It is stable for the life of the site (so links do not rot) and
 * carries nothing about the account it belongs to.
 *
 * @param int $user_id User ID.
 * @return string Obfuscated slug.
 */
function blueworx_author_slug_for_user( $user_id ) {
	$user_id = (int) $user_id;

	if ( $user_id <= 0 ) {
		return '';
	}

	return substr( hash_hmac( 'sha256', 'author-' . $user_id, blueworx_author_slug_salt() ), 0, 20 );
}

/**
 * Swaps the username out of author URLs.
 *
 * @param string $link            Author URL.
 * @param int    $author_id       Author user ID.
 * @param string $author_nicename Author nicename.
 * @return string Author URL with an obfuscated slug.
 */
function blueworx_filter_author_link( $link, $author_id, $author_nicename ) {
	$slug = blueworx_author_slug_for_user( $author_id );

	if ( '' === $slug || '' === (string) $author_nicename ) {
		return $link;
	}

	return str_replace( rawurlencode( (string) $author_nicename ), $slug, $link );
}

/**
 * Resolves an obfuscated slug back to its user when an author archive is
 * requested.
 *
 * The slug is one-way, so the lookup is a scan: the hash of each candidate user
 * is recomputed and compared. Author archives are rare and the user list on a
 * managed site is small, and the result is cached for an hour, so this is
 * cheaper than storing a second slug on every user and keeping it in sync.
 *
 * Runs on `pre_get_posts` for the main author query only. A slug that matches
 * nothing is left alone, so WordPress serves its own 404.
 *
 * @param WP_Query $query Query being prepared.
 * @return void
 */
function blueworx_resolve_obfuscated_author( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_author() ) {
		return;
	}

	$requested = (string) $query->get( 'author_name' );

	if ( '' === $requested || ! preg_match( '/^[a-f0-9]{20}$/', $requested ) ) {
		return;
	}

	$map = get_transient( 'blueworx_author_slug_map' );

	if ( ! is_array( $map ) ) {
		$map = array();

		foreach ( get_users( array( 'fields' => array( 'ID', 'user_nicename' ) ) ) as $user ) {
			$map[ blueworx_author_slug_for_user( $user->ID ) ] = $user->user_nicename;
		}

		set_transient( 'blueworx_author_slug_map', $map, HOUR_IN_SECONDS );
	}

	if ( isset( $map[ $requested ] ) ) {
		$query->set( 'author_name', $map[ $requested ] );
	}
}

/**
 * Drops the cached slug map when the user list changes.
 *
 * @return void
 */
function blueworx_flush_author_slug_map() {
	delete_transient( 'blueworx_author_slug_map' );
}

/**
 * Stops `?author=1` enumerating usernames.
 *
 * WordPress redirects that query to the author archive, which reveals the
 * nicename — obfuscating the pretty URL achieves nothing while that redirect
 * still answers. Requests carrying it are sent to the home page instead.
 *
 * @return void
 */
function blueworx_block_author_id_enumeration() {
	if ( is_admin() ) {
		return;
	}

	// Reading a public query arg to decide whether to redirect; no state change,
	// and a nonce cannot exist on an anonymous front-end request.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['author'] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '' === trim( sanitize_text_field( wp_unslash( $_GET['author'] ) ) ) ) {
		return;
	}

	blueworx_redirect_home();
}

if ( blueworx_feature_enabled( 'author_slugs' ) ) {
	add_filter( 'author_link', 'blueworx_filter_author_link', 10, 3 );
	add_action( 'pre_get_posts', 'blueworx_resolve_obfuscated_author' );
	add_action( 'template_redirect', 'blueworx_block_author_id_enumeration', 1 );

	foreach ( array( 'user_register', 'profile_update', 'deleted_user' ) as $blueworx_author_slug_hook ) {
		add_action( $blueworx_author_slug_hook, 'blueworx_flush_author_slug_map' );
	}
	unset( $blueworx_author_slug_hook );
}
