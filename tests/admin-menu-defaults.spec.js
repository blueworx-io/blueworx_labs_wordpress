// `test` comes from helpers.js, not '@playwright/test': it carries the fixture
// that opts out of core's wp-admin view transitions, which otherwise freeze
// rendering in headless Chromium and hang every actionability check.
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

const EDIT_MENU_PATH = '/wp-admin/admin.php?page=blueworx-edit-menu';

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

  // Retired with the old three-column screen, which no longer exists:
  //  - 'main menu is ordered: Dashboard, BlueWorx, then keep items by length then A-Z'
  //    asserted an ordering superseded by semantic groups (see the heading test).
  //  - 'hiding an item, saving, and reloading persists it as hidden (freeze)'
  //    drove the old markup and CORRUPTED the site: it clicked Save through a
  //    screen Task 5 had half-dismantled, rewriting hidden [plugins.php,
  //    link_category] as [themes.php, options-general.php] on staging.
  // Hiding is now covered by 'hiding an item removes it from the sidebar', which
  // drives the rebuilt screen and restores what it changes.

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

  test('Edit Menu renders a section per group plus Hidden', async ({ page }) => {
    await login(page);
    await page.goto(EDIT_MENU_PATH);

    for (const g of ['overview', 'content', 'custom', 'site', 'hidden']) {
      await expect(page.locator(`.bw-menu-editor-group[data-group="${g}"]`)).toHaveCount(1);
    }

    // Dashboard starts in Overview.
    await expect(
      page.locator('.bw-menu-editor-group[data-group="overview"] .bw-menu-editor-item[data-slug="index.php"]')
    ).toHaveCount(1);

    // Every row is keyboard-operable, not drag-only.
    const first = page.locator('.bw-menu-editor-item').first();
    await expect(first.locator('button.bw-menu-editor-up')).toBeVisible();
    await expect(first.locator('button.bw-menu-editor-down')).toBeVisible();

    // The rebuilt screen ships no jQuery UI sortable.
    const sortable = await page.evaluate(
      () => typeof (window.jQuery && window.jQuery.fn && window.jQuery.fn.sortable)
    );
    expect(sortable).not.toBe('function');
  });

  test('Edit Menu: the up button moves an item up', async ({ page }) => {
    await login(page);
    await page.goto(EDIT_MENU_PATH);

    const list = page.locator('.bw-menu-editor-group[data-group="site"] .bw-menu-editor-item');
    const second = list.nth(1);
    const slug = await second.getAttribute('data-slug');

    await second.locator('button.bw-menu-editor-up').click();

    await expect(list.first()).toHaveAttribute('data-slug', slug);
    // Not saved, so nothing to restore — the reorder dies with the page.
  });

  test('Edit Menu: keyboard moves an item across a group boundary', async ({ page }) => {
    await login(page);
    await page.goto(EDIT_MENU_PATH);

    // Move the last Content item down, past the boundary, into the next group.
    const item = page.locator('.bw-menu-editor-group[data-group="content"] .bw-menu-editor-item').last();
    const slug = await item.getAttribute('data-slug');
    await item.locator('button.bw-menu-editor-down').click();

    // It left Content...
    await expect(
      page.locator(`.bw-menu-editor-group[data-group="content"] .bw-menu-editor-item[data-slug="${slug}"]`)
    ).toHaveCount(0);

    // ...and its group input followed it, so a save would persist the move.
    const group = await page
      .locator(`.bw-menu-editor-item[data-slug="${slug}"] .bw-menu-editor-group-input`)
      .inputValue();
    expect(group).not.toBe('content');
  });

  test('Edit Menu: hiding an item removes it from the sidebar', async ({ page }) => {
    await login(page);
    await page.goto(EDIT_MENU_PATH);

    // Remember which row Tools sits above, so the restore below can put it back
    // exactly where it was. Appending it to the end of Site would "restore" it to
    // a different position and quietly walk the real site's menu order down one
    // slot on every run.
    const restoreBefore = await page.evaluate(() => {
      const item = document.querySelector('.bw-menu-editor-item[data-slug="tools.php"]');
      return item.nextElementSibling ? item.nextElementSibling.getAttribute('data-slug') : null;
    });

    // Drag is hard to script reliably; drive the same code path via the inputs.
    await page.evaluate(() => {
      const item = document.querySelector('.bw-menu-editor-item[data-slug="tools.php"]');
      const hiddenList = document.querySelector('.bw-menu-editor-group[data-group="hidden"] .bw-menu-editor-list');
      hiddenList.appendChild(item);
      item.dispatchEvent(new Event('drop', { bubbles: true }));
    });

    await page.locator('input#submit').click();
    await expect(page.locator('.notice-success')).toContainText('Menu settings saved');

    await page.goto('/wp-admin/index.php');
    // Hidden means display:none, NOT removed: blueworx_hide_admin_menu_rows()
    // deliberately hides the row while leaving the page registered, so the
    // screen stays reachable by URL. Asserting toHaveCount(0) would be asserting
    // a behaviour the plugin does not have.
    await expect(page.locator('#adminmenu > li.menu-top > a[href="tools.php"]')).toBeHidden();

    // Restore, so the test is idempotent and leaves the site as it found it —
    // same group AND same position.
    await page.goto(EDIT_MENU_PATH);
    await page.evaluate((before) => {
      const item = document.querySelector('.bw-menu-editor-item[data-slug="tools.php"]');
      const siteList = document.querySelector('.bw-menu-editor-group[data-group="site"] .bw-menu-editor-list');
      const anchor = before
        ? siteList.querySelector(`.bw-menu-editor-item[data-slug="${before}"]`)
        : null;
      siteList.insertBefore(item, anchor);
      item.dispatchEvent(new Event('drop', { bubbles: true }));
    }, restoreBefore);
    // This used to need click({ force: true }), because the SECOND save of the
    // test always timed out on Playwright's "stable" wait while the first was
    // fine. That was the view-transition freeze, and it IS now fixed at source
    // by the reduced-motion fixture in helpers.js — the earlier "tested:
    // prefers-reduced-motion makes no difference" was a false negative, because
    // reducedMotion in playwright.config.js is silently ignored. An honest click
    // works here now, and if this ever hangs again it is a real regression and
    // must not be forced away.
    await page.locator('input#submit').click();
    await expect(page.locator('.notice-success')).toContainText('Menu settings saved');

    await page.goto('/wp-admin/index.php');
    await expect(page.locator('#adminmenu > li.menu-top > a[href="tools.php"]')).toBeVisible();
  });
});
