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
		'prices'                 => array( false, 'sc-products' ),
		'variants'               => array( false, 'sc-products' ),
		'variant_options'        => array( false, '' ),
		'product_collections'    => array( false, 'sc-product-collections' ),
		'product_groups'         => array( false, '' ),
		'product_medias'         => array( false, '' ),
		'bundle_items'           => array( false, '' ),
		'coupons'                => array( false, 'sc-coupons' ),
		'promotions'             => array( false, '' ),
		'bumps'                  => array( false, 'sc-bumps' ),
		'upsells'                => array( false, '' ),
		'upsell_funnels'         => array( false, 'sc-upsell-funnels' ),
		'auto_fees'              => array( false, '' ),
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
		'checkouts'              => array( true, '' ),
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
		'reviews'                => array( true, '' ),
		'affiliations'           => array( true, 'sc-affiliates' ),
		'affiliation_requests'   => array( true, '' ),
		'referrals'              => array( true, '' ),
		'payouts'                => array( true, '' ),
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
