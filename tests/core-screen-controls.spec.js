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

      const bar = page.locator('.blueworx-view-as');
      await expect(bar).toBeVisible();

      // Ours, and only ours: the wrapper is inside the bar, not around the page.
      await expect(bar.locator('.bw-admin')).toHaveCount(1);
      await expect(page.locator('body.bw-admin')).toHaveCount(0);

      await bar.locator('select#blueworx_view_as_role').selectOption('subscriber');
      await bar.getByRole('button', { name: 'Switch' }).click();

      // The way out has to be visible from inside, or the feature is a trap.
      // It lives in the top bar now, beside who you are, rather than in a bar
      // along the bottom — which is where you look to find out who you are.
      const pill = page.locator('.bw-topbar-viewas');
      await expect(pill.locator('.bw-badge')).toContainText(/subscriber/i);
      await expect(pill.getByRole('button', { name: 'Return to my own view' })).toBeVisible();

      // And the picker's bar is gone while you are in a role — an empty bar
      // pinned across the screen would say nothing.
      await expect(bar).toHaveCount(0);

      await pill.getByRole('button', { name: 'Return to my own view' }).click();
      await expect(page.locator('.blueworx-view-as select#blueworx_view_as_role')).toBeVisible();
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
