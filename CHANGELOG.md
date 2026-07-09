# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/); this project uses semantic
versioning.

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
