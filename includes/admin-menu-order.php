<?php
/**
 * Admin menu ordering.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the default admin menu order.
 *
 * @return array Default menu slugs.
 */
function blueworx_get_default_admin_menu_order() {
	return array(
		'index.php',
		'blueworx-project-wordpress-labs',
		'edit.php',
		'upload.php',
		'edit.php?post_type=page',
		'themes.php',
		'plugins.php',
		'users.php',
		'tools.php',
		'options-general.php',
	);
}

/**
 * Gets menu slugs that should not be hidden or toggled.
 *
 * @return array Locked menu slugs.
 */
function blueworx_get_locked_admin_menu_items() {
	return array(
		'blueworx-menu-toggle',
		'separator-blueworx-toggle',
	);
}

/**
 * Gets menu items hidden from the admin menu.
 *
 * @return array Hidden menu slugs.
 */
function blueworx_get_hidden_admin_menu_items() {
	$hidden = get_option( 'blueworx_hidden_admin_menu_items', array() );

	if ( ! is_array( $hidden ) ) {
		return array();
	}

	return array_values( array_diff( array_unique( array_filter( array_map( 'sanitize_text_field', $hidden ) ) ), blueworx_get_locked_admin_menu_items() ) );
}

/**
 * Gets menu items moved under the More menu.
 *
 * @return array More menu slugs.
 */
function blueworx_get_toggled_admin_menu_items() {
	$toggled = get_option( 'blueworx_toggled_admin_menu_items', array() );

	if ( ! is_array( $toggled ) ) {
		return array();
	}

	return array_values( array_diff( array_unique( array_filter( array_map( 'sanitize_text_field', $toggled ) ) ), blueworx_get_locked_admin_menu_items() ) );
}

/**
 * Gets the saved admin menu order.
 *
 * @return array Saved or default menu slugs.
 */
function blueworx_get_saved_admin_menu_order() {
	$saved_order = get_option( 'blueworx_admin_menu_order', array() );

	if ( ! is_array( $saved_order ) || empty( $saved_order ) ) {
		return blueworx_get_default_admin_menu_order();
	}

	return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $saved_order ) ) ) );
}

/**
 * Gets visible admin menu items for the Edit Menu page.
 *
 * @return array Menu items keyed by slug.
 */
function blueworx_get_editable_admin_menu_items() {
	global $menu;

	$items  = array();
	$labels = get_option( 'blueworx_admin_menu_item_labels', array() );

	if ( ! is_array( $labels ) ) {
		$labels = array();
	}

	foreach ( (array) $menu as $menu_item ) {
		$label = isset( $menu_item[0] ) ? wp_strip_all_tags( (string) $menu_item[0] ) : '';
		$slug  = isset( $menu_item[2] ) ? (string) $menu_item[2] : '';

		if ( '' === $slug || 0 === strpos( $slug, 'separator' ) ) {
			continue;
		}

		if ( in_array( $slug, blueworx_get_locked_admin_menu_items(), true ) ) {
			continue;
		}

		$items[ $slug ] = trim( preg_replace( '/\s+/', ' ', $label ) );
	}

	foreach ( blueworx_get_saved_admin_menu_order() as $slug ) {
		if ( in_array( $slug, blueworx_get_locked_admin_menu_items(), true ) ) {
			continue;
		}

		if ( ! isset( $items[ $slug ] ) && isset( $labels[ $slug ] ) ) {
			$items[ $slug ] = $labels[ $slug ];
		}
	}

	return $items;
}

/**
 * Enables a custom admin menu order.
 *
 * @return bool Always true.
 */
function blueworx_enable_admin_menu_order() {
	return true;
}
add_filter( 'custom_menu_order', 'blueworx_enable_admin_menu_order' );

/**
 * Sets the preferred left admin menu order.
 *
 * Unknown plugin menu items are left for WordPress to place after these items.
 *
 * @param array $menu_order Existing menu order.
 * @return array Preferred menu order.
 */
function blueworx_admin_menu_order( $menu_order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $menu_order is required by the WordPress "menu_order" filter's callback signature; this implementation builds its own order from stored settings instead of the incoming value.
	$order = array_values( array_diff( blueworx_get_saved_admin_menu_order(), array( 'separator-blueworx-toggle', 'blueworx-menu-toggle' ) ) );

	if ( ! in_array( 'blueworx-project-wordpress-labs', $order, true ) ) {
		$dashboard_position = array_search( 'index.php', $order, true );
		$insert_position    = false === $dashboard_position ? 1 : $dashboard_position + 1;

		array_splice( $order, $insert_position, 0, 'blueworx-project-wordpress-labs' );
	}

	if ( ! empty( blueworx_get_toggled_admin_menu_items() ) ) {
		$order[] = 'separator-blueworx-toggle';
		$order[] = 'blueworx-menu-toggle';
	}

	return $order;
}
add_filter( 'menu_order', 'blueworx_admin_menu_order' );

/**
 * Moves selected menu items under More and hides selected menu items.
 *
 * @return void
 */
function blueworx_apply_admin_menu_visibility() {
	global $menu, $submenu;

	$hidden        = blueworx_get_hidden_admin_menu_items();
	$toggled       = blueworx_get_toggled_admin_menu_items();
	$menu_items    = array();
	$toggled_items = array();
	$hidden_ids    = array();
	$labels        = array();

	foreach ( (array) $menu as $menu_item ) {
		$label      = isset( $menu_item[0] ) ? trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $menu_item[0] ) ) ) : '';
		$capability = isset( $menu_item[1] ) ? (string) $menu_item[1] : 'read';
		$slug       = isset( $menu_item[2] ) ? (string) $menu_item[2] : '';
		$item_id    = isset( $menu_item[5] ) ? (string) $menu_item[5] : '';

		if ( '' === $slug || '' === $label || 0 === strpos( $slug, 'separator' ) ) {
			continue;
		}

		$labels[ $slug ]     = $label;
		$menu_items[ $slug ] = array(
			'label'      => $label,
			'capability' => $capability,
			'url'        => blueworx_get_admin_menu_slug_url( $slug ),
		);

		if ( ( in_array( $slug, $hidden, true ) || in_array( $slug, $toggled, true ) ) && '' !== $item_id ) {
			$hidden_ids[] = blueworx_sanitize_admin_menu_id( $item_id );
		}
	}

	if ( ! empty( $labels ) ) {
		update_option( 'blueworx_admin_menu_item_labels', $labels );
	}

	foreach ( $toggled as $slug ) {
		if ( isset( $menu_items[ $slug ] ) ) {
			$toggled_items[ $slug ] = $menu_items[ $slug ];
		}
	}

	if ( ! empty( $toggled_items ) ) {
		$menu[998] = array( '', 'read', 'separator-blueworx-toggle', '', 'wp-menu-separator blueworx-toggle-separator' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Direct mutation of the $menu global (inside the "admin_menu" action, via the `global $menu, $submenu;` above) is the standard, documented way to insert admin menu rows; WordPress provides no dedicated API for this.

		add_menu_page(
			esc_html__( 'More', 'blueworx-project-wordpress-labs' ),
			esc_html__( 'More', 'blueworx-project-wordpress-labs' ),
			'read',
			'blueworx-menu-toggle',
			'blueworx_render_toggle_menu_page',
			'dashicons-ellipsis',
			999
		);

		foreach ( $toggled_items as $slug => $item ) {
			$submenu['blueworx-menu-toggle'][] = array( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Direct mutation of the $submenu global (inside the "admin_menu" action) is the standard, documented way to add submenu rows; WordPress provides no dedicated API for this.
				$item['label'],
				$item['capability'],
				$item['url'],
				$item['label'],
			);
		}
	}

	if ( ! empty( $hidden_ids ) ) {
		$GLOBALS['blueworx_hidden_admin_menu_ids'] = array_values( array_unique( $hidden_ids ) );
	}
}
add_action( 'admin_menu', 'blueworx_apply_admin_menu_visibility', 999 );

/**
 * Sanitizes a WordPress admin menu row ID for CSS output.
 *
 * @param string $item_id Menu row ID.
 * @return string Sanitized menu row ID.
 */
function blueworx_sanitize_admin_menu_id( $item_id ) {
	return preg_replace( '|[^a-zA-Z0-9_:.]|', '-', (string) $item_id );
}

/**
 * Hides menu rows without unregistering their admin pages.
 *
 * @return void
 */
function blueworx_hide_admin_menu_rows() {
	$hidden_ids = isset( $GLOBALS['blueworx_hidden_admin_menu_ids'] ) ? (array) $GLOBALS['blueworx_hidden_admin_menu_ids'] : array();

	if ( empty( $hidden_ids ) ) {
		return;
	}
	?>
	<style>
		<?php foreach ( $hidden_ids as $hidden_id ) : ?>
			#adminmenu [id="<?php echo esc_attr( $hidden_id ); ?>"] {
				display: none;
			}
		<?php endforeach; ?>
	</style>
	<?php
}
add_action( 'admin_head', 'blueworx_hide_admin_menu_rows' );

/**
 * Makes the More menu expand without loading a page.
 *
 * @return void
 */
function blueworx_make_toggle_menu_inline() {
	?>
	<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			var link = document.querySelector( '#toplevel_page_blueworx-menu-toggle > a' );

			if ( ! link ) {
				return;
			}

			link.addEventListener( 'click', function ( event ) {
				var item = link.closest( 'li' );

				if ( ! item ) {
					return;
				}

				event.preventDefault();
				item.classList.toggle( 'wp-menu-open' );
				item.classList.toggle( 'opensub' );
			} );
		} );
	</script>
	<?php
}
add_action( 'admin_footer', 'blueworx_make_toggle_menu_inline' );

/**
 * Gets the admin URL for a saved menu slug.
 *
 * @param string $slug Admin menu slug.
 * @return string Admin URL.
 */
function blueworx_get_admin_menu_slug_url( $slug ) {
	$slug = (string) $slug;

	if ( false !== strpos( $slug, '.php' ) ) {
		return $slug;
	}

	return 'admin.php?page=' . rawurlencode( $slug );
}

/**
 * Renders a simple More landing page.
 *
 * @return void
 */
function blueworx_render_toggle_menu_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'More', 'blueworx-project-wordpress-labs' ); ?></h1>
		<p><?php esc_html_e( 'Use the submenu items here to open menus moved into More.', 'blueworx-project-wordpress-labs' ); ?></p>
	</div>
	<?php
}
