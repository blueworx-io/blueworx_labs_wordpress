<?php
/**
 * The customer dashboard's pure parts: where a request is sent, and what the
 * overview links to.
 *
 * Run with: php tests/php/store-dashboard-test.php
 *
 * @package BlueWorxLabs
 */

// The shared WordPress stand-ins.
require __DIR__ . '/stubs.php';

require __DIR__ . '/../../includes/store/slot.php';
require __DIR__ . '/../../includes/store/views.php';
require __DIR__ . '/../../includes/store/actions.php';
require __DIR__ . '/../../includes/store/shell.php';
require __DIR__ . '/../../includes/store/dashboard.php';

echo "Where a request is sent\n";
// (queried, dashboard_id, claimed_url, signed_in, view, login_url, model, action, id)
check( 'not the dashboard page: stay', blueworx_store_redirect_to( 3, 4, '', true, '', 'http://x/login/' ), '' );
check( 'no id recorded: stay, even at 0', blueworx_store_redirect_to( 0, 0, '', true, '', 'http://x/login/' ), '' );
check( 'the dashboard, signed out, unclaimed: stay, so the shop can draw its own sign-in', blueworx_store_redirect_to( 4, 4, '', false, '', 'http://x/login/' ), '' );
check( 'the dashboard, signed in, unclaimed: stay', blueworx_store_redirect_to( 4, 4, '', true, 'orders', 'http://x/login/' ), '' );
check( 'claimed: go there', blueworx_store_redirect_to( 4, 4, 'http://x/member/', true, '', 'http://x/login/' ), 'http://x/member/' );
check( 'claimed, with the panel kept', blueworx_store_redirect_to( 4, 4, 'http://x/member/', true, 'orders', 'http://x/login/' ), 'http://x/member/?view=orders' );
check( 'claimed, with an action kept', blueworx_store_redirect_to( 4, 4, 'http://x/member/', true, 'orders', 'http://x/login/', 'order', 'show', 'ord_1' ), 'http://x/member/?view=orders&model=order&action=show&id=ord_1' );
check( 'claimed, signed out: still the claim (the claimant guards its own door)', blueworx_store_redirect_to( 4, 4, 'http://x/member/', false, '', 'http://x/login/' ), 'http://x/member/' );

echo "\nWhat the page's content becomes\n";
$html = blueworx_store_dashboard_content(
	'<p>SHOP</p>',
	array(
		'site_name' => 'Fixture',
		'home_url'  => 'http://x/',
	),
	false,
	array(
		array(
			'label' => 'Terms',
			'href'  => 'http://x/terms/',
		),
	)
);
check( 'signed out: the sign-in frame', false !== strpos( $html, '<h1 class="blueworx-checkout__h1">Sign in</h1>' ), true );
check( 'around the shop\'s own content', false !== strpos( $html, '<div class="blueworx-checkout__signin"><p>SHOP</p></div>' ), true );
check( 'with the footer links', false !== strpos( $html, '>Terms</a>' ), true );
check( 'and not the dashboard frame', false === strpos( $html, 'blueworx-store' ), true );

echo "\nThe overview links to every other view\n";
$views = blueworx_store_normalize_views(
	array(
		array( 'key' => 'dashboard' ),
		array(
			'key'   => 'club',
			'label' => 'Club',
			'lede'  => 'L',
		),
	)
);
$html  = blueworx_store_overview( $views, 'http://x/', 'http://x/acct/' );
check( 'one quick link', substr_count( $html, 'blueworx-store__quick"' ), 1 );
check( 'to the club view', false !== strpos( $html, 'href="http://x/acct/?view=club"' ), true );
check( 'none to itself', false === strpos( $html, '?view=dashboard' ), true );
$html = blueworx_store_overview( blueworx_store_normalize_views( array() ), 'http://x/', '' );
check( 'nothing else to offer: empty, so the panel filter can fill it and the empty state comes after', $html, '' );

finish();
