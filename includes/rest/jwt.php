<?php
/**
 * Headless REST layer — JWT access tokens.
 *
 * Thin wrapper over the bundled firebase/php-jwt library (HS256). The signing
 * secret comes only from the wp-config constant BLUEWORX_LABS_JWT_SECRET.
 *
 * @package BlueWorxLabs
 */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the bundled firebase/php-jwt classes (no Composer autoloader in the
 * shipped plugin). Loaded in dependency order.
 *
 * @return void
 */
function blueworx_headless_load_jwt_library() {
	if ( class_exists( 'Firebase\\JWT\\JWT' ) ) {
		return;
	}

	$lib = BLUEWORX_LABS_PATH . 'includes/rest/lib/firebase-jwt/';

	require_once $lib . 'JWTExceptionWithPayloadInterface.php';
	require_once $lib . 'BeforeValidException.php';
	require_once $lib . 'ExpiredException.php';
	require_once $lib . 'SignatureInvalidException.php';
	require_once $lib . 'Key.php';
	require_once $lib . 'JWT.php';
}

/**
 * The signing algorithm. Hard-coded so a token can never dictate its own
 * algorithm (prevents algorithm-confusion attacks).
 */
if ( ! defined( 'BLUEWORX_HEADLESS_JWT_ALG' ) ) {
	define( 'BLUEWORX_HEADLESS_JWT_ALG', 'HS256' );
}

/**
 * Encodes a claim set into a signed JWT.
 *
 * @param array $claims Claim set.
 * @return string|null Signed token, or null when no secret is configured.
 */
function blueworx_headless_jwt_encode( array $claims ) {
	$secret = blueworx_headless_jwt_secret();

	if ( '' === $secret ) {
		return null;
	}

	blueworx_headless_load_jwt_library();

	return JWT::encode( $claims, $secret, BLUEWORX_HEADLESS_JWT_ALG );
}

/**
 * Decodes and validates a JWT.
 *
 * Returns the claims only for a well-formed token with a valid signature,
 * unexpired, and issued by this site for this audience. Any failure returns
 * null (never throws to the caller).
 *
 * @param string $token Compact JWT string.
 * @return array|null Claims, or null when invalid.
 */
function blueworx_headless_jwt_decode( $token ) {
	$secret = blueworx_headless_jwt_secret();

	if ( '' === $secret || '' === (string) $token ) {
		return null;
	}

	blueworx_headless_load_jwt_library();

	try {
		$decoded = JWT::decode( $token, new Key( $secret, BLUEWORX_HEADLESS_JWT_ALG ) );
	} catch ( \Exception $e ) {
		return null;
	}

	$claims = (array) $decoded;

	if ( ! isset( $claims['iss'] ) || home_url() !== $claims['iss'] ) {
		return null;
	}

	if ( ! isset( $claims['aud'] ) || 'blueworx-headless' !== $claims['aud'] ) {
		return null;
	}

	return $claims;
}

/**
 * Issues an access token for a user.
 *
 * @param int $user_id User ID.
 * @return string|null Signed access token, or null when unconfigured.
 */
function blueworx_headless_issue_access_token( $user_id ) {
	$now = time();
	$ttl = blueworx_headless_access_ttl();

	$claims = array(
		'iss' => home_url(),
		'aud' => 'blueworx-headless',
		'sub' => (string) (int) $user_id,
		'iat' => $now,
		'nbf' => $now,
		'exp' => $now + $ttl,
		'tv'  => blueworx_headless_token_version( $user_id ),
		'jti' => wp_generate_uuid4(),
	);

	return blueworx_headless_jwt_encode( $claims );
}
