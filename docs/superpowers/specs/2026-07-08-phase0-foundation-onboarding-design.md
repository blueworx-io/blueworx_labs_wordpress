# Phase 0 — Foundation Onboarding + Rename/Merge (Design Spec)

**Date:** 2026-07-08
**Repo:** `blueworx_project_wordpressLabs`
**Status:** Approved for planning
**Author:** Luke McFarland + Claude Code (brainstormed)

---

## 1. Background & the problem this solves

The written brief for this repo describes building a **new headless WordPress
plugin** (`blueworx-headless`, v0.1.0) on top of an assumed scaffold —
`IMPLEMENTATION_PLAN.md` (with an endpoint map at §13 and phase order at §14),
`CHANGELOG.md`, `.github/workflows/ci.yml`, a Playwright config, and a
`composer`/WPCS setup, all described as "already scaffolded."

**None of those artifacts exist in this repo.** What actually exists is a
mature, shipping plugin:

- **BlueWorx Enhancements v1.4.30** — slug `blueworx-enhancements`, ~4,800 lines
  across 12 include files.
- Functionality: custom login-URL hardening, Cloudways/Varnish cache refresh,
  custom user roles + role editor, admin settings, disable-comments, admin-email
  suppression, profile cleanup, page excerpts, and a **784-line Elementor
  SureCart pricing-table widget**.

The brief was written without knowledge of this existing plugin. The user's
direction: **this plugin becomes the single primary plugin** that keeps the
existing Enhancements *and* powers our headless WordPress sites.

This spec covers **Phase 0 only** — foundation onboarding plus the
rename/merge scaffolding — done directly on `main` (the brief authorises this
for initial setup, and it is genuine bootstrapping: CI runs `on: pull_request`,
so `main` commits trigger no CI, and you cannot PR-gate the PR that introduces
the gate). Phases 1–6 (the headless build) are explicitly out of scope here and
are separate spec→plan→build cycles.

## 2. Decisions locked during brainstorming

| # | Decision | Choice |
|---|----------|--------|
| 1 | Plugin topology | **One plugin** containing the existing Enhancements *and* the headless capabilities. |
| 2 | Slug / folder / main file / CI `plugin_slug` | **`blueworx-project-wordpress-labs`** (follows the repo name). |
| 3 | WordPress display label (`Plugin Name`) | **`BlueWorx Labs | WordPress Enhancements`** |
| 4 | Legacy-code strictness | **Full conform in Phase 0** — WPCS-clean the existing code, declare deps, resolve rule conflicts now. |
| 5 | Elementor SureCart pricing-table widget (violates foundation "no page builders") | **Remove entirely** (file + assets + require line). |
| 6 | Version baseline | **`1.5.0`** — marks the "BlueWorx Labs platform" milestone; migrate the `readme.txt` 1.x history into `CHANGELOG.md` so nothing is lost. |

## 3. Foundation constraints Phase 0 must satisfy

Derived from `bluegroup_core_foundation` (`ci-wordpress.yml` + the check
scripts in `scripts/`):

- **CI caller** points at
  `blueworx-io/bluegroup_core_foundation/.github/workflows/ci-wordpress.yml@main`
  with `preview_url` + `plugin_slug` inputs.
- **Plugin version sync** (`check-plugin-version-sync.mjs`): the plugin header
  `Version:` and `package.json` `version` **must be identical**.
- **Plugin zip** (`check-plugin-zip.mjs`): at most **one** `<slug>*.zip` may
  exist anywhere in the repo (depth ≤ 4). Zero is allowed.
- **Changelog** (`check-changelog.mjs`): `CHANGELOG.md` must exist and be
  modified in each PR (checked against the base branch; skipped when not a PR).
- **Version bump** (`check-version-bump.mjs`, `VERSION_SOURCE: plugin-header`):
  the plugin header version must be strictly greater than the base branch's on
  each PR. First build (no base version) is skipped.
- **Approved deps** (`check-approved-deps.mjs`): every `dependencies` /
  `devDependencies` name in `package.json` must appear in `approved-deps.json`.
  (PHP/composer deps are *not* gated by this check.)
- **PHPCS** runs in CI only **if** a `phpcs.xml` / `phpcs.xml.dist` /
  `.phpcs.xml` config exists; then it runs `composer install` and
  `vendor/bin/phpcs`. Full-conform therefore *requires* adding this config.
- **PHP syntax check** (`php -l`) runs over all `.php` regardless.
- **Playwright** runs `npx playwright test` against `PLAYWRIGHT_BASE_URL` =
  `preview_url`.

## 4. Work items

### 4.1 Rename & identity
- Rename `blueworx-enhancements.php` → `blueworx-project-wordpress-labs.php`.
- Header: `Plugin Name: BlueWorx Labs | WordPress Enhancements`;
  `Text Domain: blueworx-project-wordpress-labs`; `Version: 1.5.0`.
- Rename constants `BLUEWORX_ENHANCEMENTS_VERSION|PATH|URL` →
  `BLUEWORX_LABS_VERSION|PATH|URL` across the main file and all remaining
  includes. Keep `BLUEWORX_CUSTOM_LOGIN_SLUG`.
- Update `readme.txt` (`=== BlueWorx Labs | WordPress Enhancements ===`,
  `Stable tag: 1.5.0`) to match.

### 4.2 Elementor removal
- Delete `includes/elementor-surecart-pricing-table.php`,
  `assets/js/surecart-pricing-table.js`,
  `assets/css/surecart-pricing-table.css`, and the corresponding
  `require_once` line in the main file.
- Note it in `CHANGELOG.md` as a **breaking removal**.

### 4.3 Foundation files copied in
- `.github/workflows/ci.yml` — caller of the shared `ci-wordpress.yml@main`,
  `plugin_slug: blueworx-project-wordpress-labs`,
  `preview_url: https://staging.placeholder.blueworx.io` (placeholder, to be
  replaced once a real staging URL exists).
- `.github/PULL_REQUEST_TEMPLATE.md`, `.github/ISSUE_TEMPLATE/task.md`
  (copied verbatim from foundation).
- `.claude/settings.json` (foundation's approved actions + approved skills).
- `CLAUDE.md` — copied from foundation `CLAUDE.md.template`.
- `approved-deps.json` — from the foundation template, filled with the npm dev
  deps this repo actually introduces.

### 4.4 Tooling
- `package.json`: `version: "1.5.0"` (exact match to header). Scripts:
  - `build` → produces `dist/blueworx-project-wordpress-labs.zip` (the single
    deployment artifact; older zips removed first).
  - `lint` → JS/asset lint (final-check only; see §4.5 rule).
  - `version:check` → local mirror of the header↔package.json sync check.
- `composer.json` + `phpcs.xml.dist` (WordPress Coding Standards ruleset).
  `composer lint` = `phpcs`.
- `playwright.config.*` reading `PLAYWRIGHT_BASE_URL` (placeholder), plus a
  minimal smoke test scaffold so `npx playwright test` is meaningful.
- `CHANGELOG.md` (Keep-a-Changelog format), seeded from the `readme.txt`
  history, with `1.5.0` as the top entry (rename, Elementor removal, foundation
  onboarding).

### 4.5 Full-conform / WPCS handling (respects the no-loop linting rule)
1. Add `composer.json` + `phpcs.xml.dist`.
2. Run `composer lint` **once**.
3. **Present the findings to the user and stop.**
4. Apply fixes **only after user approval**; never loop lint→autofix→relint.

So "full conform" lands in two moves: config + report during Phase 0
implementation, then the approved fixes.

### 4.6 Branch protection (final Phase 0 step)
- Enable on `main`: require the CI status check to pass, and require a PR
  before merging.
- Attempt via `gh api`; if admin/auth is unavailable, hand the user the exact
  settings to apply in GitHub. After this, **everything is branch → PR → CI**,
  starting with Phase 1.

## 5. Out of scope (later cycles)

- Phases 1–6: auth core, accounts, public content, CORS/webhook, SureCart +
  LatePoint proxies, hardening.
- Authoring the missing `IMPLEMENTATION_PLAN.md` (endpoint map §13, phase order
  §14). **Phase 1 is blocked until this exists** — to be co-authored with, or
  provided by, the user before Phase 1 begins.
- Rebuilding the SureCart pricing table the approved (headless / block /
  shortcode) way.

## 6. Definition of done (Phase 0)

- Plugin activates under the new name/slug with no fatal errors; all remaining
  includes load with renamed constants.
- Elementor widget and its assets fully removed; no dangling references.
- Foundation files present and correct (CI caller, templates, settings,
  CLAUDE.md, approved-deps).
- `composer lint` has been run and reported; clean after approved fixes.
- `npm run build` produces exactly one `dist/blueworx-project-wordpress-labs.zip`.
- `npm run version:check` passes (header == package.json == readme == 1.5.0).
- `CHANGELOG.md` present with a `1.5.0` entry; versions consistent everywhere.
- Branch protection enabled on `main`.

## 7. Risks & mitigations

- **Slug rename disrupts live installs.** Renaming the folder/slug makes
  WordPress treat this as a new plugin (old one deactivates on update). Accepted
  by the user; deployment/communication handled outside this repo.
- **Removing Elementor widget breaks any site rendering that pricing table.**
  Accepted; a headless re-implementation is backlogged (out of scope).
- **WPCS on 4,800 legacy lines may surface many findings.** Mitigated by the
  report-then-approve flow (no autofix loop); fixes are a reviewed step.
- **Branch protection needs GitHub admin.** Mitigated by a manual-settings
  fallback if `gh` lacks permission.
