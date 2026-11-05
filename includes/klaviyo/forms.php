<?php
/**
 * Klaviyo connection: the two website forms.
 *
 * "Stay up to date" is an Elementor form and the founding reservation form is a
 * SureForms one, so each has its own hook. The settings say which form is which.
 * Only people who submit these forms are sent: WordPress accounts made any other
 * way, and the answers on the founding sign-up page, stay out of Klaviyo.
 *
 * These run while somebody is waiting for a form to answer, so all they do is
 * queue the work, and nothing that goes wrong here may reach the visitor.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Picks an Elementor form's answers out by field ID or label.
 *
 * Going by either, so the form can be edited in Elementor without having to set
 * a particular field ID.
 *
 * @param array $fields The record's fields, keyed by field ID.
 * @return array Email, first_name, last_name and preferred_centre.
 */
function blueworx_klaviyo_elementor_values( $fields ) {
	$patterns = array(
		'email'            => '/e-?mail/i',
		'first_name'       => '/first.?name|^name$/i',
		'last_name'        => '/last.?name|surname/i',
		'preferred_centre' => '/centre|center/i',
	);

	$values = array_fill_keys( array_keys( $patterns ), '' );

	foreach ( (array) $fields as $id => $field ) {
		$haystacks = array( (string) $id, isset( $field['title'] ) ? (string) $field['title'] : '' );
		$value     = isset( $field['value'] ) ? trim( (string) $field['value'] ) : '';

		foreach ( $patterns as $key => $pattern ) {
			foreach ( $haystacks as $haystack ) {
				if ( '' === $values[ $key ] && '' !== $value && preg_match( $pattern, $haystack ) ) {
					$values[ $key ] = $value;
				}
			}
		}
	}

	return $values;
}

/**
 * Picks a SureForms form's answers out of its field names.
 *
 * A name looks like srfm-input-3b3cf3ac-lbl-Rmlyc3QgTmFtZQ-first-name, and only
 * the type at the front and the slug at the end mean anything.
 *
 * @param array $data The posted form data.
 * @return array Email, first_name, last_name and marketing.
 */
function blueworx_klaviyo_sureforms_values( $data ) {
	$values = array(
		'email'      => '',
		'first_name' => '',
		'last_name'  => '',
		'marketing'  => false,
	);

	foreach ( (array) $data as $name => $value ) {
		$name = (string) $name;

		if ( 0 !== strpos( $name, 'srfm-' ) || ! is_scalar( $value ) ) {
			continue;
		}

		$text = trim( (string) $value );

		if ( 0 === strpos( $name, 'srfm-email-' ) && '' === $values['email'] ) {
			$values['email'] = $text;
		} elseif ( preg_match( '/-first-?name$/', $name ) ) {
			$values['first_name'] = $text;
		} elseif ( preg_match( '/-last-?name$/', $name ) ) {
			$values['last_name'] = $text;
		} elseif ( preg_match( '/-(marketing[a-z0-9-]*|[a-z0-9-]*news[a-z0-9-]*)$/', $name ) ) {
			$values['marketing'] = blueworx_klaviyo_is_ticked( $text );
		}
	}

	return $values;
}

/**
 * Handles an Elementor form submission.
 *
 * @param object $record Elementor's form record.
 * @return void
 */
function blueworx_klaviyo_on_elementor_form( $record ) {
	try {
		if ( ! blueworx_feature_enabled( 'klaviyo' ) ) {
			return;
		}

		$wanted = blueworx_klaviyo_setting( 'info_form_name' );

		if ( '' === $wanted || 0 !== strcasecmp( $wanted, (string) $record->get_form_settings( 'form_name' ) ) ) {
			return;
		}

		$form = blueworx_klaviyo_elementor_values( $record->get( 'fields' ) );

		// Elementor has already checked its own nonce before this hook runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form['page'] = isset( $_POST['referrer'] ) ? esc_url_raw( wp_unslash( $_POST['referrer'] ) ) : (string) wp_get_referer();

		blueworx_klaviyo_queue_info_form( $form );
	} catch ( Throwable $thrown ) {
		blueworx_klaviyo_log( 'Stay up to date form', '', 'failed', $thrown->getMessage() );
	}
}
add_action( 'elementor_pro/forms/new_record', 'blueworx_klaviyo_on_elementor_form' );

/**
 * Queues a "Stay up to date" sign-up.
 *
 * @param array $form The form's answers.
 * @return void
 */
function blueworx_klaviyo_queue_info_form( $form ) {
	$form['email'] = sanitize_email( $form['email'] );

	if ( ! is_email( $form['email'] ) ) {
		return;
	}

	$plan = blueworx_klaviyo_plan_info_form(
		$form,
		array(
			'list'         => blueworx_klaviyo_setting( 'list_info' ),
			'consent_text' => blueworx_klaviyo_setting( 'consent_text' ),
		)
	);

	blueworx_klaviyo_enqueue(
		'info:' . strtolower( $form['email'] ) . ':' . microtime( true ),
		array(
			'label' => $plan['label'],
			'email' => $plan['email'],
			'plan'  => $plan,
		)
	);
}

/**
 * Handles a SureForms submission.
 *
 * On the accepted hook rather than the later submit one, so a slow mail server
 * cannot hold this up, and it sees the raw field names.
 *
 * @param int   $entry_id Entry ID.
 * @param int   $form_id  Form ID.
 * @param array $data     The posted form data.
 * @return void
 */
function blueworx_klaviyo_on_sureforms_submission( $entry_id, $form_id, $data ) {
	try {
		if ( ! blueworx_feature_enabled( 'klaviyo' ) || (string) $form_id !== blueworx_klaviyo_setting( 'reservation_form_id' ) ) {
			return;
		}

		$form          = blueworx_klaviyo_sureforms_values( $data );
		$form['email'] = sanitize_email( $form['email'] );

		if ( ! is_email( $form['email'] ) ) {
			return;
		}

		$form['first_name'] = sanitize_text_field( $form['first_name'] );
		$form['last_name']  = sanitize_text_field( $form['last_name'] );
		$form['date']       = wp_date( 'Y-m-d' );

		$plan = blueworx_klaviyo_plan_reservation_form( $form, array( 'list' => blueworx_klaviyo_setting( 'list_reservation' ) ) );

		blueworx_klaviyo_enqueue(
			'reservation:' . $form_id . ':' . $entry_id . ':' . strtolower( $form['email'] ),
			array(
				'label' => $plan['label'],
				'email' => $plan['email'],
				'plan'  => $plan,
			)
		);
	} catch ( Throwable $thrown ) {
		blueworx_klaviyo_log( 'Founding reservation form', '', 'failed', $thrown->getMessage() );
	}
}
add_action( 'srfm_submission_accepted', 'blueworx_klaviyo_on_sureforms_submission', 10, 3 );
