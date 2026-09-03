# BlueWorx: External — a read-only viewer role with invitations

**Date:** 2026-09-02
**Status:** Design, approved in chat, pending spec review
**Target version:** 1.77.0 (minor — new feature)

## Problem

Luke wants to show potential clients around the backend of a demo site. Today
the only ways to do that are to hand over an administrator password, which lets
the visitor change anything, or to sit beside them and drive. Neither scales to
"here, have a look yourself".

Support access already solves the technically hard half of this — a read-only
session that cannot write even through a badly behaved plugin — but it is built
for one BlueWorx account, authenticated by a key, in a 24-hour window opened by
the site owner. It is not something you invite a named person into.

## Goals

1. A role, **BlueWorx: External**, that sees what an administrator sees and can
   change nothing.
2. One account per invited person, so access can be traced and revoked
   individually.
3. An invitation that sends the person an email with a link to set their own
   password — no credential ever sits in an inbox in plain text.
4. Access that expires on its own, 7 days by default.
5. One read-only implementation shared with support access, not a second copy.

## Non-goals

- Front-end restrictions. External viewers see the public site as any visitor
  does; this feature is about the backend.
- Granular per-screen permissions. External is admin-minus-writes, not a
  configurable role builder. The retired Client Roles feature (removed in
  1.45.0) was that, and it was withdrawn.
- Multisite. Single-site only, as with the rest of the plugin.
- Preventing an administrator from promoting an external account. WordPress's
  own rules govern that, as they do for any user.

## Background: why the request-layer block, not capabilities

`includes/support-access.php` establishes the model this feature adopts. Its
read-only guarantee does not come from the capability map. It comes from
`blueworx_support_block_writes()`, which refuses every non-GET/HEAD request made
by the support account at `init` priority 0, with a matching `rest_pre_dispatch`
filter for REST.

The reason is stated in that file and holds here: third-party plugins routinely
write through their own AJAX and REST endpoints without checking a meaningful
capability. A rule that depends on plugin authors behaving correctly is not a
safety model. A method-level block does not depend on them.

The capability map still matters, but for a different job: it removes the
operations WordPress performs through nonce'd GET links (trash, delete, bulk
actions on `edit.php`), which the method block never sees, and it removes
onward-access capabilities such as `install_plugins` and `unfiltered_html`.

**Known gap, inherited and documented:** a plugin that writes in response to an
ordinary GET request is not caught. This is already disclosed in the support
access console and must be disclosed on the External panel too.

## Architecture

### The shared guard

Extract the read-only enforcement from `includes/support-access.php` into a new
`includes/readonly-access.php`. The extracted code is behaviour-preserving; the
only change is what decides whether it applies.

Today each enforcement function opens with
`if ( ! blueworx_support_is_support_user() ) { return; }`. After extraction,
each opens with `if ( ! blueworx_readonly_current_user() ) { return; }`, where:

```php
function blueworx_readonly_roles() {
    return (array) apply_filters(
        'blueworx_readonly_roles',
        array(
            blueworx_support_role_slug(),
            blueworx_external_role_slug(),
        )
    );
}
```

`blueworx_readonly_current_user()` returns the current `WP_User` when it holds
one of those roles, and `null` otherwise.

Moves into the shared guard:

| Currently | Becomes |
|---|---|
| `blueworx_support_removed_caps()` | `blueworx_readonly_removed_caps()` |
| `blueworx_support_denied_meta_caps()` | `blueworx_readonly_denied_meta_caps()` |
| `blueworx_support_deny_meta_caps()` | `blueworx_readonly_deny_meta_caps()` |
| `blueworx_support_build_caps()` | `blueworx_readonly_build_caps()` |
| `blueworx_support_block_writes()` | `blueworx_readonly_block_writes()` |
| `blueworx_support_block_rest_writes()` | `blueworx_readonly_block_rest_writes()` |
| `blueworx_support_gate_write_actions()` | `blueworx_readonly_gate_write_actions()` |
| `blueworx_support_action_screens()` | `blueworx_readonly_action_screens()` |
| `blueworx_support_readonly_actions()` | `blueworx_readonly_allowed_actions()` |
| `blueworx_support_denied_screens()` and the data-screen family | `blueworx_readonly_denied_screens()` and equivalents |
| `blueworx_support_screen_is_denied()` | `blueworx_readonly_screen_is_denied()` |
| `blueworx_support_gate_data_screens()` | `blueworx_readonly_gate_data_screens()` |
| `blueworx_support_gate_data_routes()` | `blueworx_readonly_gate_data_routes()` |
| `blueworx_support_is_heartbeat_request()`, `blueworx_support_disable_heartbeat()` | `blueworx_readonly_*` equivalents |

Stays in `support-access.php` — support-specific, not shared:

- The key: generation, hashing, verification, revocation, throttling, lockout.
- The 24-hour window and its toggle.
- `blueworx_support_handle_login()` and `blueworx_support_rest_auth()`.
- The support account provisioning and its unusable password.
- The support event log and console panel.
- The Site Protection exemption for the support account.

**Data-access differences between the two roles.** Support access has a "data
access" switch that, when off, hides the personal-data screens. External has no
such switch: its data screens are *always* denied. The shared guard therefore
asks a per-role question rather than reading the support option directly:

```php
function blueworx_readonly_data_allowed( $user ) { … }
```

which returns `blueworx_support_data_open()` for the support role and always
`false` for the external role.

**Existing filter names.** `blueworx_support_denied_screens`,
`blueworx_support_denied_admin_pages`, `blueworx_support_denied_post_types` and
`blueworx_support_denied_routes` are public filters a site may already use. They
are kept and still applied, with new `blueworx_readonly_*` filters applied
after them, so no site's existing customisation breaks.

`blueworx_support_is_support_user()` is kept as a thin wrapper over the role
check. `includes/view-as-role.php` calls it, and its meaning is unchanged.

### The External role

- Slug: `blueworx_external`
- Registered name: `BlueWorx: External`
- Capabilities: `blueworx_readonly_build_caps()` — the live administrator role
  minus the removed capabilities, plus `read`.

Registered when the feature is switched on, and rebuilt whenever it is switched
on again, because the administrator role it clones can change (a commerce plugin
adds capabilities to it). Removed on plugin deactivation and on uninstall, with
the same orphan protection the existing role migrations use: a role that still
has a user assigned is left in place and recorded, rather than deleted out from
under that user.

The role's display name follows the convention already in
`includes/display-names.php` (`Site: Editor`, `Commerce: Manager`), so it is
registered as `BlueWorx: External` directly and needs no relabelling entry.

### Invitations

**Data model.** One WordPress user per invitation, plus user meta:

| Meta key | Holds |
|---|---|
| `_blueworx_external_invited_by` | User ID of the administrator who invited them |
| `_blueworx_external_invited_at` | Unix timestamp |
| `_blueworx_external_expires_at` | Unix timestamp; access is refused at or after this |
| `_blueworx_external_note` | Free-text note about who this person is |
| `_blueworx_external_last_seen` | Unix timestamp of last successful sign-in |

No custom table. The account *is* the record, so deleting the user removes the
invitation, and nothing can be left orphaned in a table the Users screen does
not know about.

**Creating an invitation.** From the External panel: name, email address,
optional note, and duration (3 / 7 / 14 days, default 7). On submit:

1. Reject unless `current_user_can( 'promote_users' )` and the nonce verifies.
2. Reject an email address that already belongs to a user, with a message
   naming the conflict rather than silently doing nothing.
3. `wp_insert_user()` with a random 64-character password, the external role,
   and the given display name. The username is derived from the email local
   part and uniquified.
4. Write the meta above.
5. Send the invitation email (below).
6. Log the event.

**The email.** Sent with `wp_mail()`. It contains:

- Who invited them, and to which site.
- That the access is view-only, and when it expires.
- A password-reset link built with `get_password_reset_key()` and
  `network_site_url( "wp-login.php?action=rp&key=…&login=…", 'login' )`.

The reset link needs no special handling for the custom login URL:
`blueworx_replace_generated_login_url()` already filters `site_url` and
`network_site_url`, preserves the query string, and rewrites the path to the
custom slug. This must be covered by a test, because it is exactly the sort of
thing that breaks silently.

Core's own new-user notification is not used. `includes/email-notifications.php`
already reroutes `wp_send_new_user_notifications`, and the wording there is
WordPress's, not ours. The external invitation is its own message.

**When sending fails.** `wp_mail()` returns `false`, or `wp_mail_failed` fires.
The account is still created — it is valid, and the administrator can resend —
but the panel shows an error naming the failure and offers **Resend invitation**
on that row. This is deliberate: a demo site with broken mail delivery must not
look like it worked.

### Expiry, revoking, and sessions

**Enforcement** mirrors `blueworx_support_enforce_window()`: on `init` at
priority 2, if the current user is external and `_blueworx_external_expires_at`
is in the past, destroy the session and sign them out. An expired account also
fails authentication, via an `authenticate` filter, so it cannot sign back in.

**Panel actions**, each nonce-protected and `promote_users`-gated:

- **Extend** — adds the chosen duration from now.
- **Revoke** — deletes the user with `wp_delete_user()`, reassigning nothing
  (an external account cannot have authored content, because it cannot write).
- **Resend invitation** — issues a fresh reset key and sends the email again.

**Site Protection.** If front-end or back-end protection is on with a role
allowlist, external users must be in it or they cannot reach the site at all.
When the feature is first switched on, `blueworx_external` is added to any
non-empty protection role list. It stays visible and removable in the Site
Protection picker afterwards, so this is a sensible default rather than a hidden
override. External accounts are *not* given the blanket exemption the support
account has.

### The console screen

Support access has a page of its own (`includes/admin-pages.php`). External gets
the same treatment: an **External access** page under BlueWorx, rendering
`blueworx_external_render_panel()` built from the design system helpers in
`includes/admin-design.php` (`blueworx_ds_card`, `blueworx_ds_input`,
`blueworx_ds_select`, `blueworx_ds_button`, `blueworx_ds_notice`,
`blueworx_ds_empty_state`, `blueworx_ds_badge`).

Contents:

1. Feature state, and what External can and cannot do — including the GET-write
   gap disclosure.
2. The invite form.
3. A table of current invitations: name, email, note, invited by, last seen, and
   expiry as a badge (active / expiring soon / expired), with the three row
   actions.
4. An empty state when nobody has been invited.

### Feature registry

New entry in `blueworx_get_feature_definitions()`:

```php
'external_access' => array(
    'label'       => __( 'External viewer access', … ),
    'description' => __( 'Lets you invite someone to look around the backend without being able to change anything. Each person gets their own sign-in, set by them, and access ends on a date you choose.', … ),
    'section'     => 'security',
    'detail'      => 'external_access',
    'default'     => '0',
),
```

Default `'0'`, per the registry's stated rule: anything that hands out a
capability must be an explicit decision by a site owner.

### Migration

One new migration in `includes/upgrade.php`, bumping
`blueworx_get_labs_db_version()`:

- Do nothing on a fresh install.
- On an existing install, write the feature option as `'0'` explicitly, so the
  update never switches it on underneath anyone.

Role registration itself happens when the feature is enabled, not in the
migration, so a site that never enables it never carries the role.

Uninstall (`uninstall.php`) removes the role, the feature option, and the
per-user meta keys. Invited accounts themselves are left alone — deleting user
accounts during an uninstall is not a plugin's decision to make.

## Files

| File | Change |
|---|---|
| `includes/readonly-access.php` | New. The shared guard, extracted from support access. |
| `includes/support-access.php` | Reduced to key, window, login, account, log, panel. Delegates enforcement. |
| `includes/external-access.php` | New. Role, invitations, expiry, email, panel. |
| `includes/features.php` | New `external_access` entry. |
| `includes/admin-pages.php` | New External access page. |
| `includes/upgrade.php` | New migration; version bump. |
| `uninstall.php` | Remove role, option, meta. |
| `blueworx-labs-wordpress.php` | Require the two new files; deactivation hook for role removal. |
| `assets/js`, `assets/css` | Only if the panel needs behaviour the design system does not already provide. |

## Testing

**PHP unit tests** (`tests/php/`), following `view-as-access-test.php`:

- `readonly-access-test.php` — the guard recognises both roles and no others;
  removed capabilities are absent from the built map; denied meta capabilities
  resolve to `do_not_allow` for a read-only user and are untouched for an
  administrator; data screens are always denied for external and follow the
  window for support.
- `external-invite-test.php` — an invitation creates the account with the right
  role and meta; a duplicate email is refused; expiry maths; the reset link
  passes through the custom-login filter with its query string intact.

**Playwright tests** (`tests/`):

- `external-access.spec.js` — the panel renders; the invite form validates; an
  invited account appears in the table with the right expiry badge; revoke
  removes it.
- `external-readonly.spec.js` — signed in as an external account: the dashboard
  renders, a POST to `admin-post.php` is refused with 403, a REST write is
  refused, the Users screen is refused, an order screen is refused, and the
  trash link is absent from the posts list.
- `support-access.spec.js` — **unchanged**. It is the regression proof that
  moving the guard changed no support behaviour. If it needs editing, the
  extraction was not behaviour-preserving, and that is the bug.

Tests run against the local `.wp-test` harness on port 8882, not the staging URL
in `.env`.

## Security review points

1. The external role is a clone of the *live* administrator role, so it inherits
   whatever a commerce plugin has added. The removed-capability list is a
   denylist over an unknown set, which is why the request-layer block exists.
   Both layers are required; neither is sufficient alone.
2. `create_users`, `edit_users`, `promote_users`, `delete_users` and
   `remove_users` are removed, so an external account cannot invite another or
   escalate itself.
3. The invitation email contains a single-use, expiring password-reset key and
   no credential.
4. Password reset keys are core's, with core's expiry.
5. An expired account is refused at authentication *and* has any live session
   destroyed. The first alone would leave an open session running.
6. `unfiltered_html` is removed, so an external viewer cannot store script that
   later executes in a real administrator's browser.
7. External accounts have no Site Protection exemption.

## Open questions

None. The account model, credential delivery, expiry default and data visibility
were settled before this spec was written.
