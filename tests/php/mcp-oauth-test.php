<?php
/**
 * Connect Claude: the sign-in rules that must hold before anything else does.
 *
 * Which addresses are claimed, PKCE, which redirect addresses may be
 * registered, how tokens are read, and that codes and refresh tokens work
 * once. tests/mcp-connection.spec.js drives the same flow through a browser.
 *
 * Run with: php tests/php/mcp-oauth-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

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
check( 'a site in a subfolder is matched within it', blueworx_mcp_relative_path( '/blog/blueworx-mcp/?x=1', '/blog' ), '/blueworx-mcp' );

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
		'expires'        => time() + 60,
	)
);

$tokens = blueworx_mcp_exchange_code( $client, 'the-code', 'https://claude.ai/api/mcp/auth_callback', $verifier );
check( 'a good code gets tokens', isset( $tokens['access_token'] ), true );
check( 'the access token is accepted', is_array( blueworx_mcp_verify_access_token( $tokens['access_token'] ) ), true );

$again = blueworx_mcp_exchange_code( $client, 'the-code', 'https://claude.ai/api/mcp/auth_callback', $verifier );
check( 'the same code a second time is refused', is_wp_error( $again ) ? $again->get_error_code() : 'accepted', 'invalid_grant' );

$next = blueworx_mcp_refresh( $client, $tokens['refresh_token'] );
check( 'a refresh token works once', isset( $next['access_token'] ), true );

$replay = blueworx_mcp_refresh( $client, $tokens['refresh_token'] );
check( 'replaying it is refused', is_wp_error( $replay ), true );
check( 'and closes the connection', blueworx_mcp_verify_access_token( $next['access_token'] ), null );

echo "\nOnly administrators\n";

set_transient(
	'blueworx_mcp_code_' . blueworx_mcp_hash( 'code-2' ),
	array(
		'client_id'      => $client['client_id'],
		'redirect_uri'   => 'https://claude.ai/api/mcp/auth_callback',
		'code_challenge' => $challenge,
		'user_id'        => 7,
		'expires'        => time() + 60,
	)
);
$tokens = blueworx_mcp_exchange_code( $client, 'code-2', 'https://claude.ai/api/mcp/auth_callback', $verifier );

$GLOBALS['is_admin'] = false;
check( 'a token stops working once its approver is no longer an administrator', blueworx_mcp_verify_access_token( $tokens['access_token'] ), null );

finish();
