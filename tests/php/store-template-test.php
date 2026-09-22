<?php
/**
 * Which document the store pages are served with, and when that choice is
 * made relative to SureCart's own.
 *
 * Run with: php tests/php/store-template-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

/** SureCart reapplies its dashboard template here, at the default priority. */
define( 'SURECART_DASHBOARD_TEMPLATE_PRIORITY', 10 );

/** And routes its own screens here, which are none of this plugin's business. */
define( 'SURECART_ROUTER_PRIORITY', 3100 );

define( 'BLUEWORX_LABS_PATH', '/plugin/' );

function blueworx_feature_enabled( $key ) {
	return true;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['filters'][] = array(
		'hook'     => $hook,
		'callback' => $callback,
		'priority' => $priority,
	);
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

$GLOBALS['filters'] = array();

require __DIR__ . '/../../includes/store/commerce.php';

/**
 * The priority one of our callbacks was registered at, or null.
 *
 * @param string $hook     Hook name.
 * @param string $callback Callback name.
 * @return int|null
 */
function registered_priority( $hook, $callback ) {
	foreach ( $GLOBALS['filters'] as $filter ) {
		if ( $filter['hook'] === $hook && $filter['callback'] === $callback ) {
			return $filter['priority'];
		}
	}
	return null;
}

echo "The store pages serve their own document\n";
check(
	'a store page gets our frame',
	blueworx_store_template_for( 'dashboard', '/theme/page.php', '/plugin/template.php' ),
	'/plugin/template.php'
);
check(
	'every other page is left alone',
	blueworx_store_template_for( '', '/theme/page.php', '/plugin/template.php' ),
	'/theme/page.php'
);

echo "\nAnd that choice is made after SureCart has made its own\n";
$ours = registered_priority( 'template_include', 'blueworx_store_serve_template' );
check( 'the filter is registered', null !== $ours, true );
check(
	'after SureCart puts its dashboard template back',
	$ours > SURECART_DASHBOARD_TEMPLATE_PRIORITY,
	true
);
check(
	'and before its router, whose screens stay its own',
	$ours < SURECART_ROUTER_PRIORITY,
	true
);

echo "\nThe content filter still runs after SureCart's blocks\n";
check(
	'dressing is late',
	registered_priority( 'the_content', 'blueworx_store_dress_content' ),
	BLUEWORX_STORE_CONTENT_PRIORITY
);

finish();
