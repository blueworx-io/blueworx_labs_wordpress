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
 * Capabilities removed from the administrator clone.
 *
 * These are the operations that are destructive or that grant onward access;
 * everything else is retained so admin screens still render, because WordPress
 * gates screen rendering on the same capabilities it gates writes on. The
 * read-only guarantee comes from the request-layer block, not from this list.
 *
 * unfiltered_html is included alongside the file/plugin/theme editing
 * capabilities because it is onward access by another route: raw script
 * saved into post or page content executes later, in a real administrator's
 * browser, when they view it.
 *
 * @return array Capability names.
 */
function blueworx_support_removed_caps() {
	return array(
		'edit_files',
		'edit_plugins',
		'edit_themes',
		'unfiltered_html',
		'install_plugins',
		'install_themes',
		'update_plugins',
		'update_themes',
		'update_core',
		'delete_plugins',
		'delete_themes',
		'export',
		'import',
		'create_users',
		'edit_users',
		'delete_users',
		'promote_users',
		'remove_users',
	);
}

/**
 * Builds the support role's capability map from the live administrator role.
 *
 * @return array Capability map (cap => true).
 */
function blueworx_support_build_caps() {
	$base = get_role( 'administrator' );
	$caps = ( $base && is_array( $base->capabilities ) ) ? $base->capabilities : array();

	foreach ( blueworx_support_removed_caps() as $cap ) {
		unset( $caps[ $cap ] );
	}

	$caps['read'] = true;

	return $caps;
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
		__( 'BlueWorx Support (read-only)', 'blueworx-labs-wordpress' ),
		blueworx_support_build_caps()
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
 * uninstall.php already handles a full removal, but deactivation left the
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
		&& in_array( blueworx_support_role_slug(), (array) $user->roles, true );
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
 * Records an audit event, keeping the most recent 100.
 *
 * The log is what makes the access window verifiable rather than merely
 * claimed, so it records refusals as well as successes.
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

	$log[] = array(
		'type' => sanitize_key( $type ),
		'time' => time(),
		'ip'   => $ip,
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
 * Admin screens denied to the support account without data access.
 *
 * @return array $pagenow values.
 */
function blueworx_support_denied_screens() {
	/**
	 * Filters the screens hidden from support access.
	 *
	 * Lets a site add the data screens of a plugin this list does not know about.
	 *
	 * @param array $screens $pagenow values.
	 */
	return (array) apply_filters(
		'blueworx_support_denied_screens',
		array( 'users.php', 'user-edit.php', 'edit-comments.php', 'export.php' )
	);
}

/**
 * Whether WooCommerce is present on this site.
 *
 * @return bool True when WooCommerce is loaded.
 */
function blueworx_support_woocommerce_active() {
	return class_exists( 'WooCommerce' ) || defined( 'WC_PLUGIN_FILE' );
}

/**
 * Whether SureCart is present on this site.
 *
 * @return bool True when SureCart is loaded.
 */
function blueworx_support_surecart_active() {
	return class_exists( 'SureCart' ) || defined( 'SURECART_PLUGIN_FILE' );
}

/**
 * Admin page slugs (admin.php?page=…) denied without data access.
 *
 * The support role is a clone of the LIVE administrator role, so it inherits
 * manage_woocommerce and its equivalents from whatever commerce plugin the
 * client runs. Those screens are not $pagenow values — they hang off
 * admin.php?page= — so they need matching of their own, or orders and
 * customers stay fully readable with data access OFF (spec §1.5).
 *
 * Only screens of a plugin that is actually present are listed, so the console
 * never refuses a page the site does not have.
 *
 * @return array Page slugs.
 */
function blueworx_support_denied_admin_pages() {
	$pages = array();

	if ( blueworx_support_woocommerce_active() ) {
		// HPOS order tables. Order-type variants are addressed as
		// wc-orders--shop_subscription, so this list is prefix-matched.
		$pages[] = 'wc-orders';
	}

	if ( blueworx_support_surecart_active() ) {
		$pages[] = 'sc-orders';
		$pages[] = 'sc-customers';
		$pages[] = 'sc-subscriptions';
	}

	/**
	 * Filters the admin.php page slugs hidden from support access.
	 *
	 * Matched as prefixes, so "wc-orders" also covers "wc-orders--<type>".
	 *
	 * @param array $pages Page slugs.
	 */
	return (array) apply_filters( 'blueworx_support_denied_admin_pages', $pages );
}

/**
 * Post types (edit.php?post_type=…) denied without data access.
 *
 * Covers the legacy, pre-HPOS WooCommerce order table, which is a normal post
 * list screen rather than a page slug.
 *
 * @return array Post type keys.
 */
function blueworx_support_denied_post_types() {
	$types = array();

	if ( blueworx_support_woocommerce_active() ) {
		$types[] = 'shop_order';
		$types[] = 'shop_subscription';
	}

	/**
	 * Filters the post types hidden from support access.
	 *
	 * @param array $types Post type keys.
	 */
	return (array) apply_filters( 'blueworx_support_denied_post_types', $types );
}

/**
 * REST route prefixes denied to the support account without data access.
 *
 * @return array Route prefixes.
 */
function blueworx_support_denied_routes() {
	$routes = array( '/wp/v2/users', '/wp/v2/comments', '/blueworx/v1/account', '/blueworx/v1/surecart' );

	// The same commerce data, reachable over REST rather than through a screen.
	if ( blueworx_support_woocommerce_active() ) {
		$routes[] = '/wc/v3/orders';
		$routes[] = '/wc/v3/customers';
		$routes[] = '/wc-analytics/orders';
		$routes[] = '/wc-analytics/customers';
	}

	if ( blueworx_support_surecart_active() ) {
		$routes[] = '/surecart/v1/orders';
		$routes[] = '/surecart/v1/customers';
	}

	/**
	 * Filters the REST routes hidden from support access.
	 *
	 * @param array $routes Route prefixes.
	 */
	return (array) apply_filters( 'blueworx_support_denied_routes', $routes );
}

/**
 * Whether the screen being requested holds personal data.
 *
 * Three shapes of screen are matched, because the data screens this feature
 * has to withhold are not all addressed the same way: plain $pagenow values
 * (users.php), page slugs behind admin.php (WooCommerce and SureCart order and
 * customer tables), and post-type list tables behind edit.php (the legacy
 * WooCommerce order table).
 *
 * @return bool True when the screen must be refused.
 */
function blueworx_support_screen_is_denied() {
	global $pagenow;

	$screen = (string) $pagenow;

	if ( in_array( $screen, blueworx_support_denied_screens(), true ) ) {
		// The account's own profile is reachable; other users' are not.
		if ( 'user-edit.php' === $screen ) {
			$target = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			return $target !== get_current_user_id();
		}

		return true;
	}

	if ( 'admin.php' === $screen ) {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		foreach ( blueworx_support_denied_admin_pages() as $prefix ) {
			if ( '' !== $page && 0 === strpos( $page, (string) $prefix ) ) {
				return true;
			}
		}

		// WooCommerce Analytics is one SPA behind a single page slug, so the
		// customers report is addressed by its path rather than its own slug.
		// Only that path is refused; the rest of the app stays diagnosable.
		if ( 'wc-admin' === $page ) {
			$path = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( 0 === strpos( $path, '/customers' ) ) {
				return true;
			}
		}

		return false;
	}

	if ( 'edit.php' === $screen ) {
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return '' !== $post_type && in_array( $post_type, blueworx_support_denied_post_types(), true );
	}

	return false;
}

/**
 * Denies personal-data admin screens unless data access is open.
 *
 * A 403 rather than a redirect, so the refusal is unambiguous.
 *
 * @return void
 */
function blueworx_support_gate_data_screens() {
	if ( ! blueworx_support_is_support_user() || blueworx_support_data_open() ) {
		return;
	}

	if ( ! blueworx_support_screen_is_denied() ) {
		return;
	}

	wp_die(
		esc_html__( 'This screen holds personal data and is not available to BlueWorx support access.', 'blueworx-labs-wordpress' ),
		esc_html__( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
		array( 'response' => 403 )
	);
}
// Priority 0: some features (e.g. includes/disable-comments.php) install their
// own admin_init handler that redirects or exits before reaching this file's
// default-priority position, which would let a denied screen escape as a
// redirect-to-200 rather than the 403 required here. Running first means this
// denial always wins over another feature's own admin_init handling of the
// same screen.
add_action( 'admin_init', 'blueworx_support_gate_data_screens', 0 );

/**
 * Admin screens whose "action" parameter activates or deactivates code.
 *
 * @return array $pagenow values.
 */
function blueworx_support_activation_screens() {
	return array( 'plugins.php', 'themes.php' );
}

/**
 * Denies plugin and theme activation actions to the support account.
 *
 * activate_plugins and switch_themes are deliberately retained on the support
 * role: activate_plugins is what gates VIEWING plugins.php, and reading the
 * plugin list is one of the primary diagnostics this feature exists to enable.
 * But WordPress activates or deactivates a SINGLE plugin through a nonce'd GET
 * link (plugins.php?action=activate&plugin=…&_wpnonce=…) — only the bulk
 * actions are POSTed — and themes.php?action=activate&stylesheet=… behaves the
 * same way. The account can render plugins.php, harvest its own valid nonces
 * from that page and follow the link, so the read-only write block (which only
 * refuses non-GET methods) never sees it.
 *
 * The action is therefore blocked here while the read is left intact: any
 * request to one of these screens carrying an action parameter is refused.
 * Both "action" and "action2" are checked because WordPress submits the bottom
 * bulk-action selector under the second name, and '-1' — WordPress's "no action
 * selected" value — is treated as no action at all.
 *
 * @return void
 */
function blueworx_support_gate_activation_actions() {
	global $pagenow;

	if ( ! blueworx_support_is_support_user() ) {
		return;
	}

	if ( ! in_array( (string) $pagenow, blueworx_support_activation_screens(), true ) ) {
		return;
	}

	foreach ( array( 'action', 'action2' ) as $param ) {
		$value = isset( $_GET[ $param ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		if ( '' === $value || '-1' === $value ) {
			continue;
		}

		blueworx_support_log_event( 'blocked_write' );

		wp_die(
			esc_html__( 'BlueWorx support access is read-only: plugins and themes cannot be activated or deactivated.', 'blueworx-labs-wordpress' ),
			esc_html__( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
			array( 'response' => 403 )
		);
	}
}
// Priority 0, for the same reason as blueworx_support_gate_data_screens(): this
// denial must win over any other feature's own admin_init handling of the same
// screen, so it cannot escape as a redirect-to-200.
add_action( 'admin_init', 'blueworx_support_gate_activation_actions', 0 );

/**
 * Denies personal-data REST routes unless data access is open.
 *
 * @param mixed           $result  Pre-dispatch result.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Current request.
 * @return mixed Untouched result, or a WP_Error.
 */
function blueworx_support_gate_data_routes( $result, $server, $request ) {
	unset( $server );

	if ( ! blueworx_support_is_support_user() || blueworx_support_data_open() ) {
		return $result;
	}

	$route = (string) $request->get_route();

	foreach ( blueworx_support_denied_routes() as $prefix ) {
		if ( 0 === strpos( $route, $prefix ) ) {
			return new WP_Error(
				'blueworx_support_no_data',
				__( 'This route returns personal data and is not available to BlueWorx support access.', 'blueworx-labs-wordpress' ),
				array( 'status' => 403 )
			);
		}
	}

	return $result;
}
add_filter( 'rest_pre_dispatch', 'blueworx_support_gate_data_routes', 11, 3 );

/**
 * Rejects every non-read request made by the support account.
 *
 * This — not the capability set — is what makes the account read-only.
 * Third-party plugins routinely write through their own AJAX and REST endpoints
 * without checking a meaningful capability, so a rule that depends on plugin
 * authors behaving correctly is not a safety model. A method-level block does
 * not.
 *
 * Known gap, documented in the console: a plugin that writes in response to a
 * GET request is not caught here.
 *
 * @return void
 */
function blueworx_support_block_writes() {
	if ( ! blueworx_support_is_support_user() ) {
		return;
	}

	$method = isset( $_SERVER['REQUEST_METHOD'] )
		? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
		: 'GET';

	if ( in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		return;
	}

	blueworx_support_log_event( 'blocked_write' );

	wp_die(
		esc_html__( 'BlueWorx support access is read-only.', 'blueworx-labs-wordpress' ),
		esc_html__( 'BlueWorx Support', 'blueworx-labs-wordpress' ),
		array( 'response' => 403 )
	);
}
add_action( 'init', 'blueworx_support_block_writes', 0 );

/**
 * Second net: refuses non-read REST requests from the support account.
 *
 * @param mixed           $result  Pre-dispatch result.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Current request.
 * @return mixed Untouched result, or a WP_Error for a write.
 */
function blueworx_support_block_rest_writes( $result, $server, $request ) {
	unset( $server );

	if ( ! blueworx_support_is_support_user() ) {
		return $result;
	}

	if ( in_array( strtoupper( $request->get_method() ), array( 'GET', 'HEAD' ), true ) ) {
		return $result;
	}

	blueworx_support_log_event( 'blocked_write' );

	return new WP_Error(
		'blueworx_support_read_only',
		__( 'BlueWorx support access is read-only.', 'blueworx-labs-wordpress' ),
		array( 'status' => 403 )
	);
}
add_filter( 'rest_pre_dispatch', 'blueworx_support_block_rest_writes', 10, 3 );

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
// blueworx_support_block_writes() (priority 0), so a session already found
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
 * Runs on determine_current_user at priority 21, after the headless JWT
 * resolver (includes/rest/bootstrap.php, registered at priority 20), and
 * follows the same rule: any failure leaves $user_id untouched, so public
 * routes and cookie or JWT authentication keep working.
 *
 * Priority 21 rather than 20: this file is required before
 * includes/rest/bootstrap.php in blueworx-labs-wordpress.php, so registering
 * at the same priority would make this resolver run FIRST, by include order
 * — a property no one reading either file in isolation should have to know
 * about, and the opposite of the ordering intended here. Running after the
 * JWT resolver is also the safer choice on its own merits: a JWT explicitly
 * issued to a real, identified user should take precedence over a support
 * key, not be pre-empted by it.
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
	$self_url = admin_url( 'admin.php?page=blueworx-labs-wordpress' );
	?>
	<?php if ( '' !== $GLOBALS['blueworx_support_new_key'] ) : ?>
		<p><strong><?php esc_html_e( 'Copy this key now — it is not shown again.', 'blueworx-labs-wordpress' ); ?></strong></p>
		<code data-testid="bw-support-key"><?php echo esc_html( $GLOBALS['blueworx_support_new_key'] ); ?></code>
	<?php endif; ?>

	<?php if ( blueworx_support_is_throttled() ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				esc_html_e(
					'Repeated failed key attempts have temporarily blocked support access from this address. Generating or revoking a key clears this.',
					'blueworx-labs-wordpress'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( blueworx_support_access_open() ) : ?>
		<?php
		$blueworx_support_until = (int) get_option( 'blueworx_support_access_until', 0 );

		// date_i18n() expects a timestamp that ALREADY carries the site's UTC
		// offset and renders it as-is; the stored expiry is a plain UTC
		// timestamp, so the offset is added here. Without this the panel would
		// report the closing time in UTC while labelling it as local. wp_date()
		// would do this properly on its own but only exists from WordPress 5.3,
		// and this plugin still supports 5.0.
		$blueworx_support_until_local = $blueworx_support_until + (int) ( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );
		?>
		<p data-testid="bw-support-expiry">
			<strong>
				<?php
				printf(
					/* translators: 1: closing date and time, 2: human-readable time remaining, e.g. "3 hours". */
					esc_html__( 'Support access is open until %1$s (%2$s remaining).', 'blueworx-labs-wordpress' ),
					esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $blueworx_support_until_local ) ),
					esc_html( human_time_diff( time(), $blueworx_support_until ) )
				);
				?>
			</strong>
			<?php if ( blueworx_support_data_open() ) : ?>
				<br /><?php esc_html_e( 'Personal-data access is also open for this window.', 'blueworx-labs-wordpress' ); ?>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<?php wp_nonce_field( 'blueworx_support_panel', 'blueworx_support_nonce' ); ?>
	<?php if ( blueworx_support_has_key() ) : ?>
		<label>
			<input type="checkbox" name="blueworx_support_with_data" value="1" />
			<?php esc_html_e( 'Also allow access to personal data for this session', 'blueworx-labs-wordpress' ); ?>
		</label>
		<button type="submit" name="blueworx_support_action" value="toggle" class="button" formaction="<?php echo esc_url( $self_url ); ?>" formmethod="post">
			<?php
			echo blueworx_support_access_open()
				? esc_html__( 'Close support access', 'blueworx-labs-wordpress' )
				: esc_html__( 'Allow support access for 24 hours', 'blueworx-labs-wordpress' );
			?>
		</button>
		<button type="submit" name="blueworx_support_action" value="revoke" class="button" formaction="<?php echo esc_url( $self_url ); ?>" formmethod="post">
			<?php esc_html_e( 'Revoke key', 'blueworx-labs-wordpress' ); ?>
		</button>
	<?php else : ?>
		<button type="submit" name="blueworx_support_action" value="generate" class="button button-primary" formaction="<?php echo esc_url( $self_url ); ?>" formmethod="post">
			<?php esc_html_e( 'Generate key', 'blueworx-labs-wordpress' ); ?>
		</button>
	<?php endif; ?>

	<p class="description">
		<?php esc_html_e( 'Read-only is enforced by rejecting every write request from this account. A plugin that writes data in response to a plain page load is not caught by that rule, so only open this window when you have asked BlueWorx to look at something.', 'blueworx-labs-wordpress' ); ?>
	</p>

	<h3><?php esc_html_e( 'Audit log', 'blueworx-labs-wordpress' ); ?></h3>
	<ul data-testid="bw-support-log">
		<?php foreach ( blueworx_support_get_log() as $entry ) : ?>
			<li>
				<code><?php echo esc_html( $entry['type'] ); ?></code>
				<?php echo esc_html( date_i18n( 'Y-m-d H:i', (int) $entry['time'] ) ); ?>
				<?php echo esc_html( $entry['ip'] ); ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}
