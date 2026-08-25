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
		'class'    => true,
		'id'       => true,
		'style'    => true,
		'title'    => true,
		'role'     => true,
		'tabindex' => true,
		'hidden'   => true,

		// Our own hooks. wp_kses() has no wildcard, so every data attribute the
		// screens rely on is listed here or it is silently dropped — which looks
		// exactly like markup that was never rendered.
		'data-lucide'                => true,
		'data-blueworx-feature'      => true,
		'data-blueworx-detail'       => true,
		'data-blueworx-guide'        => true,
		'data-blueworx-guide-tab'    => true,
		'data-blueworx-guide-panel'  => true,
		'data-blueworx-roles'        => true,
		'data-blueworx-roles-more'   => true,
		'data-blueworx-role-extra'   => true,
		'data-blueworx-guide-tabs'   => true,
		'data-more-label'            => true,
		'data-fewer-label'           => true,
		'data-testid'                => true,
		'data-blueworx-section'      => true,
		'data-blueworx-panel'        => true,

		// Edit Menu rows. The reorder script reads both, and a row whose
		// data-slug was dropped saves as though it were never in the list.
		'data-group'                 => true,
		'data-slug'                  => true,
		'draggable'                  => true,
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
		'br'       => array(),
		'hr'       => $common,

		// The feature detail panels render their own disclosure widgets and
		// inline SVG. Both are ours, both are static markup, and both vanish
		// without a trace if they are not listed here.
		'details'  => array_merge( $common, array( 'open' => true ) ),
		'summary'  => $common,
		'svg'      => array_merge(
			$common,
			array(
				'xmlns'             => true,
				'width'             => true,
				'height'            => true,
				'viewbox'           => true,
				'fill'              => true,
				'stroke'            => true,
				'stroke-width'      => true,
				'stroke-linecap'    => true,
				'stroke-linejoin'   => true,
				'focusable'         => true,
			)
		),
		'path'     => array(
			'd'               => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
		),
		'circle'   => array(
			'cx'           => true,
			'cy'           => true,
			'r'            => true,
			'fill'         => true,
			'stroke'       => true,
			'stroke-width' => true,
		),
	);
}

/**
 * Renders an icon placeholder.
 *
 * The design system's icon module upgrades any [data-lucide] element into inline
 * SVG on load. Deliberately empty until then: an icon that fails to draw should
 * leave a gap, never a stray glyph or a broken-image frame.
 *
 * The size comes from the system's own scale rather than an inline width and
 * height. A size that is not on that scale falls back to the base 16px, which
 * is the only honest answer: inventing a width here would put a value on the
 * page that the design system does not have a token for.
 *
 * @param string $name  Lucide icon name, e.g. "circle-check".
 * @param int    $size  Pixel size. One of 14, 16, 18, 20, 22, 28.
 * @param string $class Extra class for a component that positions its own icon.
 *                      Several in the stylesheet do — a select's chevron and an
 *                      empty state's glyph are each styled by a class of their
 *                      own, and without it they render unstyled while looking,
 *                      in the markup, entirely correct.
 * @return string HTML.
 */
function blueworx_ds_icon( $name, $size = 16, $class = '' ) {
	$classes = 'bw-icon';

	if ( in_array( (int) $size, array( 14, 18, 20, 22, 28 ), true ) ) {
		$classes .= ' bw-icon--' . (int) $size;
	}

	if ( '' !== $class ) {
		$classes .= ' ' . $class;
	}

	return sprintf(
		'<span class="%1$s" data-lucide="%2$s" aria-hidden="true"></span>',
		esc_attr( $classes ),
		esc_attr( $name )
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
 *     @type string $class    Extra class, for a component that positions its
 *                            own button — never for restyling one.
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
			'class'    => '',
			'attrs'    => array(),
		)
	);

	$classes = array( 'bw-btn', 'bw-btn--' . $args['variant'] );

	if ( 'md' !== $args['size'] ) {
		$classes[] = 'bw-btn--' . $args['size'];
	}

	if ( '' !== $args['class'] ) {
		$classes[] = $args['class'];
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
 * Renders an icon-only button.
 *
 * The label is not optional: an icon-only control with no accessible name is
 * an unlabelled button to anyone not looking at it.
 *
 * @param array $args {
 *     @type string $icon    Lucide icon name.
 *     @type string $label   Accessible name, also the tooltip.
 *     @type string $variant ghost|outline|danger. Default ghost.
 *     @type string $size    md|sm. Default md.
 *     @type string $type    Button type. Default button.
 *     @type string $class   Extra class, for a screen that needs its own hook.
 *     @type array  $attrs   Extra HTML attributes, name => value.
 * }
 * @return string HTML.
 */
function blueworx_ds_icon_button( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'icon'    => '',
			'label'   => '',
			'variant' => 'ghost',
			'size'    => 'md',
			'type'    => 'button',
			'class'   => '',
			'attrs'   => array(),
		)
	);

	$classes = array( 'bw-iconbtn', 'bw-iconbtn--' . $args['variant'] );

	if ( 'sm' === $args['size'] ) {
		$classes[] = 'bw-iconbtn--sm';
	}

	if ( '' !== $args['class'] ) {
		$classes[] = $args['class'];
	}

	return sprintf(
		'<button class="%1$s" type="%2$s" title="%3$s" aria-label="%3$s"%4$s>%5$s</button>',
		esc_attr( implode( ' ', $classes ) ),
		esc_attr( $args['type'] ),
		esc_attr( $args['label'] ),
		blueworx_ds_attrs( $args['attrs'] ),
		blueworx_ds_icon( $args['icon'], 'sm' === $args['size'] ? 14 : 18 )
	);
}

/**
 * Renders a badge.
 *
 * A badge takes either a dot or a leading icon, never both: they occupy the
 * same slot and say the same kind of thing. The dot wins if both are passed.
 *
 * @param string $label Badge text.
 * @param string $tone  neutral|success|warning|danger|accent|info.
 * @param bool   $dot   Whether to show the status dot.
 * @param string $icon  Optional leading Lucide icon name.
 * @return string HTML.
 */
function blueworx_ds_badge( $label, $tone = 'neutral', $dot = false, $icon = '' ) {
	$lead = '';

	if ( $dot ) {
		$lead = '<span class="bw-badge__dot"></span>';
	} elseif ( '' !== $icon ) {
		$lead = blueworx_ds_icon( $icon, 14 );
	}

	return sprintf(
		'<span class="bw-badge bw-badge--%1$s">%2$s%3$s</span>',
		esc_attr( $tone ),
		$lead,
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
			'eyebrow'    => 'BlueWorx',
			'title'      => '',
			'lede'       => '',
			'actions'    => '',
			'capability' => '',
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

	// Who can reach this screen, worked out from the capability it is registered
	// with rather than written down. "Only administrators can see this" is a
	// question people ask about every settings screen, and answering it in the
	// header is cheaper than answering it in support.
	$access = '';

	if ( '' !== $args['capability'] && function_exists( 'blueworx_roles_with_capability' ) ) {
		$roles = blueworx_roles_with_capability( $args['capability'] );

		if ( ! empty( $roles ) ) {
			$access = sprintf(
				'<div class="bw-pageaccess"><span class="bw-pageaccess__label">%1$s</span>%2$s</div>',
				esc_html__( 'Page access:', 'blueworx-labs-wordpress' ),
				blueworx_ds_role_pills( $roles, 'page:' . sanitize_key( $args['title'] ) )
			);
		}
	}

	$actions = '' !== $args['actions']
		? '<div class="bw-pagehead__actions">' . $args['actions'] . '</div>'
		: '';

	return '<header class="bw-pagehead"><div class="bw-pagehead__titles">' . $titles . '</div>' . $access . $actions . '</header>';
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
 *     @type string $class   Extra class, for a screen that needs its own hook
 *                           on the card — never for restyling one.
 *     @type array  $attrs   Extra attributes on the section element.
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
			'class'   => '',
			'attrs'   => array(),
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
		'<section class="bw-card%1$s"%2$s>%3$s%4$s%5$s</section>',
		( $args['flush'] ? ' bw-card--flush' : '' ) . ( '' !== $args['class'] ? ' ' . $args['class'] : '' ),
		blueworx_ds_attrs( $args['attrs'] ),
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

	$html  = '<div class="bw-empty">' . blueworx_ds_icon( $args['icon'], 28, 'bw-empty__icon' );
	$html .= '<h3 class="bw-empty__title">' . esc_html( $args['title'] ) . '</h3>';
	$html .= '' !== $args['text'] ? '<p class="bw-empty__text">' . esc_html( $args['text'] ) . '</p>' : '';
	$html .= '' !== $args['actions'] ? '<div class="bw-empty__actions">' . $args['actions'] . '</div>' : '';

	return $html . '</div>';
}

/**
 * Renders a read-only value with a copy button beside it.
 *
 * The value sits in a real <input> rather than a <code> block so it can be
 * selected with the keyboard, and so the copy still works where
 * navigator.clipboard does not exist — which is every site served over plain
 * HTTP. assets/js/copy-field.js binds any button carrying data-blueworx-copy
 * to the field whose id it names.
 *
 * @param array $args {
 *     @type string $value Value to show.
 *     @type string $id    Field id, and the handle the button copies by.
 *     @type string $label Copy button label.
 *     @type string $done  Button label while the copy is fresh.
 *     @type bool   $mono  Whether to render the value in the mono face.
 *     @type array  $attrs Extra attributes for the input.
 * }
 * @return string HTML.
 */
function blueworx_ds_copy_field( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'value' => '',
			'id'    => 'bw-copy-field',
			'label' => __( 'Copy', 'blueworx-labs-wordpress' ),
			'done'  => __( 'Copied', 'blueworx-labs-wordpress' ),
			'mono'  => true,
			'attrs' => array(),
		)
	);

	$input = sprintf(
		'<input type="text" class="%1$s" id="%2$s" value="%3$s" readonly%4$s />',
		esc_attr( 'bw-input' . ( $args['mono'] ? ' bw-input--mono' : '' ) ),
		esc_attr( $args['id'] ),
		esc_attr( $args['value'] ),
		blueworx_ds_attrs( $args['attrs'] )
	);

	$button = blueworx_ds_button(
		array(
			'label' => $args['label'],
			'icon'  => 'file',
			'class' => 'bw-copyfield__btn',
			'attrs' => array(
				'data-blueworx-copy' => $args['id'],
				'data-copied-label'  => $args['done'],
			),
		)
	);

	return '<div class="bw-copyfield">' . $input . $button . '</div>';
}

/**
 * Renders a checkbox.
 *
 * The design system's checkbox is a <label> wrapping its input rather than a
 * `for` pair, so callers pass the label text, not an id to point at.
 *
 * @param array $args {
 *     @type string $name    Field name.
 *     @type string $label   Visible label text.
 *     @type string $value   Submitted value.
 *     @type bool   $checked Whether it starts ticked.
 *     @type string $help    Optional second line under the label.
 *     @type array  $attrs   Extra attributes on the input.
 * }
 * @return string HTML.
 */
function blueworx_ds_checkbox( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'name'    => '',
			'label'   => '',
			'value'   => '1',
			'checked' => false,
			'help'    => '',
			'attrs'   => array(),
		)
	);

	$text = '<span>' . esc_html( $args['label'] ) . '</span>';

	if ( '' !== $args['help'] ) {
		$text .= '<span class="bw-check__help">' . esc_html( $args['help'] ) . '</span>';
	}

	return sprintf(
		'<label class="bw-check"><input type="checkbox" name="%1$s" value="%2$s"%3$s%4$s /><span class="bw-check__text">%5$s</span></label>',
		esc_attr( $args['name'] ),
		esc_attr( $args['value'] ),
		checked( (bool) $args['checked'], true, false ),
		blueworx_ds_attrs( $args['attrs'] ),
		$text
	);
}

/**
 * Renders a radio button.
 *
 * Shares the checkbox's markup on purpose — the design system draws both from
 * one class and lets the input type decide the shape.
 *
 * @param array $args {
 *     @type string $name    Field name, shared across the group.
 *     @type string $label   Visible label text.
 *     @type string $value   Submitted value.
 *     @type bool   $checked Whether it starts selected.
 *     @type string $help    Optional second line under the label.
 *     @type array  $attrs   Extra attributes on the input.
 * }
 * @return string HTML.
 */
function blueworx_ds_radio( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'name'    => '',
			'label'   => '',
			'value'   => '',
			'checked' => false,
			'help'    => '',
			'attrs'   => array(),
		)
	);

	$text = '<span>' . esc_html( $args['label'] ) . '</span>';

	if ( '' !== $args['help'] ) {
		$text .= '<span class="bw-check__help">' . esc_html( $args['help'] ) . '</span>';
	}

	return sprintf(
		'<label class="bw-check"><input type="radio" name="%1$s" value="%2$s"%3$s%4$s /><span class="bw-check__text">%5$s</span></label>',
		esc_attr( $args['name'] ),
		esc_attr( $args['value'] ),
		checked( (bool) $args['checked'], true, false ),
		blueworx_ds_attrs( $args['attrs'] ),
		$text
	);
}

/**
 * Renders a set of radio buttons as one labelled choice.
 *
 * @param array $args {
 *     @type string $name     Field name.
 *     @type string $legend   Heading for the group.
 *     @type array  $choices  Value => label.
 *     @type string $selected Selected value.
 *     @type string $help     Optional sentence under the group.
 *     @type string $id       Base id, used to tie the group to its heading.
 *     @type string $extra    Extra HTML placed inside the group, after the
 *                            options — a role picker a choice depends on, say.
 * }
 * @return string HTML.
 */
function blueworx_ds_radio_group( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'name'     => '',
			'legend'   => '',
			'choices'  => array(),
			'selected' => '',
			'help'     => '',
			'id'       => '',
			'extra'    => '',
		)
	);

	$id      = '' !== $args['id'] ? $args['id'] : 'bw-group-' . sanitize_key( $args['name'] );
	$buttons = '';

	foreach ( (array) $args['choices'] as $value => $label ) {
		$buttons .= blueworx_ds_radio(
			array(
				'name'    => $args['name'],
				'value'   => (string) $value,
				'label'   => (string) $label,
				'checked' => (string) $value === (string) $args['selected'],
			)
		);
	}

	$help = '' !== $args['help'] ? '<p class="bw-field__help">' . esc_html( $args['help'] ) . '</p>' : '';

	return sprintf(
		'<div class="bw-field"><span class="bw-field__label" id="%1$s">%2$s</span><div class="bw-radiogroup" role="radiogroup" aria-labelledby="%1$s">%3$s</div>%4$s%5$s</div>',
		esc_attr( $id . '-label' ),
		esc_html( $args['legend'] ),
		$buttons,
		$args['extra'],
		$help
	);
}

/**
 * Renders a select.
 *
 * @param array $args {
 *     @type string $name     Field name.
 *     @type string $id       Element id, for a label to point at.
 *     @type array  $options  Value => label.
 *     @type string $selected Selected value.
 *     @type array  $attrs    Extra attributes on the select.
 * }
 * @return string HTML.
 */
function blueworx_ds_select( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'name'     => '',
			'id'       => '',
			'options'  => array(),
			'selected' => '',
			'attrs'    => array(),
		)
	);

	$options = '';

	foreach ( (array) $args['options'] as $value => $label ) {
		$options .= sprintf(
			'<option value="%1$s"%2$s>%3$s</option>',
			esc_attr( (string) $value ),
			selected( (string) $value, (string) $args['selected'], false ),
			esc_html( (string) $label )
		);
	}

	return sprintf(
		'<span class="bw-select"><select class="bw-select__el" name="%1$s"%2$s%3$s>%4$s</select>%5$s</span>',
		esc_attr( $args['name'] ),
		'' !== $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : '',
		blueworx_ds_attrs( $args['attrs'] ),
		$options,
		blueworx_ds_icon( 'chevron-down', 14, 'bw-select__arrow' )
	);
}

/**
 * Renders a text-shaped input.
 *
 * @param array $args {
 *     @type string $type  Input type.
 *     @type string $name  Field name.
 *     @type string $id    Element id.
 *     @type string $value Current value.
 *     @type bool   $mono  Whether to use the monospace variant.
 *     @type bool   $small Whether to use the short variant.
 *     @type array  $attrs Extra attributes, e.g. min, max, placeholder.
 * }
 * @return string HTML.
 */
function blueworx_ds_input( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'type'  => 'text',
			'name'  => '',
			'id'    => '',
			'value' => '',
			'mono'  => false,
			'small' => false,
			'attrs' => array(),
		)
	);

	$class  = 'bw-input';
	$class .= $args['mono'] ? ' bw-input--mono' : '';
	$class .= $args['small'] ? ' bw-input--sm' : '';

	return sprintf(
		'<input type="%1$s" class="%2$s" name="%3$s"%4$s value="%5$s"%6$s />',
		esc_attr( $args['type'] ),
		esc_attr( $class ),
		esc_attr( $args['name'] ),
		'' !== $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : '',
		esc_attr( (string) $args['value'] ),
		blueworx_ds_attrs( $args['attrs'] )
	);
}

/**
 * Renders a textarea.
 *
 * @param array $args {
 *     @type string $name  Field name.
 *     @type string $id    Element id.
 *     @type string $value Current value.
 *     @type int    $rows  Visible rows.
 *     @type bool   $mono  Whether to use the monospace variant.
 *     @type array  $attrs Extra attributes.
 * }
 * @return string HTML.
 */
function blueworx_ds_textarea( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'name'  => '',
			'id'    => '',
			'value' => '',
			'rows'  => 6,
			'mono'  => false,
			'attrs' => array(),
		)
	);

	return sprintf(
		'<textarea class="%1$s" name="%2$s"%3$s rows="%4$d"%5$s>%6$s</textarea>',
		esc_attr( 'bw-textarea' . ( $args['mono'] ? ' bw-textarea--mono' : '' ) ),
		esc_attr( $args['name'] ),
		'' !== $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : '',
		(int) $args['rows'],
		blueworx_ds_attrs( $args['attrs'] ),
		esc_textarea( (string) $args['value'] )
	);
}

/**
 * Wraps a control with its label and help text.
 *
 * @param array $args {
 *     @type string $label   Label text. An empty label renders no element.
 *     @type string $for     Id of the control the label points at.
 *     @type string $control Control HTML, already escaped by its own helper.
 *     @type string $help    Optional sentence under the control.
 *     @type bool   $wide    Whether the field spans both grid columns.
 * }
 * @return string HTML.
 */
function blueworx_ds_field( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'label'   => '',
			'for'     => '',
			'control' => '',
			'help'    => '',
			'wide'    => false,
		)
	);

	$label = '';

	if ( '' !== $args['label'] ) {
		$label = sprintf(
			'<label class="bw-field__label"%1$s>%2$s</label>',
			'' !== $args['for'] ? ' for="' . esc_attr( $args['for'] ) . '"' : '',
			esc_html( $args['label'] )
		);
	}

	$help = '' !== $args['help'] ? '<p class="bw-field__help">' . esc_html( $args['help'] ) . '</p>' : '';

	return sprintf(
		'<div class="bw-field%1$s">%2$s%3$s%4$s</div>',
		$args['wide'] ? ' bw-field--wide' : '',
		$label,
		$args['control'],
		$help
	);
}

/**
 * Renders a group of related checkboxes under one heading.
 *
 * A role picker, or any "tick the ones that apply" list. It replaces the native
 * multi-select these panels used to carry: a multi-select never hints that
 * ctrl-click is how a second option gets picked, and on a phone it collapses to
 * a control most people cannot operate at all.
 *
 * `role="group"` with a pointed-at label rather than fieldset and legend: the
 * design system styles neither, so a real fieldset would arrive with a browser
 * border, and removing it means writing CSS this file is not allowed to write.
 *
 * @param array $args {
 *     @type string $name     Field name. Rendered with a [] suffix.
 *     @type string $legend   Heading for the group.
 *     @type array  $choices  Value => label.
 *     @type array  $selected Values that start ticked.
 *     @type string $help     Optional sentence under the group.
 *     @type string $id       Base id, used to tie the group to its heading.
 *     @type array  $attrs    Extra attributes on the wrapper.
 * }
 * @return string HTML.
 */
function blueworx_ds_choice_group( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'name'     => '',
			'legend'   => '',
			'choices'  => array(),
			'selected' => array(),
			'help'     => '',
			'id'       => '',
			'attrs'    => array(),
		)
	);

	$id       = '' !== $args['id'] ? $args['id'] : 'bw-group-' . sanitize_key( $args['name'] );
	$selected = array_map( 'strval', (array) $args['selected'] );
	$boxes    = '';

	foreach ( (array) $args['choices'] as $value => $label ) {
		$boxes .= blueworx_ds_checkbox(
			array(
				'name'    => $args['name'] . '[]',
				'value'   => (string) $value,
				'label'   => (string) $label,
				'checked' => in_array( (string) $value, $selected, true ),
			)
		);
	}

	$help = '' !== $args['help'] ? '<p class="bw-field__help">' . esc_html( $args['help'] ) . '</p>' : '';

	return sprintf(
		'<div class="bw-field" role="group" aria-labelledby="%1$s"%2$s><span class="bw-field__label" id="%1$s">%3$s</span><div class="bw-radiogroup">%4$s</div>%5$s</div>',
		esc_attr( $id . '-label' ),
		blueworx_ds_attrs( $args['attrs'] ),
		esc_html( $args['legend'] ),
		$boxes,
		$help
	);
}

/**
 * Renders a label-left, control-right settings row.
 *
 * The shape a long list of settings wants, and the one that stacks cleanly on a
 * phone. Use blueworx_ds_field() inside a .bw-fields grid for short two-column
 * forms instead.
 *
 * @param array $args {
 *     @type string $label   Label text.
 *     @type string $for     Id of the control the label points at.
 *     @type string $control Control HTML.
 *     @type string $help    Optional sentence under the control.
 * }
 * @return string HTML.
 */
function blueworx_ds_form_row( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'label'   => '',
			'for'     => '',
			'control' => '',
			'help'    => '',
		)
	);

	$label = sprintf(
		'<label class="bw-formrow__label"%1$s>%2$s</label>',
		'' !== $args['for'] ? ' for="' . esc_attr( $args['for'] ) . '"' : '',
		esc_html( $args['label'] )
	);

	$help = '' !== $args['help'] ? '<p class="bw-formrow__help">' . esc_html( $args['help'] ) . '</p>' : '';

	return sprintf(
		'<div class="bw-formrow">%1$s<div class="bw-formrow__control">%2$s%3$s</div></div>',
		$label,
		$args['control'],
		$help
	);
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
