<?php
/**
 * Single sign-on: the event log behind the SSO Logs screen.
 *
 * When a sign-in fails the person only ever sees a generic message, which is
 * right for them and useless for whoever has to fix it. This records what
 * actually happened, on both legs of the flow, in enough detail to tell the
 * common failures apart without guessing.
 *
 * Two legs, because half the failures are only explicable by comparing them.
 * A sign-in that leaves and never comes back is a different fault from one that
 * comes back without the cookie it left with, and a log that only records the
 * return cannot tell you which you have. Each pair shares a reference so the
 * two lines can be read together.
 *
 * What is deliberately NOT recorded: the state, the nonce, the verifier, the
 * codes, the tokens and the cookie's value. A debugging log that leaks any of
 * those hands somebody a working sign-in, which is a worse problem than the one
 * it was added to solve. Cookie NAMES are recorded, values never.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many events to keep.
 *
 * Enough to cover a debugging session with a provider — every sign-in writes
 * two lines — without letting one option row grow without limit.
 */
const BLUEWORX_SSO_LOG_LIMIT = 100;

/**
 * The option the events live in.
 */
const BLUEWORX_SSO_LOG_OPTION = 'blueworx_sso_events';

/**
 * The option the first version of this log used.
 *
 * Still read, so upgrading a site that has already been signing people in does
 * not throw its history away — and, more to the point, does not un-prove a
 * connection that has worked. See blueworx_sso_provider_proven().
 */
const BLUEWORX_SSO_LEGACY_LOG_OPTION = 'blueworx_sso_log';

/**
 * A short reference tying the two halves of one sign-in together.
 *
 * Derived from the state rather than being the state: it is written to an
 * option an administrator can read, and the state is a live credential until
 * the moment it is used.
 *
 * @param string $state State parameter.
 * @return string Eight hex characters.
 */
function blueworx_sso_log_ref( $state ) {
	return substr( hash( 'sha256', (string) $state ), 0, 8 );
}

/**
 * What can be seen about the request making this event.
 *
 * Every field here exists because some real failure is invisible without it.
 * The host and the protocol catch a site reached at two addresses or sitting
 * behind something that terminates TLS for it; the cookie domain and path catch
 * a cookie written where the browser will not send it back; the cookie names
 * catch one that was never stored at all.
 *
 * @return array Context fields.
 */
function blueworx_sso_request_context() {
	$server = isset( $_SERVER ) && is_array( $_SERVER ) ? $_SERVER : array();

	$host  = isset( $server['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $server['HTTP_HOST'] ) ) : '';
	$agent = isset( $server['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $server['HTTP_USER_AGENT'] ) ) : '';

	/*
	 * The address in front of the site, when something in front of it says so.
	 * Behind a proxy REMOTE_ADDR is the proxy, which is the same value on every
	 * line and tells you nothing. This is read for display only — nothing is
	 * ever allowed or refused on the strength of it.
	 */
	$ip = '';

	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $candidate ) {
		if ( ! empty( $server[ $candidate ] ) ) {
			$ip = sanitize_text_field( wp_unslash( $server[ $candidate ] ) );
			$ip = trim( explode( ',', $ip )[0] );
			break;
		}
	}

	/*
	 * What WordPress believes about the connection, and what the thing in front
	 * of it said. These disagreeing is the whole explanation for a cookie that
	 * was written without its secure flag, or a redirect that lands on the wrong
	 * scheme, so both are recorded rather than either alone.
	 */
	$forwarded = isset( $server['HTTP_X_FORWARDED_PROTO'] ) ? sanitize_text_field( wp_unslash( $server['HTTP_X_FORWARDED_PROTO'] ) ) : '';

	return array(
		'host'   => $host,
		'ssl'    => function_exists( 'is_ssl' ) && is_ssl() ? '1' : '0',
		'proto'  => strtolower( $forwarded ),
		'domain' => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? (string) COOKIE_DOMAIN : '',
		'path'   => defined( 'COOKIEPATH' ) && COOKIEPATH ? (string) COOKIEPATH : '/',
		'jar'    => blueworx_sso_cookie_names(),
		'ip'     => $ip,
		'agent'  => 180 < strlen( $agent ) ? substr( $agent, 0, 180 ) : $agent,
	);
}

/**
 * The names of the cookies the browser sent, without any of their values.
 *
 * "The cookie did not come back" and "the browser sent nothing at all" are
 * different faults with the same symptom, and this is what separates them: a
 * request carrying the WordPress cookies but not ours points at how ours was
 * written, while one carrying nothing points at the browser or whatever sits in
 * front of the site.
 *
 * @return string Comma-separated names.
 */
function blueworx_sso_cookie_names() {
	if ( ! isset( $_COOKIE ) || ! is_array( $_COOKIE ) ) {
		return '';
	}

	$names = array();

	foreach ( array_keys( $_COOKIE ) as $name ) {
		$names[] = sanitize_text_field( (string) $name );
	}

	sort( $names );

	return implode( ', ', $names );
}

/**
 * Records one event.
 *
 * @param string $stage   Which leg of the flow: 'start', 'return' or 'logout'.
 * @param string $outcome 'success', 'failure' or 'started'.
 * @param string $reason  Machine-readable reason, or the username on success.
 * @param array  $extra   Anything the caller knows that the request does not.
 * @return void
 */
function blueworx_sso_event( $stage, $outcome, $reason = '', $extra = array() ) {
	$outcome = in_array( $outcome, array( 'success', 'failure', 'started' ), true ) ? $outcome : 'failure';
	$stage   = in_array( $stage, array( 'start', 'return', 'logout' ), true ) ? $stage : 'return';

	$event = array_merge(
		array(
			'time'    => time(),
			'stage'   => $stage,
			'outcome' => $outcome,
			'reason'  => sanitize_text_field( (string) $reason ),
			'intent'  => '',
			'user'    => '',
			'ref'     => '',
			'cookie'  => '',
		),
		blueworx_sso_request_context(),
		array_map( 'strval', (array) $extra )
	);

	$events = blueworx_sso_events();
	array_unshift( $events, $event );

	update_option( BLUEWORX_SSO_LOG_OPTION, array_slice( $events, 0, BLUEWORX_SSO_LOG_LIMIT ), false );

	if ( 'failure' === $outcome ) {
		error_log( 'BlueWorx SSO: ' . $stage . ' failed (' . $reason . ')' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The reason is deliberately kept out of the browser, so this is the only place it can be read.
	}
}

/**
 * Reads the recorded events, newest first.
 *
 * @return array List of events.
 */
function blueworx_sso_events() {
	$events = get_option( BLUEWORX_SSO_LOG_OPTION, array() );
	$events = is_array( $events ) ? $events : array();

	if ( ! empty( $events ) ) {
		return $events;
	}

	// Nothing in the new shape yet, so show whatever the old one holds rather
	// than an empty screen on a site that has been signing people in for months.
	return blueworx_sso_legacy_events();
}

/**
 * The first version's entries, read in the shape everything else expects.
 *
 * Read-only and never written back: the old option is left exactly as it is, so
 * downgrading a site loses nothing.
 *
 * @return array List of events.
 */
function blueworx_sso_legacy_events() {
	$legacy = get_option( BLUEWORX_SSO_LEGACY_LOG_OPTION, array() );

	if ( ! is_array( $legacy ) ) {
		return array();
	}

	$events = array();

	foreach ( $legacy as $entry ) {
		if ( ! is_array( $entry ) ) {
			continue;
		}

		$events[] = array(
			'time'    => isset( $entry['time'] ) ? (int) $entry['time'] : 0,
			'stage'   => 'return',
			'outcome' => isset( $entry['outcome'] ) && 'success' === $entry['outcome'] ? 'success' : 'failure',
			'reason'  => isset( $entry['detail'] ) ? (string) $entry['detail'] : '',
			'intent'  => '',
			'user'    => '',
			'ref'     => '',
			'cookie'  => '',
			'host'    => '',
			'ssl'     => '',
			'proto'   => '',
			'domain'  => '',
			'path'    => '',
			'jar'     => '',
			'ip'      => '',
			'agent'   => '',
		);
	}

	return $events;
}

/**
 * Empties the log.
 *
 * The permanent mark left by a successful sign-in is deliberately untouched:
 * clearing a screenful of failures must not quietly re-lock the setting that
 * hides the password form. See blueworx_sso_provider_proven().
 *
 * @return void
 */
function blueworx_sso_clear_events() {
	update_option( BLUEWORX_SSO_LOG_OPTION, array(), false );
	update_option( BLUEWORX_SSO_LEGACY_LOG_OPTION, array(), false );
}

/**
 * Records one sign-in attempt.
 *
 * Kept for everything that only knows the outcome, and for the older callers
 * that predate the two-leg log.
 *
 * @param string $outcome Either 'success' or 'failure'.
 * @param string $detail  Reason code, or the username on success.
 * @param array  $extra   Anything else known about it.
 * @return void
 */
function blueworx_sso_log( $outcome, $detail = '', $extra = array() ) {
	blueworx_sso_event( 'return', 'success' === $outcome ? 'success' : 'failure', $detail, $extra );
}

/**
 * Reads the recorded attempts in the original shape.
 *
 * @return array List of entries.
 */
function blueworx_sso_get_log() {
	$entries = array();

	foreach ( blueworx_sso_events() as $event ) {
		if ( isset( $event['stage'] ) && 'start' === $event['stage'] ) {
			continue;
		}

		$entries[] = array(
			'time'    => isset( $event['time'] ) ? (int) $event['time'] : 0,
			'outcome' => isset( $event['outcome'] ) && 'success' === $event['outcome'] ? 'success' : 'failure',
			'detail'  => isset( $event['reason'] ) ? (string) $event['reason'] : '',
		);
	}

	return $entries;
}

/**
 * Whether an administrator has actually signed in through the provider.
 *
 * The gate on hiding the WordPress password form. Without it, that switch is a
 * one-click way to lock everybody out of a site whose connection has never been
 * proven to work — which is exactly the case where somebody is most likely to
 * reach for it.
 *
 * @return bool True when a successful sign-in is on record.
 */
function blueworx_sso_provider_proven() {
	/*
	 * The permanent mark, written the moment a sign-in succeeds. The events
	 * below are capped and can be cleared, so on a busy or freshly tidied site
	 * the proof would otherwise scroll off — and this setting would quietly turn
	 * itself off, long after whoever chose it stopped watching.
	 */
	if ( (int) get_option( 'blueworx_sso_last_success', 0 ) > 0 ) {
		return true;
	}

	// Still worth reading for a site that succeeded before the mark existed.
	foreach ( blueworx_sso_events() as $event ) {
		if ( isset( $event['outcome'] ) && 'success' === $event['outcome'] ) {
			return true;
		}
	}

	return false;
}
