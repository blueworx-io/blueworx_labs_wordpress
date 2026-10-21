<?php
/**
 * Connect Claude: the content and term tools keep their hands off SureCart.
 *
 * Drives the real create, update, trash and term tools in includes/mcp/server.php
 * against a site with SureCart's product type, its collections taxonomy and its
 * checkout, shop and account pages. Every write to one of those is refused before
 * anything reaches WordPress; an ordinary page still gets through, and reading
 * SureCart's pages stays allowed.
 *
 * Run with: php tests/php/mcp-server-surecart-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.VariableComment.Missing, Generic.Files.OneObjectStructurePerFile.MultipleFound -- Test stand-ins.

define( 'EMPTY_TRASH_DAYS', 30 );

function admin_url( $path = '' ) {
	return 'https://shop.test/wp-admin/' . $path;
}

function wp_strip_all_tags( $text ) {
	return trim( strip_tags( (string) $text ) );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_list_pluck( $list, $field ) {
	return array_map(
		function ( $item ) use ( $field ) {
			return $item[ $field ];
		},
		$list
	);
}

function get_post_meta( $id, $key = '', $single = false ) {
	return '';
}

function a_type( $name, $rest_base, $label ) {
	return (object) array(
		'name'           => $name,
		'rest_base'      => $rest_base,
		'rest_namespace' => 'wp/v2',
		'labels'         => (object) array( 'singular_name' => $label ),
	);
}

function get_post_types( $args = array(), $output = 'names' ) {
	return array(
		'page'       => a_type( 'page', 'pages', 'Page' ),
		'sc_product' => a_type( 'sc_product', 'sc-products', 'Product' ),
	);
}

function get_taxonomies( $args = array(), $output = 'names' ) {
	return array(
		'category'      => a_type( 'category', 'categories', 'Category' ),
		'sc_collection' => a_type( 'sc_collection', 'sc-collections', 'Collection' ),
	);
}

class WP_REST_Request {
	public $method;
	public $route;
	public $params = array();

	public function __construct( $method = 'GET', $route = '' ) {
		$this->method = $method;
		$this->route  = $route;
	}

	public function set_param( $key, $value ) {
		$this->params[ $key ] = $value;
	}

	public function set_query_params( $params ) {
		$this->params = $params;
	}
}

class Fake_Response {
	public $data;

	public function __construct( $data ) {
		$this->data = $data;
	}

	public function is_error() {
		return false;
	}

	public function get_data() {
		return $this->data;
	}

	public function get_headers() {
		return array();
	}
}

$GLOBALS['rest'] = array();

function rest_do_request( $request ) {
	$GLOBALS['rest'][] = array( $request->method, $request->route );
	$id                = (int) basename( $request->route );
	return new Fake_Response(
		array(
			'id'     => $id > 0 ? $id : 99,
			'type'   => 'page',
			'title'  => array( 'raw' => 'A page' ),
			'status' => 'draft',
		)
	);
}

function error_of( $result ) {
	return is_wp_error( $result ) ? $result->get_error_code() : 'no error';
}

function writes() {
	return array_values(
		array_filter(
			$GLOBALS['rest'],
			function ( $call ) {
				return 'GET' !== $call[0];
			}
		)
	);
}

require dirname( __DIR__, 2 ) . '/includes/mcp/server.php';
require dirname( __DIR__, 2 ) . '/includes/mcp/surecart.php';

// SureCart keeps its page IDs in these options (app/src/WordPress/Pages/PageService.php getOptionName()).
$GLOBALS['options']['surecart_checkout_page_id']  = '41';
$GLOBALS['options']['surecart_shop_page_id']      = '42';
$GLOBALS['options']['surecart_dashboard_page_id'] = '43';

check( 'a new SureCart product is refused', error_of( blueworx_mcp_tool_create_content( array( 'type' => 'sc_product', 'title' => 'Hoodie' ) ) ), 'surecart_read_only' );
check( 'so is a change to one', error_of( blueworx_mcp_tool_update_content( array( 'type' => 'sc_product', 'id' => 5, 'title' => 'Hoodie' ) ) ), 'surecart_read_only' );
check( 'and binning one', error_of( blueworx_mcp_tool_trash_content( array( 'type' => 'sc_product', 'id' => 5 ) ) ), 'surecart_read_only' );
check( 'and a new collection', error_of( blueworx_mcp_tool_create_term( array( 'taxonomy' => 'sc_collection', 'name' => 'Sale' ) ) ), 'surecart_read_only' );
check( 'by its REST name too', error_of( blueworx_mcp_tool_create_content( array( 'type' => 'sc-products', 'title' => 'Hoodie' ) ) ), 'surecart_read_only' );
check( 'the checkout page cannot be changed', error_of( blueworx_mcp_tool_update_content( array( 'type' => 'page', 'id' => 41, 'title' => 'Pay' ) ) ), 'surecart_read_only' );
check( 'nor the shop page binned', error_of( blueworx_mcp_tool_trash_content( array( 'type' => 'page', 'id' => 42 ) ) ), 'surecart_read_only' );
check( 'nor the customer dashboard changed', error_of( blueworx_mcp_tool_update_content( array( 'type' => 'page', 'id' => 43, 'content' => 'x' ) ) ), 'surecart_read_only' );
check( 'and none of that reached WordPress', writes(), array() );

check( 'the checkout page can still be read', error_of( blueworx_mcp_tool_get_content( array( 'type' => 'page', 'id' => 41 ) ) ), 'no error' );
check( 'a normal page can be created', error_of( blueworx_mcp_tool_create_content( array( 'type' => 'page', 'title' => 'About' ) ) ), 'no error' );
check( 'and changed', error_of( blueworx_mcp_tool_update_content( array( 'type' => 'page', 'id' => 7, 'title' => 'About us' ) ) ), 'no error' );
check( 'and binned', error_of( blueworx_mcp_tool_trash_content( array( 'type' => 'page', 'id' => 7 ) ) ), 'no error' );
check( 'and a normal category created', error_of( blueworx_mcp_tool_create_term( array( 'taxonomy' => 'category', 'name' => 'News' ) ) ), 'no error' );
check( 'those writes went through', writes(), array( array( 'POST', '/wp/v2/pages' ), array( 'POST', '/wp/v2/pages/7' ), array( 'DELETE', '/wp/v2/pages/7' ), array( 'POST', '/wp/v2/categories' ) ) );

finish();
