<?php
/**
 * The customer dashboard: this plugin's frame, the shop's and other
 * plugins' panels inside it.
 *
 * SureCart seeds a customer dashboard page and renders its own dashboard
 * there. This dresses that page instead: one design, one nav, and each panel
 * filled by whichever plugin owns that data. Nothing here re-renders a
 * shop's records — a panel is a block or a shortcode, rendered by the plugin
 * that registered it, in a card of ours.
 *
 * Two filters let another plugin take part without this file knowing it
 * exists. blueworx_store_panel lets it put content on any panel, the overview
 * included — a club's welcome pack goes there. blueworx_store_dashboard_url
 * lets it claim the dashboard's address outright: SureCart's page then
 * redirects to it with the view and any action preserved, and the claimant
 * draws its own screen.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where the customer dashboard lives, or '' for SureCart's own page dressed
 * by this plugin.
 *
 * @return string
 */
function blueworx_store_dashboard_url() {
	if ( ! function_exists( 'apply_filters' ) ) {
		return '';
	}
	/**
	 * Filters where the customer dashboard lives.
	 *
	 * Empty means SureCart's own dashboard page, dressed by this plugin. A URL
	 * means another plugin serves the dashboard there; SureCart's page then
	 * redirects to it with the view and any action preserved.
	 *
	 * @param string $url '' by default.
	 */
	return trim( (string) apply_filters( 'blueworx_store_dashboard_url', '' ) );
}

/**
 * Where this request should be sent instead, or '' to stay put.
 *
 * Two journeys meet here. A signed-out visitor who reaches the dashboard is
 * sent to the login page rather than shown a frame with nothing in it. And
 * when another plugin has claimed the dashboard's address, a member who
 * followed the shop's own account link, or an old bookmark, is carried across
 * with the panel they asked for intact — and with the action too, because a
 * bookmark of the shop's account page can name one ("edit this customer"),
 * and dropping it would land them on a screen that reads their details back
 * instead of the form they saved last time.
 *
 * A claim is honoured whether or not anyone is signed in: the claimant
 * guards its own door.
 *
 * Pure, so both journeys are testable without a WordPress runtime.
 *
 * @param int    $queried_id   The post this request resolved to, 0 for none.
 * @param int    $dashboard_id The page id the shop recorded, 0 when it has none.
 * @param string $claimed_url  Another plugin's dashboard address, '' when nobody has claimed it.
 * @param bool   $signed_in    Whether anyone is signed in.
 * @param string $view         The panel named in the address, '' for the overview.
 * @param string $login_url    The login page, '' when it cannot be built.
 * @param string $model        The shop record an action address names, '' for none.
 * @param string $action       What it asks be done with it, '' for none.
 * @param string $id           Which record, '' when the action needs none.
 * @return string
 */
function blueworx_store_redirect_to( $queried_id, $dashboard_id, $claimed_url, $signed_in, $view, $login_url, $model = '', $action = '', $id = '' ) {
	$queried_id   = (int) $queried_id;
	$dashboard_id = (int) $dashboard_id;
	if ( $dashboard_id <= 0 || $queried_id !== $dashboard_id ) {
		return '';
	}
	$claimed_url = trim( (string) $claimed_url );
	if ( '' === $claimed_url ) {
		return $signed_in ? '' : (string) $login_url;
	}
	$target = '' === trim( (string) $view ) ? $claimed_url : blueworx_store_view_url( trim( (string) $view ), $claimed_url );
	if ( '' === trim( (string) $model ) || '' === trim( (string) $action ) ) {
		return $target;
	}
	$target .= ( false === strpos( $target, '?' ) ? '?' : '&' )
		. BLUEWORX_STORE_ACTION_MODEL_ARG . '=' . rawurlencode( trim( (string) $model ) )
		. '&' . BLUEWORX_STORE_ACTION_ARG . '=' . rawurlencode( trim( (string) $action ) );
	// Most actions name the record they act on; a few (adding a card) do not.
	return '' === trim( (string) $id ) ? $target : $target . '&id=' . rawurlencode( trim( (string) $id ) );
}

/**
 * Act on that decision.
 *
 * Runs on template_redirect at priority 5, so the answer is settled before
 * WordPress picks a template and long before anything has been sent to the
 * browser. wp_safe_redirect() refuses a foreign host, which is right: a
 * claim must be on this site.
 *
 * @return void
 */
function blueworx_store_route() {
	if ( ! function_exists( 'wp_safe_redirect' ) || ! function_exists( 'get_queried_object_id' ) ) {
		return;
	}
	$queried_id   = (int) get_queried_object_id();
	$dashboard_id = blueworx_store_page_id( 'dashboard' );
	// blueworx_store_redirect_to() makes this same decision; asking it first
	// only spares every other page on the site the context lookup below.
	if ( $dashboard_id <= 0 || $queried_id !== $dashboard_id ) {
		return;
	}
	$asked   = blueworx_store_requested_action();
	$context = blueworx_store_context();
	$target  = blueworx_store_redirect_to(
		$queried_id,
		$dashboard_id,
		blueworx_store_dashboard_url(),
		function_exists( 'is_user_logged_in' ) && is_user_logged_in(),
		blueworx_store_requested_view(),
		(string) ( $context['login_url'] ?? '' ),
		$asked['model'],
		$asked['action'],
		blueworx_store_requested_record()
	);
	if ( '' === $target ) {
		return;
	}
	wp_safe_redirect( $target, 302 );
	exit;
}

/**
 * The dashboard itself: the nav, the panel that was asked for, and every
 * other panel hidden behind it.
 *
 * Takes its two addresses as arguments rather than reaching for them, so the
 * screen can be rendered from the page's content filter and from a test
 * alike.
 *
 * Every panel is drawn, not just the one being read — see
 * blueworx_store_shell_page() for why. Each is offered to the
 * blueworx_store_panel filter after this plugin has drawn it and before the
 * empty state is decided, so a plugin can fill a panel this plugin had
 * nothing for, and the empty state is drawn once, for whatever is still
 * empty after everyone has had their say.
 *
 * @param string $base The dashboard's own address — every view link is built on it.
 * @param string $home The site's front page, for the way back out.
 * @return string '' when no view can be drawn at all, which callers treat as "render nothing".
 */
function blueworx_store_dashboard_screen( $base, $home ) {
	$base  = (string) $base;
	$home  = (string) $home;
	$views = blueworx_store_views();
	if ( array() === $views ) {
		return '';
	}
	$current = blueworx_store_resolve_view( blueworx_store_requested_view(), $views );
	$context = blueworx_store_context();

	$panels = array();
	foreach ( $views as $view ) {
		$key  = (string) $view['key'];
		$body = BLUEWORX_STORE_DEFAULT_VIEW === $key
			? blueworx_store_overview( $views, $home, $base )
			: blueworx_store_view_body( $view );
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters one dashboard panel's rendered body.
			 *
			 * @param string $html    The panel as this plugin drew it; '' draws the empty state.
			 * @param string $key     The view's key.
			 * @param array  $context From blueworx_store_context().
			 */
			$body = (string) apply_filters( 'blueworx_store_panel', $body, $key, $context );
		}
		if ( '' === trim( $body ) ) {
			$body = blueworx_store_not_set_up( $home );
		}
		$panels[ $key ] = $body;
	}

	// An address asking for something to be done — update these details, add
	// this card, cancel this plan — takes over the panel it belongs to. The
	// screen it replaces is the read-only one the member pressed the link on,
	// so what they came from is what they go back to.
	$action = blueworx_store_action_panel();
	if ( null !== $action && isset( $panels[ $action['view'] ] ) ) {
		$panels[ $action['view'] ] = $action['body'];
		$current                   = $action['view'];
	}

	return blueworx_store_shell_page(
		array(
			'views'        => $views,
			'current'      => $current,
			'panels'       => $panels,
			'home_url'     => $home,
			'site_name'    => (string) ( $context['site_name'] ?? '' ),
			'logo_url'     => (string) ( $context['logo_url'] ?? '' ),
			'base'         => $base,
			'logout_url'   => (string) ( $context['logout_url'] ?? '' ),
			'member_name'  => (string) ( $context['member_name'] ?? '' ),
			'member_email' => (string) ( $context['member_email'] ?? '' ),
		)
	);
}

/**
 * One view's contents, or '' when its plugin has nothing to say.
 *
 * A shortcode view is handed the whole panel — a plugin that brings its own
 * tabs does not belong inside a card of ours. Blocks each get a card.
 *
 * '' rather than the empty state, so the blueworx_store_panel filter can
 * fill the panel; blueworx_store_dashboard_screen() draws the empty state
 * for whatever is still empty afterwards.
 *
 * @param array<string,mixed> $view One view, from blueworx_store_views().
 * @return string
 */
function blueworx_store_view_body( $view ) {
	$shortcode = trim( (string) ( $view['shortcode'] ?? '' ) );
	if ( '' !== $shortcode ) {
		return blueworx_store_slot_shortcode( $shortcode );
	}
	$out    = '';
	$blocks = isset( $view['blocks'] ) && is_array( $view['blocks'] ) ? $view['blocks'] : array();
	foreach ( $blocks as $block ) {
		$panel = blueworx_store_slot_block( (string) $block );
		if ( '' !== $panel ) {
			$out .= blueworx_store_shell_card( '', $panel );
		}
	}
	return $out;
}

/**
 * The overview: the way into everything else, as one card per view.
 *
 * The design draws next sessions, recent orders and an outstanding-invoice
 * notice here. Composing those means reading other plugins' records and
 * re-rendering them, which the plan rules out — so this is links, and the
 * records stay where the plugins draw them. A plugin with something to say
 * above the links says it through the blueworx_store_panel filter.
 *
 * '' when there is no other view to link to, so the filter can fill it and
 * the empty state comes after — see blueworx_store_view_body().
 *
 * @param array<int,array<string,mixed>> $views    From blueworx_store_views().
 * @param string                         $home_url The site's front page. Not drawn here: the
 *                                                 empty state that used it is the caller's now.
 * @param string                         $base     The dashboard's own address, for the links.
 * @return string
 */
function blueworx_store_overview( $views, $home_url, $base = '' ) {
	$links = '';
	foreach ( (array) $views as $view ) {
		$key = (string) ( $view['key'] ?? '' );
		if ( BLUEWORX_STORE_DEFAULT_VIEW === $key ) {
			continue; // A link to the page you are on is a dead control.
		}
		$links .= '<a class="bw-card blueworx-store__quick" href="' . blueworx_store_e( blueworx_store_view_url( $key, (string) $base ) ) . '">'
			. '<span class="blueworx-store__quick-icon">' . blueworx_store_shell_icon( (string) ( $view['icon'] ?? '' ) ) . '</span>'
			. '<span class="blueworx-store__quick-title">' . blueworx_store_e( (string) ( $view['label'] ?? '' ) ) . '</span>'
			. '<span class="blueworx-store__quick-lede">' . blueworx_store_e( (string) ( $view['lede'] ?? '' ) ) . '</span>'
			. '</a>';
	}
	return '' === $links ? '' : '<div class="blueworx-store__quicks">' . $links . '</div>';
}

/**
 * What a member sees where a panel would be if the site has not set that
 * part up. Never a blank frame: it says so plainly and offers the way back.
 *
 * @param string $home_url The site's front page.
 * @return string
 */
function blueworx_store_not_set_up( $home_url ) {
	return blueworx_store_shell_card(
		'',
		blueworx_store_shell_empty_state(
			'Nothing here yet',
			'The site has not set this part up. Nothing is missing from your account.',
			(string) $home_url,
			'Back to the site'
		)
	);
}

/**
 * The screen an action address asks for, and which panel it belongs under.
 *
 * Rendered by SureCart's own wrapper block, which is the piece that reads
 * `model` and `action` and hands the request to the right controller. So the
 * form, the save and the permission check are all SureCart's — the dashboard
 * supplies the frame around them and nothing else, the same division as
 * every read-only panel here.
 *
 * Null for an ordinary address, and for an action whose model has no panel
 * of ours (downloads, licences) or whose block will not render — a member
 * following a stale link sees their normal dashboard, not a blank frame.
 *
 * @return array{view:string,body:string}|null
 */
function blueworx_store_action_panel() {
	$asked = blueworx_store_requested_action();
	if ( ! blueworx_store_is_action( $asked['model'], $asked['action'], blueworx_store_action_check() ) ) {
		return null;
	}
	$view = blueworx_store_action_view( $asked['model'] );
	if ( '' === $view ) {
		return null;
	}
	$body = blueworx_store_slot_block( blueworx_store_action_block() );
	if ( '' === $body ) {
		return null;
	}
	return array(
		'view' => $view,
		'body' => blueworx_store_shell_card( '', $body ),
	);
}

/**
 * The view named in the address, unfiltered — blueworx_store_resolve_view()
 * decides what it means.
 *
 * @return string
 */
function blueworx_store_requested_view() {
	// Reading which panel to show, not acting; sanitized on the next line, once it is known to be a string.
	$raw = isset( $_GET[ BLUEWORX_STORE_VIEW_ARG ] ) ? wp_unslash( $_GET[ BLUEWORX_STORE_VIEW_ARG ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	return is_string( $raw ) ? sanitize_text_field( $raw ) : '';
}

/**
 * Which record an action address names, '' for none.
 *
 * @return string
 */
function blueworx_store_requested_record() {
	// Carried across a redirect; the shop's own controller checks who may act on it. Sanitized on the next line, once it is known to be a string.
	$raw = isset( $_GET['id'] ) ? wp_unslash( $_GET['id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	return is_string( $raw ) ? sanitize_text_field( $raw ) : '';
}

/**
 * The dashboard page's content: the whole screen in place of whatever
 * SureCart wrote there.
 *
 * Left alone when another plugin has claimed the address — the route has
 * already redirected by now, so this is belt and braces — and when no screen
 * can be drawn at all.
 *
 * @param string $content The page's own content.
 * @param array  $context From blueworx_store_context().
 * @return string
 */
function blueworx_store_dashboard_content( $content, $context ) {
	$content = (string) $content;
	if ( '' !== blueworx_store_dashboard_url() ) {
		return $content;
	}
	$base = '';
	if ( function_exists( 'get_permalink' ) && function_exists( 'get_the_ID' ) ) {
		$permalink = get_permalink( get_the_ID() );
		$base      = is_string( $permalink ) ? $permalink : '';
	}
	$screen = blueworx_store_dashboard_screen( $base, (string) ( $context['home_url'] ?? '' ) );
	return '' === $screen ? $content : $screen;
}

if ( function_exists( 'add_action' ) && function_exists( 'blueworx_feature_enabled' ) && blueworx_feature_enabled( 'store_pages' ) ) {
	// The real block and shortcode renderers, installed once WordPress has
	// registered them.
	add_action( 'init', 'blueworx_store_slot_install' );
	add_action( 'template_redirect', 'blueworx_store_route', 5 );
}
