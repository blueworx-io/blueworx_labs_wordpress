<?php
/**
 * Application passwords: admins only, and never a crash.
 *
 * WordPress asks whether application passwords are available while it is
 * still working out who is signed in. A check that asks who is signed in at
 * that point loops until PHP runs out of stack (#211), so the filter must
 * decide from the user it is given and nothing else.
 *
 * Run with: php tests/php/application-passwords-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

require __DIR__ . '/stubs.php';

$GLOBALS['asked_who_is_signed_in'] = 0;

function blueworx_feature_enabled( $key ) {
	return true;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function current_user_can( $cap ) {
	++$GLOBALS['asked_who_is_signed_in'];
	return true;
}
function user_can( $user, $cap ) {
	return 'manage_options' === $cap && in_array( 'administrator', (array) $user->roles, true );
}

$admin  = (object) array(
	'ID'    => 1,
	'roles' => array( 'administrator' ),
);
$editor = (object) array(
	'ID'    => 2,
	'roles' => array( 'editor' ),
);

require __DIR__ . '/../../includes/profile-cleanup.php';

echo "With application passwords switched on\n";
update_option( 'blueworx_show_application_passwords', '1' );
check( 'an admin can use one', blueworx_filter_application_passwords_available( true, $admin ), true );
check( 'a non-admin cannot', blueworx_filter_application_passwords_available( true, $editor ), false );
check( 'and the check never asks who is signed in', $GLOBALS['asked_who_is_signed_in'], 0 );

echo "\nWith them switched off\n";
update_option( 'blueworx_show_application_passwords', '0' );
check( 'nobody can, admins included', blueworx_filter_application_passwords_available( true, $admin ), false );

echo "\nWordPress's own answer still counts\n";
update_option( 'blueworx_show_application_passwords', '1' );
check( 'an admin on a site where WordPress has them off', blueworx_filter_application_passwords_available( false, $admin ), false );

finish();
