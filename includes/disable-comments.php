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
 * Closes comments on the front end for all post types.
 *
 * @param bool $open Whether comments are open.
 * @return bool Always false.
 */
function blueworx_disable_comments_status( $open ) {
	return false;
}
add_filter( 'comments_open', 'blueworx_disable_comments_status', 20 );
add_filter( 'pings_open', 'blueworx_disable_comments_status', 20 );

/**
 * Returns an empty comments array to suppress existing comments from displaying.
 *
 * @param array $comments Existing comments.
 * @return array Always empty.
 */
function blueworx_disable_comments_hide_existing( $comments ) {
	$comments = array();
	return $comments;
}
add_filter( 'comments_array', 'blueworx_disable_comments_hide_existing', 10 );

/**
 * Removes comment-related items from the admin menu.
 *
 * @return void
 */
function blueworx_disable_comments_admin_menu() {
	remove_menu_page( 'edit-comments.php' );
}
add_action( 'admin_menu', 'blueworx_disable_comments_admin_menu' );

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
add_action( 'admin_init', 'blueworx_disable_comments_admin_redirect' );

/**
 * Removes comment-related dashboard widgets.
 *
 * @return void
 */
function blueworx_disable_comments_dashboard() {
	remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
}
add_action( 'admin_init', 'blueworx_disable_comments_dashboard' );

/**
 * Removes the Comments link from the admin bar.
 *
 * @param WP_Admin_Bar $wp_admin_bar The admin bar instance.
 * @return void
 */
function blueworx_disable_comments_admin_bar( $wp_admin_bar ) {
	$wp_admin_bar->remove_node( 'comments' );
}
add_action( 'admin_bar_menu', 'blueworx_disable_comments_admin_bar', 999 );

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
add_filter( 'manage_posts_columns', 'blueworx_disable_comments_remove_column' );
add_filter( 'manage_pages_columns', 'blueworx_disable_comments_remove_column' );

/**
 * Removes comment support from all registered post types.
 *
 * @return void
 */
function blueworx_disable_comments_post_types_support() {
	$post_types = get_post_types();
	foreach ( $post_types as $post_type ) {
		if ( post_type_supports( $post_type, 'comments' ) ) {
			remove_post_type_support( $post_type, 'comments' );
			remove_post_type_support( $post_type, 'trackbacks' );
		}
	}
}
add_action( 'admin_init', 'blueworx_disable_comments_post_types_support' );
