<?php
/**
 * Minimal WordPress stand-ins, so the SSO logic can be exercised from the CLI.
 *
 * These scripts test the parts that must be right before anything else matters —
 * token verification and user resolution — and those parts are pure enough to run
 * without a WordPress install. Anything needing a real database is covered by the
 * Playwright specs instead.
 *
 * @package BlueWorxLabs
 */

define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['options']    = array();
$GLOBALS['transients'] = array();
$GLOBALS['http']       = array();
$GLOBALS['calls']      = array();
$GLOBALS['failures']   = 0;

/**
 * A WP_Error stand-in with the same shape the code under test relies on.
 */
class WP_Error {
	/**
	 * Error code.
	 *
	 * @var string
	 */
	public $code;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Constructor.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 */
	public function __construct( $code, $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	/**
	 * Returns the error code.
	 *
	 * @return string
	 */
	public function get_error_code() {
		return $this->code;
	}

	/**
	 * Returns the error message.
	 *
	 * @return string
	 */
	public function get_error_message() {
		return $this->message;
	}
}

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['options'][ $key ] = $value;
	return true;
}

function get_transient( $key ) {
	return array_key_exists( $key, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $key ] : false;
}

function set_transient( $key, $value, $expiry = 0 ) {
	$GLOBALS['transients'][ $key ] = $value;
	return true;
}

function delete_transient( $key ) {
	unset( $GLOBALS['transients'][ $key ] );
	return true;
}

function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/' );
}

function wp_json_encode( $data ) {
	return json_encode( $data );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function sanitize_user( $name, $strict = false ) {
	return preg_replace( '/[^a-zA-Z0-9 _.\-@]/', '', (string) $name );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function is_email( $value ) {
	return (bool) filter_var( $value, FILTER_VALIDATE_EMAIL );
}

function wp_generate_password( $length = 12, $special = true, $extra = false ) {
	return substr( str_repeat( 'aA1!', (int) ceil( $length / 4 ) ), 0, $length );
}

function apply_filters( $hook, $value ) {
	$args = array_slice( func_get_args(), 1 );

	if ( isset( $GLOBALS['filters'][ $hook ] ) ) {
		return call_user_func_array( $GLOBALS['filters'][ $hook ], $args );
	}

	return $value;
}

function do_action( $hook ) {
	$GLOBALS['actions'][] = func_get_args();
}

function __( $text, $domain = '' ) {
	return $text;
}

function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['calls'][] = $url;

	if ( ! isset( $GLOBALS['http'][ $url ] ) ) {
		return new WP_Error( 'http_request_failed', 'Not found' );
	}

	return array(
		'code' => 200,
		'body' => wp_json_encode( $GLOBALS['http'][ $url ] ),
	);
}

function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? $response['body'] : '';
}

function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['code'] ) ? $response['code'] : 0;
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

/**
 * Asserts one expectation and records the outcome.
 *
 * @param string $label    What is being checked.
 * @param mixed  $actual   Actual value.
 * @param mixed  $expected Expected value.
 * @return void
 */
function check( $label, $actual, $expected ) {
	if ( $actual === $expected ) {
		echo "ok   $label\n";
		return;
	}

	++$GLOBALS['failures'];
	echo 'FAIL ' . $label . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n"; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
}

/**
 * Prints a summary and exits with a status the shell can act on.
 *
 * @return void
 */
function finish() {
	if ( $GLOBALS['failures'] > 0 ) {
		echo "\n{$GLOBALS['failures']} check(s) failed.\n";
		exit( 1 );
	}

	echo "\nAll checks passed.\n";
	exit( 0 );
}
