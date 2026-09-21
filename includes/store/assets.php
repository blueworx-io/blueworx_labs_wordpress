<?php
/**
 * The store pages' stylesheets and script, and which pages get them.
 *
 * Loaded on the three pages this feature dresses — checkout, thank-you and
 * the customer dashboard — and nowhere else. The site's public pages are the
 * theme's, and the two never meet: every rule in store.css sits under a class
 * only our frame carries.
 *
 * Registered rather than enqueued at load: whether a request is one of ours
 * is decided per request, by blueworx_store_page_key() below.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The frame's stylesheet. Sits on top of the design system's. */
define( 'BLUEWORX_STORE_STYLE', 'blueworx-store' );

/** SureCart's field theme: its tokens mapped onto the design system's. */
define( 'BLUEWORX_STORE_SURECART_STYLE', 'blueworx-store-surecart' );

/** The dashboard's script, which upgrades the view links in place. */
define( 'BLUEWORX_STORE_DASHBOARD_SCRIPT', 'blueworx-store-dashboard' );

/**
 * Which page this feature dresses a post is — 'checkout', 'order-confirmation'
 * or 'dashboard' — and '' for every other post on the site.
 *
 * An id of 0 means the shop has not recorded that page, and must never
 * match — 0 would otherwise dress whatever a broken query returned.
 *
 * @param int $post_id Post id.
 * @return string
 */
function blueworx_store_page_key( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return '';
	}
	foreach ( array( 'checkout', 'order-confirmation', 'dashboard' ) as $key ) {
		$page_id = blueworx_store_page_id( $key );
		if ( $page_id > 0 && $post_id === $page_id ) {
			return $key;
		}
	}
	return '';
}

/**
 * Which store page this request is, or '' for anything else.
 *
 * Gated on is_singular(): get_queried_object_id() answers a term id on a
 * category archive and a user id on an author archive, and either can equal
 * a page id by coincidence. Only a single post can be one of the pages.
 *
 * @return string 'checkout', 'order-confirmation', 'dashboard' or ''.
 */
function blueworx_store_queried_page_key() {
	if ( ! function_exists( 'is_singular' ) || ! is_singular() || ! function_exists( 'get_queried_object_id' ) ) {
		return '';
	}
	return blueworx_store_page_key( (int) get_queried_object_id() );
}

/**
 * Whether this page needs the SureCart token mapping. Pure.
 *
 * Checkout alone. The order confirmation page renders SureCart's
 * confirmation blocks, which are read-only text rather than fields, and
 * loading a field theme there would be dead weight on the one page a buyer
 * lands on straight after paying.
 *
 * @param string $page_key From blueworx_store_page_key().
 * @return bool
 */
function blueworx_store_wants_surecart_style( $page_key ) {
	return 'checkout' === $page_key;
}

/**
 * Tell WordPress the assets exist, and put them on the page when this
 * request is one of ours.
 *
 * The decision is made here, on wp_enqueue_scripts, rather than left to the
 * content filters that draw the page: by the time those run the head has
 * already been printed, so the stylesheet arrives in the footer and the
 * member watches the page snap into shape after it has loaded. On checkout
 * that flash lands on a payment form, which is the worst place for it.
 * The queried object is known this early, which is all the decision needs.
 *
 * @return void
 */
function blueworx_store_declare_assets() {
	if ( ! function_exists( 'wp_register_style' ) || ! defined( 'BLUEWORX_LABS_URL' ) ) {
		return;
	}
	$version = defined( 'BLUEWORX_LABS_VERSION' ) ? BLUEWORX_LABS_VERSION : null;

	wp_register_style(
		BLUEWORX_STORE_STYLE,
		BLUEWORX_LABS_URL . 'assets/css/store.css',
		array( 'blueworx-admin-design' ),
		$version
	);
	wp_register_style(
		BLUEWORX_STORE_SURECART_STYLE,
		BLUEWORX_LABS_URL . 'assets/css/store-surecart.css',
		array( BLUEWORX_STORE_STYLE ),
		$version
	);
	if ( function_exists( 'wp_register_script' ) ) {
		wp_register_script(
			BLUEWORX_STORE_DASHBOARD_SCRIPT,
			BLUEWORX_LABS_URL . 'assets/js/store-dashboard.js',
			array(),
			$version,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	$key = blueworx_store_queried_page_key();
	if ( '' === $key ) {
		return;
	}
	blueworx_store_enqueue_frame();
	if ( blueworx_store_wants_surecart_style( $key ) ) {
		wp_enqueue_style( BLUEWORX_STORE_SURECART_STYLE );
	}
	if ( 'dashboard' === $key ) {
		blueworx_store_enqueue_dashboard();
	}
}

/**
 * Put the frame's look on this page: the design system, its icon module,
 * and our layout on top. Safe to call more than once.
 *
 * The icons module is what inlines every <i data-lucide="…"> the shell
 * emits; without it each icon is an empty element, which reads as a missing
 * icon rather than a missing script.
 *
 * @return void
 */
function blueworx_store_enqueue_frame() {
	if ( function_exists( 'blueworx_admin_design_enqueue' ) ) {
		blueworx_admin_design_enqueue();
	}
	if ( function_exists( 'blueworx_admin_design_enqueue_icons' ) ) {
		blueworx_admin_design_enqueue_icons();
	}
	if ( function_exists( 'wp_enqueue_style' ) ) {
		wp_enqueue_style( BLUEWORX_STORE_STYLE );
	}
}

/**
 * Everything the customer dashboard needs: the frame, its own script, and
 * the shop's components for the panels inside it.
 *
 * @return void
 */
function blueworx_store_enqueue_dashboard() {
	blueworx_store_enqueue_frame();
	if ( function_exists( 'wp_enqueue_script' ) ) {
		wp_enqueue_script( BLUEWORX_STORE_DASHBOARD_SCRIPT );
	}
	blueworx_store_enqueue_shop_assets();
}

/**
 * The shop's own components and field theme, for the panels the dashboard
 * draws. Only what SureCart has registered on this request — nothing here
 * knows where its files live, and a shop that is not installed registers
 * nothing.
 *
 * @return void
 */
function blueworx_store_enqueue_shop_assets() {
	if ( ! function_exists( 'wp_script_is' ) || ! function_exists( 'wp_enqueue_script' ) ) {
		return;
	}
	if ( wp_script_is( 'surecart-components', 'registered' ) ) {
		wp_enqueue_script( 'surecart-components' );
	}
	if ( function_exists( 'wp_style_is' ) && wp_style_is( 'surecart-themes-default', 'registered' ) ) {
		wp_enqueue_style( 'surecart-themes-default' );
	}
}

if ( function_exists( 'add_action' ) && function_exists( 'blueworx_feature_enabled' ) && blueworx_feature_enabled( 'store_pages' ) ) {
	add_action( 'wp_enqueue_scripts', 'blueworx_store_declare_assets' );
}
