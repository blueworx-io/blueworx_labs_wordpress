<?php
/**
 * BlueWorx admin screens.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the BlueWorx admin menu.
 *
 * @return void
 */
function blueworx_register_settings_page() {
	add_menu_page(
		esc_html__( 'Enhancements', 'blueworx-labs-wordpress' ),
		esc_html__( 'BlueWorx', 'blueworx-labs-wordpress' ),
		'manage_options',
		'blueworx-labs-wordpress',
		'blueworx_render_enhancements_page',
		'dashicons-schedule',
		58
	);

	add_submenu_page(
		'blueworx-labs-wordpress',
		esc_html__( 'Enhancements', 'blueworx-labs-wordpress' ),
		esc_html__( 'Enhancements', 'blueworx-labs-wordpress' ),
		'manage_options',
		'blueworx-labs-wordpress',
		'blueworx_render_enhancements_page'
	);

	if ( blueworx_feature_enabled( 'menu_editor' ) ) {
		add_submenu_page(
			'blueworx-labs-wordpress',
			esc_html__( 'Edit Menu', 'blueworx-labs-wordpress' ),
			esc_html__( 'Edit Menu', 'blueworx-labs-wordpress' ),
			'manage_options',
			'blueworx-edit-menu',
			'blueworx_render_edit_menu_page'
		);
	}

	if ( blueworx_feature_enabled( 'cache_manual' ) ) {
		add_submenu_page(
			'blueworx-labs-wordpress',
			esc_html__( 'Cache', 'blueworx-labs-wordpress' ),
			esc_html__( 'Cache', 'blueworx-labs-wordpress' ),
			'manage_options',
			'blueworx-cache',
			'blueworx_render_cache_page'
		);
	}
}
add_action( 'admin_menu', 'blueworx_register_settings_page' );

/**
 * Saves the admin menu settings from BlueWorx > Edit Menu.
 *
 * @return void
 */
function blueworx_save_edit_menu_page() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_save_admin_menu_order' );

	$raw_order  = isset( $_POST['blueworx_admin_menu_order'] ) ? (array) wp_unslash( $_POST['blueworx_admin_menu_order'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with sanitize_text_field.
	$raw_hidden = isset( $_POST['blueworx_hidden_admin_menu_items'] ) ? (array) wp_unslash( $_POST['blueworx_hidden_admin_menu_items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
	$raw_groups = isset( $_POST['blueworx_admin_menu_groups'] ) ? (array) wp_unslash( $_POST['blueworx_admin_menu_groups'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
	$order      = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $raw_order ) ) ) );
	$locked     = blueworx_get_locked_admin_menu_items();
	$hidden     = array_values( array_diff( array_unique( array_filter( array_map( 'sanitize_text_field', $raw_hidden ) ) ), $locked ) );

	// Only accept groups this build knows about; anything else is dropped rather
	// than stored, so a stale or forged POST cannot create a phantom group.
	// "hidden" is not a group — it is expressed by the hidden-items option — so
	// it is skipped here rather than written as one.
	$known  = blueworx_get_admin_menu_groups();
	$groups = array();

	foreach ( $raw_groups as $slug => $group ) {
		$slug  = sanitize_text_field( (string) $slug );
		$group = sanitize_key( (string) $group );

		if ( '' === $slug || 'hidden' === $group ) {
			continue;
		}

		if ( isset( $known[ $group ] ) ) {
			$groups[ $slug ] = $group;
		}
	}

	update_option( 'blueworx_admin_menu_order', $order );
	update_option( 'blueworx_hidden_admin_menu_items', array_values( array_unique( $hidden ) ) );
	update_option( 'blueworx_admin_menu_groups', $groups );
	update_option( 'blueworx_admin_menu_customized', '1' );
	set_transient( 'blueworx_admin_menu_order_notice', __( 'Menu settings saved.', 'blueworx-labs-wordpress' ), 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-edit-menu' ) );
	exit;
}
if ( blueworx_feature_enabled( 'menu_editor' ) ) {
	add_action( 'admin_post_blueworx_save_admin_menu_order', 'blueworx_save_edit_menu_page' );
}

/**
 * Gets all roles that can be selected for site protection.
 *
 * @return array Role labels keyed by role slug.
 */
function blueworx_get_site_protection_role_choices() {
	$choices  = array();
	$wp_roles = wp_roles();

	foreach ( $wp_roles->roles as $role_slug => $role ) {
		$choices[ $role_slug ] = translate_user_role( $role['name'] );
	}

	natcasesort( $choices );

	return $choices;
}

/**
 * Gets a saved site protection toggle.
 *
 * @param string $area Protected area.
 * @return bool True when enabled.
 */
function blueworx_site_protection_enabled( $area ) {
	return '1' === get_option( 'blueworx_' . $area . '_protection_enabled', '0' );
}

/**
 * Gets saved site protection roles.
 *
 * @param string $area Protected area.
 * @return array Role slugs.
 */
function blueworx_get_site_protection_roles( $area ) {
	$roles   = get_option( 'blueworx_' . $area . '_protection_roles', array() );
	$choices = blueworx_get_site_protection_role_choices();

	if ( ! is_array( $roles ) ) {
		return array();
	}

	return array_values( array_intersect( array_unique( array_map( 'sanitize_key', $roles ) ), array_keys( $choices ) ) );
}

/**
 * Saves all feature toggles and their detail settings from the settings page.
 *
 * @return void
 */
function blueworx_save_feature_settings() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_save_feature_settings' );

	$posted = isset( $_POST['blueworx_feature'] ) ? (array) wp_unslash( $_POST['blueworx_feature'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	foreach ( array_keys( blueworx_get_feature_definitions() ) as $key ) {
		update_option( 'blueworx_feature_' . $key, isset( $posted[ $key ] ) ? '1' : '0' );
	}

	// Login detail: editable slug.
	$raw_slug = isset( $_POST['blueworx_login_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['blueworx_login_slug'] ) ) : '';
	update_option( 'blueworx_login_slug', blueworx_sanitize_login_slug( $raw_slug ) );

	// Site Protection detail: per-area enable + roles.
	$choices = blueworx_get_site_protection_role_choices();
	foreach ( array( 'frontend', 'backend' ) as $area ) {
		$enabled = isset( $_POST[ 'blueworx_' . $area . '_protection_enabled' ] ) ? '1' : '0';
		$roles   = isset( $_POST[ 'blueworx_' . $area . '_protection_roles' ] ) ? (array) wp_unslash( $_POST[ 'blueworx_' . $area . '_protection_roles' ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$roles   = array_values( array_intersect( array_unique( array_map( 'sanitize_key', $roles ) ), array_keys( $choices ) ) );

		update_option( 'blueworx_' . $area . '_protection_enabled', $enabled );
		update_option( 'blueworx_' . $area . '_protection_roles', $roles, false );
	}

	// Application Passwords detail.
	update_option( 'blueworx_show_application_passwords', isset( $_POST['blueworx_show_application_passwords'] ) ? '1' : '0' );

	// Translation detail: languages, position, label and exclusions.
	blueworx_translate_save_settings( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() ran at the top of this handler; the callee sanitizes every field.

	// Single sign-on detail: provider, credentials and provisioning.
	blueworx_sso_save_settings( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() ran at the top of this handler; the callee sanitizes every field.

	blueworx_save_login_session_settings();
	blueworx_save_admin_bar_settings();
	blueworx_save_dashboard_widget_settings();
	blueworx_save_robots_txt_settings();
	blueworx_save_media_tools_settings();
	blueworx_save_content_tools_settings();
	blueworx_save_revisions_settings();

	set_transient( 'blueworx_labs_notice', __( 'Settings saved.', 'blueworx-labs-wordpress' ), 30 );

	// Back to the section they saved from. The screen honours ?section= on load,
	// and an unknown value is ignored there rather than showing an empty panel,
	// so this only ever has to be a well-formed key.
	$redirect = admin_url( 'admin.php?page=blueworx-labs-wordpress' );
	$section  = isset( $_POST['blueworx_section'] ) ? sanitize_key( wp_unslash( $_POST['blueworx_section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() ran at the top of this handler.

	if ( '' !== $section ) {
		$redirect = add_query_arg( 'section', $section, $redirect );
	}

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_blueworx_save_feature_settings', 'blueworx_save_feature_settings' );

/**
 * Saves the login session length.
 *
 * Every function below is called from blueworx_save_feature_settings() after it
 * has run check_admin_referer(), so the nonce is verified before any of them
 * reads $_POST.
 *
 * @return void
 */
function blueworx_save_login_session_settings() {
	$choice = isset( $_POST['blueworx_login_session'] ) ? sanitize_key( wp_unslash( $_POST['blueworx_login_session'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.

	if ( isset( blueworx_login_session_choices()[ $choice ] ) ) {
		update_option( 'blueworx_login_session', $choice );
	}
}

/**
 * Saves the toolbar cleanup settings.
 *
 * @return void
 */
function blueworx_save_admin_bar_settings() {
	$nodes = isset( $_POST['blueworx_admin_bar_removed_nodes'] ) ? (array) wp_unslash( $_POST['blueworx_admin_bar_removed_nodes'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by the calling handler; sanitized and allowlisted below.
	$nodes = array_values( array_intersect( array_keys( blueworx_admin_bar_removable_nodes() ), array_map( 'sanitize_key', $nodes ) ) );

	update_option( 'blueworx_admin_bar_removed_nodes', $nodes );
	update_option( 'blueworx_admin_bar_hide_howdy', isset( $_POST['blueworx_admin_bar_hide_howdy'] ) ? '1' : '0' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.
	update_option( 'blueworx_admin_bar_hide_help', isset( $_POST['blueworx_admin_bar_hide_help'] ) ? '1' : '0' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.

	$mode = isset( $_POST['blueworx_admin_bar_front_end_mode'] ) ? sanitize_key( wp_unslash( $_POST['blueworx_admin_bar_front_end_mode'] ) ) : 'off'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.

	update_option( 'blueworx_admin_bar_front_end_mode', in_array( $mode, array( 'off', 'all_but_admin', 'roles' ), true ) ? $mode : 'off' );

	$roles = isset( $_POST['blueworx_admin_bar_front_end_roles'] ) ? (array) wp_unslash( $_POST['blueworx_admin_bar_front_end_roles'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by the calling handler; sanitized and allowlisted below.
	$roles = array_values( array_intersect( array_keys( blueworx_get_site_protection_role_choices() ), array_map( 'sanitize_key', $roles ) ) );

	update_option( 'blueworx_admin_bar_front_end_roles', $roles );
}

/**
 * Saves the dashboard tidy-up settings.
 *
 * @return void
 */
function blueworx_save_dashboard_widget_settings() {
	$widgets = isset( $_POST['blueworx_dashboard_removed_widgets'] ) ? (array) wp_unslash( $_POST['blueworx_dashboard_removed_widgets'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by the calling handler; sanitized and allowlisted below.
	$widgets = array_values( array_intersect( array_keys( blueworx_dashboard_removable_widgets() ), array_map( 'sanitize_key', $widgets ) ) );

	update_option( 'blueworx_dashboard_removed_widgets', $widgets );
}

/**
 * Saves the robots.txt content.
 *
 * @return void
 */
function blueworx_save_robots_txt_settings() {
	if ( ! isset( $_POST['blueworx_robots_txt'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.
		return;
	}

	// Not sanitize_textarea_field: it collapses the line structure this file is
	// made of. blueworx_robots_txt_sanitize() strips tags and control characters
	// line by line instead.
	$raw = (string) wp_unslash( $_POST['blueworx_robots_txt'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by the calling handler; sanitized on the next line.

	update_option( 'blueworx_robots_txt', blueworx_robots_txt_sanitize( $raw ) );
}

/**
 * Saves the media tool settings.
 *
 * @return void
 */
function blueworx_save_media_tools_settings() {
	update_option( 'blueworx_media_replace_enabled', isset( $_POST['blueworx_media_replace_enabled'] ) ? '1' : '0' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.
	update_option( 'blueworx_media_max_dimensions_enabled', isset( $_POST['blueworx_media_max_dimensions_enabled'] ) ? '1' : '0' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.

	$width  = isset( $_POST['blueworx_media_max_width'] ) ? absint( wp_unslash( $_POST['blueworx_media_max_width'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.
	$height = isset( $_POST['blueworx_media_max_height'] ) ? absint( wp_unslash( $_POST['blueworx_media_max_height'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.

	update_option( 'blueworx_media_max_width', $width > 0 ? min( $width, 10000 ) : 1920 );
	update_option( 'blueworx_media_max_height', $height > 0 ? min( $height, 10000 ) : 1920 );

	$roles = isset( $_POST['blueworx_media_svg_roles'] ) ? (array) wp_unslash( $_POST['blueworx_media_svg_roles'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by the calling handler; sanitized and allowlisted below.
	$roles = array_values( array_intersect( array_keys( blueworx_get_site_protection_role_choices() ), array_map( 'sanitize_key', $roles ) ) );

	update_option( 'blueworx_media_svg_roles', $roles );
}

/**
 * Saves the content tool settings.
 *
 * @return void
 */
function blueworx_save_content_tools_settings() {
	update_option( 'blueworx_duplicate_enabled', isset( $_POST['blueworx_duplicate_enabled'] ) ? '1' : '0' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.
	update_option( 'blueworx_external_permalinks_enabled', isset( $_POST['blueworx_external_permalinks_enabled'] ) ? '1' : '0' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.
}

/**
 * Saves the revision limit.
 *
 * @return void
 */
function blueworx_save_revisions_settings() {
	if ( ! isset( $_POST['blueworx_revisions_limit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.
		return;
	}

	$limit = absint( wp_unslash( $_POST['blueworx_revisions_limit'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.

	update_option( 'blueworx_revisions_limit', min( 500, $limit ) );
}

/**
 * Renders the BlueWorx feature settings page.
 *
 * @return void
 */
function blueworx_render_enhancements_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-labs-wordpress' ) );
	}

	$sections  = blueworx_get_feature_sections();
	$features  = blueworx_get_feature_definitions();
	$notice    = get_transient( 'blueworx_labs_notice' );
	$login_url = home_url( '/' . blueworx_login_slug() . '/' );

	if ( $notice ) {
		delete_transient( 'blueworx_labs_notice' );
	}

	// How many of each section's functions are on, for the section nav.
	$counts = array();

	foreach ( $features as $key => $feature ) {
		$section = $feature['section'];

		if ( ! isset( $counts[ $section ] ) ) {
			$counts[ $section ] = array(
				'on'    => 0,
				'total' => 0,
			);
		}

		++$counts[ $section ]['total'];

		if ( blueworx_feature_enabled( $key ) ) {
			++$counts[ $section ]['on'];
		}
	}

	$header_actions = blueworx_ds_button(
		array(
			'label' => __( 'Read the guides', 'blueworx-labs-wordpress' ),
			'icon'  => 'lightbulb',
			'href'  => admin_url( 'admin.php?page=blueworx-guides' ),
		)
	);

	echo wp_kses(
		blueworx_ds_screen_open(
			blueworx_ds_page_header(
				array(
					'title'   => __( 'Enhancements', 'blueworx-labs-wordpress' ),
					'lede'    => __( 'Functions you can switch on one at a time. Nothing changes on the site until you save.', 'blueworx-labs-wordpress' ),
					'actions' => $header_actions,
				)
			)
		),
		blueworx_ds_allowed_html()
	);

	if ( $notice ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone' => 'success',
					'text' => $notice,
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	if ( blueworx_feature_enabled( 'login' ) ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone'  => 'accent',
					'title' => __( 'The way in to this site', 'blueworx-labs-wordpress' ),
					'html'  => sprintf(
						'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
						esc_url( $login_url ),
						esc_html( $login_url )
					),
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	$sections_with_features = array();

	foreach ( $sections as $section_id => $section_label ) {
		if ( isset( $counts[ $section_id ] ) ) {
			$sections_with_features[ $section_id ] = $section_label;
		}
	}

	$first_section = (string) array_key_first( $sections_with_features );

	// Section nav. Every panel stays in the form whichever section is showing —
	// an unchecked checkbox is indistinguishable from a missing one on save, so
	// rendering only the visible section would switch the rest of the plugin off
	// the first time anybody pressed Save.
	$nav = '';

	foreach ( $sections_with_features as $section_id => $section_label ) {
		$nav .= sprintf(
			'<button type="button" class="bw-secnav__item%1$s" data-blueworx-section="%2$s"%3$s><span>%4$s</span><span class="bw-secnav__meta">%5$s</span></button>',
			$section_id === $first_section ? ' is-active' : '',
			esc_attr( $section_id ),
			$section_id === $first_section ? ' aria-current="true"' : '',
			esc_html( $section_label ),
			esc_html(
				sprintf(
					/* translators: 1: number of functions switched on, 2: number of functions in the section. */
					__( '%1$d / %2$d', 'blueworx-labs-wordpress' ),
					$counts[ $section_id ]['on'],
					$counts[ $section_id ]['total']
				)
			)
		);
	}

	// Which section is open travels with the save, so the redirect can put the
	// operator back where they were. Without it every save throws them to the
	// first section, which is the whole thing the section nav exists to avoid.
	printf(
		'<form method="post" action="%1$s" class="bw-page__form"><input type="hidden" name="action" value="blueworx_save_feature_settings" /><input type="hidden" name="blueworx_section" value="%3$s" data-blueworx-section-field />%2$s',
		esc_url( admin_url( 'admin-post.php' ) ),
		wp_nonce_field( 'blueworx_save_feature_settings', '_wpnonce', true, false ),
		esc_attr( $first_section )
	);

	printf(
		'<div class="bw-page__body"><nav class="bw-secnav" aria-label="%1$s">%2$s</nav><div class="bw-panels">',
		esc_attr__( 'Sections', 'blueworx-labs-wordpress' ),
		wp_kses( $nav, blueworx_ds_allowed_html() )
	);

	foreach ( $sections_with_features as $section_id => $section_label ) {
		$rows = '';

		foreach ( $features as $key => $feature ) {
			if ( $feature['section'] !== $section_id ) {
				continue;
			}

			$enabled = blueworx_feature_enabled( $key );

			$switch = sprintf(
				'<label class="bw-switch"><input type="checkbox" role="switch" name="%1$s" value="1"%2$s class="blueworx-feature-toggle" data-blueworx-feature="%3$s" /><span class="bw-switch__track"><span class="bw-switch__thumb"></span></span><span class="bw-switch__label">%4$s<small>%5$s</small></span></label>',
				esc_attr( 'blueworx_feature[' . $key . ']' ),
				checked( $enabled, true, false ),
				esc_attr( $key ),
				esc_html( $feature['label'] ),
				esc_html( $feature['description'] )
			);

			$detail = '';

			if ( ! empty( $feature['detail'] ) ) {
				ob_start();
				blueworx_render_feature_detail( $key );
				$detail_html = (string) ob_get_clean();

				// NOT filtered through wp_kses(). The detail panels are this
				// plugin's own markup and escape their own values as they render;
				// running them through an allow-list here would mean every
				// attribute any panel ever uses has to be listed there too, and
				// wp_kses() drops what it does not recognise in silence. The
				// support panel alone relies on formaction, formmethod and two
				// data attributes — a stripped formaction posts the button to the
				// wrong handler, and nothing about the page looks wrong.
				$detail = sprintf(
					'<div class="blueworx-feature-detail bw-card bw-card--sunken" data-blueworx-detail="%1$s"%2$s><div class="bw-card__body">%3$s</div></div>',
					esc_attr( $key ),
					$enabled ? '' : ' hidden',
					$detail_html
				);
			}

			// One .bw-field per function: the switch on its own line with its
			// settings beneath it, rather than a 200px label column that squeezes
			// every function's name into four words a line.
			$rows .= '<div class="bw-field">' . wp_kses( $switch, blueworx_ds_allowed_html() ) . $detail . '</div>';
		}

		printf(
			'<section class="bw-card bw-settingscard" data-blueworx-panel="%1$s"%2$s><div class="bw-card__head"><div class="bw-card__titles"><p class="bw-card__eyebrow">%3$s</p><h2 class="bw-card__title">%4$s</h2></div><div class="bw-card__actions">%5$s</div></div><div class="bw-card__body bw-settingscard__body"><div class="bw-fields bw-fields--single">%6$s</div></div></section>',
			esc_attr( $section_id ),
			$section_id === $first_section ? '' : ' hidden',
			esc_html__( 'Section', 'blueworx-labs-wordpress' ),
			esc_html( $section_label ),
			wp_kses(
				blueworx_ds_badge(
					sprintf(
						/* translators: %d: number of functions switched on in this section. */
						_n( '%d on', '%d on', $counts[ $section_id ]['on'], 'blueworx-labs-wordpress' ),
						$counts[ $section_id ]['on']
					),
					$counts[ $section_id ]['on'] > 0 ? 'success' : 'neutral'
				),
				blueworx_ds_allowed_html()
			),
			// Already filtered piece by piece above: the parts this file composes
			// go through wp_kses(), the detail panels render their own escaped
			// markup. See the note on $detail.
			$rows // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	echo '</div></div>';

	// The save bar sticks to the bottom of the screen rather than sitting under
	// whichever section happens to be open, so Save is in the same place however
	// far down a long section you are.
	printf(
		'<div class="bw-savebar"><p class="bw-savebar__hint">%1$s</p>%2$s</div>',
		esc_html__( 'Nothing changes on the site until you save.', 'blueworx-labs-wordpress' ),
		wp_kses(
			blueworx_ds_button(
				array(
					'label'   => __( 'Save Changes', 'blueworx-labs-wordpress' ),
					'variant' => 'primary',
					'type'    => 'submit',
					'attrs'   => array( 'name' => 'submit' ),
				)
			),
			blueworx_ds_allowed_html()
		)
	);

	echo '</form>';

	echo wp_kses( blueworx_ds_screen_close(), blueworx_ds_allowed_html() );
}

/**
 * Renders the nested detail controls for a feature.
 *
 * @param string $key Feature key.
 * @return void
 */
function blueworx_render_feature_detail( $key ) {
	if ( 'login' === $key ) {
		?>
		<p>
			<label for="blueworx_login_slug"><?php esc_html_e( 'Login slug', 'blueworx-labs-wordpress' ); ?></label><br />
			<input type="text" id="blueworx_login_slug" name="blueworx_login_slug" class="regular-text" value="<?php echo esc_attr( blueworx_login_slug() ); ?>" />
			<span class="description"><?php echo esc_html( home_url( '/' ) ); ?>&hellip;</span>
		</p>
		<?php
		return;
	}

	if ( 'site_protection' === $key ) {
		$role_choices = blueworx_get_site_protection_role_choices();
		foreach ( array(
			'frontend' => __( 'Frontend protection', 'blueworx-labs-wordpress' ),
			'backend'  => __( 'Backend protection', 'blueworx-labs-wordpress' ),
		) as $area => $label ) :
			$selected_roles = blueworx_get_site_protection_roles( $area );
			?>
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( 'blueworx_' . $area . '_protection_enabled' ); ?>" value="1" <?php checked( blueworx_site_protection_enabled( $area ) ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			</p>
			<p>
				<select name="<?php echo esc_attr( 'blueworx_' . $area . '_protection_roles[]' ); ?>" multiple size="4" aria-label="<?php echo esc_attr( $label ); ?>">
					<?php foreach ( $role_choices as $role_slug => $role_label ) : ?>
						<option value="<?php echo esc_attr( $role_slug ); ?>" <?php selected( in_array( $role_slug, $selected_roles, true ) ); ?>><?php echo esc_html( $role_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php
		endforeach;
		return;
	}

	if ( 'sso' === $key ) {
		blueworx_sso_render_detail();
		return;
	}

	if ( 'support_access' === $key ) {
		blueworx_support_render_panel();
		return;
	}

	if ( 'application_passwords' === $key ) {
		?>
		<p>
			<label>
				<input type="checkbox" name="blueworx_show_application_passwords" value="1" <?php checked( blueworx_show_application_passwords_for_admins() ); ?> />
				<?php esc_html_e( 'Show Application Passwords for admins', 'blueworx-labs-wordpress' ); ?>
			</label>
		</p>
		<?php
		return;
	}

	if ( 'translate' === $key ) {
		blueworx_translate_render_detail();
		return;
	}

	if ( 'cache_manual' === $key ) {
		?>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=blueworx-cache' ) ); ?>"><?php esc_html_e( 'Open Cache page', 'blueworx-labs-wordpress' ); ?></a></p>
		<?php
		return;
	}

	if ( 'menu_editor' === $key ) {
		?>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=blueworx-edit-menu' ) ); ?>"><?php esc_html_e( 'Open Edit Menu page', 'blueworx-labs-wordpress' ); ?></a></p>
		<?php
		return;
	}

	if ( 'login_session' === $key ) {
		$current = blueworx_login_session_choice();
		?>
		<p>
			<label for="blueworx_login_session"><?php esc_html_e( 'Stay logged in for', 'blueworx-labs-wordpress' ); ?></label><br />
			<select id="blueworx_login_session" name="blueworx_login_session">
				<?php foreach ( blueworx_login_session_choices() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'Applies from the next time somebody signs in. Anyone already signed in keeps their current session until it runs out.', 'blueworx-labs-wordpress' ); ?></p>
		<?php
		return;
	}

	if ( 'admin_bar' === $key ) {
		$removed = blueworx_admin_bar_removed_nodes();
		$mode    = blueworx_admin_bar_front_end_mode();
		$roles   = blueworx_admin_bar_front_end_hidden_roles();
		?>
		<fieldset>
			<legend><strong><?php esc_html_e( 'Take these out of the toolbar', 'blueworx-labs-wordpress' ); ?></strong></legend>
			<?php foreach ( blueworx_admin_bar_removable_nodes() as $node => $label ) : ?>
				<p><label>
					<input type="checkbox" name="blueworx_admin_bar_removed_nodes[]" value="<?php echo esc_attr( $node ); ?>" <?php checked( in_array( $node, $removed, true ) ); ?> />
					<?php echo esc_html( $label ); ?>
				</label></p>
			<?php endforeach; ?>
			<p><label>
				<input type="checkbox" name="blueworx_admin_bar_hide_howdy" value="1" <?php checked( blueworx_admin_bar_hide_howdy() ); ?> />
				<?php esc_html_e( 'Drop the "Howdy," greeting', 'blueworx-labs-wordpress' ); ?>
			</label></p>
			<p><label>
				<input type="checkbox" name="blueworx_admin_bar_hide_help" value="1" <?php checked( blueworx_admin_bar_hide_help() ); ?> />
				<?php esc_html_e( 'Remove the Help tab and drawer', 'blueworx-labs-wordpress' ); ?>
			</label></p>
		</fieldset>
		<fieldset style="margin-top:12px;">
			<legend><strong><?php esc_html_e( 'Hide the toolbar on the front of the site', 'blueworx-labs-wordpress' ); ?></strong></legend>
			<p><label>
				<input type="radio" name="blueworx_admin_bar_front_end_mode" value="off" <?php checked( $mode, 'off' ); ?> />
				<?php esc_html_e( 'Show it to everyone signed in (WordPress default)', 'blueworx-labs-wordpress' ); ?>
			</label></p>
			<p><label>
				<input type="radio" name="blueworx_admin_bar_front_end_mode" value="all_but_admin" <?php checked( $mode, 'all_but_admin' ); ?> />
				<?php esc_html_e( 'Hide it for everyone except administrators', 'blueworx-labs-wordpress' ); ?>
			</label></p>
			<p><label>
				<input type="radio" name="blueworx_admin_bar_front_end_mode" value="roles" <?php checked( $mode, 'roles' ); ?> />
				<?php esc_html_e( 'Hide it for these roles only', 'blueworx-labs-wordpress' ); ?>
			</label></p>
			<p>
				<select name="blueworx_admin_bar_front_end_roles[]" multiple size="4" aria-label="<?php esc_attr_e( 'Roles the toolbar is hidden for', 'blueworx-labs-wordpress' ); ?>">
					<?php foreach ( blueworx_get_site_protection_role_choices() as $role_slug => $role_label ) : ?>
						<option value="<?php echo esc_attr( $role_slug ); ?>" <?php selected( in_array( $role_slug, $roles, true ) ); ?>><?php echo esc_html( $role_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="description"><?php esc_html_e( 'A BlueWorx support session always keeps its toolbar, so the read-only indicator and the button that ends the session stay visible.', 'blueworx-labs-wordpress' ); ?></p>
		</fieldset>
		<?php
		return;
	}

	if ( 'dashboard_widgets' === $key ) {
		$removed = blueworx_dashboard_removed_widgets();
		?>
		<fieldset>
			<legend><strong><?php esc_html_e( 'Remove these dashboard panels', 'blueworx-labs-wordpress' ); ?></strong></legend>
			<?php foreach ( blueworx_dashboard_removable_widgets() as $widget => $label ) : ?>
				<p><label>
					<input type="checkbox" name="blueworx_dashboard_removed_widgets[]" value="<?php echo esc_attr( $widget ); ?>" <?php checked( in_array( $widget, $removed, true ) ); ?> />
					<?php echo esc_html( $label ); ?>
				</label></p>
			<?php endforeach; ?>
		</fieldset>
		<p class="description"><?php esc_html_e( 'A panel removed here is gone for everyone, not just hidden behind Screen Options. Panels belonging to a plugin that is switched off are ignored.', 'blueworx-labs-wordpress' ); ?></p>
		<?php
		return;
	}

	if ( 'robots_txt' === $key ) {
		?>
		<?php if ( blueworx_robots_txt_file_exists() ) : ?>
			<p class="notice notice-warning" style="padding:8px;">
				<?php esc_html_e( 'There is a real robots.txt file on the server. WordPress serves that file instead, so anything saved here will have no effect until it is removed.', 'blueworx-labs-wordpress' ); ?>
			</p>
		<?php endif; ?>
		<?php if ( ! get_option( 'blog_public' ) ) : ?>
			<p class="notice notice-warning" style="padding:8px;">
				<?php esc_html_e( 'This site is set to discourage search engines, under Settings > Reading. What you save here is served anyway, so if the site is meant to stay out of search results, say so in the box below as well.', 'blueworx-labs-wordpress' ); ?>
			</p>
		<?php endif; ?>
		<p>
			<label for="blueworx_robots_txt"><?php esc_html_e( 'robots.txt content', 'blueworx-labs-wordpress' ); ?></label><br />
			<textarea id="blueworx_robots_txt" name="blueworx_robots_txt" rows="10" class="large-text code"><?php echo esc_textarea( blueworx_robots_txt_content() ); ?></textarea>
		</p>
		<p class="description">
			<?php esc_html_e( 'Replaces the file WordPress generates. If an SEO plugin also writes robots rules, check the result at /robots.txt after saving — whichever runs last wins.', 'blueworx-labs-wordpress' ); ?>
			<a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View the live file', 'blueworx-labs-wordpress' ); ?></a>
		</p>
		<?php
		return;
	}

	if ( 'media_tools' === $key ) {
		list( $max_width, $max_height ) = blueworx_media_max_dimensions();
		$svg_roles                      = blueworx_media_svg_roles();
		?>
		<p><label>
			<input type="checkbox" name="blueworx_media_replace_enabled" value="1" <?php checked( blueworx_media_replace_enabled() ); ?> />
			<?php esc_html_e( 'Allow a file to be replaced in place', 'blueworx-labs-wordpress' ); ?>
		</label></p>
		<p><label>
			<input type="checkbox" name="blueworx_media_max_dimensions_enabled" value="1" <?php checked( blueworx_media_max_dimensions_enabled() ); ?> />
			<?php esc_html_e( 'Shrink oversized images as they are uploaded', 'blueworx-labs-wordpress' ); ?>
		</label></p>
		<p>
			<label for="blueworx_media_max_width"><?php esc_html_e( 'Largest width', 'blueworx-labs-wordpress' ); ?></label>
			<input type="number" min="1" max="10000" id="blueworx_media_max_width" name="blueworx_media_max_width" value="<?php echo esc_attr( (string) $max_width ); ?>" class="small-text" />
			<label for="blueworx_media_max_height"><?php esc_html_e( 'Largest height', 'blueworx-labs-wordpress' ); ?></label>
			<input type="number" min="1" max="10000" id="blueworx_media_max_height" name="blueworx_media_max_height" value="<?php echo esc_attr( (string) $max_height ); ?>" class="small-text" />
			<span class="description"><?php esc_html_e( 'pixels', 'blueworx-labs-wordpress' ); ?></span>
		</p>
		<p><strong><?php esc_html_e( 'Allow SVG uploads for these roles', 'blueworx-labs-wordpress' ); ?></strong></p>
		<p>
			<select name="blueworx_media_svg_roles[]" multiple size="4" aria-label="<?php esc_attr_e( 'Roles allowed to upload SVG files', 'blueworx-labs-wordpress' ); ?>">
				<?php foreach ( blueworx_get_site_protection_role_choices() as $role_slug => $role_label ) : ?>
					<option value="<?php echo esc_attr( $role_slug ); ?>" <?php selected( in_array( $role_slug, $svg_roles, true ) ); ?>><?php echo esc_html( $role_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'Select nothing to switch SVG uploads off, which is the default. An SVG is a document that can carry code, so every one uploaded is stripped of anything that could run before it is stored. Only grant this to roles you trust with the whole site.', 'blueworx-labs-wordpress' ); ?></p>
		<?php
		return;
	}

	if ( 'content_tools' === $key ) {
		?>
		<p><label>
			<input type="checkbox" name="blueworx_duplicate_enabled" value="1" <?php checked( blueworx_duplicate_enabled() ); ?> />
			<?php esc_html_e( 'Show a Duplicate link on pages, posts and custom items', 'blueworx-labs-wordpress' ); ?>
		</label></p>
		<p><label>
			<input type="checkbox" name="blueworx_external_permalinks_enabled" value="1" <?php checked( blueworx_external_permalinks_enabled() ); ?> />
			<?php esc_html_e( 'Let an item point at an address on another site', 'blueworx-labs-wordpress' ); ?>
		</label></p>
		<p class="description"><?php esc_html_e( 'The second one adds a "Link to another site" box to the editor. Anyone clicking that item goes straight to the address you put there. Leave it off unless you need it.', 'blueworx-labs-wordpress' ); ?></p>
		<?php
		return;
	}

	if ( 'revisions' === $key ) {
		?>
		<p>
			<label for="blueworx_revisions_limit"><?php esc_html_e( 'Saved versions to keep per item', 'blueworx-labs-wordpress' ); ?></label>
			<input type="number" min="0" max="500" id="blueworx_revisions_limit" name="blueworx_revisions_limit" value="<?php echo esc_attr( (string) blueworx_revisions_to_keep() ); ?>" class="small-text" />
		</p>
		<p class="description"><?php esc_html_e( 'Applies to versions saved from now on — versions already stored are left alone. Set it to 0 to stop keeping versions at all.', 'blueworx-labs-wordpress' ); ?></p>
		<?php
	}
}

/**
 * Renders the Cache page.
 *
 * @return void
 */
function blueworx_render_cache_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-labs-wordpress' ) );
	}

	$cache_notice  = get_transient( 'blueworx_cache_refresh_notice' );
	$breeze_active = blueworx_is_breeze_active();

	if ( $cache_notice ) {
		delete_transient( 'blueworx_cache_refresh_notice' );
	}

	echo wp_kses_post(
		blueworx_ds_screen_open(
			blueworx_ds_page_header(
				array(
					'title' => __( 'Cache', 'blueworx-labs-wordpress' ),
					'lede'  => __( 'What gets refreshed on its own, and a button for when you would rather not wait.', 'blueworx-labs-wordpress' ),
				)
			)
		)
	);

	if ( $cache_notice ) {
		echo wp_kses_post(
			blueworx_ds_notice(
				array(
					'tone' => 'success',
					'text' => $cache_notice,
				)
			)
		);
	}

	// Status reads as a description list rather than a form table: none of it is
	// editable, and the old two-column table implied it was.
	$rows = array(
		__( 'Automatic refresh', 'blueworx-labs-wordpress' ) => blueworx_ds_badge( __( 'On', 'blueworx-labs-wordpress' ), 'success', true )
			. '<p class="bw-field__help">'
			. esc_html__( 'When a page or post changes, this plugin refreshes the edited page, the homepage, and the listing pages it appears on.', 'blueworx-labs-wordpress' )
			. '</p>',
		__( 'Breeze cache', 'blueworx-labs-wordpress' )      => blueworx_ds_badge(
			$breeze_active
				? __( 'Detected', 'blueworx-labs-wordpress' )
				: __( 'Not detected', 'blueworx-labs-wordpress' ),
			$breeze_active ? 'success' : 'neutral',
			true
		)
			. '<p class="bw-field__help">'
			. esc_html__( 'Cloudways Breeze and Varnish are used where they are available. Where they are not, WordPress clears its own caches instead.', 'blueworx-labs-wordpress' )
			. '</p>',
	);

	// The form posts exactly what it posted before — same action, same nonce.
	$form = sprintf(
		'<form method="post" action="%1$s"><input type="hidden" name="action" value="blueworx_clear_cache_now" />%2$s%3$s</form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		wp_nonce_field( 'blueworx_clear_cache_now', '_wpnonce', true, false ),
		blueworx_ds_button(
			array(
				'label'   => __( 'Refresh cache now', 'blueworx-labs-wordpress' ),
				'variant' => 'primary',
				'icon'    => 'refresh-cw',
				'type'    => 'submit',
				'attrs'   => array( 'name' => 'submit' ),
			)
		)
	);

	echo wp_kses(
		'<div class="bw-page__body"><div class="bw-panels">'
			. blueworx_ds_card(
				array(
					'eyebrow' => __( 'Cache', 'blueworx-labs-wordpress' ),
					'title'   => __( 'Refreshing', 'blueworx-labs-wordpress' ),
					'body'    => blueworx_ds_description_list( $rows ),
					'footer'  => $form,
				)
			)
			. '</div></div>',
		blueworx_ds_allowed_html()
	);

	echo wp_kses_post( blueworx_ds_screen_close() );
}

/**
 * Renders the Edit Menu page.
 *
 * @return void
 */
function blueworx_render_edit_menu_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-labs-wordpress' ) );
	}

	$menu_items  = blueworx_get_editable_admin_menu_items();
	$assignments = blueworx_get_admin_menu_group_assignments();
	$hidden      = blueworx_get_hidden_admin_menu_items();
	$saved_order = blueworx_get_saved_admin_menu_order();
	$groups      = blueworx_get_admin_menu_groups();
	$notice      = get_transient( 'blueworx_admin_menu_order_notice' );

	if ( $notice ) {
		delete_transient( 'blueworx_admin_menu_order_notice' );
	}

	// Saved order first, then anything the site has registered since.
	$ordered = array();

	foreach ( $saved_order as $slug ) {
		if ( isset( $menu_items[ $slug ] ) ) {
			$ordered[ $slug ] = $menu_items[ $slug ];
		}
	}

	foreach ( $menu_items as $slug => $label ) {
		if ( ! isset( $ordered[ $slug ] ) ) {
			$ordered[ $slug ] = $label;
		}
	}

	// Bucket every editable item: hidden wins, otherwise its assigned group.
	$buckets           = array_fill_keys( array_keys( $groups ), array() );
	$buckets['hidden'] = array();

	foreach ( $ordered as $slug => $label ) {
		if ( in_array( $slug, $hidden, true ) ) {
			$buckets['hidden'][ $slug ] = $label;
			continue;
		}

		$group = isset( $assignments[ $slug ] ) ? $assignments[ $slug ] : blueworx_get_default_admin_menu_group( $slug );

		if ( ! isset( $buckets[ $group ] ) ) {
			$group = blueworx_get_default_admin_menu_group_fallback();
		}

		$buckets[ $group ][ $slug ] = $label;
	}

	$sections           = $groups;
	$sections['hidden'] = __( 'Hidden', 'blueworx-labs-wordpress' );

	echo wp_kses_post(
		blueworx_ds_screen_open(
			blueworx_ds_page_header(
				array(
					'title' => __( 'Edit Menu', 'blueworx-labs-wordpress' ),
					'lede'  => __( 'Drag an item into another group to move it, or use the arrows. Anything left in Hidden stays out of the sidebar.', 'blueworx-labs-wordpress' ),
				)
			)
		)
	);

	if ( $notice ) {
		echo wp_kses_post(
			blueworx_ds_notice(
				array(
					'tone' => 'success',
					'text' => $notice,
				)
			)
		);
	}

	// Same action, same nonce, same field names as before — this screen changed
	// its markup, not what it posts.
	printf(
		'<form method="post" action="%1$s" class="bw-page__form"><input type="hidden" name="action" value="blueworx_save_admin_menu_order" />%2$s',
		esc_url( admin_url( 'admin-post.php' ) ),
		wp_nonce_field( 'blueworx_save_admin_menu_order', '_wpnonce', true, false )
	);

	$cards = '';

	foreach ( $sections as $group => $label ) {
		$cards .= blueworx_render_menu_editor_group( $group, $label, $buckets[ $group ] );
	}

	echo wp_kses(
		'<div class="bw-page__body"><div class="bw-panels bw-menu-editor">' . $cards . '</div></div>',
		blueworx_ds_allowed_html()
	);

	echo wp_kses(
		'<div class="bw-savebar"><p class="bw-savebar__hint">'
			. esc_html__( 'Moves are not saved until you say so.', 'blueworx-labs-wordpress' )
			. '</p>'
			. blueworx_ds_button(
				array(
					'label'   => __( 'Save Menu Settings', 'blueworx-labs-wordpress' ),
					'variant' => 'primary',
					'type'    => 'submit',
				)
			)
			. '</div>',
		blueworx_ds_allowed_html()
	);

	echo '</form>';

	echo wp_kses_post( blueworx_ds_screen_close() );
}

/**
 * Renders one Edit Menu group as a card of reorderable rows.
 *
 * The row classes are the design system's repeater; the bw-menu-editor-* ones
 * beside them are what assets/js/admin-menu-editor.js binds to. Both are
 * deliberate: the system owns how a reorderable row looks, the script owns what
 * moving one does, and neither has to know about the other.
 *
 * @param string $group Group key, or "hidden".
 * @param string $label Translated section label.
 * @param array  $items Menu labels keyed by slug.
 * @return string HTML.
 */
function blueworx_render_menu_editor_group( $group, $label, $items ) {
	$locked = blueworx_get_locked_admin_menu_items();
	$rows   = '';

	foreach ( $items as $slug => $item_label ) {
		$is_locked = in_array( $slug, $locked, true );

		$row = '<span class="bw-repeater__grip">' . blueworx_ds_icon( 'grip-vertical', 16 ) . '</span>';

		$row .= '<div class="bw-repeater__fields"><span class="bw-menu-editor-label">' . esc_html( $item_label ) . '</span></div>';

		// Locked items are refused by the save handler, so say so here rather
		// than letting someone drag one into Hidden and wonder why it came back.
		if ( $is_locked ) {
			$row .= blueworx_ds_badge( __( 'Always shown', 'blueworx-labs-wordpress' ), 'neutral' );
		}

		$row .= blueworx_ds_icon_button(
			array(
				'icon'  => 'chevron-up',
				/* translators: %s: menu item name. */
				'label' => sprintf( __( 'Move %s up', 'blueworx-labs-wordpress' ), $item_label ),
				'size'  => 'sm',
				'class' => 'bw-menu-editor-up',
			)
		);

		$row .= blueworx_ds_icon_button(
			array(
				'icon'  => 'chevron-down',
				/* translators: %s: menu item name. */
				'label' => sprintf( __( 'Move %s down', 'blueworx-labs-wordpress' ), $item_label ),
				'size'  => 'sm',
				'class' => 'bw-menu-editor-down',
			)
		);

		$row .= sprintf(
			'<input type="hidden" class="bw-menu-editor-order" name="blueworx_admin_menu_order[]" value="%1$s" /><input type="hidden" class="bw-menu-editor-group-input" name="%2$s" value="%3$s" />',
			esc_attr( $slug ),
			esc_attr( 'blueworx_admin_menu_groups[' . $slug . ']' ),
			esc_attr( $group )
		);

		if ( 'hidden' === $group ) {
			$row .= sprintf(
				'<input type="hidden" class="bw-menu-editor-hidden-input" name="blueworx_hidden_admin_menu_items[]" value="%1$s" />',
				esc_attr( $slug )
			);
		}

		$rows .= sprintf(
			'<div class="bw-repeater__row bw-menu-editor-item" role="listitem" draggable="true" data-slug="%1$s">%2$s</div>',
			esc_attr( $slug ),
			$row
		);
	}

	// An empty group still has to be a drop target, so the "nothing here" line
	// goes inside the list rather than in place of it. The script hides it as
	// soon as the list has a row, and shows it again when the last one leaves.
	$rows .= sprintf(
		'<p class="bw-repeater__empty bw-menu-editor-empty"%1$s>%2$s</p>',
		'' === $rows ? '' : ' hidden',
		esc_html__( 'Drag something here.', 'blueworx-labs-wordpress' )
	);

	return blueworx_ds_card(
		array(
			'eyebrow' => 'hidden' === $group
				? __( 'Out of the sidebar', 'blueworx-labs-wordpress' )
				: __( 'Group', 'blueworx-labs-wordpress' ),
			'title'   => $label,
			'body'    => '<div class="bw-repeater bw-menu-editor-list" role="list">' . $rows . '</div>',
			'class'   => 'bw-menu-editor-group',
			'attrs'   => array( 'data-group' => $group ),
		)
	);
}
