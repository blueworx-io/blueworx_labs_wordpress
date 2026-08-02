<?php
/**
 * BlueWorx admin & login re-skin (CSS-first).
 *
 * Restyles WordPress's own admin and login markup to the BlueWorx design system.
 * The only custom markup is the admin top bar / brand block and the Dashboard
 * hero-tiles widget below. Everything here is gated on the `admin_theme` feature
 * flag (default on) so it can be switched off from BlueWorx > Enhancements.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the BlueWorx admin theme is active.
 *
 * @return bool True when the admin_theme feature is enabled.
 */
function blueworx_admin_theme_enabled() {
	return blueworx_feature_enabled( 'admin_theme' );
}

/**
 * Gets the first character of a string, uppercased, multibyte-safe.
 *
 * @param string $text Source text.
 * @return string Single uppercase character, or an empty string.
 */
function blueworx_first_initial( $text ) {
	$text = trim( wp_strip_all_tags( (string) $text ) );

	if ( '' === $text ) {
		return '';
	}

	$first = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 1 ) : substr( $text, 0, 1 );

	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first ) : strtoupper( $first );
}

/**
 * Builds up to two initials from a display name.
 *
 * @param string $name Display name.
 * @return string Initials, e.g. "MW".
 */
function blueworx_user_initials( $name ) {
	$parts    = preg_split( '/\s+/', trim( (string) $name ) );
	$initials = '';

	if ( ! is_array( $parts ) ) {
		return '';
	}

	foreach ( $parts as $part ) {
		$initials .= blueworx_first_initial( $part );

		if ( strlen( $initials ) >= 2 ) {
			break;
		}
	}

	return $initials;
}

/**
 * Enqueues the admin re-skin on every admin screen.
 *
 * @return void
 */
function blueworx_enqueue_admin_theme() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	wp_enqueue_style(
		'blueworx-admin-fonts',
		BLUEWORX_LABS_URL . 'assets/css/blueworx-fonts.css',
		array(),
		blueworx_get_admin_asset_version( 'assets/css/blueworx-fonts.css' )
	);

	wp_enqueue_style(
		'blueworx-admin-theme',
		BLUEWORX_LABS_URL . 'assets/css/admin-theme.css',
		array( 'blueworx-admin-fonts' ),
		blueworx_get_admin_asset_version( 'assets/css/admin-theme.css' )
	);

	wp_enqueue_script(
		'blueworx-admin-menu-flyout',
		BLUEWORX_LABS_URL . 'assets/js/admin-menu-flyout.js',
		array(),
		blueworx_get_admin_asset_version( 'assets/js/admin-menu-flyout.js' ),
		true
	);
}
add_action( 'admin_enqueue_scripts', 'blueworx_enqueue_admin_theme' );

/**
 * Prints the critical layout skeleton inline in <head>, before any stylesheet.
 *
 * The full re-skin lives in the enqueued admin-theme.css. On slow connections —
 * or where an asset optimiser / cache plugin combines or defers the <link> — that
 * external sheet can apply a few frames (sometimes seconds) after first paint. In
 * that gap WordPress paints its native chrome: the dark #wpadminbar shows as a
 * stray full-width line under the top bar, and the sidebar, not yet offset below
 * the fixed 60px header, overlaps it. This handful of rules is printed inline in
 * the document head, so it cannot be deferred or async-loaded and lands in the
 * first paint, holding the corrected geometry until the full sheet arrives.
 *
 * Hooked on admin_print_styles at a low priority so it prints ahead of the
 * enqueued admin-theme.css <link> (admin_head fires after styles are printed,
 * which would place it too late and let the native chrome paint first).
 *
 * Values are literals (not the --bw-* custom properties) on purpose: those
 * properties are defined in the external sheet, which is exactly the resource
 * that may be late here. Keep the 60px top bar height in sync with
 * --bw-topbar-h in admin-theme.css.
 *
 * The block/site editor's `.interface-interface-skeleton` offset is mirrored
 * here too. The skeleton itself is not in the DOM until the editor's own JS
 * has mounted, which normally happens well after the enqueued admin-theme.css
 * has already applied — so in the common case this extra rule is a no-op. But
 * a caching or asset-optimiser plugin can defer/async that <link>, and if it
 * lands after the editor mounts, the skeleton would paint at core's default
 * (unoffset) position while the topbar has not yet gained its position:fixed
 * treatment either — then both flip into place together once the sheet
 * arrives, and on a slow enough connection that flip is visible: exactly the
 * reported overlap, flashing on every load instead of being permanently
 * fixed. Fullscreen is not mirrored here: nothing in this critical block gives
 * .bw-topbar/.bw-brand position:fixed or display:flex, so they cannot yet be
 * covering anything in this window, fullscreen or not — core's own CSS
 * already puts the skeleton at top:0 in that state, which is what we want.
 *
 * @return void
 */
function blueworx_print_admin_theme_critical_css() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}
	?>
	<style id="blueworx-admin-critical">
		@media only screen and (min-width: 783px) {
			#wpadminbar { display: none !important; }
			html.wp-toolbar { padding-top: 0 !important; }
			#wpcontent { padding-top: 60px; }
			#adminmenuback,
			#adminmenuwrap { position: fixed !important; bottom: 0 !important; height: auto !important; }
			#adminmenuback { top: 0 !important; }
			#adminmenuwrap { top: 60px !important; left: 0; }
			body.block-editor-page:not(.is-fullscreen-mode) .interface-interface-skeleton { top: 60px; }
		}
	</style>
	<?php
}
add_action( 'admin_print_styles', 'blueworx_print_admin_theme_critical_css', -100 );

/**
 * Enqueues the login re-skin.
 *
 * @return void
 */
function blueworx_enqueue_login_theme() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	wp_enqueue_style(
		'blueworx-admin-fonts',
		BLUEWORX_LABS_URL . 'assets/css/blueworx-fonts.css',
		array(),
		blueworx_get_admin_asset_version( 'assets/css/blueworx-fonts.css' )
	);

	wp_enqueue_style(
		'blueworx-login-theme',
		BLUEWORX_LABS_URL . 'assets/css/login-theme.css',
		array( 'blueworx-admin-fonts' ),
		blueworx_get_admin_asset_version( 'assets/css/login-theme.css' )
	);

	// The brand mark shows the site's first initial; pass it to CSS as a variable.
	$initial = blueworx_first_initial( get_bloginfo( 'name' ) );

	if ( '' !== $initial ) {
		wp_add_inline_style(
			'blueworx-login-theme',
			'.login h1 a::before{content:"' . esc_attr( $initial ) . '";}'
		);
	}
}
add_action( 'login_enqueue_scripts', 'blueworx_enqueue_login_theme' );

/**
 * Points the login logo at the site instead of wordpress.org.
 *
 * @return string Home URL.
 */
function blueworx_login_header_url() {
	return home_url( '/' );
}

/**
 * Uses the site name as the login logo text (rendered as a wordmark by CSS).
 *
 * @return string Site name.
 */
function blueworx_login_header_text() {
	return get_bloginfo( 'name' );
}

if ( blueworx_admin_theme_enabled() ) {
	add_filter( 'login_headerurl', 'blueworx_login_header_url' );
	add_filter( 'login_headertext', 'blueworx_login_header_text' );
}

/**
 * The wp-login action currently being displayed.
 *
 * @return string Action slug, e.g. "login", "lostpassword", "register".
 */
function blueworx_login_action() {
	// Display-only branching on a public, unauthenticated screen — reading the
	// action WordPress has already routed on, not acting on input.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';

	return '' === $action ? 'login' : $action;
}

/**
 * Renders the dark brand panel that forms the left half of the login screen.
 *
 * Fires on `login_header`, immediately after <body> opens, so the panel is a
 * sibling of #login rather than inside it. It is positioned by CSS, not by
 * document order — see the header comment in assets/css/login-theme.css.
 *
 * @return void
 */
function blueworx_render_login_panel() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	$site_name = get_bloginfo( 'name', 'display' );
	$initial   = blueworx_first_initial( $site_name );

	/**
	 * Filters the small badge shown above the login headline.
	 *
	 * @param string $badge Badge text.
	 */
	$badge = apply_filters( 'blueworx_login_badge', __( 'Admin Login', 'blueworx-labs-wordpress' ) );

	/**
	 * Filters the login panel headline.
	 *
	 * @param string $headline Headline text.
	 */
	$headline = apply_filters(
		'blueworx_login_headline',
		__( 'Everything Your Site Needs, In One Place', 'blueworx-labs-wordpress' )
	);

	/**
	 * Filters the login panel tagline shown under the headline.
	 *
	 * @param string $tagline Tagline text.
	 */
	$tagline = apply_filters(
		'blueworx_login_tagline',
		__( 'Manage content, media, and your team from a single dashboard built for clarity.', 'blueworx-labs-wordpress' )
	);
	?>
	<div class="bw-login-panel" aria-hidden="true">
		<div class="bw-login-panel-inner">
			<a class="bw-login-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" tabindex="-1">
				<span class="bw-login-brand-mark"><?php echo esc_html( $initial ); ?></span>
				<span class="bw-login-brand-name"><?php echo esc_html( $site_name ); ?></span>
			</a>
			<div class="bw-login-pitch">
				<?php if ( '' !== $badge ) : ?>
					<span class="bw-login-badge"><?php echo esc_html( $badge ); ?></span>
				<?php endif; ?>
				<p class="bw-login-headline"><?php echo esc_html( $headline ); ?></p>
				<?php if ( '' !== $tagline ) : ?>
					<p class="bw-login-tagline"><?php echo esc_html( $tagline ); ?></p>
				<?php endif; ?>
			</div>
			<p class="bw-login-panel-footer">
				<?php
				printf(
					/* translators: 1: Current year, 2: Site title. */
					esc_html__( '© %1$s %2$s · Powered by BlueWorx', 'blueworx-labs-wordpress' ),
					esc_html( gmdate( 'Y' ) ),
					esc_html( $site_name )
				);
				?>
			</p>
		</div>
	</div>
	<?php
}
add_action( 'login_header', 'blueworx_render_login_panel' );

/**
 * Adds the card heading above the login form.
 *
 * Hooked to `login_message` because that is the only output point inside #login
 * between the logo and the form. Any real message WordPress wants to show is
 * kept and rendered below the heading.
 *
 * @param string $message Existing login message markup.
 * @return string Heading markup followed by the original message.
 */
function blueworx_login_card_heading( $message ) {
	if ( ! blueworx_admin_theme_enabled() ) {
		return $message;
	}

	switch ( blueworx_login_action() ) {
		case 'lostpassword':
		case 'retrievepassword':
			$title    = __( 'Forgot Your Password?', 'blueworx-labs-wordpress' );
			$subtitle = __( 'We&#8217;ll email you a link to set a new one.', 'blueworx-labs-wordpress' );
			break;

		case 'resetpass':
		case 'rp':
			$title    = __( 'Choose a New Password', 'blueworx-labs-wordpress' );
			$subtitle = __( 'Make it long, and different from your last one.', 'blueworx-labs-wordpress' );
			break;

		case 'register':
			$title    = __( 'Create an Account', 'blueworx-labs-wordpress' );
			$subtitle = __( 'Sign up to get started.', 'blueworx-labs-wordpress' );
			break;

		case 'login':
			$title    = __( 'Welcome Back', 'blueworx-labs-wordpress' );
			$subtitle = __( 'Sign in to manage your site.', 'blueworx-labs-wordpress' );
			break;

		default:
			// Confirm-admin-email, logout confirmations and the like: leave alone.
			return $message;
	}

	$heading = sprintf(
		'<p class="bw-login-title">%s</p><p class="bw-login-subtitle">%s</p>',
		esc_html( $title ),
		esc_html( $subtitle )
	);

	return $heading . $message;
}
add_filter( 'login_message', 'blueworx_login_card_heading' );

/**
 * Whether the lost-password link has already been rendered in the password row.
 *
 * @param bool|null $set Pass true to record that it has.
 * @return bool True once the password-row link has been rendered.
 */
function blueworx_login_forgot_link_rendered( $set = null ) {
	static $rendered = false;

	if ( true === $set ) {
		$rendered = true;
	}

	return $rendered;
}

/**
 * Renders the "Forgot Password?" link that sits on the password label row.
 *
 * `login_form` fires inside the form just after the password field — the
 * closest hook to where the link belongs, but still one level out: the link has
 * to be a CHILD of .user-pass-wrap for CSS to position it against that row.
 * Nothing hooks inside the row, so the script below moves it in.
 *
 * It runs inline, immediately after the markup it moves, so the link is in
 * place before the browser first paints it — a footer script would show it in
 * the wrong spot first. Without script it stays where PHP put it, as an
 * ordinary link under the password field, which is why the positioning rule is
 * scoped to `.user-pass-wrap > .bw-login-forgot`.
 *
 * `login_form` only fires on the sign-in form, so nothing here tests the action.
 *
 * @return void
 */
function blueworx_render_login_forgot_link() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	printf(
		'<a class="bw-login-forgot" href="%s">%s</a>',
		esc_url( wp_lostpassword_url() ),
		esc_html__( 'Forgot Password?', 'blueworx-labs-wordpress' )
	);

	blueworx_login_forgot_link_rendered( true );

	if ( ! function_exists( 'wp_print_inline_script_tag' ) ) {
		return;
	}

	wp_print_inline_script_tag(
		'( function () {' .
		'var link = document.querySelector( ".bw-login-forgot" );' .
		'var row = document.querySelector( ".user-pass-wrap" );' .
		'if ( link && row ) { row.appendChild( link ); }' .
		'}() );'
	);
}
add_action( 'login_form', 'blueworx_render_login_forgot_link' );

/**
 * Drops the duplicate lost-password link from the footer nav.
 *
 * Only on the sign-in form, where the link has already been rendered in the
 * password row — the register and reset forms keep theirs. The flag is what
 * decides that, so the two can never disagree.
 *
 * @param string $html_link Lost-password link markup.
 * @return string Markup, or an empty string when already shown above.
 */
function blueworx_filter_lost_password_link( $html_link ) {
	if ( ! blueworx_admin_theme_enabled() || ! blueworx_login_forgot_link_rendered() ) {
		return $html_link;
	}

	return '';
}
add_filter( 'lost_password_html_link', 'blueworx_filter_lost_password_link' );

/**
 * Drops the "|" that would be left dangling after the registration link.
 *
 * Core prints the separator straight after the register link, expecting the
 * lost-password link to follow it — which on the sign-in form it no longer
 * does. This cannot key off the render flag the way the link filter does: core
 * resolves the separator before the form is rendered, so it tests the same
 * condition that causes the link to be removed in the first place.
 *
 * @param string $separator Separator string.
 * @return string Separator, or an empty string on the sign-in form.
 */
function blueworx_filter_login_link_separator( $separator ) {
	if ( ! blueworx_admin_theme_enabled() || 'login' !== blueworx_login_action() ) {
		return $separator;
	}

	return '';
}
add_filter( 'login_link_separator', 'blueworx_filter_login_link_separator' );

/**
 * Flags the sign-in screens where the footer nav will render with no links.
 *
 * On the sign-in form the lost-password link moves up to the password row, so
 * with registration closed #nav comes out holding nothing but whitespace —
 * which `:empty` does not match, hence a class rather than a CSS-only rule.
 *
 * @param string[] $classes Body classes.
 * @return string[] Body classes, possibly with the empty-nav flag.
 */
function blueworx_login_body_class( $classes ) {
	if ( ! blueworx_admin_theme_enabled() ) {
		return $classes;
	}

	if ( 'login' === blueworx_login_action() && ! get_option( 'users_can_register' ) ) {
		$classes[] = 'bw-login-nav-empty';
	}

	return $classes;
}
add_filter( 'login_body_class', 'blueworx_login_body_class' );

/**
 * Prefixes the registration link with the "New here?" lead-in from the design.
 *
 * @param string $link Registration link markup.
 * @return string Registration link with a lead-in.
 */
function blueworx_filter_register_link( $link ) {
	if ( ! blueworx_admin_theme_enabled() || 'login' !== blueworx_login_action() ) {
		return $link;
	}

	return '<span class="bw-login-register-lead">' .
		esc_html__( 'New here?', 'blueworx-labs-wordpress' ) .
		'</span> ' . $link;
}
add_filter( 'register', 'blueworx_filter_register_link' );

/**
 * Retitles the core login strings the design renames.
 *
 * Scoped to the login screen and to core's own text domain, and matched on the
 * exact source string, so a translated site or another plugin's copy is never
 * touched. Anything not listed falls straight through.
 *
 * @param string $translated Translated text.
 * @param string $text       Original text.
 * @param string $domain     Text domain.
 * @return string Possibly replaced text.
 */
function blueworx_login_strings( $translated, $text, $domain ) {
	if ( 'default' !== $domain ) {
		return $translated;
	}

	switch ( $text ) {
		case 'Username or Email Address':
			return __( 'Email or Username', 'blueworx-labs-wordpress' );

		case 'Remember Me':
			return __( 'Remember me on this device', 'blueworx-labs-wordpress' );

		case 'Log In':
			return __( 'Sign In', 'blueworx-labs-wordpress' );

		case 'Register':
			return __( 'Create an Account', 'blueworx-labs-wordpress' );
	}

	return $translated;
}

/**
 * Hooks the login-only string changes.
 *
 * Added on `login_init` rather than at load so the gettext filter is never
 * live on the front end or in wp-admin, where the same strings mean other
 * things ("Log In" in the admin bar, "Register" in Settings).
 *
 * @return void
 */
function blueworx_add_login_string_filters() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	add_filter( 'gettext', 'blueworx_login_strings', 10, 3 );
}
add_action( 'login_init', 'blueworx_add_login_string_filters' );

/**
 * Adds the placeholder shown in the empty username field.
 *
 * WordPress prints that input with no hook around it, so this is the one piece
 * of the design that needs script. It is cosmetic and additive: with script
 * blocked, or on WordPress older than 5.7, the field is simply a normal empty
 * field.
 *
 * @return void
 */
function blueworx_print_login_placeholder_script() {
	if ( ! blueworx_admin_theme_enabled() || 'login' !== blueworx_login_action() ) {
		return;
	}

	if ( ! function_exists( 'wp_print_inline_script_tag' ) ) {
		return;
	}

	wp_print_inline_script_tag(
		sprintf(
			'var bwLoginUser = document.getElementById( "user_login" );' .
			'if ( bwLoginUser && ! bwLoginUser.value ) { bwLoginUser.placeholder = %s; }',
			wp_json_encode( __( 'you@example.com', 'blueworx-labs-wordpress' ) )
		)
	);
}
add_action( 'login_footer', 'blueworx_print_login_placeholder_script' );

/**
 * Renders the BlueWorx brand block and admin top bar.
 *
 * Replaces the WordPress admin bar visually on desktop (the stylesheet hides
 * #wpadminbar at >=783px). The native admin bar is intentionally left rendered so
 * the responsive menu toggle it provides keeps working on small screens.
 *
 * @return void
 */
function blueworx_render_admin_topbar() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	$user       = wp_get_current_user();
	$site_name  = get_bloginfo( 'name' );
	$initial    = blueworx_first_initial( $site_name );
	$initials   = blueworx_user_initials( $user->display_name );
	$page_title = isset( $GLOBALS['title'] ) ? (string) $GLOBALS['title'] : '';
	?>
	<div class="bw-brand">
		<span class="bw-brand-mark" aria-hidden="true"><?php echo esc_html( $initial ); ?></span>
		<span class="bw-brand-text">
			<span class="bw-brand-name"><?php echo esc_html( $site_name ); ?></span>
			<span class="bw-brand-sub">BlueWorx</span>
		</span>
	</div>
	<div class="bw-topbar">
		<div class="bw-topbar-title"><?php echo esc_html( $page_title ); ?></div>
		<div class="bw-topbar-actions">
			<a class="bw-topbar-site" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" width="15" height="15" aria-hidden="true" focusable="false">
					<path d="M14 4h6v6"></path><path d="M20 4l-9 9"></path>
					<path d="M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"></path>
				</svg>
				<?php esc_html_e( 'View Site', 'blueworx-labs-wordpress' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'blueworx-labs-wordpress' ); ?></span>
			</a>
			<details class="bw-user">
				<summary class="bw-user-summary">
					<span class="bw-user-avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
					<span class="bw-user-name"><?php echo esc_html( $user->display_name ); ?></span>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" aria-hidden="true" focusable="false">
						<path d="M6 9l6 6 6-6"></path>
					</svg>
				</summary>
				<div class="bw-user-menu">
					<a href="<?php echo esc_url( get_edit_profile_url() ); ?>"><?php esc_html_e( 'Edit Profile', 'blueworx-labs-wordpress' ); ?></a>
					<a href="<?php echo esc_url( wp_logout_url() ); ?>"><?php esc_html_e( 'Log Out', 'blueworx-labs-wordpress' ); ?></a>
				</div>
			</details>
		</div>
	</div>
	<?php
}
add_action( 'in_admin_header', 'blueworx_render_admin_topbar', 5 );

/**
 * Customises the Dashboard to the BlueWorx layout.
 *
 * Removes stock widgets that are not part of the BlueWorx mockup and registers a
 * hero-tiles widget with live counts. The native Activity, Quick Draft, and Site
 * Health widgets are kept and restyled by the stylesheet.
 *
 * @return void
 */
function blueworx_customise_dashboard() {
	if ( ! blueworx_admin_theme_enabled() ) {
		return;
	}

	// Remove widgets not present in the BlueWorx dashboard mockup.
	remove_action( 'welcome_panel', 'wp_welcome_panel' );
	remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );    // WordPress Events & News.
	remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' ); // At a Glance (replaced by hero tiles).

	wp_add_dashboard_widget(
		'blueworx_dashboard_stats',
		__( 'At a Glance', 'blueworx-labs-wordpress' ),
		'blueworx_render_dashboard_stats'
	);

	/*
	 * Promote the hero tiles to the "high" priority group. do_meta_boxes() renders
	 * high -> sorted -> core, and re-sorts saved user layouts into "sorted", so a
	 * widget left in "core" lands below them (or gets lost) on any site where the
	 * dashboard has been rearranged. "high" keeps the tiles at the top for everyone.
	 */
	global $wp_meta_boxes;
	if ( isset( $wp_meta_boxes['dashboard']['normal']['core']['blueworx_dashboard_stats'] ) ) {
		$widget = $wp_meta_boxes['dashboard']['normal']['core']['blueworx_dashboard_stats'];

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Repositioning a dashboard widget requires updating the meta-box registry.
		unset( $wp_meta_boxes['dashboard']['normal']['core']['blueworx_dashboard_stats'] );

		if ( ! isset( $wp_meta_boxes['dashboard']['normal']['high'] ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Repositioning a dashboard widget requires updating the meta-box registry.
			$wp_meta_boxes['dashboard']['normal']['high'] = array();
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Repositioning a dashboard widget requires updating the meta-box registry.
		$wp_meta_boxes['dashboard']['normal']['high'] = array( 'blueworx_dashboard_stats' => $widget )
			+ $wp_meta_boxes['dashboard']['normal']['high'];
	}
}
add_action( 'wp_dashboard_setup', 'blueworx_customise_dashboard', 20 );

/**
 * Renders the four hero stat tiles with live counts.
 *
 * @return void
 */
function blueworx_render_dashboard_stats() {
	$posts       = (int) wp_count_posts( 'post' )->publish;
	$pages       = (int) wp_count_posts( 'page' )->publish;
	$comments    = (int) wp_count_comments()->approved;
	$attachments = (int) array_sum( (array) wp_count_attachments() );

	$tiles = array(
		array(
			'count' => $posts,
			'label' => __( 'Posts', 'blueworx-labs-wordpress' ),
			'url'   => admin_url( 'edit.php' ),
		),
		array(
			'count' => $pages,
			'label' => __( 'Pages', 'blueworx-labs-wordpress' ),
			'url'   => admin_url( 'edit.php?post_type=page' ),
		),
		array(
			'count' => $comments,
			'label' => __( 'Comments', 'blueworx-labs-wordpress' ),
			'url'   => admin_url( 'edit-comments.php' ),
		),
		array(
			'count' => $attachments,
			'label' => __( 'Media Items', 'blueworx-labs-wordpress' ),
			'url'   => admin_url( 'upload.php' ),
		),
	);

	echo '<div class="bw-stat-grid">';
	foreach ( $tiles as $tile ) {
		printf(
			'<a class="bw-stat-card" href="%1$s"><div class="bw-stat-num">%2$s</div><div class="bw-stat-label">%3$s</div></a>',
			esc_url( $tile['url'] ),
			esc_html( number_format_i18n( $tile['count'] ) ),
			esc_html( $tile['label'] )
		);
	}
	echo '</div>';
}

/**
 * Sets the dashboard widgets hidden by default.
 *
 * WordPress applies the default_hidden_meta_boxes filter only while a user has no
 * saved metaboxhidden_dashboard preference, so this establishes the BlueWorx
 * default layout without ever overriding anyone who has set their own Screen
 * Options. The moment a user ticks their own boxes, their choice wins.
 *
 * Hides Elementor Overview, Quick Draft and Site Management; everything else —
 * At a Glance (the BlueWorx hero tiles), SureRank Website Insights, Object Cache
 * Pro, Site Health Status and Activity — stays visible. Nothing here depends on
 * SureRank, Object Cache or Elementor being active: an inactive plugin never
 * registers its widget, so its box is neither shown nor needs hiding.
 *
 * Quick Draft and Elementor Overview are matched by their stable IDs. Site
 * Management has no documented stable ID, so it is matched by title against the
 * live meta-box registry — additive, case-insensitive, and only ever hides a box
 * that actually exists.
 *
 * @param array          $hidden Meta box IDs hidden by default.
 * @param WP_Screen|null $screen Current screen.
 * @return array Filtered hidden meta box IDs.
 */
function blueworx_default_hidden_dashboard_widgets( $hidden, $screen ) {
	if ( ! blueworx_admin_theme_enabled() || ! isset( $screen->id ) || 'dashboard' !== $screen->id ) {
		return $hidden;
	}

	$hidden = (array) $hidden;

	// Boxes with stable IDs.
	foreach ( array( 'dashboard_quick_press', 'e-dashboard-overview' ) as $id ) {
		if ( ! in_array( $id, $hidden, true ) ) {
			$hidden[] = $id;
		}
	}

	// Boxes with no documented stable ID, matched by title against the registry.
	$hide_by_title = array( 'site management' );
	global $wp_meta_boxes;

	if ( isset( $wp_meta_boxes['dashboard'] ) && is_array( $wp_meta_boxes['dashboard'] ) ) {
		foreach ( $wp_meta_boxes['dashboard'] as $contexts ) {
			foreach ( (array) $contexts as $boxes ) {
				foreach ( (array) $boxes as $box ) {
					if ( empty( $box['id'] ) || empty( $box['title'] ) || in_array( $box['id'], $hidden, true ) ) {
						continue;
					}

					$title = strtolower( trim( wp_strip_all_tags( (string) $box['title'] ) ) );

					foreach ( $hide_by_title as $needle ) {
						if ( false !== strpos( $title, $needle ) ) {
							$hidden[] = $box['id'];
							break;
						}
					}
				}
			}
		}
	}

	return array_values( array_unique( $hidden ) );
}
add_filter( 'default_hidden_meta_boxes', 'blueworx_default_hidden_dashboard_widgets', 10, 2 );

/**
 * Marks the first visible item of each semantic group and queues its heading.
 *
 * A synthetic $menu row was rejected as the mechanism for headings.
 * _wp_menu_output() (wp-admin/menu-header.php) only has two branches for a
 * row: a separator (wp-menu-separator in the class field — rendered with no
 * title and no link) or an ordinary item, which core always wraps in a
 * focusable <a> regardless of what the URL field contains. There is no branch
 * that renders a titled row without an anchor, so a synthetic row can never be
 * both visible and inert at once — it would either show nothing (separator) or
 * be a broken, clickable link to a page that does not exist (ordinary item).
 *
 * Instead, the first real item of each populated group is tagged
 * bw-group-start and bw-group-start-{key}, and its translated label is queued
 * for blueworx_print_admin_menu_group_heading_labels() to emit as a CSS custom
 * property scoped to that row's id — the same "inline <style> keyed by row id"
 * idiom blueworx_hide_admin_menu_rows() already uses. CSS (Task 10) renders the
 * label as generated ::before content, so nothing is hard-coded into the
 * stylesheet and it stays translatable.
 *
 * Uses blueworx_admin_menu_order() directly (rather than duplicating its
 * grouping logic) so the heading markers can never drift from the actual
 * render order.
 *
 * A group with no visible items gets no heading.
 *
 * @return void
 */
function blueworx_mark_admin_menu_group_starts() {
	global $menu;

	if ( ! function_exists( 'blueworx_get_admin_menu_group_assignments' ) ) {
		return;
	}

	$assignments = blueworx_get_admin_menu_group_assignments();
	$hidden      = blueworx_get_hidden_admin_menu_items();
	$labels      = blueworx_get_admin_menu_groups();
	$seen        = array();
	$rules       = array();

	foreach ( blueworx_admin_menu_order( array() ) as $slug ) {
		if ( ! isset( $assignments[ $slug ] ) || in_array( $slug, $hidden, true ) ) {
			continue;
		}

		$group = $assignments[ $slug ];

		if ( isset( $seen[ $group ] ) ) {
			continue;
		}

		foreach ( (array) $menu as $index => $menu_item ) {
			$item_slug = isset( $menu_item[2] ) ? (string) $menu_item[2] : '';

			if ( $item_slug !== $slug ) {
				continue;
			}

			$id = isset( $menu_item[5] ) ? (string) $menu_item[5] : '';

			if ( '' === $id ) {
				break;
			}

			$seen[ $group ] = true;

			$menu[ $index ][4] = trim( ( isset( $menu_item[4] ) ? $menu_item[4] : '' ) . ' bw-group-start bw-group-start-' . $group ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Direct mutation of the $menu global inside the "admin_menu" action is the standard, documented way to alter admin menu rows; WordPress provides no API for this.

			$rules[ blueworx_sanitize_admin_menu_id( $id ) ] = isset( $labels[ $group ] ) ? $labels[ $group ] : '';

			break;
		}
	}

	$GLOBALS['blueworx_admin_menu_group_heading_rules'] = $rules;
}
if ( blueworx_feature_enabled( 'admin_theme' ) ) {
	add_action( 'admin_menu', 'blueworx_mark_admin_menu_group_starts', 998 );
}

/**
 * Prints the group heading labels as inline CSS custom properties.
 *
 * Keeps the labels out of the compiled stylesheet (Task 10's ::before rule
 * just reads var(--bw-group-label)) so they stay translatable per-request.
 *
 * @return void
 */
function blueworx_print_admin_menu_group_heading_labels() {
	$rules = isset( $GLOBALS['blueworx_admin_menu_group_heading_rules'] ) ? (array) $GLOBALS['blueworx_admin_menu_group_heading_rules'] : array();

	if ( empty( $rules ) ) {
		return;
	}
	?>
	<style>
		<?php foreach ( $rules as $row_id => $label ) : ?>
			#adminmenu [id="<?php echo esc_attr( $row_id ); ?>"] { --bw-group-label: "<?php echo esc_attr( addcslashes( (string) $label, '"\\' ) ); ?>"; }
		<?php endforeach; ?>
	</style>
	<?php
}
if ( blueworx_feature_enabled( 'admin_theme' ) ) {
	add_action( 'admin_head', 'blueworx_print_admin_menu_group_heading_labels' );
}

/**
 * Replaces dashicons with the design's icon set on mapped core menus.
 *
 * Unmapped menus are left alone, so third-party plugins keep their own glyph.
 * Field 4 is the class field, 6 the icon URL. "none" stops WordPress printing
 * its own dashicon span; the actual SVG is injected by
 * blueworx_print_admin_menu_decorations() (Task 8), keyed off the bw-has-icon
 * class this sets.
 *
 * @return void
 */
function blueworx_swap_admin_menu_icons() {
	global $menu;

	foreach ( (array) $menu as $index => $menu_item ) {
		$slug = isset( $menu_item[2] ) ? (string) $menu_item[2] : '';
		$icon = blueworx_get_admin_menu_icon( $slug );

		if ( '' === $icon ) {
			continue;
		}

		$menu[ $index ][4] = trim( ( isset( $menu_item[4] ) ? $menu_item[4] : '' ) . ' bw-has-icon' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Direct $menu mutation inside "admin_menu" is the documented way to alter admin menu rows.
		$menu[ $index ][6] = 'none'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
	}
}
if ( blueworx_feature_enabled( 'admin_theme' ) ) {
	add_action( 'admin_menu', 'blueworx_swap_admin_menu_icons', 997 );
}

/**
 * Prints inline icons and count badges into the rendered menu.
 *
 * WordPress renders the icon span before we can filter the row's markup, so
 * both the icon and the badge are injected client-side, in one script keyed by
 * row id — see Task 7's commit for why the icon can't be delivered server-side.
 *
 * Where WordPress already renders its own bubble on a row (plugin updates,
 * comments awaiting moderation), core's bubble wins and no BlueWorx badge is
 * added — two count bubbles on one row would be worse than none.
 *
 * @return void
 */
function blueworx_print_admin_menu_decorations() {
	global $menu;

	$badges = blueworx_get_admin_menu_badge_counts();
	$rows   = array();

	foreach ( (array) $menu as $menu_item ) {
		$slug = isset( $menu_item[2] ) ? (string) $menu_item[2] : '';
		$id   = isset( $menu_item[5] ) ? (string) $menu_item[5] : '';

		if ( '' === $id ) {
			continue;
		}

		$icon  = blueworx_get_admin_menu_icon( $slug );
		$count = isset( $badges[ $slug ] ) ? (int) $badges[ $slug ] : 0;

		if ( '' === $icon && 0 === $count ) {
			continue;
		}

		// Never trust raw SVG into the page, even though these strings are our
		// own fixed, hardcoded set (not user input): run every icon through
		// wp_kses with the explicit SVG allowlist before it is ever echoed.
		$icon = '' === $icon ? '' : wp_kses( $icon, blueworx_get_svg_kses_allowlist() );

		$rows[ blueworx_sanitize_admin_menu_id( $id ) ] = array(
			'icon'  => $icon,
			'count' => $count,
			'label' => $count > 0
				/* translators: %d: number of items. */
				? sprintf( _n( '%d item', '%d items', $count, 'blueworx-labs-wordpress' ), $count )
				: '',
		);
	}

	if ( empty( $rows ) ) {
		return;
	}
	?>
	<script>
		( function () {
			var rows = <?php echo wp_json_encode( $rows ); ?>;

			Object.keys( rows ).forEach( function ( id ) {
				var row = document.getElementById( id );

				if ( ! row ) {
					return;
				}

				var data = rows[ id ];
				var slot = row.querySelector( '.wp-menu-image' );

				if ( slot && data.icon ) {
					slot.innerHTML = data.icon;
				}

				// Core's own bubble wins; never render two counts on one row.
				if ( data.count && ! row.querySelector( '.update-plugins, .awaiting-mod' ) ) {
					var name = row.querySelector( '.wp-menu-name' );

					if ( name && ! name.querySelector( '.bw-badge' ) ) {
						var badge = document.createElement( 'span' );
						badge.className = 'bw-badge';
						badge.textContent = String( data.count );
						badge.setAttribute( 'aria-label', data.label );
						name.appendChild( badge );
					}
				}
			} );
		}() );
	</script>
	<?php
}
if ( blueworx_feature_enabled( 'admin_theme' ) ) {
	add_action( 'admin_footer', 'blueworx_print_admin_menu_decorations' );
}

/**
 * Appends the design's Log Out row to the end of the sidebar.
 *
 * Duplicates the top bar's user menu logout. That is intentional — the v2
 * design shows both.
 *
 * @return void
 */
function blueworx_print_admin_menu_logout() {
	$icon = wp_kses( blueworx_get_admin_menu_icon( 'bw-logout', 18 ), blueworx_get_svg_kses_allowlist() );

	$markup = sprintf(
		'<a href="%1$s">%2$s<span>%3$s</span></a>',
		esc_url( wp_logout_url() ),
		$icon,
		esc_html__( 'Log Out', 'blueworx-labs-wordpress' )
	);
	?>
	<script>
		( function () {
			var menu = document.getElementById( 'adminmenu' );

			if ( ! menu || menu.querySelector( '.bw-logout' ) ) {
				return;
			}

			var item = document.createElement( 'li' );
			item.className = 'bw-logout';
			item.innerHTML = <?php echo wp_json_encode( $markup ); ?>;
			menu.appendChild( item );
		}() );
	</script>
	<?php
}
if ( blueworx_feature_enabled( 'admin_theme' ) ) {
	add_action( 'admin_footer', 'blueworx_print_admin_menu_logout' );
}
