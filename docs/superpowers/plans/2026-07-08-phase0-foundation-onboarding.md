# Phase 0 — Foundation Onboarding + Rename/Merge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename the shipping BlueWorx Enhancements plugin to the single primary `blueworx-project-wordpress-labs` plugin, remove the Elementor widget, and wire it fully to the `bluegroup_core_foundation` CI guardrails — ready for the Phase 1+ headless build to land via PRs.

**Architecture:** Pure-PHP WordPress plugin (no framework) loaded from one main file that `require_once`s focused files under `includes/`. Phase 0 is a rename + tooling/onboarding pass done directly on `main` (authorised for initial setup; CI runs `on: pull_request` so `main` commits trigger no CI). No runtime behaviour changes except the Elementor removal. Foundation guardrails run in CI via a thin caller workflow that `uses:` the shared reusable `ci-wordpress.yml`.

**Tech Stack:** PHP 8.2 / WordPress; Composer + PHP_CodeSniffer + WordPress Coding Standards (WPCS) for PHP lint; Node 20 + npm for build/version tooling; `archiver` for the zip build; ESLint (flat config) for JS lint; Playwright for end-to-end smoke tests; GitHub Actions (reusable workflow) for CI.

## Global Constraints

- **Slug / folder / main file / CI `plugin_slug`:** `blueworx-project-wordpress-labs` (exact).
- **Main plugin file:** `blueworx-project-wordpress-labs.php`.
- **Display label (`Plugin Name`):** `BlueWorx Labs | WordPress Enhancements` (exact).
- **Text Domain:** `blueworx-project-wordpress-labs` (must match slug; replaces all `blueworx-enhancements` i18n strings).
- **PHP constants prefix:** `BLUEWORX_LABS_` (replaces `BLUEWORX_ENHANCEMENTS_`). Keep `BLUEWORX_CUSTOM_LOGIN_SLUG` unchanged.
- **Version (everywhere — plugin header, `package.json`, `readme.txt` Stable tag, `CHANGELOG.md` top entry):** `1.5.0` (exact, identical in all four).
- **Plugin header `Version:` MUST equal `package.json` `version`** (CI `check-plugin-version-sync.mjs`).
- **At most one `blueworx-project-wordpress-labs*.zip` anywhere in the repo** (CI `check-plugin-zip.mjs`); build output goes to `dist/` which is gitignored.
- **Every npm `dependencies`/`devDependencies` name MUST be listed in `approved-deps.json`** (CI `check-approved-deps.mjs`).
- **Linting is report-then-approve:** run each linter once, present findings to the user, apply fixes ONLY after user approval. Never loop lint→autofix→relint autonomously.
- **Elementor and all page builders are forbidden** by the foundation; the SureCart pricing-table widget is removed in this phase.
- Foundation repo is available locally at `../bluegroup_core_foundation` (sibling of this repo) for copying templates.

---

## File map

**Renamed / modified (existing):**
- `blueworx-enhancements.php` → `blueworx-project-wordpress-labs.php` (header, constants, require lines, remove Elementor require)
- `includes/*.php` (all remaining) — constant + text-domain renames
- `readme.txt` — header, Stable tag, changelog note
- `assets/` — remove SureCart pricing-table css/js

**Deleted:**
- `includes/elementor-surecart-pricing-table.php`
- `assets/js/surecart-pricing-table.js`
- `assets/css/surecart-pricing-table.css`

**Created:**
- `package.json`, `scripts/version-check.mjs`, `scripts/build-zip.mjs`, `.gitignore`
- `composer.json`, `phpcs.xml.dist`
- `eslint.config.js`
- `.github/workflows/ci.yml`, `.github/PULL_REQUEST_TEMPLATE.md`, `.github/ISSUE_TEMPLATE/task.md`
- `.claude/settings.json`, `CLAUDE.md`, `approved-deps.json`
- `CHANGELOG.md`
- `playwright.config.js`, `tests/smoke.spec.js`

---

## Task 1: Rename plugin identity (main file, constants, text domain)

Deliverable: the plugin is renamed end-to-end and still passes `php -l`. Runtime behaviour unchanged (Elementor still present here; removed in Task 2).

**Files:**
- Rename: `blueworx-enhancements.php` → `blueworx-project-wordpress-labs.php`
- Modify: the renamed main file + all `includes/*.php` + `readme.txt`

**Interfaces:**
- Produces: constants `BLUEWORX_LABS_VERSION`, `BLUEWORX_LABS_PATH`, `BLUEWORX_LABS_URL` (used by later includes and the build); main file `blueworx-project-wordpress-labs.php` (used by CI slug inference and build).

- [ ] **Step 1: Rename the main file (preserve history)**

```bash
git mv blueworx-enhancements.php blueworx-project-wordpress-labs.php
```

- [ ] **Step 2: Rewrite the plugin header block**

In `blueworx-project-wordpress-labs.php`, replace the header comment (lines 1–17) and constant block so it reads:

```php
<?php
/**
 * Plugin Name:       BlueWorx Labs | WordPress Enhancements
 * Plugin URI:        https://blueworx.io/
 * Description:       Site hardening, cache refresh, admin/profile enhancements, and the headless REST layer that powers BlueWorx headless WordPress sites.
 * Version:           1.5.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            BlueWorx
 * Author URI:        https://profiles.wordpress.org/blueworx/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       blueworx-project-wordpress-labs
 * Domain Path:       /languages
 *
 * @package BlueWorxLabs
 */
```

- [ ] **Step 3: Rename the constant definitions**

In the same file, update the `define()` block:

```php
if ( ! defined( 'BLUEWORX_LABS_VERSION' ) ) {
	define( 'BLUEWORX_LABS_VERSION', '1.5.0' );
}

if ( ! defined( 'BLUEWORX_LABS_PATH' ) ) {
	define( 'BLUEWORX_LABS_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'BLUEWORX_LABS_URL' ) ) {
	define( 'BLUEWORX_LABS_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'BLUEWORX_CUSTOM_LOGIN_SLUG' ) ) {
	define( 'BLUEWORX_CUSTOM_LOGIN_SLUG', 'admin_login' );
}
```

Then update every `require_once BLUEWORX_ENHANCEMENTS_PATH . 'includes/...'` line to `require_once BLUEWORX_LABS_PATH . 'includes/...'`. (Leave the Elementor `require_once` line for now; Task 2 removes it.)

- [ ] **Step 4: Global-replace constants and text domain across includes**

Replace across `blueworx-project-wordpress-labs.php` and all `includes/*.php` (use editor replace-all per file; do NOT touch `docs/`):

- `BLUEWORX_ENHANCEMENTS_VERSION` → `BLUEWORX_LABS_VERSION`
- `BLUEWORX_ENHANCEMENTS_PATH` → `BLUEWORX_LABS_PATH`
- `BLUEWORX_ENHANCEMENTS_URL` → `BLUEWORX_LABS_URL`
- `'blueworx-enhancements'` → `'blueworx-project-wordpress-labs'` (i18n text-domain argument, ~199 occurrences; heaviest in `includes/admin-settings.php` and `includes/user-roles.php`)
- `blueworx_enhancements_notice` → `blueworx_labs_notice` (transient key in `includes/admin-settings.php`, 4 occurrences)
- `@package BlueWorxEnhancements` → `@package BlueWorxLabs`

- [ ] **Step 5: Update `readme.txt` header**

Change line 1 to `=== BlueWorx Labs | WordPress Enhancements ===` and `Stable tag: 1.4.30` → `Stable tag: 1.5.0`. (The changelog body in `readme.txt` is migrated to `CHANGELOG.md` in Task 7; leave it here for now.)

- [ ] **Step 6: Verify no stale references remain**

Run:
```bash
grep -rn "BLUEWORX_ENHANCEMENTS_\|blueworx-enhancements\|BlueWorxEnhancements\|blueworx_enhancements_notice" --include=*.php --include=*.txt . | grep -v "includes/elementor-surecart-pricing-table.php"
```
Expected: no output (the only remaining hit would be inside the Elementor file, which Task 2 deletes).

- [ ] **Step 7: PHP syntax check**

Run:
```bash
for f in blueworx-project-wordpress-labs.php includes/*.php; do php -l "$f"; done
```
Expected: `No syntax errors detected` for every file.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "Rename plugin to blueworx-project-wordpress-labs (BlueWorx Labs)"
```

---

## Task 2: Remove the Elementor SureCart pricing-table widget

Deliverable: the page-builder widget and its assets are gone with no dangling references; the foundation "no page builders" rule is satisfied.

**Files:**
- Delete: `includes/elementor-surecart-pricing-table.php`, `assets/js/surecart-pricing-table.js`, `assets/css/surecart-pricing-table.css`
- Modify: `blueworx-project-wordpress-labs.php` (remove its `require_once`)

- [ ] **Step 1: Delete the widget and assets**

```bash
git rm includes/elementor-surecart-pricing-table.php assets/js/surecart-pricing-table.js assets/css/surecart-pricing-table.css
```

- [ ] **Step 2: Remove the require line**

In `blueworx-project-wordpress-labs.php`, delete the line:
```php
require_once BLUEWORX_LABS_PATH . 'includes/elementor-surecart-pricing-table.php';
```

- [ ] **Step 3: Verify no dangling references**

Run:
```bash
grep -rni "elementor-surecart\|surecart-pricing-table\|Blueworx_Surecart_Pricing" --include=*.php --include=*.js --include=*.css .
```
Expected: no output.

- [ ] **Step 4: PHP syntax check on the main file**

Run:
```bash
php -l blueworx-project-wordpress-labs.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "Remove Elementor SureCart pricing-table widget (foundation: no page builders)"
```

---

## Task 3: Node build tooling — package.json, zip build, version:check, .gitignore

Deliverable: `npm install && npm run build` produces exactly one `dist/blueworx-project-wordpress-labs.zip`; `npm run version:check` passes.

**Files:**
- Create: `package.json`, `scripts/build-zip.mjs`, `scripts/version-check.mjs`, `.gitignore`

**Interfaces:**
- Produces: npm scripts `build`, `lint`, `version:check`; `dist/blueworx-project-wordpress-labs.zip`.
- Consumes: plugin header `Version:` from `blueworx-project-wordpress-labs.php` (Task 1).

- [ ] **Step 1: Create `.gitignore`**

```gitignore
node_modules/
vendor/
dist/
*.log
.DS_Store
```

- [ ] **Step 2: Create `package.json`**

```json
{
  "name": "blueworx-project-wordpress-labs",
  "version": "1.5.0",
  "private": true,
  "description": "BlueWorx Labs | WordPress Enhancements — primary headless WordPress plugin.",
  "license": "GPL-2.0-or-later",
  "type": "module",
  "scripts": {
    "build": "node scripts/build-zip.mjs",
    "lint": "eslint assets/js",
    "version:check": "node scripts/version-check.mjs"
  },
  "devDependencies": {
    "@eslint/js": "^9.13.0",
    "@playwright/test": "^1.48.0",
    "archiver": "^7.0.1",
    "eslint": "^9.13.0",
    "globals": "^15.11.0"
  }
}
```

- [ ] **Step 3: Create `scripts/version-check.mjs`**

```js
#!/usr/bin/env node
// Local mirror of the foundation header<->package.json version-sync guardrail.
import { readFileSync } from 'node:fs';

const SLUG = 'blueworx-project-wordpress-labs';
const header = readFileSync(`${SLUG}.php`, 'utf8');
const headerMatch = header.match(/^\s*\*?\s*Version:\s*(.+)$/im);
const headerVersion = headerMatch ? headerMatch[1].trim() : null;
const pkgVersion = JSON.parse(readFileSync('package.json', 'utf8')).version;

if (!headerVersion) {
  console.error('version:check FAILED — no "Version:" header found in the plugin main file.');
  process.exit(1);
}
if (headerVersion !== pkgVersion) {
  console.error(`version:check FAILED — plugin header ${headerVersion} !== package.json ${pkgVersion}.`);
  process.exit(1);
}
console.log(`version:check OK — plugin header and package.json agree (${headerVersion}).`);
```

- [ ] **Step 4: Create `scripts/build-zip.mjs`**

```js
#!/usr/bin/env node
// Builds the deployment artifact: dist/<slug>.zip containing the plugin folder.
// Removes any existing <slug>*.zip first so only the current one remains.
import { createWriteStream, mkdirSync, readdirSync, rmSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import archiver from 'archiver';

const SLUG = 'blueworx-project-wordpress-labs';
const DIST = 'dist';

// Runtime files/dirs that ship inside the plugin folder.
const INCLUDE = ['blueworx-project-wordpress-labs.php', 'readme.txt', 'includes', 'assets', 'languages'];

mkdirSync(DIST, { recursive: true });
for (const f of readdirSync(DIST)) {
  if (f.toLowerCase().startsWith(SLUG.toLowerCase()) && f.toLowerCase().endsWith('.zip')) {
    rmSync(join(DIST, f));
  }
}

const output = createWriteStream(join(DIST, `${SLUG}.zip`));
const archive = archiver('zip', { zlib: { level: 9 } });

output.on('close', () => console.log(`Built ${DIST}/${SLUG}.zip (${archive.pointer()} bytes).`));
archive.on('warning', (err) => { if (err.code !== 'ENOENT') throw err; });
archive.on('error', (err) => { throw err; });

archive.pipe(output);
for (const entry of INCLUDE) {
  if (!existsSync(entry)) continue; // languages/ is optional
  archive.glob(entry.includes('.') ? entry : `${entry}/**/*`, { dot: false }, { prefix: `${SLUG}/` });
}
await archive.finalize();
```

- [ ] **Step 5: Install and build**

Run:
```bash
npm install
npm run build
```
Expected: install succeeds; build prints `Built dist/blueworx-project-wordpress-labs.zip (<n> bytes).`

- [ ] **Step 6: Verify exactly one slug zip exists**

Run:
```bash
find . -path ./node_modules -prune -o -name 'blueworx-project-wordpress-labs*.zip' -print
```
Expected: a single line `./dist/blueworx-project-wordpress-labs.zip`.

- [ ] **Step 7: Verify version:check passes**

Run:
```bash
npm run version:check
```
Expected: `version:check OK — plugin header and package.json agree (1.5.0).`

- [ ] **Step 8: Commit**

```bash
git add package.json scripts/version-check.mjs scripts/build-zip.mjs .gitignore package-lock.json
git commit -m "Add npm build tooling: zip build, version:check, gitignore"
```

---

## Task 4: PHP lint config — Composer + WPCS (report only)

Deliverable: `composer install && composer lint` runs WPCS over the plugin and produces a findings report. **Do not auto-fix.** Present findings to the user; fixes happen only after approval (Task 4b, a manual gate).

**Files:**
- Create: `composer.json`, `phpcs.xml.dist`

- [ ] **Step 1: Create `composer.json`**

```json
{
  "name": "blueworx/blueworx-project-wordpress-labs",
  "description": "BlueWorx Labs | WordPress Enhancements",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": ">=7.4"
  },
  "require-dev": {
    "squizlabs/php_codesniffer": "^3.11",
    "wp-coding-standards/wpcs": "^3.1",
    "phpcompatibility/phpcompatibility-wp": "^2.1",
    "dealerdirect/phpcodesniffer-composer-installer": "^1.0"
  },
  "scripts": {
    "lint": "phpcs",
    "lint:fix": "phpcbf"
  },
  "config": {
    "allow-plugins": {
      "dealerdirect/phpcodesniffer-composer-installer": true
    }
  }
}
```

- [ ] **Step 2: Create `phpcs.xml.dist`**

```xml
<?xml version="1.0"?>
<ruleset name="BlueWorx Labs WordPress Enhancements">
	<description>WordPress Coding Standards for the BlueWorx Labs plugin.</description>

	<file>.</file>

	<exclude-pattern>/vendor/</exclude-pattern>
	<exclude-pattern>/node_modules/</exclude-pattern>
	<exclude-pattern>/dist/</exclude-pattern>
	<exclude-pattern>/.foundation/</exclude-pattern>
	<exclude-pattern>*/assets/*</exclude-pattern>

	<arg name="extensions" value="php"/>
	<arg name="colors"/>
	<arg value="sp"/>

	<rule ref="WordPress"/>
	<rule ref="PHPCompatibilityWP"/>
	<config name="testVersion" value="7.4-"/>
	<config name="minimum_wp_version" value="5.0"/>

	<rule ref="WordPress.WP.I18n">
		<properties>
			<property name="text_domain" type="array">
				<element value="blueworx-project-wordpress-labs"/>
			</property>
		</properties>
	</rule>

	<rule ref="WordPress.NamingConventions.PrefixAllGlobals">
		<properties>
			<property name="prefixes" type="array">
				<element value="blueworx"/>
				<element value="BLUEWORX"/>
			</property>
		</properties>
	</rule>
</ruleset>
```

- [ ] **Step 3: Install Composer deps**

Run:
```bash
composer install --no-interaction
```
Expected: installs php_codesniffer + WPCS; `vendor/bin/phpcs -i` lists `WordPress` in the installed standards.

- [ ] **Step 4: Run the linter ONCE and capture the report**

Run:
```bash
composer lint | tee ../phpcs-report.txt
```
Expected: a WPCS findings report (non-zero exit is expected on legacy code). Do NOT run `phpcbf`.

- [ ] **Step 5: Commit the config (not fixes)**

```bash
git add composer.json phpcs.xml.dist composer.lock
git commit -m "Add Composer + WPCS lint config (report only)"
```

- [ ] **Step 6: STOP — user approval gate (Task 4b)**

Present the `phpcs` findings summary (counts by file/rule) to the user. Do not modify any PHP for style until the user approves. When approved, fix in a separate commit (`composer lint:fix` for auto-fixable items, manual for the rest), re-run `composer lint` once to confirm, and commit as `Apply approved WPCS fixes`. This gate is intentionally not autonomous.

---

## Task 5: JS lint config — ESLint (report only)

Deliverable: `npm run lint` runs ESLint over `assets/js` and produces a findings report. Same report-then-approve gate as Task 4.

**Files:**
- Create: `eslint.config.js`

- [ ] **Step 1: Create `eslint.config.js`**

```js
import js from '@eslint/js';
import globals from 'globals';

export default [
  js.configs.recommended,
  {
    files: ['assets/js/**/*.js'],
    languageOptions: {
      ecmaVersion: 2021,
      sourceType: 'script',
      globals: {
        ...globals.browser,
        ...globals.jquery,
        jQuery: 'readonly',
        wp: 'readonly',
        ajaxurl: 'readonly',
      },
    },
    rules: {
      'no-unused-vars': 'warn',
    },
  },
];
```

- [ ] **Step 2: Run the linter ONCE**

Run:
```bash
npm run lint
```
Expected: an ESLint report (warnings/errors on legacy JS are expected). Do NOT auto-fix.

- [ ] **Step 3: Commit the config**

```bash
git add eslint.config.js
git commit -m "Add ESLint config for assets/js (report only)"
```

- [ ] **Step 4: STOP — user approval gate**

Present ESLint findings to the user. Fix only after approval, in a separate commit; re-run `npm run lint` once to confirm.

---

## Task 6: Foundation files — CI caller, templates, settings, CLAUDE.md, approved-deps

Deliverable: all shared foundation artifacts present; the CI caller references the reusable WordPress workflow with the correct slug and placeholder preview URL.

**Files:**
- Create: `.github/workflows/ci.yml`, `.github/PULL_REQUEST_TEMPLATE.md`, `.github/ISSUE_TEMPLATE/task.md`, `.claude/settings.json`, `CLAUDE.md`, `approved-deps.json`

- [ ] **Step 1: Create `.github/workflows/ci.yml`**

```yaml
name: CI
on: pull_request
jobs:
  guardrails:
    uses: blueworx-io/bluegroup_core_foundation/.github/workflows/ci-wordpress.yml@main
    with:
      preview_url: https://staging.placeholder.blueworx.io
      plugin_slug: blueworx-project-wordpress-labs
```

- [ ] **Step 2: Copy the PR + issue templates and Claude settings from foundation**

```bash
mkdir -p .github/ISSUE_TEMPLATE .claude
cp ../bluegroup_core_foundation/.github/PULL_REQUEST_TEMPLATE.md .github/PULL_REQUEST_TEMPLATE.md
cp ../bluegroup_core_foundation/.github/ISSUE_TEMPLATE/task.md .github/ISSUE_TEMPLATE/task.md
cp ../bluegroup_core_foundation/.claude/settings.json .claude/settings.json
```
Expected: three files copied. (If the foundation sibling path is absent, recreate them verbatim from `bluegroup_core_foundation`.)

- [ ] **Step 3: Copy `CLAUDE.md` from the foundation template**

```bash
cp ../bluegroup_core_foundation/CLAUDE.md.template CLAUDE.md
```
Expected: `CLAUDE.md` present in repo root, identical to the template.

- [ ] **Step 4: Create `approved-deps.json`**

```json
{
  "dependencies": {},
  "devDependencies": {
    "@eslint/js": "^9.13.0",
    "@playwright/test": "^1.48.0",
    "archiver": "^7.0.1",
    "eslint": "^9.13.0",
    "globals": "^15.11.0"
  }
}
```

- [ ] **Step 5: Verify the approved-deps check passes locally**

Run:
```bash
node ../bluegroup_core_foundation/scripts/check-approved-deps.mjs
```
Expected: `All dependencies are on the approved list.`

- [ ] **Step 6: Validate the workflow YAML parses**

Run:
```bash
node -e "const y=require('fs').readFileSync('.github/workflows/ci.yml','utf8'); if(!/ci-wordpress\.yml@main/.test(y)) throw new Error('caller ref missing'); console.log('ci.yml references reusable workflow OK');"
```
Expected: `ci.yml references reusable workflow OK`.

- [ ] **Step 7: Commit**

```bash
git add .github .claude CLAUDE.md approved-deps.json
git commit -m "Add foundation CI caller, PR/issue templates, Claude settings, CLAUDE.md, approved-deps"
```

---

## Task 7: CHANGELOG.md + version consistency

Deliverable: `CHANGELOG.md` exists (Keep-a-Changelog format) with `1.5.0` at the top capturing the rename + Elementor removal + foundation onboarding, seeded with the migrated `readme.txt` history; all four version locations read `1.5.0`.

**Files:**
- Create: `CHANGELOG.md`
- Modify: `readme.txt` (trim migrated changelog to a pointer)

- [ ] **Step 1: Create `CHANGELOG.md`**

```markdown
# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/); this project uses semantic
versioning.

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
```

- [ ] **Step 2: Trim the `readme.txt` changelog to a pointer**

In `readme.txt`, replace the entire `== Changelog ==` section body with:
```
== Changelog ==

Changes from 1.5.0 onward are tracked in CHANGELOG.md. Versions 1.0.0–1.4.30
were released as "BlueWorx Enhancements".
```
Leave the `== Upgrade Notice ==` section as-is or remove it if now empty.

- [ ] **Step 3: Verify version consistency across all four locations**

Run:
```bash
grep -n "Version:" blueworx-project-wordpress-labs.php; grep -n "Stable tag:" readme.txt; grep -n '"version"' package.json; grep -n "## \[1.5.0\]" CHANGELOG.md; npm run version:check
```
Expected: header `Version: 1.5.0`, `Stable tag: 1.5.0`, `"version": "1.5.0"`, a `## [1.5.0]` heading, and `version:check OK`.

- [ ] **Step 4: Commit**

```bash
git add CHANGELOG.md readme.txt
git commit -m "Add CHANGELOG.md at 1.5.0; migrate readme changelog history"
```

---

## Task 8: Playwright scaffold (skips on placeholder URL)

Deliverable: `npx playwright test` runs green, skipping the smoke test while the base URL is the placeholder, so CI stays green until a real staging URL is set.

**Files:**
- Create: `playwright.config.js`, `tests/smoke.spec.js`

**Interfaces:**
- Consumes: `PLAYWRIGHT_BASE_URL` / `BASE_URL` env (set by CI to the caller's `preview_url`).

- [ ] **Step 1: Create `playwright.config.js`**

```js
import { defineConfig } from '@playwright/test';

const baseURL =
  process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || 'https://staging.placeholder.blueworx.io';

export default defineConfig({
  testDir: './tests',
  timeout: 30_000,
  use: { baseURL },
  reporter: 'line',
});
```

- [ ] **Step 2: Create `tests/smoke.spec.js`**

```js
import { test, expect } from '@playwright/test';

const baseURL =
  process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || 'https://staging.placeholder.blueworx.io';
const isPlaceholder = /placeholder/i.test(baseURL);

test('site responds at the base URL', async ({ page }) => {
  test.skip(isPlaceholder, 'No real staging/preview URL configured yet (placeholder in use).');
  const response = await page.goto('/');
  expect(response, 'expected a response from the base URL').toBeTruthy();
  expect(response.status(), 'expected a non-error HTTP status').toBeLessThan(400);
});
```

- [ ] **Step 3: Install the Playwright browser and run**

Run:
```bash
npx playwright install --with-deps chromium
npx playwright test
```
Expected: `1 skipped` (placeholder in use), suite exits 0.

- [ ] **Step 4: Commit**

```bash
git add playwright.config.js tests/smoke.spec.js
git commit -m "Add Playwright config + smoke test (skips on placeholder URL)"
```

---

## Task 9: Enable branch protection on `main`

Deliverable: `main` requires a PR before merging and requires the CI check to pass. From here on, all work is branch → PR → CI.

**Files:** none (GitHub settings via `gh`).

- [ ] **Step 1: Confirm the repo and push Phase 0 commits**

```bash
gh repo view --json nameWithOwner -q .nameWithOwner
git push origin main
```
Expected: prints `blueworx-io/blueworx_project_wordpressLabs` (or the actual owner/name); push succeeds.

- [ ] **Step 2: Trigger a first CI run so the status check exists**

Branch protection can only require a status check GitHub has seen. Create a throwaway PR to surface the `guardrails` check:
```bash
git checkout -b chore/ci-smoke
git commit --allow-empty -m "chore: trigger first CI run"
git push -u origin chore/ci-smoke
gh pr create --fill --base main
```
Wait for the `guardrails` check to appear/run: `gh pr checks --watch`.

- [ ] **Step 3: Apply branch protection**

Replace `OWNER/REPO` with the value from Step 1. The reusable-workflow check context is typically `guardrails / guardrails`:
```bash
gh api -X PUT repos/OWNER/REPO/branches/main/protection \
  -H "Accept: application/vnd.github+json" \
  -f "required_status_checks[strict]=true" \
  -f "required_status_checks[contexts][]=guardrails / guardrails" \
  -f "enforce_admins=false" \
  -f "required_pull_request_reviews[required_approving_review_count]=0" \
  -f "restrictions=" 2>/dev/null || echo "gh api failed — apply via UI (see Step 4)."
```
Expected: JSON describing the protection, or the fallback message.

- [ ] **Step 4: Fallback / verification**

If Step 3 failed (missing admin/auth) or the context name differs, apply via GitHub UI: Settings → Branches → Add rule for `main` → require a pull request before merging + require status checks to pass → select the `guardrails` check (exact name as shown after the first CI run). Verify:
```bash
gh api repos/OWNER/REPO/branches/main/protection -q '.required_pull_request_reviews, .required_status_checks.contexts' 2>/dev/null || echo "Confirm protection in the GitHub UI."
```
Expected: protection settings echoed, or a UI confirmation.

- [ ] **Step 5: Close the smoke PR/branch**

```bash
gh pr close chore/ci-smoke --delete-branch
git checkout main
```

---

## Self-review notes (author)

- **Spec coverage:** §2 decisions 1–6 → Tasks 1,2,7 (identity/version/Elementor); §3 foundation constraints → Tasks 3,4,6,7,8 (version-sync, zip, changelog, approved-deps, phpcs config, playwright); §4.5 full-conform report-then-approve → Tasks 4b, 5 gates; §4.6 branch protection → Task 9. All covered.
- **Placeholders:** none — every config/script/test is provided in full.
- **Type/name consistency:** slug `blueworx-project-wordpress-labs`, constant prefix `BLUEWORX_LABS_`, version `1.5.0`, and devDependency versions are identical across `package.json`, `approved-deps.json`, and the ESLint/Playwright/archiver references.
- **Known follow-ups (out of Phase 0):** replace the placeholder `preview_url` once a staging URL exists; author `IMPLEMENTATION_PLAN.md` before Phase 1; the approved lint fixes (Tasks 4b/5) are user-gated.
