<?php
/**
 * Speed defaults: three cheap wins every site gets, each with its own switch.
 *
 * - `guest_cart_checks`     Stops cart checks for visitors who cannot buy.
 * - `surerank_link_checks`  Pauses SureRank's broken-link checks.
 * - `crawl_delay`           Asks aggressive crawlers to slow down in robots.txt.
 *
 * All three came from slow sites (Forum, Hiraste) where uncached requests, the
 * ones the page cache never answers, were mostly work nobody needed.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the current visitor cannot buy anything on this store.
 *
 * A signed-out visitor on a store that asks everyone to sign in first has no
 * cart to look at, so every check of "what is in my cart" is wasted work. On a
 * store that allows guest checkout, or lets people create an account while
 * paying, a guest can buy and nothing here applies.
 *
 * Nothing is assumed beyond WooCommerce's own two settings. A site that gates
 * buying some other way (a membership, say) says so with the
 * `blueworx_guest_can_buy` filter.
 *
 * @return bool True for a signed-out visitor who cannot buy.
 */
function blueworx_guest_cannot_buy() {
	if ( ! class_exists( 'WooCommerce' ) || is_user_logged_in() ) {
		return false;
	}

	$can_buy = 'yes' === get_option( 'woocommerce_enable_guest_checkout', 'yes' )
		|| 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' );

	return ! apply_filters( 'blueworx_guest_can_buy', $can_buy );
}

/**
 * Whether this request is WooCommerce's cart check.
 *
 * It is the "refresh the little cart" call WooCommerce's script sends on every
 * page, and it is never cached.
 *
 * @return bool True for a wc-ajax=get_refreshed_fragments request.
 */
function blueworx_is_cart_check_request() {
	$action = isset( $_GET['wc-ajax'] ) ? sanitize_key( wp_unslash( $_GET['wc-ajax'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only compared with a fixed name; nothing is read from it or output.

	return 'get_refreshed_fragments' === $action;
}

/**
 * Answers a guest's cart check with an empty cart and builds nothing.
 *
 * Runs on init, once the sign-in cookie can be read but before WooCommerce
 * builds a cart, a session or the mini cart, which is where the time goes.
 *
 * @return void
 */
function blueworx_guest_cart_check_answer() {
	if ( ! blueworx_is_cart_check_request() || ! blueworx_guest_cannot_buy() ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- The name page cache plugins look for.
	}

	nocache_headers();
	wp_send_json(
		array(
			'fragments' => new stdClass(),
			'cart_hash' => '',
		)
	);
}

/**
 * Stops WooCommerce loading its cart-checking script for visitors who cannot buy.
 *
 * Only dequeued, never deregistered: another script may list it as something
 * it needs, and WordPress drops a script whose needs are missing. Where one
 * does, the script loads anyway and its request is answered cheaply above.
 *
 * @return void
 */
function blueworx_guest_cart_script_off() {
	if ( blueworx_guest_cannot_buy() ) {
		wp_dequeue_script( 'wc-cart-fragments' );
	}
}

/**
 * The route SureRank's broken-link check answers on, whichever version it is.
 *
 * @param string $route Request route, for example /surerank/v1/checks/broken-link.
 * @return bool True for the broken-link check.
 */
function blueworx_is_surerank_link_check_route( $route ) {
	return 1 === preg_match( '#^/surerank/v\d+/checks/broken-link/?$#', (string) $route );
}

/**
 * Answers SureRank's broken-link check without making it.
 *
 * Each check is a request out to the other site, made while a visitor's page
 * waits. The answer says "ignored", which SureRank takes to mean there is
 * nothing to record, so no page is marked broken or fixed by it. Every other
 * route, and every other SureRank check, is left alone.
 *
 * @param mixed           $result  Response so far; null means nobody has answered.
 * @param WP_REST_Server  $server  The REST server.
 * @param WP_REST_Request $request The request.
 * @return mixed The paused answer for a link check, otherwise $result unchanged.
 */
function blueworx_surerank_link_check_pause( $result, $server, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Part of the filter signature.
	if ( null !== $result || 'POST' !== $request->get_method() || ! blueworx_is_surerank_link_check_route( $request->get_route() ) ) {
		return $result;
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'ignored' => true,
			'message' => __( 'Link checks are paused on this site.', 'blueworx-labs-wordpress' ),
		)
	);
}

/**
 * Crawlers that read robots.txt and are known to hit sites hard.
 *
 * Search engines people want to be found by (Google, Bing) are not here, and
 * never should be: slowing them down slows how fast changes reach search.
 *
 * @return string[] User-agent names.
 */
function blueworx_crawl_delay_agents() {
	$agents = array(
		'AhrefsBot',
		'SemrushBot',
		'MJ12bot',
		'DotBot',
		'PetalBot',
		'Bytespider',
		'ClaudeBot',
		'GPTBot',
		'Amazonbot',
		'meta-externalagent',
		'SeekportBot',
		'DataForSeoBot',
		'BLEXBot',
		'Barkrowler',
	);

	$agents = apply_filters( 'blueworx_crawl_delay_agents', $agents );

	return array_values( array_filter( array_map( 'trim', (array) $agents ), 'strlen' ) );
}

/**
 * How many seconds those crawlers are asked to wait between requests.
 *
 * @return int Seconds, 1 to 60.
 */
function blueworx_crawl_delay_seconds() {
	return max( 1, min( 60, (int) apply_filters( 'blueworx_crawl_delay_seconds', 10 ) ) );
}

/**
 * Adds a slow-down request for aggressive crawlers to robots.txt.
 *
 * One group naming every crawler, so the file stays short. A crawler the file
 * already gives its own group is left to that group. A site that has said it
 * does not want to be indexed still gets it: slowing a crawler down does no
 * harm there, and the setting only changes what search engines are told.
 *
 * Runs before the crawler rules (priority 30), so the shop pages they keep
 * crawlers off are added to this group as well. A crawler reads only the group
 * that names it, and ignores the one for everybody.
 *
 * @param string $output    Generated robots.txt content.
 * @param bool   $is_public Whether the site is set to be indexed.
 * @return string robots.txt content.
 */
function blueworx_crawl_delay_filter( $output, $is_public = true ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Part of the filter signature; see above for why it is not acted on.
	$output = (string) $output;
	$named  = array();

	if ( preg_match_all( '/^\s*user-agent:\s*(\S+)/im', $output, $matches ) ) {
		$named = array_map( 'strtolower', $matches[1] );
	}

	$agents = array();

	foreach ( blueworx_crawl_delay_agents() as $agent ) {
		if ( ! in_array( strtolower( $agent ), $named, true ) ) {
			$agents[] = $agent;
		}
	}

	if ( empty( $agents ) ) {
		return $output;
	}

	$group = '';

	foreach ( $agents as $agent ) {
		$group .= 'User-agent: ' . $agent . "\n";
	}

	$group .= 'Crawl-delay: ' . blueworx_crawl_delay_seconds() . "\n";

	return rtrim( $output ) . "\n\n" . $group;
}

/**
 * Hooks each speed default that is switched on.
 *
 * @return void
 */
function blueworx_speed_defaults_hooks() {
	if ( blueworx_feature_enabled( 'guest_cart_checks' ) ) {
		add_action( 'init', 'blueworx_guest_cart_check_answer', 1 );
		add_action( 'wp_enqueue_scripts', 'blueworx_guest_cart_script_off', 100 );
	}

	if ( blueworx_feature_enabled( 'surerank_link_checks' ) ) {
		add_filter( 'rest_pre_dispatch', 'blueworx_surerank_link_check_pause', 10, 3 );
	}

	if ( blueworx_feature_enabled( 'crawl_delay' ) ) {
		// Before the crawler rules (30) and after the robots.txt editor (20).
		add_filter( 'robots_txt', 'blueworx_crawl_delay_filter', 25, 2 );
	}
}
blueworx_speed_defaults_hooks();
