<?php
/**
 * Admin email notification suppression.
 *
 * @package BlueWorxEnhancements
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Suppresses plugin update available email notifications.
 *
 * @param mixed $value Current option value.
 * @return bool Always false.
 */
function blueworx_disable_plugin_update_emails( $value ) {
	return false;
}
add_filter( 'auto_plugin_update_send_email', 'blueworx_disable_plugin_update_emails' );

/**
 * Suppresses theme update available email notifications.
 *
 * @param mixed $value Current option value.
 * @return bool Always false.
 */
function blueworx_disable_theme_update_emails( $value ) {
	return false;
}
add_filter( 'auto_theme_update_send_email', 'blueworx_disable_theme_update_emails' );

remove_action( 'register_new_user', 'wp_send_new_user_notifications' );
remove_action( 'edit_user_created_user', 'wp_send_new_user_notifications' );
add_action( 'register_new_user', 'blueworx_suppress_new_user_admin_email' );
add_action( 'edit_user_created_user', 'blueworx_suppress_new_user_admin_email', 10, 2 );

/**
 * Sends notification to the new user only, not the admin.
 *
 * @param int    $user_id The new user's ID.
 * @param string $notify  Notification type passed by edit_user_created_user.
 * @return void
 */
function blueworx_suppress_new_user_admin_email( $user_id, $notify = 'user' ) {
	wp_send_new_user_notifications( $user_id, 'user' );
}

/**
 * Suppresses the password change notification email sent to the admin.
 *
 * @param string  $pass_change_email Email data array.
 * @param WP_User $user              The user whose password changed.
 * @param WP_User $userdata          Updated user data.
 * @return bool False to prevent the email from sending.
 */
function blueworx_disable_password_change_notification( $pass_change_email, $user, $userdata ) {
	return false;
}
add_filter( 'send_password_change_email', 'blueworx_disable_password_change_notification', 10, 3 );

/**
 * Suppresses the email change notification email sent to the admin.
 *
 * @param string  $email_change_email Email data array.
 * @param WP_User $user               The user whose email changed.
 * @param WP_User $userdata           Updated user data.
 * @return bool False to prevent the email from sending.
 */
function blueworx_disable_email_change_notification( $email_change_email, $user, $userdata ) {
	return false;
}
add_filter( 'send_email_change_email', 'blueworx_disable_email_change_notification', 10, 3 );
