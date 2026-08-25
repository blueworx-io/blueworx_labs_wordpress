<?php
/**
 * The Guides admin screen.
 *
 * Tabs are query-string based rather than JavaScript. With no script the page
 * still works, each tab is a real URL a client can bookmark or be sent, and the
 * Playwright specs assert on navigation rather than on script having run.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds Guides to the sidebar as a top-level row.
 *
 * The design puts Guides in the Overview group beside Dashboard and BlueWorx
 * rather than nested under BlueWorx: it explains the whole site, not only this
 * plugin's screens, and other plugins register guides into it too.
 *
 * The page slug does not change, so admin.php?page=blueworx-guides and every
 * link or bookmark to it still resolve. What does change is the screen's hook
 * suffix, which WordPress derives from the parent — it is now
 * toplevel_page_blueworx-guides, and includes/admin-assets.php gates this
 * screen's stylesheet and script on that string.
 *
 * Registered at priority 11, after blueworx_register_settings_page(), and at
 * position 58.1 so it sits directly below BlueWorx (58) on a site running with
 * the admin theme switched off, where the group ordering filter never runs.
 *
 * @return void
 */
function blueworx_register_guides_page() {
	add_menu_page(
		esc_html__( 'Guides', 'blueworx-labs-wordpress' ),
		esc_html__( 'Guides', 'blueworx-labs-wordpress' ),
		'manage_options',
		'blueworx-guides',
		'blueworx_render_guides_page',
		'none',
		58.1
	);
}
add_action( 'admin_menu', 'blueworx_register_guides_page', 11 );

/**
 * Gets the tabs that actually have guides, in display order.
 *
 * A tab with nothing in it is not shown: with every feature switchable, an
 * empty Performance tab is a dead end rather than information.
 *
 * @param array $guides Normalized guides.
 * @return array Tab labels keyed by tab id.
 */
function blueworx_get_populated_guide_tabs( $guides, $product = '' ) {
	$tabs   = blueworx_get_all_guide_tabs();
	$counts = array();

	foreach ( $guides as $guide ) {
		if ( '' !== $product && $guide['product'] !== $product ) {
			continue;
		}

		$counts[ $guide['tab'] ] = true;
	}

	$populated = array();
	foreach ( $tabs as $id => $label ) {
		if ( isset( $counts[ $id ] ) ) {
			$populated[ $id ] = $label;
		}
	}

	// Guides whose declared tab does not exist are collected at the end rather
	// than dropped, so a plugin that forgets to register its tab still shows up.
	if ( isset( $counts[ BLUEWORX_GUIDES_FALLBACK_TAB ] ) ) {
		$populated[ BLUEWORX_GUIDES_FALLBACK_TAB ] = __( 'Other', 'blueworx-labs-wordpress' );
	}

	return $populated;
}

/**
 * Gets the products that actually have guides, in display order.
 *
 * @param array $guides Normalized guides.
 * @return array Product labels keyed by key.
 */
function blueworx_get_populated_guide_products( $guides ) {
	$products = blueworx_get_guide_products();
	$live     = array();

	foreach ( $guides as $guide ) {
		$live[ $guide['product'] ] = true;
	}

	$populated = array();

	foreach ( $products as $key => $label ) {
		if ( isset( $live[ $key ] ) ) {
			$populated[ $key ] = $label;
		}
	}

	return $populated;
}

/**
 * Resolves the product to show from the query string.
 *
 * @param array $products Populated products.
 * @return string Product key, always one that exists.
 */
function blueworx_current_guide_product( $products ) {
	if ( empty( $products ) ) {
		return '';
	}

	// Read-only navigation state, so no nonce: there is nothing to forge.
	$requested = isset( $_GET['product'] ) ? sanitize_key( wp_unslash( $_GET['product'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( isset( $products[ $requested ] ) ) {
		return $requested;
	}

	return (string) array_key_first( $products );
}

/**
 * Resolves the tab to show from the query string.
 *
 * @param array $tabs Populated tabs.
 * @return string Tab id, always one that exists.
 */
function blueworx_current_guide_tab( $tabs ) {
	if ( empty( $tabs ) ) {
		return '';
	}

	// Read-only navigation state, so no nonce: there is nothing to forge.
	$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( isset( $tabs[ $requested ] ) ) {
		return $requested;
	}

	return (string) array_key_first( $tabs );
}

/**
 * Renders the Guides page.
 *
 * @return void
 */
function blueworx_render_guides_page() {
	$guides   = blueworx_get_guides();
	$products = blueworx_get_populated_guide_products( $guides );
	$product  = blueworx_current_guide_product( $products );
	$tabs     = blueworx_get_populated_guide_tabs( $guides, $product );
	$active   = blueworx_current_guide_tab( $tabs );

	$header = blueworx_ds_page_header(
		array(
			'title'      => __( 'Guides', 'blueworx-labs-wordpress' ),
			'lede'       => __( 'Pick a section along the top, then a topic below it. BlueWorx topics follow what you have switched on — switch a function off and its guides go with it.', 'blueworx-labs-wordpress' ),
			'capability' => 'read',
		)
	);

	echo wp_kses( blueworx_ds_screen_open( $header ), blueworx_ds_allowed_html() );

	if ( empty( $tabs ) ) {
		echo wp_kses(
			'<div class="bw-page__body">'
				. blueworx_ds_empty_state(
					array(
						'icon'    => 'lightbulb',
						'title'   => __( 'Nothing is switched on yet', 'blueworx-labs-wordpress' ),
						'text'    => __( 'Guides appear here for whatever you have switched on. Turn something on and its guide comes with it.', 'blueworx-labs-wordpress' ),
						'actions' => blueworx_ds_button(
							array(
								'label'   => __( 'Go to Enhancements', 'blueworx-labs-wordpress' ),
								'variant' => 'primary',
								'href'    => admin_url( 'admin.php?page=blueworx-labs-wordpress' ),
							)
						),
					)
				)
				. '</div>',
			blueworx_ds_allowed_html()
		);

		echo wp_kses( blueworx_ds_screen_close(), blueworx_ds_allowed_html() );

		return;
	}

	// How many guides sit behind each tab, so a tab can say so before you open it.
	$counts = array();

	foreach ( $guides as $guide ) {
		if ( $guide['product'] !== $product ) {
			continue;
		}

		$tab            = isset( $tabs[ $guide['tab'] ] ) ? $guide['tab'] : BLUEWORX_GUIDES_FALLBACK_TAB;
		$counts[ $tab ] = isset( $counts[ $tab ] ) ? $counts[ $tab ] + 1 : 1;
	}

	// How many topics sit behind each product, for the row above the tabs.
	$product_counts = array();

	foreach ( $guides as $guide ) {
		$key                    = $guide['product'];
		$product_counts[ $key ] = isset( $product_counts[ $key ] ) ? $product_counts[ $key ] + 1 : 1;
	}

	// Anchors, for the same reason the topic tabs are: a real URL somebody can
	// be sent, and a screen that works with JavaScript off.
	$product_html = '';

	foreach ( $products as $key => $label ) {
		$product_url = add_query_arg(
			array(
				'page'    => 'blueworx-guides',
				'product' => $key,
			),
			admin_url( 'admin.php' )
		);

		$product_html .= sprintf(
			'<a class="bw-prodtab%1$s" href="%2$s" data-blueworx-guide-product="%3$s"%4$s>%5$s<span class="bw-prodtab__count">%6$s</span></a>',
			$key === $product ? ' is-active' : '',
			esc_url( $product_url ),
			esc_attr( $key ),
			$key === $product ? ' aria-current="page"' : '',
			esc_html( $label ),
			esc_html( (string) ( isset( $product_counts[ $key ] ) ? $product_counts[ $key ] : 0 ) )
		);
	}

	// Anchors, not the design system's buttons. Each tab is a real URL somebody
	// can be sent, and the screen has to work with JavaScript off — which a
	// button that swaps panels client-side would take away.
	$tab_html = '';

	foreach ( $tabs as $id => $label ) {
		$url = add_query_arg(
			array(
				'page'    => 'blueworx-guides',
				'product' => $product,
				'tab'     => $id,
			),
			admin_url( 'admin.php' )
		);

		$count = isset( $counts[ $id ] ) ? '<span class="bw-tab__count">' . esc_html( (string) $counts[ $id ] ) . '</span>' : '';

		$tab_html .= sprintf(
			'<a class="bw-tab%1$s" href="%2$s" data-blueworx-guide-tab="%3$s"%4$s>%5$s%6$s</a>',
			$id === $active ? ' is-active' : '',
			esc_url( $url ),
			esc_attr( $id ),
			$id === $active ? ' aria-current="page"' : '',
			esc_html( $label ),
			$count
		);
	}

	// The two rows sit in a band of their own between the header and the body,
	// full width with a hairline under it, which is where the design puts them.
	// Inside .bw-page__body they were a pair of controls floating on the canvas,
	// stopping at the content width rather than running the width of the screen.
	printf(
		'<div class="bw-guidebar"><nav class="bw-prodtabs" aria-label="%1$s" data-blueworx-guide-products><span class="bw-prodtabs__label">%2$s</span>%3$s</nav><nav class="bw-tabs bw-tabs--drag" aria-label="%4$s" data-blueworx-guide-tabs>%5$s</nav></div><div class="bw-page__body"><div class="bw-panels">',
		esc_attr__( 'Guide sections', 'blueworx-labs-wordpress' ),
		esc_html__( 'Section:', 'blueworx-labs-wordpress' ),
		wp_kses( $product_html, blueworx_ds_allowed_html() ),
		esc_attr__( 'Guide topics', 'blueworx-labs-wordpress' ),
		wp_kses( $tab_html, blueworx_ds_allowed_html() )
	);

	$sections = blueworx_get_feature_sections();

	// Every topic in this section is rendered and all but the chosen one hidden,
	// so switching topic is instant rather than a page load. The tabs stay real
	// addresses: with no script each one still arrives here, and the server has
	// already hidden the rest.
	foreach ( $tabs as $tab_id => $tab_label ) {
		printf(
			'<div class="bw-guidegrid bw-guides" data-blueworx-guide-panel="%1$s"%2$s>',
			esc_attr( $tab_id ),
			$tab_id === $active ? '' : ' hidden'
		);

		foreach ( $guides as $guide ) {
			// The same normalisation the counts use, so a guide whose declared
			// tab does not exist lands under Other rather than nowhere at all.
			$guide_tab = isset( $tabs[ $guide['tab'] ] ) ? $guide['tab'] : BLUEWORX_GUIDES_FALLBACK_TAB;

			if ( $guide['product'] !== $product || $guide_tab !== $tab_id ) {
				continue;
			}

			// Guides can come from other plugins, so the body is still filtered
			// down to safe post markup — no scripts, no event handlers.
			$body = wp_kses_post( $guide['body'] );

			$minutes = blueworx_guide_read_time( $guide['body'] );

			$read_time = blueworx_ds_badge(
				sprintf(
					/* translators: %d: how many minutes the guide takes to read. */
					_n( '%d min read', '%d min read', $minutes, 'blueworx-labs-wordpress' ),
					$minutes
				),
				'neutral'
			);

			// Who can actually do the thing the guide describes, worked out from
			// this site's own roles rather than a list written down here.
			$pills = blueworx_ds_role_pills(
				blueworx_roles_with_capability( blueworx_guide_tab_capability( $guide['tab'] ) ),
				'guide:' . $guide['id']
			);

			$action = '';

			// A guide about a WordPress screen sends you to that screen. A guide
			// about one of our functions sends you to its section on Enhancements.
			$screens = array(
				'wp-writing' => 'edit.php',
				'wp-media'   => 'upload.php',
				'wp-people'  => 'users.php',
				'wp-upkeep'  => 'site-health.php',
			);

			if ( isset( $screens[ $guide['tab'] ] ) ) {
				$action = blueworx_ds_button(
					array(
						'label' => __( 'Open the screen', 'blueworx-labs-wordpress' ),
						'icon'  => 'arrow-right',
						'href'  => admin_url( $screens[ $guide['tab'] ] ),
					)
				);
			} elseif ( isset( $sections[ $guide['tab'] ] ) ) {
				$action = blueworx_ds_button(
					array(
						'label' => sprintf(
							/* translators: %s: a settings section name, e.g. "Security & Access". */
							__( 'Open %s', 'blueworx-labs-wordpress' ),
							$sections[ $guide['tab'] ]
						),
						'icon'  => 'arrow-right',
						'href'  => add_query_arg(
							array(
								'page'    => 'blueworx-labs-wordpress',
								'section' => $guide['tab'],
							),
							admin_url( 'admin.php' )
						),
					)
				);
			}

			// Not wp_kses(): the badge, the pills and the button all carry
			// attributes the allow-list would drop in silence.
			echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				array(
					'eyebrow' => $tab_label,
					'title'   => $guide['title'],
					'actions' => $read_time,
					'body'    => '<div class="bw-guide__body">' . $body . '</div>',
					'footer'  => $pills . $action,
					// Kept from the old markup: other plugins and the tests both
					// address guides by id, and that is not ours to break.
					'attrs'   => array( 'data-blueworx-guide' => $guide['id'] ),
				)
			);
		}

		echo '</div>';
	}

	echo '</div></div>';

	echo wp_kses( blueworx_ds_screen_close(), blueworx_ds_allowed_html() );
}
