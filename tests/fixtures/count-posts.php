<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Prints how many posts exist, of every status. A test that only checks the
 * response code of a refused write cannot tell a refusal from a write that
 * happened anyway and then errored; comparing this either side of the attempt
 * can.
 *
 * Every status is counted deliberately: the two writes this guards against —
 * the Duplicate row action and wp.newPost — both create a DRAFT, which never
 * appears in a default published listing.
 *
 * Usage: php count-posts.php /absolute/path/to/wp-load.php
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// See tests/fixtures/impostor-support-user.php for why the CLI context is
// declared before wp-load: Site Protection wp_die()s an anonymous request the
// moment WordPress finishes loading, and everything below would silently never
// run while the process still exited 0.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;

$posts = get_posts(
	array(
		'post_type'   => 'post',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);

echo count( $posts ), "\n";
