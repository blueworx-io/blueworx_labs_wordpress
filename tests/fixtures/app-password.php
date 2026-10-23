<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Sets up and tears down what a REST request signed in with an application
 * password needs: an administrator and an editor, each with one application
 * password, the plugin's "show application passwords" option on, and a
 * must-use plugin that tells WordPress the test site may use them. WordPress
 * otherwise only allows application passwords over HTTPS or on a site marked
 * local, and the test site is neither.
 *
 * Usage: php app-password.php /absolute/path/to/wp-load.php create|delete
 *
 * "create" prints two lines, "admin <login> <password>" and
 * "editor <login> <password>".
 *
 * @package BlueWorxLabsTests
 */

// phpcs:disable WordPress.WP.AlternativeFunctions -- A CLI fixture writing its own files and stderr.

define( 'WP_USE_THEMES', false );
require $argv[1];
require_once ABSPATH . 'wp-admin/includes/user.php';

$command = isset( $argv[2] ) ? $argv[2] : '';
$mu_file = WPMU_PLUGIN_DIR . '/blueworx-test-app-passwords.php';
$logins  = array(
	'admin'  => 'apppw-admin',
	'editor' => 'apppw-editor',
);

foreach ( $logins as $login ) {
	$existing = get_user_by( 'login', $login );

	if ( $existing ) {
		wp_delete_user( $existing->ID );
	}
}

if ( 'delete' === $command ) {
	if ( file_exists( $mu_file ) ) {
		unlink( $mu_file );
	}

	delete_option( 'blueworx_show_application_passwords' );
	exit( 0 );
}

if ( 'create' !== $command ) {
	fwrite( STDERR, "Usage: app-password.php <wp-load.php> create|delete\n" );
	exit( 1 );
}

if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	mkdir( WPMU_PLUGIN_DIR, 0777, true );
}

file_put_contents( $mu_file, "<?php\n// Written by tests/fixtures/app-password.php; removed by its delete command.\nadd_filter( 'wp_is_application_passwords_available', '__return_true' );\n" );
update_option( 'blueworx_show_application_passwords', '1' );

foreach ( $logins as $role => $login ) {
	$user_id = wp_insert_user(
		array(
			'user_login' => $login,
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $login . '@example.invalid',
			'role'       => 'admin' === $role ? 'administrator' : 'editor',
		)
	);

	if ( is_wp_error( $user_id ) ) {
		fwrite( STDERR, $user_id->get_error_message() . "\n" );
		exit( 1 );
	}

	$created = WP_Application_Passwords::create_new_application_password( $user_id, array( 'name' => 'Playwright' ) );

	if ( is_wp_error( $created ) ) {
		fwrite( STDERR, $created->get_error_message() . "\n" );
		exit( 1 );
	}

	// Not escaped: this is a shell pipe read by execFileSync(), not a page.
	echo $role . ' ' . $login . ' ' . $created[0] . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

exit( 0 );
