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
    expect(meta.blueworx_merge_log, "the spare's own earlier merges come too").toHaveLength(2);
    const log = meta.blueworx_merge_log.find((entry) => entry.spare_login === 'merge_spare');
    expect(log.kept_email, 'the log keeps the address the kept account started with').toBe('merge-kept@example.invalid');
    expect(result.comment_owner, 'a cached comment hears that it moved').toBe(kept);
  });

  test('moves posts of every type, even ones no plugin registers any more', () => {
    const { kept, spare } = fixture('seed', 'cpt');

    expect(fixture('run', kept, spare, 'kept').ok).toBe(true);
    expect(fixture('inspect', kept, spare).kept.posts).toBe(3);
  });

  test('an empty SureCart customer on the kept account is not a clash', () => {
    const { kept, spare } = fixture('seed', 'surecart-empty');

    expect(fixture('run', kept, spare, 'kept').ok).toBe(true);
    expect(fixture('inspect', kept, spare).kept.meta.sc_customer_ids).toEqual({ live: 'cus_spare' });
  });

  test('a merge that stops part-way says so and leaves the spare as it was', () => {
    const { kept, spare } = fixture('seed');
    fixture('claim', 'merge-spare@example.invalid');

    expect(fixture('run', kept, spare, 'spare')).toEqual({ error: 'merge_users_email', started: true });

    const state = fixture('inspect', kept, spare);
    expect(state.spare, 'nothing was deleted').not.toBeNull();
    expect(state.spare.email, 'the spare gets its address back').toBe('merge-spare@example.invalid');
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
    ['refuses keeping a support or external account', 'readonly-kept', (ids) => [ids.kept, ids.spare, 1], 'merge_users_readonly'],
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
      expect(state.spare.meta.sc_customer_ids, 'the SureCart link stays where it was').toEqual({ live: 'cus_spare' });
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
    expect(state.activity_kept, 'activity records move, one per course when both took it').toBe(2);
    expect(state.activity_101_status, 'and the finished one wins').toBe(1);
    expect(state.activity_meta_orphans, 'nothing is left pointing at a removed record').toBe(0);
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
    for (const id of ['cus_kept', 'cus_spare']) {
      await expect(page.locator(`a[href*="page=sc-customers"][href*="id=${id}"]`), `links to ${id} in SureCart`).toHaveCount(1);
    }
  });

  test('the preview names LearnDash groups and course access in words', async ({ page }) => {
    const { kept, spare } = fixture('seed', 'learndash');
    await login(page);

    await page.goto(previewPath(spare, kept));
    const preview = page.locator('.blueworx-merge-users__preview');
    await expect(preview).toContainText('LearnDash group: merge_fixture group');
    await expect(preview).toContainText('Course access:');
    await expect(preview).not.toContainText('learndash_group_users_');
    await expect(preview).not.toContainText('_access_from');
  });

  test('warns before an account becomes an administrator', async ({ page }) => {
    const { kept, spare } = fixture('seed', 'admin-spare');
    await login(page);

    await page.goto(previewPath(spare, kept));
    await expect(page.getByText('merge_kept will become an administrator')).toBeVisible();
  });

  test('a merge that stops part-way says so', async ({ page }) => {
    const { kept, spare } = fixture('seed');
    await login(page);

    await page.goto(previewPath(spare, kept));
    fixture('claim', 'merge-spare@example.invalid');
    await page.getByLabel('merge-spare@example.invalid (from merge_spare)').check();
    await page.getByRole('button', { name: 'Merge accounts' }).click();

    await expect(page.getByText('The merge stopped part-way.')).toBeVisible();
    await expect(page.locator('.bw-admin')).toContainText('safe to try again');
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

      const post = await page.request.post('/wp-admin/admin-post.php', {
        form: { action: 'blueworx_merge_users', kept: String(kept), spare: String(spare), email_from: 'kept' },
      });
      expect(post.status(), 'a hand-made request is turned away too').toBe(403);
      expect(fixture('inspect', kept, spare).spare, 'nothing was deleted').not.toBeNull();
    } finally {
      execFileSync('php', [viewer, wpLoad, 'delete']);
    }
  });
});
