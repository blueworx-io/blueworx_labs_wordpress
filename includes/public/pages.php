<?php
/**
 * Public front-end layer — page routing.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The pages this plugin owns and renders.
 *
 * Real WordPress Pages are created for these so menus, SEO plugins and later
 * editing all work — but rendering is taken over in blueworx_public_template(),
 * so the active theme never gets a say in how they look.
 *
 * @return array Slug => array( title, template ).
 */
function blueworx_public_pages() {
	return (array) apply_filters(
		'blueworx_public_pages',
		array(
			'home' => array(
				'title'    => __( 'Home', 'blueworx-labs-wordpress' ),
				'template' => 'pages/home.php',
			),
		)
	);
}

/**
 * Creates the plugin's pages if they are absent. Idempotent.
 *
 * Pages are matched by the stored ID first and slug second, so a page the user
 * has renamed or moved is still recognised rather than silently duplicated on
 * every activation.
 *
 * @return void
 */
function blueworx_public_install_pages() {
	$map = (array) get_option( 'blueworx_public_page_ids', array() );

	foreach ( blueworx_public_pages() as $slug => $page ) {
		if ( isset( $map[ $slug ] ) && 'page' === get_post_type( $map[ $slug ] ) && 'trash' !== get_post_status( $map[ $slug ] ) ) {
			continue;
		}

		$existing = get_page_by_path( $slug );

		if ( $existing instanceof WP_Post ) {
			$map[ $slug ] = $existing->ID;
			continue;
		}

		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $page['title'],
				'post_name'    => $slug,
				'post_content' => '',
			)
		);

		if ( ! is_wp_error( $id ) ) {
			$map[ $slug ] = (int) $id;
		}
	}

	update_option( 'blueworx_public_page_ids', $map );
}

/**
 * Whether the current request is a page this plugin renders.
 *
 * QUERY-TIME ONLY. This depends on is_page() / get_queried_object(), which
 * are only reliable once the main query has run — around the `wp` action
 * and later (template_include, wp_enqueue_scripts, etc.). Called any
 * earlier (e.g. on `init`), the query has not executed yet, is_page()
 * always reports false, and this always returns false. For an `init`-time
 * check use blueworx_public_is_owned_request_path() instead, which reads
 * the request path directly rather than query state. Two functions exist on
 * purpose — do not collapse them back into one.
 *
 * @return bool True when owned.
 */
function blueworx_public_is_owned_page() {
	return null !== blueworx_public_current_template();
}

/**
 * Whether the current request path is one of this plugin's owned pages.
 *
 * INIT-TIME SAFE. Unlike blueworx_public_is_owned_page(), this never touches
 * the main query — it normalizes $_SERVER['REQUEST_URI'] and compares it
 * directly against the plugin's page slugs, the same way
 * blueworx_is_custom_login_request_path() (includes/login-security.php)
 * determines the custom login path before the query exists. Use this for
 * anything that runs at or before `init` (e.g. the Site Protection
 * exemption); use blueworx_public_is_owned_page() once the query has run,
 * where query state is available and preferable.
 *
 * Kept in agreement with blueworx_public_is_owned_page() on two points that
 * previously drifted:
 * - The site root ("/") only counts as owned once WordPress's front page has
 *   actually been pointed at a mapped page (`show_on_front` = 'page' and
 *   `page_on_front` is one of the IDs in blueworx_public_page_ids) — not
 *   unconditionally, since "/" is WordPress's own posts index until then.
 * - Slugs are resolved from the stored ID map (get_post_field()), so a page
 *   the admin has renamed is still recognised, falling back to the static
 *   slug from blueworx_public_pages() only for a page not yet in the map
 *   (fresh install, before activation has run).
 *
 * @return bool True when the request path belongs to this plugin.
 */
function blueworx_public_is_owned_request_path() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path        = strtolower( (string) wp_parse_url( sanitize_text_field( $request_uri ), PHP_URL_PATH ) );
	$path        = '/' . trim( $path, '/' );

	$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$home_path = '/' . trim( strtolower( (string) $home_path ), '/' );

	$map = (array) get_option( 'blueworx_public_page_ids', array() );

	$bases = array();

	// "/" is only an owned page once the front page has actually been
	// pointed at one of the plugin's pages (Task 4). Reading these two plain
	// options is safe at `init` — neither touches the main query.
	$page_on_front = (int) get_option( 'page_on_front' );

	if ( 'page' === get_option( 'show_on_front' ) && $page_on_front && in_array( $page_on_front, array_map( 'intval', $map ), true ) ) {
		$bases[] = $home_path;
	}

	foreach ( array_keys( blueworx_public_pages() ) as $slug ) {
		$current_slug = $slug;

		// get_post_field() is a direct post lookup, safe at `init` (unlike
		// is_page(), it is not a query conditional). Resolving through the
		// map means a rename is still recognised, matching
		// blueworx_public_current_template()'s query-time resolution.
		if ( isset( $map[ $slug ] ) ) {
			$actual_slug = get_post_field( 'post_name', (int) $map[ $slug ] );

			if ( $actual_slug ) {
				$current_slug = $actual_slug;
			}
		}

		$bases[] = $home_path . '/' . $current_slug;
		$bases[] = $home_path . '/index.php/' . $current_slug;
	}

	foreach ( array_unique( $bases ) as $base ) {
		$base = '/' . trim( $base, '/' );
		$base = '' === $base ? '/' : $base;

		if ( $path === $base ) {
			return true;
		}
	}

	return false;
}

/**
 * Absolute path to the template for the current request, or null.
 *
 * @return string|null Template path.
 */
function blueworx_public_current_template() {
	if ( is_admin() || ! is_page() ) {
		return null;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return null;
	}

	$pages = blueworx_public_pages();
	$map   = (array) get_option( 'blueworx_public_page_ids', array() );
	$slug  = array_search( $post->ID, $map, true );

	// Fall back to the slug so a fresh install works before the option is
	// written, and so a manually created page still resolves.
	if ( false === $slug ) {
		$slug = $post->post_name;
	}

	if ( ! isset( $pages[ $slug ] ) ) {
		return null;
	}

	$path = BLUEWORX_LABS_PATH . 'templates/' . $pages[ $slug ]['template'];

	return file_exists( $path ) ? $path : null;
}

/**
 * Keeps plugin-owned marketing pages public when Site Protection is on.
 *
 * Site Protection exists to hide a site in progress from the public. The
 * marketing site is the part that is deliberately public, so it is exempted —
 * otherwise turning that feature on takes the live site down.
 *
 * This filter fires from blueworx_intercept_requests() on `init` priority 1
 * (includes/login-security.php) — before the main query runs — so it must use
 * the path-based, init-time-safe blueworx_public_is_owned_request_path()
 * rather than blueworx_public_is_owned_page(). The query-time check always
 * reports false this early, which would silently disable the exemption.
 *
 * @param bool $protected Whether the request should be gated.
 * @return bool Filtered value.
 */
function blueworx_public_exempt_from_site_protection( $protected ) {
	return blueworx_public_is_owned_request_path() ? false : $protected;
}
add_filter( 'blueworx_site_protection_applies', 'blueworx_public_exempt_from_site_protection', 10 );
