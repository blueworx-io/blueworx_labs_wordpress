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
    const atEnd = next === -1;
    const end = atEnd ? changelog.length : next + 1;
    const before = changelog.slice(0, end).replace(/\n+$/, '\n');
    // A following heading needs a blank line before it; the end of the file
    // needs no extra trailing newline.
    const after = atEnd ? '' : `\n${changelog.slice(end)}`;
    return `${before}\n${body}\n${after}`;
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
