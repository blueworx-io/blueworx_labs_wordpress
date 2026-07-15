# CI Runs No Tests — Findings & Design

**Date:** 2026-07-15
**Status:** Proposed — needs Luke's decision on Option A vs B
**Scope:** `bluegroup_core_foundation` (shared workflow) + every WordPress project repo
**Not** part of the admin re-skin refinements branch. Its own PR(s).

## Summary

**Every Playwright test in `blueworx_labs_wordpress` has been silently skipping —
in CI and locally — since the suite was written.** CI reports green while running
zero tests. This is how a 24px layout bug (`.bw-brand` rendering 256px against a
232px sidebar) and a dead login helper both shipped unnoticed.

This is a guardrail failure, not a test failure: the "Playwright test" gate in the
shared CI workflow has never gated anything on this project.

## Evidence

Every admin spec opens with:

```js
test.skip(
  isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
  'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
);
```

Both halves of that condition fire in CI. **Two independent bugs, stacked — fixing
either alone changes nothing:**

### (a) The shared workflow never passes credentials — `bluegroup_core_foundation`

`.github/workflows/ci-wordpress.yml:144` sets only:

```yaml
env:
  PLAYWRIGHT_BASE_URL: ${{ inputs.preview_url }}
  BASE_URL: ${{ inputs.preview_url }}
run: ${{ inputs.test_command }}
```

There is **no `secrets:` block on the `workflow_call` trigger** and
`WP_ADMIN_USER` / `WP_ADMIN_PASS` are never passed. So `!ADMIN_USER` is always
true and every admin test skips — **even against a real URL**. This affects every
project consuming this workflow, not just this one.

### (b) This project passes the placeholder URL

`.github/workflows/ci.yml` sets `preview_url: https://staging.placeholder.blueworx.io`.
`isPlaceholder` is `/placeholder/i.test(baseURL)` ⇒ true. Skips again.

### Why a skip reads as a pass

`npx playwright test` exits **0** when every test skips. The CI step goes green.
Nothing in the run says "0 tests ran".

## Goals

- A guardrail that cannot silently pass while testing nothing.
- Admin tests actually execute on every PR.
- No credentials in any repo.

## Non-goals

- Not changing what the tests assert.
- Not fixing the pre-existing test failures — separate work, tracked on the
  re-skin branch.

---

## The decision: where does CI's WordPress come from?

### Option A — point CI at a shared staging site

Plumb secrets through the workflow; each project sets `preview_url` plus repo
secrets.

- **Pros:** small change; tests the real host (Cloudways, Varnish, Breeze).
- **Cons, and they are serious:**
  - **Concurrent PRs corrupt each other.** These specs *mutate* shared state —
    they toggle feature flags, save settings, hide menu items. Two PRs (or a PR and
    a local run) hitting one site at once will interleave: one run's "flag off" is
    another's "flag on". **Observed, not theoretical:** running just three spec
    files in parallel against staging produced exactly this, and a crashed run left
    the site dirty for later runs. Serialising *within* a run (`workers: 1`) fixes
    one run; it cannot fix two runs.
  - **CI tests the deployed plugin, not the PR's code.** Staging runs whatever was
    last uploaded. A PR could pass CI while its own changes were never exercised —
    the guardrail lies again, more subtly.
  - Staging outages break every project's CI.
  - Varnish serves stale logged-out pages (`X-Cache: HIT`, `Age: 14897` observed),
    so tests need cache-busting to avoid asserting against hours-old HTML.

### Option B — CI spins up its own WordPress (recommended)

Use `wp-env` (or a WordPress service container) inside the workflow, install the
PR's plugin build into it, run Playwright against `localhost`.

- **Pros:**
  - **Tests the PR's actual code** — the thing a guardrail is for.
  - Isolated per run: no cross-PR interference, no shared dirty state, no Varnish.
  - No credentials to leak: the workflow creates its own admin user.
  - No dependency on a staging host being up.
- **Cons:** larger change to the shared workflow; adds ~1–2 min per run; the
  workflow's header comment explicitly chose *against* this ("Runs Playwright
  against a staging/preview URL instead of spinning up WordPress + a database") —
  so this reverses a deliberate decision and needs Luke's sign-off.
- **Docker is available on GitHub's `ubuntu-latest` runners**, so this is viable in
  CI even though it is not on Luke's Windows machine (no Docker installed).

**Recommendation: Option B.** Option A's "CI tests the deployed plugin, not the
PR" defect means it cannot honestly gate a PR — which is the entire point. Option A
is a reasonable *smoke* check against staging **after** merge, but not a PR gate.

Both options need §"Fail on zero tests" below. That part is not optional.

---

## Fail on zero tests

Whichever option wins, the skip-is-green hole must close, or the next
misconfiguration is equally invisible.

Add to `playwright.config.js`:

```js
// A run where everything skipped is a CONFIGURATION FAILURE, not a pass.
// Without this, `npx playwright test` exits 0 having tested nothing, and CI
// reports green — which is how the whole suite skipped unnoticed for its
// entire life.
forbidOnly: !!process.env.CI,
```

`forbidOnly` does not cover skips, so also add a reporter-level assertion or a CI
step that parses the JSON reporter output and fails when
`stats.expected === 0`, or when `stats.skipped === stats.total`.

The blunt alternative, if the test target is guaranteed present (Option B makes it
so): **delete the `test.skip(...)` guards entirely.** A missing WordPress should
then be a loud failure, not a silent skip. This is the cleanest end state and is
the recommended pairing with Option B.

## Changes required — Option B

**`bluegroup_core_foundation/.github/workflows/ci-wordpress.yml`:**
1. Add inputs: `wordpress_version` (default `latest`), `use_local_wordpress`
   (default `true`).
2. Add a step that starts WordPress (`wp-env` or a MySQL + WordPress service
   container), installs and activates the built plugin, and creates an admin user
   with known throwaway credentials.
3. Set `PLAYWRIGHT_BASE_URL=http://localhost:8888`, `WP_ADMIN_USER`,
   `WP_ADMIN_PASS`, and `WP_LOGIN_PATH` from that environment.
4. Keep `preview_url` supported for projects that still want a post-merge smoke
   run, but stop treating it as the PR gate.
5. Fail the job when zero tests executed.

**Each project repo:** no change needed once the workflow provides the
environment — which is the point of a shared workflow.

**Luke's actions:** approve the foundation PR. **No repo secrets required under
Option B** — that is one of its advantages.

## Changes required — Option A (if chosen instead)

1. Foundation: add `secrets: { WP_ADMIN_USER, WP_ADMIN_PASS }` to `workflow_call`
   and pass them into the test step's `env`, plus `WP_LOGIN_PATH` as an input
   (the `login` feature moves the form off `wp-login.php`).
2. This repo: real `preview_url`, and `secrets: inherit`.
3. Luke: set `WP_ADMIN_USER` / `WP_ADMIN_PASS` repo secrets. The account must be a
   **real administrator** — `manage_options` is required or most tests fail (an
   Editor-level account was the first thing that blocked local runs).
4. Serialise runs across PRs (`concurrency:` group) to limit — not eliminate —
   the mutation races.
5. Fail the job when zero tests executed.

## Risks

| Risk | Mitigation |
|---|---|
| Option B reverses the workflow's stated design | Explicit in this doc; needs Luke's sign-off, not a silent change. |
| Turning the gate on will make currently-red CI red | Correct and desirable — but land the foundation change *after* the re-skin branch, or every open PR fails at once. Sequence it. |
| `wp-env` needs Docker | Present on `ubuntu-latest`. Not needed on contributors' machines unless they opt in. |
| Option A only: a PR can pass while its own code was never deployed | Unmitigable. This is why Option B is recommended. |
