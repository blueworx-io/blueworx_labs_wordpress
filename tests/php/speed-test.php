<?php
/**
 * The speed defaults: guests who cannot buy are not asked about carts, SureRank's
 * link checks are paused, crawlers are asked to slow down, old store jobs are
 * cleared out, slow pages are logged without secrets, and the weekly report
 * says whether the object cache is really used.
 *
 * Run with: php tests/php/speed-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable Generic.Files.OneObjectStructurePerFile

require __DIR__ . '/stubs.php';

define( 'WEEK_IN_SECONDS', 604800 );
define( 'ENT_QUOTES_TEST', 1 );

$GLOBALS['taxonomies'] = array();
function get_taxonomies( $args = array(), $output = 'names' ) {
	return array_keys( $GLOBALS['taxonomies'] );
}
function get_taxonomy( $name ) {
	return isset( $GLOBALS['taxonomies'][ $name ] ) ? (object) array( 'rewrite' => array( 'slug' => $GLOBALS['taxonomies'][ $name ] ) ) : false;
}

$GLOBALS['switches'] = array();
function blueworx_feature_enabled( $key ) {
	return ! isset( $GLOBALS['switches'][ $key ] ) || $GLOBALS['switches'][ $key ];
}

$GLOBALS['hooks'] = array();
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = array( $callback, $priority );
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = array( $callback, $priority );
}
function hooked( $hook, $callback ) {
	foreach ( isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array() as $entry ) {
		if ( $entry[0] === $callback ) {
			return true;
		}
	}
	return false;
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function sanitize_email( $email ) {
	return is_email( $email ) ? $email : '';
}
function wp_unslash( $value ) {
	return $value;
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, $args );
}
function wp_rand( $min, $max ) {
	return $GLOBALS['rand'];
}
$GLOBALS['rand'] = 1;
function get_page_uri( $id ) {
	$pages = array(
		7 => 'blog',
		8 => 'blog-%e3%83%96%e3%83%ad%e3%82%b0',
	);
	return isset( $pages[ (int) $id ] ) ? $pages[ (int) $id ] : '';
}
function home_url( $path = '' ) {
	return 'https://example.test' . ( isset( $GLOBALS['home_base'] ) ? $GLOBALS['home_base'] : '' ) . $path;
}
function get_bloginfo( $what ) {
	return 'Example &amp; Co';
}
function wp_specialchars_decode( $text, $quotes = 0 ) {
	return htmlspecialchars_decode( $text );
}
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( $number, $decimals );
}
function is_admin() {
	return false;
}
function wp_doing_cron() {
	return false;
}
function wp_doing_ajax() {
	return false;
}
function wp_using_ext_object_cache() {
	return $GLOBALS['ext_cache'];
}
$GLOBALS['ext_cache'] = true;
function get_num_queries() {
	return 42;
}
$GLOBALS['logged_in'] = false;
function is_user_logged_in() {
	return $GLOBALS['logged_in'];
}
$GLOBALS['object_cache'] = array();
function date_i18n( $format, $timestamp = false ) {
	return gmdate( $format, $timestamp );
}
function wp_cache_get( $key, $group = '' ) {
	return isset( $GLOBALS['object_cache'][ $group . $key ] ) ? $GLOBALS['object_cache'][ $group . $key ] : false;
}
function wp_cache_set( $key, $value, $group = '', $expire = 0 ) {
	$GLOBALS['object_cache'][ $group . $key ] = $value;
	return true;
}
$GLOBALS['did_wp'] = 0;
function did_action( $hook ) {
	return 'wp' === $hook ? $GLOBALS['did_wp'] : 0;
}
$GLOBALS['is_search'] = false;
function is_search() {
	return $GLOBALS['is_search'];
}
$GLOBALS['is_404']   = false;
$GLOBALS['singular'] = false;
function is_singular() {
	return $GLOBALS['singular'];
}
function is_404() {
	return $GLOBALS['is_404'];
}
$GLOBALS['post_type'] = 'page';
/**
 * What a theme's related-posts loop leaves behind: never the requested page's type.
 */
function get_post_type() {
	return 'post';
}
function is_home() {
	return ! empty( $GLOBALS['is_home'] );
}
function get_queried_object() {
	return (object) array( 'post_type' => $GLOBALS['post_type'] );
}
/**
 * The address of the post WordPress answered with.
 *
 * @param mixed $post The post.
 * @return string|false The address.
 */
function get_permalink( $post = 0 ) {
	return isset( $GLOBALS['permalink'] ) ? 'https://example.test' . $GLOBALS['permalink'] : false;
}
function wp_dequeue_script( $handle ) {
	$GLOBALS['dequeued'][] = $handle;
}
$GLOBALS['dequeued'] = array();
function nocache_headers() {}
/**
 * Stops the request, as wp_send_json() does.
 */
class Stop_Request extends Exception {}
function wp_send_json( $data ) {
	$GLOBALS['json'] = $data;
	throw new Stop_Request();
}
function rest_ensure_response( $data ) {
	return $data;
}
/**
 * Stands in for WooCommerce being active.
 */
class WooCommerce {}
/**
 * A REST request reduced to its method and route.
 */
class WP_REST_Request {
	/**
	 * Method.
	 *
	 * @var string
	 */
	public $method;
	/**
	 * Route.
	 *
	 * @var string
	 */
	public $route;
	public function __construct( $method, $route ) {
		$this->method = $method;
		$this->route  = $route;
	}
	public function get_method() {
		return $this->method;
	}
	public function get_route() {
		return $this->route;
	}
}
function absint( $value ) {
	return abs( (int) $value );
}
$GLOBALS['filters']['blueworx_request_kind'] = function () {
	return 'front';
};

require __DIR__ . '/../../includes/bot-control.php';
require __DIR__ . '/../../includes/speed-defaults.php';
require __DIR__ . '/../../includes/speed-monitor.php';
require __DIR__ . '/../../includes/speed-cleanup.php';
require __DIR__ . '/../../includes/speed-report.php';

echo "Each function has its own switch, and every hook follows it\n";
check( 'guest cart answer hooked as WooCommerce starts, before it makes a session or cart', hooked( 'before_woocommerce_init', 'blueworx_guest_cart_check_answer' ), true );
check( 'and not earlier, before a late cookie name is known', hooked( 'init', 'blueworx_guest_cart_check_answer' ), false );
check( 'and as late as that action allows', $GLOBALS['hooks']['before_woocommerce_init'][0][1], PHP_INT_MAX );
check( 'not after WooCommerce has made its cart', hooked( 'wp_loaded', 'blueworx_guest_cart_check_answer' ), false );
check( 'the cart script is left alone', hooked( 'wp_enqueue_scripts', 'blueworx_guest_cart_script_off' ), false );
check( 'SureRank pause hooked', hooked( 'rest_pre_dispatch', 'blueworx_surerank_link_check_pause' ), true );
check( 'crawl delay hooked', hooked( 'robots_txt', 'blueworx_crawl_delay_filter' ), true );
check( 'monitor hooked', hooked( 'shutdown', 'blueworx_speed_monitor_shutdown' ), true );
check( 'cleanup runs on its event', hooked( 'blueworx_weekly_cleanup', 'blueworx_cleanup_cron' ), true );
check( 'report runs on its event', hooked( 'blueworx_weekly_speed_report', 'blueworx_speed_report_cron' ), true );

$GLOBALS['hooks']    = array();
$GLOBALS['switches'] = array(
	'guest_cart_checks'    => false,
	'surerank_link_checks' => false,
	'crawl_delay'          => false,
	'slow_log'             => false,
	'speed_report'         => false,
);
blueworx_speed_defaults_hooks();
blueworx_speed_monitor_hooks();
check( 'all off: nothing hooked', $GLOBALS['hooks'], array() );
$GLOBALS['switches'] = array( 'slow_log' => false );
blueworx_speed_monitor_hooks();
check( 'the report alone still hooks the monitor', hooked( 'shutdown', 'blueworx_speed_monitor_shutdown' ), true );
$GLOBALS['switches'] = array();

echo "\nA signed-out visitor who cannot buy is not asked about carts\n";
update_option( 'woocommerce_enable_guest_checkout', 'no' );
update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' );
check( 'guest, sign-in needed to buy', blueworx_guest_cannot_buy(), true );
$GLOBALS['logged_in'] = true;
check( 'a signed-in customer can buy', blueworx_guest_cannot_buy(), false );
$GLOBALS['logged_in'] = false;
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
check( 'guest checkout on: a guest can buy', blueworx_guest_cannot_buy(), false );
update_option( 'woocommerce_enable_guest_checkout', 'no' );
update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
check( 'account creation at checkout: a guest can buy', blueworx_guest_cannot_buy(), false );
update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' );
$GLOBALS['filters']['blueworx_guest_can_buy'] = function () {
	return true;
};
check( 'a site can say guests buy some other way', blueworx_guest_cannot_buy(), false );
unset( $GLOBALS['filters']['blueworx_guest_can_buy'] );

$_GET = array( 'wc-ajax' => 'get_refreshed_fragments' );
check( 'the cart check is recognised', blueworx_is_cart_check_request(), true );
$answered = false;
try {
	blueworx_guest_cart_check_answer();
} catch ( Stop_Request $e ) {
	$answered = true;
}
check( 'it is answered with no cart', $answered && empty( (array) $GLOBALS['json']['fragments'] ) && '' === $GLOBALS['json']['cart_hash'], true );

$_GET     = array( 'wc-ajax' => 'add_to_cart' );
$answered = false;
try {
	blueworx_guest_cart_check_answer();
} catch ( Stop_Request $e ) {
	$answered = true;
}
check( 'adding to a cart is never short-circuited', $answered, false );

$_GET                 = array( 'wc-ajax' => 'get_refreshed_fragments' );
$GLOBALS['logged_in'] = true;
$answered             = false;
try {
	blueworx_guest_cart_check_answer();
} catch ( Stop_Request $e ) {
	$answered = true;
}
check( 'a signed-in customer still gets their cart', $answered, false );
$GLOBALS['logged_in'] = false;
$_GET                 = array();

echo "\nA guest who has a cart in progress is still asked about it\n";
$_GET = array( 'wc-ajax' => 'get_refreshed_fragments' );
foreach ( array( 'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_abc123' ) as $cookie ) {
	$_COOKIE  = array( $cookie => '1' );
	$answered = false;
	try {
		blueworx_guest_cart_check_answer();
	} catch ( Stop_Request $e ) {
		$answered = true;
	}
	check( 'a ' . $cookie . ' cookie means a cart: asked as before', $answered, false );
}
foreach ( array( 'my_store_session_9f8e7d', 'Store_SESSION', 'shop_cart_items', 'woocommerce_custom_cart', 'wp_woocommerce_session_zz' ) as $cookie ) {
	$_COOKIE  = array( $cookie => '1' );
	$answered = false;
	try {
		blueworx_guest_cart_check_answer();
	} catch ( Stop_Request $e ) {
		$answered = true;
	}
	check( 'a cart cookie named ' . $cookie . ' (renamed by a plugin that loaded late) still keeps the cart', $answered, false );
}
$_COOKIE  = array(
	'woocommerce_recently_viewed' => '1',
	'PHPSESSID'                   => 'x',
);
$answered = false;
try {
	blueworx_guest_cart_check_answer();
} catch ( Stop_Request $e ) {
	$answered = true;
}
check( 'a recently viewed list is not a cart', $answered, true );
define( 'COOKIEHASH', 'abc' );
$GLOBALS['filters']['woocommerce_cookie'] = function () {
	return 'mp_store_session';
};
$_COOKIE                                  = array( 'mp_store_session' => '1' );
$answered                                 = false;
try {
	blueworx_guest_cart_check_answer();
} catch ( Stop_Request $e ) {
	$answered = true;
}
check( 'a session cookie the store named itself is a cart even if it starts like a tracking one', $answered, false );
unset( $GLOBALS['filters']['woocommerce_cookie'] );
foreach ( array( 'sbjs_session', 'sbjs_current', '_hjSession_123', '_ga_ABC123', '_fbp', 'pys_start_session', 'pys_session_limit' ) as $cookie ) {
	$_COOKIE  = array( $cookie => '1' );
	$answered = false;
	try {
		blueworx_guest_cart_check_answer();
	} catch ( Stop_Request $e ) {
		$answered = true;
	}
	check( 'the tracking cookie ' . $cookie . ' is not a cart', $answered, true );
}
$GLOBALS['filters']['woocommerce_cookie'] = function () {
	return 'my_store_session';
};
$_COOKIE                                  = array( 'my_store_session' => '1' );
$answered                                 = false;
try {
	blueworx_guest_cart_check_answer();
} catch ( Stop_Request $e ) {
	$answered = true;
}
check( 'a renamed session cookie means a cart too', $answered, false );
unset( $GLOBALS['filters']['woocommerce_cookie'] );
$_COOKIE  = array( 'other' => '1' );
$answered = false;
try {
	blueworx_guest_cart_check_answer();
} catch ( Stop_Request $e ) {
	$answered = true;
}
check( 'an unrelated cookie does not', $answered, true );
$_COOKIE = array();
$_GET    = array();
echo "\nSureRank's link check is paused, and nothing else\n";
$post = new WP_REST_Request( 'POST', '/surerank/v1/checks/broken-link' );
$out  = blueworx_surerank_link_check_pause( null, null, $post );
check( 'the check is answered without being made', $out['success'] && $out['ignored'], true );
check( 'a later version of the route too', is_array( blueworx_surerank_link_check_pause( null, null, new WP_REST_Request( 'POST', '/surerank/v2/checks/broken-link' ) ) ), true );
check( 'other SureRank checks run', blueworx_surerank_link_check_pause( null, null, new WP_REST_Request( 'POST', '/surerank/v1/checks/page' ) ), null );
check( 'the ignore list is untouched', blueworx_surerank_link_check_pause( null, null, new WP_REST_Request( 'POST', '/surerank/v1/checks/broken-link-ignore' ) ), null );
check( 'another plugin with a similar route', blueworx_surerank_link_check_pause( null, null, new WP_REST_Request( 'POST', '/other/v1/checks/broken-link' ) ), null );
check( 'only a POST', blueworx_surerank_link_check_pause( null, null, new WP_REST_Request( 'GET', '/surerank/v1/checks/broken-link' ) ), null );
check( 'an answer somebody else already gave is kept', blueworx_surerank_link_check_pause( 'x', null, $post ), 'x' );

echo "\nHeavy crawlers are asked to slow down, and Google is not\n";
$base = "User-agent: *\nDisallow: /wp-admin/\n";
$out  = blueworx_crawl_delay_filter( $base, true );
check( 'the original rules are kept', 0 === strpos( $out, $base ), true );
check( 'a crawler is named', false !== strpos( $out, "User-agent: AhrefsBot\n" ), true );
check( 'with a delay', false !== strpos( $out, "Crawl-delay: 10\n" ), true );
check( 'Googlebot is not slowed', false === stripos( $out, 'googlebot' ), true );
check( 'Bingbot is not slowed', false === stripos( $out, 'bingbot' ), true );
check( 'one delay line for the group', substr_count( $out, 'Crawl-delay' ), 1 );
check( 'a site that discourages search engines gets it too', false !== strpos( blueworx_crawl_delay_filter( $base, false ), 'Crawl-delay: 10' ), true );
$out = blueworx_crawl_delay_filter( "User-agent: *\nDisallow: /wp-admin/\nDisallow: /private/\nAllow: /wp-admin/admin-ajax.php\n", true );
check( 'the group keeps what the site already blocks', 2 === substr_count( $out, 'Disallow: /private/' ) && 2 === substr_count( $out, 'Disallow: /wp-admin/' ), true );
check( 'and what it allows', 2 === substr_count( $out, 'Allow: /wp-admin/admin-ajax.php' ), true );
$out = blueworx_crawl_delay_filter(
	'User-agent: * # default
Disallow: /private/ # keep out
',
	true
);
check( 'a comment after the star does not hide its rules', 2 === substr_count( $out, 'Disallow: /private/' ), true );
$own = "User-agent: AhrefsBot\nDisallow: /\n\nUser-agent: *\nDisallow: /wp-admin/\n";
check( 'a crawler with its own group is left to it', substr_count( blueworx_crawl_delay_filter( $own, true ), 'AhrefsBot' ), 1 );
$GLOBALS['filters']['blueworx_crawl_delay_agents'] = function () {
	return array( "Evil\nDisallow: /", 'Good Bot', 'FineBot' );
};
$out = blueworx_crawl_delay_filter( $base, true );
check( 'a crawler name with a line break in it is left out', false === strpos( $out, 'Evil' ), true );
check( 'a crawler name with a space in it is left out', false === strpos( $out, 'Good' ), true );
check( 'a plain name stays', false !== strpos( $out, "User-agent: FineBot\n" ), true );
unset( $GLOBALS['filters']['blueworx_crawl_delay_agents'] );
$GLOBALS['filters']['blueworx_crawl_delay_seconds'] = function () {
	return 500;
};
check( 'the delay is capped', false !== strpos( blueworx_crawl_delay_filter( $base, true ), 'Crawl-delay: 60' ), true );
unset( $GLOBALS['filters']['blueworx_crawl_delay_seconds'] );

echo "\nThe slow-page log keeps no secrets\n";
$_SERVER['REQUEST_URI'] = '/shop/?token=abc123&s=my+secret&utm_source=x';
check( 'a search made the old way keeps nothing', blueworx_slow_request_label(), '(search)' );
$GLOBALS['did_wp']      = 1;
$GLOBALS['singular']    = true;
$GLOBALS['post_type']   = 'page';
$GLOBALS['permalink']   = '/shop/';
$_SERVER['REQUEST_URI'] = '/shop/?token=abc123&utm_source=x';
check( 'values are never kept', blueworx_slow_request_label(), '/shop/?token&utm_source' );
$GLOBALS['did_wp']      = 0;
$GLOBALS['singular']    = false;
$_SERVER['REQUEST_URI'] = '/search/jane.doe@example.com/';
check( 'a pretty search address is not kept', blueworx_slow_request_label(), '(search)' );
$_SERVER['REQUEST_URI'] = '/members/jane.doe@example.com/profile/';
check( 'an email address in a path is not kept', blueworx_slow_request_label(), '/*' );
$GLOBALS['did_wp']      = 1;
$GLOBALS['singular']    = true;
$GLOBALS['permalink']   = '/members/about-us/';
$_SERVER['REQUEST_URI'] = '/members/about-us/';
check( "the site's own content keeps its address", blueworx_slow_request_label(), '/members/about-us/' );
$_SERVER['REQUEST_URI'] = '/members/about-us/page/2/';
check( 'and its numbered pages', blueworx_slow_request_label(), '/members/about-us/page/2/' );
$_SERVER['REQUEST_URI'] = '/members/about-us/comment-page-3/';
check( 'and its comment pages', blueworx_slow_request_label(), '/members/about-us/comment-page-3/' );
$_SERVER['REQUEST_URI'] = '/members/about-us/2/';
check( 'a page of a post split in parts is kept', blueworx_slow_request_label(), '/members/about-us/2/' );
$_SERVER['REQUEST_URI'] = '/members/about-us/feed/rss2/';
check( 'a named feed is kept', blueworx_slow_request_label(), '/members/about-us/feed/rss2/' );
$_SERVER['REQUEST_URI'] = '/members/about-us/orders/';
check( 'a store endpoint is kept', blueworx_slow_request_label(), '/members/about-us/orders/' );
$_SERVER['REQUEST_URI'] = '/members/about-us/edit-address/billing/';
check( 'with its value', blueworx_slow_request_label(), '/members/about-us/edit-address/billing/' );
$_SERVER['REQUEST_URI'] = '/members/about-us/order-received/123/';
check( 'and an order number', blueworx_slow_request_label(), '/members/about-us/order-received/123/' );
$_SERVER['REQUEST_URI'] = '/members/about-us/order-received/4400123987/';
check( 'a long order number is already a star', blueworx_slow_request_label(), '/members/about-us/order-received/*/' );
$_SERVER['REQUEST_URI'] = '/members/about-us/orders/jane-doe/';
check( 'a name after an endpoint is not', blueworx_slow_request_label(), '/*' );
$_SERVER['REQUEST_URI'] = '/members/about-us/jane/billing/';
check( 'nor is a word that is not an endpoint', blueworx_slow_request_label(), '/*' );
$_SERVER['REQUEST_URI'] = '/members/jane-doe/';
check( 'a profile a plugin answers with a real page is not named', blueworx_slow_request_label(), '/*' );
$_SERVER['REQUEST_URI'] = '/members/about-us/jane-doe/';
check( 'nor is a name after the page address', blueworx_slow_request_label(), '/*' );
$_SERVER['REQUEST_URI'] = '/author/jane-doe/?p=123';
$GLOBALS['permalink']   = '/2024/05/a-post/';
check( 'a post answered at an author address is not named', blueworx_slow_request_label(), '/author/*?p' );
$GLOBALS['permalink'] = '/members/about-us/';
unset( $GLOBALS['permalink'] );
$_SERVER['REQUEST_URI'] = '/members/about-us/';
check( 'a post whose address WordPress cannot give is not named', blueworx_slow_request_label(), '/*' );
$GLOBALS['permalink']   = '/members/about-us/';
$GLOBALS['post_type']   = 'member';
$_SERVER['REQUEST_URI'] = '/members/jane-doe/';
check( 'a member post type keeps no name even though WordPress found it', blueworx_slow_request_label(), '/*' );
$GLOBALS['post_type']   = 'product';
$GLOBALS['permalink']   = '/shop/blue-shirt/';
$_SERVER['REQUEST_URI'] = '/shop/blue-shirt/';
check( 'a product keeps its address', blueworx_slow_request_label(), '/shop/blue-shirt/' );
$GLOBALS['post_type']   = 'page';
$GLOBALS['post_type']   = 'member';
$_SERVER['REQUEST_URI'] = '/jane-doe/';
check( 'a member at the root of the site is not named either', blueworx_slow_request_label(), '/*' );
$GLOBALS['post_type']   = 'page';
$GLOBALS['permalink']   = '/about/';
$_SERVER['REQUEST_URI'] = '/about/';
check( 'a page at the root keeps its name', blueworx_slow_request_label(), '/about/' );
$GLOBALS['singular']    = false;
$_SERVER['REQUEST_URI'] = '/jane-doe/';
check( 'a root address a plugin answers is not named', blueworx_slow_request_label(), '/*' );
$_SERVER['REQUEST_URI'] = '/sitemap.xml';
check( 'a file name at the root stays', blueworx_slow_request_label(), '/sitemap.xml' );
$_SERVER['REQUEST_URI'] = '/members/jane-doe/';
check( 'but a profile a plugin answers does not', blueworx_slow_request_label(), '/*' );
$_SERVER['REQUEST_URI'] = '/jane.doe/';
check( 'a name with a dot is not mistaken for a file', blueworx_slow_request_label(), '/*' );
$_SERVER['REQUEST_URI'] = '/jane-doe/feed/';
check( 'a name in front of a feed is not kept', blueworx_slow_request_label(), '/*' );
$_SERVER['REQUEST_URI'] = '/author/jane-doe/';
check( 'a known route keeps its first piece', blueworx_slow_request_label(), '/author/*' );
$_SERVER['REQUEST_URI'] = '/wp-login.php';
check( 'a known file stays', blueworx_slow_request_label(), '/wp-login.php' );
$GLOBALS['filters']['blueworx_slow_log_safe_prefixes'] = function ( $list ) {
	$list[] = 'members';
	return $list;
};
$_SERVER['REQUEST_URI']                                = '/members/jane-doe/';
check( 'a site can say which first pieces are safe', blueworx_slow_request_label(), '/members/*' );
unset( $GLOBALS['filters']['blueworx_slow_log_safe_prefixes'] );

// WordPress's own request, not a list a theme made with query_posts().
$GLOBALS['wp_the_query'] = new class() {
	public function is_front_page() {
		return false;
	}
	public function is_home() {
		return false;
	}
	public function is_date() {
		return false;
	}
	public function is_singular() {
		return true;
	}
	public function is_post_type_archive() {
		return false;
	}
	public function is_category() {
		return false;
	}
	public function is_tag() {
		return false;
	}
	public function is_tax() {
		return false;
	}
	public function get_queried_object() {
		return (object) array( 'post_type' => 'member' );
	}
};
$GLOBALS['singular']     = false;
$GLOBALS['is_home']      = true;
$_SERVER['REQUEST_URI']  = '/jane-doe/';
check( 'a theme list that changed the page query does not make a member page content', blueworx_slow_request_label(), '/*' );
unset( $GLOBALS['wp_the_query'], $GLOBALS['is_home'] );

// A profile plugin that answers /jane-doe/ with its own query argument can leave
// WordPress calling the request the home page.
$GLOBALS['wp_the_query'] = new class() {
	/**
	 * Which checks answer yes.
	 *
	 * @var array
	 */
	public $flags = array( 'is_front_page' => true );
	public function is_front_page() {
		return ! empty( $this->flags['is_front_page'] );
	}
	public function is_home() {
		return ! empty( $this->flags['is_home'] );
	}
	public function is_date() {
		return ! empty( $this->flags['is_date'] );
	}
	public function is_singular() {
		return false;
	}
	public function is_post_type_archive() {
		return false;
	}
	public function is_category() {
		return false;
	}
	public function is_tag() {
		return false;
	}
	public function is_tax() {
		return false;
	}
	public function get_queried_object() {
		return ! empty( $this->flags['is_home'] ) ? (object) array( 'post_type' => 'page' ) : null;
	}
};
$GLOBALS['did_wp']       = 1;
$_SERVER['REQUEST_URI']  = '/jane-doe/';
check( 'a profile WordPress calls the home page is not named', blueworx_slow_request_label(), '/*' );
$_SERVER['REQUEST_URI'] = '/';
check( 'the home page is', blueworx_slow_request_label(), '/' );
$_SERVER['REQUEST_URI'] = '/page/2/';
check( 'a page of the home page is kept', blueworx_slow_request_label(), '/page/2/' );
$_SERVER['REQUEST_URI'] = '/page/jane-doe/';
check( 'a profile under "page" that WordPress calls the home page is not named', blueworx_slow_request_label(), '/page/*' );
$_SERVER['REQUEST_URI'] = '/page/2/jane-doe/';
check( 'nor is a name after the page number', blueworx_slow_request_label(), '/page/*' );
$GLOBALS['home_base']   = '/site';
$_SERVER['REQUEST_URI'] = '/site/';
check( 'the home page of a site kept in a folder keeps its address', blueworx_slow_request_label(), '/site/' );
$_SERVER['REQUEST_URI'] = '/site/page/3/';
check( 'and so does a numbered page of it', blueworx_slow_request_label(), '/site/page/3/' );
$_SERVER['REQUEST_URI'] = '/site/jane-doe/';
check( 'but a name under the folder is not kept', blueworx_slow_request_label(), '/*' );
unset( $GLOBALS['home_base'] );
$_SERVER['REQUEST_URI'] = '/index.php/page/2/';
check( 'a numbered home page on a site that shows index.php is kept', blueworx_slow_request_label(), '/index.php/page/2/' );
$_SERVER['REQUEST_URI'] = '/index.php/jane-doe/';
check( 'but a name after index.php is not', blueworx_slow_request_label(), '/index.php/*' );
$GLOBALS['wp_the_query']->flags       = array( 'is_home' => true );
$GLOBALS['options']['page_for_posts'] = 7;
$_SERVER['REQUEST_URI']               = '/blog/';
check( 'the posts page is kept at its own address', blueworx_slow_request_label(), '/blog/' );
$_SERVER['REQUEST_URI'] = '/blog/page/2/';
check( 'and its numbered pages', blueworx_slow_request_label(), '/blog/page/2/' );
$_SERVER['REQUEST_URI'] = '/jane-doe/';
check( 'a profile answered through the posts page is not named', blueworx_slow_request_label(), '/*' );
$_SERVER['REQUEST_URI'] = '/blog/jane-doe/';
check( 'nor is a name under the posts page', blueworx_slow_request_label(), '/*' );
unset( $GLOBALS['options']['page_for_posts'] );
$GLOBALS['options']['page_for_posts'] = 8;
$_SERVER['REQUEST_URI']               = '/blog-%E3%83%96%E3%83%AD%E3%82%B0/';
check( 'a posts page with a name outside plain ASCII is kept', blueworx_slow_request_label(), '/blog-/' );
$_SERVER['REQUEST_URI'] = '/blog-%E3%83%96%E3%83%AD%E3%82%B0/page/2/';
check( 'and its numbered pages', blueworx_slow_request_label(), '/blog-/page/2/' );
$_SERVER['REQUEST_URI'] = '/blog-jane-doe/';
check( 'another address is still not kept', blueworx_slow_request_label(), '/*' );
unset( $GLOBALS['options']['page_for_posts'] );
$GLOBALS['wp_the_query']->flags = array( 'is_front_page' => true );
$GLOBALS['wp_rewrite']          = (object) array( 'pagination_base' => 'Paginas' );
$_SERVER['REQUEST_URI']         = '/Paginas/2/';
check( 'a numbered home page with a capitalised page word is kept', blueworx_slow_request_label(), '/Paginas/2/' );
$_SERVER['REQUEST_URI'] = '/paginas/jane-doe/';
check( 'a name after the page word is not', blueworx_slow_request_label(), '/*' );
unset( $GLOBALS['wp_rewrite'] );
$GLOBALS['wp_the_query']->flags = array( 'is_date' => true );
$_SERVER['REQUEST_URI']         = '/2024/05/';
check( 'a date archive is kept', blueworx_slow_request_label(), '/2024/05/' );
$_SERVER['REQUEST_URI'] = '/2024/';
check( 'a year archive is kept', blueworx_slow_request_label(), '/2024/' );
$_SERVER['REQUEST_URI'] = '/2024/05/12/page/2/';
check( 'a dated page number is kept', blueworx_slow_request_label(), '/2024/05/12/page/2/' );
$_SERVER['REQUEST_URI'] = '/2024/jane-doe/';
check( 'a name after the year is not kept', blueworx_slow_request_label(), '/*' );
$_SERVER['REQUEST_URI'] = '/jane-doe/';
check( 'a profile WordPress calls a date archive is not named', blueworx_slow_request_label(), '/*' );

// Dates under the site's own address structure.
$GLOBALS['wp_rewrite']  = new class() {
	/**
	 * The structure to answer with.
	 *
	 * @var string
	 */
	public $struct = '/archives/date/%year%/%monthnum%/%day%';
	public function get_date_permastruct() {
		return $this->struct;
	}
};
$_SERVER['REQUEST_URI'] = '/archives/date/2026/';
check( 'a year archive under a date word is kept', blueworx_slow_request_label(), '/archives/date/2026/' );
$_SERVER['REQUEST_URI'] = '/archives/date/2026/05/';
check( 'and a month', blueworx_slow_request_label(), '/archives/date/2026/05/' );
$_SERVER['REQUEST_URI'] = '/archives/jane-doe/';
check( 'but a name under the same word is not', blueworx_slow_request_label(), '/*' );
$_SERVER['REQUEST_URI'] = '/2026/';
check( 'and a bare year is not a date there', blueworx_slow_request_label(), '/*' );
$GLOBALS['wp_rewrite']->struct = '/archive-%year%/%monthnum%/%day%';
$_SERVER['REQUEST_URI']        = '/archive-2026/';
check( 'a word stuck to the year is kept', blueworx_slow_request_label(), '/archive-2026/' );
$_SERVER['REQUEST_URI'] = '/archive-2026/05/12/page/2/';
check( 'with its month, day and page number', blueworx_slow_request_label(), '/archive-2026/05/12/page/2/' );
$_SERVER['REQUEST_URI'] = '/archive-jane-doe/';
check( 'but not a name in its place', blueworx_slow_request_label(), '/*' );
$GLOBALS['wp_rewrite']->struct = '/archives/page/%year%/%monthnum%/%day%';
$_SERVER['REQUEST_URI']        = '/archives/page/2026/';
check( 'a folder called page is not a page number', blueworx_slow_request_label(), '/archives/page/2026/' );
$_SERVER['REQUEST_URI'] = '/archives/page/2026/page/2/';
check( 'and a page number after it still is', blueworx_slow_request_label(), '/archives/page/2026/page/2/' );
$GLOBALS['wp_rewrite']->struct = '/%monthnum%/%day%/%year%';
$_SERVER['REQUEST_URI']        = '/05/12/2026/';
check( 'a date with the year last is kept', blueworx_slow_request_label(), '/05/12/2026/' );
$_SERVER['REQUEST_URI'] = '/2026/';
check( 'and a year on its own', blueworx_slow_request_label(), '/2026/' );
$_SERVER['REQUEST_URI'] = '/05/12/jane-doe/';
check( 'a name in the place of the year is not', blueworx_slow_request_label(), '/*' );
$GLOBALS['wp_rewrite']->struct = '';
$_SERVER['REQUEST_URI']        = '/2026/05/';
check( 'plain addresses have no date in the path', blueworx_slow_request_label(), '/*' );
unset( $GLOBALS['wp_rewrite'] );

// A folder with letters outside plain ASCII in its name.
$GLOBALS['wp_the_query']->flags = array( 'is_front_page' => true );
$GLOBALS['home_base']           = '/caf%C3%A9';
$_SERVER['REQUEST_URI']         = '/caf%C3%A9/page/2/';
check( 'the home page of a site in an encoded folder is kept', blueworx_slow_request_label(), '/caf/page/2/' );
$_SERVER['REQUEST_URI'] = '/caf%C3%A9/jane-doe/';
check( 'and a name under it is not', blueworx_slow_request_label(), '/*' );
unset( $GLOBALS['home_base'] );
$GLOBALS['wp_the_query']->flags = array( 'is_date' => true );

// A category or tag archive answers to the same list as other term archives.
$GLOBALS['wp_the_query'] = new class() {
	public function is_front_page() {
		return false;
	}
	public function is_home() {
		return false;
	}
	public function is_date() {
		return false;
	}
	public function is_singular() {
		return false;
	}
	/**
	 * Whether WordPress also calls this a post type archive.
	 *
	 * @var bool
	 */
	public $both = false;
	/**
	 * The terms WordPress was asked for, as its tax query holds them.
	 *
	 * @var object|null
	 */
	public $tax_query = null;
	/**
	 * Whether WordPress also calls this an author archive.
	 *
	 * @var bool
	 */
	public $author = false;
	public function is_author() {
		return $this->author;
	}
	public function is_post_type_archive() {
		return $this->both;
	}
	public function is_category() {
		return true;
	}
	public function is_tag() {
		return false;
	}
	public function is_tax() {
		return false;
	}
	public function get_queried_object() {
		return (object) array( 'taxonomy' => 'category' );
	}
};
$_SERVER['REQUEST_URI']  = '/category/private-clients/';
check( 'a category archive is kept by default', blueworx_slow_request_label(), '/category/private-clients/' );
$GLOBALS['filters']['blueworx_slow_log_taxonomies'] = function () {
	return array( 'product_cat' );
};
check( 'but not when a site takes categories off the list', blueworx_slow_request_label(), '/category/*' );
$GLOBALS['wp_the_query']->both = true;
check( 'even when WordPress also calls it a post type archive', blueworx_slow_request_label(), '/category/*' );
$GLOBALS['wp_the_query']->both = false;

// A request for a category and a tag at once is a category to WordPress, but the
// address holds the tag, so a site that takes tags off the list keeps it out.
$GLOBALS['wp_the_query']->tax_query                 = (object) array(
	'queried_terms' => array(
		'category' => array(),
		'post_tag' => array(),
	),
);
$_SERVER['REQUEST_URI']                             = '/tag/jane-doe/';
$GLOBALS['filters']['blueworx_slow_log_taxonomies'] = function () {
	return array( 'category', 'product_cat' );
};
check( 'a category filter does not let an excluded tag slug through', blueworx_slow_request_label(), '/tag/*' );
unset( $GLOBALS['filters']['blueworx_slow_log_taxonomies'] );
check( 'tags on the list keep their address', blueworx_slow_request_label(), '/tag/jane-doe/' );
$GLOBALS['filters']['blueworx_slow_log_taxonomies'] = function () {
	return array( 'category', 'post_tag' );
};
$GLOBALS['wp_the_query']->tax_query                 = (object) array(
	'queried_terms' => array(
		'category' => array(),
		'post_tag' => array(),
		'language' => array(),
	),
);
check( 'a language taxonomy is not counted', blueworx_slow_request_label(), '/tag/jane-doe/' );
unset( $GLOBALS['filters']['blueworx_slow_log_taxonomies'] );

// Whatever the request's flags say, a taxonomy that is not on the list never
// shows its term in the address: the address itself is checked against the
// address word of each public taxonomy left off the list.
$GLOBALS['wp_the_query']->tax_query                 = null;
$GLOBALS['taxonomies']                              = array(
	'category' => 'category',
	'post_tag' => 'tag',
);
$_SERVER['REQUEST_URI']                             = '/tag/jane-doe/';
$GLOBALS['filters']['blueworx_slow_log_taxonomies'] = function () {
	return array( 'category' );
};
check( 'a tag word in the address is kept out even when the request does not flag a tag', blueworx_slow_request_label(), '/tag/*' );
$_SERVER['REQUEST_URI'] = '/category/news/tag/jane-doe/';
check( 'and so is a tag after a category', blueworx_slow_request_label(), '/category/*' );
$GLOBALS['filters']['blueworx_slow_log_taxonomies'] = function () {
	return array( 'Category', 'POST_TAG' );
};
$_SERVER['REQUEST_URI']                             = '/tag/jane-doe/';
check( 'names on the list are matched whatever their case', blueworx_slow_request_label(), '/tag/jane-doe/' );
unset( $GLOBALS['filters']['blueworx_slow_log_taxonomies'] );
$GLOBALS['taxonomies'] = array();

/**
 * A request WordPress flags in a few ways at once: each flag is answered.
 */
class Flag_Query {
	/**
	 * Which checks answer yes.
	 *
	 * @var array
	 */
	public $flags = array();
	/**
	 * The page or term WordPress answered with.
	 *
	 * @var object|null
	 */
	public $object = null;
	/**
	 * The post types the request asks for.
	 *
	 * @var string[]
	 */
	public $types = array();
	/**
	 * The terms WordPress was asked for.
	 *
	 * @var object|null
	 */
	public $tax_query = null;
	public function is_front_page() {
		return ! empty( $this->flags['is_front_page'] );
	}
	public function is_home() {
		return ! empty( $this->flags['is_home'] );
	}
	public function is_date() {
		return ! empty( $this->flags['is_date'] );
	}
	public function is_singular() {
		return ! empty( $this->flags['is_singular'] );
	}
	public function is_author() {
		return ! empty( $this->flags['is_author'] );
	}
	public function is_category() {
		return ! empty( $this->flags['is_category'] );
	}
	public function is_tag() {
		return ! empty( $this->flags['is_tag'] );
	}
	public function is_tax() {
		return ! empty( $this->flags['is_tax'] );
	}
	public function is_post_type_archive() {
		return ! empty( $this->flags['is_post_type_archive'] );
	}
	public function get( $key ) {
		return 'post_type' === $key ? $this->types : '';
	}
	public function get_queried_object() {
		return $this->object;
	}
}

// A members archive filtered by a category on the list is still the members'.
$saved_query                     = $GLOBALS['wp_the_query'];
$saved_permalink                 = isset( $GLOBALS['permalink'] ) ? $GLOBALS['permalink'] : null;
$GLOBALS['wp_the_query']         = new Flag_Query();
$GLOBALS['wp_the_query']->flags  = array(
	'is_category'          => true,
	'is_post_type_archive' => true,
);
$GLOBALS['wp_the_query']->object = (object) array(
	'taxonomy' => 'category',
	'name'     => 'Private clients',
);
$GLOBALS['wp_the_query']->types  = array( 'member' );
$_SERVER['REQUEST_URI']          = '/category/private-clients/?post_type=member';
check( 'a category archive of an excluded post type keeps no term name', blueworx_slow_request_label(), '/category/*?post_type' );
$GLOBALS['wp_the_query']->flags = array( 'is_category' => true );
check( 'even when WordPress sets no archive flag for the type', blueworx_slow_request_label(), '/category/*?post_type' );
$GLOBALS['wp_the_query']->types = array( 'post', 'member' );
check( 'or the request names several types', blueworx_slow_request_label(), '/category/*?post_type' );
$GLOBALS['wp_the_query']->flags = array(
	'is_category'          => true,
	'is_post_type_archive' => true,
);
$GLOBALS['wp_the_query']->types = array( 'post' );
check( 'a category archive of an ordinary post type keeps its address', blueworx_slow_request_label(), '/category/private-clients/?post_type' );

// An excluded taxonomy's word is only a route when a term follows it.
$GLOBALS['taxonomies']           = array( 'brand' => 'brand' );
$GLOBALS['wp_the_query']         = new Flag_Query();
$GLOBALS['wp_the_query']->flags  = array( 'is_singular' => true );
$GLOBALS['wp_the_query']->object = (object) array( 'post_type' => 'product' );
$GLOBALS['permalink']            = '/product/brand/';
$_SERVER['REQUEST_URI']          = '/product/brand/';
check( 'a product that happens to be called brand keeps its address', blueworx_slow_request_label(), '/product/brand/' );
$GLOBALS['permalink']   = '/product/brand/acme/';
$_SERVER['REQUEST_URI'] = '/product/brand/acme/';
check( 'but one whose own address names a term of it is not kept', blueworx_slow_request_label(), '/product/*' );
$GLOBALS['permalink']                               = '/news/category/private-clients/updates/';
$_SERVER['REQUEST_URI']                             = '/news/category/private-clients/updates/';
$GLOBALS['taxonomies']                              = array( 'category' => 'category' );
$GLOBALS['filters']['blueworx_slow_log_taxonomies'] = function () {
	return array( 'post_tag' );
};
check( 'a post whose own address holds an excluded category name is not kept', blueworx_slow_request_label(), '/*' );
unset( $GLOBALS['filters']['blueworx_slow_log_taxonomies'] );
$GLOBALS['taxonomies']           = array( 'brand' => 'brand' );
$GLOBALS['wp_the_query']         = new Flag_Query();
$GLOBALS['wp_the_query']->flags  = array( 'is_tax' => true );
$GLOBALS['wp_the_query']->object = (object) array( 'taxonomy' => 'brand' );
$_SERVER['REQUEST_URI']          = '/brand/acme/';
check( 'but a term of the excluded taxonomy is not kept', blueworx_slow_request_label(), '/*' );
$GLOBALS['taxonomies'] = array();

// A static front page keeps its comment pages.
$GLOBALS['wp_the_query']         = new Flag_Query();
$GLOBALS['wp_the_query']->flags  = array(
	'is_front_page' => true,
	'is_singular'   => true,
);
$GLOBALS['wp_the_query']->object = (object) array( 'post_type' => 'page' );
$GLOBALS['permalink']            = '/';
$_SERVER['REQUEST_URI']          = '/comment-page-2/';
check( 'a static home page keeps its comment page', blueworx_slow_request_label(), '/comment-page-2/' );
$_SERVER['REQUEST_URI'] = '/';
check( 'and its own address', blueworx_slow_request_label(), '/' );
$_SERVER['REQUEST_URI'] = '/jane-doe/';
check( 'but a name WordPress called the home page is not kept', blueworx_slow_request_label(), '/*' );
$GLOBALS['wp_the_query'] = $saved_query;
$GLOBALS['permalink']    = $saved_permalink;

// An author's archive filtered by a category or tag is still the author's.
$GLOBALS['wp_the_query']->tax_query = null;
$GLOBALS['wp_the_query']->author    = true;
$_SERVER['REQUEST_URI']             = '/author/jane-doe/?cat=1';
check( 'an author archive filtered by a category does not keep the name', blueworx_slow_request_label(), '/author/*?cat' );
$GLOBALS['wp_the_query']->author = false;

// A post type archive answers to the list of content types.
$GLOBALS['wp_the_query'] = new class() {
	public function is_front_page() {
		return false;
	}
	public function is_home() {
		return false;
	}
	public function is_date() {
		return false;
	}
	public function is_singular() {
		return false;
	}
	/**
	 * Whether WordPress also calls this an author archive.
	 *
	 * @var bool
	 */
	public $author = false;
	/**
	 * The post type the request asks for.
	 *
	 * @var string
	 */
	public $type = 'product';
	public function is_author() {
		return $this->author;
	}
	public function is_post_type_archive() {
		return true;
	}
	public function is_category() {
		return false;
	}
	public function is_tag() {
		return false;
	}
	public function is_tax() {
		return false;
	}
	public function get( $name ) {
		return 'post_type' === $name ? $this->type : '';
	}
	public function get_queried_object() {
		return (object) array( 'name' => $this->type );
	}
};
$_SERVER['REQUEST_URI']  = '/shop/';
check( 'a product archive is kept', blueworx_slow_request_label(), '/shop/' );
$GLOBALS['wp_the_query']->type = 'member';
$_SERVER['REQUEST_URI']        = '/members/';
check( 'a members archive is not site content', blueworx_slow_request_label(), '/*' );
$GLOBALS['wp_the_query']->type   = 'product';
$GLOBALS['wp_the_query']->author = true;
$_SERVER['REQUEST_URI']          = '/author/jane-doe/?post_type=product';
check( 'an author archive of a post type does not keep the name', blueworx_slow_request_label(), '/author/*?post_type' );
unset( $GLOBALS['wp_the_query'] );
$GLOBALS['did_wp']      = 0;
$GLOBALS['did_wp']      = 0;
$_SERVER['REQUEST_URI'] = '/orders/4400123987/';
check( 'a long number is not kept', blueworx_slow_request_label(), '/*' );
$_SERVER['REQUEST_URI']                      = '/wp-json/x/v1/lookup/some-person/details';
$GLOBALS['filters']['blueworx_request_kind'] = function () {
	return 'rest';
};
check( 'an API route is cut to its first pieces', blueworx_slow_request_label(), '/wp-json/x/v1' );
$GLOBALS['filters']['blueworx_request_kind'] = function () {
	return 'front';
};
$_SERVER['REQUEST_URI']                      = '/wp-admin/admin-ajax.php?action=Heartbeat&nonce=zzz';
$GLOBALS['filters']['blueworx_request_kind'] = function () {
	return 'ajax';
};
check( 'what an AJAX call is for is kept', blueworx_slow_request_label(), '/wp-admin/admin-ajax.php?action=heartbeat&nonce' );
$GLOBALS['filters']['blueworx_request_kind'] = function () {
	return 'front';
};
$_SERVER['REQUEST_URI']                      = '/?wc-ajax=get_refreshed_fragments';
check( 'and the cart check', blueworx_slow_request_label(), '/?wc-ajax=get_refreshed_fragments' );
$_SERVER['REQUEST_URI'] = '/' . str_repeat( 'a', 500 );
$GLOBALS['did_wp']      = 1;
$GLOBALS['is_404']      = true;
$_SERVER['REQUEST_URI'] = '/anything-somebody-typed/';
check( 'a page that does not exist is not named', blueworx_slow_request_label(), '(page not found)' );
$GLOBALS['is_404']      = false;
$GLOBALS['is_search']   = true;
$_SERVER['REQUEST_URI'] = '/?s=private';
check( 'a search WordPress recognises is not named', blueworx_slow_request_label(), '(search)' );
$GLOBALS['is_search']   = false;
$GLOBALS['did_wp']      = 0;
$_SERVER['REQUEST_URI'] = '/' . str_repeat( 'a', 500 );
check( 'a very long segment is not kept', blueworx_slow_request_label(), '/*' );

check( 'the default threshold is two seconds', blueworx_slow_threshold(), 2.0 );
update_option( 'blueworx_slow_threshold', '0.01' );
check( 'it cannot be set to log everything', blueworx_slow_threshold(), 0.2 );
update_option( 'blueworx_slow_threshold', '1.5' );
check( 'a chosen threshold is used', blueworx_slow_threshold(), 1.5 );

$_SERVER['REQUEST_URI']        = '/slow/';
$GLOBALS['did_wp']             = 1;
$GLOBALS['singular']           = true;
$GLOBALS['post_type']          = 'page';
$GLOBALS['permalink']          = '/slow/';
$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 3;
blueworx_speed_monitor_shutdown();
check( 'a slow page is logged', count( blueworx_slow_log_entries() ), 1 );
check( 'with its address', blueworx_slow_log_entries()[0]['path'], '/slow/' );
$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 0.1;
blueworx_speed_monitor_shutdown();
check( 'a quick page is not', count( blueworx_slow_log_entries() ), 1 );

$GLOBALS['switches']['slow_log'] = false;
$_SERVER['REQUEST_TIME_FLOAT']   = microtime( true ) - 5;
blueworx_speed_monitor_shutdown();
check( 'switched off, nothing more is logged', count( blueworx_slow_log_entries() ), 1 );
$GLOBALS['switches'] = array();

for ( $i = 0; $i < 250; $i++ ) {
	blueworx_slow_log_write(
		array(
			'v'    => 7,
			't'    => time(),
			'ms'   => 1000 + $i,
			'path' => '/p' . ( $i % 5 ) . '/',
		)
	);
}
check( 'the log is capped', count( blueworx_slow_log_entries() ), 200 );
$pages = blueworx_slow_log_by_page( 7, 3 );
check( 'summarised by page, three at most', count( $pages ), 3 );
check( 'worst first', $pages[0]['worst'] >= $pages[1]['worst'], true );

echo "\nWhat an earlier version saved is cut back\n";
$GLOBALS['options']['blueworx_slow_log'] = array(
	array(
		't'    => time(),
		'ms'   => 3000,
		'path' => '/members/jane-doe/?x=1',
	),
	array(
		't'    => time(),
		'ms'   => 3000,
		'path' => '/search/jane.doe@example.com/',
	),
	array(
		't'    => time(),
		'ms'   => 3000,
		'path' => '/',
	),
	array(
		'v'    => 2,
		't'    => time(),
		'ms'   => 3000,
		'path' => '/members/john-smith/',
	),
	array(
		'v'    => 2,
		't'    => time(),
		'ms'   => 3000,
		'path' => '/john-smith/',
	),
	array(
		'v'    => 3,
		't'    => time(),
		'ms'   => 3000,
		'path' => '/jane.doe/',
	),
	array(
		'v'    => 4,
		't'    => time(),
		'ms'   => 3000,
		'path' => '/members/jane-doe/',
	),
	array(
		'v'    => 7,
		't'    => time(),
		'ms'   => 3000,
		'path' => '/shop/blue-shirt/',
	),
	array(
		'v'    => 5,
		't'    => time(),
		'ms'   => 3000,
		'path' => '/category/private-clients/',
	),
	array(
		'v'    => 6,
		't'    => time(),
		'ms'   => 3000,
		'path' => '/news/category/private-clients/updates/',
	),
);
$paths                                   = array_column( blueworx_slow_log_entries(), 'path' );
check( 'a name in a profile address goes', $paths[0], '/*' );
check( 'a saved search goes', $paths[1], '(search)' );
check( 'the home page stays', $paths[2], '/' );
check( 'a name kept by the version before the last is cut back too', $paths[3], '/*' );
check( 'also at the root', $paths[4], '/*' );
check( 'a name the last build saved is cut back too', $paths[5], '/*' );
check( 'an address the build before this one saved is cut back as well', $paths[6], '/*' );
check( 'a current entry is untouched', $paths[7], '/shop/blue-shirt/' );
check( 'a category address the last build saved is cut back, in case the site keeps those out', $paths[8], '/category/*' );
check( 'a full address the previous build saved is cut back', $paths[9], '/*' );
check( 'a known first piece survives the cut', blueworx_slow_generalise_path( '/shop/blue-shirt/' ), '/shop/*' );
check( 'a name with a dot is cut', blueworx_slow_generalise_path( '/jane.doe/' ), '/*' );
check( 'a name in front of a feed is cut', blueworx_slow_generalise_path( '/jane-doe/feed/' ), '/*' );
$GLOBALS['options']['blueworx_slow_log'] = array();

echo "\nThe object cache is judged on use, not on being switched on\n";
$s = array(
	'requests' => 40,
	'hits'     => 900,
	'misses'   => 100,
	'kept'     => 30,
	'lost'     => 2,
);
check( 'well used', blueworx_object_cache_verdict( 'persistent', $s )['rate'], 90 );
check( 'well used says so', 0 === strpos( blueworx_object_cache_verdict( 'persistent', $s )['text'], 'Working' ), true );
$s['hits']   = 300;
$s['misses'] = 300;
check( 'half used', 0 === strpos( blueworx_object_cache_verdict( 'persistent', $s )['text'], 'Partly used' ), true );
$s['hits']   = 10;
$s['misses'] = 400;
check( 'on but barely used', 0 === strpos( blueworx_object_cache_verdict( 'persistent', $s )['text'], 'On but barely used' ), true );
$s['kept'] = 0;
$s['lost'] = 12;
check( 'on but nothing survives between pages', false !== strpos( blueworx_object_cache_verdict( 'persistent', $s )['text'], 'survives from one page' ), true );
$s['kept'] = 3;
$s['lost'] = 11;
check( 'a note rarely found again is not called healthy', false !== strpos( blueworx_object_cache_verdict( 'persistent', $s )['text'], 'not holding on to much' ), true );
$s['kept'] = 9;
$s['lost'] = 1;
check( 'a note found again means it is shared', false === strpos( blueworx_object_cache_verdict( 'persistent', $s )['text'], 'survives from one page' ), true );
$s['kept']    = 1;
$s['lost']    = 2;
$verdict_text = blueworx_object_cache_verdict( 'persistent', $s )['text'];
check( 'hits without enough proof the cache is shared are not called working', false === strpos( $verdict_text, 'Working' ) && false !== strpos( $verdict_text, 'whether it is shared' ), true );
$s['kept']     = 30;
$s['lost']     = 2;
$s['requests'] = 2;
check( 'too few pages to say', false !== strpos( blueworx_object_cache_verdict( 'persistent', $s )['text'], 'Too few' ), true );
$s['requests'] = 40;
$s['hits']     = 0;
$s['misses']   = 0;
check( 'a cache that reports nothing is not called unused', false !== strpos( blueworx_object_cache_verdict( 'persistent', $s )['text'], 'cannot be measured' ), true );
check( 'no cache at all', 0 === strpos( blueworx_object_cache_verdict( 'none', $s )['text'], 'No object cache' ), true );
check( 'a cache file that is not in use', false !== strpos( blueworx_object_cache_verdict( 'drop-in', $s )['text'], 'not using it' ), true );

$GLOBALS['wp_object_cache'] = (object) array(
	'cache_hits'   => 8,
	'cache_misses' => 2,
);
check(
	'the counts are read from the cache',
	blueworx_object_cache_counts(),
	array(
		'hits'   => 8,
		'misses' => 2,
	)
);
$GLOBALS['wp_object_cache'] = new stdClass();
check( 'a cache without counts is unknown, not zero', blueworx_object_cache_counts(), null );

echo "\nOne request in twenty is sampled, only for ordinary page views\n";
$GLOBALS['wp_object_cache'] = (object) array(
	'cache_hits'   => 8,
	'cache_misses' => 2,
);
unset( $GLOBALS['options']['blueworx_speed_sample'] );
$_SERVER['REQUEST_METHOD']     = 'GET';
$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 0.5;
$GLOBALS['rand']               = 1;
blueworx_speed_monitor_shutdown();
$sample = blueworx_speed_sample();
check( 'a sampled page is counted', $sample['requests'], 1 );
check( 'with its hits and misses', array( $sample['hits'], $sample['misses'] ), array( 8, 2 ) );
$GLOBALS['rand'] = 7;
blueworx_speed_monitor_shutdown();
check( 'an unsampled page is not', blueworx_speed_sample()['requests'], 1 );
$GLOBALS['rand']                     = 1;
$GLOBALS['switches']['speed_report'] = false;
blueworx_speed_monitor_shutdown();
check( 'report off: no sampling', blueworx_speed_sample()['requests'], 1 );
$GLOBALS['switches'] = array();

echo "\nA cached answer is not called uncached\n";
check( 'a hit header', blueworx_response_looks_cached( array( 'X-Cache' => 'HIT' ) ), true );
check( 'Cloudflare', blueworx_response_looks_cached( array( 'cf-cache-status' => 'HIT' ) ), true );
check( 'an age', blueworx_response_looks_cached( array( 'Age' => '12' ) ), true );
check(
	'a miss',
	blueworx_response_looks_cached(
		array(
			'X-Cache' => 'MISS',
			'Age'     => '0',
		)
	),
	false
);
check( 'no cache headers', blueworx_response_looks_cached( array() ), false );

echo "\nThe weekly clear-out removes only what is old and finished\n";

/**
 * A database that records what it was asked and hands back prepared batches.
 */
class Fake_Wpdb {
	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';
	/**
	 * Every query run.
	 *
	 * @var string[]
	 */
	public $queries = array();
	/**
	 * Ids to hand back, one list per select.
	 *
	 * @var array[]
	 */
	public $batches = array();
	/**
	 * Whether deletes fail.
	 *
	 * @var bool
	 */
	public $fail = false;
	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}
	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$query = str_replace( array( '%s', '%d' ), '?', $query );
		foreach ( $args as $arg ) {
			$query = preg_replace( '/\?/', is_int( $arg ) ? (string) $arg : "'" . $arg . "'", $query, 1 );
		}
		return $query;
	}
	public function get_var( $query ) {
		return 0 === strpos( $query, 'SHOW TABLES LIKE' ) ? str_replace( array( "SHOW TABLES LIKE '", "'", '\\' ), '', $query ) : null;
	}
	public function get_col( $query ) {
		$this->queries[] = $query;
		return ( $this->batches ? array_shift( $this->batches ) : array() );
	}
	public function query( $query ) {
		$this->queries[] = $query;
		if ( $this->fail ) {
			return false;
		}
		return preg_match( '/IN \(([^)]*)\)/', $query, $m ) ? count( explode( ',', $m[1] ) ) : 3;
	}
}

$wpdb          = new Fake_Wpdb();
$wpdb->batches = array( array( 1, 2, 3 ), array( 4, 5 ), array( 6 ) );
$result        = blueworx_cleanup_run( 20 );
$all           = implode( "\n", $wpdb->queries );
foreach ( array( 'complete', 'canceled', 'failed' ) as $status ) {
	check( 'old jobs are selected: ' . $status, strpos( $all, "status = '" . $status . "'" ) !== false, true ); // phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- Reads better this way round.
}
check( 'only those older than the retention time', substr_count( $all, 'last_attempt_gmt <' ) >= 3, true );
check( 'never a job still waiting or running', false === strpos( $all, "'pending'" ) && false === strpos( $all, "'in-progress'" ), true );
check( 'the logs of removed jobs go too', false !== strpos( $all, 'DELETE FROM wp_actionscheduler_logs WHERE action_id IN (1,2,3)' ), true );
check( 'then the jobs', false !== strpos( $all, 'DELETE FROM wp_actionscheduler_actions WHERE action_id IN (1,2,3)' ), true );
check( 'the count is of jobs', $result['jobs'], 6 );
check( 'expired sessions are removed', false !== strpos( $all, 'DELETE FROM wp_woocommerce_sessions WHERE session_expiry <' ), true );
check( 'a job that never ran is not guessed at', false === strpos( $all, 'scheduled_date_gmt' ), true );
check( 'finished jobs are aged from when they last ran', false !== strpos( $all, 'last_attempt_gmt <' ), true );
check( 'the run is remembered', get_option( 'blueworx_cleanup_last' )['jobs'], 6 );
check( 'a run that finished leaves nothing to carry on', $result['more'], false );

check( 'finished jobs keep 14 days', blueworx_cleanup_retention_days( 'complete' ), 14 );
check( 'failed ones 30', blueworx_cleanup_retention_days( 'failed' ), 30 );

$wpdb          = new Fake_Wpdb();
$wpdb->batches = array( array( 1 ) );
$result        = blueworx_cleanup_action_scheduler( microtime( true ) - 1 );
check( 'a run out of time says there is more', $result['more'], true );
check( 'and has deleted nothing', $result['deleted'], 0 );

$wpdb          = new Fake_Wpdb();
$wpdb->fail    = true;
$wpdb->batches = array( array( 1, 2 ), array( 1, 2 ), array( 1, 2 ) );
$result        = blueworx_cleanup_action_scheduler( microtime( true ) + 20 );
check( 'a failing delete stops at once', count( $wpdb->queries ), 2 );
check( 'nothing is counted as removed', $result['deleted'], 0 );
check( 'and no retry is booked', $result['more'], false );
check( 'and the stop is reported', $result['error'], true );

echo "\nThe report\n";
$data = array(
	'site'     => 'https://example.test/',
	'name'     => 'Example & Co',
	'plugin'   => '1.104.0',
	'wp'       => '6.9',
	'php'      => '8.3',
	'woo'      => '10.0',
	'home'     => array(
		'seconds' => 6.64,
		'status'  => 200,
	),
	'cache'    => 'persistent',
	'sample'   => array(
		'requests' => 40,
		'hits'     => 10,
		'misses'   => 400,
		'ms'       => 40 * 5000,
		'queries'  => 40 * 300,
	),
	'verdict'  => blueworx_object_cache_verdict(
		'persistent',
		array(
			'requests' => 40,
			'hits'     => 10,
			'misses'   => 400,
			'kept'     => 30,
			'lost'     => 2,
		)
	),
	'slow'     => array(
		array(
			'path'    => '/',
			'worst'   => 9100,
			'average' => 6000,
			'count'   => 12,
		),
	),
	'slow_n'   => 12,
	'slow_sec' => 2.0,
	'log_on'   => true,
	'cleanup'  => array(
		'time'     => 1700000000,
		'jobs'     => 5000,
		'sessions' => 200,
		'more'     => false,
	),
);
$text = blueworx_speed_report_text( $data );
check( 'names the site', false !== strpos( $text, "Weekly speed report: Example & Co\nhttps://example.test/" ), true );
check( 'says it is a fresh request, and not to trust a quick one blindly', false !== strpos( $text, 'HOME PAGE, FRESH REQUEST' ) && false !== strpos( $text, 'treat a very quick result with care' ), true );
check( 'gives the uncached home page time', false !== strpos( $text, '6.6 seconds' ), true );
check( 'says the cache is on but barely used', false !== strpos( $text, 'On but barely used' ), true );
check( 'gives the hit rate', false !== strpos( $text, '2%' ), true );
check( 'lists the slowest page', false !== strpos( $text, '/  worst 9.1s, average 6.0s, 12 times' ), true );
check( 'says what the last clear-out did', false !== strpos( $text, '5000 old background jobs and 200 expired visitor sessions' ), true );
$data['home'] = array(
	'seconds' => 0.2,
	'status'  => 200,
	'cached'  => true,
);
check( 'a cached answer is not labelled uncached', false !== strpos( blueworx_speed_report_text( $data ), 'Not a clean uncached measurement' ), true );
$data['home'] = array(
	'seconds' => 0.2,
	'status'  => 500,
	'cached'  => false,
);
check( 'an error page is not either', false !== strpos( blueworx_speed_report_text( $data ), 'not a successful page' ), true );
$data['home'] = null;
check( 'an unreachable home page is said so', false !== strpos( blueworx_speed_report_text( $data ), 'Could not be measured' ), true );
$data['log_on'] = false;
check( 'a switched-off log is said so', false !== strpos( blueworx_speed_report_text( $data ), 'switched off' ), true );

check( 'the report goes to BlueWorx by default', blueworx_speed_report_recipient(), 'info@blueworx.com' );
update_option( 'blueworx_speed_report_to', 'ops@example.test' );
check( 'or where the site says', blueworx_speed_report_recipient(), 'ops@example.test' );
update_option( 'blueworx_speed_report_to', 'not an address' );
check( 'a bad address falls back', blueworx_speed_report_recipient(), 'info@blueworx.com' );

finish();
