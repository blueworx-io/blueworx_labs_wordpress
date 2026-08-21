/**
 * The BlueWorx admin theme against a REAL LatePoint install.
 *
 * The equivalent assertions in admin-theme.spec.js recreate LatePoint's rules by
 * hand. That proves our cascade resolves the way we think it does, and it runs
 * everywhere — but by construction it cannot fail when LatePoint changes those
 * rules, which is where both of the layout bugs actually came from.
 *
 * This spec measures the real thing instead, so it fails if LatePoint moves the
 * ground under us. It needs the plugin present in the harness:
 *
 *   node scripts/install-test-latepoint.mjs
 *
 * and skips, loudly, when it is not there — including in CI, which builds a
 * fresh WordPress per shard and does not pay for a 5.8MB third-party download on
 * every pull request. See issue #114 for that trade-off.
 */

import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

const LATEPOINT_PATH = '/wp-admin/admin.php?page=latepoint';

test.describe('BlueWorx admin theme — real LatePoint install', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('LatePoint gets the whole window, with nothing of ours left over it', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page);

    const response = await page.goto(LATEPOINT_PATH);

    // The plugin is not installed on this site. Skipping rather than failing is
    // deliberate — the simulated cover in admin-theme.spec.js is what guards the
    // cascade everywhere; this spec is the extra check for a machine that has
    // LatePoint. The message names the fix so a skip is never a mystery.
    test.skip(
      !response || response.status() >= 400 || !(await page.locator('body.latepoint-admin').count()),
      'LatePoint is not installed here — run `node scripts/install-test-latepoint.mjs` against the harness.'
    );

    const layout = await page.evaluate(() => {
      const rect = (selector) => {
        const el = document.querySelector(selector);
        if (!el) return null;
        const b = el.getBoundingClientRect();
        return { x: Math.round(b.x), y: Math.round(b.y) };
      };
      return {
        body: rect('body'),
        content: rect('#wpcontent'),
        topBar: rect('.latepoint-top-bar-w'),
        sideMenu: rect('.latepoint-side-menu-w'),
      };
    });

    // Nothing is hauled off the top of the window. LatePoint pulls the body up by
    // the height of the admin bar it hides, to reclaim the space core reserves on
    // <html> — space our theme has already reclaimed, so the pull has to be
    // cancelled or LatePoint's own search field and New Booking button end up
    // above the top edge. This is the assertion that was worth -32px in the wild.
    expect(layout.body.y).toBe(0);
    expect(layout.content.y).toBe(0);
    expect(layout.content.x).toBe(0);

    // LatePoint's own chrome is fully on screen, not clipped by the window edge.
    expect(layout.topBar).not.toBeNull();
    expect(layout.topBar.y).toBeGreaterThanOrEqual(0);
    expect(layout.sideMenu.x).toBe(0);

    // And ours is out of the way entirely rather than reserved for.
    await expect(page.locator('.bw-topbar')).toBeHidden();
    await expect(page.locator('.bw-brand')).toBeHidden();

    // The WordPress menu is gone too — LatePoint hides it, and our sidebar rules
    // must not resurrect an offset for something that is not painted.
    await expect(page.locator('#adminmenumain')).toBeHidden();
  });

  test('the admin bar compensation still matches what LatePoint ships', async ({ page }) => {
    // The narrow-window half of the same bargain. LatePoint mirrors core's
    // reserved height at every breakpoint (0 at ≤600px, -46px at ≤782px, -32px
    // above that); we only cancel it where we ourselves remove that space, which
    // is 783px up. Below the breakpoint core still reserves the room, so
    // LatePoint's pull is doing real work and must survive untouched.
    await page.setViewportSize({ width: 700, height: 900 });
    await login(page);

    const response = await page.goto(LATEPOINT_PATH);

    test.skip(
      !response || response.status() >= 400 || !(await page.locator('body.latepoint-admin').count()),
      'LatePoint is not installed here — run `node scripts/install-test-latepoint.mjs` against the harness.'
    );

    const narrow = await page.evaluate(() => ({
      bodyMarginTop: getComputedStyle(document.body).marginTop,
      htmlPaddingTop: getComputedStyle(document.documentElement).paddingTop,
    }));

    // Whatever the two numbers are, they must cancel: the page starts at the top
    // of the window, with nothing above it and no gap below it.
    const reserved = parseFloat(narrow.htmlPaddingTop) || 0;
    const pulled = parseFloat(narrow.bodyMarginTop) || 0;
    expect(reserved + pulled).toBe(0);
  });
});
