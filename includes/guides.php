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
	$t = static function ( $text ) {
		return __( $text, 'blueworx-labs-wordpress' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
	};

	$guides = array(
		// ── Blog posts ──
		array(
			'id'      => 'wp-posts-writing',
			'title'   => $t( 'Writing and publishing a post' ),
			'tab'     => 'wp-posts',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'Posts > Add New' ),
				'steps' => array(
					$t( 'Type the title where it says *Add title*.' ),
					$t( 'Click below it and start writing. Press Enter for a new paragraph; press the black *+* for an image, heading or list.' ),
					$t( 'Press *Save draft* as you go.' ),
					$t( 'Press *Publish*, then *Publish* again to confirm.' ),
				),
				'then'  => $t( 'The post appears at the top of your blog or news page. The address is made from the title — change it under *URL* in the panel on the right before publishing, not after.' ),
			) ),
		),
		array(
			'id'      => 'wp-posts-featured-image',
			'title'   => $t( 'Adding a featured image' ),
			'tab'     => 'wp-posts',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'The post editor, panel on the right' ),
				'steps' => array(
					$t( 'Make sure the *Post* tab is chosen at the top of the panel, not *Block*.' ),
					$t( 'Open *Featured image* and press *Set featured image*.' ),
					$t( 'Upload a picture or choose one from the library, then press *Set featured image*.' ),
					$t( 'Press *Save* or *Publish*.' ),
				),
				'then'  => $t( 'This is the picture shown in listings and when the post is shared. Without one, those places may show nothing at all.' ),
			) ),
		),
		array(
			'id'      => 'wp-posts-categories',
			'title'   => $t( 'Putting a post in a category, and adding tags' ),
			'tab'     => 'wp-posts',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'The post editor, panel on the right' ),
				'intro' => $t( 'A category is a section of the blog — News, Events. A tag is a keyword — a place, a name, a product.' ),
				'steps' => array(
					$t( 'Open *Categories* and tick one. Press *Add Category* if the right one does not exist yet.' ),
					$t( 'Open *Tags*, type a word and press Enter. Reuse tags you already have rather than inventing spellings.' ),
					$t( 'Press *Save* or *Publish*.' ),
				),
				'then'  => $t( 'A post with no category lands in "Uncategorized", which visitors can see. One category per post is usually right; tags can be many.' ),
			) ),
		),
		array(
			'id'      => 'wp-posts-scheduling',
			'title'   => $t( 'Scheduling a post for later' ),
			'tab'     => 'wp-posts',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'The post editor, panel on the right' ),
				'steps' => array(
					$t( 'Next to *Publish*, click the date (it says *Immediately*).' ),
					$t( 'Pick the day and time.' ),
					$t( 'Press *Schedule*, then *Schedule* again to confirm.' ),
				),
				'then'  => $t( 'The post goes live on its own at that time. It shows as *Scheduled* in the Posts list until then. To change your mind, open it and change the date, or switch it back to Draft.' ),
			) ),
		),
		array(
			'id'      => 'wp-posts-editing',
			'title'   => $t( 'Editing a post that is already live' ),
			'tab'     => 'wp-posts',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'Posts' ),
				'steps' => array(
					$t( 'Hover over the post in the list and press *Edit*.' ),
					$t( 'Make your changes.' ),
					$t( 'Press *Save*.' ),
				),
				'then'  => $t( 'Visitors see the change immediately. The old version is kept — see "Undoing a change you regret" under Getting started.' ),
			) ),
		),
		array(
			'id'      => 'wp-posts-unpublishing',
			'title'   => $t( 'Taking a post down without deleting it' ),
			'tab'     => 'wp-posts',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'The post editor, panel on the right' ),
				'steps' => array(
					$t( 'Open the post.' ),
					$t( 'Under *Status*, choose *Draft*.' ),
					$t( 'Press *Save*.' ),
				),
				'then'  => $t( 'The post disappears from the site but is kept in your list, ready to publish again. Anyone with the old link sees a "not found" page.' ),
			) ),
		),
		array(
			'id'      => 'wp-posts-trash',
			'title'   => $t( 'Getting a post back from the trash' ),
			'tab'     => 'wp-posts',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'Posts' ),
				'steps' => array(
					$t( 'Press *Trash* in the row of links above the list.' ),
					$t( 'Hover over the post and press *Restore*.' ),
				),
				'then'  => $t( 'It comes back as a draft. Items in the trash are removed for good after 30 days.' ),
			) ),
		),
		array(
			'id'      => 'wp-posts-author',
			'title'   => $t( 'Changing who a post says wrote it' ),
			'tab'     => 'wp-posts',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'The post editor, panel on the right' ),
				'steps' => array(
					$t( 'Find *Author* in the panel on the right.' ),
					$t( 'Choose a name from the list.' ),
					$t( 'Press *Save*.' ),
				),
				'then'  => $t( 'Only people with an account on the site are listed. If the person is not there, add them first — see "Adding someone to the site".' ),
			) ),
		),

		// ── Pages ──
		array(
			'id'      => 'wp-writing-blocks',
			'title'   => $t( 'Building a page block by block' ),
			'tab'     => 'wp-writing',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'Pages > Add New' ),
				'intro' => $t( 'A page is built from blocks — a heading, a paragraph, an image, a button.' ),
				'steps' => array(
					$t( 'Press the black *+* at the top left, or type / on an empty line and start typing what you want.' ),
					$t( 'Click a block to select it; use the toolbar above it to move it up or down or delete it.' ),
					$t( 'Press *Save draft* often, and *View* to see it as a visitor would.' ),
					$t( 'Press *Publish* when it is ready.' ),
				),
				'then'  => $t( 'The page is live but not in the menu — see "Adding a page to the menu".' ),
			) ),
		),
		array(
			'id'      => 'wp-writing-links',
			'title'   => $t( 'Adding a link' ),
			'tab'     => 'wp-writing',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'The editor' ),
				'steps' => array(
					$t( 'Select the words that should be the link.' ),
					$t( 'Press Ctrl+K (Cmd+K on a Mac).' ),
					$t( 'For a page on this site, start typing its name and pick it from the list. For another site, paste the full address.' ),
					$t( 'Press Enter.' ),
				),
				'then'  => $t( 'Picking a page from the list, rather than pasting its address, means the link keeps working if that page is ever renamed.' ),
			) ),
		),
		array(
			'id'      => 'wp-writing-headings',
			'title'   => $t( 'Headings in the right order' ),
			'tab'     => 'wp-writing',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'The editor' ),
				'steps' => array(
					$t( 'Add a *Heading* block.' ),
					$t( 'In the toolbar above it, choose the level: H2 for a main section, H3 for a part of that section.' ),
					$t( 'Never skip a level — H2 then H4 because it looked the right size is the most common mistake.' ),
				),
				'then'  => $t( 'The page title is already the H1, so start at H2. Screen readers and search engines both use the order to understand the page. Change the size with a style, not the level.' ),
			) ),
		),
		array(
			'id'      => 'wp-writing-menu',
			'title'   => $t( 'Adding a page to the menu' ),
			'tab'     => 'wp-writing',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'Appearance > Menus' ),
				'steps' => array(
					$t( 'Tick the page under *Add menu items* and press *Add to Menu*.' ),
					$t( 'Drag it into position.' ),
					$t( 'Press *Save Menu*.' ),
				),
				'then'  => $t( 'If *Appearance > Menus* is not there, your site uses the Site Editor: go to *Appearance > Editor*, click the navigation, and add the page there.' ),
			) ),
		),

		// ── Media library ──
		array(
			'id'      => 'wp-media-uploads',
			'title'   => $t( 'Uploading an image' ),
			'tab'     => 'wp-media',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'Media > Add New' ),
				'steps' => array(
					$t( 'Drop the file onto the page, or press *Select Files*.' ),
					$t( 'Once it appears, click it and fill in *Alternative text* with what the picture shows.' ),
				),
				'then'  => $t( 'Upload the best version you have; the site makes the smaller copies. A decorative flourish can have empty alt text; a photograph of your team cannot.' ),
			) ),
		),
		array(
			'id'      => 'wp-media-alt',
			'title'   => $t( 'Writing alt text' ),
			'tab'     => 'wp-media',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'Media > Library' ),
				'steps' => array(
					$t( 'Click the image.' ),
					$t( 'In *Alternative text*, describe what is in it in one sentence, as if to someone on the phone.' ),
					$t( 'Click away — it saves on its own.' ),
				),
				'then'  => $t( 'Screen readers read it aloud and search engines read it too. Do not start with "Image of" — they already know it is an image.' ),
			) ),
		),
		array(
			'id'      => 'wp-media-replacing',
			'title'   => $t( 'Replacing a file people already have the link to' ),
			'tab'     => 'wp-media',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'Media > Library' ),
				'steps' => array(
					$t( 'Click the file.' ),
					$t( 'Under *Replace file*, choose the new version, then press *Replace*.' ),
				),
				'then'  => $t( 'The address stays the same, so a price list you emailed to two hundred people keeps working. Deleting and re-uploading gives the file a new address and nothing warns you the old links broke. If there is no *Replace file* field, ask BlueWorx to switch it on.' ),
			) ),
		),
		array(
			'id'      => 'wp-media-usage',
			'title'   => $t( 'Finding where an image is used' ),
			'tab'     => 'wp-media',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'Media > Library' ),
				'steps' => array(
					$t( 'Switch to the list view (the icon at the top left).' ),
					$t( 'Look at the *Uploaded to* column.' ),
				),
				'then'  => $t( 'That column only shows the page the image was first added from. An image placed on several pages is not tracked by WordPress — search the page content, or ask BlueWorx, before deleting one.' ),
			) ),
		),

		// ── Users & roles ──
		array(
			'id'      => 'wp-people-add',
			'title'   => $t( 'Adding somebody' ),
			'tab'     => 'wp-people',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'Users > Add New' ),
				'steps' => array(
					$t( 'Type their email address and a username.' ),
					$t( 'Choose a role.' ),
					$t( 'Press *Add User*.' ),
				),
				'then'  => $t( 'They get an email with a link to set their own password. Never type a password in and send it to them.' ),
			) ),
		),
		array(
			'id'      => 'wp-people-roles',
			'title'   => $t( 'Which role to give somebody' ),
			'tab'     => 'wp-people',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'Users' ),
				'steps' => array(
					$t( 'Writes their own posts only: *Author*.' ),
					$t( 'Edits and publishes everyone\'s pages and posts: *Editor*.' ),
					$t( 'Writes but should not publish: *Contributor*.' ),
					$t( 'Installs plugins, changes settings, removes people: *Administrator* — and only if they really must.' ),
				),
				'then'  => $t( 'Give the smallest role that lets somebody do their job. Most people who ask for admin need Editor. Every extra administrator is an extra way for the site to be taken over.' ),
			) ),
		),
		array(
			'id'      => 'wp-people-leaving',
			'title'   => $t( 'When somebody leaves' ),
			'tab'     => 'wp-people',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'Users' ),
				'steps' => array(
					$t( 'Hover over their name and press *Delete*.' ),
					$t( 'Choose *Attribute all content to* and pick another person.' ),
					$t( 'Press *Confirm Deletion*.' ),
				),
				'then'  => $t( 'Their pages and posts now belong to the person you chose. Delete the account rather than changing its password — a dormant account is a way in.' ),
			) ),
		),

		// ── Updates & health ──
		array(
			'id'      => 'wp-upkeep-updates',
			'title'   => $t( 'What to do when the update badge appears' ),
			'tab'     => 'wp-upkeep',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'Dashboard > Updates' ),
				'steps' => array(
					$t( 'If BlueWorx maintains the site, do nothing — we apply updates for you.' ),
					$t( 'Otherwise press *Update Now* for WordPress, then tick every plugin and press *Update Plugins*, then the same for themes.' ),
				),
				'then'  => $t( 'Updates are the single most effective thing you can do to stay safe. Do them regularly rather than letting them pile up.' ),
			) ),
		),
		array(
			'id'      => 'wp-upkeep-health',
			'title'   => $t( 'Checking the site after an update' ),
			'tab'     => 'wp-upkeep',
			'product' => 'wordpress',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'The front of the site, then Tools > Site Health' ),
				'steps' => array(
					$t( 'Open the home page and one or two pages people use most. Check they look right and a form or a button still works.' ),
					$t( 'Open *Tools > Site Health* and read anything marked *Critical*.' ),
				),
				'then'  => $t( 'Recommendations in Site Health can be ignored; only Critical needs action. If something looks broken, tell BlueWorx which plugin you updated.' ),
			) ),
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
		'wp-posts'   => __( 'Blog posts', 'blueworx-labs-wordpress' ),
		'wp-writing' => __( 'Pages', 'blueworx-labs-wordpress' ),
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
	$t = static function ( $text ) {
		return __( $text, 'blueworx-labs-wordpress' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
	};

	return array(
		array(
			'id'    => 'basics-pages-and-posts',
			'title' => $t( 'Pages and posts: which to use' ),
			'tab'   => 'getting-started',
			'body'  => blueworx_guide_body( array(
				'where' => $t( 'Pages, or Posts' ),
				'intro' => $t( 'A page is a fixed part of the site — About, Contact, Services. A post is a dated entry in a series, such as news or a blog.' ),
				'steps' => array(
					$t( 'Ask: will this still be in the menu next year? Then it is a page — go to *Pages > Add New*.' ),
					$t( 'Is it one of many similar items that arrive over time? Then it is a post — go to *Posts > Add New*.' ),
				),
				'then'  => $t( 'Posts appear on the blog or news page on their own. Pages appear only where you put them — see "Changing the site navigation".' ),
			) ),
		),
		array(
			'id'    => 'basics-publishing',
			'title' => $t( 'Saving, previewing and publishing' ),
			'tab'   => 'getting-started',
			'body'  => blueworx_guide_body( array(
				'where' => $t( 'The top right of the editor' ),
				'steps' => array(
					$t( 'Press *Save draft* to keep your work without showing it to anyone.' ),
					$t( 'Press *View* to see it exactly as a visitor would.' ),
					$t( 'Press *Publish* (or *Save* on something already live) when it is ready.' ),
				),
				'then'  => $t( 'Nothing is live until you press Publish or Save. To take a live page down without deleting it, change its status to Draft in the panel on the right.' ),
			) ),
		),
		array(
			'id'    => 'basics-revisions',
			'title' => $t( 'Undoing a change you regret' ),
			'tab'   => 'getting-started',
			'body'  => blueworx_guide_body( array(
				'where' => $t( 'The page or post you changed' ),
				'steps' => array(
					$t( 'Open the item.' ),
					$t( 'In the panel on the right, press *Revisions*.' ),
					$t( 'Drag the slider back until you see the version you want.' ),
					$t( 'Press *Restore this revision*, then *Save*.' ),
				),
				'then'  => $t( 'Deleted the whole thing? Look under *Trash* at the top of the Pages or Posts list. Items stay there for 30 days.' ),
			) ),
		),
		array(
			'id'    => 'basics-images',
			'title' => $t( 'Adding images well' ),
			'tab'   => 'getting-started',
			'body'  => blueworx_guide_body( array(
				'where' => $t( 'The editor' ),
				'steps' => array(
					$t( 'Press the black *+* and choose *Image*, then *Upload*.' ),
					$t( 'With the image selected, fill in *Alt text* in the panel on the right: say what is in the picture, as if to someone on the phone.' ),
					$t( 'For the picture that represents the whole page in listings and link previews, open *Featured image* in the panel on the right and set one.' ),
				),
				'then'  => $t( 'The site makes smaller copies of every image itself, so upload the best version you have. Alt text is what a blind visitor hears and what search engines read.' ),
			) ),
		),
		array(
			'id'    => 'basics-menus',
			'title' => $t( 'Changing the site navigation' ),
			'tab'   => 'getting-started',
			'body'  => blueworx_guide_body( array(
				'where' => $t( 'Appearance > Menus' ),
				'intro' => $t( 'Publishing a page does not add it to the menu — the two are separate on purpose.' ),
				'steps' => array(
					$t( 'Tick the page under *Add menu items* and press *Add to Menu*.' ),
					$t( 'Drag it into position. Drag it slightly to the right to tuck it under the item above as a dropdown.' ),
					$t( 'Press *Save Menu*.' ),
				),
				'then'  => $t( 'The change shows on the site straight away. If your site uses the block-based Site Editor instead, the menu is under *Appearance > Editor > Navigation* — ask BlueWorx if you are not sure which you have.' ),
			) ),
		),
		array(
			'id'    => 'basics-users',
			'title' => $t( 'Adding someone to the site' ),
			'tab'   => 'getting-started',
			'body'  => blueworx_guide_body( array(
				'where' => $t( 'Users > Add New' ),
				'steps' => array(
					$t( 'Type their email address and a username.' ),
					$t( 'Choose the smallest role that lets them do their job — see "Which role to give somebody".' ),
					$t( 'Leave *Send the new user an email about their account* ticked and press *Add User*.' ),
				),
				'then'  => $t( 'They get an email with a link to set their own password. Give each person their own account rather than sharing one, so you can see who changed what.' ),
			) ),
		),
		array(
			'id'    => 'basics-updates',
			'title' => $t( 'Updates, and why they matter' ),
			'tab'   => 'getting-started',
			'body'  => blueworx_guide_body( array(
				'where' => $t( 'Dashboard > Updates' ),
				'intro' => $t( 'Most attacks on WordPress sites use a known weakness in an out-of-date plugin.' ),
				'steps' => array(
					$t( 'If BlueWorx maintains this site, stop here — updates are done for you and you can ignore the badges.' ),
					$t( 'Otherwise, press *Update Now* for WordPress itself, then tick all plugins and press *Update Plugins*.' ),
					$t( 'Open the home page and one or two key pages to check they still look right.' ),
				),
				'then'  => $t( 'If something breaks after an update, tell BlueWorx which plugin you updated — that is the first thing we will ask.' ),
			) ),
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
		'wp-posts'        => 'edit_posts',
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
