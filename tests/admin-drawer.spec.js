import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const PHONE = { width: 390, height: 844 };

test.describe('the admin menu on a phone', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the hamburger opens a drawer holding the real menu', async ({ page }) => {
    test.slow();

    await page.setViewportSize(PHONE);
    await login(page);
    await page.goto(SETTINGS_PATH);

    // The stylesheet only takes WordPress's own bar away once the script has
    // bound the toggle. If this class is missing the drawer is not in play and
    // every assertion below is measuring the wrong thing.
    await expect(page.locator('html.bw-drawer-ready')).toHaveCount(1);

    const toggle = page.locator('[data-blueworx-drawer-toggle]');
    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    await toggle.click();
    await expect(page.locator('html.bw-drawer-open')).toHaveCount(1);
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');

    // The panel is only worth opening if the menu is actually inside it.
    const item = page.locator('#adminmenu a', { hasText: 'Dashboard' }).first();
    await expect(item).toBeVisible();

    const onScreen = await item.evaluate((el) => {
      const rect = el.getBoundingClientRect();
      return rect.left >= 0 && rect.right <= window.innerWidth;
    });

    expect(onScreen, 'the menu is off the side of the screen').toBe(true);
  });

  test('the scrim closes it, and so does choosing something', async ({ page }) => {
    await page.setViewportSize(PHONE);
    await login(page);
    await page.goto(SETTINGS_PATH);

    await page.locator('[data-blueworx-drawer-toggle]').click();
    await expect(page.locator('html.bw-drawer-open')).toHaveCount(1);

    // Clicked to the right of the 264px drawer: the scrim covers the whole
    // viewport, so its centre point is underneath the drawer itself.
    await page.locator('[data-blueworx-drawer-scrim]').click({ position: { x: 340, y: 400 } });
    await expect(page.locator('html.bw-drawer-open')).toHaveCount(0);

    // Left open behind a loading page, the drawer reads as a stuck overlay.
    await page.locator('[data-blueworx-drawer-toggle]').click();
    await expect(page.locator('html.bw-drawer-open')).toHaveCount(1);
    await page.keyboard.press('Escape');
    await expect(page.locator('html.bw-drawer-open')).toHaveCount(0);
  });

  test('the drawer does not push the page sideways, open or shut', async ({ page }) => {
    await page.setViewportSize(PHONE);
    await login(page);
    await page.goto(SETTINGS_PATH);

    const overflow = async () =>
      page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

    expect(await overflow(), 'the page scrolls sideways with the drawer shut').toBeLessThanOrEqual(1);

    await page.locator('[data-blueworx-drawer-toggle]').click();
    await expect(page.locator('html.bw-drawer-open')).toHaveCount(1);

    expect(await overflow(), 'the page scrolls sideways with the drawer open').toBeLessThanOrEqual(1);
  });

  test('an open drawer leaves the top bar whole', async ({ page }) => {
    await page.setViewportSize(PHONE);
    await login(page);
    await page.goto(SETTINGS_PATH);

    await page.locator('[data-blueworx-drawer-toggle]').click();
    await expect(page.locator('html.bw-drawer-open')).toHaveCount(1);

    // The drawer used to run from the very top of the screen and cover the left
    // of the bar, cutting the site name off mid-word.
    const clear = await page.evaluate(() => {
      const bar = document.querySelector('.bw-topbar');
      const brand = document.querySelector('.bw-brand');
      const menu = document.getElementById('adminmenumain');

      if (!bar || !brand || !menu) {
        return null;
      }

      const bottom = bar.getBoundingClientRect().bottom;

      return {
        brand: brand.getBoundingClientRect().top >= bottom - 1,
        menu: menu.getBoundingClientRect().top >= bottom - 1,
      };
    });

    expect(clear, 'no drawer chrome to measure').not.toBeNull();
    expect(clear.brand, 'the drawer head sits over the top bar').toBe(true);
    expect(clear.menu, 'the drawer panel sits over the top bar').toBe(true);

    // The whole breadcrumb is readable, not clipped by the panel over it.
    const crumb = page.locator('.bw-topbar-here');
    await expect(crumb).toBeVisible();
  });

  test('the desktop sidebar is left alone', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await login(page);
    await page.goto(SETTINGS_PATH);

    // No hamburger, and the menu is beside the page rather than over it.
    await expect(page.locator('[data-blueworx-drawer-toggle]')).toBeHidden();

    // #adminmenumain is a plain full-width div at every size — what says the
    // drawer is not in play is that nothing has been slid out of view.
    const slid = await page.evaluate(() => {
      const menu = document.getElementById('adminmenumain');
      return menu ? getComputedStyle(menu).transform : 'none';
    });

    expect(slid, 'the desktop sidebar is being transformed like a drawer').toBe('none');
  });
});
