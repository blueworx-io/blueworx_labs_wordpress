<?php
/**
 * The guide format: every guide is a task with a Where line, steps and a Then.
 *
 * Run with: php tests/php/guides-format-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

$GLOBALS['roles'] = array(
	'administrator' => array( 'name' => 'Administrator', 'capabilities' => array( 'manage_options' => true, 'edit_posts' => true, 'read' => true ) ),
);
$GLOBALS['reader'] = 'administrator';

function current_user_can( $capability ) {
	return ! empty( $GLOBALS['roles'][ $GLOBALS['reader'] ]['capabilities'][ $capability ] );
}
function get_editable_roles() {
	return $GLOBALS['roles'];
}
function translate_user_role( $name ) {
	return $name;
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function esc_html__( $text, $domain = '' ) {
	return esc_html( $text );
}
function _n( $single, $plural, $number, $domain = '' ) {
	return 1 === $number ? $single : $plural;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function class_exists_stub( $name ) {
	return false;
}

require __DIR__ . '/../../includes/features.php';
require __DIR__ . '/../../includes/guides.php';
require __DIR__ . '/../../includes/display-names.php';

function blueworx_check_guide_format() {
	echo "The helper builds the three parts in order\n";

	$body = blueworx_guide_body(
		array(
			'where' => 'BlueWorx > Cache',
			'steps' => array( 'Press *Clear cache*.', 'Wait for the green tick.' ),
			'then'  => 'Visitors now see the new version.',
		)
	);

	check( 'starts with the Where line', 0, strpos( $body, '<p class="bw-guide__where"><strong>Where:</strong> BlueWorx &gt; Cache</p>' ) );
	check( 'steps are an ordered list', true, false !== strpos( $body, '<ol class="bw-guide__steps"><li>Press <em>Clear cache</em>.</li><li>Wait for the green tick.</li></ol>' ) );
	check( 'ends with Then', true, str_ends_with( $body, '<p class="bw-guide__then">Visitors now see the new version.</p>' ) );

	$with_intro = blueworx_guide_body(
		array(
			'where' => 'Posts',
			'intro' => 'A post is a dated entry.',
			'steps' => array( 'One.' ),
			'then'  => 'Done.',
		)
	);
	check( 'intro sits between Where and the steps', true, false !== strpos( $with_intro, 'Posts</p><p>A post is a dated entry.</p><ol' ) );

	echo "\nText is escaped before emphasis is added\n";

	check( 'angle brackets never reach the page', 'a &lt;b&gt; c', blueworx_guide_text( 'a <b> c' ) );
	check( 'asterisks become emphasis', 'press <em>Save</em> now', blueworx_guide_text( 'press *Save* now' ) );
	check( 'a lone asterisk is left alone', '2 * 3', blueworx_guide_text( '2 * 3' ) );

	echo "\nProduct names follow Display names\n";

	$GLOBALS['options'] = array( 'blueworx_feature_display_names' => '0' );
	check( 'SureCart is SureCart with the feature off', 'SureCart', blueworx_guide_product_label( 'surecart' ) );
	check( 'LatePoint too', 'LatePoint', blueworx_guide_product_label( 'latepoint' ) );

	$GLOBALS['options'] = array( 'blueworx_feature_display_names' => '1' );
	check( 'SureCart becomes Commerce with it on', 'Commerce', blueworx_guide_product_label( 'surecart' ) );
	check( 'LatePoint becomes Bookings', 'Bookings', blueworx_guide_product_label( 'latepoint' ) );
	check( 'WordPress is never renamed', 'WordPress', blueworx_guide_product_label( 'wordpress' ) );
	check( 'an unknown product is empty', '', blueworx_guide_product_label( 'nope' ) );

	$GLOBALS['options'] = array();

	echo "\nOnly client-facing features get a guide\n";

	$GLOBALS['options'] = array();
	$feature_guides = blueworx_get_feature_guides();
	$ids            = array_column( $feature_guides, 'id' );

	$hidden = array( 'xmlrpc', 'rest_users', 'author_slugs', 'application_passwords', 'robots_txt', 'emails', 'revisions', 'login_session', 'login_redirect', 'profile_cleanup', 'dashboard_widgets', 'admin_bar', 'admin_theme', 'display_names', 'comments', 'user_roles' );
	foreach ( $hidden as $key ) {
		check( "no guide for $key", false, in_array( 'feature-' . $key, $ids, true ) );
	}

	// Every feature that is on and not flagged still has its first guide under
	// the id it always had, so links and specs keep resolving.
	foreach ( blueworx_get_feature_definitions() as $key => $feature ) {
		if ( isset( $feature['guide'] ) && false === $feature['guide'] ) {
			continue;
		}
		if ( ! blueworx_feature_enabled( $key ) ) {
			continue;
		}
		check( "feature-$key still exists", true, in_array( 'feature-' . $key, $ids, true ) );
	}

	echo "\nA feature can carry more than one task\n";

	check( 'sign-in address has a second guide', true, in_array( 'feature-login-changing', $ids, true ) );
	check( 'the second guide sits in the same tab', 'security', $feature_guides[ array_search( 'feature-login-changing', $ids, true ) ]['tab'] );
	check( 'and belongs to the same feature', 'login', $feature_guides[ array_search( 'feature-login-changing', $ids, true ) ]['feature'] );

	echo "\nEvery guide in the registry is a task\n";

	$GLOBALS['options'] = array();
	foreach ( array_merge( blueworx_get_wordpress_basics_guides(), blueworx_get_feature_guides(), blueworx_get_other_product_guides() ) as $guide ) {
		$ok = false !== strpos( $guide['body'], 'bw-guide__where' )
			&& false !== strpos( $guide['body'], '<ol class="bw-guide__steps">' )
			&& false !== strpos( $guide['body'], 'bw-guide__then' );
		check( $guide['id'] . ' has where, steps and then', true, $ok );
	}
}

blueworx_check_guide_format();

finish();
