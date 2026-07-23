<?php
/**
 * Uninstall: remove the client role definitions.
 *
 * Definition-only. Users' wp_capabilities meta is deliberately preserved so that
 * reinstalling the plugin re-registers the roles and every assigned user regains
 * their role automatically. Slugs are inlined because the plugin's code is not
 * loaded during uninstall.
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
