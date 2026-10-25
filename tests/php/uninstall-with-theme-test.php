<?php
/**
 * Deleting the plugin while the BlueWorx theme is the active theme.
 *
 * The theme loads its own copy of the support functions before uninstall.php
 * runs, so uninstall must use that copy rather than load the plugin's and stop
 * on "cannot redeclare". Its own site keeps everything; a site on the network
 * not on the theme is still cleaned up.
 *
 * Run: php tests/php/uninstall-with-theme-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

define( 'WP_UNINSTALL_PLUGIN', 'blueworx-labs-wordpress/blueworx-labs-wordpress.php' );

// Site 1 runs the theme, site 2 a theme of its own.
$GLOBALS['sites']        = array(
	1 => array(
		'template'                  => 'blueworx-labs-wordpress-theme',
		'blueworx_support_key_hash' => 'one',
	),
	2 => array(
		'template'                  => 'twentytwentyfive',
		'blueworx_support_key_hash' => 'two',
	),
);
$GLOBALS['blog']         = 1;
$GLOBALS['blog_stack']   = array();
$GLOBALS['options']      = $GLOBALS['sites'][1];
$GLOBALS['removed']      = array();
$GLOBALS['meta_cleared'] = false;

/**
 * Stand-in for the theme's copy, already loaded when uninstall.php runs.
 *
 * @return void
 */
function blueworx_support_remove_account() {
	$GLOBALS['removed'][] = $GLOBALS['blog'];
}

/**
 * Stand-in for the theme's copy.
 *
 * @param int $user_id Account to delete.
 * @return bool
 */
function blueworx_delete_owned_account( $user_id ) {
	return true;
}

function is_multisite() {
	return true;
}

function get_sites( $args = array() ) {
	return array_keys( $GLOBALS['sites'] );
}

/**
 * Moves to another site, keeping each site's options apart.
 *
 * @param int $blog_id Site to move to.
 * @return void
 */
function switch_to_blog( $blog_id ) {
	$GLOBALS['sites'][ $GLOBALS['blog'] ] = $GLOBALS['options'];
	$GLOBALS['blog_stack'][]              = $GLOBALS['blog'];
	$GLOBALS['blog']                      = (int) $blog_id;
	$GLOBALS['options']                   = $GLOBALS['sites'][ $GLOBALS['blog'] ];
}

function restore_current_blog() {
	$GLOBALS['sites'][ $GLOBALS['blog'] ] = $GLOBALS['options'];
	$GLOBALS['blog']                      = array_pop( $GLOBALS['blog_stack'] );
	$GLOBALS['options']                   = $GLOBALS['sites'][ $GLOBALS['blog'] ];
}

function delete_option( $key ) {
	unset( $GLOBALS['options'][ $key ] );
	return true;
}

function delete_metadata( $type, $object_id, $key, $value = '', $all = false ) {
	$GLOBALS['meta_cleared'] = true;
	return true;
}

function get_users( $args = array() ) {
	return array();
}

function remove_role( $slug ) {}

/**
 * Theme stand-in: only the theme's folder carries the plugin's text domain.
 */
class Test_Theme {
	/**
	 * Theme folder.
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
		return 'TextDomain' === $header && 'blueworx-labs-wordpress-theme' === $this->folder ? 'blueworx-labs-wordpress' : '';
	}
}

function wp_get_theme( $stylesheet = '' ) {
	return new Test_Theme( $stylesheet );
}

echo "Deleting the plugin while the theme is active\n";

require __DIR__ . '/../../uninstall.php';

check( 'uninstall runs to the end without loading a second copy', true, true );
check( 'the site on the theme keeps its support key', get_option( 'blueworx_support_key_hash' ), 'one' );
check( 'the other site is cleaned up through the theme\'s copy', $GLOBALS['removed'], array( 2 ) );
check( 'and its support key is gone', isset( $GLOBALS['sites'][2]['blueworx_support_key_hash'] ), false );
check( 'guest viewer details shared by the network are kept', $GLOBALS['meta_cleared'], false );

finish();
