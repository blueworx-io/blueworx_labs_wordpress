// Public marketing pages — logged out — so every navigation goes through
// cacheBust() (Varnish caches logged-out responses; a stale hit reads as a
// broken build). See tests/helpers.js. Covers the 12 nested `/toolbox/<slug>`
// tool-detail pages added by Task 9b: routing, the single-tool.php template,
// a bad slug 404ing, and the Site Protection exemption reaching the new
// nested pages.
import { expect } from '@playwright/test';
import { test, isPlaceholder, cacheBust, cacheBustExempt, login, ADMIN_USER, ADMIN_PASS, restoreAll } from './helpers.js';

// SureCart is the one tool with `popular => true` in
// includes/public/content.php — its 6 features, checked both as hero
// check-rows and as `.svc` "why" cards.
const SURECART_FEATURES = [
  'Optimised checkout',
  'Subscriptions built in',
  'PCI-compliant payments',
  'One-page upsells',
  'Digital delivery',
  'Revenue analytics',
];


test.describe('Marketing tool-detail pages (/toolbox/<slug>)', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('the toolbox archive still renders a grid of 12 tool cards', async ({ page }) => {
    await page.goto(cacheBust('/toolbox/'));
    await expect(page.locator('.tbx .tbx-card')).toHaveCount(12);
  });

  test('/toolbox/surecart renders name, tagline, the Popular pill, and its 6 features twice', async ({
    page,
  }) => {
    const response = await page.goto(cacheBust('/toolbox/surecart/'));
    expect(response && response.status()).toBe(200);

    await expect(page.locator('h1.h1')).toHaveText('SureCart');
    await expect(
      page.locator('.lead', {
        hasText: 'A modern checkout and subscription engine for selling products, services, and digital downloads.',
      })
    ).toHaveCount(1);

    // Exactly one Popular pill in the hero glass-card — SureCart is the only
    // tool with `popular => true`. Scoped to `.glass-card`, not the whole
    // page: nav.php's mega panel also carries a "Popular" tag next to
    // SureCart's entry, on every page, regardless of which tool is showing.
    await expect(page.locator('.glass-card').getByText('Popular', { exact: true })).toHaveCount(1);

    // Each of the 6 features appears twice: once as a hero check-row, once as
    // a `.svc` "why it's in the Toolbox" card.
    for (const title of SURECART_FEATURES) {
      await expect(
        page.getByText(title, { exact: true }),
        `"${title}" must appear twice (hero check-row + .svc card)`
      ).toHaveCount(2);
    }
  });

  test('a non-popular tool (/toolbox/elementor) renders with no Popular pill', async ({ page }) => {
    const response = await page.goto(cacheBust('/toolbox/elementor/'));
    expect(response && response.status()).toBe(200);

    await expect(page.locator('h1.h1')).toHaveText('Elementor');
    // Scoped to `.glass-card` — see the SureCart test above for why the
    // unscoped locator would false-fail (nav.php's mega panel always tags
    // SureCart "Popular", regardless of which tool page is showing).
    await expect(page.locator('.glass-card').getByText('Popular', { exact: true })).toHaveCount(0);
  });

  test('an unknown tool slug 404s', async ({ page }) => {
    const response = await page.goto(cacheBust('/toolbox/nope/'));
    expect(response && response.status()).toBe(404);
  });

  test('a nested tool page stays reachable while logged out with Site Protection on', async ({
    page,
    browser,
  }) => {
    // WHY THIS TEST EXISTS: the Site Protection exemption
    // (blueworx_public_is_owned_request_path(), includes/public/pages.php) was
    // rewritten by this task to build its allowlist bases from get_page_uri()
    // (or the registry's full-path key as a pre-activation fallback) instead
    // of the bare post_name a nested page's stored slug returns — the exact
    // bug that would silently drop this exemption for every tool page. See
    // "a plugin-owned public page stays reachable..." in
    // tests/public-site.spec.js for the top-level-page version of this guard.
    test.skip(isPlaceholder || !ADMIN_USER || !ADMIN_PASS, 'No real WordPress target configured.');

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

      // A fresh, unauthenticated context: the nested tool page must still be
      // reachable even though the frontend Site Protection gate is now on.
      const loggedOutContext = await browser.newContext();
      const loggedOutPage = await loggedOutContext.newPage();
      // cacheBustExempt(), not cacheBust(): this request must stay recognised
      // as a clean owned-page request by the query allowlist (pages.php) —
      // see that helper's doc comment for why plain cacheBust() would trip
      // the very exemption this assertion is pinning.
      const response = await loggedOutPage.goto(cacheBustExempt('/toolbox/surecart/'));

      expect(
        response && response.status(),
        'a nested plugin-owned tool page must not be blocked (wp_die 403) by Site Protection'
      ).toBe(200);

      await loggedOutContext.close();
    } finally {
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
});
