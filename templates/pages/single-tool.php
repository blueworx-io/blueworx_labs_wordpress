<?php
/**
 * Tool-detail page template (`/toolbox/<slug>`).
 *
 * Ported from app/toolbox/[slug]/page.tsx. Tool identity is resolved via the
 * matched page registry entry's `slug` field
 * (blueworx_public_current_page()['slug'], includes/public/pages.php) —
 * deliberately NOT get_queried_object()->post_name, which a rename would make
 * stale (Task 9b's routing decision: the registry's own `slug` is the
 * rename-robust identity).
 *
 * Sections, in source order:
 * - A two-column `.tech-hero`: breadcrumb + badge + heading + lead + CTAs +
 *   status pills on the left, a bespoke `.glass-card` (58px bundled favicon
 *   tile, name + optional "Popular" pill, domain, and the 6 features as
 *   green-check rows) on the right. tech-hero.php has no breadcrumb slot (see
 *   that part's doc comment), so — like services.php's two-column hero — this
 *   is composed inline rather than through that part; the `.glass-card`
 *   itself is also hand-built here rather than via glass-card.php, which
 *   always renders a `.gc-head` (dots + tag) this page's design does not use.
 * - `#tool-why`: the same 6 features again, as `.svc-grid` "why it's in the
 *   Toolbox" cards (blueworx_icon() per feature).
 * - The related-tools grid: the shared `toolbox-grid` part, fed the first 4
 *   OTHER tools (current tool excluded).
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

$blueworx_st_page = blueworx_public_current_page();
$blueworx_st_slug = ( is_array( $blueworx_st_page ) && isset( $blueworx_st_page['slug'] ) ) ? (string) $blueworx_st_page['slug'] : '';
$blueworx_st_tool = '' !== $blueworx_st_slug ? blueworx_content_tool( $blueworx_st_slug ) : null;

// First 4 OTHER tools, in blueworx_content_tools() order — only computed when
// a tool actually resolved.
$blueworx_st_related = array();

if ( null !== $blueworx_st_tool ) {
	foreach ( blueworx_content_tools() as $blueworx_st_candidate ) {
		if ( $blueworx_st_candidate['slug'] === $blueworx_st_tool['slug'] ) {
			continue;
		}

		$blueworx_st_related[] = $blueworx_st_candidate;

		if ( 4 === count( $blueworx_st_related ) ) {
			break;
		}
	}
}

// Static, trusted glyphs ported verbatim from the source's inline <svg>
// markup: the breadcrumb chevron and the green feature checkmark.
$blueworx_st_chevron = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polyline points="9 18 15 12 9 6"/></svg>';
$blueworx_st_check   = '<svg viewBox="0 0 24 24" fill="none" stroke="#01D084" stroke-width="3" style="width:11px;height:11px"><polyline points="20 6 9 17 4 12"/></svg>';

blueworx_public_document_open( array( 'body_class' => 'bw-single-tool' ) );
blueworx_public_part( 'parts/nav.php' );

// Should never happen for an owned route (blueworx_public_current_page()
// only resolves to a registry entry whose slug matches one of
// blueworx_content_tools()) — but if it ever does, the document shell and
// nav/footer still render; there is simply no main content to show.
if ( null !== $blueworx_st_tool ) :
	?>
	<main>
		<div>
			<section class="tech-hero" style="padding-bottom:88px">
				<div class="tech-inner tech-2col">
					<div class="tc-copy">
						<div style="display:flex;align-items:center;gap:8px;font-size:13.5px;color:rgba(226,228,255,.5);margin-bottom:28px">
							<a href="<?php echo esc_url( home_url( '/toolbox' ) ); ?>" style="cursor:pointer;color:inherit;text-decoration:none"><?php esc_html_e( 'Toolbox', 'blueworx-labs-wordpress' ); ?></a>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted chevron glyph, see $blueworx_st_chevron above.
							echo $blueworx_st_chevron;
							?>
							<span style="color:rgba(255,255,255,.85)"><?php echo esc_html( $blueworx_st_tool['name'] ); ?></span>
						</div>
						<div class="tech-badge" style="margin-bottom:20px"><span class="dot"></span><?php echo esc_html( $blueworx_st_tool['category'] ); ?></div>
						<h1 class="h1"><?php echo esc_html( $blueworx_st_tool['name'] ); ?></h1>
						<p class="lead" style="max-width:520px;margin:20px 0 34px"><?php echo esc_html( $blueworx_st_tool['tagline'] ); ?></p>
						<div style="display:flex;gap:14px;flex-wrap:wrap">
							<a class="btn btn-white btn-lg" href="#tool-why" style="text-decoration:none"><?php esc_html_e( 'Add to Your Toolbox', 'blueworx-labs-wordpress' ); ?></a>
							<a class="btn btn-outline-w btn-lg" href="<?php echo esc_url( home_url( '/toolbox' ) ); ?>"><?php esc_html_e( 'View All Tools', 'blueworx-labs-wordpress' ); ?></a>
						</div>
						<div class="tech-status"><span><?php esc_html_e( 'included in every Toolbox plan', 'blueworx-labs-wordpress' ); ?></span><span><?php esc_html_e( 'set up & managed by BlueWorx', 'blueworx-labs-wordpress' ); ?></span></div>
					</div>
					<div class="glass-wrap">
						<div class="glass-card" style="padding:28px">
							<div class="gc-scan"></div>
							<div style="position:relative;display:flex;align-items:center;gap:16px">
								<div style="width:58px;height:58px;border-radius:15px;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 26px rgba(0,0,0,.28);flex-shrink:0">
									<img src="<?php echo esc_url( BLUEWORX_LABS_URL . 'assets/img/tools/' . $blueworx_st_tool['slug'] . '.png' ); ?>" alt="<?php echo esc_attr( $blueworx_st_tool['name'] ); ?>" style="width:32px;height:32px;object-fit:contain" />
								</div>
								<div style="flex:1;min-width:0">
									<div style="display:flex;align-items:center;gap:9px;font-size:17px;font-weight:600;color:#fff">
										<?php echo esc_html( $blueworx_st_tool['name'] ); ?>
										<?php if ( ! empty( $blueworx_st_tool['popular'] ) ) : ?>
											<span style="font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#0A0C29;background:#A5A7FF;padding:2px 7px;border-radius:100px"><?php esc_html_e( 'Popular', 'blueworx-labs-wordpress' ); ?></span>
										<?php endif; ?>
									</div>
									<div style="font-family:'SF Mono','JetBrains Mono',ui-monospace,Menlo,monospace;font-size:12px;letter-spacing:.06em;color:rgba(226,228,255,.45);margin-top:3px"><?php echo esc_html( $blueworx_st_tool['domain'] ); ?></div>
								</div>
							</div>
							<div style="position:relative;height:1px;background:rgba(139,142,255,.18);margin:22px 0"></div>
							<div style="position:relative;display:flex;flex-direction:column;gap:14px">
								<?php foreach ( $blueworx_st_tool['features'] as $blueworx_st_feature ) : ?>
									<div style="display:flex;align-items:flex-start;gap:11px">
										<div style="width:20px;height:20px;border-radius:50%;background:rgba(1,208,132,.14);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px">
											<?php
											// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted check glyph, see $blueworx_st_check above.
											echo $blueworx_st_check;
											?>
										</div>
										<div style="font-size:14.5px;color:rgba(226,228,255,.82);line-height:1.5"><?php echo esc_html( $blueworx_st_feature['title'] ); ?></div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>
			</section>

			<section class="sec" id="tool-why">
				<div class="center-head" style="margin-bottom:40px">
					<div class="eyebrow" style="margin-bottom:20px"><?php esc_html_e( "Why it's in the Toolbox", 'blueworx-labs-wordpress' ); ?></div>
					<h2 class="h2">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: tool name, e.g. "SureCart". */
								__( 'What %s gives you', 'blueworx-labs-wordpress' ),
								$blueworx_st_tool['name']
							)
						);
						?>
					</h2>
				</div>
				<div class="svc-grid">
					<?php foreach ( $blueworx_st_tool['features'] as $blueworx_st_feature ) : ?>
						<div class="svc">
							<div class="svc-ic"><?php blueworx_icon( $blueworx_st_feature['icon'] ); ?></div>
							<h3><?php echo esc_html( $blueworx_st_feature['title'] ); ?></h3>
							<p><?php echo esc_html( $blueworx_st_feature['desc'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</section>

			<?php
			blueworx_public_part(
				'parts/toolbox-grid.php',
				array(
					'title' => __( 'More from the Toolbox', 'blueworx-labs-wordpress' ),
					'sub'   => __( 'Every tool below is included in the same subscription. No extra logins, no extra bills.', 'blueworx-labs-wordpress' ),
					'tools' => $blueworx_st_related,
				)
			);
			?>
		</div>
	</main>
	<?php
endif;

blueworx_public_part( 'parts/footer.php' );
blueworx_public_document_close();
