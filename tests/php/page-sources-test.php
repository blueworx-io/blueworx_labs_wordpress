<?php
/**
 * The Pages list's Source column and the protection a source brings.
 *
 * Run with: php tests/php/page-sources-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

function blueworx_feature_enabled( $key ) {
	return true;
}
$GLOBALS['hooks'] = array();
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][] = $hook;
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][] = $hook;
}
function wp_die( $message, $title = '', $args = array() ) {
	throw new RuntimeException( $message . '|' . $title . '|' . $args['response'] );
}

require __DIR__ . '/../../includes/page-sources.php';
require __DIR__ . '/../../includes/store/pages.php';

echo "The column\n";
$columns = blueworx_page_sources_columns( array( 'cb' => 'x', 'title' => 'Title', 'author' => 'Author', 'date' => 'Date' ) );
check( 'Source sits right after Title', array_keys( $columns ), array( 'cb', 'title', 'blueworx_page_source', 'author', 'date' ) );
check( 'and is called Source', $columns['blueworx_page_source'], 'Source' );
check( 'with no Title column it goes last', array_keys( blueworx_page_sources_columns( array( 'cb' => 'x' ) ) ), array( 'cb', 'blueworx_page_source' ) );

echo "\nWho built a page\n";
check( 'nobody, by default', blueworx_page_source( 7 ), '' );
check( 'nothing for a bad id', blueworx_page_source( 0 ), '' );
$GLOBALS['filters']['blueworx_page_source'] = static function ( $label, $post_id ) {
	return 7 === $post_id ? '  Club page ' : $label;
};
check( 'a plugin answers through the filter, trimmed', blueworx_page_source( 7 ), 'Club page' );
check( 'and only for its own pages', blueworx_page_source( 8 ), '' );
$GLOBALS['filters']['blueworx_page_source'] = static function () {
	return array( 'junk' );
};
check( 'a non-string answer counts as no answer', blueworx_page_source( 7 ), '' );
unset( $GLOBALS['filters']['blueworx_page_source'] );

echo "\nRow actions\n";
$offered = array( 'edit' => 'E', 'inline hide-if-no-js' => 'Q', 'trash' => 'T', 'view' => 'V', 'clone' => 'C' );
check( 'a page with a source keeps view and edit only', blueworx_page_sources_row_actions( $offered, true ), array( 'edit' => 'E', 'view' => 'V' ) );
check( 'any other page keeps everything', blueworx_page_sources_row_actions( $offered, false ), $offered );
check( 'junk in is an empty list out', blueworx_page_sources_row_actions( 'nope', true ), array() );

echo "\nDeletion\n";
$GLOBALS['filters']['blueworx_page_source'] = static function ( $label, $post_id ) {
	return 7 === $post_id ? 'Commerce page' : $label;
};
$refused = '';
try {
	blueworx_page_sources_refuse_deletion( 7 );
} catch ( RuntimeException $e ) {
	$refused = $e->getMessage();
}
check( 'a page with a source is refused, with a 403 and its own name', $refused, 'This is a commerce page. The site is served from it, so it cannot be deleted. Open it to edit it instead.|Commerce page|403' );
$refused = '';
try {
	blueworx_page_sources_refuse_deletion( 8 );
} catch ( RuntimeException $e ) {
	$refused = $e->getMessage();
}
check( 'any other page goes quietly', $refused, '' );
unset( $GLOBALS['filters']['blueworx_page_source'] );

echo "\nThe store names its pages\n";
$GLOBALS['options']['surecart_checkout_page_id']           = 21;
$GLOBALS['options']['surecart_order-confirmation_page_id'] = 22;
$GLOBALS['options']['surecart_dashboard_page_id']          = 23;
$GLOBALS['options']['surecart_shop_page_id']               = '24';
check( 'checkout', blueworx_store_page_source( '', 21 ), 'Commerce page' );
check( 'thank you', blueworx_store_page_source( '', 22 ), 'Commerce page' );
check( 'dashboard', blueworx_store_page_source( '', 23 ), 'Commerce page' );
check( 'shop, even stored as a string', blueworx_store_page_source( '', 24 ), 'Commerce page' );
check( 'any other page is not', blueworx_store_page_source( '', 25 ), '' );
check( 'a label another plugin gave first is kept', blueworx_store_page_source( 'Club page', 21 ), 'Club page' );

echo "\nHooked\n";
foreach ( array( 'manage_pages_columns', 'manage_pages_custom_column', 'page_row_actions', 'wp_trash_post', 'before_delete_post', 'blueworx_page_source' ) as $hook ) {
	check( $hook, in_array( $hook, $GLOBALS['hooks'], true ), true );
}

finish();
