<?php
/**
 * Work page template.
 *
 * Ported from app/work/page.tsx's four sections, in source order: a two-column
 * tech-hero (the `tech-hero` part in centered => false mode, wrapped in this
 * page's own `.tech-inner.tech-2col`, plus a `glass-card` part showing a
 * results.log with three metrics and an 8-bar spark), a `.work-grid` of six
 * non-linked project cards (the `work-card` part in its plain `<div>` mode —
 * no href), a `stats-band` part, and a testimonials section using Work's own
 * three testimonials (not the shared homepage reviews) and its own heading, via
 * the `testimonials` part's overridable eyebrow/title/testimonials vars.
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

// The six projects. Non-linked cards (the source renders plain <div>s), so no
// href is passed to the work-card part. Images are the re-encoded assets:
// feature-image-1..4 and fig-collab are .jpg; hero-image stays .png.
$blueworx_work_projects = array(
	array(
		'image'    => 'feature-image-1.jpg',
		'alt'      => __( 'Hirasté website', 'blueworx-labs-wordpress' ),
		'tags'     => array( __( 'Web Design', 'blueworx-labs-wordpress' ), __( 'Booking Platform', 'blueworx-labs-wordpress' ) ),
		'name'     => 'Hirasté',
		'res'      => '+64%',
		'res_text' => __( 'group booking enquiries', 'blueworx-labs-wordpress' ),
	),
	array(
		'image'    => 'feature-image-3.jpg',
		'alt'      => __( 'Padel365 website', 'blueworx-labs-wordpress' ),
		'tags'     => array( __( 'E-commerce', 'blueworx-labs-wordpress' ), __( 'Court Booking', 'blueworx-labs-wordpress' ) ),
		'name'     => 'Padel365',
		'res'      => __( 'Sold-out', 'blueworx-labs-wordpress' ),
		'res_text' => __( 'launch season', 'blueworx-labs-wordpress' ),
	),
	array(
		'image'    => 'feature-image-4.jpg',
		'alt'      => __( 'QURE website', 'blueworx-labs-wordpress' ),
		'tags'     => array( __( 'Brand', 'blueworx-labs-wordpress' ), __( 'Web Build', 'blueworx-labs-wordpress' ) ),
		'name'     => 'QURE',
		'res'      => '+38%',
		'res_text' => __( 'conversion rate', 'blueworx-labs-wordpress' ),
	),
	array(
		'image'    => 'feature-image-2.jpg',
		'alt'      => __( 'Bloom & Co. website', 'blueworx-labs-wordpress' ),
		'tags'     => array( __( 'Migration', 'blueworx-labs-wordpress' ), __( 'Managed Hosting', 'blueworx-labs-wordpress' ) ),
		'name'     => 'Bloom & Co.',
		'res'      => __( 'Zero-downtime', 'blueworx-labs-wordpress' ),
		'res_text' => __( 'platform migration', 'blueworx-labs-wordpress' ),
	),
	array(
		'image'    => 'fig-collab.jpg',
		'alt'      => __( 'chromaesthesia website', 'blueworx-labs-wordpress' ),
		'tags'     => array( __( 'Web Design', 'blueworx-labs-wordpress' ), __( 'CMS', 'blueworx-labs-wordpress' ) ),
		'name'     => 'chromaesthesia',
		'res'      => __( '2× faster', 'blueworx-labs-wordpress' ),
		'res_text' => __( 'publishing workflow', 'blueworx-labs-wordpress' ),
	),
	array(
		'image'    => 'hero-image.png',
		'alt'      => __( 'Reid Consulting website', 'blueworx-labs-wordpress' ),
		'tags'     => array( __( 'SEO', 'blueworx-labs-wordpress' ), __( 'Growth Retainer', 'blueworx-labs-wordpress' ) ),
		'name'     => 'Reid Consulting',
		'res'      => '3×',
		'res_text' => __( 'organic traffic in 12 months', 'blueworx-labs-wordpress' ),
	),
);

$blueworx_work_stats = array(
	array(
		'value' => '5.0',
		'star'  => true,
		'label' => __( 'Google Rating', 'blueworx-labs-wordpress' ),
	),
	array(
		'value' => '82+',
		'label' => __( 'Projects Completed', 'blueworx-labs-wordpress' ),
	),
	array(
		'value' => '100k +',
		'label' => __( 'Revenue Handled', 'blueworx-labs-wordpress' ),
	),
	array(
		'value' => '2K +',
		'label' => __( 'Toolbox Value', 'blueworx-labs-wordpress' ),
	),
);

// Work's own testimonials, distinct from the shared homepage reviews.
$blueworx_work_testimonials = array(
	array(
		'text'     => __( '"BlueWorx has completely transformed how we manage our website. The tools are powerful and the support team is incredibly responsive."', 'blueworx-labs-wordpress' ),
		'initials' => 'SJ',
		'name'     => 'Sarah Johnson',
		'role'     => __( 'Owner, Fresh Bakery Co.', 'blueworx-labs-wordpress' ),
	),
	array(
		'text'     => __( '"The live chat and booking system have increased our conversion rate significantly. Worth every penny — and then some."', 'blueworx-labs-wordpress' ),
		'initials' => 'MR',
		'name'     => 'Marcus Reid',
		'role'     => __( 'Director, Reid Consulting', 'blueworx-labs-wordpress' ),
	),
	array(
		'text'     => __( '"Finally, one platform that does it all. We cancelled three separate subscriptions when we switched to BlueWorx."', 'blueworx-labs-wordpress' ),
		'initials' => 'AL',
		'name'     => 'Amy Leung',
		'role'     => __( 'Founder, Leung Law Group', 'blueworx-labs-wordpress' ),
	),
);

blueworx_public_document_open( array( 'body_class' => 'bw-work' ) );
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
							'badge'           => __( 'Selected Work', 'blueworx-labs-wordpress' ),
							'title'           => __( 'Work That Moves the Needle', 'blueworx-labs-wordpress' ),
							'title_highlight' => __( 'the Needle', 'blueworx-labs-wordpress' ),
							'lead'            => __( "Digital solutions we've designed, built, and grown alongside our partners, with the outcomes to show for it.", 'blueworx-labs-wordpress' ),
							'cta'             => array(
								array(
									'label' => __( 'Start a Project', 'blueworx-labs-wordpress' ),
									'href'  => home_url( '/contact' ),
									'class' => 'btn btn-white btn-lg',
								),
								array(
									'label' => __( 'Our Services', 'blueworx-labs-wordpress' ),
									'href'  => home_url( '/services' ),
									'class' => 'btn btn-outline-w btn-lg',
								),
							),
							'meta'            => array(
								__( '82+ projects', 'blueworx-labs-wordpress' ),
								__( '4.9 rating', 'blueworx-labs-wordpress' ),
								__( '99.9% uptime', 'blueworx-labs-wordpress' ),
							),
						)
					);
					?>
				</div>
				<?php
				ob_start();
				?>
				<div class="gc-metric"><small><?php echo esc_html__( 'Hirasté — booking enquiries', 'blueworx-labs-wordpress' ); ?></small><b>+64%</b><span class="up">▲</span></div>
				<div class="gc-metric"><small><?php echo esc_html__( 'QURE — conversion rate', 'blueworx-labs-wordpress' ); ?></small><b>+38%</b><span class="up">▲</span></div>
				<div class="gc-metric" style="border-bottom:none"><small><?php echo esc_html__( 'Reid — organic traffic', 'blueworx-labs-wordpress' ); ?></small><b>3×</b><span class="up">▲</span></div>
				<div class="gc-spark">
					<i style="height:30%"></i><i style="height:44%"></i><i style="height:58%"></i><i style="height:52%"></i><i style="height:70%"></i><i class="hi" style="height:96%"></i><i style="height:78%"></i><i style="height:88%"></i>
				</div>
				<?php
				$blueworx_work_gc_body = ob_get_clean();

				blueworx_public_part(
					'parts/glass-card.php',
					array(
						'tag'    => __( 'results.log', 'blueworx-labs-wordpress' ),
						'body'   => $blueworx_work_gc_body,
						'floats' => array(
							array(
								'icon'  => 'chart',
								'label' => __( 'Avg. lift', 'blueworx-labs-wordpress' ),
								'value' => '+41%',
								'style' => 'bottom:-22px;left:-26px;animation-delay:.6s',
							),
						),
					)
				);
				?>
			</div>
		</section>

		<section class="sec" style="padding-top:52px">
			<div class="work-grid">
				<?php
				foreach ( $blueworx_work_projects as $blueworx_work_project ) {
					blueworx_public_part(
						'parts/work-card.php',
						array(
							'img_url'   => BLUEWORX_LABS_URL . 'assets/img/' . $blueworx_work_project['image'],
							'alt'       => $blueworx_work_project['alt'],
							'tags'      => $blueworx_work_project['tags'],
							'name'      => $blueworx_work_project['name'],
							'res_value' => $blueworx_work_project['res'],
							'res_text'  => $blueworx_work_project['res_text'],
						)
					);
				}
				?>
			</div>
		</section>

		<?php
		blueworx_public_part(
			'parts/stats-band.php',
			array(
				'title' => __( 'Outcomes, not just outputs.', 'blueworx-labs-wordpress' ),
				'copy'  => __( 'Every engagement is measured against the goals we set together: traffic, conversions, and revenue. Not vanity metrics.', 'blueworx-labs-wordpress' ),
				'stats' => $blueworx_work_stats,
			)
		);

		blueworx_public_part(
			'parts/testimonials.php',
			array(
				'eyebrow'      => __( 'What Our Clients Say', 'blueworx-labs-wordpress' ),
				'title'        => __( "Partners Who'd Recommend Us", 'blueworx-labs-wordpress' ),
				'testimonials' => $blueworx_work_testimonials,
			)
		);
		?>
	</div>
</main>
<?php
blueworx_public_part( 'parts/footer.php' );
blueworx_public_document_close();
