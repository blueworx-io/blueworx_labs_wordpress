<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Two things tests/support-access.spec.js cannot reach through the browser:
 *
 * - "ids": the IDs of a published post, a published page and an approved
 *   comment, so a test can address a single-item admin screen directly rather
 *   than scraping a list table for whatever happens to be first.
 * - "deny-pages" / "allow-pages": installs and removes a must-use plugin that
 *   adds "page" to blueworx_support_denied_post_types(). The real denied types
 *   are WooCommerce's, and WooCommerce is not installed on the harness, so this
 *   exercises the same code branch against a post type that does exist.
 *
 * Usage: php support-access-probe.php /absolute/path/to/wp-load.php <command>
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$command = isset( $argv[2] ) ? $argv[2] : '';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// WP_CLI, before wp-load: blueworx_intercept_requests()
// (includes/login-security.php) sees this CLI process as an anonymous visitor
// and, whenever Site Protection is on, wp_die()s it the moment WordPress
// finishes loading — silently, since the process still exits 0. That handler
// skips CLI contexts, so this declares the context this really is.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;

/**
 * Path of the must-use plugin this fixture installs.
 *
 * @return string Absolute path.
 */
function blueworx_probe_mu_file() {
	return WPMU_PLUGIN_DIR . '/blueworx-test-deny-pages.php';
}

switch ( $command ) {
	case 'ids':
		$post = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$page = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		if ( empty( $post ) ) {
			$post = array( get_post( wp_insert_post( array( 'post_title' => 'BW probe post', 'post_content' => 'probe', 'post_status' => 'publish', 'post_type' => 'post' ) ) ) );
		}

		if ( empty( $page ) ) {
			$page = array( get_post( wp_insert_post( array( 'post_title' => 'BW probe page', 'post_content' => 'probe', 'post_status' => 'publish', 'post_type' => 'page' ) ) ) );
		}

		$comments = get_comments(
			array(
				'number' => 1,
				'status' => 'approve',
			)
		);

		if ( empty( $comments ) ) {
			$comment_id = wp_insert_comment(
				array(
					'comment_post_ID'      => $post[0]->ID,
					'comment_author'       => 'BW Probe',
					'comment_author_email' => 'bw-probe@example.test',
					'comment_content'      => 'probe',
					'comment_approved'     => 1,
				)
			);
			$comments   = array( get_comment( $comment_id ) );
		}

		echo wp_json_encode(
			array(
				'postId'    => (int) $post[0]->ID,
				'pageId'    => (int) $page[0]->ID,
				'commentId' => (int) $comments[0]->comment_ID,
			)
		);
		break;

	case 'deny-pages':
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			wp_mkdir_p( WPMU_PLUGIN_DIR );
		}

		file_put_contents(
			blueworx_probe_mu_file(),
			"<?php\n"
			. "// Test fixture, installed and removed by tests/fixtures/support-access-probe.php.\n"
			. "add_filter( 'blueworx_support_denied_post_types', function ( \$types ) {\n"
			. "\t\$types[] = 'page';\n"
			. "\treturn \$types;\n"
			. "} );\n"
		);
		echo 'installed';
		break;

	case 'allow-pages':
		if ( is_file( blueworx_probe_mu_file() ) ) {
			unlink( blueworx_probe_mu_file() );
		}
		echo 'removed';
		break;

	default:
		fwrite( STDERR, "Unknown command: " . var_export( $command, true ) . "\n" );
		exit( 1 );
}
