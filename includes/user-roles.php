<?php
/**
 * User role selection.
 *
 * Two changes to the Role control on the add-user and edit-user screens: the
 * list reads alphabetically, and a user may hold more than one role at once.
 *
 * WordPress has supported multiple roles per user since 3.0 — add_role() and
 * remove_role() are core, and capability resolution is already the union of
 * every assigned role. What core does not give is a way to *choose* more than
 * one: wp-admin renders a single <select name="role">, and edit_user() reads
 * exactly one value from it. This file supplies the missing control and the
 * matching save path; nothing here teaches WordPress anything it did not
 * already know about holding several roles.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The screens whose Role control this feature replaces.
 *
 * Your own profile (profile.php) is deliberately absent: WordPress never shows
 * the Role control there, and nobody should be able to grant themselves a role.
 *
 * @return array Admin screen hook suffixes.
 */
function blueworx_user_roles_screens() {
	return array( 'user-edit.php', 'user-new.php' );
}

/**
 * Sorts the editable roles alphabetically by their displayed name.
 *
 * Roles arrive in registration order, which is the order plugins happened to
 * install them in — meaningless to the person reading the list. Sorted on the
 * *translated* name, because that is the string actually on screen.
 *
 * "— No role for this site —" is not sorted in: wp-admin appends that option
 * after wp_dropdown_roles() has printed the roles, so it is already last and
 * never passes through this filter.
 *
 * This filter alone is not enough for the Role control itself, and deliberately
 * so: wp_dropdown_roles() prints array_reverse( get_editable_roles() ), which
 * would turn an ascending sort here into a descending list on screen. Sorting
 * descending to compensate would fix that one dropdown and leave every other
 * consumer of get_editable_roles() backwards, so the order is correct here and
 * the rendered checkbox list sorts again in assets/js/user-roles.js.
 *
 * @param array $roles Editable roles keyed by role slug.
 * @return array The same roles, ordered by display name.
 */
function blueworx_user_roles_sort_editable( $roles ) {
	if ( ! is_array( $roles ) ) {
		return $roles;
	}

	uasort(
		$roles,
		static function ( $a, $b ) {
			$a_name = isset( $a['name'] ) ? translate_user_role( $a['name'] ) : '';
			$b_name = isset( $b['name'] ) ? translate_user_role( $b['name'] ) : '';

			return strnatcasecmp( $a_name, $b_name );
		}
	);

	return $roles;
}

/**
 * Enqueues the script that turns the Role dropdown into a checkbox list.
 *
 * Progressive enhancement, and deliberately so: the replacement happens in the
 * browser because wp-admin offers no filter around the Role row. With the script
 * blocked or failed the native single-select is left exactly as it was and still
 * saves one role the core way — a user is never left with no way to set a role.
 *
 * @param string $hook_suffix Current admin screen hook.
 * @return void
 */
function blueworx_user_roles_enqueue( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, blueworx_user_roles_screens(), true ) ) {
		return;
	}

	if ( ! current_user_can( 'promote_users' ) ) {
		return;
	}

	wp_enqueue_script(
		'blueworx-labs-wordpress-user-roles',
		BLUEWORX_LABS_URL . 'assets/js/user-roles.js',
		array(),
		blueworx_get_admin_asset_version( 'assets/js/user-roles.js' ),
		true
	);

	$user_id = 'user-edit.php' === $hook_suffix && isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing; the value is only used to pre-tick boxes and every submitted role is re-validated on save.
	$user    = $user_id ? get_userdata( $user_id ) : false;

	wp_localize_script(
		'blueworx-labs-wordpress-user-roles',
		'blueworxUserRoles',
		array(
			'field'    => 'blueworx_user_roles',
			'selected' => $user instanceof WP_User ? array_values( (array) $user->roles ) : array(),
			'help'     => __( 'A user may hold more than one role. Their permissions are everything their chosen roles allow, combined. Clear every box to leave them with no role on this site.', 'blueworx-labs-wordpress' ),
		)
	);

	// Attached to core's own always-present admin stylesheet: the list is a few
	// lines of layout and does not justify a file, an HTTP request or a second
	// place to look when the Role row renders oddly.
	wp_add_inline_style(
		'common',
		'.blueworx-role-choices{margin:0 0 6px;max-width:32em;}
.blueworx-role-choices li{margin:0 0 4px;}
.blueworx-role-choices label{display:flex;align-items:center;gap:8px;}
.blueworx-role-help{margin-top:0;}'
	);
}

/**
 * Reads the submitted roles, rejecting anything the current user may not grant.
 *
 * Intersected against get_editable_roles() rather than the full role list: that
 * is the same allowlist wp_dropdown_roles() renders from, and it is what stops a
 * hand-crafted POST granting a role the sender was never offered.
 *
 * @return array Role slugs, in the sorted order the screen offered them.
 */
function blueworx_user_roles_submitted() {
	$raw = isset( $_POST['blueworx_user_roles'] ) ? (array) wp_unslash( $_POST['blueworx_user_roles'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified by blueworx_user_roles_request_is_valid() before this value is used; sanitized here with sanitize_key and intersected against the editable-roles allowlist.

	$editable = array_keys( get_editable_roles() );
	$slugs    = array_unique( array_map( 'sanitize_key', $raw ) );

	return array_values( array_intersect( $editable, $slugs ) );
}

/**
 * Reports whether this request may set another user's roles.
 *
 * The nonce is core's own — this feature adds no field of its own to check —
 * so both the edit-user and add-user forms are accepted, and nothing else is.
 * They do not agree on a field name: user-edit.php posts `_wpnonce`, while
 * user-new.php posts `_wpnonce_create-user`.
 *
 * @param int $user_id User being saved.
 * @return bool True when the roles in this request may be applied.
 */
function blueworx_user_roles_request_is_valid( $user_id ) {
	if ( ! isset( $_POST['blueworx_user_roles'] ) || ! is_admin() ) {
		return false;
	}

	// Your own roles are never editable here: wp-admin does not offer the Role
	// control on your own profile, and an administrator must not be able to
	// demote themselves — or a compromised session escalate — through this path.
	if ( get_current_user_id() === (int) $user_id ) {
		return false;
	}

	if ( ! current_user_can( 'promote_users' ) || ! current_user_can( 'edit_user', $user_id ) ) {
		return false;
	}

	$edit_nonce   = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
	$create_nonce = isset( $_POST['_wpnonce_create-user'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce_create-user'] ) ) : '';

	return (bool) wp_verify_nonce( $edit_nonce, 'update-user_' . $user_id ) || (bool) wp_verify_nonce( $create_nonce, 'create-user' );
}

/**
 * Applies the submitted roles to a user.
 *
 * Runs after core has already saved: edit_user() sets a single role from
 * $_POST['role'] on its way through wp_insert_user(), and both profile_update
 * and user_register fire once that is done. With the checkbox list in play the
 * native select is disabled and never submitted, so core sets no role at all on
 * an edit and the default role on a new user — either way this is the last word.
 *
 * set_role() first and add_role() after, rather than removing one at a time:
 * set_role() clears every existing role in one step, so the user is never
 * momentarily left holding a stale role alongside the new ones.
 *
 * @param int $user_id User being saved.
 * @return void
 */
function blueworx_user_roles_save( $user_id ) {
	if ( ! blueworx_user_roles_request_is_valid( $user_id ) ) {
		return;
	}

	$user = get_userdata( $user_id );

	if ( ! $user instanceof WP_User ) {
		return;
	}

	$roles = blueworx_user_roles_submitted();

	if ( array() === $roles ) {
		// The explicit "no role for this site" case: every box cleared.
		$user->set_role( '' );

		return;
	}

	$user->set_role( array_shift( $roles ) );

	foreach ( $roles as $role ) {
		$user->add_role( $role );
	}
}

if ( blueworx_feature_enabled( 'user_roles' ) ) {
	add_filter( 'editable_roles', 'blueworx_user_roles_sort_editable' );
	add_action( 'admin_enqueue_scripts', 'blueworx_user_roles_enqueue' );
	add_action( 'profile_update', 'blueworx_user_roles_save' );
	add_action( 'user_register', 'blueworx_user_roles_save' );
}
