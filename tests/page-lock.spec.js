/**
 * Locked pages.
 *
 * An administrator locks a page; a client can then change its words but not
 * its blocks, and cannot clear the lock. The lock has to hold where a client
 * actually edits — the block editor — and where a connected tool does: the
 * REST API. Both are driven here, as the client, against a page the admin
 * owns, and the admin unlocks it at the end to show that still works.
 */

import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  LOGIN_PATH,
  login,
  cacheBust,
} from './helpers.js';

const PARAGRAPH = (text) => `<!-- wp:paragraph --><p>${text}</p><!-- /wp:paragraph -->`;
const CONTENT = PARAGRAPH('First words.') + PARAGRAPH('Second words.');

async function restNonce(page) {
  return (await (await page.request.get('/wp-admin/admin-ajax.php?action=rest-nonce')).text()).trim();
}

/**
 * Opens a page in the block editor and gets the welcome tour out of the way.
 * The tour appears a moment after the canvas does, so it is looked for after.
 */
async function openEditor(page, id) {
  await page.goto(`/wp-admin/post.php?post=${id}&action=edit`);
  await expect(page.frameLocator('iframe[name="editor-canvas"]').locator('.editor-styles-wrapper')).toBeVisible({ timeout: 60_000 });
  // Two of WordPress's own pop-ups can sit over the editor: the welcome tour
  // for a first visit, and "someone else is editing" when the other person
  // opened the page moments ago. Neither is ours. They appear a little after
  // the canvas does, so keep clearing them until the screen stays clear.
  const overlay = page.locator('.components-modal__screen-overlay');
  const deadline = Date.now() + 30000;
  let clearFor = 0;
  while (Date.now() < deadline && clearFor < 2) {
    if (await overlay.count()) {
      clearFor = 0;
      const dialog = page.getByRole('dialog').first();
      // "Take over" is drawn as a link to the take-over address, not a button.
      const takeOver = dialog.getByRole('link', { name: /take over/i }).or(dialog.getByRole('button', { name: /take over/i }));
      const close = dialog.getByRole('button', { name: /^close$/i }).first();
      if (await takeOver.count()) {
        await takeOver.first().click();
        await page.waitForLoadState('domcontentloaded');
        await expect(page.frameLocator('iframe[name="editor-canvas"]').locator('.editor-styles-wrapper')).toBeVisible({ timeout: 60_000 });
      } else if (await close.count()) {
        await close.click();
      } else {
        throw new Error('An unexpected pop-up is over the editor: ' + (await dialog.innerText()).slice(0, 400));
      }
      await overlay.first().waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
    } else {
      clearFor += 1;
    }
    await page.waitForTimeout(1000);
  }
  await expect(overlay).toHaveCount(0);
}

async function signIn(page, user, pass) {
  await page.goto(cacheBust(LOGIN_PATH));
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await page.click('#wp-submit');
  await expect(page.locator('body')).toHaveClass(/wp-admin|wp-core-ui/);
}

test.describe('Locked pages', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('a new page is locked as soon as it is made', async ({ page }) => {
    await login(page);
    const headers = { 'X-WP-Nonce': await restNonce(page) };
    const created = await page.request.post('/wp-json/wp/v2/pages', {
      headers,
      data: { title: `New page ${Date.now()}`, status: 'draft', content: CONTENT },
    });
    expect(created.ok(), await created.text()).toBe(true);
    const post = await created.json();
    expect(post.meta._blueworx_page_lock).toBe(true);
    await page.request.delete(`/wp-json/wp/v2/pages/${post.id}?force=true`, { headers });
  });

  test('a client can change the words on a locked page, and nothing else, until an admin unlocks it', async ({ page, browser }) => {
    test.setTimeout(300_000);
    await login(page);
    const nonce = await restNonce(page);
    const headers = { 'X-WP-Nonce': nonce };
    const stamp = Date.now();
    let pageId = 0;
    let userId = 0;

    try {
      // The admin makes a page and locks it.
      const created = await page.request.post('/wp-json/wp/v2/pages', {
        headers,
        data: { title: `Locked page ${stamp}`, status: 'publish', content: CONTENT, meta: { _blueworx_page_lock: true } },
      });
      expect(created.ok(), await created.text()).toBe(true);
      const post = await created.json();
      pageId = post.id;
      expect(post.meta._blueworx_page_lock).toBe(true);

      // Searched by its own title: on a busy site the list runs to several screens.
      await page.goto(`/wp-admin/edit.php?post_type=page&s=${encodeURIComponent(`Locked page ${stamp}`)}`);
      await expect(page.locator(`#post-${pageId} .post-state`)).toContainText('Locked');

      // The admin's own panel carries the switch, and it is on. Done before
      // the client opens the page, so WordPress's edit lock passes from the
      // admin to the client rather than the other way round.
      await openEditor(page, pageId);
      const adminPanel = page.locator('.blueworx-page-lock');
      await expect(adminPanel).toBeVisible();
      if ((await adminPanel.locator('.components-panel__body-toggle').getAttribute('aria-expanded')) === 'false') {
        await adminPanel.locator('.components-panel__body-toggle').click();
      }
      await expect(adminPanel.locator('input[type="checkbox"]')).toBeChecked();
      // Leave the editor so the admin is not still "editing" while the client is.
      await page.goto('/wp-admin/edit.php?post_type=page');

      // A client account: an editor, which is as much as any client gets.
      const login = `pw-client-${stamp}`;
      const password = `pw-client-${stamp}!Aa`;
      const user = await page.request.post('/wp-json/wp/v2/users', {
        headers,
        data: { username: login, email: `${login}@example.test`, password, roles: ['editor'] },
      });
      expect(user.ok(), await user.text()).toBe(true);
      userId = (await user.json()).id;

      const context = await browser.newContext({ storageState: undefined });
      const client = await context.newPage();
      // See helpers.js: without this, the first link click freezes headless
      // Chromium's rendering and every click after it hangs.
      await client.emulateMedia({ reducedMotion: 'reduce' });
      await signIn(client, login, password);
      const clientNonce = await restNonce(client);
      const clientHeaders = { 'X-WP-Nonce': clientNonce };

      // Through the API: words yes, blocks no, lock never.
      const reworded = await client.request.post(`/wp-json/wp/v2/pages/${pageId}`, {
        headers: clientHeaders,
        data: { content: PARAGRAPH('First words, reworded.') + PARAGRAPH('Second words.') },
      });
      expect(reworded.ok(), await reworded.text()).toBe(true);

      const added = await client.request.post(`/wp-json/wp/v2/pages/${pageId}`, {
        headers: clientHeaders,
        data: { content: CONTENT + PARAGRAPH('A third block.') },
      });
      expect(added.status()).toBe(403);
      expect((await added.json()).message).toContain('locked');

      const unlockAttempt = await client.request.post(`/wp-json/wp/v2/pages/${pageId}`, {
        headers: clientHeaders,
        data: { meta: { _blueworx_page_lock: false } },
      });
      expect(unlockAttempt.ok()).toBe(false);

      // The words got through and the block did not.
      await page.goto(cacheBust(post.link));
      await expect(page.locator('body')).toContainText('First words, reworded.');
      await expect(page.locator('body')).not.toContainText('A third block.');

      // In the editor: no inserter, and told why.
      await openEditor(client, pageId);
      // The inserter button itself stays drawn in this WordPress; what the
      // editor will actually allow is the thing to check.
      const allowed = await client.evaluate(() => {
        const editor = wp.data.select('core/block-editor');
        return {
          lock: editor.getTemplateLock(),
          canInsert: editor.canInsertBlockType('core/paragraph'),
          canRemove: editor.canRemoveBlock(editor.getBlockOrder()[0]),
          canMove: editor.canMoveBlock(editor.getBlockOrder()[0]),
        };
      });
      expect(allowed).toEqual({ lock: 'all', canInsert: false, canRemove: false, canMove: false });
      // The panel sits on the Page tab, which is what the editor opens on;
      // selecting a block switches the sidebar to the Block tab, so read the
      // panel first.
      const panel = client.locator('.blueworx-page-lock');
      await expect(panel).toBeVisible();
      if ((await panel.locator('.components-panel__body-toggle').getAttribute('aria-expanded')) === 'false') {
        await panel.locator('.components-panel__body-toggle').click();
      }
      await expect(panel).toContainText('This page is locked');
      await expect(panel.locator('input[type="checkbox"]')).toHaveCount(0);
      await client.frameLocator('iframe[name="editor-canvas"]').locator('.wp-block-paragraph').first().click();
      await expect(client.getByRole('button', { name: 'Move up' })).toHaveCount(0);

      // The admin unlocks, and the client's block goes in.
      const unlocked = await page.request.post(`/wp-json/wp/v2/pages/${pageId}`, {
        headers,
        data: { meta: { _blueworx_page_lock: false } },
      });
      expect(unlocked.ok(), await unlocked.text()).toBe(true);
      expect((await unlocked.json()).meta._blueworx_page_lock).toBe(false);

      const addedNow = await client.request.post(`/wp-json/wp/v2/pages/${pageId}`, {
        headers: clientHeaders,
        data: { content: CONTENT + PARAGRAPH('A third block.') },
      });
      expect(addedNow.ok(), await addedNow.text()).toBe(true);
      await context.close();
    } finally {
      if (pageId) await page.request.delete(`/wp-json/wp/v2/pages/${pageId}?force=true`, { headers });
      if (userId) await page.request.delete(`/wp-json/wp/v2/users/${userId}?force=true&reassign=1`, { headers });
    }
  });
});
