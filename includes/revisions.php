<?php
/**
 * Revision limit.
 *
 * Replaces the Admin and Site Enhancements `enable_revisions_control` module.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the number of revisions kept per item.
 *
 * Zero means revisions are switched off entirely; -1, WordPress's own "keep
 * everything", is not offered — a site that wants unlimited revisions switches
 * the feature off instead, which leaves core in charge and is the same thing
 * without a second way to express it.
 *
 * @return int Revisions to keep.
 */
function blueworx_revisions_to_keep() {
	$limit = get_option( 'blueworx_revisions_limit', 20 );
	$limit = is_numeric( $limit ) ? (int) $limit : 20;

	return max( 0, min( 500, $limit ) );
}

/**
 * Applies the limit.
 *
 * WP_POST_REVISIONS in wp-config.php is left to win. It is a deliberate act by
 * whoever maintains the server, often for a reason this plugin cannot see, and
 * a settings page quietly overriding a file the site owner cannot edit from
 * wp-admin is the wrong way round.
 *
 * @param int     $num  Number of revisions core intends to keep.
 * @param WP_Post $post Post being saved.
 * @return int Number of revisions to keep.
 */
function blueworx_revisions_filter( $num, $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $post is part of the filter signature; the limit is site-wide.
	if ( defined( 'WP_POST_REVISIONS' ) && true !== WP_POST_REVISIONS ) {
		return $num;
	}

	return blueworx_revisions_to_keep();
}

if ( blueworx_feature_enabled( 'revisions' ) ) {
	add_filter( 'wp_revisions_to_keep', 'blueworx_revisions_filter', 10, 2 );
}
