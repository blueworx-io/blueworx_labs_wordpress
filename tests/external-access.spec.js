// `test` comes from helpers.js (see feature-toggles.spec.js for why): it carries
// the fixture that opts out of core's wp-admin view transitions.
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

const PAGE = '/wp-admin/admin.php?page=blueworx-external';
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

test.describe('External access console', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

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

  test('withdrawing access removes the row', async ({ page }) => {
    await openConsole(page);
    await ensureFeatureOn(page);

    const row = page.locator('[data-testid="bw-external-row"]', { hasText: INVITEE });

    await row.locator('[data-testid="bw-external-revoke"]').click();

    await expect(
      page.locator('[data-testid="bw-external-row"]', { hasText: INVITEE })
    ).toHaveCount(0);
  });
});
