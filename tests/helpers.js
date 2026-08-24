/**
 * Shared Playwright helpers.
 *
 * Every admin spec needs the same three things: the base URL, the skip
 * condition, and a login that actually logs in.
 */

import { test as base } from '@playwright/test';
// Imported, not just re-exported: `export ... from` forwards the names without
// binding them in this module, so login() below would see an undefined
// ADMIN_USER and fail with a ReferenceError inside a helper that looks fine.
import { baseURL, isPlaceholder, ADMIN_USER, ADMIN_PASS, LOGIN_PATH_RAW } from './test-target.js';

import { expect } from '@playwright/test';

export { expect };

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

// Passed through, not redefined: playwright.config.js resolves the same values
// from the same module, so the two can no longer disagree about which site is
// under test. See tests/test-target.js for why the default is the local harness.
export { baseURL, isPlaceholder, ADMIN_USER, ADMIN_PASS };

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
export const LOGIN_PATH = `/${String(LOGIN_PATH_RAW).replace(/^.*[/\\]/, '').trim()}`;

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

/**
 * The Enhancements screen's section for a given feature key.
 *
 * Sections now sit behind a section nav rather than stacked down one long page,
 * so a control is only reachable once its section is open. This map is the one
 * place that knowledge lives; it mirrors blueworx_get_feature_definitions().
 */
const FEATURE_SECTIONS = {
  login: 'security',
  site_protection: 'security',
  sso: 'security',
  support_access: 'security',
  user_roles: 'security',
  view_as_role: 'security',
  login_session: 'security',
  login_redirect: 'security',
  xmlrpc: 'security',
  author_slugs: 'security',
  rest_users: 'security',
  application_passwords: 'security',
  comments: 'content',
  page_excerpts: 'content',
  content_tools: 'content',
  revisions: 'content',
  robots_txt: 'content',
  media_tools: 'media',
  translate: 'translation',
  emails: 'notifications',
  profile_cleanup: 'notifications',
  cache_auto: 'performance',
  cache_manual: 'performance',
  menu_editor: 'admin_menu',
  admin_theme: 'appearance',
  admin_bar: 'appearance',
  dashboard_widgets: 'appearance',
};

/**
 * Opens a section on the Enhancements screen.
 *
 * Every panel is in the DOM whichever section is showing — hiding rather than
 * removing them is what keeps the form's POST payload complete — so this is a
 * visibility concern only. Safe to call when the section is already open.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @param {string} section Section id, e.g. "security".
 */
export async function openSection(page, section) {
  // The screen's script is enqueued in the footer, so straight after a save the
  // nav can be on the page a moment before it is wired up. Clicking then does
  // nothing at all, which reads exactly like a broken nav.
  await page.waitForSelector('html[data-blueworx-sections="ready"]');

  const item = page.locator(`[data-blueworx-section="${section}"]`);

  if (!(await item.count())) {
    throw new Error(`No section nav item for "${section}" — is the screen rendered?`);
  }

  await item.click();
  await expect(page.locator(`[data-blueworx-panel="${section}"]`)).toBeVisible();
}

/**
 * Opens whichever section holds a feature, by feature key.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @param {string} feature Feature key, e.g. "sso".
 */
export async function openSectionFor(page, feature) {
  const section = FEATURE_SECTIONS[feature];

  if (!section) {
    throw new Error(`No section known for feature "${feature}" — add it to FEATURE_SECTIONS.`);
  }

  await openSection(page, section);
}

/**
 * Saves the Enhancements form and waits for the confirmation.
 *
 * The confirmation is the design system's notice now, not WordPress's — same
 * message, different markup.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 */
export async function saveEnhancements(page) {
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
}

/**
 * Switches a feature on or off on the Enhancements screen.
 *
 * The toggles are the design system's Switch now: the real checkbox is a
 * zero-size, transparent element behind the track it draws, so clicking the
 * input itself lands on the track instead and times out. The label is what a
 * person clicks, and it is what this clicks.
 *
 * Opens the feature's section first, and does nothing when the toggle is
 * already in the state you asked for.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @param {string} feature Feature key.
 * @param {boolean} checked Desired state.
 */
export async function setFeature(page, feature, checked) {
  await openSectionFor(page, feature);

  const input = page.locator(`input.blueworx-feature-toggle[data-blueworx-feature="${feature}"]`);

  if ((await input.isChecked()) === checked) {
    return;
  }

  await page.locator(`label.bw-switch:has(input[data-blueworx-feature="${feature}"])`).click();
  await expect(input).toBeChecked({ checked });
}

/**
 * Whether a feature is currently on.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @param {string} feature Feature key.
 * @return {Promise<boolean>} True when on.
 */
export async function featureIsOn(page, feature) {
  return page
    .locator(`input.blueworx-feature-toggle[data-blueworx-feature="${feature}"]`)
    .isChecked();
}

/**
 * Reads the freshly generated support access key.
 *
 * The key lives in a read-only field with a copy button beside it, not in a
 * bare <code> block, so it is read by value rather than by text. One helper
 * rather than twenty-odd inline reads: the panel is rebuilt often enough that
 * the next change should only have to land here.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @return {Promise<string>} The 64-character key.
 */
export async function readSupportKey(page) {
  return (await page.locator('[data-testid="bw-support-key"]').inputValue()).trim();
}

/**
 * Reads the ticked values of a design system checkbox group.
 *
 * The role pickers were native multi-selects until the panels moved onto the
 * design system. They are checkbox groups now, so a test that wants "which
 * roles are allowed" asks for the ticked boxes rather than the selected
 * options.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @param {string} name Field name, without the trailing [].
 * @return {Promise<string[]>} Ticked values, in document order.
 */
export async function readCheckedGroup(page, name) {
  return page
    .locator(`input[name="${name}[]"]:checked`)
    .evaluateAll((boxes) => boxes.map((box) => box.value));
}

/**
 * Ticks exactly these values in a checkbox group, clearing every other box.
 *
 * Mirrors selectOption() on the multi-select it replaced: what is not listed
 * ends up unticked, so a caller restoring a captured state gets that state and
 * not a union with whatever was already there.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @param {string} name Field name, without the trailing [].
 * @param {string[]} values Values to leave ticked.
 * @return {Promise<void>}
 */
export async function setCheckedGroup(page, name, values) {
  const boxes = page.locator(`input[name="${name}[]"]`);
  const total = await boxes.count();

  for (let index = 0; index < total; index += 1) {
    const box = boxes.nth(index);
    // eslint-disable-next-line no-await-in-loop
    await box.setChecked(values.includes(await box.inputValue()));
  }
}
