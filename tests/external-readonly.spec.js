import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, baseURL, LOGIN_PATH } from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const VIEWER = 'bw_external_test';
const VIEWER_PASS = 'ExternalTest!2026';

// The same shape support-access.spec.js uses to reach the harness's wp-load.php.
function fixture(script, ...args) {
  const fixturePath = path.join(__dirname, 'fixtures', script);
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  return execFileSync('php', [fixturePath, wpLoad, ...args], { encoding: 'utf8' });
}

async function signInAsViewer(page) {
  // The custom login URL, not wp-login.php — includes/login-security.php blocks
  // the default path, and a test that used it would be testing that block.
  await page.goto(LOGIN_PATH);
  await page.fill('#user_login', VIEWER);
  await page.fill('#user_pass', VIEWER_PASS);
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/);
}

test.describe('An external viewer changes nothing', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test.beforeAll(() => {
    fixture('external-viewer.php', 'create');
  });

  test.afterAll(() => {
    fixture('external-viewer.php', 'delete');
  });

  test('the dashboard opens', async ({ page }) => {
    await signInAsViewer(page);

    await expect(page).toHaveURL(/wp-admin/);
  });

  test('a POST is refused', async ({ page, request }) => {
    await signInAsViewer(page);

    const cookies = await page.context().cookies();
    const jar = cookies.map((c) => `${c.name}=${c.value}`).join('; ');

    const response = await request.post(`${baseURL}/wp-admin/admin-post.php`, {
      headers: { cookie: jar },
      form: { action: 'blueworx_toggle_support_feature' },
      maxRedirects: 0,
    });

    expect(response.status()).toBe(403);
  });

  test('a REST write is refused', async ({ page, request }) => {
    await signInAsViewer(page);

    const cookies = await page.context().cookies();
    const jar = cookies.map((c) => `${c.name}=${c.value}`).join('; ');

    const response = await request.post(`${baseURL}/wp-json/wp/v2/posts`, {
      headers: { cookie: jar },
      data: { title: 'Should never exist' },
    });

    expect(response.status()).toBe(403);
  });

  test('the users screen is refused', async ({ page }) => {
    await signInAsViewer(page);
    await page.goto('/wp-admin/users.php');

    await expect(page.getByText('personal data')).toBeVisible();
  });

  test('there is no trash link on the posts list', async ({ page }) => {
    await signInAsViewer(page);
    await page.goto('/wp-admin/edit.php');

    await expect(page.locator('a.submitdelete')).toHaveCount(0);
  });

  test('an expired viewer cannot sign in', async ({ page }) => {
    fixture('force-external-expiry.php', VIEWER);

    await page.goto(LOGIN_PATH);
    await page.fill('#user_login', VIEWER);
    await page.fill('#user_pass', VIEWER_PASS);
    await page.click('#wp-submit');

    await expect(page.getByText('This access has ended')).toBeVisible();
  });
});
