<?php
/**
 * The network admin sidebar gets the same icons as a site's, and only once.
 *
 * WordPress builds the network admin's menu on network_admin_menu, not
 * admin_menu, so a swap hooked on admin_menu alone never removes core's icon
 * there and the sidebar shows both.
 *
 * Run with: php tests/php/network-menu-icons-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

require __DIR__ . '/stubs.php';

require __DIR__ . '/../../includes/admin-menu-icons.php';

echo "Network-only menus\n";
check( 'Sites has an icon', '' !== blueworx_get_admin_menu_icon( 'sites.php' ), true );
check( 'network Settings has the settings icon', blueworx_get_admin_menu_icon( 'settings.php' ), blueworx_get_admin_menu_icon( 'options-general.php' ) );

echo "\nWhere the swap runs\n";
$source = (string) file_get_contents( __DIR__ . '/../../includes/admin-theme.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local source file.
check( 'on site screens', false !== strpos( $source, "add_action( 'admin_menu', 'blueworx_swap_admin_menu_icons', 997 );" ), true );
check( 'and on network screens', false !== strpos( $source, "add_action( 'network_admin_menu', 'blueworx_swap_admin_menu_icons', 997 );" ), true );

finish();
