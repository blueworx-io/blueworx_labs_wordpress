<?php
/**
 * BlueWorx: Connect Claude — the SureCart helpers.
 *
 * SureCart is the shop on a site. The tools built on these helpers only look:
 * they read SureCart's own REST routes inside this request and never write.
 * This file is the shared layer under them: which record types exist, which of
 * them hold customers' personal details, how a record is cleaned and shaped
 * before Claude sees it, and where its screen is in the WordPress admin.
 *
 * Secrets never leave the site. Every record is scrubbed of anything that looks
 * like a key, token or password, however deeply it is nested. A record type this
 * file does not know is treated as personal, so a new SureCart type stays closed
 * until someone lists it here on purpose.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether SureCart is running on this site.
 *
 * @return bool
 */
function blueworx_mcp_surecart_active() {
	if ( function_exists( 'blueworx_readonly_surecart_active' ) ) {
		return (bool) blueworx_readonly_surecart_active();
	}
	return class_exists( 'SureCart' ) || defined( 'SURECART_PLUGIN_FILE' );
}

/**
 * The refusal to give when SureCart is off.
 *
 * @return WP_Error|null Null when SureCart is active.
 */
function blueworx_mcp_surecart_unavailable() {
	if ( blueworx_mcp_surecart_active() ) {
		return null;
	}
	return new WP_Error( 'surecart_inactive', 'SureCart is not active on this site, so there is no shop to look at.' );
}

/**
 * The record types Claude can look at.
 *
 * Keyed by the public name. Each entry has the SureCart REST route, whether the
 * type holds customers' personal details, the admin page that shows it (empty
 * where SureCart has no screen for it) and a plain label.
 *
 * @return array[]
 */
function blueworx_mcp_surecart_types() {
	static $types = null;
	if ( null !== $types ) {
		return $types;
	}

	// Public name => array( personal, admin page ).
	$list = array(
		'products'               => array( false, 'sc-products' ),
		'prices'                 => array( false, '' ),
		'variants'               => array( false, '' ),
		'variant_options'        => array( false, '' ),
		'product_collections'    => array( false, 'sc-product-collections' ),
		'product_groups'         => array( false, 'sc-product-groups' ),
		'product_medias'         => array( false, '' ),
		'bundle_items'           => array( false, '' ),
		'coupons'                => array( false, 'sc-coupons' ),
		'promotions'             => array( false, '' ),
		'bumps'                  => array( false, 'sc-bumps' ),
		'upsells'                => array( false, '' ),
		'upsell_funnels'         => array( false, 'sc-upsell-funnels' ),
		'auto_fees'              => array( false, 'sc-auto-fees' ),
		'tax_zones'              => array( false, '' ),
		'tax_registrations'      => array( false, '' ),
		'tax_overrides'          => array( false, '' ),
		'shipping_profiles'      => array( false, '' ),
		'shipping_zones'         => array( false, '' ),
		'shipping_methods'       => array( false, '' ),
		'shipping_rates'         => array( false, '' ),
		'warehouses'             => array( false, '' ),
		'processors'             => array( false, '' ),
		'manual_payment_methods' => array( false, '' ),
		'display_currencies'     => array( false, '' ),
		'cancellation_reasons'   => array( false, '' ),
		'return_reasons'         => array( false, '' ),
		'customers'              => array( true, 'sc-customers' ),
		'orders'                 => array( true, 'sc-orders' ),
		'checkouts'              => array( true, 'sc-checkouts' ),
		'abandoned_checkouts'    => array( true, 'sc-abandoned-checkouts' ),
		'invoices'               => array( true, 'sc-invoices' ),
		'charges'                => array( true, '' ),
		'refunds'                => array( true, '' ),
		'disputes'               => array( true, '' ),
		'subscriptions'          => array( true, 'sc-subscriptions' ),
		'purchases'              => array( true, '' ),
		'payment_methods'        => array( true, '' ),
		'licenses'               => array( true, 'sc-licenses' ),
		'activations'            => array( true, '' ),
		'fulfillments'           => array( true, '' ),
		'shipments'              => array( true, '' ),
		'return_requests'        => array( true, '' ),
		'reviews'                => array( true, 'sc-reviews' ),
		'affiliations'           => array( true, 'sc-affiliates' ),
		'affiliation_requests'   => array( true, 'sc-affiliate-requests' ),
		'referrals'              => array( true, 'sc-affiliate-referrals' ),
		'payouts'                => array( true, 'sc-affiliate-payouts' ),
		'clicks'                 => array( true, '' ),
		'cancellation_acts'      => array( true, '' ),
		'balance_transactions'   => array( true, '' ),
	);

	$types = array();
	foreach ( $list as $name => $info ) {
		$types[ $name ] = array(
			'route'      => '/surecart/v1/' . $name,
			'personal'   => $info[0],
			'admin_page' => $info[1],
			'label'      => ucfirst( str_replace( '_', ' ', $name ) ),
		);
	}
	return $types;
}

/**
 * Whether a record type holds personal details. Unknown types do.
 *
 * @param string $type A public type name.
 * @return bool
 */
function blueworx_mcp_surecart_is_personal( $type ) {
	$types = blueworx_mcp_surecart_types();
	return ! isset( $types[ $type ] ) || ! empty( $types[ $type ]['personal'] );
}

/**
 * Whether the signed-in read-only account is barred from personal data.
 *
 * Administrators, and anyone who is not on a read-only account, are never
 * blocked.
 *
 * @return bool
 */
function blueworx_mcp_surecart_personal_blocked() {
	if ( ! function_exists( 'blueworx_readonly_current_user' ) || ! function_exists( 'blueworx_readonly_data_allowed' ) ) {
		return false;
	}
	$user = blueworx_readonly_current_user();
	return $user && ! blueworx_readonly_data_allowed( $user );
}

/**
 * Removes anything that looks like a secret, however deeply it is nested.
 *
 * @param mixed $data A record, a list of records, or a single value.
 * @return mixed The same, without the secret keys.
 */
function blueworx_mcp_surecart_scrub( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}
	$clean = array();
	foreach ( $data as $key => $value ) {
		if ( is_string( $key ) && preg_match( '/(token|secret|password|private|signing|api_key)/i', $key ) ) {
			continue;
		}
		$clean[ $key ] = blueworx_mcp_surecart_scrub( $value );
	}
	return $clean;
}

/**
 * Formats an amount in minor units (pence, cents) as money.
 *
 * @param int    $amount   Amount in the currency's smallest unit.
 * @param string $currency ISO currency code.
 * @return string
 */
function blueworx_mcp_surecart_money( $amount, $currency ) {
	$code     = strtolower( (string) $currency );
	$zero     = array( 'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf' );
	$symbols  = array(
		'gbp' => '£',
		'usd' => '$',
		'eur' => '€',
		'jpy' => '¥',
	);
	$decimals = in_array( $code, $zero, true ) ? 0 : 2;
	$value    = $amount / ( 10 ** $decimals );
	$sign     = $value < 0 ? '-' : '';
	$number   = number_format( abs( $value ), $decimals );
	$prefix   = isset( $symbols[ $code ] ) ? $symbols[ $code ] : strtoupper( $code ) . ' ';
	return $sign . $prefix . $number;
}

/**
 * Cleans a record for Claude: no secrets, no noise, readable dates and money.
 *
 * @param array  $record   A SureCart record.
 * @param string $currency Currency to use when the record has none.
 * @return array
 */
function blueworx_mcp_surecart_shape( $record, $currency = '' ) {
	$record = blueworx_mcp_surecart_scrub( $record );
	if ( isset( $record['currency'] ) && is_string( $record['currency'] ) && '' !== $record['currency'] ) {
		$currency = $record['currency'];
	}

	$shaped = array();
	foreach ( $record as $key => $value ) {
		if ( is_string( $key ) && in_array( $key, array( 'post', 'image_srcset', 'cache_keys', 'metadata' ), true ) ) {
			continue;
		}
		if ( is_array( $value ) ) {
			$value = blueworx_mcp_surecart_shape( $value, $currency );
		}
		$shaped[ $key ] = $value;

		if ( ! is_string( $key ) || ! is_int( $value ) ) {
			continue;
		}
		if ( preg_match( '/_at$/', $key ) && $value > 0 ) {
			$shaped[ $key ] = wp_date( 'c', $value );
		} elseif ( '' !== $currency && preg_match( '/^amount(_|$)|_amount$/', $key ) && ! preg_match( '/_display$/', $key ) ) {
			$shaped[ $key . '_display' ] = blueworx_mcp_surecart_money( $value, $currency );
		}
	}
	return $shaped;
}

/**
 * The admin screen for a record, or the list screen when no id is given.
 *
 * @param string $type A public type name.
 * @param string $id   A record id.
 * @return string URL, or an empty string when the type has no screen.
 */
function blueworx_mcp_surecart_admin_link( $type, $id = '' ) {
	$types = blueworx_mcp_surecart_types();
	if ( ! isset( $types[ $type ] ) || '' === $types[ $type ]['admin_page'] ) {
		return '';
	}
	$path = 'admin.php?page=' . $types[ $type ]['admin_page'];
	if ( '' !== $id ) {
		$path .= '&action=edit&id=' . rawurlencode( $id );
	}
	return admin_url( $path );
}

/**
 * Asks SureCart's own REST layer, inside this request. Only ever reads.
 *
 * There is no method parameter and the caller never picks a route: it passes a
 * path under /surecart/v1 that this file built from a fixed list.
 *
 * @param string $route Route under /surecart/v1, e.g. /products.
 * @param array  $query Query parameters.
 * @return array|WP_Error array( 'data' => mixed, 'total' => int|null ), or why SureCart refused.
 */
function blueworx_mcp_surecart_request( $route, $query = array() ) {
	$request = new WP_REST_Request( 'GET', '/surecart/v1' . $route );

	if ( ! empty( $query ) ) {
		$request->set_query_params( $query );
	}

	$response = rest_do_request( $request );
	$data     = $response->get_data();

	if ( $response->is_error() ) {
		$code    = is_array( $data ) && isset( $data['code'] ) ? (string) $data['code'] : 'surecart_error';
		$message = is_array( $data ) && isset( $data['message'] ) ? wp_strip_all_tags( (string) $data['message'] ) : '';

		if ( 'rest_no_route' === $code ) {
			return new WP_Error( $code, 'SureCart is not answering on this site. Check that the SureCart plugin is active.' );
		}

		return new WP_Error( $code, '' === $message ? 'SureCart refused that.' : $message );
	}

	$headers = method_exists( $response, 'get_headers' ) ? (array) $response->get_headers() : array();
	$total   = null;

	foreach ( $headers as $name => $value ) {
		if ( 'x-wp-total' === strtolower( (string) $name ) ) {
			$total = (int) $value;
		}
	}

	return array(
		'data'  => $data,
		'total' => $total,
	);
}

/**
 * The sales reports the store answers (/stats/<id>).
 *
 * @return string[]
 */
function blueworx_mcp_surecart_reports() {
	return array( 'orders' );
}

/**
 * Which record types take each list filter.
 *
 * Read from how SureCart's own admin list screens and its Abilities
 * (app/src/Controllers/Admin/*ListTable.php, app/src/Abilities/Abilities/List*.php)
 * call its API. SureCart's REST layer passes the query straight to its API, so
 * only filters seen in use are listed; a filter on any other type is reported
 * back as ignored. There is no confirmed date filter, so there is none here.
 *
 * @return array[] Types keyed by filter name.
 */
function blueworx_mcp_surecart_filter_types() {
	return array(
		'search'       => array( 'products', 'orders', 'customers', 'coupons', 'invoices', 'licenses', 'subscriptions', 'bumps', 'auto_fees', 'upsell_funnels', 'product_collections', 'reviews', 'affiliations', 'affiliation_requests', 'referrals', 'payouts', 'clicks' ),
		'status'       => array( 'products', 'orders', 'invoices', 'subscriptions', 'abandoned_checkouts', 'bumps', 'auto_fees', 'upsell_funnels', 'reviews', 'product_groups', 'affiliations', 'affiliation_requests', 'referrals', 'payouts' ),
		'customer_ids' => array( 'orders', 'abandoned_checkouts', 'invoices', 'refunds', 'subscriptions', 'licenses' ),
		'product_ids'  => array( 'prices', 'reviews', 'licenses' ),
	);
}

/**
 * Checks a Y-m-d date.
 *
 * @param mixed  $value A date.
 * @param string $name  Argument name, for the message.
 * @return true|WP_Error
 */
function blueworx_mcp_surecart_check_date( $value, $name ) {
	$value = is_scalar( $value ) ? (string) $value : '';
	if ( 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
		return true;
	}
	return new WP_Error( 'invalid_date', $name . ' must be a real date as YYYY-MM-DD, e.g. 2026-11-01.' );
}

/**
 * Describes the SureCart tools, for tools/list. Always listed, so a call made
 * while the shop is off is told why.
 *
 * @return array Tool definitions.
 */
function blueworx_mcp_surecart_tool_definitions() {
	$annotations = array(
		'readOnlyHint'  => true,
		'openWorldHint' => false,
	);
	$types       = array_keys( blueworx_mcp_surecart_types() );
	$date        = array(
		'type'        => 'string',
		'description' => 'Date as YYYY-MM-DD.',
	);

	return array(
		array(
			'name'        => 'get_store_settings',
			'title'       => 'Get the store settings',
			'description' => 'Reads the SureCart shop\'s settings: the brand (colours, dark colour, theme, logo), the account (name, currency, connection, plan), the store settings, and links to those screens. This is where the store\'s look lives. Read only.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => new stdClass(),
			),
			'annotations' => $annotations,
		),
		array(
			'name'        => 'list_store_records',
			'title'       => 'List store records',
			'description' => 'Lists one kind of SureCart record (products, orders, customers, coupons and so on), a page at a time, with a link to each in the WordPress admin. Filters: search, status, customer_id and product_id, each only on the record types that take it; a filter that does not apply is named in ignored_filters. There is no date filter; use get_store_sales for sales over a period. Read only. Customer details are withheld on read-only accounts that have data access off.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'type'        => array(
						'type'        => 'string',
						'enum'        => $types,
						'description' => 'The kind of record.',
					),
					'search'      => array(
						'type'        => 'string',
						'description' => 'Words to search for.',
					),
					'status'      => array(
						'type'        => 'string',
						'description' => 'Only records with this status.',
					),
					'customer_id' => array(
						'type'        => 'string',
						'description' => 'Only records for this customer.',
					),
					'product_id'  => array(
						'type'        => 'string',
						'description' => 'Only records for this product.',
					),
					'page'        => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'Page number (default 1).',
					),
					'per_page'    => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 100,
						'description' => 'Records per page (default 20, most 100).',
					),
				),
				'required'   => array( 'type' ),
			),
			'annotations' => $annotations,
		),
		array(
			'name'        => 'get_store_record',
			'title'       => 'Get a store record',
			'description' => 'Gets one SureCart record in full, with a link to it in the WordPress admin. Read only.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'type' => array(
						'type'        => 'string',
						'enum'        => $types,
						'description' => 'The kind of record.',
					),
					'id'   => array(
						'type'        => 'string',
						'description' => 'The record ID.',
					),
				),
				'required'   => array( 'type', 'id' ),
			),
			'annotations' => $annotations,
		),
		array(
			'name'        => 'get_store_sales',
			'title'       => 'Get store sales',
			'description' => 'Sales over time from SureCart, one row per day, week, month or year, with amounts, averages and counts, and the totals. No customer details. Read only.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'report'   => array(
						'type'        => 'string',
						'enum'        => blueworx_mcp_surecart_reports(),
						'description' => 'Which report (default orders).',
					),
					'from'     => $date,
					'to'       => $date,
					'interval' => array(
						'type'        => 'string',
						'enum'        => array( 'day', 'week', 'month', 'year' ),
						'description' => 'Row size (default month).',
					),
				),
				'required'   => array( 'from', 'to' ),
			),
			'annotations' => $annotations,
		),
	);
}

/**
 * Maps the SureCart tool names to the functions that run them.
 *
 * @return array Callables keyed by tool name.
 */
function blueworx_mcp_surecart_tool_handlers() {
	return array(
		'get_store_settings' => 'blueworx_mcp_tool_surecart_get_settings',
		'list_store_records' => 'blueworx_mcp_tool_surecart_list_records',
		'get_store_record'   => 'blueworx_mcp_tool_surecart_get_record',
		'get_store_sales'    => 'blueworx_mcp_tool_surecart_get_sales',
	);
}

/**
 * Whether a post type or taxonomy is SureCart's own (they all start sc_).
 *
 * @param string $name Post type or taxonomy name.
 * @return bool
 */
function blueworx_mcp_surecart_locked( $name ) {
	return 0 === strpos( (string) $name, 'sc_' );
}

/**
 * The refusal for a write to one of SureCart's own records, or null when the
 * type is not SureCart's.
 *
 * @param string $name  Post type or taxonomy name.
 * @param string $label Its plain label.
 * @return WP_Error|null
 */
function blueworx_mcp_surecart_locked_error( $name, $label ) {
	if ( ! blueworx_mcp_surecart_locked( $name ) ) {
		return null;
	}
	$screens = array(
		'sc_product'    => blueworx_mcp_surecart_admin_link( 'products' ),
		'sc_collection' => blueworx_mcp_surecart_admin_link( 'product_collections' ),
		'sc_form'       => admin_url( 'admin.php?page=sc-forms' ),
	);
	$message = $label . ' belongs to SureCart, which Claude can only read. Change it in SureCart.';
	if ( ! empty( $screens[ $name ] ) ) {
		$message = $label . ' belongs to SureCart, which Claude can only read. Change it in SureCart: ' . $screens[ $name ];
	}
	return new WP_Error( 'surecart_read_only', $message );
}

/**
 * What get_site_info says about the shop.
 *
 * @return array
 */
function blueworx_mcp_surecart_site_info() {
	return array(
		'active'       => blueworx_mcp_surecart_active(),
		'record_types' => array_keys( blueworx_mcp_surecart_types() ),
	);
}

/**
 * Checks a type argument, and that the account may see that kind of record.
 *
 * @param array $args Tool arguments.
 * @return string|WP_Error The type name.
 */
function blueworx_mcp_surecart_resolve_type( $args ) {
	$type  = isset( $args['type'] ) && is_scalar( $args['type'] ) ? (string) $args['type'] : '';
	$types = blueworx_mcp_surecart_types();

	if ( ! isset( $types[ $type ] ) ) {
		return new WP_Error( 'unknown_type', 'Unknown record type "' . $type . '". The types are: ' . implode( ', ', array_keys( $types ) ) . '.' );
	}

	if ( blueworx_mcp_surecart_is_personal( $type ) && blueworx_mcp_surecart_personal_blocked() ) {
		return new WP_Error( 'personal_data_withheld', 'Customer details are switched off for this account, so ' . strtolower( $types[ $type ]['label'] ) . ' cannot be shown.' );
	}

	return $type;
}

/**
 * Tool: get_store_settings.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_surecart_get_settings( $args ) {
	$off = blueworx_mcp_surecart_unavailable();
	if ( null !== $off ) {
		return $off;
	}

	$answers = array();
	foreach ( array( 'brand', 'account', 'settings' ) as $part ) {
		$answer = blueworx_mcp_surecart_request( '/' . $part );
		if ( is_wp_error( $answer ) ) {
			return $answer;
		}
		$answers[ $part ] = is_array( $answer['data'] ) ? $answer['data'] : array();
	}

	$brand   = $answers['brand'];
	$account = $answers['account'];

	$out_brand = array();
	foreach ( array( 'color', 'dark_color', 'theme', 'logo_url' ) as $key ) {
		$out_brand[ $key ] = isset( $brand[ $key ] ) ? $brand[ $key ] : null;
	}

	$out_account = array();
	foreach ( array( 'name', 'slug', 'url', 'currency', 'locale', 'time_zone', 'live_mode', 'is_connected', 'claimed' ) as $key ) {
		if ( isset( $account[ $key ] ) ) {
			$out_account[ $key ] = $account[ $key ];
		}
	}
	if ( isset( $account['plan']['name'] ) ) {
		$out_account['plan'] = $account['plan']['name'];
	}

	return array(
		'brand'    => $out_brand,
		'account'  => blueworx_mcp_surecart_scrub( $out_account ),
		'settings' => blueworx_mcp_surecart_shape( $answers['settings'] ),
		'links'    => array(
			'settings' => admin_url( 'admin.php?page=sc-settings' ),
			'brand'    => admin_url( 'admin.php?page=sc-settings&tab=brand' ),
			'checkout' => admin_url( 'admin.php?page=sc-forms' ),
		),
	);
}

/**
 * Tool: list_store_records.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_surecart_list_records( $args ) {
	$off = blueworx_mcp_surecart_unavailable();
	if ( null !== $off ) {
		return $off;
	}

	$type = blueworx_mcp_surecart_resolve_type( $args );
	if ( is_wp_error( $type ) ) {
		return $type;
	}

	$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
	$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
	$query    = array(
		'page'     => $page,
		'per_page' => $per_page,
	);

	$text = function ( $key ) use ( $args ) {
		return isset( $args[ $key ] ) && is_scalar( $args[ $key ] ) ? trim( sanitize_text_field( (string) $args[ $key ] ) ) : '';
	};

	// Argument => array( SureCart query name, takes a list, filter table key ).
	$takes   = blueworx_mcp_surecart_filter_types();
	$ignored = array();
	foreach ( array(
		'search'      => array( 'query', false, 'search' ),
		'status'      => array( 'status', true, 'status' ),
		'customer_id' => array( 'customer_ids', true, 'customer_ids' ),
		'product_id'  => array( 'product_ids', true, 'product_ids' ),
	) as $arg => $map ) {
		if ( '' === $text( $arg ) ) {
			continue;
		}
		if ( ! in_array( $type, $takes[ $map[2] ], true ) ) {
			$ignored[] = $arg;
			continue;
		}
		$query[ $map[0] ] = $map[1] ? array( $text( $arg ) ) : $text( $arg );
	}

	$types  = blueworx_mcp_surecart_types();
	$answer = blueworx_mcp_surecart_request( substr( $types[ $type ]['route'], strlen( '/surecart/v1' ) ), $query );
	if ( is_wp_error( $answer ) ) {
		return $answer;
	}

	$records = array();
	foreach ( (array) $answer['data'] as $record ) {
		if ( ! is_array( $record ) ) {
			continue;
		}
		$shaped               = blueworx_mcp_surecart_shape( $record );
		$shaped['admin_link'] = blueworx_mcp_surecart_admin_link( $type, isset( $record['id'] ) ? (string) $record['id'] : '' );
		$records[]            = $shaped;
	}

	$out = array(
		'type'       => $type,
		'page'       => $page,
		'per_page'   => $per_page,
		'total'      => $answer['total'],
		'records'    => $records,
		'admin_link' => blueworx_mcp_surecart_admin_link( $type ),
	);

	// Say so when a filter was not applied, so an unfiltered list never reads as filtered.
	if ( ! empty( $ignored ) ) {
		$out['ignored_filters'] = $ignored;
	}

	return $out;
}

/**
 * Tool: get_store_record.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_surecart_get_record( $args ) {
	$off = blueworx_mcp_surecart_unavailable();
	if ( null !== $off ) {
		return $off;
	}

	$type = blueworx_mcp_surecart_resolve_type( $args );
	if ( is_wp_error( $type ) ) {
		return $type;
	}

	$id = isset( $args['id'] ) && is_scalar( $args['id'] ) ? trim( (string) $args['id'] ) : '';
	if ( 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $id ) ) {
		return new WP_Error( 'invalid_id', 'Give the record ID, using only letters, numbers, dashes and underscores.' );
	}

	$types  = blueworx_mcp_surecart_types();
	$answer = blueworx_mcp_surecart_request( substr( $types[ $type ]['route'], strlen( '/surecart/v1' ) ) . '/' . $id );
	if ( is_wp_error( $answer ) ) {
		return $answer;
	}

	$shaped               = blueworx_mcp_surecart_shape( is_array( $answer['data'] ) ? $answer['data'] : array() );
	$shaped['admin_link'] = blueworx_mcp_surecart_admin_link( $type, $id );
	return $shaped;
}

/**
 * Tool: get_store_sales.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_surecart_get_sales( $args ) {
	$off = blueworx_mcp_surecart_unavailable();
	if ( null !== $off ) {
		return $off;
	}

	$report = isset( $args['report'] ) && is_scalar( $args['report'] ) ? (string) $args['report'] : 'orders';
	if ( ! in_array( $report, blueworx_mcp_surecart_reports(), true ) ) {
		return new WP_Error( 'unknown_report', 'Unknown report "' . $report . '". The reports are: ' . implode( ', ', blueworx_mcp_surecart_reports() ) . '.' );
	}

	foreach ( array( 'from', 'to' ) as $name ) {
		$valid = blueworx_mcp_surecart_check_date( isset( $args[ $name ] ) ? $args[ $name ] : '', $name );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
	}
	if ( (string) $args['from'] > (string) $args['to'] ) {
		return new WP_Error( 'invalid_range', 'from must be on or before to.' );
	}

	$interval = isset( $args['interval'] ) && is_scalar( $args['interval'] ) ? (string) $args['interval'] : 'month';
	if ( ! in_array( $interval, array( 'day', 'week', 'month', 'year' ), true ) ) {
		return new WP_Error( 'invalid_interval', 'interval must be day, week, month or year.' );
	}

	// A bare date as end_at runs to the end of that day (SureCart echoes 23:59:59 UTC), so "to" is inclusive.
	$answer = blueworx_mcp_surecart_request(
		'/stats/' . $report,
		array(
			'start_at' => (string) $args['from'],
			'end_at'   => (string) $args['to'],
			'interval' => $interval,
		)
	);
	if ( is_wp_error( $answer ) ) {
		return $answer;
	}

	$data     = is_array( $answer['data'] ) ? $answer['data'] : array();
	$currency = isset( $data['query']['currency'] ) ? (string) $data['query']['currency'] : '';
	$rows     = array();
	$amount   = 0;
	$count    = 0;

	foreach ( (array) ( $data['data'] ?? array() ) as $row ) {
		$row     = (array) $row;
		$row_amt = (int) ( $row['amount'] ?? 0 );
		$row_avg = (int) ( $row['average_amount'] ?? 0 );
		$row_cnt = (int) ( $row['count'] ?? 0 );
		$amount += $row_amt;
		$count  += $row_cnt;
		$rows[]  = array(
			// SureCart buckets in UTC (its query echoes UTC-midnight stamps), so read them as UTC, not the site's zone.
			'interval_at'            => gmdate( 'Y-m-d', (int) ( $row['interval_at'] ?? 0 ) ),
			'amount'                 => $row_amt,
			'amount_display'         => blueworx_mcp_surecart_money( $row_amt, $currency ),
			'average_amount'         => $row_avg,
			'average_amount_display' => blueworx_mcp_surecart_money( $row_avg, $currency ),
			'count'                  => $row_cnt,
		);
	}

	$average = $count > 0 ? (int) round( $amount / $count ) : 0;

	return array(
		'report'   => $report,
		'interval' => $interval,
		'currency' => $currency,
		'rows'     => $rows,
		'totals'   => array(
			'amount'                 => $amount,
			'amount_display'         => blueworx_mcp_surecart_money( $amount, $currency ),
			'average_amount'         => $average,
			'average_amount_display' => blueworx_mcp_surecart_money( $average, $currency ),
			'count'                  => $count,
		),
	);
}
