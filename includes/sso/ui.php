<?php
/**
 * Single sign-on: the sign-in button.
 *
 * The label is rendered server-side, so nothing has to correct it in JavaScript
 * after the page has loaded, and the icon is inline rather than an icon font, so
 * the button costs no extra request.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The padlock icon shown on the button.
 *
 * @return string Inline SVG.
 */
function blueworx_sso_icon_svg() {
	return '<svg class="blueworx-sso-button__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
		. '<rect x="3" y="11" width="18" height="11" rx="2" />'
		. '<path d="M7 11V7a5 5 0 0 1 10 0v4" />'
		. '</svg>';
}

/**
 * Builds the sign-in button.
 *
 * @param array $args {
 *     Optional.
 *
 *     @type string $intent      'login' (the default) or 'register'.
 *     @type string $label       Override the configured label.
 *     @type string $redirect_to Where to send the person afterwards.
 * }
 * @return string Button markup, or an empty string when there is nothing to show.
 */
function blueworx_sso_button_html( $args = array() ) {
	if ( ! blueworx_sso_enabled() || is_user_logged_in() ) {
		return '';
	}

	$intent = blueworx_sso_intent( isset( $args['intent'] ) ? $args['intent'] : 'login' );
	$label  = isset( $args['label'] ) ? trim( (string) $args['label'] ) : '';

	if ( '' === $label ) {
		$label = trim( (string) blueworx_sso_option( 'register' === $intent ? 'register_button_label' : 'button_label' ) );
	}

	if ( '' === $label ) {
		$label = 'register' === $intent
			? __( 'Join with single sign-on', 'blueworx-labs-wordpress' )
			: __( 'Sign in with single sign-on', 'blueworx-labs-wordpress' );
	}

	return sprintf(
		'<a class="blueworx-sso-button blueworx-sso-button--%4$s" href="%1$s">%2$s<span class="blueworx-sso-button__label">%3$s</span></a>',
		esc_url( blueworx_sso_login_url( isset( $args['redirect_to'] ) ? $args['redirect_to'] : '', $intent ) ),
		blueworx_sso_icon_svg(),
		esc_html( $label ),
		esc_attr( $intent )
	);
}

/**
 * Prints the button on the login form.
 *
 * @return void
 */
function blueworx_sso_render_login_button() {
	echo wp_kses( blueworx_sso_button_html(), blueworx_sso_button_allowed_html() );
}
add_action( 'login_form', 'blueworx_sso_render_login_button' );

/**
 * Renders the button anywhere on the site.
 *
 * @param array $atts Shortcode attributes: intent, label, redirect_to.
 * @return string Button markup.
 */
function blueworx_sso_button_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'intent'      => 'login',
			'label'       => '',
			'redirect_to' => '',
		),
		$atts,
		'blueworx_sso_button'
	);

	return wp_kses( blueworx_sso_button_html( $atts ), blueworx_sso_button_allowed_html() );
}
add_shortcode( 'blueworx_sso_button', 'blueworx_sso_button_shortcode' );

/**
 * The markup the button is allowed to produce.
 *
 * @return array Allowed tags for wp_kses().
 */
function blueworx_sso_button_allowed_html() {
	return array(
		'a'    => array(
			'class' => array(),
			'href'  => array(),
		),
		'span' => array( 'class' => array() ),
		'svg'  => array(
			'class'            => array(),
			'width'            => array(),
			'height'           => array(),
			'viewbox'          => array(),
			'fill'             => array(),
			'stroke'           => array(),
			'stroke-width'     => array(),
			'stroke-linecap'   => array(),
			'stroke-linejoin'  => array(),
			'aria-hidden'      => array(),
			'focusable'        => array(),
		),
		'rect' => array(
			'x'      => array(),
			'y'      => array(),
			'width'  => array(),
			'height' => array(),
			'rx'     => array(),
		),
		'path' => array( 'd' => array() ),
	);
}
