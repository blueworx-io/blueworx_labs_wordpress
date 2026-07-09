<?php
/**
 * Headless REST layer — public content endpoints.
 *
 * Nav menus, whitelisted site settings, a path resolver for frontend routing,
 * and an ACF options bridge. Also registers chosen CPTs into core REST and
 * attaches ACF fields to core responses.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the public-content routes.
 *
 * @return void
 */
function blueworx_headless_register_content_routes() {
	$ns = BLUEWORX_HEADLESS_NAMESPACE;

	register_rest_route(
		$ns,
		'/menus/(?P<location>[a-zA-Z0-9_-]+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'blueworx_headless_route_menu',
			'permission_callback' => '__return_true',
			'args'                => array(
				'location' => array(
					'sanitize_callback' => 'sanitize_key',
				),
			),
		)
	);

	register_rest_route(
		$ns,
		'/site',
		array(
			'methods'             => 'GET',
			'callback'            => 'blueworx_headless_route_site',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/resolve',
		array(
			'methods'             => 'GET',
			'callback'            => 'blueworx_headless_route_resolve',
			'permission_callback' => '__return_true',
			'args'                => array(
				'uri' => array(
					'required' => true,
				),
			),
		)
	);

	register_rest_route(
		$ns,
		'/acf-options',
		array(
			'methods'             => 'GET',
			'callback'            => 'blueworx_headless_route_acf_options',
			'permission_callback' => '__return_true',
		)
	);
}

/**
 * Builds a nested tree from a flat list of nav menu items.
 *
 * @param array $items    Flat menu items.
 * @param int   $parent_id Parent item ID.
 * @return array Nested items.
 */
function blueworx_headless_build_menu_tree( $items, $parent_id = 0 ) {
	$branch = array();

	foreach ( $items as $item ) {
		if ( (int) $item->menu_item_parent === (int) $parent_id ) {
			$branch[] = array(
				'id'        => (int) $item->ID,
				'title'     => $item->title,
				'url'       => $item->url,
				'target'    => $item->target,
				'object'    => $item->object,
				'object_id' => (int) $item->object_id,
				'children'  => blueworx_headless_build_menu_tree( $items, (int) $item->ID ),
			);
		}
	}

	return $branch;
}

/**
 * GET /menus/{location} — the menu assigned to a theme location.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_route_menu( WP_REST_Request $request ) {
	$location  = $request->get_param( 'location' );
	$locations = get_nav_menu_locations();

	if ( empty( $locations[ $location ] ) ) {
		return blueworx_headless_error( 'blueworx_menu_not_found', __( 'No menu is assigned to that location.', 'blueworx-project-wordpress-labs' ), 404 );
	}

	$items = wp_get_nav_menu_items( $locations[ $location ] );

	if ( false === $items ) {
		return blueworx_headless_error( 'blueworx_menu_not_found', __( 'No menu is assigned to that location.', 'blueworx-project-wordpress-labs' ), 404 );
	}

	return new WP_REST_Response(
		array(
			'location' => $location,
			'items'    => blueworx_headless_build_menu_tree( $items ),
		),
		200
	);
}

/**
 * GET /site — whitelisted public site settings.
 *
 * @return WP_REST_Response Response.
 */
function blueworx_headless_route_site() {
	$data = array(
		'name'           => get_bloginfo( 'name' ),
		'description'    => get_bloginfo( 'description' ),
		'url'            => home_url(),
		'admin_url'      => admin_url(),
		'language'       => get_bloginfo( 'language' ),
		'timezone'       => wp_timezone_string(),
		'date_format'    => get_option( 'date_format' ),
		'time_format'    => get_option( 'time_format' ),
		'posts_per_page' => (int) get_option( 'posts_per_page' ),
		'show_on_front'  => get_option( 'show_on_front' ),
		'page_on_front'  => (int) get_option( 'page_on_front' ),
		'page_for_posts' => (int) get_option( 'page_for_posts' ),
		'site_logo'      => wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' ),
	);

	/**
	 * Filters the public /site payload.
	 *
	 * @param array $data Site settings exposed to the frontend.
	 */
	$data = apply_filters( 'blueworx_headless_site_fields', $data );

	return new WP_REST_Response( $data, 200 );
}

/**
 * GET /resolve?uri= — map a frontend path to a WP object.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response Response.
 */
function blueworx_headless_route_resolve( WP_REST_Request $request ) {
	$uri  = (string) $request->get_param( 'uri' );
	$path = wp_parse_url( $uri, PHP_URL_PATH );
	$path = is_string( $path ) ? $path : '/';

	$post_id = url_to_postid( home_url( $path ) );

	if ( $post_id > 0 ) {
		$post = get_post( $post_id );

		return new WP_REST_Response(
			array(
				'type'     => $post->post_type,
				'id'       => $post_id,
				'slug'     => $post->post_name,
				'rest_url' => blueworx_headless_post_rest_url( $post ),
				'template' => 'single',
			),
			200
		);
	}

	$trimmed = trim( $path, '/' );

	if ( '' === $trimmed ) {
		return new WP_REST_Response(
			array(
				'type'     => 'front',
				'id'       => (int) get_option( 'page_on_front' ),
				'slug'     => '',
				'rest_url' => '',
				'template' => 'front',
			),
			200
		);
	}

	return new WP_REST_Response(
		array(
			'type'     => '404',
			'id'       => 0,
			'slug'     => '',
			'rest_url' => '',
			'template' => '404',
		),
		200
	);
}

/**
 * Returns the core REST URL for a post, when its type is REST-enabled.
 *
 * @param WP_Post $post Post object.
 * @return string REST URL or ''.
 */
function blueworx_headless_post_rest_url( $post ) {
	$type = get_post_type_object( $post->post_type );

	if ( ! $type || empty( $type->show_in_rest ) ) {
		return '';
	}

	$base = ! empty( $type->rest_base ) ? $type->rest_base : $post->post_type;

	return rest_url( 'wp/v2/' . $base . '/' . $post->ID );
}

/**
 * GET /acf-options — ACF options-page fields, when ACF is active.
 *
 * @return WP_REST_Response Response.
 */
function blueworx_headless_route_acf_options() {
	$fields = array();

	if ( function_exists( 'get_fields' ) ) {
		$options = get_fields( 'option' );

		if ( is_array( $options ) ) {
			$fields = $options;
		}
	}

	return new WP_REST_Response( $fields, 200 );
}

/**
 * Registers configured CPTs into core REST (show_in_rest) and attaches ACF
 * fields to core responses. Driven by the settings list; no CPT hardcoded.
 *
 * @return void
 */
function blueworx_headless_register_cpt_rest_support() {
	$raw = (string) blueworx_headless_setting( 'cpts' );

	if ( '' === trim( $raw ) ) {
		return;
	}

	$types = array_filter( array_map( 'sanitize_key', preg_split( '/[\s,]+/', $raw ) ) );

	foreach ( $types as $type ) {
		add_filter(
			'register_post_type_args',
			function ( $args, $post_type ) use ( $type ) {
				if ( $post_type === $type ) {
					$args['show_in_rest'] = true;
				}

				return $args;
			},
			10,
			2
		);
	}
}
add_action( 'init', 'blueworx_headless_register_cpt_rest_support', 5 );

/**
 * Attaches ACF fields to core REST responses for public post types.
 *
 * @return void
 */
function blueworx_headless_register_acf_bridge() {
	if ( ! function_exists( 'get_fields' ) ) {
		return;
	}

	$types = get_post_types( array( 'public' => true ), 'names' );

	register_rest_field(
		$types,
		'acf',
		array(
			'get_callback' => function ( $prepared ) {
				$fields = get_fields( $prepared['id'] );

				return is_array( $fields ) ? $fields : array();
			},
			'schema'       => null,
		)
	);
}
add_action( 'rest_api_init', 'blueworx_headless_register_acf_bridge' );
