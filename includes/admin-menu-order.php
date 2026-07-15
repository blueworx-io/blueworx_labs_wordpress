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
		'blueworx-labs-wordpress',
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
	return array();
}

/**
 * Checks whether an admin has saved a custom menu arrangement.
 *
 * Until the Edit Menu page is saved once, the plugin serves a computed default
 * arrangement instead of the stored options.
 *
 * @return bool True when a custom arrangement has been saved.
 */
function blueworx_admin_menu_is_customized() {
	return '1' === get_option( 'blueworx_admin_menu_customized', '0' );
}

/**
 * Determines whether the computed default menu arrangement should apply.
 *
 * Defaults apply only to administrators and only until a custom arrangement is
 * saved. Lower roles and customised sites fall through to the stored options.
 *
 * @return bool True when the computed default arrangement should be used.
 */
function blueworx_should_use_default_admin_menu() {
	return ! blueworx_admin_menu_is_customized() && current_user_can( 'manage_options' );
}

/**
 * Gets the canonical top-level slugs registered by WordPress core.
 *
 * Used to tell core menu items (moved to More by default) apart from
 * plugin-added items (kept visible below the defaults).
 *
 * @return array Core top-level menu slugs.
 */
function blueworx_get_core_admin_menu_slugs() {
	return array(
		'index.php',
		'edit.php',
		'upload.php',
		'edit.php?post_type=page',
		'edit-comments.php',
		'themes.php',
		'plugins.php',
		'users.php',
		'tools.php',
		'options-general.php',
		'profile.php',
		'link-manager.php',
	);
}

/**
 * Gets the top-level slugs shown in the main menu by default.
 *
 * @return array Keep-visible menu slugs.
 */
function blueworx_get_default_visible_admin_menu_slugs() {
	return array(
		'index.php',
		'blueworx-labs-wordpress',
		'edit.php',
		'upload.php',
		'edit.php?post_type=page',
		'users.php',
	);
}

/**
 * Computes the default admin menu arrangement from the live menu.
 *
 * Dashboard is pinned first and BlueWorx second; the remaining keep-visible
 * items follow (sorted by title length, then alphabetically), then plugin
 * items (same sort), then the core items moved into More (same sort). Nothing
 * is hidden by default. Falls back to the static default order when the menu
 * global is not yet populated.
 *
 * @return array {
 *     @type array $order   Ordered top-level slugs.
 *     @type array $toggled Slugs moved into More.
 *     @type array $hidden  Slugs hidden (always empty by default).
 * }
 */
function blueworx_compute_default_admin_menu_arrangement() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	global $menu;

	$locked = blueworx_get_locked_admin_menu_items();
	$labels = array();

	foreach ( (array) $menu as $menu_item ) {
		$slug  = isset( $menu_item[2] ) ? (string) $menu_item[2] : '';
		$label = isset( $menu_item[0] ) ? trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $menu_item[0] ) ) ) : '';
		$label = trim( preg_replace( '/\s+\d+$/', '', $label ) );

		if ( '' === $slug || 0 === strpos( $slug, 'separator' ) || in_array( $slug, $locked, true ) ) {
			continue;
		}

		$labels[ $slug ] = $label;
	}

	if ( empty( $labels ) ) {
		$cache = array(
			'order'   => blueworx_get_default_admin_menu_order(),
			'toggled' => array(),
			'hidden'  => array(),
		);

		return $cache;
	}

	$present = array_keys( $labels );
	$keep    = blueworx_get_default_visible_admin_menu_slugs();
	$core    = blueworx_get_core_admin_menu_slugs();

	$pinned_top = array();
	foreach ( array( 'index.php', 'blueworx-labs-wordpress' ) as $pinned_slug ) {
		if ( in_array( $pinned_slug, $present, true ) ) {
			$pinned_top[] = $pinned_slug;
		}
	}

	$keep_rest = array();
	$plugins   = array();
	$toggled   = array();

	foreach ( $present as $slug ) {
		if ( in_array( $slug, $pinned_top, true ) ) {
			continue;
		}

		if ( in_array( $slug, $keep, true ) ) {
			$keep_rest[] = $slug;
		} elseif ( in_array( $slug, $core, true ) ) {
			$toggled[] = $slug;
		} else {
			$plugins[] = $slug;
		}
	}

	$sort = function ( $slugs ) use ( $labels ) {
		usort(
			$slugs,
			function ( $a, $b ) use ( $labels ) {
				$len_a = mb_strlen( $labels[ $a ] );
				$len_b = mb_strlen( $labels[ $b ] );

				if ( $len_a !== $len_b ) {
					return $len_a - $len_b;
				}

				return strcasecmp( $labels[ $a ], $labels[ $b ] );
			}
		);

		return $slugs;
	};

	$keep_rest = $sort( $keep_rest );
	$plugins   = $sort( $plugins );
	$toggled   = $sort( $toggled );

	$cache = array(
		'order'   => array_values( array_merge( $pinned_top, $keep_rest, $plugins, $toggled ) ),
		'toggled' => array_values( $toggled ),
		'hidden'  => array(),
	);

	return $cache;
}

/**
 * Gets menu items hidden from the admin menu.
 *
 * @return array Hidden menu slugs.
 */
function blueworx_get_hidden_admin_menu_items() {
	if ( blueworx_should_use_default_admin_menu() ) {
		$arrangement = blueworx_compute_default_admin_menu_arrangement();

		return $arrangement['hidden'];
	}

	$hidden = get_option( 'blueworx_hidden_admin_menu_items', array() );

	if ( ! is_array( $hidden ) ) {
		return array();
	}

	return array_values( array_diff( array_unique( array_filter( array_map( 'sanitize_text_field', $hidden ) ) ), blueworx_get_locked_admin_menu_items() ) );
}

/**
 * Gets the saved admin menu order.
 *
 * @return array Saved or default menu slugs.
 */
function blueworx_get_saved_admin_menu_order() {
	if ( blueworx_should_use_default_admin_menu() ) {
		$arrangement = blueworx_compute_default_admin_menu_arrangement();

		return $arrangement['order'];
	}

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
 * Promotes custom post type menus to top-level sidebar rows.
 *
 * A post type registered with show_in_menu => '<parent-slug>' is added by core
 * as a submenu of that parent, so it never reaches the $menu global. Grouping
 * reads $menu, which is why a site whose post types all hang off one plugin
 * menu renders an empty Custom Content group: the rule was right, the menu
 * shape was not what it assumed.
 *
 * The v2 design gives every custom content type its own sidebar row, so those
 * submenus are lifted to the top level and land in Custom Content via the
 * existing edit.php?post_type= rule. Post types already registered top-level
 * are untouched — they are found by the same rule either way.
 *
 * The parent menu is deliberately left in place. It may have its own screen and
 * its own remaining submenus, and deciding that a menu the site owns should
 * disappear is not this plugin's call.
 *
 * Runs at priority 996, before the icon swap (997) and the group heading
 * markers (998), so the promoted rows are decorated like any other.
 *
 * @return void
 */
function blueworx_promote_custom_content_menus() {
	global $menu, $submenu;

	$prefix   = 'edit.php?post_type=';
	$existing = array();

	foreach ( (array) $menu as $menu_item ) {
		if ( isset( $menu_item[2] ) ) {
			$existing[ (string) $menu_item[2] ] = true;
		}
	}

	foreach ( (array) $submenu as $parent => $items ) {
		foreach ( (array) $items as $index => $item ) {
			$slug = isset( $item[2] ) ? (string) $item[2] : '';

			if ( 0 !== strpos( $slug, $prefix ) || isset( $existing[ $slug ] ) ) {
				continue;
			}

			$post_type = substr( $slug, strlen( $prefix ) );
			$type_obj  = get_post_type_object( $post_type );

			// Built-ins already have their own top-level rows and their own
			// group; a plugin re-listing them under its menu must not spawn a
			// duplicate row.
			if ( ! $type_obj || ! $type_obj->show_ui || in_array( $post_type, array( 'post', 'page', 'attachment' ), true ) ) {
				continue;
			}

			// menu_name ("Sports"), not the submenu's own label, which core sets
			// to all_items ("All Sports") — a top-level row wants the short form.
			$label = isset( $type_obj->labels->menu_name ) && '' !== $type_obj->labels->menu_name
				? $type_obj->labels->menu_name
				: ( isset( $item[0] ) ? $item[0] : $post_type );

			// Mirrors the row core builds for a top-level post type in
			// wp-admin/menu.php: label, cap, slug, page title, classes, id, icon.
			$menu[] = array( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Direct mutation of the $menu global inside the "admin_menu" action is the standard, documented way to insert admin menu rows; WordPress provides no API for this.
				esc_attr( $label ),
				$type_obj->cap->edit_posts,
				$slug,
				'',
				'menu-top menu-icon-' . $post_type,
				'menu-posts-' . $post_type,
				'dashicons-admin-post',
			);

			$existing[ $slug ] = true;

			// Drop the now-duplicated submenu row, leaving the parent itself.
			unset( $submenu[ $parent ][ $index ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above, for the $submenu global.

			blueworx_add_promoted_post_type_submenu( $post_type, $type_obj );
		}
	}
}

/**
 * Gives a promoted post type the submenu core would have given it.
 *
 * A post type registered against a parent menu gets exactly one submenu row —
 * the "all items" link on that parent. The All / Add New / taxonomy rows exist
 * only for post types core itself puts at the top level. Promoting a type
 * without rebuilding them would leave a top-level row with nothing nested under
 * it, and no route to Add New or to its taxonomies.
 *
 * Mirrors wp-admin/menu.php's own construction, including its position numbers
 * (5, 10, then 15+ per taxonomy), so the rows read in core's order.
 *
 * @param string $post_type Post type name.
 * @param object $type_obj  Post type object.
 * @return void
 */
function blueworx_add_promoted_post_type_submenu( $post_type, $type_obj ) {
	global $submenu;

	$parent = 'edit.php?post_type=' . $post_type;

	// Never clobber rows the site registered itself.
	if ( ! empty( $submenu[ $parent ] ) ) {
		return;
	}

	$rows = array(
		5  => array(
			$type_obj->labels->all_items,
			$type_obj->cap->edit_posts,
			$parent,
		),
		10 => array(
			$type_obj->labels->add_new,
			$type_obj->cap->create_posts,
			'post-new.php?post_type=' . $post_type,
		),
	);

	$position = 15;

	foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy ) {
		if ( ! $taxonomy->show_ui || ! $taxonomy->show_in_menu ) {
			continue;
		}

		$rows[ $position ] = array(
			esc_attr( $taxonomy->labels->menu_name ),
			$taxonomy->cap->manage_terms,
			'edit-tags.php?taxonomy=' . $taxonomy->name . '&amp;post_type=' . $post_type,
		);

		++$position;
	}

	$submenu[ $parent ] = $rows; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Direct mutation of the $submenu global inside the "admin_menu" action is the standard, documented way to add admin submenu rows; WordPress provides no API for this.
}
if ( blueworx_feature_enabled( 'admin_theme' ) ) {
	add_action( 'admin_menu', 'blueworx_promote_custom_content_menus', 996 );
}

/**
 * Enables a custom admin menu order.
 *
 * @return bool Always true.
 */
function blueworx_enable_admin_menu_order() {
	return true;
}
if ( blueworx_feature_enabled( 'menu_editor' ) ) {
	add_filter( 'custom_menu_order', 'blueworx_enable_admin_menu_order' );
}

/**
 * Sets the preferred left admin menu order.
 *
 * Orders top-level items by semantic group (Overview -> Content -> Custom
 * Content -> Site), honouring the admin's saved order within each group.
 *
 * Shared with blueworx_mark_admin_menu_group_starts() (includes/admin-theme.php),
 * which calls this directly to learn which slug leads each group, so the
 * heading markers and the actual render order can never drift apart.
 *
 * @param array $menu_order Existing menu order.
 * @return array Preferred menu order.
 */
function blueworx_admin_menu_order( $menu_order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by the "menu_order" filter signature; this implementation builds its own order from group assignments and stored settings.
	$assignments = blueworx_get_admin_menu_group_assignments();
	$saved       = blueworx_get_saved_admin_menu_order();
	$groups      = array_keys( blueworx_get_admin_menu_groups() );
	$ordered     = array();

	$customized = blueworx_admin_menu_is_customized();

	// Within a group, honour the admin's saved order; unsaved items follow.
	foreach ( $groups as $group ) {
		$in_group = array();

		foreach ( $saved as $slug ) {
			if ( isset( $assignments[ $slug ] ) && $group === $assignments[ $slug ] ) {
				$in_group[] = $slug;
			}
		}

		foreach ( $assignments as $slug => $slug_group ) {
			if ( $group === $slug_group && ! in_array( $slug, $in_group, true ) ) {
				$in_group[] = $slug;
			}
		}

		if ( ! $customized ) {
			$in_group = blueworx_sort_admin_menu_group_by_design( $in_group );
		}

		foreach ( $in_group as $slug ) {
			$ordered[] = $slug;
		}
	}

	return $ordered;
}

/**
 * Sorts one group's slugs into the v2 design's order.
 *
 * The saved order is computed by blueworx_compute_default_admin_menu_arrangement(),
 * which sorts by label length then A-Z. That rule predates the semantic groups
 * and was written for a single flat list, so inside Content it yields Media,
 * Pages, Posts where the design reads Posts, Media, Pages.
 *
 * The group rules map is already written in the design's order, so it doubles as
 * the intended order within a group. Slugs it does not list (custom post types,
 * third-party menus) keep their existing relative order, after the listed ones.
 *
 * Applied only to sites that have never saved the Edit Menu: the design sets the
 * default, an admin's own arrangement overrides it.
 *
 * @param array $slugs Slugs in one group.
 * @return array Slugs in design order.
 */
function blueworx_sort_admin_menu_group_by_design( $slugs ) {
	$design = array_keys( blueworx_get_admin_menu_group_rules() );
	$listed = array();
	$rest   = array();

	foreach ( $slugs as $slug ) {
		if ( in_array( $slug, $design, true ) ) {
			$listed[] = $slug;
		} else {
			$rest[] = $slug;
		}
	}

	usort(
		$listed,
		function ( $a, $b ) use ( $design ) {
			return array_search( $a, $design, true ) - array_search( $b, $design, true );
		}
	);

	return array_merge( $listed, $rest );
}
if ( blueworx_feature_enabled( 'admin_theme' ) ) {
	add_filter( 'menu_order', 'blueworx_admin_menu_order' );
	add_filter( 'custom_menu_order', '__return_true' );
}

/**
 * Hides selected menu items.
 *
 * @return void
 */
function blueworx_apply_admin_menu_visibility() {
	global $menu;

	$hidden     = blueworx_get_hidden_admin_menu_items();
	$hidden_ids = array();
	$labels     = array();

	foreach ( (array) $menu as $menu_item ) {
		$label   = isset( $menu_item[0] ) ? trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $menu_item[0] ) ) ) : '';
		$slug    = isset( $menu_item[2] ) ? (string) $menu_item[2] : '';
		$item_id = isset( $menu_item[5] ) ? (string) $menu_item[5] : '';

		if ( '' === $slug || '' === $label || 0 === strpos( $slug, 'separator' ) ) {
			continue;
		}

		$labels[ $slug ] = $label;

		if ( in_array( $slug, $hidden, true ) && '' !== $item_id ) {
			$hidden_ids[] = blueworx_sanitize_admin_menu_id( $item_id );
		}
	}

	if ( ! empty( $labels ) ) {
		update_option( 'blueworx_admin_menu_item_labels', $labels );
	}

	if ( ! empty( $hidden_ids ) ) {
		$GLOBALS['blueworx_hidden_admin_menu_ids'] = array_values( array_unique( $hidden_ids ) );
	}
}
if ( blueworx_feature_enabled( 'menu_editor' ) ) {
	add_action( 'admin_menu', 'blueworx_apply_admin_menu_visibility', 999 );
}

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
if ( blueworx_feature_enabled( 'menu_editor' ) ) {
	add_action( 'admin_head', 'blueworx_hide_admin_menu_rows' );
}

