# Changelog fragments

One file per change, named after the branch: `changelog.d/add-contact-form.md`.

A fragment is exactly what would previously have gone under the version heading
in `CHANGELOG.md` — one or more category blocks, with no version line and no
date:

```markdown
### Changed
- **Short bold summary.** Then the detail: what changed and why it mattered.
  Reference the issue at the end. (#57)
```

Categories, in the order they are emitted: `Added`, `Changed`, `Deprecated`,
`Removed`, `Fixed`, `Security`. A fragment that does not start with one of these
headings fails the assembly script rather than being filed silently.

## Why

CI requires a changelog entry on every PR. When entries went at the top of
`CHANGELOG.md`, every open branch wrote to the same lines and every merge hit
the same conflict — and resolving it is where two production bugs came from. A
file per branch cannot conflict.

## Assembly

`npm run changelog:assemble` folds every pending fragment into `CHANGELOG.md`
under the current plugin version and deletes the fragments. It runs
automatically on push to `main` (`.github/workflows/changelog.yml`); the manual
command is the fallback. Never run it on a feature branch — that re-creates the
conflict this directory exists to remove.
