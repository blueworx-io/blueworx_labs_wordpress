<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Forces the BlueWorx support access window into the past, simulating natural
 * 24-hour expiry rather than an operator clicking "Close support access".
 * There is no UI path to this, so tests/support-access.spec.js shells out to
 * this script against the local .wp-test harness to reach the option
 * directly.
 *
 * Usage: php force-support-access-expiry.php /absolute/path/to/wp-load.php
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

define( 'WP_USE_THEMES', false );
require $wp_load;

update_option( 'blueworx_support_access_until', time() - 3600 );

echo "ok\n";
