import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
} from './helpers.js';

/**
 * Guides has two levels: a product along the top, topics underneath.
 *
 * Both are real URLs, and both have to work with no JavaScript — a client
 * being sent a link to one guide is the whole reason this screen exists.
 */

const GUIDES = '/wp-admin/admin.php?page=blueworx-guides';

test.describe('Guides — products and topics', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('a product row sits above the topic tabs, BlueWorx first', async ({ page }) => {
    await login(page);
    await page.goto(GUIDES);

    const products = page.locator('[data-blueworx-guide-products] .bw-prodtab');
    await expect(products.first()).toBeVisible();
    await expect(products.first()).toHaveText(/BlueWorx/);
    await expect(products.first()).toHaveClass(/is-active/);

    // Every product carries a count, and every one is a real link.
    const count = await products.count();
    expect(count).toBeGreaterThan(1);

    for (let i = 0; i < count; i++) {
      await expect(products.nth(i).locator('.bw-prodtab__count')).not.toBeEmpty();
      expect(await products.nth(i).getAttribute('href')).toContain('product=');
    }
  });

  test('choosing WordPress changes the topics underneath', async ({ page }) => {
    await login(page);
    await page.goto(GUIDES);

    const topicsBefore = await page.locator('[data-blueworx-guide-tabs] .bw-tab').allInnerTexts();

    await page.locator('[data-blueworx-guide-product="wordpress"]').click();

    await expect(page.locator('[data-blueworx-guide-product="wordpress"]')).toHaveClass(/is-active/);

    const topicsAfter = await page.locator('[data-blueworx-guide-tabs] .bw-tab').allInnerTexts();
    expect(topicsAfter).not.toEqual(topicsBefore);
    expect(topicsAfter.join(' ')).toContain('Writing');

    // And the cards showing belong to that product.
    await expect(page.locator('[data-blueworx-guide]').first()).toBeVisible();
  });

  test('a topic link carries its product, so it resolves on its own', async ({ page }) => {
    await login(page);
    await page.goto(`${GUIDES}&product=wordpress`);

    const tab = page.locator('[data-blueworx-guide-tabs] .bw-tab').nth(1);
    const href = await tab.getAttribute('href');
    expect(href).toContain('product=wordpress');

    await page.goto(href);
    await expect(page.locator('[data-blueworx-guide-product="wordpress"]')).toHaveClass(/is-active/);
    await expect(page.locator('[data-blueworx-guide]').first()).toBeVisible();
  });

  test('an unknown product falls back rather than emptying the screen', async ({ page }) => {
    await login(page);
    await page.goto(`${GUIDES}&product=nothing-here`);

    await expect(page.locator('[data-blueworx-guide-products] .bw-prodtab.is-active')).toHaveText(
      /BlueWorx/
    );
    await expect(page.locator('[data-blueworx-guide]').first()).toBeVisible();
  });

  test('a plugin we do not have gets no section', async ({ page }) => {
    await login(page);
    await page.goto(GUIDES);

    // SureCart and SureForms are not installed on the harness, so neither
    // should be offered — nobody needs guides for software they do not have.
    const labels = await page.locator('[data-blueworx-guide-products] .bw-prodtab').allInnerTexts();
    expect(labels.join(' ')).not.toContain('SureCart');
    expect(labels.join(' ')).not.toContain('SureForms');
  });
});
