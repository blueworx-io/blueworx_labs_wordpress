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

		// Only the support panel renders the copy button, and the panel only
		// renders while the feature is on.
		if ( blueworx_feature_enabled( 'support_access' ) ) {
			wp_enqueue_script(
				'blueworx-labs-wordpress-support-prompt',
				BLUEWORX_LABS_URL . 'assets/js/support-prompt.js',
				array(),
				blueworx_get_admin_asset_version( 'assets/js/support-prompt.js' ),
				true
			);
		}
	}

	if ( 'blueworx_page_blueworx-edit-menu' === $hook_suffix ) {
		// Unconditional: the Edit Menu screen must be usable with admin_theme off,
		// so it carries its own styling rather than leaning on admin-theme.css.
		wp_enqueue_style(
			'blueworx-labs-wordpress-admin-menu-editor',
			BLUEWORX_LABS_URL . 'assets/css/admin-menu-editor.css',
			array(),
			blueworx_get_admin_asset_version( 'assets/css/admin-menu-editor.css' )
		);

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
