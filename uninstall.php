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
 * @package BlueWorxLabs
 */

// Only run from WordPress's uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'blueworx_translate_languages' );
delete_option( 'blueworx_translate_position' );
delete_option( 'blueworx_translate_label' );
delete_option( 'blueworx_translate_exclusions' );
delete_option( 'blueworx_translate_display' );
delete_option( 'blueworx_translate_audience' );
delete_option( 'blueworx_translate_roles' );
delete_option( 'blueworx_translate_admin_only' );

// BlueWorx Support Access: remove the managed account, the role, and every
// option the feature created. blueworx_support_remove_account() is required
// from includes/support-access.php rather than reimplemented here because it
// already knows the account's login and how to tear it down safely, and the
// account itself (not just a role definition) must not survive uninstall.
// Requiring the file is safe in this
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

// BlueWorx: External — remove the feature option, the role definition and the
// per-user meta the invitations wrote. The role slug and meta keys are written
// as literals rather than via includes/external-access.php's constants and
// functions: the plugin is not loaded during uninstall, so neither exists here
// (the same reason blueworx_support_remove_account() above is required in
// explicitly rather than referenced by name alone).
//
// The invited accounts are already gone by the time this runs, and that is
// deliberate. WordPress always deactivates a plugin before it uninstalls it,
// and blueworx_external_on_deactivate() deletes every invited account on the
// way out: an external account is administrator-shaped, and the only thing
// making it read-only is this plugin's own request-layer block. Left standing
// with the plugin off, it would be a working administrator login. So the
// accounts go with the code that narrows them, and what is left to clear here
// is the role definition, the switch and the meta.
delete_option( 'blueworx_feature_external_access' );

remove_role( 'blueworx_external' );

delete_metadata( 'user', 0, '_blueworx_external_invited_by', '', true );
delete_metadata( 'user', 0, '_blueworx_external_invited_at', '', true );
delete_metadata( 'user', 0, '_blueworx_external_expires_at', '', true );
delete_metadata( 'user', 0, '_blueworx_external_note', '', true );
delete_metadata( 'user', 0, '_blueworx_external_last_seen', '', true );
