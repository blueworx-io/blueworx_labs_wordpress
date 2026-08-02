<?php
/**
 * Sign-in token verification.
 *
 * Generates a real RSA key at run time, signs tokens with it, and asserts that a
 * good one verifies and that every tampered variant is refused. This is the most
 * important test in the feature: everything downstream trusts these claims.
 *
 * Run with: php tests/php/jwt-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

$GLOBALS['options'] = array(
	'blueworx_sso_issuer'             => 'https://idp.test',
	'blueworx_sso_jwks_uri_override'  => 'https://idp.test/keys',
);

/**
 * Generates an RSA key pair and its JWK representation.
 *
 * @param string $kid Key ID to advertise.
 * @return array Key resource, and the matching JWK array.
 */
function make_key( $kid ) {
	// The config path is explicit because Windows PHP ships without an
	// openssl.cnf, and key generation fails without one.
	$key = openssl_pkey_new(
		array(
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
			'config'           => __DIR__ . '/openssl.cnf',
		)
	);

	if ( false === $key ) {
		echo "FAIL could not generate a test key: " . openssl_error_string() . "\n";
		exit( 1 );
	}

	$details = openssl_pkey_get_details( $key );

	return array(
		'key' => $key,
		'jwk' => array(
			'kty' => 'RSA',
			'alg' => 'RS256',
			'use' => 'sig',
			'kid' => $kid,
			'n'   => blueworx_sso_base64url_encode( $details['rsa']['n'] ),
			'e'   => blueworx_sso_base64url_encode( $details['rsa']['e'] ),
		),
	);
}

require __DIR__ . '/../../includes/sso/sso.php';
require __DIR__ . '/../../includes/sso/discovery.php';
require __DIR__ . '/../../includes/sso/jwt.php';

$signing = make_key( 'key-1' );
$other   = make_key( 'key-1' ); // Same advertised kid, different key: a forgery.

$GLOBALS['http']['https://idp.test/keys'] = array( 'keys' => array( $signing['jwk'] ) );

/**
 * Builds a signed token, after letting a mutation tamper with it.
 *
 * @param callable $mutate  Receives the header and claims by reference.
 * @param resource $key     Key to sign with.
 * @return string Compact JWS.
 */
function make_token( $mutate, $key ) {
	$header = array(
		'alg' => 'RS256',
		'typ' => 'JWT',
		'kid' => 'key-1',
	);

	$claims = array(
		'iss'            => 'https://idp.test',
		'aud'            => 'test-client',
		'sub'            => 'provider-subject-1',
		'exp'            => time() + 300,
		'iat'            => time(),
		'nonce'          => 'the-nonce',
		'email'          => 'person@example.test',
		'email_verified' => true,
	);

	$mutate( $header, $claims );

	$signing_input = blueworx_sso_base64url_encode( wp_json_encode( $header ) ) . '.' . blueworx_sso_base64url_encode( wp_json_encode( $claims ) );

	$signature = '';
	openssl_sign( $signing_input, $signature, $key, OPENSSL_ALGO_SHA256 );

	return $signing_input . '.' . blueworx_sso_base64url_encode( $signature );
}

/**
 * Verifies a token built from a mutation and returns the error code, or ''.
 *
 * @param callable $mutate Mutation to apply.
 * @param resource $key    Key to sign with.
 * @return string Error code, or an empty string when it verified.
 */
function outcome( $mutate, $key ) {
	$result = blueworx_sso_verify_id_token( make_token( $mutate, $key ), 'https://idp.test', 'test-client', 'the-nonce' );

	return is_wp_error( $result ) ? $result->get_error_code() : '';
}

$noop = function ( &$header, &$claims ) {};

check( 'a good token verifies', outcome( $noop, $signing['key'] ), '' );

// The single most important check in the feature: a token that says it needs no
// signature must never be believed, however well-formed it is.
check( 'alg none is refused', outcome( function ( &$h, &$c ) { $h['alg'] = 'none'; }, $signing['key'] ), 'blueworx_sso_bad_alg' );
check( 'a symmetric alg is refused', outcome( function ( &$h, &$c ) { $h['alg'] = 'HS256'; }, $signing['key'] ), 'blueworx_sso_bad_alg' );

check( 'a forged signature is refused', outcome( $noop, $other['key'] ), 'blueworx_sso_bad_signature' );
check( 'an unknown key is refused', outcome( function ( &$h, &$c ) { $h['kid'] = 'key-99'; }, $signing['key'] ), 'blueworx_sso_unknown_key' );
check( 'a wrong issuer is refused', outcome( function ( &$h, &$c ) { $c['iss'] = 'https://evil.test'; }, $signing['key'] ), 'blueworx_sso_bad_issuer' );
check( 'a wrong audience is refused', outcome( function ( &$h, &$c ) { $c['aud'] = 'someone-else'; }, $signing['key'] ), 'blueworx_sso_bad_audience' );
check( 'an expired token is refused', outcome( function ( &$h, &$c ) { $c['exp'] = time() - 600; }, $signing['key'] ), 'blueworx_sso_expired' );
check( 'a missing expiry is refused', outcome( function ( &$h, &$c ) { unset( $c['exp'] ); }, $signing['key'] ), 'blueworx_sso_expired' );
check( 'an issue time in the future is refused', outcome( function ( &$h, &$c ) { $c['iat'] = time() + 600; }, $signing['key'] ), 'blueworx_sso_bad_iat' );
check( 'a replayed nonce is refused', outcome( function ( &$h, &$c ) { $c['nonce'] = 'a-different-nonce'; }, $signing['key'] ), 'blueworx_sso_bad_nonce' );
check( 'a missing nonce is refused', outcome( function ( &$h, &$c ) { unset( $c['nonce'] ); }, $signing['key'] ), 'blueworx_sso_bad_nonce' );

// An audience array is legal and common when a token is issued for more than one
// client, so long as ours is in it.
check( 'an audience array containing us verifies', outcome( function ( &$h, &$c ) { $c['aud'] = array( 'other', 'test-client' ); }, $signing['key'] ), '' );

// Small clock differences between two servers are normal and must not lock
// people out; a token a minute past expiry is still within tolerance.
check( 'a small clock difference is tolerated', outcome( function ( &$h, &$c ) { $c['exp'] = time() - 60; }, $signing['key'] ), '' );

$malformed = blueworx_sso_verify_id_token( 'not.a.token', 'https://idp.test', 'test-client', 'the-nonce' );
check( 'a malformed token is refused', $malformed->get_error_code(), 'blueworx_sso_malformed' );

$two_parts = blueworx_sso_verify_id_token( 'aaa.bbb', 'https://idp.test', 'test-client', 'the-nonce' );
check( 'a token with too few parts is refused', $two_parts->get_error_code(), 'blueworx_sso_malformed' );

// Key rotation: a provider that swaps its signing key publishes the new one at
// the same URL. A cached key set must not lock the site out until it expires.
$rotated = make_key( 'key-2' );
$GLOBALS['http']['https://idp.test/keys'] = array( 'keys' => array( $rotated['jwk'] ) );

$rotated_token = blueworx_sso_verify_id_token(
	make_token( function ( &$h, &$c ) { $h['kid'] = 'key-2'; }, $rotated['key'] ),
	'https://idp.test',
	'test-client',
	'the-nonce'
);

check( 'a rotated key is picked up without waiting for the cache', is_wp_error( $rotated_token ), false );

finish();
