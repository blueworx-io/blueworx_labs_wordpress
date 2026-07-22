<?php
/**
 * Home page template.
 *
 * Ported from app/page.tsx's nine sections, in source order: home-hero (a
 * timeline glass-card visual + the scrolling service ticker, both bespoke to
 * this page, so they stay inline rather than becoming shared parts), "What
 * We Do" (`.svc2`, two svc-card parts), LogosBand, Selected Work (three
 * work-card parts), FeatureTabs (a Plan 3 interactive widget — renders a
 * labelled static placeholder here, see the note at that section below), How
 * We Work (a proc-grid part), Ongoing Partnership (`.split`, bespoke — the
 * source never reuses this collab-list/collab-visual layout elsewhere),
 * ToolboxGrid (inline: not one of the parts this task builds, and its only
 * other consumer, the Toolbox archive page, is Task 9, not this one) and
 * Testimonials (a testimonials part fed the real review content).
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

// A static, trusted SVG reused several times below (button arrows, the
// "Explore …" service-card links). Not one of blueworx_icon_paths()'s named
// icons: those are always wrapped in a `span[data-ic]` sized to 100%/100%,
// but this arrow is sized directly by `.btn svg`/`.svc-link svg` rules in
// public.css, so going through blueworx_icon() would break its sizing.
// Ported verbatim from the ARROW constant in app/page.tsx.
$blueworx_home_arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>';

// The five-step delivery timeline shown inside the hero's glass card. Kept
// local to this page (not a shared part's $vars) because no other source
// page reuses this exact shape — Services/Work's own glass cards render
// `.gc-metric` rows instead, which is why glass-card.php's contract is a
// generic pre-built `body` slot rather than trying to model this timeline.
// Ported verbatim from TimelineRow's per-state styling in app/page.tsx.
$blueworx_home_timeline = array(
	array(
		'state'  => 'done',
		'title'  => __( 'Discovery call', 'blueworx-labs-wordpress' ),
		'desc'   => __( 'Goals, scope & strategy agreed', 'blueworx-labs-wordpress' ),
		'status' => __( 'Done', 'blueworx-labs-wordpress' ),
	),
	array(
		'state'  => 'done',
		'title'  => __( 'Design', 'blueworx-labs-wordpress' ),
		'desc'   => __( 'On-brand, conversion-first layouts', 'blueworx-labs-wordpress' ),
		'status' => __( 'Done', 'blueworx-labs-wordpress' ),
	),
	array(
		'state'  => 'current',
		'icon'   => 'code',
		'title'  => __( 'Development', 'blueworx-labs-wordpress' ),
		'desc'   => __( 'Fast, responsive build in progress', 'blueworx-labs-wordpress' ),
		'status' => __( 'In progress', 'blueworx-labs-wordpress' ),
	),
	array(
		'state'  => 'todo',
		'icon'   => 'server',
		'title'  => __( 'Deploy', 'blueworx-labs-wordpress' ),
		'desc'   => __( 'Launch on managed hosting', 'blueworx-labs-wordpress' ),
		'status' => __( 'Queued', 'blueworx-labs-wordpress' ),
	),
	array(
		'state'  => 'todo',
		'icon'   => 'chat',
		'title'  => __( 'Support & growth', 'blueworx-labs-wordpress' ),
		'desc'   => __( 'Updates, SEO & a team on call', 'blueworx-labs-wordpress' ),
		'status' => __( 'Always on', 'blueworx-labs-wordpress' ),
	),
);

$blueworx_home_timeline_circle = array(
	'done'    => array(
		'background' => 'linear-gradient(rgba(1,208,132,.13),rgba(1,208,132,.13)),#10122E',
		'border'     => '1px solid rgba(1,208,132,.4)',
	),
	'current' => array(
		'background' => 'linear-gradient(rgba(139,142,255,.16),rgba(139,142,255,.16)),#10122E',
		'border'     => '1px solid rgba(154,155,255,.55)',
		'box-shadow' => '0 0 16px rgba(122,124,255,.35)',
	),
	'todo'    => array(
		'background' => 'linear-gradient(rgba(255,255,255,.05),rgba(255,255,255,.05)),#10122E',
		'border'     => '1px solid rgba(139,142,255,.25)',
	),
);

$blueworx_home_status_colors = array(
	'done'    => '#01D084',
	'current' => '#B9BAFF',
	'todo'    => 'rgba(226,228,255,.4)',
);

ob_start();
?>
<div style="position:relative;display:flex;flex-direction:column">
	<div style="position:absolute;left:17.5px;top:29px;bottom:29px;width:1px;margin-left:-0.5px;background:linear-gradient(180deg,rgba(1,208,132,.45) 0%,rgba(1,208,132,.45) 30%,rgba(139,142,255,.22) 50%,rgba(139,142,255,.22) 100%)"></div>
	<?php foreach ( $blueworx_home_timeline as $blueworx_home_step ) : ?>
		<?php
		$blueworx_home_circle       = $blueworx_home_timeline_circle[ $blueworx_home_step['state'] ];
		$blueworx_home_circle_style = 'background:' . $blueworx_home_circle['background'] . ';border:' . $blueworx_home_circle['border'] . ';' . ( isset( $blueworx_home_circle['box-shadow'] ) ? 'box-shadow:' . $blueworx_home_circle['box-shadow'] . ';' : '' );
		$blueworx_home_status_color = $blueworx_home_status_colors[ $blueworx_home_step['state'] ];
		$blueworx_home_title_color  = 'todo' === $blueworx_home_step['state'] ? 'rgba(255,255,255,.75)' : '#fff';
		$blueworx_home_desc_color   = 'todo' === $blueworx_home_step['state'] ? 'rgba(226,228,255,.45)' : 'rgba(226,228,255,.5)';
		$blueworx_home_icon_color   = 'current' === $blueworx_home_step['state'] ? '#B9BAFF' : 'rgba(226,228,255,.45)';
		?>
		<div style="position:relative;display:flex;align-items:center;gap:15px;padding:11px 0">
			<div style="width:35px;height:35px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;<?php echo esc_attr( $blueworx_home_circle_style ); ?>">
				<?php if ( 'done' === $blueworx_home_step['state'] ) : ?>
					<svg viewBox="0 0 24 24" fill="none" stroke="#01D084" stroke-width="2.5" style="width:14px;height:14px"><polyline points="20 6 9 17 4 12" /></svg>
				<?php elseif ( ! empty( $blueworx_home_step['icon'] ) ) : ?>
					<span style="width:15px;height:15px;color:<?php echo esc_attr( $blueworx_home_icon_color ); ?>"><?php blueworx_icon( $blueworx_home_step['icon'] ); ?></span>
				<?php endif; ?>
			</div>
			<div style="flex:1">
				<div style="font-size:15px;font-weight:600;color:<?php echo esc_attr( $blueworx_home_title_color ); ?>"><?php echo esc_html( $blueworx_home_step['title'] ); ?></div>
				<div style="font-size:12.5px;color:<?php echo esc_attr( $blueworx_home_desc_color ); ?>;margin-top:1px"><?php echo esc_html( $blueworx_home_step['desc'] ); ?></div>
			</div>
			<span style="font-family:'SF Mono',ui-monospace,Menlo,monospace;font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:<?php echo esc_attr( $blueworx_home_status_color ); ?>"><?php echo esc_html( $blueworx_home_step['status'] ); ?></span>
		</div>
	<?php endforeach; ?>
</div>
<?php
$blueworx_home_timeline_body = ob_get_clean();

$blueworx_home_ticker = array(
	__( 'strategy & UX', 'blueworx-labs-wordpress' ),
	__( 'web & platform design', 'blueworx-labs-wordpress' ),
	__( 'e-commerce builds', 'blueworx-labs-wordpress' ),
	__( 'SEO & growth', 'blueworx-labs-wordpress' ),
	__( 'managed hosting', 'blueworx-labs-wordpress' ),
	__( 'brand & identity', 'blueworx-labs-wordpress' ),
	__( 'automation', 'blueworx-labs-wordpress' ),
	__( 'ongoing support', 'blueworx-labs-wordpress' ),
);
$blueworx_home_ticker = array_merge( $blueworx_home_ticker, $blueworx_home_ticker );

$blueworx_home_collab_items = array(
	array(
		'icon'  => 'server',
		'label' => __( 'Maintain', 'blueworx-labs-wordpress' ),
	),
	array(
		'icon'  => 'plug',
		'label' => __( 'Integrate', 'blueworx-labs-wordpress' ),
	),
	array(
		'icon'  => 'chart',
		'label' => __( 'Improve', 'blueworx-labs-wordpress' ),
	),
	array(
		'icon'  => 'clock',
		'label' => __( 'Optimise', 'blueworx-labs-wordpress' ),
	),
);

blueworx_public_document_open( array( 'body_class' => 'bw-home' ) );
blueworx_public_part( 'parts/nav.php' );
?>
<main>
	<div>
		<section class="home-hero">
			<div class="hh-inner">
				<div class="hh-copy">
					<div class="tech-badge"><span class="dot"></span><?php echo esc_html__( 'Digital Agency & Platform', 'blueworx-labs-wordpress' ); ?></div>
					<h1 class="h1">
						<?php echo esc_html__( 'We Design, Build & Grow', 'blueworx-labs-wordpress' ); ?>
						<span class="tech-grad"><?php echo esc_html__( 'Digital Solutions', 'blueworx-labs-wordpress' ); ?></span>
						<?php echo esc_html__( 'That Win Business', 'blueworx-labs-wordpress' ); ?>
					</h1>
					<p class="lead"><?php echo esc_html__( 'BlueWorx is the agency behind high-performing digital solutions: websites, platforms, and automations. Strategy, design, build, hosting, and ongoing support from one dedicated team.', 'blueworx-labs-wordpress' ); ?></p>
					<div class="hh-cta">
						<a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="btn btn-white btn-lg"><?php echo esc_html__( 'Get a Quote', 'blueworx-labs-wordpress' ); ?></a>
						<a href="<?php echo esc_url( home_url( '/work' ) ); ?>" class="btn btn-outline-w btn-lg">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted markup, see $blueworx_home_arrow above.
							echo $blueworx_home_arrow;
							?>
							<?php echo esc_html__( 'View Our Work', 'blueworx-labs-wordpress' ); ?>
						</a>
					</div>
					<div class="hh-stats">
						<div><b><?php echo esc_html__( '82+', 'blueworx-labs-wordpress' ); ?></b><span><?php echo esc_html__( 'Projects delivered', 'blueworx-labs-wordpress' ); ?></span></div>
						<div>
							<b>
								4.9
								<svg width="20" height="20" viewBox="0 0 24 24" fill="#FFB300"><path d="M12 2l2.9 6.3 6.9.6-5.2 4.5 1.6 6.7L12 17l-6.2 3.6 1.6-6.7L2.2 8.9l6.9-.6z" /></svg>
							</b>
							<span><?php echo esc_html__( 'Google rating', 'blueworx-labs-wordpress' ); ?></span>
						</div>
						<div><b><?php echo esc_html__( '99.9%', 'blueworx-labs-wordpress' ); ?></b><span><?php echo esc_html__( 'Uptime maintained', 'blueworx-labs-wordpress' ); ?></span></div>
					</div>
				</div>
				<div class="hh-visual">
					<div class="hh-ring"></div>
					<?php
					blueworx_public_part(
						'parts/glass-card.php',
						array(
							'tag'          => __( 'yourproject · status', 'blueworx-labs-wordpress' ),
							'status_label' => __( 'On track', 'blueworx-labs-wordpress' ),
							'style'        => 'padding:28px',
							'body'         => $blueworx_home_timeline_body,
							'floats'       => array(
								array(
									'icon'  => 'clock',
									'label' => __( 'Avg. launch', 'blueworx-labs-wordpress' ),
									'value' => __( '3–6 weeks', 'blueworx-labs-wordpress' ),
									'style' => 'top:-22px;right:-26px;animation-delay:.4s',
								),
								array(
									'icon'  => 'server',
									'label' => __( 'Uptime', 'blueworx-labs-wordpress' ),
									'value' => __( 'Live · 99.9%', 'blueworx-labs-wordpress' ),
									'style' => 'bottom:-22px;left:-26px;animation-delay:1.1s',
								),
							),
						)
					);
					?>
				</div>
			</div>
			<div class="hh-ticker">
				<div class="hh-ticker-mask">
					<div class="hh-ticker-track">
						<?php foreach ( $blueworx_home_ticker as $blueworx_home_ticker_item ) : ?>
							<span><?php echo esc_html( $blueworx_home_ticker_item ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</section>

		<section class="sec" style="padding-bottom:0">
			<div class="center-head" style="margin-bottom:40px">
				<div class="eyebrow" style="margin-bottom:20px"><?php echo esc_html__( 'What We Do', 'blueworx-labs-wordpress' ); ?></div>
				<h2 class="h2"><?php echo esc_html__( 'Two Services. Everything Your Business Needs Online.', 'blueworx-labs-wordpress' ); ?></h2>
			</div>
			<div class="svc2">
				<?php
				blueworx_public_part(
					'parts/svc-card.php',
					array(
						'icon'      => 'users',
						'eyebrow'   => __( 'Service 01', 'blueworx-labs-wordpress' ),
						'title'     => __( 'Integrated Support', 'blueworx-labs-wordpress' ),
						'desc'      => __( 'A full design and development team, integrated into your business. We scope, design, and build your digital solution, then stay on as your support and growth partner.', 'blueworx-labs-wordpress' ),
						'chips'     => array(
							__( 'Design', 'blueworx-labs-wordpress' ),
							__( 'Development', 'blueworx-labs-wordpress' ),
							__( 'Support', 'blueworx-labs-wordpress' ),
							__( 'Reporting', 'blueworx-labs-wordpress' ),
						),
						'link_text' => __( 'Explore Integrated Support', 'blueworx-labs-wordpress' ),
						'href'      => home_url( '/services' ),
					)
				);
				blueworx_public_part(
					'parts/svc-card.php',
					array(
						'icon'      => 'plug',
						'eyebrow'   => __( 'Service 02', 'blueworx-labs-wordpress' ),
						'title'     => __( 'Digital Toolbox', 'blueworx-labs-wordpress' ),
						'desc'      => __( 'Every premium tool your business needs, from forms and SEO to e-commerce and automation, in one subscription with hosting included. No individual licences to manage.', 'blueworx-labs-wordpress' ),
						'chips'     => array(
							__( '12+ premium tools', 'blueworx-labs-wordpress' ),
							__( 'Hosting included', 'blueworx-labs-wordpress' ),
							__( 'Learning Center', 'blueworx-labs-wordpress' ),
						),
						'link_text' => __( 'Explore the Toolbox', 'blueworx-labs-wordpress' ),
						'href'      => home_url( '/toolbox' ),
					)
				);
				?>
			</div>
		</section>

		<?php blueworx_public_part( 'parts/logos-band.php' ); ?>

		<section class="sec" style="padding-top:0">
			<div style="display:flex;align-items:flex-end;justify-content:space-between;gap:24px;flex-wrap:wrap;margin-bottom:40px">
				<div>
					<div class="eyebrow" style="margin-bottom:20px"><?php echo esc_html__( 'Selected Work', 'blueworx-labs-wordpress' ); ?></div>
					<h2 class="h2" style="max-width:560px"><?php echo esc_html__( 'Recent Projects, Real Results', 'blueworx-labs-wordpress' ); ?></h2>
				</div>
				<a href="<?php echo esc_url( home_url( '/work' ) ); ?>" class="btn btn-outline btn-md">
					<?php echo esc_html__( 'View All Work', 'blueworx-labs-wordpress' ); ?>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted markup, see $blueworx_home_arrow above.
					echo $blueworx_home_arrow;
					?>
				</a>
			</div>
			<div class="work-grid">
				<?php
				blueworx_public_part(
					'parts/work-card.php',
					array(
						'img_url'   => BLUEWORX_LABS_URL . 'assets/img/feature-image-1.jpg',
						'alt'       => __( 'Hirasté website', 'blueworx-labs-wordpress' ),
						'tags'      => array( __( 'Web Design', 'blueworx-labs-wordpress' ), __( 'Booking Platform', 'blueworx-labs-wordpress' ) ),
						'name'      => 'Hirasté',
						'res_value' => __( '+64%', 'blueworx-labs-wordpress' ),
						'res_text'  => __( 'group booking enquiries', 'blueworx-labs-wordpress' ),
						'href'      => home_url( '/work' ),
					)
				);
				blueworx_public_part(
					'parts/work-card.php',
					array(
						'img_url'   => BLUEWORX_LABS_URL . 'assets/img/feature-image-3.jpg',
						'alt'       => __( 'Padel365 website', 'blueworx-labs-wordpress' ),
						'tags'      => array( __( 'E-commerce', 'blueworx-labs-wordpress' ), __( 'Court Booking', 'blueworx-labs-wordpress' ) ),
						'name'      => 'Padel365',
						'res_value' => __( 'Sold-out', 'blueworx-labs-wordpress' ),
						'res_text'  => __( 'launch season', 'blueworx-labs-wordpress' ),
						'href'      => home_url( '/work' ),
					)
				);
				blueworx_public_part(
					'parts/work-card.php',
					array(
						'img_url'   => BLUEWORX_LABS_URL . 'assets/img/feature-image-4.jpg',
						'alt'       => __( 'QURE website', 'blueworx-labs-wordpress' ),
						'tags'      => array( __( 'Brand', 'blueworx-labs-wordpress' ), __( 'Web Build', 'blueworx-labs-wordpress' ) ),
						'name'      => 'QURE',
						'res_value' => __( '+38%', 'blueworx-labs-wordpress' ),
						'res_text'  => __( 'conversion rate', 'blueworx-labs-wordpress' ),
						'href'      => home_url( '/work' ),
					)
				);
				?>
			</div>
		</section>

		<?php
		/*
		 * FeatureTabs is a Plan 3 interactive widget (tabbed feature
		 * showcase driven by client-side JS) — out of scope here. This
		 * static, clearly-labelled placeholder keeps the page whole and the
		 * section rhythm intact until Plan 3 mounts the real widget in its
		 * place.
		 */
		?>
		<div class="bw-plan3-placeholder" data-widget="feature-tabs">
			<p><?php echo esc_html__( 'Interactive feature showcase — coming soon.', 'blueworx-labs-wordpress' ); ?></p>
		</div>

		<section class="sec" style="padding-bottom:80px">
			<div class="center-head" style="margin-bottom:40px">
				<div class="eyebrow" style="margin-bottom:20px"><?php echo esc_html__( 'How We Work', 'blueworx-labs-wordpress' ); ?></div>
				<h2 class="h2"><?php echo esc_html__( 'From First Conversation to Long-Term Partner', 'blueworx-labs-wordpress' ); ?></h2>
			</div>
			<?php
			blueworx_public_part(
				'parts/proc-grid.php',
				array(
					'items' => array(
						array(
							'num'   => '01',
							'title' => __( 'Talk It Through', 'blueworx-labs-wordpress' ),
							'desc'  => __( 'Bring us the problem. We talk it through together and identify exactly what your business needs.', 'blueworx-labs-wordpress' ),
						),
						array(
							'num'   => '02',
							'title' => __( 'Configure Your Package', 'blueworx-labs-wordpress' ),
							'desc'  => __( 'We shape a package around your needs and budget. You sign up when it fits, not before.', 'blueworx-labs-wordpress' ),
						),
						array(
							'num'   => '03',
							'title' => __( 'Scope, Design & Build', 'blueworx-labs-wordpress' ),
							'desc'  => __( 'We scope, design, and build your digital solution, powered by the BlueWorx Toolbox.', 'blueworx-labs-wordpress' ),
						),
						array(
							'num'   => '04',
							'title' => __( 'Support & Grow', 'blueworx-labs-wordpress' ),
							'desc'  => __( 'After launch we move into a support and growth role: your dev team, on call.', 'blueworx-labs-wordpress' ),
						),
					),
				)
			);
			?>
		</section>

		<section class="split" style="padding-top:20px">
			<div>
				<div class="eyebrow" style="margin-bottom:20px"><?php echo esc_html__( 'Ongoing Partnership', 'blueworx-labs-wordpress' ); ?></div>
				<h2 class="h2"><?php echo esc_html__( 'Integrate smarter, collaborate better, and scale with BlueWorx', 'blueworx-labs-wordpress' ); ?></h2>
				<p class="lead" style="font-size:18px;margin:18px 0 30px"><?php echo esc_html__( 'Through ongoing support and retainer services, BlueWorx works alongside your team to support your digital solutions as your business grows.', 'blueworx-labs-wordpress' ); ?></p>
				<div class="collab-list">
					<?php foreach ( $blueworx_home_collab_items as $blueworx_home_collab_item ) : ?>
						<div class="fli" style="border-bottom:none;padding:10px 0">
							<div class="fli-icon"><?php blueworx_icon( $blueworx_home_collab_item['icon'] ); ?></div>
							<span style="font-size:17px"><?php echo esc_html( $blueworx_home_collab_item['label'] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
				<div style="margin-top:30px">
					<a href="<?php echo esc_url( home_url( '/services' ) ); ?>" class="btn btn-outline btn-md">
						<?php echo esc_html__( 'Find Out More', 'blueworx-labs-wordpress' ); ?>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted markup, see $blueworx_home_arrow above.
						echo $blueworx_home_arrow;
						?>
					</a>
				</div>
			</div>
			<div class="collab-visual">
				<img src="<?php echo esc_url( BLUEWORX_LABS_URL . 'assets/img/fig-collab.jpg' ); ?>" alt="<?php echo esc_attr__( 'BlueWorx collaboration tools', 'blueworx-labs-wordpress' ); ?>" />
				<div class="collab-chip" style="top:26px;left:-14px">
					<div class="ci" style="background:#E8E7F7"><span style="width:20px;height:20px;color:#4F46E5"><?php blueworx_icon( 'chart' ); ?></span></div>
					<div><small><?php echo esc_html__( 'Conversion', 'blueworx-labs-wordpress' ); ?></small><b><?php echo esc_html__( '+38.6%', 'blueworx-labs-wordpress' ); ?></b></div>
				</div>
				<div class="collab-chip" style="bottom:30px;right:-14px">
					<div class="ci" style="background:#E7F6EE"><span style="width:20px;height:20px;color:#01824C"><?php blueworx_icon( 'server' ); ?></span></div>
					<div><small><?php echo esc_html__( 'Uptime', 'blueworx-labs-wordpress' ); ?></small><b><?php echo esc_html__( '99.9%', 'blueworx-labs-wordpress' ); ?></b></div>
				</div>
			</div>
		</section>

		<section class="tbx">
			<?php blueworx_blob( 'width:320px;height:320px;top:-100px;left:-120px;opacity:.14' ); ?>
			<div class="tbx-head">
				<h2 class="h2"><?php echo esc_html__( 'Do more with the BlueWorx Toolbox', 'blueworx-labs-wordpress' ); ?></h2>
				<p><?php echo esc_html__( 'Access premium tools, reduce unnecessary costs, and run your digital operations more efficiently.', 'blueworx-labs-wordpress' ); ?></p>
			</div>
			<div class="tbx-grid">
				<?php foreach ( blueworx_content_tools() as $blueworx_home_tool ) : ?>
					<a href="<?php echo esc_url( home_url( '/toolbox/' . $blueworx_home_tool['slug'] ) ); ?>" class="tbx-card" style="text-decoration:none">
						<div class="tbx-top">
							<div class="tbx-logo"><img src="<?php echo esc_url( BLUEWORX_LABS_URL . 'assets/img/tools/' . $blueworx_home_tool['slug'] . '.png' ); ?>" alt="<?php echo esc_attr( $blueworx_home_tool['name'] ); ?>" loading="lazy" /></div>
							<span class="tbx-arrow">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted markup, see $blueworx_home_arrow above.
								echo $blueworx_home_arrow;
								?>
							</span>
						</div>
						<h4><?php echo esc_html( $blueworx_home_tool['name'] ); ?></h4>
						<p><?php echo esc_html( $blueworx_home_tool['desc'] ); ?></p>
					</a>
				<?php endforeach; ?>
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
