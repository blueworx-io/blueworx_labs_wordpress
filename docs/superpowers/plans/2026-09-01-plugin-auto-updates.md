# Plugin auto-updates implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** every site running this plugin installs new versions on its own, from a
GitHub Release, with no zip ever uploaded again after one final time.

**Architecture:** adopt the foundation's existing standard. The Plugin Update
Checker is vendored into the plugin and pointed at this repo's releases; a tag
push runs the foundation's reusable release workflow, which builds and publishes
the zip; the plugin forces its own WordPress auto-update on, so sites install it
unattended.

**Tech Stack:** WordPress plugin API (`auto_update_plugin`), YahnisElsts Plugin
Update Checker v5.7 (vendored PHP, no Composer), GitHub Actions reusable workflow
`blueworx-io/bluegroup_core_foundation/.github/workflows/release-wordpress.yml`,
Playwright, plain-PHP CLI tests.

**Spec:** `docs/superpowers/specs/2026-09-01-plugin-auto-updates-design.md`

## Global Constraints

- Repo is **public**: no update token on any site. The bootstrap's
  `BLUEWORX_PLUGIN_UPDATE_TOKEN` block is still copied in, guarded by
  `defined()`, so a later switch to private needs no code change.
- Slug is `blueworx-labs-wordpress` in all four places it appears: the folder
  inside the zip, `buildUpdateChecker()`'s third argument, the plugin's installed
  directory on every site, and the main plugin file's basename. They must agree
  or an update installs as a second copy and deactivates the original.
- Version for this work is **1.76.0** (a feature). Plugin header,
  `BLUEWORX_LABS_VERSION`, `package.json` and `readme.txt` Stable tag must all
  match, and `npm run version:check` enforces it.
- `foundation_ref` is pinned to `v1.9.0`, never the moving `v1` tag.
- The library is vendored verbatim at v5.7 and committed. Never edited.
- Branch is `add-plugin-auto-updates`, stacked on `fix-surecart-sticky-header`
  (PR #189), which must merge first.

---

### Task 1: Vendor the update checker

**Files:**
- Create: `plugin-update-checker/` (the library, unpacked from v5.7)
- Modify: `approved-deps.json` — record the vendored library

**Interfaces:**
- Produces: `plugin-update-checker/plugin-update-checker.php`, and the class
  `YahnisElsts\PluginUpdateChecker\v5\PucFactory` with the static method
  `buildUpdateChecker( string $metadataUrl, string $fullPath, string $slug )`.

- [ ] **Step 1: Download and unpack v5.7 at the repo root**

```bash
curl -L -o puc.zip https://github.com/YahnisElsts/plugin-update-checker/archive/refs/tags/v5.7.zip
unzip -q puc.zip && rm puc.zip
mv plugin-update-checker-5.7 plugin-update-checker
```

- [ ] **Step 2: Check it is the whole library and nothing else**

Run: `ls plugin-update-checker && php -l plugin-update-checker/plugin-update-checker.php`
Expected: `plugin-update-checker.php`, `Puc/`, `vendor/`, `load-v5p7.php` present;
no syntax errors.

- [ ] **Step 3: Record it in approved-deps.json**

Add a `vendored` block naming the library, its version and why it is committed
rather than installed, so the dependency guardrail has an answer on the record:

```json
  "vendored": {
    "plugin-update-checker": "5.7"
  }
```

- [ ] **Step 4: Commit**

```bash
git add plugin-update-checker approved-deps.json
git commit -m "Vendor plugin-update-checker v5.7"
```

---

### Task 2: The forced auto-update filter

**Files:**
- Create: `includes/auto-updates.php`
- Create: `tests/php/auto-updates-test.php`
- Modify: `blueworx-labs-wordpress.php` — `require_once` the new include
- Modify: `package.json` — add the test to `test:php`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: `blueworx_force_own_auto_update( $update, $item )`, returning `true`
  when `$item->plugin` is this plugin's basename and `$update` unchanged
  otherwise. Hooked to the `auto_update_plugin` filter.

- [ ] **Step 1: Write the failing test**

`tests/php/auto-updates-test.php`:

```php
<?php
require __DIR__ . '/stubs.php';

function plugin_basename( $file ) {
	return 'blueworx-labs-wordpress/blueworx-labs-wordpress.php';
}

require __DIR__ . '/../../includes/auto-updates.php';

$ours = (object) array( 'plugin' => 'blueworx-labs-wordpress/blueworx-labs-wordpress.php' );
$other = (object) array( 'plugin' => 'surecart/surecart.php' );

check( 'this plugin updates itself', blueworx_force_own_auto_update( null, $ours ), true );
check( 'another plugin keeps its own answer', blueworx_force_own_auto_update( null, $other ), null );
check( 'and an explicit no elsewhere is honoured', blueworx_force_own_auto_update( false, $other ), false );
check( 'an item with no plugin name is left alone', blueworx_force_own_auto_update( null, (object) array() ), null );

finish();
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php tests/php/auto-updates-test.php`
Expected: FAIL — `includes/auto-updates.php` does not exist.

- [ ] **Step 3: Write the include**

`includes/auto-updates.php` defines `blueworx_force_own_auto_update()` exactly as
the interface above describes, guards direct file access with the usual
`ABSPATH` check, and calls
`add_filter( 'auto_update_plugin', 'blueworx_force_own_auto_update', 10, 2 )`.
The comment says why it is forced rather than a setting, and that a bad release
therefore reaches every site unattended.

- [ ] **Step 4: Run the test again**

Run: `php tests/php/auto-updates-test.php`
Expected: PASS, four checks.

- [ ] **Step 5: Wire it into the plugin and the test script**

`require_once BLUEWORX_LABS_PATH . 'includes/auto-updates.php';` beside the other
includes, and append `&& php tests/php/auto-updates-test.php` to `test:php`.

- [ ] **Step 6: Commit**

```bash
git add includes/auto-updates.php tests/php/auto-updates-test.php blueworx-labs-wordpress.php package.json
git commit -m "Force this plugin's own auto-update on"
```

---

### Task 3: The bootstrap, and proof it loads

**Files:**
- Modify: `blueworx-labs-wordpress.php` — the bootstrap below the plugin header
- Modify: `tests/admin-theme.spec.js` — a test on the Plugins screen

**Interfaces:**
- Consumes: `PucFactory::buildUpdateChecker()` from Task 1;
  `blueworx_force_own_auto_update()` from Task 2.
- Produces: `$blueworx_update_checker`, a file-scope variable in the main plugin
  file. Nothing else reads it.

- [ ] **Step 1: Write the failing test**

In `tests/admin-theme.spec.js`, inside the existing describe block:

```js
  test('the plugin updates itself rather than offering a toggle', async ({ page }) => {
    // A forced auto_update_plugin filter is what WordPress prints as plain
    // "Auto-updates enabled" text, where a plugin left to the site's own
    // setting gets an Enable/Disable link instead. That difference is the only
    // outward sign the filter is doing its job, and loading this screen at all
    // proves the vendored update checker parses on a real WordPress.
    await login(page);
    await page.goto('/wp-admin/plugins.php');

    const row = page.locator('tr[data-plugin="blueworx-labs-wordpress/blueworx-labs-wordpress.php"]');
    await expect(row).toHaveCount(1);
    await expect(row.locator('.column-auto-updates')).toContainText('Auto-updates enabled');
    await expect(row.locator('.column-auto-updates a')).toHaveCount(0);
  });
```

- [ ] **Step 2: Run it and watch it fail**

Run: `npx playwright test tests/admin-theme.spec.js -g "updates itself"`
Expected: FAIL — the column shows an "Enable auto-updates" link.

- [ ] **Step 3: Paste the bootstrap into the main plugin file**

Directly below the plugin header's `ABSPATH` guard, at file scope — never inside
a function or conditional, because the `use` import cannot be wrapped. Copy
`templates/plugin-update-checker-bootstrap.php` from the foundation, with
`<repo>` as `blueworx_labs_wordpress` and `<slug>` as `blueworx-labs-wordpress`,
keeping the token block and `enableReleaseAssets()`.

- [ ] **Step 4: Run the test again**

Run: `npx playwright test tests/admin-theme.spec.js -g "updates itself"`
Expected: PASS.

- [ ] **Step 5: Check nothing else broke on a real WordPress**

Run: `npx playwright test tests/smoke.spec.js tests/admin-theme.spec.js`
Expected: all pass. A fatal from the vendored library would take out every one of
them, which is the point of running the whole file.

- [ ] **Step 6: Commit**

```bash
git add blueworx-labs-wordpress.php tests/admin-theme.spec.js
git commit -m "Point the plugin at its own GitHub releases"
```

---

### Task 4: Keep the update checker in the hand-built zip

**Files:**
- Modify: `scripts/build-zip.mjs:12` — the `REQUIRED` allowlist
- Create: `tests/php-checks` addition is NOT the right home; add to
  `scripts/build-zip.mjs` verification instead — see Step 3

**Interfaces:**
- Consumes: `plugin-update-checker/` from Task 1.
- Produces: a `dist/blueworx-labs-wordpress.zip` containing
  `blueworx-labs-wordpress/plugin-update-checker/plugin-update-checker.php`.

- [ ] **Step 1: Add the library to the allowlist**

```js
const REQUIRED = ['blueworx-labs-wordpress.php', 'uninstall.php', 'readme.txt', 'includes', 'assets', 'plugin-update-checker'];
```

- [ ] **Step 2: Build and check the archive by eye**

```bash
npm run build
/c/Windows/System32/tar.exe -tf dist/blueworx-labs-wordpress.zip | grep plugin-update-checker/plugin-update-checker.php
```

Expected: one line,
`blueworx-labs-wordpress/plugin-update-checker/plugin-update-checker.php`.

- [ ] **Step 3: Make the check automatic**

The script already fails on a missing `REQUIRED` entry, so the allowlist entry is
itself the guard: a deleted or renamed `plugin-update-checker/` fails the build
rather than shipping a plugin that can never update. Confirm that by temporarily
renaming the folder and running `npm run build`, expecting a failure, then
renaming it back.

- [ ] **Step 4: Commit**

```bash
git add scripts/build-zip.mjs
git commit -m "Ship the update checker in the hand-built zip too"
```

---

### Task 5: The release workflow

**Files:**
- Create: `.github/workflows/release.yml`

**Interfaces:**
- Consumes: the tag `v<version>` and everything Tasks 1-4 put in the tree.
- Produces: a GitHub Release per tag, with
  `blueworx-labs-wordpress-<version>.zip` attached.

- [ ] **Step 1: Write the workflow**

```yaml
name: Release
on:
  push:
    tags: ['v*']
jobs:
  release:
    uses: blueworx-io/bluegroup_core_foundation/.github/workflows/release-wordpress.yml@v1.9.0
    with:
      foundation_ref: v1.9.0
    permissions:
      contents: write
```

`plugin_slug` is deliberately left out so it derives from the main plugin file.
`permissions: contents: write` is required — a reusable workflow cannot widen
what its caller grants, and publishing 403s without it.

- [ ] **Step 2: Check it parses as the schema expects**

Run: `node -e "require('fs').readFileSync('.github/workflows/release.yml','utf8')"` and read it back by eye against the foundation's inputs table.
Expected: only `foundation_ref` passed, `permissions` on the job.

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/release.yml
git commit -m "Publish a release zip from a version tag"
```

---

### Task 6: Version, changelog and the pointer to the standard

**Files:**
- Modify: `blueworx-labs-wordpress.php` (header + `BLUEWORX_LABS_VERSION`),
  `package.json`, `readme.txt`, `CHANGELOG.md`
- Modify: `CLAUDE.md` — one line pointing at the foundation's doc

- [ ] **Step 1: Bump to 1.76.0 in all four places**

Run: `npm run version:check`
Expected: `version:check OK — plugin header, package.json and readme.txt agree (1.76.0).`

- [ ] **Step 2: Write the changelog entry**

Under `## [1.76.0] - 2026-09-01`, `### Added`, in plain words: the plugin now
updates itself from GitHub releases, nobody uploads a zip after one last time,
and it installs updates on its own rather than waiting to be told.

- [ ] **Step 3: Add the pointer, not the rules**

In `CLAUDE.md`'s Deployment section, one line: releases are cut by tagging, the
standard lives in the foundation's `docs/wordpress-auto-updates.md`. The rules
themselves stay in the foundation — this repo never repeats them.

- [ ] **Step 4: Run everything**

```bash
npm run version:check && npm run test:php && npm run lint
npx playwright test tests/admin-theme.spec.js tests/smoke.spec.js tests/php-checks.spec.js
```

Expected: all pass.

- [ ] **Step 5: Commit and open the pull request**

Base it on `fix-surecart-sticky-header`, and say in the body that #189 merges
first.

---

### Task 7: Cut the first release (needs Luke)

Not code. The steps only a person with merge rights can take, in order:

- [ ] **Step 1:** Merge #189, then merge this pull request.
- [ ] **Step 2:** Tag and push from `main`:

```bash
git checkout main && git pull
git tag v1.76.0 && git push origin v1.76.0
```

- [ ] **Step 3:** Watch the Release workflow. It fails on purpose if the tag and
      the plugin header disagree.
- [ ] **Step 4:** Check the published Release has
      `blueworx-labs-wordpress-1.76.0.zip` attached, and that the zip contains
      `blueworx-labs-wordpress/plugin-update-checker/`.
- [ ] **Step 5:** Upload that zip by hand, once, to every site running this
      plugin — blueworx.io and the client sites. This is the last manual upload:
      only a copy that already carries the update checker can be told about the
      next release.
- [ ] **Step 6:** On one site, Dashboard → Updates → Check again, and confirm the
      plugin reports itself up to date at 1.76.0 rather than unknown.
- [ ] **Step 7:** Prove the loop with a real release. Ship the next small change
      as 1.76.1 and leave it alone: within about twelve hours every site should
      be on it with nobody having touched them.

---

## Self-review notes

- Every section of the spec has a task: vendoring (1), the forced filter (2), the
  bootstrap (3), the hand-built zip's contents (4), the workflow (5), version and
  docs (6), and the per-site rollout the spec calls out as unavoidable (7).
- The one requirement no task can satisfy in this repo is proof of the release
  itself, which needs a tag on `main`. Task 7 is where that lands, and it is
  marked as needing Luke rather than left looking automatable.
