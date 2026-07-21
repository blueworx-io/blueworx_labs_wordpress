<?php
/**
 * Public front-end layer — template rendering.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Opens a complete HTML document for a plugin-rendered page.
 *
 * Deliberately does NOT call get_header(): the plugin renders the whole
 * document so the site is identical regardless of the active theme, which
 * matters because hosting is not fixed. wp_head() is still called so other
 * plugins, the admin bar and SEO output all keep working.
 *
 * @param array $args Optional. 'body_class' => string.
 * @return void
 */
function blueworx_public_document_open( $args = array() ) {
	$body_class = isset( $args['body_class'] ) ? (string) $args['body_class'] : '';
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'bw-page ' . $body_class ); ?>>
	<?php
}

/**
 * Closes the document opened by blueworx_public_document_open().
 *
 * @return void
 */
function blueworx_public_document_close() {
	wp_footer();
	?>
</body>
</html>
	<?php
}

/**
 * Renders a template part with scoped variables.
 *
 * @param string $relative Path under templates/, e.g. 'parts/nav.php'.
 * @param array  $vars     Variables extracted into the part's scope.
 * @return void
 */
function blueworx_public_part( $relative, $vars = array() ) {
	$path = BLUEWORX_LABS_PATH . 'templates/' . ltrim( $relative, '/' );

	if ( ! file_exists( $path ) ) {
		return;
	}

	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- scoped to a template part with a caller-controlled array.
	extract( $vars, EXTR_SKIP );

	require $path;
}
