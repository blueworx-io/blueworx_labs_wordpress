<?php
/**
 * Media tools: file replacement, upload size cap, and SVG uploads.
 *
 * Replaces the Admin and Site Enhancements `enable_media_replacement`,
 * `image_upload_control` and `enable_svg_upload` modules.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether in-place file replacement is offered.
 *
 * @return bool True when enabled.
 */
function blueworx_media_replace_enabled() {
	return '0' !== get_option( 'blueworx_media_replace_enabled', '1' );
}

/**
 * Whether oversized image uploads are scaled down.
 *
 * @return bool True when enabled.
 */
function blueworx_media_max_dimensions_enabled() {
	return '0' !== get_option( 'blueworx_media_max_dimensions_enabled', '1' );
}

/**
 * Gets the maximum width and height for an uploaded image.
 *
 * @return array{0:int,1:int} Width and height in pixels.
 */
function blueworx_media_max_dimensions() {
	$width  = (int) get_option( 'blueworx_media_max_width', 1920 );
	$height = (int) get_option( 'blueworx_media_max_height', 1920 );

	// A zero or negative cap would scale every upload to nothing; fall back to
	// the shipped default rather than destroying an image.
	$width  = $width > 0 ? min( $width, 10000 ) : 1920;
	$height = $height > 0 ? min( $height, 10000 ) : 1920;

	return array( $width, $height );
}

/**
 * Gets the roles allowed to upload SVG files.
 *
 * Empty means SVG upload is off, which is the default. SVG is an executable
 * document format, not a picture — it can carry script — so it is never on
 * unless somebody asks for it, and then only for the roles they name.
 *
 * @return array Role slugs.
 */
function blueworx_media_svg_roles() {
	$stored = get_option( 'blueworx_media_svg_roles', array() );

	if ( ! is_array( $stored ) ) {
		return array();
	}

	return array_values( array_unique( array_map( 'sanitize_key', $stored ) ) );
}

/**
 * Whether the current user may upload an SVG.
 *
 * @return bool True when allowed.
 */
function blueworx_media_user_can_upload_svg() {
	$roles = blueworx_media_svg_roles();

	if ( array() === $roles ) {
		return false;
	}

	$user = wp_get_current_user();

	if ( ! $user instanceof WP_User || 0 === $user->ID ) {
		return false;
	}

	return array() !== array_intersect( $roles, (array) $user->roles );
}

/*
 * ---------------------------------------------------------------------------
 * Image upload size cap
 * ---------------------------------------------------------------------------
 */

/**
 * Caps the dimensions of an uploaded image.
 *
 * Applied on `wp_handle_upload`, before WordPress builds the attachment and its
 * intermediate sizes, so every generated size derives from the capped original
 * and there is no full-size copy left behind to serve by accident.
 *
 * Only image types WordPress can actually edit are touched. Anything else —
 * SVG, PDF, video — passes through untouched, as does an image already inside
 * the cap.
 *
 * @param array $upload Upload result: file, url, type.
 * @return array The same array; the file on disk may have been rewritten.
 */
function blueworx_media_downscale_upload( $upload ) {
	if ( empty( $upload['file'] ) || empty( $upload['type'] ) ) {
		return $upload;
	}

	if ( 0 !== strpos( (string) $upload['type'], 'image/' ) ) {
		return $upload;
	}

	$size = @getimagesize( $upload['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A file that is not a readable image returns false here; that is the answer we want, not a warning in the log.

	if ( ! is_array( $size ) || empty( $size[0] ) || empty( $size[1] ) ) {
		return $upload;
	}

	list( $max_width, $max_height ) = blueworx_media_max_dimensions();

	if ( (int) $size[0] <= $max_width && (int) $size[1] <= $max_height ) {
		return $upload;
	}

	$editor = wp_get_image_editor( $upload['file'] );

	if ( is_wp_error( $editor ) ) {
		return $upload;
	}

	$resized = $editor->resize( $max_width, $max_height, false );

	if ( is_wp_error( $resized ) ) {
		return $upload;
	}

	// Saved back over the same path, so the URL WordPress has already worked out
	// stays correct and no orphan original is left in the uploads folder.
	$saved = $editor->save( $upload['file'] );

	if ( is_wp_error( $saved ) ) {
		return $upload;
	}

	return $upload;
}

if ( blueworx_feature_enabled( 'media_tools' ) && blueworx_media_max_dimensions_enabled() ) {
	add_filter( 'wp_handle_upload', 'blueworx_media_downscale_upload' );
}

/*
 * ---------------------------------------------------------------------------
 * SVG upload
 * ---------------------------------------------------------------------------
 */

/**
 * Allows the SVG mime type for permitted roles.
 *
 * @param array $mimes Allowed mime types keyed by extension pattern.
 * @return array Filtered mime types.
 */
function blueworx_media_allow_svg_mime( $mimes ) {
	if ( ! blueworx_media_user_can_upload_svg() ) {
		return $mimes;
	}

	$mimes['svg']  = 'image/svg+xml';
	$mimes['svgz'] = 'image/svg+xml';

	return $mimes;
}

/**
 * Stops WordPress rejecting an SVG on its own file-type sniffing.
 *
 * `wp_check_filetype_and_ext()` verifies an image by asking getimagesize(),
 * which cannot read SVG, so a genuine SVG comes back with a false extension
 * mismatch and is refused even when the mime type is allowed. This restores the
 * extension and type for that one case, and only for a user who is permitted to
 * upload one.
 *
 * @param array  $data     File data: ext, type, proper_filename.
 * @param string $file     Full path to the uploaded file.
 * @param string $filename Uploaded file name.
 * @return array Filtered file data.
 */
function blueworx_media_check_svg_filetype( $data, $file, $filename ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $file is part of the filter signature.
	if ( ! blueworx_media_user_can_upload_svg() ) {
		return $data;
	}

	$extension = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );

	if ( 'svg' !== $extension && 'svgz' !== $extension ) {
		return $data;
	}

	$data['ext']             = $extension;
	$data['type']            = 'image/svg+xml';
	$data['proper_filename'] = false;

	return $data;
}

/**
 * Gets the SVG elements that are never allowed through.
 *
 * An allowlist of safe elements was rejected: SVG is a large, still-growing
 * spec, and an allowlist written today silently strips a legitimate element
 * added tomorrow, producing a broken logo with no error. A denylist of the
 * elements that can execute or fetch is small, stable, and fails in the safe
 * direction — anything genuinely dangerous is a script host, an external
 * reference, or embedded foreign markup, and all three are named here.
 *
 * @return array Lowercase element names.
 */
function blueworx_media_svg_blocked_elements() {
	return array( 'script', 'foreignobject', 'iframe', 'embed', 'object', 'audio', 'video', 'handler', 'set', 'animate' );
}

/*
 * The DOM extension names its properties in camelCase — nodeName, parentNode,
 * documentElement — and there is no snake_case alias for any of them. The
 * WordPress naming sniff is disabled across the two functions that walk the
 * document, and switched back on immediately after, rather than repeated on
 * every line that touches one.
 */
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

/**
 * Strips executable content from an SVG document.
 *
 * Removes script-bearing elements, every event-handler attribute, and any
 * reference that points off-site or at a javascript: URL. What is left is
 * markup that draws, and nothing that runs.
 *
 * @param string $markup SVG file contents.
 * @return string|false Sanitised markup, or false when it is not parseable SVG.
 */
function blueworx_media_sanitize_svg_markup( $markup ) {
	$markup = (string) $markup;

	if ( '' === trim( $markup ) ) {
		return false;
	}

	// A DOCTYPE can carry an external entity declaration; there is no legitimate
	// reason for an uploaded logo to have one, so the file is refused outright
	// rather than being repaired.
	if ( false !== stripos( $markup, '<!DOCTYPE' ) || false !== stripos( $markup, '<!ENTITY' ) ) {
		return false;
	}

	if ( ! class_exists( 'DOMDocument' ) ) {
		return false;
	}

	$document                     = new DOMDocument();
	$document->preserveWhiteSpace = false;

	$previous = libxml_use_internal_errors( true );
	$loaded   = $document->loadXML( $markup, LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( ! $loaded || ! $document->documentElement || 'svg' !== strtolower( $document->documentElement->nodeName ) ) {
		return false;
	}

	$blocked = blueworx_media_svg_blocked_elements();
	$xpath   = new DOMXPath( $document );

	// Reverse order: removing a node while walking the live list forward skips
	// its neighbour.
	$nodes = $xpath->query( '//*' );

	for ( $i = $nodes->length - 1; $i >= 0; $i-- ) {
		$node = $nodes->item( $i );

		if ( ! $node instanceof DOMElement ) {
			continue;
		}

		$name = strtolower( $node->nodeName );

		if ( in_array( $name, $blocked, true ) && $node->parentNode ) {
			$node->parentNode->removeChild( $node );
			continue;
		}

		blueworx_media_sanitize_svg_attributes( $node );
	}

	// Processing instructions can carry a stylesheet reference to an off-site
	// document; nothing legitimate in an uploaded asset needs one.
	$instructions = $xpath->query( '//processing-instruction()' );

	for ( $i = $instructions->length - 1; $i >= 0; $i-- ) {
		$node = $instructions->item( $i );

		if ( $node && $node->parentNode ) {
			$node->parentNode->removeChild( $node );
		}
	}

	$sanitised = $document->saveXML();

	return false === $sanitised ? false : $sanitised;
}

/**
 * Strips dangerous attributes from one SVG element.
 *
 * @param DOMElement $node Element to clean.
 * @return void
 */
function blueworx_media_sanitize_svg_attributes( $node ) {
	if ( ! $node->hasAttributes() ) {
		return;
	}

	$remove = array();

	foreach ( $node->attributes as $attribute ) {
		$name  = strtolower( $attribute->nodeName );
		$value = (string) $attribute->nodeValue;

		// Every event handler, whatever it is called: onload, onclick, and the
		// SVG-only ones such as onbegin and onrepeat.
		if ( 0 === strpos( $name, 'on' ) ) {
			$remove[] = $attribute->nodeName;
			continue;
		}

		// Anything that can hold a URL. Fragment references (#id) are kept — a
		// gradient or clip path is normally referenced that way — but an absolute
		// or protocol-relative URL is not: it would make the logo fetch from
		// somewhere else the moment it is rendered.
		if ( in_array( $name, array( 'href', 'xlink:href', 'src', 'from', 'to', 'values', 'attributename', 'begin' ), true ) ) {
			$trimmed = strtolower( preg_replace( '/\s+/', '', $value ) );

			if ( 0 === strpos( $trimmed, '#' ) ) {
				continue;
			}

			$remove[] = $attribute->nodeName;
			continue;
		}

		// A style attribute can reach out with url() or, on old engines, run
		// through expression().
		if ( 'style' === $name && preg_match( '/url\s*\(|expression\s*\(|javascript\s*:/i', $value ) ) {
			$remove[] = $attribute->nodeName;
		}
	}

	foreach ( $remove as $name ) {
		$node->removeAttribute( $name );
	}
}

// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

/**
 * Sanitises an uploaded SVG before WordPress files it.
 *
 * The file is rewritten in place. A file that will not parse as SVG is refused
 * with an error rather than being stored half-cleaned.
 *
 * @param array $upload Upload result.
 * @return array Upload result, possibly carrying an error.
 */
function blueworx_media_sanitize_svg_upload( $upload ) {
	if ( empty( $upload['file'] ) || empty( $upload['type'] ) || 'image/svg+xml' !== $upload['type'] ) {
		return $upload;
	}

	if ( ! blueworx_media_user_can_upload_svg() ) {
		$upload['error'] = __( 'You are not allowed to upload SVG files.', 'blueworx-labs-wordpress' );

		return $upload;
	}

	// A .svgz is gzipped SVG. Rather than teach the sanitiser to decompress and
	// recompress, it is stored decompressed under the same name; the file still
	// renders, and there is exactly one code path that ever writes an SVG here.
	$contents = file_get_contents( $upload['file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a just-uploaded local temp file; WP_Filesystem is not initialised during an upload.

	if ( false !== $contents && 0 === strpos( (string) $contents, "\x1f\x8b" ) && function_exists( 'gzdecode' ) ) {
		$contents = gzdecode( $contents );
	}

	$clean = blueworx_media_sanitize_svg_markup( $contents );

	if ( false === $clean ) {
		wp_delete_file( $upload['file'] );

		$upload['error'] = __( 'That SVG could not be read, or contained something this site does not allow. Try exporting it again as a plain SVG.', 'blueworx-labs-wordpress' );

		return $upload;
	}

	file_put_contents( $upload['file'], $clean ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents -- Rewriting the uploaded temp file in place; WP_Filesystem is not initialised during an upload.

	return $upload;
}

/**
 * Gives an SVG attachment usable dimensions in the media library.
 *
 * Without this the library shows an SVG at 0x0 and the editor cannot insert it
 * at a sensible size, because getimagesize() cannot measure one.
 *
 * A flat 100x100 rather than the file's own viewBox: an SVG has no intrinsic
 * pixel size, the value here only seeds the editor's initial insert, and it is
 * marked as not-an-intermediate-size so nothing downstream treats it as a real
 * measurement. Parsing every SVG on every listing to produce a number that is
 * as arbitrary as this one would cost more and mean no more.
 *
 * @param array|false $image         Existing image data.
 * @param int         $attachment_id Attachment ID.
 * @return array|false Image data.
 */
function blueworx_media_svg_image_downsize( $image, $attachment_id ) {
	if ( 'image/svg+xml' !== get_post_mime_type( $attachment_id ) ) {
		return $image;
	}

	$url = wp_get_attachment_url( $attachment_id );

	return array( $url, 100, 100, false );
}

if ( blueworx_feature_enabled( 'media_tools' ) ) {
	add_filter( 'upload_mimes', 'blueworx_media_allow_svg_mime' );
	add_filter( 'wp_check_filetype_and_ext', 'blueworx_media_check_svg_filetype', 10, 3 );
	add_filter( 'wp_handle_upload_prefilter', 'blueworx_media_sanitize_svg_prefilter' );
	add_filter( 'wp_handle_upload', 'blueworx_media_sanitize_svg_upload' );
	add_filter( 'image_downsize', 'blueworx_media_svg_image_downsize', 10, 2 );
}

/**
 * Sanitises an SVG while it is still the PHP upload temp file.
 *
 * `wp_handle_upload` fires after the file has been moved into the uploads
 * folder, which means a hostile file exists at a public URL for the moment
 * between the two. Cleaning it here as well closes that window; the later hook
 * stays as the backstop for uploads that arrive by another route.
 *
 * @param array $file $_FILES entry.
 * @return array The same entry, possibly carrying an error.
 */
function blueworx_media_sanitize_svg_prefilter( $file ) {
	if ( empty( $file['name'] ) || empty( $file['tmp_name'] ) ) {
		return $file;
	}

	$extension = strtolower( (string) pathinfo( $file['name'], PATHINFO_EXTENSION ) );

	if ( 'svg' !== $extension && 'svgz' !== $extension ) {
		return $file;
	}

	if ( ! blueworx_media_user_can_upload_svg() ) {
		$file['error'] = __( 'You are not allowed to upload SVG files.', 'blueworx-labs-wordpress' );

		return $file;
	}

	$contents = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the PHP upload temp file; WP_Filesystem is not initialised here.

	if ( false !== $contents && 0 === strpos( (string) $contents, "\x1f\x8b" ) && function_exists( 'gzdecode' ) ) {
		$contents = gzdecode( $contents );
	}

	$clean = blueworx_media_sanitize_svg_markup( $contents );

	if ( false === $clean ) {
		$file['error'] = __( 'That SVG could not be read, or contained something this site does not allow. Try exporting it again as a plain SVG.', 'blueworx-labs-wordpress' );

		return $file;
	}

	file_put_contents( $file['tmp_name'], $clean ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents -- Rewriting the upload temp file in place.

	return $file;
}

/*
 * ---------------------------------------------------------------------------
 * Replace file in place
 * ---------------------------------------------------------------------------
 */

/**
 * Adds the replace-file control to the attachment edit screen.
 *
 * @param WP_Post $post Attachment.
 * @return void
 */
function blueworx_media_replace_field( $post ) {
	if ( ! current_user_can( 'edit_post', $post->ID ) || ! current_user_can( 'upload_files' ) ) {
		return;
	}

	$type = get_post_mime_type( $post->ID );
	?>
	<div class="misc-pub-section blueworx-media-replace">
		<h4><?php esc_html_e( 'Replace file', 'blueworx-labs-wordpress' ); ?></h4>
		<p class="description">
			<?php esc_html_e( 'Upload a new file to take the place of this one. Everywhere it is already used updates automatically, because the address does not change.', 'blueworx-labs-wordpress' ); ?>
		</p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="blueworx_media_replace" />
			<input type="hidden" name="attachment_id" value="<?php echo esc_attr( (string) $post->ID ); ?>" />
			<?php wp_nonce_field( 'blueworx_media_replace_' . $post->ID ); ?>
			<p><input type="file" name="blueworx_replacement" accept="<?php echo esc_attr( (string) $type ); ?>" required /></p>
			<p><?php submit_button( esc_html__( 'Replace', 'blueworx-labs-wordpress' ), 'secondary', 'submit', false ); ?></p>
		</form>
	</div>
	<?php
}

/**
 * Handles a replace-file submission.
 *
 * The new file is written over the old path, so the URL, the attachment ID and
 * every reference to it in existing content stay valid — which is the whole
 * point of replacing rather than re-uploading. That constrains it: the
 * replacement must be the same media type, or the file at that address would no
 * longer be what its extension claims.
 *
 * @return void
 */
function blueworx_media_replace_handle() {
	blueworx_require_post_request();

	$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;

	if ( ! $attachment_id ) {
		wp_die( esc_html__( 'No file was named to replace.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_media_replace_' . $attachment_id );

	if ( ! current_user_can( 'edit_post', $attachment_id ) || ! current_user_can( 'upload_files' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	$old_path = get_attached_file( $attachment_id );

	if ( ! $old_path || ! file_exists( $old_path ) ) {
		blueworx_media_replace_finish( $attachment_id, __( 'The original file could not be found on the server.', 'blueworx-labs-wordpress' ) );
	}

	if ( empty( $_FILES['blueworx_replacement']['name'] ) ) {
		blueworx_media_replace_finish( $attachment_id, __( 'Choose a file to upload.', 'blueworx-labs-wordpress' ) );
	}

	$old_extension = strtolower( (string) pathinfo( $old_path, PATHINFO_EXTENSION ) );
	$new_name      = sanitize_file_name( (string) wp_unslash( $_FILES['blueworx_replacement']['name'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized on this line with sanitize_file_name.
	$new_extension = strtolower( (string) pathinfo( $new_name, PATHINFO_EXTENSION ) );

	if ( $old_extension !== $new_extension ) {
		blueworx_media_replace_finish(
			$attachment_id,
			sprintf(
				/* translators: %s: file extension, e.g. "jpg". */
				__( 'The replacement has to be the same kind of file as the original (.%s), so that the existing address keeps working.', 'blueworx-labs-wordpress' ),
				$old_extension
			)
		);
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	// test_form false: this is our own form, checked with our own nonce above,
	// and core's check looks for an "action" field it names itself.
	$upload = wp_handle_upload(
		$_FILES['blueworx_replacement'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to core's own upload handler, which validates and sanitizes it.
		array(
			'test_form' => false,
		)
	);

	if ( ! is_array( $upload ) || ! empty( $upload['error'] ) ) {
		blueworx_media_replace_finish( $attachment_id, is_array( $upload ) && ! empty( $upload['error'] ) ? (string) $upload['error'] : __( 'The upload failed.', 'blueworx-labs-wordpress' ) );
	}

	if ( get_post_mime_type( $attachment_id ) !== $upload['type'] ) {
		wp_delete_file( $upload['file'] );
		blueworx_media_replace_finish( $attachment_id, __( 'The replacement has to be the same kind of file as the original.', 'blueworx-labs-wordpress' ) );
	}

	// Old generated sizes first: they are derived from a file that is about to
	// stop existing, and leaving them would serve a mix of old and new.
	$old_meta = wp_get_attachment_metadata( $attachment_id );
	$base_dir = trailingslashit( dirname( $old_path ) );

	if ( is_array( $old_meta ) && ! empty( $old_meta['sizes'] ) ) {
		foreach ( (array) $old_meta['sizes'] as $size ) {
			if ( ! empty( $size['file'] ) ) {
				wp_delete_file( $base_dir . wp_basename( (string) $size['file'] ) );
			}
		}
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Moving the validated upload onto the existing attachment path; WP_Filesystem offers no equivalent that preserves the exact destination path.
	if ( ! @rename( $upload['file'], $old_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A failed move is reported to the user below, not to the error log.
		wp_delete_file( $upload['file'] );
		blueworx_media_replace_finish( $attachment_id, __( 'The new file could not be written over the old one.', 'blueworx-labs-wordpress' ) );
	}

	update_attached_file( $attachment_id, $old_path );
	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $old_path ) );

	// The file behind the URL has changed but the URL has not, so anything
	// holding a cached copy — a CDN, a page cache — would keep serving the old
	// one.
	if ( function_exists( 'blueworx_refresh_manual_cache' ) ) {
		blueworx_refresh_manual_cache();
	}

	blueworx_media_replace_finish( $attachment_id, '', __( 'File replaced.', 'blueworx-labs-wordpress' ) );
}

/**
 * Ends a replace-file request with a message and a redirect.
 *
 * @param int    $attachment_id Attachment being edited.
 * @param string $error         Error message, or an empty string.
 * @param string $success       Success message, or an empty string.
 * @return void
 */
function blueworx_media_replace_finish( $attachment_id, $error, $success = '' ) {
	set_transient(
		'blueworx_media_replace_notice_' . $attachment_id,
		array(
			'error'   => (string) $error,
			'success' => (string) $success,
		),
		60
	);

	wp_safe_redirect( admin_url( 'post.php?post=' . (int) $attachment_id . '&action=edit' ) );
	exit;
}

/**
 * Shows the outcome of the last replacement on the attachment screen.
 *
 * @return void
 */
function blueworx_media_replace_notice() {
	$screen = get_current_screen();

	if ( ! $screen instanceof WP_Screen || 'attachment' !== $screen->id ) {
		return;
	}

	$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing; the value only selects which transient to read.

	if ( ! $post_id ) {
		return;
	}

	$notice = get_transient( 'blueworx_media_replace_notice_' . $post_id );

	if ( ! is_array( $notice ) ) {
		return;
	}

	delete_transient( 'blueworx_media_replace_notice_' . $post_id );

	if ( ! empty( $notice['error'] ) ) {
		printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $notice['error'] ) );
	}

	if ( ! empty( $notice['success'] ) ) {
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $notice['success'] ) );
	}
}

if ( blueworx_feature_enabled( 'media_tools' ) && blueworx_media_replace_enabled() ) {
	add_action( 'attachment_submitbox_misc_actions', 'blueworx_media_replace_field' );
	add_action( 'admin_post_blueworx_media_replace', 'blueworx_media_replace_handle' );
	add_action( 'admin_notices', 'blueworx_media_replace_notice' );
}
