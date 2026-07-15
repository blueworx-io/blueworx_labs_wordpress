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

  // Superseded by semantic groups. The old default arrangement pinned BlueWorx
  // second; the sidebar is now ordered Overview -> Content -> Custom Content ->
  // Site, and BlueWorx belongs to Site, so it can no longer sit second. The
  // group ordering is covered by 'sidebar renders semantic group headings'.
  test.skip('BlueWorx sits directly below Dashboard in the admin menu', async () => {});

  // Removed with the More menu (see the migration test below):
  //  - 'More is the last visible top-level item'
  //  - 'Edit Menu page shows the default split with items in More and none hidden'
  // Both asserted the existence of a feature this branch retires, so they could
  // only ever fail. The second also asserted zero hidden items, which was never a
  // safe assumption: hidden/More membership varies per site by design.

  // Both skipped until Task 11 rebuilds the Edit Menu screen, which retires the
  // `.blueworx-menu-order-list` markup and the main/toggle/hidden buckets these
  // drive. They assert the OLD three-column UI.
  //
  // The freeze test is skipped for a second, more urgent reason: IT CORRUPTS THE
  // SITE. Task 5 deleted blueworx_get_toggled_admin_menu_items(), so the old
  // screen can no longer bucket More items — and this test clicks Save through
  // it, rewriting blueworx_hidden_admin_menu_items with whatever the broken
  // screen rendered. Observed on staging: it replaced hidden
  // [plugins.php, link_category] with [themes.php, options-general.php].
  // Do NOT re-enable against the old screen.
  test.skip('main menu is ordered: Dashboard, BlueWorx, then keep items by length then A-Z', async () => {});

  test.skip('hiding an item, saving, and reloading persists it as hidden (freeze)', async () => {});

  test('migration: More items reappear in their natural group', async ({ page }) => {
    await login(page);

    // The More menu and its separator are gone from the sidebar entirely.
    await page.goto('/wp-admin/index.php');
    await expect(page.locator('#toplevel_page_blueworx-menu-toggle')).toHaveCount(0);
    await expect(page.locator('.blueworx-toggle-separator')).toHaveCount(0);

    // Items that used to live in More are visible top-level rows again.
    // Scoped to the top-level anchor: an unscoped href match also hits the
    // item's own submenu row ("Tools > Tools"), which is a strict-mode violation.
    await expect(page.locator('#adminmenu > li.menu-top > a[href="tools.php"]')).toBeVisible();
    await expect(page.locator('#adminmenu > li.menu-top > a[href="options-general.php"]')).toBeVisible();
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
    // SECOND top-level anchor to that row. Counts direct children only —
    // querySelectorAll('a') would also count the row's submenu links (Dashboard
    // has Home + Updates), which are legitimate and unrelated to the heading.
    const anchorCounts = await starts.evaluateAll((els) =>
      els.map((el) => el.querySelectorAll(':scope > a').length)
    );
    for (const count of anchorCounts) {
      expect(count).toBe(1);
    }
  });
});
