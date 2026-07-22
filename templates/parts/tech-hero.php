<?php
/**
 * Generic marketing-page hero template part (`.tech-hero`).
 *
 * Ported from the centered hero in app/about/page.tsx. Services and Work use
 * the same `.tech-hero`/`.tech-badge`/`.h1`/`.tech-grad`/`.lead` pieces inside
 * a two-column `.tech-inner.tech-2col` layout (copy on the left, a
 * `glass-card` part on the right) instead of this part's centered wrapper —
 * `centered => false` renders just the copy (badge/heading/lead/cta/meta)
 * with no outer `<section>`/`.tech-inner`, so a two-column page composes its
 * own `<section class="tech-hero"><div class="tech-inner tech-2col"><div
 * class="tc-copy">` around it and puts a glass-card part in the second
 * column itself, rather than this part trying to own every hero layout.
 *
 * $vars:
 * - title            (string, required) Full heading text (plain, not HTML).
 * - title_highlight  (string, optional) A substring of `title` to wrap in
 *                     `<span class="tech-grad">` (the source's gradient
 *                     accent phrase). Must be an exact substring of `title`;
 *                     ignored otherwise.
 * - badge            (string, optional) `.tech-badge` label, e.g.
 *                     "About BlueWorx". Omitted entirely when empty.
 * - lead             (string, optional) The `.lead` paragraph beneath the
 *                     heading.
 * - centered         (bool, optional) Default true. See above.
 * - max_width        (int, optional) Centered mode only. Inner max width in px.
 *                     Default 820 (About). Contact uses 780.
 * - extra_class      (string, optional) Centered mode only. Extra class(es) on
 *                     the `<section>`, e.g. "pb-tall" for Pricing's taller hero.
 * - cta              (array, optional) List of array( label, href, class )
 *                     rendered as buttons after the lead.
 * - meta             (array, optional) List of plain label strings rendered
 *                     as `.tech-status` pills after the CTA row.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blueworx_th_title     = isset( $title ) ? (string) $title : '';
$blueworx_th_highlight = isset( $title_highlight ) ? (string) $title_highlight : '';
$blueworx_th_badge     = isset( $badge ) ? (string) $badge : '';
$blueworx_th_lead      = isset( $lead ) ? (string) $lead : '';
$blueworx_th_centered  = ! isset( $centered ) || (bool) $centered;
$blueworx_th_cta       = isset( $cta ) && is_array( $cta ) ? $cta : array();
$blueworx_th_meta      = isset( $meta ) && is_array( $meta ) ? $meta : array();
$blueworx_th_maxw      = isset( $max_width ) ? (int) $max_width : 820;
$blueworx_th_extra     = isset( $extra_class ) ? trim( (string) $extra_class ) : '';

// Builds the heading markup once, wrapping `title_highlight` (when it is an
// actual substring of the title) in the `.tech-grad` gradient span. Built
// inline rather than as a helper function: this file can, in principle, be
// required more than once per request via blueworx_public_part(), and a
// `function` declaration here would fatal ("cannot redeclare") on a second
// include — see work-card.php/svc-card.php for the same buffer-then-echo
// pattern used for the same reason.
if ( '' !== $blueworx_th_highlight && false !== strpos( $blueworx_th_title, $blueworx_th_highlight ) ) {
	list( $blueworx_th_before, $blueworx_th_after ) = explode( $blueworx_th_highlight, $blueworx_th_title, 2 );

	$blueworx_th_title_html = esc_html( $blueworx_th_before ) . '<span class="tech-grad">' . esc_html( $blueworx_th_highlight ) . '</span>' . esc_html( $blueworx_th_after );
} else {
	$blueworx_th_title_html = esc_html( $blueworx_th_title );
}

if ( $blueworx_th_centered ) :
	?>
	<section class="tech-hero<?php echo '' === $blueworx_th_extra ? '' : ' ' . esc_attr( $blueworx_th_extra ); ?>" style="text-align:center;padding-bottom:72px">
		<div class="tech-inner" style="max-width:<?php echo (int) $blueworx_th_maxw; ?>px;margin:0 auto">
<?php endif; ?>
			<?php if ( '' !== $blueworx_th_badge ) : ?>
				<div class="tech-badge"<?php echo $blueworx_th_centered ? ' style="margin-bottom:22px"' : ''; ?>><span class="dot"></span><?php echo esc_html( $blueworx_th_badge ); ?></div>
			<?php endif; ?>
			<h1 class="h1"<?php echo $blueworx_th_centered ? '' : ' style="margin:22px 0 0"'; ?>>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $blueworx_th_title_html is built above entirely from esc_html() calls, not raw input.
				echo $blueworx_th_title_html;
				?>
			</h1>
			<?php if ( '' !== $blueworx_th_lead ) : ?>
				<p class="lead" style="<?php echo $blueworx_th_centered ? 'max-width:560px;margin:22px auto 0' : 'max-width:520px;margin:22px 0 34px'; ?>"><?php echo esc_html( $blueworx_th_lead ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $blueworx_th_cta ) ) : ?>
				<div style="display:flex;gap:14px;flex-wrap:wrap">
					<?php foreach ( $blueworx_th_cta as $blueworx_th_button ) : ?>
						<a href="<?php echo esc_url( $blueworx_th_button['href'] ); ?>" class="<?php echo esc_attr( $blueworx_th_button['class'] ); ?>"><?php echo esc_html( $blueworx_th_button['label'] ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $blueworx_th_meta ) ) : ?>
				<div class="tech-status">
					<?php foreach ( $blueworx_th_meta as $blueworx_th_meta_item ) : ?>
						<span><?php echo esc_html( $blueworx_th_meta_item ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
<?php if ( $blueworx_th_centered ) : ?>
		</div>
	</section>
<?php endif; ?>
