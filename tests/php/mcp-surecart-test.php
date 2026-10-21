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
	return gmdate( $format, $time );
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
blueworx_mcp_tool_surecart_list_records( array( 'type' => 'orders', 'search' => 'smith', 'status' => 'paid', 'customer_id' => 'c1', 'from' => '2026-01-01', 'to' => '2026-02-01' ) );
$q = last_call()[2];
check( 'search maps to query', $q['query'], 'smith' );
check( 'status maps to status[]', $q['status'], array( 'paid' ) );
check( 'customer maps to customer_ids', $q['customer_ids'], array( 'c1' ) );
check( 'dates map to a created_at range', isset( $q['created_at'] ), true );
blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products', 'customer_id' => 'c1' ) );
check( 'a filter a type does not take is dropped', isset( last_call()[2]['customer_ids'] ), false );
check( 'a bad date is refused', error_of( blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products', 'from' => 'soon' ) ) ), 'invalid_date' );
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
$sales = blueworx_mcp_tool_surecart_get_sales( array( 'report' => 'orders', 'from' => '2026-01-01', 'to' => '2026-10-21' ) );
check( 'sales ask for a month by default', last_call()[2]['interval'], 'month' );
check( 'sales ask the stats route', last_call()[1], '/surecart/v1/stats/orders' );
check( 'sales rows read as money', $sales['rows'][0]['amount_display'], '£48.00' );
check( 'and as an average', $sales['rows'][0]['average_amount_display'], '£24.00' );
check( 'and as a date', $sales['rows'][0]['interval_at'], '2026-01-01' );
check( 'sales add up', array( $sales['totals']['count'], $sales['totals']['amount_display'] ), array( 2, '£48.00' ) );
check( 'sales need both dates', error_of( blueworx_mcp_tool_surecart_get_sales( array( 'report' => 'orders', 'from' => '2026-01-01' ) ) ), 'invalid_date' );
check( 'an unknown report is refused', error_of( blueworx_mcp_tool_surecart_get_sales( array( 'report' => 'x', 'from' => '2026-01-01', 'to' => '2026-02-01' ) ) ), 'unknown_report' );
$GLOBALS['sc']['routes']['/surecart/v1/products'] = array( 400, array( 'code' => 'missing_api_token', 'message' => '<p>Your store is not connected to SureCart.</p>' ), array() );
check( 'a store with no connection says so in SureCart\'s words', message_of( blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products' ) ) ), 'Your store is not connected to SureCart.' );
$GLOBALS['readonly_user'] = (object) array( 'ID' => 9 );
$GLOBALS['data_open']     = false;
check( 'orders are withheld from a read-only account', error_of( blueworx_mcp_tool_surecart_list_records( array( 'type' => 'subscriptions' ) ) ), 'personal_data_withheld' );
check( 'and a single one too', error_of( blueworx_mcp_tool_surecart_get_record( array( 'type' => 'orders', 'id' => 'o1' ) ) ), 'personal_data_withheld' );
$GLOBALS['sc']['routes']['/surecart/v1/products'] = array( 200, array( $product ), array( 'X-WP-Total' => '11' ) );
check( 'products are not', error_of( blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products' ) ) ), 'no error' );
check( 'nor sales totals', error_of( blueworx_mcp_tool_surecart_get_sales( array( 'report' => 'orders', 'from' => '2026-01-01', 'to' => '2026-10-21' ) ) ), 'no error' );
check( 'nothing but GET was ever sent', array_values( array_unique( array_column( $GLOBALS['sc']['calls'], 0 ) ) ), array( 'GET' ) );

finish();
