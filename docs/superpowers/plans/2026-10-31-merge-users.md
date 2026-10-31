# Merge Users Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An admin merges two WordPress accounts into one, carrying over profile, content, SureCart link and LearnDash data, then deletes the spare.

**Architecture:** A merge engine (`includes/merge-users/merge.php`) with no UI: a check, a preview summary and a run. A screen (`includes/merge-users/screen.php`) adds the Users-list link, the choose/preview page and the confirm handler. Both are self-contained so they port to the theme by copying the folder.

**Tech Stack:** PHP (WordPress plugin), Playwright, plain PHP test scripts with `tests/php/stubs.php`.

**Spec:** `docs/superpowers/specs/2026-10-31-merge-users-design.md`

## Global Constraints

- Feature key `merge_users`, `'default' => '0'`, `'guide' => false`, section `security`.
- Admins only: `delete_users` and `edit_users`, and `blueworx_readonly_current_user()` is null. Enforced in the handler, not only by hiding the link.
- Not offered on multisite (`is_multisite()`): deleting there removes the person from the whole network. (My call.)
- New CSS classes `blueworx-merge-users__*`, never `bw-`. Icons via `data-lucide`, notices via `blueworx_ds_notice()`.
- No emails to the person: `send_email_change_email` and `send_password_change_email` return false during a run.
- SureCart and LearnDash are optional: missing tables or meta are skipped, never an error.
- Minor version bump (1.97.0) and changelog.

## Review Focus

1. Spare's email chosen: the spare must stop holding that email before the kept account takes it, without SureCart pushing a placeholder email to the spare's customer. Test: kept ends with the spare's email and the run succeeds.
2. Refused merges change nothing: same account twice, merging away yourself, a read-only/support/external account, both linked to a SureCart customer in the same mode. Test each leaves both users and their meta untouched.
3. Kept already has a value: kept's value wins for every meta key. Test a shared key keeps kept's value.
4. Spare's posts by a custom post type and its comments end up on the kept account. Test both.
5. A stale form (spare already deleted in another tab) gives a notice, not a fatal error. Test: posting a merge for a missing user shows an error notice.

---

### Task 1: Merge engine — checks, meta, content, SureCart, email, delete

**Files:**
- Create: `includes/merge-users/merge.php`
- Modify: `blueworx-labs-wordpress.php` (require after `includes/external-access.php`)
- Modify: `includes/features.php` (registry entry)
- Create: `tests/fixtures/merge-users.php`
- Create: `tests/merge-users.spec.js` (engine section)

**Interfaces:**
- Produces:
  - `blueworx_merge_users_check( int $kept_id, int $spare_id, int $actor_id ): true|WP_Error` — codes `merge_users_missing`, `merge_users_same`, `merge_users_self`, `merge_users_readonly`, `merge_users_surecart`, `merge_users_multisite`.
  - `blueworx_merge_users_preview( int $kept_id, int $spare_id ): array` — keys `kept`, `spare` (WP_User), `emails` (`kept`, `spare`), `meta_filled` (string[] keys), `roles_added` (string[]), `posts` (int), `comments` (int), `courses_added` (int[]), `courses_progress_from_spare` (int[]), `surecart` (array mode => customer id the kept account ends with), `surecart_conflict` (array mode => [kept id, spare id]).
  - `blueworx_merge_users_run( int $kept_id, int $spare_id, string $email_from, int $actor_id ): array|WP_Error` — `$email_from` is `'kept'` or `'spare'`; returns the preview array that was applied.
  - `blueworx_merge_users_skip_meta( string $key ): bool`.
  - Action `blueworx_merge_users` (`$kept_id, $spare_id`) fired before the spare is deleted.
  - Meta `blueworx_merge_log` (non-unique) on the kept account: array `time`, `actor`, `spare_id`, `spare_login`, `spare_email`, `email_from`.

- [ ] **Step 1: Fixture.** `php tests/fixtures/merge-users.php <wp-load> seed|inspect <kept> <spare>|cleanup`. `seed` creates `merge_kept` (subscriber, first_name "Kept", `bw_shared`=`kept`) and `merge_spare` (subscriber + author, first_name "Spare", last_name "Person", `bw_shared`=`spare`, `bw_only_spare`=`yes`, `session_tokens`, `_application_passwords`, `sc_customer_ids` `{live: 'cus_spare'}`), a `page` and a `post` by spare, a comment by spare; prints `{"kept":id,"spare":id}`. `inspect` prints JSON: whether spare exists, kept email, kept roles, kept meta for those keys, post authors, comment user. `cleanup` deletes anything named `merge_*`. Same wp-load bootstrap as `tests/fixtures/external-viewer.php`.
- [ ] **Step 2: Failing tests** in `tests/merge-users.spec.js` that drive the engine through the fixture plus a `run` command on it (`run <kept> <spare> <email_from>` calls `blueworx_merge_users_run` as user 1 and prints the result or the WP_Error code):
  - `carries everything across and deletes the spare` — spare gone; kept `first_name` = "Kept", `last_name` = "Person", `bw_shared` = "kept", `bw_only_spare` = "yes"; roles include `author`; both posts and the comment belong to kept; `sc_customer_ids.live` = `cus_spare`; `session_tokens` and `_application_passwords` are not copied; `blueworx_merge_log` has one entry.
  - `takes the spare's email when asked` — kept email equals spare's original email.
  - `refuses a merge with itself`, `refuses merging away the person doing it` (spare = 1), `refuses a support or external account`, `refuses two SureCart customers in the same mode` — each returns its code and `inspect` shows nothing changed.
- [ ] **Step 3: Run and see them fail** — `npx playwright test tests/merge-users.spec.js` against the harness; expect fixture `run` to report the function is undefined.
- [ ] **Step 4: Implement** the five functions above. Run order, so a failure part-way leaves the spare intact and a retry is safe: check → LearnDash (Task 2 hook point: call `blueworx_merge_users_learndash( $kept_id, $spare_id )` if defined) → SureCart link (add spare's modes the kept lacks, then delete spare's `sc_customer_ids`) → meta fill (every spare key not skipped and empty on kept; multi-value keys copied value by value) → `user_url` if empty → roles → comments (`$wpdb->update` on `comments.user_id`) → action → email (if spare: set spare's `user_email` to `merged-{ID}@invalid` with `$wpdb->update` then `clean_user_cache`, then `wp_update_user` kept) → log → `wp_delete_user( $spare_id, $kept_id )`. Skip list: `{prefix}capabilities`, `{prefix}user_level`, `{prefix}user-settings`, `{prefix}user-settings-time`, `{prefix}persisted_preferences`, `{prefix}dashboard_quick_press_last_post_id`, `session_tokens`, `_application_passwords`, `default_password_nag`, `sc_customer_ids`, `_sfwd-course_progress`, `_sfwd-quizzes`, `community-events-location`; prefixes `blueworx_sso_`, `_blueworx_external_`, `_blueworx_view_as`, `blueworx_merge_`, `manage`, `metaboxhidden_`, `closedpostboxes_`, `meta-box-order_`, `screen_layout_`. Registry entry per Global Constraints.
- [ ] **Step 5: Run tests, see them pass.**
- [ ] **Step 6: Commit** "Add the merge engine".

### Task 2: LearnDash

**Files:**
- Modify: `includes/merge-users/merge.php`
- Create: `tests/php/merge-users-test.php`; add it to `test:php` in `package.json`
- Modify: `tests/fixtures/merge-users.php`, `tests/merge-users.spec.js`

**Interfaces:**
- Produces: `blueworx_merge_users_course_progress( array $kept, array $spare ): array`, `blueworx_merge_users_quizzes( array $kept, array $spare ): array`, `blueworx_merge_users_learndash( int $kept_id, int $spare_id ): void`.

- [ ] **Step 1: Failing unit tests** (`tests/php/merge-users-test.php`, stub style of `tests/php/users-test.php`): a course only the spare has is added; where both have a course, the one with `completed` higher wins, and a completed one (`completed >= total`) beats an unfinished one; kept wins a tie; quizzes are kept's attempts followed by spare's, with exact duplicates (same `quiz`, `time`) dropped.
- [ ] **Step 2: Run** `php tests/php/merge-users-test.php` — fails, functions undefined.
- [ ] **Step 3: Implement** the two pure functions, then `blueworx_merge_users_learndash()`: write merged `_sfwd-course_progress` and `_sfwd-quizzes` to kept; reassign `user_id` from spare to kept in `{prefix}learndash_user_activity`, `{prefix}learndash_pro_quiz_statistic_ref` and `{prefix}learndash_pro_quiz_toplist`, each only if `SHOW TABLES LIKE` finds it. Access, completion and group meta (`course_*_access_from`, `course_completed_*`, `learndash_group_users_*`, `learndash_group_leaders_*`) need nothing extra: the Task 1 meta fill combines them. Preview gains `courses_added` and `courses_progress_from_spare`.
- [ ] **Step 4: Spec test** `combines LearnDash courses and progress`: fixture seeds kept with course 101 at 1/4, spare with course 101 at 3/4 and course 202 plus `course_202_access_from`, and creates a `learndash_user_activity` table with one row for spare; after a run kept has 101 at 3/4, 202 present, access meta present, the activity row on kept.
- [ ] **Step 5: Run both tests, see them pass.**
- [ ] **Step 6: Commit** "Carry LearnDash progress across a merge".

### Task 3: The screen

**Files:**
- Create: `includes/merge-users/screen.php`
- Modify: `assets/css/admin-additions.css` (only if the design system lacks a two-column compare; classes `blueworx-merge-users__*`)
- Modify: `tests/merge-users.spec.js`

**Interfaces:**
- Consumes: Task 1 and 2 functions.
- Produces: page slug `blueworx-merge-users` under Users (hidden from the menu, `add_submenu_page( '', … )`), URL args `user` (the account you started from) and `other`; admin-post action `blueworx_merge_users` with nonce `blueworx_merge_users_{kept}_{spare}`; row action key `blueworx_merge`.

- [ ] **Step 1: Failing UI tests:**
  - `the Merge link only appears when the function is on` (setFeature off → no `Merge` link in a row; on → present, but not on your own row).
  - `merging from the Users list` — click Merge on `merge_spare`, pick `merge_kept` by searching its username, choose "Keep merge_kept", preview lists the email choice, "Spare" first name gap, 2 posts, 1 comment and a warning that `merge_spare` will be deleted; tick "Use merge_spare's email", Confirm; lands on kept's profile with a success notice; `inspect` matches.
  - `a blocked merge says why and offers no button` — seed both with SureCart live customers; preview shows an error notice naming both customer IDs, no Confirm button.
  - `a stale form fails gently` — submit, delete spare via fixture first, expect an error notice, no PHP error.
  - `not offered to an external viewer` — fixture `external-viewer.php` sign-in; no Merge link and a direct GET of the page is refused.
- [ ] **Step 2: Run, see them fail.**
- [ ] **Step 3: Implement.** Choose step: a `<select>` of up to 200 other users ordered by login, plus a search field filtering it (`get_users( [ 'search' => "*{$q}*", 'number' => 200 ] )`) — fine for WSO's size. Keep choice is a radio of the two accounts, defaulting to the one you did not start from. Preview: `blueworx_ds_card` per section, `blueworx_ds_notice` for the delete warning and for any check error. Confirm posts to admin-post; on WP_Error redirect back with the code and show the message; on success redirect to `user-edit.php?user_id={kept}&blueworx_merged=1` and show a notice there.
- [ ] **Step 4: Run tests, see them pass.**
- [ ] **Step 5: Commit** "Add the merge users screen".

### Task 4: Release

- [ ] Bump 1.96.11 → 1.97.0 in the plugin header, version constant, `package.json`, `readme.txt`; changelog "Added" entry in plain words. `npm run version:check` passes.
- [ ] Run once: `vendor/bin/phpcs -q includes/merge-users`, `npx eslint tests/merge-users.spec.js`, `npm run test:php`. Report findings; don't loop.
- [ ] Commit, push, open the PR.
