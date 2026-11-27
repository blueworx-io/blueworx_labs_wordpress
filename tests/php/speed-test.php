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
check( 'guest cart answer hooked once every plugin has had its say about the cookie name', hooked( 'wp_loaded', 'blueworx_guest_cart_check_answer' ), true );
check( 'and not earlier, before a late cookie name is known', hooked( 'init', 'blueworx_guest_cart_check_answer' ), false );
check( 'and before WooCommerce answers its own ajax calls (priority 10)', $GLOBALS['hooks']['wp_loaded'][0][1] < 10, true );
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
foreach ( array( 'sbjs_session', 'sbjs_current', '_hjSession_123', '_ga_ABC123', '_fbp' ) as $cookie ) {
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
$_SERVER['REQUEST_URI'] = '/members/about-us/';
check( "the site's own content keeps its address", blueworx_slow_request_label(), '/members/about-us/' );
$GLOBALS['post_type']   = 'member';
$_SERVER['REQUEST_URI'] = '/members/jane-doe/';
check( 'a member post type keeps no name even though WordPress found it', blueworx_slow_request_label(), '/*' );
$GLOBALS['post_type']   = 'product';
$_SERVER['REQUEST_URI'] = '/shop/blue-shirt/';
check( 'a product keeps its address', blueworx_slow_request_label(), '/shop/blue-shirt/' );
$GLOBALS['post_type']   = 'page';
$GLOBALS['post_type']   = 'member';
$_SERVER['REQUEST_URI'] = '/jane-doe/';
check( 'a member at the root of the site is not named either', blueworx_slow_request_label(), '/*' );
$GLOBALS['post_type']   = 'page';
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
		return false;
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
		return null;
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
			'v'    => 4,
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
		'path' => '/shop/blue-shirt/',
	),
);
$paths                                   = array_column( blueworx_slow_log_entries(), 'path' );
check( 'a name in a profile address goes', $paths[0], '/*' );
check( 'a saved search goes', $paths[1], '(search)' );
check( 'the home page stays', $paths[2], '/' );
check( 'a name kept by the version before the last is cut back too', $paths[3], '/*' );
check( 'also at the root', $paths[4], '/*' );
check( 'a name the last build saved is cut back too', $paths[5], '/*' );
check( 'a current entry is untouched', $paths[6], '/shop/blue-shirt/' );
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
