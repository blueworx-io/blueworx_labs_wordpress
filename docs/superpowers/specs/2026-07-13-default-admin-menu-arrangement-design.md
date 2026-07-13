# Default admin-menu arrangement — design

**Date:** 2026-07-13
**Feature area:** Menu editor (`includes/admin-menu-order.php`, `includes/admin-settings.php`, `includes/upgrade.php`)
**Version target:** 1.9.0 → 1.10.0 (minor)

## Goal

Give the admin menu a sensible default arrangement out of the box, while keeping the
existing Edit Menu page fully in control once an admin customises it.

Defaults requested:

- **BlueWorx** always sits at the top of the menu, directly below the WordPress **Dashboard**.
- **More** is always last.
- Only these items are shown by default: **BlueWorx, Posts, Media, Pages, Users**
  (plus **Dashboard**, which is the top anchor).
- Every other *default WordPress* menu item is moved into **More** by default.
- Items added by **plugins** are never hidden; they sit **below** the default WordPress
  items in the visible menu.
- All menu items are ordered by **title length, shortest first, then A–Z** as a tiebreak
  (BlueWorx stays pinned at the top, More stays pinned at the bottom).

## Decisions (locked)

1. **Defaults, not enforced rules.** The arrangement applies until an admin saves the Edit
   Menu page. After that, their saved order/visibility wins (today's behaviour).
2. **Sort:** ascending by title length, alphabetical (A–Z) tiebreak for equal lengths.
3. **Plugin placement:** grouped *below* the keep set — keep items (sorted by length) first,
   then plugin items (sorted by length). Not globally interleaved.
4. **Administrators only.** The computed defaults apply only to users with `manage_options`; other roles keep the standard WordPress menu. Saved (customised) arrangements continue to apply to all roles, unchanged.

## Architecture

Three options already store menu state:

- `blueworx_admin_menu_order` — flat array of slugs (order)
- `blueworx_toggled_admin_menu_items` — slugs moved into More
- `blueworx_hidden_admin_menu_items` — slugs hidden

Today these default to empty/static, so out of the box nothing moves to More.

We add a **computed-default** layer gated by a new flag option
`blueworx_admin_menu_customized`:

- Flag not `'1'` → the three getters return **computed defaults** derived from the live
  `$menu` global.
- Flag `'1'` → the getters return the saved options exactly as today.

The flag is set to `'1'` inside the existing save handler
`blueworx_save_edit_menu_page()`. Nothing else about saving changes.

Because every downstream consumer already reads through these three getters, injecting the
default branch there means the `menu_order` filter, the More/visibility builder, and the
Edit Menu render columns all reflect the defaults with no further plumbing.

### New helpers (in `includes/admin-menu-order.php`)

- `blueworx_admin_menu_is_customized(): bool`
  Returns `'1' === get_option( 'blueworx_admin_menu_customized', '0' )`.

- `blueworx_get_core_admin_menu_slugs(): array`
  Canonical list of standard WordPress top-level slugs, used to tell core items from
  plugin items:
  `index.php, edit.php, upload.php, edit.php?post_type=page, edit-comments.php,
  themes.php, plugins.php, users.php, tools.php, options-general.php, profile.php,
  link-manager.php`.

- `blueworx_get_default_visible_admin_menu_slugs(): array`
  The keep-visible set: `index.php, blueworx-labs-wordpress, edit.php, upload.php,
  edit.php?post_type=page, users.php`.

- `blueworx_compute_default_admin_menu_arrangement(): array`
  Memoised (request-scoped static). Reads the live `$menu` global and returns
  `array( 'order' => [...], 'toggled' => [...], 'hidden' => [] )`.
  If `$menu` is empty/unavailable, returns a fallback built from
  `blueworx_get_default_admin_menu_order()` so behaviour degrades to today's static order.

### Computation algorithm

From the live `$menu`, collect present top-level entries as `slug => label`, skipping
separators and the locked toggle rows (`blueworx-menu-toggle`, `separator-blueworx-toggle`).

Let:

- `keep = blueworx_get_default_visible_admin_menu_slugs()`
- `core = blueworx_get_core_admin_menu_slugs()`

Partition present slugs:

- `pinned_top` = `index.php` (if present), then `blueworx-labs-wordpress` (if present),
  in that fixed order — never sorted.
- `keep_rest` = present slugs in `keep`, excluding the two pinned ones.
- `plugins` = present slugs **not** in `core` and **not** in `keep` and not pinned/locked.
- `toggled` = present slugs in `core`, **not** in `keep`.

Sort helper `sort_by_length_then_az($slugs, $labels)`: ascending by
`mb_strlen(label)`, tiebreak by case-insensitive `strcmp` on the label.

Result:

- `order = pinned_top + sort(keep_rest) + sort(plugins) + sort(toggled)`
  (toggled rows are hidden from the main menu anyway; sorting them keeps the array tidy.)
- `toggled = sort(toggled)` (drives the order of the More submenu)
- `hidden = []`

### Getter changes

Each getter grows a default branch that runs only when
`! blueworx_admin_menu_is_customized()`:

- `blueworx_get_saved_admin_menu_order()` → return `arrangement['order']`.
- `blueworx_get_toggled_admin_menu_items()` → return `arrangement['toggled']`.
- `blueworx_get_hidden_admin_menu_items()` → return `arrangement['hidden']` (empty).

When customised, each keeps its current implementation unchanged.

The existing `blueworx_admin_menu_order()` BlueWorx-pin and More-append logic is retained
as-is; it is a harmless no-op safety net once the computed order already places BlueWorx
second and appends More last.

### Save handler

`blueworx_save_edit_menu_page()` adds one line:
`update_option( 'blueworx_admin_menu_customized', '1' );`
so that the first save freezes the admin's arrangement and stops default computation.

## Migration

Add migration **v3** to the existing framework in `includes/upgrade.php`:

- Bump `blueworx_get_labs_db_version()` from `2` to `3`.
- New function `blueworx_migrate_mark_admin_menu_customized()`:
  if any of `blueworx_admin_menu_order`, `blueworx_toggled_admin_menu_items`, or
  `blueworx_hidden_admin_menu_items` is a non-empty array, set
  `blueworx_admin_menu_customized = '1'`.
- Wire it into `blueworx_run_pending_labs_migrations()` under `if ( $stored_version < 3 )`.

Effect: sites that already arranged their menu keep it. Fresh installs, and installs that
enabled the editor but never arranged anything, get the new defaults.

**Accepted trade-off:** a site that enabled the menu editor but never opened Edit Menu will
see its menu reshuffle to the new defaults on upgrade. Only sites with a real saved
arrangement are protected. This is consistent with treating the arrangement as a default.

## Edge cases

- **Comments in More:** only appears in More if `edit-comments.php` is still present; the
  separate "Comments disabled" feature may have removed it. Handled by computing from the
  live `$menu`.
- **Custom post types from plugins** (`edit.php?post_type=xyz`) are non-core → treated as
  plugin items, shown below the keep set.
- **`$menu` not yet built:** fallback to the static default order avoids partial results.
- **Equal-length titles** (Media/Pages/Posts/Users are all 5 chars): alphabetical tiebreak
  gives Media, Pages, Posts, Users.

## Out of scope

- No changes to `assets/js/admin-menu-order.js` — it serialises whatever columns the server
  renders; the render already reflects defaults.
- No changes to the three-column Edit Menu UI layout.
- No new user-facing setting to toggle the defaults on/off.

## Testing

Follow the project's Playwright convention for a real behaviour change. Cover:

1. Fresh state (flag unset): Main menu shows Dashboard, BlueWorx, Media, Pages, Posts,
   Users, then plugin items by length; More contains the remaining core items; length +
   A–Z order holds.
2. After saving Edit Menu: flag is `'1'`; a deliberately non-default arrangement persists
   across reload (defaults do not reassert).
3. Migration: with a pre-existing non-empty `blueworx_admin_menu_order`, upgrade sets the
   customised flag and the saved order is preserved.

## Versioning / deployment

- Bump main plugin file + any version constant/`package.json` to **1.10.0**.
- Add a changelog entry describing the new default arrangement.
- Build the deployment zip per the WordPress plugin deployment rules (bsdtar, forward
  slashes, single top-level `blueworx-labs-wordpress/` folder, zip placed one level up from
  the repo).
