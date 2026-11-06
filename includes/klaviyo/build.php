<?php
/**
 * Klaviyo connection: what gets sent, worked out from what happened.
 *
 * Everything in this file only turns one set of arrays into another, so the
 * rules the marketing team depends on can be tested without a site. A "plan" is
 * one person's worth of work for the queue:
 *
 *     email    Who it is about. Klaviyo matches on this, so a reserved member
 *              paying with the same email stays one profile.
 *     label    What the log calls it.
 *     fill     Name fields, written only when the profile has none. A form is
 *              anonymous, so it must not be able to rename somebody else.
 *     set      Profile properties written every time.
 *     guarded  Properties written only when none of the listed properties
 *              already has a value: array( prop => array( 'value', 'unless' ) ).
 *     unset    Properties to remove.
 *     event    The event, if there is one.
 *     list     The list to subscribe them to, if any.
 *     after    Memory to keep once this has gone through.
 *
 * Properties the marketing team manage (founding_member_number,
 * founding_member_type, within_150, and lead_status on anything but the first
 * form) never appear here at all, so nothing can overwrite them.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a SureCart date, which can be a timestamp or a string, into a timestamp.
 *
 * @param mixed $value Date.
 * @return int Timestamp, or 0 when there is none.
 */
function blueworx_klaviyo_timestamp( $value ) {
	if ( is_numeric( $value ) ) {
		return (int) $value;
	}

	return $value ? (int) strtotime( (string) $value ) : 0;
}

/**
 * Turns a SureCart date into the ISO date the spreadsheet asks for.
 *
 * @param mixed $value Date.
 * @return string YYYY-MM-DD in the site's timezone, or an empty string.
 */
function blueworx_klaviyo_iso_date( $value ) {
	$time = blueworx_klaviyo_timestamp( $value );

	return $time > 0 ? wp_date( 'Y-m-d', $time ) : '';
}

/**
 * Converts SureCart's smallest-unit amount into dollars.
 *
 * @param mixed $amount       Amount in cents.
 * @param bool  $zero_decimal Whether the currency has no cents.
 * @return float Amount.
 */
function blueworx_klaviyo_money( $amount, $zero_decimal = false ) {
	return $zero_decimal ? (float) $amount : round( (float) $amount / 100, 2 );
}

/**
 * The spreadsheet's word for a billing interval.
 *
 * @param string $interval SureCart's recurring interval.
 * @return string annual, monthly, or SureCart's own word for anything else.
 */
function blueworx_klaviyo_billing_interval( $interval ) {
	$words = array(
		'year'  => 'annual',
		'month' => 'monthly',
	);

	return isset( $words[ $interval ] ) ? $words[ $interval ] : (string) $interval;
}

/**
 * Whether a checkout box's answer means ticked.
 *
 * @param mixed $value Answer.
 * @return bool
 */
function blueworx_klaviyo_is_ticked( $value ) {
	if ( is_bool( $value ) ) {
		return $value;
	}

	$value = strtolower( trim( (string) $value ) );

	return '' !== $value && ! in_array( $value, array( '0', 'false', 'no', 'off' ), true );
}

/**
 * Removes properties with nothing in them, so an empty box never wipes a value.
 *
 * Only null and empty strings go. False and zero are real answers.
 *
 * @param array $properties Properties.
 * @return array
 */
function blueworx_klaviyo_clean( $properties ) {
	return array_filter(
		$properties,
		static function ( $value ) {
			return null !== $value && '' !== $value;
		}
	);
}

/**
 * Works out what to write to a profile, given what is already there.
 *
 * @param array $plan     The plan.
 * @param array $existing The profile's attributes as Klaviyo holds them, or empty.
 * @return array Attributes for the profile import.
 */
function blueworx_klaviyo_profile_attributes( $plan, $existing = array() ) {
	$held       = isset( $existing['properties'] ) && is_array( $existing['properties'] ) ? $existing['properties'] : array();
	$attributes = array( 'email' => $plan['email'] );

	foreach ( isset( $plan['fill'] ) ? $plan['fill'] : array() as $field => $value ) {
		if ( '' !== (string) $value && empty( $existing[ $field ] ) ) {
			$attributes[ $field ] = $value;
		}
	}

	$properties = isset( $plan['set'] ) ? $plan['set'] : array();

	foreach ( isset( $plan['guarded'] ) ? $plan['guarded'] : array() as $property => $rule ) {
		$blocked = false;

		foreach ( $rule['unless'] as $other ) {
			if ( isset( $held[ $other ] ) && '' !== $held[ $other ] ) {
				$blocked = true;
			}
		}

		if ( ! $blocked ) {
			$properties[ $property ] = $rule['value'];
		}
	}

	$properties = blueworx_klaviyo_clean( $properties );

	if ( $properties ) {
		$attributes['properties'] = $properties;
	}

	return $attributes;
}

/**
 * The "Stay up to date" form: spreadsheet tab 1, Form A.
 *
 * @param array $form     The answers: email, first_name, last_name, page and preferred_centre.
 * @param array $settings The Website Subscribers list and the consent wording: list and consent_text.
 * @return array The plan.
 */
function blueworx_klaviyo_plan_info_form( $form, $settings ) {
	return array(
		'label'   => 'Stay up to date form',
		'email'   => $form['email'],
		'fill'    => array(
			'first_name' => isset( $form['first_name'] ) ? $form['first_name'] : '',
			'last_name'  => isset( $form['last_name'] ) ? $form['last_name'] : '',
		),
		'set'     => array(
			'signup_source'          => 'website_info_form',
			'signup_page'            => isset( $form['page'] ) ? $form['page'] : '',
			'preferred_centre'       => isset( $form['preferred_centre'] ) ? $form['preferred_centre'] : '',
			// Klaviyo silently drops a property called consent_text, so this name is deliberate.
			'signup_consent_wording' => $settings['consent_text'],
			'marketing_consent'      => true,
		),
		'guarded' => array(
			// Never overwrite an existing true.
			'founding_member' => array(
				'value'  => false,
				'unless' => array( 'founding_member' ),
			),
			// Skip if the profile already has lead_status or founding_status.
			'lead_status'     => array(
				'value'  => 'subscriber',
				'unless' => array( 'lead_status', 'founding_status' ),
			),
		),
		'list'    => $settings['list'],
	);
}

/**
 * The founding reservation form: spreadsheet tab 1, Form B.
 *
 * Everyone who reserves goes on the reservations list. The optional box records
 * only whether they also want wider news, and leaving it unticked never takes
 * away a yes they gave earlier.
 *
 * @param array $form     The answers: email, first_name, last_name, date.
 * @param array $settings The settings used: list.
 * @return array The plan.
 */
function blueworx_klaviyo_plan_reservation_form( $form, $settings ) {
	$marketing = ! empty( $form['marketing'] );

	$set = array(
		'signup_source'             => 'founding_reservation_form',
		'founding_reservation_date' => $form['date'],
		'founding_member'           => true,
	);

	$guarded = array(
		// Never overwrite paid, nor anything else marketing has set by hand.
		'founding_status' => array(
			'value'  => 'reserved',
			'unless' => array( 'founding_status' ),
		),
	);

	if ( $marketing ) {
		$set['marketing_consent'] = true;
	} else {
		$guarded['marketing_consent'] = array(
			'value'  => false,
			'unless' => array( 'marketing_consent' ),
		);
	}

	return array(
		'label'   => 'Founding reservation form',
		'email'   => $form['email'],
		'fill'    => array(
			'first_name' => isset( $form['first_name'] ) ? $form['first_name'] : '',
			'last_name'  => isset( $form['last_name'] ) ? $form['last_name'] : '',
		),
		'set'     => $set,
		'guarded' => $guarded,
		'event'   => array(
			'name'       => 'Submitted Reservation',
			'unique_id'  => 'reservation-' . md5( strtolower( $form['email'] ) . '|' . $form['date'] ),
			'properties' => blueworx_klaviyo_clean(
				array(
					'email'                     => $form['email'],
					'first_name'                => isset( $form['first_name'] ) ? $form['first_name'] : '',
					'last_name'                 => isset( $form['last_name'] ) ? $form['last_name'] : '',
					'signup_source'             => 'founding_reservation_form',
					'founding_reservation_date' => $form['date'],
					'founding_member'           => true,
					'founding_status'           => 'reserved',
					'marketing_consent'         => $marketing,
				)
			),
		),
		'list'    => $settings['list'],
	);
}

/**
 * Whether a product is the founding one the settings name.
 *
 * Plan-agnostic on purpose: PADLX will sell other memberships through the same
 * events, and only this one product touches the founding properties.
 *
 * @param array  $product  SureCart product.
 * @param string $founding The founding product's slug or ID from the settings.
 * @return bool
 */
function blueworx_klaviyo_is_founding( $product, $founding ) {
	if ( '' === (string) $founding ) {
		return false;
	}

	return in_array( $founding, array( isset( $product['slug'] ) ? $product['slug'] : '', isset( $product['id'] ) ? $product['id'] : '' ), true );
}

/**
 * The details every membership event and profile update shares.
 *
 * @param array $subscription SureCart subscription, with its price, product,
 *                            customer and current period expanded.
 * @param array $settings     Feature settings.
 * @return array|null The details, or null when it cannot be matched to a person.
 */
function blueworx_klaviyo_membership_details( $subscription, $settings ) {
	$customer = isset( $subscription['customer'] ) && is_array( $subscription['customer'] ) ? $subscription['customer'] : array();
	$price    = isset( $subscription['price'] ) && is_array( $subscription['price'] ) ? $subscription['price'] : array();
	$product  = isset( $price['product'] ) && is_array( $price['product'] ) ? $price['product'] : array();

	if ( empty( $customer['email'] ) ) {
		return null;
	}

	$founding = blueworx_klaviyo_is_founding( $product, isset( $settings['founding_product'] ) ? $settings['founding_product'] : '' );
	$start    = $founding && ! empty( $settings['founding_start_date'] )
		? $settings['founding_start_date']
		: blueworx_klaviyo_iso_date( isset( $subscription['created_at'] ) ? $subscription['created_at'] : 0 );
	$ending   = ! empty( $subscription['cancel_at_period_end'] ) || ! empty( $subscription['ended_at'] );

	return array(
		'email'            => $customer['email'],
		'first_name'       => isset( $customer['first_name'] ) ? $customer['first_name'] : '',
		'last_name'        => isset( $customer['last_name'] ) ? $customer['last_name'] : '',
		'customer_id'      => isset( $customer['id'] ) ? $customer['id'] : '',
		'subscription_id'  => isset( $subscription['id'] ) ? $subscription['id'] : '',
		'price_id'         => isset( $price['id'] ) ? $price['id'] : '',
		'status'           => isset( $subscription['status'] ) ? $subscription['status'] : '',
		'founding'         => $founding,
		'plan_name'        => isset( $product['name'] ) ? $product['name'] : '',
		'billing_interval' => blueworx_klaviyo_billing_interval( isset( $price['recurring_interval'] ) ? $price['recurring_interval'] : '' ),
		'amount'           => blueworx_klaviyo_money( isset( $price['amount'] ) ? $price['amount'] : 0, ! empty( $price['is_zero_decimal'] ) ),
		'start_date'       => $start,
		'next_renewal'     => $ending ? '' : blueworx_klaviyo_iso_date( isset( $subscription['current_period_end_at'] ) ? $subscription['current_period_end_at'] : 0 ),
	);
}

/**
 * The five details the brief asks for on each event, where they apply.
 *
 * These sit beside the spreadsheet's own properties. "amount" is a plain
 * property, so Klaviyo never counts it as revenue.
 *
 * @param array $details Membership details.
 * @return array
 */
function blueworx_klaviyo_brief_details( $details ) {
	return blueworx_klaviyo_clean(
		array(
			'product_name'          => $details['plan_name'],
			'billing_interval'      => $details['billing_interval'],
			'amount'                => $details['amount'],
			'membership_start_date' => $details['start_date'],
			'next_renewal_date'     => $details['next_renewal'],
		)
	);
}

/**
 * The spreadsheet's word for a subscription's state.
 *
 * @param string $status SureCart's status.
 * @return string active, past_due or cancelled.
 */
function blueworx_klaviyo_membership_status( $status ) {
	if ( in_array( $status, array( 'past_due', 'unpaid' ), true ) ) {
		return 'past_due';
	}

	return in_array( $status, array( 'canceled', 'completed' ), true ) ? 'cancelled' : 'active';
}

/**
 * The profile properties shared by every membership update: spreadsheet tab 3.
 *
 * @param array $details Membership details.
 * @return array
 */
function blueworx_klaviyo_profile_base( $details ) {
	return array(
		'membership_status'    => blueworx_klaviyo_membership_status( $details['status'] ),
		'membership_plan'      => $details['plan_name'],
		'billing_interval'     => $details['billing_interval'],
		'surecart_customer_id' => $details['customer_id'],
	);
}

/**
 * Started Membership: first successful payment on a new membership.
 *
 * Sets founding_status to paid whether or not they reserved, as the spreadsheet
 * asks: someone who buys one of the open spots still counts, and marketing adds
 * their number.
 *
 * @param array $subscription SureCart subscription, expanded.
 * @param array $order        The first order, with its checkout expanded.
 * @param array $settings     Feature settings.
 * @return array|null The plan.
 */
function blueworx_klaviyo_plan_started( $subscription, $order, $settings ) {
	$details = blueworx_klaviyo_membership_details( $subscription, $settings );

	if ( ! $details ) {
		return null;
	}

	$checkout = isset( $order['checkout'] ) && is_array( $order['checkout'] ) ? $order['checkout'] : array();
	$discount = isset( $checkout['discount'] ) && is_array( $checkout['discount'] ) ? $checkout['discount'] : array();
	$coupon   = isset( $discount['promotion']['code'] ) ? $discount['promotion']['code'] : '';
	$paid     = isset( $checkout['paid_amount'] ) ? $checkout['paid_amount'] : ( isset( $checkout['amount_due'] ) ? $checkout['amount_due'] : 0 );
	$value    = blueworx_klaviyo_money( $paid, ! empty( $subscription['price']['is_zero_decimal'] ) );
	$box      = isset( $settings['checkout_box'] ) ? $settings['checkout_box'] : '';
	$metadata = isset( $checkout['metadata'] ) && is_array( $checkout['metadata'] ) ? $checkout['metadata'] : array();
	$ticked   = '' !== $box && isset( $metadata[ $box ] ) && blueworx_klaviyo_is_ticked( $metadata[ $box ] );

	$set = blueworx_klaviyo_profile_base( $details );

	$set['membership_purchase_date'] = blueworx_klaviyo_iso_date( isset( $checkout['paid_at'] ) && $checkout['paid_at'] ? $checkout['paid_at'] : time() );

	if ( $details['founding'] ) {
		$set['founding_status'] = 'paid';
		$set['founding_member'] = true;
	}

	if ( $ticked ) {
		$set['marketing_consent'] = true;
	}

	return array(
		'label' => 'Started Membership',
		'email' => $details['email'],
		'fill'  => array(
			'first_name' => $details['first_name'],
			'last_name'  => $details['last_name'],
		),
		'set'   => $set,
		'event' => array(
			'name'       => 'Started Membership',
			'unique_id'  => 'started-' . $details['subscription_id'],
			'value'      => $value,
			'properties' => blueworx_klaviyo_clean(
				array_merge(
					blueworx_klaviyo_brief_details( $details ),
					array(
						'value'           => $value,
						'plan_name'       => $details['plan_name'],
						'is_founding'     => $details['founding'],
						'subscription_id' => $details['subscription_id'],
						'order_id'        => isset( $order['id'] ) ? $order['id'] : '',
						'coupon_code'     => $coupon,
					)
				)
			),
		),
		'list'  => $ticked && ! empty( $settings['list_members'] ) ? $settings['list_members'] : '',
		'after' => array(
			'subscription' => $details['subscription_id'],
			'state'        => array(
				'price'  => $details['price_id'],
				'status' => $details['status'],
			),
		),
	);
}

/**
 * Membership Payment Succeeded: each later renewal, never the first payment.
 *
 * @param array    $subscription   SureCart subscription, expanded, with its
 *                                 current period's checkout.
 * @param int|null $payment_number Which payment this was, when it can be counted.
 * @param array    $settings       Feature settings.
 * @return array|null The plan.
 */
function blueworx_klaviyo_plan_renewed( $subscription, $payment_number, $settings ) {
	$details = blueworx_klaviyo_membership_details( $subscription, $settings );

	if ( ! $details ) {
		return null;
	}

	$period   = isset( $subscription['current_period'] ) && is_array( $subscription['current_period'] ) ? $subscription['current_period'] : array();
	$checkout = isset( $period['checkout'] ) && is_array( $period['checkout'] ) ? $period['checkout'] : array();
	$paid     = isset( $checkout['amount_due'] ) ? $checkout['amount_due'] : ( isset( $subscription['price']['amount'] ) ? $subscription['price']['amount'] : 0 );
	$value    = blueworx_klaviyo_money( $paid, ! empty( $subscription['price']['is_zero_decimal'] ) );

	return array(
		'label' => 'Membership Payment Succeeded',
		'email' => $details['email'],
		'set'   => blueworx_klaviyo_profile_base( $details ),
		'event' => array(
			'name'       => 'Membership Payment Succeeded',
			'unique_id'  => 'renewed-' . ( isset( $period['id'] ) ? $period['id'] : $details['subscription_id'] ),
			'value'      => $value,
			'properties' => blueworx_klaviyo_clean(
				array_merge(
					blueworx_klaviyo_brief_details( $details ),
					array(
						'value'           => $value,
						'plan_name'       => $details['plan_name'],
						'subscription_id' => $details['subscription_id'],
						'payment_number'  => $payment_number,
					)
				)
			),
		),
	);
}

/**
 * Membership Payment Failed.
 *
 * Reached from two SureCart events for one failure, so the attempt goes in the
 * unique ID and Klaviyo records it once however many of them arrive.
 *
 * @param array $subscription SureCart subscription, expanded.
 * @param array $settings     Feature settings.
 * @return array|null The plan.
 */
function blueworx_klaviyo_plan_failed( $subscription, $settings ) {
	$details = blueworx_klaviyo_membership_details( $subscription, $settings );

	if ( ! $details ) {
		return null;
	}

	$period  = isset( $subscription['current_period'] ) && is_array( $subscription['current_period'] ) ? $subscription['current_period'] : array();
	$attempt = isset( $period['payment_retry_count'] ) ? (int) $period['payment_retry_count'] : 0;
	$set     = blueworx_klaviyo_profile_base( $details );

	$set['membership_status'] = 'past_due';

	return array(
		'label' => 'Membership Payment Failed',
		'email' => $details['email'],
		'set'   => $set,
		'event' => array(
			'name'       => 'Membership Payment Failed',
			'unique_id'  => 'failed-' . ( isset( $period['id'] ) ? $period['id'] : $details['subscription_id'] ) . '-' . $attempt,
			'properties' => blueworx_klaviyo_clean(
				array_merge(
					blueworx_klaviyo_brief_details( $details ),
					array(
						'plan_name'       => $details['plan_name'],
						'subscription_id' => $details['subscription_id'],
						'attempt_count'   => $attempt,
						'next_retry_date' => blueworx_klaviyo_iso_date( isset( $period['next_payment_retry_at'] ) ? $period['next_payment_retry_at'] : 0 ),
					)
				)
			),
		),
	);
}

/**
 * Membership Cancelled, when a member cancels or PADLX cancels for them.
 *
 * Cancelling normally leaves access running to the end of the paid period, so
 * they stay active and only membership_status changes when access ends.
 *
 * @param array $subscription SureCart subscription, expanded.
 * @param array $settings     Feature settings.
 * @return array|null The plan.
 */
function blueworx_klaviyo_plan_cancelled( $subscription, $settings ) {
	$details = blueworx_klaviyo_membership_details( $subscription, $settings );

	if ( ! $details ) {
		return null;
	}

	$act     = isset( $subscription['current_cancellation_act'] ) && is_array( $subscription['current_cancellation_act'] ) ? $subscription['current_cancellation_act'] : array();
	$reason  = isset( $act['cancellation_reason']['label'] ) ? $act['cancellation_reason']['label'] : '';
	$comment = isset( $act['comment'] ) ? $act['comment'] : '';
	$ended   = isset( $subscription['ended_at'] ) ? $subscription['ended_at'] : '';
	$ends    = blueworx_klaviyo_iso_date( $ended ? $ended : ( isset( $subscription['current_period_end_at'] ) ? $subscription['current_period_end_at'] : 0 ) );
	$when    = isset( $act['created_at'] ) ? $act['created_at'] : time();
	$set     = blueworx_klaviyo_profile_base( $details );

	$set['membership_end_date'] = $ends;

	return array(
		'label' => 'Membership Cancelled',
		'email' => $details['email'],
		'set'   => $set,
		'event' => array(
			'name'       => 'Membership Cancelled',
			// Keyed on the cancellation itself, so someone who cancels, changes their
			// mind and cancels again still gets a second event.
			'unique_id'  => 'cancelled-' . $details['subscription_id'] . '-' . ( isset( $act['id'] ) ? $act['id'] : $ends ),
			'properties' => blueworx_klaviyo_clean(
				array_merge(
					blueworx_klaviyo_brief_details( $details ),
					array(
						'plan_name'       => $details['plan_name'],
						'subscription_id' => $details['subscription_id'],
						'cancelled_at'    => blueworx_klaviyo_iso_date( $when ),
						'access_ends_at'  => $ends,
						'cancel_reason'   => trim( $reason . ( '' !== $reason && '' !== $comment ? ': ' : '' ) . $comment ),
					)
				)
			),
		),
		'after' => array(
			'subscription' => $details['subscription_id'],
			'state'        => array( 'cancel_sent' => true ),
		),
	);
}

/**
 * Membership Plan Changed: monthly to annual, or one plan to another.
 *
 * Moving between monthly and annual keeps the same product, so the interval has
 * to be in the name or from_plan and to_plan would read the same.
 *
 * @param array $subscription SureCart subscription, expanded.
 * @param array $old_price    The price they moved from, with its product.
 * @param array $settings     Feature settings.
 * @return array|null The plan.
 */
function blueworx_klaviyo_plan_changed( $subscription, $old_price, $settings ) {
	$details = blueworx_klaviyo_membership_details( $subscription, $settings );

	if ( ! $details ) {
		return null;
	}

	$old_name = isset( $old_price['product']['name'] ) ? $old_price['product']['name'] : $details['plan_name'];
	$old_word = blueworx_klaviyo_billing_interval( isset( $old_price['recurring_interval'] ) ? $old_price['recurring_interval'] : '' );
	$from     = trim( $old_name . ( '' !== $old_word ? ' (' . $old_word . ')' : '' ) );
	$to       = $details['plan_name'] . ' (' . $details['billing_interval'] . ')';

	return array(
		'label' => 'Membership Plan Changed',
		'email' => $details['email'],
		'set'   => blueworx_klaviyo_profile_base( $details ),
		'event' => array(
			'name'       => 'Membership Plan Changed',
			'unique_id'  => 'changed-' . $details['subscription_id'] . '-' . ( isset( $old_price['id'] ) ? $old_price['id'] : '' ) . '-' . $details['price_id'],
			'properties' => blueworx_klaviyo_clean(
				array_merge(
					blueworx_klaviyo_brief_details( $details ),
					array(
						'plan_name'       => $details['plan_name'],
						'from_plan'       => $from,
						'to_plan'         => $to,
						'subscription_id' => $details['subscription_id'],
					)
				)
			),
		),
		'after' => array(
			'subscription' => $details['subscription_id'],
			'state'        => array( 'price' => $details['price_id'] ),
		),
	);
}

/**
 * Membership Refunded. Sends the amount as a plain property, not as revenue.
 *
 * @param array $refund SureCart refund, with its customer and charge expanded.
 * @return array|null The plan, or null for anything that is not a membership.
 */
function blueworx_klaviyo_plan_refunded( $refund ) {
	$customer = isset( $refund['customer'] ) && is_array( $refund['customer'] ) ? $refund['customer'] : array();
	$checkout = isset( $refund['charge']['checkout'] ) && is_array( $refund['charge']['checkout'] ) ? $refund['charge']['checkout'] : array();
	$items    = isset( $checkout['line_items']['data'] ) && is_array( $checkout['line_items']['data'] ) ? $checkout['line_items']['data'] : array();
	$price    = array();

	foreach ( $items as $item ) {
		if ( ! empty( $item['price']['recurring_interval'] ) ) {
			$price = $item['price'];
			break;
		}
	}

	if ( empty( $customer['email'] ) || ! $price ) {
		return null;
	}

	$order = isset( $checkout['order'] ) ? $checkout['order'] : '';
	$name  = isset( $price['product']['name'] ) ? $price['product']['name'] : '';

	return array(
		'label' => 'Membership Refunded',
		'email' => $customer['email'],
		'set'   => array(
			'surecart_customer_id' => isset( $customer['id'] ) ? $customer['id'] : '',
		),
		'event' => array(
			'name'       => 'Membership Refunded',
			'unique_id'  => 'refunded-' . ( isset( $refund['id'] ) ? $refund['id'] : '' ),
			'properties' => blueworx_klaviyo_clean(
				array(
					'amount'           => blueworx_klaviyo_money( isset( $refund['amount'] ) ? $refund['amount'] : 0, ! empty( $price['is_zero_decimal'] ) ),
					'order_id'         => is_array( $order ) ? ( isset( $order['id'] ) ? $order['id'] : '' ) : $order,
					'reason'           => isset( $refund['reason'] ) ? $refund['reason'] : '',
					'plan_name'        => $name,
					'product_name'     => $name,
					'billing_interval' => blueworx_klaviyo_billing_interval( $price['recurring_interval'] ),
				)
			),
		),
	);
}

/**
 * A profile update with no event, for changes the spreadsheet gives no event to:
 * access ending after a cancellation, payment recovering, a cancellation undone.
 *
 * @param array $subscription SureCart subscription, expanded.
 * @param array $settings     Feature settings.
 * @param array $extra        Extra properties to set.
 * @param array $remove       Properties to remove.
 * @return array|null The plan.
 */
function blueworx_klaviyo_plan_status( $subscription, $settings, $extra = array(), $remove = array() ) {
	$details = blueworx_klaviyo_membership_details( $subscription, $settings );

	if ( ! $details ) {
		return null;
	}

	return array(
		'label' => 'Membership status update',
		'email' => $details['email'],
		'set'   => array_merge( blueworx_klaviyo_profile_base( $details ), $extra ),
		'unset' => $remove,
	);
}
