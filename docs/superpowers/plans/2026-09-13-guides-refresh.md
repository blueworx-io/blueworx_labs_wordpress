# Guides Refresh Implementation Plan (PR 1 — this plugin)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn every guide on BlueWorx > Guides into a step-by-step task, trim the BlueWorx tab to client-facing features, add a Blog posts topic, fill out SureCart, and add LatePoint as a product.

**Architecture:** Guides stay as PHP arrays in `includes/guides.php`, rendered by `includes/admin-guides.php`. A new `blueworx_guide_body()` helper builds every body from `where` / `intro` / `steps` / `then` so the format cannot drift. Feature guides come from a per-feature *list* (`blueworx_get_feature_guide_tasks()`), gated by a new `'guide' => false` flag in the feature registry. Product labels and *Where* lines pass through one `blueworx_guide_product_label()` so Display names is honoured. Nothing about tabs, products, URLs, capability gating or the third-party filters changes.

**Tech Stack:** PHP 7.4+ WordPress plugin, standalone PHP tests under `tests/php/` (`php tests/php/<file>.php`, stubs in `tests/php/stubs.php`), Playwright against the local harness.

**Spec:** `docs/superpowers/specs/2026-09-13-guides-refresh-design.md`

ClubHouse (spec §6) and Forge (spec §7) are separate PRs in their own repos, planned after this one ships. They are not in this plan.

## Global Constraints

- Branch `guides-refresh`, never main. One PR.
- Version goes to **1.86.0** in `blueworx-labs-wordpress.php` (header line 6 and `BLUEWORX_LABS_VERSION` line 66), `package.json`, and `readme.txt` Stable tag; `CHANGELOG.md` gets a `## [1.86.0] - <date>` entry.
- No new dependencies.
- Text domain `blueworx-labs-wordpress`; every visible string through `__()` / `esc_html__()`.
- Guide voice (spec §1.3): plain words, second person, present tense, button labels in `*asterisks*` (rendered as `<em>`), British spelling, a step never explains why.
- Every guide body in this plugin is built with `blueworx_guide_body()`; `where`, `steps` and `then` are always present.
- Existing guide ids that other code or specs reference keep working: `feature-<key>` for the first guide of a feature, all `basics-*`, `wp-*`, `sc-*`, `sf-*` ids stay.
- Tab ids do not change (`wp-writing` keeps its id; only its label becomes "Pages").
- Run lint once at the end (`npm run lint`), present findings, do not loop.
- Playwright: local harness only. Start with `node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up --plugin .`, then `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8881 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw WP_LOGIN_PATH=admin_login npx playwright test <file> --workers=1 --reporter=list`. After `up`, delete the duplicate plugin symlink the harness makes (see memory `harness-duplicate-plugin-symlink`).
- Commit after every task. Commit messages: one plain line, ending with `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`.

---

## File map

| File | Change |
| ---- | ------ |
| `includes/guides.php` | Add `blueworx_guide_text()`, `blueworx_guide_body()`, `blueworx_guide_product_label()`; replace `blueworx_get_feature_guide_bodies()` with `blueworx_get_feature_guide_tasks()`; rewrite all guide arrays; add LatePoint tabs, product, detection and capabilities; add `wp-posts` tab. |
| `includes/features.php` | Add `'guide' => false` to sixteen features. |
| `includes/admin-guides.php` | Product tab labels via `blueworx_guide_product_label()`; `wp-posts` and `lp-*` screen links. |
| `assets/css/admin-additions.css` | Styles for `.bw-guide__where`, `.bw-guide__steps`, `.bw-guide__then`. |
| `tests/php/guides-format-test.php` | New: helper output, every registry guide has the three parts, flag hides features, ids stable, product labels. |
| `tests/php/guides-access-test.php` | Add stubs the new code needs (`esc_html`, `wp_kses_post` already there or added). |
| `tests/guides.spec.js`, `tests/guides-products.spec.js`, `tests/guides-access.spec.js` | Adjust expectations ("pages" not "writing"; flagged features absent). |
| `tests/guides-format.spec.js` | New: rendered cards carry Where + ordered list; Blog posts tab; LatePoint product with/without LatePoint. |
| `docs/guides-api.md` | Document `blueworx_guide_body()` and registering a product. |
| `CHANGELOG.md`, `readme.txt`, `package.json`, `blueworx-labs-wordpress.php` | Version and changelog. |

---

### Task 1: The body helper and its styles

**Files:**
- Modify: `includes/guides.php` (add functions after `BLUEWORX_GUIDES_FALLBACK_TAB`)
- Modify: `assets/css/admin-additions.css:195-205`
- Create: `tests/php/guides-format-test.php`
- Modify: `package.json` (`test:php` script)

**Interfaces:**
- Produces: `blueworx_guide_text( string $text ): string` — escapes, then turns `*Label*` into `<em>Label</em>`.
- Produces: `blueworx_guide_body( array $parts ): string` — keys `where` (string), `intro` (string, optional), `steps` (string[]), `then` (string). Returns HTML that survives `wp_kses_post` unchanged.

- [ ] **Step 1: Write the failing test**

Create `tests/php/guides-format-test.php`:

```php
<?php
/**
 * The guide format: every guide is a task with a Where line, steps and a Then.
 *
 * Run with: php tests/php/guides-format-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

$GLOBALS['roles'] = array(
	'administrator' => array( 'name' => 'Administrator', 'capabilities' => array( 'manage_options' => true, 'edit_posts' => true, 'read' => true ) ),
);
$GLOBALS['reader'] = 'administrator';

function current_user_can( $capability ) {
	return ! empty( $GLOBALS['roles'][ $GLOBALS['reader'] ]['capabilities'][ $capability ] );
}
function get_editable_roles() {
	return $GLOBALS['roles'];
}
function translate_user_role( $name ) {
	return $name;
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function esc_html__( $text, $domain = '' ) {
	return esc_html( $text );
}
function _n( $single, $plural, $number, $domain = '' ) {
	return 1 === $number ? $single : $plural;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function class_exists_stub( $name ) {
	return false;
}

require __DIR__ . '/../../includes/features.php';
require __DIR__ . '/../../includes/guides.php';

function blueworx_check_guide_format() {
	echo "The helper builds the three parts in order\n";

	$body = blueworx_guide_body(
		array(
			'where' => 'BlueWorx > Cache',
			'steps' => array( 'Press *Clear cache*.', 'Wait for the green tick.' ),
			'then'  => 'Visitors now see the new version.',
		)
	);

	check( 'starts with the Where line', 0, strpos( $body, '<p class="bw-guide__where"><strong>Where:</strong> BlueWorx &gt; Cache</p>' ) );
	check( 'steps are an ordered list', true, false !== strpos( $body, '<ol class="bw-guide__steps"><li>Press <em>Clear cache</em>.</li><li>Wait for the green tick.</li></ol>' ) );
	check( 'ends with Then', true, str_ends_with( $body, '<p class="bw-guide__then">Visitors now see the new version.</p>' ) );

	$with_intro = blueworx_guide_body(
		array(
			'where' => 'Posts',
			'intro' => 'A post is a dated entry.',
			'steps' => array( 'One.' ),
			'then'  => 'Done.',
		)
	);
	check( 'intro sits between Where and the steps', true, false !== strpos( $with_intro, 'Posts</p><p>A post is a dated entry.</p><ol' ) );

	echo "\nText is escaped before emphasis is added\n";

	check( 'angle brackets never reach the page', 'a &lt;b&gt; c', blueworx_guide_text( 'a <b> c' ) );
	check( 'asterisks become emphasis', 'press <em>Save</em> now', blueworx_guide_text( 'press *Save* now' ) );
	check( 'a lone asterisk is left alone', '2 * 3', blueworx_guide_text( '2 * 3' ) );
}

blueworx_check_guide_format();

finish();
```

- [ ] **Step 2: Run it to see it fail**

Run: `php tests/php/guides-format-test.php`
Expected: fatal error `Call to undefined function blueworx_guide_body()`.

- [ ] **Step 3: Add the helpers to `includes/guides.php`**

Insert directly after the `BLUEWORX_GUIDES_FALLBACK_TAB` constant:

```php
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
```

- [ ] **Step 4: Run the test**

Run: `php tests/php/guides-format-test.php`
Expected: every line `ok`, then `All checks passed.`

- [ ] **Step 5: Add the styles**

In `assets/css/admin-additions.css`, after the `.bw-guide__body p:last-child` rule (line ~205), add:

```css
/* A guide is a task: where to go, numbered steps, what happens next. The Where
   line is set apart as a label; the list keeps the body's own rhythm rather
   than the browser's indent. */
.bw-guide__body .bw-guide__where {
	color: var(--bw-text-muted);
	font-size: var(--bw-size-small);
}

.bw-guide__body .bw-guide__where strong {
	color: var(--bw-text-body);
	font-weight: 600;
}

.bw-guide__body ol.bw-guide__steps {
	margin: 0 0 var(--bw-space-6);
	padding-left: 1.5em;
	max-width: var(--bw-content-max);
}

.bw-guide__body ol.bw-guide__steps li {
	font-size: var(--bw-size-body);
	line-height: var(--bw-lh-body);
	color: var(--bw-text-body);
	margin: 0 0 var(--bw-space-3);
}

.bw-guide__body ol.bw-guide__steps li:last-child {
	margin-bottom: 0;
}

.bw-guide__body .bw-guide__then {
	color: var(--bw-text-muted);
}
```

If `--bw-text-muted`, `--bw-size-small` or `--bw-space-3` do not exist in `assets/blueworx-admin-design.css`, use the nearest token that does (grep `--bw-text-` and `--bw-space-` there) — do not invent a new token.

- [ ] **Step 6: Register the test and commit**

In `package.json`, append ` && php tests/php/guides-format-test.php` to the `test:php` script.

```bash
git add includes/guides.php assets/css/admin-additions.css tests/php/guides-format-test.php package.json
git commit -m "Add a helper that builds every guide as where, steps and what happens next

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Product labels that follow Display names

**Files:**
- Modify: `includes/guides.php` (`blueworx_get_guide_products()`, add `blueworx_guide_product_label()`)
- Modify: `includes/admin-guides.php:245-260` (product tab labels)
- Modify: `tests/php/guides-format-test.php`

**Interfaces:**
- Produces: `blueworx_guide_product_label( string $product ): string` — the product's name as the sidebar currently shows it: "SureCart" normally, "Commerce" when Display names is on. Unknown keys return `''`.

- [ ] **Step 1: Write the failing test**

Append to `blueworx_check_guide_format()` in `tests/php/guides-format-test.php`, before the closing brace:

```php
	echo "\nProduct names follow Display names\n";

	$GLOBALS['options'] = array( 'blueworx_feature_display_names' => '0' );
	check( 'SureCart is SureCart with the feature off', 'SureCart', blueworx_guide_product_label( 'surecart' ) );
	check( 'LatePoint too', 'LatePoint', blueworx_guide_product_label( 'latepoint' ) );

	$GLOBALS['options'] = array( 'blueworx_feature_display_names' => '1' );
	check( 'SureCart becomes Commerce with it on', 'Commerce', blueworx_guide_product_label( 'surecart' ) );
	check( 'LatePoint becomes Bookings', 'Bookings', blueworx_guide_product_label( 'latepoint' ) );
	check( 'WordPress is never renamed', 'WordPress', blueworx_guide_product_label( 'wordpress' ) );
	check( 'an unknown product is empty', '', blueworx_guide_product_label( 'nope' ) );

	$GLOBALS['options'] = array();
```

And add `require __DIR__ . '/../../includes/display-names.php';` after the `guides.php` require. If that file needs stubs the test lacks (`wp_add_inline_script`, `wp_json_encode` is in stubs), add empty stub functions above the require in the same style as the others.

- [ ] **Step 2: Run it to see it fail**

Run: `php tests/php/guides-format-test.php`
Expected: fatal `Call to undefined function blueworx_guide_product_label()`.

- [ ] **Step 3: Implement**

In `includes/guides.php`, add after `blueworx_get_guide_products()`:

```php
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
```

Then change the literal labels in `blueworx_get_guide_products()`:

```php
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
```

`includes/admin-guides.php` reads labels from `blueworx_get_guide_products()`, so the tabs pick this up with no change there. Confirm by grepping `admin-guides.php` for `'SureCart'` — there should be no literal.

- [ ] **Step 4: Run the tests**

Run: `php tests/php/guides-format-test.php && php tests/php/guides-access-test.php && php tests/php/display-names-test.php`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add includes/guides.php tests/php/guides-format-test.php
git commit -m "Name products on the Guides page the way the sidebar does

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Feature guides — the flag and one-feature-many-guides

**Files:**
- Modify: `includes/features.php` (sixteen entries)
- Modify: `includes/guides.php` (`blueworx_get_feature_guides()`, replace `blueworx_get_feature_guide_bodies()`)
- Modify: `tests/php/guides-format-test.php`

**Interfaces:**
- Consumes: `blueworx_guide_body()` from Task 1.
- Produces: `blueworx_get_feature_guide_tasks(): array` — keyed by feature key; each value a list of `array( 'slug' => string|'' , 'title' => string, 'body' => string )`. The first entry has `'slug' => ''` and becomes id `feature-<key>`; later entries become `feature-<key>-<slug>`.
- Produces: feature definition key `'guide' => false` — feature gets no guide at all.

- [ ] **Step 1: Write the failing tests**

Append to `blueworx_check_guide_format()`:

```php
	echo "\nOnly client-facing features get a guide\n";

	$GLOBALS['options'] = array();
	$feature_guides = blueworx_get_feature_guides();
	$ids            = array_column( $feature_guides, 'id' );

	$hidden = array( 'xmlrpc', 'rest_users', 'author_slugs', 'application_passwords', 'robots_txt', 'emails', 'revisions', 'login_session', 'login_redirect', 'profile_cleanup', 'dashboard_widgets', 'admin_bar', 'admin_theme', 'display_names', 'comments', 'user_roles' );
	foreach ( $hidden as $key ) {
		check( "no guide for $key", false, in_array( 'feature-' . $key, $ids, true ) );
	}

	// Every feature that is on and not flagged still has its first guide under
	// the id it always had, so links and specs keep resolving.
	foreach ( blueworx_get_feature_definitions() as $key => $feature ) {
		if ( ! empty( $feature['guide'] ) || ( isset( $feature['guide'] ) && false === $feature['guide'] ) ) {
			continue;
		}
		if ( ! blueworx_feature_enabled( $key ) ) {
			continue;
		}
		check( "feature-$key still exists", true, in_array( 'feature-' . $key, $ids, true ) );
	}

	echo "\nA feature can carry more than one task\n";

	check( 'sign-in address has a second guide', true, in_array( 'feature-login-changing', $ids, true ) );
	check( 'the second guide sits in the same tab', 'security', $feature_guides[ array_search( 'feature-login-changing', $ids, true ) ]['tab'] );
	check( 'and belongs to the same feature', 'login', $feature_guides[ array_search( 'feature-login-changing', $ids, true ) ]['feature'] );

	echo "\nEvery guide in the registry is a task\n";

	foreach ( array_merge( blueworx_get_wordpress_basics_guides(), blueworx_get_feature_guides(), blueworx_get_other_product_guides() ) as $guide ) {
		$ok = false !== strpos( $guide['body'], 'bw-guide__where' )
			&& false !== strpos( $guide['body'], '<ol class="bw-guide__steps">' )
			&& false !== strpos( $guide['body'], 'bw-guide__then' );
		check( $guide['id'] . ' has where, steps and then', true, $ok );
	}
```

Note: `blueworx_get_other_product_guides()` filters on active products; in this test none are active, so it returns only WordPress guides. That is fine — SureCart/LatePoint bodies are checked in Task 6 by temporarily forcing them active.

- [ ] **Step 2: Run it to see it fail**

Run: `php tests/php/guides-format-test.php`
Expected: FAIL lines for `no guide for xmlrpc` (and the rest), FAIL for `feature-login-changing`, and FAIL for every "has where, steps and then".

- [ ] **Step 3: Flag the features**

In `includes/features.php` add `'guide' => false,` as the last key of each of these definitions: `xmlrpc`, `rest_users`, `author_slugs`, `application_passwords`, `robots_txt`, `emails`, `revisions`, `login_session`, `login_redirect`, `profile_cleanup`, `dashboard_widgets`, `admin_bar`, `admin_theme`, `display_names`, `comments`, `user_roles`. Add to the function docblock, after the `default` explanation:

```
 * 'guide' => false keeps a feature off the Guides page. Use it for anything a
 * client never touches — the Guides page is for the people using the site,
 * and the Enhancements screen already describes every feature to whoever
 * configures it.
```

- [ ] **Step 4: Rewrite `blueworx_get_feature_guides()`**

Replace the function body:

```php
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
```

Update the docblock: "Every feature in the registry that is not flagged `guide => false` gets at least one. A feature can carry several tasks; the first keeps the id `feature-<key>` so nothing that links to it breaks."

- [ ] **Step 5: Replace `blueworx_get_feature_guide_bodies()` with `blueworx_get_feature_guide_tasks()`**

Delete `blueworx_get_feature_guide_bodies()` entirely and add, in its place, the function below. A short local `$t` closure keeps the arrays readable. Every string is wrapped in `__()`.

```php
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
						$t( 'Open *Custom login and protection* and type the new word in the *Login slug* box.' ),
						$t( 'Press *Save*.' ),
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
						$t( 'Press *Save*.' ),
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
						$t( 'Press *Save*.' ),
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
						$t( 'Press the *Sign in with…* button below the password box.' ),
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
						$t( 'Copy the shortcode shown under *Joining*.' ),
						$t( 'Open the page where people should join, add a *Shortcode* block, and paste it in.' ),
						$t( 'Back on the Single sign-on screen, choose which role a newcomer gets and where they land afterwards.' ),
						$t( 'Press *Save*, then *Update* the page.' ),
					),
					'then'  => $t( 'The button appears on the page. Nobody who joins this way can ever be made an administrator, whatever their work account says.' ),
				) ),
			),
			array(
				'slug'  => 'failed',
				'title' => $t( 'Finding out why a sign-in failed' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'BlueWorx > Single sign-on > Recent sign-ins' ),
					'steps' => array(
						$t( 'Ask the person roughly when they tried.' ),
						$t( 'Open *Recent sign-ins* and find their attempt by time and email address.' ),
						$t( 'Read the reason in the last column.' ),
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
						$t( 'Switch *Access window* on.' ),
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
						$t( 'Switch *Access window* off.' ),
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
						$t( 'Press *Clear cache*.' ),
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
						$t( 'Press *Publish* or *Update* as normal.' ),
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
						$t( 'Press *Save*.' ),
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
						$t( 'Find the item and press its *Hide* switch, or drag it into *More* to keep it but out of the way.' ),
						$t( 'Press *Save*.' ),
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
						$t( 'Press *View as* and choose a role.' ),
						$t( 'Click around the admin area as that person would.' ),
						$t( 'Press *Back to your view* when you are done.' ),
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
						$t( 'Open the new draft — it has "(Copy)" in the title.' ),
						$t( 'Change the title and the address (*URL*) in the panel on the right, then edit the content.' ),
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
						$t( 'In the panel on the right, find *Redirect to* and paste the full address, starting https://.' ),
						$t( 'Press *Update*.' ),
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
						$t( 'Press *Replace file* and choose the new version.' ),
						$t( 'Press *Upload*.' ),
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
						$t( 'Drop the .svg file onto the page, or press *Select files*.' ),
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
						$t( 'In the panel on the right, open *Excerpt* and write one or two sentences.' ),
						$t( 'If there is no *Excerpt* box, press the three dots at the top right, choose *Preferences*, and switch *Excerpt* on.' ),
						$t( 'Press *Update*.' ),
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
						$t( 'Open *Translate*.' ),
						$t( 'Tick the languages to offer and choose which corner the button sits in.' ),
						$t( 'Press *Save*.' ),
					),
					'then'  => $t( 'The button appears on the front of the site in Chrome and Edge. Other browsers do not show it, and search engines always read your original words.' ),
				) ),
			),
			array(
				'slug'  => 'excluding',
				'title' => $t( 'Keeping a page out of translation' ),
				'body'  => blueworx_guide_body( array(
					'where' => $t( 'BlueWorx > Enhancements > Translation' ),
					'steps' => array(
						$t( 'Open *Translate*.' ),
						$t( 'Under *Leave these pages out*, tick the page.' ),
						$t( 'Press *Save*.' ),
					),
					'then'  => $t( 'The button does not appear on that page. Use it for legal text that must stay in its original wording.' ),
				) ),
			),
		),
	);
}
```

Check the exact on-screen labels used above against the real screens before committing: open the harness and confirm *Login slug*, *Access window*, *Generate key*, *Clear cache*, *Hide*, *More*, *View as*, *Back to your view*, *Replace file*, *Redirect to*, *Leave these pages out*. Where a label differs, use the real one — the guide must match the button.

- [ ] **Step 6: Run the tests**

Run: `php tests/php/guides-format-test.php && php tests/php/guides-access-test.php`
Expected: all pass. The "has where, steps and then" checks for `basics-*` and `wp-*` guides will still FAIL — that is Task 4. Everything else passes.

- [ ] **Step 7: Commit**

```bash
git add includes/features.php includes/guides.php tests/php/guides-format-test.php
git commit -m "Keep only client-facing features on the Guides page, written as steps

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: WordPress guides — Blog posts topic and task rewrite

**Files:**
- Modify: `includes/guides.php` (`blueworx_get_wordpress_guide_tabs()`, `blueworx_get_wordpress_basics_guides()`, WordPress entries in `blueworx_get_other_product_guides()`, `blueworx_guide_tab_capability()`)
- Modify: `includes/admin-guides.php:355-362` (`$screens` map)
- Modify: `tests/guides-products.spec.js:57`

**Interfaces:**
- Produces: tab id `wp-posts` (label "Blog posts"), capability `edit_posts`, screen `edit.php`.
- `wp-writing` label becomes "Pages"; screen becomes `edit.php?post_type=page`.

- [ ] **Step 1: Tabs and capability**

In `blueworx_get_wordpress_guide_tabs()`:

```php
	return array(
		'wp-posts'   => __( 'Blog posts', 'blueworx-labs-wordpress' ),
		'wp-writing' => __( 'Pages', 'blueworx-labs-wordpress' ),
		'wp-media'   => __( 'Media library', 'blueworx-labs-wordpress' ),
		'wp-people'  => __( 'Users & roles', 'blueworx-labs-wordpress' ),
		'wp-upkeep'  => __( 'Updates & health', 'blueworx-labs-wordpress' ),
	);
```

In `blueworx_guide_tab_capability()` add `'wp-posts' => 'edit_posts',` above `'wp-writing'`.

In `includes/admin-guides.php` `$screens`:

```php
			$screens = array(
				'wp-posts'   => 'edit.php',
				'wp-writing' => 'edit.php?post_type=page',
				'wp-media'   => 'upload.php',
				'wp-people'  => 'users.php',
				'wp-upkeep'  => 'site-health.php',
			);
```

- [ ] **Step 2: Rewrite the Getting started guides**

Replace the whole return of `blueworx_get_wordpress_basics_guides()`. Ids unchanged.

```php
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
					$t( 'Press *Preview* to see it exactly as a visitor would.' ),
					$t( 'Press *Publish* (or *Update* on something already live) when it is ready.' ),
				),
				'then'  => $t( 'Nothing is live until you press Publish or Update. To take a live page down without deleting it, change its status to Draft in the panel on the right.' ),
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
					$t( 'Press *Restore this revision*, then *Update*.' ),
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
					$t( 'Leave *Send the new user an email* ticked and press *Add New User*.' ),
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
```

- [ ] **Step 3: Rewrite the WordPress topic guides and add Blog posts**

In `blueworx_get_other_product_guides()`, replace the eight `wp-*` entries with the set below (same `$t` closure defined at the top of the function). Keep the SureCart and SureForms entries for now — Task 5 rewrites them.

```php
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
					$t( 'Press *Update* or *Publish*.' ),
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
					$t( 'Open *Categories* and tick one. Press *Add New Category* if the right one does not exist yet.' ),
					$t( 'Open *Tags*, type a word and press Enter. Reuse tags you already have rather than inventing spellings.' ),
					$t( 'Press *Update* or *Publish*.' ),
				),
				'then'  => $t( 'A post with no category lands in "Uncategorised", which visitors can see. One category per post is usually right; tags can be many.' ),
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
					$t( 'Press *Update*.' ),
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
					$t( 'Under *Status*, choose *Draft*, or press *Switch to draft* at the top.' ),
					$t( 'Confirm.' ),
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
					$t( 'Open *Summary* (or *Status & visibility*) and find *Author*.' ),
					$t( 'Choose a name from the list.' ),
					$t( 'Press *Update*.' ),
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
					$t( 'Press *Save draft* often, and *Preview* to see it as a visitor would.' ),
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
					$t( 'Drop the file onto the page, or press *Select files*.' ),
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
					$t( 'Press *Replace file* and choose the new version.' ),
				),
				'then'  => $t( 'The address stays the same, so a price list you emailed to two hundred people keeps working. Deleting and re-uploading gives the file a new address and nothing warns you the old links broke. If there is no *Replace file* button, ask BlueWorx to switch it on.' ),
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
					$t( 'Press *Add New User*.' ),
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
```

The two old ids `wp-writing-blocks`, `wp-writing-links`, `wp-media-uploads`, `wp-media-replacing`, `wp-people-roles`, `wp-people-leaving`, `wp-upkeep-updates`, `wp-upkeep-health` are all still present above.

- [ ] **Step 4: Fix the spec that looked for "writing"**

In `tests/guides-products.spec.js` line 57, change `toContain('writing')` to `toContain('pages')`.

- [ ] **Step 5: Run the PHP tests**

Run: `npm run test:php`
Expected: all pass, including every "has where, steps and then" line.

- [ ] **Step 6: Commit**

```bash
git add includes/guides.php includes/admin-guides.php tests/guides-products.spec.js
git commit -m "Rewrite the WordPress guides as tasks and add a Blog posts topic

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: SureCart guides

**Files:**
- Modify: `includes/guides.php` (`sc-*` entries in `blueworx_get_other_product_guides()`)
- Modify: `tests/php/guides-format-test.php`

**Interfaces:**
- Consumes: `blueworx_guide_body()`, `blueworx_guide_product_label( 'surecart' )`.

- [ ] **Step 1: Write the failing test**

Append to `blueworx_check_guide_format()`:

```php
	echo "\nOther products' guides are tasks too\n";

	// Force every product active by answering the filter the registry asks.
	$GLOBALS['filters']['blueworx_guide_products'] = static function ( $products ) {
		$products['surecart']  = 'SureCart';
		$products['sureforms'] = 'SureForms';
		$products['latepoint'] = 'LatePoint';
		return $products;
	};

	$others = blueworx_get_other_product_guides();
	$by_product = array();
	foreach ( $others as $guide ) {
		$by_product[ $guide['product'] ][] = $guide['id'];
		$ok = false !== strpos( $guide['body'], 'bw-guide__where' )
			&& false !== strpos( $guide['body'], '<ol class="bw-guide__steps">' )
			&& false !== strpos( $guide['body'], 'bw-guide__then' );
		check( $guide['id'] . ' has where, steps and then', true, $ok );
	}

	check( 'SureCart has thirteen guides', 13, count( $by_product['surecart'] ) );
	check( 'the old SureCart ids are still there', true, in_array( 'sc-products-plans', $by_product['surecart'], true ) && in_array( 'sc-orders-refunds', $by_product['surecart'], true ) && in_array( 'sc-payments-test', $by_product['surecart'], true ) );

	unset( $GLOBALS['filters']['blueworx_guide_products'] );
```

Check how `$GLOBALS['filters']` is shaped in `tests/php/stubs.php` (`apply_filters` calls `$GLOBALS['filters'][$hook]` with the args) and match it.

- [ ] **Step 2: Run it to see it fail**

Run: `php tests/php/guides-format-test.php`
Expected: FAIL on the three `sc-*` "has where" checks and on the count.

- [ ] **Step 3: Replace the three SureCart entries**

The *Where* lines use `$sc = blueworx_guide_product_label( 'surecart' );` defined at the top of `blueworx_get_other_product_guides()`, so they read "Commerce > Products" when Display names is on. Use `sprintf( $t( '%s > Products' ), $sc )` for each.

```php
		// ── SureCart: Products & plans ──
		array(
			'id'      => 'sc-products-plans',
			'title'   => $t( 'Adding a product' ),
			'tab'     => 'sc-products',
			'product' => 'surecart',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Products' ), $sc ),
				'steps' => array(
					$t( 'Press *Add New*.' ),
					$t( 'Type the name and a description.' ),
					$t( 'Under *Pricing*, press *Add Price*, enter the amount, and choose *One time* or *Subscription*.' ),
					$t( 'Add an image, then press *Publish*.' ),
				),
				'then'  => $t( 'The product has its own page and can be added to any page with the *Buy Button* block. A product is the thing; a price is what it costs — one product can carry several prices.' ),
			) ),
		),
		array(
			'id'      => 'sc-products-price',
			'title'   => $t( 'Changing a price' ),
			'tab'     => 'sc-products',
			'product' => 'surecart',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Products' ), $sc ),
				'steps' => array(
					$t( 'Open the product.' ),
					$t( 'Under *Pricing*, press the price and change the amount.' ),
					$t( 'Press *Update*.' ),
				),
				'then'  => $t( 'Only new purchases pay the new amount. Anyone already on a subscription keeps paying what they signed up for.' ),
			) ),
		),
		array(
			'id'      => 'sc-products-second-price',
			'title'   => $t( 'Offering monthly and annual' ),
			'tab'     => 'sc-products',
			'product' => 'surecart',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Products' ), $sc ),
				'steps' => array(
					$t( 'Open the product.' ),
					$t( 'Under *Pricing*, press *Add Price*.' ),
					$t( 'Choose *Subscription*, set the amount and pick *Yearly*.' ),
					$t( 'Press *Update*.' ),
				),
				'then'  => $t( 'The checkout offers both and the customer picks. Give each price a short name so they can tell them apart.' ),
			) ),
		),
		array(
			'id'      => 'sc-products-coupon',
			'title'   => $t( 'Making a discount code' ),
			'tab'     => 'sc-products',
			'product' => 'surecart',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Coupons' ), $sc ),
				'steps' => array(
					$t( 'Press *Add New*.' ),
					$t( 'Choose a percentage or a fixed amount off.' ),
					$t( 'Under *Promotion codes*, type the code customers will enter.' ),
					$t( 'Set an end date or a maximum number of uses if you want one, then press *Publish*.' ),
				),
				'then'  => $t( 'Customers type the code at the checkout. A coupon is the discount; a code is the word that unlocks it — one coupon can have several codes.' ),
			) ),
		),
		array(
			'id'      => 'sc-products-archive',
			'title'   => $t( 'Stopping selling something' ),
			'tab'     => 'sc-products',
			'product' => 'surecart',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Products' ), $sc ),
				'steps' => array(
					$t( 'Open the product.' ),
					$t( 'Press *Archive* (in the three-dot menu at the top right).' ),
				),
				'then'  => $t( 'It stops being sold but its order history stays. Do not delete a product that has ever sold — deleting takes the orders with it.' ),
			) ),
		),

		// ── SureCart: Orders & customers ──
		array(
			'id'      => 'sc-orders-find',
			'title'   => $t( 'Finding an order' ),
			'tab'     => 'sc-orders',
			'product' => 'surecart',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Orders' ), $sc ),
				'steps' => array(
					$t( 'Type the customer\'s email address or the order number into the search box.' ),
					$t( 'Click the order.' ),
				),
				'then'  => $t( 'The order shows what was bought, what was paid and who paid it. If you cannot find it, check you are not in test mode — test orders are kept separately.' ),
			) ),
		),
		array(
			'id'      => 'sc-orders-refunds',
			'title'   => $t( 'Refunding a payment' ),
			'tab'     => 'sc-orders',
			'product' => 'surecart',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Orders' ), $sc ),
				'steps' => array(
					$t( 'Open the order.' ),
					$t( 'Press *Refund*.' ),
					$t( 'Enter the amount — the whole payment or part of it.' ),
					$t( 'Press *Refund* to confirm.' ),
				),
				'then'  => $t( 'The money goes back to the card that paid and can take a few working days to appear. Refunding does not cancel a subscription — that is a separate step.' ),
			) ),
		),
		array(
			'id'      => 'sc-orders-cancel-subscription',
			'title'   => $t( 'Cancelling a subscription' ),
			'tab'     => 'sc-orders',
			'product' => 'surecart',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Subscriptions' ), $sc ),
				'steps' => array(
					$t( 'Find the subscription by the customer\'s email address and open it.' ),
					$t( 'Press *Cancel*.' ),
					$t( 'Choose whether to stop now or at the end of the current period.' ),
					$t( 'Confirm.' ),
				),
				'then'  => $t( 'The next payment does not happen. The last payment is not returned — refund it from the order if you mean to.' ),
			) ),
		),
		array(
			'id'      => 'sc-orders-customer',
			'title'   => $t( 'Looking up a customer' ),
			'tab'     => 'sc-orders',
			'product' => 'surecart',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Customers' ), $sc ),
				'steps' => array(
					$t( 'Search by name or email address and open the customer.' ),
				),
				'then'  => $t( 'You see everything they have bought, every subscription, and their saved details. Change their email address here if they ask — it is also how they sign in.' ),
			) ),
		),
		array(
			'id'      => 'sc-orders-receipt',
			'title'   => $t( 'Resending a receipt' ),
			'tab'     => 'sc-orders',
			'product' => 'surecart',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Orders' ), $sc ),
				'steps' => array(
					$t( 'Open the order.' ),
					$t( 'Press *Resend Receipt* (in the three-dot menu at the top right).' ),
				),
				'then'  => $t( 'The receipt goes to the email address on the order. If they say it never arrived, check their spam folder before anything else.' ),
			) ),
		),

		// ── SureCart: Payments & test mode ──
		array(
			'id'      => 'sc-payments-test',
			'title'   => $t( 'Checking whether you are in test mode' ),
			'tab'     => 'sc-payments',
			'product' => 'surecart',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Settings > Payment Processors' ), $sc ),
				'steps' => array(
					$t( 'Look for the *Test mode* switch, and for an orange *Test mode* banner across the top of the shop screens.' ),
				),
				'then'  => $t( 'In test mode no real money moves. A checkout left in test mode looks completely normal to a customer, right up until you wonder where the money is.' ),
			) ),
		),
		array(
			'id'      => 'sc-payments-test-order',
			'title'   => $t( 'Placing a test order' ),
			'tab'     => 'sc-payments',
			'product' => 'surecart',
			'body'    => blueworx_guide_body( array(
				'where' => $t( 'The checkout on the front of the site' ),
				'steps' => array(
					$t( 'Make sure test mode is on.' ),
					$t( 'Buy something with card number 4242 4242 4242 4242, any future expiry date, and any three digits.' ),
					$t( 'Check the order arrived under Orders and the receipt email arrived.' ),
				),
				'then'  => $t( 'Test orders never become live ones and are listed separately. Delete them or leave them; they do not count.' ),
			) ),
		),
		array(
			'id'      => 'sc-payments-live',
			'title'   => $t( 'Going live' ),
			'tab'     => 'sc-payments',
			'product' => 'surecart',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Settings > Payment Processors' ), $sc ),
				'steps' => array(
					$t( 'Switch *Test mode* off.' ),
					$t( 'Check the payment processor shows as connected in live mode.' ),
					$t( 'Place one small real order yourself with a real card, then refund it.' ),
				),
				'then'  => $t( 'The refund proves the whole loop works. If the order fails, the processor is not finished being set up — ask BlueWorx before taking real orders.' ),
			) ),
		),
```

Rewrite the two SureForms entries in the same format (same ids `sf-forms-entries`, `sf-spam-notifications`; *Where* `sprintf( $t( '%s > Forms' ), blueworx_guide_product_label( 'sureforms' ) )` and `%s > Settings`). SureForms is out of scope for new coverage but must not be left in the old format.

Verify the SureCart labels (*Add Price*, *Promotion codes*, *Archive*, *Resend Receipt*, *Payment Processors*) against the SureCart version on a real site before committing; use the real wording where it differs.

- [ ] **Step 4: Run the tests**

Run: `npm run test:php`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add includes/guides.php tests/php/guides-format-test.php
git commit -m "Cover the everyday SureCart jobs step by step

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: LatePoint as a product

**Files:**
- Modify: `includes/guides.php` (`blueworx_get_latepoint_guide_tabs()`, `blueworx_get_all_guide_tabs()`, `blueworx_get_guide_products()`, `blueworx_guide_product_is_active()`, `blueworx_get_guide_tab_products()`, `blueworx_guide_tab_capability()`, LatePoint entries in `blueworx_get_other_product_guides()`)
- Modify: `includes/admin-guides.php` (`$screens` for `lp-*`)
- Modify: `tests/php/guides-format-test.php`

**Interfaces:**
- Produces: product `latepoint`, active when class `OsSettingsHelper` exists or `LATEPOINT_VERSION` is defined. Tabs `lp-calendar`, `lp-services`, `lp-staff`, `lp-customers`, all `manage_options`.

- [ ] **Step 1: Write the failing test**

Append to `blueworx_check_guide_format()`, inside the block where the products filter is still set (before the `unset`):

```php
	check( 'LatePoint has fourteen guides', 14, count( $by_product['latepoint'] ) );
	check( 'LatePoint tabs belong to LatePoint', 'latepoint', blueworx_guide_product_for_tab( 'lp-calendar' ) );
	check( 'LatePoint topics are administrator-only', 'manage_options', blueworx_guide_tab_capability( 'lp-services' ) );
```

And after the `unset`:

```php
	check( 'LatePoint is not offered when it is not installed', false, isset( blueworx_get_guide_products()['latepoint'] ) );
	define( 'LATEPOINT_VERSION', '5.0.0' );
	check( 'and is once it is', true, isset( blueworx_get_guide_products()['latepoint'] ) );
```

- [ ] **Step 2: Run it to see it fail**

Run: `php tests/php/guides-format-test.php`
Expected: FAIL (undefined index `latepoint`, wrong product for tab).

- [ ] **Step 3: Register the product**

In `includes/guides.php`:

Add after `blueworx_get_sureforms_guide_tabs()`:

```php
/**
 * The LatePoint topics. Only reached when LatePoint is running.
 *
 * @return array Tab labels keyed by tab id.
 */
function blueworx_get_latepoint_guide_tabs() {
	return array(
		'lp-calendar'  => __( 'Calendar & bookings', 'blueworx-labs-wordpress' ),
		'lp-services'  => __( 'Services', 'blueworx-labs-wordpress' ),
		'lp-staff'     => __( 'Staff & hours', 'blueworx-labs-wordpress' ),
		'lp-customers' => __( 'Customers', 'blueworx-labs-wordpress' ),
	);
}
```

In `blueworx_get_all_guide_tabs()` add:

```php
	if ( blueworx_guide_product_is_active( 'latepoint' ) ) {
		$tabs += blueworx_get_latepoint_guide_tabs();
	}
```

In `blueworx_get_guide_products()` add after the sureforms block:

```php
	if ( blueworx_guide_product_is_active( 'latepoint' ) ) {
		$products['latepoint'] = blueworx_guide_product_label( 'latepoint' );
	}
```

In `blueworx_guide_product_is_active()` add to `$signatures`:

```php
		'latepoint' => array( 'classes' => array( 'OsSettingsHelper' ), 'constants' => array( 'LATEPOINT_VERSION' ) ),
```

In `blueworx_get_guide_tab_products()` add:

```php
	foreach ( array_keys( blueworx_get_latepoint_guide_tabs() ) as $tab ) {
		$map[ $tab ] = 'latepoint';
	}
```

In `blueworx_guide_tab_capability()` add after `sf-spam`:

```php
		'lp-calendar'     => 'manage_options',
		'lp-services'     => 'manage_options',
		'lp-staff'        => 'manage_options',
		'lp-customers'    => 'manage_options',
```

In `includes/admin-guides.php` `$screens` add:

```php
				'lp-calendar'  => 'admin.php?page=latepoint&route_name=calendar__view',
				'lp-services'  => 'admin.php?page=latepoint&route_name=services__index',
				'lp-staff'     => 'admin.php?page=latepoint&route_name=agents__index',
				'lp-customers' => 'admin.php?page=latepoint&route_name=customers__index',
```

Confirm those `route_name` values against the installed LatePoint (open each screen on the harness and read the address bar). Use what the address bar says.

- [ ] **Step 4: Write the LatePoint guides**

Add to `blueworx_get_other_product_guides()` with `$lp = blueworx_guide_product_label( 'latepoint' );` at the top:

```php
		// ── LatePoint: Calendar & bookings ──
		array(
			'id'      => 'lp-calendar-today',
			'title'   => $t( 'Seeing today\'s bookings' ),
			'tab'     => 'lp-calendar',
			'product' => 'latepoint',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Calendar' ), $lp ),
				'steps' => array(
					$t( 'Press *Today* at the top of the calendar.' ),
					$t( 'Choose *Day* to see one day in detail, or *Week* for the week ahead.' ),
					$t( 'Click any booking to see who it is and what they booked.' ),
				),
				'then'  => $t( 'Colours are by service. If a staff member sees nothing, check they are looking at their own calendar and not the whole team\'s.' ),
			) ),
		),
		array(
			'id'      => 'lp-calendar-add',
			'title'   => $t( 'Booking somebody in by hand' ),
			'tab'     => 'lp-calendar',
			'product' => 'latepoint',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Calendar' ), $lp ),
				'steps' => array(
					$t( 'Press *New Appointment* (the + button).' ),
					$t( 'Choose the service and the staff member.' ),
					$t( 'Pick the day and time.' ),
					$t( 'Type the customer\'s name and email — pick them from the list if they have booked before.' ),
					$t( 'Press *Save*.' ),
				),
				'then'  => $t( 'The customer gets the same confirmation email as if they had booked online. Tick *Do not send notifications* before saving if you do not want that.' ),
			) ),
		),
		array(
			'id'      => 'lp-calendar-move',
			'title'   => $t( 'Moving a booking' ),
			'tab'     => 'lp-calendar',
			'product' => 'latepoint',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Calendar' ), $lp ),
				'steps' => array(
					$t( 'Click the booking.' ),
					$t( 'Change the date or time.' ),
					$t( 'Press *Save*.' ),
				),
				'then'  => $t( 'The customer is emailed the new time. Only free slots are offered, so if the time you want is not there, somebody else has it.' ),
			) ),
		),
		array(
			'id'      => 'lp-calendar-cancel',
			'title'   => $t( 'Cancelling a booking' ),
			'tab'     => 'lp-calendar',
			'product' => 'latepoint',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Calendar' ), $lp ),
				'steps' => array(
					$t( 'Click the booking.' ),
					$t( 'Change *Status* to *Cancelled*.' ),
					$t( 'Press *Save*.' ),
				),
				'then'  => $t( 'The slot is free again and the customer is told. Cancel rather than delete — a deleted booking leaves no record that it ever existed.' ),
			) ),
		),
		array(
			'id'      => 'lp-calendar-no-show',
			'title'   => $t( 'Marking a no-show' ),
			'tab'     => 'lp-calendar',
			'product' => 'latepoint',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Calendar' ), $lp ),
				'steps' => array(
					$t( 'Click the booking.' ),
					$t( 'Change *Status* to *No Show*.' ),
					$t( 'Press *Save*.' ),
				),
				'then'  => $t( 'It stays on the customer\'s record, so you can see a pattern before you next take a booking from them.' ),
			) ),
		),

		// ── LatePoint: Services ──
		array(
			'id'      => 'lp-services-add',
			'title'   => $t( 'Adding a service' ),
			'tab'     => 'lp-services',
			'product' => 'latepoint',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Services' ), $lp ),
				'steps' => array(
					$t( 'Press *Add Service*.' ),
					$t( 'Type the name, how long it takes, and the price.' ),
					$t( 'Under *Agents*, tick who can provide it.' ),
					$t( 'Press *Save*.' ),
				),
				'then'  => $t( 'It appears on the booking form straight away. A service with no staff ticked cannot be booked and shows to nobody.' ),
			) ),
		),
		array(
			'id'      => 'lp-services-edit',
			'title'   => $t( 'Changing how long a service takes, or its price' ),
			'tab'     => 'lp-services',
			'product' => 'latepoint',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Services' ), $lp ),
				'steps' => array(
					$t( 'Click the service.' ),
					$t( 'Change *Duration* or *Price*.' ),
					$t( 'Press *Save*.' ),
				),
				'then'  => $t( 'Bookings already made keep their old length and price. Only new bookings use the new ones.' ),
			) ),
		),
		array(
			'id'      => 'lp-services-hide',
			'title'   => $t( 'Hiding a service without deleting it' ),
			'tab'     => 'lp-services',
			'product' => 'latepoint',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Services' ), $lp ),
				'steps' => array(
					$t( 'Click the service.' ),
					$t( 'Change *Status* to *Disabled*.' ),
					$t( 'Press *Save*.' ),
				),
				'then'  => $t( 'It vanishes from the booking form but past bookings still show it. Use this for a seasonal service rather than deleting and recreating it.' ),
			) ),
		),

		// ── LatePoint: Staff & hours ──
		array(
			'id'      => 'lp-staff-add',
			'title'   => $t( 'Adding a staff member' ),
			'tab'     => 'lp-staff',
			'product' => 'latepoint',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Agents' ), $lp ),
				'steps' => array(
					$t( 'Press *Add Agent*.' ),
					$t( 'Type their name and email address.' ),
					$t( 'Under *Services*, tick what they provide.' ),
					$t( 'Under *Schedule*, set their working days and hours.' ),
					$t( 'Press *Save*.' ),
				),
				'then'  => $t( 'They can be booked from now on. They also get a WordPress account so they can sign in and see their own calendar.' ),
			) ),
		),
		array(
			'id'      => 'lp-staff-hours',
			'title'   => $t( 'Setting working hours' ),
			'tab'     => 'lp-staff',
			'product' => 'latepoint',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Settings > Work Hours' ), $lp ),
				'steps' => array(
					$t( 'For each day, set the start and end time, or switch the day off.' ),
					$t( 'Press *Save*.' ),
				),
				'then'  => $t( 'These are the hours for the whole business. To give one person different hours, open them under Agents and set *Schedule* there — a person\'s own hours win.' ),
			) ),
		),
		array(
			'id'      => 'lp-staff-holiday',
			'title'   => $t( 'Adding a day off or a holiday' ),
			'tab'     => 'lp-staff',
			'product' => 'latepoint',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Settings > Work Hours' ), $lp ),
				'steps' => array(
					$t( 'Under *Custom Day Schedules*, press *Add Day*.' ),
					$t( 'Pick the date and switch it to *Day Off*.' ),
					$t( 'Press *Save*.' ),
				),
				'then'  => $t( 'Nobody can book that day. For one person only, do the same under their own *Schedule* on the Agents screen.' ),
			) ),
		),
		array(
			'id'      => 'lp-staff-block',
			'title'   => $t( 'Blocking out part of a day' ),
			'tab'     => 'lp-staff',
			'product' => 'latepoint',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Settings > Work Hours' ), $lp ),
				'steps' => array(
					$t( 'Under *Custom Day Schedules*, press *Add Day* and pick the date.' ),
					$t( 'Set the hours you are available — say 9 to 12 — and leave the rest.' ),
					$t( 'Press *Save*.' ),
				),
				'then'  => $t( 'Only those hours are offered that day. Bookings already made outside them are not moved — check the calendar first.' ),
			) ),
		),

		// ── LatePoint: Customers ──
		array(
			'id'      => 'lp-customers-find',
			'title'   => $t( 'Finding a customer and their history' ),
			'tab'     => 'lp-customers',
			'product' => 'latepoint',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Customers' ), $lp ),
				'steps' => array(
					$t( 'Search by name, email or phone.' ),
					$t( 'Click the customer.' ),
				),
				'then'  => $t( 'You see every booking they have made, past and future, and any no-shows.' ),
			) ),
		),
		array(
			'id'      => 'lp-customers-edit',
			'title'   => $t( 'Changing a customer\'s details' ),
			'tab'     => 'lp-customers',
			'product' => 'latepoint',
			'body'    => blueworx_guide_body( array(
				'where' => sprintf( $t( '%s > Customers' ), $lp ),
				'steps' => array(
					$t( 'Click the customer.' ),
					$t( 'Change the name, email or phone.' ),
					$t( 'Press *Save*.' ),
				),
				'then'  => $t( 'Emails about future bookings go to the new address. Their past bookings stay attached to them.' ),
			) ),
		),
```

Then run `node scripts/install-test-latepoint.mjs` on the harness, open each LatePoint screen, and correct every label above (*New Appointment*, *Add Service*, *Add Agent*, *Work Hours*, *Custom Day Schedules*, *Day Off*, status names) to what the installed version actually says.

- [ ] **Step 5: Run the tests**

Run: `npm run test:php`
Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add includes/guides.php includes/admin-guides.php tests/php/guides-format-test.php
git commit -m "Add LatePoint to the Guides page, shown only where it is installed

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 7: Playwright — the rendered page

**Files:**
- Create: `tests/guides-format.spec.js`
- Modify: `tests/guides.spec.js` (if any expectation names a now-flagged feature — grep for `feature-` ids)

- [ ] **Step 1: Start the harness**

```bash
node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up --plugin .
```

Then remove the duplicate plugin symlink the harness creates (see memory `harness-duplicate-plugin-symlink`; the path is printed by `up`).

- [ ] **Step 2: Write the spec**

Create `tests/guides-format.spec.js`:

```js
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

/**
 * Every guide is a task: where to go, numbered steps, what happens next.
 *
 * The PHP test checks the registry says so; this checks the page shows it,
 * with the styles loaded and the cards laid out.
 */

const GUIDES = '/wp-admin/admin.php?page=blueworx-guides';

test.describe('Guides — task format', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('every visible card has a Where line, numbered steps and a closing line', async ({ page }) => {
    await login(page);

    for (const product of ['blueworx', 'wordpress']) {
      await page.goto(`${GUIDES}&product=${product}`);

      const tabs = await page.locator('[data-blueworx-guide-tabs] .bw-tab').all();
      expect(tabs.length).toBeGreaterThan(0);

      for (const tab of tabs) {
        await tab.click();
        const cards = page.locator('.bw-guidegrid:not([hidden]) [data-blueworx-guide]');
        const count = await cards.count();
        expect(count).toBeGreaterThan(0);

        for (let i = 0; i < count; i++) {
          const card = cards.nth(i);
          await expect(card.locator('.bw-guide__where')).toHaveText(/^Where:/);
          await expect(card.locator('ol.bw-guide__steps li').first()).toBeVisible();
          await expect(card.locator('.bw-guide__then')).toBeVisible();
        }
      }
    }
  });

  test('the technical features are no longer on the page', async ({ page }) => {
    await login(page);
    await page.goto(`${GUIDES}&product=blueworx&tab=security`);

    for (const key of ['xmlrpc', 'rest_users', 'author_slugs', 'application_passwords']) {
      await expect(page.locator(`[data-blueworx-guide="feature-${key}"]`)).toHaveCount(0);
    }

    // And a client-facing one is, under the id it always had.
    await expect(page.locator('[data-blueworx-guide="feature-login"]')).toBeVisible();
    await expect(page.locator('[data-blueworx-guide="feature-login-changing"]')).toBeVisible();
  });

  test('WordPress has a Blog posts topic with its eight guides', async ({ page }) => {
    await login(page);
    await page.goto(`${GUIDES}&product=wordpress&tab=wp-posts`);

    await expect(page.locator('[data-blueworx-guide-tab="wp-posts"]')).toHaveClass(/is-active/);
    await expect(page.locator('.bw-guidegrid:not([hidden]) [data-blueworx-guide^="wp-posts-"]')).toHaveCount(8);

    // The action on a Blog posts card opens the Posts list.
    const href = await page
      .locator('.bw-guidegrid:not([hidden]) [data-blueworx-guide="wp-posts-writing"] a.bw-button')
      .first()
      .getAttribute('href');
    expect(href).toContain('edit.php');
    expect(href).not.toContain('post_type=page');
  });

  test('LatePoint is not offered when it is not installed', async ({ page }) => {
    test.skip(process.env.LATEPOINT_INSTALLED === '1', 'LatePoint is installed on this harness');
    await login(page);
    await page.goto(GUIDES);
    const labels = await page.locator('[data-blueworx-guide-products] .bw-prodtab').allInnerTexts();
    expect(labels.join(' ')).not.toMatch(/LatePoint|Bookings/);
  });

  test('LatePoint is offered when it is installed', async ({ page }) => {
    test.skip(process.env.LATEPOINT_INSTALLED !== '1', 'Run scripts/install-test-latepoint.mjs and set LATEPOINT_INSTALLED=1');
    await login(page);
    await page.goto(`${GUIDES}&product=latepoint`);
    await expect(page.locator('[data-blueworx-guide-product="latepoint"]')).toHaveClass(/is-active/);
    await expect(page.locator('.bw-guidegrid:not([hidden]) [data-blueworx-guide^="lp-"]').first()).toBeVisible();
  });
});
```

Check how `tests/latepoint-layout.spec.js` decides LatePoint is present (its `test.skip` at line 24) and use the same signal instead of `LATEPOINT_INSTALLED` if it already has one.

The `a.bw-button` selector: confirm the class `blueworx_ds_button()` emits by reading `assets/blueworx-admin-design.php` and use that.

- [ ] **Step 3: Run the guide specs**

```bash
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8881 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw WP_LOGIN_PATH=admin_login npx playwright test tests/guides-format.spec.js tests/guides.spec.js tests/guides-products.spec.js tests/guides-access.spec.js tests/guides-design.spec.js --workers=1 --reporter=list
```

Expected: all pass (LatePoint-installed test skipped). If `guides.spec.js` 'each tab shows only its own guides' or `guides-access.spec.js` fail on a count that changed, read the assertion and update the number — the set of guides changed on purpose.

- [ ] **Step 4: Run with LatePoint installed**

```bash
node scripts/install-test-latepoint.mjs
LATEPOINT_INSTALLED=1 PLAYWRIGHT_BASE_URL=http://127.0.0.1:8881 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw WP_LOGIN_PATH=admin_login npx playwright test tests/guides-format.spec.js --workers=1 --reporter=list
```

Expected: the "offered when installed" test passes. While LatePoint is up, open `admin.php?page=blueworx-guides&product=latepoint` and click *Open the screen* on one card per tab to confirm the `route_name` links land on the right LatePoint screens.

- [ ] **Step 5: Look at it**

Open `http://127.0.0.1:8881/wp-admin/admin.php?page=blueworx-guides` in a browser (or take a Playwright screenshot) and check a card reads well: Where line muted, steps numbered and spaced, Then line under. Fix spacing in `admin-additions.css` if it does not.

- [ ] **Step 6: Commit**

```bash
git add tests/guides-format.spec.js tests/guides.spec.js tests/guides-access.spec.js
git commit -m "Check the Guides page renders every card as a task

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 8: Docs, version, changelog, zip

**Files:**
- Modify: `docs/guides-api.md`
- Modify: `CHANGELOG.md`, `readme.txt:7`, `package.json:3`, `blueworx-labs-wordpress.php:6,66`

- [ ] **Step 1: Document the helper and product registration**

In `docs/guides-api.md`, after "## Add a tab", add:

```markdown
## Write it as a task

Every guide on the page is one task: where to go, numbered steps, what happens
next. Build the body with the helper so yours reads the same way:

```php
'body' => blueworx_guide_body( array(
    'where' => __( 'Acme > Shipping', 'acme' ),
    'steps' => array(
        __( 'Press *Add zone*.', 'acme' ),
        __( 'Type the postcodes and press *Save*.', 'acme' ),
    ),
    'then'  => __( 'Orders to those postcodes now get that rate.', 'acme' ),
) ),
```

Text in `*asterisks*` is shown as emphasis — use it for the label on a button.
Everything else is escaped. Guard with `function_exists( 'blueworx_guide_body' )`
and fall back to plain HTML if your plugin can run without BlueWorx.

## Add a product

A product is the top row of tabs — BlueWorx, WordPress, and each plugin the
site runs. Register one, then say which tabs belong to it:

```php
add_filter( 'blueworx_guide_products', function ( $products ) {
    $products['acme'] = __( 'Acme', 'acme' );
    return $products;
} );

add_filter( 'blueworx_guide_tab_products', function ( $map ) {
    $map['acme-shipping'] = 'acme';
    return $map;
} );
```

A tab not in the map is treated as yours-but-BlueWorx's, and a product with no
guides is not shown.
```

- [ ] **Step 2: Bump the version**

- `blueworx-labs-wordpress.php` line 6: `1.86.0`; line 66: `'1.86.0'`.
- `package.json` line 3: `"1.86.0"`.
- `readme.txt` line 7: `Stable tag:        1.86.0`.

Run: `npm run version:check`
Expected: passes.

- [ ] **Step 3: Changelog**

At the top of `CHANGELOG.md`, under the intro:

```markdown
## [1.86.0] - 2026-09-13

### Changed
- **Guides now tell you what to click, step by step.** Every guide is one job:
  where to go, the steps in order, and what you should see afterwards.
- **The BlueWorx tab only lists what a client uses.** Technical and security
  settings are still on the Enhancements screen but no longer clutter the
  guides.

### Added
- **A Blog posts topic** under WordPress: writing, featured images, categories,
  scheduling, editing a live post, taking one down, and the trash.
- **Thirteen SureCart guides** covering products, prices, discount codes,
  orders, refunds, subscriptions and going live.
- **LatePoint guides**, shown only on sites that run it: the calendar,
  services, staff hours and days off, and customers.
```

- [ ] **Step 4: Lint once, PHP tests, full guide specs**

```bash
npm run lint
npm run test:php
vendor/bin/phpcs --standard=phpcs.xml.dist includes/guides.php includes/features.php includes/admin-guides.php
```

Present lint/phpcs findings to Luke; do not fix them without approval.

- [ ] **Step 5: Commit**

```bash
git add docs/guides-api.md CHANGELOG.md readme.txt package.json blueworx-labs-wordpress.php
git commit -m "Bump to 1.86.0 for the step-by-step guides

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 6: Build the zip**

```bash
npm run build
ls ../blueworx-labs-wordpress-*.zip
```

`scripts/build-zip.mjs` is the existing builder; confirm it wrote `../blueworx-labs-wordpress-1.86.0.zip` and removed the 1.85.0 one, then list it:

```bash
/c/Windows/System32/tar.exe -tf ../blueworx-labs-wordpress-1.86.0.zip | head
```

Every entry must read `blueworx-labs-wordpress/...` with forward slashes.

- [ ] **Step 7: Push and open the PR**

```bash
git push -u origin guides-refresh
gh pr create --title "Guides that say what to click, step by step" --body "$(cat <<'EOF'
Every guide is now one task: where to go, numbered steps, what you should see. The BlueWorx tab only lists client-facing features. Adds a Blog posts topic, thirteen SureCart guides, and LatePoint as a product (only where installed).

ClubHouse and Forge register their own guides in follow-up PRs in their repos.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Follow-ups (separate plans, after this ships)

- **ClubHouse** (`blueworx_labs_clubhouse`): register `clubhouse` product and one tab per `Blueworx_Clubhouse_Guide` chapter through `blueworx_guide_products`, `blueworx_guide_tab_products`, `blueworx_guide_tabs` and `blueworx_guides`; entries rebuilt with `blueworx_guide_body()` when it exists; Guide menu item links to `admin.php?page=blueworx-guides&product=clubhouse` when `function_exists( 'blueworx_get_guides' )`.
- **Forge client** (`blueworx_project_forge/client`): register `forge` product with tabs `forge-workspace`, `forge-checklists`, `forge-discussion`, `forge-reports`; confirm the task list against `client/includes/`.
