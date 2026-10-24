<?php
/**
 * Guest viewer and support accounts on a network.
 *
 * Accounts are network-wide on a multisite while roles, options and the support
 * window are per site, so taking an account off one site is not the same as
 * deleting it. These checks run the real support and guest viewer code against
 * a small in-memory network: three sites, per-site options and roles, and
 * accounts that can belong to any of them. A single-site pass runs first, to
 * prove that path is unchanged.
 *
 * Run with: php tests/php/multisite-accounts-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Same: unused arguments are part of the signatures.
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Thrown by the wp_die() stub, so a refusal can be seen and the script carry on.
 */
class Blueworx_Test_Died extends Exception {}

class WP_User {
	public $ID = 0;
	public $roles = array();
	public $user_login = '';
	public $user_email = '';
	public $display_name = '';
	public $user_activation_key = '';

	public function __construct( $id = 0, $roles = array() ) {
		$this->ID    = (int) $id;
		$this->roles = $roles;
	}

	public function exists() {
		return $this->ID > 0;
	}

	public function set_role( $role ) {
		$GLOBALS['users'][ $this->ID ]['blogs'][ get_current_blog_id() ] = $role;
		$this->roles = array( $role );
	}
}

// --- The network ---

$GLOBALS['multisite']    = false;
$GLOBALS['blog_id']      = 1;
$GLOBALS['blog_stack']   = array();
$GLOBALS['sites']        = array( 1, 2, 3 );
$GLOBALS['blog_options'] = array();
$GLOBALS['blog_roles']   = array();
$GLOBALS['super_admins'] = array( 'network-admin' );
$GLOBALS['users']        = array();
$GLOBALS['next_id']      = 100;
$GLOBALS['current_user'] = 0;
$GLOBALS['events']       = array();
$GLOBALS['wpmu_deleted'] = array();

/**
 * Puts the network back to a known state between scenarios.
 *
 * @param bool $multisite Whether this scenario is a network.
 * @return void
 */
function reset_network( $multisite ) {
	$GLOBALS['multisite']    = $multisite;
	$GLOBALS['blog_id']      = 1;
	$GLOBALS['blog_stack']   = array();
	$GLOBALS['options']      = array();
	$GLOBALS['blog_options'] = array();
	$GLOBALS['blog_roles']   = array();
	$GLOBALS['current_user'] = 0;
	$GLOBALS['events']       = array();
	$GLOBALS['wpmu_deleted'] = array();
	$GLOBALS['users']        = array(
		1 => array(
			'id'    => 1,
			'login' => 'admin',
			'email' => 'admin@example.com',
			'name'  => 'Luke',
			'blogs' => array( 1 => 'administrator' ),
			'meta'  => array(),
		),
		2 => array(
			'id'    => 2,
			'login' => 'network-admin',
			'email' => 'boss@example.com',
			'name'  => 'Boss',
			'blogs' => array(),
			'meta'  => array(),
		),
	);
}

/**
 * Adds an account straight to the store.
 *
 * @param string $login Username.
 * @param string $email Email address.
 * @param array  $blogs Site ID => role.
 * @return int User ID.
 */
function add_test_user( $login, $email, $blogs ) {
	$id = $GLOBALS['next_id']++;

	$GLOBALS['users'][ $id ] = array(
		'id'    => $id,
		'login' => $login,
		'email' => $email,
		'name'  => $login,
		'blogs' => $blogs,
		'meta'  => array(),
	);

	return $id;
}

/**
 * Runs a callback as if on another site, the way switch_to_blog() does.
 *
 * @param int      $blog_id  Site ID.
 * @param callable $callback What to run.
 * @return mixed What the callback returned.
 */
function on_site( $blog_id, $callback ) {
	switch_to_blog( $blog_id );

	try {
		return $callback();
	} finally {
		restore_current_blog();
	}
}

/**
 * Whether an account still exists on the network at all.
 *
 * @param int $id User ID.
 * @return bool
 */
function account_exists( $id ) {
	return isset( $GLOBALS['users'][ $id ] );
}

/**
 * The sites an account belongs to.
 *
 * @param int $id User ID.
 * @return array Site IDs.
 */
function sites_of( $id ) {
	return isset( $GLOBALS['users'][ $id ] ) ? array_keys( $GLOBALS['users'][ $id ]['blogs'] ) : array();
}

function is_multisite() {
	return $GLOBALS['multisite'];
}

function get_current_blog_id() {
	return $GLOBALS['blog_id'];
}

function switch_to_blog( $blog_id ) {
	$GLOBALS['blog_options'][ $GLOBALS['blog_id'] ] = $GLOBALS['options'];
	$GLOBALS['blog_stack'][]                        = $GLOBALS['blog_id'];
	$GLOBALS['blog_id']                             = (int) $blog_id;
	$GLOBALS['options']                             = isset( $GLOBALS['blog_options'][ $blog_id ] ) ? $GLOBALS['blog_options'][ $blog_id ] : array();

	return true;
}

function restore_current_blog() {
	if ( empty( $GLOBALS['blog_stack'] ) ) {
		return false;
	}

	$GLOBALS['blog_options'][ $GLOBALS['blog_id'] ] = $GLOBALS['options'];
	$GLOBALS['blog_id']                             = array_pop( $GLOBALS['blog_stack'] );
	$GLOBALS['options']                             = isset( $GLOBALS['blog_options'][ $GLOBALS['blog_id'] ] ) ? $GLOBALS['blog_options'][ $GLOBALS['blog_id'] ] : array();

	return true;
}

function get_sites( $args = array() ) {
	$GLOBALS['get_sites_args'] = $args;

	return $GLOBALS['sites'];
}

function delete_option( $key ) {
	unset( $GLOBALS['options'][ $key ] );

	return true;
}

function delete_metadata( $type, $object_id, $key, $value = '', $all = false ) {
	foreach ( $GLOBALS['users'] as $id => $row ) {
		unset( $GLOBALS['users'][ $id ]['meta'][ $key ] );
	}

	return true;
}

function add_role( $slug, $name, $caps ) {
	$GLOBALS['blog_roles'][ get_current_blog_id() ][ $slug ] = $caps;
}

function remove_role( $slug ) {
	unset( $GLOBALS['blog_roles'][ get_current_blog_id() ][ $slug ] );
}

function get_role( $slug ) {
	return isset( $GLOBALS['blog_roles'][ get_current_blog_id() ][ $slug ] ) ? (object) array( 'name' => $slug ) : null;
}

function is_super_admin( $id = 0 ) {
	return isset( $GLOBALS['users'][ $id ] ) && in_array( $GLOBALS['users'][ $id ]['login'], $GLOBALS['super_admins'], true );
}

function get_userdata( $id ) {
	if ( ! isset( $GLOBALS['users'][ $id ] ) ) {
		return false;
	}

	$row  = $GLOBALS['users'][ $id ];
	$here = get_current_blog_id();
	$user = new WP_User( $row['id'], isset( $row['blogs'][ $here ] ) ? array( $row['blogs'][ $here ] ) : array() );

	$user->user_login          = $row['login'];
	$user->user_email          = $row['email'];
	$user->display_name        = $row['name'];
	$user->user_activation_key = isset( $row['activation_key'] ) ? $row['activation_key'] : '';

	return $user;
}

function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $row ) {
		if ( 'login' === $field && $row['login'] === $value ) {
			return get_userdata( $row['id'] );
		}
	}

	return false;
}

function username_exists( $name ) {
	$user = get_user_by( 'login', $name );

	return $user ? $user->ID : false;
}

function email_exists( $email ) {
	foreach ( $GLOBALS['users'] as $row ) {
		if ( $row['email'] === $email ) {
			return $row['id'];
		}
	}

	return false;
}

function wp_insert_user( $data ) {
	$id = add_test_user( $data['user_login'], $data['user_email'], array( get_current_blog_id() => $data['role'] ) );

	$GLOBALS['users'][ $id ]['name'] = $data['display_name'];

	return $id;
}

/**
 * Mirrors core: on a network it only takes the account off the current site.
 *
 * @param int $id User ID.
 * @return bool
 */
function wp_delete_user( $id ) {
	if ( ! isset( $GLOBALS['users'][ $id ] ) ) {
		return false;
	}

	if ( is_multisite() ) {
		unset( $GLOBALS['users'][ $id ]['blogs'][ get_current_blog_id() ] );
	} else {
		unset( $GLOBALS['users'][ $id ] );
	}

	return true;
}

/**
 * Mirrors core: refuses a super admin, otherwise deletes the account outright.
 *
 * @param int $id User ID.
 * @return bool
 */
function wpmu_delete_user( $id ) {
	if ( ! isset( $GLOBALS['users'][ $id ] ) || is_super_admin( $id ) ) {
		return false;
	}

	$GLOBALS['wpmu_deleted'][] = (int) $id;
	unset( $GLOBALS['users'][ $id ] );

	return true;
}

function get_blogs_of_user( $id, $all = false ) {
	$blogs = array();

	foreach ( sites_of( $id ) as $blog_id ) {
		$blogs[ $blog_id ] = (object) array( 'userblog_id' => $blog_id );
	}

	return $blogs;
}

function is_user_member_of_blog( $id = 0, $blog_id = 0 ) {
	$blog_id = $blog_id ? $blog_id : get_current_blog_id();

	return in_array( (int) $blog_id, sites_of( $id ), true );
}

function add_user_to_blog( $blog_id, $id, $role ) {
	$GLOBALS['users'][ $id ]['blogs'][ (int) $blog_id ] = $role;

	return true;
}

function remove_user_from_blog( $id, $blog_id = 0 ) {
	unset( $GLOBALS['users'][ $id ]['blogs'][ $blog_id ? (int) $blog_id : get_current_blog_id() ] );

	return true;
}

function get_users( $args = array() ) {
	$found = array();

	foreach ( $GLOBALS['users'] as $row ) {
		$user = get_userdata( $row['id'] );

		if ( isset( $args['role'] ) && ! in_array( $args['role'], $user->roles, true ) ) {
			continue;
		}

		$found[] = ( isset( $args['fields'] ) && 'ID' === $args['fields'] ) ? $user->ID : $user;
	}

	return isset( $args['number'] ) ? array_slice( $found, 0, $args['number'] ) : $found;
}

function update_user_meta( $id, $key, $value ) {
	$GLOBALS['users'][ $id ]['meta'][ $key ] = $value;

	return true;
}

function get_user_meta( $id, $key, $single = false ) {
	return isset( $GLOBALS['users'][ $id ]['meta'][ $key ] ) ? $GLOBALS['users'][ $id ]['meta'][ $key ] : '';
}

function clean_user_cache( $id ) {}

/**
 * The one wpdb call support-access.php makes: the unusable password.
 */
class Blueworx_Test_Wpdb {
	public $users = 'wp_users';

	public function update( $table, $data, $where ) {
		return 1;
	}
}

$GLOBALS['wpdb'] = new Blueworx_Test_Wpdb();

function wp_get_current_user() {
	$user = get_userdata( $GLOBALS['current_user'] );

	return $user ? $user : new WP_User( 0 );
}

function get_current_user_id() {
	return $GLOBALS['current_user'];
}

function wp_set_current_user( $id ) {
	$GLOBALS['current_user'] = (int) $id;
}

function wp_destroy_current_session() {
	$GLOBALS['events'][] = 'session destroyed';
}

function wp_clear_auth_cookie() {
	$GLOBALS['events'][] = 'cookie cleared';
}

function wp_die( $message = '', $title = '', $args = array() ) {
	throw new Blueworx_Test_Died( (string) $message );
}

function blueworx_feature_enabled( $key ) {
	return '0' !== get_option( 'blueworx_feature_' . $key, '1' );
}

function blueworx_readonly_build_caps() {
	return array(
		'read'           => true,
		'manage_options' => true,
	);
}

function blueworx_readonly_user_has_role( $user, $slug ) {
	return $user instanceof WP_User && in_array( $slug, (array) $user->roles, true );
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
}

function wp_unslash( $value ) {
	return $value;
}

function esc_html__( $text, $domain = '' ) {
	return $text;
}

function sanitize_email( $email ) {
	return trim( (string) $email );
}

function wp_mail( $to, $subject, $message ) {
	$GLOBALS['mail_sent'][] = $to;

	return true;
}

function get_password_reset_key( $user ) {
	return 'RESETKEY';
}

function network_site_url( $path = '', $scheme = null ) {
	return 'https://network.example.com/' . ltrim( (string) $path, '/' );
}

function get_bloginfo( $what ) {
	return 'Site ' . get_current_blog_id();
}

function wp_specialchars_decode( $text, $quotes = 0 ) {
	return $text;
}

function date_i18n( $format, $time ) {
	return gmdate( 'j F Y', (int) $time );
}

/**
 * A theme, by folder. Only "blueworx" carries the plugin's text domain.
 */
class Test_Theme {
	/**
	 * The theme's folder.
	 *
	 * @var string
	 */
	private $folder;

	public function __construct( $folder ) {
		$this->folder = $folder;
	}

	public function exists() {
		return '' !== $this->folder;
	}

	public function get( $header ) {
		return 'TextDomain' === $header && 'blueworx' === $this->folder ? 'blueworx-labs-wordpress' : '';
	}
}

function wp_get_theme( $stylesheet = '' ) {
	return new Test_Theme( $stylesheet );
}

require_once __DIR__ . '/../../includes/support-access.php';
require_once __DIR__ . '/../../includes/external-access.php';

/**
 * Invites a guest viewer for a week on the current site.
 *
 * @param string $email Email address.
 * @return int|WP_Error New or added user ID, or the refusal.
 */
function invite_viewer( $email ) {
	return blueworx_external_invite(
		array(
			'email' => $email,
			'days'  => 7,
		)
	);
}

/**
 * Reads one option on another site.
 *
 * @param int    $blog_id Site ID.
 * @param string $key     Option name.
 * @return mixed The value, or 'none' when it is not set.
 */
function option_on( $blog_id, $key ) {
	return on_site(
		$blog_id,
		function () use ( $key ) {
			return get_option( $key, 'none' );
		}
	);
}

/**
 * The guest viewer role on the current site, if it is still registered.
 *
 * @return object|null
 */
function external_role_here() {
	return get_role( 'blueworx_external' );
}

/**
 * Provisions the support account on a site and opens or shuts its window.
 *
 * @param int  $blog_id Site ID.
 * @param bool $open    Whether that site's window is open.
 * @return int Support account ID.
 */
function give_support( $blog_id, $open ) {
	return on_site(
		$blog_id,
		function () use ( $open ) {
			blueworx_support_generate_key();
			$id = blueworx_support_ensure_account();

			if ( $open ) {
				blueworx_support_open_access( false );
			}

			return $id;
		}
	);
}

/**
 * Runs the support window check as the current user, reporting what happened.
 *
 * @return array Events, plus "refused" when the request was turned away.
 */
function run_window_check() {
	$GLOBALS['events'] = array();

	try {
		blueworx_support_enforce_window();
	} catch ( Blueworx_Test_Died $died ) {
		$GLOBALS['events'][] = 'refused';
	}

	return $GLOBALS['events'];
}

$GLOBALS['mail_sent'] = array();

// -----------------------------------------------------------------------------
echo "On a single site, nothing changes\n";

reset_network( false );

$viewer = invite_viewer( 'jane@example.com' );

check( 'a viewer is invited', is_int( $viewer ), true );
check( 'revoking them succeeds', blueworx_external_revoke( $viewer ), true );
check( 'and the account is deleted', account_exists( $viewer ), false );
check( 'without the network delete, which a single site does not have', $GLOBALS['wpmu_deleted'], array() );
check( 'an address already in use is still refused', is_wp_error( blueworx_external_invite( array( 'email' => 'admin@example.com' ) ) ), true );

$support = give_support( 1, false );

blueworx_support_remove_account();

check( 'the support account is deleted', account_exists( $support ), false );

$support = give_support( 1, false );
$GLOBALS['current_user'] = $support;

check( 'a shut window still ends the session and clears the cookie', run_window_check(), array( 'session destroyed', 'cookie cleared', 'refused' ) );

$GLOBALS['current_user'] = 0;
$viewer                  = invite_viewer( 'sam@example.com' );

blueworx_labs_test_deactivate( false );

check( 'deactivating deletes the support account', account_exists( $support ), false );
check( 'and the viewer', account_exists( $viewer ), false );

// -----------------------------------------------------------------------------
echo "\nOn a network, a deleted guest viewer is gone everywhere (#226)\n";

reset_network( true );

$viewer = invite_viewer( 'jane@example.com' );

check( 'a viewer is invited to site 1', sites_of( $viewer ), array( 1 ) );
check( 'revoking them succeeds', blueworx_external_revoke( $viewer ), true );
check( 'and the network login is deleted, not just taken off the site', account_exists( $viewer ), false );

$again = invite_viewer( 'jane@example.com' );

check( 'the same address can be invited again', is_int( $again ), true );

echo "\nAn account still used on another site is only taken off this one\n";

$editor = add_test_user( 'ed', 'ed@example.com', array( 2 => 'editor' ) );
$added  = invite_viewer( 'ed@example.com' );

check( 'inviting a network user from another site adds them here', $added, $editor );
check( 'as a viewer on this site', $GLOBALS['users'][ $editor ]['blogs'][1], 'blueworx_external' );
check( 'with their role on the other site untouched', $GLOBALS['users'][ $editor ]['blogs'][2], 'editor' );
check( 'and an expiry written', blueworx_external_expires_at( $editor ) > time(), true );
check( 'and the invitation sent', end( $GLOBALS['mail_sent'] ), 'ed@example.com' );

check( 'revoking them succeeds', blueworx_external_revoke( $editor ), true );
check( 'they are no longer on this site', sites_of( $editor ), array( 2 ) );
check( 'but their account on the other site survives', account_exists( $editor ), true );

echo "\nRe-inviting is still refused when it should be\n";

$member = invite_viewer( 'jane@example.com' );

check( 'somebody already on this site is refused', is_wp_error( $member ), true );
check( 'a super admin is refused', is_wp_error( blueworx_external_invite( array( 'email' => 'boss@example.com' ) ) ), true );
check( 'and is not made a viewer', sites_of( 2 ), array() );

$orphan = add_test_user( 'old', 'old@example.com', array() );

check( 'a network user on no site at all is added back', invite_viewer( 'old@example.com' ), $orphan );

echo "\nThe support account is removed from the network once no site uses it\n";

reset_network( true );

$support = give_support( 1, false );

give_support( 2, false );

check( 'one account serves both sites', sites_of( $support ), array( 1, 2 ) );

on_site( 1, 'blueworx_support_remove_account' );

check( 'revoking on site 1 leaves it for site 2', sites_of( $support ), array( 2 ) );

on_site( 2, 'blueworx_support_remove_account' );

check( 'revoking on the last site deletes it from the network', account_exists( $support ), false );

// -----------------------------------------------------------------------------
echo "\nClosing the window on one site does not log support out of another (#235)\n";

reset_network( true );

$support = give_support( 1, false );

give_support( 2, true );

$GLOBALS['current_user'] = $support;

check( 'site 1 still refuses the request, without ending the shared session', run_window_check(), array( 'refused' ) );
check( 'and leaves the account on site 2 alone', sites_of( $support ), array( 1, 2 ) );
check( 'and the check ran from site 1 and came back to it', get_current_blog_id(), 1 );

on_site( 2, 'blueworx_support_close_access' );

$GLOBALS['current_user'] = $support;

check( 'with no window open anywhere, the session is ended as before', run_window_check(), array( 'session destroyed', 'cookie cleared', 'refused' ) );

// -----------------------------------------------------------------------------
echo "\nNetwork deactivation cleans up every site (#225)\n";

reset_network( true );

$support = give_support( 1, true );

give_support( 3, false );

$viewer_one   = invite_viewer( 'a@example.com' );
$viewer_two   = on_site(
	2,
	function () {
		return invite_viewer( 'b@example.com' );
	}
);
$viewer_three = on_site(
	3,
	function () {
		return invite_viewer( 'c@example.com' );
	}
);

blueworx_labs_test_deactivate( true );

check( 'the support account is gone', account_exists( $support ), false );
check( 'the viewer on site 1 is gone', account_exists( $viewer_one ), false );
check( 'the viewer on site 2 is gone', account_exists( $viewer_two ), false );
check( 'the viewer on site 3 is gone', account_exists( $viewer_three ), false );
check( 'site 3 has no support key left', on_site( 3, 'blueworx_support_has_key' ), false );
check( 'every site was asked for, not just the first page', isset( $GLOBALS['get_sites_args']['number'] ) && 0 === $GLOBALS['get_sites_args']['number'], true );
check( 'and the network is back on the site it started on', array( get_current_blog_id(), $GLOBALS['blog_stack'] ), array( 1, array() ) );

echo "\nA failure on one site still puts the network back\n";

reset_network( true );

try {
	blueworx_for_each_site(
		function () {
			if ( 2 === get_current_blog_id() ) {
				throw new RuntimeException( 'site 2 broke' );
			}
		},
		true
	);
} catch ( RuntimeException $broke ) {
	unset( $broke );
}

check( 'the current site is restored', array( get_current_blog_id(), $GLOBALS['blog_stack'] ), array( 1, array() ) );

echo "\nDeactivating on one site of a network leaves the others alone\n";

reset_network( true );

$support = give_support( 1, false );

give_support( 2, false );

$elsewhere = on_site(
	2,
	function () {
		return invite_viewer( 'b@example.com' );
	}
);

blueworx_labs_test_deactivate( false );

check( 'support stays for site 2', sites_of( $support ), array( 2 ) );
check( 'and so does site 2\'s viewer', sites_of( $elsewhere ), array( 2 ) );

// -----------------------------------------------------------------------------
echo "\nA site already on the BlueWorx theme keeps its accounts\n";

reset_network( false );
update_option( 'template', 'blueworx' );

$support = give_support( 1, false );
$viewer  = invite_viewer( 'kept@example.com' );

blueworx_labs_test_deactivate( false );

check( 'deactivating keeps the support account', account_exists( $support ), true );
check( 'and its key', blueworx_support_has_key(), true );
check( 'and the viewer', account_exists( $viewer ), true );

update_option( 'template', 'twentytwentyfive' );
blueworx_labs_test_deactivate( false );

check( 'any other theme still has them removed', array( account_exists( $support ), account_exists( $viewer ) ), array( false, false ) );

reset_network( true );

$viewer_one = invite_viewer( 'one@example.com' );
$viewer_two = on_site(
	2,
	function () {
		update_option( 'template', 'blueworx' );
		return invite_viewer( 'two@example.com' );
	}
);

blueworx_labs_test_deactivate( true );

check( 'on a network, a site on the theme keeps its viewer', account_exists( $viewer_two ), true );
check( 'while a site without it loses theirs', account_exists( $viewer_one ), false );

// -----------------------------------------------------------------------------
echo "\nDeleting the plugin from a network cleans every site (#234)\n";

reset_network( true );

$support = give_support( 1, false );

give_support( 2, true );

$left_behind = on_site(
	3,
	function () {
		update_option( 'blueworx_tracking_ga', 'G-123' );
		return invite_viewer( 'left@example.com' );
	}
);

// A fourth site has already moved to the theme, which still uses all of it.
$GLOBALS['sites'] = array( 1, 2, 3, 4 );
$on_theme         = on_site(
	4,
	function () {
		update_option( 'template', 'blueworx' );
		update_option( 'blueworx_tracking_ga', 'G-456' );
		return invite_viewer( 'theme@example.com' );
	}
);

define( 'WP_UNINSTALL_PLUGIN', 'blueworx-labs-wordpress/blueworx-labs-wordpress.php' );
require __DIR__ . '/../../uninstall.php';

check( 'a site on the theme keeps its settings', option_on( 4, 'blueworx_tracking_ga' ), 'G-456' );
check( 'and its viewer', account_exists( $on_theme ), true );
check( 'and the viewer keeps their expiry', blueworx_external_expires_at( $on_theme ) > time(), true );

check( 'the support account is deleted from the network', account_exists( $support ), false );
check( 'site 2\'s support key is gone', on_site( 2, 'blueworx_support_has_key' ), false );
check( 'site 2\'s window option is gone', option_on( 2, 'blueworx_support_access_until' ), 'none' );
check( 'site 3\'s settings are gone', option_on( 3, 'blueworx_tracking_ga' ), 'none' );
check( 'a viewer left on a site nobody deactivated is deleted', account_exists( $left_behind ), false );
check( 'the external role is gone from site 3', on_site( 3, 'external_role_here' ), null );
check( 'and the network is back on the site it started on', array( get_current_blog_id(), $GLOBALS['blog_stack'] ), array( 1, array() ) );

finish();

/**
 * Runs the plugin's deactivation the way blueworx-labs-wordpress.php does.
 *
 * The main plugin file loads every other include, so it cannot be required
 * here. Its one line is mirrored instead: the real per-site work and the real
 * network loop are what is under test.
 *
 * @param bool $network_wide Whether this is a network deactivation.
 * @return void
 */
function blueworx_labs_test_deactivate( $network_wide ) {
	blueworx_for_each_site( 'blueworx_labs_deactivate_site', $network_wide );
}
