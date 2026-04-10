<?php
/**
 * Plugin Name: BlueWorx Enhancements
 * Description: Hardens login access by exposing a custom login URL and returning 404 for direct wp-admin/wp-login probing.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: BlueWorx
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: blueworx-enhancements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BlueWorx_Enhancements {
	private const LOGIN_SLUG = 'admin_dashboard';

	public static function init(): void {
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );

		add_action( 'init', array( __CLASS__, 'register_login_rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_custom_login_route' ) );
		add_action( 'init', array( __CLASS__, 'block_wp_admin_for_guests' ), 0 );
		add_action( 'login_init', array( __CLASS__, 'block_direct_wp_login_access' ), 0 );

		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
	}

	public static function activate(): void {
		self::register_login_rewrite();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function register_login_rewrite(): void {
		add_rewrite_rule(
			'^' . self::LOGIN_SLUG . '/?$',
			'index.php?bwx_custom_login=1',
			'top'
		);
	}

	public static function register_query_vars( array $vars ): array {
		$vars[] = 'bwx_custom_login';
		return $vars;
	}

	public static function handle_custom_login_route(): void {
		if ( '1' !== get_query_var( 'bwx_custom_login' ) ) {
			return;
		}

		define( 'BWX_CUSTOM_LOGIN_REQUEST', true );
		require_once ABSPATH . 'wp-login.php';
		exit;
	}

	public static function block_wp_admin_for_guests(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $request_path ) ) {
			return;
		}

		if ( 0 !== strpos( $request_path, '/wp-admin' ) ) {
			return;
		}

		if ( '/wp-admin/admin-ajax.php' === $request_path || '/wp-admin/admin-post.php' === $request_path ) {
			return;
		}

		self::send_404();
	}

	public static function block_direct_wp_login_access(): void {
		if ( defined( 'BWX_CUSTOM_LOGIN_REQUEST' ) && BWX_CUSTOM_LOGIN_REQUEST ) {
			return;
		}

		self::send_404();
	}

	private static function send_404(): void {
		status_header( 404 );
		nocache_headers();
		wp_die(
			esc_html__( '404 Not Found', 'blueworx-enhancements' ),
			esc_html__( 'Not Found', 'blueworx-enhancements' ),
			array( 'response' => 404 )
		);
	}

	public static function register_settings_page(): void {
		add_options_page(
			esc_html__( 'BlueWorx', 'blueworx-enhancements' ),
			esc_html__( 'BlueWorx', 'blueworx-enhancements' ),
			'manage_options',
			'blueworx-enhancements',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$login_url = home_url( '/' . self::LOGIN_SLUG . '/' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'BlueWorx', 'blueworx-enhancements' ); ?></h1>
			<div class="notice notice-success inline">
				<p>
					<?php
					echo esc_html__( 'BlueWorx Enhancements is installed and active.', 'blueworx-enhancements' );
					?>
				</p>
			</div>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Custom login URL', 'blueworx-enhancements' ); ?></th>
						<td>
							<code><?php echo esc_html( $login_url ); ?></code>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}

BlueWorx_Enhancements::init();
