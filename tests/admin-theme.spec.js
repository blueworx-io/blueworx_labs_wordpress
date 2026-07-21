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

  test('critical layout CSS is inlined in the head before the stylesheet loads', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    // The anti-flash skeleton is printed inline (not a <link>), so an asset
    // optimiser cannot defer it and it lands in the first paint. It must sit in
    // the <head>, ahead of the deferrable main stylesheet.
    const critical = page.locator('head style#blueworx-admin-critical');
    await expect(critical).toHaveCount(1);

    // textContent, not toContainText: a <style> element renders no text, so
    // toContainText always sees "" and the assertion can never pass — it looked
    // like a guard for years while checking nothing.
    expect(await critical.textContent()).toContain('#wpadminbar');

    const orderedBeforeStylesheet = await page.evaluate(() => {
      const style = document.getElementById('blueworx-admin-critical');
      const link = document.getElementById('blueworx-admin-theme-css');
      if (!style || !link) {
        return false;
      }
      // DOCUMENT_POSITION_FOLLOWING (4) => link comes after the inline style.
      return Boolean(style.compareDocumentPosition(link) & Node.DOCUMENT_POSITION_FOLLOWING);
    });
    expect(orderedBeforeStylesheet).toBe(true);
  });

  test('dashboard default layout: At a Glance shown, Quick Draft hidden', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    // The BlueWorx hero tiles (our "At a Glance") are part of the default layout.
    await expect(page.locator('.bw-stat-grid')).toBeVisible();

    // Quick Draft is hidden by default via default_hidden_meta_boxes. toBeHidden
    // passes whether the box is display:none or absent. This asserts the default
    // only; a user who re-enables it in Screen Options overrides the filter, so
    // this runs against the automation account's untouched dashboard.
    await expect(page.locator('#dashboard_quick_press')).toBeHidden();
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

  test('custom post types stay nested under the menu that registered them', async ({ page }) => {
    await login(page);
    await page.goto(DASH_PATH);

    // A post type registered with show_in_menu => '<parent>' is core's way of
    // saying "this belongs under that menu". An earlier pass promoted those to
    // top-level rows and scattered a site's authored structure across the
    // sidebar; nothing may lift them out again.
    const nested = await page
      .locator('#adminmenu .wp-submenu a[href^="edit.php?post_type="]')
      .evaluateAll((els) =>
        els
          .map((el) => el.getAttribute('href'))
          .filter((h) => h && h !== 'edit.php?post_type=page')
      );

    test.skip(nested.length === 0, 'This site registers no nested custom post types.');

    // Each one that is a submenu row must NOT also exist as a top-level row.
    const topLevel = await page
      .locator('#adminmenu > li.menu-top > a[href^="edit.php?post_type="]')
      .evaluateAll((els) => els.map((el) => el.getAttribute('href')));

    for (const href of nested) {
      expect(topLevel).not.toContain(href);
    }
  });

  test('unmapped plugin menus head Custom Content, and never land in Site', async ({ page }) => {
    await login(page);
    await page.goto(DASH_PATH);

    // How many unmapped menus exist depends on what else is installed. A clean
    // site has none and correctly renders no Custom Content group, so demanding
    // exactly one made this fail on a clean install while passing on a populated
    // one — the assertion tracked the environment, not the behaviour. Assert the
    // invariant instead: there is never more than one such group.
    const customStarts = await page.locator('#adminmenu li.bw-group-start-custom').count();
    expect(customStarts, 'at most one Custom Content group start').toBeLessThanOrEqual(1);

    // Site is the last group, so it is the start row plus everything after it —
    // and it must hold only mapped core housekeeping menus.
    const siteSlugs = await page
      .locator(
        '#adminmenu li.bw-group-start-site > a.menu-top, #adminmenu li.bw-group-start-site ~ li.menu-top > a.menu-top'
      )
      .evaluateAll((els) => els.map((el) => el.getAttribute('href')));

    expect(siteSlugs.length).toBeGreaterThan(0);

    for (const href of siteSlugs) {
      // nav-menus.php belongs here: 1.15.0 promoted Menus to its own Site row.
      // The allowlist was never updated to match, and this line could not fail
      // because the assertion above always threw first.
      expect(href).toMatch(/^(nav-menus|themes|plugins|users|tools|options-general)\.php/);
    }
  });

  test('BlueWorx sits in Overview, directly below Dashboard', async ({ page }) => {
    await login(page);
    await page.goto(DASH_PATH);

    const slugs = await page
      .locator('#adminmenu > li.menu-top > a.menu-top')
      .evaluateAll((els) => els.map((el) => el.getAttribute('href')));

    const dashboard = slugs.findIndex((h) => h && h.endsWith('index.php'));
    const blueworx = slugs.findIndex((h) => h && h.includes('page=blueworx-labs-wordpress'));

    expect(dashboard).toBeGreaterThanOrEqual(0);
    expect(blueworx).toBe(dashboard + 1);

    // And it is inside Overview — so it must not start a group of its own.
    const row = page.locator('#adminmenu > li.menu-top').nth(blueworx);
    await expect(row).not.toHaveClass(/bw-group-start/);
  });

  test('every top-level row is the same height, and none overhangs the sidebar', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    // Rows whose icon the design does not map keep core's dashicon, whose glyph
    // box is 36px against the mapped rows' 20px. Left alone those rows render
    // visibly taller than their neighbours.
    const heights = await page
      .locator('#adminmenu > li.menu-top > a.menu-top')
      .evaluateAll((els) => els.map((el) => Math.round(el.getBoundingClientRect().height)));

    expect(heights.length).toBeGreaterThan(1);
    expect(new Set(heights).size).toBe(1);

    // Side padding on #adminmenu is added to core's content-box width, pushing
    // items out over the content area. The menu must not exceed its own panel.
    const overhang = await page.evaluate(() => {
      const menu = document.getElementById('adminmenu');
      const back = document.getElementById('adminmenuback');
      return menu.getBoundingClientRect().right - back.getBoundingClientRect().right;
    });

    expect(overhang).toBeLessThanOrEqual(0);
  });

  test('hovering a group heading item does not paint the whole group', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    // Core paints hover on the li, which hosts both the group's ::before heading
    // and (on the current item) its inline submenu — so the highlight bled
    // across the entire section. State belongs to the anchor alone.
    const row = page.locator('#adminmenu li.bw-group-start').first();
    await row.hover();

    const transparent = ['rgba(0, 0, 0, 0)', 'transparent'];
    const liBackground = await row.evaluate((el) => getComputedStyle(el).backgroundColor);
    expect(transparent).toContain(liBackground);

    // The anchor still takes its own state, so hover is not simply gone.
    const anchor = row.locator('> a.menu-top');
    const anchorBackground = await anchor.evaluate((el) => getComputedStyle(el).backgroundColor);
    expect(transparent).not.toContain(anchorBackground);
  });
});
