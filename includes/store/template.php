<?php
/**
 * The whole document for the three pages this plugin dresses: checkout, the
 * thank-you page after it, and the customer dashboard.
 *
 * Without this they render inside the theme's page template, which draws its
 * own header above and its own footer below — so the checkout carried the
 * site's footer, several hundred pixels of it, beneath its own, and a
 * matching slab of theme header above. The frame is a full-screen checkout in
 * the approved design; it cannot be that while something else owns the page.
 *
 * This runs WordPress's real loop rather than calling a renderer:
 * blueworx_store_dress_content() is a the_content filter guarded on
 * in_the_loop() and is_main_query(), so the loop is what makes it fire — and
 * the frame keeps being built exactly where it was, with nothing about it
 * changed here.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
	<?php wp_footer(); ?>
</body>
</html>
