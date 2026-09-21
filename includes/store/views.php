<?php
/**
 * What the customer dashboard is made of: the views, in order, and what
 * each shows.
 *
 * One declarative list because three things have to agree — the nav, the
 * router that decides which view an address means, and the panel that
 * renders it. Two lists is how a nav item comes to point at a view that
 * does not exist.
 *
 * The block names are SureCart's own, read from its source: its customer
 * dashboard is composed of these separate blocks rather than one, which is
 * what makes one block per panel possible. customer-downloads and
 * customer-licenses are deliberately absent — no site sells either, and an
 * empty panel on every account page is a cost with no reader.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where a member lands with no view named, and the fallback for anything unrecognised. */
define( 'BLUEWORX_STORE_DEFAULT_VIEW', 'dashboard' );

/**
 * Every view this plugin knows how to draw when SureCart is here, in the
 * order the nav shows them.
 *
 * 'icon' names the glyph the shell draws. 'blocks' are rendered in order,
 * each in its own card. 'shortcode' takes the whole view.
 *
 * 'where' names which of the phone's bottom bar and the desktop sidebar a
 * view belongs to: 'both', 'side' or 'bar'. The bar is a curated list, not
 * "whichever views fit" — see blueworx_store_views_side() and
 * blueworx_store_views_bar().
 *
 * @return array<int,array{key:string,label:string,title:string,lede:string,icon:string,where:string,blocks:array<int,string>,shortcode:string}>
 */
function blueworx_store_default_views() {
	return array(
		array(
			'key'       => 'dashboard',
			'label'     => 'Dashboard',
			'title'     => 'Your account',
			'lede'      => 'Everything the site keeps for you, in one place.',
			'icon'      => 'layout-dashboard',
			'where'     => 'both',
			'blocks'    => array(),
			'shortcode' => '',
		),
		array(
			'key'       => 'orders',
			'label'     => 'Orders',
			'title'     => 'Orders',
			'lede'      => 'Everything you have bought.',
			'icon'      => 'shopping-cart',
			'where'     => 'side',
			'blocks'    => array( 'surecart/customer-orders' ),
			'shortcode' => '',
		),
		array(
			'key'       => 'invoices',
			'label'     => 'Invoices',
			'title'     => 'Invoices',
			'lede'      => 'Your receipts, and anything still to pay.',
			'icon'      => 'file-spreadsheet',
			'where'     => 'side',
			'blocks'    => array( 'surecart/customer-invoices' ),
			'shortcode' => '',
		),
		array(
			'key'       => 'billing',
			'label'     => 'Billing',
			'title'     => 'Billing',
			'lede'      => 'What you pay, what you have bought, and what you owe.',
			'icon'      => 'file-spreadsheet',
			'where'     => 'bar',
			// The phone's one money screen, so it carries the same three
			// panels the sidebar splits into Plans, Orders and Invoices.
			'blocks'    => array( 'surecart/customer-subscriptions', 'surecart/customer-orders', 'surecart/customer-invoices' ),
			'shortcode' => '',
		),
		array(
			'key'       => 'plans',
			'label'     => 'Plans',
			'title'     => 'Your membership',
			'lede'      => 'What you pay, how often, and when it renews.',
			'icon'      => 'refresh-cw',
			'where'     => 'side',
			'blocks'    => array( 'surecart/customer-subscriptions' ),
			'shortcode' => '',
		),
		array(
			'key'       => 'profile',
			'label'     => 'Profile',
			'title'     => 'Your profile',
			'lede'      => 'Who you are, and what the site keeps about you.',
			'icon'      => 'users',
			'where'     => 'both',
			'blocks'    => array( 'surecart/wordpress-account' ),
			'shortcode' => '',
		),
		array(
			'key'       => 'account',
			'label'     => 'Account',
			'title'     => 'Account details',
			'lede'      => 'How you pay.',
			'icon'      => 'credit-card',
			// Off the phone's bottom bar — Billing is already the phone's one
			// money screen and carries both of these panels, so a second bar
			// item would lead to the same thing by another name.
			'where'     => 'side',
			// The money: the address billed, and the cards on file.
			'blocks'    => array( 'surecart/customer-billing-details', 'surecart/customer-payment-methods' ),
			'shortcode' => '',
		),
	);
}

/**
 * Fill in whatever a filter's view left out, drop anything not shaped like
 * a view, and make sure the dashboard is present and first.
 *
 * @param array $views Candidate views, as handed back from a filter.
 * @return array<int,array<string,mixed>>
 */
function blueworx_store_normalize_views( $views ) {
	$out  = array();
	$seen = array();
	foreach ( $views as $view ) {
		if ( ! is_array( $view ) || empty( $view['key'] ) ) {
			continue;
		}
		$key = (string) $view['key'];
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$label        = isset( $view['label'] ) ? (string) $view['label'] : ucfirst( $key );
		$out[]        = array(
			'key'       => $key,
			'label'     => $label,
			'title'     => isset( $view['title'] ) ? (string) $view['title'] : $label,
			'lede'      => isset( $view['lede'] ) ? (string) $view['lede'] : '',
			'icon'      => isset( $view['icon'] ) ? (string) $view['icon'] : 'layout-dashboard',
			'where'     => isset( $view['where'] ) ? (string) $view['where'] : 'both',
			'blocks'    => isset( $view['blocks'] ) && is_array( $view['blocks'] ) ? $view['blocks'] : array(),
			'shortcode' => isset( $view['shortcode'] ) ? (string) $view['shortcode'] : '',
		);
	}

	$dashboard_index = null;
	foreach ( $out as $index => $view ) {
		if ( BLUEWORX_STORE_DEFAULT_VIEW === $view['key'] ) {
			$dashboard_index = $index;
			break;
		}
	}

	if ( null === $dashboard_index ) {
		$defaults    = blueworx_store_default_views();
		array_unshift( $out, $defaults[0] );
	} elseif ( 0 !== $dashboard_index ) {
		$dashboard = $out[ $dashboard_index ];
		unset( $out[ $dashboard_index ] );
		array_unshift( $out, $dashboard );
		$out = array_values( $out );
	}

	return array_values( $out );
}

/**
 * The customer dashboard's views, in nav order — SureCart's when it is
 * here, other plugins' panels folded in through the blueworx_store_views
 * filter, and just the dashboard when it is not.
 *
 * Memoised: the filter is applied once per request.
 *
 * @return array<int,array<string,mixed>>
 */
function blueworx_store_views() {
	static $views = null;
	if ( null !== $views ) {
		return $views;
	}

	$defaults = blueworx_store_default_views();
	$base     = blueworx_store_surecart_active() ? $defaults : array( $defaults[0] );

	/**
	 * Filters the customer dashboard's views, in nav order.
	 *
	 * Add a view for your plugin's panel, remove one, or reorder them. See
	 * docs/store-pages-api.md for the shape of each entry.
	 *
	 * @param array $views Views, each an array with key, label, title, lede, icon, where, blocks, shortcode.
	 */
	$views = blueworx_store_normalize_views( apply_filters( 'blueworx_store_views', $base ) );

	return $views;
}

/**
 * The views the desktop sidebar offers.
 *
 * @param array<int,array<string,mixed>> $views Views to filter.
 * @return array<int,array<string,mixed>>
 */
function blueworx_store_views_side( $views ) {
	return array_values(
		array_filter(
			$views,
			static function ( $view ) {
				return in_array( (string) ( $view['where'] ?? '' ), array( 'both', 'side' ), true );
			}
		)
	);
}

/**
 * The views the phone's bottom bar offers, in bar order.
 *
 * @param array<int,array<string,mixed>> $views Views to filter.
 * @return array<int,array<string,mixed>>
 */
function blueworx_store_views_bar( $views ) {
	return array_values(
		array_filter(
			$views,
			static function ( $view ) {
				return in_array( (string) ( $view['where'] ?? '' ), array( 'both', 'bar' ), true );
			}
		)
	);
}

/**
 * Which view an address means. Anything not on the list — junk, a typo, a
 * bookmark from before a plugin was removed — lands on the dashboard, which
 * always exists.
 *
 * @param string                          $requested The requested view key.
 * @param array<int,array<string,mixed>>  $available From blueworx_store_views().
 * @return string
 */
function blueworx_store_resolve_view( $requested, $available ) {
	$requested = trim( $requested );
	foreach ( $available as $view ) {
		if ( $requested === (string) $view['key'] ) {
			return $requested;
		}
	}
	return BLUEWORX_STORE_DEFAULT_VIEW;
}

/**
 * One view by key, or null.
 *
 * @param string                          $key   View key.
 * @param array<int,array<string,mixed>>  $views Views to search.
 * @return array<string,mixed>|null
 */
function blueworx_store_find_view( $key, $views ) {
	foreach ( $views as $view ) {
		if ( $key === (string) $view['key'] ) {
			return $view;
		}
	}
	return null;
}
