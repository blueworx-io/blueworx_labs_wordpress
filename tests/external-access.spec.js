// `test` comes from helpers.js (see feature-toggles.spec.js for why): it carries
// the fixture that opts out of core's wp-admin view transitions.
import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  openSectionFor,
  readCheckedGroup,
  setCheckedGroup,
  saveEnhancements,
} from './helpers.js';

const PAGE = '/wp-admin/admin.php?page=blueworx-external';
const ENHANCEMENTS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const INVITEE = `bw-plan-test-${Date.now()}@example.invalid`;

async function openConsole(page) {
  await login(page);
  await page.goto(PAGE);
}

// The real checkbox behind this design-system Switch is a zero-size,
// transparent element behind the track it draws (see setFeature() in
// helpers.js) — clicking the input itself lands on the track and times out.
// The label is what a person clicks, and it is what this clicks.
async function setExternalFeature(page, checked) {
  const toggle = page.locator('[data-testid="bw-external-feature"]');

  if ((await toggle.isChecked()) === checked) {
    return;
  }

  await page.locator('label.bw-switch:has(input[data-testid="bw-external-feature"])').click();
  await expect(toggle).toBeChecked({ checked });
  await page.getByRole('button', { name: 'Save' }).first().click();
  await page.waitForURL(/page=blueworx-external/);
}

async function ensureFeatureOn(page) {
  await setExternalFeature(page, true);
}

// Captured in beforeAll, restored in afterAll. Turning the feature on for real
// (rather than through the fixture, which restores its own changes itself)
// calls blueworx_external_allow_in_site_protection(), which can add the role
// to Site Protection's allow-lists — and switching the feature back off
// afterwards does not undo that (see blueworx_handle_external_feature_toggle()
// in includes/admin-pages.php: the removal only ever happens on switch-ON).
// Left alone, that dirties the site for everything else that runs after this
// spec, even though it does not break a re-run of these two files.
let originalFeatureOn = false;
let originalFrontendRoles = [];
let originalBackendRoles = [];

test.describe('External access console', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    // browser.newPage() bypasses the `test` fixture above, so the
    // reduced-motion emulation it applies has to be repeated here — see
    // helpers.js for why headless Chromium needs it before any click.
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await login(page);

    await page.goto(PAGE);
    originalFeatureOn = await page.locator('[data-testid="bw-external-feature"]').isChecked();

    await page.goto(ENHANCEMENTS_PATH);
    await openSectionFor(page, 'site_protection');
    originalFrontendRoles = await readCheckedGroup(page, 'blueworx_frontend_protection_roles');
    originalBackendRoles = await readCheckedGroup(page, 'blueworx_backend_protection_roles');

    await page.close();
  });

  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage();
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await login(page);

    await page.goto(PAGE);
    await setExternalFeature(page, originalFeatureOn);

    await page.goto(ENHANCEMENTS_PATH);
    await openSectionFor(page, 'site_protection');
    await setCheckedGroup(page, 'blueworx_frontend_protection_roles', originalFrontendRoles);
    await setCheckedGroup(page, 'blueworx_backend_protection_roles', originalBackendRoles);
    await saveEnhancements(page);

    await page.close();
  });

  test('the screen explains itself while the feature is off', async ({ page }) => {
    await openConsole(page);
    await setExternalFeature(page, false);

    await expect(page.getByText('External access is switched off')).toBeVisible();
    await expect(page.locator('[data-testid="bw-external-invite"]')).toHaveCount(0);
  });

  test('inviting somebody puts them in the list with an end date', async ({ page }) => {
    await openConsole(page);
    await ensureFeatureOn(page);

    await page.locator('[data-testid="bw-external-name"]').fill('Plan Test Person');
    await page.locator('[data-testid="bw-external-email"]').fill(INVITEE);
    await page.locator('[data-testid="bw-external-note"]').fill('Created by the test suite');
    await page.locator('[data-testid="bw-external-invite"]').click();

    const row = page.locator('[data-testid="bw-external-row"]', { hasText: INVITEE });

    await expect(row).toHaveCount(1);
    // The badge says Active or Ends soon depending on the chosen length; what
    // matters here is that the row is not already Ended.
    await expect(row).not.toContainText('Ended');
  });

  test('the same address cannot be invited twice', async ({ page }) => {
    await openConsole(page);
    await ensureFeatureOn(page);

    await page.locator('[data-testid="bw-external-email"]').fill(INVITEE);
    await page.locator('[data-testid="bw-external-invite"]').click();

    await expect(page.getByText('Nobody was invited.')).toBeVisible();
  });

  test('withdrawing access asks first, and then removes the row', async ({ page }) => {
    await openConsole(page);
    await ensureFeatureOn(page);

    const row = page.locator('[data-testid="bw-external-row"]', { hasText: INVITEE });

    // Withdrawing deletes the account and there is no undo, so the form asks
    // before it submits (assets/js/external-access.js). Playwright dismisses
    // dialogs unless something handles them — which is also the proof the
    // question is really being asked: without this handler the click would be
    // cancelled and the row would still be there.
    let asked = '';
    page.on('dialog', (dialog) => {
      asked = dialog.message();
      dialog.accept();
    });

    await row.locator('[data-testid="bw-external-revoke"]').click();

    await expect
      .poll(() => asked, { message: 'Withdraw must ask before it deletes' })
      .toContain('cannot be undone');

    await expect(
      page.locator('[data-testid="bw-external-row"]', { hasText: INVITEE })
    ).toHaveCount(0);
  });
});
