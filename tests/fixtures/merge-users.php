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
 *   seed [surecart-both|support|learndash]  Prints {"kept":id,"spare":id}.
 *   run <kept> <spare> <kept|spare> [actor] Prints {"ok":true} or {"error":code}.
 *   inspect <kept> <spare>                  Prints the state both accounts are in.
 *   delete <id>                             Deletes one account, as another tab would.
 *   cleanup                                 Removes every merge_* account and post.
 *
 * @package BlueWorxLabsTests
 */

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
);

/**
 * Removes every account and post this fixture made.
 *
 * @return void
 */
function merge_fixture_cleanup() {
	global $wpdb;

	foreach ( get_users( array( 'search' => 'merge_*', 'search_columns' => array( 'user_login' ) ) ) as $user ) {
		wp_delete_user( $user->ID );
	}

	foreach ( get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 's' => 'merge_fixture' ) ) as $post ) {
		wp_delete_post( $post->ID, true );
	}

	$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_content = 'merge_fixture comment'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

if ( 'cleanup' === $command ) {
	merge_fixture_cleanup();
	echo "cleaned\n";
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

	if ( 'surecart-both' === $variant ) {
		update_user_meta( $kept, 'sc_customer_ids', array( 'live' => 'cus_kept' ) );
	}

	if ( 'support' === $variant && function_exists( 'blueworx_support_role_slug' ) ) {
		if ( ! get_role( blueworx_support_role_slug() ) ) {
			add_role( blueworx_support_role_slug(), 'Support (test)', array( 'read' => true ) );
		}

		( new WP_User( $spare ) )->add_role( blueworx_support_role_slug() );
	}

	if ( 'learndash' === $variant ) {
		update_user_meta(
			$kept,
			'_sfwd-course_progress',
			array( 101 => array( 'lessons' => array( 11 => 1 ), 'topics' => array(), 'completed' => 1, 'total' => 4 ) )
		);
		update_user_meta(
			$spare,
			'_sfwd-course_progress',
			array(
				101 => array( 'lessons' => array( 11 => 1, 12 => 1, 13 => 1 ), 'topics' => array(), 'completed' => 3, 'total' => 4 ),
				202 => array( 'lessons' => array( 21 => 1 ), 'topics' => array(), 'completed' => 1, 'total' => 2 ),
			)
		);
		update_user_meta( $spare, 'course_202_access_from', 1700000000 );

		$table = $wpdb->prefix . 'learndash_user_activity';
		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$table} ( activity_id bigint(20) NOT NULL AUTO_INCREMENT, user_id bigint(20) NOT NULL DEFAULT 0, post_id bigint(20) NOT NULL DEFAULT 0, course_id bigint(20) NOT NULL DEFAULT 0, activity_type varchar(50) DEFAULT NULL, activity_status tinyint(1) DEFAULT NULL, PRIMARY KEY (activity_id) )" ); // phpcs:ignore
		$wpdb->insert( $table, array( 'user_id' => $spare, 'post_id' => 202, 'course_id' => 202, 'activity_type' => 'course', 'activity_status' => 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'merge_fixture page', 'post_author' => $spare ) );
	$post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'merge_fixture post', 'post_author' => $spare ) );

	wp_insert_comment(
		array(
			'comment_post_ID' => $page,
			'comment_content' => 'merge_fixture comment',
			'user_id'         => $spare,
			'comment_author'  => 'Spare',
		)
	);

	echo wp_json_encode( array( 'kept' => $kept, 'spare' => $spare ) ) . "\n";
	exit( 0 );
}

if ( 'run' === $command ) {
	$actor = isset( $argv[6] ) ? (int) $argv[6] : 1;
	wp_set_current_user( $actor );

	if ( ! function_exists( 'blueworx_merge_users_run' ) ) {
		echo wp_json_encode( array( 'error' => 'undefined' ) ) . "\n";
		exit( 0 );
	}

	$result = blueworx_merge_users_run( (int) $argv[3], (int) $argv[4], (string) $argv[5], $actor );
	echo wp_json_encode( is_wp_error( $result ) ? array( 'error' => $result->get_error_code() ) : array( 'ok' => true ) ) . "\n";
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

	foreach ( array( 'kept' => $kept_id, 'spare' => $spare_id ) as $label => $id ) {
		clean_user_cache( $id );
		$user = get_userdata( $id );

		if ( ! $user ) {
			$out[ $label ] = null;
			continue;
		}

		$meta = array();
		foreach ( $keys as $key ) {
			$meta[ $key ] = get_user_meta( $id, $key, 'blueworx_merge_log' !== $key );
		}

		$out[ $label ] = array(
			'email'    => $user->user_email,
			'url'      => $user->user_url,
			'roles'    => array_values( $user->roles ),
			'meta'     => $meta,
			'posts'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_title LIKE 'merge_fixture%%'", $id ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'comments' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_content = 'merge_fixture comment'", $id ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		);
	}

	$table = $wpdb->prefix . 'learndash_user_activity';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out['activity_kept'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $kept_id ) ); // phpcs:ignore
	}

	echo wp_json_encode( $out ) . "\n";
	exit( 0 );
}

fwrite( STDERR, "Unknown command: {$command}\n" );
exit( 1 );
