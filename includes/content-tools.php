<?php
/**
 * Content tools: duplicate a post, and point one at an external address.
 *
 * Replaces the Admin and Site Enhancements `enable_duplication` and
 * `enable_external_permalinks` modules.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The meta key holding an external destination.
 */
const BLUEWORX_EXTERNAL_URL_META = '_blueworx_external_url';

/**
 * Whether the Duplicate row action is offered.
 *
 * @return bool True when enabled.
 */
function blueworx_duplicate_enabled() {
	return '0' !== get_option( 'blueworx_duplicate_enabled', '1' );
}

/**
 * Whether external permalinks are offered.
 *
 * Off by default. It changes where a link goes — the one thing an editor least
 * expects a page to do — and most sites never want it. A site that does turns it
 * on deliberately.
 *
 * @return bool True when enabled.
 */
function blueworx_external_permalinks_enabled() {
	return '1' === get_option( 'blueworx_external_permalinks_enabled', '0' );
}

/**
 * Gets the post types these tools apply to.
 *
 * Every public post type with an editing UI, so a site's own custom types are
 * covered without this file having to know their names.
 *
 * @return array Post type names.
 */
function blueworx_content_tools_post_types() {
	$types = get_post_types(
		array(
			'show_ui' => true,
		),
		'names'
	);

	unset( $types['attachment'] );

	/**
	 * Filters the post types offered the duplicate and external-link tools.
	 *
	 * @param array $types Post type names.
	 */
	return (array) apply_filters( 'blueworx_content_tools_post_types', array_values( $types ) );
}

/*
 * ---------------------------------------------------------------------------
 * Duplicate
 * ---------------------------------------------------------------------------
 */

/**
 * Adds the Duplicate link to a row in a post list table.
 *
 * @param array   $actions Row actions.
 * @param WP_Post $post    Post the row belongs to.
 * @return array Row actions.
 */
function blueworx_duplicate_row_action( $actions, $post ) {
	if ( ! in_array( $post->post_type, blueworx_content_tools_post_types(), true ) ) {
		return $actions;
	}

	$type = get_post_type_object( $post->post_type );

	if ( ! $type || ! current_user_can( $type->cap->create_posts ) || ! current_user_can( 'edit_post', $post->ID ) ) {
		return $actions;
	}

	$url = wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'blueworx_duplicate_post',
				'post'   => $post->ID,
			),
			admin_url( 'admin-post.php' )
		),
		'blueworx_duplicate_post_' . $post->ID
	);

	$actions['blueworx_duplicate'] = sprintf(
		'<a href="%1$s">%2$s</a>',
		esc_url( $url ),
		esc_html__( 'Duplicate', 'blueworx-labs-wordpress' )
	);

	return $actions;
}

/**
 * Copies a post, its taxonomy terms and all of its meta into a new draft.
 *
 * Meta is copied wholesale rather than by an allowlist: on this stack the
 * field data an editor actually cares about — everything the page is built
 * from — lives in post meta, and a copy that drops it is a copy of the title
 * only. The few keys that must NOT survive are named instead, which is a list
 * that can be reasoned about.
 *
 * This handler deliberately does NOT call blueworx_require_post_request(), which
 * every other admin_post handler here does. A row action is a link, so the
 * request is a GET by construction — core's own Trash, Restore and Delete row
 * actions work the same way, and the protection against a forged one is the
 * per-post nonce checked below plus the capability checks after it. A method
 * check would simply make the feature not work.
 *
 * @return void
 */
function blueworx_duplicate_post_handle() {
	$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified on the next line.

	check_admin_referer( 'blueworx_duplicate_post_' . $post_id );

	$post = $post_id ? get_post( $post_id ) : null;

	if ( ! $post instanceof WP_Post ) {
		wp_die( esc_html__( 'That item no longer exists.', 'blueworx-labs-wordpress' ) );
	}

	$type = get_post_type_object( $post->post_type );

	if ( ! $type || ! current_user_can( $type->cap->create_posts ) || ! current_user_can( 'edit_post', $post->ID ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	$new_id = wp_insert_post(
		array(
			'post_author'    => get_current_user_id(),
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_mime_type' => $post->post_mime_type,
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'draft',
			'post_title'     => sprintf(
				/* translators: %s: title of the item being copied. */
				__( '%s (copy)', 'blueworx-labs-wordpress' ),
				$post->post_title
			),
			'post_type'      => $post->post_type,
			'menu_order'     => $post->menu_order,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
		),
		true
	);

	if ( is_wp_error( $new_id ) ) {
		wp_die( esc_html( $new_id->get_error_message() ) );
	}

	foreach ( (array) get_object_taxonomies( $post->post_type ) as $taxonomy ) {
		$terms = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'slugs' ) );

		if ( ! is_wp_error( $terms ) ) {
			wp_set_object_terms( $new_id, $terms, $taxonomy );
		}
	}

	blueworx_duplicate_copy_meta( $post->ID, (int) $new_id );

	wp_safe_redirect( admin_url( 'post.php?post=' . (int) $new_id . '&action=edit' ) );
	exit;
}

/**
 * Gets the meta keys never carried onto a copy.
 *
 * Everything here identifies the ORIGINAL rather than describing it: an edit
 * lock belonging to whoever last opened the source, a pointer to the source's
 * own revision, and the transient state WordPress uses to track an in-progress
 * edit. Copying them attaches the new draft to the wrong post.
 *
 * @return array Meta keys.
 */
function blueworx_duplicate_skipped_meta_keys() {
	/**
	 * Filters the meta keys excluded when duplicating.
	 *
	 * @param array $keys Meta keys.
	 */
	return (array) apply_filters(
		'blueworx_duplicate_skipped_meta_keys',
		array(
			'_edit_lock',
			'_edit_last',
			'_wp_old_slug',
			'_wp_old_date',
			'_wp_trash_meta_status',
			'_wp_trash_meta_time',
			'_wp_desired_post_slug',
		)
	);
}

/**
 * Copies every meta row from one post to another.
 *
 * @param int $from_id Source post.
 * @param int $to_id   Destination post.
 * @return void
 */
function blueworx_duplicate_copy_meta( $from_id, $to_id ) {
	$skip = blueworx_duplicate_skipped_meta_keys();
	$meta = get_post_meta( $from_id );

	if ( ! is_array( $meta ) ) {
		return;
	}

	foreach ( $meta as $key => $values ) {
		if ( in_array( $key, $skip, true ) ) {
			continue;
		}

		foreach ( (array) $values as $value ) {
			// Stored values arrive serialized as strings; maybe_unserialize turns
			// an array back into an array so add_post_meta re-serializes it once
			// rather than storing a serialized string of a serialized string.
			add_post_meta( $to_id, $key, maybe_unserialize( $value ) );
		}
	}
}

if ( blueworx_feature_enabled( 'content_tools' ) && blueworx_duplicate_enabled() ) {
	add_filter( 'post_row_actions', 'blueworx_duplicate_row_action', 10, 2 );
	add_filter( 'page_row_actions', 'blueworx_duplicate_row_action', 10, 2 );
	add_action( 'admin_post_blueworx_duplicate_post', 'blueworx_duplicate_post_handle' );
}

/*
 * ---------------------------------------------------------------------------
 * External permalinks
 * ---------------------------------------------------------------------------
 */

/**
 * Adds the external-address box to the editor sidebar.
 *
 * @return void
 */
function blueworx_external_url_meta_box() {
	foreach ( blueworx_content_tools_post_types() as $type ) {
		add_meta_box(
			'blueworx-external-url',
			__( 'Link to another site', 'blueworx-labs-wordpress' ),
			'blueworx_external_url_render_box',
			$type,
			'side',
			'default'
		);
	}
}

/**
 * Renders the external-address box.
 *
 * @param WP_Post $post Post being edited.
 * @return void
 */
function blueworx_external_url_render_box( $post ) {
	$value = (string) get_post_meta( $post->ID, BLUEWORX_EXTERNAL_URL_META, true );

	wp_nonce_field( 'blueworx_external_url_' . $post->ID, 'blueworx_external_url_nonce' );
	?>
	<p>
		<label class="screen-reader-text" for="blueworx_external_url"><?php esc_html_e( 'External address', 'blueworx-labs-wordpress' ); ?></label>
		<input type="url" class="widefat" id="blueworx_external_url" name="blueworx_external_url" value="<?php echo esc_attr( $value ); ?>" placeholder="https://" />
	</p>
	<p class="description">
		<?php esc_html_e( 'Fill this in and anyone clicking this item in a menu or a listing goes straight to that address instead. Leave it empty for normal behaviour.', 'blueworx-labs-wordpress' ); ?>
	</p>
	<?php
}

/**
 * Saves the external address.
 *
 * @param int $post_id Post being saved.
 * @return void
 */
function blueworx_external_url_save( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	$nonce = isset( $_POST['blueworx_external_url_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['blueworx_external_url_nonce'] ) ) : '';

	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'blueworx_external_url_' . $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$raw = isset( $_POST['blueworx_external_url'] ) ? sanitize_text_field( wp_unslash( $_POST['blueworx_external_url'] ) ) : '';
	$url = esc_url_raw( $raw, array( 'http', 'https' ) );

	if ( '' === $url ) {
		delete_post_meta( $post_id, BLUEWORX_EXTERNAL_URL_META );

		return;
	}

	update_post_meta( $post_id, BLUEWORX_EXTERNAL_URL_META, $url );
}

/**
 * Swaps the permalink for the external address.
 *
 * The three permalink filters do not agree on their second argument: `post_link`
 * and `post_type_link` pass a WP_Post, `page_link` passes a post ID. Both shapes
 * are accepted here rather than splitting this into two near-identical callbacks.
 *
 * @param string      $permalink Permalink.
 * @param WP_Post|int $post      Post object, or post ID.
 * @return string Permalink.
 */
function blueworx_external_url_permalink( $permalink, $post ) {
	$post_id = $post instanceof WP_Post ? $post->ID : (int) $post;

	if ( $post_id <= 0 ) {
		return $permalink;
	}

	$external = (string) get_post_meta( $post_id, BLUEWORX_EXTERNAL_URL_META, true );

	return '' === $external ? $permalink : $external;
}

/**
 * Sends a direct visit to the item's own address on to the external one.
 *
 * The permalink filter covers links the site generates. Somebody arriving at
 * the item's real address — a bookmark, a search result from before the address
 * was set — would otherwise see a page that is not meant to exist any more.
 *
 * A 302, not a 301: this is a setting an editor can clear at any time, and a
 * permanent redirect would stay cached in visitors' browsers long after they
 * had.
 *
 * @return void
 */
function blueworx_external_url_redirect() {
	if ( ! is_singular() ) {
		return;
	}

	$post_id = get_queried_object_id();

	if ( ! $post_id ) {
		return;
	}

	$external = (string) get_post_meta( $post_id, BLUEWORX_EXTERNAL_URL_META, true );

	if ( '' === $external ) {
		return;
	}

	// Not wp_safe_redirect(): pointing somewhere off this site is the entire
	// feature. The value can only be set by a user who can edit the post, and is
	// stored through esc_url_raw() restricted to http and https.
	wp_redirect( $external, 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- An off-site destination is the point of this feature; see above.
	exit;
}

if ( blueworx_feature_enabled( 'content_tools' ) && blueworx_external_permalinks_enabled() ) {
	add_action( 'add_meta_boxes', 'blueworx_external_url_meta_box' );
	add_action( 'save_post', 'blueworx_external_url_save' );
	add_filter( 'post_link', 'blueworx_external_url_permalink', 10, 2 );
	add_filter( 'page_link', 'blueworx_external_url_permalink', 10, 2 );
	add_filter( 'post_type_link', 'blueworx_external_url_permalink', 10, 2 );
	add_action( 'template_redirect', 'blueworx_external_url_redirect' );
}
