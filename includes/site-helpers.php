<?php
/**
 * Site helpers shared by deactivation and uninstall.php.
 *
 * Kept apart from support-access.php because uninstall.php needs these even
 * when it cannot load that file: on a site already running the BlueWorx theme,
 * the theme has loaded its own copy of the support functions, and loading this
 * plugin's copy as well stops PHP with "cannot redeclare". Each one is guarded
 * for the same reason, should a later theme carry one of its own.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'blueworx_for_each_site' ) ) {
	/**
	 * Runs a callback on every site of a network, or just this one.
	 *
	 * Every site when this is a network and $every_site is true; otherwise once,
	 * on the current site, with nothing switched. The current site is always put
	 * back afterwards, even when the callback fails part way through, so a broken
	 * site cannot leave the rest of the request running against the wrong one.
	 *
	 * @param callable $callback   Work to do on each site.
	 * @param bool     $every_site Whether to cover the whole network.
	 * @return void
	 */
	function blueworx_for_each_site( $callback, $every_site ) {
		if ( ! is_multisite() || ! $every_site ) {
			call_user_func( $callback );
			return;
		}

		foreach ( get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) as $site_id ) {
			switch_to_blog( (int) $site_id );

			try {
				call_user_func( $callback );
			} finally {
				restore_current_blog();
			}
		}
	}
}

if ( ! function_exists( 'blueworx_labs_theme_takes_over' ) ) {
	/**
	 * Whether the current site runs the BlueWorx theme, which takes over from here.
	 *
	 * The 2.0 theme is this plugin moved into a theme, with the same accounts,
	 * roles and settings. Once it runs, the guest viewer and support accounts are
	 * still kept read-only and the settings are still in use, so neither switching
	 * this plugin off nor deleting it may clear them. The theme shares this
	 * plugin's text domain, which no other theme has.
	 *
	 * @return bool
	 */
	function blueworx_labs_theme_takes_over() {
		$theme = wp_get_theme( (string) get_option( 'template' ) );

		return $theme->exists() && 'blueworx-labs-wordpress' === $theme->get( 'TextDomain' );
	}
}
