/**
 * The shared admin design system: shipped verbatim, loaded where it belongs.
 *
 * Two things worth holding still. The stylesheet must actually reach the four
 * BlueWorx screens — a design system nobody loads is just a file — and it must
 * NOT reach WordPress's own screens, where its component styles would restyle
 * furniture this plugin does not own. The second half is the one a refactor
 * breaks silently: everything still looks right on our screens while the Posts
 * list quietly changes shape.
 */

import { test, expect, login } from './helpers.js';

const DESIGN_CSS = 'assets/blueworx-admin-design.css';

/** Hrefs of every stylesheet the page has loaded. */
async function styleHrefs(page) {
  return page.$$eval('link[rel="stylesheet"]', (links) => links.map((l) => l.href));
}

test.describe('BlueWorx admin design system', () => {
  test('loads on the four BlueWorx screens', async ({ page }) => {
    await login(page);

    const screens = [
      ['Enhancements', '/wp-admin/admin.php?page=blueworx-labs-wordpress'],
      ['Guides', '/wp-admin/admin.php?page=blueworx-guides'],
      ['Edit Menu', '/wp-admin/admin.php?page=blueworx-edit-menu'],
      ['Cache', '/wp-admin/admin.php?page=blueworx-cache'],
    ];

    for (const [name, path] of screens) {
      await page.goto(path);
      const hrefs = await styleHrefs(page);
      expect(hrefs.some((h) => h.includes(DESIGN_CSS)), `${name} should load the design system`).toBe(true);
    }
  });

  test('the brand faces are declared once, and resolve', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-cache');

    // The separate fonts stylesheet is gone: this file is the only declaration
    // of Sora and Inter left, so a duplicate reappearing is a regression.
    const hrefs = await styleHrefs(page);
    expect(hrefs.some((h) => h.includes('blueworx-fonts.css'))).toBe(false);

    // styles.css asks for fonts/… relative to itself. If the stylesheet and the
    // font files ever part company, the brand type falls back silently rather
    // than erroring — so check the file is really there.
    const cssUrl = hrefs.find((h) => h.includes(DESIGN_CSS));
    const fontUrl = new URL('fonts/sora-600.woff2', cssUrl).href;
    const font = await page.request.get(fontUrl);
    expect(font.status(), 'Sora 600 should be served beside the stylesheet').toBe(200);
  });

  test('styles nothing on WordPress own screens', async ({ page }) => {
    // The stylesheet is present on core screens, because the admin re-skin gets
    // its Sora and Inter faces from it. That is safe only because every rule in
    // it is class-scoped: there is no bare `body`, `h1` or `input` selector, so
    // a screen that uses none of the classes is untouched by it.
    //
    // This is the check that keeps that true. Loading the file is fine; putting
    // its component classes on core's own markup is not, and that is what would
    // silently restyle screens this plugin does not own.
    await login(page);

    const COMPONENTS = [
      '.bw-card',
      '.bw-btn',
      '.bw-field',
      '.bw-notice',
      '.bw-formrow',
      '.bw-pagehead',
      '.bw-tabs',
      '.bw-switch',
    ];

    for (const path of ['/wp-admin/edit.php', '/wp-admin/options-general.php']) {
      await page.goto(path);

      for (const selector of COMPONENTS) {
        // Anything inside our own `.bw-admin` wrapper is ours to style; this is
        // about design system classes loose on core's markup.
        const stray = page.locator(`${selector}:not(.bw-admin ${selector})`);
        expect(await stray.count(), `${path} should carry no ${selector}`).toBe(0);
      }
    }
  });

  test('the admin re-skin still has its brand type', async ({ page }) => {
    // The re-skin used to pull in its own fonts stylesheet. It now leans on the
    // design system for the same faces, so this is the check that deleting that
    // stylesheet did not leave the rest of wp-admin without Sora.
    await login(page);
    await page.goto('/wp-admin/index.php');

    const declared = await page.evaluate(() => {
      const faces = [];
      for (const sheet of document.styleSheets) {
        let rules;
        try {
          rules = sheet.cssRules;
        } catch {
          continue;
        }
        for (const rule of rules) {
          if (rule.constructor.name === 'CSSFontFaceRule') faces.push(rule.style.fontFamily.replace(/"/g, ''));
        }
      }
      return faces;
    });

    expect(declared, 'Sora should still be declared on a plain admin screen').toContain('Sora');
    expect(declared).toContain('Inter');
  });
});
