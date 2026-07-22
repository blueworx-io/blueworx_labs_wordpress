<?php
/**
 * CTA band + site footer template part.
 *
 * Ported from CtaBand.tsx and Footer.tsx. The CTA band renders on every page,
 * outside <main>, immediately before the footer — that is the source's
 * layout, not a stylistic choice, so callers should not move it inside main.
 *
 * Fidelity notes carried over verbatim from the source rather than
 * "improved": the three social links and the Blog/Resources/Careers links
 * have no href in the design — they render as plain, non-interactive <a>
 * tags rather than inventing destinations. The newsletter form is inert:
 * markup only, no handler or action — a form plugin shortcode replaces it
 * later.
 *
 * The source's <img src="/assets/logo.png"> is bundled by the plugin itself
 * at assets/img/logo.png, matching what the front-end design ships. This
 * deliberately does NOT read get_theme_mod('custom_logo') — a theme mod is
 * stored per-theme, so it changes or vanishes on theme switch, which would
 * make the footer's output depend on which theme happens to be active. The
 * whole point of this public layer is that output is identical regardless
 * of theme, so the plugin owns its own brand asset instead. A graceful text
 * fallback (the site name) still applies if the bundled file is somehow
 * absent, so `.fb` never renders a broken image.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blueworx_footer_logo_path = BLUEWORX_LABS_PATH . 'assets/img/logo.png';
$blueworx_footer_logo_url  = BLUEWORX_LABS_URL . 'assets/img/logo.png';
?>
<div class="cta-soft">
	<div class="cta-inner">
		<?php
		blueworx_blob( 'width:220px;height:220px;bottom:-80px;left:-40px;opacity:.4' );
		blueworx_blob( 'width:180px;height:180px;top:-60px;right:-20px;opacity:.35' );
		?>
		<h2 class="h2"><?php echo esc_html__( 'Ready to Build a Digital Solution That Wins?', 'blueworx-labs-wordpress' ); ?></h2>
		<p><?php echo esc_html__( "Book a free strategy call. We'll review your current setup and show you exactly where the opportunities are.", 'blueworx-labs-wordpress' ); ?></p>
		<div class="cta-actions">
			<a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="btn btn-brand btn-md"><?php echo esc_html__( 'Get a Quote', 'blueworx-labs-wordpress' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/contact' ) ); ?>" class="btn btn-outline-w btn-md"><?php echo esc_html__( 'Book a Call', 'blueworx-labs-wordpress' ); ?></a>
		</div>
	</div>
</div>
<footer>
	<div class="ft">
		<div class="fb">
			<?php if ( file_exists( $blueworx_footer_logo_path ) ) : ?>
				<img
					src="<?php echo esc_url( $blueworx_footer_logo_url ); ?>"
					alt="<?php echo esc_attr__( 'BlueWorx', 'blueworx-labs-wordpress' ); ?>"
					style="filter:brightness(0) invert(1)"
				/>
			<?php else : ?>
				<span class="bw-footer-logo-text"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
			<?php endif; ?>
			<p><?php echo esc_html__( 'BlueWorx supports growing businesses worldwide with premium tools, hosting, and expert support.', 'blueworx-labs-wordpress' ); ?></p>
			<div class="fsocial">
				<a aria-label="<?php echo esc_attr__( 'Facebook', 'blueworx-labs-wordpress' ); ?>">
					<svg viewBox="0 0 24 24" fill="currentColor"><path d="M13 10h3l.5-3H13V5.5c0-.8.3-1.5 1.5-1.5H16.5V1.4C16.2 1.3 15 1.2 13.8 1.2c-2.5 0-4.3 1.5-4.3 4.3V7H6.7v3H9.5v8h3.5v-8z" /></svg>
				</a>
				<a aria-label="<?php echo esc_attr__( 'LinkedIn', 'blueworx-labs-wordpress' ); ?>">
					<svg viewBox="0 0 24 24" fill="currentColor"><path d="M4.98 3.5a2.5 2.5 0 11-.02 5 2.5 2.5 0 01.02-5zM3 8.9h4v12H3v-12zM9.5 8.9h3.8v1.6h.05c.53-1 1.83-2.05 3.77-2.05 4.03 0 4.78 2.65 4.78 6.1v6.35h-4v-5.63c0-1.34-.02-3.07-1.87-3.07-1.87 0-2.16 1.46-2.16 2.97v5.73h-4v-12z" /></svg>
				</a>
				<a aria-label="<?php echo esc_attr__( 'Twitter', 'blueworx-labs-wordpress' ); ?>">
					<svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 5.9c-.7.3-1.5.5-2.3.6.8-.5 1.5-1.3 1.8-2.3-.8.5-1.7.8-2.6 1a4.1 4.1 0 00-7 3.7A11.6 11.6 0 013.2 4.5a4.1 4.1 0 001.3 5.5c-.7 0-1.3-.2-1.8-.5v.05a4.1 4.1 0 003.3 4 4.1 4.1 0 01-1.8.07 4.1 4.1 0 003.8 2.85A8.2 8.2 0 012 18.1a11.6 11.6 0 006.3 1.85c7.5 0 11.7-6.3 11.7-11.7v-.5c.8-.6 1.5-1.3 2-2.15z" /></svg>
				</a>
			</div>
		</div>
		<div class="fcol">
			<h4><?php echo esc_html__( 'Pages', 'blueworx-labs-wordpress' ); ?></h4>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'Home', 'blueworx-labs-wordpress' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/services' ) ); ?>"><?php echo esc_html__( 'Services', 'blueworx-labs-wordpress' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/ai' ) ); ?>"><?php echo esc_html__( 'AI Powered', 'blueworx-labs-wordpress' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/work' ) ); ?>"><?php echo esc_html__( 'Work', 'blueworx-labs-wordpress' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/toolbox' ) ); ?>"><?php echo esc_html__( 'Toolbox', 'blueworx-labs-wordpress' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/about' ) ); ?>"><?php echo esc_html__( 'About Us', 'blueworx-labs-wordpress' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>"><?php echo esc_html__( 'Pricing', 'blueworx-labs-wordpress' ); ?></a>
		</div>
		<div class="fcol">
			<h4><?php echo esc_html__( 'About', 'blueworx-labs-wordpress' ); ?></h4>
			<a><?php echo esc_html__( 'Blog', 'blueworx-labs-wordpress' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/contact' ) ); ?>"><?php echo esc_html__( 'Contact', 'blueworx-labs-wordpress' ); ?></a>
			<a><?php echo esc_html__( 'Resources', 'blueworx-labs-wordpress' ); ?></a>
			<a><?php echo esc_html__( 'Careers', 'blueworx-labs-wordpress' ); ?></a>
		</div>
		<div class="fnews">
			<h4 style="font-size:14px;font-weight:600;color:#fff;margin-bottom:16px;"><?php echo esc_html__( 'Newsletters', 'blueworx-labs-wordpress' ); ?></h4>
			<p><?php echo esc_html__( 'Curious about new developments & updates? Sign up for our newsletter!', 'blueworx-labs-wordpress' ); ?></p>
			<div class="fnews-in">
				<input placeholder="<?php echo esc_attr__( 'email@.blueworx.com', 'blueworx-labs-wordpress' ); ?>" aria-label="<?php echo esc_attr__( 'Email address', 'blueworx-labs-wordpress' ); ?>" />
				<button aria-label="<?php echo esc_attr__( 'Subscribe', 'blueworx-labs-wordpress' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<line x1="5" y1="12" x2="19" y2="12" />
						<polyline points="12 5 19 12 12 19" />
					</svg>
				</button>
			</div>
		</div>
	</div>
	<div class="fbot">
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: current year. */
					__( '© %s BlueWorx. All rights reserved.', 'blueworx-labs-wordpress' ),
					gmdate( 'Y' )
				)
			);
			?>
		</p>
		<p><?php echo esc_html__( 'Powered by BabyBlue Digital.', 'blueworx-labs-wordpress' ); ?></p>
	</div>
</footer>
