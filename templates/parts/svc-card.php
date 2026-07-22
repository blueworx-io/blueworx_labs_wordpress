<?php
/**
 * Service/feature card template part (`.svc`).
 *
 * Ported from the richer, linked card markup inline in app/page.tsx's `.svc2`
 * section (icon + "Service NN" eyebrow + heading + copy + chips + link row).
 * The same `.svc` class also wraps a plainer icon+title+desc-only shape
 * elsewhere in the source (About's Why cards, a tool detail page's feature
 * cards) — every field below except `icon`, `title` and `desc` is optional
 * so this one part covers both: omit `eyebrow`/`chips`/`link_text`/`href`
 * for the plain shape, or supply them for Home's richer service card.
 *
 * $vars:
 * - icon      (string, required) Icon key from blueworx_icon_paths().
 * - title     (string, required) Card heading.
 * - desc      (string, required) Card body copy.
 * - eyebrow   (string, optional) Small uppercase label top-right of the icon.
 * - chips     (array, optional)  List of short chip label strings.
 * - link_text (string, optional) Trailing link/arrow row text.
 * - href      (string, optional) When set (with link_text), the whole card
 *              is a link to this URL; otherwise it renders as a <div>.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blueworx_svc_icon      = isset( $icon ) ? (string) $icon : '';
$blueworx_svc_title     = isset( $title ) ? (string) $title : '';
$blueworx_svc_desc      = isset( $desc ) ? (string) $desc : '';
$blueworx_svc_eyebrow   = isset( $eyebrow ) ? (string) $eyebrow : '';
$blueworx_svc_chips     = isset( $chips ) && is_array( $chips ) ? $chips : array();
$blueworx_svc_link_text = isset( $link_text ) ? (string) $link_text : '';
$blueworx_svc_href      = isset( $href ) ? (string) $href : '';
$blueworx_svc_rich      = ( '' !== $blueworx_svc_eyebrow || ! empty( $blueworx_svc_chips ) || '' !== $blueworx_svc_link_text );

ob_start();
if ( $blueworx_svc_rich ) :
	?>
	<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px">
		<div class="svc-ic" style="margin-bottom:0"><?php blueworx_icon( $blueworx_svc_icon ); ?></div>
		<?php if ( '' !== $blueworx_svc_eyebrow ) : ?>
			<span style="font-family:'SF Mono',ui-monospace,Menlo,monospace;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#A0AFC0"><?php echo esc_html( $blueworx_svc_eyebrow ); ?></span>
		<?php endif; ?>
	</div>
	<h3 style="font-size:26px;letter-spacing:-.6px"><?php echo esc_html( $blueworx_svc_title ); ?></h3>
	<p style="font-size:15.5px;max-width:460px"><?php echo esc_html( $blueworx_svc_desc ); ?></p>
	<?php if ( ! empty( $blueworx_svc_chips ) ) : ?>
		<div style="display:flex;flex-wrap:wrap;gap:8px;margin:2px 0 20px">
			<?php foreach ( $blueworx_svc_chips as $blueworx_svc_chip ) : ?>
				<span class="svc-chip"><?php echo esc_html( $blueworx_svc_chip ); ?></span>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php if ( '' !== $blueworx_svc_link_text ) : ?>
		<span class="svc-link">
			<?php echo esc_html( $blueworx_svc_link_text ); ?>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="7" y1="17" x2="17" y2="7" /><polyline points="7 7 17 7 17 17" /></svg>
		</span>
	<?php endif; ?>
	<?php
else :
	?>
	<div class="svc-ic"><?php blueworx_icon( $blueworx_svc_icon ); ?></div>
	<h3><?php echo esc_html( $blueworx_svc_title ); ?></h3>
	<p><?php echo esc_html( $blueworx_svc_desc ); ?></p>
	<?php
endif;
$blueworx_svc_body = ob_get_clean();
?>
<?php if ( '' !== $blueworx_svc_href && '' !== $blueworx_svc_link_text ) : ?>
	<a class="svc" href="<?php echo esc_url( $blueworx_svc_href ); ?>" style="padding:42px 40px;display:block;color:inherit;text-decoration:none">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $blueworx_svc_body is built above from the same esc_html()/esc_url() calls, not raw input.
		echo $blueworx_svc_body;
		?>
	</a>
<?php else : ?>
	<div class="svc">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $blueworx_svc_body is built above from the same esc_html()/esc_url() calls, not raw input.
		echo $blueworx_svc_body;
		?>
	</div>
<?php endif; ?>
