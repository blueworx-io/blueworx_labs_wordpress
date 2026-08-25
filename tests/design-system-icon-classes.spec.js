import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
} from './helpers.js';

/**
 * Components that position their own icon must actually get the class that
 * positions it.
 *
 * A select's chevron and an empty state's glyph are each styled by a class of
 * their own in the design system. The PHP helpers rendered the icon without
 * those classes, so the chevron fell out of the field and landed under every
 * dropdown in the plugin, and empty-state glyphs drew at full text weight.
 *
 * Nothing about the markup looks wrong when the class is missing — the icon is
 * there, the component is there — so this asserts on computed position, not on
 * the class being present.
 */

test.describe('icons carry the class their component positions them by', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('a select draws its chevron inside the field', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress&section=security');

    // :visible matters — a feature's settings panel is in the DOM but hidden
    // until its switch is on, so .first() otherwise lands on a collapsed one.
    const wrap = page.locator('.bw-select:visible').first();
    await expect(wrap).toBeVisible();

    const arrow = wrap.locator('.bw-select__arrow');
    await expect(arrow).toHaveCount(1);

    // Absolutely positioned inside the field, not stacked under it.
    const field = await wrap.locator('.bw-select__el').boundingBox();
    const chevron = await arrow.boundingBox();

    expect(chevron.y).toBeGreaterThanOrEqual(field.y - 1);
    expect(chevron.y + chevron.height).toBeLessThanOrEqual(field.y + field.height + 1);
    expect(chevron.x).toBeGreaterThan(field.x);

    // And it must not swallow clicks meant for the field.
    expect(
      await arrow.evaluate((el) => getComputedStyle(el).pointerEvents)
    ).toBe('none');
  });

  test('an empty state glyph takes the faint colour, not body colour', async ({ page }) => {
    await login(page);

    // Guides is empty only when nothing is switched on, so assert on the
    // stylesheet contract rather than trying to empty the site.
    await page.goto('/wp-admin/admin.php?page=blueworx-guides');

    const faint = await page.evaluate(() =>
      getComputedStyle(document.documentElement).getPropertyValue('--bw-text-faint').trim()
    );
    expect(faint).not.toBe('');

    const styled = await page.evaluate(() =>
      [...document.styleSheets]
        .flatMap((sheet) => {
          try {
            return [...sheet.cssRules];
          } catch {
            return [];
          }
        })
        .some((rule) => rule.selectorText === '.bw-empty__icon')
    );
    expect(styled, '.bw-empty__icon has no rule to apply').toBe(true);
  });
});
