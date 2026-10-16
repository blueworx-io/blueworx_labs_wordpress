<?php
/**
 * BlueWorx: Connect Claude — the MCP endpoint and its tools.
 *
 * Speaks the MCP Streamable HTTP transport in its simplest form: every message
 * is one POST, every answer is one JSON body, and nothing is kept between
 * requests. That is all the specification requires of a server, and it means
 * there is no session state on this site to expire, leak or clean up.
 *
 * Every tool runs as the administrator who approved the connection, through
 * WordPress's own REST controllers (rest_do_request()). So the permission
 * checks, validation, sanitising and every other plugin's hooks are exactly the
 * ones the block editor goes through — the tools add no way into the site that
 * the administrator did not already have, and the "content, not files" limit is
 * kept by which routes the tools call: posts, pages, other content types and
 * terms. There is no tool that uploads, deletes permanently, or touches users,
 * plugins, themes or settings.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Protocol versions this server can speak, newest first.
 *
 * @return string[] Versions.
 */
function blueworx_mcp_protocol_versions() {
	return array( '2025-06-18', '2025-03-26', '2024-11-05' );
}

/**
 * Answers the MCP endpoint.
 *
 * @param string $method HTTP method.
 * @return void
 */
function blueworx_mcp_handle_mcp_request( $method ) {
	$challenge = 'Bearer resource_metadata="' . blueworx_mcp_resource_metadata_url() . '"';

	if ( 'POST' !== $method ) {
		// GET would open a server-to-client event stream, which this server
		// does not offer; the specification's answer to that is 405.
		blueworx_mcp_send_json( array( 'error' => 'Use POST.' ), 405, array( 'Allow' => 'POST' ) );
	}

	$token = blueworx_mcp_bearer_from_request();

	if ( '' === $token ) {
		blueworx_mcp_send_json( array( 'error' => 'invalid_token' ), 401, array( 'WWW-Authenticate' => $challenge ) );
	}

	$verified = blueworx_mcp_verify_access_token( $token );

	if ( null === $verified ) {
		blueworx_mcp_send_json(
			array( 'error' => 'invalid_token' ),
			401,
			array( 'WWW-Authenticate' => $challenge . ', error="invalid_token"' )
		);
	}

	list( , $grant ) = $verified;

	// From here on, every check WordPress makes is made against this person.
	// Cookies on the request are deliberately ignored: only the token counts.
	wp_set_current_user( (int) $grant['user_id'] );

	$body = json_decode( (string) file_get_contents( 'php://input' ), true );

	if ( ! is_array( $body ) ) {
		blueworx_mcp_send_json( blueworx_mcp_rpc_error( null, -32700, 'Parse error' ) );
	}

	// A JSON array is a batch (allowed in 2025-03-26); an object is one message.
	$is_batch  = ! empty( $body ) && blueworx_mcp_is_list( $body );
	$messages  = $is_batch ? $body : array( $body );
	$responses = array();

	foreach ( $messages as $message ) {
		$response = blueworx_mcp_handle_message( $message, $grant );

		if ( null !== $response ) {
			$responses[] = $response;
		}
	}

	if ( empty( $responses ) ) {
		// Only notifications or responses arrived: accepted, nothing to say.
		status_header( 202 );
		exit;
	}

	blueworx_mcp_send_json( $is_batch ? $responses : $responses[0] );
}

/**
 * Builds a JSON-RPC error.
 *
 * @param mixed  $id      Request id.
 * @param int    $code    Error code.
 * @param string $message Message.
 * @return array Response.
 */
function blueworx_mcp_rpc_error( $id, $code, $message ) {
	return array(
		'jsonrpc' => '2.0',
		'id'      => $id,
		'error'   => array(
			'code'    => $code,
			'message' => $message,
		),
	);
}

/**
 * Builds a JSON-RPC result.
 *
 * @param mixed $id     Request id.
 * @param mixed $result Result.
 * @return array Response.
 */
function blueworx_mcp_rpc_result( $id, $result ) {
	return array(
		'jsonrpc' => '2.0',
		'id'      => $id,
		'result'  => $result,
	);
}

/**
 * Handles one JSON-RPC message.
 *
 * @param mixed $message Decoded message.
 * @param array $grant   Grant the call is made under.
 * @return array|null Response, or null for a notification.
 */
function blueworx_mcp_handle_message( $message, $grant ) {
	if ( ! is_array( $message ) || ! isset( $message['jsonrpc'] ) || '2.0' !== $message['jsonrpc'] ) {
		return blueworx_mcp_rpc_error( null, -32600, 'Invalid Request' );
	}

	// A notification (no id), or a response to something we never sent.
	if ( ! array_key_exists( 'id', $message ) || ! isset( $message['method'] ) ) {
		return null;
	}

	$id     = $message['id'];
	$params = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : array();

	switch ( (string) $message['method'] ) {
		case 'initialize':
			$asked    = isset( $params['protocolVersion'] ) ? (string) $params['protocolVersion'] : '';
			$versions = blueworx_mcp_protocol_versions();

			return blueworx_mcp_rpc_result(
				$id,
				array(
					'protocolVersion' => in_array( $asked, $versions, true ) ? $asked : $versions[0],
					'capabilities'    => array( 'tools' => array( 'listChanged' => false ) ),
					'serverInfo'      => array(
						'name'    => 'blueworx-wordpress',
						'title'   => get_bloginfo( 'name' ),
						'version' => defined( 'BLUEWORX_LABS_VERSION' ) ? BLUEWORX_LABS_VERSION : '0',
					),
					'instructions'    => 'Tools for reading and editing the content of the WordPress site at ' . home_url() . '. New content is created as a draft unless you are asked to publish it. Files cannot be uploaded or deleted. Pages marked built_with_elementor keep their layout outside the content field, so editing content will not change what visitors see on them.',
				)
			);

		case 'ping':
			return blueworx_mcp_rpc_result( $id, new stdClass() );

		case 'tools/list':
			return blueworx_mcp_rpc_result( $id, array( 'tools' => blueworx_mcp_tool_definitions() ) );

		case 'tools/call':
			$name = isset( $params['name'] ) ? (string) $params['name'] : '';
			$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

			if ( ! isset( blueworx_mcp_tool_handlers()[ $name ] ) ) {
				return blueworx_mcp_rpc_error( $id, -32602, 'Unknown tool: ' . $name );
			}

			return blueworx_mcp_rpc_result( $id, blueworx_mcp_call_tool( $name, $args, $grant ) );

		case 'resources/list':
			return blueworx_mcp_rpc_result( $id, array( 'resources' => array() ) );

		case 'prompts/list':
			return blueworx_mcp_rpc_result( $id, array( 'prompts' => array() ) );
	}

	return blueworx_mcp_rpc_error( $id, -32601, 'Method not found' );
}

/**
 * Gets the content types the tools may work on.
 *
 * Anything with an editor screen and a REST route, except media (that is
 * files) and the block theme's own design records (that is the theme).
 *
 * @return array REST base keyed by post type.
 */
function blueworx_mcp_content_types() {
	$types = array();

	foreach ( get_post_types(
		array(
			'show_in_rest' => true,
			'show_ui'      => true,
		),
		'objects'
	) as $type ) {
		if ( 'attachment' === $type->name || ( 0 === strpos( $type->name, 'wp_' ) && 'wp_block' !== $type->name ) ) {
			continue;
		}

		$types[ $type->name ] = array(
			'rest_base' => ! empty( $type->rest_base ) ? $type->rest_base : $type->name,
			'namespace' => ! empty( $type->rest_namespace ) ? $type->rest_namespace : 'wp/v2',
			'label'     => $type->labels->singular_name,
		);
	}

	return $types;
}

/**
 * Gets the taxonomies the tools may work on.
 *
 * @return array REST base keyed by taxonomy.
 */
function blueworx_mcp_taxonomies() {
	$taxonomies = array();

	foreach ( get_taxonomies(
		array(
			'show_in_rest' => true,
			'show_ui'      => true,
		),
		'objects'
	) as $tax ) {
		$taxonomies[ $tax->name ] = array(
			'rest_base' => ! empty( $tax->rest_base ) ? $tax->rest_base : $tax->name,
			'namespace' => ! empty( $tax->rest_namespace ) ? $tax->rest_namespace : 'wp/v2',
			'label'     => $tax->labels->singular_name,
		);
	}

	return $taxonomies;
}

/**
 * Describes every tool, for tools/list.
 *
 * @return array Tool definitions.
 */
function blueworx_mcp_tool_definitions() {
	$type     = array(
		'type'        => 'string',
		'description' => 'Content type, e.g. "page" or "post". get_site_info lists them all.',
	);
	$id       = array(
		'type'        => 'integer',
		'description' => 'ID of the item.',
	);
	$fields   = array(
		'title'          => array(
			'type'        => 'string',
			'description' => 'Title.',
		),
		'content'        => array(
			'type'        => 'string',
			'description' => 'Body, as block markup or HTML.',
		),
		'excerpt'        => array(
			'type'        => 'string',
			'description' => 'Short summary.',
		),
		'status'         => array(
			'type'        => 'string',
			'enum'        => array( 'draft', 'pending', 'private', 'publish', 'future' ),
			'description' => 'Publication status. Only publish when asked to.',
		),
		'slug'           => array(
			'type'        => 'string',
			'description' => 'URL slug.',
		),
		'date'           => array(
			'type'        => 'string',
			'description' => 'Publish date in the site timezone, e.g. 2026-11-01T09:00:00 (needed for status "future").',
		),
		'parent'         => array(
			'type'        => 'integer',
			'description' => 'Parent item ID, for hierarchical types like pages.',
		),
		'menu_order'     => array(
			'type'        => 'integer',
			'description' => 'Order among siblings.',
		),
		'featured_media' => array(
			'type'        => 'integer',
			'description' => 'ID of an existing media item to use as the featured image.',
		),
		'terms'          => array(
			'type'                 => 'object',
			'description'          => 'Terms to assign, keyed by taxonomy REST base, e.g. {"categories":[3],"tags":[7,9]}. Replaces the existing terms for each taxonomy given.',
			'additionalProperties' => array(
				'type'  => 'array',
				'items' => array( 'type' => 'integer' ),
			),
		),
	);
	$readonly = array(
		'readOnlyHint'  => true,
		'openWorldHint' => false,
	);

	return array(
		array(
			'name'        => 'get_site_info',
			'title'       => 'Get site info',
			'description' => 'Site name, address, timezone, and the content types and taxonomies the other tools accept.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => new stdClass(),
			),
			'annotations' => $readonly,
		),
		array(
			'name'        => 'list_content',
			'title'       => 'List content',
			'description' => 'Lists or searches items of one content type. Returns id, title, status, date, link. Includes drafts.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'type'     => $type,
					'search'   => array(
						'type'        => 'string',
						'description' => 'Words to search for.',
					),
					'status'   => array(
						'type'        => 'string',
						'description' => 'Only this status (publish, draft, pending, private, future, trash). Default: all except trash.',
					),
					'parent'   => array(
						'type'        => 'integer',
						'description' => 'Only children of this item.',
					),
					'per_page' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 50,
						'description' => 'Items per page (default 20).',
					),
					'page'     => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'Page number (default 1).',
					),
					'orderby'  => array(
						'type'        => 'string',
						'enum'        => array( 'date', 'modified', 'title', 'menu_order', 'id', 'relevance' ),
						'description' => 'Sort field (default date, newest first).',
					),
				),
				'required'   => array( 'type' ),
			),
			'annotations' => $readonly,
		),
		array(
			'name'        => 'get_content',
			'title'       => 'Get content',
			'description' => 'Gets one item in full, with its raw editable title, content and excerpt.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'type' => $type,
					'id'   => $id,
				),
				'required'   => array( 'type', 'id' ),
			),
			'annotations' => $readonly,
		),
		array(
			'name'        => 'create_content',
			'title'       => 'Create content',
			'description' => 'Creates an item. Saved as a draft unless status says otherwise.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array_merge( array( 'type' => $type ), $fields ),
				'required'   => array( 'type', 'title' ),
			),
			'annotations' => array(
				'readOnlyHint'    => false,
				'destructiveHint' => false,
				'openWorldHint'   => false,
			),
		),
		array(
			'name'        => 'update_content',
			'title'       => 'Update content',
			'description' => 'Changes an existing item. Only the fields given are changed. WordPress keeps a revision, so changes can be undone from the editor.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array_merge(
					array(
						'type' => $type,
						'id'   => $id,
					),
					$fields
				),
				'required'   => array( 'type', 'id' ),
			),
			'annotations' => array(
				'readOnlyHint'    => false,
				'destructiveHint' => true,
				'idempotentHint'  => true,
				'openWorldHint'   => false,
			),
		),
		array(
			'name'        => 'trash_content',
			'title'       => 'Move content to the bin',
			'description' => 'Moves an item to the bin, where it can be restored from for 30 days. Nothing is deleted permanently.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'type' => $type,
					'id'   => $id,
				),
				'required'   => array( 'type', 'id' ),
			),
			'annotations' => array(
				'readOnlyHint'    => false,
				'destructiveHint' => true,
				'openWorldHint'   => false,
			),
		),
		array(
			'name'        => 'list_terms',
			'title'       => 'List terms',
			'description' => 'Lists categories, tags or other terms of one taxonomy.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type'        => 'string',
						'description' => 'Taxonomy, e.g. "category" or "post_tag".',
					),
					'search'   => array(
						'type'        => 'string',
						'description' => 'Words to search for.',
					),
					'per_page' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 100,
						'description' => 'Items per page (default 50).',
					),
				),
				'required'   => array( 'taxonomy' ),
			),
			'annotations' => $readonly,
		),
		array(
			'name'        => 'create_term',
			'title'       => 'Create term',
			'description' => 'Creates a category, tag or other term.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'taxonomy'    => array(
						'type'        => 'string',
						'description' => 'Taxonomy, e.g. "category" or "post_tag".',
					),
					'name'        => array(
						'type'        => 'string',
						'description' => 'Name.',
					),
					'description' => array(
						'type'        => 'string',
						'description' => 'Description.',
					),
					'parent'      => array(
						'type'        => 'integer',
						'description' => 'Parent term ID, for hierarchical taxonomies.',
					),
				),
				'required'   => array( 'taxonomy', 'name' ),
			),
			'annotations' => array(
				'readOnlyHint'    => false,
				'destructiveHint' => false,
				'openWorldHint'   => false,
			),
		),
	);
}

/**
 * Maps tool names to the functions that run them.
 *
 * @return array Callables keyed by tool name.
 */
function blueworx_mcp_tool_handlers() {
	return array(
		'get_site_info'  => 'blueworx_mcp_tool_site_info',
		'list_content'   => 'blueworx_mcp_tool_list_content',
		'get_content'    => 'blueworx_mcp_tool_get_content',
		'create_content' => 'blueworx_mcp_tool_create_content',
		'update_content' => 'blueworx_mcp_tool_update_content',
		'trash_content'  => 'blueworx_mcp_tool_trash_content',
		'list_terms'     => 'blueworx_mcp_tool_list_terms',
		'create_term'    => 'blueworx_mcp_tool_create_term',
	);
}

/**
 * Runs a tool and wraps its answer the way tools/call expects.
 *
 * A tool that fails returns isError rather than a JSON-RPC error, so Claude
 * sees the reason and can correct itself.
 *
 * @param string $name  Tool name.
 * @param array  $args  Arguments.
 * @param array  $grant Grant the call is made under.
 * @return array tools/call result.
 */
function blueworx_mcp_call_tool( $name, $args, $grant ) {
	$result = call_user_func( blueworx_mcp_tool_handlers()[ $name ], $args );

	if ( is_wp_error( $result ) ) {
		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $result->get_error_message(),
				),
			),
			'isError' => true,
		);
	}

	if ( isset( $result['_log'] ) ) {
		blueworx_mcp_log( (int) $grant['user_id'], $grant['client_name'], $result['_log'][0], $result['_log'][1] );
		unset( $result['_log'] );
	}

	return array(
		'content'           => array(
			array(
				'type' => 'text',
				'text' => wp_json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ),
			),
		),
		'structuredContent' => (object) $result,
	);
}

/**
 * Runs a request through WordPress's own REST controllers, as the current user.
 *
 * @param string $method HTTP method.
 * @param string $route  Route, e.g. /wp/v2/pages.
 * @param array  $params Parameters.
 * @return array|WP_Error Response data, or the error WordPress gave.
 */
function blueworx_mcp_rest( $method, $route, $params = array() ) {
	$request = new WP_REST_Request( $method, $route );

	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	$response = rest_do_request( $request );

	if ( $response->is_error() ) {
		$error = $response->as_error();

		return new WP_Error( $error->get_error_code(), wp_strip_all_tags( $error->get_error_message() ) );
	}

	return $response->get_data();
}

/**
 * Looks up a content type argument.
 *
 * @param array $args Tool arguments.
 * @return array|WP_Error Type details plus 'name', or why it is not usable.
 */
function blueworx_mcp_resolve_type( $args ) {
	$name  = isset( $args['type'] ) ? sanitize_key( (string) $args['type'] ) : '';
	$types = blueworx_mcp_content_types();

	// Accept the REST base too ("pages"), which is what people often say.
	foreach ( $types as $type => $info ) {
		if ( $info['rest_base'] === $name ) {
			$name = $type;
		}
	}

	if ( ! isset( $types[ $name ] ) ) {
		return new WP_Error( 'unknown_type', 'Unknown content type "' . $name . '". Available: ' . implode( ', ', array_keys( $types ) ) . '.' );
	}

	$types[ $name ]['name']  = $name;
	$types[ $name ]['route'] = '/' . $types[ $name ]['namespace'] . '/' . $types[ $name ]['rest_base'];

	return $types[ $name ];
}

/**
 * Trims a REST item down to what is useful to read.
 *
 * @param array $item REST item (context edit).
 * @param bool  $full Whether to include the body.
 * @return array Item.
 */
function blueworx_mcp_shape_item( $item, $full ) {
	$raw = function ( $field ) use ( $item ) {
		if ( ! isset( $item[ $field ] ) ) {
			return null;
		}

		if ( is_array( $item[ $field ] ) ) {
			return isset( $item[ $field ]['raw'] ) ? $item[ $field ]['raw'] : ( $item[ $field ]['rendered'] ?? '' );
		}

		return $item[ $field ];
	};

	$out = array(
		'id'       => $item['id'] ?? 0,
		'type'     => $item['type'] ?? '',
		'title'    => $raw( 'title' ),
		'status'   => $item['status'] ?? '',
		'slug'     => $item['slug'] ?? '',
		'date'     => $item['date'] ?? '',
		'modified' => $item['modified'] ?? '',
		'link'     => $item['link'] ?? '',
	);

	if ( isset( $item['parent'] ) ) {
		$out['parent'] = $item['parent'];
	}

	if ( ! $full ) {
		return $out;
	}

	$out['content']        = $raw( 'content' );
	$out['excerpt']        = $raw( 'excerpt' );
	$out['author']         = $item['author'] ?? 0;
	$out['featured_media'] = $item['featured_media'] ?? 0;
	$out['menu_order']     = $item['menu_order'] ?? 0;

	$terms = array();

	foreach ( blueworx_mcp_taxonomies() as $tax ) {
		if ( isset( $item[ $tax['rest_base'] ] ) && is_array( $item[ $tax['rest_base'] ] ) ) {
			$terms[ $tax['rest_base'] ] = $item[ $tax['rest_base'] ];
		}
	}

	$out['terms']                = (object) $terms;
	$out['built_with_elementor'] = 'builder' === get_post_meta( (int) $out['id'], '_elementor_edit_mode', true );

	return $out;
}

/**
 * Collects the writable fields from tool arguments.
 *
 * @param array $args Tool arguments.
 * @return array REST parameters.
 */
function blueworx_mcp_write_params( $args ) {
	$params = array();

	foreach ( array( 'title', 'content', 'excerpt', 'status', 'slug', 'date' ) as $field ) {
		if ( isset( $args[ $field ] ) && is_scalar( $args[ $field ] ) ) {
			$params[ $field ] = (string) $args[ $field ];
		}
	}

	foreach ( array( 'parent', 'menu_order', 'featured_media' ) as $field ) {
		if ( isset( $args[ $field ] ) && is_numeric( $args[ $field ] ) ) {
			$params[ $field ] = (int) $args[ $field ];
		}
	}

	if ( isset( $args['terms'] ) && is_array( $args['terms'] ) ) {
		$allowed = wp_list_pluck( blueworx_mcp_taxonomies(), 'rest_base' );

		foreach ( $args['terms'] as $base => $ids ) {
			if ( in_array( $base, $allowed, true ) && is_array( $ids ) ) {
				$params[ $base ] = array_map( 'intval', $ids );
			}
		}
	}

	return $params;
}

/**
 * Tool: get_site_info.
 *
 * @param array $args Arguments (none).
 * @return array Result.
 */
function blueworx_mcp_tool_site_info( $args ) {
	unset( $args );

	$types = array();

	foreach ( blueworx_mcp_content_types() as $name => $info ) {
		$types[] = array(
			'type'  => $name,
			'label' => $info['label'],
		);
	}

	$taxonomies = array();

	foreach ( blueworx_mcp_taxonomies() as $name => $info ) {
		$taxonomies[] = array(
			'taxonomy'  => $name,
			'rest_base' => $info['rest_base'],
			'label'     => $info['label'],
		);
	}

	return array(
		'name'          => get_bloginfo( 'name' ),
		'description'   => get_bloginfo( 'description' ),
		'url'           => home_url(),
		'timezone'      => wp_timezone_string(),
		'language'      => get_bloginfo( 'language' ),
		'content_types' => $types,
		'taxonomies'    => $taxonomies,
	);
}

/**
 * Tool: list_content.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_list_content( $args ) {
	$type = blueworx_mcp_resolve_type( $args );

	if ( is_wp_error( $type ) ) {
		return $type;
	}

	$params = array(
		'context'  => 'edit',
		'per_page' => isset( $args['per_page'] ) ? max( 1, min( 50, (int) $args['per_page'] ) ) : 20,
		'page'     => isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1,
		'status'   => ! empty( $args['status'] ) ? array( sanitize_key( (string) $args['status'] ) ) : array( 'publish', 'future', 'draft', 'pending', 'private' ),
	);

	if ( ! empty( $args['search'] ) ) {
		$params['search'] = (string) $args['search'];
	}

	if ( isset( $args['parent'] ) && is_numeric( $args['parent'] ) ) {
		$params['parent'] = array( (int) $args['parent'] );
	}

	if ( ! empty( $args['orderby'] ) ) {
		$params['orderby'] = sanitize_key( (string) $args['orderby'] );
		$params['order']   = in_array( $params['orderby'], array( 'title', 'menu_order' ), true ) ? 'asc' : 'desc';
	}

	$items = blueworx_mcp_rest( 'GET', $type['route'], $params );

	if ( is_wp_error( $items ) ) {
		return $items;
	}

	return array(
		'type'  => $type['name'],
		'page'  => $params['page'],
		'items' => array_map(
			function ( $item ) {
				return blueworx_mcp_shape_item( $item, false );
			},
			(array) $items
		),
	);
}

/**
 * Tool: get_content.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_get_content( $args ) {
	$type = blueworx_mcp_resolve_type( $args );

	if ( is_wp_error( $type ) ) {
		return $type;
	}

	$item = blueworx_mcp_rest( 'GET', $type['route'] . '/' . absint( $args['id'] ?? 0 ), array( 'context' => 'edit' ) );

	return is_wp_error( $item ) ? $item : blueworx_mcp_shape_item( $item, true );
}

/**
 * Tool: create_content.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_create_content( $args ) {
	$type = blueworx_mcp_resolve_type( $args );

	if ( is_wp_error( $type ) ) {
		return $type;
	}

	$params = blueworx_mcp_write_params( $args );

	if ( ! isset( $params['status'] ) ) {
		$params['status'] = 'draft';
	}

	$item = blueworx_mcp_rest( 'POST', $type['route'], array_merge( $params, array( 'context' => 'edit' ) ) );

	if ( is_wp_error( $item ) ) {
		return $item;
	}

	$out         = blueworx_mcp_shape_item( $item, false );
	$out['_log'] = array( 'created', $type['label'] . ' #' . $out['id'] . ' ' . $out['title'] );

	return $out;
}

/**
 * Tool: update_content.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_update_content( $args ) {
	$type = blueworx_mcp_resolve_type( $args );

	if ( is_wp_error( $type ) ) {
		return $type;
	}

	$id     = absint( $args['id'] ?? 0 );
	$params = blueworx_mcp_write_params( $args );

	if ( empty( $params ) ) {
		return new WP_Error( 'nothing_to_change', 'No fields to change were given.' );
	}

	$item = blueworx_mcp_rest( 'POST', $type['route'] . '/' . $id, array_merge( $params, array( 'context' => 'edit' ) ) );

	if ( is_wp_error( $item ) ) {
		return $item;
	}

	$out = blueworx_mcp_shape_item( $item, false );

	if ( isset( $params['content'] ) && 'builder' === get_post_meta( $id, '_elementor_edit_mode', true ) ) {
		$out['warning'] = 'This page is built with Elementor, so the content change is saved but will not show on the live page. Edit it in Elementor instead.';
	}

	$out['_log'] = array( 'updated', $type['label'] . ' #' . $out['id'] . ' ' . $out['title'] );

	return $out;
}

/**
 * Tool: trash_content.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_trash_content( $args ) {
	$type = blueworx_mcp_resolve_type( $args );

	if ( is_wp_error( $type ) ) {
		return $type;
	}

	$id = absint( $args['id'] ?? 0 );

	if ( ! EMPTY_TRASH_DAYS ) {
		return new WP_Error( 'no_trash', 'This site has the bin switched off, so moving to the bin would delete permanently. Not done.' );
	}

	// force is false: to the bin, never a permanent delete.
	$item = blueworx_mcp_rest(
		'DELETE',
		$type['route'] . '/' . $id,
		array(
			'force'   => false,
			'context' => 'edit',
		)
	);

	if ( is_wp_error( $item ) ) {
		return $item;
	}

	$out         = blueworx_mcp_shape_item( $item, false );
	$out['_log'] = array( 'moved to bin', $type['label'] . ' #' . $out['id'] . ' ' . $out['title'] );

	return $out;
}

/**
 * Looks up a taxonomy argument.
 *
 * @param array $args Tool arguments.
 * @return array|WP_Error Taxonomy details plus 'route', or why it is not usable.
 */
function blueworx_mcp_resolve_taxonomy( $args ) {
	$name       = isset( $args['taxonomy'] ) ? sanitize_key( (string) $args['taxonomy'] ) : '';
	$taxonomies = blueworx_mcp_taxonomies();

	foreach ( $taxonomies as $tax => $info ) {
		if ( $info['rest_base'] === $name ) {
			$name = $tax;
		}
	}

	if ( ! isset( $taxonomies[ $name ] ) ) {
		return new WP_Error( 'unknown_taxonomy', 'Unknown taxonomy "' . $name . '". Available: ' . implode( ', ', array_keys( $taxonomies ) ) . '.' );
	}

	$taxonomies[ $name ]['route'] = '/' . $taxonomies[ $name ]['namespace'] . '/' . $taxonomies[ $name ]['rest_base'];

	return $taxonomies[ $name ];
}

/**
 * Trims a REST term down to what is useful.
 *
 * @param array $term REST term.
 * @return array Term.
 */
function blueworx_mcp_shape_term( $term ) {
	return array(
		'id'     => $term['id'] ?? 0,
		'name'   => $term['name'] ?? '',
		'slug'   => $term['slug'] ?? '',
		'count'  => $term['count'] ?? 0,
		'parent' => $term['parent'] ?? 0,
	);
}

/**
 * Tool: list_terms.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_list_terms( $args ) {
	$tax = blueworx_mcp_resolve_taxonomy( $args );

	if ( is_wp_error( $tax ) ) {
		return $tax;
	}

	$params = array(
		'per_page'   => isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 50,
		'hide_empty' => false,
	);

	if ( ! empty( $args['search'] ) ) {
		$params['search'] = (string) $args['search'];
	}

	$terms = blueworx_mcp_rest( 'GET', $tax['route'], $params );

	if ( is_wp_error( $terms ) ) {
		return $terms;
	}

	return array( 'terms' => array_map( 'blueworx_mcp_shape_term', (array) $terms ) );
}

/**
 * Tool: create_term.
 *
 * @param array $args Arguments.
 * @return array|WP_Error Result.
 */
function blueworx_mcp_tool_create_term( $args ) {
	$tax = blueworx_mcp_resolve_taxonomy( $args );

	if ( is_wp_error( $tax ) ) {
		return $tax;
	}

	$params = array( 'name' => (string) ( $args['name'] ?? '' ) );

	if ( isset( $args['description'] ) ) {
		$params['description'] = (string) $args['description'];
	}

	if ( isset( $args['parent'] ) && is_numeric( $args['parent'] ) ) {
		$params['parent'] = (int) $args['parent'];
	}

	$term = blueworx_mcp_rest( 'POST', $tax['route'], $params );

	if ( is_wp_error( $term ) ) {
		return $term;
	}

	$out         = blueworx_mcp_shape_term( $term );
	$out['_log'] = array( 'created', $tax['label'] . ' ' . $out['name'] );

	return $out;
}
