<?php
/**
 * User profile cleanup behavior.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks whether Application Passwords should be visible to admins.
 *
 * @return bool True when enabled.
 */
function blueworx_show_application_passwords_for_admins() {
	return '1' === get_option( 'blueworx_show_application_passwords', '0' );
}

/**
 * Controls whether Application Passwords are available for the current user.
 *
 * @param bool    $available Whether Application Passwords are available.
 * @param WP_User $user      User being edited.
 * @return bool Filtered availability.
 */
function blueworx_filter_application_passwords_available( $available, $user ) {
	if ( ! blueworx_show_application_passwords_for_admins() ) {
		return false;
	}

	if ( ! current_user_can( 'manage_options' ) || ! user_can( $user, 'manage_options' ) ) {
		return false;
	}

	return $available;
}
if ( blueworx_feature_enabled( 'application_passwords' ) ) {
	add_filter( 'wp_is_application_passwords_available_for_user', 'blueworx_filter_application_passwords_available', 10, 2 );
}

/**
 * Gets the user being viewed on profile screens.
 *
 * @return WP_User|null Profile user.
 */
function blueworx_get_current_profile_user() {
	global $pagenow;

	$user_id = get_current_user_id();

	if ( 'user-edit.php' === $pagenow && isset( $_GET['user_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user_id = absint( wp_unslash( $_GET['user_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	return $user_id ? get_user_by( 'id', $user_id ) : null;
}

/**
 * Checks whether the Application Passwords profile section should be hidden.
 *
 * @return bool True when it should be hidden.
 */
function blueworx_should_hide_application_passwords_section() {
	$user = blueworx_get_current_profile_user();

	return ! blueworx_show_application_passwords_for_admins()
		|| ! current_user_can( 'manage_options' )
		|| ! $user
		|| ! user_can( $user, 'manage_options' );
}
