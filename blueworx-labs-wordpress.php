<?php
/**
 * Plugin Name:       BlueWorx Labs | WordPress Enhancements
 * Plugin URI:        https://blueworx.io/
 * Description:       Site hardening, cache refresh, admin/profile enhancements, and the headless REST layer that powers BlueWorx headless WordPress sites.
 * Version:           1.15.1
 * Requires at least: 5.0
 * Requires PHP:      8.0
 * Author:            BlueWorx
 * Author URI:        https://profiles.wordpress.org/blueworx/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       blueworx-labs-wordpress
 * Domain Path:       /languages
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BLUEWORX_LABS_VERSION' ) ) {
	define( 'BLUEWORX_LABS_VERSION', '1.15.1' );
}

if ( ! defined( 'BLUEWORX_LABS_PATH' ) ) {
	define( 'BLUEWORX_LABS_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'BLUEWORX_LABS_URL' ) ) {
	define( 'BLUEWORX_LABS_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'BLUEWORX_CUSTOM_LOGIN_SLUG' ) ) {
	define( 'BLUEWORX_CUSTOM_LOGIN_SLUG', 'admin_login' );
}

require_once BLUEWORX_LABS_PATH . 'includes/helpers.php';
require_once BLUEWORX_LABS_PATH . 'includes/features.php';
require_once BLUEWORX_LABS_PATH . 'includes/upgrade.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-assets.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-theme.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-menu-groups.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-menu-icons.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-menu-badges.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-menu-order.php';
require_once BLUEWORX_LABS_PATH . 'includes/login-security.php';
require_once BLUEWORX_LABS_PATH . 'includes/cache-refresh.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-settings.php';
require_once BLUEWORX_LABS_PATH . 'includes/disable-comments.php';
require_once BLUEWORX_LABS_PATH . 'includes/email-notifications.php';
require_once BLUEWORX_LABS_PATH . 'includes/page-excerpts.php';
require_once BLUEWORX_LABS_PATH . 'includes/profile-cleanup.php';

// Headless REST layer (auth, accounts, content, CORS, revalidation, proxies).
require_once BLUEWORX_LABS_PATH . 'includes/rest/bootstrap.php';

register_activation_hook( __FILE__, 'blueworx_headless_install' );
register_deactivation_hook( __FILE__, 'blueworx_headless_clear_scheduled_events' );
