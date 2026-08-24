# Moving the admin re-skin onto the design system

Date: 2026-08-24

## The problem

The plugin carries three hand-written stylesheets that predate the shared
design system:

| File | Lines | What it does |
|---|---|---|
| `assets/css/admin-theme.css` | 1737 | Re-skins WordPress's own wp-admin — menu, list tables, postboxes, forms |
| `assets/css/login-theme.css` | 570 | Re-skins the wp-login screens as a split-screen layout |
| `assets/css/admin-additions.css` | 157 | The components the designs needed that the system does not carry |

All three declare their own `:root` token block using the `--bw-` prefix, which
is the design system's prefix. `admin-theme.css` and `login-theme.css` both
enqueue with `blueworx-admin-design` as a dependency, so they load **after** the
system and their `:root` wins.

Six tokens collide in wp-admin and four on the login screen:

| Token | The system's value | What the re-skin forces instead |
|---|---|---|
| `--bw-font-body` | `"Inter", …` | `"Sora", …` |
| `--bw-border` | `var(--bw-line)` → `#ECEDF3` | `#EFEFF0` |
| `--bw-success` | `#00A32A` | `#01824C` |
| `--bw-warning` | `#DBA617` | `#FFC107` |
| `--bw-info` | `#2271B1` | `#3686F7` |
| `--bw-shadow-card` | `0 1px 2px rgb(10 12 41 / .05)` | a heavier two-layer shadow |

Every panel we have already moved onto the design system is therefore rendering
with the re-skin's values, not the system's — body text in Sora rather than
Inter, and the wrong green, amber, blue, border and card shadow. This is a live
defect on shipped screens, not just untidy CSS.

Separately, the two re-skins write roughly 630 hand-authored colours, sizes and
shadows between them. The foundation's adherence check reports these, which is
why that check runs in `warn` mode here.

## Decisions taken

1. **The chrome re-skin stays in this plugin**, but is expressed entirely in
   design system tokens. It is not promoted into the foundation: only this
   plugin re-skins WordPress core today, and a shared wp-admin chrome layer is a
   large upstream change that nothing else is asking for.
2. **Where the values differ, the system wins.** wp-admin shifts slightly to
   match our own screens and every other BlueWorx plugin.

The visible consequences of decision 2, all deliberate:

- Body text becomes Inter (from Sora).
- Headings become Sora (from Helvetica Neue) — `--bw-font-head` currently
  resolves to Helvetica Neue, and the system's display face is Sora.
- Card corners go from 16px to 12px, buttons 11px to 8px.
- The success green, warning amber and info blue move to the system's values.
- Card shadows become the system's single, lighter layer.

## The design

### Token handling

Both re-skins lose their `:root` block. Names map onto the system's:

| Re-skin token | Becomes |
|---|---|
| `--bw-primary` | `--bw-brand` (identical value, `#4F46E5`) |
| `--bw-primary-dark` | `--bw-brand-deep` (identical, `#4338CA`) |
| `--bw-primary-light` | `--bw-brand-line` |
| `--bw-charcoal` | `--bw-ink` (identical, `#0A0C29`) |
| `--bw-lavender` | `--bw-brand-wash` |
| `--bw-surface` | `--bw-canvas` |
| `--bw-body` | `--bw-text-body` |
| `--bw-muted` | `--bw-text-muted` |
| `--bw-border` | `--bw-line` |
| `--bw-success` / `--bw-warning` / `--bw-info` | the same names, no longer redeclared |
| `--bw-error` | `--bw-danger` |
| `--bw-radius-card` | `--bw-radius-lg` |
| `--bw-radius-btn` / `--bw-radius-input` | `--bw-radius-md` |
| `--bw-shadow-card` | the same name, no longer redeclared |
| `--bw-shadow-card-hover` | `--bw-shadow-raised` |
| `--bw-font-head` | `--bw-font-display` |
| `--bw-font-ui` / `--bw-font-body` | `--bw-font-body` |

Four values have no equivalent in the system because they describe this
plugin's chrome, not a design decision the system holds an opinion on:
topbar height, sidebar width, content measure, and the login panel width.
These stay local and move to a **`--bwt-` prefix** so they can never collide
with the system again:

- `--bwt-topbar-h: 60px`
- `--bwt-sidebar-w: 232px`
- `--bwt-measure: 1200px`
- `--bwt-panel-width: 44%` (login only)

`--bw-topbar-h` is referenced only inside `admin-theme.css`. It is *also*
duplicated as a literal `60px` in the inline critical-CSS block in
`includes/admin-theme.php` — deliberately, because that block prints before the
stylesheet that defines the custom properties. That literal stays a literal; the
comment beside it that names the token is updated to the new name.

### Everything else

Every remaining hand-written colour, size and shadow becomes the nearest system
token. Sizes map to the `--bw-space-*` scale, radii to `--bw-radius-*`, control
heights to `--bw-control-h`. A value inside an `@media` breakpoint stays in px —
a breakpoint is a fact about the viewport, not a design decision.

`admin-additions.css` gets the same treatment. Its components stay in this
plugin, per the decision already recorded not to promote them upstream until a
second project wants one.

### `includes/admin-theme.php`

Two things in the PHP need naming, because the adherence check reports both and
they have opposite answers.

- **The inline critical-CSS block stays exactly as it is.** Its three `60px`
  literals are reported as hand-written sizes, and that is a false positive
  here: the block prints ahead of the stylesheet precisely so the geometry is
  right on first paint, so it cannot reference a custom property the stylesheet
  has not defined yet. These findings are accepted permanently, not fixed.
- **The three hand-drawn `<svg>` icons stay as they are.** The intent was to
  move them to the system's `data-lucide` pattern, and that turns out to be
  wrong here. The system's icon module is enqueued only on BlueWorx screens,
  while the topbar these icons live in renders on *every* admin page — and the
  markup degrades to an empty span without the module. Converting them would
  delete the menu, view-site and chevron icons from most of wp-admin.

  Loading the module everywhere would fix that, but it makes chrome icons wait
  on JavaScript, and this same file goes to unusual lengths (the inline
  critical-CSS block) to get the chrome right on first paint. Trading that for
  tidiness is the wrong way round. The `hand-svg` findings are accepted for
  these three, on the same reasoning as `stray-admin-css`: this is WordPress
  chrome, not one of our screens.

### What this does not change

- No markup changes beyond the three icons above. Otherwise this is a
  stylesheet-level change throughout.
- No change to which screens the re-skin applies to, or to the feature toggle
  that switches it off.
- The `stray-admin-css` finding does not go away: the foundation's rule permits
  a plugin to style only `.wrap`, `#wpcontent`, `#wpbody-content`, `#wpfooter`
  and `#wpadminbar`, and re-skinning `#adminmenu` falls outside that by design.
  Adherence therefore stays on `warn`. What this work removes is every
  `raw-color`, `raw-size`, `raw-shadow` and `raw-font` finding.

## Delivery

Three pull requests, in order. Each is independently reviewable and revertable.

**PR 1 — the token collision.** Delete both `:root` blocks, apply the mapping
above, introduce the `--bwt-` prefix. Small, and it fixes the live defect on
screens already shipped. It also proves the approach visually before anything
larger is touched.

**PR 2 — `admin-theme.css`.** The remaining ~1,700 lines onto system tokens.

**PR 3 — `login-theme.css`.** The same for the login screens.

`admin-additions.css` rides with PR 1, since it is small and already written
against system naming.

## Testing

The suite has 26 spec files and already asserts computed styles in
`admin-theme.spec.js` and `design-system-migration.spec.js`; this work follows
that convention rather than inventing one.

The test that matters is the one that would have caught this defect: on a
re-skinned admin screen, assert that a design system element computes the
**system's** font and status colours — not the re-skin's. A `:root` collision is
invisible to any test that only checks markup, which is why it survived.

Per PR:

- **PR 1** — a spec asserting `--bw-font-body`, `--bw-success`, `--bw-warning`,
  `--bw-info`, `--bw-border` and `--bw-shadow-card` resolve to the system's
  values on a re-skinned screen and on the login screen. This test fails on
  today's code and passes after the change.
- **PR 2 and PR 3** — the existing visual and layout specs stay green. Where a
  spec asserts a value that deliberately changes (a radius, a colour), it is
  updated to the system's value in the same PR, never loosened.

All 26 spec files stay green throughout.

## Risks

- **The re-skin covers a lot of WordPress.** A token change lands on screens no
  spec visits. Mitigated by keeping the three PRs separate and by the change
  being mechanical — token substitution, not restructuring.
- **Renaming the four local tokens.** A missed reference breaks the topbar or
  sidebar geometry silently. All of them live in the two stylesheets, so the
  rename is contained, and the existing topbar and mobile-layout specs cover the
  result.
- **A value with no exact system equivalent** (the muted grey, the error red)
  shifts slightly. This is the intended consequence of "the system wins", and is
  called out above so it is not mistaken for a regression at review.
