# On-Page Translation — Design Spec

**Date:** 2026-07-26
**Feature:** On-page visitor translation for the BlueWorx Labs WordPress plugin (`blueworx-labs-wordpress`)
**Target version:** 1.37.0 (minor)

## 1. Goal

Replace the Weglot plugin on BlueWorx-managed WordPress sites with a built-in feature that
lets a visitor read the current page in another language, at **zero recurring cost** — no
licence, no API key, no third-party request.

The mechanism is the **Chrome built-in Translator API**: an on-device translation model
exposed to JavaScript in Chrome/Edge 138+. Nothing the visitor reads leaves their machine,
and there is no per-word billing.

This is a **visitor convenience feature, not an SEO feature**. It deliberately does not
reproduce Weglot's paid value (indexed `/fr/` URLs, `hreflang`, crawlable translated
markup). See §8.

## 2. Decisions from brainstorming

| # | Decision | Note |
|---|----------|------|
| D1 | **Classic WordPress frontend only.** The headless REST layer is untouched. | The feature is used only on sites where this plugin renders the frontend. |
| D2 | **Chrome built-in Translator API**, no paid API, no free-tier public API, no fallback engine. | Explicit constraint: no extra licences or APIs. Unsupported browsers get no widget (D8). |
| D3 | **Floating corner button**, auto-injected sitewide. No shortcode/block in this version. | Closest to the Weglot default; nothing to place per-theme. |
| D4 | **Whole page translated, minus exclusions.** | Nav, footer and body all translate. Exclusion list protects brand names and code. |
| D5 | **Choice remembered across visits** via `localStorage`, re-applied on every page load. | Chosen over session-only for better returning-visitor UX. |
| D6 | **Source language = site locale** from `get_locale()`, mapped to a BCP-47 base tag. Not configurable in this version. | One less setting; correct on every real site. |
| D7 | Feature lives in the existing registry and **defaults ON** like every other feature. | Consistent with `blueworx_feature_enabled()`. |
| D8 | Where the API is missing or the pair is unavailable, the widget **renders nothing at all**. | No broken control, no misleading "translate" affordance in Safari/Firefox. |
| D9 | Attributes (`alt`, `title`, `placeholder`, `aria-label`) translate alongside text nodes. | Otherwise buttons and images stay untranslated and it looks half-done. |
| D10 | Restoring the source language is done **in-memory, without a page reload**. | Originals are retained for the lifetime of the page. |

## 3. Architecture

Four new files, following the plugin's existing one-concern-per-file layout.

| File | Responsibility |
|------|----------------|
| `includes/translate.php` | Settings detail panel, sanitisation/save, frontend enqueue, widget root markup, config payload. |
| `assets/js/translate-widget.js` | Capability detection, widget UI, DOM walking, translate/restore, mutation handling. |
| `assets/css/translate-widget.css` | Pill and dropdown styling, four corner positions, focus states. |
| `tests/translate.spec.js` | Playwright coverage against a stubbed `window.Translator`. |

Registration in `blueworx-labs-wordpress.php`: one `require_once` for
`includes/translate.php`, placed after `page-excerpts.php`.

### 3.1 Feature registry

A new section is added to `blueworx_get_feature_sections()`:

```php
'translation' => __( 'Translation', 'blueworx-labs-wordpress' ),
```

placed after `content`. One new definition in `blueworx_get_feature_definitions()`:

```php
'translate' => array(
    'label'       => __( 'On-page translation', 'blueworx-labs-wordpress' ),
    'description' => __( 'Adds a floating language button that translates the page on the visitor\'s own device. Chrome and Edge only.', 'blueworx-labs-wordpress' ),
    'section'     => 'translation',
    'detail'      => 'translate',
),
```

The `detail` key renders an **inline settings panel** beneath the toggle on the Enhancements
page — the same mechanism Login, Site Protection and Client Roles already use. No new
submenu page.

`blueworx_render_feature_detail()` in `admin-settings.php` is already a long if-chain, so the
translate branch is a two-line delegation to `blueworx_translate_render_detail()` living in
`includes/translate.php`. Likewise `blueworx_save_feature_settings()` gains a one-line call
to `blueworx_translate_save_settings( $_POST )`, which does its own sanitisation. All
translation logic stays in its own file; `admin-settings.php` does not grow.

### 3.2 Options

| Option | Type | Default | Sanitisation |
|--------|------|---------|--------------|
| `blueworx_translate_languages` | array of BCP-47 base tags | `array( 'fr', 'de', 'es' )` | Intersected against the supported-language allowlist (§3.3); anything unknown is dropped. |
| `blueworx_translate_position` | string | `bottom-right` | One of `bottom-right`, `bottom-left`, `top-right`, `top-left`; anything else falls back to the default. |
| `blueworx_translate_label` | string | `Language` | `sanitize_text_field`, trimmed, capped at 40 characters; empty falls back to the default. |
| `blueworx_translate_exclusions` | array of CSS selectors | `array()` | Textarea, one per line. Each line `sanitize_text_field`'d, trimmed, capped at 200 characters and 50 lines. Selectors are validated client-side in a `try/catch` around `querySelectorAll`; an invalid selector is skipped, never thrown. |

Saving happens inside the existing `admin_post_blueworx_save_feature_settings` handler, which
already performs the capability check (`manage_options`), the
`blueworx_client_roles_should_block_console()` check, `check_admin_referer()`, and the
redirect with a transient notice. `blueworx_translate_save_settings()` only sanitises and
writes the four options above — it never trusts a value that did not survive the
allowlists.

All four options are removed in `uninstall.php` alongside the existing cleanup.

### 3.3 Supported languages

`blueworx_translate_language_labels()` returns the master ordered map of BCP-47 base tag →
English label:

`ar, bn, de, en, es, fr, hi, it, ja, ko, nl, pl, pt, ru, tr, vi, zh`

`blueworx_translate_supported_languages()` returns that map **minus the site's own source
language**, and is what renders the settings checkboxes and validates what is saved — a site
can never offer its own language as a translation target. `en` is in the master map because
the switcher needs a label for the source language when the site itself is English, and needs
English as a target when the site is not.

This is an allowlist for the settings UI, not a promise: the browser is the authority on
what it can actually translate, and §4.2 handles a pair it declines.

### 3.4 Frontend enqueue

On `wp_enqueue_scripts`, only when the feature is enabled, `! is_admin()`, and at least one
target language is configured. Script is registered with `array()` dependencies, loaded in
the footer, and given the config via `wp_add_inline_script( ..., 'before' )` writing a single
`window.blueworxTranslate` object:

```js
{ source: 'en', sourceLabel: 'English', languages: [ { code: 'fr', label: 'French' } ],
  position: 'bottom-right', label: 'Language', exclude: [ '.brand' ] }
```

The widget root is a single empty `<div id="blueworx-translate-root">` printed on
`wp_footer`; all UI inside it is built by the script, so a browser without the API leaves an
empty, invisible div.

## 4. Frontend behaviour

### 4.1 Capability detection

Before any UI is built:

1. `'Translator' in self` — if absent, stop. Nothing renders.
2. For each configured language, `await Translator.availability( { sourceLanguage, targetLanguage } )`.
   Languages returning `unavailable` are dropped from the menu.
3. If no language survives, stop. Nothing renders.

Detection runs once on load and its result is not cached across page loads — it is cheap, and
caching it would strand a visitor whose browser gained support.

### 4.2 Translating

On selecting a language:

1. Pill enters a busy state (`aria-busy="true"`, spinner, disabled).
2. `Translator.create( { sourceLanguage, targetLanguage, monitor } )`. The `monitor`'s
   `downloadprogress` events drive a percentage on the pill, because first use of a pair
   downloads an on-device model.
3. Collect targets by walking `<body>` with a `TreeWalker`:
   - **Text nodes** whose trimmed value is non-empty and not purely numeric/punctuation.
   - **Attributes** `alt`, `title`, `placeholder`, `aria-label` on any element within scope.
4. Each target's original value is stored in a module-level `Map` keyed by the node (D10).
5. Targets are translated in batches with a small fixed concurrency (4 in-flight
   `translator.translate()` calls), writing each result back as it resolves so the page fills
   in progressively rather than all at once at the end.
6. `document.documentElement.lang` is set to the target code; the chosen code is written to
   `localStorage` under `blueworxTranslateLang`.
7. Pill leaves the busy state and shows the active language.

Selecting the source language restores every entry in the `Map`, clears the stored
preference, and resets `<html lang>` — no network, no reload.

### 4.3 Exclusions

A node is skipped when it or any ancestor matches:

- `script`, `style`, `noscript`, `code`, `pre`, `kbd`, `samp`, `textarea`
- `[translate="no"]`, `.notranslate`, `.blueworx-no-translate`
- `#blueworx-translate-root` (the widget must never translate itself)
- any admin-configured selector from `blueworx_translate_exclusions`

Ancestor matching uses `closest()` against a single joined selector string, guarded by
`try/catch` so one malformed admin selector cannot break the walk.

### 4.4 Dynamic content

A debounced (250 ms) `MutationObserver` on `document.body` (`childList` + `subtree`) picks up
content added after load — Elementor popups, AJAX loads, lazy sections — and runs the same
collect-and-translate pass over new nodes only, using the already-created translator. The
observer is disconnected while the widget writes its own translations back, so it never
re-processes its own output.

### 4.5 Accessibility

- Trigger is a real `<button>` with `aria-expanded` and `aria-haspopup="listbox"`.
- Menu is a `role="listbox"` of `role="option"` items; the active language carries
  `aria-selected="true"`.
- Arrow keys move between options, `Enter`/`Space` select, `Escape` closes and returns focus
  to the trigger, and focus is trapped in the open menu.
- Visible focus ring in both states; contrast meets AA against the pill background.
- Busy state announced via `aria-busy` and a polite live region carrying progress.
- `prefers-reduced-motion` disables the open/close transition and spinner animation.

## 5. Error handling

| Case | Behaviour |
|------|-----------|
| `Translator` API absent | Nothing renders (§4.1). No console error. |
| All pairs `unavailable` | Nothing renders. |
| One pair `unavailable` | That language is omitted from the menu; others still work. |
| `Translator.create()` rejects (e.g. model download fails) | Pill leaves busy state, shows a brief inline "Couldn't load that language" message, stays on the current language. Page content is untouched. |
| A single `translate()` call rejects | That node keeps its original text; the rest of the pass continues. Failures are counted, not surfaced per-node. |
| Invalid admin exclusion selector | Skipped silently at match time; the walk continues. |
| Stored `localStorage` language no longer configured or available | Cleared on load; page stays in the source language. |

## 6. Testing

Playwright, in `tests/translate.spec.js`. The real on-device model cannot run in CI — it needs
a supported Chrome build and a multi-megabyte download — so tests inject a **stubbed
`window.Translator`** via `page.addInitScript()` that resolves `availability()` to
`available` and returns a translator whose `translate()` returns `[xx] ` + the input.

Cases:

1. Feature enabled + stub present → pill renders in the configured corner.
2. Feature disabled → no widget root content anywhere in the DOM.
3. Stub absent (no `window.Translator`) → nothing renders, no console errors.
4. Selecting a language → body text and `alt`/`placeholder` attributes carry the stub prefix;
   `<html lang>` is updated.
5. Excluded selector content → unchanged after translating.
6. Selecting the source language again → original text restored exactly, `<html lang>` reset.
7. Reload after selecting → language re-applied automatically from `localStorage`.
8. Keyboard path → open with `Enter`, move with arrows, select, `Escape` closes and restores
   focus.

The existing `tests/feature-toggles.spec.js` gains the `translate` key so the toggle itself is
covered by the shared assertions.

## 7. Versioning and deployment

- Minor bump to **1.37.0** in `blueworx-labs-wordpress.php` (header + `BLUEWORX_LABS_VERSION`),
  `package.json`, and `readme.txt` `Stable tag`.
- `CHANGELOG.md` entry under 1.37.0.
- `readme.txt` description gains a line for the feature, including the Chrome/Edge limitation.
- Zip built to the parent folder with bsdtar per the deployment rules.

## 8. Explicit non-goals

These are Weglot behaviours this feature does **not** reproduce, and will not be added later
without a separate spec:

- No translated URLs (`/fr/...`), no `hreflang`, **no multilingual SEO**. Crawlers see the
  source language only.
- No server-side or persisted translations — nothing is stored in the database, and each
  visitor's browser does its own work.
- No translation memory, glossary, or manual per-string override editor.
- No support for Safari, Firefox, or browsers below Chrome/Edge 138.
- No translation of text baked into images, PDFs, or `<canvas>`.
- No shortcode or block placement for the switcher.
- No changes to the headless REST layer.
