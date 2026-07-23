# Plan 3b — Showcase Interactive Widgets + contact a11y — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the remaining Plan 3 placeholders (feature tabs, AI demo, AI pipeline) with real interactive widgets, enhance the FAQ lists with single-open behaviour, and fix the contact-card inert-link accessibility issue — all via progressive enhancement.

**Architecture:** Extend `assets/js/public-widgets.js` (from Plan 3a) with new ES5 init functions wired into its existing `init()`. Each template's placeholder is replaced with the widget's full default-state markup; PHP renders the finished/first state so the page is complete with JS off, and JS enhances. Animated widgets respect `prefers-reduced-motion`.

**Tech Stack:** Procedural PHP 8.0+, ES5 vanilla JS, Playwright against the local WP harness. Source components (ground truth for markup/copy): `components/FeatureTabs.tsx`, `AiDemo.tsx`, `AiPipeline.tsx`, `FaqList.tsx`, `app/contact/page.tsx`.

## Global Constraints

- Branch `plan3b-showcase-widgets`, stacked on `plan3a-commerce-widgets` (base = plan3a HEAD).
- Prefix everything `blueworx`/`BLUEWORX`; text domain `blueworx-labs-wordpress` only.
- PHPCS WordPress ruleset (tabs, Yoda, `array()` long syntax, spaces in parens); no PHPCS errors in new code (CRLF `InvalidEOLChar` is the ignorable Windows artefact; `phpcbf` fixes array alignment).
- All PHP output escaped (`esc_html`/`esc_html__`/`esc_url`/`esc_attr`); raw echoed SVG/HTML carries `phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted markup.`.
- Vanilla ES5 only, no new dependencies; `npm run lint` clean.
- Keep each page's `<main><div>…</div></main>` wrapper; internal links via `home_url()`; bundled favicons only.
- Progressive enhancement: PHP prints the correct default; JS only rewrites text / toggles classes / drives animation. Animated widgets (AI demo, AI pipeline) skip their timers under `prefers-reduced-motion` and leave the PHP finished frame.
- Version 1.34.0 → **1.35.0** (feature) + `CHANGELOG.md` + `readme.txt` Stable tag; `node scripts/version-check.mjs` passes.
- **Never background a command — foreground only.** Harness: `node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up --plugin . --slug blueworx-labs-wordpress --dir .wp-test --port 8892` (admin/wptest-admin-pw, WP_LOGIN_PATH=admin_login). Re-provision after the first template change. Per task run only the showcase spec; full double-run at release.
- The new init functions must each no-op when their marker/selector is absent (the one script loads on every owned page).

**Test file:** all tasks add to `tests/widgets-showcase.spec.js` (ESM imports like the existing specs: `import { expect } from '@playwright/test'; import { test, isPlaceholder } from './helpers.js';`).

---

## Task 1: Contact-card accessibility (real `href`s)

**Files:**
- Modify: `templates/pages/contact.php` (the `$blueworx_contact_cards` array + the `.cc a` render at ~line 120)
- Test: `tests/widgets-showcase.spec.js`

- [ ] **Step 1: Write the failing test**

```js
import { expect } from '@playwright/test';
import { test, isPlaceholder } from './helpers.js';

test.describe('Showcase — contact-card accessibility', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('each contact card is a real, focusable link', async ({ page }) => {
    await page.goto('/contact');
    const links = page.locator('.contact-cards .cc a');
    await expect(links).toHaveCount(3);
    await expect(links.nth(0)).toHaveAttribute('href', /^tel:/);
    await expect(links.nth(1)).toHaveAttribute('href', /^https:\/\/wa\.me\//);
    await expect(links.nth(2)).toHaveAttribute('href', /^mailto:/);
  });
});
```

- [ ] **Step 2: Run it, verify it fails**

Run: `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8892 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test widgets-showcase.spec.js -g "contact-card" --workers=1`
Expected: FAIL — the `<a>` elements have no `href`.

- [ ] **Step 3: Add an `href` to each card and render it**

In `templates/pages/contact.php`, add an `href` key to each `$blueworx_contact_cards` entry:
- call card: `'href' => 'tel:+007045550127'`
- WhatsApp card: `'href' => 'https://wa.me/007045550127'`
- email card: `'href' => 'mailto:info@blueworx.com'`

Then change the render line from `<a><?php echo esc_html( $blueworx_contact_card['link'] ); ?></a>` to:

```php
							<a href="<?php echo esc_url( $blueworx_contact_card['href'] ); ?>"<?php echo 0 === strpos( $blueworx_contact_card['href'], 'https://' ) ? ' rel="noopener"' : ''; ?>><?php echo esc_html( $blueworx_contact_card['link'] ); ?></a>
```

- [ ] **Step 4: Run the test, verify it passes**

Run: `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8892 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test widgets-showcase.spec.js -g "contact-card" --workers=1`
Expected: PASS.

- [ ] **Step 5: PHPCS + commit**

Run: `vendor/bin/phpcs templates/pages/contact.php` (ignore only `InvalidEOLChar`).
```bash
git add templates/pages/contact.php tests/widgets-showcase.spec.js
git commit -m "fix(a11y): give contact cards real tel:/wa.me/mailto: links (Plan 2 follow-up)"
```

---

## Task 2: FAQ single-open accordion

**Files:**
- Modify: `assets/js/public-widgets.js` (add `initFaqAccordion()` + wire into `init()`)
- Test: `tests/widgets-showcase.spec.js`

**Interfaces:**
- Produces: `initFaqAccordion()`. No template change — enhances the existing `.faq-list > details.faq-item` on Contact / Pricing / Toolbox.

- [ ] **Step 1: Write the failing test**

```js
test.describe('Showcase — FAQ accordion', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('opening one FAQ closes the others in the same list', async ({ page }) => {
    await page.goto('/contact');
    const items = page.locator('.faq-list details.faq-item');
    await items.nth(0).locator('summary').click();
    await expect(items.nth(0)).toHaveAttribute('open', '');
    await items.nth(1).locator('summary').click();
    await expect(items.nth(1)).toHaveAttribute('open', '');
    await expect(items.nth(0)).not.toHaveAttribute('open', '');
  });
});
```

- [ ] **Step 2: Run it, verify it fails**

Run: `... npx playwright test widgets-showcase.spec.js -g "FAQ accordion" --workers=1`
Expected: FAIL — both stay open (native `<details>` allows multiple).

- [ ] **Step 3: Add `initFaqAccordion()` to `assets/js/public-widgets.js`**

Add this function inside the IIFE and call it from `init()`:

```js
	function initFaqAccordion() {
		var lists = document.querySelectorAll( '.faq-list' );
		for ( var i = 0; i < lists.length; i++ ) {
			( function ( list ) {
				var items = list.querySelectorAll( 'details.faq-item' );
				for ( var j = 0; j < items.length; j++ ) {
					items[ j ].addEventListener( 'toggle', function () {
						if ( ! this.open ) {
							return;
						}
						for ( var k = 0; k < items.length; k++ ) {
							if ( items[ k ] !== this && items[ k ].open ) {
								items[ k ].open = false;
							}
						}
					} );
				}
			}( lists[ i ] ) );
		}
	}
```

Add `initFaqAccordion();` to the `init()` body (alongside the existing calls).

- [ ] **Step 4: Run the test, verify it passes** (`-g "FAQ accordion"`). Expected: PASS.

- [ ] **Step 5: Lint + commit**

Run: `npm run lint`.
```bash
git add assets/js/public-widgets.js tests/widgets-showcase.spec.js
git commit -m "feat: single-open FAQ accordion enhancement (Plan 3b)"
```

---

## Task 3: AI pipeline cycling console

**Files:**
- Modify: `templates/pages/ai.php` (replace `[data-widget="ai-pipeline"]` placeholder)
- Modify: `assets/js/public-widgets.js` (add `initAiPipeline()` + wire into `init()`)
- Test: `tests/widgets-showcase.spec.js`

**Source:** `components/AiPipeline.tsx`. STEPS (n, icon, title, desc, model):
`01 doc "Brief" "Your goal becomes a scoped brief with in-scope, out-of-scope and acceptance criteria." "Claude Opus"`;
`02 workflow "Plan" "The brief becomes GitHub Milestones and scoped Issues, one per screen or feature." "Claude Sonnet"`;
`03 palette "Design" "Screens designed on your design system, then handed off to build." "Claude Opus"`;
`04 code "Build" "Each Issue built on its own branch, following our recipe book and standards." "Claude Sonnet"`;
`05 shield "Review" "Automated checks, Playwright tests and code review gate every pull request." "Claude Sonnet"`;
`06 zap "Deploy" "Merged and shipped to Netlify, WordPress or standalone, versioned and logged." "Automated"`.

- [ ] **Step 1: Write the failing test**

```js
test.describe('Showcase — AI pipeline', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('renders six steps with exactly one active by default', async ({ page }) => {
    await page.goto('/ai');
    const shell = page.locator('[data-widget="ai-pipeline"]');
    await expect(shell.locator('.ai-pipe-step')).toHaveCount(6);
    await expect(shell.locator('.ai-pipe-step.on')).toHaveCount(1);
    await expect(shell.locator('.ai-pipe-step').first()).toHaveText(/Brief/);
  });
});
```

- [ ] **Step 2: Run it, verify it fails.** Expected: FAIL — placeholder has no `.ai-pipe-step`.

- [ ] **Step 3: Replace the placeholder in `templates/pages/ai.php`**

Find:
```php
			<div class="bw-plan3-placeholder" data-widget="ai-pipeline">
				<p><?php esc_html_e( 'Pipeline visualisation — coming soon.', 'blueworx-labs-wordpress' ); ?></p>
			</div>
```
Replace with the `.ai-pipe-shell` markup ported from `AiPipeline.tsx`, keeping `data-widget="ai-pipeline"` on the shell and giving the **first** step `class="ai-pipe-step on"` (the rest `class="ai-pipe-step"`). Use a PHP array of the six steps and a `foreach`; render the icon via `blueworx_icon( $step['icon'] )`; escape all copy with `esc_html__`. Structure per source:

```php
			<?php
			$blueworx_ai_pipe_steps = array(
				array( 'n' => '01', 'icon' => 'doc',      'title' => __( 'Brief', 'blueworx-labs-wordpress' ),  'desc' => __( 'Your goal becomes a scoped brief with in-scope, out-of-scope and acceptance criteria.', 'blueworx-labs-wordpress' ), 'model' => __( 'Claude Opus', 'blueworx-labs-wordpress' ) ),
				array( 'n' => '02', 'icon' => 'workflow', 'title' => __( 'Plan', 'blueworx-labs-wordpress' ),   'desc' => __( 'The brief becomes GitHub Milestones and scoped Issues, one per screen or feature.', 'blueworx-labs-wordpress' ), 'model' => __( 'Claude Sonnet', 'blueworx-labs-wordpress' ) ),
				array( 'n' => '03', 'icon' => 'palette',  'title' => __( 'Design', 'blueworx-labs-wordpress' ), 'desc' => __( 'Screens designed on your design system, then handed off to build.', 'blueworx-labs-wordpress' ), 'model' => __( 'Claude Opus', 'blueworx-labs-wordpress' ) ),
				array( 'n' => '04', 'icon' => 'code',     'title' => __( 'Build', 'blueworx-labs-wordpress' ),  'desc' => __( 'Each Issue built on its own branch, following our recipe book and standards.', 'blueworx-labs-wordpress' ), 'model' => __( 'Claude Sonnet', 'blueworx-labs-wordpress' ) ),
				array( 'n' => '05', 'icon' => 'shield',   'title' => __( 'Review', 'blueworx-labs-wordpress' ), 'desc' => __( 'Automated checks, Playwright tests and code review gate every pull request.', 'blueworx-labs-wordpress' ), 'model' => __( 'Claude Sonnet', 'blueworx-labs-wordpress' ) ),
				array( 'n' => '06', 'icon' => 'zap',      'title' => __( 'Deploy', 'blueworx-labs-wordpress' ), 'desc' => __( 'Merged and shipped to Netlify, WordPress or standalone, versioned and logged.', 'blueworx-labs-wordpress' ), 'model' => __( 'Automated', 'blueworx-labs-wordpress' ) ),
			);
			?>
			<div class="ai-pipe-shell" data-widget="ai-pipeline">
				<div class="ai-pipe-glow"></div>
				<div class="ai-pipe-status">
					<span><span class="dotp"></span><?php esc_html_e( 'Live Pipeline', 'blueworx-labs-wordpress' ); ?></span>
					<span><?php esc_html_e( 'Brief → Deploy', 'blueworx-labs-wordpress' ); ?></span>
				</div>
				<div class="ai-pipe">
					<?php foreach ( $blueworx_ai_pipe_steps as $blueworx_ai_pipe_i => $blueworx_ai_pipe_step ) : ?>
						<div class="<?php echo 0 === $blueworx_ai_pipe_i ? 'ai-pipe-step on' : 'ai-pipe-step'; ?>">
							<span class="pn"><?php echo esc_html( $blueworx_ai_pipe_step['n'] ); ?></span>
							<div class="pic"><?php blueworx_icon( $blueworx_ai_pipe_step['icon'] ); ?></div>
							<h4><?php echo esc_html( $blueworx_ai_pipe_step['title'] ); ?></h4>
							<p><?php echo esc_html( $blueworx_ai_pipe_step['desc'] ); ?></p>
							<span class="model"><?php echo esc_html( $blueworx_ai_pipe_step['model'] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
```

Confirm the icon keys (`doc`, `workflow`, `palette`, `code`, `shield`, `zap`) exist in `blueworx_icon_paths()` — they are used elsewhere on the AI page, so they do.

- [ ] **Step 4: Add `initAiPipeline()` to `assets/js/public-widgets.js`** (wire into `init()`):

```js
	function initAiPipeline() {
		var shell = document.querySelector( '[data-widget="ai-pipeline"]' );
		if ( ! shell ) {
			return;
		}
		var steps = shell.querySelectorAll( '.ai-pipe-step' );
		if ( ! steps.length ) {
			return;
		}
		if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
			return;
		}
		var active = 0;
		setInterval( function () {
			steps[ active ].className = 'ai-pipe-step';
			active = ( active + 1 ) % steps.length;
			steps[ active ].className = 'ai-pipe-step on';
		}, 1300 );
	}
```

- [ ] **Step 5: Run the test, verify it passes** (`-g "AI pipeline"`). Expected: PASS.

- [ ] **Step 6: PHPCS + lint + commit**

```bash
git add templates/pages/ai.php assets/js/public-widgets.js tests/widgets-showcase.spec.js
git commit -m "feat: cycling AI pipeline console (Plan 3b)"
```

---

## Task 4: Feature tabs (Home)

**Files:**
- Modify: `templates/pages/home.php` (replace `[data-widget="feature-tabs"]` placeholder)
- Modify: `assets/js/public-widgets.js` (add `initFeatureTabs()` + wire into `init()`)
- Test: `tests/widgets-showcase.spec.js`

**Source:** `components/FeatureTabs.tsx`. Tabs (label, heading, desc, cta, color, pts (9 y-values), legend value):
- **Support** — "Support Guides" / "Get ahead by accessing our dedicated support guides, designed to give you an edge." / "View Guides" / `#4F46E5` / `150 118 138 82 110 64 96 74 88` / `120,456`
- **Toolbox** — "Digital Toolbox" / "Access a curated set of tools that power your website, automations, and integrations, all set up, managed, and maintained for you." / "View Toolbox" / `#A5A7FF` / `120 96 112 60 84 46 72 54 62` / `245,877`
- **Hosting** — "Website Hosting" / "Remove the headache of WordPress hosting with our high-performance hosting supported by integrated growth & security functionality." / "View Hosting" / `#3686F7` / `168 150 158 128 146 120 136 126 142` / `78,987`

XS grid = `[0,65,130,195,260,325,390,455,520]`, viewBox `520×210`. The Support default chart path is:
`M0,150 L65,118 L130,138 L195,82 L260,110 L325,64 L390,96 L455,74 L520,88`; area = that + ` L520,210 L0,210 Z`; min-dot at `cx=325 cy=64`; default colour `#4F46E5`; default legend value `120,456`.

- [ ] **Step 1: Write the failing test**

```js
test.describe('Showcase — feature tabs', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('switching tabs swaps the panel heading', async ({ page }) => {
    await page.goto('/');
    const root = page.locator('[data-widget="feature-tabs"]');
    await expect(root.locator('.af-text h2')).toHaveText('Support Guides');

    await root.locator('.tab-bar .tab', { hasText: 'Toolbox' }).click();
    await expect(root.locator('.af-text h2')).toHaveText('Digital Toolbox');

    await root.locator('.tab-bar .tab', { hasText: 'Hosting' }).click();
    await expect(root.locator('.af-text h2')).toHaveText('Website Hosting');
  });
});
```

- [ ] **Step 2: Run it, verify it fails.** Expected: FAIL — placeholder has no `.af-text`.

- [ ] **Step 3: Replace the placeholder in `templates/pages/home.php`**

Replace the `<div class="bw-plan3-placeholder" data-widget="feature-tabs">…</div>` with the full section ported from `FeatureTabs.tsx`, Support tab default. Requirements:
- Root: `<section class="features-dark" data-widget="feature-tabs">` with the blob, `.fd-header` (h2 "One Platform. Every Tool. Real Results." + `.fd-sub`), the `.tab-bar` of three buttons, and `.af-wrap` with `.af-panel` + `.af-text`. (Keep the exact copy from the source; escape via `esc_html__`.)
- Each `.tab` button carries: `data-tab` (0/1/2), `data-heading`, `data-desc`, `data-cta`, `data-color`, `data-value`, and `data-pts` (space-separated 9 y-values) — all from the table above. The Support button is `class="tab on"`, the others `class="tab off"`.
- Legend rows `.af-leg` (Support `on`, others `off`), each `<small><i style="background:#RRGGBB"></i>Label</small><b>value</b>` from the table.
- The SVG `.af-chart` (viewBox `0 0 520 210`, `preserveAspectRatio="none"`) with:
  - `<defs><linearGradient id="afGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#4F46E5" stop-opacity="0.16"/><stop offset="100%" stop-color="#4F46E5" stop-opacity="0"/></linearGradient></defs>`
  - three grid `<line>`s at y = 52 / 104 / 156 (stroke `#EFEFF0`)
  - `<path class="af-area" d="…area…" fill="url(#afGrad)"/>` (the Support area path)
  - `<path class="af-line" d="…Support path…" fill="none" stroke="#4F46E5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>`
  - `<circle class="af-dot" cx="325" cy="64" r="5" fill="#fff" stroke="#4F46E5" stroke-width="3"/>`
  (echo the SVG with a `phpcs:ignore …OutputNotEscaped` comment — it is static trusted markup built from the constants above.)
- `.af-text`: `<h2 class="h2" style="…">Support Guides</h2>`, `<p class="lead" style="…">…Support desc…</p>`, and the CTA `<a class="btn btn-brand btn-md" href="<?php echo esc_url( home_url( '/toolbox' ) ); ?>">View Guides <svg…/></a>` — **the CTA text must be the FIRST child node, followed by the arrow `<svg>`** (JS updates only that leading text node).

- [ ] **Step 4: Add `initFeatureTabs()` to `assets/js/public-widgets.js`** (wire into `init()`):

```js
	function initFeatureTabs() {
		var root = document.querySelector( '[data-widget="feature-tabs"]' );
		if ( ! root ) {
			return;
		}
		var tabs = root.querySelectorAll( '.tab-bar .tab' );
		var legs = root.querySelectorAll( '.af-legend .af-leg' );
		var chart = root.querySelector( '.af-chart' );
		if ( ! tabs.length || ! chart ) {
			return;
		}
		var xs = [ 0, 65, 130, 195, 260, 325, 390, 455, 520 ];
		var areaEl = chart.querySelector( '.af-area' );
		var lineEl = chart.querySelector( '.af-line' );
		var dotEl = chart.querySelector( '.af-dot' );
		var stops = chart.querySelectorAll( '#afGrad stop' );
		var head = root.querySelector( '.af-text h2' );
		var desc = root.querySelector( '.af-text p' );
		var cta = root.querySelector( '.af-text a' );

		function select( idx ) {
			var tab = tabs[ idx ];
			var pts = tab.getAttribute( 'data-pts' ).split( ' ' );
			var color = tab.getAttribute( 'data-color' );
			var d = '';
			var minY = Infinity;
			var dotI = 0;
			var i;
			for ( i = 0; i < pts.length; i++ ) {
				var y = parseFloat( pts[ i ] );
				d += ( i ? 'L' : 'M' ) + xs[ i ] + ',' + y + ( i === pts.length - 1 ? '' : ' ' );
				if ( y < minY ) {
					minY = y;
					dotI = i;
				}
			}
			if ( lineEl ) {
				lineEl.setAttribute( 'd', d );
				lineEl.setAttribute( 'stroke', color );
			}
			if ( areaEl ) {
				areaEl.setAttribute( 'd', d + ' L520,210 L0,210 Z' );
			}
			if ( dotEl ) {
				dotEl.setAttribute( 'cx', xs[ dotI ] );
				dotEl.setAttribute( 'cy', minY );
				dotEl.setAttribute( 'stroke', color );
			}
			for ( i = 0; i < stops.length; i++ ) {
				stops[ i ].setAttribute( 'stop-color', color );
			}
			for ( i = 0; i < tabs.length; i++ ) {
				tabs[ i ].className = i === idx ? 'tab on' : 'tab off';
			}
			for ( i = 0; i < legs.length; i++ ) {
				legs[ i ].className = i === idx ? 'af-leg on' : 'af-leg off';
			}
			if ( head ) {
				head.textContent = tab.getAttribute( 'data-heading' );
			}
			if ( desc ) {
				desc.textContent = tab.getAttribute( 'data-desc' );
			}
			if ( cta && cta.firstChild ) {
				cta.firstChild.nodeValue = tab.getAttribute( 'data-cta' ) + ' ';
			}
		}

		var t;
		for ( t = 0; t < tabs.length; t++ ) {
			( function ( idx ) {
				tabs[ idx ].addEventListener( 'click', function () {
					select( idx );
				} );
			}( t ) );
		}
		for ( t = 0; t < legs.length; t++ ) {
			( function ( idx ) {
				legs[ idx ].addEventListener( 'click', function () {
					select( idx );
				} );
			}( t ) );
		}
	}
```

- [ ] **Step 5: Run the test, verify it passes** (`-g "feature tabs"`). Expected: PASS.

- [ ] **Step 6: PHPCS + lint + commit**

```bash
git add templates/pages/home.php assets/js/public-widgets.js tests/widgets-showcase.spec.js
git commit -m "feat: interactive feature tabs on the home page (Plan 3b)"
```

---

## Task 5: AI demo animation (Home… AI page)

**Files:**
- Modify: `templates/pages/ai.php` (replace `[data-widget="ai-demo"]` placeholder)
- Modify: `assets/js/public-widgets.js` (add `initAiDemo()` + wire into `init()`)
- Test: `tests/widgets-showcase.spec.js`

**Source:** `components/AiDemo.tsx`. Prompt `MSG` = `Build a booking website with Stripe checkout + admin dashboard`. `CODE_LINES` = the 11 syntax-highlighted lines (port their exact span markup verbatim from the source).

- [ ] **Step 1: Write the failing test**

```js
test.describe('Showcase — AI demo', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('renders the finished demo frame in the server HTML', async ({ page, request }) => {
    await page.goto('/ai');
    const root = page.locator('[data-widget="ai-demo"]');
    await expect(root.locator('.ai-typed')).toContainText('Build a booking website');
    // Finished-frame default (present regardless of JS animation state):
    const html = await (await request.get('/ai')).text();
    expect(html).toContain('ai-site in');
  });
});
```

- [ ] **Step 2: Run it, verify it fails.** Expected: FAIL — placeholder has no `.ai-typed`.

- [ ] **Step 3: Replace the placeholder in `templates/pages/ai.php`**

Replace `<div class="bw-plan3-placeholder" data-widget="ai-demo">…</div>` with the `.ai-demo-root` markup ported from `AiDemo.tsx`, rendered in the **finished** state, keeping `data-widget="ai-demo"` on the root:
- `.ai-stages`: all three `.ai-stage` carry `on` (`<b>1</b>Prompt<span class="bar"></span>`, etc. per source).
- `.ai-demo-head`: three `<i></i>` + `<span class="lbl">generating</span>` (`esc_html__`).
- `.ai-prompt`: the `.pi` sparkle svg, then `.pt` with `<span class="ai-typed"><?php echo esc_html__( 'Build a booking website with Stripe checkout + admin dashboard', 'blueworx-labs-wordpress' ); ?></span><span class="ai-caret" style="display:none"></span>`.
- `.ai-code`: the 11 `<div class="cl in">…</div>` lines — port each line's inner span markup verbatim from `CODE_LINES` (echo with a `phpcs:ignore …OutputNotEscaped` comment; it is static trusted syntax-highlight markup). Every line gets class `cl in`.
- `.ai-site`: `class="ai-site in"` with the `.ai-site-bar` (three coloured `<i>` + `<span class="u">yourbrand.com/book</span>`) and `.ai-site-view` (`.ai-sv-hero`, `.ai-sv-row` of 3 divs, two `.ai-sv-line`).

- [ ] **Step 4: Add `initAiDemo()` to `assets/js/public-widgets.js`** (wire into `init()`):

```js
	function initAiDemo() {
		var root = document.querySelector( '[data-widget="ai-demo"]' );
		if ( ! root ) {
			return;
		}
		if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
			return;
		}
		var typed = root.querySelector( '.ai-typed' );
		var caret = root.querySelector( '.ai-caret' );
		var codeLines = root.querySelectorAll( '.ai-code .cl' );
		var site = root.querySelector( '.ai-site' );
		var stages = root.querySelectorAll( '.ai-stage' );
		if ( ! typed ) {
			return;
		}
		var msg = typed.textContent;
		var timers = [];

		function clearTimers() {
			for ( var i = 0; i < timers.length; i++ ) {
				clearTimeout( timers[ i ] );
			}
			timers = [];
		}
		function setStage( n ) {
			for ( var i = 0; i < stages.length; i++ ) {
				stages[ i ].className = i <= n ? 'ai-stage on' : 'ai-stage';
			}
		}
		function loop() {
			clearTimers();
			var t = 0;
			var i;
			typed.textContent = '';
			if ( caret ) {
				caret.style.display = '';
			}
			for ( i = 0; i < codeLines.length; i++ ) {
				codeLines[ i ].className = 'cl';
			}
			if ( site ) {
				site.className = 'ai-site';
			}
			setStage( 0 );
			t += 450;
			for ( i = 1; i <= msg.length; i++ ) {
				( function ( n ) {
					timers.push( setTimeout( function () {
						typed.textContent = msg.slice( 0, n );
					}, t + n * 26 ) );
				}( i ) );
			}
			t += msg.length * 26 + 600;
			timers.push( setTimeout( function () {
				if ( caret ) {
					caret.style.display = 'none';
				}
				setStage( 1 );
			}, t ) );
			for ( i = 1; i <= codeLines.length; i++ ) {
				( function ( n ) {
					timers.push( setTimeout( function () {
						codeLines[ n - 1 ].className = 'cl in';
					}, t + n * 140 ) );
				}( i ) );
			}
			t += codeLines.length * 140 + 550;
			timers.push( setTimeout( function () {
				setStage( 2 );
				if ( site ) {
					site.className = 'ai-site in';
				}
			}, t ) );
			t += 3400;
			timers.push( setTimeout( loop, t ) );
		}
		loop();
	}
```

- [ ] **Step 5: Run the test, verify it passes** (`-g "AI demo"`). Expected: PASS.

- [ ] **Step 6: PHPCS + lint + commit**

```bash
git add templates/pages/ai.php assets/js/public-widgets.js tests/widgets-showcase.spec.js
git commit -m "feat: animated AI demo, progressive enhancement (Plan 3b)"
```

---

## Task 6: Version bump, no-JS assertion, regression, zip, PR

**Files:**
- Modify: `blueworx-labs-wordpress.php`, `package.json`, `CHANGELOG.md`, `readme.txt`
- Test: `tests/widgets-showcase.spec.js` (no-JS default-state case)

- [ ] **Step 1: Bump version to 1.35.0** across the plugin header + `BLUEWORX_LABS_VERSION` constant + `package.json` + `readme.txt` Stable tag; add a `## 1.35.0` CHANGELOG entry ("Plan 3b: feature tabs, AI demo, AI pipeline, single-open FAQ, contact-card a11y links"). Run `node scripts/version-check.mjs` → OK.

- [ ] **Step 2: Add the no-JS default-state test**

```js
test.describe('Showcase — no-JS default state', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('server HTML carries the finished/default widget states', async ({ request }) => {
    const home = await (await request.get('/')).text();
    expect(home).toContain('Support Guides');           // feature-tabs default panel

    const ai = await (await request.get('/ai')).text();
    expect(ai).toContain('ai-pipe-step on');            // pipeline step 1 active
    expect(ai).toContain('ai-site in');                 // ai-demo finished frame
  });
});
```

- [ ] **Step 3: Run the whole showcase spec, verify green**

Run: `... npx playwright test widgets-showcase.spec.js --workers=1`
Expected: all showcase cases pass.

- [ ] **Step 4: Full suite twice, foreground, clean state between**

Run twice: `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8892 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test --workers=1 --reporter=line`
Expected: 0 failed both runs (the 2 intentional admin skips only; login-timeout flakes may recover on retry — judge on 0 failed). Verify `blueworx_frontend_protection_enabled=0` between runs.

- [ ] **Step 5: Build and verify the zip**

Run: `npm run build && unzip -l dist/blueworx-labs-wordpress.zip | grep -E "public-widgets.js|home.php|ai.php|contact.php"`
Expected: updated JS + templates present, forward slashes, nested one level.

- [ ] **Step 6: Commit + open PR (stacked on plan3a)**

```bash
git add -A
git commit -m "chore: bump to 1.35.0 + no-JS showcase assertions (Plan 3b)"
git push -u origin plan3b-showcase-widgets
gh pr create --base plan3a-commerce-widgets --head plan3b-showcase-widgets --title "Plan 3b: showcase widgets + contact a11y" --body "..."
```

---

## Self-Review

**Spec coverage.** Contact a11y ✓ (Task 1), FAQ single-open ✓ (Task 2), AI pipeline ✓ (Task 3), feature tabs ✓ (Task 4), AI demo ✓ (Task 5), progressive-enhancement no-JS defaults ✓ (Tasks 3–6), reduced-motion on both animated widgets ✓ (Tasks 3, 5), version/changelog/readme + zip ✓ (Task 6). Stacked on plan3a so FAQ/pricing/toolbox edits stay conflict-free.

**Placeholder scan.** Every JS init is given complete. PHP markup for the three replaced placeholders references the named source component for exact copy/markup, with the precise data-attribute contract, default active element, and (feature-tabs) the exact computed default SVG path/dot — the same "port from source" approach Plan 2 used successfully, not a vague "implement the widget".

**Type consistency.** `initFaqAccordion` / `initAiPipeline` / `initFeatureTabs` / `initAiDemo` are named identically in their task and the `init()` wiring. Markers `[data-widget="feature-tabs"|"ai-demo"|"ai-pipeline"]` match the existing placeholders' `data-widget` values (so the JS finds them after the placeholder `<div>` becomes the real widget root). Feature-tabs `data-pts`/`data-color`/`data-heading`/`data-desc`/`data-cta` are produced in Task 4's PHP and consumed by Task 4's JS. The AI-demo prompt string is rendered by PHP into `.ai-typed` and read back by JS (`msg = typed.textContent`) — single source.

**Known risks.**
- AI demo is the most complex (absolute-offset `setTimeout` animation). Mitigation: PHP renders the *finished* frame, which is both the no-JS state and the reduced-motion state, and the test asserts that server-HTML frame — never the mid-animation state — so the test is stable even if the animation timing is imperfect.
- Feature-tabs CTA text update depends on the CTA's first child being the text node (arrow `<svg>` second). Task 4 Step 3 states this explicitly.
