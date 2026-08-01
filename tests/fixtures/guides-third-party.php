<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Installs and removes a must-use plugin that registers guides through the
 * public `blueworx_guides` / `blueworx_guide_tabs` filters, standing in for a
 * third-party plugin plugging into the Guides page.
 *
 * It registers three things the specs care about:
 *
 * - a tab of its own, plus a guide in it — the documented happy path
 * - a guide whose body contains a script tag, to prove the page strips it
 *   rather than trusting whatever another plugin hands over
 * - a guide naming a tab nobody registered, which must surface under "Other"
 *   rather than vanishing
 *
 * Usage: php guides-third-party.php /absolute/path/to/wp-load.php <install|remove>
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$command = isset( $argv[2] ) ? $argv[2] : '';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// Same reasoning as support-access-probe.php: without this, Site Protection
// wp_die()s the CLI process as an anonymous visitor.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;

/**
 * Path of the must-use plugin this fixture writes.
 *
 * @return string Absolute path.
 */
function blueworx_guides_fixture_file() {
	return WPMU_PLUGIN_DIR . '/blueworx-test-third-party-guides.php';
}

switch ( $command ) {
	case 'install':
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			wp_mkdir_p( WPMU_PLUGIN_DIR );
		}

		file_put_contents(
			blueworx_guides_fixture_file(),
			"<?php\n"
			. "// Test fixture, installed and removed by tests/fixtures/guides-third-party.php.\n"
			. "add_filter( 'blueworx_guide_tabs', function ( \$tabs ) {\n"
			. "\t\$tabs['acme'] = 'Acme Plugin';\n"
			. "\treturn \$tabs;\n"
			. "} );\n"
			. "add_filter( 'blueworx_guides', function ( \$guides ) {\n"
			. "\t\$guides[] = array(\n"
			. "\t\t'id'    => 'acme-shipping-zones',\n"
			. "\t\t'title' => 'Setting up shipping zones',\n"
			. "\t\t'tab'   => 'acme',\n"
			. "\t\t'body'  => '<p>Acme guide body.</p>',\n"
			. "\t);\n"
			. "\t\$guides[] = array(\n"
			. "\t\t'id'    => 'acme-unsafe',\n"
			. "\t\t'title' => 'Acme unsafe guide',\n"
			. "\t\t'tab'   => 'acme',\n"
			. "\t\t'body'  => '<p>Acme safe text.</p><script id=\"acme-xss\">window.acmeXss = true;</script>',\n"
			. "\t);\n"
			. "\t\$guides[] = array(\n"
			. "\t\t'id'    => 'acme-homeless',\n"
			. "\t\t'title' => 'Acme guide with no tab',\n"
			. "\t\t'tab'   => 'tab-that-was-never-registered',\n"
			. "\t\t'body'  => '<p>Acme homeless body.</p>',\n"
			. "\t);\n"
			. "\treturn \$guides;\n"
			. "} );\n"
		);
		echo 'installed';
		break;

	case 'remove':
		if ( is_file( blueworx_guides_fixture_file() ) ) {
			unlink( blueworx_guides_fixture_file() );
		}
		echo 'removed';
		break;

	default:
		fwrite( STDERR, 'Unknown command: ' . var_export( $command, true ) . "\n" );
		exit( 1 );
}
