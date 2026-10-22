<?php
/**
 * BlueWorx: Connect Claude — the sign-in half.
 *
 * Lets an MCP client such as Claude connect to this site the way the MCP
 * specification expects: it discovers where to sign in from two well-known
 * documents, registers itself, sends an administrator to a consent screen on
 * this site, and swaps the one-time code it gets back for a token.
 *
 * Nothing here is a general-purpose OAuth server. It issues one kind of token,
 * for one resource (the MCP endpoint in includes/mcp/server.php), to
 * administrators only, and every token is refused the moment the function is
 * switched off or the person who approved it stops being an administrator.
 *
 * Everything is answered on wp_loaded rather than through the REST API, for
 * three reasons:
 *
 * - The well-known documents have to live at fixed addresses at the site root,
 *   which no REST route can claim.
 * - The 401 that starts the whole flow has to carry a WWW-Authenticate header
 *   naming the metadata document, which is awkward to guarantee through the
 *   REST server's own error handling.
 * - wp_loaded runs after every plugin has registered its post types on init,
 *   so the tools see the same content types the admin does.
 *
 * Login and Site Protection would otherwise stop a signed-out caller reaching
 * these addresses at all; blueworx_mcp_exempt_from_site_protection() opts them
 * out, and only them.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How long an access token lasts, in seconds.
 */
const BLUEWORX_MCP_ACCESS_TTL = 3600;

/**
 * How long a refresh token lasts without being used, in seconds (30 days).
 */
const BLUEWORX_MCP_REFRESH_TTL = 2592000;

/**
 * How long a one-time authorisation code lasts, in seconds.
 */
const BLUEWORX_MCP_CODE_TTL = 600;

/**
 * How long a replaced refresh token still works, in seconds. A client whose
 * refresh reply was lost on the way retries with the token it still holds;
 * inside this window that is a retry, not a stolen copy.
 */
const BLUEWORX_MCP_REFRESH_GRACE = 60;

/**
 * Most client registrations kept. Registration is open by design — the
 * specification has a client register itself before anybody has signed in —
 * so the list is capped and the oldest unused entries make room.
 */
const BLUEWORX_MCP_MAX_CLIENTS = 50;

/**
 * How long a new registration is safe from being pruned, in seconds: long
 * enough for an administrator to reach the consent screen and click Allow.
 */
const BLUEWORX_MCP_CLIENT_FRESH = 900;

/**
 * Most registrations one address may make in an hour.
 */
const BLUEWORX_MCP_REGISTER_LIMIT = 20;

/**
 * The one scope this server issues.
 */
const BLUEWORX_MCP_SCOPE = 'content';

/**
 * Option: registered clients.
 */
const BLUEWORX_MCP_OPTION_CLIENTS = 'blueworx_mcp_clients';

/**
 * Option: issued grants (one per approved connection).
 */
const BLUEWORX_MCP_OPTION_GRANTS = 'blueworx_mcp_grants';

/**
 * Option: when each grant was last used, kept apart from the grants. See
 * blueworx_mcp_touch_grant().
 */
const BLUEWORX_MCP_OPTION_LAST_USED = 'blueworx_mcp_last_used';

/**
 * Option: when everyone was last disconnected. A code approved before then
 * is refused, so switching off and on again cannot revive one either.
 */
const BLUEWORX_MCP_OPTION_REVOKED_AT = 'blueworx_mcp_revoked_at';

/**
 * Gets the site's own path, so addresses match on a site in a subfolder too.
 *
 * @return string Path with no trailing slash, '' for a site at the root.
 */
function blueworx_mcp_home_path() {
	$path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

	return rtrim( $path, '/' );
}

/**
 * Turns a request path into the path within this site.
 *
 * @param string $uri       Raw REQUEST_URI.
 * @param string $home_path Site path from blueworx_mcp_home_path().
 * @return string Path starting with '/', no trailing slash, '/' for the root.
 */
function blueworx_mcp_relative_path( $uri, $home_path ) {
	$path = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );
	$path = '/' . trim( rawurldecode( $path ), '/' );

	if ( '' !== $home_path && ( $path === $home_path || 0 === strpos( $path, $home_path . '/' ) ) ) {
		$path = '/' . trim( substr( $path, strlen( $home_path ) ), '/' );
	}

	return $path;
}

/**
 * The well-known documents this function answers, by address.
 *
 * @return array Path prefix => route name.
 */
function blueworx_mcp_well_known_routes() {
	return array(
		'/.well-known/oauth-protected-resource'   => 'resource_metadata',
		'/.well-known/oauth-authorization-server' => 'server_metadata',
		'/.well-known/openid-configuration'       => 'server_metadata',
	);
}

/**
 * Works out which of this function's addresses a path is, if any.
 *
 * The well-known documents are matched on a prefix: a client is allowed to ask
 * for /.well-known/oauth-protected-resource/blueworx-mcp, with the resource's
 * path appended, and some do that before trying the bare address.
 *
 * @param string $path Path from blueworx_mcp_relative_path().
 * @return string One of 'resource_metadata', 'server_metadata', 'register',
 *                'authorize', 'token', 'mcp', or '' for none.
 */
function blueworx_mcp_route_for_path( $path ) {
	$path = strtolower( (string) $path );

	foreach ( blueworx_mcp_well_known_routes() as $prefix => $route ) {
		if ( $path === $prefix || 0 === strpos( $path, $prefix . '/' ) ) {
			return $route;
		}
	}

	$exact = array(
		'/blueworx-oauth/register'  => 'register',
		'/blueworx-oauth/authorize' => 'authorize',
		'/blueworx-oauth/token'     => 'token',
		'/blueworx-mcp'             => 'mcp',
	);

	return isset( $exact[ $path ] ) ? $exact[ $path ] : '';
}

/**
 * Gets the route the current request is for.
 *
 * @return string Route name, or ''.
 */
function blueworx_mcp_current_route() {
	return blueworx_mcp_route_for_path( blueworx_mcp_current_path() );
}

/**
 * Gets the current request's path within this site.
 *
 * @return string Path from blueworx_mcp_relative_path().
 */
function blueworx_mcp_current_path() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only parsed for its path, never echoed.

	return blueworx_mcp_relative_path( $uri, blueworx_mcp_home_path() );
}

/**
 * Works out which other site on a folder-style network a document request is for.
 *
 * A site at example.com/site2 signs Claude in as https://example.com/site2.
 * The standards (RFC 8414 and RFC 9728) have a client look for that site's
 * documents at the domain root, with the site's path moved to the end:
 *
 *   /.well-known/oauth-authorization-server/site2
 *   /.well-known/openid-configuration/site2
 *   /.well-known/oauth-protected-resource/site2/blueworx-mcp
 *
 * The domain root is the main site's, so those requests arrive there. Left
 * alone the main site would answer with its own documents, the client would
 * see the wrong issuer, and connecting to the subsite would fail. This finds
 * the site the trailing path names, the same way WordPress picks a site for
 * any other request, so the main site can answer for it instead.
 *
 * Only a folder-style network has this problem. A single site, or a network
 * where every site has its own domain, always gets 0.
 *
 * @param string $path Path from blueworx_mcp_relative_path().
 * @return int The other site's ID, or 0 when the request is this site's own.
 */
function blueworx_mcp_network_site_for_path( $path ) {
	if ( ! is_multisite() || is_subdomain_install() ) {
		return 0;
	}

	$path   = strtolower( (string) $path );
	$suffix = '';

	foreach ( array_keys( blueworx_mcp_well_known_routes() ) as $prefix ) {
		if ( 0 === strpos( $path, $prefix . '/' ) ) {
			$suffix = substr( $path, strlen( $prefix ) );
			break;
		}
	}

	if ( '' === trim( $suffix, '/' ) ) {
		return 0;
	}

	$current = get_site();
	$network = get_network();

	if ( ! $current || ! $network ) {
		return 0;
	}

	// The network's path plus one folder: the same lookup WordPress makes when
	// it decides which site a request belongs to.
	$site = get_site_by_path( $current->domain, rtrim( $suffix, '/' ) . '/', substr_count( $network->path, '/' ) );

	if ( ! $site || get_current_blog_id() === (int) $site->blog_id ) {
		return 0;
	}

	return (int) $site->blog_id;
}

/**
 * Builds another site's well-known document, for the main site to answer with.
 *
 * Built on that site, so it carries that site's issuer and addresses, and
 * only when that site really offers Connect Claude: an address that does not
 * work is worse than none, because a client stops at the first document it
 * finds.
 *
 * @param int    $site_id Site from blueworx_mcp_network_site_for_path().
 * @param string $route   'resource_metadata' or 'server_metadata'.
 * @return array|null The document, or null when that site does not offer one.
 */
function blueworx_mcp_network_metadata( $site_id, $route ) {
	$site = get_site( $site_id );

	if ( ! $site || '1' === (string) $site->archived || '1' === (string) $site->spam || '1' === (string) $site->deleted ) {
		return null;
	}

	switch_to_blog( (int) $site_id );

	$document = null;

	if ( blueworx_mcp_plugin_active_here() && blueworx_feature_enabled( 'mcp' ) ) {
		$document = 'server_metadata' === $route ? blueworx_mcp_server_metadata() : blueworx_mcp_resource_metadata();
	}

	restore_current_blog();

	return $document;
}

/**
 * Whether this plugin is running on the current site.
 *
 * Asked after switching to another site, where the code being run is the main
 * site's copy and says nothing about whether that site has the plugin at all.
 *
 * @return bool True when it is switched on for this site or the whole network.
 */
function blueworx_mcp_plugin_active_here() {
	$basename = plugin_basename( BLUEWORX_LABS_PLUGIN_FILE );
	$network  = get_site_option( 'active_sitewide_plugins', array() );

	if ( is_array( $network ) && isset( $network[ $basename ] ) ) {
		return true;
	}

	return in_array( $basename, (array) get_option( 'active_plugins', array() ), true );
}

/**
 * Gets the MCP endpoint address — the one an administrator pastes into Claude.
 *
 * @return string URL.
 */
function blueworx_mcp_endpoint_url() {
	return home_url( '/blueworx-mcp' );
}

/**
 * Gets this server's issuer identifier.
 *
 * @return string URL with no trailing slash.
 */
function blueworx_mcp_issuer() {
	return untrailingslashit( home_url() );
}

/**
 * Gets the address of the protected-resource metadata document.
 *
 * @return string URL.
 */
function blueworx_mcp_resource_metadata_url() {
	return home_url( '/.well-known/oauth-protected-resource' );
}

/**
 * Builds the protected-resource metadata document (RFC 9728).
 *
 * @return array Document.
 */
function blueworx_mcp_resource_metadata() {
	return array(
		'resource'                 => blueworx_mcp_endpoint_url(),
		'authorization_servers'    => array( blueworx_mcp_issuer() ),
		'scopes_supported'         => array( BLUEWORX_MCP_SCOPE ),
		'bearer_methods_supported' => array( 'header' ),
		'resource_name'            => get_bloginfo( 'name' ),
	);
}

/**
 * Builds the authorisation-server metadata document (RFC 8414).
 *
 * @return array Document.
 */
function blueworx_mcp_server_metadata() {
	return array(
		'issuer'                                => blueworx_mcp_issuer(),
		'authorization_endpoint'                => home_url( '/blueworx-oauth/authorize' ),
		'token_endpoint'                        => home_url( '/blueworx-oauth/token' ),
		'registration_endpoint'                 => home_url( '/blueworx-oauth/register' ),
		'scopes_supported'                      => array( BLUEWORX_MCP_SCOPE ),
		'response_types_supported'              => array( 'code' ),
		'response_modes_supported'              => array( 'query' ),
		'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
		'code_challenge_methods_supported'      => array( 'S256' ),
		'token_endpoint_auth_methods_supported' => array( 'none', 'client_secret_post', 'client_secret_basic' ),
	);
}

/**
 * Encodes bytes as unpadded base64url.
 *
 * @param string $bytes Raw bytes.
 * @return string Encoded value.
 */
function blueworx_mcp_base64url( $bytes ) {
	return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- encoding a hash, not hiding code.
}

/**
 * Whether an array is a plain list: keys 0, 1, 2 and so on, in order.
 *
 * Core's array_is_list() needs PHP 8.1, or WordPress 6.5 for its stand-in,
 * and this plugin supports PHP 8.0 on older WordPress.
 *
 * @param array $value Array.
 * @return bool True for a list, including an empty one.
 */
function blueworx_mcp_is_list( $value ) {
	return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
}

/**
 * Makes a random, URL-safe secret.
 *
 * @param int $bytes Bytes of randomness.
 * @return string Secret.
 */
function blueworx_mcp_random( $bytes = 32 ) {
	return blueworx_mcp_base64url( random_bytes( $bytes ) );
}

/**
 * Hashes a secret for storage. Only hashes are ever kept.
 *
 * @param string $secret Raw secret.
 * @return string SHA-256 hex.
 */
function blueworx_mcp_hash( $secret ) {
	return hash( 'sha256', (string) $secret );
}

/**
 * Checks a PKCE verifier against the challenge it must match (S256 only).
 *
 * @param string $verifier  code_verifier from the token request.
 * @param string $challenge code_challenge from the authorisation request.
 * @return bool True when they match.
 */
function blueworx_mcp_pkce_matches( $verifier, $challenge ) {
	$verifier = (string) $verifier;

	// RFC 7636 §4.1: 43 to 128 characters from the unreserved set.
	if ( ! preg_match( '/^[A-Za-z0-9\-._~]{43,128}$/', $verifier ) ) {
		return false;
	}

	return hash_equals( (string) $challenge, blueworx_mcp_base64url( hash( 'sha256', $verifier, true ) ) );
}

/**
 * Checks whether a redirect address may be registered.
 *
 * HTTPS anywhere, or plain HTTP to this machine only — the loopback case a
 * desktop client uses. Nothing with a fragment, and nothing that is not an
 * absolute web address.
 *
 * @param string $uri Redirect address.
 * @return bool True when acceptable.
 */
function blueworx_mcp_redirect_uri_allowed( $uri ) {
	$uri = (string) $uri;

	if ( '' === $uri || strlen( $uri ) > 2000 || false !== strpos( $uri, '#' ) || preg_match( '/[\s<>"]/', $uri ) ) {
		return false;
	}

	$parts = wp_parse_url( $uri );

	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return false;
	}

	$scheme = strtolower( $parts['scheme'] );
	$host   = strtolower( $parts['host'] );

	if ( 'https' === $scheme ) {
		return true;
	}

	return 'http' === $scheme && in_array( $host, array( 'localhost', '127.0.0.1', '[::1]', '::1' ), true );
}

/**
 * Gets every registered client.
 *
 * @return array Clients keyed by client_id.
 */
function blueworx_mcp_get_clients() {
	$clients = get_option( BLUEWORX_MCP_OPTION_CLIENTS, array() );

	return is_array( $clients ) ? $clients : array();
}

/**
 * Gets one registered client.
 *
 * @param string $client_id Client id.
 * @return array|null Client, or null when unknown.
 */
function blueworx_mcp_get_client( $client_id ) {
	$clients = blueworx_mcp_get_clients();

	return ( is_string( $client_id ) && isset( $clients[ $client_id ] ) ) ? $clients[ $client_id ] : null;
}

/**
 * Registers a client (RFC 7591), from its decoded registration request.
 *
 * @param array $request Registration request.
 * @return array|WP_Error Registration response, or the reason it was refused.
 */
function blueworx_mcp_register_client( $request ) {
	if ( ! is_array( $request ) ) {
		return new WP_Error( 'invalid_client_metadata', 'The registration request must be a JSON object.' );
	}

	$redirects = isset( $request['redirect_uris'] ) ? $request['redirect_uris'] : array();

	if ( ! is_array( $redirects ) || empty( $redirects ) || count( $redirects ) > 10 ) {
		return new WP_Error( 'invalid_redirect_uri', 'Between one and ten redirect_uris are required.' );
	}

	$redirects = array_values( array_map( 'strval', $redirects ) );

	foreach ( $redirects as $uri ) {
		if ( ! blueworx_mcp_redirect_uri_allowed( $uri ) ) {
			return new WP_Error( 'invalid_redirect_uri', 'Redirect addresses must use HTTPS, or HTTP to localhost.' );
		}
	}

	$method = isset( $request['token_endpoint_auth_method'] ) ? (string) $request['token_endpoint_auth_method'] : 'none';

	if ( ! in_array( $method, array( 'none', 'client_secret_post', 'client_secret_basic' ), true ) ) {
		return new WP_Error( 'invalid_client_metadata', 'Unsupported token_endpoint_auth_method.' );
	}

	$name = isset( $request['client_name'] ) ? sanitize_text_field( (string) $request['client_name'] ) : '';
	$name = '' !== $name ? substr( $name, 0, 100 ) : 'Unnamed app';

	$client_id = 'bwx_' . blueworx_mcp_random( 18 );
	$secret    = 'none' === $method ? '' : blueworx_mcp_random( 32 );
	$now       = time();

	$client = array(
		'client_id'     => $client_id,
		'client_name'   => $name,
		'redirect_uris' => $redirects,
		'auth_method'   => $method,
		'secret_hash'   => '' === $secret ? '' : blueworx_mcp_hash( $secret ),
		'created'       => $now,
	);

	$clients = blueworx_mcp_prune_clients( blueworx_mcp_get_clients() );

	if ( count( $clients ) >= BLUEWORX_MCP_MAX_CLIENTS ) {
		return new WP_Error( 'temporarily_unavailable', 'Too many apps are waiting to be approved. Try again in a few minutes.' );
	}

	$clients[ $client_id ] = $client;

	update_option( BLUEWORX_MCP_OPTION_CLIENTS, $clients, false );

	$response = array(
		'client_id'                  => $client_id,
		'client_id_issued_at'        => $now,
		'client_name'                => $name,
		'redirect_uris'              => $redirects,
		'grant_types'                => array( 'authorization_code', 'refresh_token' ),
		'response_types'             => array( 'code' ),
		'token_endpoint_auth_method' => $method,
		'scope'                      => BLUEWORX_MCP_SCOPE,
	);

	if ( '' !== $secret ) {
		$response['client_secret']            = $secret;
		$response['client_secret_expires_at'] = 0;
	}

	return $response;
}

/**
 * Makes room in the client list before another registration.
 *
 * Only clients nobody has connected with are ever dropped, oldest first; a
 * client behind a live connection is never removed to make room. Neither is
 * one registered in the last few minutes: it is most likely an app whose
 * administrator is on the consent screen right now, and dropping it would let
 * a burst of registrations knock a real connection out before it was made.
 *
 * @param array $clients Clients keyed by client_id.
 * @return array Clients, at most BLUEWORX_MCP_MAX_CLIENTS - 1 long when possible.
 */
function blueworx_mcp_prune_clients( $clients ) {
	if ( count( $clients ) < BLUEWORX_MCP_MAX_CLIENTS ) {
		return $clients;
	}

	$in_use = array();

	foreach ( blueworx_mcp_get_grants() as $grant ) {
		$in_use[ $grant['client_id'] ] = true;
	}

	uasort(
		$clients,
		function ( $a, $b ) {
			return (int) $a['created'] <=> (int) $b['created'];
		}
	);

	$fresh_since = time() - BLUEWORX_MCP_CLIENT_FRESH;

	foreach ( $clients as $id => $client ) {
		if ( count( $clients ) < BLUEWORX_MCP_MAX_CLIENTS ) {
			break;
		}

		if ( empty( $in_use[ $id ] ) && (int) $client['created'] < $fresh_since ) {
			unset( $clients[ $id ] );
		}
	}

	return $clients;
}

/**
 * Counts a registration against the caller's address, and says whether it
 * is within the limit.
 *
 * REMOTE_ADDR only: a forwarded-for header is whatever the caller wrote, so
 * counting by it would let anyone pick a fresh address for every request.
 *
 * @return bool True when this registration may go ahead.
 */
function blueworx_mcp_registration_allowed() {
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key   = 'blueworx_mcp_reg_' . md5( $ip );
	$count = (int) get_transient( $key );

	if ( $count >= BLUEWORX_MCP_REGISTER_LIMIT ) {
		return false;
	}

	set_transient( $key, $count + 1, HOUR_IN_SECONDS );

	return true;
}

/**
 * Checks a confidential client's secret. Public clients pass with none.
 *
 * @param array  $client Client.
 * @param string $secret Secret presented, '' when none.
 * @return bool True when the client is who it says it is.
 */
function blueworx_mcp_client_authenticates( $client, $secret ) {
	if ( 'none' === $client['auth_method'] ) {
		return true;
	}

	return '' !== (string) $secret && hash_equals( (string) $client['secret_hash'], blueworx_mcp_hash( $secret ) );
}

/**
 * Gets every issued grant.
 *
 * Anything about to write the list back reads it fresh, straight past the
 * object cache, so a Disconnect made while that request was running is not
 * undone by saving a copy from before it.
 *
 * @param bool $fresh Skip the cached copy.
 * @return array Grants keyed by grant id.
 */
function blueworx_mcp_get_grants( $fresh = false ) {
	if ( $fresh ) {
		wp_cache_delete( BLUEWORX_MCP_OPTION_GRANTS, 'options' );
	}

	$grants = get_option( BLUEWORX_MCP_OPTION_GRANTS, array() );

	return is_array( $grants ) ? $grants : array();
}

/**
 * Saves the grant list.
 *
 * @param array $grants Grants keyed by grant id.
 * @return void
 */
function blueworx_mcp_save_grants( $grants ) {
	update_option( BLUEWORX_MCP_OPTION_GRANTS, $grants, false );
}

/**
 * Splits a token into its grant id and secret.
 *
 * Tokens read "bwx_at_<grant>.<secret>" or "bwx_rt_<grant>.<secret>", so the
 * grant can be found without searching every stored hash, and only the secret
 * half is ever compared.
 *
 * @param string $token  Token.
 * @param string $prefix 'at' or 'rt'.
 * @return array|null array( grant id, secret ), or null when malformed.
 */
function blueworx_mcp_parse_token( $token, $prefix ) {
	if ( ! is_string( $token ) || ! preg_match( '/^bwx_' . $prefix . '_([A-Za-z0-9_-]{8,64})\.([A-Za-z0-9_-]{20,128})$/', $token, $m ) ) {
		return null;
	}

	return array( $m[1], $m[2] );
}

/**
 * Mints a fresh access and refresh token pair onto a grant.
 *
 * @param string $grant_id Grant id.
 * @param array  $grant    Grant, updated in place.
 * @return array Token response body.
 */
function blueworx_mcp_mint_tokens( $grant_id, &$grant ) {
	$access  = blueworx_mcp_random( 32 );
	$refresh = blueworx_mcp_random( 32 );
	$now     = time();

	$grant['access_hash']     = blueworx_mcp_hash( $access );
	$grant['access_expires']  = $now + BLUEWORX_MCP_ACCESS_TTL;
	$grant['prev_refresh']    = isset( $grant['refresh_hash'] ) ? $grant['refresh_hash'] : '';
	$grant['refresh_hash']    = blueworx_mcp_hash( $refresh );
	$grant['refresh_expires'] = $now + BLUEWORX_MCP_REFRESH_TTL;
	$grant['rotated_at']      = $now;

	return array(
		'access_token'  => 'bwx_at_' . $grant_id . '.' . $access,
		'token_type'    => 'Bearer',
		'expires_in'    => BLUEWORX_MCP_ACCESS_TTL,
		'refresh_token' => 'bwx_rt_' . $grant_id . '.' . $refresh,
		'scope'         => BLUEWORX_MCP_SCOPE,
	);
}

/**
 * Checks the person behind a grant may still use it.
 *
 * Checked on every use, not just when the grant was made: an administrator
 * demoted or deleted after connecting Claude loses the connection with it.
 *
 * @param int $user_id User id.
 * @return bool True when they are still an administrator.
 */
function blueworx_mcp_user_may_connect( $user_id ) {
	$user = get_userdata( (int) $user_id );

	return ( $user instanceof WP_User ) && user_can( $user, 'manage_options' );
}

/**
 * Resolves an access token to the grant behind it.
 *
 * @param string $token Bearer token.
 * @return array|null array( grant id, grant ), or null when it is not valid.
 */
function blueworx_mcp_verify_access_token( $token ) {
	if ( ! blueworx_feature_enabled( 'mcp' ) ) {
		return null;
	}

	$parts = blueworx_mcp_parse_token( $token, 'at' );

	if ( null === $parts ) {
		return null;
	}

	list( $grant_id, $secret ) = $parts;

	$grants = blueworx_mcp_get_grants();

	if ( ! isset( $grants[ $grant_id ] ) ) {
		return null;
	}

	$grant = $grants[ $grant_id ];

	if ( empty( $grant['access_hash'] ) || (int) $grant['access_expires'] < time() ) {
		return null;
	}

	if ( ! hash_equals( (string) $grant['access_hash'], blueworx_mcp_hash( $secret ) ) ) {
		return null;
	}

	if ( ! blueworx_mcp_user_may_connect( $grant['user_id'] ) ) {
		return null;
	}

	blueworx_mcp_touch_grant( $grant_id, $grants );

	return array( $grant_id, $grant );
}

/**
 * Gets when each grant was last used.
 *
 * @return array Timestamps keyed by grant id.
 */
function blueworx_mcp_get_last_used() {
	$used = get_option( BLUEWORX_MCP_OPTION_LAST_USED, array() );

	return is_array( $used ) ? $used : array();
}

/**
 * Records that a grant was just used.
 *
 * Kept in its own option rather than on the grant. This runs on every call
 * Claude makes, and writing the grant list back from here could save a copy
 * read a moment before an administrator's Disconnect, bringing the revoked
 * connection back. A lost write here only costs a slightly stale time.
 * Recorded at most every five minutes, so a busy session is not an option
 * write on every call.
 *
 * @param string $grant_id Grant id.
 * @param array  $grants   Current grants, so entries for removed ones are dropped.
 * @return void
 */
function blueworx_mcp_touch_grant( $grant_id, $grants ) {
	$used = blueworx_mcp_get_last_used();

	if ( time() - (int) ( $used[ $grant_id ] ?? 0 ) <= 300 ) {
		return;
	}

	$used              = array_intersect_key( $used, $grants );
	$used[ $grant_id ] = time();

	update_option( BLUEWORX_MCP_OPTION_LAST_USED, $used, false );
}

/**
 * Revokes one connection.
 *
 * @param string $grant_id Grant id.
 * @return bool True when there was one to revoke.
 */
function blueworx_mcp_revoke_grant( $grant_id ) {
	$grants = blueworx_mcp_get_grants( true );

	if ( ! isset( $grants[ $grant_id ] ) ) {
		return false;
	}

	unset( $grants[ $grant_id ] );
	blueworx_mcp_save_grants( $grants );

	return true;
}

/**
 * Revokes every connection.
 *
 * @return void
 */
function blueworx_mcp_revoke_all() {
	blueworx_mcp_save_grants( array() );
	update_option( BLUEWORX_MCP_OPTION_LAST_USED, array(), false );
	update_option( BLUEWORX_MCP_OPTION_REVOKED_AT, time(), false );
}

/**
 * Disconnects everyone whenever the function is switched off, from any screen.
 *
 * Hung off the option rather than a screen: the Connect Claude screen and
 * Enhancements both have the switch, and only the first used to disconnect
 * anyone. Switching off from Enhancements and back on later quietly revived
 * every old connection.
 *
 * @param mixed $old_value Previous value.
 * @param mixed $value     New value.
 * @return void
 */
function blueworx_mcp_revoke_on_switch_off( $old_value, $value ) {
	if ( '0' === (string) $value ) {
		blueworx_mcp_revoke_all();
	}
}
add_action( 'update_option_blueworx_feature_mcp', 'blueworx_mcp_revoke_on_switch_off', 10, 2 );

/**
 * Gets the Authorization header's bearer token, wherever the server put it.
 *
 * Apache under CGI or FastCGI drops the Authorization header unless told
 * otherwise, and leaves it under REDIRECT_HTTP_AUTHORIZATION when a rewrite
 * rule passes it on — so all three places are checked.
 *
 * @return string Token, or '' when none was sent.
 */
function blueworx_mcp_bearer_from_request() {
	$header = '';

	foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $key ) {
		if ( ! empty( $_SERVER[ $key ] ) ) {
			$header = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			break;
		}
	}

	if ( '' === $header && function_exists( 'getallheaders' ) ) {
		foreach ( (array) getallheaders() as $name => $value ) {
			if ( 'authorization' === strtolower( (string) $name ) ) {
				$header = sanitize_text_field( (string) $value );
				break;
			}
		}
	}

	if ( preg_match( '/^Bearer\s+(\S+)$/i', $header, $m ) ) {
		return $m[1];
	}

	return '';
}

/**
 * Sends a JSON response and stops.
 *
 * @param mixed $data    Body.
 * @param int   $status  HTTP status.
 * @param array $headers Extra headers, name => value.
 * @return void
 */
function blueworx_mcp_send_json( $data, $status = 200, $headers = array() ) {
	status_header( $status );
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'X-Content-Type-Options: nosniff' );

	foreach ( $headers as $name => $value ) {
		header( $name . ': ' . $value );
	}

	echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
	exit;
}

/**
 * Sends an OAuth error response and stops.
 *
 * @param string $code        OAuth error code.
 * @param string $description Human-readable reason.
 * @param int    $status      HTTP status.
 * @param array  $headers     Extra headers, name => value.
 * @return void
 */
function blueworx_mcp_oauth_error( $code, $description, $status = 400, $headers = array() ) {
	blueworx_mcp_send_json(
		array(
			'error'             => $code,
			'error_description' => $description,
		),
		$status,
		$headers
	);
}

/**
 * Reads the request body as parameters — form-encoded or JSON.
 *
 * @return array Parameters.
 */
function blueworx_mcp_request_params() {
	$type = isset( $_SERVER['CONTENT_TYPE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['CONTENT_TYPE'] ) ) ) : '';

	if ( false !== strpos( $type, 'application/json' ) ) {
		$data = json_decode( (string) file_get_contents( 'php://input' ), true );

		return is_array( $data ) ? $data : array();
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- an OAuth token request from a machine; the code and PKCE verifier are its proof.
	return wp_unslash( $_POST );
}

/**
 * Lets this function's addresses through Site Protection.
 *
 * Only these addresses: a client discovering and signing in has nobody signed
 * in yet, and the MCP endpoint checks its own token. Every other address keeps
 * whatever Site Protection decided.
 *
 * @param bool $applies Whether Site Protection applies to this request.
 * @return bool
 */
function blueworx_mcp_exempt_from_site_protection( $applies ) {
	if ( ! $applies || '' === blueworx_mcp_current_route() ) {
		return $applies;
	}

	// A document answered for another site on the network is that site's to
	// offer, whatever this site has switched on.
	if ( blueworx_feature_enabled( 'mcp' ) || 0 !== blueworx_mcp_network_site_for_path( blueworx_mcp_current_path() ) ) {
		return false;
	}

	return $applies;
}
add_filter( 'blueworx_site_protection_applies', 'blueworx_mcp_exempt_from_site_protection' );

/**
 * Answers any of this function's addresses.
 *
 * @return void
 */
function blueworx_mcp_dispatch() {
	$route = blueworx_mcp_current_route();

	if ( '' === $route ) {
		return;
	}

	// On a folder-style network, a subsite's documents are asked for at the
	// main site's address. Answer for the subsite, or say there is nothing
	// there so the client moves on to the next address it knows to try.
	$other_site = blueworx_mcp_network_site_for_path( blueworx_mcp_current_path() );

	if ( 0 !== $other_site ) {
		$document = blueworx_mcp_network_metadata( $other_site, $route );

		if ( null === $document ) {
			blueworx_mcp_send_json( array( 'error' => 'not_found' ), 404 );
		}

		blueworx_mcp_send_json( $document );
	}

	if ( ! blueworx_feature_enabled( 'mcp' ) ) {
		return;
	}

	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

	switch ( $route ) {
		case 'resource_metadata':
			blueworx_mcp_send_json( blueworx_mcp_resource_metadata() );
			break;

		case 'server_metadata':
			blueworx_mcp_send_json( blueworx_mcp_server_metadata() );
			break;

		case 'register':
			if ( 'POST' !== $method ) {
				blueworx_mcp_oauth_error( 'invalid_request', 'Use POST.', 405 );
			}

			if ( ! blueworx_mcp_registration_allowed() ) {
				blueworx_mcp_oauth_error( 'temporarily_unavailable', 'Too many registrations from this address. Try again later.', 429, array( 'Retry-After' => (string) HOUR_IN_SECONDS ) );
			}

			$result = blueworx_mcp_register_client( json_decode( (string) file_get_contents( 'php://input' ), true ) );

			if ( is_wp_error( $result ) ) {
				blueworx_mcp_oauth_error( $result->get_error_code(), $result->get_error_message(), 'temporarily_unavailable' === $result->get_error_code() ? 429 : 400 );
			}

			blueworx_mcp_send_json( $result, 201 );
			break;

		case 'token':
			if ( 'POST' !== $method ) {
				blueworx_mcp_oauth_error( 'invalid_request', 'Use POST.', 405 );
			}

			blueworx_mcp_handle_token();
			break;

		case 'authorize':
			blueworx_mcp_handle_authorize( $method );
			break;

		case 'mcp':
			blueworx_mcp_handle_mcp_request( $method );
			break;
	}
}
add_action( 'wp_loaded', 'blueworx_mcp_dispatch', 0 );

/**
 * Answers the token endpoint.
 *
 * @return void
 */
function blueworx_mcp_handle_token() {
	$params = blueworx_mcp_request_params();
	$grant  = isset( $params['grant_type'] ) ? (string) $params['grant_type'] : '';

	// Client credentials may arrive in the body or as HTTP Basic.
	$client_id = isset( $params['client_id'] ) ? (string) $params['client_id'] : '';
	$secret    = isset( $params['client_secret'] ) ? (string) $params['client_secret'] : '';

	if ( ! empty( $_SERVER['PHP_AUTH_USER'] ) ) {
		$client_id = rawurldecode( sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) ) );
		$secret    = isset( $_SERVER['PHP_AUTH_PW'] ) ? rawurldecode( (string) wp_unslash( $_SERVER['PHP_AUTH_PW'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared as a hash only.
	}

	$client = blueworx_mcp_get_client( $client_id );

	if ( null === $client || ! blueworx_mcp_client_authenticates( $client, $secret ) ) {
		// RFC 6749 section 5.2: a 401 here names the scheme, or strict clients
		// read it as a transport failure and never show the real reason.
		blueworx_mcp_oauth_error( 'invalid_client', 'Unknown client, or wrong client secret.', 401, array( 'WWW-Authenticate' => 'Basic realm="blueworx-oauth"' ) );
	}

	if ( 'authorization_code' === $grant ) {
		$result = blueworx_mcp_exchange_code(
			$client,
			isset( $params['code'] ) ? (string) $params['code'] : '',
			isset( $params['redirect_uri'] ) ? (string) $params['redirect_uri'] : '',
			isset( $params['code_verifier'] ) ? (string) $params['code_verifier'] : ''
		);
	} elseif ( 'refresh_token' === $grant ) {
		$result = blueworx_mcp_refresh( $client, isset( $params['refresh_token'] ) ? (string) $params['refresh_token'] : '' );
	} else {
		blueworx_mcp_oauth_error( 'unsupported_grant_type', 'Only authorization_code and refresh_token are supported.' );
	}

	if ( is_wp_error( $result ) ) {
		blueworx_mcp_oauth_error( $result->get_error_code(), $result->get_error_message() );
	}

	blueworx_mcp_send_json( $result );
}

/**
 * Swaps a one-time code for tokens.
 *
 * @param array  $client       Authenticated client.
 * @param string $code         Code.
 * @param string $redirect_uri Redirect address, which must match the one the code was issued for.
 * @param string $verifier     PKCE verifier.
 * @return array|WP_Error Token response, or why it was refused.
 */
function blueworx_mcp_exchange_code( $client, $code, $redirect_uri, $verifier ) {
	if ( '' === $code ) {
		return new WP_Error( 'invalid_request', 'Missing code.' );
	}

	$key    = 'blueworx_mcp_code_' . blueworx_mcp_hash( $code );
	$stored = get_transient( $key );

	// One use only, and spent before anything else is checked, so a code that
	// fails a check cannot be tried again.
	delete_transient( $key );

	if ( ! is_array( $stored ) || (int) $stored['expires'] < time() ) {
		return new WP_Error( 'invalid_grant', 'The code is unknown, used or expired.' );
	}

	if ( (int) ( $stored['issued'] ?? 0 ) <= (int) get_option( BLUEWORX_MCP_OPTION_REVOKED_AT, 0 ) ) {
		return new WP_Error( 'invalid_grant', 'Everyone was disconnected after this code was approved.' );
	}

	if ( $stored['client_id'] !== $client['client_id'] || $stored['redirect_uri'] !== $redirect_uri ) {
		return new WP_Error( 'invalid_grant', 'The code was issued to a different client or address.' );
	}

	if ( ! blueworx_mcp_pkce_matches( $verifier, $stored['code_challenge'] ) ) {
		return new WP_Error( 'invalid_grant', 'The code verifier does not match.' );
	}

	if ( ! blueworx_mcp_user_may_connect( $stored['user_id'] ) ) {
		return new WP_Error( 'invalid_grant', 'The person who approved this is no longer an administrator.' );
	}

	$grant_id = blueworx_mcp_random( 12 );
	$grant    = array(
		'client_id'   => $client['client_id'],
		'client_name' => $client['client_name'],
		'user_id'     => (int) $stored['user_id'],
		'created'     => time(),
	);

	$response = blueworx_mcp_mint_tokens( $grant_id, $grant );

	$grants              = blueworx_mcp_get_grants( true );
	$grants[ $grant_id ] = $grant;
	blueworx_mcp_save_grants( $grants );

	blueworx_mcp_log( (int) $stored['user_id'], $client['client_name'], 'connected', '' );

	return $response;
}

/**
 * Swaps a refresh token for a new pair.
 *
 * Refresh tokens rotate: each one works once. The one just replaced still
 * works for BLUEWORX_MCP_REFRESH_GRACE seconds, because a client whose reply
 * was lost on the way has no choice but to retry with it. Presented any later,
 * it was copied, so the whole connection is revoked rather than letting
 * whoever holds the copy keep going.
 *
 * @param array  $client Authenticated client.
 * @param string $token  Refresh token.
 * @return array|WP_Error Token response, or why it was refused.
 */
function blueworx_mcp_refresh( $client, $token ) {
	$parts = blueworx_mcp_parse_token( $token, 'rt' );

	if ( null === $parts ) {
		return new WP_Error( 'invalid_grant', 'Malformed refresh token.' );
	}

	list( $grant_id, $secret ) = $parts;

	$grants = blueworx_mcp_get_grants();

	if ( ! isset( $grants[ $grant_id ] ) ) {
		return new WP_Error( 'invalid_grant', 'This connection has been removed.' );
	}

	$grant = $grants[ $grant_id ];
	$hash  = blueworx_mcp_hash( $secret );

	if ( $grant['client_id'] !== $client['client_id'] ) {
		return new WP_Error( 'invalid_grant', 'The refresh token belongs to a different client.' );
	}

	$is_retry = ! empty( $grant['prev_refresh'] ) && hash_equals( (string) $grant['prev_refresh'], $hash );

	if ( $is_retry && time() - (int) ( $grant['rotated_at'] ?? 0 ) > BLUEWORX_MCP_REFRESH_GRACE ) {
		blueworx_mcp_revoke_grant( $grant_id );
		blueworx_mcp_log( (int) $grant['user_id'], $grant['client_name'], 'disconnected', 'an old sign-in token was used again' );
		return new WP_Error( 'invalid_grant', 'This refresh token was already used, so the connection has been closed.' );
	}

	if ( ! $is_retry && ( ! hash_equals( (string) $grant['refresh_hash'], $hash ) || (int) $grant['refresh_expires'] < time() ) ) {
		return new WP_Error( 'invalid_grant', 'The refresh token is unknown or expired.' );
	}

	if ( ! blueworx_mcp_user_may_connect( $grant['user_id'] ) ) {
		blueworx_mcp_revoke_grant( $grant_id );
		blueworx_mcp_log( (int) $grant['user_id'], $grant['client_name'], 'disconnected', 'the person who approved it is no longer an administrator' );
		return new WP_Error( 'invalid_grant', 'The person who approved this is no longer an administrator.' );
	}

	$rotated_at = (int) ( $grant['rotated_at'] ?? 0 );
	$response   = blueworx_mcp_mint_tokens( $grant_id, $grant );

	// A retry leaves the replaced token, and the time it was replaced, as they
	// were: a second lost reply can still be retried, but the window never
	// stretches past the first swap.
	if ( $is_retry ) {
		$grant['prev_refresh'] = $hash;
		$grant['rotated_at']   = $rotated_at;
	}

	$latest = blueworx_mcp_get_grants( true );

	if ( ! isset( $latest[ $grant_id ] ) ) {
		return new WP_Error( 'invalid_grant', 'This connection has been removed.' );
	}

	$latest[ $grant_id ] = $grant;
	blueworx_mcp_save_grants( $latest );

	return $response;
}

/**
 * Validates an authorisation request's parameters.
 *
 * @param array $params Request parameters.
 * @return array array( 'fatal' => string|null, 'error' => string|null,
 *               'client' => array|null ). A fatal problem is shown on this
 *               site; an ordinary one is sent back to the client.
 */
function blueworx_mcp_validate_authorize( $params ) {
	$client = blueworx_mcp_get_client( isset( $params['client_id'] ) ? (string) $params['client_id'] : '' );

	if ( null === $client ) {
		return array(
			'fatal'  => __( 'This app is not registered with the site. Remove the connector in Claude and add it again.', 'blueworx-labs-wordpress' ),
			'error'  => null,
			'client' => null,
		);
	}

	$redirect = isset( $params['redirect_uri'] ) ? (string) $params['redirect_uri'] : '';

	// Never redirect anywhere the client did not register: an unregistered
	// address is exactly where a stolen code would be sent.
	if ( ! in_array( $redirect, $client['redirect_uris'], true ) ) {
		return array(
			'fatal'  => __( 'The app asked to return to an address it did not register.', 'blueworx-labs-wordpress' ),
			'error'  => null,
			'client' => $client,
		);
	}

	$error = null;

	if ( ! isset( $params['response_type'] ) || 'code' !== $params['response_type'] ) {
		$error = 'unsupported_response_type';
	} elseif ( empty( $params['code_challenge'] ) || ! isset( $params['code_challenge_method'] ) || 'S256' !== $params['code_challenge_method'] ) {
		$error = 'invalid_request';
	} elseif ( ! preg_match( '/^[A-Za-z0-9_-]{43}$/', (string) $params['code_challenge'] ) ) {
		$error = 'invalid_request';
	} elseif ( ! empty( $params['resource'] ) && untrailingslashit( (string) $params['resource'] ) !== untrailingslashit( blueworx_mcp_endpoint_url() ) ) {
		$error = 'invalid_target';
	}

	return array(
		'fatal'  => null,
		'error'  => $error,
		'client' => $client,
	);
}

/**
 * Adds parameters to a client's redirect address.
 *
 * @param string $uri    Registered redirect address.
 * @param array  $params Parameters to add.
 * @return string URL.
 */
function blueworx_mcp_redirect_with( $uri, $params ) {
	$params = array_filter(
		$params,
		function ( $value ) {
			return null !== $value && '' !== $value;
		}
	);

	return $uri . ( false === strpos( $uri, '?' ) ? '?' : '&' ) . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
}

/**
 * Answers the authorisation endpoint: the consent screen and its answer.
 *
 * @param string $method HTTP method.
 * @return void
 */
function blueworx_mcp_handle_authorize( $method ) {
	// phpcs:disable WordPress.Security.NonceVerification -- the GET is a read; the POST is nonce-checked below.
	$source = 'POST' === $method ? $_POST : $_GET;
	$params = array();

	foreach ( array( 'response_type', 'client_id', 'redirect_uri', 'code_challenge', 'code_challenge_method', 'state', 'scope', 'resource' ) as $name ) {
		if ( isset( $source[ $name ] ) && is_string( $source[ $name ] ) ) {
			$params[ $name ] = (string) wp_unslash( $source[ $name ] );
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification

	$check = blueworx_mcp_validate_authorize( $params );

	if ( null !== $check['fatal'] ) {
		blueworx_mcp_render_consent_message( __( 'Claude could not connect', 'blueworx-labs-wordpress' ), $check['fatal'], 400 );
	}

	$client = $check['client'];
	$state  = isset( $params['state'] ) ? $params['state'] : '';

	if ( null !== $check['error'] ) {
		wp_redirect( // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- a registered client address, checked above.
			blueworx_mcp_redirect_with(
				$params['redirect_uri'],
				array(
					'error' => $check['error'],
					'state' => $state,
				)
			)
		);
		exit;
	}

	if ( ! is_user_logged_in() ) {
		$here = blueworx_mcp_redirect_with( home_url( '/blueworx-oauth/authorize' ), $params );
		wp_safe_redirect( wp_login_url( $here ) );
		exit;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		blueworx_mcp_render_consent_message(
			__( 'Only administrators can connect Claude', 'blueworx-labs-wordpress' ),
			__( 'Ask an administrator of this site to make the connection.', 'blueworx-labs-wordpress' ),
			403
		);
	}

	if ( 'POST' !== $method ) {
		blueworx_mcp_render_consent( $client, $params );
	}

	check_admin_referer( 'blueworx_mcp_authorize_' . $client['client_id'] );

	if ( empty( $_POST['blueworx_mcp_allow'] ) ) {
		wp_redirect( // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- a registered client address, checked above.
			blueworx_mcp_redirect_with(
				$params['redirect_uri'],
				array(
					'error' => 'access_denied',
					'state' => $state,
				)
			)
		);
		exit;
	}

	$code = blueworx_mcp_random( 32 );

	set_transient(
		'blueworx_mcp_code_' . blueworx_mcp_hash( $code ),
		array(
			'client_id'      => $client['client_id'],
			'redirect_uri'   => $params['redirect_uri'],
			'code_challenge' => $params['code_challenge'],
			'user_id'        => get_current_user_id(),
			'issued'         => time(),
			'expires'        => time() + BLUEWORX_MCP_CODE_TTL,
		),
		BLUEWORX_MCP_CODE_TTL
	);

	wp_redirect( // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- a registered client address, checked above.
		blueworx_mcp_redirect_with(
			$params['redirect_uri'],
			array(
				'code'  => $code,
				'state' => $state,
			)
		)
	);
	exit;
}

/**
 * Prints the top of a standalone consent page.
 *
 * @param string $title  Page title.
 * @param int    $status HTTP status.
 * @return void
 */
function blueworx_mcp_consent_head( $title, $status = 200 ) {
	status_header( $status );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	// Nobody may frame this page: a framed consent screen is how a click on
	// "Allow" gets tricked out of somebody.
	header( 'X-Frame-Options: DENY' );
	header( "Content-Security-Policy: frame-ancestors 'none'" );
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex" />
<title><?php echo esc_html( $title ); ?></title>
<style>
	:root { --bg:#f4f6fb; --card:#fff; --ink:#111827; --muted:#4b5563; --line:#e5e7eb; --brand:#1d4ed8; --brand-ink:#fff; }
	@media (prefers-color-scheme: dark) { :root { --bg:#0b1020; --card:#131a2e; --ink:#f3f4f6; --muted:#9ca3af; --line:#253049; --brand:#3b82f6; } }
	* { box-sizing:border-box; }
	body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px 16px; background:var(--bg); color:var(--ink); font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif; }
	main { width:100%; max-width:460px; background:var(--card); border:1px solid var(--line); border-radius:14px; padding:28px; }
	h1 { font-size:1.35rem; margin:0 0 8px; }
	p { margin:0 0 14px; color:var(--muted); }
	ul { margin:0 0 18px; padding-left:20px; }
	li { margin-bottom:6px; }
	.bw-host { font-family:ui-monospace,Consolas,monospace; font-size:.9rem; color:var(--ink); word-break:break-all; }
	.bw-actions { display:flex; gap:10px; flex-wrap:wrap; }
	button { font:inherit; padding:10px 18px; border-radius:8px; border:1px solid var(--line); background:var(--card); color:var(--ink); cursor:pointer; }
	button.bw-allow { background:var(--brand); border-color:var(--brand); color:var(--brand-ink); font-weight:600; }
	button:focus-visible { outline:3px solid var(--brand); outline-offset:2px; }
</style>
</head>
<body>
<main>
	<?php
}

/**
 * Shows a message page and stops.
 *
 * @param string $title   Heading.
 * @param string $message Explanation.
 * @param int    $status  HTTP status.
 * @return void
 */
function blueworx_mcp_render_consent_message( $title, $message, $status ) {
	blueworx_mcp_consent_head( $title, $status );
	echo '<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p></main></body></html>';
	exit;
}

/**
 * Shows the consent screen and stops.
 *
 * @param array $client Client asking.
 * @param array $params Validated authorisation parameters, carried through the form.
 * @return void
 */
function blueworx_mcp_render_consent( $client, $params ) {
	$host = (string) wp_parse_url( $params['redirect_uri'], PHP_URL_HOST );
	$user = wp_get_current_user();

	blueworx_mcp_consent_head( __( 'Connect Claude to this site', 'blueworx-labs-wordpress' ) );
	?>
	<h1><?php esc_html_e( 'Connect Claude to this site?', 'blueworx-labs-wordpress' ); ?></h1>
	<p>
		<?php
		printf(
			/* translators: 1: app name, 2: site name. */
			esc_html__( '%1$s wants to work on %2$s as you.', 'blueworx-labs-wordpress' ),
			'<strong>' . esc_html( $client['client_name'] ) . '</strong>',
			'<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>'
		);
		?>
	</p>
	<p><?php esc_html_e( 'It will be able to:', 'blueworx-labs-wordpress' ); ?></p>
	<ul>
		<li><?php esc_html_e( 'Read your pages, posts and other content, including drafts', 'blueworx-labs-wordpress' ); ?></li>
		<li><?php esc_html_e( 'Create and edit content, and move it to the bin', 'blueworx-labs-wordpress' ); ?></li>
		<li><?php esc_html_e( 'Create categories and tags', 'blueworx-labs-wordpress' ); ?></li>
	</ul>
	<p><?php esc_html_e( 'It cannot upload or delete files, change plugins, themes or settings, or manage users.', 'blueworx-labs-wordpress' ); ?></p>
	<p>
		<?php
		printf(
			/* translators: 1: the signed-in user's name, 2: the address the app returns to. */
			esc_html__( 'Signed in as %1$s. You will be returned to %2$s.', 'blueworx-labs-wordpress' ),
			'<strong>' . esc_html( $user->display_name ) . '</strong>',
			'<span class="bw-host">' . esc_html( $host ) . '</span>'
		);
		?>
	</p>
	<form method="post" action="<?php echo esc_url( home_url( '/blueworx-oauth/authorize' ) ); ?>">
		<?php wp_nonce_field( 'blueworx_mcp_authorize_' . $client['client_id'] ); ?>
		<?php foreach ( $params as $name => $value ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
		<?php endforeach; ?>
		<div class="bw-actions">
			<button type="submit" class="bw-allow" name="blueworx_mcp_allow" value="1" data-testid="bw-mcp-allow"><?php esc_html_e( 'Allow', 'blueworx-labs-wordpress' ); ?></button>
			<button type="submit" name="blueworx_mcp_deny" value="1" data-testid="bw-mcp-deny"><?php esc_html_e( 'Cancel', 'blueworx-labs-wordpress' ); ?></button>
		</div>
	</form>
</main>
</body>
</html>
	<?php
	exit;
}

/**
 * Option: the recent-activity log.
 */
const BLUEWORX_MCP_OPTION_LOG = 'blueworx_mcp_log';

/**
 * Records something Claude did, for the admin screen.
 *
 * Only changes and connections are recorded — reads are not, or the log would
 * be nothing but reads.
 *
 * @param int    $user_id     Whose connection it was.
 * @param string $client_name App name.
 * @param string $action      What happened.
 * @param string $target      What it happened to.
 * @return void
 */
function blueworx_mcp_log( $user_id, $client_name, $action, $target ) {
	$log = get_option( BLUEWORX_MCP_OPTION_LOG, array() );
	$log = is_array( $log ) ? $log : array();

	array_unshift(
		$log,
		array(
			'time'   => time(),
			'user'   => (int) $user_id,
			'client' => (string) $client_name,
			'action' => (string) $action,
			'target' => (string) $target,
		)
	);

	update_option( BLUEWORX_MCP_OPTION_LOG, array_slice( $log, 0, 50 ), false );
}
