# Admin UI Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the BlueWorx top bar covering the block editor toolbar, restore hover fly-out submenus in the admin sidebar, and bring the profile screen's spacing and structure up to the reference design.

**Architecture:** Three independent fixes in the existing admin re-skin. Two are CSS-only or CSS-plus-a-small-script in `assets/css/admin-theme.css`; the third extends `assets/js/profile-redesign.js`, which already restructures the native profile form by **moving** (never cloning) its inputs so core's save handler is untouched. That invariant must survive every change here.

**Tech Stack:** Plain CSS and vanilla JS (no jQuery — the codebase deliberately avoids it), Playwright for tests.

**Spec:** `docs/superpowers/specs/2026-07-27-admin-access-and-ui-design.md` §2–§4

## Global Constraints

- No jQuery, no new dependencies — `approved-deps.json` is not touched
- ESLint must pass: `npx eslint assets/js`
- Design tokens come from `:root` in `assets/css/admin-theme.css` (`--bw-topbar-h`, `--bw-sidebar-w`, `--bw-border`, `--bw-radius-card`, …). Never hard-code a value that already has a token.
- Every change is gated behind the existing `admin_theme` feature — turning it off must return the stock WordPress appearance
- Text domain `blueworx-labs-wordpress` on every user-facing string
- Accessibility (per `CLAUDE.md`): keyboard access is not optional. Anything driven by hover must also work on focus.
- **Three separate PRs**, one per task group: 1.39.0 (editor), 1.40.0 (fly-outs), 1.41.0 (profile). Each carries its own version bump across `blueworx-labs-wordpress.php`, `BLUEWORX_LABS_VERSION`, `package.json`, `readme.txt` and `CHANGELOG.md`, and must pass `npm run version:check`.

## Test Harness

```bash
node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up \
  --plugin . --slug blueworx-labs-wordpress --dir .wp-test --port 8892

PLAYWRIGHT_BASE_URL=http://127.0.0.1:8892 \
WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw WP_LOGIN_PATH=admin_login \
npx playwright test tests/admin-theme.spec.js
```

Specs import `test` from `./helpers.js`, **never** from `@playwright/test`.

---

## Group A — Block editor overlap (v1.39.0)

### Task A1: Establish which editor state actually reproduces the bug

Do this before writing any fix. WordPress persists fullscreen mode per user and has shipped it enabled by default in some versions, so the reported symptom may be occurring in fullscreen — where the spec's chosen fix deliberately does not apply. Fixing the wrong state would look like a fix and change nothing.

**Files:**
- Create: nothing. This is investigation.

- [ ] **Step 1: Open the editor on the harness in each state**

```bash
node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up \
  --plugin . --slug blueworx-labs-wordpress --dir .wp-test --port 8892
```

Sign in at `http://127.0.0.1:8892/admin_login` as `admin` / `wptest-admin-pw`, open `/wp-admin/post-new.php`, and check the editor's Options (⋮) menu for whether **Fullscreen mode** is ticked.

- [ ] **Step 2: Record which state overlaps**

In the browser console, with the editor open:

```js
const bar = document.querySelector('.bw-topbar').getBoundingClientRect();
const skeleton = document.querySelector('.interface-interface-skeleton').getBoundingClientRect();
console.log({ barBottom: bar.bottom, skeletonTop: skeleton.top, overlaps: skeleton.top < bar.bottom });
```

Repeat with fullscreen toggled the other way.

- [ ] **Step 3: Decide**

- Overlaps in **normal** mode only → proceed to Task A2 as written.
- Overlaps in **fullscreen** too → stop and raise it. The spec's decision was to leave fullscreen alone; if that is the state the user actually hits, the decision needs revisiting with them rather than being quietly reversed here.

- [ ] **Step 4: Commit**

Nothing to commit. Record the finding in the PR description.

---

### Task A2: Offset the editor skeleton below the top bar

**Files:**
- Modify: `assets/css/admin-theme.css` (Responsive block, after the `#adminmenu { margin-top: 0; }` rule at ~line 822)
- Test: `tests/admin-theme.spec.js`

- [ ] **Step 1: Write the failing test**

Append to `tests/admin-theme.spec.js`:

```js
test('the BlueWorx top bar does not cover the block editor toolbar', async ({ page }) => {
  await login(page);
  await page.goto('/wp-admin/post-new.php');

  // Leave fullscreen if WordPress remembered it on — the fix deliberately does
  // not apply there, so asserting in fullscreen would test the wrong state.
  await page.evaluate(() => {
    const { select, dispatch } = window.wp.data;
    if (select('core/edit-post').isFeatureActive('fullscreenMode')) {
      dispatch('core/edit-post').toggleFeature('fullscreenMode');
    }
  });

  const skeleton = page.locator('.interface-interface-skeleton');
  await expect(skeleton).toBeVisible();

  const bar = await page.locator('.bw-topbar').boundingBox();
  const editor = await skeleton.boundingBox();

  expect(editor.y).toBeGreaterThanOrEqual(bar.y + bar.height);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run the spec command.
Expected: FAIL — the editor's top is 32, the bar's bottom is 60.

- [ ] **Step 3: Write minimal implementation**

Inside the existing `@media only screen and (min-width: 783px)` block in `assets/css/admin-theme.css`, after the `#adminmenu { margin-top: 0; }` rule:

```css
	/* The block and site editors render a fixed full-app skeleton anchored to
	   core's admin-bar height (32px). The BlueWorx bar is 60px and sits above it
	   in the stacking order, so without this the bar paints straight over the
	   editor's own toolbar and its controls cannot be clicked.

	   Fullscreen mode is deliberately excluded: it sets the skeleton to top:0 on
	   purpose, and overriding that would be the plugin second-guessing an
	   explicit user action to clear the chrome away. */
	body.block-editor-page:not(.is-fullscreen-mode) .interface-interface-skeleton {
		top: var(--bw-topbar-h);
	}
```

The site editor uses the same skeleton class and is covered by the same rule.

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS.

- [ ] **Step 5: Verify by eye**

Open `/wp-admin/post-new.php` on the harness and confirm the editor's toolbar buttons — undo, redo, block inserter, Publish — are all clickable.

- [ ] **Step 6: Bump, changelog, commit, PR**

Set `1.39.0` in the plugin header, `BLUEWORX_LABS_VERSION`, `package.json` and `readme.txt`. Add to `CHANGELOG.md`:

```markdown
## 1.39.0

### Fixed
- The BlueWorx admin top bar no longer covers the block editor's toolbar, which
  made the editor controls unreachable. Fullscreen mode is unchanged.
```

```bash
npm run version:check
git add assets/css/admin-theme.css tests/admin-theme.spec.js blueworx-labs-wordpress.php package.json readme.txt CHANGELOG.md
git commit -m "fix: stop the top bar covering the block editor toolbar (1.39.0)"
```

---

## Group B — Sidebar hover fly-outs (v1.40.0)

### Task B1: Fly-out submenus on hover and focus

**Root cause:** `assets/css/admin-theme.css:843-848` gives `#adminmenuwrap` `overflow-x: hidden; overflow-y: auto` in the expanded state so the sidebar scrolls independently. That scroll container clips the absolutely-positioned fly-outs. The fix works because plain `overflow` does **not** clip `position: fixed` descendants — only a transformed, filtered or `will-change` ancestor does — so a fixed fly-out escapes the clip while the sidebar keeps its scroll.

**Files:**
- Create: `assets/js/admin-menu-flyout.js`
- Modify: `includes/admin-theme.php` (enqueue it)
- Modify: `assets/css/admin-theme.css`
- Test: `tests/admin-theme.spec.js`

**Interfaces:**
- Produces: no PHP functions; the script is self-contained and self-invoking, matching the style of `assets/js/profile-redesign.js`

- [ ] **Step 1: Write the failing test**

```js
test('hovering a parent menu item reveals its submenu', async ({ page }) => {
  await login(page);
  await page.goto('/wp-admin/index.php');

  // Posts is not the current menu on the dashboard, so it uses the fly-out path
  // rather than the current-item accordion.
  const posts = page.locator('#menu-posts');
  await posts.hover();

  const submenu = posts.locator('.wp-submenu');
  await expect(submenu).toBeVisible();

  const box = await submenu.boundingBox();
  const viewport = page.viewportSize();
  expect(box.x).toBeGreaterThan(0);
  expect(box.y).toBeGreaterThanOrEqual(0);
  expect(box.y + box.height).toBeLessThanOrEqual(viewport.height);
});

test('keyboard focus reveals the submenu too', async ({ page }) => {
  await login(page);
  await page.goto('/wp-admin/index.php');

  await page.locator('#menu-posts > a.menu-top').focus();
  await expect(page.locator('#menu-posts .wp-submenu')).toBeVisible();
});
```

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — the submenu is in the DOM but clipped, so `toBeVisible()` fails on zero size or it sits outside the viewport.

- [ ] **Step 3: Write minimal implementation**

Create `assets/js/admin-menu-flyout.js`:

```js
/**
 * Sidebar fly-out submenus for the expanded admin menu.
 *
 * The expanded sidebar scrolls independently (#adminmenuwrap carries
 * overflow-y: auto in admin-theme.css), and that scroll container clips the
 * absolutely-positioned fly-outs core renders for non-current items — so
 * hovering a parent showed nothing and the item had to be clicked.
 *
 * Plain overflow does not clip position: fixed descendants, so the CSS makes the
 * fly-out fixed and this script supplies the one thing fixed positioning loses:
 * the vertical offset, which must be read from the item's live bounding rect.
 *
 * Hover and focus are handled together: a fly-out reachable only by mouse is not
 * reachable at all for keyboard users.
 */
( function () {
	var menu = document.getElementById( 'adminmenu' );

	if ( ! menu ) {
		return;
	}

	/**
	 * Positions an item's fly-out beside it, flipping up near the viewport foot.
	 *
	 * @param {HTMLElement} item Menu list item.
	 */
	function place( item ) {
		if ( document.body.classList.contains( 'folded' ) ) {
			return;
		}

		var submenu = item.querySelector( '.wp-submenu' );

		if ( ! submenu || ! item.classList.contains( 'wp-not-current-submenu' ) ) {
			return;
		}

		var rect = item.getBoundingClientRect();

		// Measure before deciding: the height is only known once it is laid out.
		submenu.style.top = rect.top + 'px';

		var height = submenu.offsetHeight;

		if ( rect.top + height > window.innerHeight ) {
			submenu.style.top = Math.max( 0, window.innerHeight - height ) + 'px';
		}
	}

	/**
	 * Clears an inline offset so the item returns to its stylesheet position.
	 *
	 * @param {HTMLElement} item Menu list item.
	 */
	function clear( item ) {
		var submenu = item.querySelector( '.wp-submenu' );

		if ( submenu ) {
			submenu.style.top = '';
		}
	}

	/**
	 * Resolves the menu item an event happened inside.
	 *
	 * @param {Event} event DOM event.
	 * @return {HTMLElement|null} The item, or null.
	 */
	function itemFor( event ) {
		return event.target && event.target.closest
			? event.target.closest( '#adminmenu > li.menu-top' )
			: null;
	}

	menu.addEventListener( 'mouseover', function ( event ) {
		var item = itemFor( event );

		if ( item ) {
			place( item );
		}
	} );

	menu.addEventListener( 'mouseout', function ( event ) {
		var item = itemFor( event );

		if ( item && ! item.contains( event.relatedTarget ) ) {
			clear( item );
		}
	} );

	menu.addEventListener( 'focusin', function ( event ) {
		var item = itemFor( event );

		if ( item ) {
			place( item );
		}
	} );

	menu.addEventListener( 'focusout', function ( event ) {
		var item = itemFor( event );

		if ( item && ! item.contains( event.relatedTarget ) ) {
			clear( item );
		}
	} );
}() );
```

Enqueue it in `blueworx_enqueue_admin_theme()` in `includes/admin-theme.php`, after the stylesheet:

```php
	wp_enqueue_script(
		'blueworx-admin-menu-flyout',
		BLUEWORX_LABS_URL . 'assets/js/admin-menu-flyout.js',
		array(),
		blueworx_get_admin_asset_version( 'assets/js/admin-menu-flyout.js' ),
		true
	);
```

Replace the existing rule at `assets/css/admin-theme.css:866-868` inside the `min-width: 961px` block:

```css
	/* Fixed, not absolute: #adminmenuwrap's own overflow would clip an absolutely
	   positioned fly-out (that is the bug this replaces), and plain overflow does
	   not clip fixed descendants. The vertical offset is supplied per item by
	   assets/js/admin-menu-flyout.js, which is the one thing fixed positioning
	   cannot work out for itself. */
	body:not(.folded) #adminmenu .wp-not-current-submenu .wp-submenu {
		position: fixed;
		left: var(--bw-sidebar-w);
	}
```

Leave the folded state and the current item's inline accordion alone — both already behave correctly.

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS, both tests.

- [ ] **Step 5: Check the states the tests do not cover**

On the harness, confirm by eye: folded sidebar fly-outs still work; the current section's submenu still expands inline; a menu item near the bottom of a long sidebar flips its fly-out upward instead of running off-screen.

- [ ] **Step 6: Bump, changelog, commit, PR**

Set `1.40.0` across the four files. Add to `CHANGELOG.md`:

```markdown
## 1.40.0

### Fixed
- Admin sidebar submenus appear on hover again. The expanded sidebar's own
  scroll container had been clipping them, so a parent item had to be clicked to
  reach its children. Keyboard focus opens them too.
```

```bash
npm run version:check
npx eslint assets/js
git add assets/js/admin-menu-flyout.js assets/css/admin-theme.css includes/admin-theme.php tests/admin-theme.spec.js blueworx-labs-wordpress.php package.json readme.txt CHANGELOG.md
git commit -m "fix: restore hover fly-out submenus in the admin sidebar (1.40.0)"
```

---

## Group C — Profile screen refinement (v1.41.0)

**Invariant for every task in this group:** `assets/js/profile-redesign.js` **moves** native inputs into cards; it never clones or recreates them, so core's save handler receives exactly what it expects. Any change that copies an input, or reorders a field outside its parent form, breaks saving. Task C3's test guards this.

### Task C1: Field pairing and card subtitles

**Files:**
- Modify: `assets/js/profile-redesign.js`
- Modify: `assets/css/admin-theme.css` (profile block, from ~line 1104)
- Test: `tests/admin-theme.spec.js`

**Interfaces:**
- Produces: rows tagged `data-bw-field="<input id>"`, and `.bw-profile-card-sub` subtitle elements

- [ ] **Step 1: Write the failing test**

```js
test('name fields are paired side by side on the profile screen', async ({ page }) => {
  await login(page);
  await page.goto('/wp-admin/profile.php');

  const first = await page.locator('[data-bw-field="first_name"]').boundingBox();
  const last = await page.locator('[data-bw-field="last_name"]').boundingBox();

  // Same row, different columns.
  expect(Math.abs(first.y - last.y)).toBeLessThan(4);
  expect(last.x).toBeGreaterThan(first.x);
});

test('cards carry an explanatory subtitle', async ({ page }) => {
  await login(page);
  await page.goto('/wp-admin/profile.php');
  await expect(page.locator('.bw-profile-card-sub').first()).toBeVisible();
});
```

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — no `data-bw-field` attribute exists and fields are stacked full-width.

- [ ] **Step 3: Write minimal implementation**

In `assets/js/profile-redesign.js`, add the subtitle copy near the existing `RETITLE` map:

```js
	// Section-heading text (lower-cased) -> explanatory subtitle for the card.
	var SUBTITLE = {
		'name': 'How this user appears across the site.',
		'contact info': 'Where notifications and password resets are sent.',
		'about yourself': 'Shown on the author archive page and below posts.',
		'about the user': 'Shown on the author archive page and below posts.',
		'account management': 'Password and sign-in security.'
	};

	// Fields that share a row, in pairs. Anything not listed stays full width —
	// including fields added by other plugins, which is why this is an explicit
	// list rather than an nth-child rule.
	var PAIRED = [ 'first_name', 'last_name', 'nickname', 'display_name' ];
```

In `startCard()`, after appending the title:

```js
			if ( SUBTITLE[ key ] ) {
				var sub = document.createElement( 'p' );
				sub.className = 'bw-profile-card-sub';
				sub.textContent = SUBTITLE[ key ];
				card.appendChild( sub );
			}
```

After the form children have been moved into cards (immediately before `form.insertBefore( grid, form.firstChild )`), tag the rows:

```js
		// Tag each field row with the id of the input it holds, so CSS can pair
		// specific fields without depending on their position — a plugin adding a
		// profile field must not shuffle the pairing.
		grid.querySelectorAll( '.form-table tr' ).forEach( function ( row ) {
			var field = row.querySelector( 'input[id], select[id], textarea[id]' );

			if ( ! field ) {
				return;
			}

			row.setAttribute( 'data-bw-field', field.id );

			if ( PAIRED.indexOf( field.id ) !== -1 ) {
				row.classList.add( 'bw-profile-field-half' );
			}
		} );
```

In `assets/css/admin-theme.css`, replace the `.bw-profile-card .form-table tr` rule (~line 1271) with a grid layout:

```css
.bw-profile-card .form-table > tbody {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 14px 16px;
}

/* Full width by default; only the explicitly paired fields share a row. */
.bw-profile-card .form-table tr {
	display: block;
	grid-column: 1 / -1;
	margin-bottom: 0;
}

.bw-profile-card .form-table tr.bw-profile-field-half {
	grid-column: span 1;
}

@media only screen and (max-width: 782px) {
	.bw-profile-card .form-table tr.bw-profile-field-half {
		grid-column: 1 / -1;
	}
}

.bw-profile-card-sub {
	margin: -8px 0 16px;
	color: var(--bw-muted);
	font-size: 12px;
}
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/js/profile-redesign.js assets/css/admin-theme.css tests/admin-theme.spec.js
git commit -m "feat: pair name fields and add card subtitles on the profile screen"
```

---

### Task C2: Spacing pass and breadcrumb

**Files:**
- Modify: `assets/css/admin-theme.css`
- Modify: `assets/js/profile-redesign.js`
- Modify: `includes/admin-assets.php` (pass the users-list URL and label)
- Test: `tests/admin-theme.spec.js`

**Interfaces:**
- Consumes: the `blueworxProfile` localised object built in `blueworx_enqueue_admin_assets()`
- Produces: two new keys on it — `usersUrl` (string, `''` when the viewer lacks `list_users` or is on their own profile) and `backLabel` (string)

- [ ] **Step 1: Write the failing test**

```js
test('editing another user shows a back link to the users list', async ({ page }) => {
  await login(page);
  await page.goto('/wp-admin/users.php');
  await page.locator('#the-list tr').first().locator('a').first().click();

  await expect(page.locator('.bw-profile-back')).toBeVisible();
});

test('own profile has no back link', async ({ page }) => {
  await login(page);
  await page.goto('/wp-admin/profile.php');
  await expect(page.locator('.bw-profile-back')).toHaveCount(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — `.bw-profile-back` does not exist.

- [ ] **Step 3: Write minimal implementation**

In `includes/admin-assets.php`, add to the `wp_localize_script` array:

```php
						'usersUrl'    => ( 'user-edit.php' === $hook_suffix && current_user_can( 'list_users' ) )
							? admin_url( 'users.php' )
							: '',
						'backLabel'   => __( 'Back to Users', 'blueworx-labs-wordpress' ),
```

In `assets/js/profile-redesign.js`, before `wrap.insertBefore( buildHero(), form )`:

```js
		if ( data.usersUrl ) {
			var back = document.createElement( 'a' );
			back.className = 'bw-profile-back';
			back.href = data.usersUrl;
			back.textContent = '← ' + data.backLabel;
			wrap.insertBefore( back, form );
		}
```

In `assets/css/admin-theme.css`, add the border and tighten the rhythm:

```css
.bw-profile-back {
	display: inline-block;
	margin-bottom: 12px;
	font-size: 13px;
	font-weight: 600;
	text-decoration: none;
}

.bw-profile-card {
	border: 1px solid var(--bw-border);
	padding: 22px 24px;
}

.bw-profile-card-title {
	margin: 0 0 6px;
	font-size: 16px;
}

.bw-profile-card .form-table th {
	padding-bottom: 7px;
	font-size: 12.5px;
}

.bw-profile-grid {
	gap: 24px;
}

.bw-profile-col {
	gap: 24px;
}
```

Adjust `.bw-profile-card` rather than adding a second conflicting declaration — edit the existing rule at ~line 1242.

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/css/admin-theme.css assets/js/profile-redesign.js includes/admin-assets.php tests/admin-theme.spec.js
git commit -m "feat: profile spacing pass and back-to-users breadcrumb"
```

---

### Task C3: Security card and Delete Account, plus the save-still-works guard

**Files:**
- Modify: `assets/js/profile-redesign.js`
- Modify: `includes/admin-assets.php`
- Modify: `assets/css/admin-theme.css`
- Test: `tests/admin-theme.spec.js`

**Interfaces:**
- Produces: two more keys on `blueworxProfile` — `deleteUrl` (string, `''` unless the viewer can delete this user and it is not themselves) and `deleteLabel` (string)

WordPress already renders the sessions control ("Log Out Everywhere Else") in the Account Management section, so it needs moving and styling, not building.

- [ ] **Step 1: Write the failing test**

```js
test('saving the profile still persists a change', async ({ page }) => {
  await login(page);
  await page.goto('/wp-admin/profile.php');

  const nickname = page.locator('#nickname');
  const original = await nickname.inputValue();
  const probe = `bw-test-${Date.now()}`;

  await nickname.fill(probe);
  await page.locator('#submit').click();
  await page.goto('/wp-admin/profile.php');
  await expect(page.locator('#nickname')).toHaveValue(probe);

  // Restore.
  await page.locator('#nickname').fill(original);
  await page.locator('#submit').click();
});

test('a delete card appears when editing another user', async ({ page }) => {
  await login(page);
  await page.goto('/wp-admin/users.php');
  const row = page.locator('#the-list tr').filter({ hasNotText: 'admin' }).first();
  test.skip(await row.count() === 0, 'Harness has only one user');

  await row.locator('a').first().click();
  await expect(page.locator('.bw-profile-danger')).toBeVisible();
});
```

The first test is the invariant guard. It must pass unchanged after every task in Group C.

- [ ] **Step 2: Run test to verify it fails**

Expected: the save test PASSES already (the invariant holds today — keep it green), the delete-card test FAILS.

- [ ] **Step 3: Write minimal implementation**

In `includes/admin-assets.php`, add to the localised array:

```php
						'deleteUrl'   => ( 'user-edit.php' === $hook_suffix
							&& current_user_can( 'delete_users' )
							&& get_current_user_id() !== $profile_user->ID )
							? wp_nonce_url(
								admin_url( 'users.php?action=delete&user=' . $profile_user->ID ),
								'bulk-users'
							)
							: '',
						'deleteLabel' => __( 'Delete This User', 'blueworx-labs-wordpress' ),
```

The `bulk-users` nonce action is what core's own `users.php` delete flow expects; this links into that existing confirmation screen rather than deleting anything directly.

In `assets/js/profile-redesign.js`, after the grid is inserted:

```js
		if ( data.deleteUrl ) {
			var danger = document.createElement( 'section' );
			danger.className = 'bw-profile-card bw-profile-danger';
			danger.innerHTML =
				'<h2 class="bw-profile-card-title">' + escapeHtml( data.deleteLabel ) + '</h2>' +
				'<p class="bw-profile-card-sub">' +
					'Content can be reassigned to another user before deletion.' +
				'</p>' +
				'<a class="bw-btn bw-btn-danger" href="' + encodeURI( data.deleteUrl ) + '">' +
					escapeHtml( data.deleteLabel ) +
				'</a>';
			rightCol.appendChild( danger );
		}
```

In `assets/css/admin-theme.css`:

```css
.bw-profile-danger {
	border-color: rgba(255, 48, 47, .35);
}

.bw-profile-danger .bw-btn-danger {
	display: inline-flex;
	align-items: center;
	border: 1px solid var(--bw-error);
	border-radius: var(--bw-radius-btn);
	padding: 9px 16px;
	color: var(--bw-error);
	background: transparent;
	font-size: 13px;
	font-weight: 600;
	text-decoration: none;
}

.bw-profile-danger .bw-btn-danger:hover,
.bw-profile-danger .bw-btn-danger:focus {
	color: #fff;
	background: var(--bw-error);
}
```

- [ ] **Step 4: Run tests to verify they pass**

Expected: both PASS.

- [ ] **Step 5: Full-suite check and release**

```bash
npm run version:check
npx eslint assets/js
composer lint
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8892 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw \
WP_LOGIN_PATH=admin_login npx playwright test
```

Per `CLAUDE.md`, **do not loop on lint.** Run it once, present findings, wait for a decision.

Set `1.41.0` across the four files. Add to `CHANGELOG.md`:

```markdown
## 1.41.0

### Changed
- Profile screen refinement: name fields sit in pairs, cards carry explanatory
  subtitles and a defined border, spacing follows the design system, and editing
  another user gains a back link and a delete card.
```

```bash
git add -A
git commit -m "feat: profile security and delete cards, release 1.41.0"
```

---

## Self-Review

**Spec coverage:**

| Spec section | Task |
| --- | --- |
| §2 Editor overlap, incl. the verify-the-state requirement | A1, A2 |
| §2 Fullscreen left alone | A2 (`:not(.is-fullscreen-mode)`) |
| §3 Fly-outs, incl. flip-up and keyboard | B1 |
| §4 Field pairing, subtitles | C1 |
| §4 Spacing, borders, breadcrumb | C2 |
| §4 Delete Account | C3 |
| §4 "Moves, never clones" invariant | C3 save-guard test |

**Gap — §4 "Log Out Everywhere".** The spec says core's sessions control is moved into the Security card and styled. Task C3 names it but no step actually moves it, because the existing `startCard()` logic already routes the whole `account management` section into the right column, which is where the Security card lives — so it may need nothing beyond the styling C2 applies. **Confirm on the harness during C3**; if the control lands somewhere else, add an explicit move step rather than leaving it.

**Placeholder scan:** clean — every step carries real code.

**Type consistency:** the four keys added to `blueworxProfile` (`usersUrl`, `backLabel`, `deleteUrl`, `deleteLabel`) are named identically in `includes/admin-assets.php` and `assets/js/profile-redesign.js`. `data-bw-field` is written in C1 and read by the C1 tests only. `.bw-profile-card-sub` is created in C1 and reused in C3.
