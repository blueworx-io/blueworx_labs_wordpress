<?php
/**
 * Pricing page template.
 *
 * Ported from app/pricing/page.tsx: a `.pb-tall` centered hero containing the
 * billing toggle, the plan cards (retainer plans, via the `plan-cards` part,
 * pulled up to overlap the hero), the logos band, a feature comparison table,
 * the pricing calculator, and a static FAQ.
 *
 * The billing toggle and the pricing calculator are Plan 3 interactive widgets.
 * The toggle renders its real `.bill-toggle` markup (Monthly selected) so it
 * looks right; Plan 3 wires the click and the monthly/annual price swap on the
 * plan cards (which already carry data-price-m / data-price-a). The calculator
 * renders a labelled placeholder. The FAQ is native `<details>` until Plan 3's
 * accordion.
 *
 * The hero is composed inline rather than via the `tech-hero` part because the
 * billing toggle sits inside the hero, which the part does not model.
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

// Comparison rows. Each cell is either the string 'check' (rendered as the
// check glyph), '—' (an em dash), or literal text. A row label may carry a
// trailing question-mark glyph via 'qmark' => true.
$blueworx_pricing_cmp_head = array(
	__( 'Essential Support', 'blueworx-labs-wordpress' ),
	__( 'Growth Support', 'blueworx-labs-wordpress' ),
	__( 'Advanced Support', 'blueworx-labs-wordpress' ),
);
$blueworx_pricing_cmp_rows = array(
	array(
		'label' => __( 'Free toolbox', 'blueworx-labs-wordpress' ),
		'qmark' => true,
		'cells' => array( __( 'Basic', 'blueworx-labs-wordpress' ), __( 'Advanced', 'blueworx-labs-wordpress' ), __( 'Advanced', 'blueworx-labs-wordpress' ) ),
	),
	array(
		'label' => __( 'Template sites', 'blueworx-labs-wordpress' ),
		'cells' => array( 'check', 'check', 'check' ),
	),
	array(
		'label' => __( 'Large updates', 'blueworx-labs-wordpress' ),
		'cells' => array( 'check', 'check', 'check' ),
	),
	array(
		'label' => __( 'Small updates', 'blueworx-labs-wordpress' ),
		'cells' => array( '—', 'check', 'check' ),
	),
	array(
		'label' => __( 'Support allowance', 'blueworx-labs-wordpress' ),
		'cells' => array( '—', 'check', 'check' ),
	),
	array(
		'label' => __( 'Minor updates', 'blueworx-labs-wordpress' ),
		'cells' => array( '—', 'check', 'check' ),
	),
	array(
		'label' => __( 'Major updates', 'blueworx-labs-wordpress' ),
		'cells' => array( '—', '—', 'check' ),
	),
);

// Glyphs ported verbatim from app/pricing/page.tsx.
$blueworx_pricing_check = '<svg class="ck" width="20" height="20" viewBox="0 0 24 24" fill="#0A0C29"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm-1 14.4l-4.2-4.2 1.5-1.5 2.7 2.7 5-5 1.5 1.5z"/></svg>';
$blueworx_pricing_qmark = '<svg class="qmark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 015.8 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12" y2="17"/></svg>';

blueworx_public_document_open( array( 'body_class' => 'bw-pricing' ) );
blueworx_public_part( 'parts/nav.php' );
?>
<main>
	<div>
		<section class="tech-hero pb-tall" style="text-align:center">
			<div class="tech-inner" style="max-width:780px;margin:0 auto">
				<div class="tech-badge" style="margin-bottom:22px"><span class="dot"></span><?php esc_html_e( 'Digital Support Packages', 'blueworx-labs-wordpress' ); ?></div>
				<h1 class="h1"><?php esc_html_e( 'Choose Your ', 'blueworx-labs-wordpress' ); ?><span class="tech-grad"><?php esc_html_e( 'Support Plan', 'blueworx-labs-wordpress' ); ?></span></h1>
				<p class="lead"><?php esc_html_e( 'Choose the support plan that reflects the level of growth and support your business needs.', 'blueworx-labs-wordpress' ); ?></p>
				<div style="display:flex;justify-content:center;margin-top:34px">
					<div class="bill-toggle" data-widget="billing-toggle">
						<button class="on" type="button"><?php esc_html_e( 'Monthly billing', 'blueworx-labs-wordpress' ); ?></button>
						<button type="button"><?php esc_html_e( 'Annual billing', 'blueworx-labs-wordpress' ); ?></button>
					</div>
				</div>
			</div>
		</section>

		<?php
		blueworx_public_part(
			'parts/plan-cards.php',
			array( 'plans' => blueworx_content_retainer_plans() )
		);

		blueworx_public_part( 'parts/logos-band.php' );
		?>

		<section class="sec" style="padding-top:0">
			<div class="center-head" style="margin-bottom:40px">
				<h2 class="h2"><?php esc_html_e( 'All the features you need', 'blueworx-labs-wordpress' ); ?></h2>
				<p class="lead"><?php esc_html_e( "Compare what's included across every level of support.", 'blueworx-labs-wordpress' ); ?></p>
			</div>
			<div class="cmp-scroll">
				<table class="cmp">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Feature', 'blueworx-labs-wordpress' ); ?></th>
							<?php foreach ( $blueworx_pricing_cmp_head as $blueworx_pricing_col ) : ?>
								<th><?php echo esc_html( $blueworx_pricing_col ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $blueworx_pricing_cmp_rows as $blueworx_pricing_row ) : ?>
							<tr>
								<td>
									<?php echo esc_html( $blueworx_pricing_row['label'] ); ?>
									<?php
									if ( ! empty( $blueworx_pricing_row['qmark'] ) ) {
										// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted qmark glyph.
										echo ' ' . $blueworx_pricing_qmark;
									}
									?>
								</td>
								<?php foreach ( $blueworx_pricing_row['cells'] as $blueworx_pricing_cell ) : ?>
									<td>
										<?php
										if ( 'check' === $blueworx_pricing_cell ) {
											// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted check glyph.
											echo $blueworx_pricing_check;
										} else {
											echo esc_html( $blueworx_pricing_cell );
										}
										?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>

		<section class="sec" style="padding-top:0">
			<div class="center-head" style="margin-bottom:40px">
				<h2 class="h2"><?php esc_html_e( 'Pricing calculator', 'blueworx-labs-wordpress' ); ?></h2>
				<p class="lead"><?php esc_html_e( 'Estimate your monthly investment. Adjust the options to match your needs.', 'blueworx-labs-wordpress' ); ?></p>
			</div>
			<div class="bw-plan3-placeholder" data-widget="pricing-calc">
				<p><?php esc_html_e( 'Pricing calculator — coming soon.', 'blueworx-labs-wordpress' ); ?></p>
			</div>
		</section>

		<section class="sec" style="padding-top:0">
			<div class="center-head" style="margin-bottom:40px">
				<h2 class="h2"><?php esc_html_e( 'Frequently asked questions', 'blueworx-labs-wordpress' ); ?></h2>
				<p class="lead"><?php esc_html_e( 'Everything you need to know about the product and billing.', 'blueworx-labs-wordpress' ); ?></p>
			</div>
			<div class="faq-list">
				<?php foreach ( blueworx_content_faqs() as $blueworx_pricing_faq ) : ?>
					<details class="faq-item">
						<summary class="faq-q"><?php echo esc_html( $blueworx_pricing_faq['q'] ); ?></summary>
						<div class="faq-a"><?php echo esc_html( $blueworx_pricing_faq['a'] ); ?></div>
					</details>
				<?php endforeach; ?>
			</div>
		</section>
	</div>
</main>
<?php
blueworx_public_part( 'parts/footer.php' );
blueworx_public_document_close();
