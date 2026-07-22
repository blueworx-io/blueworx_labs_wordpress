<?php
/**
 * Contact page template.
 *
 * Ported from app/contact/page.tsx's five sections, in source order: a
 * centered tech-hero (780px), the contact grid (form column + illustration),
 * a dark contact-cards band (phone / WhatsApp / email), a static FAQ section,
 * and testimonials.
 *
 * The form column is where the source mounted a React ContactForm. Per the
 * plan, forms on this site are third-party shortcodes: this renders the
 * shortcode named by the `blueworx_contact_form_shortcode` option (filterable),
 * and nothing else — do_shortcode() is called on that single configured value,
 * NOT on arbitrary input, so it can never be coerced into running some other
 * shortcode. When the option is empty (the default), a clearly-labelled
 * placeholder stands in so the page is whole and obviously awaiting a form.
 *
 * The FAQ list is a Plan 3 interactive accordion; until then it renders as
 * native <details> so it is fully functional with no JavaScript.
 *
 * The <main><div> wrapper is required, not stylistic: globals.css targets
 * `main > div > .sec:last-child` to zero the final section's bottom padding.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blueworx_contact_cards = array(
	array(
		'icon'  => 'phone',
		'title' => __( 'Give us a call', 'blueworx-labs-wordpress' ),
		'sub'   => __( 'Mon–Fri from 8am to 5pm.', 'blueworx-labs-wordpress' ),
		'link'  => '+00 (704) 555-0127',
		'href'  => 'tel:+007045550127',
	),
	array(
		'icon'  => 'chat',
		'title' => __( 'Send us a WhatsApp', 'blueworx-labs-wordpress' ),
		'sub'   => __( 'Speak to our friendly team.', 'blueworx-labs-wordpress' ),
		'link'  => '+00 (704) 555-0127',
		'href'  => 'https://wa.me/007045550127',
	),
	array(
		'icon'  => 'mail',
		'title' => __( 'Email us here', 'blueworx-labs-wordpress' ),
		'sub'   => __( 'Let us know how we can help.', 'blueworx-labs-wordpress' ),
		'link'  => 'info@blueworx.com',
		'href'  => 'mailto:info@blueworx.com',
	),
);

/**
 * The single shortcode the contact form column renders.
 *
 * Empty by default. Set the `blueworx_contact_form_shortcode` option, or hook
 * this filter, to the exact shortcode of your form plugin, e.g.
 * `[sureforms id="12"]`. Only this one configured value is ever passed to
 * do_shortcode().
 */
$blueworx_contact_form_shortcode = (string) apply_filters(
	'blueworx_contact_form_shortcode',
	(string) get_option( 'blueworx_contact_form_shortcode', '' )
);

blueworx_public_document_open( array( 'body_class' => 'bw-contact' ) );
blueworx_public_part( 'parts/nav.php' );
?>
<main>
	<div>
		<?php
		blueworx_public_part(
			'parts/tech-hero.php',
			array(
				'badge'           => __( 'Contact Us', 'blueworx-labs-wordpress' ),
				'title'           => __( 'Start Your Conversation With BlueWorx', 'blueworx-labs-wordpress' ),
				'title_highlight' => __( 'With BlueWorx', 'blueworx-labs-wordpress' ),
				'lead'            => __( 'Tell us where your digital presence is holding you back and we&rsquo;ll show you exactly how to fix it.', 'blueworx-labs-wordpress' ),
				'max_width'       => 780,
				'meta'            => array(
					__( 'reply within 1 business day', 'blueworx-labs-wordpress' ),
					__( 'no obligation', 'blueworx-labs-wordpress' ),
				),
			)
		);
		?>

		<section style="padding:56px 0 0">
			<div class="contact-grid">
				<div class="contact-form">
					<?php
					if ( '' !== trim( $blueworx_contact_form_shortcode ) ) {
						// Only the single configured shortcode is rendered.
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_shortcode returns the form plugin's own escaped markup.
						echo do_shortcode( $blueworx_contact_form_shortcode );
					} else {
						?>
						<div class="bw-plan3-placeholder" data-widget="contact-form">
							<p><?php esc_html_e( 'Contact form goes here.', 'blueworx-labs-wordpress' ); ?></p>
							<p class="bw-placeholder-note"><?php esc_html_e( 'Set the contact form shortcode (BlueWorx contact form option) to your form plugin&rsquo;s shortcode to display it here.', 'blueworx-labs-wordpress' ); ?></p>
						</div>
						<?php
					}
					?>
				</div>
				<div class="contact-illus">
					<img src="<?php echo esc_url( BLUEWORX_LABS_URL . 'assets/img/contact-illustration.jpg' ); ?>" alt="<?php esc_attr_e( 'Get in touch', 'blueworx-labs-wordpress' ); ?>" />
				</div>
			</div>
		</section>

		<section class="contact-cards">
			<div class="blob" style="width:280px;height:280px;bottom:-100px;right:-80px;opacity:.14"></div>
			<div class="cc-grid">
				<?php foreach ( $blueworx_contact_cards as $blueworx_contact_card ) : ?>
					<div class="cc">
						<div class="cc-ic"><?php blueworx_icon( $blueworx_contact_card['icon'] ); ?></div>
						<h4><?php echo esc_html( $blueworx_contact_card['title'] ); ?></h4>
						<p><?php echo esc_html( $blueworx_contact_card['sub'] ); ?></p>
						<a href="<?php echo esc_url( $blueworx_contact_card['href'] ); ?>"<?php echo 0 === strpos( $blueworx_contact_card['href'], 'https://' ) ? ' rel="noopener"' : ''; ?>><?php echo esc_html( $blueworx_contact_card['link'] ); ?></a>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="sec">
			<div class="center-head" style="margin-bottom:40px">
				<h2 class="h2"><?php esc_html_e( 'Frequently asked questions', 'blueworx-labs-wordpress' ); ?></h2>
				<p class="lead"><?php esc_html_e( 'Everything you need to know about the product and billing.', 'blueworx-labs-wordpress' ); ?></p>
			</div>
			<div class="faq-list">
				<?php foreach ( blueworx_content_faqs() as $blueworx_contact_faq ) : ?>
					<details class="faq-item">
						<summary class="faq-q"><?php echo esc_html( $blueworx_contact_faq['q'] ); ?></summary>
						<div class="faq-a"><?php echo esc_html( $blueworx_contact_faq['a'] ); ?></div>
					</details>
				<?php endforeach; ?>
			</div>
		</section>

		<?php
		blueworx_public_part(
			'parts/testimonials.php',
			array(
				'testimonials' => blueworx_content_reviews(),
				'style'        => 'padding-top:0',
			)
		);
		?>
	</div>
</main>
<?php
blueworx_public_part( 'parts/footer.php' );
blueworx_public_document_close();
