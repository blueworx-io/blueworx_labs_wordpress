<?php
/**
 * Renders another plugin's output — a block or a shortcode — or nothing at
 * all.
 *
 * The one place that knows do_blocks() and do_shortcode() exist, so no view
 * has to think about whether SureCart or another plugin is installed. A slot
 * with nobody to fill it answers '', and the caller draws its honest empty
 * state instead.
 *
 * A seam: the rules are pure and unit-tested, and WordPress installs the
 * real renderers at boot. The default is "nothing is installed", which is
 * the safe way round — a missing panel is obvious and recoverable, a broken
 * one is neither.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Swap the two sources a slot renders from.
 *
 * @param callable|null $blocks     Returns markup for a block name, or null when unregistered.
 * @param callable|null $shortcodes Same, for shortcode tags.
 * @return void
 */
function blueworx_store_slot_set_sources( $blocks, $shortcodes ) {
	$GLOBALS['blueworx_store_slot_blocks']     = $blocks;
	$GLOBALS['blueworx_store_slot_shortcodes'] = $shortcodes;
}

/**
 * One block's rendered output, or '' when the plugin providing it is
 * absent.
 *
 * @param string $name Block name.
 * @return string
 */
function blueworx_store_slot_block( $name ) {
	$source = isset( $GLOBALS['blueworx_store_slot_blocks'] ) ? $GLOBALS['blueworx_store_slot_blocks'] : null;
	return blueworx_store_slot_render( $source, $name );
}

/**
 * One shortcode's rendered output, or '' when it is not registered.
 *
 * @param string $tag Shortcode tag.
 * @return string
 */
function blueworx_store_slot_shortcode( $tag ) {
	$source = isset( $GLOBALS['blueworx_store_slot_shortcodes'] ) ? $GLOBALS['blueworx_store_slot_shortcodes'] : null;
	return blueworx_store_slot_render( $source, $tag );
}

/**
 * @param callable|null $source Returns markup for a name, or null when unregistered.
 * @param string        $name   Block name or shortcode tag.
 * @return string
 */
function blueworx_store_slot_render( $source, $name ) {
	if ( null === $source ) {
		return '';
	}
	try {
		$out = $source( $name );
	} catch ( Throwable $e ) {
		// The page must survive a broken source, so the failure is swallowed
		// here rather than left to propagate. But swallowed silently, it is
		// not diagnosable: a club reporting "my orders page is blank" would
		// leave nobody anything to go on. Record one line and move on.
		blueworx_store_slot_log_failure( $name, $e );
		return '';
	}
	if ( ! is_string( $out ) || '' === trim( $out ) ) {
		return '';
	}
	return $out;
}

/**
 * Write one line to the PHP error log naming the slot that failed and why.
 * Guarded the same way the WordPress calls elsewhere in this file are, so a
 * host without error_log() available cannot turn a recorded failure into a
 * second one.
 *
 * @param string    $name Block name or shortcode tag.
 * @param Throwable $e    What went wrong.
 * @return void
 */
function blueworx_store_slot_log_failure( $name, $e ) {
	if ( ! function_exists( 'error_log' ) ) {
		return;
	}
	error_log( sprintf( 'BlueWorx: store panel "%s" failed to render: %s', $name, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

/**
 * Wire the real WordPress renderers.
 *
 * A block is asked for by name and rendered from a block comment, which is
 * how WordPress renders a dynamic block outside the editor. It is only
 * asked for when the registry says it exists, so an uninstalled plugin
 * leaves the comment unrendered rather than printing it.
 *
 * A shortcode is checked the same way — by tag, because the question is
 * "will this render?", not "is a directory there?".
 *
 * @return void
 */
function blueworx_store_slot_install() {
	blueworx_store_slot_set_sources(
		static function ( $name ) {
			if ( ! class_exists( 'WP_Block_Type_Registry' ) || ! function_exists( 'do_blocks' ) ) {
				return null;
			}
			if ( ! WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				return null;
			}
			return do_blocks( '<!-- wp:' . $name . ' /-->' );
		},
		static function ( $tag ) {
			if ( ! function_exists( 'shortcode_exists' ) || ! function_exists( 'do_shortcode' ) ) {
				return null;
			}
			if ( ! shortcode_exists( $tag ) ) {
				return null;
			}
			return do_shortcode( '[' . $tag . ']' );
		}
	);
}
