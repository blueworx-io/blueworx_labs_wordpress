<?php
/**
 * Single sign-on: the settings screen.
 *
 * Rendered on the Single sign-on screen, which owns every setting here and
 * saves them through a handler of its own. Enhancements keeps only the switch
 * that turns the whole function on and off.
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
		'end_session_endpoint'   => __( 'Sign-out endpoint', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Builds one single sign-on text field.
 *
 * Every field on this panel is the same shape — a labelled box with a sentence
 * under it — so it is worth one helper rather than fifteen near-copies.
 *
 * @param array $args {
 *     @type string $key         Option key, also the field name after the
 *                               blueworx_sso_ prefix.
 *     @type string $label       Label text.
 *     @type string $type        Input type. Default url.
 *     @type string $default     Option default.
 *     @type string $placeholder Placeholder text.
 *     @type string $help        Sentence under the control.
 *     @type bool   $mono        Whether the value is code-shaped.
 * }
 * @return string HTML.
 */
function blueworx_sso_text_field( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'key'         => '',
			'label'       => '',
			'type'        => 'url',
			'default'     => '',
			'placeholder' => '',
			'help'        => '',
			'mono'        => false,
		)
	);

	$id = 'blueworx_sso_' . $args['key'];

	return blueworx_ds_field(
		array(
			'label'   => $args['label'],
			'for'     => $id,
			'help'    => $args['help'],
			'control' => blueworx_ds_input(
				array(
					'type'  => $args['type'],
					'name'  => $id,
					'id'    => $id,
					'value' => blueworx_sso_option( $args['key'], $args['default'] ),
					'mono'  => $args['mono'],
					'attrs' => '' !== $args['placeholder'] ? array( 'placeholder' => $args['placeholder'] ) : array(),
				)
			),
		)
	);
}

/**
 * The providers offered by name, and how each one's address is built.
 *
 * A preset is only a shape for the issuer address — everything after it is the
 * same OpenID Connect discovery every provider answers. "Any provider" is what
 * this screen has always been, and stays the fallback so no site that already
 * works stops working.
 *
 * @return array Provider definitions keyed by key.
 */
function blueworx_sso_providers() {
	return array(
		'custom' => array(
			'label'  => __( 'Any OpenID Connect provider', 'blueworx-labs-wordpress' ),
			'issuer' => '',
			'hint'   => __( 'The sign-in service address your provider gave you.', 'blueworx-labs-wordpress' ),
			'token'  => '',
		),
		'entra'  => array(
			'label'  => __( 'Microsoft Entra ID', 'blueworx-labs-wordpress' ),
			'issuer' => 'https://login.microsoftonline.com/{tenant}/v2.0',
			'hint'   => __( 'Paste your tenant ID — the long code in the Entra portal, under the app registration.', 'blueworx-labs-wordpress' ),
			'token'  => 'tenant',
		),
		'google' => array(
			'label'  => __( 'Google Workspace', 'blueworx-labs-wordpress' ),
			'issuer' => 'https://accounts.google.com',
			'hint'   => __( 'Nothing else to fill in here. The client ID and secret come from the Google Cloud console.', 'blueworx-labs-wordpress' ),
			'token'  => '',
		),
		'okta'   => array(
			'label'  => __( 'Okta', 'blueworx-labs-wordpress' ),
			'issuer' => 'https://{domain}/oauth2/default',
			'hint'   => __( 'Your Okta domain, such as example.okta.com.', 'blueworx-labs-wordpress' ),
			'token'  => 'domain',
		),
	);
}

/**
 * The provider this site is set up with.
 *
 * @return string Provider key.
 */
function blueworx_sso_provider() {
	$key = (string) blueworx_sso_option( 'provider', 'custom' );

	return isset( blueworx_sso_providers()[ $key ] ) ? $key : 'custom';
}

/**
 * Builds the issuer address from a provider and whatever it needs filling in.
 *
 * Returns an empty string when a provider that needs a tenant or a domain has
 * not been given one. An empty issuer is what the rest of this module already
 * treats as "not configured", so a half-filled provider disables sign-on rather
 * than producing an address that cannot resolve.
 *
 * @param string $provider Provider key.
 * @param string $token    Tenant id or domain, where the provider needs one.
 * @param string $issuer   The address typed in directly, for "any provider".
 * @return string Issuer address.
 */
function blueworx_sso_build_issuer( $provider, $token, $issuer ) {
	$providers = blueworx_sso_providers();

	if ( ! isset( $providers[ $provider ] ) || '' === $providers[ $provider ]['issuer'] ) {
		return $issuer;
	}

	$template = $providers[ $provider ]['issuer'];

	if ( '' === $providers[ $provider ]['token'] ) {
		return $template;
	}

	$token = trim( $token );

	if ( '' === $token ) {
		return '';
	}

	return str_replace( '{' . $providers[ $provider ]['token'] . '}', rawurlencode( $token ), $template );
}

/**
 * Renders the single sign-on detail controls.
 *
 * @return void
 */
function blueworx_sso_render_detail() {
	$status     = blueworx_sso_discovery_status();
	$has_secret = '' !== trim( (string) blueworx_sso_option( 'client_secret' ) );

	$provider  = blueworx_sso_provider();
	$providers = blueworx_sso_providers();
	$choices   = array();

	foreach ( $providers as $key => $definition ) {
		$choices[ $key ] = $definition['label'];
	}

	// Pick the provider by name. Whoever sets this up knows they use Entra;
	// asking them for a discovery URL asks them to go and find one.
	$fields = blueworx_ds_field(
		array(
			'label'   => __( 'Provider', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_sso_provider',
			'control' => blueworx_ds_select(
				array(
					'name'     => 'blueworx_sso_provider',
					'id'       => 'blueworx_sso_provider',
					'options'  => $choices,
					'selected' => $provider,
				)
			),
		)
	);

	if ( ! empty( $providers[ $provider ]['token'] ) ) {
		$fields .= blueworx_sso_text_field(
			array(
				'key'   => 'provider_token',
				'label' => 'entra' === $provider
					? __( 'Tenant ID', 'blueworx-labs-wordpress' )
					: __( 'Your provider domain', 'blueworx-labs-wordpress' ),
				'help'  => $providers[ $provider ]['hint'],
				'mono'  => true,
			)
		);
	} elseif ( 'custom' === $provider ) {
		$fields .= blueworx_sso_text_field(
			array(
				'key'         => 'issuer',
				'label'       => __( 'Identity provider address', 'blueworx-labs-wordpress' ),
				'placeholder' => 'https://login.example.com',
				'help'        => $providers[ $provider ]['hint'],
			)
		);
	} else {
		$fields .= '<p class="bw-field__help">' . esc_html( $providers[ $provider ]['hint'] ) . '</p>';
	}

	$fields .= blueworx_sso_text_field(
		array(
			'key'   => 'client_id',
			'label' => __( 'Client ID', 'blueworx-labs-wordpress' ),
			'type'  => 'text',
			'mono'  => true,
		)
	);

	/*
	 * Rendered empty every time, never with the stored value. A secret that can
	 * be read back out of this screen is a secret anyone who reaches the screen
	 * can walk off with. An empty box on save means "leave it alone", so saving
	 * any other setting cannot wipe it either.
	 */
	$fields .= blueworx_ds_field(
		array(
			'label'   => __( 'Client secret', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_sso_client_secret',
			'control' => blueworx_ds_input(
				array(
					'type'  => 'password',
					'name'  => 'blueworx_sso_client_secret',
					'id'    => 'blueworx_sso_client_secret',
					'value' => '',
					'attrs' => array( 'autocomplete' => 'new-password' ),
				)
			),
			'help'    => $has_secret
				? __( 'A secret is saved. Leave blank to keep it, or type a new one to replace it.', 'blueworx-labs-wordpress' )
				: __( 'No secret saved yet.', 'blueworx-labs-wordpress' ),
		)
	);

	$fields .= blueworx_sso_text_field(
		array(
			'key'         => 'button_label',
			'label'       => __( 'Button text', 'blueworx-labs-wordpress' ),
			'type'        => 'text',
			'placeholder' => __( 'Sign in with single sign-on', 'blueworx-labs-wordpress' ),
		)
	);

	$fields .= blueworx_sso_text_field(
		array(
			'key'         => 'register_button_label',
			'label'       => __( 'Joining button text', 'blueworx-labs-wordpress' ),
			'type'        => 'text',
			'placeholder' => __( 'Join with single sign-on', 'blueworx-labs-wordpress' ),
		)
	);

	// Directly above the joining switch, because it is the sentence that makes
	// that switch safe to turn on: a provider like Google vouches for the whole
	// world, and "anyone with an account there" is rarely who the site means.
	$fields .= blueworx_sso_text_field(
		array(
			'key'         => 'allowed_domains',
			'label'       => __( 'Only these email domains may get an account', 'blueworx-labs-wordpress' ),
			'type'        => 'text',
			'placeholder' => 'example.com, example.co.uk',
			'mono'        => true,
			'help'        => __( 'Separate them with commas. Leave blank to let in anyone your provider signs in — which, with a provider like Google, means anyone at all. People who already have an account keep it; take their access away on the Users screen.', 'blueworx-labs-wordpress' ),
		)
	);

	$fields .= blueworx_ds_field(
		array(
			'control' => blueworx_ds_checkbox(
				array(
					'name'    => 'blueworx_sso_auto_register',
					'label'   => __( 'Let the joining button create an account for someone who does not have one', 'blueworx-labs-wordpress' ),
					'checked' => '1' === blueworx_sso_option( 'auto_register', '0' ),
					'help'    => __( 'Signing in never creates an account, whatever this is set to.', 'blueworx-labs-wordpress' ),
				)
			),
		)
	);

	$fields .= blueworx_ds_field(
		array(
			'label'   => __( 'Role for new accounts', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_sso_default_role',
			'control' => blueworx_ds_select(
				array(
					'name'     => 'blueworx_sso_default_role',
					'id'       => 'blueworx_sso_default_role',
					'options'  => blueworx_sso_role_choices(),
					'selected' => blueworx_sso_option( 'default_role', 'subscriber' ),
				)
			),
			'help'    => __( 'Administrator is never offered: signing in must not be able to hand out the keys to the site.', 'blueworx-labs-wordpress' ),
		)
	);

	$fields .= blueworx_sso_text_field(
		array(
			'key'         => 'redirect_after_login',
			'label'       => __( 'Send people here after signing in', 'blueworx-labs-wordpress' ),
			'placeholder' => admin_url(),
		)
	);

	$fields .= blueworx_sso_text_field(
		array(
			'key'   => 'redirect_after_register',
			'label' => __( 'Send people here after joining', 'blueworx-labs-wordpress' ),
			'help'  => __( 'Leave blank to use the same page as signing in.', 'blueworx-labs-wordpress' ),
		)
	);

	$fields .= blueworx_sso_text_field(
		array(
			'key'   => 'no_account_url',
			'label' => __( 'Send people here when they sign in and have no account', 'blueworx-labs-wordpress' ),
			'help'  => __( 'Your joining page. Leave blank to show the usual "we could not sign you in" message instead.', 'blueworx-labs-wordpress' ),
		)
	);

	// The callback address exists to be pasted into somebody else's control
	// panel, so it gets a copy button rather than a line of text to select.
	$fields .= blueworx_ds_field(
		array(
			'label'   => __( 'Give your provider this address', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx-sso-callback-url',
			'control' => blueworx_ds_copy_field(
				array(
					'value' => blueworx_sso_callback_url(),
					'id'    => 'blueworx-sso-callback-url',
					'attrs' => array( 'data-testid' => 'blueworx-sso-callback-url' ),
				)
			),
		)
	);

	$fields .= blueworx_ds_field(
		array(
			'label'   => __( 'Buttons', 'blueworx-labs-wordpress' ),
			'control' => '<p class="bw-field__help"><code>[blueworx_sso_button]</code> '
				. esc_html__( 'to sign in, and', 'blueworx-labs-wordpress' )
				. ' <code>[blueworx_sso_button intent="register"]</code> '
				. esc_html__( 'to join. The sign-in button is added to the login screen for you.', 'blueworx-labs-wordpress' )
				. '</p>',
		)
	);

	$fields .= blueworx_ds_field(
		array(
			'label'   => __( 'Connection', 'blueworx-labs-wordpress' ),
			'control' => '<p class="blueworx-sso-status">'
				. blueworx_ds_badge(
					$status['message'],
					$status['connected'] ? 'success' : 'neutral',
					true
				)
				. '</p>',
		)
	);

	// Not wp_kses() — see the note in blueworx_render_feature_detail(). The copy
	// field's hooks are exactly what an allow-list drops without a word.
	echo blueworx_detail_stack( $fields ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	blueworx_sso_render_log();

	$advanced = blueworx_sso_text_field(
		array(
			'key'         => 'scope',
			'label'       => __( 'Scope', 'blueworx-labs-wordpress' ),
			'type'        => 'text',
			'placeholder' => 'openid email profile',
			'mono'        => true,
			'help'        => __( 'Changing this may mean people have to approve the connection again.', 'blueworx-labs-wordpress' ),
		)
	);

	$advanced .= blueworx_sso_text_field(
		array(
			'key'         => 'redirect_uri',
			'label'       => __( 'Return address registered with the provider', 'blueworx-labs-wordpress' ),
			'placeholder' => blueworx_sso_callback_url(),
			'help'        => __( 'Only set this when the provider has an older address registered that cannot be changed.', 'blueworx-labs-wordpress' ),
		)
	);

	$advanced .= blueworx_sso_text_field(
		array(
			'key'         => 'signup_prompt',
			'label'       => __( 'Signup prompt', 'blueworx-labs-wordpress' ),
			'type'        => 'text',
			'default'     => 'signup',
			'placeholder' => 'signup',
			'mono'        => true,
			'help'        => __( 'Asks the provider to open on its "create an account" screen when someone joins. Empty it if your provider refuses the request.', 'blueworx-labs-wordpress' ),
		)
	);

	$advanced .= blueworx_ds_field(
		array(
			'label'   => __( 'Proof key (PKCE)', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_sso_pkce',
			'control' => blueworx_ds_select(
				array(
					'name'     => 'blueworx_sso_pkce',
					'id'       => 'blueworx_sso_pkce',
					'selected' => blueworx_sso_option( 'pkce', 'auto' ),
					'options'  => array(
						'auto' => __( 'Use it when the provider supports it', 'blueworx-labs-wordpress' ),
						'on'   => __( 'Always use it', 'blueworx-labs-wordpress' ),
						'off'  => __( 'Never use it', 'blueworx-labs-wordpress' ),
					),
				)
			),
		)
	);

	foreach ( blueworx_sso_endpoint_override_fields() as $key => $label ) {
		$advanced .= blueworx_sso_text_field(
			array(
				'key'   => $key . '_override',
				'label' => $label,
			)
		);
	}

	$advanced .= '<p class="bw-field__help">' . esc_html__( 'Leave the addresses blank unless your provider does not publish its own configuration.', 'blueworx-labs-wordpress' ) . '</p>';

	$advanced .= blueworx_ds_field(
		array(
			'control' => blueworx_ds_checkbox(
				array(
					'name'    => 'blueworx_sso_single_logout',
					'label'   => __( 'Sign people out of the provider too', 'blueworx-labs-wordpress' ),
					'checked' => blueworx_sso_single_logout_enabled(),
					'help'    => __( 'Otherwise logging out only ends the WordPress session, and the next click on the sign-in button walks straight back in without anyone being asked for anything. Only affects people who signed in through the provider.', 'blueworx-labs-wordpress' ),
					'attrs'   => array( 'data-testid' => 'bw-sso-single-logout' ),
				)
			),
		)
	);

	$advanced .= blueworx_sso_text_field(
		array(
			'key'   => 'redirect_after_logout',
			'label' => __( 'Send people here after signing out', 'blueworx-labs-wordpress' ),
			'help'  => __( 'Leave blank for the home page. Some providers need this address registered with them first.', 'blueworx-labs-wordpress' ),
		)
	);

	// Offered only once somebody has actually signed in through the provider.
	// Before that it is a one-click way to lock everybody out of a connection
	// that has never worked, which is exactly when it is most tempting.
	$proven = blueworx_sso_provider_proven();

	$advanced .= blueworx_ds_field(
		array(
			'control' => blueworx_ds_checkbox(
				array(
					'name'     => 'blueworx_sso_hide_password_form',
					'label'    => __( 'Hide the WordPress password form', 'blueworx-labs-wordpress' ),
					'checked'  => blueworx_sso_hide_password_form(),
					'disabled' => ! $proven,
					'attrs'    => array( 'data-testid' => 'bw-sso-hide-password' ),
				)
			),
			'help'    => $proven
				? __( 'The sign-in screen offers the provider and nothing else. Administrators can still reach the password form by adding ?blueworx-password=1 to the sign-in address.', 'blueworx-labs-wordpress' )
				: __( 'Available once one administrator has signed in through the provider successfully. Until then, hiding the form could lock everybody out.', 'blueworx-labs-wordpress' ),
		)
	);

	// Not wp_kses() — same reasoning as the fields above.
	// Not wp_kses() — same reasoning as the fields above.
	echo blueworx_ds_accordion( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		array(
			'title' => __( 'Advanced', 'blueworx-labs-wordpress' ),
			'sub'   => __( 'Scopes, addresses and sign-out. Leave these alone unless your provider asks.', 'blueworx-labs-wordpress' ),
			'body'  => blueworx_detail_stack( $advanced ),
			'class' => 'blueworx-sso-advanced',
		)
	);
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
 * Points at the log rather than repeating a few lines of it.
 *
 * This screen used to end with the last five attempts and their reason codes.
 * That was enough to know something was failing and never enough to know why,
 * so it sent everybody looking for a second source anyway. The whole record now
 * has its own screen, and this is the way to it.
 *
 * @return void
 */
function blueworx_sso_render_log() {
	$events = blueworx_sso_events();
	$failed = 0;

	foreach ( array_slice( $events, 0, 10 ) as $event ) {
		if ( isset( $event['outcome'] ) && 'failure' === $event['outcome'] ) {
			++$failed;
		}
	}

	echo wp_kses(
		blueworx_ds_field(
			array(
				'label'   => __( 'What has been happening', 'blueworx-labs-wordpress' ),
				'help'    => empty( $events )
					? __( 'Nothing has been attempted yet.', 'blueworx-labs-wordpress' )
					: (
						$failed > 0
							/* translators: %s: how many of the last ten events failed. */
							? sprintf( _n( '%s of the last ten attempts failed.', '%s of the last ten attempts failed.', $failed, 'blueworx-labs-wordpress' ), number_format_i18n( $failed ) )
							: __( 'Nothing has failed recently.', 'blueworx-labs-wordpress' )
					),
				'control' => blueworx_ds_button(
					array(
						'label' => __( 'Open SSO Logs', 'blueworx-labs-wordpress' ),
						'href'  => admin_url( 'admin.php?page=blueworx-sso-logs' ),
						'size'  => 'sm',
						'attrs' => array( 'data-testid' => 'bw-sso-open-logs' ),
					)
				),
			)
		),
		blueworx_ds_allowed_html()
	);
}

/**
 * Saves the single sign-on screen.
 *
 * Its own handler rather than the Enhancements one. That form posts every
 * feature switch on the site, and a missing checkbox is indistinguishable from
 * an unticked one — so saving this screen through it switched off every
 * function in the plugin, including this one.
 *
 * @return void
 */
function blueworx_sso_handle_settings_save() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_save_sso_settings' );

	blueworx_sso_save_settings( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() ran above; the callee sanitizes every field.

	set_transient( 'blueworx_labs_notice', __( 'Settings saved.', 'blueworx-labs-wordpress' ), 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-sso' ) );
	exit;
}
add_action( 'admin_post_blueworx_save_sso_settings', 'blueworx_sso_handle_settings_save' );

/**
 * Saves the single sign-on settings.
 *
 * The caller has already verified the nonce and the capability.
 *
 * @param array $posted Raw $_POST.
 * @return void
 */
function blueworx_sso_save_settings( $posted ) {
	$text_fields = array( 'client_id', 'button_label', 'register_button_label', 'scope', 'signup_prompt', 'allowed_domains' );

	foreach ( $text_fields as $field ) {
		$value = isset( $posted[ 'blueworx_sso_' . $field ] ) ? sanitize_text_field( wp_unslash( $posted[ 'blueworx_sso_' . $field ] ) ) : '';
		update_option( 'blueworx_sso_' . $field, $value, false );
	}

	$url_fields = array( 'issuer', 'redirect_uri', 'redirect_after_login', 'redirect_after_register', 'redirect_after_logout', 'no_account_url' );

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
	update_option( 'blueworx_sso_single_logout', isset( $posted['blueworx_sso_single_logout'] ) ? '1' : '0', false );

	$role    = isset( $posted['blueworx_sso_default_role'] ) ? sanitize_key( wp_unslash( $posted['blueworx_sso_default_role'] ) ) : 'subscriber';
	$choices = blueworx_sso_role_choices();
	update_option( 'blueworx_sso_default_role', isset( $choices[ $role ] ) ? $role : 'subscriber', false );

	$pkce = isset( $posted['blueworx_sso_pkce'] ) ? sanitize_key( wp_unslash( $posted['blueworx_sso_pkce'] ) ) : 'auto';
	update_option( 'blueworx_sso_pkce', in_array( $pkce, array( 'auto', 'on', 'off' ), true ) ? $pkce : 'auto', false );

	// The provider, and the address it implies. Written after the URL loop
	// above so a preset wins over whatever the address box last held.
	$provider  = isset( $posted['blueworx_sso_provider'] ) ? sanitize_key( wp_unslash( $posted['blueworx_sso_provider'] ) ) : 'custom';
	$providers = blueworx_sso_providers();
	$provider  = isset( $providers[ $provider ] ) ? $provider : 'custom';

	$token = isset( $posted['blueworx_sso_provider_token'] ) ? sanitize_text_field( wp_unslash( $posted['blueworx_sso_provider_token'] ) ) : '';

	update_option( 'blueworx_sso_provider', $provider, false );
	update_option( 'blueworx_sso_provider_token', $token, false );

	if ( 'custom' !== $provider ) {
		$built = blueworx_sso_build_issuer( $provider, $token, '' );
		update_option( 'blueworx_sso_issuer', esc_url_raw( $built ), false );

		if ( $previous_issuer !== $built ) {
			delete_transient( 'blueworx_sso_discovery_' . md5( untrailingslashit( (string) $previous_issuer ) ) );
			delete_transient( 'blueworx_sso_jwks' );
		}
	}

	// Hiding the password form is only offered once somebody has actually
	// signed in through the provider — see blueworx_sso_provider_proven(). The
	// saved value is refused outright otherwise, so a stale POST cannot lock
	// everybody out of a connection that has never worked.
	update_option(
		'blueworx_sso_hide_password_form',
		( ! empty( $posted['blueworx_sso_hide_password_form'] ) && blueworx_sso_provider_proven() ) ? '1' : '0',
		false
	);
}

/**
 * Whether the WordPress password form should be hidden on the sign-in screen.
 *
 * Checked rather than trusted: the gate is re-tested on every read, so a
 * connection that stops working does not leave the site unreachable.
 *
 * @return bool True when the form should be hidden.
 */
function blueworx_sso_hide_password_form() {
	if ( '1' !== blueworx_sso_option( 'hide_password_form', '0' ) ) {
		return false;
	}

	return blueworx_sso_enabled() && blueworx_sso_provider_proven();
}
