<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Creates and removes an account in a named role, so a spec can sign in as
 * somebody who is not an administrator. The Guides screen decides what to show
 * from what the reader can actually do, and that cannot be exercised from an
 * administrator session — there is no UI path to a second account's password.
 *
 * Usage: php guide-reader.php /absolute/path/to/wp-load.php create|delete <role>
 *
 * @package BlueWorxLabsTests
 */

/**
 * Fails the run with a message on stderr.
 *
 * @param string $message What went wrong.
 * @return void
 */
function blueworx_guide_reader_fail( $message ) {
	// A CLI script writing to its own stderr, not a site touching the filesystem.
	fwrite( STDERR, $message . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

/**
 * Creates the account and prints its login and password.
 *
 * @param string $login Account login.
 * @param string $role  Role slug.
 * @return void
 */
function blueworx_guide_reader_create( $login, $role ) {
	$existing = get_user_by( 'login', $login );

	if ( $existing ) {
		wp_delete_user( $existing->ID );
	}

	$password = 'guide-reader-!' . wp_generate_password( 12, false );

	$user_id = wp_insert_user(
		array(
			'user_login' => $login,
			'user_pass'  => $password,
			'user_email' => $login . '+' . wp_generate_password( 8, false ) . '@example.invalid',
			'role'       => $role,
		)
	);

	if ( is_wp_error( $user_id ) ) {
		blueworx_guide_reader_fail( $user_id->get_error_message() );
	}

	// Not escaped: this is a shell pipe read by execFileSync(), not a page.
	echo $login . ' ' . $password . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 0 );
}

/**
 * Removes the account, and proves it is gone.
 *
 * @param string $login Account login.
 * @return void
 */
function blueworx_guide_reader_delete( $login ) {
	$existing = get_user_by( 'login', $login );

	if ( $existing ) {
		wp_delete_user( $existing->ID );
		clean_user_cache( $existing );
		wp_cache_flush();

		// Verify with a fresh lookup: a silent no-op here would leave the
		// account behind and quietly poison every later run.
		if ( get_user_by( 'login', $login ) ) {
			blueworx_guide_reader_fail( "wp_delete_user() did not remove {$login}." );
		}
	}

	echo "ok\n";
	exit( 0 );
}

// The arguments and the WordPress boot stay at the top of the file: wp-load.php
// declares $wpdb and the rest of core's globals, and requiring it from inside a
// function would make every one of them a local instead.
$blueworx_wp_load = isset( $argv[1] ) ? $argv[1] : '';
$blueworx_command = isset( $argv[2] ) ? $argv[2] : '';
$blueworx_role    = isset( $argv[3] ) ? $argv[3] : '';

if ( '' === $blueworx_wp_load || ! is_file( $blueworx_wp_load ) ) {
	blueworx_guide_reader_fail( 'wp-load.php not found: ' . $blueworx_wp_load );
}

if ( ! preg_match( '/^[a-z_]+$/', $blueworx_role ) ) {
	blueworx_guide_reader_fail( "Unusable role: {$blueworx_role}" );
}

// WP_CLI, before wp-load: see the note in impostor-support-user.php. Booting
// WordPress runs the plugin's request guard, which would wp_die() this script
// as an anonymous visitor while it still exited 0.
// Core's own constants, named by core — a prefix here would define nothing.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound

require $blueworx_wp_load;
require_once ABSPATH . 'wp-admin/includes/user.php';

$blueworx_login = 'bw_guide_' . $blueworx_role;

if ( 'create' === $blueworx_command ) {
	blueworx_guide_reader_create( $blueworx_login, $blueworx_role );
}

if ( 'delete' === $blueworx_command ) {
	blueworx_guide_reader_delete( $blueworx_login );
}

blueworx_guide_reader_fail( "Unknown command: {$blueworx_command}" );
