<?php
/**
 * Headless REST layer — shortcode rendering.
 *
 * A shortcode's markup already reaches a headless front-end through
 * content.rendered, because wp/v2 runs do_shortcode server-side. What does not
 * reach it is the CSS and JS: plugins enqueue those on wp_enqueue_scripts, a
 * hook that never fires for a REST request. So anything interactive — pricing
 * tables, forms, sliders — arrives as inert markup or an empty container.
 *
 * This endpoint renders a shortcode and reports the assets it enqueued while
 * doing so, letting the front-end load them alongside the markup.
 *
 * Deliberately narrow: only allowlisted tags render. do_shortcode() on arbitrary
 * unauthenticated input would be remote code execution by proxy, since a
 * shortcode callback is just a PHP function.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode tags the endpoint is permitted to render.
 *
 * Empty means none: this fails closed, so enabling the endpoint is an explicit
 * act per tag rather than something a default turns on site-wide.
 *
 * @return array Lowercased tag names.
 */
function blueworx_headless_render_allowed_tags() {
	$raw  = (string) blueworx_headless_setting( 'render_shortcodes' );
	$tags = array_filter( array_map( 'trim', preg_split( '/[\s,]+/', $raw ) ) );

	/**
	 * Filters the renderable shortcode allowlist.
	 *
	 * @param array $tags Lowercased tag names.
	 */
	$tags = (array) apply_filters( 'blueworx_headless_render_allowed_tags', array_map( 'strtolower', $tags ) );

	return $tags;
}

/**
 * Extracts the shortcode tags used in a string.
 *
 * Uses WordPress's own shortcode regex so the endpoint sees exactly what
 * do_shortcode() will act on — a hand-rolled pattern would drift from it and
 * could miss a tag that then executes unchecked.
 *
 * @param string $content Raw content.
 * @return array Lowercased tag names, unique.
 */
function blueworx_headless_render_tags_in( $content ) {
	$pattern = get_shortcode_regex();

	if ( ! preg_match_all( '/' . $pattern . '/s', (string) $content, $matches ) ) {
		return array();
	}

	return array_values( array_unique( array_map( 'strtolower', $matches[2] ) ) );
}

/**
 * Snapshots the enqueued handles for scripts and styles.
 *
 * @return array {
 *     @type array $scripts Script handles.
 *     @type array $styles  Style handles.
 * }
 */
function blueworx_headless_render_asset_snapshot() {
	return array(
		'scripts' => (array) wp_scripts()->queue,
		'styles'  => (array) wp_styles()->queue,
	);
}

/**
 * Describes a registered dependency for the front-end to load.
 *
 * @param WP_Dependencies $registry Registry (wp_scripts()/wp_styles()).
 * @param string          $handle   Handle to describe.
 * @param bool            $is_script Whether this is a script.
 * @return array|null Description, or null when the handle is unknown.
 */
function blueworx_headless_render_describe( $registry, $handle, $is_script ) {
	if ( ! isset( $registry->registered[ $handle ] ) ) {
		return null;
	}

	$item = $registry->registered[ $handle ];
	$src  = (string) $item->src;

	// Relative srcs are relative to the WordPress root; the front-end is on a
	// different origin, so they have to be absolute to be usable at all.
	if ( '' !== $src && ! preg_match( '#^(https?:)?//#', $src ) ) {
		$src = site_url( $src );
	}

	if ( '' !== $src && ! empty( $item->ver ) ) {
		$src = add_query_arg( 'ver', $item->ver, $src );
	}

	$described = array(
		'handle' => $handle,
		'src'    => $src,
		'deps'   => array_values( (array) $item->deps ),
	);

	if ( $is_script ) {
		// wp_localize_script data. Without it a script that reads its config
		// object throws immediately and the feature is dead on arrival.
		$data = $registry->get_data( $handle, 'data' );

		$described['data']   = is_string( $data ) ? $data : '';
		$described['before'] = array_values( array_filter( (array) $registry->get_data( $handle, 'before' ), 'is_string' ) );
		$described['after']  = array_values( array_filter( (array) $registry->get_data( $handle, 'after' ), 'is_string' ) );
		$described['strategy'] = (string) ( $item->extra['strategy'] ?? '' );
	} else {
		$described['media']  = (string) ( $item->args ? $item->args : 'all' );
		$described['inline'] = array_values( array_filter( (array) $registry->get_data( $handle, 'after' ), 'is_string' ) );
	}

	return $described;
}

/**
 * Expands handles into their full dependency closure, dependencies first.
 *
 * The queue records only what was enqueued directly — WordPress resolves
 * dependencies at print time, which never happens for a REST request. A
 * front-end handed just the enqueued handle would load a script whose
 * dependency (jQuery, say) is missing, and it would throw on load.
 *
 * @param WP_Dependencies $registry Registry (wp_scripts()/wp_styles()).
 * @param array           $handles  Handles to expand.
 * @return array Handles in load order, dependencies before dependents.
 */
function blueworx_headless_render_with_deps( $registry, $handles ) {
	$ordered = array();
	$seen    = array();

	$visit = static function ( $handle ) use ( &$visit, $registry, &$ordered, &$seen ) {
		if ( isset( $seen[ $handle ] ) ) {
			return;
		}

		// Marked before recursing, so a circular dependency terminates rather
		// than recursing until the stack blows.
		$seen[ $handle ] = true;

		if ( ! isset( $registry->registered[ $handle ] ) ) {
			return;
		}

		foreach ( (array) $registry->registered[ $handle ]->deps as $dep ) {
			$visit( $dep );
		}

		$ordered[] = $handle;
	};

	foreach ( $handles as $handle ) {
		$visit( $handle );
	}

	return $ordered;
}

/**
 * Renders allowlisted shortcodes and reports the assets they enqueued.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function blueworx_headless_route_render( $request ) {
	$retry_after = blueworx_headless_rl_hit( 'render', 30, 5 * MINUTE_IN_SECONDS );

	if ( $retry_after > 0 ) {
		return blueworx_headless_rl_error( $retry_after );
	}

	$content = (string) $request->get_param( 'content' );

	if ( '' === trim( $content ) ) {
		return blueworx_headless_error(
			'blueworx_render_empty',
			__( 'No content to render.', 'blueworx-labs-wordpress' )
		);
	}

	$allowed = blueworx_headless_render_allowed_tags();

	if ( empty( $allowed ) ) {
		return blueworx_headless_error(
			'blueworx_render_disabled',
			__( 'Shortcode rendering is not enabled. Add the permitted tags in the Headless settings.', 'blueworx-labs-wordpress' ),
			403
		);
	}

	$found = blueworx_headless_render_tags_in( $content );

	if ( empty( $found ) ) {
		return blueworx_headless_error(
			'blueworx_render_no_shortcode',
			__( 'No shortcode found in the content.', 'blueworx-labs-wordpress' )
		);
	}

	$rejected = array_values( array_diff( $found, $allowed ) );

	// All-or-nothing: rendering the permitted half of a string would return
	// partial markup that looks successful, and silently drop the rest.
	if ( ! empty( $rejected ) ) {
		return blueworx_headless_error(
			'blueworx_render_not_allowed',
			sprintf(
				/* translators: %s: comma-separated shortcode tags. */
				__( 'These shortcodes are not on the render allowlist: %s', 'blueworx-labs-wordpress' ),
				implode( ', ', $rejected )
			),
			403
		);
	}

	$before = blueworx_headless_render_asset_snapshot();

	// Some plugins register and enqueue on wp_enqueue_scripts rather than inside
	// the shortcode callback. That hook never fires for a REST request, so their
	// assets would be invisible here. Opt-in, because firing it also pulls in
	// whatever the theme and every other plugin enqueue site-wide.
	if ( $request->get_param( 'with_global_enqueue' ) ) {
		if ( ! did_action( 'wp_enqueue_scripts' ) ) {
			do_action( 'wp_enqueue_scripts' );
		}
	}

	ob_start();
	$html = do_shortcode( $content );
	// Anything a callback echoed rather than returned would otherwise leak into
	// the JSON body and corrupt it.
	ob_end_clean();

	$after = blueworx_headless_render_asset_snapshot();

	$new_scripts = blueworx_headless_render_with_deps(
		wp_scripts(),
		array_values( array_diff( $after['scripts'], $before['scripts'] ) )
	);
	$new_styles = blueworx_headless_render_with_deps(
		wp_styles(),
		array_values( array_diff( $after['styles'], $before['styles'] ) )
	);

	$scripts = array();
	foreach ( $new_scripts as $handle ) {
		$described = blueworx_headless_render_describe( wp_scripts(), $handle, true );
		if ( null !== $described ) {
			$scripts[] = $described;
		}
	}

	$styles = array();
	foreach ( $new_styles as $handle ) {
		$described = blueworx_headless_render_describe( wp_styles(), $handle, false );
		if ( null !== $described ) {
			$styles[] = $described;
		}
	}

	return rest_ensure_response(
		array(
			'html'      => $html,
			'shortcodes' => $found,
			'styles'    => $styles,
			'scripts'   => $scripts,
		)
	);
}

/**
 * Registers the render route.
 *
 * @return void
 */
function blueworx_headless_register_render_routes() {
	register_rest_route(
		BLUEWORX_HEADLESS_NAMESPACE,
		'/render',
		array(
			'methods'             => 'POST',
			'callback'            => 'blueworx_headless_route_render',
			'permission_callback' => '__return_true',
			'args'                => array(
				'content'             => array(
					'required' => true,
					'type'     => 'string',
				),
				'with_global_enqueue' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
		)
	);
}
