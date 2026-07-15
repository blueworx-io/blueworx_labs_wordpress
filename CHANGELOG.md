# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/); this project uses semantic
versioning.

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
  are recognised by shape, so any type a site registers lands in Custom Content
  without being listed. A group with nothing in it renders no heading, and
  unrecognised third-party menus fall back to Site rather than being dropped.
- **Custom post types get their own sidebar rows, with their submenus intact.** A
  post type registered against a parent menu (`show_in_menu => '<parent>'`) used
  to stay buried as a submenu and never reached Custom Content. Those types are
  now lifted to top-level rows, matching the design, and are given the All / Add
  New / taxonomy rows WordPress only creates for types it places at the top level
  itself — so a promoted type nests exactly like a native one. The parent menu is
  left in place.
- **Design icon set** on the nine mapped core menus and on every custom post
  type, replacing dashicons. Icons stroke `currentColor`, so they follow their
  label through idle, hover and active. Unmapped third-party menus keep their own
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
- **Dashboard hero tiles could fail to appear.** The tiles were registered at
  `core` priority, but `do_meta_boxes()` renders `high` → `sorted` → `core` and
  moves any saved user layout into `sorted` — so on a dashboard that had been
  rearranged, the tiles were pushed below everything else. They are now registered
  at `high` priority and stay at the top.

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
