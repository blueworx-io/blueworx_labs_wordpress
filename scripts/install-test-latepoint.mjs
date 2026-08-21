/**
 * Installs the free LatePoint into the local test harness.
 *
 * Why this exists
 * ---------------
 * Two layout bugs in a row came from LatePoint's admin CSS rather than ours:
 * it hides the WordPress menu, resets #wpcontent, and pulls the body up by the
 * height of the admin bar it hides. Our theme compensates for all three. The
 * spec that covers this normally recreates those rules by hand, which proves our
 * cascade is right but cannot notice LatePoint changing the rules underneath us
 * — and both bugs were in fact found by eye, on a live site, after shipping.
 *
 * Run this and tests/latepoint-layout.spec.js stops simulating and measures the
 * real thing.
 *
 * Usage
 * -----
 *   node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up --plugin .
 *   node scripts/install-test-latepoint.mjs
 *   npx playwright test tests/latepoint-layout.spec.js
 *
 * The download is ~5.8MB from wordpress.org. It is deliberately NOT part of
 * harness provisioning: CI builds a fresh WordPress per shard, so wiring this in
 * there would pay that download on every shard of every pull request, for a
 * third-party stylesheet that changes maybe once a year. See issue #114.
 *
 * Safe to re-run — it skips the download when the plugin is already unpacked,
 * and activation is idempotent.
 */

import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

// Pinned rather than "latest" on purpose: the point is a known build we can
// reason about, and an unannounced upstream bump would turn an unrelated pull
// request red. Raise it deliberately, and read the diff when you do.
const VERSION = '5.6.10';
const SLUG = 'latepoint';
const URL = `https://downloads.wordpress.org/plugin/${SLUG}.${VERSION}.zip`;

const wpDir = resolve('.wp-test/wp');
const pluginsDir = join(wpDir, 'wp-content', 'plugins');
const target = join(pluginsDir, SLUG);

function log(message) {
  process.stdout.write(`${message}\n`);
}

function fail(message) {
  process.stderr.write(`${message}\n`);
  process.exit(1);
}

if (!existsSync(wpDir)) {
  fail(
    [
      `No harness found at ${wpDir}.`,
      'Start one first:',
      '',
      '  node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up --plugin .',
    ].join('\n')
  );
}

if (existsSync(target)) {
  log(`${SLUG} already unpacked — skipping download.`);
} else {
  const tmp = resolve('.wp-test/latepoint-download');
  mkdirSync(tmp, { recursive: true });
  const zip = join(tmp, `${SLUG}.zip`);

  log(`Downloading ${SLUG} ${VERSION}…`);
  execFileSync('curl', ['-sSL', '-o', zip, URL], { stdio: 'inherit' });

  log('Unpacking…');
  // `unzip` first, then bsdtar. Order matters on Windows: a bare `tar` in Git
  // Bash resolves to GNU tar, which reads "C:\..." as a remote host and dies
  // with "Cannot connect to C: resolve failed". Windows' own bsdtar lives in
  // System32 and handles the path fine, so it is named explicitly rather than
  // left to PATH. (Never PowerShell's Compress-Archive family here either —
  // same separator mangling as the plugin build.)
  const unpackers = [
    ['unzip', ['-o', '-q', zip, '-d', pluginsDir]],
    [join(process.env.WINDIR || 'C:\\Windows', 'System32', 'tar.exe'), ['-x', '-f', zip, '-C', pluginsDir]],
    ['bsdtar', ['-x', '-f', zip, '-C', pluginsDir]],
  ];

  const failures = [];
  const unpacked = unpackers.some(([command, args]) => {
    try {
      execFileSync(command, args, { stdio: 'pipe' });
      return true;
    } catch (error) {
      failures.push(`  ${command}: ${error.message.split('\n')[0]}`);
      return false;
    }
  });

  if (!unpacked) {
    fail(['Could not unpack the archive. Tried:', ...failures].join('\n'));
  }

  rmSync(tmp, { recursive: true, force: true });
}

if (!existsSync(join(target, `${SLUG}.php`))) {
  fail(`Unpacked, but ${join(target, `${SLUG}.php`)} is missing — the archive layout changed.`);
}

// Activation goes through WordPress itself rather than a row poked into the
// options table: LatePoint creates its own database tables on activation, and
// its admin screens are blank without them.
const bootstrap = join(wpDir, 'install-test-latepoint-bootstrap.php');
writeFileSync(
  bootstrap,
  `<?php
define( 'WP_USE_THEMES', false );
require_once __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$res = activate_plugin( '${SLUG}/${SLUG}.php', '', false, false );
echo is_wp_error( $res ) ? 'ACTIVATION_ERROR: ' . $res->get_error_message() : 'LATEPOINT_ACTIVE';
echo "\\n";
`
);

try {
  const out = execFileSync('php', [bootstrap], { encoding: 'utf8', cwd: wpDir });
  if (out.includes('ACTIVATION_ERROR')) {
    fail(out.trim());
  }
  log(out.trim());
} finally {
  rmSync(bootstrap, { force: true });
}

log('');
log('Done. The real-install layout spec will now run instead of skipping:');
log('  npx playwright test tests/latepoint-layout.spec.js');
