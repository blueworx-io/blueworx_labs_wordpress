<?php
/**
 * Where a sign-in lands.
 *
 * Core sends someone who can edit to the dashboard, and that is what people
 * expect. Booking and shop plugins — LatePoint is the one that prompted this —
 * hook the same filter to send every sign-in to their own screen, which is
 * right for a customer and wrong for the person running the site. This puts
 * the dashboard back for anyone who works in the admin area.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs after other plugins have had their say on where a sign-in goes.
 *
 * Not PHP_INT_MAX: that leaves no room for a site to hook something after this
 * one, and there is no contest to win at that end of the queue anyway.
 */
const BLUEWORX_LOGIN_REDIRECT_PRIORITY = 9999;

/**
 * Checks whether a user works in the admin area.
 *
 * The question is "does the backend mean anything to this person", not "are
 * they an administrator" — an author or contributor has screens to land on,
 * and a booking customer does not. `edit_posts` draws that line, with
 * `manage_options` alongside it for a role that administers the site without
 * touching content.
 *
 * @param WP_User $user User signing in.
 * @return bool True when the dashboard is the right destination.
 */
function blueworx_login_redirect_user_works_in_admin( $user ) {
	/**
	 * Filters the capabilities that mark somebody out as a backend user.
	 *
	 * Holding any one of them is enough. A site with a custom role that belongs
	 * in the admin area but holds neither of the defaults can name its own
	 * capability here instead of editing the plugin.
	 *
	 * @param array   $capabilities Capability names.
	 * @param WP_User $user         User signing in.
	 */
	$capabilities = apply_filters(
		'blueworx_login_redirect_capabilities',
		array( 'edit_posts', 'manage_options' ),
		$user
	);

	foreach ( (array) $capabilities as $capability ) {
		if ( user_can( $user, $capability ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Sends a backend sign-in to the dashboard.
 *
 * Core hands this filter two destinations: $redirect_to, which is wherever the
 * queue has arrived at by now and so is the other plugin's answer rather than
 * anybody's request, and $requested, the destination actually asked for. Using
 * the second is what makes this safe — there is no need to recognise or undo
 * another plugin's URL, only to notice whether the person asked for anything.
 *
 * An empty $requested means they went to the login screen and signed in with
 * nothing else in mind, so the dashboard is the answer. A non-empty one means
 * they were sent to log in from somewhere, or followed a link with a
 * destination on it, and taking them anywhere else would lose it.
 *
 * @param string           $redirect_to Destination as it currently stands.
 * @param string           $requested   Destination originally requested.
 * @param WP_User|WP_Error $user        User signing in, or the sign-in failure.
 * @return string Destination.
 */
function blueworx_login_redirect_to_dashboard( $redirect_to, $requested, $user ) {
	// A failed sign-in gets its error screen, not a redirect of ours.
	if ( ! $user instanceof WP_User ) {
		return $redirect_to;
	}

	if ( ! blueworx_login_redirect_user_works_in_admin( $user ) ) {
		return $redirect_to;
	}

	$requested = trim( (string) $requested );

	if ( '' !== $requested ) {
		return $requested;
	}

	return admin_url();
}

if ( blueworx_feature_enabled( 'login_redirect' ) ) {
	add_filter( 'login_redirect', 'blueworx_login_redirect_to_dashboard', BLUEWORX_LOGIN_REDIRECT_PRIORITY, 3 );
}
