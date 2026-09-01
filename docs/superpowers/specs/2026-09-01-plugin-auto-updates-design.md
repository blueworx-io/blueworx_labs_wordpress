# Plugin auto-updates — design

**Date:** 2026-09-01
**Goal:** every site running this plugin installs new versions on its own. Nobody
uploads a zip again.

## The decision that is already made

The foundation carries the house standard for this: `docs/wordpress-auto-updates.md`
in `bluegroup_core_foundation`, with a reusable release workflow
(`.github/workflows/release-wordpress.yml`) and a bootstrap template
(`templates/plugin-update-checker-bootstrap.php`). This plugin adopts it rather
than inventing anything, and is the **first** plugin to do so — so this design
records only what is specific to this repo, plus the two places our situation
differs from the standard's assumptions.

Nothing here is repeated from the foundation doc. Read that first.

## How it works, end to end

1. A version bump merges to `main` the normal way, green CI and all.
2. A `v<version>` tag is pushed. The foundation's release workflow checks the tag
   against the plugin header, builds a clean `blueworx-labs-wordpress/` tree,
   zips it as `blueworx-labs-wordpress-<version>.zip`, verifies its shape and its
   contents, and publishes a GitHub Release with the zip attached and that
   version's `CHANGELOG.md` section as the notes.
3. Every site's copy of the plugin runs the Plugin Update Checker, which watches
   this repo's releases and treats that zip as a wordpress.org-style update.
4. WordPress installs it unattended, because the plugin forces its own
   auto-update on.

## Two departures from the standard

**The repo is public.** The foundation doc assumes private repos and a
`BLUEWORX_PLUGIN_UPDATE_TOKEN` in every site's `wp-config.php`. This repo is
public, so releases are readable anonymously: no token, nothing to add to any
site, nothing to expire or rotate. The bootstrap's token block is still copied in
verbatim, guarded by `defined()`, so making the repo private later is a
`wp-config.php` change on each site and no code change here.

**Updates install themselves.** The standard stops at offering the update; a
human still presses Update on each site. Luke's answer was fully automatic, so
the plugin filters `auto_update_plugin` to force its own on. That is deliberate
and worth naming, because it is the risk in this design: a bad release reaches
every site within about twelve hours with nobody in the loop. What stands between
a bad release and the sites is the same CI that guards every merge, plus the
release workflow refusing to publish when the tag and the plugin header disagree.
There is no downgrade path — a bad version is fixed by releasing a higher one.

## What changes in this repo

- **`plugin-update-checker/`** — YahnisElsts' library at v5.7, vendored and
  committed whole. No Composer step, no build dependency, and the release
  workflow fails outright if the archive does not contain it.
- **`blueworx-labs-wordpress.php`** — the bootstrap, at file scope directly below
  the plugin header, where the library's `use` import has to live.
- **`includes/auto-updates.php`** — the `auto_update_plugin` filter, as a named
  function so it can be unit-tested. It answers only for this plugin's own
  basename and returns every other plugin's value untouched.
- **`.github/workflows/release.yml`** — calls the foundation workflow on `v*`
  tags with `permissions: contents: write` and `foundation_ref` pinned to a
  release, not the moving `v1` tag (which has gone stale on us before).
- **`scripts/build-zip.mjs`** — its allowlist gains `plugin-update-checker`.
  This is the sharpest edge in the whole change: the local zip is what gets
  uploaded by hand one last time, and a copy of the plugin without the update
  checker in it can never receive another update. Guarded by a test.

## What has to happen once per site

Every site is running a version that has no update checker in it, so no site can
be told about the first release. Each one needs a single manual upload of the
first release's zip — `blueworx-labs-wordpress-1.76.0.zip`, off the GitHub
Release. That upload is the last one. Sites needing it: blueworx.io and the
client sites running this plugin (list to confirm).

A site also has to be able to update itself at all: WP-Cron running, filesystem
writable by the web user, and no `DISALLOW_FILE_MODS` in `wp-config.php`. Any
managed host that blocks plugin file changes will show the update and refuse to
install it.

## Testing

- **Playwright** — the Plugins screen shows this plugin as auto-updating rather
  than offering a toggle, which is what the forced filter looks like from the
  outside. It also proves the vendored library loads on a real WordPress without
  fataling, which the PHP tests cannot.
- **PHP** — the filter returns `true` for this plugin's basename and hands back
  the incoming value unchanged for anything else.
- **The zip's contents** — the local build ships the update checker.

The one thing no test here can prove is the release itself, which needs a tag on
`main`. The first release is therefore also the test of the pipeline: cut it,
watch the workflow, and check the Release has a zip with the update checker
inside before uploading it anywhere.

## Rejected

- **A settings toggle for auto-updates.** Offered and turned down: fully
  automatic everywhere, with no per-site opt-out to keep in sync.
- **Bumping the CI pin to match the release pin.** The release workflow is pinned
  to a newer foundation release than CI is. Aligning them pulls in unrelated
  guardrail changes on the same pull request as this one; it is worth doing, on
  its own, another day.
