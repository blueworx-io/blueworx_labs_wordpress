<?php
/**
 * Klaviyo connection: the settings screen and the log screen.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tidies and checks everything posted from the settings screen.
 *
 * Pure, so the rules can be tested. A value that is refused keeps what was saved
 * before, so one mistyped box cannot break a connection that was working.
 *
 * @param array $posted  The unslashed form fields.
 * @param array $current The settings as they stand.
 * @return array {
 *     @type array    $values  The settings to save.
 *     @type string[] $refused Names of the boxes that were refused.
 * }
 */
function blueworx_klaviyo_clean_settings( $posted, $current ) {
	$values  = $current;
	$refused = array();

	foreach ( array( 'list_info', 'list_reservation', 'list_members' ) as $name ) {
		$value = isset( $posted[ $name ] ) ? trim( (string) $posted[ $name ] ) : '';

		if ( '' !== $value && ! preg_match( '/^[A-Za-z0-9]{4,12}$/', $value ) ) {
			$refused[] = $name;
			continue;
		}

		$values[ $name ] = $value;
	}

	$form_id = isset( $posted['reservation_form_id'] ) ? trim( (string) $posted['reservation_form_id'] ) : '';

	if ( '' !== $form_id && ! ctype_digit( $form_id ) ) {
		$refused[] = 'reservation_form_id';
	} else {
		$values['reservation_form_id'] = $form_id;
	}

	$date = isset( $posted['founding_start_date'] ) ? trim( (string) $posted['founding_start_date'] ) : '';

	if ( '' !== $date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		$refused[] = 'founding_start_date';
	} else {
		$values['founding_start_date'] = $date;
	}

	$box = isset( $posted['checkout_box'] ) ? trim( (string) $posted['checkout_box'] ) : '';

	if ( '' !== $box && ! preg_match( '/^[A-Za-z0-9_-]{1,60}$/', $box ) ) {
		$refused[] = 'checkout_box';
	} else {
		$values['checkout_box'] = $box;
	}

	foreach ( array( 'info_form_name', 'consent_text', 'founding_product' ) as $name ) {
		$values[ $name ] = isset( $posted[ $name ] ) ? sanitize_text_field( (string) $posted[ $name ] ) : '';
	}

	$values['send_test_mode'] = empty( $posted['send_test_mode'] ) ? '0' : '1';

	return array(
		'values'  => $values,
		'refused' => $refused,
	);
}

/**
 * Saves the settings screen, and runs the connection test if that was asked for.
 *
 * @return void
 */
function blueworx_klaviyo_handle_save() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_save_klaviyo' );

	$posted = array();

	foreach ( array_keys( blueworx_klaviyo_setting_defaults() ) as $name ) {
		if ( isset( $_POST[ 'blueworx_klaviyo_' . $name ] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- blueworx_klaviyo_clean_settings() checks every field.
			$posted[ $name ] = wp_unslash( $_POST[ 'blueworx_klaviyo_' . $name ] );
		}
	}

	$clean = blueworx_klaviyo_clean_settings( $posted, blueworx_klaviyo_settings() );

	foreach ( $clean['values'] as $name => $value ) {
		update_option( 'blueworx_klaviyo_' . $name, $value, 'no' );
	}

	foreach ( array( 'key', 'webhook_secret' ) as $secret ) {
		// An empty box means "keep what is saved": the box never shows the saved
		// value, so it is always empty when somebody saves for another reason.
		// Tidied by hand because sanitize_text_field() could alter a secret.
		$value = isset( $_POST[ 'blueworx_klaviyo_' . $secret ] ) ? trim( (string) wp_unslash( $_POST[ 'blueworx_klaviyo_' . $secret ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( '' !== $value ) {
			update_option( 'blueworx_klaviyo_' . $secret, $value, 'no' );
		}

		if ( ! empty( $_POST[ 'blueworx_klaviyo_forget_' . $secret ] ) ) {
			delete_option( 'blueworx_klaviyo_' . $secret );
		}
	}

	$notice = array( 'refused' => $clean['refused'] );

	if ( ! empty( $_POST['blueworx_klaviyo_test'] ) ) {
		$notice['test'] = blueworx_klaviyo_test_connection();
	}

	set_transient( 'blueworx_klaviyo_notice', $notice, 60 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-klaviyo' ) );
	exit;
}
add_action( 'admin_post_blueworx_save_klaviyo', 'blueworx_klaviyo_handle_save' );

/**
 * Empties the log.
 *
 * @return void
 */
function blueworx_klaviyo_handle_clear_log() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_clear_klaviyo_log' );

	delete_option( BLUEWORX_KLAVIYO_LOG_OPTION );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-klaviyo-log' ) );
	exit;
}
add_action( 'admin_post_blueworx_clear_klaviyo_log', 'blueworx_klaviyo_handle_clear_log' );

/**
 * Shows the end of a secret, enough to tell which one is saved.
 *
 * @param string $secret The secret.
 * @return string
 */
function blueworx_klaviyo_mask( $secret ) {
	$secret = (string) $secret;

	return '' === $secret ? '' : str_repeat( '•', 8 ) . substr( $secret, -4 );
}

/**
 * One box on the settings screen.
 *
 * @param string $name  Setting name.
 * @param string $label Label.
 * @param string $help  Help text.
 * @param array  $attrs Extra input attributes.
 * @return string HTML.
 */
function blueworx_klaviyo_text_field( $name, $label, $help, $attrs = array() ) {
	$id = 'blueworx_klaviyo_' . $name;

	return blueworx_ds_field(
		array(
			'label'   => $label,
			'for'     => $id,
			'help'    => $help,
			'control' => blueworx_ds_input(
				array(
					'name'  => $id,
					'id'    => $id,
					'value' => blueworx_klaviyo_setting( $name ),
					'attrs' => array_merge( array( 'autocomplete' => 'off' ), $attrs ),
				)
			),
		)
	);
}

/**
 * One secret on the settings screen. It is never put back in the box.
 *
 * @param string $name     key or webhook_secret.
 * @param string $label    Label.
 * @param string $help     Help text.
 * @param string $value    The secret in force.
 * @param string $constant The wp-config.php constant that overrides it.
 * @return string HTML.
 */
function blueworx_klaviyo_secret_field( $name, $label, $help, $value, $constant ) {
	$id = 'blueworx_klaviyo_' . $name;

	if ( defined( $constant ) && constant( $constant ) ) {
		return blueworx_ds_field(
			array(
				'label'   => $label,
				'help'    => sprintf(
					/* translators: %s: name of a wp-config.php constant. */
					__( 'Set in wp-config.php as %s, so it cannot be changed here.', 'blueworx-labs-wordpress' ),
					$constant
				),
				'control' => '<code>' . esc_html( blueworx_klaviyo_mask( $value ) ) . '</code>',
			)
		);
	}

	$html = blueworx_ds_field(
		array(
			'label'   => $label,
			'for'     => $id,
			'help'    => '' !== $value
				? sprintf(
					/* translators: %s: the last few characters of the saved secret. */
					__( 'Saved: %s. Leave this empty to keep it.', 'blueworx-labs-wordpress' ),
					blueworx_klaviyo_mask( $value )
				)
				: $help,
			'control' => blueworx_ds_input(
				array(
					'type'  => 'password',
					'name'  => $id,
					'id'    => $id,
					'value' => '',
					'attrs' => array( 'autocomplete' => 'new-password' ),
				)
			),
		)
	);

	if ( '' !== $value ) {
		$html .= blueworx_ds_checkbox(
			array(
				'name'  => 'blueworx_klaviyo_forget_' . $name,
				'value' => '1',
				'label' => __( 'Remove the saved one', 'blueworx-labs-wordpress' ),
			)
		);
	}

	return $html;
}

/**
 * Renders the settings screen.
 *
 * @return void
 */
function blueworx_render_klaviyo_page() {
	$enabled = blueworx_feature_enabled( 'klaviyo' );
	$key     = blueworx_klaviyo_key();
	$secret  = blueworx_klaviyo_webhook_secret();

	$form = $enabled
		? sprintf(
			'<form method="post" action="%1$s"><input type="hidden" name="action" value="blueworx_save_klaviyo" />%2$s',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'blueworx_save_klaviyo', '_wpnonce', true, false )
		)
		: '';

	$ready = $enabled && '' !== $key && '' !== $secret;

	blueworx_open_admin_page(
		array(
			'title'   => __( 'Klaviyo', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'Sends SureCart memberships and website form sign-ups to Klaviyo. A checkout or form never waits for it, and if Klaviyo is down the work waits and tries again.', 'blueworx-labs-wordpress' ),
			'actions' => blueworx_ds_badge(
				$ready ? __( 'Connected', 'blueworx-labs-wordpress' ) : __( 'Not connected', 'blueworx-labs-wordpress' ),
				$ready ? 'success' : 'neutral',
				true
			)
				. blueworx_ds_button(
					array(
						'label'   => __( 'Open the log', 'blueworx-labs-wordpress' ),
						'href'    => admin_url( 'admin.php?page=blueworx-klaviyo-log' ),
						'variant' => 'secondary',
						'size'    => 'sm',
					)
				),
			'form'    => $form,
		)
	);

	if ( ! $enabled ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone'    => 'info',
					'title'   => __( 'The Klaviyo connection is switched off', 'blueworx-labs-wordpress' ),
					'text'    => __( 'Nothing is sent to Klaviyo while this is off, and this page is not listed in the menu. Switch it on under Enhancements to set it up.', 'blueworx-labs-wordpress' ),
					'actions' => blueworx_ds_button(
						array(
							'label' => __( 'Go to Enhancements', 'blueworx-labs-wordpress' ),
							'icon'  => 'arrow-right',
							'href'  => admin_url( 'admin.php?page=blueworx-labs-wordpress&section=store' ),
						)
					),
				)
			),
			blueworx_ds_allowed_html()
		);

		blueworx_close_admin_page();
		return;
	}

	blueworx_klaviyo_render_notices();

	$connection = blueworx_klaviyo_secret_field(
		'key',
		__( 'Private API key', 'blueworx-labs-wordpress' ),
		__( 'In Klaviyo, under Settings, API keys. Give it full access to Lists, Profiles, Events and Subscriptions only.', 'blueworx-labs-wordpress' ),
		$key,
		'BLUEWORX_KLAVIYO_KEY'
	);

	foreach ( blueworx_klaviyo_list_labels() as $setting => $label ) {
		$connection .= blueworx_klaviyo_text_field( $setting, $label, __( 'The list\'s ID from Klaviyo.', 'blueworx-labs-wordpress' ) );
	}

	$surecart = blueworx_ds_field(
		array(
			'label'   => __( 'Webhook address', 'blueworx-labs-wordpress' ),
			'help'    => sprintf(
				/* translators: %s: comma-separated SureCart event names. */
				__( 'Add this as a webhook in the SureCart dashboard, listening for: %s.', 'blueworx-labs-wordpress' ),
				implode( ', ', blueworx_klaviyo_surecart_events() )
			),
			'control' => '<code data-testid="blueworx-klaviyo-webhook-url">' . esc_html( blueworx_klaviyo_webhook_url() ) . '</code>',
		)
	);

	$surecart .= blueworx_klaviyo_secret_field(
		'webhook_secret',
		__( 'Webhook signing secret', 'blueworx-labs-wordpress' ),
		__( 'Shown in the SureCart dashboard once the webhook is added. Until it is set here, every delivery is refused.', 'blueworx-labs-wordpress' ),
		$secret,
		'BLUEWORX_KLAVIYO_WEBHOOK_SECRET'
	);

	$surecart .= blueworx_klaviyo_text_field( 'founding_product', __( 'Founding product', 'blueworx-labs-wordpress' ), __( 'The product\'s slug, such as founding-membership. Only this product sets founding_status and founding_member.', 'blueworx-labs-wordpress' ) );
	$surecart .= blueworx_klaviyo_text_field( 'founding_start_date', __( 'Founding start date', 'blueworx-labs-wordpress' ), __( 'YYYY-MM-DD. Sent as the membership start date for that product, since everyone starts together. Empty sends the day they paid.', 'blueworx-labs-wordpress' ) );
	$surecart .= blueworx_klaviyo_text_field( 'checkout_box', __( 'Checkout marketing box name', 'blueworx-labs-wordpress' ), __( 'The Name given to the checkbox in the SureCart checkout form. Ticked adds them to the Members list.', 'blueworx-labs-wordpress' ) );
	$surecart .= blueworx_ds_checkbox(
		array(
			'name'    => 'blueworx_klaviyo_send_test_mode',
			'value'   => '1',
			'checked' => '1' === blueworx_klaviyo_setting( 'send_test_mode' ),
			'label'   => __( 'Also send SureCart test orders', 'blueworx-labs-wordpress' ),
			'help'    => __( 'For testing. Switch this off before real members pay, or test orders go into Klaviyo with them.', 'blueworx-labs-wordpress' ),
		)
	);

	$forms  = blueworx_klaviyo_text_field( 'info_form_name', __( 'Stay up to date: Elementor form name', 'blueworx-labs-wordpress' ), __( 'The Form Name set on the form in Elementor.', 'blueworx-labs-wordpress' ) );
	$forms .= blueworx_klaviyo_text_field( 'consent_text', __( 'Stay up to date: consent wording', 'blueworx-labs-wordpress' ), __( 'The exact wording shown beside the button. Sent with each sign-up as the record of what they agreed to.', 'blueworx-labs-wordpress' ) );
	$forms .= blueworx_klaviyo_text_field( 'reservation_form_id', __( 'Founding reservation: SureForms form ID', 'blueworx-labs-wordpress' ), __( 'The number in the form\'s address when you edit it. Empty leaves this form unconnected.', 'blueworx-labs-wordpress' ) );

	// Not wp_kses(): the allow-list has no opinion on these form controls, and the
	// design system helpers escape everything they emit.
	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		array(
			'title' => __( 'Klaviyo account', 'blueworx-labs-wordpress' ),
			'body'  => $connection,
		)
	);
	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		array(
			'title' => __( 'SureCart', 'blueworx-labs-wordpress' ),
			'body'  => $surecart,
		)
	);
	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		array(
			'title' => __( 'Website forms', 'blueworx-labs-wordpress' ),
			'body'  => $forms,
		)
	);

	$savebar = '<div class="bw-savebar"><span class="bw-savebar__hint">'
		. esc_html__( 'Nothing changes until you save.', 'blueworx-labs-wordpress' )
		. '</span>'
		. blueworx_ds_button(
			array(
				'label'   => __( 'Save and test connection', 'blueworx-labs-wordpress' ),
				'variant' => 'secondary',
				'type'    => 'submit',
				'attrs'   => array(
					'name'        => 'blueworx_klaviyo_test',
					'value'       => '1',
					'data-testid' => 'blueworx-klaviyo-test',
				),
			)
		)
		. blueworx_ds_button(
			array(
				'label'   => __( 'Save changes', 'blueworx-labs-wordpress' ),
				'variant' => 'primary',
				'type'    => 'submit',
				'attrs'   => array( 'data-testid' => 'blueworx-klaviyo-save' ),
			)
		)
		. '</div></form>';

	blueworx_close_admin_page( $savebar );
}

/**
 * Prints what the last save or test had to say.
 *
 * @return void
 */
function blueworx_klaviyo_render_notices() {
	$notice = get_transient( 'blueworx_klaviyo_notice' );

	if ( is_array( $notice ) ) {
		delete_transient( 'blueworx_klaviyo_notice' );

		$messages = array();

		if ( ! empty( $notice['refused'] ) ) {
			$messages[] = array(
				'tone' => 'danger',
				'text' => __( 'Some boxes were not saved because they are not in the right format, so the previous values were kept: ', 'blueworx-labs-wordpress' ) . implode( ', ', array_map( 'sanitize_key', $notice['refused'] ) ),
			);
		} else {
			$messages[] = array(
				'tone' => 'success',
				'text' => __( 'Settings saved.', 'blueworx-labs-wordpress' ),
			);
		}

		if ( isset( $notice['test'] ) ) {
			$messages[] = array(
				'tone'  => $notice['test']['ok'] ? 'success' : 'danger',
				'title' => $notice['test']['ok'] ? __( 'Connection works', 'blueworx-labs-wordpress' ) : __( 'Connection needs attention', 'blueworx-labs-wordpress' ),
				'text'  => implode( ' ', $notice['test']['lines'] ),
			);
		}

		foreach ( $messages as $message ) {
			echo wp_kses( blueworx_ds_notice( $message ), blueworx_ds_allowed_html() );
		}
	}

	$refused = (int) get_option( 'blueworx_klaviyo_last_refused', 0 );

	if ( $refused > time() - DAY_IN_SECONDS ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone' => 'warning',
					'text' => sprintf(
						/* translators: %s: date and time. */
						__( 'A delivery to the webhook address was refused at %s because its signature did not match. If SureCart is not reaching this site, check the signing secret below.', 'blueworx-labs-wordpress' ),
						wp_date( 'j M H:i', $refused )
					),
				)
			),
			blueworx_ds_allowed_html()
		);
	}
}

/**
 * Renders the log screen.
 *
 * @return void
 */
function blueworx_render_klaviyo_log_page() {
	$log = get_option( BLUEWORX_KLAVIYO_LOG_OPTION, array() );
	$log = is_array( $log ) ? $log : array();

	blueworx_open_admin_page(
		array(
			'title'   => __( 'Klaviyo log', 'blueworx-labs-wordpress' ),
			'lede'    => sprintf(
				/* translators: %s: number of sends kept. */
				__( 'The last %s sends to Klaviyo, newest first.', 'blueworx-labs-wordpress' ),
				number_format_i18n( BLUEWORX_KLAVIYO_LOG_LIMIT )
			),
			'actions' => blueworx_ds_button(
				array(
					'label'   => __( 'Klaviyo settings', 'blueworx-labs-wordpress' ),
					'href'    => admin_url( 'admin.php?page=blueworx-klaviyo' ),
					'variant' => 'secondary',
					'size'    => 'sm',
				)
			),
		)
	);

	echo '<section class="bw-card"><div class="bw-card__head"><div class="bw-card__titles">';
	echo '<h2 class="bw-card__title">' . esc_html__( 'Recent sends', 'blueworx-labs-wordpress' ) . '</h2>';
	echo '</div><div class="bw-card__actions">';

	if ( $log ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'blueworx_clear_klaviyo_log' );
		echo '<input type="hidden" name="action" value="blueworx_clear_klaviyo_log" />';
		echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helper escapes everything it emits.
			array(
				'label'   => __( 'Clear log', 'blueworx-labs-wordpress' ),
				'type'    => 'submit',
				'variant' => 'secondary',
				'size'    => 'sm',
			)
		);
		echo '</form>';
	}

	echo '</div></div>';

	if ( ! $log ) {
		echo '<div class="bw-card__body">';
		echo wp_kses(
			blueworx_ds_empty_state(
				array(
					'icon'  => 'mail',
					'title' => __( 'Nothing has been sent yet', 'blueworx-labs-wordpress' ),
					'text'  => __( 'Every send to Klaviyo writes a line here, whether it worked or not.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
		echo '</div></section>';

		blueworx_close_admin_page();
		return;
	}

	$user = blueworx_readonly_current_user();
	$hide = $user && ! blueworx_readonly_data_allowed( $user );

	$badges = array(
		'sent'     => array( __( 'Sent', 'blueworx-labs-wordpress' ), 'success' ),
		'retrying' => array( __( 'Will try again', 'blueworx-labs-wordpress' ), 'warning' ),
		'failed'   => array( __( 'Failed', 'blueworx-labs-wordpress' ), 'danger' ),
	);

	echo '<div style="overflow-x:auto"><table class="bw-table" data-testid="blueworx-klaviyo-log"><thead><tr>';
	echo '<th scope="col">' . esc_html__( 'When', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'What', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Who', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Outcome', 'blueworx-labs-wordpress' ) . '</th>';
	echo '</tr></thead><tbody>';

	foreach ( $log as $line ) {
		$outcome = isset( $line['outcome'] ) ? (string) $line['outcome'] : '';
		$badge   = isset( $badges[ $outcome ] ) ? $badges[ $outcome ] : array( $outcome, 'neutral' );
		$email   = isset( $line['email'] ) ? (string) $line['email'] : '';

		echo '<tr>';
		echo '<td><span class="bw-table__primary">' . esc_html( wp_date( 'j M Y H:i', isset( $line['time'] ) ? (int) $line['time'] : 0 ) ) . '</span></td>';
		echo '<td><span class="bw-table__primary">' . esc_html( isset( $line['label'] ) ? (string) $line['label'] : '' ) . '</span></td>';
		echo '<td>' . esc_html( $hide && '' !== $email ? __( 'Hidden', 'blueworx-labs-wordpress' ) : ( '' !== $email ? $email : '—' ) ) . '</td>';
		echo '<td>' . wp_kses( blueworx_ds_badge( $badge[0], $badge[1] ), blueworx_ds_allowed_html() );

		if ( ! empty( $line['detail'] ) ) {
			echo '<span class="bw-table__sub">' . esc_html( (string) $line['detail'] ) . '</span>';
		}

		echo '</td></tr>';
	}

	echo '</tbody></table></div></section>';

	blueworx_close_admin_page();
}
