<?php
/**
 * Admin asset loading.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets an asset version that changes when the file changes.
 *
 * @param string $relative_path Relative asset path.
 * @return string Asset version.
 */
function blueworx_get_admin_asset_version( $relative_path ) {
	$path = BLUEWORX_LABS_PATH . ltrim( $relative_path, '/' );

	if ( file_exists( $path ) ) {
		return BLUEWORX_LABS_VERSION . '-' . filemtime( $path );
	}

	return BLUEWORX_LABS_VERSION;
}

/**
 * The four BlueWorx screens the shared admin design system may style.
 *
 * Deliberately a short, explicit list rather than a prefix match. The system's
 * component styles are opt-in: they belong on screens this plugin owns
 * end-to-end, and nowhere near WordPress's own screens, where they would
 * restyle furniture we do not control.
 *
 * @return string[] Admin hook suffixes.
 */
function blueworx_admin_design_screens() {
	return array(
		'toplevel_page_blueworx-labs-wordpress',
		'toplevel_page_blueworx-guides',
		'blueworx_page_blueworx-edit-menu',
		'blueworx_page_blueworx-cache',
		'blueworx_page_blueworx-support',
		'blueworx_page_blueworx-sso',
	);
}

/**
 * WordPress's own screens where this plugin renders a control of its own.
 *
 * Separate from blueworx_admin_design_screens() because the guarantee is
 * different. On our screens the system styles everything; here it styles only
 * what sits inside our own .bw-admin wrapper, and the surrounding core screen
 * has to look exactly as it did. The stylesheet is scoped to .bw-admin, so
 * loading it is safe — but only the screens that actually carry a control get
 * it, and only while the feature behind that control is on.
 *
 * @return string[] Admin hook suffixes.
 */
function blueworx_admin_design_core_screens() {
	$screens = array();

	// Replace-file box and its notices, plus the external-address box: both live
	// on the post editor, and the attachment editor is post.php too.
	if ( blueworx_feature_enabled( 'media_tools' ) || blueworx_feature_enabled( 'content_tools' ) ) {
		$screens[] = 'post.php';
		$screens[] = 'post-new.php';
	}

	// The multi-role checkboxes.
	if ( blueworx_feature_enabled( 'user_roles' ) ) {
		$screens[] = 'user-new.php';
		$screens[] = 'user-edit.php';
		$screens[] = 'profile.php';
	}

	return $screens;
}

/**
 * Enqueues the shared design system stylesheet.
 *
 * Also the plugin's only source of @font-face for Sora and Inter — the separate
 * fonts stylesheet this replaced declared the same six faces against the same
 * files, and two declarations of one thing is one too many. That is why the
 * admin re-skin and the login screen call this too, rather than each carrying a
 * copy: they need the faces, and this is where they now live.
 *
 * @return void
 */
function blueworx_enqueue_admin_design_style() {
	wp_enqueue_style(
		'blueworx-admin-design',
		BLUEWORX_LABS_URL . 'assets/blueworx-admin-design.css',
		array(),
		blueworx_get_admin_asset_version( 'assets/blueworx-admin-design.css' )
	);
}

/**
 * Loads the design system on the BlueWorx screens.
 *
 * Registered separately from blueworx_enqueue_admin_assets() on purpose: that
 * function returns early on some screens once it has loaded what they need, and
 * a stylesheet every BlueWorx screen depends on should not sit behind another
 * screen's early return.
 *
 * @param string $hook_suffix Current admin screen hook.
 * @return void
 */
function blueworx_enqueue_admin_design_system( $hook_suffix ) {
	$screens = array_merge( blueworx_admin_design_screens(), blueworx_admin_design_core_screens() );

	// The view-as bar is hooked to admin_footer, so it can appear on any screen
	// and there is no shorter list to name. The stylesheet is scoped to
	// .bw-admin either way, so what this widens is the download, not the reach.
	if ( ! in_array( $hook_suffix, $screens, true ) && ! blueworx_feature_enabled( 'view_as_role' ) ) {
		return;
	}

	blueworx_enqueue_admin_design_style();

	// Icons are inlined as SVG by the design system's own module, which upgrades
	// any [data-lucide] element. Shipped as a module because that is how the
	// system publishes it; the markup degrades to an empty span without it.
	wp_enqueue_script(
		'blueworx-admin-design-icons',
		BLUEWORX_LABS_URL . 'assets/js/blueworx-admin-design-icons.js',
		array(),
		blueworx_get_admin_asset_version( 'assets/js/blueworx-admin-design-icons.js' ),
		true
	);
}
add_action( 'admin_enqueue_scripts', 'blueworx_enqueue_admin_design_system' );

/**
 * Serves the icon module as a real ES module.
 *
 * wp_enqueue_script() has no module type of its own on the WordPress versions
 * this plugin supports, and the file uses `export`, so without this the browser
 * rejects it on the first export statement.
 *
 * @param string $tag    Script tag.
 * @param string $handle Script handle.
 * @return string Script tag.
 */
function blueworx_admin_design_icons_module( $tag, $handle ) {
	if ( 'blueworx-admin-design-icons' !== $handle ) {
		return $tag;
	}

	return str_replace( '<script ', '<script type="module" ', $tag );
}
add_filter( 'script_loader_tag', 'blueworx_admin_design_icons_module', 10, 2 );

/**
 * Loads admin scripts only on screens touched by this plugin.
 *
 * @param string $hook_suffix Current admin screen hook.
 * @return void
 */
function blueworx_enqueue_admin_assets( $hook_suffix ) {
	$allowed_screens = array(
		'toplevel_page_blueworx-labs-wordpress',
		'blueworx_page_blueworx-edit-menu',
		'blueworx_page_blueworx-cache',
		'toplevel_page_blueworx-guides',
		'blueworx_page_blueworx-support',
		'blueworx_page_blueworx-sso',
		'profile.php',
		'user-edit.php',
	);

	if ( ! in_array( $hook_suffix, $allowed_screens, true ) ) {
		return;
	}

	if ( in_array( $hook_suffix, array( 'profile.php', 'user-edit.php' ), true ) ) {
		$profile_cleanup_enabled       = blueworx_feature_enabled( 'profile_cleanup' );
		$application_passwords_enabled = blueworx_feature_enabled( 'application_passwords' );

		if ( $profile_cleanup_enabled || $application_passwords_enabled ) {
			wp_enqueue_script(
				'blueworx-labs-wordpress-profile-cleanup',
				BLUEWORX_LABS_URL . 'assets/js/profile-cleanup.js',
				array(),
				blueworx_get_admin_asset_version( 'assets/js/profile-cleanup.js' ),
				true
			);

			if ( $profile_cleanup_enabled ) {
				wp_add_inline_script(
					'blueworx-labs-wordpress-profile-cleanup',
					'window.blueworxProfileCleanup = true;',
					'before'
				);
			}

			if ( $application_passwords_enabled && blueworx_should_hide_application_passwords_section() ) {
				wp_add_inline_script(
					'blueworx-labs-wordpress-profile-cleanup',
					'window.blueworxHideApplicationPasswords = true;',
					'before'
				);
			}
		}

		// The two-column profile redesign rides on the admin re-skin: it moves
		// native form sections into BlueWorx cards styled by admin-theme.css, so
		// it only makes sense when that stylesheet is loading.
		if ( blueworx_admin_theme_enabled() ) {
			$profile_user = blueworx_get_current_profile_user();

			if ( $profile_user instanceof WP_User ) {
				wp_enqueue_script(
					'blueworx-labs-wordpress-profile-redesign',
					BLUEWORX_LABS_URL . 'assets/js/profile-redesign.js',
					// Depends on core's user-profile script so the restructure runs
					// after core has bound its password/session handlers, rather than
					// relying on incidental script order.
					array( 'user-profile' ),
					blueworx_get_admin_asset_version( 'assets/js/profile-redesign.js' ),
					true
				);

				// Every role, not just the first: a user may hold several, and a
				// card that names one of them is worse than one that names none.
				$wp_roles   = wp_roles();
				$role_names = array();

				foreach ( (array) $profile_user->roles as $role_key ) {
					if ( isset( $wp_roles->role_names[ $role_key ] ) ) {
						$role_names[] = translate_user_role( $wp_roles->role_names[ $role_key ] );
					}
				}

				$role_label = implode( ', ', $role_names );
				$post_count = (int) count_user_posts( $profile_user->ID, 'post', true );
				$registered = $profile_user->user_registered
					? date_i18n( 'F Y', strtotime( $profile_user->user_registered ) )
					: '';

				wp_localize_script(
					'blueworx-labs-wordpress-profile-redesign',
					'blueworxProfile',
					array(
						'initials'    => blueworx_user_initials( $profile_user->display_name ),
						'name'        => $profile_user->display_name,
						'role'        => $role_label,
						'handle'      => $profile_user->user_login,
						'memberSince' => $registered,
						/* translators: %s: number of published posts. */
						'posts'       => sprintf( _n( '%s post', '%s posts', $post_count, 'blueworx-labs-wordpress' ), number_format_i18n( $post_count ) ),
						'postsUrl'    => get_author_posts_url( $profile_user->ID ),
						'saveLabel'   => __( 'Save Changes', 'blueworx-labs-wordpress' ),
						'viewLabel'   => __( 'View Posts', 'blueworx-labs-wordpress' ),
						'usersUrl'    => ( 'user-edit.php' === $hook_suffix && current_user_can( 'list_users' ) )
							? admin_url( 'users.php' )
							: '',
						'backLabel'   => __( 'Back to Users', 'blueworx-labs-wordpress' ),
						'deleteUrl'   => ( 'user-edit.php' === $hook_suffix
							&& current_user_can( 'delete_users' )
							&& get_current_user_id() !== $profile_user->ID )
							? wp_nonce_url(
								admin_url( 'users.php?action=delete&user=' . $profile_user->ID ),
								'bulk-users'
							)
							: '',
						'deleteLabel' => __( 'Delete This User', 'blueworx-labs-wordpress' ),

						/*
						 * The redesign routes, retitles and drops sections by matching
						 * core's own <h2> text. Those headings are translated, so the
						 * script cannot match English literals — it would silently do
						 * nothing on a non-English install. Pass core's strings through
						 * the default textdomain instead, so each one arrives already
						 * translated to whatever the site actually renders.
						 */
						'sections'    => array(
							'personalOptions'   => __( 'Personal Options' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, default domain intended.
							'name'              => __( 'Name' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, default domain intended.
							'contactInfo'       => __( 'Contact Info' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, default domain intended.
							'aboutYourself'     => __( 'About Yourself' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, default domain intended.
							'aboutTheUser'      => __( 'About the user' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, default domain intended.
							'accountManagement' => __( 'Account Management' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, default domain intended.
						),
						'cardTitles'  => array(
							'name'              => __( 'Profile Details', 'blueworx-labs-wordpress' ),
							'contactInfo'       => __( 'Contact', 'blueworx-labs-wordpress' ),
							'aboutYourself'     => __( 'About', 'blueworx-labs-wordpress' ),
							'aboutTheUser'      => __( 'About', 'blueworx-labs-wordpress' ),
							'accountManagement' => __( 'Account & Security', 'blueworx-labs-wordpress' ),
						),
						'cardSubs'    => array(
							'name'              => __( 'How this user appears across the site.', 'blueworx-labs-wordpress' ),
							'contactInfo'       => __( 'Where notifications and password resets are sent.', 'blueworx-labs-wordpress' ),
							'aboutYourself'     => __( 'Shown on the author archive page and below posts.', 'blueworx-labs-wordpress' ),
							'aboutTheUser'      => __( 'Shown on the author archive page and below posts.', 'blueworx-labs-wordpress' ),
							'accountManagement' => __( 'Password and sign-in security.', 'blueworx-labs-wordpress' ),
						),
						'dangerSub'   => __( 'Content can be reassigned to another user before deletion.', 'blueworx-labs-wordpress' ),
					)
				);
			}
		}
	}

	if ( 'toplevel_page_blueworx-labs-wordpress' === $hook_suffix ) {
		wp_enqueue_script(
			'blueworx-labs-wordpress-feature-settings',
			BLUEWORX_LABS_URL . 'assets/js/feature-settings.js',
			array(),
			blueworx_get_admin_asset_version( 'assets/js/feature-settings.js' ),
			true
		);

		// Only the support panel renders copy buttons, and the panel only
		// renders while the feature is on.
		if ( blueworx_feature_enabled( 'support_access' ) ) {
			wp_enqueue_script(
				'blueworx-labs-wordpress-copy-field',
				BLUEWORX_LABS_URL . 'assets/js/copy-field.js',
				array(),
				blueworx_get_admin_asset_version( 'assets/js/copy-field.js' ),
				true
			);
		}
	}


	if ( 'toplevel_page_blueworx-guides' === $hook_suffix ) {
		wp_enqueue_script(
			'blueworx-labs-wordpress-guides',
			BLUEWORX_LABS_URL . 'assets/js/guides.js',
			array(),
			blueworx_get_admin_asset_version( 'assets/js/guides.js' ),
			true
		);

		return;
	}

	// Support access and single sign-on both render a copy field on their own
	// page now, not just inside the Enhancements panel.
	if ( in_array( $hook_suffix, array( 'blueworx_page_blueworx-support', 'blueworx_page_blueworx-sso' ), true ) ) {
		wp_enqueue_script(
			'blueworx-labs-wordpress-copy-field',
			BLUEWORX_LABS_URL . 'assets/js/copy-field.js',
			array(),
			blueworx_get_admin_asset_version( 'assets/js/copy-field.js' ),
			true
		);

		return;
	}
	if ( 'blueworx_page_blueworx-edit-menu' === $hook_suffix ) {
		// No stylesheet of its own any more: the screen is built from the design
		// system, which blueworx_enqueue_admin_design_system() loads here whether
		// or not the admin theme is on.

		// No jQuery, no jQuery UI: the editor uses native drag-and-drop.
		wp_enqueue_script(
			'blueworx-labs-wordpress-admin-menu-editor',
			BLUEWORX_LABS_URL . 'assets/js/admin-menu-editor.js',
			array(),
			blueworx_get_admin_asset_version( 'assets/js/admin-menu-editor.js' ),
			true
		);

		return;
	}
}
add_action( 'admin_enqueue_scripts', 'blueworx_enqueue_admin_assets' );
