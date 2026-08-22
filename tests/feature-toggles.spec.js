// `test` comes from helpers.js, not '@playwright/test': it carries the fixture
// that opts out of core's wp-admin view transitions, which otherwise freeze
// rendering in headless Chromium and hang every actionability check.
import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  openSection,
  openSectionFor,
  setFeature,
  saveEnhancements,
} from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

/**
 * Navigate to the settings page, logging in first (a fresh context has no
 * session).
 */
async function gotoSettings(page) {
  await login(page);
  await page.goto(SETTINGS_PATH);
}

test.describe('BlueWorx feature toggles', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('every section is reachable, and Comments lives in Content', async ({ page }) => {
    await gotoSettings(page);

    // Sections are behind a section nav now rather than stacked down one page,
    // so what matters is that each one is offered and opens.
    for (const [id, label] of [
      ['security', 'Security & Access'],
      ['content', 'Content'],
      ['translation', 'Translation'],
      ['notifications', 'Notifications & Cleanup'],
      ['performance', 'Performance'],
      ['admin_menu', 'Admin Menu'],
    ]) {
      await expect(page.locator(`[data-blueworx-section="${id}"]`)).toContainText(label);
      await openSection(page, id);
      await expect(page.getByRole('heading', { name: label })).toBeVisible();
    }

    await openSectionFor(page, 'comments');
    await expect(
      page.locator('input.blueworx-feature-toggle[data-blueworx-feature="comments"]')
    ).toBeVisible();
  });

  test('orphaned managed roles are absent from the Site Protection role lists', async ({ page }) => {
    await gotoSettings(page);

    // The role editor removed in 1.8.0 left blueworx_business_owner /
    // _external_admin / _content_editor behind in the database; the 1.15.0
    // migration sweeps up any that have no users. They must no longer be offered
    // as Site Protection role options. (A role still holding users is skipped by
    // design — if this fails, that role still has members to reassign first.)
    for (const slug of [
      'blueworx_business_owner',
      'blueworx_external_admin',
      'blueworx_content_editor',
    ]) {
      await expect(page.locator(`option[value="${slug}"]`)).toHaveCount(0);
    }
  });

  test('toggling a feature persists after save', async ({ page }) => {
    await gotoSettings(page);
    await openSectionFor(page, 'page_excerpts');

    const toggle = page.locator('input.blueworx-feature-toggle[data-blueworx-feature="page_excerpts"]');
    const wasChecked = await toggle.isChecked();

    await setFeature(page, 'page_excerpts', !wasChecked);
    await saveEnhancements(page);

    await openSectionFor(page, 'page_excerpts');
    await expect(toggle).toBeChecked({ checked: !wasChecked });

    // Restore original state so the test is idempotent across runs.
    await setFeature(page, 'page_excerpts', wasChecked);
    await saveEnhancements(page);
  });
});
