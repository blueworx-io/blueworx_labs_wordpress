<?php
/**
 * Starting a sign-in, and what comes back.
 *
 * Two entry points share one callback, so the intent — signing in, or joining —
 * has to survive the round trip without being read back off a URL the browser
 * controls. These checks cover the three pure parts of that: what is sent to the
 * provider, what is accepted from it, and where the person lands afterwards.
 *
 * Run with: php tests/php/sso-flow-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

$GLOBALS['options'] = array(
	'blueworx_sso_issuer'        => 'https://idp.test',
	'blueworx_sso_client_id'     => 'client-abc',
	'blueworx_sso_client_secret' => 'shhh',
	'blueworx_sso_pkce'          => 'off',
);

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	return true;
}

function home_url( $path = '' ) {
	return 'https://example.test/' . ltrim( (string) $path, '/' );
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
}

function add_query_arg( $args, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}

function esc_url_raw( $url ) {
	return (string) $url;
}

function wp_unslash( $value ) {
	return $value;
}

function blueworx_feature_enabled( $key ) {
	return 'sso' === $key;
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/sso/sso.php';
require dirname( __DIR__, 2 ) . '/includes/sso/discovery.php';
require dirname( __DIR__, 2 ) . '/includes/sso/log.php';
require dirname( __DIR__, 2 ) . '/includes/sso/users.php';
require dirname( __DIR__, 2 ) . '/includes/sso/flow.php';

echo "What is sent to the provider\n";

$login_args = blueworx_sso_build_authorize_args( 'login', 'state-1', 'nonce-1', 'verifier-1' );

check( 'a sign-in asks for a code', $login_args['response_type'], 'code' );
check( 'and carries the state', $login_args['state'], 'state-1' );
check( 'and the nonce', $login_args['nonce'], 'nonce-1' );

// The signup prompt is the one provider-specific parameter here, and sending it
// on a plain sign-in would push existing people into creating a second account.
check( 'a sign-in sends no signup prompt', isset( $login_args['prompt'] ), false );

$register_args = blueworx_sso_build_authorize_args( 'register', 'state-2', 'nonce-2', 'verifier-2' );

check( 'joining asks the provider for its signup screen', $register_args['prompt'], 'signup' );

$GLOBALS['options']['blueworx_sso_signup_prompt'] = 'create';
check( 'and the parameter is configurable', blueworx_sso_build_authorize_args( 'register', 's', 'n', 'v' )['prompt'], 'create' );

$GLOBALS['options']['blueworx_sso_signup_prompt'] = '';
check( 'and can be switched off for a provider that rejects it', isset( blueworx_sso_build_authorize_args( 'register', 's', 'n', 'v' )['prompt'] ), false );
unset( $GLOBALS['options']['blueworx_sso_signup_prompt'] );

$GLOBALS['options']['blueworx_sso_pkce'] = 'on';
check( 'the proof key is sent when asked for', blueworx_sso_build_authorize_args( 'login', 's', 'n', 'verifier-1' )['code_challenge_method'], 'S256' );
$GLOBALS['options']['blueworx_sso_pkce'] = 'off';

echo "\nWhat is accepted back from it\n";

$token_claims = array(
	'sub'   => 'subject-1',
	'email' => 'from-token@example.test',
);

$merged = blueworx_sso_merge_claims(
	$token_claims,
	array(
		'sub'            => 'subject-1',
		'email_verified' => true,
		'given_name'     => 'Sam',
	)
);

check( 'the profile fills in what the token did not carry', $merged['given_name'], 'Sam' );
check( 'and the verified flag comes through', $merged['email_verified'], true );

// The token is the only thing this site has verified, so where the two disagree
// the token has to win — otherwise the profile response could quietly change who
// is being signed in.
$merged = blueworx_sso_merge_claims(
	$token_claims,
	array(
		'sub'   => 'subject-1',
		'email' => 'from-profile@example.test',
	)
);
check( 'the verified token wins a collision', $merged['email'], 'from-token@example.test' );

// A profile describing somebody else is not a profile for this sign-in. Taking
// its email would hand this person whichever account that address belongs to.
$mismatch = blueworx_sso_merge_claims(
	$token_claims,
	array(
		'sub'            => 'somebody-else',
		'email_verified' => true,
	)
);
check( 'a profile for a different person is refused', is_wp_error( $mismatch ) ? $mismatch->get_error_code() : 'accepted', 'blueworx_sso_subject_mismatch' );

check( 'a profile that names nobody is still usable', blueworx_sso_merge_claims( $token_claims, array( 'email_verified' => true ) )['email_verified'], true );
check( 'and an empty one is not an error', blueworx_sso_merge_claims( $token_claims, array() )['sub'], 'subject-1' );

echo "\nWhere the person lands\n";

check( 'signing in falls back to the dashboard', blueworx_sso_default_destination( 'login' ), 'https://example.test/wp-admin/' );

$GLOBALS['options']['blueworx_sso_redirect_after_login'] = 'https://example.test/welcome/';
check( 'and uses the configured page when there is one', blueworx_sso_default_destination( 'login' ), 'https://example.test/welcome/' );

// Joining ends somewhere else entirely — the page that asks the newcomer what
// they came for — and must not inherit the sign-in destination.
check( 'joining falls back to the sign-in page when nothing is set', blueworx_sso_default_destination( 'register' ), 'https://example.test/welcome/' );

$GLOBALS['options']['blueworx_sso_redirect_after_register'] = 'https://example.test/register-success/';
check( 'joining uses its own page', blueworx_sso_default_destination( 'register' ), 'https://example.test/register-success/' );
check( 'and signing in is unaffected', blueworx_sso_default_destination( 'login' ), 'https://example.test/welcome/' );

echo "\nThe two entry points\n";

check( 'the sign-in link', blueworx_sso_login_url(), 'https://example.test/?blueworx_sso=login' );
check( 'the join link', blueworx_sso_login_url( '', 'register' ), 'https://example.test/?blueworx_sso=register' );
check( 'anything else is treated as a sign-in', blueworx_sso_login_url( '', 'nonsense' ), 'https://example.test/?blueworx_sso=login' );

echo '
Whose callback this is
';

// "code and state are in the address" belongs to OAuth in general, not to this
// plugin. Claiming every request that carries them breaks whichever other
// integration on the site — payment, booking, another sign-in — uses the same
// two names on its own return leg.
$_GET = array(
	'code'  => 'someone-elses',
	'state' => 'someone-elses-state',
);
check( 'another integration coming back is left alone', blueworx_sso_is_own_callback(), false );

$GLOBALS['transients'][ blueworx_sso_attempt_key( 'our-state' ) ] = array(
	'nonce'       => 'n',
	'verifier'    => 'v',
	'binder'      => hash( 'sha256', 'binder-secret' ),
	'intent'      => 'login',
	'redirect_to' => '',
);

$_GET = array(
	'code'  => 'ours',
	'state' => 'our-state',
);
check( 'a state this site minted is recognised', blueworx_sso_is_own_callback(), true );

// Checking must not consume the record, or the callback that follows would find
// nothing and refuse the sign-in it just accepted.
check( 'and checking does not use the state up', blueworx_sso_is_own_callback(), true );

// A provider reporting a refusal comes back with no code at all.
$_GET = array(
	'state' => 'our-state',
	'error' => 'access_denied',
);
check( 'a refusal from the provider is still ours to handle', blueworx_sso_is_own_callback(), true );

$_GET = array();
check( 'and a plain page view is not a callback', blueworx_sso_is_own_callback(), false );

echo '
Which browser is coming back
';

$attempt = $GLOBALS['transients'][ blueworx_sso_attempt_key( 'our-state' ) ];

// The state says a sign-in was started. Only this says it was started here —
// without it, somebody can start a sign-in as themselves and hand the return
// address to someone else, who then lands inside their account.
$_COOKIE = array( 'blueworx_sso_binder' => 'binder-secret' );
check( 'the browser that left is let back in', blueworx_sso_binder_matches( $attempt ), true );

$_COOKIE = array( 'blueworx_sso_binder' => 'some-other-browser' );
check( 'a different browser is refused', blueworx_sso_binder_matches( $attempt ), false );

$_COOKIE = array();
check( 'and so is one carrying nothing', blueworx_sso_binder_matches( $attempt ), false );

$_COOKIE = array( 'blueworx_sso_binder' => 'binder-secret' );
check( 'an attempt recorded without a binding is refused', blueworx_sso_binder_matches( array( 'nonce' => 'n' ) ), false );
$_COOKIE = array();

echo '
Signing out
';

check( 'the provider is left signed in unless the site asks', blueworx_sso_single_logout_enabled(), false );

$GLOBALS['options']['blueworx_sso_single_logout'] = '1';
check( 'and signed out when it does', blueworx_sso_single_logout_enabled(), true );

check( 'sign-out lands on the home page by default', blueworx_sso_post_logout_redirect(), 'https://example.test/' );

$GLOBALS['options']['blueworx_sso_redirect_after_logout'] = 'https://example.test/goodbye/';
check( 'or wherever the site says', blueworx_sso_post_logout_redirect(), 'https://example.test/goodbye/' );

echo '
Whether the connection has ever worked
';

$GLOBALS['options']['blueworx_sso_log'] = array();
check( 'a connection that has never worked is not proven', blueworx_sso_provider_proven(), false );

// The recent-attempts list holds twenty, so on any site with failing traffic the
// only record of a success used to scroll off within a day — quietly switching
// the password form back on behind whoever had chosen to hide it.
$GLOBALS['options']['blueworx_sso_log'] = array(
	array(
		'outcome' => 'success',
		'detail'  => 'someone',
	),
);
check( 'a success in the recent attempts proves it', blueworx_sso_provider_proven(), true );

$GLOBALS['options']['blueworx_sso_log']          = array_fill(
	0,
	20,
	array(
		'outcome' => 'failure',
		'detail'  => 'nope',
	)
);
$GLOBALS['options']['blueworx_sso_last_success'] = 1756200000;
check( 'and twenty later failures do not un-prove it', blueworx_sso_provider_proven(), true );

finish();
