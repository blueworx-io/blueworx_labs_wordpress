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
console.log(`version:check OK — plugin header and package.json agree (${headerVersion}).`);
