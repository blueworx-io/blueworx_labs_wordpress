<?php
/**
 * Glass card visual template part (`.glass-wrap` > `.glass-card`).
 *
 * Ported from the `.glass-card` shell duplicated inline across app/page.tsx
 * (Home: a status header + a timeline of steps), app/services/page.tsx and
 * app/work/page.tsx (both: a status header + `.gc-metric` rows + a
 * `.gc-spark` bar chart). The header (dots, tag, optional status pill) and
 * outer chrome are identical everywhere and shared here; the varying content
 * beneath it is caller-composed markup passed in as `body` — none of it is
 * user input, every caller builds it from its own esc_html()/esc_attr() calls
 * (see templates/pages/home.php's timeline block) the same way work-card.php
 * and svc-card.php buffer their own bodies.
 *
 * $vars:
 * - tag           (string, required) The `.gc-tag` label, e.g. "results.log".
 * - body           (string, required) Pre-escaped HTML rendered below the
 *                   header (the source's `.gc-metric`/`.gc-spark` rows or,
 *                   for Home, its timeline rows).
 * - status_label   (string, optional) When set, renders a right-aligned
 *                   status pill (dot + label) in the header, e.g. "On track".
 * - status_color   (string, optional) CSS color for the status pill; defaults
 *                   to the source's "On track" green.
 * - style          (string, optional) Inline style override for `.glass-card`
 *                   itself (Home overrides padding to 28px; Services/Work use
 *                   the CSS default and pass nothing).
 * - floats         (array, optional) List of array( icon, label, value, style )
 *                   rendered as `.tech-float` chips positioned around the
 *                   card via each entry's own `style` (matches the source's
 *                   per-instance top/right/bottom/left/animation-delay values).
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blueworx_gc_tag          = isset( $tag ) ? (string) $tag : '';
$blueworx_gc_body         = isset( $body ) ? (string) $body : '';
$blueworx_gc_status_label = isset( $status_label ) ? (string) $status_label : '';
$blueworx_gc_status_color = isset( $status_color ) ? (string) $status_color : '#01D084';
$blueworx_gc_style        = isset( $style ) ? (string) $style : '';
$blueworx_gc_floats       = isset( $floats ) && is_array( $floats ) ? $floats : array();
$blueworx_gc_default_glow = ( '#01D084' === $blueworx_gc_status_color );
?>
<div class="glass-wrap">
	<div class="glass-card"<?php echo '' === $blueworx_gc_style ? '' : ' style="' . esc_attr( $blueworx_gc_style ) . '"'; ?>>
		<div class="gc-scan"></div>
		<div class="gc-head">
			<div class="gc-dots"><i></i><i></i><i></i></div>
			<div class="gc-tag"><?php echo esc_html( $blueworx_gc_tag ); ?></div>
			<?php if ( '' !== $blueworx_gc_status_label ) : ?>
				<span style="display:inline-flex;align-items:center;gap:6px;font-family:'SF Mono',ui-monospace,Menlo,monospace;font-size:10.5px;letter-spacing:.12em;text-transform:uppercase;color:<?php echo esc_attr( $blueworx_gc_status_color ); ?>">
					<i style="width:6px;height:6px;border-radius:50%;background:<?php echo esc_attr( $blueworx_gc_status_color ); ?>;<?php echo $blueworx_gc_default_glow ? 'box-shadow:0 0 8px rgba(1,208,132,.7);' : ''; ?>display:block"></i>
					<?php echo esc_html( $blueworx_gc_status_label ); ?>
				</span>
			<?php endif; ?>
		</div>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $blueworx_gc_body is caller-composed, pre-escaped markup (see doc comment), never raw input.
		echo $blueworx_gc_body;
		?>
	</div>
	<?php foreach ( $blueworx_gc_floats as $blueworx_gc_float ) : ?>
		<div class="tech-float"<?php echo empty( $blueworx_gc_float['style'] ) ? '' : ' style="' . esc_attr( $blueworx_gc_float['style'] ) . '"'; ?>>
			<div class="tf-ic"><?php blueworx_icon( $blueworx_gc_float['icon'] ); ?></div>
			<div><small><?php echo esc_html( $blueworx_gc_float['label'] ); ?></small><b><?php echo esc_html( $blueworx_gc_float['value'] ); ?></b></div>
		</div>
	<?php endforeach; ?>
</div>
