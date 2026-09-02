<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Creates an external viewer with a KNOWN password, so a browser test can sign
 * in as one. The real invite flow deliberately sets a random password and mails
 * a reset link, which a test cannot follow — so the account is made here and the
 * flow itself is checked separately, by asserting on what the console shows.
 *
 * Also switches the external_access feature on directly. The read-only spec
 * that uses this fixture is run on its own (per the task brief, only the two
 * external specs together), and blueworx_external_is_expired() reads a
 * switched-off feature as access having ended — so signing in as this account
 * would be refused regardless of file execution order unless this fixture
 * turns the feature on itself rather than relying on another spec file having
 * done it first.
 *
 * Usage: php external-viewer.php /absolute/path/to/wp-load.php create|delete
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
// finishes loading, and everything below would silently never run.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;
require_once ABSPATH . 'wp-admin/includes/user.php';

$login = 'bw_external_test';
$pass  = 'ExternalTest!2026';
$user  = get_user_by( 'login', $login );

if ( 'delete' === $command ) {
	if ( $user ) {
		wp_delete_user( $user->ID );
	}

	echo "deleted\n";
	exit( 0 );
}

// The feature has to be on, or blueworx_external_is_expired() reads this
// account as expired regardless of its recorded expiry date.
update_option( 'blueworx_feature_external_access', '1' );

// The role has to exist before anybody can be put in it, and it is registered on
// admin_init — which this process never reaches.
if ( function_exists( 'blueworx_external_register_role' ) ) {
	blueworx_external_register_role();
}

if ( ! $user ) {
	$id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_pass'    => $pass,
			'user_email'   => 'external-test@example.invalid',
			'display_name' => 'External Test Viewer',
			'role'         => 'blueworx_external',
		)
	);
} else {
	$id = $user->ID;
	wp_set_password( $pass, $id );
	$refreshed = get_user_by( 'id', $id );
	$refreshed->set_role( 'blueworx_external' );
}

update_user_meta( $id, '_blueworx_external_invited_by', 1 );
update_user_meta( $id, '_blueworx_external_invited_at', time() );
update_user_meta( $id, '_blueworx_external_expires_at', time() + ( 30 * 86400 ) );
update_user_meta( $id, '_blueworx_external_note', 'Created by the test suite' );

echo "created\n";
