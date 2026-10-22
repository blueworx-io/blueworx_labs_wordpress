<?php
/**
 * Tracking codes: Google Tag Manager, Google Analytics and the Meta pixel.
 *
 * Off by default. Each service gives a site the same block of code to paste,
 * with only its ID changing, so this stores the ID and writes the code itself.
 * There is deliberately no box for pasting a script: whoever can reach this
 * screen would then be able to run any code on every page of the site, and an
 * ID that has been checked against its service's format can carry nothing but
 * an ID.
 *
 * Nothing here waits for cookie consent yet — that arrives with the consent
 * work in issue #185.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The services offered, in the order their code is written to the page.
 *
 * Tag Manager first: Google asks for it as high in the head as possible, and it
 * is the one most likely to be loading the others.
 *
 * @return array Service definitions keyed by service key.
 */
function blueworx_tracking_services() {
	return array(
		'gtm'  => array(
			'label'       => __( 'Google Tag Manager ID', 'blueworx-labs-wordpress' ),
			'pattern'     => '/^GTM-[A-Z0-9]{4,12}$/',
			'placeholder' => 'GTM-XXXXXXX',
			'help'        => __( 'Starts with GTM-. Found at the top of the container in Tag Manager.', 'blueworx-labs-wordpress' ),
		),
		'ga'   => array(
			'label'       => __( 'Google Analytics ID', 'blueworx-labs-wordpress' ),
			'pattern'     => '/^G-[A-Z0-9]{4,15}$/',
			'placeholder' => 'G-XXXXXXXXXX',
			'help'        => __( 'Starts with G-. Found under Admin, Data streams in Google Analytics. Leave it empty if Analytics is already set up inside Tag Manager.', 'blueworx-labs-wordpress' ),
		),
		'meta' => array(
			'label'       => __( 'Meta pixel ID', 'blueworx-labs-wordpress' ),
			'pattern'     => '/^[0-9]{10,20}$/',
			'placeholder' => '123456789012345',
			'help'        => __( 'A long number. Found in Meta Events Manager, under Data sources.', 'blueworx-labs-wordpress' ),
		),
	);
}

/**
 * Tidies up a typed ID and checks it against its service's format.
 *
 * Case and stray spaces are forgiven, since IDs get copied out of emails and
 * screenshots; anything else is refused rather than corrected.
 *
 * @param string $service Service key.
 * @param string $raw     What was typed.
 * @return string|false The ID, an empty string for an empty box, or false when refused.
 */
function blueworx_tracking_normalize_id( $service, $raw ) {
	$services = blueworx_tracking_services();

	if ( ! isset( $services[ $service ] ) ) {
		return false;
	}

	$id = strtoupper( trim( (string) $raw ) );

	if ( 'meta' === $service ) {
		$id = str_replace( ' ', '', $id );
	}

	if ( '' === $id ) {
		return '';
	}

	return preg_match( $services[ $service ]['pattern'], $id ) ? $id : false;
}

/**
 * Gets a service's saved ID.
 *
 * Checked again on the way out, so a value written to the database some other
 * way can never reach the page.
 *
 * @param string $service Service key.
 * @return string The ID, or an empty string when there is none.
 */
function blueworx_tracking_id( $service ) {
	$id = blueworx_tracking_normalize_id( $service, (string) get_option( 'blueworx_tracking_' . $service, '' ) );

	return false === $id ? '' : $id;
}

/**
 * Saves the IDs from the Tracking codes screen.
 *
 * The caller has already verified the nonce and the capability. A refused ID
 * keeps whatever was saved before, so one mistyped box cannot switch off
 * tracking that was working.
 *
 * @param array $posted Raw $_POST.
 * @return array Keys of the services whose ID was refused.
 */
function blueworx_tracking_save( $posted ) {
	$refused = array();

	foreach ( array_keys( blueworx_tracking_services() ) as $service ) {
		// Not sanitize_text_field(): it strips a pasted <script> block down to
		// nothing, and nothing reads as an emptied box, which would wipe a working
		// ID instead of refusing the paste. The format check is the sanitizing.
		$field = 'blueworx_tracking_' . $service;
		$raw   = isset( $posted[ $field ] ) ? (string) wp_unslash( $posted[ $field ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Checked against a strict pattern on the next line.
		$id    = blueworx_tracking_normalize_id( $service, $raw );

		if ( false === $id ) {
			$refused[] = $service;
			continue;
		}

		update_option( $field, $id );
	}

	return $refused;
}

/**
 * Whether tracking code belongs on this request.
 *
 * @return bool
 */
function blueworx_tracking_active() {
	return blueworx_feature_enabled( 'tracking_codes' ) && ! is_admin();
}

/**
 * Builds the code for the head of the page.
 *
 * Each block is the one its service publishes, with only the ID filled in.
 *
 * @return string HTML.
 */
function blueworx_tracking_head_html() {
	$html = '';
	$gtm  = blueworx_tracking_id( 'gtm' );
	$ga   = blueworx_tracking_id( 'ga' );
	$meta = blueworx_tracking_id( 'meta' );

	if ( '' !== $gtm ) {
		$html .= "<!-- Google Tag Manager -->\n"
			. "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n"
			. "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n"
			. "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n"
			. "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n"
			. "})(window,document,'script','dataLayer','" . esc_js( $gtm ) . "');</script>\n"
			. "<!-- End Google Tag Manager -->\n";
	}

	if ( '' !== $ga ) {
		$html .= "<!-- Google tag (gtag.js) -->\n"
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Google's published snippet, which must sit beside its config call rather than in the footer queue.
			. '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $ga ) . "\"></script>\n"
			. "<script>\n"
			. "  window.dataLayer = window.dataLayer || [];\n"
			. "  function gtag(){dataLayer.push(arguments);}\n"
			. "  gtag('js', new Date());\n\n"
			. "  gtag('config', '" . esc_js( $ga ) . "');\n"
			. "</script>\n";
	}

	if ( '' !== $meta ) {
		$html .= "<!-- Meta Pixel Code -->\n"
			. "<script>\n"
			. "!function(f,b,e,v,n,t,s)\n"
			. "{if(f.fbq)return;n=f.fbq=function(){n.callMethod?\n"
			. "n.callMethod.apply(n,arguments):n.queue.push(arguments)};\n"
			. "if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';\n"
			. "n.queue=[];t=b.createElement(e);t.async=!0;\n"
			. "t.src=v;s=b.getElementsByTagName(e)[0];\n"
			. "s.parentNode.insertBefore(t,s)}(window, document,'script',\n"
			. "'https://connect.facebook.net/en_US/fbevents.js');\n"
			. "fbq('init', '" . esc_js( $meta ) . "');\n"
			. "fbq('track', 'PageView');\n"
			. "</script>\n"
			. '<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=' . esc_attr( $meta ) . "&amp;ev=PageView&amp;noscript=1\" /></noscript>\n"
			. "<!-- End Meta Pixel Code -->\n";
	}

	return $html;
}

/**
 * Builds the code that goes straight after the body opens.
 *
 * Only Tag Manager has any: its fallback for visitors with scripts turned off.
 *
 * @return string HTML.
 */
function blueworx_tracking_body_html() {
	$gtm = blueworx_tracking_id( 'gtm' );

	if ( '' === $gtm ) {
		return '';
	}

	return "<!-- Google Tag Manager (noscript) -->\n"
		. '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr( $gtm ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n"
		. "<!-- End Google Tag Manager (noscript) -->\n";
}

/**
 * Prints the head code.
 *
 * @return void
 */
function blueworx_tracking_print_head() {
	if ( blueworx_tracking_active() ) {
		echo blueworx_tracking_head_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup; every ID in it is format-checked and escaped where it is built.
	}
}

/**
 * Prints the code that goes straight after the body opens.
 *
 * @return void
 */
function blueworx_tracking_print_body() {
	if ( blueworx_tracking_active() ) {
		echo blueworx_tracking_body_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup; the ID in it is format-checked and escaped where it is built.
	}
}

// Ahead of everything at the default priorities, title tag included, which is
// as high in the head as a plugin can reach without rewriting the theme's page.
// The body fallback goes below zero because core prints the admin bar there at
// zero, and Google asks for the fallback immediately after the body opens.
add_action( 'wp_head', 'blueworx_tracking_print_head', 0 );
add_action( 'wp_body_open', 'blueworx_tracking_print_body', -10 );

/**
 * Clears cached pages, so a change shows to visitors straight away rather
 * than whenever the cache next expires.
 *
 * @return void
 */
function blueworx_tracking_refresh_cache() {
	if ( function_exists( 'blueworx_purge_site_cache' ) ) {
		blueworx_purge_site_cache( 'tracking codes' );
	}
}
add_action( 'update_option_blueworx_feature_tracking_codes', 'blueworx_tracking_refresh_cache' );

/**
 * Saves the Tracking codes screen.
 *
 * @return void
 */
function blueworx_tracking_handle_save() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_save_tracking_codes' );

	$refused = blueworx_tracking_save( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() ran above; the callee sanitizes every field.

	blueworx_tracking_refresh_cache();

	set_transient( 'blueworx_tracking_notice', $refused, 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-tracking' ) );
	exit;
}
add_action( 'admin_post_blueworx_save_tracking_codes', 'blueworx_tracking_handle_save' );

/**
 * Renders the Tracking codes screen.
 *
 * @return void
 */
function blueworx_render_tracking_page() {
	$enabled  = blueworx_feature_enabled( 'tracking_codes' );
	$services = blueworx_tracking_services();
	$live     = array_filter( array_map( 'blueworx_tracking_id', array_combine( array_keys( $services ), array_keys( $services ) ) ) );

	$form = $enabled
		? sprintf(
			'<form method="post" action="%1$s"><input type="hidden" name="action" value="blueworx_save_tracking_codes" />%2$s',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'blueworx_save_tracking_codes', '_wpnonce', true, false )
		)
		: '';

	blueworx_open_admin_page(
		array(
			'title'   => __( 'Tracking codes', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'Adds Google Tag Manager, Google Analytics and the Meta pixel to every page of the site. Enter an ID and the plugin writes the code.', 'blueworx-labs-wordpress' ),
			'actions' => blueworx_ds_badge(
				$enabled && $live ? __( 'Live', 'blueworx-labs-wordpress' ) : __( 'Not live', 'blueworx-labs-wordpress' ),
				$enabled && $live ? 'success' : 'neutral',
				true
			),
			'form'    => $form,
		)
	);

	if ( ! $enabled ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone'    => 'info',
					'title'   => __( 'Tracking codes are switched off', 'blueworx-labs-wordpress' ),
					'text'    => __( 'No tracking code is added to the site while this is off, and this page is not listed in the menu. Switch it on under Enhancements to set it up.', 'blueworx-labs-wordpress' ),
					'actions' => blueworx_ds_button(
						array(
							'label' => __( 'Go to Enhancements', 'blueworx-labs-wordpress' ),
							'icon'  => 'arrow-right',
							'href'  => admin_url( 'admin.php?page=blueworx-labs-wordpress&section=tracking' ),
						)
					),
				)
			),
			blueworx_ds_allowed_html()
		);

		blueworx_close_admin_page();
		return;
	}

	$refused = get_transient( 'blueworx_tracking_notice' );

	if ( false !== $refused ) {
		delete_transient( 'blueworx_tracking_notice' );

		$refused = array_intersect_key( $services, array_flip( (array) $refused ) );

		echo wp_kses(
			blueworx_ds_notice(
				$refused
					? array(
						'tone' => 'danger',
						'text' => sprintf(
							/* translators: %s: the boxes that were not saved, e.g. "Google Tag Manager ID". */
							__( 'Not saved: %s. That is not the right format, so the previous value was kept. Everything else was saved.', 'blueworx-labs-wordpress' ),
							implode( ', ', wp_list_pluck( $refused, 'label' ) )
						),
					)
					: array(
						'tone' => 'success',
						'text' => __( 'Settings saved.', 'blueworx-labs-wordpress' ),
					)
			),
			blueworx_ds_allowed_html()
		);
	}

	if ( isset( $live['gtm'], $live['ga'] ) ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone' => 'warning',
					'text' => __( 'Both Tag Manager and Analytics are set here. If Analytics is also set up inside Tag Manager, every visit is counted twice — empty the Analytics box if so.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	$fields = '';

	foreach ( $services as $service => $definition ) {
		$id = 'blueworx_tracking_' . $service;

		$fields .= blueworx_ds_field(
			array(
				'label'   => $definition['label'],
				'for'     => $id,
				'help'    => $definition['help'],
				'control' => blueworx_ds_input(
					array(
						'name'  => $id,
						'id'    => $id,
						'value' => blueworx_tracking_id( $service ),
						'mono'  => true,
						'attrs' => array(
							'placeholder'  => $definition['placeholder'],
							'autocomplete' => 'off',
						),
					)
				),
			)
		);
	}

	echo wp_kses(
		blueworx_ds_card(
			array(
				'title' => __( 'IDs', 'blueworx-labs-wordpress' ),
				'body'  => $fields,
			)
		)
		. blueworx_ds_notice(
			array(
				'tone' => 'info',
				'text' => __( 'The code is added to every page visitors see, including the shop and checkout, but never to admin screens or the login page. It loads for everyone, including people signed in, so it can be tested from here.', 'blueworx-labs-wordpress' ),
			)
		),
		blueworx_ds_allowed_html()
	);

	$savebar = '<div class="bw-savebar"><span class="bw-savebar__hint">'
		. esc_html__( 'Nothing changes until you save.', 'blueworx-labs-wordpress' )
		. '</span>'
		. blueworx_ds_button(
			array(
				'label'   => __( 'Save changes', 'blueworx-labs-wordpress' ),
				'variant' => 'primary',
				'type'    => 'submit',
			)
		)
		. '</div></form>';

	blueworx_close_admin_page( $savebar );
}
