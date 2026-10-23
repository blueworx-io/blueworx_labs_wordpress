<?php
/**
 * Functions a fresh site gets switched off until somebody turns them on.
 *
 * Run with: php tests/php/feature-defaults-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

require __DIR__ . '/stubs.php';

function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) );
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

require __DIR__ . '/../../includes/features.php';

echo "Off on a fresh site\n";
check( 'on-page translation (#238)', blueworx_feature_enabled( 'translate' ), false );

echo "\nOn once someone switches it on\n";
update_option( 'blueworx_feature_translate', '1' );
check( 'on-page translation', blueworx_feature_enabled( 'translate' ), true );

finish();
