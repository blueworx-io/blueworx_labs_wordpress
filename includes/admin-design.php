<?php
/**
 * The shared BlueWorx admin design system, rendered from PHP.
 *
 * The system ships as React components plus one stylesheet. Our screens are
 * PHP-rendered, so what we can share is the stylesheet and the markup contract:
 * these functions emit exactly the class structure the components emit, and
 * nothing else. The stylesheet stays verbatim from the foundation — see
 * assets/blueworx-admin-design.css and the CI sync check that holds it there.
 *
 * The rule that matters: no new BlueWorx CSS gets written here. If a screen
 * needs something the system has no class for, it goes into the system first
 * and comes back with the next sync — otherwise we are maintaining a second
 * design system in a plugin, which is the thing this migration exists to end.
 *
 * Every helper returns escaped HTML rather than echoing, so callers can compose
 * and the escaping happens once, here, where the markup is known.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The tags these helpers emit, for wp_kses() at the point of output.
 *
 * wp_kses_post() is the usual choice but strips every form control — form,
 * input, button, select — which is most of a settings screen. This is that list
 * plus the controls, and nothing else: no script, no style, no event handlers.
 *
 * @return array Allowed HTML, in wp_kses() format.
 */
function blueworx_ds_allowed_html() {
	$global = array(
		'class'       => true,
		'id'          => true,
		'style'       => true,
		'title'       => true,
		'role'        => true,
		'tabindex'    => true,
		'hidden'      => true,
		'data-lucide' => true,
	);

	// Every aria-* and data-* attribute our screens use, spelled out: wp_kses()
	// has no wildcard, and a missing one is silently dropped rather than warned
	// about — which is how an accessible control quietly stops being one.
	$aria = array(
		'aria-hidden'      => true,
		'aria-label'       => true,
		'aria-labelledby'  => true,
		'aria-describedby' => true,
		'aria-expanded'    => true,
		'aria-controls'    => true,
		'aria-current'     => true,
		'aria-selected'    => true,
		'aria-disabled'    => true,
		'aria-live'        => true,
	);

	$common = array_merge( $global, $aria );

	return array(
		'div'      => $common,
		'section'  => $common,
		'header'   => $common,
		'nav'      => $common,
		'span'     => $common,
		'p'        => $common,
		'small'    => $common,
		'strong'   => $common,
		'em'       => $common,
		'code'     => $common,
		'h1'       => $common,
		'h2'       => $common,
		'h3'       => $common,
		'h4'       => $common,
		'ul'       => $common,
		'ol'       => $common,
		'li'       => $common,
		'dl'       => $common,
		'dt'       => $common,
		'dd'       => $common,
		'table'    => $common,
		'thead'    => $common,
		'tbody'    => $common,
		'tr'       => $common,
		'th'       => array_merge( $common, array( 'scope' => true, 'colspan' => true ) ),
		'td'       => array_merge( $common, array( 'colspan' => true ) ),
		'a'        => array_merge( $common, array( 'href' => true, 'target' => true, 'rel' => true, 'download' => true ) ),
		'form'     => array_merge( $common, array( 'method' => true, 'action' => true, 'name' => true ) ),
		'label'    => array_merge( $common, array( 'for' => true ) ),
		'button'   => array_merge( $common, array( 'type' => true, 'name' => true, 'value' => true, 'disabled' => true ) ),
		'input'    => array_merge(
			$common,
			array(
				'type'        => true,
				'name'        => true,
				'value'       => true,
				'checked'     => true,
				'disabled'    => true,
				'readonly'    => true,
				'required'    => true,
				'placeholder' => true,
				'min'         => true,
				'max'         => true,
				'step'        => true,
				'size'        => true,
				'autocomplete' => true,
			)
		),
		'select'   => array_merge( $common, array( 'name' => true, 'multiple' => true, 'disabled' => true ) ),
		'option'   => array_merge( $common, array( 'value' => true, 'selected' => true ) ),
		'optgroup' => array_merge( $common, array( 'label' => true ) ),
		'textarea' => array_merge( $common, array( 'name' => true, 'rows' => true, 'cols' => true, 'placeholder' => true, 'readonly' => true ) ),
		'fieldset' => $common,
		'legend'   => $common,
	);
}

/**
 * Renders an icon placeholder.
 *
 * The design system's icon module upgrades any [data-lucide] element into inline
 * SVG on load. Deliberately empty until then: an icon that fails to draw should
 * leave a gap, never a stray glyph or a broken-image frame.
 *
 * @param string $name Lucide icon name, e.g. "circle-check".
 * @param int    $size Pixel size.
 * @return string HTML.
 */
function blueworx_ds_icon( $name, $size = 16 ) {
	return sprintf(
		'<span class="bw-icon" data-lucide="%1$s" aria-hidden="true" style="width:%2$dpx;height:%2$dpx"></span>',
		esc_attr( $name ),
		(int) $size
	);
}

/**
 * Renders a button, or a link that looks like one.
 *
 * @param array $args {
 *     @type string $label    Button text.
 *     @type string $variant  primary|secondary|ghost|danger|link. Default secondary.
 *     @type string $size     md|sm. Default md.
 *     @type string $icon     Optional leading Lucide icon.
 *     @type string $href     Renders an anchor instead of a button.
 *     @type string $type     Button type. Default button.
 *     @type bool   $disabled Whether the control is disabled.
 *     @type array  $attrs    Extra HTML attributes, name => value.
 * }
 * @return string HTML.
 */
function blueworx_ds_button( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'label'    => '',
			'variant'  => 'secondary',
			'size'     => 'md',
			'icon'     => '',
			'href'     => '',
			'type'     => 'button',
			'disabled' => false,
			'attrs'    => array(),
		)
	);

	$classes = array( 'bw-btn', 'bw-btn--' . $args['variant'] );

	if ( 'md' !== $args['size'] ) {
		$classes[] = 'bw-btn--' . $args['size'];
	}

	$inner = '';

	if ( '' !== $args['icon'] ) {
		$inner .= blueworx_ds_icon( $args['icon'], 'sm' === $args['size'] ? 14 : 16 );
	}

	$inner .= esc_html( $args['label'] );

	$attrs = blueworx_ds_attrs( $args['attrs'] );

	if ( '' !== $args['href'] ) {
		return sprintf(
			'<a class="%1$s" href="%2$s"%3$s%4$s>%5$s</a>',
			esc_attr( implode( ' ', $classes ) ),
			esc_url( $args['href'] ),
			$args['disabled'] ? ' aria-disabled="true"' : '',
			$attrs,
			$inner
		);
	}

	return sprintf(
		'<button class="%1$s" type="%2$s"%3$s%4$s>%5$s</button>',
		esc_attr( implode( ' ', $classes ) ),
		esc_attr( $args['type'] ),
		$args['disabled'] ? ' disabled' : '',
		$attrs,
		$inner
	);
}

/**
 * Renders a badge.
 *
 * @param string $label Badge text.
 * @param string $tone  neutral|success|warning|danger|accent|info.
 * @param bool   $dot   Whether to show the status dot.
 * @return string HTML.
 */
function blueworx_ds_badge( $label, $tone = 'neutral', $dot = false ) {
	return sprintf(
		'<span class="bw-badge bw-badge--%1$s">%2$s%3$s</span>',
		esc_attr( $tone ),
		$dot ? '<span class="bw-badge__dot"></span>' : '',
		esc_html( $label )
	);
}

/**
 * Renders a screen-level message.
 *
 * @param array $args {
 *     @type string $tone    info|success|warning|danger|accent. Default info.
 *     @type string $title   Optional bold first line.
 *     @type string $text    Body text.
 *     @type string $html    Body HTML, used instead of $text when set. Callers
 *                           are responsible for escaping what they pass.
 *     @type string $actions Action HTML.
 * }
 * @return string HTML.
 */
function blueworx_ds_notice( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'tone'    => 'info',
			'title'   => '',
			'text'    => '',
			'html'    => '',
			'actions' => '',
		)
	);

	$icons = array(
		'info'    => 'info',
		'success' => 'circle-check',
		'warning' => 'triangle-alert',
		'danger'  => 'circle-alert',
		'accent'  => 'lightbulb',
	);

	$icon = isset( $icons[ $args['tone'] ] ) ? $icons[ $args['tone'] ] : 'info';
	$body = '';

	if ( '' !== $args['title'] ) {
		$body .= '<p class="bw-notice__title">' . esc_html( $args['title'] ) . '</p>';
	}

	if ( '' !== $args['html'] ) {
		$body .= '<p class="bw-notice__text">' . $args['html'] . '</p>';
	} elseif ( '' !== $args['text'] ) {
		$body .= '<p class="bw-notice__text">' . esc_html( $args['text'] ) . '</p>';
	}

	if ( '' !== $args['actions'] ) {
		$body .= '<div class="bw-notice__actions">' . $args['actions'] . '</div>';
	}

	return sprintf(
		'<div class="bw-notice bw-notice--%1$s" role="%2$s">%3$s<div class="bw-notice__body">%4$s</div></div>',
		esc_attr( $args['tone'] ),
		'danger' === $args['tone'] ? 'alert' : 'status',
		blueworx_ds_icon( $icon, 18 ),
		$body
	);
}

/**
 * Renders the page header every BlueWorx screen opens with.
 *
 * @param array $args {
 *     @type string $eyebrow Small brand line above the title.
 *     @type string $title   Screen title.
 *     @type string $lede    One-line explanation.
 *     @type string $actions Action HTML, bottom right.
 * }
 * @return string HTML.
 */
function blueworx_ds_page_header( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'eyebrow' => 'BlueWorx',
			'title'   => '',
			'lede'    => '',
			'actions' => '',
		)
	);

	$titles = '';

	if ( '' !== $args['eyebrow'] ) {
		$titles .= '<p class="bw-pagehead__eyebrow">' . esc_html( $args['eyebrow'] ) . '</p>';
	}

	$titles .= '<h1 class="bw-pagehead__h1">' . esc_html( $args['title'] ) . '</h1>';

	if ( '' !== $args['lede'] ) {
		$titles .= '<p class="bw-pagehead__lede">' . esc_html( $args['lede'] ) . '</p>';
	}

	$actions = '' !== $args['actions']
		? '<div class="bw-pagehead__actions">' . $args['actions'] . '</div>'
		: '';

	return '<header class="bw-pagehead"><div class="bw-pagehead__titles">' . $titles . '</div>' . $actions . '</header>';
}

/**
 * Renders a card.
 *
 * @param array $args {
 *     @type string $eyebrow Optional small line above the title.
 *     @type string $title   Card title.
 *     @type string $note    Optional first line of the body.
 *     @type string $body    Body HTML. Callers escape their own content.
 *     @type string $actions Head actions HTML.
 *     @type string $footer  Footer HTML.
 *     @type bool   $flush   Whether the body drops its padding (tables).
 * }
 * @return string HTML.
 */
function blueworx_ds_card( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'eyebrow' => '',
			'title'   => '',
			'note'    => '',
			'body'    => '',
			'actions' => '',
			'footer'  => '',
			'flush'   => false,
		)
	);

	$head = '';

	if ( '' !== $args['title'] || '' !== $args['eyebrow'] || '' !== $args['actions'] ) {
		$titles = '';

		if ( '' !== $args['eyebrow'] ) {
			$titles .= '<p class="bw-card__eyebrow">' . esc_html( $args['eyebrow'] ) . '</p>';
		}

		if ( '' !== $args['title'] ) {
			$titles .= '<h2 class="bw-card__title">' . esc_html( $args['title'] ) . '</h2>';
		}

		$head  = '<div class="bw-card__head"><div class="bw-card__titles">' . $titles . '</div>';
		$head .= '' !== $args['actions'] ? '<div class="bw-card__actions">' . $args['actions'] . '</div>' : '';
		$head .= '</div>';
	}

	$body = '';

	if ( '' !== $args['note'] || '' !== $args['body'] ) {
		$note  = '' !== $args['note'] ? '<p class="bw-card__note">' . esc_html( $args['note'] ) . '</p>' : '';
		$body  = '<div class="bw-card__body">' . $note . $args['body'] . '</div>';
	}

	$footer = '' !== $args['footer'] ? '<div class="bw-card__foot">' . $args['footer'] . '</div>' : '';

	return sprintf(
		'<section class="bw-card%1$s">%2$s%3$s%4$s</section>',
		$args['flush'] ? ' bw-card--flush' : '',
		$head,
		$body,
		$footer
	);
}

/**
 * Renders a description list.
 *
 * @param array $rows Term => value HTML. Values are not escaped, so callers can
 *                    pass badges and copy fields; escape plain text yourself.
 * @return string HTML.
 */
function blueworx_ds_description_list( $rows ) {
	$html = '';

	foreach ( $rows as $term => $value ) {
		$html .= '<dt>' . esc_html( $term ) . '</dt><dd>' . $value . '</dd>';
	}

	return '<dl class="bw-dl">' . $html . '</dl>';
}

/**
 * Renders the "nothing here" state.
 *
 * @param array $args {
 *     @type string $icon    Lucide icon name.
 *     @type string $title   Heading.
 *     @type string $text    Explanation.
 *     @type string $actions Action HTML.
 * }
 * @return string HTML.
 */
function blueworx_ds_empty_state( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'icon'    => 'archive',
			'title'   => '',
			'text'    => '',
			'actions' => '',
		)
	);

	$html  = '<div class="bw-empty">' . blueworx_ds_icon( $args['icon'], 28 );
	$html .= '<h3 class="bw-empty__title">' . esc_html( $args['title'] ) . '</h3>';
	$html .= '' !== $args['text'] ? '<p class="bw-empty__text">' . esc_html( $args['text'] ) . '</p>' : '';
	$html .= '' !== $args['actions'] ? '<div class="bw-empty__actions">' . $args['actions'] . '</div>' : '';

	return $html . '</div>';
}

/**
 * Builds an attribute string.
 *
 * @param array $attrs Attribute name => value. A true value renders the name
 *                     alone; false and null drop the attribute entirely.
 * @return string Attribute string, with a leading space when non-empty.
 */
function blueworx_ds_attrs( $attrs ) {
	$html = '';

	foreach ( (array) $attrs as $name => $value ) {
		if ( false === $value || null === $value ) {
			continue;
		}

		if ( true === $value ) {
			$html .= ' ' . esc_attr( $name );
			continue;
		}

		$html .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
	}

	return $html;
}

/**
 * Opens a BlueWorx screen.
 *
 * `.bw-admin` is the opt-in wrapper: the design system's base type and colour
 * apply inside it and nowhere else, which is what keeps it off WordPress's own
 * screens. `.wrap` stays so core keeps placing its own admin notices where
 * people expect them.
 *
 * @param string $header_html Page header HTML.
 * @return string HTML.
 */
function blueworx_ds_screen_open( $header_html ) {
	return '<div class="wrap bw-admin bw-page">' . $header_html;
}

/**
 * Closes a BlueWorx screen.
 *
 * @return string HTML.
 */
function blueworx_ds_screen_close() {
	return '</div>';
}
