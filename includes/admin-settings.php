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

	$raw_order  = isset( $_POST['blueworx_admin_menu_order'] ) ? (array) wp_unslash( $_POST['blueworx_admin_menu_order'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with sanitize_text_field.
	$raw_hidden = isset( $_POST['blueworx_hidden_admin_menu_items'] ) ? (array) wp_unslash( $_POST['blueworx_hidden_admin_menu_items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
	$raw_groups = isset( $_POST['blueworx_admin_menu_groups'] ) ? (array) wp_unslash( $_POST['blueworx_admin_menu_groups'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
	$order      = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $raw_order ) ) ) );
	$locked     = blueworx_get_locked_admin_menu_items();
	$hidden     = array_values( array_diff( array_unique( array_filter( array_map( 'sanitize_text_field', $raw_hidden ) ) ), $locked ) );

	// Only accept groups this build knows about; anything else is dropped rather
	// than stored, so a stale or forged POST cannot create a phantom group.
	// "hidden" is not a group — it is expressed by the hidden-items option — so
	// it is skipped here rather than written as one.
	$known  = blueworx_get_admin_menu_groups();
	$groups = array();

	foreach ( $raw_groups as $slug => $group ) {
		$slug  = sanitize_text_field( (string) $slug );
		$group = sanitize_key( (string) $group );

		if ( '' === $slug || 'hidden' === $group ) {
			continue;
		}

		if ( isset( $known[ $group ] ) ) {
			$groups[ $slug ] = $group;
		}
	}

	update_option( 'blueworx_admin_menu_order', $order );
	update_option( 'blueworx_hidden_admin_menu_items', array_values( array_unique( $hidden ) ) );
	update_option( 'blueworx_admin_menu_groups', $groups );
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
	$assignments = blueworx_get_admin_menu_group_assignments();
	$hidden      = blueworx_get_hidden_admin_menu_items();
	$saved_order = blueworx_get_saved_admin_menu_order();
	$groups      = blueworx_get_admin_menu_groups();
	$notice      = get_transient( 'blueworx_admin_menu_order_notice' );

	if ( $notice ) {
		delete_transient( 'blueworx_admin_menu_order_notice' );
	}

	// Saved order first, then anything the site has registered since.
	$ordered = array();

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

	// Bucket every editable item: hidden wins, otherwise its assigned group.
	$buckets           = array_fill_keys( array_keys( $groups ), array() );
	$buckets['hidden'] = array();

	foreach ( $ordered as $slug => $label ) {
		if ( in_array( $slug, $hidden, true ) ) {
			$buckets['hidden'][ $slug ] = $label;
			continue;
		}

		$group = isset( $assignments[ $slug ] ) ? $assignments[ $slug ] : blueworx_get_default_admin_menu_group( $slug );

		if ( ! isset( $buckets[ $group ] ) ) {
			$group = blueworx_get_default_admin_menu_group_fallback();
		}

		$buckets[ $group ][ $slug ] = $label;
	}

	$sections           = $groups;
	$sections['hidden'] = __( 'Hidden', 'blueworx-labs-wordpress' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( $notice ); ?></p>
			</div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Drag an item into another group to move it, or use the arrow buttons. Items in Hidden do not appear in the sidebar.', 'blueworx-labs-wordpress' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="blueworx_save_admin_menu_order" />
			<?php wp_nonce_field( 'blueworx_save_admin_menu_order' ); ?>

			<div class="bw-menu-editor">
				<?php foreach ( $sections as $group => $label ) : ?>
					<?php blueworx_render_menu_editor_group( $group, $label, $buckets[ $group ] ); ?>
				<?php endforeach; ?>
			</div>

			<?php submit_button( esc_html__( 'Save Menu Settings', 'blueworx-labs-wordpress' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Renders one Edit Menu group section.
 *
 * @param string $group Group key, or "hidden".
 * @param string $label Translated section label.
 * @param array  $items Menu labels keyed by slug.
 * @return void
 */
function blueworx_render_menu_editor_group( $group, $label, $items ) {
	?>
	<section class="bw-menu-editor-group" data-group="<?php echo esc_attr( $group ); ?>">
		<h2 class="bw-menu-editor-group-title"><?php echo esc_html( $label ); ?></h2>
		<ul class="bw-menu-editor-list">
			<?php foreach ( $items as $slug => $item_label ) : ?>
				<li class="bw-menu-editor-item" draggable="true" data-slug="<?php echo esc_attr( $slug ); ?>">
					<span class="bw-menu-editor-handle" aria-hidden="true">⠿</span>
					<span class="bw-menu-editor-label"><?php echo esc_html( $item_label ); ?></span>
					<button type="button" class="button-link bw-menu-editor-up"
						aria-label="<?php /* translators: %s: menu item name. */ echo esc_attr( sprintf( __( 'Move %s up', 'blueworx-labs-wordpress' ), $item_label ) ); ?>">▲</button>
					<button type="button" class="button-link bw-menu-editor-down"
						aria-label="<?php /* translators: %s: menu item name. */ echo esc_attr( sprintf( __( 'Move %s down', 'blueworx-labs-wordpress' ), $item_label ) ); ?>">▼</button>
					<input type="hidden" class="bw-menu-editor-order" name="blueworx_admin_menu_order[]" value="<?php echo esc_attr( $slug ); ?>" />
					<input type="hidden" class="bw-menu-editor-group-input" name="<?php echo esc_attr( 'blueworx_admin_menu_groups[' . $slug . ']' ); ?>" value="<?php echo esc_attr( $group ); ?>" />
					<?php if ( 'hidden' === $group ) : ?>
						<input type="hidden" class="bw-menu-editor-hidden-input" name="blueworx_hidden_admin_menu_items[]" value="<?php echo esc_attr( $slug ); ?>" />
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php
}
