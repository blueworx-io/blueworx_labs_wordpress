<?php
/**
 * BlueWorx user roles.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the BlueWorx managed roles.
 *
 * @return array Role labels keyed by role slug.
 */
function blueworx_get_managed_roles() {
	return array(
		'blueworx_business_owner' => __( 'Business Owner', 'blueworx-project-wordpress-labs' ),
		'blueworx_external_admin' => __( 'External Admin', 'blueworx-project-wordpress-labs' ),
		'blueworx_content_editor' => __( 'Content Editor', 'blueworx-project-wordpress-labs' ),
	);
}

/**
 * Creates the BlueWorx roles when missing.
 *
 * @return void
 */
function blueworx_ensure_managed_roles() {
	foreach ( blueworx_get_managed_roles() as $role_slug => $role_label ) {
		if ( get_role( $role_slug ) ) {
			continue;
		}

		add_role(
			$role_slug,
			$role_label,
			array(
				'read' => true,
			)
		);
	}
}
add_action( 'init', 'blueworx_ensure_managed_roles' );
register_activation_hook( BLUEWORX_LABS_PATH . 'blueworx-project-wordpress-labs.php', 'blueworx_activate_managed_roles' );

/**
 * Creates BlueWorx roles on plugin activation.
 *
 * @param bool $network_wide Whether the plugin is network activated.
 * @return void
 */
function blueworx_activate_managed_roles( $network_wide = false ) {
	if ( ! is_multisite() || ! $network_wide ) {
		blueworx_ensure_managed_roles();
		return;
	}

	$sites = get_sites(
		array(
			'fields' => 'ids',
		)
	);

	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		blueworx_ensure_managed_roles();
		restore_current_blog();
	}
}

/**
 * Gets all capabilities that can be assigned from the role editor.
 *
 * @return array Capability details keyed by capability name.
 */
function blueworx_get_editable_capabilities() {
	$wp_roles     = wp_roles();
	$capabilities = blueworx_get_extra_editable_capabilities();

	foreach ( $wp_roles->roles as $role ) {
		if ( empty( $role['capabilities'] ) || ! is_array( $role['capabilities'] ) ) {
			continue;
		}

		foreach ( $role['capabilities'] as $capability => $allowed ) {
			if ( ! $allowed || 'do_not_allow' === $capability || 0 === strpos( $capability, 'level_' ) ) {
				continue;
			}

			$capabilities[ $capability ] = blueworx_get_capability_details( $capability );
		}
	}

	uasort( $capabilities, 'blueworx_sort_capabilities_by_label' );

	return $capabilities;
}

/**
 * Gets extra permissions that might not exist until another plugin adds them.
 *
 * @return array Capability details keyed by capability name.
 */
function blueworx_get_extra_editable_capabilities() {
	$capabilities = array();

	foreach ( blueworx_get_elementor_capabilities() as $capability ) {
		$capabilities[ $capability ] = blueworx_get_capability_details( $capability );
	}

	return $capabilities;
}

/**
 * Gets Elementor permissions shown in the role editor.
 *
 * @return array Elementor capability names.
 */
function blueworx_get_elementor_capabilities() {
	return array(
		'blueworx_edit_elementor_templates',
		'delete_elementor_library',
		'delete_others_elementor_library',
		'delete_private_elementor_library',
		'delete_published_elementor_library',
		'edit_elementor_library',
		'edit_others_elementor_library',
		'edit_private_elementor_library',
		'edit_published_elementor_library',
		'publish_elementor_library',
		'read_private_elementor_library',
	);
}

/**
 * Sorts permissions by their plain English names.
 *
 * @param array $first  First capability details.
 * @param array $second Second capability details.
 * @return int Sort result.
 */
function blueworx_sort_capabilities_by_label( $first, $second ) {
	return strnatcasecmp( $first['label'], $second['label'] );
}

/**
 * Gets a readable label for a WordPress capability.
 *
 * @param string $capability Capability name.
 * @return string Capability label.
 */
function blueworx_get_capability_label( $capability ) {
	$details = blueworx_get_capability_details( $capability );

	return $details['label'];
}

/**
 * Gets the name and simple description for a permission.
 *
 * @param string $capability Capability name.
 * @return array Capability details.
 */
function blueworx_get_capability_details( $capability ) {
	$details = blueworx_get_known_capability_details();

	if ( isset( $details[ $capability ] ) ) {
		return $details[ $capability ];
	}

	$label = ucwords( str_replace( '_', ' ', $capability ) );

	return array(
		'label'       => $label,
		'description' => sprintf(
			/* translators: %s: Capability label. */
			__( 'Allows this role to use the %s permission.', 'blueworx-project-wordpress-labs' ),
			$label
		),
	);
}

/**
 * Gets plain English names and descriptions for common permissions.
 *
 * @return array Capability details keyed by capability name.
 */
function blueworx_get_known_capability_details() {
	return array(
		'activate_plugins'                   => blueworx_capability_details( __( 'Activate plugins', 'blueworx-project-wordpress-labs' ), __( 'Allows turning installed plugins on or off.', 'blueworx-project-wordpress-labs' ) ),
		'blueworx_edit_elementor_templates'  => blueworx_capability_details( __( 'Edit Elementor templates', 'blueworx-project-wordpress-labs' ), __( 'Allows editing Elementor templates such as headers, footers, popups, and theme layouts.', 'blueworx-project-wordpress-labs' ) ),
		'create_users'                       => blueworx_capability_details( __( 'Create users', 'blueworx-project-wordpress-labs' ), __( 'Allows adding new users to the site.', 'blueworx-project-wordpress-labs' ) ),
		'delete_elementor_library'           => blueworx_capability_details( __( 'Delete Elementor templates', 'blueworx-project-wordpress-labs' ), __( 'Allows deleting Elementor template items.', 'blueworx-project-wordpress-labs' ) ),
		'delete_others_elementor_library'    => blueworx_capability_details( __( 'Delete other Elementor templates', 'blueworx-project-wordpress-labs' ), __( 'Allows deleting Elementor templates made by other users.', 'blueworx-project-wordpress-labs' ) ),
		'delete_others_pages'                => blueworx_capability_details( __( 'Delete other users pages', 'blueworx-project-wordpress-labs' ), __( 'Allows deleting pages created by other users.', 'blueworx-project-wordpress-labs' ) ),
		'delete_others_posts'                => blueworx_capability_details( __( 'Delete other users posts', 'blueworx-project-wordpress-labs' ), __( 'Allows deleting posts created by other users.', 'blueworx-project-wordpress-labs' ) ),
		'delete_pages'                       => blueworx_capability_details( __( 'Delete pages', 'blueworx-project-wordpress-labs' ), __( 'Allows deleting pages.', 'blueworx-project-wordpress-labs' ) ),
		'delete_plugins'                     => blueworx_capability_details( __( 'Delete plugins', 'blueworx-project-wordpress-labs' ), __( 'Allows removing plugins from the site.', 'blueworx-project-wordpress-labs' ) ),
		'delete_posts'                       => blueworx_capability_details( __( 'Delete posts', 'blueworx-project-wordpress-labs' ), __( 'Allows deleting posts.', 'blueworx-project-wordpress-labs' ) ),
		'delete_private_elementor_library'   => blueworx_capability_details( __( 'Delete private Elementor templates', 'blueworx-project-wordpress-labs' ), __( 'Allows deleting private Elementor templates.', 'blueworx-project-wordpress-labs' ) ),
		'delete_private_pages'               => blueworx_capability_details( __( 'Delete private pages', 'blueworx-project-wordpress-labs' ), __( 'Allows deleting private pages.', 'blueworx-project-wordpress-labs' ) ),
		'delete_private_posts'               => blueworx_capability_details( __( 'Delete private posts', 'blueworx-project-wordpress-labs' ), __( 'Allows deleting private posts.', 'blueworx-project-wordpress-labs' ) ),
		'delete_published_elementor_library' => blueworx_capability_details( __( 'Delete published Elementor templates', 'blueworx-project-wordpress-labs' ), __( 'Allows deleting live Elementor templates.', 'blueworx-project-wordpress-labs' ) ),
		'delete_published_pages'             => blueworx_capability_details( __( 'Delete published pages', 'blueworx-project-wordpress-labs' ), __( 'Allows deleting live pages.', 'blueworx-project-wordpress-labs' ) ),
		'delete_published_posts'             => blueworx_capability_details( __( 'Delete published posts', 'blueworx-project-wordpress-labs' ), __( 'Allows deleting live posts.', 'blueworx-project-wordpress-labs' ) ),
		'delete_themes'                      => blueworx_capability_details( __( 'Delete themes', 'blueworx-project-wordpress-labs' ), __( 'Allows removing themes from the site.', 'blueworx-project-wordpress-labs' ) ),
		'delete_users'                       => blueworx_capability_details( __( 'Delete users', 'blueworx-project-wordpress-labs' ), __( 'Allows permanently deleting users.', 'blueworx-project-wordpress-labs' ) ),
		'edit_dashboard'                     => blueworx_capability_details( __( 'Edit dashboard', 'blueworx-project-wordpress-labs' ), __( 'Allows changing dashboard widgets and dashboard content.', 'blueworx-project-wordpress-labs' ) ),
		'edit_elementor_library'             => blueworx_capability_details( __( 'Edit Elementor templates', 'blueworx-project-wordpress-labs' ), __( 'Allows editing Elementor template items.', 'blueworx-project-wordpress-labs' ) ),
		'edit_files'                         => blueworx_capability_details( __( 'Edit files', 'blueworx-project-wordpress-labs' ), __( 'Allows editing theme and plugin files from WordPress.', 'blueworx-project-wordpress-labs' ) ),
		'edit_others_elementor_library'      => blueworx_capability_details( __( 'Edit other Elementor templates', 'blueworx-project-wordpress-labs' ), __( 'Allows editing Elementor templates made by other users.', 'blueworx-project-wordpress-labs' ) ),
		'edit_others_pages'                  => blueworx_capability_details( __( 'Edit other users pages', 'blueworx-project-wordpress-labs' ), __( 'Allows editing pages created by other users.', 'blueworx-project-wordpress-labs' ) ),
		'edit_others_posts'                  => blueworx_capability_details( __( 'Edit other users posts', 'blueworx-project-wordpress-labs' ), __( 'Allows editing posts created by other users.', 'blueworx-project-wordpress-labs' ) ),
		'edit_pages'                         => blueworx_capability_details( __( 'Edit pages', 'blueworx-project-wordpress-labs' ), __( 'Allows updating page content.', 'blueworx-project-wordpress-labs' ) ),
		'edit_plugins'                       => blueworx_capability_details( __( 'Edit plugins', 'blueworx-project-wordpress-labs' ), __( 'Allows editing plugin files from WordPress.', 'blueworx-project-wordpress-labs' ) ),
		'edit_posts'                         => blueworx_capability_details( __( 'Edit posts', 'blueworx-project-wordpress-labs' ), __( 'Allows updating post content.', 'blueworx-project-wordpress-labs' ) ),
		'edit_private_elementor_library'     => blueworx_capability_details( __( 'Edit private Elementor templates', 'blueworx-project-wordpress-labs' ), __( 'Allows editing private Elementor templates.', 'blueworx-project-wordpress-labs' ) ),
		'edit_private_pages'                 => blueworx_capability_details( __( 'Edit private pages', 'blueworx-project-wordpress-labs' ), __( 'Allows editing private pages.', 'blueworx-project-wordpress-labs' ) ),
		'edit_private_posts'                 => blueworx_capability_details( __( 'Edit private posts', 'blueworx-project-wordpress-labs' ), __( 'Allows editing private posts.', 'blueworx-project-wordpress-labs' ) ),
		'edit_published_elementor_library'   => blueworx_capability_details( __( 'Edit published Elementor templates', 'blueworx-project-wordpress-labs' ), __( 'Allows editing live Elementor templates.', 'blueworx-project-wordpress-labs' ) ),
		'edit_published_pages'               => blueworx_capability_details( __( 'Edit published pages', 'blueworx-project-wordpress-labs' ), __( 'Allows updating live pages.', 'blueworx-project-wordpress-labs' ) ),
		'edit_published_posts'               => blueworx_capability_details( __( 'Edit published posts', 'blueworx-project-wordpress-labs' ), __( 'Allows updating live posts.', 'blueworx-project-wordpress-labs' ) ),
		'edit_theme_options'                 => blueworx_capability_details( __( 'Edit theme options', 'blueworx-project-wordpress-labs' ), __( 'Allows changing theme settings, menus, widgets, and some builder settings.', 'blueworx-project-wordpress-labs' ) ),
		'edit_themes'                        => blueworx_capability_details( __( 'Edit themes', 'blueworx-project-wordpress-labs' ), __( 'Allows editing theme files from WordPress.', 'blueworx-project-wordpress-labs' ) ),
		'edit_users'                         => blueworx_capability_details( __( 'Edit users', 'blueworx-project-wordpress-labs' ), __( 'Allows changing user accounts.', 'blueworx-project-wordpress-labs' ) ),
		'export'                             => blueworx_capability_details( __( 'Export content', 'blueworx-project-wordpress-labs' ), __( 'Allows downloading site content exports.', 'blueworx-project-wordpress-labs' ) ),
		'import'                             => blueworx_capability_details( __( 'Import content', 'blueworx-project-wordpress-labs' ), __( 'Allows importing content into the site.', 'blueworx-project-wordpress-labs' ) ),
		'install_plugins'                    => blueworx_capability_details( __( 'Install plugins', 'blueworx-project-wordpress-labs' ), __( 'Allows adding new plugins.', 'blueworx-project-wordpress-labs' ) ),
		'install_themes'                     => blueworx_capability_details( __( 'Install themes', 'blueworx-project-wordpress-labs' ), __( 'Allows adding new themes.', 'blueworx-project-wordpress-labs' ) ),
		'list_users'                         => blueworx_capability_details( __( 'List users', 'blueworx-project-wordpress-labs' ), __( 'Allows seeing the user list.', 'blueworx-project-wordpress-labs' ) ),
		'manage_categories'                  => blueworx_capability_details( __( 'Manage categories', 'blueworx-project-wordpress-labs' ), __( 'Allows adding and changing categories and tags.', 'blueworx-project-wordpress-labs' ) ),
		'manage_links'                       => blueworx_capability_details( __( 'Manage links', 'blueworx-project-wordpress-labs' ), __( 'Allows managing old WordPress link items.', 'blueworx-project-wordpress-labs' ) ),
		'manage_options'                     => blueworx_capability_details( __( 'Manage site settings', 'blueworx-project-wordpress-labs' ), __( 'Allows changing important WordPress settings.', 'blueworx-project-wordpress-labs' ) ),
		'moderate_comments'                  => blueworx_capability_details( __( 'Moderate comments', 'blueworx-project-wordpress-labs' ), __( 'Allows approving, editing, and deleting comments.', 'blueworx-project-wordpress-labs' ) ),
		'promote_users'                      => blueworx_capability_details( __( 'Promote users', 'blueworx-project-wordpress-labs' ), __( 'Allows changing user roles.', 'blueworx-project-wordpress-labs' ) ),
		'publish_elementor_library'          => blueworx_capability_details( __( 'Publish Elementor templates', 'blueworx-project-wordpress-labs' ), __( 'Allows publishing Elementor templates.', 'blueworx-project-wordpress-labs' ) ),
		'publish_pages'                      => blueworx_capability_details( __( 'Publish pages', 'blueworx-project-wordpress-labs' ), __( 'Allows making pages live.', 'blueworx-project-wordpress-labs' ) ),
		'publish_posts'                      => blueworx_capability_details( __( 'Publish posts', 'blueworx-project-wordpress-labs' ), __( 'Allows making posts live.', 'blueworx-project-wordpress-labs' ) ),
		'read'                               => blueworx_capability_details( __( 'Read', 'blueworx-project-wordpress-labs' ), __( 'Allows logging in and viewing the WordPress dashboard.', 'blueworx-project-wordpress-labs' ) ),
		'read_private_elementor_library'     => blueworx_capability_details( __( 'Read private Elementor templates', 'blueworx-project-wordpress-labs' ), __( 'Allows viewing private Elementor templates.', 'blueworx-project-wordpress-labs' ) ),
		'read_private_pages'                 => blueworx_capability_details( __( 'Read private pages', 'blueworx-project-wordpress-labs' ), __( 'Allows viewing private pages.', 'blueworx-project-wordpress-labs' ) ),
		'read_private_posts'                 => blueworx_capability_details( __( 'Read private posts', 'blueworx-project-wordpress-labs' ), __( 'Allows viewing private posts.', 'blueworx-project-wordpress-labs' ) ),
		'remove_users'                       => blueworx_capability_details( __( 'Remove users', 'blueworx-project-wordpress-labs' ), __( 'Allows removing users from the site.', 'blueworx-project-wordpress-labs' ) ),
		'switch_themes'                      => blueworx_capability_details( __( 'Switch themes', 'blueworx-project-wordpress-labs' ), __( 'Allows changing the active theme.', 'blueworx-project-wordpress-labs' ) ),
		'update_core'                        => blueworx_capability_details( __( 'Update WordPress', 'blueworx-project-wordpress-labs' ), __( 'Allows updating WordPress itself.', 'blueworx-project-wordpress-labs' ) ),
		'update_plugins'                     => blueworx_capability_details( __( 'Update plugins', 'blueworx-project-wordpress-labs' ), __( 'Allows updating installed plugins.', 'blueworx-project-wordpress-labs' ) ),
		'update_themes'                      => blueworx_capability_details( __( 'Update themes', 'blueworx-project-wordpress-labs' ), __( 'Allows updating installed themes.', 'blueworx-project-wordpress-labs' ) ),
		'upload_files'                       => blueworx_capability_details( __( 'Upload files', 'blueworx-project-wordpress-labs' ), __( 'Allows uploading images, PDFs, and other media.', 'blueworx-project-wordpress-labs' ) ),
	);
}

/**
 * Formats a permission name and description.
 *
 * @param string $label       Capability label.
 * @param string $description Capability description.
 * @return array Capability details.
 */
function blueworx_capability_details( $label, $description ) {
	return array(
		'label'       => $label,
		'description' => $description,
	);
}

/**
 * Gets enabled capabilities for a managed role.
 *
 * @param string $role_slug Role slug.
 * @return array Enabled capabilities.
 */
function blueworx_get_role_enabled_capabilities( $role_slug ) {
	$role = get_role( $role_slug );

	if ( ! $role ) {
		return array();
	}

	return array_keys( array_filter( $role->capabilities ) );
}

/**
 * Updates one managed role with selected capabilities.
 *
 * @param string $role_slug    Role slug.
 * @param array  $capabilities Selected capabilities.
 * @return void
 */
function blueworx_update_managed_role_capabilities( $role_slug, $capabilities ) {
	$role = get_role( $role_slug );

	if ( ! $role ) {
		return;
	}

	$editable = array_keys( blueworx_get_editable_capabilities() );
	$selected = array_values( array_intersect( array_unique( array_map( 'sanitize_key', $capabilities ) ), $editable ) );

	if ( ! in_array( 'read', $selected, true ) ) {
		$selected[] = 'read';
	}

	foreach ( $editable as $capability ) {
		$role->remove_cap( $capability );
	}

	foreach ( $selected as $capability ) {
		$role->add_cap( $capability );
	}
}

/**
 * Gets the saved backend page rules for all BlueWorx roles.
 *
 * @return array Saved backend page rules.
 */
function blueworx_get_role_backend_page_rules() {
	$rules = get_option( 'blueworx_role_backend_page_rules', array() );

	if ( ! is_array( $rules ) ) {
		return array();
	}

	return $rules;
}

/**
 * Saves backend page rules for one BlueWorx role.
 *
 * @param string $role_slug       Role slug.
 * @param array  $allowed_pages   Pages with full access.
 * @param array  $view_only_pages Pages with view-only access.
 * @return void
 */
function blueworx_update_role_backend_page_rules( $role_slug, $allowed_pages, $view_only_pages ) {
	$managed_roles = blueworx_get_managed_roles();

	if ( ! isset( $managed_roles[ $role_slug ] ) ) {
		return;
	}

	$allowed_pages   = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) $allowed_pages ) ) ) );
	$view_only_pages = array_values( array_diff( array_unique( array_filter( array_map( 'sanitize_text_field', (array) $view_only_pages ) ) ), $allowed_pages ) );
	$rules           = blueworx_get_role_backend_page_rules();

	$rules[ $role_slug ] = array(
		'allowed'   => $allowed_pages,
		'view_only' => $view_only_pages,
	);

	update_option( 'blueworx_role_backend_page_rules', $rules, false );
}

/**
 * Gets backend page rules for one role.
 *
 * @param string $role_slug Role slug.
 * @return array Role backend page rules.
 */
function blueworx_get_role_backend_page_rule_set( $role_slug ) {
	$rules = blueworx_get_role_backend_page_rules();

	if ( ! isset( $rules[ $role_slug ] ) || ! is_array( $rules[ $role_slug ] ) ) {
		return array(
			'allowed'   => array(),
			'view_only' => array(),
			'is_saved'  => false,
		);
	}

	return array(
		'allowed'   => isset( $rules[ $role_slug ]['allowed'] ) && is_array( $rules[ $role_slug ]['allowed'] ) ? array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $rules[ $role_slug ]['allowed'] ) ) ) ) : array(),
		'view_only' => isset( $rules[ $role_slug ]['view_only'] ) && is_array( $rules[ $role_slug ]['view_only'] ) ? array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $rules[ $role_slug ]['view_only'] ) ) ) ) : array(),
		'is_saved'  => true,
	);
}

/**
 * Gets backend pages currently available on this site.
 *
 * @return array Backend page groups.
 */
function blueworx_get_backend_page_groups() {
	global $menu, $submenu;

	$groups = array();

	foreach ( (array) $menu as $menu_item ) {
		$group_label = isset( $menu_item[0] ) ? blueworx_clean_admin_label( $menu_item[0] ) : '';
		$group_slug  = isset( $menu_item[2] ) ? sanitize_text_field( (string) $menu_item[2] ) : '';
		$group_cap   = isset( $menu_item[1] ) ? sanitize_text_field( (string) $menu_item[1] ) : 'read';

		if ( '' === $group_label || '' === $group_slug || 0 === strpos( $group_slug, 'separator' ) ) {
			continue;
		}

		if ( in_array( $group_slug, blueworx_get_role_backend_locked_pages(), true ) ) {
			continue;
		}

		if ( ! isset( $groups[ $group_slug ] ) ) {
			$groups[ $group_slug ] = array(
				'label' => $group_label,
				'items' => array(),
			);
		}

		if ( ! empty( $submenu[ $group_slug ] ) && is_array( $submenu[ $group_slug ] ) ) {
			foreach ( $submenu[ $group_slug ] as $submenu_item ) {
				$page_label = isset( $submenu_item[0] ) ? blueworx_clean_admin_label( $submenu_item[0] ) : '';
				$page_cap   = isset( $submenu_item[1] ) ? sanitize_text_field( (string) $submenu_item[1] ) : $group_cap;
				$page_slug  = isset( $submenu_item[2] ) ? sanitize_text_field( (string) $submenu_item[2] ) : '';

				if ( '' === $page_label || '' === $page_slug || in_array( $page_slug, blueworx_get_role_backend_locked_pages(), true ) ) {
					continue;
				}

				$groups[ $group_slug ]['items'][ $page_slug ] = blueworx_backend_page_details( $page_label, $page_cap, $group_label );
			}
		} else {
			$groups[ $group_slug ]['items'][ $group_slug ] = blueworx_backend_page_details( $group_label, $group_cap, $group_label );
		}
	}

	foreach ( $groups as $group_slug => $group ) {
		if ( empty( $group['items'] ) ) {
			unset( $groups[ $group_slug ] );
			continue;
		}

		uasort( $groups[ $group_slug ]['items'], 'blueworx_sort_role_editor_items_by_label' );
	}

	uasort( $groups, 'blueworx_sort_role_editor_items_by_label' );
	blueworx_store_backend_page_map( $groups );

	return $groups;
}

/**
 * Gets backend pages that must stay protected.
 *
 * @return array Locked page slugs.
 */
function blueworx_get_role_backend_locked_pages() {
	return array(
		'blueworx-project-wordpress-labs',
		'blueworx-edit-role',
		'blueworx-cache',
		'blueworx-menu-toggle',
	);
}

/**
 * Formats backend page details for the role editor.
 *
 * @param string $label      Page label.
 * @param string $capability Required capability.
 * @param string $group      Group label.
 * @return array Page details.
 */
function blueworx_backend_page_details( $label, $capability, $group ) {
	return array(
		'label'       => $label,
		'description' => sprintf(
			/* translators: %s: Admin section name. */
			__( 'Backend page in %s.', 'blueworx-project-wordpress-labs' ),
			$group
		),
		'capability'  => $capability,
	);
}

/**
 * Saves the current backend page map for permission checks.
 *
 * @param array $groups Backend page groups.
 * @return void
 */
function blueworx_store_backend_page_map( $groups ) {
	$map = array();

	foreach ( $groups as $group_label => $group ) {
		foreach ( $group['items'] as $slug => $page ) {
			$map[ $slug ] = array(
				'label'      => $page['label'],
				'capability' => $page['capability'],
				'group'      => isset( $group['label'] ) ? $group['label'] : $group_label,
			);
		}
	}

	if ( ! empty( $map ) ) {
		update_option( 'blueworx_backend_page_map', $map, false );
	}
}

/**
 * Gets the saved backend page map.
 *
 * @return array Backend page map.
 */
function blueworx_get_backend_page_map() {
	$map = get_option( 'blueworx_backend_page_map', array() );

	return is_array( $map ) ? $map : array();
}

/**
 * Sorts role editor items by label.
 *
 * @param array $first  First item.
 * @param array $second Second item.
 * @return int Sort result.
 */
function blueworx_sort_role_editor_items_by_label( $first, $second ) {
	return strnatcasecmp( $first['label'], $second['label'] );
}

/**
 * Cleans a WordPress admin menu label.
 *
 * @param string $label Menu label.
 * @return string Clean label.
 */
function blueworx_clean_admin_label( $label ) {
	$label = wp_strip_all_tags( (string) $label );
	$label = preg_replace( '/\s*\d+\s*$/', '', $label );

	return trim( preg_replace( '/\s+/', ' ', $label ) );
}

/**
 * Checks whether one role has a permission.
 *
 * @param string $role_slug  Role slug.
 * @param string $capability Capability name.
 * @return bool True when the role has the permission.
 */
function blueworx_role_has_capability( $role_slug, $capability ) {
	$role = get_role( $role_slug );

	if ( ! $role ) {
		return false;
	}

	if ( 'read' === $capability ) {
		return true;
	}

	return ! empty( $role->capabilities[ $capability ] );
}

/**
 * Gets backend page groups split by state for one role.
 *
 * @param string $role_slug Role slug.
 * @param array  $groups    Backend page groups.
 * @return array Backend page groups by state.
 */
function blueworx_get_role_backend_page_groups_by_state( $role_slug, $groups ) {
	$rules  = blueworx_get_role_backend_page_rule_set( $role_slug );
	$states = array(
		'available' => array(),
		'allowed'   => array(),
		'view_only' => array(),
	);

	foreach ( $groups as $group_slug => $group ) {
		foreach ( $group['items'] as $page_slug => $page ) {
			if ( $rules['is_saved'] ) {
				if ( in_array( $page_slug, $rules['allowed'], true ) ) {
					$state = 'allowed';
				} elseif ( in_array( $page_slug, $rules['view_only'], true ) ) {
					$state = 'view_only';
				} else {
					$state = 'available';
				}
			} else {
				$state = blueworx_role_has_capability( $role_slug, $page['capability'] ) ? 'allowed' : 'available';
			}

			if ( ! isset( $states[ $state ][ $group_slug ] ) ) {
				$states[ $state ][ $group_slug ] = array(
					'label' => $group['label'],
					'items' => array(),
				);
			}

			$states[ $state ][ $group_slug ]['items'][ $page_slug ] = $page;
		}
	}

	return $states;
}

/**
 * Gets capability groups split by state for one role.
 *
 * @param string $role_slug    Role slug.
 * @param array  $capabilities Capability details.
 * @return array Capability groups by state.
 */
function blueworx_get_role_capability_groups_by_state( $role_slug, $capabilities ) {
	$enabled = blueworx_get_role_enabled_capabilities( $role_slug );
	$states  = array(
		'available' => array(),
		'allowed'   => array(),
	);

	foreach ( $capabilities as $capability => $details ) {
		$group = blueworx_get_capability_group_label( $capability );
		$state = in_array( $capability, $enabled, true ) ? 'allowed' : 'available';

		if ( ! isset( $states[ $state ][ $group ] ) ) {
			$states[ $state ][ $group ] = array(
				'label' => $group,
				'items' => array(),
			);
		}

		$states[ $state ][ $group ]['items'][ $capability ] = $details;
	}

	return $states;
}

/**
 * Gets role editor settings ready for export.
 *
 * @return array Export data.
 */
function blueworx_get_role_settings_export_data() {
	$roles         = blueworx_get_managed_roles();
	$capabilities  = blueworx_get_editable_capabilities();
	$backend_pages = blueworx_get_backend_page_groups();
	$export_roles  = array();

	foreach ( $roles as $role_slug => $role_label ) {
		$export_roles[ $role_slug ] = array(
			'label'               => $role_label,
			'capability_groups'   => blueworx_get_role_capability_groups_by_state( $role_slug, $capabilities ),
			'backend_page_groups' => blueworx_get_role_backend_page_groups_by_state( $role_slug, $backend_pages ),
			'raw'                 => array(
				'allowed_capabilities' => blueworx_get_role_enabled_capabilities( $role_slug ),
				'backend_page_rules'   => blueworx_get_role_backend_page_rule_set( $role_slug ),
			),
		);
	}

	return array(
		'plugin'             => 'blueworx-project-wordpress-labs',
		'version'            => BLUEWORX_LABS_VERSION,
		'exported_at'        => current_time( 'mysql' ),
		'roles'              => $export_roles,
		'available_features' => array(
			'capabilities'  => $capabilities,
			'backend_pages' => $backend_pages,
		),
	);
}

/**
 * Gets the group name for a permission.
 *
 * @param string $capability Capability name.
 * @return string Group label.
 */
function blueworx_get_capability_group_label( $capability ) {
	if ( false !== strpos( $capability, 'elementor' ) ) {
		return __( 'Elementor', 'blueworx-project-wordpress-labs' );
	}

	if ( false !== strpos( $capability, 'page' ) ) {
		return __( 'Pages', 'blueworx-project-wordpress-labs' );
	}

	if ( false !== strpos( $capability, 'post' ) ) {
		return __( 'Posts', 'blueworx-project-wordpress-labs' );
	}

	if ( false !== strpos( $capability, 'user' ) ) {
		return __( 'Users', 'blueworx-project-wordpress-labs' );
	}

	if ( false !== strpos( $capability, 'plugin' ) ) {
		return __( 'Plugins', 'blueworx-project-wordpress-labs' );
	}

	if ( false !== strpos( $capability, 'theme' ) ) {
		return __( 'Themes', 'blueworx-project-wordpress-labs' );
	}

	if ( false !== strpos( $capability, 'file' ) || 'upload_files' === $capability ) {
		return __( 'Media', 'blueworx-project-wordpress-labs' );
	}

	if ( false !== strpos( $capability, 'comment' ) ) {
		return __( 'Comments', 'blueworx-project-wordpress-labs' );
	}

	if ( in_array( $capability, array( 'manage_options', 'edit_dashboard', 'update_core', 'import', 'export' ), true ) ) {
		return __( 'Settings and Tools', 'blueworx-project-wordpress-labs' );
	}

	return __( 'General', 'blueworx-project-wordpress-labs' );
}

/**
 * Checks whether a user has one of the BlueWorx roles.
 *
 * @param int $user_id User ID.
 * @return bool True when the user has a BlueWorx role.
 */
function blueworx_user_has_managed_role( $user_id ) {
	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return false;
	}

	return (bool) array_intersect( array_keys( blueworx_get_managed_roles() ), (array) $user->roles );
}

/**
 * Gets BlueWorx roles assigned to a user.
 *
 * @param int $user_id User ID.
 * @return array BlueWorx role slugs.
 */
function blueworx_get_user_managed_roles( $user_id ) {
	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return array();
	}

	return array_values( array_intersect( array_keys( blueworx_get_managed_roles() ), (array) $user->roles ) );
}

/**
 * Gets the current backend page candidates.
 *
 * @param string $url Optional admin URL.
 * @return array Possible backend page slugs.
 */
function blueworx_get_current_backend_page_candidates( $url = '' ) {
	$candidates = array();

	if ( '' === $url ) {
		$server_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$url                = $server_request_uri;
	}

	$path  = wp_parse_url( $url, PHP_URL_PATH );
	$query = wp_parse_url( $url, PHP_URL_QUERY );
	$args  = array();
	$file  = $path ? basename( $path ) : '';

	if ( $query ) {
		wp_parse_str( $query, $args );
	}

	if ( isset( $args['page'] ) && '' !== $args['page'] ) {
		$page         = sanitize_text_field( (string) $args['page'] );
		$candidates[] = $page;
		$candidates[] = 'admin.php?page=' . $page;
	}

	if ( isset( $args['post_type'] ) && '' !== $args['post_type'] && '' !== $file ) {
		$post_type    = sanitize_text_field( (string) $args['post_type'] );
		$candidates[] = $file . '?post_type=' . $post_type;
		$candidates[] = 'edit.php?post_type=' . $post_type;
	}

	if ( isset( $args['taxonomy'] ) && '' !== $args['taxonomy'] && '' !== $file ) {
		$taxonomy     = sanitize_text_field( (string) $args['taxonomy'] );
		$candidates[] = $file . '?taxonomy=' . $taxonomy;
	}

	if ( in_array( $file, array( 'post.php', 'post-new.php' ), true ) ) {
		$post_type = isset( $args['post_type'] ) ? sanitize_text_field( (string) $args['post_type'] ) : '';

		if ( '' === $post_type && isset( $args['post'] ) ) {
			$post = get_post( absint( $args['post'] ) );

			if ( $post ) {
				$post_type = $post->post_type;
			}
		}

		if ( '' !== $post_type && 'post' !== $post_type ) {
			$candidates[] = 'edit.php?post_type=' . $post_type;
		} else {
			$candidates[] = 'edit.php';
		}
	}

	if ( '' !== $file ) {
		$candidates[] = $file;
	}

	return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $candidates ) ) ) );
}

/**
 * Gets a backend page state for one role.
 *
 * @param string $role_slug Role slug.
 * @param string $page_slug Backend page slug.
 * @return string Page state.
 */
function blueworx_get_role_backend_page_state( $role_slug, $page_slug ) {
	$rules = blueworx_get_role_backend_page_rule_set( $role_slug );

	if ( $rules['is_saved'] ) {
		if ( in_array( $page_slug, $rules['allowed'], true ) ) {
			return 'allowed';
		}

		if ( in_array( $page_slug, $rules['view_only'], true ) ) {
			return 'view_only';
		}

		return 'available';
	}

	$page_map = blueworx_get_backend_page_map();

	if ( isset( $page_map[ $page_slug ] ) && blueworx_role_has_capability( $role_slug, $page_map[ $page_slug ]['capability'] ) ) {
		return 'allowed';
	}

	return 'available';
}

/**
 * Gets the strongest backend page state for a user.
 *
 * @param int   $user_id    User ID.
 * @param array $candidates Backend page candidates.
 * @return string Page state.
 */
function blueworx_get_user_backend_page_state( $user_id, $candidates ) {
	$roles      = blueworx_get_user_managed_roles( $user_id );
	$best_state = 'available';

	if ( empty( $roles ) || empty( $candidates ) ) {
		return 'allowed';
	}

	foreach ( $roles as $role_slug ) {
		foreach ( $candidates as $candidate ) {
			$state = blueworx_get_role_backend_page_state( $role_slug, $candidate );

			if ( 'allowed' === $state ) {
				return 'allowed';
			}

			if ( 'view_only' === $state ) {
				$best_state = 'view_only';
			}
		}
	}

	return $best_state;
}

/**
 * Gets backend page capabilities selected for a user.
 *
 * @param int $user_id User ID.
 * @return array Capability names.
 */
function blueworx_get_user_selected_backend_page_capabilities( $user_id ) {
	$roles    = blueworx_get_user_managed_roles( $user_id );
	$page_map = blueworx_get_backend_page_map();
	$caps     = array( 'read' );

	foreach ( $roles as $role_slug ) {
		$rules = blueworx_get_role_backend_page_rule_set( $role_slug );

		foreach ( array_merge( $rules['allowed'], $rules['view_only'] ) as $page_slug ) {
			if ( isset( $page_map[ $page_slug ]['capability'] ) ) {
				$caps[] = $page_map[ $page_slug ]['capability'];
			}
		}
	}

	return array_values( array_unique( array_filter( $caps ) ) );
}

/**
 * Grants only the capabilities needed to open selected backend pages.
 *
 * @param array $allcaps All user capabilities.
 * @param array $caps    Required primitive capabilities.
 * @param array $args    Capability check details.
 * @param mixed $user    User object.
 * @return array Updated capabilities.
 */
function blueworx_grant_selected_backend_page_capabilities( $allcaps, $caps, $args, $user ) {
	$user_id = isset( $user->ID ) ? (int) $user->ID : 0;

	if ( ! is_admin() || ! $user_id || ! blueworx_user_has_managed_role( $user_id ) ) {
		return $allcaps;
	}

	$requested_capability = isset( $args[0] ) ? (string) $args[0] : '';
	$selected_caps        = blueworx_get_user_selected_backend_page_capabilities( $user_id );

	if ( in_array( $requested_capability, $selected_caps, true ) ) {
		$allcaps[ $requested_capability ] = true;
	}

	return $allcaps;
}
add_filter( 'user_has_cap', 'blueworx_grant_selected_backend_page_capabilities', 10, 4 );

/**
 * Hides unavailable backend pages from BlueWorx roles.
 *
 * @return void
 */
function blueworx_apply_role_backend_page_visibility() {
	if ( ! is_admin() || ! is_user_logged_in() || ! blueworx_user_has_managed_role( get_current_user_id() ) ) {
		return;
	}

	global $menu, $submenu;

	foreach ( (array) $submenu as $parent_slug => $submenu_items ) {
		foreach ( (array) $submenu_items as $index => $submenu_item ) {
			$page_slug = isset( $submenu_item[2] ) ? sanitize_text_field( (string) $submenu_item[2] ) : '';

			if ( '' !== $page_slug && 'available' === blueworx_get_user_backend_page_state( get_current_user_id(), array( $page_slug ) ) ) {
				unset( $submenu[ $parent_slug ][ $index ] );
			}
		}
	}

	foreach ( (array) $menu as $index => $menu_item ) {
		$page_slug = isset( $menu_item[2] ) ? sanitize_text_field( (string) $menu_item[2] ) : '';

		if ( '' === $page_slug || 0 === strpos( $page_slug, 'separator' ) ) {
			continue;
		}

		$has_visible_submenu = ! empty( $submenu[ $page_slug ] );

		if ( ! $has_visible_submenu && 'available' === blueworx_get_user_backend_page_state( get_current_user_id(), array( $page_slug ) ) ) {
			unset( $menu[ $index ] );
		}
	}
}
add_action( 'admin_menu', 'blueworx_apply_role_backend_page_visibility', 1001 );

/**
 * Checks whether the request is likely to change data.
 *
 * @return bool True when the request should be blocked for view-only pages.
 */
function blueworx_is_backend_change_request() {
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		return true;
	}

	$change_keys = array( 'action', 'action2', '_wpnonce', 'delete', 'delete_all', 'bulk_action', 'doaction', 'trashed', 'untrashed' );

	foreach ( $change_keys as $key ) {
		if ( ! isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only presence check used to classify the current request; no data is processed or persisted here, and the state-changing handlers this feeds into (admin-post.php, admin-ajax.php, options.php, update.php) perform their own nonce verification.
			continue;
		}

		$value = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Value is only used to decide whether the request looks like a data-changing request (for the view-only-role gate below); it is never processed, saved, or acted upon, so no nonce check applies here.

		if ( '' !== $value && '-1' !== $value ) {
			return true;
		}
	}

	return false;
}

/**
 * Checks whether candidates are WordPress action endpoints.
 *
 * @param array $candidates Backend page candidates.
 * @return bool True when the request is for an action endpoint.
 */
function blueworx_is_backend_action_endpoint( $candidates ) {
	return (bool) array_intersect(
		$candidates,
		array(
			'admin-ajax.php',
			'admin-post.php',
			'async-upload.php',
			'options.php',
			'update.php',
		)
	);
}

/**
 * Returns the request referer only when it is same-origin with this site.
 *
 * The backend page gate derives a request's originating page from the referer.
 * The referer header is client-suppliable, so an off-site value must never be
 * trusted for an access decision — this returns '' in that case, which the gate
 * treats as "no trusted origin" and fails closed. (A same-site referer can
 * still be crafted by an authenticated managed-role user; a full redesign of
 * this gate that does not rely on the referer is tracked separately.)
 *
 * @return string Trusted same-site referer, or '' when absent/off-site.
 */
function blueworx_get_same_site_referer() {
	$referer = wp_get_referer();

	if ( ! $referer ) {
		return '';
	}

	$referer_host = wp_parse_url( $referer, PHP_URL_HOST );
	$home_host    = wp_parse_url( home_url(), PHP_URL_HOST );

	if ( ! $referer_host || strtolower( $referer_host ) !== strtolower( (string) $home_host ) ) {
		return '';
	}

	return $referer;
}

/**
 * Blocks unavailable and view-only backend page changes.
 *
 * @return void
 */
function blueworx_block_restricted_backend_page_requests() {
	if ( ! is_admin() || ! is_user_logged_in() || ! blueworx_user_has_managed_role( get_current_user_id() ) ) {
		return;
	}

	$current_candidates = blueworx_get_current_backend_page_candidates();
	$current_state      = blueworx_get_user_backend_page_state( get_current_user_id(), $current_candidates );
	$referrer           = blueworx_get_same_site_referer();
	$referrer_state     = $referrer ? blueworx_get_user_backend_page_state( get_current_user_id(), blueworx_get_current_backend_page_candidates( $referrer ) ) : 'available';
	$is_action_endpoint = blueworx_is_backend_action_endpoint( $current_candidates );

	if ( $is_action_endpoint && blueworx_is_backend_change_request() ) {
		if ( 'view_only' === $referrer_state ) {
			wp_die( esc_html__( 'This backend page is view only for your role. Changes are not allowed.', 'blueworx-project-wordpress-labs' ) );
		}

		if ( 'available' === $referrer_state ) {
			wp_die( esc_html__( 'You do not have access to this backend page.', 'blueworx-project-wordpress-labs' ) );
		}

		return;
	}

	if ( ! $is_action_endpoint && 'available' === $current_state && ! empty( $current_candidates ) ) {
		wp_die( esc_html__( 'You do not have access to this backend page.', 'blueworx-project-wordpress-labs' ) );
	}

	if ( blueworx_is_backend_change_request() && ( 'view_only' === $current_state || 'view_only' === $referrer_state ) ) {
		wp_die( esc_html__( 'This backend page is view only for your role. Changes are not allowed.', 'blueworx-project-wordpress-labs' ) );
	}
}
add_action( 'admin_init', 'blueworx_block_restricted_backend_page_requests', 1 );

/**
 * Shows a notice on view-only backend pages.
 *
 * @return void
 */
function blueworx_show_view_only_backend_page_notice() {
	if ( ! is_admin() || ! is_user_logged_in() || ! blueworx_user_has_managed_role( get_current_user_id() ) ) {
		return;
	}

	if ( 'view_only' !== blueworx_get_user_backend_page_state( get_current_user_id(), blueworx_get_current_backend_page_candidates() ) ) {
		return;
	}
	?>
	<div class="notice notice-warning">
		<p><?php esc_html_e( 'This page is view only for your role. You can look, but changes will be blocked.', 'blueworx-project-wordpress-labs' ); ?></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'blueworx_show_view_only_backend_page_notice' );

/**
 * Blocks BlueWorx roles from editing Elementor templates unless allowed.
 *
 * @param array  $caps    Required capabilities.
 * @param string $cap     Capability being checked.
 * @param int    $user_id User ID.
 * @param array  $args    Extra check details.
 * @return array Required capabilities.
 */
function blueworx_protect_elementor_templates( $caps, $cap, $user_id, $args ) {
	if ( ! in_array( $cap, array( 'delete_post', 'edit_post', 'read_post' ), true ) || empty( $args[0] ) ) {
		return $caps;
	}

	$post = get_post( (int) $args[0] );

	if ( ! $post || 'elementor_library' !== $post->post_type || ! blueworx_user_has_managed_role( $user_id ) ) {
		return $caps;
	}

	if ( user_can( $user_id, 'blueworx_edit_elementor_templates' ) ) {
		return $caps;
	}

	return array( 'do_not_allow' );
}
add_filter( 'map_meta_cap', 'blueworx_protect_elementor_templates', 10, 4 );
