<?php
/**
 * Single sign-on: matching an identity to a WordPress user.
 *
 * The risky part of any sign-in feature. Getting this wrong hands someone else's
 * account away, so the rules are deliberately strict:
 *
 *  - where the site names the email domains it accepts, nobody outside them can
 *    get an account, by joining or by being linked to one;
 *  - the provider's own subject identifier is the real key, stored on first use;
 *  - an email may link an identity to an existing account exactly once, and only
 *    when the provider says that email is verified;
 *  - an existing user's role is never changed, and administrator is never given;
 *  - only the joining route may create an account. Signing in resolves or
 *    refuses, so somebody who mistypes their way in never lands in a brand new,
 *    empty account wondering where everything went.
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
 * @param array  $claims Verified claims from the provider.
 * @param string $intent Which button started this: 'login' or 'register'.
 * @return WP_User|WP_Error The user, or an error naming why not.
 */
function blueworx_sso_resolve_user( $claims, $intent = 'login' ) {
	$subject = isset( $claims['sub'] ) ? (string) $claims['sub'] : '';

	if ( '' === $subject ) {
		return new WP_Error( 'blueworx_sso_no_subject', __( 'The identity provider did not say who signed in.', 'blueworx-labs-wordpress' ) );
	}

	$email  = isset( $claims['email'] ) ? (string) $claims['email'] : '';
	$issuer = (string) blueworx_sso_option( 'issuer' );
	$user   = blueworx_sso_find_by_subject( $subject, $issuer );
	$is_new = false;

	if ( ! $user ) {
		/*
		 * The gate on getting in at all — checked on the way to an account, and
		 * only there. A provider like Google vouches for every account in the
		 * world, so without this a site that lets people join is open to all of
		 * them.
		 *
		 * Deliberately not re-checked for somebody already linked. Whether they
		 * may still sign in is whether their account still exists: a leaver's
		 * provider account is switched off at the provider, which stops them
		 * long before this code runs, and anyone else is removed on the Users
		 * screen where it can be seen. Re-checking here would instead lock
		 * people out silently, at the login box, months after the list changed.
		 */
		if ( ! blueworx_sso_email_domain_allowed( $email ) ) {
			return new WP_Error( 'blueworx_sso_domain_not_allowed', __( 'That email address cannot be given an account here.', 'blueworx-labs-wordpress' ) );
		}

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

		/*
		 * Two gates, both of which must be open. The intent stops the sign-in
		 * button quietly creating accounts for people who already have one under
		 * another address; the setting is the site owner's say in whether the
		 * joining button may create accounts at all.
		 */
		if ( 'register' !== $intent || '1' !== blueworx_sso_option( 'auto_register', '0' ) ) {
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
	 * @param int    $user_id The person who signed in.
	 * @param array  $claims  Verified claims from the provider.
	 * @param bool   $is_new  Whether this sign-in created the account.
	 * @param string $intent  Which button started this: 'login' or 'register'.
	 */
	do_action( 'blueworx_sso_user_authenticated', $user->ID, $claims, $is_new, $intent );

	return $user;
}

/**
 * The email domains this site accepts, if it names any.
 *
 * Written as a plain list — "example.com, example.co.uk" — because that is how
 * whoever fills it in thinks about it. An empty list means the site has not
 * restricted anything, which is the setting every existing site already has.
 *
 * @return array Lower-case domains, possibly empty.
 */
function blueworx_sso_allowed_domains() {
	$raw     = strtolower( (string) blueworx_sso_option( 'allowed_domains', '' ) );
	$domains = array();

	foreach ( (array) preg_split( '/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY ) as $candidate ) {
		// "@example.com" is what people paste, and it means the same thing.
		$candidate = trim( ltrim( trim( $candidate ), '@' ), '.' );

		if ( '' !== $candidate ) {
			$domains[] = $candidate;
		}
	}

	/**
	 * Filters the email domains allowed to sign in.
	 *
	 * For a site whose rule is not a fixed list — a domain per environment, or
	 * one held somewhere else.
	 *
	 * @param array $domains Lower-case domains. Empty means no restriction.
	 */
	$domains = apply_filters( 'blueworx_sso_allowed_email_domains', $domains );

	return array_values( array_unique( array_filter( array_map( 'strval', (array) $domains ) ) ) );
}

/**
 * Whether an email address may be given an account here.
 *
 * Exact domains only: a rule for example.com is not a rule for anything else
 * ending in example.com, and treating it as one is how a lookalike domain gets
 * quietly let in.
 *
 * @param string $email Email address from the verified claims.
 * @return bool True when the site names no domains, or this one is among them.
 */
function blueworx_sso_email_domain_allowed( $email ) {
	$domains = blueworx_sso_allowed_domains();

	if ( empty( $domains ) ) {
		return true;
	}

	$at = strrpos( (string) $email, '@' );

	// A site that names its domains cannot accept somebody with no address at
	// all: there is nothing to check them against.
	if ( false === $at ) {
		return false;
	}

	return in_array( strtolower( substr( (string) $email, $at + 1 ) ), $domains, true );
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
