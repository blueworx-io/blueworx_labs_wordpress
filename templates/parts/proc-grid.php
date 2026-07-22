<?php
/**
 * "How We Work" process grid template part.
 *
 * Ported from the `.proc-grid` block duplicated inline across app/page.tsx
 * (Home) and app/services/page.tsx (Services) — each page uses four steps
 * with the same shape but different wording, so only the grid markup is
 * shared here; the heading above it (eyebrow + h2, which also differs per
 * page) stays with the calling template.
 *
 * $vars:
 * - items (array, required) List of array( num, title, desc ), exactly 4
 *   in every current source usage but not enforced here.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blueworx_proc_items = isset( $items ) && is_array( $items ) ? $items : array();
?>
<div class="proc-grid">
	<?php foreach ( $blueworx_proc_items as $blueworx_proc_item ) : ?>
		<div class="proc">
			<b class="num"><?php echo esc_html( $blueworx_proc_item['num'] ); ?></b>
			<h4><?php echo esc_html( $blueworx_proc_item['title'] ); ?></h4>
			<p><?php echo esc_html( $blueworx_proc_item['desc'] ); ?></p>
		</div>
	<?php endforeach; ?>
</div>
