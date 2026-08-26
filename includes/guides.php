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
		'blueworx'  => __( 'BlueWorx', 'blueworx-labs-wordpress' ),
		'wordpress' => __( 'WordPress', 'blueworx-labs-wordpress' ),
	);

	if ( blueworx_guide_product_is_active( 'surecart' ) ) {
		$products['surecart'] = __( 'SureCart', 'blueworx-labs-wordpress' );
	}

	if ( blueworx_guide_product_is_active( 'sureforms' ) ) {
		$products['sureforms'] = __( 'SureForms', 'blueworx-labs-wordpress' );
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
 * Every feature in the registry gets one. Where no written guide exists yet the
 * feature's own settings-page description is used, so a newly registered
 * feature is never missing from this page — it just starts brief.
 *
 * @return array List of guides.
 */
function blueworx_get_feature_guides() {
	$bodies = blueworx_get_feature_guide_bodies();
	$guides = array();

	foreach ( blueworx_get_feature_definitions() as $key => $feature ) {
		if ( ! blueworx_feature_enabled( $key ) ) {
			continue;
		}

		$body = isset( $bodies[ $key ] ) ? $bodies[ $key ] : '<p>' . esc_html( $feature['description'] ) . '</p>';

		$guides[] = array(
			'id'      => 'feature-' . $key,
			'title'   => $feature['label'],
			'tab'     => $feature['section'],
			'body'    => $body,
			'feature' => $key,
		);
	}

	return $guides;
}

/**
 * Gets the written guide body for each feature, keyed by feature key.
 *
 * @return array Guide bodies keyed by feature key.
 */
function blueworx_get_feature_guide_bodies() {
	return array(
		'login'                 => '<p>' . esc_html__( 'The standard WordPress sign-in address is replaced with one only your team knows. Anyone visiting the old address is sent away, which stops the automated attacks that hammer it around the clock.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'Your current sign-in address is shown at the top of BlueWorx > Enhancements. Bookmark it. Change the slug there if it is ever shared outside the team, and tell everyone the new address before you save — the old one stops working immediately.', 'blueworx-labs-wordpress' ) . '</p>',

		'site_protection'       => '<p>' . esc_html__( 'Keeps the site private. Visitors who are not signed in, or who do not hold one of the roles you pick, cannot see the site at all.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'Use it for a site in build, a staging copy, or a members-only area. Choose the front of the site, the admin area, or both, then tick the roles allowed through. Take care not to lock out the role you are signed in with.', 'blueworx-labs-wordpress' ) . '</p>',

		'sso'                   => '<p>' . esc_html__( 'Lets people sign in with an account they already have somewhere else — a company Google or Microsoft account, or a membership system — instead of a separate password here.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'Your provider gives you three things: an address, a client ID and a client secret. Paste them in, and give the provider the return address shown on the settings screen. The connection line tells you whether the two ends can see each other.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'There are two buttons: one to sign in, one to join. Signing in finds an existing account and never makes a new one, so somebody who has not joined yet is sent to your joining page rather than into a fresh, empty account. Joining is the button that may create one — decide whether it should, and which role a newcomer lands on. Neither can ever make someone an administrator, whatever the provider says.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'The sign-in button is added to the login screen for you. Put the joining button on a page with the shortcode shown on the settings screen, and set where each of them lands afterwards.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'If a sign-in fails, the person only sees a general message — telling them why would help an attacker. The real reason is listed under Recent sign-ins on the settings screen.', 'blueworx-labs-wordpress' ) . '</p>',

		'support_access'        => '<p>' . esc_html__( 'Lets BlueWorx look at your site to help with a problem, without you sharing a password or creating an account.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'Nothing is open until you act. Generate a key, send it to us, and switch the window on. Access is read-only and expires after 24 hours on its own. You can close it early at any time by switching the window off.', 'blueworx-labs-wordpress' ) . '</p>',

		'user_roles'            => '<p>' . esc_html__( 'Roles are listed alphabetically when you add or edit a user, which makes the one you want easier to find on a site with many roles.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'It also lets one person hold more than one role at once — useful where someone needs, say, both shop manager and editor without you building a custom role for the combination.', 'blueworx-labs-wordpress' ) . '</p>',

		'application_passwords' => '<p>' . esc_html__( 'Application Passwords let an outside app connect to this site as a particular user. They are hidden by default because most people never need them and they are a common way in for an attacker.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'Switch this on only when an integration asks for one, and only administrators will see the option on their profile. Delete the password from the profile screen as soon as the integration is retired.', 'blueworx-labs-wordpress' ) . '</p>',

		'comments'              => '<p>' . esc_html__( 'Turns comments off across the whole site and removes the comment areas from the admin screens, so nobody is left moderating spam on a site that never wanted comments.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'Existing comments are hidden rather than deleted. Switch this off again and they come back.', 'blueworx-labs-wordpress' ) . '</p>',

		'page_excerpts'         => '<p>' . esc_html__( 'Adds the Excerpt box to Pages, which WordPress normally offers on Posts only.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'An excerpt is the short summary used in search results, link previews and listings. Without one those places fall back to the first few lines of the page, which often reads badly. If you cannot see the box, open the three-dot menu at the top right of the editor, choose Preferences, and turn Excerpt on.', 'blueworx-labs-wordpress' ) . '</p>',

		'translate'             => '<p>' . esc_html__( 'Adds a floating language button so a visitor can read the site in their own language.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'The translation happens on the visitor\'s own device, so nothing is sent anywhere and the site stays fast. It works in Chrome and Edge; other browsers simply do not see the button. Search engines always index your original wording, so this cannot affect your rankings.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'Choose which languages to offer, where the button sits, and any pages to leave out, in the settings under this feature.', 'blueworx-labs-wordpress' ) . '</p>',

		'emails'                => '<p>' . esc_html__( 'WordPress emails the site administrator whenever a user changes their password, a plugin updates, and so on. On a busy site that is a lot of mail nobody reads.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'This stops those routine notifications. Genuine mail your site sends — order confirmations, contact form messages, password resets to the person who asked — is untouched.', 'blueworx-labs-wordpress' ) . '</p>',

		'profile_cleanup'       => '<p>' . esc_html__( 'Hides the parts of the user profile screen that nobody uses, along with the Elementor AI and Elementor Notes panels.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'Purely tidying. Nothing is deleted and no setting changes — the options are simply out of the way.', 'blueworx-labs-wordpress' ) . '</p>',

		'cache_auto'            => '<p>' . esc_html__( 'A cache keeps a ready-made copy of your pages so they load quickly. The catch is that after you edit something, visitors can keep seeing the old copy.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'This clears the relevant cache automatically whenever you publish or update a page or post, so what you just changed is what people see.', 'blueworx-labs-wordpress' ) . '</p>',

		'cache_manual'          => '<p>' . esc_html__( 'Adds BlueWorx > Cache, with a button that clears the whole cache on demand.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'Reach for it when something looks stale and you cannot work out why — after a theme change, a plugin update, or an edit made straight in the database. Clearing the cache is safe; the site simply rebuilds its copies as people visit.', 'blueworx-labs-wordpress' ) . '</p>',

		'menu_editor'           => '<p>' . esc_html__( 'Adds BlueWorx > Edit Menu, where you decide what the admin sidebar looks like for everyone on the site.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'Drag items into the order that suits how you work, hide the ones nobody uses, or move rarely needed items into More to shorten the list. Hiding an item does not remove the feature — anyone who knows the address can still reach it — so treat it as tidying, not as a permission.', 'blueworx-labs-wordpress' ) . '</p>',

		'admin_theme'           => '<p>' . esc_html__( 'Restyles the admin and sign-in screens with the BlueWorx look.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'Appearance only — every screen works exactly as it does normally. Switch it off at any time to go back to the standard WordPress look.', 'blueworx-labs-wordpress' ) . '</p>',

		'login_session'         => '<p>' . esc_html__( 'Decides how long somebody stays signed in before WordPress asks for their password again. Out of the box that is two days, which on a site people dip in and out of feels like being logged out constantly.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'Pick a length that matches how the site is used. A longer session is more convenient and slightly less safe on a shared computer, so choose "Until they sign out" only where everyone has their own machine. The change applies the next time each person signs in.', 'blueworx-labs-wordpress' ) . '</p>',

		'login_redirect'        => '<p>' . esc_html__( 'Booking, shop and membership plugins often take over where people land after signing in, so an editor who signs in to write a page arrives on a bookings dashboard or a shop account page instead.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'This puts anyone who works in the admin area on the dashboard instead. Customers are left where the shop or booking plugin wanted them, and a link that asked for a particular page still goes to that page.', 'blueworx-labs-wordpress' ) . '</p>',

		'view_as_role'          => '<p>' . esc_html__( 'Lets you look at the admin area the way one of your other roles sees it, so you can check what an editor or a member can actually reach before you tell them.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'The control sits at the foot of the sidebar, above Log Out. It says which view you are in and puts you back with one click. You are only offered the roles below your own, and it can only ever show you less than you normally see, never more, so it cannot be used to gain access to anything.', 'blueworx-labs-wordpress' ) . '</p>',

		'display_names'         => '<p>' . esc_html__( 'Plugins and roles are named after the product they came from, not the job they do. Somebody looking for the shop finds "SureCart", and a customer account is called a "Subscriber", which means nothing to anyone who did not install it.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'This renames them on screen: SureCart reads as Commerce, LatePoint as Bookings, SureForms as Forms Builder, SureDash as Dashboards, and Subscriber as Customer. The four WordPress roles — Administrator, Editor, Author and Contributor — already say what they are and are left alone.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'It changes the words and nothing else. Nobody gains or loses access, no plugin behaves differently, and nothing is written into those plugins — switch it off and every original name is back on the next page. The one place a name can still show through is inside a plugin\'s own buttons and messages, which belong to that plugin.', 'blueworx-labs-wordpress' ) . '</p>',

		'xmlrpc'                => '<p>' . esc_html__( 'XML-RPC is an old way of posting to WordPress from another program. Almost nobody uses it any more, but attackers do: it lets them try hundreds of passwords in a single request.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'Leave this on. Turn it off only if you publish from the WordPress mobile app or use Jetpack, both of which need it.', 'blueworx-labs-wordpress' ) . '</p>',

		'author_slugs'          => '<p>' . esc_html__( 'By default a WordPress author page has the person\'s sign-in name in its address, which hands an attacker half of what they need to get in.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'This replaces that name with a meaningless code. It is off by default because it changes those addresses, so switch it on early in a site\'s life rather than after the pages have been shared or indexed.', 'blueworx-labs-wordpress' ) . '</p>',

		'rest_users'            => '<p>' . esc_html__( 'WordPress publishes the list of everyone with an account on the site, readable by anyone, with no sign-in needed. It gives away the names people sign in with, which is half of what someone needs to break in.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'This closes that off, so only people who are signed in and allowed to manage users can read it. Leave it on. Turn it off only if something outside the site — a separate app or a directory page — genuinely needs to read your list of users.', 'blueworx-labs-wordpress' ) . '</p>',

		'content_tools'         => '<p>' . esc_html__( 'Adds a Duplicate link beside every page and post. It makes a complete copy as a draft — the content, the categories, and all the field values — so you can build the next one from a page that already works rather than starting again.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'The copy is always a draft, and nothing is published until you say so. There is also an optional setting that lets a page point at an address on another site, for when a menu entry needs to send people somewhere else entirely.', 'blueworx-labs-wordpress' ) . '</p>',

		'revisions'             => '<p>' . esc_html__( 'WordPress keeps a copy of a page every time you save it, forever. On a site that is edited often that quietly becomes the largest thing in the database and slows everything down.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'This keeps the most recent ones and lets the older ones go. Twenty is plenty for undoing a mistake. Copies already saved are left alone; the limit applies from now on.', 'blueworx-labs-wordpress' ) . '</p>',

		'robots_txt'            => '<p>' . esc_html__( 'robots.txt is the short file that tells search engines which parts of the site to look at. This gives you a box to edit it in rather than needing access to the server.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'Be careful: one wrong line here can remove the site from Google. If an SEO plugin is already managing this, leave it switched off and let that plugin do it. After saving, open /robots.txt in a browser and check it says what you expect.', 'blueworx-labs-wordpress' ) . '</p>',

		'media_tools'           => '<p>' . esc_html__( 'Three things at once. You can replace a file with a new version without the address changing, so every page already using it updates by itself instead of you hunting them down.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'Photos straight from a phone or camera are far larger than any screen needs, so oversized images are scaled down as they are uploaded — the page loads faster and nobody has to remember to resize anything.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'SVG logos can also be allowed, for chosen roles only. WordPress blocks them by default for a good reason: an SVG is a document that can carry code. Every one uploaded here is stripped of anything that could run, but still only allow it for people you trust with the whole site.', 'blueworx-labs-wordpress' ) . '</p>',

		'admin_bar'             => '<p>' . esc_html__( 'Tidies the black bar across the top of the screen: the WordPress logo, the Customize link, the update counter and the Help drawer all go, leaving the things your team actually uses.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'It can also hide that bar completely on the public side of the site, either for everyone but administrators or for the roles you choose, so a logged-in member sees the site the way a visitor does.', 'blueworx-labs-wordpress' ) . '</p>',

		'dashboard_widgets'     => '<p>' . esc_html__( 'Removes the dashboard panels nobody on your site uses, so the first screen after signing in shows what matters instead of WordPress news and an empty draft box.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'These are removed rather than hidden, so they stay gone for everybody instead of reappearing the moment someone opens Screen Options.', 'blueworx-labs-wordpress' ) . '</p>',
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
