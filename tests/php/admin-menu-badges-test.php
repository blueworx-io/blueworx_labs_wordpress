<?php
/**
 * The plugins badge counts plugins switched on for the whole network too.
 *
 * Run with: php tests/php/admin-menu-badges-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

require __DIR__ . '/stubs.php';

$GLOBALS['multisite']    = false;
$GLOBALS['site_options'] = array();
function is_multisite() {
	return $GLOBALS['multisite'];
}
function get_site_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['site_options'] ) ? $GLOBALS['site_options'][ $key ] : $default;
}
function get_plugins() {
	return array();
}
function wp_count_posts( $type ) {
	return (object) array( 'publish' => 0 );
}
function wp_count_attachments() {
	return array();
}
function get_post_types( $args, $output ) {
	return array();
}
function wp_count_comments() {
	return (object) array( 'moderated' => 0 );
}

require __DIR__ . '/../../includes/admin-menu-badges.php';

echo "A single site\n";
$GLOBALS['options']['active_plugins']               = array( 'a/a.php', 'b/b.php' );
$GLOBALS['site_options']['active_sitewide_plugins'] = array( 'c/c.php' => 1700000000 );
check( 'counts the site\'s own plugins', blueworx_count_active_plugins(), 2 );

echo "\nA site on a network\n";
$GLOBALS['multisite'] = true;
check( 'adds the plugins switched on for the whole network', blueworx_count_active_plugins(), 3 );

$GLOBALS['options']['active_plugins'] = array( 'a/a.php', 'c/c.php' );
check( 'counts a plugin on in both places once', blueworx_count_active_plugins(), 2 );

$GLOBALS['options']['active_plugins']               = array();
$GLOBALS['site_options']['active_sitewide_plugins'] = false;
check( 'copes with nothing switched on anywhere', blueworx_count_active_plugins(), 0 );

echo "\nThe badge\n";
$GLOBALS['options']['active_plugins']               = array( 'a/a.php' );
$GLOBALS['site_options']['active_sitewide_plugins'] = array(
	'c/c.php' => 1700000000,
	'd/d.php' => 1700000000,
);
$counts = blueworx_get_admin_menu_badge_counts();
check( 'shows the network-wide total on Plugins', isset( $counts['plugins.php'] ) ? $counts['plugins.php'] : null, 3 );

finish();
