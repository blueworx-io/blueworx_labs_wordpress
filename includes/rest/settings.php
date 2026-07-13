<?php
/**
 * Headless REST layer — admin settings screen.
 *
 * Adds a "Headless" page under the BlueWorx menu for the non-secret settings.
 * Secrets are read only from wp-config constants and are shown here as
 * configured/not-configured status, never as editable values.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Headless submenu page.
 *
 * @return void
 */
function blueworx_headless_register_settings_page() {
	add_submenu_page(
		'blueworx-labs-wordpress',
		esc_html__( 'Headless', 'blueworx-labs-wordpress' ),
		esc_html__( 'Headless', 'blueworx-labs-wordpress' ),
		'manage_options',
		'blueworx-headless',
		'blueworx_headless_render_settings_page'
	);
}
add_action( 'admin_menu', 'blueworx_headless_register_settings_page', 20 );

/**
 * Saves the Headless settings.
 *
 * @return void
 */
function blueworx_headless_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_headless_save_settings' );

	$mode  = isset( $_POST['registration_mode'] ) ? sanitize_key( wp_unslash( $_POST['registration_mode'] ) ) : 'closed';
	$modes = array( 'open', 'invite', 'closed' );

	update_option( 'blueworx_headless_registration_mode', in_array( $mode, $modes, true ) ? $mode : 'closed' );
	update_option( 'blueworx_headless_email_verification_required', isset( $_POST['email_verification_required'] ) ? '1' : '0' );
	update_option( 'blueworx_headless_default_role', isset( $_POST['default_role'] ) ? sanitize_key( wp_unslash( $_POST['default_role'] ) ) : 'subscriber' );
	update_option( 'blueworx_headless_access_ttl', isset( $_POST['access_ttl'] ) ? absint( wp_unslash( $_POST['access_ttl'] ) ) : 3600 );
	update_option( 'blueworx_headless_refresh_ttl_days', isset( $_POST['refresh_ttl_days'] ) ? absint( wp_unslash( $_POST['refresh_ttl_days'] ) ) : 14 );
	update_option( 'blueworx_headless_login_max_attempts', isset( $_POST['login_max_attempts'] ) ? absint( wp_unslash( $_POST['login_max_attempts'] ) ) : 5 );
	update_option( 'blueworx_headless_login_window', isset( $_POST['login_window'] ) ? absint( wp_unslash( $_POST['login_window'] ) ) : 900 );
	update_option( 'blueworx_headless_allowed_origins', isset( $_POST['allowed_origins'] ) ? sanitize_textarea_field( wp_unslash( $_POST['allowed_origins'] ) ) : '' );
	update_option( 'blueworx_headless_frontend_url', isset( $_POST['frontend_url'] ) ? esc_url_raw( wp_unslash( $_POST['frontend_url'] ) ) : '' );
	update_option( 'blueworx_headless_revalidate_enabled', isset( $_POST['revalidate_enabled'] ) ? '1' : '0' );
	update_option( 'blueworx_headless_revalidate_url', isset( $_POST['revalidate_url'] ) ? esc_url_raw( wp_unslash( $_POST['revalidate_url'] ) ) : '' );
	update_option( 'blueworx_headless_surecart_enabled', isset( $_POST['surecart_enabled'] ) ? '1' : '0' );
	update_option( 'blueworx_headless_cpts', isset( $_POST['cpts'] ) ? sanitize_text_field( wp_unslash( $_POST['cpts'] ) ) : '' );

	set_transient( 'blueworx_headless_notice', __( 'Headless settings saved.', 'blueworx-labs-wordpress' ), 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-headless' ) );
	exit;
}
add_action( 'admin_post_blueworx_headless_save_settings', 'blueworx_headless_save_settings' );

/**
 * Generates a one-time invite token and stores its hash.
 *
 * @return void
 */
function blueworx_headless_generate_invite() {
	global $wpdb;

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_headless_generate_invite' );

	$email = isset( $_POST['invite_email'] ) ? sanitize_email( wp_unslash( $_POST['invite_email'] ) ) : '';
	$role  = isset( $_POST['invite_role'] ) ? sanitize_key( wp_unslash( $_POST['invite_role'] ) ) : '';
	$days  = isset( $_POST['invite_days'] ) ? absint( wp_unslash( $_POST['invite_days'] ) ) : 7;
	$days  = $days > 0 ? $days : 7;
	$raw   = bin2hex( random_bytes( 24 ) );

	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		blueworx_headless_invites_table(),
		array(
			'token_hash' => blueworx_headless_hash_token( $raw ),
			'email'      => $email ? $email : null,
			'role'       => $role ? $role : null,
			'created_by' => get_current_user_id(),
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
			'expires_at' => gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS ),
		),
		array( '%s', '%s', '%s', '%d', '%s', '%s' )
	);

	set_transient( 'blueworx_headless_new_invite', $raw, 60 );
	set_transient( 'blueworx_headless_notice', __( 'Invite created. Copy the token below now — it is shown only once.', 'blueworx-labs-wordpress' ), 60 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-headless' ) );
	exit;
}
add_action( 'admin_post_blueworx_headless_generate_invite', 'blueworx_headless_generate_invite' );

/**
 * Renders a constant status row.
 *
 * @param string $label    Human label.
 * @param bool   $is_set   Whether the constant is defined and non-empty.
 * @return void
 */
function blueworx_headless_render_constant_status( $label, $is_set ) {
	?>
	<tr>
		<th scope="row"><?php echo esc_html( $label ); ?></th>
		<td>
			<strong>
				<?php echo $is_set ? esc_html__( 'Configured', 'blueworx-labs-wordpress' ) : esc_html__( 'Not set', 'blueworx-labs-wordpress' ); ?>
			</strong>
		</td>
	</tr>
	<?php
}

/**
 * Renders the Headless settings page.
 *
 * @return void
 */
function blueworx_headless_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-labs-wordpress' ) );
	}

	$notice     = get_transient( 'blueworx_headless_notice' );
	$new_invite = get_transient( 'blueworx_headless_new_invite' );

	if ( $notice ) {
		delete_transient( 'blueworx_headless_notice' );
	}
	if ( $new_invite ) {
		delete_transient( 'blueworx_headless_new_invite' );
	}

	$mode  = blueworx_headless_setting( 'registration_mode' );
	$roles = wp_roles()->get_names();
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<?php if ( $new_invite ) : ?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'New invite token (shown once):', 'blueworx-labs-wordpress' ); ?></p>
				<p><input type="text" class="large-text code" readonly value="<?php echo esc_attr( $new_invite ); ?>" onclick="this.select();" /></p>
			</div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Secrets (wp-config.php constants)', 'blueworx-labs-wordpress' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Secrets are read only from wp-config.php and never stored in the database.', 'blueworx-labs-wordpress' ); ?></p>
		<table class="form-table" role="presentation">
			<?php
			blueworx_headless_render_constant_status( 'BLUEWORX_LABS_JWT_SECRET', blueworx_headless_auth_ready() );
			blueworx_headless_render_constant_status( 'BLUEWORX_LABS_SURECART_API_KEY', defined( 'BLUEWORX_LABS_SURECART_API_KEY' ) && '' !== (string) BLUEWORX_LABS_SURECART_API_KEY );
			blueworx_headless_render_constant_status( 'BLUEWORX_LABS_REVALIDATE_SECRET', defined( 'BLUEWORX_LABS_REVALIDATE_SECRET' ) && '' !== (string) BLUEWORX_LABS_REVALIDATE_SECRET );
			blueworx_headless_render_constant_status( 'BLUEWORX_LABS_ALLOWED_ORIGINS (override)', defined( 'BLUEWORX_LABS_ALLOWED_ORIGINS' ) && '' !== (string) BLUEWORX_LABS_ALLOWED_ORIGINS );
			?>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="blueworx_headless_save_settings" />
			<?php wp_nonce_field( 'blueworx_headless_save_settings' ); ?>

			<h2><?php esc_html_e( 'Accounts', 'blueworx-labs-wordpress' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="registration_mode"><?php esc_html_e( 'Registration mode', 'blueworx-labs-wordpress' ); ?></label></th>
					<td>
						<select name="registration_mode" id="registration_mode">
							<?php foreach ( array( 'closed', 'invite', 'open' ) as $option ) : ?>
								<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $mode, $option ); ?>><?php echo esc_html( ucfirst( $option ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Email verification', 'blueworx-labs-wordpress' ); ?></th>
					<td><label><input type="checkbox" name="email_verification_required" value="1" <?php checked( '1', blueworx_headless_setting( 'email_verification_required' ) ); ?> /> <?php esc_html_e( 'Require email confirmation before sign-in', 'blueworx-labs-wordpress' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="default_role"><?php esc_html_e( 'Default role', 'blueworx-labs-wordpress' ); ?></label></th>
					<td>
						<select name="default_role" id="default_role">
							<?php foreach ( $roles as $slug => $name ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( blueworx_headless_setting( 'default_role' ), $slug ); ?>><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Tokens & lockout', 'blueworx-labs-wordpress' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="access_ttl"><?php esc_html_e( 'Access token lifetime (seconds)', 'blueworx-labs-wordpress' ); ?></label></th>
					<td><input type="number" name="access_ttl" id="access_ttl" min="60" value="<?php echo esc_attr( blueworx_headless_setting( 'access_ttl' ) ); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="refresh_ttl_days"><?php esc_html_e( 'Refresh token lifetime (days)', 'blueworx-labs-wordpress' ); ?></label></th>
					<td><input type="number" name="refresh_ttl_days" id="refresh_ttl_days" min="1" value="<?php echo esc_attr( blueworx_headless_setting( 'refresh_ttl_days' ) ); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="login_max_attempts"><?php esc_html_e( 'Login attempts before lockout', 'blueworx-labs-wordpress' ); ?></label></th>
					<td><input type="number" name="login_max_attempts" id="login_max_attempts" min="1" value="<?php echo esc_attr( blueworx_headless_setting( 'login_max_attempts' ) ); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="login_window"><?php esc_html_e( 'Lockout window (seconds)', 'blueworx-labs-wordpress' ); ?></label></th>
					<td><input type="number" name="login_window" id="login_window" min="60" value="<?php echo esc_attr( blueworx_headless_setting( 'login_window' ) ); ?>" class="small-text" /></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Frontend & CORS', 'blueworx-labs-wordpress' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="frontend_url"><?php esc_html_e( 'Frontend URL', 'blueworx-labs-wordpress' ); ?></label></th>
					<td><input type="url" name="frontend_url" id="frontend_url" class="regular-text" value="<?php echo esc_attr( blueworx_headless_setting( 'frontend_url' ) ); ?>" placeholder="https://app.example.com" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="allowed_origins"><?php esc_html_e( 'Allowed origins', 'blueworx-labs-wordpress' ); ?></label></th>
					<td>
						<textarea name="allowed_origins" id="allowed_origins" rows="3" class="large-text code" placeholder="https://app.example.com"><?php echo esc_textarea( blueworx_headless_setting( 'allowed_origins' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One origin per line or comma-separated. Overridden by the BLUEWORX_LABS_ALLOWED_ORIGINS constant when set.', 'blueworx-labs-wordpress' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Revalidation & integrations', 'blueworx-labs-wordpress' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Revalidation webhook', 'blueworx-labs-wordpress' ); ?></th>
					<td><label><input type="checkbox" name="revalidate_enabled" value="1" <?php checked( '1', blueworx_headless_setting( 'revalidate_enabled' ) ); ?> /> <?php esc_html_e( 'Ping the frontend to revalidate changed content (never triggers a full build)', 'blueworx-labs-wordpress' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="revalidate_url"><?php esc_html_e( 'Revalidation URL', 'blueworx-labs-wordpress' ); ?></label></th>
					<td><input type="url" name="revalidate_url" id="revalidate_url" class="regular-text" value="<?php echo esc_attr( blueworx_headless_setting( 'revalidate_url' ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'SureCart proxy', 'blueworx-labs-wordpress' ); ?></th>
					<td><label><input type="checkbox" name="surecart_enabled" value="1" <?php checked( '1', blueworx_headless_setting( 'surecart_enabled' ) ); ?> /> <?php esc_html_e( 'Enable the SureCart proxy (requires the API key constant)', 'blueworx-labs-wordpress' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="cpts"><?php esc_html_e( 'Custom post types in REST', 'blueworx-labs-wordpress' ); ?></label></th>
					<td>
						<input type="text" name="cpts" id="cpts" class="regular-text" value="<?php echo esc_attr( blueworx_headless_setting( 'cpts' ) ); ?>" placeholder="portfolio, testimonial" />
						<p class="description"><?php esc_html_e( 'Comma-separated post-type keys to expose on the core REST API.', 'blueworx-labs-wordpress' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( esc_html__( 'Save Headless Settings', 'blueworx-labs-wordpress' ) ); ?>
		</form>

		<h2><?php esc_html_e( 'Create invite', 'blueworx-labs-wordpress' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="blueworx_headless_generate_invite" />
			<?php wp_nonce_field( 'blueworx_headless_generate_invite' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="invite_email"><?php esc_html_e( 'Pin to email (optional)', 'blueworx-labs-wordpress' ); ?></label></th>
					<td><input type="email" name="invite_email" id="invite_email" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="invite_role"><?php esc_html_e( 'Role (optional)', 'blueworx-labs-wordpress' ); ?></label></th>
					<td>
						<select name="invite_role" id="invite_role">
							<option value=""><?php esc_html_e( '— Default —', 'blueworx-labs-wordpress' ); ?></option>
							<?php foreach ( $roles as $slug => $name ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="invite_days"><?php esc_html_e( 'Expires in (days)', 'blueworx-labs-wordpress' ); ?></label></th>
					<td><input type="number" name="invite_days" id="invite_days" min="1" value="7" class="small-text" /></td>
				</tr>
			</table>
			<?php submit_button( esc_html__( 'Create Invite', 'blueworx-labs-wordpress' ), 'secondary' ); ?>
		</form>
	</div>
	<?php
}
