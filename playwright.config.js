import { defineConfig } from '@playwright/test';
import dotenv from 'dotenv';

// Loads tests/../.env into process.env for local runs, so credentials do not
// have to be pasted onto every command line (where they land in shell history).
// .env is gitignored; copy .env.example to .env and fill it in.
//
// Never overrides a variable that is already set, so CI — which injects these as
// real secrets, with no .env file present — always wins.
//
// MUST run before ./tests/test-target.js is imported: that module reads
// process.env at import time, so loading .env after it would leave it reading a
// half-empty environment. A static `import` would be hoisted above this call,
// hence the dynamic import on the line after.
dotenv.config({ quiet: true });

const { baseURL } = await import('./tests/test-target.js');

export default defineConfig({
  testDir: './tests',
  // Defaults to the local harness (see tests/test-target.js), so a bare run
  // tests the working tree. This tells you plainly when it is not running.
  globalSetup: './tests/global-setup.js',
  // These specs mutate ONE shared WordPress: they toggle feature flags, save
  // settings, and hide menu items, then restore. Run them in parallel and they
  // corrupt each other — one spec's "flag off" is another's "flag on", and a
  // spec that fails mid-way leaves the site dirty for every later run. Serialise.
  workers: 1,
  fullyParallel: false,
  // These specs drive a REMOTE WordPress, so a single test can make a dozen
  // round trips at a few seconds each. The old 30s budget was not enough for the
  // toggle tests (log in, save, reload, restore) and expired mid-action, which
  // surfaced as a misleading "locator.setChecked: Test timeout exceeded" — as if
  // the element were unactionable, when it was only slow to get to.
  timeout: 120_000,
  expect: { timeout: 10_000 },
  // The local harness serves WordPress from PHP's single-threaded built-in
  // server, so a sign-in occasionally times out under load — seen twice in one
  // run, then not at all on the next with no code change. One retry absorbs
  // that without hiding a genuine failure, which would fail twice.
  retries: 1,
  use: {
    baseURL,
    // NOTE: `reducedMotion: 'reduce'` does NOT belong here. It looks like it
    // works and silently does nothing — see the fixture in tests/helpers.js,
    // which applies it the only way that takes effect.
    //
    // A remote host is slower than localhost for both.
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
  },
  reporter: 'line',
});
