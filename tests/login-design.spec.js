// `test` comes from helpers.js, not '@playwright/test' — see the note there.
import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  DASH_PATH,
  LOGIN_PATH,
  login,
  cacheBust,
} from './helpers.js';

const DESKTOP = { width: 1440, height: 900 };
const PHONE = { width: 375, height: 812 };

/**
 * Open the login screen logged out.
 *
 * Cache-busted: this is a logged-out page and Varnish serves those from cache,
 * which has previously made a working login screen look broken.
 */
async function openLogin(page, context, viewport = DESKTOP) {
  await context.clearCookies();
  await page.setViewportSize(viewport);
  await page.goto(cacheBust(LOGIN_PATH));
}

test.describe('BlueWorx split-screen login', () => {
  test.skip(isPlaceholder, 'No real staging/preview URL configured yet.');

  test('brand panel fills the left half and the card sits beside it', async ({ page, context }) => {
    await openLogin(page, context);

    const panel = page.locator('.bw-login-panel');
    await expect(panel).toBeVisible();

    const panelBox = await panel.boundingBox();
    const cardBox = await page.locator('#login').boundingBox();

    // Panel starts at the left edge and runs the full height.
    expect(panelBox.x).toBeCloseTo(0, 0);
    expect(panelBox.height).toBeGreaterThanOrEqual(DESKTOP.height - 1);

    // The card clears it completely rather than sitting on top of it.
    expect(cardBox.x).toBeGreaterThanOrEqual(panelBox.x + panelBox.width);
  });

  test('panel carries the brand, badge, headline, tagline and footer', async ({ page, context }) => {
    await openLogin(page, context);

    await expect(page.locator('.bw-login-brand-mark')).toHaveText(/^[A-Z0-9]$/);
    await expect(page.locator('.bw-login-badge')).toHaveText('Admin Login');
    await expect(page.locator('.bw-login-headline')).toHaveText(
      'Everything Your Site Needs, In One Place'
    );
    await expect(page.locator('.bw-login-tagline')).not.toBeEmpty();
    await expect(page.locator('.bw-login-panel-footer')).toContainText('Powered by BlueWorx');
  });

  test('card is headed "Welcome Back" and the panel replaces the logo', async ({ page, context }) => {
    await openLogin(page, context);

    await expect(page.locator('.bw-login-title')).toHaveText('Welcome Back');
    await expect(page.locator('.bw-login-subtitle')).toHaveText('Sign in to manage your site.');

    // The brand lives in the panel at this width, so the logo heading inside
    // the card is suppressed rather than duplicating it.
    await expect(page.locator('#login h1')).toBeHidden();
  });

  test('the form is renamed to match the design', async ({ page, context }) => {
    await openLogin(page, context);

    await expect(page.locator('label[for="user_login"]')).toHaveText('Email or Username');
    await expect(page.locator('label[for="rememberme"]')).toHaveText('Remember me on this device');
    await expect(page.locator('#wp-submit')).toHaveValue('Sign In');
    await expect(page.locator('#user_login')).toHaveAttribute('placeholder', 'you@example.com');
  });

  test('"Forgot Password?" sits on the password label row', async ({ page, context }) => {
    await openLogin(page, context);

    // Moved inside the password row — the CSS that positions it only applies
    // there, so this is the assertion that catches the move silently failing.
    const forgot = page.locator('.user-pass-wrap > .bw-login-forgot');
    await expect(forgot).toBeVisible();

    const forgotBox = await forgot.boundingBox();
    const labelBox = await page.locator('label[for="user_pass"]').boundingBox();
    const inputBox = await page.locator('#user_pass').boundingBox();

    // On the label's line, not below the field.
    expect(forgotBox.y).toBeLessThan(inputBox.y);
    expect(Math.abs(forgotBox.y - labelBox.y)).toBeLessThan(12);

    // And to the right of the label.
    expect(forgotBox.x).toBeGreaterThan(labelBox.x + labelBox.width);
  });

  test('it is not duplicated in the footer nav', async ({ page, context }) => {
    await openLogin(page, context);

    await expect(page.locator('#nav .wp-login-lost-password')).toHaveCount(0);
    await expect(page.locator('.bw-login-forgot')).toHaveCount(1);

    // A bare #nav must not leave a divider rule floating under the button.
    const navLinks = await page.locator('#nav a').count();
    if (navLinks === 0) {
      await expect(page.locator('#nav')).toBeHidden();
    }
  });

  test('the lost-password screen keeps its own links and heading', async ({ page, context }) => {
    await context.clearCookies();
    await page.setViewportSize(DESKTOP);
    await page.goto(cacheBust(`${LOGIN_PATH}?action=lostpassword`));

    await expect(page.locator('.bw-login-title')).toHaveText('Forgot Your Password?');
    // The password-row link only exists on the sign-in form, so this screen's
    // own nav must be left intact.
    await expect(page.locator('#nav a')).not.toHaveCount(0);
  });

  test('on a phone the panel gives way to the centred card', async ({ page, context }) => {
    await openLogin(page, context, PHONE);

    await expect(page.locator('.bw-login-panel')).toBeHidden();

    // The brand comes back inside the card to replace what the panel carried.
    await expect(page.locator('#login h1 a')).toBeVisible();

    // Nothing may overflow sideways at this width.
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    expect(scrollWidth).toBeLessThanOrEqual(PHONE.width);
  });

  test('signing in still works', async ({ page, context }) => {
    test.skip(!ADMIN_USER || !ADMIN_PASS, 'No WP_ADMIN_USER / WP_ADMIN_PASS configured.');
    await context.clearCookies();

    // The whole issue is a restyle: the one thing that must not change.
    await login(page);
    await page.goto(DASH_PATH);
    await expect(page.locator('body.wp-admin')).toBeVisible();
  });
});
