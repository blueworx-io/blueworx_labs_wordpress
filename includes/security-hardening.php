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

/**
 * REST route prefixes that expose the user list.
 *
 * The author-slug option above hides usernames in author URLs, which is only
 * half the job: core publishes the same usernames at /wp/v2/users to anyone who
 * asks, with no key and no login, for every user who has published a post. An
 * obfuscated author link is worth little while the username is one request away.
 *
 * @return array Route prefixes.
 */
function blueworx_rest_user_routes() {
	/**
	 * Filters the REST routes treated as the user list.
	 *
	 * Matched as prefixes, so a namespace that republishes the same data under
	 * its own route can be named here.
	 *
	 * @param array $routes Route prefixes.
	 */
	return (array) apply_filters( 'blueworx_rest_user_routes', array( '/wp/v2/users' ) );
}

/**
 * Whether a route is one of the user routes this feature restricts.
 *
 * /wp/v2/users/me is deliberately exempt. It returns only the caller's own
 * record, so it discloses nothing the caller does not already know, and wp-admin
 * fetches it on every page load for block-editor preferences — restricting it
 * would put a console error on every admin screen while protecting nobody.
 *
 * Matching is by path segment rather than raw prefix, so /wp/v2/users-something
 * in another plugin's namespace is not swept in by accident.
 *
 * @param string $route Route being dispatched.
 * @return bool True when the route must be checked.
 */
function blueworx_rest_users_route_is_restricted( $route ) {
	$route = (string) $route;

	foreach ( blueworx_rest_user_routes() as $prefix ) {
		$prefix = (string) $prefix;

		if ( '' === $prefix ) {
			continue;
		}

		if ( $route !== $prefix && 0 !== strpos( $route, $prefix . '/' ) ) {
			continue;
		}

		if ( $prefix . '/me' === $route ) {
			return false;
		}

		return true;
	}

	return false;
}

/**
 * Whether a caller must be refused a user route.
 *
 * Kept separate from the dispatch filter, and pure, so the rule can be checked
 * without a WordPress install (tests/php/rest-users-test.php).
 *
 * The bar is deliberately "signed in", not a capability. The hole being closed
 * is anonymous enumeration — the site handing its account names to the public.
 * Once a caller is authenticated, core's own per-context permission checks
 * decide what they may see, and they are already careful: context=edit demands
 * list_users, and ?who=authors is scoped to who may assign authors.
 *
 * Re-implementing that here with a flat list_users test was the first attempt
 * and it was wrong. It refuses roles core deliberately allows, so the block
 * editor's author picker and anything else reading the route as a signed-in
 * non-administrator would break — a regression well outside what this feature
 * was for, and one that would surface as "the editor is broken" rather than as
 * a security setting.
 *
 * @param string $route     Route being dispatched.
 * @param bool   $logged_in Whether the caller is authenticated.
 * @return bool True when the request must be refused.
 */
function blueworx_rest_users_request_denied( $route, $logged_in ) {
	if ( ! blueworx_rest_users_route_is_restricted( $route ) ) {
		return false;
	}

	return ! $logged_in;
}

/**
 * Refuses the public user routes to callers who are not signed in.
 *
 * 401 rather than 403: nobody was authenticated, which is the distinction core
 * draws on the same routes, so a client library reacts the way it already knows
 * how and an operator reading a log sees "no credentials" rather than "wrong
 * credentials".
 *
 * @param mixed           $result  Pre-dispatch result.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Current request.
 * @return mixed Untouched result, or a WP_Error.
 */
function blueworx_restrict_rest_users( $result, $server, $request ) {
	unset( $server );

	// Something ahead of this has already answered the request; leave it alone.
	if ( null !== $result ) {
		return $result;
	}

	if ( ! blueworx_rest_users_request_denied( (string) $request->get_route(), is_user_logged_in() ) ) {
		return $result;
	}

	return new WP_Error(
		'blueworx_rest_users_forbidden',
		__( 'The user list is not publicly available on this site.', 'blueworx-labs-wordpress' ),
		array( 'status' => 401 )
	);
}

if ( blueworx_feature_enabled( 'rest_users' ) ) {
	// Priority 5: ahead of support access's own gates (10 and 11), which speak
	// only for the support account and would otherwise never be reached for it —
	// the support role holds list_users, so this rule passes it through and lets
	// blueworx_support_gate_data_routes() give its own, more specific refusal.
	add_filter( 'rest_pre_dispatch', 'blueworx_restrict_rest_users', 5, 3 );
}
