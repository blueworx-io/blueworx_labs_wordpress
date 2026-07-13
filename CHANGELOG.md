# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/); this project uses semantic
versioning.

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
