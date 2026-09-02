<?php
/**
 * Which admin screens count as a plugin's own app.
 *
 * The answer decides whether a screen keeps the BlueWorx top bar, so it is worth
 * pinning down away from a browser: SureCart's app screens qualify, and the
 * ordinary WordPress screens its content lives on must not.
 *
 * Run with: php tests/php/app-screens-test.php
 *
 * @package BlueWorxLabs
 */

// The shared WordPress stand-ins. Kept apart from the docblock above, which
// phpcs otherwise reads as this statement's rather than the file's.
require __DIR__ . '/stubs.php';

// This script stands in for WordPress rather than being loaded into it, so its
// stubs have to carry core's names.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
}

require __DIR__ . '/../../includes/admin-app-screens.php';

echo "SureCart's own app screens get the window to themselves\n";

check( 'the dashboard', blueworx_is_plugin_app_screen( 'sc-dashboard' ), true );
check( 'settings', blueworx_is_plugin_app_screen( 'sc-settings' ), true );
check( 'and one we have never seen', blueworx_is_plugin_app_screen( 'sc-whatever-comes-next' ), true );

echo "\nWordPress's own screens keep the BlueWorx chrome\n";

check( 'the plugin list', blueworx_is_plugin_app_screen( '' ), false );
check( 'this plugin', blueworx_is_plugin_app_screen( 'blueworx-labs-wordpress' ), false );
check( 'another plugin whose slug merely contains sc-', blueworx_is_plugin_app_screen( 'my-sc-tool' ), false );
check( 'and one that only starts with the letters', blueworx_is_plugin_app_screen( 'scheduling' ), false );

echo "\nAnd the body class follows the same answer\n";

$_GET['page'] = 'sc-dashboard';
check( 'an app screen is marked', blueworx_admin_app_screen_body_class( 'wp-admin' ), 'wp-admin bw-app-screen' );

$_GET['page'] = 'blueworx-labs-wordpress';
check( 'ours is not', blueworx_admin_app_screen_body_class( 'wp-admin' ), 'wp-admin' );

unset( $_GET['page'] );
check( 'nor is a screen with no page at all', blueworx_admin_app_screen_body_class( 'wp-admin' ), 'wp-admin' );

finish();
