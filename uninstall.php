<?php
/**
 * Uninstall: remove the client role definitions and BlueWorx support access.
 *
 * Client roles are definition-only. Users' wp_capabilities meta is deliberately
 * preserved so that reinstalling the plugin re-registers the roles and every
 * assigned user regains their role automatically. Slugs are inlined because the
 * plugin's code is not loaded during uninstall.
 *
 * Support access is different: the managed account itself must not survive
 * uninstall, so that part requires includes/support-access.php to reuse its
 * existing removal routine rather than reimplementing it here.
 *
 * @package BlueWorxLabs
 */

// Only run from WordPress's uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

foreach ( array( 'blueworx_client_owner', 'blueworx_client_dev', 'blueworx_client_editor' ) as $blueworx_role_slug ) {
	if ( get_role( $blueworx_role_slug ) ) {
		remove_role( $blueworx_role_slug );
	}
}

delete_option( 'blueworx_client_roles_signature' );
delete_option( 'blueworx_client_editor_can_delete_users' );

// BlueWorx Support Access: remove the managed account, the role, and every
// option the feature created. blueworx_support_remove_account() is required
// from includes/support-access.php rather than reimplemented here because it
// already knows the account's login and how to tear it down safely; unlike
// the client-roles pattern above, the account itself (not just a role
// definition) must not survive uninstall. Requiring the file is safe in this
// context: at the top level it only defines constants and functions and
// registers hooks that never fire during uninstall (no admin_init, init or
// REST request is in flight), so nothing it references executes here except
// the one function called explicitly below.
require_once __DIR__ . '/includes/support-access.php';
blueworx_support_remove_account();

delete_option( 'blueworx_support_key_hash' );
delete_option( 'blueworx_support_access_until' );
delete_option( 'blueworx_support_data_until' );
delete_option( 'blueworx_support_log' );

// Not deleted: the per-IP throttle transient (blueworx_support_fail_<md5(ip)>).
// It is keyed by hashed caller address, not by a name this file can enumerate,
// so removing it would require a wildcard query against wp_options — exactly
// the kind of broad delete this file avoids elsewhere. Any stale transient
// expires on its own within BLUEWORX_SUPPORT_LOCKOUT (900) seconds, and it
// blocks nothing once the key hash above is gone, so leaving it is safe.
