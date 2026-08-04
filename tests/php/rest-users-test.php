<?php
/**
 * Who may read the user routes over REST.
 *
 * The rule is pure — a route, and whether the caller may list users — so it is
 * checked here rather than by driving a browser. The one case a browser would
 * add is covered by tests/rest-users.spec.js, which proves an anonymous fetch of
 * /wp/v2/users is actually refused on a running site.
 *
 * Run with: php tests/php/rest-users-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

$GLOBALS['hooks'] = array();

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ] = array(
		'callback' => $callback,
		'priority' => $priority,
		'args'     => $args,
	);
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	add_filter( $hook, $callback, $priority, $args );
}

function blueworx_feature_enabled( $feature ) {
	return ! empty( $GLOBALS['features'][ $feature ] );
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

$GLOBALS['features']['rest_users'] = true;

require __DIR__ . '/../../includes/security-hardening.php';

echo "Which routes the rule covers\n";

check(
	'the user collection is covered',
	blueworx_rest_users_route_is_restricted( '/wp/v2/users' ),
	true
);

check(
	'so is a single user by ID',
	blueworx_rest_users_route_is_restricted( '/wp/v2/users/1' ),
	true
);

check(
	'and so is anything else hanging off the collection',
	blueworx_rest_users_route_is_restricted( '/wp/v2/users/1/application-passwords' ),
	true
);

check(
	'the caller asking about itself is not covered',
	blueworx_rest_users_route_is_restricted( '/wp/v2/users/me' ),
	false
);

check(
	'nor is an unrelated route that merely starts the same way',
	blueworx_rest_users_route_is_restricted( '/wp/v2/posts' ),
	false
);

check(
	'a route named users under another namespace is left alone',
	blueworx_rest_users_route_is_restricted( '/surecart/v1/users' ),
	false
);

echo "\nWho is refused\n";

check(
	'an anonymous caller is refused the collection',
	blueworx_rest_users_request_denied( '/wp/v2/users', false ),
	true
);

check(
	'a caller who may list users is allowed through',
	blueworx_rest_users_request_denied( '/wp/v2/users', true ),
	false
);

check(
	'an anonymous caller is refused a single user too',
	blueworx_rest_users_request_denied( '/wp/v2/users/1', false ),
	true
);

check(
	'but never its own record, which wp-admin fetches on every page load',
	blueworx_rest_users_request_denied( '/wp/v2/users/me', false ),
	false
);

check(
	'and no other route is touched, whoever is asking',
	blueworx_rest_users_request_denied( '/wp/v2/posts', false ),
	false
);

echo "\nThe hook is registered when the feature is on\n";

check(
	'on rest_pre_dispatch',
	isset( $GLOBALS['hooks']['rest_pre_dispatch']['callback'] ) ? $GLOBALS['hooks']['rest_pre_dispatch']['callback'] : null,
	'blueworx_restrict_rest_users'
);

check(
	'taking all three arguments, or the route would be invisible',
	$GLOBALS['hooks']['rest_pre_dispatch']['args'],
	3
);

check(
	'ahead of support access, which answers for its own account first',
	$GLOBALS['hooks']['rest_pre_dispatch']['priority'] < 10,
	true
);

echo "\nThe route list can be widened\n";

$GLOBALS['filters']['blueworx_rest_user_routes'] = function ( $routes ) {
	$routes[] = '/wp/v2/authors';
	return $routes;
};

check(
	'a site can name another route that exposes the same data',
	blueworx_rest_users_route_is_restricted( '/wp/v2/authors' ),
	true
);

unset( $GLOBALS['filters']['blueworx_rest_user_routes'] );

finish();
