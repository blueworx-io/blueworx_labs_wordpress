<?php
/**
 * Connect Claude: the SureCart record helpers.
 *
 * What is scrubbed, how money and dates are shaped, which record types count as
 * personal, where admin links point, and when personal data is blocked.
 *
 * Run with: php tests/php/mcp-surecart-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stand-ins.

function admin_url( $path = '' ) {
	return 'https://shop.test/wp-admin/' . $path;
}

function wp_date( $format, $time = null ) {
	// A site behind UTC: shifts instants, so a bucket read in site time shows the day before.
	return gmdate( $format, $time + (int) ( $GLOBALS['tz_offset'] ?? 0 ) );
}

function blueworx_readonly_current_user() {
	return $GLOBALS['readonly_user'];
}

function blueworx_readonly_data_allowed( $user ) {
	return (bool) $GLOBALS['data_open'];
}

function wp_strip_all_tags( $text ) {
	return trim( strip_tags( (string) $text ) );
}

class WP_REST_Request {
	public $method;
	public $route;
	public $query = array();

	public function __construct( $method = 'GET', $route = '' ) {
		$this->method = $method;
		$this->route  = $route;
	}

	public function set_query_params( $params ) {
		$this->query = $params;
	}
}

class Fake_Response {
	public $status;
	public $data;
	public $headers;

	public function __construct( $status, $data, $headers = array() ) {
		$this->status  = $status;
		$this->data    = $data;
		$this->headers = $headers;
	}

	public function is_error() {
		return $this->status >= 400;
	}

	public function get_data() {
		return $this->data;
	}

	public function get_headers() {
		return $this->headers;
	}
}

/**
 * What SureCart's REST layer hands back in-process for most types: a model
 * object that only becomes an array when it is JSON-encoded.
 */
class Fake_Model implements JsonSerializable {
	private $attributes;

	public function __construct( $attributes ) {
		$this->attributes = $attributes;
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return $this->attributes;
	}
}

$GLOBALS['sc'] = array( 'calls' => array(), 'routes' => array() );

function rest_do_request( $request ) {
	$GLOBALS['sc']['calls'][] = array( $request->method, $request->route, $request->query );
	$answer = isset( $GLOBALS['sc']['routes'][ $request->route ] ) ? $GLOBALS['sc']['routes'][ $request->route ] : array( 404, array( 'code' => 'rest_no_route', 'message' => 'No route.' ), array() );
	return new Fake_Response( $answer[0], $answer[1], $answer[2] );
}

function last_call() {
	return end( $GLOBALS['sc']['calls'] );
}

function error_of( $result ) {
	return is_wp_error( $result ) ? $result->get_error_code() : 'no error';
}

function message_of( $result ) {
	return is_wp_error( $result ) ? $result->get_error_message() : 'no error';
}

require dirname( __DIR__, 2 ) . '/includes/mcp/surecart.php';

check( 'a token is never returned', blueworx_mcp_surecart_scrub( array( 'object' => 'settings', 'api_token' => 'st_x', 'uninstall' => false ) ), array( 'object' => 'settings', 'uninstall' => false ) );
check( 'nor one buried in a record', blueworx_mcp_surecart_scrub( array( 'processors' => array( array( 'id' => 'p1', 'live_secret_key' => 'sk', 'webhook_signing_secret' => 'wh' ) ) ) ), array( 'processors' => array( array( 'id' => 'p1' ) ) ) );
$shaped = blueworx_mcp_surecart_shape( array( 'id' => 'o1', 'currency' => 'gbp', 'amount_due' => 2400, 'created_at' => 1747498098, 'post' => array( 'ID' => 1 ) ) );
check( 'money reads as money', $shaped['amount_due_display'], '£24.00' );
check( 'the raw amount stays', $shaped['amount_due'], 2400 );
check( 'dates read as dates', $shaped['created_at'], '2025-05-17T16:08:18+00:00' );
check( 'the WordPress copy is dropped', isset( $shaped['post'] ), false );
check( 'yen has no pence', blueworx_mcp_surecart_shape( array( 'currency' => 'jpy', 'amount' => 2400 ) )['amount_display'], '¥2,400' );
check( 'an unlisted currency shows its code', blueworx_mcp_surecart_shape( array( 'currency' => 'chf', 'amount' => 2400 ) )['amount_display'], 'CHF 24.00' );
check( 'the passed currency fills a gap', blueworx_mcp_surecart_shape( array( 'amount' => 500 ), 'usd' )['amount_display'], '$5.00' );
check( 'an order links to its screen', blueworx_mcp_surecart_admin_link( 'orders', 'o1' ), 'https://shop.test/wp-admin/admin.php?page=sc-orders&action=edit&id=o1' );
check( 'no id gives the list screen', blueworx_mcp_surecart_admin_link( 'orders' ), 'https://shop.test/wp-admin/admin.php?page=sc-orders' );
check( 'a type with no screen gives no link', blueworx_mcp_surecart_admin_link( 'clicks', 'c1' ), '' );
check( 'an unknown type gives no link', blueworx_mcp_surecart_admin_link( 'nope', 'c1' ), '' );
check( 'every type says if it is personal', count( array_filter( blueworx_mcp_surecart_types(), function ( $t ) { return ! is_bool( $t['personal'] ); } ) ), 0 );
check( 'customers are personal', blueworx_mcp_surecart_types()['customers']['personal'], true );
check( 'products are not', blueworx_mcp_surecart_types()['products']['personal'], false );
check( 'an unknown type counts as personal', blueworx_mcp_surecart_is_personal( 'nope' ), true );
check( 'orders count as personal', blueworx_mcp_surecart_is_personal( 'orders' ), true );
check( 'products do not', blueworx_mcp_surecart_is_personal( 'products' ), false );
check( 'routes sit under surecart/v1', blueworx_mcp_surecart_types()['orders']['route'], '/surecart/v1/orders' );
$GLOBALS['readonly_user'] = (object) array( 'ID' => 9 ); $GLOBALS['data_open'] = false;
check( 'a read-only account with data closed is blocked', blueworx_mcp_surecart_personal_blocked(), true );
$GLOBALS['data_open'] = true;
check( 'and let through once data is opened', blueworx_mcp_surecart_personal_blocked(), false );
$GLOBALS['readonly_user'] = null;
check( 'an administrator is never blocked', blueworx_mcp_surecart_personal_blocked(), false );
check( 'with SureCart absent it is inactive', blueworx_mcp_surecart_active(), false );
check( 'and the refusal says why', blueworx_mcp_surecart_unavailable()->get_error_code(), 'surecart_inactive' );
check( 'a call without SureCart is refused plainly', error_of( blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products' ) ) ), 'surecart_inactive' );
check( 'settings are refused too', error_of( blueworx_mcp_tool_surecart_get_settings( array() ) ), 'surecart_inactive' );
check( 'and SureCart was never asked', $GLOBALS['sc']['calls'], array() );
check( 'site info says it is off', blueworx_mcp_surecart_site_info()['active'], false );
define( 'SURECART_PLUGIN_FILE', 'x' );
check( 'with SureCart present it is active', blueworx_mcp_surecart_active(), true );
check( 'and nothing refuses', blueworx_mcp_surecart_unavailable(), null );

$product = array( 'id' => 'p1', 'object' => 'product', 'name' => 'Hoodie', 'metrics' => array( 'currency' => 'gbp', 'max_price_amount' => 2400 ), 'created_at' => 1747498098, 'post' => array( 'ID' => 1 ), 'image_srcset' => 'x' );
$GLOBALS['sc']['routes'] = array(
	'/surecart/v1/products'      => array( 200, array( $product ), array( 'X-WP-Total' => '11' ) ),
	'/surecart/v1/products/p1'   => array( 200, $product, array() ),
	'/surecart/v1/orders'        => array( 200, array( array( 'id' => 'o1', 'currency' => 'gbp', 'amount_due' => 2400 ) ), array( 'X-WP-Total' => '1' ) ),
	'/surecart/v1/subscriptions' => array( 200, array(), array( 'X-WP-Total' => '0' ) ),
	'/surecart/v1/settings'      => array( 200, array( 'object' => 'settings', 'api_token' => 'st_x', 'uninstall' => false ), array() ),
	'/surecart/v1/brand'         => array( 200, array( 'object' => 'brand', 'color' => '00ccff', 'dark_color' => '112233', 'theme' => 'light', 'logo_url' => 'https://media.test/logo.svg', 'logo' => array( 'id' => 'm1' ) ), array() ),
	'/surecart/v1/account'       => array( 200, array( 'object' => 'account', 'name' => 'Demo', 'currency' => 'gbp', 'is_connected' => true, 'plan' => array( 'name' => 'Launch' ), 'public_token' => 'pt_x', 'cache_keys' => array( 'a' => 'b' ), 'order_protocol' => 'zzz' ), array() ),
	'/surecart/v1/stats/orders'  => array( 200, array( 'object' => 'stats', 'query' => array( 'currency' => 'gbp', 'interval' => 'month' ), 'data' => array( array( 'interval_at' => 1767225600, 'amount' => 4800, 'average_amount' => 2400, 'count' => 2 ), array( 'interval_at' => 1769904000, 'amount' => 0, 'average_amount' => 0, 'count' => 0 ) ) ), array() ),
);
check( 'every tool says it is read-only', array_unique( array_column( array_column( blueworx_mcp_surecart_tool_definitions(), 'annotations' ), 'readOnlyHint' ) ), array( true ) );
check( 'and none reaches outside the site', array_unique( array_column( array_column( blueworx_mcp_surecart_tool_definitions(), 'annotations' ), 'openWorldHint' ) ), array( false ) );
check( 'and every listed tool has a handler', array_keys( blueworx_mcp_surecart_tool_handlers() ), array_column( blueworx_mcp_surecart_tool_definitions(), 'name' ) );
check( 'site info lists the types', in_array( 'products', blueworx_mcp_surecart_site_info()['record_types'], true ), true );
$list = blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products', 'per_page' => 500 ) );
check( 'per_page is capped at 100', last_call()[2]['per_page'], 100 );
check( 'the request is for the route under surecart/v1', last_call()[1], '/surecart/v1/products' );
check( 'the total comes back', $list['total'], 11 );
check( 'the page comes back', $list['page'], 1 );
check( 'each record links to its screen', $list['records'][0]['admin_link'], 'https://shop.test/wp-admin/admin.php?page=sc-products&action=edit&id=p1' );
check( 'the list links to its screen', $list['admin_link'], 'https://shop.test/wp-admin/admin.php?page=sc-products' );
check( 'records are cleaned', isset( $list['records'][0]['post'] ) || isset( $list['records'][0]['image_srcset'] ), false );
blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products' ) );
check( 'per_page defaults to 20', last_call()[2]['per_page'], 20 );
blueworx_mcp_tool_surecart_list_records( array( 'type' => 'orders', 'search' => 'smith', 'status' => 'paid', 'customer_id' => 'c1' ) );
$q = last_call()[2];
check( 'search maps to query', $q['query'], 'smith' );
check( 'status maps to status[]', $q['status'], array( 'paid' ) );
check( 'customer maps to customer_ids', $q['customer_ids'], array( 'c1' ) );
check( 'no date filter is sent, as none is confirmed', isset( $q['created_at'] ), false );
blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products', 'customer_id' => 'c1' ) );
check( 'a filter a type does not take is dropped', isset( last_call()[2]['customer_ids'] ), false );
check( 'a filter a type does not take is named as ignored', blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products', 'customer_id' => 'c1' ) )['ignored_filters'], array( 'customer_id' ) );
check( 'a filter that applies is not named', isset( blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products', 'search' => 'x' ) )['ignored_filters'] ), false );
check( 'list has no date arguments', array_intersect( array( 'from', 'to' ), array_keys( blueworx_mcp_surecart_tool_definitions()[1]['inputSchema']['properties'] ) ), array() );
$GLOBALS['tz_offset'] = 0;
check( 'a get returns the record with its link', blueworx_mcp_tool_surecart_get_record( array( 'type' => 'products', 'id' => 'p1' ) )['admin_link'], 'https://shop.test/wp-admin/admin.php?page=sc-products&action=edit&id=p1' );
check( 'and asks for that record', last_call()[1], '/surecart/v1/products/p1' );
check( 'money in a record reads as money', blueworx_mcp_tool_surecart_get_record( array( 'type' => 'products', 'id' => 'p1' ) )['metrics']['max_price_amount_display'], '£24.00' );
check( 'a path in an ID is refused', error_of( blueworx_mcp_tool_surecart_get_record( array( 'type' => 'products', 'id' => '../settings' ) ) ), 'invalid_id' );
check( 'an unknown type is refused', error_of( blueworx_mcp_tool_surecart_list_records( array( 'type' => 'users' ) ) ), 'unknown_type' );
$settings = blueworx_mcp_tool_surecart_get_settings( array() );
check( 'settings never carry the token', isset( $settings['settings']['api_token'] ), false );
check( 'settings keep the rest', $settings['settings']['uninstall'], false );
check( 'the brand colours come back', array( $settings['brand']['color'], $settings['brand']['dark_color'], $settings['brand']['theme'], $settings['brand']['logo_url'] ), array( '00ccff', '112233', 'light', 'https://media.test/logo.svg' ) );
check( 'the account name and currency come back', array( $settings['account']['name'], $settings['account']['currency'] ), array( 'Demo', 'gbp' ) );
check( 'the account has no cache keys or tokens', array_intersect( array( 'cache_keys', 'public_token' ), array_keys( $settings['account'] ) ), array() );
check( 'settings link to the screens', array_keys( $settings['links'] ), array( 'settings', 'brand', 'checkout' ) );
$GLOBALS['tz_offset'] = -18000;
$sales = blueworx_mcp_tool_surecart_get_sales( array( 'report' => 'orders', 'from' => '2026-01-01', 'to' => '2026-10-21' ) );
check( 'sales ask for a month by default', last_call()[2]['interval'], 'month' );
check( 'sales ask the stats route', last_call()[1], '/surecart/v1/stats/orders' );
check( 'sales rows read as money', $sales['rows'][0]['amount_display'], '£48.00' );
check( 'and as an average', $sales['rows'][0]['average_amount_display'], '£24.00' );
check( 'and a UTC-midnight bucket keeps its day on a site behind UTC', $sales['rows'][1]['interval_at'], '2026-02-01' );
check( 'and as a date', $sales['rows'][0]['interval_at'], '2026-01-01' );
check( 'sales add up', array( $sales['totals']['count'], $sales['totals']['amount_display'] ), array( 2, '£48.00' ) );
check( 'sales need both dates', error_of( blueworx_mcp_tool_surecart_get_sales( array( 'report' => 'orders', 'from' => '2026-01-01' ) ) ), 'invalid_date' );
check( 'an unknown report is refused', error_of( blueworx_mcp_tool_surecart_get_sales( array( 'report' => 'x', 'from' => '2026-01-01', 'to' => '2026-02-01' ) ) ), 'unknown_report' );
$GLOBALS['sc']['routes']['/surecart/v1/products'] = array( 400, array( 'code' => 'missing_token', 'message' => '<p>Please connect your site to SureCart.</p>' ), array() );
check( 'a store with no connection says so in SureCart\'s words', message_of( blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products' ) ) ), 'Please connect your site to SureCart.' );
// In-process, SureCart hands most types back as model objects, not arrays.
$GLOBALS['sc']['routes']['/surecart/v1/orders']    = array( 200, array( new Fake_Model( array( 'id' => 'o2', 'currency' => 'gbp', 'amount_due' => 1500, 'checkout' => (object) array( 'id' => 'ch1', 'customer' => (object) array( 'name' => 'Ann', 'api_token' => 'x' ) ) ) ) ), array( 'X-WP-Total' => '1' ) );
$GLOBALS['sc']['routes']['/surecart/v1/orders/o2'] = array( 200, new Fake_Model( array( 'id' => 'o2', 'currency' => 'gbp', 'amount_due' => 1500, 'checkout' => (object) array( 'id' => 'ch1', 'webhook_signing_secret' => 'wh' ) ) ), array() );
$objects = blueworx_mcp_tool_surecart_list_records( array( 'type' => 'orders' ) );
check( 'a list of model objects still returns its records', count( $objects['records'] ), 1 );
check( 'and each reads as money', $objects['records'][0]['amount_due_display'] ?? null, '£15.00' );
check( 'and a nested object is cleaned too', isset( $objects['records'][0]['checkout']['customer']['api_token'] ), false );
check( 'and keeps what is safe', $objects['records'][0]['checkout']['customer']['name'] ?? null, 'Ann' );
check( 'the orders list asks for the checkout and its customer', array_intersect( array( 'checkout', 'checkout.customer', 'checkout.line_items' ), (array) ( last_call()[2]['expand'] ?? array() ) ), array( 'checkout', 'checkout.customer', 'checkout.line_items' ) );
$one = blueworx_mcp_tool_surecart_get_record( array( 'type' => 'orders', 'id' => 'o2' ) );
check( 'a single model object comes back as a record', $one['amount_due_display'] ?? null, '£15.00' );
check( 'and is cleaned', isset( $one['checkout']['webhook_signing_secret'] ), false );
check( 'a single order asks for the same detail', in_array( 'checkout.customer', (array) ( last_call()[2]['expand'] ?? array() ), true ), true );
blueworx_mcp_tool_surecart_list_records( array( 'type' => 'subscriptions' ) );
check( 'subscriptions ask for the customer and the product', array_intersect( array( 'customer', 'price', 'price.product' ), (array) ( last_call()[2]['expand'] ?? array() ) ), array( 'customer', 'price', 'price.product' ) );
blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products' ) );
check( 'a product list asks for no extra detail', isset( last_call()[2]['expand'] ), false );
check( 'no type without personal details expands anything', array_keys( array_filter( blueworx_mcp_surecart_types(), function ( $t ) { return ! $t['personal'] && ! empty( $t['expand'] ); } ) ), array() );
check( 'the caller cannot ask for an expand', isset( blueworx_mcp_surecart_tool_definitions()[1]['inputSchema']['properties']['expand'] ) || isset( blueworx_mcp_surecart_tool_definitions()[2]['inputSchema']['properties']['expand'] ), false );
blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products', 'expand' => array( 'customer' ) ) );
check( 'and one passed anyway is not sent', isset( last_call()[2]['expand'] ), false );

// Prices and variants have no screen of their own, so they link to their product.
$GLOBALS['sc']['routes']['/surecart/v1/prices']   = array( 200, array( new Fake_Model( array( 'id' => 'pr1', 'product' => 'p1' ) ), new Fake_Model( array( 'id' => 'pr2' ) ) ), array( 'X-WP-Total' => '2' ) );
$GLOBALS['sc']['routes']['/surecart/v1/variants'] = array( 200, array( new Fake_Model( array( 'id' => 'v1', 'product' => (object) array( 'id' => 'p7', 'name' => 'Hoodie' ) ) ) ), array( 'X-WP-Total' => '1' ) );
$prices = blueworx_mcp_tool_surecart_list_records( array( 'type' => 'prices' ) );
check( 'a price links to its product', $prices['records'][0]['admin_link'], 'https://shop.test/wp-admin/admin.php?page=sc-products&action=edit&id=p1' );
check( 'a price with no product has no link', $prices['records'][1]['admin_link'], '' );
check( 'a variant links to its product, expanded or not', blueworx_mcp_tool_surecart_list_records( array( 'type' => 'variants' ) )['records'][0]['admin_link'], 'https://shop.test/wp-admin/admin.php?page=sc-products&action=edit&id=p7' );
$GLOBALS['sc']['routes']['/surecart/v1/prices/pr1'] = array( 200, new Fake_Model( array( 'id' => 'pr1', 'product' => 'p1' ) ), array() );
check( 'and so does a single price', blueworx_mcp_tool_surecart_get_record( array( 'type' => 'prices', 'id' => 'pr1' ) )['admin_link'], 'https://shop.test/wp-admin/admin.php?page=sc-products&action=edit&id=p1' );

// Charges and refunds have no screen; they link to their order when they carry one.
$GLOBALS['sc']['routes']['/surecart/v1/charges'] = array( 200, array( new Fake_Model( array( 'id' => 'c1', 'checkout' => (object) array( 'id' => 'ch1', 'order' => (object) array( 'id' => 'o9' ) ) ) ), new Fake_Model( array( 'id' => 'c2', 'checkout' => 'ch2' ) ) ), array( 'X-WP-Total' => '2' ) );
$GLOBALS['sc']['routes']['/surecart/v1/refunds'] = array( 200, array( new Fake_Model( array( 'id' => 'r1', 'charge' => (object) array( 'id' => 'c1', 'checkout' => (object) array( 'order' => 'o9' ) ) ) ), new Fake_Model( array( 'id' => 'r2', 'charge' => 'c1' ) ) ), array( 'X-WP-Total' => '2' ) );
$charges = blueworx_mcp_tool_surecart_list_records( array( 'type' => 'charges' ) );
check( 'charges ask for their checkout and its order', array_intersect( array( 'checkout', 'checkout.order' ), (array) ( last_call()[2]['expand'] ?? array() ) ), array( 'checkout', 'checkout.order' ) );
check( 'a charge links to its order', $charges['records'][0]['admin_link'], 'https://shop.test/wp-admin/admin.php?page=sc-orders&action=edit&id=o9' );
check( 'a charge without its order has no link', $charges['records'][1]['admin_link'], '' );
$refunds = blueworx_mcp_tool_surecart_list_records( array( 'type' => 'refunds' ) );
check( 'refunds ask for their charge', in_array( 'charge', (array) ( last_call()[2]['expand'] ?? array() ), true ), true );
check( 'a refund links to its order when its charge carries one', $refunds['records'][0]['admin_link'], 'https://shop.test/wp-admin/admin.php?page=sc-orders&action=edit&id=o9' );
check( 'and has no link when it does not', $refunds['records'][1]['admin_link'], '' );

// Checkouts have an edit screen but no list screen.
$GLOBALS['sc']['routes']['/surecart/v1/checkouts'] = array( 200, array( array( 'id' => 'ch1' ) ), array( 'X-WP-Total' => '1' ) );
$checkouts = blueworx_mcp_tool_surecart_list_records( array( 'type' => 'checkouts' ) );
check( 'the checkouts list has no list link', $checkouts['admin_link'], '' );
check( 'but each checkout keeps its own', $checkouts['records'][0]['admin_link'], 'https://shop.test/wp-admin/admin.php?page=sc-checkouts&action=edit&id=ch1' );

// Some types can only be listed.
$before = count( $GLOBALS['sc']['calls'] );
$reason = blueworx_mcp_tool_surecart_get_record( array( 'type' => 'return_reasons', 'id' => 'rr1' ) );
check( 'a get on a list-only type is refused up front', error_of( $reason ), 'list_only' );
check( 'with a plain way forward', strpos( message_of( $reason ), 'list_store_records' ) !== false, true );
check( 'and SureCart is not asked', count( $GLOBALS['sc']['calls'] ), $before );
check( 'return reasons are list-only', blueworx_mcp_surecart_types()['return_reasons']['list_only'], true );
check( 'orders are not', blueworx_mcp_surecart_types()['orders']['list_only'], false );

// The core store settings, each read on its own so one refusal does not sink the rest.
$GLOBALS['sc']['routes']['/surecart/v1/tax_protocol']    = array( 200, new Fake_Model( array( 'object' => 'tax_protocol', 'tax_enabled' => true, 'eu_vat' => (object) array( 'api_key' => 'k', 'required' => true ) ) ), array() );
$GLOBALS['sc']['routes']['/surecart/v1/order_protocol']  = array( 200, array( 'object' => 'order_protocol', 'number_prefix' => 'SC' ), array() );
$GLOBALS['sc']['routes']['/surecart/v1/review_protocol'] = array( 403, array( 'code' => 'plan_required', 'message' => 'Reviews need a higher plan.' ), array() );
$settings = blueworx_mcp_tool_surecart_get_settings( array() );
check( 'settings still come back when one part is refused', error_of( $settings ), 'no error' );
check( 'the tax setup comes back', $settings['protocols']['tax_protocol']['tax_enabled'] ?? null, true );
check( 'without its keys', isset( $settings['protocols']['tax_protocol']['eu_vat']['api_key'] ), false );
check( 'but with the rest of it', $settings['protocols']['tax_protocol']['eu_vat']['required'] ?? null, true );
check( 'the order setup comes back', $settings['protocols']['order_protocol']['number_prefix'] ?? null, 'SC' );
check( 'a refused part says it is unavailable', $settings['protocols']['review_protocol']['unavailable'] ?? null, 'Reviews need a higher plan.' );
check( 'every core settings part is asked for', array_keys( $settings['protocols'] ), array( 'tax_protocol', 'shipping_protocol', 'order_protocol', 'subscription_protocol', 'customer_notification_protocol', 'customer_portal_protocol', 'abandoned_checkout_protocol', 'affiliation_protocol', 'review_protocol', 'auto_fee_protocol' ) );

// SureCart's own checkout, shop and account pages are locked against change.
$GLOBALS['options']['surecart_checkout_page_id']  = '41';
$GLOBALS['options']['surecart_shop_page_id']      = 42;
$GLOBALS['options']['surecart_dashboard_page_id'] = '43';
check( 'the checkout page is locked', error_of( blueworx_mcp_surecart_page_locked_error( 41 ) ), 'surecart_read_only' );
check( 'the shop page too', error_of( blueworx_mcp_surecart_page_locked_error( 42 ) ), 'surecart_read_only' );
check( 'and the customer dashboard', error_of( blueworx_mcp_surecart_page_locked_error( 43 ) ), 'surecart_read_only' );
check( 'and the refusal points at SureCart\'s settings', strpos( message_of( blueworx_mcp_surecart_page_locked_error( 41 ) ), 'page=sc-settings' ) !== false, true );
check( 'an ordinary page is not', blueworx_mcp_surecart_page_locked_error( 7 ), null );
check( 'and nor is a missing ID', blueworx_mcp_surecart_page_locked_error( 0 ), null );

$GLOBALS['readonly_user'] = (object) array( 'ID' => 9 );
$GLOBALS['data_open']     = false;
$before                   = count( $GLOBALS['sc']['calls'] );
check( 'orders are withheld from a read-only account', error_of( blueworx_mcp_tool_surecart_list_records( array( 'type' => 'subscriptions' ) ) ), 'personal_data_withheld' );
check( 'and a single one too', error_of( blueworx_mcp_tool_surecart_get_record( array( 'type' => 'orders', 'id' => 'o1' ) ) ), 'personal_data_withheld' );
check( 'and SureCart was never asked for them', count( $GLOBALS['sc']['calls'] ), $before );
$GLOBALS['sc']['routes']['/surecart/v1/products'] = array( 200, array( $product ), array( 'X-WP-Total' => '11' ) );
check( 'products are not', error_of( blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products' ) ) ), 'no error' );
check( 'nor sales totals', error_of( blueworx_mcp_tool_surecart_get_sales( array( 'report' => 'orders', 'from' => '2026-01-01', 'to' => '2026-10-21' ) ) ), 'no error' );
check( 'nothing but GET was ever sent', array_values( array_unique( array_column( $GLOBALS['sc']['calls'], 0 ) ) ), array( 'GET' ) );

check( 'SureCart products are locked', blueworx_mcp_surecart_locked( 'sc_product' ), true );
check( 'collections too', blueworx_mcp_surecart_locked( 'sc_collection' ), true );
check( 'pages are not', blueworx_mcp_surecart_locked( 'page' ), false );
$locked = blueworx_mcp_surecart_locked_error( 'sc_product', 'Product' );
check( 'a locked write is refused', error_of( $locked ), 'surecart_read_only' );
check( 'and points at the products screen', strpos( message_of( $locked ), 'page=sc-products' ) !== false, true );
check( 'a locked type with no screen has no link', strpos( message_of( blueworx_mcp_surecart_locked_error( 'sc_cart', 'Cart' ) ), 'http' ), false );
check( 'an open type gives no error', blueworx_mcp_surecart_locked_error( 'page', 'Page' ), null );

finish();
