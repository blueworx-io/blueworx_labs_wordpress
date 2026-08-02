# Single Sign-On Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a first-party OpenID Connect login for the BlueWorx Labs plugin that works with any standards-based provider, so miniOrange can be removed.

**Architecture:** A new `includes/sso/` directory, one file per job, loaded from the main plugin file and gated by the `sso` feature flag. Outbound half builds an authorization request with `state`, `nonce` and PKCE. Inbound half verifies the `id_token` against the provider's JWKS using PHP's OpenSSL functions, resolves a WordPress user, and fires hooks that site-specific plugins listen to. Nothing in the feature names a particular provider.

**Tech Stack:** PHP 7.4+, WordPress 6.x, `wp_remote_get`/`wp_remote_post`, transients, `openssl_verify`. Playwright for browser tests, plain PHP scripts for the crypto tests. No new dependencies.

**Design doc:** `docs/superpowers/specs/2026-08-02-generic-sso-design.md`

## Global Constraints

- No new Composer or npm dependencies. Signature verification uses PHP's bundled OpenSSL functions.
- No provider name, no client name and no site-specific field name appears anywhere in this plugin's code, copy or option keys.
- Every option key is prefixed `blueworx_sso_`.
- The `sso` feature defaults to `'0'` — it opens network surface and hands out logins, so an update must never switch it on.
- SSO must never grant `administrator` and must never change the role of an existing user. Enforced in code, not only in the UI.
- Every failure path fails closed, logs the specific reason server-side, and shows the user one generic message.
- Follow the file's existing style: procedural functions prefixed `blueworx_sso_`, `if ( ! defined( 'ABSPATH' ) ) { exit; }` at the top of every file, translator-ready strings in the `blueworx-labs-wordpress` text domain.
- Bump the plugin version and add a changelog entry on the PR, per repo policy.
- Playwright specs run against the local `.wp-test` harness, not staging.

---

### Task 1: Feature registration and file skeleton

**Files:**
- Create: `includes/sso/sso.php`
- Modify: `blueworx-labs-wordpress.php` (require list, after `includes/login-security.php`)
- Modify: `includes/features.php` (`blueworx_get_feature_definitions()`, in the `security` section after `site_protection`)
- Test: `tests/sso.spec.js`

**Interfaces:**
- Consumes: `blueworx_feature_enabled( $key )` from `includes/features.php`.
- Produces:
  - `blueworx_sso_enabled(): bool` — feature flag AND a client ID AND a secret AND an issuer are all present.
  - `blueworx_sso_option( string $name, $default = '' )` — reads `blueworx_sso_{$name}`.
  - `blueworx_sso_callback_url(): string` — `home_url( '/?blueworx_sso=callback' )`.

- [ ] **Step 1: Write the failing test**

```js
// tests/sso.spec.js
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const toggleFor = (key) => `input.blueworx-feature-toggle[data-blueworx-feature="${key}"]`;

test.describe('Single sign-on', () => {
  test.skip(isPlaceholder || !ADMIN_USER || !ADMIN_PASS, 'No local harness configured.');

  test('the feature is offered and is off by default', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    await expect(page.locator(toggleFor('sso'))).toHaveCount(1);
    await expect(page.locator(toggleFor('sso'))).not.toBeChecked();
  });
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `npx playwright test tests/sso.spec.js -g "offered"`
Expected: FAIL — the toggle has count 0.

- [ ] **Step 3: Register the feature**

In `includes/features.php`, immediately after the `site_protection` entry:

```php
'sso'                   => array(
	'label'       => __( 'Single sign-on', 'blueworx-labs-wordpress' ),
	'description' => __( 'Lets people sign in with an external identity provider using OpenID Connect.', 'blueworx-labs-wordpress' ),
	'section'     => 'security',
	'detail'      => 'sso',
	'default'     => '0',
),
```

- [ ] **Step 4: Create the bootstrap file**

```php
<?php
/**
 * Single sign-on: option access and shared helpers.
 *
 * @package BlueWorxLabs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads an SSO option.
 *
 * @param string $name    Option name without the blueworx_sso_ prefix.
 * @param mixed  $default Value to return when the option is unset.
 * @return mixed
 */
function blueworx_sso_option( $name, $default = '' ) {
	return get_option( 'blueworx_sso_' . $name, $default );
}

/**
 * Whether the feature is on and fully configured.
 *
 * @return bool
 */
function blueworx_sso_enabled() {
	if ( ! blueworx_feature_enabled( 'sso' ) ) {
		return false;
	}

	foreach ( array( 'issuer', 'client_id', 'client_secret' ) as $required ) {
		if ( '' === trim( (string) blueworx_sso_option( $required ) ) ) {
			return false;
		}
	}

	return true;
}

/**
 * The callback URL a provider should be given for new setups.
 *
 * @return string
 */
function blueworx_sso_callback_url() {
	return home_url( '/?blueworx_sso=callback' );
}
```

- [ ] **Step 5: Load it**

Add to `blueworx-labs-wordpress.php` after the `login-security.php` line:

```php
require_once BLUEWORX_LABS_PATH . 'includes/sso/sso.php';
```

- [ ] **Step 6: Run the test and watch it pass**

Run: `npx playwright test tests/sso.spec.js -g "offered"`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add includes/sso/sso.php includes/features.php blueworx-labs-wordpress.php tests/sso.spec.js
git commit -m "Add the single sign-on feature flag and bootstrap"
```

---

### Task 2: Discovery

**Files:**
- Create: `includes/sso/discovery.php`
- Create: `tests/php/discovery-test.php`
- Modify: `package.json` (add a `test:php` script)
- Modify: `blueworx-labs-wordpress.php` (require)

**Interfaces:**
- Consumes: `blueworx_sso_option()`.
- Produces:
  - `blueworx_sso_discover( string $issuer ): array|WP_Error` — returns the discovery document, cached in the `blueworx_sso_discovery_{md5(issuer)}` transient for 12 hours.
  - `blueworx_sso_endpoint( string $name ): string` — `authorization_endpoint`, `token_endpoint`, `userinfo_endpoint` or `jwks_uri`; a non-empty `blueworx_sso_{$name}_override` option wins over discovery.
  - `blueworx_sso_discovery_supports( string $key, string $value ): bool` — true when the discovery document's `$key` array contains `$value`. Used for PKCE and token auth method probing.

- [ ] **Step 1: Write the failing test**

`tests/php/discovery-test.php` runs without WordPress by defining the four WordPress functions it needs as stubs, then asserting behaviour:

```php
<?php
// Minimal WordPress stubs so the pure logic can be tested from the CLI.
define( 'ABSPATH', __DIR__ );
$GLOBALS['transients'] = array();
$GLOBALS['options']    = array( 'blueworx_sso_token_endpoint_override' => 'https://override.test/token' );
$GLOBALS['http']       = array(
	'https://idp.test/.well-known/openid-configuration' => array(
		'issuer'                              => 'https://idp.test',
		'authorization_endpoint'              => 'https://idp.test/authorize',
		'token_endpoint'                      => 'https://idp.test/token',
		'jwks_uri'                            => 'https://idp.test/keys',
		'code_challenge_methods_supported'    => array( 'S256' ),
	),
);

function get_option( $k, $d = false ) { return isset( $GLOBALS['options'][ $k ] ) ? $GLOBALS['options'][ $k ] : $d; }
function get_transient( $k ) { return isset( $GLOBALS['transients'][ $k ] ) ? $GLOBALS['transients'][ $k ] : false; }
function set_transient( $k, $v, $t ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function untrailingslashit( $s ) { return rtrim( $s, '/' ); }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['calls'][] = $url;
	return isset( $GLOBALS['http'][ $url ] )
		? array( 'code' => 200, 'body' => wp_json_encode( $GLOBALS['http'][ $url ] ) )
		: new WP_Error( 'http', 'not found' );
}
function wp_json_encode( $d ) { return json_encode( $d ); }
class WP_Error { public $code; public $msg; public function __construct( $c, $m ) { $this->code = $c; $this->msg = $m; } }

require __DIR__ . '/../../includes/sso/discovery.php';

$failures = 0;
function check( $label, $actual, $expected ) {
	global $failures;
	if ( $actual === $expected ) { echo "ok   $label\n"; return; }
	$failures++;
	echo "FAIL $label: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
}

$GLOBALS['calls'] = array();
check( 'authorization endpoint from discovery', blueworx_sso_endpoint( 'authorization_endpoint' ), 'https://idp.test/authorize' );
check( 'override beats discovery', blueworx_sso_endpoint( 'token_endpoint' ), 'https://override.test/token' );
check( 'S256 detected', blueworx_sso_discovery_supports( 'code_challenge_methods_supported', 'S256' ), true );
check( 'plain not offered', blueworx_sso_discovery_supports( 'code_challenge_methods_supported', 'plain' ), false );
check( 'discovery fetched once', count( array_unique( $GLOBALS['calls'] ) ), 1 );
check( 'unknown issuer errors', is_wp_error( blueworx_sso_discover( 'https://nope.test' ) ), true );

exit( $failures > 0 ? 1 : 0 );
```

The test file must set `$GLOBALS['options']['blueworx_sso_issuer'] = 'https://idp.test';` before the checks so `blueworx_sso_endpoint()` knows which issuer to use.

- [ ] **Step 2: Run it and watch it fail**

Add to `package.json` scripts: `"test:php": "php tests/php/discovery-test.php && php tests/php/jwt-test.php"`.
Run: `php tests/php/discovery-test.php`
Expected: FAIL — `discovery.php` does not exist.

- [ ] **Step 3: Implement discovery**

```php
<?php
/**
 * Single sign-on: provider discovery.
 *
 * @package BlueWorxLabs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches the provider's discovery document, cached for 12 hours.
 *
 * @param string $issuer Issuer URL.
 * @return array|WP_Error
 */
function blueworx_sso_discover( $issuer ) {
	$issuer = untrailingslashit( trim( (string) $issuer ) );

	if ( '' === $issuer ) {
		return new WP_Error( 'blueworx_sso_no_issuer', __( 'No issuer is configured.', 'blueworx-labs-wordpress' ) );
	}

	$cache_key = 'blueworx_sso_discovery_' . md5( $issuer );
	$cached    = get_transient( $cache_key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$response = wp_remote_get(
		$issuer . '/.well-known/openid-configuration',
		array( 'timeout' => 10 )
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'blueworx_sso_discovery_failed', __( 'The identity provider did not answer.', 'blueworx-labs-wordpress' ) );
	}

	$document = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $document ) || empty( $document['authorization_endpoint'] ) ) {
		return new WP_Error( 'blueworx_sso_discovery_invalid', __( 'The identity provider returned an unusable configuration.', 'blueworx-labs-wordpress' ) );
	}

	set_transient( $cache_key, $document, 12 * HOUR_IN_SECONDS );

	return $document;
}

/**
 * Resolves a single endpoint, letting a manual override win.
 *
 * @param string $name Discovery key, e.g. token_endpoint.
 * @return string Empty string when unknown.
 */
function blueworx_sso_endpoint( $name ) {
	$override = trim( (string) get_option( 'blueworx_sso_' . $name . '_override', '' ) );

	if ( '' !== $override ) {
		return $override;
	}

	$document = blueworx_sso_discover( get_option( 'blueworx_sso_issuer', '' ) );

	if ( is_wp_error( $document ) || empty( $document[ $name ] ) ) {
		return '';
	}

	return (string) $document[ $name ];
}

/**
 * Whether the discovery document advertises a value in a list-valued key.
 *
 * @param string $key   Discovery key, e.g. code_challenge_methods_supported.
 * @param string $value Value to look for.
 * @return bool
 */
function blueworx_sso_discovery_supports( $key, $value ) {
	$document = blueworx_sso_discover( get_option( 'blueworx_sso_issuer', '' ) );

	if ( is_wp_error( $document ) || empty( $document[ $key ] ) || ! is_array( $document[ $key ] ) ) {
		return false;
	}

	return in_array( $value, $document[ $key ], true );
}
```

The CLI test must define `HOUR_IN_SECONDS` — add `define( 'HOUR_IN_SECONDS', 3600 );` to the stub block.

- [ ] **Step 4: Run the test and watch it pass**

Run: `php tests/php/discovery-test.php`
Expected: every line `ok`, exit code 0.

- [ ] **Step 5: Load it and commit**

Add `require_once BLUEWORX_LABS_PATH . 'includes/sso/discovery.php';` after the `sso.php` require.

```bash
git add includes/sso/discovery.php tests/php/discovery-test.php package.json blueworx-labs-wordpress.php
git commit -m "Add identity provider discovery with manual endpoint overrides"
```

---

### Task 3: id_token verification

**Files:**
- Create: `includes/sso/jwt.php`
- Create: `tests/php/jwt-test.php`
- Modify: `blueworx-labs-wordpress.php` (require)

**Interfaces:**
- Consumes: `blueworx_sso_endpoint( 'jwks_uri' )`.
- Produces:
  - `blueworx_sso_jwks( bool $force_refresh = false ): array|WP_Error` — the key set, cached 12 hours.
  - `blueworx_sso_jwk_to_pem( array $jwk ): string` — RSA JWK to a PEM public key, `''` on bad input.
  - `blueworx_sso_verify_id_token( string $jwt, string $issuer, string $client_id, string $nonce ): array|WP_Error` — verified claims, or an error whose code names the exact failure.

Only RS256 is accepted. An `alg` of `none`, `HS256` or anything else is rejected before any other work — this is the single most important check in the feature.

- [ ] **Step 1: Write the failing test**

`tests/php/jwt-test.php` generates a real RSA key at run time with `openssl_pkey_new()`, builds tokens with it, and asserts:

```php
$cases = array(
	// label, mutation applied to the claims or header, expected error code ('' means it must verify)
	array( 'a good token verifies',        function ( &$h, &$c ) {},                                  '' ),
	array( 'alg none is rejected',         function ( &$h, &$c ) { $h['alg'] = 'none'; },             'blueworx_sso_bad_alg' ),
	array( 'HS256 is rejected',            function ( &$h, &$c ) { $h['alg'] = 'HS256'; },            'blueworx_sso_bad_alg' ),
	array( 'a wrong issuer is rejected',   function ( &$h, &$c ) { $c['iss'] = 'https://evil.test'; }, 'blueworx_sso_bad_issuer' ),
	array( 'a wrong audience is rejected', function ( &$h, &$c ) { $c['aud'] = 'someone-else'; },     'blueworx_sso_bad_audience' ),
	array( 'an expired token is rejected', function ( &$h, &$c ) { $c['exp'] = time() - 60; },        'blueworx_sso_expired' ),
	array( 'a future iat is rejected',     function ( &$h, &$c ) { $c['iat'] = time() + 600; },       'blueworx_sso_bad_iat' ),
	array( 'a wrong nonce is rejected',    function ( &$h, &$c ) { $c['nonce'] = 'other'; },          'blueworx_sso_bad_nonce' ),
	array( 'an unknown kid is rejected',   function ( &$h, &$c ) { $h['kid'] = 'unknown'; },          'blueworx_sso_unknown_key' ),
);
```

Plus one case that signs with a second, different key while keeping the known `kid`, and expects `blueworx_sso_bad_signature`. Use the same stub-and-`check()` harness as Task 2, with `wp_remote_get` returning a JWKS built from the generated key's `n` and `e` (base64url of the modulus and exponent from `openssl_pkey_get_details()`).

- [ ] **Step 2: Run it and watch it fail**

Run: `php tests/php/jwt-test.php`
Expected: FAIL — `jwt.php` does not exist.

- [ ] **Step 3: Implement**

```php
/**
 * Verifies an id_token and returns its claims.
 *
 * @param string $jwt       The raw token.
 * @param string $issuer    Expected issuer.
 * @param string $client_id Expected audience.
 * @param string $nonce     Nonce sent with the authorization request.
 * @return array|WP_Error
 */
function blueworx_sso_verify_id_token( $jwt, $issuer, $client_id, $nonce ) {
	$parts = explode( '.', (string) $jwt );

	if ( 3 !== count( $parts ) ) {
		return new WP_Error( 'blueworx_sso_malformed', __( 'The sign-in token was malformed.', 'blueworx-labs-wordpress' ) );
	}

	$header = json_decode( blueworx_sso_base64url_decode( $parts[0] ), true );
	$claims = json_decode( blueworx_sso_base64url_decode( $parts[1] ), true );

	if ( ! is_array( $header ) || ! is_array( $claims ) ) {
		return new WP_Error( 'blueworx_sso_malformed', __( 'The sign-in token was malformed.', 'blueworx-labs-wordpress' ) );
	}

	// Only RS256. Never trust the token's own claim that it needs no signature.
	if ( empty( $header['alg'] ) || 'RS256' !== $header['alg'] ) {
		return new WP_Error( 'blueworx_sso_bad_alg', __( 'The sign-in token used an unsupported signing method.', 'blueworx-labs-wordpress' ) );
	}

	$kid = isset( $header['kid'] ) ? (string) $header['kid'] : '';
	$pem = blueworx_sso_pem_for_kid( $kid );

	if ( '' === $pem ) {
		// A key we have never seen may be a rotation. Refetch once, then give up.
		$pem = blueworx_sso_pem_for_kid( $kid, true );
	}

	if ( '' === $pem ) {
		return new WP_Error( 'blueworx_sso_unknown_key', __( 'The sign-in token was signed with an unknown key.', 'blueworx-labs-wordpress' ) );
	}

	$signed   = $parts[0] . '.' . $parts[1];
	$verified = openssl_verify( $signed, blueworx_sso_base64url_decode( $parts[2] ), $pem, OPENSSL_ALGO_SHA256 );

	if ( 1 !== $verified ) {
		return new WP_Error( 'blueworx_sso_bad_signature', __( 'The sign-in token signature did not check out.', 'blueworx-labs-wordpress' ) );
	}

	$now  = time();
	$skew = 120;

	if ( untrailingslashit( (string) ( isset( $claims['iss'] ) ? $claims['iss'] : '' ) ) !== untrailingslashit( $issuer ) ) {
		return new WP_Error( 'blueworx_sso_bad_issuer', __( 'The sign-in token came from the wrong issuer.', 'blueworx-labs-wordpress' ) );
	}

	$audience = isset( $claims['aud'] ) ? (array) $claims['aud'] : array();

	if ( ! in_array( $client_id, $audience, true ) ) {
		return new WP_Error( 'blueworx_sso_bad_audience', __( 'The sign-in token was issued for a different application.', 'blueworx-labs-wordpress' ) );
	}

	if ( empty( $claims['exp'] ) || (int) $claims['exp'] + $skew < $now ) {
		return new WP_Error( 'blueworx_sso_expired', __( 'The sign-in token had expired.', 'blueworx-labs-wordpress' ) );
	}

	if ( empty( $claims['iat'] ) || (int) $claims['iat'] - $skew > $now ) {
		return new WP_Error( 'blueworx_sso_bad_iat', __( 'The sign-in token was issued in the future.', 'blueworx-labs-wordpress' ) );
	}

	if ( '' !== $nonce && ( ! isset( $claims['nonce'] ) || ! hash_equals( $nonce, (string) $claims['nonce'] ) ) ) {
		return new WP_Error( 'blueworx_sso_bad_nonce', __( 'The sign-in token did not match this sign-in attempt.', 'blueworx-labs-wordpress' ) );
	}

	return $claims;
}
```

Supporting functions in the same file: `blueworx_sso_base64url_decode()`, `blueworx_sso_jwks( $force_refresh )` (transient `blueworx_sso_jwks`, 12 hours, refetches when `$force_refresh`), `blueworx_sso_pem_for_kid( $kid, $force_refresh = false )` (finds the JWK by `kid`, or the single key when the set has exactly one and no `kid` was given, then converts), and `blueworx_sso_jwk_to_pem()` which DER-encodes the `n`/`e` pair into a `PUBLIC KEY` PEM.

- [ ] **Step 4: Run the test and watch it pass**

Run: `php tests/php/jwt-test.php`
Expected: every case `ok`, exit code 0.

- [ ] **Step 5: Commit**

```bash
git add includes/sso/jwt.php tests/php/jwt-test.php blueworx-labs-wordpress.php
git commit -m "Verify sign-in tokens against the provider's published keys"
```

---

### Task 4: The outbound authorization request

**Files:**
- Create: `includes/sso/flow.php`
- Modify: `blueworx-labs-wordpress.php` (require)
- Test: `tests/sso.spec.js`

**Interfaces:**
- Consumes: `blueworx_sso_enabled()`, `blueworx_sso_option()`, `blueworx_sso_endpoint()`, `blueworx_sso_discovery_supports()`.
- Produces:
  - `blueworx_sso_login_url( string $redirect_to = '' ): string` — the local trigger URL.
  - `blueworx_sso_start(): void` — mints state, stores the attempt, redirects to the provider.
  - `blueworx_sso_attempt_key( string $state ): string` — `blueworx_sso_attempt_` plus a hash of the state.

- [ ] **Step 1: Write the failing test**

```js
test('the trigger URL redirects to the provider with state, nonce and PKCE', async ({ page }) => {
  // Settings are seeded by the harness fixture: issuer https://idp.test, client id test-client.
  const response = await page.request.get('/?blueworx_sso=login', { maxRedirects: 0 });
  expect(response.status()).toBe(302);
  const target = new URL(response.headers()['location']);
  expect(target.origin + target.pathname).toBe('https://idp.test/authorize');
  expect(target.searchParams.get('response_type')).toBe('code');
  expect(target.searchParams.get('client_id')).toBe('test-client');
  expect(target.searchParams.get('state')).toHaveLength(43);
  expect(target.searchParams.get('nonce')).toHaveLength(43);
  expect(target.searchParams.get('code_challenge_method')).toBe('S256');
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `npx playwright test tests/sso.spec.js -g "trigger URL"`
Expected: FAIL — 200, not 302.

- [ ] **Step 3: Implement the outbound half**

Key points for the implementer:

```php
function blueworx_sso_start() {
	$state    = blueworx_sso_random_string();
	$nonce    = blueworx_sso_random_string();
	$verifier = blueworx_sso_random_string();

	$attempt = array(
		'nonce'       => $nonce,
		'verifier'    => $verifier,
		'redirect_to' => blueworx_sso_requested_redirect(),
		'created'     => time(),
	);

	// Single-use, ten minutes. The transient IS the record that this attempt is
	// live; deleting it on callback is what makes a replayed state fail.
	set_transient( blueworx_sso_attempt_key( $state ), $attempt, 10 * MINUTE_IN_SECONDS );

	$args = array(
		'response_type' => 'code',
		'client_id'     => blueworx_sso_option( 'client_id' ),
		'redirect_uri'  => blueworx_sso_redirect_uri(),
		'scope'         => blueworx_sso_option( 'scope', 'openid email profile' ),
		'state'         => $state,
		'nonce'         => $nonce,
	);

	if ( blueworx_sso_use_pkce() ) {
		$args['code_challenge']        = blueworx_sso_base64url_encode( hash( 'sha256', $verifier, true ) );
		$args['code_challenge_method'] = 'S256';
	}

	/**
	 * Filters the authorization request arguments.
	 *
	 * @param array $args Query arguments.
	 */
	$args = apply_filters( 'blueworx_sso_authorize_args', $args );

	wp_redirect( add_query_arg( array_map( 'rawurlencode', $args ), blueworx_sso_endpoint( 'authorization_endpoint' ) ) );
	exit;
}
```

- `blueworx_sso_random_string()` returns `blueworx_sso_base64url_encode( random_bytes( 32 ) )` — 43 characters.
- `blueworx_sso_use_pkce()` returns true when the `pkce` option is `auto` and discovery advertises `S256`, or when it is `on`; false when it is `off`. Default `auto`.
- `blueworx_sso_redirect_uri()` returns the `redirect_uri` option when set, otherwise `blueworx_sso_callback_url()`. PSA-style setups paste the site root here; that is why the callback is recognised by parameters rather than by path.
- Hook the dispatcher on `init` at priority 1, alongside the existing login interception:

```php
function blueworx_sso_dispatch() {
	if ( ! blueworx_sso_enabled() ) {
		return;
	}

	$action = isset( $_GET['blueworx_sso'] ) ? sanitize_key( wp_unslash( $_GET['blueworx_sso'] ) ) : '';

	/**
	 * Filters whether the current request is an SSO sign-in trigger.
	 *
	 * Lets a site plugin keep a legacy sign-in URL working after cutover.
	 *
	 * @param bool $is_login Whether this request should start sign-in.
	 */
	if ( apply_filters( 'blueworx_sso_is_login_request', 'login' === $action ) ) {
		blueworx_sso_start();
	}

	// A callback is any request carrying a code plus a state we minted. That
	// makes the site root usable as the registered redirect URI.
	if ( isset( $_GET['code'], $_GET['state'] ) || 'callback' === $action ) {
		blueworx_sso_handle_callback();
	}
}
add_action( 'init', 'blueworx_sso_dispatch', 1 );
```

For this task `blueworx_sso_handle_callback()` may be a stub that returns; Task 5 fills it in.

- [ ] **Step 4: Run the test and watch it pass**

Run: `npx playwright test tests/sso.spec.js -g "trigger URL"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/sso/flow.php blueworx-labs-wordpress.php tests/sso.spec.js
git commit -m "Send users to the identity provider with state, nonce and PKCE"
```

---

### Task 5: The callback, token exchange and userinfo

**Files:**
- Modify: `includes/sso/flow.php`
- Create: `includes/sso/log.php`
- Modify: `blueworx-labs-wordpress.php` (require)
- Test: `tests/sso.spec.js`

**Interfaces:**
- Consumes: `blueworx_sso_verify_id_token()`, `blueworx_sso_endpoint()`, `blueworx_sso_resolve_user()` (Task 6 — stub it to return a `WP_Error` until then).
- Produces:
  - `blueworx_sso_handle_callback(): void`
  - `blueworx_sso_log( string $outcome, string $detail = '' ): void` — appends to the `blueworx_sso_log` option, newest first, capped at 20 entries, autoload off.
  - `blueworx_sso_fail( string $reason ): void` — logs, then redirects to the login screen with `?blueworx_sso_error=1`.

- [ ] **Step 1: Write the failing tests**

```js
test('a callback with an unknown state is refused', async ({ page }) => {
  const response = await page.request.get('/?blueworx_sso=callback&code=abc&state=nonsense', { maxRedirects: 0 });
  expect(response.status()).toBe(302);
  expect(response.headers()['location']).toContain('blueworx_sso_error=1');
});

test('a callback with no state at all is refused', async ({ page }) => {
  const response = await page.request.get('/?blueworx_sso=callback&code=abc', { maxRedirects: 0 });
  expect(response.status()).toBe(302);
  expect(response.headers()['location']).toContain('blueworx_sso_error=1');
});

test('the error message on the login screen says nothing specific', async ({ page }) => {
  await page.goto('/?blueworx_sso_error=1', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('body')).not.toContainText('state');
  await expect(page.locator('body')).not.toContainText('signature');
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `npx playwright test tests/sso.spec.js -g "callback"`
Expected: FAIL — 200 rather than a redirect.

- [ ] **Step 3: Implement**

```php
function blueworx_sso_handle_callback() {
	if ( isset( $_GET['error'] ) ) {
		blueworx_sso_fail( 'provider_error:' . sanitize_text_field( wp_unslash( $_GET['error'] ) ) );
	}

	$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
	$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

	if ( '' === $state || '' === $code ) {
		blueworx_sso_fail( 'missing_state_or_code' );
	}

	$key     = blueworx_sso_attempt_key( $state );
	$attempt = get_transient( $key );

	// Delete before use: a state is good for exactly one callback, so a replay
	// of the same URL finds nothing and fails.
	delete_transient( $key );

	if ( ! is_array( $attempt ) ) {
		blueworx_sso_fail( 'unknown_or_replayed_state' );
	}

	$tokens = blueworx_sso_exchange_code( $code, $attempt['verifier'] );

	if ( is_wp_error( $tokens ) ) {
		blueworx_sso_fail( $tokens->get_error_code() );
	}

	$claims = blueworx_sso_verify_id_token(
		isset( $tokens['id_token'] ) ? $tokens['id_token'] : '',
		blueworx_sso_option( 'issuer' ),
		blueworx_sso_option( 'client_id' ),
		$attempt['nonce']
	);

	if ( is_wp_error( $claims ) ) {
		blueworx_sso_fail( $claims->get_error_code() );
	}

	$claims = array_merge( blueworx_sso_userinfo( $tokens ), $claims );
	$user   = blueworx_sso_resolve_user( $claims );

	if ( is_wp_error( $user ) ) {
		blueworx_sso_fail( $user->get_error_code() );
	}

	blueworx_sso_log( 'success', $user->user_login );
	update_option( 'blueworx_sso_last_success', time(), false );

	wp_set_auth_cookie( $user->ID, false );
	do_action( 'wp_login', $user->user_login, $user );

	/**
	 * Filters where a user lands after signing in.
	 *
	 * @param string  $redirect Destination URL.
	 * @param WP_User $user     The signed-in user.
	 * @param array   $claims   Verified claims from the provider.
	 */
	$redirect = apply_filters(
		'blueworx_sso_login_redirect',
		'' !== $attempt['redirect_to'] ? $attempt['redirect_to'] : blueworx_sso_option( 'redirect_after_login', admin_url() ),
		$user,
		$claims
	);

	wp_safe_redirect( $redirect );
	exit;
}
```

- `blueworx_sso_exchange_code()` POSTs `grant_type=authorization_code`, `code`, `redirect_uri` and `code_verifier` to the token endpoint. Client authentication is `client_secret_basic` (an `Authorization: Basic` header) unless discovery lists only `client_secret_post`, in which case the credentials go in the body. Non-200 or a missing `id_token` returns a `WP_Error`.
- `blueworx_sso_userinfo()` GETs the userinfo endpoint with the access token as a bearer, returns `array()` on any failure — claims from the verified id_token always win, which is why it is the second argument to `array_merge`.
- `blueworx_sso_fail()` writes the reason to the log and `error_log()`, then `wp_safe_redirect( add_query_arg( 'blueworx_sso_error', '1', wp_login_url() ) ); exit;`. The reason never reaches the browser.
- Add a `login_message` filter that renders one generic notice when `blueworx_sso_error` is present: "We could not sign you in. Please try again."

- [ ] **Step 4: Run the tests and watch them pass**

Run: `npx playwright test tests/sso.spec.js -g "callback"` and `-g "error message"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/sso/flow.php includes/sso/log.php blueworx-labs-wordpress.php tests/sso.spec.js
git commit -m "Handle the provider callback, exchange the code and verify the result"
```

---

### Task 6: Matching, provisioning and hooks

**Files:**
- Create: `includes/sso/users.php`
- Modify: `blueworx-labs-wordpress.php` (require)
- Create: `tests/php/users-test.php`

**Interfaces:**
- Consumes: verified `$claims` from Task 5.
- Produces: `blueworx_sso_resolve_user( array $claims ): WP_User|WP_Error`.

User meta written: `blueworx_sso_subject` (the `sub`) and `blueworx_sso_issuer`.

- [ ] **Step 1: Write the failing test**

`tests/php/users-test.php` stubs `get_users`, `get_user_by`, `wp_insert_user`, `update_user_meta` and asserts, in order:

```
ok  an existing subject match returns that user and writes no new user
ok  a verified email with no subject links, and stores the subject
ok  an unverified email with no subject is refused with blueworx_sso_email_unverified
ok  no match with auto-registration off is refused with blueworx_sso_no_account
ok  no match with auto-registration on creates a user on the configured default role
ok  a default role of administrator is refused and falls back to subscriber
ok  an existing user's role is never changed
ok  a missing sub claim is refused with blueworx_sso_no_subject
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php tests/php/users-test.php`
Expected: FAIL — `users.php` does not exist.

- [ ] **Step 3: Implement**

Resolution order, exactly:

1. Reject when `sub` is missing — `blueworx_sso_no_subject`.
2. Look for a user whose `blueworx_sso_subject` meta equals `sub` and whose `blueworx_sso_issuer` meta equals the configured issuer. Found: return it. Its role is not touched.
3. Otherwise, if `email` is present AND `email_verified` is strictly true, look the email up. Found: write the subject and issuer meta onto it, then return it. This is the one-time link that carries existing accounts across cutover.
4. If `email` is present but `email_verified` is not true, refuse with `blueworx_sso_email_unverified`. Never link an unverified email.
5. No match and auto-registration off: refuse with `blueworx_sso_no_account`.
6. No match and auto-registration on: build the userdata, filter it, insert, write the meta.

```php
$role = blueworx_sso_option( 'default_role', 'subscriber' );

// A provider must never be able to hand out the keys to the site.
if ( 'administrator' === $role || ! get_role( $role ) ) {
	$role = 'subscriber';
}

$userdata = array(
	'user_login' => blueworx_sso_unique_login( $claims ),
	'user_email' => $claims['email'],
	'first_name' => isset( $claims['given_name'] ) ? $claims['given_name'] : '',
	'last_name'  => isset( $claims['family_name'] ) ? $claims['family_name'] : '',
	'user_pass'  => wp_generate_password( 32, true, true ),
	'role'       => $role,
);

/**
 * Filters the data used to create a user on first sign-in.
 *
 * @param array $userdata Arguments for wp_insert_user().
 * @param array $claims   Verified claims from the provider.
 */
$userdata = apply_filters( 'blueworx_sso_new_user_data', $userdata, $claims );

// The role is re-checked after the filter for the same reason it was checked
// before it: nothing may promote a sign-in to administrator.
if ( 'administrator' === $userdata['role'] ) {
	$userdata['role'] = 'subscriber';
}
```

- `blueworx_sso_unique_login()` takes the email's local part, `sanitize_user()`s it, and appends `-2`, `-3` and so on until free.
- After a successful resolve, in either branch, fire:

```php
/**
 * Fires after a user has signed in through SSO.
 *
 * Site-specific behaviour — profile fields, extra roles, membership data —
 * belongs here, in the plugin that owns it, not in this one.
 *
 * @param int     $user_id User ID.
 * @param array   $claims  Verified claims from the provider.
 * @param bool    $is_new  Whether the user was created by this sign-in.
 */
do_action( 'blueworx_sso_user_authenticated', $user->ID, $claims, $is_new );
```

- [ ] **Step 4: Run the test and watch it pass**

Run: `php tests/php/users-test.php`
Expected: every case `ok`, exit code 0.

- [ ] **Step 5: Commit**

```bash
git add includes/sso/users.php tests/php/users-test.php blueworx-labs-wordpress.php
git commit -m "Match, link and provision users from verified sign-in claims"
```

---

### Task 7: Settings screen

**Files:**
- Create: `includes/sso/settings.php`
- Modify: `includes/admin-settings.php` (`blueworx_render_feature_detail()`, `blueworx_save_feature_settings()`)
- Modify: `blueworx-labs-wordpress.php` (require)
- Test: `tests/sso.spec.js`

**Interfaces:**
- Produces: `blueworx_sso_render_detail(): void`, `blueworx_sso_save_settings( array $posted ): void`.

Follow the `translate` feature's pattern exactly: `blueworx_render_feature_detail()` gets an `if ( 'sso' === $key ) { blueworx_sso_render_detail(); return; }` branch, and `blueworx_save_feature_settings()` calls `blueworx_sso_save_settings( $_POST )` next to the `blueworx_translate_save_settings()` call.

Fields: enable is the existing toggle; then issuer, client ID, client secret, scope, button label, auto-register, default role, redirect after login. Behind a `<details>` marked "Advanced": the four endpoint overrides, the redirect URI, and the PKCE mode select.

- [ ] **Step 1: Write the failing tests**

```js
test('the settings survive a save and the secret is never rendered', async ({ page }) => {
  await login(page);
  await page.goto(SETTINGS_PATH);
  await page.locator(toggleFor('sso')).setChecked(true);
  await page.fill('#blueworx_sso_issuer', 'https://idp.test');
  await page.fill('#blueworx_sso_client_id', 'test-client');
  await page.fill('#blueworx_sso_client_secret', 'super-secret-value');
  await save(page);

  await page.goto(SETTINGS_PATH);
  await expect(page.locator('#blueworx_sso_issuer')).toHaveValue('https://idp.test');
  await expect(page.locator('#blueworx_sso_client_secret')).toHaveValue('');
  await expect(page.locator('body')).not.toContainText('super-secret-value');
  expect(await page.content()).not.toContain('super-secret-value');
});

test('the callback URL is shown for copying', async ({ page }) => {
  await login(page);
  await page.goto(SETTINGS_PATH);
  await expect(page.locator('.blueworx-sso-callback-url')).toContainText('blueworx_sso=callback');
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `npx playwright test tests/sso.spec.js -g "settings survive"`
Expected: FAIL — no such field.

- [ ] **Step 3: Implement**

Secret handling is the part to get right:

```php
// The secret is write-only. The field renders empty every time, and an empty
// submission means "leave it alone" — so saving any other setting cannot wipe
// it, and nobody with access to this screen can read it back out.
$posted_secret = isset( $posted['blueworx_sso_client_secret'] ) ? trim( (string) wp_unslash( $posted['blueworx_sso_client_secret'] ) ) : '';

if ( '' !== $posted_secret ) {
	update_option( 'blueworx_sso_client_secret', $posted_secret, false );
}
```

Render it as `<input type="password" id="blueworx_sso_client_secret" name="blueworx_sso_client_secret" value="" autocomplete="new-password" />` with a note reading "A secret is saved" or "No secret saved yet". Never echo the stored value. Because it is never rendered and a support session cannot save settings, no extra support-access rule is needed.

Also render, read-only:
- The callback URL, in `<code class="blueworx-sso-callback-url">`.
- Discovery status: "Connected to <issuer>" or the discovery error message.
- The last few log entries with their outcome and time.

Sanitise on save: `esc_url_raw` for the issuer, endpoints and redirect URI; `sanitize_text_field` for client ID, scope and button label; `sanitize_key` against `get_editable_roles()` for the default role; `'1'`/`'0'` for auto-register; one of `auto|on|off` for PKCE. Store the client ID and secret with autoload off.

- [ ] **Step 4: Run the tests and watch them pass**

Run: `npx playwright test tests/sso.spec.js -g "settings survive"` and `-g "callback URL"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/sso/settings.php includes/admin-settings.php blueworx-labs-wordpress.php tests/sso.spec.js
git commit -m "Add the single sign-on settings screen with a write-only secret"
```

---

### Task 8: Login button and shortcode

**Files:**
- Create: `includes/sso/ui.php`
- Modify: `assets/css/login.css` (or the existing login stylesheet — check which file the login screen already enqueues)
- Modify: `blueworx-labs-wordpress.php` (require)
- Test: `tests/sso.spec.js`

**Interfaces:**
- Produces: `blueworx_sso_button_html( array $args = array() ): string`, hooked to `login_form` and registered as the `[blueworx_sso_button]` shortcode.

- [ ] **Step 1: Write the failing tests**

```js
test('the button renders on the login screen with the configured label', async ({ page }) => {
  await page.goto('/wp-login.php');
  const button = page.locator('a.blueworx-sso-button');
  await expect(button).toBeVisible();
  await expect(button).toHaveText('Sign in with Test IdP');
  await expect(button).toHaveAttribute('href', /blueworx_sso=login/);
});

test('no Font Awesome stylesheet is loaded for it', async ({ page }) => {
  await page.goto('/wp-login.php');
  await expect(page.locator('link[href*="font-awesome"]')).toHaveCount(0);
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `npx playwright test tests/sso.spec.js -g "button renders"`
Expected: FAIL — no such element.

- [ ] **Step 3: Implement**

```php
/**
 * Builds the sign-in button.
 *
 * The label is rendered server-side so nothing has to patch it in JavaScript
 * afterwards.
 *
 * @param array $args Optional. 'label' and 'redirect_to'.
 * @return string Empty string when the feature is off.
 */
function blueworx_sso_button_html( $args = array() ) {
	if ( ! blueworx_sso_enabled() || is_user_logged_in() ) {
		return '';
	}

	$label = isset( $args['label'] ) && '' !== $args['label']
		? $args['label']
		: blueworx_sso_option( 'button_label', __( 'Sign in with single sign-on', 'blueworx-labs-wordpress' ) );

	$redirect = isset( $args['redirect_to'] ) ? $args['redirect_to'] : '';

	return sprintf(
		'<a class="blueworx-sso-button" href="%1$s">%2$s<span>%3$s</span></a>',
		esc_url( blueworx_sso_login_url( $redirect ) ),
		blueworx_sso_icon_svg(),
		esc_html( $label )
	);
}
```

`blueworx_sso_icon_svg()` returns a small inline padlock SVG with `aria-hidden="true"` and `focusable="false"`. No icon font, no external stylesheet. Style the button in the plugin's existing login CSS, matching the split-screen login design already in the repo. Register on `login_form` and as the shortcode.

- [ ] **Step 4: Run the tests and watch them pass**

Run: `npx playwright test tests/sso.spec.js -g "button"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/sso/ui.php assets/css blueworx-labs-wordpress.php tests/sso.spec.js
git commit -m "Render the sign-in button server-side with an inline icon"
```

---

### Task 9: Documentation, version and guide

**Files:**
- Modify: `CHANGELOG.md`
- Modify: `readme.txt`
- Modify: `blueworx-labs-wordpress.php` (version header and constant)
- Modify: `package.json` (version)
- Modify: `includes/guides.php` (a short setup guide, following the existing entries)

- [ ] **Step 1: Bump the version**

Minor bump — this is a new feature. Keep the plugin header, the version constant and `package.json` identical.

- [ ] **Step 2: Write the changelog entry**

One line in the user's words, e.g. "Adds single sign-on: people can sign in with an external identity provider such as Google, Microsoft or a membership system."

- [ ] **Step 3: Add the guide**

Cover, in plain words: turn the feature on, paste the issuer, client ID and secret, hand the callback URL to the provider, pick whether new people get an account automatically, and what to do when sign-in fails.

- [ ] **Step 4: Run everything**

```bash
npm run test:php
npx playwright test tests/sso.spec.js
npm run lint
npm run version:check
```

Expected: all pass.

- [ ] **Step 5: Commit and open the PR**

```bash
git add CHANGELOG.md readme.txt blueworx-labs-wordpress.php package.json includes/guides.php
git commit -m "Document single sign-on and bump the version"
```

---

## Follow-up, not in this plan

- **Issue #76** stays blocked on the miniOrange export. Nothing above needs it; only the claim-to-field mapping on the site side does.
- **WSO client plugin**, separate issues in that repo: delete `sso-button-text.js`, and move the referee fields, level assignment and profile-completion redirect onto `blueworx_sso_user_authenticated` and `blueworx_sso_login_redirect`. Switch ASE's competing `redirect_after_login_to_slug` off.
- **Issue #83 cutover** is an operational task, not code: configure on staging against the real provider, verify each path, enable in production with miniOrange still installed as rollback, then remove it, clear its options, retire the legacy trigger URL, and ask the provider to rotate the secret.
