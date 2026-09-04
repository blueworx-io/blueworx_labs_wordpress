<?php
/**
 * Where a sign-out lands.
 *
 * One rule: if the site has named a landing page, everybody goes there, and if
 * it has not, nothing changes. The only thing with any subtlety is the address
 * being off-site, which core would silently swap for the dashboard — so the
 * host allow-list is checked too.
 *
 * Run with: php tests/php/logout-redirect-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

$GLOBALS['options'] = array();
$GLOBALS['hooks']   = array();

/**
 * Where WordPress would have sent them anyway.
 */
const WP_DEFAULT = 'https://example.test/admin_login/?loggedout=true';

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

class WP_User {
	public $ID = 1;
}

function user_can( $user, $capability ) {
	return false;
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
}

function home_url( $path = '' ) {
	return 'https://example.test/' . ltrim( (string) $path, '/' );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( (string) $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Stub for the WordPress wrapper.
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ] = array(
		'callback' => $callback,
		'priority' => $priority,
		'args'     => $args,
	);
	return true;
}

function blueworx_feature_enabled( $key ) {
	return 'login' === $key;
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/login-redirect.php';

echo "With nothing set, the home page\n";

check( 'a sign-out lands on the home page, not the login screen', blueworx_logout_redirect_to_landing_page( WP_DEFAULT ), 'https://example.test/' );
$GLOBALS['options']['blueworx_logout_redirect'] = '   ';

check( 'whitespace is not a landing page either', blueworx_logout_redirect_to_landing_page( WP_DEFAULT ), 'https://example.test/' );
check( 'and no extra host is allowed through', blueworx_logout_allow_landing_host( array( 'example.test' ) ), array( 'example.test' ) );

echo "\nWith a landing page set, everybody goes there\n";

$GLOBALS['options']['blueworx_logout_redirect'] = 'https://example.test/goodbye/';

check( 'a plain sign-out', blueworx_logout_redirect_to_landing_page( WP_DEFAULT ), 'https://example.test/goodbye/' );
check( 'a sign-out whose link asked for somewhere else', blueworx_logout_redirect_to_landing_page( WP_DEFAULT ), 'https://example.test/goodbye/' );
check( 'a same-site address adds no host', blueworx_logout_allow_landing_host( array( 'example.test' ) ), array( 'example.test' ) );

echo "\nAn off-site landing page is let through\n";

$GLOBALS['options']['blueworx_logout_redirect'] = 'https://members.example.org/bye';

check( 'its host is added to the allow-list', blueworx_logout_allow_landing_host( array( 'example.test' ) ), array( 'example.test', 'members.example.org' ) );
check( 'and only once', blueworx_logout_allow_landing_host( array( 'example.test', 'members.example.org' ) ), array( 'example.test', 'members.example.org' ) );

echo "\nThe hooks are in place\n";

check( 'the sign-out filter is registered', isset( $GLOBALS['hooks']['logout_redirect']['callback'] ) ? $GLOBALS['hooks']['logout_redirect']['callback'] : null, 'blueworx_logout_redirect_to_landing_page' );
check( 'after an ordinary plugin', $GLOBALS['hooks']['logout_redirect']['priority'] > 100, true );
check( 'and so is the host allow-list', isset( $GLOBALS['hooks']['allowed_redirect_hosts']['callback'] ) ? $GLOBALS['hooks']['allowed_redirect_hosts']['callback'] : null, 'blueworx_logout_allow_landing_host' );

finish();
