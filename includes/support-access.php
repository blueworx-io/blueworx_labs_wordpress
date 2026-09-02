<?php
/**
 * BlueWorx Support Access — a key-gated, read-only access path for remote
 * troubleshooting.
 *
 * The key is stored only as a SHA-256 hash and is shown once, at generation.
 * Access is refused unless a deliberately opened window is still in effect, so
 * a leaked key is inert in the standing state of every site.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Length of the access window opened by the console toggle, in seconds.
 */
const BLUEWORX_SUPPORT_WINDOW = 86400;

/**
 * Failed key attempts allowed before a caller is locked out.
 */
const BLUEWORX_SUPPORT_MAX_FAILURES = 5;

/**
 * Lockout duration in seconds.
 */
const BLUEWORX_SUPPORT_LOCKOUT = 900;

/**
 * Gets the throttle transient key for the calling address.
 *
 * The address is hashed, not stored raw: the throttle must not turn into an
 * incidental log of who tried.
 *
 * @return string Transient key.
 */
function blueworx_support_throttle_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] )
		? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
		: 'unknown';

	return 'blueworx_support_fail_' . md5( $ip );
}

/**
 * Whether the calling address is currently locked out.
 *
 * @return bool True when further attempts must be refused.
 */
function blueworx_support_is_throttled() {
	return (int) get_transient( blueworx_support_throttle_key() ) >= BLUEWORX_SUPPORT_MAX_FAILURES;
}

/**
 * Records a failed key attempt.
 *
 * @return void
 */
function blueworx_support_record_failure() {
	$key   = blueworx_support_throttle_key();
	$count = (int) get_transient( $key ) + 1;

	set_transient( $key, $count, BLUEWORX_SUPPORT_LOCKOUT );
}

/**
 * Clears the failure counter for the calling address.
 *
 * Called after a successful key authentication, and also when an operator
 * proven legitimate by a manage_options-gated, nonce-protected console action
 * (generating or revoking a key) needs the lockout on their own address lifted.
 *
 * @return void
 */
function blueworx_support_clear_failures() {
	delete_transient( blueworx_support_throttle_key() );
}

/**
 * Whether a key currently exists for this site.
 *
 * @return bool True when a key hash is stored.
 */
function blueworx_support_has_key() {
	return '' !== (string) get_option( 'blueworx_support_key_hash', '' );
}

/**
 * Generates a new key, replacing any existing one.
 *
 * Only the hash is persisted; the raw key is returned to the caller once and
 * never stored, so it cannot be recovered from the database.
 *
 * @return string Raw key.
 */
function blueworx_support_generate_key() {
	$raw = bin2hex( random_bytes( 32 ) );

	update_option( 'blueworx_support_key_hash', hash( 'sha256', $raw ) );

	return $raw;
}

/**
 * Verifies a presented key against the stored hash.
 *
 * Uses hash_equals so a wrong key cannot be discovered by timing the response.
 *
 * @param string $raw Presented key.
 * @return bool True when the key matches.
 */
function blueworx_support_verify_key( $raw ) {
	$stored = (string) get_option( 'blueworx_support_key_hash', '' );

	if ( '' === $stored || '' === (string) $raw ) {
		return false;
	}

	return hash_equals( $stored, hash( 'sha256', (string) $raw ) );
}

/**
 * Removes the key and closes any open window.
 *
 * @return void
 */
function blueworx_support_revoke_key() {
	delete_option( 'blueworx_support_key_hash' );
	blueworx_support_close_access();
}

/**
 * Whether the access window is currently open.
 *
 * Turning the feature off is the operator's panic switch, so it has to end a
 * live session and not merely bar new logins: with the check made here, every
 * caller of this function — the login handler, the REST resolver and
 * blueworx_support_enforce_window() — treats a disabled feature exactly as it
 * treats a lapsed window.
 *
 * The console panel is unaffected: it renders inside the enhancements page
 * regardless of the toggle, and simply offers "Allow support access for 24
 * hours" again once the feature is off. Re-enabling runs through
 * blueworx_save_feature_settings(), which never consults this function.
 *
 * @return bool True when access is permitted right now.
 */
function blueworx_support_access_open() {
	if ( ! blueworx_feature_enabled( 'support_access' ) ) {
		return false;
	}

	return time() < (int) get_option( 'blueworx_support_access_until', 0 );
}

/**
 * Whether personal-data access is currently permitted.
 *
 * Never true when the access window itself is shut.
 *
 * @return bool True when data screens and routes are permitted.
 */
function blueworx_support_data_open() {
	if ( ! blueworx_support_access_open() ) {
		return false;
	}

	return time() < (int) get_option( 'blueworx_support_data_until', 0 );
}

/**
 * Opens the access window for BLUEWORX_SUPPORT_WINDOW seconds.
 *
 * The window lapses on its own; there is no scheduled task that could fail to
 * run and leave access standing open.
 *
 * @param bool $with_data Whether to also permit personal-data access.
 * @return void
 */
function blueworx_support_open_access( $with_data ) {
	$until = time() + BLUEWORX_SUPPORT_WINDOW;

	update_option( 'blueworx_support_access_until', $until );
	update_option( 'blueworx_support_data_until', $with_data ? $until : 0 );
}

/**
 * Closes the access window immediately.
 *
 * @return void
 */
function blueworx_support_close_access() {
	update_option( 'blueworx_support_access_until', 0 );
	update_option( 'blueworx_support_data_until', 0 );
}

/**
 * Gets the support role slug.
 *
 * @return string Role slug.
 */
function blueworx_support_role_slug() {
	return 'blueworx_support';
}

/**
 * Provisions the support role and user.
 *
 * Called only when a key is generated: a site that never uses support access
 * never carries a dormant account. The user's password is set to a value no
 * input can hash to, so the account cannot be signed into with a password at
 * all — the key is the only way in.
 *
 * @return int User ID, or 0 on failure.
 */
function blueworx_support_ensure_account() {
	global $wpdb;

	remove_role( blueworx_support_role_slug() );
	add_role(
		blueworx_support_role_slug(),
		__( 'BlueWorx - Support Agent (Read-Only)', 'blueworx-labs-wordpress' ),
		blueworx_readonly_build_caps()
	);

	$user = get_user_by( 'login', 'blueworx_support' );

	if ( $user instanceof WP_User ) {
		$user->set_role( blueworx_support_role_slug() );
		$user_id = (int) $user->ID;
	} else {
		$user_id = wp_insert_user(
			array(
				'user_login'   => 'blueworx_support',
				'user_pass'    => wp_generate_password( 64, true, true ),
				'user_email'   => 'support+' . wp_generate_password( 8, false ) . '@blueworx.invalid',
				'display_name' => __( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
				'role'         => blueworx_support_role_slug(),
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		$user_id = (int) $user_id;
	}

	// Make the password unusable. '!' is not a valid hash, so wp_check_password()
	// can never match it, whatever is submitted. This is why there is no
	// credential to leak, phish or rotate.
	$wpdb->update( $wpdb->users, array( 'user_pass' => '!' ), array( 'ID' => $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	clean_user_cache( $user_id );

	return $user_id;
}

/**
 * Deletes the support user and role.
 *
 * @return void
 */
function blueworx_support_remove_account() {
	$user = get_user_by( 'login', 'blueworx_support' );

	if ( $user instanceof WP_User ) {
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		wp_delete_user( $user->ID );
	}

	remove_role( blueworx_support_role_slug() );
}

/**
 * Tears support access down when the plugin is deactivated.
 *
 * Uninstall already handles a full removal, but deactivation left the
 * near-administrator account standing with nothing protecting it: the
 * read-only guarantee is the request-layer block in this file, and with the
 * plugin switched off that block does not run at all. The account and role go
 * first, and the key goes with them because it addresses an account that no
 * longer exists — leaving a live key hash behind would only misreport the
 * console's state on reactivation.
 *
 * Registered on register_deactivation_hook in blueworx-labs-wordpress.php.
 *
 * @return void
 */
function blueworx_support_on_deactivate() {
	blueworx_support_remove_account();
	blueworx_support_revoke_key();
}

/**
 * Gets the support user.
 *
 * @return WP_User|null Support user, or null when it does not exist.
 */
function blueworx_support_get_user() {
	$user = get_user_by( 'login', 'blueworx_support' );

	return $user instanceof WP_User ? $user : null;
}

/**
 * Whether the current request is running as the support user.
 *
 * Both the login AND the role are required. The login alone is not an identity
 * claim anyone else is stopped from making: on a site with open registration a
 * visitor could once have signed up as "blueworx_support" and, through
 * blueworx_support_exempt_from_site_protection(), bypassed Site Protection
 * entirely with no key and no window. Only the managed account provisioned by
 * blueworx_support_ensure_account() holds the support role, and that role is
 * never assignable from the users screen because create_users, edit_users and
 * promote_users are all removed from it.
 *
 * The role is present in every context this function gates — including the
 * login request itself, because blueworx_support_handle_login() resolves the
 * account with wp_set_current_user(), which populates WP_User::$roles from the
 * stored capabilities before any later hook runs.
 *
 * @return bool True for the support account.
 */
function blueworx_support_is_support_user() {
	$user = wp_get_current_user();

	return $user instanceof WP_User
		&& $user->exists()
		&& 'blueworx_support' === $user->user_login
		&& blueworx_readonly_user_has_role( $user, blueworx_support_role_slug() );
}

/**
 * Raw key to display once, for the request that generated it.
 *
 * @var string
 */
$GLOBALS['blueworx_support_new_key'] = '';

/**
 * Processes the support panel's form submissions.
 *
 * @return void
 */
function blueworx_support_handle_actions() {
	if ( ! blueworx_feature_enabled( 'support_access' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$action = isset( $_POST['blueworx_support_action'] )
		? sanitize_key( wp_unslash( $_POST['blueworx_support_action'] ) )
		: '';

	if ( '' === $action ) {
		return;
	}

	check_admin_referer( 'blueworx_support_panel', 'blueworx_support_nonce' );

	if ( 'generate' === $action ) {
		$GLOBALS['blueworx_support_new_key'] = blueworx_support_generate_key();
		blueworx_support_ensure_account();
		blueworx_support_clear_failures();
		blueworx_support_log_event( 'key_generated' );
		return;
	}

	if ( 'revoke' === $action ) {
		blueworx_support_revoke_key();
		blueworx_support_remove_account();
		blueworx_support_clear_failures();
		blueworx_support_log_event( 'key_revoked' );
		return;
	}

	if ( 'toggle' === $action ) {
		if ( blueworx_support_access_open() ) {
			blueworx_support_close_access();
			blueworx_support_log_event( 'access_closed' );
		} else {
			$with_data = ! empty( $_POST['blueworx_support_with_data'] );
			blueworx_support_open_access( $with_data );
			blueworx_support_log_event( $with_data ? 'data_opened' : 'access_opened' );
		}
	}
}
add_action( 'admin_init', 'blueworx_support_handle_actions' );

/**
 * The most recent time one of these events happened.
 *
 * Read from the audit log rather than from a second option of its own: the log
 * is already the record of what happened and when, and a parallel timestamp is
 * one more thing that can disagree with it.
 *
 * @param array $types Event types to look for.
 * @return int Unix timestamp, or 0.
 */
function blueworx_support_last_event_time( $types ) {
	$log  = get_option( 'blueworx_support_log', array() );
	$best = 0;

	foreach ( (array) $log as $entry ) {
		if ( ! is_array( $entry ) || ! isset( $entry['type'], $entry['time'] ) ) {
			continue;
		}

		if ( ! in_array( $entry['type'], (array) $types, true ) ) {
			continue;
		}

		$best = max( $best, (int) $entry['time'] );
	}

	return $best;
}

/**
 * The state of support access, as three plain answers.
 *
 * Key, window and last session — the three things somebody looking at this
 * screen is actually trying to find out. The key itself is stored hashed and
 * stays that way, so that row says one exists and when it was made, never what
 * it is.
 *
 * @return array Values keyed by their label.
 */
function blueworx_support_state_rows() {
	$rows      = array();
	$has_key   = '' !== (string) get_option( 'blueworx_support_key_hash', '' );
	$generated = blueworx_support_last_event_time( array( 'key_generated' ) );
	$until     = (int) get_option( 'blueworx_support_access_until', 0 );
	$last      = blueworx_support_last_event_time( array( 'login', 'rest_auth' ) );

	if ( ! $has_key ) {
		$rows[ __( 'Key', 'blueworx-labs-wordpress' ) ] = esc_html__( 'None. Generate one to open a window.', 'blueworx-labs-wordpress' );
	} elseif ( $generated > 0 ) {
		$rows[ __( 'Key', 'blueworx-labs-wordpress' ) ] = esc_html(
			sprintf(
				/* translators: %s: how long ago, e.g. "2 hours". */
				__( 'One key exists, made %s ago', 'blueworx-labs-wordpress' ),
				human_time_diff( $generated, time() )
			)
		);
	} else {
		$rows[ __( 'Key', 'blueworx-labs-wordpress' ) ] = esc_html__( 'One key exists', 'blueworx-labs-wordpress' );
	}

	if ( $until > time() ) {
		$rows[ __( 'Window', 'blueworx-labs-wordpress' ) ] = esc_html(
			sprintf(
				/* translators: %s: how long is left, e.g. "18 hours". */
				__( 'Open, %s left', 'blueworx-labs-wordpress' ),
				human_time_diff( time(), $until )
			)
		);
	} else {
		$rows[ __( 'Window', 'blueworx-labs-wordpress' ) ] = esc_html__( 'Shut', 'blueworx-labs-wordpress' );
	}

	$rows[ __( 'Last session', 'blueworx-labs-wordpress' ) ] = $last > 0
		? esc_html(
			sprintf(
				/* translators: %s: how long ago, e.g. "3 days". */
				__( '%s ago', 'blueworx-labs-wordpress' ),
				human_time_diff( $last, time() )
			)
		)
		: esc_html__( 'Nobody has signed in with a key', 'blueworx-labs-wordpress' );

	return $rows;
}

/**
 * Records an audit event, keeping the most recent 100.
 *
 * The log is what makes the access window verifiable rather than merely
 * claimed, so it records refusals as well as successes.
 *
 * Consecutive identical events from the same IP collapse into a single entry
 * carrying a count. A 100-entry cap plus one row per request means any chatty
 * caller — Heartbeat was the one that surfaced this, but it is not the only
 * candidate — silently evicts the events the log exists to prove, well inside
 * the 24-hour window it documents. Collapsing keeps the evidence and loses
 * nothing: the repeated event is still recorded, with how often and how
 * recently it happened.
 *
 * @param string $type Event type.
 * @return void
 */
function blueworx_support_log_event( $type ) {
	$log = get_option( 'blueworx_support_log', array() );

	if ( ! is_array( $log ) ) {
		$log = array();
	}

	$ip = isset( $_SERVER['REMOTE_ADDR'] )
		? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
		: '';

	$type = sanitize_key( $type );
	$last = end( $log );
	$key  = key( $log );

	if ( is_array( $last ) && isset( $last['type'] ) && $last['type'] === $type && ( isset( $last['ip'] ) ? $last['ip'] : '' ) === $ip ) {
		$log[ $key ]['count'] = ( isset( $last['count'] ) ? (int) $last['count'] : 1 ) + 1;
		// The newest occurrence is the useful timestamp: it answers "is this
		// still happening?", which the first occurrence cannot.
		$log[ $key ]['time'] = time();

		update_option( 'blueworx_support_log', $log );

		return;
	}

	$log[] = array(
		'type'  => $type,
		'time'  => time(),
		'ip'    => $ip,
		'count' => 1,
	);

	update_option( 'blueworx_support_log', array_slice( $log, -100 ) );
}

/**
 * Gets the audit log, newest first.
 *
 * @return array Log entries.
 */
function blueworx_support_get_log() {
	$log = get_option( 'blueworx_support_log', array() );

	return is_array( $log ) ? array_reverse( $log ) : array();
}

/**
 * Ends any support session once the window is shut or lapsed.
 *
 * The toggle would otherwise only bar new logins, leaving an already-open
 * session running for as long as its cookie lasted.
 *
 * @return void
 */
function blueworx_support_enforce_window() {
	if ( ! blueworx_support_is_support_user() || blueworx_support_access_open() ) {
		return;
	}

	wp_destroy_current_session();
	wp_clear_auth_cookie();
	wp_set_current_user( 0 );
	blueworx_support_log_event( 'access_expired' );

	wp_die(
		esc_html__( 'The BlueWorx support window has closed.', 'blueworx-labs-wordpress' ),
		esc_html__( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
		array( 'response' => 403 )
	);
}
// Priority 2: runs after blueworx_support_handle_login() (priority 0), which
// sets the current user and only ever runs while the window is open, so this
// check never fires on the login request itself. It also runs after
// blueworx_readonly_block_writes() (priority 0), so a session already found
// stale here is destroyed before that handler would otherwise inspect it.
add_action( 'init', 'blueworx_support_enforce_window', 2 );

/**
 * Signs the support user in from a key in the query string.
 *
 * Sets a session cookie only (no "remember me"), so the browser never keeps a
 * long-lived credential for this account.
 *
 * @return void
 */
function blueworx_support_handle_login() {
	if ( ! isset( $_GET['blueworx_support_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$key = sanitize_text_field( wp_unslash( $_GET['blueworx_support_login'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( blueworx_support_is_throttled() ) {
		blueworx_support_log_event( 'login_throttled' );
		wp_die(
			esc_html__( 'Too many attempts. Try again later.', 'blueworx-labs-wordpress' ),
			esc_html__( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
			array( 'response' => 429 )
		);
	}

	// A failure is recorded only when the key itself is wrong. A CORRECT key
	// presented while the feature is off or the window is shut says nothing
	// about the caller being an attacker, and counting it meant an operator
	// testing their own valid key could lock themselves out of the window they
	// were about to open.
	if ( ! blueworx_support_verify_key( $key ) ) {
		blueworx_support_record_failure();
		blueworx_support_log_event( 'login_refused' );
		wp_die(
			esc_html__( 'Support access is not available.', 'blueworx-labs-wordpress' ),
			esc_html__( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
			array( 'response' => 403 )
		);
	}

	if ( ! blueworx_feature_enabled( 'support_access' ) || ! blueworx_support_access_open() ) {
		blueworx_support_log_event( 'login_refused' );
		wp_die(
			esc_html__( 'Support access is not available.', 'blueworx-labs-wordpress' ),
			esc_html__( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
			array( 'response' => 403 )
		);
	}

	$user = blueworx_support_get_user();

	if ( ! $user instanceof WP_User ) {
		wp_die(
			esc_html__( 'Support access is not available.', 'blueworx-labs-wordpress' ),
			esc_html__( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
			array( 'response' => 403 )
		);
	}

	blueworx_support_clear_failures();

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, false );
	blueworx_support_log_event( 'login' );

	wp_safe_redirect( admin_url() );
	exit;
}
// Priority 0: this MUST run before blueworx_intercept_requests()
// (includes/login-security.php, also hooked on init at priority 1). At equal
// priority, intercept_requests runs first — required earlier in
// blueworx-labs-wordpress.php — and Site Protection's frontend check would
// wp_die() a logged-out key request before this handler ever ran, so the key
// could never be exchanged. Do not "tidy" this back to priority 1.
add_action( 'init', 'blueworx_support_handle_login', 0 );

/**
 * Exempts the support account from Site Protection's role allow-list.
 *
 * Site Protection (includes/login-security.php) only admits users whose role
 * is on the operator's selected list for the area, and the support role is
 * never on that list by default. Without this, a successful key exchange
 * would be logged in only to be immediately thrown back out of wp-admin by
 * backend protection, and a logged-out visitor could never reach the frontend
 * key-exchange URL at all while frontend protection is on.
 *
 * Scope is deliberately narrow: this only ever returns true when the current
 * user IS the support account (blueworx_support_is_support_user()); every
 * other user's own $has_role result passes through unchanged, so Site
 * Protection is not weakened for anyone else. It also does not touch the
 * access-window check — that gate lives entirely in
 * blueworx_support_handle_login() and runs before the support user is ever
 * logged in, so a key presented while the window is shut is still refused
 * before this filter is reached.
 *
 * @param bool   $has_role Whether the user's own roles satisfy $area's allow-list.
 * @param string $area     'frontend' or 'backend'.
 * @return bool
 */
function blueworx_support_exempt_from_site_protection( $has_role, $area ) {
	unset( $area );

	if ( blueworx_support_is_support_user() ) {
		return true;
	}

	return $has_role;
}
add_filter( 'blueworx_site_protection_role_check', 'blueworx_support_exempt_from_site_protection', 10, 2 );

/**
 * Resolves the support user from the access-key header.
 *
 * Runs on determine_current_user at priority 21, and follows the rule any
 * resolver on that filter must: a failure leaves $user_id untouched, so public
 * routes and cookie authentication keep working.
 *
 * The priority is 21 rather than the default 10 because it once had to run
 * after the headless layer's JWT resolver, which sat at 20. That layer was
 * removed in 1.54.0 and nothing else registers here now, so the number no
 * longer resolves a conflict — it is left alone rather than changed, because
 * moving a resolver on this filter is a change to who a request is
 * authenticated as, and there is nothing to gain from making it.
 *
 * determine_current_user can be invoked more than once per request, and on
 * the vast majority of requests no key header is present at all. Both facts
 * matter for the throttle:
 *
 * - The header-absent case returns immediately, before the throttle is even
 *   consulted, so anonymous REST traffic can never contribute a failure and
 *   can never be locked out. Only a request that actually presents a key can
 *   reach the verification branch below.
 * - A static guard stops a single request from recording two failures if
 *   this filter fires more than once for it. WordPress bootstraps the whole
 *   plugin fresh on every request in standard hosting (PHP-FPM, mod_php,
 *   CGI), so the static re-initialises to false at the start of each request
 *   — it only ever suppresses a second recording within the same request.
 *
 * A failure is recorded only when a key was presented and
 * blueworx_support_verify_key() rejects it — not merely because the feature
 * is off or the window is shut, since in that case the key was never even
 * checked and nothing is known about whether it was right or wrong.
 *
 * A successful authentication is logged as a "rest_auth" event (spec §1.6).
 * Without it a key used purely over REST left no trace at all in the audit log
 * this feature's accountability rests on. It uses its own static guard, for the
 * same reason as the failure counter: determine_current_user can fire more than
 * once per request and must not produce two entries for one caller.
 *
 * @param int|false $user_id User ID resolved so far.
 * @return int|false Resolved user ID.
 */
function blueworx_support_rest_auth( $user_id ) {
	static $failure_recorded = false;
	static $success_logged   = false;

	if ( ! empty( $user_id ) ) {
		return $user_id;
	}

	if ( empty( $_SERVER['HTTP_X_BLUEWORX_SUPPORT_KEY'] ) ) {
		return $user_id;
	}

	if ( blueworx_support_is_throttled() ) {
		return $user_id;
	}

	if ( ! blueworx_feature_enabled( 'support_access' ) || ! blueworx_support_access_open() ) {
		return $user_id;
	}

	$key = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_BLUEWORX_SUPPORT_KEY'] ) );

	if ( ! blueworx_support_verify_key( $key ) ) {
		if ( ! $failure_recorded ) {
			blueworx_support_record_failure();
			$failure_recorded = true;
		}
		return $user_id;
	}

	$user = blueworx_support_get_user();

	if ( ! ( $user instanceof WP_User ) ) {
		return $user_id;
	}

	blueworx_support_clear_failures();

	if ( ! $success_logged ) {
		blueworx_support_log_event( 'rest_auth' );
		$success_logged = true;
	}

	return (int) $user->ID;
}
add_filter( 'determine_current_user', 'blueworx_support_rest_auth', 21 );

/**
 * Builds the copy-ready Claude Code connection prompt for this site.
 *
 * The prompt is the paste-once alternative to explaining the connection by
 * hand at the start of every session: it carries the site URL, the key, the
 * two entry points, and — just as importantly — the limits, so the session
 * reports a 403 back rather than casting around for a way past it.
 *
 * The key is a parameter rather than something this function fetches, because
 * only the hash is ever stored: the raw value exists for exactly one request,
 * the one that generated it. Every later render passes an empty string and
 * gets a <SUPPORT-KEY> placeholder, which is the honest output — a wrong or
 * stale key pasted into a session is worse than an obvious blank.
 *
 * The prompt text is deliberately not translated. Its audience is Claude Code,
 * which is instructed in English; a localised copy would change the meaning of
 * the rules it states.
 *
 * @param string $key Raw access key, or '' when it is no longer available.
 * @return string Prompt text.
 */
function blueworx_support_claude_prompt( $key = '' ) {
	$site = untrailingslashit( home_url() );
	$key  = ( '' !== (string) $key ) ? (string) $key : '<SUPPORT-KEY>';

	$login_url = home_url( '/' . blueworx_login_slug() . '/' );

	$lines = array(
		'You are connecting to a live WordPress site running the BlueWorx Labs | WordPress',
		'Enhancements plugin. Use its Support Access path — read-only, key-gated.',
		'',
		'Site URL:    ' . $site,
		'Support key: ' . $key,
		'',
		'How to connect',
		'',
		'- REST: send the key as a header on every request.',
		'      curl -sS -H "X-Blueworx-Support-Key: ' . $key . '" "' . $site . '/wp-json/wp/v2/pages?per_page=5"',
		'  Do not put the key in a query string you might paste into a log or an issue.',
		'- Browser (only if you need to see a wp-admin screen): open',
		'      ' . $site . '/?blueworx_support_login=' . $key,
		'  It sets a session cookie and redirects to wp-admin.',
		'',
		'URLs you will need',
		'',
		'- REST index:        ' . $site . '/wp-json/',
		'- Core content:      ' . $site . '/wp-json/wp/v2/       (posts, pages, CPTs, media)',
		'- BlueWorx console:  ' . $site . '/wp-admin/admin.php?page=blueworx-labs-wordpress',
		'- /wp-login.php is blocked by this plugin. The custom login URL is ' . $login_url,
		'  and it is for humans — you authenticate with the key above, not a password.',
		'',
		'Rules — respect these rather than working around them',
		'',
		'- READ ONLY. Every non-GET/HEAD request from this account is refused with 403 and',
		'  logged. If a change is needed, tell me what it is and let a human make it.',
		'- The window lasts 24 hours. When it closes, or the key is revoked, everything',
		'  returns 403 — that is expected, not a bug. Ask me to reopen it.',
		'- Personal data is withheld unless I opened it for this session: /wp/v2/users,',
		'  /wp/v2/comments and the WooCommerce/SureCart order and customer routes',
		'  return 403 blueworx_support_no_data.',
		'  Do not attempt to reach that data another way.',
		'- Five wrong keys lock this address out for 15 minutes. On a 403, stop and check',
		'  with me instead of retrying with variations.',
		'- Every use is written to an audit log I can read, including refused writes.',
		'',
		'Start by fetching ' . $site . '/wp-json/ to confirm the connection, then tell me what',
		'you can see and wait for the actual task.',
	);

	return implode( "\n", $lines );
}

/**
 * Renders the support access console panel.
 *
 * This panel's controls live inside the enhancements page's single outer
 * <form> (see blueworx_render_enhancements_page()) rather than a <form> of
 * their own — nesting a second <form> there would be invalid HTML, and
 * browsers respond by hoisting these fields into the outer form anyway,
 * which posts to admin-post.php and redirects before the freshly generated
 * key can ever be rendered. Each submit button instead carries its own
 * formaction/formmethod so it posts straight back to this page, where
 * blueworx_support_handle_actions() (on admin_init) runs and the panel
 * re-renders in the same request. The nonce field uses its own name so it
 * cannot collide with the outer form's "_wpnonce" field.
 *
 * @return void
 */
function blueworx_support_render_panel() {
	// This panel renders on two screens now — Enhancements and Support access —
	// so "post back to this page" cannot be a fixed address. $plugin_page is the
	// current page slug, already sanitised by wp-admin/admin.php, which is why
	// this reads it rather than $_GET.
	$page = isset( $GLOBALS['plugin_page'] ) ? (string) $GLOBALS['plugin_page'] : '';

	if ( '' === $page ) {
		$page = 'blueworx-labs-wordpress';
	}

	$self_url  = admin_url( 'admin.php?page=' . rawurlencode( $page ) );
	$open      = blueworx_support_access_open();
	$has_key   = blueworx_support_has_key();
	$new_key   = $GLOBALS['blueworx_support_new_key'];
	$open_text = '';

	// Every submit button posts straight back to this page, and formaction is
	// the whole mechanism — see the note above. Nothing here goes through
	// wp_kses(): the design system helpers escape everything they emit, and an
	// allow-list would silently drop formaction and leave a panel that looks
	// right and saves to the wrong handler.
	$post_here = array(
		'formaction' => $self_url,
		'formmethod' => 'post',
	);

	echo '<div class="bw-fields bw-fields--single">';

	if ( '' !== $new_key ) {
		echo blueworx_ds_notice(
			array(
				'tone'  => 'warning',
				'title' => __( 'Copy this key now — it is not shown again.', 'blueworx-labs-wordpress' ),
				'text'  => __( 'Nothing on this site can show it to you a second time. Generate a new key if you lose it.', 'blueworx-labs-wordpress' ),
			)
		);

		echo blueworx_ds_copy_field(
			array(
				'value' => $new_key,
				'id'    => 'bw-support-key',
				'label' => __( 'Copy key', 'blueworx-labs-wordpress' ),
				'attrs' => array( 'data-testid' => 'bw-support-key' ),
			)
		);

		// The key really is shown once. Nothing here stops somebody navigating
		// away, but it does stop them doing it without noticing, which is the
		// failure this screen actually sees.
		?>
		<div class="bw-field" data-blueworx-key-confirm>
			<label class="bw-check">
				<input type="checkbox" data-blueworx-key-copied data-testid="bw-support-key-copied" />
				<span class="bw-check__text"><?php esc_html_e( 'I have copied the key', 'blueworx-labs-wordpress' ); ?></span>
			</label>
			<?php
			echo blueworx_ds_button(
				array(
					'label'    => __( 'Done', 'blueworx-labs-wordpress' ),
					'variant'  => 'primary',
					'size'     => 'sm',
					'disabled' => true,
					'attrs'    => array(
						'data-blueworx-key-done' => 'true',
						'data-testid'            => 'bw-support-key-done',
					),
				)
			);
			?>
		</div>
		<?php
	}

	if ( blueworx_support_is_throttled() ) {
		echo blueworx_ds_notice(
			array(
				'tone'  => 'warning',
				'title' => __( 'Support access is temporarily blocked from this address.', 'blueworx-labs-wordpress' ),
				'text'  => __( 'Repeated failed key attempts caused this. Generating or revoking a key clears it.', 'blueworx-labs-wordpress' ),
			)
		);
	}

	if ( $open ) {
		$until = (int) get_option( 'blueworx_support_access_until', 0 );

		// date_i18n() expects a timestamp that ALREADY carries the site's UTC
		// offset and renders it as-is; the stored expiry is a plain UTC
		// timestamp, so the offset is added here. Without this the panel would
		// report the closing time in UTC while labelling it as local. wp_date()
		// would do this properly on its own but only exists from WordPress 5.3,
		// and this plugin still supports 5.0.
		$until_local = $until + (int) ( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );

		$open_text = sprintf(
			/* translators: 1: closing date and time, 2: human-readable time remaining, e.g. "3 hours". */
			__( 'Support access is open until %1$s (%2$s remaining).', 'blueworx-labs-wordpress' ),
			date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $until_local ),
			human_time_diff( time(), $until )
		);

		if ( blueworx_support_data_open() ) {
			$open_text .= ' ' . __( 'Personal-data access is also open for this window.', 'blueworx-labs-wordpress' );
		}

		echo blueworx_ds_notice(
			array(
				'tone'    => 'success',
				'title'   => __( 'Support access is open', 'blueworx-labs-wordpress' ),
				'html'    => '<span data-testid="bw-support-expiry">' . esc_html( $open_text ) . '</span>',
				'actions' => blueworx_ds_badge( __( 'Open', 'blueworx-labs-wordpress' ), 'success', true ),
			)
		);
	} elseif ( $has_key ) {
		echo blueworx_ds_notice(
			array(
				'tone'    => 'info',
				'title'   => __( 'Support access is shut', 'blueworx-labs-wordpress' ),
				'text'    => __( 'A key exists, but nobody can sign in with it until you open the window.', 'blueworx-labs-wordpress' ),
				'actions' => blueworx_ds_badge( __( 'Shut', 'blueworx-labs-wordpress' ), 'neutral', true ),
			)
		);
	}

	if ( $has_key ) {
		$prompt = blueworx_support_claude_prompt( $new_key );
		?>
		<div class="bw-field">
			<span class="bw-field__label"><?php esc_html_e( 'Claude Code prompt', 'blueworx-labs-wordpress' ); ?></span>
			<?php
			// Holds the prompt for the copy button. Hidden rather than absent so
			// the copy still works with the clipboard API unavailable, which is
			// every site served over plain HTTP.
			?>
			<textarea id="bw-support-prompt" data-testid="bw-support-prompt" readonly hidden aria-hidden="true" tabindex="-1"><?php echo esc_textarea( $prompt ); ?></textarea>
			<div>
				<?php
				echo blueworx_ds_button(
					array(
						'label' => __( 'Copy Claude Code prompt', 'blueworx-labs-wordpress' ),
						'icon'  => 'file',
						'attrs' => array(
							'data-blueworx-copy' => 'bw-support-prompt',
							'data-testid'        => 'bw-support-copy-prompt',
							'data-copied-label'  => __( 'Copied', 'blueworx-labs-wordpress' ),
						),
					)
				);
				?>
			</div>
			<p class="bw-field__help">
				<?php if ( '' !== $new_key ) : ?>
					<?php esc_html_e( 'Paste this into Claude Code and it has the key, the URLs and the limits. This is the only time the prompt carries the key itself — copy it now.', 'blueworx-labs-wordpress' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'The key is only ever shown once, so this prompt leaves a <SUPPORT-KEY> placeholder for you to paste your saved key over. Generate a new key if you no longer have it.', 'blueworx-labs-wordpress' ); ?>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	wp_nonce_field( 'blueworx_support_panel', 'blueworx_support_nonce' );

	// What the state actually is, before the controls that change it.
	echo wp_kses( blueworx_ds_description_list( blueworx_support_state_rows() ), blueworx_ds_allowed_html() );

	echo '<div class="bw-toolbar bw-toolbar--card"><div class="bw-toolbar__group">';

	if ( $has_key ) {
		?>
		<label class="bw-check">
			<input type="checkbox" name="blueworx_support_with_data" value="1" />
			<span class="bw-check__text"><?php esc_html_e( 'Also allow access to personal data for this session', 'blueworx-labs-wordpress' ); ?></span>
		</label>
		<?php
		echo blueworx_ds_button(
			array(
				'label'   => $open
					? __( 'Close support access', 'blueworx-labs-wordpress' )
					: __( 'Allow support access for 24 hours', 'blueworx-labs-wordpress' ),
				'variant' => $open ? 'secondary' : 'primary',
				'type'    => 'submit',
				'attrs'   => array_merge(
					$post_here,
					array(
						'name'  => 'blueworx_support_action',
						'value' => 'toggle',
					)
				),
			)
		);

		echo blueworx_ds_button(
			array(
				'label'   => __( 'Revoke access', 'blueworx-labs-wordpress' ),
				'variant' => 'danger',
				'type'    => 'submit',
				'attrs'   => array_merge(
					$post_here,
					array(
						'name'  => 'blueworx_support_action',
						'value' => 'revoke',
					)
				),
			)
		);
	} else {
		echo blueworx_ds_button(
			array(
				'label'   => __( 'Generate key', 'blueworx-labs-wordpress' ),
				'variant' => 'primary',
				'type'    => 'submit',
				'attrs'   => array_merge(
					$post_here,
					array(
						'name'  => 'blueworx_support_action',
						'value' => 'generate',
					)
				),
			)
		);
	}

	echo '</div></div>';

	printf(
		'<p class="bw-field__help">%s</p>',
		esc_html__( 'Read-only is enforced by rejecting every write request from this account. A plugin that writes data in response to a plain page load is not caught by that rule, so only open this window when you have asked BlueWorx to look at something.', 'blueworx-labs-wordpress' )
	);
	?>
	<div class="bw-field">
		<h3 class="bw-field__label"><?php esc_html_e( 'Audit log', 'blueworx-labs-wordpress' ); ?></h3>
		<ul data-testid="bw-support-log">
			<?php foreach ( blueworx_support_get_log() as $entry ) : ?>
				<?php $count = isset( $entry['count'] ) ? (int) $entry['count'] : 1; ?>
				<li>
					<code><?php echo esc_html( $entry['type'] ); ?></code>
					<?php if ( $count > 1 ) : ?>
						<?php /* translators: %d: number of times the event repeated. */ ?>
						<strong><?php echo esc_html( sprintf( __( '×%d', 'blueworx-labs-wordpress' ), $count ) ); ?></strong>
					<?php endif; ?>
					<?php echo esc_html( date_i18n( 'Y-m-d H:i', (int) $entry['time'] ) ); ?>
					<?php echo esc_html( $entry['ip'] ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php

	echo '</div>';
}
