<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Creates an external viewer with a KNOWN password, so a browser test can sign
 * in as one. The real invite flow deliberately sets a random password and mails
 * a reset link, which a test cannot follow — so the account is made here and the
 * flow itself is checked separately, by asserting on what the console shows.
 *
 * Makes the read-only spec genuinely self-sufficient — it must pass whether or
 * not external-access.spec.js has run first in the same invocation — by doing
 * everything the console's own "turn the feature on" action does, not just the
 * feature flag:
 *
 *  - Switches the external_access feature on. blueworx_external_is_expired()
 *    reads a switched-off feature as access having ended, regardless of the
 *    account's own recorded expiry.
 *  - Registers the role, which normally only happens on admin_init.
 *  - Calls blueworx_external_allow_in_site_protection(), the same call
 *    blueworx_handle_external_feature_toggle() makes when an operator switches
 *    the feature on. Without it, a site with Site Protection's backend
 *    allow-list already populated (anything other than "unrestricted") refuses
 *    this account at the door — the sign-in test would fail on a clean site
 *    that happened to have that list configured, and only worked before by
 *    accident of file order (external-access.spec.js's own toggle click makes
 *    the same call for real).
 *
 * Every one of those three is captured beforehand and restored on delete, so
 * running this fixture never leaves the site more open than it found it.
 *
 * Usage: php external-viewer.php /absolute/path/to/wp-load.php create|delete
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$command = isset( $argv[2] ) ? $argv[2] : '';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// See tests/fixtures/impostor-support-user.php for why this comes before
// wp-load: Site Protection wp_die()s an anonymous request as soon as WordPress
// finishes loading, and everything below would silently never run.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;
require_once ABSPATH . 'wp-admin/includes/user.php';

$login      = 'bw_external_test';
$pass       = 'ExternalTest!2026';
$backup_key = '_blueworx_external_test_state_backup';

if ( 'delete' === $command ) {
	$user = get_user_by( 'login', $login );

	if ( $user ) {
		wp_delete_user( $user->ID );
	}

	// Undo exactly what "create" changed, so the site is left the way it was
	// found — not switched off/empty by assumption, whatever it actually was.
	$backup = get_option( $backup_key );

	if ( is_array( $backup ) ) {
		if ( ! empty( $backup['feature_existed'] ) ) {
			update_option( 'blueworx_feature_external_access', $backup['feature'] );
		} else {
			delete_option( 'blueworx_feature_external_access' );
		}

		update_option( 'blueworx_frontend_protection_roles', $backup['frontend_roles'] );
		update_option( 'blueworx_backend_protection_roles', $backup['backend_roles'] );

		delete_option( $backup_key );
	}

	echo "deleted\n";
	exit( 0 );
}

// Captured before anything below changes it.
update_option(
	$backup_key,
	array(
		'feature_existed' => ( false !== get_option( 'blueworx_feature_external_access', false ) ),
		'feature'         => get_option( 'blueworx_feature_external_access', '0' ),
		'frontend_roles'  => get_option( 'blueworx_frontend_protection_roles', array() ),
		'backend_roles'   => get_option( 'blueworx_backend_protection_roles', array() ),
	)
);

// The feature has to be on, or blueworx_external_is_expired() reads this
// account as expired regardless of its recorded expiry date.
update_option( 'blueworx_feature_external_access', '1' );

// The role has to exist before anybody can be put in it, and it is registered on
// admin_init — which this process never reaches.
if ( function_exists( 'blueworx_external_register_role' ) ) {
	blueworx_external_register_role();
}

// Matches what switching the feature on for real does: without this, a site
// whose Site Protection backend allow-list is already populated refuses this
// account at the door, and this fixture's own claim to be self-sufficient
// would be false.
if ( function_exists( 'blueworx_external_allow_in_site_protection' ) ) {
	blueworx_external_allow_in_site_protection();
}

$user = get_user_by( 'login', $login );

if ( ! $user ) {
	$id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_pass'    => $pass,
			'user_email'   => 'external-test@example.invalid',
			'display_name' => 'External Test Viewer',
			'role'         => 'blueworx_external',
		)
	);
} else {
	$id = $user->ID;
	wp_set_password( $pass, $id );
	$refreshed = get_user_by( 'id', $id );
	$refreshed->set_role( 'blueworx_external' );
}

update_user_meta( $id, '_blueworx_external_invited_by', 1 );
update_user_meta( $id, '_blueworx_external_invited_at', time() );
update_user_meta( $id, '_blueworx_external_expires_at', time() + ( 30 * 86400 ) );
update_user_meta( $id, '_blueworx_external_note', 'Created by the test suite' );

echo "created\n";
