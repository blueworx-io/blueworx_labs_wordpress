<?php
/**
 * The sign-on event log behind the SSO Logs screen.
 *
 * Two things matter here and they pull against each other. The log has to carry
 * enough to tell the failures apart — a cookie that never came back is a
 * different fault from one that came back holding something else — and it must
 * carry none of the things that would let whoever reads it sign in as somebody
 * else. These checks cover both, plus the cap and the older log it has to keep
 * reading.
 *
 * Run with: php tests/php/sso-log-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

$GLOBALS['options'] = array();

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	return true;
}

function wp_unslash( $value ) {
	return $value;
}

function is_ssl() {
	return ! empty( $GLOBALS['is_ssl'] );
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

// The log writes every failure to the PHP error log as well, which is the point
// of it on a real site and only noise here. Sent to a file so the run reads.
ini_set( 'error_log', tempnam( sys_get_temp_dir(), 'blueworx-sso-log-test' ) ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- Test harness only.

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'COOKIE_DOMAIN', '' );
define( 'COOKIEPATH', '/' );

require dirname( __DIR__, 2 ) . '/includes/sso/log.php';

/**
 * Puts the log and the request back to a known state.
 *
 * @return void
 */
function reset_log() {
	$GLOBALS['options'] = array();
	$GLOBALS['is_ssl']  = true;

	$_COOKIE = array();
	$_SERVER = array(
		'HTTP_HOST'            => 'example.test',
		'HTTP_USER_AGENT'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
		'REMOTE_ADDR'          => '10.0.0.9',
		'HTTP_CF_CONNECTING_IP' => '203.0.113.7',
	);
}

echo "Recording an event\n";

reset_log();
blueworx_sso_event( 'start', 'started', 'sent_to_provider', array( 'ref' => 'abcd1234', 'cookie' => 'set', 'intent' => 'login' ) );

$events = blueworx_sso_events();

check( 'one event is kept', count( $events ), 1 );
check( 'it knows which leg it was', $events[0]['stage'], 'start' );
check( 'and how it went', $events[0]['outcome'], 'started' );
check( 'and why', $events[0]['reason'], 'sent_to_provider' );
check( 'and what the cookie did', $events[0]['cookie'], 'set' );
check( 'and which button started it', $events[0]['intent'], 'login' );
check( 'the address is recorded', $events[0]['host'], 'example.test' );
check( 'so is the protocol', $events[0]['ssl'], '1' );
check( 'the visitor is recorded, not the proxy', $events[0]['ip'], '203.0.113.7' );

echo "\nPairing the two halves of one sign-in\n";

reset_log();
$state = 'a-state-nobody-should-ever-read';
$ref   = blueworx_sso_log_ref( $state );

blueworx_sso_event( 'start', 'started', 'sent_to_provider', array( 'ref' => $ref ) );
blueworx_sso_event( 'return', 'failure', 'state_not_bound_to_this_browser:missing', array( 'ref' => $ref ) );

$events = blueworx_sso_events();

check( 'both halves are recorded', count( $events ), 2 );
check( 'newest first', $events[0]['stage'], 'return' );
check( 'and they share a reference', $events[0]['ref'], $events[1]['ref'] );
check( 'the reference is short enough to read out', strlen( $ref ), 8 );
check( 'and is not the state itself', false, false !== strpos( $ref, $state ) );
check( 'nor does any event carry the state', false, false !== strpos( wp_json_encode( $events ), $state ) );

echo "\nWhat the browser sent, by name only\n";

reset_log();
$_COOKIE = array(
	'wordpress_logged_in_abc'       => 'a-real-session-value',
	'wordpress_blueworx_sso_binder' => 'a-real-binder-value',
);

blueworx_sso_event( 'return', 'failure', 'state_not_bound_to_this_browser:mismatch' );

$events = blueworx_sso_events();

check( 'the cookie names are listed', $events[0]['jar'], 'wordpress_blueworx_sso_binder, wordpress_logged_in_abc' );
check( 'the binder value is never stored', false, false !== strpos( wp_json_encode( $events ), 'a-real-binder-value' ) );
check( 'nor is the session value', false, false !== strpos( wp_json_encode( $events ), 'a-real-session-value' ) );

echo "\nHow much is kept\n";

reset_log();

for ( $i = 0; $i < BLUEWORX_SSO_LOG_LIMIT + 25; $i++ ) {
	blueworx_sso_event( 'return', 'failure', 'attempt_' . $i );
}

$events = blueworx_sso_events();

check( 'the log is capped', count( $events ), BLUEWORX_SSO_LOG_LIMIT );
check( 'and it is the newest that survive', $events[0]['reason'], 'attempt_' . ( BLUEWORX_SSO_LOG_LIMIT + 24 ) );

echo "\nThe log the first version wrote\n";

reset_log();
$GLOBALS['options']['blueworx_sso_log'] = array(
	array(
		'time'    => 1750000000,
		'outcome' => 'success',
		'detail'  => 'someone',
	),
);

$events = blueworx_sso_events();

check( 'old entries are still shown', count( $events ), 1 );
check( 'in the new shape', $events[0]['outcome'], 'success' );
check( 'with what they did record', $events[0]['reason'], 'someone' );
check( 'and a connection that worked is still proven', blueworx_sso_provider_proven(), true );

blueworx_sso_event( 'return', 'failure', 'something_new' );
$events = blueworx_sso_events();

// The first new event carries the old entries into the new log rather than
// replacing them, so upgrading mid-investigation does not lose the failures
// somebody is part-way through reading.
check( 'writing something new keeps what was already there', count( $events ), 2 );
check( 'with the new entry on top', $events[0]['reason'], 'something_new' );
check( 'and the old one below it', $events[1]['reason'], 'someone' );

echo "\nClearing it\n";

reset_log();
blueworx_sso_event( 'return', 'success', 'someone' );
$GLOBALS['options']['blueworx_sso_last_success'] = 1750000000;

blueworx_sso_clear_events();

check( 'the events are gone', count( blueworx_sso_events() ), 0 );
check( 'and so is the older log', count( get_option( 'blueworx_sso_log', array() ) ), 0 );

// Clearing a screenful of failures must not quietly re-lock the setting that
// hides the password form, which would take the site's sign-in screen with it.
check( 'but a connection that has worked is still proven', blueworx_sso_provider_proven(), true );

echo "\nThe shape the rest of the plugin reads\n";

reset_log();
blueworx_sso_event( 'start', 'started', 'sent_to_provider' );
blueworx_sso_event( 'return', 'success', 'someone' );

$entries = blueworx_sso_get_log();

check( 'the outbound leg is left out of it', count( $entries ), 1 );
check( 'and the attempt keeps its old shape', $entries[0]['detail'], 'someone' );
check( 'with its outcome', $entries[0]['outcome'], 'success' );

finish();
