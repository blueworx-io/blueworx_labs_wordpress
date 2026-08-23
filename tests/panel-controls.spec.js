import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login, openSection } from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

/**
 * The classes a WordPress options form leaves behind.
 *
 * Every one of these styled a control inside a feature detail panel before the
 * panels moved onto the design system. Finding any of them again means a panel
 * has drifted back to stock markup — which is invisible until you look at the
 * screen, and is exactly what this file exists to catch.
 */
const STOCK_CLASSES = ['.regular-text', '.large-text', '.small-text', '.code', 'p.description'];

test.describe('the controls inside our own panels come from the design system', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('no panel renders a stock WordPress control', async ({ page }) => {
    test.slow();

    await login(page);
    await page.goto(SETTINGS_PATH);

    const panels = page.locator('.blueworx-feature-detail');
    await expect(panels.first()).toHaveCount(1);

    for (const selector of STOCK_CLASSES) {
      await expect(
        panels.locator(selector),
        `a panel still carries ${selector}`
      ).toHaveCount(0);
    }

    // A bare fieldset arrives with a browser border nothing in the design
    // system removes, so it reads as a box drawn around half the panel.
    await expect(panels.locator('fieldset')).toHaveCount(0);

    // Multi-selects never hinted that ctrl-click picked a second option, and
    // collapsed to something unusable on a phone.
    await expect(panels.locator('select[multiple]')).toHaveCount(0);

    // Nothing should be carrying its own inline styling either.
    await expect(panels.locator('[style*="margin"]')).toHaveCount(0);
  });

  test('the Site Protection role pickers are design system checkboxes', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await openSection(page, 'security');

    for (const area of ['frontend', 'backend']) {
      const boxes = page.locator(`input[type="checkbox"][name="blueworx_${area}_protection_roles[]"]`);
      await expect(boxes.first(), `${area} has no role checkboxes`).toBeAttached();

      // The same wrapper Support Access uses, which is the whole point: one
      // checkbox, drawn one way, wherever it appears.
      await expect(
        boxes.first().locator('xpath=ancestor::label[contains(@class,"bw-check")]')
      ).toHaveCount(1);

      // Ticking nothing locks everyone out, so the panel has to say so.
      const group = page.locator(`[aria-labelledby="blueworx-${area}-roles-label"]`);
      await expect(group).toHaveCount(1);
    }
  });

  test('a panel is usable at phone width', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);
    await page.goto(SETTINGS_PATH);
    await openSection(page, 'security');

    // Nothing inside a panel may push the page sideways. A control wider than
    // the screen is the failure people describe as "mobile doesn't work": the
    // whole admin area scrolls horizontally and the save bar drifts off-screen.
    const overflow = await page.evaluate(() => {
      const root = document.documentElement;
      return root.scrollWidth - root.clientWidth;
    });

    expect(overflow, 'the settings screen scrolls sideways on a phone').toBeLessThanOrEqual(1);

    // The role checkboxes stack rather than sitting in a cut-off row.
    const box = page
      .locator('input[type="checkbox"][name="blueworx_frontend_protection_roles[]"]')
      .first();

    await expect(box).toBeAttached();

    const withinViewport = await box.evaluate((el) => {
      const rect = el.getBoundingClientRect();
      return rect.left >= 0 && rect.right <= window.innerWidth;
    });

    expect(withinViewport, 'a role checkbox sits outside the screen').toBe(true);
  });
});
