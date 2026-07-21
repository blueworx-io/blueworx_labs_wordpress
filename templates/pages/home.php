<?php
/**
 * Home page template.
 *
 * The <main><div> wrapper is required, not stylistic: globals.css targets
 * `main > div > .sec:last-child` to zero the final section's bottom padding.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

blueworx_public_document_open( array( 'body_class' => 'bw-home' ) );
blueworx_public_part( 'parts/nav.php' );
?>
<main>
	<div>
		<section class="sec">
			<div class="center-head">
				<h1 class="h1"><?php echo esc_html__( 'BlueWorx', 'blueworx-labs-wordpress' ); ?></h1>
			</div>
		</section>
	</div>
</main>
<?php
blueworx_public_part( 'parts/footer.php' );
blueworx_public_document_close();
