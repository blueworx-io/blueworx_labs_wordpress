<?php
/**
 * Any change purges the site's page cache, once per request, and is recorded.
 *
 * Run with: php tests/php/cache-purge-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable WordPress.DB.DirectDatabaseQuery

namespace {
require __DIR__ . '/stubs.php';

function blueworx_feature_enabled( $key ) {
	return true;
}
$GLOBALS['actions'] = array();
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['actions'][ $hook ] = $callback;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['actions'][ $hook ] = $callback;
}
function has_action( $hook ) {
	return false;
}
function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}
function wp_parse_url( $url, $component = -1 ) {
	return wp_parse_url_real( $url, $component );
}
function wp_parse_url_real( $url, $component ) {
	return -1 === $component ? parse_url( $url ) : parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
}
function wp_remote_request( $url, $args = array() ) {
	$GLOBALS['http'][] = array_merge( array( 'url' => $url ), $args );
	return array();
}
function is_multisite() {
	return false;
}
function wp_cache_flush() {
	$GLOBALS['calls'][] = 'wp_cache_flush';
}
function clean_post_cache( $post_id ) {
	$GLOBALS['calls'][] = 'clean_post_cache:' . $post_id;
}
function wp_cache_delete( $key, $group = '' ) {}
$GLOBALS['posts'] = array(
	7 => (object) array(
		'ID'          => 7,
		'post_type'   => 'elementor_library',
		'post_status' => 'publish',
		'post_parent' => 0,
	),
	8 => (object) array(
		'ID'          => 8,
		'post_type'   => 'page',
		'post_status' => 'draft',
		'post_parent' => 0,
	),
);
function get_post( $post_id ) {
	return isset( $GLOBALS['posts'][ $post_id ] ) ? $GLOBALS['posts'][ $post_id ] : null;
}
function get_post_status( $post_id ) {
	$post = get_post( $post_id );
	return $post ? $post->post_status : false;
}
function wp_is_post_autosave( $post_id ) {
	return false;
}
function wp_is_post_revision( $post_id ) {
	return false;
}
function get_permalink( $post_id ) {
	return 'https://example.test/p/' . $post_id;
}
function get_post_type_archive_link( $type ) {
	return false;
}
function current_user_can( $cap ) {
	return true;
}
function human_time_diff( $from, $to ) {
	return '1 min';
}

// Elementor stand-ins: what a save must and must not touch.
$GLOBALS['elementor'] = array();
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable Squiz.Commenting.ClassComment.Missing
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch
// phpcs:disable WordPress.Files.FileName
}

namespace Elementor\Core\Files\CSS {

class Post {
	public $id;
	public static function create( $post_id ) {
		$instance     = new self();
		$instance->id = $post_id;
		return $instance;
	}
	public function delete() {
		$GLOBALS['elementor'][] = 'delete:' . $this->id;
	}
}
}

namespace Elementor {

class Files_Manager {
	public function clear_cache() {
		$GLOBALS['elementor'][] = 'clear_all';
	}
}
class Plugin {
	public static $instance;
	public $files_manager;
}
Plugin::$instance                = new Plugin();
Plugin::$instance->files_manager = new Files_Manager();
}

namespace {
	require __DIR__ . '/../../includes/cache-refresh.php';

	echo "A site-wide purge\n";
	blueworx_purge_site_cache( 'menu change' );
	check( 'sends one request', count( $GLOBALS['http'] ), 1 );
	check( 'to Varnish, for the whole site', $GLOBALS['http'][0]['url'], 'https://example.test/.*' );
	check( 'as a PURGE', $GLOBALS['http'][0]['method'], 'PURGE' );
	check( 'by pattern', $GLOBALS['http'][0]['headers']['X-Purge-Method'], 'regex' );
	check( 'without waiting for the answer', $GLOBALS['http'][0]['blocking'], false );
	check( 'records why', blueworx_cache_last_purge()['reason'], 'menu change' );
	check( 'records when', blueworx_cache_last_purge()['time'] > 0, true );

	echo "\nOnce per request\n";
	blueworx_purge_site_cache( 'theme settings' );
	blueworx_purge_site_cache( 'page change' );
	check( 'no further requests', count( $GLOBALS['http'] ), 1 );
	check( 'the first reason stands', blueworx_cache_last_purge()['reason'], 'menu change' );

	echo "\nWhat triggers it\n";
	foreach ( array( 'save_post', 'wp_create_nav_menu', 'wp_update_nav_menu', 'wp_delete_nav_menu', 'wp_update_nav_menu_item', 'customize_save_after', 'switch_theme', 'upgrader_process_complete', 'activated_plugin', 'deactivated_plugin', 'trashed_post', 'untrashed_post', 'before_delete_post' ) as $hook ) {
		check( $hook, isset( $GLOBALS['actions'][ $hook ] ), true );
	}

	echo "\nSaving an Elementor template\n";
	$GLOBALS['http']      = array();
	$GLOBALS['elementor'] = array();
	blueworx_cache_reset_request_state();
	call_user_func( $GLOBALS['actions']['save_post'], 7, get_post( 7 ), true );
	check( 'purges the site', count( $GLOBALS['http'] ), 1 );
	check( 'names the change', blueworx_cache_last_purge()['reason'], 'elementor_library change' );
	check( 'throws away only that template\'s Elementor styles', $GLOBALS['elementor'], array( 'delete:7' ) );

	echo "\nSaving a draft\n";
	$GLOBALS['http'] = array();
	blueworx_cache_reset_request_state();
	call_user_func( $GLOBALS['actions']['save_post'], 8, get_post( 8 ), true );
	check( 'does nothing', count( $GLOBALS['http'] ), 0 );

	echo "\nThe manual button\n";
	$GLOBALS['http']      = array();
	$GLOBALS['elementor'] = array();
	blueworx_cache_reset_request_state();
	blueworx_refresh_manual_cache();
	check( 'still clears every Elementor file', $GLOBALS['elementor'], array( 'clear_all' ) );
	check( 'purges the site', count( $GLOBALS['http'] ), 1 );
	check( 'is recorded as manual', blueworx_cache_last_purge()['reason'], 'manual refresh' );

	finish();
}
