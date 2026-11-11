/**
 * Email log: every email the site sends is listed under BlueWorx → Email logs,
 * filterable, with each message on a page of its own — and emails are sent in
 * the site's name rather than "WordPress".
 *
 * The harness has no mail server, so tests/fixtures/email-log.php fakes the
 * mailer's answer: "ok" is accepted, "fail" is refused.
 */
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  setFeature,
  featureIsOn,
  saveEnhancements,
  openSectionFor,
} from './helpers.js';
import { isHarness } from './test-target.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SCREEN = '/wp-admin/admin.php?page=blueworx-email-log';
const ENHANCEMENTS = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const STAMP = Date.now();
const OK_SUBJECT = `Log test accepted ${STAMP}`;
const FAIL_SUBJECT = `Log test refused ${STAMP}`;

function fixture(mode, subject = '') {
  const file = path.join(__dirname, 'fixtures', 'email-log.php');
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  const out = execFileSync('php', [file, wpLoad, mode, subject], { encoding: 'utf8' });

  return JSON.parse(out.trim().split('\n').pop());
}

test.describe('Email log', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );
  test.skip(!isHarness, 'Sends mail through the harness, which needs the fixture on the same machine.');
  test.describe.configure({ mode: 'serial' });

  let wasOn = false;
  let senderWasOn = false;

  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    await login(page);
    await page.goto(ENHANCEMENTS);
    await openSectionFor(page, 'email_log');
    wasOn = await featureIsOn(page, 'email_log');

    senderWasOn = await featureIsOn(page, 'email_sender');

    if (!wasOn || !senderWasOn) {
      await setFeature(page, 'email_log', true);
      await setFeature(page, 'email_sender', true);
      await saveEnhancements(page);
    }

    await page.close();
  });

  test.afterAll(async ({ browser }) => {
    fixture('off');

    if (!wasOn || !senderWasOn) {
      const page = await browser.newPage();
      await login(page);
      await page.goto(ENHANCEMENTS);
      await openSectionFor(page, 'email_log');
      await setFeature(page, 'email_log', wasOn);
      await setFeature(page, 'email_sender', senderWasOn);
      await saveEnhancements(page);
      await page.close();
    }
  });

  test('the BlueWorx menu lists Email logs while the function is on', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/index.php');
    await expect(page.locator('#adminmenu a[href$="page=blueworx-email-log"]')).toHaveCount(1);
  });

  test('an accepted and a refused email both appear, newest first, with the right status', async ({ page }) => {
    const ok = fixture('ok', OK_SUBJECT);
    expect(ok.result).toBe(true);

    const fail = fixture('fail', FAIL_SUBJECT);
    expect(fail.result).toBe(false);

    await login(page);
    await page.goto(SCREEN);

    const rows = page.locator('[data-testid="bw-email-log-row"]');
    await expect(rows.first()).toContainText(FAIL_SUBJECT);
    await expect(rows.first()).toHaveAttribute('data-status', 'failed');
    await expect(rows.first()).toContainText('Could not connect to SMTP host');
    await expect(rows.first()).toContainText('SMTP');

    const accepted = rows.filter({ hasText: OK_SUBJECT });
    await expect(accepted).toHaveCount(1);
    await expect(accepted).toHaveAttribute('data-status', 'sent');
    await expect(accepted).toContainText('first@example.invalid');
    await expect(accepted).toContainText('and 1 more');
  });

  test('emails go out in the site\'s name, not "WordPress"', async ({ page }) => {
    const ok = fixture('ok', `Sender name ${STAMP}`);
    expect(ok.from_name).not.toBe('WordPress');
    expect(ok.from_name).not.toBe('');

    await login(page);
    const title = ok.site_title;
    expect(ok.from_name).toBe(title);

    await page.goto(SCREEN);
    await expect(
      page.locator('[data-testid="bw-email-log-row"]').filter({ hasText: `Sender name ${STAMP}` })
    ).toContainText(`From ${title}`);
  });

  test('the filters narrow the list by status and by text', async ({ page }) => {
    await login(page);
    await page.goto(SCREEN);

    await page.locator('#bw-email-log-status').selectOption('failed');
    await page.locator('[data-testid="bw-email-log-filters"] button[type="submit"]').click();
    await page.waitForURL(/status=failed/);

    const rows = page.locator('[data-testid="bw-email-log-row"]');
    await expect(rows.first()).toBeVisible();
    await expect(rows.filter({ hasText: OK_SUBJECT })).toHaveCount(0);
    expect(await rows.evaluateAll((els) => els.every((el) => el.dataset.status === 'failed'))).toBe(true);

    await page.locator('#bw-email-log-status').selectOption('');
    await page.locator('#bw-email-log-search').fill(OK_SUBJECT);
    await page.locator('[data-testid="bw-email-log-filters"] button[type="submit"]').click();
    await page.waitForURL(/s=Log\+test\+accepted/);

    await expect(rows).toHaveCount(1);
    await expect(rows).toContainText(OK_SUBJECT);

    // A date range that cannot include today.
    await page.goto(`${SCREEN}&from=2000-01-01&to=2000-01-02`);
    await expect(page.locator('[data-testid="bw-email-log-row"]')).toHaveCount(0);
    await expect(page.locator('.bw-empty')).toContainText('Nothing matches');
  });

  test('View opens the full email on its own page in a new tab', async ({ page, context }) => {
    await login(page);
    await page.goto(`${SCREEN}&s=${encodeURIComponent(OK_SUBJECT)}`);

    const row = page.locator('[data-testid="bw-email-log-row"]').first();
    await expect(row).toContainText(OK_SUBJECT);

    const link = row.locator('[data-testid="bw-email-log-view"]');
    await expect(link).toHaveAttribute('target', '_blank');

    const [popup] = await Promise.all([context.waitForEvent('page'), link.click()]);
    await popup.waitForLoadState('domcontentloaded');

    const message = popup.locator('[data-testid="bw-email-log-message"]');
    await expect(message.locator('h1')).toHaveText(OK_SUBJECT);
    await expect(message).toContainText('first@example.invalid, second@example.invalid');
    await expect(message).toContainText('Sent');
    await expect(message).toContainText('HTML');

    // An HTML body is shown inside a sandboxed frame, never in the page itself.
    const frame = message.locator('iframe[sandbox]');
    await expect(frame).toHaveCount(1);
    await expect(popup.frameLocator('iframe[sandbox]').locator('body')).toContainText('Hello from the email log fixture');

    // The body is not on the list screen.
    await expect(page.locator('body')).not.toContainText('Hello from the');

    await popup.close();
  });

  test('a refused email\'s page shows the mailer\'s error', async ({ page, context }) => {
    await login(page);
    await page.goto(`${SCREEN}&s=${encodeURIComponent(FAIL_SUBJECT)}`);

    const link = page.locator('[data-testid="bw-email-log-view"]').first();
    const [popup] = await Promise.all([context.waitForEvent('page'), link.click()]);
    await popup.waitForLoadState('domcontentloaded');

    const message = popup.locator('[data-testid="bw-email-log-message"]');
    await expect(message).toContainText('Failed');
    await expect(message).toContainText('Could not connect to SMTP host');
    await expect(message.locator('pre')).toContainText('This one is meant to fail.');
    await popup.close();
  });

  test('Clear log empties it', async ({ page }) => {
    await login(page);
    await page.goto(SCREEN);
    await page.locator('[data-testid="bw-email-log-clear"]').click();
    await page.waitForURL(/cleared=1/);

    await expect(page.locator('.bw-notice--success')).toContainText('cleared');
    await expect(page.locator('[data-testid="bw-email-log-row"]')).toHaveCount(0);
    await expect(page.locator('[data-testid="bw-email-log-clear"]')).toHaveCount(0);
  });

  test('with the function off the screen says so and the menu row goes', async ({ page }) => {
    await login(page);
    await page.goto(ENHANCEMENTS);
    await setFeature(page, 'email_log', false);
    await saveEnhancements(page);

    await expect(page.locator('#adminmenu a[href$="page=blueworx-email-log"]')).toHaveCount(0);

    await page.goto(SCREEN);
    await expect(page.locator('.bw-notice--info')).toContainText('Email logging is switched off');

    await page.goto(ENHANCEMENTS);
    await setFeature(page, 'email_log', true);
    await saveEnhancements(page);
  });
});
