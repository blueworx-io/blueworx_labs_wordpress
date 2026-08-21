<?php
/**
 * Guide registry for the Guides page.
 *
 * The page assembles itself rather than being maintained as a list of links.
 * Tabs come from the feature sections in features.php, and every feature in the
 * registry gets a guide slot, so a feature added there appears here without
 * anyone remembering to add it. A feature switched off in settings has its
 * guide hidden — a client should never be reading instructions for something
 * they cannot see.
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
 * Gets every guide to display, in tab order.
 *
 * @return array List of guides, each with id, title, tab, body and feature.
 */
function blueworx_get_guides() {
	$guides = array_merge( blueworx_get_wordpress_basics_guides(), blueworx_get_feature_guides() );

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

	return blueworx_normalize_guides( $guides );
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
	$tabs  = blueworx_get_guide_tabs();
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

		$clean[] = array(
			'id'      => $id,
			'title'   => $title,
			'tab'     => $tab,
			'body'    => $body,
			'feature' => $feature,
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

		'view_as_role'          => '<p>' . esc_html__( 'Lets you look at the admin area the way one of your other roles sees it, so you can check what an editor or a member can actually reach before you tell them.', 'blueworx-labs-wordpress' ) . '</p>'
			. '<p>' . esc_html__( 'A bar along the bottom of the screen shows which role you are viewing as and puts you back with one click. It can only ever show you less than you normally see, never more, so it cannot be used to gain access to anything.', 'blueworx-labs-wordpress' ) . '</p>',

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
