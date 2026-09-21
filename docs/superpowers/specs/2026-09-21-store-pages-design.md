# Store pages — SureCart's checkout, thank-you and dashboard, dressed by Labs and open to other plugins

**Date:** 2026-09-21
**Status:** Design, approved in chat, pending spec review
**Target version:** 1.87.0 here (minor — a new feature); 0.105.0 in ClubHouse (minor — it switches over)

## Problem

ClubHouse dresses three of SureCart's pages — Checkout, Thank you and the
customer Dashboard — in the BlueWorx admin design system, and keeps all four
SureCart pages (those three plus Shop) present and published. That is the
right experience for every BlueWorx site that sells through SureCart, not
just clubs, and it is the natural place for other BlueWorx plugins to put
their own account panels. Today it is welded to ClubHouse: the welcome pack,
the profile card, LatePoint bookings, the club's crest, its login and logout
and its "back to the club" links are all reached for directly.

One site runs it: crewevagrantssquash.co.uk. The switch must be invisible
there.

## Goals

1. Labs owns the three dressed pages and the four-page existence check, and
   turns them on for any site with SureCart.
2. Any plugin can add a dashboard view, change a panel, supply branding and
   links, or claim the dashboard's address — through WordPress filters, with
   no class or load-order coupling.
3. ClubHouse deletes its copy and becomes the first consumer of those
   filters. A member of Crewe Vagrants sees exactly what they see today, at
   the same addresses.
4. Nothing SureCart does changes. Its blocks, its controllers, its permission
   checks and its page ids are used as they are.

## Non-goals

- Dressing the Shop page. It is checked and repaired, as today, and rendered
  by the theme.
- A stand-down guard for the window between the two plugins' releases. The
  switch on the one live site is done by hand: both plugins disabled, then
  re-enabled in order (see section 8).
- Re-rendering SureCart's records in our own markup. Panels are SureCart's
  blocks in our frame, exactly as now.
- New views, new design, or a settings screen for the pages. What exists
  moves; nothing is added beyond the hook API.
- Multisite.

---

## 1. The feature

A new entry in the feature registry, `store_pages`, labelled **Store pages**,
in a new **Store** section of the Enhancements screen. Default `'1'`: this is
the standard experience for a BlueWorx SureCart site. It does nothing on a
site without SureCart active, so the default costs nothing anywhere else.

Its description: "Gives SureCart's checkout, thank-you and account pages the
BlueWorx look, and keeps the four pages SureCart needs present and published."

It gets a guide entry (the Guides page's format test fails a feature without
one, on purpose), under SureCart: "Find your store's pages" — where they are,
what the warning notice means and what the button does.

## 2. The pages

### Which pages

Found the way SureCart finds them: the option `surecart_<key>_page_id` for
`checkout`, `order-confirmation`, `dashboard` and `shop`. An id of 0 never
matches anything.

### Existence and repair

Moves whole from ClubHouse's `Shop_Pages` and `Shop_Pages_Controller`:

- A status for each page (no shop / ok / missing / unpublished).
- One admin notice on every admin screen when anything is wrong, listing the
  consequence of each problem, with a "Put the missing pages back" button
  that republishes trashed pages and asks SureCart's own seeder to create
  the rest. The notice is prefixed "BlueWorx:" rather than "Clubhouse:".
- The Thank you page is created by Labs when SureCart's activation seeder
  has not made one and no id was ever recorded — same rule, same slug, title
  and block as today, written through SureCart's page service so SureCart
  can find it. Runs on `admin_init`.

Capability `manage_options`, as today.

### Dressing checkout and thank-you

Unchanged in behaviour, moved whole from `Commerce_Pages`:

- A `the_content` filter at priority 30 (after SureCart has expanded its
  blocks), gated on the main query, the loop and the page id, with the
  re-entrancy guard.
- Checkout gets the checkout frame: brand, heading, the page's own content
  untouched, footer with a back link and the links from the filter in
  section 3.
- Thank you gets the bare frame: title, lede, one card with the page's
  content, a back link.
- Both are served from Labs' own template `templates/store.php` so the theme
  draws no header or footer around them, and the theme's `core/post-title`
  block is blanked on them so the page has one h1.
- No sign-in check: a guest may pay.

### The dashboard

SureCart's own dashboard page, dressed the same way — a `the_content` filter
and the same template — unless another plugin claims the dashboard's address
(section 3, `blueworx_store_dashboard_url`). When one does, requests for
SureCart's page are redirected there with the `view`, `model`, `action` and
`id` arguments preserved, exactly as ClubHouse redirects today.

A signed-out visitor on the dashboard is sent to the login URL from the
context filter. Priority 5 on `template_redirect`.

The screen is built by a public function so a plugin serving the dashboard
at its own address can draw it:

```php
blueworx_store_dashboard_screen( string $base, string $home ): string
blueworx_store_enqueue_dashboard(): void   // stylesheet, script, SureCart's assets
blueworx_store_page_url( string $key ): string // a page's address, or '' when it is not reachable
```

`$base` is the address every view link is built on; `$home` the way out.

Everything else moves as it is: the declarative view list, the router that
resolves `?view=`, the "every panel drawn, all but one hidden" rule, the
action journeys through SureCart's `dashboard-page` wrapper block with the
model-to-view map, the plugin slot that renders a block or shortcode or
answers nothing, the "Nothing here yet" empty state, and the deferred script
that switches panels without a reload.

## 3. The hook API

Five filters, prefixed `blueworx_store_`. Every one receives Labs' own
default and returns the same shape. Documented with a worked example in
`docs/store-pages-api.md`.

### `blueworx_store_views` — `( array $views ): array`

The dashboard's views, in nav order. Each:

| Key         | Type     | Meaning |
| ----------- | -------- | ------- |
| `key`       | string   | Address and nav identity. Unique. |
| `label`     | string   | Nav text. |
| `title`     | string   | Page heading when the view is open. |
| `lede`      | string   | One line under the heading. |
| `icon`      | string   | A Lucide icon name the design system ships. |
| `where`     | string   | `both`, `side` or `bar` — desktop sidebar, phone bottom bar, or both. |
| `blocks`    | string[] | Block names, each rendered in its own card. |
| `shortcode` | string   | A shortcode tag that takes the whole view instead. |

Labs registers `dashboard`, `orders`, `invoices`, `billing`, `plans`,
`profile` and `account` with SureCart's blocks, as the list stands today.
There is no `requires` key any more: a plugin only adds a view when its own
dependency is present, and Labs only registers its own when SureCart is.
`dashboard` cannot be removed; it is where anything unrecognised lands.

### `blueworx_store_panel` — `( string $html, string $key, array $context ): string`

The rendered body of one view, before it goes in the frame. Empty string
means "draw the empty state". ClubHouse prepends the welcome pack on
`dashboard` and appends its profile card on `profile`.

### `blueworx_store_context` — `( array $context ): array`

Who and where. Labs' defaults, then the filter:

| Key            | Default |
| -------------- | ------- |
| `site_name`    | `get_bloginfo( 'name' )` |
| `logo_url`     | The site icon, else the theme's custom logo, else '' (the shell falls back to initials). |
| `home_url`     | `home_url( '/' )` |
| `home_label`   | "Back to <site name>", or "Back to the site". |
| `login_url`    | The login URL as Labs' own login feature resolves it. |
| `logout_url`   | `wp_logout_url( home_url( '/' ) )` |
| `member_name`  | Display name, else login. |
| `member_email` | The signed-in user's email. |

ClubHouse supplies the club's favicon-or-logo, its own login and logout
addresses and "Back to Crewe Vagrants".

### `blueworx_store_checkout_links` — `( array $links, array $context ): array`

The checkout footer's links, each `['label' => …, 'href' => …]`. Default:
the site's privacy policy page if one is set, else nothing. ClubHouse adds
terms, privacy, rules and contact, filtered by its own visibility settings.

### `blueworx_store_dashboard_url` — `( string $url ): string`

Empty by default. A non-empty answer means "the dashboard lives here": Labs
stops dressing SureCart's page and redirects it there instead. ClubHouse
answers `/member-dashboard/`.

## 4. Files

Labs, new:

| File | From ClubHouse |
| ---- | -------------- |
| `includes/store/store.php` | feature bootstrap; registers everything below when the feature is on and SureCart is active |
| `includes/store/pages.php` | `class-shop-pages.php` |
| `includes/store/pages-notice.php` | `class-shop-pages-controller.php` |
| `includes/store/commerce.php` | `class-commerce-pages.php` |
| `includes/store/dashboard.php` | `class-member-dashboard.php` (minus welcome pack, profile, branding, auth) |
| `includes/store/views.php` | `class-dashboard-views.php` + the filter |
| `includes/store/actions.php` | `class-dashboard-actions.php` |
| `includes/store/slot.php` | `class-plugin-slot.php` |
| `includes/store/shell.php` | `class-dashboard-shell.php` |
| `includes/store/assets.php` | `class-dashboard-assets.php` |
| `includes/store/template.php` | `templates/commerce.php` |
| `assets/css/store.css` | the member-area rules from `assets/bw/bw.css` (the rules that mention `clubhouse-member`), renamed |
| `assets/css/store-surecart.css` | `assets/bw/surecart.css` |
| `assets/js/store-dashboard.js` | `assets/js/member-area.js` |
| `docs/store-pages-api.md` | new |

Labs' code is procedural, prefixed `blueworx_store_`, matching the rest of
`includes/`. The pure rules keep their pure shape (arguments in, answer out)
so they stay testable; the WordPress calls live at the edges as they do now.

The design system stylesheet itself is not vendored again: Labs already
ships `assets/blueworx-admin-design.css` and its newest-wins registrar. The
store pages enqueue that handle, then `store.css` on top of it.

CSS class names: `clubhouse-member__*` becomes `blueworx-store__*`,
`clubhouse-checkout__*` becomes `blueworx-checkout__*`. The `bw-admin`, `bw-page`,
`bw-card`, `bw-secnav` and `bw-panels` classes are the design system's and
stay.

## 5. ClubHouse

Deletes: the nine source files in the right-hand column above, both
`assets/bw/` files, `member-area.js`, `templates/commerce.php`, and their
unit tests.

Keeps: `Welcome_Pack`, `Profile_Form`, `Checkout_Form`, `SureCart_Products`,
`Link_Catalogue` (which now reads page addresses from Labs), and the
`/member-dashboard/` route.

Adds one file, `includes/store/class-labs-store.php`, which:

- hooks the five filters (Bookings view when LatePoint is present; welcome
  pack and profile card; branding, login, logout and back label; the four
  footer links; the dashboard address);
- has the member-area route render `blueworx_store_dashboard_screen()` and
  enqueue through `blueworx_store_enqueue_dashboard()`;
- declares the dependency: `Requires Plugins: blueworx-labs-wordpress` in the
  plugin header, plus a check at boot that Labs is active and at least
  1.87.0, with an admin notice naming what is missing when it is not. With
  Labs missing the route serves a plain "the member area is not available"
  page rather than a fatal.

Its browser specs that assert on `clubhouse-member__*` or
`clubhouse-checkout__*` selectors are updated to the new names. What they
assert does not change.

## 6. Testing

Labs (Playwright, local harness, no SureCart installed — fixture pages with
SureCart's options pointed at them, as ClubHouse's global setup does today):

- Checkout wears the frame, passes its content through, has one h1, no nav,
  and the field theme in the head.
- Thank you wears the bare frame.
- Dashboard: signed-out visitor is redirected to login; signed-in sees the
  nav and the overview; `?view=` selects a panel; junk falls back to the
  overview; a `model`/`action` address is routed to the right view.
- Existence: with an option pointed at a trashed page, the notice appears
  and the button republishes it.
- Hook API: a test mu-plugin registers a view, changes a panel, sets the
  context and adds a footer link through the filters, and the page shows
  each; a second fixture claims the dashboard URL and SureCart's page
  redirects there with the arguments intact.
- Feature off: none of the above happens.
- Design-system adherence spec covers the new markup.

ClubHouse:

- Existing checkout, thank-you and member-area specs pass with updated
  selectors.
- A markup snapshot of the three pages taken with today's code, compared
  with the same pages under Labs + new ClubHouse, ignoring the class rename.
  This is the seamlessness proof, run before either release.
- A new spec: with Labs deactivated, the admin notice shows and the
  member-area route does not fatal.

## 7. Versions and changelogs

Labs 1.87.0: "Store pages: SureCart's checkout, thank-you and account pages
get the BlueWorx look, and other BlueWorx plugins can add their own account
panels." ClubHouse 0.105.0: "The member area, checkout and thank-you page
are now served by BlueWorx Labs. Nothing changes for members."

## 8. Cutover on crewevagrantssquash.co.uk

Both plugins install their own updates, so the two releases are tagged
together and the switch is done straight after, by hand:

1. Tag Labs 1.87.0 and ClubHouse 0.105.0.
2. On the site: deactivate ClubHouse, deactivate Labs. Update both.
   Activate Labs, then ClubHouse. Confirm Store pages is on.
3. Check: `/member-dashboard/` signed out redirects to the club login;
   signed in shows the same views; `?view=orders` opens Orders; a Join
   button reaches the framed checkout; an "edit card" link opens SureCart's
   form under Account; the thank-you page renders.
4. Rollback: reinstall ClubHouse 0.104.x and deactivate Labs' Store pages
   feature. No data changes in either direction.
