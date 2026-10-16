<?php
/**
 * BlueWorx: Connect Claude — the admin screen.
 *
 * One screen: the switch, the address to paste into Claude, who is connected
 * (with a button to cut each one off), and what was changed recently.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saves the switch, and the revoke buttons.
 *
 * Switching the function off also removes every connection, so turning it
 * back on later never quietly revives an old one.
 *
 * @return void
 */
function blueworx_mcp_handle_screen_actions() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_mcp_screen' );

	$do = isset( $_POST['blueworx_mcp_do'] ) ? sanitize_key( wp_unslash( $_POST['blueworx_mcp_do'] ) ) : '';

	if ( 'toggle' === $do ) {
		$on = ! empty( $_POST['blueworx_mcp_feature'] );

		blueworx_set_feature_enabled( 'mcp', $on );

		if ( ! $on ) {
			blueworx_mcp_revoke_all();
		}
	} elseif ( 'revoke' === $do && isset( $_POST['blueworx_mcp_grant'] ) ) {
		blueworx_mcp_revoke_grant( sanitize_text_field( wp_unslash( $_POST['blueworx_mcp_grant'] ) ) );
	} elseif ( 'revoke_all' === $do ) {
		blueworx_mcp_revoke_all();
	}

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-mcp' ) );
	exit;
}
add_action( 'admin_post_blueworx_mcp_screen', 'blueworx_mcp_handle_screen_actions' );

/**
 * Formats a stored UTC timestamp in the site's time.
 *
 * @param int $time Timestamp.
 * @return string Date and time, or a dash for never.
 */
function blueworx_mcp_format_time( $time ) {
	if ( empty( $time ) ) {
		return '—';
	}

	return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $time + (int) ( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
}

/**
 * Gets a user's display name, or a placeholder when they are gone.
 *
 * @param int $user_id User id.
 * @return string Name.
 */
function blueworx_mcp_user_name( $user_id ) {
	$user = get_userdata( (int) $user_id );

	return $user ? $user->display_name : __( 'Deleted user', 'blueworx-labs-wordpress' );
}

/**
 * Renders the Connect Claude screen.
 *
 * @return void
 */
function blueworx_render_mcp_page() {
	$on     = blueworx_feature_enabled( 'mcp' );
	$grants = $on ? blueworx_mcp_get_grants() : array();
	$action = esc_url( admin_url( 'admin-post.php' ) );
	$nonce  = wp_nonce_field( 'blueworx_mcp_screen', '_wpnonce', true, false );

	blueworx_open_admin_page(
		array(
			'title'   => __( 'Connect Claude', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'Lets Claude read and edit this site\'s content for you, after an administrator approves it. It cannot touch files, plugins, themes, settings or users.', 'blueworx-labs-wordpress' ),
			'actions' => blueworx_ds_badge(
				$on ? __( 'On', 'blueworx-labs-wordpress' ) : __( 'Off', 'blueworx-labs-wordpress' ),
				$on ? 'success' : 'neutral',
				true
			),
		)
	);

	$switch = sprintf(
		'<form method="post" action="%1$s">%2$s<input type="hidden" name="action" value="blueworx_mcp_screen" /><input type="hidden" name="blueworx_mcp_do" value="toggle" /><label class="bw-switch bw-switch--bare"><input type="checkbox" role="switch" name="blueworx_mcp_feature" value="1"%3$s data-testid="bw-mcp-feature" /><span class="bw-switch__track"><span class="bw-switch__thumb"></span></span><span class="screen-reader-text">%4$s</span></label>%5$s</form>',
		$action,
		$nonce,
		checked( $on, true, false ),
		esc_html__( 'Claude can connect to this site', 'blueworx-labs-wordpress' ),
		blueworx_ds_button(
			array(
				'label' => __( 'Save', 'blueworx-labs-wordpress' ),
				'type'  => 'submit',
				'size'  => 'sm',
				'attrs' => array( 'data-testid' => 'bw-mcp-save' ),
			)
		)
	);

	echo '<section class="bw-card bw-supportcard"><div class="bw-card__head"><div class="bw-card__titles">';
	echo '<p class="bw-card__eyebrow">' . esc_html__( 'Administrators only, content only', 'blueworx-labs-wordpress' ) . '</p>';
	echo '<h2 class="bw-card__title">' . esc_html__( 'Claude connection', 'blueworx-labs-wordpress' ) . '</h2>';
	echo '</div><div class="bw-card__actions">';
	echo $switch; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from escaped parts.
	echo '</div></div><div class="bw-card__body"><div class="bw-fields bw-fields--single">';

	if ( ! $on ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone'  => 'info',
					'title' => __( 'Switched off', 'blueworx-labs-wordpress' ),
					'text'  => __( 'Claude cannot connect while this is off, and switching it off disconnects everyone. Switch it on above to get the address to paste into Claude.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);

		echo '</div></div></section>';
		blueworx_close_admin_page();
		return;
	}

	?>
	<div class="bw-field">
		<span class="bw-field__label"><?php esc_html_e( 'Connector address', 'blueworx-labs-wordpress' ); ?></span>
		<?php
		echo blueworx_ds_copy_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the helper escapes everything it emits.
			array(
				'value' => blueworx_mcp_endpoint_url(),
				'id'    => 'bw-mcp-url',
				'label' => __( 'Copy address', 'blueworx-labs-wordpress' ),
				'attrs' => array( 'data-testid' => 'bw-mcp-url' ),
			)
		);
		?>
		<p class="bw-field__help"><?php esc_html_e( 'In Claude, add a custom connector and paste this address. Claude then sends you back here to sign in and approve it. Leave the advanced client ID and secret fields empty.', 'blueworx-labs-wordpress' ); ?></p>
	</div>
	<?php

	echo '</div></div></section>';

	// Connected apps.
	echo '<section class="bw-card"><div class="bw-card__head"><div class="bw-card__titles">';
	echo '<h2 class="bw-card__title">' . esc_html__( 'Connected', 'blueworx-labs-wordpress' ) . '</h2>';
	echo '</div>';

	if ( ! empty( $grants ) ) {
		echo '<div class="bw-card__actions"><form method="post" action="' . $action . '">' . $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		echo '<input type="hidden" name="action" value="blueworx_mcp_screen" /><input type="hidden" name="blueworx_mcp_do" value="revoke_all" />';
		echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the helper escapes everything it emits.
			array(
				'label' => __( 'Disconnect all', 'blueworx-labs-wordpress' ),
				'type'  => 'submit',
				'size'  => 'sm',
				'attrs' => array( 'data-testid' => 'bw-mcp-revoke-all' ),
			)
		);
		echo '</form></div>';
	}

	echo '</div><div class="bw-card__body">';

	if ( empty( $grants ) ) {
		echo wp_kses(
			blueworx_ds_empty_state(
				array(
					'icon'  => 'link',
					'title' => __( 'Nothing connected yet', 'blueworx-labs-wordpress' ),
					'text'  => __( 'Connections appear here once an administrator approves Claude.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	} else {
		echo '<div style="overflow-x:auto"><table class="bw-table" data-testid="bw-mcp-grants"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'App', 'blueworx-labs-wordpress' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Approved by', 'blueworx-labs-wordpress' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Last used', 'blueworx-labs-wordpress' ) . '</th>';
		echo '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'blueworx-labs-wordpress' ) . '</span></th>';
		echo '</tr></thead><tbody>';

		foreach ( $grants as $grant_id => $grant ) {
			echo '<tr><td class="bw-table__primary">' . esc_html( $grant['client_name'] );
			echo '<span class="bw-table__sub">' . esc_html(
				sprintf(
					/* translators: %s: date and time. */
					__( 'Connected %s', 'blueworx-labs-wordpress' ),
					blueworx_mcp_format_time( $grant['created'] )
				)
			) . '</span></td>';
			echo '<td>' . esc_html( blueworx_mcp_user_name( $grant['user_id'] ) ) . '</td>';
			echo '<td>' . esc_html( blueworx_mcp_format_time( $grant['last_used'] ?? 0 ) ) . '</td>';
			echo '<td><form method="post" action="' . $action . '">' . $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			echo '<input type="hidden" name="action" value="blueworx_mcp_screen" /><input type="hidden" name="blueworx_mcp_do" value="revoke" />';
			echo '<input type="hidden" name="blueworx_mcp_grant" value="' . esc_attr( $grant_id ) . '" />';
			echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the helper escapes everything it emits.
				array(
					'label' => __( 'Disconnect', 'blueworx-labs-wordpress' ),
					'type'  => 'submit',
					'size'  => 'sm',
					'attrs' => array( 'data-testid' => 'bw-mcp-revoke' ),
				)
			);
			echo '</form></td></tr>';
		}

		echo '</tbody></table></div>';
	}

	echo '</div></section>';

	// Recent changes.
	$log = get_option( BLUEWORX_MCP_OPTION_LOG, array() );
	$log = is_array( $log ) ? array_slice( $log, 0, 20 ) : array();

	echo '<section class="bw-card"><div class="bw-card__head"><div class="bw-card__titles">';
	echo '<h2 class="bw-card__title">' . esc_html__( 'Recent changes by Claude', 'blueworx-labs-wordpress' ) . '</h2>';
	echo '</div></div><div class="bw-card__body">';

	if ( empty( $log ) ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'blueworx-labs-wordpress' ) . '</p>';
	} else {
		echo '<div style="overflow-x:auto"><table class="bw-table" data-testid="bw-mcp-log"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'When', 'blueworx-labs-wordpress' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'What', 'blueworx-labs-wordpress' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Connection', 'blueworx-labs-wordpress' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $log as $entry ) {
			echo '<tr><td>' . esc_html( blueworx_mcp_format_time( $entry['time'] ) ) . '</td>';
			echo '<td class="bw-table__primary">' . esc_html( ucfirst( $entry['action'] ) . ( '' !== $entry['target'] ? ': ' . $entry['target'] : '' ) ) . '</td>';
			echo '<td>' . esc_html( $entry['client'] . ' · ' . blueworx_mcp_user_name( $entry['user'] ) ) . '</td></tr>';
		}

		echo '</tbody></table></div>';
	}

	echo '</div></section>';

	blueworx_close_admin_page();
}
