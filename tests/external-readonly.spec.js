import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  baseURL,
  LOGIN_PATH,
  cacheBust,
} from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const VIEWER = 'bw_external_test';
const VIEWER_PASS = 'ExternalTest!2026';

// The text blueworx_readonly_block_writes() (includes/readonly-access.php) dies
// with for a refused non-GET request. Asserting on it, rather than only the
// response status, is what pins the refusal on the read-only guard rather than
// on something else that also happens to answer 403 — check_admin_referer(),
// for instance, refuses a nonce-less POST to admin-post.php for an
// administrator too, and the external role keeps manage_options. A test that
// only checked the status code would stay green even if the entire read-only
// block were deleted.
const READONLY_WRITE_MESSAGE = 'This account is read-only. Nothing on this site can be changed from it.';

// The same shape support-access.spec.js uses to reach the harness's wp-load.php.
function fixture(script, ...args) {
  const fixturePath = path.join(__dirname, 'fixtures', script);
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  return execFileSync('php', [fixturePath, wpLoad, ...args], { encoding: 'utf8' });
}

async function signInAsViewer(page) {
  // The custom login URL, not wp-login.php — includes/login-security.php blocks
  // the default path, and a test that used it would be testing that block.
  // Cache-busted the same way helpers.js's own login() is: a Varnish-cached
  // login page carries a stale nonce and test cookie, which makes logins fail
  // intermittently.
  await page.goto(cacheBust(LOGIN_PATH));
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

  test('the viewer can open a screen an ordinary subscriber could not', async ({ page }) => {
    // "Can look at everything an administrator sees" is the other half of the
    // promise this role makes, and nothing else here tests it. activate_plugins
    // is not one of the capabilities blueworx_readonly_removed_caps() strips, so
    // this account — unlike a subscriber, who is refused this screen outright —
    // can read it, with real content rather than an empty shell.
    await signInAsViewer(page);

    const response = await page.goto('/wp-admin/plugins.php');

    expect(response.status()).toBe(200);
    await expect(page.locator('h1')).toContainText('Plugins');

    const rows = page.locator('#the-list tr');
    expect(await rows.count()).toBeGreaterThan(0);
  });

  test('a POST is refused by the read-only guard, not by something else that would also 403', async ({
    page,
    request,
  }) => {
    await signInAsViewer(page);

    const cookies = await page.context().cookies();
    const jar = cookies.map((c) => `${c.name}=${c.value}`).join('; ');

    const response = await request.post(`${baseURL}/wp-admin/admin-post.php`, {
      headers: { cookie: jar },
      form: { action: 'blueworx_toggle_support_feature' },
      maxRedirects: 0,
    });

    expect(response.status()).toBe(403);
    // check_admin_referer() would also 403 a nonce-less POST like this one for
    // a full administrator — the external role keeps manage_options — so the
    // status alone proves nothing about which code refused it. The body must
    // carry the read-only guard's own wording.
    expect(await response.text()).toContain(READONLY_WRITE_MESSAGE);
  });

  test('a REST write is refused by the read-only guard specifically', async ({ page, request }) => {
    await signInAsViewer(page);

    const cookies = await page.context().cookies();
    const jar = cookies.map((c) => `${c.name}=${c.value}`).join('; ');

    const response = await request.post(`${baseURL}/wp-json/wp/v2/posts`, {
      headers: { cookie: jar },
      data: { title: 'Should never exist' },
    });

    expect(response.status()).toBe(403);

    // For a cookie-authenticated caller, blueworx_readonly_block_writes() —
    // the very same "init" guard the admin-post.php test above hits, not
    // includes/readonly-access.php's separate rest_pre_dispatch net — is what
    // actually fires here too: it runs before REST routing even begins, so it
    // is the first thing to see this request regardless of where it is headed.
    // WordPress's own wp_die() renders that as JSON (code "wp_die") rather
    // than HTML only because Playwright's JSON body sets a
    // Content-Type: application/json header, which is what
    // wp_is_json_request() keys on — confirmed by hand against this same
    // account with curl: a form-encoded POST to the same URL gets the
    // identical refusal as a plain HTML page instead. The REST-specific
    // rest_pre_dispatch filter is a second net for a caller not yet
    // recognised as read-only at "init" (Application Passwords, resolved
    // later) — not reachable from an ordinary logged-in browser session, so
    // asserting on ITS error code here would test a path this request never
    // takes. The message text is what is actually guaranteed either way.
    expect((await response.json()).message).toBe(READONLY_WRITE_MESSAGE);
  });

  test('the users screen is refused', async ({ page }) => {
    await signInAsViewer(page);
    await page.goto('/wp-admin/users.php');

    await expect(page.getByText('personal data')).toBeVisible();
  });

  test('there is no trash link on the posts list, which itself renders real rows', async ({
    page,
  }) => {
    await signInAsViewer(page);
    const response = await page.goto('/wp-admin/edit.php');

    // Without this, toHaveCount(0) below is satisfied just as well by a
    // refused page or an empty list as by a working one with the link
    // correctly removed — proving nothing about the capability strip.
    expect(response.status()).toBe(200);
    const rows = page.locator('#the-list tr');
    expect(await rows.count()).toBeGreaterThan(0);

    await expect(page.locator('a.submitdelete')).toHaveCount(0);
  });

  test('a hand-built GET trash link is refused even with a real, scraped nonce', async ({
    page,
  }) => {
    // The write block in blueworx_readonly_block_writes() only refuses
    // non-GET methods, and WordPress trashes over a nonce'd GET link
    // (post.php?action=trash) that block never sees — blueworx_readonly_
    // gate_write_actions() is what has to catch it instead. The capability
    // layer (delete_posts stripped) is covered by the previous test; this
    // covers the request layer independently, the way support-access.spec.js's
    // "cannot trash or delete content over core GET links" does for the
    // support role, which shares this same guard.
    await signInAsViewer(page);

    await page.goto('/wp-admin/edit.php');
    const rowId = await page.locator('#the-list tr').first().getAttribute('id');
    const postId = Number(String(rowId).replace('post-', ''));
    expect(postId).toBeGreaterThan(0);

    // A real nonce, scraped from a page this account can legitimately read —
    // not a fabricated one a real attack would not have. edit.php's bulk
    // action form carries one.
    const nonce = await page.evaluate(() => {
      const el = document.querySelector('#_wpnonce, input[name="_wpnonce"]');
      return el ? el.value : '';
    });
    expect(nonce, 'a real nonce must be scrapeable from the page this account can read').toBeTruthy();

    const trashed = await page.goto(
      `/wp-admin/post.php?action=trash&post=${postId}&_wpnonce=${nonce}`
    );
    expect(trashed.status(), 'GET trash must be refused').toBe(403);

    // Nothing was actually trashed.
    const check = await page.goto(`/wp-admin/post.php?post=${postId}&action=edit`);
    expect(check.status()).toBe(200);
    await expect(page.locator('#original_post_status')).toHaveValue('publish');
  });

  test('the Duplicate row action is refused, and duplicates nothing', async ({ page }) => {
    // The hole this covers: Duplicate is a GET link to admin-post.php, and
    // blueworx_readonly_block_writes() used to refuse non-GET methods only. The
    // external role keeps edit_posts, edit_others_posts, edit_published_posts
    // and publish_posts, so the link renders on every row for an invited
    // viewer, carrying a nonce minted for their own session — and one click
    // created a draft. blueworx_readonly_is_action_endpoint() is what refuses
    // the endpoint now, whatever the method.
    await signInAsViewer(page);

    const before = Number(fixture('count-posts.php').trim());
    expect(before).toBeGreaterThan(0);

    await page.goto('/wp-admin/edit.php');

    // The link must still be ON the page. The fix is in the request guard, not
    // in hiding the control: if a later change removed the link instead, this
    // test would go green while any hand-built URL still worked.
    const link = page.locator('a[href*="action=blueworx_duplicate_post"]').first();
    await expect(link).toHaveCount(1);

    const href = await link.getAttribute('href');
    expect(href, 'the Duplicate link must carry a real nonce').toContain('_wpnonce=');

    const refused = await page.goto(href);

    expect(refused.status(), 'Duplicate must be refused').toBe(403);
    // Status alone proves nothing — check_admin_referer() answers 403 too. The
    // body has to carry the read-only guard's own wording.
    await expect(page.locator('.wp-die-message')).toContainText(READONLY_WRITE_MESSAGE);

    // And nothing was actually copied.
    expect(Number(fixture('count-posts.php').trim())).toBe(before);
  });

  test('XML-RPC cannot write, even with the endpoint open', async ({ request }) => {
    // xmlrpc.php authenticates from the request body, long after "init" has
    // run blueworx_readonly_block_writes() against an anonymous user, and there
    // is no XML-RPC equivalent of the rest_pre_dispatch net. wp.newPost checks
    // only capabilities this role keeps, so the endpoint was a complete way
    // round the guard. blueworx_readonly_block_xmlrpc() closes it.
    //
    // The endpoint is opened for this test because the plugin's own xmlrpc
    // function closes it by default — which masks the hole rather than fixing
    // it, and is a switch site owners are told to turn off for Jetpack or the
    // mobile app.
    fixture('xmlrpc-endpoint.php', 'open');

    try {
      const call = (method, params) =>
        `<?xml version="1.0"?><methodCall><methodName>${method}</methodName><params>${params}</params></methodCall>`;
      const creds = (user, pass) =>
        `<param><value><string>${user}</string></value></param><param><value><string>${pass}</string></value></param>`;
      const post = (user, pass) =>
        call(
          'wp.newPost',
          `<param><value><int>1</int></value></param>${creds(user, pass)}` +
            '<param><value><struct>' +
            '<member><name>post_type</name><value><string>post</string></value></member>' +
            '<member><name>post_status</name><value><string>draft</string></value></member>' +
            '<member><name>post_title</name><value><string>Should never exist</string></value></member>' +
            '</struct></value></param>'
        );

      // The endpoint really is live and really does authenticate — without this
      // the refusal below would be satisfied just as well by a closed endpoint,
      // proving nothing about the guard. A read, so it leaves nothing behind.
      const live = await request.post(`${baseURL}/xmlrpc.php`, {
        headers: { 'content-type': 'text/xml' },
        data: call('wp.getUsersBlogs', creds(ADMIN_USER, ADMIN_PASS)),
      });

      expect(await live.text(), 'XML-RPC must be reachable for this test to mean anything').not.toContain(
        '<fault>'
      );

      const before = Number(fixture('count-posts.php').trim());

      const refused = await request.post(`${baseURL}/xmlrpc.php`, {
        headers: { 'content-type': 'text/xml' },
        data: post(VIEWER, VIEWER_PASS),
      });

      const body = await refused.text();

      // Core's wp_xmlrpc_server::login() replaces any authenticate error with
      // its own generic wording, so the guard's message never reaches the
      // caller — deliberate on core's part, and it means the fault is what can
      // be asserted here. The account's password is provably good: every other
      // test in this file signs in with it.
      expect(body).toContain('<fault>');
      expect(body).toContain('403');

      // The only claim that really matters.
      expect(Number(fixture('count-posts.php').trim())).toBe(before);
    } finally {
      // Always put the site's own setting back, including when an expectation
      // above failed — a left-open endpoint would change what every later run
      // is testing.
      fixture('xmlrpc-endpoint.php', 'restore');
    }
  });

  test('an expired viewer cannot sign in', async ({ page }) => {
    fixture('force-external-expiry.php', VIEWER);

    await page.goto(cacheBust(LOGIN_PATH));
    await page.fill('#user_login', VIEWER);
    await page.fill('#user_pass', VIEWER_PASS);
    await page.click('#wp-submit');

    await expect(page.getByText('This access has ended')).toBeVisible();
  });
});
