# Store Pages Implementation Plan (Labs side)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move SureCart's dressed checkout, thank-you and customer dashboard pages, plus the four-page existence check, from the ClubHouse plugin into this one, with five WordPress filters other plugins use to add views, panels, branding and links.

**Architecture:** One feature (`store_pages`) made of procedural files under `includes/store/`, each a port of one ClubHouse class with the club-specific parts removed and a filter put in their place. Checkout, thank-you and dashboard are SureCart's own seeded pages, dressed by a `the_content` filter and served from the plugin's own template. Everything pure (status decisions, view resolution, redirects, markup) is a plain function testable from the CLI with `tests/php/stubs.php`; the WordPress calls sit at the edges.

**Tech Stack:** PHP 8.0+ procedural (this plugin's house style), WordPress filters, plain-PHP CLI tests (`npm run test:php`), Playwright against the local harness (`npx playwright test`), the vendored admin design system (`assets/blueworx-admin-design.css`).

**Spec:** `docs/superpowers/specs/2026-09-21-store-pages-design.md` — read it first. The ClubHouse source being ported lives at `c:\Users\LukeMcfarland\Documents\GitHub\blueworx_labs_clubhouse` (an additional working directory; read it, never edit it from this plan).

## Global Constraints

- Version becomes **1.87.0** in `blueworx-labs-wordpress.php` (header and `BLUEWORX_LABS_VERSION`), `package.json` and `readme.txt` Stable tag. `npm run version:check` must pass.
- CHANGELOG.md gets a `## [1.87.0] - 2026-09-21` entry (Task 9).
- No new dependencies. Nothing added to `approved-deps.json`.
- Every function is prefixed `blueworx_store_`. Files are procedural, no classes, `if ( ! defined( 'ABSPATH' ) ) { exit; }` at the top, docblocks on every function (phpcs runs in CI: `composer lint`).
- SureCart loads **after** this plugin (alphabetical plugin order), so "is SureCart active" is only ever asked inside a hook callback, never at file scope.
- Feature gating follows `includes/disable-comments.php`: hooks are added at file scope inside `if ( blueworx_feature_enabled( 'store_pages' ) )`.
- CSS classes: what ClubHouse calls `clubhouse-member__*` becomes `bw-store__*`; `clubhouse-checkout__*` becomes `bw-checkout__*`; the root `clubhouse-member` class becomes `bw-store`; `clubhouse-checkout` becomes `bw-checkout`; `data-clubhouse-member` becomes `data-bw-store`; ids `clubhouse-member-navtab-*` / `clubhouse-member-tab-*` / `clubhouse-member-view` become `bw-store-navtab-*` / `bw-store-tab-*` / `bw-store-view`. Design-system classes (`bw-admin`, `bw-page`, `bw-card`, `bw-secnav`, `bw-panels`, `bw-pagehead`, `bw-person`, `bw-avatar`, `bw-empty`, `bw-btn`, `bw-icon`) stay.
- The plugin zip ships only `includes/`, `assets/` and the top-level files (`scripts/build-zip.mjs`), so the template lives at `includes/store/template.php`, not a top-level `templates/`.
- Copy in notices and guides is plain English, no jargon. Notice prefix is "BlueWorx:".
- Commit after every task with a one-line message and the trailer `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`.
- Lint once at the end (Task 9), never in a loop; present findings, do not auto-fix.

## Function inventory (the contract between tasks)

Every later task uses these exact names. Signatures are PHP 8, typed where the house style allows (the rest of `includes/` is untyped; match that — docblocks carry the types).

`includes/store/pages.php`
- `blueworx_store_surecart_active(): bool`
- `blueworx_store_pages(): array` — `key => ['label' => …, 'consequence' => …]`
- `blueworx_store_confirmation_page(): array` — `slug, option, title, content`
- `blueworx_store_option_name( $key ): string`
- `blueworx_store_page_id( $key ): int`
- `blueworx_store_post_status( $page_id ): string`
- `blueworx_store_decide( $shop_active, $page_id, $post_status ): string` — one of `'no-shop' | 'ok' | 'missing' | 'unpublished'`
- `blueworx_store_page_status( $key ): string`
- `blueworx_store_page_statuses(): array`
- `blueworx_store_problems( $statuses ): array`
- `blueworx_store_repairable( $problems, $pages ): array`
- `blueworx_store_page_url( $key ): string` — **public API**
- `blueworx_store_can_seed(): bool`
- `blueworx_store_repair(): bool`
- `blueworx_store_should_create_confirmation( $shop_active, $page_id, $post_status ): bool`
- `blueworx_store_ensure_confirmation(): void`
- `blueworx_store_create_confirmation(): void`
- `blueworx_store_publish_existing( $page_id ): bool`

`includes/store/pages-notice.php`
- `blueworx_store_notice_message( $problems, $pages, $can_seed ): ?array` — `['lines' => [], 'button' => '', 'footnote' => '']`
- `blueworx_store_notice_html( $message, $action_url ): string`
- `blueworx_store_render_notice(): void`
- `blueworx_store_handle_repair(): void`

`includes/store/slot.php`
- `blueworx_store_slot_set_sources( $blocks, $shortcodes ): void`
- `blueworx_store_slot_block( $name ): string`
- `blueworx_store_slot_shortcode( $tag ): string`
- `blueworx_store_slot_install(): void`

`includes/store/views.php`
- `blueworx_store_default_views(): array` — the seven SureCart views
- `blueworx_store_normalize_views( $views ): array` — pure; drops junk, guarantees `dashboard` first
- `blueworx_store_views(): array` — applies `blueworx_store_views`, memoised per request
- `blueworx_store_views_side( $views ): array`, `blueworx_store_views_bar( $views ): array`
- `blueworx_store_resolve_view( $requested, $views ): string`
- `blueworx_store_find_view( $key, $views ): ?array`

`includes/store/actions.php`
- `blueworx_store_action_block(): string` — `'surecart/dashboard-page'`
- `blueworx_store_is_action( $model, $action, $exists ): bool`
- `blueworx_store_action_view( $model ): string`
- `blueworx_store_set_action_check( $check ): void`, `blueworx_store_action_check(): callable`
- `blueworx_store_requested_action(): array` — `['model' => '', 'action' => '']`

`includes/store/shell.php`
- `blueworx_store_e( $v ): string`
- `blueworx_store_view_url( $key, $base = '' ): string`
- `blueworx_store_initials( $name ): string`
- `blueworx_store_shell_page( $args ): string`
- `blueworx_store_shell_bare( $title, $lede, $body, $home_url, $site_name ): string`
- `blueworx_store_shell_checkout( $args ): string`
- `blueworx_store_shell_card( $title, $body ): string`
- `blueworx_store_shell_empty_state( $title, $text, $href, $label ): string`
- `blueworx_store_shell_icon( $name, $svg = '' ): string`

`includes/store/context.php`
- `blueworx_store_default_context(): array`
- `blueworx_store_context(): array` — applies `blueworx_store_context`, memoised
- `blueworx_store_back_label( $site_name ): string`

`includes/store/assets.php`
- `blueworx_store_page_key( $post_id ): string` — `'checkout' | 'order-confirmation' | 'dashboard' | ''`
- `blueworx_store_declare_assets(): void` (on `wp_enqueue_scripts`)
- `blueworx_store_enqueue_frame(): void`
- `blueworx_store_enqueue_dashboard(): void` — **public API**
- `blueworx_store_enqueue_shop_assets(): void`
- `blueworx_store_wants_surecart_style( $page_key ): bool`

`includes/store/commerce.php`
- `blueworx_store_template_for( $page_key, $default, $ours ): string`
- `blueworx_store_serve_template( $template ): string`
- `blueworx_store_strip_post_title( $block_content, $block, $instance = null ): string`
- `blueworx_store_dress_content( $content ): string`
- `blueworx_store_checkout_links(): array` — applies `blueworx_store_checkout_links`

`includes/store/dashboard.php`
- `blueworx_store_dashboard_url(): string` — applies `blueworx_store_dashboard_url`
- `blueworx_store_redirect_to( $queried_id, $dashboard_id, $claimed_url, $signed_in, $view, $login_url, $model = '', $action = '', $id = '' ): string` — pure
- `blueworx_store_route(): void`
- `blueworx_store_dashboard_screen( $base, $home ): string` — **public API**
- `blueworx_store_view_body( $view, $home_url ): string`
- `blueworx_store_overview( $views, $home_url, $base ): string`
- `blueworx_store_not_set_up( $home_url ): string`
- `blueworx_store_requested_view(): string`, `blueworx_store_requested_record(): string`
- `blueworx_store_action_panel(): ?array`

`includes/store/store.php` — requires the files above in order and registers hooks.

Filters (names are final): `blueworx_store_views`, `blueworx_store_panel`, `blueworx_store_context`, `blueworx_store_checkout_links`, `blueworx_store_dashboard_url`.

---

### Task 1: Register the feature, its section and its guide

**Files:**
- Modify: `includes/features.php` (sections at ~line 23, definitions at ~line 56)
- Modify: `includes/guides.php` (`blueworx_guide_tab_capability()` at ~line 2057; `blueworx_get_feature_guide_tasks()` at ~line 1539)
- Create: `includes/store/store.php`
- Modify: `blueworx-labs-wordpress.php` (the `require_once` list, ~line 95–150)
- Test: `tests/php/guides-format-test.php` (existing — must still pass), `tests/store-feature.spec.js` (new)

**Interfaces:**
- Produces: feature key `store_pages`, section `store`; `includes/store/store.php` as the single require point for later tasks.

- [ ] **Step 1: Add the section and the feature definition**

In `blueworx_get_feature_sections()` add, after `'appearance'`:

```php
'store'         => __( 'Store', 'blueworx-labs-wordpress' ),
```

In `blueworx_get_feature_definitions()` add, as the last entry:

```php
'store_pages'           => array(
	'label'       => __( 'Store pages', 'blueworx-labs-wordpress' ),
	'description' => __( 'Gives SureCart\'s checkout, thank-you and account pages the BlueWorx look, and keeps the four pages SureCart needs present and published. Does nothing until SureCart is installed.', 'blueworx-labs-wordpress' ),
	'section'     => 'store',
),
```

No `'default'` key: it is on by default, per the spec.

- [ ] **Step 2: Give the new tab a capability and the feature a guide**

In `blueworx_guide_tab_capability()`'s `$map`, after `'appearance' => 'edit_theme_options',`:

```php
'store'           => 'manage_options',
```

In `blueworx_get_feature_guide_tasks()`, add a `'store_pages'` entry beside the others (same shape as `'login'`):

```php
'store_pages'     => array(
	array(
		'slug'  => '',
		'title' => $t( 'Finding your store\'s pages' ),
		'body'  => blueworx_guide_body(
			array(
				'where' => $t( 'Pages' ),
				'intro' => $t( 'Your store needs four pages: Shop, Checkout, Thank you and Dashboard. SureCart makes them, and BlueWorx keeps them dressed and published.' ),
				'steps' => array(
					$t( 'Open *Pages* and look for *Shop*, *Checkout*, *Thank you!* and *Dashboard*.' ),
					$t( 'If a yellow notice at the top of the admin says one is missing or in the trash, press *Put the missing pages back*.' ),
					$t( 'Open your site\'s checkout by pressing any Buy button.' ),
				),
				'then'  => $t( 'The checkout, the thank-you page and a customer\'s account page all wear the BlueWorx look. Leave those four pages alone: if you edit or delete one, the notice will tell you.' ),
			)
		),
	),
),
```

- [ ] **Step 3: Create the bootstrap file and require it**

`includes/store/store.php`:

```php
<?php
/**
 * Store pages: SureCart's checkout, thank-you and customer dashboard, dressed
 * in the BlueWorx look, and the check that keeps its four pages present.
 *
 * One feature, several files. Each file is one job, ported from the ClubHouse
 * plugin's dashboard with the club-specific parts replaced by filters other
 * plugins hook. See docs/store-pages-api.md.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Later tasks add one require_once per file here, in this order:
// pages, pages-notice, slot, views, actions, shell, context, assets, commerce, dashboard.
```

In `blueworx-labs-wordpress.php`, add after the `display-names.php` require (the last one):

```php
require_once BLUEWORX_LABS_PATH . 'includes/store/store.php';
```

- [ ] **Step 4: Run the guide format test**

Run: `php tests/php/guides-format-test.php`
Expected: passes, exit 0 (it checks every feature with a guide has the three parts).

- [ ] **Step 5: Write the Playwright check that the feature shows on Enhancements**

`tests/store-feature.spec.js`:

```js
import { test, expect, login, openSection } from './helpers.js';

// The feature is on by default and lives in its own Store section.
test('Store pages is listed under Store and is on by default', async ({ page }) => {
  await login(page);
  await page.goto('/wp-admin/admin.php?page=blueworx-enhancements');
  await openSection(page, 'store');
  const toggle = page.locator('input[name="blueworx_features[store_pages]"]');
  await expect(toggle).toBeVisible();
  await expect(toggle).toBeChecked();
});
```

Check `tests/helpers.js` `openSection()` and the settings page slug before running — copy the pattern from an existing spec such as `tests/feature-toggles.spec.js` if the input name or page slug differ, and adjust the two locators to match.

- [ ] **Step 6: Run it**

Run: `npx playwright test tests/store-feature.spec.js`
Expected: PASS (harness must be up: `node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up --plugin .`).

- [ ] **Step 7: Commit**

```bash
git add includes/features.php includes/guides.php includes/store/store.php blueworx-labs-wordpress.php tests/store-feature.spec.js
git commit -m "Register the Store pages feature"
```

---

### Task 2: Page existence and repair

**Files:**
- Create: `includes/store/pages.php`
- Modify: `includes/store/store.php` (add the require)
- Test: `tests/php/store-pages-test.php`
- Modify: `package.json` (`test:php` script — append `&& php tests/php/store-pages-test.php`)
- Source: `blueworx_labs_clubhouse/includes/membership/class-shop-pages.php` (whole file)

**Interfaces:**
- Produces: every `blueworx_store_*` function listed for `pages.php` in the inventory.

- [ ] **Step 1: Write the failing test**

`tests/php/store-pages-test.php`:

```php
<?php
/**
 * The store page rules: what state a page is in and what the repair may fix.
 *
 * Run with: php tests/php/store-pages-test.php
 *
 * @package BlueWorxLabs
 */

require __DIR__ . '/stubs.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

function blueworx_feature_enabled( $key ) {
	return true;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

require __DIR__ . '/../../includes/store/pages.php';

echo "A page's state\n";
check( 'no shop means no-shop whatever else is true', blueworx_store_decide( false, 5, 'publish' ), 'no-shop' );
check( 'a published page with an id is ok', blueworx_store_decide( true, 5, 'publish' ), 'ok' );
check( 'a trashed page is unpublished', blueworx_store_decide( true, 5, 'trash' ), 'unpublished' );
check( 'a draft is unpublished', blueworx_store_decide( true, 5, 'draft' ), 'unpublished' );
check( 'an id pointing at nothing is missing', blueworx_store_decide( true, 5, '' ), 'missing' );
check( 'no id is missing', blueworx_store_decide( true, 0, '' ), 'missing' );

echo "\nWhich problems the button can fix\n";
$pages    = blueworx_store_pages();
$problems = blueworx_store_problems( array( 'checkout' => 'ok', 'shop' => 'missing', 'dashboard' => 'unpublished', 'order-confirmation' => 'no-shop' ) );
check( 'only missing and unpublished are problems', array_keys( $problems ), array( 'shop', 'dashboard' ) );
check( 'every known page is repairable', array_keys( blueworx_store_repairable( $problems, $pages ) ), array( 'shop', 'dashboard' ) );
check( 'an unknown missing page is not', blueworx_store_repairable( array( 'mystery' => 'missing' ), $pages ), array() );
check( 'but an unknown unpublished one can be republished', array_keys( blueworx_store_repairable( array( 'mystery' => 'unpublished' ), $pages ) ), array( 'mystery' ) );

echo "\nThe option name is SureCart's own\n";
check( 'checkout', blueworx_store_option_name( 'checkout' ), 'surecart_checkout_page_id' );
$GLOBALS['options']['surecart_checkout_page_id'] = '12';
check( 'a stored id is read as an int', blueworx_store_page_id( 'checkout' ), 12 );
$GLOBALS['options']['surecart_checkout_page_id'] = 'junk';
check( 'junk reads as 0', blueworx_store_page_id( 'checkout' ), 0 );

echo "\nThe thank-you page is made only when nothing ever made one\n";
check( 'never created: yes', blueworx_store_should_create_confirmation( true, 0, '' ), true );
check( 'deleted (id on record, no post): no', blueworx_store_should_create_confirmation( true, 9, '' ), false );
check( 'present: no', blueworx_store_should_create_confirmation( true, 9, 'publish' ), false );
check( 'no shop: no', blueworx_store_should_create_confirmation( false, 0, '' ), false );
$page = blueworx_store_confirmation_page();
check( 'it is SureCart\'s own slug', $page['slug'], 'order-confirmation' );
check( 'and its own block', $page['content'], '<!-- wp:surecart/order-confirmation --> <!-- /wp:surecart/order-confirmation -->' );

echo "\nThe four pages\n";
check( 'are checkout, order-confirmation, dashboard, shop', array_keys( $pages ), array( 'checkout', 'order-confirmation', 'dashboard', 'shop' ) );

finish();
```

Look at the bottom of `tests/php/stubs.php` for the exact names of the `check()` and finishing helpers other tests call (some use `finish()`, some print a summary and `exit( $GLOBALS['failures'] ? 1 : 0 )`); use whatever `display-names-test.php` uses.

- [ ] **Step 2: Run it to see it fail**

Run: `php tests/php/store-pages-test.php`
Expected: fatal "Failed opening required '…/includes/store/pages.php'".

- [ ] **Step 3: Port `class-shop-pages.php` to `includes/store/pages.php`**

Port every method of `Blueworx_Clubhouse_Shop_Pages` to the function of the same job from the inventory, keeping the docblocks' reasoning (they explain SureCart's behaviour and are worth keeping). Changes from the source:

- `Blueworx_Clubhouse_SureCart_Products::is_active()` → `blueworx_store_surecart_active()`, defined here as:

```php
/**
 * Whether SureCart is loaded on this request.
 *
 * Asked inside hooks only: SureCart's folder sorts after this plugin's, so at
 * file scope the answer is always no.
 *
 * @return bool
 */
function blueworx_store_surecart_active() {
	return class_exists( 'SureCart' ) || defined( 'SURECART_PLUGIN_FILE' );
}
```

- Status strings are literals (`'no-shop'`, `'ok'`, `'missing'`, `'unpublished'`), not class constants.
- `Blueworx_Clubhouse_Link_Catalogue::forget_shop_targets()` has no equivalent here; drop both calls.
- `url()` becomes `blueworx_store_page_url( $key )` — same body: `''` unless status is `'ok'`, else `get_permalink()`.
- Keep the guards on `function_exists( 'get_option' )` etc. exactly as the source has them; they are what lets the CLI test load the file.

Then in `includes/store/store.php` add:

```php
require_once BLUEWORX_LABS_PATH . 'includes/store/pages.php';
```

- [ ] **Step 4: Run the test**

Run: `php tests/php/store-pages-test.php`
Expected: every line "ok", exit 0.

- [ ] **Step 5: Add it to `npm run test:php` and commit**

Append ` && php tests/php/store-pages-test.php` to the `test:php` script in `package.json`.

```bash
git add includes/store/pages.php includes/store/store.php tests/php/store-pages-test.php package.json
git commit -m "Store pages: know which SureCart pages exist and repair them"
```

---

### Task 3: The admin notice and the repair button

**Files:**
- Create: `includes/store/pages-notice.php`
- Modify: `includes/store/store.php`
- Test: `tests/php/store-notice-test.php`; `package.json` (`test:php`)
- Source: `blueworx_labs_clubhouse/includes/admin/class-shop-pages-controller.php`

**Interfaces:**
- Consumes: `blueworx_store_pages()`, `blueworx_store_problems()`, `blueworx_store_page_statuses()`, `blueworx_store_repairable()`, `blueworx_store_can_seed()`, `blueworx_store_repair()`, `blueworx_store_ensure_confirmation()`.
- Produces: `blueworx_store_notice_message()`, `blueworx_store_notice_html()`, `blueworx_store_render_notice()`, `blueworx_store_handle_repair()`; admin-post action `blueworx_store_repair`, nonce `blueworx_store_repair`.

- [ ] **Step 1: Write the failing test**

`tests/php/store-notice-test.php` (same preamble as Task 2's test, plus stubs `esc_html`, `esc_url` returning their input if `stubs.php` lacks them — check first):

```php
require __DIR__ . '/../../includes/store/pages.php';
require __DIR__ . '/../../includes/store/pages-notice.php';

$pages = blueworx_store_pages();

echo "Nothing wrong, nothing said\n";
check( 'null when there are no problems', blueworx_store_notice_message( array(), $pages, true ), null );
check( 'and no markup', blueworx_store_notice_html( null, 'x' ), '' );

echo "\nOne line per problem, in plain words\n";
$m = blueworx_store_notice_message( array( 'checkout' => 'missing', 'dashboard' => 'unpublished' ), $pages, true );
check( 'missing', $m['lines'][0], 'Your checkout page is missing, so nobody can pay and membership Join buttons fall back to your contact page.' );
check( 'unpublished', $m['lines'][1], 'Your customer dashboard is in the trash or unpublished, so members have nowhere to manage what they have paid for.' );
check( 'the button is offered', $m['button'], 'Put the missing pages back' );
check( 'no footnote when everything is fixable', $m['footnote'], '' );

echo "\nWhen SureCart cannot seed\n";
$m = blueworx_store_notice_message( array( 'checkout' => 'missing' ), $pages, false );
check( 'no button', $m['button'], '' );
check( 'the owner is told to finish setting the shop up', $m['footnote'], 'Open SureCart and finish setting the shop up.' );

echo "\nThe markup escapes what it prints\n";
$html = blueworx_store_notice_html( array( 'lines' => array( 'a <b>' ), 'button' => 'Go', 'footnote' => '' ), 'http://x/?a=1&b=2' );
check( 'prefix', false !== strpos( $html, '<strong>BlueWorx:</strong> your shop is not ready to take payments.' ), true );
check( 'line escaped', false !== strpos( $html, 'a &lt;b&gt;' ), true );
check( 'button present', false !== strpos( $html, '>Go</a>' ), true );

finish();
```

- [ ] **Step 2: Run it to see it fail**

Run: `php tests/php/store-notice-test.php` — Expected: fatal, file missing.

- [ ] **Step 3: Port the controller**

Port `message()`, `notice_html()`, `render_notice()`, `handle_repair()`, `action_url()` and `can_manage()` from the source to the inventory's names. The `Join` wording in the checkout consequence stays as it is in `blueworx_store_pages()` (it came across in Task 2). Change the notice's prefix to `<strong>BlueWorx:</strong>`. Hooks, at the bottom of the file:

```php
if ( blueworx_feature_enabled( 'store_pages' ) ) {
	add_action( 'admin_notices', 'blueworx_store_render_notice' );
	add_action( 'admin_post_blueworx_store_repair', 'blueworx_store_handle_repair' );
	// The thank-you page is made here rather than only on activation, because
	// the usual order is this plugin first and the shop afterwards.
	add_action( 'admin_init', 'blueworx_store_ensure_confirmation' );
}
```

`blueworx_store_render_notice()` returns early unless `blueworx_store_surecart_active()` — a site with no shop must never be nagged about one (the status would say `no-shop`, which is not a problem, but bail before reading four options).

Add `require_once BLUEWORX_LABS_PATH . 'includes/store/pages-notice.php';` to `store.php`.

- [ ] **Step 4: Run the test, then the whole PHP suite**

Run: `php tests/php/store-notice-test.php` — Expected: all ok.
Add ` && php tests/php/store-notice-test.php` to `test:php`; run `npm run test:php` — Expected: exit 0.

- [ ] **Step 5: Commit**

```bash
git add includes/store/pages-notice.php includes/store/store.php tests/php/store-notice-test.php package.json
git commit -m "Store pages: warn when a SureCart page is missing, with a button that puts it back"
```

---

### Task 4: The plugin slot, the view list and the action map

**Files:**
- Create: `includes/store/slot.php`, `includes/store/views.php`, `includes/store/actions.php`
- Modify: `includes/store/store.php`
- Test: `tests/php/store-views-test.php`; `package.json`
- Sources: `blueworx_labs_clubhouse/includes/dashboard/class-plugin-slot.php`, `class-dashboard-views.php`, `class-dashboard-actions.php`

**Interfaces:**
- Produces: everything listed for `slot.php`, `views.php`, `actions.php`; the `blueworx_store_views` filter.

- [ ] **Step 1: Write the failing test**

`tests/php/store-views-test.php` (same preamble; also stub `apply_filters( $hook, $value ) { return $value; }` if `stubs.php` lacks it):

```php
require __DIR__ . '/../../includes/store/slot.php';
require __DIR__ . '/../../includes/store/views.php';
require __DIR__ . '/../../includes/store/actions.php';

echo "The default views are SureCart's, dashboard first\n";
$defaults = blueworx_store_default_views();
check( 'seven of them', count( $defaults ), 7 );
check( 'in nav order', array_column( $defaults, 'key' ), array( 'dashboard', 'orders', 'invoices', 'billing', 'plans', 'profile', 'account' ) );

echo "\nNormalising what a filter hands back\n";
$n = blueworx_store_normalize_views( array( array( 'key' => 'club', 'label' => 'Club', 'shortcode' => 'club_panel' ) ) );
check( 'dashboard is put back, first', $n[0]['key'], 'dashboard' );
check( 'the plugin view is kept', $n[1]['key'], 'club' );
check( 'missing fields get defaults', $n[1]['where'], 'both' );
check( 'blocks default empty', $n[1]['blocks'], array() );
$n = blueworx_store_normalize_views( array( 'junk', array( 'label' => 'no key' ), array( 'key' => 'a' ), array( 'key' => 'a' ) ) );
check( 'junk and duplicates are dropped', array_column( $n, 'key' ), array( 'dashboard', 'a' ) );

echo "\nWhere a view sits\n";
$views = blueworx_store_normalize_views( array(
	array( 'key' => 'dashboard', 'where' => 'both' ),
	array( 'key' => 'orders', 'where' => 'side' ),
	array( 'key' => 'billing', 'where' => 'bar' ),
) );
check( 'sidebar', array_column( blueworx_store_views_side( $views ), 'key' ), array( 'dashboard', 'orders' ) );
check( 'phone bar', array_column( blueworx_store_views_bar( $views ), 'key' ), array( 'dashboard', 'billing' ) );

echo "\nWhich view an address means\n";
check( 'a known key', blueworx_store_resolve_view( 'orders', $views ), 'orders' );
check( 'junk lands on the dashboard', blueworx_store_resolve_view( 'nope', $views ), 'dashboard' );
check( 'find', blueworx_store_find_view( 'billing', $views )['where'], 'bar' );
check( 'find nothing', blueworx_store_find_view( 'x', $views ), null );

echo "\nThe slot answers '' for anything it cannot render\n";
check( 'no sources', blueworx_store_slot_block( 'surecart/customer-orders' ), '' );
blueworx_store_slot_set_sources(
	static fn ( $n ) => 'surecart/customer-orders' === $n ? '<p>orders</p>' : null,
	static fn ( $t ) => 'club' === $t ? '<p>club</p>' : null
);
check( 'a block', blueworx_store_slot_block( 'surecart/customer-orders' ), '<p>orders</p>' );
check( 'an unregistered block', blueworx_store_slot_block( 'other' ), '' );
check( 'a shortcode', blueworx_store_slot_shortcode( 'club' ), '<p>club</p>' );
blueworx_store_slot_set_sources( static function ( $n ) { throw new RuntimeException( 'boom' ); }, null );
check( 'a source that throws answers empty', blueworx_store_slot_block( 'x' ), '' );

echo "\nAction addresses\n";
$exists = static fn ( $controller, $method ) => 'cancel' === $method;
check( 'a known model with a real method', blueworx_store_is_action( 'subscription', 'cancel', $exists ), true );
check( 'a method the controller lacks', blueworx_store_is_action( 'subscription', 'fly', $exists ), false );
check( 'an unknown model', blueworx_store_is_action( 'widget', 'cancel', $exists ), false );
check( 'subscription belongs under plans', blueworx_store_action_view( 'subscription' ), 'plans' );
check( 'payment_method under account', blueworx_store_action_view( 'payment_method' ), 'account' );
check( 'download has no panel', blueworx_store_action_view( 'download' ), '' );
check( 'the wrapper block', blueworx_store_action_block(), 'surecart/dashboard-page' );

finish();
```

- [ ] **Step 2: Run it to see it fail** — `php tests/php/store-views-test.php` → fatal.

- [ ] **Step 3: Port the three files**

`slot.php`: port `Blueworx_Clubhouse_Plugin_Slot` verbatim into the four functions, holding the two callables in `$GLOBALS['blueworx_store_slot_blocks']` / `$GLOBALS['blueworx_store_slot_shortcodes']`. The error-log line reads `BlueWorx: store panel "%s" failed to render: %s`.

`views.php`:
- `blueworx_store_default_views()` returns the seven entries from `Blueworx_Clubhouse_Dashboard_Views::all()` **without** the `bookings` entry and **without** the `requires` and `panel` keys. Keep `key, label, title, lede, icon, where, blocks, shortcode`. Keep the comments explaining Billing and Account.
- `blueworx_store_normalize_views( $views )`: for each item, skip if not an array or `key` is empty or already seen; fill defaults `label => ucfirst(key)`, `title => label`, `lede => ''`, `icon => 'layout-dashboard'`, `icon_svg => ''`, `where => 'both'`, `blocks => []`, `shortcode => ''`; if no `dashboard` survives, unshift the default dashboard entry from `blueworx_store_default_views()`; if it is present but not first, move it first.
- `blueworx_store_views()`: memoise in a static; base list is `blueworx_store_default_views()` when `blueworx_store_surecart_active()`, else only its first (dashboard) entry; then:

```php
/**
 * Filters the customer dashboard's views, in nav order.
 *
 * Add a view for your plugin's panel, remove one, or reorder them. See
 * docs/store-pages-api.md for the shape of each entry.
 *
 * @param array $views Views, each an array with key, label, title, lede, icon, where, blocks, shortcode.
 */
$views = blueworx_store_normalize_views( apply_filters( 'blueworx_store_views', $base ) );
```

- `side()`, `bar()`, `resolve()`, `find()` port straight.

`actions.php`: port `Blueworx_Clubhouse_Dashboard_Actions` verbatim: the two maps become the return values of `blueworx_store_action_controllers()` and `blueworx_store_action_views()` (private helpers, fine to name so), the check seam is `$GLOBALS['blueworx_store_action_check']`, and `blueworx_store_action_block()` returns `'surecart/dashboard-page'`.

Add the three requires to `store.php` after `pages-notice.php`.

- [ ] **Step 4: Run the test; add it to `test:php`; commit**

```bash
git add includes/store/slot.php includes/store/views.php includes/store/actions.php includes/store/store.php tests/php/store-views-test.php package.json
git commit -m "Store pages: the dashboard's views, other plugins' panels, and SureCart's action addresses"
```

---

### Task 5: The shell — every piece of markup

**Files:**
- Create: `includes/store/shell.php`
- Modify: `includes/store/store.php`
- Test: `tests/php/store-shell-test.php`; `package.json`
- Source: `blueworx_labs_clubhouse/includes/render/class-dashboard-shell.php` (all 525 lines)

**Interfaces:**
- Consumes: `blueworx_store_views_side()`, `blueworx_store_views_bar()`, `blueworx_store_find_view()`.
- Produces: everything listed for `shell.php`. `blueworx_store_shell_page( $args )` takes the same keys as the source's `page()` with `club_name` renamed `site_name`: `views, current, panels, home_url, site_name, logo_url, base, logout_url, member_name, member_email`. `blueworx_store_shell_checkout( $args )` keys: `site_name, logo_url, home_url, home_label, body, footnote, links`.

- [ ] **Step 1: Write the failing test**

`tests/php/store-shell-test.php` (preamble as before, then require `views.php` and `shell.php`):

```php
$views = blueworx_store_normalize_views( array(
	array( 'key' => 'dashboard', 'label' => 'Dashboard', 'title' => 'Your account', 'icon' => 'layout-dashboard', 'where' => 'both' ),
	array( 'key' => 'orders', 'label' => 'Orders', 'title' => 'Orders', 'icon' => 'shopping-cart', 'where' => 'side' ),
) );
$html = blueworx_store_shell_page( array(
	'views'        => $views,
	'current'      => 'orders',
	'panels'       => array( 'dashboard' => '<p>home</p>', 'orders' => '<p>orders</p>' ),
	'home_url'     => 'http://x/',
	'site_name'    => 'Fixture & Co',
	'base'         => 'http://x/account/',
	'logout_url'   => 'http://x/out/?a=1&b=2',
	'member_name'  => 'Pat Lee',
	'member_email' => 'pat@example.com',
) );

echo "The frame\n";
check( 'root carries the design system and our own class', false !== strpos( $html, '<div class="bw-admin bw-page bw-store" data-bw-store data-view-initial="orders">' ), true );
check( 'no clubhouse class survives', false === strpos( $html, 'clubhouse' ), true );
check( 'the site name is escaped', false !== strpos( $html, 'Fixture &amp; Co' ), true );
check( 'ampersands are not double-escaped', false === strpos( $html, '&amp;amp;' ), true );
check( 'the current panel is shown', false !== strpos( $html, 'data-view="orders" role="tabpanel"' ), true );
check( 'the other is hidden', 1 === preg_match( '/data-view="dashboard"[^>]*hidden/', $html ), true );
check( 'view links build on the base', false !== strpos( $html, 'href="http://x/account/?view=orders"' ), true );
check( 'initials from the name', blueworx_store_initials( 'Pat Lee' ), 'PL' );

echo "\nThe checkout frame\n";
$html = blueworx_store_shell_checkout( array(
	'site_name'  => 'Fixture',
	'logo_url'   => '',
	'home_url'   => 'http://x/',
	'home_label' => 'Back to Fixture',
	'body'       => '<p id="shop-content">SHOP</p>',
	'footnote'   => '',
	'links'      => array( array( 'label' => 'Terms', 'href' => 'http://x/terms/' ), array( 'label' => '', 'href' => 'http://x/none/' ) ),
) );
check( 'root', false !== strpos( $html, '<div class="bw-admin bw-checkout">' ), true );
check( 'one h1', substr_count( $html, '<h1' ), 1 );
check( 'the body is passed through untouched', false !== strpos( $html, '<p id="shop-content">SHOP</p>' ), true );
check( 'a link with no label is skipped', substr_count( $html, '<nav class="bw-checkout__links"' ), 1 );
check( 'and the real one is drawn', false !== strpos( $html, '>Terms</a>' ), true );
check( 'no nav offered', false === strpos( $html, 'bw-secnav' ), true );

echo "\nSmaller pieces\n";
check( 'a bare view url', blueworx_store_view_url( 'orders' ), '?view=orders' );
check( 'appended to a query', blueworx_store_view_url( 'orders', 'http://x/?page_id=4' ), 'http://x/?page_id=4&view=orders' );
check( 'a card', blueworx_store_shell_card( '', '<p>x</p>' ), '<div class="bw-card"><div class="bw-card__body"><p>x</p></div></div>' );
check( 'a known icon', false !== strpos( blueworx_store_shell_icon( 'lock' ), '<svg' ), true );
check( 'an unknown icon with no svg is empty', blueworx_store_shell_icon( 'nope' ), '' );
check( 'a supplied svg is used', blueworx_store_shell_icon( 'nope', '<path d="M1 1"/>' ), '<svg class="bw-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 1"/></svg>' );

finish();
```

Before running, read the source's `card()` (line 474) and `icon()` (line 506) and make the two expected strings above match the exact markup they emit (the wrapper attributes in `icon()` must be copied, not guessed).

- [ ] **Step 2: Run it to see it fail** — fatal, file missing.

- [ ] **Step 3: Port the shell**

Port every method of `Blueworx_Clubhouse_Dashboard_Shell` (lines 43–525) into `shell.php`, one function each, private helpers named `blueworx_store_shell_sidebar`, `_panels`, `_head`, `_view`, `_checkout_head`, `_checkout_foot`, `_nav`, `_tabbar`. Apply the class-name table in Global Constraints to every string. Other changes:

- `Blueworx_Clubhouse_Dashboard_Views::side()/bar()` → `blueworx_store_views_side()/bar()`.
- `club_name` → `site_name` in `$args`; the sidebar's "Member area" sub-line becomes "Your account".
- `icon( $name )` becomes `blueworx_store_shell_icon( $name, $svg = '' )`: the eight inline Lucide paths stay; when `$name` is not in the set and `$svg` is non-empty, wrap `$svg` in the same `<svg …>` element. Callers that draw a view's icon pass `$view['icon'], $view['icon_svg']`.
- `checkout()`'s h1 text stays "Checkout"; `bare()`'s "Back to the club site" fallback becomes "Back to the site".

Add `require_once BLUEWORX_LABS_PATH . 'includes/store/shell.php';` to `store.php`.

- [ ] **Step 4: Run the test; add it to `test:php`; commit**

```bash
git add includes/store/shell.php includes/store/store.php tests/php/store-shell-test.php package.json
git commit -m "Store pages: the frame around checkout, thank-you and the dashboard"
```

---

### Task 6: Context, assets, template — and the dressed checkout and thank-you pages

**Files:**
- Create: `includes/store/context.php`, `includes/store/assets.php`, `includes/store/commerce.php`, `includes/store/template.php`, `assets/css/store.css`, `assets/css/store-surecart.css`
- Modify: `includes/store/store.php`, `tests/global-setup.js`
- Test: `tests/store-checkout.spec.js`
- Sources: `blueworx_labs_clubhouse/includes/dashboard/class-commerce-pages.php`, `class-dashboard-assets.php`, `class-member-dashboard.php` (lines 207–260 for logo/name/email/logout), `templates/commerce.php`, `assets/bw/bw.css` lines 622–767, `assets/bw/surecart.css`

**Interfaces:**
- Consumes: shell, pages, views.
- Produces: `blueworx_store_context()`, `blueworx_store_page_key()`, `blueworx_store_enqueue_frame()`, `blueworx_store_enqueue_dashboard()`, `blueworx_store_dress_content()`, `blueworx_store_checkout_links()`, the two filters `blueworx_store_context` and `blueworx_store_checkout_links`. Task 7 adds the `dashboard` branch to `blueworx_store_dress_content()` — leave a clear seam (see Step 4).

- [ ] **Step 1: Seed fixture pages in the harness**

Extend `tests/global-setup.js`. After the existing reachability check, when `isHarness` and `.wp-test/wp/wp-load.php` exists, write a PHP file to the OS temp dir and run it with `php` (copy the `spawnSync` approach from `blueworx_labs_clubhouse/tests/global-setup.js`; this repo's file is an ES module, so use `import { spawnSync } from 'node:child_process'`). The PHP:

```php
<?php
require_once '<abs path to .wp-test/wp/wp-load.php>';

function bw_fixture_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing instanceof WP_Post ) {
		wp_update_post( array( 'ID' => $existing->ID, 'post_status' => 'publish' ) );
		return $existing->ID;
	}
	return (int) wp_insert_post( array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $content,
	) );
}

// Stand-ins for the pages SureCart seeds. CI has no SureCart; the stored id IS
// the contract, so a page the option names is the honest fixture.
update_option( 'surecart_checkout_page_id', bw_fixture_page( 'checkout-fixture', 'Checkout fixture', '<p id="shop-content">SHOP CONTENT</p>' ) );
update_option( 'surecart_order_confirmation_page_id', bw_fixture_page( 'thanks-fixture', 'Thank you fixture', '<p id="shop-content">THANKS CONTENT</p>' ) );
update_option( 'surecart_dashboard_page_id', bw_fixture_page( 'dashboard-fixture', 'Dashboard fixture', '<p id="foreign-content">FOREIGN</p>' ) );
bw_fixture_page( 'claimed-fixture', 'Claimed dashboard fixture', '<p id="claimed-content">CLAIMED</p>' );

// A member who is not an administrator, for the dashboard specs.
if ( ! get_user_by( 'login', 'member' ) ) {
	wp_insert_user( array( 'user_login' => 'member', 'user_pass' => 'wptest-member-pw', 'user_email' => 'member@example.com', 'display_name' => 'Pat Member', 'role' => 'subscriber' ) );
}
```

Also write the test hooks mu-plugin to `.wp-test/wp/wp-content/mu-plugins/blueworx-store-test-hooks.php` (create the directory if needed) — its content is in Task 7 Step 1; for this task it can be an empty `<?php` file. Fail loudly (throw) if `php` exits non-zero.

- [ ] **Step 2: Write the failing checkout spec**

`tests/store-checkout.spec.js`:

```js
import { test, expect } from './helpers.js';

// The frame goes on whichever post SureCart records as its checkout. The
// harness has no SureCart, so checkout-fixture (seeded by global-setup.js,
// with the option pointed at it) stands in. These assert our frame and the
// shop's content surviving it — never SureCart's fields.
const CHECKOUT = '/checkout-fixture/';
const THANKS = '/thanks-fixture/';

test('the checkout wears its own frame', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('.bw-admin.bw-checkout')).toHaveCount(1);
  await expect(page.locator('.bw-checkout__head')).toBeVisible();
  await expect(page.locator('.bw-checkout__foot')).toBeVisible();
});

test("the shop's own content is passed through untouched", async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('#shop-content')).toHaveText('SHOP CONTENT');
});

test('a buyer is offered no nav to wander off into', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('.bw-secnav')).toHaveCount(0);
  await expect(page.locator('.bw-store__tabbar')).toHaveCount(0);
});

test('the page has exactly one heading at the top level', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('h1')).toHaveCount(1);
});

test('the design system and the field theme are asked for in the head', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('head link[rel="stylesheet"][href*="blueworx-admin-design.css"]')).toHaveCount(1);
  await expect(page.locator('head link[rel="stylesheet"][href*="store-surecart.css"]')).toHaveCount(1);
  await expect(page.locator('head link[rel="stylesheet"][href*="store.css"]')).toHaveCount(1);
});

test('the checkout owns the whole page, with no theme chrome around it', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('.wp-block-template-part')).toHaveCount(0);
  await expect(page.locator('header.wp-block-template-part, footer.wp-block-template-part')).toHaveCount(0);
});

test('the footer stacks into full-width targets on a phone', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(CHECKOUT);
  const back = page.locator('.bw-checkout__back');
  await expect(back).toBeVisible();
  const box = await back.boundingBox();
  expect(box.height).toBeGreaterThanOrEqual(44);
});

test('the thank-you page wears the bare frame', async ({ page }) => {
  await page.goto(THANKS);
  await expect(page.locator('.bw-admin.bw-page.bw-store')).toHaveCount(1);
  await expect(page.locator('h1')).toHaveText('Thank you');
  await expect(page.locator('#shop-content')).toHaveText('THANKS CONTENT');
  await expect(page.locator('head link[rel="stylesheet"][href*="store-surecart.css"]')).toHaveCount(0);
});

test('an ordinary page is left alone', async ({ page }) => {
  await page.goto('/claimed-fixture/');
  await expect(page.locator('.bw-admin')).toHaveCount(0);
  await expect(page.locator('#claimed-content')).toHaveText('CLAIMED');
});
```

- [ ] **Step 3: Run it to see it fail**

Run: `npx playwright test tests/store-checkout.spec.js` — Expected: the frame assertions fail (no `.bw-checkout`).

- [ ] **Step 4: Write context, assets, template, stylesheets and commerce**

`context.php`:

```php
/**
 * Who this site is and who is signed in, for the frame to draw.
 *
 * WordPress's own answers, then the blueworx_store_context filter, so a
 * plugin that knows better — a club with its own crest and login page —
 * can say so without this file knowing it exists.
 *
 * @return array site_name, logo_url, home_url, home_label, login_url, logout_url, member_name, member_email.
 */
function blueworx_store_context() {
	static $context = null;
	if ( null !== $context ) {
		return $context;
	}
	/**
	 * Filters the site and member details the store pages draw.
	 *
	 * @param array $context See blueworx_store_default_context().
	 */
	$context = array_merge( blueworx_store_default_context(), (array) apply_filters( 'blueworx_store_context', blueworx_store_default_context() ) );
	return $context;
}
```

`blueworx_store_default_context()` builds: `site_name` from `get_bloginfo( 'name' )`; `logo_url` from `get_site_icon_url( 64 )`, else `wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'thumbnail' )`, else `''`; `home_url` = `home_url( '/' )`; `home_label` = `blueworx_store_back_label( site_name )`; `login_url` = `wp_login_url( $current )` where `$current` is the request URL (`home_url( add_query_arg( array() ) )`) — the login feature already filters `wp_login_url`; `logout_url` = `wp_logout_url( home_url( '/' ) )`; `member_name`/`member_email` from `wp_get_current_user()` as the source's `member_name()`/`member_email()` do. Every WordPress call guarded with `function_exists` so the file loads under the CLI stubs. `blueworx_store_back_label()` ports `Commerce_Pages::back_label()` with "Back to the site" as the fallback.

`assets.php`: port `Dashboard_Assets`. Handles: `blueworx-store` → `assets/css/store.css` (depends on `blueworx-admin-design`), `blueworx-store-surecart` → `assets/css/store-surecart.css` (depends on `blueworx-store`), `blueworx-store-dashboard` → `assets/js/store-dashboard.js` (deferred, footer; Task 7 adds the file). `blueworx_store_page_key( $post_id )` returns `'checkout'`, `'order-confirmation'` or `'dashboard'` by comparing against the three `blueworx_store_page_id()` answers, `''` otherwise and for `$post_id <= 0`. `blueworx_store_declare_assets()` on `wp_enqueue_scripts`: register all three, then if the queried object's key is non-empty call `blueworx_store_enqueue_frame()`, plus the SureCart style when `blueworx_store_wants_surecart_style( $key )` (checkout only), plus `blueworx_store_enqueue_dashboard()` when the key is `dashboard`. `blueworx_store_enqueue_frame()` calls `blueworx_admin_design_enqueue()` then enqueues `blueworx-store`. `blueworx_store_enqueue_dashboard()` = frame + `blueworx-store-dashboard` script + `blueworx_store_enqueue_shop_assets()` (port of `Member_Dashboard::enqueue_shop_assets()`).

`template.php`: port `templates/commerce.php` verbatim (its comment updated to name this plugin).

`assets/css/store.css`: copy `assets/bw/bw.css` lines 622–767 **except** the `.clubhouse-profile*` rules (lines 670–690 — they are ClubHouse's profile card and stay there), then copy `assets/bw/surecart.css` lines 69 to the end (the `.clubhouse-checkout*` rules; verify the line where the token mapping ends and the checkout layout begins by reading the file). Apply the class rename table with a global replace. Header comment: "Store pages layout. Sits on top of blueworx-admin-design.css, same tokens, same scoping: every rule is under .bw-store or .bw-checkout."

`assets/css/store-surecart.css`: copy `assets/bw/surecart.css` lines 1 to just before the checkout layout rules (the SureCart token mapping only). Apply the rename table (there should be no `clubhouse` left; grep to confirm).

`commerce.php`: port `Commerce_Pages`. `blueworx_store_template_for()`, `blueworx_store_serve_template()` (template path `BLUEWORX_LABS_PATH . 'includes/store/template.php'`), `blueworx_store_strip_post_title()`, and `blueworx_store_dress_content()`:

```php
function blueworx_store_dress_content( $content ) {
	static $rendering = false;
	$content = (string) $content;
	if ( $rendering ) {
		return $content;
	}
	if ( ! function_exists( 'is_singular' ) || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$key = blueworx_store_page_key( (int) get_the_ID() );
	if ( '' === $key ) {
		return $content;
	}
	$rendering = true;
	try {
		$context = blueworx_store_context();
		if ( 'checkout' === $key ) {
			return blueworx_store_shell_checkout( array(
				'site_name'  => $context['site_name'],
				'logo_url'   => $context['logo_url'],
				'home_url'   => $context['home_url'],
				'home_label' => $context['home_label'],
				'body'       => $content,
				'footnote'   => '',
				'links'      => blueworx_store_checkout_links(),
			) );
		}
		if ( 'order-confirmation' === $key ) {
			return blueworx_store_shell_bare(
				'Thank you',
				'Your order is confirmed. A receipt is on its way by email.',
				blueworx_store_shell_card( '', $content ),
				$context['home_url'],
				$context['site_name']
			);
		}
		// 'dashboard' is handled in dashboard.php (Task 7), which wraps this
		// function's answer: until then the page is left as it is.
		return blueworx_store_dashboard_content( $content, $context );
	} finally {
		$rendering = false;
	}
}
```

For this task, define a placeholder-free stub at the bottom of `commerce.php` that Task 7 moves into `dashboard.php`:

```php
if ( ! function_exists( 'blueworx_store_dashboard_content' ) ) {
	/**
	 * The dashboard page's content. Replaced by dashboard.php.
	 *
	 * @param string $content The page's own content.
	 * @param array  $context From blueworx_store_context().
	 * @return string
	 */
	function blueworx_store_dashboard_content( $content, $context ) {
		return $content;
	}
}
```

`blueworx_store_checkout_links()`: default is `array()` unless `get_option( 'wp_page_for_privacy_policy' )` names a published page, in which case one link `['label' => 'Privacy policy', 'href' => get_permalink( $id )]`; then `apply_filters( 'blueworx_store_checkout_links', $links, blueworx_store_context() )`; then drop any entry whose trimmed `label` or `href` is empty.

Hooks at the bottom of `commerce.php` and `assets.php`, inside `if ( blueworx_feature_enabled( 'store_pages' ) )`: `the_content` → `blueworx_store_dress_content` at 30; `render_block` → `blueworx_store_strip_post_title` at 10 with 3 args; `template_include` → `blueworx_store_serve_template`; `wp_enqueue_scripts` → `blueworx_store_declare_assets`.

Add the requires to `store.php`: `context.php`, `assets.php`, `commerce.php` (after `shell.php`).

- [ ] **Step 5: Run the spec, then the PHP suite**

Run: `npx playwright test tests/store-checkout.spec.js` — Expected: all pass.
Run: `npm run test:php` — Expected: exit 0 (the new files must load under the stubs; if one fatals, guard the WordPress call it reaches at file scope).

- [ ] **Step 6: Commit**

```bash
git add includes/store assets/css/store.css assets/css/store-surecart.css tests/global-setup.js tests/store-checkout.spec.js
git commit -m "Store pages: dress SureCart's checkout and thank-you pages"
```

---

### Task 7: The dashboard, its routing, and the panel and address filters

**Files:**
- Create: `includes/store/dashboard.php`, `assets/js/store-dashboard.js`
- Modify: `includes/store/commerce.php` (remove the stub), `includes/store/store.php`, `tests/global-setup.js` (the mu-plugin content)
- Test: `tests/php/store-dashboard-test.php`, `tests/store-dashboard.spec.js`; `package.json`
- Sources: `blueworx_labs_clubhouse/includes/dashboard/class-member-dashboard.php`, `assets/js/member-area.js`

**Interfaces:**
- Consumes: everything from Tasks 4–6.
- Produces: `blueworx_store_dashboard_screen( $base, $home )`, `blueworx_store_dashboard_url()`, `blueworx_store_redirect_to()`, the `blueworx_store_panel` and `blueworx_store_dashboard_url` filters.

- [ ] **Step 1: The test hooks mu-plugin**

In `tests/global-setup.js`, write this to `.wp-test/wp/wp-content/mu-plugins/blueworx-store-test-hooks.php` (overwriting each run):

```php
<?php
/**
 * Plugin Name: BlueWorx store pages test hooks
 * Description: Exercises the five blueworx_store_* filters the way another plugin would. Test fixture only.
 */

add_shortcode( 'bw_store_test_panel', static function () {
	return '<p id="test-panel">TEST PANEL</p>';
} );

add_filter( 'blueworx_store_views', static function ( $views ) {
	$views[] = array(
		'key'       => 'club',
		'label'     => 'Club',
		'title'     => 'Your club',
		'lede'      => 'A view another plugin added.',
		'icon'      => 'star',
		'icon_svg'  => '<path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7z"/>',
		'where'     => 'both',
		'shortcode' => 'bw_store_test_panel',
	);
	return $views;
} );

add_filter( 'blueworx_store_panel', static function ( $html, $key ) {
	return 'dashboard' === $key ? '<p id="test-welcome">WELCOME</p>' . $html : $html;
}, 10, 2 );

add_filter( 'blueworx_store_context', static function ( $context ) {
	$context['site_name']  = 'Fixture Club';
	$context['home_label'] = 'Back to Fixture Club';
	return $context;
} );

add_filter( 'blueworx_store_checkout_links', static function ( $links ) {
	$links[] = array( 'label' => 'Fixture terms', 'href' => home_url( '/terms-fixture/' ) );
	return $links;
} );

// Claims the dashboard address only when the request asks it to, so the same
// site can prove both "dressed here" and "redirected there".
add_filter( 'blueworx_store_dashboard_url', static function ( $url ) {
	return isset( $_GET['bw_claim'] ) ? home_url( '/claimed-fixture/' ) : $url; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
} );
```

- [ ] **Step 2: Write the failing PHP test for the pure routing**

`tests/php/store-dashboard-test.php` (preamble; require `slot.php`, `views.php`, `actions.php`, `shell.php`, `dashboard.php`; stub `apply_filters` to return its value):

```php
echo "Where a request is sent\n";
// (queried, dashboard_id, claimed_url, signed_in, view, login_url, model, action, id)
check( 'not the dashboard page: stay', blueworx_store_redirect_to( 3, 4, '', true, '', 'http://x/login/' ), '' );
check( 'no id recorded: stay, even at 0', blueworx_store_redirect_to( 0, 0, '', true, '', 'http://x/login/' ), '' );
check( 'the dashboard, signed out: login', blueworx_store_redirect_to( 4, 4, '', false, '', 'http://x/login/' ), 'http://x/login/' );
check( 'the dashboard, signed in, unclaimed: stay', blueworx_store_redirect_to( 4, 4, '', true, 'orders', 'http://x/login/' ), '' );
check( 'claimed: go there', blueworx_store_redirect_to( 4, 4, 'http://x/member/', true, '', 'http://x/login/' ), 'http://x/member/' );
check( 'claimed, with the panel kept', blueworx_store_redirect_to( 4, 4, 'http://x/member/', true, 'orders', 'http://x/login/' ), 'http://x/member/?view=orders' );
check( 'claimed, with an action kept', blueworx_store_redirect_to( 4, 4, 'http://x/member/', true, 'orders', 'http://x/login/', 'order', 'show', 'ord_1' ), 'http://x/member/?view=orders&model=order&action=show&id=ord_1' );
check( 'claimed, signed out: still the claim (the claimant guards its own door)', blueworx_store_redirect_to( 4, 4, 'http://x/member/', false, '', 'http://x/login/' ), 'http://x/member/' );

echo "\nThe overview links to every other view\n";
$views = blueworx_store_normalize_views( array( array( 'key' => 'dashboard' ), array( 'key' => 'club', 'label' => 'Club', 'lede' => 'L' ) ) );
$html  = blueworx_store_overview( $views, 'http://x/', 'http://x/acct/' );
check( 'one quick link', substr_count( $html, 'bw-store__quick"' ), 1 );
check( 'to the club view', false !== strpos( $html, 'href="http://x/acct/?view=club"' ), true );
check( 'none to itself', false === strpos( $html, '?view=dashboard' ), true );
$html = blueworx_store_overview( blueworx_store_normalize_views( array() ), 'http://x/', '' );
check( 'nothing else to offer: empty, so the panel filter can fill it and the empty state comes after', $html, '' );

finish();
```

- [ ] **Step 3: Run it to see it fail** — fatal, file missing.

- [ ] **Step 4: Write `dashboard.php`**

Port `Member_Dashboard` with these changes:

- `blueworx_store_redirect_to( $queried_id, $dashboard_id, $claimed_url, $signed_in, $view, $login_url, $model = '', $action = '', $id = '' )`:

```php
function blueworx_store_redirect_to( $queried_id, $dashboard_id, $claimed_url, $signed_in, $view, $login_url, $model = '', $action = '', $id = '' ) {
	$queried_id   = (int) $queried_id;
	$dashboard_id = (int) $dashboard_id;
	if ( $dashboard_id <= 0 || $queried_id !== $dashboard_id ) {
		return '';
	}
	$claimed_url = trim( (string) $claimed_url );
	if ( '' === $claimed_url ) {
		return $signed_in ? '' : (string) $login_url;
	}
	$target = '' === trim( (string) $view ) ? $claimed_url : blueworx_store_view_url( trim( (string) $view ), $claimed_url );
	if ( '' === trim( (string) $model ) || '' === trim( (string) $action ) ) {
		return $target;
	}
	$target .= ( false === strpos( $target, '?' ) ? '?' : '&' )
		. 'model=' . rawurlencode( trim( (string) $model ) )
		. '&action=' . rawurlencode( trim( (string) $action ) );
	return '' === trim( (string) $id ) ? $target : $target . '&id=' . rawurlencode( trim( (string) $id ) );
}
```

- `blueworx_store_dashboard_url()`:

```php
/**
 * Filters where the customer dashboard lives.
 *
 * Empty means SureCart's own dashboard page, dressed by this plugin. A URL
 * means another plugin serves the dashboard there; SureCart's page then
 * redirects to it with the view and any action preserved.
 *
 * @param string $url '' by default.
 */
return trim( (string) apply_filters( 'blueworx_store_dashboard_url', '' ) );
```

- `blueworx_store_route()` on `template_redirect` priority 5: reads `get_queried_object_id()`, `blueworx_store_page_id( 'dashboard' )`, `blueworx_store_dashboard_url()`, `is_user_logged_in()`, `blueworx_store_requested_view()`, `blueworx_store_context()['login_url']`, the requested model/action/record; `wp_safe_redirect( $target, 302 ); exit;` when non-empty. `wp_safe_redirect` refuses a foreign host, which is right: the claim must be on this site.
- `blueworx_store_dashboard_screen( $base, $home )`: port `screen()`. `$views = blueworx_store_views()`. For each view, the panel body is `blueworx_store_view_body( $view, $home )` (or `blueworx_store_overview()` for `dashboard`), **then** filtered:

```php
/**
 * Filters one dashboard panel's rendered body.
 *
 * @param string $html    The panel as this plugin drew it; '' draws the empty state.
 * @param string $key     The view's key.
 * @param array  $context From blueworx_store_context().
 */
$body = (string) apply_filters( 'blueworx_store_panel', $body, $key, $context );
if ( '' === trim( $body ) ) {
	$body = blueworx_store_not_set_up( $home );
}
```

  Note the ordering change from the source: the source draws the empty state inside `view_body()` and `overview()`; here both return `''` when nothing rendered, so a plugin can fill an empty panel through the filter, and the empty state is drawn once, after the filter, for whatever is still empty. That keeps today's behaviour for a club: welcome pack alone on the overview shows the pack, not the pack plus an empty-state card. The welcome pack, profile panel renderer and accent CSS are gone — they are ClubHouse's and arrive through the panel filter. Shell args use `site_name`, `logo_url`, `logout_url`, `member_name`, `member_email` from `blueworx_store_context()`.
- `blueworx_store_view_body( $view, $home_url )`: shortcode wins; else each block in a card; no `panel` key any more.
- `blueworx_store_action_panel()`, `blueworx_store_requested_view()`, `blueworx_store_requested_record()`: port straight, using `actions.php` and `slot.php`.
- `blueworx_store_dashboard_content( $content, $context )` (move here from `commerce.php`, delete the stub there): when `blueworx_store_dashboard_url()` is non-empty return `$content` (the route already redirected; this is belt and braces); otherwise return `blueworx_store_dashboard_screen( get_permalink( get_the_ID() ), $context['home_url'] )`, and if that is `''` return `$content`.
- Hooks: `template_redirect` → `blueworx_store_route` at 5; `init` → `blueworx_store_slot_install` (the real block/shortcode renderers, installed once WordPress has registered them). Both inside the feature check.

`assets/js/store-dashboard.js`: copy `member-area.js`, replacing `[data-clubhouse-member]` with `[data-bw-store]` and `.clubhouse-member__panel` / `__tab` / `__navtab` etc. with the `bw-store__` names; `data-member-title` / `data-member-lede` stay. Run `npm run lint` once at the end of the task (not in a loop); it lints `assets/js`.

Add `require_once BLUEWORX_LABS_PATH . 'includes/store/dashboard.php';` last in `store.php`.

- [ ] **Step 5: Run the PHP test; add it to `test:php`**

Run: `php tests/php/store-dashboard-test.php` — all ok. `npm run test:php` — exit 0.

- [ ] **Step 6: Write the dashboard browser spec**

`tests/store-dashboard.spec.js`:

```js
import { test, expect, login } from './helpers.js';

// SureCart's dashboard page is dashboard-fixture (option pointed at it by
// global-setup.js). The harness has no SureCart, so the only views are the
// overview and the one the test mu-plugin adds through the filter — which is
// exactly the hook API under test.
const DASHBOARD = '/dashboard-fixture/';

async function signInAsMember(page) {
  await page.goto('/admin_login/');
  await page.fill('#user_login', 'member');
  await page.fill('#user_pass', 'wptest-member-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test('a signed-out visitor is sent to the login page', async ({ page }) => {
  await page.goto(DASHBOARD);
  await expect(page).toHaveURL(/admin_login/);
});

test('a member sees the frame, the nav and the overview', async ({ page }) => {
  await signInAsMember(page);
  await page.goto(DASHBOARD);
  await expect(page.locator('.bw-admin.bw-page.bw-store')).toHaveCount(1);
  await expect(page.locator('#foreign-content')).toHaveCount(0);
  await expect(page.locator('.bw-secnav__item', { hasText: 'Club' })).toBeVisible();
  await expect(page.locator('.bw-store__panel[data-view="dashboard"]:not([hidden])')).toHaveCount(1);
});

test('another plugin can put content on the overview', async ({ page }) => {
  await signInAsMember(page);
  await page.goto(DASHBOARD);
  await expect(page.locator('#test-welcome')).toHaveText('WELCOME');
});

test('another plugin can add a whole view', async ({ page }) => {
  await signInAsMember(page);
  await page.goto(`${DASHBOARD}?view=club`);
  await expect(page.locator('.bw-store__panel[data-view="club"]:not([hidden]) #test-panel')).toHaveText('TEST PANEL');
  await expect(page.locator('.bw-store__panel[data-view="dashboard"]')).toHaveAttribute('hidden', '');
});

test('the context filter reaches the frame', async ({ page }) => {
  await signInAsMember(page);
  await page.goto(DASHBOARD);
  await expect(page.locator('.bw-store__brandname')).toHaveText('Fixture Club');
});

test('junk in the address lands on the overview', async ({ page }) => {
  await signInAsMember(page);
  await page.goto(`${DASHBOARD}?view=nope`);
  await expect(page.locator('.bw-store__panel[data-view="dashboard"]:not([hidden])')).toHaveCount(1);
});

test("a plugin that claims the dashboard's address gets the visitor, panel and all", async ({ page }) => {
  await signInAsMember(page);
  await page.goto(`${DASHBOARD}?view=orders&bw_claim=1`);
  await expect(page).toHaveURL(/\/claimed-fixture\/\?view=orders/);
  await expect(page.locator('#claimed-content')).toHaveText('CLAIMED');
});

test('the checkout footer carries the links a plugin adds', async ({ page }) => {
  await page.goto('/checkout-fixture/');
  await expect(page.locator('.bw-checkout__links a', { hasText: 'Fixture terms' })).toHaveCount(1);
  await expect(page.locator('.bw-checkout__back')).toHaveText(/Back to Fixture Club/);
});

test('with the feature off, the page is left alone', async ({ page }) => {
  await login(page);
  await page.goto('/wp-admin/admin.php?page=blueworx-enhancements');
  // Use the helpers' setFeature/saveEnhancements; restore in finally.
  const { setFeature, saveEnhancements } = await import('./helpers.js');
  try {
    await setFeature(page, 'store_pages', false);
    await saveEnhancements(page);
    await page.goto('/checkout-fixture/');
    await expect(page.locator('.bw-checkout')).toHaveCount(0);
    await expect(page.locator('#shop-content')).toHaveText('SHOP CONTENT');
  } finally {
    await page.goto('/wp-admin/admin.php?page=blueworx-enhancements');
    await setFeature(page, 'store_pages', true);
    await saveEnhancements(page);
  }
});
```

Confirm the login path (`/admin_login/`) and the helper names against `tests/helpers.js` before running; the file exports `setFeature` and `saveEnhancements` already.

- [ ] **Step 7: Run both store specs**

Run: `npx playwright test tests/store-checkout.spec.js tests/store-dashboard.spec.js` — Expected: all pass. If the redirect test lands on `/claimed-fixture/?view=orders&bw_claim=1` that is fine; the regex allows it.

- [ ] **Step 8: Commit**

```bash
git add includes/store assets/js/store-dashboard.js tests/global-setup.js tests/php/store-dashboard-test.php tests/store-dashboard.spec.js package.json
git commit -m "Store pages: the customer dashboard, and the filters other plugins hook"
```

---

### Task 8: The API document

**Files:**
- Create: `docs/store-pages-api.md`

**Interfaces:** documents the five filters and three public functions from the inventory. Nothing else.

- [ ] **Step 1: Write it**

Contents, in this order, short and plain (this is read by whoever builds the next BlueWorx plugin):

1. What the store pages are, in three sentences, and that the feature is on by default and inert without SureCart.
2. **Add a view** — `blueworx_store_views`: the entry shape (table from the spec, section 3), the rule that a plugin adds its view only when its own dependency is present, and a full worked example: a plugin registering a `bookings` view with a shortcode.
3. **Change a panel** — `blueworx_store_panel( $html, $key, $context )`: prepend to `dashboard`, append to `profile`; example.
4. **Say who the site is** — `blueworx_store_context`: the keys table with defaults; example setting `logo_url`, `login_url`, `logout_url`, `home_label`.
5. **Checkout footer links** — `blueworx_store_checkout_links( $links, $context )`; example.
6. **Serve the dashboard yourself** — `blueworx_store_dashboard_url`; and then in your own route call `blueworx_store_dashboard_screen( $base, $home )` and `blueworx_store_enqueue_dashboard()` on `wp_enqueue_scripts`; `blueworx_store_page_url( $key )` for the checkout / thank-you / shop / dashboard addresses.
7. **Testing your integration** — point at `tests/global-setup.js`'s mu-plugin as the model.

- [ ] **Step 2: Commit**

```bash
git add docs/store-pages-api.md
git commit -m "Document how a plugin hooks the store pages"
```

---

### Task 9: Version, changelog, lint, zip

**Files:**
- Modify: `blueworx-labs-wordpress.php` (header `Version:` and `BLUEWORX_LABS_VERSION`), `package.json` (`version`), `readme.txt` (`Stable tag`), `CHANGELOG.md`
- Modify: `docs/superpowers/specs/2026-09-21-store-pages-design.md` — change `templates/store.php` to `includes/store/template.php` in the file table.

- [ ] **Step 1: Bump to 1.87.0 in all four places; run `npm run version:check`** — Expected: passes.

- [ ] **Step 2: Changelog**

At the top of `CHANGELOG.md`, under the intro:

```markdown
## [1.87.0] - 2026-09-21

### Added
- **Store pages.** SureCart's checkout, thank-you and account pages now wear
  the BlueWorx look, and the four pages SureCart needs are kept present and
  published, with a one-button repair if one goes missing. On by default;
  does nothing until SureCart is installed.
- **Other BlueWorx plugins can add to the account page.** A plugin can add
  its own panel, change what a panel shows, supply the site's logo and links,
  or serve the account page at its own address. See docs/store-pages-api.md.
```

- [ ] **Step 3: Run everything once**

Run, in order, and record the output:
- `npm run test:php`
- `npx playwright test tests/store-feature.spec.js tests/store-checkout.spec.js tests/store-dashboard.spec.js`
- `composer lint` (once — present any findings to the user at handoff; do not fix them unasked)
- `npm run lint` (same)
- `npm run check:merge` (see memory: it can false-positive on edited `package.json` lines; report, do not chase)

- [ ] **Step 4: Build and verify the zip**

```bash
npm run build
/c/Windows/System32/tar.exe -tf dist/blueworx-labs-wordpress.zip | grep -E "store|template" 
```

Expected: `blueworx-labs-wordpress/includes/store/*.php` (ten files incl. `template.php`), `assets/css/store.css`, `assets/css/store-surecart.css`, `assets/js/store-dashboard.js`, all with forward slashes. Then follow the WordPress plugin deployment rules in `~/.claude/CLAUDE.md`: one `blueworx-labs-wordpress-1.87.0.zip` in the parent folder, older ones removed, listed with `tar -tf` before handoff.

- [ ] **Step 5: Commit, push the branch, open the PR**

```bash
git add -A
git commit -m "Version 1.87.0: Store pages"
git push -u origin store-pages-spec
```

PR title: "Store pages: SureCart's checkout, thank-you and dashboard move here from ClubHouse". Body: two sentences on what it does, a line that ClubHouse switches over in its own PR against 1.87.0, and the attribution line `🤖 Generated with [Claude Code](https://claude.com/claude-code)`.

---

## Self-review against the spec

- §1 feature, section, guide → Task 1. §2 existence/repair → Tasks 2–3. §2 checkout/thank-you dressing and template → Task 6. §2 dashboard, redirect, login, public functions → Task 7 (`blueworx_store_page_url` in Task 2, `blueworx_store_enqueue_dashboard` in Task 6). §3 five filters → views (Task 4), context and links (Task 6), panel and dashboard URL (Task 7). §4 files → `template.php` moved under `includes/store/` (build allowlist); spec updated in Task 9. §6 Labs testing → Tasks 1–7; the design-system adherence check runs in CI at `warn`. §7 version/changelog → Task 9. §5 and §8 (ClubHouse and cutover) are the second plan, in the ClubHouse repo.
- Names: `blueworx_store_view_url` used by Task 7's redirect matches Task 5. `blueworx_store_shell_page` `site_name` key matches Tasks 5 and 7. `blueworx_store_dashboard_content` is defined in Task 6 (stub) and moved in Task 7.
