<?php
/**
 * The custom login and Site Protection on a multisite network.
 *
 * Three things go wrong on a network that never show on a single site: a super
 * admin with no role on a site gets locked out of it, a subsite's admin at
 * /site2/wp-admin slips past the backend gate, and the welcome email for a new
 * subsite links to the wp-login.php the plugin blocks. Each is checked here,
 * along with the single-site behaviour that must not move.
 *
 * Run with: php tests/php/login-security-multisite-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable WordPress.Files.FileName.InvalidClassFileName
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

require __DIR__ . '/stubs.php';

define( 'BLUEWORX_CUSTOM_LOGIN_SLUG', 'admin_login' );
define( 'BLUEWORX_LABS_PLUGIN_FILE', '/plugins/blueworx-labs-wordpress/blueworx-labs-wordpress.php' );

/**
 * Thrown where the real code would redirect or die, so a request can be traced.
 */
class Request_Stopped extends Exception {}

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
	 * Roles held on the current site.
	 *
	 * @var array
	 */
	public $roles;

	/**
	 * Constructor.
	 *
	 * @param int   $id    User ID.
	 * @param array $roles Role slugs.
	 */
	public function __construct( $id, $roles = array() ) {
		$this->ID    = $id;
		$this->roles = $roles;
	}
}

$GLOBALS['hooks']             = array();
$GLOBALS['multisite']         = true;
$GLOBALS['test_super_admins'] = array( 1 );
$GLOBALS['test_user']         = new WP_User( 0 );
$GLOBALS['site_path']         = '/';
$GLOBALS['home']              = 'https://example.test';
$GLOBALS['site_options']      = array(
	'active_sitewide_plugins' => array( 'blueworx-labs-wordpress/blueworx-labs-wordpress.php' => 1 ),
);
$GLOBALS['blogs']             = array(
	2 => array(
		'address' => 'https://example.test/site2/',
		'home'    => 'https://example.test/site2',
		'options' => array(),
	),
);
$GLOBALS['blog_stack']        = array();

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ] = array(
		'callback' => $callback,
		'args'     => $args,
	);
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	return add_action( $hook, $callback, $priority, $args );
}

function blueworx_feature_enabled( $key ) {
	return '0' !== get_option( 'blueworx_feature_' . $key, '1' );
}

function is_multisite() {
	return $GLOBALS['multisite'];
}

function is_super_admin( $user_id = false ) {
	return in_array( (int) $user_id, $GLOBALS['test_super_admins'], true );
}

function wp_get_current_user() {
	return $GLOBALS['test_user'];
}

function is_user_logged_in() {
	return 0 !== $GLOBALS['test_user']->ID;
}

function get_site( $site = null ) {
	return (object) array( 'path' => $GLOBALS['site_path'] );
}

function home_url( $path = '' ) {
	return $GLOBALS['home'] . '/' . ltrim( (string) $path, '/' );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
}

function sanitize_title( $title ) {
	return trim( preg_replace( '/[^a-z0-9_\-]+/', '-', strtolower( (string) $title ) ), '-' );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function wp_unslash( $value ) {
	return $value;
}

function esc_html__( $text, $domain = '' ) {
	return $text;
}

function wp_die( $message = '' ) {
	throw new Request_Stopped( 'die: ' . $message );
}

function blueworx_redirect_home() {
	throw new Request_Stopped( 'home' );
}

function add_query_arg( $key, $value, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value;
}

function get_site_option( $key, $fallback = false ) {
	return array_key_exists( $key, $GLOBALS['site_options'] ) ? $GLOBALS['site_options'][ $key ] : $fallback;
}

function plugin_basename( $file ) {
	return 'blueworx-labs-wordpress/blueworx-labs-wordpress.php';
}

function get_blogaddress_by_id( $blog_id ) {
	return $GLOBALS['blogs'][ $blog_id ]['address'];
}

function switch_to_blog( $blog_id ) {
	$GLOBALS['blog_stack'][] = array( $GLOBALS['home'], $GLOBALS['options'] );
	$GLOBALS['home']         = $GLOBALS['blogs'][ $blog_id ]['home'];
	$GLOBALS['options']      = $GLOBALS['blogs'][ $blog_id ]['options'];
	return true;
}

function restore_current_blog() {
	list( $GLOBALS['home'], $GLOBALS['options'] ) = array_pop( $GLOBALS['blog_stack'] );
	return true;
}

require dirname( __DIR__, 2 ) . '/includes/login-security.php';

/**
 * Runs the request gate for a path and reports where the visitor ended up.
 *
 * @param string $uri Request URI.
 * @return string 'home', 'die: <message>', or 'through'.
 */
function visit( $uri ) {
	$_SERVER['REQUEST_URI'] = $uri;
	$_SERVER['SCRIPT_NAME'] = '/index.php';

	try {
		blueworx_intercept_requests();
	} catch ( Request_Stopped $stopped ) {
		return $stopped->getMessage();
	}

	return 'through';
}

/**
 * Puts the test on a given site of the network.
 *
 * @param string $site_path The site's path, e.g. '/site2/'.
 * @return void
 */
function on_site( $site_path ) {
	$GLOBALS['site_path'] = $site_path;
	$GLOBALS['home']      = 'https://example.test' . rtrim( $site_path, '/' );
}

$login_please = 'die: Please log in to view this site.';
$no_access    = 'die: You do not have access to view this area.';

echo "Super admins get through Site Protection (#227)\n";

$GLOBALS['options'] = array(
	'blueworx_backend_protection_enabled'  => '1',
	'blueworx_frontend_protection_enabled' => '1',
	'blueworx_backend_protection_roles'    => array( 'administrator' ),
	'blueworx_frontend_protection_roles'   => array( 'administrator' ),
);

$GLOBALS['test_user'] = new WP_User( 1 );

check( 'a super admin with no role on this site passes the backend check', blueworx_current_user_has_site_protection_role( 'backend' ), true );
check( 'and the frontend check', blueworx_current_user_has_site_protection_role( 'frontend' ), true );
check( 'so the network admin stays open to them', visit( '/wp-admin/network/' ), 'through' );

$GLOBALS['test_user'] = new WP_User( 5 );

check( 'an ordinary user with no role is still turned away', blueworx_current_user_has_site_protection_role( 'backend' ), false );

$GLOBALS['test_user'] = new WP_User( 6, array( 'subscriber' ) );

check( 'and so is one whose role is not on the list', blueworx_current_user_has_site_protection_role( 'backend' ), false );

$GLOBALS['multisite'] = false;
$GLOBALS['test_user'] = new WP_User( 1 );

check( 'on a single site nothing changes: no role still fails', blueworx_current_user_has_site_protection_role( 'backend' ), false );

$GLOBALS['multisite'] = true;

echo "\nA subsite's admin area is gated like the main site's (#229)\n";

on_site( '/site2/' );

check( 'the site path is stripped from a subsite request', blueworx_path_within_site( '/site2/wp-admin' ), '/wp-admin' );
check( 'the site root becomes /', blueworx_path_within_site( '/site2' ), '/' );
check( 'a longer folder name is not mistaken for this site', blueworx_path_within_site( '/site20/wp-admin' ), '/site20/wp-admin' );

$GLOBALS['test_user'] = new WP_User( 0 );
$GLOBALS['options']   = array(
	'blueworx_backend_protection_enabled' => '1',
	'blueworx_backend_protection_roles'   => array( 'administrator' ),
);

check( 'logged out at /site2/wp-admin with Site Protection on: asked to log in', visit( '/site2/wp-admin/' ), $login_please );

$GLOBALS['options'] = array( 'blueworx_feature_site_protection' => '0' );

check( 'logged out at /site2/wp-admin with only the custom login: sent home, not to the login', visit( '/site2/wp-admin/' ), 'home' );
check( 'a deeper subsite admin screen too', visit( '/site2/wp-admin/options-general.php' ), 'home' );
check( 'the subsite wp-login.php is sent home', visit( '/site2/wp-login.php' ), 'home' );

$GLOBALS['test_user'] = new WP_User( 6, array( 'subscriber' ) );
$GLOBALS['options']   = array(
	'blueworx_backend_protection_enabled' => '1',
	'blueworx_backend_protection_roles'   => array( 'administrator' ),
);

check( 'logged in without an allowed role at /site2/wp-admin: refused', visit( '/site2/wp-admin/' ), $no_access );

$GLOBALS['test_user'] = new WP_User( 0 );

check( 'the subsite custom login address is recognised', blueworx_is_custom_login_request_path( '/site2/admin_login' ), true );
check( 'with wp-login.php behind it too', blueworx_is_custom_login_request_path( '/site2/admin_login/wp-login.php' ), true );

on_site( '/' );
$GLOBALS['options'] = array( 'blueworx_feature_site_protection' => '0' );

check( 'the main site path is left alone', blueworx_path_within_site( '/wp-admin' ), '/wp-admin' );
check( 'the main site admin still sends logged-out visitors home', visit( '/wp-admin/' ), 'home' );

$GLOBALS['multisite'] = false;
on_site( '/site2/' );

check( 'on a single site the path is never rewritten', blueworx_path_within_site( '/site2/wp-admin' ), '/site2/wp-admin' );

$GLOBALS['multisite'] = true;
on_site( '/' );
$GLOBALS['options'] = array();

echo "\nNew-subsite welcome emails link to a working login (#228)\n";

$email = "Your new site is at https://example.test/site2/\nLog in here: https://example.test/site2/wp-login.php\n";

check(
	'the filter is registered for the welcome email',
	isset( $GLOBALS['hooks']['update_welcome_email'] ) ? $GLOBALS['hooks']['update_welcome_email'] : null,
	array(
		'callback' => 'blueworx_welcome_email_login_link',
		'args'     => 2,
	)
);

check(
	'the wp-login.php link becomes the new site\'s custom login',
	blueworx_welcome_email_login_link( $email, 2 ),
	"Your new site is at https://example.test/site2/\nLog in here: https://example.test/site2/admin_login/\n"
);

check( 'and the current site is restored afterwards', home_url( '/' ), 'https://example.test/' );

$GLOBALS['blogs'][2]['options'] = array( 'blueworx_login_slug' => 'staff-door' );

check(
	'the new site\'s own login slug is used',
	blueworx_welcome_email_login_link( $email, 2 ),
	"Your new site is at https://example.test/site2/\nLog in here: https://example.test/site2/staff-door/\n"
);

$GLOBALS['blogs'][2]['options'] = array( 'blueworx_feature_login' => '0' );

check( 'left alone when the new site has the custom login switched off', blueworx_welcome_email_login_link( $email, 2 ), $email );

$GLOBALS['blogs'][2]['options']                     = array();
$GLOBALS['site_options']['active_sitewide_plugins'] = array();

check( 'left alone when the plugin is not running on the new site', blueworx_welcome_email_login_link( $email, 2 ), $email );

$GLOBALS['blogs'][2]['options'] = array( 'active_plugins' => array( 'blueworx-labs-wordpress/blueworx-labs-wordpress.php' ) );

check(
	'rewritten when the plugin is switched on for that site alone',
	blueworx_welcome_email_login_link( $email, 2 ),
	"Your new site is at https://example.test/site2/\nLog in here: https://example.test/site2/admin_login/\n"
);

finish();
