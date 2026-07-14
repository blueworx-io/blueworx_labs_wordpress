<?php
/**
 * BlueWorx admin & login re-skin (CSS-first).
 *
 * Restyles WordPress's own admin and login markup to the BlueWorx design system.
 * No frameworks and no replacement markup — the only custom markup is the
 * Dashboard hero-tiles widget below. Everything here is gated on the
 * `admin_theme` feature flag (default on) so it can be switched off from
 * BlueWorx > Enhancements.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the BlueWorx admin theme is active.
 *
 * @return bool True when the admin_theme feature is enabled.
 */
function blueworx_admin_theme_enabled() {
	return blueworx_feature_enabled( 'admin_theme' );
}

/**
 * Enqueues the admin re-skin on every admin screen.
 *
 * @return void
 */
function blueworx_enqueue_admin_theme() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	wp_enqueue_style(
		'blueworx-admin-fonts',
		BLUEWORX_LABS_URL . 'assets/css/blueworx-fonts.css',
		array(),
		blueworx_get_admin_asset_version( 'assets/css/blueworx-fonts.css' )
	);

	wp_enqueue_style(
		'blueworx-admin-theme',
		BLUEWORX_LABS_URL . 'assets/css/admin-theme.css',
		array( 'blueworx-admin-fonts' ),
		blueworx_get_admin_asset_version( 'assets/css/admin-theme.css' )
	);
}
add_action( 'admin_enqueue_scripts', 'blueworx_enqueue_admin_theme' );

/**
 * Enqueues the login re-skin.
 *
 * @return void
 */
function blueworx_enqueue_login_theme() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	wp_enqueue_style(
		'blueworx-admin-fonts',
		BLUEWORX_LABS_URL . 'assets/css/blueworx-fonts.css',
		array(),
		blueworx_get_admin_asset_version( 'assets/css/blueworx-fonts.css' )
	);

	wp_enqueue_style(
		'blueworx-login-theme',
		BLUEWORX_LABS_URL . 'assets/css/login-theme.css',
		array( 'blueworx-admin-fonts' ),
		blueworx_get_admin_asset_version( 'assets/css/login-theme.css' )
	);
}
add_action( 'login_enqueue_scripts', 'blueworx_enqueue_login_theme' );

/**
 * Customises the Dashboard to the BlueWorx layout.
 *
 * Removes stock widgets that are not part of the BlueWorx mockup, registers a
 * hero-tiles widget with live counts, and moves it to the top of the normal
 * column. The native Activity, Quick Draft, and Site Health widgets are kept and
 * restyled by the stylesheet.
 *
 * @return void
 */
function blueworx_customise_dashboard() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	// Remove widgets not present in the BlueWorx dashboard mockup.
	remove_action( 'welcome_panel', 'wp_welcome_panel' );
	remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );    // WordPress Events & News.
	remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' ); // At a Glance (replaced by hero tiles).

	wp_add_dashboard_widget(
		'blueworx_dashboard_stats',
		__( 'At a Glance', 'blueworx-labs-wordpress' ),
		'blueworx_render_dashboard_stats'
	);

	// Move the hero tiles to the top of the normal column. Reordering the
	// dashboard meta-box registry is the documented way to reposition a widget.
	global $wp_meta_boxes;
	$core = isset( $wp_meta_boxes['dashboard']['normal']['core'] )
		? $wp_meta_boxes['dashboard']['normal']['core']
		: array();
	if ( isset( $core['blueworx_dashboard_stats'] ) ) {
		$widget = $core['blueworx_dashboard_stats'];
		unset( $core['blueworx_dashboard_stats'] );
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Repositioning a dashboard widget requires updating the meta-box registry.
		$wp_meta_boxes['dashboard']['normal']['core'] = array( 'blueworx_dashboard_stats' => $widget ) + $core;
	}
}
add_action( 'wp_dashboard_setup', 'blueworx_customise_dashboard', 20 );

/**
 * Renders the four hero stat tiles with live counts.
 *
 * @return void
 */
function blueworx_render_dashboard_stats() {
	$posts       = (int) wp_count_posts( 'post' )->publish;
	$pages       = (int) wp_count_posts( 'page' )->publish;
	$comments    = (int) wp_count_comments()->approved;
	$attachments = (int) array_sum( (array) wp_count_attachments() );

	$tiles = array(
		array(
			'count' => $posts,
			'label' => __( 'Posts', 'blueworx-labs-wordpress' ),
			'url'   => admin_url( 'edit.php' ),
		),
		array(
			'count' => $pages,
			'label' => __( 'Pages', 'blueworx-labs-wordpress' ),
			'url'   => admin_url( 'edit.php?post_type=page' ),
		),
		array(
			'count' => $comments,
			'label' => __( 'Comments', 'blueworx-labs-wordpress' ),
			'url'   => admin_url( 'edit-comments.php' ),
		),
		array(
			'count' => $attachments,
			'label' => __( 'Media Items', 'blueworx-labs-wordpress' ),
			'url'   => admin_url( 'upload.php' ),
		),
	);

	echo '<div class="bw-stat-grid">';
	foreach ( $tiles as $tile ) {
		printf(
			'<a class="bw-stat-card" href="%1$s"><div class="bw-stat-num">%2$s</div><div class="bw-stat-label">%3$s</div></a>',
			esc_url( $tile['url'] ),
			esc_html( number_format_i18n( $tile['count'] ) ),
			esc_html( $tile['label'] )
		);
	}
	echo '</div>';
}
