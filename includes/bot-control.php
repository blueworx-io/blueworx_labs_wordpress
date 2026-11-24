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
 * Some names are probes on one site and a real page on another: a folder called
 * phpunit, or a page called adminer. Those are "ambiguous", and are only turned
 * away once WordPress has looked and found no page there. Everything else
 * (hidden files, config and backup files, scripts in uploads) is answered at
 * once.
 *
 * @param string $path              Request path, without the query string.
 * @param bool   $include_ambiguous Whether names that could be a real page count.
 * @return bool True when the path is a probe.
 */
function blueworx_probe_path_matches( $path, $include_ambiguous = true ) {
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

	if ( $include_ambiguous && array_intersect( $folders, $segments ) ) {
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

	// A name with no extension (adminer, server-status) could be a page.
	if ( in_array( $base, $files, true ) && ( $include_ambiguous || false !== strpos( $base, '.' ) ) ) {
		return true;
	}

	// wp-config.php and the copies somebody left behind (wp-config.php.bak,
	// wp-config.old, wp-config-sample.php, wp-config.php~). Matched by shape and
	// not by prefix, so a real page called wp-config-guide is never caught.
	if ( preg_match( '/^wp-config([-_.](bak|backup|old|orig|save|copy|sample|dev|local|prod))?\.(php|inc|txt|bak|old|orig|save|swp|zip|tar|gz|tgz|7z|rar|\d+)(\.(bak|old|orig|save|swp|txt|zip|gz|\d+))?~?$/', $base ) ) {
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

	return (bool) preg_match( '/\.(jpe?g|png|gif|webp|avif|svg|ico|bmp|css|m?js|cjs|map|json|webmanifest|wasm|woff2?|ttf|otf|eot|mp[34]|m4a|ogg|wav|webm|mov|pdf|tiff?|heic|jfif)$/', end( $segments ) );
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
 * Gets the query string of the current request.
 *
 * @return string Query string, or an empty string when there is none.
 */
function blueworx_bot_request_query() {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return '';
	}

	$query = wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared against fixed patterns only; never output.

	return is_string( $query ) ? $query : '';
}

/**
 * Whether the current request is one that must never be turned away or paused.
 *
 * Sign-in, the admin area, background jobs and the REST API keep working for
 * everyone, so a paused visitor can still sign in. Paths are read relative to
 * the site, so a site in a folder or a subsite of a network is handled, and both
 * REST address formats (/wp-json/ and ?rest_route=) count.
 *
 * @param string $path  Request path.
 * @param string $query Request query string.
 * @return bool True for a request the limit leaves alone.
 */
function blueworx_bot_request_is_exempt( $path, $query = '' ) {
	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || 'cli' === PHP_SAPI || ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) ) {
		return true;
	}

	return blueworx_bot_path_is_exempt( $path, $query );
}

/**
 * Whether a request path is one of the exempt routes.
 *
 * @param string $path  Request path.
 * @param string $query Request query string.
 * @return bool True for an exempt route.
 */
function blueworx_bot_path_is_exempt( $path, $query = '' ) {
	if ( '' !== $query ) {
		parse_str( $query, $args );

		if ( isset( $args['rest_route'] ) && is_string( $args['rest_route'] ) && '' !== $args['rest_route'] ) {
			return true;
		}
	}

	$segments = blueworx_probe_segments( $path );
	$base     = empty( $segments ) ? '' : end( $segments );

	// Read the path from the site's own root: drop the folder the site lives in,
	// and an index.php in front of the route.
	$home      = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$home_segs = blueworx_probe_segments( is_string( $home ) ? $home : '' );

	if ( $home_segs && array_slice( $segments, 0, count( $home_segs ) ) === $home_segs ) {
		$segments = array_slice( $segments, count( $home_segs ) );
	}

	if ( isset( $segments[0] ) && 'index.php' === $segments[0] ) {
		array_shift( $segments );
	}

	$first = isset( $segments[0] ) ? $segments[0] : '';

	if ( in_array( $first, array( 'wp-admin', 'wp-json' ), true ) || in_array( $base, array( 'wp-login.php', 'wp-cron.php', 'admin-ajax.php' ), true ) ) {
		return true;
	}

	if ( function_exists( 'blueworx_is_custom_login_request_path' ) && blueworx_feature_enabled( 'login' ) && blueworx_is_custom_login_request_path( $path ) ) {
		return true;
	}

	// Single sign-on: the address that starts a sign-in, and the provider's
	// return with a state this site issued. A paused visitor must still be able
	// to finish signing in.
	if ( function_exists( 'blueworx_sso_enabled' ) && blueworx_sso_enabled() ) {
		if ( '' !== $query ) {
			parse_str( $query, $sso_args );

			if ( isset( $sso_args['blueworx_sso'] ) ) {
				return true;
			}
		}

		if ( function_exists( 'blueworx_sso_is_own_callback' ) && blueworx_sso_is_own_callback() ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether the visitor holds a valid signed-in session.
 *
 * Checked from the cookie itself, because the early checks run before
 * WordPress has worked out who is signed in. Anyone signed in is never paused
 * or counted: on a shared address they are not the one making the requests.
 *
 * @return bool True for a signed-in visitor.
 */
function blueworx_bot_visitor_signed_in() {
	static $signed_in = null;

	if ( null === $signed_in ) {
		$signed_in = defined( 'LOGGED_IN_COOKIE' ) && ! empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) && function_exists( 'wp_validate_auth_cookie' ) && (bool) wp_validate_auth_cookie( '', 'logged_in' );
	}

	return $signed_in;
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
 * Cloudflare's published address ranges.
 *
 * Requests from these carry the real visitor's address in CF-Connecting-IP.
 *
 * @return string[] Networks in CIDR form.
 */
function blueworx_cloudflare_ranges() {
	return array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);
}

/**
 * Every network whose requests may carry a forwarded visitor address.
 *
 * Cloudflare, and the machine itself. Nothing else is trusted without being
 * asked: a proxy that passes a visitor's own X-Forwarded-For through unchanged
 * would let them choose their address. A host with a proxy in front adds its
 * addresses with the blueworx_trusted_proxies filter.
 *
 * @return string[] Networks in CIDR form.
 */
function blueworx_trusted_proxy_ranges() {
	// Cloudflare, plus the machine itself: a proxy on the same machine is the
	// host's own set-up.
	$ranges = array_merge( blueworx_cloudflare_ranges(), array( '127.0.0.0/8', '::1/128' ) );
	$extra  = apply_filters( 'blueworx_trusted_proxies', array() );

	return is_array( $extra ) ? array_merge( $ranges, array_map( 'strval', $extra ) ) : $ranges;
}

/**
 * Whether an address falls inside a network.
 *
 * @param string $ip    IPv4 or IPv6 address.
 * @param string $range Network in CIDR form, or a single address.
 * @return bool True when the address is inside it.
 */
function blueworx_ip_in_range( $ip, $range ) {
	$parts  = explode( '/', (string) $range, 2 );
	$ip_bin = @inet_pton( (string) $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A malformed address returns false, handled below.
	$net    = @inet_pton( $parts[0] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A malformed address returns false, handled below.

	if ( false === $ip_bin || false === $net || strlen( $ip_bin ) !== strlen( $net ) ) {
		return false;
	}

	$bits  = isset( $parts[1] ) ? (int) $parts[1] : strlen( $ip_bin ) * 8;
	$bytes = intdiv( $bits, 8 );
	$extra = $bits % 8;

	if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $net, 0, $bytes ) ) {
		return false;
	}

	if ( $extra > 0 ) {
		$mask = ( 0xFF << ( 8 - $extra ) ) & 0xFF;

		return ( ord( $ip_bin[ $bytes ] ) & $mask ) === ( ord( $net[ $bytes ] ) & $mask );
	}

	return true;
}

/**
 * Whether an address is one of Cloudflare's.
 *
 * @param string $ip IP address.
 * @return bool True for a Cloudflare address.
 */
function blueworx_ip_is_cloudflare( $ip ) {
	foreach ( blueworx_cloudflare_ranges() as $range ) {
		if ( blueworx_ip_in_range( $ip, $range ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether an address is one of the trusted proxy networks.
 *
 * @param string $ip IP address.
 * @return bool True for a trusted proxy.
 */
function blueworx_ip_is_trusted_proxy( $ip ) {
	foreach ( blueworx_trusted_proxy_ranges() as $range ) {
		if ( blueworx_ip_in_range( $ip, $range ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Works out who a visitor is, for counting their missing pages.
 *
 * REMOTE_ADDR is the answer, and the only one believed, unless the request
 * comes from a proxy that is trusted to say who it is forwarding for (see
 * blueworx_trusted_proxy_ranges()). From Cloudflare the visitor is
 * CF-Connecting-IP. From any other trusted proxy the visitor is the last public
 * address in X-Forwarded-For, which is the one that proxy saw, not anything the
 * visitor wrote. A private address that is not trusted is a proxy the host has
 * not told us about, so nobody is counted. From anyone else the forwarding
 * headers are ignored. A site with a different arrangement says so with the
 * blueworx_trusted_proxies or blueworx_visitor_ip filters.
 *
 * @return string Visitor address, or an empty string when it cannot be told.
 */
function blueworx_visitor_ip() {
	$server = $_SERVER; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Each value is validated as an IP address below before it is used.
	$remote = isset( $server['REMOTE_ADDR'] ) ? (string) $server['REMOTE_ADDR'] : '';
	$public = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
	$ip     = '';

	if ( false !== filter_var( $remote, FILTER_VALIDATE_IP ) ) {
		$is_public = false !== filter_var( $remote, FILTER_VALIDATE_IP, $public );

		if ( ! blueworx_ip_is_trusted_proxy( $remote ) ) {
			// Not a proxy we know. A public address is the visitor. A private one
			// is some proxy the host has not told us about, so nobody is counted
			// rather than everybody as one.
			$ip = $is_public ? $remote : '';
		} elseif ( blueworx_ip_is_cloudflare( $remote ) && ! empty( $server['HTTP_CF_CONNECTING_IP'] ) && false !== filter_var( $server['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP ) ) {
			$ip = (string) $server['HTTP_CF_CONNECTING_IP'];
		} elseif ( ! empty( $server['HTTP_X_FORWARDED_FOR'] ) ) {
			// Right to left: the entry nearest the site was written by a proxy we
			// trust; anything further left could have been written by the visitor.
			foreach ( array_reverse( explode( ',', (string) $server['HTTP_X_FORWARDED_FOR'] ) ) as $candidate ) {
				$candidate = trim( $candidate );

				if ( false === filter_var( $candidate, FILTER_VALIDATE_IP, $public ) || blueworx_ip_is_trusted_proxy( $candidate ) ) {
					continue;
				}

				$ip = $candidate;
				break;
			}
		}
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
 * Adds one to a visitor's missing-page count for the current ten-minute window.
 *
 * The count lives in its own ten-minute window, so old misses drop out and one
 * a day never adds up to a pause. With a persistent object cache the add is
 * atomic. Without one it is a plain read and write, so a burst of simultaneous
 * requests can lose a count or two; the pause then comes a few requests later,
 * never not at all.
 *
 * @param string $ip Visitor address.
 * @return int The count so far in this window.
 */
function blueworx_missing_page_increment( $ip ) {
	$window = 10 * MINUTE_IN_SECONDS;
	$key    = blueworx_missing_page_key( 'count', $ip ) . '_' . (int) floor( time() / $window );

	if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
		wp_cache_add( $key, 0, 'blueworx_bot_control', $window );
		$count = wp_cache_incr( $key, 1, 'blueworx_bot_control' );

		if ( false !== $count ) {
			return (int) $count;
		}
	}

	$count = (int) get_transient( $key ) + 1;
	set_transient( $key, $count, $window );

	return $count;
}

/**
 * Counts one missing page against a visitor, pausing them at the limit.
 *
 * @param string $ip Visitor address.
 * @return void
 */
function blueworx_missing_page_record( $ip ) {
	if ( '' === $ip ) {
		return;
	}

	if ( blueworx_missing_page_increment( $ip ) >= blueworx_missing_page_allowance() ) {
		$seconds = blueworx_missing_page_pause_minutes() * MINUTE_IN_SECONDS;

		set_transient( blueworx_missing_page_key( 'pause', $ip ), time() + $seconds, $seconds );
	}
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

	if ( blueworx_bot_request_is_exempt( $path, blueworx_bot_request_query() ) ) {
		return;
	}

	$ip = $throttle_on ? blueworx_visitor_ip() : '';

	if ( $throttle_on ) {
		$left = blueworx_missing_page_pause_left( $ip );

		if ( $left > 0 && ! blueworx_bot_visitor_signed_in() ) {
			blueworx_bot_respond( 429, 'Too many requests for pages that do not exist. Please try again later.', array( 'Retry-After' => (string) $left ) );
		}
	}

	if ( $probe_on && apply_filters( 'blueworx_is_probe_path', blueworx_probe_path_matches( $path, false ), $path ) ) {
		// A probe is a missing page too, so a scanner is paused after a handful
		// of them rather than being answered cheaply for ever.
		if ( $throttle_on && ! blueworx_bot_visitor_signed_in() ) {
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
	if ( ! is_404() ) {
		return;
	}

	$path = blueworx_bot_request_path();

	if ( '' === $path || blueworx_bot_request_is_exempt( $path, blueworx_bot_request_query() ) ) {
		return;
	}

	$signed_in = is_user_logged_in();

	// A name that could have been a real page is turned away only now, when
	// WordPress has looked and there is no page there.
	if ( ! $signed_in && blueworx_feature_enabled( 'probe_block' ) && apply_filters( 'blueworx_is_probe_path', blueworx_probe_path_matches( $path ), $path ) ) {
		if ( blueworx_feature_enabled( 'missing_page_limit' ) ) {
			blueworx_missing_page_record( blueworx_visitor_ip() );
		}

		blueworx_bot_respond( blueworx_probe_status(), 403 === blueworx_probe_status() ? 'Forbidden' : 'Not found' );
	}

	if ( $signed_in || ! blueworx_feature_enabled( 'missing_page_limit' ) || blueworx_path_is_static_asset( $path ) ) {
		return;
	}

	blueworx_missing_page_record( blueworx_visitor_ip() );
}

/**
 * Gets the rules that keep a store page away from crawlers.
 *
 * A page address can be written several ways, and a rule written for one does
 * not cover the others: /pay-here/ does not cover /pay-here, and a plain
 * permalink (/?page_id=7) has no path at all. So each form gets its own rule,
 * and each is anchored so a different page that merely starts the same way
 * (/pay-here-guide/) is not caught.
 *
 * @param string $url Full address of the page.
 * @param string $base The site's folder, without a trailing slash.
 * @return string[] Disallow lines, empty when the address cannot be turned into a safe rule.
 */
function blueworx_crawler_rules_for_url( $url, $base ) {
	$path  = wp_parse_url( $url, PHP_URL_PATH );
	$query = wp_parse_url( $url, PHP_URL_QUERY );
	$path  = is_string( $path ) && '' !== $path ? $path : '/';

	if ( is_string( $query ) && '' !== $query ) {
		// Only the plain-permalink forms WordPress itself uses, and only the whole
		// value: page_id=7 must not catch page_id=70.
		if ( ! preg_match( '/^(page_id|p)=\d+$/', $query ) ) {
			return array();
		}

		return array(
			'Disallow: ' . $path . '?' . $query . '$',
			'Disallow: ' . $path . '?' . $query . '&',
		);
	}

	$trimmed = untrailingslashit( $path );

	// The site's own front page is never a store page, and "/" would shut
	// crawlers out of everything.
	if ( '' === $trimmed || $trimmed === $base || 0 !== strpos( $trimmed, $base . '/' ) ) {
		return array();
	}

	return array(
		'Disallow: ' . $trimmed . '/',
		'Disallow: ' . $trimmed . '$',
	);
}

/**
 * Gets the robots.txt rules that keep crawlers off cart, checkout and filters.
 *
 * Wildcards (*) and the end-of-address mark ($) are understood by Google, Bing
 * and the other major crawlers.
 *
 * @return string[] Disallow lines.
 */
function blueworx_crawler_rules() {
	$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$base = untrailingslashit( is_string( $home ) ? $home : '' );
	$out  = array();

	foreach ( array( '/cart', '/checkout', '/my-account' ) as $path ) {
		$out[] = 'Disallow: ' . $base . $path . '/';
		$out[] = 'Disallow: ' . $base . $path . '$';
	}

	// The store's own checkout and account pages, wherever they have been put.
	if ( function_exists( 'blueworx_store_page_url' ) ) {
		foreach ( array( 'checkout', 'dashboard' ) as $key ) {
			$url = blueworx_store_page_url( $key );

			if ( '' !== $url ) {
				$out = array_merge( $out, blueworx_crawler_rules_for_url( $url, $base ) );
			}
		}
	}

	foreach ( array( 'orderby=', 'filter_', 'min_price=', 'max_price=', 'rating_filter=', 'add-to-cart=' ) as $param ) {
		$out[] = 'Disallow: ' . $base . '/*?*' . $param;
	}

	$out   = array_values( array_unique( $out ) );
	$rules = apply_filters( 'blueworx_crawler_rules', $out );

	return is_array( $rules ) ? array_values( array_filter( array_map( 'strval', $rules ) ) ) : $out;
}

/**
 * Adds the crawler rules to robots.txt.
 *
 * The rules go into every group, after that group's User-agent lines. A crawler
 * that has a group of its own (Googlebot, say) reads only that group and
 * ignores the "User-agent: *" one, so a rule added only to the catch-all group
 * would never reach it. A group's existing rules are kept, and a rule it
 * already has is not added twice. Where there is no catch-all group, one is
 * added. A second "User-agent: *" group is never created: crawlers read the
 * first and ignore the rest.
 *
 * Sitemap lines, blank lines and comments sit between groups without ending
 * them, as they do for a crawler.
 *
 * @param string $output Generated robots.txt content.
 * @return string robots.txt content with the rules in it.
 */
function blueworx_crawler_rules_filter( $output ) {
	$lines = explode( "\n", str_replace( "\r\n", "\n", (string) $output ) );

	// Split the file into groups. A group is one or more consecutive User-agent
	// lines and the rules that follow them, up to the next User-agent line.
	$groups  = array();
	$current = -1;
	$in_head = false;

	foreach ( $lines as $index => $line ) {
		$trimmed = trim( $line );

		if ( '' === $trimmed || '#' === $trimmed[0] || preg_match( '/^sitemap:/i', $trimmed ) ) {
			continue;
		}

		if ( preg_match( '/^user-agent:\s*(.*)$/i', $trimmed, $match ) ) {
			if ( ! $in_head ) {
				++$current;
				$groups[ $current ] = array(
					'star'  => false,
					'end'   => $index,
					'rules' => array(),
				);
			}

			$in_head = true;

			$groups[ $current ]['end'] = $index;

			if ( '*' === trim( $match[1] ) ) {
				$groups[ $current ]['star'] = true;
			}

			continue;
		}

		$in_head = false;

		if ( $current >= 0 ) {
			$groups[ $current ]['rules'][ strtolower( preg_replace( '/\s+/', '', $trimmed ) ) ] = true;
		}
	}

	$wanted   = blueworx_crawler_rules();
	$has_star = false;

	// Work from the last group back, so the line numbers of earlier groups do not
	// move as rules are inserted.
	foreach ( array_reverse( $groups, true ) as $group ) {
		$has_star = $has_star || $group['star'];
		$add      = array();

		foreach ( $wanted as $rule ) {
			if ( ! isset( $group['rules'][ strtolower( preg_replace( '/\s+/', '', $rule ) ) ] ) ) {
				$add[] = $rule;
			}
		}

		if ( $add ) {
			array_splice( $lines, $group['end'] + 1, 0, $add );
		}
	}

	$result = implode( "\n", $lines );

	if ( ! $has_star && $wanted ) {
		// No catch-all group yet: add one at the end.
		$result = rtrim( $result ) . "\n\nUser-agent: *\n" . implode( "\n", $wanted ) . "\n";
	}

	return $result;
}

if ( blueworx_feature_enabled( 'probe_block' ) || blueworx_feature_enabled( 'missing_page_limit' ) ) {
	// Priority 0: as early as a plugin can run, before the theme, the query or
	// any template is touched.
	add_action( 'plugins_loaded', 'blueworx_bot_control_early', 0 );
}

if ( blueworx_feature_enabled( 'missing_page_limit' ) || blueworx_feature_enabled( 'probe_block' ) ) {
	// Late, after WordPress's own redirects (an old page address, a missing
	// trailing slash) have had their chance: those end in a page, not a miss.
	add_action( 'template_redirect', 'blueworx_missing_page_count_404', 100 );
}

if ( blueworx_feature_enabled( 'crawler_rules' ) ) {
	// After the robots.txt editor (priority 20), so its content is what gets the
	// rules added.
	add_filter( 'robots_txt', 'blueworx_crawler_rules_filter', 30 );
}
