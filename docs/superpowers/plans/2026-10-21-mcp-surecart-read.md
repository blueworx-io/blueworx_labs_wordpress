# SureCart in the Claude connector (read-only) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Claude can read anything about a SureCart store through the connector, and can change nothing in it.

**Architecture:** A new `includes/mcp/surecart.php` beside `forge.php`, with four read-only tools that call SureCart's own REST routes in-process (`rest_do_request`, GET only, routes fixed in code). `server.php` merges them in, mentions the shop in its opening instructions when SureCart is active, and stops its existing write tools touching SureCart's WordPress-stored records.

**Tech Stack:** PHP (WordPress plugin), plain-PHP test scripts (`tests/php/*-test.php` with `tests/php/stubs.php`), Playwright.

**Spec:** `docs/superpowers/specs/2026-10-21-mcp-surecart-read-design.md`

## Global Constraints

- No write to SureCart, ever. Every SureCart request these tools make is `GET`, and the caller never picks the route, only a type from a fixed list.
- No new dependencies.
- Who can connect is unchanged (administrators only).
- Read-only accounts with data access off are refused every customer-bearing record. Use `blueworx_readonly_current_user()` and `blueworx_readonly_data_allowed()`; do not change `blueworx_readonly_denied_routes()` or the admin-screen lists.
- Secrets never leave the site. SureCart's `/settings` returns `api_token` (seen on the demo store), so every response is scrubbed.
- Unknown record types count as personal (fail closed).
- Own classes never use the `bw-` prefix (only relevant if any admin copy changes).
- Minor version bump to 1.95.0 in `blueworx-labs-wordpress.php` (header + `BLUEWORX_LABS_VERSION`), `package.json`, `readme.txt` stable tag, plus a `CHANGELOG.md` entry in plain words.
- Test store: https://demo.305media.co.uk via a Support Access key from Luke, never committed. Orders, customers and subscriptions need Luke to open data access for the session.

## Review Focus

1. **A secret nested deep in a record** (a processor's keys, a webhook signing secret): the scrubber must work at any depth, not just top level. Test in Task 1.
2. **Zero-decimal currencies** (JPY, KRW): an amount of 2400 is ¥2400, not ¥24.00. Test in Task 1.
3. **An ID shaped like a path** (`../settings`, `abc/refund`): refused before SureCart sees it, so a get can never reach a different route. Test in Task 2.
4. **SureCart installed but not connected to a store** (no token, SureCart's REST routes answer with an error): the tool says so in one plain sentence, not a raw error dump. Test in Task 2.
5. **A huge store** (thousands of orders): lists are paged with a hard `per_page` cap of 100 and return the total, so Claude knows there's more. Test in Task 2.

---

### Task 1: Store record helpers (types, scrubbing, shaping, links, access)

**Files:**
- Create: `includes/mcp/surecart.php`
- Create: `tests/php/mcp-surecart-test.php`
- Modify: `package.json` (append `&& php tests/php/mcp-surecart-test.php` to `test:php`)

**Interfaces:**
- Produces:
  - `blueworx_mcp_surecart_active(): bool`: `blueworx_readonly_surecart_active()` if that function exists, else `class_exists( 'SureCart' ) || defined( 'SURECART_PLUGIN_FILE' )`.
  - `blueworx_mcp_surecart_unavailable(): WP_Error|null`: code `surecart_inactive`, message `SureCart is not active on this site, so there is no shop to look at.`
  - `blueworx_mcp_surecart_types(): array`: keyed by the public type name. Each entry is `array( 'route' => string, 'personal' => bool, 'admin_page' => string|'' , 'label' => string )`.
  - `blueworx_mcp_surecart_personal_blocked(): bool`: true when `blueworx_readonly_current_user()` returns a user and `blueworx_readonly_data_allowed( $user )` is false. Guard with `function_exists`.
  - `blueworx_mcp_surecart_scrub( mixed $data ): mixed`: recursive, drops keys matching `/(token|secret|password|private|signing|api_key)/i`.
  - `blueworx_mcp_surecart_shape( array $record, string $currency = '' ): array`: scrubbed, `*_at` unix timestamps become `wp_date( 'c' )` strings, `amount`/`*_amount` integers get a sibling `*_display` money string using the record's `currency` (or the passed one), noise keys `post`, `image_srcset`, `cache_keys`, `metadata` dropped.
  - `blueworx_mcp_surecart_admin_link( string $type, string $id = '' ): string`: `admin_url( 'admin.php?page=<admin_page>&action=edit&id=<id>' )`, the list screen when `$id` is empty, `''` when the type has no screen.

The type list (public name ⇒ route, personal):

| Kind | Types |
|---|---|
| Not personal | products, prices, variants, variant_options, product_collections, product_groups, product_medias, bundle_items, coupons, promotions, bumps, upsells, upsell_funnels, auto_fees, tax_zones, tax_registrations, tax_overrides, shipping_profiles, shipping_zones, shipping_methods, shipping_rates, warehouses, processors, manual_payment_methods, display_currencies, cancellation_reasons, return_reasons |
| Personal | customers, orders, checkouts, abandoned_checkouts, invoices, charges, refunds, disputes, subscriptions, purchases, payment_methods, licenses, activations, fulfillments, shipments, return_requests, reviews, affiliations, affiliation_requests, referrals, payouts, clicks, cancellation_acts, balance_transactions |

Each route is `/surecart/v1/<name>`. Admin pages: confirm each slug on the demo store's admin (log in through the support link), starting from `sc-products`, `sc-orders`, `sc-customers`, `sc-subscriptions`, `sc-coupons`, `sc-invoices`, `sc-abandoned-checkouts`, `sc-product-collections`, `sc-bumps`, `sc-upsell-funnels`, `sc-licenses`, `sc-affiliates`, `sc-settings`. Leave `admin_page` empty where no screen exists, and for prices and variants link the product screen.

- [ ] **Step 1: Write the failing tests** in `tests/php/mcp-surecart-test.php`, following `tests/php/mcp-forge-test.php` (require `stubs.php`, own `WP_REST_Request`/`Fake_Response`/`rest_do_request` stand-ins, `check()`/`finish()`). Stub `admin_url( $p )` as `'https://shop.test/wp-admin/' . $p`, `wp_date( 'c', $t )` as `gmdate( 'c', $t )`, and `blueworx_readonly_current_user()` / `blueworx_readonly_data_allowed()` driven by `$GLOBALS['readonly_user']` and `$GLOBALS['data_open']`.

```php
check( 'a token is never returned', blueworx_mcp_surecart_scrub( array( 'object' => 'settings', 'api_token' => 'st_x', 'uninstall' => false ) ), array( 'object' => 'settings', 'uninstall' => false ) );
check( 'nor one buried in a record', blueworx_mcp_surecart_scrub( array( 'processors' => array( array( 'id' => 'p1', 'live_secret_key' => 'sk', 'webhook_signing_secret' => 'wh' ) ) ) ), array( 'processors' => array( array( 'id' => 'p1' ) ) ) );
$shaped = blueworx_mcp_surecart_shape( array( 'id' => 'o1', 'currency' => 'gbp', 'amount_due' => 2400, 'created_at' => 1747498098, 'post' => array( 'ID' => 1 ) ) );
check( 'money reads as money', $shaped['amount_due_display'], '£24.00' );
check( 'the raw amount stays', $shaped['amount_due'], 2400 );
check( 'dates read as dates', $shaped['created_at'], '2025-05-17T16:08:18+00:00' );
check( 'the WordPress copy is dropped', isset( $shaped['post'] ), false );
check( 'yen has no pence', blueworx_mcp_surecart_shape( array( 'currency' => 'jpy', 'amount' => 2400 ) )['amount_display'], '¥2,400' );
check( 'an order links to its screen', blueworx_mcp_surecart_admin_link( 'orders', 'o1' ), 'https://shop.test/wp-admin/admin.php?page=sc-orders&action=edit&id=o1' );
check( 'unknown types count as personal', count( array_filter( blueworx_mcp_surecart_types(), function ( $t ) { return ! is_bool( $t['personal'] ); } ) ), 0 );
$GLOBALS['readonly_user'] = (object) array( 'ID' => 9 ); $GLOBALS['data_open'] = false;
check( 'a read-only account with data closed is blocked', blueworx_mcp_surecart_personal_blocked(), true );
$GLOBALS['data_open'] = true;
check( 'and let through once data is opened', blueworx_mcp_surecart_personal_blocked(), false );
$GLOBALS['readonly_user'] = null;
check( 'an administrator is never blocked', blueworx_mcp_surecart_personal_blocked(), false );
```

- [ ] **Step 2: Run to see it fail.** `php tests/php/mcp-surecart-test.php`: fatal, file or function missing.
- [ ] **Step 3: Implement the helpers** in `includes/mcp/surecart.php` with the file header and docblock style of `forge.php`. Money: a small map of zero-decimal currencies (`bif clp djf gnf jpy kmf krw mga pyg rwf ugx vnd vuv xaf xof xpf`) and a symbol map for the common ones (`gbp £`, `usd $`, `eur €`, `jpy ¥`), falling back to the upper-case code plus a space (`CHF 24.00`). Use `number_format`.
- [ ] **Step 4: Run to see it pass.** `php tests/php/mcp-surecart-test.php` ends `All checks passed.`
- [ ] **Step 5: Commit.** `git add includes/mcp/surecart.php tests/php/mcp-surecart-test.php package.json && git commit -m "Add the SureCart record helpers for the connector"`

### Task 2: The four read tools

**Files:**
- Modify: `includes/mcp/surecart.php`
- Modify: `tests/php/mcp-surecart-test.php`

**Interfaces:**
- Consumes: everything Task 1 produces.
- Produces:
  - `blueworx_mcp_surecart_request( string $route, array $query = array() ): array|WP_Error`: always `new WP_REST_Request( 'GET', '/surecart/v1' . $route )`, no method parameter. Returns `array( 'data' => mixed, 'total' => int|null )` with `total` from the `X-WP-Total` header when present. A SureCart error becomes a `WP_Error` with SureCart's own message, stripped of tags.
  - `blueworx_mcp_surecart_tool_definitions(): array`: four tools, each with `'annotations' => array( 'readOnlyHint' => true, 'openWorldHint' => false )`, always listed (unlike Forge's, which hide while Forge is off; the spec asks that a call says the shop isn't there).
  - `blueworx_mcp_surecart_tool_handlers(): array`: name ⇒ callable, keys matching the definitions.
  - `blueworx_mcp_surecart_site_info(): array`: `array( 'active' => bool, 'record_types' => string[] )`.

The tools:

- `get_store_settings`: no arguments. Returns `brand` (colours, dark colour, theme, logo URL), `account` (name, currency, live or test mode, anything else useful from `/account` except `cache_keys`), `settings` (from `/settings`, scrubbed), and `links` to the settings, brand and checkout screens. Description tells Claude this is where the store's look lives.
- `list_store_records`: `type` (required, enum of the type names), `search`, `status`, `customer_id`, `product_id`, `from`, `to` (Y-m-d), `page` (default 1), `per_page` (default 20, max 100). Map filters onto SureCart's query names (`query`, `status[]`, `customer_ids[]`, `product_ids[]`, `created_at` range); drop any a type doesn't take. Returns `type`, `page`, `per_page`, `total`, `records` (shaped, each with `admin_link`), and `admin_link` for the list screen.
- `get_store_record`: `type`, `id`. ID must match `/^[A-Za-z0-9_-]+$/`, else `invalid_id`. Returns the shaped record with `admin_link`.
- `get_store_sales`: `report` (enum, start with `orders`; add the other `/stats/<id>` reports the demo store answers, such as `abandoned_checkouts` and `recovered_checkouts`), `from`, `to` (required), `interval` (`day|week|month|year`, default `month`). Returns per-interval rows with `amount_display`, `average_amount_display`, `count`, and totals. No personal data, so not blocked for read-only accounts.

Every tool first returns `blueworx_mcp_surecart_unavailable()` when that isn't null. List and get return `WP_Error( 'personal_data_withheld', 'Customer details are switched off for this account, so <label> cannot be shown.' )` when the type is personal and `blueworx_mcp_surecart_personal_blocked()`.

- [ ] **Step 1: Write the failing tests.** Make the stand-in `rest_do_request` record every call as `array( method, route, query )` and answer `/surecart/v1/products`, `/products/p1`, `/orders`, `/settings`, `/brand`, `/account`, `/stats/orders` with shapes copied from the demo store (product above; `settings` including `api_token`). Give `Fake_Response` a `get_headers()` returning `X-WP-Total`.

```php
check( 'every tool says it is read-only', array_unique( array_column( array_column( blueworx_mcp_surecart_tool_definitions(), 'annotations' ), 'readOnlyHint' ) ), array( true ) );
check( 'and every listed tool has a handler', array_keys( blueworx_mcp_surecart_tool_handlers() ), array_column( blueworx_mcp_surecart_tool_definitions(), 'name' ) );
// SureCart off.
check( 'a call without SureCart is refused plainly', error_of( blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products' ) ) ), 'surecart_inactive' );
check( 'and SureCart was never asked', $GLOBALS['sc']['calls'], array() );
// SureCart on (define SURECART_PLUGIN_FILE).
$list = blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products', 'per_page' => 500 ) );
check( 'per_page is capped at 100', last_call()[2]['per_page'], 100 );
check( 'the total comes back', $list['total'], 11 );
check( 'each record links to its screen', $list['records'][0]['admin_link'], 'https://shop.test/wp-admin/admin.php?page=sc-products&action=edit&id=p1' );
check( 'a path in an ID is refused', error_of( blueworx_mcp_tool_surecart_get_record( array( 'type' => 'products', 'id' => '../settings' ) ) ), 'invalid_id' );
check( 'an unknown type is refused', error_of( blueworx_mcp_tool_surecart_list_records( array( 'type' => 'users' ) ) ), 'unknown_type' );
check( 'settings never carry the token', isset( blueworx_mcp_tool_surecart_get_settings( array() )['settings']['api_token'] ), false );
check( 'a store with no connection says so in SureCart\'s words', message_of( /* stub /products to 400 with SureCart's message */ ), '<SureCart message from demo or its source>' );
$GLOBALS['readonly_user'] = (object) array( 'ID' => 9 ); $GLOBALS['data_open'] = false;
check( 'orders are withheld from a read-only account', error_of( blueworx_mcp_tool_surecart_list_records( array( 'type' => 'subscriptions' ) ) ), 'personal_data_withheld' );
check( 'products are not', error_of( blueworx_mcp_tool_surecart_list_records( array( 'type' => 'products' ) ) ), 'no error' );
check( 'nor sales totals', error_of( blueworx_mcp_tool_surecart_get_sales( array( 'report' => 'orders', 'from' => '2026-01-01', 'to' => '2026-10-21' ) ) ), 'no error' );
check( 'nothing but GET was ever sent', array_unique( array_column( $GLOBALS['sc']['calls'], 0 ) ), array( 'GET' ) );
```

For the "not connected" message, find what SureCart returns when it has no API token (its source on the demo site, or a disconnected local install) and use that text.

- [ ] **Step 2: Run to see it fail.** `php tests/php/mcp-surecart-test.php`
- [ ] **Step 3: Implement** the request helper, the four `blueworx_mcp_tool_surecart_*` handlers (`get_settings`, `list_records`, `get_record`, `get_sales`), the definitions, handlers and site info.
- [ ] **Step 4: Run to see it pass.**
- [ ] **Step 5: Check against the demo store.** With a support key from Luke, compare a product, the brand settings and a month of sales stats with what the tools would build from the same responses (saved with curl into the scratchpad, never the repo). Ask Luke to open data access, then do the same for one order and one subscription. Fix any shape the stubs got wrong, updating the stubs to match.
- [ ] **Step 6: Commit.** `git commit -am "Let Claude read the SureCart store"`

### Task 3: Wire into the server and close the back door

**Files:**
- Modify: `blueworx-labs-wordpress.php:132` (require `includes/mcp/surecart.php` after `forge.php`)
- Modify: `includes/mcp/server.php` (instructions ~line 172, definitions ~line 507, handlers ~line 515, site info ~line 755, create/update/trash content, create_term)
- Modify: `tests/php/mcp-surecart-test.php`
- Modify: `tests/mcp-connection.spec.js`

**Interfaces:**
- Consumes: `blueworx_mcp_surecart_tool_definitions()`, `blueworx_mcp_surecart_tool_handlers()`, `blueworx_mcp_surecart_site_info()`, `blueworx_mcp_surecart_active()`, `blueworx_mcp_surecart_admin_link()`.
- Produces: `blueworx_mcp_surecart_locked( string $name ): bool` in `surecart.php`, true for any post type or taxonomy starting `sc_` (SureCart's prefix: `sc_product`, `sc_form`, `sc_cart`, `sc_collection` on the demo store).

- [ ] **Step 1: Write the failing tests.** In the PHP test, load `server.php` with the stubs it needs, or, if it pulls in too much, test `blueworx_mcp_surecart_locked()` there and cover the server wiring in Playwright.

```php
check( 'SureCart products are locked', blueworx_mcp_surecart_locked( 'sc_product' ), true );
check( 'collections too', blueworx_mcp_surecart_locked( 'sc_collection' ), true );
check( 'pages are not', blueworx_mcp_surecart_locked( 'page' ), false );
```

In `tests/mcp-connection.spec.js` (the harness has no SureCart), following the Forge tests there: `tools/list` includes `get_store_settings`, `list_store_records`, `get_store_record`, `get_store_sales`; calling `list_store_records` returns `isError` with `SureCart is not active on this site`; `get_site_info` has `surecart.active === false`.

- [ ] **Step 2: Run to see them fail.** `php tests/php/mcp-surecart-test.php`; `npx playwright test tests/mcp-connection.spec.js` against the local harness (see memory: the `.env` target is stale, point at `.wp-test`).
- [ ] **Step 3: Implement.** Merge the definitions and handlers after Forge's. Add to the instructions, only when active: `The site also runs SureCart, its shop: get_store_settings, list_store_records, get_store_record and get_store_sales read products, prices, orders, subscriptions, customers, settings, the store's look and sales. These tools cannot change the shop; when asked for a change, say which SureCart screen to open (each record has an admin_link) and what to change there.` In create, update and trash content, after resolving the type, return `WP_Error( 'surecart_read_only', '<label> belongs to SureCart, which Claude can only read. Change it in SureCart: <list-screen link>' )` when locked; the same in `create_term` for locked taxonomies. In site info add `'surecart' => blueworx_mcp_surecart_site_info()` and mark locked content types and taxonomies with `'read_only' => true`. Reading through `list_content`/`get_content` stays allowed.
- [ ] **Step 4: Run to see them pass,** plus `npm run test:php` in full.
- [ ] **Step 5: Run the read-only suites.** `npx playwright test tests/support-access.spec.js tests/external-readonly.spec.js tests/external-access.spec.js` against the local harness. All pass.
- [ ] **Step 6: Commit.** `git commit -am "Stop the connector changing SureCart's records"`

### Task 4: Version, changelog, setup guide

**Files:**
- Modify: `blueworx-labs-wordpress.php` (header `Version:` and `BLUEWORX_LABS_VERSION` to `1.95.0`), `package.json`, `readme.txt` stable tag, `CHANGELOG.md`, `docs/mcp-setup-guide.md`

- [ ] **Step 1: Bump to 1.95.0** in all four places.
- [ ] **Step 2: Changelog entry** under `## [1.95.0] - <date>` / `### Added`, in the style of 1.94.0, for example: **Claude can look round the shop.** On sites running SureCart it can read products, prices, orders, subscriptions, customers, settings, the store's look and sales totals, and tell you which screen to open to change something. It can't change anything in the shop, and it can no longer edit SureCart's product pages or checkout forms through the page tools either.
- [ ] **Step 3: Add a short SureCart paragraph** to `docs/mcp-setup-guide.md` saying the same.
- [ ] **Step 4: Verify.** `npm run version:check` passes; `npm run lint` once, findings reported to Luke rather than fixed.
- [ ] **Step 5: Commit.** `git commit -am "Release 1.95.0: SureCart in the Claude connector"`
