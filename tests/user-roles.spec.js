// `test` comes from helpers.js, not '@playwright/test': it carries the fixture
// that opts out of core's wp-admin view transitions, which otherwise freeze
// rendering in headless Chromium and hang every actionability check.
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login, restoreAll } from './helpers.js';

const ROLE_BOX = 'input[name="blueworx_user_roles[]"]';

/**
 * Reads the role names, in the order the checkbox list offers them.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @return {Promise<string[]>} Role display names.
 */
function offeredRoleNames(page) {
  return page.locator('.blueworx-role-choices label span').allTextContents();
}

/**
 * Deletes a user by login name, leaving the site as the test found it.
 *
 * @param {import('@playwright/test').Page} page      Playwright page.
 * @param {string}                          userLogin User login to remove.
 */
async function deleteUser(page, userLogin) {
  await page.goto(`/wp-admin/users.php?s=${encodeURIComponent(userLogin)}`);

  const row = page.locator('tr', { hasText: userLogin }).first();

  if ((await row.count()) === 0) {
    return;
  }

  // Followed rather than clicked: the row actions are only painted on hover, so
  // the link sits outside the viewport as far as an actionability check is
  // concerned. The href already carries its own nonce.
  const href = await row.locator('a.submitdelete').first().getAttribute('href');
  await page.goto(`/wp-admin/${href}`);
  // The confirmation screen; "Delete all content" is already the safe default
  // for a user who has never published anything.
  await page.locator('#submit').click();
  await expect(page.locator('#message, .notice')).toContainText(/deleted/i);
}

test.describe('BlueWorx user roles — the role control', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the Add User screen offers roles as checkboxes, not a dropdown', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/user-new.php');

    await expect(page.locator(ROLE_BOX).first()).toBeVisible();
    // The native select stays in the DOM for anything that looks for it, but is
    // disabled so it is never submitted alongside the checkboxes.
    await expect(page.locator('select#role')).toBeDisabled();
    // The site's default role starts ticked, exactly as the dropdown did.
    await expect(page.locator(`${ROLE_BOX}:checked`)).toHaveCount(1);
  });

  test('roles are listed alphabetically', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/user-new.php');

    const offered = await offeredRoleNames(page);
    expect(offered.length).toBeGreaterThan(1);
    expect(offered).toEqual([...offered].sort((a, b) => a.localeCompare(b)));
  });

  test('more than one role can be ticked at once', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/user-new.php');

    // The site's default role starts ticked, so clear the slate first — the
    // point of the assertion is that ticking a second box does not untick the
    // first, the way the dropdown it replaced would have.
    const boxes = page.locator(ROLE_BOX);
    const count = await boxes.count();

    for (let i = 0; i < count; i += 1) {
      await boxes.nth(i).setChecked(false);
    }

    await boxes.nth(0).setChecked(true);
    await boxes.nth(1).setChecked(true);

    await expect(page.locator(`${ROLE_BOX}:checked`)).toHaveCount(2);
  });
});

test.describe('BlueWorx user roles — saving', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  // Unique per run so a leftover account from a failed run cannot collide with
  // this one, and so a parallel worker cannot fight this test for the name.
  const suffix = `${process.pid}${Date.now().toString(36)}`;
  const USER_LOGIN = `bwrole${suffix}`;
  const USER_EMAIL = `${USER_LOGIN}@example.invalid`;

  test('a user can be created with two roles, edited down to one, and cleared', async ({ page }) => {
    await login(page);

    try {
      await page.goto('/wp-admin/user-new.php');

      await page.fill('#user_login', USER_LOGIN);
      await page.fill('#email', USER_EMAIL);
      // The address is deliberately undeliverable; a bounced welcome email adds
      // nothing to the test and a mail failure would show up as an admin notice.
      await page.locator('#send_user_notification').setChecked(false);

      // Two roles that every WordPress install has, whatever else is
      // registered alongside them.
      await page.locator(`${ROLE_BOX}[value="subscriber"]`).setChecked(true);
      await page.locator(`${ROLE_BOX}[value="contributor"]`).setChecked(true);
      // Anything else the default role left ticked would muddy the assertion.
      await page.locator(`${ROLE_BOX}[value="author"]`).setChecked(false);
      await page.locator(`${ROLE_BOX}[value="editor"]`).setChecked(false);

      await page.locator('#createusersub').click();

      // The Users list names every role a user holds, comma separated.
      await page.goto(`/wp-admin/users.php?s=${encodeURIComponent(USER_LOGIN)}`);
      const row = page.locator('tr', { hasText: USER_LOGIN }).first();
      await expect(row).toContainText('Contributor');

      // Either name will do: this is about the roles the user ended up with,
      // and the site may be showing Subscriber under its friendlier name.
      await expect(row).toContainText(/Subscriber|Basic User/);

      // ...and the edit screen ticks both of them back.
      // The username itself, not the "Edit" row action: row actions are only
      // painted on hover and never satisfy an actionability check.
      await row.getByRole('link', { name: USER_LOGIN, exact: true }).first().click();
      await expect(page.locator(`${ROLE_BOX}[value="subscriber"]`)).toBeChecked();
      await expect(page.locator(`${ROLE_BOX}[value="contributor"]`)).toBeChecked();

      // Down to one role.
      await page.locator(`${ROLE_BOX}[value="contributor"]`).setChecked(false);
      await page.locator('#bw-profile-save, #submit').first().click();
      await expect(page.locator(`${ROLE_BOX}[value="subscriber"]`)).toBeChecked();
      await expect(page.locator(`${ROLE_BOX}[value="contributor"]`)).not.toBeChecked();

      // Clearing every box is the explicit "no role for this site" case.
      await page.locator(`${ROLE_BOX}[value="subscriber"]`).setChecked(false);
      await page.locator('#bw-profile-save, #submit').first().click();
      await expect(page.locator(`${ROLE_BOX}:checked`)).toHaveCount(0);
    } finally {
      await restoreAll([['delete the test user', async () => deleteUser(page, USER_LOGIN)]]);
    }
  });
});
