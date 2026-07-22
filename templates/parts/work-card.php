<?php
/**
 * Single work/project card template part (`.work-card`, sits inside a
 * `.work-grid`).
 *
 * Ported from the card markup duplicated across app/page.tsx (Home, linked),
 * app/about/page.tsx (linked, adds a description paragraph) and
 * app/work/page.tsx (plain, non-linked divs — Work links each card to
 * nowhere in the source, so a missing `href` renders a <div>, not an <a>,
 * rather than inventing a destination).
 *
 * $vars:
 * - img_url   (string, required) Absolute image URL.
 * - alt       (string, required) Image alt text.
 * - tags      (array, required)  List of tag label strings.
 * - name      (string, required) Card heading.
 * - res_value (string, required) Bold result figure, e.g. "+64%".
 * - res_text  (string, required) Result caption following the bold figure.
 * - desc      (string, optional) Extra description paragraph (About's
 *              Client Success Stories cards only).
 * - href      (string, optional) When set, the whole card is a link to this
 *              (already-escaped-safe, e.g. home_url()) URL.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blueworx_wc_img_url   = isset( $img_url ) ? (string) $img_url : '';
$blueworx_wc_alt       = isset( $alt ) ? (string) $alt : '';
$blueworx_wc_tags      = isset( $tags ) && is_array( $tags ) ? $tags : array();
$blueworx_wc_name      = isset( $name ) ? (string) $name : '';
$blueworx_wc_res_value = isset( $res_value ) ? (string) $res_value : '';
$blueworx_wc_res_text  = isset( $res_text ) ? (string) $res_text : '';
$blueworx_wc_desc      = isset( $desc ) ? (string) $desc : '';
$blueworx_wc_href      = isset( $href ) ? (string) $href : '';

ob_start();
?>
<div class="work-img"><img src="<?php echo esc_url( $blueworx_wc_img_url ); ?>" alt="<?php echo esc_attr( $blueworx_wc_alt ); ?>" /></div>
<div class="work-meta">
	<div class="work-tags">
		<?php foreach ( $blueworx_wc_tags as $blueworx_wc_tag ) : ?>
			<span class="work-tag"><?php echo esc_html( $blueworx_wc_tag ); ?></span>
		<?php endforeach; ?>
	</div>
	<h3><?php echo esc_html( $blueworx_wc_name ); ?></h3>
	<?php if ( '' !== $blueworx_wc_desc ) : ?>
		<p style="font-size:14.5px;line-height:1.6;color:#4C4C4C;margin:10px 0 14px"><?php echo esc_html( $blueworx_wc_desc ); ?></p>
	<?php endif; ?>
	<div class="res"><b><?php echo esc_html( $blueworx_wc_res_value ); ?></b> <?php echo esc_html( $blueworx_wc_res_text ); ?></div>
</div>
<?php
$blueworx_wc_body = ob_get_clean();
?>
<?php if ( '' !== $blueworx_wc_href ) : ?>
	<a class="work-card" href="<?php echo esc_url( $blueworx_wc_href ); ?>" style="display:block;color:inherit;text-decoration:none">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $blueworx_wc_body is built above from the same esc_html()/esc_url()/esc_attr() calls, not raw input.
		echo $blueworx_wc_body;
		?>
	</a>
<?php else : ?>
	<div class="work-card">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $blueworx_wc_body is built above from the same esc_html()/esc_url()/esc_attr() calls, not raw input.
		echo $blueworx_wc_body;
		?>
	</div>
<?php endif; ?>
