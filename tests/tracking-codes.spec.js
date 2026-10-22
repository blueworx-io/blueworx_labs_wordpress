/**
 * BlueWorx > Tracking codes.
 *
 * The PHP check covers which IDs are accepted and what code they produce. This
 * covers the parts only a real site can show: the screen saves, a bad ID is
 * refused out loud, and the code really lands in the page a visitor gets — in
 * the head and straight after the body opens — and leaves again when the
 * switch goes off.
 */

import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  cacheBust,
  setFeature,
  saveEnhancements,
  restoreAll,
} from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const TRACKING_PATH = '/wp-admin/admin.php?page=blueworx-tracking';

/**
 * Switches the function on or off from Enhancements.
 */
async function switchTracking(page, on) {
  await page.goto(SETTINGS_PATH);
  await setFeature(page, 'tracking_codes', on);
  await saveEnhancements(page);
}

/**
 * Fills the three ID boxes and saves.
 */
async function saveIds(page, ids) {
  await page.goto(TRACKING_PATH);
  await page.fill('#blueworx_tracking_gtm', ids.gtm);
  await page.fill('#blueworx_tracking_ga', ids.ga);
  await page.fill('#blueworx_tracking_meta', ids.meta);
  await page.locator('.bw-savebar button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');
}

/**
 * The home page's HTML, as a visitor's browser receives it.
 */
async function homeHtml(page) {
  const response = await page.request.get(cacheBust('/'));
  return response.text();
}

test.describe('Tracking codes', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test.afterEach(async ({ page }) => {
    // The boxes are only on the page while the function is on.
    await restoreAll([
      ['switch on', () => switchTracking(page, true)],
      ['empty the IDs', () => saveIds(page, { gtm: '', ga: '', meta: '' })],
      ['switch off', () => switchTracking(page, false)],
    ]);
  });

  test('saved IDs put each service\'s code on the page, and switching off removes it', async ({ page }) => {
    await login(page);

    // Off by default, so the page is not in the menu yet.
    await page.goto(SETTINGS_PATH);
    await expect(page.locator('#adminmenu a[href$="page=blueworx-tracking"]')).toHaveCount(0);

    await switchTracking(page, true);
    await expect(page.locator('#adminmenu a[href$="page=blueworx-tracking"]')).toHaveText('Tracking codes');

    // Lower case and stray spaces are forgiven; the boxes show the tidied IDs.
    await saveIds(page, { gtm: ' gtm-tfd87dqf ', ga: 'G-AB12CD34EF', meta: '123456789012345' });
    await expect(page.locator('.bw-notice--success')).toContainText('Settings saved.');
    await expect(page.locator('#blueworx_tracking_gtm')).toHaveValue('GTM-TFD87DQF');

    // Analytics set here alongside Tag Manager risks counting visits twice.
    await expect(page.locator('.bw-notice--warning')).toContainText('counted twice');

    const html = await homeHtml(page);
    const head = html.slice(0, html.indexOf('</head>'));
    const body = html.slice(html.indexOf('<body'));

    expect(head).toContain("(window,document,'script','dataLayer','GTM-TFD87DQF');</script>");
    expect(head).toContain('https://www.googletagmanager.com/gtag/js?id=G-AB12CD34EF');
    expect(head).toContain("fbq('init', '123456789012345');");

    // Tag Manager's fallback sits straight after the body opens, ahead of the
    // theme's own markup.
    const afterOpen = body.slice(body.indexOf('>') + 1).trimStart();
    expect(afterOpen.startsWith('<!-- Google Tag Manager (noscript) -->')).toBe(true);
    expect(afterOpen).toContain('https://www.googletagmanager.com/ns.html?id=GTM-TFD87DQF');

    // Never on an admin screen.
    await page.goto(TRACKING_PATH);
    expect(await page.content()).not.toContain('gtm.js');

    await switchTracking(page, false);
    expect(await homeHtml(page)).not.toContain('GTM-TFD87DQF');
  });

  test('a badly formed ID is refused and the previous one kept', async ({ page }) => {
    await login(page);
    await switchTracking(page, true);

    await saveIds(page, { gtm: 'GTM-TFD87DQF', ga: '', meta: '' });
    await saveIds(page, { gtm: "<script>alert('x')</script>", ga: '', meta: '12345' });

    const refused = page.locator('.bw-notice--danger');
    await expect(refused).toContainText('Google Tag Manager ID');
    await expect(refused).toContainText('Meta pixel ID');
    await expect(page.locator('#blueworx_tracking_gtm')).toHaveValue('GTM-TFD87DQF');

    expect(await homeHtml(page)).not.toContain("alert('x')");
  });
});
