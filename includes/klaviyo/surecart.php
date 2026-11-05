<?php
/**
 * Klaviyo connection: what SureCart tells us.
 *
 * SureCart's own link to WordPress does not carry failed payments, cancellations
 * or refunds, so this is a second webhook of its own, set up in the SureCart
 * dashboard with its own signing secret. It leaves SureCart's link alone, and
 * Stripe is never involved: SureCart already reports every payment with the plan
 * attached, and connecting Stripe as well would count each payment twice.
 *
 * The address only has to acknowledge quickly. SureCart gives it about ten
 * seconds, and its events carry no related records, so the event is checked and
 * queued here and looked up properly in the background.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which SureCart events this listens for. The setup steps tick the same list.
 *
 * @return string[]
 */
function blueworx_klaviyo_surecart_events() {
	return array(
		'purchase.created',
		'subscription.renewed',
		'order.payment_failed',
		'subscription.set_to_cancel',
		'subscription.canceled',
		'subscription.updated',
		'refund.succeeded',
	);
}

/**
 * The address to give SureCart.
 *
 * @return string
 */
function blueworx_klaviyo_webhook_url() {
	return rest_url( 'blueworx/v1/klaviyo/surecart' );
}

/**
 * Adds the address, only while the function is on.
 *
 * @return void
 */
function blueworx_klaviyo_register_route() {
	if ( ! blueworx_feature_enabled( 'klaviyo' ) ) {
		return;
	}

	register_rest_route(
		'blueworx/v1',
		'/klaviyo/surecart',
		array(
			'methods'             => 'POST',
			'callback'            => 'blueworx_klaviyo_receive_webhook',
			// The signature checked in the callback is the permission: SureCart has
			// no WordPress account, and the check needs the raw body anyway.
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'blueworx_klaviyo_register_route' );

/**
 * Checks an event really came from SureCart.
 *
 * Refuses everything until a secret is set. An old timestamp is refused too, so
 * a captured request cannot be replayed; SureCart signs each retry afresh, so a
 * genuine retry is never caught by this.
 *
 * @param string   $body      Raw request body.
 * @param string   $signature The x-webhook-signature header.
 * @param string   $timestamp The x-webhook-timestamp header.
 * @param string   $secret    Signing secret.
 * @param int|null $now       Current time, for tests.
 * @return bool
 */
function blueworx_klaviyo_signature_valid( $body, $signature, $timestamp, $secret, $now = null ) {
	if ( '' === (string) $secret || '' === (string) $signature || ! is_numeric( $timestamp ) ) {
		return false;
	}

	$now = null === $now ? time() : $now;

	if ( abs( $now - (int) $timestamp ) > 5 * MINUTE_IN_SECONDS ) {
		return false;
	}

	return hash_equals( hash_hmac( 'sha256', $timestamp . '.' . $body, (string) $secret ), (string) $signature );
}

/**
 * Handles one delivery from SureCart.
 *
 * @param WP_REST_Request $request The delivery.
 * @return WP_REST_Response|WP_Error
 */
function blueworx_klaviyo_receive_webhook( $request ) {
	$body = (string) $request->get_body();

	if ( ! blueworx_klaviyo_signature_valid( $body, (string) $request->get_header( 'x-webhook-signature' ), (string) $request->get_header( 'x-webhook-timestamp' ), blueworx_klaviyo_webhook_secret() ) ) {
		// Noted for the settings screen, which is where a wrong secret gets noticed.
		// Not a log line: anyone can post here, and would push out the real ones.
		if ( (int) get_option( 'blueworx_klaviyo_last_refused', 0 ) < time() - MINUTE_IN_SECONDS ) {
			update_option( 'blueworx_klaviyo_last_refused', time(), 'no' );
		}

		return new WP_Error( 'blueworx_klaviyo_bad_signature', 'Invalid signature.', array( 'status' => 401 ) );
	}

	$event = json_decode( $body, true );

	if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['type'] ) ) {
		return new WP_Error( 'blueworx_klaviyo_bad_event', 'Not a SureCart event.', array( 'status' => 400 ) );
	}

	$status = blueworx_klaviyo_accept_event( $event );

	return new WP_REST_Response( array( 'status' => $status ), 200 );
}

/**
 * Decides what to do with a genuine event, and queues it if it matters.
 *
 * @param array $event The decoded event.
 * @return string What happened: queued, repeat, ignored or test_mode.
 */
function blueworx_klaviyo_accept_event( $event ) {
	if ( ! in_array( $event['type'], blueworx_klaviyo_surecart_events(), true ) ) {
		return 'ignored';
	}

	$object = isset( $event['data']['object'] ) && is_array( $event['data']['object'] ) ? $event['data']['object'] : array();

	if ( isset( $object['live_mode'] ) && false === $object['live_mode'] && '1' !== blueworx_klaviyo_setting( 'send_test_mode' ) ) {
		return 'test_mode';
	}

	if ( blueworx_klaviyo_seen( $event['id'] ) ) {
		return 'repeat';
	}

	blueworx_klaviyo_mark_seen( $event['id'] );

	blueworx_klaviyo_enqueue(
		'webhook:' . $event['id'],
		array(
			'label'   => $event['type'],
			'webhook' => array(
				'type'     => $event['type'],
				'id'       => isset( $object['id'] ) ? $object['id'] : '',
				'previous' => isset( $event['data']['previous_attributes'] ) && is_array( $event['data']['previous_attributes'] ) ? $event['data']['previous_attributes'] : array(),
			),
		)
	);

	return 'queued';
}

/**
 * Looks up one SureCart record, with what the plans need expanded.
 *
 * The filter is the seam the tests use, since there is no SureCart account to
 * talk to from a test site.
 *
 * @param string $type subscription, purchase, order, refund or price.
 * @param string $id   Record ID.
 * @return array|WP_Error The record as plain arrays.
 */
function blueworx_klaviyo_surecart_record( $type, $id ) {
	$short = apply_filters( 'blueworx_klaviyo_surecart_record', null, $type, $id );

	if ( null !== $short ) {
		return $short;
	}

	$expand = array(
		'subscription' => array( 'customer', 'price', 'price.product', 'current_period', 'period.checkout', 'current_cancellation_act', 'cancellation_act.cancellation_reason' ),
		'purchase'     => array( 'initial_order', 'order.checkout', 'checkout.discount', 'discount.promotion' ),
		'order'        => array( 'checkout' ),
		'refund'       => array( 'customer', 'charge', 'charge.checkout', 'checkout.line_items', 'line_item.price', 'price.product' ),
		'price'        => array( 'product' ),
	);

	$classes = array(
		'subscription' => '\SureCart\Models\Subscription',
		'purchase'     => '\SureCart\Models\Purchase',
		'order'        => '\SureCart\Models\Order',
		'refund'       => '\SureCart\Models\Refund',
		'price'        => '\SureCart\Models\Price',
	);

	if ( ! isset( $classes[ $type ] ) || ! class_exists( $classes[ $type ] ) ) {
		return new WP_Error( 'blueworx_klaviyo_no_surecart', 'SureCart is not available.' );
	}

	$record = $classes[ $type ]::with( $expand[ $type ] )->find( $id );

	// The reason for a cancellation is a nicety. If asking for it is what failed,
	// ask again without it rather than lose the event.
	if ( is_wp_error( $record ) && 'subscription' === $type ) {
		$record = $classes[ $type ]::with( array_slice( $expand[ $type ], 0, 5 ) )->find( $id );
	}

	if ( is_wp_error( $record ) ) {
		return $record;
	}

	return blueworx_klaviyo_plain( $record );
}

/**
 * Turns SureCart's models into plain arrays, however deep they go.
 *
 * @param mixed $record A model or array.
 * @return array
 */
function blueworx_klaviyo_plain( $record ) {
	$plain = json_decode( (string) wp_json_encode( is_object( $record ) && method_exists( $record, 'toArray' ) ? $record->toArray() : $record ), true );

	return is_array( $plain ) ? $plain : array();
}

/**
 * Counts how many payments a subscription has made, for payment_number.
 *
 * A figure that cannot be worked out is left off rather than guessed, so it can
 * never hold up the renewal it belongs to.
 *
 * @param string $subscription_id Subscription ID.
 * @return int|null
 */
function blueworx_klaviyo_payment_number( $subscription_id ) {
	$short = apply_filters( 'blueworx_klaviyo_payment_number', null, $subscription_id );

	if ( null !== $short ) {
		return $short;
	}

	if ( ! class_exists( '\SureCart\Models\Period' ) ) {
		return null;
	}

	$periods = \SureCart\Models\Period::where( array( 'subscription_ids' => array( $subscription_id ) ) )->paginate( array( 'per_page' => 100 ) );

	if ( is_wp_error( $periods ) || empty( $periods->data ) ) {
		return null;
	}

	$paid = 0;

	foreach ( $periods->data as $period ) {
		if ( 'paid' === ( isset( $period->status ) ? $period->status : '' ) ) {
			++$paid;
		}
	}

	return $paid > 0 ? $paid : null;
}

/**
 * Finds the subscription a failed order was for.
 *
 * Orders do not name their subscription, and periods cannot be searched by
 * checkout, so this looks through the customer's subscriptions for the one whose
 * current period is that checkout. A failed first payment has no subscription
 * yet, which is why this can come back empty: that is not a renewal failing.
 *
 * @param array $order The order, with its checkout expanded.
 * @return string Subscription ID, or an empty string.
 */
function blueworx_klaviyo_subscription_for_order( $order ) {
	$short = apply_filters( 'blueworx_klaviyo_subscription_for_order', null, $order );

	if ( null !== $short ) {
		return $short;
	}

	$checkout = isset( $order['checkout'] ) && is_array( $order['checkout'] ) ? $order['checkout'] : array();
	$customer = isset( $checkout['customer'] ) ? $checkout['customer'] : '';
	$customer = is_array( $customer ) ? ( isset( $customer['id'] ) ? $customer['id'] : '' ) : $customer;

	if ( '' === (string) $customer || empty( $checkout['id'] ) || ! class_exists( '\SureCart\Models\Subscription' ) ) {
		return '';
	}

	$subscriptions = \SureCart\Models\Subscription::where( array( 'customer_ids' => array( $customer ) ) )->with( array( 'current_period' ) )->get();

	if ( is_wp_error( $subscriptions ) ) {
		return '';
	}

	foreach ( (array) $subscriptions as $subscription ) {
		$plain = blueworx_klaviyo_plain( $subscription );

		if ( isset( $plain['current_period']['checkout'] ) && $plain['current_period']['checkout'] === $checkout['id'] ) {
			return isset( $plain['id'] ) ? $plain['id'] : '';
		}
	}

	return '';
}

/**
 * Works out what a SureCart event means for Klaviyo.
 *
 * @param array $webhook {
 *     @type string $type     Event type.
 *     @type string $id       The record it is about.
 *     @type array  $previous What changed, when SureCart says.
 * }
 * @return array|WP_Error Plans to send, possibly none, or an error worth a retry.
 */
function blueworx_klaviyo_surecart_plans( $webhook ) {
	$settings = blueworx_klaviyo_settings();

	switch ( $webhook['type'] ) {
		case 'purchase.created':
			return blueworx_klaviyo_plans_for_purchase( $webhook['id'], $settings );

		case 'subscription.renewed':
			return blueworx_klaviyo_plans_for_renewal( $webhook['id'], $settings );

		case 'order.payment_failed':
			return blueworx_klaviyo_plans_for_failed_order( $webhook['id'], $settings );

		case 'subscription.set_to_cancel':
		case 'subscription.canceled':
			return blueworx_klaviyo_plans_for_cancel( $webhook['id'], $settings );

		case 'subscription.updated':
			return blueworx_klaviyo_plans_for_update( $webhook['id'], $webhook['previous'], $settings );

		case 'refund.succeeded':
			$refund = blueworx_klaviyo_surecart_record( 'refund', $webhook['id'] );
			return is_wp_error( $refund ) ? $refund : array_values( array_filter( array( blueworx_klaviyo_plan_refunded( $refund ) ) ) );
	}

	return array();
}

/**
 * Started Membership, from a new purchase. A one-off purchase is not a membership.
 *
 * @param string $purchase_id Purchase ID.
 * @param array  $settings    Feature settings.
 * @return array|WP_Error
 */
function blueworx_klaviyo_plans_for_purchase( $purchase_id, $settings ) {
	$purchase = blueworx_klaviyo_surecart_record( 'purchase', $purchase_id );

	if ( is_wp_error( $purchase ) ) {
		return $purchase;
	}

	$subscription_id = isset( $purchase['subscription'] ) ? $purchase['subscription'] : '';
	$subscription_id = is_array( $subscription_id ) ? ( isset( $subscription_id['id'] ) ? $subscription_id['id'] : '' ) : $subscription_id;

	if ( '' === (string) $subscription_id ) {
		return array();
	}

	$subscription = blueworx_klaviyo_surecart_record( 'subscription', $subscription_id );

	if ( is_wp_error( $subscription ) ) {
		return $subscription;
	}

	$order = isset( $purchase['initial_order'] ) && is_array( $purchase['initial_order'] ) ? $purchase['initial_order'] : array();

	return array_values( array_filter( array( blueworx_klaviyo_plan_started( $subscription, $order, $settings ) ) ) );
}

/**
 * Membership Payment Succeeded, once the renewal has really been paid.
 *
 * If SureCart reports the renewal before the money has settled this asks to be
 * tried again, and a renewal that never gets paid ends up in the log as failed.
 *
 * @param string $subscription_id Subscription ID.
 * @param array  $settings        Feature settings.
 * @return array|WP_Error
 */
function blueworx_klaviyo_plans_for_renewal( $subscription_id, $settings ) {
	$subscription = blueworx_klaviyo_surecart_record( 'subscription', $subscription_id );

	if ( is_wp_error( $subscription ) ) {
		return $subscription;
	}

	$status = isset( $subscription['current_period']['status'] ) ? $subscription['current_period']['status'] : '';

	if ( '' !== $status && 'paid' !== $status ) {
		return new WP_Error( 'blueworx_klaviyo_not_paid', 'SureCart has not confirmed this renewal as paid yet.' );
	}

	return array_values( array_filter( array( blueworx_klaviyo_plan_renewed( $subscription, blueworx_klaviyo_payment_number( $subscription_id ), $settings ) ) ) );
}

/**
 * Membership Payment Failed, from a failed order.
 *
 * @param string $order_id Order ID.
 * @param array  $settings Feature settings.
 * @return array|WP_Error
 */
function blueworx_klaviyo_plans_for_failed_order( $order_id, $settings ) {
	$order = blueworx_klaviyo_surecart_record( 'order', $order_id );

	if ( is_wp_error( $order ) ) {
		return $order;
	}

	$subscription_id = blueworx_klaviyo_subscription_for_order( $order );

	if ( '' === $subscription_id ) {
		return array();
	}

	$subscription = blueworx_klaviyo_surecart_record( 'subscription', $subscription_id );

	if ( is_wp_error( $subscription ) ) {
		return $subscription;
	}

	return array_values( array_filter( array( blueworx_klaviyo_plan_failed( $subscription, $settings ) ) ) );
}

/**
 * Membership Cancelled, or just the status when that has already been sent.
 *
 * Setting a membership to cancel sends the event. When access then ends, only
 * the status changes, so one cancellation is one event. A cancellation that goes
 * straight to the end, with no notice period, sends it then.
 *
 * @param string $subscription_id Subscription ID.
 * @param array  $settings        Feature settings.
 * @return array|WP_Error
 */
function blueworx_klaviyo_plans_for_cancel( $subscription_id, $settings ) {
	$subscription = blueworx_klaviyo_surecart_record( 'subscription', $subscription_id );

	if ( is_wp_error( $subscription ) ) {
		return $subscription;
	}

	$state = blueworx_klaviyo_recall( $subscription_id );

	if ( ! empty( $state['cancel_sent'] ) ) {
		$ended = isset( $subscription['ended_at'] ) ? $subscription['ended_at'] : '';

		return array_values(
			array_filter(
				array(
					blueworx_klaviyo_plan_status(
						$subscription,
						$settings,
						$ended ? array( 'membership_end_date' => blueworx_klaviyo_iso_date( $ended ) ) : array()
					),
				)
			)
		);
	}

	return array_values( array_filter( array( blueworx_klaviyo_plan_cancelled( $subscription, $settings ) ) ) );
}

/**
 * Whatever a general update means: a new plan, a failed payment, a cancellation
 * undone, payment recovering, or usually nothing at all.
 *
 * SureCart says what changed in previous_attributes. What the connection last
 * saw fills any gap, so a plan change is still noticed if SureCart leaves it out.
 *
 * @param string $subscription_id Subscription ID.
 * @param array  $previous        What changed.
 * @param array  $settings        Feature settings.
 * @return array|WP_Error
 */
function blueworx_klaviyo_plans_for_update( $subscription_id, $previous, $settings ) {
	$subscription = blueworx_klaviyo_surecart_record( 'subscription', $subscription_id );

	if ( is_wp_error( $subscription ) ) {
		return $subscription;
	}

	$state     = blueworx_klaviyo_recall( $subscription_id );
	$price     = isset( $subscription['price'] ) && is_array( $subscription['price'] ) ? $subscription['price'] : array();
	$price_id  = isset( $price['id'] ) ? $price['id'] : '';
	$status    = isset( $subscription['status'] ) ? $subscription['status'] : '';
	$old_price = isset( $previous['price'] ) ? $previous['price'] : ( isset( $state['price'] ) ? $state['price'] : '' );
	$old_price = is_array( $old_price ) ? ( isset( $old_price['id'] ) ? $old_price['id'] : '' ) : (string) $old_price;
	$old_state = isset( $previous['status'] ) ? (string) $previous['status'] : ( isset( $state['status'] ) ? (string) $state['status'] : '' );
	$plans     = array();

	if ( '' !== $old_price && '' !== $price_id && $old_price !== $price_id ) {
		$old_record = blueworx_klaviyo_surecart_record( 'price', $old_price );

		if ( is_wp_error( $old_record ) ) {
			return $old_record;
		}

		$plans[] = blueworx_klaviyo_plan_changed( $subscription, $old_record, $settings );
	}

	if ( '' !== $old_state && $old_state !== $status ) {
		if ( 'past_due' === blueworx_klaviyo_membership_status( $status ) && 'past_due' !== blueworx_klaviyo_membership_status( $old_state ) ) {
			$plans[] = blueworx_klaviyo_plan_failed( $subscription, $settings );
		} elseif ( ! $plans ) {
			$plans[] = blueworx_klaviyo_plan_status( $subscription, $settings );
		}
	}

	$now = array(
		'price'  => $price_id,
		'status' => $status,
	);

	// A cancellation taken back before access ended: there is no end date any more,
	// and the next cancellation is a new one.
	if ( ! empty( $previous['cancel_at_period_end'] ) && empty( $subscription['cancel_at_period_end'] ) ) {
		$plans[]            = blueworx_klaviyo_plan_status( $subscription, $settings, array(), array( 'membership_end_date' ) );
		$now['cancel_sent'] = false;
	}

	$plans = array_values( array_filter( $plans ) );

	if ( ! $plans ) {
		blueworx_klaviyo_remember( $subscription_id, $now );
		return array();
	}

	// Remembered only once it has gone through, so a retry still sees the change.
	$last                   = count( $plans ) - 1;
	$plans[ $last ]['after'] = array(
		'subscription' => $subscription_id,
		'state'        => $now,
	);

	return $plans;
}
