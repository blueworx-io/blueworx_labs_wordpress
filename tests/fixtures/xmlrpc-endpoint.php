<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Opens the XML-RPC endpoint, and puts the site's own setting back afterwards.
 *
 * The plugin's `xmlrpc` function closes the endpoint outright and is on by
 * default, which masks the hole blueworx_readonly_block_xmlrpc() exists to
 * close. Site owners are told to turn that function off if they use Jetpack or
 * the mobile app, so the read-only guarantee has to hold with it off — which is
 * the state this fixture creates.
 *
 * Usage: php xmlrpc-endpoint.php /absolute/path/to/wp-load.php open|restore
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$command = isset( $argv[2] ) ? $argv[2] : '';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// See tests/fixtures/impostor-support-user.php for why the CLI context is
// declared before wp-load: Site Protection wp_die()s an anonymous request the
// moment WordPress finishes loading, and everything below would silently never
// run while the process still exited 0.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;

$backup_key = '_blueworx_xmlrpc_test_state_backup';

if ( 'restore' === $command ) {
	$backup = get_option( $backup_key );

	if ( is_array( $backup ) ) {
		if ( ! empty( $backup['existed'] ) ) {
			update_option( 'blueworx_feature_xmlrpc', $backup['value'] );
		} else {
			delete_option( 'blueworx_feature_xmlrpc' );
		}

		delete_option( $backup_key );
	}

	echo "restored\n";
	exit( 0 );
}

// Captured before anything below changes it, so "restore" puts back whatever
// the site actually had rather than assuming the default.
update_option(
	$backup_key,
	array(
		'existed' => ( false !== get_option( 'blueworx_feature_xmlrpc', false ) ),
		'value'   => get_option( 'blueworx_feature_xmlrpc', '1' ),
	)
);

update_option( 'blueworx_feature_xmlrpc', '0' );

echo "open\n";
