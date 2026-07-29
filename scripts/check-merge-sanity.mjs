#!/usr/bin/env node
// Verifies a conflict resolution before it is pushed.
//
// Every feature branch here bumps the version and prepends a changelog entry, so
// merging main into a second or third branch conflicts in the same handful of
// files each time. Resolving those by hand has two failure modes that both look
// fine locally and only surface as a red CI run 19 minutes later:
//
//   1. `git checkout --ours <file>` takes the WHOLE file from your side, not the
//      conflicting hunk. On the plugin header that silently deletes whatever the
//      base branch added — on 2026-07-29 it dropped a require_once and a
//      deactivation hook, switching off an entire feature and failing 26 tests.
//   2. Keeping both sides of a spec conflict concatenates across the hunk
//      boundary and swallows a closing `});`, so the file stops parsing and every
//      test in it fails to collect. `npm run lint` does not catch this: it lints
//      assets/js only.
//
// Run: npm run check:merge  (optionally with a base ref, default origin/main)
//
// Checks, in order:
//   - no leftover conflict markers in any tracked file
//   - no line present in the base has vanished from a version-carrying file
//   - every test spec still parses
//
// Exits non-zero on failure, naming the file and line to look at.

import { execFileSync } from 'node:child_process';
import { readFileSync, existsSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const BASE = process.argv[2] || 'origin/main';

// The files a version bump touches, and therefore the files a conflict
// resolution is most likely to take wholesale from the wrong side.
const VERSION_FILES = ['blueworx-labs-wordpress.php', 'package.json', 'readme.txt'];
const TESTS_DIR = 'tests';

let failed = false;

const fail = (message) => {
  console.error(`FAIL  ${message}`);
  failed = true;
};
const pass = (message) => console.log(`ok    ${message}`);
const skip = (message) => console.log(`skip  ${message}`);

function git(args) {
  try {
    return execFileSync('git', args, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
  } catch {
    return null;
  }
}

// 1. Conflict markers, across every tracked file — a stray marker is always a
//    mistake, and not always in a file you thought you touched.
{
  const tracked = (git(['ls-files']) || '').split('\n').filter(Boolean);
  const offenders = [];

  for (const file of tracked) {
    if (!existsSync(file)) {
      continue;
    }

    let text;
    try {
      text = readFileSync(file, 'utf8');
    } catch {
      continue; // binary or unreadable
    }

    const line = text.split('\n').findIndex((l) => /^(<{7}|={7}|>{7})(\s|$)/.test(l));
    if (line !== -1) {
      offenders.push(`${file}:${line + 1}`);
    }
  }

  if (offenders.length) {
    fail(`conflict markers left in: ${offenders.join(', ')}`);
  } else {
    pass('no conflict markers');
  }
}

// 2. Nothing the base had has been lost.
//
//    Deliberately asymmetric: ADDING to these files is normal (a branch may add
//    an npm script or a plugin header field), while REMOVING something the base
//    branch has is the signature of a resolution that took one side wholesale.
//    A removed line is only reported if its content no longer appears anywhere in
//    the file, so reformatting — a trailing comma appearing when a JSON key stops
//    being last — is not mistaken for deletion.
{
  const isVersionLine = (line) =>
    /(?:Version:|BLUEWORX_LABS_VERSION|"version"\s*:|Stable tag:)/.test(line);

  const normalise = (line) => line.replace(/^[-+]/, '').replace(/[,;]\s*$/, '').trim();

  for (const file of VERSION_FILES) {
    const diff = git(['diff', BASE, '--', file]);

    if (diff === null) {
      skip(`${file} (cannot diff against ${BASE})`);
      continue;
    }

    if (!existsSync(file)) {
      fail(`${file} is missing from the working tree`);
      continue;
    }

    const current = readFileSync(file, 'utf8')
      .split('\n')
      .map((l) => normalise(l))
      .filter(Boolean);
    const currentSet = new Set(current);

    const lost = diff
      .split('\n')
      .filter((l) => l.startsWith('-') && !l.startsWith('---'))
      .filter((l) => !isVersionLine(l))
      .map((l) => normalise(l))
      .filter(Boolean)
      // Still present elsewhere in the file ⇒ moved or reformatted, not lost.
      .filter((content) => !currentSet.has(content));

    if (lost.length) {
      fail(
        `${file} has dropped ${lost.length} line(s) that ${BASE} still has — the ` +
          `resolution probably took one side wholesale:\n      ` +
          lost.slice(0, 6).join('\n      ')
      );
    } else {
      pass(`${file} keeps everything ${BASE} has`);
    }
  }
}

// 3. Every spec still parses.
//
//    node --check is the real JavaScript parser, so this catches the swallowed
//    closing brace and anything else a hand-merge can mangle. An earlier version
//    of this script counted braces and asserted every test() sat at depth 1 —
//    that was tuned to one file and wrongly failed top-level tests and nested
//    describes. A parser has no such blind spot.
{
  if (!existsSync(TESTS_DIR)) {
    skip(`${TESTS_DIR} (no tests directory)`);
  } else {
    const specs = readdirSync(TESTS_DIR).filter((f) => f.endsWith('.spec.js'));

    for (const spec of specs) {
      const path = join(TESTS_DIR, spec);

      try {
        execFileSync(process.execPath, ['--check', path], { stdio: ['ignore', 'pipe', 'pipe'] });
        pass(`${path} parses`);
      } catch (error) {
        const detail = String(error.stderr || error.message)
          .split('\n')
          .filter((l) => /SyntaxError|\^|\.js:\d+/.test(l))
          .slice(0, 3)
          .join(' ')
          .trim();
        fail(`${path} does not parse — ${detail || 'syntax error'}`);
      }
    }
  }
}

if (failed) {
  console.error('\nMerge sanity check failed. Fix before pushing.');
  process.exit(1);
}

console.log('\nMerge sanity check passed.');
