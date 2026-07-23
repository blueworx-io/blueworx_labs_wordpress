<?php
/**
 * Toolbox (archive) page template.
 *
 * Ported from app/toolbox/page.tsx: a `.pb-tall` centered hero with the billing
 * toggle, the toolbox plan cards (via the `plan-cards` part), the logos band, a
 * feature comparison table, the savings calculator, a static FAQ, and the dark
 * toolbox grid (via the `toolbox-grid` part).
 *
 * The billing toggle and the savings calculator are Plan 3 interactive widgets:
 * the toggle renders its real markup (Monthly selected) and Plan 3 wires the
 * price swap; the calculator is a labelled placeholder in the `#savings`
 * section. The FAQ is native `<details>` until Plan 3's accordion. The hero is
 * composed inline because the billing toggle sits inside it.
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

$blueworx_toolbox_cmp_head = array(
	__( 'Personal', 'blueworx-labs-wordpress' ),
	__( 'Business', 'blueworx-labs-wordpress' ),
	__( 'Agency', 'blueworx-labs-wordpress' ),
);
$blueworx_toolbox_cmp_rows = array(
	array(
		'label' => __( 'All 12+ premium tools', 'blueworx-labs-wordpress' ),
		'cells' => array( 'check', 'check', 'check' ),
	),
	array(
		'label' => __( 'Managed website hosting', 'blueworx-labs-wordpress' ),
		'qmark' => true,
		'cells' => array( __( 'Included', 'blueworx-labs-wordpress' ), __( 'Included', 'blueworx-labs-wordpress' ), __( 'Included', 'blueworx-labs-wordpress' ) ),
	),
	array(
		'label' => __( 'Websites covered', 'blueworx-labs-wordpress' ),
		'cells' => array( '1', __( 'Up to 5', 'blueworx-labs-wordpress' ), __( 'Up to 25', 'blueworx-labs-wordpress' ) ),
	),
	array(
		'label' => __( 'Learning Center access', 'blueworx-labs-wordpress' ),
		'cells' => array( 'check', 'check', 'check' ),
	),
	array(
		'label' => __( 'Site stability support', 'blueworx-labs-wordpress' ),
		'cells' => array( 'check', 'check', 'check' ),
	),
	array(
		'label' => __( 'Priority support', 'blueworx-labs-wordpress' ),
		'cells' => array( '—', 'check', 'check' ),
	),
	array(
		'label' => __( 'Bulk licensing for client sites', 'blueworx-labs-wordpress' ),
		'cells' => array( '—', '—', 'check' ),
	),
	array(
		'label' => __( 'Dedicated account manager', 'blueworx-labs-wordpress' ),
		'cells' => array( '—', '—', 'check' ),
	),
);

$blueworx_toolbox_check = '<svg class="ck" width="20" height="20" viewBox="0 0 24 24" fill="#0A0C29"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm-1 14.4l-4.2-4.2 1.5-1.5 2.7 2.7 5-5 1.5 1.5z"/></svg>';
$blueworx_toolbox_qmark = '<svg class="qmark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 015.8 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12" y2="17"/></svg>';

blueworx_public_document_open( array( 'body_class' => 'bw-toolbox' ) );
blueworx_public_part( 'parts/nav.php' );
?>
<main>
	<div>
		<section class="tech-hero pb-tall" style="text-align:center">
			<div class="tech-inner" style="max-width:780px;margin:0 auto">
				<div class="tech-badge" style="margin-bottom:22px"><span class="dot"></span><?php esc_html_e( 'BlueWorx Toolbox', 'blueworx-labs-wordpress' ); ?></div>
				<h1 class="h1"><?php esc_html_e( 'Choose Your ', 'blueworx-labs-wordpress' ); ?><span class="tech-grad"><?php esc_html_e( 'Toolbox Plan', 'blueworx-labs-wordpress' ); ?></span></h1>
				<p class="lead" style="max-width:580px;margin:22px auto 0"><?php esc_html_e( 'One subscription replaces a stack of individual licences. Every tool is set up and managed for you, with website hosting included. For BlueWorx clients, individuals, and agencies buying in bulk.', 'blueworx-labs-wordpress' ); ?></p>
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
			array( 'plans' => blueworx_content_toolbox_plans() )
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
							<?php foreach ( $blueworx_toolbox_cmp_head as $blueworx_toolbox_col ) : ?>
								<th><?php echo esc_html( $blueworx_toolbox_col ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $blueworx_toolbox_cmp_rows as $blueworx_toolbox_row ) : ?>
							<tr>
								<td>
									<?php echo esc_html( $blueworx_toolbox_row['label'] ); ?>
									<?php
									if ( ! empty( $blueworx_toolbox_row['qmark'] ) ) {
										// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted qmark glyph.
										echo ' ' . $blueworx_toolbox_qmark;
									}
									?>
								</td>
								<?php foreach ( $blueworx_toolbox_row['cells'] as $blueworx_toolbox_cell ) : ?>
									<td>
										<?php
										if ( 'check' === $blueworx_toolbox_cell ) {
											// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted check glyph.
											echo $blueworx_toolbox_check;
										} else {
											echo esc_html( $blueworx_toolbox_cell );
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

		<section class="sec" id="savings" style="padding-top:0">
			<div class="center-head" style="margin-bottom:40px">
				<h2 class="h2"><?php esc_html_e( 'See what you save', 'blueworx-labs-wordpress' ); ?></h2>
				<p class="lead"><?php esc_html_e( "Compare the Toolbox to paying for each tool individually. Toggle off anything you wouldn't buy on its own.", 'blueworx-labs-wordpress' ); ?></p>
			</div>
			<div class="bw-plan3-placeholder" data-widget="savings-calc">
				<p><?php esc_html_e( 'Savings calculator — coming soon.', 'blueworx-labs-wordpress' ); ?></p>
			</div>
		</section>

		<section class="sec" style="padding-top:0">
			<div class="center-head" style="margin-bottom:40px">
				<h2 class="h2"><?php esc_html_e( 'Frequently asked questions', 'blueworx-labs-wordpress' ); ?></h2>
				<p class="lead"><?php esc_html_e( 'Everything you need to know about the product and billing.', 'blueworx-labs-wordpress' ); ?></p>
			</div>
			<div class="faq-list">
				<?php foreach ( blueworx_content_faqs() as $blueworx_toolbox_faq ) : ?>
					<details class="faq-item">
						<summary class="faq-q"><?php echo esc_html( $blueworx_toolbox_faq['q'] ); ?></summary>
						<div class="faq-a"><?php echo esc_html( $blueworx_toolbox_faq['a'] ); ?></div>
					</details>
				<?php endforeach; ?>
			</div>
		</section>

		<?php blueworx_public_part( 'parts/toolbox-grid.php' ); ?>
	</div>
</main>
<?php
blueworx_public_part( 'parts/footer.php' );
blueworx_public_document_close();
