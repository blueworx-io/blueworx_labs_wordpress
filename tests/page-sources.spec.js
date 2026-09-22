import { test, expect, login } from './helpers.js';

// The Pages list's Source column: the store names its own pages, the test
// mu-plugin names one of its fixtures the way another plugin would, and a
// page with a source can only be viewed or edited from the list — not
// trashed, whichever way the request comes.
const PAGES = '/wp-admin/edit.php?post_type=page&post_status=all';

function row(page, title) {
  return page
    .locator('#the-list tr', { has: page.locator('a.row-title', { hasText: new RegExp(`^${title}$`) }) })
    .first();
}

test('the column is there, headed Source, next to Title', async ({ page }) => {
  await login(page);
  await page.goto(PAGES);
  const headings = await page.locator('thead th').allInnerTexts();
  const title = headings.findIndex((h) => h.trim() === 'Title');
  expect(headings[title + 1].trim()).toBe('Source');
});

test("the store's pages read Commerce page and keep only view and edit", async ({ page }) => {
  await login(page);
  await page.goto(PAGES);
  for (const title of ['Checkout fixture', 'Thank you fixture', 'Dashboard fixture']) {
    const r = row(page, title);
    await expect(r.locator('.column-blueworx_page_source')).toHaveText('Commerce page');
    await expect(r.locator('.row-actions .edit')).toHaveCount(1);
    await expect(r.locator('.row-actions .view')).toHaveCount(1);
    await expect(r.locator('.row-actions .inline')).toHaveCount(0);
    await expect(r.locator('.row-actions .trash')).toHaveCount(0);
  }
});

test('another plugin names its page through the filter', async ({ page }) => {
  await login(page);
  await page.goto(PAGES);
  const r = row(page, 'Claimed dashboard fixture');
  await expect(r.locator('.column-blueworx_page_source')).toHaveText('Fixture page');
  await expect(r.locator('.row-actions .trash')).toHaveCount(0);
});

test('an ordinary page is left alone', async ({ page }) => {
  await login(page);
  await page.goto(PAGES);
  const r = row(page, 'Sample Page');
  await expect(r.locator('.column-blueworx_page_source')).toHaveText('');
  await expect(r.locator('.row-actions .trash')).toHaveCount(1);
});

test('a bulk trash of a page with a source is refused', async ({ page }) => {
  await login(page);
  await page.goto(PAGES);
  await row(page, 'Checkout fixture').locator('input[type="checkbox"]').check();
  await page.selectOption('#bulk-action-selector-top', 'trash');
  await page.click('#doaction');
  await expect(page.locator('body')).toContainText('This is a commerce page. The site is served from it, so it cannot be deleted.');
  // And it is still there.
  await page.goto(PAGES);
  await expect(row(page, 'Checkout fixture')).toHaveCount(1);
});
