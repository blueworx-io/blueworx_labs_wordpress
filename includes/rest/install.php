<?php
/**
 * Headless REST layer — database installation and scheduled maintenance.
 *
 * Creates the refresh-token and invite tables, tracks a schema version so the
 * tables are created/updated on plugin update (not only on activation), and
 * schedules garbage collection of expired refresh tokens.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current headless schema version. Bump when the table structure changes.
 */
if ( ! defined( 'BLUEWORX_HEADLESS_DB_VERSION' ) ) {
	define( 'BLUEWORX_HEADLESS_DB_VERSION', '1' );
}

/**
 * Returns the refresh-token table name (with the site table prefix).
 *
 * @return string Fully-qualified table name.
 */
function blueworx_headless_refresh_tokens_table() {
	global $wpdb;

	return $wpdb->prefix . 'blueworx_refresh_tokens';
}

/**
 * Returns the invites table name (with the site table prefix).
 *
 * @return string Fully-qualified table name.
 */
function blueworx_headless_invites_table() {
	global $wpdb;

	return $wpdb->prefix . 'blueworx_invites';
}

/**
 * Creates or updates the headless database tables via dbDelta.
 *
 * @return void
 */
function blueworx_headless_install() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$refresh_table   = blueworx_headless_refresh_tokens_table();
	$invites_table   = blueworx_headless_invites_table();

	$refresh_sql = "CREATE TABLE {$refresh_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		family_id char(32) NOT NULL,
		token_hash char(64) NOT NULL,
		issued_at datetime NOT NULL,
		expires_at datetime NOT NULL,
		revoked tinyint(1) NOT NULL DEFAULT 0,
		replaced_by char(64) DEFAULT NULL,
		ip varchar(100) DEFAULT NULL,
		user_agent varchar(255) DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY token_hash (token_hash),
		KEY user_id (user_id),
		KEY family_id (family_id),
		KEY expires_at (expires_at)
	) {$charset_collate};";

	$invites_sql = "CREATE TABLE {$invites_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		token_hash char(64) NOT NULL,
		email varchar(191) DEFAULT NULL,
		role varchar(60) DEFAULT NULL,
		created_by bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		expires_at datetime NOT NULL,
		used_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY token_hash (token_hash),
		KEY email (email)
	) {$charset_collate};";

	dbDelta( $refresh_sql );
	dbDelta( $invites_sql );

	update_option( 'blueworx_headless_db_version', BLUEWORX_HEADLESS_DB_VERSION );

	blueworx_headless_schedule_events();
}

/**
 * Ensures the schema is current on normal loads (covers plugin updates that do
 * not fire the activation hook).
 *
 * @return void
 */
function blueworx_headless_maybe_upgrade_db() {
	if ( get_option( 'blueworx_headless_db_version' ) !== BLUEWORX_HEADLESS_DB_VERSION ) {
		blueworx_headless_install();
	}
}
add_action( 'admin_init', 'blueworx_headless_maybe_upgrade_db' );

/**
 * Schedules the daily refresh-token garbage-collection event.
 *
 * @return void
 */
function blueworx_headless_schedule_events() {
	if ( ! wp_next_scheduled( 'blueworx_headless_gc_tokens' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'blueworx_headless_gc_tokens' );
	}
}

/**
 * Clears scheduled events on deactivation.
 *
 * @return void
 */
function blueworx_headless_clear_scheduled_events() {
	$timestamp = wp_next_scheduled( 'blueworx_headless_gc_tokens' );

	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'blueworx_headless_gc_tokens' );
	}
}

/**
 * Deletes refresh-token rows whose expiry has passed.
 *
 * @return void
 */
function blueworx_headless_gc_tokens() {
	global $wpdb;

	$table = blueworx_headless_refresh_tokens_table();

	// Direct query on a custom table; no caching applies to a maintenance sweep.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "DELETE FROM {$table} WHERE expires_at < %s", gmdate( 'Y-m-d H:i:s' ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
}
add_action( 'blueworx_headless_gc_tokens', 'blueworx_headless_gc_tokens' );
