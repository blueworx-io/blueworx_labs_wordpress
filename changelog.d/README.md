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

Nothing to do by hand. On every push to `main`,
`.github/workflows/changelog.yml` folds the pending fragments into
`CHANGELOG.md` under the current plugin version and deletes them. The script and
the job both live in the foundation, so every project that opts in gets the same
behaviour.

It can also be run from the Actions tab ("Assemble changelog" → Run workflow) if
it ever needs a nudge. There is deliberately no local command: assembly writes
to the top of the shared file, which is the exact thing this directory exists to
keep off feature branches.
