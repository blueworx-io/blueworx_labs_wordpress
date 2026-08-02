# Single Sign-On — design

Date: 2026-08-02
Milestone: Replace: OAuth SSO (miniOrange OAuth Client) — issues #76–#83

## What we are building

One SSO feature in the BlueWorx Labs plugin: a site owner pastes an issuer URL, a
client ID and a client secret, and users can sign in with that identity provider.
It is a standards-based OpenID Connect authorization-code client, so it works with
PSA, Google Workspace, Microsoft Entra, Auth0 or anything else that publishes a
discovery document.

Nothing in this feature knows what PSA is. PSA is simply the first site to fill the
settings in. Everything site-specific — referee ID fields, WSO levels, the
profile-completion redirect — happens in the WSO client plugin, which listens to
events this feature fires.

Out of scope: multiple simultaneous providers, SAML, SSO for the REST API,
provider presets. One connection per site.

## Decisions taken

| Decision | Choice |
|---|---|
| Scope | One connection, any OIDC provider |
| Site-specific behaviour | Hooks, consumed by the WSO plugin |
| Account linking | Verified email links once, then a stable subject ID |
| id_token verification | RS256 via `openssl_verify` against JWKS — no new dependency |
| Secret storage | Own non-autoloaded option, write-only in the UI, excluded from support access |

## Architecture

New directory `includes/sso/`, loaded from the main plugin file behind the feature
gate, one file per job:

- `sso.php` — bootstrap, option accessors, feature registration.
- `discovery.php` — fetches and caches `/.well-known/openid-configuration`; manual
  endpoint overrides when a provider has no discovery document.
- `jwt.php` — JWKS fetch and cache with rotation handling, RS256 signature check,
  claim checks. Pure functions, no WordPress state beyond the transient cache.
- `flow.php` — builds the authorization request, handles the callback, exchanges
  the code, and hands verified claims to the user layer.
- `users.php` — matches or provisions the WordPress user and logs them in.
- `ui.php` — the login button and the `[blueworx_sso_button]` shortcode.
- `settings.php` — the detail screen rendering and save handler.
- `log.php` — a short ring buffer of recent attempts for diagnostics.

`users.php` and `flow.php` are the only files that touch WordPress user state.
`jwt.php` and `discovery.php` are testable without a browser.

## Data flow

1. A visitor hits the login trigger — `?blueworx_sso=login` on any URL, or any URL
   a site plugin claims via the `blueworx_sso_is_login_request` filter. That filter
   is how the existing `/?option=oauthredirect&app_name=PSA` links keep working
   through cutover, without that string appearing in this plugin.
2. We mint `state`, `nonce` and a PKCE verifier, store them in a single-use
   transient with a ten-minute TTL keyed to the browser, and redirect to the
   provider's authorization endpoint. PKCE S256 is used when discovery advertises
   it, and can be forced on or off in settings.
3. The provider returns to the registered redirect URI. We recognise a callback by
   the presence of `code` plus a `state` that matches a live transient — so the
   redirect URI can be the site root, which is what PSA has registered today, and
   PSA never has to re-register anything. New setups get a clean
   `?blueworx_sso=callback` URL shown in the settings screen for copy-paste.
4. We exchange the code at the token endpoint using `client_secret_basic`, falling
   back to `client_secret_post` when discovery says that is what the provider
   supports.
5. We verify the `id_token`: RS256 signature against the cached JWKS, then `iss`,
   `aud`, `exp`, `iat` and `nonce`. A `kid` we have not seen refetches the JWKS
   once, to handle key rotation. Any failure fails closed.
6. We call the userinfo endpoint for claims the id_token does not carry. Default
   scope is `openid email profile`; it is editable, because widening scope may need
   the provider to re-consent.
7. We resolve the user, log them in, fire `blueworx_sso_user_authenticated`, then
   redirect.

## User matching and provisioning

- Primary key is the provider's `sub`, stored in user meta alongside the issuer.
  Once a user has logged in once, email changes at the provider cannot detach them.
- If no user carries that `sub`, we match on email — but only when the provider
  asserts `email_verified`. An unverified email never links to an existing account.
  This is what lets every current PSA user keep logging in with no migration.
- If nothing matches and auto-registration is on, we create a user with the
  configured default role. Auto-registration is off by default.
- SSO never grants `administrator`, and never changes the role of an existing user.
  Both are enforced in code, not just in the UI.
- Usernames are derived from the email local part, deduplicated.

## Hooks — how site-specific behaviour attaches

- `blueworx_sso_is_login_request` (filter) — claim a legacy or custom trigger URL.
- `blueworx_sso_authorize_args` (filter) — add provider-specific request params.
- `blueworx_sso_new_user_data` (filter) — change the userdata array before insert.
- `blueworx_sso_user_authenticated` (action, `$user_id`, `$claims`, `$is_new`) —
  the main one. The WSO plugin writes its referee fields, assigns its levels and
  keeps `sc_customer` in place from here.
- `blueworx_sso_login_redirect` (filter) — where to send the user. The WSO
  plugin's profile-completeness check hooks in here, which resolves issue #81:
  this plugin owns one redirect decision and exposes it, the WSO plugin owns the
  policy, and ASE's competing setting gets switched off.

## Settings screen

A `sso` detail screen under Security & Access, matching the existing `login` and
`site_protection` screens. Fields: enable, issuer, client ID, client secret,
scope, auto-register, default role, button label, redirect-after-login. Endpoint
overrides and the PKCE toggle sit behind an "advanced" disclosure.

The screen shows discovery status, the callback URL to hand the provider, and the
last few login attempts with their outcome.

The client secret is stored in `blueworx_sso_client_secret` with autoload off,
rendered as an empty field with a "secret is set" note rather than the value, and
added to the support-access denylist so a read-only support session cannot read
it. Nothing in the feature exposes it over REST.

## Front end

The button is rendered server-side on the login form with the configured label —
no JavaScript patching the text afterwards, so the WSO plugin's
`sso-button-text.js` can be deleted. Icon is an inline SVG; the Font Awesome
enqueue goes. A `[blueworx_sso_button]` shortcode covers placement anywhere else.

## Failure handling

Every verification failure — bad state, replayed state, expired code, denied
consent, bad signature, wrong issuer or audience, unverified email — logs the
specific reason to the ring buffer and error log, and shows the user one generic
"we could not sign you in" message on the login screen. Reasons are never
reflected back to the browser.

## Testing

Playwright specs, no live provider needed:

- Settings screen saves, and the secret never appears in the rendered HTML.
- The login button renders with the configured label when enabled, and not when
  disabled.
- A callback with a missing, unknown or already-used `state` is rejected.
- The trigger URL redirects to the configured authorization endpoint with the
  expected `state`, `nonce` and PKCE challenge.

Token verification and user matching are covered by plain PHP scripts run from the
command line, with the handful of WordPress functions they touch stubbed. The JWT
script generates a real RSA key at run time and asserts that a good token verifies
and that every tampered variant — `alg` of none, wrong issuer, wrong audience,
expired, replayed nonce, unknown key, bad signature — is refused.

## Cutover

Ship disabled. Configure on staging against the real provider with a test account,
verify new-user provisioning, existing-user linking, the redirect chain and each
failure path, then enable in production during a quiet window with miniOrange
still installed as rollback. After a day of clean logins, remove miniOrange, clear
its `mo_oauth_*` options, retire the legacy trigger URL once nothing links to it,
and ask the provider to rotate the client secret.

## Notes for Luke

- No new dependencies. Signature verification uses PHP's own OpenSSL functions
  rather than re-vendoring a JWT library.
- Two changes are needed in the WSO client plugin: delete the button-text script,
  and move the referee-field, level and redirect logic onto the hooks above. Those
  are separate issues in that repo.
- Issue #76 is still blocked on the miniOrange export. Everything above can be
  built and tested without it; only the WSO-side claim mapping needs it.
