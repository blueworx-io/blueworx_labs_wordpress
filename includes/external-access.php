<?php
/**
 * BlueWorx: External — a read-only viewer account for people you invite.
 *
 * The point is showing somebody round the backend of a site without handing
 * them the ability to change it: a client evaluating the work, a contractor
 * being briefed, a colleague who needs to see rather than do.
 *
 * The role is a clone of the LIVE administrator role minus the capabilities
 * that are destructive or that grant onward access, and — as with support
 * access — the read-only guarantee is not that list. It is the request-layer
 * block in includes/readonly-access.php, which this role opts into by being
 * named in blueworx_readonly_roles().
 *
 * One account per invited person, never a shared login: a shared one cannot be
 * traced to anybody and cannot be withdrawn from one person without withdrawing
 * it from everyone.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User meta: who issued the invitation.
 */
const BLUEWORX_EXTERNAL_META_INVITED_BY = '_blueworx_external_invited_by';

/**
 * User meta: when the invitation was issued.
 */
const BLUEWORX_EXTERNAL_META_INVITED_AT = '_blueworx_external_invited_at';

/**
 * User meta: when access ends.
 */
const BLUEWORX_EXTERNAL_META_EXPIRES_AT = '_blueworx_external_expires_at';

/**
 * User meta: a free-text note about who this person is.
 */
const BLUEWORX_EXTERNAL_META_NOTE = '_blueworx_external_note';

/**
 * User meta: when they last signed in.
 */
const BLUEWORX_EXTERNAL_META_LAST_SEEN = '_blueworx_external_last_seen';

/**
 * Gets the external role slug.
 *
 * @return string Role slug.
 */
function blueworx_external_role_slug() {
	return 'blueworx_external';
}

/**
 * Gets the external role's displayed name.
 *
 * "BlueWorx: External" follows the convention in includes/display-names.php,
 * where every role says where it belongs before it says what it is — Site:
 * Editor, Commerce: Manager. Registering it under that name means it needs no
 * entry in the relabelling map.
 *
 * @return string Role name.
 */
function blueworx_external_role_name() {
	return 'BlueWorx: External';
}

/**
 * Registers the role, rebuilding its capabilities from the live administrator.
 *
 * Rebuilt rather than created-once because the role it clones changes: a
 * commerce or booking plugin installed next month adds its capabilities to
 * administrator, and a role frozen at first registration would quietly stop
 * showing the screens this feature exists to show.
 *
 * Called only at the moments the role can actually need writing — the plugin
 * activating, the function being switched on, and a site upgrading into a
 * version that has it on already. It is NOT called on admin_init, the way an
 * earlier version did: remove_role() and add_role() are two writes to the
 * autoloaded wp_user_roles option, so that put two database writes on every
 * wp-admin page load of every user — including the read-only viewers, who
 * would have been writing to the database on a GET. Worse, between the two
 * calls the role does not exist at all, so a request arriving in that window
 * resolved an external user with no capabilities and no role for the read-only
 * guard to recognise.
 *
 * includes/support-access.php sets the precedent: it writes its role only when
 * a key is generated.
 *
 * @return void
 */
function blueworx_external_register_role() {
	remove_role( blueworx_external_role_slug() );
	add_role(
		blueworx_external_role_slug(),
		blueworx_external_role_name(),
		blueworx_readonly_build_caps()
	);
}

/**
 * Makes sure the role exists, without rewriting one that already does.
 *
 * The cheap call for anywhere that only needs the role to be there — plugin
 * activation, an upgrade — as against blueworx_external_register_role(), which
 * deliberately rebuilds the capability map and is reserved for the moments
 * somebody actually switched the function on.
 *
 * @return void
 */
function blueworx_external_ensure_role() {
	if ( get_role( blueworx_external_role_slug() ) ) {
		return;
	}

	blueworx_external_register_role();
}

/**
 * Puts the role in place when the plugin is activated.
 *
 * Only for a site that already has the function switched on, which means a
 * reactivation: a fresh install has it off, and switching it on registers the
 * role at that point.
 *
 * Registered on register_activation_hook in blueworx-labs-wordpress.php.
 *
 * @return void
 */
function blueworx_external_on_activate() {
	if ( ! blueworx_feature_enabled( 'external_access' ) ) {
		return;
	}

	blueworx_external_ensure_role();
}

/**
 * Removes the role, unless somebody still holds it.
 *
 * The same rule the plugin's earlier role sweeps use: a role with a user
 * assigned is left standing and recorded, because deleting it would strip that
 * account of every capability while leaving it able to sign in — a broken
 * account is worse than a tidy database.
 *
 * @return void
 */
function blueworx_external_remove_role() {
	$holders = get_users(
		array(
			'role'   => blueworx_external_role_slug(),
			'number' => 1,
			'fields' => 'ID',
		)
	);

	if ( ! empty( $holders ) ) {
		$skipped = (array) get_option( 'blueworx_orphaned_roles_skipped', array() );

		if ( ! in_array( blueworx_external_role_slug(), $skipped, true ) ) {
			$skipped[] = blueworx_external_role_slug();
			update_option( 'blueworx_orphaned_roles_skipped', $skipped );
		}

		return;
	}

	remove_role( blueworx_external_role_slug() );
}

/**
 * Whether a user is an external viewer.
 *
 * @param mixed $user User to test.
 * @return bool True when they hold the external role.
 */
function blueworx_external_is_external_user( $user ) {
	return blueworx_readonly_user_has_role( $user, blueworx_external_role_slug() );
}

/**
 * The invitation lengths on offer, in days.
 *
 * @return array Whole days.
 */
function blueworx_external_durations() {
	return array( 3, 7, 14 );
}

/**
 * The length used when nobody chooses one.
 *
 * @return int Whole days.
 */
function blueworx_external_default_duration() {
	return 7;
}

/**
 * Reduces a submitted duration to one that was actually offered.
 *
 * An allow-list rather than a range check: the form offers three values, and
 * anything else arriving is either a stale form or a hand-crafted POST. Falling
 * back to the default is safe in both cases, where honouring the number is not.
 *
 * @param mixed $days Submitted duration.
 * @return int Whole days.
 */
function blueworx_external_sanitize_duration( $days ) {
	$days = (int) $days;

	return in_array( $days, blueworx_external_durations(), true )
		? $days
		: blueworx_external_default_duration();
}

/**
 * Works out when access ends.
 *
 * Counted from the moment of invitation rather than from midnight, so "30 days"
 * is thirty days and does not silently become twenty-nine.
 *
 * @param int $now  Starting timestamp.
 * @param int $days Whole days.
 * @return int Expiry timestamp.
 */
function blueworx_external_expiry_from( $now, $days ) {
	return (int) $now + ( (int) $days * DAY_IN_SECONDS );
}

/**
 * Derives a username from an email address.
 *
 * The local part, stripped to what WordPress accepts. Uniquifying it against
 * accounts that already exist happens at invitation time, where the database
 * is available; this half is pure so it can be reasoned about on its own.
 *
 * @param string $email Email address.
 * @return string Username stem.
 */
function blueworx_external_username_from_email( $email ) {
	$local = (string) strstr( (string) $email, '@', true );

	if ( '' === $local ) {
		$local = (string) $email;
	}

	$name = sanitize_user( $local, true );

	// sanitize_user() in strict mode keeps '@' — it is a valid character in a
	// username, just not one this function wants to hand back. It only ever
	// survives here when $local fell back to the whole address because
	// nothing preceded the first '@' (an address with no local part at all),
	// so stripping it is safe rather than lossy.
	$name = str_replace( '@', '', $name );

	return '' === $name ? 'external' : $name;
}

/**
 * Every account currently holding the external role.
 *
 * The account IS the invitation record — there is no separate table for it.
 * That is a deliberate choice, not an omission: deleting the user withdraws
 * the invitation completely, in one action, through the Users screen anybody
 * already knows how to use. A separate invitations table would let the two
 * fall out of sync — a row surviving a deleted user, or a user surviving a
 * deleted row — and nothing on the Users screen would show it.
 *
 * @return array WP_User objects, most recently invited first.
 */
function blueworx_external_invitations() {
	return (array) get_users(
		array(
			'role'    => blueworx_external_role_slug(),
			'orderby' => 'registered',
			'order'   => 'DESC',
		)
	);
}

/**
 * Withdraws external access when the plugin is switched off.
 *
 * Every invited account is deleted, not merely expired. Expiry is enforced by
 * this plugin; with it switched off, an account left standing is a working
 * administrator-shaped login that nothing is narrowing.
 *
 * @return void
 */
function blueworx_external_on_deactivate() {
	if ( ! function_exists( 'wp_delete_user' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}

	foreach ( blueworx_external_invitations() as $user ) {
		wp_delete_user( $user->ID );
	}

	blueworx_external_remove_role();
}

if ( blueworx_feature_enabled( 'external_access' ) ) {
	// The role is NOT registered from a hook here. See
	// blueworx_external_register_role() for why an admin_init rebuild was a
	// database write on every page load and a window in which the role did not
	// exist; the three places it is written now are plugin activation, the
	// function being switched on, and the upgrade migration.
	//
	// The handler is gated behind the feature check above: while the feature is
	// off the console cannot render (blueworx_render_external_page() returns
	// before the form does), so this closes the one path — a forged POST to any
	// admin page — that could otherwise still reach the handler.
	add_action( 'admin_init', 'blueworx_external_handle_actions' );
}

// Registered unconditionally, unlike the role above — mirrors
// blueworx_support_enforce_window(), which also registers regardless of the
// feature switch. Switching the feature off is not the same as deactivating
// the plugin: invited accounts still exist, and blueworx_external_is_expired()
// reads a disabled feature as access having ended, so these two keep refusing
// them at the door and closing anything already open rather than going quiet
// the moment the switch is off.
add_filter( 'authenticate', 'blueworx_external_block_expired_login', 30, 3 );
// Priority 2, matching blueworx_support_enforce_window(): after the write
// block at priority 0, so a session found stale here is destroyed before
// anything else inspects it.
add_action( 'init', 'blueworx_external_enforce_expiry', 2 );
// Kept alongside the two enforcement hooks above rather than behind the
// feature gate: while the feature is off nothing can actually reach this
// (the authenticate filter above already refuses the login), so leaving it
// registered is free, and it means a re-enabled feature does not need this
// file reloaded for sign-ins to start being recorded again.
add_action( 'wp_login', 'blueworx_external_record_sign_in', 10, 2 );

/**
 * Makes a username nobody already holds.
 *
 * @param string $stem Username stem.
 * @return string Free username.
 */
function blueworx_external_unique_username( $stem ) {
	$stem = '' === (string) $stem ? 'external' : (string) $stem;
	$name = $stem;
	$n    = 2;

	while ( username_exists( $name ) ) {
		$name = $stem . $n;
		++$n;
	}

	return $name;
}

/**
 * Gets when an invitation ends.
 *
 * @param int $user_id Invited account.
 * @return int Timestamp, or 0 when none is recorded.
 */
function blueworx_external_expires_at( $user_id ) {
	return (int) get_user_meta( (int) $user_id, BLUEWORX_EXTERNAL_META_EXPIRES_AT, true );
}

/**
 * Whether the invitation email blueworx_external_invite() sent actually went
 * out.
 *
 * The invite function sends the invitation itself as its last step. A
 * caller that needs to know whether that send succeeded — to choose between a
 * "sent" and a "created, but the email failed" notice — reads this
 * immediately afterwards rather than calling blueworx_external_send_invite()
 * a second time: that function calls get_password_reset_key(), which
 * overwrites the reset key on the account, so a second call invalidates the
 * link the first email already carries. Mirrors
 * $GLOBALS['blueworx_support_new_key'] in support-access.php: a value a
 * function produces as a side effect, alongside its real return value.
 *
 * @var bool
 */
$GLOBALS['blueworx_external_last_invite_mailed'] = false;

/**
 * Invites somebody.
 *
 * The password set here is random and never communicated. The invitation email
 * carries a password-reset link instead, so the person chooses their own and no
 * credential ever sits in an inbox. That is also why the account is usable
 * immediately: a reset link is not a pending state, it is the way in.
 *
 * Sends the invitation itself and records whether that succeeded in
 * $GLOBALS['blueworx_external_last_invite_mailed'] — see that global's own
 * docblock for why a caller reads it there rather than sending again.
 *
 * @param array $args name, email, note, days.
 * @return int|WP_Error New user ID, or the reason it was refused.
 */
function blueworx_external_invite( $args ) {
	$name  = sanitize_text_field( isset( $args['name'] ) ? $args['name'] : '' );
	$email = sanitize_email( isset( $args['email'] ) ? $args['email'] : '' );
	$note  = sanitize_text_field( isset( $args['note'] ) ? $args['note'] : '' );
	$days  = blueworx_external_sanitize_duration( isset( $args['days'] ) ? $args['days'] : 0 );

	if ( '' === $email || ! is_email( $email ) ) {
		return new WP_Error(
			'blueworx_external_bad_email',
			__( 'That is not an email address anything could be sent to.', 'blueworx-labs-wordpress' )
		);
	}

	if ( email_exists( $email ) ) {
		// Named rather than silent: an administrator who cannot see why nothing
		// happened will invite the same person again, and again.
		return new WP_Error(
			'blueworx_external_email_taken',
			__( 'Somebody with that email address already has an account on this site.', 'blueworx-labs-wordpress' )
		);
	}

	if ( '' === $name ) {
		$name = $email;
	}

	$user_id = wp_insert_user(
		array(
			'user_login'   => blueworx_external_unique_username( blueworx_external_username_from_email( $email ) ),
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 64, true, true ),
			'display_name' => $name,
			'role'         => blueworx_external_role_slug(),
		)
	);

	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	$user_id = (int) $user_id;
	$now     = time();

	update_user_meta( $user_id, BLUEWORX_EXTERNAL_META_INVITED_BY, get_current_user_id() );
	update_user_meta( $user_id, BLUEWORX_EXTERNAL_META_INVITED_AT, $now );
	update_user_meta( $user_id, BLUEWORX_EXTERNAL_META_EXPIRES_AT, blueworx_external_expiry_from( $now, $days ) );
	update_user_meta( $user_id, BLUEWORX_EXTERNAL_META_NOTE, $note );

	$GLOBALS['blueworx_external_last_invite_mailed'] = blueworx_external_send_invite( $user_id );

	return $user_id;
}

/**
 * Builds the link that lets an invited person set their own password.
 *
 * Nothing here handles the plugin's custom login URL, and nothing needs to:
 * blueworx_replace_generated_login_url() in includes/login-security.php filters
 * network_site_url(), keeps the query string and swaps the path for the custom
 * slug. Building the address any other way would step around that filter and
 * send people to a URL this plugin blocks.
 *
 * @param WP_User $user Invited account.
 * @return string Reset URL.
 */
function blueworx_external_reset_url( $user ) {
	$key = get_password_reset_key( $user );

	if ( is_wp_error( $key ) ) {
		return '';
	}

	return network_site_url(
		'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ),
		'login'
	);
}

/**
 * Puts a password-reset key back the way it was.
 *
 * Written straight to the users table because there is no API for it:
 * get_password_reset_key() only ever mints a new one. The stored value carries
 * its own timestamp, so restoring it restores the original expiry too — this
 * puts the account back exactly where it was, it does not extend anything.
 *
 * @param int    $user_id Account to restore.
 * @param string $key     The value that was there before.
 * @return void
 */
function blueworx_external_restore_reset_key( $user_id, $key ) {
	global $wpdb;

	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->users,
		array( 'user_activation_key' => (string) $key ),
		array( 'ID' => (int) $user_id )
	);

	clean_user_cache( (int) $user_id );
}

/**
 * Sends, or re-sends, the invitation email.
 *
 * Plain text. It says who invited them, that the access is view-only, when it
 * ends, and gives one link. It contains no password, because there is no
 * password to contain.
 *
 * Refuses to run for any account outside the external role. A resend button
 * that trusted its $user_id argument would mail a password-reset link for
 * whichever account it was pointed at — an administrator included.
 *
 * Minting the new link rotates the key, which kills the link in any email the
 * person already has. That is correct when the new message goes out, and wrong
 * when it does not: a resend that fails would otherwise leave somebody with two
 * dead links and no way back. So the previous key is kept and put back if the
 * send fails, and the account is left exactly as it was found.
 *
 * @param int $user_id Invited account.
 * @return bool True when the mail was handed off successfully.
 */
function blueworx_external_send_invite( $user_id ) {
	$user = get_userdata( (int) $user_id );

	if ( ! $user instanceof WP_User || ! blueworx_external_is_external_user( $user ) ) {
		return false;
	}

	$previous_key = isset( $user->user_activation_key ) ? (string) $user->user_activation_key : '';

	$link = blueworx_external_reset_url( $user );

	if ( '' === $link ) {
		return false;
	}

	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$host    = wp_get_current_user();
	$expires = blueworx_external_expires_at( $user_id );

	$subject = sprintf(
		/* translators: %s: site name. */
		__( 'You have been given a look round %s', 'blueworx-labs-wordpress' ),
		$site
	);

	$lines = array(
		sprintf(
			/* translators: 1: inviter's name, 2: site name. */
			__( '%1$s has given you view-only access to the back end of %2$s.', 'blueworx-labs-wordpress' ),
			$host instanceof WP_User && '' !== $host->display_name ? $host->display_name : $site,
			$site
		),
		'',
		__( 'You can look at everything an administrator sees. You cannot change anything, and nothing you click will alter the site.', 'blueworx-labs-wordpress' ),
		'',
		__( 'Choose a password to get started:', 'blueworx-labs-wordpress' ),
		$link,
		'',
		sprintf(
			/* translators: %s: date. */
			__( 'Your access ends on %s.', 'blueworx-labs-wordpress' ),
			date_i18n( get_option( 'date_format' ), $expires )
		),
		'',
		sprintf(
			/* translators: %s: username. */
			__( 'Your username is %s.', 'blueworx-labs-wordpress' ),
			$user->user_login
		),
	);

	$sent = (bool) wp_mail( $user->user_email, $subject, implode( "\n", $lines ) );

	if ( ! $sent ) {
		// Nothing went out, so nothing should have changed. Whatever link the
		// person is already holding keeps working.
		blueworx_external_restore_reset_key( $user->ID, $previous_key );
	}

	return $sent;
}

/**
 * Whether an invitation has run out.
 *
 * An account with no expiry recorded is treated as expired rather than as
 * permanent. The only way to hold this role is to have been invited, and every
 * invitation writes a date; an account without one has been tampered with or
 * half-created, and the safe reading of a missing date is "no".
 *
 * A switched-off feature also reads as expired, the same way
 * blueworx_support_access_open() reads a switched-off support feature as a
 * shut window. Turning the feature off is not the same as deactivating the
 * plugin — the invited accounts are still there — so this is the single place
 * that closes them off, and both blueworx_external_block_expired_login() and
 * blueworx_external_enforce_expiry() inherit it by calling this.
 *
 * @param int $user_id Invited account.
 * @return bool True when access has ended.
 */
function blueworx_external_is_expired( $user_id ) {
	if ( ! blueworx_feature_enabled( 'external_access' ) ) {
		return true;
	}

	$expires = blueworx_external_expires_at( $user_id );

	return $expires <= 0 || $expires <= time();
}

/**
 * Puts the end date forward.
 *
 * Counted from now rather than from the existing date, so extending something
 * that lapsed last month gives the full period rather than a date still in the
 * past.
 *
 * Refuses to run for any account outside the external role, the same guard
 * blueworx_external_revoke() has: a caller that trusted its $user_id argument
 * would write external expiry meta onto whichever account it was pointed at —
 * an administrator included.
 *
 * @param int $user_id Invited account.
 * @param int $days    Whole days.
 * @return bool True when written.
 */
function blueworx_external_extend( $user_id, $days ) {
	$user = get_userdata( (int) $user_id );

	if ( ! $user instanceof WP_User || ! blueworx_external_is_external_user( $user ) ) {
		return false;
	}

	$days = blueworx_external_sanitize_duration( $days );

	return (bool) update_user_meta(
		(int) $user_id,
		BLUEWORX_EXTERNAL_META_EXPIRES_AT,
		blueworx_external_expiry_from( time(), $days )
	);
}

/**
 * Withdraws an invitation by deleting the account.
 *
 * Nothing is reassigned, because there is nothing to reassign: an external
 * account cannot write, so it cannot have authored anything.
 *
 * @param int $user_id Invited account.
 * @return bool True when the account was removed.
 */
function blueworx_external_revoke( $user_id ) {
	$user = get_userdata( (int) $user_id );

	if ( ! $user instanceof WP_User || ! blueworx_external_is_external_user( $user ) ) {
		return false;
	}

	if ( ! function_exists( 'wp_delete_user' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}

	return (bool) wp_delete_user( (int) $user_id );
}

/**
 * Refuses an expired account at the door.
 *
 * @param mixed  $user     User or error so far.
 * @param string $username Submitted username.
 * @param string $password Submitted password.
 * @return mixed The user, or a WP_Error.
 */
function blueworx_external_block_expired_login( $user, $username = '', $password = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Both are required by the "authenticate" filter signature; the decision is made from the resolved user alone.
	if ( ! $user instanceof WP_User || ! blueworx_external_is_external_user( $user ) ) {
		return $user;
	}

	if ( ! blueworx_external_is_expired( $user->ID ) ) {
		return $user;
	}

	return new WP_Error(
		'blueworx_external_expired',
		__( 'This access has ended. Ask whoever invited you to extend it.', 'blueworx-labs-wordpress' )
	);
}

/**
 * Ends a session that is already open when the invitation runs out.
 *
 * Refusing the login alone is not enough: somebody signed in on the last day of
 * their access would otherwise keep that session for as long as the cookie
 * lasted. Mirrors blueworx_support_enforce_window() and runs at the same point
 * for the same reasons.
 *
 * @return void
 */
function blueworx_external_enforce_expiry() {
	$user = wp_get_current_user();

	if ( ! blueworx_external_is_external_user( $user ) || ! blueworx_external_is_expired( $user->ID ) ) {
		return;
	}

	wp_destroy_current_session();
	wp_clear_auth_cookie();
	wp_set_current_user( 0 );

	wp_die(
		esc_html__( 'This access has ended. Ask whoever invited you to extend it.', 'blueworx-labs-wordpress' ),
		esc_html__( 'Access ended', 'blueworx-labs-wordpress' ),
		array( 'response' => 403 )
	);
}

/**
 * Notes when an invited person actually used their access.
 *
 * The console shows it so an administrator can tell a live demo from an
 * invitation nobody ever opened.
 *
 * @param string $login Username.
 * @param mixed  $user  Signed-in user.
 * @return void
 */
function blueworx_external_record_sign_in( $login, $user = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $login is required by the "wp_login" action signature; the user object is what this needs.
	if ( ! blueworx_external_is_external_user( $user ) ) {
		return;
	}

	update_user_meta( $user->ID, BLUEWORX_EXTERNAL_META_LAST_SEEN, time() );
}

/**
 * Records what to tell the administrator after a redirect.
 *
 * A transient rather than a query argument: the message can name an email
 * address, and addresses do not belong in a URL that ends up in a browser
 * history or a server log.
 *
 * @param string $tone  Notice tone.
 * @param string $title Notice title.
 * @param string $text  Notice body.
 * @return void
 */
function blueworx_external_set_notice( $tone, $title, $text = '' ) {
	set_transient(
		'blueworx_external_notice_' . get_current_user_id(),
		array(
			'tone'  => $tone,
			'title' => $title,
			'text'  => $text,
		),
		60
	);
}

/**
 * Reads and clears the pending message.
 *
 * @return array Notice arguments, or an empty array.
 */
function blueworx_external_take_notice() {
	$key    = 'blueworx_external_notice_' . get_current_user_id();
	$notice = get_transient( $key );

	delete_transient( $key );

	return is_array( $notice ) ? $notice : array();
}

/**
 * Handles the panel's form submissions.
 *
 * Every branch requires its own nonce, tied to its own action and (where one
 * is targeted) the one account it names, so a nonce lifted from the page
 * cannot be replayed against a different person or a different action.
 *
 * Gated on BOTH create_users and promote_users, not promote_users alone:
 * inviting somebody creates an administrator-shaped account, and create_users
 * is the capability WordPress already gates account creation on. promote_users
 * covers assigning the role. A caller missing either capability should not be
 * able to do what this function does.
 *
 * @return void
 */
function blueworx_external_handle_actions() {
	if ( ! isset( $_POST['blueworx_external_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Each branch below verifies its own nonce before acting.
		return;
	}

	if ( ! current_user_can( 'create_users' ) || ! current_user_can( 'promote_users' ) ) {
		// Said rather than silent. The screen registers on create_users alone, so
		// somebody holding that and not promote_users — a common shape on
		// multisite — can open it, fill the form in and get nothing back at all.
		// A form that does nothing and says nothing reads as a broken site.
		blueworx_external_set_notice(
			'error',
			__( 'Nothing was done.', 'blueworx-labs-wordpress' ),
			__( 'Inviting somebody both creates an account and gives it a role, and this account cannot do both. Ask an administrator to do it.', 'blueworx-labs-wordpress' )
		);

		return;
	}

	$action  = sanitize_key( wp_unslash( $_POST['blueworx_external_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below, per branch.
	$self    = admin_url( 'admin.php?page=blueworx-external' );
	$user_id = isset( $_POST['blueworx_external_user'] ) ? absint( $_POST['blueworx_external_user'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Same.

	if ( 'invite' === $action ) {
		check_admin_referer( 'blueworx_external_invite' );

		$result = blueworx_external_invite(
			array(
				'name'  => isset( $_POST['blueworx_external_name'] ) ? wp_unslash( $_POST['blueworx_external_name'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized inside blueworx_external_invite().
				'email' => isset( $_POST['blueworx_external_email'] ) ? wp_unslash( $_POST['blueworx_external_email'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Same.
				'note'  => isset( $_POST['blueworx_external_note'] ) ? wp_unslash( $_POST['blueworx_external_note'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Same.
				'days'  => isset( $_POST['blueworx_external_days'] ) ? wp_unslash( $_POST['blueworx_external_days'] ) : 0, // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reduced to an offered value by blueworx_external_sanitize_duration().
			)
		);

		if ( is_wp_error( $result ) ) {
			blueworx_external_set_notice(
				'error',
				__( 'Nobody was invited.', 'blueworx-labs-wordpress' ),
				$result->get_error_message()
			);
		} elseif ( ! $GLOBALS['blueworx_external_last_invite_mailed'] ) {
			// blueworx_external_invite() already sent the one invitation email as
			// its last step; reading whether that succeeded here rather than
			// calling blueworx_external_send_invite() again avoids overwriting the
			// reset key it already emailed, which would invalidate that link and
			// send a second, contradictory email besides.
			//
			// The account is real and usable; only the email failed. Saying so
			// is the point — a demo site with broken mail must not look like it
			// worked.
			blueworx_external_set_notice(
				'warning',
				__( 'The account was created, but the email did not send.', 'blueworx-labs-wordpress' ),
				__( 'Check that this site can send email, then use Resend on their row.', 'blueworx-labs-wordpress' )
			);
		} else {
			blueworx_external_set_notice(
				'success',
				__( 'Invitation sent.', 'blueworx-labs-wordpress' ),
				__( 'They will get an email with a link to choose a password.', 'blueworx-labs-wordpress' )
			);
		}
	}

	if ( 'revoke' === $action && $user_id ) {
		check_admin_referer( 'blueworx_external_revoke_' . $user_id );

		blueworx_external_revoke( $user_id );
		blueworx_external_set_notice( 'success', __( 'Access withdrawn.', 'blueworx-labs-wordpress' ) );
	}

	if ( 'extend' === $action && $user_id ) {
		check_admin_referer( 'blueworx_external_extend_' . $user_id );

		// blueworx_external_extend() reports failure when the new expiry equals
		// the one already stored — update_user_meta() reads "no change" the same
		// way it reads "could not write". Extending twice by the same duration
		// inside one second is the only way that happens, and it is not a
		// failure worth explaining to whoever clicked Extend, so its return
		// value is not surfaced here: this action always reports success.
		blueworx_external_extend( $user_id, isset( $_POST['blueworx_external_days'] ) ? wp_unslash( $_POST['blueworx_external_days'] ) : 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reduced to an offered value by blueworx_external_sanitize_duration().
		blueworx_external_set_notice( 'success', __( 'Access extended.', 'blueworx-labs-wordpress' ) );
	}

	if ( 'resend' === $action && $user_id ) {
		check_admin_referer( 'blueworx_external_resend_' . $user_id );

		if ( blueworx_external_send_invite( $user_id ) ) {
			blueworx_external_set_notice( 'success', __( 'Invitation sent again.', 'blueworx-labs-wordpress' ) );
		} else {
			blueworx_external_set_notice(
				'error',
				__( 'That email did not send.', 'blueworx-labs-wordpress' ),
				__( 'This site could not hand the message to a mail server. Any link they already have still works.', 'blueworx-labs-wordpress' )
			);
		}
	}

	wp_safe_redirect( $self );
	exit;
}

/**
 * The state badge for one invitation.
 *
 * Three states rather than two: "ends soon" is the one an administrator needs
 * to see before it matters, and a list that only distinguishes live from dead
 * never shows it.
 *
 * @param int $user_id Invited account.
 * @return string Badge markup.
 */
function blueworx_external_state_badge( $user_id ) {
	$expires = blueworx_external_expires_at( $user_id );
	$now     = time();

	if ( blueworx_external_is_expired( $user_id ) ) {
		return blueworx_ds_badge( __( 'Ended', 'blueworx-labs-wordpress' ), 'neutral', true );
	}

	// One day, not three. The shortest invitation on offer is three days, so a
	// three-day threshold would badge every new invitation "Ends soon" the moment
	// it was sent — a warning that fires on arrival tells nobody anything.
	if ( ( $expires - $now ) <= DAY_IN_SECONDS ) {
		return blueworx_ds_badge( __( 'Ends soon', 'blueworx-labs-wordpress' ), 'warning', true );
	}

	return blueworx_ds_badge( __( 'Active', 'blueworx-labs-wordpress' ), 'success', true );
}

/**
 * Renders the invite form and the list of people invited.
 *
 * @return void
 */
function blueworx_external_render_panel() {
	$notice = blueworx_external_take_notice();
	$self   = admin_url( 'admin.php?page=blueworx-external' );

	if ( ! empty( $notice ) ) {
		echo wp_kses( blueworx_ds_notice( $notice ), blueworx_ds_allowed_html() );
	}

	echo wp_kses(
		blueworx_ds_notice(
			array(
				'tone'  => 'info',
				'title' => __( 'What an external viewer can do', 'blueworx-labs-wordpress' ),
				'text'  => __( 'They see the back end the way an administrator does, and can change nothing: every save, delete and setting is refused. Customer and order screens stay hidden from them. One thing this cannot catch is a plugin that changes something in response to an ordinary page view rather than a save — rare, but worth knowing before you invite somebody into a live site.', 'blueworx-labs-wordpress' ),
			)
		),
		blueworx_ds_allowed_html()
	);

	$days = array();

	foreach ( blueworx_external_durations() as $option ) {
		$days[ (string) $option ] = sprintf(
			/* translators: %d: number of days. */
			_n( '%d day', '%d days', $option, 'blueworx-labs-wordpress' ),
			$option
		);
	}

	echo '<form method="post" action="' . esc_url( $self ) . '" class="bw-fields bw-fields--single">';
	wp_nonce_field( 'blueworx_external_invite' );
	echo '<input type="hidden" name="blueworx_external_action" value="invite" />';

	echo blueworx_ds_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helper escapes everything it emits.
		array(
			'label'   => __( 'Their name', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_external_name',
			'control' => blueworx_ds_input(
				array(
					'name'  => 'blueworx_external_name',
					'id'    => 'blueworx_external_name',
					'attrs' => array( 'data-testid' => 'bw-external-name' ),
				)
			),
		)
	);

	echo blueworx_ds_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same.
		array(
			'label'   => __( 'Their email address', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_external_email',
			'control' => blueworx_ds_input(
				array(
					'name'  => 'blueworx_external_email',
					'id'    => 'blueworx_external_email',
					'type'  => 'email',
					'attrs' => array(
						'required'    => 'required',
						'data-testid' => 'bw-external-email',
					),
				)
			),
			'help'    => __( 'This is where the invitation goes, and it is how they sign in.', 'blueworx-labs-wordpress' ),
		)
	);

	echo blueworx_ds_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same.
		array(
			'label'   => __( 'A note for you', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_external_note',
			'control' => blueworx_ds_input(
				array(
					'name'  => 'blueworx_external_note',
					'id'    => 'blueworx_external_note',
					'attrs' => array( 'data-testid' => 'bw-external-note' ),
				)
			),
			'help'    => __( 'Only you see this. Handy for remembering which conversation somebody came from.', 'blueworx-labs-wordpress' ),
		)
	);

	echo blueworx_ds_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same.
		array(
			'label'   => __( 'How long they get', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_external_days',
			'control' => blueworx_ds_select(
				array(
					'name'     => 'blueworx_external_days',
					'id'       => 'blueworx_external_days',
					'options'  => $days,
					'selected' => (string) blueworx_external_default_duration(),
					'attrs'    => array( 'data-testid' => 'bw-external-days' ),
				)
			),
		)
	);

	echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same.
		array(
			'label'   => __( 'Send invitation', 'blueworx-labs-wordpress' ),
			'variant' => 'primary',
			'type'    => 'submit',
			'attrs'   => array( 'data-testid' => 'bw-external-invite' ),
		)
	);

	echo '</form>';

	$invitations = blueworx_external_invitations();

	if ( empty( $invitations ) ) {
		echo wp_kses(
			blueworx_ds_empty_state(
				array(
					'title' => __( 'Nobody has been invited yet', 'blueworx-labs-wordpress' ),
					'text'  => __( 'Whoever you invite appears here, with when their access ends.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);

		return;
	}

	echo '<table class="bw-table"><thead><tr>';
	echo '<th scope="col">' . esc_html__( 'Who', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Last seen', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Access ends', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Actions', 'blueworx-labs-wordpress' ) . '</th>';
	echo '</tr></thead><tbody>';

	$format = get_option( 'date_format' );

	foreach ( $invitations as $user ) {
		$note      = (string) get_user_meta( $user->ID, BLUEWORX_EXTERNAL_META_NOTE, true );
		$last_seen = (int) get_user_meta( $user->ID, BLUEWORX_EXTERNAL_META_LAST_SEEN, true );

		echo '<tr data-testid="bw-external-row" data-external-user="' . esc_attr( $user->ID ) . '">';

		echo '<td class="bw-table__primary">' . esc_html( $user->display_name );
		echo '<span class="bw-table__sub">' . esc_html( $user->user_email ) . '</span>';

		if ( '' !== $note ) {
			echo '<span class="bw-table__sub">' . esc_html( $note ) . '</span>';
		}

		echo '</td>';

		echo '<td>' . esc_html(
			$last_seen > 0
				? date_i18n( $format, $last_seen )
				: __( 'Never', 'blueworx-labs-wordpress' )
		) . '</td>';

		echo '<td>' . esc_html( date_i18n( $format, blueworx_external_expires_at( $user->ID ) ) ) . ' ';
		echo wp_kses( blueworx_external_state_badge( $user->ID ), blueworx_ds_allowed_html() );
		echo '</td>';

		echo '<td><span class="bw-rowactions">';

		// Three separate forms rather than one with several submits: each
		// carries its own nonce, tied to its own action and this one account, so
		// a nonce lifted from the page cannot be replayed against a different
		// person or a different action.
		echo '<form method="post" action="' . esc_url( $self ) . '">';
		wp_nonce_field( 'blueworx_external_extend_' . $user->ID );
		echo '<input type="hidden" name="blueworx_external_action" value="extend" />';
		echo '<input type="hidden" name="blueworx_external_user" value="' . esc_attr( $user->ID ) . '" />';
		echo '<input type="hidden" name="blueworx_external_days" value="' . esc_attr( blueworx_external_default_duration() ) . '" />';
		echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helper escapes everything it emits.
			array(
				'label' => __( 'Extend', 'blueworx-labs-wordpress' ),
				'type'  => 'submit',
				'size'  => 'sm',
				'attrs' => array( 'data-testid' => 'bw-external-extend' ),
			)
		);
		echo '</form>';

		echo '<form method="post" action="' . esc_url( $self ) . '">';
		wp_nonce_field( 'blueworx_external_resend_' . $user->ID );
		echo '<input type="hidden" name="blueworx_external_action" value="resend" />';
		echo '<input type="hidden" name="blueworx_external_user" value="' . esc_attr( $user->ID ) . '" />';
		echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same.
			array(
				'label' => __( 'Resend', 'blueworx-labs-wordpress' ),
				'type'  => 'submit',
				'size'  => 'sm',
				'attrs' => array( 'data-testid' => 'bw-external-resend' ),
			)
		);
		echo '</form>';

		// Asks before it deletes. Withdrawing removes the account outright and
		// there is no undo, and the button sits a few pixels from Resend. The
		// question is carried on the form as an attribute and asked by
		// assets/js/external-access.js — never an inline handler, which this
		// codebase does not allow.
		echo '<form method="post" action="' . esc_url( $self ) . '" data-blueworx-confirm="' . esc_attr(
			sprintf(
				/* translators: %s: the invited person's name. */
				__( 'Withdraw access for %s? Their account is deleted and this cannot be undone.', 'blueworx-labs-wordpress' ),
				$user->display_name
			)
		) . '">';
		wp_nonce_field( 'blueworx_external_revoke_' . $user->ID );
		echo '<input type="hidden" name="blueworx_external_action" value="revoke" />';
		echo '<input type="hidden" name="blueworx_external_user" value="' . esc_attr( $user->ID ) . '" />';
		echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same.
			array(
				'label'   => __( 'Withdraw', 'blueworx-labs-wordpress' ),
				'variant' => 'danger',
				'type'    => 'submit',
				'size'    => 'sm',
				'attrs'   => array( 'data-testid' => 'bw-external-revoke' ),
			)
		);
		echo '</form>';

		echo '</span></td></tr>';
	}

	echo '</tbody></table>';
}

/**
 * Adds the external role to any Site Protection allow-list already in force.
 *
 * Site Protection only lets named roles view the site at all, so an invited
 * viewer on a protected site would meet a 403 before ever seeing the back end —
 * and nothing on screen would explain why. Adding the role when the feature is
 * switched on makes the invitation work.
 *
 * A default, not an override: the role is listed in the Site Protection pickers
 * like any other and can be taken out again. Nothing is done to a list that is
 * empty, because an empty list means that area is not restricted by role.
 *
 * External accounts deliberately get no blanket exemption of the kind the
 * BlueWorx support account holds. That exemption exists because a support
 * session is authenticated by a key outside the site's own roles; an external
 * viewer is an ordinary account and Site Protection should be able to exclude
 * it.
 *
 * @return void
 */
function blueworx_external_allow_in_site_protection() {
	foreach ( array( 'frontend', 'backend' ) as $area ) {
		$key   = 'blueworx_' . $area . '_protection_roles';
		$roles = get_option( $key, array() );

		if ( ! is_array( $roles ) || array() === $roles ) {
			continue;
		}

		if ( in_array( blueworx_external_role_slug(), $roles, true ) ) {
			continue;
		}

		$roles[] = blueworx_external_role_slug();

		update_option( $key, array_values( $roles ) );
	}
}
