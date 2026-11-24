<?php
/**
 * Bot control: turns away scanner bots and heavy crawlers cheaply.
 *
 * Three separate functions, each with its own switch (see features.php):
 *
 * - `probe_block`        Answers requests for well-known probe paths (.env,
 *                        backup.zip, phpinfo.php and friends) with a plain 404
 *                        or 403 before the theme, the query or any template
 *                        loads.
 * - `missing_page_limit` Pauses a visitor who keeps asking for pages that do
 *                        not exist, answering 429 from then on, again before
 *                        WordPress builds a page.
 * - `crawler_rules`      Adds rules to robots.txt that keep well-behaved
 *                        crawlers off cart, checkout and filtered shop pages.
 *
 * Every missing page WordPress answers is a full page build. Scanners ask for
 * hundreds of them per site, which is what these functions are here to stop.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalises a request path into its segments.
 *
 * Decodes percent-escapes (twice, because scanners double-encode on purpose),
 * turns backslashes into slashes, lowercases, and drops empty and "." segments.
 * A ".." segment is kept, because it is itself a sign of a probe.
 *
 * @param string $path Request path, without the query string.
 * @return string[] Segments, or an empty array for an empty path.
 */
function blueworx_probe_segments( $path ) {
	$path = rawurldecode( rawurldecode( (string) $path ) );
	$path = strtolower( str_replace( '\\', '/', $path ) );

	$segments = array();

	foreach ( explode( '/', $path ) as $segment ) {
		$segment = trim( $segment );

		if ( '' === $segment || '.' === $segment ) {
			continue;
		}

		$segments[] = $segment;
	}

	return $segments;
}

/**
 * Whether a request path is one of the well-known probe paths.
 *
 * Deliberately a short, specific list rather than a broad pattern: a rule that
 * turns away a real page is far worse than a probe that gets through to the
 * normal 404. Anything on the list is a file or folder no WordPress site serves
 * through WordPress, so a request for it can only be a scan.
 *
 * @param string $path Request path, without the query string.
 * @return bool True when the path is a probe.
 */
function blueworx_probe_path_matches( $path ) {
	if ( false !== strpos( (string) $path, "\0" ) || false !== strpos( (string) $path, '%00' ) ) {
		return true;
	}

	$segments = blueworx_probe_segments( $path );

	if ( empty( $segments ) ) {
		return false;
	}

	$base = end( $segments );

	// Dot-files and dot-folders: .env, .git, .svn, .aws, .htaccess, .DS_Store,
	// and ".." climbing out of the site. The one legitimate dot-folder on the web
	// is .well-known, which has to stay reachable.
	foreach ( $segments as $segment ) {
		if ( '.' === $segment[0] && '.well-known' !== $segment ) {
			return true;
		}
	}

	// Folders that are only ever somebody else's admin tool or a leftover.
	$folders = array( 'phpmyadmin', 'myadmin', 'cgi-bin', 'phpunit', 'node_modules' );

	if ( array_intersect( $folders, $segments ) ) {
		return true;
	}

	// Config, secret and tool files, by exact name.
	$files = array(
		'config.php',
		'config.inc.php',
		'config.json',
		'config.yml',
		'config.yaml',
		'configuration.php',
		'settings.php',
		'settings.json',
		'secrets.json',
		'secrets.yml',
		'credentials',
		'credentials.json',
		'appsettings.json',
		'database.yml',
		'sftp-config.json',
		'web.config',
		'docker-compose.yml',
		'docker-compose.yaml',
		'dockerfile',
		'composer.json',
		'composer.lock',
		'package.json',
		'package-lock.json',
		'yarn.lock',
		'id_rsa',
		'id_dsa',
		'id_ecdsa',
		'id_ed25519',
		'phpinfo.php',
		'info.php',
		'php.php',
		'pi.php',
		'test.php',
		'adminer.php',
		'adminer',
		'phpmyadmin.php',
		'shell.php',
		'cmd.php',
		'c99.php',
		'r57.php',
		'wso.php',
		'eval-stdin.php',
		'server-status',
		'server-info',
		'debug.log',
		'error_log',
		'error.log',
		'access.log',
	);

	if ( in_array( $base, $files, true ) ) {
		return true;
	}

	// wp-config.php and every copy of it somebody left behind (wp-config.php.bak,
	// wp-config.old, wp-config.php~).
	if ( 0 === strpos( $base, 'wp-config' ) ) {
		return true;
	}

	// Database dumps and editor or backup leftovers, whatever they are called.
	if ( preg_match( '/(\.sql(\.gz|\.zip|\.bz2)?|\.bak|\.old|\.orig|\.save|\.swp|\.swo|~)$/', $base ) ) {
		return true;
	}

	// Site archives, by the names bots guess: backup.zip, www.tar.gz, site.7z.
	if ( preg_match( '/^([a-z0-9_-]*backup[a-z0-9_-]*|bak|db|database|dump|site|www|public_html|html|web|wordpress|wp|archive|old|website|files|data)\.(zip|tar|tar\.gz|tgz|gz|rar|7z|bz2)$/', $base ) ) { // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- A lowercase file name guessed by bots, not the product name.
		return true;
	}

	// A script inside the uploads folder is a planted web shell, never a page.
	if ( in_array( 'uploads', $segments, true ) && in_array( 'wp-content', $segments, true ) && preg_match( '/\.(php\d?|phtml|phar)$/', $base ) ) {
		return true;
	}

	return false;
}

/**
 * Whether a request path is for a static file (image, script, font and so on).
 *
 * A page with a few broken images makes a visitor ask for a few missing files.
 * That is a page problem, not a scanner, so those never count towards the
 * missing-page limit.
 *
 * @param string $path Request path, without the query string.
 * @return bool True for a static asset.
 */
function blueworx_path_is_static_asset( $path ) {
	$segments = blueworx_probe_segments( $path );

	if ( empty( $segments ) ) {
		return false;
	}

	return (bool) preg_match( '/\.(jpe?g|png|gif|webp|avif|svg|ico|bmp|css|js|map|woff2?|ttf|otf|eot|mp[34]|webm|mov|pdf)$/', end( $segments ) );
}

/**
 * Gets the status code a turned-away probe is answered with.
 *
 * @return int 404 or 403.
 */
function blueworx_probe_status() {
	return '403' === (string) get_option( 'blueworx_probe_status', '404' ) ? 403 : 404;
}

/**
 * Gets the path of the current request, without the query string.
 *
 * @return string Request path, or an empty string when there is none.
 */
function blueworx_bot_request_path() {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return '';
	}

	// Only ever compared against fixed patterns and never output, so the raw
	// value is read and nothing is echoed back.
	$uri  = (string) wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared against fixed patterns only; never output.
	$path = wp_parse_url( $uri, PHP_URL_PATH );

	return is_string( $path ) ? $path : '';
}

/**
 * Whether the current request is one that must never be turned away or paused.
 *
 * Sign-in, the admin area, background jobs and the REST API keep working for
 * everyone, so a paused visitor can still sign in.
 *
 * @param string $path Request path.
 * @return bool True for a request the limit leaves alone.
 */
function blueworx_bot_request_is_exempt( $path ) {
	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || 'cli' === PHP_SAPI || ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) ) {
		return true;
	}

	$segments = blueworx_probe_segments( $path );
	$first    = isset( $segments[0] ) ? $segments[0] : '';
	$base     = empty( $segments ) ? '' : end( $segments );

	if ( in_array( $first, array( 'wp-admin', 'wp-json' ), true ) || in_array( $base, array( 'wp-login.php', 'wp-cron.php', 'admin-ajax.php' ), true ) ) {
		return true;
	}

	if ( function_exists( 'blueworx_login_slug' ) && in_array( blueworx_login_slug(), $segments, true ) ) {
		return true;
	}

	return false;
}

/**
 * Sends a short plain-text answer and stops.
 *
 * Nothing else loads: no theme, no query, no template. Not cached, so changing
 * a setting takes effect straight away.
 *
 * @param int    $status  HTTP status.
 * @param string $message Plain-text body.
 * @param array  $headers Extra headers, name => value.
 * @return void
 */
function blueworx_bot_respond( $status, $message, $headers = array() ) {
	status_header( $status );
	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );

	foreach ( $headers as $name => $value ) {
		header( $name . ': ' . $value );
	}

	echo esc_html( $message );
	exit;
}

/**
 * Works out who a visitor is, for counting their missing pages.
 *
 * REMOTE_ADDR is the answer unless the site sits behind something that forwards
 * traffic. Behind Cloudflare every request carries the visitor's address in
 * CF-Connecting-IP. Behind a private-network load balancer REMOTE_ADDR is the
 * balancer, so the first public address in X-Forwarded-For is used. Anywhere
 * else the forwarding headers are ignored: a visitor could write whatever they
 * like in them. A site with another arrangement sets the address itself with
 * the `blueworx_visitor_ip` filter.
 *
 * @return string Visitor address, or an empty string when it cannot be told.
 */
function blueworx_visitor_ip() {
	$server = $_SERVER; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Each value is validated as an IP address below before it is used.
	$remote = isset( $server['REMOTE_ADDR'] ) ? (string) $server['REMOTE_ADDR'] : '';
	$public = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
	$ip     = '';

	if ( ! empty( $server['HTTP_CF_CONNECTING_IP'] ) && false !== filter_var( $server['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP ) ) {
		$ip = (string) $server['HTTP_CF_CONNECTING_IP'];
	} elseif ( false === filter_var( $remote, FILTER_VALIDATE_IP, $public ) && ! empty( $server['HTTP_X_FORWARDED_FOR'] ) ) {
		foreach ( explode( ',', (string) $server['HTTP_X_FORWARDED_FOR'] ) as $candidate ) {
			$candidate = trim( $candidate );

			if ( false !== filter_var( $candidate, FILTER_VALIDATE_IP, $public ) ) {
				$ip = $candidate;
				break;
			}
		}
	} elseif ( false !== filter_var( $remote, FILTER_VALIDATE_IP, $public ) ) {
		$ip = $remote;
	}

	$ip = apply_filters( 'blueworx_visitor_ip', $ip );

	return ( is_string( $ip ) && false !== filter_var( $ip, FILTER_VALIDATE_IP ) ) ? $ip : '';
}

/**
 * How many missing pages one visitor may ask for before being paused.
 *
 * @return int Count, between 5 and 1000.
 */
function blueworx_missing_page_allowance() {
	return max( 5, min( 1000, absint( get_option( 'blueworx_missing_page_limit', 30 ) ) ) );
}

/**
 * How long a paused visitor is turned away for, in minutes.
 *
 * @return int Minutes, between 1 and 1440.
 */
function blueworx_missing_page_pause_minutes() {
	return max( 1, min( 1440, absint( get_option( 'blueworx_missing_page_pause', 15 ) ) ) );
}

/**
 * The options key for a visitor's counter or pause marker.
 *
 * @param string $kind 'count' or 'pause'.
 * @param string $ip   Visitor address.
 * @return string Transient name.
 */
function blueworx_missing_page_key( $kind, $ip ) {
	return 'blueworx_miss_' . $kind . '_' . md5( $ip );
}

/**
 * Counts one missing page against a visitor, pausing them at the limit.
 *
 * The count lives for ten minutes after the visitor's last miss, so a slow
 * scan is caught too and a visitor who stops is forgotten quickly.
 *
 * @param string $ip Visitor address.
 * @return void
 */
function blueworx_missing_page_record( $ip ) {
	if ( '' === $ip ) {
		return;
	}

	$key   = blueworx_missing_page_key( 'count', $ip );
	$count = (int) get_transient( $key ) + 1;

	if ( $count >= blueworx_missing_page_allowance() ) {
		$seconds = blueworx_missing_page_pause_minutes() * MINUTE_IN_SECONDS;

		set_transient( blueworx_missing_page_key( 'pause', $ip ), time() + $seconds, $seconds );
		delete_transient( $key );

		return;
	}

	set_transient( $key, $count, 10 * MINUTE_IN_SECONDS );
}

/**
 * How many seconds a visitor is paused for.
 *
 * @param string $ip Visitor address.
 * @return int Seconds left, 0 when not paused.
 */
function blueworx_missing_page_pause_left( $ip ) {
	if ( '' === $ip ) {
		return 0;
	}

	$until = (int) get_transient( blueworx_missing_page_key( 'pause', $ip ) );

	return max( 0, $until - time() );
}

/**
 * Answers probe paths and paused visitors before WordPress does any real work.
 *
 * @return void
 */
function blueworx_bot_control_early() {
	$path = blueworx_bot_request_path();

	if ( '' === $path ) {
		return;
	}

	$probe_on    = blueworx_feature_enabled( 'probe_block' );
	$throttle_on = blueworx_feature_enabled( 'missing_page_limit' );

	if ( ! $probe_on && ! $throttle_on ) {
		return;
	}

	if ( blueworx_bot_request_is_exempt( $path ) ) {
		return;
	}

	$ip = $throttle_on ? blueworx_visitor_ip() : '';

	if ( $throttle_on ) {
		$left = blueworx_missing_page_pause_left( $ip );

		if ( $left > 0 ) {
			blueworx_bot_respond( 429, 'Too many requests for pages that do not exist. Please try again later.', array( 'Retry-After' => (string) $left ) );
		}
	}

	if ( $probe_on && apply_filters( 'blueworx_is_probe_path', blueworx_probe_path_matches( $path ), $path ) ) {
		// A probe is a missing page too, so a scanner is paused after a handful
		// of them rather than being answered cheaply for ever.
		if ( $throttle_on ) {
			blueworx_missing_page_record( $ip );
		}

		blueworx_bot_respond( blueworx_probe_status(), 403 === blueworx_probe_status() ? 'Forbidden' : 'Not found' );
	}
}

/**
 * Counts a missing page that WordPress had to build in full.
 *
 * @return void
 */
function blueworx_missing_page_count_404() {
	if ( ! is_404() || is_user_logged_in() ) {
		return;
	}

	$path = blueworx_bot_request_path();

	if ( '' === $path || blueworx_path_is_static_asset( $path ) || blueworx_bot_request_is_exempt( $path ) ) {
		return;
	}

	blueworx_missing_page_record( blueworx_visitor_ip() );
}

/**
 * Gets the robots.txt rules that keep crawlers off cart, checkout and filters.
 *
 * Wildcards (*) are understood by Google, Bing and the other major crawlers.
 *
 * @return string[] Disallow lines.
 */
function blueworx_crawler_rules() {
	$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$base = untrailingslashit( is_string( $home ) ? $home : '' );
	$out  = array();

	foreach ( array( '/cart/', '/checkout/', '/my-account/' ) as $path ) {
		$out[] = 'Disallow: ' . $base . $path;
	}

	foreach ( array( 'orderby=', 'filter_', 'min_price=', 'max_price=', 'rating_filter=', 'add-to-cart=' ) as $param ) {
		$out[] = 'Disallow: ' . $base . '/*?*' . $param;
	}

	$rules = apply_filters( 'blueworx_crawler_rules', $out );

	return is_array( $rules ) ? array_values( array_filter( array_map( 'strval', $rules ) ) ) : $out;
}

/**
 * Adds the crawler rules to robots.txt.
 *
 * The rules go straight under the first "User-agent: *" line, wherever the file
 * came from (WordPress, SureRank or the robots.txt editor). A second
 * "User-agent: *" group would not merge: crawlers read the first and ignore the
 * rest. A rule the file already has is not added twice.
 *
 * @param string $output Generated robots.txt content.
 * @return string robots.txt content with the rules in it.
 */
function blueworx_crawler_rules_filter( $output ) {
	$lines    = explode( "\n", str_replace( "\r\n", "\n", (string) $output ) );
	$existing = array();

	foreach ( $lines as $line ) {
		$existing[ strtolower( preg_replace( '/\s+/', '', $line ) ) ] = true;
	}

	$add = array();

	foreach ( blueworx_crawler_rules() as $rule ) {
		if ( ! isset( $existing[ strtolower( preg_replace( '/\s+/', '', $rule ) ) ] ) ) {
			$add[] = $rule;
		}
	}

	if ( empty( $add ) ) {
		return (string) $output;
	}

	foreach ( $lines as $index => $line ) {
		if ( preg_match( '/^user-agent:\s*\*\s*$/i', trim( $line ) ) ) {
			array_splice( $lines, $index + 1, 0, $add );

			return implode( "\n", $lines );
		}
	}

	// No catch-all group yet: add one at the end.
	return rtrim( (string) $output ) . "\n\nUser-agent: *\n" . implode( "\n", $add ) . "\n";
}

if ( blueworx_feature_enabled( 'probe_block' ) || blueworx_feature_enabled( 'missing_page_limit' ) ) {
	// Priority 0: as early as a plugin can run, before the theme, the query or
	// any template is touched.
	add_action( 'plugins_loaded', 'blueworx_bot_control_early', 0 );
}

if ( blueworx_feature_enabled( 'missing_page_limit' ) ) {
	add_action( 'template_redirect', 'blueworx_missing_page_count_404', 0 );
}

if ( blueworx_feature_enabled( 'crawler_rules' ) ) {
	// After the robots.txt editor (priority 20), so its content is what gets the
	// rules added.
	add_filter( 'robots_txt', 'blueworx_crawler_rules_filter', 30 );
}
