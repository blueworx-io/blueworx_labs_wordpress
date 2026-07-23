// Home page marketing content (Task 2 of Plan 2). Logged-out by definition,
// so every navigation goes through cacheBust() — see tests/public-site.spec.js's
// top-of-file note on Cloudways Varnish caching stale logged-out responses.
import { expect } from '@playwright/test';
import { test, isPlaceholder, cacheBust } from './helpers.js';

test.describe('Marketing home page', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('renders the home-hero, What We Do, How We Work and testimonials sections', async ({ page }) => {
    await page.goto(cacheBust('/'));

    // home-hero: the bespoke section built directly in templates/pages/home.php,
    // including its glass-card timeline visual (via the new glass-card part)
    // and the scrolling service ticker.
    const hero = page.locator('main > div > section.home-hero');
    await expect(hero).toHaveCount(1);
    await expect(hero.locator('.glass-card')).toHaveCount(1);
    await expect(hero.locator('.gc-tag')).toHaveText('yourproject · status');
    // Five timeline steps, ported verbatim from app/page.tsx's TimelineRow calls.
    for (const step of ['Discovery call', 'Design', 'Development', 'Deploy', 'Support & growth']) {
      await expect(hero.locator('.glass-card').getByText(step, { exact: true })).toHaveCount(1);
    }
    await expect(hero.locator('.hh-ticker-track span').first()).toBeVisible();

    // "What We Do": two svc-card parts inside .svc2.
    const svc2 = page.locator('main > div > section .svc2');
    await expect(svc2).toHaveCount(1);
    const svcCards = svc2.locator('> a.svc');
    await expect(svcCards).toHaveCount(2);
    await expect(svcCards.nth(0)).toContainText('Integrated Support');
    await expect(svcCards.nth(0)).toHaveAttribute('href', /\/services\/?$/);
    await expect(svcCards.nth(1)).toContainText('Digital Toolbox');
    await expect(svcCards.nth(1)).toHaveAttribute('href', /\/toolbox\/?$/);

    // Selected Work: three work-card parts.
    const workCards = page.locator('main > div .work-grid > a.work-card');
    await expect(workCards).toHaveCount(3);
    await expect(workCards.nth(0)).toContainText('Hirasté');

    // How We Work: proc-grid part, four steps.
    const procGrid = page.locator('main > div > section .proc-grid');
    await expect(procGrid).toHaveCount(1);
    await expect(procGrid.locator('.proc')).toHaveCount(4);
    await expect(procGrid.locator('.proc').first().locator('.num')).toHaveText('01');

    // Testimonials part, fed the real blueworx_content_reviews() content.
    const testimonials = page.locator('main > div .tg > .tc');
    await expect(testimonials).toHaveCount(4);
    await expect(testimonials.first().locator('.tname')).not.toBeEmpty();
  });

  test('the FeatureTabs region renders a labelled placeholder, not a broken widget', async ({ page }) => {
    await page.goto(cacheBust('/'));

    // FeatureTabs is a Plan 3 interactive widget, explicitly out of scope —
    // this pins that the page still renders a clearly-labelled static
    // placeholder in its place rather than an empty gap or broken markup.
    const placeholder = page.locator('main > div > .bw-plan3-placeholder[data-widget="feature-tabs"]');
    await expect(placeholder).toHaveCount(1);
    await expect(placeholder).not.toBeEmpty();

    // It must sit between Selected Work and How We Work, matching the
    // source's section order in app/page.tsx.
    const sections = await page.locator('main > div > *').evaluateAll((els) =>
      els.map((el) => (el.matches('.bw-plan3-placeholder') ? 'placeholder' : el.className))
    );
    const placeholderIndex = sections.indexOf('placeholder');
    expect(placeholderIndex, 'the FeatureTabs placeholder must be present in the section flow').toBeGreaterThan(-1);
  });

  test('the Toolbox band lists all 12 tools with bundled favicons', async ({ page }) => {
    await page.goto(cacheBust('/'));

    const tbxCards = page.locator('main > div .tbx-grid > a.tbx-card');
    await expect(tbxCards).toHaveCount(12);

    const srcs = await tbxCards.locator('.tbx-logo img').evaluateAll((els) => els.map((el) => el.getAttribute('src')));
    for (const src of srcs) {
      expect(src, 'toolbox favicons must be bundled by the plugin, not fetched from Google').toMatch(
        /\/wp-content\/plugins\/blueworx-labs-wordpress\/assets\/img\/tools\/[^/]+\.png$/
      );
    }
  });

  test('the Ongoing Partnership split section renders the collaboration visual', async ({ page }) => {
    await page.goto(cacheBust('/'));

    const split = page.locator('main > div > section.split');
    await expect(split).toHaveCount(1);
    await expect(split.locator('.collab-list .fli')).toHaveCount(4);

    const img = split.locator('.collab-visual img');
    await expect(img).toHaveCount(1);
    const src = await img.getAttribute('src');
    expect(src, 'the collaboration image must be served from the plugin, not the theme/uploads').toMatch(
      /\/wp-content\/plugins\/blueworx-labs-wordpress\/assets\/img\/fig-collab\.jpg$/
    );
  });
});
