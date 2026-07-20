# Client Roles — Design Spec

**Date:** 2026-07-20
**Feature:** Client Roles for the BlueWorx Labs WordPress plugin (`blueworx-labs-wordpress`)
**Target version:** 1.16.0 (minor)

## 1. Goal

Add three custom roles to assign to client accounts, giving **area-level show/hide of
the backend** built on top of WordPress's existing roles/capabilities. Low-impact and
general. **No** granular per-capability editor UI (that was the role editor removed in
1.8.0 — not being rebuilt).

The three roles:

1. **Admin — Business Owner** — the client owner. Full run of content, store, SEO,
   settings and their team, but **not** the technical/destructive areas (no installing/
   editing plugins or themes, no file editors).
2. **External Dev** — 3rd-party developer. Technical build access (plugins, themes/
   appearance, tools, settings), but **no** user-account management (own account only) and
   **no** file editors.
3. **Content Editor** — editorial, plus limited user administration. Create/edit posts,
   pages, media and comments; **edit** other users' accounts (not delete, unless a BlueWorx
   setting allows it); everything else hidden.

## 2. Decisions from brainstorming (these override the original brief's starting points)

| # | Decision | Note |
|---|----------|------|
| D1 | **Fresh slugs** `blueworx_client_owner`, `blueworx_client_dev`, `blueworx_client_editor` | Avoids colliding with the 1.15.0 orphan skip-list, the removal migration, and the Playwright test that asserts the *old* slugs are absent. |
| D2 | Feature toggle **defaults ON** | Consistent with every other feature. Roles created for existing sites on upgrade. |
| D3 | External Dev: install plugins/themes, **no file editors**, **no Users** (own account only) | Matches the brief. |
| D4 | **Roles persist and auto-restore.** Deactivate touches nothing; uninstall removes role *definitions* only and never touches users' `wp_capabilities` meta, so reinstalling re-registers the roles and assigned users snap back. No auto-delete on toggle-off; a manual "Delete roles" action may come later. | Satisfies "remembered" without leaving an orphaned definition. |
| D5 | **Gating is group-based** on the existing sidebar groups (Overview / Custom Content / Content / Site), with a small item-exception list; capabilities do the fine work inside a visible group. | Reuses `blueworx_get_admin_menu_group_for_slug()`. |
| D6 | **Custom Content and Content are visible to all three roles.** | SureCart's own capability checks still bound who can actually open it. |
| D7 | **BlueWorx console is administrators-only** — hidden from all three roles (incl. Business Owner), even though it sits in a visible group. | Prevents a client switching off Site Protection / reconfiguring roles. |
| D8 | **Overview group = Business Owner only**, with **Dashboard** as an all-roles exception. Padel 365 (and any other custom Overview item) shows for Business Owner only. | Per Luke. |
| D9 | **Content Editor gains user administration:** `list_users` + `edit_users` (edit any user and their own), **without** delete. A new BlueWorx setting **"Allow Content Editors to delete users"** adds `delete_users` when enabled. | Role caps are dynamic on that setting. |
| D10 | **Users:** Business Owner = full; Content Editor = edit (delete gated by D9 setting); External Dev = none (own account only). | Supersedes the earlier "Users → all 3" note. |

## 3. Role capabilities

Built by **cloning the live core role at registration time** and adjusting, so they track
whatever the site's `administrator` / `editor` roles actually hold.

### 3.1 Business Owner — clone `administrator`, remove
`activate_plugins`, `install_plugins`, `update_plugins`, `delete_plugins`, `edit_plugins`,
`install_themes`, `update_themes`, `delete_themes`, `edit_themes`, `edit_files`,
`update_core`, `import`, `export`.

Keeps: `manage_options`, `switch_themes`, `edit_theme_options`, **all** user-management caps,
all content caps, `moderate_comments`, `unfiltered_html`.
Net: Plugins gone, file/code editors gone, Updates hidden, Tools trimmed; Appearance /
Settings / Users (full) / SureCart / SureRank / Padel 365 visible.

### 3.2 External Dev — clone `administrator`, remove
`list_users`, `create_users`, `edit_users`, `delete_users`, `promote_users`, `remove_users`,
`edit_files`, `edit_plugins`, `edit_themes`.

Keeps: plugin install/activate/update/delete, theme install/switch/update/delete,
`edit_theme_options`, `manage_options`, `import`/`export`, `update_core`, all content caps.
Net: Users gone (own Profile only), file/code editors gone; Plugins / Appearance / Tools /
Settings visible.

### 3.3 Content Editor — clone `editor`, add
- Always: `list_users`, `edit_users`.
- Only when the BlueWorx setting **"Allow Content Editors to delete users"** is on:
  `delete_users`, `remove_users`.

**Withheld deliberately:** `create_users`, `promote_users` (so a Content Editor cannot mint
accounts or change roles — including their own). The core Editor caps otherwise stand, so
Appearance / Plugins / Tools / Settings stay hidden by capability.

### 3.4 Admin-protection guard (confirmed)
`edit_users` lets a Content Editor edit **any** account, including an administrator's (reset
an admin's password → takeover). WordPress has no native "edit users except admins" cap, so
a **`map_meta_cap` filter** denies `edit_user` / `delete_user` / `promote_user` when the
*target* user holds the `administrator` role and the *actor* is a Content Editor. Editors can
still manage all non-admin accounts and their own. This ships as part of the feature.

## 4. Group-visibility model (the gating mechanism)

Per role, a set of **visible sidebar groups**, plus an item-exception list. On `admin_menu`
(priority 999), for a user who holds a client role **and is not an administrator**:

1. Remove `blueworx-labs-wordpress` and its subpages (`blueworx-edit-menu`, `blueworx-cache`)
   — administrators-only console (D7).
2. Keep item-exception slugs regardless of group: `index.php` (Dashboard), `users.php`
   (Users) — actual visibility still bounded by capability.
3. For every other top-level slug, `remove_menu_page()` if its group is not in the role's
   visible set.

Administrators are never gated.

| Group / item | Business Owner | External Dev | Content Editor | Admin |
|---|:--:|:--:|:--:|:--:|
| **Overview** group (Padel 365, …) | ✓ | ✗ | ✗ | ✓ |
| ↳ Dashboard *(exception)* | ✓ | ✓ | ✓ | ✓ |
| ↳ BlueWorx console *(admins only)* | ✗ | ✗ | ✗ | ✓ |
| **Custom Content** (SureCart, SureRank) | ✓ | ✓ | ✓ | ✓ |
| **Content** (Posts, Media, Pages) | ✓ | ✓ | ✓ | ✓ |
| **Site** (Settings, Appearance, Tools, Plugins) | ✓ | ✓ | ✗ | ✓ |
| ↳ Users *(exception, cap-bounded)* | ✓ full | ✗ (no cap) | ✓ edit | ✓ |

Visible-group sets:
- Business Owner: `overview, custom, content, site`
- External Dev: `custom, content, site`
- Content Editor: `custom, content`

Within a visible group, items still gate by capability (Business Owner: no Plugins/file
editors, Tools trimmed; External Dev: no Users). The sets live in one function
(`blueworx_get_client_role_visible_groups()`), so "split the sections" is a one-line change.

## 5. Registration, persistence, uninstall (D4)

- **Single source of truth:** `blueworx_get_client_role_definitions()` → slug → {label,
  clone-base, add[], remove[]}. Content Editor's add[] includes the delete caps only when the
  D9 setting is on, so the definition is computed against current settings.
- **Ensure (idempotent):** `blueworx_client_roles_ensure()` (re)builds each role's caps to
  match the current definition. Runs on: `register_activation_hook`; a **version-gated
  migration** (db version 5 → 6) for existing sites; the feature toggle flipping ON; and the
  D9 setting changing. Not on every request.
- **Deactivate:** no role code in the deactivation hook — definitions and assignments intact.
- **Uninstall (`uninstall.php`, new):** `remove_role()` for the three slugs (definition only);
  **never** touch `wp_capabilities` meta. Reinstalling re-registers and users snap back. No
  orphaned definition left in the DB.
- **Toggle OFF:** relaxes the gating pass and the console restriction; roles stay registered
  so no assigned user is stranded. A future manual "Delete roles" action is the only purge.

## 6. Settings

New feature key `client_roles` in `blueworx_get_feature_definitions()`, section **Security &
Access**. Its detail renderer shows:
- The three role names + a one-line note that roles persist and restore on reinstall.
- A checkbox **"Allow Content Editors to delete users"** (option
  `blueworx_client_editor_can_delete_users`, default `0`). Saving it (via the existing
  `blueworx_save_feature_settings()` handler) calls `blueworx_client_roles_ensure()` so the
  Content Editor caps re-sync immediately.

Flipping the `client_roles` toggle ON also calls `blueworx_client_roles_ensure()`.

## 7. Site Protection integration

Free. `blueworx_get_site_protection_role_choices()` iterates `wp_roles()` live, so the three
roles appear in the frontend/backend selects automatically and inherit Site Protection's
role-based gating with no extra code.

## 8. Testing (Playwright)

New `tests/client-roles.spec.js`, guarded by the same skip as the existing suites:
1. **Toggle present + persists** — the `client_roles` toggle renders and survives a save
   round-trip (restores original state).
2. **Roles appear in Site Protection** — all three slugs appear as `<option>`s in **both** the
   frontend and backend protection selects.

A "Content Editor sees only allowed menus" check needs a seeded non-admin account, so it is
**not** a hard CI dependency; manual verification steps are documented in the PR.

## 9. Versioning & docs

- Plugin header `Version:` + `BLUEWORX_LABS_VERSION` → **1.16.0**.
- `package.json` `version` → **1.16.0**; `readme.txt` `Stable tag:` → **1.16.0**.
- `CHANGELOG.md`: `## [1.16.0]` under **Added**.
- Lint run once at the end; findings reported, not auto-fixed.

## 10. Files touched

- `includes/client-roles.php` — **new**: definitions, ensure/sync, group-visibility gating,
  console restriction, feature hooks.
- `includes/features.php` — add `client_roles` to the registry.
- `includes/admin-settings.php` — detail renderer (+ delete-users checkbox); non-admin guard
  on the console render + save handlers; ensure on toggle/setting change.
- `includes/upgrade.php` — db version 5 → 6; ensure-roles migration.
- `blueworx-labs-wordpress.php` — require the file; activation hook; version bump.
- `uninstall.php` — **new**: remove role definitions, preserve usermeta.
- `tests/client-roles.spec.js` — **new**.
- `CHANGELOG.md`, `readme.txt`, `package.json`.

## 11. Non-goals

- No per-capability editor UI (that was 1.8.0's removed feature).
- Third-party menus (SureCart, SureRank, Padel 365) handled **by group**, not a hard-coded
  slug list — new plugins inherit their group's rule automatically.

## 12. Caveats / open security decision

- **Policy boundary, not a hard wall:** External Dev keeps plugin-install caps, so any hiding
  is policy/UX — a determined External Dev could install a plugin to reach anything.
- **Content Editor `edit_users` escalation — mitigated:** the admin-protection guard in §3.4
  ships with the feature, so Content Editors cannot edit/delete/promote administrator accounts.
- **Console restriction keys off the `administrator` role;** a site using a custom
  admin-equivalent super-role would not see the console while the feature is on. Acceptable for
  this plugin's audience.
