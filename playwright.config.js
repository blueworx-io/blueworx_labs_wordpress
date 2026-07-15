import { defineConfig } from '@playwright/test';

const baseURL =
  process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || 'https://staging.placeholder.blueworx.io';

export default defineConfig({
  testDir: './tests',
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
  use: {
    baseURL,
    // A remote host is slower than localhost for both.
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
  },
  reporter: 'line',
});
