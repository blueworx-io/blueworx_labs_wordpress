import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

const GUIDES = '/wp-admin/admin.php?page=blueworx-guides';

test.describe('the Guides screen as designed', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('guides sit in a two-column grid with a read time each', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await login(page);
    await page.goto(GUIDES);

    const cards = page.locator('.bw-guidegrid > .bw-card');
    await expect(cards.first()).toBeVisible();

    // Two columns means two cards sharing a top edge. Comparing the first two
    // cards' y positions says that without measuring the grid itself.
    const sideBySide = await page.evaluate(() => {
      const found = document.querySelectorAll('.bw-guidegrid > .bw-card');
      if (found.length < 2) {
        return null;
      }
      const a = found[0].getBoundingClientRect();
      const b = found[1].getBoundingClientRect();
      return Math.abs(a.top - b.top) < 2 && b.left > a.left;
    });

    if (null !== sideBySide) {
      expect(sideBySide, 'the guides are not in two columns at 1440px').toBe(true);
    }

    await expect(cards.first().locator('.bw-badge')).toContainText('min read');
  });

  test('one column on a phone', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);
    await page.goto(GUIDES);

    const stacked = await page.evaluate(() => {
      const found = document.querySelectorAll('.bw-guidegrid > .bw-card');
      if (found.length < 2) {
        return true;
      }
      return found[1].getBoundingClientRect().top > found[0].getBoundingClientRect().top + 10;
    });

    expect(stacked, 'the guides are still in columns on a phone').toBe(true);

    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );

    expect(overflow, 'Guides scrolls sideways on a phone').toBeLessThanOrEqual(1);
  });

  test('a guide says who can do the thing, three at a time', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await login(page);
    await page.goto(`${GUIDES}&tab=getting-started`);

    const card = page.locator('.bw-guidegrid > .bw-card').first();
    const more = card.locator('[data-blueworx-roles-more]');

    await expect(more, 'nothing to expand — this tab should overflow three roles').toHaveCount(1);

    // Three roles plus the expander, which is itself a pill.
    await expect(card.locator('.bw-rolepill:visible')).toHaveCount(4);

    // How many roles there are is a property of the site, not of this screen:
    // a stock WordPress has five, and an install that has added its own has
    // more. The full set is always in the markup with the overflow hidden, so
    // count it rather than pin a number that only holds where this was written.
    const all = await card.locator('.bw-rolepill').count();

    await more.click();
    await expect(more).toHaveText('Show fewer');
    await expect(card.locator('.bw-rolepill:visible')).toHaveCount(all);

    await more.click();
    await expect(card.locator('.bw-rolepill:visible')).toHaveCount(4);

    // Administrator is the answer most people came for, so it is picked out.
    await expect(card.locator('.bw-rolepill--admin').first()).toBeVisible();
  });

  test('the roles come from capabilities, not from a list we wrote down', async ({ page }) => {
    await login(page);

    // Security guides describe things only an administrator can do, so their
    // pill list must be shorter than a tab everyone can act on. Hardcoded lists
    // would not know the difference.
    await page.goto(`${GUIDES}&tab=getting-started`);
    const everyone = await page.locator('.bw-guidegrid > .bw-card').first().getAttribute('data-blueworx-guide');
    // Scoped to the grid the chosen topic is showing in: page headers carry a
    // role list of their own, and every topic's grid is on the page now with
    // all but one hidden — so an unscoped .bw-rolepills reads the wrong topic.
    const openRoles = await page
      .locator('.bw-guidegrid:not([hidden]) .bw-rolepills')
      .first()
      .getAttribute('title');

    await page.goto(`${GUIDES}&tab=security`);
    const adminRoles = await page
      .locator('.bw-guidegrid:not([hidden]) .bw-rolepills')
      .first()
      .getAttribute('title');

    expect(everyone, 'no guide rendered to read roles from').toBeTruthy();
    expect(adminRoles).toContain('Administrator');
    expect(
      adminRoles.split(',').length,
      'security lists as many roles as getting started, so they are not derived'
    ).toBeLessThan(openRoles.split(',').length);
  });

  test('the tab bar is the drag-scrollable one and every tab is a real link', async ({ page }) => {
    await login(page);
    await page.goto(GUIDES);

    const bar = page.locator('[data-blueworx-guide-tabs]');
    await expect(bar).toHaveClass(/bw-tabs--drag/);

    // Drag-scrolling is an addition, not a replacement: a tab has to stay a URL
    // somebody can be sent, and keep working with the script absent.
    const href = await bar.locator('.bw-tab').nth(1).getAttribute('href');
    expect(href).toContain('page=blueworx-guides');
    expect(href).toContain('tab=');
  });
});
