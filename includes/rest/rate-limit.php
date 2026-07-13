<?php
/**
 * Headless REST layer — rate limiting & lockout.
 *
 * Transient-backed counters keyed by action + client IP. Fail-open on cache
 * eviction is acceptable at these thresholds. All limits are configurable.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the transient key for an action/identifier pair.
 *
 * @param string $action     Action bucket (e.g. 'login').
 * @param string $identifier Extra identifier (defaults to client IP).
 * @return string Transient key.
 */
function blueworx_headless_rl_key( $action, $identifier = '' ) {
	if ( '' === $identifier ) {
		$identifier = blueworx_headless_client_ip();
	}

	return 'blueworx_rl_' . $action . '_' . md5( $identifier );
}

/**
 * Registers one attempt against a limit and reports whether the caller is now
 * blocked.
 *
 * @param string $action     Action bucket.
 * @param int    $limit      Maximum attempts within the window.
 * @param int    $window     Window length in seconds.
 * @param string $identifier Optional identifier (defaults to client IP).
 * @return int Seconds to wait when blocked, or 0 when allowed.
 */
function blueworx_headless_rl_hit( $action, $limit, $window, $identifier = '' ) {
	$key   = blueworx_headless_rl_key( $action, $identifier );
	$entry = get_transient( $key );

	if ( ! is_array( $entry ) ) {
		$entry = array(
			'count' => 0,
			'reset' => time() + $window,
		);
	}

	++$entry['count'];

	$remaining = max( 1, $entry['reset'] - time() );
	set_transient( $key, $entry, $remaining );

	if ( $entry['count'] > $limit ) {
		return $remaining;
	}

	return 0;
}

/**
 * Reports whether an action is currently blocked without recording a new hit.
 *
 * @param string $action     Action bucket.
 * @param int    $limit      Maximum attempts within the window.
 * @param string $identifier Optional identifier (defaults to client IP).
 * @return int Seconds to wait when blocked, or 0 when allowed.
 */
function blueworx_headless_rl_blocked( $action, $limit, $identifier = '' ) {
	$entry = get_transient( blueworx_headless_rl_key( $action, $identifier ) );

	if ( is_array( $entry ) && $entry['count'] > $limit ) {
		return max( 1, $entry['reset'] - time() );
	}

	return 0;
}

/**
 * Clears an action's counter (e.g. after a successful login).
 *
 * @param string $action     Action bucket.
 * @param string $identifier Optional identifier (defaults to client IP).
 * @return void
 */
function blueworx_headless_rl_clear( $action, $identifier = '' ) {
	delete_transient( blueworx_headless_rl_key( $action, $identifier ) );
}

/**
 * Builds a 429 error carrying a Retry-After value.
 *
 * @param int $retry_after Seconds to wait.
 * @return WP_Error Too-many-requests error.
 */
function blueworx_headless_rl_error( $retry_after ) {
	return new WP_Error(
		'blueworx_rate_limited',
		__( 'Too many attempts. Please wait and try again.', 'blueworx-labs-wordpress' ),
		array(
			'status'      => 429,
			'retry_after' => (int) $retry_after,
		)
	);
}
