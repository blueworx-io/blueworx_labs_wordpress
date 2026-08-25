import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
} from './helpers.js';

/**
 * The two screens that own a single function each.
 *
 * Support access can now be switched on where you are standing rather than by
 * being sent to Enhancements. Both paths write through one setter, so the two
 * switches cannot end up disagreeing — which is the only way this change could
 * go wrong, and so the thing worth asserting.
 */

const SUPPORT = '/wp-admin/admin.php?page=blueworx-support';
const SSO = '/wp-admin/admin.php?page=blueworx-sso';
const SETTINGS = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

test.describe('Support access', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the screen states key, window and last session', async ({ page }) => {
    await login(page);
    await page.goto(SUPPORT);

    const list = page.locator('.bw-dl').first();
    await expect(list).toBeVisible();

    const terms = await list.locator('dt').allInnerTexts();
    expect(terms.join(' ')).toContain('Key');
    expect(terms.join(' ')).toContain('Window');
    expect(terms.join(' ')).toContain('Last session');

    // The key itself is stored hashed and must never appear here.
    const values = await list.locator('dd').allInnerTexts();
    expect(values.join(' ')).not.toMatch(/bwx-/i);
  });

  test('its own switch and the one on Enhancements agree', async ({ page }) => {
    await login(page);
    await page.goto(SUPPORT);

    const own = page.locator('[data-testid="bw-support-feature"]');
    await expect(own).toBeAttached();

    // The input sits behind its own track, which is how every switch in the
    // design system is drawn — click the label, not the box.
    const flip = () => page.locator('label.bw-switch:has([data-testid="bw-support-feature"])').click();

    const wasOn = await own.isChecked();

    try {
      await flip();
      await page.getByRole('button', { name: 'Save', exact: true }).click();

      await page.goto(SETTINGS);
      await expect(
        page.locator('.blueworx-feature-toggle[data-blueworx-feature="support_access"]')
      ).toBeChecked({ checked: !wasOn });
    } finally {
      await page.goto(SUPPORT);
      const now = page.locator('[data-testid="bw-support-feature"]');
      if ((await now.count()) && (await now.isChecked()) !== wasOn) {
        await flip();
        await page.getByRole('button', { name: 'Save', exact: true }).click();
      }
    }
  });
});

test.describe('Single sign-on', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('a provider is picked by name', async ({ page }) => {
    await login(page);
    await page.goto(SSO);

    const picker = page.locator('select#blueworx_sso_provider');

    if ((await picker.count()) === 0) {
      test.skip(true, 'Single sign-on is switched off on this site.');
    }

    const options = await picker.locator('option').allInnerTexts();
    expect(options.join(' ')).toContain('Microsoft Entra ID');
    expect(options.join(' ')).toContain('Any OpenID Connect provider');
  });

  test('hiding the password form is refused until a sign-in has worked', async ({ page }) => {
    await login(page);
    await page.goto(SSO);

    const box = page.locator('[data-testid="bw-sso-hide-password"]');

    if ((await box.count()) === 0) {
      test.skip(true, 'Single sign-on is switched off on this site.');
    }

    // No provider sign-in has ever succeeded on the harness, so the switch must
    // refuse. This is the guard that stops it being a way to lock everybody out.
    await expect(box).toBeDisabled();
  });

  test('Advanced is the design system accordion, not a browser triangle', async ({ page }) => {
    await login(page);
    await page.goto(SSO);

    const advanced = page.locator('details.bw-accordion.blueworx-sso-advanced');

    if ((await advanced.count()) === 0) {
      test.skip(true, 'Single sign-on is switched off on this site.');
    }

    await expect(advanced.locator('.bw-accordion__head')).toBeVisible();
    await expect(advanced.locator('.bw-accordion__chev')).toHaveCount(1);
  });
});
