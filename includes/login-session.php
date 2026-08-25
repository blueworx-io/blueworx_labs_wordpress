<?php
/**
 * Login session length.
 *
 * WordPress signs you out after two days, or fourteen if you ticked "Remember
 * me" — and it re-checks that on every page load, so on a site where people
 * work in short bursts it feels like being logged out constantly. This lets a
 * site owner choose the length instead.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The session length that applies when nothing has been chosen.
 */
const BLUEWORX_LOGIN_SESSION_DEFAULT = '24h';

/**
 * Gets the offered session lengths.
 *
 * "Indefinite" is ten years rather than a literal forever: an auth cookie has
 * to carry an expiry, and every path out of a session — logging out, changing
 * a password, an administrator ending the session — works on the session token,
 * not the clock. Ten years is past the life of the site.
 *
 * @return array Labels keyed by option value.
 */
function blueworx_login_session_choices() {
	return array(
		'24h'        => __( '24 hours', 'blueworx-labs-wordpress' ),
		'2d'         => __( '2 days', 'blueworx-labs-wordpress' ),
		'5d'         => __( '5 days', 'blueworx-labs-wordpress' ),
		'7d'         => __( '7 days', 'blueworx-labs-wordpress' ),
		'indefinite' => __( 'Until they sign out', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Gets the length of a session in seconds, keyed by choice.
 *
 * @return array Seconds keyed by option value.
 */
function blueworx_login_session_lengths() {
	return array(
		'24h'        => DAY_IN_SECONDS,
		'2d'         => 2 * DAY_IN_SECONDS,
		'5d'         => 5 * DAY_IN_SECONDS,
		'7d'         => 7 * DAY_IN_SECONDS,
		'indefinite' => 10 * YEAR_IN_SECONDS,
	);
}

/**
 * Gets the chosen session length.
 *
 * @return string Option value.
 */
function blueworx_login_session_choice() {
	$choice = (string) get_option( 'blueworx_login_session', BLUEWORX_LOGIN_SESSION_DEFAULT );

	return isset( blueworx_login_session_choices()[ $choice ] ) ? $choice : BLUEWORX_LOGIN_SESSION_DEFAULT;
}

/**
 * The lengths offered for administrators, plus "same as everyone else".
 *
 * An administrator's session is the one worth shortening: it is the account
 * that can change the site, and it is the one most often left signed in on a
 * shared or borrowed machine.
 *
 * @return array Labels keyed by option value.
 */
function blueworx_admin_session_choices() {
	return array( '' => __( 'Same as everyone else', 'blueworx-labs-wordpress' ) ) + blueworx_login_session_choices();
}

/**
 * Gets the chosen administrator session length.
 *
 * @return string Option value, or an empty string for "same as everyone else".
 */
function blueworx_admin_session_choice() {
	$choice = (string) get_option( 'blueworx_admin_session', '' );

	return isset( blueworx_admin_session_choices()[ $choice ] ) ? $choice : '';
}

/**
 * Sets how long an auth cookie lasts.
 *
 * Applied to both a normal sign-in and a "Remember me" one. Splitting them was
 * considered and rejected: the setting is worded as how long a login lasts, and
 * two lengths for one question is how you end up with a site where the tick box
 * makes sessions SHORTER than the configured value.
 *
 * A BlueWorx support session is exempt and keeps core's own expiry. The support
 * account's whole safety story is that it lapses; an "until they sign out"
 * setting must not quietly extend a read-only support login to ten years. The
 * 24-hour access window closes it either way, but the cookie should not outlive
 * it.
 *
 * @param int  $expiration Cookie lifetime in seconds.
 * @param int  $user_id    User signing in.
 * @param bool $remember   Whether "Remember me" was ticked.
 * @return int Cookie lifetime in seconds.
 */
function blueworx_login_session_expiration( $expiration, $user_id, $remember ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $remember is part of the filter signature; see above.

	/*
	 * Checked against the named user rather than blueworx_support_is_support_user(),
	 * which reads the CURRENT user — and at the point this filter runs, during
	 * wp_set_auth_cookie(), that is not yet the person signing in.
	 */
	if ( function_exists( 'blueworx_support_get_user' ) ) {
		$support = blueworx_support_get_user();

		if ( $support instanceof WP_User && (int) $support->ID === (int) $user_id ) {
			return $expiration;
		}
	}

	$lengths = blueworx_login_session_lengths();
	$choice  = blueworx_login_session_choice();

	// An administrator may have a shorter window of their own. Checked against
	// the named user for the same reason the support check above is: the person
	// signing in is not yet the current user at this point.
	$admin_choice = blueworx_admin_session_choice();

	if ( '' !== $admin_choice ) {
		$user = get_userdata( (int) $user_id );

		if ( $user instanceof WP_User && in_array( 'administrator', (array) $user->roles, true ) ) {
			$choice = $admin_choice;
		}
	}

	return isset( $lengths[ $choice ] ) ? (int) $lengths[ $choice ] : $expiration;
}

/**
 * Makes an ordinary sign-in persist for the chosen length.
 *
 * Without this the setting is half a feature. WordPress writes the auth cookie
 * with NO expiry unless "Remember me" was ticked — a browser-session cookie,
 * gone the moment the window closes — and only the server-side token carries
 * the length the filter above sets. So "stay logged in for 7 days" would still
 * sign somebody out when they shut their laptop, which is the exact complaint
 * this feature exists to answer.
 *
 * `login_form_login` fires in wp-login.php before wp_signon() reads the posted
 * credentials, so this is the last point at which the choice can be made. The
 * "Remember me" box is left on the form: with this on it no longer changes the
 * outcome, but removing a control people recognise, to make a hidden setting
 * the only explanation for how long they stay signed in, is worse.
 *
 * @return void
 */
function blueworx_login_session_force_persistent() {
	// Writing to the request, not reading it: the value is a constant, nothing
	// is trusted from the client, and core validates the credentials itself
	// immediately afterwards.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$_POST['rememberme'] = 'forever';
}

if ( blueworx_feature_enabled( 'login_session' ) ) {
	add_filter( 'auth_cookie_expiration', 'blueworx_login_session_expiration', 10, 3 );
	add_action( 'login_form_login', 'blueworx_login_session_force_persistent' );
}
