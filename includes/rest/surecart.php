<?php
/**
 * Headless REST layer — SureCart proxy.
 *
 * Server-side proxy to the SureCart API. The secret key never leaves the
 * server (wp-config constant). Public catalogue endpoints are open; per-user
 * endpoints are scoped to the caller's SureCart customer and fail closed —
 * a record is returned only when it can be positively confirmed to belong to
 * that customer. Disabled by default (settings toggle).
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SureCart API base URL.
 */
if ( ! defined( 'BLUEWORX_HEADLESS_SURECART_BASE' ) ) {
	define( 'BLUEWORX_HEADLESS_SURECART_BASE', 'https://api.surecart.com/v1' );
}

/**
 * Registers the SureCart proxy routes (only when enabled and configured).
 *
 * @return void
 */
function blueworx_headless_register_surecart_routes() {
	if ( ! blueworx_headless_surecart_ready() ) {
		return;
	}

	$ns   = BLUEWORX_HEADLESS_NAMESPACE;
	$open = '__return_true';
	$auth = 'blueworx_headless_require_auth';

	$routes = array(
		array( '/surecart/products', 'GET', 'blueworx_headless_sc_products', $open ),
		array( '/surecart/products/(?P<id>[a-zA-Z0-9_-]+)', 'GET', 'blueworx_headless_sc_product', $open ),
		array( '/surecart/prices', 'GET', 'blueworx_headless_sc_prices', $open ),
		array( '/surecart/me/orders', 'GET', 'blueworx_headless_sc_my_orders', $auth ),
		array( '/surecart/me/subscriptions', 'GET', 'blueworx_headless_sc_my_subscriptions', $auth ),
		array( '/surecart/me/invoices', 'GET', 'blueworx_headless_sc_my_invoices', $auth ),
		array( '/surecart/checkout', 'POST', 'blueworx_headless_sc_checkout', $auth ),
		array( '/surecart/me/subscriptions/(?P<id>[a-zA-Z0-9_-]+)/cancel', 'POST', 'blueworx_headless_sc_cancel_subscription', $auth ),
	);

	foreach ( $routes as $route ) {
		register_rest_route(
			$ns,
			$route[0],
			array(
				'methods'             => $route[1],
				'callback'            => $route[2],
				'permission_callback' => $route[3],
			)
		);
	}
}

/**
 * Whether the SureCart proxy is enabled and has an API key.
 *
 * @return bool True when ready.
 */
function blueworx_headless_surecart_ready() {
	return '1' === blueworx_headless_setting( 'surecart_enabled' )
		&& defined( 'BLUEWORX_LABS_SURECART_API_KEY' )
		&& '' !== (string) BLUEWORX_LABS_SURECART_API_KEY;
}

/**
 * Performs a SureCart API request.
 *
 * @param string $method HTTP method.
 * @param string $path   Path beginning with '/'.
 * @param array  $query  Query parameters.
 * @param array  $body   Request body (for writes).
 * @return array|WP_Error { status, data } or error.
 */
function blueworx_headless_surecart_request( $method, $path, $query = array(), $body = array() ) {
	$url = BLUEWORX_HEADLESS_SURECART_BASE . $path;

	if ( ! empty( $query ) ) {
		$url = add_query_arg( $query, $url );
	}

	$args = array(
		'method'  => $method,
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Bearer ' . BLUEWORX_LABS_SURECART_API_KEY,
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
		),
	);

	if ( ! empty( $body ) ) {
		$args['body'] = wp_json_encode( $body );
	}

	$response = wp_remote_request( esc_url_raw( $url ), $args );

	if ( is_wp_error( $response ) ) {
		return blueworx_headless_error( 'blueworx_surecart_unreachable', __( 'The store is temporarily unavailable.', 'blueworx-project-wordpress-labs' ), 502 );
	}

	$status = (int) wp_remote_retrieve_response_code( $response );
	$data   = json_decode( wp_remote_retrieve_body( $response ), true );

	return array(
		'status' => $status,
		'data'   => $data,
	);
}

/**
 * Resolves (and caches) the SureCart customer ID for a WP user.
 *
 * @param WP_User $user User.
 * @return string Customer ID, or '' when none exists.
 */
function blueworx_headless_surecart_customer_id( $user ) {
	$cached = get_user_meta( $user->ID, 'blueworx_surecart_customer_id', true );

	if ( ! empty( $cached ) ) {
		return (string) $cached;
	}

	$result = blueworx_headless_surecart_request( 'GET', '/customers', array( 'email' => $user->user_email ) );

	if ( is_wp_error( $result ) || $result['status'] >= 300 ) {
		return '';
	}

	$records = blueworx_headless_surecart_records( $result['data'] );

	foreach ( $records as $record ) {
		if ( isset( $record['email'] ) && strtolower( (string) $record['email'] ) === strtolower( $user->user_email ) && ! empty( $record['id'] ) ) {
			update_user_meta( $user->ID, 'blueworx_surecart_customer_id', (string) $record['id'] );

			return (string) $record['id'];
		}
	}

	return '';
}

/**
 * Extracts the list of records from a SureCart response envelope.
 *
 * @param mixed $data Decoded response.
 * @return array List of record arrays.
 */
function blueworx_headless_surecart_records( $data ) {
	if ( ! is_array( $data ) ) {
		return array();
	}

	if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
		return $data['data'];
	}

	// Some envelopes key the list by the resource name.
	foreach ( $data as $value ) {
		if ( is_array( $value ) && isset( $value[0] ) ) {
			return $value;
		}
	}

	return isset( $data[0] ) ? $data : array();
}

/**
 * Extracts a record's customer ID from common SureCart shapes.
 *
 * @param array $record Record.
 * @return string Customer ID, or '' when not determinable.
 */
function blueworx_headless_surecart_record_customer( $record ) {
	if ( ! isset( $record['customer'] ) ) {
		return '';
	}

	if ( is_string( $record['customer'] ) ) {
		return $record['customer'];
	}

	if ( is_array( $record['customer'] ) && ! empty( $record['customer']['id'] ) ) {
		return (string) $record['customer']['id'];
	}

	return '';
}

/**
 * Requests a customer-scoped list and fails closed: only records positively
 * confirmed to belong to the customer are returned.
 *
 * @param string $path        Resource path (e.g. '/orders').
 * @param string $customer_id Customer ID.
 * @param array  $query       Extra query parameters.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_surecart_scoped_list( $path, $customer_id, $query = array() ) {
	$query['customer_ids'] = array( $customer_id );

	$result = blueworx_headless_surecart_request( 'GET', $path, $query );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$owned = array();

	foreach ( blueworx_headless_surecart_records( $result['data'] ) as $record ) {
		if ( blueworx_headless_surecart_record_customer( $record ) === $customer_id ) {
			$owned[] = $record;
		}
	}

	return new WP_REST_Response( array( 'data' => $owned ), 200 );
}

/**
 * Resolves the current user's customer ID or returns a 404-style empty result.
 *
 * @return string|WP_REST_Response Customer ID, or an empty-list response.
 */
function blueworx_headless_sc_current_customer() {
	$customer_id = blueworx_headless_surecart_customer_id( wp_get_current_user() );

	if ( '' === $customer_id ) {
		return new WP_REST_Response( array( 'data' => array() ), 200 );
	}

	return $customer_id;
}

/**
 * GET /surecart/products — public catalogue passthrough.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_sc_products( WP_REST_Request $request ) {
	$result = blueworx_headless_surecart_request( 'GET', '/products', $request->get_query_params() );

	return blueworx_headless_surecart_passthrough( $result );
}

/**
 * GET /surecart/products/{id} — retrieve a product.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_sc_product( WP_REST_Request $request ) {
	$id     = sanitize_text_field( (string) $request->get_param( 'id' ) );
	$result = blueworx_headless_surecart_request( 'GET', '/products/' . rawurlencode( $id ) );

	return blueworx_headless_surecart_passthrough( $result );
}

/**
 * GET /surecart/prices — public prices passthrough.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_sc_prices( WP_REST_Request $request ) {
	$result = blueworx_headless_surecart_request( 'GET', '/prices', $request->get_query_params() );

	return blueworx_headless_surecart_passthrough( $result );
}

/**
 * GET /surecart/me/orders — the caller's orders.
 *
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_sc_my_orders() {
	$customer = blueworx_headless_sc_current_customer();

	return is_string( $customer ) ? blueworx_headless_surecart_scoped_list( '/orders', $customer ) : $customer;
}

/**
 * GET /surecart/me/subscriptions — the caller's subscriptions.
 *
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_sc_my_subscriptions() {
	$customer = blueworx_headless_sc_current_customer();

	return is_string( $customer ) ? blueworx_headless_surecart_scoped_list( '/subscriptions', $customer ) : $customer;
}

/**
 * GET /surecart/me/invoices — the caller's invoices.
 *
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_sc_my_invoices() {
	$customer = blueworx_headless_sc_current_customer();

	return is_string( $customer ) ? blueworx_headless_surecart_scoped_list( '/invoices', $customer ) : $customer;
}

/**
 * POST /surecart/checkout — create a checkout for the caller's customer.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_sc_checkout( WP_REST_Request $request ) {
	$customer = blueworx_headless_sc_current_customer();

	if ( ! is_string( $customer ) ) {
		return blueworx_headless_error( 'blueworx_no_customer', __( 'No customer record is associated with your account yet.', 'blueworx-project-wordpress-labs' ), 400 );
	}

	$body             = (array) $request->get_json_params();
	$body['customer'] = $customer;

	$result = blueworx_headless_surecart_request( 'POST', '/checkouts', array(), $body );

	return blueworx_headless_surecart_passthrough( $result );
}

/**
 * POST /surecart/me/subscriptions/{id}/cancel — cancel an owned subscription.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_sc_cancel_subscription( WP_REST_Request $request ) {
	$customer = blueworx_headless_sc_current_customer();

	if ( ! is_string( $customer ) ) {
		return blueworx_headless_error( 'blueworx_forbidden', __( 'You do not have access to that subscription.', 'blueworx-project-wordpress-labs' ), 403 );
	}

	$id     = sanitize_text_field( (string) $request->get_param( 'id' ) );
	$lookup = blueworx_headless_surecart_request( 'GET', '/subscriptions/' . rawurlencode( $id ) );

	if ( is_wp_error( $lookup ) ) {
		return $lookup;
	}

	// Ownership is verified before any state change; fail closed.
	if ( blueworx_headless_surecart_record_customer( (array) $lookup['data'] ) !== $customer ) {
		return blueworx_headless_error( 'blueworx_forbidden', __( 'You do not have access to that subscription.', 'blueworx-project-wordpress-labs' ), 403 );
	}

	$result = blueworx_headless_surecart_request( 'POST', '/subscriptions/' . rawurlencode( $id ) . '/cancel' );

	return blueworx_headless_surecart_passthrough( $result );
}

/**
 * Converts a SureCart request result into a REST response, mapping upstream
 * error statuses to a normalised envelope.
 *
 * @param array|WP_Error $result Request result.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_surecart_passthrough( $result ) {
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	if ( $result['status'] >= 400 ) {
		return blueworx_headless_error( 'blueworx_surecart_error', __( 'The store could not complete that request.', 'blueworx-project-wordpress-labs' ), $result['status'] );
	}

	return new WP_REST_Response( $result['data'], $result['status'] );
}
