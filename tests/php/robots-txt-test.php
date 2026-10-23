<?php
/**
 * On a folder-style network, a subsite's robots.txt settings say they do nothing.
 *
 * Run with: php tests/php/robots-txt-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

require __DIR__ . '/stubs.php';

function blueworx_feature_enabled( $key ) {
	return true;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['filters'][ $hook ] = $callback;
}
function home_url( $path = '' ) {
	return 'https://example.test/two' . $path;
}
function get_home_url( $blog_id = null, $path = '' ) {
	return 'https://example.test' . $path;
}
function get_main_site_id() {
	return 1;
}

// The network shape, changed by each check below.
$GLOBALS['multisite'] = false;
$GLOBALS['subdomain'] = false;
$GLOBALS['main_site'] = true;
function is_multisite() {
	return $GLOBALS['multisite'];
}
function is_subdomain_install() {
	return $GLOBALS['subdomain'];
}
function is_main_site() {
	return $GLOBALS['main_site'];
}

/**
 * The design system notice, reduced to what it was asked to say.
 *
 * @param array $args Notice arguments.
 * @return string Tone and text.
 */
function blueworx_ds_notice( $args ) {
	return '[notice:' . $args['tone'] . '] ' . $args['text'];
}

require __DIR__ . '/../../includes/robots-txt.php';

$shapes = array(
	'a single site'                        => array( false, false, true, false ),
	'the main site of a folder network'    => array( true, false, true, false ),
	'a subsite of a folder network'        => array( true, false, false, true ),
	'a subsite of a subdomain network'     => array( true, true, false, false ),
	'the main site of a subdomain network' => array( true, true, true, false ),
);

echo "Whether the settings only work on the main site\n";
foreach ( $shapes as $label => $shape ) {
	list( $GLOBALS['multisite'], $GLOBALS['subdomain'], $GLOBALS['main_site'], $expected ) = $shape;
	check( $label, blueworx_robots_txt_only_on_main_site(), $expected );
}

echo "\nThe note on the settings\n";
list( $GLOBALS['multisite'], $GLOBALS['subdomain'], $GLOBALS['main_site'] ) = array( false, false, true );
check( 'is not shown on a single site', blueworx_robots_txt_main_site_notice(), '' );

list( $GLOBALS['multisite'], $GLOBALS['subdomain'], $GLOBALS['main_site'] ) = array( true, true, false );
check( 'is not shown on a subdomain subsite', blueworx_robots_txt_main_site_notice(), '' );

list( $GLOBALS['multisite'], $GLOBALS['subdomain'], $GLOBALS['main_site'] ) = array( true, false, false );
$notice = blueworx_robots_txt_main_site_notice();
check( 'is shown on a folder subsite', 0 === strpos( $notice, '[notice:info]' ), true );
check( 'says the settings only take effect on the main site', false !== strpos( $notice, 'only take effect on the main site' ), true );
check( 'names the one file search engines read', false !== strpos( $notice, 'https://example.test/robots.txt' ), true );

finish();
