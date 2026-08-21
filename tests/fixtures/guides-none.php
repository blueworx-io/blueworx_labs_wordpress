<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Installs and removes a must-use plugin that empties the guide list, so the
 * Guides screen can be seen in the state where nothing is switched on.
 *
 * Reaching that state for real means turning all 27 functions off and back on
 * again, which is slow and leaves the site one interrupted run away from a
 * mess. Filtering the list to empty puts the screen in exactly the state it
 * would be in, and touches nothing that has to be put back.
 *
 * Usage: php guides-none.php /absolute/path/to/wp-load.php <install|remove>
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$command = isset( $argv[2] ) ? $argv[2] : '';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// Same reasoning as the other fixtures: without this, Site Protection wp_die()s
// the CLI process as an anonymous visitor.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;

/**
 * Path of the must-use plugin this fixture writes.
 *
 * @return string Absolute path.
 */
function blueworx_guides_none_fixture_file() {
	return WPMU_PLUGIN_DIR . '/blueworx-test-no-guides.php';
}

switch ( $command ) {
	case 'install':
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			wp_mkdir_p( WPMU_PLUGIN_DIR );
		}

		file_put_contents(
			blueworx_guides_none_fixture_file(),
			"<?php\n"
			. "// Test fixture, installed and removed by tests/fixtures/guides-none.php.\n"
			. "add_filter( 'blueworx_guides', '__return_empty_array', 99 );\n"
		);
		echo 'installed';
		break;

	case 'remove':
		if ( is_file( blueworx_guides_none_fixture_file() ) ) {
			unlink( blueworx_guides_none_fixture_file() );
		}
		echo 'removed';
		break;

	default:
		fwrite( STDERR, "Usage: php guides-none.php /path/to/wp-load.php <install|remove>\n" );
		exit( 1 );
}
