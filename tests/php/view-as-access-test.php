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
	return 1;
}

function get_user_meta( $user_id, $key, $single = false ) {
	return isset( $GLOBALS['meta'][ $key ] ) ? $GLOBALS['meta'][ $key ] : '';
}

function get_role( $slug ) {
	return isset( $GLOBALS['roles'][ $slug ] ) ? (object) $GLOBALS['roles'][ $slug ] : null;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

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

check(
	'an administrator sees every role below them',
	blueworx_offered_to( 'administrator' ),
	array( 'editor', 'author', 'contributor', 'subscriber' )
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
$GLOBALS['meta'][ BLUEWORX_VIEW_AS_META ] = 'subscriber';

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
$GLOBALS['meta'][ BLUEWORX_VIEW_AS_META ] = 'editor';
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

finish();
