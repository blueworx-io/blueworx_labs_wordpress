<?php
/**
 * Single sign-on: the SSO Logs screen.
 *
 * Its own screen rather than a panel on the settings one. The settings screen is
 * read by somebody setting the connection up; this is read by somebody finding
 * out why it did not work, which is a different job, needs far more of the page,
 * and should not push the settings themselves off the top of the screen.
 *
 * Two parts, in the order they get used. "This site" is the fixed picture —
 * addresses, cookie rules, what sits in front of the site — which explains most
 * failures on its own without anybody having to reproduce anything. The table
 * below it is what actually happened, one row per leg of each sign-in.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many events the table shows at once.
 */
const BLUEWORX_SSO_LOG_ROWS = 40;

/**
 * The screen.
 *
 * @return void
 */
function blueworx_render_sso_logs_page() {
	blueworx_open_admin_page(
		array(
			'title'   => __( 'SSO Logs', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'Every sign-in that has been attempted through your identity provider, and what this site did with it.', 'blueworx-labs-wordpress' ),
			'actions' => blueworx_ds_button(
				array(
					'label'   => __( 'Sign-on settings', 'blueworx-labs-wordpress' ),
					'href'    => admin_url( 'admin.php?page=blueworx-sso' ),
					'variant' => 'secondary',
					'size'    => 'sm',
				)
			),
		)
	);

	if ( ! blueworx_sso_enabled() ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone'  => 'info',
					'title' => __( 'Single sign-on is not running', 'blueworx-labs-wordpress' ),
					'text'  => __( 'Nothing new will be recorded until the function is switched on and a provider is filled in. Anything already recorded is still shown below.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	blueworx_sso_render_environment_card();
	blueworx_sso_render_events_card();

	blueworx_close_admin_page();
}

/**
 * The fixed picture of how sign-on is wired up on this site.
 *
 * Every row here is something that has silently broken a real sign-in. They are
 * put on the screen together because the fault is almost never one value being
 * wrong on its own — it is two of them disagreeing.
 *
 * @return void
 */
function blueworx_sso_render_environment_card() {
	$callback = blueworx_sso_redirect_uri();
	$site     = wp_parse_url( home_url(), PHP_URL_HOST );
	$served   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';

	$rows = array();

	$rows[ __( 'Return address given to the provider', 'blueworx-labs-wordpress' ) ] = '<code>' . esc_html( $callback ) . '</code>';

	/*
	 * The address the site calls itself, against the address the browser used to
	 * reach it. The sign-in cookie belongs to one exact host, so when these two
	 * differ — www against bare, most often — the cookie is written against one
	 * and looked for against the other, and every sign-in fails on the way back
	 * saying the browser is not the one that left.
	 */
	$host_matches = '' !== $served && strtolower( $served ) === strtolower( (string) $site );

	$rows[ __( 'Address this site calls itself', 'blueworx-labs-wordpress' ) ] = '<code>' . esc_html( (string) $site ) . '</code>';

	$rows[ __( 'Address you reached it on', 'blueworx-labs-wordpress' ) ] = '<code>' . esc_html( $served ) . '</code> '
		. ( $host_matches
			? blueworx_ds_badge( __( 'Matches', 'blueworx-labs-wordpress' ), 'success' )
			: blueworx_ds_badge( __( 'Does not match', 'blueworx-labs-wordpress' ), 'danger' ) );

	$rows[ __( 'Cookies are written for', 'blueworx-labs-wordpress' ) ] = '<code>' . esc_html(
		defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN
			? (string) COOKIE_DOMAIN
			: __( 'this exact address only', 'blueworx-labs-wordpress' )
	) . '</code>';

	$rows[ __( 'Cookie path', 'blueworx-labs-wordpress' ) ] = '<code>' . esc_html( defined( 'COOKIEPATH' ) && COOKIEPATH ? (string) COOKIEPATH : '/' ) . '</code>';

	/*
	 * What WordPress believes about the connection. Behind something that
	 * terminates TLS for the site, this can be false on an https site — which
	 * writes the sign-in cookie without its secure flag, and is worth seeing
	 * even though browsers still accept it.
	 */
	$forwarded = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) ) : '';

	$rows[ __( 'Secure connection', 'blueworx-labs-wordpress' ) ] = is_ssl()
		? blueworx_ds_badge( __( 'WordPress sees https', 'blueworx-labs-wordpress' ), 'success' )
		: blueworx_ds_badge(
			'https' === $forwarded
				? __( 'Your browser has https, this site does not know it', 'blueworx-labs-wordpress' )
				: __( 'WordPress does not see https', 'blueworx-labs-wordpress' ),
			'warning'
		);

	$in_front = blueworx_sso_proxy_in_front();

	$rows[ __( 'In front of this site', 'blueworx-labs-wordpress' ) ] = '' !== $in_front
		? esc_html( $in_front )
		: esc_html__( 'Nothing this site can see', 'blueworx-labs-wordpress' );

	$rows[ __( 'Object cache', 'blueworx-labs-wordpress' ) ] = wp_using_ext_object_cache()
		? esc_html__( 'On — sign-in attempts are held in it, not the database', 'blueworx-labs-wordpress' )
		: esc_html__( 'Off', 'blueworx-labs-wordpress' );

	$last = (int) get_option( 'blueworx_sso_last_success', 0 );

	$rows[ __( 'Last successful sign-in', 'blueworx-labs-wordpress' ) ] = $last > 0
		? esc_html( wp_date( 'j M Y H:i', $last ) )
		: esc_html__( 'Never', 'blueworx-labs-wordpress' );

	echo '<section class="bw-card"><div class="bw-card__head"><div class="bw-card__titles">';
	echo '<p class="bw-card__eyebrow">' . esc_html__( 'Check this first', 'blueworx-labs-wordpress' ) . '</p>';
	echo '<h2 class="bw-card__title">' . esc_html__( 'This site', 'blueworx-labs-wordpress' ) . '</h2>';
	echo '</div></div><div class="bw-card__body" data-testid="bw-sso-environment">';

	if ( ! $host_matches && '' !== $served ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone'  => 'danger',
					'title' => __( 'This site answers to two different addresses', 'blueworx-labs-wordpress' ),
					'text'  => __( 'The sign-in cookie belongs to one address only, so a sign-in that starts on one and comes back on the other loses it and fails. Make the two below the same — usually by settling on one address for the whole site.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	// Not wp_kses(): the rows carry badges and <code>, and the allow-list drops
	// attributes the design system helpers put on them.
	echo blueworx_ds_description_list( $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every value above is escaped where it is built.

	echo '</div></section>';
}

/**
 * Whatever sits in front of this site and admits to it.
 *
 * Named rather than detected properly: a proxy or CDN is the usual explanation
 * for a cookie that was sent and never came back, and knowing one is there is
 * the whole point. Nothing is decided on the strength of these headers.
 *
 * @return string A short name, or an empty string.
 */
function blueworx_sso_proxy_in_front() {
	$server = isset( $_SERVER ) && is_array( $_SERVER ) ? $_SERVER : array();

	$known = array(
		'HTTP_CF_RAY'            => 'Cloudflare',
		'HTTP_X_SUCURI_ID'       => 'Sucuri',
		'HTTP_X_FORWARDED_FOR'   => __( 'A proxy or load balancer', 'blueworx-labs-wordpress' ),
		'HTTP_X_FORWARDED_PROTO' => __( 'A proxy or load balancer', 'blueworx-labs-wordpress' ),
	);

	foreach ( $known as $header => $name ) {
		if ( ! empty( $server[ $header ] ) ) {
			return (string) $name;
		}
	}

	return '';
}

/**
 * The events table.
 *
 * @return void
 */
function blueworx_sso_render_events_card() {
	$events = blueworx_sso_events();
	$total  = count( $events );
	$shown  = array_slice( $events, 0, BLUEWORX_SSO_LOG_ROWS );

	echo '<section class="bw-card"><div class="bw-card__head"><div class="bw-card__titles">';
	echo '<p class="bw-card__eyebrow">' . esc_html__( 'Newest first, two lines per sign-in', 'blueworx-labs-wordpress' ) . '</p>';
	echo '<h2 class="bw-card__title">' . esc_html__( 'Sign-on events', 'blueworx-labs-wordpress' ) . '</h2>';
	echo '</div><div class="bw-card__actions">';

	if ( $total > 0 ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'blueworx_clear_sso_log' );
		echo '<input type="hidden" name="action" value="blueworx_clear_sso_log" />';
		echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helper escapes everything it emits.
			array(
				'label'   => __( 'Clear log', 'blueworx-labs-wordpress' ),
				'type'    => 'submit',
				'variant' => 'secondary',
				'size'    => 'sm',
				'attrs'   => array( 'data-testid' => 'bw-sso-clear-log' ),
			)
		);
		echo '</form>';
	}

	echo '</div></div>';

	if ( empty( $shown ) ) {
		echo '<div class="bw-card__body">';
		echo wp_kses(
			blueworx_ds_empty_state(
				array(
					'icon'  => 'key-round',
					'title' => __( 'Nothing has been attempted yet', 'blueworx-labs-wordpress' ),
					'text'  => __( 'Every sign-in writes two lines here — one when somebody leaves for the provider, one when they come back. Try signing in and this fills up.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
		echo '</div></section>';

		return;
	}

	echo '<div style="overflow-x:auto"><table class="bw-table" data-testid="bw-sso-log-table"><thead><tr>';
	echo '<th scope="col">' . esc_html__( 'When', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Step', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Outcome', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Sign-in cookie', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Address used', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Who and what from', 'blueworx-labs-wordpress' ) . '</th>';
	echo '</tr></thead><tbody>';

	foreach ( $shown as $event ) {
		blueworx_sso_render_event_row( $event );
	}

	echo '</tbody></table></div>';

	echo '<div class="bw-tablefoot"><span>' . esc_html(
		sprintf(
			/* translators: 1: rows shown, 2: rows held. */
			__( 'Showing %1$s of %2$s', 'blueworx-labs-wordpress' ),
			number_format_i18n( count( $shown ) ),
			number_format_i18n( $total )
		)
	) . '</span><span>' . esc_html(
		sprintf(
			/* translators: %s: number of events kept. */
			__( 'The last %s are kept', 'blueworx-labs-wordpress' ),
			number_format_i18n( BLUEWORX_SSO_LOG_LIMIT )
		)
	) . '</span></div>';

	echo '</section>';
}

/**
 * One row of the table.
 *
 * @param array $event A recorded event.
 * @return void
 */
function blueworx_sso_render_event_row( $event ) {
	$get = function ( $key ) use ( $event ) {
		return isset( $event[ $key ] ) ? (string) $event[ $key ] : '';
	};

	$stage   = $get( 'stage' );
	$outcome = $get( 'outcome' );
	$reason  = $get( 'reason' );

	echo '<tr data-testid="bw-sso-log-row" data-sso-outcome="' . esc_attr( $outcome ) . '">';

	// When.
	$time = isset( $event['time'] ) ? (int) $event['time'] : 0;
	echo '<td><span class="bw-table__primary">' . esc_html( $time > 0 ? wp_date( 'j M H:i', $time ) : '—' ) . '</span>';
	echo '<span class="bw-table__sub">' . esc_html( $time > 0 ? wp_date( 'Y', $time ) : '' ) . '</span></td>';

	// Step, and the reference pairing the two halves of one sign-in.
	echo '<td><span class="bw-table__primary">' . esc_html( blueworx_sso_stage_label( $stage ) ) . '</span>';

	if ( '' !== $get( 'intent' ) ) {
		echo '<span class="bw-table__sub">' . esc_html(
			'register' === $get( 'intent' )
				? __( 'Joining', 'blueworx-labs-wordpress' )
				: __( 'Signing in', 'blueworx-labs-wordpress' )
		) . '</span>';
	}

	if ( '' !== $get( 'ref' ) ) {
		/* translators: %s: short reference shared by both halves of one sign-in. */
		echo '<span class="bw-table__sub">' . esc_html( sprintf( __( 'Ref %s', 'blueworx-labs-wordpress' ), $get( 'ref' ) ) ) . '</span>';
	}

	echo '</td>';

	// Outcome.
	echo '<td>' . wp_kses( blueworx_sso_outcome_badge( $outcome ), blueworx_ds_allowed_html() );

	if ( '' !== $reason ) {
		echo '<span class="bw-table__sub">' . esc_html( blueworx_sso_reason_label( $reason ) ) . '</span>';
		echo '<span class="bw-table__sub"><code>' . esc_html( $reason ) . '</code></span>';
	}

	echo '</td>';

	// The cookie, which is the answer to most of these.
	echo '<td><span class="bw-table__primary">' . esc_html( blueworx_sso_cookie_label( $get( 'cookie' ) ) ) . '</span>';

	if ( '' !== $get( 'jar' ) ) {
		echo '<span class="bw-table__sub">' . esc_html(
			/* translators: %s: comma-separated cookie names. */
			sprintf( __( 'Browser sent: %s', 'blueworx-labs-wordpress' ), $get( 'jar' ) )
		) . '</span>';
	}

	echo '</td>';

	// Address, protocol and the cookie rules in force at the time.
	echo '<td><span class="bw-table__primary">' . esc_html( '' !== $get( 'host' ) ? $get( 'host' ) : '—' ) . '</span>';

	if ( '' !== $get( 'ssl' ) ) {
		echo '<span class="bw-table__sub">' . esc_html(
			'1' === $get( 'ssl' )
				? __( 'https', 'blueworx-labs-wordpress' )
				: __( 'not seen as https', 'blueworx-labs-wordpress' )
		) . ( '' !== $get( 'proto' ) ? esc_html( ' · ' . $get( 'proto' ) . ' in front' ) : '' ) . '</span>';
	}

	if ( '' !== $get( 'domain' ) || '' !== $get( 'path' ) ) {
		echo '<span class="bw-table__sub">' . esc_html(
			sprintf(
				/* translators: 1: cookie domain, 2: cookie path. */
				__( 'Cookie for %1$s at %2$s', 'blueworx-labs-wordpress' ),
				'' !== $get( 'domain' ) ? $get( 'domain' ) : __( 'this address only', 'blueworx-labs-wordpress' ),
				'' !== $get( 'path' ) ? $get( 'path' ) : '/'
			)
		) . '</span>';
	}

	echo '</td>';

	// Who, and which browser.
	echo '<td><span class="bw-table__primary">' . esc_html( '' !== $get( 'user' ) ? $get( 'user' ) : '—' ) . '</span>';

	if ( '' !== $get( 'ip' ) ) {
		echo '<span class="bw-table__sub">' . esc_html( $get( 'ip' ) ) . '</span>';
	}

	if ( '' !== $get( 'agent' ) ) {
		echo '<span class="bw-table__sub">' . esc_html( blueworx_sso_browser_label( $get( 'agent' ) ) ) . '</span>';
	}

	echo '</td></tr>';
}

/**
 * The name of one leg of the flow.
 *
 * @param string $stage Recorded stage.
 * @return string
 */
function blueworx_sso_stage_label( $stage ) {
	$labels = array(
		'start'  => __( 'Left for the provider', 'blueworx-labs-wordpress' ),
		'return' => __( 'Came back', 'blueworx-labs-wordpress' ),
		'logout' => __( 'Signed out', 'blueworx-labs-wordpress' ),
	);

	return isset( $labels[ $stage ] ) ? $labels[ $stage ] : $stage;
}

/**
 * The badge for an outcome.
 *
 * @param string $outcome Recorded outcome.
 * @return string HTML.
 */
function blueworx_sso_outcome_badge( $outcome ) {
	if ( 'success' === $outcome ) {
		return blueworx_ds_badge( __( 'Worked', 'blueworx-labs-wordpress' ), 'success' );
	}

	if ( 'started' === $outcome ) {
		return blueworx_ds_badge( __( 'Started', 'blueworx-labs-wordpress' ), 'info' );
	}

	return blueworx_ds_badge( __( 'Failed', 'blueworx-labs-wordpress' ), 'danger' );
}

/**
 * What the sign-in cookie did, in words.
 *
 * @param string $state Recorded cookie state.
 * @return string
 */
function blueworx_sso_cookie_label( $state ) {
	$labels = array(
		'set'      => __( 'Set on the way out', 'blueworx-labs-wordpress' ),
		'not_set'  => __( 'Could not be set', 'blueworx-labs-wordpress' ),
		'returned' => __( 'Came back', 'blueworx-labs-wordpress' ),
		'missing'  => __( 'Did not come back', 'blueworx-labs-wordpress' ),
		'mismatch' => __( 'Came back, but from a later sign-in', 'blueworx-labs-wordpress' ),
		'unbound'  => __( 'This attempt was never given one', 'blueworx-labs-wordpress' ),
	);

	return isset( $labels[ $state ] ) ? $labels[ $state ] : '—';
}

/**
 * A plain sentence for a reason code.
 *
 * The code stays on the row underneath: it is what gets quoted in a support
 * thread, and translating it away would make that impossible.
 *
 * @param string $reason Recorded reason.
 * @return string
 */
function blueworx_sso_reason_label( $reason ) {
	$labels = array(
		'sent_to_provider'                         => __( 'Handed over to the provider.', 'blueworx-labs-wordpress' ),
		'cookie_not_set_headers_already_sent'      => __( 'The page had already started sending, so the cookie could not be set. This sign-in could never have worked.', 'blueworx-labs-wordpress' ),
		'missing_state_or_code'                    => __( 'The provider came back without the parts a sign-in needs.', 'blueworx-labs-wordpress' ),
		'unknown_or_replayed_state'                => __( 'No sign-in was in progress for this. Either it had already been used, or it took more than ten minutes.', 'blueworx-labs-wordpress' ),
		'state_not_bound_to_this_browser:missing'  => __( 'The sign-in cookie never came back. If the browser sent no cookies at all, a page cache is stripping them; otherwise look at the address.', 'blueworx-labs-wordpress' ),
		'state_not_bound_to_this_browser:mismatch' => __( 'A second sign-in was started in the same browser before this one finished.', 'blueworx-labs-wordpress' ),
		'state_not_bound_to_this_browser:unbound'  => __( 'This attempt was recorded without a cookie to check against.', 'blueworx-labs-wordpress' ),
		'blueworx_sso_no_account'                  => __( 'Nobody here has that sign-in, and signing in never creates an account.', 'blueworx-labs-wordpress' ),
		'no_account_sent_to_register'              => __( 'Nobody here has that sign-in, so they were sent to the joining page.', 'blueworx-labs-wordpress' ),
		'blueworx_sso_email_unverified'            => __( 'The provider has not verified that email address.', 'blueworx-labs-wordpress' ),
		'blueworx_sso_already_linked'              => __( 'That account is already tied to a different sign-in.', 'blueworx-labs-wordpress' ),
		'blueworx_sso_domain_not_allowed'          => __( 'That email domain is not one this site accepts.', 'blueworx-labs-wordpress' ),
		'blueworx_sso_token_rejected'              => __( 'The provider refused the exchange. Usually the client secret or the return address.', 'blueworx-labs-wordpress' ),
		'blueworx_sso_token_request_failed'        => __( 'This site could not reach the provider.', 'blueworx-labs-wordpress' ),
		'blueworx_sso_bad_signature'               => __( 'The sign-in token was not signed by the provider.', 'blueworx-labs-wordpress' ),
		'blueworx_sso_bad_audience'                => __( 'The token was issued for a different application. Check the client ID.', 'blueworx-labs-wordpress' ),
		'blueworx_sso_bad_issuer'                  => __( 'The token came from a different provider than the one configured.', 'blueworx-labs-wordpress' ),
		'blueworx_sso_bad_nonce'                   => __( 'The token did not belong to this sign-in.', 'blueworx-labs-wordpress' ),
		'blueworx_sso_expired'                     => __( 'The token had expired. Check this server\'s clock.', 'blueworx-labs-wordpress' ),
	);

	if ( isset( $labels[ $reason ] ) ) {
		return $labels[ $reason ];
	}

	if ( 0 === strpos( $reason, 'provider_error:' ) ) {
		return __( 'The provider refused it before this site was involved.', 'blueworx-labs-wordpress' );
	}

	return '';
}

/**
 * A browser name from a user agent string.
 *
 * The full string is far too long for a table cell, and the only thing anybody
 * reads it for is telling one browser from another.
 *
 * @param string $agent Recorded user agent.
 * @return string
 */
function blueworx_sso_browser_label( $agent ) {
	$browsers = array(
		'Edg/'     => 'Edge',
		'OPR/'     => 'Opera',
		'Chrome/'  => 'Chrome',
		'Safari/'  => 'Safari',
		'Firefox/' => 'Firefox',
	);

	foreach ( $browsers as $needle => $name ) {
		if ( false !== strpos( $agent, $needle ) ) {
			return $name;
		}
	}

	return substr( $agent, 0, 40 );
}

/**
 * Empties the log.
 *
 * @return void
 */
function blueworx_sso_handle_clear_log() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_clear_sso_log' );

	blueworx_sso_clear_events();

	set_transient( 'blueworx_labs_notice', __( 'Sign-on log cleared.', 'blueworx-labs-wordpress' ), 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-sso-logs' ) );
	exit;
}
add_action( 'admin_post_blueworx_clear_sso_log', 'blueworx_sso_handle_clear_log' );
