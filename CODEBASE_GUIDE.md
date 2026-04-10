# Codebase Guide for Newcomers

## Current Repository State (as of 2026-04-10)

This branch currently contains **no runtime source files**. The latest commits removed:

- `.distignore`
- `README.md`
- `blueworx_enhancements.php`
- `plugin-update-checker/plugin-update-checker.php`

So the present tree is effectively an empty Git repository scaffold.

## What the Project Previously Contained

From commit history, this project was a small WordPress plugin named **BlueWorx Enhancements** with GitHub-backed update delivery.

### Previous top-level structure

```text
/blueworx_enhancements
  .distignore
  README.md
  blueworx_enhancements.php
  plugin-update-checker/
    plugin-update-checker.php
```

## Key Architecture (historical)

### 1) Plugin bootstrap (`blueworx_enhancements.php`)

This file defined:

- WordPress plugin headers (`Plugin Name`, `Version`, requirements, etc.)
- constants for plugin version, GitHub repository path, and branch
- token loading via:
  - `BWX_GITHUB_TOKEN` constant
  - `BWX_GITHUB_TOKEN` environment variable
  - `bwx_github_update_token` filter
- setup of a custom update checker factory (`PucFactory::buildUpdateChecker`)
- a request-options filter to set GitHub API headers

### 2) Update checker library (`plugin-update-checker/plugin-update-checker.php`)

This file implemented two classes under `Puc\v5p6`:

- `UpdateChecker`: integrated with WordPress plugin update hooks.
- `PucFactory`: static constructor for `UpdateChecker`.

Core behavior of `UpdateChecker`:

- hooks into `pre_set_site_transient_update_plugins` to inject update metadata
- hooks into `plugins_api` to populate plugin info modal
- fetches `releases/latest` from GitHub API
- strips a leading `v` from release tags to compare semantic versions
- scans release assets for a `.zip` and uses that as the package URL

## Important Operational Rules (historical)

The old README emphasized release discipline:

1. Plugin header version and internal version constant must match.
2. Git tag should mirror plugin version (for example, plugin `1.0.1` => tag `v1.0.1`).
3. Create a GitHub release for that tag.
4. Attach a ZIP with root folder `blueworx_enhancements/`.

## What to Learn Next

If you are onboarding and rebuilding or reviving this repository, start in this order:

1. **WordPress plugin lifecycle basics**
   - activation, plugin headers, update transients, `plugins_api`.
2. **GitHub Releases API integration**
   - auth headers, endpoint reliability, response/error handling.
3. **Secure token handling**
   - prefer constants/env vars/secrets manager over hardcoding.
4. **Versioning and release automation**
   - CI-driven tag/release/asset build to avoid manual mistakes.
5. **Testing strategy**
   - unit tests for version parsing and asset selection
   - integration tests against a mock GitHub API response.

## Suggested Rebuild Plan

- Recreate minimal plugin bootstrap.
- Restore update checker module with test coverage.
- Add local development docs and automated release packaging.
- Add CI checks for PHP linting and WordPress coding standards.
