<?php
/**
 * Provider discovery: caching, manual overrides, and capability probing.
 *
 * Run with: php tests/php/discovery-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

$GLOBALS['options'] = array(
	'blueworx_sso_issuer'                   => 'https://idp.test',
	'blueworx_sso_token_endpoint_override'  => 'https://override.test/token',
);

$GLOBALS['http'] = array(
	'https://idp.test/.well-known/openid-configuration' => array(
		'issuer'                              => 'https://idp.test',
		'authorization_endpoint'              => 'https://idp.test/authorize',
		'token_endpoint'                      => 'https://idp.test/token',
		'userinfo_endpoint'                   => 'https://idp.test/userinfo',
		'jwks_uri'                            => 'https://idp.test/keys',
		'code_challenge_methods_supported'    => array( 'S256' ),
		'token_endpoint_auth_methods_supported' => array( 'client_secret_basic', 'client_secret_post' ),
	),
);

require __DIR__ . '/../../includes/sso/sso.php';
require __DIR__ . '/../../includes/sso/discovery.php';

check( 'the authorization endpoint comes from discovery', blueworx_sso_endpoint( 'authorization_endpoint' ), 'https://idp.test/authorize' );
check( 'a manual override beats discovery', blueworx_sso_endpoint( 'token_endpoint' ), 'https://override.test/token' );
check( 'the JWKS URI comes from discovery', blueworx_sso_endpoint( 'jwks_uri' ), 'https://idp.test/keys' );
check( 'S256 is detected', blueworx_sso_discovery_supports( 'code_challenge_methods_supported', 'S256' ), true );
check( 'plain is not claimed', blueworx_sso_discovery_supports( 'code_challenge_methods_supported', 'plain' ), false );
check( 'an unlisted key is not claimed', blueworx_sso_discovery_supports( 'nothing_supported', 'anything' ), false );

// Everything above went through one issuer, so the document must have been
// fetched exactly once: an uncached discovery would add a network round trip to
// every single sign-in.
check( 'the document is fetched once and cached', count( $GLOBALS['calls'] ), 1 );

check( 'an unreachable issuer is an error', is_wp_error( blueworx_sso_discover( 'https://nope.test' ) ), true );
check( 'an empty issuer is an error', is_wp_error( blueworx_sso_discover( '' ) ), true );

// A trailing slash on the issuer is the commonest paste error, and must not
// produce a double slash in the well-known URL or a second cache entry.
$GLOBALS['calls'] = array();
check( 'a trailing slash is tolerated', is_wp_error( blueworx_sso_discover( 'https://idp.test/' ) ), false );
check( 'and does not refetch', count( $GLOBALS['calls'] ), 0 );

// A document with no authorization endpoint is unusable, however well-formed.
$GLOBALS['http']['https://empty.test/.well-known/openid-configuration'] = array( 'issuer' => 'https://empty.test' );
check( 'a document with no endpoints is an error', is_wp_error( blueworx_sso_discover( 'https://empty.test' ) ), true );

finish();
