<?php
/**
 * Where a sign-in lands.
 *
 * The rules are pure — a user, a requested destination, and an answer — so they
 * are checked here rather than by driving a browser through a login for each
 * case. The one thing a browser could add, a competing plugin to beat, is
 * reproduced directly: every case passes in a $redirect_to that is NOT the
 * dashboard, which is exactly what LatePoint leaves on the filter.
 *
 * Run with: php tests/php/login-redirect-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

$GLOBALS['hooks'] = array();

/**
 * Whatever the plugin ahead of us in the queue decided. Never the answer.
 */
const RIVAL = 'https://example.test/wp-admin/admin.php?page=latepoint';

/**
 * A WP_User stand-in carrying only what the code under test reads.
 */
class WP_User {
	/**
	 * User ID.
	 *
	 * @var int
	 */
	public $ID;

	/**
	 * Capabilities held, keyed by name.
	 *
	 * @var array
	 */
	public $caps;

	/**
	 * Constructor.
	 *
	 * @param int   $id   User ID.
	 * @param array $caps Capability names held.
	 */
	public function __construct( $id, $caps = array() ) {
		$this->ID   = $id;
		$this->caps = array_fill_keys( $caps, true );
	}
}

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function user_can( $user, $capability ) {
	return $user instanceof WP_User && isset( $user->caps[ $capability ] );
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
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
	return 'login_redirect' === $key;
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/login-redirect.php';

$admin       = new WP_User( 1, array( 'edit_posts', 'manage_options' ) );
$author      = new WP_User( 2, array( 'edit_posts' ) );
$coordinator = new WP_User( 3, array( 'manage_options' ) );
$customer    = new WP_User( 4, array( 'read' ) );

echo "Backend users go to the dashboard\n";

check(
	'an administrator with nothing requested lands on the dashboard, not the rival plugin',
	blueworx_login_redirect_to_dashboard( RIVAL, '', $admin ),
	'https://example.test/wp-admin/'
);

check(
	'an author is a backend user too',
	blueworx_login_redirect_to_dashboard( RIVAL, '', $author ),
	'https://example.test/wp-admin/'
);

check(
	'so is a role that administers without editing content',
	blueworx_login_redirect_to_dashboard( RIVAL, '', $coordinator ),
	'https://example.test/wp-admin/'
);

check(
	'whitespace is not a requested destination',
	blueworx_login_redirect_to_dashboard( RIVAL, '   ', $admin ),
	'https://example.test/wp-admin/'
);

echo "\nA requested destination is honoured\n";

check(
	'a deep link into the admin survives',
	blueworx_login_redirect_to_dashboard( RIVAL, 'https://example.test/wp-admin/options-general.php', $admin ),
	'https://example.test/wp-admin/options-general.php'
);

check(
	'a requested front-end page survives',
	blueworx_login_redirect_to_dashboard( RIVAL, 'https://example.test/members/', $admin ),
	'https://example.test/members/'
);

check(
	'the rival plugin still wins when the person asked for it themselves',
	blueworx_login_redirect_to_dashboard( RIVAL, RIVAL, $admin ),
	RIVAL
);

echo "\nEverybody else is left alone\n";

check(
	'a customer keeps the booking plugin flow',
	blueworx_login_redirect_to_dashboard( RIVAL, '', $customer ),
	RIVAL
);

check(
	'a customer with a requested destination is still not ours to redirect',
	blueworx_login_redirect_to_dashboard( RIVAL, 'https://example.test/my-bookings/', $customer ),
	RIVAL
);

check(
	'a failed sign-in gets its error screen',
	blueworx_login_redirect_to_dashboard( RIVAL, '', new WP_Error( 'incorrect_password' ) ),
	RIVAL
);

check(
	'so does a sign-in that produced no user at all',
	blueworx_login_redirect_to_dashboard( RIVAL, '', null ),
	RIVAL
);

echo "\nThe capability list can be widened\n";

$GLOBALS['filters']['blueworx_login_redirect_capabilities'] = function ( $capabilities, $user ) {
	$capabilities[] = 'manage_bookings';
	return $capabilities;
};

$manager = new WP_User( 5, array( 'manage_bookings' ) );

check(
	'a custom backend role can be named by a site',
	blueworx_login_redirect_to_dashboard( RIVAL, '', $manager ),
	'https://example.test/wp-admin/'
);

check(
	'and widening it does not sweep customers in',
	blueworx_login_redirect_to_dashboard( RIVAL, '', $customer ),
	RIVAL
);

unset( $GLOBALS['filters']['blueworx_login_redirect_capabilities'] );

echo "\nThe hook runs last\n";

check(
	'the filter is registered',
	isset( $GLOBALS['hooks']['login_redirect']['callback'] ) ? $GLOBALS['hooks']['login_redirect']['callback'] : null,
	'blueworx_login_redirect_to_dashboard'
);

check(
	'at a priority that runs after an ordinary plugin',
	$GLOBALS['hooks']['login_redirect']['priority'] > 100,
	true
);

check(
	'taking all three arguments, or the requested destination would be invisible',
	$GLOBALS['hooks']['login_redirect']['args'],
	3
);

finish();
