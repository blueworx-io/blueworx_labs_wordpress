<?php
/**
 * Single sign-on: provider discovery.
 *
 * Every OpenID Connect provider publishes its endpoints at a well-known URL, so
 * a site owner only has to paste an issuer. Providers that do not publish one can
 * still be used by filling in the endpoint overrides on the settings screen.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches the provider's discovery document.
 *
 * Cached for twelve hours. Without a cache every sign-in would pay for an extra
 * network round trip before it could even start.
 *
 * @param string $issuer Issuer URL.
 * @return array|WP_Error Discovery document, or an error naming what went wrong.
 */
function blueworx_sso_discover( $issuer ) {
	$issuer = untrailingslashit( trim( (string) $issuer ) );

	if ( '' === $issuer ) {
		return new WP_Error( 'blueworx_sso_no_issuer', __( 'No identity provider is configured.', 'blueworx-labs-wordpress' ) );
	}

	$cache_key = 'blueworx_sso_discovery_' . md5( $issuer );
	$cached    = get_transient( $cache_key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$response = wp_remote_get(
		$issuer . '/.well-known/openid-configuration',
		array( 'timeout' => 10 )
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'blueworx_sso_discovery_failed', __( 'The identity provider did not answer.', 'blueworx-labs-wordpress' ) );
	}

	$document = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $document ) || empty( $document['authorization_endpoint'] ) ) {
		return new WP_Error( 'blueworx_sso_discovery_invalid', __( 'The identity provider returned a configuration this site cannot use.', 'blueworx-labs-wordpress' ) );
	}

	set_transient( $cache_key, $document, 12 * HOUR_IN_SECONDS );

	return $document;
}

/**
 * Resolves one endpoint, letting a manual override win over discovery.
 *
 * @param string $name Discovery key, e.g. token_endpoint.
 * @return string Endpoint URL, or an empty string when it cannot be resolved.
 */
function blueworx_sso_endpoint( $name ) {
	$override = trim( (string) get_option( 'blueworx_sso_' . $name . '_override', '' ) );

	if ( '' !== $override ) {
		return $override;
	}

	$document = blueworx_sso_discover( get_option( 'blueworx_sso_issuer', '' ) );

	if ( is_wp_error( $document ) || empty( $document[ $name ] ) ) {
		return '';
	}

	return (string) $document[ $name ];
}

/**
 * Whether the provider advertises a value in one of its "supported" lists.
 *
 * Used to decide whether to send a PKCE challenge and how to authenticate at the
 * token endpoint, rather than assuming and failing at sign-in time.
 *
 * @param string $key   Discovery key, e.g. code_challenge_methods_supported.
 * @param string $value Value to look for.
 * @return bool True when the provider lists it.
 */
function blueworx_sso_discovery_supports( $key, $value ) {
	$document = blueworx_sso_discover( get_option( 'blueworx_sso_issuer', '' ) );

	if ( is_wp_error( $document ) || empty( $document[ $key ] ) || ! is_array( $document[ $key ] ) ) {
		return false;
	}

	return in_array( $value, $document[ $key ], true );
}

/**
 * A short, human-readable connection status for the settings screen.
 *
 * @return array {
 *     @type bool   $connected Whether discovery succeeded.
 *     @type string $message   Sentence to show the site owner.
 * }
 */
function blueworx_sso_discovery_status() {
	$issuer = trim( (string) blueworx_sso_option( 'issuer' ) );

	if ( '' === $issuer ) {
		return array(
			'connected' => false,
			'message'   => __( 'No identity provider set yet.', 'blueworx-labs-wordpress' ),
		);
	}

	$document = blueworx_sso_discover( $issuer );

	if ( is_wp_error( $document ) ) {
		return array(
			'connected' => false,
			'message'   => $document->get_error_message(),
		);
	}

	/* translators: %s: identity provider issuer URL. */
	return array(
		'connected' => true,
		'message'   => sprintf( __( 'Connected to %s.', 'blueworx-labs-wordpress' ), $issuer ),
	);
}
