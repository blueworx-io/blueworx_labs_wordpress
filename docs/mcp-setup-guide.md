# Connect Claude to a site

Lets Claude read and edit a site's pages, posts and other content. An
administrator approves it on the site, and can disconnect it at any time.

Claude **can**: read content (including drafts), create content (as drafts
unless told to publish), edit content, move content to the bin, and add
categories and tags. Where Forge is installed, it can also work with Forge
projects and tasks (see below).

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

## Forge projects and tasks

On a site running Forge, Claude can also list projects and tasks, read one in
full, create a task, change its title, description, due date, assignee,
reviewer or priority, set the planned hours and dates for each role, say
where it came from, confirm its client, move its status, and add a comment.

Claude can also read the team's capacity: each person's hours, week by
week, after leave and the work already booked. Ask about one person to see
the tasks taking up their time. Claude cannot change anyone's working week
or leave; that stays in Forge.

- Status changes follow Forge's workflow. If Forge is waiting on something
  first (a reviewer, say), Claude is told what is missing.
- Sending work back to an earlier status needs a reason, as it does in Forge.
- New tasks start at Future Idea. Claude needs a project or a client site to
  put them on.
- To move a new task on, Forge needs a reviewer, where the work came from,
  and the client confirmed. Claude can give all three, and should only
  confirm the client when it knows the client is right.
- Other sign-offs Forge asks for later (approvals, review results) are still
  done in Forge by the people it names.

On a site without Forge, none of this is offered.

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
