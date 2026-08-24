# Admin re-skin on design system tokens — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop the plugin's two hand-written re-skins from overriding the shared design system's tokens, and express every value in both of them — plus `admin-additions.css` — using the system's tokens.

**Architecture:** Three sequential pull requests against `main`. PR 1 removes the colliding `:root` blocks and renames the four genuinely-local values to a `--bwt-` prefix. PR 2 converts the body of `admin-theme.css`. PR 3 converts `login-theme.css`. No markup changes except three hand-drawn icons in PR 2.

**Tech Stack:** WordPress plugin, plain CSS with custom properties, Playwright for tests, PHP 8.

**Spec:** `docs/superpowers/specs/2026-08-24-admin-reskin-on-design-system-design.md`

## Global Constraints

- The design system stylesheet `assets/blueworx-admin-design.css` and everything under `.claude/skills/blueworx-admin-design/` are **never edited**. CI compares them against the foundation byte for byte.
- Every plugin stylesheet loads *after* the design system, so it may read the system's tokens but must never redeclare a `--bw-` name the system declares.
- Names the plugin owns use the **`--bwt-`** prefix. The system owns `--bw-`.
- A `px` value inside an `@media` breakpoint stays `px` — a breakpoint is a fact about the viewport.
- The inline critical-CSS block in `includes/admin-theme.php` keeps its `60px` literals. It prints before the stylesheet that defines the custom properties.
- Version bumped (patch) and `CHANGELOG.md` updated in every PR.
- Tests run against the local harness at `http://127.0.0.1:8881` — the default. Never point the suite at staging.

---

### Task 1: A test that fails on today's code

**Files:**
- Create: `tests/design-system-tokens.spec.js`

**Interfaces:**
- Consumes: `./helpers.js` — `test`, `expect`, `isPlaceholder`, `ADMIN_USER`, `ADMIN_PASS`, `login`
- Produces: nothing other tasks import.

- [ ] **Step 1: Write the failing test**

```js
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

/**
 * The design system's tokens must survive the re-skin.
 *
 * Both re-skins load after the design system, so a `--bw-` name they declare
 * overrides the system's for every component on the page. That went unnoticed
 * for weeks because nothing about the markup changes when it happens — only the
 * computed value does. This is the test that sees it.
 */

/** Name, and the value the design system defines for it. */
const SYSTEM_TOKENS = [
  ['--bw-success', '#00A32A'],
  ['--bw-warning', '#DBA617'],
  ['--bw-info', '#2271B1'],
  ['--bw-line', '#ECEDF3'],
];

const tokenValue = (page, name) =>
  page.evaluate(
    (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim(),
    name
  );

test.describe('the re-skin does not override the design system', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('an admin screen keeps the system values for the shared tokens', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');

    for (const [name, expected] of SYSTEM_TOKENS) {
      expect(
        (await tokenValue(page, name)).toUpperCase(),
        `${name} is not the design system's value`
      ).toBe(expected.toUpperCase());
    }
  });

  test('body text on an admin screen is the system body face', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');

    // Inter is the system's body face. Sora is the display face, and was what
    // the re-skin forced onto every component by redeclaring --bw-font-body.
    expect(await tokenValue(page, '--bw-font-body')).toContain('Inter');
  });

  test('the login screen keeps the system values too', async ({ page }) => {
    await page.goto('/admin_login');

    for (const [name, expected] of SYSTEM_TOKENS.filter(([n]) => n !== '--bw-info' && n !== '--bw-warning')) {
      expect(
        (await tokenValue(page, name)).toUpperCase(),
        `${name} is not the design system's value on the login screen`
      ).toBe(expected.toUpperCase());
    }
    expect(await tokenValue(page, '--bw-font-body')).toContain('Inter');
  });
});
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `npx playwright test tests/design-system-tokens.spec.js --reporter=list`
Expected: FAIL. `--bw-success` reports `#01824C`, `--bw-font-body` contains `Sora`.

- [ ] **Step 3: Commit the failing test**

```bash
git add tests/design-system-tokens.spec.js
git commit -m "Test that the re-skin does not override the design system"
```

---

### Task 2: Remove the colliding token blocks

**Files:**
- Modify: `assets/css/admin-theme.css` — the `:root` block, and every `var()` reading a renamed token
- Modify: `assets/css/login-theme.css` — same
- Modify: `includes/admin-theme.php` — the comment naming `--bw-topbar-h`

**Interfaces:**
- Produces: the `--bwt-` names `--bwt-topbar-h`, `--bwt-sidebar-w`, `--bwt-measure`, `--bwt-panel-width`, used by Tasks 3 and 5.

- [ ] **Step 1: Apply the mapping in `admin-theme.css`**

Delete the whole `:root { … }` block at the top of the file. Then rewrite every
`var(--old)` reference:

| Old | New |
|---|---|
| `--bw-primary` | `--bw-brand` |
| `--bw-primary-dark` | `--bw-brand-deep` |
| `--bw-charcoal` | `--bw-ink` |
| `--bw-lavender` | `--bw-brand-wash` |
| `--bw-surface` | `--bw-canvas` |
| `--bw-body` | `--bw-text-body` |
| `--bw-muted` | `--bw-text-muted` |
| `--bw-border` | `--bw-line` |
| `--bw-error` | `--bw-danger` |
| `--bw-radius-card` | `--bw-radius-lg` |
| `--bw-radius-btn` | `--bw-radius-md` |
| `--bw-radius-input` | `--bw-radius-md` |
| `--bw-shadow-card-hover` | `--bw-shadow-raised` |
| `--bw-font-head` | `--bw-font-display` |
| `--bw-font-ui` | `--bw-font-body` |
| `--bw-font-body` | `--bw-font-body` |
| `--bw-sidebar-w` | `--bwt-sidebar-w` |
| `--bw-measure` | `--bwt-measure` |
| `--bw-topbar-h` | `--bwt-topbar-h` |

`--bw-success`, `--bw-warning`, `--bw-info` and `--bw-shadow-card` keep their
names — they are simply no longer redeclared, so the system's values apply.

Then add the plugin's own block, which declares only names the system does not:

```css
/* The chrome measurements this plugin owns. The --bwt- prefix keeps them out of
   the design system's namespace: this file loads after the system, so a --bw-
   name declared here would silently override the system's for every component
   on the page. That is exactly the bug this replaced. */
:root {
	--bwt-topbar-h: 60px;
	--bwt-sidebar-w: 232px;
	--bwt-measure: 1200px;
}
```

- [ ] **Step 2: Apply the same in `login-theme.css`**

Delete its `:root` block. Same mapping, plus `--bw-primary-light` → `--bw-brand-line`. Then:

```css
/* See the note in admin-theme.css: names this plugin owns use --bwt-. */
:root {
	--bwt-panel-width: 44%;
}
```

- [ ] **Step 3: Update the comment in `includes/admin-theme.php`**

The comment reads "Keep the 60px top bar height in sync with `--bw-topbar-h` in
admin-theme.css." Change `--bw-topbar-h` to `--bwt-topbar-h`. The `60px`
literals in that block do **not** change.

- [ ] **Step 4: Check no old name survives**

Run:
```bash
grep -nE -- '--bw-(primary|charcoal|lavender|surface|body|muted|border|error|radius-card|radius-btn|radius-input|shadow-card-hover|font-head|font-ui|sidebar-w|measure|topbar-h)\b' assets/css/admin-theme.css assets/css/login-theme.css
```
Expected: no output.

- [ ] **Step 5: Check nothing declares a system token**

Run:
```bash
grep -oE -- '--bw-[a-z0-9-]+[[:space:]]*:' assets/css/admin-theme.css assets/css/login-theme.css | sed 's/.*\(--bw[^:]*\):/\1/' | tr -d ' ' | sort -u
```
Expected: only `--bwt-` names.

- [ ] **Step 6: Run the new test**

Run: `npx playwright test tests/design-system-tokens.spec.js --reporter=list`
Expected: PASS, all three.

- [ ] **Step 7: Commit**

```bash
git add assets/css/admin-theme.css assets/css/login-theme.css includes/admin-theme.php
git commit -m "Stop the re-skin overriding the design system's tokens"
```

---

### Task 3: `admin-additions.css` onto system tokens

**Files:**
- Modify: `assets/css/admin-additions.css`

- [ ] **Step 1: Replace every hand-written value**

Every colour becomes the nearest system colour token, every size the nearest
`--bw-space-*` or `--bw-radius-*`, and the one `font-family` declaration becomes
`var(--bw-font-body)`. A `px` inside `@media` stays.

- [ ] **Step 2: Confirm the adherence findings for this file are gone**

Run:
```bash
BASE_REF=main SKILL_PATH=.claude/skills/blueworx-admin-design \
  node ../bluegroup_core_foundation/scripts/check-admin-ui-adherence.mjs
```
Expected: no `raw-color`, `raw-size` or `raw-font` for `admin-additions.css`.
`stray-admin-css` findings remain and are expected.

- [ ] **Step 3: Run the visual specs that cover these components**

Run: `npx playwright test tests/guides-design.spec.js tests/panel-controls.spec.js --reporter=list`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add assets/css/admin-additions.css
git commit -m "Put the local components on design system tokens"
```

---

### Task 4: Ship PR 1

**Files:**
- Modify: `blueworx-labs-wordpress.php`, `package.json`, `readme.txt` — version
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Bump the patch version in all three places and add the changelog entry**

- [ ] **Step 2: Verify the versions agree**

Run: `npm run version:check`
Expected: `version:check OK`.

- [ ] **Step 3: Run the full suite**

Run: `npx playwright test --reporter=list`
Expected: all pass. Known-good baseline is 153 passed / 2 skipped.

- [ ] **Step 4: Lint once**

Run: `npm run lint`
Expected: clean.

- [ ] **Step 5: Push and open the pull request, wait for CI, merge**

---

### Task 5: `admin-theme.css` body onto tokens

**Files:**
- Modify: `assets/css/admin-theme.css`
- Modify: `includes/admin-theme.php` — three `<svg>` icons

- [ ] **Step 1: Replace every remaining hand-written colour, size and shadow**

Work block by block, in the order the file's own header lists: Tokens, Base,
Chrome, Sidebar, Cards & tables, Buttons, Inputs, Headings & notices, Dashboard
hero tiles, Responsive. Commit after each block so a regression is bisectable.

Mapping for values with no exact token: pick the nearest on the system's scale
and let the value shift. That is the decision recorded in the spec.

- [ ] **Step 2: Replace the three hand-drawn icons in the PHP**

Each `<svg viewBox="0 0 24 24" …>` becomes:

```php
<i class="bw-icon" data-lucide="<name>" aria-hidden="true"></i>
```

Pick the Lucide name matching the glyph the SVG draws.

- [ ] **Step 3: Confirm the raw-value findings are gone**

Run the adherence command from Task 3 Step 2.
Expected: no `raw-color`, `raw-size`, `raw-shadow`, `raw-font` or `hand-svg` for
`admin-theme.css` or `includes/admin-theme.php`. `stray-admin-css` remains.

- [ ] **Step 4: Run the full suite**

Run: `npx playwright test --reporter=list`
Expected: all pass. Where a spec asserts a value that deliberately changed,
update the spec to the system's value in this PR — never loosen the assertion.

- [ ] **Step 5: Bump version, changelog, lint, push, CI, merge**

---

### Task 6: `login-theme.css` onto tokens

**Files:**
- Modify: `assets/css/login-theme.css`

- [ ] **Step 1: Replace every remaining hand-written colour, size and shadow**

- [ ] **Step 2: Confirm the raw-value findings are gone**

Run the adherence command from Task 3 Step 2.

- [ ] **Step 3: Run the login specs, then the full suite**

Run: `npx playwright test tests/login-design.spec.js --reporter=list`
then `npx playwright test --reporter=list`
Expected: PASS.

- [ ] **Step 4: Bump version, changelog, lint, push, CI, merge**

---

### Task 7: Deployment

- [ ] **Step 1: Build the zip**

Run: `npm run build`

- [ ] **Step 2: Place it beside the repo and remove the older one**

```bash
cd .. && rm -f blueworx-labs-wordpress-*.zip
cp blueworx_labs_wordpress/dist/blueworx-labs-wordpress.zip blueworx-labs-wordpress-<version>.zip
unzip -l blueworx-labs-wordpress-<version>.zip | head
```
Expected: every entry reads `blueworx-labs-wordpress/…` with forward slashes.
