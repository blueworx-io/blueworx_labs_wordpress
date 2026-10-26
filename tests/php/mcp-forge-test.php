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

function wp_date( $format ) {
	return gmdate( $format );
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
				'id'                  => $id,
				'title'               => $title,
				'level'               => $level,
				'work_type'           => 'task',
				'stage'               => 'future-idea',
				'stage_label'         => 'Future Idea',
				'priority'            => 'normal',
				'primary_user_id'     => '',
				'reviewer_id'         => '',
				'planned_due'         => '',
				'parent_id'           => $parent,
				'client_site_id'      => $site,
				'record_version'      => 1,
				'problem'             => '',
				'client_confirmed_at' => 1,
				'source'              => 'internal',
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
					'reviewer_id'     => 'usr_sam',
					'planned_due'     => '2026-11-10',
					'hours_primary'   => 6,
					'hours_designer'  => 0,
					'hours_review'    => 1.2,
					'hours_delivery'  => 0.6,
					'review_target'   => '2026-11-12',
					'release_target'  => '2026-11-14',
				)
			),
			'wi_f2' => $item( 'wi_f2', 'Review copy', 'feature', 'cs_a', 'wi_m1', array( 'reviewer_id' => 'usr_luke' ) ),
			'wi_p2' => $item( 'wi_p2', 'App launch', 'project', 'cs_b' ),
			'wi_f3' => $item( 'wi_f3', 'Store listing', 'feature', 'cs_b', 'wi_p2', array( 'primary_user_id' => 'usr_luke' ) ),
		),
		'calls'    => array(),
		'comments' => array(),
		'records'  => array(),
	);
}

function forge_source_question() {
	return array(
		'id'           => 'G-FUTURE-IDEA-3',
		'label'        => 'Source',
		'satisfied_by' => 'Choose where this came from.',
		'by'           => 'record',
		'fields'       => array(),
		'options'      => array(
			array(
				'value' => 'client-request',
				'label' => 'Client request',
			),
			array(
				'value' => 'internal',
				'label' => 'Internal',
			),
		),
	);
}

/**
 * Where each stage goes next, on the stand-in's short path.
 */
function forge_next( $stage ) {
	$next = array(
		'future-idea'    => array( 'up-next' ),
		'up-next'        => array( 'in-development' ),
		'in-development' => array( 'in-review' ),
	);

	return $next[ $stage ] ?? array();
}

/**
 * Forge's gate registry, cut down to a question of each kind.
 */
function forge_gates() {
	$pick = function ( $id, $label, $options, $source = '' ) {
		return array(
			'id'      => $id,
			'label'   => $label,
			'by'      => 'record',
			'control' => 'pick',
			'source'  => $source,
			'options' => array_map(
				function ( $value, $label ) {
					return array(
						'value' => $value,
						'label' => $label,
					);
				},
				array_keys( $options ),
				$options
			),
		);
	};
	$box  = function ( $id, $label, $input ) {
		return array(
			'id'      => $id,
			'label'   => $label,
			'by'      => 'record',
			'control' => 'box',
			'input'   => $input,
			'options' => array(),
		);
	};

	return array(
		'ok'    => true,
		'gates' => array(
			'G-FUTURE-IDEA'   => array(
				array(
					'id'     => 'G-FUTURE-IDEA-1',
					'label'  => 'Item description',
					'by'     => 'field',
					'fields' => array( 'problem' ),
				),
				forge_source_question() + array(
					'control' => 'pick',
					'source'  => '',
				),
			),
			'G-TRIAGE'        => array(
				$pick( 'G-TRIAGE-6', 'Duplicate check', array( 'none' => 'No duplicate found' ), 'items' ),
				$pick(
					'G-TRIAGE-7',
					'Triage outcome',
					array(
						'proceed'  => 'Proceed',
						'rejected' => 'Rejected',
					)
				),
			),
			'G-BUG-TRACKING'  => array( $box( 'G-BUG-TRACKING-3', 'Reproduction steps', 'text' ) ),
			'G-DOCUMENTATION' => array( $pick( 'G-DOCUMENTATION-9', 'Documentation approval', array( 'approved' => 'Approved' ) ) ),
			'G-IN-REVIEW'     => array(
				$pick( 'G-IN-REVIEW-4', 'Review approval', array( 'approved' => 'Approved' ) ),
				$box( 'G-IN-REVIEW-5', 'Post-review hours adjustment', 'number' ),
			),
		),
	);
}

function forge_up_next_unmet( $item ) {
	$unmet = array();

	if ( '' === $item['source'] ) {
		$unmet[] = forge_source_question();
	}

	if ( empty( $item['client_confirmed_at'] ) ) {
		$unmet[] = array(
			'label'        => 'Client confirmed',
			'satisfied_by' => 'Confirm the client, or change it, before triage.',
			'fields'       => array( 'client_confirmed_at' ),
		);
	}

	if ( '' === $item['reviewer_id'] ) {
		$unmet[] = array(
			'label'        => 'Reviewing it assigned',
			'satisfied_by' => 'Say who is reviewing it.',
			'fields'       => array( 'reviewer_id' ),
		);
	}

	return $unmet;
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

	$assignees = $item['assignees'] ?? array();
	$assignees = is_string( $assignees ) ? (array) json_decode( $assignees, true ) : (array) $assignees;

	if ( isset( $q['person'] ) && ! array_intersect( (array) $q['person'], array_merge( array( $item['primary_user_id'], $item['reviewer_id'] ), $assignees ) ) ) {
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

	if ( '/gates' === $path ) {
		return new Fake_Response( forge_gates() );
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

	if ( '/capacity' === $path ) {
		return forge_capacity( $q );
	}

	if ( preg_match( '#^/capacity/person/([a-z_]+)$#', $path, $who ) ) {
		return forge_capacity_person( $who[1], $q );
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

	if ( ! preg_match( '#^/work-items/([a-z0-9_]+)(?:/([a-z-]+))?$#', $path, $m ) ) {
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
				'available'  => forge_next( $item['stage'] ),
				'returns'    => 'in-development' === $item['stage'] ? array( 'up-next' ) : array(),
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
						'unmet'  => forge_up_next_unmet( $item ),
						'checks' => array(),
						'all'    => 'future-idea' === $item['stage'] ? array( forge_source_question() ) : array(),
					),
				),
				'seat_names' => array( 'usr_gone' => 'Former Staff' ),
				'comments'   => $forge['comments'],
			)
		);
	}

	if ( 'gate' === $sub ) {
		if ( ! in_array( $body['requirement'], array( 'G-FUTURE-IDEA-3', 'G-TRIAGE-6', 'G-TRIAGE-7', 'G-BUG-TRACKING-3', 'G-DOCUMENTATION-9' ), true ) ) {
			return forge_answer( 400, 'bwx_forge_unknown_requirement', 'There is no such gate requirement.' );
		}

		if ( 'G-FUTURE-IDEA-3' === $body['requirement'] ) {
			$item['source'] = $body['value'];
		}

		$forge['records'][] = array( $body['requirement'], $body['value'] );

		return new Fake_Response( array( 'ok' => true ) );
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
		if ( isset( $body['hours_primary'] ) && $body['hours_primary'] > 100 ) {
			return forge_answer( 400, 'bwx_forge_invalid_work_item', 'That change could not be saved.', array( 'fields' => array( 'hours_primary' => 'At most 100 hours.' ) ) );
		}

		if ( isset( $body['planned_due'] ) && '2026-13-01' === $body['planned_due'] ) {
			return forge_answer( 400, 'bwx_forge_invalid_work_item', 'That change could not be saved.', array( 'fields' => array( 'planned_due' => 'That is not a date. Use YYYY-MM-DD.' ) ) );
		}

		unset( $body['record_version'] );
		$item = array_merge( $item, $body );
		++$item['record_version'];

		return new Fake_Response( array( 'item' => $item ) );
	}

	if ( 'confirm-client' === $sub ) {
		$item['client_confirmed_at'] = 1792400000;
		++$item['record_version'];

		return new Fake_Response( array( 'item' => $item ) );
	}

	if ( 'transition' === $sub ) {
		if ( 'up-next' === $body['to'] && array() !== forge_up_next_unmet( $item ) ) {
			return new Fake_Response(
				array(
					'ok'        => false,
					'attempted' => 'up-next',
					'unmet'     => forge_up_next_unmet( $item ),
				),
				409
			);
		}

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

/**
 * Forge's capacity grid: Luke tight in the first week, Sam not set up.
 */
function forge_capacity( $q ) {
	if ( ! empty( $GLOBALS['forge']['no_capacity'] ) ) {
		return forge_answer( 403, 'bwx_forge_not_permitted', 'You cannot see staff capacity.' );
	}

	$cell = function ( $from, $to, $available, $committed, $completed, $band ) {
		return array(
			'from'      => $from,
			'to'        => $to,
			'available' => $available,
			'committed' => $committed,
			'completed' => $completed,
			'remaining' => round( $available - $committed - $completed, 2 ),
			'band'      => $band,
		);
	};

	return new Fake_Response(
		array(
			'from'    => $q['from'],
			'to'      => $q['to'],
			'by'      => $q['by'],
			'periods' => array(),
			'people'  => array(
				array(
					'user_id'      => 'usr_luke',
					'display_name' => 'Luke McFarland',
					'periods'      => array(
						$cell( '2026-11-09', '2026-11-15', 37.5, 30, 2, 'tight' ),
						$cell( '2026-11-16', '2026-11-22', 37.5, 4, 0, 'clear' ),
					),
					'total'        => $cell( '2026-11-09', '2026-11-22', 75, 34, 2, 'clear' ),
				),
				array(
					'user_id'      => 'usr_sam',
					'display_name' => 'Sam Jones',
					'periods'      => array(
						$cell( '2026-11-09', '2026-11-15', 0, 1.2, 0, 'unrecorded' ),
						$cell( '2026-11-16', '2026-11-22', 0, 0, 0, 'unrecorded' ),
					),
					'total'        => $cell( '2026-11-09', '2026-11-22', 0, 1.2, 0, 'unrecorded' ),
				),
			),
			'only_me' => false,
		)
	);
}

/**
 * One person's drill-down: a task of their own, and a review they are covering.
 */
function forge_capacity_person( $user_id, $q ) {
	$allocation = function ( $item, $title, $role, $covering, $hours, $day, $status ) use ( $user_id ) {
		return array(
			'item_id'        => $item,
			'title'          => $title,
			'client_id'      => 'cl_acme',
			'client_site_id' => 'cs_a',
			'role'           => $role,
			'user_id'        => $user_id,
			'covering'       => $covering,
			'hours'          => $hours,
			'from'           => $day,
			'to'             => $day,
			'status'         => $status,
		);
	};

	return new Fake_Response(
		array(
			'user_id'          => $user_id,
			'display_name'     => 'Luke McFarland',
			'from'             => $q['from'],
			'to'               => $q['to'],
			'days'             => array(),
			'committed_by_day' => array(),
			'completed_by_day' => array(),
			'allocations'      => array(
				$allocation( 'wi_f1', 'Build the header', 'primary', '', 6, '2026-11-10', 'committed' ),
				$allocation( 'wi_f9', 'Check the footer', 'review', 'usr_sam', 1.5, '2026-11-11', 'completed' ),
			),
			'position'         => array(),
		)
	);
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
	array( 'list_projects', 'get_project', 'list_tasks', 'get_task', 'create_task', 'update_task', 'add_task_comment', 'get_team_capacity' )
);

$asks = array( 'designer', 'deliverer', 'who_pays', 'duplicate_check', 'triage_outcome', 'how_to_test', 'gate_answers', 'capacity_reason' );
check( 'update_task takes everything Forge asks for before In Review', array_values( array_intersect( $asks, array_keys( blueworx_mcp_forge_tool_definitions()[5]['inputSchema']['properties'] ) ) ), $asks );
check( 'and every listed tool has a handler', array_keys( blueworx_mcp_forge_tool_handlers() ), array_column( blueworx_mcp_forge_tool_definitions(), 'name' ) );

$info = blueworx_mcp_forge_site_info();
check( 'site info names the Forge version', $info['version'], '2.192.0' );
check(
	'and lists the sites',
	$info['sites'][0],
	array(
		'id'     => 'cs_a',
		'name'   => 'acme.com',
		'client' => 'Acme',
	)
);
check(
	'and the people',
	$info['people'][1],
	array(
		'id'   => 'usr_sam',
		'name' => 'Sam Jones',
	)
);
check(
	'and the statuses',
	$info['statuses'][2],
	array(
		'id'    => 'in-development',
		'label' => 'In Development',
	)
);

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
check(
	'a site that disagrees with the project is refused',
	error_of(
		blueworx_mcp_tool_forge_create_task(
			array(
				'title'          => 'X',
				'project_id'     => 'wi_p1',
				'client_site_id' => 'cs_b',
			)
		)
	),
	'site_mismatch'
);
check(
	'Forge\'s own refusal comes back in its words',
	message_of(
		blueworx_mcp_tool_forge_create_task(
			array(
				'title'          => 'X',
				'client_site_id' => 'cs_closed',
			)
		)
	),
	'That site is closed; reactivate it before adding work.'
);

echo "\nUpdating\n";

forge_reset();

$updated = blueworx_mcp_tool_forge_update_task(
	array(
		'id'     => 'wi_f2',
		'title'  => 'Review the copy',
		'status' => 'Up Next',
	)
);
check(
	'the edit is made against the version read',
	last_call( 'PATCH', '/work-items/wi_f2' )[3],
	array(
		'title'          => 'Review the copy',
		'record_version' => 1,
	)
);
check(
	'and the move against the version after it',
	last_call( 'POST', '/work-items/wi_f2/transition' )[3],
	array(
		'to'             => 'up-next',
		'record_version' => 2,
	)
);
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

check(
	'sending back needs a reason',
	error_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'     => 'wi_f1',
				'status' => 'up-next',
			)
		)
	),
	'reason_required'
);

$back = blueworx_mcp_tool_forge_update_task(
	array(
		'id'     => 'wi_f1',
		'status' => 'up-next',
		'reason' => 'Needs a design first',
	)
);
check( 'with one it goes through Forge\'s send-back', last_call( 'POST', '/work-items/wi_f1/return' )[3]['reason'], 'Needs a design first' );
check( 'and lands there', $back['status'], 'up-next' );

check(
	'a move Forge does not offer lists the ones it does',
	message_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'     => 'wi_f1',
				'status' => 'future-idea',
			)
		)
	),
	'From "up-next" this cannot go straight to "future-idea". It can move to: in-development.'
);
check(
	'several fields change in one call',
	message_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'          => 'wi_f2',
				'priority'    => 'high',
				'description' => 'x',
				'due_date'    => '2026-12-31',
				'assignee'    => '',
			)
		)
	) === 'no error',
	true
);
check( 'a cleared seat is sent empty', $GLOBALS['forge']['items']['wi_f2']['primary_user_id'], '' );
blueworx_mcp_tool_forge_update_task(
	array(
		'id'       => 'wi_f2',
		'reviewer' => 'Sam Jones',
	)
);
check( 'a reviewer goes in Forge\'s reviewer seat', $GLOBALS['forge']['items']['wi_f2']['reviewer_id'], 'usr_sam' );
check( 'nothing to change is refused', error_of( blueworx_mcp_tool_forge_update_task( array( 'id' => 'wi_f2' ) ) ), 'nothing_to_change' );
check(
	'an unknown priority is refused',
	error_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'       => 'wi_f2',
				'priority' => 'asap',
			)
		)
	),
	'invalid_priority'
);

$stub_fields = blueworx_mcp_forge_error(
	array(
		'code'    => 'bwx_forge_invalid_work_item',
		'message' => 'That change could not be saved.',
		'data'    => array( 'fields' => array( 'planned_due' => 'That is not a date. Use YYYY-MM-DD.' ) ),
	)
);
check( 'a field Forge rejects is named as the tool names it', $stub_fields->get_error_message(), 'That change could not be saved. due_date: That is not a date. Use YYYY-MM-DD.' );
check( 'a missing Forge route reads as Forge being off', message_of( blueworx_mcp_forge_error( array( 'code' => 'rest_no_route' ) ) ), 'Forge is not answering on this site. Check that the Forge plugin is active.' );

echo "\nGetting a new task out of Future Idea\n";

$GLOBALS['forge']['items']['wi_new2'] = array_merge(
	$GLOBALS['forge']['items']['wi_m1'],
	array(
		'id'                  => 'wi_new2',
		'title'               => 'Fresh idea',
		'level'               => 'feature',
		'parent_id'           => 'wi_m1',
		'source'              => '',
		'client_confirmed_at' => 0,
		'record_version'      => 1,
	)
);

check(
	'what is missing says which field gives it',
	blueworx_mcp_tool_forge_get_task( array( 'id' => 'wi_new2' ) )['next_statuses'][1]['still_needed'],
	array(
		'Source (Choose where this came from) [update_task: source]',
		'Client confirmed (Confirm the client, or change it, before triage) [update_task: confirm_client]',
		'Reviewing it assigned (Say who is reviewing it) [update_task: reviewer]',
	)
);
check(
	'a source Forge does not offer lists the ones it does',
	message_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'     => 'wi_new2',
				'source' => 'rumour',
			)
		)
	),
	'source must be one of: Client request, Internal.'
);

$walked = blueworx_mcp_tool_forge_update_task(
	array(
		'id'             => 'wi_new2',
		'reviewer'       => 'Sam Jones',
		'source'         => 'Client request',
		'confirm_client' => true,
		'status'         => 'Up Next',
	)
);
check( 'reviewer, source, client and move all go in one call', error_of( $walked ) . ' ' . ( is_array( $walked ) ? implode( ',', $walked['changed'] ) : message_of( $walked ) ), 'no error reviewer,confirm_client,source,status' );
check(
	'the source is recorded as Forge\'s answer, by its value',
	last_call( 'POST', '/work-items/wi_new2/gate' )[3],
	array(
		'requirement' => 'G-FUTURE-IDEA-3',
		'value'       => 'client-request',
		'evidence'    => '',
	)
);
check( 'the client is confirmed against the version after the edit', last_call( 'POST', '/work-items/wi_new2/confirm-client' )[3], array( 'record_version' => 2 ) );
check(
	'and the move against the version after that',
	last_call( 'POST', '/work-items/wi_new2/transition' )[3],
	array(
		'to'             => 'up-next',
		'record_version' => 3,
	)
);
check( 'so the task leaves Future Idea', $GLOBALS['forge']['items']['wi_new2']['stage'], 'up-next' );

echo "\nEverything Forge asks for on the way to In Review\n";

forge_reset();

$triaged = blueworx_mcp_tool_forge_update_task(
	array(
		'id'              => 'wi_f2',
		'who_pays'        => 'chargeable',
		'deliverer'       => 'Sam Jones',
		'designer'        => '',
		'how_to_test'     => 'Open the <b>page</b>',
		'duplicate_check' => 'none',
		'triage_outcome'  => 'Proceed',
		'gate_answers'    => array(
			'Documentation approval' => 'Approved',
			'reproduction steps'     => 'Click the button',
		),
	)
);
check(
	'who pays, how to test and the seats go in Forge\'s fields',
	last_call( 'PATCH', '/work-items/wi_f2' )[3],
	array(
		'test_description' => 'Open the page',
		'commercial_class' => 'chargeable',
		'designer_id'      => '',
		'deliverer_id'     => 'usr_sam',
		'record_version'   => 1,
	)
);
check(
	'each answer is recorded as Forge\'s value',
	$GLOBALS['forge']['records'],
	array(
		array( 'G-TRIAGE-6', 'none' ),
		array( 'G-TRIAGE-7', 'proceed' ),
		array( 'G-DOCUMENTATION-9', 'approved' ),
		array( 'G-BUG-TRACKING-3', 'Click the button' ),
	)
);
check( 'and reported by the name it was given', $triaged['changed'], array( 'how_to_test', 'who_pays', 'designer', 'deliverer', 'duplicate_check', 'triage_outcome', 'Documentation approval', 'Reproduction steps' ) );

blueworx_mcp_tool_forge_update_task(
	array(
		'id'              => 'wi_f2',
		'duplicate_check' => 'wi_f3',
	)
);
check( 'a duplicate is named by the task it duplicates', end( $GLOBALS['forge']['records'] ), array( 'G-TRIAGE-6', 'wi_f3' ) );

$calls = count( $GLOBALS['forge']['calls'] );
check(
	'a task that does not exist is not a duplicate',
	message_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'              => 'wi_f2',
				'title'           => 'Kept?',
				'duplicate_check' => 'wi_nope',
			)
		)
	),
	'duplicate_check must be one of: No duplicate found, or the ID of another task.'
);
check(
	'an outcome that ends the task is left to Forge',
	error_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'           => 'wi_f2',
				'gate_answers' => array( 'Triage outcome' => 'Rejected' ),
			)
		)
	),
	'outcome_ends_task'
);
check(
	'the review approval stays with the reviewer',
	error_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'           => 'wi_f2',
				'gate_answers' => array( 'Review approval' => 'Approved' ),
			)
		)
	),
	'answer_not_allowed'
);
check(
	'a question Forge does not ask lists the ones it does',
	message_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'           => 'wi_f2',
				'gate_answers' => array( 'Vibe check' => 'Yes' ),
			)
		)
	),
	'Forge asks nothing called "vibe-check" before In Review. It asks: Source, Duplicate check, Triage outcome, Reproduction steps, Documentation approval.'
);
check(
	'an answer Forge does not offer lists the ones it does',
	message_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'           => 'wi_f2',
				'gate_answers' => array( 'Documentation approval' => 'Maybe' ),
			)
		)
	),
	'"Documentation approval" must be one of: Approved.'
);
check(
	'an empty answer is refused',
	error_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'           => 'wi_f2',
				'gate_answers' => array( 'Documentation approval' => ' ' ),
			)
		)
	),
	'missing_answer'
);
check(
	'gate_answers must be a set of answers',
	error_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'           => 'wi_f2',
				'gate_answers' => 'Approved',
			)
		)
	),
	'invalid_gate_answers'
);
check(
	'who pays outside Forge\'s three is refused',
	error_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'       => 'wi_f2',
				'who_pays' => 'the client',
			)
		)
	),
	'invalid_who_pays'
);
check(
	'and a refused answer saves nothing else in the same call',
	array_filter(
		array_slice( $GLOBALS['forge']['calls'], $calls ),
		function ( $call ) {
			return 'GET' !== $call[0];
		}
	),
	array()
);

check(
	'a missing answer says which argument gives it',
	blueworx_mcp_forge_still_needed(
		array(
			'unmet' => array(
				array(
					'label'   => 'Duplicate check',
					'control' => 'pick',
				),
				array(
					'label'   => 'Documentation approval',
					'control' => 'pick',
				),
				array(
					'label'  => 'Who pays',
					'fields' => array( 'commercial_class' ),
				),
				array(
					'label'  => 'Deliverer assigned',
					'fields' => array( 'deliverer_id' ),
				),
				array( 'label' => 'Planned hours per role' ),
				array( 'label' => 'Capacity check' ),
				array(
					'label'  => 'How to test it',
					'fields' => array( 'test_description' ),
				),
			),
		)
	),
	array(
		'Duplicate check [update_task: duplicate_check]',
		'Documentation approval [update_task: gate_answers]',
		'Who pays [update_task: who_pays]',
		'Deliverer assigned [update_task: deliverer]',
		'Planned hours per role [update_task: assignee_hours]',
		'Capacity check [update_task: capacity_reason]',
		'How to test it [update_task: how_to_test]',
	)
);

echo "\nMoving several stages at once\n";

forge_reset();

$far = blueworx_mcp_tool_forge_update_task(
	array(
		'id'              => 'wi_f2',
		'status'          => 'In Development',
		'capacity_reason' => 'Agreed with the client',
	)
);
check(
	'it goes one stage at a time',
	array_values(
		array_column(
			array_filter(
				$GLOBALS['forge']['calls'],
				function ( $call ) {
					return '/work-items/wi_f2/transition' === $call[1];
				}
			),
			3
		)
	),
	array(
		array(
			'to'              => 'up-next',
			'record_version'  => 1,
			'capacity_reason' => 'Agreed with the client',
		),
		array(
			'to'              => 'in-development',
			'record_version'  => 2,
			'capacity_reason' => 'Agreed with the client',
		),
	)
);
check( 'and gets there', array( $far['status'], $far['changed'] ), array( 'in-development', array( 'status' ) ) );

$GLOBALS['forge']['items']['wi_f3']['reviewer_id'] = 'usr_sam';

$short = blueworx_mcp_tool_forge_update_task(
	array(
		'id'     => 'wi_f3',
		'status' => 'In Review',
	)
);
check( 'a move Forge stops part-way says how far it got', message_of( $short ), 'Moved it as far as "in-development", but no further. Forge will not move this to "in-review" yet. Still needed: Test steps written (Write the steps to test it); Hours available.' );
check( 'and really got that far', $GLOBALS['forge']['items']['wi_f3']['stage'], 'in-development' );

$GLOBALS['forge']['items']['wi_f3']['stage'] = 'future-idea';
check(
	'an edit alongside is owned up to as well',
	0 === strpos(
		message_of(
			blueworx_mcp_tool_forge_update_task(
				array(
					'id'     => 'wi_f3',
					'title'  => 'Listing',
					'status' => 'In Review',
				)
			)
		),
		'Saved the change to title and moved it as far as "in-development", but no further.'
	),
	true
);

check(
	'a gate on the first step stops it where it is',
	0 === strpos(
		message_of(
			blueworx_mcp_tool_forge_update_task(
				array(
					'id'     => 'wi_m1',
					'status' => 'In Review',
				)
			)
		),
		'Forge will not move this to "up-next" yet.'
	),
	true
);
check( 'without a capacity reason none is sent', array_key_exists( 'capacity_reason', last_call( 'POST', '/work-items/wi_m1/transition' )[3] ), false );

echo "\nComments\n";

$comment = blueworx_mcp_tool_forge_add_comment(
	array(
		'id'      => 'wi_f1',
		'comment' => 'Header done, needs review.',
	)
);
check(
	'comments are internal unless asked',
	last_call( 'POST', '/work-items/wi_f1/comments' )[3],
	array(
		'body'       => 'Header done, needs review.',
		'visibility' => 'internal',
	)
);
check( 'and logged', $comment['_log'][0], 'commented on' );

blueworx_mcp_tool_forge_add_comment(
	array(
		'id'                => 'wi_f1',
		'comment'           => 'Ready for you to look at.',
		'visible_to_client' => true,
	)
);
check( 'or shared with the client', last_call( 'POST', '/work-items/wi_f1/comments' )[3]['visibility'], 'client' );
check(
	'an empty comment is refused',
	error_of(
		blueworx_mcp_tool_forge_add_comment(
			array(
				'id'      => 'wi_f1',
				'comment' => '  ',
			)
		)
	),
	'missing_comment'
);

$task = blueworx_mcp_tool_forge_get_task( array( 'id' => 'wi_f1' ) );
check( 'a task shows its comments', count( $task['comments'] ), 2 );
check(
	'and what each next move still needs',
	$task['next_statuses'][0],
	array(
		'status'       => 'in-review',
		'ready'        => false,
		'still_needed' => array( 'Test steps written (Write the steps to test it)' ),
	)
);
check(
	'a passed check is not something needed',
	$task['next_statuses'][1],
	array(
		'status'       => 'up-next',
		'ready'        => true,
		'still_needed' => array(),
	)
);

echo "\nPlanned hours on tasks\n";

forge_reset();

$listed = blueworx_mcp_tool_forge_list_tasks( array() );
check(
	'a listed task shows each role\'s hours',
	$listed['tasks'][1]['hours'],
	array(
		'assignee'  => 6.0,
		'designer'  => 0.0,
		'reviewer'  => 1.2,
		'deliverer' => 0.6,
	)
);

$full = blueworx_mcp_tool_forge_get_task( array( 'id' => 'wi_f1' ) );
check( 'a task in full shows the date each role\'s hours sit on', array( $full['design_by'], $full['due_date'], $full['review_by'], $full['release_by'] ), array( '', '2026-11-10', '2026-11-12', '2026-11-14' ) );

blueworx_mcp_tool_forge_create_task(
	array(
		'title'          => 'Plan the launch',
		'project_id'     => 'wi_p1',
		'assignee_hours' => 5,
		'review_by'      => '2026-11-21',
	)
);
$sent = last_call( 'POST', '/work-items' )[3];
check( 'hours and dates go in on a new task, in Forge\'s names', array( $sent['hours_primary'], $sent['review_target'] ), array( 5, '2026-11-21' ) );
check( 'and a role not mentioned is left for Forge to fill in', array_key_exists( 'hours_review', $sent ), false );

$planned = blueworx_mcp_tool_forge_update_task(
	array(
		'id'              => 'wi_f2',
		'reviewer_hours'  => '2.5',
		'designer_hours'  => 0,
		'deliverer_hours' => 1,
		'design_by'       => '2026-11-18',
		'release_by'      => '',
	)
);
check(
	'an update sets hours and dates per role',
	last_call( 'PATCH', '/work-items/wi_f2' )[3],
	array(
		'design_target'  => '2026-11-18',
		'release_target' => '',
		'hours_designer' => 0,
		'hours_review'   => 2.5,
		'hours_delivery' => 1,
		'record_version' => 1,
	)
);
check( 'and says what changed in the tools\' names', $planned['changed'], array( 'design_by', 'release_by', 'designer_hours', 'reviewer_hours', 'deliverer_hours' ) );

check(
	'negative hours are refused',
	error_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'             => 'wi_f2',
				'assignee_hours' => -1,
			)
		)
	),
	'invalid_hours'
);
check(
	'so are hours that are not a number',
	error_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'             => 'wi_f2',
				'assignee_hours' => 'lots',
			)
		)
	),
	'invalid_hours'
);
check(
	'and a role date that does not exist',
	error_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'        => 'wi_f2',
				'review_by' => '2026-11-31',
			)
		)
	),
	'invalid_date'
);
check(
	'hours Forge refuses are named as the tool names them',
	message_of(
		blueworx_mcp_tool_forge_update_task(
			array(
				'id'             => 'wi_f2',
				'assignee_hours' => 500,
			)
		)
	),
	'That change could not be saved. assignee_hours: At most 100 hours.'
);

echo "\nPeople and hours on chores and reminders\n";

// Forge keeps a chore's or reminder's people in a list and each person's time
// in hours_each, not in the assignee seat. Its REST answers send the list
// decoded; a raw row would still hold it as JSON, so both are tried.
forge_reset();
$GLOBALS['forge']['items']['wi_c1'] = array_merge(
	$GLOBALS['forge']['items']['wi_f2'],
	array(
		'id'          => 'wi_c1',
		'title'       => 'Weekly backups check',
		'parent_id'   => '',
		'reviewer_id' => '',
		'assignees'   => array( 'usr_sam' ),
		'hours_each'  => 0.75,
	)
);
$GLOBALS['forge']['items']['wi_c2'] = array_merge(
	$GLOBALS['forge']['items']['wi_c1'],
	array(
		'id'         => 'wi_c2',
		'title'      => 'Renew the domain',
		'assignees'  => '["usr_luke"]',
		'hours_each' => '0.5',
	)
);

$listed = blueworx_mcp_tool_forge_list_tasks( array() );
$chores = array_values(
	array_filter(
		$listed['tasks'],
		function ( $task ) {
			return in_array( $task['id'], array( 'wi_c1', 'wi_c2' ), true );
		}
	)
);
check( 'a chore shows its person by name', array( $chores[0]['assignee'], $chores[0]['assignee_id'] ), array( 'Sam Jones', 'usr_sam' ) );
check( 'and their time as the assignee\'s hours', $chores[0]['hours']['assignee'], 0.75 );
check( 'a list still held as JSON is read too', array( $chores[1]['assignee'], $chores[1]['hours']['assignee'] ), array( 'Luke McFarland', 0.5 ) );
check( 'a normal task keeps its own assignee and hours', array( $listed['tasks'][1]['assignee'], $listed['tasks'][1]['hours']['assignee'] ), array( 'Luke McFarland', 6.0 ) );

$chore = blueworx_mcp_tool_forge_get_task( array( 'id' => 'wi_c1' ) );
check( 'a chore in full shows its person and hours', array( $chore['assignee'], $chore['hours']['assignee'] ), array( 'Sam Jones', 0.75 ) );

check( 'listing by assignee finds their chores', ids_of( blueworx_mcp_tool_forge_list_tasks( array( 'assignee' => 'Sam Jones' ) )['tasks'] ), array( 'wi_c1' ) );

forge_reset();

echo "\nWhen work was added\n";

// Forge keeps when each item was added and last changed, as Unix times.
foreach ( array(
	'wi_m1' => array( 1767225600, 1767225600 ), // 2026-01-01.
	'wi_f1' => array( 1780272000, 1782864000 ), // 2026-06-01, changed 2026-07-01.
	'wi_f2' => array( 1772323200, 1772323200 ), // 2026-03-01.
) as $id => $times ) {
	$GLOBALS['forge']['items'][ $id ]['created_at'] = $times[0];
	$GLOBALS['forge']['items'][ $id ]['updated_at'] = $times[1];
}

$dated = blueworx_mcp_tool_forge_list_tasks( array() );
check( 'each task says when it was added and last changed', array( $dated['tasks'][1]['created_date'], $dated['tasks'][1]['updated_date'] ), array( '2026-06-01', '2026-07-01' ) );
check( 'a task Forge has no date for says so with nothing', $dated['tasks'][3]['created_date'], '' );
check( 'work added before a date', ids_of( blueworx_mcp_tool_forge_list_tasks( array( 'created_before' => '2026-03-01' ) )['tasks'] ), array( 'wi_m1', 'wi_f2' ) );
check( 'work added after a date', ids_of( blueworx_mcp_tool_forge_list_tasks( array( 'created_after' => '2026-02-01' ) )['tasks'] ), array( 'wi_f1', 'wi_f2' ) );
check( 'oldest first', ids_of( blueworx_mcp_tool_forge_list_tasks( array( 'oldest_first' => true ) )['tasks'] ), array( 'wi_m1', 'wi_f2', 'wi_f1', 'wi_f3' ) );
check( 'a created date that does not exist is refused', error_of( blueworx_mcp_tool_forge_list_tasks( array( 'created_before' => '2026-02-30' ) ) ), 'invalid_date' );
check( 'the full task says when it was added', blueworx_mcp_tool_forge_get_task( array( 'id' => 'wi_f1' ) )['created_date'], '2026-06-01' );

forge_reset();

echo "\nThe client as reviewer\n";

// Forge keeps the client in the reviewer seat as "client", which is no person.
blueworx_mcp_tool_forge_update_task(
	array(
		'id'       => 'wi_f2',
		'reviewer' => 'The client',
	)
);
check( 'the client can be made the reviewer', last_call( 'PATCH', '/work-items/wi_f2' )[3]['reviewer_id'], 'client' );
check( 'and shows as the client', blueworx_mcp_tool_forge_get_task( array( 'id' => 'wi_f2' ) )['people']->reviewer, 'The client' );

blueworx_mcp_tool_forge_create_task(
	array(
		'title'          => 'Sign off the homepage',
		'client_site_id' => 'cs_a',
		'reviewer'       => 'client',
	)
);
check( 'a new task can have the client review it', last_call( 'POST', '/work-items' )[3]['reviewer_id'], 'client' );
check( 'the client is no one\'s assignee', error_of( blueworx_mcp_tool_forge_update_task( array( 'id' => 'wi_f2', 'assignee' => 'client' ) ) ), 'unknown_person' );

forge_reset();

echo "\nTeam capacity\n";

$team = blueworx_mcp_tool_forge_get_team_capacity(
	array(
		'from' => '2026-11-09',
		'to'   => '2026-11-22',
	)
);
check(
	'it asks Forge for the whole team by the week',
	last_call( 'GET', '/capacity' )[2],
	array(
		'from' => '2026-11-09',
		'to'   => '2026-11-22',
		'by'   => 'weeks',
	)
);
check(
	'each person gets a line per week',
	$team['people'][0]['weeks'][0],
	array(
		'from'      => '2026-11-09',
		'to'        => '2026-11-15',
		'available' => 37.5,
		'booked'    => 30.0,
		'done'      => 2.0,
		'left'      => 5.5,
		'status'    => 'tight',
	)
);
check( 'and a total for the range', array( $team['people'][0]['name'], $team['people'][0]['id'], $team['people'][0]['total']['left'] ), array( 'Luke McFarland', 'usr_luke', 39.0 ) );
check( 'someone with no working week set is flagged as such', $team['people'][1]['total']['status'], 'not set up' );
check( 'and nobody is listed with tasks unless asked', isset( $team['people'][0]['tasks'] ), false );

blueworx_mcp_tool_forge_get_team_capacity(
	array(
		'from' => '2026-11-09',
		'to'   => '2026-11-09',
		'by'   => 'days',
	)
);
check( 'it can go day by day', last_call( 'GET', '/capacity' )[2]['by'], 'days' );
check(
	'which renames the lines',
	isset(
		blueworx_mcp_tool_forge_get_team_capacity(
			array(
				'from' => '2026-11-09',
				'to'   => '2026-11-10',
				'by'   => 'days',
			)
		)['people'][0]['days']
	),
	true
);

blueworx_mcp_tool_forge_get_team_capacity( array() );
check( 'with no dates it reads the next four weeks', array_values( array_slice( last_call( 'GET', '/capacity' )[2], 0, 2 ) ), array( gmdate( 'Y-m-d' ), gmdate( 'Y-m-d', strtotime( '+27 days' ) ) ) );

blueworx_mcp_tool_forge_get_team_capacity( array( 'from' => '2026-12-01' ) );
check( 'a start alone runs four weeks from it', last_call( 'GET', '/capacity' )[2]['to'], '2026-12-28' );

$luke = blueworx_mcp_tool_forge_get_team_capacity(
	array(
		'from'   => '2026-11-09',
		'to'     => '2026-11-22',
		'person' => 'luke',
	)
);
check( 'naming a person narrows it to them', array_column( $luke['people'], 'id' ), array( 'usr_luke' ) );
check(
	'and lists the tasks taking up their time',
	last_call( 'GET', '/capacity/person/usr_luke' )[2],
	array(
		'from' => '2026-11-09',
		'to'   => '2026-11-22',
	)
);
check(
	'each with its role, hours and date',
	$luke['people'][0]['tasks'][0],
	array(
		'task_id' => 'wi_f1',
		'title'   => 'Build the header',
		'role'    => 'assignee',
		'hours'   => 6.0,
		'from'    => '2026-11-10',
		'to'      => '2026-11-10',
		'status'  => 'to do',
	)
);
check( 'and who they are covering for', array( $luke['people'][0]['tasks'][1]['role'], $luke['people'][0]['tasks'][1]['covering_for'], $luke['people'][0]['tasks'][1]['status'] ), array( 'reviewer', 'Sam Jones', 'done' ) );

check(
	'dates in the wrong order are refused',
	error_of(
		blueworx_mcp_tool_forge_get_team_capacity(
			array(
				'from' => '2026-11-22',
				'to'   => '2026-11-09',
			)
		)
	),
	'invalid_range'
);
check( 'a date that does not exist is refused', error_of( blueworx_mcp_tool_forge_get_team_capacity( array( 'from' => '2026-02-30' ) ) ), 'invalid_date' );
check( 'an unknown person is refused', error_of( blueworx_mcp_tool_forge_get_team_capacity( array( 'person' => 'Nobody' ) ) ), 'unknown_person' );

$GLOBALS['forge']['no_capacity'] = true;
check( 'someone Forge will not show capacity to is told in Forge\'s words', message_of( blueworx_mcp_tool_forge_get_team_capacity( array() ) ), 'You cannot see staff capacity.' );

finish();
