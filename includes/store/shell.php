<?php
/**
 * The member area's markup: page head, left nav, cards.
 *
 * Pure and escaped, in the same way Sections is — every rule about what this
 * page looks like is decided here and testable without WordPress or a shop.
 *
 * The classes are the BlueWorx admin design system's, not a shop's own look.
 * The two never meet: nothing else styles this markup, and every rule in
 * bw.css is scoped to .bw-admin, which only this markup carries. Our own
 * classes use the `blueworx-` prefix rather than `bw-`, because `bw-` is the
 * design system's own namespace and these are not design-system patterns.
 *
 * The nav is links rather than buttons because each view is its own address —
 * openable in a new tab, bookmarkable, and working with no JavaScript at all.
 *
 * Icons are the design system's own element — an <i> carrying a data-lucide
 * name, inlined by its icons module (Task 6 enqueues it on these pages) —
 * rather than an icon font, a script of our own, or inline SVG.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The query argument each view is addressed by. */
define( 'BLUEWORX_STORE_VIEW_ARG', 'view' );

/**
 * Escape, the same way Sections does — decoding first so escaping twice
 * changes nothing. Some of what arrives here is built by WordPress's own
 * helpers, which hand back a URL with its ampersands already written as
 * entities; escaping that again would turn &amp; into &amp;amp; and quietly
 * rename the query argument behind it.
 *
 * @param string $v Value to escape.
 * @return string
 */
function blueworx_store_e( $v ) {
	return htmlspecialchars( html_entity_decode( (string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), ENT_QUOTES, 'UTF-8' );
}

/**
 * The address of one view.
 *
 * Built on the page's own address, handed in by whoever knows it, because a
 * bare '?view=orders' replaces the whole query rather than adding to it: on a
 * site with permalinks set to Plain the dashboard lives at '/?page_id=42' and
 * that link would land the member on the front page. With no address to
 * build on it falls back to the bare form, which is right wherever the page
 * carries no query of its own.
 *
 * @param string $key  View key.
 * @param string $base Base URL to build on.
 * @return string
 */
function blueworx_store_view_url( $key, $base = '' ) {
	$arg = BLUEWORX_STORE_VIEW_ARG . '=' . rawurlencode( $key );
	if ( '' === trim( $base ) ) {
		return '?' . $arg;
	}
	return $base . ( false !== strpos( $base, '?' ) ? '&' : '?' ) . $arg;
}

/**
 * The whole member area: the site and the member down the left, the view
 * being read to the right of them.
 *
 * Takes one array rather than a row of positional arguments — the design
 * needs the site's logo, the member's name and every panel's markup, and
 * eleven positional strings is a signature nobody can call correctly.
 *
 * Every panel is rendered, and every one but the current carries `hidden`.
 * The panels are other plugins' web components and shortcodes: they come
 * alive when the page loads, so a panel fetched later would render as an
 * empty box. Showing and hiding what is already there costs one attribute.
 *
 * @param array{
 *   views:array<int,array<string,mixed>>,
 *   current:string,
 *   panels:array<string,string>,
 *   home_url?:string, site_name?:string, logo_url?:string, base?:string,
 *   logout_url?:string, member_name?:string, member_email?:string
 * } $args Page arguments.
 * @return string
 */
function blueworx_store_shell_page( $args ) {
	$views   = isset( $args['views'] ) && is_array( $args['views'] ) ? $args['views'] : array();
	$current = (string) ( $args['current'] ?? '' );
	$panels  = isset( $args['panels'] ) && is_array( $args['panels'] ) ? $args['panels'] : array();
	$base    = (string) ( $args['base'] ?? '' );
	$home    = trim( (string) ( $args['home_url'] ?? '' ) );

	return '<div class="bw-admin bw-page blueworx-store" data-blueworx-store data-view-initial="' . blueworx_store_e( $current ) . '">'
		. '<div class="blueworx-store__shell">'
		. blueworx_store_shell_sidebar( $views, $current, $base, $args )
		. '<div class="blueworx-store__main">'
		. blueworx_store_shell_head( $views, $current, $args )
		. '<main class="bw-panels" id="blueworx-store-view">'
		. blueworx_store_shell_panels( $views, $current, $panels )
		. '</main>'
		. '</div>'
		. blueworx_store_shell_tabbar( $views, $current, $base, $home )
		. '</div></div>';
}

/**
 * Every panel, with all but one hidden. See blueworx_store_shell_page() for
 * why they are all drawn. A view with nothing rendered for it is skipped
 * rather than drawn empty.
 *
 * @param array<int,array<string,mixed>> $views   Views in nav order.
 * @param string                          $current The current view's key.
 * @param array<string,string>            $panels  Rendered panel markup by key.
 * @return string
 */
function blueworx_store_shell_panels( $views, $current, $panels ) {
	$out = '';
	foreach ( $views as $view ) {
		$key  = (string) $view['key'];
		$body = (string) ( $panels[ $key ] ?? '' );
		if ( '' === $body ) {
			continue;
		}
		// Named from both the sidebar's link and the tab bar's — whichever
		// breakpoint is live, exactly one of the two is display:none, and a
		// hidden node referenced by aria-labelledby contributes no text, so
		// the visible one alone supplies the accessible name. Two ids, not
		// one shared between the links, so neither document has a duplicate.
		$out .= '<div class="blueworx-store__panel" data-view="' . blueworx_store_e( $key ) . '"'
			. ' role="tabpanel" aria-labelledby="blueworx-store-navtab-' . blueworx_store_e( $key )
			. ' blueworx-store-tab-' . blueworx_store_e( $key ) . '"'
			. ( $key === $current ? '' : ' hidden' ) . '>'
			. $body . '</div>';
	}
	return $out;
}

/**
 * The left column: who the site is, where a member can go, and who they are
 * signed in as. Full height, as the design draws it.
 *
 * @param array<int,array<string,mixed>> $views   Views in nav order.
 * @param string                          $current The current view's key.
 * @param string                          $base    Base URL to build view links on.
 * @param array<string,mixed>             $args    Page arguments.
 * @return string
 */
function blueworx_store_shell_sidebar( $views, $current, $base, $args ) {
	$site  = trim( (string) ( $args['site_name'] ?? '' ) );
	$logo  = trim( (string) ( $args['logo_url'] ?? '' ) );
	$home  = trim( (string) ( $args['home_url'] ?? '' ) );
	$name  = trim( (string) ( $args['member_name'] ?? '' ) );
	$email = trim( (string) ( $args['member_email'] ?? '' ) );

	// The current view's title and lede, and the way to sign out — both
	// needed a second time here because on a phone the page head
	// (blueworx_store_shell_head()) is hidden and this row carries its job
	// instead. See the phone media query in bw.css for which pair is shown
	// at which width.
	$view   = blueworx_store_shell_view( $views, $current );
	$vtitle = (string) ( $view['title'] ?? '' );
	$vlede  = (string) ( $view['lede'] ?? '' );
	$logout = trim( (string) ( $args['logout_url'] ?? '' ) );

	$out = '<aside class="blueworx-store__side">';

	// The brand block. A site with no logo set gets its initials in the same
	// box, so the corner is never empty.
	$out .= '<div class="blueworx-store__brand">';
	if ( '' !== $logo ) {
		$out .= '<span class="blueworx-store__brandmark"><img src="' . blueworx_store_e( $logo ) . '" alt=""></span>';
	} elseif ( '' !== $site ) {
		$out .= '<span class="blueworx-store__brandmark">' . blueworx_store_e( blueworx_store_initials( $site ) ) . '</span>';
	}
	if ( '' !== $site ) {
		$out .= '<span class="blueworx-store__brandtext">'
			. '<span class="blueworx-store__brandname">' . blueworx_store_e( $site ) . '</span>'
			. '<span class="blueworx-store__brandsub">Your account</span>'
			. '</span>';
	}
	// The phone-only pair, re-targeted the same way as the pair in
	// blueworx_store_shell_head() — see that function's docblock. Its own
	// classes, not brandname/brandsub, so the CSS can show one pair and hide
	// the other.
	$out .= '<span class="blueworx-store__viewtext">'
		. '<span class="blueworx-store__viewtitle" data-member-title>' . blueworx_store_e( $vtitle ) . '</span>';
	if ( '' !== trim( $vlede ) ) {
		$out .= '<span class="blueworx-store__viewlede" data-member-lede>' . blueworx_store_e( $vlede ) . '</span>';
	} else {
		$out .= '<span class="blueworx-store__viewlede" data-member-lede hidden></span>';
	}
	$out .= '</span>';
	// The phone-only sign out. Desktop keeps the one in
	// blueworx_store_shell_head(); drawn only when there is an address to
	// sign out to — a dead link is worse than no link, exactly as that
	// function already treats it.
	if ( '' !== $logout ) {
		$out .= '<a class="blueworx-store__brandsignout bw-btn bw-btn--secondary bw-btn--sm" href="' . blueworx_store_e( $logout ) . '">Sign out</a>';
	}
	$out .= '</div>';

	$out .= blueworx_store_shell_nav( $views, $current, $base );

	if ( '' !== $home ) {
		// Drawn as a nav item, because that is what it is — one more place
		// to go from this column. Its own class stays on it so the CSS can
		// still push it to the foot of the column and hide it on a phone,
		// where the tab bar carries the way home instead.
		$out .= '<a class="bw-secnav__item blueworx-store__back" href="' . blueworx_store_e( $home ) . '">'
			. '<span class="blueworx-store__navlabel">'
			. blueworx_store_shell_icon( 'arrow-left' ) . 'Back home</span></a>';
	}

	// Who is signed in. The design shows a membership number here; nothing
	// holds one, so the address they signed in with does the job of telling
	// a member which account they are looking at.
	if ( '' !== $name ) {
		$out .= '<div class="blueworx-store__person"><div class="bw-person">'
			. '<span class="bw-avatar blueworx-store__avatar">' . blueworx_store_e( blueworx_store_initials( $name ) ) . '</span>'
			. '<span class="blueworx-store__persontext">'
			. '<span class="bw-person__name">' . blueworx_store_e( $name ) . '</span>';
		if ( '' !== $email ) {
			$out .= '<span class="bw-person__sub">' . blueworx_store_e( $email ) . '</span>';
		}
		$out .= '</span></div></div>';
	}

	return $out . '</aside>';
}

/**
 * Up to two letters for an avatar: the first letter of the first word and of
 * the last. Pure, and safe on a single word, on extra whitespace, and on
 * nothing at all.
 *
 * @param string $name Full name.
 * @return string
 */
function blueworx_store_initials( $name ) {
	$words = preg_split( '/\s+/', trim( (string) $name ), -1, PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $words ) || array() === $words ) {
		return '';
	}
	$first = mb_substr( (string) $words[0], 0, 1 );
	$last  = count( $words ) > 1 ? mb_substr( (string) $words[ count( $words ) - 1 ], 0, 1 ) : '';
	return mb_strtoupper( $first . $last );
}

/**
 * The top bar: what this view is, and the two things a member does from
 * anywhere — leave, or sign out.
 *
 * @param array<int,array<string,mixed>> $views   Views in nav order.
 * @param string                          $current The current view's key.
 * @param array<string,mixed>             $args    Page arguments.
 * @return string
 */
function blueworx_store_shell_head( $views, $current, $args ) {
	$view   = blueworx_store_shell_view( $views, $current );
	$title  = (string) ( $view['title'] ?? '' );
	$lede   = (string) ( $view['lede'] ?? '' );
	$logout = trim( (string) ( $args['logout_url'] ?? '' ) );

	$out = '<header class="bw-pagehead blueworx-store__head"><div class="bw-pagehead__titles">'
		. '<h1 class="bw-pagehead__h1" data-member-title>' . blueworx_store_e( $title ) . '</h1>';
	if ( '' !== trim( $lede ) ) {
		$out .= '<p class="bw-pagehead__lede" data-member-lede>' . blueworx_store_e( $lede ) . '</p>';
	} else {
		$out .= '<p class="bw-pagehead__lede" data-member-lede hidden></p>';
	}
	$out .= '</div><div class="bw-pagehead__actions">';
	// Nothing is drawn when there is no address to sign out to — a dead link
	// is worse than no link.
	if ( '' !== $logout ) {
		$out .= '<a class="bw-btn bw-btn--secondary bw-btn--sm" href="' . blueworx_store_e( $logout ) . '">Sign out</a>';
	}
	return $out . '</div></header>';
}

/**
 * One view's entry, or an empty array.
 *
 * The lookup itself is blueworx_store_find_view()'s job, already shared with
 * the rest of the plugin; this only adapts its null for a caller that wants
 * an array to read optional keys off of with no isset() of its own.
 *
 * @param array<int,array<string,mixed>> $views Views to search.
 * @param string                          $key   View key.
 * @return array<string,mixed>
 */
function blueworx_store_shell_view( $views, $key ) {
	$view = blueworx_store_find_view( $key, $views );
	return null === $view ? array() : $view;
}

/**
 * The same look with no nav — checkout and order confirmation.
 *
 * A member on the checkout page is mid-purchase and should not be offered
 * six places to wander off to.
 *
 * @param string $title    Page title.
 * @param string $lede     Page lede.
 * @param string $body     Page body markup.
 * @param string $home_url Address to go home to.
 * @param string $site_name Site name.
 * @return string
 */
function blueworx_store_shell_bare( $title, $lede, $body, $home_url, $site_name ) {
	$head = '<header class="bw-pagehead"><div class="bw-pagehead__titles">';
	if ( '' !== trim( $site_name ) ) {
		$head .= '<p class="bw-pagehead__eyebrow">' . blueworx_store_e( $site_name ) . '</p>';
	}
	$head .= '<h1 class="bw-pagehead__h1">' . blueworx_store_e( $title ) . '</h1>';
	if ( '' !== trim( $lede ) ) {
		$head .= '<p class="bw-pagehead__lede">' . blueworx_store_e( $lede ) . '</p>';
	}
	$head .= '</div><div class="bw-pagehead__actions">'
		. '<a class="bw-btn bw-btn--secondary" href="' . blueworx_store_e( $home_url ) . '">'
		. blueworx_store_shell_icon( 'arrow-left' ) . 'Back to the site</a>'
		. '</div></header>';
	return '<div class="bw-admin bw-page blueworx-store">' . $head
		. '<div class="bw-page__body"><main class="bw-panels">' . $body . '</main></div></div>';
}

/**
 * The checkout page's frame: a header, the shop's own form, and a footer.
 *
 * Chrome only. The two columns a buyer sees are not drawn here — they are
 * SureCart's own column blocks, inside the form. The alternative would be
 * cutting the rendered content in two, and the_content hands it over as a
 * single string with no seam to cut on. See the design doc.
 *
 * No nav, for the same reason blueworx_store_shell_bare() has none: someone
 * mid-purchase should not be offered six places to wander off to. The
 * footer's links are the exception, because a buyer is entitled to read the
 * terms before paying.
 *
 * Pure: everything drawn arrives in $args.
 *
 * @param array{site_name?:string, logo_url?:string, home_url?:string,
 *              home_label?:string, body?:string, footnote?:string,
 *              links?:array<int,array{label:string,href:string}>} $args Checkout arguments.
 * @return string
 */
function blueworx_store_shell_checkout( $args ) {
	$site     = trim( (string) ( $args['site_name'] ?? '' ) );
	$logo     = trim( (string) ( $args['logo_url'] ?? '' ) );
	$home     = trim( (string) ( $args['home_url'] ?? '' ) );
	$label    = trim( (string) ( $args['home_label'] ?? '' ) );
	$body     = (string) ( $args['body'] ?? '' );
	$footnote = trim( (string) ( $args['footnote'] ?? '' ) );
	$links    = is_array( $args['links'] ?? null ) ? $args['links'] : array();

	$out = '<div class="bw-admin blueworx-checkout">'
		. blueworx_store_shell_checkout_head( $site, $logo )
		. '<main class="blueworx-checkout__body">' . $body . '</main>'
		. blueworx_store_shell_checkout_foot( $home, $label, $links, $footnote )
		. '</div>';
	return $out;
}

/**
 * The checkout header: the site's crest and name, and the one reassurance
 * that matters on a payment page — that the site never sees the card.
 *
 * @param string $site Site name.
 * @param string $logo Logo URL.
 * @return string
 */
function blueworx_store_shell_checkout_head( $site, $logo ) {
	$crest = '' !== $logo
		? '<img class="blueworx-checkout__crest" src="' . blueworx_store_e( $logo ) . '" alt="" width="34" height="34">'
		: '<span class="blueworx-checkout__crest" aria-hidden="true">' . blueworx_store_e( blueworx_store_initials( $site ) ) . '</span>';

	$out = '<header class="blueworx-checkout__head">'
		. '<div class="blueworx-checkout__brand">' . $crest
		. '<span class="blueworx-checkout__titles">';
	if ( '' !== $site ) {
		$out .= '<span class="blueworx-checkout__club">' . blueworx_store_e( $site ) . '</span>';
	}
	$out .= '<h1 class="blueworx-checkout__h1">Checkout</h1>'
		. '</span></div>'
		. '<p class="blueworx-checkout__secure">' . blueworx_store_shell_icon( 'lock' )
		. 'Your card is handled by Stripe. The site never sees it.</p>'
		. '</header>';
	return $out;
}

/**
 * The checkout footer: the way back, the site's legal pages, and whatever
 * the site has to say about itself in law.
 *
 * Every part is drawn only when there is something to draw. An empty nav
 * announces a navigation landmark holding nothing, which is worse for a
 * screen reader than no nav at all.
 *
 * @param string                                        $home     Address to go home to.
 * @param string                                        $label    Home link label.
 * @param array<int,array{label:string,href:string}>    $links    Footer links.
 * @param string                                        $footnote Footer footnote.
 * @return string
 */
function blueworx_store_shell_checkout_foot( $home, $label, $links, $footnote ) {
	$out = '<footer class="blueworx-checkout__foot">';
	if ( '' !== $home ) {
		$out .= '<a class="blueworx-checkout__back" href="' . blueworx_store_e( $home ) . '">'
			. blueworx_store_shell_icon( 'arrow-left' )
			. blueworx_store_e( '' !== $label ? $label : 'Back to the site' )
			. '</a>';
	}
	// Built first and only wrapped if anything survived validation — every
	// entry can be malformed and dropped, and a <nav> left standing around
	// that is empty announces a navigation landmark holding nothing.
	$link_items = '';
	foreach ( $links as $link ) {
		$href = trim( (string) ( $link['href'] ?? '' ) );
		$text = trim( (string) ( $link['label'] ?? '' ) );
		if ( '' === $href || '' === $text ) {
			continue;
		}
		$link_items .= '<a href="' . blueworx_store_e( $href ) . '">' . blueworx_store_e( $text ) . '</a>';
	}
	if ( '' !== $link_items ) {
		$out .= '<nav class="blueworx-checkout__links" aria-label="Terms and policies">' . $link_items . '</nav>';
	}
	if ( '' !== $footnote ) {
		$out .= '<p class="blueworx-checkout__footnote">' . blueworx_store_e( $footnote ) . '</p>';
	}
	return $out . '</footer>';
}

/**
 * The side nav. Links, not buttons: each view is its own address, openable
 * in a new tab and working with no JavaScript at all. A script upgrades
 * these in place.
 *
 * @param array<int,array<string,mixed>> $views   Views in nav order.
 * @param string                          $current The current view's key.
 * @param string                          $base    Base URL to build view links on.
 * @return string
 */
function blueworx_store_shell_nav( $views, $current, $base = '' ) {
	$out = '<nav class="bw-secnav blueworx-store__nav" aria-label="Your account">';
	foreach ( blueworx_store_views_side( $views ) as $view ) {
		$key    = (string) $view['key'];
		$active = $key === $current;
		$out   .= '<a class="bw-secnav__item' . ( $active ? ' is-active' : '' ) . '"'
			. ' id="blueworx-store-navtab-' . blueworx_store_e( $key ) . '"'
			. ' data-view-link="' . blueworx_store_e( $key ) . '"'
			. ' data-view-title="' . blueworx_store_e( (string) ( $view['title'] ?? '' ) ) . '"'
			. ' data-view-lede="' . blueworx_store_e( (string) ( $view['lede'] ?? '' ) ) . '"'
			. ' href="' . blueworx_store_e( blueworx_store_view_url( $key, $base ) ) . '"'
			. ( $active ? ' aria-current="page"' : '' ) . '>'
			. '<span class="blueworx-store__navlabel">'
			. blueworx_store_shell_icon( (string) $view['icon'] )
			. blueworx_store_e( (string) $view['label'] )
			. '</span></a>';
	}
	return $out . '</nav>';
}

/**
 * The bottom tab bar, which is what the sidebar becomes on a phone.
 *
 * A curated list — blueworx_store_views_bar() — not "whichever views fit",
 * so there is no slicing here. The last item is always the way out: a plain
 * link to the site, carrying no data-view-link so the switching script
 * leaves it alone and it navigates for real.
 *
 * @param array<int,array<string,mixed>> $views   Views in nav order.
 * @param string                          $current The current view's key.
 * @param string                          $base    Base URL to build view links on.
 * @param string                          $home    Address to go home to.
 * @return string
 */
function blueworx_store_shell_tabbar( $views, $current, $base = '', $home = '' ) {
	$shown = blueworx_store_views_bar( $views );
	// Drawn for a single view too. A site with no plugin panels still gets
	// the bar, so the member area looks and behaves the same on every site
	// rather than growing a bar the day a plugin is installed.
	if ( array() === $shown && '' === $home ) {
		return '';
	}
	$out = '<nav class="blueworx-store__tabbar" aria-label="Your account">';
	foreach ( $shown as $view ) {
		$key    = (string) $view['key'];
		$active = $key === $current;
		$out   .= '<a class="blueworx-store__tab' . ( $active ? ' is-active' : '' ) . '"'
			. ' id="blueworx-store-tab-' . blueworx_store_e( $key ) . '"'
			. ' data-view-link="' . blueworx_store_e( $key ) . '"'
			. ' data-view-title="' . blueworx_store_e( (string) ( $view['title'] ?? '' ) ) . '"'
			. ' data-view-lede="' . blueworx_store_e( (string) ( $view['lede'] ?? '' ) ) . '"'
			. ' href="' . blueworx_store_e( blueworx_store_view_url( $key, $base ) ) . '"'
			. ( $active ? ' aria-current="page"' : '' ) . '>'
			. blueworx_store_shell_icon( (string) $view['icon'] )
			. '<span class="blueworx-store__tablabel">' . blueworx_store_e( (string) $view['label'] ) . '</span>'
			. '</a>';
	}
	if ( '' !== $home ) {
		// No data-view-link: this is a real exit from the member area, not a
		// panel to switch to, so the switching script must leave it alone.
		$out .= '<a class="blueworx-store__tab" href="' . blueworx_store_e( $home ) . '">'
			. blueworx_store_shell_icon( 'arrow-left' )
			. '<span class="blueworx-store__tablabel">Back home</span>'
			. '</a>';
	}
	return $out . '</nav>';
}

/**
 * One panel. A card with no title is a card with no head, not an empty one.
 *
 * @param string $title Card title.
 * @param string $body  Card body markup.
 * @return string
 */
function blueworx_store_shell_card( $title, $body ) {
	$out = '<section class="bw-card">';
	if ( '' !== trim( $title ) ) {
		$out .= '<div class="bw-card__head"><div class="bw-card__titles">'
			. '<h2 class="bw-card__title">' . blueworx_store_e( $title ) . '</h2>'
			. '</div></div>';
	}
	return $out . '<div class="bw-card__body">' . $body . '</div></section>';
}

/**
 * What a member sees where a panel would be if the site has not set that
 * part up. Never a blank frame: it says so plainly and offers the way back.
 *
 * @param string $title Empty state title.
 * @param string $text  Empty state text.
 * @param string $href  Action link address.
 * @param string $label Action link label.
 * @return string
 */
function blueworx_store_shell_empty_state( $title, $text, $href, $label ) {
	$out = '<div class="bw-empty">'
		. '<p class="bw-empty__title">' . blueworx_store_e( $title ) . '</p>'
		. '<p class="bw-empty__text">' . blueworx_store_e( $text ) . '</p>';
	if ( '' !== trim( $href ) && '' !== trim( $label ) ) {
		$out .= '<div class="bw-empty__actions">'
			. '<a class="bw-btn bw-btn--secondary" href="' . blueworx_store_e( $href ) . '">' . blueworx_store_e( $label ) . '</a>'
			. '</div>';
	}
	return $out . '</div>';
}

/**
 * One glyph, or '' for a name nothing draws — a missing icon must never be
 * a fatal or a broken image.
 *
 * The design system's own icon element: an <i> carrying a data-lucide name,
 * inlined by its icons module. No SVG of our own — that would be a second
 * icon system growing beside the one the design system already ships.
 *
 * @param string $name Icon name.
 * @return string
 */
function blueworx_store_shell_icon( $name ) {
	$name = trim( (string) $name );
	if ( '' === $name ) {
		return '';
	}
	return '<i class="bw-icon" data-lucide="' . blueworx_store_e( $name ) . '" aria-hidden="true"></i>';
}
