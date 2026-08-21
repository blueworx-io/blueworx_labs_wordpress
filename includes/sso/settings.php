<?php
/**
 * Single sign-on: the settings screen.
 *
 * Rendered inside the BlueWorx Labs settings page as the detail panel for the
 * `sso` feature, and saved by the same handler as every other feature.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The endpoint overrides offered under "Advanced".
 *
 * @return array Labels keyed by discovery key.
 */
function blueworx_sso_endpoint_override_fields() {
	return array(
		'authorization_endpoint' => __( 'Authorization endpoint', 'blueworx-labs-wordpress' ),
		'token_endpoint'         => __( 'Token endpoint', 'blueworx-labs-wordpress' ),
		'userinfo_endpoint'      => __( 'User info endpoint', 'blueworx-labs-wordpress' ),
		'jwks_uri'               => __( 'Signing keys (JWKS) URL', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Renders the single sign-on detail controls.
 *
 * @return void
 */
function blueworx_sso_render_detail() {
	$status    = blueworx_sso_discovery_status();
	$has_secret = '' !== trim( (string) blueworx_sso_option( 'client_secret' ) );
	?>
	<p>
		<label for="blueworx_sso_issuer"><?php esc_html_e( 'Identity provider address', 'blueworx-labs-wordpress' ); ?></label><br />
		<input type="url" id="blueworx_sso_issuer" name="blueworx_sso_issuer" class="regular-text" value="<?php echo esc_attr( blueworx_sso_option( 'issuer' ) ); ?>" placeholder="https://login.example.com" />
		<span class="description"><?php esc_html_e( 'The sign-in service address your provider gave you.', 'blueworx-labs-wordpress' ); ?></span>
	</p>
	<p>
		<label for="blueworx_sso_client_id"><?php esc_html_e( 'Client ID', 'blueworx-labs-wordpress' ); ?></label><br />
		<input type="text" id="blueworx_sso_client_id" name="blueworx_sso_client_id" class="regular-text" value="<?php echo esc_attr( blueworx_sso_option( 'client_id' ) ); ?>" />
	</p>
	<p>
		<label for="blueworx_sso_client_secret"><?php esc_html_e( 'Client secret', 'blueworx-labs-wordpress' ); ?></label><br />
		<?php
		/*
		 * Rendered empty every time, never with the stored value. A secret that can
		 * be read back out of this screen is a secret anyone who reaches the screen
		 * can walk off with. An empty box on save means "leave it alone", so saving
		 * any other setting cannot wipe it either.
		 */
		?>
		<input type="password" id="blueworx_sso_client_secret" name="blueworx_sso_client_secret" class="regular-text" value="" autocomplete="new-password" />
		<span class="description">
			<?php
			echo $has_secret
				? esc_html__( 'A secret is saved. Leave blank to keep it, or type a new one to replace it.', 'blueworx-labs-wordpress' )
				: esc_html__( 'No secret saved yet.', 'blueworx-labs-wordpress' );
			?>
		</span>
	</p>
	<p>
		<label for="blueworx_sso_button_label"><?php esc_html_e( 'Button text', 'blueworx-labs-wordpress' ); ?></label><br />
		<input type="text" id="blueworx_sso_button_label" name="blueworx_sso_button_label" class="regular-text" value="<?php echo esc_attr( blueworx_sso_option( 'button_label' ) ); ?>" placeholder="<?php esc_attr_e( 'Sign in with single sign-on', 'blueworx-labs-wordpress' ); ?>" />
	</p>
	<p>
		<label for="blueworx_sso_register_button_label"><?php esc_html_e( 'Joining button text', 'blueworx-labs-wordpress' ); ?></label><br />
		<input type="text" id="blueworx_sso_register_button_label" name="blueworx_sso_register_button_label" class="regular-text" value="<?php echo esc_attr( blueworx_sso_option( 'register_button_label' ) ); ?>" placeholder="<?php esc_attr_e( 'Join with single sign-on', 'blueworx-labs-wordpress' ); ?>" />
	</p>
	<p>
		<label>
			<input type="checkbox" name="blueworx_sso_auto_register" value="1" <?php checked( '1', blueworx_sso_option( 'auto_register', '0' ) ); ?> />
			<?php esc_html_e( 'Let the joining button create an account for someone who does not have one', 'blueworx-labs-wordpress' ); ?>
		</label><br />
		<span class="description"><?php esc_html_e( 'Signing in never creates an account, whatever this is set to.', 'blueworx-labs-wordpress' ); ?></span>
	</p>
	<p>
		<label for="blueworx_sso_default_role"><?php esc_html_e( 'Role for new accounts', 'blueworx-labs-wordpress' ); ?></label><br />
		<select id="blueworx_sso_default_role" name="blueworx_sso_default_role">
			<?php foreach ( blueworx_sso_role_choices() as $slug => $label ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, blueworx_sso_option( 'default_role', 'subscriber' ) ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<span class="description"><?php esc_html_e( 'Administrator is never offered: signing in must not be able to hand out the keys to the site.', 'blueworx-labs-wordpress' ); ?></span>
	</p>
	<p>
		<label for="blueworx_sso_redirect_after_login"><?php esc_html_e( 'Send people here after signing in', 'blueworx-labs-wordpress' ); ?></label><br />
		<input type="url" id="blueworx_sso_redirect_after_login" name="blueworx_sso_redirect_after_login" class="regular-text" value="<?php echo esc_attr( blueworx_sso_option( 'redirect_after_login' ) ); ?>" placeholder="<?php echo esc_attr( admin_url() ); ?>" />
	</p>
	<p>
		<label for="blueworx_sso_redirect_after_register"><?php esc_html_e( 'Send people here after joining', 'blueworx-labs-wordpress' ); ?></label><br />
		<input type="url" id="blueworx_sso_redirect_after_register" name="blueworx_sso_redirect_after_register" class="regular-text" value="<?php echo esc_attr( blueworx_sso_option( 'redirect_after_register' ) ); ?>" />
		<span class="description"><?php esc_html_e( 'Leave blank to use the same page as signing in.', 'blueworx-labs-wordpress' ); ?></span>
	</p>
	<p>
		<label for="blueworx_sso_no_account_url"><?php esc_html_e( 'Send people here when they sign in and have no account', 'blueworx-labs-wordpress' ); ?></label><br />
		<input type="url" id="blueworx_sso_no_account_url" name="blueworx_sso_no_account_url" class="regular-text" value="<?php echo esc_attr( blueworx_sso_option( 'no_account_url' ) ); ?>" />
		<span class="description"><?php esc_html_e( 'Your joining page. Leave blank to show the usual "we could not sign you in" message instead.', 'blueworx-labs-wordpress' ); ?></span>
	</p>

	<p>
		<strong><?php esc_html_e( 'Give your provider this address:', 'blueworx-labs-wordpress' ); ?></strong>
		<code class="blueworx-sso-callback-url"><?php echo esc_html( blueworx_sso_callback_url() ); ?></code>
	</p>
	<p>
		<strong><?php esc_html_e( 'Buttons:', 'blueworx-labs-wordpress' ); ?></strong>
		<code>[blueworx_sso_button]</code>
		<?php esc_html_e( 'to sign in, and', 'blueworx-labs-wordpress' ); ?>
		<code>[blueworx_sso_button intent="register"]</code>
		<?php esc_html_e( 'to join. The sign-in button is added to the login screen for you.', 'blueworx-labs-wordpress' ); ?>
	</p>
	<p>
		<strong><?php esc_html_e( 'Connection:', 'blueworx-labs-wordpress' ); ?></strong>
		<span class="blueworx-sso-status"><?php echo esc_html( $status['message'] ); ?></span>
	</p>

	<?php blueworx_sso_render_log(); ?>

	<details class="blueworx-sso-advanced">
		<summary><?php esc_html_e( 'Advanced', 'blueworx-labs-wordpress' ); ?></summary>
		<p>
			<label for="blueworx_sso_scope"><?php esc_html_e( 'Scope', 'blueworx-labs-wordpress' ); ?></label><br />
			<input type="text" id="blueworx_sso_scope" name="blueworx_sso_scope" class="regular-text" value="<?php echo esc_attr( blueworx_sso_option( 'scope' ) ); ?>" placeholder="openid email profile" />
			<span class="description"><?php esc_html_e( 'Changing this may mean people have to approve the connection again.', 'blueworx-labs-wordpress' ); ?></span>
		</p>
		<p>
			<label for="blueworx_sso_redirect_uri"><?php esc_html_e( 'Return address registered with the provider', 'blueworx-labs-wordpress' ); ?></label><br />
			<input type="url" id="blueworx_sso_redirect_uri" name="blueworx_sso_redirect_uri" class="regular-text" value="<?php echo esc_attr( blueworx_sso_option( 'redirect_uri' ) ); ?>" placeholder="<?php echo esc_attr( blueworx_sso_callback_url() ); ?>" />
			<span class="description"><?php esc_html_e( 'Only set this when the provider has an older address registered that cannot be changed.', 'blueworx-labs-wordpress' ); ?></span>
		</p>
		<p>
			<label for="blueworx_sso_signup_prompt"><?php esc_html_e( 'Signup prompt', 'blueworx-labs-wordpress' ); ?></label><br />
			<input type="text" id="blueworx_sso_signup_prompt" name="blueworx_sso_signup_prompt" class="regular-text" value="<?php echo esc_attr( blueworx_sso_option( 'signup_prompt', 'signup' ) ); ?>" placeholder="signup" />
			<span class="description"><?php esc_html_e( 'Asks the provider to open on its "create an account" screen when someone joins. Empty it if your provider refuses the request.', 'blueworx-labs-wordpress' ); ?></span>
		</p>
		<p>
			<label for="blueworx_sso_pkce"><?php esc_html_e( 'Proof key (PKCE)', 'blueworx-labs-wordpress' ); ?></label><br />
			<select id="blueworx_sso_pkce" name="blueworx_sso_pkce">
				<?php
				foreach ( array(
					'auto' => __( 'Use it when the provider supports it', 'blueworx-labs-wordpress' ),
					'on'   => __( 'Always use it', 'blueworx-labs-wordpress' ),
					'off'  => __( 'Never use it', 'blueworx-labs-wordpress' ),
				) as $value => $label ) :
					?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, blueworx_sso_option( 'pkce', 'auto' ) ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php foreach ( blueworx_sso_endpoint_override_fields() as $key => $label ) : ?>
			<p>
				<label for="<?php echo esc_attr( 'blueworx_sso_' . $key . '_override' ); ?>"><?php echo esc_html( $label ); ?></label><br />
				<input type="url" id="<?php echo esc_attr( 'blueworx_sso_' . $key . '_override' ); ?>" name="<?php echo esc_attr( 'blueworx_sso_' . $key . '_override' ); ?>" class="regular-text" value="<?php echo esc_attr( blueworx_sso_option( $key . '_override' ) ); ?>" />
			</p>
		<?php endforeach; ?>
		<p class="description"><?php esc_html_e( 'Leave the addresses blank unless your provider does not publish its own configuration.', 'blueworx-labs-wordpress' ); ?></p>
	</details>
	<?php
}

/**
 * The roles offered for new accounts.
 *
 * Administrator is deliberately absent.
 *
 * @return array Role labels keyed by slug.
 */
function blueworx_sso_role_choices() {
	$choices = array();

	foreach ( get_editable_roles() as $slug => $role ) {
		if ( 'administrator' === $slug ) {
			continue;
		}

		$choices[ $slug ] = translate_user_role( $role['name'] );
	}

	return $choices;
}

/**
 * Renders the recent attempts.
 *
 * @return void
 */
function blueworx_sso_render_log() {
	$entries = blueworx_sso_get_log();

	if ( empty( $entries ) ) {
		return;
	}

	echo '<p><strong>' . esc_html__( 'Recent sign-ins:', 'blueworx-labs-wordpress' ) . '</strong></p><ul class="blueworx-sso-log">';

	foreach ( array_slice( $entries, 0, 5 ) as $entry ) {
		printf(
			'<li>%1$s — %2$s <span class="description">%3$s</span></li>',
			esc_html( wp_date( 'j M Y H:i', (int) $entry['time'] ) ),
			'success' === $entry['outcome'] ? esc_html__( 'signed in', 'blueworx-labs-wordpress' ) : esc_html__( 'failed', 'blueworx-labs-wordpress' ),
			esc_html( $entry['detail'] )
		);
	}

	echo '</ul>';
}

/**
 * Saves the single sign-on settings.
 *
 * The caller has already verified the nonce and the capability.
 *
 * @param array $posted Raw $_POST.
 * @return void
 */
function blueworx_sso_save_settings( $posted ) {
	$text_fields = array( 'client_id', 'button_label', 'register_button_label', 'scope', 'signup_prompt' );

	foreach ( $text_fields as $field ) {
		$value = isset( $posted[ 'blueworx_sso_' . $field ] ) ? sanitize_text_field( wp_unslash( $posted[ 'blueworx_sso_' . $field ] ) ) : '';
		update_option( 'blueworx_sso_' . $field, $value, false );
	}

	$url_fields = array( 'issuer', 'redirect_uri', 'redirect_after_login', 'redirect_after_register', 'no_account_url' );

	foreach ( blueworx_sso_endpoint_override_fields() as $key => $unused_label ) {
		$url_fields[] = $key . '_override';
	}

	$previous_issuer = blueworx_sso_option( 'issuer' );

	foreach ( $url_fields as $field ) {
		$value = isset( $posted[ 'blueworx_sso_' . $field ] ) ? esc_url_raw( trim( wp_unslash( $posted[ 'blueworx_sso_' . $field ] ) ) ) : '';
		update_option( 'blueworx_sso_' . $field, $value, false );
	}

	// A changed provider makes the cached configuration and keys wrong, and a
	// stale key set reads exactly like a broken connection.
	if ( $previous_issuer !== blueworx_sso_option( 'issuer' ) ) {
		delete_transient( 'blueworx_sso_discovery_' . md5( untrailingslashit( (string) $previous_issuer ) ) );
		delete_transient( 'blueworx_sso_jwks' );
	}

	/*
	 * The secret is write-only. An empty box means "keep what is stored", so
	 * saving any other setting on this screen cannot wipe it.
	 */
	$secret = isset( $posted['blueworx_sso_client_secret'] ) ? trim( (string) wp_unslash( $posted['blueworx_sso_client_secret'] ) ) : '';

	if ( '' !== $secret ) {
		update_option( 'blueworx_sso_client_secret', $secret, false );
	}

	update_option( 'blueworx_sso_auto_register', isset( $posted['blueworx_sso_auto_register'] ) ? '1' : '0', false );

	$role    = isset( $posted['blueworx_sso_default_role'] ) ? sanitize_key( wp_unslash( $posted['blueworx_sso_default_role'] ) ) : 'subscriber';
	$choices = blueworx_sso_role_choices();
	update_option( 'blueworx_sso_default_role', isset( $choices[ $role ] ) ? $role : 'subscriber', false );

	$pkce = isset( $posted['blueworx_sso_pkce'] ) ? sanitize_key( wp_unslash( $posted['blueworx_sso_pkce'] ) ) : 'auto';
	update_option( 'blueworx_sso_pkce', in_array( $pkce, array( 'auto', 'on', 'off' ), true ) ? $pkce : 'auto', false );
}
