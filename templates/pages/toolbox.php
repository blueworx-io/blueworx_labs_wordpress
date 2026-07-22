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
			<?php
			$blueworx_sv_tools   = blueworx_content_tools();
			$blueworx_sv_prices  = blueworx_content_solo_prices();
			$blueworx_sv_hosting = 30;
			$blueworx_sv_toolbox = 30;
			$blueworx_sv_solo    = $blueworx_sv_hosting;
			foreach ( $blueworx_sv_tools as $blueworx_sv_tool ) {
				$blueworx_sv_solo += isset( $blueworx_sv_prices[ $blueworx_sv_tool['slug'] ] ) ? (int) $blueworx_sv_prices[ $blueworx_sv_tool['slug'] ] : 0;
			}
			$blueworx_sv_save = max( 0, $blueworx_sv_solo - $blueworx_sv_toolbox );
			?>
			<div class="calc" data-widget="savings-calc">
				<div class="calc-panel">
					<div class="sv-tools">
						<?php foreach ( $blueworx_sv_tools as $blueworx_sv_tool ) : ?>
							<?php $blueworx_sv_price = isset( $blueworx_sv_prices[ $blueworx_sv_tool['slug'] ] ) ? (int) $blueworx_sv_prices[ $blueworx_sv_tool['slug'] ] : 0; ?>
							<div class="sv-row" data-slug="<?php echo esc_attr( $blueworx_sv_tool['slug'] ); ?>" data-price="<?php echo esc_attr( (string) $blueworx_sv_price ); ?>" data-on="1" style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #F0F0F5">
								<div style="width:32px;height:32px;border-radius:9px;background:#F5F6FB;border:1px solid #EEEEF5;display:flex;align-items:center;justify-content:center;flex-shrink:0">
									<img src="<?php echo esc_url( BLUEWORX_LABS_URL . 'assets/img/tools/' . $blueworx_sv_tool['slug'] . '.png' ); ?>" alt="<?php echo esc_attr( $blueworx_sv_tool['name'] ); ?>" style="width:17px;height:17px;object-fit:contain" loading="lazy" />
								</div>
								<div style="flex:1;min-width:0">
									<div style="font-size:14px;font-weight:600;color:#0A0C29"><?php echo esc_html( $blueworx_sv_tool['name'] ); ?></div>
									<div style="font-size:12px;color:#8A8DA6">
										<?php
										/* translators: %d: monthly price in dollars. */
										echo esc_html( sprintf( __( '$%d/mo individually', 'blueworx-labs-wordpress' ), $blueworx_sv_price ) );
										?>
									</div>
								</div>
								<button type="button" class="toggle-pill on" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: tool name. */ __( 'Include %s', 'blueworx-labs-wordpress' ), $blueworx_sv_tool['name'] ) ); ?>" aria-pressed="true" style="transform:scale(.78);transform-origin:right center"></button>
							</div>
						<?php endforeach; ?>
					</div>
					<div style="display:flex;align-items:center;gap:12px;padding:14px 0 2px">
						<div style="width:32px;height:32px;border-radius:9px;background:#E8E7F7;display:flex;align-items:center;justify-content:center;flex-shrink:0">
							<?php blueworx_icon( 'server', '', 'width:16px;height:16px;display:block;color:#4338CA' ); ?>
						</div>
						<div style="flex:1;min-width:0">
							<div style="font-size:14px;font-weight:600;color:#0A0C29"><?php esc_html_e( 'Managed website hosting', 'blueworx-labs-wordpress' ); ?></div>
							<div style="font-size:12px;color:#8A8DA6"><?php esc_html_e( '$30/mo bought separately', 'blueworx-labs-wordpress' ); ?></div>
						</div>
						<span style="font-size:12px;font-weight:600;color:#178048;background:#E6F6EC;padding:5px 12px;border-radius:20px"><?php esc_html_e( 'Included', 'blueworx-labs-wordpress' ); ?></span>
					</div>
				</div>
				<div class="calc-out">
					<div class="cl"><?php esc_html_e( 'Buying everything individually', 'blueworx-labs-wordpress' ); ?></div>
					<div style="position:relative;z-index:1;font-weight:700;font-size:34px;letter-spacing:-.5px;color:rgba(255,255,255,.55);text-decoration:line-through;text-decoration-color:rgba(255,107,107,.75);text-decoration-thickness:3px">
						$<span data-testid="solo-total"><?php echo esc_html( (string) $blueworx_sv_solo ); ?></span><span style="font-size:15px;font-weight:500">/mo</span>
					</div>
					<div class="cl" style="margin-top:20px"><?php esc_html_e( 'With the BlueWorx Toolbox', 'blueworx-labs-wordpress' ); ?></div>
					<div class="cv">$30<span style="font-size:20px;font-weight:500;color:rgba(255,255,255,.6)">/mo</span></div>
					<div class="cp" style="margin-top:14px">
						<span data-testid="savings-line" style="display:inline-flex;align-items:center;gap:7px;font-size:14px;font-weight:600;color:#01D084;background:rgba(1,208,132,.12);border:1px solid rgba(1,208,132,.3);padding:8px 16px;border-radius:100px">
							<?php
							/* translators: 1: monthly saving, 2: yearly saving (thousands-separated). */
							echo esc_html( sprintf( __( 'You save $%1$s/mo · $%2$s/yr', 'blueworx-labs-wordpress' ), number_format_i18n( $blueworx_sv_save ), number_format_i18n( $blueworx_sv_save * 12 ) ) );
							?>
						</span>
					</div>
					<a href="<?php echo esc_url( home_url( '/contact' ) ); ?>" class="btn btn-brand btn-md" style="width:100%;text-decoration:none"><?php esc_html_e( 'Get the Toolbox', 'blueworx-labs-wordpress' ); ?></a>
				</div>
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
