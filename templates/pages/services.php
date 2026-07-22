<?php
/**
 * Services page template.
 *
 * Ported from app/services/page.tsx's five sections, in source order: a
 * two-column tech-hero (copy via the `tech-hero` part in `centered => false`
 * mode, wrapped in this page's own `.tech-inner.tech-2col` — the part
 * deliberately does not own two-column layout, see tech-hero.php's doc
 * comment — plus a `glass-card` part showing three metrics and an 8-bar
 * spark), Service 01 (`.svc01-head` two-column intro, an auto-fit grid of
 * four feature-highlight cards with no matching part so it stays inline, and
 * a large bespoke analytics/browser panel with a hand-authored sparkline SVG
 * that no part could express), How It Works (a `proc-grid` part), Service 02
 * (a dark `.af-wrap` layout — checklist copy + a `glass-card` part listing
 * Toolbox tools with their bundled favicons), and Testimonials (the
 * `testimonials` part fed the real review content).
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

// A static, trusted SVG reused for the "View Support Plans" button arrow.
// Not one of blueworx_icon_paths()'s named icons — sized directly by
// `.btn svg`, the same reason home.php/about.php keep their own copy rather
// than routing through blueworx_icon(). Ported verbatim from the ARROW
// constant in app/services/page.tsx.
$blueworx_svc_arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>';

// A static, trusted green checkmark reused for every Toolbox checklist row.
// Ported verbatim from the GREEN_CHECK constant in app/services/page.tsx.
$blueworx_svc_green_check = '<div style="width:20px;height:20px;border-radius:50%;background:rgba(1,208,132,.14);display:flex;align-items:center;justify-content:center;flex-shrink:0"><svg viewBox="0 0 24 24" fill="none" stroke="#01D084" stroke-width="3" style="width:11px;height:11px"><polyline points="20 6 9 17 4 12"/></svg></div>';

$blueworx_svc_feat_highlights = array(
	array(
		'icon'  => 'palette',
		'title' => __( 'Design', 'blueworx-labs-wordpress' ),
		'desc'  => __( 'Branding, UX/UI, and custom graphics. Every touchpoint designed to convert.', 'blueworx-labs-wordpress' ),
	),
	array(
		'icon'  => 'code',
		'title' => __( 'Development', 'blueworx-labs-wordpress' ),
		'desc'  => __( 'Page builds, integrations, forms, booking systems, and e-commerce.', 'blueworx-labs-wordpress' ),
	),
	array(
		'icon'  => 'chat',
		'title' => __( 'Support', 'blueworx-labs-wordpress' ),
		'desc'  => __( 'Reactive assistance and guidance from a team that already knows your site.', 'blueworx-labs-wordpress' ),
	),
	array(
		'icon'  => 'chart',
		'title' => __( 'Reporting', 'blueworx-labs-wordpress' ),
		'desc'  => __( 'Monthly traffic and key-metric reports, in plain English.', 'blueworx-labs-wordpress' ),
	),
);

$blueworx_svc_toolbox_checks = array(
	__( '12+ premium tools, set up and managed for you', 'blueworx-labs-wordpress' ),
	__( 'Managed website hosting included in the fee', 'blueworx-labs-wordpress' ),
	__( 'Learning Center guides for every tool and system', 'blueworx-labs-wordpress' ),
	__( 'Available to individuals — and in bulk for agencies', 'blueworx-labs-wordpress' ),
);

// Matched to the bundled assets/img/tools/<slug>.png files (see
// blueworx_content_tools() in includes/public/content.php) rather than the
// source's google.com/s2/favicons lookups — this page never fetches a
// third-party favicon service.
$blueworx_svc_glass_tools = array(
	array(
		'slug' => 'surecart',
		'name' => 'SureCart',
		'sub'  => __( 'E-commerce & checkout', 'blueworx-labs-wordpress' ),
	),
	array(
		'slug' => 'sureforms',
		'name' => 'SureForms',
		'sub'  => __( 'Forms & lead capture', 'blueworx-labs-wordpress' ),
	),
	array(
		'slug' => 'surerank',
		'name' => 'SureRank',
		'sub'  => __( 'SEO & search visibility', 'blueworx-labs-wordpress' ),
	),
	array(
		'slug' => 'ottokit',
		'name' => 'OttoKit',
		'sub'  => __( 'Workflow automation', 'blueworx-labs-wordpress' ),
	),
	array(
		'slug' => 'elementor',
		'name' => 'Elementor',
		'sub'  => __( 'Visual page building', 'blueworx-labs-wordpress' ),
	),
);

blueworx_public_document_open( array( 'body_class' => 'bw-services' ) );
blueworx_public_part( 'parts/nav.php' );
?>
<main>
	<div>
		<section class="tech-hero">
			<div class="tech-inner tech-2col">
				<div class="tc-copy">
					<?php
					blueworx_public_part(
						'parts/tech-hero.php',
						array(
							'centered'        => false,
							'badge'           => __( 'Our Services', 'blueworx-labs-wordpress' ),
							'title'           => __( 'Two Services. One Accountable Team.', 'blueworx-labs-wordpress' ),
							'title_highlight' => __( 'One Accountable Team.', 'blueworx-labs-wordpress' ),
							'lead'            => __( 'An integrated dev team that works inside your business, and a Digital Toolbox that replaces a stack of individual subscriptions, hosting included.', 'blueworx-labs-wordpress' ),
							'cta'             => array(
								array(
									'label' => __( 'Get a Quote', 'blueworx-labs-wordpress' ),
									'href'  => home_url( '/pricing' ),
									'class' => 'btn btn-white btn-lg',
								),
								array(
									'label' => __( 'View Our Work', 'blueworx-labs-wordpress' ),
									'href'  => home_url( '/work' ),
									'class' => 'btn btn-outline-w btn-lg',
								),
							),
							'meta'            => array(
								__( 'integrated support', 'blueworx-labs-wordpress' ),
								__( 'digital toolbox', 'blueworx-labs-wordpress' ),
								__( 'hosting included', 'blueworx-labs-wordpress' ),
								__( 'learning center', 'blueworx-labs-wordpress' ),
							),
						)
					);
					?>
				</div>
				<?php
				ob_start();
				?>
				<div class="gc-metric"><small><?php echo esc_html__( 'Web pageviews', 'blueworx-labs-wordpress' ); ?></small><b>98,745</b><span class="up">↑ 24%</span></div>
				<div class="gc-metric"><small><?php echo esc_html__( 'Conversion rate', 'blueworx-labs-wordpress' ); ?></small><b>4.9%</b><span class="up">↑ 0.8%</span></div>
				<div class="gc-metric" style="border-bottom:none;padding-bottom:4px"><small><?php echo esc_html__( 'Core Web Vitals', 'blueworx-labs-wordpress' ); ?></small><b><?php echo esc_html__( 'Pass', 'blueworx-labs-wordpress' ); ?></b><span class="up">98 / 100</span></div>
				<div class="gc-spark">
					<i style="height:38%"></i><i style="height:60%"></i><i style="height:46%"></i><i style="height:72%"></i><i class="hi" style="height:92%"></i><i style="height:64%"></i><i style="height:80%"></i><i style="height:56%"></i>
				</div>
				<?php
				$blueworx_svc_hero_gc_body = ob_get_clean();

				blueworx_public_part(
					'parts/glass-card.php',
					array(
						'tag'    => __( 'performance.live', 'blueworx-labs-wordpress' ),
						'body'   => $blueworx_svc_hero_gc_body,
						'floats' => array(
							array(
								'icon'  => 'server',
								'label' => __( 'Uptime', 'blueworx-labs-wordpress' ),
								'value' => __( '99.9%', 'blueworx-labs-wordpress' ),
								'style' => 'bottom:-22px;left:-26px;animation-delay:.8s',
							),
						),
					)
				);
				?>
			</div>
		</section>

		<section class="sec" style="padding-top:70px;padding-bottom:76px">
			<div class="svc01-head" style="display:grid;grid-template-columns:1.05fr 0.95fr;gap:56px;align-items:end;margin-bottom:40px">
				<div>
					<div class="eyebrow" style="margin-bottom:18px"><?php echo esc_html__( 'Service 01 · Integrated Support', 'blueworx-labs-wordpress' ); ?></div>
					<h2 class="h2"><?php echo esc_html__( 'A full dev team, integrated into your business', 'blueworx-labs-wordpress' ); ?></h2>
				</div>
				<div>
					<p class="lead" style="font-size:17px"><?php echo esc_html__( 'Designers, developers, and strategists who work as an extension of your own team, without the overhead of hiring one. Request what you need; we deliver it, then keep improving it.', 'blueworx-labs-wordpress' ); ?></p>
					<div style="margin-top:18px">
						<a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="btn btn-dark btn-md">
							<?php echo esc_html__( 'View Support Plans', 'blueworx-labs-wordpress' ); ?>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted markup, see $blueworx_svc_arrow above.
							echo $blueworx_svc_arrow;
							?>
						</a>
					</div>
				</div>
			</div>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px">
				<?php foreach ( $blueworx_svc_feat_highlights as $blueworx_svc_feat ) : ?>
					<div style="background:#FBFBFE;border:1px solid #ECECF3;border-radius:16px;padding:24px 22px">
						<div class="fli-num" style="margin-bottom:16px"><?php blueworx_icon( $blueworx_svc_feat['icon'] ); ?></div>
						<h4 style="font-size:17px;font-weight:600;margin-bottom:6px"><?php echo esc_html( $blueworx_svc_feat['title'] ); ?></h4>
						<p style="font-size:14px;line-height:1.6;color:#667085"><?php echo esc_html( $blueworx_svc_feat['desc'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
			<div style="margin-top:14px;background:linear-gradient(160deg,#EEF0FF,#E4E2FA);border:1px solid #E7E7F2;border-radius:20px;padding:20px">
				<div style="background:#fff;border-radius:14px;border:1px solid #ECECF3;box-shadow:0 18px 40px rgba(10,12,41,.10);overflow:hidden">
					<div style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-bottom:1px solid #F0F0F5">
						<div style="display:flex;gap:6px">
							<i style="width:9px;height:9px;border-radius:50%;background:#FF5F57;display:block"></i>
							<i style="width:9px;height:9px;border-radius:50%;background:#FEBC2E;display:block"></i>
							<i style="width:9px;height:9px;border-radius:50%;background:#28C840;display:block"></i>
						</div>
						<div style="margin-left:6px;font-family:'SF Mono',ui-monospace,Menlo,monospace;font-size:11px;color:#A0AFC0">app.blueworx.com</div>
					</div>
					<div style="display:flex;align-items:center;gap:36px;padding:22px 28px">
						<div style="flex-shrink:0">
							<div style="font-size:12px;color:#667085;margin-bottom:4px"><?php echo esc_html__( 'Revenue this month', 'blueworx-labs-wordpress' ); ?></div>
							<div style="display:flex;align-items:center;gap:12px">
								<div style="font-family:'Helvetica Neue',var(--font-sora),sans-serif;font-size:30px;font-weight:700;letter-spacing:-1px;color:#0A0C29">$48,270</div>
								<span style="font-size:12px;font-weight:600;color:#01824C;background:#E7F6EE;padding:5px 11px;border-radius:20px">↑ 18.4%</span>
							</div>
						</div>
						<?php
						/*
						 * Bespoke, hand-authored sparkline — ported verbatim from the
						 * source's inline <svg>. Fully static (no request-varying
						 * data), so it is echoed as a trusted string rather than run
						 * through wp_kses(): blueworx_icon_allowed_svg() only allows
						 * a fixed attribute set on path/circle/rect/line/polyline/
						 * polygon and has no entry for <linearGradient>/<stop>, which
						 * this sparkline needs for its area fill.
						 */
						?>
						<svg viewBox="0 0 320 118" preserveAspectRatio="none" style="flex:1;min-width:0;height:88px;display:block">
							<defs>
								<linearGradient id="fsg" x1="0" y1="0" x2="0" y2="1">
									<stop offset="0" stop-color="#4F46E5" stop-opacity=".26" />
									<stop offset="1" stop-color="#4F46E5" stop-opacity="0" />
								</linearGradient>
							</defs>
							<path d="M0,92 L40,74 L80,84 L120,52 L160,62 L200,34 L240,46 L280,20 L320,30 L320,118 L0,118 Z" fill="url(#fsg)" />
							<path d="M0,92 L40,74 L80,84 L120,52 L160,62 L200,34 L240,46 L280,20 L320,30" fill="none" stroke="#4F46E5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
						</svg>
						<div style="flex-shrink:0;border-left:1px solid #F0F0F5;padding-left:36px">
							<div style="font-size:12px;color:#667085"><?php echo esc_html__( 'Web pageviews', 'blueworx-labs-wordpress' ); ?></div>
							<div style="font-family:'Helvetica Neue',var(--font-sora),sans-serif;font-size:26px;font-weight:700;letter-spacing:-.5px;color:#0A0C29;margin:2px 0">98,745</div>
							<div style="font-size:12px;color:#01824C"><?php echo esc_html__( '↑ this month', 'blueworx-labs-wordpress' ); ?></div>
						</div>
					</div>
				</div>
			</div>
		</section>

		<section class="sec" style="padding-top:24px">
			<div class="center-head" style="margin-bottom:40px">
				<div class="eyebrow" style="margin-bottom:20px"><?php echo esc_html__( 'How It Works', 'blueworx-labs-wordpress' ); ?></div>
				<h2 class="h2"><?php echo esc_html__( 'From first conversation to live solution', 'blueworx-labs-wordpress' ); ?></h2>
			</div>
			<?php
			blueworx_public_part(
				'parts/proc-grid.php',
				array(
					'items' => array(
						array(
							'num'   => '01',
							'title' => __( 'Talk It Through', 'blueworx-labs-wordpress' ),
							'desc'  => __( 'Come to us with a problem. We talk it through and identify exactly what your business needs.', 'blueworx-labs-wordpress' ),
						),
						array(
							'num'   => '02',
							'title' => __( 'Configure Your Package', 'blueworx-labs-wordpress' ),
							'desc'  => __( 'We configure a package around your needs and budget, then you sign up.', 'blueworx-labs-wordpress' ),
						),
						array(
							'num'   => '03',
							'title' => __( 'Scope, Design & Build', 'blueworx-labs-wordpress' ),
							'desc'  => __( 'We scope, design, and build your digital solution, powered by the Toolbox for expanded value.', 'blueworx-labs-wordpress' ),
						),
						array(
							'num'   => '04',
							'title' => __( 'Support & Grow', 'blueworx-labs-wordpress' ),
							'desc'  => __( 'Once live, we move into a support and growth role: updates, reporting, and a team on call.', 'blueworx-labs-wordpress' ),
						),
					),
				)
			);
			?>
		</section>

		<section class="sec" style="background:#0A0C29;position:relative;overflow:hidden">
			<?php blueworx_blob( 'width:340px;height:340px;top:-120px;right:-120px;opacity:.14' ); ?>
			<div class="af-wrap" style="position:relative">
				<div class="af-text">
					<div class="eyebrow" style="margin-bottom:20px;border-color:rgba(139,142,255,.28);background:rgba(139,142,255,.08);color:#B7B9FF"><?php echo esc_html__( 'Service 02 · Digital Toolbox', 'blueworx-labs-wordpress' ); ?></div>
					<h2 class="h2" style="color:#fff"><?php echo esc_html__( 'Every premium tool. One subscription.', 'blueworx-labs-wordpress' ); ?></h2>
					<p class="lead" style="margin-top:16px"><?php echo esc_html__( 'All BlueWorx clients get the full Digital Toolbox (forms, SEO, e-commerce, automation, and more) without paying for individual licences. Website hosting is included in the fee.', 'blueworx-labs-wordpress' ); ?></p>
					<div style="display:flex;flex-direction:column;gap:13px;margin:26px 0 32px">
						<?php foreach ( $blueworx_svc_toolbox_checks as $blueworx_svc_check ) : ?>
							<div style="display:flex;align-items:center;gap:11px;font-size:15px;color:rgba(226,228,255,.85)">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted markup, see $blueworx_svc_green_check above.
								echo $blueworx_svc_green_check;
								?>
								<?php echo esc_html( $blueworx_svc_check ); ?>
							</div>
						<?php endforeach; ?>
					</div>
					<div style="display:flex;gap:14px;flex-wrap:wrap">
						<a href="<?php echo esc_url( home_url( '/toolbox' ) ); ?>" class="btn btn-brand btn-md">
							<?php echo esc_html__( 'View Toolbox Plans', 'blueworx-labs-wordpress' ); ?>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted markup, see $blueworx_svc_arrow above.
							echo $blueworx_svc_arrow;
							?>
						</a>
						<a href="<?php echo esc_url( home_url( '/toolbox#savings' ) ); ?>" class="btn btn-outline-w btn-md"><?php echo esc_html__( 'Calculate Your Savings', 'blueworx-labs-wordpress' ); ?></a>
					</div>
				</div>
				<?php
				ob_start();
				?>
				<?php foreach ( $blueworx_svc_glass_tools as $blueworx_svc_tool ) : ?>
					<div class="gc-metric" style="padding:12px 0">
						<div style="display:flex;align-items:center;gap:12px">
							<div style="width:34px;height:34px;border-radius:9px;background:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
								<img src="<?php echo esc_url( BLUEWORX_LABS_URL . 'assets/img/tools/' . $blueworx_svc_tool['slug'] . '.png' ); ?>" alt="<?php echo esc_attr( $blueworx_svc_tool['name'] ); ?>" style="width:20px;height:20px;object-fit:contain" />
							</div>
							<div>
								<div style="font-size:14.5px;font-weight:600;color:#fff"><?php echo esc_html( $blueworx_svc_tool['name'] ); ?></div>
								<div style="font-size:11.5px;color:rgba(226,228,255,.5)"><?php echo esc_html( $blueworx_svc_tool['sub'] ); ?></div>
							</div>
						</div>
						<svg viewBox="0 0 24 24" fill="none" stroke="#01D084" stroke-width="3" style="width:15px;height:15px"><polyline points="20 6 9 17 4 12" /></svg>
					</div>
				<?php endforeach; ?>
				<a href="<?php echo esc_url( home_url( '/toolbox' ) ); ?>" style="display:block;margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,.09);font-size:13px;color:rgba(226,228,255,.55);text-align:center;text-decoration:none">
					<?php echo esc_html__( '+ 7 more tools, hosting & Learning Center in every plan', 'blueworx-labs-wordpress' ); ?>
				</a>
				<?php
				$blueworx_svc_toolbox_gc_body = ob_get_clean();

				blueworx_public_part(
					'parts/glass-card.php',
					array(
						'tag'    => __( 'toolbox · included', 'blueworx-labs-wordpress' ),
						'body'   => $blueworx_svc_toolbox_gc_body,
						'floats' => array(
							array(
								'icon'  => 'server',
								'label' => __( 'Hosting', 'blueworx-labs-wordpress' ),
								'value' => __( 'Included', 'blueworx-labs-wordpress' ),
								'style' => 'bottom:-22px;left:-26px;animation-delay:.7s',
							),
						),
					)
				);
				?>
			</div>
		</section>

		<?php
		blueworx_public_part(
			'parts/testimonials.php',
			array(
				'testimonials' => blueworx_content_reviews(),
			)
		);
		?>
	</div>
</main>
<?php
blueworx_public_part( 'parts/footer.php' );
blueworx_public_document_close();
