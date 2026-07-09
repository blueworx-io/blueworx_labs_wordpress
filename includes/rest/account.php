<?php
/**
 * Headless REST layer — account lifecycle endpoints (/account/*).
 *
 * Registration (open/invite/closed), email verification, password
 * forgot/reset/change, profile update, and self-deletion. Responses that could
 * reveal whether an email is registered are deliberately non-enumerating.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the /account routes.
 *
 * @return void
 */
function blueworx_headless_register_account_routes() {
	$ns   = BLUEWORX_HEADLESS_NAMESPACE;
	$open = '__return_true';
	$auth = 'blueworx_headless_require_auth';

	$routes = array(
		array( '/account/register', 'POST', 'blueworx_headless_route_register', $open ),
		array( '/account/verify', 'POST', 'blueworx_headless_route_verify', $open ),
		array( '/account/resend-verification', 'POST', 'blueworx_headless_route_resend_verification', $open ),
		array( '/account/password/forgot', 'POST', 'blueworx_headless_route_password_forgot', $open ),
		array( '/account/password/reset', 'POST', 'blueworx_headless_route_password_reset', $open ),
		array( '/account/password/change', 'POST', 'blueworx_headless_route_password_change', $auth ),
		array( '/account', 'PATCH', 'blueworx_headless_route_account_update', $auth ),
		array( '/account', 'DELETE', 'blueworx_headless_route_account_delete', $auth ),
	);

	foreach ( $routes as $route ) {
		register_rest_route(
			$ns,
			$route[0],
			array(
				'methods'             => $route[1],
				'callback'            => $route[2],
				'permission_callback' => $route[3],
			)
		);
	}
}

/**
 * Generic "check your email" response used for non-enumerating flows.
 *
 * @return WP_REST_Response Response.
 */
function blueworx_headless_generic_email_ok() {
	return new WP_REST_Response(
		array(
			'ok'      => true,
			'message' => __( 'If that email can be used, we have sent a message with the next steps.', 'blueworx-project-wordpress-labs' ),
		),
		200
	);
}

/**
 * Validates and consumes an invite token.
 *
 * @param string $raw   Raw invite token.
 * @param string $email Registering email (checked against a pinned invite).
 * @return object|null Invite row on success, null otherwise.
 */
function blueworx_headless_consume_invite( $raw, $email ) {
	global $wpdb;

	if ( '' === (string) $raw ) {
		return null;
	}

	$table = blueworx_headless_invites_table();
	$hash  = blueworx_headless_hash_token( $raw );

	$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "SELECT * FROM {$table} WHERE token_hash = %s", $hash ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);

	if ( ! $row || null !== $row->used_at ) {
		return null;
	}

	if ( strtotime( $row->expires_at . ' UTC' ) < time() ) {
		return null;
	}

	if ( ! empty( $row->email ) && strtolower( $row->email ) !== strtolower( $email ) ) {
		return null;
	}

	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table,
		array( 'used_at' => gmdate( 'Y-m-d H:i:s' ) ),
		array( 'id' => (int) $row->id ),
		array( '%s' ),
		array( '%d' )
	);

	return $row;
}

/**
 * Stores a single-use, hashed, expiring token in user meta.
 *
 * @param int    $user_id   User ID.
 * @param string $meta_key  Meta key for the hash.
 * @param int    $ttl       Lifetime in seconds.
 * @return string Raw token to deliver to the user.
 */
function blueworx_headless_set_user_token( $user_id, $meta_key, $ttl ) {
	$raw = bin2hex( random_bytes( 32 ) );

	update_user_meta( $user_id, $meta_key, blueworx_headless_hash_token( $raw ) );
	update_user_meta( $user_id, $meta_key . '_expires', time() + $ttl );

	return $raw;
}

/**
 * Finds a user by a hashed single-use token, honouring expiry.
 *
 * @param string $raw      Raw token.
 * @param string $meta_key Meta key holding the hash.
 * @return WP_User|null Matching user or null.
 */
function blueworx_headless_find_user_by_token( $raw, $meta_key ) {
	if ( '' === (string) $raw ) {
		return null;
	}

	$users = get_users(
		array(
			'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => blueworx_headless_hash_token( $raw ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'number'     => 1,
			'fields'     => 'all',
		)
	);

	if ( empty( $users ) ) {
		return null;
	}

	$user    = $users[0];
	$expires = (int) get_user_meta( $user->ID, $meta_key . '_expires', true );

	if ( $expires < time() ) {
		return null;
	}

	return $user;
}

/**
 * Clears a single-use token pair from user meta.
 *
 * @param int    $user_id  User ID.
 * @param string $meta_key Meta key holding the hash.
 * @return void
 */
function blueworx_headless_clear_user_token( $user_id, $meta_key ) {
	delete_user_meta( $user_id, $meta_key );
	delete_user_meta( $user_id, $meta_key . '_expires' );
}

/**
 * Sends the verification email with a frontend link.
 *
 * @param WP_User $user User.
 * @param string  $raw  Raw verification token.
 * @return void
 */
function blueworx_headless_send_verification_email( $user, $raw ) {
	$link    = blueworx_headless_frontend_url() . '/verify?token=' . rawurlencode( $raw );
	$subject = __( 'Confirm your email address', 'blueworx-project-wordpress-labs' );
	/* translators: %s: verification link. */
	$body = sprintf( __( "Please confirm your email address by opening this link:\n\n%s", 'blueworx-project-wordpress-labs' ), $link );

	wp_mail( $user->user_email, $subject, $body );
}

/**
 * POST /account/register — create an account per the configured mode.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_route_register( WP_REST_Request $request ) {
	$mode = blueworx_headless_setting( 'registration_mode' );

	if ( 'closed' === $mode ) {
		return blueworx_headless_error( 'blueworx_registration_closed', __( 'Registration is not available.', 'blueworx-project-wordpress-labs' ), 403 );
	}

	$retry = blueworx_headless_rl_hit( 'register', 10, HOUR_IN_SECONDS );
	if ( $retry > 0 ) {
		return blueworx_headless_rl_error( $retry );
	}

	$email    = sanitize_email( (string) $request->get_param( 'email' ) );
	$password = (string) $request->get_param( 'password' );

	if ( ! is_email( $email ) ) {
		return blueworx_headless_error( 'blueworx_invalid_email', __( 'A valid email address is required.', 'blueworx-project-wordpress-labs' ), 400 );
	}

	if ( strlen( $password ) < 8 ) {
		return blueworx_headless_error( 'blueworx_weak_password', __( 'Password must be at least 8 characters.', 'blueworx-project-wordpress-labs' ), 400 );
	}

	$role = sanitize_key( blueworx_headless_setting( 'default_role' ) );

	if ( 'invite' === $mode ) {
		$invite = blueworx_headless_consume_invite( (string) $request->get_param( 'invite_token' ), $email );

		if ( null === $invite ) {
			return blueworx_headless_error( 'blueworx_invalid_invite', __( 'This invitation is invalid or has expired.', 'blueworx-project-wordpress-labs' ), 403 );
		}

		if ( ! empty( $invite->role ) ) {
			$role = sanitize_key( $invite->role );
		}
	}

	// Non-enumerating: existing email yields the same generic response.
	if ( email_exists( $email ) ) {
		return blueworx_headless_generic_email_ok();
	}

	$user_id = wp_insert_user(
		array(
			'user_login' => $email,
			'user_email' => $email,
			'user_pass'  => $password,
			'role'       => get_role( $role ) ? $role : 'subscriber',
		)
	);

	if ( is_wp_error( $user_id ) ) {
		return blueworx_headless_generic_email_ok();
	}

	$verify_required = ( '1' === blueworx_headless_setting( 'email_verification_required' ) );

	if ( $verify_required ) {
		update_user_meta( $user_id, 'blueworx_headless_email_unverified', '1' );
		$raw = blueworx_headless_set_user_token( $user_id, 'blueworx_headless_verify_token', 2 * DAY_IN_SECONDS );
		blueworx_headless_send_verification_email( get_user_by( 'id', $user_id ), $raw );

		return blueworx_headless_generic_email_ok();
	}

	return blueworx_headless_issue_session( get_user_by( 'id', $user_id ) );
}

/**
 * POST /account/verify — confirm an email address.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_route_verify( WP_REST_Request $request ) {
	$retry = blueworx_headless_rl_hit( 'verify', 10, HOUR_IN_SECONDS );
	if ( $retry > 0 ) {
		return blueworx_headless_rl_error( $retry );
	}

	$user = blueworx_headless_find_user_by_token( (string) $request->get_param( 'token' ), 'blueworx_headless_verify_token' );

	if ( null === $user ) {
		return blueworx_headless_error( 'blueworx_invalid_token', __( 'This confirmation link is invalid or has expired.', 'blueworx-project-wordpress-labs' ), 400 );
	}

	delete_user_meta( $user->ID, 'blueworx_headless_email_unverified' );
	blueworx_headless_clear_user_token( $user->ID, 'blueworx_headless_verify_token' );

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * POST /account/resend-verification — re-send the confirmation email.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response Response (always generic).
 */
function blueworx_headless_route_resend_verification( WP_REST_Request $request ) {
	blueworx_headless_rl_hit( 'verify', 10, HOUR_IN_SECONDS );

	$email = sanitize_email( (string) $request->get_param( 'email' ) );
	$user  = is_email( $email ) ? get_user_by( 'email', $email ) : false;

	if ( $user && '1' === get_user_meta( $user->ID, 'blueworx_headless_email_unverified', true ) ) {
		$raw = blueworx_headless_set_user_token( $user->ID, 'blueworx_headless_verify_token', 2 * DAY_IN_SECONDS );
		blueworx_headless_send_verification_email( $user, $raw );
	}

	return blueworx_headless_generic_email_ok();
}

/**
 * POST /account/password/forgot — start a password reset (non-enumerating).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response Response (always generic).
 */
function blueworx_headless_route_password_forgot( WP_REST_Request $request ) {
	$retry = blueworx_headless_rl_hit( 'forgot', 5, HOUR_IN_SECONDS );
	if ( $retry > 0 ) {
		return blueworx_headless_rl_error( $retry );
	}

	$email = sanitize_email( (string) $request->get_param( 'email' ) );
	$user  = is_email( $email ) ? get_user_by( 'email', $email ) : false;

	if ( $user ) {
		$raw     = blueworx_headless_set_user_token( $user->ID, 'blueworx_headless_reset_token', HOUR_IN_SECONDS );
		$link    = blueworx_headless_frontend_url() . '/reset-password?token=' . rawurlencode( $raw );
		$subject = __( 'Reset your password', 'blueworx-project-wordpress-labs' );
		/* translators: %s: password reset link. */
		$body = sprintf( __( "You can reset your password using this link:\n\n%s\n\nIf you did not request this, you can ignore this email.", 'blueworx-project-wordpress-labs' ), $link );

		wp_mail( $user->user_email, $subject, $body );
	}

	return blueworx_headless_generic_email_ok();
}

/**
 * POST /account/password/reset — set a new password from a reset token.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_route_password_reset( WP_REST_Request $request ) {
	$retry = blueworx_headless_rl_hit( 'reset', 10, HOUR_IN_SECONDS );
	if ( $retry > 0 ) {
		return blueworx_headless_rl_error( $retry );
	}

	$password = (string) $request->get_param( 'password' );

	if ( strlen( $password ) < 8 ) {
		return blueworx_headless_error( 'blueworx_weak_password', __( 'Password must be at least 8 characters.', 'blueworx-project-wordpress-labs' ), 400 );
	}

	$user = blueworx_headless_find_user_by_token( (string) $request->get_param( 'token' ), 'blueworx_headless_reset_token' );

	if ( null === $user ) {
		return blueworx_headless_error( 'blueworx_invalid_token', __( 'This reset link is invalid or has expired.', 'blueworx-project-wordpress-labs' ), 400 );
	}

	wp_set_password( $password, $user->ID );
	blueworx_headless_clear_user_token( $user->ID, 'blueworx_headless_reset_token' );
	blueworx_headless_revoke_user_tokens( $user->ID );
	blueworx_headless_bump_token_version( $user->ID );

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * POST /account/password/change — change password for the current user.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_route_password_change( WP_REST_Request $request ) {
	$user     = wp_get_current_user();
	$current  = (string) $request->get_param( 'current_password' );
	$new_pass = (string) $request->get_param( 'new_password' );

	if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
		return blueworx_headless_error( 'blueworx_bad_password', __( 'Your current password is incorrect.', 'blueworx-project-wordpress-labs' ), 403 );
	}

	if ( strlen( $new_pass ) < 8 ) {
		return blueworx_headless_error( 'blueworx_weak_password', __( 'Password must be at least 8 characters.', 'blueworx-project-wordpress-labs' ), 400 );
	}

	wp_set_password( $new_pass, $user->ID );
	blueworx_headless_revoke_user_tokens( $user->ID );
	blueworx_headless_bump_token_version( $user->ID );

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * PATCH /account — update whitelisted profile fields.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_route_account_update( WP_REST_Request $request ) {
	$user_id = get_current_user_id();
	$fields  = array( 'display_name', 'first_name', 'last_name', 'nickname' );
	$update  = array( 'ID' => $user_id );

	foreach ( $fields as $field ) {
		$value = $request->get_param( $field );

		if ( null !== $value ) {
			$update[ $field ] = sanitize_text_field( (string) $value );
		}
	}

	$result = wp_update_user( $update );

	if ( is_wp_error( $result ) ) {
		return blueworx_headless_error( 'blueworx_update_failed', __( 'Could not update your profile.', 'blueworx-project-wordpress-labs' ), 400 );
	}

	return new WP_REST_Response( blueworx_headless_user_payload( get_user_by( 'id', $user_id ) ), 200 );
}

/**
 * DELETE /account — delete the current user after password re-authentication.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_route_account_delete( WP_REST_Request $request ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';

	$user    = wp_get_current_user();
	$current = (string) $request->get_param( 'current_password' );

	if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
		return blueworx_headless_error( 'blueworx_bad_password', __( 'Your current password is incorrect.', 'blueworx-project-wordpress-labs' ), 403 );
	}

	$user_id = $user->ID;

	blueworx_headless_revoke_user_tokens( $user_id );
	blueworx_headless_bump_token_version( $user_id );
	blueworx_headless_clear_refresh_cookie();

	/**
	 * Fires before a headless self-deletion, for sites that prefer to
	 * anonymise rather than hard-delete.
	 *
	 * @param int $user_id User being deleted.
	 */
	do_action( 'blueworx_headless_before_account_delete', $user_id );

	wp_delete_user( $user_id );

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}
