# BlueWorx Admin Re-skin — Refinements (v2 design)

**Date:** 2026-07-15
**Repo:** `blueworx_labs_wordpress`
**Branch:** `admin-reskin-refinements`
**Target version:** 1.12.0
**Status:** Approved (brainstorming)

## Summary

Second pass over the wp-admin re-skin, driven by a staging review of v1.11.0 and by
a **newer design export** that arrived after the original spec was written.

This spec **supersedes the sidebar and settings-page sections** of
`2026-07-14-admin-reskin-design.md`. That spec remains the reference for the top
bar, dashboard, login screen, tokens, and the feature-flag architecture, all of
which are unchanged.

Two kinds of work, one branch:

1. **Bugs** in the shipped re-skin (top-edge strip, sidebar hover colour overlap,
   broken Edit Menu drag-and-drop).
2. **Design gaps** against the v2 export (sidebar groups, icons, badges, Log Out;
   card containers on settings screens).

## The design source changed

The v1.11.0 re-skin was built from `WordPress Admin.dc.html`. The export folder
(`~/Downloads/Reimagined WordPress Backend Design/`) now also contains
**`WordPress Admin v2.dc.html`**, which is the demo Luke is asking to match.

v2 adds, in the sidebar:

- A brand block (site initial in an indigo square, site name, `wp-admin` subtitle)
- Group headings: **OVERVIEW / CONTENT / CUSTOM CONTENT / SITE**
- Count badges on items (`Posts 24`, `Plugins 2`)
- A storage meter (**explicitly cut** — see Non-goals)
- A Log Out item pinned to the bottom
- Lucide-style stroked icons in place of dashicons

### Reversed non-goal

The v1 spec stated:

> Not pixel-identical to the mockup. The mockup is a simplified, bespoke admin
> (trimmed 7-item nav). We keep WordPress's real menu and notices and apply the
> BlueWorx skin to them.

**This is reversed for the sidebar.** The target is now ~99% fidelity to the v2
sidebar. The practical consequence: the sidebar is no longer a pure-CSS re-skin —
it restructures WordPress's `$menu` in PHP. The non-goal still holds everywhere
else (notices, list tables, and third-party admin pages keep WordPress's real
markup and are only skinned).

## Goals

- Sidebar matches the v2 export ~99%: groups, icons, badges, Log Out.
- The three shipped bugs are fixed at root cause, not patched at the symptom.
- Settings screens read as cards, on our pages **and** on core/third-party pages.
- Still fully reversible via the existing feature flags.
- No new runtime dependencies. jQuery UI `sortable` is **removed**, not added to.

## Non-goals

- **No storage meter.** WordPress has real disk usage but no concept of a quota,
  so the design's `6.2 / 20 GB` would require inventing one. Cut by decision.
- No search field or "New" button in the top bar (already decided in the v1 spec;
  unchanged).
- Not restyling the block-editor canvas (`.editor-styles-wrapper`).
- No redesign of the dashboard, login screen, or top bar — v1.11.0 stands.

---

## 1. Sidebar groups

### Model

Four groups own the sidebar structure. **The "More" menu is removed entirely** —
its purpose was visual grouping, which real groups now do properly.

Default group assignment, overridable per-item in Edit Menu:

| Group | Heading | Default members |
|---|---|---|
| `overview` | OVERVIEW | `index.php` |
| `content` | CONTENT | `edit.php`, `upload.php`, `edit.php?post_type=page`, `edit-comments.php` |
| `custom` | CUSTOM CONTENT | Any registered custom post type menu (e.g. Clubhouse, Content) |
| `site` | SITE | `themes.php`, `plugins.php`, `users.php`, `tools.php`, `options-general.php`, `blueworx-labs-wordpress`, and any unrecognised third-party menu |

Rules:

- **Custom Content** is detected by matching top-level slugs of the form
  `edit.php?post_type=…` where the post type is not `post` or `page`.
- **Unknown third-party menus default to `site`.** They are never dropped.
- **An empty group renders no heading.** With the `comments` feature enabled
  ("Comments disabled"), `edit-comments.php` is absent from the menu — Content
  still has other members, so this needs no special case. But a group that ends up
  with zero visible items must not emit a stray heading.
- `edit-comments.php` sits in Content by rule. It simply does not render when the
  `comments` flag removes it.

### Rendering the headings

Headings are injected as **inert pseudo-items into the global `$menu`** on
`admin_menu` at a late priority, carrying a `bw-menu-group` class in the menu
item's class field (index 4). CSS renders them as headings and makes them
non-interactive (`pointer-events: none`), and they are removed from the tab order.

They are **not** faked with CSS `content:` strings, because that would hard-code
English into the stylesheet and break translation. Labels go through
`__( …, 'blueworx-labs-wordpress' )` like every other string.

**Validation required during implementation:** core's `_wp_menu_output()` special-
cases items whose class contains `wp-menu-separator`, and renders other items as
anchors. The heading item must render as readable text without becoming a
focusable link. If core's renderer cannot be made to emit an inert heading
cleanly, the fallback is to mark the *first item of each group* with a
`bw-group-start-{group}` class and render the heading from a `::before` on that
item, with the label supplied via an inline `style` custom property
(`--bw-group-label`) so translation still works. Pick the pseudo-item approach
first; fall back only if core fights it.

### Interaction with the feature flags

Two independent flags, unchanged in meaning:

| Flag | Owns |
|---|---|
| `admin_theme` | The **group structure itself**: default rule-based grouping, headings, icons, badges, Log Out, all CSS |
| `menu_editor` | The **user's overrides** of that structure: per-item group reassignment, order within a group, hidden items |

The split is *structure* vs *customisation of structure*. Grouping is a property
of the BlueWorx sidebar design, so it belongs to `admin_theme`; Edit Menu only
overrides what the design already establishes.

Behaviour matrix:

- **Both on** — full v2 sidebar, with the user's saved overrides applied.
- **`admin_theme` on, `menu_editor` off** — full v2 sidebar, grouped by the
  default rules, ignoring any saved overrides.
- **`admin_theme` off, `menu_editor` on** — stock WordPress look. No grouping and
  **no headings injected** (an unstyled heading in a stock menu would be a visual
  defect). Saved group assignments are inert; the flat `blueworx_admin_menu_order`
  and hidden items are still respected.
- **Both off** — stock WordPress.

There is no half-skinned state.

## 2. Icons

Inline SVGs lifted from the v2 export replace dashicons on all mapped core menus.

| Menu | Icon |
|---|---|
| Dashboard | grid |
| Posts | file-text |
| Media Library | image |
| Pages | layers |
| Users | user |
| Plugins | puzzle |
| Appearance | palette |
| Tools | wrench |
| Settings | sliders |

- Icons are injected as inline SVG (stroke `currentColor`) so they inherit the
  item's label colour in every state, matching the design.
- **Unmapped third-party menus keep their own dashicon** — there is nothing to map
  them to, and blanking them would be worse than an inconsistent glyph.
- Custom post type menus use a single generic `tag` icon, matching how the v2
  export renders its repeated `{{ cn.label }}` custom-content rows.

## 3. Badges and Log Out

**Badges.** Real counts, from core APIs, computed **once per request** and passed
to the menu builder:

- Posts → `wp_count_posts( 'post' )->publish`
- Pages → `wp_count_posts( 'page' )->publish`
- Media → `wp_count_attachments()` summed
- Custom post types → `wp_count_posts( $type )->publish`
- Plugins → count of active plugins

A count of zero renders **no badge** (the v2 export shows badges only on non-zero
items). Badges must not collide with WordPress's own update-count bubbles
(`.update-plugins`, `.awaiting-mod`); where core already renders a bubble on an
item, core's bubble wins and we do not add a second.

**Log Out.** Pinned to the sidebar bottom, above `#collapse-menu`, using
`wp_logout_url()` (which carries the nonce). Duplicates the top-bar user menu's
logout; that is intentional and matches the design.

## 4. Bug: sidebar hover colour overlap

**Confirmed root cause** (`assets/css/admin-theme.css:296–318`):

```css
#adminmenu li.menu-top:hover        { background: rgba(255, 255, 255, .06); }  /* the li  */
#adminmenu li.current a.menu-top    { background: rgba(79, 70, 229, .22);   }  /* the a   */
```

Hover paints a translucent white onto the **`li`**; the active pill paints a
translucent indigo onto the **`a`** nested inside it. Hovering the current item
composites both translucent layers over the charcoal sidebar, producing a third,
muddier colour that belongs to neither state.

**Fix:** both states paint the **same element** (the anchor), with **opaque**
colours resolved against the sidebar background rather than stacked alphas. The
`li` carries only layout (margin, radius); the `a` carries all state colour.

## 5. Bug: the top-edge strip ("the jutt")

**Symptom (confirmed by screenshot):** a full-width strip along the very top of
the viewport, spanning across both the sidebar and the top bar, sitting above
both. It is a lighter, greyer tone than `--bw-charcoal` (`#0A0C29`).

**Root cause: not yet diagnosed. Do not guess.**

Ruled out by inspection: `html.wp-toolbar { padding-top: 0 }` and
`#wpadminbar { display: none }` are both already applied at ≥783px
(`admin-theme.css:583–590`), so the obvious candidate is already handled.

**Required approach:** use `superpowers:systematic-debugging` against the live
admin. Identify the actual element producing the strip via DOM inspection before
changing any CSS.

**Explicitly forbidden:** "fixing" this with a negative margin, a covering
pseudo-element, or an offset that hides the strip without explaining it. The
element must be found and its cause removed.

## 6. Edit Menu revamp

### Why it is being rebuilt, not patched

Two separate problems: drag-and-drop is broken, and the data model must change
from three buckets (`main` / `toggle` / `hidden`) to five (four groups + hidden).

**Diagnose the existing breakage first.** `assets/js/admin-menu-order.js` uses
jQuery UI `sortable` with `handle: '.blueworx-menu-order-handle'`, and
`jquery-ui-sortable` *is* correctly enqueued as a dependency
(`includes/admin-assets.php:90–94`), so the obvious cause is already ruled out.
Find the real reason before replacing it — otherwise the rebuild risks
reproducing the same fault.

### New UI

Stacked group sections, one card per group, in sidebar order — the screen mirrors
the shape of the thing it configures:

```
┌─ OVERVIEW ──────────────────┐
│ ∷ [▦] Dashboard          ▲▼ │
└─────────────────────────────┘
┌─ CONTENT ───────────────────┐
│ ∷ [▤] Posts           24 ▲▼ │
│ ∷ [▧] Media Library      ▲▼ │
└─────────────────────────────┘
┌─ HIDDEN ────────────────────┐
│ ∷ [▢] Links              ▲▼ │
└─────────────────────────────┘
```

- Drag a row between sections to reassign its group; drag within a section to
  reorder.
- **Native HTML5 drag API.** No jQuery UI, no new dependency.
- **Up/down buttons are a first-class control, not a nicety** — they make the
  screen keyboard-accessible, which the current drag-only implementation is not.
  Moving an item past a section boundary moves it into the adjacent group.
- Locked items (`blueworx-menu-toggle`, `separator-blueworx-toggle`) are retired
  along with the More menu.

### Data model and migration

Current options:

| Option | Meaning |
|---|---|
| `blueworx_admin_menu_order` | Ordered top-level slugs |
| `blueworx_toggled_admin_menu_items` | Slugs moved into More |
| `blueworx_hidden_admin_menu_items` | Slugs hidden |
| `blueworx_admin_menu_customized` | `'1'` once the screen has been saved |

New option:

| Option | Meaning |
|---|---|
| `blueworx_admin_menu_groups` | Map of slug → group key (`overview`/`content`/`custom`/`site`) |

`blueworx_admin_menu_order` and `blueworx_hidden_admin_menu_items` are **kept
as-is** (order is now interpreted within a group; hidden is unchanged).
`blueworx_toggled_admin_menu_items` is **retired**.

**Migration (one-time, idempotent), only when `blueworx_admin_menu_customized`
is `'1'`:**

1. Every slug in `blueworx_toggled_admin_menu_items` is assigned to its
   **default rule group** and therefore **reappears in the sidebar**.
2. `blueworx_hidden_admin_menu_items` is preserved untouched — hidden stays hidden.
3. `blueworx_toggled_admin_menu_items` is deleted.
4. A version flag records that the migration ran, so it cannot run twice.

**Decision (Luke, 2026-07-15): More items reappear rather than migrate to Hidden.**
More was a *grouping* affordance, not a *hiding* one — the plugin has a separate
Hidden bucket for hiding, and anyone who wanted an item gone would have used it.
Silently hiding items on upgrade would be the more destructive reading of intent.
This **is** a visible change on upgrade and must be called out in `CHANGELOG.md`
and `readme.txt` under an "Upgrade Notice", not buried.

## 7. Containers on settings screens

Split by ownership of the markup:

**Our pages** (`Enhancements`, `Cache`, `Headless`) — real card markup in PHP,
since we own the templates. Each section becomes a `.bw-card` with a heading,
optional description, and body.

This is also the actual fix for "Enhancements is very ugly with bad spacing": the
page has **no container structure at all**, so its problem is missing hierarchy,
not wrong padding. Rhythm comes from the card system, not from tweaked margins.

**Core and third-party pages** (`General Settings`, and every plugin settings
screen) — **CSS only**. `.form-table` is styled as a card: white surface, 16px
radius, `--bw-shadow-card`, with row padding and a lavender label column. Section
`<h2>`s sit above their card.

No JavaScript wrapping of core markup. The CSS-only approach means every settings
screen benefits — including plugins we have never seen — with zero risk of
breaking markup we do not control. The trade-off, accepted: a core screen with
multiple `<h2>` + `<table>` pairs gets one card per table rather than one card per
titled section, which is very close and infinitely safer.

## Accessibility

- Group headings are inert and out of the tab order; they must not read as
  interactive to a screen reader.
- Icons are decorative (`aria-hidden="true"`) — the adjacent text label is the
  accessible name.
- Badges get an accessible label (e.g. "24 posts"), not a bare number.
- The Edit Menu's up/down buttons make group assignment fully keyboard-operable.
  Drag-and-drop is an enhancement layered on top, never the only route.
- Sidebar hover/active colours retested for WCAG AA after the opaque-colour fix.

## Testing

Extending `tests/admin-theme.spec.js` and `tests/admin-menu-defaults.spec.js`,
following the existing harness convention (skip on placeholder URL / missing
`WP_ADMIN_USER` / `WP_ADMIN_PASS`; log in on redirect):

1. Group headings render, in order, with the expected labels.
2. An empty group emits no heading.
3. Badges show real counts and are absent at zero.
4. Log Out is present at the sidebar bottom and points at a nonced logout URL.
5. **Regression — hover overlap:** the current item's computed background is
   unchanged by hover (asserts the two states no longer composite).
6. **Regression — the jutt:** the sidebar and top bar both start at viewport
   `y = 0`, with no element above them.
7. Edit Menu: dragging a row to another group persists after save; the up/down
   buttons move an item across a group boundary.
8. Migration: a site with `blueworx_toggled_admin_menu_items` set has those items
   assigned to their default groups and the option deleted, and running it twice
   changes nothing.
9. `admin_theme` off ⇒ no headings, no injected icons, stock WordPress look.

Test 6 is only meaningful once the jutt's root cause is known; write it against
the real cause, not the symptom.

## Versioning & deployment

- Bump 1.11.0 → **1.12.0** (minor — new functionality) in
  `blueworx-labs-wordpress.php` (header + `BLUEWORX_LABS_VERSION`),
  `package.json`, and `readme.txt` (`Stable tag`).
- `CHANGELOG.md` + `readme.txt` changelog updated alongside, including the
  **Upgrade Notice** about More items reappearing.
- Branch `admin-reskin-refinements` → PR into `main`. CI (lint, build, version
  bump, changelog, Playwright) must pass.
- Lint runs **once** as a final check; findings are presented to Luke, not
  auto-fixed in a loop (per `CLAUDE.md`).
- Plugin zip built with bsdtar (forward slashes, single top-level folder) and
  verified with `unzip -l` before hand-off.

## Risks & mitigations

| Risk | Mitigation |
|---|---|
| Injecting pseudo-items into `$menu` is unusual; core's renderer may fight it | Validate early; documented `::before` fallback (§1). Both routes are behind `admin_theme`. |
| Third-party plugins that also filter `menu_order` / `custom_menu_order` could conflict | We already own these filters today; behaviour is unchanged in kind. `menu_editor` flag is the escape hatch. |
| Removing More is a visible change for existing sites | One-time migration into natural groups; called out in the Upgrade Notice. |
| The jutt's cause may sit in core CSS we cannot cleanly override | Diagnose first. If the cause is genuinely un-overridable, bring options back to Luke rather than patching the symptom. |
| Icon swap could blank a third-party menu's glyph | Only mapped core slugs are swapped; everything else keeps its dashicon. |
| CSS-only `.form-table` cards touch every plugin's settings screen | Style only the table shell; no layout rewrites. `admin_theme` flag is the escape hatch. |
