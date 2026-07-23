<?php
/**
 * Logos band template part.
 *
 * Ported from LogosBand.tsx. The brand marks are hardcoded in the source
 * component itself (no data-layer export for them), so they stay hardcoded
 * here too rather than inventing a content accessor for a fixed, five-item
 * decorative list. No $vars are consumed — every instance renders identically,
 * matching the source, which takes no props.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blueworx_logos_brands = array( 'QURE', 'Padel365', 'HIRASTÉ', 'BlueWorx', 'chromaesthesia' );
$blueworx_logos_marks  = array_merge( $blueworx_logos_brands, $blueworx_logos_brands );
?>
<div style="margin:48px 0;padding:56px 0;display:flex;flex-direction:column;align-items:center;gap:34px;border-top:1px solid #EFEFF0;border-bottom:1px solid #EFEFF0;background:#FCFCFD">
	<div style="display:flex;align-items:center;gap:18px;width:100%;max-width:720px;padding:0 32px;box-sizing:border-box">
		<span style="flex:1;height:1px;background:linear-gradient(90deg,transparent,#E3E4E8)"></span>
		<p style="margin:0;font-size:12.5px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:#8A8F98;text-align:center">
			<?php echo esc_html__( 'Loved by owners. Trusted by businesses.', 'blueworx-labs-wordpress' ); ?>
		</p>
		<span style="flex:1;height:1px;background:linear-gradient(90deg,#E3E4E8,transparent)"></span>
	</div>
	<div class="logos-mask">
		<div class="logos-track" style="gap:64px">
			<?php foreach ( $blueworx_logos_marks as $blueworx_logos_mark ) : ?>
				<span class="brandmark"><?php echo esc_html( $blueworx_logos_mark ); ?></span>
			<?php endforeach; ?>
		</div>
	</div>
</div>
