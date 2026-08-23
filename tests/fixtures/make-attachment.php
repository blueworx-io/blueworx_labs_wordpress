<?php
/**
 * Test fixture — NOT part of the plugin.
 *
 * Creates a real PNG attachment and prints its ID, so a spec can drive the
 * replace-file control against something that genuinely exists in the media
 * library. Uploading through the admin would work too, but the uploader there
 * is an AJAX widget whose failures read as test flake rather than as the thing
 * under test.
 *
 * "delete" removes the attachment and its file again, so a run leaves the
 * library as it found it.
 *
 * Usage: php make-attachment.php /absolute/path/to/wp-load.php create|delete [id]
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$command = isset( $argv[2] ) ? $argv[2] : '';
$id      = isset( $argv[3] ) ? (int) $argv[3] : 0;

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// See impostor-support-user.php: without this, Site Protection can wp_die() the
// script the moment WordPress finishes loading.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;
require_once ABSPATH . 'wp-admin/includes/image.php';

if ( 'delete' === $command ) {
	if ( $id > 0 ) {
		wp_delete_attachment( $id, true );
	}

	exit( 0 );
}

// A 1x1 red PNG, byte for byte. Small enough to inline, and a real image, so
// wp_generate_attachment_metadata() has something to read.
$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );

$uploads = wp_upload_dir();
$name    = 'blueworx-replace-fixture-' . time() . '.png';
$path    = trailingslashit( $uploads['path'] ) . $name;

if ( false === file_put_contents( $path, $png ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture, not plugin code.
	fwrite( STDERR, "could not write $path\n" );
	exit( 1 );
}

$attachment_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'BlueWorx replace fixture',
		'post_status'    => 'inherit',
	),
	$path
);

if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
	fwrite( STDERR, "could not insert attachment\n" );
	exit( 1 );
}

wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $path ) );

echo (int) $attachment_id;
