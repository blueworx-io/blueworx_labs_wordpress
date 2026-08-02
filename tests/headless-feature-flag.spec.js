/**
 * The headless REST layer is off unless somebody switches it on.
 *
 * Nothing consumes `blueworx/v1` any more, so the whole layer now sits behind a
 * feature toggle that defaults to off. These assert the part that matters: with
 * the toggle off the namespace is not there at all — not "returns 401", not
 * "returns an empty list", but absent, because the routes are never registered.
 *
 * The toggle is left as it was found, whichever way round that is.
 */

import { request } from '@playwright/test';
import { test, expect, baseURL, isPlaceholder, ADMIN_USER, ADMIN_PASS, login, restoreAll } from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const TOGGLE = 'input.blueworx-feature-toggle[data-blueworx-feature="headless_api"]';
const ns = '/wp-json/blueworx/v1';

test.describe('Headless API feature flag', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the blueworx/v1 namespace is gone while the feature is off', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);

    const toggle = page.locator(TOGGLE);
    await expect(toggle, 'the settings page should offer a Headless content API toggle').toHaveCount(1);

    const wasChecked = await toggle.isChecked();

    await restoreAll([
      [
        'probe the namespace with the feature off',
        async () => {
          if (wasChecked) {
            await toggle.setChecked(false);
            await page.getByRole('button', { name: 'Save Changes' }).click();
            await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
          }

          // A separate, unauthenticated context: the routes must be absent to
          // the anonymous internet, which is who was reaching them before.
          const api = await request.newContext({ baseURL });

          try {
            const site = await api.get(`${ns}/site`);
            expect(site.status(), 'a public content route must not answer while the feature is off').toBe(404);

            const login404 = await api.post(`${ns}/auth/login`, {
              data: { login: 'nobody@example.test', password: 'wrong' },
            });
            expect(login404.status(), 'the sign-in route must not answer while the feature is off').toBe(404);
          } finally {
            await api.dispose();
          }
        },
      ],
      [
        'restore the toggle',
        async () => {
          await page.goto(SETTINGS_PATH);
          await page.locator(TOGGLE).setChecked(wasChecked);
          await page.getByRole('button', { name: 'Save Changes' }).click();
          await expect(page.locator('.notice-success').first()).toContainText('Settings saved');
        },
      ],
    ]);
  });
});
