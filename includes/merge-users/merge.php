<?php
/**
 * Merging two accounts into one.
 *
 * The engine only: what may be merged, what a merge would change, and the merge
 * itself. The screen that drives it lives in screen.php beside this file. Both
 * are kept to this folder so the theme can take the feature as it is.
 *
 * One account is kept and the other, the spare, is deleted at the end. The kept
 * account's own values always win; the spare only fills gaps. Nothing that
 * signs somebody in or grants access is carried across.
 *
 * The order of a run matters. The spare is deleted last, so a failure part-way
 * leaves it in place for the admin to see and try again, and every step before
 * that is safe to repeat.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a user meta key must never be copied from the spare.
 *
 * These are sign-ins, permissions, access windows and screen preferences: the
 * things that belong to an account rather than to the person. SureCart and
 * LearnDash progress keys are listed because they have rules of their own.
 *
 * @param string $key Meta key.
 * @return bool True to leave it behind.
 */
function blueworx_merge_users_skip_meta( $key ) {
	global $wpdb;

	$exact = array(
		$wpdb->prefix . 'capabilities',
		$wpdb->prefix . 'user_level',
		$wpdb->prefix . 'user-settings',
		$wpdb->prefix . 'user-settings-time',
		$wpdb->prefix . 'persisted_preferences',
		$wpdb->prefix . 'dashboard_quick_press_last_post_id',
		'session_tokens',
		'_application_passwords',
		'default_password_nag',
		'sc_customer_ids',
		'_sfwd-course_progress',
		'_sfwd-quizzes',
		'community-events-location',
	);

	if ( in_array( $key, $exact, true ) ) {
		return true;
	}

	$prefixes = array(
		'blueworx_sso_',
		'_blueworx_external_',
		'_blueworx_view_as',
		'blueworx_merge_',
		'manage',
		'metaboxhidden_',
		'closedpostboxes_',
		'meta-box-order_',
		'screen_layout_',
	);

	foreach ( $prefixes as $prefix ) {
		if ( 0 === strpos( $key, $prefix ) ) {
			return true;
		}
	}

	return false;
}

/**
 * A user's SureCart customer IDs, by mode.
 *
 * @param int $user_id User ID.
 * @return array Customer IDs keyed by mode ("live", "test").
 */
function blueworx_merge_users_surecart_ids( $user_id ) {
	$ids = get_user_meta( (int) $user_id, 'sc_customer_ids', true );

	return is_array( $ids ) ? array_filter( $ids, 'is_string' ) : array();
}

/**
 * Whether any meta value is worth having.
 *
 * Core writes first_name, last_name and description as empty strings on every
 * account, so "has the key" is not "has a value". '0' is a value.
 *
 * @param array $values Raw values for one key.
 * @return bool True when at least one is not an empty string.
 */
function blueworx_merge_users_has_value( $values ) {
	foreach ( (array) $values as $value ) {
		if ( '' !== $value && null !== $value ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether two accounts may be merged.
 *
 * @param int $kept_id  Account to keep.
 * @param int $spare_id Account to delete.
 * @param int $actor_id Person running the merge.
 * @return true|WP_Error True, or why not.
 */
function blueworx_merge_users_check( $kept_id, $spare_id, $actor_id ) {
	if ( is_multisite() ) {
		return new WP_Error( 'merge_users_multisite', __( 'Merging accounts is not available on a multisite network, because deleting an account there removes it from every site.', 'blueworx-labs-wordpress' ) );
	}

	$kept  = get_userdata( (int) $kept_id );
	$spare = get_userdata( (int) $spare_id );

	if ( ! $kept || ! $spare ) {
		return new WP_Error( 'merge_users_missing', __( 'One of those accounts no longer exists. It may have been merged or deleted in another tab.', 'blueworx-labs-wordpress' ) );
	}

	if ( (int) $kept->ID === (int) $spare->ID ) {
		return new WP_Error( 'merge_users_same', __( 'Pick two different accounts.', 'blueworx-labs-wordpress' ) );
	}

	if ( (int) $actor_id === (int) $spare->ID ) {
		return new WP_Error( 'merge_users_self', __( 'You cannot delete the account you are signed in as. Keep that one instead.', 'blueworx-labs-wordpress' ) );
	}

	foreach ( array( $kept, $spare ) as $user ) {
		foreach ( blueworx_readonly_roles() as $slug ) {
			if ( blueworx_readonly_user_has_role( $user, $slug ) ) {
				return new WP_Error(
					'merge_users_readonly',
					sprintf(
						/* translators: %s: username. */
						__( '%s is a support, read-only or external account. Those cannot be merged.', 'blueworx-labs-wordpress' ),
						$user->user_login
					)
				);
			}
		}
	}

	$kept_sc  = blueworx_merge_users_surecart_ids( $kept->ID );
	$spare_sc = blueworx_merge_users_surecart_ids( $spare->ID );
	$clashes  = array();

	foreach ( array_intersect_key( $kept_sc, $spare_sc ) as $mode => $id ) {
		if ( $id !== $spare_sc[ $mode ] ) {
			$clashes[ $mode ] = array( $id, $spare_sc[ $mode ] );
		}
	}

	if ( $clashes ) {
		$names = array();
		foreach ( $clashes as $pair ) {
			$names[] = implode( ' / ', $pair );
		}

		return new WP_Error(
			'merge_users_surecart',
			sprintf(
				/* translators: %s: SureCart customer IDs. */
				__( 'Both accounts have their own SureCart customer (%s), and SureCart cannot move orders between customers. Sort them out in SureCart first. Nothing has been changed.', 'blueworx-labs-wordpress' ),
				implode( ', ', $names )
			),
			array( 'clashes' => $clashes )
		);
	}

	return true;
}

/**
 * What a merge would change, without changing anything.
 *
 * @param int $kept_id  Account to keep.
 * @param int $spare_id Account to delete.
 * @return array {
 *     @type WP_User  $kept                        Account kept.
 *     @type WP_User  $spare                       Account deleted.
 *     @type array    $emails                      'kept' and 'spare' addresses.
 *     @type string[] $meta_filled                 Keys filled from the spare.
 *     @type string[] $roles_added                 Roles the kept account gains.
 *     @type int      $posts                       Posts of any type moving.
 *     @type int      $comments                    Comments moving.
 *     @type int[]    $courses_added               LearnDash courses only the spare had.
 *     @type int[]    $courses_progress_from_spare Courses where the spare was further along.
 *     @type array    $surecart                    Customer IDs the kept account ends with.
 * }
 */
function blueworx_merge_users_preview( $kept_id, $spare_id ) {
	global $wpdb;

	$kept       = get_userdata( (int) $kept_id );
	$spare      = get_userdata( (int) $spare_id );
	$kept_meta  = get_user_meta( $kept->ID );
	$spare_meta = get_user_meta( $spare->ID );
	$filled     = array();

	foreach ( $spare_meta as $key => $values ) {
		if ( blueworx_merge_users_skip_meta( $key ) || ! blueworx_merge_users_has_value( $values ) ) {
			continue;
		}

		if ( ! isset( $kept_meta[ $key ] ) || ! blueworx_merge_users_has_value( $kept_meta[ $key ] ) ) {
			$filled[] = $key;
		}
	}

	$courses = function_exists( 'blueworx_merge_users_learndash_preview' )
		? blueworx_merge_users_learndash_preview( $kept->ID, $spare->ID )
		: array(
			'added'      => array(),
			'from_spare' => array(),
		);

	return array(
		'kept'                        => $kept,
		'spare'                       => $spare,
		'emails'                      => array(
			'kept'  => $kept->user_email,
			'spare' => $spare->user_email,
		),
		'meta_filled'                 => $filled,
		'roles_added'                 => array_values( array_diff( (array) $spare->roles, (array) $kept->roles ) ),
		'posts'                       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type <> 'revision'", $spare->ID ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A count across every post type; no API gives one.
		'comments'                    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d", $spare->ID ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Same.
		'courses_added'               => $courses['added'],
		'courses_progress_from_spare' => $courses['from_spare'],
		'surecart'                    => blueworx_merge_users_surecart_ids( $kept->ID ) + blueworx_merge_users_surecart_ids( $spare->ID ),
	);
}

/**
 * Merges the spare account into the kept one, then deletes the spare.
 *
 * @param int    $kept_id    Account to keep.
 * @param int    $spare_id   Account to delete.
 * @param string $email_from Which email the kept account ends with: 'kept' or 'spare'.
 * @param int    $actor_id   Person running the merge.
 * @return array|WP_Error What was applied (as blueworx_merge_users_preview()), or why not.
 */
function blueworx_merge_users_run( $kept_id, $spare_id, $email_from, $actor_id ) {
	global $wpdb;

	$check = blueworx_merge_users_check( $kept_id, $spare_id, $actor_id );

	if ( is_wp_error( $check ) ) {
		return $check;
	}

	$applied = blueworx_merge_users_preview( $kept_id, $spare_id );
	$kept    = $applied['kept'];
	$spare   = $applied['spare'];

	if ( function_exists( 'blueworx_merge_users_learndash' ) ) {
		blueworx_merge_users_learndash( $kept->ID, $spare->ID );
	}

	// The link moves before the spare is deleted, so nothing that listens for a
	// deleted account can find a customer on it.
	if ( $applied['surecart'] ) {
		update_user_meta( $kept->ID, 'sc_customer_ids', $applied['surecart'] );
	}
	delete_user_meta( $spare->ID, 'sc_customer_ids' );

	foreach ( $applied['meta_filled'] as $key ) {
		delete_user_meta( $kept->ID, $key );

		foreach ( get_user_meta( $spare->ID, $key ) as $value ) {
			add_user_meta( $kept->ID, $key, wp_slash( $value ) );
		}
	}

	$kept_user = new WP_User( $kept->ID );
	foreach ( $applied['roles_added'] as $role ) {
		$kept_user->add_role( $role );
	}

	$wpdb->update( $wpdb->comments, array( 'user_id' => $kept->ID ), array( 'user_id' => $spare->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API moves comments between users.
	wp_cache_set_comments_last_changed();

	/**
	 * Fires before the spare account is deleted, for anything else to move.
	 *
	 * @param int $kept_id  Account kept.
	 * @param int $spare_id Account about to be deleted.
	 */
	do_action( 'blueworx_merge_users', $kept->ID, $spare->ID );

	$update = array( 'ID' => $kept->ID );

	if ( '' === $kept->user_url && '' !== $spare->user_url ) {
		$update['user_url'] = $spare->user_url;
	}

	if ( 'spare' === $email_from && $spare->user_email !== $kept->user_email ) {
		// WordPress refuses two accounts with one address, so the spare lets go
		// of it first. Written straight to the table: through wp_update_user()
		// SureCart would push the placeholder onto the spare's customer.
		$wpdb->update( $wpdb->users, array( 'user_email' => 'merged-' . $spare->ID . '@invalid' ), array( 'ID' => $spare->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_user_cache( $spare->ID );
		$update['user_email'] = $spare->user_email;
	}

	if ( count( $update ) > 1 ) {
		add_filter( 'send_email_change_email', '__return_false' );
		add_filter( 'send_password_change_email', '__return_false' );
		$updated = wp_update_user( wp_slash( $update ) );
		remove_filter( 'send_email_change_email', '__return_false' );
		remove_filter( 'send_password_change_email', '__return_false' );

		if ( is_wp_error( $updated ) ) {
			$wpdb->update( $wpdb->users, array( 'user_email' => $spare->user_email ), array( 'ID' => $spare->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_user_cache( $spare->ID );

			return $updated;
		}
	}

	add_user_meta(
		$kept->ID,
		'blueworx_merge_log',
		array(
			'time'        => time(),
			'actor'       => (int) $actor_id,
			'spare_id'    => (int) $spare->ID,
			'spare_login' => $spare->user_login,
			'spare_email' => $spare->user_email,
			'email_from'  => 'spare' === $email_from ? 'spare' : 'kept',
		)
	);

	require_once ABSPATH . 'wp-admin/includes/user.php';

	if ( ! wp_delete_user( $spare->ID, $kept->ID ) ) {
		return new WP_Error( 'merge_users_delete', __( 'Everything was moved across, but the spare account could not be deleted. Delete it from the Users list.', 'blueworx-labs-wordpress' ) );
	}

	return $applied;
}
