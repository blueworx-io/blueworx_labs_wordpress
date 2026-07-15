/**
 * Shared Playwright helpers.
 *
 * Every admin spec needs the same three things: the base URL, the skip
 * condition, and a login that actually logs in.
 */

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
  await page.goto(LOGIN_PATH);

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
