import { test, expect, login, setFeature, saveEnhancements } from './helpers.js';

// SureCart's dashboard page is dashboard-fixture (option pointed at it by
// global-setup.js). The harness has no SureCart, so the only views are the
// overview and the one the test mu-plugin adds through the filter — which is
// exactly the hook API under test.
const DASHBOARD = '/dashboard-fixture/';
const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

async function signInAsMember(page) {
  await page.goto('/admin_login/');
  await page.fill('#user_login', 'member');
  await page.fill('#user_pass', 'wptest-member-pw');
  await page.click('#wp-submit');
  // Present, not visible: where a member lands after signing in the bar is
  // in the document but hidden, and its being there is what proves the
  // sign-in took.
  await expect(page.locator('#wpadminbar')).toHaveCount(1);
}

test('a signed-out visitor is sent to the login page', async ({ page }) => {
  await page.goto(DASHBOARD);
  await expect(page).toHaveURL(/admin_login/);
});

test('a member sees the frame, the nav and the overview', async ({ page }) => {
  await signInAsMember(page);
  await page.goto(DASHBOARD);
  await expect(page.locator('.bw-admin.bw-page.blueworx-store')).toHaveCount(1);
  await expect(page.locator('#foreign-content')).toHaveCount(0);
  await expect(page.locator('.bw-secnav__item', { hasText: 'Club' })).toBeVisible();
  await expect(page.locator('.blueworx-store__panel[data-view="dashboard"]:not([hidden])')).toHaveCount(1);
});

test('another plugin can put content on the overview', async ({ page }) => {
  await signInAsMember(page);
  await page.goto(DASHBOARD);
  await expect(page.locator('#test-welcome')).toHaveText('WELCOME');
});

test('another plugin can add a whole view', async ({ page }) => {
  await signInAsMember(page);
  await page.goto(`${DASHBOARD}?view=club`);
  await expect(page.locator('.blueworx-store__panel[data-view="club"]:not([hidden]) #test-panel')).toHaveText('TEST PANEL');
  await expect(page.locator('.blueworx-store__panel[data-view="dashboard"]')).toHaveAttribute('hidden', '');
});

test('the context filter reaches the frame', async ({ page }) => {
  await signInAsMember(page);
  await page.goto(DASHBOARD);
  await expect(page.locator('.blueworx-store__brandname')).toHaveText('Fixture Club');
});

test('junk in the address lands on the overview', async ({ page }) => {
  await signInAsMember(page);
  await page.goto(`${DASHBOARD}?view=nope`);
  await expect(page.locator('.blueworx-store__panel[data-view="dashboard"]:not([hidden])')).toHaveCount(1);
});

test("a plugin that claims the dashboard's address gets the visitor, panel and all", async ({ page }) => {
  await signInAsMember(page);
  await page.goto(`${DASHBOARD}?view=orders&bw_claim=1`);
  await expect(page).toHaveURL(/\/claimed-fixture\/\?view=orders/);
  await expect(page.locator('#claimed-content')).toHaveText('CLAIMED');
});

test('the checkout footer carries the links a plugin adds', async ({ page }) => {
  await page.goto('/checkout-fixture/');
  await expect(page.locator('.blueworx-checkout__links a', { hasText: 'Fixture terms' })).toHaveCount(1);
  await expect(page.locator('.blueworx-checkout__back')).toHaveText(/Back to Fixture Club/);
});

test('with the feature off, the page is left alone', async ({ page }) => {
  await login(page);
  await page.goto(SETTINGS_PATH);
  try {
    await setFeature(page, 'store_pages', false);
    await saveEnhancements(page);
    await page.goto('/checkout-fixture/');
    await expect(page.locator('.blueworx-checkout')).toHaveCount(0);
    await expect(page.locator('#shop-content')).toHaveText('SHOP CONTENT');
  } finally {
    await page.goto(SETTINGS_PATH);
    await setFeature(page, 'store_pages', true);
    await saveEnhancements(page);
  }
});
