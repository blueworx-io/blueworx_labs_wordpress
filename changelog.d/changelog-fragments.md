### Changed
- **Changelog entries are per-change files instead of edits to `CHANGELOG.md`.**
  CI requires an entry on every PR, and every entry went at the top of one
  shared file, so any two open branches conflicted on the same lines. Merging
  four branches on 2026-07-29 meant resolving that conflict four times, and two
  of those resolutions introduced real bugs.

  A branch now adds `changelog.d/<branch-name>.md` and never touches
  `CHANGELOG.md`, so there is nothing to conflict on. A workflow on `main` folds
  the pending fragments in under the current version and deletes them;
  `npm run changelog:assemble` is the manual fallback. Never run it on a feature
  branch.

  The shared guardrail in the foundation accepts either shape, so projects
  without a `changelog.d/` directory are unaffected. (#57)
