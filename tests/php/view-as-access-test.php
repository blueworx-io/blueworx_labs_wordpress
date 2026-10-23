<?php
/**
 * Who may preview a role, and which roles they are offered.
 *
 * The rule is pure — a set of capabilities in, a list of roles out — so it is
 * checked here rather than by driving a browser. What only a running site can
 * show is that the control survives the preview it started, and
 * tests/core-screen-controls.spec.js covers that.
 *
 * Run with: php tests/php/view-as-access-test.php
 *
 * @package BlueWorxLabs
 */

// The shared WordPress stand-ins. Kept apart from the docblock above, which
// phpcs otherwise reads as this statement's rather than the file's.
require __DIR__ . '/stubs.php';

// This script stands in for WordPress rather than being loaded into it, so its
// stubs have to carry core's names and its state has to be global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Same: the text domain is part of the signature.

/** Every role on this pretend site, in the shape wp_roles() hands them over. */
$GLOBALS['roles'] = array(
	'administrator' => array(
		'name'         => 'Administrator',
		'capabilities' => array(
			'read'           => true,
			'edit_posts'     => true,
			'publish_posts'  => true,
			'edit_others'    => true,
			'upload_files'   => true,
			'manage_options' => true,
		),
	),
	'editor'        => array(
		'name'         => 'Editor',
		'capabilities' => array(
			'read'          => true,
			'edit_posts'    => true,
			'publish_posts' => true,
			'edit_others'   => true,
			'upload_files'  => true,
		),
	),
	'author'        => array(
		'name'         => 'Author',
		'capabilities' => array(
			'read'          => true,
			'edit_posts'    => true,
			'publish_posts' => true,
			'upload_files'  => true,
		),
	),
	'contributor'   => array(
		'name'         => 'Contributor',
		'capabilities' => array(
			'read'       => true,
			'edit_posts' => true,
		),
	),
	'subscriber'    => array(
		'name'         => 'Subscriber',
		'capabilities' => array( 'read' => true ),
	),
);

/** Whoever is signed in. */
$GLOBALS['reader'] = 'administrator';

function wp_get_current_user() {
	// Anonymous rather than named: WP_User and WP_Roles are only read for a
	// property or two here, and a named class in a test script trips every
	// sniff about what a class file is supposed to look like.
	$role = $GLOBALS['reader'];

	return new class( $role, $GLOBALS['roles'][ $role ]['capabilities'] ) {
		/**
		 * User ID.
		 *
		 * @var int
		 */
		public $ID;

		/**
		 * Roles held.
		 *
		 * @var array
		 */
		public $roles;

		/**
		 * Capabilities held, before any preview narrows them.
		 *
		 * @var array
		 */
		public $allcaps;

		public function __construct( $role, $caps ) {
			$this->ID      = get_current_user_id();
			$this->roles   = array( $role );
			$this->allcaps = $caps;
		}

		public function exists() {
			return true;
		}
	};
}

function wp_roles() {
	return new class( $GLOBALS['roles'] ) {
		/**
		 * Every role registered on the site.
		 *
		 * @var array
		 */
		public $roles;

		public function __construct( $roles ) {
			$this->roles = $roles;
		}
	};
}

function translate_user_role( $name ) {
	return $name;
}

function blueworx_feature_enabled( $key ) {
	return empty( $GLOBALS['feature_off'] );
}

function blueworx_admin_theme_enabled() {
	return empty( $GLOBALS['theme_off'] );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function esc_html__( $text, $domain = '' ) {
	return $text;
}

function esc_attr__( $text, $domain = '' ) {
	return $text;
}

function get_current_user_id() {
	return isset( $GLOBALS['user_id'] ) ? $GLOBALS['user_id'] : 1;
}

// User meta, per user and per key, the way the usermeta table holds it.
$GLOBALS['usermeta'] = array();

function get_user_meta( $user_id, $key, $single = false ) {
	return isset( $GLOBALS['usermeta'][ $user_id ][ $key ] ) ? $GLOBALS['usermeta'][ $user_id ][ $key ] : '';
}

function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['usermeta'][ $user_id ][ $key ] = $value;

	return true;
}

function delete_user_meta( $user_id, $key ) {
	unset( $GLOBALS['usermeta'][ $user_id ][ $key ] );

	return true;
}

function update_user_option( $user_id, $option, $value, $is_global = false ) {
	// Core's user options: user meta whose key carries the site's table prefix.
	return update_user_meta( $user_id, ( $is_global ? '' : $GLOBALS['wpdb']->get_blog_prefix() ) . $option, $value );
}

function delete_user_option( $user_id, $option, $is_global = false ) {
	return delete_user_meta( $user_id, ( $is_global ? '' : $GLOBALS['wpdb']->get_blog_prefix() ) . $option );
}

// The database handle, for the one thing read off it: which site's prefix is in play.
$GLOBALS['blog_prefix'] = 'wp_';
$GLOBALS['wpdb']        = new class() { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Stands in for core's handle; WordPress is never loaded here.
	public function get_blog_prefix() {
		return $GLOBALS['blog_prefix'];
	}
};

function is_multisite() {
	return ! empty( $GLOBALS['multisite'] );
}

function is_super_admin( $user_id = false ) {
	return ! empty( $GLOBALS['super_admin'] );
}

function is_network_admin() {
	return ! empty( $GLOBALS['network_admin'] );
}

function get_role( $slug ) {
	return isset( $GLOBALS['roles'][ $slug ] ) ? (object) $GLOBALS['roles'][ $slug ] : null;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = $callback;
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require __DIR__ . '/../../includes/view-as-role.php';

/**
 * What one role is offered.
 *
 * @param string $role Role slug of whoever is signed in.
 * @return array Role slugs on offer, in the order the menu lists them.
 */
function blueworx_offered_to( $role ) {
	$GLOBALS['reader'] = $role;

	return array_keys( blueworx_view_as_role_choices() );
}

/**
 * Whether one role gets the control at all.
 *
 * @param string $role Role slug of whoever is signed in.
 * @return bool True when the switch is offered.
 */
function blueworx_available_to( $role ) {
	$GLOBALS['reader'] = $role;

	return blueworx_view_as_available();
}

echo "Anyone who edits the site gets the switch\n";

check( 'an administrator', blueworx_available_to( 'administrator' ), true );
check( 'an editor', blueworx_available_to( 'editor' ), true );
check( 'a contributor, who can edit but not publish', blueworx_available_to( 'contributor' ), true );
check( 'a subscriber, who edits nothing', blueworx_available_to( 'subscriber' ), false );

echo "\nAnd is offered their own level downwards, never upwards\n";

// Alphabetical by the name on screen, not by how much each role can do: the
// menu is a list to read, and Editor sitting above Author says nothing to the
// person reading it.
check(
	'an administrator sees every role below them, in name order',
	blueworx_offered_to( 'administrator' ),
	array( 'author', 'contributor', 'editor', 'subscriber' )
);

check(
	'an editor is not offered editor, and never administrator',
	blueworx_offered_to( 'editor' ),
	array( 'author', 'contributor', 'subscriber' )
);

check(
	'an author drops to contributor and subscriber',
	blueworx_offered_to( 'author' ),
	array( 'contributor', 'subscriber' )
);

check( 'and a contributor has only subscriber below them', blueworx_offered_to( 'contributor' ), array( 'subscriber' ) );

echo "\nSwitching the function off takes it away from everybody\n";

$GLOBALS['feature_off'] = true;

check( 'even an administrator', blueworx_available_to( 'administrator' ), false );

unset( $GLOBALS['feature_off'] );

echo "\nA preview cannot widen what the person could already do\n";

// The list is worked out from the real user's own capabilities, which is why a
// preview already in progress does not change what is on offer: the menu is the
// way out as well as the way in.
blueworx_view_as_store_role( 1, 'subscriber' );

check(
	'the menu still offers the whole way back up',
	blueworx_offered_to( 'editor' ),
	array( 'author', 'contributor', 'subscriber' )
);

check( 'and the control has not disappeared', blueworx_available_to( 'editor' ), true );

echo "\nAnd a preview really does take the previewed role's limits on\n";

$GLOBALS['reader'] = 'administrator';
$capped            = blueworx_view_as_filter_caps( $GLOBALS['roles']['administrator']['capabilities'] );

check( 'an administrator previewing a subscriber cannot reach the settings', empty( $capped['manage_options'] ), true );
check( 'nor edit anything', empty( $capped['edit_posts'] ), true );
check( 'but wp-admin still opens', ! empty( $capped['read'] ), true );

// Nothing the role grants and the person lacks may appear: the filter is an
// intersection, and a subscriber previewing anything gains nothing at all.
blueworx_view_as_store_role( 1, 'editor' );
$widened = blueworx_view_as_filter_caps( $GLOBALS['roles']['subscriber']['capabilities'] );

check( 'and previewing a role above you grants nothing', empty( $widened['edit_posts'] ), true );

echo "\nAnd a preview nobody could get out of is not applied at all\n";

// The control lives in the re-skinned sidebar. With the admin theme switched
// off there is nothing on screen to end a preview with, and a preview now takes
// the settings screens away too — so the stored role is ignored rather than
// leaving somebody locked into it.
$GLOBALS['reader']    = 'administrator';
$GLOBALS['theme_off'] = true;

$unstyled = blueworx_view_as_filter_caps( $GLOBALS['roles']['administrator']['capabilities'] );

check( 'with the admin theme off, nothing is narrowed', ! empty( $unstyled['manage_options'] ), true );

unset( $GLOBALS['theme_off'] );
blueworx_view_as_store_role( 1, '' );

/**
 * What a capability check needs once the super-admin hook has seen it.
 *
 * @param array  $caps What core says the capability needs.
 * @param string $cap  The capability asked for.
 * @return array What the hook leaves it needing.
 */
function blueworx_super_admin_needs( $caps, $cap ) {
	return blueworx_view_as_super_admin_caps( $caps, $cap, get_current_user_id() );
}

echo "\nOn a single site the preview is kept against the site, not the person\n";

blueworx_view_as_store_role( 1, 'subscriber' );

check( 'it is saved under the site\'s own key', get_user_meta( 1, 'wp_' . BLUEWORX_VIEW_AS_META, true ), 'subscriber' );
check( 'and not under the old account-wide one', get_user_meta( 1, BLUEWORX_VIEW_AS_META, true ), '' );
check( 'and it is still the preview in force', blueworx_view_as_current_role(), 'subscriber' );

blueworx_view_as_store_role( 1, '' );

check( 'ending it clears the site\'s key', get_user_meta( 1, 'wp_' . BLUEWORX_VIEW_AS_META, true ), '' );
check( 'and leaves no preview behind', blueworx_view_as_current_role(), '' );

echo "\nOn a network, a preview stays on the site it was started on\n";

$GLOBALS['multisite']   = true;
$GLOBALS['blog_prefix'] = 'wp_2_';

blueworx_view_as_store_role( 1, 'subscriber' );

check( 'previewing on one site takes effect there', blueworx_view_as_current_role(), 'subscriber' );

$GLOBALS['blog_prefix'] = 'wp_3_';

check( 'and has no effect on another site', blueworx_view_as_current_role(), '' );

blueworx_view_as_store_role( 1, 'editor' );
$GLOBALS['blog_prefix'] = 'wp_2_';

check( 'a second preview elsewhere leaves the first alone', blueworx_view_as_current_role(), 'subscriber' );

blueworx_view_as_store_role( 1, '' );

check( 'ending one ends only that one', blueworx_view_as_current_role(), '' );

$GLOBALS['blog_prefix'] = 'wp_3_';

check( 'the other site\'s preview is still running', blueworx_view_as_current_role(), 'editor' );

$GLOBALS['network_admin'] = true;

check( 'and the network admin, which is no one site, is never previewed', blueworx_view_as_current_role(), '' );

unset( $GLOBALS['network_admin'] );
blueworx_view_as_store_role( 1, '' );

echo "\nA preview saved by an older version is picked up once, not left behind\n";

// Before the per-site key, the preview lived on the account itself. Nobody may
// be left inside a preview with no control that shows it.
unset( $GLOBALS['multisite'] );
$GLOBALS['blog_prefix'] = 'wp_';
$GLOBALS['user_id']     = 2;
$GLOBALS['usermeta'][2] = array( BLUEWORX_VIEW_AS_META => 'author' );

check( 'on a single site the preview carries on', blueworx_view_as_current_role(), 'author' );
check( 'now saved under the site\'s key', get_user_meta( 2, 'wp_' . BLUEWORX_VIEW_AS_META, true ), 'author' );
check( 'with the old key gone', get_user_meta( 2, BLUEWORX_VIEW_AS_META, true ), '' );

// On a network there is no telling which site the old preview was started on,
// so it is ended rather than guessed at.
$GLOBALS['multisite']   = true;
$GLOBALS['blog_prefix'] = 'wp_2_';
$GLOBALS['user_id']     = 3;
$GLOBALS['usermeta'][3] = array( BLUEWORX_VIEW_AS_META => 'author' );

check( 'on a network the old preview is ended', blueworx_view_as_current_role(), '' );
check( 'and its key removed', get_user_meta( 3, BLUEWORX_VIEW_AS_META, true ), '' );
check( 'without moving it onto whichever site came first', get_user_meta( 3, 'wp_2_' . BLUEWORX_VIEW_AS_META, true ), '' );

$GLOBALS['user_id'] = 1;

echo "\nA super admin previewing a role really is held to it\n";

// Core answers yes to a super admin before the user_has_cap filter ever runs,
// so the narrowing has to happen earlier, at map_meta_cap.
check( 'the hook is in place', in_array( 'blueworx_view_as_super_admin_caps', isset( $GLOBALS['hooks']['map_meta_cap'] ) ? $GLOBALS['hooks']['map_meta_cap'] : array(), true ), true );

$GLOBALS['super_admin'] = true;
blueworx_view_as_store_role( 1, 'subscriber' );

check( 'a super admin previewing a subscriber loses the settings', blueworx_super_admin_needs( array( 'manage_options' ), 'manage_options' ), array( 'do_not_allow' ) );
check( 'and network management', blueworx_super_admin_needs( array( 'manage_network' ), 'manage_network' ), array( 'do_not_allow' ) );
check( 'but can still open wp-admin', blueworx_super_admin_needs( array( 'read' ), 'read' ), array( 'read' ) );

blueworx_view_as_store_role( 1, 'author' );

check( 'previewing an author keeps what an author can do', blueworx_super_admin_needs( array( 'edit_posts', 'upload_files' ), 'upload_files' ), array( 'edit_posts', 'upload_files' ) );
check( 'and nothing more', blueworx_super_admin_needs( array( 'edit_posts', 'edit_others' ), 'edit_others_posts' ), array( 'do_not_allow' ) );

$GLOBALS['user_id'] = 4;

check( 'other people\'s checks are left alone', blueworx_view_as_super_admin_caps( array( 'manage_options' ), 'manage_options', 1 ), array( 'manage_options' ) );

$GLOBALS['user_id'] = 1;

echo "\nAnd can always get back out\n";

// The way out is gated on what the real person can do. A super admin can do
// everything, even on a site where they hold no role of their own.
$GLOBALS['reader'] = 'subscriber';
blueworx_view_as_store_role( 1, 'contributor' );

check( 'the control is still there mid-preview', blueworx_view_as_available(), true );
check( 'offering every role but administrator', array_keys( blueworx_view_as_role_choices() ), array( 'author', 'contributor', 'editor' ) );

blueworx_view_as_store_role( 1, '' );

check( 'with no preview running, nothing is touched', blueworx_super_admin_needs( array( 'manage_options' ), 'manage_options' ), array( 'manage_options' ) );

echo "\nWhile nobody else's checks change\n";

unset( $GLOBALS['super_admin'] );
$GLOBALS['reader'] = 'administrator';
blueworx_view_as_store_role( 1, 'subscriber' );

check( 'a site administrator is left to the usual filter', blueworx_super_admin_needs( array( 'manage_options' ), 'manage_options' ), array( 'manage_options' ) );

blueworx_view_as_store_role( 1, '' );
unset( $GLOBALS['multisite'] );
$GLOBALS['blog_prefix'] = 'wp_';
$GLOBALS['super_admin'] = true;
blueworx_view_as_store_role( 1, 'subscriber' );

check( 'and a single site, where nobody is a super admin, is untouched', blueworx_super_admin_needs( array( 'manage_options' ), 'manage_options' ), array( 'manage_options' ) );
check( 'leaving its own filter to do the narrowing', empty( blueworx_view_as_filter_caps( $GLOBALS['roles']['administrator']['capabilities'] )['manage_options'] ), true );

unset( $GLOBALS['super_admin'] );
blueworx_view_as_store_role( 1, '' );

echo "\nA preview only narrows the person previewing (#236)\n";

// A screen asking "can this author edit this post?" checks somebody else.
// Their answer must be their own, not the previewed role's.
$GLOBALS['reader'] = 'administrator';
blueworx_view_as_store_role( 1, 'subscriber' );

$other = blueworx_view_as_filter_caps( $GLOBALS['roles']['administrator']['capabilities'], array(), array(), (object) array( 'ID' => 2 ) );
$self  = blueworx_view_as_filter_caps( $GLOBALS['roles']['administrator']['capabilities'], array(), array(), (object) array( 'ID' => 1 ) );

check( 'another user keeps their own permissions', ! empty( $other['manage_options'] ), true );
check( 'while the previewer is still held to the role', empty( $self['manage_options'] ), true );

blueworx_view_as_store_role( 1, '' );

finish();
