# Plan 3a — Commerce Interactive Widgets Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the billing toggle, pricing calculator and savings calculator interactive via progressive enhancement — PHP renders the real default state, vanilla ES5 JS enhances it.

**Architecture:** One new script `assets/js/public-widgets.js` (IIFE, ES5, no deps), enqueued on owned pages in the footer, self-initialising by scanning for the `[data-widget]` markers already in the templates. The pricing/toolbox templates get their placeholder `<div>`s replaced with the widgets' full default-state markup; the billing-toggle markup and the `data-price-*` plan-card attributes already exist. JS reads all data off the DOM — no `wp_localize_script`.

**Tech Stack:** Procedural PHP 8.0+, ES5 vanilla JS, Playwright against the local WordPress harness. Design source: `bluegroup_project_blueworx/components/PricingCalc.tsx`, `SavingsCalc.tsx`, `PlanCards`/`Plans.tsx`.

## Global Constraints

- Based on branch `marketing-pages` (Plan 2 / PR #41) — the pages being edited exist only there.
- Prefix everything `blueworx` / `BLUEWORX`; text domain `blueworx-labs-wordpress` only.
- PHPCS WordPress ruleset (tabs, Yoda, `array()` long syntax, spaces in parens). No PHPCS errors in new code (local CRLF `InvalidEOLChar` is the ignorable Windows artefact; `phpcbf` fixes array alignment).
- All PHP output escaped (`esc_html`/`esc_html__`/`esc_url`/`esc_attr`); raw echoed SVG carries a `phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted glyph.` comment.
- New JS passes `npm run lint` (eslint on `assets/js`). Vanilla ES5 only, no new dependencies.
- Every page keeps its `<main><div>…</div></main>` wrapper; internal links via `home_url()`; tool favicons are the bundled `assets/img/tools/<slug>.png`, never Google.
- Progressive enhancement: PHP prints the correct default state; JS only rewrites text and toggles classes. Every price/total is correct with JS off.
- Version bump to **1.34.0** (feature) + `CHANGELOG.md` + `readme.txt` Stable tag; `node scripts/version-check.mjs` passes.
- **Never run a command in the background — foreground only.** Re-provision the harness after the enqueue change: `node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up --plugin . --slug blueworx-labs-wordpress --dir .wp-test --port 8892` (admin/wptest-admin-pw, WP_LOGIN_PATH=admin_login). Run only the new spec per task; save the full double-run for release.
- The deliverable is the plugin zip; `assets/` is already in `scripts/build-zip.mjs`'s allowlist, so the new JS ships automatically — verify with `unzip -l`.

**Verified data (from `includes/public/content.php`):**
- Retainer plans (Pricing): Essential 200/160, **Growth 500/400 (feat, pop)**, Advanced 750/600.
- Toolbox plans (Toolbox): Personal 30/25, **Business 60/50 (feat, pop)**, Agency 200/160.
- Solo prices sum = **160**; savings default solo = 160 + 30 hosting = **190**, saving = 190 − 30 = **160/mo · 1,920/yr**.
- Pricing calc default (growth, updates 2, sites 1, hosting on) = 500 + 60 + 0 + 40 = **600**.

---

## Task 1: Billing toggle + script enqueue + version bump

**Files:**
- Create: `assets/js/public-widgets.js`
- Modify: `includes/public/assets.php` (enqueue the new script)
- Modify: `blueworx-labs-wordpress.php` (Version header + constant), `CHANGELOG.md`, `readme.txt` (Stable tag)
- Test: `tests/widgets-commerce.spec.js` (billing-toggle cases)

**Interfaces:**
- Consumes: existing `.bill-toggle[data-widget="billing-toggle"]` (two `<button>`s, first `.on`) on `templates/pages/pricing.php` and `templates/pages/toolbox.php`; existing `.plan-price[data-price-m][data-price-a]` with `<b>` and `<em data-sub-m data-sub-a>` from `templates/parts/plan-cards.php`.
- Produces: global function set inside the IIFE — `initBillingToggle()`, `initPricingCalc()`, `initSavingsCalc()` are wired to run on `DOMContentLoaded`. Later tasks add the calc bodies; this task creates the file with all three init stubs and the billing body.

- [ ] **Step 1: Write the failing test**

Create `tests/widgets-commerce.spec.js`:

```js
const { test, expect } = require('@playwright/test');
const { isPlaceholder } = require('./helpers');

const BASE = process.env.PLAYWRIGHT_BASE_URL || '';

test.describe('Commerce widgets — billing toggle', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  for (const page of [
    { path: '/pricing', plan: 'Growth Support', m: '$500', a: '$400' },
    { path: '/toolbox', plan: 'Business', m: '$60', a: '$50' },
  ]) {
    test(`toggle swaps monthly/annual prices on ${page.path}`, async ({ page: pw }) => {
      await pw.goto(page.path);
      const card = pw.locator('.plan-card', { hasText: page.plan }).first();
      const price = card.locator('.plan-price b');
      const sub = card.locator('.plan-price em');

      await expect(price).toHaveText(page.m);
      await expect(sub).toHaveText('per month');

      await pw.locator('[data-widget="billing-toggle"] button', { hasText: 'Annual' }).click();
      await expect(price).toHaveText(page.a);
      await expect(sub).toHaveText('per month, billed yearly');

      await pw.locator('[data-widget="billing-toggle"] button', { hasText: 'Monthly' }).click();
      await expect(price).toHaveText(page.m);
      await expect(sub).toHaveText('per month');
    });
  }
});
```

- [ ] **Step 2: Run it, verify it fails**

Run: `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8892 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test widgets-commerce.spec.js --workers=1`
Expected: FAIL — clicking Annual does not change the price (no JS wired yet).

- [ ] **Step 3: Create `assets/js/public-widgets.js`**

```js
/**
 * BlueWorx public marketing widgets (Plan 3a).
 *
 * Progressive enhancement: the templates render each widget's correct default
 * state; this script only rewrites text and toggles classes on interaction.
 * Each init no-ops when its [data-widget] marker is absent, so the one file is
 * safe on every owned page.
 */
( function () {
	'use strict';

	function initBillingToggle() {
		var toggle = document.querySelector( '[data-widget="billing-toggle"]' );
		if ( ! toggle ) {
			return;
		}
		var btns = toggle.querySelectorAll( 'button' );
		if ( btns.length < 2 ) {
			return;
		}

		function apply( annual ) {
			btns[ 0 ].className = annual ? '' : 'on';
			btns[ 1 ].className = annual ? 'on' : '';
			btns[ 0 ].setAttribute( 'aria-pressed', annual ? 'false' : 'true' );
			btns[ 1 ].setAttribute( 'aria-pressed', annual ? 'true' : 'false' );

			var prices = document.querySelectorAll( '.plan-price' );
			for ( var i = 0; i < prices.length; i++ ) {
				var b = prices[ i ].querySelector( 'b' );
				var em = prices[ i ].querySelector( 'em' );
				if ( b ) {
					b.textContent = '$' + ( annual ? prices[ i ].getAttribute( 'data-price-a' ) : prices[ i ].getAttribute( 'data-price-m' ) );
				}
				if ( em ) {
					em.textContent = annual ? em.getAttribute( 'data-sub-a' ) : em.getAttribute( 'data-sub-m' );
				}
			}
		}

		toggle.setAttribute( 'role', 'group' );
		btns[ 0 ].addEventListener( 'click', function () {
			apply( false );
		} );
		btns[ 1 ].addEventListener( 'click', function () {
			apply( true );
		} );
		apply( false );
	}

	function initPricingCalc() {
		// Body added in Task 2.
	}

	function initSavingsCalc() {
		// Body added in Task 3.
	}

	function init() {
		initBillingToggle();
		initPricingCalc();
		initSavingsCalc();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
```

- [ ] **Step 4: Enqueue it in `includes/public/assets.php`**

In `blueworx_enqueue_public_assets()`, after the existing `blueworx-public-nav` `wp_enqueue_script(...)` block, add:

```php
	wp_enqueue_script(
		'blueworx-public-widgets',
		BLUEWORX_LABS_URL . 'assets/js/public-widgets.js',
		array(),
		blueworx_get_admin_asset_version( 'assets/js/public-widgets.js' ),
		true
	);
```

- [ ] **Step 5: Re-provision the harness and run the test, verify it passes**

Run: `node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up --plugin . --slug blueworx-labs-wordpress --dir .wp-test --port 8892`
Then: `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8892 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test widgets-commerce.spec.js --workers=1`
Expected: PASS (both `/pricing` and `/toolbox` cases).

- [ ] **Step 6: Lint the new JS**

Run: `npm run lint`
Expected: clean (0 errors).

- [ ] **Step 7: Bump version + changelog + readme**

- `blueworx-labs-wordpress.php`: `Version: 1.34.0` header and the version constant.
- `CHANGELOG.md`: new `## 1.34.0` entry — "Plan 3a: interactive billing toggle, pricing calculator and savings calculator (progressive enhancement)."
- `readme.txt`: `Stable tag: 1.34.0`.
- Run: `node scripts/version-check.mjs` → OK.

- [ ] **Step 8: Commit**

```bash
git add assets/js/public-widgets.js includes/public/assets.php tests/widgets-commerce.spec.js blueworx-labs-wordpress.php CHANGELOG.md readme.txt
git commit -m "feat: interactive billing toggle (Plan 3a) + enqueue public-widgets.js"
```

---

## Task 2: Pricing calculator

**Files:**
- Modify: `templates/pages/pricing.php` (replace the `[data-widget="pricing-calc"]` placeholder)
- Modify: `assets/js/public-widgets.js` (fill in `initPricingCalc()`)
- Test: `tests/widgets-commerce.spec.js` (add pricing-calc cases)

**Interfaces:**
- Consumes: the `initPricingCalc()` stub from Task 1.
- Produces: `[data-widget="pricing-calc"]` markup with `.opt[data-support]` buttons, `.stepper[data-field][data-min][data-max]`, a hosting `.toggle-pill`, and `[data-testid="calc-total"]`.

- [ ] **Step 1: Write the failing test**

Add inside `tests/widgets-commerce.spec.js`:

```js
test.describe('Commerce widgets — pricing calculator', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('recomputes the monthly total from the controls', async ({ page }) => {
    await page.goto('/pricing');
    const total = page.locator('[data-testid="calc-total"]');
    const calc = page.locator('[data-widget="pricing-calc"]');

    await expect(total).toHaveText('$600'); // growth + 1 extra update pack + hosting

    await calc.locator('.opt', { hasText: 'Essential' }).click();
    await expect(total).toHaveText('$300'); // 200 + 60 + 40

    await calc.locator('.stepper[data-field="sites"] button', { hasText: '+' }).click();
    await expect(total).toHaveText('$420'); // 200 + 60 + 120 + 40

    await calc.locator('.toggle-pill').click();
    await expect(total).toHaveText('$380'); // hosting off
  });

  test('steppers clamp at their bounds', async ({ page }) => {
    await page.goto('/pricing');
    const updates = page.locator('.stepper[data-field="updates"]');
    for (let i = 0; i < 8; i++) await updates.locator('button', { hasText: '+' }).click();
    await expect(updates.locator('b')).toHaveText('6'); // max 6
  });
});
```

- [ ] **Step 2: Run it, verify it fails**

Run: `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8892 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test widgets-commerce.spec.js -g "pricing calculator" --workers=1`
Expected: FAIL — the placeholder has no `[data-testid="calc-total"]`.

- [ ] **Step 3: Replace the placeholder in `templates/pages/pricing.php`**

Find the block:

```php
			<div class="bw-plan3-placeholder" data-widget="pricing-calc">
```

Replace that placeholder `<div>…</div>` with the calc markup (default: Growth, updates 2, sites 1, hosting on, total $600):

```php
			<div class="calc" data-widget="pricing-calc">
				<div class="calc-panel">
					<div class="calc-field">
						<label><?php esc_html_e( 'Support level', 'blueworx-labs-wordpress' ); ?></label>
						<div class="opt-row">
							<button type="button" class="opt" data-support="essential"><?php esc_html_e( 'Essential', 'blueworx-labs-wordpress' ); ?></button>
							<button type="button" class="opt on" data-support="growth"><?php esc_html_e( 'Growth', 'blueworx-labs-wordpress' ); ?></button>
							<button type="button" class="opt" data-support="advanced"><?php esc_html_e( 'Advanced', 'blueworx-labs-wordpress' ); ?></button>
						</div>
					</div>
					<div class="calc-field">
						<label><?php esc_html_e( 'Update packs per year', 'blueworx-labs-wordpress' ); ?></label>
						<div class="stepper" data-field="updates" data-min="1" data-max="6">
							<button type="button" aria-label="<?php esc_attr_e( 'Fewer update packs', 'blueworx-labs-wordpress' ); ?>">&minus;</button>
							<b>2</b>
							<button type="button" aria-label="<?php esc_attr_e( 'More update packs', 'blueworx-labs-wordpress' ); ?>">+</button>
						</div>
					</div>
					<div class="calc-field">
						<label><?php esc_html_e( 'Number of websites', 'blueworx-labs-wordpress' ); ?></label>
						<div class="stepper" data-field="sites" data-min="1" data-max="5">
							<button type="button" aria-label="<?php esc_attr_e( 'Fewer websites', 'blueworx-labs-wordpress' ); ?>">&minus;</button>
							<b>1</b>
							<button type="button" aria-label="<?php esc_attr_e( 'More websites', 'blueworx-labs-wordpress' ); ?>">+</button>
						</div>
					</div>
					<div class="calc-field" style="display:flex;align-items:center;justify-content:space-between">
						<label style="margin:0"><?php esc_html_e( 'Managed hosting add-on', 'blueworx-labs-wordpress' ); ?></label>
						<button type="button" class="toggle-pill on" aria-label="<?php esc_attr_e( 'Managed hosting add-on', 'blueworx-labs-wordpress' ); ?>" aria-pressed="true"></button>
					</div>
				</div>
				<div class="calc-out">
					<div class="cl"><?php esc_html_e( 'Estimated total', 'blueworx-labs-wordpress' ); ?></div>
					<div class="cv" data-testid="calc-total">$600</div>
					<div class="cp"><?php esc_html_e( 'per month', 'blueworx-labs-wordpress' ); ?></div>
					<a href="<?php echo esc_url( home_url( '/contact' ) ); ?>" class="btn btn-brand btn-md" style="width:100%;text-decoration:none"><?php esc_html_e( 'Get this plan', 'blueworx-labs-wordpress' ); ?></a>
				</div>
			</div>
```

- [ ] **Step 4: Fill in `initPricingCalc()` in `assets/js/public-widgets.js`**

Replace the `initPricingCalc()` stub body with:

```js
	function initPricingCalc() {
		var root = document.querySelector( '[data-widget="pricing-calc"]' );
		if ( ! root ) {
			return;
		}
		var out = root.querySelector( '[data-testid="calc-total"]' );
		var base = { essential: 200, growth: 500, advanced: 750 };
		var state = { support: 'growth', updates: 2, sites: 1, hosting: true };

		function clamp( v, min, max ) {
			return Math.max( min, Math.min( max, v ) );
		}
		function render() {
			var total = base[ state.support ] + ( state.updates - 1 ) * 60 + ( state.sites - 1 ) * 120 + ( state.hosting ? 40 : 0 );
			if ( out ) {
				out.textContent = '$' + total;
			}
		}

		var opts = root.querySelectorAll( '.opt-row .opt' );
		for ( var i = 0; i < opts.length; i++ ) {
			( function ( opt ) {
				opt.addEventListener( 'click', function () {
					state.support = opt.getAttribute( 'data-support' );
					for ( var j = 0; j < opts.length; j++ ) {
						opts[ j ].className = 'opt';
					}
					opt.className = 'opt on';
					render();
				} );
			}( opts[ i ] ) );
		}

		var steppers = root.querySelectorAll( '.stepper' );
		for ( var s = 0; s < steppers.length; s++ ) {
			( function ( stepper ) {
				var field = stepper.getAttribute( 'data-field' );
				var min = parseInt( stepper.getAttribute( 'data-min' ), 10 );
				var max = parseInt( stepper.getAttribute( 'data-max' ), 10 );
				var value = stepper.querySelector( 'b' );
				var buttons = stepper.querySelectorAll( 'button' );
				function change( delta ) {
					state[ field ] = clamp( state[ field ] + delta, min, max );
					if ( value ) {
						value.textContent = state[ field ];
					}
					render();
				}
				buttons[ 0 ].addEventListener( 'click', function () {
					change( -1 );
				} );
				buttons[ 1 ].addEventListener( 'click', function () {
					change( 1 );
				} );
			}( steppers[ s ] ) );
		}

		var hosting = root.querySelector( '.toggle-pill' );
		if ( hosting ) {
			hosting.addEventListener( 'click', function () {
				state.hosting = ! state.hosting;
				hosting.className = state.hosting ? 'toggle-pill on' : 'toggle-pill';
				hosting.setAttribute( 'aria-pressed', state.hosting ? 'true' : 'false' );
				render();
			} );
		}
		render();
	}
```

- [ ] **Step 5: Run the test, verify it passes**

Run: `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8892 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test widgets-commerce.spec.js -g "pricing calculator" --workers=1`
Expected: PASS.

- [ ] **Step 6: PHPCS + lint the changed files**

Run: `vendor/bin/phpcs templates/pages/pricing.php` (ignore only `InvalidEOLChar`); `npm run lint`.
Expected: 0 real errors.

- [ ] **Step 7: Commit**

```bash
git add templates/pages/pricing.php assets/js/public-widgets.js tests/widgets-commerce.spec.js
git commit -m "feat: interactive pricing calculator (Plan 3a)"
```

---

## Task 3: Savings calculator

**Files:**
- Modify: `templates/pages/toolbox.php` (replace the `[data-widget="savings-calc"]` placeholder)
- Modify: `assets/js/public-widgets.js` (fill in `initSavingsCalc()`)
- Test: `tests/widgets-commerce.spec.js` (add savings-calc cases)

**Interfaces:**
- Consumes: `initSavingsCalc()` stub from Task 1; `blueworx_content_tools()` and `blueworx_content_solo_prices()` from `includes/public/content.php`.
- Produces: `[data-widget="savings-calc"]` markup — `.sv-row[data-slug][data-price][data-on]` per tool with a `.toggle-pill`, `[data-testid="solo-total"]`, `[data-testid="savings-line"]`.

- [ ] **Step 1: Write the failing test**

Add inside `tests/widgets-commerce.spec.js`:

```js
test.describe('Commerce widgets — savings calculator', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('default totals and per-tool recompute', async ({ page }) => {
    await page.goto('/toolbox');
    const solo = page.locator('[data-testid="solo-total"]');
    const save = page.locator('[data-testid="savings-line"]');

    await expect(solo).toHaveText('190');                       // sum 160 + 30 hosting
    await expect(save).toHaveText('You save $160/mo · $1,920/yr'); // 190 - 30

    // Toggle SureCart ($19) off.
    await page.locator('.sv-row[data-slug="surecart"] .toggle-pill').click();
    await expect(solo).toHaveText('171');                       // 190 - 19
    await expect(save).toHaveText('You save $141/mo · $1,692/yr'); // 171 - 30
  });
});
```

- [ ] **Step 2: Run it, verify it fails**

Run: `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8892 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test widgets-commerce.spec.js -g "savings calculator" --workers=1`
Expected: FAIL — the placeholder has no `[data-testid="solo-total"]`.

- [ ] **Step 3: Replace the placeholder in `templates/pages/toolbox.php`**

Find:

```php
			<div class="bw-plan3-placeholder" data-widget="savings-calc">
				<p><?php esc_html_e( 'Savings calculator — coming soon.', 'blueworx-labs-wordpress' ); ?></p>
			</div>
```

Replace with the calc, rendered from the content accessors. Note the totals are computed in PHP so the default is correct without JS:

```php
			<?php
			$blueworx_sv_tools  = blueworx_content_tools();
			$blueworx_sv_prices = blueworx_content_solo_prices();
			$blueworx_sv_hosting = 30;
			$blueworx_sv_toolbox = 30;
			$blueworx_sv_solo   = $blueworx_sv_hosting;
			foreach ( $blueworx_sv_tools as $blueworx_sv_tool ) {
				$blueworx_sv_solo += isset( $blueworx_sv_prices[ $blueworx_sv_tool['slug'] ] ) ? (int) $blueworx_sv_prices[ $blueworx_sv_tool['slug'] ] : 0;
			}
			$blueworx_sv_save = max( 0, $blueworx_sv_solo - $blueworx_sv_toolbox );
			?>
			<div class="calc" data-widget="savings-calc">
				<div class="calc-panel">
					<div class="sv-tools">
						<?php foreach ( $blueworx_sv_tools as $blueworx_sv_tool ) : ?>
							<?php $blueworx_sv_price = isset( $blueworx_sv_prices[ $blueworx_sv_tool['slug'] ] ) ? (int) $blueworx_sv_prices[ $blueworx_sv_tool['slug'] ] : 0; ?>
							<div class="sv-row" data-slug="<?php echo esc_attr( $blueworx_sv_tool['slug'] ); ?>" data-price="<?php echo esc_attr( (string) $blueworx_sv_price ); ?>" data-on="1" style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #F0F0F5">
								<div style="width:32px;height:32px;border-radius:9px;background:#F5F6FB;border:1px solid #EEEEF5;display:flex;align-items:center;justify-content:center;flex-shrink:0">
									<img src="<?php echo esc_url( BLUEWORX_LABS_URL . 'assets/img/tools/' . $blueworx_sv_tool['slug'] . '.png' ); ?>" alt="<?php echo esc_attr( $blueworx_sv_tool['name'] ); ?>" style="width:17px;height:17px;object-fit:contain" loading="lazy" />
								</div>
								<div style="flex:1;min-width:0">
									<div style="font-size:14px;font-weight:600;color:#0A0C29"><?php echo esc_html( $blueworx_sv_tool['name'] ); ?></div>
									<div style="font-size:12px;color:#8A8DA6">
										<?php
										/* translators: %d: monthly price in dollars. */
										echo esc_html( sprintf( __( '$%d/mo individually', 'blueworx-labs-wordpress' ), $blueworx_sv_price ) );
										?>
									</div>
								</div>
								<button type="button" class="toggle-pill on" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: tool name. */ __( 'Include %s', 'blueworx-labs-wordpress' ), $blueworx_sv_tool['name'] ) ); ?>" aria-pressed="true" style="transform:scale(.78);transform-origin:right center"></button>
							</div>
						<?php endforeach; ?>
					</div>
					<div style="display:flex;align-items:center;gap:12px;padding:14px 0 2px">
						<div style="width:32px;height:32px;border-radius:9px;background:#E8E7F7;display:flex;align-items:center;justify-content:center;flex-shrink:0">
							<?php blueworx_icon( 'server', '', 'width:16px;height:16px;display:block;color:#4338CA' ); ?>
						</div>
						<div style="flex:1;min-width:0">
							<div style="font-size:14px;font-weight:600;color:#0A0C29"><?php esc_html_e( 'Managed website hosting', 'blueworx-labs-wordpress' ); ?></div>
							<div style="font-size:12px;color:#8A8DA6"><?php esc_html_e( '$30/mo bought separately', 'blueworx-labs-wordpress' ); ?></div>
						</div>
						<span style="font-size:12px;font-weight:600;color:#178048;background:#E6F6EC;padding:5px 12px;border-radius:20px"><?php esc_html_e( 'Included', 'blueworx-labs-wordpress' ); ?></span>
					</div>
				</div>
				<div class="calc-out">
					<div class="cl"><?php esc_html_e( 'Buying everything individually', 'blueworx-labs-wordpress' ); ?></div>
					<div style="position:relative;z-index:1;font-weight:700;font-size:34px;letter-spacing:-.5px;color:rgba(255,255,255,.55);text-decoration:line-through;text-decoration-color:rgba(255,107,107,.75);text-decoration-thickness:3px">
						$<span data-testid="solo-total"><?php echo esc_html( (string) $blueworx_sv_solo ); ?></span><span style="font-size:15px;font-weight:500">/mo</span>
					</div>
					<div class="cl" style="margin-top:20px"><?php esc_html_e( 'With the BlueWorx Toolbox', 'blueworx-labs-wordpress' ); ?></div>
					<div class="cv">$30<span style="font-size:20px;font-weight:500;color:rgba(255,255,255,.6)">/mo</span></div>
					<div class="cp" style="margin-top:14px">
						<span data-testid="savings-line" style="display:inline-flex;align-items:center;gap:7px;font-size:14px;font-weight:600;color:#01D084;background:rgba(1,208,132,.12);border:1px solid rgba(1,208,132,.3);padding:8px 16px;border-radius:100px">
							<?php
							/* translators: 1: monthly saving, 2: yearly saving (thousands-separated). */
							echo esc_html( sprintf( __( 'You save $%1$s/mo · $%2$s/yr', 'blueworx-labs-wordpress' ), number_format_i18n( $blueworx_sv_save ), number_format_i18n( $blueworx_sv_save * 12 ) ) );
							?>
						</span>
					</div>
					<a href="<?php echo esc_url( home_url( '/contact' ) ); ?>" class="btn btn-brand btn-md" style="width:100%;text-decoration:none"><?php esc_html_e( 'Get the Toolbox', 'blueworx-labs-wordpress' ); ?></a>
				</div>
			</div>
```

Note: `blueworx_icon()`'s signature is `blueworx_icon( $name, $class = '', $style = '' )` and the `server` key exists in `blueworx_icon_paths()` (both verified in `includes/public/helpers-public.php`), so the call above is correct as written.

- [ ] **Step 4: Fill in `initSavingsCalc()` in `assets/js/public-widgets.js`**

```js
	function initSavingsCalc() {
		var root = document.querySelector( '[data-widget="savings-calc"]' );
		if ( ! root ) {
			return;
		}
		var hostingCost = 30;
		var toolboxCost = 30;
		var rows = root.querySelectorAll( '.sv-row' );
		var soloOut = root.querySelector( '[data-testid="solo-total"]' );
		var saveOut = root.querySelector( '[data-testid="savings-line"]' );

		function group( n ) {
			return String( n ).replace( /\B(?=(\d{3})+(?!\d))/g, ',' );
		}
		function render() {
			var solo = hostingCost;
			for ( var i = 0; i < rows.length; i++ ) {
				if ( '1' === rows[ i ].getAttribute( 'data-on' ) ) {
					solo += parseInt( rows[ i ].getAttribute( 'data-price' ), 10 );
				}
			}
			var save = Math.max( 0, solo - toolboxCost );
			if ( soloOut ) {
				soloOut.textContent = solo;
			}
			if ( saveOut ) {
				saveOut.textContent = 'You save $' + group( save ) + '/mo · $' + group( save * 12 ) + '/yr';
			}
		}

		for ( var i = 0; i < rows.length; i++ ) {
			( function ( row ) {
				var pill = row.querySelector( '.toggle-pill' );
				if ( ! pill ) {
					return;
				}
				pill.addEventListener( 'click', function () {
					var on = '1' === row.getAttribute( 'data-on' );
					row.setAttribute( 'data-on', on ? '0' : '1' );
					pill.className = on ? 'toggle-pill' : 'toggle-pill on';
					pill.setAttribute( 'aria-pressed', on ? 'false' : 'true' );
					render();
				} );
			}( rows[ i ] ) );
		}
		render();
	}
```

Note: the JS text `'You save $…/mo · $…/yr'` must match the PHP `__()` string exactly (same `·` middot and spacing). If the translated string differs, the test asserting the default line comes from PHP and the post-toggle line from JS — keep both literals identical.

- [ ] **Step 5: Run the test, verify it passes**

Run: `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8892 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test widgets-commerce.spec.js -g "savings calculator" --workers=1`
Expected: PASS.

- [ ] **Step 6: PHPCS + lint**

Run: `vendor/bin/phpcs templates/pages/toolbox.php` (ignore only `InvalidEOLChar`; `phpcbf` for array alignment); `npm run lint`.
Expected: 0 real errors.

- [ ] **Step 7: Commit**

```bash
git add templates/pages/toolbox.php assets/js/public-widgets.js tests/widgets-commerce.spec.js
git commit -m "feat: interactive savings calculator (Plan 3a)"
```

---

## Task 4: No-JS assertion, regression, zip, PR

**Files:**
- Test: `tests/widgets-commerce.spec.js` (add the no-JS server-HTML case)

- [ ] **Step 1: Add the no-JS progressive-enhancement test**

```js
test.describe('Commerce widgets — no-JS default state', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('server HTML already carries the default totals', async ({ request }) => {
    const pricing = await (await request.get('/pricing')).text();
    expect(pricing).toContain('data-testid="calc-total">$600<');

    const toolbox = await (await request.get('/toolbox')).text();
    expect(toolbox).toContain('data-testid="solo-total">190<');
    expect(toolbox).toContain('You save $160/mo · $1,920/yr');
  });
});
```

- [ ] **Step 2: Run the new case, verify it passes**

Run: `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8892 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test widgets-commerce.spec.js -g "no-JS" --workers=1`
Expected: PASS.

- [ ] **Step 3: Full suite twice, foreground, clean state between**

Run (twice back-to-back): `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8892 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test --workers=1 --reporter=line`
Expected: both green; the 2 intentional admin skips only; no public/marketing skips or failures.

- [ ] **Step 4: Build and verify the zip**

Run: `npm run build && unzip -l dist/blueworx-labs-wordpress.zip | grep -E "public-widgets.js|pricing.php|toolbox.php"`
Expected: `assets/js/public-widgets.js` present with forward slashes, nested one level.

- [ ] **Step 5: Commit + open PR**

```bash
git add tests/widgets-commerce.spec.js
git commit -m "test: assert commerce widgets render their default totals without JS (Plan 3a)"
git push -u origin plan3a-commerce-widgets
gh pr create --base main --head plan3a-commerce-widgets --title "Plan 3a: commerce interactive widgets" --body "..."
```

(If PR #41 has not yet merged to `main`, target `--base marketing-pages` instead, or note the dependency in the PR body.)

---

## Self-Review

**Spec coverage.** Billing toggle ✓ (Task 1), pricing calc ✓ (Task 2), savings calc ✓ (Task 3), progressive-enhancement no-JS default ✓ (Tasks 2–4), content.php single source via DOM `data-price` ✓ (Task 3), enqueue on owned pages ✓ (Task 1), version/changelog/readme ✓ (Task 1), zip verify ✓ (Task 4). No `wp_localize_script` — data read from the DOM, matching the refined spec.

**Placeholder scan.** Every code step carries complete code. The one external dependency — `blueworx_icon()`'s signature and the `server` icon key — was verified against `helpers-public.php` and stated definitively in Task 3 Step 3; nothing is left to guess.

**Type consistency.** `initBillingToggle` / `initPricingCalc` / `initSavingsCalc` named identically in Tasks 1–3. Test IDs `calc-total`, `solo-total`, `savings-line` and attributes `data-support`, `data-field`/`data-min`/`data-max`, `data-slug`/`data-price`/`data-on` are used identically in the markup and the JS/tests. The PHP savings-line string and the JS savings-line string are required to match verbatim (noted in Task 3 Step 4).

**Known risk.** The savings-line string duplication (PHP default render + JS recompute) is the one place a divergence would break the test silently — the plan flags it explicitly in both directions.
