<?php
/**
 * Single sign-on: the authorization code flow.
 *
 * Outbound, this sends someone to their identity provider with a one-time state,
 * a nonce and a PKCE challenge. Inbound, it takes the code back, swaps it for
 * tokens, verifies them and signs the person in.
 *
 * Every failure ends the same way: the reason is recorded server-side and the
 * person is shown one generic message. Nothing about why a sign-in failed is
 * reflected back to the browser.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The transient key holding a live sign-in attempt.
 *
 * The state itself is never stored: a hash of it is enough to find the record,
 * and means the value cannot be read back out of the options table.
 *
 * @param string $state State parameter.
 * @return string Transient key.
 */
function blueworx_sso_attempt_key( $state ) {
	return 'blueworx_sso_attempt_' . hash( 'sha256', (string) $state );
}

/**
 * The local URL that starts a sign-in.
 *
 * @param string $redirect_to Optional. Where to send the person afterwards.
 * @return string Absolute URL.
 */
function blueworx_sso_login_url( $redirect_to = '' ) {
	$url = home_url( '/?blueworx_sso=login' );

	if ( '' !== $redirect_to ) {
		$url = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $url );
	}

	return $url;
}

/**
 * Whether to send a PKCE challenge.
 *
 * @return bool
 */
function blueworx_sso_use_pkce() {
	$mode = blueworx_sso_option( 'pkce', 'auto' );

	if ( 'off' === $mode ) {
		return false;
	}

	if ( 'on' === $mode ) {
		return true;
	}

	return blueworx_sso_discovery_supports( 'code_challenge_methods_supported', 'S256' );
}

/**
 * The destination requested by whatever started the sign-in.
 *
 * @return string URL, or an empty string.
 */
function blueworx_sso_requested_redirect() {
	if ( ! isset( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A sign-in has no nonce to check yet; the value is only ever used through wp_safe_redirect().
		return '';
	}

	return esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
}

/**
 * Starts a sign-in: records the attempt and hands off to the provider.
 *
 * @return void
 */
function blueworx_sso_start() {
	$endpoint = blueworx_sso_endpoint( 'authorization_endpoint' );

	if ( '' === $endpoint ) {
		blueworx_sso_fail( 'no_authorization_endpoint' );
	}

	$state    = blueworx_sso_random_string();
	$nonce    = blueworx_sso_random_string();
	$verifier = blueworx_sso_random_string();

	/*
	 * This record IS the proof that a sign-in is in progress. Deleting it when
	 * the provider comes back is what makes a replayed callback fail, and the
	 * ten-minute life is what stops an abandoned attempt being usable later.
	 */
	set_transient(
		blueworx_sso_attempt_key( $state ),
		array(
			'nonce'       => $nonce,
			'verifier'    => $verifier,
			'redirect_to' => blueworx_sso_requested_redirect(),
			'created'     => time(),
		),
		10 * MINUTE_IN_SECONDS
	);

	$args = array(
		'response_type' => 'code',
		'client_id'     => (string) blueworx_sso_option( 'client_id' ),
		'redirect_uri'  => blueworx_sso_redirect_uri(),
		'scope'         => (string) blueworx_sso_option( 'scope', 'openid email profile' ),
		'state'         => $state,
		'nonce'         => $nonce,
	);

	if ( '' === trim( $args['scope'] ) ) {
		$args['scope'] = 'openid email profile';
	}

	if ( blueworx_sso_use_pkce() ) {
		$args['code_challenge']        = blueworx_sso_base64url_encode( hash( 'sha256', $verifier, true ) );
		$args['code_challenge_method'] = 'S256';
	}

	/**
	 * Filters the arguments sent to the provider's authorization endpoint.
	 *
	 * For providers that need something non-standard, such as a tenant hint or a
	 * forced prompt.
	 *
	 * @param array $args Query arguments.
	 */
	$args = apply_filters( 'blueworx_sso_authorize_args', $args );

	wp_redirect( add_query_arg( array_map( 'rawurlencode', $args ), $endpoint ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- The destination is the configured identity provider, which is by definition off-site.
	exit;
}

/**
 * Ends a failed sign-in.
 *
 * @param string $reason Machine-readable reason, for the log only.
 * @return void
 */
function blueworx_sso_fail( $reason ) {
	blueworx_sso_log( 'failure', $reason );

	wp_safe_redirect( add_query_arg( 'blueworx_sso_error', '1', wp_login_url() ) );
	exit;
}

/**
 * Swaps an authorization code for tokens.
 *
 * @param string $code     Authorization code.
 * @param string $verifier PKCE verifier from the stored attempt.
 * @return array|WP_Error Token response, or an error.
 */
function blueworx_sso_exchange_code( $code, $verifier ) {
	$endpoint = blueworx_sso_endpoint( 'token_endpoint' );

	if ( '' === $endpoint ) {
		return new WP_Error( 'blueworx_sso_no_token_endpoint', __( 'The identity provider has no token address.', 'blueworx-labs-wordpress' ) );
	}

	$client_id     = (string) blueworx_sso_option( 'client_id' );
	$client_secret = (string) blueworx_sso_option( 'client_secret' );

	$body = array(
		'grant_type'   => 'authorization_code',
		'code'         => $code,
		'redirect_uri' => blueworx_sso_redirect_uri(),
	);

	if ( blueworx_sso_use_pkce() ) {
		$body['code_verifier'] = $verifier;
	}

	$headers = array( 'Content-Type' => 'application/x-www-form-urlencoded' );

	/*
	 * The specification prefers the credentials in an Authorization header, and
	 * so do we — but a provider that only lists the form-body method will reject
	 * the header, so follow what it says it accepts.
	 */
	if ( blueworx_sso_discovery_supports( 'token_endpoint_auth_methods_supported', 'client_secret_post' )
		&& ! blueworx_sso_discovery_supports( 'token_endpoint_auth_methods_supported', 'client_secret_basic' ) ) {
		$body['client_id']     = $client_id;
		$body['client_secret'] = $client_secret;
	} else {
		$headers['Authorization'] = 'Basic ' . base64_encode( $client_id . ':' . $client_secret ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic credentials.
	}

	$response = wp_remote_post(
		$endpoint,
		array(
			'timeout' => 15,
			'headers' => $headers,
			'body'    => $body,
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'blueworx_sso_token_request_failed', __( 'The identity provider could not be reached.', 'blueworx-labs-wordpress' ) );
	}

	if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'blueworx_sso_token_rejected', __( 'The identity provider refused the sign-in.', 'blueworx-labs-wordpress' ) );
	}

	$tokens = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $tokens ) || empty( $tokens['id_token'] ) ) {
		return new WP_Error( 'blueworx_sso_no_id_token', __( 'The identity provider returned no sign-in token.', 'blueworx-labs-wordpress' ) );
	}

	return $tokens;
}

/**
 * Fetches extra claims from the provider's user info endpoint.
 *
 * Best effort: anything it cannot get is simply absent, because the verified
 * token is the authority on who someone is.
 *
 * @param array $tokens Token response.
 * @return array Claims, possibly empty.
 */
function blueworx_sso_userinfo( $tokens ) {
	$endpoint = blueworx_sso_endpoint( 'userinfo_endpoint' );

	if ( '' === $endpoint || empty( $tokens['access_token'] ) ) {
		return array();
	}

	$response = wp_remote_get(
		$endpoint,
		array(
			'timeout' => 10,
			'headers' => array( 'Authorization' => 'Bearer ' . $tokens['access_token'] ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return array();
	}

	$claims = json_decode( wp_remote_retrieve_body( $response ), true );

	return is_array( $claims ) ? $claims : array();
}

/**
 * Handles the provider's callback.
 *
 * @return void
 */
function blueworx_sso_handle_callback() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- This IS the verification: the state parameter is a single-use token this site minted.
	if ( isset( $_GET['error'] ) ) {
		blueworx_sso_fail( 'provider_error:' . sanitize_text_field( wp_unslash( $_GET['error'] ) ) );
	}

	$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
	$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( '' === $state || '' === $code ) {
		blueworx_sso_fail( 'missing_state_or_code' );
	}

	$key     = blueworx_sso_attempt_key( $state );
	$attempt = get_transient( $key );

	// Deleted before it is used, not after: a state is good for exactly one
	// callback, so replaying the same URL finds nothing and fails.
	delete_transient( $key );

	if ( ! is_array( $attempt ) ) {
		blueworx_sso_fail( 'unknown_or_replayed_state' );
	}

	$tokens = blueworx_sso_exchange_code( $code, $attempt['verifier'] );

	if ( is_wp_error( $tokens ) ) {
		blueworx_sso_fail( $tokens->get_error_code() );
	}

	$claims = blueworx_sso_verify_id_token(
		$tokens['id_token'],
		(string) blueworx_sso_option( 'issuer' ),
		(string) blueworx_sso_option( 'client_id' ),
		$attempt['nonce']
	);

	if ( is_wp_error( $claims ) ) {
		blueworx_sso_fail( $claims->get_error_code() );
	}

	// The verified token wins every collision: user info is convenience, not proof.
	$claims = array_merge( blueworx_sso_userinfo( $tokens ), $claims );

	$user = blueworx_sso_resolve_user( $claims );

	if ( is_wp_error( $user ) ) {
		blueworx_sso_fail( $user->get_error_code() );
	}

	blueworx_sso_log( 'success', $user->user_login );
	update_option( 'blueworx_sso_last_success', time(), false );

	wp_set_auth_cookie( $user->ID, false );
	wp_set_current_user( $user->ID );
	do_action( 'wp_login', $user->user_login, $user );

	$default = (string) blueworx_sso_option( 'redirect_after_login' );

	if ( '' === $default ) {
		$default = admin_url();
	}

	/**
	 * Filters where someone lands after signing in.
	 *
	 * This is the one place the destination is decided, so a site that has its
	 * own rule — a portal, a profile-completion step — should attach here rather
	 * than adding another redirect of its own.
	 *
	 * @param string  $redirect Destination URL.
	 * @param WP_User $user     The person who just signed in.
	 * @param array   $claims   Verified claims from the provider.
	 */
	$redirect = apply_filters(
		'blueworx_sso_login_redirect',
		'' !== $attempt['redirect_to'] ? $attempt['redirect_to'] : $default,
		$user,
		$claims
	);

	wp_safe_redirect( $redirect );
	exit;
}

/**
 * Routes sign-in and callback requests.
 *
 * @return void
 */
function blueworx_sso_dispatch() {
	if ( ! blueworx_sso_enabled() ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Routing only; each branch does its own verification.
	$action = isset( $_GET['blueworx_sso'] ) ? sanitize_key( wp_unslash( $_GET['blueworx_sso'] ) ) : '';
	$is_callback = ( isset( $_GET['code'] ) && isset( $_GET['state'] ) ) || 'callback' === $action;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	/**
	 * Filters whether this request should start a sign-in.
	 *
	 * A site moving from another sign-in plugin can claim its old address here,
	 * so existing links and bookmarks keep working after the change.
	 *
	 * @param bool $is_login Whether to start a sign-in.
	 */
	if ( apply_filters( 'blueworx_sso_is_login_request', 'login' === $action ) ) {
		blueworx_sso_start();
	}

	/*
	 * A callback is recognised by its parameters rather than its path, so a
	 * provider that already has the site root registered as the return address
	 * keeps working without anyone having to re-register anything.
	 */
	if ( $is_callback ) {
		blueworx_sso_handle_callback();
	}
}
add_action( 'init', 'blueworx_sso_dispatch', 1 );

/**
 * Shows one generic message when a sign-in failed.
 *
 * @param string $message Existing login screen message.
 * @return string Message with the notice appended.
 */
function blueworx_sso_login_message( $message ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presentation only; the flag carries no meaning beyond "show a notice".
	if ( ! isset( $_GET['blueworx_sso_error'] ) ) {
		return $message;
	}

	return $message . '<div id="login_error">' . esc_html__( 'We could not sign you in. Please try again.', 'blueworx-labs-wordpress' ) . '</div>';
}
add_filter( 'login_message', 'blueworx_sso_login_message' );
