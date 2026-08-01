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
 * Adds Guides to the BlueWorx menu.
 *
 * Registered after blueworx_register_settings_page() so it sits below the
 * screens it explains.
 *
 * @return void
 */
function blueworx_register_guides_page() {
	add_submenu_page(
		'blueworx-labs-wordpress',
		esc_html__( 'Guides', 'blueworx-labs-wordpress' ),
		esc_html__( 'Guides', 'blueworx-labs-wordpress' ),
		'manage_options',
		'blueworx-guides',
		'blueworx_render_guides_page'
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

	if ( empty( $tabs ) ) {
		?>
		<div class="wrap blueworx-guides">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'No guides are available. Every function is switched off in BlueWorx > Enhancements.', 'blueworx-labs-wordpress' ); ?></p>
		</div>
		<?php
		return;
	}
	?>
	<div class="wrap blueworx-guides">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<p><?php esc_html_e( 'How to use this site and everything BlueWorx adds to it. Only functions that are switched on appear here.', 'blueworx-labs-wordpress' ); ?></p>

		<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Guide sections', 'blueworx-labs-wordpress' ); ?>">
			<?php
			foreach ( $tabs as $id => $label ) :
				$url = add_query_arg(
					array(
						'page' => 'blueworx-guides',
						'tab'  => $id,
					),
					admin_url( 'admin.php' )
				);
				?>
				<a
					href="<?php echo esc_url( $url ); ?>"
					class="nav-tab<?php echo $id === $active ? ' nav-tab-active' : ''; ?>"
					data-blueworx-guide-tab="<?php echo esc_attr( $id ); ?>"
					<?php echo $id === $active ? ' aria-current="page"' : ''; ?>
				><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<div class="blueworx-guides-panel" data-blueworx-guide-panel="<?php echo esc_attr( $active ); ?>">
			<?php
			foreach ( $guides as $guide ) :
				if ( $guide['tab'] !== $active ) {
					continue;
				}
				?>
				<div class="postbox blueworx-guide" data-blueworx-guide="<?php echo esc_attr( $guide['id'] ); ?>">
					<div class="postbox-header">
						<h2 class="hndle"><?php echo esc_html( $guide['title'] ); ?></h2>
					</div>
					<div class="inside">
						<?php
						// Guides can come from other plugins, so the body is filtered
						// down to safe post markup — no scripts, no event handlers.
						echo wp_kses_post( $guide['body'] );
						?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}
