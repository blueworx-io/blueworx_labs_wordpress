# Retiring Admin and Site Enhancements

The cutover runbook for issue #75. Every step here runs against a live or
staging WordPress install, not this repository — the code side of the milestone
is done, this is the part that has to be performed on the site.

Do not start until the parity matrix in `docs/ase-parity-matrix.md` has been
verified on staging.

## Before anything

- Take a full backup — database and files — and confirm you can restore it.
- Do this on staging first, in full, and only then on production.
- Book a quiet window. Step 4 changes what editors see.

## 1. Carry the settings across

BlueWorx Labs does not read ASE's `admin_site_enhancements` option; nothing is
migrated automatically, on purpose — a silent copy of one plugin's settings into
another is impossible to review. Set these by hand in **BlueWorx >
Enhancements**:

| Set this | To |
|---|---|
| Toolbar cleanup | On. Tick WP logo, Customize, Update counter, New Content, Howdy, Help. |
| Toolbar cleanup > front of site | Hide for everyone except administrators. |
| Dashboard tidy-up | On. Tick Welcome, Activity, Quick Draft, Elementor Overview. |
| XML-RPC disabled | On. |
| Hide usernames in author links | Leave OFF — the WSO plugin already redirects author archives. |
| Search engine rules | On. Paste the live robots.txt content below, exactly. |
| Media tools | On. Replacement on, size cap on at 1920 x 1920, SVG roles: Administrator. |
| Duplicate and redirect | On. Duplicate on. External links: on only if step 2 finds entries using it. |
| Revision limit | On, 20. |
| View the admin as another role | On. |
| Login session length | 24 hours, unless the client asks otherwise. |

The robots.txt content to paste, byte for byte:

```
User-agent: *
Allow: /wp-admin/admin-ajax.php
Disallow: /wp-admin/
Disallow: /author/

Sitemap: https://worldsquashofficiating.com/sitemap.xml
Sitemap: https://worldsquashofficiating.com/sitemap.rss
```

## 2. Open questions to settle on the site

- **External permalinks.** Count the entries actually using ASE's external
  permalink field. If none, leave the BlueWorx setting off and note it.
- **SureRank.** Check whether it also emits robots directives. Whichever filter
  runs last wins, so confirm `/robots.txt` reads correctly after saving.
- **Physical robots.txt.** If a real file exists at the site root, the settings
  screen says so and nothing saved will take effect. Remove the file.

## 3. Verify on staging, as real users

An administrator sees none of what this milestone changes. Test with:

- a real **editor** account,
- a real **WSO Rep** (`author`) account,
- a real **member** account holding both `sc_customer` and a `wso_level_*` role.

Check, for each: the toolbar on the front of the site, the dashboard, the user
profile screen showing and saving more than one role, the Duplicate link,
uploading an oversized photo, and replacing a file.

Also confirm the BlueWorx support session still works and still shows its
toolbar indicator.

## 4. Switch ASE off

1. Deactivate Admin and Site Enhancements.
2. Walk the checks in step 3 again. Nothing should have changed.
3. Confirm **Tools > Enhancements** is gone from the menu.
4. Delete the plugin.

If anything regresses, reactivate ASE — it is a plugin, not a migration, so
turning it back on restores the previous behaviour.

## 5. Clean up what it left behind

Only after the site has run without ASE for a week:

- Delete the `admin_site_enhancements` option.
- Drop ASE's login-attempts log table.
- Grep the Labs codebase for any reference to ASE or to that option. There
  should be none; this document and the parity matrix are the only mentions.

## Note on the 1049 media items

Nothing in this milestone rewrites an existing attachment. The upload size cap
applies on ingest only, and file replacement only touches the one file an editor
chooses. No bulk pass over the media library is needed or wanted.
