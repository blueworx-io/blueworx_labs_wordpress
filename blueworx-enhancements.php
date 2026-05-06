<?php
/**
 * Plugin Name:       BlueWorx Enhancements
 * Plugin URI:        https://blueworx.io/
 * Description:       Hardens WordPress security by replacing the default login URL, disabling comments, suppressing admin email notifications, and cleaning up the user profile screen.
 * Version:           1.3.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            BlueWorx
 * Author URI:        https://profiles.wordpress.org/blueworx/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       blueworx-enhancements
 * Domain Path:       /languages
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Defines the custom login slug used throughout the plugin.
define( 'BLUEWORX_CUSTOM_LOGIN_SLUG', 'admin_login' );

// ============================================================================
// 1. ADMIN MENU — Settings > BlueWorx
// ============================================================================

/**
 * Registers the BlueWorx settings page under Settings in the WP admin menu.
 *
 * @return void
 */
function blueworx_register_settings_page() {
	add_options_page(
		esc_html__( 'BlueWorx Enhancements', 'blueworx-enhancements' ),
		esc_html__( 'BlueWorx', 'blueworx-enhancements' ),
		'manage_options',
		'blueworx-enhancements',
		'blueworx_render_settings_page'
	);
}
add_action( 'admin_menu', 'blueworx_register_settings_page' );

/**
 * Renders the BlueWorx settings page content.
 *
 * @return void
 */
function blueworx_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-enhancements' ) );
	}

	$custom_login_url = home_url( '/' . BLUEWORX_CUSTOM_LOGIN_SLUG . '/' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p><?php esc_html_e( 'This plugin is active and managing the features listed below.', 'blueworx-enhancements' ); ?></p>

		<h2><?php esc_html_e( 'Custom Login URL', 'blueworx-enhancements' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'New Admin Login URL', 'blueworx-enhancements' ); ?>
				</th>
				<td>
					<input
						type="text"
						value="<?php echo esc_url( $custom_login_url ); ?>"
						readonly
						disabled
						class="regular-text"
						style="background:#f0f0f1; color:#3c434a; cursor:default;"
					/>
					<a href="<?php echo esc_url( $custom_login_url ); ?>" target="_blank" rel="noopener noreferrer" style="margin-left:10px;">
						<?php esc_html_e( 'Open', 'blueworx-enhancements' ); ?> &rarr;
					</a>
					<p class="description">
						<?php
						printf(
							/* translators: 1: /wp-admin  2: /wp-login.php */
							esc_html__( 'This is your new login URL. Bookmark it — the default %1$s and %2$s URLs are blocked and will redirect visitors to the homepage.', 'blueworx-enhancements' ),
							'<code>/wp-admin</code>',
							'<code>/wp-login.php</code>'
						);
						?>
					</p>
				</td>
			</tr>
		</table>
	</div>
	<?php
}

// ============================================================================
// 2. REQUEST INTERCEPTION — pure PHP, no rewrite rules.
//    Compatible with Nginx + Apache (Cloudways and similar stacks).
// ============================================================================

/**
 * Intercepts incoming requests early in the WordPress bootstrap to:
 *  - Serve wp-login.php when the custom slug is requested.
 *  - Block direct access to wp-login.php and redirect to homepage.
 *  - Block unauthenticated access to wp-admin and redirect to homepage.
 *
 * @return void
 */
function blueworx_intercept_requests() {
	// Do not interfere with AJAX, Cron, CLI, or REST API contexts.
	if (
		( defined( 'DOING_AJAX' )   && DOING_AJAX   ) ||
		( defined( 'DOING_CRON' )   && DOING_CRON   ) ||
		( defined( 'WP_CLI' )       && WP_CLI        ) ||
		( defined( 'REST_REQUEST' ) && REST_REQUEST  )
	) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path        = strtolower( wp_parse_url( sanitize_text_field( $request_uri ), PHP_URL_PATH ) );
	$path        = '/' . trim( $path, '/' );

	// ── 2a. Serve wp-login.php when the custom slug is requested ────────────
	if ( $path === '/' . BLUEWORX_CUSTOM_LOGIN_SLUG ) {
		$action          = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$allowed_actions = array( 'logout', 'lostpassword', 'rp', 'resetpass', 'confirmaction', 'postpass' );
		$is_post         = ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] );
		$is_allowed      = $is_post || in_array( $action, $allowed_actions, true ) || '' === $action;

		if ( $is_allowed ) {
			$_SERVER['SCRIPT_FILENAME'] = ABSPATH . 'wp-login.php';
			$_SERVER['PHP_SELF']        = '/wp-login.php';
			$_SERVER['SCRIPT_NAME']     = '/wp-login.php';
			require_once ABSPATH . 'wp-login.php'; // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude.FileIncludeFound
			exit;
		}
	}

	// ── 2b. Block direct requests to /wp-login.php ──────────────────────────
	$script_name  = isset( $_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : '';
	$is_login_php = (
		false !== strpos( $path, '/wp-login.php' ) ||
		false !== strpos( $script_name, 'wp-login.php' )
	);

	if ( $is_login_php ) {
		blueworx_redirect_home();
	}

	// ── 2c. Block unauthenticated access to /wp-admin ───────────────────────
	if ( 0 === strpos( $path, '/wp-admin' ) && ! is_user_logged_in() ) {
		blueworx_redirect_home();
	}
}
add_action( 'init', 'blueworx_intercept_requests', 1 );

// ============================================================================
// 3. FILTER login_url — ensure WordPress core always outputs the custom URL
//    (used in password-reset emails, logout redirects, etc.)
// ============================================================================

/**
 * Replaces the default wp-login.php login URL with the custom slug URL.
 *
 * @param string $login_url    The original login URL.
 * @param string $redirect     URL to redirect to after login.
 * @param bool   $force_reauth Whether to force re-authentication.
 * @return string The filtered login URL.
 */
function blueworx_custom_login_url( $login_url, $redirect, $force_reauth ) {
	$custom = home_url( '/' . BLUEWORX_CUSTOM_LOGIN_SLUG . '/' );

	if ( $redirect ) {
		$custom = add_query_arg( 'redirect_to', $redirect, $custom );
	}
	if ( $force_reauth ) {
		$custom = add_query_arg( 'reauth', '1', $custom );
	}

	return $custom;
}
add_filter( 'login_url', 'blueworx_custom_login_url', 10, 3 );

/**
 * Replaces generated wp-login.php URLs with the custom login slug.
 *
 * This keeps login, logout, password reset, and form POST actions on /admin_login/.
 *
 * @param string $url  The generated URL.
 * @param string $path The requested path.
 * @return string The filtered URL.
 */
function blueworx_replace_generated_login_url( $url, $path ) {
	$url  = (string) $url;
	$path = (string) $path;

	if ( false === strpos( $path, 'wp-login.php' ) && false === strpos( $url, 'wp-login.php' ) ) {
		return $url;
	}

	$query  = wp_parse_url( $url, PHP_URL_QUERY );
	$custom = home_url( '/' . BLUEWORX_CUSTOM_LOGIN_SLUG . '/' );

	if ( $query ) {
		$custom .= '?' . $query;
	}

	return $custom;
}
add_filter( 'site_url', 'blueworx_replace_generated_login_url', 10, 2 );
add_filter( 'network_site_url', 'blueworx_replace_generated_login_url', 10, 2 );

// ============================================================================
// 4. SECONDARY GUARD — template_redirect catches any wp-login.php requests
//    that reach WordPress after the init hook.
// ============================================================================

/**
 * Secondary safeguard to block direct wp-login.php access at template_redirect.
 *
 * @return void
 */
function blueworx_template_redirect_guard() {
	global $pagenow;

	if ( 'wp-login.php' !== $pagenow ) {
		return;
	}

	blueworx_redirect_home();
}
add_action( 'template_redirect', 'blueworx_template_redirect_guard' );

// ============================================================================
// HELPER
// ============================================================================

/**
 * Redirects the visitor to the site homepage and halts execution.
 *
 * @return void
 */
function blueworx_redirect_home() {
	wp_safe_redirect( home_url( '/' ), 301 );
	exit;
}

// ============================================================================
// 5. DISABLE COMMENTS COMPLETELY
// ============================================================================

/**
 * Closes comments on the front end for all post types.
 *
 * @param bool $open Whether comments are open.
 * @return bool Always false.
 */
function blueworx_disable_comments_status( $open ) {
	return false;
}
add_filter( 'comments_open', 'blueworx_disable_comments_status', 20 );
add_filter( 'pings_open', 'blueworx_disable_comments_status', 20 );

/**
 * Returns an empty comments array to suppress any existing comments from displaying.
 *
 * @param array $comments Existing comments.
 * @return array Always empty.
 */
function blueworx_disable_comments_hide_existing( $comments ) {
	$comments = array();
	return $comments;
}
add_filter( 'comments_array', 'blueworx_disable_comments_hide_existing', 10 );

/**
 * Removes comment-related items from the admin menu and redirects direct access.
 *
 * @return void
 */
function blueworx_disable_comments_admin_menu() {
	remove_menu_page( 'edit-comments.php' );
}
add_action( 'admin_menu', 'blueworx_disable_comments_admin_menu' );

/**
 * Redirects any direct attempt to access the comments admin page.
 *
 * @return void
 */
function blueworx_disable_comments_admin_redirect() {
	global $pagenow;
	if ( 'edit-comments.php' === $pagenow ) {
		wp_safe_redirect( admin_url() );
		exit;
	}
}
add_action( 'admin_init', 'blueworx_disable_comments_admin_redirect' );

/**
 * Removes comment-related dashboard widgets.
 *
 * @return void
 */
function blueworx_disable_comments_dashboard() {
	remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
}
add_action( 'admin_init', 'blueworx_disable_comments_dashboard' );

/**
 * Removes the Comments link from the admin bar.
 *
 * @param WP_Admin_Bar $wp_admin_bar The admin bar instance.
 * @return void
 */
function blueworx_disable_comments_admin_bar( $wp_admin_bar ) {
	$wp_admin_bar->remove_node( 'comments' );
}
add_action( 'admin_bar_menu', 'blueworx_disable_comments_admin_bar', 999 );

/**
 * Removes the Comments column from post/page list tables.
 *
 * @param array $columns Existing columns.
 * @return array Filtered columns.
 */
function blueworx_disable_comments_remove_column( $columns ) {
	unset( $columns['comments'] );
	return $columns;
}
add_filter( 'manage_posts_columns', 'blueworx_disable_comments_remove_column' );
add_filter( 'manage_pages_columns', 'blueworx_disable_comments_remove_column' );

/**
 * Removes comment support from all registered post types.
 *
 * @return void
 */
function blueworx_disable_comments_post_types_support() {
	$post_types = get_post_types();
	foreach ( $post_types as $post_type ) {
		if ( post_type_supports( $post_type, 'comments' ) ) {
			remove_post_type_support( $post_type, 'comments' );
			remove_post_type_support( $post_type, 'trackbacks' );
		}
	}
}
add_action( 'admin_init', 'blueworx_disable_comments_post_types_support' );

// ============================================================================
// 6. SUPPRESS ADMIN EMAIL NOTIFICATIONS
// ============================================================================

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

/**
 * Suppresses the new user registration notification email sent to the admin.
 *
 * @param string $to         The recipient email address.
 * @param int    $user_id    The new user ID.
 * @param string $notify     Who to notify: 'admin', 'user', or 'both'.
 * @return void
 */
function blueworx_disable_new_user_notification( $to, $user_id, $notify ) {
	// Intentionally suppressed — do not send new user registration emails to admin.
}
// Remove the default handler and replace with our no-op.
remove_action( 'register_new_user', 'wp_send_new_user_notifications' );
remove_action( 'edit_user_created_user', 'wp_send_new_user_notifications' );
add_action( 'register_new_user', 'blueworx_suppress_new_user_admin_email' );
add_action( 'edit_user_created_user', 'blueworx_suppress_new_user_admin_email', 10, 2 );

/**
 * Fires on new user creation — sends notification to user only, not admin.
 *
 * @param int    $user_id The new user's ID.
 * @param string $notify  Notification type passed by edit_user_created_user.
 * @return void
 */
function blueworx_suppress_new_user_admin_email( $user_id, $notify = 'user' ) {
	// Only notify the user, never the admin.
	wp_send_new_user_notifications( $user_id, 'user' );
}

/**
 * Suppresses the password change notification email sent to the admin.
 *
 * @param string  $pass_change_email Email data array (not used — filter returns false).
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
 * @param string  $email_change_email Email data array (not used — filter returns false).
 * @param WP_User $user               The user whose email changed.
 * @param WP_User $userdata           Updated user data.
 * @return bool False to prevent the email from sending.
 */
function blueworx_disable_email_change_notification( $email_change_email, $user, $userdata ) {
	return false;
}
add_filter( 'send_email_change_email', 'blueworx_disable_email_change_notification', 10, 3 );

// ============================================================================
// 7. HIDE PROFILE PAGE UI SECTIONS
// ============================================================================

/**
 * Injects CSS on user-edit.php and profile.php to hide unwanted profile sections.
 *
 * Targets:
 *  - .user-syntax-highlighting-wrap
 *  - .user-admin-color-wrap
 *  - .user-comment-shortcuts-wrap
 *  - .show-admin-bar
 *  - .user-language-wrap
 *
 * @return void
 */
function blueworx_hide_profile_sections() {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'profile', 'user-edit' ), true ) ) {
		return;
	}
	?>
	<style>
		.user-syntax-highlighting-wrap,
		.user-admin-color-wrap,
		.user-comment-shortcuts-wrap,
		.show-admin-bar,
		.user-language-wrap {
			display: none !important;
		}
	</style>
	<?php
}
add_action( 'admin_head', 'blueworx_hide_profile_sections' );
