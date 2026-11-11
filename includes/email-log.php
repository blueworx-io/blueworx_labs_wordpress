<?php
/**
 * Email log: a record of every email the site hands to its mailer.
 *
 * WordPress sends mail and forgets it. When a contact form says "sent" and
 * nothing arrives, there is nothing on the site to say whether the message
 * ever left, who it was addressed to, or what the mailer said when it refused.
 * This keeps that record, whatever is doing the sending underneath — PHP's
 * mail(), an SMTP plugin, or a delivery service.
 *
 * Rows live in their own table rather than an option, because a busy store
 * sends thousands of messages a month and an option that size is loaded on
 * every request. They are kept for thirty days and cleared daily.
 *
 * The body is stored, because the body is often the thing that explains the
 * complaint ("the link in the email was wrong"). It is never shown in the
 * list, only on a page of its own that administrators open on purpose.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many days a row is kept.
 */
const BLUEWORX_EMAIL_LOG_DAYS = 30;

/**
 * Rows per page on the Email logs screen.
 */
const BLUEWORX_EMAIL_LOG_PER_PAGE = 25;

/**
 * The daily event that clears old rows.
 */
const BLUEWORX_EMAIL_LOG_PRUNE_EVENT = 'blueworx_email_log_prune';

/**
 * The option that records which table version this site has.
 */
const BLUEWORX_EMAIL_LOG_SCHEMA_OPTION = 'blueworx_email_log_schema';

/**
 * The table's current shape. Bump when the columns change.
 */
const BLUEWORX_EMAIL_LOG_SCHEMA = '1';

/**
 * The log table's name, with this site's prefix.
 *
 * @return string
 */
function blueworx_email_log_table() {
	global $wpdb;

	return $wpdb->prefix . 'blueworx_email_log';
}

/**
 * Creates or updates the log table.
 *
 * Runs from the first write on a site that has no table yet, or whose table
 * is from an older schema version.
 *
 * @return void
 */
function blueworx_email_log_install() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = blueworx_email_log_table();
	$charset = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			sent_at datetime NOT NULL,
			status varchar(10) NOT NULL DEFAULT 'sent',
			recipients text NOT NULL,
			subject text NOT NULL,
			from_header varchar(255) NOT NULL DEFAULT '',
			headers text NOT NULL,
			content_type varchar(100) NOT NULL DEFAULT '',
			attachments text NOT NULL,
			body longtext NOT NULL,
			mailer varchar(20) NOT NULL DEFAULT '',
			host varchar(255) NOT NULL DEFAULT '',
			error text NOT NULL,
			PRIMARY KEY  (id),
			KEY sent_at (sent_at),
			KEY status (status)
		) {$charset};"
	);

	update_option( BLUEWORX_EMAIL_LOG_SCHEMA_OPTION, BLUEWORX_EMAIL_LOG_SCHEMA );
}

/**
 * Whether the table exists, checked once per request.
 *
 * @return bool
 */
function blueworx_email_log_table_exists() {
	global $wpdb;

	static $exists = null;

	if ( null === $exists ) {
		$table  = blueworx_email_log_table();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	return $exists;
}

/**
 * Makes sure the table is there before a write.
 *
 * @return void
 */
function blueworx_email_log_ensure_table() {
	if ( ! blueworx_email_log_table_exists() || BLUEWORX_EMAIL_LOG_SCHEMA !== get_option( BLUEWORX_EMAIL_LOG_SCHEMA_OPTION ) ) {
		blueworx_email_log_install();
	}
}

/**
 * What the mailer told us about the message it is about to send.
 *
 * Captured on phpmailer_init, which runs after the From, the content type and
 * the transport are all settled and just before the message leaves. The
 * succeeded and failed hooks that follow carry the message but not the mailer,
 * so this bridges the two.
 *
 * @param array|null $set When given, replaces the stored details.
 * @return array
 */
function blueworx_email_log_current( $set = null ) {
	static $current = array();

	if ( null !== $set ) {
		$current = $set;
	}

	return $current;
}

/**
 * Remembers the mailer's details for the message about to go.
 *
 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer The mailer, by reference.
 * @return void
 */
function blueworx_email_log_capture_mailer( $phpmailer ) {
	// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer's own property names.
	$from = (string) $phpmailer->From;

	if ( '' !== (string) $phpmailer->FromName ) {
		$from = $phpmailer->FromName . ' <' . $from . '>';
	}

	blueworx_email_log_current(
		array(
			'from'         => $from,
			'content_type' => (string) $phpmailer->ContentType,
			'mailer'       => (string) $phpmailer->Mailer,
			'host'         => 'smtp' === $phpmailer->Mailer ? (string) $phpmailer->Host : '',
		)
	);
	// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
}

/**
 * Records a message the mailer accepted.
 *
 * @param array $mail_data to, subject, message, headers, attachments.
 * @return void
 */
function blueworx_email_log_record_success( $mail_data ) {
	blueworx_email_log_write( 'sent', (array) $mail_data, '' );
}

/**
 * Records a message the mailer refused.
 *
 * @param WP_Error $error The error, with the message in its data.
 * @return void
 */
function blueworx_email_log_record_failure( $error ) {
	$data = is_wp_error( $error ) ? (array) $error->get_error_data() : array();

	blueworx_email_log_write( 'failed', $data, is_wp_error( $error ) ? $error->get_error_message() : '' );
}

/**
 * Turns the to/headers/attachments wp_mail accepts into one line each.
 *
 * @param mixed $value A string, a comma-separated string, or an array.
 * @return string
 */
function blueworx_email_log_flatten( $value ) {
	if ( is_array( $value ) ) {
		$value = implode( "\n", array_map( 'strval', $value ) );
	}

	return trim( (string) $value );
}

/**
 * Writes one row.
 *
 * @param string $status  sent|failed.
 * @param array  $data    to, subject, message, headers, attachments.
 * @param string $message The mailer's error, when it failed.
 * @return void
 */
function blueworx_email_log_write( $status, $data, $message ) {
	global $wpdb;

	blueworx_email_log_ensure_table();

	$mailer = blueworx_email_log_current();
	blueworx_email_log_current( array() );

	$to = isset( $data['to'] ) ? $data['to'] : '';

	if ( is_string( $to ) ) {
		$to = array_map( 'trim', explode( ',', $to ) );
	}

	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		blueworx_email_log_table(),
		array(
			'sent_at'      => current_time( 'mysql', true ),
			'status'       => 'failed' === $status ? 'failed' : 'sent',
			'recipients'   => blueworx_email_log_flatten( $to ),
			'subject'      => isset( $data['subject'] ) ? (string) $data['subject'] : '',
			'from_header'  => isset( $mailer['from'] ) ? $mailer['from'] : '',
			'headers'      => blueworx_email_log_flatten( isset( $data['headers'] ) ? $data['headers'] : '' ),
			'content_type' => isset( $mailer['content_type'] ) ? $mailer['content_type'] : '',
			'attachments'  => blueworx_email_log_flatten( isset( $data['attachments'] ) ? array_map( 'basename', (array) $data['attachments'] ) : '' ),
			'body'         => isset( $data['message'] ) ? (string) $data['message'] : '',
			'mailer'       => isset( $mailer['mailer'] ) ? $mailer['mailer'] : '',
			'host'         => isset( $mailer['host'] ) ? $mailer['host'] : '',
			'error'        => (string) $message,
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
}

/**
 * Deletes rows older than the retention period.
 *
 * @return void
 */
function blueworx_email_log_prune() {
	global $wpdb;

	if ( ! blueworx_email_log_table_exists() ) {
		return;
	}

	$table  = blueworx_email_log_table();
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - BLUEWORX_EMAIL_LOG_DAYS * DAY_IN_SECONDS );

	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE sent_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the prefix.
}

/**
 * Empties the log.
 *
 * @return void
 */
function blueworx_email_log_clear() {
	global $wpdb;

	if ( ! blueworx_email_log_table_exists() ) {
		return;
	}

	$table = blueworx_email_log_table();

	$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the prefix.
}

/**
 * Keeps the daily clear-out scheduled while the function is on, and not
 * otherwise.
 *
 * @return void
 */
function blueworx_email_log_schedule() {
	$next = wp_next_scheduled( BLUEWORX_EMAIL_LOG_PRUNE_EVENT );

	if ( blueworx_feature_enabled( 'email_log' ) ) {
		if ( ! $next ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', BLUEWORX_EMAIL_LOG_PRUNE_EVENT );
		}
	} elseif ( $next ) {
		wp_unschedule_event( $next, BLUEWORX_EMAIL_LOG_PRUNE_EVENT );
	}
}

/**
 * Reads a page of rows matching the filters.
 *
 * @param array $filters search, status, from, to (Y-m-d), page.
 * @return array{rows: array, total: int}
 */
function blueworx_email_log_query( $filters ) {
	global $wpdb;

	if ( ! blueworx_email_log_table_exists() ) {
		return array(
			'rows'  => array(),
			'total' => 0,
		);
	}

	$table = blueworx_email_log_table();
	$where = array( '1=1' );

	if ( '' !== $filters['search'] ) {
		$like    = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
		$where[] = $wpdb->prepare( '(recipients LIKE %s OR subject LIKE %s OR from_header LIKE %s)', $like, $like, $like );
	}

	if ( in_array( $filters['status'], array( 'sent', 'failed' ), true ) ) {
		$where[] = $wpdb->prepare( 'status = %s', $filters['status'] );
	}

	// Dates are read in the site's timezone and the column is UTC.
	if ( '' !== $filters['from'] ) {
		$where[] = $wpdb->prepare( 'sent_at >= %s', get_gmt_from_date( $filters['from'] . ' 00:00:00' ) );
	}

	if ( '' !== $filters['to'] ) {
		$where[] = $wpdb->prepare( 'sent_at <= %s', get_gmt_from_date( $filters['to'] . ' 23:59:59' ) );
	}

	$sql    = implode( ' AND ', $where );
	$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$sql}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Each clause above is prepared.
	$offset = ( max( 1, (int) $filters['page'] ) - 1 ) * BLUEWORX_EMAIL_LOG_PER_PAGE;

	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT id, sent_at, status, recipients, subject, from_header, mailer, host, error FROM {$table} WHERE {$sql} ORDER BY sent_at DESC, id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Each clause above is prepared.
			BLUEWORX_EMAIL_LOG_PER_PAGE,
			$offset
		),
		ARRAY_A
	);

	return array(
		'rows'  => is_array( $rows ) ? $rows : array(),
		'total' => $total,
	);
}

/**
 * Reads one row in full.
 *
 * @param int $id Row id.
 * @return array|null
 */
function blueworx_email_log_get( $id ) {
	global $wpdb;

	if ( ! blueworx_email_log_table_exists() ) {
		return null;
	}

	$table = blueworx_email_log_table();
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the prefix.

	return is_array( $row ) ? $row : null;
}

/**
 * The address of one message's own page.
 *
 * @param int $id Row id.
 * @return string
 */
function blueworx_email_log_view_url( $id ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'blueworx_email_log_view',
				'id'     => (int) $id,
			),
			admin_url( 'admin-post.php' )
		),
		'blueworx_email_log_view_' . (int) $id
	);
}

/**
 * Clears the log from the screen's button.
 *
 * @return void
 */
function blueworx_email_log_handle_clear() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_clear_email_log' );

	blueworx_email_log_clear();

	wp_safe_redirect( add_query_arg( 'cleared', '1', admin_url( 'admin.php?page=blueworx-email-log' ) ) );
	exit;
}

if ( blueworx_feature_enabled( 'email_log' ) ) {
	add_action( 'phpmailer_init', 'blueworx_email_log_capture_mailer', PHP_INT_MAX );
	add_action( 'wp_mail_succeeded', 'blueworx_email_log_record_success' );
	add_action( 'wp_mail_failed', 'blueworx_email_log_record_failure' );
}

add_action( 'init', 'blueworx_email_log_schedule' );
add_action( BLUEWORX_EMAIL_LOG_PRUNE_EVENT, 'blueworx_email_log_prune' );
add_action( 'admin_post_blueworx_clear_email_log', 'blueworx_email_log_handle_clear' );
