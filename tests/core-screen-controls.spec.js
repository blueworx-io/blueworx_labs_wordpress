import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  setFeature,
  saveEnhancements,
  featureIsOn,
} from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Creates or removes a real PNG attachment via tests/fixtures/make-attachment.php.
 *
 * @param {'create'|'delete'} command Fixture command.
 * @param {number} id Attachment to delete.
 * @return {string} Fixture stdout — the new attachment ID for "create".
 */
function attachmentFixture(command, id) {
  const fixture = path.join(__dirname, 'fixtures', 'make-attachment.php');
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  const args = [fixture, wpLoad, command];

  if (id) {
    args.push(String(id));
  }

  return execFileSync('php', args, { encoding: 'utf8' }).trim();
}

/** A 1x1 PNG, and a 2x2 one, so a replace is visible in the file's size. */
const ONE_PX = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
  'base64'
);
const FOUR_PX = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAFUlEQVR42mNk+M/wn4EIwDiqkL4KAdxWCAFmvJk8AAAAAElFTkSuQmCC',
  'base64'
);

test.describe('BlueWorx controls inside core screens', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('replacing a file keeps the address and changes the file', async ({ page }) => {
    const id = Number(attachmentFixture('create'));
    expect(id).toBeGreaterThan(0);

    try {
      await login(page);
      await page.goto(`/wp-admin/post.php?post=${id}&action=edit`);

      const box = page.locator('.blueworx-media-replace');
      await expect(box).toBeVisible();

      // The control is ours, so it is the one carrying the wrapper.
      await expect(box.locator('.bw-admin')).toHaveCount(1);

      const before = await page.locator('#attachment_url, .attachment-details input[readonly]').first().inputValue();
      expect(before).toContain('.png');

      await box
        .locator('input[type="file"]')
        .setInputFiles({ name: 'replacement.png', mimeType: 'image/png', buffer: FOUR_PX });
      await box.getByRole('button', { name: 'Replace' }).click();

      // Replaced, and said so in our own notice rather than a core one.
      await expect(page.locator('.bw-notice--success')).toContainText(/replac/i);

      // The whole point: the address is the same file path it always was, so
      // every existing use of it still resolves.
      const after = await page.locator('#attachment_url, .attachment-details input[readonly]').first().inputValue();
      expect(after).toBe(before);

      // And the bytes behind that address really did change.
      const fetched = await page.request.get(`${after}?v=${Date.now()}`);
      expect(fetched.status()).toBe(200);
      expect((await fetched.body()).length).not.toBe(ONE_PX.length);
    } finally {
      attachmentFixture('delete', id);
    }
  });

  test('switching to a role says so, and switching back returns you', async ({ page }) => {
    await login(page);

    const wasOn = await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress').then(async () => {
      return featureIsOn(page, 'view_as_role');
    });

    if (!wasOn) {
      await setFeature(page, 'view_as_role', true);
      await saveEnhancements(page);
    }

    try {
      await page.goto('/wp-admin/index.php');

      const trigger = page.locator('.bw-viewas__trigger');
      const menu = page.locator('.bw-viewas__menu');

      // One control, in the sidebar above Log Out. It used to be two — a picker
      // in a bar across the bottom, and a pill in the top bar that only appeared
      // once you were already inside a role.
      await expect(trigger).toBeVisible();
      await expect(trigger).toContainText('My own view');
      await expect(page.locator('.blueworx-view-as')).toHaveCount(0);
      await expect(page.locator('.bw-topbar-viewas')).toHaveCount(0);

      await expect(menu).toBeHidden();
      await trigger.click();
      await expect(menu).toBeVisible();

      // Alphabetical, so the list can be read down. "My own view" leads it
      // whatever the roles are called — it is where you already are, not one of
      // the places you can go.
      const options = await menu.locator('.bw-viewas__option').allTextContents();
      expect(options[0]).toBe('My own view');
      expect(options.slice(1)).toEqual([...options.slice(1)].sort((a, b) => a.localeCompare(b)));

      await menu.locator('.bw-viewas__option[value="subscriber"]').click();

      // Where you are has to be readable from inside, or the feature is a trap —
      // and so does the way out, which is choosing your own view again.
      await expect(trigger).toContainText(/viewing as subscriber/i);
      await expect(trigger).toHaveClass(/is-active/);

      // And the preview is the real thing: a subscriber has no settings, so
      // neither does anyone previewing one. The control itself is what stays
      // reachable, which is what makes taking the rest away safe.
      await expect(page.locator('#adminmenu a[href*="page=blueworx-labs-wordpress"]')).toHaveCount(0);

      const settings = await page.request.get('/wp-admin/admin.php?page=blueworx-labs-wordpress');
      expect(settings.status(), 'the settings screen must refuse a previewed subscriber').toBe(403);

      await trigger.click();
      await menu.locator('.bw-viewas__option[value=""]').click();
      await expect(trigger).toContainText('My own view');
      await expect(trigger).not.toHaveClass(/is-active/);
    } finally {
      if (!wasOn) {
        await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
        await setFeature(page, 'view_as_role', false);
        await saveEnhancements(page);
      }
    }
  });

  test('the design system does not leak onto the core screen around our controls', async ({
    page,
  }) => {
    await login(page);
    await page.goto('/wp-admin/user-new.php');

    // Our control is wrapped...
    await expect(page.locator('.bw-admin .blueworx-role-choices')).toHaveCount(1);

    // ...and nothing outside it is. The system's base styles are opt-in, and a
    // wrapper placed one level too high would restyle a screen we do not own.
    const strays = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('.bw-admin')).filter((el) =>
        el.closest('form#createuser') === null || el.querySelector('.blueworx-role-choices') === null
      ).length;
    });
    expect(strays).toBe(0);

    // Core's own submit button is still core's.
    await expect(page.locator('#createusersub')).toHaveClass(/button-primary/);
  });
});
