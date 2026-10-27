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

test('the thank-you page wears the checkout header, with the way home on the right', async ({ page }) => {
  await page.goto(THANKS);
  await expect(page.locator('.bw-admin.blueworx-checkout')).toHaveCount(1);
  await expect(page.locator('.blueworx-checkout__head h1')).toHaveText('Thank you');
  await expect(page.locator('.blueworx-checkout__head .blueworx-checkout__home')).toHaveText('Back to the site');
  await expect(page.locator('.blueworx-checkout__secure')).toHaveCount(0);
  await expect(page.locator('#shop-content')).toHaveText('THANKS CONTENT');
  await expect(page.locator('head link[rel="stylesheet"][href*="store-surecart.css"]')).toHaveCount(0);
});

// Elementor styles every h1 and link through the body's .elementor-kit-N
// class, which used to outrank the header and blow "Checkout" up to 48px.
for (const [name, url] of [['checkout', CHECKOUT], ['thank-you page', THANKS]]) {
  test(`a page builder's heading and link styles do not reach the ${name} header`, async ({ page }) => {
    await page.goto(url);
    await page.evaluate(() => {
      document.body.classList.add('elementor-kit-52');
      const style = document.createElement('style');
      style.textContent = '.elementor-kit-52 h1{font-size:48px;line-height:1.2em;color:rgb(97,43,22)}'
        + '.elementor-kit-52 a{color:rgb(97,43,22);text-decoration:underline}';
      document.head.appendChild(style);
    });
    const h1 = page.locator('.blueworx-checkout__h1');
    await expect(h1).toHaveCSS('font-size', '15px');
    await expect(h1).not.toHaveCSS('color', 'rgb(97, 43, 22)');
    if (url === THANKS) {
      await expect(page.locator('.blueworx-checkout__home')).not.toHaveCSS('text-decoration-line', 'underline');
    }
  });
}

test('an ordinary page is left alone', async ({ page }) => {
  await page.goto('/claimed-fixture/');
  await expect(page.locator('.bw-admin')).toHaveCount(0);
  await expect(page.locator('#claimed-content')).toHaveText('CLAIMED');
});

test('an archive is left alone even if its id collided with a store page', async ({ page }) => {
  // get_queried_object_id() answers a term id here, not a post id — a store
  // page is only ever a single post, so an archive must never be dressed.
  await page.goto('/category/uncategorized/');
  await expect(page.locator('.blueworx-checkout')).toHaveCount(0);
  await expect(page.locator('head link[href*="store.css"]')).toHaveCount(0);
});
