<?php
/**
 * Matching an identity to a WordPress user.
 *
 * These are the account-takeover rules: who gets linked to whom, on what
 * evidence, and what a sign-in is never allowed to grant.
 *
 * Run with: php tests/php/users-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

$GLOBALS['options'] = array(
	'blueworx_sso_issuer'        => 'https://idp.test',
	'blueworx_sso_auto_register' => '0',
	'blueworx_sso_default_role'  => 'subscriber',
);

$GLOBALS['users']    = array();
$GLOBALS['meta']     = array();
$GLOBALS['inserted'] = array();
$GLOBALS['actions']  = array();
$GLOBALS['roles']    = array( 'subscriber', 'editor', 'author', 'administrator', 'sc_customer' );

/**
 * A WP_User stand-in.
 */
class WP_User {
	/**
	 * User ID.
	 *
	 * @var int
	 */
	public $ID;

	/**
	 * Username.
	 *
	 * @var string
	 */
	public $user_login;

	/**
	 * Email address.
	 *
	 * @var string
	 */
	public $user_email;

	/**
	 * Roles held.
	 *
	 * @var array
	 */
	public $roles;

	/**
	 * Constructor.
	 *
	 * @param int    $id    User ID.
	 * @param string $login Username.
	 * @param string $email Email address.
	 * @param array  $roles Roles held.
	 */
	public function __construct( $id, $login, $email, $roles = array( 'subscriber' ) ) {
		$this->ID         = $id;
		$this->user_login = $login;
		$this->user_email = $email;
		$this->roles      = $roles;
	}
}

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) {
			return $user;
		}

		if ( 'id' === $field && (int) $user->ID === (int) $value ) {
			return $user;
		}
	}

	return false;
}

function username_exists( $login ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( $user->user_login === $login ) {
			return $user->ID;
		}
	}

	return false;
}

function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['meta'][ $user_id ][ $key ] = $value;
	return true;
}

function get_user_meta( $user_id, $key, $single = false ) {
	return isset( $GLOBALS['meta'][ $user_id ][ $key ] ) ? $GLOBALS['meta'][ $user_id ][ $key ] : '';
}

function get_users( $args ) {
	$wanted = array();

	foreach ( $args['meta_query'] as $clause ) {
		if ( is_array( $clause ) && isset( $clause['key'] ) ) {
			$wanted[ $clause['key'] ] = $clause['value'];
		}
	}

	$found = array();

	foreach ( $GLOBALS['users'] as $user ) {
		$meta  = isset( $GLOBALS['meta'][ $user->ID ] ) ? $GLOBALS['meta'][ $user->ID ] : array();
		$match = true;

		foreach ( $wanted as $key => $value ) {
			if ( ! isset( $meta[ $key ] ) || $meta[ $key ] !== $value ) {
				$match = false;
			}
		}

		if ( $match ) {
			$found[] = $user;
		}
	}

	return $found;
}

function get_role( $slug ) {
	return in_array( $slug, $GLOBALS['roles'], true ) ? (object) array( 'name' => $slug ) : null;
}

function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
}

function wp_insert_user( $userdata ) {
	$GLOBALS['inserted'][] = $userdata;
	$id                    = count( $GLOBALS['users'] ) + 100;
	$user                  = new WP_User( $id, $userdata['user_login'], $userdata['user_email'], array( $userdata['role'] ) );
	$GLOBALS['users'][]    = $user;

	return $id;
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require __DIR__ . '/../../includes/sso/sso.php';
require __DIR__ . '/../../includes/sso/discovery.php';
require __DIR__ . '/../../includes/sso/users.php';

/**
 * Builds a claim set.
 *
 * @param array $overrides Claims to change.
 * @return array
 */
function claims( $overrides = array() ) {
	return array_merge(
		array(
			'sub'            => 'provider-subject-1',
			'email'          => 'person@example.test',
			'email_verified' => true,
			'given_name'     => 'Sam',
			'family_name'    => 'Taylor',
		),
		$overrides
	);
}

/**
 * Resolves a claim set and reduces the result to something comparable.
 *
 * Defaults to the joining route, because that is the only one allowed to create
 * an account; the signing-in route is asked for explicitly where it matters.
 *
 * @param array  $claim_set Claims.
 * @param string $intent    'register' or 'login'.
 * @return string Error code, or 'user:' and the username.
 */
function resolve( $claim_set, $intent = 'register' ) {
	$result = blueworx_sso_resolve_user( $claim_set, $intent );

	return is_wp_error( $result ) ? $result->get_error_code() : 'user:' . $result->user_login;
}

// A claim set with no subject proves nothing about who is signing in.
check( 'a sign-in with no subject is refused', resolve( claims( array( 'sub' => '' ) ) ), 'blueworx_sso_no_subject' );

// Nobody here yet, and creating accounts is off.
check( 'an unknown person is refused when registration is off', resolve( claims() ), 'blueworx_sso_no_account' );

// An unverified email must never link, because anyone able to register that
// address at the provider could then walk into the matching account here.
$GLOBALS['users'][] = new WP_User( 1, 'existing', 'person@example.test', array( 'editor' ) );
check( 'an unverified email is refused', resolve( claims( array( 'email_verified' => false ) ) ), 'blueworx_sso_email_unverified' );
check( 'a missing verification claim is refused', resolve( claims( array( 'email_verified' => null ) ) ), 'blueworx_sso_email_unverified' );
check( 'nothing was linked by the refusals', isset( $GLOBALS['meta'][1] ), false );

// The one-time link: a verified email adopts the existing account, and records
// the subject so the email is never needed again.
check( 'a verified email links to the existing account', resolve( claims() ), 'user:existing' );
check( 'and the subject is recorded', $GLOBALS['meta'][1]['blueworx_sso_subject'], 'provider-subject-1' );
check( 'and the issuer with it', $GLOBALS['meta'][1]['blueworx_sso_issuer'], 'https://idp.test' );
check( 'the existing role is untouched', $GLOBALS['users'][0]->roles, array( 'editor' ) );

// Once linked, the subject is the key: a changed email at the provider still
// finds the same account, and no new one is created.
check( 'a later email change still finds the account', resolve( claims( array( 'email' => 'moved@example.test' ) ) ), 'user:existing' );
check( 'and created nobody', count( $GLOBALS['inserted'] ), 0 );

// A different subject arriving on the same verified email is a different person
// at the provider, and must not be allowed to re-point the linked account.
check( 'a second identity on a linked account is refused', resolve( claims( array( 'sub' => 'provider-subject-2' ) ) ), 'blueworx_sso_already_linked' );
check( 'and the original link is intact', $GLOBALS['meta'][1]['blueworx_sso_subject'], 'provider-subject-1' );

$GLOBALS['options']['blueworx_sso_auto_register'] = '1';

echo "\nSigning in never creates an account\n";

// Someone with no account here who presses Sign in has almost certainly signed
// in somewhere before and expects to find their things. Handing them a fresh,
// empty account looks exactly like their history has been lost, so the routes
// are kept apart: only joining creates.
$before = count( $GLOBALS['inserted'] );
check( 'signing in with no account is refused even with joining switched on', resolve( claims( array( 'sub' => 'subject-visitor', 'email' => 'visitor@example.test' ) ), 'login' ), 'blueworx_sso_no_account' );
check( 'and created nobody', count( $GLOBALS['inserted'] ), $before );

// Linking is not creating, though: an existing account with a matching verified
// email is still adopted on the way in.
$GLOBALS['users'][] = new WP_User( 7, 'already-here', 'already-here@example.test', array( 'author' ) );
check( 'signing in still links an existing account', resolve( claims( array( 'sub' => 'subject-existing', 'email' => 'already-here@example.test' ) ), 'login' ), 'user:already-here' );
check( 'without creating anything', count( $GLOBALS['inserted'] ), $before );
check( 'and without touching their role', $GLOBALS['users'][ count( $GLOBALS['users'] ) - 1 ]->roles, array( 'author' ) );

echo "\nJoining creates\n";

check( 'a new person gets an account', resolve( claims( array( 'sub' => 'subject-new', 'email' => 'newcomer@example.test' ) ) ), 'user:newcomer' );
check( 'on the configured role', $GLOBALS['inserted'][0]['role'], 'subscriber' );
check( 'with their name filled in', $GLOBALS['inserted'][0]['first_name'], 'Sam' );

// A username collision must not fail, and must not overwrite anyone.
$GLOBALS['users'][] = new WP_User( 5, 'taken', 'taken@elsewhere.test' );
check( 'a clashing username is made unique', resolve( claims( array( 'sub' => 'subject-clash', 'email' => 'taken@example.test' ) ) ), 'user:taken-2' );

// The rule that matters most: no route through this file may produce an
// administrator, whatever the settings say.
$GLOBALS['options']['blueworx_sso_default_role'] = 'administrator';
check( 'a configured administrator role is refused', resolve( claims( array( 'sub' => 'subject-admin', 'email' => 'admin-hopeful@example.test' ) ) ), 'user:admin-hopeful' );
check( 'and lands on subscriber instead', $GLOBALS['inserted'][2]['role'], 'subscriber' );

$GLOBALS['options']['blueworx_sso_default_role'] = 'nonexistent-role';
check( 'an unknown role falls back to subscriber', resolve( claims( array( 'sub' => 'subject-unknown-role', 'email' => 'unknown-role@example.test' ) ) ), 'user:unknown-role' );
check( 'on subscriber', $GLOBALS['inserted'][3]['role'], 'subscriber' );

// And a site plugin filtering the new account cannot promote it either.
$GLOBALS['options']['blueworx_sso_default_role'] = 'subscriber';
$GLOBALS['filters']['blueworx_sso_new_user_data'] = function ( $userdata, $claim_set ) {
	$userdata['role'] = 'administrator';
	return $userdata;
};

check( 'a filter cannot promote to administrator', resolve( claims( array( 'sub' => 'subject-filtered', 'email' => 'filtered@example.test' ) ) ), 'user:filtered' );
check( 'the filtered role is reduced', $GLOBALS['inserted'][4]['role'], 'subscriber' );
unset( $GLOBALS['filters']['blueworx_sso_new_user_data'] );

// The hook every site-specific plugin hangs off must fire for both branches.
$GLOBALS['actions'] = array();
resolve( claims() );
check( 'the authenticated hook fires for a returning person', $GLOBALS['actions'][0][0], 'blueworx_sso_user_authenticated' );
check( 'and says they are not new', $GLOBALS['actions'][0][3], false );

$GLOBALS['actions'] = array();
resolve( claims( array( 'sub' => 'subject-hook', 'email' => 'hook@example.test' ) ) );
check( 'and says a first-timer is new', $GLOBALS['actions'][0][3], true );

finish();
