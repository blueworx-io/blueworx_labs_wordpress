/**
 * Shared Playwright helpers.
 *
 * Every admin spec needs the same three things: the base URL, the skip
 * condition, and a login that actually logs in.
 */

import { test as base } from '@playwright/test';

export { expect } from '@playwright/test';

/**
 * The `test` every spec must import — from here, NOT from '@playwright/test'.
 *
 * WordPress 6.9 ships cross-document view transitions in wp-admin:
 *
 *   @media (prefers-reduced-motion: no-preference) {
 *     @view-transition { navigation: auto; }
 *     #adminmenu > .menu-top { view-transition-name: attr(id type(<custom-ident>),none); }
 *   }
 *
 * In headless Chromium those transitions permanently stop the page from being
 * rendered. requestAnimationFrame never fires again — while timers keep running
 * and the DOM stays queryable, so the page looks perfectly healthy. Playwright's
 * actionability "stable" check is built on rAF, so from that moment EVERY click,
 * setChecked and hover hangs until it times out, reporting
 *
 *   waiting for element to be visible, enabled and stable
 *
 * about an element that is provably visible, enabled, stable and hit-testable.
 * The element is never the problem; the renderer is.
 *
 * Only a page-initiated same-origin navigation arms it — a link click or a form
 * submit. page.goto() is browser-initiated and does not, which is why the first
 * click of a test always works and the one after it never does, and why a spec
 * that only navigates and asserts passes while any spec that clicks twice fails.
 *
 * Measured: healthy 73 frames/1.2s before a click, 0 frames/1.2s after one, and
 * it never recovers — not across goto, reload, bringToFront or a resize.
 *
 * The site itself is fine. Real browsers finish the transition and keep
 * painting; this is a headless-only interaction.
 *
 * Emulating reduced motion opts out of core's rule at source. It MUST be done
 * imperatively: `use: { reducedMotion: 'reduce' }` in playwright.config.js is
 * accepted and then silently ignored (verified on @playwright/test 1.61.1 —
 * matchMedia still reports no-preference and the freeze still happens). That
 * false negative is why this bug survived two debugging sessions: the right
 * hypothesis was tested with a no-op and cleared.
 */
export const test = base.extend({
  page: async ({ page }, use) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await use(page);
  },
});

export const baseURL =
  process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || 'https://staging.placeholder.blueworx.io';

export const isPlaceholder = /placeholder/i.test(baseURL);
export const ADMIN_USER = process.env.WP_ADMIN_USER;
export const ADMIN_PASS = process.env.WP_ADMIN_PASS;

/**
 * Path of the login form.
 *
 * The plugin's `login` feature moves the form off wp-login.php to a custom slug
 * and blocks the default path, so this must be configurable per environment. Set
 * WP_LOGIN_PATH to the site's slug when that feature is on — with or without a
 * leading slash; both work:
 *
 *   WP_LOGIN_PATH=admin_login npx playwright test
 *
 * Prefer the slug WITHOUT a leading slash on Git Bash / MSYS: it rewrites values
 * that look like absolute POSIX paths into Windows ones, turning "/admin_login"
 * into "c:/Program Files/Git/admin_login" before Node sees it. Normalising here
 * means callers cannot get it wrong either way.
 */
const rawLoginPath = process.env.WP_LOGIN_PATH || 'wp-login.php';
export const LOGIN_PATH = `/${String(rawLoginPath).replace(/^.*[/\\]/, '').trim()}`;

export const DASH_PATH = '/wp-admin/index.php';

let bustCounter = 0;

/**
 * Appends a unique query arg to defeat an edge cache.
 *
 * Cloudways fronts the site with Varnish, which caches LOGGED-OUT responses even
 * though WordPress marks the login page `no-cache, no-store, private`. Observed
 * live: /admin_login served with `X-Cache: HIT` and `Age: 14897` — a 4-hour-old
 * copy. That is not cosmetic for tests:
 *
 *  - a stale login page carries a stale nonce and test cookie, so logins fail at
 *    random, and
 *  - assertions about logged-out pages test whatever was cached hours ago rather
 *    than the code under test. It made the working branded-login feature look
 *    broken.
 *
 * Logged-in admin requests are not affected (the auth cookie bypasses the cache),
 * so only logged-out navigations need this.
 *
 * @param {string} path Path to bust.
 * @return {string} Path with a unique query arg.
 */
export function cacheBust(path) {
  bustCounter += 1;
  const unique = `${process.pid}-${bustCounter}-${Math.random().toString(36).slice(2, 10)}`;
  return `${path}${path.includes('?') ? '&' : '?'}bw_test_nocache=${unique}`;
}

/**
 * Like cacheBust(), for a request that must ALSO stay recognised as a clean,
 * owned-page request by blueworx_public_is_owned_request_path()'s query
 * allowlist (includes/public/pages.php) — e.g. asserting a plugin-owned page
 * stays exempt (200) from Site Protection. cacheBust()'s own "bw_test_nocache"
 * key is deliberately NOT on that allowlist (only a handful of tracking
 * params are), so appending it would trip the very check the request is
 * trying to pin, gating a request that should be exempt. This reuses
 * "utm_content" — one of the allowlisted tracking params — with a unique
 * value instead, so the request still defeats the cache without adding a
 * disqualifying key.
 *
 * @param {string} path Path to bust.
 * @return {string} Path with a unique, allowlisted query arg.
 */
export function cacheBustExempt(path) {
  bustCounter += 1;
  const unique = `${process.pid}-${bustCounter}-${Math.random().toString(36).slice(2, 10)}`;
  return `${path}${path.includes('?') ? '&' : '?'}utm_content=bw-test-${unique}`;
}

/**
 * Logs into wp-admin, and throws if it did not work.
 *
 * The previous per-spec helper did `goto('/wp-admin/')` and filled the form only
 * `if (#user_login)` — but the `site_protection` feature redirects logged-out
 * visitors from wp-admin to the front page, where no login form exists. The
 * condition was simply false, so the helper silently did nothing and every test
 * ran logged out, failing later on unrelated assertions.
 *
 * NEVER probe wp-admin before logging in. blueworx_redirect_home()
 * (includes/helpers.php) sends a 301 — permanent, and therefore cached by the
 * browser. One logged-out hit on /wp-admin poisons that URL in the context for
 * the rest of its life: every later visit follows the cached redirect to the
 * front page WITHOUT asking the server, so the session looks logged out even
 * though the auth cookie is set. Verified: identical flows differ only by a
 * pre-login wp-admin visit, and only the one that skips it reaches the
 * dashboard. Go straight to the login form.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 */
export async function login(page) {
  // Cache-busted: a Varnish-cached login page carries a stale nonce and test
  // cookie, which makes logins fail intermittently. See cacheBust().
  await page.goto(cacheBust(LOGIN_PATH));

  if (await page.locator('body.wp-admin').count()) {
    return;
  }

  if (!(await page.locator('#user_login').count())) {
    throw new Error(
      `No login form at ${LOGIN_PATH}. If the site uses a custom login slug, set WP_LOGIN_PATH.`
    );
  }

  await page.fill('#user_login', ADMIN_USER);
  await page.fill('#user_pass', ADMIN_PASS);
  await page.click('#wp-submit');
  await page.waitForLoadState('domcontentloaded');

  await page.goto(DASH_PATH);

  if (!(await page.locator('body.wp-admin').count())) {
    const error = await page.locator('#login_error').innerText().catch(() => '');
    throw new Error(`Login failed via ${LOGIN_PATH}. ${error.trim()}`.trim());
  }
}

/**
 * Runs a set of state-restoring cleanup steps to completion, one after another,
 * even if an earlier step throws — so a failure restoring one piece of mutated
 * global state can never skip restoring another, unrelated piece. Every step is
 * attempted; collected errors are re-thrown together at the end so a genuine
 * cleanup failure still fails the test loudly rather than being swallowed.
 *
 * @param {Array<[string, () => Promise<void>]>} steps [label, step] pairs.
 */
export async function restoreAll(steps) {
  const errors = [];
  for (const [label, step] of steps) {
    try {
      await step();
    } catch (error) {
      errors.push(`${label}: ${error && error.message ? error.message : String(error)}`);
    }
  }
  if (errors.length > 0) {
    throw new Error(
      `Cleanup failed for ${errors.length} of ${steps.length} restore step(s):\n${errors.join('\n')}`
    );
  }
}
