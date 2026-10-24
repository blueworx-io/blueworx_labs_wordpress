<?php
/**
 * Uninstall: remove BlueWorx support access, BlueWorx: External, and their
 * feature options.
 *
 * The support account itself must not survive uninstall, so that part requires
 * includes/support-access.php to reuse its existing removal routine rather than
 * reimplementing it here. Invited external accounts are treated differently —
 * see the comment beside their cleanup below.
 *
 * The retired client roles are not handled here: the 1.45.0 migration in
 * includes/upgrade.php clears them on upgrade, well before any uninstall.
 *
 * On a network every site has its own settings, roles and support window, so
 * the per-site part runs on each site in turn. User meta is shared by the whole
 * network and is cleared once, after every site is done.
 *
 * @package BlueWorxLabs
 */

// Only run from WordPress's uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// BlueWorx Support Access: blueworx_support_remove_account() is required from
// includes/support-access.php rather than reimplemented here because it
// already knows the account's login and how to tear it down safely, and the
// account itself (not just a role definition) must not survive uninstall.
// blueworx_for_each_site() and blueworx_delete_owned_account() come from the
// same file. Requiring it is safe in this context: at the top level it only
// defines constants and functions and registers hooks that never fire during
// uninstall (no admin_init, init or REST request is in flight), so nothing it
// references executes here except the functions called explicitly below.
require_once __DIR__ . '/includes/support-access.php';

// A site on the BlueWorx theme still uses every setting and account below, so
// deleting the old plugin after moving to the theme leaves that site alone.
$blueworx_theme_in_use = false;

blueworx_for_each_site(
	function () use ( &$blueworx_theme_in_use ) {
		if ( blueworx_labs_theme_takes_over() ) {
			$blueworx_theme_in_use = true;
			return;
		}

		blueworx_uninstall_site();
	},
	true
);

// The per-user meta the invitations wrote. User meta belongs to the whole
// network rather than to one site, so this runs once, not per site, and not
// at all while a site on the theme may still have guest viewers.
if ( ! $blueworx_theme_in_use ) {
	delete_metadata( 'user', 0, '_blueworx_external_invited_by', '', true );
	delete_metadata( 'user', 0, '_blueworx_external_invited_at', '', true );
	delete_metadata( 'user', 0, '_blueworx_external_expires_at', '', true );
	delete_metadata( 'user', 0, '_blueworx_external_note', '', true );
	delete_metadata( 'user', 0, '_blueworx_external_last_seen', '', true );
}

/**
 * Removes the plugin's settings, role and accounts from the current site.
 *
 * Run once on a single site, and once on every site of a network.
 *
 * @return void
 */
function blueworx_uninstall_site() {
	delete_option( 'blueworx_translate_languages' );
	delete_option( 'blueworx_translate_position' );
	delete_option( 'blueworx_translate_label' );
	delete_option( 'blueworx_translate_exclusions' );
	delete_option( 'blueworx_translate_display' );
	delete_option( 'blueworx_translate_audience' );
	delete_option( 'blueworx_translate_roles' );
	delete_option( 'blueworx_translate_admin_only' );

	delete_option( 'blueworx_tracking_gtm' );
	delete_option( 'blueworx_tracking_ga' );
	delete_option( 'blueworx_tracking_meta' );

	// BlueWorx Support Access: the managed account, the role, and every option
	// the feature created. On a network the account is shared, and is deleted
	// outright on the last site that still has it.
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

	// BlueWorx: External — remove the feature option and the role definition.
	// The role slug is written as a literal rather than via
	// includes/external-access.php's functions: the plugin is not loaded during
	// uninstall, so they do not exist here (the same reason
	// blueworx_support_remove_account() above is required in explicitly rather
	// than referenced by name alone).
	//
	// On a single site the invited accounts are already gone by the time this
	// runs, and that is deliberate. WordPress always deactivates a plugin before
	// it uninstalls it, and blueworx_external_on_deactivate() deletes every
	// invited account on the way out: an external account is
	// administrator-shaped, and the only thing making it read-only is this
	// plugin's own request-layer block. Left standing with the plugin off, it
	// would be a working administrator login. So the accounts go with the code
	// that narrows them, and what is left to clear here is the role definition
	// and the switch.
	//
	// A network is the exception. The plugin can be deleted there while still
	// switched on for individual sites, which never get that deactivation, so
	// any viewer still on this site is deleted here instead.
	if ( is_multisite() ) {
		$viewers = get_users(
			array(
				'role'   => 'blueworx_external',
				'fields' => 'ID',
			)
		);

		foreach ( (array) $viewers as $viewer_id ) {
			blueworx_delete_owned_account( $viewer_id );
		}
	}

	delete_option( 'blueworx_feature_external_access' );

	remove_role( 'blueworx_external' );

	// Connect Claude: every client registration, connection and log entry, and
	// the switch. The one-time codes are transients that expire within minutes.
	delete_option( 'blueworx_feature_mcp' );
	delete_option( 'blueworx_mcp_clients' );
	delete_option( 'blueworx_mcp_grants' );
	delete_option( 'blueworx_mcp_last_used' );
	delete_option( 'blueworx_mcp_revoked_at' );
	delete_option( 'blueworx_mcp_log' );
}
