# Headless Integration Guide

**Consuming this WordPress plugin from a BlueWorx headless (Next.js) frontend.**

This document is the contract between the **BlueWorx Labs | WordPress Enhancements**
plugin (this repo) and a decoupled Next.js frontend that renders the site. It is
written to be read by a Claude Code session working *in the headless repo* — point
that repo at this file and it has everything needed to wire the frontend to the CMS.

> **Source of truth.** Everything below is derived directly from the plugin code in
> [`includes/rest/`](includes/rest/), not from intent docs. Where the shipped code
> differs from the design plan, this guide follows the **code**. If you change the
> REST layer, update this file in the same PR — the headless repo depends on it.

---

## 0. How a headless agent should use this document

1. Read §1–§3 to learn the base URL, what must be configured on the WordPress side,
   and the environment variables the frontend needs.
2. Read §4 (auth model) carefully — the access-token + rotating-refresh-cookie dance
   has non-obvious cross-site cookie rules that will silently break login if ignored.
3. Use §5 (endpoint contract) and §6 (content model) as the API reference while building
   pages and data fetchers.
4. Drop in the reference API client in §10 and adapt it — don't reinvent the refresh loop.
5. Implement the revalidation route (§8) only if on-demand revalidation is enabled.
6. Follow §12 (house conventions) so the build passes CI and matches every other
   BlueWorx headless project.
7. Before opening the PR, walk the §13 checklist.

---

## 1. What this plugin exposes

A custom REST namespace layered on top of core WordPress REST:

| Layer | Base | Purpose |
|-------|------|---------|
| **BlueWorx headless API** | `/wp-json/blueworx/v1/` | Auth, accounts, menus, site config, path resolution, ACF options, SureCart proxy, revalidation |
| **Core WordPress REST** | `/wp-json/wp/v2/` | The actual post/page/CPT content (title, content, ACF fields, media, etc.) |

The pattern is: **use `blueworx/v1` for identity, routing, and site-level data;
use `wp/v2` for the content bodies themselves.** The `/resolve` endpoint (§6.1)
bridges the two — it turns a frontend path into a `wp/v2` URL to fetch.

The namespace constant is `blueworx/v1` (see
[`helpers-rest.php`](includes/rest/helpers-rest.php) → `BLUEWORX_HEADLESS_NAMESPACE`).

---

## 2. WordPress-side prerequisites (verify before building)

The frontend cannot work until the CMS is configured. These live in **BlueWorx →
Headless** in wp-admin, except secrets, which are `wp-config.php` constants only.

### Secrets — `wp-config.php` constants (never in the DB, never in the browser)

| Constant | Required for | Notes |
|----------|--------------|-------|
| `BLUEWORX_LABS_JWT_SECRET` | **All auth** | HS256 signing secret. Without it, every `/auth/*` and account route returns `503 blueworx_auth_unconfigured`. |
| `BLUEWORX_LABS_REVALIDATE_SECRET` | On-demand revalidation | Shared secret sent to the frontend as `X-Blueworx-Revalidate`. |
| `BLUEWORX_LABS_SURECART_API_KEY` | SureCart proxy | SureCart secret bearer; never leaves the server. |
| `BLUEWORX_LABS_ALLOWED_ORIGINS` | CORS (optional override) | Comma/newline-separated. Overrides the admin setting when defined. |

### Admin settings (BlueWorx → Headless)

| Setting | Default | The frontend depends on it for |
|---------|---------|-------------------------------|
| **Allowed origins** | *(empty)* | CORS. **Must contain the exact frontend origin** (scheme + host, no trailing slash) or the browser blocks every credentialed call. Applies to `wp/v2` as well as `blueworx/v1` — an empty list denies both. |
| **Frontend URL** | *(empty)* | Base for links in verification / password-reset emails (`/verify?token=`, `/reset-password?token=`). Set it to the deployed frontend origin. |
| **Registration mode** | `closed` | `/account/register` returns `403` while `closed`. Set to `open` or `invite` to allow sign-up. |
| **Email verification required** | `on` | When on, new accounts must confirm email before `/auth/login` succeeds (`403 blueworx_email_unverified`). |
| **Default role** | `subscriber` | Role assigned to self-registered users. |
| **Access token TTL** | `3600` s | The frontend should refresh at/near this. |
| **Refresh token TTL** | `14` days | Session length before a full re-login. |
| **Login attempts / window** | `5` / `900` s | Drives `429` lockout on `/auth/login`. |
| **Revalidation enabled + URL** | off | On-demand ISR (§8). |
| **SureCart proxy enabled** | off | Store endpoints (§9). |
| **CPTs in REST** | *(empty)* | Comma-separated CPT keys to force into `wp/v2`. Any CPT you fetch must be listed here (or already `show_in_rest`). |

> **Non-obvious defaults:** out of the box, **registration is `closed` and email
> verification is required**. A "sign up" page will 403 until an admin opens
> registration. Don't assume open registration.

---

## 3. Frontend environment variables

Store these in Netlify (never commit them). Suggested names — keep them consistent
across BlueWorx headless projects:

```bash
# The WordPress origin (scheme + host, no trailing slash). Public because the
# browser calls /auth/* and wp/v2 directly.
NEXT_PUBLIC_WORDPRESS_URL=https://cms.example.com

# Shared secret matching BLUEWORX_LABS_REVALIDATE_SECRET on the CMS.
# Server-only — used to verify the incoming revalidation webhook. Never NEXT_PUBLIC_.
REVALIDATE_SECRET=<same value as BLUEWORX_LABS_REVALIDATE_SECRET>
```

Derive the two API bases from the origin:

```ts
export const WP_ORIGIN = process.env.NEXT_PUBLIC_WORDPRESS_URL!;
export const BLUEWORX_API = `${WP_ORIGIN}/wp-json/blueworx/v1`;
export const WP_API = `${WP_ORIGIN}/wp-json/wp/v2`;
```

---

## 4. Authentication model (read this before writing any auth code)

Short-lived **JWT access token** + long-lived **rotating refresh cookie**.

### The pieces

- **Access token** — a signed JWT (HS256), returned in the JSON body of `/auth/login`
  and `/auth/refresh`. Send it as `Authorization: Bearer <token>` on every
  authenticated request. TTL = the configured access TTL (default 3600 s). Claims
  include a per-user `tv` (token version); bumping the user's token version instantly
  invalidates all outstanding access tokens.
- **Refresh token** — opaque, delivered **only** as an `HttpOnly`, `Secure`,
  `SameSite=None` cookie named `blueworx_headless_refresh`. **JavaScript never sees
  it.** Stored server-side as a SHA-256 hash. Each login starts a *family*; each
  refresh **rotates** the token within the family. Replaying an already-used refresh
  token is treated as theft and revokes the whole family (forces re-login).

### The rules the frontend MUST follow

1. **Keep the access token in memory only** — a module variable or React state. Do
   **not** put it in `localStorage` or a readable cookie. On a hard reload it's gone;
   recover the session by calling `/auth/refresh` (the cookie survives).
2. **Every request that needs the cookie must use `credentials: 'include'`** — this
   includes `/auth/refresh`, `/auth/logout`, and `/auth/login`. Without it the browser
   won't send or store the refresh cookie and refresh silently fails.
3. **On `401` from an authenticated call**, try `/auth/refresh` once, then replay the
   original request with the new access token. If refresh also fails, treat the user
   as logged out.
4. **The refresh cookie path is scoped to `/wp-json/blueworx/v1/auth/`.** The browser
   only sends it to `/auth/*` routes — which is exactly where it's needed. Don't
   expect it elsewhere.

### ⚠️ Cross-site cookie gotcha (the #1 thing that breaks headless auth)

The refresh cookie is `SameSite=None; Secure`, so it *can* be sent cross-site — **but
modern browsers (Safari ITP, Chrome's third-party-cookie phase-out) block third-party
cookies.** If the frontend and the CMS are on **different registrable domains**
(`app.netlify.app` calling `cms.clientsite.com`), the refresh cookie will be dropped
and sessions won't persist past the access-token TTL.

**Fix / recommendation:** host the CMS on a **subdomain of the production frontend
domain** (e.g. frontend `www.clientsite.com`, CMS `cms.clientsite.com`). They share the
registrable domain `clientsite.com`, so the cookie is *same-site* and first-party —
refresh works everywhere, including Safari. Flag this in the infra/DNS setup as a
requirement, not a nice-to-have.

### Session lifecycle

```
login ──> { access_token (memory), refresh cookie (browser, HttpOnly) }
   │
   ├─ authed request ─ Authorization: Bearer <access>
   │
   ├─ access expires / 401 ─> POST /auth/refresh (cookie) ─> new access + rotated cookie
   │
   ├─ logout ─> POST /auth/logout (revokes this family, clears cookie)
   └─ logout-all ─> POST /auth/logout-all (bumps token_version, kills every session)
```

---

## 5. Endpoint contract — `blueworx/v1`

**Auth column:** `none` = public · `access` = valid `Authorization: Bearer` ·
`refresh` = valid refresh cookie · `access+reauth` = access token **and** current
password in the body.

All errors use one envelope (§7). All responses are JSON.

### 5.1 Auth — [`auth.php`](includes/rest/auth.php)

| Method · Path | Auth | Request body | Success response |
|---|---|---|---|
| `POST /auth/login` | none | `{ login, password }` | `{ access_token, token_type: "Bearer", expires_in, user }` + sets refresh cookie |
| `POST /auth/refresh` | refresh | — (cookie) | `{ access_token, token_type, expires_in }` + rotated cookie |
| `POST /auth/logout` | refresh | — (cookie) | `{ ok: true }` + cleared cookie |
| `POST /auth/logout-all` | access | — | `{ ok: true }` (bumps token version, revokes all) |
| `GET  /auth/me` | access | — | user payload **+** `capabilities: string[]` |

`login` accepts a username **or** email. `user` / `/auth/me` payload shape:

```jsonc
{
  "id": 12,
  "email": "user@example.com",
  "username": "user@example.com",
  "display_name": "Jane Doe",
  "first_name": "Jane",
  "last_name": "Doe",
  "roles": ["subscriber"],
  "capabilities": ["read", "level_0"]   // /auth/me only
}
```

### 5.2 Accounts — [`account.php`](includes/rest/account.php)

| Method · Path | Auth | Request body | Response | Notes |
|---|---|---|---|---|
| `POST /account/register` | none | `{ email, password, invite_token? }` | generic 200 **or** a session | Mode-gated; `403` when `closed`. **Non-enumerating** (see below). If verification is off, returns a full login session instead. |
| `POST /account/verify` | none | `{ token }` | `{ ok: true }` | Single-use, expiring token from the email link. |
| `POST /account/resend-verification` | none | `{ email }` | generic 200 | Always generic. |
| `POST /account/password/forgot` | none | `{ email }` | generic 200 | Always 200, even for unknown emails. |
| `POST /account/password/reset` | none | `{ token, password }` | `{ ok: true }` | Revokes all sessions. |
| `POST /account/password/change` | access | `{ current_password, new_password }` | `{ ok: true }` | Revokes all sessions. |
| `PATCH /account` | access | `{ display_name?, first_name?, last_name?, nickname? }` | updated user payload | Whitelisted fields only; unknown fields ignored. |
| `DELETE /account` | access+reauth | `{ current_password }` | `{ ok: true }` | Re-auth required; revokes sessions then deletes the user. |

- **Password minimum: 8 characters** (register, reset, change). Enforce this in the UI
  to avoid a round-trip `400 blueworx_weak_password`.
- **Non-enumeration:** register / forgot / resend deliberately return the *same*
  generic success (`{ ok: true, message: "If that email can be used, …" }`) whether or
  not the email exists. Your UI must show "check your email" for **all** of these —
  never "email already registered" or "no such account".

### 5.3 Public content — [`content.php`](includes/rest/content.php)

| Method · Path | Auth | Query | Response |
|---|---|---|---|
| `GET /menus/{location}` | none | — | `{ location, items: MenuItem[] }`; `404` if no menu is assigned to that theme location |
| `GET /site` | none | — | Whitelisted site config (§6.2) |
| `GET /resolve` | none | `?uri=<path or url>` (required) | `{ type, id, slug, rest_url, template }` (§6.1) |
| `GET /acf-options` | none | — | ACF options-page fields as a flat object (`{}` if ACF is inactive) |
| `POST /render` | none | body: `{ content, with_global_enqueue? }` | `{ html, shortcodes, styles[], scripts[] }` (§6.3) |

### 6.3 Rendering shortcodes

A shortcode's **markup** already reaches you through `content.rendered`, because
`wp/v2` runs `do_shortcode()` server-side. What does not reach you is its **CSS
and JS**: plugins enqueue those on `wp_enqueue_scripts`, which never fires for a
REST request. That is why a SureCart table or a form arrives as inert markup or
an empty container.

`POST /render` renders the shortcode *and* tells you what it enqueued:

```jsonc
{
  "html": "<div class=\"...\">…</div>",
  "shortcodes": ["sureforms"],
  "styles":  [{ "handle": "…", "src": "https://…", "deps": [], "media": "all", "inline": ["…"] }],
  "scripts": [{ "handle": "…", "src": "https://…", "deps": [],
                "data": "var cfg = {…};", "before": [], "after": [], "strategy": "defer" }]
}
```

Notes that matter when you consume it:

- **Load in array order.** The lists are the full dependency closure, dependencies
  first — a script's `deps` are already included as their own entries.
- **Skip entries with an empty `src`.** Some handles (`jquery`) are grouping
  aliases with no file of their own, only dependencies.
- **`data` is `wp_localize_script` output.** Inject it *before* the script, or
  anything reading its config object throws on load.
- **Tags must be allowlisted** in *BlueWorx → Headless → Renderable shortcodes*.
  It is empty by default, so the endpoint refuses everything until configured —
  a shortcode is a PHP function, so the allowlist is what stops an
  unauthenticated caller invoking arbitrary code. Requests are rate limited, and
  a request mixing allowed and disallowed tags is refused whole rather than
  rendered in part.
- **`with_global_enqueue: true`** additionally fires `wp_enqueue_scripts` for
  shortcodes that register assets there rather than in their own callback. It
  also pulls in whatever the theme and every other plugin enqueue site-wide, so
  it is off by default.

**This is a workaround, not a cure.** Shortcodes depending on `wp_head`, on the
loop, or on inline output outside the enqueue system may still misbehave, and
each third-party plugin is its own compatibility question. Test the ones you
actually use.

`MenuItem` is a recursive tree:

```jsonc
{
  "id": 45, "title": "About", "url": "https://cms.example.com/about/",
  "target": "", "object": "page", "object_id": 12,
  "children": [ /* MenuItem[] */ ]
}
```

> **Rewrite menu URLs.** Menu `url` values point at the **WordPress** origin. Strip the
> origin and keep the path when rendering `<Link>`s, or nav links will bounce users to
> the CMS.

### 5.4 SureCart proxy — §9 (only registered when enabled + API key present)

---

## 6. Content model & routing

### 6.1 `/resolve` — turn a path into something to fetch

Given a frontend path, `/resolve` tells you what WordPress object (if any) lives there
and where to fetch its body from. Use it in a catch-all route or `generateMetadata`.

```
GET /wp-json/blueworx/v1/resolve?uri=/about
```

Response shape:

```jsonc
{
  "type": "page",        // the post type, or "front" or "404"
  "id": 12,
  "slug": "about",
  "rest_url": "https://cms.example.com/wp-json/wp/v2/pages/12",  // "" when not REST-enabled
  "template": "single"   // "single" | "front" | "404"
}
```

**What the shipped code actually resolves** (be precise — don't build against the plan):

- A path that maps to a **post or page** → `type` = that post type, `template: "single"`,
  `rest_url` pointing at the `wp/v2` resource (empty string if the type isn't
  REST-enabled — see the CPT setting in §2).
- The **home path** (`/`) → `{ type: "front", template: "front", id: page_on_front }`.
- **Everything else** → `{ type: "404", template: "404" }`.

> Category/tag/date **archives and term pages are not resolved** by the current code —
> they come back as `404`. If a project needs archive routing, that's a plugin change
> (extend `blueworx_headless_route_resolve`), not something to hack around on the
> frontend. Fetch listing/archive data directly from `wp/v2` with query params instead.

Typical flow for a dynamic page:

1. `GET /resolve?uri=<path>` → get `rest_url` + `type`.
2. If `type === "404"` → `notFound()`.
3. `GET rest_url` (core `wp/v2`) → render `title.rendered`, `content.rendered`, `acf`, etc.

### 6.2 `/site` — global site config

```jsonc
{
  "name": "…", "description": "…", "url": "https://cms.example.com",
  "admin_url": "…", "language": "en-US", "timezone": "Europe/London",
  "date_format": "F j, Y", "time_format": "g:i a",
  "posts_per_page": 10, "show_on_front": "page",
  "page_on_front": 2, "page_for_posts": 5,
  "site_logo": "https://…/logo.png"   // or null
}
```

Fetch once (cached / ISR) for site name, logo, and front-page wiring. Extensible
server-side via the `blueworx_headless_site_fields` filter.

### 6.3 ACF fields

- **On content:** ACF fields are attached to **core `wp/v2` responses** for every
  public post type as an `acf` object on each item (via `register_rest_field`). No
  separate call — read `post.acf.<field>` from the `wp/v2` payload.
- **Options page:** `GET /acf-options` returns the ACF *options* fields (global content
  like footer, social links). Empty object if ACF isn't installed.

### 6.4 Custom post types

A CPT is only in `wp/v2` if its key is listed in **BlueWorx → Headless → CPTs in REST**
(or the CPT already registered `show_in_rest`). Coordinate the exact CPT keys with the
CMS config before building pages that depend on them; a missing key means an empty
`rest_url` from `/resolve` and no `wp/v2` route.

---

## 7. Error envelope & codes

Every error (any status ≥ 400) is the standard WordPress `WP_Error` JSON:

```jsonc
{
  "code": "blueworx_invalid_login",
  "message": "Invalid username/email or password.",
  "data": { "status": 401 }
}
```

Rate-limit errors add `data.retry_after` (seconds):

```jsonc
{ "code": "blueworx_rate_limited", "message": "Too many attempts. …",
  "data": { "status": 429, "retry_after": 480 } }
```

Codes worth handling explicitly in the UI:

| Code | Status | Meaning / UI action |
|------|--------|---------------------|
| `blueworx_auth_unconfigured` | 503 | CMS missing `BLUEWORX_LABS_JWT_SECRET`. Config error, not user error. |
| `blueworx_invalid_login` | 401 | Wrong credentials. |
| `blueworx_email_unverified` | 403 | Tell the user to confirm their email; offer "resend". |
| `blueworx_registration_closed` | 403 | Hide/disable the sign-up form. |
| `blueworx_weak_password` | 400 | Password < 8 chars. |
| `blueworx_invalid_token` | 400 | Verify/reset link expired or bad. |
| `blueworx_refresh_reuse` | 401 | Session revoked for security — force full re-login. |
| `blueworx_rate_limited` | 429 | Back off; respect `retry_after`. |

Rate limits (per client IP): login = configured (default 5 / 15 min) · register/verify =
10/hr · forgot = 5/hr · reset = 10/hr.

---

## 8. On-demand revalidation (ISR)

**Direction: CMS → frontend.** When content changes and revalidation is enabled, the
plugin fires a **non-blocking** `POST` to the configured **Revalidation URL** (2 s
timeout, never triggers a Netlify build):

```
POST <revalidate_url>
Content-Type: application/json
X-Blueworx-Revalidate: <BLUEWORX_LABS_REVALIDATE_SECRET>

{ "paths": ["/about"] }
```

Implement the receiver as a Next.js route handler and verify the shared secret with a
constant-time comparison:

```ts
// app/api/revalidate/route.ts
import { revalidatePath } from "next/cache";
import { NextRequest, NextResponse } from "next/server";
import { timingSafeEqual } from "node:crypto";

export async function POST(req: NextRequest) {
  const provided = req.headers.get("x-blueworx-revalidate") ?? "";
  const expected = process.env.REVALIDATE_SECRET ?? "";
  const a = Buffer.from(provided), b = Buffer.from(expected);
  if (a.length !== b.length || !timingSafeEqual(a, b)) {
    return NextResponse.json({ ok: false }, { status: 401 });
  }
  const { paths } = (await req.json()) as { paths?: string[] };
  for (const p of paths ?? []) revalidatePath(p);
  return NextResponse.json({ ok: true, revalidated: paths ?? [] });
}
```

Set **BlueWorx → Headless → Revalidation URL** to
`https://<frontend>/api/revalidate` and define `BLUEWORX_LABS_REVALIDATE_SECRET` in
`wp-config.php` = `REVALIDATE_SECRET` in Netlify. Pair with `revalidate` on your fetches
(e.g. `next: { revalidate: 3600, tags: [...] }`) so pages have a baseline TTL and the
webhook provides instant freshness on edits.

---

## 9. SureCart proxy (optional commerce)

Only registered when **SureCart proxy** is enabled *and* `BLUEWORX_LABS_SURECART_API_KEY`
is set. The SureCart secret stays on the server; per-user endpoints are scoped to the
caller's mapped SureCart customer and **fail closed** (a record is returned only when
positively confirmed to belong to that customer).

| Method · Path | Auth | Scope |
|---|---|---|
| `GET /surecart/products` | none | Public catalogue |
| `GET /surecart/products/{id}` | none | Public |
| `GET /surecart/prices` | none | Public |
| `GET /surecart/me/orders` | access | Caller's customer |
| `GET /surecart/me/subscriptions` | access | Caller's customer |
| `GET /surecart/me/invoices` | access | Caller's customer |
| `POST /surecart/checkout` | access | Creates a checkout for the caller's customer |
| `POST /surecart/me/subscriptions/{id}/cancel` | access | Ownership verified before cancel |

Public list endpoints pass through SureCart query params. `/me/*` returns `{ data: [] }`
when the user has no SureCart customer yet. Responses are SureCart's own shapes,
passed through (or normalised to the error envelope on upstream failure).

---

## 10. Reference API client (drop-in, adapt as needed)

A minimal browser client implementing in-memory access token + single-flight refresh on
`401`. This is the recommended baseline — don't hand-roll the refresh loop.

```ts
// lib/wp-client.ts
import { BLUEWORX_API } from "./config";

let accessToken: string | null = null;
let refreshing: Promise<boolean> | null = null;

export function setAccessToken(t: string | null) { accessToken = t; }

async function refresh(): Promise<boolean> {
  // Single-flight: concurrent 401s share one refresh call.
  refreshing ??= (async () => {
    try {
      const res = await fetch(`${BLUEWORX_API}/auth/refresh`, {
        method: "POST",
        credentials: "include", // sends the HttpOnly refresh cookie
      });
      if (!res.ok) return false;
      const data = await res.json();
      accessToken = data.access_token;
      return true;
    } catch {
      return false;
    } finally {
      refreshing = null;
    }
  })();
  return refreshing;
}

/** Authenticated fetch against blueworx/v1 with one automatic refresh+retry. */
export async function api(path: string, init: RequestInit = {}): Promise<Response> {
  const call = () =>
    fetch(`${BLUEWORX_API}${path}`, {
      ...init,
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        ...(accessToken ? { Authorization: `Bearer ${accessToken}` } : {}),
        ...init.headers,
      },
    });

  let res = await call();
  if (res.status === 401 && (await refresh())) res = await call();
  return res;
}

export async function login(loginId: string, password: string) {
  const res = await fetch(`${BLUEWORX_API}/auth/login`, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ login: loginId, password }),
  });
  if (!res.ok) throw await res.json();
  const data = await res.json();
  accessToken = data.access_token;
  return data.user;
}

export async function logout() {
  await fetch(`${BLUEWORX_API}/auth/logout`, { method: "POST", credentials: "include" });
  accessToken = null;
}

/** Restore a session on app load (page reload loses the in-memory token). */
export async function restoreSession(): Promise<boolean> {
  return refresh();
}
```

**Bootstrapping the session:** call `restoreSession()` once on mount (e.g. in a client
auth provider). If it returns `true`, follow with `GET /auth/me` to hydrate the user.

---

## 11. SSR vs client data — where each piece of data lives

- **Public content** (pages, posts, menus, `/site`, ACF options) → fetch in **Server
  Components** with `fetch` caching / ISR. No token needed. This is the bulk of the site
  and should be statically rendered + revalidated.
- **Per-user content** (`/auth/me`, account screens, SureCart `/me/*`) → the access
  token lives in the **browser** only, so fetch these from **Client Components** after
  hydration using the §10 client. Don't try to read the access token in a Server
  Component — it isn't there.
- The refresh cookie is `HttpOnly` and path-scoped to `/auth/`, so Next.js middleware /
  route handlers can't use it as a general session either. Treat authenticated data as a
  client concern unless you add a dedicated BFF layer (out of scope here).

---

## 12. BlueWorx house conventions for the headless build

Follow these so the build matches every other BlueWorx project and passes CI (see the
project `CLAUDE.md` and `bluegroup_core_foundation`):

- **Stack:** Next.js (App Router) + TypeScript, scaffolded via `create-next-app`.
- **UI:** Radix Themes as the component base · `lucide-react` icons · Tailwind CSS ·
  design tokens from `styles.refero.design`.
- **Animation:** `tailwindcss-animate` for simple cases, GSAP for complex.
- **No page builders.** Everything in code.
- **Deployment:** Netlify handles install/build/deploy on merge — nothing manual. Secrets
  are Netlify env vars, never committed.
- **CI guardrails (every PR):** lint passes · build passes · version bumped · changelog
  updated · no new dependency without `approved-deps.json` approval · new functionality
  or a real bug fix ships with a Playwright test.
- **Workflow:** branch off `main` (short descriptive name) → PR → checks → review →
  merge. Every change starts from an approved GitHub Issue. Never commit to `main`.
- **Accessibility:** real form labels (login/register/reset), meaningful alt text,
  readable contrast, full keyboard access, correct heading order.

---

## 13. First-build checklist

**CMS side (confirm before wiring the frontend):**

- [ ] `BLUEWORX_LABS_JWT_SECRET` defined in `wp-config.php`; **BlueWorx → Headless**
      shows JWT as *Configured*.
- [ ] Frontend origin added to **Allowed origins** (exact scheme + host, no trailing `/`).
- [ ] **Frontend URL** set (so email links point at the app).
- [ ] Registration mode + email verification set to the intended policy.
- [ ] Any CPTs the frontend renders are listed in **CPTs in REST**.
- [ ] CMS on a **subdomain of the frontend's registrable domain** (refresh-cookie sanity).
- [ ] If using ISR: revalidation enabled, URL = `https://<frontend>/api/revalidate`,
      `BLUEWORX_LABS_REVALIDATE_SECRET` defined.

**Frontend side:**

- [ ] `NEXT_PUBLIC_WORDPRESS_URL` (and `REVALIDATE_SECRET` if using ISR) set in Netlify.
- [ ] Config module deriving `BLUEWORX_API` / `WP_API` from the origin (§3).
- [ ] API client from §10 in place; `restoreSession()` called on app load.
- [ ] All credentialed calls use `credentials: 'include'`.
- [ ] Catch-all route using `/resolve` → `wp/v2` fetch → render; `404` handled.
- [ ] Menu URLs rewritten from CMS origin to app paths.
- [ ] Non-enumerating auth flows show a generic "check your email" everywhere.
- [ ] `/api/revalidate` route implemented and secret-verified (if using ISR).
- [ ] Playwright test covering the primary new flow (e.g. login, or render a resolved page).
- [ ] Lint + build pass; version bumped; changelog updated.

---

*Maintained in the plugin repo. When the REST layer in [`includes/rest/`](includes/rest/)
changes, update this contract in the same PR.*
