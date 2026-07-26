<?php
/**
 * On-page translation.
 *
 * A floating language switcher that translates the current page in the visitor's
 * own browser using the Chrome built-in Translator API. There is no translation
 * service, no API key and no server-side translation store: this file only
 * decides which languages to offer and hands that list to the frontend script.
 *
 * Deliberately not an SEO feature — no translated URLs and no hreflang. See
 * docs/superpowers/specs/2026-07-26-on-page-translation-design.md.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget corner positions.
 *
 * @return array Position labels keyed by position id.
 */
function blueworx_translate_positions() {
	return array(
		'bottom-right' => __( 'Bottom right', 'blueworx-labs-wordpress' ),
		'bottom-left'  => __( 'Bottom left', 'blueworx-labs-wordpress' ),
		'top-right'    => __( 'Top right', 'blueworx-labs-wordpress' ),
		'top-left'     => __( 'Top left', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Gets every language this feature knows a label for.
 *
 * Includes the site's own language: the switcher needs a label for the source
 * language to offer "back to original", and a non-English site needs English as
 * a target.
 *
 * @return array English language names keyed by BCP-47 base tag.
 */
function blueworx_translate_language_labels() {
	return array(
		'ar' => __( 'Arabic', 'blueworx-labs-wordpress' ),
		'bn' => __( 'Bengali', 'blueworx-labs-wordpress' ),
		'de' => __( 'German', 'blueworx-labs-wordpress' ),
		'en' => __( 'English', 'blueworx-labs-wordpress' ),
		'es' => __( 'Spanish', 'blueworx-labs-wordpress' ),
		'fr' => __( 'French', 'blueworx-labs-wordpress' ),
		'hi' => __( 'Hindi', 'blueworx-labs-wordpress' ),
		'it' => __( 'Italian', 'blueworx-labs-wordpress' ),
		'ja' => __( 'Japanese', 'blueworx-labs-wordpress' ),
		'ko' => __( 'Korean', 'blueworx-labs-wordpress' ),
		'nl' => __( 'Dutch', 'blueworx-labs-wordpress' ),
		'pl' => __( 'Polish', 'blueworx-labs-wordpress' ),
		'pt' => __( 'Portuguese', 'blueworx-labs-wordpress' ),
		'ru' => __( 'Russian', 'blueworx-labs-wordpress' ),
		'tr' => __( 'Turkish', 'blueworx-labs-wordpress' ),
		'vi' => __( 'Vietnamese', 'blueworx-labs-wordpress' ),
		'zh' => __( 'Chinese', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Gets the source language: the site's own locale as a BCP-47 base tag.
 *
 * get_locale() returns forms like "en_GB" and "pt_BR"; the Translator API takes
 * base tags, and a site whose locale is not in the label map still translates
 * correctly from its own language.
 *
 * @return string Two-letter language tag, or "en" when the locale is unusable.
 */
function blueworx_translate_source_language() {
	$base = strtolower( substr( (string) get_locale(), 0, 2 ) );

	return preg_match( '/^[a-z]{2}$/', $base ) ? $base : 'en';
}

/**
 * Gets the languages that may be offered as translation targets.
 *
 * The site's own language is removed: translating English into English is not a
 * choice worth offering, and it must never be savable.
 *
 * @return array English language names keyed by BCP-47 base tag.
 */
function blueworx_translate_supported_languages() {
	$labels = blueworx_translate_language_labels();
	unset( $labels[ blueworx_translate_source_language() ] );

	return $labels;
}

/**
 * Gets the saved target languages.
 *
 * Validated on read as well as on write, so a value that predates a change to
 * the supported list — or one written directly to the database — cannot reach
 * the frontend.
 *
 * @return array Ordered list of BCP-47 base tags.
 */
function blueworx_translate_languages() {
	$saved     = get_option( 'blueworx_translate_languages', array( 'fr', 'de', 'es' ) );
	$supported = blueworx_translate_supported_languages();

	if ( ! is_array( $saved ) ) {
		return array();
	}

	$codes = array_map( 'sanitize_key', $saved );

	return array_values( array_intersect( array_unique( $codes ), array_keys( $supported ) ) );
}

/**
 * Gets the widget corner position.
 *
 * @return string One of the keys of blueworx_translate_positions().
 */
function blueworx_translate_position() {
	$saved = sanitize_key( (string) get_option( 'blueworx_translate_position', 'bottom-right' ) );

	return isset( blueworx_translate_positions()[ $saved ] ) ? $saved : 'bottom-right';
}

/**
 * Gets the switcher button label.
 *
 * @return string Non-empty label.
 */
function blueworx_translate_label() {
	$saved = trim( (string) get_option( 'blueworx_translate_label', '' ) );

	return '' === $saved ? __( 'Language', 'blueworx-labs-wordpress' ) : $saved;
}

/**
 * Gets the extra CSS selectors whose contents must never be translated.
 *
 * @return array Ordered list of selectors.
 */
function blueworx_translate_exclusions() {
	$saved = get_option( 'blueworx_translate_exclusions', array() );

	return is_array( $saved ) ? array_values( array_filter( array_map( 'strval', $saved ) ) ) : array();
}

/**
 * Sanitises and saves the translation settings.
 *
 * Called from blueworx_save_feature_settings(), which has already performed the
 * capability check, the client-role console check and the nonce check. This
 * function does no permission checking of its own and must never be wired to a
 * request on its own.
 *
 * @param array $post Raw $_POST.
 * @return void
 */
function blueworx_translate_save_settings( $post ) {
	$supported = blueworx_translate_supported_languages();
	$raw_langs = isset( $post['blueworx_translate_languages'] ) ? (array) wp_unslash( $post['blueworx_translate_languages'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with sanitize_key and intersected against the supported allowlist.
	$languages = array_values( array_intersect( array_unique( array_map( 'sanitize_key', $raw_langs ) ), array_keys( $supported ) ) );

	$raw_position = isset( $post['blueworx_translate_position'] ) ? sanitize_key( wp_unslash( $post['blueworx_translate_position'] ) ) : '';
	$position     = isset( blueworx_translate_positions()[ $raw_position ] ) ? $raw_position : 'bottom-right';

	$raw_label = isset( $post['blueworx_translate_label'] ) ? sanitize_text_field( wp_unslash( $post['blueworx_translate_label'] ) ) : '';
	$label     = trim( mb_substr( $raw_label, 0, 40 ) );

	// One selector per line, blank lines dropped, and hard caps on both the line
	// length and the number of lines so a paste accident cannot bloat the option
	// or the inline config payload on every page load.
	$raw_exclusions = isset( $post['blueworx_translate_exclusions'] ) ? (string) wp_unslash( $post['blueworx_translate_exclusions'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below, line by line, with sanitize_text_field.
	$exclusions     = array();

	foreach ( preg_split( '/\r\n|\r|\n/', $raw_exclusions ) as $line ) {
		$line = trim( sanitize_text_field( $line ) );

		if ( '' === $line ) {
			continue;
		}

		$exclusions[] = mb_substr( $line, 0, 200 );

		if ( count( $exclusions ) >= 50 ) {
			break;
		}
	}

	update_option( 'blueworx_translate_languages', $languages, false );
	update_option( 'blueworx_translate_position', $position );
	update_option( 'blueworx_translate_label', $label );
	update_option( 'blueworx_translate_exclusions', array_values( array_unique( $exclusions ) ), false );
}

/**
 * Renders the nested settings panel shown under the feature toggle.
 *
 * @return void
 */
function blueworx_translate_render_detail() {
	$supported  = blueworx_translate_supported_languages();
	$selected   = blueworx_translate_languages();
	$positions  = blueworx_translate_positions();
	$position   = blueworx_translate_position();
	$exclusions = blueworx_translate_exclusions();
	?>
	<p class="description">
		<?php esc_html_e( 'Adds a floating language button to the site. Translation happens in the visitor\'s own browser, so there is no cost and no data leaves their device. Works in Chrome and Edge 138 or newer; in other browsers the button does not appear. Search engines still only see the original language.', 'blueworx-labs-wordpress' ); ?>
	</p>
	<fieldset>
		<legend><?php esc_html_e( 'Languages offered', 'blueworx-labs-wordpress' ); ?></legend>
		<?php foreach ( $supported as $code => $label ) : ?>
			<label style="display:inline-block;min-width:9em;">
				<input type="checkbox" name="blueworx_translate_languages[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $selected, true ) ); ?> />
				<?php echo esc_html( $label ); ?>
			</label>
		<?php endforeach; ?>
	</fieldset>
	<p>
		<label for="blueworx_translate_position"><?php esc_html_e( 'Button position', 'blueworx-labs-wordpress' ); ?></label><br />
		<select id="blueworx_translate_position" name="blueworx_translate_position">
			<?php foreach ( $positions as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $position, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="blueworx_translate_label"><?php esc_html_e( 'Button label', 'blueworx-labs-wordpress' ); ?></label><br />
		<input type="text" id="blueworx_translate_label" name="blueworx_translate_label" class="regular-text" maxlength="40" value="<?php echo esc_attr( blueworx_translate_label() ); ?>" />
	</p>
	<p>
		<label for="blueworx_translate_exclusions"><?php esc_html_e( 'Never translate (one CSS selector per line)', 'blueworx-labs-wordpress' ); ?></label><br />
		<textarea id="blueworx_translate_exclusions" name="blueworx_translate_exclusions" class="large-text code" rows="4"><?php echo esc_textarea( implode( "\n", $exclusions ) ); ?></textarea>
		<span class="description"><?php esc_html_e( 'Use for brand names, product codes and anything that must stay as written. Code, pre and elements marked translate="no" or .notranslate are always skipped.', 'blueworx-labs-wordpress' ); ?></span>
	</p>
	<?php
}
