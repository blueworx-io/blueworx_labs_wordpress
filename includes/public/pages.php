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
 * @return bool True when owned.
 */
function blueworx_public_is_owned_page() {
	return null !== blueworx_public_current_template();
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
 * @param bool $protected Whether the request should be gated.
 * @return bool Filtered value.
 */
function blueworx_public_exempt_from_site_protection( $protected ) {
	return blueworx_public_is_owned_page() ? false : $protected;
}
add_filter( 'blueworx_site_protection_applies', 'blueworx_public_exempt_from_site_protection', 10 );
