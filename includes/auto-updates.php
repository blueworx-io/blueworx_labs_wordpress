<?php
/**
 * The plugin keeps itself up to date.
 *
 * The update checker bootstrapped in the main plugin file tells WordPress that a
 * newer version exists. This is what decides that WordPress should go ahead and
 * install it, rather than leaving the offer on the Plugins screen for somebody to
 * press. Together they are the whole of "nobody uploads a zip".
 *
 * Forced rather than a setting, deliberately. There is one answer for every site
 * this plugin is on, so there is nothing to keep in sync and nothing to forget on
 * a site nobody has logged into for a year. The cost is real and worth naming: a
 * bad release reaches every site within about twelve hours, with no human in the
 * loop. What stands in its way is the CI that guards every merge, and a release
 * that refuses to publish when the tag and the plugin's own version disagree.
 * There is no downgrade — a bad version is answered by releasing a better one.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns automatic updates on for this plugin, and this plugin only.
 *
 * Every plugin, theme and translation on the site passes through this filter, so
 * the incoming decision is returned untouched for all of them — including a
 * deliberate "no" that some other plugin or the site owner has set, which this
 * has no business overturning.
 *
 * WordPress passes theme and translation items here too, and those carry no
 * `plugin` property at all, hence the isset() rather than a bare comparison.
 *
 * @param bool|null $update Whether to update, as decided so far. Null means
 *                          nobody has an opinion yet.
 * @param object    $item   The update offer. Plugins carry a `plugin` property
 *                          holding the basename, e.g. `my-plugin/my-plugin.php`.
 * @return bool|null True for this plugin, otherwise the decision as it arrived.
 */
function blueworx_force_own_auto_update( $update, $item ) {
	if ( ! is_object( $item ) || ! isset( $item->plugin ) ) {
		return $update;
	}

	if ( plugin_basename( BLUEWORX_LABS_PLUGIN_FILE ) !== $item->plugin ) {
		return $update;
	}

	return true;
}
add_filter( 'auto_update_plugin', 'blueworx_force_own_auto_update', 10, 2 );
