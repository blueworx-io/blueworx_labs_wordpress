<?php
/**
 * Single sign-on: sign-in token verification.
 *
 * The identity provider proves who someone is with a signed token. Everything
 * downstream — matching, provisioning, roles — trusts what this file returns, so
 * it fails closed on anything it cannot fully check, and only ever accepts RS256
 * signed by a key the provider itself publishes.
 *
 * Verification is done with PHP's own OpenSSL functions rather than a JWT
 * library, which keeps the plugin dependency-free.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches the provider's public key set.
 *
 * @param bool $force_refresh Whether to bypass the cache, for key rotation.
 * @return array|WP_Error Key set, or an error.
 */
function blueworx_sso_jwks( $force_refresh = false ) {
	$cache_key = 'blueworx_sso_jwks';

	if ( ! $force_refresh ) {
		$cached = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}
	}

	$jwks_uri = blueworx_sso_endpoint( 'jwks_uri' );

	if ( '' === $jwks_uri ) {
		return new WP_Error( 'blueworx_sso_no_jwks', __( 'The identity provider does not publish any signing keys.', 'blueworx-labs-wordpress' ) );
	}

	$response = wp_remote_get( $jwks_uri, array( 'timeout' => 10 ) );

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'blueworx_sso_jwks_failed', __( 'The identity provider did not return its signing keys.', 'blueworx-labs-wordpress' ) );
	}

	$keys = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $keys ) || empty( $keys['keys'] ) || ! is_array( $keys['keys'] ) ) {
		return new WP_Error( 'blueworx_sso_jwks_invalid', __( 'The identity provider returned unusable signing keys.', 'blueworx-labs-wordpress' ) );
	}

	set_transient( $cache_key, $keys, 12 * HOUR_IN_SECONDS );

	return $keys;
}

/**
 * Converts an RSA JWK into a PEM public key.
 *
 * A JWK carries the modulus and exponent as raw base64url. OpenSSL wants a DER
 * SubjectPublicKeyInfo structure, so the two integers are wrapped by hand.
 *
 * @param array $jwk Key in JWK form.
 * @return string PEM public key, or an empty string when the key is unusable.
 */
function blueworx_sso_jwk_to_pem( $jwk ) {
	if ( empty( $jwk['kty'] ) || 'RSA' !== $jwk['kty'] || empty( $jwk['n'] ) || empty( $jwk['e'] ) ) {
		return '';
	}

	$modulus  = blueworx_sso_base64url_decode( $jwk['n'] );
	$exponent = blueworx_sso_base64url_decode( $jwk['e'] );

	if ( '' === $modulus || '' === $exponent ) {
		return '';
	}

	$sequence = blueworx_sso_der_tag( 0x30, blueworx_sso_der_integer( $modulus ) . blueworx_sso_der_integer( $exponent ) );

	// The rsaEncryption algorithm identifier, then the key bit string.
	$algorithm  = blueworx_sso_der_tag( 0x30, "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00" );
	$bit_string = blueworx_sso_der_tag( 0x03, "\x00" . $sequence );
	$der        = blueworx_sso_der_tag( 0x30, $algorithm . $bit_string );

	return "-----BEGIN PUBLIC KEY-----\n"
		. chunk_split( base64_encode( $der ), 64, "\n" ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- PEM encoding.
		. "-----END PUBLIC KEY-----\n";
}

/**
 * Wraps a payload in a DER tag with its length.
 *
 * @param int    $tag     DER tag byte.
 * @param string $payload Raw payload.
 * @return string Tagged DER.
 */
function blueworx_sso_der_tag( $tag, $payload ) {
	$length = strlen( $payload );

	if ( $length < 128 ) {
		$encoded_length = chr( $length );
	} else {
		$bytes          = ltrim( pack( 'N', $length ), "\x00" );
		$encoded_length = chr( 0x80 | strlen( $bytes ) ) . $bytes;
	}

	return chr( $tag ) . $encoded_length . $payload;
}

/**
 * Encodes raw bytes as a DER positive INTEGER.
 *
 * @param string $bytes Big-endian integer bytes.
 * @return string DER INTEGER.
 */
function blueworx_sso_der_integer( $bytes ) {
	$bytes = ltrim( $bytes, "\x00" );

	// A leading bit of 1 would read as a negative number, so pad it.
	if ( '' === $bytes || ord( $bytes[0] ) > 0x7f ) {
		$bytes = "\x00" . $bytes;
	}

	return blueworx_sso_der_tag( 0x02, $bytes );
}

/**
 * Finds the PEM public key for a key ID.
 *
 * @param string $kid           Key ID from the token header.
 * @param bool   $force_refresh Whether to refetch the key set first.
 * @return string PEM public key, or an empty string when not found.
 */
function blueworx_sso_pem_for_kid( $kid, $force_refresh = false ) {
	$jwks = blueworx_sso_jwks( $force_refresh );

	if ( is_wp_error( $jwks ) ) {
		return '';
	}

	foreach ( $jwks['keys'] as $jwk ) {
		if ( ! is_array( $jwk ) ) {
			continue;
		}

		$key_id = isset( $jwk['kid'] ) ? (string) $jwk['kid'] : '';

		// A provider with a single key need not name it, and some do not.
		if ( $key_id === $kid || ( '' === $kid && 1 === count( $jwks['keys'] ) ) ) {
			return blueworx_sso_jwk_to_pem( $jwk );
		}
	}

	return '';
}

/**
 * Verifies a sign-in token and returns its claims.
 *
 * Order matters: the signing method is checked before anything else, because a
 * token claiming it needs no signature must never get as far as being read.
 *
 * @param string $jwt       Raw compact token.
 * @param string $issuer    Issuer the site is configured for.
 * @param string $client_id Client ID the site is configured for.
 * @param string $nonce     Nonce sent with this sign-in attempt.
 * @return array|WP_Error Verified claims, or an error naming the exact failure.
 */
function blueworx_sso_verify_id_token( $jwt, $issuer, $client_id, $nonce ) {
	$parts = explode( '.', (string) $jwt );

	if ( 3 !== count( $parts ) ) {
		return new WP_Error( 'blueworx_sso_malformed', __( 'The sign-in token was malformed.', 'blueworx-labs-wordpress' ) );
	}

	$header = json_decode( blueworx_sso_base64url_decode( $parts[0] ), true );
	$claims = json_decode( blueworx_sso_base64url_decode( $parts[1] ), true );

	if ( ! is_array( $header ) || ! is_array( $claims ) ) {
		return new WP_Error( 'blueworx_sso_malformed', __( 'The sign-in token was malformed.', 'blueworx-labs-wordpress' ) );
	}

	// RS256 only. "none" and the symmetric algorithms are the classic way to
	// forge one of these, and neither is ever legitimate here.
	if ( empty( $header['alg'] ) || 'RS256' !== $header['alg'] ) {
		return new WP_Error( 'blueworx_sso_bad_alg', __( 'The sign-in token used an unsupported signing method.', 'blueworx-labs-wordpress' ) );
	}

	$kid = isset( $header['kid'] ) ? (string) $header['kid'] : '';
	$pem = blueworx_sso_pem_for_kid( $kid );

	// A key we have not seen usually means the provider rotated it. Refetch once
	// rather than locking the site out until the cache expires.
	if ( '' === $pem ) {
		$pem = blueworx_sso_pem_for_kid( $kid, true );
	}

	if ( '' === $pem ) {
		return new WP_Error( 'blueworx_sso_unknown_key', __( 'The sign-in token was signed with an unknown key.', 'blueworx-labs-wordpress' ) );
	}

	$verified = openssl_verify(
		$parts[0] . '.' . $parts[1],
		blueworx_sso_base64url_decode( $parts[2] ),
		$pem,
		OPENSSL_ALGO_SHA256
	);

	if ( 1 !== $verified ) {
		return new WP_Error( 'blueworx_sso_bad_signature', __( 'The sign-in token signature did not check out.', 'blueworx-labs-wordpress' ) );
	}

	$now = time();

	// Two servers rarely agree on the time to the second, and a strict comparison
	// would lock people out for no security gain.
	$skew = 120;

	$token_issuer = isset( $claims['iss'] ) ? (string) $claims['iss'] : '';

	if ( untrailingslashit( $token_issuer ) !== untrailingslashit( (string) $issuer ) ) {
		return new WP_Error( 'blueworx_sso_bad_issuer', __( 'The sign-in token came from the wrong identity provider.', 'blueworx-labs-wordpress' ) );
	}

	$audience = isset( $claims['aud'] ) ? (array) $claims['aud'] : array();

	if ( ! in_array( (string) $client_id, array_map( 'strval', $audience ), true ) ) {
		return new WP_Error( 'blueworx_sso_bad_audience', __( 'The sign-in token was issued for a different application.', 'blueworx-labs-wordpress' ) );
	}

	if ( empty( $claims['exp'] ) || (int) $claims['exp'] + $skew < $now ) {
		return new WP_Error( 'blueworx_sso_expired', __( 'The sign-in token had expired.', 'blueworx-labs-wordpress' ) );
	}

	if ( empty( $claims['iat'] ) || (int) $claims['iat'] - $skew > $now ) {
		return new WP_Error( 'blueworx_sso_bad_iat', __( 'The sign-in token was issued in the future.', 'blueworx-labs-wordpress' ) );
	}

	// The nonce ties this token to the sign-in this browser started, which is what
	// stops a token captured elsewhere being replayed here.
	if ( '' !== (string) $nonce ) {
		if ( ! isset( $claims['nonce'] ) || ! hash_equals( (string) $nonce, (string) $claims['nonce'] ) ) {
			return new WP_Error( 'blueworx_sso_bad_nonce', __( 'The sign-in token did not match this sign-in attempt.', 'blueworx-labs-wordpress' ) );
		}
	}

	return $claims;
}
