/**
 * Merging two accounts into one.
 *
 * The engine is driven straight through tests/fixtures/merge-users.php, which
 * seeds both accounts and reads back what they hold, so each rule here is
 * checked against the database rather than against what a screen claims.
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, LOGIN_PATH, cacheBust, login } from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Runs one fixture command and parses its JSON answer.
 *
 * @param {...(string|number)} args Command and its arguments.
 * @return {any} Parsed output.
 */
function fixture(...args) {
  const file = path.join(__dirname, 'fixtures', 'merge-users.php');
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  const out = execFileSync('php', [file, wpLoad, ...args.map(String)], { encoding: 'utf8' }).trim();
  const last = out.split('\n').pop();

  try {
    return JSON.parse(last);
  } catch {
    return last;
  }
}

test.describe('Merge users — the engine', () => {
  test.skip(isPlaceholder || !ADMIN_USER || !ADMIN_PASS, 'No test site configured.');

  test.afterAll(() => {
    fixture('cleanup');
  });

  test('carries everything across and deletes the spare', () => {
    const { kept, spare } = fixture('seed');

    const result = fixture('run', kept, spare, 'kept');
    expect(result.ok).toBe(true);
    expect(result.mails, 'nobody is emailed').toBe(0);
    // SureCart copies a WordPress account's email onto its customers when the
    // account is saved. The customer that came from the spare has to hear the
    // kept address, and the spare must never be saved with a placeholder.
    expect(result.updated, 'the kept account is saved, so SureCart syncs it').toContain(kept);
    expect(result.updated, 'the spare is never saved').not.toContain(spare);

    const state = fixture('inspect', kept, spare);
    expect(state.spare, 'the spare account is deleted').toBeNull();

    const meta = state.kept.meta;
    expect(state.kept.email).toBe('merge-kept@example.invalid');
    expect(meta.first_name, "the kept account's own name wins").toBe('Kept');
    expect(meta.last_name, 'a gap is filled from the spare').toBe('Person');
    expect(meta.bw_shared, "the kept account's value wins").toBe('kept');
    expect(meta.bw_only_spare).toBe('yes');
    expect(state.kept.url).toBe('https://spare.example.invalid');
    expect(state.kept.roles).toEqual(expect.arrayContaining(['subscriber', 'author']));
    expect(state.kept.posts, 'a page and a post move across').toBe(2);
    expect(state.kept.comments).toBe(1);
    expect(meta.sc_customer_ids).toEqual({ live: 'cus_spare' });
    expect(meta.session_tokens, 'sign-ins are never copied').toBe('');
    expect(meta._application_passwords, 'app passwords are never copied').toBe('');
    expect(meta.view_as, '"view as" is never copied').toBe('');
    expect(meta._new_email, "a pending email change can't follow").toBe('');
    expect(meta.blueworx_merge_log).toHaveLength(1);
  });

  test("takes the spare's email when asked", () => {
    const { kept, spare } = fixture('seed');

    const result = fixture('run', kept, spare, 'spare');
    expect(result.ok).toBe(true);
    expect(result.mails, 'nobody is emailed').toBe(0);
    expect(result.updated, 'the spare is never saved with a placeholder').not.toContain(spare);
    expect(fixture('inspect', kept, spare).kept.email).toBe('merge-spare@example.invalid');
  });

  test("a SureCart customer from the spare is told the kept account's email", () => {
    // Nothing else about the kept account changes here, so only a deliberate
    // save tells SureCart which address its customer now belongs to.
    const { kept, spare } = fixture('seed', 'no-url');

    const result = fixture('run', kept, spare, 'kept');
    expect(result.ok).toBe(true);
    expect(result.updated).toContain(kept);
  });

  test('a retry never hands the kept account a placeholder email', () => {
    const { kept, spare } = fixture('seed');
    fixture('placeholder', spare);

    expect(fixture('run', kept, spare, 'spare').ok).toBe(true);
    expect(fixture('inspect', kept, spare).kept.email).toBe('merge-kept@example.invalid');
  });

  test('someone who cannot hand out roles cannot gain them by merging', () => {
    const { kept, spare, manager } = fixture('seed', 'manager');

    expect(fixture('run', kept, spare, 'kept', manager)).toEqual({ error: 'merge_users_roles' });
    expect(fixture('inspect', kept, spare).spare, 'nothing was deleted').not.toBeNull();
  });

  for (const [name, variant, pick, code] of [
    ['refuses a merge with itself', '', (ids) => [ids.kept, ids.kept, 1], 'merge_users_same'],
    ['refuses merging away the person doing it', '', (ids) => [ids.kept, ids.spare, ids.spare], 'merge_users_self'],
    ['refuses a support or external account', 'support', (ids) => [ids.kept, ids.spare, 1], 'merge_users_readonly'],
    ['refuses two SureCart customers in the same mode', 'surecart-both', (ids) => [ids.kept, ids.spare, 1], 'merge_users_surecart'],
    ['refuses an account that no longer exists', '', (ids) => [ids.kept, ids.spare + 100000, 1], 'merge_users_missing'],
  ]) {
    test(name, () => {
      const ids = fixture('seed', variant);
      const [kept, spare, actor] = pick(ids);

      expect(fixture('run', kept, spare, 'kept', actor)).toEqual({ error: code });

      const state = fixture('inspect', ids.kept, ids.spare);
      expect(state.spare, 'nothing was deleted').not.toBeNull();
      expect(state.kept.meta.bw_only_spare, 'nothing was copied').toBe('');
      expect(state.spare.posts).toBe(2);
    });
  }

  test('combines LearnDash courses and progress', () => {
    const { kept, spare } = fixture('seed', 'learndash');

    expect(fixture('run', kept, spare, 'kept').ok).toBe(true);

    const state = fixture('inspect', kept, spare);
    const progress = state.kept.meta['_sfwd-course_progress'];
    expect(state.kept.meta.course_101_access_from, 'the later access date wins, so access is not cut short').toBe('1700000000');
    expect(state.kept.meta.learndash_course_expired_101, 'and the kept account is no longer marked expired').toBe('');
    expect(state.kept.meta.learndash_course_expired_303, "the spare's expired mark never locks out the kept account").toBe('');
    expect(state.quiz_stats_kept, "quiz records in LearnDash's older tables move too").toBe(1);
    expect(progress[101].completed, 'the further-along progress wins').toBe(3);
    expect(progress[202], 'a course only the spare had comes across').toBeTruthy();
    expect(state.kept.meta.course_202_access_from, 'and so does access to it').toBe('1700000000');
    expect(state.activity_kept, 'activity records move to the kept account').toBe(1);
  });
});

/**
 * The merge screen for a pair of accounts, with the kept one already chosen.
 *
 * @param {number} from  Account the merge was started from.
 * @param {number} other The other account.
 * @return {string} Path.
 */
function previewPath(from, other) {
  return `/wp-admin/admin.php?page=blueworx-merge-users&user=${from}&other=${other}&keep=other`;
}

test.describe('Merge users — the screen', () => {
  test.skip(isPlaceholder || !ADMIN_USER || !ADMIN_PASS, 'No test site configured.');

  test.beforeAll(() => {
    fixture('feature', 'on');
  });

  test.afterAll(() => {
    fixture('cleanup');
    fixture('feature', 'off');
  });

  test('the Merge link only appears when the function is on', async ({ page }) => {
    fixture('seed');
    await login(page);

    fixture('feature', 'off');
    await page.goto('/wp-admin/users.php?s=merge_spare');
    await expect(page.locator('tr', { hasText: 'merge_spare' }).first()).toBeVisible();
    await expect(page.locator('.row-actions .blueworx_merge')).toHaveCount(0);

    fixture('feature', 'on');
    await page.goto('/wp-admin/users.php?s=merge_spare');
    await expect(page.locator('tr', { hasText: 'merge_spare' }).locator('.row-actions .blueworx_merge a')).toHaveCount(1);
  });

  test('merging from the Users list', async ({ page }) => {
    const { kept, spare } = fixture('seed');
    await login(page);

    await page.goto('/wp-admin/users.php?s=merge_spare');
    const row = page.locator('tr', { hasText: 'merge_spare' });
    await row.hover();
    await row.locator('.row-actions .blueworx_merge a').click();

    await page.fill('#blueworx_merge_users_q', 'merge_kept');
    await page.getByRole('button', { name: 'Search' }).click();
    await page.selectOption('#blueworx_merge_users_other', String(kept));
    await page.getByRole('button', { name: 'Continue' }).click();

    const preview = page.locator('.blueworx-merge-users__preview');
    await expect(preview).toContainText('merge_kept');
    await expect(preview, 'profile fields are named in plain words').toContainText('Last name');
    await expect(page.locator('.blueworx-merge-users__confirm')).toContainText('merge_spare will be deleted');

    await page.getByLabel('merge-spare@example.invalid (from merge_spare)').check();
    await page.getByRole('button', { name: 'Merge accounts' }).click();

    await expect(page.getByText('Accounts merged.')).toBeVisible();

    const state = fixture('inspect', kept, spare);
    expect(state.spare).toBeNull();
    expect(state.kept.email).toBe('merge-spare@example.invalid');
    expect(state.kept.posts).toBe(2);
  });

  test('a blocked merge says why and offers no button', async ({ page }) => {
    const { kept, spare } = fixture('seed', 'surecart-both');
    await login(page);

    await page.goto(previewPath(spare, kept));
    await expect(page.getByText('These accounts cannot be merged.')).toBeVisible();
    await expect(page.locator('.bw-admin')).toContainText('cus_kept');
    await expect(page.getByRole('button', { name: 'Merge accounts' })).toHaveCount(0);
  });

  test('a stale form fails gently', async ({ page }) => {
    const { kept, spare } = fixture('seed');
    await login(page);

    await page.goto(previewPath(spare, kept));
    await expect(page.getByRole('button', { name: 'Merge accounts' })).toBeVisible();

    fixture('delete', spare);
    await page.getByRole('button', { name: 'Merge accounts' }).click();

    await expect(page.getByText('The merge did not happen.')).toBeVisible();
    await expect(page.locator('.bw-admin')).toContainText('no longer exists');
  });

  test('not offered to an external viewer', async ({ page }) => {
    const { kept, spare } = fixture('seed');
    const viewer = path.join(__dirname, 'fixtures', 'external-viewer.php');
    const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
    execFileSync('php', [viewer, wpLoad, 'create']);

    try {
      await page.goto(cacheBust(LOGIN_PATH));
      await page.fill('#user_login', 'bw_external_test');
      await page.fill('#user_pass', 'ExternalTest!2026');
      await page.click('#wp-submit');
      await page.waitForURL(/wp-admin/);

      await page.goto('/wp-admin/users.php?s=merge_spare');
      await expect(page.locator('.row-actions .blueworx_merge')).toHaveCount(0);

      const response = await page.goto(previewPath(spare, kept));
      expect(response.status()).toBe(403);
      await expect(page.getByRole('button', { name: 'Merge accounts' })).toHaveCount(0);
    } finally {
      execFileSync('php', [viewer, wpLoad, 'delete']);
    }
  });
});
