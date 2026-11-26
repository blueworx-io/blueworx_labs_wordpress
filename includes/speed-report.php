<?php
/**
 * The weekly speed report.
 *
 * Once a week each site emails BlueWorx a short plain-text note: how long an
 * uncached home page takes, which pages were slowest, and whether the object
 * cache is actually being used on pages the page cache did not answer, not just
 * switched on. Switch it off under Performance as Weekly speed report.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The event the weekly report is sent on.
 */
const BLUEWORX_REPORT_HOOK = 'blueworx_weekly_speed_report';

/**
 * Where the report goes.
 *
 * Saved under the function's settings, and a filter lets a host or a site's
 * own code change it without touching the screen.
 *
 * @return string Email address.
 */
function blueworx_speed_report_recipient() {
	$saved = sanitize_email( (string) get_option( 'blueworx_speed_report_to', '' ) );
	$to    = '' !== $saved ? $saved : 'info@blueworx.com';

	$filtered = sanitize_email( (string) apply_filters( 'blueworx_speed_report_recipient', $to ) );

	return '' !== $filtered ? $filtered : $to;
}

/**
 * Times an uncached visit to the home page.
 *
 * Asks for the home page with a one-off argument on the end, so a page cache
 * keyed on the address cannot answer it, and tells caches not to. It is one
 * extra uncached visit a week, from the site to itself.
 *
 * @return array|null Array with 'seconds' and 'status', or null when it could not be reached.
 */
function blueworx_speed_probe_home() {
	$start    = microtime( true );
	$response = wp_remote_get(
		add_query_arg( 'blueworx_speed_check', (string) time(), home_url( '/' ) ),
		array(
			'timeout'     => 10,
			'redirection' => 3,
			'headers'     => array(
				'Cache-Control' => 'no-cache',
				'Pragma'        => 'no-cache',
			),
			'user-agent'  => 'BlueWorx speed check',
		)
	);

	if ( is_wp_error( $response ) ) {
		return null;
	}

	$headers = wp_remote_retrieve_headers( $response );

	return array(
		'seconds' => microtime( true ) - $start,
		'status'  => (int) wp_remote_retrieve_response_code( $response ),
		'cached'  => blueworx_response_looks_cached( is_object( $headers ) && method_exists( $headers, 'getAll' ) ? $headers->getAll() : (array) $headers ),
	);
}

/**
 * Whether a response's headers say a cache answered it.
 *
 * Page caches, content networks and proxies say so in different headers. Any of
 * them saying "hit", or an Age above zero, counts. A cache that says nothing
 * cannot be told apart from no cache, which is why the report words its answer
 * as what the headers showed.
 *
 * @param array $headers Response headers, name => value.
 * @return bool True when a header says the answer came from a cache.
 */
function blueworx_response_looks_cached( $headers ) {
	$headers = array_change_key_case( (array) $headers, CASE_LOWER );

	if ( isset( $headers['age'] ) && (int) $headers['age'] > 0 ) {
		return true;
	}

	foreach ( array( 'x-cache', 'x-cache-status', 'cf-cache-status', 'x-proxy-cache', 'x-litespeed-cache', 'x-sg-cache', 'x-fastcgi-cache', 'x-nginx-cache', 'x-varnish-cache' ) as $name ) {
		$value = isset( $headers[ $name ] ) ? (string) ( is_array( $headers[ $name ] ) ? implode( ',', $headers[ $name ] ) : $headers[ $name ] ) : '';

		if ( false !== stripos( $value, 'hit' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Says in plain words whether the object cache is doing its job.
 *
 * "On" and "used" are different things: a cache that is switched on but flushed
 * on every request, or one the site cannot reach, still shows as on.
 *
 * @param string $kind   'persistent', 'drop-in' or 'none'.
 * @param array  $sample Sample from blueworx_speed_sample().
 * @return array Array with 'rate' (percent or null) and 'text'.
 */
function blueworx_object_cache_verdict( $kind, $sample ) {
	$lookups = (int) $sample['hits'] + (int) $sample['misses'];
	$rate    = $lookups > 0 ? (int) round( 100 * $sample['hits'] / $lookups ) : null;

	if ( 'none' === $kind ) {
		return array(
			'rate' => null,
			'text' => 'No object cache. Every page asks the database for everything it needs.',
		);
	}

	if ( 'drop-in' === $kind ) {
		return array(
			'rate' => null,
			'text' => 'A cache file is in place but WordPress is not using it, so there is no object cache in practice. Check the cache server is running and reachable.',
		);
	}

	if ( (int) $sample['requests'] < 5 ) {
		return array(
			'rate' => $rate,
			'text' => 'On. Too few uncached pages were sampled this week to say how well it is used.',
		);
	}

	// The note left for the next page only survives if the cache is shared. One
	// page cannot find it, so the first of any run is not counted against it.
	$kept  = isset( $sample['kept'] ) ? (int) $sample['kept'] : 0;
	$lost  = isset( $sample['lost'] ) ? max( 0, (int) $sample['lost'] - 1 ) : 0;
	$tries = $kept + $lost;

	if ( $tries >= 5 && 0 === $kept ) {
		return array(
			'rate' => $rate,
			'text' => 'On, but nothing it holds survives from one page to the next, so in practice it is not a shared cache. Check the cache server is running and reachable.',
		);
	}

	// The hit rate counts what a cache remembers within one page as well, so it
	// cannot vouch for a cache that is shared. How often the note left for the
	// next page was still there can.
	if ( $tries >= 5 && $kept / $tries < 0.5 ) {
		return array(
			'rate' => $rate,
			'text' => sprintf( 'On but not holding on to much. A note left for the next page was found again only %d%% of the time, so the cache is being emptied or is not always reachable. Check it is not flushed on every change.', round( 100 * $kept / $tries ) ),
		);
	}

	if ( null === $rate ) {
		return array(
			'rate' => null,
			'text' => 'On, but this cache does not report hits and misses, so its use cannot be measured.',
		);
	}

	if ( $rate >= 80 ) {
		return array(
			'rate' => $rate,
			'text' => sprintf( 'Working. %d%% of lookups on uncached pages were answered from the cache.', $rate ),
		);
	}

	if ( $rate >= 50 ) {
		return array(
			'rate' => $rate,
			'text' => sprintf( 'Partly used. Only %d%% of lookups on uncached pages were answered from the cache.', $rate ),
		);
	}

	return array(
		'rate' => $rate,
		'text' => sprintf( 'On but barely used. Only %d%% of lookups on uncached pages were answered from the cache. It may be flushed too often, or full.', $rate ),
	);
}

/**
 * Gathers everything the report says.
 *
 * @param bool       $probe  Whether to time an uncached home page visit.
 * @param array|null $sample The week's object cache sample. Default: the one being kept now.
 * @return array Report data.
 */
function blueworx_speed_report_data( $probe = true, $sample = null ) {
	global $wp_version;

	$sample = is_array( $sample ) ? $sample : blueworx_speed_sample();
	$kind   = blueworx_object_cache_kind();

	return array(
		'site'     => home_url( '/' ),
		'name'     => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
		'plugin'   => defined( 'BLUEWORX_LABS_VERSION' ) ? BLUEWORX_LABS_VERSION : '',
		'wp'       => (string) $wp_version,
		'php'      => PHP_VERSION,
		'woo'      => defined( 'WC_VERSION' ) ? (string) WC_VERSION : '',
		'home'     => $probe ? blueworx_speed_probe_home() : null,
		'cache'    => $kind,
		'sample'   => $sample,
		'verdict'  => blueworx_object_cache_verdict( $kind, $sample ),
		'slow'     => blueworx_slow_log_by_page( 7, 10 ),
		'slow_n'   => count(
			array_filter(
				blueworx_slow_log_entries(),
				static function ( $entry ) {
					return is_array( $entry ) && (int) $entry['t'] >= time() - WEEK_IN_SECONDS;
				}
			)
		),
		'slow_sec' => blueworx_slow_threshold(),
		'log_on'   => blueworx_feature_enabled( 'slow_log' ),
		'cleanup'  => get_option( 'blueworx_cleanup_last', array() ),
	);
}

/**
 * Writes the report as plain text.
 *
 * @param array $data Data from blueworx_speed_report_data().
 * @return string Report text.
 */
function blueworx_speed_report_text( $data ) {
	$lines   = array();
	$lines[] = sprintf( 'Weekly speed report: %s', $data['name'] );
	$lines[] = $data['site'];
	$lines[] = sprintf( 'BlueWorx plugin %s, WordPress %s, PHP %s%s', $data['plugin'], $data['wp'], $data['php'], '' !== $data['woo'] ? ', WooCommerce ' . $data['woo'] : '' );
	$lines[] = '';
	$lines[] = 'HOME PAGE, FRESH REQUEST';

	if ( is_array( $data['home'] ) && $data['home']['status'] >= 200 && $data['home']['status'] < 300 && empty( $data['home']['cached'] ) ) {
		$lines[] = sprintf( '%.1f seconds (answered with status %d)', $data['home']['seconds'], $data['home']['status'] );
		$lines[] = 'Asked for with a one-off address and no sign of a cache in the answer. A content network that ignores the one-off address and says nothing could still have answered it, so treat a very quick result with care.';
	} elseif ( is_array( $data['home'] ) ) {
		$lines[] = sprintf(
			'Not a clean uncached measurement: %.1f seconds, status %d, %s.',
			$data['home']['seconds'],
			$data['home']['status'],
			! empty( $data['home']['cached'] ) ? 'and the response headers say a cache answered it' : 'which is not a successful page'
		);
	} else {
		$lines[] = 'Could not be measured: the site could not reach itself.';
	}

	$sample  = $data['sample'];
	$lines[] = '';
	$lines[] = 'OBJECT CACHE';
	$lines[] = sprintf( 'Type: %s', 'persistent' === $data['cache'] ? 'persistent (Redis, Memcached or similar)' : ( 'drop-in' === $data['cache'] ? 'file present, not in use' : 'none' ) );
	$lines[] = $data['verdict']['text'];

	if ( (int) $sample['requests'] > 0 ) {
		$lines[] = sprintf(
			'Sampled %d uncached pages since %s: %d ms on average, %d database queries on average.',
			$sample['requests'],
			! empty( $sample['since'] ) ? blueworx_local_date( (int) $sample['since'], 'j M' ) : 'the start of the week',
			round( $sample['ms'] / $sample['requests'] ),
			round( $sample['queries'] / $sample['requests'] )
		);
	}

	$lines[] = '';
	$lines[] = sprintf( 'SLOW PAGES (over %s seconds, last 7 days)', rtrim( rtrim( number_format( $data['slow_sec'], 1 ), '0' ), '.' ) );

	if ( ! $data['log_on'] ) {
		$lines[] = 'The slow-page log is switched off on this site.';
	} elseif ( empty( $data['slow'] ) ) {
		$lines[] = 'None logged.';
	} else {
		$lines[] = sprintf( '%d slow requests in all. Worst first:', $data['slow_n'] );

		foreach ( $data['slow'] as $page ) {
			$lines[] = sprintf( '%s  worst %.1fs, average %.1fs, %d times', $page['path'], $page['worst'] / 1000, $page['average'] / 1000, $page['count'] );
		}
	}

	if ( is_array( $data['cleanup'] ) && ! empty( $data['cleanup']['time'] ) ) {
		$lines[] = '';
		$lines[] = 'LAST CLEAR-OUT';
		$lines[] = sprintf(
			'%s: %d old background jobs and %d expired visitor sessions removed%s.',
			blueworx_local_date( (int) $data['cleanup']['time'], 'j M Y' ),
			$data['cleanup']['jobs'],
			$data['cleanup']['sessions'],
			! empty( $data['cleanup']['error'] ) ? ', then stopped on a database error' : ( ! empty( $data['cleanup']['more'] ) ? ', more still to go' : '' )
		);
	}

	return implode( "\n", $lines ) . "\n";
}

/**
 * Sends the report, then starts the next week's count.
 *
 * @param bool $probe     Whether to time an uncached home page visit.
 * @param bool $scheduled True for the weekly report, which starts the next week's count. A report sent by hand never does, so it cannot take the week's measurements from the scheduled one.
 * @return bool True when the mail was accepted.
 */
function blueworx_speed_report_send( $probe = true, $scheduled = false ) {
	// The weekly report takes its sample, and starts the next week's, before
	// anything slow happens (the home page visit, the email). Measurements made
	// while it is being sent belong to the next report, not to none. It is done
	// whether or not the mail goes out, so a failed send cannot make the next
	// report claim several weeks of measurements as one week's. A report sent by
	// hand only reads the sample and leaves it running.
	$sample = null;

	if ( $scheduled ) {
		$sample = blueworx_speed_sample();
		delete_option( 'blueworx_speed_sample' );
	}

	$data    = blueworx_speed_report_data( $probe, $sample );
	$subject = preg_replace( '/[\r\n]+/', ' ', sprintf( 'Weekly speed report: %s', $data['name'] ) );
	$sent    = wp_mail( blueworx_speed_report_recipient(), $subject, blueworx_speed_report_text( $data ) );

	update_option(
		'blueworx_speed_report_last',
		array(
			'time' => time(),
			'sent' => (bool) $sent,
		),
		false
	);

	if ( $sent ) {
		// The slow-page log keeps four weeks, so a page that was slow once does
		// not stay on it for ever.
		$cutoff = time() - ( 4 * WEEK_IN_SECONDS );

		update_option(
			'blueworx_slow_log',
			array_values(
				array_filter(
					blueworx_slow_log_entries(),
					static function ( $entry ) use ( $cutoff ) {
						return is_array( $entry ) && (int) $entry['t'] >= $cutoff;
					}
				)
			),
			false
		);
	}

	return (bool) $sent;
}

/**
 * The weekly event's callback.
 *
 * @return void
 */
function blueworx_speed_report_cron() {
	if ( blueworx_feature_enabled( 'speed_report' ) ) {
		blueworx_speed_report_send( true, true );
	}
}

/**
 * Keeps the weekly event in step with its switch.
 *
 * @return void
 */
function blueworx_speed_report_sync_schedule() {
	$next = wp_next_scheduled( BLUEWORX_REPORT_HOOK );

	if ( blueworx_feature_enabled( 'speed_report' ) ) {
		if ( ! $next ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'blueworx_weekly', BLUEWORX_REPORT_HOOK );
		}
	} elseif ( $next ) {
		wp_clear_scheduled_hook( BLUEWORX_REPORT_HOOK );
	}
}

/**
 * Sends a report now, for an administrator who asked for one on the settings screen.
 *
 * The button posts back to the settings screen rather than to admin-post.php:
 * the settings form around it carries its own hidden "action" field, and in a
 * POST that one wins over the address, so admin-post.php would run the save.
 *
 * @return void
 */
function blueworx_speed_report_now() {
	// Only when the button was the thing pressed: this runs on every admin page.
	if ( empty( $_POST['blueworx_speed_report_now'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked below, once it is known this is the button.
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to do this.', 'blueworx-labs-wordpress' ), '', array( 'response' => 403 ) );
	}

	blueworx_require_post_request();
	check_admin_referer( 'blueworx_speed_report_now', 'blueworx_speed_nonce' );

	$sent = blueworx_speed_report_send();

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'            => 'blueworx-labs-wordpress',
				'section'         => 'performance',
				'blueworx_report' => $sent ? 'sent' : 'failed',
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}

add_action( BLUEWORX_REPORT_HOOK, 'blueworx_speed_report_cron' );
add_action( 'init', 'blueworx_speed_report_sync_schedule' );
add_action( 'admin_init', 'blueworx_speed_report_now' );

/**
 * Saves the slow-page log and speed report settings from the Enhancements form.
 *
 * Called from the one save handler, after it has checked the nonce and the
 * visitor's capability.
 *
 * @return void
 */
function blueworx_save_speed_settings() {
	if ( isset( $_POST['blueworx_slow_threshold'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.
		$seconds = (float) wp_unslash( $_POST['blueworx_slow_threshold'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by the calling handler; cast to a number.

		update_option( 'blueworx_slow_threshold', max( 0.2, min( 60, $seconds ) ) );
	}

	if ( isset( $_POST['blueworx_speed_report_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.
		// Blank is allowed, and means the default address.
		update_option( 'blueworx_speed_report_to', sanitize_email( wp_unslash( $_POST['blueworx_speed_report_to'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.
	}
}

/**
 * Builds the settings panel under one of the speed functions.
 *
 * @param string $key Feature key: slow_log, speed_report or weekly_cleanup.
 * @return string HTML.
 */
function blueworx_speed_detail_html( $key ) {
	if ( 'slow_log' === $key ) {
		$rows = '';

		foreach ( blueworx_slow_log_by_page( 7, 10 ) as $page ) {
			$rows .= sprintf(
				'<tr><td><span class="bw-table__primary">%1$s</span></td><td class="bw-table__num">%2$s</td><td class="bw-table__num">%3$s</td><td class="bw-table__num">%4$d</td></tr>',
				esc_html( $page['path'] ),
				esc_html( number_format_i18n( $page['worst'] / 1000, 1 ) ),
				esc_html( number_format_i18n( $page['average'] / 1000, 1 ) ),
				(int) $page['count']
			);
		}

		if ( '' === $rows ) {
			$list = '<p class="bw-field__help" data-testid="bw-slow-log-empty">' . esc_html__( 'No slow pages in the last seven days.', 'blueworx-labs-wordpress' ) . '</p>';
		} else {
			$list = sprintf(
				'<div style="overflow-x:auto"><table class="bw-table" data-testid="bw-slow-log-table"><thead><tr><th>%1$s</th><th class="bw-table__num">%2$s</th><th class="bw-table__num">%3$s</th><th class="bw-table__num">%4$s</th></tr></thead><tbody>%5$s</tbody></table></div>',
				esc_html__( 'Page', 'blueworx-labs-wordpress' ),
				esc_html__( 'Slowest (s)', 'blueworx-labs-wordpress' ),
				esc_html__( 'Average (s)', 'blueworx-labs-wordpress' ),
				esc_html__( 'Times', 'blueworx-labs-wordpress' ),
				$rows
			);
		}

		return blueworx_detail_stack(
			blueworx_ds_field(
				array(
					'label'   => __( 'Log a page that takes longer than (seconds)', 'blueworx-labs-wordpress' ),
					'for'     => 'blueworx_slow_threshold',
					'control' => blueworx_ds_input(
						array(
							'type'  => 'number',
							'name'  => 'blueworx_slow_threshold',
							'id'    => 'blueworx_slow_threshold',
							'value' => (string) blueworx_slow_threshold(),
							'small' => true,
							'attrs' => array(
								'min'  => '0.2',
								'max'  => '60',
								'step' => '0.1',
							),
						)
					),
					'help'    => __( 'The time the server spent building the page. Only the address, without anything after a question mark except the names of the options, is kept, and the last 200 are remembered.', 'blueworx-labs-wordpress' ),
				)
			) . blueworx_ds_field(
				array(
					'label'   => __( 'Slowest pages, last seven days', 'blueworx-labs-wordpress' ),
					'control' => $list,
				)
			)
		);
	}

	if ( 'speed_report' === $key ) {
		$last   = get_option( 'blueworx_speed_report_last', array() );
		$status = '';
		$flash  = isset( $_GET['blueworx_report'] ) ? sanitize_key( wp_unslash( $_GET['blueworx_report'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only chooses which notice to show.

		if ( 'sent' === $flash || 'failed' === $flash ) {
			$status = blueworx_ds_notice(
				array(
					'tone' => 'sent' === $flash ? 'success' : 'warning',
					'text' => 'sent' === $flash
						? __( 'A report was sent.', 'blueworx-labs-wordpress' )
						: __( 'The report could not be sent. Check that this site can send email.', 'blueworx-labs-wordpress' ),
				)
			);
		}

		$last_text = is_array( $last ) && ! empty( $last['time'] ) && empty( $last['sent'] )
			? sprintf(
				/* translators: %s: date of the last attempt. */
				__( 'The last report, on %s, could not be sent. Check that this site can send email.', 'blueworx-labs-wordpress' ),
				blueworx_local_date( (int) $last['time'] )
			)
			: ( is_array( $last ) && ! empty( $last['time'] )
			? sprintf(
				/* translators: %s: date the last report was sent. */
				__( 'The last report went out on %s.', 'blueworx-labs-wordpress' ),
				blueworx_local_date( (int) $last['time'] )
			)
			: __( 'No report has been sent yet. The first goes out within a day or so of switching this on.', 'blueworx-labs-wordpress' ) );

		return blueworx_detail_stack(
			$status . blueworx_ds_field(
				array(
					'label'   => __( 'Send the report to', 'blueworx-labs-wordpress' ),
					'for'     => 'blueworx_speed_report_to',
					'control' => blueworx_ds_input(
						array(
							'type'  => 'email',
							'name'  => 'blueworx_speed_report_to',
							'id'    => 'blueworx_speed_report_to',
							'value' => blueworx_speed_report_recipient(),
						)
					),
					'help'    => $last_text . ' ' . __( 'It says how long the home page takes uncached, which pages were slowest, and whether the object cache is really being used.', 'blueworx-labs-wordpress' ),
				)
			) . '<input type="hidden" name="blueworx_speed_nonce" value="' . esc_attr( wp_create_nonce( 'blueworx_speed_report_now' ) ) . '" />' . blueworx_ds_button(
				array(
					'label'   => __( 'Send a report now', 'blueworx-labs-wordpress' ),
					'icon'    => 'mail',
					'variant' => 'ghost',
					'size'    => 'sm',
					'type'    => 'submit',
					// Posts straight to its own handler, past the settings form around
					// it. A link would send mail on a prefetch or a stray click.
					'attrs'   => array(
						'name'           => 'blueworx_speed_report_now',
						'value'          => '1',
						'formaction'     => admin_url( 'admin.php?page=blueworx-labs-wordpress&section=performance' ),
						'formmethod'     => 'post',
						// The report uses what is saved, so another field in the form that
						// is not valid yet must not stop it.
						'formnovalidate' => true,
						'data-testid'    => 'bw-speed-report-now',
					),
				)
			)
		);
	}

	if ( 'weekly_cleanup' === $key ) {
		$last = get_option( 'blueworx_cleanup_last', array() );

		$text = is_array( $last ) && ! empty( $last['time'] ) && ! empty( $last['error'] )
			? sprintf(
				/* translators: 1: date of the last clear-out, 2: old background jobs removed before it stopped. */
				__( 'The last clear-out, on %1$s, stopped on a database error after removing %2$d old background jobs. It will try again next week.', 'blueworx-labs-wordpress' ),
				blueworx_local_date( (int) $last['time'] ),
				(int) $last['jobs']
			)
			: ( is_array( $last ) && ! empty( $last['time'] )
			? sprintf(
				/* translators: 1: date of the last clear-out, 2: old background jobs removed, 3: expired visitor sessions removed. */
				__( 'The last clear-out was on %1$s. It removed %2$d old background jobs and %3$d expired visitor sessions.', 'blueworx-labs-wordpress' ),
				blueworx_local_date( (int) $last['time'] ),
				(int) $last['jobs'],
				(int) $last['sessions']
			)
			: __( 'No clear-out has run yet. The first runs within the hour of switching this on, then once a week.', 'blueworx-labs-wordpress' ) );

		return blueworx_detail_stack(
			blueworx_ds_field(
				array(
					'label'   => __( 'What it removes', 'blueworx-labs-wordpress' ),
					'control' => '<p class="bw-field__help" data-testid="bw-cleanup-last">' . esc_html( $text ) . '</p>',
					'help'    => __( 'Background jobs that finished more than 14 days ago, failed ones after 30 days, expired temporary data, and expired visitor sessions. Jobs still waiting are never touched.', 'blueworx-labs-wordpress' ),
				)
			)
		);
	}

	return '';
}
