# Merging parallel feature branches

Every branch in this repo bumps the plugin version and records a changelog
entry, because CI requires both on every PR. That is fine for one branch at a
time. When several branches are open at once, each one has to take `main` before
it can merge, and the same handful of files conflict every time:

- `blueworx-labs-wordpress.php`, `package.json`, `readme.txt` — the version line
- whichever spec both branches touched, usually `tests/admin-theme.spec.js`

`CHANGELOG.md` used to head that list, and was the most frequent conflict of the
lot. It no longer is. A branch adds its own file under `changelog.d/` instead
(see `changelog.d/README.md`), so two branches never touch the same lines, and a
workflow folds those fragments into `CHANGELOG.md` on `main`, where nothing else
is writing to it. Never run `npm run changelog:assemble` on a feature branch —
that puts the entry back at the top of the shared file and re-creates exactly
the conflict the fragments remove.

The conflicts themselves are trivial. Resolving them is where the damage happens.

## Two ways this goes wrong

**Never `git checkout --ours <file>`.** It takes the *whole file* from your side,
not the conflicting hunk. On 2026-07-29 that was used on the plugin header, where
the only conflict was the version line — and it silently discarded `main`'s
`require_once` for `includes/support-access.php` along with a deactivation hook.
An entire feature stopped loading. 26 CI tests failed, 19 minutes later.

**Do not blindly concatenate both sides of a spec conflict.** Each side of the
hunk ends mid-block, so joining them swallows the closing `});` of the last test
on the first side. The file stops parsing and every test in it fails to collect.
This happened twice on the same day, at the same hunk boundary.

`npm run lint` does not catch the second one: it lints `assets/js` only.

## What to do instead

Resolve the conflicting hunk, and only the conflicting hunk. Then, before pushing:

```bash
npm run check:merge          # defaults to origin/main
npm run check:merge upstream/main   # or name a base
```

It checks three things:

1. No leftover conflict markers in any tracked file.
2. No line that the base branch has is missing from the version-carrying files.
   Additions are fine; a *deletion* is the signature of taking one side wholesale.
   Reformatting is tolerated — a line that moved or gained a trailing comma is not
   reported as lost.
3. Every spec still parses, via `node --check`.

Then run the tests the branch actually touches against the local harness before
you push — see the harness notes in the project memory. A green local run is much
cheaper than a red CI run.

## Order matters

Merge in ascending version order. Each PR's version-bump check compares against
`main`, so merging 1.41.0 before 1.39.0 leaves the lower bumps looking like
regressions.
