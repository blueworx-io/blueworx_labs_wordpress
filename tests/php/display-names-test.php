<?php
/**
 * The renaming rules for roles and plugins.
 *
 * All of this is text in, text out, so it is checked here rather than by driving
 * a browser. What only a running site can show is that the new names reach the
 * screens, and tests/display-names.spec.js covers that.
 *
 * Run with: php tests/php/display-names-test.php
 *
 * @package BlueWorxLabs
 */

// The shared WordPress stand-ins. Kept apart from the docblock above, which
// phpcs otherwise reads as this statement's rather than the file's.
require __DIR__ . '/stubs.php';

// This script stands in for WordPress rather than being loaded into it, so its
// stubs have to carry core's names.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Same: the hook arguments are part of the signature.

function blueworx_feature_enabled( $key ) {
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require __DIR__ . '/../../includes/display-names.php';

/**
 * What one role name comes out as.
 *
 * @param string $name Role name as registered.
 * @return string Name to display.
 */
function blueworx_role_shown_as( $name ) {
	return blueworx_rename_role_name( $name, $name, 'User role' );
}

/**
 * What one plugin name comes out as on the Plugins screen.
 *
 * @param string $name Plugin name as registered.
 * @return string Name to display.
 */
function blueworx_plugin_shown_as( $name ) {
	$plugins = blueworx_rename_plugins_list( array( 'acme/acme.php' => array( 'Name' => $name ) ) );

	return $plugins['acme/acme.php']['Name'];
}

echo "Roles read as the job they do\n";

check( 'Subscriber becomes Customer', blueworx_role_shown_as( 'Subscriber' ), 'Customer' );
check( 'SureCart Shop Manager becomes Commerce Manager', blueworx_role_shown_as( 'SureCart Shop Manager' ), 'Commerce Manager' );
check( 'LatePoint Agent becomes Booking Agent', blueworx_role_shown_as( 'LatePoint Agent' ), 'Booking Agent' );

// The role map names this one outright and the plugin map would have made it
// "Commerce Customer" as well, but by a different route. The exact name has to
// win, or a role gets whatever the first prefix rule happens to say.
check( 'SureCart Customer becomes Commerce Customer', blueworx_role_shown_as( 'SureCart Customer' ), 'Commerce Customer' );

echo "\nAnd the WordPress ones are left alone\n";

foreach ( array( 'Administrator', 'Editor', 'Author', 'Contributor' ) as $wp_role ) {
	check( $wp_role . ' is unchanged', blueworx_role_shown_as( $wp_role ), $wp_role );
}

check( 'and so is text that only looks like a role', blueworx_rename_role_name( 'Subscriber', 'Subscriber', 'Post status' ), 'Subscriber' );

echo "\nPlugins read as what they are for\n";

check( 'SureCart becomes Commerce', blueworx_plugin_shown_as( 'SureCart' ), 'Commerce' );
check( 'SureDash becomes Dashboards', blueworx_plugin_shown_as( 'SureDash' ), 'Dashboards' );
check( 'SureForms becomes Forms Builder', blueworx_plugin_shown_as( 'SureForms' ), 'Forms Builder' );
check( 'LatePoint becomes Bookings', blueworx_plugin_shown_as( 'LatePoint' ), 'Bookings' );
check( 'and an add-on keeps its own half', blueworx_plugin_shown_as( 'SureCart Subscriptions' ), 'Commerce Subscriptions' );
check( 'the linked title is renamed too', blueworx_rename_plugins_list( array( 'a/a.php' => array( 'Title' => 'LatePoint' ) ) )['a/a.php']['Title'], 'Bookings' );
check( 'and anything else is untouched', blueworx_plugin_shown_as( 'Akismet Anti-spam' ), 'Akismet Anti-spam' );

echo "\nA name is only replaced where it stands as a name\n";

// Rewriting the middle of a sentence would be putting words in the plugin
// author's mouth, so a description saying who it works with is left as written.
check(
	'a description mentioning the product is left alone',
	blueworx_rename_display_text( 'Adds checkout blocks for SureCart', blueworx_plugin_display_names() ),
	null
);

echo "\nAnd a menu row keeps whatever core hung off it\n";

$menu_map = blueworx_plugin_display_names();

check(
	'the update bubble survives the rename',
	blueworx_rename_menu_label( 'SureCart <span class="update-plugins count-2">2</span>', $menu_map ),
	'Commerce <span class="update-plugins count-2">2</span>'
);

check( 'a plain row renames', blueworx_rename_menu_label( 'LatePoint', $menu_map ), 'Bookings' );
check( 'and a row we do not know is left for its owner', blueworx_rename_menu_label( 'Tools', $menu_map ), null );

finish();
