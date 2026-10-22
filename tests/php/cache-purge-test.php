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

// Sites that have switched the automatic refresh off, by blog ID.
$GLOBALS['cache_auto_off'] = array();
function blueworx_feature_enabled( $key ) {
	return ! ( 'cache_auto' === $key && in_array( $GLOBALS['blog_id'], $GLOBALS['cache_auto_off'], true ) );
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
// A folder-style network of three sites; single site unless a check says otherwise.
$GLOBALS['multisite']  = false;
$GLOBALS['blog_id']    = 1;
$GLOBALS['blog_stack'] = array();
$GLOBALS['site_paths'] = array(
	1 => '',
	2 => '/two',
	3 => '/three',
);
function home_url( $path = '' ) {
	return 'https://example.test' . $GLOBALS['site_paths'][ $GLOBALS['blog_id'] ] . $path;
}
function get_current_blog_id() {
	return $GLOBALS['blog_id'];
}
function get_sites( $args = array() ) {
	$GLOBALS['get_sites_args'] = $args;
	return array_map( 'strval', array_keys( $GLOBALS['site_paths'] ) );
}
function switch_to_blog( $blog_id ) {
	$GLOBALS['blog_stack'][] = $GLOBALS['blog_id'];
	$GLOBALS['blog_id']      = (int) $blog_id;
	return true;
}
function restore_current_blog() {
	$GLOBALS['blog_id'] = array_pop( $GLOBALS['blog_stack'] );
	return true;
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
	return $GLOBALS['multisite'];
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
function request_urls( $requests ) {
	return array_column( $requests, 'url' );
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

	echo "\nAn update on a single site\n";
	$GLOBALS['http'] = array();
	blueworx_cache_reset_request_state();
	call_user_func( $GLOBALS['actions']['upgrader_process_complete'], null, array( 'action' => 'update', 'type' => 'plugin' ) );
	check( 'purges the site once', request_urls( $GLOBALS['http'] ), array( 'https://example.test/.*' ) );

	echo "\nOn a network: a plugin update\n";
	$GLOBALS['multisite'] = true;
	$GLOBALS['blog_id']   = 2;
	$GLOBALS['http']      = array();
	blueworx_cache_reset_request_state();
	call_user_func( $GLOBALS['actions']['upgrader_process_complete'], null, array( 'action' => 'update', 'type' => 'plugin' ) );
	check( 'purges every site on the network', request_urls( $GLOBALS['http'] ), array( 'https://example.test/.*', 'https://example.test/two/.*', 'https://example.test/three/.*' ) );
	check( 'asks for every site, not the first hundred', $GLOBALS['get_sites_args']['number'], 0 );
	check( 'goes back to the site it started on', get_current_blog_id(), 2 );
	check( 'leaves nothing switched', $GLOBALS['blog_stack'], array() );

	echo "\nOn a network: a second update in the same request\n";
	$GLOBALS['http'] = array();
	call_user_func( $GLOBALS['actions']['upgrader_process_complete'], null, array( 'action' => 'update', 'type' => 'theme' ) );
	check( 'purges nothing more', count( $GLOBALS['http'] ), 0 );

	echo "\nOn a network: a site that switched automatic refresh off\n";
	$GLOBALS['http']           = array();
	$GLOBALS['cache_auto_off'] = array( 3 );
	blueworx_cache_reset_request_state();
	call_user_func( $GLOBALS['actions']['upgrader_process_complete'], null, array( 'action' => 'update', 'type' => 'core' ) );
	check( 'is left alone', request_urls( $GLOBALS['http'] ), array( 'https://example.test/.*', 'https://example.test/two/.*' ) );
	$GLOBALS['cache_auto_off'] = array();

	echo "\nOn a network: installing a plugin\n";
	$GLOBALS['http'] = array();
	blueworx_cache_reset_request_state();
	call_user_func( $GLOBALS['actions']['upgrader_process_complete'], null, array( 'action' => 'install', 'type' => 'plugin' ) );
	check( 'purges only this site', request_urls( $GLOBALS['http'] ), array( 'https://example.test/two/.*' ) );

	echo "\nOn a network: activating a plugin for every site\n";
	$GLOBALS['http'] = array();
	blueworx_cache_reset_request_state();
	call_user_func( $GLOBALS['actions']['activated_plugin'], 'x/x.php', true );
	check( 'purges every site', count( $GLOBALS['http'] ), 3 );

	echo "\nOn a network: activating a plugin on one site\n";
	$GLOBALS['http'] = array();
	blueworx_cache_reset_request_state();
	call_user_func( $GLOBALS['actions']['activated_plugin'], 'x/x.php', false );
	check( 'purges only this site', request_urls( $GLOBALS['http'] ), array( 'https://example.test/two/.*' ) );

	echo "\nOn a network: deactivating a plugin for every site\n";
	$GLOBALS['http'] = array();
	blueworx_cache_reset_request_state();
	call_user_func( $GLOBALS['actions']['deactivated_plugin'], 'x/x.php', true );
	check( 'purges every site', count( $GLOBALS['http'] ), 3 );

	echo "\nOn a network: the manual button\n";
	$GLOBALS['http']  = array();
	$GLOBALS['calls'] = array();
	blueworx_cache_reset_request_state();
	blueworx_refresh_manual_cache();
	check( 'purges only this site\'s pages', request_urls( $GLOBALS['http'] ), array( 'https://example.test/two/.*' ) );
	check( 'still empties the object cache, which cannot be emptied for one site', in_array( 'wp_cache_flush', $GLOBALS['calls'], true ), true );
	check( 'says the object cache was emptied for the whole network', false !== strpos( blueworx_cache_refresh_notice_text(), 'every site on the network' ), true );

	echo "\nBack on a single site\n";
	$GLOBALS['multisite'] = false;
	$GLOBALS['blog_id']   = 1;
	check( 'the notice is unchanged', blueworx_cache_refresh_notice_text(), 'Cache refresh requested. Every cached page is cleared, along with Elementor\'s generated files and the WordPress object cache.' );

	finish();
}
