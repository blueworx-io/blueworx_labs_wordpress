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
 * Ordered by English label (Arabic, Chinese, English, French, German, Spanish),
 * NOT by language code — do not "tidy" this back into code order. Every other
 * function that lists languages (blueworx_translate_supported_languages(),
 * blueworx_translate_sanitize_languages()) derives its order from this map, and
 * the switcher menu must read alphabetically to a human, which code order does
 * not give: "zh" sorts after "fr" and "de" by code, but Chinese sorts before
 * both by name.
 *
 * @return array English language names keyed by BCP-47 base tag.
 */
function blueworx_translate_language_labels() {
	return array(
		'ar' => __( 'Arabic', 'blueworx-labs-wordpress' ),
		'zh' => __( 'Chinese', 'blueworx-labs-wordpress' ),
		'en' => __( 'English', 'blueworx-labs-wordpress' ),
		'fr' => __( 'French', 'blueworx-labs-wordpress' ),
		'de' => __( 'German', 'blueworx-labs-wordpress' ),
		'es' => __( 'Spanish', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Gets the flag emoji for every language this feature knows a label for.
 *
 * Emoji rather than image assets: a flag emoji is a real character, so it
 * inherits the site's own font sizing, needs no HTTP request and cannot go
 * stale. Where the platform has no flag glyph — Windows, notably — the pair of
 * regional indicators degrades to the two-letter country code, which still
 * reads as a compact language marker rather than a broken box. The mobile
 * default (flags only) matters most on Android and iOS, and both render the
 * real flags.
 *
 * A language is not a country, so these are conventional rather than exact:
 * Arabic is shown with the Saudi flag and English with the Union Flag, the way
 * language switchers on the web generally do it.
 *
 * @return array Flag emoji keyed by BCP-47 base tag.
 */
function blueworx_translate_language_flags() {
	return array(
		'ar' => '🇸🇦',
		'zh' => '🇨🇳',
		'en' => '🇬🇧',
		'fr' => '🇫🇷',
		'de' => '🇩🇪',
		'es' => '🇪🇸',
	);
}

/**
 * Gets the flag for one language, falling back to its upper-cased code.
 *
 * The fallback matters for the source language: a site whose locale is not in
 * the flag map still needs something to show in the flags-only style, and an
 * empty flag would leave the switcher with no visible content at all.
 *
 * @param string $code BCP-47 base tag.
 * @return string Flag emoji, or the upper-cased code.
 */
function blueworx_translate_language_flag( $code ) {
	$flags = blueworx_translate_language_flags();

	return isset( $flags[ $code ] ) ? $flags[ $code ] : strtoupper( $code );
}

/**
 * Switcher display styles.
 *
 * @return array Style labels keyed by style id.
 */
function blueworx_translate_display_styles() {
	return array(
		'text'       => __( 'Text only', 'blueworx-labs-wordpress' ),
		'text-flags' => __( 'Text & flags', 'blueworx-labs-wordpress' ),
		'flags'      => __( 'Flags only', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Gets the switcher display style.
 *
 * Applies to desktop only: narrow screens always fall back to flags only, in
 * CSS rather than here, so the same markup serves both without a resize
 * listener and without the server needing to know the viewport.
 *
 * @return string One of the keys of blueworx_translate_display_styles().
 */
function blueworx_translate_display() {
	$saved = sanitize_key( (string) get_option( 'blueworx_translate_display', 'text' ) );

	return isset( blueworx_translate_display_styles()[ $saved ] ) ? $saved : 'text';
}

/**
 * Gets the source language: the site's own locale as a BCP-47 base tag.
 *
 * Locales arrive from get_locale() in forms like "en_GB" and "pt_BR"; the
 * Translator API takes base tags, and a site whose locale is not in the label
 * map still translates correctly from its own language.
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
 * Sanitises a raw list of language codes down to the validated, canonical order.
 *
 * Shared by blueworx_translate_languages() (read) and
 * blueworx_translate_save_settings() (write) so the same codes are always
 * validated the same way, and so the result is always ordered by the plugin's
 * fixed language map rather than by whatever order the codes arrived in — the
 * order checkboxes happen to be submitted in, or the order a value was saved
 * in previously, never matters.
 *
 * @param array $codes Raw language codes.
 * @return array Ordered list of BCP-47 base tags, in canonical order.
 */
function blueworx_translate_sanitize_languages( $codes ) {
	if ( ! is_array( $codes ) ) {
		return array();
	}

	$supported = blueworx_translate_supported_languages();
	$sanitized = array_unique( array_map( 'sanitize_key', $codes ) );

	return array_values( array_intersect( array_keys( $supported ), $sanitized ) );
}

/**
 * Gets the saved target languages.
 *
 * Validated on read as well as on write, so a value that predates a change to
 * the supported list — or one written directly to the database — cannot reach
 * the frontend. Always returned in canonical order; see
 * blueworx_translate_sanitize_languages().
 *
 * @return array Ordered list of BCP-47 base tags.
 */
function blueworx_translate_languages() {
	$saved = get_option( 'blueworx_translate_languages', array_keys( blueworx_translate_supported_languages() ) );

	return blueworx_translate_sanitize_languages( $saved );
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
 * capability check and the nonce check. This
 * function does no permission checking of its own and must never be wired to a
 * request on its own.
 *
 * @param array $post Raw $_POST.
 * @return void
 */
function blueworx_translate_save_settings( $post ) {
	$raw_langs = isset( $post['blueworx_translate_languages'] ) ? (array) wp_unslash( $post['blueworx_translate_languages'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below by blueworx_translate_sanitize_languages() with sanitize_key and intersected against the supported allowlist.
	$languages = blueworx_translate_sanitize_languages( $raw_langs );

	$raw_position = isset( $post['blueworx_translate_position'] ) ? sanitize_key( wp_unslash( $post['blueworx_translate_position'] ) ) : '';
	$position     = isset( blueworx_translate_positions()[ $raw_position ] ) ? $raw_position : 'bottom-right';

	$raw_display = isset( $post['blueworx_translate_display'] ) ? sanitize_key( wp_unslash( $post['blueworx_translate_display'] ) ) : '';
	$display     = isset( blueworx_translate_display_styles()[ $raw_display ] ) ? $raw_display : 'text';

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
	update_option( 'blueworx_translate_display', $display );
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
	$styles     = blueworx_translate_display_styles();
	$display    = blueworx_translate_display();
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
		<label for="blueworx_translate_display"><?php esc_html_e( 'Show languages as', 'blueworx-labs-wordpress' ); ?></label><br />
		<select id="blueworx_translate_display" name="blueworx_translate_display">
			<?php foreach ( $styles as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $display, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select><br />
		<span class="description"><?php esc_html_e( 'Applies on desktop. On phones the switcher always shows flags only, to save space. Windows has no flag glyphs and shows the two-letter country code instead.', 'blueworx-labs-wordpress' ); ?></span>
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

/**
 * Decides whether the frontend widget should load at all.
 *
 * @return bool True when the feature is on, this is a frontend request, and at
 *              least one target language is configured.
 */
function blueworx_translate_should_load() {
	if ( is_admin() || ! blueworx_feature_enabled( 'translate' ) ) {
		return false;
	}

	return array() !== blueworx_translate_languages();
}

/**
 * Builds the config handed to the frontend script.
 *
 * @return array Config payload.
 */
function blueworx_translate_config() {
	$labels    = blueworx_translate_language_labels();
	$source    = blueworx_translate_source_language();
	$languages = array();

	foreach ( blueworx_translate_languages() as $code ) {
		$languages[] = array(
			'code'  => $code,
			'label' => isset( $labels[ $code ] ) ? $labels[ $code ] : strtoupper( $code ),
			'flag'  => blueworx_translate_language_flag( $code ),
		);
	}

	return array(
		'source'      => $source,
		'sourceLabel' => isset( $labels[ $source ] ) ? $labels[ $source ] : strtoupper( $source ),
		'sourceFlag'  => blueworx_translate_language_flag( $source ),
		'languages'   => $languages,
		'position'    => blueworx_translate_position(),
		'display'     => blueworx_translate_display(),
		'label'       => blueworx_translate_label(),
		'exclude'     => blueworx_translate_exclusions(),
		'errorLabel'  => __( "Couldn't load that language.", 'blueworx-labs-wordpress' ),
	);
}

/**
 * Enqueues the frontend widget script and stylesheet.
 *
 * @return void
 */
function blueworx_translate_enqueue_assets() {
	if ( ! blueworx_translate_should_load() ) {
		return;
	}

	wp_enqueue_style(
		'blueworx-translate-widget',
		BLUEWORX_LABS_URL . 'assets/css/translate-widget.css',
		array(),
		blueworx_get_admin_asset_version( 'assets/css/translate-widget.css' )
	);

	wp_enqueue_script(
		'blueworx-translate-widget',
		BLUEWORX_LABS_URL . 'assets/js/translate-widget.js',
		array(),
		blueworx_get_admin_asset_version( 'assets/js/translate-widget.js' ),
		true
	);

	wp_add_inline_script(
		'blueworx-translate-widget',
		'window.blueworxTranslate = ' . wp_json_encode( blueworx_translate_config() ) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'blueworx_translate_enqueue_assets' );

/**
 * Prints the empty root the script builds the widget inside.
 *
 * Kept empty on purpose: a browser without the Translator API leaves it empty
 * and invisible, so an unsupported browser shows no control at all rather than
 * one that cannot work.
 *
 * @return void
 */
function blueworx_translate_render_root() {
	if ( ! blueworx_translate_should_load() ) {
		return;
	}

	echo '<div id="blueworx-translate-root"></div>';
}
add_action( 'wp_footer', 'blueworx_translate_render_root' );
