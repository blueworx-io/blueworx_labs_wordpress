<?php
/**
 * Single sign-on: option access and shared helpers.
 *
 * The feature is a plain OpenID Connect relying party. Nothing in it knows which
 * provider a site uses: a site owner pastes an issuer, a client ID and a secret,
 * and anything site-specific attaches through the hooks fired in users.php and
 * flow.php.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads an SSO option.
 *
 * @param string $name    Option name without the blueworx_sso_ prefix.
 * @param mixed  $default Value to return when the option is unset.
 * @return mixed Stored value, or $default.
 */
function blueworx_sso_option( $name, $default = '' ) {
	return get_option( 'blueworx_sso_' . $name, $default );
}

/**
 * Whether single sign-on is on AND has enough configuration to work.
 *
 * A half-configured connection is worse than none: the button would appear and
 * every click would fail. Both conditions are checked everywhere the feature is
 * about to do something visible.
 *
 * @return bool True when the feature can actually sign someone in.
 */
function blueworx_sso_enabled() {
	if ( ! blueworx_feature_enabled( 'sso' ) ) {
		return false;
	}

	foreach ( array( 'issuer', 'client_id', 'client_secret' ) as $required ) {
		if ( '' === trim( (string) blueworx_sso_option( $required ) ) ) {
			return false;
		}
	}

	return true;
}

/**
 * The callback URL to hand the identity provider on a new setup.
 *
 * Existing setups may have registered something else — often the site root —
 * which is why the callback is recognised by its query parameters rather than by
 * its path. See blueworx_sso_dispatch().
 *
 * @return string Absolute URL.
 */
function blueworx_sso_callback_url() {
	return home_url( '/?blueworx_sso=callback' );
}

/**
 * The redirect URI sent to the provider, which must match what it has registered.
 *
 * @return string Absolute URL.
 */
function blueworx_sso_redirect_uri() {
	$configured = trim( (string) blueworx_sso_option( 'redirect_uri' ) );

	return '' !== $configured ? $configured : blueworx_sso_callback_url();
}

/**
 * Base64url-encodes a string, per RFC 7515.
 *
 * @param string $data Raw bytes.
 * @return string Encoded, unpadded.
 */
function blueworx_sso_base64url_encode( $data ) {
	return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Token encoding, not obfuscation.
}

/**
 * Base64url-decodes a string, per RFC 7515.
 *
 * @param string $data Encoded string.
 * @return string Raw bytes, or an empty string when undecodable.
 */
function blueworx_sso_base64url_decode( $data ) {
	$padded  = strtr( (string) $data, '-_', '+/' );
	$padding = strlen( $padded ) % 4;

	if ( $padding > 0 ) {
		$padded .= str_repeat( '=', 4 - $padding );
	}

	$decoded = base64_decode( $padded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Token decoding, not obfuscation.

	return false === $decoded ? '' : $decoded;
}

/**
 * A cryptographically random, URL-safe string of 43 characters.
 *
 * Used for state, nonce and the PKCE verifier — each of which must be
 * unguessable for the flow to be safe.
 *
 * @return string
 */
function blueworx_sso_random_string() {
	return blueworx_sso_base64url_encode( random_bytes( 32 ) );
}
