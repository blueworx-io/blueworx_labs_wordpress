<?php
/**
 * Core plugin class.
 *
 * @package BlueWorxEnhancements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
final class BlueWorx_Enhancements {

	/**
	 * Singleton instance.
	 *
	 * @var BlueWorx_Enhancements|null
	 */
	private static $instance = null;

	/**
	 * Return singleton instance.
	 *
	 * @return BlueWorx_Enhancements
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->hooks();
		$this->register_update_checker();
	}

	/**
	 * Register WP hooks.
	 */
	private function hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Configure GitHub update checker when dependency is present.
	 *
	 * @return void
	 */
	private function register_update_checker() {
		if ( ! class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
			return;
		}

		$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			BLUEWORX_ENHANCEMENTS_GITHUB_REPOSITORY,
			BLUEWORX_ENHANCEMENTS_FILE,
			'blueworx-enhancements'
		);

		$update_checker->setBranch( BLUEWORX_ENHANCEMENTS_GITHUB_BRANCH );

		if ( defined( 'BLUEWORX_ENHANCEMENTS_GITHUB_TOKEN' ) && BLUEWORX_ENHANCEMENTS_GITHUB_TOKEN ) {
			$update_checker->setAuthentication( BLUEWORX_ENHANCEMENTS_GITHUB_TOKEN );
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'blueworx-enhancements', false, dirname( plugin_basename( BLUEWORX_ENHANCEMENTS_FILE ) ) . '/languages' );
	}

	/**
	 * Activation hook callback.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! get_option( 'blueworx_enhancements_version' ) ) {
			add_option( 'blueworx_enhancements_version', BLUEWORX_ENHANCEMENTS_VERSION );
		}
	}

	/**
	 * Deactivation hook callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Reserved for future cleanup.
	}
}