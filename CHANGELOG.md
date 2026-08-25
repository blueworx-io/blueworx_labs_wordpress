# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/); this project uses semantic
versioning.

## [1.70.0] - 2026-08-25

### Changed
- **The controls around every list screen are BlueWorx now, not WordPress.**
  The filter links, the bulk-action bar, the row actions under each row, the
  search box and the pager all match the table they sit around. Covers posts,
  pages, media, comments, users, plugins and site health.

### Added
- **Every WordPress screen says which part of the site it belongs to**, and who
  can reach it — the same two lines the BlueWorx screens carry. Both are worked
  out from the screen itself, so they are right on a custom post type and on
  screens belonging to plugins we have never seen.
## [1.69.0] - 2026-08-25

### Added
- **Support access can be switched on from its own screen**, instead of sending
  you to Enhancements and back. Switching it off there also shuts any window
  that was open.
- **The Support screen says what the state actually is** — whether a key
  exists and when it was made, whether the window is open, and when somebody
  last signed in with one.
- **Copying the support key now asks you to confirm you have it.** It is shown
  once and never again.
- **Sign-on offers a provider by name** — Microsoft Entra ID, Google Workspace,
  Okta, or any OpenID Connect provider. You no longer have to go and find a
  discovery address.
- **The WordPress password form can be hidden** once one administrator has
  actually signed in through the provider. Until then the switch refuses,
  because before that it is a way to lock everybody out.

### Changed
- **Revoke says what it revokes** — access, not just the key.
- **Advanced on the sign-on screen looks like the rest of the plugin**, rather
  than a browser's own disclosure triangle.

## [1.68.0] - 2026-08-25

### Added
- **Guides now covers more than this plugin.** Pick a section along the top —
  BlueWorx, WordPress, and SureCart or SureForms where the site runs them —
  then a topic below it.
- **Thirteen new guides**, covering writing and editing, the media library,
  users and roles, updates and health, and the SureCart and SureForms basics.
  Guides for a plugin the site does not have never appear.
## [1.67.0] - 2026-08-25

### Changed
- **The top bar says where you are**, not only what you are looking at — the
  site's name, then the screen.
- **"Viewing as" moved to the top bar**, beside who you are, instead of a bar
  pinned across the bottom of every screen. The picker that gets you into a
  role stays where it was.
- **Admin screens use the whole window.** They were capped at 1200px wide.

### Added
- **Comments and Updates now carry a count in the sidebar**, like Posts and
  Pages already did. Comments counts what is waiting on you, not the total.

## [1.63.0] - 2026-08-25

### Changed
- **Enhancements is rebuilt the way it was designed.** Each function is now its
  own card, with its name and description beside the switch instead of crammed
  into its label, and its settings open in a panel underneath that belongs to
  it. A section can be switched off in one go, and each says how many of its
  functions are on.
- **The login panel gives you the whole sign-in address to copy**, instead of
  just the last part of it, and the notice repeating it at the top of the
  screen has gone.
- **Image sizing is one slider** — the longest edge — rather than a separate
  width and height that only ever mattered when they disagreed.
- **Chosen translation languages read as a row you can remove from**, rather
  than ticks scattered through a list of thirty.

### Added
- **Administrators can be signed out sooner than everybody else.** Theirs is
  the account that can change the site.
## [1.62.0] - 2026-08-25

### Added
- **Edit Menu can send an item straight to another group**, with two new arrows
  on every row. Before, you had to walk it past every other item first. There is
  also a Reset to WordPress order button, and a Discard changes button beside
  Save.
- **The Cache screen says when the cache was last refreshed by hand.** Nothing
  on the site was recording it.

### Changed
- **The Cache screen is narrower and calmer** — one column, and it now warns
  that the first visitor to each page waits a moment after a refresh.
- **Edit Menu rows show each menu's own icon**, and each group says how many
  items are in it.
- **The two reference screens are titled the way the designs title them.**

## [1.61.11] - 2026-08-25

### Added
- **Four new parts of the shared design system are now available to these
  screens** — the function card, the section header, the two-column settings
  row and the product tab row, plus a narrow one-column page. Nothing uses
  them yet; this only brings them in.

## [1.61.9] - 2026-08-25

### Fixed
- **Dropdowns draw their arrow inside the box again.** It had been falling out
  and landing underneath every dropdown in the plugin, and the one on the
  role-view bar had no arrow at all.
- **Icons are sized by the design system rather than by a width written into
  the markup**, which is also what puts empty-state icons back in the right
  colour.

## [1.61.8] - 2026-08-25

### Changed
- **The dashboard tiles are now the shared design system's**, so they match
  every other panel rather than being a set of our own. Each tile gains a small
  icon, and the comments tile now says when comments are switched off — a zero
  there used to mean either nobody had commented or comments were off, with no
  way to tell which.

## [1.61.5] - 2026-08-24

### Changed
- **The sign-in screen now takes its colours, spacing and type from the shared
  design system** too, finishing the job the previous two releases started. The
  split-screen layout is unchanged.

## [1.61.4] - 2026-08-24

### Changed
- **The admin re-skin now takes its colours, spacing and type from the shared
  design system** rather than from values written into its own stylesheet. The
  admin looks a touch flatter — corners are slightly less round — and the
  greens, ambers and blues are the same ones the rest of the plugin uses.

## [1.61.3] - 2026-08-24

### Fixed
- **The admin screens now use the right brand colours and typeface.** The
  BlueWorx re-skin was quietly overriding the shared design system, so panels
  built on the system rendered with the wrong green, the wrong border and the
  wrong card shadow, and body text in the heading face rather than the reading
  one. Nothing about the layout changes; the colours and type are simply the
  ones they were meant to be. The same applied to the sign-in screen.

## [1.61.2] - 2026-08-24

### Changed
- The plugin now carries the shared design system guardrail: an admin screen
  that is not built from the BlueWorx design system is refused as it is written,
  rather than being found later. Nothing on the site changes.
- Our checks now run against a named release of the shared foundation rather than
  a tag that had been left behind since the start of August, so the design system
  is checked on every pull request. The check that our admin screens are built
  from the design system reports without blocking while the re-skin catches up.

## [1.61.1] - 2026-08-24

### Fixed
- **Support access works again on its own screen.** The Generate key button,
  and the ones for opening and revoking, did nothing at all when clicked. The
  panel had moved onto a page of its own without the form its buttons need, so
  they took the click and went nowhere. The same buttons on Enhancements were
  never affected.

## [1.61.0] - 2026-08-24

### Changed
- The settings inside each function's panel on Enhancements now look like the
  rest of the screen. They were still plain WordPress form controls — most
  obviously the Site Protection role pickers, which were multi-select boxes
  nobody could work out how to pick two roles in. Roles are now a simple list
  of tick boxes, the same one used everywhere else in the plugin. The Single
  sign-on and Translation panels got the same treatment.
- The address to give your identity provider now has a copy button instead of
  being a line of text to select by hand.

### Fixed
- The admin screens no longer scroll sideways on a phone. The section nav down
  the side kept its full-width column on small screens and squashed everything
  beside it, and on Guides the row of tabs dragged the page out to nearly four
  times the width of the screen.
- Added the missing guide for landing on the dashboard after signing in. Every
  function now has one.

## [1.60.0] - 2026-08-23

### Added
- Support access and single sign-on now have pages of their own under BlueWorx,
  rather than being panels buried in a list of twenty-seven switches. The
  Enhancements switch still turns each one on and off.
- Two reference screens: Embedded controls, which lists every place the plugin
  puts something inside one of WordPress's own screens, and System additions,
  which records the components these designs needed that the shared design
  system does not carry yet.
- Guides now say who can do the thing each one describes. The roles are worked
  out from this site's own capabilities, so a site that has added or changed a
  role gets pills that match it.
- Guides show a read time, sit in two columns on a wide screen, and their tabs
  can be dragged sideways when there are more than fit.

### Changed
- Guides is now its own item in the side menu, sitting just below BlueWorx,
  instead of being tucked inside the BlueWorx menu. The address for it has not
  changed, so existing links and bookmarks still work.
- The side menu matches the design: the row you are on carries the highlight on
  its own, and the item above it gets a quieter wash instead of a second one.
- On a phone the menu is now a drawer that slides in over the page from a
  button in the BlueWorx bar. WordPress's own bar is only taken away once that
  button is working, so there is no way to end up on a phone with no menu.

## [1.58.9] - 2026-08-22

### Changed
- **Tidy-up only — nothing looks or behaves differently.** The old stylesheet
  still had rules for the BlueWorx screens as they used to be built. Those are
  gone, and there is now a test that fails if a BlueWorx screen starts leaning
  on it again. (#122)

## [1.58.8] - 2026-08-22

### Fixed
- **Replacing a media file works again.** The Replace button on an attachment
  sent you to the Posts list with nothing replaced and nothing said. It had
  never worked from that screen.

### Changed
- **The BlueWorx controls inside WordPress's own screens now match the rest of
  BlueWorx.** The replace-file box, the external address field, the multi-role
  tick list and the view-as-a-role bar all look like the plugin they belong to.
  The screens around them are untouched. (#121)

## [1.58.7] - 2026-08-22

### Changed
- **The Edit Menu screen has been rebuilt.** Groups are cards, each item is a
  row you can drag or move with the arrow buttons, and an empty group now says
  so rather than being a blank space you have to guess at. Save sits at the
  bottom of the screen and tells you nothing is saved until you press it.
  Reordering, hiding and grouping all work exactly as they did. (#119)

## [1.58.6] - 2026-08-22

### Changed
- **The Support Access panel has been rebuilt.** The key you generate now sits
  in a field with a Copy button beside it, so you can copy it on any site
  rather than having to select it by hand. Whether access is open, shut or
  temporarily blocked is said plainly at the top of the panel instead of in a
  line of bold text. Generating, opening and revoking work exactly as before,
  and the key is still only ever shown once. (#120)

## [1.58.5] - 2026-08-22

### Changed
- **The Enhancements screen has been rebuilt.** Sections now sit in a list down
  the side, each showing how many of its functions are on, so you work in one
  section at a time instead of scrolling past everything. Each function is a
  switch with its settings underneath it, and Save stays with you at the bottom
  of the screen rather than at the end of the page. Nothing about what the
  functions do, or how they save, has changed. (#118)

## [1.58.4] - 2026-08-22

### Fixed
- **Tabs, menu rows and buttons are no longer underlined.** Anything on a
  BlueWorx screen that is a link underneath — a tab, a row in the side menu, a
  button you can right-click and open in a new window — was being underlined as
  though it were a link in a sentence. Ordinary links in text still are.

## [1.58.3] - 2026-08-21

### Changed
- **The Guides screen has been rebuilt.** Each guide is now a card, the section
  tabs say how many guides sit behind them, and a site with nothing switched on
  gets a proper explanation and a way through to Enhancements instead of a bare
  line of text. Tabs are still ordinary links, so you can still bookmark one or
  send it to somebody. (#117)

## [1.58.2] - 2026-08-21

### Changed
- **The Cache screen has been rebuilt.** It now looks like the rest of BlueWorx
  rather than a plain WordPress settings table, and says plainly what refreshes
  on its own and what is waiting for you to press the button. Refreshing works
  exactly as it did. (#116)

## [1.58.1] - 2026-08-21

### Internal
- The BlueWorx screens now draw from the shared BlueWorx design system rather
  than their own stylesheet, so they look like the rest of BlueWorx and stay
  that way on their own. Nothing on screen changes yet — this release only puts
  the system in place for the screen rebuilds that follow. (#115)

Nothing changes for anyone using the plugin.

## [1.58.0] - 2026-08-21

### Added
- **A separate button for joining.** Single sign-on now has two entry points
  instead of one: Sign in, for people who already have an account, and Join, for
  people who do not. Both come back to the same address, so your provider still
  only needs the one.

  Joining asks the provider to open on its "create an account" screen, and the
  two can land people on different pages afterwards — a newcomer on your welcome
  or next-steps page, everyone else wherever they normally go. The sign-in button
  is still added to the login screen for you; put the joining one on a page with
  `[blueworx_sso_button intent="register"]`.

### Changed
- **Signing in no longer creates accounts.** Someone with no account here who
  presses Sign in used to get a brand new empty one, which looks exactly like
  their history has been lost. They are now sent to your joining page instead —
  set it under Single sign-on. Only the Join button creates accounts, and only if
  you allow it.

  If you were relying on the sign-in button to create accounts, add the joining
  button to your site.

### Fixed
- **A safety check on the profile the provider sends back.** Where the signed
  proof of who signed in and the profile details fetched alongside it disagreed
  about who they described, the details were used anyway. They are now refused.
  No site is known to have been affected; providers do not normally do this.

## [1.57.3] - 2026-08-21

### Fixed
- The editor no longer opens with an empty strip across the top when you put it
  in fullscreen. WordPress 7.1 changed how it reserves room for the toolbar we
  replace, and we were still leaving that room behind.

### Internal
- Tests now run against the local copy of the plugin by default, instead of
  whatever build happens to be deployed on a staging site. A run that says
  "passed" now says it about the code in front of you. Testing a deployed site is
  still possible, but you have to ask for it. (#113)
- The LatePoint layout can now be checked against a real LatePoint install
  rather than a hand-written imitation of its stylesheet, which is where the last
  two Bookings layout bugs came from. One command installs it locally; the check
  skips where it is absent. (#114)

Nothing changes for anyone using the plugin in either of those two.

## [1.57.2] - 2026-08-06

### Fixed
- **The Bookings search box and New Booking button are no longer cut off.** The
  last release gave LatePoint the whole window, but its screens shift themselves
  up to fill the gap the WordPress toolbar leaves behind — and we had already
  closed that gap, so everything sat too high and the top of the page was lost
  off the edge of the window. It lines up properly now.

## [1.57.1] - 2026-08-06

### Fixed
- **The Bookings screens are no longer squashed by the admin design.** LatePoint
  uses the whole window and brings its own menu, so our sidebar was leaving an
  empty column down the left of every Bookings page with the BlueWorx logo
  stranded at the top of it. The BlueWorx sidebar and top bar now step aside on
  those screens and LatePoint gets the whole window, the way it does on a site
  without this plugin. Its own button, bottom left, takes you back to the rest
  of the admin.

## [1.57.0] - 2026-08-04

### Added
- **Your list of users is no longer public.** WordPress publishes everyone's
  account name to anyone who asks, without them signing in — which gives away
  the names people log in with. A new Security & Access option, on by default,
  closes that off so it can only be read by someone signed in. Turn it off if
  something outside the site genuinely needs that list. Pairs with "Hide
  usernames in author links": either on its own leaves the names reachable by
  the other route.

  Sites already running the plugin are left as they are and can switch it on
  when they are ready, so nothing changes underneath a live site on update.
  New installs get it from the start.

## [1.56.1] - 2026-08-04

### Fixed
- **The admin sidebar no longer garbles itself on a smaller screen.** On a
  laptop window between roughly 780 and 960 pixels wide, WordPress narrows the
  sidebar to a strip of icons — but our section headings and the Log Out label
  stayed on, squashed into it as unreadable stacked fragments. They now collapse
  with the sidebar, the way they already did when you collapse it yourself.

  Sites already running the plugin are left as they are and can switch it on
  when they are ready, so nothing changes underneath a live site on update.
  New installs get it from the start.

## [1.56.0] - 2026-08-03

### Added
- **Signing in takes you to the dashboard again.** On a site with a booking or
  shop plugin — LatePoint was the one that prompted this — logging in could drop
  you straight onto that plugin's screen instead of your dashboard. A new
  Security & Access option, on by default, puts the dashboard back for anyone who
  works in the admin area. Customers still go where the booking plugin sends
  them, and a link that asked for a particular page still takes you there.

## [1.55.1] - 2026-08-02

### Changed
- The sign-in safety checks now run automatically with the rest of the tests, so
  a future change cannot weaken them unnoticed. Nothing changes on the site.

## [1.55.0] - 2026-08-02

### Added
- **Sign in with an account you already have.** A new Single sign-on option under
  Security & Access lets people sign in with an account from somewhere else — a
  company Google or Microsoft account, or a membership system — instead of a
  separate password here. Paste in the three details your provider gives you,
  give them the return address shown on the screen, and a sign-in button appears
  on the login page.

  You choose whether a first-time visitor gets an account automatically and which
  role they land on. Signing in can never make someone an administrator. It is
  off until you switch it on, and nothing changes for sites that leave it off.

## [1.54.0] - 2026-08-02

### Removed
- **The headless API is gone.** It let a separate front-end app read the site
  and sign people in, the app it was built for no longer exists, and no site
  uses it. It was switched off in the last release; this deletes it.

  Updating tidies up after it: two unused database tables are dropped, a nightly
  job that had nothing left to do is cancelled, and its settings are cleared. You
  do not have to do anything, and nothing you can see on the site changes.

## [1.53.0] - 2026-08-02

### Added
- **Choose how long people stay signed in.** Under Security & Access, "Login
  session length" sets how long a login lasts — 24 hours, 2, 5 or 7 days, or
  until the person signs out. It was two days before, and only if you closed the
  browser first, which is why it felt like being logged out constantly. Existing
  sites get 24 hours without changing anything.

- **Toolbar cleanup.** Takes the WordPress logo, Customize, the update counter,
  "+ New" and the "Howdy," greeting out of the black bar, and removes the Help
  drawer. It can also hide that bar completely on the public side of the site,
  either for everyone but administrators or for the roles you choose. A BlueWorx
  support session always keeps its toolbar.

- **Dashboard tidy-up.** Removes the dashboard panels nobody uses — Welcome,
  Quick Draft, Elementor Overview and others you pick. Removed for good, rather
  than hidden behind Screen Options where one tick brings them back.

- **Duplicate a page or post.** A Duplicate link beside every item makes a full
  copy as a draft, including its categories and all its field values, so the
  next one starts from a page that already works.

- **Media tools.** Replace a file with a new version without the address
  changing, so every page already using it updates by itself. Oversized photos
  are scaled down as they are uploaded — 1920 by 1920 out of the box. SVG logos
  can be allowed for chosen roles, and every one is stripped of anything that
  could run before it is stored.

- **Revision limit.** Keeps the most recent saved versions of a page rather than
  every version forever, which stops the database quietly becoming the biggest
  thing on the site. Twenty by default; versions already saved are left alone.

- **XML-RPC off.** Closes the old remote-publishing endpoint attackers use to
  guess passwords in bulk. On by default. Turn it off if you use the WordPress
  mobile app or Jetpack.

- **Search engine rules.** An editable robots.txt, off by default so an existing
  file or your SEO plugin stays in charge.

- **Hide usernames in author links.** Replaces the sign-in name in author page
  addresses with a meaningless code. Off by default, because it changes those
  addresses.

- **View the admin as another role.** Lets an administrator see what an editor
  or a member can actually reach, with a bar along the bottom to switch back. It
  can only ever show you less than you normally see, never more. Off by default.

### Changed
- **The headless API is now off unless you switch it on.** It let a separate
  front-end app read the site and sign people in, and nothing uses it any more —
  the app it was built for is gone. It was on everywhere, so a handful of
  addresses were answering on every site with no one calling them. Existing sites
  are not switched on by an update; if a site genuinely needs it, turn it on
  under Integrations. Nothing is deleted — the setting can be turned back on and
  everything is as it was.

## [1.52.0] - 2026-08-01

### Changed
- **A new look for the login screen.** It is now a split screen: your site's
  name and a short welcome on a dark panel down the left, and the sign-in box on
  the right. The wording is friendlier too — "Email or Username", "Remember me
  on this device", and a "Forgot Password?" link that sits next to the password
  box instead of underneath the form.

  Nothing about signing in changes. The same fields, the same buttons, the same
  links, and the same password reset and registration screens as before. On a
  phone the dark panel steps aside and you get the sign-in box on its own, as
  now.

## [1.51.0] - 2026-08-01

### Added
- **A Guides page, under BlueWorx > Guides.** Written support guides for every
  function this plugin adds, plus the everyday WordPress questions clients ask —
  pages versus posts, publishing and scheduling, undoing a change, images and
  alt text, editing the navigation, adding a user, and why updates matter.

  The page assembles itself rather than being a hand-kept list. Tabs are the
  feature sections from the settings page, so guides and settings read in the
  same shape, and every feature in the registry gets a guide slot — add a
  feature there and it appears here, falling back to its own settings
  description until someone writes a longer one. A function switched off has its
  guide hidden, so a client is never reading instructions for something they
  cannot see. A tab with nothing left in it disappears rather than becoming a
  dead end.

  The guide text is written, not generated. A docblock describes code to a
  developer and an LLM writing about the plugin at runtime can state things that
  are not true; neither belongs in front of a client.

  Other plugins plug into the same page through two filters:
  `blueworx_guide_tabs` adds a tab, `blueworx_guides` adds guides. Anything a
  third party supplies is run through `wp_kses_post` on output, so a guide body
  cannot introduce script. A guide naming a tab nobody registered is collected
  under "Other" rather than dropped — losing another plugin's content is worse
  than losing its grouping — and an id already in use is ignored, so a third
  party cannot displace a built-in guide by reusing its id.

  Tabs are plain links rather than JavaScript: each is a real URL that can be
  bookmarked or sent to someone, and the page works with no script at all.
  (#97)

## [1.50.2] - 2026-08-01

### Changed
- **Changelog entries go back in `CHANGELOG.md`.** 1.50.0 moved them to
  per-change files in `changelog.d/` so two open branches could not conflict on
  the top of one shared file. That half worked. The other half did not: folding
  the fragments back into `CHANGELOG.md` has to happen on `main` after a merge,
  and nothing is allowed to write there.

  `main` accepts changes only through a pull request. A workflow cannot be given
  an exception — this repo uses GitHub's older branch protection, which has no
  bypass list, and the newer ruleset system does not offer the Actions bot as an
  option. A bot-opened pull request is not a way round it either: GitHub does not
  run CI on one, so the required check never reports and it can never merge.
  Every remaining route needed either a weakened branch rule or a stored access
  token.

  So the fragments are gone and the version-line conflicts in
  `blueworx-labs-wordpress.php`, `package.json` and `readme.txt` were never
  addressed by this anyway. `docs/merging-branches.md` is the guidance that
  actually helps: resolve only the conflicting hunk, then run
  `npm run check:merge` before pushing. (#57)

### Changed

- **CI now pins the shared foundation workflow to `@v1` instead of tracking its
  `main` branch.** Any change to the shared workflow used to land in this
  project's CI the moment it merged upstream, with no way to stage it. `v1` is a
  moving major tag that follows backward-compatible releases, so fixes still
  arrive on their own; a breaking change goes to `v2` and waits for a deliberate
  move here. `foundation_ref` is set to match — it defaults to `main`, so pinning
  only the `uses:` ref would run the v1 workflow against today's scripts.
  Nothing about the plugin itself changes.

## [1.49.2] - 2026-08-01

### Changed
- **CI splits the Playwright suite across three runners.** The guardrails job
  ran all 133 tests on one runner at `workers: 1` and took ~11m30s on every PR,
  which was the main cost of merging anything.

  `workers: 1` stays exactly as it is. It is load-bearing: the specs toggle
  site-wide state — feature flags, menu order, protection settings — so running
  them concurrently against one WordPress makes one spec's "off" another's
  "on". Sharding avoids that because each shard is a separate CI runner with
  its own WordPress, so there is nothing shared to corrupt. Do not raise
  `workers` in `playwright.config.js` to chase the same win.

  The zero-tests gate now applies to the sum across shards, so a suite that
  skips itself wholesale still fails the build. No plugin code changes. (#55)

## [1.49.1] - 2026-08-01

### Changed
- **PHPCS runs clean.** The last twelve findings are gone, so the next real one
  will be visible instead of buried: array formatting in
  `admin-menu-badges.php` and `admin-menu-icons.php`, assignment and double
  arrow alignment in `rest/render.php`, and a doc comment in `rest/cors.php`
  that started with a lowercase `wp/v2`.

  Two were not reformatting. The `do_action( 'wp_enqueue_scripts' )` in
  `rest/render.php` is fired deliberately — it is core's hook, and the whole
  point is to reach the callbacks other plugins and the theme registered on it,
  so a plugin-prefixed name would reach nothing. That one carries a
  `phpcs:ignore` with the reason rather than a rename. The `get_post_types()`
  arguments in `admin-menu-badges.php` moved to a named variable instead of
  taking PHPCBF's inline wrapping, which read worse than the original.

  No behaviour change anywhere — formatting, one comment reword, and one
  documented ignore.

## [1.49.0] - 2026-08-01

### Security
- **A headless bearer token now only authenticates `blueworx/v1` routes.** The
  token check hangs off `determine_current_user`, which WordPress consults for
  every request it serves — so a valid token authenticated the whole of core
  `wp/v2` as well as our own namespace. A token minted so a front end could
  read its own account was, for an administrator, also enough to read and
  rewrite `wp/v2/settings`. The filter now returns early unless the request is
  addressed to `blueworx/v1`, reading both permalink shapes
  (`/wp-json/blueworx/v1/…` and `?rest_route=/blueworx/v1/…`) and taking the
  prefix from `rest_get_url_prefix()` rather than hard-coding `wp-json`. An
  unrecognised URL shape fails closed.

  Silent failure is unchanged: an invalid or out-of-scope token still leaves
  the request anonymous rather than refusing it, so public core routes stay
  public and cookie+nonce authentication is untouched.

  **Breaking for anyone calling core `wp/v2` with a bearer token** — that never
  worked by design, but it did work. Use cookie authentication for core routes,
  or a `blueworx/v1` route. (#27)
## [1.48.1] - 2026-08-01

### Fixed
- **The plugin now states one minimum PHP version, not two.** `composer.json`
  asked for `>=7.4` while the plugin header and `phpcs.xml.dist` both said 8.0,
  so Composer would happily resolve on a PHP version the code is neither
  written for nor linted against. Composer now requires `>=8.0`, matching the
  header, `readme.txt` and the PHPCS `testVersion`. No runtime behaviour
  changes and no dependency versions move — the lock file's platform
  requirement is the only thing that shifts. (#28)

## [1.48.0] - 2026-07-31

### Removed
- **The language switcher's button label is gone**, along with the "Button
  label" setting that fed it. The pill now shows the current language and
  nothing else — a flag, a name, or both, per the display style. Sites that had
  customised the wording lose it; migration 8 deletes the
  `blueworx_translate_label` option rather than leaving a dead row behind.

### Changed
- **The switcher's accessible name no longer depends on a site setting.** A pill
  reading only "English" — or, in flags-only, showing only a flag — does not say
  what pressing it does, so the button carries a fixed, translatable "Choose
  language" that is announced and never painted. The busy state, which used to
  echo the button label, now says "Translating…".

## [1.47.0] - 2026-07-31

### Added
- **The language switcher can be limited to site administrators.** New
  "Only show the switcher to site administrators" checkbox on the On-page
  translation panel, off by default, for trying the switcher out on a live site
  before opening it up. It is not a hidden button: the gate sits in
  `blueworx_translate_should_load()`, which every part of the feature already
  asks, so a visitor who is not an administrator is sent no root element, no
  inline config, no script and no stylesheet. "Administrator" means
  `manage_options` — the same capability that gates the settings screen the
  option lives on.

## [1.46.0] - 2026-07-31

### Added
- **A user can hold more than one role.** New "Flexible user roles" feature
  (Security & Access). The Role dropdown on the add-user and edit-user screens
  becomes a checkbox list built from the same roles WordPress was already
  willing to grant, and every ticked role is saved. Capabilities were always the
  union of a user's roles in WordPress core — what was missing was any way to
  choose more than one. Clearing every box is the explicit "no role for this
  site" case. `includes/user-roles.php`, `assets/js/user-roles.js`.
- **Roles are listed alphabetically.** The same feature filters `editable_roles`
  so every role list reads by display name instead of by whichever order plugins
  happened to register in. "— No role for this site —" is appended by wp-admin
  after the roles are printed, so it stays last.
- **The language switcher can show text, flags, or both.** New "Show languages
  as" setting under On-page translation: Text only (the default, unchanged
  behaviour), Text & flags, or Flags only. Flags are emoji, so there are no new
  assets and no requests; where a platform has no flag glyph — Windows — they
  degrade to the two-letter country code.
- **Phones get flags on the pill whatever the site chose.** Below 600px the
  floating button shows the flag alone. The open menu still reads in words,
  where there is room for them.

### Changed
- **The profile card names every role a user holds**, comma separated, instead
  of only the first one.

## [1.45.0] - 2026-07-30

### Removed
- **The Client Roles feature is gone.** The three assignable roles it registered
  — Admin — Business Owner (`blueworx_client_owner`), External Dev
  (`blueworx_client_dev`) and Content Editor (`blueworx_client_editor`) — are
  withdrawn along with the sidebar-group gating, the console block and the
  "Allow Content Editors to delete users" setting. `includes/client-roles.php`
  and the feature toggle are deleted; nothing else in the plugin depended on
  them.
- **Existing sites are swept clean on upgrade.** Migration 7 removes the three
  role definitions and deletes `blueworx_client_roles_signature`,
  `blueworx_client_editor_can_delete_users` and `blueworx_feature_client_roles`,
  so the roles stop appearing in the Users screen and in Site Protection. As
  with the 1.8.0 role-editor sweep, a role that still has a user assigned is
  left in place and recorded in `blueworx_orphaned_roles_skipped` rather than
  stranding that account.

### Changed
- **The support role is now labelled "BlueWorx - Support Agent (Read-Only)"**
  (was "BlueWorx Support (read-only)"). Presentation only — capabilities, the
  key gate, the window and the read-only enforcement are untouched. Migration 7
  re-registers the role in place so a site that already holds a support key
  picks up the new label without waiting for the next key generation.

## [1.44.0] - 2026-07-30

### Fixed
- **The support account can read its own user record again.** `/wp/v2/users` is
  denied by prefix while the personal-data window is shut, and `/wp/v2/users/me`
  starts with it — so the account was refused the one record that is not
  third-party personal data. wp-admin fetches `/wp/v2/users/me?context=edit` on
  every page load, so a support session saw a console 403 on every screen and
  block-editor preferences could not load or persist. `me`, and the account's own
  numeric ID, are now exempt; `/wp/v2/users` and any other user's ID are refused
  exactly as before (`blueworx_support_route_is_own_record()`). Closes #60.
- **Heartbeat no longer erases the audit log.** WordPress polls
  `admin-ajax.php` every 60 seconds from any open tab. The write block correctly
  refused it and logged a `blocked_write` each time, so an idle session filled the
  100-entry log in about 100 minutes — evicting `key_generated`, `access_opened`,
  `login` and any genuine refusal well inside the 24-hour window the log exists to
  document. Two changes: Heartbeat is deregistered for the support account so the
  request is never made, and a Heartbeat POST that does arrive is still refused but
  no longer recorded. Closes #59.

### Changed
- **Consecutive identical audit events collapse into one entry with a count**,
  rendered as `×N` against the newest occurrence. This is the general defence
  behind the Heartbeat fix: any chatty caller, known or not, now costs one row
  rather than one row per request, so the cap can no longer silently discard the
  evidence. Existing entries without a count render unchanged.

- **Lint cleanups, no behaviour change.** The unused `catch` binding in
  `assets/js/support-prompt.js` is dropped (optional catch binding), and two
  `phpcs` nits in `includes/support-access.php` are corrected — a missing blank
  line before a block comment, and a non-Yoda comparison in the `map_meta_cap`
  guard.

### Notes
- **CRLF line endings are repo-wide and deliberately left alone.** `phpcs` reports
  one `Generic.Files.LineEndings.InvalidEOLChar` per file across 33 PHP files
  because the committed blobs carry CRLF. Normalising is a whole-repo change that
  rewrites every line of every file, so it does not belong in a bug-fix diff;
  tracked separately for a `.gitattributes` + `git add --renormalize` commit.
- #20 (headless CORS has no deny path) was already fixed in 1.16.2 under #35 and
  is closed as such — core's `rest_send_cors_headers` is removed and replaced in
  `includes/rest/cors.php`, and `tests/headless-rest.spec.js` covers both
  namespaces.

## [1.43.0] - 2026-07-29

### Added
- **Copy Claude Code prompt** button on the support access panel
  (`includes/support-access.php`, `assets/js/support-prompt.js`). One click puts the
  whole connection prompt on the clipboard — site URL, custom login URL, REST bases, the
  `X-Blueworx-Support-Key` header, the browser key-exchange URL, and the read-only,
  24-hour-window and personal-data rules — so a session can be handed the setup in a
  single paste. On the render that generates a key the prompt carries the real key; on
  every later render it leaves a `<SUPPORT-KEY>` placeholder, because only the hash is
  stored and a stale key pasted into a session is worse than an obvious blank.
- The copy falls back to a selection copy where `navigator.clipboard` is absent, which is
  every site served over plain HTTP, and says on the button when a copy did not happen
  rather than reporting a success that did not occur.
- `tests/support-access.spec.js` — covers the button being absent with no key, the
  generated prompt carrying the fresh key and the site URL, the copy not submitting the
  form it sits inside, and the placeholder appearing on a later render.

## [1.42.1] - 2026-07-29

### Added
- `CONNECT_CLAUDE_CODE.md` — a copy-ready prompt that hands a Claude Code session
  everything it needs to connect to a live site over Support Access: the key, the site
  URLs, the REST header, the browser key-exchange URL, and the read-only, 24-hour-window
  and personal-data rules. Previously the same setup had to be explained by hand at the
  start of every session. Also a short FAQ entry in `readme.txt` pointing at it.
  Closes #61.
- The prompt ships with `<SITE-URL>` and `<SUPPORT-KEY>` placeholders rather than a real
  key. A support key is a live per-site credential shown once at generation, so writing
  one into a tracked file would commit a secret and pin the doc to a single site.

## [1.42.0] - 2026-07-29

### Added
- **On-page translation** (`includes/translate.php`, `assets/js/translate-widget.js`,
  `assets/css/translate-widget.css`) — a new `translate` feature, on by default, that
  adds a floating language switcher to the front end and translates the page in the
  visitor's own browser via the Chrome built-in Translator API. Replaces the Weglot
  plugin on BlueWorx-managed sites at no recurring cost: no API key, no per-page
  translation service call, and no translation stored on the server. The first use
  of a language pair does download an on-device model from Google in the visitor's
  browser.
- A new **Translation** settings section with an inline detail panel: which of the
  five offered languages (Arabic, Chinese, French, German, Spanish — ordered by
  English label, all on by default, English is always the source and never offered
  as a target) are enabled, button position (four corners), button label, and a
  per-site "never translate" list of CSS selectors. `code`, `pre`, `textarea`,
  `[translate="no"]` and `.notranslate` are always skipped, as is the widget itself.
- Text nodes and the `alt`, `title`, `placeholder` and `aria-label` attributes are
  translated; the visitor's choice is remembered in `localStorage` and re-applied on
  later pages; returning to the original language restores every string in place with
  no reload. Switching directly between two target languages fully restores the page
  to the source language before re-translating into the new one, so the page is never
  left half in one language and half in another. Content added after load (Elementor
  popups, AJAX) is picked up by a debounced `MutationObserver`.
- If a language fails to load (for example the on-device model download errors out),
  the widget, the page text, `html[lang]` and the remembered language are all rolled
  back together to the source language, so nothing is left in a mismatched state.
- `tests/translate.spec.js` — 22 Playwright tests covering the settings panel, the
  config payload, capability detection, translation and exclusions, restore and
  persistence, dynamic content, keyboard operation, switching directly between target
  languages, and a failed language load. The real on-device model cannot run in CI,
  so the specs drive a stubbed `window.Translator`.

### Notes
- **Chrome and Edge 138 or newer only.** In any other browser the switcher renders
  nothing at all rather than a control that cannot work.
- **Not an SEO feature.** There are no translated URLs and no `hreflang`; crawlers see
  the source language only. That is the deliberate trade for removing the licence cost.

## [1.41.1] - 2026-07-29

### Changed
- Restored the single `mcp__playwright__*` permission entry in the shared Claude
  settings. The two lines that had replaced it granted the same access but named
  an unsafe tool explicitly, which read as a deliberate security decision it was
  not. Closes #58.
- Machine-local Claude Code settings now live in an ignored
  `.claude/settings.local.json`, so a local permissions tweak can no longer ride
  into an unrelated commit. `.claude/settings.json` stays tracked for team policy.

### Added
- `npm run check:merge`, run after resolving a merge conflict and before pushing.
  It catches leftover conflict markers, content the base branch has that a
  resolution dropped, and specs that no longer parse — the three ways a hand
  merge has actually broken this repo. See `docs/merging-branches.md`.

## [1.41.0] - 2026-07-29

### Changed
- Profile screen refinement: name fields sit in pairs, cards carry explanatory
  subtitles and a defined border, spacing follows the design system, and editing
  another user gains a back link and a delete card.
- The profile redesign's section routing, card titles and subtitles now come
  from translated strings rather than matching English heading text, so the
  layout survives on non-English installs.

### Fixed
- Profile cards no longer force rows that core hides by class to display, which
  had left "Repeat New Password" and the weak-password confirmation visible on
  every profile page load.

## [1.40.0] - 2026-07-29

### Fixed
- Admin sidebar submenus appear on hover again. The expanded sidebar's own
  scroll container had been clipping them, so a parent item had to be clicked to
  reach its children. Keyboard focus opens them too.

## [1.39.0] - 2026-07-29

### Fixed
- The BlueWorx admin top bar no longer covers the block editor's toolbar. In
  normal mode the editor's skeleton is now offset below the bar; in fullscreen
  mode the bar and brand block hide themselves instead, since fullscreen also
  overlapped and is an explicit user request to clear the chrome away.
- The editor's controls, including Undo/Redo, were also partly hidden
  underneath the wider BlueWorx sidebar with the menu expanded, since core
  assumes a narrower sidebar than ours. The editor now aligns to our sidebar's
  actual width instead.

## [1.38.0] - 2026-07-28

### Added
- BlueWorx support access: a single per-site key that opens a read-only wp-admin
  and REST session for 24 hours, controlled by a toggle in the console. The
  managed account has no usable password, so there is nothing to rotate.
- Personal data is out of reach unless separately opted in for that session.
  This covers WooCommerce and SureCart order and customer screens as well as
  the WordPress users, comments and export screens.
- The console shows when an open support window closes, and how long is left.
- Audit log of every open, login, refusal, blocked write and key-authenticated
  REST request.
- Turning the "BlueWorx support access" toggle off now ends a live support
  session immediately, rather than only barring new logins.

### Fixed
- Support access could trash and permanently delete posts, pages, media and
  categories. WordPress drives those through nonce'd GET links and GET bulk
  forms, so the read-only block — which refused only non-GET methods — never
  saw them. The delete capabilities are now stripped from the support role, a
  write action arriving on any admin screen is refused by default, and the
  destructive meta capabilities are denied outright. Reading every one of
  those screens is unchanged.
- Support access could read a WooCommerce order or a single comment by ID with
  personal data access switched off. Only the list screens were denied; the
  single-item editors behind `post.php` and `comment.php` were not.
- Support access could deactivate or activate a plugin or theme through
  WordPress's nonce'd GET links. Reading the plugin and theme lists is
  unchanged; only the action is refused.
- Every BlueWorx admin form handler now requires a POST. A nonce presented in
  the query string previously satisfied `check_admin_referer()`, so the feature
  settings, menu settings, cache refresh, headless settings and headless invite
  handlers could all be triggered by a plain link — wiping settings, or minting
  an invite token.
- An unrelated account that merely happened to be named `blueworx_support` is
  no longer treated as the managed support account.
- Testing a correct key while the window is shut no longer counts toward the
  lockout, so an operator can no longer lock themselves out with their own key.
- Deactivating the plugin now removes the support account, role and key,
  instead of leaving the account behind with nothing enforcing read-only.

## [1.36.0] - 2026-07-23

### Removed
- **The public marketing site.** All front-end rendering — `includes/public/`
  (bootstrap, pages, render, assets, content, helpers), the entire `templates/`
  tree (9 page templates + 13 parts), the marketing-only assets
  (`assets/css/public.css`, `assets/js/public-nav.js`,
  `assets/js/public-widgets.js`, `assets/img/`), and the marketing/public
  Playwright specs — has been removed. This reverts the plugin to a pure
  enhancement plugin (site hardening, cache refresh, admin/profile
  enhancements, and the headless REST layer).
- The `public_site` feature toggle (`includes/features.php`) and its activation
  (`blueworx_public_activate` — page install) / deactivation
  (`blueworx_public_deactivate` — front-page restore) hooks in the main plugin
  file.
- `uninstall.php` no longer deletes `blueworx_public_prior_front` /
  `blueworx_public_page_ids` (those options belong to the marketing plugin now).
- `templates/` dropped from the release-zip allowlist (`scripts/build-zip.mjs`),
  and the now-unused `cacheBustExempt()` test helper removed.

The marketing site now lives in its own standalone plugin, `blueworx-site`
(BlueWorx | Site), in the `bluegroup_project_blueworx` repo. `blueworx-fonts.css`
and `assets/fonts/` are retained here — the admin theme uses them.

## [1.35.1] - 2026-07-23

### Fixed
- Activation now repairs a Toolbox tool page whose parent link went stale (e.g.
  the Toolbox page was trashed and recreated with a new ID), so its
  `/toolbox/<slug>` permalink and the Site Protection path check keep working.

### Changed (internal)
- Consolidated the `restoreAll()` test helper into `tests/helpers.js` (was
  duplicated across specs), added a robust test that the AI-pipeline console
  cycles when motion is allowed, and corrected stale "placeholder" test titles
  that now describe the real Plan 3 widgets.

## [1.35.0] - 2026-07-22

### Added
- Plan 3b showcase widgets (progressive enhancement, `prefers-reduced-motion` aware):
  interactive Home feature tabs (SVG chart swap), the AI-page animated AI demo
  (prompt → code → site loop) and the cycling AI pipeline console, plus a single-open
  FAQ accordion enhancement on the Contact/Pricing/Toolbox pages.

### Fixed
- Accessibility: the Contact page's phone/WhatsApp/email cards are now real, focusable
  links (`tel:` / `https://wa.me/` / `mailto:`) instead of inert `<a>` elements.

## [1.34.0] - 2026-07-22

### Added
- Plan 3a: interactive billing toggle, pricing calculator and savings calculator (progressive enhancement).

## [1.33.0] - 2026-07-22

### Added
- **Tool-detail pages (`templates/pages/single-tool.php`)**, registered at `/toolbox/<slug>`
  for all 12 Toolbox tools — completing Task 9. `blueworx_public_pages()` now generates the
  12 nested entries from `blueworx_content_tools()` (keyed by full path, e.g.
  `toolbox/surecart`), rather than hand-transcribing them, so the tool list stays a single
  source of truth. `blueworx_public_install_pages()` creates them as real, nested WordPress
  Pages (`post_parent` set from the already-mapped `toolbox` page) on activation, idempotently.
  Each page renders a two-column hero (breadcrumb, badge, heading, tagline, CTAs, a
  `glass-card` with the bundled 58px favicon, an optional "Popular" pill, and the tool's 6
  features as check rows), a `#tool-why` section repeating those 6 features as `.svc` cards,
  and a related-tools grid (first 4 other tools) via the shared `toolbox-grid` part.
- New `blueworx_public_current_page()` accessor (`includes/public/pages.php`) — returns the
  matched page-registry entry for the current request, factored out of
  `blueworx_public_current_template()` so a template can read its own registry data (e.g. the
  tool slug `single-tool.php` needs) without re-deriving ID→slug→registry resolution itself,
  and without reading the queried post's own (rename-able) `post_name`.
- `tests/marketing-single-tool.spec.js` — the toolbox archive still rendering 12 cards, a
  popular tool's name/tagline/pill/6-features-twice, a non-popular tool showing no pill, an
  unknown slug 404ing, and the Site Protection exemption reaching a nested tool page.

### Fixed
- **Site Protection exemption for nested pages** (`blueworx_public_is_owned_request_path()`).
  The allowlist previously built each owned page's base path from
  `get_post_field( 'post_name', $id )` — the page's own bare slug (e.g. `surecart`), which is
  wrong for a page nested under `/toolbox/` and would have silently dropped the exemption for
  every tool page the moment Site Protection was turned on. It now uses `get_page_uri()`,
  which returns the full hierarchical path (`toolbox/surecart`), with the registry key itself
  (already a full path) as the pre-activation fallback.

## [1.32.0] - 2026-07-22

### Added
- **Pricing page (`templates/pages/pricing.php`)** and **Toolbox archive page
  (`templates/pages/toolbox.php`)**, registered as `pricing` and `toolbox`. Both render a
  `.pb-tall` hero with the billing toggle, the plan cards (via a new shared `plan-cards`
  part, pulled up to overlap the hero), the logos band, a feature comparison table, a
  calculator, and a static FAQ — Toolbox additionally has the `#savings` section and the
  toolbox grid.
- **`plan-cards` template part** — renders the plan grid from a plans array, each card
  carrying `data-price-m` / `data-price-a` so Plan 3 can swap monthly/annual prices; the
  button class is derived from the plan's `feat` flag (dark vs outline). Billing toggle
  and calculators are Plan 3 widgets: the toggle renders its real `.bill-toggle` markup
  (Monthly selected), the calculators render labelled placeholders.
- **`toolbox-grid` template part**, extracted from the home page's inline toolbox band so
  Home and Toolbox share one implementation (Home refactored to use it).
- `tests/marketing-plans.spec.js` — plan cards, price data attributes, comparison tables,
  the billing toggle, the calculator placeholders, the toolbox grid, and active nav links.

## [1.31.0] - 2026-07-22

### Added
- **AI Powered page (`templates/pages/ai.php`)**, registered as `ai`, rendering the five
  sections from `app/ai/page.tsx`: the two-column ai-hero with the Claude badge, "The Full
  Flow", "Model Guidance" (four model cards), "Approved Stack" (ten chips) and "What We
  Build" (five offering cards). The AiDemo and AiPipeline interactive widgets are Plan 3,
  so labelled placeholders stand in for them. This page's `.ai-*` styles were already in
  public.css.
- `tests/marketing-ai.spec.js` — the Claude hero, four model cards, ten stack chips, five
  offerings, the two Plan 3 placeholders, and the active nav link.

## [1.30.0] - 2026-07-22

### Added
- **Work page (`templates/pages/work.php`)**, registered as `work`, rendering the four
  sections from `app/work/page.tsx`: a two-column tech-hero with a `results.log`
  glass-card, a `.work-grid` of six non-linked project cards (the `work-card` part in
  plain `<div>` mode), a stats-band, and a testimonials section using Work's own three
  testimonials and heading ("Partners Who'd Recommend Us") rather than the shared
  homepage reviews. Reuses tech-hero, glass-card, work-card, stats-band and testimonials
  parts throughout.
- `tests/marketing-work.spec.js` — the two-column hero, six non-linked cards, the stats
  band, the Work-specific testimonials, and plugin-hosted images.

## [1.29.0] - 2026-07-22

### Added
- **Contact page (`templates/pages/contact.php`)**, registered as `contact`, rendering
  the five sections from `app/contact/page.tsx`: a centered 780px tech-hero with two
  status pills, the contact grid (form column + illustration), the dark contact-cards
  band (call / WhatsApp / email), a static FAQ section, and testimonials.
- **Contact form as a shortcode.** The form column renders the shortcode named by the
  `blueworx_contact_form_shortcode` option (also filterable) — and only that single
  configured value is passed to `do_shortcode()`, never arbitrary input, so it can never
  be coerced into running another shortcode. Empty by default, in which case a clearly
  labelled placeholder stands in. This is the forms-as-shortcodes approach: point the
  option at a form plugin's shortcode to show the form.
- The FAQ list renders as native `<details>` (fully functional with no JavaScript) until
  Plan 3 upgrades it to the animated accordion.
- `tests/marketing-contact.spec.js` — hero, contact grid, three cards, five `<details>`
  FAQs, testimonials, the placeholder-when-unconfigured behaviour, and that Contact
  (not a nav item) marks nothing active.

## [1.28.0] - 2026-07-22

### Added
- **Services page (`templates/pages/services.php`)**, registered as `services` in
  `blueworx_public_pages()`, rendering all five sections from `app/services/page.tsx`:
  a two-column tech-hero with a metrics glass-card, Service 01 (four feature cards plus
  the bespoke hand-authored analytics/browser panel with its `#fsg` gradient sparkline,
  ported verbatim), How It Works (proc-grid part), the dark Service 02 section listing
  the Digital Toolbox tools with **bundled** favicons (`assets/img/tools/`, no Google
  requests), and testimonials. Reuses the `tech-hero`, `glass-card`, `proc-grid` and
  `testimonials` parts; the analytics panel and two-column layout are composed inline.
- `tests/marketing-services.spec.js` — asserts the two-column hero, the `#fsg` sparkline,
  the four process steps, plugin-hosted (not Google) tool favicons, and the active nav link.

## [1.27.0] - 2026-07-22

Task 3 of the marketing-pages migration (`marketing-pages`): the About page.

### Added

- **About page (`templates/pages/about.php`)**, registered as `about` in
  `blueworx_public_pages()`, rendering all five sections from
  `app/about/page.tsx` in source order: a centered tech-hero, "Why BlueWorx"
  (`.af-wrap about-why`, copy column + four plain svc-card parts), a
  stats-band part (5.0★ Google Rating, 82+ Projects Completed, 100k + Revenue
  Handled, 2K + Toolbox Value), "Our Team" (three team-card blocks — no
  shared part exists for these, so they stay inline), and "Client Success
  Stories" (three linked work-card parts on a tinted background). 100%
  static, entirely composed from existing template parts.

## [1.26.1] - 2026-07-22

### Changed
- `tech-hero` template part: the centered variant now accepts `max_width` (default
  820, About's value) and `extra_class` (e.g. `pb-tall`). Review found it hardcoded
  About's exact pixel values, which Contact (780px) and Pricing (`pb-tall`) could not
  reuse — they would have had to bypass the shared part. Backward-compatible; the
  defaults reproduce About's hero unchanged.

## [1.26.0] - 2026-07-22

Task 2 of the marketing-pages migration (`marketing-pages`): the real Home
page plus the shared template parts every later marketing page will reuse.

### Added

- **Home page (`templates/pages/home.php`)** replaces the Plan 1 stub,
  rendering all nine sections from `app/page.tsx` in source order: the
  home-hero (bespoke timeline glass-card visual + scrolling service ticker),
  "What We Do" (`.svc2`, two svc-card parts), the logos band, Selected Work
  (three work-card parts), a labelled FeatureTabs placeholder (Plan 3), How
  We Work (a proc-grid part), the Ongoing Partnership split section, the
  Toolbox band (driven by `blueworx_content_tools()`), and testimonials (a
  testimonials part fed `blueworx_content_reviews()`).
- **Eight shared template parts (`templates/parts/`)** so Tasks 3–9 reuse
  rather than duplicate: `tech-hero`, `glass-card`, `proc-grid`, `work-card`,
  `stats-band`, `testimonials`, `logos-band`, `svc-card`. Each takes a
  documented `$vars` contract designed around every known source usage, not
  just Home's — e.g. `work-card` renders a `<div>` instead of an `<a>` when no
  `href` is given (Work's plain, non-linked project cards), and `tech-hero`
  supports both the centered layout (About) and a `centered => false` mode a
  two-column hero (Services, Work) composes around.
- **FeatureTabs placeholder.** A `.bw-plan3-placeholder[data-widget="feature-tabs"]`
  static block stands in for the Plan 3 interactive widget so the page stays
  whole; Plan 3 replaces it with the real component.
- `tests/marketing-home.spec.js` — asserts the home-hero, `.svc2`, `.proc-grid`,
  the Toolbox band's 12 bundled-favicon cards, the Ongoing Partnership split
  section, and the testimonials all render, and that the FeatureTabs region
  renders the labelled placeholder rather than an empty gap.

## [1.25.1] - 2026-07-22

### Changed
- Re-encoded the photographic marketing images as JPEG instead of palette-quantised
  PNG. The 64-colour quantisation used to hit the size target left visible dithering
  speckle on `feature-image-2` (flagged in review). The five photographic feature
  images (`feature-image-1..4`, `fig-collab`) now ship as clean mozjpeg at ~82 quality,
  each well under target and totalling ~450KB rather than the PNGs' larger, dithered
  output. Their filenames change from `.png` to `.jpg`; `hero-image.png` stays PNG
  (it carries transparency).

## [1.25.0] - 2026-07-22

Task 1 of the marketing-pages migration (`marketing-pages`): the content data
layer every marketing page template will draw from, plus web-sized bundled
images. No page markup yet — see later releases for the pages themselves.

### Added

- **Content data layer (`includes/public/content.php`).** Six accessors —
  `blueworx_content_tools()`, `blueworx_content_tool( $slug )`,
  `blueworx_content_solo_prices()`, `blueworx_content_toolbox_plans()`,
  `blueworx_content_retainer_plans()`, `blueworx_content_faqs()`,
  `blueworx_content_reviews()` — port the marketing site's copy and pricing
  (12 Toolbox tools with 6 features each, solo prices, subscription and
  retainer plans, pricing FAQs, homepage reviews) verbatim from the front-end
  design export's `lib/data.ts`. Each accessor's return value is filterable
  via `apply_filters( 'blueworx_content_<name>', $array )` so a later cycle
  can override the content without editing this file. The source's `btn`
  raw-CSS-class field is dropped from plan data — templates choose their own
  button classes.
- **Bundled, web-sized marketing images** (`assets/img/`):
  `about-illustration.jpg`, `contact-illustration.jpg`, `hero-image.png`,
  `feature-image-1..4.png`, `fig-collab.png`. Resized to a 1600px max long
  edge (never upscaled) and recompressed with `sharp-cli` (a one-shot `npx`
  tool, not an added dependency); every file ships under 160KB, versus up to
  1.5MB in the source export.

## [1.24.1] - 2026-07-22

### Security
- **Site Protection exemption converted from a denylist to an allowlist.** The
  public marketing pages are exempt from Site Protection, which is decided at
  `init` from the request path. The prior denylist of content-selecting query
  vars leaked: `?category_name=`, `?taxonomy=&term=`, `?rest_route=`,
  `?attachment=`, `?embed=` and `?post_format=` were never listed, so a
  logged-out visitor could reach post archives and the REST API through the
  `/` exemption. An owned marketing page never legitimately carries a
  content-selecting query var, so the request is now exempt only when every
  query parameter is on a strict allowlist of tracking params
  (`utm_*`, `fbclid`, `gclid`, `mc_cid`, `mc_eid`), filterable via
  `blueworx_public_allowed_query_params`. Anything else — known or not — is
  gated by default.

## [1.24.0] - 2026-07-22

Final pre-merge fix wave for the public front-end layer (`public-rendering-foundation`).

### Security

- **Site Protection could be bypassed on "/" via a content-selecting query var.** Once
  activation points the front page at the plugin's Home page, `blueworx_public_is_owned_request_path()`
  (`includes/public/pages.php`) exempted path `/` from Site Protection unconditionally — but
  WordPress still honours query vars on `/`, so `/?p=<id>`, `/?s=<term>`, `/?feed=rss2`, `/?cat=1`
  etc. shared that same "owned" path and were exempted right along with it, letting a logged-out
  request reach WordPress's normal query handling instead of the 403 wall (a full content leak for
  search results and feeds). The exemption now checks the request's query string against
  WordPress's own content-selecting public query vars (`p`, `page_id`, `s`, `feed`, `cat`, `tag`,
  `author`, `paged`, `post_type`, `preview`, …) and refuses the exemption if any are present.

### Fixed

- **Activation could silently replace the site owner's existing homepage, with no way back.**
  `blueworx_public_install_pages()` overwrote `show_on_front`/`page_on_front` on every activation
  with no memory of the prior value. It now skips the takeover entirely when the front page is
  already pointed at a page this plugin does not own, snapshots the prior value once when it does
  take over (`blueworx_public_prior_front`), and a new deactivation hook restores that snapshot —
  but only while the front page is still actually this plugin's Home page. `uninstall.php` also now
  removes `blueworx_public_prior_front` and `blueworx_public_page_ids`.

### Theme independence

- `wp_body_open()` is now called on every plugin-rendered page, so analytics/tag-manager/
  cookie-consent/skip-link plugins that hook it keep working.
- The plugin now declares `add_theme_support( 'title-tag' )` itself, so `<title>` output no longer
  depends on whether the active theme opted in.
- The active theme's own front-end stylesheet is now dequeued/deregistered on plugin-owned pages,
  so a theme with bare-element or `!important` rules can no longer visibly change the plugin's
  pages. The theme-independence test now also asserts no theme stylesheet element is present in
  `<head>` on an owned page.

### Fixed

- **`templates/parts/nav.php` hrefs pointed outside the site on a subdirectory WordPress install.**
  Every nav link and the logo now build their href with `home_url()`, matching `footer.php`.
- **12 third-party requests to Google on every page load.** `templates/parts/nav.php` rendered each
  toolbox tool's logo via `https://www.google.com/s2/favicons?domain=...`. The 12 favicons are now
  bundled at `assets/img/tools/<slug>.png` and served from the plugin itself.
- **The Toolbox mega panel and About Us dropdown were keyboard-unreachable.**
  `assets/js/public-nav.js` only opened either panel on `mouseenter`/`mouseleave`. `focusin`/
  `focusout` handlers (plus a `:focus-within` CSS fallback) now open them for keyboard users too.

## [1.23.0] - 2026-07-21

### Fixed
- **Site Protection could be left ON after the public-site test suite ran.**
  `tests/public-site.spec.js`'s "a plugin-owned public page stays reachable
  while logged out with Site Protection on" test restored its two mutated
  toggles (`blueworx_frontend_protection_enabled` and the `site_protection`
  feature flag) as one monolithic `finally` block sharing a single Save
  Changes click — unlike the other two Site Protection tests in the same
  file, which already used the `restoreAll()` helper (introduced for exactly
  this) to isolate independent restore steps. If the first toggle's restore
  threw (e.g. a `.notice-success` assertion timing out), the second toggle
  was never set back and Save was never clicked, leaving Site Protection ON
  for the rest of the suite — and for a real visitor — since the top-level
  feature flag gates all frontend/backend enforcement. That test's cleanup
  now uses `restoreAll()` with two independent, self-contained round trips
  (matching the other two tests), so a failure restoring one toggle can
  never skip restoring the other.

### Added

### Added
- **Site navigation.** `templates/parts/nav.php` and `assets/js/public-nav.js`
  port `Nav.tsx`: the logo, primary links (Home, Services, Toolbox with its
  mega panel, Pricing, About Us with its dropdown, AI Powered), the CTA pair,
  and the mobile hamburger menu. Active-state matching is exact for `/` and
  prefix-based otherwise. Because a plain document cannot mount an element on
  hover the way React does, the mega panel, the About Us dropdown and the
  mobile menu are all rendered unconditionally and toggled with an `.open`
  class instead — `public-nav.js` opens each dropdown immediately on
  `mouseenter` and closes it after a 300ms grace period on `mouseleave` (two
  independent timers, matching the source's `megaT`/`aboutT` refs), so moving
  the cursor from a trigger into its panel does not snap it shut. The same
  file also ports the source's rAF-throttled hide-on-scroll-down /
  reveal-on-scroll-up behaviour (`nav-scrolled` past 8px, `nav-hidden` past
  160px while moving down more than 4px, suppressed while the mobile menu is
  open) and the mobile menu's body scroll lock, keeping `aria-expanded` in
  sync with the hamburger's real state. `includes/public/assets.php` enqueues
  the new script; `assets/css/public.css` gains the `.mega-panel`/
  `.about-panel` rules the always-present markup needs plus
  `text-decoration: none` on the sign-in links (rendered as `<a>` here, `<span>`
  in the source).

## [1.21.1] - 2026-07-21

### Fixed
- **Footer logo depended on the active theme.** `templates/parts/footer.php`
  resolved the logo via `get_theme_mod( 'custom_logo' )` — a per-theme
  Customizer setting that changes or vanishes on theme switch, undermining
  the public layer's core guarantee that output is identical regardless of
  which theme is active. The plugin now bundles its own brand asset at
  `assets/img/logo.png` (matching what the source front-end ships at
  `/assets/logo.png`), served via `BLUEWORX_LABS_URL`, with the same
  `filter:brightness(0) invert(1)` treatment and the same graceful
  site-name text fallback if the bundled file is ever absent. The
  `get_theme_mod()` call is removed entirely.

## [1.21.0] - 2026-07-21

### Added
- **Shared public helpers: icons and decorative blobs, plus the CTA band and
  footer template part.** `blueworx_icon()` and `blueworx_blob()`
  (`includes/public/helpers-public.php`) port the 21 inline SVG icons and the
  decorative background blobs from the front-end design's `lib/icons.ts` and
  `CtaBand.tsx`. The icon renderer wraps every `<svg>` in a `<span
  data-ic="...">` sized to fill it at 100% — the span is what `public.css`
  sizes at ten separate selectors, so a bare `<svg>` would collapse icon
  sizing sitewide. `templates/parts/footer.php` ports `CtaBand.tsx` and
  `Footer.tsx`: the CTA band renders as a sibling of `<main>`, before the
  footer, on every page; the footer reproduces the source's `.fb`/`.fcol`
  /`.fnews`/`.fbot` structure, keeping the social and Blog/Resources/Careers
  links as non-links (no `href` in the source) and the newsletter form inert
  (markup only, no handler — a form plugin shortcode replaces it later).
  `home.php`'s existing call to `blueworx_public_part( 'parts/footer.php' )`
  now resolves.

## [1.20.1] - 2026-07-21

### Fixed
- **Fragile test cleanup in `tests/public-site.spec.js`.** Two tests
  (`"/" is not exempt from Site Protection when show_on_front is not a
  plugin-owned page` and the slug-collision test) restored multiple
  independent pieces of mutated global state — Site Protection toggles,
  show_on_front/page_on_front, and page slugs — as sequential steps inside a
  single `finally` block. A throw partway through (e.g. a `.notice-success`
  assertion timing out) skipped every restore step after it, risking the site
  root being left serving the posts index or a page stuck on a temporary
  slug for the rest of the suite and any real visitor. A new `restoreAll()`
  helper runs each restore step in isolation via its own try/catch,
  collecting errors and re-throwing them together at the end, so every
  mutated piece of state is restored regardless of which step fails, while a
  genuine cleanup failure still fails the test loudly. Applied to both named
  tests plus the sibling `a renamed plugin-owned page stays exempt from Site
  Protection` test, which had the same underlying fragility despite already
  ordering its restores correctly.

## [1.20.0] - 2026-07-21

### Added
- **Theme-independent document shell and template routing.** The public site
  now renders its own complete HTML document — `blueworx_public_document_open()`
  / `blueworx_public_document_close()` (`includes/public/render.php`) call
  `wp_head()` / `wp_footer()` but deliberately never `get_header()` /
  `get_footer()`, so the site looks identical no matter which theme is active
  or where it ends up hosted. `template_include` is now hooked
  (`blueworx_public_template()` in `includes/public/pages.php`) to hand
  rendering of owned pages to the plugin's own templates, and a
  `templates/pages/home.php` placeholder (via the new `blueworx_public_part()`
  helper) is the first template to actually render. Activation now also
  points `show_on_front` / `page_on_front` at the Home page, so `/` is
  WordPress's actual front page rather than the default posts index.

## [1.19.3] - 2026-07-21

### Fixed
- **Slug-collision hijack in `blueworx_public_current_template()`.** After a
  rename, the map's `home` key keeps pointing at the renamed page's ID. If a
  different, unrelated page later takes the now-free `home` slug, its ID is
  not in the map, so `array_search()` fails and the code fell back to
  matching the static registry purely by slug — rendering the plugin's Home
  template over a page it does not own. The fallback now only resolves by
  slug when the map has no entry for that slug at all; if the slug is
  already claimed by a different mapped ID, the page is correctly reported as
  not owned. The fresh-install path (no map entry yet) is unaffected.

## [1.19.2] - 2026-07-21

### Fixed
- **The two ownership checks could disagree.** `blueworx_public_is_owned_request_path()`
  (init-time, drives the Site Protection exemption) and
  `blueworx_public_is_owned_page()` (query-time, drives rendering and asset
  enqueue) were meant to describe the same notion of "owned", but the
  path-based check had two gaps:
  - It unconditionally treated `/` as owned. `/` only becomes an owned page
    once WordPress's front page is pointed at one of the plugin's pages
    (Task 4) — until then it's WordPress's default posts index, and
    exempting it from Site Protection weakened the gate at the site root.
    Now `/` counts as owned only when `show_on_front` is `'page'` and
    `page_on_front` is one of the IDs in `blueworx_public_page_ids`.
  - It compared the request path against the plugin's *static* slugs, so
    renaming an owned page's slug kept the query-time check correct but made
    the path check — and so the Site Protection exemption — stop recognising
    it, wp_die()-ing a real visitor to a page the plugin still owns. Now the
    path check resolves each mapped page's slug via `get_post_field()`
    (falling back to the static slug only for a page not yet in the map),
    matching how `blueworx_public_current_template()` already resolves
    renames at query time.

  Site Protection's default behaviour is unchanged — only which requests are
  exempt from it. Added two tests to `tests/public-site.spec.js` covering
  both gaps.

## [1.19.1] - 2026-07-21

### Fixed
- **Site Protection exemption ran before the query.** The exemption that keeps
  plugin-owned public pages reachable when Site Protection is on is hooked to
  `blueworx_site_protection_applies`, which fires from
  `blueworx_intercept_requests()` on `init` priority 1 — before the main
  WordPress query has run. The exemption previously called
  `blueworx_public_is_owned_page()`, which depends on `is_page()` /
  `get_queried_object()`; both are unreliable that early and always reported
  "not a page", so the exemption never fired. Turning Site Protection on
  would have `wp_die()`'d every logged-out visitor to the plugin's own
  marketing pages. Added `blueworx_public_is_owned_request_path()`, an
  `init`-safe check that compares the normalized request path against the
  plugin's page registry (mirroring `blueworx_is_custom_login_request_path()`
  in `includes/login-security.php`), and pointed the exemption at it.
  `blueworx_public_is_owned_page()` is unchanged and remains correct for its
  existing query-time callers.

## [1.19.0] - 2026-07-21

### Added
- **Plugin-owned page registry.** `blueworx_public_pages()` declares the pages
  this plugin renders (slug ⇒ title/template — one entry, `home`, for now);
  `blueworx_public_install_pages()` creates a real WordPress Page for each one
  on activation so menus, SEO plugins, and later content editing all work
  normally. Idempotent and safe to run on every activation: pages are matched
  by their previously-stored ID first, then by slug, so a page the user has
  renamed or moved is recognised rather than duplicated.
- **Page-ownership lookup for later rendering.** `blueworx_public_is_owned_page()`
  and `blueworx_public_current_template()` resolve the current request against
  the registry to an absolute template path (or `null`), which `template_include`
  will use in a later task to take over rendering from the active theme.
  `includes/public/assets.php` now gates its stylesheet enqueue on this real
  ownership check instead of the `is_front_page()` placeholder from 1.18.1.

### Security
- **Site Protection now exempts plugin-owned public pages.** Site Protection's
  frontend gate (`includes/login-security.php`) can `wp_die()` logged-out
  visitors — appropriate for a site still in progress, but it would also take
  down the deliberately-public marketing pages this plugin renders. A new
  `blueworx_site_protection_applies` filter (applied only around that gate, no
  other behaviour changed) lets `blueworx_public_exempt_from_site_protection()`
  exclude owned pages from the block. Unhooked — i.e. with `public_site` off —
  this is a no-op and Site Protection behaves exactly as before.

## [1.18.3] - 2026-07-21

### Fixed
- **Two more bare element selectors survived the 1.18.2 scoping pass by
  hiding inside mixed selector lists.** `.h1, .h2, h3, h4 { text-wrap:
  balance; }` and `.lead, p, .ttext, .plan-desc, .fd-sub { text-wrap:
  pretty; }` each carried a bare `h3`/`h4`/`p` alongside already-scoped
  classes — the bare `p` in particular restyled every paragraph the active
  theme rendered. Both rules are now `.h1, .h2, .bw-page h3, .bw-page h4`
  and `.lead, .bw-page p, .ttext, .plan-desc, .fd-sub`. The regression test
  in `tests/public-site.spec.js` only flagged rules that were entirely
  bare, which is exactly why these survived it; it now inspects every
  comma-separated part of every selector list and flags any bare part on
  its own.

## [1.18.2] - 2026-07-21

### Fixed
- **`assets/css/public.css` leaked bare element selectors document-wide.**
  The stylesheet was ported from a standalone front-end where it owned the
  whole document, and was scoped to `.bw-page` for the `*` reset and `body`
  rule — but five bare element selectors (`img`, `button`, `nav` twice
  including its responsive variant, `footer`) were missed and still matched
  document-wide. In WordPress that restyled the admin bar and the active
  theme's own markup — worst of all, a theme's `<nav>` picked up a full
  96px sticky reskin. All five are now scoped under `.bw-page`. Added a
  regression test (`tests/public-site.spec.js`) that reads the stylesheet
  from disk and fails if any unscoped bare element selector reappears.

## [1.18.1] - 2026-07-21

### Added
- **Public asset pipeline.** `assets/css/public.css` ports the headless
  front-end's `globals.css` (marketing sections only — the client portal and
  auth forms stay out of scope), scoped under `.bw-page` so its reset can't
  reach the admin bar or block styles. `blueworx_enqueue_public_assets()`
  enqueues it alongside the existing self-hosted font stylesheet on
  `wp_enqueue_scripts`, versioned via the existing asset-version helper so a
  CSS change reaches visitors immediately instead of waiting on a stale
  browser cache. Gated on `is_front_page()` until Task 3 adds real page-
  ownership detection.

## [1.18.0] - 2026-07-21

### Added
- **Public front-end module skeleton.** New `includes/public/*.php` layer
  (bootstrap, pages, render, assets, helpers) and a `templates/` directory,
  gated behind a new `public_site` feature flag under **BlueWorx →
  Enhancements → Appearance**. Nothing renders yet — this lays the groundwork
  for the plugin to serve its own marketing site independently of the active
  theme. On by default, matching the "absent option means enabled" convention
  so a fresh install ships ready.
- `templates/` added to the release zip allowlist (`scripts/build-zip.mjs`) so
  the module has somewhere to ship its markup once later tasks populate it.

## [1.17.0] - 2026-07-21

### Added
- **`POST blueworx/v1/render` — shortcodes that actually work on a headless
  front-end.** A shortcode's markup already reached the front-end via
  `content.rendered`, but its CSS and JS never did: plugins enqueue those on
  `wp_enqueue_scripts`, which does not fire for a REST request. Anything
  interactive therefore arrived as inert markup or an empty container. This
  endpoint renders the shortcode and returns the assets it enqueued —
  `{ html, shortcodes, styles[], scripts[] }` — including `wp_localize_script`
  data and inline before/after scripts, so the front-end can load them alongside
  the markup.
  - Returns the **full dependency closure in load order**, not just the handles
    enqueued directly. WordPress resolves dependencies at print time, which never
    happens here, so a front-end given only the enqueued handle would load a
    script whose jQuery dependency was missing and it would throw.
  - Relative asset URLs are made absolute, since the front-end is on another
    origin and could not resolve them otherwise.
  - `with_global_enqueue: true` also fires `wp_enqueue_scripts` for plugins that
    register assets there rather than in the shortcode callback. Off by default,
    because it also pulls in everything the theme and other plugins enqueue.
- **"Renderable shortcodes" setting** under BlueWorx → Headless.

### Security
- The render endpoint **fails closed**: the allowlist is empty by default and the
  endpoint refuses everything until tags are named explicitly. A shortcode is a
  PHP function, so an unrestricted `do_shortcode()` on public input would be
  remote code execution by proxy.
- A request mixing allowlisted and non-allowlisted tags is refused whole, rather
  than returning partial markup that looks like a success.
- Rate limited (30 per 5 minutes per IP), and output buffering prevents anything
  a callback echoes from corrupting the JSON body.

### Notes
- **This is a workaround, not a cure.** Shortcodes depending on `wp_head`, the
  loop, or inline output outside the enqueue system may still misbehave, and each
  third-party plugin is its own compatibility question. `HEADLESS_INTEGRATION.md`
  §6.3 documents the contract and the limits.
- Closes #25. The front-end side is `bluegroup_project_blueworx#11`.

## [1.16.4] - 2026-07-21

### Fixed
- **Two tests that could never have caught a regression.**
  - The critical-CSS assertion used `toContainText` on a `<style>` element.
    A `<style>` renders no text, so it always saw `""` — the assertion could not
    pass regardless of the CSS. Now asserts on `textContent`.
  - The unmapped-menu test demanded exactly one Custom Content group, which only
    exists when a third-party plugin registers a top-level menu. It therefore
    tracked what happened to be installed rather than the plugin's behaviour, and
    failed on a clean site where rendering no group is correct. Now asserts the
    invariant (never more than one) and keeps the real guard: Site holds only
    mapped core menus.
- **A third bug the above was masking.** That test's Site-group allowlist never
  included `nav-menus.php`, even though 1.15.0 deliberately promoted Menus into
  the Site group. The line could not fail because the assertion before it always
  threw first.

### Changed
- `retries: 1` in the Playwright config. The local harness serves WordPress from
  PHP's single-threaded built-in server, and a sign-in occasionally times out
  under load. One retry absorbs that; a genuine failure still fails twice and is
  reported as failed rather than flaky.

### Notes
- Full suite against the harness: 41 passed, 2 skipped, 1 flaky, 0 failed.
- Fixes #37.

## [1.16.3] - 2026-07-21

### Fixed
- **Sidebar rows are a uniform height again.** A row carrying a count badge
  rendered 1px taller than one without. `.wp-menu-name` is a flex container, so
  the row is as tall as its tallest item — and the badge's `line-height: 1.5`
  plus `2px` vertical padding came to 20.5px against the label's 18.2px line box.
  The badge is now a fixed 18px box with centred content, which cannot drive the
  row height. Visually it is a slightly smaller pill; the rows are level.

### Notes
- Found by #24's harness. Fixes #36.

## [1.16.2] - 2026-07-21

### Security
- **The CORS allowlist now actually restricts origins.** WordPress core echoes
  any `Origin` back with `Access-Control-Allow-Credentials: true` on REST routes.
  Core's handler ran first and this plugin's allowlist only ever *declined to add*
  headers — it never removed core's — so any site could make credentialed
  cross-origin calls to `blueworx/v1` and `wp/v2` and read the responses. That
  matters here because the refresh cookie is deliberately `SameSite=None`.
  Core's handler is now removed and replaced, so a disallowed origin receives no
  `Access-Control-Allow-Origin` at all.
- **Fails closed.** An empty allowlist now denies every cross-origin caller
  instead of effectively allowing everyone.
- `Vary: Origin` is sent whether or not the origin is allowed, so a shared cache
  cannot serve one origin's response to another.

### Changed
- CORS now covers `wp/v2` as well as `blueworx/v1`, because the headless
  front-end reads content bodies from `wp/v2` and simply removing core's handler
  would otherwise have broken it. Namespaces are filterable via
  `blueworx_headless_cors_namespaces`.
- Allowed responses now also send `X-WP-Nonce` in `Access-Control-Allow-Headers`
  and expose `X-WP-Total`, `X-WP-TotalPages` and `Link`, matching what core used
  to provide so pagination keeps working.

### Notes
- **Breaking for other REST namespaces.** Third-party namespaces outside
  `blueworx/v1` and `wp/v2` no longer receive CORS headers, since core's
  permissive handler is gone. Add them via the filter if an integration needs
  them — deliberately opt-in rather than open by default.
- Found by #24's harness the first time the suite ran for real. Fixes #35.

## [1.16.1] - 2026-07-21

### Changed
- **CI now actually tests this plugin.** `ci.yml` switches to
  `use_local_wordpress: true`, so each run provisions a disposable WordPress on
  the runner (PHP + SQLite, no Docker) and tests against that instead of a
  placeholder staging URL. Also passes `wp_login_path: admin_login`, because this
  plugin moves the login screen off `wp-login.php` and every admin spec would
  otherwise fail at the sign-in step.
- Removed `allow_zero_tests`. It was a suppressed alarm added in 1.15.1 to keep
  the repo unblocked; with a real test target the gate can do its job.
- `.wp-test/` added to `.gitignore` — it holds a full WordPress tree.

### Notes
- For context on what this changes: the suite had been skipping **all 40 tests**
  in CI since it was written, reporting green while asserting nothing. Running it
  for real surfaced #35, #36 and #37.
- Run the same instance locally:
  `node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up --plugin .`
  See `docs/wordpress-test-harness.md` in the foundation.

## [1.16.0] - 2026-07-20

### Added
- **Client Roles.** Three assignable roles for client accounts — **Admin —
  Business Owner**, **External Dev** and **Content Editor** — that show or hide
  whole backend areas, gated behind a new *Client Roles* toggle on BlueWorx >
  Enhancements (on by default). Areas are hidden by capability where possible and
  by the plugin's existing sidebar groups for third-party menus:
  - *Business Owner* — everything except Plugins and the file/code editors; Tools
    trimmed (no import/export). Keeps Settings, Appearance, Users and the store.
  - *External Dev* — plugins, appearance, tools and settings, but no Users
    management (own account only) and no file editors.
  - *Content Editor* — posts, pages, media and comments, plus editing other
    users' accounts (not deleting them, unless enabled below). Everything
    technical is hidden.
- **"Allow Content Editors to delete users"** setting under Client Roles, off by
  default, which grants Content Editors the delete-user capability.
- The three roles appear automatically in the Site Protection role lists.

### Security
- **Admin accounts protected from Content Editors.** A Content Editor's
  user-editing capability cannot be used to edit, delete or promote an
  administrator, closing a password-reset takeover path.
- **BlueWorx console is administrators-only** when Client Roles is on — hidden and
  URL-blocked for the client roles even though they may hold `manage_options`.

### Notes
- Client roles are registered on activation and via a one-time migration, persist
  across deactivation, and are removed (definitions only) on uninstall — user
  assignments are preserved, so reinstalling restores them.

## [1.15.1] - 2026-07-20

### Changed
- **CI unblocked, not fixed.** The shared foundation workflow now fails any run
  that executes zero Playwright tests. This project's `preview_url` is still a
  placeholder, so every spec skips itself — meaning that gate would fail every
  PR here. `ci.yml` now passes `allow_zero_tests: true`, which downgrades the
  failure to a warning while the test-host decision is open.

  **This project's CI asserts nothing today.** The flag is a suppressed alarm,
  not a passing suite, and is tracked for removal in #24.
- `ci.yml` now passes `secrets: inherit`, so `WP_ADMIN_USER` / `WP_ADMIN_PASS`
  reach the Playwright step once they exist as repo secrets. Until then the
  admin specs continue to skip.

## [1.15.0] - 2026-07-20

### Added
- **Menus in the Site group.** The WordPress Menus editor (`nav-menus.php`),
  which core nests under Appearance, is now promoted to its own top-level row in
  the Site group of the re-skinned sidebar, directly after Appearance, with a
  matching list icon.

### Changed
- **Default dashboard layout.** On dashboards a user has not customised, Elementor
  Overview, Quick Draft and Site Management are hidden by default, leaving At a
  Glance, SureRank Website Insights, Object Cache Pro, Site Health Status and
  Activity visible. Applied through `default_hidden_meta_boxes`, so it only sets
  the default and never overrides a user's own Screen Options; widgets whose
  plugins are inactive are unaffected.
- **Edit Menu arrows** now use inline-SVG chevrons from the sidebar icon set
  (stroke 1.75, viewBox 24) in place of the `▲`/`▼` glyphs, so the reorder
  controls match the rest of the re-skin.

### Fixed
- **Admin chrome flash on slow connections.** The layout skeleton that hides the
  native admin bar and offsets the sidebar below the fixed top bar is now printed
  inline in the document head, so it can no longer be deferred by an asset
  optimiser or arrive late behind the main stylesheet. This removes the transient
  stray line under the top bar and the sidebar overflowing its bounds before the
  full theme applied.

### Removed
- **Orphaned managed roles.** A one-time migration removes the `Business Owner`,
  `External Admin` and `Content Editor` roles left in the database by the role
  editor removed in 1.8.0. A role is only removed when it has no users assigned;
  any role still in use is left in place (and its slug recorded in
  `blueworx_orphaned_roles_skipped`) so no account is stranded. The roles can be
  reintroduced later.
- **Stale PHPCS capability rule.** Dropped the `WordPress.WP.Capabilities`
  override whitelisting `blueworx_edit_elementor_templates` — a remnant of the
  same removed role editor; the capability and its `includes/user-roles.php` no
  longer exist.

## [1.14.0] - 2026-07-16

### Changed
- **Sidebar pinned to the viewport.** The sidebar now fills from below the top
  bar to the foot of the screen and no further, so a long page no longer
  stretches the dark panel into a tall empty column below the last item. The
  menu gets its own scroll, independent of the page scroll, and only scrolls as
  far as its last item. The scrollbar is hidden (scroll still works by
  wheel/trackpad/keys) and the foot clearance was raised so the last items clear
  the pinned Log Out on short viewports.
- **Brand dot** recoloured to `#1d2043`, resized, moved to the top-right and
  clipped to the sidebar so it no longer bleeds into the content area. The header
  is opaque so menu content disappears behind it when scrolling.
- **Open submenu background is transparent** — the inline (current) submenu now
  reads as part of the sidebar rather than a dark block; only the guide rail and
  active tick remain. Fly-out submenus keep their solid fill.
- **BlueWorx menu icon** is now Lucide `layout-panel-top`.

### Fixed
- **Buttons keep their radius** through hover, focus and active — primary and
  `page-title-action` buttons were snapping to square corners on click because
  core's `:active` rule reset the radius.
- **Removed WordPress's fly-out pointer triangle** (the dark caret on the right
  of any submenu item).

### Note
- Giving the expanded sidebar its own scroll clips horizontally, so hover
  fly-out submenus for non-current items are suppressed when the sidebar is
  expanded (the current section's submenu still shows inline). Folded mode keeps
  its fly-outs, which are the only way to reach a submenu when collapsed.

## [1.13.0] - 2026-07-16

### Added
- **Profile screen redesign** (`profile.php` / `user-edit.php`). When the admin
  re-skin is on, the native profile form is restructured into a dark hero header
  — avatar, display name, role badge, `@handle · Member since · post count`, plus
  **View Posts** and **Save Changes** — over a two-column card layout. Native
  form sections are MOVED (never recreated) into the cards, so every field,
  nonce and hidden input still posts through core's save handler; the hero
  **Save Changes** proxies core's own submit button. WordPress's two-column
  `.form-table` rows are flattened to stacked, label-above fields inside each
  card. Non-native concepts from the comp (two-factor, session device counts,
  email-verified badges) are intentionally omitted rather than faked.
- **Sidebar brand dot.** A soft indigo radial now sits behind the top of the
  sidebar. The charcoal panel moved to `#adminmenuback` so the dot shows through
  the (now transparent) menu list; fly-out submenus keep their solid fill.
- **Submenu differentiation.** Expanded sub-items now sit under a hairline guide
  rail, and the active child carries a short brand-coloured tick.

### Changed
- **Log Out is pinned** to the foot of the sidebar (783px and up) and stays in
  view while the menu items above it scroll.
- **Consistent default menu order.** The computed default arrangement now sorts
  recognised items into the same design order the render filter uses, so two
  unedited sites no longer draw the Content group in different orders depending
  on whether either had ever saved the menu.
- **Custom Content sits above Content** in the sidebar group order.
- **Dashboard stat tiles** render two per row instead of four.
- **Custom-content icon** is a distinct "shapes" glyph rather than the generic
  tag, and the brand block subtitle reads **BlueWorx** in place of "wp-admin".

## [1.12.0] - 2026-07-15

### Added
- **BlueWorx admin top bar.** On screens 783px and wider, the WordPress admin bar
  is replaced by the design's top bar: the current page title, a **View Site**
  button that opens the front end in a new tab, and a user menu (Edit Profile,
  Log Out). A brand block — the site's initial in an indigo mark, the site name,
  and "wp-admin" — sits above the menu. The user menu is a native `<details>`
  disclosure, so no JavaScript ships with it. Below 783px the native admin bar is
  kept, because it carries WordPress's responsive menu toggle.
- **Semantic sidebar groups.** The sidebar is grouped by meaning — Overview,
  Content, Custom Content, Site — with a heading above each. Custom post types
  are recognised by shape, so any type a site registers top-level lands in Custom
  Content without being listed. A group with nothing in it renders no heading,
  and unrecognised third-party menus fall back to Custom Content rather than
  being dropped — a plugin's own top-level menu is nearly always the content it
  manages, where Site is core's housekeeping. BlueWorx sits in Overview, directly
  below the Dashboard it extends.
- **Design icon set** on the mapped core menus and on every custom post type,
  replacing dashicons. Icons stroke `currentColor`, so they follow their label
  through idle, hover and active. Unmapped third-party menus keep their own
  glyph.
- **Count badges** on Posts, Media, Pages, custom post types and Plugins, from
  core's count APIs. Zero renders no badge. Where WordPress already draws its own
  bubble (plugin updates, comments awaiting moderation), core's wins — one row
  never carries two counts.
- **Log Out row** at the foot of the sidebar, alongside the top bar's user menu.
  The design shows both.

### Changed
- **Sidebar order follows the design by default.** Within a group, items now use
  the design's order — Content reads Posts, Media, Pages — instead of the older
  "shortest label, then A–Z" rule, which predates the groups and was written for
  a single flat list. Sites that have saved the Edit Menu keep their own
  arrangement: the design sets the default, an admin's choice overrides it.
- **Collapse Menu is hidden**, per the design. It reappears if the menu is
  folded, so the state is always reversible, and it is left alone below 961px
  where WordPress auto-folds and it is the only way back out.
- **Edit Menu rebuilt around the semantic groups.** One card per group plus
  Hidden, replacing the Main/More/Hidden columns, so the screen mirrors the
  sidebar it edits. Drag now uses the browser's own drag-and-drop instead of
  jQuery UI, and every row gains up/down buttons — the old screen was drag-only,
  and so unusable by keyboard. Moving an item into another group, or into
  Hidden, is saved with the order.
- **Sidebar matches the design more closely.** Widened to 232px when expanded,
  with rounded menu rows, an indigo active pill, the current-item arrow removed,
  and icons that follow their label colour instead of being tinted indigo.
- **Lighter shadows.** `--bw-shadow-card` is now a soft two-stop lift
  (`0 1px 2px / 0 4px 12px` at 4–5% alpha) instead of the heavier stack, matching
  the design's elevation across cards, tables, notices, and tiles.
- **Login screens are properly designed.** All wp-login actions (log in, lost
  password, reset, register) now use a brand mark in place of the WordPress logo,
  a centred white card with the light shadow, Sora/Inter type, full-width charcoal
  button, rounded inputs with indigo focus rings, and restyled messages/errors.

### Fixed
- **Custom post types no longer torn out of the menu that registered them.** An
  earlier pass in this release lifted every type registered with
  `show_in_menu => '<parent>'` into its own top-level row. On a real site that
  shredded the structure the site had authored — Clubhouse registers Sports,
  Teams, Fixtures, Events, Sponsors and People under its own Content menu, and
  promoting them scattered six rows across the sidebar while leaving the Content
  parent behind them, emptied. Where a site nests its post types is a statement
  about how that site is organised, and overruling it is not this plugin's call.
  Types now stay where they were registered, and Custom Content is populated by
  the parent menus themselves.
- **The sidebar overhung its own panel.** `#adminmenu` carried `6px` of side
  padding, but core sizes the menu with a width and leaves it content-box, so
  that padding was added to the width and the rows spilled out of the dark panel
  onto the content area. The padding is gone; the gutter is the row margin alone.
- **Rows were not all the same height.** Only mapped slugs get a design SVG; an
  unmapped third-party menu keeps core's dashicon, whose glyph box is 36px
  (`20px/1` plus `8px` vertical padding) against the mapped rows' 20px. With the
  same anchor padding on both, unmapped rows stood 16px taller than their
  neighbours. The icon slot is now a fixed 20px box for every row, so they all
  settle at the shorter height.
- **Hovering a section's top item highlighted the whole section.** Core paints
  hover on the `li`, not the anchor — and that `li` hosts both the group's
  `::before` heading and, on the current item, its inline submenu. The fill
  therefore bled across the heading and every row beneath it. The `li` no longer
  takes a background; state lives on the anchor alone, as the design intends.
- **Dashboard hero tiles could fail to appear.** The tiles were registered at
  `core` priority, but `do_meta_boxes()` renders `high` → `sorted` → `core` and
  moves any saved user layout into `sorted` — so on a dashboard that had been
  rearranged, the tiles were pushed below everything else. They are now registered
  at `high` priority and stay at the top.
- **Edit Menu listed "Plugins 0".** Core hangs a live update bubble off some menu
  labels (`Plugins <span class="update-plugins count-0">…`), and flattening the
  label with `wp_strip_all_tags()` folded that count into the name — so the screen
  listed "Plugins 0" and its reorder buttons announced "Move Plugins 0 up" to a
  screen reader. The bubble is now stripped before flattening, in both the screen
  and the stored `blueworx_admin_menu_item_labels` fallback, so the count no
  longer leaks into either.

### Internal
- **Playwright: the admin suite no longer hangs on its second click.** WordPress
  6.9 ships cross-document view transitions in wp-admin, guarded by
  `@media (prefers-reduced-motion: no-preference)`. In headless Chromium those
  transitions permanently stop the page being rendered: `requestAnimationFrame`
  never fires again while timers keep running and the DOM stays queryable. Every
  Playwright actionability check is built on rAF, so from the first click-driven
  navigation onward every `click`, `setChecked` and `hover` hung for its full
  timeout, reporting "waiting for element to be visible, enabled and stable" about
  elements that were provably all three. Only page-initiated navigations arm it,
  which is why `page.goto()` was always fine and why the *first* click of a test
  always worked and the *second* never did. The suite now emulates reduced motion
  via a fixture in `tests/helpers.js`, opting out of core's rule at source. This
  MUST be done imperatively — `use: { reducedMotion: 'reduce' }` in
  `playwright.config.js` is accepted and then silently ignored (verified on
  @playwright/test 1.61.1), which is what made this look like "not view
  transitions" for two sessions. The site itself was never affected; real browsers
  finish the transition and keep painting.
- **The theme flag is restored even when a test dies.** `admin_theme` is a real
  setting on a real site. The on/off test turned it off, and a failure before the
  restore left staging unthemed and every later test in the file asserting against
  stock WordPress. Restoring now happens in an `afterEach`, which still runs when
  the test throws or times out.
- **`click({ force: true })` removed** from the Edit Menu save. It was masking the
  view-transition freeze above; an honest click works now.
- **Credentials come from a gitignored `.env`** via dotenv, so runs no longer need
  them pasted onto every command line (and into shell history). Copy
  `.env.example` to `.env`. Anything already set in the environment still wins, so
  CI can inject real secrets with no `.env` present.
- **The auth/login REST test skips when the site has no auth configured.** A site
  without a JWT secret answers `503 blueworx_auth_unconfigured` and never looks at
  the credentials, so there is no rejection behaviour to assert — an environment
  gap, not a defect. It now skips with that reason instead of reporting a red no
  code change could fix. Any other 503 still fails.

### Known issues
- `tests/headless-rest.spec.js` › *CORS is not granted to a disallowed origin*
  fails, and is left failing deliberately. The plugin's CORS allowlist has no deny
  path: it never removes core's `rest_send_cors_headers`, so any origin is echoed
  with `Access-Control-Allow-Credentials: true`. Pre-existing and unrelated to this
  release; tracked in #20 rather than hidden behind a skip.
- No Playwright test gates a pull request. CI points at a placeholder URL, so every
  admin spec skips, and the shared workflow never deploys the plugin — it would
  test whatever is installed on staging rather than the code under review. Tracked
  in #21.

### Removed
- **The "More" menu is retired.** The design replaces the Main/More/Hidden split
  with the four semantic groups, so More has no equivalent. Items that sat in it
  are assigned to their natural group and **reappear as top-level rows** — More
  was a grouping affordance, not a hiding one, and the separate Hidden bucket is
  untouched. Existing sites are migrated automatically.
- **`#wpfooter` is hidden** on all admin screens while the theme is active.

## [1.11.0] - 2026-07-14

### Added
- **BlueWorx admin re-skin.** A CSS-first re-skin of wp-admin and the login
  screen using the BlueWorx design system (indigo/charcoal/lavender palette,
  self-hosted Sora + Inter, rounded cards, restyled admin bar and sidebar). It
  restyles WordPress's own native elements — no framework, no replacement markup.
  Shipped as a feature flag under **BlueWorx → Enhancements → Appearance**
  (`admin_theme`, default on); turn it off to return to the standard WordPress
  appearance.
- **Hybrid Dashboard.** The Dashboard gains a hero row of four live stat tiles
  (Posts, Pages, Comments, Media) and keeps the native Activity, Quick Draft, and
  Site Health widgets, restyled to match. The Welcome panel, WordPress Events &
  News, and At a Glance widgets are removed while the theme is active.

## [1.10.1] - 2026-07-13

### Added
- **Headless integration guide.** New `HEADLESS_INTEGRATION.md` documenting the
  `blueworx/v1` REST contract, auth model, content/routing, revalidation, and
  SureCart proxy so a headless Next.js frontend can be pointed at this repo to
  build against the plugin. Documentation only; no runtime change.

## [1.10.0] - 2026-07-13

### Added
- **Default admin-menu arrangement.** For administrators, the admin menu now
  pins BlueWorx directly below Dashboard, keeps only Posts, Media, Pages, and
  Users visible, moves every other core WordPress item into **More**, and leaves
  plugin-added items visible below the defaults. All items are ordered by title
  length (shortest first, alphabetical tiebreak); More stays last. Saving the
  Edit Menu page freezes the arrangement, after which saved choices always win.
  Sites that had already arranged their menu are detected on upgrade and keep
  their existing layout.

## [1.9.0] - 2026-07-13

### Added
- **Feature settings page.** BlueWorx → Enhancements is now a grouped settings
  form with an on/off toggle for every enhancement function, organized into
  Security & Access, Content, Notifications & Cleanup, Performance, and Admin
  Menu sections. Disabling a function makes it fully inert and hides its page
  and detail controls; every function defaults on, so existing sites are
  unchanged.
- **Editable login slug.** The custom login path is now configurable on the
  settings page (was the fixed `admin_login`). Turning the login function off
  restores the standard WordPress login.

## [1.8.0] - 2026-07-13

### Removed
- **Role editor / user-role controls**, to be rebuilt later. Removes the
  "Edit Role" admin page, the three managed roles (Business Owner, External
  Admin, Content Editor), the capability editor, and the backend-page
  permission engine (`includes/user-roles.php` and `assets/js/role-editor.js`).
  The separate "Site Protection" feature (role-based frontend/backend view
  gating) is unchanged. Existing sites keep their roles and saved role options
  in the database — only the code is removed, so nothing is lost when the
  feature is rebuilt.

## [1.7.0] - 2026-07-13

### Changed
- **Plugin slug renamed** from `blueworx-project-wordpress-labs` to
  `blueworx-labs-wordpress`, aligning it with the renamed repository. The main
  file, folder, text domain, admin page slug, asset handles, CI `plugin_slug`,
  and build/version scripts all move to the new slug. The display name
  ("BlueWorx Labs | WordPress Enhancements") is unchanged.

### Migrations
- Added a one-time migration (labs DB version 2) that remaps saved admin-menu
  customizations (order, hidden/toggled items, and item labels) from the old
  slug to the new one, so admins keep their menu settings across the rename.

## [1.6.0] - 2026-07-09

### Added
- **Headless REST layer** under `/wp-json/blueworx/v1/` implementing
  IMPLEMENTATION_PLAN.md Phases 1–6:
  - **Auth core:** JWT access tokens (bundled `firebase/php-jwt` v7, HS256,
    secret from `wp-config`), a `determine_current_user` filter, and rotating
    refresh-token families with reuse-detection and `token_version` global
    revocation. Endpoints: `/auth/login`, `/refresh`, `/logout`, `/logout-all`,
    `/me`.
  - **Accounts:** `/account/register` (open/invite/closed modes, default
    closed), `/verify`, `/resend-verification`, `/password/forgot|reset|change`,
    `PATCH /account`, and `DELETE /account` (re-auth required). Reset/register
    responses are non-enumerating.
  - **Public content:** `/menus/{location}`, `/site`, `/resolve`,
    `/acf-options`, an ACF-to-core-REST bridge, and a settings-driven CPT
    registrar.
  - **CORS + revalidation:** credentialed CORS (exact-origin echo, never `*`)
    and an outbound on-demand revalidation webhook (default OFF; never triggers
    a Netlify build).
  - **SureCart proxy:** public catalogue plus ownership-scoped `/me/*` and write
    endpoints; ownership fails closed. Disabled by default.
- New **Headless** admin settings tab for all non-secret configuration; secrets
  are read only from `wp-config.php` constants.
- Transient-based rate limiting and lockout on auth endpoints.
- Custom tables for refresh-token families and invites (installed on
  activation / schema-version upgrade); daily token garbage-collection cron.

### Changed
- Raised the minimum PHP version to **8.0** (required by `firebase/php-jwt` v7,
  which carries the fix for the v6 advisory CVE-2025-45769). PHP 7.4 is EOL.
- Hardened the managed-role backend page gate to ignore off-site referers
  (fail closed) rather than trusting any `wp_get_referer()` value.

### Dependencies
- Added `firebase/php-jwt` ^7.0 (Composer, PHP). Its HS256 subset is bundled in
  `includes/rest/lib/firebase-jwt/` so the shipped plugin is self-contained.

## [1.5.1] - 2026-07-09

### Added
- Authored `IMPLEMENTATION_PLAN.md` — the authoritative build contract for the
  headless REST layer (§1–§16), including the concrete §13 endpoint map and §14
  phase order. Settles the JWT library (`firebase/php-jwt`), refresh-token
  revocation storage, rate-limit mechanism, registration modes, and the
  settings-vs-`wp-config` split; defers the LatePoint proxy to Phase 7 (no
  official LatePoint REST API). Unblocks Phases 1–6. Documentation only — no
  runtime code changed.

## [1.5.0] - 2026-07-08

### Changed
- Renamed the plugin to **BlueWorx Labs | WordPress Enhancements** (slug
  `blueworx-project-wordpress-labs`); constants now use the `BLUEWORX_LABS_`
  prefix and the text domain is `blueworx-project-wordpress-labs`.
- Onboarded the repo to `bluegroup_core_foundation`: shared CI guardrail
  workflow, PR/issue templates, Claude settings, `CLAUDE.md`, `approved-deps.json`,
  Composer/WPCS + ESLint lint config, npm build tooling, and Playwright scaffold.

### Removed
- **Breaking:** removed the Elementor SureCart pricing-table widget and its
  assets (foundation "no page builders" rule). Sites rendering that widget in
  Elementor need an alternative.

## Earlier history

Versions 1.0.0–1.4.30 were released under the previous name **BlueWorx
Enhancements**. See `readme.txt` history or git tags for details:
custom login-URL hardening, Cloudways/Varnish cache refresh, role editor and
custom roles, disable-comments, admin-email suppression, profile cleanup, and
page excerpts.
