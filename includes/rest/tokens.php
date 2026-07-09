<?php
/**
 * Headless REST layer — refresh-token families & revocation.
 *
 * Opaque refresh tokens are stored only as SHA-256 hashes. Each login starts a
 * family; refresh rotates within the family. Presenting an already-consumed
 * (revoked) token is treated as theft and revokes the whole family. A per-user
 * `token_version` integer provides instant global revocation of access tokens.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the user's current token version (0 when unset).
 *
 * @param int $user_id User ID.
 * @return int Token version.
 */
function blueworx_headless_token_version( $user_id ) {
	return (int) get_user_meta( (int) $user_id, 'blueworx_headless_token_version', true );
}

/**
 * Increments the user's token version, instantly invalidating every
 * outstanding access token for that user.
 *
 * @param int $user_id User ID.
 * @return void
 */
function blueworx_headless_bump_token_version( $user_id ) {
	$current = blueworx_headless_token_version( $user_id );
	update_user_meta( (int) $user_id, 'blueworx_headless_token_version', $current + 1 );
}

/**
 * Hashes a raw refresh token for storage/lookup.
 *
 * @param string $raw Raw token.
 * @return string SHA-256 hex hash.
 */
function blueworx_headless_hash_token( $raw ) {
	return hash( 'sha256', (string) $raw );
}

/**
 * Issues a refresh token, persisting its hash in the given (or a new) family.
 *
 * @param int    $user_id   User ID.
 * @param string $family_id Existing family, or '' to start a new one.
 * @return array|null { token, family_id, expires } or null on failure.
 */
function blueworx_headless_issue_refresh_token( $user_id, $family_id = '' ) {
	global $wpdb;

	$raw     = bin2hex( random_bytes( 32 ) );
	$family  = '' !== $family_id ? $family_id : bin2hex( random_bytes( 16 ) );
	$now     = time();
	$expires = $now + blueworx_headless_refresh_ttl();
	$table   = blueworx_headless_refresh_tokens_table();
	$agent   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

	$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table,
		array(
			'user_id'    => (int) $user_id,
			'family_id'  => $family,
			'token_hash' => blueworx_headless_hash_token( $raw ),
			'issued_at'  => gmdate( 'Y-m-d H:i:s', $now ),
			'expires_at' => gmdate( 'Y-m-d H:i:s', $expires ),
			'revoked'    => 0,
			'ip'         => blueworx_headless_client_ip(),
			'user_agent' => mb_substr( $agent, 0, 255 ),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
	);

	if ( ! $inserted ) {
		return null;
	}

	return array(
		'token'     => $raw,
		'family_id' => $family,
		'expires'   => $expires,
	);
}

/**
 * Looks up a stored refresh-token row by raw token.
 *
 * @param string $raw Raw token.
 * @return object|null Row, or null when not found.
 */
function blueworx_headless_find_refresh_token( $raw ) {
	global $wpdb;

	$table = blueworx_headless_refresh_tokens_table();

	return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "SELECT * FROM {$table} WHERE token_hash = %s", blueworx_headless_hash_token( $raw ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
}

/**
 * Revokes every token in a family (e.g. on logout or reuse-detection).
 *
 * @param string $family_id Family identifier.
 * @return void
 */
function blueworx_headless_revoke_family( $family_id ) {
	global $wpdb;

	$table = blueworx_headless_refresh_tokens_table();

	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "UPDATE {$table} SET revoked = 1 WHERE family_id = %s", $family_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
}

/**
 * Revokes all refresh tokens for a user.
 *
 * @param int $user_id User ID.
 * @return void
 */
function blueworx_headless_revoke_user_tokens( $user_id ) {
	global $wpdb;

	$table = blueworx_headless_refresh_tokens_table();

	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "UPDATE {$table} SET revoked = 1 WHERE user_id = %d", (int) $user_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
}

/**
 * Rotates a refresh token: validates the presented token, marks it consumed,
 * and issues a successor in the same family. Detects replay of a consumed
 * token and revokes the family.
 *
 * @param string $raw Raw refresh token from the cookie.
 * @return array|WP_Error { user_id, token, family_id, expires } or error.
 */
function blueworx_headless_rotate_refresh_token( $raw ) {
	global $wpdb;

	if ( '' === (string) $raw ) {
		return blueworx_headless_error( 'blueworx_no_refresh', __( 'No session found.', 'blueworx-project-wordpress-labs' ), 401 );
	}

	$row = blueworx_headless_find_refresh_token( $raw );

	if ( ! $row ) {
		return blueworx_headless_error( 'blueworx_invalid_refresh', __( 'Session is no longer valid.', 'blueworx-project-wordpress-labs' ), 401 );
	}

	// Replay of a consumed token: treat as theft, revoke the whole family.
	if ( 1 === (int) $row->revoked ) {
		blueworx_headless_revoke_family( $row->family_id );

		return blueworx_headless_error( 'blueworx_refresh_reuse', __( 'Session revoked for security reasons. Please sign in again.', 'blueworx-project-wordpress-labs' ), 401 );
	}

	if ( strtotime( $row->expires_at . ' UTC' ) < time() ) {
		return blueworx_headless_error( 'blueworx_expired_refresh', __( 'Session has expired.', 'blueworx-project-wordpress-labs' ), 401 );
	}

	$successor = blueworx_headless_issue_refresh_token( (int) $row->user_id, $row->family_id );

	if ( ! $successor ) {
		return blueworx_headless_error( 'blueworx_rotate_failed', __( 'Could not refresh the session.', 'blueworx-project-wordpress-labs' ), 500 );
	}

	$table = blueworx_headless_refresh_tokens_table();

	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table,
		array(
			'revoked'     => 1,
			'replaced_by' => blueworx_headless_hash_token( $successor['token'] ),
		),
		array( 'id' => (int) $row->id ),
		array( '%d', '%s' ),
		array( '%d' )
	);

	return array(
		'user_id'   => (int) $row->user_id,
		'token'     => $successor['token'],
		'family_id' => $successor['family_id'],
		'expires'   => $successor['expires'],
	);
}
