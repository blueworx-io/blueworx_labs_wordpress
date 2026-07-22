# Plan 3a — Commerce Interactive Widgets (design)

**Date:** 2026-07-22
**Depends on:** Plan 2 (marketing pages) — branch `marketing-pages` / PR #41. Plan 3a
modifies `pricing.php`, `toolbox.php` and `parts/plan-cards.php`, which exist only on that
branch, so this work is based on `marketing-pages`.

## Goal

Replace the static "coming soon" placeholders for the three revenue-relevant widgets with
working interactivity, using progressive enhancement: PHP renders each widget's real
default state, and vanilla ES5 JavaScript enhances it on interaction. The remaining four
widgets (feature tabs, AI demo, AI pipeline, FAQ accordion) are Plan 3b — a separate spec
and PR.

Widgets in scope:
1. **Billing toggle** — Pricing and Toolbox pages. Swaps every plan card's price and
   sub-label between monthly and annual.
2. **Pricing calculator** — Pricing page. Support level + update packs + sites + hosting
   add-on → live monthly total.
3. **Savings calculator** — Toolbox page. Per-tool include/exclude toggles → "buy
   individually" total vs the Toolbox price, and the resulting saving.

Source components ported: `components/PricingCalc.tsx`, `components/SavingsCalc.tsx`, and
the billing-toggle behaviour shared with `components/PlanCards`/`Plans.tsx`.

## Principles

- **Progressive enhancement, not JS rendering.** Every widget's full markup is emitted by
  PHP in a correct default state. JS only rewrites text and toggles classes. With
  JavaScript disabled the pages stay readable and every price/total shown is correct.
  This matches the billing toggle and FAQ, which already render real static markup, and
  honours the house accessibility rule (full keyboard access, readable without JS).
- **content.php stays the single source of content.** The savings calculator's tool list
  and solo prices come from `blueworx_content_tools()` and `blueworx_content_solo_prices()`,
  handed to JS via `wp_localize_script`. No tool data is duplicated in JS.
- **No new dependencies, no CSS work.** Every class these widgets use is already in
  `assets/css/public.css` (verified: `.calc`, `.calc-panel`, `.stepper`, `.toggle-pill`,
  `.sv-tools`, `.bill-toggle`, `.plan-price`). Vanilla ES5 only, consistent with
  `public-nav.js`. If a state rule turns out to be missing, add it to `public.css` with a
  one-line reason (not expected).

## Architecture

### Script: `assets/js/public-widgets.js`
- One file, vanilla ES5, IIFE, no dependencies, enqueued in the footer.
- Self-initialising: on `DOMContentLoaded` it scans for each `[data-widget]` marker and
  runs that widget's init; each init returns early if its marker is absent, so the same
  file is safe on every owned page. Enqueued on all owned pages (matching `public-nav.js`);
  on pages with no commerce widget it is an inexpensive no-op. Not conditionally scoped per
  page — the file is tiny and the marker scan is the guard.
- Three init functions: `initBillingToggle()`, `initPricingCalc()`, `initSavingsCalc()`.

### Enqueue: `includes/public/assets.php`
- Register/enqueue `blueworx-public-widgets` (`assets/js/public-widgets.js`), footer,
  version via `blueworx_get_admin_asset_version()`, in `blueworx_enqueue_public_assets()`
  alongside the existing nav script (owned pages only).
- `wp_localize_script( 'blueworx-public-widgets', 'blueworxWidgets', array( 'tools' =>
  [ {slug,name,domain} × 12 ], 'soloPrices' => { slug: int }, 'faviconBase' =>
  BLUEWORX_LABS_URL . 'assets/img/tools/' ) )`. Only the fields the savings calc needs —
  not the full 6-feature tool arrays.

### Data shapes handed to JS
- `blueworxWidgets.tools`: array of `{ slug, name, domain }` in registry order (from
  `blueworx_content_tools()`).
- `blueworxWidgets.soloPrices`: `{ slug: int }` (from `blueworx_content_solo_prices()`).
- `blueworxWidgets.faviconBase`: string, so JS builds `faviconBase + slug + '.png'` —
  bundled favicons only, never Google.

## Widget details

### 1. Billing toggle (`[data-widget="billing-toggle"]`, on Pricing + Toolbox)
Markup already exists (two `<button>`s, Monthly `.on`). Plan cards already carry
`data-price-m` / `data-price-a` on `.plan-price` and `data-sub-m` / `data-sub-a` on its
`<em>`.
- **JS:** click either button → set `.on` on the clicked button, remove from the other;
  for every `.plan-price` on the page, set its `<b>` to `'$' + data-price-{m|a}` and its
  `<em>` text to `data-sub-{m|a}`.
- **Default (no JS):** monthly, already rendered by PHP.
- **A11y:** the toggle container gets `role="group"`; buttons already native, focusable;
  add `aria-pressed` reflecting selection. Keyboard works natively (buttons).

### 2. Pricing calculator (`[data-widget="pricing-calc"]`, Pricing page)
Replace the placeholder `<div>` with the full `.calc` markup (ported from
`PricingCalc.tsx`), default state rendered by PHP:
- Support level: Essential / Growth / Advanced, **Growth `.on`** by default.
- Update packs stepper: default **2**, clamp 1–6.
- Websites stepper: default **1**, clamp 1–5.
- Managed hosting `.toggle-pill.on` by default.
- Output `.calc-out` with `[data-testid="calc-total"]` showing the default total.
- **Rates (ported constants, live in JS):** `BASE = { essential:200, growth:500,
  advanced:750 }`; total = `BASE[support] + (updates−1)*60 + (sites−1)*120 +
  (hosting?40:0)`. Default = `500 + 60 + 0 + 40 = 600`. **PHP computes and prints the same
  default** so server HTML and first JS render agree. (Correcting the design-review note:
  the default total is **$600**, not $560 — 500 base + one extra update pack at 60 +
  hosting 40.)
- **JS:** handlers on the three support buttons (swap `.on`), the two steppers' −/+
  (clamped), and the hosting pill (toggle `.on` + `aria-pressed`); recompute and write
  `[data-testid="calc-total"]` as `'$' + total`.

### 3. Savings calculator (`[data-widget="savings-calc"]`, Toolbox page)
Replace the placeholder with the full `.calc` markup (ported from `SavingsCalc.tsx`),
default state rendered by PHP from the content accessors:
- `.sv-tools`: one row per tool (12) — bundled favicon (`assets/img/tools/<slug>.png`),
  name, `"$<solo>/mo individually"`, and an include `.toggle-pill.on` (all included by
  default). Each row carries `data-slug` and `data-price` so JS needs no lookup for the
  default and the localized data covers dynamic recompute.
- Hosting row: "Managed website hosting", "$30/mo bought separately", "Included" pill
  (static, always counted).
- `.calc-out`: struck-through "buy individually" total, the `$30` Toolbox price, and the
  "You save $X/mo · $Y/yr" pill.
- **Constants (JS):** `HOSTING = 30`, `TOOLBOX = 30`. Solo total = Σ(included tool prices)
  + HOSTING; saving = `max(0, solo − TOOLBOX)`; yearly = `saving * 12` formatted with
  thousands separators.
- **Default (PHP):** all tools included, so PHP computes solo = Σ all soloPrices + 30 and
  prints it, the $30 Toolbox price, and the default saving — correct without JS.
- **JS:** toggling a tool's pill flips its included state (`.on` + `aria-pressed`),
  recomputes the solo total, the Toolbox line (constant), and the saving pill.

## Testing (Playwright, foreground, small per-widget specs)

Run against the local WordPress harness (port 8892). New spec(s), e.g.
`tests/widgets-commerce.spec.js`:
- **Billing toggle:** on `/pricing` and `/toolbox`, a known plan shows its monthly `<b>`
  price + "per month"; clicking Annual shows the annual `<b>` + "per month, billed
  yearly"; clicking Monthly restores. (Assert on a plan whose monthly ≠ annual.)
- **Pricing calc:** default `[data-testid="calc-total"]` is `$600`; selecting Essential
  → `$300` (200+60+40); +1 website → `$720` (600+120); toggling hosting off → `$560`;
  a stepper at its bound does not exceed the clamp.
- **Savings calc:** default solo total = Σ all solo prices + 30 and the saving = solo−30;
  toggling one known tool off reduces the solo total by exactly that tool's price and
  updates the saving.
- **No-JS assertion:** fetch the raw server HTML of `/pricing` and `/toolbox` and assert
  the default `calc-total` / savings figures are present in the markup (widgets render
  without JS).
- State restored via the existing `restoreAll()` helper; site left clean.

## Hygiene / deliverables

- Enqueue in `assets.php`; `wp_localize_script` payload as above.
- Version bump 1.33.0 → **1.34.0** (feature); `CHANGELOG.md` + `readme.txt` Stable tag;
  `node scripts/version-check.mjs` passes.
- `npm run build`; `unzip -l` confirms `assets/js/public-widgets.js` ships (assets/ already
  allowlisted).
- PHPCS clean on changed PHP (CRLF `InvalidEOLChar` is the ignorable Windows artefact);
  `npm run lint` (eslint) clean on the new JS.
- Never run a command in the background (foreground only). Re-provision the harness after
  the asset/enqueue change.

## Out of scope (Plan 3b)

Feature tabs (Home), AI demo + AI pipeline (AI page), FAQ accordion animation
(Contact/Pricing/Toolbox). Independent; their own spec, plan and PR.
