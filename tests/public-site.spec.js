// Public-site specs are logged out by definition, so every navigation goes
// through cacheBust() — Cloudways Varnish caches logged-out responses and a
// stale hit makes a passing build look broken (see tests/helpers.js:88-105).
import { readFileSync, mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect } from '@playwright/test';
import { test, isPlaceholder, cacheBust, login, baseURL, ADMIN_USER, ADMIN_PASS } from './helpers.js';

// A keyframe step ("0%", "100%", "from", "to") is the one kind of bare-looking
// prelude this file legitimately needs — it is never a document element and
// never needs .bw-page scoping.
const isKeyframeStop = (branch) => branch === 'from' || branch === 'to' || /^\d+(\.\d+)?%$/.test(branch);

/**
 * Runs a set of state-restoring cleanup steps to completion, one after
 * another, even if an earlier step throws — so a failure restoring one
 * piece of mutated global state (e.g. a `.notice-success` assertion timing
 * out) can never skip restoring another, unrelated piece of state.
 *
 * Every step still runs regardless of earlier failures, but a genuine
 * cleanup failure is never swallowed: each step's error is collected and,
 * once all steps have been attempted, re-thrown together so the test still
 * fails loudly and visibly.
 *
 * @param {Array<[string, () => Promise<void>]>} steps  [label, step] pairs.
 */
async function restoreAll(steps) {
  const errors = [];
  for (const [label, step] of steps) {
    try {
      await step();
    } catch (error) {
      errors.push(`${label}: ${error && error.message ? error.message : String(error)}`);
    }
  }
  if (errors.length > 0) {
    throw new Error(
      `Cleanup failed for ${errors.length} of ${steps.length} restore step(s):\n${errors.join('\n')}`
    );
  }
}

/**
 * Reads the currently active theme's slug directly off /wp-admin/themes.php.
 *
 * There is no data-slug attribute on the server-rendered `.theme` markup to
 * read (that only exists in the JS search-results template) — but the
 * "Theme Details" button's id is rendered server-side as `"{$slug}-action"`
 * (wp-admin/themes.php: `$aria_action = $theme['id'] . '-action';`), so the
 * slug is recovered from there instead.
 *
 * @param {import('@playwright/test').Page} page
 * @return {Promise<string>}
 */
async function activeThemeSlug(page) {
  const id = await page.locator('.theme.active button.more-details').getAttribute('id');
  expect(id, 'the currently active theme must be identifiable on themes.php').toBeTruthy();
  return id.replace(/-action$/, '');
}

/**
 * Picks an installed, inactive, activatable theme to switch to — preferring
 * twentytwentyfour/twentytwentyfive (bundled on this harness) but falling
 * back to whatever else is actually installed, rather than assuming a
 * specific theme exists on every install this plugin might land on.
 *
 * Must be called on /wp-admin/themes.php.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} excludeSlug Slug to never pick (the currently active theme).
 * @return {Promise<string>}
 */
async function pickAlternateThemeSlug(page, excludeSlug) {
  const hrefs = await page
    .locator('a.activate')
    .evaluateAll((els) => els.map((el) => el.getAttribute('href')).filter(Boolean));

  const slugs = hrefs
    .map((href) => {
      const match = href.match(/[?&]stylesheet=([^&]+)/);
      return match ? decodeURIComponent(match[1]) : null;
    })
    .filter((slug) => slug && slug !== excludeSlug);

  const preferred = ['twentytwentyfour', 'twentytwentyfive'].find((slug) => slugs.includes(slug));
  const picked = preferred || slugs[0];
  expect(picked, 'themes.php must offer at least one other installed theme to switch to').toBeTruthy();
  return picked;
}

/**
 * Logs in and activates the given theme via /wp-admin/themes.php — the same
 * screen an administrator would use.
 *
 * Navigates straight to the matching "Activate" link's own href (which
 * already carries the required nonce) rather than clicking it: that button
 * lives under an `opacity: 0` hover overlay (wp-admin/css/themes.css) that
 * would add nothing but flakiness to drive via a real click.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} slug Theme stylesheet slug to activate.
 */
async function switchTheme(page, slug) {
  await login(page);
  await page.goto(cacheBust('/wp-admin/themes.php'));

  const hrefs = await page
    .locator('a.activate')
    .evaluateAll((els) => els.map((el) => el.getAttribute('href')).filter(Boolean));
  const activateHref = hrefs.find((href) => {
    const match = href.match(/[?&]stylesheet=([^&]+)/);
    return match && decodeURIComponent(match[1]) === slug;
  });
  expect(activateHref, `no inactive, activatable theme found for slug "${slug}"`).toBeTruthy();

  await page.goto(activateHref);
  await page.goto(cacheBust('/wp-admin/themes.php'));
  expect(await activeThemeSlug(page), `theme "${slug}" must be active after switching`).toBe(slug);
}

test.describe('Public site', () => {
  test.skip(isPlaceholder || !ADMIN_USER || !ADMIN_PASS, 'No real WordPress target configured.');

  test('the front page renders from the plugin, not the theme', async ({ page }) => {
    await page.goto(cacheBust('/'));

    // .bw-page is the plugin's wrapper and the scope for every ported style.
    await expect(page.locator('.bw-page')).toHaveCount(1);
    // wp_head must still run or other plugins and the admin bar break.
    await expect(page.locator('head link[href*="public.css"]')).toHaveCount(1);
    await expect(page.locator('main > div')).toHaveCount(1);
  });

  test('renders identically regardless of the active theme', async ({ page, browser }) => {
    // THE CLAIM UNDER TEST: blueworx_public_document_open()/_close() (see
    // includes/public/render.php) emit the whole document themselves and
    // deliberately never call get_header()/get_footer(), so which theme is
    // active must have zero effect on what a visitor sees. Hosting is not
    // fixed — the owner uploads this plugin to a WordPress install whose
    // theme is unknown — so this is the one test that actually proves that,
    // rather than assuming it. If this fails, STOP: fix the architecture
    // (most likely get_header() crept in, or the active theme is enqueueing
    // something that reaches the plugin's own markup), do not weaken this
    // assertion to make it pass.
    //
    // .bw-page is the class blueworx_public_document_open() puts on <body>
    // itself (not a wrapper div), so its innerHTML is everything the plugin
    // and WordPress core print between <body> and </body> — nav, main,
    // footer, and wp_footer(). Nothing in templates/parts/nav.php,
    // templates/pages/home.php or templates/parts/footer.php embeds a
    // nonce, a timestamp, or a cache-busting query string (the one dynamic
    // value, the copyright year via gmdate('Y'), cannot change between two
    // requests made moments apart in the same test), so no content
    // normalisation is applied to the captured HTML itself — an exact match
    // is the real bar.
    //
    // NORMALISED INSTEAD (two things, neither of them this plugin's markup):
    //
    // 1. Login state. `page` is the one session that logs in to drive
    //    wp-admin (switching the theme requires it), so both .bw-page
    //    captures are taken through a SEPARATE, always-logged-out browser
    //    context/page — matching the pattern `browser.newContext()` already
    //    uses elsewhere in this file for the same reason. First attempt
    //    without this proved it necessary: capturing "after" on the
    //    logged-in `page` showed the admin bar appearing and WordPress
    //    core's speculative-loading <script type="speculationrules">
    //    disappearing — both gated on login state, not on the active theme,
    //    which would have made a login artifact look like a
    //    theme-independence failure.
    //
    // 2. The active theme's own directory path, but ONLY inside that same
    //    <script type="speculationrules"> tag. wp-includes/speculative-loading.php
    //    is hooked unconditionally to `wp_footer` by WordPress core itself
    //    (wp-includes/default-filters.php: add_action('wp_footer',
    //    'wp_print_speculation_rules')) on every WP 6.8+ install, and its
    //    prefetch-exclusion list is built from get_stylesheet_directory_uri()/
    //    get_template_directory_uri() — i.e. it is BY DESIGN meant to name
    //    whichever theme happens to be active, on every front-end page of the
    //    site (a plain blog post shows this exact same before/after diff on a
    //    theme switch). That is WordPress core behaviour, wholly unrelated to
    //    blueworx_public_document_open()/_close() or this plugin's routing —
    //    not get_header() creeping in, not the theme overriding the plugin's
    //    own styles — so only the theme path inside that one script tag is
    //    normalised, not the innerHTML wholesale. Nothing this plugin
    //    actually owns (nav, footer, CTA band, the document shell itself)
    //    ever emits a `/wp-content/themes/` path, so this narrow substitution
    //    cannot hide a genuine theme leak anywhere else in .bw-page.
    const normaliseThemePath = (html) =>
      html.replace(
        /(<script type="speculationrules">)([\s\S]*?)(<\/script>)/,
        (match, open, body, close) =>
          open + body.replace(/\/wp-content\/themes\/[^/"\\]+/g, '/wp-content/themes/__ACTIVE_THEME__') + close
      );

    const beforeContext = await browser.newContext();
    const beforePage = await beforeContext.newPage();
    await beforePage.goto(cacheBust('/'));
    const before = normaliseThemePath(await beforePage.locator('.bw-page').innerHTML());
    await beforeContext.close();

    await login(page);
    await page.goto(cacheBust('/wp-admin/themes.php'));
    const originalSlug = await activeThemeSlug(page);
    const alternateSlug = await pickAlternateThemeSlug(page, originalSlug);

    try {
      await switchTheme(page, alternateSlug);

      const afterContext = await browser.newContext();
      const afterPage = await afterContext.newPage();
      await afterPage.goto(cacheBust('/'));
      const after = normaliseThemePath(await afterPage.locator('.bw-page').innerHTML());
      await afterContext.close();

      expect(
        after,
        `the plugin's .bw-page output must be identical whether "${originalSlug}" or ` +
          `"${alternateSlug}" is the active theme — any difference means theme output is ` +
          'leaking into a page the plugin is supposed to own outright'
      ).toBe(before);
    } finally {
      // This test mutates global site state (the active theme), so it must
      // restore the original theme even if the assertion above throws.
      // restoreAll() (used elsewhere in this file for the same reason) runs
      // every cleanup step to completion and re-throws collected errors
      // afterwards, so a cleanup failure is never silently swallowed.
      await restoreAll([
        [
          'restore original theme',
          async () => {
            await switchTheme(page, originalSlug);
          },
        ],
      ]);
    }
  });

  test('navigation renders and marks the current page', async ({ page }) => {
    // WHY THIS TEST EXISTS: templates/parts/nav.php ports Nav.tsx's active-state
    // logic (exact match for "/", prefix match otherwise) and its asymmetric
    // two-bar hamburger. A regression here silently breaks "where am I" for
    // every visitor, on every page.
    await page.goto(cacheBust('/'));
    await expect(page.locator('nav .nav-links a[href="/"]')).toHaveClass(/active/);
    await expect(page.locator('nav .hamburger span')).toHaveCount(2);
  });

  test('the mobile menu opens and closes', async ({ page }) => {
    // WHY THIS TEST EXISTS: templates/parts/nav.php renders .mobile-menu as a
    // sibling of <nav> that is always present in the DOM (unlike the source,
    // which only mounts it while open) so assets/js/public-nav.js can toggle
    // it. This pins the hamburger actually driving that toggle end to end.
    await page.setViewportSize({ width: 900, height: 800 });
    await page.goto(cacheBust('/'));

    await expect(page.locator('.mobile-menu')).toBeHidden();
    await page.locator('.hamburger').click();
    await expect(page.locator('.mobile-menu')).toBeVisible();
    await page.locator('.hamburger').click();
    await expect(page.locator('.mobile-menu')).toBeHidden();
  });

  test('the CTA band and footer render on every page', async ({ page }) => {
    // WHY THIS TEST EXISTS: CtaBand.tsx/Footer.tsx are ported as a single
    // template part (templates/parts/footer.php) that home.php already calls
    // after </main>. This pins the acceptance shape: the CTA band is a
    // sibling of <main>, not nested inside it, and the footer's class
    // structure matches the source exactly.
    await page.goto(cacheBust('/'));

    const ctaBand = page.locator('body > .cta-soft');
    await expect(ctaBand, 'the CTA band must be a direct child of <body>, outside <main>').toHaveCount(1);
    await expect(page.locator('main ~ .cta-soft'), 'the CTA band must come after <main>, before the footer').toHaveCount(1);

    const ctaInner = ctaBand.locator('.cta-inner');
    await expect(ctaInner).toHaveCount(1);
    await expect(ctaInner.locator('> .blob')).toHaveCount(2);
    await expect(ctaInner.locator('> h2.h2')).toHaveCount(1);
    await expect(ctaInner.locator('> p')).toHaveCount(1);

    const ctaLinks = ctaInner.locator('.cta-actions a');
    await expect(ctaLinks).toHaveCount(2);
    await expect(ctaLinks.nth(0)).toHaveClass(/\bbtn-brand\b/);
    await expect(ctaLinks.nth(0)).toHaveAttribute('href', /\/pricing\/?$/);
    await expect(ctaLinks.nth(1)).toHaveClass(/\bbtn-outline-w\b/);
    await expect(ctaLinks.nth(1)).toHaveAttribute('href', /\/contact\/?$/);

    const footer = page.locator('footer .ft');
    await expect(footer).toHaveCount(1);
    await expect(footer.locator('> .fb')).toHaveCount(1);

    // Three social links, none of them real hrefs in the source.
    const socialLinks = footer.locator('.fb .fsocial a');
    await expect(socialLinks).toHaveCount(3);
    const socialHrefs = await socialLinks.evaluateAll((els) => els.map((el) => el.getAttribute('href')));
    for (const href of socialHrefs) {
      expect(href, 'social links must stay non-links, not point at an invented destination').toBeNull();
    }

    await expect(footer.locator('> .fcol')).toHaveCount(2);
    // Blog/Resources/Careers also have no href in the source.
    const aboutCol = footer.locator('> .fcol').nth(1);
    const nonLinkTexts = ['Blog', 'Resources', 'Careers'];
    for (const text of nonLinkTexts) {
      const el = aboutCol.locator('a', { hasText: text });
      await expect(el).toHaveCount(1);
      expect(await el.getAttribute('href'), `"${text}" must stay a non-link`).toBeNull();
    }

    await expect(footer.locator('> .fnews .fnews-in input')).toHaveCount(1);
    await expect(footer.locator('> .fnews .fnews-in button')).toHaveCount(1);
    await expect(page.locator('footer > .fbot')).toHaveCount(1);
    await expect(page.locator('footer > .fbot > p')).toHaveCount(2);
  });

  test('the footer logo is bundled by the plugin, not resolved via the active theme', async ({ page }) => {
    // WHY THIS TEST EXISTS: the plugin's core guarantee is that public output
    // is identical regardless of which theme is active. The footer logo
    // previously read get_theme_mod('custom_logo') — a per-theme setting that
    // changes or vanishes on theme switch. It must now be the plugin's own
    // bundled asset at assets/img/logo.png, served from BLUEWORX_LABS_URL.
    // A naive "<img> exists" check would pass even for a broken src, so this
    // asserts the actual HTTP response for the logo request succeeds and
    // that its URL points at the plugin, not a theme or upload path.
    await page.goto(cacheBust('/'));

    const logo = page.locator('footer .fb img');
    await expect(logo).toHaveCount(1);

    const src = await logo.getAttribute('src');
    expect(src, 'logo src must be set').toBeTruthy();
    expect(src, 'logo must be served from the plugin, not a theme/uploads path').toMatch(
      /\/wp-content\/plugins\/blueworx-labs-wordpress\/assets\/img\/logo\.png/
    );

    const response = await page.request.get(src);
    expect(response.status(), 'the plugin logo asset must load successfully').toBe(200);
  });

  test('the public_site feature is registered and on by default', async ({ page }) => {
    await login(page);
    await page.goto(cacheBust('/wp-admin/admin.php?page=blueworx-labs-wordpress'));

    const toggle = page.locator('[data-blueworx-feature="public_site"]');
    await expect(toggle, 'the feature must appear on the Enhancements screen').toHaveCount(1);
    // Absent option means enabled (features.php:125-127) — a fresh install
    // must ship with the public site on, or activation renders nothing.
    await expect(toggle).toBeChecked();
  });

  test('activation creates the plugin-owned pages', async ({ page }) => {
    await login(page);
    await page.goto(cacheBust('/wp-admin/edit.php?post_type=page'));

    // Asserts the registry actually produced a Page, which is what later tasks
    // route on. A 200 on "/" would pass without any of this existing.
    await expect(
      page.locator('#the-list .row-title', { hasText: 'Home' }),
      'activation must create the Home page'
    ).toHaveCount(1);
  });

  test('the public stylesheet loads on the front page', async ({ page }) => {
    await page.goto(cacheBust('/'));
    const href = await page
      .locator('link[rel="stylesheet"][href*="public.css"]')
      .first()
      .getAttribute('href');

    expect(href, 'public.css must be enqueued').toBeTruthy();
    // Cache-busting is a house rule, not a nicety: without it a CSS change
    // silently does not reach anyone until their browser cache expires.
    expect(href, 'stylesheet must be versioned').toMatch(/[?&]ver=/);
  });

  test('a plugin-owned public page stays reachable while logged out with Site Protection on', async ({
    page,
    browser,
  }) => {
    // WHY THIS TEST EXISTS: the Site Protection exemption for plugin-owned
    // pages (includes/public/pages.php) is wired to a filter that fires on
    // `init` priority 1 — before the main query runs. A version of that
    // exemption that reads is_page()/get_queried_object() at that point
    // always sees "not a page" and never exempts anything, so turning Site
    // Protection on would wp_die() every logged-out visitor to the plugin's
    // own marketing pages. Nothing else in this suite turns Site Protection
    // on, so nothing else would catch that regression.
    await login(page);

    // Grab the Home page's real public URL before touching any settings.
    await page.goto(cacheBust('/wp-admin/edit.php?post_type=page'));
    const homeRow = page.locator('#the-list tr', {
      has: page.locator('.row-title', { hasText: 'Home' }),
    });
    const homeUrl = await homeRow.locator('.row-actions .view a').getAttribute('href');
    expect(homeUrl, 'the Home page must expose a public URL').toBeTruthy();

    const settingsPath = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
    await page.goto(cacheBust(settingsPath));

    const siteProtectionToggle = page.locator(
      'input.blueworx-feature-toggle[data-blueworx-feature="site_protection"]'
    );
    const frontendToggle = page.locator('input[name="blueworx_frontend_protection_enabled"]');

    const featureWasChecked = await siteProtectionToggle.isChecked();
    const frontendWasChecked = await frontendToggle.isChecked();

    try {
      if (!featureWasChecked) {
        await siteProtectionToggle.setChecked(true);
      }
      await frontendToggle.setChecked(true);
      await page.getByRole('button', { name: 'Save Changes' }).click();
      await expect(page.locator('.notice-success').first()).toContainText('Settings saved');

      // A fresh, unauthenticated context: the plugin-owned page must still be
      // reachable even though the frontend Site Protection gate is now on.
      const loggedOutContext = await browser.newContext();
      const loggedOutPage = await loggedOutContext.newPage();
      const response = await loggedOutPage.goto(cacheBust(homeUrl));

      expect(
        response && response.status(),
        'a plugin-owned public page must not be blocked (wp_die 403) by Site Protection'
      ).toBe(200);

      await loggedOutContext.close();
    } finally {
      // Restore both toggles independently — two unrelated pieces of
      // mutated state — so a throw restoring one (e.g. the .notice-success
      // assertion timing out, or a setChecked() actionability timeout) can
      // never skip restoring the other. Previously this ran as one
      // monolithic sequence sharing a single Save Changes click: if the
      // frontend-toggle step threw, the site_protection toggle was never
      // set back and Save was never clicked, leaving Site Protection ON for
      // the rest of the suite (and any real visitor) — exactly the
      // "left on" leak this restructure closes. Any errors are collected
      // and re-thrown together so a genuine cleanup failure still fails
      // this test loudly instead of being swallowed.
      await restoreAll([
        [
          'restore frontend protection toggle',
          async () => {
            await page.goto(cacheBust(settingsPath));
            await page
              .locator('input[name="blueworx_frontend_protection_enabled"]')
              .setChecked(frontendWasChecked);
            await page.getByRole('button', { name: 'Save Changes' }).click();
            await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
          },
        ],
        [
          'restore site_protection feature toggle',
          async () => {
            await page.goto(cacheBust(settingsPath));
            await page
              .locator('input.blueworx-feature-toggle[data-blueworx-feature="site_protection"]')
              .setChecked(featureWasChecked);
            await page.getByRole('button', { name: 'Save Changes' }).click();
            await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
          },
        ],
      ]);
    }
  });

  test('a draft post is not exempt from Site Protection via a query-var request to "/"', async ({
    page,
    browser,
  }) => {
    // WHY THIS TEST EXISTS: blueworx_public_is_owned_request_path() keyed the
    // Site Protection exemption on the request PATH only. Once activation
    // points show_on_front/page_on_front at the plugin's Home page, path "/"
    // is "owned" — but WordPress still honours query vars on "/", so
    // "/?p=<id>", "/?s=<term>" etc. have the exact same path as "/" and were
    // exempted right along with it, letting a logged-out request reach
    // WordPress's normal query handling instead of being stopped at the 403
    // wall. For a search results page that means the content actually
    // renders (200) to a logged-out visitor — the exact thing Site
    // Protection exists to prevent. A draft post is included too even though
    // WordPress's own core privacy rules already 404 it for anonymous
    // visitors regardless of this bug (a draft never renders, fixed or not)
    // — what the fix changes for a draft is WHERE that request is stopped:
    // unpatched, it bypasses Site Protection's wall and falls through to
    // WordPress's own 404; patched, Site Protection's 403 wall stops it
    // first, before WordPress's own query even runs. A clean "/" must stay
    // exempt (200) throughout.
    await login(page);

    // Create a real draft post via the block editor. The dashboard's Quick
    // Draft widget is hidden by default when the BlueWorx admin theme
    // feature is on (includes/admin-theme.php), so it is not a reliable
    // target here; Ctrl+S is used instead of clicking "Save draft" because
    // that button can sit under the BlueWorx admin topbar depending on
    // viewport/scroll state, which is unrelated to what this test guards.
    const draftTitle = `bw-critical1-draft-${Date.now()}`;
    await page.goto(cacheBust('/wp-admin/post-new.php'));

    const welcomeDialog = page.getByRole('dialog', { name: 'Welcome to the editor' });
    if (await welcomeDialog.count()) {
      await welcomeDialog.getByRole('button', { name: 'Close' }).click();
    }

    const titleField = page
      .locator('iframe[name="editor-canvas"]')
      .contentFrame()
      .getByRole('textbox', { name: 'Add title' });
    await expect(titleField).toBeVisible();
    await titleField.fill(draftTitle);

    await page.keyboard.press('Control+s');
    // The "Draft saved." snackbar is transient and can disappear before this
    // assertion runs; the URL swapping to post.php?post=<id> on save is the
    // durable signal that the auto-draft became a real draft.
    await page.waitForURL(/\/wp-admin\/post\.php\?post=\d+&action=edit/);

    const draftId = new URL(page.url()).searchParams.get('post');
    expect(draftId, 'the draft post ID must be extractable from the editor URL after saving').toBeTruthy();

    const settingsPath = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
    await page.goto(cacheBust(settingsPath));

    const siteProtectionToggle = page.locator(
      'input.blueworx-feature-toggle[data-blueworx-feature="site_protection"]'
    );
    const frontendToggle = page.locator('input[name="blueworx_frontend_protection_enabled"]');
    const featureWasChecked = await siteProtectionToggle.isChecked();
    const frontendWasChecked = await frontendToggle.isChecked();

    try {
      if (!featureWasChecked) {
        await siteProtectionToggle.setChecked(true);
      }
      await frontendToggle.setChecked(true);
      await page.getByRole('button', { name: 'Save Changes' }).click();
      await expect(page.locator('.notice-success').first()).toContainText('Settings saved');

      const loggedOutContext = await browser.newContext();
      const loggedOutPage = await loggedOutContext.newPage();

      const draftResponse = await loggedOutPage.goto(cacheBust(`/?p=${draftId}`));
      expect(
        draftResponse && draftResponse.status(),
        'a draft post reached via "/?p=<id>" must be blocked (403) by Site Protection, not leaked through the "/" exemption'
      ).toBe(403);

      // Also cover a query var that WordPress's own privacy rules do NOT
      // hide from anonymous visitors (unlike a draft, which 404s for a
      // logged-out request with or without this bug — WP core itself
      // refuses to serve draft content to a visitor who cannot read_post
      // it). A search results page renders normally for anyone, so this is
      // the assertion that catches actual leaked content, not just a
      // differing error code.
      const searchResponse = await loggedOutPage.goto(cacheBust('/?s=test'));
      expect(
        searchResponse && searchResponse.status(),
        'a search results page reached via "/?s=<term>" must be blocked (403) by Site Protection, not leaked through the "/" exemption'
      ).toBe(403);

      const homeResponse = await loggedOutPage.goto(cacheBust('/'));
      expect(
        homeResponse && homeResponse.status(),
        'a clean "/" request must remain exempt (200) from Site Protection'
      ).toBe(200);

      await loggedOutContext.close();
    } finally {
      // Restore the toggles and delete the draft independently — two
      // unrelated pieces of mutated state — so a throw restoring one can
      // never skip the other, matching restoreAll()'s use elsewhere in this
      // file.
      await restoreAll([
        [
          'restore Site Protection toggles',
          async () => {
            await page.goto(cacheBust(settingsPath));
            await page
              .locator('input[name="blueworx_frontend_protection_enabled"]')
              .setChecked(frontendWasChecked);
            await page
              .locator('input.blueworx-feature-toggle[data-blueworx-feature="site_protection"]')
              .setChecked(featureWasChecked);
            await page.getByRole('button', { name: 'Save Changes' }).click();
            await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
          },
        ],
        [
          'delete the draft post',
          async () => {
            await page.goto(cacheBust('/wp-admin/edit.php?post_status=draft&post_type=post'));
            const row = page.locator('#the-list tr', { hasText: draftTitle });
            await row.hover();
            const trashLink = row.locator('.row-actions .trash a');
            await expect(trashLink, 'the draft must expose a Trash row action').toHaveCount(1);
            // page.goto() the row action's own href directly rather than
            // clicking it — a clicked same-origin link navigation here has
            // been observed to hang Playwright's post-click "waiting for
            // scheduled navigations to finish" step in this environment,
            // whereas driving the exact same URL via goto() completes
            // immediately and performs the identical nonce-verified trash
            // action.
            const trashHref = await trashLink.getAttribute('href');
            expect(trashHref, 'the Trash row action must expose a nonced href').toBeTruthy();
            await page.goto(trashHref);
          },
        ],
      ]);
    }
  });

  test('"/" is not exempt from Site Protection when show_on_front is not a plugin-owned page', async ({
    page,
    browser,
  }) => {
    // WHY THIS TEST EXISTS: blueworx_public_is_owned_request_path() used to
    // treat "/" as an owned page unconditionally. "/" only counts as owned
    // when show_on_front is 'page' AND page_on_front is one of the plugin's
    // mapped page IDs (pages.php). Task 4 makes activation set exactly that
    // state for the Home page, so a fresh install never reaches the
    // "not owned" branch on its own any more — but Settings > Reading can
    // still be flipped back to "Your latest posts" by an admin (or another
    // plugin) at any time, and "/" must stop being exempt the moment that
    // happens, since it reverts to WordPress's own default posts index.
    await login(page);

    const readingPath = '/wp-admin/options-reading.php';
    await page.goto(cacheBust(readingPath));
    const originalPageOnFront = await page.locator('select[name="page_on_front"]').inputValue();

    const settingsPath = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

    const siteProtectionToggle = page.locator(
      'input.blueworx-feature-toggle[data-blueworx-feature="site_protection"]'
    );
    const frontendToggle = page.locator('input[name="blueworx_frontend_protection_enabled"]');

    let featureWasChecked;
    let frontendWasChecked;
    let frontChanged = false;

    try {
      // Move the site off "a static page" so "/" is WordPress's default
      // posts index again — the exact state this test guards.
      await page.locator('input[name="show_on_front"][value="posts"]').check();
      await page.getByRole('button', { name: 'Save Changes' }).click();
      frontChanged = true;

      await page.goto(cacheBust(settingsPath));
      featureWasChecked = await siteProtectionToggle.isChecked();
      frontendWasChecked = await frontendToggle.isChecked();

      if (!featureWasChecked) {
        await siteProtectionToggle.setChecked(true);
      }
      await frontendToggle.setChecked(true);
      await page.getByRole('button', { name: 'Save Changes' }).click();
      await expect(page.locator('.notice-success').first()).toContainText('Settings saved');

      const loggedOutContext = await browser.newContext();
      const loggedOutPage = await loggedOutContext.newPage();
      const response = await loggedOutPage.goto(cacheBust('/'));

      expect(
        response && response.status(),
        '"/" must still be gated (wp_die 403) by Site Protection when show_on_front is not a ' +
          'plugin-owned page'
      ).toBe(403);

      await loggedOutContext.close();
    } finally {
      // Restore the Site Protection toggles and show_on_front/page_on_front
      // independently — these are two unrelated pieces of mutated global
      // state, so a throw while restoring one (e.g. the .notice-success
      // assertion timing out) must never skip restoring the other. Any
      // errors are collected and re-thrown together so a genuine cleanup
      // failure still fails this test loudly instead of being swallowed.
      await restoreAll([
        [
          'restore Site Protection toggles',
          async () => {
            if (undefined !== featureWasChecked) {
              await page.goto(cacheBust(settingsPath));
              await page
                .locator('input[name="blueworx_frontend_protection_enabled"]')
                .setChecked(frontendWasChecked);
              await page
                .locator('input.blueworx-feature-toggle[data-blueworx-feature="site_protection"]')
                .setChecked(featureWasChecked);
              await page.getByRole('button', { name: 'Save Changes' }).click();
              await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
            }
          },
        ],
        [
          'restore show_on_front/page_on_front',
          async () => {
            if (frontChanged) {
              await page.goto(cacheBust(readingPath));
              await page.locator('input[name="show_on_front"][value="page"]').check();
              await page.locator('select[name="page_on_front"]').selectOption(originalPageOnFront);
              await page.getByRole('button', { name: 'Save Changes' }).click();
            }
          },
        ],
      ]);
    }
  });

  test('a renamed plugin-owned page stays exempt from Site Protection', async ({ page, browser }) => {
    // WHY THIS TEST EXISTS: blueworx_public_is_owned_request_path() used to
    // match the request path against the STATIC slugs from
    // blueworx_public_pages(), while blueworx_public_is_owned_page() (used at
    // query time, e.g. by template_include) resolves the page through the
    // stored blueworx_public_page_ids map instead. Renaming the Home page's
    // slug made the two disagree: the query-time check still recognised the
    // renamed page, but the path check — and therefore the Site Protection
    // exemption — did not, so a real visitor to the renamed page would get
    // wp_die()'d on a page the plugin still owns.
    //
    // Home is also WordPress's configured front page (Task 4), so its admin
    // "View" link — and get_permalink() itself — always read home_url('/')
    // regardless of its actual slug. That is WordPress's own front-page
    // special-casing, not this plugin, so the real current slug is read
    // straight out of Quick Edit instead, and the renamed page's URL is
    // built directly rather than off the (unhelpfully always-"/") View link.
    await login(page);

    await page.goto(cacheBust('/wp-admin/edit.php?post_type=page'));
    const homeRow = page.locator('#the-list tr', {
      has: page.locator('.row-title', { hasText: 'Home' }),
    });

    await homeRow.hover();
    await homeRow.locator('button.editinline').click();
    const originalSlugInput = page.locator('input[name="post_name"]:visible');
    await expect(originalSlugInput).toBeVisible();
    const originalSlug = await originalSlugInput.inputValue();
    expect(originalSlug, 'the Home page must have a slug').toBeTruthy();
    // Cancel out of Quick Edit without saving — this was only a read.
    await page.locator('.inline-edit-save button.cancel:visible').click();
    await expect(originalSlugInput).toBeHidden();
    const newSlug = `home-renamed-${Date.now()}`;

    const settingsPath = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
    await page.goto(cacheBust(settingsPath));

    const siteProtectionToggle = page.locator(
      'input.blueworx-feature-toggle[data-blueworx-feature="site_protection"]'
    );
    const frontendToggle = page.locator('input[name="blueworx_frontend_protection_enabled"]');
    const featureWasChecked = await siteProtectionToggle.isChecked();
    const frontendWasChecked = await frontendToggle.isChecked();

    let renamed = false;

    try {
      if (!featureWasChecked) {
        await siteProtectionToggle.setChecked(true);
      }
      await frontendToggle.setChecked(true);
      await page.getByRole('button', { name: 'Save Changes' }).click();
      await expect(page.locator('.notice-success').first()).toContainText('Settings saved');

      // Rename the Home page's slug via Quick Edit, the same way an admin
      // would from the Pages list.
      await page.goto(cacheBust('/wp-admin/edit.php?post_type=page'));
      await homeRow.hover();
      await homeRow.locator('button.editinline').click();

      // WordPress clones the dormant #inline-edit template into a new
      // #edit-<id> row on click and leaves the original template in the DOM
      // (hidden, values reset), so an unscoped `input[name="post_name"]`
      // matches both. `:visible` picks the live, populated clone.
      const slugInput = page.locator('input[name="post_name"]:visible');
      await expect(slugInput).toBeVisible();
      await slugInput.fill(newSlug);
      await page.locator('.inline-edit-save button.save:visible').click();
      await expect(slugInput).toBeHidden();
      renamed = true;

      const renamedUrl = `${baseURL}/${newSlug}/`;

      const loggedOutContext = await browser.newContext();
      const loggedOutPage = await loggedOutContext.newPage();
      const response = await loggedOutPage.goto(cacheBust(renamedUrl));

      // Site Protection gates on `init`, before WordPress's own
      // redirect_canonical() sends the front page's slug URL back to "/" —
      // an unexempted request 403s right here and never reaches that
      // redirect. A 200 (after following the redirect to "/", which must
      // also stay exempt) proves the exemption held throughout.
      expect(
        response && response.status(),
        'a renamed plugin-owned page must still be exempt (200, not wp_die 403) from Site Protection'
      ).toBe(200);

      await loggedOutContext.close();
    } finally {
      // Restore the Home page's slug and the Site Protection toggles
      // independently — two unrelated pieces of mutated state — so a throw
      // in either step can never skip the other. Any errors are collected
      // and re-thrown together so a genuine cleanup failure still fails
      // this test loudly instead of being swallowed.
      await restoreAll([
        [
          'restore Home page slug',
          async () => {
            if (renamed) {
              await page.goto(cacheBust('/wp-admin/edit.php?post_type=page'));
              await homeRow.hover();
              await homeRow.locator('button.editinline').click();
              const slugInput = page.locator('input[name="post_name"]:visible');
              await expect(slugInput).toBeVisible();
              await slugInput.fill(originalSlug);
              await page.locator('.inline-edit-save button.save:visible').click();
              await expect(slugInput).toBeHidden();
            }
          },
        ],
        [
          'restore Site Protection toggles',
          async () => {
            await page.goto(cacheBust(settingsPath));
            await page
              .locator('input[name="blueworx_frontend_protection_enabled"]')
              .setChecked(frontendWasChecked);
            await page
              .locator('input.blueworx-feature-toggle[data-blueworx-feature="site_protection"]')
              .setChecked(featureWasChecked);
            await page.getByRole('button', { name: 'Save Changes' }).click();
            await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
          },
        ],
      ]);
    }
  });

  test('a page whose slug collides with a freed owned slug renders its own content, not the plugin template', async ({
    page,
    browser,
  }) => {
    // WHY THIS TEST EXISTS: this is the real end-to-end version of the defect
    // covered hermetically below by "Public page ownership resolution"
    // (a page reusing a slug freed by a rename is not hijacked). That harness
    // calls blueworx_public_current_template() directly because, until
    // template_include was wired (this task), its return value had no
    // browser-observable effect. Now it does, so this reproduces the actual
    // scenario end-to-end: rename Home away from "home" (the map keeps
    // pointing 'home' at Home's ID — renaming never touches the map), hand
    // the freed "home" slug to an unrelated page (WordPress's default Sample
    // Page), and confirm that unrelated page renders its OWN theme output —
    // not the plugin's .bw-page template — even though its slug now matches
    // a key in blueworx_public_page_ids.
    await login(page);

    await page.goto(cacheBust('/wp-admin/edit.php?post_type=page'));
    const homeRow = page.locator('#the-list tr', {
      has: page.locator('.row-title', { hasText: 'Home' }),
    });
    const sampleRow = page.locator('#the-list tr', {
      has: page.locator('.row-title', { hasText: 'Sample Page' }),
    });

    const originalHomeUrl = await homeRow.locator('.row-actions .view a').getAttribute('href');
    expect(originalHomeUrl, 'the Home page must expose a public URL').toBeTruthy();
    const homeSlug = new URL(originalHomeUrl).pathname.replace(/^\/+|\/+$/g, '');
    const tempHomeSlug = `home-collision-${Date.now()}`;

    const originalSampleUrl = await sampleRow.locator('.row-actions .view a').getAttribute('href');
    expect(
      originalSampleUrl,
      'a default "Sample Page" must exist on a fresh install to reuse the freed slug'
    ).toBeTruthy();
    const sampleSlug = new URL(originalSampleUrl).pathname.replace(/^\/+|\/+$/g, '');

    let homeRenamed = false;
    let sampleRenamed = false;

    try {
      // Free the "home" slug: rename Home away from it. The map still maps
      // 'home' => Home's ID afterwards — renaming never updates the map.
      await homeRow.hover();
      await homeRow.locator('button.editinline').click();
      let slugInput = page.locator('input[name="post_name"]:visible');
      await expect(slugInput).toBeVisible();
      await slugInput.fill(tempHomeSlug);
      await page.locator('.inline-edit-save button.save:visible').click();
      await expect(slugInput).toBeHidden();
      homeRenamed = true;

      // Hand the now-free "home" slug to a completely unrelated page.
      await sampleRow.hover();
      await sampleRow.locator('button.editinline').click();
      slugInput = page.locator('input[name="post_name"]:visible');
      await expect(slugInput).toBeVisible();
      await slugInput.fill(homeSlug);
      await page.locator('.inline-edit-save button.save:visible').click();
      await expect(slugInput).toBeHidden();
      sampleRenamed = true;

      const collidedUrl = await sampleRow.locator('.row-actions .view a').getAttribute('href');
      expect(collidedUrl, 'the unrelated page must now expose the freed slug').toContain(homeSlug);

      const loggedOutContext = await browser.newContext();
      const loggedOutPage = await loggedOutContext.newPage();
      const response = await loggedOutPage.goto(cacheBust(collidedUrl));

      expect(response && response.status(), 'the colliding page must still load').toBe(200);
      await expect(
        loggedOutPage.locator('.bw-page'),
        'a page whose slug collides with a freed owned slug must render its own (theme) content — not be hijacked by the plugin template'
      ).toHaveCount(0);

      await loggedOutContext.close();
    } finally {
      // Restore both slugs independently — a throw while restoring the
      // Sample page's slug must never skip restoring Home's, which would
      // otherwise leave Home on a temporary slug with the collision still
      // in place. Any errors are collected and re-thrown together so a
      // genuine cleanup failure still fails this test loudly instead of
      // being swallowed.
      await restoreAll([
        [
          'restore Sample Page slug',
          async () => {
            if (sampleRenamed) {
              await sampleRow.hover();
              await sampleRow.locator('button.editinline').click();
              const slugInput = page.locator('input[name="post_name"]:visible');
              await expect(slugInput).toBeVisible();
              await slugInput.fill(sampleSlug);
              await page.locator('.inline-edit-save button.save:visible').click();
              await expect(slugInput).toBeHidden();
            }
          },
        ],
        [
          'restore Home page slug',
          async () => {
            if (homeRenamed) {
              await homeRow.hover();
              await homeRow.locator('button.editinline').click();
              const slugInput = page.locator('input[name="post_name"]:visible');
              await expect(slugInput).toBeVisible();
              await slugInput.fill(homeSlug);
              await page.locator('.inline-edit-save button.save:visible').click();
              await expect(slugInput).toBeHidden();
            }
          },
        ],
      ]);
    }
  });
});

const PAGES_PHP = fileURLToPath(new URL('../includes/public/pages.php', import.meta.url));

/**
 * Directly invokes the real blueworx_public_current_template() against a
 * synthetic blueworx_public_page_ids map and queried post, entirely outside
 * WordPress.
 *
 * WHY HERMETIC RATHER THAN BROWSER-DRIVEN: the slug-collision bug this guards
 * against lives entirely inside blueworx_public_current_template()'s
 * ownership-resolution logic, but that function's return value has no
 * browser-observable effect yet. `template_include` is not hooked to it until
 * Task 4, and the one consumer that exists today
 * (blueworx_enqueue_public_assets(), via blueworx_public_is_owned_page())
 * is itself gated on file_exists() for templates/pages/home.php — which does
 * not exist yet either (also Task 4). So right now EVERY call returns null
 * regardless of this bug, and a real "rename via Quick Edit, create a
 * colliding page, load it in a browser" repro would pass identically whether
 * the bug is present or fixed — a vacuous test. This harness instead
 * `require`s the actual production file with the handful of WordPress
 * functions it touches stubbed (get_option, is_page, get_queried_object,
 * WP_Post, …) and a throwaway template file so file_exists() succeeds, then
 * calls the real function with a fixture matching the exact defect scenario.
 *
 * TASK 4 HAS NOW LANDED (templates/pages/home.php + the template_include
 * hook), so the return value of blueworx_public_current_template() is
 * browser-observable and the real end-to-end version of this scenario exists
 * above: "a page whose slug collides with a freed owned slug renders its own
 * content, not the plugin template". This hermetic harness is kept alongside
 * it deliberately — it is far cheaper to run (no browser, no WordPress) and
 * pins the exact defect inside the resolution function itself, while the
 * browser test pins the end-to-end behaviour a real visitor sees.
 *
 * @param {object} fixture
 * @param {Record<string, number>} fixture.map  The blueworx_public_page_ids option value.
 * @param {number} fixture.postId               The queried post's ID.
 * @param {string} fixture.postName              The queried post's current slug.
 * @return {string} 'NULL' when the function returned null, otherwise the resolved template path.
 */
function resolveOwnedTemplate({ map, postId, postName }) {
  const workDir = mkdtempSync(join(tmpdir(), 'bw-pages-test-'));

  try {
    const templateDir = join(workDir, 'templates', 'pages');
    mkdirSync(templateDir, { recursive: true });
    writeFileSync(join(templateDir, 'home.php'), '<?php // test stub, never executed\n');

    // Forward slashes: safe in a PHP path on every platform and avoids
    // Windows backslashes breaking out of the single-quoted PHP literals below.
    const bwPath = `${workDir.replace(/\\/g, '/')}/`;
    const pagesPhp = PAGES_PHP.replace(/\\/g, '/');

    const script = `<?php
define( 'ABSPATH', '${bwPath}' );
define( 'BLUEWORX_LABS_PATH', '${bwPath}' );

class WP_Post {
	public $ID;
	public $post_name;
	public function __construct( $id, $post_name ) {
		$this->ID        = $id;
		$this->post_name = $post_name;
	}
}

function __( $text, $domain = 'default' ) { return $text; }
function apply_filters( $tag, $value ) { return $value; }
function add_filter( $tag, $callback, $priority = 10 ) { /* no-op */ }
function get_option( $name, $default = false ) {
	if ( 'blueworx_public_page_ids' === $name ) {
		return json_decode( '${JSON.stringify(map)}', true );
	}
	return $default;
}
function is_admin() { return false; }
function is_page() { return true; }
function get_queried_object() { return new WP_Post( ${postId}, '${postName}' ); }

require '${pagesPhp}';

$result = blueworx_public_current_template();
echo null === $result ? 'NULL' : $result;
`;

    const scriptPath = join(workDir, 'harness.php');
    writeFileSync(scriptPath, script);

    return execFileSync('php', [scriptPath], { encoding: 'utf8' }).trim();
  } finally {
    rmSync(workDir, { recursive: true, force: true });
  }
}

// Deliberately outside the "Public site" describe above: it needs neither a
// browser nor a live WordPress target (see resolveOwnedTemplate()'s doc
// comment for why), so it must never be skipped by the isPlaceholder /
// ADMIN_USER / ADMIN_PASS gate that guards the rest of this file.
test.describe('Public page ownership resolution (blueworx_public_current_template)', () => {
  test('a page reusing a slug freed by a rename is not hijacked', () => {
    // THE DEFECT: admin renames the Home page's slug away from "home" — the
    // map still points its "home" key at that page's ID (111). Later, an
    // unrelated page is created and takes the now-free slug "home" (ID 222).
    // 222 is not in the map, so array_search() fails; the buggy fallback then
    // matched purely on post_name against the static registry, hijacking a
    // page the plugin does not own.
    const result = resolveOwnedTemplate({ map: { home: 111 }, postId: 222, postName: 'home' });

    expect(
      result,
      'a page whose slug collides with a DIFFERENT mapped ID must not resolve to the plugin template'
    ).toBe('NULL');
  });

  test('a fresh install with no map entry still resolves by slug', () => {
    // Guards the fallback's actual purpose: before blueworx_public_page_ids
    // is ever written (fresh activation), a page must still be found by slug.
    const result = resolveOwnedTemplate({ map: {}, postId: 333, postName: 'home' });

    expect(result, 'an unmapped slug must still fall back to the static registry').not.toBe('NULL');
  });

  test('a renamed page is still recognised by its mapped ID', () => {
    // Guards the other direction: the page the map actually points at must
    // keep resolving after a rename, however its slug reads now.
    const result = resolveOwnedTemplate({ map: { home: 111 }, postId: 111, postName: 'home-renamed-xyz' });

    expect(result, 'the mapped ID must still resolve regardless of its current slug').not.toBe('NULL');
  });
});

const HELPERS_PUBLIC_PHP = fileURLToPath(new URL('../includes/public/helpers-public.php', import.meta.url));

/**
 * Directly `require`s the real includes/public/helpers-public.php with the
 * handful of WordPress escaping functions it calls stubbed out, then runs a
 * snippet of PHP against it and returns stdout.
 *
 * WHY HERMETIC RATHER THAN BROWSER-DRIVEN: nothing in this task's scope
 * (helpers-public.php + templates/parts/footer.php) calls blueworx_icon()
 * from a page that actually renders — CtaBand.tsx/Footer.tsx, the two
 * components this task ports, never use the Icon component in the source
 * either (their social/subscribe glyphs are bespoke inline <svg>, not from
 * the ICONS map). So there is currently no browser-observable page with a
 * `[data-ic]` element, the same "not wired to anything visible yet" shape
 * `resolveOwnedTemplate()` above documents for a different function. This
 * harness instead exercises the real function directly, exactly like that
 * one does, to pin the one behaviour that matters most here: the span
 * carries data-ic and the svg fills it at 100% — get that backwards and
 * icon sizing collapses sitewide the moment a later task calls
 * blueworx_icon() from a real page.
 *
 * @param {string} php PHP statements to run after helpers-public.php loads.
 * @return {string} Captured stdout, trimmed.
 */
function runHelpersPublicPhp(php) {
  const workDir = mkdtempSync(join(tmpdir(), 'bw-helpers-public-test-'));

  try {
    const bwPath = `${workDir.replace(/\\/g, '/')}/`;
    const helpersPath = HELPERS_PUBLIC_PHP.replace(/\\/g, '/');

    const script = `<?php
define( 'ABSPATH', '${bwPath}' );

// Minimal stand-ins for the WordPress escaping functions helpers-public.php
// calls — real WordPress is not loaded in this harness, so these mirror just
// enough of core's behaviour (attribute-escape, pass geometry through) to
// exercise blueworx_icon()/blueworx_blob() in isolation.
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function wp_kses( $string, $allowed_html ) { return $string; }

require '${helpersPath}';

${php}
`;

    const scriptPath = join(workDir, 'harness.php');
    writeFileSync(scriptPath, script);

    return execFileSync('php', [scriptPath], { encoding: 'utf8' });
  } finally {
    rmSync(workDir, { recursive: true, force: true });
  }
}

// Deliberately outside the "Public site" describe above: it needs neither a
// browser nor a live WordPress target, so it must never be skipped by the
// isPlaceholder / ADMIN_USER / ADMIN_PASS gate that guards the rest of this
// file.
test.describe('Shared public helpers (blueworx_icon, blueworx_blob)', () => {
  test('icons render with the data-ic sizing hook', () => {
    const html = runHelpersPublicPhp("blueworx_icon( 'chat' );");

    const spanOpen = html.indexOf('<span data-ic="chat"');
    const svgOpen = html.indexOf('<svg', spanOpen);
    const svgClose = html.indexOf('</svg>', svgOpen);
    const spanClose = html.indexOf('</span>', svgClose);

    expect(spanOpen, 'the span must carry data-ic — CSS sizes the span, not the svg').toBeGreaterThanOrEqual(0);
    expect(svgOpen, 'the svg must be nested inside the data-ic span').toBeGreaterThan(spanOpen);
    expect(svgClose, 'the svg must close').toBeGreaterThan(svgOpen);
    expect(spanClose, 'the span must close after the svg').toBeGreaterThan(svgClose);

    const svgTag = html.slice(svgOpen, html.indexOf('>', svgOpen) + 1);
    expect(
      svgTag,
      'the svg must be 100% of its span or icon sizing collapses'
    ).toMatch(/style="[^"]*width:100%;height:100%[^"]*"/);
  });

  test('an unknown icon name renders nothing', () => {
    const html = runHelpersPublicPhp("blueworx_icon( 'does-not-exist' );");
    expect(html.trim()).toBe('');
  });

  test('an optional class and style are applied to the wrapping span, not the svg', () => {
    const html = runHelpersPublicPhp("blueworx_icon( 'zap', 'pt-nav-item-ic', 'color:red' );");
    const spanOpen = html.slice(0, html.indexOf('>') + 1);
    expect(spanOpen).toContain('class="pt-nav-item-ic"');
    expect(spanOpen).toContain('style="color:red"');
  });

  test('blueworx_blob renders a bare, style-only decorative div', () => {
    const html = runHelpersPublicPhp("blueworx_blob( 'width:220px;height:220px' );");
    expect(html.trim()).toBe('<div class="blob" style="width:220px;height:220px"></div>');
  });

  test('blueworx_blob with no style renders a plain .blob div', () => {
    const html = runHelpersPublicPhp('blueworx_blob();');
    expect(html.trim()).toBe('<div class="blob"></div>');
  });
});

// This suite is deliberately outside the "Public site" describe above: it
// reads a static file off disk and needs neither a browser nor a live
// WordPress target, so it must never be skipped by the isPlaceholder /
// ADMIN_USER / ADMIN_PASS gate that guards the rest of this file.
test.describe('Public stylesheet scoping', () => {
  test('assets/css/public.css has no unscoped bare element selectors', () => {
    // WHY THIS TEST EXISTS: public.css does not own the document it ships
    // into — it is enqueued into a normal WordPress page, alongside the
    // admin bar and the active theme's own markup. It was ported from a
    // standalone front-end where it *did* own the whole page, and was
    // scoped to `.bw-page` for its `*` reset and `body` rule — but plain
    // element selectors (`img`, `button`, `nav`, `footer`, ...) were missed.
    // A bare `nav { ... }` does not just style the plugin's own nav; it
    // silently restyles the admin bar and every `<nav>` the active theme
    // renders (the worst instance found here gave a theme's own nav a full
    // 96px sticky reskin). A later miss (h3/h4/p hiding inside mixed
    // selector lists like `.h1, .h2, h3, h4`) showed that checking whether
    // the WHOLE rule is unscoped isn't enough — a single bare part in an
    // otherwise-scoped list still reaches every matching element in the
    // document. This test fails the build the moment a future edit
    // reintroduces either shape, instead of waiting for someone to notice a
    // corrupted admin bar or theme in production.
    // Resolved relative to this file (not process.cwd()) so the test works
    // regardless of which directory `playwright test` is invoked from.
    const css = readFileSync(new URL('../assets/css/public.css', import.meta.url), 'utf8')
      // Strip comments first so anything mentioned in prose doesn't confuse
      // the selector scan below.
      .replace(/\/\*[\s\S]*?\*\//g, '');

    const violations = [];

    // Walk every "<selector>{" occurrence in the file, including ones
    // nested inside @media blocks (the responsive `nav { ... }` variant of
    // this bug lived inside one). @keyframes/@media preludes are skipped
    // wholesale because they start with "@"; a stray custom-property line
    // is skipped defensively in case one is ever captured as a "prelude".
    const rulePattern = /([^{}]+)\{/g;
    let match;
    while ((match = rulePattern.exec(css)) !== null) {
      const prelude = match[1].trim();
      if (!prelude || prelude.startsWith('@') || prelude.startsWith('--')) {
        continue;
      }

      // Inspect every comma-separated part of the selector list on its own
      // — not just whether the list as a whole is unscoped — so a bare
      // element hiding among scoped classes (e.g. `.h1, .h2, h3, h4`) can't
      // slip past by riding along with `.h1`/`.h2`.
      const branches = prelude.split(',').map((branch) => branch.trim());
      for (const branch of branches) {
        if (!branch || isKeyframeStop(branch)) {
          continue;
        }
        // A branch is scoped the moment it carries a class, id, or pseudo
        // qualifier anywhere in it — `.bw-page nav`, `a.btn`, `:root`, and
        // `.h1` are all fine. A branch with none of those characters is a
        // bare element selector with zero scoping.
        const isScoped = /[.#:]/.test(branch);
        if (!isScoped) {
          violations.push(`${prelude}  (bare part: "${branch}")`);
        }
      }
    }

    expect(
      violations,
      `Found unscoped bare element selector(s) in public.css: ${violations.join(', ')}. ` +
        'Scope them under .bw-page — this stylesheet ships into a WordPress ' +
        'document it does not own, and a bare element selector silently ' +
        "restyles the admin bar and the active theme."
    ).toEqual([]);
  });
});
