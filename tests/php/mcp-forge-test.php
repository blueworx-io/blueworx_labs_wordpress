<?php
/**
 * Connect Claude: the Forge tools, against a stand-in for Forge's REST routes.
 *
 * What each tool asks Forge for, how it turns Forge's answers and refusals
 * into something Claude can act on, and that nothing is listed or reachable
 * while Forge is off. The stand-in answers the way Forge's own controllers do
 * (JSON bodies, record versions, gate refusals as a 409 with what is unmet);
 * tests/mcp-connection.spec.js drives the real thing where Forge is installed.
 *
 * Run with: php tests/php/mcp-forge-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.VariableComment.Missing, Generic.Files.OneObjectStructurePerFile.MultipleFound -- Test stand-ins.

function wp_strip_all_tags( $text ) {
	return strip_tags( $text );
}

function sanitize_textarea_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function wp_get_current_user() {
	return (object) array( 'display_name' => 'Luke McFarland' );
}

class WP_REST_Request {
	public $method;
	public $route;
	public $query = array();
	public $body  = null;

	public function __construct( $method, $route ) {
		$this->method = $method;
		$this->route  = $route;
	}

	public function set_query_params( $params ) {
		$this->query = $params;
	}

	public function set_header( $key, $value ) {}

	public function set_body( $body ) {
		$this->body = json_decode( $body, true );
	}
}

class Fake_Response {
	public $data;
	public $status;

	public function __construct( $data, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	public function get_data() {
		return $this->data;
	}

	public function is_error() {
		return $this->status >= 400;
	}
}

/**
 * The stand-in Forge: a few items on two sites, and the routes the tools use.
 */
function forge_reset() {
	$item = function ( $id, $title, $level, $site, $parent = '', $extra = array() ) {
		return array_merge(
			array(
				'id'              => $id,
				'title'           => $title,
				'level'           => $level,
				'work_type'       => 'task',
				'stage'           => 'future-idea',
				'stage_label'     => 'Future Idea',
				'priority'        => 'normal',
				'primary_user_id' => '',
				'reviewer_id'     => '',
				'planned_due'     => '',
				'parent_id'       => $parent,
				'client_site_id'  => $site,
				'record_version'  => 1,
				'problem'         => '',
			),
			$extra
		);
	};

	$GLOBALS['forge'] = array(
		'items'    => array(
			'wi_p1' => $item( 'wi_p1', 'Website rebuild', 'project', 'cs_a' ),
			'wi_m1' => $item( 'wi_m1', 'Phase one', 'milestone', 'cs_a', 'wi_p1' ),
			'wi_f1' => $item(
				'wi_f1',
				'Build the header',
				'feature',
				'cs_a',
				'wi_m1',
				array(
					'stage'           => 'in-development',
					'stage_label'     => 'In Development',
					'primary_user_id' => 'usr_luke',
					'planned_due'     => '2026-11-10',
				)
			),
			'wi_f2' => $item( 'wi_f2', 'Review copy', 'feature', 'cs_a', 'wi_m1', array( 'reviewer_id' => 'usr_luke' ) ),
			'wi_p2' => $item( 'wi_p2', 'App launch', 'project', 'cs_b' ),
			'wi_f3' => $item( 'wi_f3', 'Store listing', 'feature', 'cs_b', 'wi_p2', array( 'primary_user_id' => 'usr_luke' ) ),
		),
		'calls'    => array(),
		'comments' => array(),
	);
}

function forge_answer( $status, $code, $message, $data = array() ) {
	return new Fake_Response(
		array(
			'code'    => $code,
			'message' => $message,
			'data'    => array_merge( array( 'status' => $status ), $data ),
		),
		$status
	);
}

function forge_matches( $item, $q ) {
	foreach ( array( 'level', 'stage' ) as $filter ) {
		if ( isset( $q[ $filter ] ) && ! in_array( $item[ $filter ], (array) $q[ $filter ], true ) ) {
			return false;
		}
	}

	if ( isset( $q['person'] ) && ! array_intersect( (array) $q['person'], array( $item['primary_user_id'], $item['reviewer_id'] ) ) ) {
		return false;
	}

	if ( isset( $q['due_to'] ) && ( '' === $item['planned_due'] || $item['planned_due'] > $q['due_to'] ) ) {
		return false;
	}

	return true;
}

function rest_do_request( $request ) {
	$forge = &$GLOBALS['forge'];
	$path  = substr( $request->route, strlen( '/blueworx-forge/v1' ) );
	$q     = $request->query;
	$body  = (array) $request->body;

	$forge['calls'][] = array( $request->method, $path, $q, $body );

	if ( '/stages' === $path ) {
		return new Fake_Response(
			array(
				'stages' => array(
					array(
						'id'    => 'future-idea',
						'label' => 'Future Idea',
					),
					array(
						'id'    => 'up-next',
						'label' => 'Up Next',
					),
					array(
						'id'    => 'in-development',
						'label' => 'In Development',
					),
					array(
						'id'    => 'in-review',
						'label' => 'In Review',
					),
				),
			)
		);
	}

	if ( '/people' === $path ) {
		return new Fake_Response(
			array(
				'people' => array(
					array(
						'id'           => 'usr_luke',
						'display_name' => 'Luke McFarland',
					),
					array(
						'id'           => 'usr_sam',
						'display_name' => 'Sam Jones',
					),
					array(
						'id'           => 'usr_sami',
						'display_name' => 'Samira Khan',
					),
				),
			)
		);
	}

	if ( '/client-sites' === $path ) {
		return new Fake_Response(
			array(
				'sites' => array(
					array(
						'id'          => 'cs_a',
						'name'        => 'acme.com',
						'client_name' => 'Acme',
					),
				),
			)
		);
	}

	if ( 'GET' === $request->method && in_array( $path, array( '/work-items', '/work-items-all' ), true ) ) {
		$items = array();

		foreach ( $forge['items'] as $item ) {
			if ( isset( $q['client_site_id'] ) && $item['client_site_id'] !== $q['client_site_id'] ) {
				continue;
			}

			if ( forge_matches( $item, $q ) ) {
				$items[] = $item;
			}
		}

		return new Fake_Response( array( 'items' => $items ) );
	}

	if ( 'POST' === $request->method && '/work-items' === $path ) {
		if ( 'cs_closed' === $body['client_site_id'] ) {
			return forge_answer( 409, 'bwx_forge_inactive_client_site', 'That site is closed; reactivate it before adding work.' );
		}

		$item = array_merge(
			$body,
			array(
				'id'             => 'wi_new',
				'stage'          => 'future-idea',
				'stage_label'    => 'Future Idea',
				'record_version' => 1,
			)
		);

		$forge['items']['wi_new'] = $item;

		return new Fake_Response(
			array(
				'ok'   => true,
				'item' => $item,
			)
		);
	}

	if ( ! preg_match( '#^/work-items/([a-z0-9_]+)(?:/([a-z]+))?$#', $path, $m ) ) {
		return forge_answer( 404, 'rest_no_route', 'No route was found matching the URL and request method.' );
	}

	if ( ! isset( $forge['items'][ $m[1] ] ) ) {
		return forge_answer( 404, 'bwx_forge_not_found', 'That work item does not exist.' );
	}

	$item = &$forge['items'][ $m[1] ];
	$sub  = $m[2] ?? '';

	if ( 'GET' === $request->method && '' === $sub ) {
		$children = array_values(
			array_filter(
				$forge['items'],
				function ( $child ) use ( $item ) {
					return $child['parent_id'] === $item['id'];
				}
			)
		);

		return new Fake_Response(
			array(
				'item'       => $item,
				'children'   => $children,
				'available'  => 'in-development' === $item['stage'] ? array( 'in-review' ) : array( 'up-next' ),
				'returns'    => 'in-development' === $item['stage'] ? array( 'up-next', 'future-idea' ) : array(),
				'readiness'  => array(
					'in-review' => array(
						'unmet'  => array(
							array(
								'label'        => 'Test steps written',
								'satisfied_by' => 'Write the steps to test it.',
							),
						),
						'checks' => array(
							array(
								'label'  => 'Capacity',
								'result' => 'pass',
							),
						),
					),
					'up-next'   => array(
						'unmet'  => array(),
						'checks' => array(),
					),
				),
				'seat_names' => array( 'usr_gone' => 'Former Staff' ),
				'comments'   => $forge['comments'],
			)
		);
	}

	if ( in_array( $request->method, array( 'PATCH', 'POST' ), true ) && 'comments' !== $sub ) {
		if ( ! isset( $body['record_version'] ) ) {
			return forge_answer( 400, 'bwx_forge_missing_version', 'This write did not say which version of the record it was made against.' );
		}

		if ( $body['record_version'] !== $item['record_version'] ) {
			return forge_answer( 409, 'bwx_forge_stale_write', 'This record changed since you loaded it. Your change was not saved.' );
		}
	}

	if ( 'PATCH' === $request->method ) {
		if ( isset( $body['planned_due'] ) && '2026-13-01' === $body['planned_due'] ) {
			return forge_answer( 400, 'bwx_forge_invalid_work_item', 'That change could not be saved.', array( 'fields' => array( 'planned_due' => 'That is not a date. Use YYYY-MM-DD.' ) ) );
		}

		unset( $body['record_version'] );
		$item = array_merge( $item, $body );
		++$item['record_version'];

		return new Fake_Response( array( 'item' => $item ) );
	}

	if ( 'transition' === $sub ) {
		if ( 'in-review' === $body['to'] ) {
			return new Fake_Response(
				array(
					'ok'        => false,
					'attempted' => 'in-review',
					'unmet'     => array(
						array(
							'label'        => 'Test steps written',
							'satisfied_by' => 'Write the steps to test it.',
						),
					),
					'checks'    => array(
						array(
							'label'  => 'Hours available',
							'result' => 'fail',
						),
						array(
							'label'  => 'Capacity',
							'result' => 'pass',
						),
					),
				),
				409
			);
		}

		$item['stage'] = $body['to'];
		++$item['record_version'];

		return new Fake_Response( array( 'item' => $item ) );
	}

	if ( 'return' === $sub ) {
		$item['stage'] = $body['to'];
		++$item['record_version'];

		return new Fake_Response( array( 'item' => $item ) );
	}

	if ( 'comments' === $sub ) {
		$comment             = array(
			'body'        => $body['body'],
			'visibility'  => $body['visibility'],
			'author_name' => 'Luke McFarland',
		);
		$forge['comments'][] = $comment;

		return new Fake_Response( array( 'comment' => $comment ) );
	}

	return forge_answer( 404, 'rest_no_route', 'No route was found matching the URL and request method.' );
}

function last_call( $method, $path ) {
	foreach ( array_reverse( $GLOBALS['forge']['calls'] ) as $call ) {
		if ( $call[0] === $method && $call[1] === $path ) {
			return $call;
		}
	}

	return null;
}

function error_of( $result ) {
	return is_wp_error( $result ) ? $result->get_error_code() : 'no error';
}

function message_of( $result ) {
	return is_wp_error( $result ) ? $result->get_error_message() : 'no error';
}

function ids_of( $list ) {
	return array_column( $list, 'id' );
}

// phpcs:enable

require __DIR__ . '/../../includes/mcp/forge.php';

forge_reset();

echo "With Forge off\n";

check( 'no Forge tools are listed', blueworx_mcp_forge_tool_definitions(), array() );
check( 'a call made anyway is refused plainly', error_of( blueworx_mcp_tool_forge_list_tasks( array() ) ), 'forge_inactive' );
check( 'site info says Forge is not there', blueworx_mcp_forge_site_info()['active'], false );
check( 'and Forge was never asked', $GLOBALS['forge']['calls'], array() );

define( 'BWX_FORGE_VERSION', '2.192.0' );

echo "\nWith Forge on\n";

check(
	'every Forge tool is listed',
	array_column( blueworx_mcp_forge_tool_definitions(), 'name' ),
	array( 'list_projects', 'get_project', 'list_tasks', 'get_task', 'create_task', 'update_task', 'add_task_comment' )
);
check( 'and every listed tool has a handler', array_keys( blueworx_mcp_forge_tool_handlers() ), array_column( blueworx_mcp_forge_tool_definitions(), 'name' ) );

$info = blueworx_mcp_forge_site_info();
check( 'site info names the Forge version', $info['version'], '2.192.0' );
check( 'and lists the sites', $info['sites'][0], array( 'id' => 'cs_a', 'name' => 'acme.com', 'client' => 'Acme' ) );
check( 'and the people', $info['people'][1], array( 'id' => 'usr_sam', 'name' => 'Sam Jones' ) );
check( 'and the statuses', $info['statuses'][2], array( 'id' => 'in-development', 'label' => 'In Development' ) );

echo "\nProjects\n";

$projects = blueworx_mcp_tool_forge_list_projects( array() );
check( 'every site\'s projects, and only projects', ids_of( $projects['projects'] ), array( 'wi_p1', 'wi_p2' ) );
check( 'across sites by default', last_call( 'GET', '/work-items-all' )[2]['level'], array( 'project' ) );

blueworx_mcp_tool_forge_list_projects( array( 'client_site_id' => 'cs_b' ) );
check( 'one site when asked', last_call( 'GET', '/work-items' )[2]['client_site_id'], 'cs_b' );

$project = blueworx_mcp_tool_forge_get_project( array( 'id' => 'wi_p1' ) );
check( 'a project comes with what is under it', ids_of( $project['children'] ), array( 'wi_m1' ) );
check( 'a task is not a project', error_of( blueworx_mcp_tool_forge_get_project( array( 'id' => 'wi_f1' ) ) ), 'not_a_project' );
check( 'an ID that could be a path is refused before Forge sees it', error_of( blueworx_mcp_tool_forge_get_project( array( 'id' => '../users' ) ) ), 'invalid_id' );
check( 'an unknown item says so in Forge\'s words', message_of( blueworx_mcp_tool_forge_get_project( array( 'id' => 'wi_nope' ) ) ), 'That work item does not exist.' );

echo "\nListing tasks\n";

$all = blueworx_mcp_tool_forge_list_tasks( array() );
check( 'tasks leave projects out', ids_of( $all['tasks'] ), array( 'wi_m1', 'wi_f1', 'wi_f2', 'wi_f3' ) );
check( 'the assignee is given by name', $all['tasks'][1]['assignee'], 'Luke McFarland' );
check( 'status and due date use the tools\' names', array( $all['tasks'][1]['status'], $all['tasks'][1]['due_date'] ), array( 'in-development', '2026-11-10' ) );

$under = blueworx_mcp_tool_forge_list_tasks( array( 'project_id' => 'wi_p1' ) );
check( 'a project\'s tasks include those two levels down', ids_of( $under['tasks'] ), array( 'wi_m1', 'wi_f1', 'wi_f2' ) );

$by_status = blueworx_mcp_tool_forge_list_tasks( array( 'status' => array( 'In Development' ) ) );
check( 'a status can be given by its name', ids_of( $by_status['tasks'] ), array( 'wi_f1' ) );
check( 'an unknown status lists the real ones', message_of( blueworx_mcp_tool_forge_list_tasks( array( 'status' => array( 'Done-ish' ) ) ) ), 'Unknown status "Done-ish". Forge\'s statuses are: Future Idea, Up Next, In Development, In Review.' );

$mine = blueworx_mcp_tool_forge_list_tasks( array( 'assignee' => 'luke' ) );
check( 'the assignee is the main person, not a reviewer', ids_of( $mine['tasks'] ), array( 'wi_f1', 'wi_f3' ) );
check( '"me" is the person who connected Claude', ids_of( blueworx_mcp_tool_forge_list_tasks( array( 'assignee' => 'me' ) )['tasks'] ), array( 'wi_f1', 'wi_f3' ) );
check( 'a name that fits two people is refused', error_of( blueworx_mcp_tool_forge_list_tasks( array( 'assignee' => 'sam' ) ) ), 'ambiguous_person' );
check( 'unless one fits it whole', ! is_wp_error( blueworx_mcp_tool_forge_list_tasks( array( 'assignee' => 'Sam Jones' ) ) ), true );
check( 'a stranger is refused', error_of( blueworx_mcp_tool_forge_list_tasks( array( 'assignee' => 'Nobody' ) ) ), 'unknown_person' );

check( 'due dates are passed as Forge\'s filter', ids_of( blueworx_mcp_tool_forge_list_tasks( array( 'due_before' => '2026-12-01' ) )['tasks'] ), array( 'wi_f1' ) );
check( 'a date that does not exist is refused', error_of( blueworx_mcp_tool_forge_list_tasks( array( 'due_before' => '2026-02-30' ) ) ), 'invalid_date' );
check( 'a level outside the three is refused', error_of( blueworx_mcp_tool_forge_list_tasks( array( 'level' => array( 'project' ) ) ) ), 'invalid_level' );

$limited = blueworx_mcp_tool_forge_list_tasks( array( 'limit' => 2 ) );
check( 'a limit shortens the list but not the total', array( count( $limited['tasks'] ), $limited['total'] ), array( 2, 4 ) );

echo "\nCreating\n";

$created = blueworx_mcp_tool_forge_create_task(
	array(
		'title'       => 'Fix footer',
		'project_id'  => 'wi_p1',
		'description' => '<b>Footer</b> overlaps on mobile',
		'assignee'    => 'Samira',
		'due_date'    => '2026-11-20',
	)
);
$sent    = last_call( 'POST', '/work-items' )[3];
check( 'it goes on the project\'s site, under it', array( $sent['client_site_id'], $sent['parent_id'] ), array( 'cs_a', 'wi_p1' ) );
check( 'with Forge\'s own defaults', array( $sent['level'], $sent['work_type'] ), array( 'feature', 'task' ) );
check( 'in Forge\'s field names, cleaned', array( $sent['problem'], $sent['primary_user_id'], $sent['planned_due'] ), array( 'Footer overlaps on mobile', 'usr_sami', '2026-11-20' ) );
check( 'and is logged on the Connect Claude screen', $created['_log'], array( 'created', 'Forge task wi_new Fix footer' ) );

check( 'a title is needed', error_of( blueworx_mcp_tool_forge_create_task( array( 'project_id' => 'wi_p1' ) ) ), 'missing_title' );
check( 'and somewhere to put it', error_of( blueworx_mcp_tool_forge_create_task( array( 'title' => 'Loose' ) ) ), 'missing_site' );
check( 'a site that disagrees with the project is refused', error_of( blueworx_mcp_tool_forge_create_task( array( 'title' => 'X', 'project_id' => 'wi_p1', 'client_site_id' => 'cs_b' ) ) ), 'site_mismatch' );
check( 'Forge\'s own refusal comes back in its words', message_of( blueworx_mcp_tool_forge_create_task( array( 'title' => 'X', 'client_site_id' => 'cs_closed' ) ) ), 'That site is closed; reactivate it before adding work.' );

echo "\nUpdating\n";

forge_reset();

$updated = blueworx_mcp_tool_forge_update_task(
	array(
		'id'     => 'wi_f2',
		'title'  => 'Review the copy',
		'status' => 'Up Next',
	)
);
check( 'the edit is made against the version read', last_call( 'PATCH', '/work-items/wi_f2' )[3], array( 'title' => 'Review the copy', 'record_version' => 1 ) );
check( 'and the move against the version after it', last_call( 'POST', '/work-items/wi_f2/transition' )[3], array( 'to' => 'up-next', 'record_version' => 2 ) );
check( 'both are reported', array( $updated['status'], $updated['changed'] ), array( 'up-next', array( 'title', 'status' ) ) );

$gate = blueworx_mcp_tool_forge_update_task(
	array(
		'id'     => 'wi_f1',
		'status' => 'in-review',
	)
);
check( 'a refused move says what is still needed', message_of( $gate ), 'Forge will not move this to "in-review" yet. Still needed: Test steps written (Write the steps to test it); Hours available.' );

$half = blueworx_mcp_tool_forge_update_task(
	array(
		'id'       => 'wi_f1',
		'due_date' => '2026-12-01',
		'status'   => 'in-review',
	)
);
check( 'an edit saved before a refused move is owned up to', 0 === strpos( message_of( $half ), 'Saved the change to due_date, but the status did not change.' ), true );
check( 'and really was saved', $GLOBALS['forge']['items']['wi_f1']['planned_due'], '2026-12-01' );

check( 'sending back needs a reason', error_of( blueworx_mcp_tool_forge_update_task( array( 'id' => 'wi_f1', 'status' => 'up-next' ) ) ), 'reason_required' );

$back = blueworx_mcp_tool_forge_update_task(
	array(
		'id'     => 'wi_f1',
		'status' => 'up-next',
		'reason' => 'Needs a design first',
	)
);
check( 'with one it goes through Forge\'s send-back', last_call( 'POST', '/work-items/wi_f1/return' )[3]['reason'], 'Needs a design first' );
check( 'and lands there', $back['status'], 'up-next' );

check( 'a move Forge does not offer lists the ones it does', message_of( blueworx_mcp_tool_forge_update_task( array( 'id' => 'wi_m1', 'status' => 'in-review' ) ) ), 'From "future-idea" this cannot go straight to "in-review". It can move to: up-next.' );
check( 'several fields change in one call', message_of( blueworx_mcp_tool_forge_update_task( array( 'id' => 'wi_f2', 'priority' => 'high', 'description' => 'x', 'due_date' => '2026-12-31', 'assignee' => '' ) ) ) === 'no error', true );
check( 'a cleared seat is sent empty', $GLOBALS['forge']['items']['wi_f2']['primary_user_id'], '' );
blueworx_mcp_tool_forge_update_task( array( 'id' => 'wi_f2', 'reviewer' => 'Sam Jones' ) );
check( 'a reviewer goes in Forge\'s reviewer seat', $GLOBALS['forge']['items']['wi_f2']['reviewer_id'], 'usr_sam' );
check( 'nothing to change is refused', error_of( blueworx_mcp_tool_forge_update_task( array( 'id' => 'wi_f2' ) ) ), 'nothing_to_change' );
check( 'an unknown priority is refused', error_of( blueworx_mcp_tool_forge_update_task( array( 'id' => 'wi_f2', 'priority' => 'asap' ) ) ), 'invalid_priority' );

$stub_fields = blueworx_mcp_forge_error(
	array(
		'code'    => 'bwx_forge_invalid_work_item',
		'message' => 'That change could not be saved.',
		'data'    => array( 'fields' => array( 'planned_due' => 'That is not a date. Use YYYY-MM-DD.' ) ),
	)
);
check( 'a field Forge rejects is named as the tool names it', $stub_fields->get_error_message(), 'That change could not be saved. due_date: That is not a date. Use YYYY-MM-DD.' );
check( 'a missing Forge route reads as Forge being off', message_of( blueworx_mcp_forge_error( array( 'code' => 'rest_no_route' ) ) ), 'Forge is not answering on this site. Check that the Forge plugin is active.' );

echo "\nComments\n";

$comment = blueworx_mcp_tool_forge_add_comment(
	array(
		'id'      => 'wi_f1',
		'comment' => 'Header done, needs review.',
	)
);
check( 'comments are internal unless asked', last_call( 'POST', '/work-items/wi_f1/comments' )[3], array( 'body' => 'Header done, needs review.', 'visibility' => 'internal' ) );
check( 'and logged', $comment['_log'][0], 'commented on' );

blueworx_mcp_tool_forge_add_comment(
	array(
		'id'                => 'wi_f1',
		'comment'           => 'Ready for you to look at.',
		'visible_to_client' => true,
	)
);
check( 'or shared with the client', last_call( 'POST', '/work-items/wi_f1/comments' )[3]['visibility'], 'client' );
check( 'an empty comment is refused', error_of( blueworx_mcp_tool_forge_add_comment( array( 'id' => 'wi_f1', 'comment' => '  ' ) ) ), 'missing_comment' );

$task = blueworx_mcp_tool_forge_get_task( array( 'id' => 'wi_f1' ) );
check( 'a task shows its comments', count( $task['comments'] ), 2 );
check( 'and what each next move still needs', $task['next_statuses'][0], array( 'status' => 'in-review', 'ready' => false, 'still_needed' => array( 'Test steps written (Write the steps to test it)' ) ) );
check( 'a passed check is not something needed', $task['next_statuses'][1], array( 'status' => 'up-next', 'ready' => true, 'still_needed' => array() ) );

finish();
