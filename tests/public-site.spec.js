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
