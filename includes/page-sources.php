<?php
/**
 * The Pages list's Source column: which plugin built each page, and the
 * protection that comes with being built by one.
 *
 * Plugins make pages the site is served from — a shop's checkout, a club's
 * rules page — and they turn up in WordPress's Pages list looking like any
 * other. This column says whose they are, and any page with a source is
 * read-only from the list: View and Edit are the only row actions offered,
 * and trashing or deleting it is refused whichever way the request comes.
 *
 * A plugin names its pages through one filter:
 *
 *     add_filter( 'blueworx_page_source', function ( $label, $post_id ) {
 *         return my_plugin_owns( $post_id ) ? 'Club page' : $label;
 *     }, 10, 2 );
 *
 * Always on: the column costs nothing on a site where no plugin answers.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The list column's key. */
define( 'BLUEWORX_PAGE_SOURCE_COLUMN', 'blueworx_page_source' );

/**
 * Who built a page, or '' when no plugin claims it.
 *
 * @param int $post_id The page.
 * @return string
 */
function blueworx_page_source( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return '';
	}
	$label = apply_filters( 'blueworx_page_source', '', $post_id );
	return is_string( $label ) ? trim( $label ) : '';
}

/**
 * The Pages list's columns with Source beside the title, where it is read.
 * Pure.
 *
 * @param array<string,string> $columns WordPress's columns.
 * @return array<string,string>
 */
function blueworx_page_sources_columns( $columns ) {
	$out = array();
	foreach ( (array) $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'title' === $key ) {
			$out[ BLUEWORX_PAGE_SOURCE_COLUMN ] = 'Source';
		}
	}
	if ( ! isset( $out[ BLUEWORX_PAGE_SOURCE_COLUMN ] ) ) {
		$out[ BLUEWORX_PAGE_SOURCE_COLUMN ] = 'Source';
	}
	return $out;
}

/**
 * The row actions a page keeps. Pure.
 *
 * An allowlist rather than a list of removals: another plugin can add a row
 * action of its own, and anything not thought about should not be offered on
 * a page the site depends on.
 *
 * @param array<string,string> $actions   What WordPress offered.
 * @param bool                 $has_source Whether the page has a source.
 * @return array<string,string>
 */
function blueworx_page_sources_row_actions( $actions, $has_source ) {
	$actions = is_array( $actions ) ? $actions : array();
	if ( ! $has_source ) {
		return $actions;
	}
	return array_intersect_key( $actions, array_flip( array( 'edit', 'view' ) ) );
}

/**
 * Prints the column on a row.
 *
 * @param string $column  Which column is being printed.
 * @param int    $post_id The page the row is for.
 * @return void
 */
function blueworx_page_sources_column( $column, $post_id ) {
	if ( BLUEWORX_PAGE_SOURCE_COLUMN !== $column ) {
		return;
	}
	echo esc_html( blueworx_page_source( $post_id ) );
}

/**
 * The row-actions filter: a page with a source keeps only View and Edit.
 *
 * @param array<string,string> $actions What WordPress offered.
 * @param WP_Post|int          $post    The page the row is for.
 * @return array<string,string>
 */
function blueworx_page_sources_filter_row_actions( $actions, $post = null ) {
	$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
	return blueworx_page_sources_row_actions( $actions, blueworx_page_source( $post_id ) !== '' );
}

/**
 * Refuses to trash or delete a page a plugin built, whoever asked.
 *
 * Both hooks fire before WordPress does the deed, so stopping the request
 * here stops the deletion — from the list, a bulk action, the REST API or
 * another plugin. Blunt, deliberately: there is no right way to carry on
 * once a page the site routes through has gone.
 *
 * @param int $post_id The page about to go.
 * @return void
 */
function blueworx_page_sources_refuse_deletion( $post_id ) {
	$source = blueworx_page_source( $post_id );
	if ( '' === $source ) {
		return;
	}
	wp_die(
		esc_html(
			sprintf(
				/* translators: %s: what the Source column calls the page, e.g. "Commerce page". */
				__( 'This is a %s. The site is served from it, so it cannot be deleted. Open it to edit it instead.', 'blueworx-labs-wordpress' ),
				strtolower( $source )
			)
		),
		esc_html( $source ),
		array( 'response' => 403 )
	);
}

add_filter( 'manage_pages_columns', 'blueworx_page_sources_columns' );
add_action( 'manage_pages_custom_column', 'blueworx_page_sources_column', 10, 2 );
add_filter( 'page_row_actions', 'blueworx_page_sources_filter_row_actions', 10, 2 );
add_action( 'wp_trash_post', 'blueworx_page_sources_refuse_deletion' );
add_action( 'before_delete_post', 'blueworx_page_sources_refuse_deletion' );
