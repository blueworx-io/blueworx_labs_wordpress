<?php
/**
 * Single sign-on: the authorization code flow.
 *
 * Outbound, this sends someone to their identity provider with a one-time state,
 * a nonce and a PKCE challenge. Inbound, it takes the code back, swaps it for
 * tokens, verifies them and signs the person in.
 *
 * There are two ways in — signing in and joining — and one way back, because
 * providers match the return address exactly and most will only register one.
 * Which button was pressed is therefore kept in the server-side record the state
 * points at, never in the URL the browser carries. A visitor who edits their way
 * from "login" to "register" on the return leg changes nothing.
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
 * Reduces anything claiming to be an intent to one of the two real ones.
 *
 * @param mixed $value Candidate intent.
 * @return string 'register', or 'login' for everything else.
 */
function blueworx_sso_intent( $value ) {
	return 'register' === $value ? 'register' : 'login';
}

/**
 * The local URL that starts a sign-in.
 *
 * @param string $redirect_to Optional. Where to send the person afterwards.
 * @param string $intent      Optional. 'login' or 'register'.
 * @return string Absolute URL.
 */
function blueworx_sso_login_url( $redirect_to = '', $intent = 'login' ) {
	$url = home_url( '/?blueworx_sso=' . blueworx_sso_intent( $intent ) );

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
 * Builds the query the provider is sent.
 *
 * @param string $intent   'login' or 'register'.
 * @param string $state    One-time state.
 * @param string $nonce    One-time nonce.
 * @param string $verifier PKCE verifier.
 * @return array Query arguments.
 */
function blueworx_sso_build_authorize_args( $intent, $state, $nonce, $verifier ) {
	$intent = blueworx_sso_intent( $intent );

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

	/*
	 * Asking the provider to open on its signup screen rather than its sign-in
	 * one. There is no standard parameter for this, so it is a setting: most
	 * providers use "signup", some use something else, and a provider that
	 * rejects unknown parameters can have it emptied.
	 */
	$prompt = trim( (string) blueworx_sso_option( 'signup_prompt', 'signup' ) );

	if ( 'register' === $intent && '' !== $prompt ) {
		$args['prompt'] = $prompt;
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
	 * @param array  $args   Query arguments.
	 * @param string $intent 'login' or 'register'.
	 */
	return apply_filters( 'blueworx_sso_authorize_args', $args, $intent );
}

/**
 * Starts a sign-in: records the attempt and hands off to the provider.
 *
 * @param string $intent Which button was pressed: 'login' or 'register'.
 * @return void
 */
function blueworx_sso_start( $intent = 'login' ) {
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
			'intent'      => blueworx_sso_intent( $intent ),
			'redirect_to' => blueworx_sso_requested_redirect(),
			'created'     => time(),
		),
		10 * MINUTE_IN_SECONDS
	);

	$args = blueworx_sso_build_authorize_args( $intent, $state, $nonce, $verifier );

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
 * Sends someone who has no account here to the page that can give them one.
 *
 * This is the only failure with an obvious next step, and the generic "we could
 * not sign you in" is actively unhelpful for it: nothing went wrong, they simply
 * have not joined yet. Sites without a joining page get the usual message.
 *
 * @param WP_Error $error  Why resolution failed.
 * @param string   $intent Which button started this.
 * @return void Returns when this is not that case; otherwise redirects and exits.
 */
function blueworx_sso_no_account_redirect( $error, $intent ) {
	if ( 'blueworx_sso_no_account' !== $error->get_error_code() || 'login' !== blueworx_sso_intent( $intent ) ) {
		return;
	}

	$destination = trim( (string) blueworx_sso_option( 'no_account_url' ) );

	if ( '' === $destination ) {
		return;
	}

	blueworx_sso_log( 'failure', 'no_account_sent_to_register' );

	wp_safe_redirect( add_query_arg( 'blueworx_sso_no_account', '1', $destination ) );
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
 * Combines the verified token with the profile fetched alongside it.
 *
 * The token is the only part this site has checked a signature on, so it wins
 * every collision. The profile is where the verified-email flag usually lives,
 * which is why it is worth having at all — but a profile naming a different
 * person is not this person's profile, and taking an email address off it would
 * hand them whichever account that address belongs to.
 *
 * @param array $token_claims Verified claims from the ID token.
 * @param array $profile      Claims from the user info endpoint, possibly empty.
 * @return array|WP_Error Combined claims, or an error when the two disagree.
 */
function blueworx_sso_merge_claims( $token_claims, $profile ) {
	if ( ! is_array( $profile ) || empty( $profile ) ) {
		return $token_claims;
	}

	$profile_subject = isset( $profile['sub'] ) ? (string) $profile['sub'] : '';
	$token_subject   = isset( $token_claims['sub'] ) ? (string) $token_claims['sub'] : '';

	if ( '' !== $profile_subject && $profile_subject !== $token_subject ) {
		return new WP_Error( 'blueworx_sso_subject_mismatch', __( 'The identity provider described two different people.', 'blueworx-labs-wordpress' ) );
	}

	return array_merge( $profile, $token_claims );
}

/**
 * Where a sign-in lands when nothing more specific was asked for.
 *
 * Joining ends somewhere else than signing in — usually the page that asks a
 * newcomer what they came for — so it gets its own setting, falling back to the
 * sign-in destination rather than to nowhere.
 *
 * @param string $intent 'login' or 'register'.
 * @return string Absolute URL.
 */
function blueworx_sso_default_destination( $intent ) {
	$login = trim( (string) blueworx_sso_option( 'redirect_after_login' ) );

	if ( '' === $login ) {
		$login = admin_url();
	}

	if ( 'register' !== blueworx_sso_intent( $intent ) ) {
		return $login;
	}

	$register = trim( (string) blueworx_sso_option( 'redirect_after_register' ) );

	return '' !== $register ? $register : $login;
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

	// Whichever button was pressed on the way out, taken from this site's own
	// record of the attempt rather than from anything the browser came back with.
	$intent = blueworx_sso_intent( isset( $attempt['intent'] ) ? $attempt['intent'] : 'login' );

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

	$claims = blueworx_sso_merge_claims( $claims, blueworx_sso_userinfo( $tokens ) );

	if ( is_wp_error( $claims ) ) {
		blueworx_sso_fail( $claims->get_error_code() );
	}

	$user = blueworx_sso_resolve_user( $claims, $intent );

	if ( is_wp_error( $user ) ) {
		blueworx_sso_no_account_redirect( $user, $intent );
		blueworx_sso_fail( $user->get_error_code() );
	}

	blueworx_sso_log( 'success', $user->user_login );
	update_option( 'blueworx_sso_last_success', time(), false );

	wp_set_auth_cookie( $user->ID, false );
	wp_set_current_user( $user->ID );
	do_action( 'wp_login', $user->user_login, $user );

	$default = blueworx_sso_default_destination( $intent );

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
	 * @param string  $intent   Which button started this: 'login' or 'register'.
	 */
	$redirect = apply_filters(
		'blueworx_sso_login_redirect',
		'' !== $attempt['redirect_to'] ? $attempt['redirect_to'] : $default,
		$user,
		$claims,
		$intent
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
	 * @param bool   $is_login Whether to start a sign-in.
	 * @param string $action   The requested action, if any.
	 */
	if ( apply_filters( 'blueworx_sso_is_login_request', 'login' === $action, $action ) ) {
		blueworx_sso_start( 'login' );
	}

	/**
	 * Filters whether this request should start a joining.
	 *
	 * Separate from the sign-in filter above, so a site claiming an old address
	 * can say which of the two that address meant.
	 *
	 * @param bool   $is_register Whether to start a joining.
	 * @param string $action      The requested action, if any.
	 */
	if ( apply_filters( 'blueworx_sso_is_register_request', 'register' === $action, $action ) ) {
		blueworx_sso_start( 'register' );
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
