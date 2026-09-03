<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Pushes an external invitation's end date into the past, so a browser test can
 * check what an expired viewer meets without waiting thirty days.
 *
 * Usage: php force-external-expiry.php /absolute/path/to/wp-load.php <user-login>
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$login   = isset( $argv[2] ) ? $argv[2] : '';

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

$user = get_user_by( 'login', $login );

if ( ! $user ) {
	fwrite( STDERR, 'No such user: ' . $login . "\n" );
	exit( 1 );
}

update_user_meta( $user->ID, '_blueworx_external_expires_at', time() - 60 );

echo "expired\n";
