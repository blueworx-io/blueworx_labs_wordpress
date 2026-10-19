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
	return 'mcp' === $feature;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function home_url( $path = '' ) {
	return 'https://example.com' . $path;
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

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

$GLOBALS['is_admin'] = true;

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

finish();
