<?php
/**
 * Headless REST layer — outbound on-demand revalidation webhook.
 *
 * Default OFF. When enabled, content changes POST the affected path(s) plus a
 * shared secret to a configured frontend revalidation endpoint (ISR / on-demand
 * revalidation). This never triggers a full Netlify build.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the revalidation webhook is enabled and configured.
 *
 * @return bool True when active.
 */
function blueworx_headless_revalidate_ready() {
	return '1' === blueworx_headless_setting( 'revalidate_enabled' )
		&& '' !== trim( (string) blueworx_headless_setting( 'revalidate_url' ) )
		&& defined( 'BLUEWORX_LABS_REVALIDATE_SECRET' )
		&& '' !== (string) BLUEWORX_LABS_REVALIDATE_SECRET;
}

/**
 * Fires a revalidation request for a changed post.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function blueworx_headless_revalidate_post( $post_id, $post ) {
	if ( ! blueworx_headless_revalidate_ready() ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( ! in_array( get_post_status( $post_id ), array( 'publish', 'trash', 'draft', 'private' ), true ) ) {
		return;
	}

	$permalink = get_permalink( $post_id );
	$path      = $permalink ? wp_parse_url( $permalink, PHP_URL_PATH ) : '/';
	$paths     = array( $path ? $path : '/' );

	/**
	 * Filters the paths sent for revalidation on a content change.
	 *
	 * @param array   $paths   Affected frontend paths.
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	$paths = apply_filters( 'blueworx_headless_revalidate_paths', $paths, $post_id, $post );

	wp_remote_post(
		esc_url_raw( (string) blueworx_headless_setting( 'revalidate_url' ) ),
		array(
			'timeout'  => 2,
			'blocking' => false,
			'headers'  => array(
				'Content-Type'          => 'application/json',
				'X-Blueworx-Revalidate' => (string) BLUEWORX_LABS_REVALIDATE_SECRET,
			),
			'body'     => wp_json_encode( array( 'paths' => array_values( array_unique( $paths ) ) ) ),
		)
	);
}

/**
 * Handles publish/update revalidation.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function blueworx_headless_on_save_post( $post_id, $post ) {
	blueworx_headless_revalidate_post( $post_id, $post );
}
add_action( 'save_post', 'blueworx_headless_on_save_post', 20, 2 );

/**
 * Handles deletion revalidation.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function blueworx_headless_on_delete_post( $post_id ) {
	$post = get_post( $post_id );

	if ( $post instanceof WP_Post ) {
		blueworx_headless_revalidate_post( $post_id, $post );
	}
}
add_action( 'before_delete_post', 'blueworx_headless_on_delete_post', 20 );
