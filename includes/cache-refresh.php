<?php
/**
 * Cloudways, Breeze, Varnish, and Elementor cache refresh behavior.
 *
 * @package BlueWorxEnhancements
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
 * Handles the manual cache refresh button on Settings > BlueWorx.
 *
 * @return void
 */
function blueworx_handle_manual_cache_refresh() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-enhancements' ) );
	}

	check_admin_referer( 'blueworx_clear_cache_now' );
	blueworx_refresh_manual_cache();
	set_transient( 'blueworx_cache_refresh_notice', __( 'Cache refresh requested. Breeze full-cache clearing is used when available; otherwise the homepage and WordPress object cache are refreshed.', 'blueworx-enhancements' ), 30 );
	wp_safe_redirect( admin_url( 'options-general.php?page=blueworx-enhancements' ) );
	exit;
}
add_action( 'admin_post_blueworx_clear_cache_now', 'blueworx_handle_manual_cache_refresh' );

/**
 * Refreshes cache after a real post or page change.
 *
 * @param int     $post_id The post ID.
 * @param WP_Post $post    The post object.
 * @param bool    $update  Whether this is an update.
 * @return void
 */
function blueworx_refresh_cache_on_save( $post_id, $post, $update ) {
	if ( ! blueworx_should_refresh_post_cache( $post_id, $post ) ) {
		return;
	}

	blueworx_refresh_cache_for_post( $post_id );
}
add_action( 'save_post', 'blueworx_refresh_cache_on_save', 20, 3 );

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
add_action( 'trashed_post', 'blueworx_refresh_cache_on_trash_change' );
add_action( 'untrashed_post', 'blueworx_refresh_cache_on_trash_change' );
add_action( 'before_delete_post', 'blueworx_refresh_cache_on_trash_change' );

/**
 * Determines whether a post should trigger cache refresh.
 *
 * @param int          $post_id The post ID.
 * @param WP_Post|null $post    The post object.
 * @return bool True when cache should refresh.
 */
function blueworx_should_refresh_post_cache( $post_id, $post ) {
	if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
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
 * Refreshes the relevant cache for one post or page.
 *
 * @param int $post_id The post ID.
 * @return void
 */
function blueworx_refresh_cache_for_post( $post_id ) {
	clean_post_cache( $post_id );
	wp_cache_delete( $post_id, 'posts' );

	blueworx_refresh_elementor_cache();

	$urls = blueworx_get_cache_refresh_urls( $post_id );
	blueworx_send_varnish_purge_requests( $urls );

	blueworx_do_breeze_clear_post_cache( $post_id );
}

/**
 * Runs the best available manual cache refresh for admin use.
 *
 * @return void
 */
function blueworx_refresh_manual_cache() {
	blueworx_refresh_elementor_cache();
	wp_cache_flush();

	if ( blueworx_do_breeze_clear_all_cache() ) {
		return;
	}

	blueworx_send_varnish_purge_requests( array( home_url( '/' ) ) );
}

/**
 * Clears Elementor's generated CSS cache when Elementor is available.
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
 * Runs Breeze's post cache clear action when available.
 *
 * @param int $post_id The post ID.
 * @return bool True when the Breeze action ran.
 */
function blueworx_do_breeze_clear_post_cache( $post_id ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is a Breeze-owned hook.
	if ( ! has_action( 'breeze_clear_post_cache' ) ) {
		return false;
	}

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is a Breeze-owned hook.
	do_action( 'breeze_clear_post_cache', $post_id );
	return true;
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
 * Builds the page URLs that should refresh after a content change.
 *
 * @param int $post_id The post ID.
 * @return array List of URLs.
 */
function blueworx_get_cache_refresh_urls( $post_id ) {
	$post = get_post( $post_id );
	$urls = array( home_url( '/' ) );

	if ( ! $post ) {
		return $urls;
	}

	$permalink = get_permalink( $post_id );
	if ( $permalink ) {
		$urls[] = $permalink;
	}

	$archive_link = get_post_type_archive_link( $post->post_type );
	if ( $archive_link ) {
		$urls[] = $archive_link;
	}

	if ( 'post' === $post->post_type ) {
		$urls[] = get_author_posts_url( (int) $post->post_author );

		foreach ( wp_get_post_categories( $post_id ) as $term_id ) {
			$term_link = get_category_link( $term_id );
			if ( ! is_wp_error( $term_link ) ) {
				$urls[] = $term_link;
			}
		}

		foreach ( wp_get_post_tags( $post_id ) as $term ) {
			$term_link = get_tag_link( $term->term_id );
			if ( ! is_wp_error( $term_link ) ) {
				$urls[] = $term_link;
			}
		}
	}

	if ( 'page' === $post->post_type && $post->post_parent ) {
		$parent_link = get_permalink( $post->post_parent );
		if ( $parent_link ) {
			$urls[] = $parent_link;
		}
	}

	return array_values( array_unique( array_filter( $urls ) ) );
}

/**
 * Sends targeted PURGE requests for Cloudways/Varnish-style page cache.
 *
 * @param array $urls URLs to purge.
 * @return void
 */
function blueworx_send_varnish_purge_requests( $urls ) {
	foreach ( $urls as $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! $host ) {
			continue;
		}

		wp_remote_request(
			$url,
			array(
				'method'   => 'PURGE',
				'timeout'  => 2,
				'blocking' => false,
				'headers'  => array(
					'Host' => $host,
				),
			)
		);
	}
}
