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

finish();
