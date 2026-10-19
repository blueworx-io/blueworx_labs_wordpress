<?php
/**
 * Cloudways Varnish, Breeze, and Elementor cache refresh behavior.
 *
 * One rule: any change purges the whole site's page cache, once per request.
 *
 * The targeted version this replaced purged only the saved page, the homepage
 * and its listing pages — and only when a page or post was saved. A changed
 * menu, header, footer, Elementor template or theme setting purged nothing, so
 * the old copy of every page stayed in Varnish (or Breeze) until it expired on
 * its own, usually a day later. People worked around it with ?random=123 on
 * the URL. A full purge is cheap on Varnish (one request, a pattern ban) and
 * cannot miss a page that showed the changed thing.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks whether the Breeze cache plugin appears to be active.
 *
 * @return bool True when Breeze is detected.
 */
function blueworx_is_breeze_active() {
	$active_plugins = (array) get_option( 'active_plugins', array() );

	if ( in_array( 'breeze/breeze.php', $active_plugins, true ) ) {
		return true;
	}

	if ( is_multisite() ) {
		$network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
		if ( isset( $network_plugins['breeze/breeze.php'] ) ) {
			return true;
		}
	}

	return defined( 'BREEZE_VERSION' ) || blueworx_has_breeze_clear_all_cache_action();
}

/**
 * Option holding the last purge: when it happened and what caused it.
 */
const BLUEWORX_CACHE_LAST_PURGE_OPTION = 'blueworx_cache_last_purge';

/**
 * Option holding the time of the last manual refresh, as a Unix timestamp.
 */
const BLUEWORX_CACHE_REFRESHED_OPTION = 'blueworx_cache_last_refreshed';

/**
 * Handles the manual cache refresh button on BlueWorx > Cache.
 *
 * @return void
 */
function blueworx_handle_manual_cache_refresh() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_clear_cache_now' );
	blueworx_refresh_manual_cache();

	// When it last happened is the one thing somebody comes to this screen to
	// find out, and nothing was recording it.
	update_option( BLUEWORX_CACHE_REFRESHED_OPTION, time(), false );

	set_transient( 'blueworx_cache_refresh_notice', __( 'Cache refresh requested. Every cached page is cleared, along with Elementor\'s generated files and the WordPress object cache.', 'blueworx-labs-wordpress' ), 30 );
	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-cache' ) );
	exit;
}

/**
 * When the cache was last refreshed by hand, as a human-readable phrase.
 *
 * @return string Localised "x ago" phrase, or an empty string when it never has.
 */
function blueworx_cache_last_refreshed_label() {
	$when = (int) get_option( BLUEWORX_CACHE_REFRESHED_OPTION, 0 );

	if ( $when <= 0 ) {
		return '';
	}

	return sprintf(
		/* translators: %s: how long ago, e.g. "5 mins". */
		__( '%s ago', 'blueworx-labs-wordpress' ),
		human_time_diff( $when, time() )
	);
}
if ( blueworx_feature_enabled( 'cache_manual' ) ) {
	add_action( 'admin_post_blueworx_clear_cache_now', 'blueworx_handle_manual_cache_refresh' );
}

/**
 * The last purge, however it was triggered.
 *
 * @return array|null `time` (Unix timestamp) and `reason`, or null when there has never been one.
 */
function blueworx_cache_last_purge() {
	$last = get_option( BLUEWORX_CACHE_LAST_PURGE_OPTION, null );

	if ( ! is_array( $last ) || empty( $last['time'] ) ) {
		return null;
	}

	return array(
		'time'   => (int) $last['time'],
		'reason' => isset( $last['reason'] ) ? (string) $last['reason'] : '',
	);
}

/**
 * Whether this request has already purged, optionally marking it as having done so.
 *
 * Elementor fires save_post several times for one click of Update, and a
 * plugin update fires upgrader_process_complete once per plugin. Each would
 * purge again, to no effect but the requests. The first purge in a request is
 * the only one that does anything.
 *
 * @param bool|null $set True to mark this request as purged; null to only read.
 * @return bool True once a purge has run in this request.
 */
function blueworx_cache_request_state( $set = null ) {
	static $purged = false;

	if ( null !== $set ) {
		$purged = (bool) $set;
	}

	return $purged;
}

/**
 * Forgets that this request has purged, so the next purge runs again.
 *
 * For tests, which exercise several requests' worth of saves in one process.
 *
 * @return void
 */
function blueworx_cache_reset_request_state() {
	blueworx_cache_request_state( false );
}

/**
 * Purges every cached page on the site, once per request, and records why.
 *
 * @param string $reason What changed, e.g. "menu change". Shown on the Cache screen.
 * @return void
 */
function blueworx_purge_site_cache( $reason ) {
	if ( blueworx_cache_request_state() ) {
		return;
	}

	blueworx_cache_request_state( true );

	blueworx_send_varnish_purge_all();
	blueworx_do_breeze_clear_all_cache();

	update_option(
		BLUEWORX_CACHE_LAST_PURGE_OPTION,
		array(
			'time'   => time(),
			'reason' => (string) $reason,
		),
		false
	);
}

/**
 * Whether a saved post is the kind of change a visitor could see.
 *
 * @param int          $post_id The post ID.
 * @param WP_Post|null $post    The post object.
 * @return bool True when the save should purge.
 */
function blueworx_should_refresh_post_cache( $post_id, $post ) {
	if ( ! $post || empty( $post->post_type ) ) {
		return false;
	}

	// Menus purge through their own hook; the rest never appear on a page.
	if ( in_array( $post->post_type, array( 'revision', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'user_request' ), true ) ) {
		return false;
	}

	if (
		( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
		( defined( 'DOING_CRON' ) && DOING_CRON ) ||
		( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) ||
		wp_is_post_autosave( $post_id ) ||
		wp_is_post_revision( $post_id )
	) {
		return false;
	}

	return in_array( get_post_status( $post_id ), array( 'publish', 'trash' ), true );
}

/**
 * Refreshes cache after a real change to any kind of post.
 *
 * @param int     $post_id The post ID.
 * @param WP_Post $post    The post object.
 * @param bool    $update  Whether this is an update.
 * @return void
 */
function blueworx_refresh_cache_on_save( $post_id, $post, $update ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $update is required by the WordPress "save_post" action's callback signature (registered with 3 args); it is unused here since the cache refresh applies on every save.
	if ( ! blueworx_should_refresh_post_cache( $post_id, $post ) ) {
		return;
	}

	blueworx_refresh_cache_for_post( $post_id );
}
if ( blueworx_feature_enabled( 'cache_auto' ) ) {
	add_action( 'save_post', 'blueworx_refresh_cache_on_save', 20, 3 );
}

/**
 * Refreshes cache when content is moved to or from trash.
 *
 * @param int $post_id The post ID.
 * @return void
 */
function blueworx_refresh_cache_on_trash_change( $post_id ) {
	$post = get_post( $post_id );

	if ( ! blueworx_should_refresh_post_cache( $post_id, $post ) ) {
		return;
	}

	blueworx_refresh_cache_for_post( $post_id );
}
if ( blueworx_feature_enabled( 'cache_auto' ) ) {
	add_action( 'trashed_post', 'blueworx_refresh_cache_on_trash_change' );
	add_action( 'untrashed_post', 'blueworx_refresh_cache_on_trash_change' );
	add_action( 'before_delete_post', 'blueworx_refresh_cache_on_trash_change' );
}

/**
 * Refreshes cache after a change that is not a post: a menu, the Customiser,
 * the theme, a plugin.
 *
 * @param string $reason What changed.
 * @return void
 */
function blueworx_refresh_cache_on_site_change( $reason ) {
	blueworx_purge_site_cache( $reason );
}
if ( blueworx_feature_enabled( 'cache_auto' ) ) {
	// Creating, editing and deleting a menu are three different hooks, and an
	// item saved through the REST API fires a fourth without the menu's own.
	foreach ( array( 'wp_create_nav_menu', 'wp_update_nav_menu', 'wp_delete_nav_menu', 'wp_update_nav_menu_item' ) as $blueworx_menu_hook ) {
		add_action(
			$blueworx_menu_hook,
			static function () {
				blueworx_refresh_cache_on_site_change( 'menu change' );
			}
		);
	}
	unset( $blueworx_menu_hook );
	add_action(
		'customize_save_after',
		static function () {
			blueworx_refresh_cache_on_site_change( 'theme settings' );
		}
	);
	add_action(
		'switch_theme',
		static function () {
			blueworx_refresh_cache_on_site_change( 'theme change' );
		}
	);
	add_action(
		'upgrader_process_complete',
		static function () {
			blueworx_refresh_cache_on_site_change( 'update' );
		}
	);
	add_action(
		'activated_plugin',
		static function () {
			blueworx_refresh_cache_on_site_change( 'plugin change' );
		}
	);
	add_action(
		'deactivated_plugin',
		static function () {
			blueworx_refresh_cache_on_site_change( 'plugin change' );
		}
	);
}

/**
 * Refreshes the relevant cache for one changed post of any type.
 *
 * @param int $post_id The post ID.
 * @return void
 */
function blueworx_refresh_cache_for_post( $post_id ) {
	clean_post_cache( $post_id );
	wp_cache_delete( $post_id, 'posts' );

	blueworx_refresh_elementor_post_cache( $post_id );

	$post = get_post( $post_id );

	blueworx_purge_site_cache(
		sprintf(
			/* translators: %s: post type, e.g. "page". */
			__( '%s change', 'blueworx-labs-wordpress' ),
			$post && ! empty( $post->post_type ) ? $post->post_type : 'content'
		)
	);
}

/**
 * Runs the best available manual cache refresh for admin use.
 *
 * @return void
 */
function blueworx_refresh_manual_cache() {
	blueworx_refresh_elementor_cache();
	wp_cache_flush();

	blueworx_purge_site_cache( 'manual refresh' );
}

/**
 * Throws away the generated Elementor styles of one post.
 *
 * Only that post's. This used to clear every post's generated CSS on every
 * save, so one edit made every page on the site rebuild its styles on the
 * next visit — on a shared server that was most of the cost of a save.
 * Elementor regenerates the one file the next time the post is viewed.
 *
 * @param int $post_id The post ID.
 * @return void
 */
function blueworx_refresh_elementor_post_cache( $post_id ) {
	if ( ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
		return;
	}

	$css = \Elementor\Core\Files\CSS\Post::create( (int) $post_id );

	if ( is_callable( array( $css, 'delete' ) ) ) {
		$css->delete();
	}
}

/**
 * Clears Elementor's generated CSS cache when Elementor is available.
 *
 * The site-wide clear. Only the manual button uses it now.
 *
 * @return void
 */
function blueworx_refresh_elementor_cache() {
	if (
		class_exists( '\Elementor\Plugin' ) &&
		isset( \Elementor\Plugin::$instance->files_manager ) &&
		is_callable( array( \Elementor\Plugin::$instance->files_manager, 'clear_cache' ) )
	) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
}

/**
 * Checks whether Breeze has a full-cache clear action registered.
 *
 * @return bool True when Breeze has the action available.
 */
function blueworx_has_breeze_clear_all_cache_action() {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is a Breeze-owned hook.
	return (bool) has_action( 'breeze_clear_all_cache' );
}

/**
 * Runs Breeze's full cache clear action when available.
 *
 * @return bool True when the Breeze action ran.
 */
function blueworx_do_breeze_clear_all_cache() {
	if ( ! blueworx_has_breeze_clear_all_cache_action() ) {
		return false;
	}

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is a Breeze-owned hook.
	do_action( 'breeze_clear_all_cache' );
	return true;
}

/**
 * Asks Varnish to drop every cached page for this site.
 *
 * One PURGE for the pattern `/.*`, which Cloudways' Varnish treats as a ban on
 * everything under the host. Non-blocking: the save must not wait on the cache,
 * and a cache that is not there to answer is not an error.
 *
 * @return void
 */
function blueworx_send_varnish_purge_all() {
	$url  = home_url( '/.*' );
	$host = wp_parse_url( $url, PHP_URL_HOST );

	if ( ! $host ) {
		return;
	}

	wp_remote_request(
		$url,
		array(
			'method'   => 'PURGE',
			'timeout'  => 2,
			'blocking' => false,
			'headers'  => array(
				'Host'           => $host,
				'X-Purge-Method' => 'regex',
			),
		)
	);
}
