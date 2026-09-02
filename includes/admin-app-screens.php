<?php
/**
 * Screens that belong to a plugin's own app, not to WordPress.
 *
 * SureCart's admin screens are a whole application rendered inside wp-admin:
 * their own header, their own breadcrumb, their own version and account
 * controls. Stacking the BlueWorx top bar above that gave every one of those
 * screens two headers saying the same thing, and left their header sliding under
 * ours as the page scrolled.
 *
 * So these screens get the window to themselves: our top bar comes off and the
 * app's own header becomes the top of the page. The sidebar stays, because
 * unlike LatePoint — which hides the WordPress menu and carries its own — this
 * app has no navigation of its own and would strand you without it.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether an admin page slug belongs to a plugin app that owns its whole screen.
 *
 * Matched on the page slug rather than the screen id, because the screen id
 * carries the menu's title — which this plugin renames ("SureCart" to
 * "Commerce") — and would stop matching the moment a name changed.
 *
 * Only the app's own screens qualify. The Products and Custom Forms lists are
 * ordinary WordPress list tables at edit.php, and they keep the BlueWorx chrome
 * like every other WordPress screen.
 *
 * @param string $page The `page` query argument of the current admin request.
 * @return bool True when the screen is a plugin app's own.
 */
function blueworx_is_plugin_app_screen( $page ) {
	$page = sanitize_key( (string) $page );

	if ( '' === $page ) {
		return false;
	}

	// SureCart's app screens are all admin.php?page=sc-*.
	return 0 === strpos( $page, 'sc-' );
}

/**
 * Marks those screens on the body, for the stylesheet to act on.
 *
 * @param string $classes Space-separated body classes.
 * @return string Body classes.
 */
function blueworx_admin_app_screen_body_class( $classes ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading which screen is being displayed, not acting on a request.
	$page = isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : '';

	if ( ! blueworx_is_plugin_app_screen( $page ) ) {
		return $classes;
	}

	return trim( $classes . ' bw-app-screen' );
}
add_filter( 'admin_body_class', 'blueworx_admin_app_screen_body_class' );
