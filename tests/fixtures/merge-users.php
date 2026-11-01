<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Seeds two accounts for the merge users spec, runs the merge engine directly,
 * and reports what each account holds afterwards. Everything it makes is named
 * merge_* so cleanup finds it without a list.
 *
 * Usage: php merge-users.php /absolute/path/to/wp-load.php <command> [args]
 *
 *   seed [variant]                          Prints {"kept":id,"spare":id}. Variants:
 *                                           surecart-both, surecart-empty, support,
 *                                           readonly-kept, manager, no-url, cpt,
 *                                           admin-spare, learndash.
 *   run <kept> <spare> <kept|spare> [actor] Prints {"ok":true} or {"error":code}.
 *   claim <email>                           Gives a third account that address, straight in the table.
 *   inspect <kept> <spare>                  Prints the state both accounts are in.
 *   placeholder <id>                        Gives an account the address a half-finished merge leaves.
 *   delete <id>                             Deletes one account, as another tab would.
 *   feature <on|off>                        Switches the function on or off.
 *   cleanup                                 Removes every merge_* account, post and test table.
 *
 * @package BlueWorxLabsTests
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- A fixture: it seeds and reads the tables directly on purpose.

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$command = isset( $argv[2] ) ? $argv[2] : '';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// See tests/fixtures/impostor-support-user.php for why this comes before
// wp-load: Site Protection wp_die()s an anonymous request as soon as WordPress
// finishes loading.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;
require_once ABSPATH . 'wp-admin/includes/user.php';

$keys = array(
	'first_name',
	'last_name',
	'bw_shared',
	'bw_only_spare',
	'session_tokens',
	'_application_passwords',
	'sc_customer_ids',
	'blueworx_merge_log',
	'_sfwd-course_progress',
	'_sfwd-quizzes',
	'course_202_access_from',
	'course_101_access_from',
	'learndash_course_expired_101',
	'learndash_course_expired_303',
	'_new_email',
	$GLOBALS['wpdb']->prefix . '_blueworx_view_as_role',
);

/**
 * The LearnDash tables the fixture stands in for, when LearnDash is not there.
 *
 * @return string[] Table names.
 */
function merge_fixture_tables() {
	global $wpdb;

	return array(
		$wpdb->prefix . 'learndash_user_activity',
		$wpdb->prefix . 'learndash_user_activity_meta',
		$wpdb->prefix . 'wp_pro_quiz_statistic_ref',
	);
}

/**
 * Removes every account and post this fixture made.
 *
 * @return void
 */
function merge_fixture_cleanup() {
	global $wpdb;

	foreach ( get_users(
		array(
			'search'         => 'merge_*',
			'search_columns' => array( 'user_login' ),
		)
	) as $user ) {
		wp_delete_user( $user->ID );
	}

	// Straight from the table, so posts of a type nobody registered go too.
	foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE 'merge\\_fixture%'" ) as $post_id ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_delete_post( (int) $post_id, true );
	}

	$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_content = 'merge_fixture comment'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// Only ever the fixture's own: a site with LearnDash keeps its tables.
	if ( ! defined( 'LEARNDASH_VERSION' ) ) {
		foreach ( merge_fixture_tables() as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore
		}
	}
}

if ( 'cleanup' === $command ) {
	merge_fixture_cleanup();
	echo "cleaned\n";
	exit( 0 );
}

if ( 'feature' === $command ) {
	update_option( 'blueworx_feature_merge_users', 'on' === ( $argv[3] ?? '' ) ? '1' : '0' );
	echo 'ok
';
	exit( 0 );
}

if ( 'seed' === $command ) {
	global $wpdb;

	merge_fixture_cleanup();
	$variant = isset( $argv[3] ) ? $argv[3] : '';

	$kept = wp_insert_user(
		array(
			'user_login' => 'merge_kept',
			'user_pass'  => wp_generate_password(),
			'user_email' => 'merge-kept@example.invalid',
			'first_name' => 'Kept',
			'role'       => 'subscriber',
		)
	);

	$spare = wp_insert_user(
		array(
			'user_login' => 'merge_spare',
			'user_pass'  => wp_generate_password(),
			'user_email' => 'merge-spare@example.invalid',
			'first_name' => 'Spare',
			'last_name'  => 'Person',
			'user_url'   => 'https://spare.example.invalid',
			'role'       => 'subscriber',
		)
	);

	( new WP_User( $spare ) )->add_role( 'author' );

	update_user_meta( $kept, 'bw_shared', 'kept' );
	update_user_meta( $spare, 'bw_shared', 'spare' );
	update_user_meta( $spare, 'bw_only_spare', 'yes' );
	update_user_meta( $spare, 'session_tokens', array( 'abc' => array( 'expiration' => time() + 3600 ) ) );
	update_user_meta( $spare, '_application_passwords', array( array( 'uuid' => 'merge-fixture' ) ) );
	update_user_meta( $spare, 'sc_customer_ids', array( 'live' => 'cus_spare' ) );
	update_user_meta( $spare, $wpdb->prefix . '_blueworx_view_as_role', 'editor' );
	update_user_meta(
		$spare,
		'_new_email',
		array(
			'hash'     => 'x',
			'newemail' => 'someone-else@example.invalid',
		)
	);
	add_user_meta( $spare, 'blueworx_merge_log', array( 'spare_login' => 'merge_older' ) );

	if ( 'surecart-both' === $variant ) {
		update_user_meta( $kept, 'sc_customer_ids', array( 'live' => 'cus_kept' ) );
	}

	if ( 'surecart-empty' === $variant ) {
		update_user_meta( $kept, 'sc_customer_ids', array( 'live' => '' ) );
	}

	if ( 'readonly-kept' === $variant && function_exists( 'blueworx_support_role_slug' ) ) {
		if ( ! get_role( blueworx_support_role_slug() ) ) {
			add_role( blueworx_support_role_slug(), 'Support (test)', array( 'read' => true ) );
		}

		( new WP_User( $kept ) )->add_role( blueworx_support_role_slug() );
	}

	if ( 'admin-spare' === $variant ) {
		( new WP_User( $spare ) )->add_role( 'administrator' );
	}

	if ( 'cpt' === $variant ) {
		wp_insert_post(
			array(
				'post_type'   => 'merge_fixture_cpt',
				'post_status' => 'publish',
				'post_title'  => 'merge_fixture cpt',
				'post_author' => $spare,
			)
		);
	}

	if ( 'support' === $variant && function_exists( 'blueworx_support_role_slug' ) ) {
		if ( ! get_role( blueworx_support_role_slug() ) ) {
			add_role( blueworx_support_role_slug(), 'Support (test)', array( 'read' => true ) );
		}

		( new WP_User( $spare ) )->add_role( blueworx_support_role_slug() );
	}

	if ( 'no-url' === $variant ) {
		wp_update_user(
			array(
				'ID'       => $spare,
				'user_url' => '',
			)
		);
	}

	$manager = 0;
	if ( 'manager' === $variant ) {
		remove_role( 'merge_user_manager' );
		add_role(
			'merge_user_manager',
			'User manager (test)',
			array(
				'read'         => true,
				'list_users'   => true,
				'edit_users'   => true,
				'delete_users' => true,
			)
		);
		$manager = wp_insert_user(
			array(
				'user_login' => 'merge_manager',
				'user_pass'  => wp_generate_password(),
				'user_email' => 'merge-manager@example.invalid',
				'role'       => 'merge_user_manager',
			)
		);
	}

	if ( 'learndash' === $variant ) {
		update_user_meta( $kept, 'course_101_access_from', 1600000000 );
		update_user_meta( $kept, 'learndash_course_expired_101', 1600000000 );
		update_user_meta( $spare, 'course_101_access_from', 1700000000 );
		update_user_meta( $kept, 'course_303_access_from', 1700000000 );
		update_user_meta( $spare, 'learndash_course_expired_303', 1600000000 );

		$group = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'draft',
				'post_title'  => 'merge_fixture group',
				'post_author' => $kept,
			)
		);
		update_user_meta( $spare, 'learndash_group_users_' . $group, $group );

		$legacy = $wpdb->prefix . 'wp_pro_quiz_statistic_ref';
		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$legacy} ( statistic_ref_id bigint(20) NOT NULL AUTO_INCREMENT, quiz_id bigint(20) NOT NULL DEFAULT 0, user_id bigint(20) NOT NULL DEFAULT 0, create_time bigint(20) NOT NULL DEFAULT 0, PRIMARY KEY (statistic_ref_id) )" ); // phpcs:ignore
		$wpdb->insert(
			$legacy,
			array(
				'quiz_id'     => 9,
				'user_id'     => $spare,
				'create_time' => time(),
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		update_user_meta(
			$kept,
			'_sfwd-course_progress',
			array(
				101 => array(
					'lessons'   => array( 11 => 1 ),
					'topics'    => array(),
					'completed' => 1,
					'total'     => 4,
				),
			)
		);
		update_user_meta(
			$spare,
			'_sfwd-course_progress',
			array(
				101 => array(
					'lessons'   => array(
						11 => 1,
						12 => 1,
						13 => 1,
					),
					'topics'    => array(),
					'completed' => 3,
					'total'     => 4,
				),
				202 => array(
					'lessons'   => array( 21 => 1 ),
					'topics'    => array(),
					'completed' => 1,
					'total'     => 2,
				),
			)
		);
		update_user_meta( $spare, 'course_202_access_from', 1700000000 );

		$table = $wpdb->prefix . 'learndash_user_activity';
		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$table} ( activity_id bigint(20) NOT NULL AUTO_INCREMENT, user_id bigint(20) NOT NULL DEFAULT 0, post_id bigint(20) NOT NULL DEFAULT 0, course_id bigint(20) NOT NULL DEFAULT 0, activity_type varchar(50) DEFAULT NULL, activity_status tinyint(1) DEFAULT NULL, PRIMARY KEY (activity_id) )" ); // phpcs:ignore
		$wpdb->insert(
			$table,
			array(
				'user_id'         => $spare,
				'post_id'         => 202,
				'course_id'       => 202,
				'activity_type'   => 'course',
				'activity_status' => 0,
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Both started course 101; only the spare finished it.
		$wpdb->insert(
			$table,
			array(
				'user_id'         => $kept,
				'post_id'         => 101,
				'course_id'       => 101,
				'activity_type'   => 'course',
				'activity_status' => 0,
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$kept_row = $wpdb->insert_id;
		$wpdb->insert(
			$table,
			array(
				'user_id'         => $spare,
				'post_id'         => 101,
				'course_id'       => 101,
				'activity_type'   => 'course',
				'activity_status' => 1,
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$meta_table = $table . '_meta';
		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$meta_table} ( activity_meta_id bigint(20) NOT NULL AUTO_INCREMENT, activity_id bigint(20) NOT NULL DEFAULT 0, activity_meta_key varchar(255) DEFAULT NULL, activity_meta_value mediumtext, PRIMARY KEY (activity_meta_id) )" ); // phpcs:ignore
		$wpdb->insert(
			$meta_table,
			array(
				'activity_id'         => $kept_row,
				'activity_meta_key'   => 'steps_completed',
				'activity_meta_value' => '1',
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	$page = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'merge_fixture page',
			'post_author' => $spare,
		)
	);
	$post = wp_insert_post(
		array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => 'merge_fixture post',
			'post_author' => $spare,
		)
	);

	wp_insert_comment(
		array(
			'comment_post_ID' => $page,
			'comment_content' => 'merge_fixture comment',
			'user_id'         => $spare,
			'comment_author'  => 'Spare',
		)
	);

	echo wp_json_encode(
		array(
			'kept'    => $kept,
			'spare'   => $spare,
			'manager' => $manager,
		)
	) . "\n";
	exit( 0 );
}

if ( 'run' === $command ) {
	$actor = isset( $argv[6] ) ? (int) $argv[6] : 1;
	wp_set_current_user( $actor );

	if ( ! function_exists( 'blueworx_merge_users_run' ) ) {
		echo wp_json_encode( array( 'error' => 'undefined' ) ) . "\n";
		exit( 0 );
	}

	// Records who SureCart would hear about, and any email WordPress tries to send.
	$updated = array();
	$mails   = 0;
	add_action(
		'profile_update',
		static function ( $id ) use ( &$updated ) {
			$updated[] = (int) $id;
		}
	);
	add_filter(
		'pre_wp_mail',
		static function () use ( &$mails ) {
			++$mails;
			return true;
		}
	);

	// Loads the spare's comments into the cache first, as a busy site would have
	// them, to see whether the cache hears that they moved.
	$comment_ids = get_comments(
		array(
			'user_id' => (int) $argv[4],
			'fields'  => 'ids',
		)
	);
	foreach ( $comment_ids as $comment_id ) {
		get_comment( $comment_id );
	}

	$result = blueworx_merge_users_run( (int) $argv[3], (int) $argv[4], (string) $argv[5], $actor );

	if ( is_wp_error( $result ) ) {
		$data = $result->get_error_data();
		$out  = array( 'error' => $result->get_error_code() );

		if ( is_array( $data ) && ! empty( $data['started'] ) ) {
			$out['started'] = true;
		}
	} else {
		$out = array(
			'ok'            => true,
			'updated'       => array_values( array_unique( $updated ) ),
			'mails'         => $mails,
			'comment_owner' => $comment_ids ? (int) get_comment( $comment_ids[0] )->user_id : 0,
		);
	}

	echo wp_json_encode( $out ) . "\n";
	exit( 0 );
}

if ( 'claim' === $command ) {
	global $wpdb;
	$third = wp_insert_user(
		array(
			'user_login' => 'merge_third',
			'user_pass'  => wp_generate_password(),
			'user_email' => 'merge-third@example.invalid',
			'role'       => 'subscriber',
		)
	);
	$wpdb->update( $wpdb->users, array( 'user_email' => (string) $argv[3] ), array( 'ID' => $third ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	clean_user_cache( $third );
	echo wp_json_encode( array( 'third' => $third ) ) . "\n";
	exit( 0 );
}

if ( 'placeholder' === $command ) {
	global $wpdb;
	$wpdb->update( $wpdb->users, array( 'user_email' => 'merged-' . (int) $argv[3] . '@invalid' ), array( 'ID' => (int) $argv[3] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	clean_user_cache( (int) $argv[3] );
	echo "ok\n";
	exit( 0 );
}

if ( 'delete' === $command ) {
	wp_delete_user( (int) $argv[3] );
	echo "deleted\n";
	exit( 0 );
}

if ( 'inspect' === $command ) {
	global $wpdb;

	$kept_id  = (int) $argv[3];
	$spare_id = (int) $argv[4];
	$out      = array();

	foreach ( array(
		'kept'  => $kept_id,
		'spare' => $spare_id,
	) as $label => $id ) {
		clean_user_cache( $id );
		$user = get_userdata( $id );

		if ( ! $user ) {
			$out[ $label ] = null;
			continue;
		}

		$meta = array();
		foreach ( $keys as $key ) {
			$name          = $wpdb->prefix . '_blueworx_view_as_role' === $key ? 'view_as' : $key;
			$meta[ $name ] = get_user_meta( $id, $key, 'blueworx_merge_log' !== $key );
		}

		$out[ $label ] = array(
			'email'    => $user->user_email,
			'url'      => $user->user_url,
			'roles'    => array_values( $user->roles ),
			'meta'     => $meta,
			'posts'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_title LIKE %s", $id, $wpdb->esc_like( 'merge_fixture' ) . '%' ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'comments' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_content = 'merge_fixture comment'", $id ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		);
	}

	$table = $wpdb->prefix . 'learndash_user_activity';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out['activity_kept']       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $kept_id ) ); // phpcs:ignore
		$out['activity_101_status'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(activity_status) FROM {$table} WHERE user_id = %d AND course_id = 101", $kept_id ) ); // phpcs:ignore
	}

	$meta_table = $table . '_meta';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $meta_table ) ) === $meta_table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out['activity_meta_orphans'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$meta_table} m LEFT JOIN {$table} a ON a.activity_id = m.activity_id WHERE a.activity_id IS NULL" ); // phpcs:ignore
	}

	$legacy = $wpdb->prefix . 'wp_pro_quiz_statistic_ref';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy ) ) === $legacy ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out['quiz_stats_kept'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$legacy} WHERE user_id = %d", $kept_id ) ); // phpcs:ignore
	}

	echo wp_json_encode( $out ) . "\n";
	exit( 0 );
}

fwrite( STDERR, "Unknown command: {$command}\n" );
exit( 1 );
