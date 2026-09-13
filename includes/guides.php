<?php
/**
 * Guide registry for the Guides page.
 *
 * The page assembles itself rather than being maintained as a list of links.
 * Tabs come from the feature sections in features.php, and every feature in the
 * registry gets a guide slot, so a feature added there appears here without
 * anyone remembering to add it. A feature switched off in settings has its
 * guide hidden — a client should never be reading instructions for something
 * they cannot see. The same rule applies to roles: a guide only reaches
 * somebody who could actually do the thing it describes, so the BlueWorx
 * section is administrator-only and an editor never sees the topics about
 * users, updates or settings.
 *
 * Other plugins extend both halves: `blueworx_guide_tabs` adds a tab,
 * `blueworx_guides` adds guides. Everything they supply is escaped on output.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tab id used for guides whose declared tab does not exist.
 */
const BLUEWORX_GUIDES_FALLBACK_TAB = 'other';

/**
 * Escapes one line of guide text and adds the emphasis a guide is allowed.
 *
 * Guides name buttons in *asterisks* — "press *Save*" — which become <em> after
 * escaping, so a guide can point at a label without ever handing raw markup to
 * the page. A lone asterisk (2 * 3) is left as it is.
 *
 * @param string $text Plain text with optional *emphasis*.
 * @return string Escaped HTML.
 */
function blueworx_guide_text( $text ) {
	return preg_replace( '/\*([^*\s][^*]*?)\*/', '<em>$1</em>', esc_html( (string) $text ) );
}

/**
 * Builds a guide body from its parts, so every guide reads the same way.
 *
 * Where, an optional opening sentence, the numbered steps, then what happens
 * next. Everything is escaped here, so the result already passes wp_kses_post
 * on output and a third party using this helper gets the same shape as ours.
 *
 * @param array $parts {
 *     @type string   $where The menu path, as the sidebar labels it.
 *     @type string   $intro Optional framing sentence.
 *     @type string[] $steps One action per step.
 *     @type string   $then  What the person should now see.
 * }
 * @return string HTML.
 */
function blueworx_guide_body( $parts ) {
	$html = '';

	if ( ! empty( $parts['where'] ) ) {
		$html .= '<p class="bw-guide__where"><strong>' . esc_html__( 'Where:', 'blueworx-labs-wordpress' ) . '</strong> ' . blueworx_guide_text( $parts['where'] ) . '</p>';
	}

	if ( ! empty( $parts['intro'] ) ) {
		$html .= '<p>' . blueworx_guide_text( $parts['intro'] ) . '</p>';
	}

	if ( ! empty( $parts['steps'] ) ) {
		$html .= '<ol class="bw-guide__steps">';
		foreach ( (array) $parts['steps'] as $step ) {
			$html .= '<li>' . blueworx_guide_text( $step ) . '</li>';
		}
		$html .= '</ol>';
	}

	if ( ! empty( $parts['then'] ) ) {
		$html .= '<p class="bw-guide__then">' . blueworx_guide_text( $parts['then'] ) . '</p>';
	}

	return $html;
}

/**
 * Gets the ordered Guides tabs.
 *
 * Getting started first, then the feature sections in their settings-page
 * order, so guides and settings read in the same shape.
 *
 * @return array Tab labels keyed by tab id, in display order.
 */
function blueworx_get_guide_tabs() {
	$tabs = array_merge(
		array( 'getting-started' => __( 'Getting started', 'blueworx-labs-wordpress' ) ),
		blueworx_get_feature_sections()
	);

	/**
	 * Filters the Guides tabs.
	 *
	 * @param array $tabs Tab labels keyed by tab id, in display order.
	 */
	$tabs = apply_filters( 'blueworx_guide_tabs', $tabs );

	$clean = array();
	foreach ( (array) $tabs as $id => $label ) {
		$id = sanitize_key( $id );
		if ( '' === $id || ! is_string( $label ) || '' === trim( $label ) ) {
			continue;
		}
		$clean[ $id ] = $label;
	}

	return $clean;
}

/**
 * The guides that are not about this plugin.
 *
 * WordPress topics show on every site. The SureCart and SureForms sets are
 * filtered out unless those plugins are running, which is decided once in
 * blueworx_get_guide_products() rather than repeated here.
 *
 * @return array List of guides.
 */
function blueworx_get_other_product_guides() {
	$guides = array(
		array(
			'id'      => 'wp-writing-blocks',
			'title'   => __( 'Writing a page, block by block', 'blueworx-labs-wordpress' ),
			'tab'     => 'wp-writing',
			'product' => 'wordpress',
			'body'    => '<p>' . esc_html__( 'A page is built from blocks — a heading, a paragraph, an image, a button. Press the black + at the top left to add one, or type / on an empty line and start naming what you want.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'Nothing is live until you press Publish or Update. Save draft keeps your work without showing it to anybody, and Preview opens it exactly as a visitor would see it.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'      => 'wp-writing-links',
			'title'   => __( 'Links, headings and the things that break', 'blueworx-labs-wordpress' ),
			'tab'     => 'wp-writing',
			'product' => 'wordpress',
			'body'    => '<p>' . esc_html__( 'Select some text and press Ctrl+K, or Cmd+K on a Mac, to make it a link. Start typing a page name and the site offers its own pages — always pick one from that list rather than pasting an address, so the link follows the page if it is ever renamed.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'Use one Heading 1 per page and go down in order. Skipping from a Heading 1 to a Heading 3 because it looked the right size is the most common reason a page is hard to use with a screen reader.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'      => 'wp-media-uploads',
			'title'   => __( 'Adding images without slowing the site down', 'blueworx-labs-wordpress' ),
			'tab'     => 'wp-media',
			'product' => 'wordpress',
			'body'    => '<p>' . esc_html__( 'Upload the best version you have and let the site make the smaller ones. It keeps a set of sizes for every image and serves whichever fits the space, so a photo straight off a camera is not what a visitor downloads.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'Give every image alt text that says what is in it. Screen readers read it aloud and search engines read it too. A decorative flourish can be left empty; a photograph of your team cannot.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'      => 'wp-media-replacing',
			'title'   => __( 'Replacing a file people already have the link to', 'blueworx-labs-wordpress' ),
			'tab'     => 'wp-media',
			'product' => 'wordpress',
			'body'    => '<p>' . esc_html__( 'Deleting a file and uploading a new one gives it a new address, and every link and embed pointing at the old one stops working. Nothing warns you when that happens.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'Open the file in the media library and use Replace file instead. The address stays the same, so a price list you have emailed to two hundred people keeps working.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'      => 'wp-people-roles',
			'title'   => __( 'Which role to give somebody', 'blueworx-labs-wordpress' ),
			'tab'     => 'wp-people',
			'product' => 'wordpress',
			'body'    => '<p>' . esc_html__( 'Administrator can change anything, including installing plugins and removing other people. Editor can publish and edit anyone else\'s content but cannot touch settings. Author can publish their own. Contributor can write but not publish. Subscriber can only sign in.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'Give the smallest role that lets somebody do their job. Most people who say they need admin need Editor, and an extra administrator is an extra way for the site to be taken over.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'      => 'wp-people-leaving',
			'title'   => __( 'When somebody leaves', 'blueworx-labs-wordpress' ),
			'tab'     => 'wp-people',
			'product' => 'wordpress',
			'body'    => '<p>' . esc_html__( 'Delete the account rather than changing its password. WordPress asks what to do with anything they wrote — choose another person and their posts are reassigned rather than deleted with them.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'Do it the day they leave. A dormant account with a known password is the most common way a site is broken into months after anybody was still watching.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'      => 'wp-upkeep-updates',
			'title'   => __( 'Updates, and which ones can wait', 'blueworx-labs-wordpress' ),
			'tab'     => 'wp-upkeep',
			'product' => 'wordpress',
			'body'    => '<p>' . esc_html__( 'A security release is not the same as a feature release. If an update says it fixes a vulnerability, apply it now. Everything else can wait for whoever looks after the site.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'Never update a plugin for the first time an hour before something important. Where the site has a staging copy, updates are tried there first and reach the live site afterwards.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'      => 'wp-upkeep-health',
			'title'   => __( 'Reading Site Health without panicking', 'blueworx-labs-wordpress' ),
			'tab'     => 'wp-upkeep',
			'product' => 'wordpress',
			'body'    => '<p>' . esc_html__( 'Site Health lists critical issues and recommended improvements. Critical means something is actually broken. Recommended usually means a setting could be better, and a site can run for years with several of them showing.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'The score is not a grade. Do not chase it. Read the critical list, ignore the number, and send anything you do not understand to whoever maintains the site.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'      => 'sc-products-plans',
			'title'   => __( 'Products, prices and plans', 'blueworx-labs-wordpress' ),
			'tab'     => 'sc-products',
			'product' => 'surecart',
			'body'    => '<p>' . esc_html__( 'A product is the thing being sold. A price is what it costs, and one product can carry several — a monthly price and an annual one, say. Changing a price does not change what anybody already pays; it only affects new purchases.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'To stop selling something, archive the product rather than deleting it. Deleting takes its order history with it.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'      => 'sc-orders-refunds',
			'title'   => __( 'Orders, customers and refunds', 'blueworx-labs-wordpress' ),
			'tab'     => 'sc-orders',
			'product' => 'surecart',
			'body'    => '<p>' . esc_html__( 'Every order shows what was bought, what was paid and who paid it. A refund is issued from the order itself and goes back to the card that paid, which can take a few working days to appear.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'Cancelling a subscription and refunding a payment are separate actions. Cancelling stops the next payment; it does not return the last one.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'      => 'sc-payments-test',
			'title'   => __( 'Test mode, and how to tell you are in it', 'blueworx-labs-wordpress' ),
			'tab'     => 'sc-payments',
			'product' => 'surecart',
			'body'    => '<p>' . esc_html__( 'In test mode no real money moves and no real card is charged. It is the right way to check a checkout works before opening it up.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'Test orders never become live ones. Before you take a real payment, switch test mode off and place one small real order yourself — a checkout left in test mode looks entirely normal to a customer, right up until you wonder where the money is.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'      => 'sf-forms-entries',
			'title'   => __( 'Building a form and finding what it collected', 'blueworx-labs-wordpress' ),
			'tab'     => 'sf-forms',
			'product' => 'sureforms',
			'body'    => '<p>' . esc_html__( 'Build the form, then place it on a page with its block. Every submission is stored under Entries as well as emailed, which matters the day an email does not arrive.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'Label every field with what you actually want, not with a placeholder inside the box. A placeholder disappears the moment somebody starts typing, which is exactly when they need it.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'      => 'sf-spam-notifications',
			'title'   => __( 'Spam, and where the notification goes', 'blueworx-labs-wordpress' ),
			'tab'     => 'sf-spam',
			'product' => 'sureforms',
			'body'    => '<p>' . esc_html__( 'Turn the spam protection on before the form goes live, not after. A public form without it starts collecting rubbish within days.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'Check where the notification email is sent, and send yourself a test. A form quietly delivering to somebody who left last year is the most common fault there is, and nothing on the site looks wrong while it happens.', 'blueworx-labs-wordpress' ) . '</p>',
		),
	);

	$products = blueworx_get_guide_products();

	return array_values(
		array_filter(
			$guides,
			static function ( $guide ) use ( $products ) {
				return isset( $products[ $guide['product'] ] );
			}
		)
	);
}

/**
 * The WordPress topics, which every site has.
 *
 * @return array Tab labels keyed by tab id.
 */
function blueworx_get_wordpress_guide_tabs() {
	return array(
		'wp-writing' => __( 'Writing & editing', 'blueworx-labs-wordpress' ),
		'wp-media'   => __( 'Media library', 'blueworx-labs-wordpress' ),
		'wp-people'  => __( 'Users & roles', 'blueworx-labs-wordpress' ),
		'wp-upkeep'  => __( 'Updates & health', 'blueworx-labs-wordpress' ),
	);
}

/**
 * The SureCart topics. Only reached when SureCart is running.
 *
 * @return array Tab labels keyed by tab id.
 */
function blueworx_get_surecart_guide_tabs() {
	return array(
		'sc-products' => __( 'Products & plans', 'blueworx-labs-wordpress' ),
		'sc-orders'   => __( 'Orders & customers', 'blueworx-labs-wordpress' ),
		'sc-payments' => __( 'Payments & test mode', 'blueworx-labs-wordpress' ),
	);
}

/**
 * The SureForms topics. Only reached when SureForms is running.
 *
 * @return array Tab labels keyed by tab id.
 */
function blueworx_get_sureforms_guide_tabs() {
	return array(
		'sf-forms' => __( 'Forms & entries', 'blueworx-labs-wordpress' ),
		'sf-spam'  => __( 'Spam & notifications', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Every tab on the screen, whichever product it belongs to.
 *
 * @return array Tab labels keyed by tab id.
 */
function blueworx_get_all_guide_tabs() {
	$tabs = blueworx_get_guide_tabs() + blueworx_get_wordpress_guide_tabs();

	if ( blueworx_guide_product_is_active( 'surecart' ) ) {
		$tabs += blueworx_get_surecart_guide_tabs();
	}

	if ( blueworx_guide_product_is_active( 'sureforms' ) ) {
		$tabs += blueworx_get_sureforms_guide_tabs();
	}

	return $tabs;
}

/**
 * A product's name as the sidebar currently shows it.
 *
 * With Display names on, SureCart is "Commerce" and LatePoint is "Bookings"
 * across the admin. A guide telling somebody to open "LatePoint" when the menu
 * says "Bookings" is a guide that sends them looking for a word that is not
 * there, so the product tabs and every Where line come through here.
 *
 * @param string $product Product key.
 * @return string Label, or '' for an unknown product.
 */
function blueworx_guide_product_label( $product ) {
	$names = array(
		'blueworx'  => 'BlueWorx',
		'wordpress' => 'WordPress',
		'surecart'  => 'SureCart',
		'sureforms' => 'SureForms',
		'latepoint' => 'LatePoint',
	);

	if ( ! isset( $names[ $product ] ) ) {
		return '';
	}

	$name = $names[ $product ];

	if ( function_exists( 'blueworx_plugin_display_names' ) && blueworx_feature_enabled( 'display_names' ) ) {
		$renamed = blueworx_rename_display_text( $name, blueworx_plugin_display_names() );
		if ( null !== $renamed ) {
			return $renamed;
		}
	}

	return $name;
}

/**
 * Gets the guide products, in display order.
 *
 * A product is the thing a guide is about — this plugin, WordPress itself, or
 * another plugin the site runs. It is the coarser of the two levels on the
 * Guides screen: pick a product along the top, then a topic below it.
 *
 * A product whose plugin is not active never appears. Nobody needs guides for
 * software they do not have.
 *
 * @return array Product labels keyed by product key.
 */
function blueworx_get_guide_products() {
	$products = array(
		'blueworx'  => blueworx_guide_product_label( 'blueworx' ),
		'wordpress' => blueworx_guide_product_label( 'wordpress' ),
	);

	if ( blueworx_guide_product_is_active( 'surecart' ) ) {
		$products['surecart'] = blueworx_guide_product_label( 'surecart' );
	}

	if ( blueworx_guide_product_is_active( 'sureforms' ) ) {
		$products['sureforms'] = blueworx_guide_product_label( 'sureforms' );
	}

	/**
	 * Filters the guide products.
	 *
	 * @param array $products Product labels keyed by key, in display order.
	 */
	$products = apply_filters( 'blueworx_guide_products', $products );

	$clean = array();

	foreach ( (array) $products as $key => $label ) {
		$key = sanitize_key( (string) $key );

		if ( '' === $key || ! is_string( $label ) || '' === trim( $label ) ) {
			continue;
		}

		$clean[ $key ] = $label;
	}

	return $clean;
}

/**
 * Whether a plugin a product's guides describe is actually running.
 *
 * Checked by class and by constant rather than by plugin path, because a
 * plugin's folder is not something we control and a renamed one would silently
 * hide its guides.
 *
 * @param string $product Product key.
 * @return bool True when the plugin is active.
 */
function blueworx_guide_product_is_active( $product ) {
	$signatures = array(
		'surecart'  => array( 'classes' => array( 'SureCart' ), 'constants' => array( 'SURECART_PLUGIN_FILE' ) ),
		'sureforms' => array( 'classes' => array( 'SRFM_Loader' ), 'constants' => array( 'SRFM_FILE' ) ),
	);

	if ( ! isset( $signatures[ $product ] ) ) {
		return false;
	}

	foreach ( $signatures[ $product ]['classes'] as $class ) {
		if ( class_exists( $class ) ) {
			return true;
		}
	}

	foreach ( $signatures[ $product ]['constants'] as $constant ) {
		if ( defined( $constant ) ) {
			return true;
		}
	}

	return false;
}

/**
 * The product a tab belongs to.
 *
 * Every BlueWorx feature section is ours; anything a third party registers is
 * theirs unless they say otherwise.
 *
 * @param string $tab Tab id.
 * @return string Product key.
 */
function blueworx_guide_product_for_tab( $tab ) {
	$map = blueworx_get_guide_tab_products();

	return isset( $map[ $tab ] ) ? $map[ $tab ] : 'blueworx';
}

/**
 * The product each tab belongs to.
 *
 * @return array Product keys by tab id.
 */
function blueworx_get_guide_tab_products() {
	$map = array();

	foreach ( array_keys( blueworx_get_guide_tabs() ) as $tab ) {
		$map[ $tab ] = 'blueworx';
	}

	foreach ( array_keys( blueworx_get_wordpress_guide_tabs() ) as $tab ) {
		$map[ $tab ] = 'wordpress';
	}

	foreach ( array_keys( blueworx_get_surecart_guide_tabs() ) as $tab ) {
		$map[ $tab ] = 'surecart';
	}

	foreach ( array_keys( blueworx_get_sureforms_guide_tabs() ) as $tab ) {
		$map[ $tab ] = 'sureforms';
	}

	/**
	 * Filters which product each guide tab belongs to.
	 *
	 * @param array $map Product keys by tab id.
	 */
	return apply_filters( 'blueworx_guide_tab_products', $map );
}

/**
 * Gets the guides to display for whoever is reading, in tab order.
 *
 * Already narrowed to what this user may act on — see
 * blueworx_filter_guides_by_capability().
 *
 * @return array List of guides, each with id, title, tab, product, body,
 *               feature and capability.
 */
function blueworx_get_guides() {
	$guides = array_merge(
		blueworx_get_wordpress_basics_guides(),
		blueworx_get_feature_guides(),
		blueworx_get_other_product_guides()
	);

	/**
	 * Filters the registered guides.
	 *
	 * A plugin adds its own with:
	 *
	 *     add_filter( 'blueworx_guides', function ( $guides ) {
	 *         $guides[] = array(
	 *             'id'    => 'acme-shipping-zones',
	 *             'title' => 'Setting up shipping zones',
	 *             'tab'   => 'acme',
	 *             'body'  => '<p>…</p>',
	 *         );
	 *         return $guides;
	 *     } );
	 *
	 * @param array $guides List of guides.
	 */
	$guides = apply_filters( 'blueworx_guides', $guides );

	return blueworx_filter_guides_by_capability( blueworx_normalize_guides( $guides ) );
}

/**
 * Drops the guides the person reading cannot act on.
 *
 * Nobody should be reading instructions for a screen they cannot open. A guide
 * is kept only when the reader holds both the capability its section requires
 * and the capability the guide itself describes, so an editor sees the writing
 * and media topics and never the ones about users, updates or this plugin.
 *
 * The tabs and the section row are built from whatever survives this, so an
 * empty topic and an empty section disappear on their own rather than needing
 * to be hidden separately.
 *
 * @param array $guides Normalized guides.
 * @return array The guides this user may read.
 */
function blueworx_filter_guides_by_capability( $guides ) {
	$allowed = array();

	foreach ( $guides as $guide ) {
		foreach ( blueworx_guide_capabilities( $guide ) as $capability ) {
			if ( ! current_user_can( $capability ) ) {
				continue 2;
			}
		}

		$allowed[] = $guide;
	}

	return $allowed;
}

/**
 * Everything somebody must be able to do before a guide is theirs to read.
 *
 * Both gates in one list: the section's, and the guide's own. Not the same
 * question the role pills on the card answer — those say who can do the thing,
 * which is worth knowing about a section only administrators are shown.
 *
 * @param array $guide One normalized guide.
 * @return array Capability names, possibly empty.
 */
function blueworx_guide_capabilities( $guide ) {
	$needed = array(
		blueworx_guide_product_capability( isset( $guide['product'] ) ? $guide['product'] : '' ),
		isset( $guide['capability'] ) ? (string) $guide['capability'] : '',
	);

	return array_values( array_unique( array_filter( $needed ) ) );
}

/**
 * The capability a whole guide section requires.
 *
 * BlueWorx is administrator-only: every screen its guides describe sits behind
 * manage_options, so there is nothing in that section an editor could act on.
 * Every other section is open, and its topics are gated one at a time by what
 * they actually describe — see blueworx_guide_tab_capability().
 *
 * @param string $product Product key.
 * @return string Capability name, or an empty string for a section open to all.
 */
function blueworx_guide_product_capability( $product ) {
	$map = array(
		'blueworx' => 'manage_options',
	);

	/**
	 * Filters the capability a guide section requires.
	 *
	 * @param string $capability Capability name, or '' for no section-level gate.
	 * @param string $product    Product key.
	 */
	return (string) apply_filters(
		'blueworx_guide_product_capability',
		isset( $map[ $product ] ) ? $map[ $product ] : '',
		$product
	);
}

/**
 * Validates and normalizes a guide list from any source.
 *
 * A guide naming a tab that does not exist is kept and moved to the fallback
 * tab rather than dropped: a third party forgetting to register its tab should
 * lose the grouping, not the content.
 *
 * @param array $guides Raw guide list.
 * @return array Normalized guides, in tab order then registration order.
 */
function blueworx_normalize_guides( $guides ) {
	// Every product's tabs, not only ours: a WordPress or SureCart guide naming
	// its own tab would otherwise be swept into the fallback.
	$tabs  = blueworx_get_all_guide_tabs();
	$clean = array();
	$seen  = array();
	$order = array_keys( $tabs );

	$order[] = BLUEWORX_GUIDES_FALLBACK_TAB;

	foreach ( (array) $guides as $guide ) {
		if ( ! is_array( $guide ) ) {
			continue;
		}

		$id    = isset( $guide['id'] ) ? sanitize_key( $guide['id'] ) : '';
		$title = isset( $guide['title'] ) ? (string) $guide['title'] : '';
		$body  = isset( $guide['body'] ) ? (string) $guide['body'] : '';

		if ( '' === $id || '' === trim( $title ) || '' === trim( $body ) ) {
			continue;
		}

		// First registration of an id wins, so a third party cannot displace a
		// built-in guide by reusing its id.
		if ( isset( $seen[ $id ] ) ) {
			continue;
		}
		$seen[ $id ] = true;

		$tab = isset( $guide['tab'] ) ? sanitize_key( $guide['tab'] ) : '';
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = BLUEWORX_GUIDES_FALLBACK_TAB;
		}

		$feature = isset( $guide['feature'] ) ? sanitize_key( $guide['feature'] ) : '';

		// What somebody must be able to do before this guide is worth reading.
		// Left unsaid it is the topic's own capability, which is what the role
		// pills on the card already show — so a guide is hidden from exactly
		// the people those pills say cannot do the thing.
		$capability = isset( $guide['capability'] )
			? sanitize_key( $guide['capability'] )
			: blueworx_guide_tab_capability( $tab );

		$clean[] = array(
			'id'         => $id,
			'title'      => $title,
			'tab'        => $tab,
			'product'    => isset( $guide['product'] ) ? sanitize_key( $guide['product'] ) : blueworx_guide_product_for_tab( $tab ),
			'body'       => $body,
			'feature'    => $feature,
			'capability' => $capability,
		);
	}

	usort(
		$clean,
		static function ( $a, $b ) use ( $order ) {
			$position_a = array_search( $a['tab'], $order, true );
			$position_b = array_search( $b['tab'], $order, true );
			return $position_a <=> $position_b;
		}
	);

	return $clean;
}

/**
 * Gets the guides for this plugin's own features.
 *
 * Every feature in the registry that is not flagged guide => false gets at
 * least one. A feature can carry several tasks; the first keeps the id
 * feature-<key> so nothing that links to it breaks.
 *
 * @return array List of guides.
 */
function blueworx_get_feature_guides() {
	$tasks  = blueworx_get_feature_guide_tasks();
	$guides = array();

	foreach ( blueworx_get_feature_definitions() as $key => $feature ) {
		if ( isset( $feature['guide'] ) && false === $feature['guide'] ) {
			continue;
		}

		if ( ! blueworx_feature_enabled( $key ) ) {
			continue;
		}

		// A feature with nothing written yet still gets one card, from its own
		// settings description, so it is never missing — just brief.
		$list = isset( $tasks[ $key ] ) ? $tasks[ $key ] : array(
			array(
				'slug'  => '',
				'title' => $feature['label'],
				'body'  => '<p>' . esc_html( $feature['description'] ) . '</p>',
			),
		);

		foreach ( $list as $task ) {
			$slug = isset( $task['slug'] ) ? sanitize_key( $task['slug'] ) : '';

			$guides[] = array(
				'id'      => 'feature-' . $key . ( '' === $slug ? '' : '-' . $slug ),
				'title'   => $task['title'],
				'tab'     => $feature['section'],
				'body'    => $task['body'],
				'feature' => $key,
			);
		}
	}

	return $guides;
}

/**
 * The written tasks for each feature, keyed by feature key.
 *
 * Each feature is a list: the first task keeps the id feature-<key>, any
 * others get feature-<key>-<slug>. Only client-facing features are here;
 * the rest are flagged guide => false in the registry.
 *
 * @return array Lists of tasks (slug, title, body) keyed by feature key.
 */
function blueworx_get_feature_guide_tasks() {
	$t = static function ( $text ) {
		return __( $text, 'blueworx-labs-wordpress' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
	};

	return array(
		'login'           => array(
			array(
				'slug'  => '',
				'title' => $t( 'Finding your sign-in address' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'BlueWorx > Enhancements' ),
					'intro' => $t( 'The usual WordPress sign-in address is switched off on this site. Your team signs in at an address only they know.' ),
					'steps' => array(
						$t( 'Open *BlueWorx > Enhancements*.' ),
						$t( 'Look at the top of the *Security & Access* section. Your sign-in address is shown there.' ),
						$t( 'Bookmark it in your browser.' ),
					),
					'then'  => $t( 'Anyone going to the old /wp-admin or /wp-login address is sent to the home page instead. That is expected, not a fault.' ),
				) ),
			),
			array(
				'slug'  => 'changing',
				'title' => $t( 'Changing the sign-in address' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'BlueWorx > Enhancements > Security & Access' ),
					'steps' => array(
						$t( 'Tell everyone on the team the new address first — the old one stops working the moment you save.' ),
						$t( 'Open *Custom login and protection* and type the new word in the *Address to use* box.' ),
						$t( 'Press *Save Changes*.' ),
						$t( 'Sign out and sign back in at the new address to check it.' ),
					),
					'then'  => $t( 'If you cannot get back in, contact BlueWorx — we can reset it for you.' ),
				) ),
			),
		),

		'site_protection' => array(
			array(
				'slug'  => '',
				'title' => $t( 'Making the site private while you build' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'BlueWorx > Enhancements > Security & Access' ),
					'steps' => array(
						$t( 'Open *Site protection*.' ),
						$t( 'Choose what to protect: the front of the site, the admin area, or both.' ),
						$t( 'Tick the roles that may still get in. Make sure your own role is ticked.' ),
						$t( 'Press *Save Changes*.' ),
						$t( 'Open the site in a private browser window to check a visitor is turned away.' ),
					),
					'then'  => $t( 'Visitors who are not signed in see nothing. If you tick the wrong roles and lock yourself out, contact BlueWorx.' ),
				) ),
			),
			array(
				'slug'  => 'opening',
				'title' => $t( 'Opening the site up again' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'BlueWorx > Enhancements > Security & Access' ),
					'steps' => array(
						$t( 'Open *Site protection*.' ),
						$t( 'Switch it off, or untick the front of the site if you only want the admin area kept private.' ),
						$t( 'Press *Save Changes*.' ),
						$t( 'Check the home page in a private browser window.' ),
					),
					'then'  => $t( 'The site is public straight away. If it still looks private, clear the cache — see the Cache guide.' ),
				) ),
			),
		),

		'sso'             => array(
			array(
				'slug'  => '',
				'title' => $t( 'Signing in with your work account' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'The sign-in screen' ),
					'steps' => array(
						$t( 'Go to your sign-in address.' ),
						$t( 'Press the *Sign in with single sign-on* button below the password box.' ),
						$t( 'Sign in with your work account if it asks you to.' ),
					),
					'then'  => $t( 'You land on the site already signed in. If it says the sign-in did not work, you do not have an account here yet — ask whoever runs the site to add you, or use the joining page if there is one.' ),
				) ),
			),
			array(
				'slug'  => 'join-button',
				'title' => $t( 'Putting the Join button on a page' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'BlueWorx > Single sign-on' ),
					'steps' => array(
						$t( 'Copy the join shortcode shown under *Buttons*.' ),
						$t( 'Open the page where people should join, add a *Shortcode* block, and paste it in.' ),
						$t( 'Back on the Single sign-on screen, choose which role a newcomer gets and where they land afterwards.' ),
						$t( 'Press *Save Changes* on the Single sign-on screen, then *Save* the page.' ),
					),
					'then'  => $t( 'The button appears on the page. Nobody who joins this way can ever be made an administrator, whatever their work account says.' ),
				) ),
			),
			array(
				'slug'  => 'failed',
				'title' => $t( 'Finding out why a sign-in failed' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'BlueWorx > Single sign-on > SSO Logs' ),
					'steps' => array(
						$t( 'Ask the person roughly when they tried.' ),
						$t( 'Press *Open SSO Logs* and find their attempt by time.' ),
						$t( 'Read the reason in the *Outcome* column.' ),
					),
					'then'  => $t( 'The person only ever sees a general message — the real reason is here. "No account" means they need adding; "Provider refused" means their work account, not this site.' ),
				) ),
			),
		),

		'support_access'  => array(
			array(
				'slug'  => '',
				'title' => $t( 'Letting BlueWorx look at the site' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'BlueWorx > Support access' ),
					'steps' => array(
						$t( 'Press *Generate key*.' ),
						$t( 'Copy the key and send it to BlueWorx in the thread you are already talking in.' ),
						$t( 'Press *Allow support access for 24 hours*.' ),
					),
					'then'  => $t( 'We can look but not change anything. The window closes on its own after 24 hours, and you never need to share a password.' ),
				) ),
			),
			array(
				'slug'  => 'closing',
				'title' => $t( 'Closing the window early' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'BlueWorx > Support access' ),
					'steps' => array(
						$t( 'Press *Revoke access*.' ),
					),
					'then'  => $t( 'Access ends immediately. The old key stops working; generate a new one next time.' ),
				) ),
			),
		),

		'cache_manual'    => array(
			array(
				'slug'  => '',
				'title' => $t( 'Clearing the cache when something looks stale' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'BlueWorx > Cache' ),
					'steps' => array(
						$t( 'Press *Refresh cache now*.' ),
						$t( 'Wait for the confirmation.' ),
						$t( 'Reload the page that looked wrong.' ),
					),
					'then'  => $t( 'Visitors see the current version. If it still looks old, reload once more with Ctrl+Shift+R (Cmd+Shift+R on a Mac) — your own browser keeps a copy too.' ),
				) ),
			),
		),

		'cache_auto'      => array(
			array(
				'slug'  => '',
				'title' => $t( 'What clears on its own when you publish' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'Any page or post' ),
					'steps' => array(
						$t( 'Press *Publish* or *Save* as normal.' ),
					),
					'then'  => $t( 'The cached copy of that page is thrown away for you, so what you just changed is what people see. You only need the Cache screen when something else changed — a menu, a theme setting, a plugin.' ),
				) ),
			),
		),

		'menu_editor'     => array(
			array(
				'slug'  => '',
				'title' => $t( 'Reordering the sidebar' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'BlueWorx > Edit Menu' ),
					'steps' => array(
						$t( 'Drag an item up or down the list.' ),
						$t( 'Press *Save changes*.' ),
					),
					'then'  => $t( 'The sidebar changes for everyone on the site, not just you.' ),
				) ),
			),
			array(
				'slug'  => 'hiding',
				'title' => $t( 'Hiding things nobody uses' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'BlueWorx > Edit Menu' ),
					'steps' => array(
						$t( 'Drag the item into *Hidden*, or use its arrows to move it there.' ),
						$t( 'Press *Save changes*.' ),
					),
					'then'  => $t( 'Hiding is tidying, not a lock: anyone who knows the address can still get there. To stop somebody doing something, change their role instead.' ),
				) ),
			),
		),

		'view_as_role'    => array(
			array(
				'slug'  => '',
				'title' => $t( 'Checking what an editor can see' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'The foot of the sidebar, above Log Out' ),
					'steps' => array(
						$t( 'Press *My own view* and choose a role.' ),
						$t( 'Click around the admin area as that person would.' ),
						$t( 'Press *My own view* again when you are done.' ),
					),
					'then'  => $t( 'You see less, never more, so nothing you do here can affect access. If a role cannot reach something it should, change the role on Users > Roles or ask BlueWorx.' ),
				) ),
			),
		),

		'content_tools'   => array(
			array(
				'slug'  => '',
				'title' => $t( 'Duplicating a page' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'Pages, or Posts' ),
					'steps' => array(
						$t( 'Hover over the page in the list and press *Duplicate*.' ),
						$t( 'Open the new draft — it has "(copy)" in the title.' ),
						$t( 'Change the title and the address (*Slug*) in the panel on the right, then edit the content.' ),
						$t( 'Press *Publish* when it is ready.' ),
					),
					'then'  => $t( 'The copy is a draft until you publish it, so nothing is live by accident.' ),
				) ),
			),
			array(
				'slug'  => 'external-link',
				'title' => $t( 'Pointing a menu entry at another site' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'Pages' ),
					'steps' => array(
						$t( 'Open the page that sits in the menu, or create a new one with just a title.' ),
						$t( 'In the panel on the right, open *Link to another site* and paste the full address into *External address*, starting https://.' ),
						$t( 'Press *Save*.' ),
					),
					'then'  => $t( 'Anyone opening that page, from the menu or a link, is sent to the other site instead.' ),
				) ),
			),
		),

		'media_tools'     => array(
			array(
				'slug'  => '',
				'title' => $t( 'Replacing a file without breaking links' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'Media > Library' ),
					'steps' => array(
						$t( 'Click the file you want to replace.' ),
						$t( 'Under *Replace file*, choose the new version.' ),
						$t( 'Press *Replace*.' ),
					),
					'then'  => $t( 'The address stays the same, so every page, link and email pointing at the old file now shows the new one. Do not delete and re-upload — that gives the file a new address and breaks every link to it.' ),
				) ),
			),
			array(
				'slug'  => 'svg',
				'title' => $t( 'Uploading a logo as SVG' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'Media > Add New' ),
					'steps' => array(
						$t( 'Drop the .svg file onto the page, or press *Select Files*.' ),
					),
					'then'  => $t( 'If it is refused, your role is not allowed SVG uploads — an administrator can allow it under BlueWorx > Enhancements > Media tools. Every SVG is cleaned of anything that could run, so it is safe to use once it is in.' ),
				) ),
			),
		),

		'page_excerpts'   => array(
			array(
				'slug'  => '',
				'title' => $t( 'Writing the summary that search results show' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'Pages' ),
					'steps' => array(
						$t( 'Open the page.' ),
						$t( 'In the panel on the right, press *Add an excerpt…* and write one or two sentences.' ),
						$t( 'Press *Save*.' ),
					),
					'then'  => $t( 'Search results, link previews and listings use this instead of the first few lines of the page.' ),
				) ),
			),
		),

		'translate'       => array(
			array(
				'slug'  => '',
				'title' => $t( 'Choosing which languages the button offers' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'BlueWorx > Enhancements > Translation' ),
					'steps' => array(
						$t( 'Open *On-page translation*.' ),
						$t( 'Tick the languages to offer and choose which corner the button sits in.' ),
						$t( 'Press *Save Changes*.' ),
					),
					'then'  => $t( 'The button appears on the front of the site in Chrome and Edge. Other browsers do not show it, and search engines always read your original words.' ),
				) ),
			),
			array(
				'slug'  => 'excluding',
				'title' => $t( 'Keeping something out of translation' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'BlueWorx > Enhancements > Translation' ),
					'steps' => array(
						$t( 'Open *On-page translation*.' ),
						$t( 'Under *Never translate (one CSS selector per line)*, add a CSS selector for the text to leave alone — for example .price or #legal-notice.' ),
						$t( 'Press *Save Changes*.' ),
					),
					'then'  => $t( 'Anything matching that selector keeps its original wording. Code blocks and anything already marked notranslate are always left alone too.' ),
				) ),
			),
		),
	);
}

/**
 * Gets the guides for everyday WordPress tasks.
 *
 * These are the questions that come up on every site regardless of which
 * features are switched on, so they are not gated on anything.
 *
 * @return array List of guides.
 */
function blueworx_get_wordpress_basics_guides() {
	return array(
		array(
			'id'    => 'basics-pages-and-posts',
			'title' => __( 'Pages and posts: which to use', 'blueworx-labs-wordpress' ),
			'tab'   => 'getting-started',
			'body'  => '<p>' . esc_html__( 'A page is a fixed part of the site — About, Contact, Services. A post is a dated entry in a series, such as news or a blog.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'The rule of thumb: if it belongs in the navigation menu and will still be there next year, make it a page. If it is one of many similar items that arrive over time, make it a post.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'    => 'basics-publishing',
			'title' => __( 'Saving, previewing and publishing', 'blueworx-labs-wordpress' ),
			'tab'   => 'getting-started',
			'body'  => '<p>' . esc_html__( 'Save draft keeps your work private. Preview shows how it will look without publishing it. Publish makes it live for everyone.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'To schedule something instead, click the date next to Publish and pick a future time — the wording changes to Schedule. To take a live page down without deleting it, switch its status to Draft.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'    => 'basics-revisions',
			'title' => __( 'Undoing a change you regret', 'blueworx-labs-wordpress' ),
			'tab'   => 'getting-started',
			'body'  => '<p>' . esc_html__( 'WordPress keeps earlier versions of your pages and posts. Open the item, find Revisions in the settings panel on the right, and step back through the versions until you find the one you want.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'Deleted the whole thing? Look in Trash on the Pages or Posts list. Items stay there for 30 days before WordPress removes them for good.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'    => 'basics-images',
			'title' => __( 'Adding images well', 'blueworx-labs-wordpress' ),
			'tab'   => 'getting-started',
			'body'  => '<p>' . esc_html__( 'Resize a photo before uploading it. A picture straight off a phone is far larger than any screen needs and is the most common reason a site feels slow.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'Always fill in the alt text. It is what a blind visitor hears in place of the image, and what search engines read. Describe what the image shows, in a sentence, as if to someone on the phone.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'The featured image is the one used in listings and link previews. Set it in the settings panel on the right; without one, those places may show nothing at all.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'    => 'basics-menus',
			'title' => __( 'Changing the site navigation', 'blueworx-labs-wordpress' ),
			'tab'   => 'getting-started',
			'body'  => '<p>' . esc_html__( 'Publishing a page does not add it to the menu — the two are separate on purpose, so you can build pages before they are announced.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'Find the navigation under Appearance, add the page to the menu, drag it into position, and save. Dragging an item slightly to the right nests it underneath the one above as a dropdown.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'    => 'basics-users',
			'title' => __( 'Adding someone to the site', 'blueworx-labs-wordpress' ),
			'tab'   => 'getting-started',
			'body'  => '<p>' . esc_html__( 'Go to Users > Add New. Give each person their own account rather than sharing one — it is safer, and you can see who changed what.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'Give the lowest role that lets them do their job. Author for someone writing their own posts, Editor for someone managing everyone\'s content, Administrator only for people who should be able to install plugins and change settings.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'When someone leaves, delete the account rather than changing its password. WordPress will offer to reassign their content to someone else first.', 'blueworx-labs-wordpress' ) . '</p>',
		),
		array(
			'id'    => 'basics-updates',
			'title' => __( 'Updates, and why they matter', 'blueworx-labs-wordpress' ),
			'tab'   => 'getting-started',
			'body'  => '<p>' . esc_html__( 'Most attacks on WordPress sites use a known weakness in an out-of-date plugin. Updates are the single most effective thing you can do to stay safe.', 'blueworx-labs-wordpress' ) . '</p>'
				. '<p>' . esc_html__( 'If BlueWorx maintains this site, updates are handled for you and you can ignore the badges. Otherwise apply them regularly, and check the site afterwards — look at the home page and one or two key pages.', 'blueworx-labs-wordpress' ) . '</p>',
		),
	);
}

/**
 * The capability a tab's guides actually describe.
 *
 * The role pills on a guide are the user-facing answer to "can I do this?", so
 * they are worked out from capabilities rather than written down: a site that
 * has added a Shop manager role, or taken upload_files off Authors, gets pills
 * that match its own setup instead of ours.
 *
 * @param string $tab Tab id.
 * @return string Capability name.
 */
function blueworx_guide_tab_capability( $tab ) {
	$map = array(
		'getting-started' => 'read',
		'security'        => 'manage_options',
		'content'         => 'edit_posts',
		'media'           => 'upload_files',
		'translation'     => 'edit_posts',
		'notifications'   => 'manage_options',
		'performance'     => 'manage_options',
		'admin_menu'      => 'manage_options',
		'appearance'      => 'edit_theme_options',

		// The other products' topics. Same rule: the capability somebody needs
		// to do the thing the guide describes, so the role pills on the card are
		// worked out from this site's own roles rather than written down.
		'wp-writing'      => 'edit_posts',
		'wp-media'        => 'upload_files',
		'wp-people'       => 'list_users',
		'wp-upkeep'       => 'update_core',
		'sc-products'     => 'manage_options',
		'sc-orders'       => 'manage_options',
		'sc-payments'     => 'manage_options',
		'sf-forms'        => 'edit_posts',
		'sf-spam'         => 'manage_options',
	);

	/**
	 * Filters the capability a guide tab describes.
	 *
	 * @param string $capability Capability name.
	 * @param string $tab        Tab id.
	 */
	return (string) apply_filters(
		'blueworx_guide_tab_capability',
		isset( $map[ $tab ] ) ? $map[ $tab ] : 'manage_options',
		$tab
	);
}

/**
 * The roles on this site that hold a capability.
 *
 * Administrator is listed first when it is in the set — it is the answer most
 * people are looking for, and the design gives it its own pill.
 *
 * @param string $capability Capability name.
 * @return array Role display names, keyed by slug.
 */
function blueworx_roles_with_capability( $capability ) {
	$roles = array();

	foreach ( get_editable_roles() as $slug => $role ) {
		if ( empty( $role['capabilities'][ $capability ] ) ) {
			continue;
		}

		$roles[ $slug ] = translate_user_role( $role['name'] );
	}

	if ( isset( $roles['administrator'] ) ) {
		$admin = $roles['administrator'];
		unset( $roles['administrator'] );
		$roles = array( 'administrator' => $admin ) + $roles;
	}

	return $roles;
}

/**
 * How long a guide takes to read, in whole minutes.
 *
 * @param string $body Guide body HTML.
 * @return int Minutes, never less than one.
 */
function blueworx_guide_read_time( $body ) {
	$words = str_word_count( wp_strip_all_tags( (string) $body ) );

	return max( 1, (int) ceil( $words / 200 ) );
}

/**
 * Renders a capped list of role pills, the rest behind a "+N more" dropdown.
 *
 * Two pills, then a button that opens the remainder in a small panel under
 * itself — three things in the row at most, counting the button. It used to
 * unfold them into the row instead, which pushed a page header's title sideways
 * and wrapped a guide card's footer onto three lines the moment anybody asked
 * who else could do the thing.
 *
 * The overflow roles are always in the markup, and the group still carries the
 * full list as its title, so with the script absent hovering it still answers
 * the question rather than leaving a button that does nothing.
 *
 * @param array  $roles Role display names, keyed by slug.
 * @param string $key   Unique key for this list, so two lists on one screen
 *                      open independently.
 * @param int    $cap   How many to show before the dropdown.
 * @return string HTML.
 */
function blueworx_ds_role_pills( $roles, $key, $cap = 2 ) {
	if ( empty( $roles ) ) {
		return '';
	}

	$names = array_values( $roles );
	$slugs = array_keys( $roles );
	$pills = '';

	foreach ( array_slice( $names, 0, $cap ) as $index => $name ) {
		$pills .= sprintf(
			'<span class="bw-rolepill%1$s">%2$s</span>',
			'administrator' === $slugs[ $index ] ? ' bw-rolepill--admin' : '',
			esc_html( $name )
		);
	}

	$extra = array_slice( $names, $cap );

	if ( ! empty( $extra ) ) {
		$more = sprintf(
			/* translators: %d: how many further roles there are. */
			__( '+%d more', 'blueworx-labs-wordpress' ),
			count( $extra )
		);

		// Derived from the caller's key rather than counted up, so the same list
		// gets the same id on every render and two lists on one screen never
		// collide.
		$panel_id = 'bw-roledrop-' . substr( md5( $key ), 0, 8 );
		$hidden   = '';

		foreach ( $extra as $name ) {
			$hidden .= '<span class="bw-rolepill">' . esc_html( $name ) . '</span>';
		}

		$pills .= sprintf(
			'<span class="bw-rolemore">'
				. '<button type="button" class="bw-rolepill bw-rolepill--more" data-blueworx-roles-more aria-expanded="false" aria-haspopup="true" aria-controls="%1$s">%2$s</button>'
				. '<span class="bw-roledrop" id="%1$s" data-blueworx-roles-extra hidden>%3$s</span>'
			. '</span>',
			esc_attr( $panel_id ),
			esc_html( $more ),
			$hidden
		);
	}

	return sprintf(
		'<span class="bw-rolepills" data-blueworx-roles="%1$s" title="%2$s">%3$s</span>',
		esc_attr( $key ),
		esc_attr( implode( ', ', $names ) ),
		$pills
	);
}
