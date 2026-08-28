import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

/**
 * The screens against the design, at the level of where things sit.
 *
 * Every assertion here is a measurement rather than a class name, because all
 * of these were bugs you could see and none of them changed the markup enough
 * for an attribute check to notice: a header that kept WordPress's margins, a
 * button that stopped in mid-air, a column that never grew, a card capped at
 * 760px. Measuring is the only thing that would have failed before the fix.
 */

const ENHANCEMENTS = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const GUIDES = '/wp-admin/admin.php?page=blueworx-guides';
const SUPPORT = '/wp-admin/admin.php?page=blueworx-support';
const EDIT_MENU = '/wp-admin/admin.php?page=blueworx-edit-menu';

/** A desktop wide enough for every layout the design describes. */
const DESKTOP = { width: 1440, height: 1000 };

test.describe('the admin shell as designed', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('a BlueWorx screen runs flush to the admin chrome', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await login(page);
    await page.goto(ENHANCEMENTS);

    const flush = await page.evaluate(() => {
      const page_ = document.querySelector('.bw-page');
      const column = document.querySelector('#wpbody-content');

      if (!page_ || !column) {
        return null;
      }

      const a = page_.getBoundingClientRect();
      const b = column.getBoundingClientRect();

      return { left: a.left - b.left, right: b.right - a.right, top: a.top - b.top };
    });

    expect(flush, 'no .bw-page inside #wpbody-content').not.toBeNull();

    // WordPress's own .wrap margins are 10px 20px 0 2px. Anything above a
    // rounding pixel here means they are still applied.
    expect(flush.left, 'the screen is inset from the left of the column').toBeLessThanOrEqual(1);
    expect(flush.right, 'the screen is inset from the right of the column').toBeLessThanOrEqual(1);
    expect(flush.top, 'the screen is pushed down from the top of the column').toBeLessThanOrEqual(1);
  });

  test('notices land under the page header, not inside it', async ({ page }) => {
    await login(page);
    await page.goto(ENHANCEMENTS);

    // The marker is the whole mechanism: without it WordPress falls back to the
    // first h1 and inserts notices between the title and the lede.
    await expect(page.locator('.bw-page > hr.wp-header-end')).toBeAttached();
    await expect(page.locator('.bw-pagehead .notice')).toHaveCount(0);
  });

  test('header buttons take a row of their own, hard right', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await login(page);
    await page.goto(ENHANCEMENTS);

    const placed = await page.evaluate(() => {
      const head = document.querySelector('.bw-pagehead');
      const actions = document.querySelector('.bw-pagehead__actions');
      const lede = document.querySelector('.bw-pagehead__lede');

      if (!head || !actions || !lede) {
        return null;
      }

      const a = actions.getBoundingClientRect();
      const h = head.getBoundingClientRect();
      const l = lede.getBoundingClientRect();
      const pad = parseFloat(getComputedStyle(head).paddingRight) || 0;

      return { below: a.top >= l.bottom - 1, fromRight: h.right - pad - a.right };
    });

    expect(placed, 'no page header with actions on Enhancements').not.toBeNull();
    expect(placed.below, 'the header buttons sit beside the lede, not under it').toBe(true);
    expect(placed.fromRight, 'the header buttons do not reach the right edge').toBeLessThanOrEqual(2);
  });

  test('"Page access:" stays on the same line as its pills', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await login(page);
    await page.goto(GUIDES);

    const inline = await page.evaluate(() => {
      const label = document.querySelector('.bw-pageaccess__label');
      const pill = document.querySelector('.bw-pageaccess .bw-rolepill');

      if (!label || !pill) {
        return null;
      }

      const a = label.getBoundingClientRect();
      const b = pill.getBoundingClientRect();

      // Same line means their centres agree, not that their boxes match.
      return Math.abs(a.top + a.height / 2 - (b.top + b.height / 2)) < 4;
    });

    expect(inline, 'no page-access row on Guides').not.toBeNull();
    expect(inline, 'the label has broken onto its own line above the pills').toBe(true);
  });

  test('the save bar spans the screen and sits on the bottom', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await login(page);
    await page.goto(ENHANCEMENTS);

    const bar = await page.evaluate(() => {
      const found = document.querySelector('.bw-page .bw-savebar');
      const page_ = document.querySelector('.bw-page');

      if (!found || !page_) {
        return null;
      }

      const a = found.getBoundingClientRect();
      const b = page_.getBoundingClientRect();

      // Nothing of ours is pinned across the bottom any more, so the floor is
      // simply the foot of the window.
      const floor = window.innerHeight;

      return {
        left: a.left - b.left,
        right: b.right - a.right,
        fromFloor: floor - a.bottom,
      };
    });

    expect(bar, 'no save bar on Enhancements').not.toBeNull();
    expect(bar.left, 'the save bar is inset from the left').toBeLessThanOrEqual(1);
    expect(bar.right, 'the save bar is inset from the right').toBeLessThanOrEqual(1);
    expect(bar.fromFloor, 'the save bar floats short of the bottom').toBeLessThanOrEqual(2);
  });

  test('the save bar sits on the bottom on a screen too short to scroll', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await login(page);

    // Admin Menu is one function, so this screen does not fill the window. The
    // bar is sticky, and sticky cannot move an element that has nowhere to
    // scroll — it used to stop wherever the settings stopped, hanging mid-page.
    await page.goto(`${ENHANCEMENTS}&section=admin_menu`);

    const measured = await page.evaluate(() => {
      const bar = document.querySelector('.bw-page .bw-savebar');

      if (!bar) {
        return null;
      }

      const floor = window.innerHeight;

      return {
        scrollable: document.documentElement.scrollHeight - window.innerHeight,
        fromFloor: floor - bar.getBoundingClientRect().bottom,
      };
    });

    expect(measured, 'no save bar on Enhancements').not.toBeNull();
    expect(measured.scrollable, 'this section is long enough to scroll').toBeLessThanOrEqual(2);
    expect(measured.fromFloor, 'the save bar floats short of the bottom').toBeLessThanOrEqual(2);
  });

  test('the content clears the save bar by the same gap it has at the top', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await login(page);
    await page.goto(ENHANCEMENTS);

    const gutters = await page.evaluate(() => {
      const style = getComputedStyle(document.querySelector('.bw-page__body'));

      return {
        top: parseFloat(style.paddingTop),
        bottom: parseFloat(style.paddingBottom),
      };
    });

    expect(gutters.top).toBeGreaterThan(0);
    expect(gutters.bottom, 'the save bar sits flush against the last card').toBe(gutters.top);
  });

  // View as role used to be a bar fixed across the bottom of every screen, and
  // it landed on top of the save bar — Save could not be clicked at all. It is
  // a control in the sidebar now, so nothing of ours is over the page.
  test('nothing is pinned over the save button', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await login(page);
    await page.goto(ENHANCEMENTS);

    const save = page.locator('.bw-page .bw-savebar').getByRole('button', { name: 'Save Changes' });
    const box = await save.boundingBox();

    expect(box, 'no Save Changes button on Enhancements').not.toBeNull();

    // What the browser would actually hand the click to at the button's centre.
    const onTop = await page.evaluate(
      ({ x, y }) => {
        const el = document.elementFromPoint(x, y);

        return el ? !!el.closest('.bw-savebar') : false;
      },
      { x: box.x + box.width / 2, y: box.y + box.height / 2 }
    );

    expect(onTop, 'something is painted over the save button').toBe(true);

    await expect(save).toBeVisible();
    await save.click({ trial: true, timeout: 5000 });
  });
});

test.describe('Enhancements as designed', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the panels fill the width beside the section nav', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await login(page);
    await page.goto(ENHANCEMENTS);

    const short = await page.evaluate(() => {
      const body = document.querySelector('.bw-page__body');
      const row = document.querySelector('.bw-panels-row');

      if (!body || !row) {
        return null;
      }

      const pad = parseFloat(getComputedStyle(body).paddingRight) || 0;

      return body.getBoundingClientRect().right - pad - row.getBoundingClientRect().right;
    });

    expect(short, 'no two-column body on Enhancements').not.toBeNull();
    expect(short, 'the panels column stops short of the page').toBeLessThanOrEqual(2);
  });

  test('the section header is spaced off the cards below it', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await login(page);
    await page.goto(ENHANCEMENTS);

    const gaps = await page.evaluate(() => {
      const panel = document.querySelector('.bw-panels > [data-blueworx-panel]:not([hidden])');

      if (!panel) {
        return null;
      }

      const head = panel.querySelector('.bw-sectionhead');
      const cards = panel.querySelectorAll('.bw-fncard');

      if (!head || cards.length < 2) {
        return null;
      }

      return {
        toStack: cards[0].getBoundingClientRect().top - head.getBoundingClientRect().bottom,
        betweenCards: cards[1].getBoundingClientRect().top - cards[0].getBoundingClientRect().bottom,
      };
    });

    expect(gaps, 'no section panel with two function cards').not.toBeNull();

    // The header used to sit flush against the stack while the cards below it
    // were spaced, which is what read as inconsistent.
    expect(gaps.toStack, 'the section header is flush against the first card').toBeGreaterThan(14);
    expect(gaps.betweenCards, 'the function cards are not spaced as designed').toBeGreaterThan(10);
  });
});

test.describe('Guides as designed', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('section and topic sit in a band the width of the screen', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await login(page);
    await page.goto(GUIDES);

    const band = await page.evaluate(() => {
      const found = document.querySelector('.bw-guidebar');
      const page_ = document.querySelector('.bw-page');

      if (!found || !page_) {
        return null;
      }

      const a = found.getBoundingClientRect();
      const b = page_.getBoundingClientRect();

      return { left: a.left - b.left, right: b.right - a.right };
    });

    expect(band, 'no section band on Guides').not.toBeNull();
    expect(band.left, 'the band is inset from the left').toBeLessThanOrEqual(1);
    expect(band.right, 'the band is inset from the right').toBeLessThanOrEqual(1);
  });

  test('changing topic does not reload the page', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await login(page);
    await page.goto(GUIDES);

    const tabs = page.locator('[data-blueworx-guide-tabs] .bw-tab');

    if ((await tabs.count()) < 2) {
      test.skip(true, 'this site has only one guide topic switched on');
      return;
    }

    // Survives a swap and not a page load, which is the whole difference.
    await page.evaluate(() => {
      window.__bwStillHere = true;
    });

    const second = tabs.nth(1);
    const id = await second.getAttribute('data-blueworx-guide-tab');

    await second.click();

    await expect(page.locator(`.bw-guidegrid[data-blueworx-guide-panel="${id}"]`)).toBeVisible();
    await expect(second).toHaveClass(/is-active/);

    expect(await page.evaluate(() => window.__bwStillHere), 'the tab reloaded the screen').toBe(true);

    // Still a real address, so the tab can be bookmarked or sent to somebody.
    expect(page.url()).toContain(`tab=${id}`);
  });

  test('every topic is on the page, with one shown', async ({ page }) => {
    await login(page);
    await page.goto(GUIDES);

    const panels = page.locator('.bw-guidegrid');
    const shown = page.locator('.bw-guidegrid:not([hidden])');

    expect(await panels.count()).toBeGreaterThan(0);
    await expect(shown).toHaveCount(1);
  });
});

test.describe('the screens that were capped at 760px', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('Support access fills the content area', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await login(page);
    await page.goto(SUPPORT);

    const short = await page.evaluate(() => {
      const body = document.querySelector('.bw-page__body');
      const card = document.querySelector('.bw-page__body .bw-card');

      if (!body || !card) {
        return null;
      }

      const pad = parseFloat(getComputedStyle(body).paddingRight) || 0;

      return body.getBoundingClientRect().right - pad - card.getBoundingClientRect().right;
    });

    expect(short, 'no card on Support access').not.toBeNull();
    expect(short, 'the card is still capped short of the page').toBeLessThanOrEqual(2);
  });

  test('the support switch is in the card head', async ({ page }) => {
    await login(page);
    await page.goto(SUPPORT);

    await expect(
      page.locator('.bw-card__head [data-testid="bw-support-feature"]')
    ).toBeAttached();
  });
});

test.describe('Edit Menu as designed', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the buckets lay out across the width', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await login(page);
    await page.goto(EDIT_MENU);

    const sideBySide = await page.evaluate(() => {
      const found = document.querySelectorAll('.bw-menu-editor > .bw-card');

      if (found.length < 2) {
        return null;
      }

      const a = found[0].getBoundingClientRect();
      const b = found[1].getBoundingClientRect();

      return Math.abs(a.top - b.top) < 2 && b.left > a.left;
    });

    if (null !== sideBySide) {
      expect(sideBySide, 'the buckets are stacked one per row at 1440px').toBe(true);
    }
  });

  test('a long menu label does not run under the Locked badge', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await login(page);
    await page.goto(EDIT_MENU);

    const overlap = await page.evaluate(() => {
      const rows = document.querySelectorAll('.bw-menu-editor-item');
      let worst = 0;

      rows.forEach((row) => {
        const label = row.querySelector('.bw-menu-editor-label');
        const badge = row.querySelector('.bw-badge');

        if (!label || !badge) {
          return;
        }

        worst = Math.max(worst, label.getBoundingClientRect().right - badge.getBoundingClientRect().left);
      });

      return worst;
    });

    expect(overlap, 'a menu label is overlapping the badge beside it').toBeLessThanOrEqual(1);
  });
});
