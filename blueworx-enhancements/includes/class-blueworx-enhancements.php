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
	}

	/**
	 * Register WP hooks.
	 */
	private function hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
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
