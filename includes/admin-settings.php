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
		esc_html__( 'Enhancements', 'blueworx-labs-wordpress' ),
		esc_html__( 'BlueWorx', 'blueworx-labs-wordpress' ),
		'manage_options',
		'blueworx-labs-wordpress',
		'blueworx_render_enhancements_page',
		'dashicons-schedule',
		58
	);

	add_submenu_page(
		'blueworx-labs-wordpress',
		esc_html__( 'Enhancements', 'blueworx-labs-wordpress' ),
		esc_html__( 'Enhancements', 'blueworx-labs-wordpress' ),
		'manage_options',
		'blueworx-labs-wordpress',
		'blueworx_render_enhancements_page'
	);

	if ( blueworx_feature_enabled( 'menu_editor' ) ) {
		add_submenu_page(
			'blueworx-labs-wordpress',
			esc_html__( 'Edit Menu', 'blueworx-labs-wordpress' ),
			esc_html__( 'Edit Menu', 'blueworx-labs-wordpress' ),
			'manage_options',
			'blueworx-edit-menu',
			'blueworx_render_edit_menu_page'
		);
	}

	if ( blueworx_feature_enabled( 'cache_manual' ) ) {
		add_submenu_page(
			'blueworx-labs-wordpress',
			esc_html__( 'Cache', 'blueworx-labs-wordpress' ),
			esc_html__( 'Cache', 'blueworx-labs-wordpress' ),
			'manage_options',
			'blueworx-cache',
			'blueworx_render_cache_page'
		);
	}
}
add_action( 'admin_menu', 'blueworx_register_settings_page' );

/**
 * Saves the admin menu settings from BlueWorx > Edit Menu.
 *
 * @return void
 */
function blueworx_save_edit_menu_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_save_admin_menu_order' );

	$raw_order  = isset( $_POST['blueworx_admin_menu_order'] ) ? (array) wp_unslash( $_POST['blueworx_admin_menu_order'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$raw_hidden = isset( $_POST['blueworx_hidden_admin_menu_items'] ) ? (array) wp_unslash( $_POST['blueworx_hidden_admin_menu_items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$order      = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $raw_order ) ) ) );
	$locked     = blueworx_get_locked_admin_menu_items();
	$hidden     = array_values( array_diff( array_unique( array_filter( array_map( 'sanitize_text_field', $raw_hidden ) ) ), $locked ) );

	update_option( 'blueworx_admin_menu_order', $order );
	update_option( 'blueworx_hidden_admin_menu_items', array_values( array_unique( $hidden ) ) );
	update_option( 'blueworx_admin_menu_customized', '1' );
	set_transient( 'blueworx_admin_menu_order_notice', __( 'Menu settings saved.', 'blueworx-labs-wordpress' ), 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-edit-menu' ) );
	exit;
}
if ( blueworx_feature_enabled( 'menu_editor' ) ) {
	add_action( 'admin_post_blueworx_save_admin_menu_order', 'blueworx_save_edit_menu_page' );
}

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
 * Saves all feature toggles and their detail settings from the settings page.
 *
 * @return void
 */
function blueworx_save_feature_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_save_feature_settings' );

	$posted = isset( $_POST['blueworx_feature'] ) ? (array) wp_unslash( $_POST['blueworx_feature'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	foreach ( array_keys( blueworx_get_feature_definitions() ) as $key ) {
		update_option( 'blueworx_feature_' . $key, isset( $posted[ $key ] ) ? '1' : '0' );
	}

	// Login detail: editable slug.
	$raw_slug = isset( $_POST['blueworx_login_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['blueworx_login_slug'] ) ) : '';
	update_option( 'blueworx_login_slug', blueworx_sanitize_login_slug( $raw_slug ) );

	// Site Protection detail: per-area enable + roles.
	$choices = blueworx_get_site_protection_role_choices();
	foreach ( array( 'frontend', 'backend' ) as $area ) {
		$enabled = isset( $_POST[ 'blueworx_' . $area . '_protection_enabled' ] ) ? '1' : '0';
		$roles   = isset( $_POST[ 'blueworx_' . $area . '_protection_roles' ] ) ? (array) wp_unslash( $_POST[ 'blueworx_' . $area . '_protection_roles' ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$roles   = array_values( array_intersect( array_unique( array_map( 'sanitize_key', $roles ) ), array_keys( $choices ) ) );

		update_option( 'blueworx_' . $area . '_protection_enabled', $enabled );
		update_option( 'blueworx_' . $area . '_protection_roles', $roles, false );
	}

	// Application Passwords detail.
	update_option( 'blueworx_show_application_passwords', isset( $_POST['blueworx_show_application_passwords'] ) ? '1' : '0' );

	set_transient( 'blueworx_labs_notice', __( 'Settings saved.', 'blueworx-labs-wordpress' ), 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-labs-wordpress' ) );
	exit;
}
add_action( 'admin_post_blueworx_save_feature_settings', 'blueworx_save_feature_settings' );

/**
 * Renders the BlueWorx feature settings page.
 *
 * @return void
 */
function blueworx_render_enhancements_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-labs-wordpress' ) );
	}

	$sections  = blueworx_get_feature_sections();
	$features  = blueworx_get_feature_definitions();
	$notice    = get_transient( 'blueworx_labs_notice' );
	$login_url = home_url( '/' . blueworx_login_slug() . '/' );

	if ( $notice ) {
		delete_transient( 'blueworx_labs_notice' );
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Turn each function on or off. Functions left on behave exactly as before.', 'blueworx-labs-wordpress' ); ?></p>
		<?php if ( blueworx_feature_enabled( 'login' ) ) : ?>
			<p><strong><?php esc_html_e( 'Active login URL:', 'blueworx-labs-wordpress' ); ?></strong>
				<a href="<?php echo esc_url( $login_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $login_url ); ?></a></p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="blueworx_save_feature_settings" />
			<?php wp_nonce_field( 'blueworx_save_feature_settings' ); ?>

			<?php foreach ( $sections as $section_id => $section_label ) : ?>
				<div class="postbox blueworx-feature-section">
					<div class="postbox-header"><h2 class="hndle"><?php echo esc_html( $section_label ); ?></h2></div>
					<div class="inside">
						<table class="form-table" role="presentation"><tbody>
						<?php
						foreach ( $features as $key => $feature ) :
							if ( $feature['section'] !== $section_id ) {
								continue;
							}
							$enabled = blueworx_feature_enabled( $key );
							?>
							<tr>
								<th scope="row">
									<label>
										<input type="checkbox" name="<?php echo esc_attr( 'blueworx_feature[' . $key . ']' ); ?>" value="1" <?php checked( $enabled ); ?> class="blueworx-feature-toggle" data-blueworx-feature="<?php echo esc_attr( $key ); ?>" />
										<?php echo esc_html( $feature['label'] ); ?>
									</label>
								</th>
								<td>
									<p class="description"><?php echo esc_html( $feature['description'] ); ?></p>
									<?php if ( ! empty( $feature['detail'] ) ) : ?>
										<div class="blueworx-feature-detail" data-blueworx-detail="<?php echo esc_attr( $key ); ?>" <?php echo $enabled ? '' : 'hidden'; ?>>
											<?php blueworx_render_feature_detail( $key ); ?>
										</div>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody></table>
					</div>
				</div>
			<?php endforeach; ?>

			<?php submit_button( esc_html__( 'Save Changes', 'blueworx-labs-wordpress' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Renders the nested detail controls for a feature.
 *
 * @param string $key Feature key.
 * @return void
 */
function blueworx_render_feature_detail( $key ) {
	if ( 'login' === $key ) {
		?>
		<p>
			<label for="blueworx_login_slug"><?php esc_html_e( 'Login slug', 'blueworx-labs-wordpress' ); ?></label><br />
			<input type="text" id="blueworx_login_slug" name="blueworx_login_slug" class="regular-text" value="<?php echo esc_attr( blueworx_login_slug() ); ?>" />
			<span class="description"><?php echo esc_html( home_url( '/' ) ); ?>&hellip;</span>
		</p>
		<?php
		return;
	}

	if ( 'site_protection' === $key ) {
		$role_choices = blueworx_get_site_protection_role_choices();
		foreach ( array(
			'frontend' => __( 'Frontend protection', 'blueworx-labs-wordpress' ),
			'backend'  => __( 'Backend protection', 'blueworx-labs-wordpress' ),
		) as $area => $label ) :
			$selected_roles = blueworx_get_site_protection_roles( $area );
			?>
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( 'blueworx_' . $area . '_protection_enabled' ); ?>" value="1" <?php checked( blueworx_site_protection_enabled( $area ) ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			</p>
			<p>
				<select name="<?php echo esc_attr( 'blueworx_' . $area . '_protection_roles[]' ); ?>" multiple size="4" aria-label="<?php echo esc_attr( $label ); ?>">
					<?php foreach ( $role_choices as $role_slug => $role_label ) : ?>
						<option value="<?php echo esc_attr( $role_slug ); ?>" <?php selected( in_array( $role_slug, $selected_roles, true ) ); ?>><?php echo esc_html( $role_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php
		endforeach;
		return;
	}

	if ( 'application_passwords' === $key ) {
		?>
		<p>
			<label>
				<input type="checkbox" name="blueworx_show_application_passwords" value="1" <?php checked( blueworx_show_application_passwords_for_admins() ); ?> />
				<?php esc_html_e( 'Show Application Passwords for admins', 'blueworx-labs-wordpress' ); ?>
			</label>
		</p>
		<?php
		return;
	}

	if ( 'cache_manual' === $key ) {
		?>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=blueworx-cache' ) ); ?>"><?php esc_html_e( 'Open Cache page', 'blueworx-labs-wordpress' ); ?></a></p>
		<?php
		return;
	}

	if ( 'menu_editor' === $key ) {
		?>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=blueworx-edit-menu' ) ); ?>"><?php esc_html_e( 'Open Edit Menu page', 'blueworx-labs-wordpress' ); ?></a></p>
		<?php
	}
}

/**
 * Renders the Cache page.
 *
 * @return void
 */
function blueworx_render_cache_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-labs-wordpress' ) );
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

		<h2><?php esc_html_e( 'Cache Refresh', 'blueworx-labs-wordpress' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Automatic Refresh', 'blueworx-labs-wordpress' ); ?></th>
				<td>
					<strong><?php esc_html_e( 'Enabled', 'blueworx-labs-wordpress' ); ?></strong>
					<p class="description">
						<?php esc_html_e( 'When a page or post changes, this plugin refreshes the edited page, homepage, and related listing pages.', 'blueworx-labs-wordpress' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Breeze Cache', 'blueworx-labs-wordpress' ); ?></th>
				<td>
					<strong>
						<?php echo $breeze_active ? esc_html__( 'Detected', 'blueworx-labs-wordpress' ) : esc_html__( 'Not detected', 'blueworx-labs-wordpress' ); ?>
					</strong>
					<p class="description">
						<?php esc_html_e( 'Cloudways Breeze/Varnish is used when available. WordPress cache clearing is used as a safe fallback.', 'blueworx-labs-wordpress' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Manual Refresh', 'blueworx-labs-wordpress' ); ?></th>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="blueworx_clear_cache_now" />
						<?php wp_nonce_field( 'blueworx_clear_cache_now' ); ?>
						<?php submit_button( esc_html__( 'Refresh Cache Now', 'blueworx-labs-wordpress' ), 'secondary', 'submit', false ); ?>
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
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-labs-wordpress' ) );
	}

	$menu_items  = blueworx_get_editable_admin_menu_items();
	$saved_order = blueworx_get_saved_admin_menu_order();
	$hidden      = blueworx_get_hidden_admin_menu_items();
	$locked      = blueworx_get_locked_admin_menu_items();
	// More is retired (migration 4): nothing can be toggled into it any more.
	// The More column below is inert until Task 14 rewrites this page for groups.
	$toggled     = array();
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
		<p><?php esc_html_e( 'Drag items between Main Menu and More Menu. Use the eye icon to hide or show an item.', 'blueworx-labs-wordpress' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="blueworx_save_admin_menu_order" />
			<?php wp_nonce_field( 'blueworx_save_admin_menu_order' ); ?>

			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Main Menu', 'blueworx-labs-wordpress' ); ?></th>
						<th scope="col"><?php esc_html_e( 'More Menu', 'blueworx-labs-wordpress' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Hidden', 'blueworx-labs-wordpress' ); ?></th>
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

			<?php submit_button( esc_html__( 'Save Menu Settings', 'blueworx-labs-wordpress' ) ); ?>
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
						aria-label="<?php echo esc_attr( 'hidden' === $state ? __( 'Show menu item', 'blueworx-labs-wordpress' ) : __( 'Hide menu item', 'blueworx-labs-wordpress' ) ); ?>"
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
