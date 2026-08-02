<?php
/**
 * Single sign-on: matching an identity to a WordPress user.
 *
 * The risky part of any sign-in feature. Getting this wrong hands someone else's
 * account away, so the rules are deliberately strict:
 *
 *  - the provider's own subject identifier is the real key, stored on first use;
 *  - an email may link an identity to an existing account exactly once, and only
 *    when the provider says that email is verified;
 *  - an existing user's role is never changed, and administrator is never given.
 *
 * Anything a particular site needs beyond that — extra profile fields, its own
 * roles, membership data — belongs on the hooks at the end of this file, in the
 * plugin that owns those concepts.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds or creates the WordPress user for a verified identity.
 *
 * @param array $claims Verified claims from the provider.
 * @return WP_User|WP_Error The user, or an error naming why not.
 */
function blueworx_sso_resolve_user( $claims ) {
	$subject = isset( $claims['sub'] ) ? (string) $claims['sub'] : '';

	if ( '' === $subject ) {
		return new WP_Error( 'blueworx_sso_no_subject', __( 'The identity provider did not say who signed in.', 'blueworx-labs-wordpress' ) );
	}

	$issuer = (string) blueworx_sso_option( 'issuer' );
	$user   = blueworx_sso_find_by_subject( $subject, $issuer );
	$is_new = false;

	if ( ! $user ) {
		$email    = isset( $claims['email'] ) ? (string) $claims['email'] : '';
		$verified = isset( $claims['email_verified'] ) && blueworx_sso_claim_is_true( $claims['email_verified'] );

		if ( '' !== $email ) {
			/*
			 * An unverified email is not evidence of anything. Linking on one would
			 * let anyone who can register that address at the provider walk into the
			 * matching account here.
			 */
			if ( ! $verified ) {
				return new WP_Error( 'blueworx_sso_email_unverified', __( 'The identity provider has not verified that email address.', 'blueworx-labs-wordpress' ) );
			}

			$existing = get_user_by( 'email', $email );

			if ( $existing ) {
				$already = (string) get_user_meta( $existing->ID, 'blueworx_sso_subject', true );

				// Linking is a one-time step. An account already tied to a different
				// identity must never be re-pointed on the strength of an email — that
				// is exactly how one person ends up inside another's account.
				if ( '' !== $already && $already !== $subject ) {
					return new WP_Error( 'blueworx_sso_already_linked', __( 'That account is already connected to a different sign-in.', 'blueworx-labs-wordpress' ) );
				}

				// From here the subject is the key, so a later email change at either
				// end cannot detach or hijack the account.
				blueworx_sso_link( $existing->ID, $subject, $issuer );

				return $existing;
			}
		}

		if ( '1' !== blueworx_sso_option( 'auto_register', '0' ) ) {
			return new WP_Error( 'blueworx_sso_no_account', __( 'There is no account here for that sign-in.', 'blueworx-labs-wordpress' ) );
		}

		if ( '' === $email ) {
			return new WP_Error( 'blueworx_sso_no_email', __( 'The identity provider did not supply an email address.', 'blueworx-labs-wordpress' ) );
		}

		$user = blueworx_sso_create_user( $claims, $email );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		blueworx_sso_link( $user->ID, $subject, $issuer );
		$is_new = true;
	}

	/**
	 * Fires after someone has signed in through single sign-on.
	 *
	 * Anything specific to one site belongs here — profile fields, extra roles,
	 * membership records — in the plugin that owns it, rather than in this one.
	 *
	 * @param int   $user_id The person who signed in.
	 * @param array $claims  Verified claims from the provider.
	 * @param bool  $is_new  Whether this sign-in created the account.
	 */
	do_action( 'blueworx_sso_user_authenticated', $user->ID, $claims, $is_new );

	return $user;
}

/**
 * Whether a claim that should be boolean is true.
 *
 * Providers are inconsistent here: some send a real boolean, some the string
 * "true". Both mean the same thing and both must be accepted.
 *
 * @param mixed $value Claim value.
 * @return bool
 */
function blueworx_sso_claim_is_true( $value ) {
	return true === $value || 'true' === $value || 1 === $value || '1' === $value;
}

/**
 * Finds the user already linked to a provider subject.
 *
 * @param string $subject Subject identifier.
 * @param string $issuer  Issuer the subject belongs to.
 * @return WP_User|null
 */
function blueworx_sso_find_by_subject( $subject, $issuer ) {
	$users = get_users(
		array(
			'number'     => 2,
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Both keys are indexed by WordPress and this runs once per sign-in.
				'relation' => 'AND',
				array(
					'key'   => 'blueworx_sso_subject',
					'value' => $subject,
				),
				array(
					'key'   => 'blueworx_sso_issuer',
					'value' => $issuer,
				),
			),
		)
	);

	// More than one match means the meta has been tampered with or hand-edited.
	// Refusing is the only safe answer; guessing would sign someone into whichever
	// account happened to sort first.
	if ( count( $users ) !== 1 ) {
		return null;
	}

	return $users[0];
}

/**
 * Records which provider identity an account belongs to.
 *
 * @param int    $user_id User ID.
 * @param string $subject Subject identifier.
 * @param string $issuer  Issuer.
 * @return void
 */
function blueworx_sso_link( $user_id, $subject, $issuer ) {
	update_user_meta( $user_id, 'blueworx_sso_subject', $subject );
	update_user_meta( $user_id, 'blueworx_sso_issuer', $issuer );
}

/**
 * Creates an account for a first-time sign-in.
 *
 * @param array  $claims Verified claims.
 * @param string $email  Email address.
 * @return WP_User|WP_Error
 */
function blueworx_sso_create_user( $claims, $email ) {
	$role = blueworx_sso_safe_role( blueworx_sso_option( 'default_role', 'subscriber' ) );

	$userdata = array(
		'user_login' => blueworx_sso_unique_login( $email, $claims ),
		'user_email' => $email,
		'first_name' => isset( $claims['given_name'] ) ? sanitize_text_field( $claims['given_name'] ) : '',
		'last_name'  => isset( $claims['family_name'] ) ? sanitize_text_field( $claims['family_name'] ) : '',
		'user_pass'  => wp_generate_password( 32, true, true ),
		'role'       => $role,
	);

	/**
	 * Filters the account details used on a first sign-in.
	 *
	 * @param array $userdata Arguments for wp_insert_user().
	 * @param array $claims   Verified claims from the provider.
	 */
	$userdata = apply_filters( 'blueworx_sso_new_user_data', $userdata, $claims );

	// Re-checked after the filter for the same reason it was checked before it:
	// nothing may turn a sign-in into an administrator.
	$userdata['role'] = blueworx_sso_safe_role( isset( $userdata['role'] ) ? $userdata['role'] : $role );

	$user_id = wp_insert_user( $userdata );

	if ( is_wp_error( $user_id ) ) {
		return new WP_Error( 'blueworx_sso_create_failed', __( 'The account could not be created.', 'blueworx-labs-wordpress' ) );
	}

	return get_user_by( 'id', $user_id );
}

/**
 * Reduces a role to one signing in is allowed to grant.
 *
 * @param string $role Requested role.
 * @return string Safe role slug.
 */
function blueworx_sso_safe_role( $role ) {
	$role = sanitize_key( (string) $role );

	if ( '' === $role || 'administrator' === $role || ! get_role( $role ) ) {
		return 'subscriber';
	}

	return $role;
}

/**
 * Builds a username that is not already taken.
 *
 * @param string $email  Email address.
 * @param array  $claims Verified claims.
 * @return string Username.
 */
function blueworx_sso_unique_login( $email, $claims ) {
	$base = '';

	if ( isset( $claims['preferred_username'] ) ) {
		$base = sanitize_user( $claims['preferred_username'], true );
	}

	if ( '' === $base ) {
		$parts = explode( '@', $email );
		$base  = sanitize_user( $parts[0], true );
	}

	if ( '' === $base ) {
		$base = 'user';
	}

	$login    = $base;
	$sequence = 1;

	while ( username_exists( $login ) ) {
		++$sequence;
		$login = $base . '-' . $sequence;
	}

	return $login;
}
