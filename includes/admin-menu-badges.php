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
 * Counts the plugins running on this site.
 *
 * On a network that includes the ones switched on for every site, which are
 * kept in a network option rather than the site's own list. A plugin that
 * somehow appears in both is counted once.
 *
 * @return int Number of active plugins.
 */
function blueworx_count_active_plugins() {
	$active = (array) get_option( 'active_plugins', array() );

	if ( is_multisite() ) {
		// Keyed by plugin file. Not cast with (array): a stored false would
		// become one plugin that is not there.
		$network = get_site_option( 'active_sitewide_plugins', array() );
		$network = is_array( $network ) ? array_keys( $network ) : array();
		$active  = array_unique( array_merge( $active, $network ) );
	}

	return count( $active );
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

	$cpt_args = array(
		'_builtin' => false,
		'show_ui'  => true,
	);

	foreach ( get_post_types( $cpt_args, 'names' ) as $post_type ) {
		$cpt_count = (int) wp_count_posts( $post_type )->publish;

		if ( $cpt_count > 0 ) {
			$counts[ 'edit.php?post_type=' . $post_type ] = $cpt_count;
		}
	}

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$active = blueworx_count_active_plugins();
	if ( $active > 0 ) {
		$counts['plugins.php'] = $active;
	}

	// Comments waiting on somebody, not comments in total: a badge is a job to
	// do. A site with four thousand approved comments and none pending has
	// nothing here that needs attention.
	$pending = (int) wp_count_comments()->moderated;
	if ( $pending > 0 ) {
		$counts['edit-comments.php'] = $pending;
	}

	// Updates available, from core's own count.
	if ( function_exists( 'wp_get_update_data' ) ) {
		$updates = wp_get_update_data();
		$total   = isset( $updates['counts']['total'] ) ? (int) $updates['counts']['total'] : 0;

		if ( $total > 0 ) {
			$counts['update-core.php'] = $total;
		}
	}

	return $counts;
}
