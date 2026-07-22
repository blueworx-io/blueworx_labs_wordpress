<?php
/**
 * Stats band template part (`.stats-band`).
 *
 * Ported from the block duplicated inline across app/about/page.tsx and
 * app/work/page.tsx — same markup, different heading, copy and stat values,
 * so those vary per caller while the structure is shared here.
 *
 * $vars:
 * - title (string, required) The band's h2.
 * - copy  (string, required) The paragraph beneath the heading.
 * - stats (array, required)  List of array( value, label, star ), `star`
 *          optional bool — appends the gold star glyph after `value` (used
 *          for the "5.0 ★ Google Rating" stat in both source usages).
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blueworx_sb_title = isset( $title ) ? (string) $title : '';
$blueworx_sb_copy  = isset( $copy ) ? (string) $copy : '';
$blueworx_sb_stats = isset( $stats ) && is_array( $stats ) ? $stats : array();
?>
<section class="stats-band">
	<?php blueworx_blob( 'width:320px;height:320px;top:-60px;right:-100px;opacity:.16' ); ?>
	<div class="stats-grid">
		<h2 class="h2"><?php echo esc_html( $blueworx_sb_title ); ?></h2>
		<div class="stats-copy">
			<p><?php echo esc_html( $blueworx_sb_copy ); ?></p>
			<div class="stat-nums">
				<?php foreach ( $blueworx_sb_stats as $blueworx_sb_stat ) : ?>
					<div class="stat">
						<b>
							<?php echo esc_html( $blueworx_sb_stat['value'] ); ?>
							<?php if ( ! empty( $blueworx_sb_stat['star'] ) ) : ?>
								<svg width="26" height="26" viewBox="0 0 24 24" fill="#FFC107"><path d="M12 2l2.9 6.3 6.9.6-5.2 4.5 1.6 6.7L12 17l-6.2 3.6 1.6-6.7L2.2 8.9l6.9-.6z" /></svg>
							<?php endif; ?>
						</b>
						<span><?php echo esc_html( $blueworx_sb_stat['label'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
