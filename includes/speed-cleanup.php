<?php
/**
 * Weekly clear-out of old WooCommerce background jobs and expired data.
 *
 * WooCommerce runs its background jobs through Action Scheduler, which keeps a
 * row for every job it has ever finished. On a busy store that is hundreds of
 * thousands of rows, and every one of them slows the queries that look for the
 * next job. This removes finished and cancelled jobs once they are old, failed
 * ones later, and the data that has already expired: transients, and
 * WooCommerce's saved visitor sessions.
 *
 * Jobs still waiting or running are never touched.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The event the weekly clear-out runs on.
 */
const BLUEWORX_CLEANUP_HOOK = 'blueworx_weekly_cleanup';

/**
 * The one-off event that carries on when a run ran out of time.
 */
const BLUEWORX_CLEANUP_MORE_HOOK = 'blueworx_weekly_cleanup_more';

/**
 * Adds a once-a-week schedule, which WordPress does not have of its own.
 *
 * @param array $schedules Existing schedules.
 * @return array Schedules including blueworx_weekly.
 */
function blueworx_weekly_schedule( $schedules ) {
	if ( ! isset( $schedules['blueworx_weekly'] ) ) {
		$schedules['blueworx_weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once a week', 'blueworx-labs-wordpress' ),
		);
	}

	return $schedules;
}

/**
 * Gets how old a finished job must be before it is removed.
 *
 * @param string $status Job status: complete, canceled or failed.
 * @return int Days.
 */
function blueworx_cleanup_retention_days( $status ) {
	$days = 'failed' === $status ? 30 : 14;

	return max( 1, (int) apply_filters( 'blueworx_cleanup_retention_days', $days, $status ) );
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Action Scheduler's and WooCommerce's own tables, and a one-off clear-out whose result must never be cached.

/**
 * Whether a database table exists.
 *
 * @param string $table Full table name.
 * @return bool True when it exists.
 */
function blueworx_table_exists( $table ) {
	global $wpdb;

	return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
}

/**
 * Removes old finished jobs from Action Scheduler, in small batches.
 *
 * A batch at a time so a store with a huge backlog never holds the database in
 * one long delete. Stops when the time is up and says whether more is left.
 *
 * @param float $deadline microtime() at which to stop.
 * @return array Array with 'deleted' (jobs removed) and 'more' (whether any old ones remain).
 */
function blueworx_cleanup_action_scheduler( $deadline ) {
	global $wpdb;

	$actions = $wpdb->prefix . 'actionscheduler_actions';
	$logs    = $wpdb->prefix . 'actionscheduler_logs';
	$deleted = 0;

	if ( ! blueworx_table_exists( $actions ) ) {
		return array(
			'deleted' => 0,
			'more'    => false,
		);
	}

	$has_logs = blueworx_table_exists( $logs );
	$more     = false;

	foreach ( array( 'complete', 'canceled', 'failed' ) as $status ) {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( blueworx_cleanup_retention_days( $status ) * DAY_IN_SECONDS ) );

		// Age is counted from when the job last ran, as Action Scheduler's own
		// clean-up does, and not from when it was due: a job due three weeks ago
		// that only finished today is not old. A cancelled job never ran, so it
		// has to be old by both counts.
		$age = 'canceled' === $status
			? 'last_attempt_gmt < %s AND scheduled_date_gmt < %s'
			: 'last_attempt_gmt < %s AND last_attempt_gmt > %s';
		$low = 'canceled' === $status ? $cutoff : '2000-01-01 00:00:00';

		while ( true ) {
			if ( microtime( true ) >= $deadline ) {
				$more = true;
				break 2;
			}

			$ids = $wpdb->get_col(
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The age test carries two placeholders, counted below.
					"SELECT action_id FROM {$actions} WHERE status = %s AND {$age} ORDER BY action_id ASC LIMIT 500", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Table name from the prefix; the age test is one of two fixed strings above.
					$status,
					$cutoff,
					$low
				)
			);

			if ( empty( $ids ) ) {
				break;
			}

			$ids          = array_map( 'absint', $ids );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			if ( $has_logs && false === $wpdb->query( $wpdb->prepare( "DELETE FROM {$logs} WHERE action_id IN ({$placeholders})", $ids ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name from the prefix; one %d per id.
				break 2;
			}

			$removed = $wpdb->query( $wpdb->prepare( "DELETE FROM {$actions} WHERE action_id IN ({$placeholders})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name from the prefix; one %d per id.

			// A delete that fails would pick the same rows again until time ran
			// out, so stop, and say nothing more is waiting: trying again in five
			// minutes would fail the same way.
			if ( ! $removed ) {
				break 2;
			}

			$deleted += (int) $removed;
		}
	}

	return array(
		'deleted' => $deleted,
		'more'    => $more,
	);
}
/**
 * Removes WooCommerce visitor sessions that have already expired.
 *
 * @return int Sessions removed.
 */
function blueworx_cleanup_expired_sessions() {
	global $wpdb;

	$table = $wpdb->prefix . 'woocommerce_sessions';

	if ( ! blueworx_table_exists( $table ) ) {
		return 0;
	}

	return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE session_expiry < %d", time() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is built from the site's own prefix.
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery

/**
 * Runs the clear-out and remembers what it did.
 *
 * @param int $seconds Longest it may run for.
 * @return array Result with 'time', 'jobs', 'sessions' and 'more'.
 */
function blueworx_cleanup_run( $seconds = 20 ) {
	$deadline = microtime( true ) + max( 1, (int) $seconds );
	$jobs     = blueworx_cleanup_action_scheduler( $deadline );

	// Expired transients are removed from the database directly, so the ones an
	// object cache never held do not pile up in the options table.
	if ( function_exists( 'delete_expired_transients' ) ) {
		delete_expired_transients( true );
	}

	$result = array(
		'time'     => time(),
		'jobs'     => $jobs['deleted'],
		'sessions' => blueworx_cleanup_expired_sessions(),
		'more'     => $jobs['more'],
	);

	update_option( 'blueworx_cleanup_last', $result, false );

	// A big backlog is cleared over several short runs, not one long one.
	if ( $result['more'] && ! wp_next_scheduled( BLUEWORX_CLEANUP_MORE_HOOK ) ) {
		wp_schedule_single_event( time() + ( 5 * MINUTE_IN_SECONDS ), BLUEWORX_CLEANUP_MORE_HOOK );
	}

	return $result;
}

/**
 * The cron event's callback.
 *
 * @return void
 */
function blueworx_cleanup_cron() {
	if ( blueworx_feature_enabled( 'weekly_cleanup' ) ) {
		blueworx_cleanup_run();
	}
}

/**
 * Keeps the weekly event in step with its switch.
 *
 * @return void
 */
function blueworx_cleanup_sync_schedule() {
	$next = wp_next_scheduled( BLUEWORX_CLEANUP_HOOK );

	if ( blueworx_feature_enabled( 'weekly_cleanup' ) ) {
		if ( ! $next ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'blueworx_weekly', BLUEWORX_CLEANUP_HOOK );
		}
	} else {
		if ( $next ) {
			wp_clear_scheduled_hook( BLUEWORX_CLEANUP_HOOK );
		}

		if ( wp_next_scheduled( BLUEWORX_CLEANUP_MORE_HOOK ) ) {
			wp_clear_scheduled_hook( BLUEWORX_CLEANUP_MORE_HOOK );
		}
	}
}

add_filter( 'cron_schedules', 'blueworx_weekly_schedule' ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- A weekly schedule is the point of this.
add_action( BLUEWORX_CLEANUP_HOOK, 'blueworx_cleanup_cron' );
add_action( BLUEWORX_CLEANUP_MORE_HOOK, 'blueworx_cleanup_cron' );
add_action( 'init', 'blueworx_cleanup_sync_schedule' );
