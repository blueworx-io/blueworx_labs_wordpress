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

	add_submenu_page(
		'blueworx-labs-wordpress',
		esc_html__( 'Support access', 'blueworx-labs-wordpress' ),
		esc_html__( 'Support access', 'blueworx-labs-wordpress' ),
		'manage_options',
		'blueworx-support',
		'blueworx_render_support_page'
	);

	// Registered under the BlueWorx menu while the function is on, and under no
	// parent while it is off — which keeps the address working so the screen can
	// explain itself, without listing a settings page for something that is not
	// running. remove_submenu_page() is not the same thing: it takes the page out
	// of $_registered_pages too, and the address then answers "you are not
	// allowed", which reads like a permissions fault rather than a switched-off
	// function.
	add_submenu_page(
		blueworx_feature_enabled( 'sso' ) ? 'blueworx-labs-wordpress' : null,
		esc_html__( 'Single sign-on', 'blueworx-labs-wordpress' ),
		esc_html__( 'Single sign-on', 'blueworx-labs-wordpress' ),
		'manage_options',
		'blueworx-sso',
		'blueworx_render_sso_page'
	);

	add_submenu_page(
		'blueworx-labs-wordpress',
		esc_html__( 'Embedded controls', 'blueworx-labs-wordpress' ),
		esc_html__( 'Embedded controls', 'blueworx-labs-wordpress' ),
		'manage_options',
		'blueworx-embedded',
		'blueworx_render_embedded_page'
	);

	add_submenu_page(
		'blueworx-labs-wordpress',
		esc_html__( 'System additions', 'blueworx-labs-wordpress' ),
		esc_html__( 'System additions', 'blueworx-labs-wordpress' ),
		'manage_options',
		'blueworx-additions',
		'blueworx_render_additions_page'
	);
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
 * Puts the sidebar back the way WordPress left it.
 *
 * Deletes the three options rather than writing defaults into them: with
 * nothing saved, the grouping rules and WordPress's own order take over again,
 * which is the same state a site that has never touched this screen is in.
 *
 * @return void
 */
function blueworx_reset_edit_menu_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_reset_admin_menu_order' );

	delete_option( 'blueworx_admin_menu_order' );
	delete_option( 'blueworx_hidden_admin_menu_items' );
	delete_option( 'blueworx_admin_menu_groups' );
	delete_option( 'blueworx_admin_menu_customized' );

	set_transient( 'blueworx_admin_menu_order_notice', __( 'The sidebar is back to the WordPress order.', 'blueworx-labs-wordpress' ), 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-edit-menu' ) );
	exit;
}
if ( blueworx_feature_enabled( 'menu_editor' ) ) {
	add_action( 'admin_post_blueworx_reset_admin_menu_order', 'blueworx_reset_edit_menu_page' );
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
		blueworx_set_feature_enabled( $key, isset( $posted[ $key ] ) );
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

	$admin = isset( $_POST['blueworx_admin_session'] ) ? sanitize_key( wp_unslash( $_POST['blueworx_admin_session'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.

	// An empty value is a real choice here — "same as everyone else" — so it is
	// written rather than skipped, or the setting could never be undone.
	if ( isset( blueworx_admin_session_choices()[ $admin ] ) ) {
		update_option( 'blueworx_admin_session', $admin );
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

	// One control now writes both. "Longest edge" is the bounding box the
	// resizer already applies, so width and height were only ever different by
	// accident. Both options are kept so nothing that reads them has to change.
	$longest = isset( $_POST['blueworx_media_longest_edge'] ) ? absint( wp_unslash( $_POST['blueworx_media_longest_edge'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler.
	$width   = $longest;
	$height  = $longest;

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
					'title'      => __( 'Enhancements', 'blueworx-labs-wordpress' ),
					'lede'       => sprintf(
						/* translators: %d: how many functions this plugin offers. */
						_n(
							'%d function you can switch on one at a time. Nothing changes on the site until you save.',
							'%d functions you can switch on one at a time. Nothing changes on the site until you save.',
							count( $features ),
							'blueworx-labs-wordpress'
						),
						count( $features )
					),
					'actions'    => $header_actions,
					'capability' => 'manage_options',
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
		'<div class="bw-page__body"><div class="bw-panels-row"><nav class="bw-secnav" aria-label="%1$s">%2$s</nav><div class="bw-panels">',
		esc_attr__( 'Sections', 'blueworx-labs-wordpress' ),
		wp_kses( $nav, blueworx_ds_allowed_html() )
	);

	foreach ( $sections_with_features as $section_id => $section_label ) {
		$cards = '';

		foreach ( $features as $key => $feature ) {
			if ( $feature['section'] !== $section_id ) {
				continue;
			}

			$enabled = blueworx_feature_enabled( $key );

			// The switch is bare and sits hard right of the function's name and
			// description, rather than carrying them as its own label. At
			// twenty-seven rows that is the difference between a list you can
			// scan and a wall of sentences.
			$switch = sprintf(
				'<label class="bw-switch bw-switch--bare"><input type="checkbox" role="switch" name="%1$s" value="1"%2$s class="blueworx-feature-toggle" data-blueworx-feature="%3$s" aria-label="%4$s" /><span class="bw-switch__track"><span class="bw-switch__thumb"></span></span></label>',
				esc_attr( 'blueworx_feature[' . $key . ']' ),
				checked( $enabled, true, false ),
				esc_attr( $key ),
				esc_attr( $feature['label'] )
			);

			$row = sprintf(
				'<div class="bw-fncard__row"><div><span class="bw-fncard__name">%1$s</span><p class="bw-fncard__desc">%2$s</p></div>%3$s</div>',
				esc_html( $feature['label'] ),
				esc_html( $feature['description'] ),
				wp_kses( $switch, blueworx_ds_allowed_html() )
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
				//
				// The panel stays in the DOM while the switch is off. An
				// unchecked box and a missing box are the same thing on save, so
				// rendering only the open ones would switch off every setting
				// nobody happened to be looking at.
				$detail = sprintf(
					'<div class="bw-fncard__panel blueworx-feature-detail" data-blueworx-detail="%1$s"%2$s><div class="bw-card bw-card--sunken"><div class="bw-card__body"><p class="bw-card__eyebrow">%3$s</p>%4$s</div></div></div>',
					esc_attr( $key ),
					$enabled ? '' : ' hidden',
					esc_html__( 'Settings for this function', 'blueworx-labs-wordpress' ),
					$detail_html
				);
			}

			$cards .= '<div class="bw-fncard">' . $row . $detail . '</div>';
		}

		// The section head is its own card above the stack, so the count and the
		// switch-everything-off control belong to the section rather than to
		// whichever function happens to be first.
		printf(
			'<div data-blueworx-panel="%1$s"%2$s><section class="bw-card bw-card--flush bw-sectionhead"><div class="bw-card__head"><div class="bw-card__titles"><p class="bw-card__eyebrow">%3$s</p><h2 class="bw-card__title">%4$s</h2></div><div class="bw-card__actions">%5$s%6$s</div></div></section><div class="bw-fnstack">%7$s</div></div>',
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
					'neutral'
				),
				blueworx_ds_allowed_html()
			),
			wp_kses(
				blueworx_ds_button(
					array(
						'label'   => __( 'Switch this section off', 'blueworx-labs-wordpress' ),
						'variant' => 'ghost',
						'size'    => 'sm',
						'class'   => 'blueworx-section-off',
					)
				),
				blueworx_ds_allowed_html()
			),
			// Already filtered piece by piece above: the parts this file composes
			// go through wp_kses(), the detail panels render their own escaped
			// markup. See the note on $detail.
			$cards // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	echo '</div></div></div>';

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
 * Stacks a panel's fields.
 *
 * One column, and the grid's own gap between fields — so a panel spaces itself
 * the way the rest of the screen does rather than relying on paragraph margins.
 *
 * @param string $fields Field HTML.
 * @return string HTML.
 */
function blueworx_detail_stack( $fields ) {
	return '<div class="bw-fields bw-fields--single">' . $fields . '</div>';
}

/**
 * Renders the nested detail controls for a feature.
 *
 * @param string $key Feature key.
 * @return void
 */
function blueworx_render_feature_detail( $key ) {
	// Three panels own their whole surface and render themselves.
	if ( 'sso' === $key ) {
		blueworx_sso_render_detail();
		return;
	}

	if ( 'support_access' === $key ) {
		blueworx_support_render_panel();
		return;
	}

	if ( 'translate' === $key ) {
		blueworx_translate_render_detail();
		return;
	}

	$html = blueworx_get_feature_detail_html( $key );

	if ( '' !== $html ) {
		// Not wp_kses(): the design system helpers escape everything they emit,
		// and an allow-list silently drops what it does not recognise — the copy
		// button's data-blueworx-copy among it, which leaves a button that looks
		// right and copies nothing. Same reasoning as the support panel.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Builds the detail controls for a feature.
 *
 * Every control comes from the design system helpers, so a panel reads as part
 * of the screen around it rather than as a WordPress options form dropped into
 * a card. Nothing here renders a bare input, and nothing here writes CSS.
 *
 * @param string $key Feature key.
 * @return string HTML, or an empty string when the feature has no detail.
 */
function blueworx_get_feature_detail_html( $key ) {
	if ( 'login' === $key ) {
		// The whole address, copyable. A slug on its own is not the thing anybody
		// needs to keep — the address is, and typing it out from a slug is where
		// people get locked out.
		return blueworx_detail_stack(
			blueworx_ds_field(
				array(
					'label'   => __( 'Your login address', 'blueworx-labs-wordpress' ),
					'for'     => 'blueworx_login_slug',
					'control' => blueworx_ds_copy_field(
						array(
							'value' => home_url( '/' ) . blueworx_login_slug(),
							'id'    => 'blueworx-login-address',
							'label' => __( 'Copy', 'blueworx-labs-wordpress' ),
						)
					),
					'help'    => __( 'Keep a copy somewhere outside the site. Once you save, the usual WordPress login stops working for everyone.', 'blueworx-labs-wordpress' ),
				)
			)
			. blueworx_ds_field(
				array(
					'label'   => __( 'Address to use', 'blueworx-labs-wordpress' ),
					'for'     => 'blueworx_login_slug',
					'control' => blueworx_ds_input(
						array(
							'name'  => 'blueworx_login_slug',
							'id'    => 'blueworx_login_slug',
							'value' => blueworx_login_slug(),
							'mono'  => true,
						)
					),
					'help'    => __( 'The last part of the address above. Changing it changes where you sign in the moment you save.', 'blueworx-labs-wordpress' ),
				)
			)
		);
	}

	if ( 'site_protection' === $key ) {
		$role_choices = blueworx_get_site_protection_role_choices();
		$areas        = array(
			'frontend' => array(
				'toggle' => __( 'Protect the front of the site', 'blueworx-labs-wordpress' ),
				'roles'  => __( 'Roles that can view the site', 'blueworx-labs-wordpress' ),
			),
			'backend'  => array(
				'toggle' => __( 'Protect the admin area', 'blueworx-labs-wordpress' ),
				'roles'  => __( 'Roles that can reach the admin area', 'blueworx-labs-wordpress' ),
			),
		);

		$fields = '';

		foreach ( $areas as $area => $labels ) {
			$fields .= blueworx_ds_field(
				array(
					'control' => blueworx_ds_checkbox(
						array(
							'name'    => 'blueworx_' . $area . '_protection_enabled',
							'label'   => $labels['toggle'],
							'checked' => blueworx_site_protection_enabled( $area ),
						)
					),
				)
			);

			$fields .= blueworx_ds_choice_group(
				array(
					'name'     => 'blueworx_' . $area . '_protection_roles',
					'id'       => 'blueworx-' . $area . '-roles',
					'legend'   => $labels['roles'],
					'choices'  => $role_choices,
					'selected' => blueworx_get_site_protection_roles( $area ),
					'help'     => __( 'Tick at least one role. With none ticked, nobody gets in at all.', 'blueworx-labs-wordpress' ),
				)
			);
		}

		return blueworx_detail_stack( $fields );
	}

	if ( 'application_passwords' === $key ) {
		return blueworx_detail_stack(
			blueworx_ds_field(
				array(
					'control' => blueworx_ds_checkbox(
						array(
							'name'    => 'blueworx_show_application_passwords',
							'label'   => __( 'Show Application Passwords for admins', 'blueworx-labs-wordpress' ),
							'checked' => blueworx_show_application_passwords_for_admins(),
						)
					),
				)
			)
		);
	}

	if ( 'cache_manual' === $key ) {
		return blueworx_ds_button(
			array(
				'label' => __( 'Open Cache page', 'blueworx-labs-wordpress' ),
				'icon'  => 'refresh-cw',
				'href'  => admin_url( 'admin.php?page=blueworx-cache' ),
			)
		);
	}

	if ( 'menu_editor' === $key ) {
		return blueworx_ds_button(
			array(
				'label' => __( 'Open Edit Menu page', 'blueworx-labs-wordpress' ),
				'icon'  => 'list',
				'href'  => admin_url( 'admin.php?page=blueworx-edit-menu' ),
			)
		);
	}

	if ( 'login_session' === $key ) {
		return blueworx_detail_stack(
			blueworx_ds_field(
				array(
					'label'   => __( 'Stay signed in for', 'blueworx-labs-wordpress' ),
					'for'     => 'blueworx_login_session',
					'control' => blueworx_ds_select(
						array(
							'name'     => 'blueworx_login_session',
							'id'       => 'blueworx_login_session',
							'options'  => blueworx_login_session_choices(),
							'selected' => blueworx_login_session_choice(),
						)
					),
					'help'    => __( 'Applies from the next time somebody signs in. Anyone already signed in keeps their current session until it runs out.', 'blueworx-labs-wordpress' ),
				)
			)
			. blueworx_ds_field(
				array(
					'label'   => __( 'Administrators', 'blueworx-labs-wordpress' ),
					'for'     => 'blueworx_admin_session',
					'control' => blueworx_ds_select(
						array(
							'name'     => 'blueworx_admin_session',
							'id'       => 'blueworx_admin_session',
							'options'  => blueworx_admin_session_choices(),
							'selected' => blueworx_admin_session_choice(),
						)
					),
					'help'    => __( 'Administrators can be signed out sooner than everyone else. Theirs is the account that can change the site.', 'blueworx-labs-wordpress' ),
				)
			)
		);
	}

	if ( 'admin_bar' === $key ) {
		$removed = blueworx_admin_bar_removed_nodes();
		$boxes   = '';

		foreach ( blueworx_admin_bar_removable_nodes() as $node => $label ) {
			$boxes .= blueworx_ds_checkbox(
				array(
					'name'    => 'blueworx_admin_bar_removed_nodes[]',
					'value'   => $node,
					'label'   => $label,
					'checked' => in_array( $node, $removed, true ),
				)
			);
		}

		$boxes .= blueworx_ds_checkbox(
			array(
				'name'    => 'blueworx_admin_bar_hide_howdy',
				'label'   => __( 'Drop the "Howdy," greeting', 'blueworx-labs-wordpress' ),
				'checked' => blueworx_admin_bar_hide_howdy(),
			)
		);

		$boxes .= blueworx_ds_checkbox(
			array(
				'name'    => 'blueworx_admin_bar_hide_help',
				'label'   => __( 'Remove the Help tab and drawer', 'blueworx-labs-wordpress' ),
				'checked' => blueworx_admin_bar_hide_help(),
			)
		);

		$fields = sprintf(
			'<div class="bw-field" role="group" aria-labelledby="%1$s"><span class="bw-field__label" id="%1$s">%2$s</span><div class="bw-radiogroup">%3$s</div></div>',
			'blueworx-admin-bar-nodes-label',
			esc_html__( 'Take these out of the toolbar', 'blueworx-labs-wordpress' ),
			$boxes
		);

		$fields .= blueworx_ds_radio_group(
			array(
				'name'     => 'blueworx_admin_bar_front_end_mode',
				'id'       => 'blueworx-admin-bar-mode',
				'legend'   => __( 'Hide the toolbar on the front of the site', 'blueworx-labs-wordpress' ),
				'selected' => blueworx_admin_bar_front_end_mode(),
				'choices'  => array(
					'off'           => __( 'Show it to everyone signed in (WordPress default)', 'blueworx-labs-wordpress' ),
					'all_but_admin' => __( 'Hide it for everyone except administrators', 'blueworx-labs-wordpress' ),
					'roles'         => __( 'Hide it for these roles only', 'blueworx-labs-wordpress' ),
				),
				'extra'    => blueworx_ds_choice_group(
					array(
						'name'     => 'blueworx_admin_bar_front_end_roles',
						'id'       => 'blueworx-admin-bar-roles',
						'legend'   => __( 'Roles the toolbar is hidden for', 'blueworx-labs-wordpress' ),
						'choices'  => blueworx_get_site_protection_role_choices(),
						'selected' => blueworx_admin_bar_front_end_hidden_roles(),
					)
				),
				'help'     => __( 'A BlueWorx support session always keeps its toolbar, so the read-only indicator and the button that ends the session stay visible.', 'blueworx-labs-wordpress' ),
			)
		);

		return blueworx_detail_stack( $fields );
	}

	if ( 'dashboard_widgets' === $key ) {
		return blueworx_detail_stack(
			blueworx_ds_choice_group(
				array(
					'name'     => 'blueworx_dashboard_removed_widgets',
					'id'       => 'blueworx-dashboard-widgets',
					'legend'   => __( 'Remove these dashboard panels', 'blueworx-labs-wordpress' ),
					'choices'  => blueworx_dashboard_removable_widgets(),
					'selected' => blueworx_dashboard_removed_widgets(),
					'help'     => __( 'A panel removed here is gone for everyone, not just hidden behind Screen Options. Panels belonging to a plugin that is switched off are ignored.', 'blueworx-labs-wordpress' ),
				)
			)
		);
	}

	if ( 'robots_txt' === $key ) {
		$fields = '';

		if ( blueworx_robots_txt_file_exists() ) {
			$fields .= blueworx_ds_notice(
				array(
					'tone' => 'warning',
					'text' => __( 'There is a real robots.txt file on the server. WordPress serves that file instead, so anything saved here will have no effect until it is removed.', 'blueworx-labs-wordpress' ),
				)
			);
		}

		if ( ! get_option( 'blog_public' ) ) {
			$fields .= blueworx_ds_notice(
				array(
					'tone' => 'warning',
					'text' => __( 'This site is set to discourage search engines, under Settings > Reading. What you save here is served anyway, so if the site is meant to stay out of search results, say so in the box below as well.', 'blueworx-labs-wordpress' ),
				)
			);
		}

		$fields .= blueworx_ds_field(
			array(
				'label'   => __( 'robots.txt content', 'blueworx-labs-wordpress' ),
				'for'     => 'blueworx_robots_txt',
				'control' => blueworx_ds_textarea(
					array(
						'name'  => 'blueworx_robots_txt',
						'id'    => 'blueworx_robots_txt',
						'value' => blueworx_robots_txt_content(),
						'rows'  => 10,
						'mono'  => true,
					)
				),
				'help'    => __( 'Replaces the file WordPress generates. If an SEO plugin also writes robots rules, check the result at /robots.txt after saving — whichever runs last wins.', 'blueworx-labs-wordpress' ),
			)
		);

		$fields .= blueworx_ds_button(
			array(
				'label'   => __( 'View the live file', 'blueworx-labs-wordpress' ),
				'icon'    => 'external-link',
				'variant' => 'ghost',
				'size'    => 'sm',
				'href'    => home_url( '/robots.txt' ),
				'attrs'   => array(
					'target' => '_blank',
					'rel'    => 'noopener noreferrer',
				),
			)
		);

		return blueworx_detail_stack( $fields );
	}

	if ( 'media_tools' === $key ) {
		list( $max_width, $max_height ) = blueworx_media_max_dimensions();

		$fields = blueworx_ds_field(
			array(
				'control' => blueworx_ds_checkbox(
					array(
						'name'    => 'blueworx_media_replace_enabled',
						'label'   => __( 'Allow a file to be replaced in place', 'blueworx-labs-wordpress' ),
						'checked' => blueworx_media_replace_enabled(),
					)
				) . blueworx_ds_checkbox(
					array(
						'name'    => 'blueworx_media_max_dimensions_enabled',
						'label'   => __( 'Shrink oversized images as they are uploaded', 'blueworx-labs-wordpress' ),
						'checked' => blueworx_media_max_dimensions_enabled(),
					)
				),
			)
		);

		// One control, not two. The design system's range field, and "longest
		// edge" is what a bounding box actually means: WordPress fits an upload
		// inside a square of this size, so a separate width and height only ever
		// mattered when they differed, which is a setting nobody wanted.
		//
		// A site that had different values keeps the larger of the two until it
		// next saves this panel.
		$longest = max( (int) $max_width, (int) $max_height );

		$fields .= blueworx_ds_field(
			array(
				'label'   => __( 'Longest edge', 'blueworx-labs-wordpress' ),
				'for'     => 'blueworx_media_longest_edge',
				'control' => sprintf(
					'<div class="bw-range"><input type="range" name="blueworx_media_longest_edge" id="blueworx_media_longest_edge" min="1200" max="4000" step="200" value="%1$s" /><span class="bw-range__value" data-blueworx-range-value="blueworx_media_longest_edge" data-blueworx-range-format="%3$s">%2$s</span></div>',
					esc_attr( (string) $longest ),
					esc_html(
						sprintf(
							/* translators: %s: a number of pixels. */
							__( '%s px', 'blueworx-labs-wordpress' ),
							number_format_i18n( $longest )
						)
					),
					/* translators: %s: a number of pixels. */
					esc_attr__( '%s px', 'blueworx-labs-wordpress' )
				),
				'help'    => __( 'Anything larger is shrunk to fit inside a square this size. The original is not kept.', 'blueworx-labs-wordpress' ),
			)
		);

		$fields .= blueworx_ds_choice_group(
			array(
				'name'     => 'blueworx_media_svg_roles',
				'id'       => 'blueworx-media-svg-roles',
				'legend'   => __( 'Allow SVG uploads for these roles', 'blueworx-labs-wordpress' ),
				'choices'  => blueworx_get_site_protection_role_choices(),
				'selected' => blueworx_media_svg_roles(),
				'help'     => __( 'Tick nothing to switch SVG uploads off, which is the default. An SVG is a document that can carry code, so every one uploaded is stripped of anything that could run before it is stored. Only grant this to roles you trust with the whole site.', 'blueworx-labs-wordpress' ),
			)
		);

		return blueworx_detail_stack( $fields );
	}

	if ( 'content_tools' === $key ) {
		return blueworx_detail_stack(
			blueworx_ds_field(
				array(
					'control' => blueworx_ds_checkbox(
						array(
							'name'    => 'blueworx_duplicate_enabled',
							'label'   => __( 'Show a Duplicate link on pages, posts and custom items', 'blueworx-labs-wordpress' ),
							'checked' => blueworx_duplicate_enabled(),
						)
					) . blueworx_ds_checkbox(
						array(
							'name'    => 'blueworx_external_permalinks_enabled',
							'label'   => __( 'Let an item point at an address on another site', 'blueworx-labs-wordpress' ),
							'checked' => blueworx_external_permalinks_enabled(),
							'help'    => __( 'Adds a "Link to another site" box to the editor. Anyone clicking that item goes straight to the address you put there.', 'blueworx-labs-wordpress' ),
						)
					),
				)
			)
		);
	}

	if ( 'revisions' === $key ) {
		return blueworx_detail_stack(
			blueworx_ds_field(
				array(
					'label'   => __( 'Saved versions to keep per item', 'blueworx-labs-wordpress' ),
					'for'     => 'blueworx_revisions_limit',
					'control' => blueworx_ds_input(
						array(
							'type'  => 'number',
							'name'  => 'blueworx_revisions_limit',
							'id'    => 'blueworx_revisions_limit',
							'value' => (string) blueworx_revisions_to_keep(),
							'small' => true,
							'attrs' => array(
								'min' => '0',
								'max' => '500',
							),
						)
					),
					'help'    => __( 'Applies to versions saved from now on — versions already stored are left alone. Set it to 0 to stop keeping versions at all.', 'blueworx-labs-wordpress' ),
				)
			)
		);
	}

	return '';
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
					'title'      => __( 'Cache', 'blueworx-labs-wordpress' ),
					'lede'       => __( 'Refresh the cache by hand when something looks stale on the front of the site.', 'blueworx-labs-wordpress' ),
					'capability' => 'manage_options',
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
		__( 'Automatic refresh', 'blueworx-labs-wordpress' ) => blueworx_ds_badge( __( 'Enabled', 'blueworx-labs-wordpress' ), 'success', true )
			. '<p class="bw-field__help">'
			. esc_html__( 'When a page or post changes, this plugin refreshes the edited page, the homepage, and the listing pages it appears on.', 'blueworx-labs-wordpress' )
			. '</p>',
		__( 'Breeze cache', 'blueworx-labs-wordpress' )      => blueworx_ds_badge(
			$breeze_active
				? __( 'Detected', 'blueworx-labs-wordpress' )
				: __( 'Not detected', 'blueworx-labs-wordpress' ),
			// Amber, not grey: a missing Breeze is not neutral information — it is
			// the reason a refresh clears less than somebody expects it to.
			$breeze_active ? 'success' : 'warning',
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

	$last = blueworx_cache_last_refreshed_label();

	// The card foot pushes its first child hard left, so the hint sits opposite
	// the button rather than beside it.
	$footer = sprintf(
		'<span class="bw-savebar__hint" data-testid="bw-cache-last">%s</span>',
		'' !== $last
			? esc_html(
				sprintf(
					/* translators: %s: how long ago, e.g. "5 mins ago". */
					__( 'Last refreshed %s', 'blueworx-labs-wordpress' ),
					$last
				)
			)
			: esc_html__( 'Never refreshed by hand', 'blueworx-labs-wordpress' )
	) . $form;

	echo wp_kses(
		'<div class="bw-page__body"><div class="bw-panels">'
			. blueworx_ds_card(
				array(
					'title'  => __( 'Status', 'blueworx-labs-wordpress' ),
					'body'   => blueworx_ds_description_list( $rows ),
					'footer' => $footer,
				)
			)
			. blueworx_ds_notice(
				array(
					'tone' => 'info',
					'text' => __( 'A refresh clears every cached page. The first visitor to each page waits a moment longer while it is rebuilt.', 'blueworx-labs-wordpress' ),
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
					'title'      => __( 'Edit Menu', 'blueworx-labs-wordpress' ),
					'lede'       => __( 'Drag an item into another group to move it, or use the arrows. Anything left in Hidden stays out of the sidebar.', 'blueworx-labs-wordpress' ),
					'capability' => 'manage_options',
					'actions'    => blueworx_ds_button(
						array(
							'label' => __( 'Reset to WordPress order', 'blueworx-labs-wordpress' ),
							'icon'  => 'rotate-ccw',
							'href'  => wp_nonce_url(
								add_query_arg( 'action', 'blueworx_reset_admin_menu_order', admin_url( 'admin-post.php' ) ),
								'blueworx_reset_admin_menu_order'
							),
						)
					),
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
					'label'   => __( 'Discard changes', 'blueworx-labs-wordpress' ),
					'variant' => 'ghost',
					'href'    => admin_url( 'admin.php?page=blueworx-edit-menu' ),
				)
			)
			. blueworx_ds_button(
				array(
					'label'   => __( 'Save changes', 'blueworx-labs-wordpress' ),
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

		// The menu's own icon, so a row is recognisable as the thing it moves
		// rather than as a line of text. Only mapped core slugs have one; a
		// third-party menu simply has no glyph here.
		$menu_icon = blueworx_get_admin_menu_icon( $slug, 18 );

		if ( '' !== $menu_icon ) {
			$row .= '<span class="bw-icon bw-icon--18">'
				. wp_kses( $menu_icon, blueworx_get_svg_kses_allowlist() )
				. '</span>';
		}

		$row .= '<div class="bw-repeater__fields"><span class="bw-menu-editor-label">' . esc_html( $item_label ) . '</span></div>';

		// Locked items are refused by the save handler, so say so here rather
		// than letting someone drag one into Hidden and wonder why it came back.
		if ( $is_locked ) {
			$row .= blueworx_ds_badge( __( 'Locked', 'blueworx-labs-wordpress' ), 'neutral', false, 'lock' );
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

		// Up and down cross into the next group only once you reach the end of
		// this one. These two send an item straight there, which is what moving
		// between groups actually means to the person doing it.
		$row .= blueworx_ds_icon_button(
			array(
				'icon'  => 'chevron-left',
				/* translators: %s: menu item name. */
				'label' => sprintf( __( 'Move %s to the previous group', 'blueworx-labs-wordpress' ), $item_label ),
				'size'  => 'sm',
				'class' => 'bw-menu-editor-prev',
			)
		);

		$row .= blueworx_ds_icon_button(
			array(
				'icon'  => 'chevron-right',
				/* translators: %s: menu item name. */
				'label' => sprintf( __( 'Move %s to the next group', 'blueworx-labs-wordpress' ), $item_label ),
				'size'  => 'sm',
				'class' => 'bw-menu-editor-next',
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
		esc_html__( 'Nothing here. Drop an item in, or send one across with the arrows.', 'blueworx-labs-wordpress' )
	);

	return blueworx_ds_card(
		array(
			'eyebrow' => 'hidden' === $group
				? __( 'Out of the sidebar', 'blueworx-labs-wordpress' )
				: __( 'Group', 'blueworx-labs-wordpress' ),
			'title'   => $label,
			// How many are in here, without counting the rows by eye.
			'actions' => blueworx_ds_badge( (string) count( $items ), 'neutral' ),
			'body'    => '<div class="bw-repeater bw-menu-editor-list" role="list">' . $rows . '</div>',
			'class'   => 'bw-menu-editor-group',
			'attrs'   => array( 'data-group' => $group ),
		)
	);
}
