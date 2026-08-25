import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  openSectionFor,
} from './helpers.js';

/**
 * Enhancements, rebuilt as one card per function.
 *
 * The thing that can quietly break this screen is the form: an unchecked box
 * and a missing box are indistinguishable when it posts, so every panel has to
 * stay in the DOM whichever section is showing. Rendering only the open section
 * would switch off every function nobody happened to be looking at.
 */

const SETTINGS = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

test.describe('Enhancements as function cards', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('every function is its own card, with the section head above the stack', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS);

    const openPanel = page.locator('[data-blueworx-panel]:not([hidden])');
    await expect(openPanel).toHaveCount(1);

    await expect(openPanel.locator('.bw-sectionhead')).toHaveCount(1);
    await expect(openPanel.locator('.bw-fnstack .bw-fncard').first()).toBeVisible();

    // Name and description sit beside the switch, not inside its label.
    const card = openPanel.locator('.bw-fncard').first();
    await expect(card.locator('.bw-fncard__name')).not.toBeEmpty();
    await expect(card.locator('.bw-switch--bare input[type="checkbox"]')).toHaveCount(1);
  });

  test('every section stays in the form, open or not', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS);

    const panels = page.locator('[data-blueworx-panel]');
    const total = await panels.count();
    expect(total).toBeGreaterThan(1);

    // Hidden, never removed — this is the one that matters.
    const toggles = page.locator('.blueworx-feature-toggle');
    expect(await toggles.count()).toBeGreaterThan(20);
  });

  test('switching a section off unticks only that section, and saves nothing yet', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS);

    const openPanel = page.locator('[data-blueworx-panel]:not([hidden])');
    const inSection = openPanel.locator('.blueworx-feature-toggle');
    const elsewhere = page.locator('[data-blueworx-panel][hidden] .blueworx-feature-toggle');

    const onElsewhereBefore = await elsewhere.evaluateAll((els) =>
      els.filter((el) => el.checked).length
    );

    await openPanel.getByRole('button', { name: 'Switch this section off' }).click();

    expect(await inSection.evaluateAll((els) => els.filter((el) => el.checked).length)).toBe(0);
    expect(
      await elsewhere.evaluateAll((els) => els.filter((el) => el.checked).length)
    ).toBe(onElsewhereBefore);

    // Nothing is written until Save — a reload puts it all back.
    await page.reload();
    expect(
      await page
        .locator('[data-blueworx-panel]:not([hidden]) .blueworx-feature-toggle')
        .evaluateAll((els) => els.filter((el) => el.checked).length)
    ).toBeGreaterThan(0);
  });

  test('a function with settings shows them railed under its own switch', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS);
    await openSectionFor(page, 'login');

    const panel = page.locator('.bw-fncard__panel[data-blueworx-detail="login"]');
    await expect(panel).toBeVisible();
    await expect(panel.locator('.bw-card--sunken')).toHaveCount(1);
    await expect(panel.locator('.bw-card__eyebrow')).toHaveText('Settings for this function');

    // And it belongs to the card its switch is in.
    await expect(
      panel.locator('xpath=parent::*').locator('.blueworx-feature-toggle[data-blueworx-feature="login"]')
    ).toHaveCount(1);
  });
});
