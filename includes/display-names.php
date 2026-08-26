<?php
/**
 * Friendlier names for roles and plugins.
 *
 * Display only, and deliberately so. Nothing here touches a role slug, a
 * capability, a plugin folder or a single row in the database: it changes what
 * is written on the screen and stops there. Switching the feature off puts every
 * original name back, because nothing was ever stored under the new one.
 *
 * That is also why the renaming is done with filters rather than by writing the
 * new names anywhere. A plugin that renamed its neighbours for real would be
 * editing other people's data, and the next update of that plugin would either
 * overwrite it or trip over it.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The role names to rewrite, old name to new.
 *
 * Keyed by the role's registered English name rather than its slug, because
 * that is what WordPress hands the display filter and what a plugin's own
 * screens print. The four WordPress roles that already describe a job — editor,
 * author, contributor, administrator — are left alone.
 *
 * @return array New names keyed by the name being replaced.
 */
function blueworx_role_display_names() {
	$names = array(
		'LatePoint Agent'       => __( 'Booking Agent', 'blueworx-labs-wordpress' ),
		'Subscriber'            => __( 'Customer', 'blueworx-labs-wordpress' ),
		'SureCart Accountant'   => __( 'Commerce Accountant', 'blueworx-labs-wordpress' ),
		'SureCart Customer'     => __( 'Commerce Customer', 'blueworx-labs-wordpress' ),
		'SureCart Shop Manager' => __( 'Commerce Manager', 'blueworx-labs-wordpress' ),
		'SureCart Shop Worker'  => __( 'Commerce Editor', 'blueworx-labs-wordpress' ),
	);

	/**
	 * Filters the role names shown on the admin screens.
	 *
	 * @param array $names New names keyed by the name being replaced.
	 */
	return apply_filters( 'blueworx_role_display_names', $names );
}

/**
 * The plugin names to rewrite, old name to new.
 *
 * @return array New names keyed by the name being replaced.
 */
function blueworx_plugin_display_names() {
	$names = array(
		'SureCart'  => __( 'Commerce', 'blueworx-labs-wordpress' ),
		'SureDash'  => __( 'Dashboards', 'blueworx-labs-wordpress' ),
		'SureForms' => __( 'Forms Builder', 'blueworx-labs-wordpress' ),
		'LatePoint' => __( 'Bookings', 'blueworx-labs-wordpress' ),
	);

	/**
	 * Filters the plugin names shown on the admin screens.
	 *
	 * @param array $names New names keyed by the name being replaced.
	 */
	return apply_filters( 'blueworx_plugin_display_names', $names );
}

/**
 * Rewrites one piece of text against a name map.
 *
 * Matches the whole string, or the first word of it: "SureCart" becomes
 * "Commerce" and so does the "SureCart" in "SureCart Subscriptions", which is
 * how add-ons and sub-menu items name themselves. It deliberately does not
 * replace the name mid-sentence — a description reading "works with SureCart"
 * is about the product as its makers ship it, and rewriting that would be
 * putting words in their mouth.
 *
 * @param string $text Text as registered.
 * @param array  $map  New names keyed by the name being replaced.
 * @return string|null The new text, or null when nothing matched.
 */
function blueworx_rename_display_text( $text, $map ) {
	$text = (string) $text;

	if ( '' === $text ) {
		return null;
	}

	if ( isset( $map[ $text ] ) ) {
		return $map[ $text ];
	}

	foreach ( $map as $old => $new ) {
		if ( 0 === strpos( $text, $old . ' ' ) ) {
			return $new . substr( $text, strlen( $old ) );
		}
	}

	return null;
}

/**
 * Renames a role wherever WordPress prints one.
 *
 * Every screen that shows a role name — the users list, the role dropdowns, the
 * profile screen, this plugin's own pages and its view-as menu — reaches it
 * through translate_user_role(), which is this filter with this context. One
 * hook therefore covers the lot, and anything that bypasses it is printing a
 * raw slug rather than a name.
 *
 * @param string $translation Translated name.
 * @param string $text        Name as registered.
 * @param string $context     Gettext context.
 * @return string Name to display.
 */
function blueworx_rename_role_name( $translation, $text, $context ) {
	if ( 'User role' !== $context ) {
		return $translation;
	}

	// Held for the request. This filter runs on every translated string that
	// carries a context, which on an admin page is thousands of them, and
	// rebuilding the map each time would put a filter pass behind every one.
	static $map = null;

	if ( null === $map ) {
		$map = blueworx_role_display_names();
	}

	// The registered name first, then whatever the translation made of it, so a
	// site running in another language still matches on one of the two.
	$renamed = blueworx_rename_display_text( $text, $map );

	if ( null === $renamed ) {
		$renamed = blueworx_rename_display_text( $translation, $map );
	}

	return ( null === $renamed ) ? $translation : $renamed;
}

/**
 * Renames plugins on the Plugins screen.
 *
 * @param array $plugins Plugin data keyed by plugin file.
 * @return array Plugin data.
 */
function blueworx_rename_plugins_list( $plugins ) {
	$map = blueworx_plugin_display_names();

	foreach ( (array) $plugins as $file => $data ) {
		// Name is what the list prints; Title is the same name wrapped in the
		// plugin's own homepage link, and both are shown depending on the row.
		foreach ( array( 'Name', 'Title' ) as $key ) {
			if ( empty( $data[ $key ] ) ) {
				continue;
			}

			$renamed = blueworx_rename_display_text( $data[ $key ], $map );

			if ( null !== $renamed ) {
				$plugins[ $file ][ $key ] = $renamed;
			}
		}
	}

	return $plugins;
}

/**
 * Rewrites one admin menu label, leaving any count bubble alone.
 *
 * Core hangs a live count off some rows as markup inside the label. Only the
 * text in front of it is a name, so only that is considered.
 *
 * @param string $label Raw menu label, markup and all.
 * @param array  $map   New names keyed by the name being replaced.
 * @return string|null The new label, or null when nothing matched.
 */
function blueworx_rename_menu_label( $label, $map ) {
	$label  = (string) $label;
	$marker = strpos( $label, '<' );
	$text   = ( false === $marker ) ? $label : substr( $label, 0, $marker );
	$rest   = ( false === $marker ) ? '' : substr( $label, $marker );

	$renamed = blueworx_rename_display_text( rtrim( $text ), $map );

	if ( null === $renamed ) {
		return null;
	}

	// Put back whatever separated the name from the bubble, or the two run
	// together into "Commerce3".
	return $renamed . substr( $text, strlen( rtrim( $text ) ) ) . $rest;
}

/**
 * Renames plugins in the sidebar.
 *
 * Runs late enough that every plugin has registered its own rows, and records
 * which ones were renamed so the heading pass knows which screens belong to a
 * renamed plugin.
 *
 * @return void
 */
function blueworx_rename_admin_menu() {
	global $menu, $submenu;

	$map   = blueworx_plugin_display_names();
	$slugs = array();

	foreach ( (array) $menu as $index => $item ) {
		if ( ! isset( $item[0], $item[2] ) ) {
			continue;
		}

		$renamed = blueworx_rename_menu_label( $item[0], $map );

		if ( null === $renamed ) {
			continue;
		}

		$menu[ $index ][0] = $renamed; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Renaming a row in place is the only way WordPress offers to relabel the menu.
		$slugs[]           = (string) $item[2];
	}

	$pages = $slugs;

	foreach ( $slugs as $parent ) {
		if ( empty( $submenu[ $parent ] ) ) {
			continue;
		}

		foreach ( (array) $submenu[ $parent ] as $index => $item ) {
			if ( isset( $item[2] ) ) {
				$pages[] = (string) $item[2];
			}

			if ( ! isset( $item[0] ) ) {
				continue;
			}

			$renamed = blueworx_rename_menu_label( $item[0], $map );

			if ( null !== $renamed ) {
				$submenu[ $parent ][ $index ][0] = $renamed; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
			}
		}
	}

	$GLOBALS['blueworx_renamed_plugin_pages'] = array_values( array_unique( $pages ) );
}

/**
 * Whether the screen being viewed belongs to a renamed plugin.
 *
 * @return bool True on that plugin's own pages.
 */
function blueworx_on_renamed_plugin_screen() {
	if ( empty( $GLOBALS['blueworx_renamed_plugin_pages'] ) ) {
		return false;
	}

	$pages = (array) $GLOBALS['blueworx_renamed_plugin_pages'];

	foreach ( array( 'plugin_page', 'parent_file' ) as $global ) {
		$slug = isset( $GLOBALS[ $global ] ) ? (string) $GLOBALS[ $global ] : '';

		if ( '' !== $slug && in_array( $slug, $pages, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Renames the plugin in the browser tab title.
 *
 * @param string $admin_title Full title, including the site name.
 * @return string Title to show.
 */
function blueworx_rename_admin_title( $admin_title ) {
	$renamed = blueworx_rename_display_text( $admin_title, blueworx_plugin_display_names() );

	return ( null === $renamed ) ? $admin_title : $renamed;
}

/**
 * Renames the plugin in the headings on its own screens.
 *
 * The one part of this that cannot be done in PHP. These plugins draw their
 * screens in the browser, long after WordPress has finished with the page, so
 * there is no filter to hang a heading off — which is also why this watches for
 * later renders rather than running once. It is confined to headings, and only
 * on pages belonging to a plugin that was renamed, so it cannot reach into
 * anybody's content.
 *
 * @return void
 */
function blueworx_rename_screen_headings() {
	if ( ! blueworx_on_renamed_plugin_screen() ) {
		return;
	}

	$pairs = array();

	foreach ( blueworx_plugin_display_names() as $old => $new ) {
		$pairs[] = array( $old, $new );
	}

	wp_enqueue_script(
		'blueworx-display-names',
		BLUEWORX_LABS_URL . 'assets/js/display-names.js',
		array(),
		BLUEWORX_LABS_VERSION,
		true
	);

	wp_add_inline_script(
		'blueworx-display-names',
		'window.blueworxDisplayNames = ' . wp_json_encode( $pairs ) . ';',
		'before'
	);
}

if ( blueworx_feature_enabled( 'display_names' ) ) {
	add_filter( 'gettext_with_context', 'blueworx_rename_role_name', 10, 3 );
	add_filter( 'all_plugins', 'blueworx_rename_plugins_list' );
	add_filter( 'admin_title', 'blueworx_rename_admin_title' );

	// Last, once every plugin has registered its rows. The Edit Menu screen
	// reads the menu as it stands when the page renders, so it lists the new
	// names too and the two screens agree.
	add_action( 'admin_menu', 'blueworx_rename_admin_menu', 9999 );
	add_action( 'admin_enqueue_scripts', 'blueworx_rename_screen_headings' );
}
