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
 * Whether this visitor has a cart in progress.
 *
 * WooCommerce marks a visitor who has put something in a cart with a cookie for
 * the item count and a session cookie. Without either there is nothing to ask
 * about, and the same cookies are what page caches already look for.
 *
 * @return bool True when a cart cookie or session cookie came with the request.
 */
function blueworx_visitor_has_cart_cookie() {
	// A store can rename its session cookie, with the filter WooCommerce itself
	// reads it from. Asked for by that name as well as by the usual start of it.
	$session_cookie = defined( 'COOKIEHASH' ) ? (string) apply_filters( 'woocommerce_cookie', 'wp_woocommerce_session_' . COOKIEHASH ) : '';  // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter, read so a renamed cookie is still found.

	// This runs before most plugins have had their say, so a cookie renamed by a
	// plugin that registers its filter later is not known by name. Anything that
	// looks like a cart or a session is kept, which costs a cart check at worst
	// and never loses a cart.
	foreach ( array_keys( $_COOKIE ) as $name ) {
		$name = (string) $name;
		$low  = strtolower( $name );

		if ( ( '' !== $session_cookie && $session_cookie === $name ) || false !== strpos( $low, 'session' ) || false !== strpos( $low, 'cart' ) ) {
			return true;
		}

		if ( 0 === strpos( $low, 'woocommerce_' ) && 'woocommerce_recently_viewed' !== $low ) {
			return true;
		}
	}

	return false;
}

/**
 * Answers a guest's cart check with an empty cart and builds nothing.
 *
 * Only for a signed-out visitor who cannot buy and has no cart in progress:
 * the true answer for them is an empty cart. Anybody who has put something in
 * a cart, or who can, is asked as before.
 *
 * Runs early on init, ahead of WooCommerce's own start-up, so no cart, session
 * or mini cart is built, which is where the time goes.
 *
 * @return void
 */
function blueworx_guest_cart_check_answer() {
	if ( ! blueworx_is_cart_check_request() || ! blueworx_guest_cannot_buy() || blueworx_visitor_has_cart_cookie() ) {
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

	// A name is one word. A line break or a space in one would write a line of
	// its own into robots.txt, so such a name is left out, not cleaned up.
	return array_values(
		array_filter(
			array_map( 'trim', array_map( 'strval', (array) $agents ) ),
			static function ( $agent ) {
				return 1 === preg_match( '/^[A-Za-z0-9._-]+$/', $agent );
			}
		)
	);
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
 * Gets the Allow and Disallow lines of the group for everybody.
 *
 * @param string $output robots.txt content.
 * @return string[] Rule lines, in the order they appear.
 */
function blueworx_robots_everybody_rules( $output ) {
	$rules   = array();
	$in_all  = false;
	$in_head = false;

	foreach ( explode( "\n", str_replace( "\r\n", "\n", (string) $output ) ) as $line ) {
		// A comment can follow any line: "User-agent: * # default".
		$line = trim( (string) preg_replace( '/#.*$/', '', $line ) );

		if ( preg_match( '/^user-agent:\s*(.*)$/i', $line, $match ) ) {
			// Several User-agent lines in a row share the rules that follow.
			$in_all  = $in_head ? ( $in_all || '*' === trim( $match[1] ) ) : '*' === trim( $match[1] );
			$in_head = true;
			continue;
		}

		if ( preg_match( '/^(allow|disallow)\s*:/i', $line ) ) {
			$in_head = false;

			if ( $in_all ) {
				$rules[] = $line;
			}
		}
	}

	return $rules;
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
function blueworx_crawl_delay_filter( $output, $is_public = true ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Part of the filter signature; see above for why it is not acted on.
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

	// A crawler reads only the group that names it, so it would stop obeying the
	// rules the site already has for everybody. Those rules come with it.
	foreach ( blueworx_robots_everybody_rules( $output ) as $rule ) {
		$group .= $rule . "\n";
	}

	return rtrim( $output ) . "\n\n" . $group;
}

/**
 * Hooks each speed default that is switched on.
 *
 * @return void
 */
function blueworx_speed_defaults_hooks() {
	if ( blueworx_feature_enabled( 'guest_cart_checks' ) ) {
		// Before WooCommerce starts its cart and session on init (priority 0).
		add_action( 'init', 'blueworx_guest_cart_check_answer', -1 );
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
