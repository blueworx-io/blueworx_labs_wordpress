<?php
/**
 * Klaviyo connection: what gets sent, and what never gets overwritten.
 *
 * The marketing team's segments depend on the exact names and the protective
 * rules in their spreadsheet, so those are what this checks. The queue, the
 * webhook address and the screens need a real site and are in the Playwright
 * spec.
 *
 * Run with: php tests/php/klaviyo-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

require __DIR__ . '/stubs.php';

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function wp_date( $format, $time = null ) {
	return gmdate( $format, null === $time ? time() : $time );
}

require dirname( __DIR__, 2 ) . '/includes/klaviyo/build.php';

function subscription_fixture( $overrides = array() ) {
	return array_merge(
		array(
			'id'                    => 'sub1',
			'status'                => 'active',
			'created_at'            => '2026-11-04T19:00:42+00:00',
			'current_period_end_at' => '2027-11-04T19:00:42+00:00',
			'cancel_at_period_end'  => false,
			'ended_at'              => null,
			'customer'              => array(
				'id'         => 'cus1',
				'email'      => 'jane@example.com',
				'first_name' => 'Jane',
				'last_name'  => 'Smith',
			),
			'price'                 => array(
				'id'                 => 'price1',
				'amount'             => 251900,
				'recurring_interval' => 'year',
				'product'            => array(
					'id'   => 'prod1',
					'slug' => 'founding-membership',
					'name' => 'Founding Membership',
				),
			),
			'current_period'        => array(
				'id'                    => 'per1',
				'status'                => 'paid',
				'payment_retry_count'   => 2,
				'next_payment_retry_at' => '2026-12-10T00:00:00+00:00',
				'checkout'              => array( 'amount_due' => 22900 ),
			),
		),
		$overrides
	);
}

$settings = array(
	'founding_product'    => 'founding-membership',
	'founding_start_date' => '2027-01-15',
	'checkout_box'        => 'marketing_opt_in',
	'list_members'        => 'XRYaZh',
);

$order_fixture = array(
	'id'       => 'ord1',
	'checkout' => array(
		'paid_amount' => 251900,
		'paid_at'     => '2026-11-04T19:00:42+00:00',
		'metadata'    => array( 'marketing_opt_in' => 'on' ),
		'discount'    => array( 'promotion' => array( 'code' => 'FOUNDER' ) ),
	),
);

echo "Started Membership\n";
$plan = blueworx_klaviyo_plan_started( subscription_fixture(), $order_fixture, $settings );
check( 'event name is the spreadsheet\'s', $plan['event']['name'], 'Started Membership' );
check( 'revenue value in dollars', $plan['event']['value'], 2519.0 );
check( 'value property', $plan['event']['properties']['value'], 2519.0 );
check( 'plan_name means the membership', $plan['event']['properties']['plan_name'], 'Founding Membership' );
check( 'annual or monthly is its own field', $plan['event']['properties']['billing_interval'], 'annual' );
check( 'is_founding', $plan['event']['properties']['is_founding'], true );
check( 'subscription_id', $plan['event']['properties']['subscription_id'], 'sub1' );
check( 'order_id', $plan['event']['properties']['order_id'], 'ord1' );
check( 'coupon_code', $plan['event']['properties']['coupon_code'], 'FOUNDER' );
check( 'start date is the fixed founding date', $plan['event']['properties']['membership_start_date'], '2027-01-15' );
check( 'next renewal', $plan['event']['properties']['next_renewal_date'], '2027-11-04' );
check( 'reserved becomes paid', $plan['set']['founding_status'], 'paid' );
check( 'founding_member true', $plan['set']['founding_member'], true );
check( 'membership_status', $plan['set']['membership_status'], 'active' );
check( 'membership_plan', $plan['set']['membership_plan'], 'Founding Membership' );
check( 'membership_purchase_date', $plan['set']['membership_purchase_date'], '2026-11-04' );
check( 'surecart_customer_id', $plan['set']['surecart_customer_id'], 'cus1' );
check( 'box ticked records consent', $plan['set']['marketing_consent'], true );
check( 'box ticked goes on Members', $plan['list'], 'XRYaZh' );
check( 'one event per membership', $plan['event']['unique_id'], 'started-sub1' );

echo "\nThe checkout box unticked\n";
$unticked                         = $order_fixture;
$unticked['checkout']['metadata'] = array();
$plan                             = blueworx_klaviyo_plan_started( subscription_fixture(), $unticked, $settings );
check( 'no consent written', isset( $plan['set']['marketing_consent'] ), false );
check( 'not on any list', $plan['list'], '' );
check( 'service event still sent', $plan['event']['name'], 'Started Membership' );

echo "\nA membership that is not founding\n";
$other                     = subscription_fixture();
$other['price']['product'] = array(
	'id'   => 'prod2',
	'slug' => 'social-membership-arundel',
	'name' => 'Social Membership - Arundel',
);
$plan                      = blueworx_klaviyo_plan_started( $other, $order_fixture, $settings );
check( 'is_founding false', $plan['event']['properties']['is_founding'], false );
check( 'founding_status untouched', isset( $plan['set']['founding_status'] ), false );
check( 'founding_member untouched', isset( $plan['set']['founding_member'] ), false );
check( 'start date is the day they joined', $plan['event']['properties']['membership_start_date'], '2026-11-04' );

echo "\nRenewal\n";
$plan = blueworx_klaviyo_plan_renewed( subscription_fixture(), 3, $settings );
check( 'event name', $plan['event']['name'], 'Membership Payment Succeeded' );
check( 'revenue from what the period charged', $plan['event']['value'], 229.0 );
check( 'payment_number', $plan['event']['properties']['payment_number'], 3 );
check( 'one event per period', $plan['event']['unique_id'], 'renewed-per1' );

echo "\nPayment failed\n";
$plan = blueworx_klaviyo_plan_failed( subscription_fixture( array( 'status' => 'past_due' ) ), $settings );
check( 'event name', $plan['event']['name'], 'Membership Payment Failed' );
check( 'no revenue', isset( $plan['event']['value'] ), false );
check( 'amount is a plain property', $plan['event']['properties']['amount'], 2519.0 );
check( 'attempt_count', $plan['event']['properties']['attempt_count'], 2 );
check( 'next_retry_date', $plan['event']['properties']['next_retry_date'], '2026-12-10' );
check( 'status past_due', $plan['set']['membership_status'], 'past_due' );
check( 'one event per attempt', $plan['event']['unique_id'], 'failed-per1-2' );

echo "\nCancelled, with access still running\n";
$cancelling = subscription_fixture(
	array(
		'cancel_at_period_end'     => true,
		'current_cancellation_act' => array(
			'id'                  => 'act1',
			'created_at'          => '2026-12-01T00:00:00+00:00',
			'comment'             => 'Moving away',
			'cancellation_reason' => array( 'label' => 'Other' ),
		),
	)
);
$plan       = blueworx_klaviyo_plan_cancelled( $cancelling, $settings );
check( 'event name', $plan['event']['name'], 'Membership Cancelled' );
check( 'no revenue', isset( $plan['event']['value'] ), false );
check( 'cancelled_at', $plan['event']['properties']['cancelled_at'], '2026-12-01' );
check( 'access_ends_at', $plan['event']['properties']['access_ends_at'], '2027-11-04' );
check( 'cancel_reason', $plan['event']['properties']['cancel_reason'], 'Other: Moving away' );
check( 'stays active until access ends', $plan['set']['membership_status'], 'active' );
check( 'end date set', $plan['set']['membership_end_date'], '2027-11-04' );
check( 'no renewal date on a cancelled membership', isset( $plan['event']['properties']['next_renewal_date'] ), false );
check( 'remembers it was sent', $plan['after']['state']['cancel_sent'], true );

echo "\nAccess ended\n";
$ended = subscription_fixture(
	array(
		'status'   => 'canceled',
		'ended_at' => '2027-11-04T19:00:42+00:00',
	)
);
$plan  = blueworx_klaviyo_plan_status( $ended, $settings, array( 'membership_end_date' => '2027-11-04' ) );
check( 'status cancelled', $plan['set']['membership_status'], 'cancelled' );
check( 'no second event', isset( $plan['event'] ), false );

echo "\nPlan change\n";
$old  = array(
	'id'                 => 'price0',
	'recurring_interval' => 'month',
	'product'            => array( 'name' => 'Founding Membership' ),
);
$plan = blueworx_klaviyo_plan_changed( subscription_fixture(), $old, $settings );
check( 'event name', $plan['event']['name'], 'Membership Plan Changed' );
check( 'from_plan tells them apart', $plan['event']['properties']['from_plan'], 'Founding Membership (monthly)' );
check( 'to_plan tells them apart', $plan['event']['properties']['to_plan'], 'Founding Membership (annual)' );
check( 'no revenue', isset( $plan['event']['value'] ), false );

echo "\nRefund\n";
$refund = array(
	'id'       => 'ref1',
	'amount'   => 22900,
	'reason'   => 'requested_by_customer',
	'customer' => array(
		'id'    => 'cus1',
		'email' => 'jane@example.com',
	),
	'charge'   => array(
		'checkout' => array(
			'order'      => 'ord1',
			'line_items' => array(
				'data' => array(
					array(
						'price' => array(
							'recurring_interval' => 'month',
							'product'            => array( 'name' => 'Founding Membership' ),
						),
					),
				),
			),
		),
	),
);
$plan   = blueworx_klaviyo_plan_refunded( $refund );
check( 'event name', $plan['event']['name'], 'Membership Refunded' );
check( 'not counted as revenue', isset( $plan['event']['value'] ), false );
check( 'no value property either', isset( $plan['event']['properties']['value'] ), false );
check( 'amount sent', $plan['event']['properties']['amount'], 229.0 );
check( 'order_id', $plan['event']['properties']['order_id'], 'ord1' );
check( 'reason', $plan['event']['properties']['reason'], 'requested_by_customer' );

$refund['charge']['checkout']['line_items']['data'][0]['price']['recurring_interval'] = null;
check( 'a one-off refund is not a membership', blueworx_klaviyo_plan_refunded( $refund ), null );

echo "\nStay up to date form\n";
$form = array(
	'email'      => 'jane@example.com',
	'first_name' => 'Jane',
	'page'       => 'https://padlx.com.au/',
);
$plan = blueworx_klaviyo_plan_info_form(
	$form,
	array(
		'list'         => 'Y5fFQA',
		'consent_text' => 'Yes please',
	)
);
check( 'goes on the list', $plan['list'], 'Y5fFQA' );

$attributes = blueworx_klaviyo_profile_attributes( $plan, array() );
check( 'new person: name written', $attributes['first_name'], 'Jane' );
check( 'signup_source', $attributes['properties']['signup_source'], 'website_info_form' );
check( 'signup_page', $attributes['properties']['signup_page'], 'https://padlx.com.au/' );
check( 'consent_text', $attributes['properties']['consent_text'], 'Yes please' );
check( 'marketing_consent', $attributes['properties']['marketing_consent'], true );
check( 'founding_member false is kept, not dropped', $attributes['properties']['founding_member'], false );
check( 'lead_status', $attributes['properties']['lead_status'], 'subscriber' );
check( 'an empty centre is left out', isset( $attributes['properties']['preferred_centre'] ), false );

$attributes = blueworx_klaviyo_profile_attributes(
	$plan,
	array(
		'first_name' => 'Janet',
		'properties' => array(
			'founding_member' => true,
			'founding_status' => 'reserved',
		),
	)
);
check( 'never overwrites a true founding_member', isset( $attributes['properties']['founding_member'] ), false );
check( 'founding_status blocks lead_status', isset( $attributes['properties']['lead_status'] ), false );
check( 'a form cannot rename someone', isset( $attributes['first_name'] ), false );

$attributes = blueworx_klaviyo_profile_attributes( $plan, array( 'properties' => array( 'lead_status' => 'hot' ) ) );
check( 'lead_status is never overwritten', isset( $attributes['properties']['lead_status'] ), false );

echo "\nFounding reservation form\n";
$form = array(
	'email'      => 'jane@example.com',
	'first_name' => 'Jane',
	'last_name'  => 'Smith',
	'marketing'  => false,
	'date'       => '2026-11-03',
);
$plan = blueworx_klaviyo_plan_reservation_form( $form, array( 'list' => 'SFZx43' ) );
check( 'on the list either way', $plan['list'], 'SFZx43' );
check( 'event name', $plan['event']['name'], 'Submitted Reservation' );
check( 'event says the box was unticked', $plan['event']['properties']['marketing_consent'], false );

$attributes = blueworx_klaviyo_profile_attributes( $plan, array() );
check( 'signup_source', $attributes['properties']['signup_source'], 'founding_reservation_form' );
check( 'reservation date', $attributes['properties']['founding_reservation_date'], '2026-11-03' );
check( 'founding_member true', $attributes['properties']['founding_member'], true );
check( 'status reserved', $attributes['properties']['founding_status'], 'reserved' );
check( 'unticked records false', $attributes['properties']['marketing_consent'], false );

$attributes = blueworx_klaviyo_profile_attributes(
	$plan,
	array(
		'properties' => array(
			'founding_status'   => 'paid',
			'marketing_consent' => true,
		),
	)
);
check( 'never overwrites paid', isset( $attributes['properties']['founding_status'] ), false );
check( 'unticked never takes back a yes', isset( $attributes['properties']['marketing_consent'] ), false );

$attributes = blueworx_klaviyo_profile_attributes( $plan, array( 'properties' => array( 'founding_status' => 'complimentary' ) ) );
check( 'leaves complimentary alone too', isset( $attributes['properties']['founding_status'] ), false );

$form['marketing'] = true;
$attributes        = blueworx_klaviyo_profile_attributes( blueworx_klaviyo_plan_reservation_form( $form, array( 'list' => 'SFZx43' ) ), array() );
check( 'ticked records true', $attributes['properties']['marketing_consent'], true );

echo "\nProperties marketing manage never appear\n";
$every = array(
	blueworx_klaviyo_plan_started( subscription_fixture(), $order_fixture, $settings ),
	blueworx_klaviyo_plan_renewed( subscription_fixture(), 2, $settings ),
	blueworx_klaviyo_plan_failed( subscription_fixture(), $settings ),
	blueworx_klaviyo_plan_cancelled( subscription_fixture(), $settings ),
	blueworx_klaviyo_plan_changed( subscription_fixture(), $old, $settings ),
);
$found = array();
foreach ( $every as $plan ) {
	foreach ( array( 'founding_member_number', 'founding_member_type', 'within_150', 'lead_status' ) as $managed ) {
		if ( isset( $plan['set'][ $managed ] ) || isset( $plan['guarded'][ $managed ] ) ) {
			$found[] = $managed;
		}
	}
}
check( 'none on any membership event', $found, array() );

echo "\nSmall rules\n";
check( 'monthly', blueworx_klaviyo_billing_interval( 'month' ), 'monthly' );
check( 'a ticked box posts on', blueworx_klaviyo_is_ticked( 'on' ), true );
check( 'an empty answer is not ticked', blueworx_klaviyo_is_ticked( '' ), false );
check( 'off is not ticked', blueworx_klaviyo_is_ticked( 'off' ), false );
check( 'no email, no plan', blueworx_klaviyo_plan_renewed( subscription_fixture( array( 'customer' => array() ) ), 2, $settings ), null );

finish();
