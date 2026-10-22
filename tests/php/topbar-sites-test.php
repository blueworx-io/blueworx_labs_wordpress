<?php
/**
 * The top bar's My Sites menu: who sees it, and where it goes.
 *
 * The BlueWorx top bar hides WordPress's own toolbar on desktop, and that
 * toolbar is where My Sites and Network Admin live. Without this menu there is
 * no way to move between sites on a multisite network.
 *
 * Run with: php tests/php/topbar-sites-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

require __DIR__ . '/stubs.php';

$GLOBALS['multisite']     = true;
$GLOBALS['super_admin']   = false;
$GLOBALS['network_admin'] = false;
$GLOBALS['viewing_site']  = 1;
$GLOBALS['user_blogs']    = array();

function is_multisite() {
	return $GLOBALS['multisite'];
}
function is_super_admin( $user_id = false ) {
	return $GLOBALS['super_admin'];
}
function is_network_admin() {
	return $GLOBALS['network_admin'];
}
function get_current_blog_id() {
	return $GLOBALS['viewing_site'];
}
function get_blogs_of_user( $user_id ) {
	return $GLOBALS['user_blogs'];
}
function get_admin_url( $blog_id = null, $path = '' ) {
	return 'https://site' . $blog_id . '.test/wp-admin/' . $path;
}
function network_admin_url( $path = '' ) {
	return 'https://site1.test/wp-admin/network/' . $path;
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

function blog( $id, $name, $domain = 'example.test', $path = '/' ) {
	return (object) array(
		'userblog_id' => $id,
		'blogname'    => $name,
		'domain'      => $domain,
		'path'        => $path,
	);
}

require __DIR__ . '/../../includes/admin-topbar-sites.php';

echo "Who sees the menu\n";
$GLOBALS['multisite']  = false;
$GLOBALS['user_blogs'] = array(
	1 => blog( 1, 'Main' ),
	2 => blog( 2, 'Shop' ),
);
check( 'nobody on a single site', blueworx_topbar_sites_links( 7 ), array() );

$GLOBALS['multisite']  = true;
$GLOBALS['user_blogs'] = array( 1 => blog( 1, 'Main' ) );
check( 'not someone with only this site', blueworx_topbar_sites_links( 7 ), array() );

echo "\nSomeone on two sites\n";
$GLOBALS['user_blogs']   = array(
	1 => blog( 1, 'Main' ),
	2 => blog( 2, 'Shop' ),
);
$GLOBALS['viewing_site'] = 2;
check(
	'each site, linking to its dashboard, with this one marked',
	blueworx_topbar_sites_links( 7 ),
	array(
		array(
			'label'   => 'Main',
			'url'     => 'https://site1.test/wp-admin/',
			'current' => false,
		),
		array(
			'label'   => 'Shop',
			'url'     => 'https://site2.test/wp-admin/',
			'current' => true,
		),
	)
);

echo "\nA super admin\n";
$GLOBALS['super_admin']  = true;
$GLOBALS['user_blogs']   = array( 1 => blog( 1, 'Main' ) );
$GLOBALS['viewing_site'] = 1;
check(
	'gets Network Admin first, even with one site',
	blueworx_topbar_sites_links( 1 ),
	array(
		array(
			'label'   => 'Network Admin',
			'url'     => 'https://site1.test/wp-admin/network/',
			'current' => false,
		),
		array(
			'label'   => 'Main',
			'url'     => 'https://site1.test/wp-admin/',
			'current' => true,
		),
	)
);

$GLOBALS['network_admin'] = true;
$links                    = blueworx_topbar_sites_links( 1 );
check( 'in the network admin, Network Admin is the one marked', $links[0]['current'], true );
check( 'and no site is', $links[1]['current'], false );

echo "\nA site with no name\n";
$GLOBALS['network_admin'] = false;
$GLOBALS['user_blogs']    = array( 3 => blog( 3, '', 'example.test', '/club/' ) );
$links                    = blueworx_topbar_sites_links( 1 );
check( 'is shown by its address', $links[1]['label'], 'example.test/club' );

finish();
