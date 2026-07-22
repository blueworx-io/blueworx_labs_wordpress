<?php
/**
 * Dark Toolbox grid band (`.tbx`), used by the Home and Toolbox pages.
 *
 * Ported from the ToolboxGrid component. Renders every tool from
 * blueworx_content_tools() as a card linking to its detail page, with the
 * bundled favicon (assets/img/tools/<slug>.png), not a Google request.
 *
 * $vars:
 * - title (string, optional) Heading. Defaults to the Home/Toolbox heading.
 * - sub   (string, optional) Sub-copy under the heading.
 * - tools (array,  optional) Tool list; defaults to blueworx_content_tools().
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blueworx_tbx_title = isset( $title ) ? (string) $title : __( 'Do more with the BlueWorx Toolbox', 'blueworx-labs-wordpress' );
$blueworx_tbx_sub   = isset( $sub ) ? (string) $sub : __( 'Access premium tools, reduce unnecessary costs, and run your digital operations more efficiently.', 'blueworx-labs-wordpress' );
$blueworx_tbx_tools = isset( $tools ) && is_array( $tools ) ? $tools : blueworx_content_tools();

// Static trusted arrow glyph, sized by `.tbx-arrow svg`.
$blueworx_tbx_arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>';
?>
<section class="tbx">
	<?php blueworx_blob( 'width:320px;height:320px;top:-100px;left:-120px;opacity:.14' ); ?>
	<div class="tbx-head">
		<h2 class="h2"><?php echo esc_html( $blueworx_tbx_title ); ?></h2>
		<p><?php echo esc_html( $blueworx_tbx_sub ); ?></p>
	</div>
	<div class="tbx-grid">
		<?php foreach ( $blueworx_tbx_tools as $blueworx_tbx_tool ) : ?>
			<a href="<?php echo esc_url( home_url( '/toolbox/' . $blueworx_tbx_tool['slug'] ) ); ?>" class="tbx-card" style="text-decoration:none">
				<div class="tbx-top">
					<div class="tbx-logo"><img src="<?php echo esc_url( BLUEWORX_LABS_URL . 'assets/img/tools/' . $blueworx_tbx_tool['slug'] . '.png' ); ?>" alt="<?php echo esc_attr( $blueworx_tbx_tool['name'] ); ?>" loading="lazy" /></div>
					<span class="tbx-arrow">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted arrow glyph.
						echo $blueworx_tbx_arrow;
						?>
					</span>
				</div>
				<h4><?php echo esc_html( $blueworx_tbx_tool['name'] ); ?></h4>
				<p><?php echo esc_html( $blueworx_tbx_tool['desc'] ); ?></p>
			</a>
		<?php endforeach; ?>
	</div>
</section>
