<?php
/**
 * Connect Claude: the sign-in rules that must hold before anything else does.
 *
 * Which addresses are claimed, PKCE, which redirect addresses may be
 * registered, how tokens are read, that codes and refresh tokens work once,
 * that a lost refresh reply can be retried, that switching off disconnects
 * everyone, and that registration cannot be flooded.
 * tests/mcp-connection.spec.js drives the same flow through a browser.
 *
 * Run with: php tests/php/mcp-oauth-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

function wp_cache_delete( $key, $group = '' ) {
	return true;
}

function wp_unslash( $value ) {
	return $value;
}

function blueworx_feature_enabled( $feature ) {
	return 'mcp' === $feature && '0' !== get_option( 'blueworx_feature_mcp', '1' );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function home_url( $path = '' ) {
	return 'https://example.com' . $GLOBALS['sites'][ $GLOBALS['site_in_use'] ]['path_no_slash'] . $path;
}

function get_bloginfo( $what ) {
	return 'Example';
}

class WP_User {
	public $ID = 7;
}

function get_userdata( $id ) {
	return 7 === (int) $id ? new WP_User() : false;
}

function user_can( $user, $cap ) {
	return 'manage_options' === $cap && ! empty( $GLOBALS['is_admin'] );
}

/*
 * A network, for the checks that need one. Each site keeps its own options, as
 * on a real network, so switching site swaps the whole set in and out.
 */
define( 'BLUEWORX_LABS_PLUGIN_FILE', '/plugins/blueworx-labs-wordpress/blueworx-labs-wordpress.php' );

function is_multisite() {
	return ! empty( $GLOBALS['multisite'] );
}

function is_subdomain_install() {
	return ! empty( $GLOBALS['subdomain_install'] );
}

function get_current_blog_id() {
	return $GLOBALS['site_in_use'];
}

function get_site( $site_id = null ) {
	$site_id = null === $site_id ? $GLOBALS['site_in_use'] : (int) $site_id;

	if ( ! isset( $GLOBALS['sites'][ $site_id ] ) ) {
		return null;
	}

	$site = $GLOBALS['sites'][ $site_id ];

	return (object) array(
		'blog_id'  => (string) $site_id,
		'domain'   => 'example.com',
		'path'     => $site['path_no_slash'] . '/',
		'archived' => isset( $site['archived'] ) ? $site['archived'] : '0',
		'spam'     => '0',
		'deleted'  => '0',
	);
}

function get_network() {
	return (object) array(
		'domain' => 'example.com',
		'path'   => '/',
	);
}

function get_site_by_path( $domain, $path, $segments = null ) {
	$GLOBALS['site_lookups'][] = array( $domain, $path, $segments );
	$first                     = (string) strtok( trim( $path, '/' ), '/' );

	foreach ( $GLOBALS['sites'] as $id => $site ) {
		if ( '' !== $first && '/' . $first === $site['path_no_slash'] ) {
			return get_site( $id );
		}
	}

	return get_site( 1 );
}

function switch_to_blog( $site_id ) {
	$GLOBALS['blog_stack'][]                            = $GLOBALS['site_in_use'];
	$GLOBALS['blog_options'][ $GLOBALS['site_in_use'] ] = $GLOBALS['options'];
	$GLOBALS['site_in_use']                             = (int) $site_id;
	$GLOBALS['options']                                 = isset( $GLOBALS['blog_options'][ $site_id ] ) ? $GLOBALS['blog_options'][ $site_id ] : array();
	return true;
}

function restore_current_blog() {
	$GLOBALS['blog_options'][ $GLOBALS['site_in_use'] ] = $GLOBALS['options'];
	$GLOBALS['site_in_use']                             = array_pop( $GLOBALS['blog_stack'] );
	$GLOBALS['options']                                 = $GLOBALS['blog_options'][ $GLOBALS['site_in_use'] ];
	return true;
}

function get_site_option( $key, $fallback = false ) {
	return array_key_exists( $key, $GLOBALS['network_options'] ) ? $GLOBALS['network_options'][ $key ] : $fallback;
}

function plugin_basename( $file ) {
	return 'blueworx-labs-wordpress/blueworx-labs-wordpress.php';
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

$GLOBALS['is_admin']          = true;
$GLOBALS['multisite']         = false;
$GLOBALS['subdomain_install'] = false;
$GLOBALS['site_in_use']       = 1;
$GLOBALS['blog_stack']        = array();
$GLOBALS['blog_options']      = array();
$GLOBALS['network_options']   = array();
$GLOBALS['site_lookups']      = array();
$GLOBALS['sites']             = array(
	1 => array( 'path_no_slash' => '' ),
);

require __DIR__ . '/../../includes/mcp/oauth.php';

echo "Which addresses are claimed\n";

check( 'the MCP endpoint', blueworx_mcp_route_for_path( '/blueworx-mcp' ), 'mcp' );
check( 'resource metadata', blueworx_mcp_route_for_path( '/.well-known/oauth-protected-resource' ), 'resource_metadata' );
check( 'resource metadata with the resource path appended', blueworx_mcp_route_for_path( '/.well-known/oauth-protected-resource/blueworx-mcp' ), 'resource_metadata' );
check( 'server metadata', blueworx_mcp_route_for_path( '/.well-known/oauth-authorization-server' ), 'server_metadata' );
check( 'an unrelated page is left alone', blueworx_mcp_route_for_path( '/about-us' ), '' );
check( 'a look-alike prefix is left alone', blueworx_mcp_route_for_path( '/.well-known/oauth-protected-resourcex' ), '' );
check( 'a well-known path under the sign-in folder is left alone', blueworx_mcp_route_for_path( '/blueworx-oauth/.well-known/openid-configuration' ), '' );
check( 'the resource document names the MCP endpoint', blueworx_mcp_resource_metadata()['resource'], 'https://example.com/blueworx-mcp' );
check( 'and points at this site to sign in', blueworx_mcp_resource_metadata()['authorization_servers'], array( 'https://example.com' ) );
check( 'the server document asks for PKCE with S256 only', blueworx_mcp_server_metadata()['code_challenge_methods_supported'], array( 'S256' ) );
check( 'and names the registration address', blueworx_mcp_server_metadata()['registration_endpoint'], 'https://example.com/blueworx-oauth/register' );
check( 'a site in a subfolder is matched within it', blueworx_mcp_relative_path( '/blog/blueworx-mcp/?x=1', '/blog' ), '/blueworx-mcp' );

echo "\nBatches\n";

check( 'a JSON array is a batch', blueworx_mcp_is_list( json_decode( '[{"id":1},{"id":2}]', true ) ), true );
check( 'a JSON object is one message', blueworx_mcp_is_list( json_decode( '{"jsonrpc":"2.0","id":1}', true ) ), false );
check( 'numbered keys out of order are not a batch', blueworx_mcp_is_list( array( 1 => 'a', 0 => 'b' ) ), false );

echo "\nPKCE\n";

$verifier  = str_repeat( 'a', 43 );
$challenge = blueworx_mcp_base64url( hash( 'sha256', $verifier, true ) );

check( 'the right verifier matches', blueworx_mcp_pkce_matches( $verifier, $challenge ), true );
check( 'a wrong verifier does not', blueworx_mcp_pkce_matches( str_repeat( 'b', 43 ), $challenge ), false );
check( 'a too-short verifier is refused outright', blueworx_mcp_pkce_matches( 'abc', blueworx_mcp_base64url( hash( 'sha256', 'abc', true ) ) ), false );

echo "\nRedirect addresses\n";

check( 'HTTPS is allowed', blueworx_mcp_redirect_uri_allowed( 'https://claude.ai/api/mcp/auth_callback' ), true );
check( 'HTTP to localhost is allowed', blueworx_mcp_redirect_uri_allowed( 'http://localhost:3000/cb' ), true );
check( 'HTTP anywhere else is not', blueworx_mcp_redirect_uri_allowed( 'http://evil.example/cb' ), false );
check( 'a script address is not', blueworx_mcp_redirect_uri_allowed( 'javascript:alert(1)' ), false );
check( 'an address with a fragment is not', blueworx_mcp_redirect_uri_allowed( 'https://claude.ai/cb#x' ), false );

$bad = blueworx_mcp_register_client( array( 'redirect_uris' => array( 'http://evil.example/cb' ) ) );
check( 'registration refuses a bad redirect address', is_wp_error( $bad ) ? $bad->get_error_code() : 'accepted', 'invalid_redirect_uri' );

echo "\nTokens\n";

check( 'a well-formed access token parses', blueworx_mcp_parse_token( 'bwx_at_abcdefgh.' . str_repeat( 'x', 30 ), 'at' ), array( 'abcdefgh', str_repeat( 'x', 30 ) ) );
check( 'a refresh token is not accepted as an access token', blueworx_mcp_parse_token( 'bwx_rt_abcdefgh.' . str_repeat( 'x', 30 ), 'at' ), null );
check( 'garbage is refused', blueworx_mcp_parse_token( 'Bearer nonsense', 'at' ), null );

echo "\nThe whole exchange\n";

$client = blueworx_mcp_register_client(
	array(
		'client_name'   => 'Claude',
		'redirect_uris' => array( 'https://claude.ai/api/mcp/auth_callback' ),
	)
);
$client = blueworx_mcp_get_client( $client['client_id'] );

set_transient(
	'blueworx_mcp_code_' . blueworx_mcp_hash( 'the-code' ),
	array(
		'client_id'      => $client['client_id'],
		'redirect_uri'   => 'https://claude.ai/api/mcp/auth_callback',
		'code_challenge' => $challenge,
		'user_id'        => 7,
		'issued'         => time(),
		'expires'        => time() + 60,
	)
);

$tokens = blueworx_mcp_exchange_code( $client, 'the-code', 'https://claude.ai/api/mcp/auth_callback', $verifier );
check( 'a good code gets tokens', isset( $tokens['access_token'] ), true );

$grant_id = explode( '.', substr( $tokens['access_token'], 7 ) )[0];
$before   = $GLOBALS['options']['blueworx_mcp_grants'];

check( 'the access token is accepted', is_array( blueworx_mcp_verify_access_token( $tokens['access_token'] ) ), true );
check( 'using a token never rewrites the connection list', $GLOBALS['options']['blueworx_mcp_grants'], $before );
check( 'but it records when it was used', isset( blueworx_mcp_get_last_used()[ $grant_id ] ), true );

$again = blueworx_mcp_exchange_code( $client, 'the-code', 'https://claude.ai/api/mcp/auth_callback', $verifier );
check( 'the same code a second time is refused', is_wp_error( $again ) ? $again->get_error_code() : 'accepted', 'invalid_grant' );

$next = blueworx_mcp_refresh( $client, $tokens['refresh_token'] );
check( 'a refresh token works once', isset( $next['access_token'] ), true );

// The reply carrying $next was "lost": the client retries with the old token.
$retry = blueworx_mcp_refresh( $client, $tokens['refresh_token'] );
check( 'a retry with the replaced token inside a minute gets a fresh pair', isset( $retry['access_token'] ), true );
check( 'and the connection stays open', is_array( blueworx_mcp_verify_access_token( $retry['access_token'] ) ), true );

$retry = blueworx_mcp_refresh( $client, $tokens['refresh_token'] );
check( 'a second retry inside the same minute works too', isset( $retry['access_token'] ), true );

$GLOBALS['options']['blueworx_mcp_grants'][ $grant_id ]['rotated_at'] = time() - 120;

$replay = blueworx_mcp_refresh( $client, $tokens['refresh_token'] );
check( 'the same token a minute later is refused', is_wp_error( $replay ), true );
check( 'and closes the connection', blueworx_mcp_verify_access_token( $retry['access_token'] ), null );
check( 'and says why on the admin screen', $GLOBALS['options']['blueworx_mcp_log'][0]['action'], 'disconnected' );

echo "\nOnly administrators\n";

set_transient(
	'blueworx_mcp_code_' . blueworx_mcp_hash( 'code-2' ),
	array(
		'client_id'      => $client['client_id'],
		'redirect_uri'   => 'https://claude.ai/api/mcp/auth_callback',
		'code_challenge' => $challenge,
		'user_id'        => 7,
		'issued'         => time(),
		'expires'        => time() + 60,
	)
);
$tokens = blueworx_mcp_exchange_code( $client, 'code-2', 'https://claude.ai/api/mcp/auth_callback', $verifier );

$GLOBALS['is_admin'] = false;
check( 'a token stops working once its approver is no longer an administrator', blueworx_mcp_verify_access_token( $tokens['access_token'] ), null );
$GLOBALS['is_admin'] = true;

echo "\nRegistration\n";

$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$allowed                = 0;

for ( $i = 0; $i < BLUEWORX_MCP_REGISTER_LIMIT + 5; $i++ ) {
	$allowed += blueworx_mcp_registration_allowed() ? 1 : 0;
}

check( 'one address gets twenty registrations an hour', $allowed, BLUEWORX_MCP_REGISTER_LIMIT );

$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
check( 'another address is counted on its own', blueworx_mcp_registration_allowed(), true );

$GLOBALS['options']['blueworx_mcp_clients'] = array( $client['client_id'] => $client );

for ( $i = 1; $i < BLUEWORX_MCP_MAX_CLIENTS; $i++ ) {
	blueworx_mcp_register_client( array( 'redirect_uris' => array( 'https://example.org/cb' ) ) );
}

$full = blueworx_mcp_register_client( array( 'redirect_uris' => array( 'https://example.org/cb' ) ) );
check( 'a burst cannot push out registrations still waiting for approval', is_wp_error( $full ) ? $full->get_error_code() : 'accepted', 'temporarily_unavailable' );

foreach ( $GLOBALS['options']['blueworx_mcp_clients'] as $id => $entry ) {
	$GLOBALS['options']['blueworx_mcp_clients'][ $id ]['created'] = time() - 3600;
}

$room = blueworx_mcp_register_client( array( 'redirect_uris' => array( 'https://example.org/cb' ) ) );
check( 'once they are old and unused they make room', isset( $room['client_id'] ), true );
check( 'the list stays capped', count( $GLOBALS['options']['blueworx_mcp_clients'] ) <= BLUEWORX_MCP_MAX_CLIENTS, true );
check( 'an app with a live connection is kept', isset( $GLOBALS['options']['blueworx_mcp_clients'][ $client['client_id'] ] ), true );

echo "\nSwitching off\n";

set_transient(
	'blueworx_mcp_code_' . blueworx_mcp_hash( 'code-3' ),
	array(
		'client_id'      => $client['client_id'],
		'redirect_uri'   => 'https://claude.ai/api/mcp/auth_callback',
		'code_challenge' => $challenge,
		'user_id'        => 7,
		'issued'         => time(),
		'expires'        => time() + 60,
	)
);

blueworx_mcp_revoke_on_switch_off( '0', '1' );
check( 'switching on leaves connections alone', count( blueworx_mcp_get_grants() ) > 0, true );

blueworx_mcp_revoke_on_switch_off( '1', '0' );
check( 'switching off from any screen disconnects everyone', blueworx_mcp_get_grants(), array() );

$late = blueworx_mcp_exchange_code( $client, 'code-3', 'https://claude.ai/api/mcp/auth_callback', $verifier );
check( 'a code approved before the switch-off cannot be swapped afterwards', is_wp_error( $late ) ? $late->get_error_code() : 'accepted', 'invalid_grant' );

echo "\nSites on a folder-style network\n";

/*
 * A site at example.com/site2 has the issuer https://example.com/site2. The
 * standards tell a client to look for its documents at the domain root with
 * the path moved to the end — /.well-known/oauth-authorization-server/site2 —
 * and the root belongs to the main site. So the main site has to answer for
 * the site the address names, never with its own documents.
 */
$GLOBALS['options']['blueworx_feature_mcp'] = '1';

$server_for_site2   = '/.well-known/oauth-authorization-server/site2';
$resource_for_site2 = '/.well-known/oauth-protected-resource/site2/blueworx-mcp';

check( 'a single site never looks for another site', blueworx_mcp_network_site_for_path( $server_for_site2 ), 0 );
check( 'and asks nothing about one', $GLOBALS['site_lookups'], array() );

$GLOBALS['multisite']         = true;
$GLOBALS['subdomain_install'] = true;
check( 'nor does a network where each site has its own domain', blueworx_mcp_network_site_for_path( $server_for_site2 ), 0 );

$GLOBALS['subdomain_install'] = false;
$GLOBALS['sites'][2]          = array( 'path_no_slash' => '/site2' );
$GLOBALS['sites'][3]          = array( 'path_no_slash' => '/plugin-off' );
$GLOBALS['sites'][4]          = array( 'path_no_slash' => '/switched-off' );
$GLOBALS['sites'][5]          = array(
	'path_no_slash' => '/archived',
	'archived'      => '1',
);
$GLOBALS['blog_options'][2]   = array( 'active_plugins' => array( 'blueworx-labs-wordpress/blueworx-labs-wordpress.php' ) );
$GLOBALS['blog_options'][3]   = array( 'active_plugins' => array() );
$GLOBALS['blog_options'][4]   = array(
	'active_plugins'       => array( 'blueworx-labs-wordpress/blueworx-labs-wordpress.php' ),
	'blueworx_feature_mcp' => '0',
);
$GLOBALS['blog_options'][5]   = $GLOBALS['blog_options'][2];

check( 'the server document for a subsite is about that subsite', blueworx_mcp_network_site_for_path( $server_for_site2 ), 2 );
check( 'and so is its OpenID-style address', blueworx_mcp_network_site_for_path( '/.well-known/openid-configuration/site2' ), 2 );
check( 'and the resource document with its endpoint appended', blueworx_mcp_network_site_for_path( $resource_for_site2 ), 2 );
check( 'and the lookup is the one WordPress itself makes', end( $GLOBALS['site_lookups'] ), array( 'example.com', '/site2/blueworx-mcp/', 1 ) );
check( 'the main site\'s own resource path stays its own', blueworx_mcp_network_site_for_path( '/.well-known/oauth-protected-resource/blueworx-mcp' ), 0 );
check( 'and so does the bare address', blueworx_mcp_network_site_for_path( '/.well-known/oauth-authorization-server' ), 0 );
check( 'a folder that is not a site stays the main site\'s', blueworx_mcp_network_site_for_path( '/.well-known/oauth-authorization-server/no-such-site' ), 0 );
check( 'an address that is not a document is left alone', blueworx_mcp_network_site_for_path( '/site2/blueworx-mcp' ), 0 );

$GLOBALS['site_in_use'] = 2;
check( 'a subsite does not hand its own requests on', blueworx_mcp_network_site_for_path( $server_for_site2 ), 0 );
$GLOBALS['site_in_use'] = 1;

$server_doc = blueworx_mcp_network_metadata( 2, 'server_metadata' );
check( 'the main site answers with the subsite\'s issuer', $server_doc['issuer'], 'https://example.com/site2' );
check( 'and the subsite\'s own sign-in addresses', $server_doc['authorization_endpoint'], 'https://example.com/site2/blueworx-oauth/authorize' );
check( 'token', $server_doc['token_endpoint'], 'https://example.com/site2/blueworx-oauth/token' );
check( 'and registration', $server_doc['registration_endpoint'], 'https://example.com/site2/blueworx-oauth/register' );

$resource_doc = blueworx_mcp_network_metadata( 2, 'resource_metadata' );
check( 'the resource document names the subsite\'s endpoint', $resource_doc['resource'], 'https://example.com/site2/blueworx-mcp' );
check( 'and sends the client to the subsite to sign in', $resource_doc['authorization_servers'], array( 'https://example.com/site2' ) );

check( 'and afterwards the main site is itself again', blueworx_mcp_issuer(), 'https://example.com' );
check( 'with its own settings back', $GLOBALS['options']['blueworx_feature_mcp'], '1' );

// A subsite that has not got Connect Claude must not appear to have it.
check( 'nothing is offered for a site without this plugin', blueworx_mcp_network_metadata( 3, 'server_metadata' ), null );
check( 'nor for one that has switched Connect Claude off', blueworx_mcp_network_metadata( 4, 'server_metadata' ), null );
check( 'nor for an archived site', blueworx_mcp_network_metadata( 5, 'server_metadata' ), null );

$GLOBALS['network_options']['active_sitewide_plugins'] = array( 'blueworx-labs-wordpress/blueworx-labs-wordpress.php' => time() );
check( 'switched on for the whole network counts as having it', blueworx_mcp_network_metadata( 3, 'server_metadata' )['issuer'], 'https://example.com/plugin-off' );

// The main site switching Connect Claude off is the main site's choice, and
// does not stop it pointing Claude at a subsite that has it on.
$GLOBALS['options']['blueworx_feature_mcp'] = '0';
$_SERVER['REQUEST_URI']                     = $server_for_site2;
check( 'Site Protection lets a handed-on document through, even with Connect Claude off here', blueworx_mcp_exempt_from_site_protection( true ), false );
$_SERVER['REQUEST_URI'] = '/.well-known/oauth-authorization-server';
check( 'while its own documents stay behind it', blueworx_mcp_exempt_from_site_protection( true ), true );
unset( $_SERVER['REQUEST_URI'] );

$GLOBALS['multisite'] = false;

finish();
