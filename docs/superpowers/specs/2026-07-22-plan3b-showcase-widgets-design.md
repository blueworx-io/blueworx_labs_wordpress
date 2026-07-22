# Plan 3b — Showcase Interactive Widgets + contact a11y (design)

**Date:** 2026-07-22
**Depends on / stacked on:** Plan 3a (`plan3a-commerce-widgets`, PR #42), which in turn
stacks on Plan 2 (`marketing-pages`, PR #41). Plan 3b touches `home.php`, `ai.php`,
`contact.php`, `pricing.php`, `toolbox.php` (Plan 2 pages) and extends
`assets/js/public-widgets.js` (created by Plan 3a). Stacking linearly keeps the FAQ changes
to `pricing.php`/`toolbox.php` conflict-free with 3a's calc changes to the same files.

## Goal

Replace the remaining Plan 3 placeholders with their real interactive widgets, and fix the
one accessibility follow-up from Plan 2's review — all via the same progressive-enhancement
pattern as Plan 3a (PHP renders a correct, complete default state; vanilla ES5 JS enhances;
animations respect `prefers-reduced-motion`).

In scope:
1. **Contact a11y fix** — the three contact cards render `<a>…</a>` with no `href` (inert,
   not keyboard-focusable — a faithful port of the source, flagged in Plan 2's review). Give
   each a real `href` (`tel:` / WhatsApp `https://wa.me/` / `mailto:`).
2. **FAQ accordion** — the FAQ lists already render accessible native `<details>`; enhance
   them with single-open behaviour (opening one closes its siblings). JS-only, no template
   change, native keyboard semantics preserved.
3. **AI pipeline** — the 6-step pipeline console; PHP renders all six with step 1 active,
   JS cycles the active step every 1.3s. `prefers-reduced-motion` → no cycling.
4. **Feature tabs** — the Home "One Platform" tabbed analytics section; PHP renders the
   Support tab active, JS switches tabs (recomputes the SVG line chart, swaps heading /
   copy / CTA / legend). No animation loop, so reduced-motion is N/A.
5. **AI demo** — the hero prompt→code→site animation; PHP renders the completed final frame
   (prompt filled, all code lines shown, site preview visible) so it is complete without JS;
   JS runs the looping animation. `prefers-reduced-motion` → leave the final frame, no loop.

Source components (ground truth for markup/copy — port exactly, as Plan 2 did):
`components/FeatureTabs.tsx`, `components/AiDemo.tsx`, `components/AiPipeline.tsx`,
`components/FaqList.tsx`, `app/contact/page.tsx`.

Out of scope: any new page, the portal, `includes/rest/`. This is the last Plan 3 cycle.

## Principles (same as Plan 3a)

- **Progressive enhancement.** PHP prints the full, correct default state; JS only rewrites
  text and toggles classes / drives animation. Every page is complete and readable with JS
  off. For the two animated widgets the PHP default is the *finished* frame, which doubles
  as the `prefers-reduced-motion` state.
- **DOM is the runtime source.** Feature-tabs carries all per-tab data (copy, colour, chart
  points, legend value) as `data-*` on the tab buttons; JS reads them off the DOM. No
  `wp_localize_script`, no copy duplicated in JS (keeps everything translatable in PHP).
- **No new dependencies, no CSS work.** All classes are already in `public.css` (verified:
  `.features-dark`, `.tab-bar`, `.af-*`, `.ai-demo*`, `.ai-pipe*`, `.faq-item`). Vanilla
  ES5 only, extending the existing `public-widgets.js` IIFE with new init functions, each a
  no-op when its marker/selector is absent.
- **Reduced motion.** The two animated widgets (AI demo, AI pipeline) check
  `window.matchMedia('(prefers-reduced-motion: reduce)')` and skip their timers, leaving the
  PHP-rendered finished state.

## Widget contracts

### Contact a11y
Add an `href` to each contact-card array entry and render `<a href="…">`:
- call (`+00 (704) 555-0127`) → `tel:+007045550127` (digits + leading `+`).
- WhatsApp (same number) → `https://wa.me/007045550127`.
- email (`info@blueworx.com`) → `mailto:info@blueworx.com`.
Add `rel="noopener"` to the WhatsApp (external) link. The number is a placeholder; the fix
is about link semantics and keyboard focusability, not the literal digits.

### FAQ accordion (`initFaqAccordion`)
- Select each `.faq-list`; within a list, on any `<details>`'s `toggle` event, if it just
  opened, close every sibling `<details>` in the same list. Native `<summary>` keyboard
  focus/toggle is untouched. No markup change; works as plain `<details>` with JS off.

### AI pipeline (`initAiPipeline`, marker `[data-widget="ai-pipeline"]` on `ai.php`)
- PHP replaces the placeholder with the `.ai-pipe-shell` markup ported from
  `AiPipeline.tsx`: all six `.ai-pipe-step` (n, `blueworx_icon(icon)`, title, desc, model),
  the **first** carrying `on`. JS cycles the `on` class 0→5→0 every 1300ms; reduced-motion →
  no interval, step 1 stays lit.

### Feature tabs (`initFeatureTabs`, marker `[data-widget="feature-tabs"]` on `home.php`)
- PHP replaces the placeholder with the full `.features-dark` section ported from
  `FeatureTabs.tsx`, Support tab active by default. Three `.tab` buttons each carry:
  `data-tab` (index), `data-heading`, `data-desc`, `data-cta`, `data-color`,
  `data-value` (legend figure), `data-pts` (nine space-separated y-values). The `.af-panel`
  renders the Support default: legend (Support `.af-leg on`), the SVG (`#afGrad` gradient,
  three grid lines, area + line path + min-dot computed from Support's pts), and `.af-text`
  (Support heading/desc/CTA).
- JS on tab click: mark the clicked `.tab`/`.af-leg` `on`; read the tab's `data-*`; rebuild
  the area/line `d` and reposition the dot from `data-pts` (XS grid `[0,65,…,520]`, area
  closes `L520,210 L0,210 Z`, dot at the min y); set the gradient stops' colour and the
  line/dot stroke to `data-color`; swap `.af-text` heading/desc/CTA and the CTA stays
  `home_url('/toolbox')`.
- Chart geometry constants (`XS`, viewBox `520×210`, grid lines) live in JS — geometry, not
  content. All copy/colour/points come from the DOM `data-*`.

### AI demo (`initAiDemo`, marker `[data-widget="ai-demo"]` on `ai.php`)
- PHP replaces the placeholder with the `.ai-demo-root` markup ported from `AiDemo.tsx`,
  rendered in the **finished** state: `.ai-typed` = the full prompt string, every `.ai-code .cl`
  carrying `in`, `.ai-site` carrying `in`, all three `.ai-stage`s `on`, caret hidden.
- JS reads the prompt target from `.ai-typed`'s text, then runs the source's loop (reset →
  type the prompt → reveal code lines → show site → pause → repeat) using `setTimeout`,
  guarded by an `alive` flag. On `prefers-reduced-motion: reduce`, JS does nothing (the PHP
  finished frame stays). Timings ported from the source (26ms/char, 140ms/line, etc.).

## Testing (Playwright, foreground, per-widget; extends `tests/widgets-showcase.spec.js`)

- **Contact a11y:** `/contact` — the three `.cc a` have `href` starting `tel:`, `https://wa.me/`,
  `mailto:` respectively.
- **FAQ:** `/contact` (or `/toolbox`) — open FAQ item 1, then open item 2; assert item 1 is
  no longer `[open]` (single-open). With JS, `<details>` still toggles.
- **AI pipeline:** `/ai` — the `.ai-pipe-shell` renders 6 `.ai-pipe-step`; exactly one has
  `on` initially. (Cycling is time-based/decorative — assert structure + initial active, not
  the interval, to avoid a flaky timing test.)
- **Feature tabs:** `/ai`… no — `/` (home) — clicking the "Toolbox" tab changes `.af-text h2`
  to "Digital Toolbox" and the active `.tab` moves; clicking "Hosting" → "Website Hosting".
- **AI demo:** `/ai` — the `.ai-demo-root` renders with the prompt text present and the site
  preview `.ai-site.in` in the server HTML (finished-frame default, so the assertion is
  stable regardless of JS animation state).
- **No-JS default state:** server HTML of `/` and `/ai` already contains the feature-tabs
  Support heading, the pipeline 6 steps, and the AI-demo finished frame.
- State restored via `restoreAll()`; site left clean; run only the showcase spec per task.

## Hygiene / deliverables

- Extend `assets/js/public-widgets.js` (new init fns wired into the existing `init()`).
- Version bump 1.34.0 → **1.35.0** (feature); `CHANGELOG.md` + `readme.txt` Stable tag;
  `node scripts/version-check.mjs` passes.
- `npm run build`; `unzip -l` confirms the updated JS + edited templates ship.
- PHPCS clean on changed PHP (CRLF `InvalidEOLChar` ignorable); `npm run lint` clean.
- Foreground only; re-provision the harness after the enqueue is unchanged (no enqueue
  change this plan — the script handle already loads), but re-provision anyway after the
  first template change to be safe. Full suite twice at release.
