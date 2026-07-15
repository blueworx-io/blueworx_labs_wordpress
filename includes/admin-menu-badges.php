<?php
/**
 * Admin menu count badges.
 *
 * Real counts from core APIs, computed once per request. A zero count renders no
 * badge, matching the design (which badges only non-zero items).
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the badge count for every badgeable menu slug.
 *
 * Statically cached: called once per request even if read repeatedly.
 *
 * @return array Counts keyed by menu slug. Zero counts are omitted.
 */
function blueworx_get_admin_menu_badge_counts() {
	static $counts = null;

	if ( null !== $counts ) {
		return $counts;
	}

	$counts = array();

	$posts = (int) wp_count_posts( 'post' )->publish;
	if ( $posts > 0 ) {
		$counts['edit.php'] = $posts;
	}

	$pages = (int) wp_count_posts( 'page' )->publish;
	if ( $pages > 0 ) {
		$counts['edit.php?post_type=page'] = $pages;
	}

	$media = 0;
	foreach ( (array) wp_count_attachments() as $mime_count ) {
		$media += (int) $mime_count;
	}
	if ( $media > 0 ) {
		$counts['upload.php'] = $media;
	}

	foreach ( get_post_types( array( '_builtin' => false, 'show_ui' => true ), 'names' ) as $post_type ) {
		$cpt_count = (int) wp_count_posts( $post_type )->publish;

		if ( $cpt_count > 0 ) {
			$counts[ 'edit.php?post_type=' . $post_type ] = $cpt_count;
		}
	}

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$active = count( (array) get_option( 'active_plugins', array() ) );
	if ( $active > 0 ) {
		$counts['plugins.php'] = $active;
	}

	return $counts;
}
