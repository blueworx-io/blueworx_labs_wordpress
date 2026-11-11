<?php
/**
 * Email log: the Email logs screen and each message's own page.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The filters on the current request, cleaned.
 *
 * @return array search, status, from, to, page.
 */
function blueworx_email_log_filters() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters on a GET screen; nothing is changed.
	$date = function ( $key ) {
		$value = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';

		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	};

	return array(
		'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
		'from'   => $date( 'from' ),
		'to'     => $date( 'to' ),
		'page'   => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
	);
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}

/**
 * Renders the Email logs screen.
 *
 * @return void
 */
function blueworx_render_email_log_page() {
	$on      = blueworx_feature_enabled( 'email_log' );
	$filters = blueworx_email_log_filters();
	$result  = blueworx_email_log_query( $filters );

	blueworx_open_admin_page(
		array(
			'title'   => __( 'Email logs', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'Every email this site has handed to its mailer, and whether the mailer took it.', 'blueworx-labs-wordpress' ),
			'actions' => blueworx_ds_button(
				array(
					'label'   => __( 'Email settings', 'blueworx-labs-wordpress' ),
					'href'    => admin_url( 'admin.php?page=blueworx-labs-wordpress#notifications' ),
					'variant' => 'secondary',
					'size'    => 'sm',
				)
			),
		)
	);

	if ( ! $on ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone'  => 'info',
					'title' => __( 'Email logging is switched off', 'blueworx-labs-wordpress' ),
					'text'  => __( 'Nothing new is recorded while it is off. Switch it on under Notifications & Cleanup in Enhancements. Anything already recorded is still shown below.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A flag on the redirect back from the clear action, which checked its own nonce.
	if ( isset( $_GET['cleared'] ) ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone' => 'success',
					'text' => __( 'The log has been cleared.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	blueworx_email_log_render_card( $filters, $result );

	blueworx_close_admin_page();
}

/**
 * The card holding the filters, the table and the pager.
 *
 * @param array $filters From blueworx_email_log_filters().
 * @param array $result  From blueworx_email_log_query().
 * @return void
 */
function blueworx_email_log_render_card( $filters, $result ) {
	$total = (int) $result['total'];
	$rows  = $result['rows'];

	echo '<section class="bw-card bw-card--flush"><div class="bw-card__head"><div class="bw-card__titles">';
	echo '<p class="bw-card__eyebrow">' . esc_html(
		sprintf(
			/* translators: %s: number of days. */
			__( 'Newest first, kept for %s days', 'blueworx-labs-wordpress' ),
			number_format_i18n( BLUEWORX_EMAIL_LOG_DAYS )
		)
	) . '</p>';
	echo '<h2 class="bw-card__title">' . esc_html__( 'Emails', 'blueworx-labs-wordpress' ) . '</h2>';
	echo '</div><div class="bw-card__actions">';

	if ( $total > 0 || blueworx_email_log_is_filtered( $filters ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'blueworx_clear_email_log' );
		echo '<input type="hidden" name="action" value="blueworx_clear_email_log" />';
		echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helper escapes everything it emits.
			array(
				'label'   => __( 'Clear log', 'blueworx-labs-wordpress' ),
				'type'    => 'submit',
				'variant' => 'secondary',
				'size'    => 'sm',
				'attrs'   => array( 'data-testid' => 'bw-email-log-clear' ),
			)
		);
		echo '</form>';
	}

	echo '</div></div>';

	blueworx_email_log_render_toolbar( $filters );

	if ( empty( $rows ) ) {
		echo '<div class="bw-card__body">';
		echo wp_kses(
			blueworx_ds_empty_state(
				array(
					'icon'  => 'mail',
					'title' => blueworx_email_log_is_filtered( $filters )
						? __( 'Nothing matches those filters', 'blueworx-labs-wordpress' )
						: __( 'Nothing has been sent yet', 'blueworx-labs-wordpress' ),
					'text'  => blueworx_email_log_is_filtered( $filters )
						? __( 'Widen the dates or clear the search to see more.', 'blueworx-labs-wordpress' )
						: __( 'Every email the site sends from now on is listed here, with whether the mailer accepted it.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
		echo '</div></section>';

		return;
	}

	echo '<div style="overflow-x:auto"><table class="bw-table" data-testid="bw-email-log-table"><thead><tr>';
	echo '<th scope="col">' . esc_html__( 'When', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'To', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Subject', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Status', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Sent via', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Details', 'blueworx-labs-wordpress' ) . '</span></th>';
	echo '</tr></thead><tbody>';

	foreach ( $rows as $row ) {
		blueworx_email_log_render_row( $row );
	}

	echo '</tbody></table></div>';

	blueworx_email_log_render_pager( $filters, $total, count( $rows ) );

	echo '</section>';
}

/**
 * Whether any filter is narrowing the list.
 *
 * @param array $filters From blueworx_email_log_filters().
 * @return bool
 */
function blueworx_email_log_is_filtered( $filters ) {
	return '' !== $filters['search'] || '' !== $filters['from'] || '' !== $filters['to'] || in_array( $filters['status'], array( 'sent', 'failed' ), true );
}

/**
 * The filter bar.
 *
 * @param array $filters From blueworx_email_log_filters().
 * @return void
 */
function blueworx_email_log_render_toolbar( $filters ) {
	echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="bw-toolbar" data-testid="bw-email-log-filters">';
	echo '<input type="hidden" name="page" value="blueworx-email-log" />';

	echo '<div class="bw-toolbar__group">';
	echo '<label class="screen-reader-text" for="bw-email-log-search">' . esc_html__( 'Search by recipient, subject or sender', 'blueworx-labs-wordpress' ) . '</label>';
	echo blueworx_ds_input( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helper escapes everything it emits.
		array(
			'type'  => 'search',
			'name'  => 's',
			'id'    => 'bw-email-log-search',
			'value' => $filters['search'],
			'small' => true,
			'attrs' => array( 'placeholder' => __( 'Recipient, subject or sender', 'blueworx-labs-wordpress' ) ),
		)
	);
	echo '</div>';

	echo '<div class="bw-toolbar__group">';
	echo '<label class="screen-reader-text" for="bw-email-log-status">' . esc_html__( 'Status', 'blueworx-labs-wordpress' ) . '</label>';
	echo blueworx_ds_select( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helper escapes everything it emits.
		array(
			'name'     => 'status',
			'id'       => 'bw-email-log-status',
			'selected' => $filters['status'],
			'options'  => array(
				''       => __( 'Sent and failed', 'blueworx-labs-wordpress' ),
				'sent'   => __( 'Sent', 'blueworx-labs-wordpress' ),
				'failed' => __( 'Failed', 'blueworx-labs-wordpress' ),
			),
		)
	);
	echo '</div>';

	echo '<div class="bw-toolbar__group">';
	echo '<label class="screen-reader-text" for="bw-email-log-from">' . esc_html__( 'From date', 'blueworx-labs-wordpress' ) . '</label>';
	echo blueworx_ds_input( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helper escapes everything it emits.
		array(
			'type'  => 'date',
			'name'  => 'from',
			'id'    => 'bw-email-log-from',
			'value' => $filters['from'],
			'small' => true,
		)
	);
	echo '<span>' . esc_html__( 'to', 'blueworx-labs-wordpress' ) . '</span>';
	echo '<label class="screen-reader-text" for="bw-email-log-to">' . esc_html__( 'To date', 'blueworx-labs-wordpress' ) . '</label>';
	echo blueworx_ds_input( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helper escapes everything it emits.
		array(
			'type'  => 'date',
			'name'  => 'to',
			'id'    => 'bw-email-log-to',
			'value' => $filters['to'],
			'small' => true,
		)
	);
	echo '</div>';

	echo '<div class="bw-toolbar__group">';
	echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helper escapes everything it emits.
		array(
			'label' => __( 'Filter', 'blueworx-labs-wordpress' ),
			'type'  => 'submit',
			'size'  => 'sm',
		)
	);

	if ( blueworx_email_log_is_filtered( $filters ) ) {
		echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helper escapes everything it emits.
			array(
				'label'   => __( 'Reset', 'blueworx-labs-wordpress' ),
				'href'    => admin_url( 'admin.php?page=blueworx-email-log' ),
				'variant' => 'ghost',
				'size'    => 'sm',
			)
		);
	}

	echo '</div></form>';
}

/**
 * One row of the table.
 *
 * @param array $row A row from blueworx_email_log_query().
 * @return void
 */
function blueworx_email_log_render_row( $row ) {
	$time       = strtotime( $row['sent_at'] . ' UTC' );
	$recipients = array_filter( array_map( 'trim', explode( "\n", (string) $row['recipients'] ) ) );
	$first      = array_shift( $recipients );
	$failed     = 'failed' === $row['status'];

	echo '<tr data-testid="bw-email-log-row" data-status="' . esc_attr( $row['status'] ) . '">';

	echo '<td><span class="bw-table__primary">' . esc_html( $time ? wp_date( 'j M H:i', $time ) : '—' ) . '</span>';
	echo '<span class="bw-table__sub">' . esc_html( $time ? wp_date( 'Y', $time ) : '' ) . '</span></td>';

	echo '<td><span class="bw-table__primary">' . esc_html( null !== $first ? $first : '—' ) . '</span>';

	if ( ! empty( $recipients ) ) {
		echo '<span class="bw-table__sub">' . esc_html(
			sprintf(
				/* translators: %s: how many more recipients. */
				_n( 'and %s more', 'and %s more', count( $recipients ), 'blueworx-labs-wordpress' ),
				number_format_i18n( count( $recipients ) )
			)
		) . '</span>';
	}

	echo '</td>';

	echo '<td><span class="bw-table__primary">' . esc_html( '' !== $row['subject'] ? $row['subject'] : __( '(no subject)', 'blueworx-labs-wordpress' ) ) . '</span>';

	if ( '' !== $row['from_header'] ) {
		echo '<span class="bw-table__sub">' . esc_html(
			sprintf(
				/* translators: %s: the From address. */
				__( 'From %s', 'blueworx-labs-wordpress' ),
				$row['from_header']
			)
		) . '</span>';
	}

	echo '</td>';

	echo '<td>';
	echo wp_kses(
		$failed
			? blueworx_ds_badge( __( 'Failed', 'blueworx-labs-wordpress' ), 'danger', true )
			: blueworx_ds_badge( __( 'Sent', 'blueworx-labs-wordpress' ), 'success', true ),
		blueworx_ds_allowed_html()
	);

	if ( $failed && '' !== $row['error'] ) {
		echo '<span class="bw-table__sub">' . esc_html( $row['error'] ) . '</span>';
	}

	echo '</td>';

	echo '<td><span class="bw-table__primary">' . esc_html( blueworx_email_log_mailer_label( $row['mailer'] ) ) . '</span>';

	if ( '' !== $row['host'] ) {
		echo '<span class="bw-table__sub">' . esc_html( $row['host'] ) . '</span>';
	}

	echo '</td>';

	echo '<td class="bw-table__num"><span class="bw-rowactions">';
	echo '<a class="bw-rowactions__link" href="' . esc_url( blueworx_email_log_view_url( (int) $row['id'] ) ) . '" target="_blank" rel="noopener" data-testid="bw-email-log-view">' . esc_html__( 'View', 'blueworx-labs-wordpress' ) . '</a>';
	echo '</span></td>';

	echo '</tr>';
}

/**
 * The mailer in plain words.
 *
 * @param string $mailer PHPMailer's Mailer: mail, smtp, sendmail, qmail.
 * @return string
 */
function blueworx_email_log_mailer_label( $mailer ) {
	switch ( $mailer ) {
		case 'smtp':
			return __( 'SMTP', 'blueworx-labs-wordpress' );
		case 'sendmail':
		case 'qmail':
			return __( 'Sendmail', 'blueworx-labs-wordpress' );
		case 'mail':
			return __( 'PHP mail()', 'blueworx-labs-wordpress' );
		case '':
			return '—';
	}

	return $mailer;
}

/**
 * The strip under the table: the count and previous/next.
 *
 * @param array $filters From blueworx_email_log_filters().
 * @param int   $total   Rows matching the filters.
 * @param int   $shown   Rows on this page.
 * @return void
 */
function blueworx_email_log_render_pager( $filters, $total, $shown ) {
	$page  = max( 1, (int) $filters['page'] );
	$pages = max( 1, (int) ceil( $total / BLUEWORX_EMAIL_LOG_PER_PAGE ) );
	$first = ( $page - 1 ) * BLUEWORX_EMAIL_LOG_PER_PAGE + 1;

	$url = function ( $to ) use ( $filters ) {
		return add_query_arg(
			array(
				'page'   => 'blueworx-email-log',
				's'      => '' !== $filters['search'] ? $filters['search'] : false,
				'status' => '' !== $filters['status'] ? $filters['status'] : false,
				'from'   => '' !== $filters['from'] ? $filters['from'] : false,
				'to'     => '' !== $filters['to'] ? $filters['to'] : false,
				'paged'  => $to > 1 ? $to : false,
			),
			admin_url( 'admin.php' )
		);
	};

	echo '<div class="bw-tablefoot"><span>' . esc_html(
		sprintf(
			/* translators: 1: first row number, 2: last row number, 3: total rows. */
			__( 'Showing %1$s–%2$s of %3$s', 'blueworx-labs-wordpress' ),
			number_format_i18n( $first ),
			number_format_i18n( $first + $shown - 1 ),
			number_format_i18n( $total )
		)
	) . '</span>';

	if ( $pages > 1 ) {
		echo '<nav class="bw-pager" aria-label="' . esc_attr__( 'Pages', 'blueworx-labs-wordpress' ) . '">';
		echo '<span class="bw-pager__of">' . esc_html(
			sprintf(
				/* translators: 1: current page, 2: total pages. */
				__( 'Page %1$s of %2$s', 'blueworx-labs-wordpress' ),
				number_format_i18n( $page ),
				number_format_i18n( $pages )
			)
		) . '</span><span class="bw-pager__btns">';

		if ( $page > 1 ) {
			echo '<a class="bw-pager__btn" href="' . esc_url( $url( $page - 1 ) ) . '">' . esc_html__( 'Previous', 'blueworx-labs-wordpress' ) . '</a>';
		} else {
			echo '<button type="button" class="bw-pager__btn" disabled>' . esc_html__( 'Previous', 'blueworx-labs-wordpress' ) . '</button>';
		}

		if ( $page < $pages ) {
			echo '<a class="bw-pager__btn" href="' . esc_url( $url( $page + 1 ) ) . '">' . esc_html__( 'Next', 'blueworx-labs-wordpress' ) . '</a>';
		} else {
			echo '<button type="button" class="bw-pager__btn" disabled>' . esc_html__( 'Next', 'blueworx-labs-wordpress' ) . '</button>';
		}

		echo '</span></nav>';
	}

	echo '</div>';
}

/**
 * One message on a page of its own, opened in a new tab from the list.
 *
 * A plain page rather than an admin screen: it is a document to read, not a
 * place to do anything, and it should print and share cleanly. An HTML body
 * is shown inside a sandboxed frame so nothing in a message can run on the
 * admin's session.
 *
 * @return void
 */
function blueworx_email_log_handle_view() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-labs-wordpress' ) );
	}

	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

	check_admin_referer( 'blueworx_email_log_view_' . $id );

	$row = blueworx_email_log_get( $id );

	if ( null === $row ) {
		wp_die( esc_html__( 'That email is no longer in the log.', 'blueworx-labs-wordpress' ), '', array( 'response' => 404 ) );
	}

	$time    = strtotime( $row['sent_at'] . ' UTC' );
	$is_html = false !== stripos( (string) $row['content_type'], 'html' );
	$failed  = 'failed' === $row['status'];

	$details = array(
		__( 'Status', 'blueworx-labs-wordpress' )   => $failed ? __( 'Failed', 'blueworx-labs-wordpress' ) : __( 'Sent', 'blueworx-labs-wordpress' ),
		__( 'When', 'blueworx-labs-wordpress' )     => $time ? wp_date( 'j F Y, H:i:s', $time ) : '—',
		__( 'From', 'blueworx-labs-wordpress' )     => '' !== $row['from_header'] ? $row['from_header'] : '—',
		__( 'To', 'blueworx-labs-wordpress' )       => str_replace( "\n", ', ', (string) $row['recipients'] ),
		__( 'Subject', 'blueworx-labs-wordpress' )  => '' !== $row['subject'] ? $row['subject'] : __( '(no subject)', 'blueworx-labs-wordpress' ),
		__( 'Sent via', 'blueworx-labs-wordpress' ) => trim( blueworx_email_log_mailer_label( $row['mailer'] ) . ' ' . $row['host'] ),
		__( 'Format', 'blueworx-labs-wordpress' )   => $is_html ? __( 'HTML', 'blueworx-labs-wordpress' ) : __( 'Plain text', 'blueworx-labs-wordpress' ),
	);

	if ( $failed ) {
		$details[ __( 'Error', 'blueworx-labs-wordpress' ) ] = '' !== $row['error'] ? $row['error'] : '—';
	}

	if ( '' !== $row['attachments'] ) {
		$details[ __( 'Attachments', 'blueworx-labs-wordpress' ) ] = str_replace( "\n", ', ', (string) $row['attachments'] );
	}

	if ( '' !== $row['headers'] ) {
		$details[ __( 'Headers', 'blueworx-labs-wordpress' ) ] = (string) $row['headers'];
	}

	nocache_headers();
	header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );

	echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '" />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_language_attributes() is escaped by core.
	echo '<meta name="viewport" content="width=device-width, initial-scale=1" /><meta name="robots" content="noindex" />';
	echo '<title>' . esc_html( $details[ __( 'Subject', 'blueworx-labs-wordpress' ) ] ) . '</title>';
	echo '<style>
		body{margin:0;padding:32px;background:#f4f5f7;color:#1f2430;font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
		main{max-width:900px;margin:0 auto;background:#fff;border:1px solid #e1e4ea;border-radius:12px;overflow:hidden}
		header{padding:24px 28px;border-bottom:1px solid #e1e4ea}
		header p{margin:0 0 8px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280}
		header h1{margin:0;font-size:22px;line-height:1.3}
		dl{display:grid;grid-template-columns:140px 1fr;gap:10px 20px;margin:0;padding:20px 28px;border-bottom:1px solid #e1e4ea}
		dt{color:#6b7280;font-size:13px}
		dd{margin:0;overflow-wrap:anywhere;white-space:pre-line}
		.status{display:inline-block;padding:2px 10px;border-radius:999px;font-size:13px;font-weight:600}
		.status--sent{background:#e3f6ec;color:#116b3a}
		.status--failed{background:#fde8e8;color:#9b1c1c}
		section{padding:20px 28px}
		section h2{margin:0 0 12px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280}
		pre{margin:0;white-space:pre-wrap;overflow-wrap:anywhere;font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace}
		iframe{display:block;width:100%;min-height:600px;border:1px solid #e1e4ea;border-radius:8px;background:#fff}
	</style></head><body><main data-testid="bw-email-log-message">';

	echo '<header><p>' . esc_html( get_bloginfo( 'name' ) ) . ' · ' . esc_html__( 'Email log', 'blueworx-labs-wordpress' ) . '</p>';
	echo '<h1>' . esc_html( $details[ __( 'Subject', 'blueworx-labs-wordpress' ) ] ) . '</h1></header>';

	echo '<dl>';

	foreach ( $details as $label => $value ) {
		echo '<dt>' . esc_html( $label ) . '</dt>';

		if ( __( 'Status', 'blueworx-labs-wordpress' ) === $label ) {
			echo '<dd><span class="status status--' . esc_attr( $row['status'] ) . '">' . esc_html( $value ) . '</span></dd>';
		} else {
			echo '<dd>' . esc_html( $value ) . '</dd>';
		}
	}

	echo '</dl>';

	echo '<section><h2>' . esc_html__( 'Message', 'blueworx-labs-wordpress' ) . '</h2>';

	if ( $is_html ) {
		echo '<iframe sandbox="" title="' . esc_attr__( 'The email as sent', 'blueworx-labs-wordpress' ) . '" srcdoc="' . esc_attr( (string) $row['body'] ) . '"></iframe>';
	} else {
		echo '<pre>' . esc_html( (string) $row['body'] ) . '</pre>';
	}

	echo '</section></main></body></html>';
	exit;
}
add_action( 'admin_post_blueworx_email_log_view', 'blueworx_email_log_handle_view' );
