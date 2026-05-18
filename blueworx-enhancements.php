<?php
/**
 * Plugin Name:       BlueWorx Enhancements
 * Plugin URI:        https://blueworx.io/
 * Description:       Hardens WordPress security, disables comments, suppresses admin email notifications, cleans up the user profile screen, and refreshes Cloudways cache after content changes.
 * Version:           1.4.26
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            BlueWorx
 * Author URI:        https://profiles.wordpress.org/blueworx/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       blueworx-enhancements
 * Domain Path:       /languages
 *
 * @package BlueWorxEnhancements
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BLUEWORX_ENHANCEMENTS_VERSION' ) ) {
	define( 'BLUEWORX_ENHANCEMENTS_VERSION', '1.4.26' );
}

if ( ! defined( 'BLUEWORX_ENHANCEMENTS_PATH' ) ) {
	define( 'BLUEWORX_ENHANCEMENTS_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'BLUEWORX_ENHANCEMENTS_URL' ) ) {
	define( 'BLUEWORX_ENHANCEMENTS_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'BLUEWORX_CUSTOM_LOGIN_SLUG' ) ) {
	define( 'BLUEWORX_CUSTOM_LOGIN_SLUG', 'admin_login' );
}

require_once BLUEWORX_ENHANCEMENTS_PATH . 'includes/helpers.php';
require_once BLUEWORX_ENHANCEMENTS_PATH . 'includes/admin-assets.php';
require_once BLUEWORX_ENHANCEMENTS_PATH . 'includes/user-roles.php';
require_once BLUEWORX_ENHANCEMENTS_PATH . 'includes/admin-menu-order.php';
require_once BLUEWORX_ENHANCEMENTS_PATH . 'includes/login-security.php';
require_once BLUEWORX_ENHANCEMENTS_PATH . 'includes/cache-refresh.php';
require_once BLUEWORX_ENHANCEMENTS_PATH . 'includes/admin-settings.php';
require_once BLUEWORX_ENHANCEMENTS_PATH . 'includes/disable-comments.php';
require_once BLUEWORX_ENHANCEMENTS_PATH . 'includes/email-notifications.php';
require_once BLUEWORX_ENHANCEMENTS_PATH . 'includes/page-excerpts.php';
require_once BLUEWORX_ENHANCEMENTS_PATH . 'includes/profile-cleanup.php';
