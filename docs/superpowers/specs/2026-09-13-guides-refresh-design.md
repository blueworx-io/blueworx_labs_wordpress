# Guides refresh — task-based guides across every product a client uses

**Date:** 2026-09-13
**Status:** Design, approved in chat, pending spec review
**Target version:** 1.86.0 here (minor — new content and a new product); minor bumps in ClubHouse and Forge

## Problem

The Guides page (BlueWorx > Guides) explains *what* things are, in two short
paragraphs each. Clients need to be told *how* to do a job: where to click, in
what order, and what they should see afterwards. Three things are wrong today:

1. **The wrong audience on the BlueWorx tab.** Every enabled feature gets a
   guide, including ones a client never touches (XML-RPC, REST user listing,
   author slugs, robots.txt, session length…). A client reading the BlueWorx
   tab sees a wall of technical settings before anything they can act on.
2. **Thin coverage of the other products.** WordPress has no blog-post topic
   at all; SureCart has three guides; LatePoint, ClubHouse and Forge have none.
3. **The style is explanatory, not instructional.** A guide says why a thing
   matters and roughly where it is. It does not walk somebody through it.

## Goals

1. Every guide is one task, written as numbered steps with a "where" line and
   a one-line "what happens next".
2. The BlueWorx tab carries only the features a client will actually use.
3. WordPress (with a Blog posts topic), SureCart, LatePoint, ClubHouse and
   Forge are each covered end to end for the jobs a client does on them.
4. Guides for another plugin live in that plugin's repo and register through
   the existing filters, so they change with the plugin they describe.
5. Everything stays where it is: same page, same URL, same tab structure,
   same capability gating. Only the content and the product list change.

## Non-goals

- Guides for BlueWorx staff or site builders. Nothing here explains how to
  configure a feature; the Enhancements screen's own descriptions do that.
- Search, screenshots, video or an in-page contents list. The page is
  already organised by product and topic; that is enough.
- A guide editor. Guides remain code, translated through the text domain.
- Rewriting ClubHouse's self-building guide. It is reused, not replaced.
- Multisite.

---

## 1. The guide format

A guide is one task. On the card it reads:

> **Where:** BlueWorx > Cache
> 1. Press *Clear cache*.
> 2. Wait for the green confirmation.
>
> The page you changed now shows the new version to visitors. If it still
> looks old, refresh your browser once — your own browser keeps a copy too.

Three parts, in that order, every time:

| Part   | Required | What it is |
| ------ | -------- | ---------- |
| Where  | yes      | The menu path or screen, as the sidebar labels it (after Display names, where that is on — see §2.3). |
| Steps  | yes      | An ordered list. One action per step. No step longer than two sentences. |
| Then   | yes      | One or two sentences: what the person should now see, and the one thing that most often goes wrong. |

A short opening sentence *may* precede the steps when the task needs framing
("A page is a fixed part of the site; a post is a dated entry"), but it is
the exception.

### 1.1 A helper, so guides are data not markup

`blueworx_guide_body( array $parts )` builds the body from
`where`, `intro` (optional), `steps` (list of strings) and `then`. It escapes
every string, wraps the steps in `<ol>`, and returns HTML that already passes
`wp_kses_post`. Every guide in this plugin uses it; the `body` key the filter
accepts stays as documented in `docs/guides-api.md`, so other plugins may use
the helper when this plugin is present or hand in their own HTML when it is
not.

### 1.2 Rendering

The card renderer in `includes/admin-guides.php` does not change shape. Two
small additions:

- Styles for `ol` inside `.bw-guide__body` and for the *Where* line, in the
  guides stylesheet, using the design-system tokens already loaded.
- The read-time badge keeps working; it counts words in the body as now.

### 1.3 Voice

Same rules as the current guides, applied to steps: plain words, second
person, present tense, the label on the button in italics, no jargon that is
not on screen. British spelling. A step never explains *why* — that is what
*Then* is for, and only when it stops a common mistake.

---

## 2. The BlueWorx product

### 2.1 Which features get a guide

The feature registry in `includes/features.php` gains an optional
`'guide' => false` flag. `blueworx_get_feature_guides()` skips a flagged
feature exactly as it skips a disabled one. Nothing else reads the flag.

Guides are kept for: `login` (sign-in address), `site_protection`, `sso`,
`support_access`, `cache_manual`, `cache_auto`, `menu_editor`, `view_as_role`,
`content_tools` (duplicate), `media_tools` (replace file), `page_excerpts`,
`translate`.

Flagged `guide => false`: `xmlrpc`, `rest_users`, `author_slugs`,
`application_passwords`, `robots_txt`, `emails`, `revisions`,
`login_session`, `login_redirect`, `profile_cleanup`, `dashboard_widgets`,
`admin_bar`, `admin_theme`, `display_names`, `comments`, `user_roles`.

### 2.2 Splitting features into tasks

One feature can carry more than one guide when it does more than one job.
`blueworx_get_feature_guide_bodies()` becomes
`blueworx_get_feature_guide_tasks()`, keyed by feature, each a *list* of
guides. The first guide keeps the id `feature-<key>` so existing links and
specs still resolve; extra guides are `feature-<key>-<slug>`. All inherit the
feature's tab, gating and hidden-when-off behaviour.

| Feature | Guides |
| ------- | ------ |
| login | Finding your sign-in address · Changing it and telling the team |
| site_protection | Making the site private while you build · Opening it up again |
| sso | Signing in with your work account · Putting the *Join* button on a page · Finding out why a sign-in failed |
| support_access | Letting BlueWorx look at the site · Closing the window early |
| cache_manual | Clearing the cache when something looks stale |
| cache_auto | What clears on its own when you publish (one guide, short) |
| menu_editor | Reordering the sidebar · Hiding things nobody uses |
| view_as_role | Checking what an editor can see |
| content_tools | Duplicating a page · Pointing a menu entry at another site |
| media_tools | Replacing a file without breaking links · Uploading a logo as SVG |
| page_excerpts | Writing the summary that search results show |
| translate | Choosing which languages the button offers · Keeping a page out of translation |

### 2.3 Names on screen

Where Display names is on, the sidebar says *Commerce* not *SureCart* and
*Bookings* not *LatePoint*. The *Where* line uses whichever the sidebar
currently shows, via the existing display-name lookup, so a client is never
told to click a label that is not there. Product tab labels along the top of
the Guides page do the same.

### 2.4 Gating

Unchanged. The BlueWorx section stays `manage_options`, because every screen
it describes is. A client who is an editor sees WordPress and whichever other
products they can act on; a client who is the site administrator sees
everything.

---

## 3. WordPress

Tabs become: **Getting started**, **Blog posts** (new, `wp-posts`),
**Pages** (renamed from Writing & editing), **Media library**,
**Users & roles**, **Updates & health**. Every existing guide is rewritten to
the task format; nothing is dropped.

**Blog posts** — the topic the request named, in full:

1. Writing and publishing a post
2. Adding a featured image
3. Putting a post in a category, and adding tags
4. Scheduling a post for later
5. Editing a post that is already live
6. Taking a post down without deleting it
7. Getting a post back from the trash
8. Changing who a post says wrote it

**Pages**: building a page block by block · adding a link · headings in the
right order · publishing, previewing and saving a draft · undoing a change
(revisions) · adding a page to the menu.

**Media library**: uploading an image · writing alt text · replacing a file ·
finding where an image is used.

**Users & roles**: adding somebody · which role to give them · when somebody
leaves.

**Updates & health**: what to do when the update badge appears · checking the
site after an update.

---

## 4. SureCart

Tabs stay **Products & plans**, **Orders & customers**, **Payments & test
mode**. Guides grow from three to:

**Products & plans**: adding a product · changing a price · adding a second
price (monthly and annual) · making a discount code · archiving a product you
no longer sell.

**Orders & customers**: finding an order · refunding a payment · cancelling a
subscription · looking up a customer and what they have bought · resending a
receipt.

**Payments & test mode**: checking whether you are in test mode · placing a
test order · going live and placing one real order.

Detection is unchanged (`SureCart` class or `SURECART_PLUGIN_FILE`).

---

## 5. LatePoint (new product)

Added to `blueworx_get_guide_products()` after SureCart, detected by the
`OsSettingsHelper` class or the `LATEPOINT_VERSION` constant, with a capability
the LatePoint screens themselves require. Label follows Display names
(*Bookings* where on). Tabs:

**Calendar** (`lp-calendar`): seeing today's bookings · booking somebody in by
hand · moving a booking · cancelling a booking · marking a no-show.

**Services** (`lp-services`): adding a service · changing its length or price ·
hiding a service without deleting it.

**Staff & hours** (`lp-staff`): adding a staff member · setting working hours ·
adding a day off or holiday · blocking out part of a day.

**Customers** (`lp-customers`): finding a customer and their history · editing
their details.

The exact screen labels are taken from the LatePoint version running on the
test harness at implementation time and recorded in the guide bodies; if
LatePoint's own menu differs from what a guide says, the guide is wrong and
the harness spec that opens LatePoint should catch it.

---

## 6. ClubHouse (in `blueworx_labs_clubhouse`)

ClubHouse already builds its own user guide from the live site
(`Blueworx_Clubhouse_Guide`): chapters for screens, pages, content,
collections and looks, derived from the same registries the product uses, so
it never drifts. Keep that engine. Two changes:

1. **Register into the BlueWorx Guides page.** When the `blueworx_guides`
   filter exists, ClubHouse adds a `clubhouse` product and one tab per
   chapter, and turns each chapter entry into a guide. Entries are rewritten
   in the task format of §1 — the derived facts (page names, screen URLs,
   collection counts) become the *Where* line and the steps, so the guides
   stay self-updating.
2. **One place to look.** When the BlueWorx plugin is active, ClubHouse's own
   Guide menu item links to `admin.php?page=blueworx-guides&product=clubhouse`
   instead of its own screen. Its own screen stays registered and working for
   sites without BlueWorx.

Gating: each guide carries the capability of the screen it describes, which
the controller already knows.

---

## 7. Forge client site (in `blueworx_project_forge`, under `client/`)

The client plugin registers a `forge` product. The studio plugin registers
nothing — its users are BlueWorx staff. Guides, one tab each unless the tab
would hold one guide:

**Your workspace** (`forge-workspace`): finding what has been sent to you ·
reading a submission all the way through (the read-through) · approving it ·
sending it back with a note.

**Checklists** (`forge-checklists`): answering a checklist · what happens when
you say no to an item.

**Discussion** (`forge-discussion`): replying in a discussion · who sees what
you write.

**Reports** (`forge-reports`): reading a report · the weekly digest email and
where to change it.

The exact set is confirmed against `client/includes/` at implementation time
— the list above is what the module names describe, and anything a client
cannot reach from the client site is left out.

---

## 8. Documentation

`docs/guides-api.md` gains a short section on `blueworx_guide_body()` and the
recommended shape, and a note that a product is registered with
`blueworx_guide_products` plus `blueworx_guide_tab_products`.

`readme.txt` and `CHANGELOG.md` entries in each repo say what a client will
notice: guides now tell you what to click, step by step; new sections for
blog posts, LatePoint, ClubHouse and Forge; technical settings no longer
listed.

---

## 9. Testing

Playwright, against the local harness, in each repo. The existing
`guides*.spec.js` here already cover tabs, products, gating and design; they
are extended rather than duplicated.

This plugin:

- Every guide body contains a *Where* line and an ordered list (loop over the
  registry, not fixed ids, so a new guide cannot skip the format).
- The flagged features have no guide and the kept ones do, with the feature
  on and with it off.
- The Blog posts tab exists and lists its eight guides.
- LatePoint product appears with LatePoint active, not without; SureCart
  unchanged. The harness fixture that already installs LatePoint for
  `latepoint-layout.spec.js` (`scripts/install-test-latepoint.mjs`) is reused.
- An editor sees WordPress and not BlueWorx; an administrator sees both.
- *Where* lines change with Display names on and off.

ClubHouse: the product and tabs appear on the BlueWorx Guides page when both
plugins run; switching a ClubHouse page off removes its guide; the Guide menu
item lands on the BlueWorx page.

Forge: the client site shows the Forge product to a client user; the studio
site does not register one.

---

## 10. Delivery

Three pull requests, one per repo, in this order:

1. **blueworx_labs_wordpress** — format, helper, BlueWorx scope, WordPress,
   SureCart, LatePoint, docs, tests. 1.86.0.
2. **blueworx_labs_clubhouse** — filter registration and menu link. Minor
   bump.
3. **blueworx_project_forge** — client-site registration. Minor bump.

2 and 3 depend on 1 being released, because they rely on the helper and the
product filters as documented there. Each PR is independently mergeable and
each site keeps working with any mix of versions: a guide handed in without
the helper is just HTML, and a product nobody registers is just absent.

Roughly 65 guides across the three PRs. Writing is the bulk of the work; the
code changes are small.
