# Feature Toggle Settings Page — Design

**Plugin:** BlueWorx Labs | WordPress Enhancements (`blueworx-labs-wordpress`)
**Date:** 2026-07-13
**Status:** Approved design, ready for implementation plan

## Goal

Turn the static **BlueWorx → Enhancements** status table into a fully
controlled settings page where every enhancement function can be toggled on or
off, grouped into clear sections. Functions that are always-on today must
default to **on**, so out-of-the-box behavior is unchanged.

Scope is the **Enhancements bundle only**. The Headless REST layer keeps its
own separate **Headless** tab, unchanged. The role editor stays removed (rebuilt
separately later).

## Decisions (locked)

| Decision | Choice |
| --- | --- |
| Toggle scope | Enhancements bundle only; Headless tab untouched |
| Sections | 5 sections, grouped by concern |
| Off-behavior | Fully inert (hooks not registered) **and** hidden (submenu page + nested detail controls) |
| Login model | Merge Custom login URL + Login protection into one `login` feature with an **editable slug**; OFF restores standard `/wp-login.php` |
| Defaults | Every toggle defaults **on** (absent option = on) |
| Storage | Per-feature option `blueworx_feature_{key}`, mirroring the existing `blueworx_headless_setting()` pattern |
| DB migration | None required (absent option = on = current behavior) |

## Architecture

### New file: `includes/features.php`

Required **first**, immediately after `includes/helpers.php` in the main plugin
file, so its functions are available when the other feature files load and
register (or skip) their hooks.

Provides:

- `blueworx_get_feature_definitions()` — the single source of truth. Returns an
  ordered, section-grouped registry. Each feature entry has:
  - `key` — option suffix, e.g. `login`, `comments`, `page_excerpts`
  - `label` — plain-English name (i18n)
  - `description` — existing plain-English description (i18n)
  - `section` — one of the five section keys
  - `default` — `'1'` for every feature (all default on)
  - `detail` — optional callback that renders nested detail controls (used by
    `login`, `site_protection`, `application_passwords`)
- `blueworx_get_feature_sections()` — ordered section keys → section labels.
- `blueworx_feature_enabled( $key )` — reads option `blueworx_feature_{key}`,
  returns `true` unless the stored value is exactly `'0'`. Absent → on.

### Storage

- Master toggles: per-feature option `blueworx_feature_{key}` = `'1'` / `'0'`.
- Detail settings keep their **existing** options, read only when the master
  feature is on:
  - Site Protection: `blueworx_{area}_protection_enabled`,
    `blueworx_{area}_protection_roles`
  - Application Passwords: `blueworx_show_application_passwords`
  - Login slug (new): `blueworx_login_slug` (default `admin_login`)
- Turning a feature off **never deletes** its detail options; turning it back on
  restores the previous sub-settings.

### Gating

Each `includes/*.php` feature wraps its top-level hook registration in
`if ( blueworx_feature_enabled( '<key>' ) ) { … }`. Because the feature files are
`require`d during plugin load (after WordPress core is available), `get_option`
works at registration time. A disabled feature registers **nothing**.

Submenu pages (Cache, Edit Menu) are registered in
`blueworx_register_settings_page()` only when their feature is enabled. The
matching `admin-post` handlers (`blueworx_clear_cache_now`, menu save, etc.) also
check the flag and no-op when the feature is off, so a stale direct POST does
nothing.

### Two files host two features each — gate independently

- `cache-refresh.php`:
  - `cache_auto` → the `save_post` / `trashed_post` / `untrashed_post` /
    `before_delete_post` hooks
  - `cache_manual` → the `admin_post_blueworx_clear_cache_now` handler + the
    Cache page
- `profile-cleanup.php`:
  - `profile_cleanup` → profile screen cleanup
  - `application_passwords` → the
    `wp_is_application_passwords_available_for_user` filter + the profile-screen
    hide/show script

### Login change

- New `login` feature merges the old "Custom login URL" and "Login protection".
- `blueworx_login_slug()` returns the sanitized option `blueworx_login_slug`,
  default `admin_login`; used everywhere the `BLUEWORX_CUSTOM_LOGIN_SLUG`
  constant is read today. The constant remains defined as the ultimate fallback
  default.
- All of `login-security.php`'s hooks register only when `login` is enabled.
  OFF → none register → standard WordPress login/admin behavior is fully
  restored (safe fallback; no lockout is possible).

## The settings page (UX)

Rebuilds `blueworx_render_enhancements_page()` on the same slug
(`blueworx-labs-wordpress`) into a single settings form.

### Sections (order and membership)

1. **Security & Access**
   - Custom login & protection (toggle + editable slug field)
   - Site Protection (toggle + per-area enable + role multiselect)
   - Application Passwords (toggle + "show for admins")
2. **Content**
   - Comments disabled (toggle)
   - Page excerpts (toggle)
3. **Notifications & Cleanup**
   - Reduced admin emails (toggle)
   - Profile cleanup (toggle)
4. **Performance**
   - Automatic cache refresh (toggle)
   - Manual cache refresh (toggle + link to the Cache page when on)
5. **Admin Menu**
   - Menu editor (toggle + link to the Edit Menu page when on)

### Row rendering

Each section is a collapsible `postbox` card (matching the prior role-editor card
style). Each function row shows:

- A native checkbox styled as an on/off switch (no new dependencies), with a real
  `<label>` (keyboard-accessible).
- The existing plain-English description beneath the label.
- Any nested detail controls, shown only when the toggle is on (toggled via a
  small inline admin script; off = hidden).

### Saving

- One form posting to `admin-post.php` with a nonce (same pattern as the current
  Site Protection and menu handlers).
- A single **Save Changes** button writes all `blueworx_feature_*` options plus
  the nested detail options (login slug, site-protection, application passwords)
  in one submit.
- Success admin notice on save.

### Login safety UX

- The editable slug is sanitized with `sanitize_title`, must be non-empty and not
  a reserved path (`wp-admin`, `wp-login`, `admin`); invalid input falls back to
  `admin_login` with a warning notice.
- After save, the page shows the **active login URL** prominently, so the admin
  always knows the current login door.

## Accessibility

Toggles are native checkboxes with real labels and full keyboard access. Section
cards use correct heading order. Descriptions stay in plain English.

## Testing

- **Playwright** (CI guardrail): a spec exercising toggle round-trips —
  - Disable "Comments disabled" → comments become open again; re-enable → closed.
  - Toggle `login` off → `/wp-login.php` is reachable; on → blocked and the
    custom slug serves login.
  - Edit the login slug → the new slug serves login and the page reports the new
    active URL.
- **Verification** before completion: drive the settings page in a real WP
  instance, flip a representative toggle in each section, and confirm the
  corresponding behavior changes.

## Versioning

Minor version bump (new feature) with a matching CHANGELOG entry, per the project
guardrails.

## Out of scope

- The Headless REST layer and its **Headless** tab (unchanged).
- Rebuilding the role editor (tracked separately).
- Any change to which functions exist — this is purely about exposing on/off
  control and the editable login slug.
