# Connect Claude to a site

Lets Claude read and edit a site's pages, posts and other content. An
administrator approves it on the site, and can disconnect it at any time.

Claude **can**: read content (including drafts), create content (as drafts
unless told to publish), edit content, move content to the bin, and add
categories and tags.

Claude **cannot**: upload or delete files, delete anything permanently, or
change plugins, themes, settings or users.

---

## 1. Switch it on

1. In wp-admin, go to **BlueWorx → Enhancements → Security & Access**.
2. Turn on **Connect Claude**, and save.
3. Click **Open Connect Claude page** (or go to **BlueWorx → Connect Claude**).
4. Click **Copy address**. It looks like `https://yoursite.com/blueworx-mcp`.

## 2. Add it in Claude

1. In Claude, go to the connectors settings and add a **custom connector**.
2. Give it a name (for example the site name) and paste the address.
3. Leave the advanced client ID and secret fields empty.
4. Click **Connect**.

## 3. Approve it on the site

1. Claude opens the site. Sign in as an **administrator** if asked.
2. Check the screen says the right site, then click **Allow**.
3. You go back to Claude, and the connector shows as connected.

Done. Ask Claude things like "list the draft pages on my site" or "write a
draft blog post about our opening hours".

---

## Disconnecting

- **One connection:** BlueWorx → Connect Claude → **Disconnect** beside it.
- **Everyone:** **Disconnect all**, or switch the function off.

A connection also stops working by itself if the person who approved it stops
being an administrator, or if Claude is not used for 30 days.

## Good to know

- Everything Claude changes is listed under **Recent changes by Claude**.
- WordPress keeps revisions, so an edit can be undone from the page editor.
- **Elementor pages:** their layout is stored separately, so Claude's edits to
  their content do not show on the live page. Claude is told this.

## If it does not connect

- **"Only administrators can connect Claude"** — sign in with an administrator
  account.
- **Claude says it cannot authenticate** — the host may be stripping the
  sign-in header. On Apache, add this to `.htaccess` above the WordPress block:
  `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`
- **A security plugin or firewall blocks it** — allow the addresses
  `/blueworx-mcp`, `/blueworx-oauth/` and `/.well-known/oauth-`.
