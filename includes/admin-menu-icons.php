<?php
/**
 * Admin menu icons.
 *
 * Inline SVGs lifted verbatim from the "WordPress Admin v2" design export. All
 * share viewBox 0 0 24 24, fill none, stroke currentColor, stroke-width 1.75, so
 * they inherit the menu label's colour in every state.
 *
 * Only mapped core slugs are swapped. Unrecognised third-party menus keep their
 * own dashicon — there is nothing to map them to, and blanking them would be
 * worse than an inconsistent glyph.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the allowlist for passing inline SVG through wp_kses().
 *
 * @return array Allowed tags and attributes.
 */
function blueworx_get_svg_kses_allowlist() {
	$shared = array(
		'fill'         => true,
		'stroke'       => true,
		'stroke-width' => true,
	);

	return array(
		'svg'    => array(
			'viewbox'         => true,
			'width'           => true,
			'height'          => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'class'           => true,
			'aria-hidden'     => true,
			'focusable'       => true,
		),
		'path'   => array_merge( $shared, array( 'd' => true ) ),
		'rect'   => array_merge( $shared, array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true ) ),
		'circle' => array_merge( $shared, array( 'cx' => true, 'cy' => true, 'r' => true ) ),
	);
}

/**
 * Gets the slug => SVG inner-markup map.
 *
 * @return array SVG inner markup keyed by menu slug.
 */
function blueworx_get_admin_menu_icon_paths() {
	return array(
		// Dashboard — grid.
		'index.php'               => '<rect x="3" y="3" width="8" height="8" rx="1.5"></rect><rect x="13" y="3" width="8" height="8" rx="1.5"></rect><rect x="3" y="13" width="8" height="8" rx="1.5"></rect><rect x="13" y="13" width="8" height="8" rx="1.5"></rect>',
		// BlueWorx console — layout-panel-top.
		'blueworx-labs-wordpress' => '<rect x="3" y="3" width="18" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect>',
		// Posts — document.
		'edit.php'                => '<path d="M6 3h9l4 4v14H6z"></path><path d="M9 12h7M9 16h7M9 8h3"></path>',
		// Media — image.
		'upload.php'              => '<rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="8.5" cy="9.5" r="1.5"></circle><path d="M21 16l-5.5-5.5L9 17"></path>',
		// Pages — layers.
		'edit.php?post_type=page' => '<path d="M12 3l9 5-9 5-9-5 9-5z"></path><path d="M3 13l9 5 9-5"></path>',
		// Appearance — palette.
		'themes.php'              => '<path d="M12 3a9 9 0 100 18 1.5 1.5 0 001.1-2.5 1.5 1.5 0 011.1-2.5H17a4 4 0 004-4c0-5-4-9-9-9z"></path><circle cx="7.5" cy="11.5" r="1"></circle><circle cx="10.5" cy="7.5" r="1"></circle><circle cx="15" cy="8" r="1"></circle>',
		// Plugins — puzzle.
		'plugins.php'             => '<path d="M9 4v2a2 2 0 002 2 2 2 0 002-2V4h4v4h-2a2 2 0 000 4h2v4h-4v-2a2 2 0 00-2-2 2 2 0 00-2 2v2H5v-4h2a2 2 0 000-4H5V8h4z"></path>',
		// Users — user.
		'users.php'               => '<circle cx="12" cy="8" r="3.5"></circle><path d="M4.5 20c1.5-4 4.5-6 7.5-6s6 2 7.5 6"></path>',
		// Tools — wrench.
		'tools.php'               => '<path d="M14.7 6.3a4 4 0 00-5.3 5.3L3 18v3h3l6.4-6.4a4 4 0 005.3-5.3l-2.9 2.9-2.1-2.1 2.9-2.9z"></path>',
		// Settings — gear.
		'options-general.php'     => '<circle cx="12" cy="12" r="3"></circle><path d="M19 12a7 7 0 00-.1-1.2l2-1.5-2-3.4-2.3.9a7 7 0 00-2-1.2L14 3h-4l-.6 2.6a7 7 0 00-2 1.2l-2.3-.9-2 3.4 2 1.5A7 7 0 005 12c0 .4 0 .8.1 1.2l-2 1.5 2 3.4 2.3-.9a7 7 0 002 1.2L10 21h4l.6-2.6a7 7 0 002-1.2l2.3.9 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z"></path>',
		// Custom content — shapes (custom post types and unmapped plugin menus).
		'bw-custom-post-type'     => '<path d="M8.3 10a.7.7 0 01-.6-1.1L11.4 3a.7.7 0 011.2 0l3.7 5.9a.7.7 0 01-.6 1.1z"></path><rect x="3" y="14" width="7" height="7" rx="1.5"></rect><circle cx="17.5" cy="17.5" r="3.5"></circle>',
		// Log Out — log-out.
		'bw-logout'               => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"></path><path d="M16 17l5-5-5-5M21 12H9"></path>',
	);
}

/**
 * Gets the inline SVG for a menu slug.
 *
 * @param string $slug Menu slug, or a bw-* pseudo key.
 * @param int    $size Pixel size.
 * @return string SVG markup, or an empty string when the slug is unmapped.
 */
function blueworx_get_admin_menu_icon( $slug, $size = 19 ) {
	$paths = blueworx_get_admin_menu_icon_paths();
	$slug  = (string) $slug;

	if ( ! isset( $paths[ $slug ] ) && 0 === strpos( $slug, 'edit.php?post_type=' ) ) {
		$slug = 'bw-custom-post-type';
	}

	if ( ! isset( $paths[ $slug ] ) ) {
		return '';
	}

	return sprintf(
		'<svg class="bw-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" width="%1$d" height="%1$d" aria-hidden="true" focusable="false">%2$s</svg>',
		(int) $size,
		$paths[ $slug ]
	);
}
