// The sidebar's hover menus (#241).
//
// Hovering a sidebar item opens its submenu as a fly-out beside it. On a site
// with many plugin menus that fly-out jumped to the wrong height, shut before
// the pointer could reach it, sometimes sat under the top bar where its first
// items could not be clicked, and on SureDash's screens its open menu turned
// white. Each test below pins one of those down.
//
// `test` comes from helpers.js, not '@playwright/test': it carries the fixture
// that opts out of core's wp-admin view transitions, which otherwise freeze
// rendering in headless Chromium and hang every actionability check.
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, DASH_PATH, login } from './helpers.js';

const VIEWPORT = { width: 1280, height: 900 };

// How far apart, in px, a fly-out's top and its item's top may be and still
// count as lined up. Core's own fly-out sits 1px above its item; anything near
// a group heading's height (about 30px) is the bug.
const ALIGN_TOLERANCE = 3;

/**
 * Every top-level item that has a fly-out (has a submenu, is not the current
 * menu) and can actually be pointed at right now: its link is on screen and is
 * the topmost thing at its centre, not hidden under the pinned Log Out row or
 * scrolled out of the sidebar.
 */
async function hoverableFlyoutItems(page) {
  return page.evaluate(() => {
    const items = document.querySelectorAll('#adminmenu > li.wp-has-submenu.wp-not-current-submenu');

    return Array.from(items)
      .filter((li) => {
        const anchor = li.querySelector(':scope > a.menu-top');

        if (!li.id || !anchor || !li.querySelector(':scope > .wp-submenu a')) {
          return false;
        }

        const rect = anchor.getBoundingClientRect();

        if (!rect.height || rect.top < 0 || rect.bottom > window.innerHeight) {
          return false;
        }

        const hit = document.elementFromPoint(rect.left + rect.width / 2, rect.top + rect.height / 2);

        return Boolean(hit && anchor.contains(hit));
      })
      .map((li) => li.id);
  });
}

/**
 * Reads the alpha of a computed colour, whichever way the browser serialised
 * it: rgb()/rgba(), or color(srgb …) for values that came out of color-mix().
 */
function alphaOf(colour) {
  if (colour === 'transparent') {
    return 0;
  }

  const rgba = colour.match(/^rgba?\(([^)]+)\)$/);

  if (rgba) {
    const parts = rgba[1].split(/[\s,/]+/).filter(Boolean);
    return parts.length > 3 ? parseFloat(parts[3]) : 1;
  }

  const fn = colour.match(/\/\s*([\d.]+)\s*\)$/);
  return fn ? parseFloat(fn[1]) : 1;
}

/**
 * Relative luminance (0 black – 1 white) of an opaque rgb() colour.
 */
function luminanceOf(colour) {
  const [r, g, b] = colour
    .match(/[\d.]+/g)
    .slice(0, 3)
    .map((v) => {
      const c = parseFloat(v) / 255;
      return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });

  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

test.describe('Sidebar hover menus', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(VIEWPORT);
    await login(page);
    await page.goto(DASH_PATH);
  });

  test('every hover menu opens beside its item, level with it, and below the top bar', async ({ page }) => {
    const ids = await hoverableFlyoutItems(page);

    // Core alone gives Posts, Appearance, Plugins, Users, Tools and Settings
    // fly-outs; LatePoint adds another. Fewer than one means the test found
    // nothing to check rather than that everything passed.
    expect(ids.length).toBeGreaterThan(0);

    const sidebar = await page.locator('#adminmenuwrap').boundingBox();

    for (const id of ids) {
      const item = page.locator(`#adminmenu > li[id="${id}"]`);
      const anchor = item.locator(':scope > a.menu-top');
      const flyout = item.locator(':scope > .wp-submenu');

      await anchor.hover();
      await expect(item, `${id} should open on hover`).toHaveClass(/\bopensub\b/);

      const a = await anchor.boundingBox();
      const f = await flyout.boundingBox();

      // Beside the item: starts at the sidebar's right edge, past the link.
      expect(f.x, `${id} fly-out should start at the sidebar edge`).toBeCloseTo(sidebar.x + sidebar.width, 0);
      expect(f.x).toBeGreaterThanOrEqual(a.x + a.width);

      // Never under the top bar, where its first items could not be clicked.
      expect(f.y, `${id} fly-out should not open under the top bar`).toBeGreaterThanOrEqual(sidebar.y - 0.5);
      expect(f.y + f.height, `${id} fly-out should end on screen`).toBeLessThanOrEqual(VIEWPORT.height + 0.5);

      // Level with the item, unless that would run it off the foot of the
      // screen. Then it is lifted to fit, which the two checks above cover.
      if (a.y + f.height <= VIEWPORT.height) {
        expect(Math.abs(f.y - a.y), `${id} fly-out should line up with its item`).toBeLessThanOrEqual(
          ALIGN_TOLERANCE
        );
      }

      // Its first item is the topmost thing where it is drawn: nothing (top
      // bar, brand block, another panel) is covering it.
      const firstLink = flyout.locator('a').first();
      const covered = await firstLink.evaluate((el) => {
        const r = el.getBoundingClientRect();
        const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
        return !(hit && el.contains(hit));
      });
      expect(covered, `${id} fly-out's first item should not be covered`).toBe(false);
    }
  });

  test('the pointer can travel from an item into its menu and click the last link', async ({ page }) => {
    const ids = await hoverableFlyoutItems(page);
    // Posts when it is there: its last link (Tags) is a plain screen, so the
    // navigation is unambiguous. Otherwise the first item that has a fly-out.
    const id = ids.includes('menu-posts') ? 'menu-posts' : ids[0];
    expect(id, 'no sidebar item with a hover menu was found').toBeTruthy();

    const item = page.locator(`#adminmenu > li[id="${id}"]`);
    const anchor = item.locator(':scope > a.menu-top');

    await anchor.hover();
    await expect(item).toHaveClass(/\bopensub\b/);

    // The LAST link, so the path runs diagonally down across the items below
    // this one — the move that used to swap to the neighbour's menu — and
    // across the gap between the item and its fly-out.
    const link = item.locator(':scope > .wp-submenu a').last();
    const target = await link.evaluate((el) => el.href);
    const a = await anchor.boundingBox();
    const l = await link.boundingBox();

    await page.mouse.move(a.x + a.width - 4, a.y + a.height / 2);
    await page.mouse.move(l.x + l.width / 2, l.y + l.height / 2, { steps: 12 });

    // Still this item's menu, and nobody else's.
    await expect(item).toHaveClass(/\bopensub\b/);
    await expect(page.locator('#adminmenu > li.opensub')).toHaveCount(1);

    // And the link is what is under the pointer, not something laid over it.
    const reachable = await link.evaluate((el) => {
      const r = el.getBoundingClientRect();
      const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
      return Boolean(hit && el.contains(hit));
    });
    expect(reachable).toBe(true);

    await page.mouse.click(l.x + l.width / 2, l.y + l.height / 2);
    await page.waitForURL(target);
  });

  test('the current menu keeps the sidebar colours, idle and hovered, whatever the screen paints', async ({
    page,
  }) => {
    const item = page.locator('#adminmenu > li.wp-has-current-submenu').first();
    const submenu = item.locator(':scope > .wp-submenu');
    await expect(submenu.locator('a').first()).toBeVisible();

    // WordPress gives the menu row the same class as the body of its screen.
    // SureDash colours its screen with `.toplevel_page_portal { background-color:
    // #f8fafc }`, which therefore painted its own open menu near-white. Recreate
    // that collision on whichever menu is current here.
    await page.evaluate(() => {
      const li = document.querySelector('#adminmenu > li.wp-has-current-submenu');
      li.classList.add('toplevel_page_portal');
      const style = document.createElement('style');
      style.textContent = '.toplevel_page_portal { background-color: #f8fafc; }';
      document.head.appendChild(style);
    });

    const back = await page.locator('#adminmenuback').evaluate((el) => getComputedStyle(el).backgroundColor);
    expect(luminanceOf(back), 'the sidebar panel itself should be dark').toBeLessThan(0.1);

    // The row and its open submenu paint nothing of their own, so what shows
    // through is the dark sidebar panel behind them.
    const paints = async () => ({
      row: alphaOf(await item.evaluate((el) => getComputedStyle(el).backgroundColor)),
      submenu: alphaOf(await submenu.evaluate((el) => getComputedStyle(el).backgroundColor)),
    });

    await page.mouse.move(VIEWPORT.width - 10, VIEWPORT.height - 10);
    expect(await paints(), 'idle').toEqual({ row: 0, submenu: 0 });

    await submenu.locator('a').first().hover();
    expect(await paints(), 'hovering a submenu item').toEqual({ row: 0, submenu: 0 });

    await item.locator(':scope > a.menu-top').hover();
    expect(await paints(), 'hovering the parent').toEqual({ row: 0, submenu: 0 });
  });
});
