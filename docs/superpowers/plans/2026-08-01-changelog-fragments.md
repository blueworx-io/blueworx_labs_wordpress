# Changelog Fragments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a PR satisfy the changelog guardrail by adding its own file under `changelog.d/`, so two open branches never conflict on `CHANGELOG.md`.

**Architecture:** The shared guardrail in `bluegroup_core_foundation` learns a second accepted shape (a file under `changelog.d/`) alongside the existing one (`CHANGELOG.md` in the diff); projects with no such directory are unaffected. This repo then opts in with a `changelog.d/` directory and an assembly script that folds pending fragments into `CHANGELOG.md` on `main`, where no parallel branch exists to conflict with.

**Tech Stack:** Node 20 ESM (`.mjs`), `node:test` + `node:assert/strict`, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-08-01-changelog-fragments-design.md`

## Global Constraints

- Two repos, in order: `bluegroup_core_foundation` (Part 1) must be merged to its `main` before this repo's PR (Part 2) can pass CI. This repo's `.github/workflows/ci.yml` already calls the foundation at `@main`.
- Always work on a branch, never `main`. Every change goes through a PR.
- Foundation repo path: `c:/Users/LukeMcfarland/Documents/GitHub/bluegroup_core_foundation`. This repo: `c:/Users/LukeMcfarland/Documents/GitHub/blueworx_labs_wordpress`.
- Pure check logic lives in `scripts/lib/checks.mjs` and does no I/O — callers gather git/fs input and pass it in. Keep it that way.
- Path comparison uses the forward-slash paths `git diff --name-only` produces.
- Fragment categories, in this exact order: `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`.
- Default fragment directory name: `changelog.d`.
- This repo takes a **minor** version bump (new tooling), synced across `blueworx-labs-wordpress.php`, `package.json`, and `readme.txt`.
- No new npm dependencies (`approved-deps.json` guardrail). Everything uses Node built-ins.

---

## Part 1 — `bluegroup_core_foundation`

### Task 1: Teach `changelogUpdated` about a fragment directory

**Files:**
- Modify: `scripts/lib/checks.mjs` (the `changelogUpdated` function)
- Test: `scripts/lib/checks.test.mjs`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `changelogUpdated({ changedFiles: string[], changelogPath: string, changelogDir?: string }) => { ok: boolean, message: string }`. Task 2 calls it with all three.

- [ ] **Step 1: Create the branch**

```bash
cd c:/Users/LukeMcfarland/Documents/GitHub/bluegroup_core_foundation
git checkout main && git pull
git checkout -b changelog-fragments
```

- [ ] **Step 2: Write the failing tests**

Replace the existing `changelogUpdated` test in `scripts/lib/checks.test.mjs` with these six. The first one is the existing behaviour, kept verbatim in intent so a regression is impossible to miss.

```js
test('changelogUpdated: passes only when the changelog is in the diff', () => {
  assert.equal(
    changelogUpdated({ changedFiles: ['src/a.js', 'CHANGELOG.md'], changelogPath: 'CHANGELOG.md' }).ok,
    true,
  );
  assert.equal(
    changelogUpdated({ changedFiles: ['src/a.js'], changelogPath: 'CHANGELOG.md' }).ok,
    false,
  );
});

test('changelogUpdated: a fragment under the fragment dir satisfies the check', () => {
  assert.equal(
    changelogUpdated({
      changedFiles: ['src/a.js', 'changelog.d/add-thing.md'],
      changelogPath: 'CHANGELOG.md',
      changelogDir: 'changelog.d',
    }).ok,
    true,
  );
});

test('changelogUpdated: editing CHANGELOG.md still passes when a fragment dir is configured', () => {
  assert.equal(
    changelogUpdated({
      changedFiles: ['CHANGELOG.md'],
      changelogPath: 'CHANGELOG.md',
      changelogDir: 'changelog.d',
    }).ok,
    true,
  );
});

test('changelogUpdated: fails when neither shape is present', () => {
  const result = changelogUpdated({
    changedFiles: ['src/a.js'],
    changelogPath: 'CHANGELOG.md',
    changelogDir: 'changelog.d',
  });
  assert.equal(result.ok, false);
  assert.match(result.message, /changelog\.d\//);
  assert.match(result.message, /CHANGELOG\.md/);
});

test('changelogUpdated: the fragment dir README does not count as an entry', () => {
  assert.equal(
    changelogUpdated({
      changedFiles: ['changelog.d/README.md'],
      changelogPath: 'CHANGELOG.md',
      changelogDir: 'changelog.d',
    }).ok,
    false,
  );
});

test('changelogUpdated: no fragment dir configured means fragments do not count', () => {
  assert.equal(
    changelogUpdated({ changedFiles: ['changelog.d/add-thing.md'], changelogPath: 'CHANGELOG.md' }).ok,
    false,
  );
  assert.equal(
    changelogUpdated({
      changedFiles: ['changelog.d/add-thing.md'],
      changelogPath: 'CHANGELOG.md',
      changelogDir: '',
    }).ok,
    false,
  );
});
```

- [ ] **Step 3: Run the tests to verify they fail**

```bash
node --test scripts/lib/checks.test.mjs
```

Expected: the four new fragment tests fail. The two existing-behaviour assertions pass.

- [ ] **Step 4: Implement**

Replace `changelogUpdated` in `scripts/lib/checks.mjs` with:

```js
// Two accepted shapes. The historical one is "CHANGELOG.md is in the diff",
// which forces every branch to write to the top of one shared file and so makes
// any two open branches conflict on the same lines. A project can instead keep
// per-change fragments in a directory, where each branch owns its own file and
// the conflict cannot arise; a release step folds them into CHANGELOG.md.
//
// A project with no such directory can never match the second branch, so its
// behaviour is unchanged.
export function changelogUpdated({ changedFiles, changelogPath, changelogDir }) {
  if (changedFiles.includes(changelogPath)) {
    return { ok: true, message: `${changelogPath} was updated.` };
  }

  if (changelogDir) {
    const prefix = `${changelogDir.replace(/\/+$/, '')}/`;
    // The directory's own README documents the format; editing it is not an entry.
    const fragments = changedFiles.filter(
      (f) => f.startsWith(prefix) && f !== `${prefix}README.md`,
    );
    if (fragments.length > 0) {
      return { ok: true, message: `Changelog fragment(s) added: ${fragments.join(', ')}.` };
    }
    return {
      ok: false,
      message:
        `Neither ${changelogPath} nor a file under ${prefix} was changed in this PR.\n` +
        `  Add a fragment (${prefix}<branch-name>.md) or update ${changelogPath} directly.`,
    };
  }

  return { ok: false, message: `${changelogPath} was not updated in this PR. Update it alongside the version bump.` };
}
```

- [ ] **Step 5: Run the tests to verify they pass**

```bash
node --test scripts/lib/checks.test.mjs
```

Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add scripts/lib/checks.mjs scripts/lib/checks.test.mjs
git commit -m "feat: let a changelog fragment directory satisfy the changelog check"
```

---

### Task 2: Wire the fragment directory through the CLI and the workflow

**Files:**
- Modify: `scripts/check-changelog.mjs`
- Modify: `.github/workflows/ci-wordpress.yml` (inputs block; the "Changelog check" step at ~line 235)

**Interfaces:**
- Consumes: `changelogUpdated({ changedFiles, changelogPath, changelogDir })` from Task 1.
- Produces: the `CHANGELOG_DIR` environment variable contract (default `changelog.d`) and the `changelog_dir` workflow input.

- [ ] **Step 1: Update the CLI script**

In `scripts/check-changelog.mjs`, after the `changelogPath` line add:

```js
const changelogDir = process.env.CHANGELOG_DIR || '';
```

and change the final call to:

```js
const result = changelogUpdated({ changedFiles, changelogPath, changelogDir });
```

Leave the `!baseRef` skip and the `changedFiles === null` fallback exactly as they are — with no diff there is nothing to look for in the fragment directory, and requiring `CHANGELOG.md` to exist stays the right fallback.

- [ ] **Step 2: Verify the script still parses**

```bash
node --check scripts/check-changelog.mjs
```

Expected: no output, exit 0.

- [ ] **Step 3: Verify the CLI end to end against this repo's own history**

```bash
BASE_REF=main CHANGELOG_DIR=changelog.d node scripts/check-changelog.mjs
```

Expected: on a branch whose diff has neither shape, it prints the new two-line message naming both `CHANGELOG.md` and `changelog.d/` and exits 1. (The foundation repo has no `CHANGELOG.md`, so this is the failing path — that is the message being verified, not a problem.)

- [ ] **Step 4: Add the workflow input**

In `.github/workflows/ci-wordpress.yml`, in the `inputs:` block, after `foundation_ref`:

```yaml
      changelog_dir:
        description: >-
          Directory holding per-change changelog fragments. A PR satisfies the
          changelog guardrail by adding a file here OR by editing CHANGELOG.md.
          Each branch owning its own file is what stops two open branches
          conflicting on the top of CHANGELOG.md. Projects without this
          directory are unaffected — nothing can match, so the old rule applies.
        type: string
        default: changelog.d
```

- [ ] **Step 5: Pass it to the check step**

Change the "Changelog check" step to:

```yaml
      - name: Changelog check
        env:
          BASE_REF: ${{ github.base_ref }}
          CHANGELOG_DIR: ${{ inputs.changelog_dir }}
        run: node "$GITHUB_WORKSPACE/.foundation/scripts/check-changelog.mjs"
```

- [ ] **Step 6: Run the full foundation CI locally**

```bash
for f in scripts/*.mjs scripts/lib/*.mjs; do node --check "$f"; done
node --test scripts/lib/*.test.mjs
```

Expected: no syntax errors; all tests pass.

- [ ] **Step 7: Commit**

```bash
git add scripts/check-changelog.mjs .github/workflows/ci-wordpress.yml
git commit -m "feat: add a changelog_dir input to the WordPress CI workflow"
```

---

### Task 3: Document it and open the foundation PR

**Files:**
- Modify: `docs/` — whichever file documents the guardrails (find it with `grep -rl "check-changelog\|Changelog check" docs/`). If no such file exists, skip the doc edit and say so in the PR body.

- [ ] **Step 1: Find the guardrail documentation**

```bash
cd c:/Users/LukeMcfarland/Documents/GitHub/bluegroup_core_foundation
grep -rln "changelog" docs/ README.md
```

- [ ] **Step 2: Add a paragraph to the changelog guardrail section**

Describe both accepted shapes and that opting in means creating `changelog.d/` — no workflow change needed, because the input already defaults to that name. Match the surrounding prose style.

- [ ] **Step 3: Commit and push**

```bash
git add -A && git commit -m "docs: describe the changelog fragment option"
git push -u origin changelog-fragments
```

- [ ] **Step 4: Open the PR**

```bash
gh pr create --repo blueworx-io/bluegroup_core_foundation \
  --title "Let a changelog fragment directory satisfy the changelog check" \
  --body "..."
```

The body must state: what changes, that consumer projects with no `changelog.d/` directory are byte-for-byte unaffected, and that it unblocks blueworx_labs_wordpress#57.

- [ ] **Step 5: Wait for Foundation CI to pass, then merge**

```bash
gh pr checks --repo blueworx-io/bluegroup_core_foundation --watch
gh pr merge --repo blueworx-io/bluegroup_core_foundation --squash
```

Part 2 cannot pass CI until this is on foundation `main`.

---

## Part 2 — `blueworx_labs_wordpress`

Branch `changelog-fragments` already exists here and holds the spec commit.

### Task 4: The fragment directory and its README

**Files:**
- Create: `changelog.d/README.md`

**Interfaces:**
- Produces: the fragment file format that Task 5's parser consumes — one or more `### <Category>` headings followed by list items, no version heading, no date.

- [ ] **Step 1: Write `changelog.d/README.md`**

```markdown
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
```

- [ ] **Step 2: Commit**

```bash
git add changelog.d/README.md
git commit -m "feat: add the changelog.d fragment directory"
```

---

### Task 5: The assembly script

**Files:**
- Create: `scripts/assemble-changelog.mjs`
- Test: `scripts/assemble-changelog.test.mjs`
- Modify: `package.json` (scripts block)

**Interfaces:**
- Consumes: the fragment format from Task 4.
- Produces: two named exports used by the test —
  - `parseFragment(text: string, filename: string) => Array<{ category: string, body: string }>` — throws `Error` naming the file if the first non-blank line is not a recognised `### <Category>` heading.
  - `assemble({ changelog: string, fragments: Array<{ name: string, text: string }>, version: string, date: string }) => string` — returns the new `CHANGELOG.md` contents. Pure: no fs, no clock.
  - The CLI entry point does the fs and clock work and calls `assemble`.

- [ ] **Step 1: Write the failing tests**

Create `scripts/assemble-changelog.test.mjs`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseFragment, assemble } from './assemble-changelog.mjs';

const HEAD = `# Changelog

Preamble line.

## [1.0.0] - 2026-01-01

### Added
- The first thing.
`;

test('parseFragment: reads a single category block', () => {
  const blocks = parseFragment('### Added\n- A thing.\n', 'a.md');
  assert.deepEqual(blocks, [{ category: 'Added', body: '- A thing.' }]);
});

test('parseFragment: reads several category blocks in one fragment', () => {
  const blocks = parseFragment('### Added\n- A.\n\n### Fixed\n- B.\n', 'a.md');
  assert.deepEqual(blocks, [
    { category: 'Added', body: '- A.' },
    { category: 'Fixed', body: '- B.' },
  ]);
});

test('parseFragment: rejects a fragment with no recognised heading', () => {
  assert.throws(() => parseFragment('- just a bullet\n', 'bad.md'), /bad\.md/);
  assert.throws(() => parseFragment('### Nonsense\n- x\n', 'bad.md'), /bad\.md/);
});

test('assemble: inserts a new version section above the existing ones', () => {
  const out = assemble({
    changelog: HEAD,
    fragments: [{ name: 'a.md', text: '### Added\n- A new thing.\n' }],
    version: '1.1.0',
    date: '2026-08-01',
  });
  assert.match(out, /^# Changelog\n\nPreamble line\.\n/);
  assert.ok(out.indexOf('## [1.1.0] - 2026-08-01') < out.indexOf('## [1.0.0] - 2026-01-01'));
  assert.match(out, /## \[1\.1\.0\] - 2026-08-01\n\n### Added\n- A new thing\./);
});

test('assemble: merges fragments by category, in canonical category order', () => {
  const out = assemble({
    changelog: HEAD,
    fragments: [
      { name: 'b.md', text: '### Fixed\n- Fix two.\n' },
      { name: 'a.md', text: '### Fixed\n- Fix one.\n\n### Added\n- Add one.\n' },
    ],
    version: '1.1.0',
    date: '2026-08-01',
  });
  const section = out.slice(out.indexOf('## [1.1.0]'), out.indexOf('## [1.0.0]'));
  assert.ok(section.indexOf('### Added') < section.indexOf('### Fixed'));
  // Within a category, fragments concatenate in filename order: a.md then b.md.
  assert.ok(section.indexOf('- Fix one.') < section.indexOf('- Fix two.'));
  assert.equal(section.match(/### Fixed/g).length, 1);
});

test('assemble: appends into an existing heading for the same version', () => {
  const out = assemble({
    changelog: HEAD,
    fragments: [{ name: 'a.md', text: '### Fixed\n- Late fix.\n' }],
    version: '1.0.0',
    date: '2026-08-01',
  });
  assert.equal(out.match(/## \[1\.0\.0\]/g).length, 1);
  assert.match(out, /### Added\n- The first thing\.\n\n### Fixed\n- Late fix\./);
});

test('assemble: no fragments leaves the changelog untouched', () => {
  assert.equal(assemble({ changelog: HEAD, fragments: [], version: '1.1.0', date: '2026-08-01' }), HEAD);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
cd c:/Users/LukeMcfarland/Documents/GitHub/blueworx_labs_wordpress
node --test scripts/assemble-changelog.test.mjs
```

Expected: FAIL — `Cannot find module ... assemble-changelog.mjs`.

- [ ] **Step 3: Write the script**

Create `scripts/assemble-changelog.mjs`. Structure it as: the two pure exports first, then a CLI block guarded so importing the module in a test does not run it.

```js
#!/usr/bin/env node
// Folds every pending changelog.d/ fragment into CHANGELOG.md under the current
// plugin version, then deletes them.
//
// Fragments exist because the changelog guardrail requires an entry on every PR,
// and an entry at the top of one shared file makes every pair of open branches
// conflict on the same lines. Run this on main only — running it on a feature
// branch re-creates exactly the conflict the directory removes.

import { readdirSync, readFileSync, writeFileSync, unlinkSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

// Keep a Changelog's order. Emitted in this order regardless of fragment order.
const CATEGORIES = ['Added', 'Changed', 'Deprecated', 'Removed', 'Fixed', 'Security'];

const FRAGMENT_DIR = 'changelog.d';
const CHANGELOG = 'CHANGELOG.md';
const PLUGIN_FILE = 'blueworx-labs-wordpress.php';

export function parseFragment(text, filename) {
  const blocks = [];
  let current = null;
  for (const line of text.split('\n')) {
    const heading = line.match(/^###\s+(\S+)\s*$/);
    if (heading && CATEGORIES.includes(heading[1])) {
      current = { category: heading[1], lines: [] };
      blocks.push(current);
      continue;
    }
    if (heading) {
      throw new Error(
        `${FRAGMENT_DIR}/${filename}: "${line.trim()}" is not a recognised category. ` +
          `Use one of: ${CATEGORIES.join(', ')}.`,
      );
    }
    if (current) current.lines.push(line);
    else if (line.trim() !== '') {
      throw new Error(
        `${FRAGMENT_DIR}/${filename}: content before any "### Category" heading. ` +
          `A fragment must start with one of: ${CATEGORIES.join(', ')}.`,
      );
    }
  }
  if (blocks.length === 0) {
    throw new Error(
      `${FRAGMENT_DIR}/${filename}: no "### Category" heading found. ` +
        `A fragment must start with one of: ${CATEGORIES.join(', ')}.`,
    );
  }
  return blocks.map((b) => ({ category: b.category, body: b.lines.join('\n').trim() }));
}

export function assemble({ changelog, fragments, version, date }) {
  if (fragments.length === 0) return changelog;

  // Filename order, so a category's entries land in a stable, reviewable order.
  const sorted = [...fragments].sort((a, b) => a.name.localeCompare(b.name));

  const byCategory = new Map();
  for (const fragment of sorted) {
    for (const block of parseFragment(fragment.text, fragment.name)) {
      if (!byCategory.has(block.category)) byCategory.set(block.category, []);
      byCategory.get(block.category).push(block.body);
    }
  }

  const body = CATEGORIES.filter((c) => byCategory.has(c))
    .map((c) => `### ${c}\n${byCategory.get(c).join('\n')}`)
    .join('\n\n');

  const existing = changelog.indexOf(`## [${version}]`);
  if (existing !== -1) {
    // Same version already has a section — merge into its end rather than
    // emitting a second heading for one version.
    const next = changelog.indexOf('\n## [', existing + 1);
    const end = next === -1 ? changelog.length : next + 1;
    const before = changelog.slice(0, end).replace(/\n+$/, '\n');
    return `${before}\n${body}\n${changelog.slice(end)}`;
  }

  const section = `## [${version}] - ${date}\n\n${body}\n`;
  const first = changelog.indexOf('\n## [');
  if (first === -1) return `${changelog.replace(/\n+$/, '\n')}\n${section}`;
  return `${changelog.slice(0, first + 1)}\n${section}\n${changelog.slice(first + 1)}`;
}

function pluginVersion() {
  const m = readFileSync(PLUGIN_FILE, 'utf8').match(/^\s*\*?\s*Version:\s*(.+)$/im);
  if (!m) throw new Error(`No "Version:" header found in ${PLUGIN_FILE}.`);
  return m[1].trim();
}

function main() {
  if (!existsSync(FRAGMENT_DIR)) {
    console.log(`No ${FRAGMENT_DIR}/ directory — nothing to assemble.`);
    return;
  }
  const names = readdirSync(FRAGMENT_DIR)
    .filter((n) => n.endsWith('.md') && n !== 'README.md')
    .sort();
  if (names.length === 0) {
    console.log(`No fragments in ${FRAGMENT_DIR}/ — nothing to assemble.`);
    return;
  }

  const fragments = names.map((name) => ({ name, text: readFileSync(join(FRAGMENT_DIR, name), 'utf8') }));
  const version = pluginVersion();
  const date = new Date().toISOString().slice(0, 10);

  writeFileSync(CHANGELOG, assemble({ changelog: readFileSync(CHANGELOG, 'utf8'), fragments, version, date }));
  for (const name of names) unlinkSync(join(FRAGMENT_DIR, name));

  console.log(`Assembled ${names.length} fragment(s) into ${CHANGELOG} under ${version}: ${names.join(', ')}.`);
}

if (process.argv[1] === fileURLToPath(import.meta.url)) main();
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
node --test scripts/assemble-changelog.test.mjs
```

Expected: all 7 pass.

- [ ] **Step 5: Add the npm script**

In `package.json`, in `scripts`, after `"check:merge"`:

```json
    "changelog:assemble": "node scripts/assemble-changelog.mjs",
    "test:scripts": "node --test scripts/*.test.mjs"
```

- [ ] **Step 6: Verify both npm scripts run**

```bash
npm run test:scripts
npm run changelog:assemble
```

Expected: tests pass; the assemble run prints `No fragments in changelog.d/ — nothing to assemble.` and changes no files. Confirm with `git status --short` that `CHANGELOG.md` is untouched.

- [ ] **Step 7: Commit**

```bash
git add scripts/assemble-changelog.mjs scripts/assemble-changelog.test.mjs package.json
git commit -m "feat: assemble changelog fragments into CHANGELOG.md"
```

---

### Task 6: CI — run the script unit tests, and assemble on main

**Files:**
- Create: `.github/workflows/scripts-test.yml`
- Create: `.github/workflows/changelog.yml`

**Interfaces:**
- Consumes: `npm run test:scripts` and `npm run changelog:assemble` from Task 5.

- [ ] **Step 1: Create `.github/workflows/scripts-test.yml`**

```yaml
name: Script tests

# The shared guardrails workflow runs ESLint and Playwright. Neither covers the
# build scripts in scripts/, and the foundation's test_command input is the
# Playwright command — overloading it would break sharding and the zero-tests
# gate. So the unit tests get their own three-step job.
on: pull_request

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
      - run: npm run test:scripts
```

- [ ] **Step 2: Create `.github/workflows/changelog.yml`**

```yaml
name: Assemble changelog

# Feature branches add their own file under changelog.d/ instead of writing to
# the top of CHANGELOG.md, which is what stops two open branches conflicting.
# Folding those fragments into CHANGELOG.md happens here, on main, where there
# is no parallel branch to conflict with.
on:
  push:
    branches: [main]

permissions:
  contents: write

jobs:
  assemble:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Assemble fragments
        run: npm run changelog:assemble

      # This job's own push re-triggers the workflow. That run finds an empty
      # changelog.d/, the script exits without writing, and this step commits
      # nothing — so it terminates after one extra no-op run.
      - name: Commit if anything changed
        run: |
          set -euo pipefail
          if git diff --quiet; then
            echo "Nothing to commit."
            exit 0
          fi
          version=$(grep -m1 -oP '^\s*\*?\s*Version:\s*\K.+' blueworx-labs-wordpress.php | tr -d '\r')
          git config user.name  "github-actions[bot]"
          git config user.email "41898282+github-actions[bot]@users.noreply.github.com"
          git add CHANGELOG.md changelog.d
          git commit -m "docs: assemble changelog fragments for ${version}"
          git push
```

- [ ] **Step 3: Validate both workflow files parse as YAML**

```bash
node -e "const {readFileSync}=require('fs');for(const f of ['.github/workflows/scripts-test.yml','.github/workflows/changelog.yml']){readFileSync(f,'utf8');console.log(f,'read ok')}"
```

Then eyeball the indentation against `.github/workflows/ci.yml`. There is no YAML linter in this repo and adding one would need dependency approval.

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/scripts-test.yml .github/workflows/changelog.yml
git commit -m "ci: run script unit tests on PRs and assemble the changelog on main"
```

---

### Task 7: Prove it — assemble a real fragment end to end

This is the acceptance test for the whole plan, run locally before anything is pushed.

- [ ] **Step 1: Write two fragments by hand**

```bash
printf '### Added\n- **Thing one.** From branch one. (#57)\n' > changelog.d/tmp-branch-one.md
printf '### Fixed\n- **Thing two.** From branch two. (#57)\n' > changelog.d/tmp-branch-two.md
```

- [ ] **Step 2: Assemble and inspect**

```bash
npm run changelog:assemble
head -25 CHANGELOG.md
git status --short changelog.d
```

Run this **before** the version bump in Task 8, so the plugin header still reads
`1.49.2` — a version `CHANGELOG.md` already has a heading for. That makes this
run exercise the same-version merge path: the entries are appended into the
existing `## [1.49.2]` section rather than creating a second heading for one
version.

Expected: exactly one `## [1.49.2]` heading, now ending with `### Added` and
`### Fixed` blocks holding the two new entries, and its original `### Changed`
block still intact above them.

```bash
grep -c '^## \[1\.49\.2\]' CHANGELOG.md
```

Expected: `1`. Both fragment files are gone from `changelog.d/`.

- [ ] **Step 3: Throw the experiment away**

```bash
git checkout CHANGELOG.md
git status --short
```

Expected: clean. The fragments were deleted by the script and were never committed.

---

### Task 8: Documentation, version bump, and this change's own fragment

**Files:**
- Modify: `docs/merging-branches.md`
- Modify: `blueworx-labs-wordpress.php` (`Version:` header), `package.json` (`version`), `readme.txt` (`Stable tag:`)
- Create: `changelog.d/changelog-fragments.md`

- [ ] **Step 1: Update `docs/merging-branches.md`**

In the opening list of files that conflict on every merge, replace the `CHANGELOG.md` bullet with a note that it no longer conflicts, and why:

```markdown
- `blueworx-labs-wordpress.php`, `package.json`, `readme.txt` — the version line
- whichever spec both branches touched, usually `tests/admin-theme.spec.js`

`CHANGELOG.md` used to be on that list, and was the most frequent conflict of
the lot. It no longer is: a branch adds its own file under `changelog.d/`
instead (see `changelog.d/README.md`), and a workflow folds those fragments into
`CHANGELOG.md` on `main`, where nothing else is writing to it. Never run
`npm run changelog:assemble` on a feature branch — that puts the entry back at
the top of the shared file and re-creates the conflict.
```

Leave the rest of the document as it is. The `--ours` warning, the spec
concatenation warning, `npm run check:merge`, and the ascending-version-order
rule are all still true.

- [ ] **Step 2: Bump the version to 1.50.0 in all three files**

```bash
grep -n "1\.49\.2" blueworx-labs-wordpress.php package.json readme.txt
```

Edit each hit to `1.50.0`. Minor, because this adds tooling.

- [ ] **Step 3: Verify the versions agree**

```bash
npm run version:check
```

Expected: pass.

- [ ] **Step 4: Write this change's own fragment**

`changelog.d/changelog-fragments.md` — the first real use of the mechanism:

```markdown
### Changed
- **Changelog entries are per-change files instead of edits to `CHANGELOG.md`.**
  CI requires an entry on every PR, and every entry went at the top of one
  shared file, so any two open branches conflicted on the same lines. Merging
  four branches on 2026-07-29 meant resolving that conflict four times, and two
  of those resolutions introduced real bugs.

  A branch now adds `changelog.d/<branch-name>.md` and never touches
  `CHANGELOG.md`, so there is nothing to conflict on. A workflow on `main` folds
  the pending fragments in under the current version and deletes them;
  `npm run changelog:assemble` is the manual fallback.

  The shared guardrail in the foundation accepts either shape, so projects
  without a `changelog.d/` directory are unaffected. (#57)
```

- [ ] **Step 5: Commit**

```bash
git add docs/merging-branches.md blueworx-labs-wordpress.php package.json readme.txt changelog.d/changelog-fragments.md
git commit -m "docs: record the fragment workflow and bump to 1.50.0"
```

---

### Task 9: Verify and open the PR

- [ ] **Step 1: Run every check this PR will face**

```bash
npm run lint
npm run test:scripts
npm run version:check
npm run check:merge
node -e "require('child_process').execSync('node --check scripts/assemble-changelog.mjs',{stdio:'inherit'})"
```

Expected: all pass. Report any lint findings to the user rather than fixing them in a loop.

- [ ] **Step 2: Confirm the guardrail accepts this PR's shape**

The PR does not touch `CHANGELOG.md`; it adds `changelog.d/changelog-fragments.md`. With the Part 1 change on foundation `main`, the Changelog check passes on the fragment. Confirm the intent locally:

```bash
git diff --name-only origin/main...HEAD | grep '^changelog\.d/'
```

Expected: `changelog.d/README.md` and `changelog.d/changelog-fragments.md`.

- [ ] **Step 3: Push and open the PR**

```bash
git push -u origin changelog-fragments
gh pr create --title "Replace the single CHANGELOG.md with per-change fragments" --body "Closes #57 ..."
```

The body must state: the problem, both parts, the foundation PR it depends on, and that `npm run changelog:assemble` must never be run on a feature branch.

- [ ] **Step 4: Watch CI**

```bash
gh pr checks --watch
```

Expected: the guardrails job passes with the Changelog check satisfied by the fragment, and the new Script tests job passes.

---

## Notes for the executor

- Part 1 must be merged before Part 2's CI can pass. If the foundation PR is still open, Part 2's Changelog check will fail on the old rule — that is expected, not a bug in Part 2.
- The first push to `main` after Part 2 merges will trigger `changelog.yml` and produce a bot commit folding `changelog-fragments.md` into `CHANGELOG.md` under 1.50.0. If branch protection rejects the bot push, the workflow fails loudly; run `npm run changelog:assemble` on `main` manually and push, then raise a follow-up about the protection rule.
