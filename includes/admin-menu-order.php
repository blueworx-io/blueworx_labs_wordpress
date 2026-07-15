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
 * Flattens a raw $menu label to plain text, without core's update bubble.
 *
 * Core hangs a live count off some labels. Plugins is
 * 'Plugins <span class="update-plugins count-0"><span class="plugin-count">0</span></span>',
 * Comments carries an 'awaiting-mod' bubble. wp_strip_all_tags() alone flattens
 * that count INTO the text, which is why the Edit Menu listed "Plugins 0" — and
 * why the Move up/down controls announced "Move Plugins 0 up" to a screen
 * reader. The count is core's transient state, not part of the item's name.
 *
 * The bubble is always trailing, so cut from the count span to the end of the
 * string; that handles core's nested spans without trying to match them.
 *
 * @param string $raw Raw $menu[0] value.
 * @return string Plain-text label.
 */
function blueworx_clean_admin_menu_label( $raw ) {
	$raw = preg_replace( '#<span[^>]*class="[^"]*\bcount[^"]*"[^>]*>.*#is', '', (string) $raw );

	return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $raw ) ) );
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
		$label = isset( $menu_item[0] ) ? blueworx_clean_admin_menu_label( $menu_item[0] ) : '';
		$slug  = isset( $menu_item[2] ) ? (string) $menu_item[2] : '';

		if ( '' === $slug || 0 === strpos( $slug, 'separator' ) ) {
			continue;
		}

		if ( in_array( $slug, blueworx_get_locked_admin_menu_items(), true ) ) {
			continue;
		}

		$items[ $slug ] = $label;
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

/*
 * Custom post type menus are deliberately NOT promoted to top-level rows.
 *
 * An earlier pass here lifted every post type registered with
 * show_in_menu => '<parent-slug>' out of its parent and gave it its own sidebar
 * row, on the reading that the v2 design wanted one row per content type. On a
 * real site that shredded the structure the site had authored: Clubhouse
 * registers Sports, Teams, Fixtures, Events, Sponsors and People under its own
 * Content menu, and promoting them scattered six rows across the sidebar while
 * leaving the Content parent behind them, emptied.
 *
 * Where a site nests its post types is a statement about how that site is
 * organised, and it is not this plugin's call to overrule it. The types stay
 * where they were registered, and the Custom Content group is populated by the
 * parent menus themselves — which reach it through
 * blueworx_get_default_admin_menu_group_fallback() (includes/admin-menu-groups.php).
 * Post types a site registers top-level still land in Custom Content via the
 * edit.php?post_type= rule, exactly as before.
 */

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
		// Same cleaning as the Edit Menu screen: without it the stored label is
		// "Plugins 0", and it is this option the screen falls back to for items
		// the current request has not registered.
		$label   = isset( $menu_item[0] ) ? blueworx_clean_admin_menu_label( $menu_item[0] ) : '';
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

