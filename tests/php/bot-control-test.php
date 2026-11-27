<?php
/**
 * Probe paths are recognised, ordinary pages are not, a visitor is paused after
 * too many missing pages, and robots.txt gets the crawler rules once.
 *
 * Run with: php tests/php/bot-control-test.php
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
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooked'][ $hook ][]                   = $callback;
	$GLOBALS['hook_priority'][ $hook ][ $callback ] = $priority;
}
// Whether WordPress has reached init; most checks below stand after it.
$GLOBALS['did_init'] = 1;
function did_action( $hook ) {
	return 'init' === $hook ? $GLOBALS['did_init'] : 0;
}
$GLOBALS['is_admin'] = false;
function is_admin() {
	return $GLOBALS['is_admin'];
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function wp_load_alloptions() {
	return $GLOBALS['options'];
}
// A store's page settings: key => page id, and id => address.
$GLOBALS['wc_pages']   = array();
$GLOBALS['permalinks'] = array();
$GLOBALS['unpublish']  = array();
function wc_get_page_id( $page ) {
	return isset( $GLOBALS['wc_pages'][ $page ] ) ? $GLOBALS['wc_pages'][ $page ] : -1;
}
function get_permalink( $id ) {
	return isset( $GLOBALS['permalinks'][ $id ] ) ? $GLOBALS['permalinks'][ $id ] : false;
}
function get_post_status( $id ) {
	return in_array( $id, $GLOBALS['unpublish'], true ) ? 'trash' : 'publish';
}
function wp_unslash( $value ) {
	return $value;
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
}
function trailingslashit( $value ) {
	return rtrim( $value, '/\\' ) . '/';
}
$GLOBALS['ext_cache'] = false;
$GLOBALS['cache']     = array();
function wp_using_ext_object_cache() {
	return $GLOBALS['ext_cache'];
}
function wp_cache_add( $key, $value, $group = '', $expire = 0 ) {
	if ( ! isset( $GLOBALS['cache'][ $group . $key ] ) ) {
		$GLOBALS['cache'][ $group . $key ] = $value;
	}
	return true;
}
function wp_cache_incr( $key, $offset = 1, $group = '' ) {
	if ( ! isset( $GLOBALS['cache'][ $group . $key ] ) ) {
		return false;
	}
	$GLOBALS['cache'][ $group . $key ] += $offset;
	return $GLOBALS['cache'][ $group . $key ];
}
function blueworx_store_page_url( $key ) {
	return isset( $GLOBALS['store_urls'][ $key ] ) ? $GLOBALS['store_urls'][ $key ] : '';
}
$GLOBALS['store_urls'] = array();
function absint( $value ) {
	return abs( (int) $value );
}
function home_url( $path = '' ) {
	return 'https://example.test' . $GLOBALS['home_path'] . $path;
}

$GLOBALS['home_path']   = '';
$GLOBALS['site_path']   = null;
$GLOBALS['rest_prefix'] = 'wp-json';
function site_url( $path = '' ) {
	return 'https://example.test' . ( null === $GLOBALS['site_path'] ? $GLOBALS['home_path'] : $GLOBALS['site_path'] ) . $path;
}
function rest_get_url_prefix() {
	return $GLOBALS['rest_prefix'];
}

// The real single sign-on routing, so the two can never disagree about it.
require __DIR__ . '/../../includes/sso/sso.php';
require __DIR__ . '/../../includes/sso/flow.php';
require __DIR__ . '/../../includes/bot-control.php';

echo "Probe paths are turned away\n";
$probes = array(
	'/.env',
	'/.env.local',
	'/.git/config',
	'/.aws/credentials',
	'/.htaccess',
	'/.DS_Store',
	'/%2eenv',
	'/%252eenv',
	'/wp-config.php',
	'/wp-config.php.bak',
	'/wp-config.old',
	'/wp-config.php~',
	'/backup.zip',
	'/Backup.ZIP',
	'/site-backup-2024.tar.gz',
	'/www.tar.gz',
	'/db.sql',
	'/wp-content/uploads/dump.sql.gz',
	'/phpinfo.php',
	'/info.php',
	'/phpmyadmin/index.php',
	'/phpMyAdmin/',
	'/adminer.php',
	'/composer.json',
	'/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php',
	'/cgi-bin/test.cgi',
	'/wp-content/debug.log',
	'/wp-content/uploads/2024/01/shell.php',
	'/blog/../../etc/passwd',
	'/id_rsa',
	'/shop/.env',
	'/sub/backup.zip',
	'/x%00.jpg',
	'/aws-credentials.txt',
	'/credentials.txt',
	'/_next/static/chunks/main.js',
	'/_next/data/abc/index.json',
	'/_nuxt/entry.js',
);

foreach ( $probes as $path ) {
	check( $path, blueworx_probe_path_matches( $path ), true );
}

echo "\nNames that could be a real page wait for WordPress\n";
foreach ( array( '/docs/phpunit/', '/phpmyadmin/', '/adminer', '/server-status', '/katalog/', '/katalog/product-123', '/_next/data/abc/', '/_next/image/', '/_nuxt/' ) as $path ) {
	check( 'not turned away at once: ' . $path, blueworx_probe_path_matches( $path, false ), false );
	check( 'but a probe once WordPress finds no page: ' . $path, blueworx_probe_path_matches( $path ), true );
}
foreach ( array( '/wp-config.php.bak', '/backup.zip', '/phpinfo.php', '/composer.json', '/db.sql', '/wp-content/uploads/x.php', '/dot/.git/config', '/aws-credentials.txt', '/_next/static/chunks/main.js' ) as $path ) {
	check( 'turned away at once: ' . $path, blueworx_probe_path_matches( $path, false ), true );
}

echo "\nA file name that is a page address on this site waits for WordPress\n";
update_option( 'permalink_structure', '/%postname%.php' );
foreach ( array( '/info.php', '/test.php', '/phpinfo.php' ) as $path ) {
	check( 'a .php permalink waits: ' . $path, blueworx_probe_path_matches( $path, false ), false );
	check( 'and is a probe once WordPress finds no page: ' . $path, blueworx_probe_path_matches( $path ), true );
}
foreach ( array( '/.env', '/backup.zip', '/composer.json', '/wp-content/uploads/x.php', '/.git/index.php' ) as $path ) {
	check( 'the rest are still turned away at once: ' . $path, blueworx_probe_path_matches( $path, false ), true );
}
update_option( 'permalink_structure', '/%postname%/' );
update_option(
	'rewrite_rules',
	array(
		'([^/]+)\.php/?$'                                  => 'index.php?pagename=$matches[1]',
		'.*wp-(atom|rdf|rss|rss2|feed|commentsrss2)\.php$' => 'index.php?feed=old',
	)
);
check( "a plugin's own .php page rule counts too", blueworx_probe_path_matches( '/info.php', false ), false );
$GLOBALS['home_path'] = '/club';
check( 'on a site in a folder as well', blueworx_probe_path_matches( '/club/info.php', false ), false );
$GLOBALS['home_path'] = '';
update_option( 'rewrite_rules', array( '.*wp-(atom|rdf|rss|rss2|feed|commentsrss2)\.php$' => 'index.php?feed=old' ) );
check( "WordPress's own old feed rule does not", blueworx_probe_path_matches( '/info.php', false ), true );
update_option( 'rewrite_rules', '' );

echo "\nOrdinary paths are left alone\n";
$normal = array(
	'/',
	'/about/',
	'/shop/',
	'/cart/',
	'/checkout/',
	'/checkout/?sc-checkout=1',
	'/my-account/',
	'/product/blue-shirt/',
	'/backup-your-site-guide/',
	'/blog/how-to-make-a-backup/',
	'/info/',
	'/products/_next-day-delivery/',
	'/test/',
	'/config/',
	'/wp-login.php',
	'/wp-admin/admin-ajax.php',
	'/wp-json/wp/v2/posts',
	'/wp-config-guide/',
	'/wp-config-guide',
	'/wp-config-tips.html',
	'/wp-sitemap.xml',
	'/robots.txt',
	'/.well-known/acme-challenge/abc',
	'/.well-known/security.txt',
	'/wp-content/uploads/2024/01/photo.jpg',
	'/wp-content/uploads/2024/01/report.pdf',
	'/wp-content/themes/x/style.css',
	'/db/',
	'/archive/2024/',
	'',
);

foreach ( $normal as $path ) {
	check( 'not a probe: "' . $path . '"', blueworx_probe_path_matches( $path ), false );
}

echo "\nRequests the limit never touches\n";
check( 'the admin area', blueworx_bot_path_is_exempt( '/wp-admin/edit.php' ), true );
check( 'REST by path', blueworx_bot_path_is_exempt( '/wp-json/wp/v2/posts' ), true );
check( 'REST by query', blueworx_bot_path_is_exempt( '/', 'rest_route=/surecart/v1/webhooks' ), true );
check( 'an empty rest_route is an ordinary request', blueworx_bot_path_is_exempt( '/', 'rest_route=' ), false );
check( 'REST by query with other arguments', blueworx_bot_path_is_exempt( '/index.php', 'a=1&rest_route=%2Fwp%2Fv2%2Fposts' ), true );
check( 'REST after index.php', blueworx_bot_path_is_exempt( '/index.php/wp-json/wp/v2/posts' ), true );
$GLOBALS['home_path'] = '/club';
check( 'the admin area of a site in a folder', blueworx_bot_path_is_exempt( '/club/wp-admin/edit.php' ), true );
check( 'REST of a site in a folder', blueworx_bot_path_is_exempt( '/club/wp-json/wp/v2/posts' ), true );
$GLOBALS['home_path'] = '';

$GLOBALS['is_admin'] = true;
check( 'the admin area of WordPress in its own folder', blueworx_bot_path_is_exempt( '/wordpress/wp-admin/index.php' ), true );
$GLOBALS['is_admin'] = false;
check( 'a page that merely has wp-admin in it is not the admin area', blueworx_bot_path_is_exempt( '/wordpress/wp-admin/nope' ), false );
$GLOBALS['site_path'] = '/wp';
check( 'the admin area of WordPress in its own folder, found by its address', blueworx_bot_path_is_exempt( '/wp/wp-admin/edit.php' ), true );
check( 'its sign-in too', blueworx_bot_path_is_exempt( '/wp/wp-login.php' ), true );
check( 'a made-up wp-admin elsewhere is still an ordinary request', blueworx_bot_path_is_exempt( '/other/wp-admin/nope' ), false );
$GLOBALS['site_path']   = null;
$GLOBALS['rest_prefix'] = 'api';
check( 'a REST address the site has renamed', blueworx_bot_path_is_exempt( '/api/wp/v2/posts' ), true );
$GLOBALS['rest_prefix'] = 'wp-json';
$GLOBALS['rest_prefix'] = 'api/rest';
check( 'a REST address of several parts', blueworx_bot_path_is_exempt( '/api/rest/wp/v2/posts' ), true );
check( 'but not just its first part', blueworx_bot_path_is_exempt( '/api/other' ), false );
$GLOBALS['rest_prefix'] = 'wp-json';
update_option( 'rewrite_rules', array( '^custom-api/?$' => 'index.php?rest_route=/' ) );
check( 'a REST address a theme sets later is known from the saved rules', blueworx_bot_path_is_exempt( '/custom-api/wp/v2/posts' ), true );
update_option( 'rewrite_rules', array() );

echo "\nAn IPv4 visitor reported as IPv6\n";
check( 'is the plain IPv4 address', blueworx_normalise_ip( '::ffff:93.184.216.5' ), '93.184.216.5' );
check( 'and so is the long way of writing it', blueworx_normalise_ip( '0:0:0:0:0:ffff:5db8:d805' ), '93.184.216.5' );
check( 'a real IPv6 address is untouched', blueworx_normalise_ip( '2a00:1450::1' ), '2a00:1450::1' );
check( 'and so is something that is not an address', blueworx_normalise_ip( 'nope' ), 'nope' );
check( 'a mapped address is inside the machine\'s own network', blueworx_ip_in_range( '::ffff:127.0.0.1', '127.0.0.0/8' ), true );
$_SERVER = array( 'REMOTE_ADDR' => '::ffff:93.184.216.5' );
check( 'is one visitor, whichever way the server writes it', blueworx_request_ip(), '93.184.216.5' );
check( 'a proxy network written the mapped way keeps its size', blueworx_ip_in_range( '10.0.0.200', '::ffff:10.0.0.0/120' ), true );
check( 'and does not grow past it', blueworx_ip_in_range( '10.0.1.1', '::ffff:10.0.0.0/120' ), false );
$_SERVER = array(
	'REMOTE_ADDR'          => '127.0.0.1',
	'HTTP_X_FORWARDED_FOR' => '::ffff:93.184.216.5',
);
check( 'a trusted proxy naming the visitor the mapped way names nobody else', blueworx_request_names_others( '93.184.216.5' ), false );
$_SERVER = array();

echo "\nOnly the sign-ins single sign-on really handles are left alone\n";
$sso_options = array(
	'blueworx_sso_issuer'        => 'https://idp.test',
	'blueworx_sso_client_id'     => 'client-abc',
	'blueworx_sso_client_secret' => 'shhh',
);
foreach ( $sso_options as $key => $value ) {
	update_option( $key, $value );
}
$sso_request = function ( $args, $path = '/no-such-page/' ) {
	$_GET   = $args;
	$exempt = blueworx_bot_path_is_exempt( $path, http_build_query( $args ) );
	$_GET   = array();
	return $exempt;
};
foreach ( array( 'login', 'register', 'callback' ) as $action ) {
	check( 'a real one is left alone: ' . $action, $sso_request( array( 'blueworx_sso' => $action ) ), true );
}
check( 'an action nobody handles is an ordinary request', $sso_request( array( 'blueworx_sso' => 'anything' ) ), false );
check( 'and so is an empty one', $sso_request( array( 'blueworx_sso' => '' ) ), false );
set_transient( blueworx_sso_attempt_key( 'issued' ), array( 'nonce' => 'n' ) );
check( "the provider's return with a state this site issued is left alone", $sso_request( array( 'state' => 'issued' ) ), true );
check( 'any other state is not', $sso_request( array( 'state' => 'made-up' ) ), false );
$GLOBALS['filters']['blueworx_sso_is_login_request'] = function ( $is_login ) {
	return $is_login || ! empty( $_GET['old_login'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A test's own value.
};
check( 'an old sign-in address the site has claimed is left alone', $sso_request( array( 'old_login' => '1' ) ), true );
$GLOBALS['asked'] = 0;
foreach ( array( 'blueworx_sso_is_login_request', 'blueworx_sso_is_register_request' ) as $filter ) {
	$GLOBALS['filters'][ $filter ] = function ( $claimed ) {
		++$GLOBALS['asked'];
		return $claimed;
	};
}
$GLOBALS['did_init'] = 0;
$sso_request( array( 'old_login' => '1' ) );
$sso_request( array() );
check( "a site's own sign-in rules are never asked before WordPress is ready", $GLOBALS['asked'], 0 );
$GLOBALS['did_init'] = 1;
$sso_request( array( 'old_login' => '1' ) );
check( 'and are asked once it is', $GLOBALS['asked'], 2 );
unset( $GLOBALS['filters']['blueworx_sso_is_login_request'], $GLOBALS['filters']['blueworx_sso_is_register_request'] );
$GLOBALS['hook_priority'] = array();
$GLOBALS['hooked']        = array();
update_option( 'blueworx_sso_client_secret', '' );
blueworx_bot_control_hooks();
update_option( 'blueworx_sso_client_secret', 'shhh' );
// Single sign-on was not set up when the hooks were added; another plugin has
// supplied its settings by the time they run.
$GLOBALS['did_init'] = 0;

$_SERVER['REQUEST_URI'] = '/.sso/start';
check( 'probes wait for every plugin to load', $GLOBALS['hook_priority']['plugins_loaded']['blueworx_bot_control_early'] ?? 0, PHP_INT_MAX );
blueworx_bot_control_early();
check( 'with single sign-on on, probes wait until it has taken its own requests', $GLOBALS['hook_priority']['init']['blueworx_bot_control_early'] ?? 0, 2 );
$GLOBALS['did_init'] = 1;
foreach ( array_keys( $sso_options ) as $key ) {
	update_option( $key, '' );
}
check( 'with single sign-on off, nothing is', $sso_request( array( 'blueworx_sso' => 'login' ) ), false );

$GLOBALS['hook_priority'] = array();
$GLOBALS['hooked']        = array();
blueworx_bot_control_hooks();
$GLOBALS['did_init'] = 0;
blueworx_bot_control_early();
check( 'and probes are not put off', isset( $GLOBALS['hook_priority']['init'] ), false );
$GLOBALS['did_init'] = 1;
unset( $_SERVER['REQUEST_URI'] );

echo "\nStatus and the missing-image exception\n";
check( 'the answer defaults to 404', blueworx_probe_status(), 404 );
update_option( 'blueworx_probe_status', '403' );
check( 'it can be 403', blueworx_probe_status(), 403 );
update_option( 'blueworx_probe_status', 'nonsense' );
check( 'anything else falls back to 404', blueworx_probe_status(), 404 );
check( 'a missing image is a static asset', blueworx_path_is_static_asset( '/wp-content/uploads/a.JPG' ), true );
check( 'a missing page is not', blueworx_path_is_static_asset( '/no-such-page/' ), false );
foreach ( array( '/assets/app.mjs', '/site.webmanifest', '/manifest.json', '/a/b.wasm', '/index.php/x.cjs' ) as $path ) {
	check( 'a missing script or manifest is an asset: ' . $path, blueworx_path_is_static_asset( $path ), true );
}

foreach ( array( '/files/prices.csv', '/files/pack.zip', '/files/form.docx', '/files/list.XLSX', '/files/deck.pptx', '/files/site.tar.gz', '/files/event.ics', '/files/notes.txt' ) as $path ) {
	check( 'a missing download is a file, not a page: ' . $path, blueworx_path_is_static_asset( $path ), true );
}
foreach ( array( '/blog/zip/', '/how-to-open-a-csv/', '/old-page.html', '/old-page.php' ) as $path ) {
	check( 'a missing page still counts: ' . $path, blueworx_path_is_static_asset( $path ), false );
}
check( 'a backup archive is still a probe', blueworx_probe_path_matches( '/backup.zip' ), true );

echo "\nVisitor address\n";
$server_backup = $_SERVER;
$_SERVER       = array( 'REMOTE_ADDR' => '93.184.216.5' );
check( 'a public address is used as it is', blueworx_visitor_ip(), '93.184.216.5' );
$_SERVER = array(
	'REMOTE_ADDR'          => '93.184.216.5',
	'HTTP_X_FORWARDED_FOR' => '93.184.216.9',
);
check( 'a header from a public address is not trusted', blueworx_visitor_ip(), '93.184.216.5' );
$_SERVER = array(
	'REMOTE_ADDR'          => '10.0.0.2',
	'HTTP_X_FORWARDED_FOR' => '93.184.216.9',
);
check( 'a private proxy nobody has vouched for is not believed', blueworx_visitor_ip(), '' );
$_SERVER = array(
	'REMOTE_ADDR'          => '127.0.0.1',
	'HTTP_X_FORWARDED_FOR' => '93.184.216.9',
);
check( 'a proxy on the same machine is believed', blueworx_visitor_ip(), '93.184.216.9' );
$GLOBALS['filters']['blueworx_trusted_proxies'] = function () {
	return array( '10.0.0.0/8' );
};
$_SERVER                                        = array(
	'REMOTE_ADDR'          => '10.0.0.2',
	'HTTP_X_FORWARDED_FOR' => '10.0.0.7, 93.184.216.9',
);
check( 'behind a private balancer, the forwarded address is used', blueworx_visitor_ip(), '93.184.216.9' );
$_SERVER = array(
	'REMOTE_ADDR'          => '10.0.0.2',
	'HTTP_X_FORWARDED_FOR' => '8.8.8.8, 93.184.216.9',
);
check( 'a visitor cannot choose their address by writing a header of their own', blueworx_visitor_ip(), '93.184.216.9' );
$_SERVER = array(
	'REMOTE_ADDR'          => '10.0.0.2',
	'HTTP_X_FORWARDED_FOR' => '93.184.216.9, 172.67.1.1',
);
check( 'a Cloudflare address on the end of the list is skipped', blueworx_visitor_ip(), '93.184.216.9' );
$_SERVER = array(
	'REMOTE_ADDR'           => '93.184.216.5',
	'HTTP_CF_CONNECTING_IP' => '93.184.216.20',
);
check( 'a Cloudflare header from an address that is not Cloudflare is ignored', blueworx_visitor_ip(), '93.184.216.5' );
$_SERVER = array(
	'REMOTE_ADDR'           => '10.0.0.2',
	'HTTP_CF_CONNECTING_IP' => '93.184.216.20',
);
check( 'a Cloudflare header from a private address is ignored too', blueworx_visitor_ip(), '' );
$_SERVER = array(
	'REMOTE_ADDR'           => '127.0.0.1',
	'HTTP_CF_CONNECTING_IP' => '93.184.216.20',
);
check( 'and so is one from the machine itself', blueworx_visitor_ip(), '' );
unset( $GLOBALS['filters']['blueworx_trusted_proxies'] );
check( 'IPv6 ranges are understood', blueworx_ip_in_range( '2606:4700::1', '2606:4700::/32' ), true );
check( 'and an address outside them is not inside', blueworx_ip_in_range( '2607:4700::1', '2606:4700::/32' ), false );
check( 'a range that stops mid-byte is honoured', blueworx_ip_in_range( '173.245.63.255', '173.245.48.0/20' ), true );
check( 'and the next address is outside', blueworx_ip_in_range( '173.245.64.0', '173.245.48.0/20' ), false );
$_SERVER = array(
	'REMOTE_ADDR'           => '172.67.1.1',
	'HTTP_CF_CONNECTING_IP' => '93.184.216.20',
);
check( 'behind Cloudflare, its header is used', blueworx_visitor_ip(), '93.184.216.20' );
$_SERVER = array( 'REMOTE_ADDR' => '127.0.0.1' );
check( 'with no usable address nobody is counted', blueworx_visitor_ip(), '' );
$_SERVER = array(
	'REMOTE_ADDR'          => '127.0.0.1',
	'HTTP_X_FORWARDED_FOR' => '93.184.216.9, 10.0.0.5',
);
check( 'the first address that is not trusted ends the search, even a private one', blueworx_visitor_ip(), '' );
$_SERVER['HTTP_X_FORWARDED_FOR'] = '93.184.216.9, unknown';
check( 'and one that cannot be read', blueworx_visitor_ip(), '' );
$_SERVER['HTTP_X_FORWARDED_FOR'] = '93.184.216.9, 127.0.0.2';
check( 'a known proxy on the way is still skipped', blueworx_visitor_ip(), '93.184.216.9' );

echo "\nAn address that may be shared is never counted or paused\n";
$limited = function ( $server ) {
	$_SERVER = $server;
	return blueworx_limited_visitor_ip();
};
$direct  = array( 'REMOTE_ADDR' => '93.184.216.5' );
$local   = array(
	'REMOTE_ADDR'          => '127.0.0.1',
	'HTTP_X_FORWARDED_FOR' => '93.184.216.9',
);
$cloud   = array(
	'REMOTE_ADDR'           => '172.67.1.1',
	'HTTP_CF_CONNECTING_IP' => '93.184.216.20',
);
check( 'a visitor who connects directly is counted', $limited( $direct ), '93.184.216.5' );
$ordinary = array(
	'SERVER_ADDR'           => '93.184.216.1',
	'HTTP_HOST'             => 'example.test',
	'HTTP_USER_AGENT'       => 'Mozilla',
	'HTTP_ACCEPT'           => '*/*',
	'HTTP_REFERER'          => 'https://example.test/',
	'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
	'HTTP_CACHE_CONTROL'    => 'no-cache',
	'HTTP_X_FORWARDED_FOR'  => '',
);
check( 'so is one who sends only ordinary headers', $limited( $direct + $ordinary ), '93.184.216.5' );
$markers = array(
	'HTTP_X_FORWARDED_FOR'          => '93.184.216.9',
	'HTTP_X_FORWARDED_PROTO'        => 'https',
	'HTTP_X_FORWARDED_HOST'         => 'example.test',
	'HTTP_FORWARDED'                => 'for=93.184.216.9',
	'HTTP_VIA'                      => '1.1 varnish',
	'HTTP_X_REAL_IP'                => '93.184.216.9',
	'HTTP_CLIENT_IP'                => '93.184.216.9',
	'HTTP_TRUE_CLIENT_IP'           => '93.184.216.9',
	'HTTP_X_CLUSTER_CLIENT_IP'      => '93.184.216.9',
	'HTTP_CF_CONNECTING_IP'         => '93.184.216.9',
	'HTTP_CF_RAY'                   => 'abc',
	'HTTP_FASTLY_CLIENT_IP'         => '93.184.216.9',
	'HTTP_X_SUCURI_CLIENTIP'        => '93.184.216.9',
	'HTTP_INCAP_CLIENT_IP'          => '93.184.216.9',
	'HTTP_CDN_LOOP'                 => 'some-cdn',
	'HTTP_X_AMZ_CF_ID'              => 'abc',
	'HTTP_X_AMZN_TRACE_ID'          => 'Root=1',
	'HTTP_X_CLOUD_TRACE_CONTEXT'    => 'abc',
	'HTTP_X_AZURE_SOCKETIP'         => '93.184.216.9',
	'HTTP_X_ENVOY_EXTERNAL_ADDRESS' => '93.184.216.9',
	'HTTP_X_VARNISH'                => '123',
);
foreach ( $markers as $header => $value ) {
	check( 'nobody is counted behind a proxy nobody has vouched for: ' . $header, $limited( $direct + array( $header => $value ) ), '' );
}
check( 'the machine asking itself is not a visitor', $limited( $direct + array( 'SERVER_ADDR' => '93.184.216.5' ) ), '' );
check( 'nor is an address a carrier shares', $limited( array( 'REMOTE_ADDR' => '100.64.3.4' ) ), '' );
$usual = array(
	'HTTP_X_FORWARDED_FOR'   => '93.184.216.20',
	'HTTP_X_FORWARDED_PROTO' => 'https',
	'HTTP_CDN_LOOP'          => 'cloudflare; loops=1',
	'HTTP_CF_RAY'            => 'abc',
	'HTTP_CF_IPCOUNTRY'      => 'ZA',
	'HTTP_CF_VISITOR'        => '{"scheme":"https"}',
);
check( 'Cloudflare says who the visitor is', $limited( $cloud + $usual ), '93.184.216.20' );
$six = array(
	'REMOTE_ADDR'           => '172.67.1.1',
	'HTTP_CF_CONNECTING_IP' => '2a00:1450::1',
	'HTTP_X_FORWARDED_FOR'  => '2a00:1450:0:0::1',
);
check( 'an IPv6 address written another way is the same visitor', $limited( $six ), '2a00:1450::1' );
check( 'another proxy in front of Cloudflare hides the visitor', $limited( $cloud + array( 'HTTP_X_FORWARDED_FOR' => '8.8.8.8, 93.184.216.20' ) ), '' );
$worker = array(
	'REMOTE_ADDR'           => '172.67.1.1',
	'HTTP_CF_CONNECTING_IP' => '2a06:98c0:3600::103',
);
check( "Cloudflare's own address is never the visitor", $limited( $worker ), '' );
$pseudo = array(
	'REMOTE_ADDR'             => '172.67.1.1',
	'HTTP_CF_CONNECTING_IP'   => '240.16.0.1',
	'HTTP_X_FORWARDED_FOR'    => '240.16.0.1',
	'HTTP_CF_CONNECTING_IPV6' => '2a00:1450::1',
);
check( 'a stand-in IPv4 address Cloudflare made up is never counted', $limited( $pseudo ), '' );
$same = array(
	'HTTP_X_REAL_IP'         => '93.184.216.9',
	'HTTP_X_FORWARDED_PROTO' => 'https',
	'HTTP_FORWARDED'         => 'for=93.184.216.9;proto=https',
);
check( 'a proxy on the same machine says who the visitor is', $limited( $local + $same ), '93.184.216.9' );
check( 'an unknown proxy in front of that one hides the visitor', $limited( array( 'HTTP_X_FORWARDED_FOR' => '8.8.8.8, 93.184.216.9' ) + $local ), '' );
check( 'even when only another header shows it', $limited( $local + array( 'HTTP_X_SUCURI_CLIENTIP' => '8.8.8.8' ) ), '' );
check( 'or only a Via header', $limited( $local + array( 'HTTP_VIA' => '1.1 some-cdn' ) ), '' );
check( 'or a Forwarded header', $limited( $local + array( 'HTTP_FORWARDED' => 'for=8.8.8.8;proto=https' ) ), '' );
check( 'or one that cannot be read', $limited( $local + array( 'HTTP_FORWARDED' => 'for=_hidden' ) ), '' );
$GLOBALS['filters']['blueworx_trusted_proxies'] = function () {
	return array( '93.184.216.5' );
};
check( 'a proxy the host has named is believed', $limited( $direct + array( 'HTTP_X_FORWARDED_FOR' => '93.184.216.9' ) ), '93.184.216.9' );
unset( $GLOBALS['filters']['blueworx_trusted_proxies'] );
$GLOBALS['filters']['blueworx_visitor_ip'] = function ( $ip ) {
	return empty( $_SERVER['HTTP_X_SUCURI_CLIENTIP'] ) ? $ip : $_SERVER['HTTP_X_SUCURI_CLIENTIP']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- A test's own value.
};
check( 'a host that says who the visitor is is believed', $limited( $direct + array( 'HTTP_X_SUCURI_CLIENTIP' => '93.184.216.9' ) ), '93.184.216.9' );
check( 'but not when it only hands the same address back', $limited( $direct + array( 'HTTP_X_FORWARDED_FOR' => '93.184.216.9' ) ), '' );
check( 'and never with the address of a proxy', $limited( $direct + array( 'HTTP_X_SUCURI_CLIENTIP' => '172.67.1.1' ) ), '' );
unset( $GLOBALS['filters']['blueworx_visitor_ip'] );
$GLOBALS['filters']['blueworx_visitor_address_is_shared'] = function () {
	return false;
};
check( 'a host that has put the real address back can say so', $limited( $direct + array( 'HTTP_X_FORWARDED_FOR' => '93.184.216.5' ) ), '93.184.216.5' );
unset( $GLOBALS['filters']['blueworx_visitor_address_is_shared'] );
$_SERVER = $server_backup;

echo "\nCounting and pausing use the same address\n";
$hooked_on = function ( $hook ) {
	return isset( $GLOBALS['hooked'][ $hook ] ) ? $GLOBALS['hooked'][ $hook ] : array();
};
check( 'pauses are enforced once the theme has loaded', in_array( 'blueworx_missing_page_enforce_pause', $hooked_on( 'wp_loaded' ), true ), true );
check( 'and not before it has had its say', in_array( 'blueworx_missing_page_enforce_pause', $hooked_on( 'plugins_loaded' ), true ), false );
check( 'probes are still turned away as early as before', in_array( 'blueworx_bot_control_early', $hooked_on( 'plugins_loaded' ), true ), true );

echo "\nNothing is looked up while nobody is paused\n";
$GLOBALS['transient_reads'] = 0;
check( 'a visitor is not paused', blueworx_missing_page_pause_left( '93.184.216.200' ), 0 );
check( 'and nothing was looked up to find out', $GLOBALS['transient_reads'], 0 );

echo "\nA visitor is paused after too many missing pages\n";
update_option( 'blueworx_missing_page_limit', 5 );
update_option( 'blueworx_missing_page_pause', 15 );
for ( $i = 1; $i <= 4; $i++ ) {
	blueworx_missing_page_record( '93.184.216.9' );
}
check( 'four misses of five allowed: not paused', blueworx_missing_page_pause_left( '93.184.216.9' ), 0 );
blueworx_missing_page_record( '93.184.216.9' );
$left = blueworx_missing_page_pause_left( '93.184.216.9' );
check( 'the fifth pauses them', $left > 14 * 60 && $left <= 15 * 60, true );
check( 'another visitor is not affected', blueworx_missing_page_pause_left( '93.184.216.10' ), 0 );
update_option( 'blueworx_missing_page_paused_until', time() - 1 );
$GLOBALS['transient_reads'] = 0;
check( 'once every pause is over nobody is paused', blueworx_missing_page_pause_left( '93.184.216.9' ), 0 );
check( 'and nothing is looked up again', $GLOBALS['transient_reads'], 0 );
update_option( 'blueworx_missing_page_paused_until', time() + 5 * HOUR_IN_SECONDS );
for ( $i = 1; $i <= 5; $i++ ) {
	blueworx_missing_page_record( '93.184.216.11' );
}
check( 'a shorter pause never cuts a longer one short', get_option( 'blueworx_missing_page_paused_until' ) > time() + 4 * HOUR_IN_SECONDS, true );
blueworx_missing_page_record( '' );
check( 'an unknown visitor is never counted', blueworx_missing_page_pause_left( '' ), 0 );
update_option( 'blueworx_missing_page_limit', 1 );
check( 'the limit has a floor of five', blueworx_missing_page_allowance(), 5 );
update_option( 'blueworx_missing_page_limit', 99999 );
check( 'and a ceiling of a thousand', blueworx_missing_page_allowance(), 1000 );

echo "\nThe count only covers its own ten minutes\n";
$GLOBALS['transients'] = array();
$old_bucket            = (int) floor( time() / 600 ) - 1;
$GLOBALS['transients'][ blueworx_missing_page_key( 'count', '93.184.216.77' ) . '_' . $old_bucket ] = 4;
check( 'misses from an earlier window are not carried over', blueworx_missing_page_increment( '93.184.216.77' ), 1 );
check( 'misses in the same window add up', blueworx_missing_page_increment( '93.184.216.77' ), 2 );
$GLOBALS['ext_cache'] = true;
check( 'with an object cache the first miss counts one', blueworx_missing_page_increment( '93.184.216.78' ), 1 );
check( 'and the next adds one atomically', blueworx_missing_page_increment( '93.184.216.78' ), 2 );
$GLOBALS['ext_cache'] = false;

echo "\nCrawler rules in robots.txt\n";
$core = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
$out  = blueworx_crawler_rules_filter( $core );
check( 'cart is kept off', false !== strpos( $out, "Disallow: /cart/\n" ), true );
check( 'checkout is kept off', false !== strpos( $out, "Disallow: /checkout/\n" ), true );
check( 'filtered shop pages are kept off', false !== strpos( $out, 'Disallow: /*?*orderby=' ), true );
check( 'one User-agent group only', substr_count( strtolower( $out ), 'user-agent:' ), 1 );
check( 'the rules sit straight under the User-agent line', 0 === strpos( $out, "User-agent: *\nDisallow: /cart/" ), true );
check( 'the original rules survive', false !== strpos( $out, 'Allow: /wp-admin/admin-ajax.php' ), true );
check( 'running it again adds nothing', blueworx_crawler_rules_filter( $out ), $out );

$custom = "User-agent: Googlebot\nDisallow: /private/\n\nUser-agent: *\nDisallow: /cart/\n";
$out    = blueworx_crawler_rules_filter( $custom );
check( 'a rule already in a group is not repeated there', substr_count( $out, 'Disallow: /cart/' ), 2 );
check( 'the catch-all group gets the other rules', strpos( $out, 'Disallow: /checkout/', strpos( $out, 'User-agent: *' ) ) !== false, true );
check( 'a named group gets the rules too', 0 === strpos( $out, "User-agent: Googlebot\nDisallow: /cart/" ), true );
check( 'and keeps its own rule', false !== strpos( $out, 'Disallow: /private/' ), true );

$only_bot = "User-agent: Googlebot\nDisallow: /private/\n";
$out      = blueworx_crawler_rules_filter( $only_bot );
check( 'with no catch-all group one is added', substr_count( $out, "User-agent: *\nDisallow: /cart/" ), 1 );

$shared = "User-agent: Bingbot\nUser-agent: *\nDisallow: /wp-admin/\n\nUser-agent: Slurp\nDisallow: /slurp/\n";
$out    = blueworx_crawler_rules_filter( $shared );
check( 'a group shared by several crawlers stays whole', 0 === strpos( $out, "User-agent: Bingbot\nUser-agent: *\nDisallow: /cart/" ), true );
check( 'other groups get the rules and keep their own', false !== strpos( $out, "User-agent: Slurp\nDisallow: /cart/" ) && false !== strpos( $out, 'Disallow: /slurp/' ), true );

$other = "User-agent: *\nDisallow: /wp-admin/\n\nUser-agent: Googlebot\nDisallow: /cart/\n";
$out   = blueworx_crawler_rules_filter( $other );
check( 'a rule in another group does not count as already there', substr_count( $out, 'Disallow: /cart/' ), 2 );

$sitemap = "User-agent: Bingbot\nSitemap: https://example.test/sitemap.xml\nUser-agent: *\nDisallow: /wp-admin/\n";
$out     = blueworx_crawler_rules_filter( $sitemap );
check( 'a Sitemap line between two User-agent lines does not split the group', 0 === strpos( $out, "User-agent: Bingbot\nSitemap: https://example.test/sitemap.xml\nUser-agent: *\nDisallow: /cart/" ), true );
check( 'and no second group is made', substr_count( strtolower( $out ), 'user-agent:' ), 2 );

$delay = "User-agent: Googlebot\nCrawl-delay: 5\nUser-agent: Bingbot\nDisallow: /private/\n";
$out   = blueworx_crawler_rules_filter( $delay );
check( 'a Crawl-delay line does not split a shared group', 0 === strpos( $out, "User-agent: Googlebot\nCrawl-delay: 5\nUser-agent: Bingbot\nDisallow: /cart/" ), true );
$host = "User-agent: Googlebot\nHost: example.test\nNoindex: /x/\nUser-agent: *\nAllow: /\n";
$out  = blueworx_crawler_rules_filter( $host );
check( 'nor does any other line that is not a rule', 0 === strpos( $out, "User-agent: Googlebot\nHost: example.test\nNoindex: /x/\nUser-agent: *\nDisallow: /cart/" ), true );
$ends = "User-agent: Googlebot\nAllow: /a/\nUser-agent: *\nDisallow: /b/\n";
$out  = blueworx_crawler_rules_filter( $ends );
check( 'an Allow line still ends one', substr_count( $out, "Disallow: /cart/\n" ), 2 );

$case = "User-agent: *\ndisallow:/Cart/\nDISALLOW: /checkout/ # ours\n";
$out  = blueworx_crawler_rules_filter( $case );
check( 'a rule for /Cart/ does not stand in for /cart/', false !== strpos( $out, "Disallow: /cart/\n" ), true );
check( 'a rule is the same however Disallow is written', strpos( $out, "Disallow: /checkout/\n" ), false );

check( 'a checkout link with a query and no closing slash is kept off', in_array( 'Disallow: /checkout?', blueworx_crawler_rules(), true ), true );
check( 'without catching a page that starts with the same word', in_array( 'Disallow: /checkout', blueworx_crawler_rules(), true ), false );

$GLOBALS['wc_pages']   = array(
	'cart'      => 11,
	'checkout'  => 12,
	'myaccount' => 13,
);
$GLOBALS['permalinks'] = array(
	11 => 'https://example.test/panier/',
	12 => 'https://example.test/boutique/commande',
	13 => 'https://example.test/?page_id=13',
);
$rules                 = blueworx_crawler_rules();
foreach ( array( '/panier/', '/panier$', '/panier?', '/boutique/commande/', '/boutique/commande?', '/?page_id=13$' ) as $path ) {
	check( "a store's own page address is kept off: " . $path, in_array( 'Disallow: ' . $path, $rules, true ), true );
}
$GLOBALS['permalinks'][12] = 'https://example.test/commande/?lang=fr';
$GLOBALS['permalinks'][13] = 'https://example.test/mon-compte?lang=fr-fr';
$rules                     = blueworx_crawler_rules();
foreach ( array( '/commande/', '/commande$', '/commande?', '/mon-compte/', '/mon-compte?' ) as $path ) {
	check( 'a shop page with a query of its own is kept off by its path: ' . $path, in_array( 'Disallow: ' . $path, $rules, true ), true );
}
$GLOBALS['permalinks'][11] = 'https://example.test/shop/?view=cart';
$GLOBALS['permalinks'][12] = 'https://example.test/shop/?page_id=12&lang=fr';
$GLOBALS['permalinks'][13] = 'https://example.test/shop/?lang=fr&page_id=13';
$rules                     = blueworx_crawler_rules();
check( 'a page picked by a query never shuts off the path it shares', preg_grep( '#^Disallow: /shop[/$?]?$#', $rules ), array() );
check( 'it is kept off by the query that picks it', in_array( 'Disallow: /shop/?page_id=12&', $rules, true ), true );
check( 'wherever in the query that is', in_array( 'Disallow: /shop/?*&page_id=13$', $rules, true ), true );
check( 'and a page whose number starts the same is not caught', array_intersect( array( 'Disallow: /shop/?page_id=12', 'Disallow: /shop/?*&page_id=13' ), $rules ), array() );
$GLOBALS['permalinks'][12] = 'https://example.test/?lang=fr';
$GLOBALS['permalinks'][13] = 'https://example.test/index.php?lang=fr&page_id=13';
$rules                     = blueworx_crawler_rules();
check( 'but never the front page in another language, or every page', preg_grep( '#^Disallow: (/|/index\.php)?[/$?]?$#', $rules ), array() );
$GLOBALS['home_path']      = '/sub';
$GLOBALS['permalinks'][12] = 'https://example.test/sub/?lang=fr';
check( 'nor on a site in a folder', preg_grep( '#^Disallow: /sub[/$?]?$#', blueworx_crawler_rules() ), array() );
$GLOBALS['home_path'] = '';
$GLOBALS['unpublish'] = array( 11 );
check( 'a page that is not published adds nothing', in_array( 'Disallow: /panier/', blueworx_crawler_rules(), true ), false );
$GLOBALS['unpublish']  = array();
$GLOBALS['permalinks'] = array( 12 => 'https://example.test/' );
check( 'a checkout on the front page never shuts crawlers out', array_intersect( array( 'Disallow: /', 'Disallow: $', 'Disallow: /$', 'Disallow: ?', 'Disallow: /?' ), blueworx_crawler_rules() ), array() );
$GLOBALS['wc_pages']   = array();
$GLOBALS['permalinks'] = array();

$GLOBALS['store_urls'] = array(
	'checkout'  => 'https://example.test/pay-here/',
	'dashboard' => 'https://example.test/?page_id=7',
);
$rules                 = blueworx_crawler_rules();
check( "the store's own checkout address is kept off", in_array( 'Disallow: /pay-here/', $rules, true ), true );
check( 'with or without the closing slash', in_array( 'Disallow: /pay-here$', $rules, true ), true );
check( 'without catching a page that starts the same way', in_array( 'Disallow: /pay-here', $rules, true ), false );
check( 'a plain-permalink page is kept off by its exact address', in_array( 'Disallow: /?page_id=7$', $rules, true ), true );
check( 'and with more arguments after it', in_array( 'Disallow: /?page_id=7&', $rules, true ), true );
check( 'never the whole site', in_array( 'Disallow: /', $rules, true ), false );
$GLOBALS['store_urls'] = array( 'checkout' => 'https://example.test/?foo=bar' );
check( 'an unknown query adds nothing', in_array( 'Disallow: /?foo=bar$', blueworx_crawler_rules(), true ), false );
$GLOBALS['store_urls'] = array();

$GLOBALS['home_path'] = '/sub';
check( 'a site in a folder prefixes the rules', in_array( 'Disallow: /sub/cart/', blueworx_crawler_rules(), true ), true );

finish();
