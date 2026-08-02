<?php
/**
 * Single sign-on: a short record of recent attempts.
 *
 * When sign-in fails the person only ever sees a generic message, which is right
 * for them and useless for whoever has to fix it. This keeps the specific reason
 * where an administrator can read it.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many attempts to keep.
 */
const BLUEWORX_SSO_LOG_LIMIT = 20;

/**
 * Records one sign-in attempt.
 *
 * @param string $outcome Either 'success' or 'failure'.
 * @param string $detail  Reason code, or the username on success.
 * @return void
 */
function blueworx_sso_log( $outcome, $detail = '' ) {
	$entries = blueworx_sso_get_log();

	array_unshift(
		$entries,
		array(
			'time'    => time(),
			'outcome' => 'success' === $outcome ? 'success' : 'failure',
			'detail'  => sanitize_text_field( $detail ),
		)
	);

	update_option( 'blueworx_sso_log', array_slice( $entries, 0, BLUEWORX_SSO_LOG_LIMIT ), false );

	if ( 'success' !== $outcome ) {
		error_log( 'BlueWorx SSO: sign-in failed (' . $detail . ')' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The reason is deliberately kept out of the browser, so this is the only place it can be read.
	}
}

/**
 * Reads the recorded attempts, newest first.
 *
 * @return array List of entries.
 */
function blueworx_sso_get_log() {
	$entries = get_option( 'blueworx_sso_log', array() );

	return is_array( $entries ) ? $entries : array();
}
