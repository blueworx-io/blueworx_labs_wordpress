<?php
/**
 * Checkout and order confirmation, in the store pages' look.
 *
 * The same frame as the customer dashboard, minus the nav: someone
 * mid-purchase should not be offered six places to wander off to. The page's
 * own content is passed through untouched — the shop renders the shop, and
 * this plugin draws the frame around it.
 *
 * No check on whether anyone is signed in, unlike the dashboard: a site sells
 * to guests, and someone paying without an account is an ordinary sale rather
 * than a mistake. Nothing here needs to know who they are — the page's own
 * content is the shop's, and it decides.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** After SureCart has expanded its own blocks into the content. */
define( 'BLUEWORX_STORE_CONTENT_PRIORITY', 30 );

/**
 * Which template a page should be served with. Pure.
 *
 * @param string $page_key Empty for any page this plugin does not dress.
 * @param string $chosen   Whatever WordPress had chosen.
 * @param string $ours     This plugin's store template.
 * @return string
 */
function blueworx_store_template_for( $page_key, $chosen, $ours ) {
	return '' !== (string) $page_key ? (string) $ours : (string) $chosen;
}

/**
 * Give the store pages a document of their own.
 *
 * Left to the theme, they render inside its page template, which draws its
 * own header above the frame and its own footer below it — so the checkout
 * carried the site's footer, several hundred pixels of it, underneath its
 * own. The approved design is a self-contained checkout, and a page cannot
 * be self-contained while something else owns the document.
 *
 * The frame itself is untouched: template.php runs WordPress's ordinary
 * loop, blueworx_store_dress_content() is still a the_content filter, and it
 * still fires exactly where it did.
 *
 * @param string $template The template WordPress chose.
 * @return string
 */
function blueworx_store_serve_template( $template ) {
	$template = (string) $template;
	if ( ! defined( 'BLUEWORX_LABS_PATH' ) ) {
		return $template;
	}
	return blueworx_store_template_for(
		blueworx_store_queried_page_key(),
		$template,
		BLUEWORX_LABS_PATH . 'includes/store/template.php'
	);
}

/**
 * Blanks the theme's own core/post-title block on the pages this plugin
 * dresses, so the frame's own heading is the only one on the page.
 *
 * A block theme draws its own post-title block above whatever the_content
 * returns, which put two h1s on the checkout — the theme's title, then the
 * frame's "Checkout" right underneath it — on the one page where a buyer is
 * mid-payment. render_block sees each block before it lands on the page, so
 * the theme's title is dropped here rather than fought after the fact.
 *
 * @param string              $block_content The block's rendered markup.
 * @param array<string,mixed> $block         The parsed block.
 * @param mixed               $instance      The WP_Block instance, when there is one.
 * @return string
 */
function blueworx_store_strip_post_title( $block_content, $block, $instance = null ) {
	$block_content = (string) $block_content;
	// render_block fires for every block on every page of the site, and
	// core/post-title appears at most once or twice on any of them. Bail on
	// the block name alone — an array lookup — before blueworx_store_page_key()
	// below, which reads options, ever runs.
	if ( ! is_array( $block ) || 'core/post-title' !== ( $block['blockName'] ?? '' ) ) {
		return $block_content;
	}
	$post_id = 0;
	if ( is_object( $instance ) && isset( $instance->context['postId'] ) ) {
		$post_id = (int) $instance->context['postId'];
	} elseif ( function_exists( 'get_the_ID' ) ) {
		$post_id = (int) get_the_ID();
	}
	return '' === blueworx_store_page_key( $post_id ) ? $block_content : '';
}

/**
 * The frame around the page's own content, on the pages this plugin dresses.
 *
 * Guarded against re-entering itself: the page's own content is the shop's
 * checkout, and a block or shortcode inside it is free to apply the_content
 * itself. On the same post that would come straight back in here and recurse
 * until the request ran out of memory — a white screen where someone is
 * trying to pay. The shop does not do it today; the guard costs a boolean
 * and makes it impossible.
 *
 * @param string $content The page's own content.
 * @return string
 */
function blueworx_store_dress_content( $content ) {
	static $rendering = false;
	$content          = (string) $content;
	if ( $rendering ) {
		return $content;
	}
	if ( ! function_exists( 'is_singular' ) || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$key = blueworx_store_page_key( (int) get_the_ID() );
	if ( '' === $key ) {
		return $content;
	}
	$rendering = true;
	try {
		$context = blueworx_store_context();
		if ( 'checkout' === $key ) {
			return blueworx_store_shell_checkout(
				array(
					'site_name'  => $context['site_name'],
					'logo_url'   => $context['logo_url'],
					'home_url'   => $context['home_url'],
					'home_label' => $context['home_label'],
					'body'       => $content,
					// Nothing in the plugin stores a site's registration number,
					// and adding a settings field for it is outside this work —
					// left blank on purpose, not an oversight.
					'footnote'   => '',
					'links'      => blueworx_store_checkout_links(),
				)
			);
		}
		if ( 'order-confirmation' === $key ) {
			return blueworx_store_shell_bare(
				'Thank you',
				'Your order is confirmed. A receipt is on its way by email.',
				blueworx_store_shell_card( '', $content ),
				$context['home_url'],
				$context['site_name']
			);
		}
		// 'dashboard': the whole screen, from dashboard.php.
		return blueworx_store_dashboard_content( $content, $context );
	} finally {
		$rendering = false;
	}
}

/**
 * The pages a buyer is entitled to read before paying, and their addresses.
 *
 * WordPress knows one: the privacy policy page, when the site has set one
 * and it is published. Anything else — terms, rules, a contact page — is
 * the site's to add through the blueworx_store_checkout_links filter. An
 * entry with no label or no address is dropped, so a filter that hands back
 * something half-built never draws a blank link.
 *
 * @return array<int,array{label:string,href:string}>
 */
function blueworx_store_checkout_links() {
	$links = array();
	if ( function_exists( 'get_option' ) && function_exists( 'get_post_status' ) && function_exists( 'get_permalink' ) ) {
		$privacy_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );
		if ( $privacy_id > 0 && 'publish' === get_post_status( $privacy_id ) ) {
			$href = get_permalink( $privacy_id );
			if ( is_string( $href ) && '' !== $href ) {
				$links[] = array(
					'label' => 'Privacy policy',
					'href'  => $href,
				);
			}
		}
	}
	if ( function_exists( 'apply_filters' ) ) {
		/**
		 * Filters the links in the checkout's footer.
		 *
		 * @param array<int,array{label:string,href:string}> $links   The links so far.
		 * @param array                                       $context From blueworx_store_context().
		 */
		$links = apply_filters( 'blueworx_store_checkout_links', $links, blueworx_store_context() );
	}
	$out = array();
	foreach ( (array) $links as $link ) {
		if ( ! is_array( $link ) ) {
			continue;
		}
		$label = trim( (string) ( $link['label'] ?? '' ) );
		$href  = trim( (string) ( $link['href'] ?? '' ) );
		if ( '' === $label || '' === $href ) {
			continue;
		}
		$out[] = array(
			'label' => $label,
			'href'  => $href,
		);
	}
	return $out;
}

if ( function_exists( 'add_filter' ) && function_exists( 'blueworx_feature_enabled' ) && blueworx_feature_enabled( 'store_pages' ) ) {
	add_filter( 'the_content', 'blueworx_store_dress_content', BLUEWORX_STORE_CONTENT_PRIORITY );
	add_filter( 'render_block', 'blueworx_store_strip_post_title', 10, 3 );
	// These pages serve their own document — see blueworx_store_serve_template().
	add_filter( 'template_include', 'blueworx_store_serve_template' );
}
