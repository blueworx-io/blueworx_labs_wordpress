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
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
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

$GLOBALS['home_path'] = '';

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
);

foreach ( $probes as $path ) {
	check( $path, blueworx_probe_path_matches( $path ), true );
}

echo "\nNames that could be a real page wait for WordPress\n";
foreach ( array( '/docs/phpunit/', '/phpmyadmin/', '/adminer', '/server-status' ) as $path ) {
	check( 'not turned away at once: ' . $path, blueworx_probe_path_matches( $path, false ), false );
	check( 'but a probe once WordPress finds no page: ' . $path, blueworx_probe_path_matches( $path ), true );
}
foreach ( array( '/wp-config.php.bak', '/backup.zip', '/phpinfo.php', '/composer.json', '/db.sql', '/wp-content/uploads/x.php', '/dot/.git/config' ) as $path ) {
	check( 'turned away at once: ' . $path, blueworx_probe_path_matches( $path, false ), true );
}

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
$_SERVER = $server_backup;

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
