#!/usr/bin/env node
// Local mirror of the foundation header<->package.json version-sync guardrail.
import { readFileSync } from 'node:fs';

const SLUG = 'blueworx-labs-wordpress';
let header;
try {
  header = readFileSync(`${SLUG}.php`, 'utf8');
} catch (err) {
  console.error(`version:check FAILED — cannot read plugin main file ${SLUG}.php: ${err.message}`);
  process.exit(1);
}
const headerMatch = header.match(/^\s*\*?\s*Version:\s*(.+)$/im);
const headerVersion = headerMatch ? headerMatch[1].trim() : null;
const pkgVersion = JSON.parse(readFileSync('package.json', 'utf8')).version;

if (!headerVersion) {
  console.error('version:check FAILED — no "Version:" header found in the plugin main file.');
  process.exit(1);
}
if (headerVersion !== pkgVersion) {
  console.error(`version:check FAILED — plugin header ${headerVersion} !== package.json ${pkgVersion}.`);
  process.exit(1);
}

// readme.txt's stable tag, which the two above do not imply. Nothing reads it
// until the plugin is served from an update endpoint, and then it is the ONLY
// number that decides which release a site is offered — a stale tag offers an
// old version, or none at all. Because nothing breaks in the meantime it drifts
// silently: it sat at 1.61.5 through fourteen releases. Checked here so a bump
// that forgets it fails at the bump rather than at the deploy.
const readme = readFileSync('readme.txt', 'utf8');
const stableMatch = readme.match(/^Stable tag:\s*(.+)$/im);
const stableTag = stableMatch ? stableMatch[1].trim() : null;

if (!stableTag) {
  console.error('version:check FAILED — no "Stable tag:" line found in readme.txt.');
  process.exit(1);
}
if (stableTag !== pkgVersion) {
  console.error(`version:check FAILED — readme.txt stable tag ${stableTag} !== package.json ${pkgVersion}.`);
  process.exit(1);
}

console.log(`version:check OK — plugin header, package.json and readme.txt agree (${headerVersion}).`);
