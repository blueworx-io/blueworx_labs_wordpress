# ASE parity matrix

Which Admin and Site Enhancements modules BlueWorx Labs rebuilds, which it
drops, and which were already covered. Basis: the live read-only support session
on worldsquashofficiating.com, 30 July 2026 (issue #65).

Status column:

- **Built** — a BlueWorx Labs feature now does this.
- **Already covered** — Labs already did it before this milestone.
- **Dropped** — deliberately not rebuilt; the reason is given.

## Modules enabled on the live site

| ASE module | Live setting | Status | BlueWorx Labs feature | Notes |
|---|---|---|---|---|
| `hide_modify_elements` | WP logo, Customize, Updates, Comments, New Content, Howdy, Help | Built | `admin_bar` (Toolbar cleanup) | Comments node stays with the `comments` feature — one node, one owner. |
| `hide_admin_bar` | on, no roles named | Built | `admin_bar`, front-end section | Read as "hide for everyone except administrators". See decision 1. |
| `disable_dashboard_widgets` | Welcome, Activity, Elementor Overview, Quick Draft | Built | `dashboard_widgets` | True removal, not `default_hidden_meta_boxes`. Activity is not a shipped default — see decision 2. |
| `disable_xmlrpc` | on | Built | `xmlrpc` | Also drops `X-Pingback`, `rsd_link` and the pingback methods. |
| `obfuscate_author_slugs` | on | Built | `author_slugs` | Default off. See decision 3. |
| `manage_robots_txt` | custom, 2 sitemaps, `Disallow: /author/` | Built | `robots_txt` | Default off. Live content is carried over at cutover, byte for byte. |
| `enable_media_replacement` | on | Built | `media_tools` | Same file type only, so the address keeps working. |
| `enable_svg_upload` | administrator only | Built | `media_tools` | Sanitised on upload, role-gated, no roles by default. |
| `image_upload_control` | 1920 x 1920 | Built | `media_tools` | Applied before the thumbnails are generated. |
| `enable_duplication` | on | Built | `content_tools` | Copies content, terms and all post meta as a draft. |
| `enable_external_permalinks` | on | Built | `content_tools` | Default off. See decision 4. |
| `enable_revisions_control` | max 20 | Built | `revisions` | `WP_POST_REVISIONS` in wp-config still wins. |
| `multiple_user_roles` | on | Already covered | `user_roles` | Shipped before this milestone; verify at cutover, do not rebuild. |
| `view_admin_as_role` | on | Built | `view_as_role` | Default off, anyone who can edit, own level downwards, can only narrow. |
| `disable_comments` | post, page, attachment, sfwd-essays | Already covered | `comments` | Labs applies to every post type, so it is a superset. Verify on staging. |
| `admin_menu_width` | 180px | Already covered | `admin_theme` | The BlueWorx admin theme sets its own sidebar width. |

## Confirmed off on the live site — not ported

`change_login_url` (Labs owns `/admin_login/`), `limit_login_attempts`,
`maintenance_mode`, `redirect_after_login`, `redirect_after_logout`,
`disable_gutenberg`, heartbeat control, and custom head/body/footer code. Their
sub-field values are still stored in the ASE option but the parent toggle is
off, so nothing is live to replace.

## Decisions taken

These were open questions in the issues. Each was resolved to the option that
fails safe, and is recorded here rather than in a comment thread.

**1. `hide_admin_bar` with no roles named.** ASE stores the module as on with an
empty role list, which is ambiguous. Read as "hide for everyone except
administrators": the point of the setting is that members and editors do not see
admin chrome on the public site, and an administrator hiding their own toolbar
helps nobody. A named-roles mode is also offered for sites that want something
narrower. A BlueWorx support session always keeps its toolbar — the read-only
indicator and the button that ends the session live there.

**2. Activity is not removed by default.** The live site turns it off, but it is
the only dashboard panel showing what has recently changed, and one client's
preference is a poor default for every other site. The shipped default matches
what the BlueWorx admin theme already hides — Welcome, Quick Draft, Elementor
Overview — so switching this feature on changes nothing visible; it just makes
those three stay gone rather than being one Screen Options tick away. WSO ticks
Activity at cutover.

**3. Author slug obfuscation is kept, and defaults off.** Issue #69 asked whether
the WSO client plugin's author-archive redirect makes it redundant. It does for
WSO, but the redirect is that client's code and this plugin serves every site, so
the capability stays. Default off because it changes URLs, which is not
something an update may do to a live site. WSO can leave it off: their redirect
already covers it.

**4. External permalinks are kept, and default off.** Issue #72 asked for a usage
count before porting, which needs a write-capable session this milestone did not
have. Building it behind a default-off switch costs nothing and settles the
question either way: if WSO turns out not to use it, nobody turns it on and no
surface exists.

**5. Toolbar cleanup is its own feature, not part of `admin_theme`.** Issue #66
suggested extending the admin theme. Rejected. That feature is described to
clients as purely visual and reversible; removing a toolbar node changes what a
user can reach, and hiding the front-end toolbar changes it for everyone but an
administrator. Somebody switching the theme off to get standard WordPress back
should not also hand the Customizer to every editor.

**6. New features that change URLs, open network surface, or grant a capability
default OFF.** The feature registry previously treated an absent option as
enabled, which is right for the features that shipped under it. `robots_txt`,
`author_slugs` and `view_as_role` now carry an explicit
`'default' => '0'`, so an update can never switch them on underneath a live site.

## Still to do before ASE can be removed

See the retirement runbook in `docs/ase-retirement.md` (issue #75). Nothing in
the table above has been verified against the live site yet — it is built, not
cut over.
