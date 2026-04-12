<?php
/**
 * Plugin Name: BlueWorx Enhancements
 * Plugin URI: https://example.com/blueworx-enhancements
 * Description: Starter plugin for BlueWorx custom enhancements.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: BlueWorx
 * Author URI: https://example.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: blueworx-enhancements
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BLUEWORX_ENHANCEMENTS_VERSION', '0.1.0' );
define( 'BLUEWORX_ENHANCEMENTS_FILE', __FILE__ );
define( 'BLUEWORX_ENHANCEMENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BLUEWORX_ENHANCEMENTS_URL', plugin_dir_url( __FILE__ ) );
define( 'BLUEWORX_ENHANCEMENTS_GITHUB_REPOSITORY', 'https://github.com/REPLACE_WITH_YOUR_ORG/REPLACE_WITH_YOUR_REPO' );
define( 'BLUEWORX_ENHANCEMENTS_GITHUB_BRANCH', 'main' );

$blueworx_autoload_path = BLUEWORX_ENHANCEMENTS_PATH . 'vendor/autoload.php';
if ( file_exists( $blueworx_autoload_path ) ) {
	require_once $blueworx_autoload_path;
}

require_once BLUEWORX_ENHANCEMENTS_PATH . 'includes/class-blueworx-enhancements.php';

/**
 * Boot the plugin singleton.
 *
 * @return BlueWorx_Enhancements
 */
function blueworx_enhancements() {
	return BlueWorx_Enhancements::instance();
}

register_activation_hook( __FILE__, array( 'BlueWorx_Enhancements', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BlueWorx_Enhancements', 'deactivate' ) );

blueworx_enhancements();
