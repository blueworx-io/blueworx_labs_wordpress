<?php
/**
 * Testimonials template part.
 *
 * Ported from Testimonials.tsx. Unlike the source (which always fetches
 * HOME_REVIEWS itself via getTestimonials()), the reviews are passed in via
 * $vars so a page using a different testimonial set (e.g. Work's own local
 * TESTIMONIALS array, Task 5) can reuse this same markup instead of
 * duplicating it — the source itself already diverges here (Work hand-rolls
 * an identical `.tg`/`.tc` block with different copy and heading).
 *
 * $vars:
 * - testimonials (array, required) List of array( text, initials, name, role ).
 * - eyebrow       (string, optional) Defaults to the source's own copy.
 * - title         (string, optional) Defaults to the source's own copy.
 * - style         (string, optional) Inline style for the outer <section>,
 *                  mirroring the source component's `style` prop.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blueworx_tst_items   = isset( $testimonials ) && is_array( $testimonials ) ? $testimonials : array();
$blueworx_tst_eyebrow = isset( $eyebrow ) ? (string) $eyebrow : __( 'What our clients say', 'blueworx-labs-wordpress' );
$blueworx_tst_title   = isset( $title ) ? (string) $title : __( 'Kind words from our customers', 'blueworx-labs-wordpress' );
$blueworx_tst_style   = isset( $style ) ? (string) $style : '';
?>
<section class="sec"<?php echo '' === $blueworx_tst_style ? '' : ' style="' . esc_attr( $blueworx_tst_style ) . '"'; ?>>
	<div class="center-head" style="margin-bottom:40px">
		<div class="eyebrow" style="margin-bottom:20px"><?php echo esc_html( $blueworx_tst_eyebrow ); ?></div>
		<h2 class="h2"><?php echo esc_html( $blueworx_tst_title ); ?></h2>
	</div>
	<div class="tg">
		<?php foreach ( $blueworx_tst_items as $blueworx_tst_item ) : ?>
			<div class="tc">
				<div class="tstars">★★★★★</div>
				<p class="ttext"><?php echo esc_html( $blueworx_tst_item['text'] ); ?></p>
				<div class="tauthor">
					<div class="tavatar"><?php echo esc_html( $blueworx_tst_item['initials'] ); ?></div>
					<div>
						<div class="tname"><?php echo esc_html( $blueworx_tst_item['name'] ); ?></div>
						<div class="trole"><?php echo esc_html( $blueworx_tst_item['role'] ); ?></div>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>
