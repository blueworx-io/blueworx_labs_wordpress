# Marketing Pages Implementation Plan (Plan 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Render all eight BlueWorx marketing pages from the plugin as templates, content hardcoded, on top of the rendering foundation merged in PR #40.

**Architecture:** Each page is a `templates/pages/<slug>.php` registered in `blueworx_public_pages()`, rendered by the existing document shell (`blueworx_public_document_open/close`, nav, footer). Static content ported from the front-end lives in a PHP data layer (`includes/public/content.php`) so Plan 2 hardcodes it and a later cycle can swap the source without touching templates. Interactive widgets (billing toggle, calculators, FAQ accordion, feature tabs, AI demo) are **out of scope — Plan 3**; pages that use them render their static shell now with a clearly-marked placeholder where the widget mounts.

**Tech Stack:** Procedural PHP 8.0+ (no classes), hand-written CSS already ported, ES5 vanilla JS, Playwright against the local WordPress harness.

## Global Constraints

- **Prefix everything** `blueworx` / `BLUEWORX` (PHPCS `PrefixAllGlobals`).
- **Text domain** `blueworx-labs-wordpress`, the only permitted one.
- **PHPCS** WordPress ruleset: tabs, Yoda conditions, `array()` long syntax, spaces inside parens. **No PHPCS errors in new code** — two slipped through in Plan 1 and had to be cleaned up; do not repeat.
- Every PHP file (templates included): `@package BlueWorxLabs` docblock then `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- **All output escaped:** `esc_html()`, `esc_html__()`, `esc_url()`, `esc_attr()`. Content strings via `esc_html__()` with the text domain.
- **Every page template keeps the `<main><div>…</div></main>` wrapper** — `globals.css:325` (`main > div > .sec:last-child`) depends on it. Losing it breaks CTA-band spacing.
- **URLs via `home_url()`**, never bare root-relative — the site may run in a subdirectory. Follow the nav/footer precedent from Plan 1.
- **The deliverable is the plugin zip the owner uploads by hand.** New top-level dirs must be in `scripts/build-zip.mjs`'s allowlist (`assets/` and `templates/` already are). Verify every new asset ships with `unzip -l`.
- **Tests must run, not skip.** The harness is a real WordPress:
  `node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up --plugin . --slug blueworx-labs-wordpress --dir .wp-test --port 8892` (admin/wptest-admin-pw, WP_LOGIN_PATH=admin_login). Re-provision after activation changes.
- **Never run a command in the background** — three agents stalled that way in Plan 1. Foreground only.
- **Leave the site clean** after each task: Site Protection off, `/` rendering the plugin Home page. Tests that mutate state restore it via the existing `restoreAll()` helper.
- Bump the version + `CHANGELOG.md` + `readme.txt` Stable tag on every task (`node scripts/version-check.mjs`).
- **Do NOT** touch `includes/rest/`, the portal, or build the Plan 3 interactive widgets.

---

## Source of truth

Markup and content come from the front-end at `c:\Users\LukeMcfarland\Documents\GitHub\bluegroup_project_blueworx`. You are porting rendered output, not React. The per-page section breakdown is in that repo's `app/<page>/page.tsx`. The ported stylesheet already contains every class these pages use — do not add CSS unless a page needs a rule that was inline in the source (as happened for the nav panels in Plan 1); if so, add it to `assets/css/public.css` and say why.

**Reusable helpers that already exist** (from Plan 1 — use, do not recreate): `blueworx_icon()`, `blueworx_blob()`, `blueworx_public_document_open/close()`, `blueworx_public_part()`, `blueworx_public_pages()`, `blueworx_public_nav_active_class()`.

**Shared presentational blocks that recur across pages** — build each once as a template part in Task 2 and reuse: `tech-hero`, `glass-card`, `proc-grid` (How We Work), `work-grid` card, `stats-band`, `testimonials`, `logos-band`, `svc` card. The source duplicates some of these inline across pages; consolidate here rather than porting the duplication.

---

## Task 1: Content data layer + optimised images

Everything else depends on this. No page renders real content without it.

**Files:**
- Create: `includes/public/content.php` (data accessors), `assets/img/` additions
- Modify: `includes/public/bootstrap.php` (require content.php)
- Test: `tests/public-content.spec.js`

**Interfaces produced:**
- `blueworx_content_tools(): array` — 12 tools, each `array( slug, name, desc, domain, category, popular, tagline, features[] )` where `features` is exactly 6 `array( icon, title, desc )`. Ported from `lib/data.ts` `TOOLBOX_TOOLS`, verbatim.
- `blueworx_content_solo_prices(): array` — slug ⇒ int, from `SOLO_PRICES`.
- `blueworx_content_toolbox_plans(): array` and `blueworx_content_retainer_plans(): array` — from `TOOLBOX_PLANS` / `RETAINER_PLANS`, each `array( name, desc, priceM, priceA, feat, pop, features[] )`. **Drop the `btn` raw-class-string field** from the source — templates decide their own button classes; carrying a CSS class in data was a smell.
- `blueworx_content_faqs(): array` — `array( q, a )`, from `FAQS`.
- `blueworx_content_reviews(): array` — `array( text, initials, name, role )`, from `HOME_REVIEWS`.
- `blueworx_content_tool( string $slug ): ?array` — one tool or null.

- [ ] **Step 1: Write the failing test**

`tests/public-content.spec.js` — a hermetic PHP-level test (follow the existing hermetic pattern in `tests/public-site.spec.js` that stubs WP and requires a real include). Assert `blueworx_content_tools()` returns 12 tools, each with exactly 6 features, that `surecart` has `popular === true`, and that every tool slug has a `solo_prices` entry (parity — the source has a fixtures-parity test guarding this).

- [ ] **Step 2: Run it, verify it fails** (function undefined).

- [ ] **Step 3: Port the data** into `includes/public/content.php`. Read `lib/data.ts` and transcribe all seven exports into PHP arrays behind the accessors above. This is transcription — get the values exactly (names, slugs, domains, prices, the `popular` flag on surecart only, all 12×6 feature rows). Each accessor wraps its array in `apply_filters( 'blueworx_content_<name>', $array )` so a later cycle can override without editing this file.

- [ ] **Step 4: Require it** from `includes/public/bootstrap.php` (before templates need it).

- [ ] **Step 5: Run the test, verify it passes.**

- [ ] **Step 6: Optimise and bundle the images.** Source images live in `bluegroup_project_blueworx/public/assets/` (5.1MB total; several JP/PNG over 1MB). For each image a marketing page uses (`about-illustration.jpg`, `contact-illustration.jpg`, `hero-image.png`, `feature-image-1..4.png`, `fig-collab.png`), produce a web-sized version (target ≤ 200KB each, max ~1600px on the long edge) in `assets/img/`. **Tooling:** no image library is installed and `sharp` is not an approved dep. Use `npx --yes sharp-cli` for a one-shot conversion without adding a dependency, or the platform's ImageMagick if present. If neither works in this environment, STOP and report — do not ship the 1MB+ originals, and do not add `sharp` to `package.json` without approval.

- [ ] **Step 7: Verify the images ship** and are actually smaller:
  `npm run build && unzip -l dist/blueworx-labs-wordpress.zip | grep img/` — confirm each is present and under target size.

- [ ] **Step 8: Bump (1.25.0, new feature), changelog, commit.**

---

## Tasks 2–9: the eight pages

Each task: register the page in `blueworx_public_pages()`, create `templates/pages/<slug>.php`, render the sections from the source in order, add a Playwright test asserting the page's landmark sections are present, verify the page renders at its URL without error, leave the site clean, bump patch version, commit. Each page ends with the shared footer/CTA (already automatic via the document shell) — do not re-add it.

**Common acceptance criteria for every page:**
- Renders at `home_url( '/<slug>' )` with HTTP 200 and a single `.bw-page` wrapper.
- Keeps the `<main><div>` wrapper.
- Nav marks the page active (exact for home, prefix otherwise — already handled by `blueworx_public_nav_active_class()`).
- All content via the Task 1 accessors or `esc_html__()` literals — no content invented that isn't in the source.
- No unescaped output; no PHPCS errors.
- A test asserts the page's distinctive sections exist (named per task below).

### Task 2: Home (`front-page` / `home.php`) + shared parts

Replace the Plan 1 placeholder `templates/pages/home.php`. **Also build the shared template parts** listed under "Source of truth" (tech-hero, glass-card, proc-grid, work-grid card, stats-band, testimonials, logos-band, svc card) since Home uses most of them and later pages reuse them. Source: `app/page.tsx` (9 sections: home-hero with timeline glass card + ticker, What We Do `.svc2`, LogosBand, Selected Work 3 cards, FeatureTabs **[Plan 3 placeholder]**, How We Work `.proc-grid`, Ongoing Partnership `.split`, ToolboxGrid, Testimonials).
**Test:** home-hero, `.svc2`, `.proc-grid`, testimonials present; the FeatureTabs region renders a labelled placeholder, not a broken widget.

### Task 3: About (`about.php`) — source `app/about/page.tsx`
Sections: tech-hero, Why BlueWorx `.af-wrap.about-why`, `.stats-band`, Our Team `.team-grid` (3), Client Success Stories `.work-grid` (3). 100% static — the cleanest page.
**Test:** hero, `.stats-band`, `.team-grid` with 3 cards, stories grid present.

### Task 4: Services (`services.php`) — source `app/services/page.tsx`
Sections: tech-hero 2-col with glass card, Service 01 with the inline analytics panel (port the hand-authored `<svg>` sparkline verbatim), How It Works `.proc-grid`, Service 02 dark `.af-wrap` with `GLASS_TOOLS` favicons (use bundled favicons from Plan 1's `assets/img/tools/`, not Google), Testimonials.
**Test:** hero, both service sections, `.proc-grid` present; tool favicons resolve to plugin URLs not google.com.

### Task 5: Work (`work.php`) — source `app/work/page.tsx`
Sections: tech-hero glass `results.log`, `.work-grid` of 6 `PROJECTS` (plain divs, not links, per source), `.stats-band`, testimonials block. Consolidate the source's locally-duplicated `ABOUT_STATS` and testimonial markup onto the shared parts from Task 2.
**Test:** hero, 6 work cards, `.stats-band`, testimonials present.

### Task 6: AI (`ai.php`) — source `app/ai/page.tsx`
Sections: tech-hero `.ai-hero` with `.claude-badge` and AiDemo **[Plan 3 placeholder]**, The Full Flow with AiPipeline **[Plan 3 placeholder]**, Model Guidance `.ai-models` (4), Approved Stack `.ai-chip` (10), What We Build `.ai-off-grid` (5). The AI page's CSS block already ported.
**Test:** `.claude-badge`, `.ai-models` (4), `.ai-stack`, `.ai-off-grid` present; the two Plan 3 regions render labelled placeholders.

### Task 7: Contact (`contact.php`) — source `app/contact/page.tsx`
Sections: tech-hero centered, `.contact-grid` (form + illustration), `.contact-cards` dark (3), FaqList **[Plan 3 placeholder — render the FAQ items as static `<details>` so they work without JS, and note Plan 3 may upgrade the animation]**, Testimonials.
**The contact form is a shortcode.** Render the form column by echoing `do_shortcode()` of a configurable shortcode (reuse the pattern/allowlist idea from the render endpoint work, or a simple option `blueworx_contact_form_shortcode`). If the option is empty, render a clearly-labelled placeholder — do NOT port the React `ContactForm`. This is the point where forms-as-shortcodes (the user's stated preference) lands.
**Test:** hero, `.contact-cards` (3), FAQ items present; the form region renders the shortcode output or the labelled placeholder.

### Task 8: Pricing (`pricing.php`) — source `app/pricing/page.tsx`
Static shell only. Sections: tech-hero `.pb-tall` with **BillingToggle [Plan 3]** and PlanCards (render the retainer plans from Task 1 as static cards showing the monthly price, with `data-price-m`/`data-price-a` attributes ready for Plan 3 to toggle), LogosBand, comparison `table.cmp` (`CMP_ROWS`), **PricingCalc [Plan 3 placeholder]**, **FaqList** (static `<details>`).
**Test:** plan cards render with both price data-attributes present, comparison table present, calculator region is a labelled placeholder.

### Task 9: Toolbox archive (`toolbox.php`) + tool detail (`single-tool.php`) — source `app/toolbox/page.tsx` and `app/toolbox/[slug]/page.tsx`
The most data-driven. Toolbox page: BillingToggle+PlanCards (toolbox plans, static as Task 8), LogosBand, comparison table, **SavingsCalc [Plan 3 placeholder]** at `#savings`, FaqList (static), ToolboxGrid.
Tool detail: this needs a **route per tool**. Register the 12 tools as owned pages under `/toolbox/<slug>` in `blueworx_public_pages()` (extend the registry + activation to create them), each rendering `single-tool.php` driven by `blueworx_content_tool( $slug )` — hero with 58px favicon tile + the 6 features as check rows, `#tool-why` with the same 6 as `.svc` cards, then a related-tools grid (first 4 others).
**Test:** the toolbox archive renders the grid of 12; `/toolbox/surecart` renders SureCart's name, tagline, the "Popular" pill, and its 6 features twice (hero list + why cards); a bad slug (`/toolbox/nope`) 404s.

---

## Task 10: Full-suite regression, zip, release

- [ ] Run the FULL suite twice back-to-back in the foreground; both pass, state clean between runs.
- [ ] `npm run lint` clean; PHPCS clean on all new files.
- [ ] `npm run build`; `unzip -l` confirms all eight page templates, `single-tool.php`, shared parts, and optimised images present, forward slashes, nested one level, and the zip is not bloated (should be well under ~2MB with optimised images).
- [ ] Final version bump (1.26.0), consolidated changelog entry, `readme.txt` synced.
- [ ] Open PR to `main`.

---

## Self-Review

**Spec coverage.** All eight marketing pages ✓ (Tasks 2–9), content hardcoded via a swappable data layer ✓ (Task 1), forms as shortcodes ✓ (Task 7), portal untouched ✓, subdirectory-safe URLs ✓ (global constraint), images optimised for hand-upload ✓ (Task 1). Plan 3 interactivity explicitly deferred with placeholders that must render, not break.

**Placeholders in the plan.** Tasks 2–9 say "port the sections from the source" rather than inlining hundreds of lines of markup — deliberate, matching Plan 1's successful nav/footer approach; every page carries named acceptance-criteria sections a reviewer checks. Each *decision* (drop `btn` field, bundle vs Google favicons, static `<details>` for FAQ, shortcode contact form) is stated explicitly.

**Type consistency.** `blueworx_content_*` accessor names and their array shapes are defined once in Task 1 and consumed by name in Tasks 2–9. Tool detail routing extends the same `blueworx_public_pages()` registry Plan 1 built.

**Known risk.** Task 9's per-tool routing adds 12 pages to activation — confirm idempotency and the front-page/collision protections from Plan 1 still hold with the larger registry. Task 7's `do_shortcode` in a template must not run arbitrary unauthenticated shortcodes — scope it to a single configured form shortcode, not free rendering.

## Plan 3 (not yet written)

The interactive widgets, each replacing a placeholder this plan leaves: billing toggle + plan-card price swap, pricing calculator, savings calculator, FAQ accordion animation, feature tabs, AI demo. Independent of each other; can parallelise.
