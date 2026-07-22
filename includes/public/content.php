<?php
/**
 * Public front-end layer — content data accessors.
 *
 * Every marketing page draws its copy and pricing from these accessors
 * instead of hard-coding it in a template. Values are ported verbatim from
 * the front-end design export's lib/data.ts (TOOLBOX_TOOLS, SOLO_PRICES,
 * TOOLBOX_PLANS, RETAINER_PLANS, FAQS, HOME_REVIEWS) — this file is a
 * transcription, not a redesign. The one deliberate difference: the source's
 * `btn` field (a raw CSS class string per plan) is dropped from every plan
 * array here, since a template choosing its own button classes belongs in
 * the template, not the data.
 *
 * Each accessor wraps its return value in
 * apply_filters( 'blueworx_content_<name>', $array ) so a later cycle can
 * override the content without editing this file.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The 12 Toolbox tools, each with exactly 6 features.
 *
 * Ported verbatim from lib/data.ts TOOLBOX_TOOLS. `popular` is true only for
 * `surecart` — every other tool omits the key entirely, matching the source
 * (an optional field there, not a `false` default).
 *
 * @return array List of tool arrays, each
 *   array( slug, name, desc, domain, category, popular, tagline, features[] ),
 *   where `features` is exactly 6 array( icon, title, desc ).
 */
function blueworx_content_tools() {
	$tools = array(
		array(
			'slug'     => 'sureforms',
			'name'     => 'SureForms',
			'desc'     => 'Flexible form builder with smart, multi-step flows.',
			'domain'   => 'sureforms.com',
			'category' => 'Build',
			'tagline'  => 'Build forms that convert, from simple contact forms to smart, multi-step flows with conditional logic.',
			'features' => array(
				array(
					'icon'  => 'workflow',
					'title' => 'Conditional logic',
					'desc'  => 'Show or hide fields based on what a visitor enters, so every form feels built just for them.',
				),
				array(
					'icon'  => 'palette',
					'title' => 'On-brand templates',
					'desc'  => 'Dozens of ready-made layouts that match your site design in a couple of clicks.',
				),
				array(
					'icon'  => 'chart',
					'title' => 'Submission insights',
					'desc'  => 'See completion rates and drop-off points so you can improve conversion over time.',
				),
				array(
					'icon'  => 'mail',
					'title' => 'Instant notifications',
					'desc'  => 'Route every submission to the right inbox, Slack channel, or CRM the moment it arrives.',
				),
				array(
					'icon'  => 'shield',
					'title' => 'Spam protection',
					'desc'  => 'Honeypots and smart filtering keep junk out without making visitors solve puzzles.',
				),
				array(
					'icon'  => 'plug',
					'title' => 'Payment fields',
					'desc'  => 'Take deposits or full payments right inside a form. No separate checkout needed.',
				),
			),
		),
		array(
			'slug'     => 'surerank',
			'name'     => 'SureRank',
			'desc'     => 'SEO insights to improve search visibility.',
			'domain'   => 'surerank.com',
			'category' => 'Grow',
			'tagline'  => 'Plain-English SEO guidance that shows you exactly what to fix to climb the search results.',
			'features' => array(
				array(
					'icon'  => 'gauge',
					'title' => 'Site health score',
					'desc'  => 'A single score tracks technical SEO issues across every page of your site.',
				),
				array(
					'icon'  => 'chart',
					'title' => 'Keyword tracking',
					'desc'  => 'Monitor rankings for the terms that matter most to your business.',
				),
				array(
					'icon'  => 'zap',
					'title' => 'One-click fixes',
					'desc'  => 'Resolve common on-page SEO issues without touching a line of code.',
				),
				array(
					'icon'  => 'doc',
					'title' => 'Content analysis',
					'desc'  => 'Page-by-page guidance on headings, length, and structure that search engines reward.',
				),
				array(
					'icon'  => 'workflow',
					'title' => 'Schema automation',
					'desc'  => 'Structured data added automatically so your pages earn rich results.',
				),
				array(
					'icon'  => 'users',
					'title' => 'Competitor snapshots',
					'desc'  => 'See who outranks you for key terms and exactly what they do differently.',
				),
			),
		),
		array(
			'slug'     => 'suremail',
			'name'     => 'SureMail',
			'desc'     => 'Reliable delivery for transactional and automated emails.',
			'domain'   => 'suremails.com',
			'category' => 'Grow',
			'tagline'  => 'Rock-solid delivery for every transactional and automated email your site sends.',
			'features' => array(
				array(
					'icon'  => 'mail',
					'title' => 'Guaranteed delivery',
					'desc'  => 'Emails route through a trusted infrastructure so they land in the inbox, not spam.',
				),
				array(
					'icon'  => 'chart',
					'title' => 'Delivery logs',
					'desc'  => 'See every email sent, opened, and clicked from one simple dashboard.',
				),
				array(
					'icon'  => 'shield',
					'title' => 'Built-in authentication',
					'desc'  => 'SPF, DKIM, and DMARC handled for you automatically.',
				),
				array(
					'icon'  => 'clock',
					'title' => 'Smart retries',
					'desc'  => 'Failed sends are queued and retried automatically, so nothing silently disappears.',
				),
				array(
					'icon'  => 'workflow',
					'title' => 'Multiple providers',
					'desc'  => 'Fall back across sending services for uptime even when one provider has issues.',
				),
				array(
					'icon'  => 'doc',
					'title' => 'Template management',
					'desc'  => 'Keep transactional email templates versioned, branded, and easy to update.',
				),
			),
		),
		array(
			'slug'     => 'surewriter',
			'name'     => 'SureWriter',
			'desc'     => 'AI-assisted website and marketing copy.',
			'domain'   => 'surewriter.com',
			'category' => 'Build',
			'tagline'  => 'AI-assisted copywriting that drafts on-brand website and marketing content in seconds.',
			'features' => array(
				array(
					'icon'  => 'sparkles',
					'title' => 'On-brand drafts',
					'desc'  => 'Generates copy tuned to your tone of voice, ready to refine and publish.',
				),
				array(
					'icon'  => 'zap',
					'title' => 'Rewrite & polish',
					'desc'  => 'Tighten, lengthen, or restyle existing copy without starting from scratch.',
				),
				array(
					'icon'  => 'doc',
					'title' => 'Every page type',
					'desc'  => 'Landing pages, product descriptions, emails, and social posts, covered.',
				),
				array(
					'icon'  => 'users',
					'title' => 'Audience presets',
					'desc'  => 'Tune drafts to a specific customer profile so copy speaks their language.',
				),
				array(
					'icon'  => 'chart',
					'title' => 'A/B variations',
					'desc'  => 'Generate multiple headline and CTA options to test what converts best.',
				),
				array(
					'icon'  => 'workflow',
					'title' => 'Brief-to-draft flow',
					'desc'  => 'Drop in a short brief and get a structured first draft, section by section.',
				),
			),
		),
		array(
			'slug'     => 'surecart',
			'name'     => 'SureCart',
			'desc'     => 'Modern checkout, subscriptions, and digital sales.',
			'domain'   => 'surecart.com',
			'category' => 'Sell',
			'popular'  => true,
			'tagline'  => 'A modern checkout and subscription engine for selling products, services, and digital downloads.',
			'features' => array(
				array(
					'icon'  => 'cart',
					'title' => 'Optimised checkout',
					'desc'  => 'A fast, distraction-free checkout built to lift conversion on every device.',
				),
				array(
					'icon'  => 'calendar',
					'title' => 'Subscriptions built in',
					'desc'  => 'Recurring billing, upgrades, and dunning management with no extra plugins.',
				),
				array(
					'icon'  => 'shield',
					'title' => 'PCI-compliant payments',
					'desc'  => 'Accept cards, wallets, and more with security handled for you.',
				),
				array(
					'icon'  => 'zap',
					'title' => 'One-page upsells',
					'desc'  => 'Post-purchase offers and order bumps that raise average order value.',
				),
				array(
					'icon'  => 'doc',
					'title' => 'Digital delivery',
					'desc'  => 'Secure, expiring download links for e-books, courses, and files.',
				),
				array(
					'icon'  => 'chart',
					'title' => 'Revenue analytics',
					'desc'  => 'MRR, churn, and lifetime value tracked without a spreadsheet in sight.',
				),
			),
		),
		array(
			'slug'     => 'zipwp',
			'name'     => 'ZipWP',
			'desc'     => 'AI-generated WordPress websites in minutes.',
			'domain'   => 'zipwp.com',
			'category' => 'Build',
			'tagline'  => 'Describe your business and get a complete, AI-generated WordPress website in minutes.',
			'features' => array(
				array(
					'icon'  => 'sparkles',
					'title' => 'AI site generation',
					'desc'  => 'Full pages, copy, and imagery generated from a short business description.',
				),
				array(
					'icon'  => 'palette',
					'title' => 'Instant redesigns',
					'desc'  => 'Regenerate the look and feel until it fits, before you touch a single setting.',
				),
				array(
					'icon'  => 'zap',
					'title' => 'Launch-ready in minutes',
					'desc'  => 'From idea to a live, editable website in one sitting.',
				),
				array(
					'icon'  => 'doc',
					'title' => 'Industry templates',
					'desc'  => 'Starting points tuned to your sector, from trades to consultants to cafés.',
				),
				array(
					'icon'  => 'workflow',
					'title' => 'Fully editable output',
					'desc'  => 'Everything generated lands in your normal editor. Nothing is locked in.',
				),
				array(
					'icon'  => 'chart',
					'title' => 'Built-in best practice',
					'desc'  => 'Generated pages follow proven layout and conversion patterns by default.',
				),
			),
		),
		array(
			'slug'     => 'ottokit',
			'name'     => 'OttoKit',
			'desc'     => 'Automated workflows connecting sites and tools.',
			'domain'   => 'ottokit.com',
			'category' => 'Automate',
			'tagline'  => 'Connect your site to the tools you already use with no-code automated workflows.',
			'features' => array(
				array(
					'icon'  => 'workflow',
					'title' => 'Visual workflow builder',
					'desc'  => 'Chain triggers and actions across apps without writing a single line of code.',
				),
				array(
					'icon'  => 'plug',
					'title' => 'Hundreds of integrations',
					'desc'  => 'Connect CRMs, spreadsheets, marketing tools, and more out of the box.',
				),
				array(
					'icon'  => 'clock',
					'title' => 'Runs in the background',
					'desc'  => 'Automations fire instantly and quietly, so nothing needs manual follow-up.',
				),
				array(
					'icon'  => 'shield',
					'title' => 'Error alerts',
					'desc'  => 'Get notified the moment a workflow fails, with a clear trail of what happened.',
				),
				array(
					'icon'  => 'chart',
					'title' => 'Run history',
					'desc'  => 'Every automation run is logged, so you can audit exactly what fired and when.',
				),
				array(
					'icon'  => 'zap',
					'title' => 'Conditional branches',
					'desc'  => 'Split workflows by customer type, order value, or any field you choose.',
				),
			),
		),
		array(
			'slug'     => 'ally',
			'name'     => 'Ally',
			'desc'     => 'Improves website accessibility and usability.',
			'domain'   => 'useally.io',
			'category' => 'Support',
			'tagline'  => 'Ongoing accessibility improvements so every visitor can use your site with ease.',
			'features' => array(
				array(
					'icon'  => 'shield',
					'title' => 'WCAG monitoring',
					'desc'  => 'Continuous scans flag accessibility issues as your site changes.',
				),
				array(
					'icon'  => 'zap',
					'title' => 'Guided remediation',
					'desc'  => 'Clear, prioritised steps to fix issues instead of a raw audit dump.',
				),
				array(
					'icon'  => 'users',
					'title' => 'Usability for everyone',
					'desc'  => 'Keyboard navigation, screen readers, and contrast handled properly.',
				),
				array(
					'icon'  => 'doc',
					'title' => 'Compliance reports',
					'desc'  => 'Shareable reports that document your accessibility posture for stakeholders.',
				),
				array(
					'icon'  => 'zap',
					'title' => 'Automatic alt text',
					'desc'  => 'Missing image descriptions flagged and suggested so nothing ships unlabeled.',
				),
				array(
					'icon'  => 'clock',
					'title' => 'Continuous coverage',
					'desc'  => 'New pages and edits are scanned as they go live, not just at audit time.',
				),
			),
		),
		array(
			'slug'     => 'sweet-ai',
			'name'     => 'Sweet AI',
			'desc'     => 'AI support for improving site content.',
			'domain'   => 'sweetai.com',
			'category' => 'Build',
			'tagline'  => 'An AI assistant that reviews and improves your site content for clarity and impact.',
			'features' => array(
				array(
					'icon'  => 'sparkles',
					'title' => 'Content suggestions',
					'desc'  => 'Surfaces specific rewrites for weak or unclear copy across your site.',
				),
				array(
					'icon'  => 'chart',
					'title' => 'Readability scoring',
					'desc'  => 'Flags dense or confusing passages before visitors bounce.',
				),
				array(
					'icon'  => 'zap',
					'title' => 'One-click apply',
					'desc'  => 'Accept a suggestion and it publishes straight to the live page.',
				),
				array(
					'icon'  => 'users',
					'title' => 'Tone matching',
					'desc'  => 'Suggestions respect your brand voice instead of flattening it.',
				),
				array(
					'icon'  => 'workflow',
					'title' => 'Site-wide review',
					'desc'  => 'Scan every page at once and work through improvements from one queue.',
				),
				array(
					'icon'  => 'shield',
					'title' => 'Human-in-the-loop',
					'desc'  => 'Nothing changes on your site until you approve it. AI proposes, you decide.',
				),
			),
		),
		array(
			'slug'     => 'elementor-ai-planner',
			'name'     => 'Elementor AI Planner',
			'desc'     => 'AI-guided website structure and planning.',
			'domain'   => 'elementor.com',
			'category' => 'Build',
			'tagline'  => 'AI-guided planning that maps out your site structure before you build a single page.',
			'features' => array(
				array(
					'icon'  => 'sparkles',
					'title' => 'Sitemap generation',
					'desc'  => 'Get a full page and navigation structure from a short project brief.',
				),
				array(
					'icon'  => 'workflow',
					'title' => 'Content outlines',
					'desc'  => 'Section-by-section outlines for every page, ready to hand to a writer.',
				),
				array(
					'icon'  => 'chart',
					'title' => 'Goal-based planning',
					'desc'  => 'Structures tuned to lead generation, sales, or content, based on your goal.',
				),
				array(
					'icon'  => 'users',
					'title' => 'Audience mapping',
					'desc'  => 'Plans pages around the questions your actual customers arrive with.',
				),
				array(
					'icon'  => 'doc',
					'title' => 'Exportable briefs',
					'desc'  => 'Hand a complete, structured brief to your designer or builder in one click.',
				),
				array(
					'icon'  => 'zap',
					'title' => 'Revise in seconds',
					'desc'  => 'Adjust the brief and regenerate the whole plan instantly, with no rework.',
				),
			),
		),
		array(
			'slug'     => 'elementor',
			'name'     => 'Elementor',
			'desc'     => 'Visual page building without code.',
			'domain'   => 'elementor.com',
			'category' => 'Build',
			'tagline'  => 'The visual page builder behind every custom layout we design, no code required.',
			'features' => array(
				array(
					'icon'  => 'palette',
					'title' => 'Drag-and-drop design',
					'desc'  => 'Build pixel-perfect layouts visually, with instant live preview.',
				),
				array(
					'icon'  => 'code',
					'title' => 'Theme building',
					'desc'  => 'Design headers, footers, and archive templates without touching code.',
				),
				array(
					'icon'  => 'zap',
					'title' => 'Fast, responsive output',
					'desc'  => 'Every layout adapts cleanly across desktop, tablet, and mobile.',
				),
				array(
					'icon'  => 'workflow',
					'title' => 'Global styles',
					'desc'  => 'Change a color or font once and it updates consistently across the site.',
				),
				array(
					'icon'  => 'plug',
					'title' => 'Deep integrations',
					'desc'  => 'Forms, popups, and dynamic content connect to the rest of your stack.',
				),
				array(
					'icon'  => 'clock',
					'title' => 'Revision history',
					'desc'  => 'Roll any page back to an earlier version whenever you need to.',
				),
			),
		),
		array(
			'slug'     => 'equalize-a11y-checker',
			'name'     => 'Equalize A11y Checker',
			'desc'     => 'Real-time WCAG accessibility checks.',
			'domain'   => 'equalizedigital.com',
			'category' => 'Support',
			'tagline'  => 'Real-time WCAG accessibility checks that catch issues as pages are edited.',
			'features' => array(
				array(
					'icon'  => 'shield',
					'title' => 'Real-time scanning',
					'desc'  => 'Flags accessibility issues the moment a page is edited or published.',
				),
				array(
					'icon'  => 'chart',
					'title' => 'Detailed reporting',
					'desc'  => 'Clear reports mapped to WCAG success criteria, not vague warnings.',
				),
				array(
					'icon'  => 'gauge',
					'title' => 'Compliance tracking',
					'desc'  => 'Track accessibility scores across your entire site over time.',
				),
				array(
					'icon'  => 'zap',
					'title' => 'In-editor warnings',
					'desc'  => 'Issues surface while editing, so problems are fixed before they publish.',
				),
				array(
					'icon'  => 'doc',
					'title' => 'Fix documentation',
					'desc'  => 'Every flagged issue links to plain-English guidance on how to resolve it.',
				),
				array(
					'icon'  => 'users',
					'title' => 'Team accountability',
					'desc'  => 'Assign issues to teammates and track them through to resolution.',
				),
			),
		),
	);

	/**
	 * Filters the Toolbox tools list.
	 *
	 * @param array $tools The 12 tool arrays.
	 */
	return apply_filters( 'blueworx_content_tools', $tools );
}

/**
 * Gets a single tool by slug.
 *
 * @param string $slug Tool slug, e.g. 'surecart'.
 * @return array|null The matching tool array, or null if no tool has that slug.
 */
function blueworx_content_tool( $slug ) {
	foreach ( blueworx_content_tools() as $tool ) {
		if ( $slug === $tool['slug'] ) {
			return $tool;
		}
	}

	return null;
}

/**
 * The solo monthly price (USD) for each Toolbox tool, keyed by slug.
 *
 * Ported verbatim from lib/data.ts SOLO_PRICES.
 *
 * @return array Slug => int price.
 */
function blueworx_content_solo_prices() {
	$prices = array(
		'sureforms'             => 9,
		'surerank'              => 16,
		'suremail'              => 8,
		'surewriter'            => 12,
		'surecart'              => 19,
		'zipwp'                 => 12,
		'ottokit'               => 17,
		'ally'                  => 29,
		'sweet-ai'              => 12,
		'elementor-ai-planner'  => 6,
		'elementor'             => 8,
		'equalize-a11y-checker' => 12,
	);

	/**
	 * Filters the Toolbox tools' solo prices.
	 *
	 * @param array $prices Slug => int price.
	 */
	return apply_filters( 'blueworx_content_solo_prices', $prices );
}

/**
 * The 3 Toolbox subscription plans.
 *
 * Ported from lib/data.ts TOOLBOX_PLANS, with the `btn` raw-CSS-class field
 * dropped — templates choose their own button classes.
 *
 * @return array List of array( name, desc, priceM, priceA, feat, pop, features[] ).
 */
function blueworx_content_toolbox_plans() {
	$plans = array(
		array(
			'name'     => 'Personal',
			'desc'     => 'For individuals and single-site projects.',
			'priceM'   => 30,
			'priceA'   => 25,
			'feat'     => false,
			'features' => array(
				'All 12+ premium tools',
				'Managed website hosting included',
				'1 website',
				'Learning Center access',
				'Site stability support',
			),
		),
		array(
			'name'     => 'Business',
			'desc'     => 'For businesses running one or more sites.',
			'priceM'   => 60,
			'priceA'   => 50,
			'feat'     => true,
			'pop'      => true,
			'features' => array(
				'All 12+ premium tools',
				'Managed website hosting included',
				'Up to 5 websites',
				'Learning Center access',
				'Priority support',
			),
		),
		array(
			'name'     => 'Agency',
			'desc'     => 'Bulk licensing for agencies and resellers.',
			'priceM'   => 200,
			'priceA'   => 160,
			'feat'     => false,
			'features' => array(
				'All 12+ premium tools',
				'Managed hosting for every site',
				'Up to 25 client websites',
				'Bulk licensing for client sites',
				'Dedicated account manager',
			),
		),
	);

	/**
	 * Filters the Toolbox subscription plans.
	 *
	 * @param array $plans The 3 plan arrays.
	 */
	return apply_filters( 'blueworx_content_toolbox_plans', $plans );
}

/**
 * The 3 retainer support plans.
 *
 * Ported from lib/data.ts RETAINER_PLANS, with the `btn` raw-CSS-class field
 * dropped — templates choose their own button classes.
 *
 * @return array List of array( name, desc, priceM, priceA, feat, pop, features[] ).
 */
function blueworx_content_retainer_plans() {
	$plans = array(
		array(
			'name'     => 'Essential Support',
			'desc'     => 'Designed for smaller businesses that require occasional updates and ongoing maintenance.',
			'priceM'   => 200,
			'priceA'   => 160,
			'feat'     => false,
			'features' => array(
				'Access to the free toolbox',
				'Basic template site',
				'3 small updates per year',
				'Up to 6 hours of expert design & developer support per month',
				'Minimum 1 year commitment',
			),
		),
		array(
			'name'     => 'Growth Support',
			'desc'     => 'Ideal for digital solutions that require regular updates, feature improvements, and ongoing development support.',
			'priceM'   => 500,
			'priceA'   => 400,
			'feat'     => true,
			'pop'      => true,
			'features' => array(
				'Access to free toolbox',
				'Customised template site',
				'1 major update per year',
				'3 minor updates per year',
				'Up to 12 hours of expert design & developer support per month',
			),
		),
		array(
			'name'     => 'Advanced Support',
			'desc'     => 'Designed for fully customised digital solutions requiring ongoing development and improvements.',
			'priceM'   => 750,
			'priceA'   => 600,
			'feat'     => false,
			'features' => array(
				'Access to free toolbox',
				'Completely customised site',
				'2 major updates per year',
				'3 minor updates per year',
				'Unlimited expert design & developer support',
			),
		),
	);

	/**
	 * Filters the retainer support plans.
	 *
	 * @param array $plans The 3 plan arrays.
	 */
	return apply_filters( 'blueworx_content_retainer_plans', $plans );
}

/**
 * The pricing FAQ list.
 *
 * Ported verbatim from lib/data.ts FAQS.
 *
 * @return array List of array( q, a ).
 */
function blueworx_content_faqs() {
	$faqs = array(
		array(
			'q' => 'How do payments work?',
			'a' => 'Pay and forget! Annual payments mean more time spent on your business and less time managing subscriptions. Choose monthly or annual billing at checkout, and you can switch at any point.',
		),
		array(
			'q' => 'How do I get started?',
			'a' => 'Pick a plan, create your account, and our team helps you onboard step by step. Most websites are designed, built, and live within a few days.',
		),
		array(
			'q' => 'Can I change my plan later?',
			'a' => 'Absolutely. Upgrade or downgrade at any time from your dashboard. Changes are prorated automatically so you only ever pay for what you use.',
		),
		array(
			'q' => 'Do I need to be a developer?',
			'a' => 'Not at all. BlueWorx is built for business owners. Our tools are no-code and our expert team handles anything technical on your behalf.',
		),
		array(
			'q' => 'Will I be able to edit my package?',
			'a' => 'Yes. Add tools, spin up new sites, and adjust your support allowance whenever your needs change. Your package flexes with your business.',
		),
	);

	/**
	 * Filters the pricing FAQ list.
	 *
	 * @param array $faqs List of array( q, a ).
	 */
	return apply_filters( 'blueworx_content_faqs', $faqs );
}

/**
 * The homepage customer review list.
 *
 * Ported verbatim from lib/data.ts HOME_REVIEWS.
 *
 * @return array List of array( text, initials, name, role ).
 */
function blueworx_content_reviews() {
	$reviews = array(
		array(
			'text'     => 'BlueWorx took our site off three separate platforms and put everything in one place. Hosting, tools, and support — it just works.',
			'initials' => 'H',
			'name'     => 'Hannah Whitfield',
			'role'     => 'Owner, Bloom & Co.',
		),
		array(
			'text'     => 'They rebuilt our booking flow and our conversions climbed within weeks. It genuinely feels like an extension of our own team.',
			'initials' => 'D',
			'name'     => 'Daniel Okafor',
			'role'     => 'Director, Padel365',
		),
		array(
			'text'     => 'Fast, reliable, and always one message away. The toolbox alone saved us hundreds a month in subscriptions.',
			'initials' => 'P',
			'name'     => 'Priya Nair',
			'role'     => 'Founder, Hirasté',
		),
		array(
			'text'     => 'Migrated our entire site with zero downtime. The support has been outstanding at every single step.',
			'initials' => 'M',
			'name'     => 'Marcus Reed',
			'role'     => 'CEO, QURE',
		),
	);

	/**
	 * Filters the homepage customer review list.
	 *
	 * @param array $reviews List of array( text, initials, name, role ).
	 */
	return apply_filters( 'blueworx_content_reviews', $reviews );
}
