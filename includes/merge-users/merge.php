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
		// "View as" lives under the site's table prefix, not under its own name.
		$wpdb->prefix . '_blueworx_view_as_role',
		'session_tokens',
		'_application_passwords',
		'default_password_nag',
		// A pending "change my email" link, which would otherwise be able to
		// change the kept account's email.
		'_new_email',
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
		'_two_factor_',
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

	// An empty ID is no customer at all, and must not read as a clash.
	return is_array( $ids )
		? array_filter(
			$ids,
			static function ( $id ) {
				return is_string( $id ) && '' !== $id;
			}
		)
		: array();
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
 * Whether a LearnDash course record is finished.
 *
 * @param mixed $progress One course from _sfwd-course_progress.
 * @return bool True when every step is done.
 */
function blueworx_merge_users_course_done( $progress ) {
	return is_array( $progress )
		&& ! empty( $progress['total'] )
		&& (int) ( $progress['completed'] ?? 0 ) >= (int) $progress['total'];
}

/**
 * Whether the spare got further through a course than the kept account.
 *
 * Finishing beats any number of steps; otherwise more steps done wins. A tie
 * goes to the kept account, like everything else in a merge.
 *
 * @param mixed $kept  Kept account's record for the course.
 * @param mixed $spare Spare account's record for the course.
 * @return bool True when the spare's record should win.
 */
function blueworx_merge_users_spare_further( $kept, $spare ) {
	$kept_done  = blueworx_merge_users_course_done( $kept );
	$spare_done = blueworx_merge_users_course_done( $spare );

	if ( $kept_done !== $spare_done ) {
		return $spare_done;
	}

	return (int) ( $spare['completed'] ?? 0 ) > (int) ( $kept['completed'] ?? 0 );
}

/**
 * Combines two LearnDash progress records, course by course.
 *
 * @param mixed $kept  Kept account's _sfwd-course_progress.
 * @param mixed $spare Spare account's _sfwd-course_progress.
 * @return array Combined progress, keyed by course ID.
 */
function blueworx_merge_users_course_progress( $kept, $spare ) {
	$kept = is_array( $kept ) ? $kept : array();

	foreach ( is_array( $spare ) ? $spare : array() as $course_id => $progress ) {
		if ( ! isset( $kept[ $course_id ] ) || blueworx_merge_users_spare_further( $kept[ $course_id ], $progress ) ) {
			$kept[ $course_id ] = $progress;
		}
	}

	return $kept;
}

/**
 * Combines two lists of LearnDash quiz attempts.
 *
 * @param mixed $kept  Kept account's _sfwd-quizzes.
 * @param mixed $spare Spare account's _sfwd-quizzes.
 * @return array The kept attempts, then any of the spare's not already there.
 */
function blueworx_merge_users_quizzes( $kept, $spare ) {
	$merged = array_values( is_array( $kept ) ? $kept : array() );
	$seen   = array();

	foreach ( $merged as $attempt ) {
		$seen[ ( $attempt['quiz'] ?? '' ) . '|' . ( $attempt['time'] ?? '' ) ] = true;
	}

	foreach ( is_array( $spare ) ? $spare : array() as $attempt ) {
		$key = ( $attempt['quiz'] ?? '' ) . '|' . ( $attempt['time'] ?? '' );

		if ( ! isset( $seen[ $key ] ) ) {
			$merged[]     = $attempt;
			$seen[ $key ] = true;
		}
	}

	return $merged;
}

/**
 * Which courses a merge adds, and where the spare's progress wins.
 *
 * @param int $kept_id  Account to keep.
 * @param int $spare_id Account to delete.
 * @return array 'added' and 'from_spare', each a list of course IDs.
 */
function blueworx_merge_users_learndash_preview( $kept_id, $spare_id ) {
	$kept  = get_user_meta( (int) $kept_id, '_sfwd-course_progress', true );
	$kept  = is_array( $kept ) ? $kept : array();
	$spare = get_user_meta( (int) $spare_id, '_sfwd-course_progress', true );
	$out   = array(
		'added'      => array(),
		'from_spare' => array(),
	);

	foreach ( is_array( $spare ) ? $spare : array() as $course_id => $progress ) {
		if ( ! isset( $kept[ $course_id ] ) ) {
			$out['added'][] = (int) $course_id;
		} elseif ( blueworx_merge_users_spare_further( $kept[ $course_id ], $progress ) ) {
			$out['from_spare'][] = (int) $course_id;
		}
	}

	return $out;
}

/**
 * Moves LearnDash progress, quiz attempts and activity to the kept account.
 *
 * Course access, completion dates and group membership are plain user meta and
 * come across with the rest. Each table is only touched if LearnDash made it.
 *
 * @param int $kept_id  Account to keep.
 * @param int $spare_id Account to delete.
 * @return void
 */
function blueworx_merge_users_learndash( $kept_id, $spare_id ) {
	global $wpdb;

	$spare = get_user_meta( (int) $spare_id, '_sfwd-course_progress', true );
	if ( is_array( $spare ) && $spare ) {
		$kept = get_user_meta( (int) $kept_id, '_sfwd-course_progress', true );
		update_user_meta( (int) $kept_id, '_sfwd-course_progress', wp_slash( blueworx_merge_users_course_progress( $kept, $spare ) ) );
	}

	$spare = get_user_meta( (int) $spare_id, '_sfwd-quizzes', true );
	if ( is_array( $spare ) && $spare ) {
		$kept = get_user_meta( (int) $kept_id, '_sfwd-quizzes', true );
		update_user_meta( (int) $kept_id, '_sfwd-quizzes', wp_slash( blueworx_merge_users_quizzes( $kept, $spare ) ) );
	}

	blueworx_merge_users_learndash_access( $kept_id, $spare_id );

	foreach ( blueworx_merge_users_learndash_tables() as $table ) {
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( 'learndash_user_activity' === substr( $table, -strlen( 'learndash_user_activity' ) ) ) {
				blueworx_merge_users_learndash_activity( $table, $kept_id, $spare_id );
			}

			$wpdb->update( $table, array( 'user_id' => (int) $kept_id ), array( 'user_id' => (int) $spare_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- LearnDash has no API to move a user's records.
		}
	}
}

/**
 * Leaves one activity record per step when both accounts have one.
 *
 * Both accounts may have started the same course or lesson. LearnDash expects
 * a single record for each, so before the spare's move across, the weaker of
 * every pair goes: the finished one wins, and a tie goes to the kept account.
 * Any details LearnDash kept against a removed record go with it.
 *
 * @param string $table    The activity table.
 * @param int    $kept_id  Account to keep.
 * @param int    $spare_id Account to delete.
 * @return void
 */
function blueworx_merge_users_learndash_activity( $table, $kept_id, $spare_id ) {
	global $wpdb;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- LearnDash's own table, named by the code above.
	$pairs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT k.activity_id AS kept_row, s.activity_id AS spare_row, k.activity_status AS kept_status, s.activity_status AS spare_status
			FROM {$table} k
			JOIN {$table} s ON s.post_id = k.post_id AND s.course_id = k.course_id AND s.activity_type = k.activity_type
			WHERE k.user_id = %d AND s.user_id = %d",
			(int) $kept_id,
			(int) $spare_id
		)
	);

	$drop = array();
	foreach ( (array) $pairs as $pair ) {
		$drop[] = (int) $pair->spare_status > (int) $pair->kept_status ? (int) $pair->kept_row : (int) $pair->spare_row;
	}

	$drop = array_values( array_unique( $drop ) );

	if ( ! $drop ) {
		return;
	}

	$in = implode( ',', $drop );
	$wpdb->query( "DELETE FROM {$table} WHERE activity_id IN ({$in})" );

	$meta = $table . '_meta';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $meta ) ) ) === $meta ) {
		$wpdb->query( "DELETE FROM {$meta} WHERE activity_id IN ({$in})" );
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * The LearnDash tables that hold records against a user.
 *
 * LearnDash renamed its quiz tables, and a site that never ran that upgrade
 * still has the old names, so both are listed. Only the ones that exist are
 * touched.
 *
 * @return string[] Table names.
 */
function blueworx_merge_users_learndash_tables() {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'learndash_user_activity',
		$wpdb->prefix . 'learndash_pro_quiz_statistic_ref',
		$wpdb->prefix . 'learndash_pro_quiz_toplist',
		$wpdb->prefix . 'wp_pro_quiz_statistic_ref',
		$wpdb->prefix . 'wp_pro_quiz_toplist',
	);

	if ( class_exists( 'LDLMS_DB' ) && method_exists( 'LDLMS_DB', 'get_table_name' ) ) {
		foreach ( array( 'user_activity', 'quiz_statistic_ref', 'quiz_toplist' ) as $name ) {
			$tables[] = LDLMS_DB::get_table_name( $name );
		}
	}

	return array_values( array_unique( array_filter( $tables ) ) );
}

/**
 * Keeps course access from being cut short by a merge.
 *
 * The kept account normally wins every key, but for access that could leave
 * somebody locked out of a course they can open today: an expiring course
 * counts from the access date, so the later of the two dates wins, and an
 * "expired" mark goes when the spare still had access.
 *
 * @param int $kept_id  Account to keep.
 * @param int $spare_id Account to delete.
 * @return void
 */
function blueworx_merge_users_learndash_access( $kept_id, $spare_id ) {
	foreach ( get_user_meta( (int) $spare_id ) as $key => $values ) {
		if ( ! preg_match( '/^course_(\d+)_access_from$/', $key, $match ) ) {
			continue;
		}

		$spare_from = (int) reset( $values );
		$kept_from  = (int) get_user_meta( (int) $kept_id, $key, true );

		if ( $spare_from > $kept_from ) {
			update_user_meta( (int) $kept_id, $key, $spare_from );
		}

		if ( '' === (string) get_user_meta( (int) $spare_id, 'learndash_course_expired_' . $match[1], true ) ) {
			delete_user_meta( (int) $kept_id, 'learndash_course_expired_' . $match[1] );
		}
	}
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

	// A merge hands the kept account the spare's roles, which is the same as
	// assigning them, so it needs the same permission WordPress asks for that.
	if ( array_diff( (array) $spare->roles, (array) $kept->roles ) && ! user_can( (int) $actor_id, 'promote_users' ) ) {
		return new WP_Error( 'merge_users_roles', __( 'The account being deleted has roles the kept one does not, and this account is not allowed to hand out roles. Ask an administrator.', 'blueworx-labs-wordpress' ) );
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
 *     @type array    $surecart_before             Customer IDs the kept account started with.
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

		// The spare's "expired" mark must not lock the kept account out of a
		// course it can still open.
		if ( preg_match( '/^learndash_course_expired_(\d+)$/', $key, $match )
			&& isset( $kept_meta[ 'course_' . $match[1] . '_access_from' ] ) ) {
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
		'surecart_before'             => blueworx_merge_users_surecart_ids( $kept->ID ),
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

		foreach ( get_user_meta( $spare->ID, $key, false ) as $value ) {
			add_user_meta( $kept->ID, $key, wp_slash( $value ) );
		}
	}

	$kept_user = new WP_User( $kept->ID );
	foreach ( $applied['roles_added'] as $role ) {
		$kept_user->add_role( $role );
	}

	$comment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE user_id = %d", $spare->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API moves comments between users.
	$wpdb->update( $wpdb->comments, array( 'user_id' => $kept->ID ), array( 'user_id' => $spare->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Same.
	clean_comment_cache( array_map( 'intval', $comment_ids ) );

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

	// A run that stopped part-way can leave the spare holding the placeholder
	// below. Taking that would give the kept account an address nobody reads.
	if ( preg_match( '/^merged-\d+@invalid$/', $spare->user_email ) ) {
		$email_from = 'kept';
	}

	if ( 'spare' === $email_from && $spare->user_email !== $kept->user_email ) {
		// WordPress refuses two accounts with one address, so the spare lets go
		// of it first. Written straight to the table: through wp_update_user()
		// SureCart would push the placeholder onto the spare's customer.
		$wpdb->update( $wpdb->users, array( 'user_email' => 'merged-' . $spare->ID . '@invalid' ), array( 'ID' => $spare->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_user_cache( $spare->ID );
		$update['user_email'] = $spare->user_email;
	}

	// SureCart copies an account's email onto its customers only when the
	// account is saved. A customer that came from the spare still has the
	// spare's address until then, and receipts would keep going there.
	$moved_customer = array_diff_assoc( $applied['surecart'], $applied['surecart_before'] );

	if ( count( $update ) > 1 || $moved_customer ) {
		add_filter( 'send_email_change_email', '__return_false' );
		add_filter( 'send_password_change_email', '__return_false' );
		$updated = wp_update_user( wp_slash( $update ) );
		remove_filter( 'send_email_change_email', '__return_false' );
		remove_filter( 'send_password_change_email', '__return_false' );

		if ( is_wp_error( $updated ) ) {
			$wpdb->update( $wpdb->users, array( 'user_email' => $spare->user_email ), array( 'ID' => $spare->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_user_cache( $spare->ID );

			// Everything before this has already moved, so the screen must not
			// say nothing happened. 'started' is how it tells.
			return new WP_Error(
				'merge_users_email',
				sprintf(
					/* translators: %s: WordPress's reason. */
					__( 'Everything else moved across, but the kept account could not be saved: %s Nothing has been deleted, so it is safe to try again once that is sorted.', 'blueworx-labs-wordpress' ),
					wp_strip_all_tags( $updated->get_error_message() )
				),
				array( 'started' => true )
			);
		}
	}

	// The spare's own record of earlier merges comes too, so the history of
	// who was folded into whom survives however many merges it took.
	foreach ( get_user_meta( $spare->ID, 'blueworx_merge_log', false ) as $earlier ) {
		add_user_meta( $kept->ID, 'blueworx_merge_log', wp_slash( $earlier ) );
	}

	add_user_meta(
		$kept->ID,
		'blueworx_merge_log',
		wp_slash(
			array(
				'time'        => time(),
				'actor'       => (int) $actor_id,
				'kept_email'  => $kept->user_email,
				'spare_id'    => (int) $spare->ID,
				'spare_login' => $spare->user_login,
				'spare_email' => $spare->user_email,
				'email_from'  => 'spare' === $email_from ? 'spare' : 'kept',
			)
		)
	);

	require_once ABSPATH . 'wp-admin/includes/user.php';

	if ( ! wp_delete_user( $spare->ID, $kept->ID ) ) {
		return new WP_Error(
			'merge_users_delete',
			__( 'Everything was moved across, but the spare account could not be deleted. Delete it from the Users list.', 'blueworx-labs-wordpress' ),
			array( 'started' => true )
		);
	}

	return $applied;
}
