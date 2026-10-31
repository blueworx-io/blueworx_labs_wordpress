import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
} from './helpers.js';

/**
 * Moving an item between groups, and getting back out of a change.
 *
 * Up and down already crossed a group boundary, but only once you had walked
 * the row past every other item in its group. The left and right buttons send
 * it straight there, which is what "put this in Content" means to the person
 * doing it.
 */

const EDIT_MENU = '/wp-admin/admin.php?page=blueworx-edit-menu';

test.describe('Edit Menu — moving between groups', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('an item can be sent to the next group and back, without a mouse drag', async ({ page }) => {
    await login(page);
    await page.goto(EDIT_MENU);

    const groups = page.locator('.bw-menu-editor-group');
    await expect(groups.first()).toBeVisible();

    // First movable row in the first group.
    const row = groups.first().locator('.bw-menu-editor-item').first();
    const slug = await row.getAttribute('data-slug');
    expect(slug).toBeTruthy();

    const groupOf = async () =>
      page
        .locator(`.bw-menu-editor-item[data-slug="${slug}"]`)
        .locator('xpath=ancestor::*[contains(@class,"bw-menu-editor-group")]')
        .getAttribute('data-group');

    const before = await groupOf();

    await page.locator(`.bw-menu-editor-item[data-slug="${slug}"] .bw-menu-editor-next`).click();
    const after = await groupOf();
    expect(after).not.toBe(before);

    // The hidden input the save reads must have followed the row.
    await expect(
      page.locator(`.bw-menu-editor-item[data-slug="${slug}"] .bw-menu-editor-group-input`)
    ).toHaveValue(after);

    await page.locator(`.bw-menu-editor-item[data-slug="${slug}"] .bw-menu-editor-prev`).click();
    expect(await groupOf()).toBe(before);
  });

  test('the first group cannot go further left, and the last no further right', async ({ page }) => {
    await login(page);
    await page.goto(EDIT_MENU);

    const groups = page.locator('.bw-menu-editor-group');
    const count = await groups.count();
    expect(count).toBeGreaterThan(1);

    await expect(
      groups.first().locator('.bw-menu-editor-item').first().locator('.bw-menu-editor-prev')
    ).toBeDisabled();

    const lastWithRows = groups.nth(count - 1);
    if ((await lastWithRows.locator('.bw-menu-editor-item').count()) > 0) {
      await expect(
        lastWithRows.locator('.bw-menu-editor-item').first().locator('.bw-menu-editor-next')
      ).toBeDisabled();
    }
  });

  test('discarding leaves nothing saved', async ({ page }) => {
    await login(page);
    await page.goto(EDIT_MENU);

    const row = page.locator('.bw-menu-editor-item').first();
    const slug = await row.getAttribute('data-slug');

    await page.locator(`.bw-menu-editor-item[data-slug="${slug}"] .bw-menu-editor-next`).click();
    await page.getByRole('link', { name: 'Discard changes' }).click();

    // Back on a fresh render, the row is where it started.
    await expect(page.locator('.bw-menu-editor-item').first()).toHaveAttribute('data-slug', slug);
  });
  // A design system update made its list rows stack top to bottom, which put
  // each item's handle, name and arrows on separate lines and turned the
  // screen into a long scroll. An item is one line.
  test('each item sits on one line', async ({ page }) => {
    await login(page);
    await page.goto(EDIT_MENU);

    const row = page.locator('.bw-menu-editor-item').first();
    const label = await row.locator('.bw-menu-editor-label').boundingBox();
    const up = await row.locator('.bw-menu-editor-up').boundingBox();

    expect(label && up, 'the label and its arrows should both be drawn').toBeTruthy();
    // The arrow starts above the label's bottom edge and ends below its top.
    expect(up.y).toBeLessThan(label.y + label.height);
    expect(up.y + up.height).toBeGreaterThan(label.y);
  });
});
