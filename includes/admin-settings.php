<?php
/**
 * Settings > BlueWorx admin screen.
 *
 * @package BlueWorxEnhancements
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	$cache_notice     = get_transient( 'blueworx_cache_refresh_notice' );
	$breeze_active    = blueworx_is_breeze_active();

	if ( $cache_notice ) {
		delete_transient( 'blueworx_cache_refresh_notice' );
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php if ( $cache_notice ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( $cache_notice ); ?></p>
			</div>
		<?php endif; ?>
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
						class="regular-text blueworx-readonly-url"
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

		<h2><?php esc_html_e( 'Cache Refresh', 'blueworx-enhancements' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Automatic Refresh', 'blueworx-enhancements' ); ?></th>
				<td>
					<strong><?php esc_html_e( 'Enabled', 'blueworx-enhancements' ); ?></strong>
					<p class="description">
						<?php esc_html_e( 'When a page or post changes, this plugin refreshes the edited page, homepage, and related listing pages.', 'blueworx-enhancements' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Breeze Cache', 'blueworx-enhancements' ); ?></th>
				<td>
					<strong>
						<?php echo $breeze_active ? esc_html__( 'Detected', 'blueworx-enhancements' ) : esc_html__( 'Not detected', 'blueworx-enhancements' ); ?>
					</strong>
					<p class="description">
						<?php esc_html_e( 'Cloudways Breeze/Varnish is used when available. WordPress cache clearing is used as a safe fallback.', 'blueworx-enhancements' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Manual Refresh', 'blueworx-enhancements' ); ?></th>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="blueworx_clear_cache_now" />
						<?php wp_nonce_field( 'blueworx_clear_cache_now' ); ?>
						<?php submit_button( esc_html__( 'Clear Cache Now', 'blueworx-enhancements' ), 'secondary', 'submit', false ); ?>
					</form>
				</td>
			</tr>
		</table>
	</div>
	<?php
}
