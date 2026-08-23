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
function blueworx_get_populated_guide_tabs( $guides ) {
	$tabs   = blueworx_get_guide_tabs();
	$counts = array();

	foreach ( $guides as $guide ) {
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
	$guides = blueworx_get_guides();
	$tabs   = blueworx_get_populated_guide_tabs( $guides );
	$active = blueworx_current_guide_tab( $tabs );

	$header = blueworx_ds_page_header(
		array(
			'title'      => __( 'Guides', 'blueworx-labs-wordpress' ),
			'lede'       => __( 'One tab per section you have something switched on in. Switch a function off and its guides go with it.', 'blueworx-labs-wordpress' ),
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
		$tab            = isset( $tabs[ $guide['tab'] ] ) ? $guide['tab'] : BLUEWORX_GUIDES_FALLBACK_TAB;
		$counts[ $tab ] = isset( $counts[ $tab ] ) ? $counts[ $tab ] + 1 : 1;
	}

	// Anchors, not the design system's buttons. Each tab is a real URL somebody
	// can be sent, and the screen has to work with JavaScript off — which a
	// button that swaps panels client-side would take away.
	$tab_html = '';

	foreach ( $tabs as $id => $label ) {
		$url = add_query_arg(
			array(
				'page' => 'blueworx-guides',
				'tab'  => $id,
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

	// The tab bar and the guides are one column, not two: .bw-page__body is a
	// flex row (main plus sidebar), so they have to share a .bw-panels column or
	// the tabs become a narrow left-hand rail.
	printf(
		'<div class="bw-page__body"><div class="bw-panels"><nav class="bw-tabs bw-tabs--inset bw-tabs--drag" aria-label="%1$s" data-blueworx-guide-tabs>%2$s</nav>',
		esc_attr__( 'Guide sections', 'blueworx-labs-wordpress' ),
		wp_kses( $tab_html, blueworx_ds_allowed_html() )
	);

	printf(
		'<div class="bw-guidegrid bw-guides" data-blueworx-guide-panel="%s">',
		esc_attr( $active )
	);

	$sections = blueworx_get_feature_sections();

	foreach ( $guides as $guide ) {
		if ( $guide['tab'] !== $active ) {
			continue;
		}

		// Guides can come from other plugins, so the body is still filtered down
		// to safe post markup — no scripts, no event handlers. Unchanged.
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

		if ( isset( $sections[ $guide['tab'] ] ) ) {
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
				'eyebrow' => isset( $tabs[ $guide['tab'] ] ) ? $tabs[ $guide['tab'] ] : '',
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

	echo '</div></div></div>';

	echo wp_kses( blueworx_ds_screen_close(), blueworx_ds_allowed_html() );
}
