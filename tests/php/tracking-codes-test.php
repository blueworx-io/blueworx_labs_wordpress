<?php
/**
 * Tracking codes: which IDs are accepted, and what lands on the page.
 *
 * The code each service asks a site to paste is fixed; only the ID changes. So
 * the two things that must be right are that nothing but a well-formed ID is
 * ever stored, and that a stored ID produces that service's code, in the right
 * part of the page, and nothing else.
 *
 * Run with: php tests/php/tracking-codes-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';

$GLOBALS['hooks']   = array();
$GLOBALS['enabled'] = true;
$GLOBALS['admin']   = false;

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function add_action( $hook, $callback, $priority = 10 ) {
	$GLOBALS['hooks'][ $hook ] = array(
		'callback' => $callback,
		'priority' => $priority,
	);
	return true;
}

function blueworx_feature_enabled( $key ) {
	return 'tracking_codes' === $key && $GLOBALS['enabled'];
}

function is_admin() {
	return $GLOBALS['admin'];
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_js( $text ) {
	return addslashes( (string) $text );
}

function wp_unslash( $value ) {
	return $value;
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/tracking-codes.php';

/**
 * Captures what a print function echoes.
 *
 * @param callable $callback Print function.
 * @return string Output.
 */
function blueworx_test_capture( $callback ) {
	ob_start();
	call_user_func( $callback );
	return (string) ob_get_clean();
}

echo "Only a well-formed ID is accepted\n";

check( 'a Tag Manager ID', blueworx_tracking_normalize_id( 'gtm', 'GTM-TFD87DQF' ), 'GTM-TFD87DQF' );
check( 'typed in lower case, with spaces around it', blueworx_tracking_normalize_id( 'gtm', '  gtm-tfd87dqf ' ), 'GTM-TFD87DQF' );
check( 'a Google Analytics ID', blueworx_tracking_normalize_id( 'ga', 'G-AB12CD34EF' ), 'G-AB12CD34EF' );
check( 'a Meta pixel ID', blueworx_tracking_normalize_id( 'meta', '123456789012345' ), '123456789012345' );
check( 'a pixel ID copied with spaces in it', blueworx_tracking_normalize_id( 'meta', '1234 5678 9012 345' ), '123456789012345' );
check( 'an empty box clears it', blueworx_tracking_normalize_id( 'gtm', '   ' ), '' );
check( 'an Analytics ID in the Tag Manager box', blueworx_tracking_normalize_id( 'gtm', 'G-AB12CD34EF' ), false );
check( 'the old Universal Analytics format', blueworx_tracking_normalize_id( 'ga', 'UA-12345-1' ), false );
check( 'the whole pasted snippet', blueworx_tracking_normalize_id( 'gtm', "<script>(function(w,d,s,l,i){})(window,document,'script','dataLayer','GTM-TFD87DQF');</script>" ), false );
check( 'a script smuggled after an ID', blueworx_tracking_normalize_id( 'gtm', "GTM-AB12');alert(1);//" ), false );
check( 'letters in a pixel ID', blueworx_tracking_normalize_id( 'meta', '12345abc890' ), false );
check( 'an unknown service', blueworx_tracking_normalize_id( 'hotjar', '12345' ), false );

echo "\nSaving keeps a refused ID's old value and says which it was\n";

$GLOBALS['options']['blueworx_tracking_gtm'] = 'GTM-OLD1234';

$refused = blueworx_tracking_save(
	array(
		'blueworx_tracking_gtm'  => 'not an id',
		'blueworx_tracking_ga'   => 'g-new12345',
		'blueworx_tracking_meta' => '',
	)
);

check( 'the refused one is named', $refused, array( 'gtm' ) );
check( 'and keeps what it had', $GLOBALS['options']['blueworx_tracking_gtm'], 'GTM-OLD1234' );
check( 'the good one is saved, tidied up', $GLOBALS['options']['blueworx_tracking_ga'], 'G-NEW12345' );
check( 'the empty one is cleared', $GLOBALS['options']['blueworx_tracking_meta'], '' );

echo "\nNothing is added while every box is empty\n";

$GLOBALS['options'] = array();

check( 'in the head', blueworx_test_capture( 'blueworx_tracking_print_head' ), '' );
check( 'after the body opens', blueworx_test_capture( 'blueworx_tracking_print_body' ), '' );

echo "\nTag Manager's code, in both places\n";

$GLOBALS['options']['blueworx_tracking_gtm'] = 'GTM-TFD87DQF';

$head = blueworx_test_capture( 'blueworx_tracking_print_head' );
$body = blueworx_test_capture( 'blueworx_tracking_print_body' );

check( 'the loader is in the head', false !== strpos( $head, "(window,document,'script','dataLayer','GTM-TFD87DQF');</script>" ), true );
check( 'with the dataLayer start event', false !== strpos( $head, "w[l].push({'gtm.start':" ), true );
check( 'the fallback is not in the head', false !== strpos( $head, 'ns.html' ), false );
check( 'the fallback is after the body opens', false !== strpos( $body, '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TFD87DQF" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' ), true );
check( 'and no Analytics or Meta code', false !== strpos( $head, 'gtag/js' ) || false !== strpos( $head, 'fbevents' ), false );

echo "\nAnalytics and Meta add their own code\n";

$GLOBALS['options']['blueworx_tracking_ga']   = 'G-AB12CD34EF';
$GLOBALS['options']['blueworx_tracking_meta'] = '123456789012345';

$head = blueworx_test_capture( 'blueworx_tracking_print_head' );

// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- The expected output, not a script this file loads.
check( 'the Analytics loader', false !== strpos( $head, '<script async src="https://www.googletagmanager.com/gtag/js?id=G-AB12CD34EF"></script>' ), true );
check( 'and its config', false !== strpos( $head, "gtag('config', 'G-AB12CD34EF');" ), true );
check( 'the Meta pixel', false !== strpos( $head, "fbq('init', '123456789012345');" ), true );
check( 'recording a page view', false !== strpos( $head, "fbq('track', 'PageView');" ), true );
check( 'with its image fallback', false !== strpos( $head, 'https://www.facebook.com/tr?id=123456789012345&amp;ev=PageView&amp;noscript=1' ), true );
check( 'Tag Manager comes first', strpos( $head, 'GTM-TFD87DQF' ) < strpos( $head, 'G-AB12CD34EF' ), true );

echo "\nA bad value already in the database never reaches the page\n";

$GLOBALS['options']['blueworx_tracking_gtm'] = "GTM-AB12');alert(1);//";

check( 'it is skipped', false !== strpos( blueworx_test_capture( 'blueworx_tracking_print_head' ), 'alert' ), false );

echo "\nSwitched off, or on an admin screen, nothing at all\n";

$GLOBALS['options']['blueworx_tracking_gtm'] = 'GTM-TFD87DQF';
$GLOBALS['enabled']                          = false;

check( 'switched off: head', blueworx_test_capture( 'blueworx_tracking_print_head' ), '' );
check( 'switched off: body', blueworx_test_capture( 'blueworx_tracking_print_body' ), '' );

$GLOBALS['enabled'] = true;
$GLOBALS['admin']   = true;

check( 'admin screen: head', blueworx_test_capture( 'blueworx_tracking_print_head' ), '' );

$GLOBALS['admin'] = false;

echo "\nThe hooks are in place\n";

check( 'the head code is hooked', isset( $GLOBALS['hooks']['wp_head']['callback'] ) ? $GLOBALS['hooks']['wp_head']['callback'] : null, 'blueworx_tracking_print_head' );
check( 'ahead of everything else in the head', $GLOBALS['hooks']['wp_head']['priority'] < 1, true );
check( 'the fallback is hooked after the body opens', isset( $GLOBALS['hooks']['wp_body_open']['callback'] ) ? $GLOBALS['hooks']['wp_body_open']['callback'] : null, 'blueworx_tracking_print_body' );
check( 'first thing in the body', $GLOBALS['hooks']['wp_body_open']['priority'] < 1, true );

finish();
