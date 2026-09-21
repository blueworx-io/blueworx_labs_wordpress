<?php
/**
 * The shell: every piece of markup around checkout, thank-you and the
 * customer dashboard.
 *
 * Run with: php tests/php/store-shell-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

require __DIR__ . '/../../includes/store/views.php';
require __DIR__ . '/../../includes/store/shell.php';

$views = blueworx_store_normalize_views( array(
	array( 'key' => 'dashboard', 'label' => 'Dashboard', 'title' => 'Your account', 'icon' => 'layout-dashboard', 'where' => 'both' ),
	array( 'key' => 'orders', 'label' => 'Orders', 'title' => 'Orders', 'icon' => 'shopping-cart', 'where' => 'side' ),
) );
$html = blueworx_store_shell_page( array(
	'views'        => $views,
	'current'      => 'orders',
	'panels'       => array( 'dashboard' => '<p>home</p>', 'orders' => '<p>orders</p>' ),
	'home_url'     => 'http://x/',
	'site_name'    => 'Fixture & Co',
	'base'         => 'http://x/account/',
	'logout_url'   => 'http://x/out/?a=1&b=2',
	'member_name'  => 'Pat Lee',
	'member_email' => 'pat@example.com',
) );

echo "The frame\n";
check( 'root carries the design system and our own class', false !== strpos( $html, '<div class="bw-admin bw-page blueworx-store" data-blueworx-store data-view-initial="orders">' ), true );
check( 'no clubhouse class survives', false === strpos( $html, 'clubhouse' ), true );
check( 'the site name is escaped', false !== strpos( $html, 'Fixture &amp; Co' ), true );
check( 'ampersands are not double-escaped', false === strpos( $html, '&amp;amp;' ), true );
check( 'the current panel is shown', false !== strpos( $html, 'data-view="orders" role="tabpanel"' ), true );
check( 'the other is hidden', 1 === preg_match( '/data-view="dashboard"[^>]*hidden/', $html ), true );
check( 'view links build on the base', false !== strpos( $html, 'href="http://x/account/?view=orders"' ), true );
check( 'initials from the name', blueworx_store_initials( 'Pat Lee' ), 'PL' );

echo "\nThe checkout frame\n";
$html = blueworx_store_shell_checkout( array(
	'site_name'  => 'Fixture',
	'logo_url'   => '',
	'home_url'   => 'http://x/',
	'home_label' => 'Back to Fixture',
	'body'       => '<p id="shop-content">SHOP</p>',
	'footnote'   => '',
	'links'      => array( array( 'label' => 'Terms', 'href' => 'http://x/terms/' ), array( 'label' => '', 'href' => 'http://x/none/' ) ),
) );
check( 'root', false !== strpos( $html, '<div class="bw-admin blueworx-checkout">' ), true );
check( 'one h1', substr_count( $html, '<h1' ), 1 );
check( 'the body is passed through untouched', false !== strpos( $html, '<p id="shop-content">SHOP</p>' ), true );
check( 'a link with no label is skipped', substr_count( $html, '<nav class="blueworx-checkout__links"' ), 1 );
check( 'and the real one is drawn', false !== strpos( $html, '>Terms</a>' ), true );
check( 'no nav offered', false === strpos( $html, 'bw-secnav' ), true );

echo "\nSmaller pieces\n";
check( 'a bare view url', blueworx_store_view_url( 'orders' ), '?view=orders' );
check( 'appended to a query', blueworx_store_view_url( 'orders', 'http://x/?page_id=4' ), 'http://x/?page_id=4&view=orders' );
check( 'a card', blueworx_store_shell_card( '', '<p>x</p>' ), '<section class="bw-card"><div class="bw-card__body"><p>x</p></div></section>' );
check( 'an icon is the design-system element', blueworx_store_shell_icon( 'lock' ), '<i class="bw-icon" data-lucide="lock" aria-hidden="true"></i>' );
check( 'no name, no icon', blueworx_store_shell_icon( '' ), '' );

finish();
