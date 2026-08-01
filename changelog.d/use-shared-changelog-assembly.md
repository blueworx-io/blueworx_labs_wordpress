### Changed
- **Changelog assembly moved to the foundation.** The guardrail that *demands* a
  fragment already lived there; the script that *clears* them lived here. A
  project could opt in by creating `changelog.d/`, get the requirement
  immediately, and have no cleanup until it hand-copied a script and a workflow
  out of this repo.

  Both halves are shared now. `.github/workflows/changelog.yml` is a few lines
  calling the foundation's reusable job, and `scripts/assemble-changelog.mjs`
  and its tests are gone from here. No behaviour change, and no local
  `changelog:assemble` command any more — assembly only ever ran on `main`, and
  the command being available locally invited running it on a branch, which
  re-creates the conflict the fragments remove. (#57)
