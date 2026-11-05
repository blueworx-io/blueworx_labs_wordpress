<?php
/**
 * Klaviyo connection: settings, and talking to Klaviyo.
 *
 * The key lives either in wp-config.php or in an option that never autoloads,
 * and never reaches a page in full. Nothing in this file runs while a visitor is
 * waiting: only the queue calls it, in the background.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where Klaviyo's API lives.
 */
const BLUEWORX_KLAVIYO_API = 'https://a.klaviyo.com/api/';

/**
 * The version of Klaviyo's API this was written and checked against.
 *
 * A fixed date rather than "latest": Klaviyo keeps each dated version working
 * for two years, so a site keeps working without anyone changing anything.
 */
const BLUEWORX_KLAVIYO_REVISION = '2026-10-15';

/**
 * The boxes on the settings screen, other than the two secrets.
 *
 * @return array Default values keyed by setting name.
 */
function blueworx_klaviyo_setting_defaults() {
	return array(
		'list_info'           => '',
		'list_reservation'    => '',
		'list_members'        => '',
		'info_form_name'      => '',
		'consent_text'        => '',
		'reservation_form_id' => '',
		'founding_product'    => '',
		'founding_start_date' => '',
		'checkout_box'        => 'marketing_opt_in',
		'send_test_mode'      => '0',
	);
}

/**
 * Reads one setting.
 *
 * @param string $name Setting name.
 * @return string
 */
function blueworx_klaviyo_setting( $name ) {
	$defaults = blueworx_klaviyo_setting_defaults();

	return (string) get_option( 'blueworx_klaviyo_' . $name, isset( $defaults[ $name ] ) ? $defaults[ $name ] : '' );
}

/**
 * All the settings, in the shape the plan builders take.
 *
 * @return array
 */
function blueworx_klaviyo_settings() {
	$settings = array();

	foreach ( array_keys( blueworx_klaviyo_setting_defaults() ) as $name ) {
		$settings[ $name ] = blueworx_klaviyo_setting( $name );
	}

	return $settings;
}

/**
 * The private API key: wp-config.php wins over the settings screen.
 *
 * @return string
 */
function blueworx_klaviyo_key() {
	if ( defined( 'BLUEWORX_KLAVIYO_KEY' ) && BLUEWORX_KLAVIYO_KEY ) {
		return (string) BLUEWORX_KLAVIYO_KEY;
	}

	return (string) get_option( 'blueworx_klaviyo_key', '' );
}

/**
 * The SureCart webhook's signing secret: wp-config.php wins here too.
 *
 * @return string
 */
function blueworx_klaviyo_webhook_secret() {
	if ( defined( 'BLUEWORX_KLAVIYO_WEBHOOK_SECRET' ) && BLUEWORX_KLAVIYO_WEBHOOK_SECRET ) {
		return (string) BLUEWORX_KLAVIYO_WEBHOOK_SECRET;
	}

	return (string) get_option( 'blueworx_klaviyo_webhook_secret', '' );
}

/**
 * Makes one request to Klaviyo.
 *
 * @param string     $method GET or POST.
 * @param string     $path   Path after /api/, with any query string.
 * @param array|null $body   JSON body.
 * @return array {
 *     @type int    $status HTTP status, or 0 when it never got there.
 *     @type array  $body   Decoded response.
 *     @type string $error  Short description when it failed.
 * }
 */
function blueworx_klaviyo_request( $method, $path, $body = null ) {
	$args = array(
		'method'  => $method,
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Klaviyo-API-Key ' . blueworx_klaviyo_key(),
			'revision'      => BLUEWORX_KLAVIYO_REVISION,
			'Accept'        => 'application/vnd.api+json',
		),
	);

	if ( null !== $body ) {
		$args['headers']['Content-Type'] = 'application/vnd.api+json';
		$args['body']                    = wp_json_encode( $body );
	}

	$response = wp_remote_request( BLUEWORX_KLAVIYO_API . $path, $args );

	if ( is_wp_error( $response ) ) {
		return array(
			'status' => 0,
			'body'   => array(),
			'error'  => $response->get_error_message(),
		);
	}

	$status  = (int) wp_remote_retrieve_response_code( $response );
	$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	$decoded = is_array( $decoded ) ? $decoded : array();
	$error   = '';

	if ( $status < 200 || $status >= 300 ) {
		$detail = isset( $decoded['errors'][0]['detail'] ) ? (string) $decoded['errors'][0]['detail'] : '';
		$error  = 'Klaviyo said ' . $status . ( '' !== $detail ? ': ' . $detail : '' );
	}

	return array(
		'status' => $status,
		'body'   => $decoded,
		'error'  => $error,
	);
}

/**
 * Whether a failed request is worth trying again.
 *
 * Klaviyo being down or busy clears up on its own, and so does a key somebody
 * fixes. A request Klaviyo refuses as malformed fails the same way every time.
 *
 * @param int $status HTTP status, or 0 when it never got there.
 * @return bool
 */
function blueworx_klaviyo_is_retryable( $status ) {
	return 0 === $status || in_array( $status, array( 401, 403, 408, 409, 429 ), true ) || $status >= 500;
}

/**
 * Finds a profile by email.
 *
 * @param string $email Email address.
 * @return array The request result, with 'profile' added: its attributes, or empty.
 */
function blueworx_klaviyo_find_profile( $email ) {
	$filter = 'equals(email,"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $email ) . '")';
	$result = blueworx_klaviyo_request( 'GET', 'profiles?filter=' . rawurlencode( $filter ) . '&fields[profile]=email,first_name,last_name,properties' );

	$result['profile'] = isset( $result['body']['data'][0]['attributes'] ) ? (array) $result['body']['data'][0]['attributes'] : array();

	return $result;
}

/**
 * Carries out one plan: profile first, then the event, then the list.
 *
 * Safe to repeat from the top. The profile import is an update by email, an
 * event with a unique ID is recorded once, and subscribing someone who is
 * already subscribed changes nothing. That is what makes retrying simple.
 *
 * @param array $plan The plan.
 * @return array {
 *     @type bool   $ok    Whether it all went through.
 *     @type bool   $retry Whether trying again might help.
 *     @type string $error What went wrong.
 * }
 */
function blueworx_klaviyo_send_plan( $plan ) {
	$failed = static function ( $result ) {
		return array(
			'ok'    => false,
			'retry' => blueworx_klaviyo_is_retryable( $result['status'] ),
			'error' => $result['error'],
		);
	};

	$existing = array();

	// Only asked when the answer changes what gets written.
	if ( ! empty( $plan['fill'] ) || ! empty( $plan['guarded'] ) ) {
		$found = blueworx_klaviyo_find_profile( $plan['email'] );

		if ( '' !== $found['error'] ) {
			return $failed( $found );
		}

		$existing = $found['profile'];
	}

	$import = array(
		'data' => array(
			'type'       => 'profile',
			'attributes' => blueworx_klaviyo_profile_attributes( $plan, $existing ),
		),
	);

	if ( ! empty( $plan['unset'] ) ) {
		$import['data']['meta'] = array( 'patch_properties' => array( 'unset' => array_values( $plan['unset'] ) ) );
	}

	$result = blueworx_klaviyo_request( 'POST', 'profile-import', $import );

	if ( '' !== $result['error'] ) {
		return $failed( $result );
	}

	if ( ! empty( $plan['event'] ) ) {
		$result = blueworx_klaviyo_request( 'POST', 'events', blueworx_klaviyo_event_body( $plan ) );

		if ( '' !== $result['error'] ) {
			return $failed( $result );
		}
	}

	if ( ! empty( $plan['list'] ) ) {
		$result = blueworx_klaviyo_request( 'POST', 'profile-subscription-bulk-create-jobs', blueworx_klaviyo_subscribe_body( $plan['email'], $plan['list'] ) );

		if ( '' !== $result['error'] ) {
			return $failed( $result );
		}
	}

	return array(
		'ok'    => true,
		'retry' => false,
		'error' => '',
	);
}

/**
 * The request body for an event.
 *
 * Only the event's own 'value' becomes Klaviyo's revenue figure. Every other
 * amount travels as an ordinary property.
 *
 * @param array $plan The plan.
 * @return array
 */
function blueworx_klaviyo_event_body( $plan ) {
	$event = $plan['event'];

	$attributes = array(
		'properties' => (object) $event['properties'],
		'unique_id'  => $event['unique_id'],
		'metric'     => array(
			'data' => array(
				'type'       => 'metric',
				'attributes' => array( 'name' => $event['name'] ),
			),
		),
		'profile'    => array(
			'data' => array(
				'type'       => 'profile',
				'attributes' => array( 'email' => $plan['email'] ),
			),
		),
	);

	if ( isset( $event['value'] ) ) {
		$attributes['value']          = $event['value'];
		$attributes['value_currency'] = 'AUD';
	}

	return array(
		'data' => array(
			'type'       => 'event',
			'attributes' => $attributes,
		),
	);
}

/**
 * The request body for subscribing one email to one list.
 *
 * @param string $email Email address.
 * @param string $list  List ID.
 * @return array
 */
function blueworx_klaviyo_subscribe_body( $email, $list ) {
	return array(
		'data' => array(
			'type'          => 'profile-subscription-bulk-create-job',
			'attributes'    => array(
				'profiles'          => array(
					'data' => array(
						array(
							'type'       => 'profile',
							'attributes' => array(
								'email'         => $email,
								'subscriptions' => array(
									'email' => array(
										'marketing' => array( 'consent' => 'SUBSCRIBED' ),
									),
								),
							),
						),
					),
				),
				'historical_import' => false,
			),
			'relationships' => array(
				'list' => array(
					'data' => array(
						'type' => 'list',
						'id'   => $list,
					),
				),
			),
		),
	);
}

/**
 * Checks the key and the three lists, for the Test connection button.
 *
 * @return array {
 *     @type bool     $ok    Whether everything answered.
 *     @type string[] $lines What was found, one sentence each.
 * }
 */
function blueworx_klaviyo_test_connection() {
	if ( '' === blueworx_klaviyo_key() ) {
		return array(
			'ok'    => false,
			'lines' => array( __( 'There is no API key yet.', 'blueworx-labs-wordpress' ) ),
		);
	}

	$result = blueworx_klaviyo_request( 'GET', 'lists?fields[list]=name' );

	if ( '' !== $result['error'] ) {
		return array(
			'ok'    => false,
			'lines' => array( $result['error'] ),
		);
	}

	$ok    = true;
	$lines = array( __( 'The key works.', 'blueworx-labs-wordpress' ) );

	foreach ( blueworx_klaviyo_list_labels() as $setting => $label ) {
		$id = blueworx_klaviyo_setting( $setting );

		if ( '' === $id ) {
			$ok      = false;
			$lines[] = sprintf(
				/* translators: %s: list's label on the settings screen. */
				__( '%s: no list ID yet.', 'blueworx-labs-wordpress' ),
				$label
			);
			continue;
		}

		$list = blueworx_klaviyo_request( 'GET', 'lists/' . rawurlencode( $id ) . '?fields[list]=name' );

		if ( '' !== $list['error'] ) {
			$ok      = false;
			$lines[] = sprintf(
				/* translators: 1: list's label on the settings screen, 2: the list ID. */
				__( '%1$s: Klaviyo cannot find list %2$s.', 'blueworx-labs-wordpress' ),
				$label,
				$id
			);
			continue;
		}

		$lines[] = sprintf(
			/* translators: 1: list's label on the settings screen, 2: the list's name in Klaviyo. */
			__( '%1$s: found "%2$s".', 'blueworx-labs-wordpress' ),
			$label,
			isset( $list['body']['data']['attributes']['name'] ) ? $list['body']['data']['attributes']['name'] : $id
		);
	}

	return array(
		'ok'    => $ok,
		'lines' => $lines,
	);
}

/**
 * What each list is for, in the order the settings screen shows them.
 *
 * @return array Labels keyed by setting name.
 */
function blueworx_klaviyo_list_labels() {
	return array(
		'list_info'        => __( 'Stay up to date list', 'blueworx-labs-wordpress' ),
		'list_reservation' => __( 'Founding reservations list', 'blueworx-labs-wordpress' ),
		'list_members'     => __( 'Members list', 'blueworx-labs-wordpress' ),
	);
}
