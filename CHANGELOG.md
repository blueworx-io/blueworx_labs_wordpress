# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/); this project uses semantic
versioning.

## [1.21.0] - 2026-07-21

### Added
- **Shared public helpers: icons and decorative blobs, plus the CTA band and
  footer template part.** `blueworx_icon()` and `blueworx_blob()`
  (`includes/public/helpers-public.php`) port the 21 inline SVG icons and the
  decorative background blobs from the front-end design's `lib/icons.ts` and
  `CtaBand.tsx`. The icon renderer wraps every `<svg>` in a `<span
  data-ic="...">` sized to fill it at 100% — the span is what `public.css`
  sizes at ten separate selectors, so a bare `<svg>` would collapse icon
  sizing sitewide. `templates/parts/footer.php` ports `CtaBand.tsx` and
  `Footer.tsx`: the CTA band renders as a sibling of `<main>`, before the
  footer, on every page; the footer reproduces the source's `.fb`/`.fcol`
  /`.fnews`/`.fbot` structure, keeping the social and Blog/Resources/Careers
  links as non-links (no `href` in the source) and the newsletter form inert
  (markup only, no handler — a form plugin shortcode replaces it later).
  `home.php`'s existing call to `blueworx_public_part( 'parts/footer.php' )`
  now resolves.

## [1.20.1] - 2026-07-21

### Fixed
- **Fragile test cleanup in `tests/public-site.spec.js`.** Two tests
  (`"/" is not exempt from Site Protection when show_on_front is not a
  plugin-owned page` and the slug-collision test) restored multiple
  independent pieces of mutated global state — Site Protection toggles,
  show_on_front/page_on_front, and page slugs — as sequential steps inside a
  single `finally` block. A throw partway through (e.g. a `.notice-success`
  assertion timing out) skipped every restore step after it, risking the site
  root being left serving the posts index or a page stuck on a temporary
  slug for the rest of the suite and any real visitor. A new `restoreAll()`
  helper runs each restore step in isolation via its own try/catch,
  collecting errors and re-throwing them together at the end, so every
  mutated piece of state is restored regardless of which step fails, while a
  genuine cleanup failure still fails the test loudly. Applied to both named
  tests plus the sibling `a renamed plugin-owned page stays exempt from Site
  Protection` test, which had the same underlying fragility despite already
  ordering its restores correctly.

## [1.20.0] - 2026-07-21

### Added
- **Theme-independent document shell and template routing.** The public site
  now renders its own complete HTML document — `blueworx_public_document_open()`
  / `blueworx_public_document_close()` (`includes/public/render.php`) call
  `wp_head()` / `wp_footer()` but deliberately never `get_header()` /
  `get_footer()`, so the site looks identical no matter which theme is active
  or where it ends up hosted. `template_include` is now hooked
  (`blueworx_public_template()` in `includes/public/pages.php`) to hand
  rendering of owned pages to the plugin's own templates, and a
  `templates/pages/home.php` placeholder (via the new `blueworx_public_part()`
  helper) is the first template to actually render. Activation now also
  points `show_on_front` / `page_on_front` at the Home page, so `/` is
  WordPress's actual front page rather than the default posts index.

## [1.19.3] - 2026-07-21

### Fixed
- **Slug-collision hijack in `blueworx_public_current_template()`.** After a
  rename, the map's `home` key keeps pointing at the renamed page's ID. If a
  different, unrelated page later takes the now-free `home` slug, its ID is
  not in the map, so `array_search()` fails and the code fell back to
  matching the static registry purely by slug — rendering the plugin's Home
  template over a page it does not own. The fallback now only resolves by
  slug when the map has no entry for that slug at all; if the slug is
  already claimed by a different mapped ID, the page is correctly reported as
  not owned. The fresh-install path (no map entry yet) is unaffected.

## [1.19.2] - 2026-07-21

### Fixed
- **The two ownership checks could disagree.** `blueworx_public_is_owned_request_path()`
  (init-time, drives the Site Protection exemption) and
  `blueworx_public_is_owned_page()` (query-time, drives rendering and asset
  enqueue) were meant to describe the same notion of "owned", but the
  path-based check had two gaps:
  - It unconditionally treated `/` as owned. `/` only becomes an owned page
    once WordPress's front page is pointed at one of the plugin's pages
    (Task 4) — until then it's WordPress's default posts index, and
    exempting it from Site Protection weakened the gate at the site root.
    Now `/` counts as owned only when `show_on_front` is `'page'` and
    `page_on_front` is one of the IDs in `blueworx_public_page_ids`.
  - It compared the request path against the plugin's *static* slugs, so
    renaming an owned page's slug kept the query-time check correct but made
    the path check — and so the Site Protection exemption — stop recognising
    it, wp_die()-ing a real visitor to a page the plugin still owns. Now the
    path check resolves each mapped page's slug via `get_post_field()`
    (falling back to the static slug only for a page not yet in the map),
    matching how `blueworx_public_current_template()` already resolves
    renames at query time.

  Site Protection's default behaviour is unchanged — only which requests are
  exempt from it. Added two tests to `tests/public-site.spec.js` covering
  both gaps.

## [1.19.1] - 2026-07-21

### Fixed
- **Site Protection exemption ran before the query.** The exemption that keeps
  plugin-owned public pages reachable when Site Protection is on is hooked to
  `blueworx_site_protection_applies`, which fires from
  `blueworx_intercept_requests()` on `init` priority 1 — before the main
  WordPress query has run. The exemption previously called
  `blueworx_public_is_owned_page()`, which depends on `is_page()` /
  `get_queried_object()`; both are unreliable that early and always reported
  "not a page", so the exemption never fired. Turning Site Protection on
  would have `wp_die()`'d every logged-out visitor to the plugin's own
  marketing pages. Added `blueworx_public_is_owned_request_path()`, an
  `init`-safe check that compares the normalized request path against the
  plugin's page registry (mirroring `blueworx_is_custom_login_request_path()`
  in `includes/login-security.php`), and pointed the exemption at it.
  `blueworx_public_is_owned_page()` is unchanged and remains correct for its
  existing query-time callers.

## [1.19.0] - 2026-07-21

### Added
- **Plugin-owned page registry.** `blueworx_public_pages()` declares the pages
  this plugin renders (slug ⇒ title/template — one entry, `home`, for now);
  `blueworx_public_install_pages()` creates a real WordPress Page for each one
  on activation so menus, SEO plugins, and later content editing all work
  normally. Idempotent and safe to run on every activation: pages are matched
  by their previously-stored ID first, then by slug, so a page the user has
  renamed or moved is recognised rather than duplicated.
- **Page-ownership lookup for later rendering.** `blueworx_public_is_owned_page()`
  and `blueworx_public_current_template()` resolve the current request against
  the registry to an absolute template path (or `null`), which `template_include`
  will use in a later task to take over rendering from the active theme.
  `includes/public/assets.php` now gates its stylesheet enqueue on this real
  ownership check instead of the `is_front_page()` placeholder from 1.18.1.

### Security
- **Site Protection now exempts plugin-owned public pages.** Site Protection's
  frontend gate (`includes/login-security.php`) can `wp_die()` logged-out
  visitors — appropriate for a site still in progress, but it would also take
  down the deliberately-public marketing pages this plugin renders. A new
  `blueworx_site_protection_applies` filter (applied only around that gate, no
  other behaviour changed) lets `blueworx_public_exempt_from_site_protection()`
  exclude owned pages from the block. Unhooked — i.e. with `public_site` off —
  this is a no-op and Site Protection behaves exactly as before.

## [1.18.3] - 2026-07-21

### Fixed
- **Two more bare element selectors survived the 1.18.2 scoping pass by
  hiding inside mixed selector lists.** `.h1, .h2, h3, h4 { text-wrap:
  balance; }` and `.lead, p, .ttext, .plan-desc, .fd-sub { text-wrap:
  pretty; }` each carried a bare `h3`/`h4`/`p` alongside already-scoped
  classes — the bare `p` in particular restyled every paragraph the active
  theme rendered. Both rules are now `.h1, .h2, .bw-page h3, .bw-page h4`
  and `.lead, .bw-page p, .ttext, .plan-desc, .fd-sub`. The regression test
  in `tests/public-site.spec.js` only flagged rules that were entirely
  bare, which is exactly why these survived it; it now inspects every
  comma-separated part of every selector list and flags any bare part on
  its own.

## [1.18.2] - 2026-07-21

### Fixed
- **`assets/css/public.css` leaked bare element selectors document-wide.**
  The stylesheet was ported from a standalone front-end where it owned the
  whole document, and was scoped to `.bw-page` for the `*` reset and `body`
  rule — but five bare element selectors (`img`, `button`, `nav` twice
  including its responsive variant, `footer`) were missed and still matched
  document-wide. In WordPress that restyled the admin bar and the active
  theme's own markup — worst of all, a theme's `<nav>` picked up a full
  96px sticky reskin. All five are now scoped under `.bw-page`. Added a
  regression test (`tests/public-site.spec.js`) that reads the stylesheet
  from disk and fails if any unscoped bare element selector reappears.

## [1.18.1] - 2026-07-21

### Added
- **Public asset pipeline.** `assets/css/public.css` ports the headless
  front-end's `globals.css` (marketing sections only — the client portal and
  auth forms stay out of scope), scoped under `.bw-page` so its reset can't
  reach the admin bar or block styles. `blueworx_enqueue_public_assets()`
  enqueues it alongside the existing self-hosted font stylesheet on
  `wp_enqueue_scripts`, versioned via the existing asset-version helper so a
  CSS change reaches visitors immediately instead of waiting on a stale
  browser cache. Gated on `is_front_page()` until Task 3 adds real page-
  ownership detection.

## [1.18.0] - 2026-07-21

### Added
- **Public front-end module skeleton.** New `includes/public/*.php` layer
  (bootstrap, pages, render, assets, helpers) and a `templates/` directory,
  gated behind a new `public_site` feature flag under **BlueWorx →
  Enhancements → Appearance**. Nothing renders yet — this lays the groundwork
  for the plugin to serve its own marketing site independently of the active
  theme. On by default, matching the "absent option means enabled" convention
  so a fresh install ships ready.
- `templates/` added to the release zip allowlist (`scripts/build-zip.mjs`) so
  the module has somewhere to ship its markup once later tasks populate it.

## [1.17.0] - 2026-07-21

### Added
- **`POST blueworx/v1/render` — shortcodes that actually work on a headless
  front-end.** A shortcode's markup already reached the front-end via
  `content.rendered`, but its CSS and JS never did: plugins enqueue those on
  `wp_enqueue_scripts`, which does not fire for a REST request. Anything
  interactive therefore arrived as inert markup or an empty container. This
  endpoint renders the shortcode and returns the assets it enqueued —
  `{ html, shortcodes, styles[], scripts[] }` — including `wp_localize_script`
  data and inline before/after scripts, so the front-end can load them alongside
  the markup.
  - Returns the **full dependency closure in load order**, not just the handles
    enqueued directly. WordPress resolves dependencies at print time, which never
    happens here, so a front-end given only the enqueued handle would load a
    script whose jQuery dependency was missing and it would throw.
  - Relative asset URLs are made absolute, since the front-end is on another
    origin and could not resolve them otherwise.
  - `with_global_enqueue: true` also fires `wp_enqueue_scripts` for plugins that
    register assets there rather than in the shortcode callback. Off by default,
    because it also pulls in everything the theme and other plugins enqueue.
- **"Renderable shortcodes" setting** under BlueWorx → Headless.

### Security
- The render endpoint **fails closed**: the allowlist is empty by default and the
  endpoint refuses everything until tags are named explicitly. A shortcode is a
  PHP function, so an unrestricted `do_shortcode()` on public input would be
  remote code execution by proxy.
- A request mixing allowlisted and non-allowlisted tags is refused whole, rather
  than returning partial markup that looks like a success.
- Rate limited (30 per 5 minutes per IP), and output buffering prevents anything
  a callback echoes from corrupting the JSON body.

### Notes
- **This is a workaround, not a cure.** Shortcodes depending on `wp_head`, the
  loop, or inline output outside the enqueue system may still misbehave, and each
  third-party plugin is its own compatibility question. `HEADLESS_INTEGRATION.md`
  §6.3 documents the contract and the limits.
- Closes #25. The front-end side is `bluegroup_project_blueworx#11`.

## [1.16.4] - 2026-07-21

### Fixed
- **Two tests that could never have caught a regression.**
  - The critical-CSS assertion used `toContainText` on a `<style>` element.
    A `<style>` renders no text, so it always saw `""` — the assertion could not
    pass regardless of the CSS. Now asserts on `textContent`.
  - The unmapped-menu test demanded exactly one Custom Content group, which only
    exists when a third-party plugin registers a top-level menu. It therefore
    tracked what happened to be installed rather than the plugin's behaviour, and
    failed on a clean site where rendering no group is correct. Now asserts the
    invariant (never more than one) and keeps the real guard: Site holds only
    mapped core menus.
- **A third bug the above was masking.** That test's Site-group allowlist never
  included `nav-menus.php`, even though 1.15.0 deliberately promoted Menus into
  the Site group. The line could not fail because the assertion before it always
  threw first.

### Changed
- `retries: 1` in the Playwright config. The local harness serves WordPress from
  PHP's single-threaded built-in server, and a sign-in occasionally times out
  under load. One retry absorbs that; a genuine failure still fails twice and is
  reported as failed rather than flaky.

### Notes
- Full suite against the harness: 41 passed, 2 skipped, 1 flaky, 0 failed.
- Fixes #37.

## [1.16.3] - 2026-07-21

### Fixed
- **Sidebar rows are a uniform height again.** A row carrying a count badge
  rendered 1px taller than one without. `.wp-menu-name` is a flex container, so
  the row is as tall as its tallest item — and the badge's `line-height: 1.5`
  plus `2px` vertical padding came to 20.5px against the label's 18.2px line box.
  The badge is now a fixed 18px box with centred content, which cannot drive the
  row height. Visually it is a slightly smaller pill; the rows are level.

### Notes
- Found by #24's harness. Fixes #36.

## [1.16.2] - 2026-07-21

### Security
- **The CORS allowlist now actually restricts origins.** WordPress core echoes
  any `Origin` back with `Access-Control-Allow-Credentials: true` on REST routes.
  Core's handler ran first and this plugin's allowlist only ever *declined to add*
  headers — it never removed core's — so any site could make credentialed
  cross-origin calls to `blueworx/v1` and `wp/v2` and read the responses. That
  matters here because the refresh cookie is deliberately `SameSite=None`.
  Core's handler is now removed and replaced, so a disallowed origin receives no
  `Access-Control-Allow-Origin` at all.
- **Fails closed.** An empty allowlist now denies every cross-origin caller
  instead of effectively allowing everyone.
- `Vary: Origin` is sent whether or not the origin is allowed, so a shared cache
  cannot serve one origin's response to another.

### Changed
- CORS now covers `wp/v2` as well as `blueworx/v1`, because the headless
  front-end reads content bodies from `wp/v2` and simply removing core's handler
  would otherwise have broken it. Namespaces are filterable via
  `blueworx_headless_cors_namespaces`.
- Allowed responses now also send `X-WP-Nonce` in `Access-Control-Allow-Headers`
  and expose `X-WP-Total`, `X-WP-TotalPages` and `Link`, matching what core used
  to provide so pagination keeps working.

### Notes
- **Breaking for other REST namespaces.** Third-party namespaces outside
  `blueworx/v1` and `wp/v2` no longer receive CORS headers, since core's
  permissive handler is gone. Add them via the filter if an integration needs
  them — deliberately opt-in rather than open by default.
- Found by #24's harness the first time the suite ran for real. Fixes #35.

## [1.16.1] - 2026-07-21

### Changed
- **CI now actually tests this plugin.** `ci.yml` switches to
  `use_local_wordpress: true`, so each run provisions a disposable WordPress on
  the runner (PHP + SQLite, no Docker) and tests against that instead of a
  placeholder staging URL. Also passes `wp_login_path: admin_login`, because this
  plugin moves the login screen off `wp-login.php` and every admin spec would
  otherwise fail at the sign-in step.
- Removed `allow_zero_tests`. It was a suppressed alarm added in 1.15.1 to keep
  the repo unblocked; with a real test target the gate can do its job.
- `.wp-test/` added to `.gitignore` — it holds a full WordPress tree.

### Notes
- For context on what this changes: the suite had been skipping **all 40 tests**
  in CI since it was written, reporting green while asserting nothing. Running it
  for real surfaced #35, #36 and #37.
- Run the same instance locally:
  `node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up --plugin .`
  See `docs/wordpress-test-harness.md` in the foundation.

## [1.16.0] - 2026-07-20

### Added
- **Client Roles.** Three assignable roles for client accounts — **Admin —
  Business Owner**, **External Dev** and **Content Editor** — that show or hide
  whole backend areas, gated behind a new *Client Roles* toggle on BlueWorx >
  Enhancements (on by default). Areas are hidden by capability where possible and
  by the plugin's existing sidebar groups for third-party menus:
  - *Business Owner* — everything except Plugins and the file/code editors; Tools
    trimmed (no import/export). Keeps Settings, Appearance, Users and the store.
  - *External Dev* — plugins, appearance, tools and settings, but no Users
    management (own account only) and no file editors.
  - *Content Editor* — posts, pages, media and comments, plus editing other
    users' accounts (not deleting them, unless enabled below). Everything
    technical is hidden.
- **"Allow Content Editors to delete users"** setting under Client Roles, off by
  default, which grants Content Editors the delete-user capability.
- The three roles appear automatically in the Site Protection role lists.

### Security
- **Admin accounts protected from Content Editors.** A Content Editor's
  user-editing capability cannot be used to edit, delete or promote an
  administrator, closing a password-reset takeover path.
- **BlueWorx console is administrators-only** when Client Roles is on — hidden and
  URL-blocked for the client roles even though they may hold `manage_options`.

### Notes
- Client roles are registered on activation and via a one-time migration, persist
  across deactivation, and are removed (definitions only) on uninstall — user
  assignments are preserved, so reinstalling restores them.

## [1.15.1] - 2026-07-20

### Changed
- **CI unblocked, not fixed.** The shared foundation workflow now fails any run
  that executes zero Playwright tests. This project's `preview_url` is still a
  placeholder, so every spec skips itself — meaning that gate would fail every
  PR here. `ci.yml` now passes `allow_zero_tests: true`, which downgrades the
  failure to a warning while the test-host decision is open.

  **This project's CI asserts nothing today.** The flag is a suppressed alarm,
  not a passing suite, and is tracked for removal in #24.
- `ci.yml` now passes `secrets: inherit`, so `WP_ADMIN_USER` / `WP_ADMIN_PASS`
  reach the Playwright step once they exist as repo secrets. Until then the
  admin specs continue to skip.

## [1.15.0] - 2026-07-20

### Added
- **Menus in the Site group.** The WordPress Menus editor (`nav-menus.php`),
  which core nests under Appearance, is now promoted to its own top-level row in
  the Site group of the re-skinned sidebar, directly after Appearance, with a
  matching list icon.

### Changed
- **Default dashboard layout.** On dashboards a user has not customised, Elementor
  Overview, Quick Draft and Site Management are hidden by default, leaving At a
  Glance, SureRank Website Insights, Object Cache Pro, Site Health Status and
  Activity visible. Applied through `default_hidden_meta_boxes`, so it only sets
  the default and never overrides a user's own Screen Options; widgets whose
  plugins are inactive are unaffected.
- **Edit Menu arrows** now use inline-SVG chevrons from the sidebar icon set
  (stroke 1.75, viewBox 24) in place of the `▲`/`▼` glyphs, so the reorder
  controls match the rest of the re-skin.

### Fixed
- **Admin chrome flash on slow connections.** The layout skeleton that hides the
  native admin bar and offsets the sidebar below the fixed top bar is now printed
  inline in the document head, so it can no longer be deferred by an asset
  optimiser or arrive late behind the main stylesheet. This removes the transient
  stray line under the top bar and the sidebar overflowing its bounds before the
  full theme applied.

### Removed
- **Orphaned managed roles.** A one-time migration removes the `Business Owner`,
  `External Admin` and `Content Editor` roles left in the database by the role
  editor removed in 1.8.0. A role is only removed when it has no users assigned;
  any role still in use is left in place (and its slug recorded in
  `blueworx_orphaned_roles_skipped`) so no account is stranded. The roles can be
  reintroduced later.
- **Stale PHPCS capability rule.** Dropped the `WordPress.WP.Capabilities`
  override whitelisting `blueworx_edit_elementor_templates` — a remnant of the
  same removed role editor; the capability and its `includes/user-roles.php` no
  longer exist.

## [1.14.0] - 2026-07-16

### Changed
- **Sidebar pinned to the viewport.** The sidebar now fills from below the top
  bar to the foot of the screen and no further, so a long page no longer
  stretches the dark panel into a tall empty column below the last item. The
  menu gets its own scroll, independent of the page scroll, and only scrolls as
  far as its last item. The scrollbar is hidden (scroll still works by
  wheel/trackpad/keys) and the foot clearance was raised so the last items clear
  the pinned Log Out on short viewports.
- **Brand dot** recoloured to `#1d2043`, resized, moved to the top-right and
  clipped to the sidebar so it no longer bleeds into the content area. The header
  is opaque so menu content disappears behind it when scrolling.
- **Open submenu background is transparent** — the inline (current) submenu now
  reads as part of the sidebar rather than a dark block; only the guide rail and
  active tick remain. Fly-out submenus keep their solid fill.
- **BlueWorx menu icon** is now Lucide `layout-panel-top`.

### Fixed
- **Buttons keep their radius** through hover, focus and active — primary and
  `page-title-action` buttons were snapping to square corners on click because
  core's `:active` rule reset the radius.
- **Removed WordPress's fly-out pointer triangle** (the dark caret on the right
  of any submenu item).

### Note
- Giving the expanded sidebar its own scroll clips horizontally, so hover
  fly-out submenus for non-current items are suppressed when the sidebar is
  expanded (the current section's submenu still shows inline). Folded mode keeps
  its fly-outs, which are the only way to reach a submenu when collapsed.

## [1.13.0] - 2026-07-16

### Added
- **Profile screen redesign** (`profile.php` / `user-edit.php`). When the admin
  re-skin is on, the native profile form is restructured into a dark hero header
  — avatar, display name, role badge, `@handle · Member since · post count`, plus
  **View Posts** and **Save Changes** — over a two-column card layout. Native
  form sections are MOVED (never recreated) into the cards, so every field,
  nonce and hidden input still posts through core's save handler; the hero
  **Save Changes** proxies core's own submit button. WordPress's two-column
  `.form-table` rows are flattened to stacked, label-above fields inside each
  card. Non-native concepts from the comp (two-factor, session device counts,
  email-verified badges) are intentionally omitted rather than faked.
- **Sidebar brand dot.** A soft indigo radial now sits behind the top of the
  sidebar. The charcoal panel moved to `#adminmenuback` so the dot shows through
  the (now transparent) menu list; fly-out submenus keep their solid fill.
- **Submenu differentiation.** Expanded sub-items now sit under a hairline guide
  rail, and the active child carries a short brand-coloured tick.

### Changed
- **Log Out is pinned** to the foot of the sidebar (783px and up) and stays in
  view while the menu items above it scroll.
- **Consistent default menu order.** The computed default arrangement now sorts
  recognised items into the same design order the render filter uses, so two
  unedited sites no longer draw the Content group in different orders depending
  on whether either had ever saved the menu.
- **Custom Content sits above Content** in the sidebar group order.
- **Dashboard stat tiles** render two per row instead of four.
- **Custom-content icon** is a distinct "shapes" glyph rather than the generic
  tag, and the brand block subtitle reads **BlueWorx** in place of "wp-admin".

## [1.12.0] - 2026-07-15

### Added
- **BlueWorx admin top bar.** On screens 783px and wider, the WordPress admin bar
  is replaced by the design's top bar: the current page title, a **View Site**
  button that opens the front end in a new tab, and a user menu (Edit Profile,
  Log Out). A brand block — the site's initial in an indigo mark, the site name,
  and "wp-admin" — sits above the menu. The user menu is a native `<details>`
  disclosure, so no JavaScript ships with it. Below 783px the native admin bar is
  kept, because it carries WordPress's responsive menu toggle.
- **Semantic sidebar groups.** The sidebar is grouped by meaning — Overview,
  Content, Custom Content, Site — with a heading above each. Custom post types
  are recognised by shape, so any type a site registers top-level lands in Custom
  Content without being listed. A group with nothing in it renders no heading,
  and unrecognised third-party menus fall back to Custom Content rather than
  being dropped — a plugin's own top-level menu is nearly always the content it
  manages, where Site is core's housekeeping. BlueWorx sits in Overview, directly
  below the Dashboard it extends.
- **Design icon set** on the mapped core menus and on every custom post type,
  replacing dashicons. Icons stroke `currentColor`, so they follow their label
  through idle, hover and active. Unmapped third-party menus keep their own
  glyph.
- **Count badges** on Posts, Media, Pages, custom post types and Plugins, from
  core's count APIs. Zero renders no badge. Where WordPress already draws its own
  bubble (plugin updates, comments awaiting moderation), core's wins — one row
  never carries two counts.
- **Log Out row** at the foot of the sidebar, alongside the top bar's user menu.
  The design shows both.

### Changed
- **Sidebar order follows the design by default.** Within a group, items now use
  the design's order — Content reads Posts, Media, Pages — instead of the older
  "shortest label, then A–Z" rule, which predates the groups and was written for
  a single flat list. Sites that have saved the Edit Menu keep their own
  arrangement: the design sets the default, an admin's choice overrides it.
- **Collapse Menu is hidden**, per the design. It reappears if the menu is
  folded, so the state is always reversible, and it is left alone below 961px
  where WordPress auto-folds and it is the only way back out.
- **Edit Menu rebuilt around the semantic groups.** One card per group plus
  Hidden, replacing the Main/More/Hidden columns, so the screen mirrors the
  sidebar it edits. Drag now uses the browser's own drag-and-drop instead of
  jQuery UI, and every row gains up/down buttons — the old screen was drag-only,
  and so unusable by keyboard. Moving an item into another group, or into
  Hidden, is saved with the order.
- **Sidebar matches the design more closely.** Widened to 232px when expanded,
  with rounded menu rows, an indigo active pill, the current-item arrow removed,
  and icons that follow their label colour instead of being tinted indigo.
- **Lighter shadows.** `--bw-shadow-card` is now a soft two-stop lift
  (`0 1px 2px / 0 4px 12px` at 4–5% alpha) instead of the heavier stack, matching
  the design's elevation across cards, tables, notices, and tiles.
- **Login screens are properly designed.** All wp-login actions (log in, lost
  password, reset, register) now use a brand mark in place of the WordPress logo,
  a centred white card with the light shadow, Sora/Inter type, full-width charcoal
  button, rounded inputs with indigo focus rings, and restyled messages/errors.

### Fixed
- **Custom post types no longer torn out of the menu that registered them.** An
  earlier pass in this release lifted every type registered with
  `show_in_menu => '<parent>'` into its own top-level row. On a real site that
  shredded the structure the site had authored — Clubhouse registers Sports,
  Teams, Fixtures, Events, Sponsors and People under its own Content menu, and
  promoting them scattered six rows across the sidebar while leaving the Content
  parent behind them, emptied. Where a site nests its post types is a statement
  about how that site is organised, and overruling it is not this plugin's call.
  Types now stay where they were registered, and Custom Content is populated by
  the parent menus themselves.
- **The sidebar overhung its own panel.** `#adminmenu` carried `6px` of side
  padding, but core sizes the menu with a width and leaves it content-box, so
  that padding was added to the width and the rows spilled out of the dark panel
  onto the content area. The padding is gone; the gutter is the row margin alone.
- **Rows were not all the same height.** Only mapped slugs get a design SVG; an
  unmapped third-party menu keeps core's dashicon, whose glyph box is 36px
  (`20px/1` plus `8px` vertical padding) against the mapped rows' 20px. With the
  same anchor padding on both, unmapped rows stood 16px taller than their
  neighbours. The icon slot is now a fixed 20px box for every row, so they all
  settle at the shorter height.
- **Hovering a section's top item highlighted the whole section.** Core paints
  hover on the `li`, not the anchor — and that `li` hosts both the group's
  `::before` heading and, on the current item, its inline submenu. The fill
  therefore bled across the heading and every row beneath it. The `li` no longer
  takes a background; state lives on the anchor alone, as the design intends.
- **Dashboard hero tiles could fail to appear.** The tiles were registered at
  `core` priority, but `do_meta_boxes()` renders `high` → `sorted` → `core` and
  moves any saved user layout into `sorted` — so on a dashboard that had been
  rearranged, the tiles were pushed below everything else. They are now registered
  at `high` priority and stay at the top.
- **Edit Menu listed "Plugins 0".** Core hangs a live update bubble off some menu
  labels (`Plugins <span class="update-plugins count-0">…`), and flattening the
  label with `wp_strip_all_tags()` folded that count into the name — so the screen
  listed "Plugins 0" and its reorder buttons announced "Move Plugins 0 up" to a
  screen reader. The bubble is now stripped before flattening, in both the screen
  and the stored `blueworx_admin_menu_item_labels` fallback, so the count no
  longer leaks into either.

### Internal
- **Playwright: the admin suite no longer hangs on its second click.** WordPress
  6.9 ships cross-document view transitions in wp-admin, guarded by
  `@media (prefers-reduced-motion: no-preference)`. In headless Chromium those
  transitions permanently stop the page being rendered: `requestAnimationFrame`
  never fires again while timers keep running and the DOM stays queryable. Every
  Playwright actionability check is built on rAF, so from the first click-driven
  navigation onward every `click`, `setChecked` and `hover` hung for its full
  timeout, reporting "waiting for element to be visible, enabled and stable" about
  elements that were provably all three. Only page-initiated navigations arm it,
  which is why `page.goto()` was always fine and why the *first* click of a test
  always worked and the *second* never did. The suite now emulates reduced motion
  via a fixture in `tests/helpers.js`, opting out of core's rule at source. This
  MUST be done imperatively — `use: { reducedMotion: 'reduce' }` in
  `playwright.config.js` is accepted and then silently ignored (verified on
  @playwright/test 1.61.1), which is what made this look like "not view
  transitions" for two sessions. The site itself was never affected; real browsers
  finish the transition and keep painting.
- **The theme flag is restored even when a test dies.** `admin_theme` is a real
  setting on a real site. The on/off test turned it off, and a failure before the
  restore left staging unthemed and every later test in the file asserting against
  stock WordPress. Restoring now happens in an `afterEach`, which still runs when
  the test throws or times out.
- **`click({ force: true })` removed** from the Edit Menu save. It was masking the
  view-transition freeze above; an honest click works now.
- **Credentials come from a gitignored `.env`** via dotenv, so runs no longer need
  them pasted onto every command line (and into shell history). Copy
  `.env.example` to `.env`. Anything already set in the environment still wins, so
  CI can inject real secrets with no `.env` present.
- **The auth/login REST test skips when the site has no auth configured.** A site
  without a JWT secret answers `503 blueworx_auth_unconfigured` and never looks at
  the credentials, so there is no rejection behaviour to assert — an environment
  gap, not a defect. It now skips with that reason instead of reporting a red no
  code change could fix. Any other 503 still fails.

### Known issues
- `tests/headless-rest.spec.js` › *CORS is not granted to a disallowed origin*
  fails, and is left failing deliberately. The plugin's CORS allowlist has no deny
  path: it never removes core's `rest_send_cors_headers`, so any origin is echoed
  with `Access-Control-Allow-Credentials: true`. Pre-existing and unrelated to this
  release; tracked in #20 rather than hidden behind a skip.
- No Playwright test gates a pull request. CI points at a placeholder URL, so every
  admin spec skips, and the shared workflow never deploys the plugin — it would
  test whatever is installed on staging rather than the code under review. Tracked
  in #21.

### Removed
- **The "More" menu is retired.** The design replaces the Main/More/Hidden split
  with the four semantic groups, so More has no equivalent. Items that sat in it
  are assigned to their natural group and **reappear as top-level rows** — More
  was a grouping affordance, not a hiding one, and the separate Hidden bucket is
  untouched. Existing sites are migrated automatically.
- **`#wpfooter` is hidden** on all admin screens while the theme is active.

## [1.11.0] - 2026-07-14

### Added
- **BlueWorx admin re-skin.** A CSS-first re-skin of wp-admin and the login
  screen using the BlueWorx design system (indigo/charcoal/lavender palette,
  self-hosted Sora + Inter, rounded cards, restyled admin bar and sidebar). It
  restyles WordPress's own native elements — no framework, no replacement markup.
  Shipped as a feature flag under **BlueWorx → Enhancements → Appearance**
  (`admin_theme`, default on); turn it off to return to the standard WordPress
  appearance.
- **Hybrid Dashboard.** The Dashboard gains a hero row of four live stat tiles
  (Posts, Pages, Comments, Media) and keeps the native Activity, Quick Draft, and
  Site Health widgets, restyled to match. The Welcome panel, WordPress Events &
  News, and At a Glance widgets are removed while the theme is active.

## [1.10.1] - 2026-07-13

### Added
- **Headless integration guide.** New `HEADLESS_INTEGRATION.md` documenting the
  `blueworx/v1` REST contract, auth model, content/routing, revalidation, and
  SureCart proxy so a headless Next.js frontend can be pointed at this repo to
  build against the plugin. Documentation only; no runtime change.

## [1.10.0] - 2026-07-13

### Added
- **Default admin-menu arrangement.** For administrators, the admin menu now
  pins BlueWorx directly below Dashboard, keeps only Posts, Media, Pages, and
  Users visible, moves every other core WordPress item into **More**, and leaves
  plugin-added items visible below the defaults. All items are ordered by title
  length (shortest first, alphabetical tiebreak); More stays last. Saving the
  Edit Menu page freezes the arrangement, after which saved choices always win.
  Sites that had already arranged their menu are detected on upgrade and keep
  their existing layout.

## [1.9.0] - 2026-07-13

### Added
- **Feature settings page.** BlueWorx → Enhancements is now a grouped settings
  form with an on/off toggle for every enhancement function, organized into
  Security & Access, Content, Notifications & Cleanup, Performance, and Admin
  Menu sections. Disabling a function makes it fully inert and hides its page
  and detail controls; every function defaults on, so existing sites are
  unchanged.
- **Editable login slug.** The custom login path is now configurable on the
  settings page (was the fixed `admin_login`). Turning the login function off
  restores the standard WordPress login.

## [1.8.0] - 2026-07-13

### Removed
- **Role editor / user-role controls**, to be rebuilt later. Removes the
  "Edit Role" admin page, the three managed roles (Business Owner, External
  Admin, Content Editor), the capability editor, and the backend-page
  permission engine (`includes/user-roles.php` and `assets/js/role-editor.js`).
  The separate "Site Protection" feature (role-based frontend/backend view
  gating) is unchanged. Existing sites keep their roles and saved role options
  in the database — only the code is removed, so nothing is lost when the
  feature is rebuilt.

## [1.7.0] - 2026-07-13

### Changed
- **Plugin slug renamed** from `blueworx-project-wordpress-labs` to
  `blueworx-labs-wordpress`, aligning it with the renamed repository. The main
  file, folder, text domain, admin page slug, asset handles, CI `plugin_slug`,
  and build/version scripts all move to the new slug. The display name
  ("BlueWorx Labs | WordPress Enhancements") is unchanged.

### Migrations
- Added a one-time migration (labs DB version 2) that remaps saved admin-menu
  customizations (order, hidden/toggled items, and item labels) from the old
  slug to the new one, so admins keep their menu settings across the rename.

## [1.6.0] - 2026-07-09

### Added
- **Headless REST layer** under `/wp-json/blueworx/v1/` implementing
  IMPLEMENTATION_PLAN.md Phases 1–6:
  - **Auth core:** JWT access tokens (bundled `firebase/php-jwt` v7, HS256,
    secret from `wp-config`), a `determine_current_user` filter, and rotating
    refresh-token families with reuse-detection and `token_version` global
    revocation. Endpoints: `/auth/login`, `/refresh`, `/logout`, `/logout-all`,
    `/me`.
  - **Accounts:** `/account/register` (open/invite/closed modes, default
    closed), `/verify`, `/resend-verification`, `/password/forgot|reset|change`,
    `PATCH /account`, and `DELETE /account` (re-auth required). Reset/register
    responses are non-enumerating.
  - **Public content:** `/menus/{location}`, `/site`, `/resolve`,
    `/acf-options`, an ACF-to-core-REST bridge, and a settings-driven CPT
    registrar.
  - **CORS + revalidation:** credentialed CORS (exact-origin echo, never `*`)
    and an outbound on-demand revalidation webhook (default OFF; never triggers
    a Netlify build).
  - **SureCart proxy:** public catalogue plus ownership-scoped `/me/*` and write
    endpoints; ownership fails closed. Disabled by default.
- New **Headless** admin settings tab for all non-secret configuration; secrets
  are read only from `wp-config.php` constants.
- Transient-based rate limiting and lockout on auth endpoints.
- Custom tables for refresh-token families and invites (installed on
  activation / schema-version upgrade); daily token garbage-collection cron.

### Changed
- Raised the minimum PHP version to **8.0** (required by `firebase/php-jwt` v7,
  which carries the fix for the v6 advisory CVE-2025-45769). PHP 7.4 is EOL.
- Hardened the managed-role backend page gate to ignore off-site referers
  (fail closed) rather than trusting any `wp_get_referer()` value.

### Dependencies
- Added `firebase/php-jwt` ^7.0 (Composer, PHP). Its HS256 subset is bundled in
  `includes/rest/lib/firebase-jwt/` so the shipped plugin is self-contained.

## [1.5.1] - 2026-07-09

### Added
- Authored `IMPLEMENTATION_PLAN.md` — the authoritative build contract for the
  headless REST layer (§1–§16), including the concrete §13 endpoint map and §14
  phase order. Settles the JWT library (`firebase/php-jwt`), refresh-token
  revocation storage, rate-limit mechanism, registration modes, and the
  settings-vs-`wp-config` split; defers the LatePoint proxy to Phase 7 (no
  official LatePoint REST API). Unblocks Phases 1–6. Documentation only — no
  runtime code changed.

## [1.5.0] - 2026-07-08

### Changed
- Renamed the plugin to **BlueWorx Labs | WordPress Enhancements** (slug
  `blueworx-project-wordpress-labs`); constants now use the `BLUEWORX_LABS_`
  prefix and the text domain is `blueworx-project-wordpress-labs`.
- Onboarded the repo to `bluegroup_core_foundation`: shared CI guardrail
  workflow, PR/issue templates, Claude settings, `CLAUDE.md`, `approved-deps.json`,
  Composer/WPCS + ESLint lint config, npm build tooling, and Playwright scaffold.

### Removed
- **Breaking:** removed the Elementor SureCart pricing-table widget and its
  assets (foundation "no page builders" rule). Sites rendering that widget in
  Elementor need an alternative.

## Earlier history

Versions 1.0.0–1.4.30 were released under the previous name **BlueWorx
Enhancements**. See `readme.txt` history or git tags for details:
custom login-URL hardening, Cloudways/Varnish cache refresh, role editor and
custom roles, disable-comments, admin-email suppression, profile cleanup, and
page excerpts.
