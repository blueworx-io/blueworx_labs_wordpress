import { test, expect } from './helpers.js';

// The frame goes on whichever post SureCart records as its checkout. The
// harness has no SureCart, so checkout-fixture (seeded by global-setup.js,
// with the option pointed at it) stands in. These assert our frame and the
// shop's content surviving it — never SureCart's fields.
const CHECKOUT = '/checkout-fixture/';
const THANKS = '/thanks-fixture/';

test('the checkout wears its own frame', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('.bw-admin.blueworx-checkout')).toHaveCount(1);
  await expect(page.locator('.blueworx-checkout__head')).toBeVisible();
  await expect(page.locator('.blueworx-checkout__foot')).toBeVisible();
});

test("the shop's own content is passed through untouched", async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('#shop-content')).toHaveText('SHOP CONTENT');
});

test('a buyer is offered no nav to wander off into', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('.bw-secnav')).toHaveCount(0);
  await expect(page.locator('.blueworx-store__tabbar')).toHaveCount(0);
});

test('the page has exactly one heading at the top level', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('h1')).toHaveCount(1);
});

test('the design system and the field theme are asked for in the head', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('head link[rel="stylesheet"][href*="blueworx-admin-design.css"]')).toHaveCount(1);
  await expect(page.locator('head link[rel="stylesheet"][href*="store-surecart.css"]')).toHaveCount(1);
  await expect(page.locator('head link[rel="stylesheet"][href*="store.css"]')).toHaveCount(1);
});

test('the checkout owns the whole page, with no theme chrome around it', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('.wp-block-template-part')).toHaveCount(0);
  await expect(page.locator('header.wp-block-template-part, footer.wp-block-template-part')).toHaveCount(0);
});

test('the footer stacks into full-width targets on a phone', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(CHECKOUT);
  const back = page.locator('.blueworx-checkout__back');
  await expect(back).toBeVisible();
  const box = await back.boundingBox();
  expect(box.height).toBeGreaterThanOrEqual(44);
});

test('the thank-you page wears the bare frame', async ({ page }) => {
  await page.goto(THANKS);
  await expect(page.locator('.bw-admin.bw-page.blueworx-store')).toHaveCount(1);
  await expect(page.locator('h1')).toHaveText('Thank you');
  await expect(page.locator('#shop-content')).toHaveText('THANKS CONTENT');
  await expect(page.locator('head link[rel="stylesheet"][href*="store-surecart.css"]')).toHaveCount(0);
});

test('an ordinary page is left alone', async ({ page }) => {
  await page.goto('/claimed-fixture/');
  await expect(page.locator('.bw-admin')).toHaveCount(0);
  await expect(page.locator('#claimed-content')).toHaveText('CLAIMED');
});
