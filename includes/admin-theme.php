<?php
/**
 * BlueWorx admin & login re-skin (CSS-first).
 *
 * Restyles WordPress's own admin and login markup to the BlueWorx design system.
 * The only custom markup is the admin top bar / brand block and the Dashboard
 * hero-tiles widget below. Everything here is gated on the `admin_theme` feature
 * flag (default on) so it can be switched off from BlueWorx > Enhancements.
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
 * Gets the first character of a string, uppercased, multibyte-safe.
 *
 * @param string $text Source text.
 * @return string Single uppercase character, or an empty string.
 */
function blueworx_first_initial( $text ) {
	$text = trim( wp_strip_all_tags( (string) $text ) );

	if ( '' === $text ) {
		return '';
	}

	$first = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 1 ) : substr( $text, 0, 1 );

	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first ) : strtoupper( $first );
}

/**
 * Builds up to two initials from a display name.
 *
 * @param string $name Display name.
 * @return string Initials, e.g. "MW".
 */
function blueworx_user_initials( $name ) {
	$parts    = preg_split( '/\s+/', trim( (string) $name ) );
	$initials = '';

	if ( ! is_array( $parts ) ) {
		return '';
	}

	foreach ( $parts as $part ) {
		$initials .= blueworx_first_initial( $part );

		if ( strlen( $initials ) >= 2 ) {
			break;
		}
	}

	return $initials;
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

	// The brand mark shows the site's first initial; pass it to CSS as a variable.
	$initial = blueworx_first_initial( get_bloginfo( 'name' ) );

	if ( '' !== $initial ) {
		wp_add_inline_style(
			'blueworx-login-theme',
			'.login h1 a::before{content:"' . esc_attr( $initial ) . '";}'
		);
	}
}
add_action( 'login_enqueue_scripts', 'blueworx_enqueue_login_theme' );

/**
 * Points the login logo at the site instead of wordpress.org.
 *
 * @return string Home URL.
 */
function blueworx_login_header_url() {
	return home_url( '/' );
}

/**
 * Uses the site name as the login logo text (rendered as a wordmark by CSS).
 *
 * @return string Site name.
 */
function blueworx_login_header_text() {
	return get_bloginfo( 'name' );
}

if ( blueworx_admin_theme_enabled() ) {
	add_filter( 'login_headerurl', 'blueworx_login_header_url' );
	add_filter( 'login_headertext', 'blueworx_login_header_text' );
}

/**
 * Renders the BlueWorx brand block and admin top bar.
 *
 * Replaces the WordPress admin bar visually on desktop (the stylesheet hides
 * #wpadminbar at >=783px). The native admin bar is intentionally left rendered so
 * the responsive menu toggle it provides keeps working on small screens.
 *
 * @return void
 */
function blueworx_render_admin_topbar() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	$user       = wp_get_current_user();
	$site_name  = get_bloginfo( 'name' );
	$initial    = blueworx_first_initial( $site_name );
	$initials   = blueworx_user_initials( $user->display_name );
	$page_title = isset( $GLOBALS['title'] ) ? (string) $GLOBALS['title'] : '';
	?>
	<div class="bw-brand">
		<span class="bw-brand-mark" aria-hidden="true"><?php echo esc_html( $initial ); ?></span>
		<span class="bw-brand-text">
			<span class="bw-brand-name"><?php echo esc_html( $site_name ); ?></span>
			<span class="bw-brand-sub">wp-admin</span>
		</span>
	</div>
	<div class="bw-topbar">
		<div class="bw-topbar-title"><?php echo esc_html( $page_title ); ?></div>
		<div class="bw-topbar-actions">
			<a class="bw-topbar-site" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" width="15" height="15" aria-hidden="true" focusable="false">
					<path d="M14 4h6v6"></path><path d="M20 4l-9 9"></path>
					<path d="M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"></path>
				</svg>
				<?php esc_html_e( 'View Site', 'blueworx-labs-wordpress' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'blueworx-labs-wordpress' ); ?></span>
			</a>
			<details class="bw-user">
				<summary class="bw-user-summary">
					<span class="bw-user-avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
					<span class="bw-user-name"><?php echo esc_html( $user->display_name ); ?></span>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" aria-hidden="true" focusable="false">
						<path d="M6 9l6 6 6-6"></path>
					</svg>
				</summary>
				<div class="bw-user-menu">
					<a href="<?php echo esc_url( get_edit_profile_url() ); ?>"><?php esc_html_e( 'Edit Profile', 'blueworx-labs-wordpress' ); ?></a>
					<a href="<?php echo esc_url( wp_logout_url() ); ?>"><?php esc_html_e( 'Log Out', 'blueworx-labs-wordpress' ); ?></a>
				</div>
			</details>
		</div>
	</div>
	<?php
}
add_action( 'in_admin_header', 'blueworx_render_admin_topbar', 5 );

/**
 * Customises the Dashboard to the BlueWorx layout.
 *
 * Removes stock widgets that are not part of the BlueWorx mockup and registers a
 * hero-tiles widget with live counts. The native Activity, Quick Draft, and Site
 * Health widgets are kept and restyled by the stylesheet.
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

	/*
	 * Promote the hero tiles to the "high" priority group. do_meta_boxes() renders
	 * high -> sorted -> core, and re-sorts saved user layouts into "sorted", so a
	 * widget left in "core" lands below them (or gets lost) on any site where the
	 * dashboard has been rearranged. "high" keeps the tiles at the top for everyone.
	 */
	global $wp_meta_boxes;
	if ( isset( $wp_meta_boxes['dashboard']['normal']['core']['blueworx_dashboard_stats'] ) ) {
		$widget = $wp_meta_boxes['dashboard']['normal']['core']['blueworx_dashboard_stats'];

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Repositioning a dashboard widget requires updating the meta-box registry.
		unset( $wp_meta_boxes['dashboard']['normal']['core']['blueworx_dashboard_stats'] );

		if ( ! isset( $wp_meta_boxes['dashboard']['normal']['high'] ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Repositioning a dashboard widget requires updating the meta-box registry.
			$wp_meta_boxes['dashboard']['normal']['high'] = array();
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Repositioning a dashboard widget requires updating the meta-box registry.
		$wp_meta_boxes['dashboard']['normal']['high'] = array( 'blueworx_dashboard_stats' => $widget )
			+ $wp_meta_boxes['dashboard']['normal']['high'];
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
