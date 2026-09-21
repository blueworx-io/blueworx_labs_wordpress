<?php
/**
 * The dashboard's views, other plugins' panels, and SureCart's action
 * addresses.
 *
 * Run with: php tests/php/store-views-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

function blueworx_feature_enabled( $key ) {
	return true;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

require __DIR__ . '/../../includes/store/slot.php';
require __DIR__ . '/../../includes/store/views.php';
require __DIR__ . '/../../includes/store/actions.php';

echo "The default views are SureCart's, dashboard first\n";
$defaults = blueworx_store_default_views();
check( 'seven of them', count( $defaults ), 7 );
check( 'in nav order', array_column( $defaults, 'key' ), array( 'dashboard', 'orders', 'invoices', 'billing', 'plans', 'profile', 'account' ) );

echo "\nNormalising what a filter hands back\n";
$n = blueworx_store_normalize_views( array( array( 'key' => 'club', 'label' => 'Club', 'shortcode' => 'club_panel' ) ) );
check( 'dashboard is put back, first', $n[0]['key'], 'dashboard' );
check( 'the plugin view is kept', $n[1]['key'], 'club' );
check( 'missing fields get defaults', $n[1]['where'], 'both' );
check( 'blocks default empty', $n[1]['blocks'], array() );
$n = blueworx_store_normalize_views( array( 'junk', array( 'label' => 'no key' ), array( 'key' => 'a' ), array( 'key' => 'a' ) ) );
check( 'junk and duplicates are dropped', array_column( $n, 'key' ), array( 'dashboard', 'a' ) );

echo "\nWhere a view sits\n";
$views = blueworx_store_normalize_views( array(
	array( 'key' => 'dashboard', 'where' => 'both' ),
	array( 'key' => 'orders', 'where' => 'side' ),
	array( 'key' => 'billing', 'where' => 'bar' ),
) );
check( 'sidebar', array_column( blueworx_store_views_side( $views ), 'key' ), array( 'dashboard', 'orders' ) );
check( 'phone bar', array_column( blueworx_store_views_bar( $views ), 'key' ), array( 'dashboard', 'billing' ) );

echo "\nWhich view an address means\n";
check( 'a known key', blueworx_store_resolve_view( 'orders', $views ), 'orders' );
check( 'junk lands on the dashboard', blueworx_store_resolve_view( 'nope', $views ), 'dashboard' );
check( 'find', blueworx_store_find_view( 'billing', $views )['where'], 'bar' );
check( 'find nothing', blueworx_store_find_view( 'x', $views ), null );

echo "\nThe slot answers '' for anything it cannot render\n";
check( 'no sources', blueworx_store_slot_block( 'surecart/customer-orders' ), '' );
blueworx_store_slot_set_sources(
	static fn ( $n ) => 'surecart/customer-orders' === $n ? '<p>orders</p>' : null,
	static fn ( $t ) => 'club' === $t ? '<p>club</p>' : null
);
check( 'a block', blueworx_store_slot_block( 'surecart/customer-orders' ), '<p>orders</p>' );
check( 'an unregistered block', blueworx_store_slot_block( 'other' ), '' );
check( 'a shortcode', blueworx_store_slot_shortcode( 'club' ), '<p>club</p>' );
blueworx_store_slot_set_sources( static function ( $n ) { throw new RuntimeException( 'boom' ); }, null );
check( 'a source that throws answers empty', blueworx_store_slot_block( 'x' ), '' );

echo "\nAction addresses\n";
$exists = static fn ( $controller, $method ) => 'cancel' === $method;
check( 'a known model with a real method', blueworx_store_is_action( 'subscription', 'cancel', $exists ), true );
check( 'a method the controller lacks', blueworx_store_is_action( 'subscription', 'fly', $exists ), false );
check( 'an unknown model', blueworx_store_is_action( 'widget', 'cancel', $exists ), false );
check( 'subscription belongs under plans', blueworx_store_action_view( 'subscription' ), 'plans' );
check( 'payment_method under account', blueworx_store_action_view( 'payment_method' ), 'account' );
check( 'download has no panel', blueworx_store_action_view( 'download' ), '' );
check( 'the wrapper block', blueworx_store_action_block(), 'surecart/dashboard-page' );

finish();
