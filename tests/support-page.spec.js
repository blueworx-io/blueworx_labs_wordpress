import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login, readSupportKey } from './helpers.js';

/**
 * The Support access screen of its own, as opposed to the panel on Enhancements.
 *
 * tests/support-access.spec.js drives the copy on the Enhancements console and
 * says nothing about this one, which is how the panel shipped here with its
 * buttons outside any form — they rendered perfectly and did nothing at all.
 * These cover the screen actually doing something, not how it looks.
 */
const SUPPORT_PAGE = '/wp-admin/admin.php?page=blueworx-support';

test.describe('Support access — its own screen', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the panel is inside a form, so its buttons can submit at all', async ({ page }) => {
    await login(page);
    await page.goto(SUPPORT_PAGE);

    const generate = page.getByRole('button', { name: 'Generate key' });
    await expect(generate).toBeVisible();

    // A submit button with no owning form is inert: it looks right, it takes a
    // click, and nothing happens. Ask the browser what form it belongs to.
    const hasForm = await generate.evaluate((button) => null !== button.form);
    expect(hasForm, 'the Generate key button belongs to no form').toBe(true);
  });

  test('generating a key works here and stays on this screen', async ({ page }) => {
    await login(page);
    await page.goto(SUPPORT_PAGE);

    await page.getByRole('button', { name: 'Generate key' }).click();

    const key = await readSupportKey(page);
    expect(key).toMatch(/^[0-9a-f]{64}$/);

    // Posting to the Enhancements handler would work and dump the operator on a
    // different screen. The point of this page is that it is self-contained.
    expect(page.url()).toContain('page=blueworx-support');

    // Restore: this suite shares one site, and a key left behind changes what
    // every later support test sees.
    await page.getByRole('button', { name: 'Revoke key' }).click();
    await expect(page.getByRole('button', { name: 'Generate key' })).toBeVisible();
  });

  test('the window can be opened and shut from here', async ({ page }) => {
    await login(page);
    await page.goto(SUPPORT_PAGE);

    await page.getByRole('button', { name: 'Generate key' }).click();
    await page.getByRole('button', { name: 'Allow support access for 24 hours' }).click();

    await expect(page.locator('[data-testid="bw-support-expiry"]')).toContainText('open until');

    await page.getByRole('button', { name: 'Close support access' }).click();
    await expect(
      page.getByRole('button', { name: 'Allow support access for 24 hours' })
    ).toBeVisible();

    await page.getByRole('button', { name: 'Revoke key' }).click();
    await expect(page.getByRole('button', { name: 'Generate key' })).toBeVisible();
  });
});
