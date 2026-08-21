/**
 * Where the suite points, and what it signs in with.
 *
 * One module, imported by both playwright.config.js and tests/helpers.js, so
 * there is a single answer to "which site am I testing?" rather than two
 * fallbacks that can drift apart.
 *
 * The default is the LOCAL HARNESS, deliberately. `.wp-test/wp` is a WordPress
 * install with this repo symlinked into wp-content/plugins, so it serves the
 * working tree with no build or deploy step — a plain `npx playwright test`
 * therefore tests the code in front of you.
 *
 * It used to default to a staging host instead. A run against staging tests
 * whatever build is deployed there, which passes or fails for reasons that have
 * nothing to do with your branch, and reads as authoritative either way. That
 * cost a full task cycle to spot once. Staging is still reachable — it is now an
 * explicit opt-in rather than what you get by saying nothing.
 *
 * Every value below yields to anything already set in the environment, so CI
 * (which provisions its own WordPress per shard and passes these in) and a
 * one-off command-line override both still win.
 */

/** Local harness defaults — these match what wp-test-env.mjs provisions. */
export const HARNESS_URL = 'http://127.0.0.1:8881';
const HARNESS_USER = 'admin';
const HARNESS_PASS = 'wptest-admin-pw';

/**
 * The plugin's `login` feature moves the sign-in form off wp-login.php and
 * blocks the default path, so the suite cannot find the form without this. It is
 * on in the harness, so the harness slug is the default too.
 */
const HARNESS_LOGIN_PATH = 'admin_login';

export const baseURL =
  process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || HARNESS_URL;

export const ADMIN_USER = process.env.WP_ADMIN_USER || HARNESS_USER;
export const ADMIN_PASS = process.env.WP_ADMIN_PASS || HARNESS_PASS;
export const LOGIN_PATH_RAW = process.env.WP_LOGIN_PATH || HARNESS_LOGIN_PATH;

/**
 * Whether this run is pointed at the local harness.
 *
 * Used to decide whether an unreachable base URL is worth explaining (see
 * tests/global-setup.js): a developer who has not started the harness is the
 * common case and deserves a real message, whereas a staging or CI URL that is
 * down is a genuine failure to report as-is.
 */
export const isHarness = /^https?:\/\/(127\.0\.0\.1|localhost)(:|\/|$)/i.test(baseURL);

/**
 * Whether the target is the old placeholder host.
 *
 * Kept so an explicit placeholder still skips the admin specs rather than
 * failing — the escape hatch for an environment that genuinely has nowhere to
 * point. Nothing defaults to it any more.
 */
export const isPlaceholder = /placeholder/i.test(baseURL);
