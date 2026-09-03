<?php
/**
 * The pure rules behind an external invitation.
 *
 * Durations, expiry maths and the username derived from an email address are
 * decided without WordPress, so they are checked without it. Creating the
 * account, sending the mail and refusing an expired sign-in need a running
 * site and are covered by tests/external-access.spec.js.
 *
 * Run with: php tests/php/external-access-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Same: the text domain is part of the signature.

class WP_User {
	public $ID = 0;
	public $roles = array();
	public $user_login = '';
	public $user_email = '';
	public $display_name = '';
	public $user_activation_key = '';

	public function __construct( $id = 0, $roles = array() ) {
		$this->ID    = $id;
		$this->roles = $roles;
	}

	public function exists() {
		return $this->ID > 0;
	}
}

function blueworx_feature_enabled( $key ) {
	return empty( $GLOBALS['feature_off'] );
}

function blueworx_readonly_build_caps() {
	return array( 'read' => true, 'manage_options' => true );
}

function blueworx_readonly_user_has_role( $user, $slug ) {
	return $user instanceof WP_User && in_array( $slug, (array) $user->roles, true );
}

// Stubs local to this test, beyond what stubs.php already provides. Kept as
// its own block so Tasks 3 and 4 can append what their own checks need
// without hunting through the rest of the file.
// --- begin local stubs ---

// The guard registers its hooks at file scope; only their existence matters
// here, not what they do, since nothing in this script fires a hook.
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

// Task 3: invitations create real accounts, so the store lives here rather
// than in stubs.php, which stays generic across every test file that requires it.
$GLOBALS['users']      = array(
	// A pre-existing administrator, id 1, so blueworx_external_send_invite()'s
	// role guard has a real non-external account to be tested against.
	1 => array(
		'id'    => 1,
		'login' => 'admin',
		'email' => 'admin@example.com',
		'name'  => 'Luke',
		'role'  => 'administrator',
		'meta'  => array(),
	),
);
$GLOBALS['next_id']    = 100;
$GLOBALS['mail_sent']  = array();
$GLOBALS['mail_fails'] = false;

function username_exists( $name ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( $user['login'] === $name ) {
			return $user['id'];
		}
	}

	return false;
}

function email_exists( $email ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( $user['email'] === $email ) {
			return $user['id'];
		}
	}

	return false;
}

function wp_insert_user( $data ) {
	$id = $GLOBALS['next_id']++;

	$GLOBALS['users'][ $id ] = array(
		'id'    => $id,
		'login' => $data['user_login'],
		'email' => $data['user_email'],
		'name'  => $data['display_name'],
		'role'  => $data['role'],
		'meta'  => array(),
	);

	return $id;
}

function update_user_meta( $id, $key, $value ) {
	$GLOBALS['users'][ $id ]['meta'][ $key ] = $value;

	return true;
}

function get_user_meta( $id, $key, $single = false ) {
	return isset( $GLOBALS['users'][ $id ]['meta'][ $key ] )
		? $GLOBALS['users'][ $id ]['meta'][ $key ]
		: '';
}

function wp_mail( $to, $subject, $message ) {
	if ( $GLOBALS['mail_fails'] ) {
		return false;
	}

	$GLOBALS['mail_sent'][] = array(
		'to'      => $to,
		'subject' => $subject,
		'message' => $message,
	);

	return true;
}

function sanitize_email( $email ) {
	return trim( (string) $email );
}

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

function get_bloginfo( $what ) {
	return 'Demo Site';
}

function home_url( $path = '/' ) {
	return 'https://demo.example.com' . $path;
}

function network_site_url( $path = '', $scheme = null ) {
	// The plugin's custom-login filter rewrites this in a live site; the point
	// checked here is that whatever it returns is what the email carries.
	return 'https://demo.example.com/' . ltrim( (string) $path, '/' );
}

$GLOBALS['reset_keys_issued'] = 0;

function get_password_reset_key( $user ) {
	++$GLOBALS['reset_keys_issued'];

	// Mirrors core: minting a key WRITES it to the account, killing whatever
	// link was there before. That side effect is the whole reason
	// blueworx_external_send_invite() keeps the previous value.
	$GLOBALS['users'][ $user->ID ]['activation_key'] = 'RESETKEY123';

	return 'RESETKEY123';
}

/**
 * The one wpdb call this file's code under test makes: putting a reset key back.
 *
 * Narrow on purpose. It answers to the single update() shape
 * blueworx_external_restore_reset_key() uses and would fail loudly on any other,
 * rather than quietly accepting writes nothing has checked.
 */
class Blueworx_Test_Wpdb {
	public $users = 'wp_users';

	public function update( $table, $data, $where ) {
		$GLOBALS['users'][ $where['ID'] ]['activation_key'] = $data['user_activation_key'];

		return 1;
	}
}

$GLOBALS['wpdb'] = new Blueworx_Test_Wpdb();

function clean_user_cache( $id ) {}

function get_userdata( $id ) {
	if ( ! isset( $GLOBALS['users'][ $id ] ) ) {
		return false;
	}

	$row                       = $GLOBALS['users'][ $id ];
	$user                      = new WP_User( $row['id'], array( $row['role'] ) );
	$user->user_login          = $row['login'];
	$user->user_email          = $row['email'];
	$user->display_name        = $row['name'];
	$user->user_activation_key = isset( $row['activation_key'] ) ? $row['activation_key'] : '';

	return $user;
}

function wp_delete_user( $id ) {
	if ( ! isset( $GLOBALS['users'][ $id ] ) ) {
		return false;
	}

	unset( $GLOBALS['users'][ $id ] );

	return true;
}

function wp_get_current_user() {
	$user               = new WP_User( 1, array( 'administrator' ) );
	$user->display_name = 'Luke';

	return $user;
}

function get_current_user_id() {
	return 1;
}

function wp_specialchars_decode( $t, $q = 0 ) {
	return $t;
}

function date_i18n( $f, $t ) {
	return gmdate( 'j F Y', (int) $t );
}

// --- end local stubs ---

require __DIR__ . '/../../includes/external-access.php';

echo "The role is named the way every other role in this plugin is\n";

check( 'the slug', blueworx_external_role_slug(), 'blueworx_external' );
check( 'the name', blueworx_external_role_name(), 'BlueWorx: External' );

echo "\nWho counts as an external viewer\n";

check(
	'an account holding the role does',
	blueworx_external_is_external_user( new WP_User( 4, array( 'blueworx_external' ) ) ),
	true
);
check(
	'an administrator does not',
	blueworx_external_is_external_user( new WP_User( 1, array( 'administrator' ) ) ),
	false
);
check( 'and nor does nobody at all', blueworx_external_is_external_user( null ), false );

echo "\nHow long an invitation lasts\n";

check( 'the default is thirty days', blueworx_external_default_duration(), 30 );
check( 'seven is offered', in_array( 7, blueworx_external_durations(), true ), true );
check( 'ninety is offered', in_array( 90, blueworx_external_durations(), true ), true );

echo "\nA duration nobody offered falls back rather than being honoured\n";

check( 'a value off the list', blueworx_external_sanitize_duration( 3650 ), 30 );
check( 'a negative value', blueworx_external_sanitize_duration( -1 ), 30 );
check( 'junk', blueworx_external_sanitize_duration( 'forever' ), 30 );
check( 'and a value on the list is kept', blueworx_external_sanitize_duration( '7' ), 7 );

echo "\nExpiry is counted from now, not from midnight\n";

$now = 1000000;

check( 'thirty days on', blueworx_external_expiry_from( $now, 30 ), $now + ( 30 * DAY_IN_SECONDS ) );
check( 'seven days on', blueworx_external_expiry_from( $now, 7 ), $now + ( 7 * DAY_IN_SECONDS ) );

echo "\nA username is derived from the email address\n";

check( 'the local part is used', blueworx_external_username_from_email( 'jane.doe@example.com' ), 'jane.doe' );
check( 'punctuation nobody can type is dropped', blueworx_external_username_from_email( 'a+tag@example.com' ), 'atag' );
check( 'and an unusable address still yields something', blueworx_external_username_from_email( '@@@' ), 'external' );

echo "\nAn invitation creates one account, with an expiry on it\n";

// The invite path stamps its own timestamp with time() rather than a mockable
// clock (support-access.php does the same for its own window, and Task 4's
// expiry fixture writes time() - 60 directly) so the check below brackets a
// real wall-clock call rather than asserting an exact value.
$before = time();

$id = blueworx_external_invite(
	array(
		'name'  => 'Jane Doe',
		'email' => 'jane@example.com',
		'note'  => 'Prospect, seen the pitch',
		'days'  => 30,
	)
);

$after = time();

check( 'the account was created', is_int( $id ) && $id > 0, true );
check( 'in the external role', $GLOBALS['users'][ $id ]['role'], 'blueworx_external' );
check( 'with a username off the address', $GLOBALS['users'][ $id ]['login'], 'jane' );
check(
	'and an expiry thirty days out',
	blueworx_external_expires_at( $id ) >= blueworx_external_expiry_from( $before, 30 )
		&& blueworx_external_expires_at( $id ) <= blueworx_external_expiry_from( $after, 30 ),
	true
);
check(
	'the note is kept',
	get_user_meta( $id, BLUEWORX_EXTERNAL_META_NOTE, true ),
	'Prospect, seen the pitch'
);
check(
	'and so is who invited them',
	(int) get_user_meta( $id, BLUEWORX_EXTERNAL_META_INVITED_BY, true ),
	1
);
// blueworx_external_invite() sends the invitation itself; a caller must never
// also call blueworx_external_send_invite() on the result, because a second
// call regenerates the reset key and invalidates the link the first email
// already carries — two emails, the first one dead on arrival. This count
// proves invite() itself queued exactly one.
check( 'and exactly one email was queued', count( $GLOBALS['mail_sent'] ), 1 );
check(
	'and blueworx_external_invite() reports the send succeeded, for a caller that does not want to send again',
	$GLOBALS['blueworx_external_last_invite_mailed'],
	true
);

echo "\nThe same address is not invited twice\n";

$again = blueworx_external_invite(
	array(
		'name'  => 'Jane Doe',
		'email' => 'jane@example.com',
		'days'  => 30,
	)
);

check( 'a duplicate is refused', is_wp_error( $again ), true );

echo "\nAnd an address that is not one is refused before an account exists\n";

$bad = blueworx_external_invite( array( 'name' => 'Nobody', 'email' => 'not-an-address', 'days' => 30 ) );

check( 'junk is refused', is_wp_error( $bad ), true );

echo "\nThe email carries a link to set a password, and no password\n";

$mail = end( $GLOBALS['mail_sent'] );

check( 'one was sent', is_array( $mail ), true );
check( 'to the person invited', $mail['to'], 'jane@example.com' );
check( 'carrying a reset link', false !== strpos( $mail['message'], 'action=rp' ), true );
check( 'and the key', false !== strpos( $mail['message'], 'RESETKEY123' ), true );
check( 'saying the access is view-only', false !== stripos( $mail['message'], 'view-only' ), true );
// wp_generate_password( 64, ... ) in this stub always returns 'aA1!' repeated
// to length, so its absence proves the account's actual password never made
// it into the message rather than merely not being asserted on.
check( 'and no password', false === strpos( $mail['message'], 'aA1!' ), true );

echo "\nA send that fails is reported rather than swallowed\n";

$GLOBALS['mail_fails'] = true;

$failed = blueworx_external_invite(
	array( 'name' => 'Sam', 'email' => 'sam@example.com', 'days' => 7 )
);

check( 'the account is still created', is_int( $failed ) && $failed > 0, true );
check(
	'and blueworx_external_invite() itself reports the send failed',
	$GLOBALS['blueworx_external_last_invite_mailed'],
	false
);
check( 'and the failure is visible to the caller', blueworx_external_send_invite( $failed ), false );

echo "\nA resend that fails leaves the link the person already has alone\n";

// A working invitation: the key on the account is the one their email carries.
$GLOBALS['mail_fails'] = false;

$holder = blueworx_external_invite(
	array( 'name' => 'Ada', 'email' => 'ada@example.com', 'days' => 30 )
);

$GLOBALS['users'][ $holder ]['activation_key'] = 'THEIR-WORKING-KEY';

// Now mail breaks and they ask for it again.
$GLOBALS['mail_fails'] = true;

check( 'the resend reports failure', blueworx_external_send_invite( $holder ), false );
check(
	'and their existing link still works',
	$GLOBALS['users'][ $holder ]['activation_key'],
	'THEIR-WORKING-KEY'
);

$GLOBALS['mail_fails'] = false;

check( 'while a resend that succeeds does rotate it', blueworx_external_send_invite( $holder ), true );
check( 'to the newly minted key', $GLOBALS['users'][ $holder ]['activation_key'], 'RESETKEY123' );

echo "\nA username collision is resolved by numbering, not by failing\n";

$second = blueworx_external_invite(
	array(
		'name'  => 'Jane Other',
		'email' => 'jane@other.example',
		'days'  => 30,
	)
);

check( 'a second account is created', is_int( $second ) && $second > 0, true );
check( 'with the collision resolved by numbering', $GLOBALS['users'][ $second ]['login'], 'jane2' );

echo "\nThe reset email is never sent to an account outside the external role\n";

$keys_before = $GLOBALS['reset_keys_issued'];

check( 'an administrator is refused', blueworx_external_send_invite( 1 ), false );
check( 'and no reset key was generated for them', $GLOBALS['reset_keys_issued'], $keys_before );

echo "\nAccess stops when it runs out\n";

$live = blueworx_external_invite(
	array( 'name' => 'Live', 'email' => 'live@example.com', 'days' => 30 )
);

check( 'a fresh invitation is not expired', blueworx_external_is_expired( $live ), false );

// Pushed into the past the way the console's own Extend does it, in reverse.
update_user_meta( $live, BLUEWORX_EXTERNAL_META_EXPIRES_AT, 1000000 - 1 );

check( 'one whose date has passed is', blueworx_external_is_expired( $live ), true );

echo "\nExtending it puts the clock forward from now\n";

// blueworx_external_extend() stamps time() rather than a mockable clock (the
// same reason the invite test above brackets it), so this brackets a real
// wall-clock call rather than asserting an exact value.
$before = time();

blueworx_external_extend( $live, 7 );

$after = time();

check(
	'seven days from now, not from when it lapsed',
	blueworx_external_expires_at( $live ) >= blueworx_external_expiry_from( $before, 7 )
		&& blueworx_external_expires_at( $live ) <= blueworx_external_expiry_from( $after, 7 ),
	true
);
check( 'and it is live again', blueworx_external_is_expired( $live ), false );

echo "\nExtending is refused for an account outside the external role\n";

// Same guard blueworx_external_revoke() already has: a table row's own ID
// should never be trusted to belong to an external account.
$admin_expiry_before = blueworx_external_expires_at( 1 );

check( 'extending an administrator is refused', blueworx_external_extend( 1, 30 ), false );
check(
	'and no expiry meta was written onto them',
	blueworx_external_expires_at( 1 ),
	$admin_expiry_before
);

echo "\nAn expired account cannot sign back in\n";

update_user_meta( $live, BLUEWORX_EXTERNAL_META_EXPIRES_AT, 1000000 - 1 );

$refused = blueworx_external_block_expired_login( get_userdata( $live ), 'live', 'whatever' );

check( 'authentication is refused', is_wp_error( $refused ), true );

blueworx_external_extend( $live, 30 );

$allowed = blueworx_external_block_expired_login( get_userdata( $live ), 'live', 'whatever' );

check( 'and a live one is not', is_wp_error( $allowed ), false );

echo "\nSwitching the feature off ends access the same as a lapsed date\n";

// $live is a genuinely live account at this point (extended above), so this
// isolates the feature switch as the reason access ends rather than the date.
$GLOBALS['feature_off'] = true;

check( 'a live account reads as expired while the feature is off', blueworx_external_is_expired( $live ), true );

$refused_by_switch = blueworx_external_block_expired_login( get_userdata( $live ), 'live', 'whatever' );

check( 'and sign-in is refused while it is off', is_wp_error( $refused_by_switch ), true );

unset( $GLOBALS['feature_off'] );

check( 'and normal again once the feature is back on', blueworx_external_is_expired( $live ), false );

echo "\nAn account that is not external is never touched by any of this\n";

$admin = new WP_User( 1, array( 'administrator' ) );

check(
	'an administrator authenticates normally',
	is_wp_error( blueworx_external_block_expired_login( $admin, 'luke', 'whatever' ) ),
	false
);

echo "\nRevoking removes the account entirely\n";

$revoked = blueworx_external_invite(
	array( 'name' => 'Revoke Me', 'email' => 'revoke@example.com', 'days' => 30 )
);

check( 'revoking an external account succeeds', blueworx_external_revoke( $revoked ), true );
check( 'and the account is gone', get_userdata( $revoked ), false );
check( 'revoking an administrator is refused', blueworx_external_revoke( 1 ), false );
check( 'and the administrator account still exists', get_userdata( 1 ) instanceof WP_User, true );

finish();
