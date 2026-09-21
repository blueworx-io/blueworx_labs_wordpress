<?php
/**
 * The admin notice that tells an owner which of the shop's pages need
 * attention, and the button that asks SureCart to put them back.
 *
 * Behind a button rather than automatic: publishing pages on a club's live
 * site is not something a plugin should do on a whim, and the state this
 * catches is rare enough that somebody should be there when it is fixed.
 *
 * The notice runs on every admin screen rather than only this plugin's own,
 * because the owner who needs it has no reason to visit a BlueWorx screen —
 * the symptom they are living with is on the front end.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the notice says. Pure.
 *
 * One notice for the lot rather than one per page, because the usual cause
 * is a shop that was never finished, and four warnings about one cause is
 * four times the noise for no extra information.
 *
 * @param array<string,string>                                  $problems From blueworx_store_problems().
 * @param array<string,array{label:string,consequence:string}>  $pages    From blueworx_store_pages().
 * @param bool                                                  $can_seed Whether SureCart can create what is missing.
 * @return array{lines:array<int,string>,button:string,footnote:string}|null Null when nothing is wrong.
 */
function blueworx_store_notice_message( $problems, $pages, $can_seed ) {
	if ( array() === $problems ) {
		return null;
	}

	$lines = array();
	foreach ( $problems as $key => $status ) {
		$page = isset( $pages[ $key ] ) ? $pages[ $key ] : array(
			'label'       => $key,
			'consequence' => 'it cannot be reached',
		);
		$state   = 'unpublished' === $status ? 'is in the trash or unpublished' : 'is missing';
		$lines[] = 'Your ' . $page['label'] . ' ' . $state . ', so ' . $page['consequence'] . '.';
	}

	$repairable      = blueworx_store_repairable( $problems, $pages );
	$has_unpublished = in_array( 'unpublished', $problems, true );
	$button          = ( array() !== $repairable && $can_seed ) || $has_unpublished
		? 'Put the missing pages back'
		: '';

	// Anything the button will not fix is named, so pressing it and finding
	// a warning still there is not a surprise. With no button on offer the
	// same sentence would be talking about a button that is not there.
	$left     = array_diff_key( $problems, $repairable );
	$footnote = '';
	if ( array() !== $left && '' !== $button ) {
		$missing_labels = array_map(
			static function ( $key ) use ( $pages ) {
				return isset( $pages[ $key ] ) ? $pages[ $key ]['label'] : $key;
			},
			array_keys( $left )
		);
		$footnote = 'Open SureCart and finish setting the shop up — the button above cannot create the '
			. implode( ' or the ', $missing_labels ) . '.';
	} elseif ( '' === $button ) {
		$footnote = 'Open SureCart and finish setting the shop up.';
	}

	return array(
		'lines'    => $lines,
		'button'   => $button,
		'footnote' => $footnote,
	);
}

/**
 * The notice markup. Pure, so the escaping is asserted in a test rather
 * than by eye.
 *
 * Plain WordPress core notice/button classes, not the BlueWorx design
 * system: this notice is printed by admin_notices on every wp-admin screen,
 * most of which never enqueue the design system's stylesheet, so its own
 * classes would render unstyled almost everywhere they are seen. The same
 * reasoning keeps the admin re-skin's topbar icons as hand-drawn SVG.
 *
 * @param array{lines:array<int,string>,button:string,footnote:string}|null $message    From blueworx_store_notice_message().
 * @param string                                                            $action_url Where the repair button submits to.
 * @return string
 */
function blueworx_store_notice_html( $message, $action_url ) {
	if ( null === $message ) {
		return '';
	}
	$html = '<div class="notice notice-warning"><p><strong>BlueWorx:</strong> your shop is not ready to take payments.</p><ul>';
	foreach ( $message['lines'] as $line ) {
		$html .= '<li>' . esc_html( $line ) . '</li>';
	}
	$html .= '</ul>';
	if ( '' !== $message['button'] ) {
		$html .= '<p><a class="button button-primary" href="' . esc_url( $action_url ) . '">'
			. esc_html( $message['button'] ) . '</a></p>';
	}
	if ( '' !== $message['footnote'] ) {
		$html .= '<p>' . esc_html( $message['footnote'] ) . '</p>';
	}
	return $html . '</div>';
}

/**
 * Prints the notice, when there is one to print and this owner can act on
 * it.
 *
 * Bails before reading anything else on a site with no shop: the status
 * would read no-shop, which is not a problem, but a club that never
 * installed SureCart must never be nagged about one.
 *
 * @return void
 */
function blueworx_store_render_notice() {
	if ( ! blueworx_store_surecart_active() ) {
		return;
	}
	if ( ! blueworx_store_can_manage() ) {
		return;
	}
	$pages    = blueworx_store_pages();
	$problems = blueworx_store_problems( blueworx_store_page_statuses() );
	if ( array() === $problems ) {
		return;
	}
	echo blueworx_store_notice_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html escapes every dynamic part.
		blueworx_store_notice_message( $problems, $pages, blueworx_store_can_seed() ),
		blueworx_store_action_url()
	);
}

/**
 * Runs the repair when the owner presses the notice's button, then sends
 * them back where they came from.
 *
 * @return void
 */
function blueworx_store_handle_repair() {
	if ( ! blueworx_store_can_manage() ) {
		return;
	}
	check_admin_referer( 'blueworx_store_repair' );
	blueworx_store_repair();
	$back = wp_get_referer();
	wp_safe_redirect( false !== $back ? $back : admin_url() );
	exit;
}

/**
 * The repair button's target, nonced for blueworx_store_handle_repair() to
 * check.
 *
 * @return string
 */
function blueworx_store_action_url() {
	return wp_nonce_url( admin_url( 'admin-post.php?action=blueworx_store_repair' ), 'blueworx_store_repair' );
}

/**
 * Whether the current user may see the notice and press its button.
 *
 * @return bool
 */
function blueworx_store_can_manage() {
	return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
}

if ( blueworx_feature_enabled( 'store_pages' ) ) {
	add_action( 'admin_notices', 'blueworx_store_render_notice' );
	add_action( 'admin_post_blueworx_store_repair', 'blueworx_store_handle_repair' );
	// The thank-you page is made here rather than only on activation, because
	// the usual order is this plugin first and the shop afterwards.
	add_action( 'admin_init', 'blueworx_store_ensure_confirmation' );
}
