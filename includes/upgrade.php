<?php
/**
 * One-time upgrade migrations.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the current migration version for this file's migrations.
 *
 * Bump this when a new migration is added below.
 *
 * @return int Current migration version.
 */
function blueworx_get_labs_db_version() {
	return 13;
}

/**
 * Whether a site that already runs this plugin should be opted out of a new
 * default-on feature.
 *
 * The promise made in includes/features.php is that an absent option falls back
 * to the registered default "so an existing install is never silently changed by
 * an update". A feature that is added default-on quietly breaks that promise:
 * the site has no stored option, so it inherits the new default and its
 * behaviour changes on upgrade without anyone choosing it.
 *
 * The fix is to write the option explicitly for sites that were already running,
 * and leave fresh installs to fall through to the default. So: new sites get the
 * safer setting, existing sites keep working, and both are a deliberate answer
 * rather than an accident of ordering.
 *
 * @param bool $plugin_has_run_before Whether this plugin has run on this site before.
 * @return bool True when the feature must be written off rather than inherited.
 */
function blueworx_should_opt_out_of_new_default( $plugin_has_run_before ) {
	return (bool) $plugin_has_run_before;
}

/**
 * Leaves existing sites with the public user list they already had.
 *
 * 1.57.0 adds the `rest_users` feature default-on. A site upgrading into it has
 * no stored option and would otherwise start refusing /wp/v2/users to anonymous
 * callers the moment it updated — which is the right end state, but not
 * something to do to a live site without its owner choosing it. Anything reading
 * that route from outside would break, and nobody would connect the failure to a
 * WordPress update.
 *
 * Fresh installs are not touched, so they inherit the default and are protected
 * from the start.
 *
 * Known limit: a site so old that it predates blueworx_labs_db_version (before
 * the 1.x "blueworx-enhancements" era) has no version row either, so it reads as
 * fresh and does get the feature on. That is accepted rather than worked around
 * — there is no other durable marker to test, and the alternative is sniffing
 * unrelated options and guessing.
 *
 * @return void
 */
function blueworx_migrate_rest_users_default() {
	if ( ! blueworx_should_opt_out_of_new_default( null !== get_option( 'blueworx_labs_db_version', null ) ) ) {
		return;
	}

	// Only when the site has not already expressed a preference, which it cannot
	// have here — but this migration must stay safe if it is ever re-run.
	if ( null !== get_option( 'blueworx_feature_rest_users', null ) ) {
		return;
	}

	update_option( 'blueworx_feature_rest_users', '0' );
}

/**
 * Keeps External access switched off on a site that already runs this plugin.
 *
 * The feature is registered default-off today, so this is belt and braces
 * rather than a correction: it writes the option explicitly for sites already
 * running, using the same fresh-install-vs-upgrade test as
 * blueworx_migrate_rest_users_default(), so that no later change to the
 * registry's default can switch on a feature that creates near-administrator
 * accounts underneath somebody who never asked for it. A fresh install is left
 * to fall through to the registered default, exactly as every other feature
 * does.
 *
 * @return void
 */
function blueworx_migrate_external_access_default() {
	if ( ! blueworx_should_opt_out_of_new_default( null !== get_option( 'blueworx_labs_db_version', null ) ) ) {
		return;
	}

	// Only when the site has not already expressed a preference, which it cannot
	// have here — but this migration must stay safe if it is ever re-run.
	if ( null !== get_option( 'blueworx_feature_external_access', null ) ) {
		return;
	}

	update_option( 'blueworx_feature_external_access', '0' );
}

/**
 * Puts the external role in place on a site that already has the function on.
 *
 * Until now the role was rebuilt on every admin request, which meant it existed
 * by accident of the last page load rather than because anything had written
 * it. That rebuild is gone — it was two database writes per admin page view,
 * and it left a window in which the role did not exist at all — so a site
 * upgrading with External access already switched on needs the role written
 * once, here.
 *
 * Nothing happens on a site with the function off, or where the role is already
 * there: this only fills the gap the removed hook used to paper over.
 *
 * @return void
 */
function blueworx_migrate_register_external_role() {
	if ( ! blueworx_feature_enabled( 'external_access' ) ) {
		return;
	}

	blueworx_external_ensure_role();
}

/**
 * Gets the admin menu options that store the menu slug as flat array VALUES.
 *
 * Each of these options is a flat, indexed array of slug strings.
 *
 * @return array Option names.
 */
function blueworx_get_admin_menu_slug_value_options() {
	return array(
		'blueworx_admin_menu_order',
		'blueworx_hidden_admin_menu_items',
		'blueworx_toggled_admin_menu_items',
	);
}

/**
 * Remaps a slug that appears as a VALUE within a flat, indexed array.
 *
 * @param array  $slugs    Indexed array of slug strings.
 * @param string $old_slug Slug to replace.
 * @param string $new_slug Replacement slug.
 * @return array Remapped, de-duplicated, re-indexed array.
 */
function blueworx_remap_admin_menu_slug_value_option( $slugs, $old_slug, $new_slug ) {
	if ( ! is_array( $slugs ) ) {
		return $slugs;
	}

	$remapped = array_map(
		function ( $slug ) use ( $old_slug, $new_slug ) {
			return $old_slug === $slug ? $new_slug : $slug;
		},
		$slugs
	);

	return array_values( array_unique( $remapped ) );
}

/**
 * Remaps a slug that appears as an array KEY, preserving its value.
 *
 * If the new key does not already exist, the old key is renamed to the new
 * key while keeping its value (and the surrounding key order). If the new key
 * already exists (both old and new present), the existing new-key entry is
 * preferred and the stale old-key entry is dropped, since the new key reflects
 * the live menu slug.
 *
 * @param array  $items    Associative array keyed by slug.
 * @param string $old_slug Key to replace.
 * @param string $new_slug Replacement key.
 * @return array Remapped array.
 */
function blueworx_remap_admin_menu_slug_key_option( $items, $old_slug, $new_slug ) {
	if ( ! is_array( $items ) || ! array_key_exists( $old_slug, $items ) ) {
		return $items;
	}

	if ( array_key_exists( $new_slug, $items ) ) {
		unset( $items[ $old_slug ] );

		return $items;
	}

	$remapped = array();

	foreach ( $items as $key => $value ) {
		$remapped[ $old_slug === $key ? $new_slug : $key ] = $value;
	}

	return $remapped;
}

/**
 * Migrates the old plugin slug to the new plugin slug inside saved admin
 * settings, so admins' menu customizations survive the plugin rename from
 * "blueworx-enhancements" to "blueworx-project-wordpress-labs".
 *
 * Covers every stored option that can hold the renamed menu slug:
 *  - blueworx_admin_menu_order         (slug as array value)
 *  - blueworx_hidden_admin_menu_items  (slug as array value)
 *  - blueworx_toggled_admin_menu_items (slug as array value)
 *  - blueworx_admin_menu_item_labels   (slug as array KEY, value = label)
 *
 * The role-editor options (blueworx_role_backend_page_rules,
 * blueworx_backend_page_map) cannot contain the renamed slug: the BlueWorx
 * top-level page is a locked backend page and locked pages are excluded from
 * the role editor, so the slug can never be stored there. See task-10 report.
 *
 * @return void
 */
function blueworx_migrate_admin_menu_slug_rename() {
	$old_slug = 'blueworx-enhancements';
	$new_slug = 'blueworx-project-wordpress-labs';

	// Options that store the slug as flat array VALUES.
	foreach ( blueworx_get_admin_menu_slug_value_options() as $option_name ) {
		$value = get_option( $option_name, null );

		if ( ! is_array( $value ) || ! in_array( $old_slug, $value, true ) ) {
			continue;
		}

		$remapped = blueworx_remap_admin_menu_slug_value_option( $value, $old_slug, $new_slug );

		if ( $remapped !== $value ) {
			update_option( $option_name, $remapped );
		}
	}

	// Option that stores the slug as an array KEY (slug => label).
	$labels = get_option( 'blueworx_admin_menu_item_labels', null );

	if ( is_array( $labels ) && array_key_exists( $old_slug, $labels ) ) {
		$remapped = blueworx_remap_admin_menu_slug_key_option( $labels, $old_slug, $new_slug );

		if ( $remapped !== $labels ) {
			update_option( 'blueworx_admin_menu_item_labels', $remapped );
		}
	}
}

/**
 * Migrates the plugin slug again after the repo rename, so admins' menu
 * customizations survive the second rename from "blueworx-project-wordpress-labs"
 * to "blueworx-labs-wordpress" (aligning the slug with the renamed repo).
 *
 * Runs after blueworx_migrate_admin_menu_slug_rename(), so sites upgrading from
 * the "blueworx-enhancements" era are remapped in two steps
 * (enhancements -> project-wordpress-labs -> labs-wordpress). Covers the same
 * stored options as that migration.
 *
 * @return void
 */
function blueworx_migrate_admin_menu_slug_labs_wordpress() {
	$old_slug = 'blueworx-project-wordpress-labs';
	$new_slug = 'blueworx-labs-wordpress';

	// Options that store the slug as flat array VALUES.
	foreach ( blueworx_get_admin_menu_slug_value_options() as $option_name ) {
		$value = get_option( $option_name, null );

		if ( ! is_array( $value ) || ! in_array( $old_slug, $value, true ) ) {
			continue;
		}

		$remapped = blueworx_remap_admin_menu_slug_value_option( $value, $old_slug, $new_slug );

		if ( $remapped !== $value ) {
			update_option( $option_name, $remapped );
		}
	}

	// Option that stores the slug as an array KEY (slug => label).
	$labels = get_option( 'blueworx_admin_menu_item_labels', null );

	if ( is_array( $labels ) && array_key_exists( $old_slug, $labels ) ) {
		$remapped = blueworx_remap_admin_menu_slug_key_option( $labels, $old_slug, $new_slug );

		if ( $remapped !== $labels ) {
			update_option( 'blueworx_admin_menu_item_labels', $remapped );
		}
	}
}

/**
 * Marks sites that already arranged their admin menu as customised, so the new
 * computed default arrangement does not overwrite an existing arrangement.
 *
 * A site counts as arranged if any of the three menu-state options holds a
 * non-empty array. Sites with no saved arrangement are left unmarked and adopt
 * the new defaults.
 *
 * @return void
 */
function blueworx_migrate_mark_admin_menu_customized() {
	foreach ( blueworx_get_admin_menu_slug_value_options() as $option_name ) {
		$value = get_option( $option_name, array() );

		if ( is_array( $value ) && ! empty( $value ) ) {
			update_option( 'blueworx_admin_menu_customized', '1' );

			return;
		}
	}
}

/**
 * Converts the retired More menu into semantic group assignments.
 *
 * The v2 design replaces the user-defined Main/More/Hidden split with four
 * semantic groups, so the More bucket has no equivalent and is retired.
 *
 * Items sitting in More are assigned to their rule-based group, which means they
 * REAPPEAR as top-level rows. This is deliberate: More was a grouping
 * affordance, not a hiding one — the plugin has always had a separate Hidden
 * bucket for hiding, so anyone wanting an item gone would have used it. Reading
 * More as "hide" would be the more destructive interpretation.
 *
 * Hidden items are left untouched. Order is preserved and reinterpreted as
 * order-within-group.
 *
 * @return void
 */
function blueworx_migrate_admin_menu_groups() {
	$toggled = get_option( 'blueworx_toggled_admin_menu_items', array() );
	$order   = get_option( 'blueworx_admin_menu_order', array() );
	$slugs   = array();

	foreach ( array( $toggled, $order ) as $source ) {
		if ( is_array( $source ) ) {
			$slugs = array_merge( $slugs, $source );
		}
	}

	$assignments = array();

	foreach ( array_unique( array_filter( $slugs ) ) as $slug ) {
		$slug = sanitize_text_field( (string) $slug );

		if ( '' === $slug || 0 === strpos( $slug, 'separator' ) || 'blueworx-menu-toggle' === $slug ) {
			continue;
		}

		$assignments[ $slug ] = blueworx_get_default_admin_menu_group( $slug );
	}

	if ( ! empty( $assignments ) ) {
		update_option( 'blueworx_admin_menu_groups', $assignments );
	}

	// Drop the retired More state and its synthetic rows from the saved order.
	delete_option( 'blueworx_toggled_admin_menu_items' );

	if ( is_array( $order ) ) {
		$cleaned = array_values(
			array_diff( $order, array( 'blueworx-menu-toggle', 'separator-blueworx-toggle' ) )
		);

		if ( $cleaned !== $order ) {
			update_option( 'blueworx_admin_menu_order', $cleaned );
		}
	}
}

/**
 * Gets the orphaned managed-role slugs left by the removed role editor.
 *
 * These roles were registered by the "Edit Role" feature removed in 1.8.0. The
 * code that created them is gone, but the roles persisted in the database (that
 * removal deliberately left existing roles untouched). Listed here as the single
 * source of truth for the removal migration and any future reintroduction.
 *
 * @return array Role slugs.
 */
function blueworx_get_orphaned_managed_role_slugs() {
	return array(
		'blueworx_business_owner',
		'blueworx_external_admin',
		'blueworx_content_editor',
	);
}

/**
 * Removes the orphaned managed roles left by the role editor removed in 1.8.0.
 *
 * A role is removed only when no users are assigned to it. remove_role() does not
 * reassign users, so dropping a role that is still a user's only role would strand
 * that account; roles that still have users are therefore left in place and their
 * slugs recorded in blueworx_orphaned_roles_skipped so the situation is visible
 * and they can be cleared once their users are reassigned. The roles can be
 * reintroduced later — this only sweeps up the abandoned rows.
 *
 * @return void
 */
function blueworx_migrate_remove_orphaned_roles() {
	$skipped = array();

	foreach ( blueworx_get_orphaned_managed_role_slugs() as $slug ) {
		if ( ! get_role( $slug ) ) {
			continue;
		}

		$assigned = get_users(
			array(
				'role'   => $slug,
				'number' => 1,
				'fields' => 'ID',
			)
		);

		if ( ! empty( $assigned ) ) {
			$skipped[] = $slug;
			continue;
		}

		remove_role( $slug );
	}

	if ( ! empty( $skipped ) ) {
		update_option( 'blueworx_orphaned_roles_skipped', array_values( $skipped ) );
	} else {
		delete_option( 'blueworx_orphaned_roles_skipped' );
	}
}

/**
 * Gets the client role slugs retired in 1.45.0.
 *
 * These were the "Client Roles" feature's three assignable roles (Business
 * Owner, External Dev, Content Editor). The feature is gone; the slugs live on
 * here only so the removal migration below can find whatever an earlier version
 * wrote into the database.
 *
 * @return array Role slugs.
 */
function blueworx_get_retired_client_role_slugs() {
	return array(
		'blueworx_client_owner',
		'blueworx_client_dev',
		'blueworx_client_editor',
	);
}

/**
 * Removes the client roles retired in 1.45.0.
 *
 * The feature's code is gone, but a site that ran 1.16.0–1.44.0 still carries
 * the three role definitions and the feature's options, so they would otherwise
 * keep appearing in the Users screen and in Site Protection forever.
 *
 * A role is removed only when no users are assigned to it: remove_role() does
 * not reassign users, so dropping a role that is still an account's only role
 * would strand that account. Anything skipped is recorded in
 * blueworx_orphaned_roles_skipped, the same visibility the 1.8.0 role-editor
 * sweep uses, so it can be cleared once its users are reassigned.
 *
 * @return void
 */
function blueworx_migrate_remove_client_roles() {
	$skipped = get_option( 'blueworx_orphaned_roles_skipped', array() );
	$skipped = is_array( $skipped ) ? $skipped : array();

	foreach ( blueworx_get_retired_client_role_slugs() as $slug ) {
		if ( ! get_role( $slug ) ) {
			continue;
		}

		$assigned = get_users(
			array(
				'role'   => $slug,
				'number' => 1,
				'fields' => 'ID',
			)
		);

		if ( ! empty( $assigned ) ) {
			$skipped[] = $slug;
			continue;
		}

		remove_role( $slug );
	}

	delete_option( 'blueworx_client_roles_signature' );
	delete_option( 'blueworx_client_editor_can_delete_users' );
	delete_option( 'blueworx_feature_client_roles' );

	$skipped = array_values( array_unique( $skipped ) );

	if ( ! empty( $skipped ) ) {
		update_option( 'blueworx_orphaned_roles_skipped', $skipped );
	} else {
		delete_option( 'blueworx_orphaned_roles_skipped' );
	}
}

/**
 * Re-registers the support role so it carries its 1.45.0 label.
 *
 * The role is written only when a key is generated, so a site with a support
 * key already issued would otherwise keep showing the old
 * "BlueWorx Support (read-only)" label until the next key. Capabilities are
 * rebuilt from the same function the feature uses, so this is a label change
 * only, and sites that never used support access have no role to touch.
 *
 * @return void
 */
function blueworx_migrate_relabel_support_role() {
	$slug = blueworx_support_role_slug();

	if ( ! get_role( $slug ) ) {
		return;
	}

	remove_role( $slug );
	add_role(
		$slug,
		__( 'BlueWorx - Support Agent (Read-Only)', 'blueworx-labs-wordpress' ),
		blueworx_readonly_build_caps()
	);
}

/**
 * Drops the retired language switcher button label.
 *
 * The switcher no longer carries wording of its own — the pill shows the
 * current language and nothing else — so the setting and its option are gone.
 * Nothing reads `blueworx_translate_label` any more; this only stops a site
 * that once customised it from carrying a dead row forever.
 *
 * @return void
 */
function blueworx_migrate_remove_translate_label() {
	delete_option( 'blueworx_translate_label' );
}

/**
 * Carries the retired administrators-only translation setting over.
 *
 * 1.79.0 replaces a single "administrators only" tick with a choice of audience
 * and a list of roles. A site that had ticked it means "not visitors", so it
 * becomes the roles audience with administrators as the one role; a site that
 * had not ticked it already means everyone, which is the new default and needs
 * nothing written.
 *
 * Safe to re-run: a site that has already answered the new question keeps its
 * answer.
 *
 * @return void
 */
function blueworx_migrate_translate_audience() {
	$admin_only = get_option( 'blueworx_translate_admin_only', null );

	if ( '1' === $admin_only && null === get_option( 'blueworx_translate_audience', null ) ) {
		update_option( 'blueworx_translate_audience', 'roles' );
		update_option( 'blueworx_translate_roles', array( 'administrator' ), false );
	}

	delete_option( 'blueworx_translate_admin_only' );
}

/**
 * Gets the options the removed headless REST layer wrote.
 *
 * Listed explicitly rather than swept with a `blueworx_headless_%` LIKE delete:
 * a wildcard delete against wp_options is the kind of thing that quietly takes
 * a row it was not meant to, and this set is finite and known.
 *
 * @return array Option names.
 */
function blueworx_get_retired_headless_options() {
	return array(
		'blueworx_headless_db_version',
		'blueworx_headless_access_ttl',
		'blueworx_headless_allowed_origins',
		'blueworx_headless_cpts',
		'blueworx_headless_default_role',
		'blueworx_headless_email_verification_required',
		'blueworx_headless_frontend_url',
		'blueworx_headless_login_max_attempts',
		'blueworx_headless_login_window',
		'blueworx_headless_refresh_ttl_days',
		'blueworx_headless_registration_mode',
		'blueworx_headless_render_shortcodes',
		'blueworx_headless_revalidate_enabled',
		'blueworx_headless_revalidate_url',
		'blueworx_headless_surecart_enabled',
		'blueworx_feature_headless_api',
	);
}

/**
 * Removes everything the headless REST layer left behind.
 *
 * The layer was switched off in 1.53.0 and deleted in 1.54.0. Its code is gone,
 * but a site that ran any earlier version still carries two tables, a scheduled
 * event with nothing listening to it, its settings, and a per-user token
 * version — all of it created on activation, on every install, whether or not
 * the site was ever headless.
 *
 * The tables hold refresh tokens and invites. Both are meaningless without the
 * code that issued and validated them: a refresh token can no longer be
 * presented anywhere, and an invite can no longer be redeemed. Dropping them is
 * therefore not data loss in any sense a site owner would recognise — it is
 * removing the residue of a feature that no longer exists.
 *
 * @return void
 */
function blueworx_migrate_remove_headless_layer() {
	global $wpdb;

	$timestamp = wp_next_scheduled( 'blueworx_headless_gc_tokens' );

	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'blueworx_headless_gc_tokens' );
	}

	// Belt and braces: an event scheduled by a version that used a different
	// argument signature would survive the single unschedule above.
	wp_clear_scheduled_hook( 'blueworx_headless_gc_tokens' );

	foreach ( blueworx_get_retired_headless_options() as $option ) {
		delete_option( $option );
	}

	delete_metadata( 'user', 0, 'blueworx_headless_token_version', '', true );
	delete_metadata( 'user', 0, 'blueworx_headless_email_unverified', '', true );

	foreach ( array( 'blueworx_refresh_tokens', 'blueworx_invites' ) as $suffix ) {
		$table = $wpdb->prefix . $suffix;

		// Dropping a table this plugin created, named from the site prefix and a
		// fixed literal — no caller input reaches this, and DROP TABLE takes no
		// placeholders.
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}

/**
 * Runs any pending one-time migrations.
 *
 * Cheap on every request: a single get_option compare when already current.
 *
 * @return void
 */
function blueworx_run_pending_labs_migrations() {
	$current_version = blueworx_get_labs_db_version();
	$stored_version  = (int) get_option( 'blueworx_labs_db_version', 0 );

	if ( $stored_version >= $current_version ) {
		return;
	}

	if ( $stored_version < 1 ) {
		blueworx_migrate_admin_menu_slug_rename();
	}

	if ( $stored_version < 2 ) {
		blueworx_migrate_admin_menu_slug_labs_wordpress();
	}

	if ( $stored_version < 3 ) {
		blueworx_migrate_mark_admin_menu_customized();
	}

	if ( $stored_version < 4 ) {
		blueworx_migrate_admin_menu_groups();
	}

	if ( $stored_version < 5 ) {
		blueworx_migrate_remove_orphaned_roles();
	}

	if ( $stored_version < 7 ) {
		blueworx_migrate_remove_client_roles();
		blueworx_migrate_relabel_support_role();
	}

	if ( $stored_version < 8 ) {
		blueworx_migrate_remove_translate_label();
	}

	if ( $stored_version < 9 ) {
		blueworx_migrate_remove_headless_layer();
	}

	// Runs before the version row is written below, so it can still tell an
	// upgrade from a fresh install by whether that row exists at all.
	if ( $stored_version < 10 ) {
		blueworx_migrate_rest_users_default();
	}

	// Same reasoning, and must run before the same version row write.
	if ( $stored_version < 11 ) {
		blueworx_migrate_external_access_default();
	}

	// After the migration above, so a site being opted out of the function does
	// not get a role written for it a moment later.
	if ( $stored_version < 12 ) {
		blueworx_migrate_register_external_role();
	}

	if ( $stored_version < 13 ) {
		blueworx_migrate_translate_audience();
	}

	update_option( 'blueworx_labs_db_version', $current_version );
}
add_action( 'plugins_loaded', 'blueworx_run_pending_labs_migrations' );
