# Support Access & Admin UI — Design

**Date:** 2026-07-27
**Branch:** `support-access-and-admin-ui` (spec only; each feature ships on its own branch)
**Status:** Approved

Four pieces of work, delivered in this order:

1. BlueWorx Support Access — a read-only, key-gated access path for remote troubleshooting
2. Block editor top-bar overlap fix
3. Admin sidebar hover fly-out submenus
4. Profile screen refinement

Items 2–4 are contained fixes. Item 1 is a security-critical feature and is realistically
larger than the other three combined.

---

## 1. BlueWorx Support Access

### Problem

Remote troubleshooting currently requires hand-creating an administrator account per site
and rotating its credentials afterwards. Across many client sites that is both laborious and
a standing credential-sprawl risk.

### Goal

One key per site, created by the plugin, never regenerated. It grants **read-only** access
via both a browser session (wp-admin, for visual diagnosis) and the REST API (for data),
and only while a deliberately opened 24-hour window is in effect.

### Non-goals

- Write access of any kind
- Access to personal data by default (see Data Gating)
- Replacing the existing Client Roles feature, which solves a different problem
  (long-lived client accounts, not short remote support sessions)

### Components

New file `includes/support-access.php`. Console UI added to `includes/admin-settings.php`.
Registry entry `support_access` in `includes/features.php`, section `security`.

#### 1.1 Account provisioning

A single managed user `blueworx_support` and role `blueworx_support`, provisioned **when the
key is first generated** and deleted when the key is revoked, following the existing
`blueworx_client_roles_ensure()` signature pattern in `includes/client-roles.php`.

Provisioning is deliberately *not* done on activation: a site that never uses support access
should never carry a dormant account holding administrator-adjacent capabilities. The account
exists only for as long as a key exists.

Role is built by cloning the live `administrator` role and removing:

```
edit_files, edit_plugins, edit_themes,
install_plugins, install_themes, update_plugins, update_themes, update_core,
delete_plugins, delete_themes,
export, import,
create_users, edit_users, delete_users, promote_users, remove_users
```

`list_users` is retained (viewing the user list is a legitimate diagnostic need, and the
screen itself is separately gated — see Data Gating).

The account's `user_pass` is set to an unusable value (a hash that no input can produce).
There is no password to phish, leak, or rotate. Authentication happens exclusively through
the key.

**Why capabilities are not the safety mechanism.** WordPress gates *screen rendering* on the
same capabilities it gates writes on — `manage_options` is required merely to view a Settings
screen. Any role that can see what an administrator sees therefore necessarily holds
write-capable capabilities. Capability trimming here removes the most destructive operations
(file editing, plugin installation, user manipulation), but the actual read-only guarantee is
enforced one layer down.

#### 1.2 Hard request-layer block

This is the load-bearing control.

When the current user is the support account, any request whose method is not `GET` or `HEAD`
is rejected with a 403 before it reaches its handler. Enforced at:

| Hook | Covers |
| --- | --- |
| `admin_init` (priority 0) | wp-admin form POSTs, `admin-post.php`, `admin-ajax.php` actions |
| `rest_pre_dispatch` | every REST route, core and third-party |

Rationale: third-party plugins routinely perform writes through their own AJAX and REST
endpoints without checking a meaningful capability. Trusting every plugin on a client site to
respect capabilities is not a safety model. A method-level block does not depend on plugin
authors doing the right thing.

**Known residual risk (accepted, documented):** a plugin that performs a write in response to
a `GET` request is not caught by this rule. This is a WordPress anti-pattern but it exists in
the wild. Mitigations are the trimmed capability set and the 24-hour window. This limitation
must be stated in the console UI text, not just here.

#### 1.3 Key and access window

| Option | Purpose |
| --- | --- |
| `blueworx_support_key_hash` | SHA-256 of the key. The raw key is shown once, on generation, and never stored. |
| `blueworx_support_access_until` | Unix timestamp. Access is refused unless `now < value`. |
| `blueworx_support_data_until` | Unix timestamp for the data-access opt-in. Independent of the above and never longer. |
| `blueworx_support_log` | Capped audit log (see 1.6). |

The console toggle sets `blueworx_support_access_until` to `now + 24h`. It lapses on its own;
there is no scheduled task to fail. Turning the toggle off sets it to `0` immediately.

Key comparison uses `hash_equals` against the stored hash (timing-safe). Failed attempts are
rate-limited through the existing `includes/rest/rate-limit.php` helpers.

A leaked key is inert while the window is closed — which is the standing state of every site.

#### 1.4 Entry points

**REST.** Header `X-BlueWorx-Support-Key`, resolved in a `determine_current_user` filter
alongside the existing JWT resolution in `includes/rest/bootstrap.php`. Any failure leaves
the request anonymous rather than erroring, matching the existing pattern.

**Browser.** `GET /?blueworx_support_login=<key>` validates the key and the window, then calls
`wp_set_auth_cookie( $support_user_id, false )` — session cookie only, no "remember me" — and
redirects to `wp-admin`. The cookie is additionally invalidated when the window closes, so
closing the toggle ends any live session rather than only blocking new ones.

#### 1.5 Data gating

**Approach: screen-level denial, not field-level masking.**

Field-level PII masking was considered and rejected. It cannot be made complete — arbitrary
plugins render arbitrary data through paths the plugin does not control — and a partial
masking implementation presents as safer than it is. Screen-level denial is enforceable and
testable.

With `blueworx_support_data_until` unset or lapsed, the support account is denied:

- **Admin screens:** `users.php` (beyond own profile), `edit-comments.php`, `export.php`,
  and the data screens of any detected commerce/form plugins (SureCart, WooCommerce,
  and the common form plugins), by slug
- **REST routes:** `wp/v2/users` beyond self, `wp/v2/comments`, `blueworx/v1/account/*`,
  the SureCart proxies in `includes/rest/surecart.php`

Denial is a 403, not a redirect, so it is unambiguous in a transcript.

No field-level masking is implemented anywhere, including as a backstop. An earlier draft
masked the users-list email column, but that rule can never fire usefully: with data access
off the screen is already denied, and with it on the mask would defeat the opt-in the operator
just gave. Denial is the whole mechanism.

Opting in is a separate console checkbox, valid for the same 24-hour window and logged
distinctly. The default state of every site is no data access.

**Compliance note.** Because the default is no personal-data access, the disclosure burden
(DPA / sub-processor terms) attaches only to sessions where data access was deliberately
enabled, and the audit log evidences when that happened. This is the reason the default is
what it is.

#### 1.6 Audit log

Last 100 events, capped, stored as an option. Each entry: event type, timestamp, IP.

Events: `access_opened`, `access_closed`, `access_expired`, `login`, `rest_auth`,
`data_opened`, `data_closed`, `key_generated`, `key_revoked`, `blocked_write`.

Rendered in the console, newest first.

#### 1.7 Console UI

New panel in the BlueWorx console, administrators only:

- Generate key (shown once, with an explicit "this will not be shown again" warning)
- Revoke / regenerate key
- **Allow BlueWorx support access** toggle, with a live countdown to auto-close
- **Allow access to personal data** checkbox, nested under the toggle, off by default
- Audit log
- Plain-English statement of what the account can and cannot do, including the
  GET-write residual risk from 1.2

### Testing

Playwright, against the local `.wp-test` harness (**not** the staging URL in `.env`):

- Key rejected while window closed; accepted while open
- Window lapses without intervention; live session ends when it does
- `POST` to an admin screen, a REST route, and `admin-ajax.php` all return 403 as the
  support account
- Denied data screens return 403 with data access off, render with it on
- Audit log records open, login, blocked write, close

### Security review

`/security-review` is to be run on this PR specifically, before it goes near a client site.
The key-comparison and window-expiry logic are the parts where a subtle error becomes an
incident.

---

## 2. Block editor top-bar overlap

### Root cause

`.bw-topbar` (`assets/css/admin-theme.css:141-153`) is `position: fixed`, `height: 60px`,
`z-index: 9990`, applied on every admin screen. The block editor's
`.interface-interface-skeleton` is fixed at `top: 32px` (core's admin-bar height variable)
with a much lower stacking order, so the BlueWorx bar paints over the editor's own toolbar
and its controls cannot be reached.

### Fix

At `min-width: 783px`, offset the editor skeleton to clear the bar:

```css
body.block-editor-page .interface-interface-skeleton {
	top: var(--bw-topbar-h);
}
```

The site editor uses the same skeleton class and is covered by the same rule.

Editor popovers and dropdowns sit at `z-index: 1000000`, correctly above the 9990 bar, so
no stacking changes are needed.

### Fullscreen mode

The editor's fullscreen mode deliberately sets the skeleton to `top: 0`. **It is left alone.**
Fullscreen is an explicit user action meaning "remove the chrome", and overriding it would be
the plugin second-guessing the user. In fullscreen the BlueWorx bar is hidden; exiting
restores it.

**To verify during implementation:** WordPress persists fullscreen mode per user and has
defaulted it on in some versions. The reported symptom may be occurring specifically in
fullscreen rather than normal mode. Confirm which state reproduces the overlap on the local
harness before writing the fix, and if it is fullscreen, revisit this decision rather than
shipping a fix for the wrong state.

### Testing

Playwright: open `post-new.php` on the harness, assert the editor's top toolbar is not
covered — the element at the toolbar's centre point is the toolbar, not `.bw-topbar`.

---

## 3. Admin sidebar hover fly-out submenus

### Root cause

`assets/css/admin-theme.css:843-848` sets `overflow-x: hidden; overflow-y: auto` on
`#adminmenuwrap` in the expanded state, to give the sidebar its own scroll. That scroll
container clips the absolutely-positioned fly-out submenus, so hovering a non-current parent
item shows nothing. The comment at line 840 already records that the folded state keeps
`overflow: visible` for exactly this reason.

### Fix

Plain `overflow` does not clip `position: fixed` descendants (only a transformed/filtered
ancestor does). So the fly-out escapes the clip by becoming fixed, and the sidebar keeps its
scroll.

New `assets/js/admin-menu-flyout.js`, enqueued with the admin theme:

- On `mouseenter` / `focusin` of `li.wp-has-submenu.wp-not-current-submenu`, read the item's
  bounding rect and set the submenu's `top` accordingly
- Flip upward when the submenu would extend past the viewport bottom
- Clear on `mouseleave` / `focusout`

CSS gives `body:not(.folded) #adminmenu .wp-not-current-submenu > .wp-submenu` a
`position: fixed` and `left: var(--bw-sidebar-w)`.

Unchanged: the folded state (already works) and the current item's inline accordion
(correct behaviour).

### Accessibility

`focusin` / `focusout` are handled alongside the mouse events, so keyboard navigation reaches
the fly-out. This matches the accessibility expectations in `CLAUDE.md`.

### Testing

Playwright: hover a non-current parent item, assert its submenu is visible and within the
viewport. Repeat with keyboard focus.

---

## 4. Profile screen refinement

### Scope

Refinement of the existing structure, not a rebuild. `assets/js/profile-redesign.js` already
moves native form sections into a hero + two-column card layout; the reference design differs
mainly in spacing, field arrangement, and two components.

Built:

- **Field pairing** — First/Last Name and Nickname/Display Name side by side. Rows are tagged
  in JS by their input id, and CSS lays the card body out as a two-column grid with
  full-width rows marked. Deliberately **not** `nth-child`, which breaks the moment a plugin
  adds a profile field.
- **Card subtitles** — static explanatory copy per card ("How this user appears across the
  site.")
- **Spacing pass** — `1px solid var(--bw-border)` on cards, tightened card padding, and a
  consistent label/input/description rhythm
- **"Back to Users" breadcrumb** — `user-edit.php` only, gated on `list_users`
- **Log Out Everywhere** — core already renders this in the Sessions row; it is moved into
  the Security card and styled. No new plumbing.
- **Delete Account** — a danger card on `user-edit.php` only, gated on `delete_users` and on
  the target not being the current user, linking to core's existing nonce-protected delete
  flow.

Not built (per scope decision):

- Two-factor authentication toggle — WordPress has no native 2FA. Rolling our own is a
  security-critical build deserving its own spec and testing, not a corner of a spacing pass.
  If 2FA is wanted on client sites, that is separate work, and an established plugin should be
  evaluated before building.
- Email "Verified" badge — no such concept in WordPress
- Avatar upload dropzone — WordPress uses Gravatar; a local-avatar feature is out of scope
- "Password: last changed 4 months ago" — WordPress does not record this

The reference screenshot shows `[object Object]` in the Biographical Info field. That is a
mockup artefact and is not reproduced.

### Testing

Playwright: on the harness profile screen, assert the paired fields share a row, the Security
card contains the sessions control, and saving the form still persists a changed field —
the last one guarding the existing invariant that `profile-redesign.js` **moves** native
inputs rather than recreating them.

---

## Delivery

Four branches, four pull requests, in the order above. Each carries its own version bump,
changelog entry, and Playwright test to clear CI.

| # | Feature | Version |
| --- | --- | --- |
| 1 | Support Access | 1.38.0 |
| 2 | Editor overlap | 1.39.0 |
| 3 | Menu fly-outs | 1.40.0 |
| 4 | Profile refinement | 1.41.0 |

All tests target the local `.wp-test` harness. The staging URL in `.env` is stale; testing
against it would verify deployed code rather than the changes in the branch.

Items 2 and 3 are each small and self-contained. If quick wins are wanted ahead of the larger
build, they can be reordered without affecting item 1.
