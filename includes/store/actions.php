<?php
/**
 * The customer dashboard's write journeys — updating billing details,
 * adding a card, opening an order, cancelling a plan.
 *
 * SureCart composes its own dashboard from one wrapper block that reads
 * `model` and `action` off the address and hands the request to a
 * controller, plus the leaf blocks that draw each read-only panel. The
 * customer dashboard replaces that wrapper with its own frame and renders
 * only the leaves, so every link SureCart draws — Update, Add, Payment
 * History, Cancel — came back to the same read-only panel and did nothing
 * at all.
 *
 * This is the part that was missing: which addresses mean an action, and
 * which of our panels that action belongs under. The rendering is still
 * SureCart's — done by rendering its own wrapper block — so its
 * controllers, its permission checks and its markup are the ones that run.
 *
 * The map is SureCart's, read from its DashboardPage block. A model it does
 * not know, or an action its controller has no method for, is not an
 * action at all: the customer dashboard draws its normal panels, which is
 * what an old bookmark or a hand-typed address should do.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The query args SureCart's links carry. */
define( 'BLUEWORX_STORE_ACTION_MODEL_ARG', 'model' );
define( 'BLUEWORX_STORE_ACTION_ARG', 'action' );

/**
 * SureCart's model-to-controller map, from its DashboardPage block.
 *
 * @return array<string,string>
 */
function blueworx_store_action_controllers() {
	return array(
		'subscription'   => '\SureCartBlocks\Controllers\SubscriptionController',
		'payment_method' => '\SureCartBlocks\Controllers\PaymentMethodController',
		'charge'         => '\SureCartBlocks\Controllers\ChargeController',
		'order'          => '\SureCartBlocks\Controllers\OrderController',
		'user'           => '\SureCartBlocks\Controllers\UserController',
		'customer'       => '\SureCartBlocks\Controllers\CustomerController',
		'download'       => '\SureCartBlocks\Controllers\DownloadController',
		'invoice'        => '\SureCartBlocks\Controllers\InvoiceController',
		'license'        => '\SureCartBlocks\Controllers\LicenseController',
	);
}

/**
 * Which of our panels a model's action belongs under, so the nav goes on
 * highlighting the thing the member is actually looking at.
 *
 * SureCart's own links drop every query arg but its own, so the view named
 * in the address does not survive the click. This puts it back.
 *
 * @return array<string,string>
 */
function blueworx_store_action_views() {
	return array(
		'customer'       => 'account',
		'user'           => 'account',
		'payment_method' => 'account',
		'subscription'   => 'plans',
		'order'          => 'orders',
		'charge'         => 'orders',
		'invoice'        => 'invoices',
	);
}

/**
 * Whether this address asks for something to be done rather than read.
 *
 * Pure: the caller says which controllers and methods the shop on this site
 * actually has, so the rule is testable without SureCart present.
 *
 * @param string   $model  The model named in the address.
 * @param string   $action The action named in the address.
 * @param callable $exists Does this controller have this method? Answers a bool.
 * @return bool
 */
function blueworx_store_is_action( $model, $action, $exists ) {
	$model       = trim( $model );
	$action      = trim( $action );
	$controllers = blueworx_store_action_controllers();
	if ( '' === $model || '' === $action || ! isset( $controllers[ $model ] ) ) {
		return false;
	}
	return (bool) $exists( $controllers[ $model ], $action );
}

/**
 * The panel an action belongs under, or '' when it belongs under none —
 * downloads and licences, which the customer dashboard offers no panel
 * for and which no site sells.
 *
 * @param string $model The model named in the address.
 * @return string
 */
function blueworx_store_action_view( $model ) {
	$views = blueworx_store_action_views();
	$model = trim( $model );
	return isset( $views[ $model ] ) ? $views[ $model ] : '';
}

/**
 * SureCart's own wrapper block: the piece that does the routing.
 *
 * @return string
 */
function blueworx_store_action_block() {
	return 'surecart/dashboard-page';
}

/**
 * Swap the "does this controller have this method" test.
 *
 * A seam, like the plugin slot's: SureCart's classes are not loaded in a
 * unit test, so without one every action address would answer "not an
 * action" and the routing could only be exercised on a live site.
 *
 * @param callable|null $check Null restores the real one.
 * @return void
 */
function blueworx_store_set_action_check( $check ) {
	$GLOBALS['blueworx_store_action_check'] = $check;
}

/**
 * The real "does this controller have this method" test, for the site.
 *
 * Exactly the check SureCart's own wrapper makes before dispatching, so an
 * address this says yes to is one SureCart will act on.
 *
 * @return callable
 */
function blueworx_store_action_check() {
	if ( isset( $GLOBALS['blueworx_store_action_check'] ) && null !== $GLOBALS['blueworx_store_action_check'] ) {
		return $GLOBALS['blueworx_store_action_check'];
	}
	return static function ( $controller, $action ) {
		return class_exists( $controller ) && method_exists( $controller, $action );
	};
}

/**
 * What the address is asking for.
 *
 * @return array{model:string,action:string}
 */
function blueworx_store_requested_action() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading which screen to draw; SureCart's own controllers check permissions before acting on anything.
	$model  = isset( $_GET[ BLUEWORX_STORE_ACTION_MODEL_ARG ] ) && is_string( $_GET[ BLUEWORX_STORE_ACTION_MODEL_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ BLUEWORX_STORE_ACTION_MODEL_ARG ] ) ) : '';
	$action = isset( $_GET[ BLUEWORX_STORE_ACTION_ARG ] ) && is_string( $_GET[ BLUEWORX_STORE_ACTION_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ BLUEWORX_STORE_ACTION_ARG ] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	return array(
		'model'  => $model,
		'action' => $action,
	);
}
