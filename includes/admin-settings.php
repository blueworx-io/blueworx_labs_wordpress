<?php
/**
 * BlueWorx admin screens.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the BlueWorx admin menu.
 *
 * @return void
 */
function blueworx_register_settings_page() {
	add_menu_page(
		esc_html__( 'Enhancements', 'blueworx-project-wordpress-labs' ),
		esc_html__( 'BlueWorx', 'blueworx-project-wordpress-labs' ),
		'manage_options',
		'blueworx-project-wordpress-labs',
		'blueworx_render_enhancements_page',
		'dashicons-schedule',
		58
	);

	add_submenu_page(
		'blueworx-project-wordpress-labs',
		esc_html__( 'Enhancements', 'blueworx-project-wordpress-labs' ),
		esc_html__( 'Enhancements', 'blueworx-project-wordpress-labs' ),
		'manage_options',
		'blueworx-project-wordpress-labs',
		'blueworx_render_enhancements_page'
	);

	add_submenu_page(
		'blueworx-project-wordpress-labs',
		esc_html__( 'Edit Menu', 'blueworx-project-wordpress-labs' ),
		esc_html__( 'Edit Menu', 'blueworx-project-wordpress-labs' ),
		'manage_options',
		'blueworx-edit-menu',
		'blueworx_render_edit_menu_page'
	);

	add_submenu_page(
		'blueworx-project-wordpress-labs',
		esc_html__( 'Edit Role', 'blueworx-project-wordpress-labs' ),
		esc_html__( 'Edit Role', 'blueworx-project-wordpress-labs' ),
		'manage_options',
		'blueworx-edit-role',
		'blueworx_render_edit_role_page'
	);

	add_submenu_page(
		'blueworx-project-wordpress-labs',
		esc_html__( 'Cache', 'blueworx-project-wordpress-labs' ),
		esc_html__( 'Cache', 'blueworx-project-wordpress-labs' ),
		'manage_options',
		'blueworx-cache',
		'blueworx_render_cache_page'
	);
}
add_action( 'admin_menu', 'blueworx_register_settings_page' );

/**
 * Saves the admin menu settings from BlueWorx > Edit Menu.
 *
 * @return void
 */
function blueworx_save_edit_menu_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-project-wordpress-labs' ) );
	}

	check_admin_referer( 'blueworx_save_admin_menu_order' );

	$raw_order   = isset( $_POST['blueworx_admin_menu_order'] ) ? (array) wp_unslash( $_POST['blueworx_admin_menu_order'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$raw_hidden  = isset( $_POST['blueworx_hidden_admin_menu_items'] ) ? (array) wp_unslash( $_POST['blueworx_hidden_admin_menu_items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$raw_toggled = isset( $_POST['blueworx_toggled_admin_menu_items'] ) ? (array) wp_unslash( $_POST['blueworx_toggled_admin_menu_items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$order       = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $raw_order ) ) ) );
	$locked      = blueworx_get_locked_admin_menu_items();
	$hidden      = array_values( array_diff( array_unique( array_filter( array_map( 'sanitize_text_field', $raw_hidden ) ) ), $locked ) );
	$toggled     = array_values( array_diff( array_unique( array_filter( array_map( 'sanitize_text_field', $raw_toggled ) ) ), $locked, $hidden ) );

	update_option( 'blueworx_admin_menu_order', $order );
	update_option( 'blueworx_hidden_admin_menu_items', array_values( array_unique( $hidden ) ) );
	update_option( 'blueworx_toggled_admin_menu_items', array_values( array_unique( $toggled ) ) );
	set_transient( 'blueworx_admin_menu_order_notice', __( 'Menu settings saved.', 'blueworx-project-wordpress-labs' ), 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-edit-menu' ) );
	exit;
}
add_action( 'admin_post_blueworx_save_admin_menu_order', 'blueworx_save_edit_menu_page' );

/**
 * Saves BlueWorx managed role permissions.
 *
 * @return void
 */
function blueworx_save_edit_role_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-project-wordpress-labs' ) );
	}

	check_admin_referer( 'blueworx_save_role_capabilities' );

	$posted_roles  = isset( $_POST['blueworx_role_caps'] ) ? (array) wp_unslash( $_POST['blueworx_role_caps'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$posted_pages  = isset( $_POST['blueworx_role_pages'] ) ? (array) wp_unslash( $_POST['blueworx_role_pages'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$managed_roles = blueworx_get_managed_roles();

	blueworx_ensure_managed_roles();

	foreach ( $managed_roles as $role_slug => $role_label ) {
		$capabilities = isset( $posted_roles[ $role_slug ] ) && is_array( $posted_roles[ $role_slug ] ) ? $posted_roles[ $role_slug ] : array();
		$pages        = isset( $posted_pages[ $role_slug ] ) && is_array( $posted_pages[ $role_slug ] ) ? $posted_pages[ $role_slug ] : array();
		$allowed      = isset( $pages['allowed'] ) && is_array( $pages['allowed'] ) ? $pages['allowed'] : array();
		$view_only    = isset( $pages['view_only'] ) && is_array( $pages['view_only'] ) ? $pages['view_only'] : array();

		blueworx_update_managed_role_capabilities( $role_slug, $capabilities );
		blueworx_update_role_backend_page_rules( $role_slug, $allowed, $view_only );
	}

	set_transient( 'blueworx_role_editor_notice', __( 'Role permissions saved.', 'blueworx-project-wordpress-labs' ), 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-edit-role' ) );
	exit;
}
add_action( 'admin_post_blueworx_save_role_capabilities', 'blueworx_save_edit_role_page' );

/**
 * Exports the BlueWorx role editor settings as JSON.
 *
 * @return void
 */
function blueworx_export_role_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-project-wordpress-labs' ) );
	}

	check_admin_referer( 'blueworx_export_role_settings' );

	$export_data = blueworx_get_role_settings_export_data();
	$json        = wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

	if ( false === $json ) {
		wp_die( esc_html__( 'The role settings could not be exported.', 'blueworx-project-wordpress-labs' ) );
	}

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=blueworx-role-settings-' . gmdate( 'Y-m-d-His' ) . '.json' );
	header( 'Content-Length: ' . strlen( $json ) );

	echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}
add_action( 'admin_post_blueworx_export_role_settings', 'blueworx_export_role_settings' );

/**
 * Saves the Application Passwords visibility option.
 *
 * @return void
 */
function blueworx_save_application_passwords_setting() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-project-wordpress-labs' ) );
	}

	check_admin_referer( 'blueworx_save_application_passwords_setting' );

	$show_application_passwords = isset( $_POST['blueworx_show_application_passwords'] ) ? '1' : '0'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	update_option( 'blueworx_show_application_passwords', $show_application_passwords );
	set_transient( 'blueworx_labs_notice', __( 'Application Passwords setting saved.', 'blueworx-project-wordpress-labs' ), 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-project-wordpress-labs' ) );
	exit;
}
add_action( 'admin_post_blueworx_save_application_passwords_setting', 'blueworx_save_application_passwords_setting' );

/**
 * Gets all roles that can be selected for site protection.
 *
 * @return array Role labels keyed by role slug.
 */
function blueworx_get_site_protection_role_choices() {
	$choices  = array();
	$wp_roles = wp_roles();

	foreach ( $wp_roles->roles as $role_slug => $role ) {
		$choices[ $role_slug ] = translate_user_role( $role['name'] );
	}

	natcasesort( $choices );

	return $choices;
}

/**
 * Gets a saved site protection toggle.
 *
 * @param string $area Protected area.
 * @return bool True when enabled.
 */
function blueworx_site_protection_enabled( $area ) {
	return '1' === get_option( 'blueworx_' . $area . '_protection_enabled', '0' );
}

/**
 * Gets saved site protection roles.
 *
 * @param string $area Protected area.
 * @return array Role slugs.
 */
function blueworx_get_site_protection_roles( $area ) {
	$roles   = get_option( 'blueworx_' . $area . '_protection_roles', array() );
	$choices = blueworx_get_site_protection_role_choices();

	if ( ! is_array( $roles ) ) {
		return array();
	}

	return array_values( array_intersect( array_unique( array_map( 'sanitize_key', $roles ) ), array_keys( $choices ) ) );
}

/**
 * Saves frontend and backend site protection settings.
 *
 * @return void
 */
function blueworx_save_site_protection_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-project-wordpress-labs' ) );
	}

	check_admin_referer( 'blueworx_save_site_protection_settings' );

	$choices = blueworx_get_site_protection_role_choices();

	foreach ( array( 'frontend', 'backend' ) as $area ) {
		$enabled = isset( $_POST[ 'blueworx_' . $area . '_protection_enabled' ] ) ? '1' : '0'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$roles   = isset( $_POST[ 'blueworx_' . $area . '_protection_roles' ] ) ? (array) wp_unslash( $_POST[ 'blueworx_' . $area . '_protection_roles' ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$roles   = array_values( array_intersect( array_unique( array_map( 'sanitize_key', $roles ) ), array_keys( $choices ) ) );

		update_option( 'blueworx_' . $area . '_protection_enabled', $enabled );
		update_option( 'blueworx_' . $area . '_protection_roles', $roles, false );
	}

	set_transient( 'blueworx_labs_notice', __( 'Site Protection settings saved.', 'blueworx-project-wordpress-labs' ), 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-project-wordpress-labs' ) );
	exit;
}
add_action( 'admin_post_blueworx_save_site_protection_settings', 'blueworx_save_site_protection_settings' );

/**
 * Gets the active plugin features shown on the Enhancements page.
 *
 * @return array Feature list.
 */
function blueworx_get_active_features() {
	return array(
		array(
			'title'       => __( 'Custom login URL', 'blueworx-project-wordpress-labs' ),
			'description' => __( 'Replaces the standard WordPress login address with the BlueWorx login address.', 'blueworx-project-wordpress-labs' ),
			'action'      => 'login_url',
		),
		array(
			'title'       => __( 'Login protection', 'blueworx-project-wordpress-labs' ),
			'description' => __( 'Blocks direct visits to the default WordPress login and admin login paths.', 'blueworx-project-wordpress-labs' ),
			'action'      => '',
		),
		array(
			'title'       => __( 'Comments disabled', 'blueworx-project-wordpress-labs' ),
			'description' => __( 'Turns comments off and removes comment areas from the admin screens.', 'blueworx-project-wordpress-labs' ),
			'action'      => '',
		),
		array(
			'title'       => __( 'Email notifications reduced', 'blueworx-project-wordpress-labs' ),
			'description' => __( 'Stops extra admin emails for user, password, plugin, and theme changes.', 'blueworx-project-wordpress-labs' ),
			'action'      => '',
		),
		array(
			'title'       => __( 'Profile cleanup', 'blueworx-project-wordpress-labs' ),
			'description' => __( 'Hides unused profile options, Elementor AI, and Elementor Notes.', 'blueworx-project-wordpress-labs' ),
			'action'      => '',
		),
		array(
			'title'       => __( 'Application Passwords', 'blueworx-project-wordpress-labs' ),
			'description' => __( 'Hidden by default. When enabled, only admins can see Application Passwords on admin user profiles.', 'blueworx-project-wordpress-labs' ),
			'action'      => 'application_passwords',
		),
		array(
			'title'       => __( 'Site Protection', 'blueworx-project-wordpress-labs' ),
			'description' => __( 'Only lets logged-in users with selected roles view the frontend or backend.', 'blueworx-project-wordpress-labs' ),
			'action'      => 'site_protection',
		),
		array(
			'title'       => __( 'Automatic cache refresh', 'blueworx-project-wordpress-labs' ),
			'description' => __( 'Refreshes cache when pages or posts are changed.', 'blueworx-project-wordpress-labs' ),
			'action'      => '',
		),
		array(
			'title'       => __( 'Manual cache refresh', 'blueworx-project-wordpress-labs' ),
			'description' => __( 'Adds a Cache page where cache can be refreshed manually.', 'blueworx-project-wordpress-labs' ),
			'action'      => 'cache',
		),
		array(
			'title'       => __( 'Menu editor', 'blueworx-project-wordpress-labs' ),
			'description' => __( 'Lets you reorder menu items, hide them, or move them into More.', 'blueworx-project-wordpress-labs' ),
			'action'      => 'edit_menu',
		),
		array(
			'title'       => __( 'Role editor', 'blueworx-project-wordpress-labs' ),
			'description' => __( 'Adds Business Owner, External Admin, and Content Editor roles and lets you choose their permissions.', 'blueworx-project-wordpress-labs' ),
			'action'      => 'edit_role',
		),
		array(
			'title'       => __( 'Page excerpts', 'blueworx-project-wordpress-labs' ),
			'description' => __( 'Adds excerpt support to Pages, the same way Posts already have it.', 'blueworx-project-wordpress-labs' ),
			'action'      => '',
		),
	);
}

/**
 * Renders the BlueWorx Labs page.
 *
 * @return void
 */
function blueworx_render_enhancements_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-project-wordpress-labs' ) );
	}

	$custom_login_url = home_url( '/' . BLUEWORX_CUSTOM_LOGIN_SLUG . '/' );
	$features         = blueworx_get_active_features();
	$notice           = get_transient( 'blueworx_labs_notice' );
	$role_choices     = blueworx_get_site_protection_role_choices();

	if ( $notice ) {
		delete_transient( 'blueworx_labs_notice' );
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( $notice ); ?></p>
			</div>
		<?php endif; ?>
		<p><?php esc_html_e( 'This plugin is active and managing the features listed below.', 'blueworx-project-wordpress-labs' ); ?></p>

		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Feature', 'blueworx-project-wordpress-labs' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Details', 'blueworx-project-wordpress-labs' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Settings', 'blueworx-project-wordpress-labs' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $features as $feature ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $feature['title'] ); ?></th>
						<td><?php echo esc_html( $feature['description'] ); ?></td>
						<td>
							<?php if ( 'login_url' === $feature['action'] ) : ?>
								<input
									type="text"
									value="<?php echo esc_url( $custom_login_url ); ?>"
									readonly
									disabled
									class="regular-text"
								/>
								<a class="button" href="<?php echo esc_url( $custom_login_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'Open', 'blueworx-project-wordpress-labs' ); ?>
								</a>
							<?php elseif ( 'cache' === $feature['action'] ) : ?>
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=blueworx-cache' ) ); ?>">
									<?php esc_html_e( 'Open', 'blueworx-project-wordpress-labs' ); ?>
								</a>
							<?php elseif ( 'edit_menu' === $feature['action'] ) : ?>
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=blueworx-edit-menu' ) ); ?>">
									<?php esc_html_e( 'Open', 'blueworx-project-wordpress-labs' ); ?>
								</a>
							<?php elseif ( 'edit_role' === $feature['action'] ) : ?>
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=blueworx-edit-role' ) ); ?>">
									<?php esc_html_e( 'Open', 'blueworx-project-wordpress-labs' ); ?>
								</a>
							<?php elseif ( 'application_passwords' === $feature['action'] ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="blueworx_save_application_passwords_setting" />
									<?php wp_nonce_field( 'blueworx_save_application_passwords_setting' ); ?>
									<label>
										<input
											type="checkbox"
											name="blueworx_show_application_passwords"
											value="1"
											<?php checked( blueworx_show_application_passwords_for_admins() ); ?>
											onchange="this.form.submit();"
										/>
										<?php esc_html_e( 'Show for admins', 'blueworx-project-wordpress-labs' ); ?>
									</label>
								</form>
							<?php elseif ( 'site_protection' === $feature['action'] ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="blueworx_save_site_protection_settings" />
									<?php wp_nonce_field( 'blueworx_save_site_protection_settings' ); ?>
									<?php
									foreach ( array(
										'frontend' => __( 'Frontend protection', 'blueworx-project-wordpress-labs' ),
										'backend'  => __( 'Backend protection', 'blueworx-project-wordpress-labs' ),
									) as $area => $label ) :
										?>
										<?php $selected_roles = blueworx_get_site_protection_roles( $area ); ?>
										<p>
											<label>
												<input
													type="checkbox"
													name="<?php echo esc_attr( 'blueworx_' . $area . '_protection_enabled' ); ?>"
													value="1"
													<?php checked( blueworx_site_protection_enabled( $area ) ); ?>
												/>
												<?php echo esc_html( $label ); ?>
											</label>
										</p>
										<p>
											<select
												name="<?php echo esc_attr( 'blueworx_' . $area . '_protection_roles[]' ); ?>"
												multiple
												size="4"
												aria-label="<?php echo esc_attr( $label ); ?>"
											>
												<?php foreach ( $role_choices as $role_slug => $role_label ) : ?>
													<option value="<?php echo esc_attr( $role_slug ); ?>" <?php selected( in_array( $role_slug, $selected_roles, true ) ); ?>>
														<?php echo esc_html( $role_label ); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</p>
									<?php endforeach; ?>
									<p>
										<button type="submit" class="button button-primary">
											<?php esc_html_e( 'Save', 'blueworx-project-wordpress-labs' ); ?>
										</button>
									</p>
								</form>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Renders the Edit Role page.
 *
 * @return void
 */
function blueworx_render_edit_role_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-project-wordpress-labs' ) );
	}

	blueworx_ensure_managed_roles();

	$roles         = blueworx_get_managed_roles();
	$capabilities  = blueworx_get_editable_capabilities();
	$backend_pages = blueworx_get_backend_page_groups();
	$notice        = get_transient( 'blueworx_role_editor_notice' );

	if ( $notice ) {
		delete_transient( 'blueworx_role_editor_notice' );
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( $notice ); ?></p>
			</div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Drag one item or a whole group between columns. Backend pages can be Available, Allowed, or View Only.', 'blueworx-project-wordpress-labs' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="blueworx_export_role_settings" />
			<?php wp_nonce_field( 'blueworx_export_role_settings' ); ?>
			<?php submit_button( esc_html__( 'Export Settings', 'blueworx-project-wordpress-labs' ), 'secondary', 'submit', false ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="blueworx_save_role_capabilities" />
			<?php wp_nonce_field( 'blueworx_save_role_capabilities' ); ?>

			<div>
				<?php foreach ( $roles as $role_slug => $role_label ) : ?>
					<?php blueworx_render_role_editor_card( $role_slug, $role_label, $capabilities, $backend_pages ); ?>
				<?php endforeach; ?>
			</div>

			<?php submit_button( esc_html__( 'Save Role Permissions', 'blueworx-project-wordpress-labs' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Renders one role editor card.
 *
 * @param string $role_slug    Role slug.
 * @param string $role_label   Role label.
 * @param array  $capabilities Capability labels keyed by capability name.
 * @param array  $backend_pages Backend page groups.
 * @return void
 */
function blueworx_render_role_editor_card( $role_slug, $role_label, $capabilities, $backend_pages ) {
	$capability_groups = blueworx_get_role_capability_groups_by_state( $role_slug, $capabilities );
	$page_groups       = blueworx_get_role_backend_page_groups_by_state( $role_slug, $backend_pages );
	?>
	<div class="postbox closed blueworx-role-card" data-blueworx-role="<?php echo esc_attr( $role_slug ); ?>">
		<div class="postbox-header">
			<h2 class="hndle"><?php echo esc_html( $role_label ); ?></h2>
			<button type="button" class="handlediv blueworx-role-toggle" aria-expanded="false">
				<span class="screen-reader-text"><?php esc_html_e( 'Toggle panel', 'blueworx-project-wordpress-labs' ); ?></span>
				<span class="toggle-indicator" aria-hidden="true"></span>
			</button>
		</div>
		<div class="inside blueworx-role-card-body" hidden>
		<?php
		blueworx_render_role_grouped_panel(
			__( 'Permission Functions', 'blueworx-project-wordpress-labs' ),
			__( 'Controls what this role can do inside WordPress.', 'blueworx-project-wordpress-labs' ),
			$role_slug,
			'capabilities',
			array(
				'available' => __( 'Available', 'blueworx-project-wordpress-labs' ),
				'allowed'   => __( 'Allowed', 'blueworx-project-wordpress-labs' ),
			),
			$capability_groups
		);

		blueworx_render_role_grouped_panel(
			__( 'Backend Pages', 'blueworx-project-wordpress-labs' ),
			__( 'Controls which dashboard pages this role can open or only view.', 'blueworx-project-wordpress-labs' ),
			$role_slug,
			'pages',
			array(
				'available' => __( 'Available', 'blueworx-project-wordpress-labs' ),
				'allowed'   => __( 'Allowed', 'blueworx-project-wordpress-labs' ),
				'view_only' => __( 'View Only', 'blueworx-project-wordpress-labs' ),
			),
			$page_groups
		);
		?>
		</div>
	</div>
	<?php
}

/**
 * Renders grouped role editor columns.
 *
 * @param string $title       Panel title.
 * @param string $description Panel description.
 * @param string $role_slug   Role slug.
 * @param string $panel_type  Panel type.
 * @param array  $states      Column states.
 * @param array  $groups      Groups by state.
 * @return void
 */
function blueworx_render_role_grouped_panel( $title, $description, $role_slug, $panel_type, $states, $groups ) {
	$all_groups = blueworx_collect_role_editor_groups( $groups );
	?>
	<div class="blueworx-role-panel" data-blueworx-role-panel="<?php echo esc_attr( $panel_type ); ?>">
		<h3><?php echo esc_html( $title ); ?></h3>
		<p><?php echo esc_html( $description ); ?></p>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<?php foreach ( $states as $state_label ) : ?>
						<th scope="col"><?php echo esc_html( $state_label ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<tr>
					<?php foreach ( $states as $state => $state_label ) : ?>
						<td class="blueworx-role-column" data-blueworx-role-state="<?php echo esc_attr( $state ); ?>">
							<ul class="categorychecklist form-no-clear blueworx-role-group-list">
								<?php foreach ( $all_groups as $group_key => $group_label ) : ?>
									<?php $items = isset( $groups[ $state ][ $group_key ]['items'] ) ? $groups[ $state ][ $group_key ]['items'] : array(); ?>
									<?php blueworx_render_role_editor_group( $role_slug, $panel_type, $state, $group_key, $group_label, $items ); ?>
								<?php endforeach; ?>
							</ul>
						</td>
					<?php endforeach; ?>
				</tr>
			</tbody>
		</table>
		<?php if ( 'capabilities' === $panel_type ) : ?>
			<input type="hidden" name="blueworx_role_caps[<?php echo esc_attr( $role_slug ); ?>][]" value="read" />
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Collects every group used by a role editor panel.
 *
 * @param array $groups Groups by state.
 * @return array Group labels keyed by group key.
 */
function blueworx_collect_role_editor_groups( $groups ) {
	$all_groups = array();

	foreach ( $groups as $state_groups ) {
		foreach ( $state_groups as $group_key => $group ) {
			$all_groups[ $group_key ] = $group['label'];
		}
	}

	natcasesort( $all_groups );

	return $all_groups;
}

/**
 * Renders a draggable role editor group.
 *
 * @param string $role_slug  Role slug.
 * @param string $panel_type Panel type.
 * @param string $state      Column state.
 * @param string $group_key  Group key.
 * @param string $group_label Group label.
 * @param array  $items      Group items.
 * @return void
 */
function blueworx_render_role_editor_group( $role_slug, $panel_type, $state, $group_key, $group_label, $items ) {
	?>
	<li class="blueworx-role-group" data-blueworx-group="<?php echo esc_attr( $group_key ); ?>" data-blueworx-group-label="<?php echo esc_attr( $group_label ); ?>">
		<p>
			<span class="blueworx-role-group-handle" aria-hidden="true">::</span>
			<strong><?php echo esc_html( $group_label ); ?></strong>
		</p>
		<ul class="categorychecklist form-no-clear blueworx-role-item-list" data-blueworx-group="<?php echo esc_attr( $group_key ); ?>">
			<?php foreach ( $items as $item_key => $item ) : ?>
				<?php blueworx_render_role_editor_item( $role_slug, $panel_type, $state, $item_key, $item ); ?>
			<?php endforeach; ?>
		</ul>
	</li>
	<?php
}

/**
 * Renders one draggable role editor item.
 *
 * @param string $role_slug  Role slug.
 * @param string $panel_type Panel type.
 * @param string $state      Column state.
 * @param string $item_key   Item key.
 * @param array  $item       Item details.
 * @return void
 */
function blueworx_render_role_editor_item( $role_slug, $panel_type, $state, $item_key, $item ) {
	?>
	<li
		class="blueworx-role-item"
		data-blueworx-item="<?php echo esc_attr( $item_key ); ?>"
		data-blueworx-item-label="<?php echo esc_attr( $item['label'] ); ?>"
	>
		<span class="blueworx-role-item-handle" aria-hidden="true">::</span>
		<?php echo esc_html( $item['label'] ); ?>
		<p class="description"><?php echo esc_html( $item['description'] ); ?></p>
		<input type="hidden" class="blueworx-role-item-input" value="<?php echo esc_attr( $item_key ); ?>" />
	</li>
	<?php
}

/**
 * Renders the Cache page.
 *
 * @return void
 */
function blueworx_render_cache_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-project-wordpress-labs' ) );
	}

	$cache_notice  = get_transient( 'blueworx_cache_refresh_notice' );
	$breeze_active = blueworx_is_breeze_active();

	if ( $cache_notice ) {
		delete_transient( 'blueworx_cache_refresh_notice' );
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php if ( $cache_notice ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( $cache_notice ); ?></p>
			</div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Cache Refresh', 'blueworx-project-wordpress-labs' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Automatic Refresh', 'blueworx-project-wordpress-labs' ); ?></th>
				<td>
					<strong><?php esc_html_e( 'Enabled', 'blueworx-project-wordpress-labs' ); ?></strong>
					<p class="description">
						<?php esc_html_e( 'When a page or post changes, this plugin refreshes the edited page, homepage, and related listing pages.', 'blueworx-project-wordpress-labs' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Breeze Cache', 'blueworx-project-wordpress-labs' ); ?></th>
				<td>
					<strong>
						<?php echo $breeze_active ? esc_html__( 'Detected', 'blueworx-project-wordpress-labs' ) : esc_html__( 'Not detected', 'blueworx-project-wordpress-labs' ); ?>
					</strong>
					<p class="description">
						<?php esc_html_e( 'Cloudways Breeze/Varnish is used when available. WordPress cache clearing is used as a safe fallback.', 'blueworx-project-wordpress-labs' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Manual Refresh', 'blueworx-project-wordpress-labs' ); ?></th>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="blueworx_clear_cache_now" />
						<?php wp_nonce_field( 'blueworx_clear_cache_now' ); ?>
						<?php submit_button( esc_html__( 'Refresh Cache Now', 'blueworx-project-wordpress-labs' ), 'secondary', 'submit', false ); ?>
					</form>
				</td>
			</tr>
		</table>
	</div>
	<?php
}

/**
 * Renders the Edit Menu page.
 *
 * @return void
 */
function blueworx_render_edit_menu_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-project-wordpress-labs' ) );
	}

	$menu_items   = blueworx_get_editable_admin_menu_items();
	$saved_order  = blueworx_get_saved_admin_menu_order();
	$hidden       = blueworx_get_hidden_admin_menu_items();
	$toggled      = blueworx_get_toggled_admin_menu_items();
	$locked       = blueworx_get_locked_admin_menu_items();
	$notice       = get_transient( 'blueworx_admin_menu_order_notice' );
	$ordered      = array();
	$main_items   = array();
	$toggle_items = array();
	$hidden_items = array();

	if ( $notice ) {
		delete_transient( 'blueworx_admin_menu_order_notice' );
	}

	foreach ( $saved_order as $slug ) {
		if ( isset( $menu_items[ $slug ] ) ) {
			$ordered[ $slug ] = $menu_items[ $slug ];
		}
	}

	foreach ( $menu_items as $slug => $label ) {
		if ( ! isset( $ordered[ $slug ] ) ) {
			$ordered[ $slug ] = $label;
		}
	}

	foreach ( $ordered as $slug => $label ) {
		if ( in_array( $slug, $hidden, true ) ) {
			$hidden_items[ $slug ] = $label;
		} elseif ( in_array( $slug, $toggled, true ) ) {
			$toggle_items[ $slug ] = $label;
		} else {
			$main_items[ $slug ] = $label;
		}
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( $notice ); ?></p>
			</div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Drag items between Main Menu and More Menu. Use the eye icon to hide or show an item.', 'blueworx-project-wordpress-labs' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="blueworx_save_admin_menu_order" />
			<?php wp_nonce_field( 'blueworx_save_admin_menu_order' ); ?>

			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Main Menu', 'blueworx-project-wordpress-labs' ); ?></th>
						<th scope="col"><?php esc_html_e( 'More Menu', 'blueworx-project-wordpress-labs' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Hidden', 'blueworx-project-wordpress-labs' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<?php
						blueworx_render_menu_editor_section( 'main', $main_items, $locked );
						blueworx_render_menu_editor_section( 'toggle', $toggle_items, $locked );
						blueworx_render_menu_editor_section( 'hidden', $hidden_items, $locked );
						?>
					</tr>
				</tbody>
			</table>

			<?php submit_button( esc_html__( 'Save Menu Settings', 'blueworx-project-wordpress-labs' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Renders one menu editor section.
 *
 * @param string $state  Section state.
 * @param array  $items  Menu items.
 * @param array  $locked Locked slugs.
 * @return void
 */
function blueworx_render_menu_editor_section( $state, $items, $locked ) {
	?>
	<td>
		<ul class="categorychecklist form-no-clear blueworx-menu-order-list" data-blueworx-menu-section="<?php echo esc_attr( $state ); ?>">
			<?php foreach ( $items as $slug => $label ) : ?>
				<?php $is_locked = in_array( $slug, $locked, true ); ?>
				<li class="blueworx-menu-order-item" data-blueworx-menu-item="<?php echo esc_attr( $slug ); ?>">
					<span class="blueworx-menu-order-handle" aria-hidden="true">::</span>
					<?php echo esc_html( $label ); ?>
					<button
						type="button"
						class="button-link blueworx-menu-visibility-toggle"
						aria-label="<?php echo esc_attr( 'hidden' === $state ? __( 'Show menu item', 'blueworx-project-wordpress-labs' ) : __( 'Hide menu item', 'blueworx-project-wordpress-labs' ) ); ?>"
						<?php disabled( $is_locked ); ?>
					>
						<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					</button>
					<input type="hidden" class="blueworx-menu-order-input" name="blueworx_admin_menu_order[]" value="<?php echo esc_attr( $slug ); ?>" />
					<input type="hidden" class="blueworx-menu-state-input" value="<?php echo esc_attr( $slug ); ?>" />
				</li>
			<?php endforeach; ?>
		</ul>
	</td>
	<?php
}
