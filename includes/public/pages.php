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
			'home'  => array(
				'title'    => __( 'Home', 'blueworx-labs-wordpress' ),
				'template' => 'pages/home.php',
			),
			'about' => array(
				'title'    => __( 'About', 'blueworx-labs-wordpress' ),
				'template' => 'pages/about.php',
			),
			'services' => array(
				'title'    => __( 'Services', 'blueworx-labs-wordpress' ),
				'template' => 'pages/services.php',
			),
			'contact' => array(
				'title'    => __( 'Contact', 'blueworx-labs-wordpress' ),
				'template' => 'pages/contact.php',
			),
			'work' => array(
				'title'    => __( 'Work', 'blueworx-labs-wordpress' ),
				'template' => 'pages/work.php',
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

	// "/" only becomes an owned page once the front page is actually pointed
	// at the plugin's home page — see blueworx_public_is_owned_request_path()
	// for why that condition matters at init time as well.
	if ( ! isset( $map['home'] ) ) {
		return;
	}

	$existing_front_id   = (int) get_option( 'page_on_front' );
	$owns_existing_front = $existing_front_id && in_array( $existing_front_id, array_map( 'intval', $map ), true );

	// Never overwrite a homepage the site owner (or another plugin) already
	// has configured. This only takes over the front page when it is unset,
	// or already pointed at a page this plugin itself owns (e.g. a prior
	// activation) — never a genuine, pre-existing homepage on a live site.
	// The owner can still reach the BlueWorx home page directly at its
	// "/home" slug.
	if ( $existing_front_id && ! $owns_existing_front ) {
		return;
	}

	// Snapshot the site's prior front-page configuration exactly once so
	// deactivation (blueworx_public_restore_prior_front(), registered in the
	// main plugin file) can hand it back. add_option() is a no-op when this
	// already exists, so a later re-activation can never overwrite the
	// genuine original with this plugin's own 'page' / Home-page values.
	add_option(
		'blueworx_public_prior_front',
		array(
			'show_on_front' => get_option( 'show_on_front' ),
			'page_on_front' => $existing_front_id,
		)
	);

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', (int) $map['home'] );
}

/**
 * Hands the front page back to whatever it pointed at before this plugin
 * took it over, on deactivation.
 *
 * Only acts when blueworx_public_prior_front (written once by
 * blueworx_public_install_pages() the first time it takes over the front
 * page) exists AND the front page is still actually pointed at this
 * plugin's Home page — if the site owner (or another plugin) has since
 * pointed the front page elsewhere, that change is respected and left
 * alone rather than clobbered.
 *
 * @return void
 */
function blueworx_public_restore_prior_front() {
	$prior = get_option( 'blueworx_public_prior_front', false );

	if ( ! is_array( $prior ) || ! array_key_exists( 'show_on_front', $prior ) || ! array_key_exists( 'page_on_front', $prior ) ) {
		return;
	}

	$map     = (array) get_option( 'blueworx_public_page_ids', array() );
	$home_id = isset( $map['home'] ) ? (int) $map['home'] : 0;

	$still_owns_front = $home_id
		&& 'page' === get_option( 'show_on_front' )
		&& (int) get_option( 'page_on_front' ) === $home_id;

	if ( ! $still_owns_front ) {
		return;
	}

	update_option( 'show_on_front', $prior['show_on_front'] );
	update_option( 'page_on_front', $prior['page_on_front'] );
	delete_option( 'blueworx_public_prior_front' );
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
 * An owned marketing page is always a clean-path request — it never
 * legitimately carries any query var beyond harmless tracking/analytics
 * params. This is deliberately an ALLOWLIST, not a denylist of known
 * content-selecting query vars: a denylist must anticipate every dangerous
 * key up front and reliably falls behind (it previously missed
 * `category_name` — the slug form of `cat` — `taxonomy`/`term`, `rest_route`
 * (which reaches this code because REST_REQUEST is not yet defined this
 * early at `init` priority 1), `attachment`, `embed` and `post_format`, each
 * of which let a logged-out request reach real content or the REST API
 * right through the "/" exemption). An allowlist only has to name the small,
 * fixed set of keys that are safe, and anything else — known or not — is
 * refused by default. If any query parameter present is not on the
 * allowlist, the request is not an eligible clean owned-page request,
 * regardless of its path.
 *
 * @return bool True when the request path belongs to this plugin.
 */
function blueworx_public_is_owned_request_path() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path        = strtolower( (string) wp_parse_url( sanitize_text_field( $request_uri ), PHP_URL_PATH ) );
	$path        = '/' . trim( $path, '/' );

	$query_string = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( '' !== $query_string ) {
		// Tracking/analytics params only — never a key that can select
		// content. Filterable so a site with a genuine need can extend it,
		// but the default stays strict; this is not the place to add a
		// content-selecting key back in.
		$allowed_query_params = (array) apply_filters(
			'blueworx_public_allowed_query_params',
			array(
				'utm_source',
				'utm_medium',
				'utm_campaign',
				'utm_term',
				'utm_content',
				'fbclid',
				'gclid',
				'mc_cid',
				'mc_eid',
			)
		);

		$query_vars = array();
		wp_parse_str( $query_string, $query_vars );

		if ( array_diff_key( $query_vars, array_flip( $allowed_query_params ) ) ) {
			return false;
		}
	}

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
	// written, and so a manually created page still resolves — but only when
	// that slug has no entry in the map at all. A slug the map already claims
	// belongs exclusively to the mapped ID: if an admin renames that page away
	// and a different, unrelated page later takes the freed slug, the new
	// page's ID will not be in the map (so array_search fails) but its slug
	// still collides with the map entry. Falling through to the static
	// registry in that case would render the plugin's template over a page
	// it does not own.
	if ( false === $slug ) {
		$candidate_slug = $post->post_name;

		if ( isset( $map[ $candidate_slug ] ) && (int) $map[ $candidate_slug ] !== (int) $post->ID ) {
			return null;
		}

		$slug = $candidate_slug;
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

/**
 * Hands rendering of owned pages to the plugin's own template.
 *
 * @param string $template Theme template WordPress resolved.
 * @return string Template path to load.
 */
function blueworx_public_template( $template ) {
	$own = blueworx_public_current_template();

	return null === $own ? $template : $own;
}
add_filter( 'template_include', 'blueworx_public_template' );
