# BlueWorx Admin Re-skin — Design

**Date:** 2026-07-14
**Repo:** `blueworx_labs_wordpress`
**Branch:** `admin-reskin`
**Status:** Approved (brainstorming)

## Summary

Re-skin the WordPress admin (wp-admin) and login screen to match the "Reimagined
WordPress Backend" design (Claude Design export, BlueWorx design system). The
re-skin is **CSS-first**: it restyles WordPress's own native admin markup rather
than replacing it with a framework or custom SPA. The one place we go beyond pure
CSS is the Dashboard, which gains a small custom hero-tile widget and a reordered,
restyled set of the native dashboard widgets.

It ships inside the existing `blueworx_labs_wordpress` plugin as a **feature flag**
(default on), togglable from **BlueWorx → Enhancements**.

## Goals

- One consistent BlueWorx look across the whole of wp-admin and the login page.
- Style WordPress's existing elements wherever possible; add the minimum new
  markup (only the Dashboard hero tiles have no native equivalent).
- No new front-end frameworks; no new runtime dependencies.
- Fully reversible: toggling the feature off returns the standard WordPress look.

## Non-goals

- Not pixel-identical to the mockup. The mockup is a simplified, bespoke admin
  (trimmed 7-item nav). We keep WordPress's real menu and notices and apply the
  BlueWorx skin to them.

> **Revised 2026-07-15 (v1.12.0).** Two decisions in this spec were reversed after
> the first staging review:
> 1. **The admin bar is no longer restyled-and-kept.** It is replaced on screens
>    ≥783px by the design's own top bar (page title, View Site, user menu) rendered
>    via `in_admin_header`. Below 783px the native bar is kept, because it carries
>    WordPress's responsive menu toggle — the only way to open the admin menu on a
>    phone. Search and the "New" button from the mockup are intentionally omitted.
> 2. **The login screen is branded.** The WordPress logo is replaced by a brand
>    mark (site initial in an indigo square + site name wordmark); no logo asset
>    is required.
>
> Also revised: `#wpfooter` is hidden on all admin screens, and `--bw-shadow-card`
> was lightened to a soft two-stop lift to match the design's elevation.
- Not restyling the Gutenberg block-editor **canvas** (`.editor-styles-wrapper`)
  or the front-end admin bar.
- Not the headless front-end (that is `bluegroup_project_blueworx`).

## Design tokens (from the export)

- **Primary indigo** `#4F46E5`, **primary-dark** `#4338CA`
- **Charcoal** `#0A0C29` (sidebar, primary buttons, headings)
- **Accent lavender** `#E8E7F7`, **surface-subtle** `#F5F6FF` (page background)
- **Neutrals**: `#4C4C4C` body, `#667085` muted, `#EFEFF0` dividers
- **Semantic**: success `#01824C`, error `#FF302F`, warning `#FFC107`, info `#3686F7`
- **Radii**: 8px inputs, 10–12px buttons, 16px cards
- **Shadow (card)**: layered soft shadow (`--shadow-card` from export)
- **Fonts**: Helvetica Neue (heading, falls back to system stack), Sora
  (secondary/body), Inter (UI). Sora + Inter are **self-hosted** in the plugin.

## Architecture

### New files

```
includes/admin-theme.php          Enqueue logic + dashboard customisation, all gated on the flag
assets/css/blueworx-fonts.css     @font-face for self-hosted Sora + Inter
assets/css/admin-theme.css        wp-admin re-skin (tokens + native-selector overrides)
assets/css/login-theme.css        wp-login.php re-skin
assets/fonts/                      Sora 400/600/700 + Inter 400/500/600 (woff2, subset to used weights)
```

`includes/admin-theme.php` is `require_once`'d from `blueworx-labs-wordpress.php`
after the other includes.

### Enqueue

New enqueue paths, **all gated on `blueworx_feature_enabled( 'admin_theme' )`**.
The existing per-screen `blueworx_enqueue_admin_assets()` is left untouched.

- `admin_enqueue_scripts` (no screen filter — applies to all admin screens):
  enqueue `blueworx-admin-fonts` (the fonts stylesheet) and `blueworx-admin-theme`
  (with the fonts stylesheet as a dependency).
- `login_enqueue_scripts`: enqueue `blueworx-admin-fonts` and `blueworx-login-theme`.

Asset versioning reuses the existing `blueworx_get_admin_asset_version()` helper
(mtime-busted), so cache invalidates when a CSS file changes.

Font `url()` references are relative from `assets/css/` to `../fonts/…`, so
self-hosted fonts resolve without hardcoding a plugin URL.

### Feature registration

In `includes/features.php`:

- Add section to `blueworx_get_feature_sections()`:
  `'appearance' => __( 'Appearance', … )` (appended after `admin_menu`).
- Add feature to `blueworx_get_feature_definitions()`:
  ```
  'admin_theme' => array(
      'label'       => 'BlueWorx admin theme',
      'description' => 'Restyles the WordPress admin and login screens with the
                        BlueWorx look. Purely visual; turn off to return to the
                        standard WordPress appearance.',
      'section'     => 'appearance',
      'detail'      => null,
  ),
  ```

Defaults on (absent option = enabled), matching every other feature. No changes
to the save handler needed — it already iterates all defined feature keys. The
settings page already renders every section/feature generically, so the toggle
appears automatically.

## Token → WordPress selector map

CSS custom properties (`--bw-*`) are declared once (on `:root`), then applied to
native elements. Key mappings:

| BlueWorx element | WordPress target(s) |
|---|---|
| Dark indigo sidebar; rounded indigo active-item pill; muted-white labels; indigo icons | `#adminmenuback`, `#adminmenuwrap`, `#adminmenu`, `#adminmenu li.menu-top`, `#adminmenu .wp-has-current-submenu`, `#adminmenu .wp-submenu`, `#collapse-menu` |
| Light top bar: white bg, charcoal text/icons, bottom border (restyled, not hidden) | `#wpadminbar`, `#wpadminbar .ab-item`, `#wpadminbar .ab-icon` |
| Lavender page surface | `#wpwrap`, `#wpbody-content`, `body.wp-admin` |
| White rounded cards, 16px radius, soft shadow, lavender table head | `.wp-list-table`, `.wp-list-table thead`, `.postbox`, `#dashboard-widgets .postbox` |
| Charcoal primary / outline secondary buttons, 10–12px radius | `.button-primary`, `.button`, `.button-secondary`, `.page-title-action` |
| Rounded inputs, indigo focus ring | `.form-table input[type=text]`, `input[type=email]`, `input[type=url]`, `input[type=search]`, `select`, `textarea` |
| Indigo links | `a`, `.row-actions a` |
| Heading font + weight | `.wrap h1`, `.wrap h2`, `#wpbody h1` |
| Rounded notices with semantic left-border | `.notice`, `.notice-success`, `.notice-error`, `.notice-warning`, `.notice-info` |
| Pagination / bulk-action bar | `.tablenav`, `.tablenav-pages a`, `.tablenav .actions` |

Deliberately **not** touched: `.editor-styles-wrapper` (block-editor canvas). The
front-end admin bar is untouched because the admin stylesheet only enqueues in
wp-admin.

## Dashboard (hybrid)

The default WordPress dashboard is customised in `includes/admin-theme.php`,
gated on the same flag, via `wp_dashboard_setup`:

1. **Remove** stock widgets not in the mockup: Welcome panel
   (`remove_action('welcome_panel', …)`), WordPress Events & News
   (`dashboard_primary`), and **At a Glance** (`dashboard_right_now`) — its counts
   are replaced by the custom hero tiles, so keeping it would duplicate them.
2. **Add** one custom hero widget (`wp_add_dashboard_widget`) rendering four
   number-tiles with **real counts**:
   - Posts → `wp_count_posts('post')->publish`
   - Pages → `wp_count_posts('page')->publish`
   - Comments → `wp_count_comments()->approved`
   - Media → sum of `wp_count_attachments()` (all mime types)
   The widget is moved to the top of the normal column by reordering
   `$wp_meta_boxes['dashboard']['normal']['core']` after `wp_dashboard_setup` runs,
   so it renders first.
3. **Restyle & keep** the native `Activity` (recently published + recent
   comments), `Quick Draft`, and `Site Health Status` widgets — the CSS card
   treatment in `admin-theme.css` makes them read as the mockup cards.

This gives the mockup's hero row exactly (no native equivalent) while reusing
native widgets — with live data — for the rest. No custom data queries beyond the
four counts, which use core count APIs.

Escape hatch: with the feature off, `wp_dashboard_setup` customisations don't run
and the stock dashboard returns.

## Login screen

`login-theme.css`: lavender (`#F5F6FF`) page background, white rounded card with
soft shadow, indigo primary "Log In" button, Sora/Inter type. **The WordPress
default logo mark is kept for now** (no brand asset swap) — only colour and layout
are restyled. No PHP changes to login flow (the plugin's existing `login-security`
wiring is not modified).

## Accessibility

- Contrast held to WCAG AA: white/muted-white text on charcoal sidebar,
  charcoal/`#4C4C4C` text on lavender surfaces, white on indigo buttons.
- Visible focus rings preserved — never `outline: none` without an indigo
  replacement ring on interactive elements.
- Semantic notice colours remain distinguishable (colour + left border, not
  colour alone).

## Testing

New Playwright spec `tests/admin-theme.spec.js`, following the existing harness
convention (skips on placeholder URL / missing `WP_ADMIN_USER` / `WP_ADMIN_PASS`;
logs in on redirect):

- Asserts the `blueworx-admin-theme` stylesheet `<link>` is present in wp-admin
  when the feature is on.
- Asserts the `appearance` section and `admin_theme` toggle render on the
  settings page.
- Toggles `admin_theme` off, saves, and asserts the stylesheet `<link>` is absent
  (then restores the original state, idempotent — same pattern as
  `feature-toggles.spec.js`).

## Versioning & deployment

- Bump plugin version 1.10.1 → **1.11.0** (minor — new feature) in
  `blueworx-labs-wordpress.php` (header + `BLUEWORX_LABS_VERSION`), `package.json`,
  and `readme.txt` (`Stable tag`).
- Update `CHANGELOG.md` and `readme.txt` changelog alongside the bump.
- Branch `admin-reskin` → PR into `main`. CI (lint, build, version bump,
  changelog, Playwright test) must pass.
- WordPress-plugin zip deployment per global rules (bsdtar, forward slashes,
  single top-level folder) at session end.

## Risks & mitigations

- **Global CSS touches third-party plugin admin pages.** Mitigation: target WP's
  own stable selectors and shared classes; the feature flag is the escape hatch.
- **Admin bar restyle (dark → light) can reduce contrast if done naively.**
  Mitigation: explicit charcoal text/icons on white, tested for AA.
- **Dashboard widget removal could hide something a site relies on.** Mitigation:
  only remove Welcome/Events-News (cosmetic); keep Activity, Quick Draft, Site
  Health; all reversible via the flag.
