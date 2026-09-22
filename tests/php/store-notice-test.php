<?php
/**
 * The admin notice's wording and markup, and the escaping that protects it.
 *
 * Run with: php tests/php/store-notice-test.php
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
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function esc_url( $url ) {
	return $url;
}
function blueworx_ds_notice( $args ) {
	return '<ds>' . json_encode( $args ) . '</ds>';
}

require __DIR__ . '/../../includes/store/pages.php';
require __DIR__ . '/../../includes/store/pages-notice.php';

$pages = blueworx_store_pages();

echo "Nothing wrong, nothing said\n";
check( 'null when there are no problems', blueworx_store_notice_message( array(), $pages, true ), null );
check( 'and no markup', blueworx_store_notice_html( null, 'x' ), '' );

echo "\nOne line per problem, in plain words\n";
$m = blueworx_store_notice_message( array( 'checkout' => 'missing', 'dashboard' => 'unpublished' ), $pages, true );
check( 'missing', $m['lines'][0], 'Your checkout page is missing, so nobody can pay.' );
check( 'unpublished', $m['lines'][1], 'Your customer dashboard is in the trash or unpublished, so customers have nowhere to manage what they have bought.' );
check( 'the button is offered', $m['button'], 'Put the missing pages back' );
check( 'no footnote when everything is fixable', $m['footnote'], '' );

echo "\nWhen SureCart cannot seed\n";
$m = blueworx_store_notice_message( array( 'checkout' => 'missing' ), $pages, false );
check( 'no button', $m['button'], '' );
check( 'the owner is told to finish setting the shop up', $m['footnote'], 'Open SureCart and finish setting the shop up.' );

echo "\nThe markup escapes what it prints\n";
$html = blueworx_store_notice_html( array( 'lines' => array( 'a <b>' ), 'button' => 'Go', 'footnote' => '' ), 'http://x/?a=1&b=2' );
check( 'wrapper', false !== strpos( $html, '<div class="notice"><div class="bw-admin"><ds>' ), true );
preg_match( '/<ds>(.*)<\/ds>/', $html, $matches );
$args = json_decode( $matches[1], true );
check( 'tone', $args['tone'], 'warning' );
check( 'title', $args['title'], 'BlueWorx: your shop is not ready to take payments.' );
check( 'line escaped', false !== strpos( $args['html'], 'a &lt;b&gt;' ), true );
check( 'line is inline markup, not a block element', $args['html'], '<span>a &lt;b&gt;</span>' );
check( 'button class', false !== strpos( $args['actions'], 'bw-btn bw-btn--primary' ), true );
check( 'button present', false !== strpos( $args['actions'], '>Go</a>' ), true );

echo "\nA footnote trails the lines, still inline\n";
$html = blueworx_store_notice_html( array( 'lines' => array( 'a', 'b' ), 'button' => '', 'footnote' => 'c' ), 'http://x/' );
preg_match( '/<ds>(.*)<\/ds>/', $html, $matches );
$args = json_decode( $matches[1], true );
check( 'lines and footnote, no block elements', $args['html'], '<span>a</span><br><span>b</span><br><span>c</span>' );

echo "\nNo button means no actions\n";
$html = blueworx_store_notice_html( array( 'lines' => array( 'a' ), 'button' => '', 'footnote' => '' ), 'http://x/' );
preg_match( '/<ds>(.*)<\/ds>/', $html, $matches );
$args = json_decode( $matches[1], true );
check( 'no actions', $args['actions'], '' );

finish();
