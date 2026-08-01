# Changelog fragments — design

Issue: [#57](https://github.com/blueworx-io/blueworx_labs_wordpress/issues/57)
Date: 2026-08-01

## Problem

CI requires `CHANGELOG.md` to be modified on every PR. Every entry is prepended
at the top of the file, so any two open branches conflict on the same lines.
Merging four parallel branches on 2026-07-29 meant resolving that same conflict
four times, and two of those resolutions introduced real bugs (see #54 and
`docs/merging-branches.md`).

The conflict is structural, not accidental: the guardrail forces every branch to
write to one shared location.

## Solution

Each branch adds its own file under `changelog.d/` instead of editing
`CHANGELOG.md`. Branches never touch the same lines, so the conflict cannot
occur. A script folds the pending fragments into `CHANGELOG.md` on `main`, where
there is no parallel branch to conflict with.

This needs a change in two repos, in this order.

## Part 1 — `bluegroup_core_foundation`

`scripts/check-changelog.mjs` asserts `CHANGELOG.md` is in the PR diff. It must
accept either shape.

### `scripts/lib/checks.mjs`

`changelogUpdated` gains a `changelogDir` parameter:

```js
export function changelogUpdated({ changedFiles, changelogPath, changelogDir })
```

Passes when either:

- `changedFiles` includes `changelogPath` (current behaviour), or
- `changedFiles` includes any path under `${changelogDir}/`, excluding
  `${changelogDir}/README.md` — that file documents the directory and editing it
  is not a changelog entry.

Path comparison is on the forward-slash paths `git diff --name-only` produces.
When `changelogDir` is absent or empty, only the first branch applies.

On failure the message names both accepted shapes so the fix is obvious from CI
output alone:

```
Neither CHANGELOG.md nor a file under changelog.d/ was changed in this PR.
Add a fragment (changelog.d/<branch-name>.md) or update CHANGELOG.md directly.
```

`gitChangedFiles` reports deletions as well as additions, so a PR that only
*deletes* a fragment would satisfy the directory branch. That is accepted: the
only thing that deletes fragments is the assembly commit, which runs on `main`
and is never a PR. Not worth a `--diff-filter` change to the shared helper.

### `scripts/check-changelog.mjs`

Read `CHANGELOG_DIR` from the environment, defaulting to `changelog.d`, and pass
it through. The no-base-ref fallback path is unchanged — it still checks that
`CHANGELOG.md` exists.

### `.github/workflows/ci-wordpress.yml`

New input:

```yaml
changelog_dir:
  description: >-
    Directory holding per-change changelog fragments. A PR satisfies the
    changelog guardrail by adding a file here OR by editing CHANGELOG.md.
    Projects without this directory are unaffected.
  type: string
  default: changelog.d
```

Passed as `CHANGELOG_DIR` on the "Changelog check" step.

### Backwards compatibility

Projects with no `changelog.d/` directory can never match the new branch, so
their behaviour is byte-for-byte unchanged. No consumer repo needs to do
anything. The input exists so a project can point at a different directory, not
because opting in requires it.

### Tests

`scripts/lib/checks.test.mjs` — foundation CI already runs `node --test
scripts/lib/*.test.mjs`. Cases:

- `CHANGELOG.md` changed, no dir configured → ok (existing behaviour preserved)
- fragment under `changelog.d/` changed, `CHANGELOG.md` untouched → ok
- both changed → ok
- neither changed → fails
- only `changelog.d/README.md` changed → fails
- `changelogDir` undefined and only a `changelog.d/` file changed → fails

## Part 2 — this repo

Landed only after Part 1 is on foundation `main`. This repo's `ci.yml` already
calls the foundation at `@main`, so no ref change is needed.

### `changelog.d/`

Created with a `README.md` describing the format. A fragment is exactly the
markdown that would previously have gone under the version heading — one or more
category blocks, no version line, no date:

```markdown
### Changed
- **CI splits the Playwright suite across three runners.** ... (#55)
```

Filename is the branch name: `changelog.d/shard-ci.md`. Two branches can only
collide by sharing a branch name, which git already prevents.

Allowed categories are Keep a Changelog's: `Added`, `Changed`, `Deprecated`,
`Removed`, `Fixed`, `Security`.

### `scripts/assemble-changelog.mjs`

Run via `npm run changelog:assemble`. Behaviour:

1. Read every `*.md` under `changelog.d/` except `README.md`. Exit 0 with a
   message if there are none — this makes the workflow below safe to run on
   every push.
2. Read the current version from the `Version:` header in
   `blueworx-labs-wordpress.php` (the same source the version guardrail uses).
3. Parse each fragment into `### Category` blocks. A fragment whose first
   non-blank line is not a recognised `### Category` heading is an error: exit
   non-zero and name the file. Silently mis-filing an entry is worse than
   failing.
4. Merge blocks across fragments by category, in Keep a Changelog order, with
   fragments concatenated in filename order within a category.
5. Insert `## [<version>] - <YYYY-MM-DD>` plus the merged body immediately above
   the first existing `## [` heading, preserving the file preamble. Date is the
   current UTC date.
6. Delete the consumed fragment files.

If `CHANGELOG.md` already contains a heading for that exact version, append the
new blocks into that section rather than emitting a duplicate heading. This
happens when two PRs merge without a version bump between them.

### `.github/workflows/changelog.yml`

```yaml
on:
  push:
    branches: [main]
```

Checks out, runs the script, and commits with `github-actions[bot]` only if the
tree changed. `permissions: contents: write`. The commit message is
`docs: assemble changelog fragments for <version>`.

The bot's push re-triggers the workflow. That run finds no fragments and exits
at step 1 without committing, so it terminates — no loop guard needed beyond
the empty-directory early exit.

If branch protection rejects the bot push, the workflow fails loudly and
`npm run changelog:assemble` on `main` is the documented manual fallback. This
is recorded in `docs/merging-branches.md` rather than pre-emptively worked
around.

### `.github/workflows/scripts-test.yml`

`on: pull_request`, runs `node --test scripts/*.test.mjs`. This repo's shared CI
runs ESLint and Playwright only, and the foundation's `test_command` input is
the Playwright command — overloading it would break the sharding and the
zero-tests gate. A separate three-step workflow is the clean seam.

`scripts/assemble-changelog.test.mjs` covers: single fragment, multiple
fragments merged by category, category ordering, existing-version append,
unrecognised heading rejected, empty directory no-op, `README.md` ignored.

The "new functionality needs a Playwright test" guardrail does not apply — this
is build tooling with no browser surface. The `node --test` unit tests are the
honest coverage.

### `docs/merging-branches.md`

Remove `CHANGELOG.md` from the list of files that conflict on every merge.
Document the fragment workflow and the manual assembly fallback. Keep everything
else — the `--ours` warning, the spec-concatenation warning, `npm run
check:merge`, and the ascending-version-order rule are all still true.

### Version and changelog for this change

Minor bump (new tooling), and this change ships its own fragment at
`changelog.d/changelog-fragments.md` — the first use of the mechanism it adds.

## Out of scope

`blueworx-labs-wordpress.php`, `package.json`, and `readme.txt` still conflict on
the version line every merge, and that is the other half of the recurring-
conflict problem. Solving it needs a different mechanism (the version is a
single value, not an append-only list) and belongs in its own issue.

## Success criteria

- Two branches can each add a changelog entry and merge in either order with no
  `CHANGELOG.md` conflict.
- A PR touching neither `CHANGELOG.md` nor `changelog.d/` still fails CI.
- Every other project consuming the foundation sees no behaviour change.
- `CHANGELOG.md` on `main` reads the same as it does today.
