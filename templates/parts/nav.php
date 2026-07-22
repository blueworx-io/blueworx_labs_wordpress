<?php
/**
 * Site navigation template part.
 *
 * Ported from Nav.tsx. The React source conditionally mounts the Toolbox
 * mega panel, the About Us dropdown and the mobile menu only while each is
 * open — a plain document cannot hover into, or slide open, an element that
 * does not exist yet, so this port renders all three unconditionally and
 * relies on assets/js/public-nav.js to toggle an ".open" class, matched by
 * ".mega-panel"/".about-panel"/".mobile-menu" rules in assets/css/public.css.
 * That is the one deliberate structural difference from the source; markup
 * order and every class name otherwise match it exactly.
 *
 * Every internal href is built with home_url( '/services' ) etc. (matching
 * templates/parts/footer.php), not a bare "/services" — the source's own
 * <Link href="/services"> paths assume a root-domain deployment, but on a
 * subdirectory WordPress install (example.com/blog/) a bare root-relative
 * href points outside the site entirely. blueworx_public_nav_active_class()
 * still compares against the home-relative $blueworx_nav_path built below,
 * so active-state matching is unaffected by the subdirectory prefix either
 * way.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * TEMPORARY: hardcoded Toolbox tool list for the mega panel.
 *
 * Mirrors TOOLBOX_TOOLS in the front-end repo's lib/data.ts — name, slug,
 * description, favicon domain and the "popular" flag on SureCart only; this
 * plugin has no page/tool data layer yet. Plan 2 replaces this array with
 * real, admin-managed data; it is kept isolated here so that swap is a
 * single, obvious change.
 */
$blueworx_nav_tools = array(
	array(
		'slug'    => 'sureforms',
		'name'    => __( 'SureForms', 'blueworx-labs-wordpress' ),
		'desc'    => __( 'Flexible form builder with smart, multi-step flows.', 'blueworx-labs-wordpress' ),
		'domain'  => 'sureforms.com',
		'popular' => false,
	),
	array(
		'slug'    => 'surerank',
		'name'    => __( 'SureRank', 'blueworx-labs-wordpress' ),
		'desc'    => __( 'SEO insights to improve search visibility.', 'blueworx-labs-wordpress' ),
		'domain'  => 'surerank.com',
		'popular' => false,
	),
	array(
		'slug'    => 'suremail',
		'name'    => __( 'SureMail', 'blueworx-labs-wordpress' ),
		'desc'    => __( 'Reliable delivery for transactional and automated emails.', 'blueworx-labs-wordpress' ),
		'domain'  => 'suremails.com',
		'popular' => false,
	),
	array(
		'slug'    => 'surewriter',
		'name'    => __( 'SureWriter', 'blueworx-labs-wordpress' ),
		'desc'    => __( 'AI-assisted website and marketing copy.', 'blueworx-labs-wordpress' ),
		'domain'  => 'surewriter.com',
		'popular' => false,
	),
	array(
		'slug'    => 'surecart',
		'name'    => __( 'SureCart', 'blueworx-labs-wordpress' ),
		'desc'    => __( 'Modern checkout, subscriptions, and digital sales.', 'blueworx-labs-wordpress' ),
		'domain'  => 'surecart.com',
		'popular' => true,
	),
	array(
		'slug'    => 'zipwp',
		'name'    => __( 'ZipWP', 'blueworx-labs-wordpress' ),
		'desc'    => __( 'AI-generated WordPress websites in minutes.', 'blueworx-labs-wordpress' ),
		'domain'  => 'zipwp.com',
		'popular' => false,
	),
	array(
		'slug'    => 'ottokit',
		'name'    => __( 'OttoKit', 'blueworx-labs-wordpress' ),
		'desc'    => __( 'Automated workflows connecting sites and tools.', 'blueworx-labs-wordpress' ),
		'domain'  => 'ottokit.com',
		'popular' => false,
	),
	array(
		'slug'    => 'ally',
		'name'    => __( 'Ally', 'blueworx-labs-wordpress' ),
		'desc'    => __( 'Improves website accessibility and usability.', 'blueworx-labs-wordpress' ),
		'domain'  => 'useally.io',
		'popular' => false,
	),
	array(
		'slug'    => 'sweet-ai',
		'name'    => __( 'Sweet AI', 'blueworx-labs-wordpress' ),
		'desc'    => __( 'AI support for improving site content.', 'blueworx-labs-wordpress' ),
		'domain'  => 'sweetai.com',
		'popular' => false,
	),
	array(
		'slug'    => 'elementor-ai-planner',
		'name'    => __( 'Elementor AI Planner', 'blueworx-labs-wordpress' ),
		'desc'    => __( 'AI-guided website structure and planning.', 'blueworx-labs-wordpress' ),
		'domain'  => 'elementor.com',
		'popular' => false,
	),
	array(
		'slug'    => 'elementor',
		'name'    => __( 'Elementor', 'blueworx-labs-wordpress' ),
		'desc'    => __( 'Visual page building without code.', 'blueworx-labs-wordpress' ),
		'domain'  => 'elementor.com',
		'popular' => false,
	),
	array(
		'slug'    => 'equalize-a11y-checker',
		'name'    => __( 'Equalize A11y Checker', 'blueworx-labs-wordpress' ),
		'desc'    => __( 'Real-time WCAG accessibility checks.', 'blueworx-labs-wordpress' ),
		'domain'  => 'equalizedigital.com',
		'popular' => false,
	),
);

// Resolve the current request path once, relative to the site root, so every
// active-state check below compares against the same value. blueworx_public_pages()
// registers "home" for "/"; every other href here is a future Plan 2 page reached
// the same root-relative way footer.php already links to them.
$blueworx_nav_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
$blueworx_nav_path        = (string) wp_parse_url( sanitize_text_field( $blueworx_nav_request_uri ), PHP_URL_PATH );
$blueworx_nav_home_path   = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

if ( '' !== $blueworx_nav_home_path && '/' !== $blueworx_nav_home_path && 0 === strpos( $blueworx_nav_path, $blueworx_nav_home_path ) ) {
	$blueworx_nav_path = substr( $blueworx_nav_path, strlen( $blueworx_nav_home_path ) );
}

$blueworx_nav_path = '/' . trim( $blueworx_nav_path, '/' );

if ( ! function_exists( 'blueworx_public_nav_active_class' ) ) {
	/**
	 * Whether a nav href is the current page.
	 *
	 * Exact match for "/", prefix match otherwise — ports Nav.tsx's
	 * `href === "/" ? pathname === "/" : pathname.startsWith(href)` verbatim.
	 *
	 * @param string $href         Root-relative href, e.g. '/services'.
	 * @param string $current_path Current request path, e.g. '/services/seo'.
	 * @return string 'active' or ''.
	 */
	function blueworx_public_nav_active_class( $href, $current_path ) {
		if ( '/' === $href ) {
			return '/' === $current_path ? 'active' : '';
		}

		return 0 === strpos( $current_path, $href ) ? 'active' : '';
	}
}

$blueworx_nav_logo_path = BLUEWORX_LABS_PATH . 'assets/img/logo.png';
$blueworx_nav_logo_url  = BLUEWORX_LABS_URL . 'assets/img/logo.png';
?>
<nav>
	<a class="nav-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<?php if ( file_exists( $blueworx_nav_logo_path ) ) : ?>
			<img src="<?php echo esc_url( $blueworx_nav_logo_url ); ?>" alt="<?php echo esc_attr__( 'BlueWorx', 'blueworx-labs-wordpress' ); ?>" />
		<?php else : ?>
			<span class="bw-nav-logo-text"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
		<?php endif; ?>
	</a>
	<div class="nav-links">
		<a class="<?php echo esc_attr( blueworx_public_nav_active_class( '/', $blueworx_nav_path ) ); ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'Home', 'blueworx-labs-wordpress' ); ?></a>
		<a class="<?php echo esc_attr( blueworx_public_nav_active_class( '/services', $blueworx_nav_path ) ); ?>" href="<?php echo esc_url( home_url( '/services' ) ); ?>"><?php echo esc_html__( 'Services', 'blueworx-labs-wordpress' ); ?></a>

		<div class="nav-drop" data-nav-drop="mega">
			<a class="<?php echo esc_attr( blueworx_public_nav_active_class( '/toolbox', $blueworx_nav_path ) ); ?>" href="<?php echo esc_url( home_url( '/toolbox' ) ); ?>">
				<?php echo esc_html__( 'Toolbox', 'blueworx-labs-wordpress' ); ?>
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polyline points="6 9 12 15 18 9" /></svg>
			</a>
			<div class="mega-panel">
				<?php foreach ( $blueworx_nav_tools as $blueworx_nav_tool ) : ?>
					<a
						href="<?php echo esc_url( home_url( '/toolbox/' . $blueworx_nav_tool['slug'] ) ); ?>"
						class="mega-item"
						style="display:flex;gap:12px;align-items:flex-start;padding:12px;border-radius:12px;"
					>
						<div style="width:38px;height:38px;border-radius:10px;background:#fff;flex-shrink:0;display:flex;align-items:center;justify-content:center;overflow:hidden">
							<img
								src="<?php echo esc_url( BLUEWORX_LABS_URL . 'assets/img/tools/' . $blueworx_nav_tool['slug'] . '.png' ); ?>"
								alt="<?php echo esc_attr( $blueworx_nav_tool['name'] ); ?>"
								style="width:22px;height:22px;object-fit:contain"
							/>
						</div>
						<div>
							<div style="font-size:14.5px;font-weight:600;color:#fff;display:flex;align-items:center;gap:7px">
								<?php echo esc_html( $blueworx_nav_tool['name'] ); ?>
								<?php if ( ! empty( $blueworx_nav_tool['popular'] ) ) : ?>
									<span class="nav-tag tag-dark"><?php echo esc_html__( 'Popular', 'blueworx-labs-wordpress' ); ?></span>
								<?php endif; ?>
							</div>
							<div style="font-size:12.5px;color:rgba(255,255,255,.5);line-height:1.4;margin-top:2px"><?php echo esc_html( $blueworx_nav_tool['desc'] ); ?></div>
						</div>
					</a>
				<?php endforeach; ?>
				<div style="grid-column:1 / -1;border-top:1px solid rgba(255,255,255,.1);margin-top:8px;padding-top:16px;display:flex;justify-content:space-between;align-items:center">
					<span style="font-size:13px;color:rgba(255,255,255,.5)"><?php echo esc_html__( '12 tools, one subscription.', 'blueworx-labs-wordpress' ); ?></span>
					<a href="<?php echo esc_url( home_url( '/toolbox' ) ); ?>" style="font-size:14px;font-weight:600;color:#A5A7FF;cursor:pointer;display:flex;align-items:center;gap:6px;text-decoration:none">
						<?php echo esc_html__( 'Browse the full Toolbox', 'blueworx-labs-wordpress' ); ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><line x1="7" y1="17" x2="17" y2="7" /><polyline points="7 7 17 7 17 17" /></svg>
					</a>
				</div>
			</div>
		</div>

		<a class="<?php echo esc_attr( blueworx_public_nav_active_class( '/pricing', $blueworx_nav_path ) ); ?>" href="<?php echo esc_url( home_url( '/pricing' ) ); ?>"><?php echo esc_html__( 'Pricing', 'blueworx-labs-wordpress' ); ?></a>

		<div class="nav-drop" data-nav-drop="about">
			<a class="<?php echo esc_attr( blueworx_public_nav_active_class( '/about', $blueworx_nav_path ) ); ?>" href="<?php echo esc_url( home_url( '/about' ) ); ?>">
				<?php echo esc_html__( 'About Us', 'blueworx-labs-wordpress' ); ?>
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polyline points="6 9 12 15 18 9" /></svg>
			</a>
			<div class="about-panel">
				<a
					class="<?php echo esc_attr( blueworx_public_nav_active_class( '/work', $blueworx_nav_path ) ); ?>"
					href="<?php echo esc_url( home_url( '/work' ) ); ?>"
					style="display:block;padding:10px 14px;color:#fff;font-size:14.5px;font-weight:500;border-radius:8px;text-decoration:none"
				>
					<?php echo esc_html__( 'Work', 'blueworx-labs-wordpress' ); ?>
				</a>
			</div>
		</div>

		<a class="<?php echo esc_attr( blueworx_public_nav_active_class( '/ai', $blueworx_nav_path ) ); ?>" href="<?php echo esc_url( home_url( '/ai' ) ); ?>" style="gap:7px"><?php echo esc_html__( 'AI Powered', 'blueworx-labs-wordpress' ); ?><span class="nav-tag tag-light"><?php echo esc_html__( 'New', 'blueworx-labs-wordpress' ); ?></span></a>
	</div>
	<div class="nav-cta">
		<a class="nav-sign-in" href="<?php echo esc_url( home_url( '/portal' ) ); ?>"><?php echo esc_html__( 'Client Login', 'blueworx-labs-wordpress' ); ?></a>
		<a class="nav-btn" href="<?php echo esc_url( home_url( '/pricing' ) ); ?>">
			<?php echo esc_html__( 'Get a Quote', 'blueworx-labs-wordpress' ); ?>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="7" y1="17" x2="17" y2="7" /><polyline points="7 7 17 7 17 17" /></svg>
		</a>
	</div>
	<a class="nav-sign-in-mobile" href="<?php echo esc_url( home_url( '/portal' ) ); ?>"><?php echo esc_html__( 'Client Login', 'blueworx-labs-wordpress' ); ?></a>
	<button class="hamburger" aria-label="<?php echo esc_attr__( 'Toggle menu', 'blueworx-labs-wordpress' ); ?>" aria-expanded="false">
		<span></span>
		<span></span>
	</button>
</nav>
<div class="mobile-menu">
	<a class="<?php echo esc_attr( blueworx_public_nav_active_class( '/', $blueworx_nav_path ) ); ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'Home', 'blueworx-labs-wordpress' ); ?></a>
	<a class="<?php echo esc_attr( blueworx_public_nav_active_class( '/services', $blueworx_nav_path ) ); ?>" href="<?php echo esc_url( home_url( '/services' ) ); ?>"><?php echo esc_html__( 'Services', 'blueworx-labs-wordpress' ); ?></a>
	<a class="<?php echo esc_attr( blueworx_public_nav_active_class( '/toolbox', $blueworx_nav_path ) ); ?>" href="<?php echo esc_url( home_url( '/toolbox' ) ); ?>"><?php echo esc_html__( 'Toolbox', 'blueworx-labs-wordpress' ); ?></a>
	<div style="display:flex;flex-direction:column;gap:0;padding-left:12px;border-left:2px solid rgba(79,70,229,.15);margin:0 0 4px">
		<?php foreach ( $blueworx_nav_tools as $blueworx_nav_tool ) : ?>
			<a href="<?php echo esc_url( home_url( '/toolbox/' . $blueworx_nav_tool['slug'] ) ); ?>" style="font-size:13.5px;padding:8px 8px"><?php echo esc_html( $blueworx_nav_tool['name'] ); ?></a>
		<?php endforeach; ?>
	</div>
	<a class="<?php echo esc_attr( blueworx_public_nav_active_class( '/pricing', $blueworx_nav_path ) ); ?>" href="<?php echo esc_url( home_url( '/pricing' ) ); ?>"><?php echo esc_html__( 'Pricing', 'blueworx-labs-wordpress' ); ?></a>
	<a class="<?php echo esc_attr( blueworx_public_nav_active_class( '/about', $blueworx_nav_path ) ); ?>" href="<?php echo esc_url( home_url( '/about' ) ); ?>"><?php echo esc_html__( 'About Us', 'blueworx-labs-wordpress' ); ?></a>
	<a class="<?php echo esc_attr( blueworx_public_nav_active_class( '/work', $blueworx_nav_path ) ); ?>" href="<?php echo esc_url( home_url( '/work' ) ); ?>" style="font-size:13.5px;padding-left:24px"><?php echo esc_html__( 'Work', 'blueworx-labs-wordpress' ); ?></a>
	<a class="<?php echo esc_attr( blueworx_public_nav_active_class( '/ai', $blueworx_nav_path ) ); ?>" href="<?php echo esc_url( home_url( '/ai' ) ); ?>"><?php echo esc_html__( 'AI Powered', 'blueworx-labs-wordpress' ); ?><span class="nav-tag tag-light"><?php echo esc_html__( 'New', 'blueworx-labs-wordpress' ); ?></span></a>
	<a href="<?php echo esc_url( home_url( '/portal' ) ); ?>"><?php echo esc_html__( 'Client Login', 'blueworx-labs-wordpress' ); ?></a>
	<a class="btn btn-brand btn-md" href="<?php echo esc_url( home_url( '/pricing' ) ); ?>"><?php echo esc_html__( 'Get a Quote', 'blueworx-labs-wordpress' ); ?></a>
</div>
