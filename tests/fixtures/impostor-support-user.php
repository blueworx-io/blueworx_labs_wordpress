<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Creates and removes an IMPOSTOR account whose user_login happens to be
 * "blueworx_support" but which does NOT hold the managed support role — the
 * exact account a site with open registration could once have handed out.
 * blueworx_support_is_support_user() used to key on the login alone, so this
 * user silently inherited the support account's Site Protection exemption.
 *
 * There is no UI path that creates a subscriber with this login reliably (the
 * console never offers it, and the managed account may occupy the name), so
 * tests/support-access.spec.js shells out to this script against the local
 * .wp-test harness.
 *
 * Usage: php impostor-support-user.php /absolute/path/to/wp-load.php create|delete
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$command = isset( $argv[2] ) ? $argv[2] : '';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// WP_CLI, before wp-load: booting WordPress runs the plugin's init hooks, and
// blueworx_intercept_requests() (includes/login-security.php) treats this CLI
// process as an anonymous visitor. With Site Protection switched on — which is
// exactly the state the exemption test needs — it wp_die()s the script the
// instant WordPress finishes loading, and everything below here silently never
// runs while the process still exits 0. That handler skips CLI, cron, AJAX and
// REST contexts, so declaring the context this really is, is the fix.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;
require_once ABSPATH . 'wp-admin/includes/user.php';

$login    = 'blueworx_support';
$password = 'impostor-pw-!' . wp_generate_password( 12, false );

if ( 'create' === $command ) {
	$existing = get_user_by( 'login', $login );

	if ( $existing ) {
		fwrite( STDERR, "A user named {$login} already exists — refusing to overwrite it.\n" );
		exit( 1 );
	}

	$user_id = wp_insert_user(
		array(
			'user_login' => $login,
			'user_pass'  => $password,
			'user_email' => 'impostor+' . wp_generate_password( 8, false ) . '@example.invalid',
			'role'       => 'subscriber',
		)
	);

	if ( is_wp_error( $user_id ) ) {
		fwrite( STDERR, $user_id->get_error_message() . "\n" );
		exit( 1 );
	}

	echo $password . "\n";
	exit( 0 );
}

if ( 'delete' === $command ) {
	$existing = get_user_by( 'login', $login );

	// Only ever delete an impostor. The managed account holds the support role
	// and is torn down by revoking the key, never by this fixture.
	if ( $existing && in_array( 'blueworx_support', (array) $existing->roles, true ) ) {
		fwrite( STDERR, "Refusing to delete the MANAGED support account — revoke the key instead.\n" );
		exit( 1 );
	}

	if ( $existing ) {
		wp_delete_user( $existing->ID );
		clean_user_cache( $existing );
		wp_cache_flush();

		// Verify with a fresh lookup: a silent no-op here would leave the
		// impostor behind and quietly poison every later run.
		if ( get_user_by( 'login', $login ) ) {
			fwrite( STDERR, "wp_delete_user() did not remove {$login}.\n" );
			exit( 1 );
		}
	}

	echo "ok\n";
	exit( 0 );
}

fwrite( STDERR, "Unknown command: {$command}\n" );
exit( 1 );
