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
  openSectionFor,
  setFeature,
} from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

/**
 * Go to the settings page and return the admin_theme toggle locator.
 */
async function themeToggle(page) {
  await page.goto(SETTINGS_PATH);
  // Appearance is not the section that opens first, so the toggle is in the DOM
  // but not on screen until its section is opened.
  await openSectionFor(page, 'admin_theme');
  return page.locator('input.blueworx-feature-toggle[data-blueworx-feature="admin_theme"]');
}

async function saveSettings(page) {
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
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
      await setFeature(page, 'admin_theme', true);
      await saveSettings(page);
    }
  });

  test('Appearance section and admin_theme toggle render', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await openSectionFor(page, 'admin_theme');
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
      await setFeature(page, 'admin_theme', true);
      await saveSettings(page);
    }

    await page.goto(DASH_PATH);
    await expect(page.locator('link#blueworx-admin-theme-css')).toHaveCount(1);
    await expect(page.locator('#blueworx_dashboard_stats .bw-stats')).toBeVisible();

    // Turn it OFF — stylesheet, hero tiles, and custom chrome disappear.
    // Flagged before the save, not after: if the save itself is what fails, the
    // setting may still have landed, so the afterEach must assume the worst.
    toggle = await themeToggle(page);
    await setFeature(page, 'admin_theme', false);
    themeIsOff = true;
    await saveSettings(page);

    await page.goto(DASH_PATH);
    await expect(page.locator('link#blueworx-admin-theme-css')).toHaveCount(0);
    await expect(page.locator('#blueworx_dashboard_stats .bw-stats')).toHaveCount(0);
    await expect(page.locator('.bw-topbar')).toHaveCount(0);

    // Restore ON so the test is idempotent across runs. The afterEach is the
    // net for when this line is never reached.
    toggle = await themeToggle(page);
    await setFeature(page, 'admin_theme', true);
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

  test('the breadcrumb keeps its divider on a WordPress screen', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);

    // Tools, not one of ours: the divider is a design-system icon, and the icon
    // module used to be loaded on BlueWorx screens only — so everywhere else it
    // was an empty span and the site name ran straight into the screen name.
    await page.goto('/wp-admin/tools.php');

    const divider = page.locator('.bw-topbar-crumb [data-lucide="chevron-right"] svg');
    await expect(divider).toHaveCount(1);

    const drawn = await divider.evaluate((el) => el.getBoundingClientRect().width);
    expect(drawn, 'the divider is in the markup but has no size').toBeGreaterThan(4);
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
    await expect(page.locator('#blueworx_dashboard_stats .bw-stats')).toBeVisible();

    // Quick Draft is hidden by default via default_hidden_meta_boxes. toBeHidden
    // passes whether the box is display:none or absent. This asserts the default
    // only; a user who re-enables it in Screen Options overrides the filter, so
    // this runs against the automation account's untouched dashboard.
    await expect(page.locator('#dashboard_quick_press')).toBeHidden();
  });

  test('mobile swaps the native admin bar for the BlueWorx bar and its drawer', async ({ page }) => {
    await login(page);
    await page.setViewportSize({ width: 480, height: 900 });
    await page.goto(DASH_PATH);

    // This used to assert the opposite: the native bar stayed on a phone
    // because its toggle was the only way to reach the menu. The drawer is that
    // way in now, so the bar can go — but only once the script that opens the
    // drawer has said it is ready. That gate is the safety property worth
    // testing, so it is asserted before anything else.
    await expect(page.locator('html.bw-drawer-ready')).toHaveCount(1);

    await expect(page.locator('.bw-topbar')).toBeVisible();
    await expect(page.locator('[data-blueworx-drawer-toggle]')).toBeVisible();
    await expect(page.locator('#wpadminbar')).toBeHidden();
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

    // The WordPress logo is replaced by the site-name wordmark. It is in the
    // markup at every width, but from 900px up the split-screen brand panel
    // carries the branding and this copy is hidden — so assert the element and
    // its styling, not its visibility. Layout lives in login-design.spec.js.
    const logo = page.locator('.login h1 a');
    await expect(logo).toHaveCount(1);
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

    // The wash, not the brand pill: the row you are ON carries the pill, and the
    // parent above it gets this instead. Hover must still not move it.
    //
    // Asked for as a colour rather than as a string. The rule behind it now
    // reads `color-mix(in srgb, var(--bw-white) 6%, transparent)` instead of an
    // rgba() literal, and the browser serialises that as `color(srgb 1 1 1 /
    // 0.06)` — the same colour, spelled differently. Resolving the expected
    // value through the same engine keeps this pinned to 6% white without
    // pinning it to one browser's spelling.
    const sixPercentWhite = await page.evaluate(() => {
      const probe = document.createElement('div');
      probe.style.backgroundColor = 'color-mix(in srgb, #fff 6%, transparent)';
      document.body.append(probe);
      const value = getComputedStyle(probe).backgroundColor;
      probe.remove();
      return value;
    });
    expect(before).toBe(sixPercentWhite);
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
    const badge = page.locator('#adminmenu li a[href="edit.php"] .bwx-badge');

    if (published > 0) {
      await expect(badge).toHaveText(String(published));
      await expect(badge).toHaveAttribute('aria-label', new RegExp(`${published}`));
    } else {
      await expect(badge).toHaveCount(0);
    }
  });

  test('core screens get card containers, and ours bring their own', async ({ page }) => {
    await page.setViewportSize({ width: 1600, height: 900 });
    await login(page);

    const white = 'rgb(255, 255, 255)';

    // WordPress's own screens are still carded by the re-skin — that is the
    // half of this the design system migration did not touch.
    await page.goto('/wp-admin/options-general.php');
    await expect(page.locator('.wrap > form > .form-table').first()).toHaveCSS('background-color', white);

    // Ours no longer have a form-table to card: they are built from the design
    // system's own cards. The thing worth holding is that the re-skin does not
    // then card the card — a second background behind the first is how nesting
    // shows up.
    await page.goto('/wp-admin/admin.php?page=blueworx-cache');
    const card = page.locator('.bw-admin .bw-card').first();
    await expect(card).toBeVisible();
    await expect(page.locator('.bw-admin .bw-card .bw-card')).toHaveCount(0);

    // And a card is inset from the edges of the screen rather than running
    // edge-to-edge. It used to be checked as a width under 1300px, which was
    // really testing the 760px column these screens were pinned to; the screens
    // fill the width now, so the gutter is the thing that actually holds.
    const inset = await page.evaluate(() => {
      const el = document.querySelector(".bw-admin .bw-card");
      const shell = document.querySelector(".bw-page");

      if (!el || !shell) {
        return null;
      }

      const a = el.getBoundingClientRect();
      const b = shell.getBoundingClientRect();

      return { left: a.left - b.left, right: b.right - a.right };
    });

    expect(inset, "no card inside .bw-page").not.toBeNull();
    expect(inset.left, "the card runs to the left edge").toBeGreaterThanOrEqual(8);
    expect(inset.right, "the card runs to the right edge").toBeGreaterThanOrEqual(8);
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

    // .06em at 11px, per the design.
    expect(style.letterSpacing).toBe('0.66px');
    expect(style.fontSize).toBe('11px');
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

  test('Guides is its own row in Overview, directly below BlueWorx', async ({ page }) => {
    await login(page);
    await page.goto(DASH_PATH);

    const slugs = await page
      .locator('#adminmenu > li.menu-top > a.menu-top')
      .evaluateAll((els) => els.map((el) => el.getAttribute('href')));

    const blueworx = slugs.findIndex((h) => h && h.includes('page=blueworx-labs-wordpress'));
    const guides = slugs.findIndex((h) => h && h.includes('page=blueworx-guides'));

    expect(blueworx).toBeGreaterThanOrEqual(0);
    expect(guides).toBe(blueworx + 1);

    // Same group as BlueWorx, so it must not open one of its own.
    await expect(page.locator('#adminmenu > li.menu-top').nth(guides)).not.toHaveClass(
      /bw-group-start/
    );

    // And it has left the BlueWorx submenu. Scoped to that row: add_menu_page()
    // gives Guides a self-titled submenu of its own, which an unscoped href
    // match would hit.
    await expect(
      page.locator(
        '#adminmenu > li.menu-top:has(> a[href*="page=blueworx-labs-wordpress"]) .wp-submenu a[href*="page=blueworx-guides"]'
      )
    ).toHaveCount(0);
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

  test('name fields are paired side by side on the profile screen', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/profile.php');

    const first = await page.locator('[data-bw-field="first_name"]').boundingBox();
    const last = await page.locator('[data-bw-field="last_name"]').boundingBox();

    // Same row, different columns.
    expect(Math.abs(first.y - last.y)).toBeLessThan(4);
    expect(last.x).toBeGreaterThan(first.x);
  });

  test('cards carry an explanatory subtitle', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/profile.php');
    await expect(page.locator('.bw-profile-card-sub').first()).toBeVisible();
  });

  test('editing another user shows a back link to the users list', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/users.php');

    // The back link only exists on user-edit.php. Core sends you to your own
    // profile.php when you click yourself, where the link is correctly absent,
    // so this only tests anything with a second user present.
    const row = page.locator('#the-list tr').filter({ hasNotText: 'admin' }).first();
    test.skip(await row.count() === 0, 'Harness has only one user');

    await row.locator('a').first().click();
    await expect(page.locator('.bw-profile-back')).toBeVisible();
  });

  test('own profile has no back link', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/profile.php');
    await expect(page.locator('.bw-profile-back')).toHaveCount(0);
  });

  test('saving the profile still persists a change', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/profile.php');

    const nickname = page.locator('#nickname');
    const original = await nickname.inputValue();
    const probe = `bw-test-${Date.now()}`;

    // The native #submit control is hidden by design — the hero's Save Changes
    // button (#bw-profile-save) is what a real user clicks, and it proxies the
    // native submit via nativeSubmit.click(). Clicking through the hero button
    // here is what actually guards the "still saves" invariant post-redesign.
    await nickname.fill(probe);
    await page.locator('#bw-profile-save').click();
    await page.goto('/wp-admin/profile.php');
    await expect(page.locator('#nickname')).toHaveValue(probe);

    // Restore, and wait for the save to land so teardown cannot race it and
    // leave the harness carrying a bw-test-* nickname into the next run.
    await page.locator('#nickname').fill(original);
    await page.locator('#bw-profile-save').click();
    await expect(page.locator('#nickname')).toHaveValue(original);
  });

  test('every profile field stays inside the form after the restructure', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/profile.php');

    // The redesign MOVES core's markup rather than recreating it. Any control
    // that lands outside #your-profile silently stops posting — the save test
    // above would still pass, because it only exercises one field. This is the
    // structural guard for all of them.
    const inForm = await page.locator('#your-profile input, #your-profile select, #your-profile textarea').count();
    const onPage = await page.locator('.wrap input, .wrap select, .wrap textarea').count();

    // The hero and back link add only a <button> and an <a>, never a field, so
    // every input/select/textarea under .wrap must still be inside the form.
    expect(inForm).toBeGreaterThan(0);
    expect(onPage).toBe(inForm);
  });

  test('the security card carries the sessions control', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/profile.php');

    // Account Management routes to the right-hand card; the "Log Out Everywhere
    // Else" row must travel with it rather than being orphaned or dropped.
    // Core renders this row on every own-profile view, so this is an outright
    // assertion — no skip guard, which would let the section go missing quietly.
    await expect(page.locator('.bw-profile-card .user-sessions-wrap')).toBeAttached();
    await expect(page.locator('.bw-profile-card #destroy-sessions')).toBeVisible();
  });

  test('core rows hidden by class stay hidden inside the cards', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/profile.php');

    // The card grid must not out-specify core's own class-only hiding rules.
    // Both of these are hidden until the user asks to change their password.
    await expect(page.locator('#your-profile tr.user-pass2-wrap')).toBeHidden();
    await expect(page.locator('#your-profile tr.pw-weak')).toBeHidden();
  });

  test('no bare section headings are left visible outside the cards', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/profile.php');

    // Every original <h2> (Name, Contact Info, About Yourself, Account
    // Management, ...) is left in the form so any nonce/markup it wraps still
    // posts, but must be hidden — the card title stands in for it visually.
    const strayHeadings = page.locator('#your-profile > h2');
    const count = await strayHeadings.count();

    for (let i = 0; i < count; i += 1) {
      await expect(strayHeadings.nth(i)).toBeHidden();
    }

    // And the native submit button must not be visibly duplicating the hero's
    // Save Changes button, even though it stays in the DOM and clickable.
    await expect(page.locator('#your-profile p.submit')).toBeHidden();
  });

  test('a delete card appears when editing another user', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/users.php');
    const row = page.locator('#the-list tr').filter({ hasNotText: 'admin' }).first();
    test.skip(await row.count() === 0, 'Harness has only one user');

    await row.locator('a').first().click();
    await expect(page.locator('.bw-profile-danger')).toBeVisible();
  });

  test('hovering a parent menu item reveals its submenu', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    // Posts is not the current menu on the dashboard, so it uses the fly-out
    // path rather than the current-item accordion.
    const posts = page.locator('#menu-posts');
    await posts.hover();

    const submenu = posts.locator('.wp-submenu');
    await expect(submenu).toBeVisible();

    // A naive visibility check would pass even if the fly-out rendered at the
    // wrong offset (still on-screen, just beside the wrong item, or clipped by
    // #adminmenuwrap's scroll container). Assert it actually sits beside its
    // own item and fully inside the viewport.
    const submenuBox = await submenu.boundingBox();
    const itemBox = await posts.boundingBox();
    const viewport = page.viewportSize();

    expect(submenuBox.x).toBeGreaterThan(0);
    expect(submenuBox.y).toBeGreaterThanOrEqual(0);
    expect(submenuBox.y + submenuBox.height).toBeLessThanOrEqual(viewport.height);
    expect(Math.abs(submenuBox.y - itemBox.y)).toBeLessThanOrEqual(itemBox.height);
  });

  test('keyboard focus reveals the submenu too', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    await page.locator('#menu-posts > a.menu-top').focus();

    const submenu = page.locator('#menu-posts .wp-submenu');
    await expect(submenu).toBeVisible();

    const submenuBox = await submenu.boundingBox();
    const viewport = page.viewportSize();

    expect(submenuBox.x).toBeGreaterThan(0);
    expect(submenuBox.y).toBeGreaterThanOrEqual(0);
    expect(submenuBox.y + submenuBox.height).toBeLessThanOrEqual(viewport.height);
  });

  test('a fly-out near the bottom of the sidebar flips upward', async ({ page }) => {
    // A short viewport forces the last menu item's fly-out to overflow the
    // viewport foot if it were positioned at the item's own top offset.
    await page.setViewportSize({ width: 1280, height: 500 });
    await login(page);
    await page.goto(DASH_PATH);

    const lastItem = page.locator('#adminmenu > li.menu-top.wp-not-current-submenu').last();
    await lastItem.hover();

    const submenu = lastItem.locator('.wp-submenu');
    await expect(submenu).toBeVisible();

    const submenuBox = await submenu.boundingBox();
    const itemBox = await lastItem.boundingBox();
    const viewport = page.viewportSize();

    expect(submenuBox.y + submenuBox.height).toBeLessThanOrEqual(viewport.height);
    // Proof it actually flipped rather than merely fitting: the fly-out's top
    // is above the item's own top offset.
    expect(submenuBox.y).toBeLessThan(itemBox.y);
  });

  test('the BlueWorx top bar does not cover the block editor toolbar in normal mode', async ({ page }) => {
    // Wide enough, and above the 961px breakpoint, so the sidebar sits in its
    // expanded (232px) state — the state where core's 160px assumption and our
    // width disagree, which is what the horizontal assertions below cover.
    await page.setViewportSize({ width: 1265, height: 900 });
    await login(page);
    await page.goto('/wp-admin/post-new.php');

    // Leave fullscreen if WordPress remembered it on — this test asserts the
    // normal-mode offset, so asserting in fullscreen would test the wrong state.
    await page.evaluate(() => {
      const { select, dispatch } = window.wp.data;
      if (select('core/edit-post').isFeatureActive('fullscreenMode')) {
        dispatch('core/edit-post').toggleFeature('fullscreenMode');
      }
    });

    const skeleton = page.locator('.interface-interface-skeleton');
    await expect(skeleton).toBeVisible();

    const bar = await page.locator('.bw-topbar').boundingBox();
    const sidebar = await page.locator('#adminmenuback').boundingBox();
    const editor = await skeleton.boundingBox();

    expect(editor.y).toBeGreaterThanOrEqual(bar.y + bar.height);

    // Core hard-codes the skeleton's left edge to its own 160px expanded
    // admin-menu width. Our expanded sidebar is 232px, so without the
    // matching override the editor — including its own Undo button — renders
    // partly underneath ours.
    expect(editor.x).toBeGreaterThanOrEqual(sidebar.x + sidebar.width);

    const undo = await page.locator('button[aria-label="Undo"]').boundingBox();
    expect(undo.x).toBeGreaterThanOrEqual(sidebar.x + sidebar.width);
  });

  test('the BlueWorx top bar hides itself in fullscreen mode instead of covering the editor toolbar', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/post-new.php');

    // Force fullscreen on — this test asserts the fullscreen-mode behaviour, so
    // it must not depend on whatever state WordPress remembered for this user.
    await page.evaluate(() => {
      const { select, dispatch } = window.wp.data;
      if (!select('core/edit-post').isFeatureActive('fullscreenMode')) {
        dispatch('core/edit-post').toggleFeature('fullscreenMode');
      }
    });

    const skeleton = page.locator('.interface-interface-skeleton');
    await expect(skeleton).toBeVisible();

    // Our chrome is gone rather than reserved-for: the bar isn't just moved out
    // of the way, it isn't there to cover anything.
    await expect(page.locator('.bw-topbar')).toBeHidden();
    await expect(page.locator('.bw-brand')).toBeHidden();

    // The editor's own toolbar (undo/redo/inserter) sits at the very top of the
    // skeleton in fullscreen — confirm nothing of ours still covers it.
    const editor = await skeleton.boundingBox();
    expect(editor.y).toBe(0);
  });

  test('the block editor toolbar clears the folded-width sidebar in the 783-960px band', async ({ page }) => {
    // Below 961px the sidebar is always in its folded state, and folded's 36px
    // happens to equal core's own folded admin-menu width — so this state
    // needs no left-offset override, only pinning: a future change to
    // --bwt-sidebar-w or the breakpoints could silently break the agreement.
    await page.setViewportSize({ width: 900, height: 700 });
    await login(page);
    await page.goto('/wp-admin/post-new.php');

    await page.evaluate(() => {
      const { select, dispatch } = window.wp.data;
      if (select('core/edit-post').isFeatureActive('fullscreenMode')) {
        dispatch('core/edit-post').toggleFeature('fullscreenMode');
      }
    });

    const skeleton = page.locator('.interface-interface-skeleton');
    await expect(skeleton).toBeVisible();

    const sidebar = await page.locator('#adminmenuback').boundingBox();
    const editor = await skeleton.boundingBox();

    expect(editor.x).toBeGreaterThanOrEqual(sidebar.x + sidebar.width);
  });

  test('the sidebar labels collapse with the rail, however the rail got narrow', async ({ page }) => {
    // Two different classes fold this sidebar. body.folded is the collapse
    // button; body.auto-fold is WordPress narrowing it on its own between 783
    // and 960px. The label-hiding rule originally named only the first, so on
    // any laptop window in that band the 36px rail still carried its labels:
    // the group headings wrapped into stacked fragments between the icons and
    // Log Out broke across two lines.
    const labelDisplays = () =>
      page.evaluate(() => ({
        heading: getComputedStyle(
          document.querySelector('#adminmenu li.bw-group-start'),
          '::before'
        ).display,
        logout: getComputedStyle(document.querySelector('#adminmenu li.bw-logout a span')).display,
        railWidth: Math.round(document.getElementById('adminmenuwrap').getBoundingClientRect().width),
      }));

    await login(page);

    // Auto-folded: a 36px rail, so the labels must be gone. 940px, not 900:
    // at 900 and below the sidebar is a drawer now, not a folded rail, so
    // there is no rail there to measure.
    await page.setViewportSize({ width: 940, height: 900 });
    await page.goto(DASH_PATH);

    let state = await labelDisplays();
    expect(state.railWidth).toBeLessThan(60);
    expect(state.heading).toBe('none');
    expect(state.logout).toBe('none');

    // Wide and expanded: auto-fold is STILL on the body here, so a rule without
    // an upper bound would wrongly hide the labels on a full-width sidebar.
    await page.setViewportSize({ width: 1200, height: 900 });

    state = await labelDisplays();
    expect(state.railWidth).toBeGreaterThan(150);
    expect(state.heading).toBe('block');
    expect(state.logout).toBe('block');

    // Wide but collapsed with the button: back to a rail, back to no labels.
    await page.evaluate(() => document.body.classList.add('folded'));

    state = await labelDisplays();
    expect(state.railWidth).toBeLessThan(60);
    expect(state.heading).toBe('none');
    expect(state.logout).toBe('none');
  });

  test('a plugin that takes the whole window over gets the whole window', async ({ page }) => {
    // LatePoint is not installed on this harness, so the test recreates the
    // three rules it actually ships on its own admin screens (verified against
    // latepoint/public/stylesheets/admin.css 5.6.10, and the resulting geometry
    // measured on a live LatePoint install): body.latepoint-admin hides
    // #adminmenumain outright, resets #wpcontent to margin-left:0 because its app
    // runs full width behind its own left nav, and pulls the body up by 32px to
    // reclaim the space core reserves on <html> for the admin bar it hides.
    //
    // This is a cascade test, which is the whole of the bug — twice over. Our
    // `body:not(.folded) #wpcontent` rule carries a type selector LatePoint's
    // `.latepoint-admin #wpcontent` does not, so ours used to win and hold the
    // content indented against a sidebar that was no longer rendered. And we
    // zero html.wp-toolbar's padding-top ourselves, so that -32px pull had
    // nothing left to cancel and hauled the whole app off the top of the window.
    await page.setViewportSize({ width: 1265, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    await page.addStyleTag({
      content: `
        .latepoint-admin #adminmenumain { display: none; }
        .latepoint-admin #wpcontent { margin-left: 0px; padding-left: 0px; }
        .wp-toolbar body.latepoint-admin { margin-top: -32px; }
      `,
    });
    await page.evaluate(() => document.body.classList.add('latepoint-admin'));

    const layout = await page.evaluate(() => {
      const content = document.getElementById('wpcontent');
      const box = content.getBoundingClientRect();
      return {
        contentLeft: Math.round(box.x),
        contentTop: Math.round(box.y),
        contentMargin: getComputedStyle(content).marginLeft,
        contentPadding: getComputedStyle(content).paddingTop,
        bodyMarginTop: getComputedStyle(document.body).marginTop,
        bodyTop: Math.round(document.body.getBoundingClientRect().y),
      };
    });

    // Neither offset is held open: margin-left for a sidebar that isn't painted,
    // padding-top for a bar that is no longer drawn.
    expect(layout.contentMargin).toBe('0px');
    expect(layout.contentPadding).toBe('0px');
    expect(layout.contentLeft).toBe(0);
    expect(layout.contentTop).toBe(0);

    // And nothing is hauled off the top of the window: LatePoint's -32px pull
    // compensates for space we no longer reserve, so it has to be cancelled or
    // its own search field and New Booking button are clipped by the window edge.
    expect(layout.bodyMarginTop).toBe('0px');
    expect(layout.bodyTop).toBe(0);

    // Our chrome is gone rather than reserved-for, the same answer fullscreen
    // gets. The brand block matters specifically: it is fixed and lives outside
    // the #adminmenumain LatePoint hides, so it survives on its own unless we
    // hide it too — it was what floated at the top of the empty column.
    await expect(page.locator('.bw-brand')).toBeHidden();
    await expect(page.locator('.bw-topbar')).toBeHidden();
  });

  test("a plugin's sticky header sits below the top bar, not behind it", async ({ page }) => {
    // SureCart pins its own header with `position: sticky; top: 32px` — the
    // height of the WordPress admin bar, hard-coded (surecart 4.7.0, the header
    // in dist/admin/dashboard.js and #sc-settings-header) — at z-index 9989,
    // one step below our bar. From 783px up we hide the admin bar and put our
    // own 64px bar in that band, so the header pinned 32px too high and our bar
    // painted over its top half. Measured on the live site: 32px of the header
    // covered on both Commerce > Dashboard and Commerce > Settings.
    //
    // SureCart is not installed on this harness, so the test recreates the rule
    // rather than the plugin. Any plugin that offsets by the admin bar's height
    // has the same bug, which is why the fix keys on that value rather than on
    // SureCart's own selectors — and why recreating the rule tests the fix
    // honestly.
    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    // Injected after load, exactly as SureCart's app renders its own: this is
    // also what proves the fix notices a header that arrives late.
    await page.evaluate(() => {
      const content = document.getElementById('wpbody-content');

      const header = document.createElement('div');
      header.id = 'bw-plugin-header';
      header.style.cssText =
        'position:sticky;top:32px;z-index:9989;height:60px;background:#fff';
      content.prepend(header);

      // Enough page to scroll with: a header only slides under the bar once it
      // is pinned, and the dashboard can be shorter than the window.
      const filler = document.createElement('div');
      filler.style.height = '2400px';
      content.append(filler);
    });

    await page.evaluate(() => window.scrollTo(0, 1200));

    const clearance = () =>
      page.evaluate(() => {
        const bar = document.querySelector('.bw-topbar').getBoundingClientRect();
        const header = document.getElementById('bw-plugin-header').getBoundingClientRect();
        return Math.round(header.top - bar.bottom);
      });

    await expect.poll(clearance).toBeGreaterThanOrEqual(0);
  });

  test('a plugin app screen gets the top of the window to itself', async ({ page }) => {
    // SureCart's Commerce screens draw their own header, breadcrumb and controls.
    // Ours above theirs was two headers saying the same thing, so these screens
    // lose the BlueWorx bar entirely and the app's own header becomes the top of
    // the page. SureCart is not installed here, so the test sets the body class
    // its screens carry — see includes/admin-app-screens.php, which is what puts
    // that class there on a real one.
    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    // The bar is there on an ordinary WordPress screen.
    await expect(page.locator('.bw-topbar')).toBeVisible();

    await page.evaluate(() => {
      document.body.classList.add('bw-app-screen');

      const header = document.createElement('div');
      header.id = 'bw-app-header';
      header.style.cssText =
        'position:sticky;top:32px;z-index:9989;height:60px;background:#fff';
      document.getElementById('wpbody-content').prepend(header);

      const filler = document.createElement('div');
      filler.style.height = '2400px';
      document.getElementById('wpbody-content').append(filler);
    });

    await expect(page.locator('.bw-topbar')).toBeHidden();

    // No room held open for a bar that is not drawn.
    await expect
      .poll(() => page.evaluate(() => getComputedStyle(document.getElementById('wpcontent')).paddingTop))
      .toBe('0px');

    // And the app's own header pins flush to the top of the window rather than
    // 32px below it, where it would leave a band of the page scrolling past
    // above it.
    await page.evaluate(() => window.scrollTo(0, 1200));
    await expect
      .poll(() =>
        page.evaluate(() =>
          Math.round(document.getElementById('bw-app-header').getBoundingClientRect().top)
        )
      )
      .toBe(0);

    // The sidebar stays: unlike a plugin that hides the WordPress menu and
    // carries its own, this one has no navigation of its own to fall back on.
    await expect(page.locator('#adminmenuwrap')).toBeVisible();
    await expect(page.locator('.bw-brand')).toBeVisible();
  });

  test("a plugin's header is caught even when its stylesheet lands late", async ({ page }) => {
    // How the SureCart bug survived the first fix. Its app inserts the header
    // first and injects the rule that makes it sticky a moment later, so at the
    // instant the element arrives it is still position: static and reads as
    // nothing to correct. Nothing re-checked it, so the header stayed pinned 32px
    // too high until a resize happened to trigger another pass — which is why
    // zooming the window "fixed" it.
    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    await page.evaluate(() => {
      const content = document.getElementById('wpbody-content');

      const header = document.createElement('div');
      header.id = 'bw-late-header';
      header.className = 'plugin-header-late';
      header.style.cssText = 'height:60px;background:#fff';
      content.prepend(header);

      const filler = document.createElement('div');
      filler.style.height = '2400px';
      content.append(filler);

      // The rule arrives after the element, exactly as an emotion/CSS-in-JS
      // runtime writes it.
      setTimeout(() => {
        const style = document.createElement('style');
        style.textContent =
          '.plugin-header-late { position: sticky; top: 32px; z-index: 9989; }';
        document.head.append(style);
      }, 300);
    });

    await page.evaluate(() => window.scrollTo(0, 1200));

    const clearance = () =>
      page.evaluate(() => {
        const bar = document.querySelector('.bw-topbar').getBoundingClientRect();
        const header = document.getElementById('bw-late-header').getBoundingClientRect();
        return Math.round(header.top - bar.bottom);
      });

    await expect.poll(clearance, { timeout: 15000 }).toBeGreaterThanOrEqual(0);
  });

  test("a plugin's own scrolling panel is left alone", async ({ page }) => {
    // The other half of the fix. A header pinned inside a panel that scrolls by
    // itself — SureCart's Products list, and every WordPress DataViews table —
    // sticks to that panel, not to the window, so nothing covers it and moving
    // it down by the height of our bar would be the bug rather than the fix.
    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page);
    await page.goto(DASH_PATH);

    await page.evaluate(() => {
      const panel = document.createElement('div');
      panel.style.cssText = 'overflow-y:auto;height:300px';

      const header = document.createElement('div');
      header.id = 'bw-panel-header';
      header.style.cssText =
        'position:sticky;top:32px;z-index:9989;height:60px;background:#fff';

      const filler = document.createElement('div');
      filler.style.height = '1200px';

      panel.append(header, filler);
      document.getElementById('wpbody-content').prepend(panel);
    });

    // Long enough for the fix to have run and moved it, had it been going to.
    await expect
      .poll(() =>
        page.evaluate(
          () => getComputedStyle(document.getElementById('bw-panel-header')).top
        )
      )
      .toBe('32px');
  });

  test('the plugin updates itself rather than offering a toggle', async ({ page }) => {
    // Two things at once, and both need a real WordPress. WordPress prints
    // "Auto-updates enabled" as plain text where a plugin has forced the
    // decision, and an Enable/Disable link where it has left the choice to the
    // site — so that difference is the only outward sign the filter is doing
    // its job. And the Plugins screen renders at all, which it would not if the
    // vendored update checker failed to parse or load.
    await login(page);
    await page.goto('/wp-admin/plugins.php');

    const row = page.locator(
      'tr[data-plugin="blueworx-labs-wordpress/blueworx-labs-wordpress.php"]'
    );
    await expect(row).toHaveCount(1);

    const column = row.locator('.column-auto-updates');
    await expect(column).toContainText('Auto-updates enabled');
    await expect(column.locator('a')).toHaveCount(0);
  });

  test('the site editor is always fullscreen, so our chrome stays hidden rather than offset', async ({ page }) => {
    // WordPress forces site-editor.php permanently into fullscreen — there is
    // no non-fullscreen state to test here. Our fullscreen rule is what
    // governs it, so this documents that (non-obvious) fact and confirms our
    // chrome disappears rather than needing its own offset rule.
    await login(page);
    await page.goto('/wp-admin/site-editor.php');

    // The site editor can take several seconds to boot on this harness — wait
    // on its own layout element rather than a fixed timeout.
    const layout = page.locator('.edit-site-layout');
    await expect(layout).toBeVisible({ timeout: 15000 });

    await expect(page.locator('body')).toHaveClass(/is-fullscreen-mode/);
    await expect(page.locator('.bw-topbar')).toBeHidden();

    const box = await layout.boundingBox();
    expect(box.x).toBe(0);
    expect(box.y).toBe(0);
  });

  test('the comments tile says so when comments are switched off', async ({ page }) => {
    // A zero on the comments tile means one of two things — nobody has
    // commented, or comments are off entirely. The footnote is what tells them
    // apart, so it has to track the function rather than the count.
    await login(page);

    let commentsOff = null;

    try {
      await page.goto(SETTINGS_PATH);
      await openSectionFor(page, 'comments');
      await setFeature(page, 'comments', true);
      commentsOff = true;
      await saveSettings(page);

      await page.goto(DASH_PATH);
      const tile = page.locator('#blueworx_dashboard_stats .bw-stat', { hasText: 'Comments' });
      await expect(tile.locator('.bw-stat__foot')).toHaveText('Comments are switched off');

      await page.goto(SETTINGS_PATH);
      await openSectionFor(page, 'comments');
      await setFeature(page, 'comments', false);
      commentsOff = false;
      await saveSettings(page);

      await page.goto(DASH_PATH);
      await expect(tile.locator('.bw-stat__foot')).toHaveCount(0);
    } finally {
      // Never leave the site's comments off because an assertion failed.
      if (commentsOff) {
        await page.goto(SETTINGS_PATH);
        await openSectionFor(page, 'comments');
        await setFeature(page, 'comments', false);
        await page.getByRole('button', { name: 'Save Changes' }).click();
      }
    }
  });

  // Every BlueWorx plugin's screens sit inside this re-skin, and they are built
  // from the shared design system. A base rule weighed heavily enough to beat a
  // component class repaints them: `.wp-admin a` outweighed `.bw-btn--primary`,
  // so a plugin's primary button took brand text on its brand background and
  // read as a blank blue block. The link colour still has to win against
  // WordPress's own, which is the other half of this.
  test('the re-skin colours WordPress links without repainting design-system buttons', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-cache');

    const painted = await page.evaluate(() => {
      const host = document.createElement('div');
      host.innerHTML =
        '<a class="bw-btn bw-btn--primary" href="#">Primary</a>' +
        '<a class="bw-btn bw-btn--secondary" href="#">Secondary</a>' +
        '<a href="#">Plain link</a>';
      document.querySelector('#wpbody-content').appendChild(host);
      const colour = (i) => getComputedStyle(host.children[i]).color;
      return { primary: colour(0), secondary: colour(1), plain: colour(2) };
    });

    expect(painted.primary).toBe('rgb(255, 255, 255)');
    expect(painted.secondary).toBe('rgb(10, 12, 41)');
    expect(painted.plain).toBe('rgb(79, 70, 229)');
  });
});
