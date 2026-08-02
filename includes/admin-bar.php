<?php
/**
 * Toolbar cleanup.
 *
 * Two jobs, both under the `admin_bar` feature:
 *
 *  1. Strip the nodes nobody on a managed site uses from the black toolbar —
 *     the WordPress logo menu, Customize, the update counter, New Content and
 *     the "Howdy," greeting — and drop the Help drawer that sits under it.
 *  2. Hide the toolbar entirely on the front of the site for the roles chosen
 *     in settings.
 *
 * Deliberately NOT folded into the `admin_theme` feature. That feature is
 * described to clients as purely visual and reversible, and the admin theme
 * already hides #wpadminbar with CSS on desktop. Removing a node changes what a
 * user can reach, and hiding the front-end toolbar changes it for everyone but
 * an administrator — behaviour, not appearance. Somebody switching the theme
 * off to "get standard WordPress back" should not also be handing the Customizer
 * back to every editor, and somebody who wants the Help drawer gone should not
 * have to give up the design to get it.
 *
 * Replaces the Admin and Site Enhancements `hide_modify_elements` and
 * `hide_admin_bar` modules.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the toolbar nodes this feature can remove.
 *
 * The Comments node is absent on purpose: includes/disable-comments.php already
 * removes it whenever comments are switched off, and offering the same removal
 * twice would leave two settings quietly disagreeing about one node.
 *
 * @return array Node labels keyed by admin bar node id.
 */
function blueworx_admin_bar_removable_nodes() {
	return array(
		'wp-logo'     => __( 'WordPress logo menu', 'blueworx-labs-wordpress' ),
		'customize'   => __( 'Customize', 'blueworx-labs-wordpress' ),
		'updates'     => __( 'Update counter', 'blueworx-labs-wordpress' ),
		'new-content' => __( 'New Content ("+ New")', 'blueworx-labs-wordpress' ),
		'search'      => __( 'Front-end search box', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Gets the toolbar nodes chosen for removal.
 *
 * @return array Node ids.
 */
function blueworx_admin_bar_removed_nodes() {
	$stored = get_option( 'blueworx_admin_bar_removed_nodes', null );

	if ( ! is_array( $stored ) ) {
		// Never saved: the live-site parity list, minus Comments (handled by the
		// comments feature) and minus the front-end search box, which is a
		// genuine navigation aid rather than clutter.
		return array( 'wp-logo', 'customize', 'updates', 'new-content' );
	}

	return array_values(
		array_intersect(
			array_keys( blueworx_admin_bar_removable_nodes() ),
			array_map( 'sanitize_key', $stored )
		)
	);
}

/**
 * Whether the "Howdy," greeting is stripped from the account node.
 *
 * @return bool True when the greeting is reduced to the display name.
 */
function blueworx_admin_bar_hide_howdy() {
	return '0' !== get_option( 'blueworx_admin_bar_hide_howdy', '1' );
}

/**
 * Whether the Help tab and drawer are removed.
 *
 * @return bool True when Help is removed.
 */
function blueworx_admin_bar_hide_help() {
	return '0' !== get_option( 'blueworx_admin_bar_hide_help', '1' );
}

/**
 * Gets the roles for which the front-end toolbar is hidden.
 *
 * Absent means the shipped default: everyone except an administrator. That is
 * the sane reading of a site whose owner asked for the toolbar to be hidden but
 * named no roles — the point is that visitors and editors do not see it, and an
 * administrator locking themselves out of their own toolbar helps nobody.
 *
 * @return array Role slugs, or the string 'all_but_admin'.
 */
function blueworx_admin_bar_front_end_hidden_roles() {
	$stored = get_option( 'blueworx_admin_bar_front_end_roles', null );

	if ( ! is_array( $stored ) ) {
		return array();
	}

	return array_values( array_unique( array_map( 'sanitize_key', $stored ) ) );
}

/**
 * The front-end toolbar hiding mode.
 *
 * @return string 'off', 'all_but_admin' or 'roles'.
 */
function blueworx_admin_bar_front_end_mode() {
	$mode = (string) get_option( 'blueworx_admin_bar_front_end_mode', 'off' );

	return in_array( $mode, array( 'off', 'all_but_admin', 'roles' ), true ) ? $mode : 'off';
}

/**
 * Removes the chosen nodes from the toolbar.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
 * @return void
 */
function blueworx_admin_bar_remove_nodes( $wp_admin_bar ) {
	foreach ( blueworx_admin_bar_removed_nodes() as $node ) {
		$wp_admin_bar->remove_node( $node );
	}
}

/**
 * Replaces "Howdy, Name" with the name on its own.
 *
 * Rewrites the existing node rather than removing and re-adding it, so the
 * avatar markup, href and every child item core hung off it survive untouched.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
 * @return void
 */
function blueworx_admin_bar_strip_howdy( $wp_admin_bar ) {
	$account = $wp_admin_bar->get_node( 'my-account' );

	if ( ! $account || empty( $account->title ) ) {
		return;
	}

	$user = wp_get_current_user();

	if ( ! $user instanceof WP_User || '' === $user->display_name ) {
		return;
	}

	/*
	 * Core builds this title as "Howdy, <name><avatar>". Matching on the
	 * translated greeting rather than the raw string keeps a translated site
	 * working, and the str_replace is a no-op if the theme or another plugin has
	 * already changed it — no scenario here leaves the node blank.
	 */
	// Core's own string, in core's own domain, on purpose: this has to reproduce
	// the exact text core put in the node, and asking for it in this plugin's
	// domain would return the untranslated original on a translated site. The
	// placeholder is core's too, so it carries no translators note of ours.
	// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch, WordPress.WP.I18n.MissingTranslatorsComment -- Reproducing a core string; see above.
	$greeting = sprintf( __( 'Howdy, %s', 'default' ), $user->display_name );

	$account->title = str_replace( $greeting, $user->display_name, $account->title );

	$wp_admin_bar->add_node( (array) $account );
}

/**
 * Removes the Help tab and its drawer from every admin screen.
 *
 * @return void
 */
function blueworx_admin_bar_remove_help_tabs() {
	$screen = get_current_screen();

	if ( ! $screen instanceof WP_Screen ) {
		return;
	}

	$screen->remove_help_tabs();
	$screen->set_help_sidebar( '' );
}

/**
 * Decides whether the current user keeps the front-end toolbar.
 *
 * A BlueWorx support session always keeps it: the session indicator and its
 * "end session" control live in the toolbar, and an agent who cannot see that
 * they are in a read-only session, or close it, is the whole point of the
 * indicator defeated.
 *
 * @param bool $show Whether WordPress intends to show the toolbar.
 * @return bool Whether the toolbar is shown.
 */
function blueworx_admin_bar_filter_front_end( $show ) {
	if ( is_admin() || ! $show ) {
		return $show;
	}

	if ( function_exists( 'blueworx_support_is_support_user' ) && blueworx_support_is_support_user() ) {
		return $show;
	}

	$mode = blueworx_admin_bar_front_end_mode();

	if ( 'off' === $mode ) {
		return $show;
	}

	$user = wp_get_current_user();

	if ( ! $user instanceof WP_User || 0 === $user->ID ) {
		// Logged out: core never shows a toolbar anyway.
		return $show;
	}

	if ( 'all_but_admin' === $mode ) {
		return user_can( $user, 'manage_options' );
	}

	$hidden = blueworx_admin_bar_front_end_hidden_roles();

	return array() === array_intersect( $hidden, (array) $user->roles );
}

if ( blueworx_feature_enabled( 'admin_bar' ) ) {
	add_action( 'admin_bar_menu', 'blueworx_admin_bar_remove_nodes', 999 );
	add_filter( 'show_admin_bar', 'blueworx_admin_bar_filter_front_end', 999 );

	if ( blueworx_admin_bar_hide_howdy() ) {
		add_action( 'admin_bar_menu', 'blueworx_admin_bar_strip_howdy', 999 );
	}

	if ( blueworx_admin_bar_hide_help() ) {
		add_action( 'admin_head', 'blueworx_admin_bar_remove_help_tabs', 999 );
	}
}
