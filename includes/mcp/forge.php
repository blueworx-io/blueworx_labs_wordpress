<?php
/**
 * BlueWorx: Connect Claude — the Forge tools.
 *
 * Forge keeps its projects and tasks in tables of its own, so the content
 * tools cannot see them. These tools reach them the same way the content tools
 * reach pages: through Forge's own REST routes, run inside this request as the
 * administrator who approved the connection (rest_do_request()). Forge's
 * permission checks, its workflow gates and its change history all apply
 * exactly as they do in the Forge app, and nothing here reads or writes its
 * tables directly.
 *
 * In Forge a project and a task are one kind of record, a work item, at one
 * of four levels. "Projects" here are the top level; "tasks" are the other
 * three. A task's status is its Forge stage, and it changes by moving the item
 * through Forge's workflow, never by editing it, so a move Forge's gates
 * refuse comes back with what is still missing. Everything those gates ask
 * for on the way to In Review can be given through update_task, approvals
 * included; the review approval and the release stay with the people Forge
 * names.
 *
 * Planned hours are set per role on each task, and each role's hours sit on
 * that role's own date. get_team_capacity reads Forge's capacity figures, which
 * add those hours up against everyone's working week and leave, so Claude can
 * see who has room before it books anything.
 *
 * With Forge switched off none of this is listed, and a call made anyway is
 * answered with a plain refusal rather than an error from WordPress.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forge's REST namespace.
 */
define( 'BLUEWORX_MCP_FORGE_NAMESPACE', 'blueworx-forge/v1' );

/**
 * Whether Forge is running on this site.
 *
 * @return bool
 */
function blueworx_mcp_forge_active() {
	return defined( 'BWX_FORGE_VERSION' );
}

/**
 * The levels below a project, which these tools call tasks.
 *
 * @return string[] Levels, highest first.
 */
function blueworx_mcp_forge_task_levels() {
	return array( 'milestone', 'feature', 'sub-feature' );
}

/**
 * Maps the Forge tool names to the functions that run them.
 *
 * Always registered, so a call made while Forge is off is told why rather than
 * told the tool does not exist.
 *
 * @return array Callables keyed by tool name.
 */
function blueworx_mcp_forge_tool_handlers() {
	return array(
		'list_projects'     => 'blueworx_mcp_tool_forge_list_projects',
		'get_project'       => 'blueworx_mcp_tool_forge_get_project',
		'list_tasks'        => 'blueworx_mcp_tool_forge_list_tasks',
		'get_task'          => 'blueworx_mcp_tool_forge_get_task',
		'create_task'       => 'blueworx_mcp_tool_forge_create_task',
		'update_task'       => 'blueworx_mcp_tool_forge_update_task',
		'add_task_comment'  => 'blueworx_mcp_tool_forge_add_comment',
		'get_team_capacity' => 'blueworx_mcp_tool_forge_get_team_capacity',
	);
}

/**
 * Describes the Forge tools, for tools/list. Empty while Forge is off.
 *
 * @return array Tool definitions.
 */
function blueworx_mcp_forge_tool_definitions() {
	if ( ! blueworx_mcp_forge_active() ) {
		return array();
	}

	$item_id  = array(
		'type'        => 'string',
		'description' => 'Forge item ID, e.g. "wi_abc123".',
	);
	$site     = array(
		'type'        => 'string',
		'description' => 'Forge client site ID. get_site_info lists them under forge.sites.',
	);
	$status   = array(
		'type'        => 'string',
		'description' => 'Forge status (stage), by ID or name, e.g. "in-development" or "In Review". get_site_info lists them under forge.statuses.',
	);
	$person   = array(
		'type'        => 'string',
		'description' => 'A Forge person, by ID ("usr_…"), by name, or "me". get_site_info lists them under forge.people.',
	);
	$date     = array(
		'type'        => 'string',
		'description' => 'Date as YYYY-MM-DD.',
	);
	$priority = array(
		'type' => 'string',
		'enum' => array( 'low', 'normal', 'high', 'urgent' ),
	);
	$planning = blueworx_mcp_forge_planning_properties();
	$readonly = array(
		'readOnlyHint'  => true,
		'openWorldHint' => false,
	);
	$writes   = array(
		'readOnlyHint'    => false,
		'destructiveHint' => false,
		'openWorldHint'   => false,
	);

	return array(
		array(
			'name'        => 'list_projects',
			'title'       => 'List Forge projects',
			'description' => 'Lists Forge projects (the top level of Forge work), with status, site, client and progress.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'client_site_id'   => $site,
					'search'           => array(
						'type'        => 'string',
						'description' => 'Words to search for.',
					),
					'include_archived' => array(
						'type'        => 'boolean',
						'description' => 'Include archived projects (default false).',
					),
				),
			),
			'annotations' => $readonly,
		),
		array(
			'name'        => 'get_project',
			'title'       => 'Get a Forge project',
			'description' => 'Gets one Forge project in full, with the work directly under it.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array( 'id' => $item_id ),
				'required'   => array( 'id' ),
			),
			'annotations' => $readonly,
		),
		array(
			'name'        => 'list_tasks',
			'title'       => 'List Forge tasks',
			'description' => 'Lists Forge tasks (milestones, features and sub-features). Filters combine; leave them out to list everything open.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'project_id'       => array(
						'type'        => 'string',
						'description' => 'Only work anywhere under this project.',
					),
					'client_site_id'   => $site,
					'status'           => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Only these statuses, by ID or name.',
					),
					'assignee'         => $person,
					'due_after'        => array(
						'type'        => 'string',
						'description' => 'Only work due on or after this date (YYYY-MM-DD).',
					),
					'due_before'       => array(
						'type'        => 'string',
						'description' => 'Only work due on or before this date (YYYY-MM-DD).',
					),
					'level'            => array(
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => blueworx_mcp_forge_task_levels(),
						),
						'description' => 'Only these levels (default all three).',
					),
					'search'           => array(
						'type'        => 'string',
						'description' => 'Words to search for.',
					),
					'include_archived' => array(
						'type'        => 'boolean',
						'description' => 'Include archived work (default false).',
					),
					'limit'            => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 200,
						'description' => 'Most to return (default 50). The total is always given.',
					),
				),
			),
			'annotations' => $readonly,
		),
		array(
			'name'        => 'get_task',
			'title'       => 'Get a Forge task',
			'description' => 'Gets one Forge task in full: description, people, dates, the statuses it can move to next and what each still needs, and recent comments.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array( 'id' => $item_id ),
				'required'   => array( 'id' ),
			),
			'annotations' => $readonly,
		),
		array(
			'name'        => 'create_task',
			'title'       => 'Create a Forge task',
			'description' => 'Creates a Forge task. It starts at Future Idea; use update_task to move it on. Give a project (or other parent) or a client site.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'title'          => array(
						'type'        => 'string',
						'description' => 'Title.',
					),
					'project_id'     => array(
						'type'        => 'string',
						'description' => 'The project, milestone or feature to put it under. Its site is used.',
					),
					'client_site_id' => $site,
					'description'    => array(
						'type'        => 'string',
						'description' => 'What the work is (Forge\'s "Item description").',
					),
					'level'          => array(
						'type'        => 'string',
						'enum'        => blueworx_mcp_forge_task_levels(),
						'description' => 'Level (default feature, as in Forge).',
					),
					'work_type'      => array(
						'type'        => 'string',
						'enum'        => array( 'task', 'feature', 'bug', 'feedback' ),
						'description' => 'Kind of work (default task).',
					),
					'assignee'       => $person,
					'reviewer'       => array(
						'type'        => 'string',
						'description' => 'Who reviews it, someone other than the assignee. Forge needs one before the task leaves Future Idea.',
					),
					'due_date'       => $date,
					'priority'       => $priority,
				) + $planning,
				'required'   => array( 'title' ),
			),
			'annotations' => $writes,
		),
		array(
			'name'        => 'update_task',
			'title'       => 'Update a Forge task',
			'description' => 'Changes a Forge task. Only the fields given change. A new status moves the task through Forge\'s workflow, one stage at a time if it is several ahead (up to In Review); if Forge refuses a move, the reason and what is still needed come back, and the other changes in the same call are still saved. Everything Forge asks for before In Review can be given here: out of Future Idea a reviewer, source and confirm_client; out of Triage priority, duplicate_check, triage_outcome and who_pays; into work a deliverer, hours and dates; before review how_to_test; and any other gate answer through gate_answers. get_task says what each next move still needs.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'id'              => $item_id,
					'status'          => array(
						'type'        => 'string',
						'description' => $status['description'] . ' Several stages ahead is fine: the task is moved one stage at a time until it gets there or Forge stops it.',
					),
					'reason'          => array(
						'type'        => 'string',
						'description' => 'Why, when sending work back to an earlier status. Required for that.',
					),
					'title'           => array(
						'type'        => 'string',
						'description' => 'Title.',
					),
					'description'     => array(
						'type'        => 'string',
						'description' => 'What the work is.',
					),
					'due_date'        => array(
						'type'        => 'string',
						'description' => 'Date as YYYY-MM-DD, or "" to clear it.',
					),
					'assignee'        => array(
						'type'        => 'string',
						'description' => 'A Forge person by ID, name or "me", or "" to clear the seat.',
					),
					'reviewer'        => array(
						'type'        => 'string',
						'description' => 'Who reviews it (someone other than the assignee), by ID, name or "me", or "" to clear.',
					),
					'priority'        => $priority,
					'source'          => array(
						'type'        => 'string',
						'enum'        => array( 'client-request', 'internal', 'bug-report', 'meeting' ),
						'description' => 'Where the work came from. Forge asks for this before a task leaves Future Idea.',
					),
					'confirm_client'  => array(
						'type'        => 'boolean',
						'description' => 'Confirm the task is for the client it is filed under. Forge asks for this before Triage. Only set it when you know the client is right.',
					),
					'designer'        => array(
						'type'        => 'string',
						'description' => 'Who designs it, by ID, name or "me", or "" for no design (the task then skips Design).',
					),
					'deliverer'       => array(
						'type'        => 'string',
						'description' => 'Who releases it, by ID, name or "me", or "" to clear. Forge needs one before work starts.',
					),
					'who_pays'        => array(
						'type'        => 'string',
						'enum'        => array( 'chargeable', 'free-bug', 'free-general' ),
						'description' => 'Who pays: chargeable (the client), free-bug (a bug we caused) or free-general (nobody). Forge asks for this before leaving Triage.',
					),
					'duplicate_check' => array(
						'type'        => 'string',
						'description' => '"none" when nothing duplicates this task, or the ID of the task it duplicates. Forge asks for this before leaving Triage.',
					),
					'triage_outcome'  => array(
						'type'        => 'string',
						'enum'        => array( 'proceed' ),
						'description' => 'The triage decision. Forge asks for this before leaving Triage. Only proceed is given here: rejecting, deferring or closing as a duplicate ends the task, which is done in Forge.',
					),
					'how_to_test'     => array(
						'type'        => 'string',
						'description' => 'How to test it, for the reviewer. Forge asks for this before In Review.',
					),
					'gate_answers'    => array(
						'type'                 => 'object',
						'additionalProperties' => array( 'type' => 'string' ),
						'description'          => 'Any other answer Forge asks for before In Review, keyed by its name as get_task shows it, e.g. {"Documentation approval": "Approved", "Bug classification": "Regression", "Responsive states": "Done"}. Give a choice by its name, or the text or number a box asks for. Recorded as the person who connected Claude.',
					),
					'capacity_reason' => array(
						'type'        => 'string',
						'description' => 'Why the move goes ahead although it over-books someone. Forge asks for this when leaving Up Next would put a person over their hours; read get_team_capacity first.',
					),
				) + $planning,
				'required'   => array( 'id' ),
			),
			'annotations' => array(
				'readOnlyHint'    => false,
				'destructiveHint' => true,
				'openWorldHint'   => false,
			),
		),
		array(
			'name'        => 'add_task_comment',
			'title'       => 'Comment on a Forge task',
			'description' => 'Adds a comment to a Forge project or task. Internal (staff only) unless visible_to_client is true.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'id'                => $item_id,
					'comment'           => array(
						'type'        => 'string',
						'description' => 'The comment.',
					),
					'visible_to_client' => array(
						'type'        => 'boolean',
						'description' => 'Let the client see it (default false).',
					),
				),
				'required'   => array( 'id', 'comment' ),
			),
			'annotations' => $writes,
		),
		array(
			'name'        => 'get_team_capacity',
			'title'       => 'Get team capacity',
			'description' => 'Shows who has room, week by week: each person\'s available hours (their working week, less leave), hours booked on work still to do, hours used on finished work, and hours left. status is clear, tight (80% or more used), over-booked, or not set up (nobody has entered their working week). Only work from Up Next onwards books time, and each role\'s hours sit on that role\'s date. Name a person to also see the tasks taking up their time. Read this before setting hours or dates, so nobody is over-booked.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'from'   => array(
						'type'        => 'string',
						'description' => 'First day, YYYY-MM-DD (default today).',
					),
					'to'     => array(
						'type'        => 'string',
						'description' => 'Last day, YYYY-MM-DD (default four weeks from the first). At most a year.',
					),
					'by'     => array(
						'type'        => 'string',
						'enum'        => array( 'weeks', 'days' ),
						'description' => 'One line per week (default) or per day.',
					),
					'person' => $person,
				),
			),
			'annotations' => $readonly,
		),
	);
}

/**
 * The hours and dates a task can be planned with, for create_task and
 * update_task.
 *
 * @return array Input schema properties.
 */
function blueworx_mcp_forge_planning_properties() {
	$properties = array();

	foreach ( blueworx_mcp_forge_role_hours() as $arg => $column ) {
		$properties[ $arg ] = array(
			'type'        => 'number',
			'minimum'     => 0,
			'description' => 'Planned hours for the ' . substr( $arg, 0, -6 ) . '.'
				. ( 'assignee_hours' === $arg ? ' Forge fills in reviewer and deliverer hours from this (20% and 10%) unless you give them.' : '' ),
		);
	}

	$what = array(
		'design_by'  => 'When design is due',
		'review_by'  => 'When review is due',
		'release_by' => 'When it is released',
	);

	foreach ( blueworx_mcp_forge_role_dates() as $arg => $column ) {
		$properties[ $arg ] = array(
			'type'        => 'string',
			'description' => $what[ $arg ] . ', YYYY-MM-DD, or "" to clear. That role\'s hours are booked on this day. Dates run design_by, due_date, review_by, release_by.',
		);
	}

	return $properties;
}

/**
 * Each role's planned hours: the tool argument, and Forge's field.
 *
 * @return array Forge field keyed by argument.
 */
function blueworx_mcp_forge_role_hours() {
	return array(
		'assignee_hours'  => 'hours_primary',
		'designer_hours'  => 'hours_designer',
		'reviewer_hours'  => 'hours_review',
		'deliverer_hours' => 'hours_delivery',
	);
}

/**
 * The role dates besides the due date: the tool argument, and Forge's field.
 *
 * @return array Forge field keyed by argument.
 */
function blueworx_mcp_forge_role_dates() {
	return array(
		'design_by'  => 'design_target',
		'review_by'  => 'review_target',
		'release_by' => 'release_target',
	);
}

/**
 * Runs a request through Forge's own REST routes, as the current user.
 *
 * Forge reads its write bodies as JSON, so a body goes in as JSON rather than
 * as parameters.
 *
 * @param string     $method HTTP method.
 * @param string     $route  Route under Forge's namespace, e.g. /work-items.
 * @param array      $query  Query parameters.
 * @param array|null $body   JSON body, for writes.
 * @return array|WP_Error Response data, or why Forge refused.
 */
function blueworx_mcp_forge_request( $method, $route, $query = array(), $body = null ) {
	$request = new WP_REST_Request( $method, '/' . BLUEWORX_MCP_FORGE_NAMESPACE . $route );

	if ( ! empty( $query ) ) {
		$request->set_query_params( $query );
	}

	if ( null !== $body ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
	}

	$response = rest_do_request( $request );
	$data     = $response->get_data();

	if ( $response->is_error() ) {
		return blueworx_mcp_forge_error( is_array( $data ) ? $data : array() );
	}

	return is_array( $data ) ? $data : array();
}

/**
 * Turns a Forge refusal into one sentence Claude can act on.
 *
 * Forge says what is wrong in two places a bare error message would lose: the
 * fields an edit got wrong, and the requirements a workflow gate is still
 * waiting for.
 *
 * @param array $data Response data.
 * @return WP_Error
 */
function blueworx_mcp_forge_error( $data ) {
	if ( isset( $data['attempted'] ) || isset( $data['unmet'] ) ) {
		$needed  = blueworx_mcp_forge_still_needed( $data );
		$message = 'Forge will not move this to "' . ( $data['attempted'] ?? '' ) . '" yet.';

		if ( ! empty( $needed ) ) {
			$message .= ' Still needed: ' . implode( '; ', $needed ) . '.';
		}

		return new WP_Error( 'forge_gate', $message );
	}

	$code = (string) ( $data['code'] ?? 'forge_error' );

	if ( 'rest_no_route' === $code ) {
		return new WP_Error( $code, 'Forge is not answering on this site. Check that the Forge plugin is active.' );
	}

	$message = isset( $data['message'] ) ? wp_strip_all_tags( (string) $data['message'] ) : 'Forge refused that.';
	$fields  = (array) ( $data['data']['fields'] ?? array() );

	if ( ! empty( $fields ) ) {
		$names = blueworx_mcp_forge_field_names();
		$parts = array();

		foreach ( $fields as $field => $problem ) {
			$parts[] = ( $names[ $field ] ?? $field ) . ': ' . $problem;
		}

		$message .= ' ' . implode( ' ', $parts );
	}

	return new WP_Error( $code, $message );
}

/**
 * What a workflow gate is still waiting for, each with how to give it.
 *
 * Read from a gate refusal or from the single-item view's readiness, which
 * share the shape: requirements not yet met, and system checks that failed.
 *
 * @param array $gate Gate answer, with 'unmet' and 'checks'.
 * @return string[] One line per missing thing.
 */
function blueworx_mcp_forge_still_needed( $gate ) {
	$needed = array();

	// The update_task argument that gives each thing Forge may ask for.
	$tool = array_merge( blueworx_mcp_forge_field_names(), array( 'client_confirmed_at' => 'confirm_client' ) );
	$asks = blueworx_mcp_forge_answer_args() + array(
		'planned-hours-per-role' => 'assignee_hours',
		'capacity-check'         => 'capacity_reason',
	);

	foreach ( (array) ( $gate['unmet'] ?? array() ) as $requirement ) {
		$label = (string) ( $requirement['label'] ?? $requirement['id'] ?? '' );
		$how   = rtrim( (string) ( $requirement['satisfied_by'] ?? '' ), '.' );
		$line  = '' === $how ? $label : $label . ' (' . $how . ')';
		$arg   = $asks[ blueworx_mcp_forge_slug( $label ) ] ?? '';

		if ( '' === $arg && in_array( $requirement['control'] ?? '', array( 'pick', 'box' ), true ) ) {
			$arg = 'gate_answers';
		}

		foreach ( (array) ( $requirement['fields'] ?? array() ) as $field ) {
			$arg = $tool[ $field ] ?? $arg;
		}

		$needed[] = '' === $arg ? $line : $line . ' [update_task: ' . $arg . ']';
	}

	foreach ( (array) ( $gate['checks'] ?? array() ) as $check ) {
		if ( 'fail' === ( $check['result'] ?? '' ) ) {
			$needed[] = (string) ( $check['label'] ?? '' );
		}
	}

	return array_values( array_unique( array_filter( $needed ) ) );
}

/**
 * Forge's field names, as these tools name them.
 *
 * @return array Tool name keyed by Forge field.
 */
function blueworx_mcp_forge_field_names() {
	return array(
		'problem'         => 'description',
		'planned_due'     => 'due_date',
		'primary_user_id'  => 'assignee',
		'reviewer_id'      => 'reviewer',
		'designer_id'      => 'designer',
		'deliverer_id'     => 'deliverer',
		'commercial_class' => 'who_pays',
		'test_description' => 'how_to_test',
		'parent_id'        => 'project_id',
		'stage'            => 'status',
	) + array_flip( blueworx_mcp_forge_role_dates() ) + array_flip( blueworx_mcp_forge_role_hours() );
}

/**
 * The gate answers update_task has an argument of its own for, keyed by the
 * name Forge gives the question. Anything else answerable goes through
 * gate_answers.
 *
 * @return array Argument keyed by the question's slug.
 */
function blueworx_mcp_forge_answer_args() {
	return array(
		'source'          => 'source',
		'duplicate-check' => 'duplicate_check',
		'triage-outcome'  => 'triage_outcome',
	);
}

/**
 * The gates whose answers Claude may give: every one a task passes on its way
 * to In Review. Review approval and release stay with the people Forge names.
 *
 * @return string[] Gate names.
 */
function blueworx_mcp_forge_answerable_gates() {
	return array( 'G-FUTURE-IDEA', 'G-TRIAGE', 'G-BUG-TRACKING', 'G-DOCUMENTATION', 'G-UP-NEXT', 'G-DESIGN', 'G-IN-DEVELOPMENT' );
}

/**
 * Refuses every Forge tool while Forge is off.
 *
 * @return WP_Error|null Null when Forge is running.
 */
function blueworx_mcp_forge_unavailable() {
	if ( blueworx_mcp_forge_active() ) {
		return null;
	}

	return new WP_Error( 'forge_inactive', 'Forge is not active on this site, so there are no projects or tasks to work with.' );
}

/**
 * Reduces a name or ID to a comparable form: "In Review" and "in-review" match.
 *
 * @param string $value Name or ID.
 * @return string
 */
function blueworx_mcp_forge_slug( $value ) {
	return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( trim( (string) $value ) ) ), '-' );
}

/**
 * Forge's statuses, from its own registry.
 *
 * @return array|WP_Error Label keyed by status ID.
 */
function blueworx_mcp_forge_statuses() {
	$answer = blueworx_mcp_forge_request( 'GET', '/stages' );

	if ( is_wp_error( $answer ) ) {
		return $answer;
	}

	$statuses = array();

	foreach ( (array) ( $answer['stages'] ?? array() ) as $stage ) {
		$statuses[ (string) $stage['id'] ] = (string) $stage['label'];
	}

	return $statuses;
}

/**
 * Looks up a status given by ID or name.
 *
 * @param string $given    What was asked for.
 * @param array  $statuses blueworx_mcp_forge_statuses().
 * @return string|WP_Error The status ID.
 */
function blueworx_mcp_forge_resolve_status( $given, $statuses ) {
	$wanted = blueworx_mcp_forge_slug( $given );

	foreach ( $statuses as $id => $label ) {
		if ( blueworx_mcp_forge_slug( $id ) === $wanted || blueworx_mcp_forge_slug( $label ) === $wanted ) {
			return (string) $id;
		}
	}

	return new WP_Error( 'unknown_status', 'Unknown status "' . $given . '". Forge\'s statuses are: ' . implode( ', ', $statuses ) . '.' );
}

/**
 * Forge's people, keyed by ID.
 *
 * @return array|WP_Error Name keyed by person ID.
 */
function blueworx_mcp_forge_people() {
	$answer = blueworx_mcp_forge_request( 'GET', '/people' );

	if ( is_wp_error( $answer ) ) {
		return $answer;
	}

	$people = array();

	foreach ( (array) ( $answer['people'] ?? array() ) as $person ) {
		$people[ (string) $person['id'] ] = (string) $person['display_name'];
	}

	return $people;
}

/**
 * Looks up a person given by ID, name or "me".
 *
 * A name matches whole first and in part second, and only an answer that
 * names one person is taken: assigning work to the wrong one of two Lukes is
 * worse than asking which.
 *
 * @param string $given  What was asked for.
 * @param array  $people blueworx_mcp_forge_people().
 * @return string|WP_Error The person's ID.
 */
function blueworx_mcp_forge_resolve_person( $given, $people ) {
	$given = trim( (string) $given );

	if ( isset( $people[ $given ] ) ) {
		return $given;
	}

	if ( 'me' === strtolower( $given ) ) {
		$given = (string) wp_get_current_user()->display_name;
	}

	$wanted = strtolower( $given );
	$whole  = array();
	$part   = array();

	foreach ( $people as $id => $name ) {
		if ( strtolower( $name ) === $wanted ) {
			$whole[] = $id;
		} elseif ( '' !== $wanted && false !== strpos( strtolower( $name ), $wanted ) ) {
			$part[] = $id;
		}
	}

	$found = ! empty( $whole ) ? $whole : $part;

	if ( 1 === count( $found ) ) {
		return (string) $found[0];
	}

	if ( count( $found ) > 1 ) {
		$names = array_map(
			function ( $id ) use ( $people ) {
				return $people[ $id ] . ' (' . $id . ')';
			},
			$found
		);

		return new WP_Error( 'ambiguous_person', '"' . $given . '" matches more than one person: ' . implode( ', ', $names ) . '. Use their ID.' );
	}

	return new WP_Error( 'unknown_person', 'Nobody in Forge is called "' . $given . '". People: ' . implode( ', ', $people ) . '.' );
}

/**
 * Checks a date argument.
 *
 * @param string $value Date.
 * @param string $name  Argument name, for the message.
 * @return true|WP_Error
 */
function blueworx_mcp_forge_check_date( $value, $name ) {
	$parts = explode( '-', (string) $value );

	if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value ) && checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
		return true;
	}

	return new WP_Error( 'invalid_date', $name . ' must be a real date as YYYY-MM-DD, e.g. 2026-11-01.' );
}

/**
 * Trims a Forge work item down to what is useful in a list.
 *
 * @param array $item   Forge work item.
 * @param array $people Name keyed by person ID.
 * @return array Item.
 */
function blueworx_mcp_forge_shape( $item, $people ) {
	$assignee = (string) ( $item['primary_user_id'] ?? '' );

	$out = array(
		'id'           => (string) ( $item['id'] ?? '' ),
		'title'        => (string) ( $item['title'] ?? '' ),
		'level'        => (string) ( $item['level'] ?? '' ),
		'work_type'    => (string) ( $item['work_type'] ?? '' ),
		'status'       => (string) ( $item['stage'] ?? '' ),
		'status_label' => (string) ( $item['stage_label'] ?? '' ),
		'priority'     => (string) ( $item['priority'] ?? '' ),
		'assignee'     => '' === $assignee ? '' : ( $people[ $assignee ] ?? $assignee ),
		'assignee_id'  => $assignee,
		'due_date'     => (string) ( $item['planned_due'] ?? '' ),
		'parent_id'    => (string) ( $item['parent_id'] ?? '' ),
		'hours'        => array(),
	);

	foreach ( blueworx_mcp_forge_role_hours() as $arg => $column ) {
		$out['hours'][ substr( $arg, 0, -6 ) ] = (float) ( $item[ $column ] ?? 0 );
	}

	foreach ( array(
		'client_site_id' => 'client_site_id',
		'site_name'      => 'site',
		'client_name'    => 'client',
		'progress'       => 'progress',
	) as $field => $name ) {
		if ( isset( $item[ $field ] ) ) {
			$out[ $name ] = $item[ $field ];
		}
	}

	if ( ! empty( $item['archived'] ) ) {
		$out['archived'] = true;
	}

	return $out;
}

/**
 * Reads one Forge item in full (Forge's single-item view).
 *
 * @param array $args Tool arguments, with an id.
 * @return array|WP_Error The view.
 */
function blueworx_mcp_forge_show( $args ) {
	$id = isset( $args['id'] ) && is_scalar( $args['id'] ) ? trim( (string) $args['id'] ) : '';

	if ( 1 !== preg_match( '/^[A-Za-z0-9_\-]+$/', $id ) ) {
		return new WP_Error( 'invalid_id', 'Give the Forge item ID, e.g. "wi_abc123".' );
	}

	return blueworx_mcp_forge_request( 'GET', '/work-items/' . $id );
}

/**
 * Shapes Forge's single-item view for reading in full.
 *
 * @param array $view   Forge's single-item view.
 * @param array $people Name keyed by person ID.
 * @return array Item.
 */
function blueworx_mcp_forge_shape_full( $view, $people ) {
	$item  = (array) ( $view['item'] ?? array() );
	$names = array_merge( $people, (array) ( $view['seat_names'] ?? array() ) );
	$out   = blueworx_mcp_forge_shape( $item, $names );

	$out['description']         = (string) ( $item['problem'] ?? '' );
	$out['scope']               = (string) ( $item['scope'] ?? '' );
	$out['requirements']        = (string) ( $item['requirements'] ?? '' );
	$out['acceptance_criteria'] = (string) ( $item['acceptance_criteria'] ?? '' );
	$out['start_date']          = (string) ( $item['planned_start'] ?? '' );

	foreach ( blueworx_mcp_forge_role_dates() as $arg => $column ) {
		$out[ $arg ] = (string) ( $item[ $column ] ?? '' );
	}

	$out['people'] = array();

	foreach ( array(
		'designer_id'  => 'designer',
		'reviewer_id'  => 'reviewer',
		'deliverer_id' => 'deliverer',
	) as $field => $seat ) {
		$person = (string) ( $item[ $field ] ?? '' );

		if ( '' !== $person ) {
			$out['people'][ $seat ] = $names[ $person ] ?? $person;
		}
	}

	$out['people'] = (object) $out['people'];

	$next = array();

	foreach ( (array) ( $view['readiness'] ?? array() ) as $to => $readiness ) {
		$missing = blueworx_mcp_forge_still_needed( (array) $readiness );
		$next[]  = array(
			'status'       => (string) $to,
			'ready'        => empty( $missing ),
			'still_needed' => $missing,
		);
	}

	$out['next_statuses'] = $next;
	$out['can_send_back'] = array_values( (array) ( $view['returns'] ?? array() ) );

	$out['comments'] = array_map(
		function ( $comment ) {
			$time = (int) ( $comment['created_at'] ?? 0 );

			return array(
				'author'     => (string) ( $comment['author_name'] ?? '' ),
				'comment'    => (string) ( $comment['body'] ?? '' ),
				'visibility' => (string) ( $comment['visibility'] ?? '' ),
				'date'       => $time > 0 ? gmdate( 'Y-m-d\TH:i:s\Z', $time ) : '',
			);
		},
		array_slice( (array) ( $view['comments'] ?? array() ), -20 )
	);

	$out['children'] = array_map(
		function ( $child ) use ( $names ) {
			return blueworx_mcp_forge_shape( (array) $child, $names );
		},
		(array) ( $view['children'] ?? array() )
	);

	return $out;
}

/**
 * Tool: list_projects.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_forge_list_projects( $args ) {
	$off = blueworx_mcp_forge_unavailable();

	if ( null !== $off ) {
		return $off;
	}

	$query = array( 'level' => array( 'project' ) );
	$site  = isset( $args['client_site_id'] ) && is_scalar( $args['client_site_id'] ) ? trim( (string) $args['client_site_id'] ) : '';

	if ( isset( $args['search'] ) && is_scalar( $args['search'] ) && '' !== trim( (string) $args['search'] ) ) {
		$query['search'] = sanitize_text_field( (string) $args['search'] );
	}

	if ( ! empty( $args['include_archived'] ) ) {
		$query['include_archived'] = true;
	}

	if ( '' !== $site ) {
		$query['client_site_id'] = $site;
	}

	$answer = blueworx_mcp_forge_request( 'GET', '' === $site ? '/work-items-all' : '/work-items', $query );

	if ( is_wp_error( $answer ) ) {
		return $answer;
	}

	$people = blueworx_mcp_forge_people();
	$people = is_wp_error( $people ) ? array() : $people;

	$projects = array();

	foreach ( (array) ( $answer['items'] ?? array() ) as $item ) {
		$projects[] = blueworx_mcp_forge_shape( (array) $item, $people );
	}

	return array(
		'total'    => count( $projects ),
		'projects' => $projects,
	);
}

/**
 * Tool: get_project.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_forge_get_project( $args ) {
	$off = blueworx_mcp_forge_unavailable();

	if ( null !== $off ) {
		return $off;
	}

	$view = blueworx_mcp_forge_show( $args );

	if ( is_wp_error( $view ) ) {
		return $view;
	}

	$level = (string) ( $view['item']['level'] ?? '' );

	if ( 'project' !== $level ) {
		return new WP_Error( 'not_a_project', 'That item is a ' . $level . ', not a project. Use get_task for it.' );
	}

	$people = blueworx_mcp_forge_people();

	return blueworx_mcp_forge_shape_full( $view, is_wp_error( $people ) ? array() : $people );
}

/**
 * Tool: list_tasks.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_forge_list_tasks( $args ) {
	$off = blueworx_mcp_forge_unavailable();

	if ( null !== $off ) {
		return $off;
	}

	$levels = blueworx_mcp_forge_task_levels();

	if ( isset( $args['level'] ) ) {
		$asked  = array_map( 'strval', (array) $args['level'] );
		$levels = array_values( array_intersect( $levels, $asked ) );

		if ( empty( $levels ) ) {
			return new WP_Error( 'invalid_level', 'level must be one or more of: ' . implode( ', ', blueworx_mcp_forge_task_levels() ) . '.' );
		}
	}

	$query = array( 'level' => $levels );

	if ( ! empty( $args['status'] ) ) {
		$statuses = blueworx_mcp_forge_statuses();

		if ( is_wp_error( $statuses ) ) {
			return $statuses;
		}

		$query['stage'] = array();

		foreach ( (array) $args['status'] as $given ) {
			$status = blueworx_mcp_forge_resolve_status( (string) $given, $statuses );

			if ( is_wp_error( $status ) ) {
				return $status;
			}

			$query['stage'][] = $status;
		}
	}

	foreach ( array(
		'due_after'  => 'due_from',
		'due_before' => 'due_to',
	) as $arg => $filter ) {
		if ( isset( $args[ $arg ] ) && '' !== $args[ $arg ] ) {
			$valid = blueworx_mcp_forge_check_date( $args[ $arg ], $arg );

			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$query[ $filter ] = (string) $args[ $arg ];
		}
	}

	if ( isset( $args['search'] ) && is_scalar( $args['search'] ) && '' !== trim( (string) $args['search'] ) ) {
		$query['search'] = sanitize_text_field( (string) $args['search'] );
	}

	if ( ! empty( $args['include_archived'] ) ) {
		$query['include_archived'] = true;
	}

	$people = blueworx_mcp_forge_people();

	if ( is_wp_error( $people ) ) {
		return $people;
	}

	$assignee = '';

	if ( isset( $args['assignee'] ) && '' !== trim( (string) $args['assignee'] ) ) {
		$assignee = blueworx_mcp_forge_resolve_person( (string) $args['assignee'], $people );

		if ( is_wp_error( $assignee ) ) {
			return $assignee;
		}

		// Forge's person filter matches any seat; the assignee is the main one,
		// narrowed below.
		$query['person'] = array( $assignee );
	}

	$site  = isset( $args['client_site_id'] ) && is_scalar( $args['client_site_id'] ) ? trim( (string) $args['client_site_id'] ) : '';
	$under = null;

	if ( ! empty( $args['project_id'] ) ) {
		$project = blueworx_mcp_forge_show( array( 'id' => $args['project_id'] ) );

		if ( is_wp_error( $project ) ) {
			return $project;
		}

		$site  = (string) ( $project['item']['client_site_id'] ?? '' );
		$under = blueworx_mcp_forge_descendants( (string) $project['item']['id'], $site );

		if ( is_wp_error( $under ) ) {
			return $under;
		}
	}

	if ( '' !== $site ) {
		$query['client_site_id'] = $site;
	}

	$answer = blueworx_mcp_forge_request( 'GET', '' === $site ? '/work-items-all' : '/work-items', $query );

	if ( is_wp_error( $answer ) ) {
		return $answer;
	}

	$tasks = array();

	foreach ( (array) ( $answer['items'] ?? array() ) as $item ) {
		$item = (array) $item;

		if ( null !== $under && ! isset( $under[ (string) $item['id'] ] ) ) {
			continue;
		}

		if ( '' !== $assignee && (string) ( $item['primary_user_id'] ?? '' ) !== $assignee ) {
			continue;
		}

		$tasks[] = blueworx_mcp_forge_shape( $item, $people );
	}

	$limit = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;

	return array(
		'total' => count( $tasks ),
		'shown' => min( $limit, count( $tasks ) ),
		'tasks' => array_slice( $tasks, 0, $limit ),
	);
}

/**
 * Every item anywhere under one, on its site.
 *
 * Forge's parent filter is one level deep, and a project's tasks sit up to
 * three below it, so this walks the site's whole tree once.
 *
 * @param string $root    The item to look under.
 * @param string $site_id Its site.
 * @return array|WP_Error IDs (as keys) of everything under it.
 */
function blueworx_mcp_forge_descendants( $root, $site_id ) {
	$answer = blueworx_mcp_forge_request(
		'GET',
		'/work-items',
		array(
			'client_site_id'   => $site_id,
			'include_archived' => true,
		)
	);

	if ( is_wp_error( $answer ) ) {
		return $answer;
	}

	$children = array();

	foreach ( (array) ( $answer['items'] ?? array() ) as $item ) {
		$children[ (string) ( $item['parent_id'] ?? '' ) ][] = (string) $item['id'];
	}

	$found = array();
	$queue = array( $root );

	while ( ! empty( $queue ) ) {
		$parent = array_shift( $queue );

		foreach ( $children[ $parent ] ?? array() as $child ) {
			if ( ! isset( $found[ $child ] ) ) {
				$found[ $child ] = true;
				$queue[]         = $child;
			}
		}
	}

	return $found;
}

/**
 * Tool: get_task.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_forge_get_task( $args ) {
	$off = blueworx_mcp_forge_unavailable();

	if ( null !== $off ) {
		return $off;
	}

	$view = blueworx_mcp_forge_show( $args );

	if ( is_wp_error( $view ) ) {
		return $view;
	}

	$people = blueworx_mcp_forge_people();

	return blueworx_mcp_forge_shape_full( $view, is_wp_error( $people ) ? array() : $people );
}

/**
 * Collects the editable fields from tool arguments, in Forge's names.
 *
 * @param array $args   Tool arguments.
 * @param array $people Name keyed by person ID.
 * @return array|WP_Error Forge fields.
 */
function blueworx_mcp_forge_write_fields( $args, $people ) {
	$fields = array();

	if ( isset( $args['title'] ) && is_scalar( $args['title'] ) ) {
		$fields['title'] = sanitize_text_field( (string) $args['title'] );
	}

	if ( isset( $args['description'] ) && is_scalar( $args['description'] ) ) {
		$fields['problem'] = sanitize_textarea_field( (string) $args['description'] );
	}

	if ( isset( $args['due_date'] ) && is_scalar( $args['due_date'] ) ) {
		$due = trim( (string) $args['due_date'] );

		if ( '' !== $due ) {
			$valid = blueworx_mcp_forge_check_date( $due, 'due_date' );

			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}

		$fields['planned_due'] = $due;
	}

	if ( isset( $args['how_to_test'] ) && is_scalar( $args['how_to_test'] ) ) {
		$fields['test_description'] = sanitize_textarea_field( (string) $args['how_to_test'] );
	}

	if ( isset( $args['who_pays'] ) && is_scalar( $args['who_pays'] ) ) {
		if ( ! in_array( $args['who_pays'], array( 'chargeable', 'free-bug', 'free-general' ), true ) ) {
			return new WP_Error( 'invalid_who_pays', 'who_pays must be chargeable, free-bug or free-general.' );
		}

		$fields['commercial_class'] = (string) $args['who_pays'];
	}

	foreach ( array(
		'assignee'  => 'primary_user_id',
		'reviewer'  => 'reviewer_id',
		'designer'  => 'designer_id',
		'deliverer' => 'deliverer_id',
	) as $arg => $seat ) {
		if ( ! isset( $args[ $arg ] ) || ! is_scalar( $args[ $arg ] ) ) {
			continue;
		}

		$fields[ $seat ] = '';

		if ( '' !== trim( (string) $args[ $arg ] ) ) {
			$person = blueworx_mcp_forge_resolve_person( (string) $args[ $arg ], $people );

			if ( is_wp_error( $person ) ) {
				return $person;
			}

			$fields[ $seat ] = $person;
		}
	}

	if ( isset( $args['priority'] ) && is_scalar( $args['priority'] ) ) {
		if ( ! in_array( $args['priority'], array( 'low', 'normal', 'high', 'urgent' ), true ) ) {
			return new WP_Error( 'invalid_priority', 'priority must be low, normal, high or urgent.' );
		}

		$fields['priority'] = (string) $args['priority'];
	}

	foreach ( blueworx_mcp_forge_role_dates() as $arg => $column ) {
		if ( ! isset( $args[ $arg ] ) || ! is_scalar( $args[ $arg ] ) ) {
			continue;
		}

		$day = trim( (string) $args[ $arg ] );

		if ( '' !== $day ) {
			$valid = blueworx_mcp_forge_check_date( $day, $arg );

			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}

		$fields[ $column ] = $day;
	}

	foreach ( blueworx_mcp_forge_role_hours() as $arg => $column ) {
		if ( ! isset( $args[ $arg ] ) ) {
			continue;
		}

		if ( ! is_numeric( $args[ $arg ] ) || (float) $args[ $arg ] < 0 ) {
			return new WP_Error( 'invalid_hours', $arg . ' must be a number of hours, 0 or more.' );
		}

		$fields[ $column ] = round( (float) $args[ $arg ], 2 );
	}

	return $fields;
}

/**
 * Tool: create_task.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_forge_create_task( $args ) {
	$off = blueworx_mcp_forge_unavailable();

	if ( null !== $off ) {
		return $off;
	}

	if ( ! isset( $args['title'] ) || ! is_scalar( $args['title'] ) || '' === trim( (string) $args['title'] ) ) {
		return new WP_Error( 'missing_title', 'A task needs a title.' );
	}

	$level = isset( $args['level'] ) ? (string) $args['level'] : 'feature';
	$type  = isset( $args['work_type'] ) ? (string) $args['work_type'] : 'task';

	if ( ! in_array( $level, blueworx_mcp_forge_task_levels(), true ) ) {
		return new WP_Error( 'invalid_level', 'level must be one of: ' . implode( ', ', blueworx_mcp_forge_task_levels() ) . '.' );
	}

	if ( ! in_array( $type, array( 'task', 'feature', 'bug', 'feedback' ), true ) ) {
		return new WP_Error( 'invalid_work_type', 'work_type must be task, feature, bug or feedback.' );
	}

	$site   = isset( $args['client_site_id'] ) && is_scalar( $args['client_site_id'] ) ? trim( (string) $args['client_site_id'] ) : '';
	$parent = '';

	if ( ! empty( $args['project_id'] ) ) {
		$view = blueworx_mcp_forge_show( array( 'id' => $args['project_id'] ) );

		if ( is_wp_error( $view ) ) {
			return $view;
		}

		$parent      = (string) $view['item']['id'];
		$parent_site = (string) ( $view['item']['client_site_id'] ?? '' );

		if ( '' !== $site && $site !== $parent_site ) {
			return new WP_Error( 'site_mismatch', 'That project is on a different client site from the one given. Leave client_site_id out to use the project\'s.' );
		}

		$site = $parent_site;
	}

	if ( '' === $site ) {
		return new WP_Error( 'missing_site', 'Say where the task goes: a project_id, or a client_site_id (get_site_info lists the sites).' );
	}

	$people = blueworx_mcp_forge_people();

	if ( is_wp_error( $people ) ) {
		return $people;
	}

	$fields = blueworx_mcp_forge_write_fields( $args, $people );

	if ( is_wp_error( $fields ) ) {
		return $fields;
	}

	$answer = blueworx_mcp_forge_request(
		'POST',
		'/work-items',
		array(),
		array_merge(
			$fields,
			array(
				'client_site_id' => $site,
				'level'          => $level,
				'work_type'      => $type,
				'parent_id'      => $parent,
			)
		)
	);

	if ( is_wp_error( $answer ) ) {
		return $answer;
	}

	$out         = blueworx_mcp_forge_shape( (array) ( $answer['item'] ?? array() ), $people );
	$out['_log'] = array( 'created', 'Forge task ' . $out['id'] . ' ' . $out['title'] );

	return $out;
}

/**
 * Tool: update_task.
 *
 * Fields first, then the move, each against the version Forge last reported,
 * so the move never trips over the edit made a moment before it.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_forge_update_task( $args ) {
	$off = blueworx_mcp_forge_unavailable();

	if ( null !== $off ) {
		return $off;
	}

	$view = blueworx_mcp_forge_show( $args );

	if ( is_wp_error( $view ) ) {
		return $view;
	}

	$people = blueworx_mcp_forge_people();

	if ( is_wp_error( $people ) ) {
		return $people;
	}

	$fields = blueworx_mcp_forge_write_fields( $args, $people );

	if ( is_wp_error( $fields ) ) {
		return $fields;
	}

	$move = '';

	if ( isset( $args['status'] ) && is_scalar( $args['status'] ) && '' !== trim( (string) $args['status'] ) ) {
		$statuses = blueworx_mcp_forge_statuses();

		if ( is_wp_error( $statuses ) ) {
			return $statuses;
		}

		$move = blueworx_mcp_forge_resolve_status( (string) $args['status'], $statuses );

		if ( is_wp_error( $move ) ) {
			return $move;
		}
	}

	$answers = blueworx_mcp_forge_resolve_answers( $args, $people );

	if ( is_wp_error( $answers ) ) {
		return $answers;
	}

	$confirm = ! empty( $args['confirm_client'] );

	if ( empty( $fields ) && '' === $move && empty( $answers ) && ! $confirm ) {
		return new WP_Error( 'nothing_to_change', 'No changes were given.' );
	}

	$item    = (array) $view['item'];
	$id      = (string) $item['id'];
	$changed = array();

	// Once something is saved, a later step that fails says so rather than
	// reading as though nothing happened.
	$partly = function ( $error ) use ( &$changed ) {
		if ( empty( $changed ) ) {
			return $error;
		}

		return new WP_Error( $error->get_error_code(), 'Saved the change to ' . implode( ', ', $changed ) . ', but not the rest. ' . $error->get_error_message() );
	};

	if ( ! empty( $fields ) ) {
		$answer = blueworx_mcp_forge_request(
			'PATCH',
			'/work-items/' . $id,
			array(),
			array_merge( $fields, array( 'record_version' => (int) $item['record_version'] ) )
		);

		if ( is_wp_error( $answer ) ) {
			return $answer;
		}

		$item    = (array) $answer['item'];
		$names   = blueworx_mcp_forge_field_names();
		$changed = array_map(
			function ( $field ) use ( $names ) {
				return $names[ $field ] ?? $field;
			},
			array_keys( $fields )
		);
	}

	if ( $confirm ) {
		$answer = blueworx_mcp_forge_request( 'POST', '/work-items/' . $id . '/confirm-client', array(), array( 'record_version' => (int) $item['record_version'] ) );

		if ( is_wp_error( $answer ) ) {
			return $partly( $answer );
		}

		$item      = (array) $answer['item'];
		$changed[] = 'confirm_client';
	}

	foreach ( $answers as $given ) {
		$answer = blueworx_mcp_forge_request(
			'POST',
			'/work-items/' . $id . '/gate',
			array(),
			array(
				'requirement' => $given['requirement'],
				'value'       => $given['value'],
				'evidence'    => '',
			)
		);

		if ( is_wp_error( $answer ) ) {
			return $partly( $answer );
		}

		$changed[] = $given['name'];
	}

	if ( '' !== $move && (string) $item['stage'] !== $move ) {
		// Read again after an answer or a confirmation: the moves on offer, and
		// the version the move is made against, may both have changed.
		if ( ! empty( $answers ) || $confirm ) {
			$view = blueworx_mcp_forge_show( array( 'id' => $id ) );

			if ( is_wp_error( $view ) ) {
				return $partly( $view );
			}

			$item = (array) $view['item'];
		}

		$reached = '';
		$moved   = blueworx_mcp_forge_walk( $item, $move, $view, $args, array_keys( $statuses ), $reached );

		if ( is_wp_error( $moved ) ) {
			if ( empty( $changed ) && '' === $reached ) {
				return $moved;
			}

			$saved = empty( $changed ) ? '' : 'Saved the change to ' . implode( ', ', $changed );

			if ( '' === $reached ) {
				$saved .= ', but the status did not change. ';
			} else {
				$saved = ( '' === $saved ? 'Moved it' : $saved . ' and moved it' ) . ' as far as "' . $reached . '", but no further. ';
			}

			return new WP_Error( $moved->get_error_code(), $saved . $moved->get_error_message() );
		}

		$item      = $moved;
		$changed[] = 'status';
	}

	$out            = blueworx_mcp_forge_shape( $item, $people );
	$out['changed'] = $changed;
	$out['_log']    = array( 'updated', 'Forge task ' . $out['id'] . ' ' . $out['title'] );

	return $out;
}

/**
 * Finds the gate questions being answered, and checks each answer.
 *
 * Questions are found by name in Forge's own gate registry rather than by
 * requirement ID, so a renumbered gate does not break them. Claude may answer
 * any question a task meets on its way to In Review, approvals included, as
 * the person who connected it; the review approval and the release are left
 * to the people Forge names. Everything is checked before anything is saved.
 *
 * @param array $args   Tool arguments.
 * @param array $people Name keyed by person ID.
 * @return array|WP_Error Each answer: 'requirement', 'value', and 'name' (what it is reported as).
 */
function blueworx_mcp_forge_resolve_answers( $args, $people ) {
	$asked = array();

	foreach ( blueworx_mcp_forge_answer_args() as $slug => $arg ) {
		if ( isset( $args[ $arg ] ) && is_scalar( $args[ $arg ] ) && '' !== trim( (string) $args[ $arg ] ) ) {
			$asked[] = array( $slug, trim( (string) $args[ $arg ] ), $arg );
		}
	}

	if ( isset( $args['gate_answers'] ) ) {
		if ( ! is_array( $args['gate_answers'] ) ) {
			return new WP_Error( 'invalid_gate_answers', 'gate_answers must be an object of question names and answers, e.g. {"Documentation approval": "Approved"}.' );
		}

		foreach ( $args['gate_answers'] as $question => $given ) {
			if ( ! is_scalar( $given ) || '' === trim( (string) $given ) ) {
				return new WP_Error( 'missing_answer', '"' . $question . '" needs an answer.' );
			}

			$asked[] = array( blueworx_mcp_forge_slug( (string) $question ), trim( (string) $given ), '' );
		}
	}

	if ( empty( $asked ) ) {
		return array();
	}

	$registry = blueworx_mcp_forge_request( 'GET', '/gates' );

	if ( is_wp_error( $registry ) ) {
		return $registry;
	}

	$open   = array();
	$later  = array();
	$labels = array();

	foreach ( (array) ( $registry['gates'] ?? array() ) as $gate => $requirements ) {
		foreach ( (array) $requirements as $requirement ) {
			// Only questions somebody answers: fields are set with their own
			// arguments, and checks Forge runs for itself.
			if ( 'record' !== ( $requirement['by'] ?? '' ) || ! in_array( $requirement['control'] ?? '', array( 'pick', 'box' ), true ) ) {
				continue;
			}

			$names = array( blueworx_mcp_forge_slug( $requirement['label'] ?? '' ), blueworx_mcp_forge_slug( $requirement['id'] ?? '' ) );

			if ( ! in_array( $gate, blueworx_mcp_forge_answerable_gates(), true ) ) {
				$later += array_fill_keys( $names, true );
				continue;
			}

			if ( ! isset( $open[ $names[0] ] ) ) {
				$labels[] = (string) $requirement['label'];
			}

			$open += array_fill_keys( $names, $requirement );
		}
	}

	$answers = array();

	foreach ( $asked as list( $slug, $given, $arg ) ) {
		if ( ! isset( $open[ $slug ] ) ) {
			$name = '' === $arg ? '"' . $slug . '"' : $arg;

			if ( isset( $later[ $slug ] ) ) {
				return new WP_Error( 'answer_not_allowed', $name . ' is answered in Forge by the people it names. Claude takes a task as far as In Review.' );
			}

			return new WP_Error( 'unknown_question', 'Forge asks nothing called ' . $name . ' before In Review. It asks: ' . implode( ', ', $labels ) . '.' );
		}

		$requirement = $open[ $slug ];
		$name        = '' === $arg ? (string) $requirement['label'] : $arg;
		$value       = blueworx_mcp_forge_answer_value( $requirement, $given, '' === $arg ? '"' . $name . '"' : $arg, $people );

		if ( is_wp_error( $value ) ) {
			return $value;
		}

		$answers[] = array(
			'requirement' => (string) $requirement['id'],
			'value'       => $value,
			'name'        => $name,
		);
	}

	return $answers;
}

/**
 * Checks one answer against what its question accepts.
 *
 * @param array  $requirement The question, from Forge's gate registry.
 * @param string $given       The answer given.
 * @param string $name        How the question is named in a refusal.
 * @param array  $people      Name keyed by person ID.
 * @return string|WP_Error The value Forge records.
 */
function blueworx_mcp_forge_answer_value( $requirement, $given, $name, $people ) {
	if ( 'box' === $requirement['control'] ) {
		$input = (string) ( $requirement['input'] ?? 'text' );

		if ( 'number' === $input ) {
			return is_numeric( $given ) ? (string) ( 0 + $given ) : new WP_Error( 'invalid_answer', $name . ' must be a number.' );
		}

		if ( 'date' === $input ) {
			$valid = blueworx_mcp_forge_check_date( $given, $name );

			return is_wp_error( $valid ) ? $valid : $given;
		}

		return sanitize_textarea_field( $given );
	}

	$wanted = blueworx_mcp_forge_slug( $given );
	$labels = array();

	foreach ( (array) ( $requirement['options'] ?? array() ) as $option ) {
		if ( blueworx_mcp_forge_slug( $option['value'] ?? '' ) === $wanted || blueworx_mcp_forge_slug( $option['label'] ?? '' ) === $wanted ) {
			// Any other outcome ends the task, which is Forge's End it, not a gate.
			if ( 'triage-outcome' === blueworx_mcp_forge_slug( $requirement['label'] ) && 'proceed' !== $option['value'] ) {
				return new WP_Error( 'outcome_ends_task', 'Only proceed is given from here. Rejecting, deferring or closing a task as a duplicate ends it, and is done in Forge.' );
			}

			return (string) $option['value'];
		}

		$labels[] = (string) ( $option['label'] ?? $option['value'] ?? '' );
	}

	$source = (string) ( $requirement['source'] ?? '' );

	if ( 'items' === $source ) {
		$view = blueworx_mcp_forge_show( array( 'id' => $given ) );

		if ( ! is_wp_error( $view ) ) {
			return (string) $view['item']['id'];
		}

		$labels[] = 'or the ID of another task';
	}

	if ( 'people' === $source ) {
		return blueworx_mcp_forge_resolve_person( $given, $people );
	}

	return new WP_Error( 'unknown_answer', $name . ' must be one of: ' . implode( ', ', $labels ) . '.' );
}

/**
 * Moves an item towards a status, one stage at a time when it is further on
 * than the next. Each step is a move Forge offers and gates as usual, so the
 * walk stops at the first stage Forge will not let it leave.
 *
 * @param array  $item    The item as it stands now.
 * @param string $to      Status ID.
 * @param array  $view    Forge's single-item view, for the moves on offer.
 * @param array  $args    Tool arguments, for a reason.
 * @param array  $order   Status IDs, in Forge's order.
 * @param string $reached Set to the last stage reached, when the walk stops short.
 * @return array|WP_Error The moved item.
 */
function blueworx_mcp_forge_walk( $item, $to, $view, $args, $order, &$reached ) {
	$target = array_search( $to, $order, true );

	// One step per stage at most, so a move that goes nowhere cannot loop.
	foreach ( $order as $unused ) {
		$forward = array_map( 'strval', (array) ( $view['available'] ?? array() ) );
		$from    = array_search( (string) $item['stage'], $order, true );
		$step    = $to;

		if ( ! in_array( $to, $forward, true ) && false !== $target && false !== $from ) {
			foreach ( $forward as $option ) {
				$at = array_search( $option, $order, true );

				if ( false !== $at && $at > $from && $at < $target ) {
					$step = $option;
					break;
				}
			}
		}

		$moved = blueworx_mcp_forge_move( $item, $step, $view, $args );

		if ( is_wp_error( $moved ) || $step === $to ) {
			return $moved;
		}

		$reached = $step;
		$view    = blueworx_mcp_forge_show( array( 'id' => $item['id'] ) );

		if ( is_wp_error( $view ) ) {
			return $view;
		}

		$item = (array) $view['item'];
	}

	return $item;
}

/**
 * Moves an item to a new status, forwards or back, the way Forge allows.
 *
 * @param array  $item The item as it stands now.
 * @param string $to   Status ID.
 * @param array  $view Forge's single-item view, for the moves on offer.
 * @param array  $args Tool arguments, for a reason.
 * @return array|WP_Error The moved item.
 */
function blueworx_mcp_forge_move( $item, $to, $view, $args ) {
	$forward = array_map( 'strval', (array) ( $view['available'] ?? array() ) );
	$back    = array_map( 'strval', (array) ( $view['returns'] ?? array() ) );
	$base    = '/work-items/' . (string) $item['id'];
	$version = (int) $item['record_version'];

	if ( in_array( $to, $forward, true ) ) {
		$body = array(
			'to'             => $to,
			'record_version' => $version,
		);

		// Only sent when given: Forge refuses a reason from anybody who may not
		// over-book people, even on a move that over-books nobody.
		if ( isset( $args['capacity_reason'] ) && is_scalar( $args['capacity_reason'] ) && '' !== trim( (string) $args['capacity_reason'] ) ) {
			$body['capacity_reason'] = sanitize_text_field( (string) $args['capacity_reason'] );
		}

		$answer = blueworx_mcp_forge_request( 'POST', $base . '/transition', array(), $body );
	} elseif ( in_array( $to, $back, true ) ) {
		$reason = isset( $args['reason'] ) && is_scalar( $args['reason'] ) ? sanitize_text_field( (string) $args['reason'] ) : '';

		if ( '' === $reason ) {
			return new WP_Error( 'reason_required', 'Sending work back to "' . $to . '" needs a reason. Give one in reason.' );
		}

		$answer = blueworx_mcp_forge_request(
			'POST',
			$base . '/return',
			array(),
			array(
				'to'             => $to,
				'reason'         => $reason,
				'record_version' => $version,
			)
		);
	} else {
		$options = array_merge( $forward, $back );

		return new WP_Error(
			'move_not_available',
			'From "' . (string) $item['stage'] . '" this cannot go straight to "' . $to . '".'
			. ( empty( $options ) ? ' It has no moves available here; change it in Forge.' : ' It can move to: ' . implode( ', ', $options ) . '.' )
		);
	}

	if ( is_wp_error( $answer ) ) {
		return $answer;
	}

	return (array) ( $answer['item'] ?? $item );
}

/**
 * Tool: add_task_comment.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_forge_add_comment( $args ) {
	$off = blueworx_mcp_forge_unavailable();

	if ( null !== $off ) {
		return $off;
	}

	$comment = isset( $args['comment'] ) && is_scalar( $args['comment'] ) ? sanitize_textarea_field( (string) $args['comment'] ) : '';

	if ( '' === trim( $comment ) ) {
		return new WP_Error( 'missing_comment', 'The comment is empty.' );
	}

	$view = blueworx_mcp_forge_show( $args );

	if ( is_wp_error( $view ) ) {
		return $view;
	}

	$id     = (string) $view['item']['id'];
	$answer = blueworx_mcp_forge_request(
		'POST',
		'/work-items/' . $id . '/comments',
		array(),
		array(
			'body'       => $comment,
			'visibility' => empty( $args['visible_to_client'] ) ? 'internal' : 'client',
		)
	);

	if ( is_wp_error( $answer ) ) {
		return $answer;
	}

	$title = (string) ( $view['item']['title'] ?? '' );

	return array(
		'task_id'    => $id,
		'comment'    => (string) ( $answer['comment']['body'] ?? $comment ),
		'visibility' => (string) ( $answer['comment']['visibility'] ?? '' ),
		'_log'       => array( 'commented on', 'Forge task ' . $id . ' ' . $title ),
	);
}

/**
 * Tool: get_team_capacity.
 *
 * Forge works the figures out (every client at once, so nobody looks free on
 * one while full on another); this only picks the window and renames them.
 * Naming a person adds Forge's drill-down: the work behind their figures.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_forge_get_team_capacity( $args ) {
	$off = blueworx_mcp_forge_unavailable();

	if ( null !== $off ) {
		return $off;
	}

	$window = array();

	foreach ( array( 'from', 'to' ) as $end ) {
		$window[ $end ] = isset( $args[ $end ] ) && is_scalar( $args[ $end ] ) ? trim( (string) $args[ $end ] ) : '';

		if ( '' !== $window[ $end ] ) {
			$valid = blueworx_mcp_forge_check_date( $window[ $end ], $end );

			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}
	}

	$from = '' === $window['from'] ? (string) wp_date( 'Y-m-d' ) : $window['from'];
	$to   = '' === $window['to'] ? gmdate( 'Y-m-d', (int) strtotime( $from . ' 00:00:00 UTC +27 days' ) ) : $window['to'];

	if ( $to < $from ) {
		return new WP_Error( 'invalid_range', 'to must be on or after from.' );
	}

	$by     = isset( $args['by'] ) && 'days' === $args['by'] ? 'days' : 'weeks';
	$people = blueworx_mcp_forge_people();

	if ( is_wp_error( $people ) ) {
		return $people;
	}

	$who = '';

	if ( isset( $args['person'] ) && is_scalar( $args['person'] ) && '' !== trim( (string) $args['person'] ) ) {
		$who = blueworx_mcp_forge_resolve_person( (string) $args['person'], $people );

		if ( is_wp_error( $who ) ) {
			return $who;
		}
	}

	$grid = blueworx_mcp_forge_request(
		'GET',
		'/capacity',
		array(
			'from' => $from,
			'to'   => $to,
			'by'   => $by,
		)
	);

	if ( is_wp_error( $grid ) ) {
		return $grid;
	}

	$rows = array();

	foreach ( (array) ( $grid['people'] ?? array() ) as $row ) {
		$id = (string) ( $row['user_id'] ?? '' );

		if ( '' !== $who && $id !== $who ) {
			continue;
		}

		$rows[] = array(
			'id'    => $id,
			'name'  => (string) ( $row['display_name'] ?? '' ),
			$by     => array_map( 'blueworx_mcp_forge_capacity_cell', (array) ( $row['periods'] ?? array() ) ),
			'total' => blueworx_mcp_forge_capacity_cell( (array) ( $row['total'] ?? array() ) ),
		);
	}

	if ( '' !== $who && ! empty( $rows ) ) {
		$detail = blueworx_mcp_forge_request(
			'GET',
			'/capacity/person/' . $who,
			array(
				'from' => $from,
				'to'   => $to,
			)
		);

		if ( is_wp_error( $detail ) ) {
			return $detail;
		}

		$rows[0]['tasks'] = array_map(
			function ( $allocation ) use ( $people ) {
				return blueworx_mcp_forge_capacity_task( (array) $allocation, $people );
			},
			(array) ( $detail['allocations'] ?? array() )
		);
	}

	$out = array(
		'from'   => $from,
		'to'     => $to,
		'people' => $rows,
	);

	if ( ! empty( $grid['only_me'] ) ) {
		$out['note'] = 'Forge only shows you your own capacity. An administrator sees everyone.';
	}

	return $out;
}

/**
 * One period of Forge's capacity figures, in plain names.
 *
 * @param array $cell Forge's figures for a period.
 * @return array Figures.
 */
function blueworx_mcp_forge_capacity_cell( $cell ) {
	$out = array();

	if ( isset( $cell['from'] ) ) {
		$out['from'] = (string) $cell['from'];
		$out['to']   = (string) ( $cell['to'] ?? $cell['from'] );
	}

	$band  = (string) ( $cell['band'] ?? '' );
	$plain = array(
		'over'       => 'over-booked',
		'unrecorded' => 'not set up',
	);

	return $out + array(
		'available' => (float) ( $cell['available'] ?? 0 ),
		'booked'    => (float) ( $cell['committed'] ?? 0 ),
		'done'      => (float) ( $cell['completed'] ?? 0 ),
		'left'      => (float) ( $cell['remaining'] ?? 0 ),
		'status'    => $plain[ $band ] ?? $band,
	);
}

/**
 * One piece of work taking up a person's time, from Forge's drill-down.
 *
 * @param array $allocation Forge allocation.
 * @param array $people     Name keyed by person ID.
 * @return array Task line.
 */
function blueworx_mcp_forge_capacity_task( $allocation, $people ) {
	$roles    = array(
		'primary'  => 'assignee',
		'design'   => 'designer',
		'review'   => 'reviewer',
		'delivery' => 'deliverer',
	);
	$statuses = array(
		'committed'   => 'to do',
		'completed'   => 'done',
		'with-client' => 'with client',
	);
	$role     = (string) ( $allocation['role'] ?? '' );
	$status   = (string) ( $allocation['status'] ?? '' );
	$covering = (string) ( $allocation['covering'] ?? '' );

	$out = array(
		'task_id' => (string) ( $allocation['item_id'] ?? '' ),
		'title'   => (string) ( $allocation['title'] ?? '' ),
		'role'    => $roles[ $role ] ?? $role,
		'hours'   => (float) ( $allocation['hours'] ?? 0 ),
		'from'    => (string) ( $allocation['from'] ?? '' ),
		'to'      => (string) ( $allocation['to'] ?? '' ),
		'status'  => $statuses[ $status ] ?? $status,
	);

	if ( '' !== $covering ) {
		$out['covering_for'] = $people[ $covering ] ?? $covering;
	}

	return $out;
}

/**
 * What get_site_info says about Forge.
 *
 * Everything the Forge tools take an ID for, so Claude can look a site,
 * person or status up once rather than guess. A part Forge will not give is
 * reported as such rather than failing the whole of get_site_info.
 *
 * @return array Forge details.
 */
function blueworx_mcp_forge_site_info() {
	if ( ! blueworx_mcp_forge_active() ) {
		return array(
			'active' => false,
			'note'   => 'Forge is not active on this site, so the Forge tools are not available.',
		);
	}

	$out = array(
		'active'     => true,
		'version'    => BWX_FORGE_VERSION,
		'tools'      => array_keys( blueworx_mcp_forge_tool_handlers() ),
		'levels'     => array_merge( array( 'project' ), blueworx_mcp_forge_task_levels() ),
		'work_types' => array( 'task', 'feature', 'bug', 'feedback' ),
	);

	$statuses = blueworx_mcp_forge_statuses();

	if ( is_wp_error( $statuses ) ) {
		$out['statuses'] = $statuses->get_error_message();
	} else {
		$out['statuses'] = array();

		foreach ( $statuses as $id => $label ) {
			$out['statuses'][] = array(
				'id'    => $id,
				'label' => $label,
			);
		}
	}

	$sites = blueworx_mcp_forge_request( 'GET', '/client-sites' );

	if ( is_wp_error( $sites ) ) {
		$out['sites'] = $sites->get_error_message();
	} else {
		$out['sites'] = array();

		foreach ( (array) ( $sites['sites'] ?? array() ) as $site ) {
			$out['sites'][] = array(
				'id'     => (string) $site['id'],
				'name'   => (string) $site['name'],
				'client' => (string) ( $site['client_name'] ?? '' ),
			);
		}
	}

	$people = blueworx_mcp_forge_people();

	if ( is_wp_error( $people ) ) {
		$out['people'] = $people->get_error_message();
	} else {
		$out['people'] = array();

		foreach ( $people as $id => $name ) {
			$out['people'][] = array(
				'id'   => $id,
				'name' => $name,
			);
		}
	}

	return $out;
}
