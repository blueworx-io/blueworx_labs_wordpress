// Public-site specs are logged out by definition, so every navigation goes
// through cacheBust() — Cloudways Varnish caches logged-out responses and a
// stale hit makes a passing build look broken (see tests/helpers.js:88-105).
import { readFileSync } from 'node:fs';
import { expect } from '@playwright/test';
import { test, isPlaceholder, cacheBust, login, ADMIN_USER, ADMIN_PASS } from './helpers.js';

// A keyframe step ("0%", "100%", "from", "to") is the one kind of bare-looking
// prelude this file legitimately needs — it is never a document element and
// never needs .bw-page scoping.
const isKeyframeStop = (branch) => branch === 'from' || branch === 'to' || /^\d+(\.\d+)?%$/.test(branch);

test.describe('Public site', () => {
  test.skip(isPlaceholder || !ADMIN_USER || !ADMIN_PASS, 'No real WordPress target configured.');

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
      // Restore both toggles even if an assertion above failed, so this test
      // never leaves the site inaccessible for the rest of the suite or a
      // real visitor.
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
  });

  test('"/" is not exempt from Site Protection while show_on_front is still the default', async ({
    page,
    browser,
  }) => {
    // WHY THIS TEST EXISTS: blueworx_public_is_owned_request_path() used to
    // treat "/" as an owned page unconditionally. "/" only becomes an owned
    // page once WordPress's front page is actually pointed at one of the
    // plugin's pages (Task 4, not done yet) — until then it is WordPress's
    // own default posts index, not a page this plugin renders. Exempting it
    // from Site Protection would weaken the gate at the site root, the one
    // path every anonymous visitor hits first.
    await login(page);

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
      const response = await loggedOutPage.goto(cacheBust('/'));

      expect(
        response && response.status(),
        '"/" must still be gated (wp_die 403) by Site Protection — it is the default posts ' +
          'index, not yet a plugin-owned page'
      ).toBe(403);

      await loggedOutContext.close();
    } finally {
      // Restore both toggles even if an assertion above failed, so this test
      // never leaves the site inaccessible for the rest of the suite or a
      // real visitor.
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
    await login(page);

    await page.goto(cacheBust('/wp-admin/edit.php?post_type=page'));
    const homeRow = page.locator('#the-list tr', {
      has: page.locator('.row-title', { hasText: 'Home' }),
    });

    const originalUrl = await homeRow.locator('.row-actions .view a').getAttribute('href');
    expect(originalUrl, 'the Home page must expose a public URL').toBeTruthy();
    const originalSlug = new URL(originalUrl).pathname.replace(/^\/+|\/+$/g, '');
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

      const renamedUrl = await homeRow.locator('.row-actions .view a').getAttribute('href');
      expect(renamedUrl, 'the renamed page must still expose a public URL').toContain(newSlug);

      const loggedOutContext = await browser.newContext();
      const loggedOutPage = await loggedOutContext.newPage();
      const response = await loggedOutPage.goto(cacheBust(renamedUrl));

      expect(
        response && response.status(),
        'a renamed plugin-owned page must still be exempt (200, not wp_die 403) from Site Protection'
      ).toBe(200);

      await loggedOutContext.close();
    } finally {
      // Rename the slug back first so the site is left exactly as found,
      // then restore both Site Protection toggles — in that order, even if
      // an assertion above failed.
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
