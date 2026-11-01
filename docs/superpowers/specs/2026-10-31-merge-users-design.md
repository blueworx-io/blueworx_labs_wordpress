# Merge users

Forge: wrk_065d154981944201d4d9defd37

## Why

Some people end up with two accounts on a site. Today Luke fixes it by hand: copies one account's details onto the other, then deletes the spare. That's slow and things get missed, especially courses and purchases. This tool does it in one go.

First site: World Squash Officiating (WSO), which uses WordPress, SureCart and LearnDash.

## Where it lives

In this plugin, as a deliberate exception to the fixes-only freeze, because WSO can't move to the theme yet. It gets ported to the theme repo afterwards, so keep it self-contained in its own file and don't lean on plugin-only internals that the theme won't have.

## Goals

- An admin picks two accounts, chooses which to keep, and merges them.
- Carry over as much as possible from the spare account: profile, content, SureCart link, LearnDash enrolments and progress.
- Email is the most important detail. The person merging chooses which email the kept account ends up with.
- The spare account is deleted at the end.

## Non-goals

- Merging two SureCart customers. SureCart holds orders and subscriptions on its own servers and can't move them between customers.
- Undo. The spare is deleted, not parked.
- Merging more than two accounts at once.
- Bulk or automatic duplicate detection.

## Anti-goals (a failure even if it "works")

- Anyone losing access to an order, subscription, course or certificate they had before.
- A half-finished merge: some things moved, then an error, spare left in a broken state.
- Read-only, support-access or external viewers being able to run it.

## How it behaves

1. **Off by default.** A new feature in the registry with `'default' => '0'` and `'guide' => false`, in the Content or Security section (builder's call).
2. **Start.** A "Merge" link on each row of the Users list, admins only (`delete_users` and `edit_users`). Never shown to read-only or external viewers, and the merge request must be refused for them on the server too, not just hidden.
3. **Choose.** A screen showing the account you started from, a search to pick the other account, and a choice of which one to keep.
4. **Preview.** Before anything changes, show side by side what each account has and what will happen:
   - which email the kept account will end up with (a choice between the two, defaulting to the kept account's)
   - profile fields that will be filled in from the spare
   - number of posts and comments moving
   - LearnDash courses and groups being added, and which progress wins where both have the same course
   - SureCart: which customer link the kept account ends up with
   - a clear warning that the spare account will be deleted
5. **Merge.** One confirm button, with a nonce. Then a done notice on the kept account's profile.

## What moves

**WordPress**
- Username and password stay as the kept account's. Usernames can't change.
- Email: whichever was chosen in the preview. The spare's email has to be freed first (spare is deleted, or its email changed to a placeholder before the swap) because WordPress won't allow two accounts with the same email.
- Profile fields and other user meta: copy every key the kept account doesn't already have a value for; the kept account's value always wins. (Luke wants as much carried over as possible.) Skip list, never copied (my call): capabilities and user level, session tokens, application passwords, single sign-on links and tokens, view-as-role state, support and external access state (invites, expiry), dashboard and screen preferences. LearnDash and SureCart keys follow their own rules below, not this one.
- Roles: the kept account gets any roles the spare had as well, shown in the preview. Never add `administrator` this way without it standing out in the preview.
- No emails are sent to the person (Luke's answer). Suppress WordPress's own "email changed" and "password changed" notices during the merge.
- Posts of any type and comments: reassigned to the kept account. `wp_delete_user( $spare, $kept )` covers posts; comments need moving separately.

**SureCart**
- The WordPress account links to a SureCart customer through user meta (believed to be `sc_customer_ids`, one per live/test mode; builder to confirm).
- If only the spare has a customer in a mode, the kept account takes over that link.
- If only the kept account has one, nothing changes.
- **If both have a customer in the same mode, the merge is blocked** before anything changes. The preview says so and links to both customers in SureCart so they can be sorted there. Builder to confirm whether a customer with no orders, subscriptions or licences can safely be treated as "has nothing" and not block.
- After the email changes, make sure the SureCart customer's email follows (SureCart may sync this itself; builder to check).

**LearnDash**
- Course access, group membership and group leadership: combined, so the kept account has everything either had.
- Course and quiz progress: where both did the same course, the further-along progress wins. Completed beats in progress.
- Activity records (the LearnDash activity tables): moved to the kept account, so reports, completion dates and certificates still work.
- Builder to confirm the exact meta keys and tables against the LearnDash version on WSO.

**Anything else**
- Fire an action (e.g. `blueworx_merge_users`, passing kept and spare IDs) before the spare is deleted, so other plugins can move their own data later.

## Safety

- Run every check (permissions, SureCart conflict, both accounts still exist, not the same account, not merging yourself away) before changing anything.
- Do the database work in an order where a failure part-way leaves the spare still existing and intact, so the admin can see and retry. Delete the spare last.
- Log the merge (who, when, kept and spare IDs and emails) somewhere an admin can find it later. Builder's call where: the kept account's user meta is fine.
- Never allow merging away the account you're logged in as.
- Refuse the merge if either account is a support, read-only or external account (my call). Those roles decide who is read-only, so merging one in could quietly turn an admin read-only or carry an access expiry across.

## Constraints

- New CSS classes use `blueworx-merge-users__*`, never `bw-`. Icons via `data-lucide`, notices via `blueworx_ds_notice()`.
- Works with LearnDash or SureCart missing: those parts are skipped, not errors.
- Playwright tests on the local harness. LearnDash and SureCart are probably not on the harness, so cover the WordPress part end to end and the SureCart/LearnDash rules with whatever the harness allows; say what couldn't be tested.
- Minor version bump and changelog.

## Left to the builder

- Exact screen layout (follow the existing admin design system).
- Which settings section it sits in.
- How the account search works.
- Whether the merge runs in one request or steps.
