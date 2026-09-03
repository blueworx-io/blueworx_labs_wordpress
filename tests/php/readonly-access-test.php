<?php
/**
 * Which accounts the read-only guard applies to, and what it strips.
 *
 * The guard is what makes both BlueWorx support access and BlueWorx: External
 * read-only. Its rules are pure — a user and a role table in, a decision out —
 * so they are checked here rather than by driving a browser. That the block
 * actually fires on a live request is covered by tests/support-access.spec.js
 * and tests/external-readonly.spec.js.
 *
 * Run with: php tests/php/readonly-access-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Same: the text domain is part of the signature.

/** The administrator role this pretend site clones from. */
$GLOBALS['roles'] = array(
	'administrator' => array(
		'name'         => 'Administrator',
		'capabilities' => array(
			'read'             => true,
			'edit_posts'       => true,
			'delete_posts'     => true,
			'manage_options'   => true,
			'install_plugins'  => true,
			'unfiltered_html'  => true,
			'edit_users'       => true,
			'promote_users'    => true,
			'manage_woocommerce' => true,
		),
	),
);

class WP_User {
	public $ID = 0;
	public $roles = array();
	public $user_login = '';
	private $exists = true;

	public function __construct( $id = 0, $roles = array(), $login = '' ) {
		$this->ID         = $id;
		$this->roles      = $roles;
		$this->user_login = $login;
	}

	public function exists() {
		return $this->exists;
	}
}

class WP_Role {
	public $capabilities = array();

	public function __construct( $capabilities ) {
		$this->capabilities = $capabilities;
	}
}

function get_role( $slug ) {
	return isset( $GLOBALS['roles'][ $slug ] )
		? new WP_Role( $GLOBALS['roles'][ $slug ]['capabilities'] )
		: null;
}

function wp_get_current_user() {
	return isset( $GLOBALS['current_user'] ) ? $GLOBALS['current_user'] : new WP_User( 0, array() );
}

function get_current_user_id() {
	$user = wp_get_current_user();

	return (int) $user->ID;
}

// The support half of the pair, which the guard asks about by name.
function blueworx_support_role_slug() {
	return 'blueworx_support';
}

function blueworx_support_data_open() {
	return ! empty( $GLOBALS['support_data_open'] );
}

function blueworx_support_log_event( $type ) {
	$GLOBALS['support_log'][] = $type;
}

function blueworx_external_role_slug() {
	return 'blueworx_external';
}

// The guard registers its hooks at file scope; only their existence matters
// here, not what they do, since nothing in this script fires a hook.
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

require __DIR__ . '/../../includes/readonly-access.php';

echo "Who the guard applies to\n";

$GLOBALS['current_user'] = new WP_User( 1, array( 'administrator' ), 'luke' );
check( 'an administrator is not read-only', null === blueworx_readonly_current_user(), true );

$GLOBALS['current_user'] = new WP_User( 2, array( 'blueworx_support' ), 'blueworx_support' );
check( 'the support account is', blueworx_readonly_current_user() instanceof WP_User, true );

$GLOBALS['current_user'] = new WP_User( 3, array( 'blueworx_external' ), 'client' );
check( 'and an external viewer is', blueworx_readonly_current_user() instanceof WP_User, true );

$GLOBALS['current_user'] = new WP_User( 0, array() );
check( 'a signed-out visitor is not', null === blueworx_readonly_current_user(), true );

echo "\nWhat the cloned role loses\n";

$caps = blueworx_readonly_build_caps();

check( 'installing plugins is gone', isset( $caps['install_plugins'] ), false );
check( 'raw HTML is gone', isset( $caps['unfiltered_html'] ), false );
check( 'managing users is gone', isset( $caps['edit_users'] ), false );
check( 'promoting users is gone', isset( $caps['promote_users'] ), false );
check( 'deleting posts is gone', isset( $caps['delete_posts'] ), false );
check( 'but reading the settings screens is kept', ! empty( $caps['manage_options'] ), true );
check( 'and whatever the shop added is kept', ! empty( $caps['manage_woocommerce'] ), true );
check( 'and read is always present', ! empty( $caps['read'] ), true );

echo "\nWho may see personal data\n";

$support  = new WP_User( 2, array( 'blueworx_support' ), 'blueworx_support' );
$external = new WP_User( 3, array( 'blueworx_external' ), 'client' );

$GLOBALS['support_data_open'] = false;
check( 'support cannot, with the switch off', blueworx_readonly_data_allowed( $support ), false );

$GLOBALS['support_data_open'] = true;
check( 'support can, with the switch on', blueworx_readonly_data_allowed( $support ), true );
check( 'an external viewer never can', blueworx_readonly_data_allowed( $external ), false );

finish();
