<?php
/**
 * Plugin Name: BlueWorx Enhancements
 * Description: Hardens login access by exposing a custom login URL and returning 404 for direct wp-admin/wp-login probing.
 * Version: 1.1.0
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
	private const VERSION = '1.1.0';
	private const UPDATE_CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Set this constant in wp-config.php as `owner/repo` to enable GitHub updates.
	 * Example: define( 'BWX_GITHUB_REPO', 'blueworx/blueworx-enhancements' );
	 */
	private const DEFAULT_GITHUB_REPO = '';

	public static function init(): void {
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );

		add_action( 'init', array( __CLASS__, 'register_login_rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_custom_login_route' ) );
		add_action( 'init', array( __CLASS__, 'block_wp_admin_for_guests' ), 0 );
		add_action( 'login_init', array( __CLASS__, 'block_direct_wp_login_access' ), 0 );

		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );

		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_plugin_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'inject_plugin_info' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'attach_auth_header_for_package_download' ), 10, 4 );
		add_filter( 'login_url', array( __CLASS__, 'filter_login_url' ), 10, 3 );
		add_filter( 'site_url', array( __CLASS__, 'filter_site_login_url' ), 10, 4 );
		add_filter( 'network_site_url', array( __CLASS__, 'filter_site_login_url' ), 10, 3 );
		add_filter( 'wp_redirect', array( __CLASS__, 'filter_login_redirect_url' ), 10, 2 );
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

		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
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
		$repo      = self::get_github_repo();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'BlueWorx', 'blueworx-enhancements' ); ?></h1>
			<div class="notice notice-success inline">
				<p><?php echo esc_html__( 'BlueWorx Enhancements is installed and active.', 'blueworx-enhancements' ); ?></p>
			</div>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Custom login URL', 'blueworx-enhancements' ); ?></th>
						<td><code><?php echo esc_html( $login_url ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Plugin version', 'blueworx-enhancements' ); ?></th>
						<td><code><?php echo esc_html( self::VERSION ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'GitHub updates', 'blueworx-enhancements' ); ?></th>
						<td>
							<?php if ( '' !== $repo ) : ?>
								<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
								<?php echo esc_html( sprintf( __( 'Enabled (%s)', 'blueworx-enhancements' ), $repo ) ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-warning" aria-hidden="true"></span>
								<?php echo esc_html__( 'Disabled. Set BWX_GITHUB_REPO in wp-config.php to enable.', 'blueworx-enhancements' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function filter_login_url( string $login_url, string $redirect, bool $force_reauth ): string {
		unset( $redirect, $force_reauth );
		return self::replace_wp_login_path( $login_url );
	}

	public static function filter_site_login_url( string $url, string $path, string $scheme, $blog_id = null ): string {
		unset( $path, $blog_id );

		if ( ! in_array( $scheme, array( 'login', 'login_post' ), true ) ) {
			return $url;
		}

		return self::replace_wp_login_path( $url );
	}

	public static function filter_login_redirect_url( string $location, int $status ): string {
		unset( $status );
		return self::replace_wp_login_path( $location );
	}

	private static function replace_wp_login_path( string $url ): string {
		$login_path = '/' . self::LOGIN_SLUG . '/';
		$url        = str_replace( 'wp-login.php', trim( $login_path, '/' ), $url );

		// Ensure pretty-slug format for replacements that removed .php but missed trailing slash.
		$url = preg_replace( '#/' . preg_quote( trim( $login_path, '/' ), '#' ) . '(\?|$)#', $login_path . '$1', $url );

		return is_string( $url ) ? $url : '';
	}

	public static function inject_plugin_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$release = self::get_latest_release();
		if ( null === $release || empty( $release['version'] ) || empty( $release['package'] ) ) {
			return $transient;
		}

		if ( version_compare( $release['version'], self::VERSION, '<=' ) ) {
			return $transient;
		}

		$plugin_file                    = plugin_basename( __FILE__ );
		$transient->response            = is_array( $transient->response ) ? $transient->response : array();
		$transient->response[ $plugin_file ] = (object) array(
			'slug'        => dirname( $plugin_file ),
			'plugin'      => $plugin_file,
			'new_version' => $release['version'],
			'url'         => $release['url'],
			'package'     => $release['package'],
		);

		return $transient;
	}

	public static function inject_plugin_info( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) ) {
			return $result;
		}

		if ( 'blueworx-enhancements' !== $args->slug ) {
			return $result;
		}

		$release = self::get_latest_release();
		if ( null === $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'BlueWorx Enhancements',
			'slug'          => 'blueworx-enhancements',
			'version'       => $release['version'],
			'author'        => 'BlueWorx',
			'homepage'      => $release['url'],
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => __( 'Security hardening for WordPress login routing.', 'blueworx-enhancements' ),
			),
		);
	}

	public static function attach_auth_header_for_package_download( $reply, string $package, $upgrader ) {
		$token = self::get_github_token();
		if ( '' === $token || false === strpos( $package, 'github.com' ) ) {
			return $reply;
		}

		add_filter(
			'http_request_args',
			static function ( array $args, string $url ) use ( $package, $token ) {
				if ( $url !== $package ) {
					return $args;
				}

				$args['headers'] = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
				$args['headers']['Authorization'] = 'Bearer ' . $token;
				$args['headers']['User-Agent']    = 'BlueWorx-Enhancements-Updater';

				return $args;
			},
			10,
			2
		);

		return $reply;
	}

	private static function get_latest_release(): ?array {
		$repo = self::get_github_repo();
		if ( '' === $repo ) {
			return null;
		}

		$cache_key = 'bwx_latest_release_' . md5( $repo );
		$cached    = get_site_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'BlueWorx-Enhancements-Updater',
		);
		$token = self::get_github_token();
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/releases/latest', $repo ),
			array(
				'timeout' => 15,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return null;
		}

		$package_url = '';
		if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( isset( $asset['browser_download_url'] ) && isset( $asset['name'] ) && preg_match( '/\.zip$/i', $asset['name'] ) ) {
					$package_url = $asset['browser_download_url'];
					break;
				}
			}
		}

		if ( '' === $package_url && isset( $data['zipball_url'] ) ) {
			$package_url = $data['zipball_url'];
		}

		$release = array(
			'version' => ltrim( (string) $data['tag_name'], 'vV' ),
			'url'     => isset( $data['html_url'] ) ? (string) $data['html_url'] : '',
			'package' => $package_url,
		);

		set_site_transient( $cache_key, $release, self::UPDATE_CACHE_TTL );
		return $release;
	}

	private static function get_github_repo(): string {
		$repo = defined( 'BWX_GITHUB_REPO' ) ? BWX_GITHUB_REPO : self::DEFAULT_GITHUB_REPO;
		$repo = trim( (string) $repo );

		if ( preg_match( '/^[\w.-]+\/[\w.-]+$/', $repo ) ) {
			return $repo;
		}

		return '';
	}

	private static function get_github_token(): string {
		$token = '';

		if ( defined( 'BWX_GITHUB_TOKEN' ) && is_string( BWX_GITHUB_TOKEN ) ) {
			$token = BWX_GITHUB_TOKEN;
		}

		$env_token = getenv( 'BWX_GITHUB_TOKEN' );
		if ( '' === $token && is_string( $env_token ) ) {
			$token = $env_token;
		}

		/**
		 * Allows secrets manager integrations.
		 *
		 * @param string $token Current token.
		 */
		$token = apply_filters( 'bwx_github_update_token', $token );

		return trim( (string) $token );
	}
}

BlueWorx_Enhancements::init();
