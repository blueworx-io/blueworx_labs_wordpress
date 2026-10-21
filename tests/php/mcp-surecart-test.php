<?php
/**
 * Connect Claude: the SureCart record helpers.
 *
 * What is scrubbed, how money and dates are shaped, which record types count as
 * personal, where admin links point, and when personal data is blocked.
 *
 * Run with: php tests/php/mcp-surecart-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stand-ins.

function admin_url( $path = '' ) {
	return 'https://shop.test/wp-admin/' . $path;
}

function wp_date( $format, $time = null ) {
	return gmdate( $format, $time );
}

function blueworx_readonly_current_user() {
	return $GLOBALS['readonly_user'];
}

function blueworx_readonly_data_allowed( $user ) {
	return (bool) $GLOBALS['data_open'];
}

require dirname( __DIR__, 2 ) . '/includes/mcp/surecart.php';

check( 'a token is never returned', blueworx_mcp_surecart_scrub( array( 'object' => 'settings', 'api_token' => 'st_x', 'uninstall' => false ) ), array( 'object' => 'settings', 'uninstall' => false ) );
check( 'nor one buried in a record', blueworx_mcp_surecart_scrub( array( 'processors' => array( array( 'id' => 'p1', 'live_secret_key' => 'sk', 'webhook_signing_secret' => 'wh' ) ) ) ), array( 'processors' => array( array( 'id' => 'p1' ) ) ) );
$shaped = blueworx_mcp_surecart_shape( array( 'id' => 'o1', 'currency' => 'gbp', 'amount_due' => 2400, 'created_at' => 1747498098, 'post' => array( 'ID' => 1 ) ) );
check( 'money reads as money', $shaped['amount_due_display'], '£24.00' );
check( 'the raw amount stays', $shaped['amount_due'], 2400 );
check( 'dates read as dates', $shaped['created_at'], '2025-05-17T16:08:18+00:00' );
check( 'the WordPress copy is dropped', isset( $shaped['post'] ), false );
check( 'yen has no pence', blueworx_mcp_surecart_shape( array( 'currency' => 'jpy', 'amount' => 2400 ) )['amount_display'], '¥2,400' );
check( 'an unlisted currency shows its code', blueworx_mcp_surecart_shape( array( 'currency' => 'chf', 'amount' => 2400 ) )['amount_display'], 'CHF 24.00' );
check( 'the passed currency fills a gap', blueworx_mcp_surecart_shape( array( 'amount' => 500 ), 'usd' )['amount_display'], '$5.00' );
check( 'an order links to its screen', blueworx_mcp_surecart_admin_link( 'orders', 'o1' ), 'https://shop.test/wp-admin/admin.php?page=sc-orders&action=edit&id=o1' );
check( 'no id gives the list screen', blueworx_mcp_surecart_admin_link( 'orders' ), 'https://shop.test/wp-admin/admin.php?page=sc-orders' );
check( 'a type with no screen gives no link', blueworx_mcp_surecart_admin_link( 'clicks', 'c1' ), '' );
check( 'an unknown type gives no link', blueworx_mcp_surecart_admin_link( 'nope', 'c1' ), '' );
check( 'every type says if it is personal', count( array_filter( blueworx_mcp_surecart_types(), function ( $t ) { return ! is_bool( $t['personal'] ); } ) ), 0 );
check( 'customers are personal', blueworx_mcp_surecart_types()['customers']['personal'], true );
check( 'products are not', blueworx_mcp_surecart_types()['products']['personal'], false );
check( 'an unknown type counts as personal', blueworx_mcp_surecart_is_personal( 'nope' ), true );
check( 'orders count as personal', blueworx_mcp_surecart_is_personal( 'orders' ), true );
check( 'products do not', blueworx_mcp_surecart_is_personal( 'products' ), false );
check( 'routes sit under surecart/v1', blueworx_mcp_surecart_types()['orders']['route'], '/surecart/v1/orders' );
$GLOBALS['readonly_user'] = (object) array( 'ID' => 9 ); $GLOBALS['data_open'] = false;
check( 'a read-only account with data closed is blocked', blueworx_mcp_surecart_personal_blocked(), true );
$GLOBALS['data_open'] = true;
check( 'and let through once data is opened', blueworx_mcp_surecart_personal_blocked(), false );
$GLOBALS['readonly_user'] = null;
check( 'an administrator is never blocked', blueworx_mcp_surecart_personal_blocked(), false );
check( 'with SureCart absent it is inactive', blueworx_mcp_surecart_active(), false );
check( 'and the refusal says why', blueworx_mcp_surecart_unavailable()->get_error_code(), 'surecart_inactive' );
define( 'SURECART_PLUGIN_FILE', 'x' );
check( 'with SureCart present it is active', blueworx_mcp_surecart_active(), true );
check( 'and nothing refuses', blueworx_mcp_surecart_unavailable(), null );

finish();
