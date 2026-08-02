<?php
/**
 * Feature registry and per-feature on/off gate.
 *
 * Single source of truth for which enhancement functions exist, how they are
 * grouped on the settings page, and whether each is enabled. Every feature
 * defaults to on: an absent option is treated as enabled, so a fresh install
 * behaves exactly as before this settings page existed.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the ordered settings sections.
 *
 * @return array Section labels keyed by section id, in display order.
 */
function blueworx_get_feature_sections() {
	return array(
		'security'      => __( 'Security & Access', 'blueworx-labs-wordpress' ),
		'content'       => __( 'Content', 'blueworx-labs-wordpress' ),
		'media'         => __( 'Media', 'blueworx-labs-wordpress' ),
		'translation'   => __( 'Translation', 'blueworx-labs-wordpress' ),
		'notifications' => __( 'Notifications & Cleanup', 'blueworx-labs-wordpress' ),
		'performance'   => __( 'Performance', 'blueworx-labs-wordpress' ),
		'admin_menu'    => __( 'Admin Menu', 'blueworx-labs-wordpress' ),
		'appearance'    => __( 'Appearance', 'blueworx-labs-wordpress' ),
		'integrations'  => __( 'Integrations', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Gets the feature registry.
 *
 * Each definition may carry a 'default' of '1' or '0'. It is what a site gets
 * when the option has never been written, which is the only thing that decides
 * how an existing install behaves after an update. '1' is assumed when absent,
 * because that was the registry's only behaviour before this key existed and
 * every feature registered then relies on it.
 *
 * Use '0' for anything that changes URLs, opens network surface, or hands out a
 * capability. Those must be an explicit decision by a site owner, never
 * something an update switches on underneath them.
 *
 * @return array Feature definitions keyed by feature key, in display order.
 */
function blueworx_get_feature_definitions() {
	return array(
		'login'                 => array(
			'label'       => __( 'Custom login & protection', 'blueworx-labs-wordpress' ),
			'description' => __( 'Moves login to a custom URL and blocks the default WordPress login and admin paths.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => 'login',
		),
		'site_protection'       => array(
			'label'       => __( 'Site Protection', 'blueworx-labs-wordpress' ),
			'description' => __( 'Only lets logged-in users with selected roles view the frontend or backend.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => 'site_protection',
		),
		'support_access'        => array(
			'label'       => __( 'BlueWorx support access', 'blueworx-labs-wordpress' ),
			'description' => __( 'Lets BlueWorx open a read-only support session with one key, for a 24-hour window you control. No access is possible until you generate a key and switch the window on.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => 'support_access',
		),
		'user_roles'            => array(
			'label'       => __( 'Flexible user roles', 'blueworx-labs-wordpress' ),
			'description' => __( 'Lists roles alphabetically on the add-user and edit-user screens, and lets one user hold more than one role at a time.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => null,
		),
		'view_as_role'          => array(
			'label'       => __( 'View the admin as another role', 'blueworx-labs-wordpress' ),
			'description' => __( 'Lets an administrator see the admin area the way one of your other roles sees it, to check what that role can and cannot reach. It only ever narrows what you can see, never widens it, and one click puts you back.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => null,
			'default'     => '0',
		),
		'login_session'         => array(
			'label'       => __( 'Login session length', 'blueworx-labs-wordpress' ),
			'description' => __( 'Chooses how long someone stays signed in before they have to sign in again.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => 'login_session',
		),
		'xmlrpc'                => array(
			'label'       => __( 'XML-RPC disabled', 'blueworx-labs-wordpress' ),
			'description' => __( 'Closes the old remote-publishing endpoint that attackers use to guess passwords in bulk. Leave this on unless you use the WordPress mobile app or Jetpack.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => null,
		),
		'author_slugs'          => array(
			'label'       => __( 'Hide usernames in author links', 'blueworx-labs-wordpress' ),
			'description' => __( 'Replaces the username in author page addresses with a meaningless code, so your sign-in names cannot be harvested from the site. Off by default because it changes those addresses.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => null,
			'default'     => '0',
		),
		'application_passwords' => array(
			'label'       => __( 'Application Passwords', 'blueworx-labs-wordpress' ),
			'description' => __( 'Hidden by default. When enabled, only admins can see Application Passwords on admin user profiles.', 'blueworx-labs-wordpress' ),
			'section'     => 'security',
			'detail'      => 'application_passwords',
		),
		'comments'              => array(
			'label'       => __( 'Comments disabled', 'blueworx-labs-wordpress' ),
			'description' => __( 'Turns comments off and removes comment areas from the admin screens.', 'blueworx-labs-wordpress' ),
			'section'     => 'content',
			'detail'      => null,
		),
		'page_excerpts'         => array(
			'label'       => __( 'Page excerpts', 'blueworx-labs-wordpress' ),
			'description' => __( 'Adds excerpt support to Pages, the same way Posts already have it.', 'blueworx-labs-wordpress' ),
			'section'     => 'content',
			'detail'      => null,
		),
		'content_tools'         => array(
			'label'       => __( 'Duplicate and redirect', 'blueworx-labs-wordpress' ),
			'description' => __( 'Adds a Duplicate link to every page and post, so you can copy one as a draft instead of rebuilding it. Can also let a menu entry point at an address on another site.', 'blueworx-labs-wordpress' ),
			'section'     => 'content',
			'detail'      => 'content_tools',
		),
		'revisions'             => array(
			'label'       => __( 'Revision limit', 'blueworx-labs-wordpress' ),
			'description' => __( 'Keeps a set number of saved drafts per page or post instead of every version forever, which stops the database growing without limit.', 'blueworx-labs-wordpress' ),
			'section'     => 'content',
			'detail'      => 'revisions',
		),
		'robots_txt'            => array(
			'label'       => __( 'Search engine rules (robots.txt)', 'blueworx-labs-wordpress' ),
			'description' => __( 'Lets you edit the file that tells search engines which parts of the site to crawl. Off by default so an existing file, or your SEO plugin, stays in charge.', 'blueworx-labs-wordpress' ),
			'section'     => 'content',
			'detail'      => 'robots_txt',
			'default'     => '0',
		),
		'media_tools'           => array(
			'label'       => __( 'Media tools', 'blueworx-labs-wordpress' ),
			'description' => __( 'Replace a file without breaking the links to it, shrink oversized photos as they are uploaded, and optionally allow SVG logos.', 'blueworx-labs-wordpress' ),
			'section'     => 'media',
			'detail'      => 'media_tools',
		),
		'translate'             => array(
			'label'       => __( 'On-page translation', 'blueworx-labs-wordpress' ),
			'description' => __( 'Adds a floating language button that translates the page on the visitor\'s own device. Chrome and Edge only; no effect on search engines.', 'blueworx-labs-wordpress' ),
			'section'     => 'translation',
			'detail'      => 'translate',
		),
		'emails'                => array(
			'label'       => __( 'Email notifications reduced', 'blueworx-labs-wordpress' ),
			'description' => __( 'Stops extra admin emails for user, password, plugin, and theme changes.', 'blueworx-labs-wordpress' ),
			'section'     => 'notifications',
			'detail'      => null,
		),
		'profile_cleanup'       => array(
			'label'       => __( 'Profile cleanup', 'blueworx-labs-wordpress' ),
			'description' => __( 'Hides unused profile options, Elementor AI, and Elementor Notes.', 'blueworx-labs-wordpress' ),
			'section'     => 'notifications',
			'detail'      => null,
		),
		'cache_auto'            => array(
			'label'       => __( 'Automatic cache refresh', 'blueworx-labs-wordpress' ),
			'description' => __( 'Refreshes cache when pages or posts are changed.', 'blueworx-labs-wordpress' ),
			'section'     => 'performance',
			'detail'      => null,
		),
		'cache_manual'          => array(
			'label'       => __( 'Manual cache refresh', 'blueworx-labs-wordpress' ),
			'description' => __( 'Adds a Cache page where cache can be refreshed manually.', 'blueworx-labs-wordpress' ),
			'section'     => 'performance',
			'detail'      => 'cache_manual',
		),
		'menu_editor'           => array(
			'label'       => __( 'Menu editor', 'blueworx-labs-wordpress' ),
			'description' => __( 'Lets you reorder menu items, hide them, or move them into More.', 'blueworx-labs-wordpress' ),
			'section'     => 'admin_menu',
			'detail'      => 'menu_editor',
		),
		'admin_theme'           => array(
			'label'       => __( 'BlueWorx admin theme', 'blueworx-labs-wordpress' ),
			'description' => __( 'Restyles the WordPress admin and login screens with the BlueWorx look. Purely visual; turn off to return to the standard WordPress appearance.', 'blueworx-labs-wordpress' ),
			'section'     => 'appearance',
			'detail'      => null,
		),
		'admin_bar'             => array(
			'label'       => __( 'Toolbar cleanup', 'blueworx-labs-wordpress' ),
			'description' => __( 'Takes the WordPress logo, Customize, update counter and Help drawer out of the black toolbar, and can hide the toolbar entirely on the front of the site.', 'blueworx-labs-wordpress' ),
			'section'     => 'appearance',
			'detail'      => 'admin_bar',
		),
		'dashboard_widgets'     => array(
			'label'       => __( 'Dashboard tidy-up', 'blueworx-labs-wordpress' ),
			'description' => __( 'Removes the dashboard panels you never use, rather than just hiding them behind Screen Options where they come back.', 'blueworx-labs-wordpress' ),
			'section'     => 'appearance',
			'detail'      => 'dashboard_widgets',
		),
		'headless_api'          => array(
			'label'       => __( 'Headless content API', 'blueworx-labs-wordpress' ),
			'description' => __( 'Opens the BlueWorx API that lets a separate front-end app read this site and sign users in. Off unless a site was built that way — nothing else needs it.', 'blueworx-labs-wordpress' ),
			'section'     => 'integrations',
			'detail'      => 'headless_api',
			'default'     => '0',
		),
	);
}

/**
 * Gets a feature's default state.
 *
 * @param string $key Feature key.
 * @return string '1' or '0'.
 */
function blueworx_get_feature_default( $key ) {
	$features = blueworx_get_feature_definitions();

	if ( isset( $features[ $key ]['default'] ) && '0' === (string) $features[ $key ]['default'] ) {
		return '0';
	}

	return '1';
}

/**
 * Checks whether a feature is enabled.
 *
 * An absent option falls back to the feature's registered default, so an
 * existing install is never silently changed by an update: a default-on feature
 * keeps working as it always did, and a default-off feature stays off until
 * somebody ticks it.
 *
 * @param string $key Feature key from blueworx_get_feature_definitions().
 * @return bool True when the feature is enabled.
 */
function blueworx_feature_enabled( $key ) {
	return '0' !== get_option( 'blueworx_feature_' . $key, blueworx_get_feature_default( $key ) );
}
