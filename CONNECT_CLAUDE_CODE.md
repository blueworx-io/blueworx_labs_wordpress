# Connect Claude Code to a BlueWorx site

A copy-ready prompt that gives a Claude Code session everything it needs to inspect a
live WordPress site running **BlueWorx Labs | WordPress Enhancements** — no manual
explanation, every session.

It uses the plugin's **Support Access** feature (`includes/support-access.php`): one
per-site key that grants **read-only** wp-admin and REST access, and only while a
deliberately opened 24-hour window is in effect.

---

## Before you paste

1. In wp-admin, go to **BlueWorx → Enhancements → BlueWorx support access**.
2. **Generate key** if there is no key yet. The raw key is shown **once** — copy it now.
   Only its SHA-256 hash is stored, so a lost key must be revoked and regenerated.
3. Click **Allow support access for 24 hours**. Without an open window the key is inert
   and every request is refused with `403`, so this step is not optional.
   - Tick **Also allow access to personal data for this session** only if the work
     genuinely needs users, comments, accounts or orders. Leave it off by default.
4. Fill the two placeholders in the prompt below — `<SITE-URL>` and `<SUPPORT-KEY>` —
   then paste the whole block into Claude Code.

> **Never commit a filled-in prompt.** The key is a live credential. Keep it in your
> password manager or paste buffer, not in this repo, an issue, or a chat log. When the
> work is done, **Close support access** (or **Revoke key**) in the same panel.

---

## The prompt

```text
You are connecting to a live WordPress site that runs the BlueWorx Labs | WordPress
Enhancements plugin. Use its Support Access path — read-only, key-gated.

Site URL:    <SITE-URL>            # scheme + host, no trailing slash, e.g. https://example.com
Support key: <SUPPORT-KEY>

How to connect

- REST: send the key as a header on every request.
      curl -sS -H "X-Blueworx-Support-Key: <SUPPORT-KEY>" "<SITE-URL>/wp-json/wp/v2/pages?per_page=5"
  The key authenticates you as the managed `blueworx_support` user. Do not send it as a
  query parameter and do not put it in a URL you might paste into a log or an issue.
- Browser (only if you need to see a wp-admin screen): open
      <SITE-URL>/?blueworx_support_login=<SUPPORT-KEY>
  It sets a session cookie and redirects to wp-admin. Any path on the site works.

URLs you will need

- REST index:        <SITE-URL>/wp-json/
- Core content:      <SITE-URL>/wp-json/wp/v2/          (posts, pages, CPTs, media)
- BlueWorx headless: <SITE-URL>/wp-json/blueworx/v1/    (menus, site config, path resolution)
- wp-admin console:  <SITE-URL>/wp-admin/admin.php?page=blueworx-labs-wordpress
- The default /wp-login.php is blocked by this plugin — there is a custom login URL,
  shown on the console above. Do not try to log in the normal way.

Rules — respect these rather than working around them

- READ ONLY. Every non-GET/HEAD request from this account is refused with 403 and
  logged. If a change is needed, tell me what it is and let a human make it.
- The window is 24 hours. When it closes, or if the key is revoked, everything starts
  returning 403 — that is expected, not a bug. Ask me to reopen it.
- Personal data is withheld unless I explicitly opened it for this session:
  /wp/v2/users, /wp/v2/comments, /blueworx/v1/account, /blueworx/v1/surecart, and the
  WooCommerce/SureCart order and customer routes return
  403 blueworx_support_no_data. Do not attempt to reach that data another way.
- Five wrong keys locks this address out for 15 minutes. If you get a 403, stop and
  check with me instead of retrying with variations.
- Every use is written to the audit log on the console panel, including refused writes.

Start by fetching <SITE-URL>/wp-json/ to confirm the connection, then tell me what you
can see and wait for the actual task.
```

---

## Troubleshooting

| Symptom | Cause |
|---------|-------|
| `403 Support access is not available.` | The window is shut, the key is wrong, or the `support_access` feature is toggled off. |
| `403 BlueWorx support access is read-only.` | A write was attempted. Expected — it is logged as `blocked_write`. |
| `403 blueworx_support_no_data` | A personal-data route without the personal-data opt-in for this window. |
| `429 Too many attempts.` | Five failed key attempts from this address. Generating or revoking a key clears it. |
| Requests authenticate but wp-admin bounces to the homepage | Site Protection is on and the request is not the support account — re-check the key header. |

## Related

- [`HEADLESS_INTEGRATION.md`](HEADLESS_INTEGRATION.md) — the JWT auth model for a Next.js
  frontend. That is a different credential path; support access is for troubleshooting,
  not for running a site.
- [`includes/support-access.php`](includes/support-access.php) — the implementation, and
  the source of truth if this document and the code ever disagree.
