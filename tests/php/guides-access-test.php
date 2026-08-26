<?php
/**
 * Who is shown which guide.
 *
 * The rule is pure — a guide, a reader's capabilities, and a yes or no — so it
 * is checked here rather than by driving a browser. tests/guides-access.spec.js
 * covers the one thing this cannot: that a real editor signing in to a real
 * site sees the narrowed screen.
 *
 * Run with: php tests/php/guides-access-test.php
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

/** What each role on this pretend site can do. */
$GLOBALS['roles'] = array(
	'administrator' => array(
		'name'         => 'Administrator',
		'capabilities' => array(
			'read'               => true,
			'edit_posts'         => true,
			'upload_files'       => true,
			'list_users'         => true,
			'update_core'        => true,
			'edit_theme_options' => true,
			'manage_options'     => true,
		),
	),
	'editor'        => array(
		'name'         => 'Editor',
		'capabilities' => array(
			'read'         => true,
			'edit_posts'   => true,
			'upload_files' => true,
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

/** Whoever is reading right now. */
$GLOBALS['reader'] = 'administrator';

function current_user_can( $capability ) {
	$caps = $GLOBALS['roles'][ $GLOBALS['reader'] ]['capabilities'];

	return ! empty( $caps[ $capability ] );
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
	return $text;
}

function _n( $single, $plural, $number, $domain = '' ) {
	return 1 === (int) $number ? $single : $plural;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require __DIR__ . '/../../includes/features.php';
require __DIR__ . '/../../includes/guides.php';

/**
 * Whether one guide reaches one role.
 *
 * @param string $role  Role slug.
 * @param array  $guide A raw guide.
 * @return bool True when the guide is shown.
 */
function blueworx_guide_reaches( $role, $guide ) {
	$GLOBALS['reader'] = $role;

	$normalized = blueworx_normalize_guides( array( $guide ) );
	$allowed    = blueworx_filter_guides_by_capability( $normalized );

	return 1 === count( $allowed );
}

/**
 * Runs every check in this file.
 *
 * Wrapped rather than written at the top level so the guides and the results
 * are locals: a script of bare globals is how two checks start sharing a name.
 *
 * @return void
 */
function blueworx_check_guide_access() {
	// A guide about writing pages, which any contributor can do.
	$writing = array(
		'id'    => 'probe-writing',
		'title' => 'Writing a page',
		'tab'   => 'wp-writing',
		'body'  => '<p>Words.</p>',
	);

	// A guide about the user list, which only somebody who can list users needs.
	$people = array(
		'id'    => 'probe-people',
		'title' => 'Which role to give somebody',
		'tab'   => 'wp-people',
		'body'  => '<p>Words.</p>',
	);

	// A guide in the BlueWorx section, on a topic an editor could otherwise act on.
	$ours = array(
		'id'    => 'probe-ours',
		'title' => 'Duplicating a page',
		'tab'   => 'content',
		'body'  => '<p>Words.</p>',
	);

	echo "A guide only reaches somebody who could do the thing it describes\n";

	check( 'an editor gets the writing guide', blueworx_guide_reaches( 'editor', $writing ), true );
	check( 'a contributor gets it too', blueworx_guide_reaches( 'contributor', $writing ), true );
	check( 'an editor does not get the user-list guide', blueworx_guide_reaches( 'editor', $people ), false );
	check( 'an administrator does', blueworx_guide_reaches( 'administrator', $people ), true );

	echo "\nThe BlueWorx section is administrator-only, whatever the topic is\n";

	// 'content' maps to edit_posts, so without the section gate an editor would
	// be reading about a settings screen they cannot open.
	check( 'the topic alone would let an editor in', 'edit_posts', blueworx_guide_tab_capability( 'content' ) );
	check( 'the section keeps them out', blueworx_guide_reaches( 'editor', $ours ), false );
	check( 'and an administrator still gets it', blueworx_guide_reaches( 'administrator', $ours ), true );

	echo "\nA guide can name its own capability\n";

	$declared = array_merge( $writing, array( 'capability' => 'update_core' ) );

	check( 'which wins over the topic default', blueworx_guide_reaches( 'editor', $declared ), false );
	check( 'and still lets an administrator through', blueworx_guide_reaches( 'administrator', $declared ), true );

	echo "\nThe role pills still say who can do the thing, not who was shown the card\n";

	// An administrator reading the Content guides needs to know their editors
	// can use the duplicate link, even though the section itself is theirs alone.
	$normalized = blueworx_normalize_guides( array( $ours ) );
	$pills      = array_keys( blueworx_roles_with_capability( $normalized[0]['capability'] ) );

	check(
		'the BlueWorx content guide still lists everybody who can edit',
		$pills,
		array( 'administrator', 'editor', 'contributor' )
	);

	check(
		'while the gate it sits behind is stricter than that',
		blueworx_guide_capabilities( $normalized[0] ),
		array( 'manage_options', 'edit_posts' )
	);
}

blueworx_check_guide_access();

finish();
