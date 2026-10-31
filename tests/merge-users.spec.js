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
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS } from './helpers.js';

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
  test.describe.configure({ mode: 'serial' });

  test.afterAll(() => {
    fixture('cleanup');
  });

  test('carries everything across and deletes the spare', () => {
    const { kept, spare } = fixture('seed');

    expect(fixture('run', kept, spare, 'kept')).toEqual({ ok: true });

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
    expect(meta.blueworx_merge_log).toHaveLength(1);
  });

  test("takes the spare's email when asked", () => {
    const { kept, spare } = fixture('seed');

    expect(fixture('run', kept, spare, 'spare')).toEqual({ ok: true });
    expect(fixture('inspect', kept, spare).kept.email).toBe('merge-spare@example.invalid');
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

    expect(fixture('run', kept, spare, 'kept')).toEqual({ ok: true });

    const state = fixture('inspect', kept, spare);
    const progress = state.kept.meta['_sfwd-course_progress'];
    expect(progress[101].completed, 'the further-along progress wins').toBe(3);
    expect(progress[202], 'a course only the spare had comes across').toBeTruthy();
    expect(state.kept.meta.course_202_access_from, 'and so does access to it').toBe('1700000000');
    expect(state.activity_kept, 'activity records move to the kept account').toBe(1);
  });
});
