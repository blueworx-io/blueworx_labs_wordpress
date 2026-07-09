# IMPLEMENTATION_PLAN.md — BlueWorx Labs Headless REST Layer

**Plugin:** BlueWorx Labs | WordPress Enhancements (`blueworx-project-wordpress-labs`)
**Baseline:** v1.5.0 (Phase 0 complete — foundation onboarding + rename/merge)
**Status:** Authoritative build spec for the headless phases (1–6, LatePoint deferred to 7)
**Authored:** 2026-07-09 (Luke McFarland + Claude Code, brainstormed; decisions doc-verified against SureCart, LatePoint, WordPress REST, and RFC 9700 / OWASP sources)

> This document is the contract. Each module's definition of done is checked
> against it — endpoints must match the map in **§13**, phases must follow the
> order in **§14**. Do not deviate from this plan without Luke's approval.

---

## 1. Purpose & scope

This plugin powers BlueWorx **headless** WordPress sites: a decoupled frontend
(Next.js on Netlify) consumes WordPress purely over HTTP. This layer supplies
authenticated, capability-gated JSON and proxies third-party commerce/booking
APIs whose secrets the browser must never hold.

**Division of responsibility:**

- **WordPress core REST (`/wp/v2/…`)** owns all post/page/CPT content CRUD.
  We never re-implement what core already exposes. Authenticated writes ride
  core, authorised by the JWT `determine_current_user` filter in §3.
- **`/wp-json/blueworx/v1/…`** (this plugin) owns everything core does not:
  auth/session, account lifecycle, curated public content helpers, CORS,
  outbound revalidation, and the SureCart proxy.

**Non-negotiable constraints (from the brief):**

- Generic and reusable — **all** site-specific behaviour flows through the
  settings page or WP filters. Never hardcode per-site values.
- Capability-driven — authenticate the request, set the current user, let WP
  capabilities gatekeep. Enforce ownership on every per-user proxy call.
- Secrets (JWT, SureCart, revalidation) come from `wp-config.php` constants —
  never the database, never the browser.

**Out of scope:** rebuilding the removed Elementor SureCart pricing table; any
frontend code; triggering Netlify **builds** (we do on-demand revalidation
only — see §9).

---

## 2. Architecture & request lifecycle

All headless routes register under namespace `blueworx/v1` via
`register_rest_route()` on the `rest_api_init` hook. Every route declares an
explicit `permission_callback` (never `__return_true` by omission).

**Request lifecycle for an authenticated call:**

1. Client sends `Authorization: Bearer <access_token>` (a JWT).
2. Our `determine_current_user` filter (priority 20) runs during REST auth:
   parse the bearer, validate signature/`exp`/`iss`/`aud`, confirm the token's
   `tv` claim equals the user's current `token_version` (§4). On success it
   returns the user ID, so WordPress sets the current user; on any failure it
   returns the value unchanged (no fatal, no side effects) and the request
   continues as anonymous.
3. The route's `permission_callback` runs `current_user_can()` (authorization).
4. The route callback executes; per-user data access is scoped server-side to
   the resolved user — client-supplied user/customer identifiers are ignored.

**Separation of concerns:** `determine_current_user` handles **authentication
only** (who is this). `permission_callback` + `current_user_can()` handle
**authorization only** (may they). This matches the WordPress REST handbook's
guidance and keeps us compatible with other auth plugins.

**Code layout (new `includes/rest/` tree):**

```
includes/rest/
  bootstrap.php          # registers rest_api_init, determine_current_user, CORS
  class-jwt.php          # encode/decode access tokens (firebase/php-jwt wrapper)
  class-tokens.php       # refresh-token families: issue/rotate/revoke/reuse-detect
  class-rate-limit.php   # transient-based throttling + lockout
  controller-auth.php    # /auth/*
  controller-account.php # /account/*
  controller-content.php # /menus, /site, /resolve, /acf-options
  controller-surecart.php# /surecart/*
  cors.php               # origin allowlist + preflight
  revalidate.php         # outbound on-demand revalidation (default OFF)
  install.php            # dbDelta for the refresh-token table (activation)
```

Follows the existing procedural + `blueworx_`-prefixed style; classes are thin
namespaced helpers, wired by procedural bootstrap to match the current codebase.

---

## 3. Auth core (Phase 1)

**JWT access tokens** — library: **`firebase/php-jwt`** (Composer, approved §16),
algorithm **HS256**, secret `BLUEWORX_LABS_JWT_SECRET` (wp-config).

Access-token claims:

| Claim | Meaning |
|-------|---------|
| `iss` | site home URL |
| `aud` | `blueworx-headless` |
| `sub` | WP user ID |
| `iat` / `exp` | issued / expiry (`exp` = `iat` + access TTL, default 60 min) |
| `tv`  | snapshot of the user's `token_version` at issue time |
| `jti` | unique token id |

**`determine_current_user` filter** (§2 step 2). Decoding failures — bad
signature, expired, wrong `aud`/`iss`, or `tv` mismatch — all resolve to
"anonymous", never an error response, so unauthenticated public routes and core
REST keep working.

**Refresh tokens** — opaque high-entropy random strings (never JWTs), delivered
**only** as an `HttpOnly; Secure; SameSite=None` cookie scoped to the refresh
path, 14-day default TTL. Rotation + reuse-detection per §4.

**Login flow (`POST /auth/login`):** validate credentials with
`wp_authenticate()`; on success issue an access token (response body) and set a
refresh cookie (new family). Rate-limited + lockout per §5. Generic error on
failure (no "user exists" leak).

**Refresh flow (`POST /auth/refresh`):** read the refresh cookie, validate +
rotate the family (§4), return a new access token and set the rotated cookie.

**Logout (`POST /auth/logout`):** revoke the presented refresh family, clear the
cookie. **Logout-all (`POST /auth/logout-all`):** bump the user's
`token_version` (invalidates every outstanding access token immediately) and
revoke all that user's refresh families.

---

## 4. Token & session security

**Access tokens:** stateless; validated by signature + `exp` + `tv` check. The
`tv` claim is the cheap global revocation lever — one integer compare per
request, no DB hit for the common case beyond loading the user's `token_version`
(cached user meta).

**`token_version`** — integer in **user meta** (`blueworx_token_version`,
default 0). Bumped on logout-all, password change/reset, and account-security
events. Any access token whose `tv` ≠ current value is rejected.

**Refresh-token families** — stored in a custom table (§ install):

`{$wpdb->prefix}blueworx_refresh_tokens`

| Column | Notes |
|--------|-------|
| `id` | PK |
| `user_id` | indexed |
| `family_id` | indexed — a login starts a family; rotation preserves it |
| `token_hash` | SHA-256 of the opaque token (never store plaintext), unique index |
| `issued_at` / `expires_at` | indexed on `expires_at` for GC |
| `revoked` | tinyint |
| `replaced_by` | `token_hash` of the successor (rotation chain) |
| `ip` / `user_agent` | audit metadata |

**Rotation + reuse-detection (RFC 9700 / OWASP):** on refresh, look up by
`token_hash`. If found, unrevoked, unexpired → mark it revoked, insert a
successor in the same family, set `replaced_by`. If a token that is **already
revoked** is presented (replay of a consumed token) → **revoke the entire
family** and force re-login. Expired/unknown tokens → 401, cookie cleared.

**Garbage collection:** a daily `wp_cron` event deletes rows past `expires_at`.

---

## 5. Rate limiting, lockout & abuse

Mechanism: **WordPress transients keyed by IP + route** (native, no
dependency, matches the existing plugin idiom; fail-open on cache flush is
acceptable at these thresholds). All thresholds are settings (§12) with the
defaults below.

| Surface | Default policy |
|---------|----------------|
| `POST /auth/login` | 5 failures / 15 min per IP → 15-min lockout. Optional per-account counter (same thresholds). |
| `POST /auth/refresh` | 30 / 5 min per IP (abuse ceiling; normal use is far below). |
| `POST /account/register` | 10 / hour per IP. |
| `POST /account/password/forgot` | 5 / hour per IP. |
| `POST /account/verify`, `/password/reset` | 10 / hour per IP. |

Client IP resolved via a filterable helper (`blueworx_headless_client_ip`) so a
site behind Cloudflare/Cloudways can trust the correct forwarded header. Lockout
responses use HTTP 429 with `Retry-After`.

---

## 6. Accounts (Phase 2)

**Registration modes** — setting `blueworx_headless_registration_mode`
∈ `{open, invite, closed}`, **default `closed`**:

- **open** — anyone may self-register. Email verification required by default
  (setting); account exists but login is blocked until verified.
- **invite** — registration requires a valid, unused, unexpired invite token
  (single-use, stored hashed in `{$wpdb->prefix}blueworx_invites`; may pin an
  email and/or role). Admin issues invites from the settings screen.
- **closed** — no public registration. Accounts are created only in wp-admin.
  `POST /account/register` returns 403.

**Non-enumerating responses (mandatory):**

- `register` with an already-registered email → same generic 200 ("check your
  email") as a fresh signup; no "email taken" leak.
- `password/forgot` → always 200 regardless of whether the email exists.

**Endpoints:** register, verify (email), resend-verification, password/forgot,
password/reset, password/change (auth, requires current password), `PATCH
/account` (auth, whitelisted profile fields), `DELETE /account` (auth **+
re-auth**: current password required in body; bumps `token_version`, revokes
families, then deletes/anonymises per policy). Full contract in §13.

**Verification/reset tokens:** opaque, stored **hashed** in user meta with an
expiry; single-use; consumed on success. Password change/reset bumps
`token_version` (kills existing sessions).

---

## 7. Authorization & ownership

- Every route has an explicit `permission_callback`. Public routes return
  `true`; authenticated routes assert `is_user_logged_in()` and, where relevant,
  a specific `current_user_can( $cap )`.
- **Ownership is enforced server-side on every `/me/*` call.** The target
  resource is derived from the resolved current user (e.g. their mapped SureCart
  customer id), never from a client-supplied id. Requests for another user's
  resource are impossible to express, not merely rejected.
- Content writes are **not** re-implemented — they ride core `/wp/v2` under the
  same JWT identity, where core's own capability checks apply.

---

## 8. Public content (Phase 3)

| Endpoint | Returns |
|----------|---------|
| `GET /menus/{location}` | The nav menu assigned to a theme location, as an ordered tree (`id`, `title`, `url`, `target`, `parent`, `children`). 404 if the location has no menu. |
| `GET /site` | Whitelisted public site settings: `name`, `description`, `url`, `home`, `language`, `timezone`, `date_format`, `posts_per_page`, `site_logo`. Whitelist is filterable (`blueworx_headless_site_fields`). |
| `GET /resolve?uri=` | Maps a frontend path to a WP object for routing: `{ type: post|page|term|archive|404, id, slug, rest_url, template }`. Uses `url_to_postid()` + term/archive detection. |
| `GET /acf-options` | ACF options-page fields (guarded by `function_exists('get_fields')`). Per-post ACF is attached to core REST responses via `register_rest_field`, not here. |

**CPT registrar** — not an endpoint: a settings/filter-driven list
(`blueworx_headless_cpts`) that guarantees `show_in_rest => true` (and a REST
base) for chosen post types so they ride core `/wp/v2`. Generic — no CPT is
hardcoded.

---

## 9. CORS + optional revalidation webhook (Phase 4)

**CORS** — for `blueworx/v1` responses and `OPTIONS` preflight. Origin allowlist
comes from the setting `blueworx_headless_allowed_origins` (constant
`BLUEWORX_LABS_ALLOWED_ORIGINS` overrides). Because requests are credentialed
(refresh cookie), we **echo the exact matching origin** and send
`Access-Control-Allow-Credentials: true` — **never** `*`. Unlisted origins get
no CORS headers. Allowed methods/headers are limited to what the API uses.

**Revalidation webhook** — **outbound, default OFF**
(`blueworx_headless_revalidate_enabled`). When enabled, on `save_post` /
`transition_post_status` / `deleted_post`, POST the affected path(s) plus a
shared secret (`BLUEWORX_LABS_REVALIDATE_SECRET`) to a configured on-demand
revalidation URL (`blueworx_headless_revalidate_url`). This targets the
frontend's ISR/on-demand revalidation endpoint **only**; it never triggers a
Netlify build. Sent non-blocking (`wp_remote_post` with a short timeout);
failures are logged, never fatal.

---

## 10. SureCart proxy (Phase 5)

Server-side proxy to `https://api.surecart.com/v1/` via `wp_remote_*`, injecting
`Authorization: Bearer <BLUEWORX_LABS_SURECART_API_KEY>` (wp-config). The browser
never sees the key. Enabled by `blueworx_headless_surecart_enabled`. Three tiers:

| Tier | Endpoint | Ownership |
|------|----------|-----------|
| public | `GET /surecart/products`, `GET /surecart/products/{id}`, `GET /surecart/prices` | none — catalogue data |
| user | `GET /surecart/me/orders`, `GET /surecart/me/subscriptions`, `GET /surecart/me/invoices` | scoped to the caller's SureCart customer |
| write | `POST /surecart/checkout`, `POST /surecart/me/subscriptions/{id}/cancel` | verified to belong to the caller before proxying |

**Customer mapping:** resolve the WP user → SureCart customer id, cached in user
meta (`blueworx_surecart_customer_id`, looked up by email on first use). Every
`/me/*` request is filtered by that id **server-side**; the client cannot pass a
customer id. Before any write on a specific resource (e.g. cancel subscription
`{id}`), confirm the resource belongs to the mapped customer or return 403.

Responses are passed through with only the needed fields; upstream errors are
normalised to our error envelope (§13). Exposed surface is intentionally a
curated subset of SureCart's full API — extend via `blueworx_headless_surecart_routes`.

---

## 11. LatePoint proxy (Phase 7 — deferred)

**Finding:** LatePoint core has **no official REST API** (still an open feature
request). A REST surface exists only via the third-party paid **wplimit
"LatePoint API Extension"** (`/wp-json/latepoint-api/v1/`, `lp_` bearer key).

**Decision:** defer LatePoint to its own later phase, validated against a live
LatePoint install before committing. When it lands, mirror §10's three-tier,
ownership-enforced shape (public: services/agents/availability; user:
`/me/bookings`; write: create/cancel/reschedule).

**Driver decision to resolve then (recommended: adapter, internal-models
default):** a LatePoint adapter with selectable drivers — (1) call LatePoint's
own PHP model classes in-process (guarded by `class_exists` + supported-version
check; no paid add-on, no key); (2) optionally use the wplimit REST API if that
plugin is present. Keeps us generic and free by default with a cleaner path
where available. Not built until Phase 7.

---

## 12. Settings & configuration

**`wp-config.php` constants — secrets only (never DB, never browser):**

| Constant | Purpose | Required for |
|----------|---------|--------------|
| `BLUEWORX_LABS_JWT_SECRET` | HS256 signing secret | Auth (Phase 1) |
| `BLUEWORX_LABS_SURECART_API_KEY` | SureCart secret bearer | SureCart proxy (Phase 5) |
| `BLUEWORX_LABS_REVALIDATE_SECRET` | shared secret for the webhook | Revalidation (Phase 4) |
| `BLUEWORX_LABS_ALLOWED_ORIGINS` | optional CORS override (comma-separated) | CORS (Phase 4) |

**Settings page (non-secret; new "Headless" tab; option prefix
`blueworx_headless_`):** allowed origins list, registration mode + "email
verification required" toggle, invite management, access-token TTL (default 60
min), refresh-token TTL (default 14 days), rate-limit/lockout thresholds,
revalidation enabled + URL, SureCart proxy enabled, CPT registrar list. Missing
a **required** secret constant disables the dependent feature and surfaces an
admin notice — the feature never half-works.

**Filters (extension points, non-exhaustive):** `blueworx_headless_client_ip`,
`blueworx_headless_site_fields`, `blueworx_headless_cpts`,
`blueworx_headless_surecart_routes`, `blueworx_headless_access_ttl`,
`blueworx_headless_refresh_ttl`.

---

## 13. Endpoint map — the contract

Namespace `/wp-json/blueworx/v1/`. **Auth** column: `none` (public),
`access` (valid access-token bearer), `refresh` (valid refresh cookie),
`access+reauth` (access token **and** current password in body).
All error responses use one envelope: `{ code, message, data: { status } }`.
No response ever leaks account existence where §6 requires non-enumeration.

### Auth (Phase 1)

| Method · Path | Auth | Request | Response | Cap |
|---|---|---|---|---|
| POST `/auth/login` | none | `{ login, password }` | `{ access_token, expires_in, user }` + Set-Cookie refresh | — |
| POST `/auth/refresh` | refresh | — (cookie) | `{ access_token, expires_in }` + rotated Set-Cookie | — |
| POST `/auth/logout` | refresh | — (cookie) | `{ ok: true }` + cleared cookie | — |
| POST `/auth/logout-all` | access | — | `{ ok: true }` (bumps `token_version`) | — |
| GET  `/auth/me` | access | — | `{ id, email, display_name, roles, capabilities }` | — |

### Accounts (Phase 2)

| Method · Path | Auth | Request | Response | Notes |
|---|---|---|---|---|
| POST `/account/register` | none | `{ email, password, invite_token? }` | generic 200 | mode-gated; non-enumerating; 403 in `closed` |
| POST `/account/verify` | none | `{ token }` | `{ ok: true }` | single-use, expiring |
| POST `/account/resend-verification` | none | `{ email }` | generic 200 | non-enumerating |
| POST `/account/password/forgot` | none | `{ email }` | generic 200 | always 200; non-enumerating |
| POST `/account/password/reset` | none | `{ token, password }` | `{ ok: true }` | bumps `token_version` |
| POST `/account/password/change` | access | `{ current_password, new_password }` | `{ ok: true }` | bumps `token_version` |
| PATCH `/account` | access | `{ display_name?, first_name?, last_name?, … }` | updated user | whitelisted fields only |
| DELETE `/account` | access+reauth | `{ current_password }` | `{ ok: true }` | revokes sessions, then deletes/anonymises |

### Public content (Phase 3)

| Method · Path | Auth | Request | Response |
|---|---|---|---|
| GET `/menus/{location}` | none | — | ordered menu tree; 404 if unassigned |
| GET `/site` | none | — | whitelisted site settings object |
| GET `/resolve` | none | `?uri=` | `{ type, id, slug, rest_url, template }` |
| GET `/acf-options` | none | — | ACF options fields (empty if ACF absent) |

### SureCart proxy (Phase 5)

| Method · Path | Auth | Ownership |
|---|---|---|
| GET `/surecart/products` | none | — |
| GET `/surecart/products/{id}` | none | — |
| GET `/surecart/prices` | none | — |
| GET `/surecart/me/orders` | access | mapped customer |
| GET `/surecart/me/subscriptions` | access | mapped customer |
| GET `/surecart/me/invoices` | access | mapped customer |
| POST `/surecart/checkout` | access | mapped customer |
| POST `/surecart/me/subscriptions/{id}/cancel` | access | resource ownership verified |

### LatePoint proxy (Phase 7 — deferred)

Shape mirrors SureCart (public services/agents/availability · user `/me/bookings`
· write create/cancel/reschedule). Not part of the initial contract; specified in
§11 and finalised when Phase 7 begins.

---

## 14. Build phase order & per-phase definition of done

Each phase is its **own branch → PR**, gated by CI (WPCS lint, `php -l`, version
bump, changelog, Playwright). Per-PR: bump the plugin header +
`BLUEWORX_LABS_VERSION` + `package.json`, add a `CHANGELOG.md` entry.

| Phase | Deliverable | Depends on | Definition of done |
|-------|-------------|------------|--------------------|
| **1 · Auth core** | JWT class, refresh-token table + families, `determine_current_user`, `/auth/*`, rate-limit helper | — | Login→me→refresh→logout→logout-all all pass e2e; reuse-detection revokes a family; `tv` bump invalidates access tokens; PHPUnit on token logic green. |
| **2 · Accounts** | `/account/*`, invite table, verification/reset tokens, registration modes | 1 | All three modes behave per §6; non-enumeration verified; re-auth required for delete; password change/reset kills sessions. |
| **3 · Public content** | `/menus`, `/site`, `/resolve`, `/acf-options`, ACF bridge, CPT registrar | 1 | Endpoints return documented shapes; CPT registrar drives core `show_in_rest`; graceful when ACF absent. |
| **4 · CORS + webhook** | CORS allowlist + preflight, outbound revalidation (default OFF) | 1–3 | Credentialed CORS echoes exact allowed origin, never `*`; preflight passes; webhook fires only when enabled and never triggers a Netlify build. |
| **5 · SureCart proxy** | `/surecart/*` three tiers, customer mapping | 1 | Public catalogue works keyless-to-client; `/me/*` scoped server-side; write ownership enforced; SureCart mocked in CI. |
| **6 · Hardening** | Security pass: fix `wp_get_referer()` trust in the backend gate (see phase-status), audit all `permission_callback`s, headers, GC cron, abuse thresholds | 1–5 | Security-review skill clean; no route without an explicit permission check; documented threat mitigations. |
| **7 · LatePoint proxy** *(deferred)* | LatePoint adapter + `/latepoint/*` | 1, live LatePoint install | Driver chosen (§11); three-tier ownership parity with SureCart. |

---

## 15. Testing strategy

- **PHPUnit (logic/units):** JWT encode/decode + claim validation; refresh
  rotation + reuse-detection; `token_version` invalidation; rate-limit counters
  + lockout; registration-mode gating; ownership scoping; `resolve` mapping.
- **Playwright (e2e):** login → me → refresh → logout → logout-all; CORS
  preflight from an allowed vs. disallowed origin; register/verify happy path;
  non-enumeration (identical responses for known/unknown email).
- **External APIs mocked in CI:** SureCart HTTP mocked via the `pre_http_request`
  filter — no live calls in CI. LatePoint deferred (Phase 7).
- Existing placeholder-URL skip behaviour in `playwright.config.js` is retained
  until a real staging URL exists.

---

## 16. Dependencies, versioning & deployment

- **New dependency:** `firebase/php-jwt` (Composer, PHP) — **approved**. PHP
  Composer deps are not gated by `approved-deps.json` (npm-only), but this is
  recorded here as the sanctioned addition. It ships in the built plugin's
  `vendor/` (production autoloader).
- **Versioning:** patch for fixes, minor for each phase's features. Bump the
  plugin header, `BLUEWORX_LABS_VERSION`, and `package.json` together every PR;
  `npm run version:check` and `composer lint` (WPCS) must pass.
- **Deployment artifact:** `npm run build` → `dist/blueworx-project-wordpress-labs.zip`
  (single zip; older zips removed first). Never copy individual files.
- **Linting:** run once as a final check; present findings; fix only after
  approval (no lint→autofix→relint loop).
