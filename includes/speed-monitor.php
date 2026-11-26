<?php
/**
 * Slow-page log, and a sample of how the object cache is doing.
 *
 * The slow-page log (`slow_log`) keeps the pages that took longer than a set
 * time to build, so nobody has to install a timing plugin to find them. The
 * speed report (`speed_report`) reads the same notes, plus a small sample of
 * object cache hits and misses taken on pages that reached PHP at all, which
 * is to say pages the page cache did not answer.
 *
 * Both only look at the end of a request, when the work is already done, and
 * write to the database only for a slow page or one request in
 * BLUEWORX_SPEED_SAMPLE_EVERY.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Most entries the slow-page log keeps. The oldest go first.
 */
if ( ! defined( 'BLUEWORX_SLOW_LOG_MAX' ) ) {
	define( 'BLUEWORX_SLOW_LOG_MAX', 200 );
}

/**
 * One request in this many is counted for the object cache sample.
 */
if ( ! defined( 'BLUEWORX_SPEED_SAMPLE_EVERY' ) ) {
	define( 'BLUEWORX_SPEED_SAMPLE_EVERY', 20 );
}

/**
 * Gets how long a page may take, in seconds, before it is written to the log.
 *
 * @return float Seconds, 0.2 to 60.
 */
function blueworx_slow_threshold() {
	$seconds = (float) get_option( 'blueworx_slow_threshold', 2 );

	if ( $seconds <= 0 ) {
		$seconds = 2;
	}

	return max( 0.2, min( 60, $seconds ) );
}

/**
 * Says what kind of request this is.
 *
 * @return string One of front, admin, ajax, rest, cron or cli.
 */
function blueworx_request_kind() {
	if ( 'cli' === PHP_SAPI || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		$kind = 'cli';
	} elseif ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
		$kind = 'cron';
	} elseif ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
		$kind = 'ajax';
	} elseif ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		$kind = 'rest';
	} else {
		$kind = is_admin() ? 'admin' : 'front';
	}

	return (string) apply_filters( 'blueworx_request_kind', $kind );
}

/**
 * Gets the status code the request is being answered with.
 *
 * PHP reports nothing when no code has been set, which means 200.
 *
 * @return int HTTP status.
 */
function blueworx_response_status() {
	$code = http_response_code();

	return $code ? (int) $code : 200;
}

/**
 * Gets the object cache's own hit and miss counts for this request.
 *
 * WordPress's cache class and the common Redis and Memcached drop-ins all keep
 * these two counters. A drop-in that does not gives nothing to read, and that
 * is reported as unknown rather than as zero.
 *
 * @return array|null Array with 'hits' and 'misses', or null when unreadable.
 */
function blueworx_object_cache_counts() {
	global $wp_object_cache;

	if ( ! is_object( $wp_object_cache ) || ! isset( $wp_object_cache->cache_hits, $wp_object_cache->cache_misses ) ) {
		return null;
	}

	return array(
		'hits'   => (int) $wp_object_cache->cache_hits,
		'misses' => (int) $wp_object_cache->cache_misses,
	);
}

/**
 * Describes this site's object cache in a few plain words.
 *
 * @return string 'persistent', 'drop-in' or 'none'.
 */
function blueworx_object_cache_kind() {
	if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
		return 'persistent';
	}

	return defined( 'WP_CONTENT_DIR' ) && file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ? 'drop-in' : 'none';
}

/**
 * Whether a path segment could be something a visitor typed or supplied.
 *
 * @param string $segment One path segment, already decoded.
 * @return bool True for a segment that must not be kept.
 */
function blueworx_segment_is_personal( $segment ) {
	return '' === $segment || false !== strpos( $segment, '@' ) || strlen( $segment ) > 60 || preg_match( '/\d{6,}/', $segment );
}

/**
 * Turns the request's address into a short note that holds nothing personal.
 *
 * Only the route is kept. Anything a visitor could have supplied is left out:
 * the values of query arguments (their names stay), a search (pretty search
 * addresses carry what was typed), a page that does not exist (the address is
 * whatever somebody asked for), and any path piece that looks like an email
 * address or a long number. Of an API route only the first three pieces stay.
 * The two arguments that say what an AJAX call is for (action, wc-ajax) keep
 * their value, since without it every call looks the same.
 *
 * @return string Route, with argument names after a question mark.
 */
function blueworx_slow_request_label() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reduced to a route and argument names below; never output unescaped.
	$path = wp_parse_url( $uri, PHP_URL_PATH );
	$path = is_string( $path ) ? rawurldecode( $path ) : '/';

	// What WordPress has worked out is better than reading the address.
	if ( did_action( 'wp' ) ) {
		if ( function_exists( 'is_search' ) && is_search() ) {
			return '(search)';
		}

		if ( function_exists( 'is_404' ) && is_404() ) {
			return '(page not found)';
		}
	}

	$segments = array();

	foreach ( explode( '/', $path ) as $segment ) {
		if ( '' === $segment ) {
			continue;
		}

		$segments[] = blueworx_segment_is_personal( $segment ) ? '*' : substr( preg_replace( '/[^\x21-\x7E]/', '', $segment ), 0, 60 );
	}

	if ( in_array( blueworx_request_kind(), array( 'rest', 'ajax' ), true ) || ( isset( $segments[0] ) && 'wp-json' === $segments[0] ) ) {
		$segments = array_slice( $segments, 0, 3 );
	}

	// A pretty search address (/search/whatever/) when WordPress has not said so.
	if ( isset( $segments[0] ) && 'search' === $segments[0] ) {
		return '(search)';
	}

	$label = '/' . implode( '/', $segments );
	$label = '/' === $label ? '/' : $label . ( '/' === substr( $path, -1 ) ? '/' : '' );
	$label = substr( $label, 0, 200 );

	$query = wp_parse_url( $uri, PHP_URL_QUERY );
	$parts = array();

	if ( is_string( $query ) && '' !== $query ) {
		parse_str( $query, $args );

		foreach ( array_slice( array_keys( $args ), 0, 8 ) as $name ) {
			$name = substr( sanitize_key( (string) $name ), 0, 40 );

			if ( '' === $name ) {
				continue;
			}

			if ( in_array( $name, array( 'action', 'wc-ajax' ), true ) && is_string( $args[ $name ] ) ) {
				$name .= '=' . substr( sanitize_key( $args[ $name ] ), 0, 60 );
			}

			$parts[] = $name;
		}
	}

	// A search made the old way (?s=...) keeps no trace of what was typed either.
	if ( isset( $args ) && is_array( $args ) && isset( $args['s'] ) ) {
		return '(search)';
	}

	return $label . ( $parts ? '?' . implode( '&', $parts ) : '' );
}
/**
 * Gets the saved slow-page log, newest first.
 *
 * @return array[] Entries with t, ms, path, kind, status, db, mem, in and hit.
 */
function blueworx_slow_log_entries() {
	$log = get_option( 'blueworx_slow_log', array() );

	return is_array( $log ) ? $log : array();
}

/**
 * Builds one log entry for the request that has just finished.
 *
 * @param float $seconds How long the request took.
 * @return array Log entry.
 */
function blueworx_slow_log_entry( $seconds ) {
	$counts = blueworx_object_cache_counts();
	$total  = $counts ? $counts['hits'] + $counts['misses'] : 0;

	return array(
		't'      => time(),
		'ms'     => (int) round( $seconds * 1000 ),
		'path'   => blueworx_slow_request_label(),
		'kind'   => blueworx_request_kind(),
		'status' => blueworx_response_status(),
		'db'     => function_exists( 'get_num_queries' ) ? (int) get_num_queries() : 0,
		'mem'    => (int) round( memory_get_peak_usage() / 1048576 ),
		'in'     => function_exists( 'is_user_logged_in' ) && is_user_logged_in(),
		'hit'    => $total > 0 ? (int) round( 100 * $counts['hits'] / $total ) : null,
	);
}

/**
 * Writes a slow request to the log, newest first, within the size limit.
 *
 * @param array $entry Log entry.
 * @return void
 */
function blueworx_slow_log_write( $entry ) {
	// Best effort: two slow requests that finish within milliseconds of each other
	// can overwrite one another's entry. The log is a sample of where time goes,
	// not a count, so that is accepted rather than paying for a locked write.
	$log = blueworx_slow_log_entries();

	array_unshift( $log, $entry );

	// Not autoloaded: it is read on the settings screen and by the weekly
	// report, never on an ordinary page.
	update_option( 'blueworx_slow_log', array_slice( $log, 0, BLUEWORX_SLOW_LOG_MAX ), false );
}

/**
 * Gets the running object cache sample.
 *
 * @return array Sample with since, requests, hits, misses, ms and queries.
 */
function blueworx_speed_sample() {
	$sample = get_option( 'blueworx_speed_sample', array() );

	return wp_parse_args(
		is_array( $sample ) ? $sample : array(),
		array(
			'since'    => 0,
			'requests' => 0,
			'hits'     => 0,
			'misses'   => 0,
			'unknown'  => 0,
			'kept'     => 0,
			'lost'     => 0,
			'ms'       => 0,
			'queries'  => 0,
		)
	);
}

/**
 * Adds one uncached page to the object cache sample.
 *
 * @param float $seconds How long the page took.
 * @return void
 */
function blueworx_speed_sample_add( $seconds ) {
	$sample = blueworx_speed_sample();
	$counts = blueworx_object_cache_counts();

	if ( ! $sample['since'] ) {
		$sample['since'] = time();
	}

	++$sample['requests'];
	$sample['ms']      += (int) round( $seconds * 1000 );
	$sample['queries'] += function_exists( 'get_num_queries' ) ? (int) get_num_queries() : 0;

	// Hits are not proof the cache is shared between pages: some drop-ins count
	// what they remember within one request as well. So a note is left in the
	// cache for the next sampled page to find. A cache that really is shared
	// finds it nearly every time; one that is not, never does.
	if ( 'persistent' === blueworx_object_cache_kind() ) {
		if ( false !== wp_cache_get( 'sample_note', 'blueworx_speed' ) ) {
			++$sample['kept'];
		} else {
			++$sample['lost'];
			wp_cache_set( 'sample_note', time(), 'blueworx_speed', 6 * HOUR_IN_SECONDS );
		}
	}

	if ( $counts ) {
		$sample['hits']   += $counts['hits'];
		$sample['misses'] += $counts['misses'];
	} else {
		++$sample['unknown'];
	}

	update_option( 'blueworx_speed_sample', $sample, false );
}

/**
 * Whether this request belongs in the object cache sample.
 *
 * Ordinary page views that WordPress answered with a page. The sample is about
 * uncached pages, so anything the page cache answered never gets here, and
 * admin screens, AJAX, REST and cron are left out because they say nothing
 * about what a visitor waits for.
 *
 * @return bool True for a page view to count.
 */
function blueworx_speed_sample_wanted() {
	if ( 'front' !== blueworx_request_kind() || ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) ) {
		return false;
	}

	if ( 200 !== blueworx_response_status() ) {
		return false;
	}

	return 1 === wp_rand( 1, max( 1, (int) apply_filters( 'blueworx_speed_sample_every', BLUEWORX_SPEED_SAMPLE_EVERY ) ) );
}

/**
 * Looks at the request that has just finished.
 *
 * @return void
 */
function blueworx_speed_monitor_shutdown() {
	if ( empty( $_SERVER['REQUEST_TIME_FLOAT'] ) ) {
		return;
	}

	$kind = blueworx_request_kind();

	if ( 'cli' === $kind || 'cron' === $kind ) {
		return;
	}

	$seconds = microtime( true ) - (float) $_SERVER['REQUEST_TIME_FLOAT'];

	// The threshold is read only for a request that has already taken at least the
	// shortest it can be set to. A site that never saved one has no stored value,
	// and looking up a missing option is a database query on a site without a
	// persistent cache. A quick page never pays for it.
	if ( $seconds >= 0.2 && blueworx_feature_enabled( 'slow_log' ) && $seconds >= blueworx_slow_threshold() ) {
		blueworx_slow_log_write( blueworx_slow_log_entry( $seconds ) );
	}

	if ( blueworx_feature_enabled( 'speed_report' ) && blueworx_speed_sample_wanted() ) {
		blueworx_speed_sample_add( $seconds );
	}
}

/**
 * Summarises the log by page: how often, how slow, for the last so many days.
 *
 * @param int $days  How many days back to look.
 * @param int $limit Most pages to return.
 * @return array[] Pages, slowest worst-case first, with path, count, worst and average in ms.
 */
function blueworx_slow_log_by_page( $days = 7, $limit = 10 ) {
	$since = time() - ( $days * DAY_IN_SECONDS );
	$pages = array();

	foreach ( blueworx_slow_log_entries() as $entry ) {
		if ( ! is_array( $entry ) || empty( $entry['path'] ) || (int) $entry['t'] < $since ) {
			continue;
		}

		$path = (string) $entry['path'];

		if ( ! isset( $pages[ $path ] ) ) {
			$pages[ $path ] = array(
				'path'  => $path,
				'count' => 0,
				'worst' => 0,
				'sum'   => 0,
				'kind'  => isset( $entry['kind'] ) ? (string) $entry['kind'] : '',
			);
		}

		++$pages[ $path ]['count'];
		$pages[ $path ]['sum']  += (int) $entry['ms'];
		$pages[ $path ]['worst'] = max( $pages[ $path ]['worst'], (int) $entry['ms'] );
	}

	foreach ( $pages as &$page ) {
		$page['average'] = (int) round( $page['sum'] / $page['count'] );
	}
	unset( $page );

	usort(
		$pages,
		static function ( $a, $b ) {
			return $b['worst'] <=> $a['worst'];
		}
	);

	return array_slice( $pages, 0, $limit );
}

/**
 * Hooks the monitor when either of its two functions is on.
 *
 * @return void
 */
function blueworx_speed_monitor_hooks() {
	if ( blueworx_feature_enabled( 'slow_log' ) || blueworx_feature_enabled( 'speed_report' ) ) {
		// As late as possible, so the time includes every other plugin's own
		// shutdown work and the counts are the request's final ones.
		add_action( 'shutdown', 'blueworx_speed_monitor_shutdown', PHP_INT_MAX );
	}
}
blueworx_speed_monitor_hooks();
