<?php
/**
 * Comment disabling behavior.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post types that keep their own comment setting while comments are off.
 *
 * SureDash runs its community on these: spaces, feed posts and lesson content.
 * Its own "Allow Comments" switch decides there.
 *
 * @return string[] Post type names.
 */
function blueworx_comments_allowed_post_types() {
	/**
	 * Filters the post types left alone by "Comments disabled".
	 *
	 * @param string[] $post_types Post type names.
	 */
	return (array) apply_filters( 'blueworx_comments_allowed_post_types', array( 'portal', 'community-post', 'community-content' ) );
}

/**
 * Whether a post keeps its own comment setting.
 *
 * @param int|WP_Post|null $post Post ID or object.
 * @return bool
 */
function blueworx_comments_allowed_for( $post ) {
	$post_type = $post ? get_post_type( $post ) : false;
	return $post_type && in_array( $post_type, blueworx_comments_allowed_post_types(), true );
}

/**
 * Closes comments on the front end, except where they are left alone.
 *
 * @param bool             $open    Whether comments are open.
 * @param int|WP_Post|null $post_id Post ID or object.
 * @return bool
 */
function blueworx_disable_comments_status( $open, $post_id = null ) {
	return blueworx_comments_allowed_for( $post_id ) ? $open : false;
}

/**
 * Closes trackbacks and pingbacks everywhere.
 *
 * @param bool $open Whether pings are open.
 * @return bool Always false.
 */
function blueworx_disable_comments_pings( $open ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $open is required by the "pings_open" filter callback signature.
	return false;
}
if ( blueworx_feature_enabled( 'comments' ) ) {
	add_filter( 'comments_open', 'blueworx_disable_comments_status', 20, 2 );
	add_filter( 'pings_open', 'blueworx_disable_comments_pings', 20 );
}

/**
 * Hides existing comments, except where comments are left alone.
 *
 * @param array $comments Existing comments.
 * @param int   $post_id  Post ID.
 * @return array
 */
function blueworx_disable_comments_hide_existing( $comments, $post_id = 0 ) {
	return blueworx_comments_allowed_for( $post_id ) ? $comments : array();
}
if ( blueworx_feature_enabled( 'comments' ) ) {
	add_filter( 'comments_array', 'blueworx_disable_comments_hide_existing', 10, 2 );
}

/**
 * Removes comment-related items from the admin menu.
 *
 * @return void
 */
function blueworx_disable_comments_admin_menu() {
	remove_menu_page( 'edit-comments.php' );
}
if ( blueworx_feature_enabled( 'comments' ) ) {
	add_action( 'admin_menu', 'blueworx_disable_comments_admin_menu' );
}

/**
 * Redirects any direct attempt to access the comments admin page.
 *
 * @return void
 */
function blueworx_disable_comments_admin_redirect() {
	global $pagenow;
	if ( 'edit-comments.php' === $pagenow ) {
		wp_safe_redirect( admin_url() );
		exit;
	}
}
if ( blueworx_feature_enabled( 'comments' ) ) {
	add_action( 'admin_init', 'blueworx_disable_comments_admin_redirect' );
}

/**
 * Removes comment-related dashboard widgets.
 *
 * @return void
 */
function blueworx_disable_comments_dashboard() {
	remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
}
if ( blueworx_feature_enabled( 'comments' ) ) {
	add_action( 'admin_init', 'blueworx_disable_comments_dashboard' );
}

/**
 * Removes the Comments link from the admin bar.
 *
 * @param WP_Admin_Bar $wp_admin_bar The admin bar instance.
 * @return void
 */
function blueworx_disable_comments_admin_bar( $wp_admin_bar ) {
	$wp_admin_bar->remove_node( 'comments' );
}
if ( blueworx_feature_enabled( 'comments' ) ) {
	add_action( 'admin_bar_menu', 'blueworx_disable_comments_admin_bar', 999 );
}

/**
 * Removes the Comments column from post/page list tables.
 *
 * @param array $columns Existing columns.
 * @return array Filtered columns.
 */
function blueworx_disable_comments_remove_column( $columns ) {
	unset( $columns['comments'] );
	return $columns;
}
if ( blueworx_feature_enabled( 'comments' ) ) {
	add_filter( 'manage_posts_columns', 'blueworx_disable_comments_remove_column' );
	add_filter( 'manage_pages_columns', 'blueworx_disable_comments_remove_column' );
}

/**
 * Removes comment support from every post type except those left alone.
 *
 * @return void
 */
function blueworx_disable_comments_post_types_support() {
	$post_types = array_diff( get_post_types(), blueworx_comments_allowed_post_types() );
	foreach ( $post_types as $post_type ) {
		if ( post_type_supports( $post_type, 'comments' ) ) {
			remove_post_type_support( $post_type, 'comments' );
			remove_post_type_support( $post_type, 'trackbacks' );
		}
	}
}
if ( blueworx_feature_enabled( 'comments' ) ) {
	add_action( 'admin_init', 'blueworx_disable_comments_post_types_support' );
}
