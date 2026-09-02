<?php
/**
 * The BlueWorx screens that are not Enhancements, Guides, Edit Menu or Cache.
 *
 * The design gives Support access and Single sign-on pages of their own rather
 * than leaving them buried in an Enhancements panel: both are things somebody
 * comes to the admin area specifically to do, and neither is findable when it
 * is a disclosure inside a list of twenty-seven switches. The Enhancements
 * panel stays the on/off control for each — these pages are where the work
 * happens once it is on.
 *
 * Embedded controls and System additions are reference screens. They exist so
 * nobody has to read the plugin to find out where it puts things.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Opens a BlueWorx screen with its header, and dies for anyone without access.
 *
 * @param array $args {
 *     @type string $title   Screen title.
 *     @type string $lede    One-line explanation.
 *     @type string $actions Header action HTML.
 *     @type string $form    Opening <form> tag and its hidden fields, for a
 *                           screen that ends in a save bar. It opens outside
 *                           the page body so the bar is a sibling of the
 *                           content rather than the last thing inside it —
 *                           which is what lets the bar sit at the foot of the
 *                           window on a screen too short to scroll. Close it in
 *                           the footer passed to blueworx_close_admin_page().
 * }
 * @return void
 */
function blueworx_open_admin_page( $args ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'blueworx-labs-wordpress' ) );
	}

	$args = wp_parse_args(
		$args,
		array(
			'eyebrow' => 'BlueWorx',
			'title'   => '',
			'lede'    => '',
			'actions' => '',
			'form'    => '',
		)
	);

	// Not wp_kses(): the page header carries badges built by the design system
	// helpers, and the allow-list drops attributes it does not know about.
	echo blueworx_ds_screen_open( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		blueworx_ds_page_header(
			array(
				'eyebrow'    => $args['eyebrow'],
				'title'      => $args['title'],
				'lede'       => $args['lede'],
				'actions'    => $args['actions'],
				'capability' => 'manage_options',
			)
		)
	);

	if ( '' !== $args['form'] ) {
		// Not wp_kses(): this is the caller's own <form> tag and nonce field,
		// and the allow-list has no opinion on form attributes.
		echo $args['form']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	echo '<div class="bw-page__body"><div class="bw-panels">';
}

/**
 * Closes a BlueWorx screen opened by blueworx_open_admin_page().
 *
 * @param string $footer Optional HTML printed after the page body and before
 *                       the screen closes — a save bar, and the closing
 *                       </form> when the screen opened one.
 * @return void
 */
function blueworx_close_admin_page( $footer = '' ) {
	echo '</div></div>';

	if ( '' !== $footer ) {
		// Not wp_kses(): the save bar is built from design system helpers, which
		// escape everything they emit, and the allow-list drops the button
		// attributes it does not know about.
		echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	echo wp_kses( blueworx_ds_screen_close(), blueworx_ds_allowed_html() );
}

/**
 * Switches support access on or off from its own screen.
 *
 * Writes through blueworx_set_feature_enabled() rather than touching the option,
 * so this and Enhancements cannot end up disagreeing.
 *
 * Switching it OFF shuts any open window with it. Leaving a window open on a
 * function nobody can see is exactly the state this screen exists to prevent.
 *
 * @return void
 */
function blueworx_handle_support_feature_toggle() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_toggle_support_feature' );

	$on = ! empty( $_POST['blueworx_support_feature'] );

	blueworx_set_feature_enabled( 'support_access', $on );

	if ( ! $on && function_exists( 'blueworx_support_close_access' ) ) {
		blueworx_support_close_access();
	}

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-support' ) );
	exit;
}
add_action( 'admin_post_blueworx_toggle_support_feature', 'blueworx_handle_support_feature_toggle' );

/**
 * Renders the Support access screen.
 *
 * @return void
 */
function blueworx_render_support_page() {
	$open = blueworx_support_access_open();

	blueworx_open_admin_page(
		array(
			'title'   => __( 'Support access', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'Lets BlueWorx open a read-only support session with one key, for a 24-hour window you control.', 'blueworx-labs-wordpress' ),
			'actions' => blueworx_ds_badge(
				$open ? __( 'Access open', 'blueworx-labs-wordpress' ) : __( 'Shut', 'blueworx-labs-wordpress' ),
				$open ? 'success' : 'neutral',
				true
			),
		)
	);

	$on = blueworx_feature_enabled( 'support_access' );

	// The switch lives in the card's own head, which is where the design puts
	// it: the thing it switches on is the card, and a strip of controls above
	// the card read as a second, unrelated setting. It is still the same writer
	// as Enhancements — both go through blueworx_set_feature_enabled().
	//
	// Its own form, a sibling of the panel's rather than a parent: a form inside
	// a form is invalid and the browser drops the inner one, which would leave
	// every button in the panel inert.
	$switch = sprintf(
		'<form method="post" action="%1$s">%2$s<input type="hidden" name="action" value="blueworx_toggle_support_feature" /><label class="bw-switch bw-switch--bare"><input type="checkbox" role="switch" name="blueworx_support_feature" value="1"%3$s data-testid="bw-support-feature" /><span class="bw-switch__track"><span class="bw-switch__thumb"></span></span><span class="screen-reader-text">%4$s</span></label>%5$s</form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		wp_nonce_field( 'blueworx_toggle_support_feature', '_wpnonce', true, false ),
		checked( $on, true, false ),
		esc_html__( 'Support access is available on this site', 'blueworx-labs-wordpress' ),
		// A Save beside the switch rather than an onchange handler in the
		// markup. Every other switch in this plugin waits for a save, and this
		// file does not put event handlers in HTML.
		blueworx_ds_button(
			array(
				'label' => __( 'Save', 'blueworx-labs-wordpress' ),
				'type'  => 'submit',
				'size'  => 'sm',
			)
		)
	);

	// Title first, caption under it — assets/css/admin-additions.css reverses
	// the order the system's card head renders them in.
	echo '<section class="bw-card bw-supportcard"><div class="bw-card__head"><div class="bw-card__titles">';
	echo '<p class="bw-card__eyebrow">' . esc_html__( 'Read-only, and only while the window is open', 'blueworx-labs-wordpress' ) . '</p>';
	echo '<h2 class="bw-card__title">' . esc_html__( 'BlueWorx support access', 'blueworx-labs-wordpress' ) . '</h2>';
	echo '</div><div class="bw-card__actions">';
	echo $switch; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from escaped parts.
	echo '</div></div><div class="bw-card__body">';

	if ( ! $on ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone'  => 'info',
					'title' => __( 'Support access is switched off', 'blueworx-labs-wordpress' ),
					'text'  => __( 'Nobody at BlueWorx can reach this site while it is off, and no key here will work. Switch it on above to generate a key and open a 24-hour read-only window.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);

		echo '</div></section>';

		blueworx_close_admin_page();
		return;
	}

	// The panel is nothing but submit buttons, and a submit button outside a
	// form is inert — it renders correctly, takes the click and does nothing.
	// On Enhancements it inherits the settings form; here it needs its own.
	echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=blueworx-support' ) ) . '">';

	blueworx_support_render_panel();

	echo '</form>';

	echo '</div></section>';

	blueworx_close_admin_page();
}

/**
 * Saves the External access on/off switch.
 *
 * @return void
 */
function blueworx_handle_external_feature_toggle() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_toggle_external_feature' );

	$on = ! empty( $_POST['blueworx_external_feature'] );

	blueworx_set_feature_enabled( 'external_access', $on );

	if ( $on && function_exists( 'blueworx_external_register_role' ) ) {
		// Registered here as well as on admin_init so the role exists for the
		// very first invitation, which can be made before the next page load.
		blueworx_external_register_role();
	}

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-external' ) );
	exit;
}
add_action( 'admin_post_blueworx_toggle_external_feature', 'blueworx_handle_external_feature_toggle' );

/**
 * Renders the External access screen.
 *
 * @return void
 */
function blueworx_render_external_page() {
	$on    = blueworx_feature_enabled( 'external_access' );
	$count = $on ? count( blueworx_external_invitations() ) : 0;

	blueworx_open_admin_page(
		array(
			'title'   => __( 'External access', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'Invite somebody to look round the back end of this site without being able to change anything.', 'blueworx-labs-wordpress' ),
			'actions' => blueworx_ds_badge(
				$count > 0
					/* translators: %d: number of people invited. */
					? sprintf( _n( '%d person invited', '%d people invited', $count, 'blueworx-labs-wordpress' ), $count )
					: __( 'Nobody invited', 'blueworx-labs-wordpress' ),
				$count > 0 ? 'success' : 'neutral',
				true
			),
		)
	);

	$switch = sprintf(
		'<form method="post" action="%1$s">%2$s<input type="hidden" name="action" value="blueworx_toggle_external_feature" /><label class="bw-switch bw-switch--bare"><input type="checkbox" role="switch" name="blueworx_external_feature" value="1"%3$s data-testid="bw-external-feature" /><span class="bw-switch__track"><span class="bw-switch__thumb"></span></span><span class="screen-reader-text">%4$s</span></label>%5$s</form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		wp_nonce_field( 'blueworx_toggle_external_feature', '_wpnonce', true, false ),
		checked( $on, true, false ),
		esc_html__( 'External access is available on this site', 'blueworx-labs-wordpress' ),
		blueworx_ds_button(
			array(
				'label' => __( 'Save', 'blueworx-labs-wordpress' ),
				'type'  => 'submit',
				'size'  => 'sm',
			)
		)
	);

	echo '<section class="bw-card"><div class="bw-card__head"><div class="bw-card__titles">';
	echo '<p class="bw-card__eyebrow">' . esc_html__( 'They can look at everything and change nothing', 'blueworx-labs-wordpress' ) . '</p>';
	echo '<h2 class="bw-card__title">' . esc_html__( 'External viewer access', 'blueworx-labs-wordpress' ) . '</h2>';
	echo '</div><div class="bw-card__actions">';
	echo $switch; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from escaped parts.
	echo '</div></div><div class="bw-card__body">';

	if ( ! $on ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone'  => 'info',
					'title' => __( 'External access is switched off', 'blueworx-labs-wordpress' ),
					'text'  => __( 'Nobody can be invited while it is off, and anybody already invited cannot sign in. Switch it on above to invite somebody.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);

		echo '</div></section>';

		blueworx_close_admin_page();
		return;
	}

	blueworx_external_render_panel();

	echo '</div></section>';

	blueworx_close_admin_page();
}

/**
 * Renders the Single sign-on screen.
 *
 * @return void
 */
function blueworx_render_sso_page() {
	$connected = '' !== trim( (string) blueworx_sso_option( 'client_secret' ) );
	$enabled   = blueworx_feature_enabled( 'sso' );

	// Its own action, not the Enhancements one: that form posts every feature
	// switch on the site, and a missing checkbox reads as an unticked one.
	$form = $enabled
		? sprintf(
			'<form method="post" action="%1$s"><input type="hidden" name="action" value="blueworx_save_sso_settings" />%2$s',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'blueworx_save_sso_settings', '_wpnonce', true, false )
		)
		: '';

	blueworx_open_admin_page(
		array(
			'title'   => __( 'Single sign-on', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'Lets people sign in with an external identity provider using OpenID Connect.', 'blueworx-labs-wordpress' ),
			'actions' => blueworx_ds_badge(
				$connected ? __( 'Connected', 'blueworx-labs-wordpress' ) : __( 'Not connected', 'blueworx-labs-wordpress' ),
				$connected ? 'success' : 'neutral',
				true
			),
			'form'    => $form,
		)
	);

	if ( ! $enabled ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone'    => 'info',
					'title'   => __( 'Single sign-on is switched off', 'blueworx-labs-wordpress' ),
					'text'    => __( 'Nobody can sign in through a provider while it is off, and this page is not listed in the menu. Switch it on under Enhancements to set it up.', 'blueworx-labs-wordpress' ),
					'actions' => blueworx_ds_button(
						array(
							'label' => __( 'Go to Enhancements', 'blueworx-labs-wordpress' ),
							'icon'  => 'arrow-right',
							'href'  => admin_url( 'admin.php?page=blueworx-labs-wordpress&section=security' ),
						)
					),
				)
			),
			blueworx_ds_allowed_html()
		);

		blueworx_close_admin_page();
		return;
	}

	$saved = get_transient( 'blueworx_labs_notice' );

	if ( $saved ) {
		delete_transient( 'blueworx_labs_notice' );

		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone' => 'success',
					'text' => $saved,
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	echo '<section class="bw-card"><div class="bw-card__head"><div class="bw-card__titles">';
	echo '<h2 class="bw-card__title">' . esc_html__( 'Provider', 'blueworx-labs-wordpress' ) . '</h2>';
	echo '</div></div><div class="bw-card__body">';

	blueworx_sso_render_detail();

	echo '</div></section>';

	$savebar = '<div class="bw-savebar"><span class="bw-savebar__hint">'
		. esc_html__( 'Nothing changes until you save.', 'blueworx-labs-wordpress' )
		. '</span>'
		. blueworx_ds_button(
			array(
				'label'   => __( 'Save changes', 'blueworx-labs-wordpress' ),
				'variant' => 'primary',
				'type'    => 'submit',
			)
		)
		. '</div></form>';

	blueworx_close_admin_page( $savebar );
}

/**
 * The places this plugin adds a control to one of WordPress's own screens.
 *
 * Each row is a real thing the plugin does, gated on the function that puts it
 * there — so the list shrinks as functions are switched off rather than
 * describing a plugin nobody is running.
 *
 * @return array List of rows, each with where, what, and the feature key.
 */
function blueworx_embedded_control_map() {
	return array(
		array(
			'feature' => 'content_tools',
			'where'   => __( 'Pages and posts list', 'blueworx-labs-wordpress' ),
			'what'    => __( 'A Duplicate link in the row actions, beside Edit and Trash.', 'blueworx-labs-wordpress' ),
			'link'    => admin_url( 'edit.php?post_type=page' ),
		),
		array(
			'feature' => 'content_tools',
			'where'   => __( 'The editor, in the settings panel', 'blueworx-labs-wordpress' ),
			'what'    => __( 'A "Link to another site" box, when external addresses are switched on.', 'blueworx-labs-wordpress' ),
			'link'    => '',
		),
		array(
			'feature' => 'page_excerpts',
			'where'   => __( 'The editor, in the settings panel', 'blueworx-labs-wordpress' ),
			'what'    => __( 'The Excerpt box on pages, which WordPress hides by default.', 'blueworx-labs-wordpress' ),
			'link'    => '',
		),
		array(
			'feature' => 'user_roles',
			'where'   => __( 'Add User and Edit User', 'blueworx-labs-wordpress' ),
			'what'    => __( 'The Role dropdown becomes a list of tick boxes, so one person can hold more than one role.', 'blueworx-labs-wordpress' ),
			'link'    => admin_url( 'user-new.php' ),
		),
		array(
			'feature' => 'profile_cleanup',
			'where'   => __( 'Your profile', 'blueworx-labs-wordpress' ),
			'what'    => __( 'The settings nobody uses are taken out, and what is left is grouped.', 'blueworx-labs-wordpress' ),
			'link'    => admin_url( 'profile.php' ),
		),
		array(
			'feature' => 'application_passwords',
			'where'   => __( 'Your profile', 'blueworx-labs-wordpress' ),
			'what'    => __( 'Application Passwords is hidden unless you switch it back on.', 'blueworx-labs-wordpress' ),
			'link'    => admin_url( 'profile.php' ),
		),
		array(
			'feature' => 'media_tools',
			'where'   => __( 'Media library, on one item', 'blueworx-labs-wordpress' ),
			'what'    => __( 'A Replace file control, so an address stays the same when the file behind it changes.', 'blueworx-labs-wordpress' ),
			'link'    => admin_url( 'upload.php' ),
		),
		array(
			'feature' => 'view_as_role',
			'where'   => __( 'The sidebar, above Log Out', 'blueworx-labs-wordpress' ),
			'what'    => __( 'A control saying which view you are in, and the roles below yours you can look as.', 'blueworx-labs-wordpress' ),
			'link'    => '',
		),
		array(
			'feature' => 'admin_bar',
			'where'   => __( 'The toolbar', 'blueworx-labs-wordpress' ),
			'what'    => __( 'Whatever you took out of it, plus the Howdy greeting and the Help tab.', 'blueworx-labs-wordpress' ),
			'link'    => '',
		),
		array(
			'feature' => 'dashboard_widgets',
			'where'   => __( 'Dashboard', 'blueworx-labs-wordpress' ),
			'what'    => __( 'The panels you removed are gone for everyone, not hidden behind Screen Options.', 'blueworx-labs-wordpress' ),
			'link'    => admin_url( 'index.php' ),
		),
	);
}

/**
 * Renders the Embedded controls screen.
 *
 * @return void
 */
function blueworx_render_embedded_page() {
	blueworx_open_admin_page(
		array(
			'eyebrow' => __( 'Reference', 'blueworx-labs-wordpress' ),
			'title'   => __( 'Controls inside WordPress screens', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'Where this plugin puts things inside WordPress\'s own screens. Switch a function off and its control goes with it.', 'blueworx-labs-wordpress' ),
		)
	);

	$features = blueworx_get_feature_definitions();
	$rows     = '';

	foreach ( blueworx_embedded_control_map() as $row ) {
		$on    = blueworx_feature_enabled( $row['feature'] );
		$label = isset( $features[ $row['feature'] ]['label'] ) ? $features[ $row['feature'] ]['label'] : $row['feature'];

		$where = esc_html( $row['where'] );

		if ( '' !== $row['link'] ) {
			$where = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $row['link'] ),
				esc_html( $row['where'] )
			);
		}

		$rows .= sprintf(
			'<tr><td class="bw-table__primary">%1$s<span class="bw-table__sub">%2$s</span></td><td>%3$s</td><td>%4$s</td></tr>',
			$where,
			esc_html( $label ),
			esc_html( $row['what'] ),
			blueworx_ds_badge(
				$on ? __( 'On', 'blueworx-labs-wordpress' ) : __( 'Off', 'blueworx-labs-wordpress' ),
				$on ? 'success' : 'neutral',
				true
			)
		);
	}

	$table = sprintf(
		'<div style="overflow-x:auto"><table class="bw-table"><thead><tr><th scope="col">%1$s</th><th scope="col">%2$s</th><th scope="col">%3$s</th></tr></thead><tbody>%4$s</tbody></table></div>',
		esc_html__( 'Where', 'blueworx-labs-wordpress' ),
		esc_html__( 'What appears', 'blueworx-labs-wordpress' ),
		esc_html__( 'Status', 'blueworx-labs-wordpress' ),
		$rows
	);

	// Not wp_kses(): the badges carry attributes the allow-list does not know.
	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		array(
			'title' => __( 'Where they appear', 'blueworx-labs-wordpress' ),
			'body'  => $table,
			'flush' => true,
		)
	);

	blueworx_close_admin_page();
}

/**
 * Renders the System additions screen.
 *
 * @return void
 */
function blueworx_render_additions_page() {
	blueworx_open_admin_page(
		array(
			'eyebrow' => __( 'Handover', 'blueworx-labs-wordpress' ),
			'title'   => __( 'Added to the design system', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'What these screens needed that the shared design system did not already have. Anything listed here should go back into the system.', 'blueworx-labs-wordpress' ),
		)
	);

	$additions = array(
		array(
			__( 'Role pill', 'blueworx-labs-wordpress' ),
			__( 'Guides and page headers both have to say who can do a thing, and a section can carry eight roles.', 'blueworx-labs-wordpress' ),
			__( 'Resting, Administrator, "+N more", dropdown open', 'blueworx-labs-wordpress' ),
		),
		array(
			__( 'Page access row', 'blueworx-labs-wordpress' ),
			__( 'Every screen states which roles can reach it, hard right of the title.', 'blueworx-labs-wordpress' ),
			__( 'Wraps rather than overflows', 'blueworx-labs-wordpress' ),
		),
		array(
			__( 'Drag-scrollable tab bar', 'blueworx-labs-wordpress' ),
			__( 'Guides can carry more tabs than fit, and a scrollbar under them looked broken.', 'blueworx-labs-wordpress' ),
			__( 'Grab, grabbing, drag suppresses the click', 'blueworx-labs-wordpress' ),
		),
		array(
			__( 'Guide grid', 'blueworx-labs-wordpress' ),
			__( 'Two equal columns from 1000px, with card footers lining up.', 'blueworx-labs-wordpress' ),
			__( 'One column, two columns', 'blueworx-labs-wordpress' ),
		),
		array(
			__( 'Guides section band', 'blueworx-labs-wordpress' ),
			__( 'Section and topic run the full width under the page header, as one white band with a hairline.', 'blueworx-labs-wordpress' ),
			__( 'Resting only', 'blueworx-labs-wordpress' ),
		),
		array(
			__( 'Full-bleed page shell', 'blueworx-labs-wordpress' ),
			__( 'Our screens run flush to the sidebar and top bar, so the wrap margins WordPress adds are cancelled on them.', 'blueworx-labs-wordpress' ),
			__( 'Header, body, sticky save bar', 'blueworx-labs-wordpress' ),
		),
		array(
			__( 'Menu bucket grid', 'blueworx-labs-wordpress' ),
			__( 'Edit Menu lays its buckets out across the width rather than one per row.', 'blueworx-labs-wordpress' ),
			__( 'One to four columns', 'blueworx-labs-wordpress' ),
		),

		array(
			__( 'Side nav', 'blueworx-labs-wordpress' ),
			__( 'The admin menu as the design draws it: groups, counts and sub-items.', 'blueworx-labs-wordpress' ),
			__( 'Resting, active, parent of active, drawer', 'blueworx-labs-wordpress' ),
		),
		array(
			__( 'Form controls', 'blueworx-labs-wordpress' ),
			__( 'Checkbox, radio, select, input, textarea, field and choice group, rendered from PHP.', 'blueworx-labs-wordpress' ),
			__( 'Resting, hover, focus, disabled', 'blueworx-labs-wordpress' ),
		),
	);

	$rows = '';

	foreach ( $additions as $addition ) {
		$rows .= sprintf(
			'<tr><td class="bw-table__primary">%1$s</td><td>%2$s</td><td>%3$s</td></tr>',
			esc_html( $addition[0] ),
			esc_html( $addition[1] ),
			esc_html( $addition[2] )
		);
	}

	$table = sprintf(
		'<div style="overflow-x:auto"><table class="bw-table"><thead><tr><th scope="col">%1$s</th><th scope="col">%2$s</th><th scope="col">%3$s</th></tr></thead><tbody>%4$s</tbody></table></div>',
		esc_html__( 'Component', 'blueworx-labs-wordpress' ),
		esc_html__( 'Why it was needed', 'blueworx-labs-wordpress' ),
		esc_html__( 'States', 'blueworx-labs-wordpress' ),
		$rows
	);

	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		array(
			'title'   => __( 'New components', 'blueworx-labs-wordpress' ),
			'actions' => blueworx_ds_badge( (string) count( $additions ), 'accent' ),
			'body'    => $table,
			'flush'   => true,
		)
	);

	echo wp_kses(
		blueworx_ds_card(
			array(
				'title' => __( 'Nothing else was invented', 'blueworx-labs-wordpress' ),
				'body'    => '<p class="bw-field__help">'
					. esc_html__( 'Every other control on these screens is stock design system — page header, card, settings card, field, switch, checkbox, radio, select, notice, empty state, badge, chip, table, description list and tabs.', 'blueworx-labs-wordpress' )
					. '</p><p class="bw-field__help">'
					. esc_html__( 'The stylesheet is taken verbatim from the shared system. Anything in the table above is a local addition and should be folded back in, or the next sync will drop it.', 'blueworx-labs-wordpress' )
					. '</p><p class="bw-field__help">'
					. esc_html__( 'The function card, the section header, the two-column settings row and the product tab row started here and now live in the shared system, so they are no longer listed above.', 'blueworx-labs-wordpress' )
					. '</p>',
			)
		),
		blueworx_ds_allowed_html()
	);

	blueworx_close_admin_page();
}
