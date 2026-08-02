<?php
/**
 * Robots.txt manager.
 *
 * Replaces the Admin and Site Enhancements `manage_robots_txt` module.
 *
 * Off by default, and inert whenever a real robots.txt file exists on disk: the
 * `robots_txt` filter WordPress offers only applies to the virtual file it
 * generates itself, so a site with a physical file would save happily here and
 * see nothing change. Rather than let that happen silently, the settings screen
 * says so.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the starting content offered when nothing has been saved.
 *
 * Mirrors what WordPress generates for a public site, plus the site's sitemap,
 * so a site owner opening the box for the first time is editing what they
 * already have rather than starting from a blank page.
 *
 * @return string robots.txt content.
 */
function blueworx_robots_txt_default() {
	$lines = array(
		'User-agent: *',
		'Allow: /wp-admin/admin-ajax.php',
		'Disallow: /wp-admin/',
		'',
		'Sitemap: ' . home_url( '/wp-sitemap.xml' ),
	);

	return implode( "\n", $lines );
}

/**
 * Gets the saved robots.txt content.
 *
 * @return string Saved content, or the default when nothing is saved.
 */
function blueworx_robots_txt_content() {
	$stored = get_option( 'blueworx_robots_txt', null );

	if ( ! is_string( $stored ) || '' === trim( $stored ) ) {
		return blueworx_robots_txt_default();
	}

	return $stored;
}

/**
 * Normalises submitted robots.txt content.
 *
 * Line endings are collapsed to \n, and every line is stripped of tags and
 * control characters. Nothing is validated beyond that: robots.txt is a
 * free-form file, agents ignore directives they do not understand, and a plugin
 * second-guessing which ones are legitimate would break the day a crawler adds
 * a new one.
 *
 * @param string $raw Submitted content.
 * @return string Normalised content.
 */
function blueworx_robots_txt_sanitize( $raw ) {
	$raw   = str_replace( array( "\r\n", "\r" ), "\n", (string) $raw );
	$lines = array();

	foreach ( explode( "\n", $raw ) as $line ) {
		$line    = wp_strip_all_tags( $line );
		$line    = preg_replace( '/[^\P{C}\t]+/u', '', $line );
		$lines[] = rtrim( (string) $line );
	}

	// Cap the stored value: robots.txt is a short file and an unbounded textarea
	// straight into an option is the kind of thing that ends up holding a page of
	// pasted HTML.
	return substr( implode( "\n", $lines ), 0, 16384 );
}

/**
 * Whether a real robots.txt file exists on disk at the site root.
 *
 * WordPress only serves a virtual robots.txt when no real file is there, so a
 * physical file silently wins over anything saved here.
 *
 * @return bool True when a physical file shadows the virtual one.
 */
function blueworx_robots_txt_file_exists() {
	return file_exists( trailingslashit( ABSPATH ) . 'robots.txt' );
}

/**
 * Serves the saved content in place of the generated robots.txt.
 *
 * Returns the saved value outright rather than appending to core's output. Two
 * `User-agent: *` blocks in one file is not a merge — crawlers read the first
 * matching group and the rest is dead text, so appending would look like it
 * worked and quietly not.
 *
 * The site's "discourage search engines" setting is deliberately NOT allowed to
 * veto this. That was the first shape of this function and it was wrong: a site
 * owner who has switched the feature on and written a file has said what they
 * want, and quietly serving something else instead is exactly the failure a
 * robots manager exists to prevent. The two settings can genuinely disagree, so
 * the settings screen warns about it where somebody can see and act on it,
 * rather than the disagreement being resolved invisibly at request time.
 *
 * @param string $output    Generated robots.txt content.
 * @param bool   $is_public Whether the site is set to be indexed.
 * @return string robots.txt content.
 */
function blueworx_robots_txt_filter( $output, $is_public ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Part of the filter signature; see above for why it is deliberately not acted on.
	$content = blueworx_robots_txt_content();

	return '' === trim( $content ) ? $output : $content . "\n";
}

if ( blueworx_feature_enabled( 'robots_txt' ) ) {
	add_filter( 'robots_txt', 'blueworx_robots_txt_filter', 20, 2 );
}
