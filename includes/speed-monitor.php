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
 * Asks WordPress's own request, not a query a theme made later, a question.
 *
 * A theme that runs query_posts() for a related-posts list and never resets it
 * changes what the template tags (is_home(), is_singular()) see. The original
 * request is kept in $wp_the_query.
 *
 * @param string $method WP_Query method, for example is_singular.
 * @return bool The answer.
 */
function blueworx_main_query_is( $method ) {
	global $wp_the_query;

	if ( is_object( $wp_the_query ) && method_exists( $wp_the_query, $method ) ) {
		return (bool) $wp_the_query->$method();
	}

	return function_exists( $method ) && (bool) $method();
}

/**
 * Gets the page or term the original request was for.
 *
 * @return object|null The queried object.
 */
function blueworx_main_query_object() {
	global $wp_the_query;

	if ( is_object( $wp_the_query ) && method_exists( $wp_the_query, 'get_queried_object' ) ) {
		$object = $wp_the_query->get_queried_object();
	} else {
		$object = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
	}

	return is_object( $object ) ? $object : null;
}

/**
 * Gets every taxonomy the original request asks for, not only the first.
 *
 * Language plugins add their own taxonomy to requests; those are left out, since
 * they never put a term in the address.
 *
 * @return string[] Taxonomy names.
 */
function blueworx_main_query_taxonomies() {
	global $wp_the_query;

	$names = array();

	if ( blueworx_main_query_is( 'is_category' ) ) {
		$names[] = 'category';
	}

	if ( blueworx_main_query_is( 'is_tag' ) ) {
		$names[] = 'post_tag';
	}

	if ( is_object( $wp_the_query ) && isset( $wp_the_query->tax_query->queried_terms ) && is_array( $wp_the_query->tax_query->queried_terms ) ) {
		$names = array_merge( $names, array_map( 'strval', array_keys( $wp_the_query->tax_query->queried_terms ) ) );
	}

	return array_values( array_diff( array_unique( $names ), array( 'language', 'term_language', 'post_translations' ) ) );
}

/**
 * Gets every post type the original request asks for.
 *
 * @param bool $with_object Whether the queried object's own name counts as a type (not for a term, whose name is its title).
 * @return string[] Post type names; empty when the request does not say.
 */
function blueworx_main_query_post_types( $with_object = true ) {
	global $wp_the_query;

	$types = array();

	if ( is_object( $wp_the_query ) && method_exists( $wp_the_query, 'get' ) ) {
		$types = array_merge( $types, (array) $wp_the_query->get( 'post_type' ) );
	}

	$object = $with_object ? blueworx_main_query_object() : null;

	if ( null !== $object && isset( $object->name ) ) {
		$types[] = $object->name;
	}

	return array_values( array_unique( array_filter( array_map( 'strval', $types ), 'strlen' ) ) );
}

/**
 * The first pieces of an address that are WordPress's or a store's own words.
 *
 * Anything else at the start of an address that WordPress did not answer as the
 * site's content can be a person's name, so it is not kept. A site adds its own
 * (a members folder, say) with the filter.
 *
 * @return string[] Lower case names, or file names.
 */
function blueworx_slow_safe_prefixes() {
	$known = array(
		'wp-admin',
		'wp-json',
		'wp-content',
		'wp-includes',
		'wp-login.php',
		'wp-cron.php',
		'wp-sitemap.xml',
		'xmlrpc.php',
		'index.php',
		'sitemap.xml',
		'sitemap_index.xml',
		'robots.txt',
		'favicon.ico',
		'feed',
		'comments',
		'author',
		'category',
		'tag',
		'page',
		'shop',
		'product',
		'product-category',
		'product-tag',
		'cart',
		'checkout',
		'my-account',
		'wc-api',
	);

	return array_map( 'strtolower', array_map( 'strval', (array) apply_filters( 'blueworx_slow_log_safe_prefixes', $known ) ) );
}

/**
 * Whether the first piece of an address is one of the known safe ones.
 *
 * @param string $segment First piece of the address.
 * @return bool True when it may be kept.
 */
function blueworx_slow_prefix_is_safe( $segment ) {
	return in_array( strtolower( (string) $segment ), blueworx_slow_safe_prefixes(), true );
}

/**
 * One piece of an address as it is kept in a note: plain printable characters
 * only, and no more than 60 of them.
 *
 * @param string $segment Piece of the address, already decoded.
 * @return string Cleaned piece.
 */
function blueworx_slow_clean_segment( $segment ) {
	return substr( (string) preg_replace( '/[^!-~]/', '', (string) $segment ), 0, 60 );
}

/**
 * One piece of an address in the form the notes keep it: decoded, a personal
 * looking piece turned into a star, anything else cleaned.
 *
 * @param string $segment Piece of the address, as sent (possibly encoded).
 * @return string Piece as kept.
 */
function blueworx_slow_normalise_segment( $segment ) {
	$segment = rawurldecode( (string) $segment );

	return blueworx_segment_is_personal( $segment ) ? '*' : blueworx_slow_clean_segment( $segment );
}

/**
 * The pieces of an address that come after the folder the site lives in.
 *
 * @param string[] $segments The pieces of the address.
 * @return string[] The same pieces without the site's own folder at the front.
 */
function blueworx_slow_segments_below_home( $segments ) {
	$base = function_exists( 'home_url' ) ? wp_parse_url( home_url(), PHP_URL_PATH ) : '';
	$base = is_string( $base ) ? array_values( array_filter( explode( '/', $base ), 'strlen' ) ) : array();

	// The request's pieces are decoded and cleaned before they get here, so the
	// folder's are brought to the same form (a folder named caf%C3%A9).
	$base = array_map( 'blueworx_slow_normalise_segment', $base );

	$segments = array_values( $segments );

	if ( $base && array_slice( $segments, 0, count( $base ) ) === $base ) {
		$segments = array_values( array_slice( $segments, count( $base ) ) );
	}

	// Addresses on a site without rewrite rules in the server start with index.php.
	if ( isset( $segments[0] ) && 'index.php' === strtolower( $segments[0] ) ) {
		$segments = array_values( array_slice( $segments, 1 ) );
	}

	return $segments;
}

/**
 * The word WordPress puts in front of a page number ("page" unless changed).
 *
 * @return string Pagination base, lower case.
 */
function blueworx_pagination_base() {
	global $wp_rewrite;

	$base = ( is_object( $wp_rewrite ) && ! empty( $wp_rewrite->pagination_base ) ) ? (string) $wp_rewrite->pagination_base : 'page';

	return strtolower( $base );
}

/**
 * Whether the pieces of an address are the posts page chosen in Settings, or a numbered page of it.
 *
 * @param string[] $segments The pieces of the address, below the site's folder.
 * @return bool True when the address is exactly the posts page's own address.
 */
function blueworx_segments_are_posts_page( $segments ) {
	$id = (int) get_option( 'page_for_posts' );

	if ( $id <= 0 || ! function_exists( 'get_page_uri' ) ) {
		return false;
	}

	$uri = get_page_uri( $id );

	if ( ! is_string( $uri ) || '' === $uri ) {
		return false;
	}

	// The page's address is stored encoded, and a request's is cleaned the same
	// way the label is (letters outside plain ASCII dropped), so both are brought
	// to that form first.
	$own  = array_map( 'blueworx_slow_clean_segment', array_values( array_filter( explode( '/', rawurldecode( $uri ) ), 'strlen' ) ) );
	$own  = array_map( 'strtolower', $own );
	$have = array_map( 'strtolower', array_map( 'strval', $segments ) );
	$n    = count( $own );

	if ( array_slice( $have, 0, $n ) !== $own ) {
		return false;
	}

	return blueworx_segments_are_page_number( array_slice( $have, $n ) );
}

/**
 * Whether the pieces of an address are the address of the post, page or product that was asked for.
 *
 * A plugin can answer /members/jane-doe/ or /author/jane-doe/?p=123 with a real
 * page or post, so WordPress finding a post is not enough: the address has to be
 * that post's own, optionally followed by a page number, comment page or feed.
 *
 * @param string[] $segments The pieces of the address, below the site's folder.
 * @param object   $post_object The post WordPress answered with.
 * @return bool True when the address is the object's own.
 */
function blueworx_segments_are_object_address( $segments, $post_object ) {
	if ( ! function_exists( 'get_permalink' ) ) {
		return false;
	}

	$link = get_permalink( $post_object );
	$path = is_string( $link ) ? wp_parse_url( $link, PHP_URL_PATH ) : '';

	if ( ! is_string( $path ) ) {
		return false;
	}

	$own = array_values( array_filter( explode( '/', $path ), 'strlen' ) );
	$own = blueworx_slow_segments_below_home( array_map( 'blueworx_slow_normalise_segment', $own ) );
	$own = array_map( 'strtolower', $own );

	$have = array_map( 'strtolower', array_map( 'strval', $segments ) );
	$n    = count( $own );

	if ( array_slice( $have, 0, $n ) !== $own ) {
		return false;
	}

	$rest = array_slice( $have, $n );

	if ( empty( $rest ) ) {
		return true;
	}

	// One piece after the address: a page of a post split with <!--nextpage-->
	// (/slug/2/), a comment page or one of WordPress's own endpoints.
	if ( 1 === count( $rest ) && 1 === preg_match( '/^([0-9]{1,5}|comment-page-[0-9]{1,5}|feed|embed|trackback|amp)$/', $rest[0] ) ) {
		return true;
	}

	// A named feed (/slug/feed/rss2/).
	if ( 2 === count( $rest ) && 'feed' === $rest[0] && 1 === preg_match( '/^(feed|rss|rss2|rdf|atom)$/', $rest[1] ) ) {
		return true;
	}

	// A store's account and checkout endpoints are the page with a word and a
	// short value after it (/my-account/orders/, /my-account/edit-address/billing/,
	// /checkout/order-received/*/). Only a word the store knows, and a value that is
	// a number, billing, shipping or already a star.
	if ( count( $rest ) <= 3 && in_array( $rest[0], blueworx_store_endpoints(), true ) ) {
		foreach ( array_slice( $rest, 1 ) as $value ) {
			if ( 1 !== preg_match( '/^(billing|shipping|[0-9]{1,10}|[*])$/', $value ) ) {
				return false;
			}
		}

		return true;
	}

	return blueworx_segments_are_page_number( $rest );
}

/**
 * The words a store puts after a page's address (WooCommerce's account and checkout endpoints).
 *
 * @return string[] Lower case words. A site adds its own with the blueworx_slow_log_endpoints filter.
 */
function blueworx_store_endpoints() {
	$known = array(
		'orders',
		'view-order',
		'downloads',
		'edit-address',
		'edit-account',
		'payment-methods',
		'add-payment-method',
		'delete-payment-method',
		'set-default-payment-method',
		'lost-password',
		'customer-logout',
		'order-received',
		'order-pay',
	);

	return array_map( 'strtolower', array_map( 'strval', (array) apply_filters( 'blueworx_slow_log_endpoints', $known ) ) );
}

/**
 * Whether the pieces of an address are nothing, or "page" and a number.
 *
 * @param string[] $segments The pieces of the address.
 * @return bool True for the home page itself or one of its numbered pages.
 */
function blueworx_segments_are_page_number( $segments ) {
	if ( empty( $segments ) ) {
		return true;
	}

	return 2 === count( $segments ) && blueworx_pagination_base() === strtolower( (string) $segments[0] ) && 1 === preg_match( '/^[0-9]{1,5}$/', (string) $segments[1] );
}

/**
 * The shapes a date archive's address can take on this site, as patterns.
 *
 * Read from WordPress's own date address structure (the way it builds its year
 * and month structures from it), so a site with its dates under /archives/date/,
 * a word stuck to the year (/archive-2026/) or the year last is recognised, and
 * nothing else is.
 *
 * @return string[][] Each shape is a list of patterns, one for each piece of the address.
 */
function blueworx_date_archive_shapes() {
	global $wp_rewrite;

	$struct = '%year%/%monthnum%/%day%';

	if ( is_object( $wp_rewrite ) && method_exists( $wp_rewrite, 'get_date_permastruct' ) ) {
		$struct = $wp_rewrite->get_date_permastruct();

		// Plain addresses (?m=202405): nothing in the path.
		if ( ! is_string( $struct ) || '' === $struct ) {
			return array( array() );
		}
	}

	$struct = strtolower( $struct );
	$month  = preg_replace( '#/+#', '/', str_replace( '%day%', '', $struct ) );
	$year   = preg_replace( '#/+#', '/', str_replace( '%monthnum%', '', $month ) );
	$shapes = array( array() );

	foreach ( array( $year, $month, $struct ) as $form ) {
		$pieces = array_values( array_filter( explode( '/', $form ), 'strlen' ) );

		if ( isset( $pieces[0] ) && 'index.php' === $pieces[0] ) {
			array_shift( $pieces );
		}

		$shape = array();

		foreach ( $pieces as $piece ) {
			$pattern = '';

			foreach ( (array) preg_split( '/(%year%|%monthnum%|%day%)/', $piece, -1, PREG_SPLIT_DELIM_CAPTURE ) as $part ) {
				if ( '%year%' === $part ) {
					$pattern .= '[0-9]{4}';
				} elseif ( '%monthnum%' === $part || '%day%' === $part ) {
					$pattern .= '[0-9]{1,2}';
				} else {
					// Fixed words are compared the way a request's pieces are kept.
					$pattern .= preg_quote( strtolower( blueworx_slow_clean_segment( rawurldecode( (string) $part ) ) ), '#' );
				}
			}

			$shape[] = '#^' . $pattern . '$#';
		}

		$shapes[] = $shape;
	}

	return $shapes;
}

/**
 * Whether the pieces of an address are one of the shapes a date archive has.
 *
 * @param string[] $segments The pieces of the address, lower case.
 * @return bool True when each piece fits the shape's pattern.
 */
function blueworx_segments_fit_date_shape( $segments ) {
	$count = count( $segments );

	foreach ( blueworx_date_archive_shapes() as $shape ) {
		if ( count( $shape ) !== $count ) {
			continue;
		}

		$match = true;

		foreach ( $shape as $index => $pattern ) {
			if ( 1 !== preg_match( $pattern, $segments[ $index ] ) ) {
				$match = false;
				break;
			}
		}

		if ( $match ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether the pieces of an address are only what a date archive is made of.
 *
 * @param string[] $segments The pieces of the address.
 * @return bool True for nothing (a ?m= address), or a date in the form this site's addresses use, then optionally "page" and a number.
 */
function blueworx_segments_are_date( $segments ) {
	$segments = array_values( array_map( 'strtolower', array_map( 'strval', $segments ) ) );
	$count    = count( $segments );

	// Read as it stands first: a folder called "page" (/archives/page/2026/) is
	// part of the address, not a page number.
	if ( blueworx_segments_fit_date_shape( $segments ) ) {
		return true;
	}

	if ( $count >= 2 && blueworx_pagination_base() === $segments[ $count - 2 ] && 1 === preg_match( '/^[0-9]{1,5}$/', $segments[ $count - 1 ] ) ) {
		return blueworx_segments_fit_date_shape( array_slice( $segments, 0, $count - 2 ) );
	}

	return false;
}

/**
 * Whether an address carries the address word of a taxonomy that is not on the list.
 *
 * The request's own flags can miss a second term (a tag asked for next to a
 * category), so the address is also read for the word each public taxonomy puts
 * before its terms (/tag/, /category/, /product-tag/).
 *
 * @param string[] $segments The pieces of the address.
 * @param string[] $listed   Taxonomy names the site keeps, lower case.
 * @return bool True when a taxonomy left off the list has its word, and a term after it, in the address.
 */
function blueworx_segments_name_unlisted_term( $segments, $listed ) {
	if ( empty( $segments ) || ! function_exists( 'get_taxonomies' ) || ! function_exists( 'get_taxonomy' ) ) {
		return false;
	}

	$segments = array_map( 'strtolower', array_map( 'strval', array_values( $segments ) ) );
	$total    = count( $segments );

	foreach ( (array) get_taxonomies( array( 'public' => true ), 'names' ) as $name ) {
		if ( in_array( strtolower( (string) $name ), $listed, true ) ) {
			continue;
		}

		$taxonomy = get_taxonomy( $name );
		$slug     = is_object( $taxonomy ) && is_array( $taxonomy->rewrite ) && ! empty( $taxonomy->rewrite['slug'] ) ? strtolower( trim( (string) $taxonomy->rewrite['slug'], '/' ) ) : '';
		$words    = '' === $slug ? array() : explode( '/', $slug );
		$length   = count( $words );

		// The word is a route only when something (the term) comes after it.
		for ( $start = 0; $length > 0 && $start + $length < $total; ++$start ) {
			if ( array_slice( $segments, $start, $length ) === $words ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Whether WordPress resolved this request to the site's own content.
 *
 * @param string[] $segments The pieces of the address, to check the answer against.
 * @return bool True for the home page, a post, page or product, a post type archive, or a category, tag or product category or tag archive.
 */
function blueworx_request_is_site_content( $segments = array() ) {
	if ( ! did_action( 'wp' ) ) {
		return false;
	}

	// A post type that holds people (members, staff, students, a directory) names
	// them in its address, so only the ordinary content types keep theirs. A site
	// adds its own with the filter.
	$types = (array) apply_filters( 'blueworx_slow_log_content_types', array( 'post', 'page', 'product' ) );
	$taxes = array_map( 'strtolower', array_map( 'strval', (array) apply_filters( 'blueworx_slow_log_taxonomies', array( 'category', 'post_tag', 'product_cat', 'product_tag' ) ) ) );

	// A plugin that answers /jane-doe/ (or /page/jane-doe/) with a query argument
	// of its own can leave WordPress calling the request the home page or a date
	// archive. So the address has to look like one too: nothing in it, or a page
	// number for the home page; a year, month, day and page number for a date.
	// A site kept in a folder (example.com/site/) has that folder in front of
	// every address, so it is set aside first.
	$segments = blueworx_slow_segments_below_home( $segments );

	// Whatever WordPress says about the request, a term of a taxonomy that is not on
	// the list is never kept: its address word (/tag/) in the address is enough.
	// A post's own address can hold a category name too (/news/category/x/post/),
	// so this holds for posts as well. The word on its own, with nothing after it
	// (/product/brand/), names no term and stays.
	if ( blueworx_segments_name_unlisted_term( $segments, $taxes ) ) {
		return false;
	}

	if ( blueworx_main_query_is( 'is_front_page' ) ) {
		if ( blueworx_segments_are_page_number( $segments ) ) {
			return true;
		}

		// A static home page also has its own endpoints (/comment-page-2/).
		$page = blueworx_main_query_object();

		return blueworx_main_query_is( 'is_singular' ) && null !== $page && isset( $page->post_type ) && in_array( $page->post_type, $types, true ) && blueworx_segments_are_object_address( $segments, $page );
	}

	if ( blueworx_main_query_is( 'is_home' ) ) {
		// The page chosen to show the latest posts, and only at its own address: a
		// plugin can answer /jane-doe/ through it with a query argument.
		$page = blueworx_main_query_object();

		return null !== $page && isset( $page->post_type ) && in_array( $page->post_type, $types, true ) && blueworx_segments_are_posts_page( $segments );
	}

	if ( blueworx_main_query_is( 'is_date' ) ) {
		return blueworx_segments_are_date( $segments );
	}

	if ( blueworx_main_query_is( 'is_singular' ) ) {
		// The page that was asked for, not whatever post a theme's own loop (related
		// posts, say) left as the current one.
		$page = blueworx_main_query_object();

		return null !== $page && isset( $page->post_type ) && in_array( $page->post_type, $types, true ) && blueworx_segments_are_object_address( $segments, $page );
	}

	// An author's archive can also be a category, tag or post type archive
	// (/author/jane-doe/?cat=1), and the author's name is in the address.
	if ( blueworx_main_query_is( 'is_author' ) ) {
		return false;
	}

	// Category, tag and other term archives all name a term, so all answer to the
	// same list: a site that takes one off it keeps those addresses out. Asked
	// before the post type archive, since WordPress can call one request both
	// (a category of a custom type) and the term is what names the address.
	if ( blueworx_main_query_is( 'is_category' ) || blueworx_main_query_is( 'is_tag' ) || blueworx_main_query_is( 'is_tax' ) ) {
		$term = blueworx_main_query_object();

		// The queried object is only the first term the request names. A request can
		// name a second one (/tag/jane/?cat=1 is a category to WordPress, but its
		// address holds the tag), so every taxonomy it asks for has to be on the
		// list, not just the first.
		foreach ( blueworx_main_query_taxonomies() as $taxonomy ) {
			if ( ! in_array( $taxonomy, $taxes, true ) ) {
				return false;
			}
		}

		if ( null === $term || ! isset( $term->taxonomy ) || ! in_array( $term->taxonomy, $taxes, true ) ) {
			return false;
		}

		// A term archive can also be narrowed to a post type (/category/news/?post_type=member).
		// WordPress does not always flag that as a post type archive (a type with no
		// archive, or several types at once), so any type the request names has to be
		// one the site keeps too.
		foreach ( blueworx_main_query_post_types( false ) as $type ) {
			if ( ! in_array( $type, $types, true ) ) {
				return false;
			}
		}

		return true;
	}

	if ( ! blueworx_main_query_is( 'is_post_type_archive' ) ) {
		return false;
	}

	// A post type that holds people has an archive that lists them, so the type
	// has to be one the site counts as its own content, like single posts are.
	foreach ( blueworx_main_query_post_types() as $type ) {
		if ( ! in_array( $type, $types, true ) ) {
			return false;
		}
	}

	return true;
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
		if ( blueworx_main_query_is( 'is_search' ) ) {
			return '(search)';
		}

		if ( blueworx_main_query_is( 'is_404' ) ) {
			return '(page not found)';
		}
	}

	$segments = array();

	foreach ( explode( '/', $path ) as $segment ) {
		if ( '' === $segment ) {
			continue;
		}

		$segments[] = blueworx_segment_is_personal( $segment ) ? '*' : blueworx_slow_clean_segment( $segment );
	}

	if ( in_array( blueworx_request_kind(), array( 'rest', 'ajax' ), true ) || ( isset( $segments[0] ) && 'wp-json' === $segments[0] ) ) {
		$segments = array_slice( $segments, 0, 3 );
	}

	// A pretty search address (/search/whatever/) when WordPress has not said so.
	if ( isset( $segments[0] ) && 'search' === $segments[0] ) {
		return '(search)';
	}

	// On the front of the site only a page that is the site's own content keeps
	// its whole address: a post, a page, an archive, the home page. Everything else
	// (a member's profile, an author page, anything a plugin answers) keeps only
	// its first piece, since the rest can be a person's name. The same goes when
	// WordPress never got far enough to say.
	if ( 'front' === blueworx_request_kind() && ! empty( $segments ) && ! blueworx_request_is_site_content( $segments ) ) {
		// Only a first piece that is known to be WordPress's or a store's own word
		// stays. Anything else can be a person's name (/jane-doe/, /jane.doe/,
		// /jane-doe/feed/), whatever shape it has.
		if ( ! blueworx_slow_prefix_is_safe( $segments[0] ) ) {
			$segments = array( '*' );
			$path     = '/*';
		} elseif ( count( $segments ) > 1 ) {
			$segments = array( $segments[0], '*' );
			$path     = '/' . $segments[0] . '/*';
		}
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

	if ( ! is_array( $log ) ) {
		return array();
	}

	// Entries saved before the log stopped keeping personal details are cut back
	// to their first piece, so what an earlier version stored never reaches a
	// screen or a report.
	foreach ( $log as $index => $entry ) {
		if ( is_array( $entry ) && ( empty( $entry['v'] ) || (int) $entry['v'] < 7 ) && isset( $entry['path'] ) ) {
			$log[ $index ]['path'] = blueworx_slow_generalise_path( (string) $entry['path'] );
			$log[ $index ]['v']    = 7;
		}
	}

	return $log;
}

/**
 * Cuts an older log entry's address back to its first piece.
 *
 * @param string $path Address as an earlier version saved it.
 * @return string Address with nothing after the first piece, or a bracketed note.
 */
function blueworx_slow_generalise_path( $path ) {
	if ( '' === $path || '(' === $path[0] ) {
		return $path;
	}

	$route    = (string) strtok( $path, '?' );
	$segments = array_values( array_filter( explode( '/', $route ), 'strlen' ) );

	if ( empty( $segments ) ) {
		return '/';
	}

	if ( 'search' === $segments[0] ) {
		return '(search)';
	}

	if ( ! blueworx_slow_prefix_is_safe( $segments[0] ) ) {
		return '/*';
	}

	if ( count( $segments ) > 1 ) {
		return '/' . $segments[0] . '/*';
	}

	// One piece at the root can be a person's name unless it is a known one.
	return blueworx_slow_prefix_is_safe( $segments[0] ) ? '/' . $segments[0] . ( false === strpos( $segments[0], '.' ) ? '/' : '' ) : '/*';
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
		'v'      => 7,
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
			wp_cache_set( 'sample_note', time(), 'blueworx_speed', WEEK_IN_SECONDS );
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
	// shortest it can be set to, and is saved with the other autoloaded settings
	// the first time the admin is opened (see blueworx_speed_seed_options()).
	// Until then a site without a persistent cache pays one lookup, and only on a
	// request that was already slow.
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
 * Saves the speed functions' own defaults the first time somebody opens the admin.
 *
 * An option that was never saved is looked up in the database on every request
 * that asks for it, on a site without a persistent object cache. Saved, and set
 * to load with the rest, it costs nothing. The values are the defaults, so
 * nothing changes.
 *
 * @return void
 */
function blueworx_speed_seed_options() {
	foreach ( array( 'guest_cart_checks', 'surerank_link_checks', 'crawl_delay', 'weekly_cleanup', 'slow_log', 'speed_report' ) as $key ) {
		if ( false === get_option( 'blueworx_feature_' . $key ) ) {
			add_option( 'blueworx_feature_' . $key, blueworx_get_feature_default( $key ), '', true );
		}
	}

	if ( false === get_option( 'blueworx_slow_threshold' ) ) {
		add_option( 'blueworx_slow_threshold', 2, '', true );
	}
}
add_action( 'admin_init', 'blueworx_speed_seed_options' );

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
