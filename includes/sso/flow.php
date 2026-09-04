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
 * The cookie tying a sign-in attempt to the browser that started it.
 */
const BLUEWORX_SSO_BINDER_COOKIE = 'blueworx_sso_binder';

/**
 * Remembers, in the browser, that this browser started a sign-in.
 *
 * The state alone proves that a sign-in was started, not that THIS person
 * started it. Without something only the starting browser holds, somebody can
 * begin a sign-in as themselves and hand the return address to someone else,
 * who is then quietly signed in as them — and does whatever they came to do
 * inside an account that is not theirs.
 *
 * Lax rather than Strict: the return leg is a top-level navigation from the
 * provider, which Lax allows and Strict would drop.
 *
 * @param string $binder Random value; only its hash is stored server-side.
 * @return bool True when the cookie was actually sent to the browser.
 */
function blueworx_sso_set_binder_cookie( $binder ) {
	/*
	 * Reported rather than silently skipped. A sign-in that leaves without this
	 * cookie is guaranteed to fail on the way back, and it fails with a message
	 * about the browser — which sends whoever is debugging it looking at the
	 * browser, when the answer is that the site never sent the cookie at all.
	 */
	if ( headers_sent() ) {
		return false;
	}

	setcookie(
		BLUEWORX_SSO_BINDER_COOKIE,
		$binder,
		array(
			'expires'  => time() + ( 10 * MINUTE_IN_SECONDS ),
			'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);

	return true;
}

/**
 * Drops the binding cookie, whichever way the sign-in ended.
 *
 * @return void
 */
function blueworx_sso_clear_binder_cookie() {
	if ( headers_sent() ) {
		return;
	}

	setcookie(
		BLUEWORX_SSO_BINDER_COOKIE,
		'',
		array(
			'expires'  => time() - DAY_IN_SECONDS,
			'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
}

/**
 * Which of the ways the browser check can go, this one went.
 *
 * One boolean cannot tell these apart, and they have nothing to do with each
 * other. A cookie that never arrived is a question about how it was written or
 * what sits in front of the site; one that arrived holding something else is a
 * second sign-in started in the same browser, overwriting the first. Answering
 * both with "not bound to this browser" is what makes this failure so hard to
 * place, so the reason is recorded and only the message stays generic.
 *
 * @param array $attempt The stored attempt.
 * @return string 'ok', 'missing', 'mismatch', or 'unbound' when the attempt
 *                itself recorded no binding.
 */
function blueworx_sso_binder_state( $attempt ) {
	$expected = isset( $attempt['binder'] ) ? (string) $attempt['binder'] : '';
	$given    = isset( $_COOKIE[ BLUEWORX_SSO_BINDER_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ BLUEWORX_SSO_BINDER_COOKIE ] ) ) : '';

	if ( '' === $expected ) {
		return 'unbound';
	}

	if ( '' === $given ) {
		return 'missing';
	}

	return hash_equals( $expected, hash( 'sha256', $given ) ) ? 'ok' : 'mismatch';
}

/**
 * Whether the browser coming back is the one that left.
 *
 * @param array $attempt The stored attempt.
 * @return bool True when the cookie matches the hash recorded at the start.
 */
function blueworx_sso_binder_matches( $attempt ) {
	return 'ok' === blueworx_sso_binder_state( $attempt );
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
	$binder   = blueworx_sso_random_string();

	/*
	 * This record IS the proof that a sign-in is in progress. Deleting it when
	 * the provider comes back is what makes a replayed callback fail, and the
	 * ten-minute life is what stops an abandoned attempt being usable later.
	 *
	 * Only the hash of the binder is kept, for the same reason only the hash of
	 * the state is: a record that leaks must not hand anybody a working sign-in.
	 */
	set_transient(
		blueworx_sso_attempt_key( $state ),
		array(
			'nonce'       => $nonce,
			'verifier'    => $verifier,
			'binder'      => hash( 'sha256', $binder ),
			'intent'      => blueworx_sso_intent( $intent ),
			'redirect_to' => blueworx_sso_requested_redirect(),
			'created'     => time(),
		),
		10 * MINUTE_IN_SECONDS
	);

	$cookie_sent = blueworx_sso_set_binder_cookie( $binder );

	/*
	 * The outbound half of the record. Written before the redirect, because
	 * after it there is no more PHP — and a sign-in that never comes back is
	 * otherwise invisible, which is precisely the case somebody needs to see.
	 */
	blueworx_sso_event(
		'start',
		'started',
		$cookie_sent ? 'sent_to_provider' : 'cookie_not_set_headers_already_sent',
		array(
			'intent' => blueworx_sso_intent( $intent ),
			'ref'    => blueworx_sso_log_ref( $state ),
			'cookie' => $cookie_sent ? 'set' : 'not_set',
		)
	);

	$args = blueworx_sso_build_authorize_args( $intent, $state, $nonce, $verifier );

	wp_redirect( add_query_arg( array_map( 'rawurlencode', $args ), $endpoint ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- The destination is the configured identity provider, which is by definition off-site.
	exit;
}

/**
 * Ends a failed sign-in.
 *
 * @param string $reason Machine-readable reason, for the log only.
 * @param array  $extra  What the caller knows about this attempt, for the log.
 * @return void
 */
function blueworx_sso_fail( $reason, $extra = array() ) {
	blueworx_sso_log( 'failure', $reason, $extra );

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
 * @param WP_Error $error   Why resolution failed.
 * @param string   $intent  Which button started this.
 * @param array    $context What is known about this attempt, for the log.
 * @return void Returns when this is not that case; otherwise redirects and exits.
 */
function blueworx_sso_no_account_redirect( $error, $intent, $context = array() ) {
	if ( 'blueworx_sso_no_account' !== $error->get_error_code() || 'login' !== blueworx_sso_intent( $intent ) ) {
		return;
	}

	$destination = trim( (string) blueworx_sso_option( 'no_account_url' ) );

	if ( '' === $destination ) {
		return;
	}

	blueworx_sso_log( 'failure', 'no_account_sent_to_register', $context );

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
	$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
	$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

	// Carried into every outcome below, so one line in the log can be read
	// against the line the outbound leg wrote for the same sign-in. Read before
	// the provider's own refusal is handled, so that refusal is traceable to the
	// sign-in it belongs to rather than floating on its own.
	$context = array( 'ref' => blueworx_sso_log_ref( $state ) );

	if ( isset( $_GET['error'] ) ) {
		blueworx_sso_fail( 'provider_error:' . sanitize_text_field( wp_unslash( $_GET['error'] ) ), $context );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( '' === $state || '' === $code ) {
		blueworx_sso_fail( 'missing_state_or_code', $context );
	}

	$key     = blueworx_sso_attempt_key( $state );
	$attempt = get_transient( $key );

	// Deleted before it is used, not after: a state is good for exactly one
	// callback, so replaying the same URL finds nothing and fails.
	delete_transient( $key );

	blueworx_sso_clear_binder_cookie();

	if ( ! is_array( $attempt ) ) {
		blueworx_sso_fail( 'unknown_or_replayed_state', $context );
	}

	$context['intent'] = blueworx_sso_intent( isset( $attempt['intent'] ) ? $attempt['intent'] : 'login' );

	// The state says a sign-in was started. This says it was started HERE, in
	// this browser — without it, somebody can start a sign-in as themselves and
	// hand the return address to someone else, who lands inside their account.
	$binder            = blueworx_sso_binder_state( $attempt );
	$context['cookie'] = 'ok' === $binder ? 'returned' : $binder;

	if ( 'ok' !== $binder ) {
		blueworx_sso_fail( 'state_not_bound_to_this_browser:' . $binder, $context );
	}

	// Whichever button was pressed on the way out, taken from this site's own
	// record of the attempt rather than from anything the browser came back with.
	$intent = $context['intent'];

	$tokens = blueworx_sso_exchange_code( $code, $attempt['verifier'] );

	if ( is_wp_error( $tokens ) ) {
		blueworx_sso_fail( $tokens->get_error_code(), $context );
	}

	$claims = blueworx_sso_verify_id_token(
		$tokens['id_token'],
		(string) blueworx_sso_option( 'issuer' ),
		(string) blueworx_sso_option( 'client_id' ),
		$attempt['nonce']
	);

	if ( is_wp_error( $claims ) ) {
		blueworx_sso_fail( $claims->get_error_code(), $context );
	}

	$claims = blueworx_sso_merge_claims( $claims, blueworx_sso_userinfo( $tokens ) );

	if ( is_wp_error( $claims ) ) {
		blueworx_sso_fail( $claims->get_error_code(), $context );
	}

	$user = blueworx_sso_resolve_user( $claims, $intent );

	if ( is_wp_error( $user ) ) {
		blueworx_sso_no_account_redirect( $user, $intent, $context );
		blueworx_sso_fail( $user->get_error_code(), $context );
	}

	blueworx_sso_log( 'success', $user->user_login, array_merge( $context, array( 'user' => $user->user_login ) ) );
	update_option( 'blueworx_sso_last_success', time(), false );

	// Kept only when the site actually signs people out at the provider, and
	// only until it does: it is the one thing an end-session request needs, and
	// there is no reason to hold it otherwise.
	if ( blueworx_sso_single_logout_enabled() ) {
		update_user_meta( $user->ID, 'blueworx_sso_id_token', $tokens['id_token'] );
	}

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
 * Whether this request is a provider returning from a sign-in this site started.
 *
 * "code and state are in the address" is not enough on its own: those two names
 * belong to OAuth in general, not to this plugin, and any other integration on
 * the site — another sign-in, a payment, a booking system — comes back the same
 * way. Claiming all of them breaks whichever one the site actually uses. The
 * state has to be one this site minted and still has a record of.
 *
 * @return bool
 */
function blueworx_sso_is_own_callback() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only; the callback re-reads and verifies the state itself.
	$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

	if ( '' === $state ) {
		return false;
	}

	// Read, not consumed: the callback deletes the record itself, so a state
	// checked here is still good for exactly one sign-in.
	return is_array( get_transient( blueworx_sso_attempt_key( $state ) ) );
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
	$action      = isset( $_GET['blueworx_sso'] ) ? sanitize_key( wp_unslash( $_GET['blueworx_sso'] ) ) : '';
	$is_callback = 'callback' === $action || blueworx_sso_is_own_callback();
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
	 * A callback is recognised either by its own action, or by a state this site
	 * minted — so a provider that already has the site root registered as the
	 * return address keeps working without anyone re-registering anything, and
	 * another integration's return traffic is left alone.
	 */
	if ( $is_callback ) {
		blueworx_sso_handle_callback();
	}
}
add_action( 'init', 'blueworx_sso_dispatch', 1 );

/**
 * Whether signing out of WordPress should sign the person out of the provider.
 *
 * @return bool
 */
function blueworx_sso_single_logout_enabled() {
	return '1' === blueworx_sso_option( 'single_logout', '0' );
}

/**
 * Where the provider is asked to send someone after signing them out.
 *
 * @return string Absolute URL.
 */
function blueworx_sso_post_logout_redirect() {
	$configured = trim( (string) blueworx_sso_option( 'redirect_after_logout' ) );

	return '' !== $configured ? $configured : home_url( '/' );
}

/**
 * Signs someone out of the identity provider as well as out of WordPress.
 *
 * Without this, signing out is only half a sign-out: the WordPress cookie goes,
 * the provider's session does not, and the next click on the sign-in button
 * walks straight back in without anyone being asked for anything. On a shared
 * computer that is not a sign-out at all.
 *
 * Only people who arrived through the provider are sent there — a password
 * sign-in has no token to hand over, so it logs out the way it always did.
 *
 * @param int $user_id The person signing out.
 * @return void
 */
function blueworx_sso_logout( $user_id = 0 ) {
	if ( ! $user_id || ! blueworx_sso_enabled() || ! blueworx_sso_single_logout_enabled() ) {
		return;
	}

	$hint = (string) get_user_meta( $user_id, 'blueworx_sso_id_token', true );

	if ( '' === $hint ) {
		return;
	}

	delete_user_meta( $user_id, 'blueworx_sso_id_token' );

	$endpoint = blueworx_sso_endpoint( 'end_session_endpoint' );

	// A provider that publishes no end-session address cannot be signed out of.
	// Nothing is broken by that, so the local sign-out simply finishes as usual.
	if ( '' === $endpoint ) {
		return;
	}

	$args = array(
		'id_token_hint'            => $hint,
		'client_id'                => (string) blueworx_sso_option( 'client_id' ),
		'post_logout_redirect_uri' => blueworx_sso_post_logout_redirect(),
	);

	/**
	 * Filters the arguments sent to the provider's end-session endpoint.
	 *
	 * @param array $args    Query arguments.
	 * @param int   $user_id The person signing out.
	 */
	$args = apply_filters( 'blueworx_sso_end_session_args', $args, $user_id );

	$signing_out = get_user_by( 'id', $user_id );

	blueworx_sso_event(
		'logout',
		'success',
		'sent_to_provider',
		array( 'user' => $signing_out ? $signing_out->user_login : (string) $user_id )
	);

	wp_redirect( add_query_arg( array_map( 'rawurlencode', $args ), $endpoint ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- The destination is the configured identity provider, which is by definition off-site.
	exit;
}
add_action( 'wp_logout', 'blueworx_sso_logout' );

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
