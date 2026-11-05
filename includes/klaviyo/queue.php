<?php
/**
 * Klaviyo connection: the queue and the log.
 *
 * A checkout or a form never waits on Klaviyo. It writes one row and carries on,
 * and a background run does the talking and the retrying. If Klaviyo is down the
 * person never knows, and the work waits for it to come back.
 *
 * Each job is its own option row rather than an item in one big array, because
 * two people paying at the same moment would otherwise overwrite each other's
 * work. A repeat of the same job lands on the same row, so it cannot run twice.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many sends the log keeps.
 */
const BLUEWORX_KLAVIYO_LOG_LIMIT = 100;

/**
 * The option the log lives in.
 */
const BLUEWORX_KLAVIYO_LOG_OPTION = 'blueworx_klaviyo_log';

/**
 * Start of every queued job's option name.
 */
const BLUEWORX_KLAVIYO_JOB_PREFIX = 'blueworx_klaviyo_job_';

/**
 * Start of every "this SureCart event has been received" marker.
 */
const BLUEWORX_KLAVIYO_SEEN_PREFIX = 'blueworx_klaviyo_seen_';

/**
 * How long a marker is kept. SureCart stops retrying well before this.
 */
const BLUEWORX_KLAVIYO_SEEN_DAYS = 30;

/**
 * The wait before each retry, in seconds. Its length sets the number of tries.
 *
 * @return int[]
 */
function blueworx_klaviyo_retry_delays() {
	return array( 60, 300, 900, 3600, 21600 );
}

/**
 * Adds a job, or quietly records why it cannot be added. Never throws.
 *
 * @param string $key Identifies the job, so a repeat lands on the same row.
 * @param array  $job The job: label, email when known, and either a plan to send or a SureCart webhook to work out first.
 * @return bool Whether it was queued.
 */
function blueworx_klaviyo_enqueue( $key, $job ) {
	if ( '' === blueworx_klaviyo_key() ) {
		blueworx_klaviyo_log( isset( $job['label'] ) ? $job['label'] : '', isset( $job['email'] ) ? $job['email'] : '', 'failed', __( 'There is no API key yet, so this was not sent.', 'blueworx-labs-wordpress' ) );
		return false;
	}

	$job['attempts'] = 0;
	$job['next_at']  = 0;

	add_option( BLUEWORX_KLAVIYO_JOB_PREFIX . md5( $key ), $job, '', 'no' );

	blueworx_klaviyo_wake_queue();

	return true;
}

/**
 * Asks WordPress to run the queue as soon as it can, in a separate request.
 *
 * The event's argument changes each minute because WordPress drops a repeat of
 * the same event within ten minutes, which would hold this job up that long.
 *
 * @return void
 */
function blueworx_klaviyo_wake_queue() {
	wp_schedule_single_event( time(), 'blueworx_klaviyo_run_queue', array( (int) floor( time() / MINUTE_IN_SECONDS ) ) );

	if ( function_exists( 'spawn_cron' ) ) {
		spawn_cron();
	}
}

/**
 * Reads the waiting jobs straight from the table, oldest first.
 *
 * Straight from the table because these rows are written by other requests, and
 * the option cache in this one would not know about them.
 *
 * @return array Jobs keyed by option name.
 */
function blueworx_klaviyo_waiting_jobs() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- See above: the cache cannot be trusted for rows other requests write.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT 200",
			$wpdb->esc_like( BLUEWORX_KLAVIYO_JOB_PREFIX ) . '%'
		)
	);

	$jobs = array();

	foreach ( (array) $rows as $row ) {
		$job = maybe_unserialize( $row->option_value );

		if ( is_array( $job ) ) {
			$jobs[ $row->option_name ] = $job;
		}
	}

	return $jobs;
}

/**
 * The background run: works through whatever is due.
 *
 * Two runs overlapping would only repeat work that is safe to repeat, so the
 * lock is there to save requests rather than to keep anything correct.
 *
 * @return void
 */
function blueworx_klaviyo_run_queue() {
	if ( ! blueworx_feature_enabled( 'klaviyo' ) || '' === blueworx_klaviyo_key() ) {
		return;
	}

	if ( get_transient( 'blueworx_klaviyo_lock' ) ) {
		return;
	}

	set_transient( 'blueworx_klaviyo_lock', 1, 3 * MINUTE_IN_SECONDS );

	$deadline = time() + 45;

	foreach ( blueworx_klaviyo_waiting_jobs() as $name => $job ) {
		if ( time() >= $deadline ) {
			break;
		}

		if ( ! empty( $job['next_at'] ) && (int) $job['next_at'] > time() ) {
			continue;
		}

		blueworx_klaviyo_process_job( $name, $job );
	}

	delete_transient( 'blueworx_klaviyo_lock' );

	blueworx_klaviyo_prune_seen();
}
add_action( 'blueworx_klaviyo_run_queue', 'blueworx_klaviyo_run_queue' );

/**
 * Handles one job from start to finish.
 *
 * @param string $name Option name.
 * @param array  $job  The job.
 * @return void
 */
function blueworx_klaviyo_process_job( $name, $job ) {
	$label = isset( $job['label'] ) ? $job['label'] : '';
	$email = isset( $job['email'] ) ? $job['email'] : '';

	try {
		$plans = isset( $job['webhook'] ) ? blueworx_klaviyo_surecart_plans( $job['webhook'] ) : array( $job['plan'] );
	} catch ( Throwable $thrown ) {
		$plans = new WP_Error( 'blueworx_klaviyo_exception', $thrown->getMessage() );
	}

	if ( is_wp_error( $plans ) ) {
		blueworx_klaviyo_retry_job( $name, $job, $label, $email, $plans->get_error_message() );
		return;
	}

	foreach ( $plans as $plan ) {
		$outcome = blueworx_klaviyo_send_plan( $plan );

		if ( ! $outcome['ok'] ) {
			if ( $outcome['retry'] ) {
				blueworx_klaviyo_retry_job( $name, $job, $plan['label'], $plan['email'], $outcome['error'] );
				return;
			}

			blueworx_klaviyo_log( $plan['label'], $plan['email'], 'failed', $outcome['error'] );
			continue;
		}

		blueworx_klaviyo_log( $plan['label'], $plan['email'], 'sent', '' );

		if ( ! empty( $plan['after'] ) ) {
			blueworx_klaviyo_remember( $plan['after']['subscription'], $plan['after']['state'] );
		}
	}

	delete_option( $name );
}

/**
 * Sets a job to try again later, or gives up on it out loud.
 *
 * @param string $name    Option name.
 * @param array  $job     The job.
 * @param string $label   What the log calls it.
 * @param string $email   Who it is about.
 * @param string $message What went wrong.
 * @return void
 */
function blueworx_klaviyo_retry_job( $name, $job, $label, $email, $message ) {
	$delays   = blueworx_klaviyo_retry_delays();
	$attempts = isset( $job['attempts'] ) ? (int) $job['attempts'] : 0;

	if ( $attempts >= count( $delays ) ) {
		blueworx_klaviyo_log( $label, $email, 'failed', $message );
		delete_option( $name );
		return;
	}

	$job['attempts'] = $attempts + 1;
	$job['next_at']  = time() + $delays[ $attempts ];

	update_option( $name, $job, 'no' );

	blueworx_klaviyo_log( $label, $email, 'retrying', $message );
}

/**
 * What the connection remembers about one subscription, between events.
 *
 * @param string $subscription_id Subscription ID.
 * @return array {
 *     @type string $price       The price it was last seen on.
 *     @type string $status      The status it was last seen in.
 *     @type bool   $cancel_sent Whether Membership Cancelled has been sent.
 * }
 */
function blueworx_klaviyo_recall( $subscription_id ) {
	$state = get_option( 'blueworx_klaviyo_sub_' . md5( (string) $subscription_id ), array() );

	return is_array( $state ) ? $state : array();
}

/**
 * Adds to what the connection remembers about one subscription.
 *
 * @param string $subscription_id Subscription ID.
 * @param array  $state           What to remember.
 * @return void
 */
function blueworx_klaviyo_remember( $subscription_id, $state ) {
	if ( '' === (string) $subscription_id ) {
		return;
	}

	update_option( 'blueworx_klaviyo_sub_' . md5( (string) $subscription_id ), array_merge( blueworx_klaviyo_recall( $subscription_id ), $state ), 'no' );
}

/**
 * Adds a line to the log.
 *
 * @param string $label   What it was.
 * @param string $email   Who it was about.
 * @param string $outcome sent, retrying or failed.
 * @param string $detail  What went wrong, if anything.
 * @return void
 */
function blueworx_klaviyo_log( $label, $email, $outcome, $detail ) {
	$log = get_option( BLUEWORX_KLAVIYO_LOG_OPTION, array() );
	$log = is_array( $log ) ? $log : array();

	array_unshift(
		$log,
		array(
			'time'    => time(),
			'label'   => (string) $label,
			'email'   => (string) $email,
			'outcome' => (string) $outcome,
			'detail'  => substr( (string) $detail, 0, 300 ),
		)
	);

	update_option( BLUEWORX_KLAVIYO_LOG_OPTION, array_slice( $log, 0, BLUEWORX_KLAVIYO_LOG_LIMIT ), 'no' );
}

/**
 * Whether a SureCart event has been received before.
 *
 * @param string $event_id SureCart's ID for the event.
 * @return bool
 */
function blueworx_klaviyo_seen( $event_id ) {
	return false !== get_option( BLUEWORX_KLAVIYO_SEEN_PREFIX . md5( (string) $event_id ), false );
}

/**
 * Records that a SureCart event has been received.
 *
 * @param string $event_id SureCart's ID for the event.
 * @return void
 */
function blueworx_klaviyo_mark_seen( $event_id ) {
	add_option( BLUEWORX_KLAVIYO_SEEN_PREFIX . md5( (string) $event_id ), time(), '', 'no' );
}

/**
 * Clears out old markers, at most once a day.
 *
 * @return void
 */
function blueworx_klaviyo_prune_seen() {
	global $wpdb;

	if ( get_transient( 'blueworx_klaviyo_pruned' ) ) {
		return;
	}

	set_transient( 'blueworx_klaviyo_pruned', 1, DAY_IN_SECONDS );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Housekeeping over a family of rows no API can list.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1000",
			$wpdb->esc_like( BLUEWORX_KLAVIYO_SEEN_PREFIX ) . '%'
		)
	);

	$cutoff = time() - BLUEWORX_KLAVIYO_SEEN_DAYS * DAY_IN_SECONDS;

	foreach ( (array) $rows as $row ) {
		if ( (int) $row->option_value < $cutoff ) {
			delete_option( $row->option_name );
		}
	}
}

/**
 * Keeps a slow run going every five minutes while the function is on, so retries
 * happen and nothing waits on a request that happens to wake the queue.
 *
 * @param array $schedules WordPress's schedules.
 * @return array
 */
function blueworx_klaviyo_cron_schedule( $schedules ) {
	$schedules['blueworx_klaviyo_five_minutes'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => 'Every five minutes (BlueWorx Klaviyo)',
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'blueworx_klaviyo_cron_schedule' ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Retries need to run sooner than the fifteen minutes the check asks for.

/**
 * Starts or stops the regular run to match the function's switch.
 *
 * @return void
 */
function blueworx_klaviyo_sync_cron() {
	$scheduled = wp_next_scheduled( 'blueworx_klaviyo_tick' );

	if ( blueworx_feature_enabled( 'klaviyo' ) ) {
		if ( ! $scheduled ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'blueworx_klaviyo_five_minutes', 'blueworx_klaviyo_tick' );
		}
	} elseif ( $scheduled ) {
		wp_clear_scheduled_hook( 'blueworx_klaviyo_tick' );
	}
}
add_action( 'init', 'blueworx_klaviyo_sync_cron' );
add_action( 'blueworx_klaviyo_tick', 'blueworx_klaviyo_run_queue' );
