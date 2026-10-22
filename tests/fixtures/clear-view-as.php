<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Ends every "view the admin as another role" preview, for every user.
 *
 * A preview is stored as a per-site user option and takes the settings screens
 * away, so a test that fails after entering one and before leaving it strands
 * the account inside that role. Every later test in the same shard then signs in to a
 * stripped-down admin and fails for a reason that has nothing to do with what
 * it was testing — one flake reads as fifty-seven. Clicking the way out cannot
 * be the only cleanup, because the way out is on the very screen that broke.
 *
 * This reaches the meta directly, so it works whatever state the browser is in.
 * tests/core-screen-controls.spec.js runs it before anything else in its
 * cleanup.
 *
 * Usage: php clear-view-as.php /absolute/path/to/wp-load.php
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;

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

$meta = defined( 'BLUEWORX_VIEW_AS_META' ) ? BLUEWORX_VIEW_AS_META : '_blueworx_view_as_role';

// The preview is saved per site, under the option name with that site's table
// prefix in front. Older versions saved it under the bare name, on the account,
// so that goes too. On a network every site's key is cleared.
$keys = array( $meta );

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		$keys[] = $wpdb->get_blog_prefix( $site_id ) . $meta;
	}
} else {
	$keys[] = $wpdb->get_blog_prefix() . $meta;
}

// delete_metadata() with $delete_all clears the key for every user in one go,
// so the fixture does not need to be told which account was stranded.
foreach ( array_unique( $keys ) as $key ) {
	delete_metadata( 'user', 0, $key, '', true );
}

echo "ok\n";
