<?php
/**
 * Plugin Name: BlueWorx Klaviyo test double
 * Description: Answers for Klaviyo and SureCart on a test site, so no test can ever reach the real services. Test fixture only.
 *
 * Copied into mu-plugins by tests/fixtures/klaviyo.php for the length of the spec.
 *
 * @package BlueWorxLabsTests
 */

// A test double WordPress loads only on a test site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.DB.DirectDatabaseQuery

/**
 * Answers every request bound for Klaviyo, and writes down what was asked.
 *
 * One row per call rather than one growing list: the queue can be running in a
 * background request at the same time as the fixture.
 */
add_filter(
	'pre_http_request',
	static function ( $short, $args, $url ) {
		if ( false === strpos( $url, 'a.klaviyo.com' ) ) {
			return $short;
		}

		add_option(
			'blueworx_klaviyo_test_call_' . str_pad( (string) hrtime( true ), 24, '0', STR_PAD_LEFT ),
			wp_json_encode(
				array(
					'method'   => $args['method'],
					'url'      => $url,
					'revision' => isset( $args['headers']['revision'] ) ? $args['headers']['revision'] : '',
					'auth'     => isset( $args['headers']['Authorization'] ) ? $args['headers']['Authorization'] : '',
					'body'     => isset( $args['body'] ) ? json_decode( $args['body'], true ) : null,
				)
			),
			'',
			'no'
		);

		$answer = static function ( $code, $body ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( $body ),
				'response' => array(
					'code'    => $code,
					'message' => '',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		if ( 'down' === get_option( 'blueworx_klaviyo_test_mode', 'ok' ) ) {
			return $answer( 503, array( 'errors' => array( array( 'detail' => 'Service unavailable' ) ) ) );
		}

		if ( 'rejects' === get_option( 'blueworx_klaviyo_test_mode', 'ok' ) ) {
			return $answer( 400, array( 'errors' => array( array( 'detail' => 'Bad request' ) ) ) );
		}

		if ( false !== strpos( $url, '/api/profiles' ) ) {
			$profile = get_option( 'blueworx_klaviyo_test_profile', null );

			return $answer( 200, array( 'data' => $profile ? array( array( 'attributes' => $profile ) ) : array() ) );
		}

		if ( preg_match( '#/api/lists/([^?]+)#', $url, $matches ) ) {
			return $answer( 200, array( 'data' => array( 'attributes' => array( 'name' => 'List ' . $matches[1] ) ) ) );
		}

		return $answer( 202, array( 'data' => array() ) );
	},
	10,
	3
);

/**
 * Stands in for SureCart's records, from whatever the fixture has seeded.
 */
add_filter(
	'blueworx_klaviyo_surecart_record',
	static function ( $short, $type, $id ) {
		$records = get_option( 'blueworx_klaviyo_test_records', array() );

		return isset( $records[ $type ][ $id ] ) ? $records[ $type ][ $id ] : new WP_Error( 'not_found', 'No such record.' );
	},
	10,
	3
);

add_filter(
	'blueworx_klaviyo_payment_number',
	static function () {
		return 2;
	}
);

add_filter(
	'blueworx_klaviyo_subscription_for_order',
	static function ( $short, $order ) {
		return isset( $order['subscription_for_test'] ) ? $order['subscription_for_test'] : '';
	},
	10,
	2
);
