import { test, expect } from '@playwright/test';
import { isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

const EDIT_MENU_PATH = '/wp-admin/admin.php?page=blueworx-edit-menu';

async function mainColumnSlugs(page) {
  return page.$$eval(
    '.blueworx-menu-order-list[data-blueworx-menu-section="main"] .blueworx-menu-order-item',
    (els) => els.map((el) => el.getAttribute('data-blueworx-menu-item'))
  );
}

test.describe('BlueWorx default admin-menu arrangement', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('BlueWorx sits directly below Dashboard in the admin menu', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/');
    const topLevel = page.locator('#adminmenu > li.menu-top:visible');
    const first = await topLevel.nth(0).innerText();
    const second = await topLevel.nth(1).innerText();
    expect(first).toContain('Dashboard');
    expect(second).toContain('BlueWorx');
  });

  test('More is the last visible top-level item', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/');
    const visible = page.locator('#adminmenu > li.menu-top:visible');
    const last = await visible.last().innerText();
    expect(last).toContain('More');
  });

  test('Edit Menu page shows the default split with items in More and none hidden', async ({ page }) => {
    await login(page);
    await page.goto(EDIT_MENU_PATH);
    const moreColumn = page.locator('.blueworx-menu-order-list[data-blueworx-menu-section="toggle"]');
    await expect(moreColumn.locator('.blueworx-menu-order-item')).not.toHaveCount(0);
    const hiddenColumn = page.locator('.blueworx-menu-order-list[data-blueworx-menu-section="hidden"]');
    await expect(hiddenColumn.locator('.blueworx-menu-order-item')).toHaveCount(0);
  });

  test('main menu is ordered: Dashboard, BlueWorx, then keep items by length then A-Z', async ({ page }) => {
    await login(page);
    await page.goto(EDIT_MENU_PATH);
    const slugs = await mainColumnSlugs(page);
    // Dashboard pinned first, BlueWorx pinned second.
    expect(slugs.indexOf('index.php')).toBe(0);
    expect(slugs.indexOf('blueworx-labs-wordpress')).toBe(1);
    // Media, Pages, Posts, Users are all 5 chars -> alphabetical order.
    const keepOrder = ['upload.php', 'edit.php?post_type=page', 'edit.php', 'users.php'];
    const positions = keepOrder.map((slug) => slugs.indexOf(slug));
    for (const pos of positions) {
      expect(pos).toBeGreaterThan(1);
    }
    for (let i = 1; i < positions.length; i += 1) {
      expect(positions[i]).toBeGreaterThan(positions[i - 1]);
    }
  });

  test('hiding an item, saving, and reloading persists it as hidden (freeze)', async ({ page }) => {
    await login(page);
    await page.goto(EDIT_MENU_PATH);

    // Hide the last item in the Main column (avoids the pinned Dashboard/BlueWorx rows).
    const mainItems = page.locator(
      '.blueworx-menu-order-list[data-blueworx-menu-section="main"] .blueworx-menu-order-item'
    );
    const target = mainItems.last();
    const slug = await target.getAttribute('data-blueworx-menu-item');
    await target.locator('.blueworx-menu-visibility-toggle').click();
    await page.getByRole('button', { name: 'Save Menu Settings' }).click();
    await expect(page.locator('.notice-success').first()).toContainText('Menu settings saved');

    // Reload: the item must now be in the Hidden column. Defaults never hide anything,
    // so this only holds if the saved arrangement was frozen (not recomputed).
    await page.goto(EDIT_MENU_PATH);
    const hidden = page.locator(
      `.blueworx-menu-order-list[data-blueworx-menu-section="hidden"] .blueworx-menu-order-item[data-blueworx-menu-item="${slug}"]`
    );
    await expect(hidden).toHaveCount(1);

    // Restore so the test is idempotent across runs.
    await hidden.locator('.blueworx-menu-visibility-toggle').click();
    await page.getByRole('button', { name: 'Save Menu Settings' }).click();
    await expect(page.locator('.notice-success').first()).toContainText('Menu settings saved');
  });

  test('migration: More items reappear in their natural group', async ({ page }) => {
    await login(page);

    // The More menu and its separator are gone from the sidebar entirely.
    await page.goto('/wp-admin/index.php');
    await expect(page.locator('#toplevel_page_blueworx-menu-toggle')).toHaveCount(0);
    await expect(page.locator('.blueworx-toggle-separator')).toHaveCount(0);

    // Items that used to live in More are visible top-level rows again.
    await expect(page.locator('#adminmenu a[href="tools.php"]')).toBeVisible();
    await expect(page.locator('#adminmenu a[href="options-general.php"]')).toBeVisible();
  });

  // Route decision: WordPress's _wp_menu_output() (wp-admin/menu-header.php)
  // only has two branches per $menu row — a separator (no title, no link) or
  // an ordinary item, which core always wraps in a focusable <a> regardless of
  // the URL field. There is no branch that renders a titled row without an
  // anchor, so a synthetic "heading row" can never be both visible and inert.
  // This uses the documented fallback instead: the first real item of each
  // group is tagged bw-group-start(-{key}), and its translated label is
  // rendered as ::before generated content (not innerText) sourced from a
  // --bw-group-label custom property set inline per row — so the heading is
  // real, translatable, and never introduces a second anchor.
  test('sidebar renders semantic group headings in order', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/index.php');

    const starts = page.locator('#adminmenu li.bw-group-start');
    await expect(starts.first()).toBeVisible();

    // Generated content isn't part of innerText, so read the computed ::before
    // content directly. Chromium/Firefox both quote it, e.g. '"Overview"'.
    const labels = await starts.evaluateAll((els) =>
      els.map((el) => window.getComputedStyle(el, '::before').content.replace(/^"|"$/g, ''))
    );
    const seen = labels.map((t) => t.trim().toUpperCase()).filter(Boolean);

    // Groups appear in the design's order, and only non-empty ones appear.
    const expected = ['OVERVIEW', 'CONTENT', 'CUSTOM CONTENT', 'SITE'];
    expect(seen).toEqual(expected.filter((g) => seen.includes(g)));
    expect(seen).toContain('OVERVIEW');
    expect(seen).toContain('SITE');

    // The heading decorates the group's first real item; it must not add a
    // second anchor to that row (each row keeps exactly its own one link).
    const anchorCounts = await starts.evaluateAll((els) => els.map((el) => el.querySelectorAll('a').length));
    for (const count of anchorCounts) {
      expect(count).toBe(1);
    }
  });
});
