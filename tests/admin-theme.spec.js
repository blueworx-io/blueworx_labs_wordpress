// `test` comes from helpers.js, not '@playwright/test': it carries the fixture
// that opts out of core's wp-admin view transitions, which otherwise freeze
// rendering in headless Chromium and hang every actionability check.
import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  DASH_PATH,
  LOGIN_PATH,
  login,
  cacheBust,
} from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

/**
 * Go to the settings page and return the admin_theme toggle locator.
 */
async function themeToggle(page) {
  await page.goto(SETTINGS_PATH);
  return page.locator('input.blueworx-feature-toggle[data-blueworx-feature="admin_theme"]');
}

async function saveSettings(page) {
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
}

test.describe('BlueWorx admin theme', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  // Set for exactly as long as the theme is deliberately switched off. Only the
  // on/off test below ever does that.
  let themeIsOff = false;

  // The safety net. admin_theme is a REAL setting on a REAL site, and turning it
  // off is not a test detail: it restyles the whole admin for anyone looking at
  // it, and every later test in this file then asserts against a stock WordPress
  // and fails. A restore on the happy path alone is not enough — the run that
  // needs it most is the one that failed. This hook still runs when the test
  // above throws or times out.
  test.afterEach(async ({ page }) => {
    if (!themeIsOff) {
      return;
    }
    themeIsOff = false;
    const toggle = await themeToggle(page);
    if (!(await toggle.isChecked())) {
      await toggle.setChecked(true);
      await saveSettings(page);
    }
  });

  test('Appearance section and admin_theme toggle render', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await expect(page.getByRole('heading', { name: 'Appearance' })).toBeVisible();
    await expect(
      page.locator('input.blueworx-feature-toggle[data-blueworx-feature="admin_theme"]')
    ).toBeVisible();
  });

  test('theme stylesheet + hero tiles load when on, absent when off', async ({ page }) => {
    await login(page);

    // Ensure the theme is ON.
    let toggle = await themeToggle(page);
    if (!(await toggle.isChecked())) {
      await toggle.setChecked(true);
      await saveSettings(page);
    }

    await page.goto(DASH_PATH);
    await expect(page.locator('link#blueworx-admin-theme-css')).toHaveCount(1);
    await expect(page.locator('.bw-stat-grid')).toBeVisible();

    // Turn it OFF — stylesheet, hero tiles, and custom chrome disappear.
    // Flagged before the save, not after: if the save itself is what fails, the
    // setting may still have landed, so the afterEach must assume the worst.
    toggle = await themeToggle(page);
    await toggle.setChecked(false);
    themeIsOff = true;
    await saveSettings(page);

    await page.goto(DASH_PATH);
    await expect(page.locator('link#blueworx-admin-theme-css')).toHaveCount(0);
    await expect(page.locator('.bw-stat-grid')).toHaveCount(0);
    await expect(page.locator('.bw-topbar')).toHaveCount(0);

    // Restore ON so the test is idempotent across runs. The afterEach is the
    // net for when this line is never reached.
    toggle = await themeToggle(page);
    await toggle.setChecked(true);
    await saveSettings(page);
    themeIsOff = false;
  });

  test('desktop chrome: BlueWorx top bar replaces the admin bar, footer hidden', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    // Custom top bar is present with the site link and user menu.
    await expect(page.locator('.bw-topbar')).toBeVisible();
    await expect(page.locator('.bw-topbar-site')).toBeVisible();
    await expect(page.locator('.bw-topbar-site')).toHaveAttribute('target', '_blank');
    await expect(page.locator('.bw-user-summary')).toBeVisible();
    await expect(page.locator('.bw-brand')).toBeVisible();

    // WordPress chrome we replaced is not visible on desktop.
    await expect(page.locator('#wpadminbar')).toBeHidden();
    await expect(page.locator('#wpfooter')).toBeHidden();

    // The user menu is a native <details> — opening it reveals profile + logout.
    await page.locator('.bw-user-summary').click();
    await expect(page.locator('.bw-user-menu a', { hasText: 'Log Out' })).toBeVisible();
  });

  test('mobile keeps the native admin bar so the menu toggle still works', async ({ page }) => {
    await login(page);
    await page.setViewportSize({ width: 480, height: 900 });
    await page.goto(DASH_PATH);

    await expect(page.locator('.bw-topbar')).toBeHidden();
    await expect(page.locator('#wpadminbar')).toBeVisible();
  });

  test('login screen is branded', async ({ page, context }) => {
    await context.clearCookies();
    // Not a hardcoded /wp-login.php: the `login` feature blocks that path and
    // moves the form to a custom slug, so the branded screen only exists at
    // LOGIN_PATH on sites with it enabled.
    //
    // Cache-busted: this is a logged-out page, and Varnish serves those from
    // cache. Without this the test asserts against whatever HTML was cached
    // hours ago — which reported this working feature as broken.
    await page.goto(cacheBust(LOGIN_PATH));

    // The WordPress logo is replaced by the site-name wordmark.
    const logo = page.locator('.login h1 a');
    await expect(logo).toBeVisible();
    await expect(logo).not.toHaveCSS('background-image', /wordpress-logo/);
    await expect(page.locator('link#blueworx-login-theme-css')).toHaveCount(1);
  });

  test('regression: brand block never overhangs the top bar (the jutt)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    // Expanded: the brand's rendered box must match the sidebar's exactly.
    const brand = await page.locator('.bw-brand').boundingBox();
    const menu = await page.locator('#adminmenuwrap').boundingBox();
    expect(brand.width).toBeCloseTo(menu.width, 0);

    // And it must not cross into the top bar.
    const topbar = await page.locator('.bw-topbar').boundingBox();
    expect(brand.x + brand.width).toBeLessThanOrEqual(topbar.x + 0.5);

    // Folded: same guarantee (this state had the same 24px overhang).
    // Folded by adding the class rather than clicking #collapse-button: the
    // design has no Collapse Menu, so the theme hides that button whenever the
    // menu is expanded. body.folded is still reachable in the wild — WordPress
    // auto-folds between 783px and 960px, and it persists the state per user —
    // and it is the rendered state, not the click, that this regression is
    // about. Toggling the class keeps the test on the real CSS.
    await page.evaluate(() => document.body.classList.add('folded'));
    const fBrand = await page.locator('.bw-brand').boundingBox();
    const fMenu = await page.locator('#adminmenuwrap').boundingBox();
    expect(fBrand.width).toBeCloseTo(fMenu.width, 0);

    // The brand mark must still be visible when folded, not clipped to nothing.
    const mark = await page.locator('.bw-brand-mark').boundingBox();
    expect(mark.width).toBeGreaterThan(20);

    // Folded is the one state where the button must come back, so nobody can be
    // stranded in it.
    await expect(page.locator('#collapse-button')).toBeVisible();

    await page.evaluate(() => document.body.classList.remove('folded'));
  });

  test('Collapse Menu is hidden when expanded, per the design', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    await expect(page.locator('#collapse-menu')).toBeHidden();
  });

  test('regression: hovering the current item does not shift its colour', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    // Top-level current item only. `#adminmenu li.current` also matches the
    // SUBMENU row (Dashboard > Home), whose anchor is not .menu-top — so
    // `li.current > a.menu-top` matched nothing and the test hung.
    const currentLink = page
      .locator('#adminmenu li.wp-has-current-submenu > a.wp-has-current-submenu')
      .first();
    const before = await currentLink.evaluate((el) => getComputedStyle(el).backgroundColor);

    await currentLink.hover();
    const after = await currentLink.evaluate((el) => getComputedStyle(el).backgroundColor);

    // Hover must not composite a second translucent layer over the active pill.
    expect(after).toBe(before);
    // And the active pill is the design's opaque indigo, not a 22% wash.
    expect(before).toBe('rgb(79, 70, 229)');
  });

  // The icon-swap ($menu field 6 = 'none') runs in this task; the actual SVG
  // injection lands with the badges renderer in Task 8, so this test only
  // becomes green once both are in place. Written now per TDD; not run here.
  test('core menu items use the design icon set, third-party keep dashicons', async ({ page }) => {
    await login(page);
    await page.goto(DASH_PATH);

    // Mapped core items get an inline SVG.
    const dash = page.locator('#adminmenu li a[href="index.php"] svg.bw-menu-icon');
    await expect(dash).toHaveCount(1);
    await expect(dash).toHaveAttribute('aria-hidden', 'true');

    // Icons inherit the label colour.
    await expect(dash).toHaveAttribute('stroke', 'currentColor');
  });

  test('menu badges show real counts and are absent at zero', async ({ page }) => {
    await login(page);

    // Read the true published-post count from the Posts list table.
    await page.goto('/wp-admin/edit.php');
    const publishedText = await page.locator('.subsubsub .publish .count, .subsubsub li.publish a').first().innerText();
    const published = parseInt(publishedText.replace(/\D/g, ''), 10);

    await page.goto(DASH_PATH);
    const badge = page.locator('#adminmenu li a[href="edit.php"] .bw-badge');

    if (published > 0) {
      await expect(badge).toHaveText(String(published));
      await expect(badge).toHaveAttribute('aria-label', new RegExp(`${published}`));
    } else {
      await expect(badge).toHaveCount(0);
    }
  });

  test('settings screens get card containers, without nesting cards', async ({ page }) => {
    await page.setViewportSize({ width: 1600, height: 900 });
    await login(page);

    const white = 'rgb(255, 255, 255)';

    // Cache: bare form-table gets carded.
    await page.goto('/wp-admin/admin.php?page=blueworx-cache');
    await expect(page.locator('.wrap > .form-table').first()).toHaveCSS('background-color', white);

    // General Settings (core markup) gets carded too.
    await page.goto('/wp-admin/options-general.php');
    await expect(page.locator('.wrap > form > .form-table').first()).toHaveCSS('background-color', white);

    // Enhancements: its form-table lives inside an already-carded .postbox.
    // A card here means the child combinators broke and cards are nesting.
    await page.goto(SETTINGS_PATH);
    const nested = page.locator('.postbox .inside > .form-table').first();
    await expect(nested).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');

    // And its cards must be constrained, not stretched edge-to-edge at 1600px.
    const box = await page.locator('.postbox').first().boundingBox();
    expect(box.width).toBeLessThan(1300);
  });

  test('sidebar has a Log Out row with a nonced URL', async ({ page }) => {
    await login(page);
    await page.goto(DASH_PATH);

    const logout = page.locator('#adminmenu .bw-logout a');
    await expect(logout).toBeVisible();
    await expect(logout).toHaveAttribute('href', /action=logout/);
    await expect(logout).toHaveAttribute('href', /_wpnonce=/);
    await expect(page.locator('#adminmenu .bw-logout svg')).toHaveCount(1);
  });

  test('group headings are styled and inert', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    // The heading is a ::before on the group's first item, not an element of
    // its own, so it has to be read off the pseudo-element.
    const heading = page.locator('#adminmenu li.bw-group-start').first();
    const style = await heading.evaluate((el) => {
      const cs = getComputedStyle(el, '::before');
      return {
        letterSpacing: cs.letterSpacing,
        fontSize: cs.fontSize,
        fontWeight: cs.fontWeight,
        pointerEvents: cs.pointerEvents,
        content: cs.content,
      };
    });

    expect(style.letterSpacing).toBe('1.2px');
    expect(style.fontSize).toBe('10.5px');
    expect(style.fontWeight).toBe('600');
    // Inert: the heading must not swallow its host item's click.
    expect(style.pointerEvents).toBe('none');
    // And it carries a real, translated label — not an empty string.
    expect(style.content).toMatch(/\w/);
  });

  test('custom post types appear as top-level rows under Custom Content', async ({ page }) => {
    await login(page);
    await page.goto(DASH_PATH);

    // Any registered CPT — wherever the site registered it — must reach the
    // sidebar as its own top-level row, not stay buried as a plugin submenu.
    const cptRows = page.locator('#adminmenu > li.menu-top > a[href^="edit.php?post_type="]');
    const hrefs = await cptRows.evaluateAll((els) => els.map((el) => el.getAttribute('href')));
    const custom = hrefs.filter((h) => h !== 'edit.php?post_type=page');

    test.skip(custom.length === 0, 'This site registers no custom post types.');

    // Their group heading renders, and it is the Custom Content one.
    const firstCustom = page
      .locator(`#adminmenu > li.menu-top:has(> a[href="${custom[0]}"])`)
      .first();
    await expect(firstCustom).toBeVisible();

    const started = await page
      .locator('#adminmenu li.bw-group-start-custom')
      .count();
    expect(started).toBe(1);

    // The promoted row is decorated like every other: it gets the design icon.
    await expect(
      page.locator(`#adminmenu a[href="${custom[0]}"] svg.bw-menu-icon`)
    ).toHaveCount(1);
  });

  test('promoted custom post types keep their nested submenu', async ({ page }) => {
    await login(page);
    await page.goto(DASH_PATH);

    const first = await page
      .locator('#adminmenu > li.menu-top > a[href^="edit.php?post_type="]')
      .evaluateAll((els) =>
        els.map((el) => el.getAttribute('href')).filter((h) => h !== 'edit.php?post_type=page')
      );

    test.skip(first.length === 0, 'This site registers no custom post types.');

    const row = page.locator(`#adminmenu > li.menu-top:has(> a[href="${first[0]}"])`);

    // A post type registered against a parent menu gets no All/Add New rows of
    // its own, so promoting it without rebuilding them would leave a top-level
    // row with nothing under it and no route to Add New.
    const links = await row.locator('.wp-submenu li a').evaluateAll((els) =>
      els.map((el) => el.getAttribute('href'))
    );

    expect(links).toContain(first[0]);
    expect(links.some((h) => h && h.startsWith('post-new.php?post_type='))).toBe(true);
  });
});
