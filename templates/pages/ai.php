<?php
/**
 * AI Powered page template.
 *
 * Ported from app/ai/page.tsx's five sections, in source order: the ai-hero
 * (two-column, Claude badge + copy, with the AiDemo widget replaced by a Plan 3
 * placeholder), "The Full Flow" (AiPipeline, also a Plan 3 placeholder), "Model
 * Guidance" (a dark section of four model cards), "Approved Stack" (ten chips),
 * and "What We Build" (five offering cards on a dark section).
 *
 * AiDemo and AiPipeline are Plan 3 interactive widgets; until then a labelled
 * placeholder stands in for each so the page is whole. Everything else is
 * static. This page's `.ai-*` styles are already in public.css.
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

// Static trusted arrow SVG, sized by `.btn svg` / `.svc-link svg`. Ported
// verbatim from the ARROW constant in app/ai/page.tsx (not a blueworx_icon).
$blueworx_ai_arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>';

$blueworx_ai_models = array(
	array(
		'icon'  => 'gauge',
		'tier'  => 'Haiku',
		'title' => __( 'Fast & light', 'blueworx-labs-wordpress' ),
		'role'  => __( 'For quick, mechanical, high-volume work and simple tweaks.', 'blueworx-labs-wordpress' ),
		'items' => array( __( 'High-volume edits', 'blueworx-labs-wordpress' ), __( 'Simple design tweaks', 'blueworx-labs-wordpress' ), __( 'Routine changes', 'blueworx-labs-wordpress' ) ),
	),
	array(
		'icon'  => 'code',
		'tier'  => 'Sonnet',
		'title' => __( 'The default', 'blueworx-labs-wordpress' ),
		'role'  => __( 'Our everyday workhorse for building, planning and most design.', 'blueworx-labs-wordpress' ),
		'items' => array( __( 'Day-to-day coding', 'blueworx-labs-wordpress' ), __( 'Issues & milestones', 'blueworx-labs-wordpress' ), __( 'Everyday screens', 'blueworx-labs-wordpress' ) ),
	),
	array(
		'icon'  => 'sparkles',
		'tier'  => 'Opus',
		'title' => __( 'Deep reasoning', 'blueworx-labs-wordpress' ),
		'role'  => __( 'For new or unclear briefs, hard bugs and demanding design.', 'blueworx-labs-wordpress' ),
		'items' => array( __( 'New project briefs', 'blueworx-labs-wordpress' ), __( 'Hard bugs & architecture', 'blueworx-labs-wordpress' ), __( 'Demanding design', 'blueworx-labs-wordpress' ) ),
	),
	array(
		'icon'  => 'zap',
		'tier'  => 'Fable',
		'title' => __( 'The big jobs', 'blueworx-labs-wordpress' ),
		'role'  => __( 'Reserved for the largest builds and toughest design challenges.', 'blueworx-labs-wordpress' ),
		'items' => array( __( 'Major migrations', 'blueworx-labs-wordpress' ), __( 'Multi-day builds', 'blueworx-labs-wordpress' ), __( 'Toughest design', 'blueworx-labs-wordpress' ) ),
	),
);

$blueworx_ai_stack = array(
	array(
		'name' => 'Radix Themes',
		'sub'  => __( 'components', 'blueworx-labs-wordpress' ),
	),
	array(
		'name' => 'lucide-react',
		'sub'  => __( 'icons', 'blueworx-labs-wordpress' ),
	),
	array(
		'name' => 'Tailwind CSS',
		'sub'  => __( 'styling', 'blueworx-labs-wordpress' ),
	),
	array(
		'name' => 'GSAP',
		'sub'  => __( 'motion', 'blueworx-labs-wordpress' ),
	),
	array(
		'name' => 'Refero',
		'sub'  => __( 'tokens', 'blueworx-labs-wordpress' ),
	),
	array(
		'name' => 'Playwright',
		'sub'  => __( 'testing', 'blueworx-labs-wordpress' ),
	),
	array(
		'name' => 'GitHub',
		'sub'  => __( 'source', 'blueworx-labs-wordpress' ),
	),
	array(
		'name' => 'Netlify',
		'sub'  => __( 'deploy', 'blueworx-labs-wordpress' ),
	),
	array(
		'name' => 'WordPress',
		'sub'  => __( 'backend', 'blueworx-labs-wordpress' ),
	),
	array(
		'name' => 'Claude Code',
		'sub'  => __( 'build', 'blueworx-labs-wordpress' ),
	),
);

$blueworx_ai_offerings = array(
	array(
		'icon'  => 'doc',
		'title' => __( 'AI-Built Websites', 'blueworx-labs-wordpress' ),
		'desc'  => __( 'Conversion-focused sites generated from your brief and refined by hand. Fast, on-brand and production-ready.', 'blueworx-labs-wordpress' ),
	),
	array(
		'icon'  => 'plug',
		'title' => __( 'AI Plugins & Integrations', 'blueworx-labs-wordpress' ),
		'desc'  => __( 'Custom WordPress plugins and app integrations, built code-first so every change is reviewable through a pull request.', 'blueworx-labs-wordpress' ),
	),
	array(
		'icon'  => 'workflow',
		'title' => __( 'AI Automations & Workflows', 'blueworx-labs-wordpress' ),
		'desc'  => __( 'Connect the tools you already use and let AI-designed workflows handle the busywork end to end.', 'blueworx-labs-wordpress' ),
	),
	array(
		'icon'  => 'server',
		'title' => __( 'Custom AI Tooling', 'blueworx-labs-wordpress' ),
		'desc'  => __( 'Bespoke internal tools and AI features tailored to how your business actually runs day to day.', 'blueworx-labs-wordpress' ),
	),
	array(
		'icon'  => 'palette',
		'title' => __( 'AI-Assisted Design', 'blueworx-labs-wordpress' ),
		'desc'  => __( 'Screens designed in Claude Design on your own design system (Radix, lucide and tokens), ready for a clean handoff.', 'blueworx-labs-wordpress' ),
	),
);

blueworx_public_document_open( array( 'body_class' => 'bw-ai' ) );
blueworx_public_part( 'parts/nav.php' );
?>
<main>
	<div>
		<section class="tech-hero ai-hero">
			<div class="tech-inner">
				<div class="tech-2col">
					<div class="tc-copy">
						<div class="claude-badge">
							<span class="cb-spark"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1.5c.62 5.4 3.6 8.38 9 9-5.4.62-8.38 3.6-9 9-.62-5.4-3.6-8.38-9-9 5.4-.62 8.38-3.6 9-9z"/></svg></span>
							<span class="cb-lbl"><?php esc_html_e( 'Powered by', 'blueworx-labs-wordpress' ); ?></span>
							<b>Claude</b>
							<span class="cb-div"></span>
							<span class="cb-by">Anthropic</span>
						</div>
						<h1 class="h1" style="margin-top:22px"><?php esc_html_e( 'From Prompt to Production — ', 'blueworx-labs-wordpress' ); ?><span class="tech-grad"><?php esc_html_e( 'Built by AI', 'blueworx-labs-wordpress' ); ?></span><?php esc_html_e( ', Shipped by Experts', 'blueworx-labs-wordpress' ); ?></h1>
						<p class="lead" style="margin-top:22px"><?php esc_html_e( 'We build websites, plugins, automations and custom tools with an AI-first process. Claude models turn your brief into production-ready code on a vetted stack, reviewed, tested and shipped by our team.', 'blueworx-labs-wordpress' ); ?></p>
						<div class="hh-cta" style="margin-top:34px">
							<a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="btn btn-white btn-lg"><?php esc_html_e( 'Get a Quote', 'blueworx-labs-wordpress' ); ?></a>
							<a href="<?php echo esc_url( home_url( '/work' ) ); ?>" class="btn btn-outline-w btn-lg">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted arrow markup.
								echo $blueworx_ai_arrow;
								?>
								<?php esc_html_e( 'View Our Work', 'blueworx-labs-wordpress' ); ?>
							</a>
						</div>
						<div class="ai-lead-tags">
							<span><?php esc_html_e( 'Opus · Sonnet · Haiku', 'blueworx-labs-wordpress' ); ?></span><span><?php esc_html_e( 'Radix + Tailwind', 'blueworx-labs-wordpress' ); ?></span><span><?php esc_html_e( 'GSAP motion', 'blueworx-labs-wordpress' ); ?></span>
						</div>
					</div>
					<div class="glass-wrap">
						<div class="bw-plan3-placeholder" data-widget="ai-demo">
							<p><?php esc_html_e( 'AI demo — coming soon.', 'blueworx-labs-wordpress' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</section>

		<section class="sec">
			<div class="center-head" style="margin-bottom:40px">
				<div class="eyebrow" style="margin-bottom:20px"><?php esc_html_e( 'The Full Flow', 'blueworx-labs-wordpress' ); ?></div>
				<h2 class="h2"><?php esc_html_e( 'From Brief to Deploy, One Continuous Flow', 'blueworx-labs-wordpress' ); ?></h2>
				<p class="lead"><?php esc_html_e( 'A repeatable pipeline where the right Claude model handles each stage, and every change ships through review, tests and version control.', 'blueworx-labs-wordpress' ); ?></p>
			</div>
			<div class="bw-plan3-placeholder" data-widget="ai-pipeline">
				<p><?php esc_html_e( 'Pipeline visualisation — coming soon.', 'blueworx-labs-wordpress' ); ?></p>
			</div>
		</section>

		<section class="features-dark">
			<div class="blob" style="width:360px;height:360px;bottom:-140px;right:-120px;opacity:.13"></div>
			<div class="center-head" style="margin-bottom:40px">
				<div class="eyebrow" style="margin-bottom:20px;background:rgba(139,142,255,.09);border-color:rgba(139,142,255,.28);color:#B7B9FF"><?php esc_html_e( 'Model Guidance', 'blueworx-labs-wordpress' ); ?></div>
				<h2 class="h2" style="color:#fff"><?php esc_html_e( 'The Right Claude Model for Every Task', 'blueworx-labs-wordpress' ); ?></h2>
				<p class="fd-sub" style="margin:16px auto 0;max-width:600px;text-align:center"><?php esc_html_e( 'We match each job to the model that fits it best, so you get the right balance of speed, cost and depth on every piece of work.', 'blueworx-labs-wordpress' ); ?></p>
			</div>
			<div class="ai-models">
				<?php foreach ( $blueworx_ai_models as $blueworx_ai_model ) : ?>
					<div class="ai-model">
						<div class="tier"><span class="glyph"><?php blueworx_icon( $blueworx_ai_model['icon'] ); ?></span><?php echo esc_html( $blueworx_ai_model['tier'] ); ?></div>
						<h4><?php echo esc_html( $blueworx_ai_model['title'] ); ?></h4>
						<p class="role"><?php echo esc_html( $blueworx_ai_model['role'] ); ?></p>
						<ul>
							<?php foreach ( $blueworx_ai_model['items'] as $blueworx_ai_item ) : ?>
								<li><?php echo esc_html( $blueworx_ai_item ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="sec">
			<div class="center-head" style="margin-bottom:40px">
				<div class="eyebrow" style="margin-bottom:20px"><?php esc_html_e( 'Approved Stack', 'blueworx-labs-wordpress' ); ?></div>
				<h2 class="h2"><?php esc_html_e( 'Built on Tools We Trust', 'blueworx-labs-wordpress' ); ?></h2>
				<p class="lead"><?php esc_html_e( 'No guesswork. A vetted, approved toolset used consistently across every project.', 'blueworx-labs-wordpress' ); ?></p>
			</div>
			<div class="ai-stack">
				<?php foreach ( $blueworx_ai_stack as $blueworx_ai_chip ) : ?>
					<div class="ai-chip"><?php echo esc_html( $blueworx_ai_chip['name'] ); ?> <small><?php echo esc_html( $blueworx_ai_chip['sub'] ); ?></small></div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="features-dark">
			<div class="blob" style="width:340px;height:340px;top:-120px;left:-120px;opacity:.14"></div>
			<div class="blob" style="width:300px;height:300px;bottom:-120px;right:-100px;opacity:.11"></div>
			<div class="center-head" style="margin-bottom:52px">
				<div class="eyebrow" style="margin-bottom:20px;background:rgba(139,142,255,.09);border-color:rgba(139,142,255,.28);color:#B7B9FF"><?php esc_html_e( 'What We Build', 'blueworx-labs-wordpress' ); ?></div>
				<h2 class="h2" style="color:#fff"><?php esc_html_e( 'AI Offerings, Built for Real Businesses', 'blueworx-labs-wordpress' ); ?></h2>
				<p class="fd-sub" style="margin:16px auto 0;max-width:620px;text-align:center"><?php esc_html_e( 'Every build runs through our AI-first process: production-ready code on a vetted stack, reviewed and shipped by our team.', 'blueworx-labs-wordpress' ); ?></p>
			</div>
			<div class="ai-off-grid">
				<?php foreach ( $blueworx_ai_offerings as $blueworx_ai_offering ) : ?>
					<div class="fc">
						<div class="fi"><?php blueworx_icon( $blueworx_ai_offering['icon'] ); ?></div>
						<h3><?php echo esc_html( $blueworx_ai_offering['title'] ); ?></h3>
						<p><?php echo esc_html( $blueworx_ai_offering['desc'] ); ?></p>
						<span class="svc-link">
							<?php esc_html_e( 'Learn more', 'blueworx-labs-wordpress' ); ?>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted arrow markup.
							echo $blueworx_ai_arrow;
							?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	</div>
</main>
<?php
blueworx_public_part( 'parts/footer.php' );
blueworx_public_document_close();
