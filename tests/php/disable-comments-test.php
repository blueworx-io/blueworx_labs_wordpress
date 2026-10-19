<?php
/**
 * Comments stay off site-wide, except on SureDash's community content.
 *
 * Run with: php tests/php/disable-comments-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

require __DIR__ . '/stubs.php';

function blueworx_feature_enabled( $key ) {
	return true;
}
$GLOBALS['hooks'] = array();
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ] = $args;
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ] = $args;
}
$GLOBALS['post_types'] = array(
	1 => 'post',
	2 => 'page',
	3 => 'portal',
	4 => 'community-post',
	5 => 'community-content',
);
function get_post_type( $post = null ) {
	return isset( $GLOBALS['post_types'][ $post ] ) ? $GLOBALS['post_types'][ $post ] : false;
}
$GLOBALS['supports'] = array();
function get_post_types() {
	return array( 'post', 'page', 'portal', 'community-post', 'community-content' );
}
function post_type_supports( $post_type, $feature ) {
	return true;
}
function remove_post_type_support( $post_type, $feature ) {
	$GLOBALS['supports'][] = $post_type . ':' . $feature;
}

require __DIR__ . '/../../includes/disable-comments.php';

echo "Is the comment open?\n";
check( 'a blog post: no, even when its own setting says yes', blueworx_disable_comments_status( true, 1 ), false );
check( 'a page: no', blueworx_disable_comments_status( true, 2 ), false );
check( 'a SureDash space: its own setting decides (on)', blueworx_disable_comments_status( true, 3 ), true );
check( 'a SureDash space: its own setting decides (off)', blueworx_disable_comments_status( false, 3 ), false );
check( 'a SureDash feed post: its own setting decides', blueworx_disable_comments_status( true, 4 ), true );
check( 'SureDash lesson content: its own setting decides', blueworx_disable_comments_status( true, 5 ), true );
check( 'no post at all: no', blueworx_disable_comments_status( true, 0 ), false );
check( 'the post id is asked for', $GLOBALS['hooks']['comments_open'], 2 );

echo "\nExisting comments\n";
$some = array( 'a', 'b' );
check( 'hidden on a blog post', blueworx_disable_comments_hide_existing( $some, 1 ), array() );
check( 'shown on a SureDash space', blueworx_disable_comments_hide_existing( $some, 3 ), $some );
check( 'the post id is asked for', $GLOBALS['hooks']['comments_array'], 2 );

echo "\nOther plugins can add their own\n";
$GLOBALS['filters']['blueworx_comments_allowed_post_types'] = static function ( $types ) {
	return array_merge( $types, array( 'page' ) );
};
check( 'a page opens once a plugin names it', blueworx_disable_comments_status( true, 2 ), true );
unset( $GLOBALS['filters']['blueworx_comments_allowed_post_types'] );

echo "\nPost type support\n";
blueworx_disable_comments_post_types_support();
check( 'removed from ordinary types only', $GLOBALS['supports'], array( 'post:comments', 'post:trackbacks', 'page:comments', 'page:trackbacks' ) );

finish();
