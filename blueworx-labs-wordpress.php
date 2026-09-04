<?php
/**
 * Plugin Name:       BlueWorx Labs | WordPress Enhancements
 * Plugin URI:        https://blueworx.io/
 * Description:       Site hardening, admin and media tools, cache refresh, and profile enhancements.
 * Version:           1.81.0
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

/*
 * Updates. The plugin watches this repo's GitHub releases and installs new
 * versions itself, so nobody uploads a zip. The house standard this follows is
 * documented in the foundation's docs/wordpress-auto-updates.md; the decision
 * to install rather than merely offer lives in includes/auto-updates.php.
 *
 * At file scope, and not inside a function, conditional or hook: the library's
 * "use" import below cannot be wrapped without a parse error.
 */
require_once plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$blueworx_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/blueworx-io/blueworx_labs_wordpress/',
	__FILE__,
	'blueworx-labs-wordpress' // Must equal the plugin's folder name on the site,
	                          // and the folder name inside the release zip. If
	                          // they disagree, an update installs alongside the
	                          // original as a second copy and deactivates it.
);

/*
 * This repo is public, so releases are readable without credentials and no site
 * needs a token. The guard is kept so that making the repo private later is a
 * line in each site's wp-config.php and no change here:
 *
 *     define( 'BLUEWORX_PLUGIN_UPDATE_TOKEN', 'github_pat_...' );
 */
if ( defined( 'BLUEWORX_PLUGIN_UPDATE_TOKEN' ) && BLUEWORX_PLUGIN_UPDATE_TOKEN ) {
	$blueworx_update_checker->setAuthentication( BLUEWORX_PLUGIN_UPDATE_TOKEN );
}

/*
 * Install the zip attached to the release, not GitHub's own source tarball.
 * That tarball unpacks to a folder named <repo>-<version>, which WordPress
 * would treat as a different plugin — and it carries every development file in
 * the repo besides.
 */
$blueworx_update_checker->getVcsApi()->enableReleaseAssets();

if ( ! defined( 'BLUEWORX_LABS_VERSION' ) ) {
	define( 'BLUEWORX_LABS_VERSION', '1.81.0' );
}

// The main plugin file's own path. Two things need it by name rather than by
// guessing at it: the update checker, which identifies the plugin to WordPress
// by this file, and the filter that turns this plugin's own auto-update on.
if ( ! defined( 'BLUEWORX_LABS_PLUGIN_FILE' ) ) {
	define( 'BLUEWORX_LABS_PLUGIN_FILE', __FILE__ );
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
require_once BLUEWORX_LABS_PATH . 'includes/sso/logs-screen.php';
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
require_once BLUEWORX_LABS_PATH . 'includes/readonly-access.php';
require_once BLUEWORX_LABS_PATH . 'includes/support-access.php';
require_once BLUEWORX_LABS_PATH . 'includes/external-access.php';
require_once BLUEWORX_LABS_PATH . 'includes/auto-updates.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-app-screens.php';

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
require_once BLUEWORX_LABS_PATH . 'includes/display-names.php';

/**
 * Puts back what deactivation took away.
 *
 * Only the external role, and only when the function is already on — which
 * means this is a reactivation, and deactivation removed the role on the way
 * out. A fresh install has the function off and registers the role when
 * somebody switches it on.
 *
 * @return void
 */
function blueworx_labs_on_activate() {
	blueworx_external_on_activate();
}
register_activation_hook( __FILE__, 'blueworx_labs_on_activate' );

/**
 * Tears down everything that must not outlive the plugin being switched off.
 *
 * Both read-only roles are near-administrator accounts whose safety comes from
 * the request-layer block in includes/readonly-access.php. With the plugin off
 * that block does not run, so the accounts must not be left standing.
 *
 * @return void
 */
function blueworx_labs_on_deactivate() {
	blueworx_support_on_deactivate();
	blueworx_external_on_deactivate();
}
register_deactivation_hook( __FILE__, 'blueworx_labs_on_deactivate' );
