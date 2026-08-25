<?php
/**
 * Plugin Name:       BlueWorx Labs | WordPress Enhancements
 * Plugin URI:        https://blueworx.io/
 * Description:       Site hardening, admin and media tools, cache refresh, and profile enhancements.
 * Version:           1.67.0
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
	define( 'BLUEWORX_LABS_VERSION', '1.67.0' );
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
require_once BLUEWORX_LABS_PATH . 'includes/admin-design.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-assets.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-theme.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-menu-groups.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-menu-icons.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-menu-badges.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-menu-order.php';
require_once BLUEWORX_LABS_PATH . 'includes/login-security.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/sso.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/discovery.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/jwt.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/log.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/users.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/flow.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/ui.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/settings.php';
require_once BLUEWORX_LABS_PATH . 'includes/cache-refresh.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-settings.php';
require_once BLUEWORX_LABS_PATH . 'includes/guides.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-pages.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-guides.php';
require_once BLUEWORX_LABS_PATH . 'includes/disable-comments.php';
require_once BLUEWORX_LABS_PATH . 'includes/email-notifications.php';
require_once BLUEWORX_LABS_PATH . 'includes/page-excerpts.php';
require_once BLUEWORX_LABS_PATH . 'includes/translate.php';
require_once BLUEWORX_LABS_PATH . 'includes/profile-cleanup.php';
require_once BLUEWORX_LABS_PATH . 'includes/user-roles.php';
require_once BLUEWORX_LABS_PATH . 'includes/support-access.php';

require_once BLUEWORX_LABS_PATH . 'includes/admin-bar.php';
require_once BLUEWORX_LABS_PATH . 'includes/dashboard-widgets.php';
require_once BLUEWORX_LABS_PATH . 'includes/security-hardening.php';
require_once BLUEWORX_LABS_PATH . 'includes/robots-txt.php';
require_once BLUEWORX_LABS_PATH . 'includes/media-tools.php';
require_once BLUEWORX_LABS_PATH . 'includes/content-tools.php';
require_once BLUEWORX_LABS_PATH . 'includes/revisions.php';
require_once BLUEWORX_LABS_PATH . 'includes/login-session.php';
require_once BLUEWORX_LABS_PATH . 'includes/login-redirect.php';
require_once BLUEWORX_LABS_PATH . 'includes/view-as-role.php';

// Deactivation, not just uninstall: the support account is a near-administrator
// whose read-only guarantee comes entirely from this plugin's request-layer
// block. With the plugin switched off that block is gone but the account would
// remain, so it is removed the moment the plugin is deactivated.
register_deactivation_hook( __FILE__, 'blueworx_support_on_deactivate' );
