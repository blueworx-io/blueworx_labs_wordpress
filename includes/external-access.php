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
	return array( 7, 30, 90 );
}

/**
 * The length used when nobody chooses one.
 *
 * @return int Whole days.
 */
function blueworx_external_default_duration() {
	return 30;
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
	// Registered on admin_init rather than at load: add_role() writes an option,
	// and doing that on every front-end request of every site is a database
	// write nobody asked for. The role only has to exist where it is assigned
	// and where capabilities are resolved, and admin_init covers both.
	add_action( 'admin_init', 'blueworx_external_register_role', 1 );
}

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
 * Invites somebody.
 *
 * The password set here is random and never communicated. The invitation email
 * carries a password-reset link instead, so the person chooses their own and no
 * credential ever sits in an inbox. That is also why the account is usable
 * immediately: a reset link is not a pending state, it is the way in.
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
	$now     = (int) current_time( 'timestamp' );

	update_user_meta( $user_id, BLUEWORX_EXTERNAL_META_INVITED_BY, get_current_user_id() );
	update_user_meta( $user_id, BLUEWORX_EXTERNAL_META_INVITED_AT, $now );
	update_user_meta( $user_id, BLUEWORX_EXTERNAL_META_EXPIRES_AT, blueworx_external_expiry_from( $now, $days ) );
	update_user_meta( $user_id, BLUEWORX_EXTERNAL_META_NOTE, $note );

	blueworx_external_send_invite( $user_id );

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
 * Sends, or re-sends, the invitation email.
 *
 * Plain text. It says who invited them, that the access is view-only, when it
 * ends, and gives one link. It contains no password, because there is no
 * password to contain.
 *
 * @param int $user_id Invited account.
 * @return bool True when the mail was handed off successfully.
 */
function blueworx_external_send_invite( $user_id ) {
	$user = get_userdata( (int) $user_id );

	if ( ! $user instanceof WP_User ) {
		return false;
	}

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

	return (bool) wp_mail( $user->user_email, $subject, implode( "\n", $lines ) );
}
