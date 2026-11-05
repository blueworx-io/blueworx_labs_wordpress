<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Sets a test site up for the Klaviyo spec, runs the queue and reports what was
 * sent. Installs tests/fixtures/klaviyo-mock.php first, so nothing can reach the
 * real Klaviyo or SureCart.
 *
 * Usage: php klaviyo.php /absolute/path/to/wp-load.php <command> [args]
 *
 *   setup                  Installs the test double and fills in the settings.
 *   off                    Switches the function off and empties the settings.
 *   reset                  Clears the queue, log, memory and recorded calls.
 *   mode <ok|down|rejects> How the fake Klaviyo behaves.
 *   profile <json|none>    The profile the fake Klaviyo holds.
 *   records <json>         The SureCart records to serve.
 *   option <name> <value>  Sets one of the feature's settings.
 *   drain                  Runs the queue and prints {calls,jobs,log}.
 *   due                    Makes every waiting job due now.
 *   sureforms <json>       Fires the SureForms hook for form 4773.
 *   sureforms-other <json> Fires the SureForms hook for a different form.
 *   elementor <name> <json> Fires the Elementor hook for a form with that name.
 *   cleanup                Removes everything, the test double included.
 *
 * @package BlueWorxLabsTests
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- A fixture: it reads the tables directly on purpose.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.WP.AlternativeFunctions -- A CLI fixture writing its own files and stderr.
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Reports a bad path on the command line.

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$command = isset( $argv[2] ) ? $argv[2] : '';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// The test double has to be in place before WordPress loads, so a queue run
// started by this very process cannot reach anything real.
$mu_plugins = dirname( $wp_load ) . '/wp-content/mu-plugins';
$mock       = $mu_plugins . '/blueworx-klaviyo-mock.php';

if ( 'setup' === $command ) {
	if ( ! is_dir( $mu_plugins ) ) {
		mkdir( $mu_plugins, 0777, true );
	}

	copy( __DIR__ . '/klaviyo-mock.php', $mock );
}

// See tests/fixtures/impostor-support-user.php for why this comes before wp-load.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;

/**
 * Clears the queue, the log, the memory and the recorded calls.
 *
 * @return void
 */
function klaviyo_fixture_reset() {
	global $wpdb;

	foreach ( array( 'job_', 'seen_', 'sub_', 'test_call_' ) as $family ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'blueworx_klaviyo_' . $family ) . '%' ) );
	}

	delete_option( 'blueworx_klaviyo_log' );
	delete_option( 'blueworx_klaviyo_test_profile' );
	delete_option( 'blueworx_klaviyo_test_records' );
	delete_option( 'blueworx_klaviyo_last_refused' );
	delete_transient( 'blueworx_klaviyo_lock' );
	update_option( 'blueworx_klaviyo_test_mode', 'ok', 'no' );
	wp_cache_flush();
}

/**
 * What the fake Klaviyo has been asked, oldest first.
 *
 * @return array
 */
function klaviyo_fixture_calls() {
	global $wpdb;

	$rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name ASC", $wpdb->esc_like( 'blueworx_klaviyo_test_call_' ) . '%' ) );

	return array_map(
		static function ( $row ) {
			return json_decode( maybe_unserialize( $row ), true );
		},
		$rows
	);
}

/**
 * Prints one answer as JSON on the last line.
 *
 * @param mixed $answer The answer.
 * @return void
 */
function klaviyo_fixture_print( $answer ) {
	echo wp_json_encode( $answer ) . "\n";
}

$arg = isset( $argv[3] ) ? $argv[3] : '';

switch ( $command ) {
	case 'setup':
		klaviyo_fixture_reset();
		update_option( 'blueworx_feature_klaviyo', '1' );
		update_option( 'blueworx_klaviyo_key', 'pk_test_fixture_key_abcd', 'no' );
		update_option( 'blueworx_klaviyo_webhook_secret', 'fixture-signing-secret', 'no' );
		update_option( 'blueworx_klaviyo_list_info', 'Y5fFQA', 'no' );
		update_option( 'blueworx_klaviyo_list_reservation', 'SFZx43', 'no' );
		update_option( 'blueworx_klaviyo_list_members', 'XRYaZh', 'no' );
		update_option( 'blueworx_klaviyo_info_form_name', 'New Form', 'no' );
		update_option( 'blueworx_klaviyo_consent_text', 'Keep me posted.', 'no' );
		update_option( 'blueworx_klaviyo_reservation_form_id', '4773', 'no' );
		update_option( 'blueworx_klaviyo_founding_product', 'founding-membership', 'no' );
		update_option( 'blueworx_klaviyo_founding_start_date', '2027-01-15', 'no' );
		update_option( 'blueworx_klaviyo_send_test_mode', '0', 'no' );
		klaviyo_fixture_print( array( 'ok' => true ) );
		break;

	case 'off':
		update_option( 'blueworx_feature_klaviyo', '0' );
		klaviyo_fixture_print( array( 'ok' => true ) );
		break;

	case 'reset':
		klaviyo_fixture_reset();
		klaviyo_fixture_print( array( 'ok' => true ) );
		break;

	case 'mode':
		update_option( 'blueworx_klaviyo_test_mode', $arg, 'no' );
		klaviyo_fixture_print( array( 'ok' => true ) );
		break;

	case 'profile':
		if ( 'none' === $arg ) {
			delete_option( 'blueworx_klaviyo_test_profile' );
		} else {
			update_option( 'blueworx_klaviyo_test_profile', json_decode( $arg, true ), 'no' );
		}
		klaviyo_fixture_print( array( 'ok' => true ) );
		break;

	case 'records':
		update_option( 'blueworx_klaviyo_test_records', json_decode( $arg, true ), 'no' );
		klaviyo_fixture_print( array( 'ok' => true ) );
		break;

	case 'option':
		update_option( 'blueworx_klaviyo_' . $arg, isset( $argv[4] ) ? $argv[4] : '', 'no' );
		klaviyo_fixture_print( array( 'ok' => true ) );
		break;

	case 'due':
		foreach ( blueworx_klaviyo_waiting_jobs() as $name => $job ) {
			$job['next_at'] = 0;
			update_option( $name, $job, 'no' );
		}
		klaviyo_fixture_print( array( 'ok' => true ) );
		break;

	case 'drain':
		// A background request may hold the lock for a moment.
		for ( $attempt = 0; $attempt < 20; $attempt++ ) {
			delete_transient( 'blueworx_klaviyo_lock' );
			blueworx_klaviyo_run_queue();

			$due = array_filter(
				blueworx_klaviyo_waiting_jobs(),
				static function ( $job ) {
					return empty( $job['next_at'] ) || (int) $job['next_at'] <= time();
				}
			);

			if ( ! $due ) {
				break;
			}

			usleep( 250000 );
		}

		wp_cache_flush();

		klaviyo_fixture_print(
			array(
				'calls' => klaviyo_fixture_calls(),
				'jobs'  => array_values( blueworx_klaviyo_waiting_jobs() ),
				'log'   => get_option( 'blueworx_klaviyo_log', array() ),
			)
		);
		break;

	case 'sureforms':
	case 'sureforms-other':
		do_action( 'srfm_submission_accepted', 101, 'sureforms' === $command ? 4773 : 999, json_decode( $arg, true ) );
		klaviyo_fixture_print( array( 'ok' => true ) );
		break;

	case 'elementor':
		$record = new class( $arg, json_decode( $argv[4], true ) ) {
			/**
			 * Form name.
			 *
			 * @var string
			 */
			private $name;

			/**
			 * Form fields.
			 *
			 * @var array
			 */
			private $fields;

			/**
			 * Stands in for Elementor's form record.
			 *
			 * @param string $name   Form name.
			 * @param array  $fields Form fields.
			 */
			public function __construct( $name, $fields ) {
				$this->name   = $name;
				$this->fields = $fields;
			}

			/**
			 * The form's setting.
			 *
			 * @param string $setting Setting name.
			 * @return string
			 */
			public function get_form_settings( $setting ) {
				return 'form_name' === $setting ? $this->name : '';
			}

			/**
			 * The record's property.
			 *
			 * @param string $property Property name.
			 * @return array
			 */
			public function get( $property ) {
				return 'fields' === $property ? $this->fields : array();
			}
		};

		$_POST['referrer'] = 'https://padlx.com.au/';

		do_action( 'elementor_pro/forms/new_record', $record ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Elementor Pro's own hook.
		klaviyo_fixture_print( array( 'ok' => true ) );
		break;

	case 'cleanup':
		klaviyo_fixture_reset();
		foreach ( array_keys( blueworx_klaviyo_setting_defaults() ) as $name ) {
			delete_option( 'blueworx_klaviyo_' . $name );
		}
		delete_option( 'blueworx_klaviyo_key' );
		delete_option( 'blueworx_klaviyo_webhook_secret' );
		delete_option( 'blueworx_klaviyo_test_mode' );
		delete_option( 'blueworx_feature_klaviyo' );
		wp_clear_scheduled_hook( 'blueworx_klaviyo_tick' );
		if ( is_file( $mock ) ) {
			unlink( $mock );
		}
		klaviyo_fixture_print( array( 'ok' => true ) );
		break;

	default:
		fwrite( STDERR, 'Unknown command: ' . $command . "\n" );
		exit( 1 );
}
