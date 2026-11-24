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

echo "\nStatus and the missing-image exception\n";
check( 'the answer defaults to 404', blueworx_probe_status(), 404 );
update_option( 'blueworx_probe_status', '403' );
check( 'it can be 403', blueworx_probe_status(), 403 );
update_option( 'blueworx_probe_status', 'nonsense' );
check( 'anything else falls back to 404', blueworx_probe_status(), 404 );
check( 'a missing image is a static asset', blueworx_path_is_static_asset( '/wp-content/uploads/a.JPG' ), true );
check( 'a missing page is not', blueworx_path_is_static_asset( '/no-such-page/' ), false );

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
	'HTTP_X_FORWARDED_FOR' => '10.0.0.7, 93.184.216.9',
);
check( 'behind a private balancer, the first public forwarded address is used', blueworx_visitor_ip(), '93.184.216.9' );
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
check( 'a rule already there is not repeated', substr_count( $out, 'Disallow: /cart/' ), 1 );
check( 'rules go under the catch-all group, not the first group', strpos( $out, 'Disallow: /checkout/' ) > strpos( $out, 'User-agent: *' ), true );

$only_bot = "User-agent: Googlebot\nDisallow: /private/\n";
$out      = blueworx_crawler_rules_filter( $only_bot );
check( 'with no catch-all group one is added', substr_count( $out, "User-agent: *\nDisallow: /cart/" ), 1 );

$GLOBALS['home_path'] = '/sub';
check( 'a site in a folder prefixes the rules', in_array( 'Disallow: /sub/cart/', blueworx_crawler_rules(), true ), true );

finish();
