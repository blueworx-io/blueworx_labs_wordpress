/**
 * The stand-in WooCommerce the speed tests use on the local test site.
 *
 * It is a must-use file, so a crashed run could leave it on the site. Two things
 * keep it harmless if that ever happens: it does nothing unless a request sends
 * the test header, and it does nothing after its expiry time. The suite also
 * removes any left-over copy before it starts.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export const MU_DIR = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-content', 'mu-plugins');
export const FAKE_STORE_FILE = path.join(MU_DIR, 'blueworx-test-fake-woocommerce.php');
export const FAKE_STORE_PROBE_FILE = path.join(MU_DIR, 'blueworx-test-fake-woocommerce-probe.php');

/** The header a test request sends to ask the probe file to report. */
export const FAKE_PROBE_HEADER = 'X-Bw-Fake-Probe';

/** The session cookie name the stand-in gives itself as WooCommerce starts. */
export const RENAMED_SESSION_COOKIE = 'mp_store_basket';

/** The header a test request sends to switch the stand-in on. */
export const FAKE_STORE_HEADER = 'X-Bw-Fake-Store';

/** How long a stand-in lives, in seconds. */
export const FAKE_STORE_LIFETIME = 600;

/**
 * Takes the stand-in away. Safe to call any number of times.
 */
export function removeFakeStore() {
  fs.rmSync(FAKE_STORE_FILE, { force: true });
  fs.rmSync(FAKE_STORE_PROBE_FILE, { force: true });
}

/**
 * The stand-in's PHP: a WooCommerce class, sign-in-only checkout, the
 * before_woocommerce_init action fired on init (priority 0) as WooCommerce does,
 * and a cart that is "made" on init (priority 5) and says so in a header.
 *
 * @param {number} expires When it stops working, in seconds since 1970.
 * @return {string} The file's contents.
 */
export function fakeStoreSource(expires) {
  // Everything, the class included, sits inside the guard: PHP declares a class
  // at the top of a file even after an early return, so a guard that only returns
  // would still make the site look like it has WooCommerce.
  return [
    '<?php',
    `if ( time() <= ${Math.floor(expires)} && isset( $_SERVER['HTTP_X_BW_FAKE_STORE'] ) ) {`,
    '  class WooCommerce {}',
    "  add_filter( 'pre_option_woocommerce_enable_guest_checkout', function () { return 'no'; } );",
    "  add_filter( 'pre_option_woocommerce_enable_signup_and_login_from_checkout', function () { return 'no'; } );",
    "  add_action( 'init', function () { header( 'X-Test-Fake-Store: 1' ); do_action( 'before_woocommerce_init' ); }, 0 );",
    '  // A store that renames its session cookie as WooCommerce starts (as a plugin does).',
    `  add_action( 'before_woocommerce_init', function () { add_filter( 'woocommerce_cookie', function () { return '${RENAMED_SESSION_COOKIE}'; } ); } );`,
    "  add_action( 'init', function () {",
    "    if ( isset( $_GET['wc-ajax'] ) && 'get_refreshed_fragments' === $_GET['wc-ajax'] ) {",
    "      header( 'X-Test-Cart-Made: 1' );",
    "      wp_send_json( array( 'fragments' => array( 'a' => 'b' ), 'cart_hash' => 'made' ) );",
    '    }',
    '  }, 5 );',
    '}',
    '',
  ].join('\n');
}

/**
 * A second small file that reports, for every request, whether a class called
 * WooCommerce exists. It is how a test sees that a stand-in is really inert. It
 * sorts after the stand-in, so the stand-in has loaded (or not) by then.
 *
 * @param {number} expires When it stops working, in seconds since 1970.
 * @return {string} The file's contents.
 */
export function fakeStoreProbeSource(expires) {
  // Like the stand-in, it speaks only to a request that asks, and only until it expires.
  return [
    '<?php',
    `if ( time() <= ${Math.floor(expires)} && isset( $_SERVER['HTTP_X_BW_FAKE_PROBE'] ) ) {`,
    "  add_action( 'init', function () { header( 'X-Test-Woo-Class: ' . ( class_exists( 'WooCommerce', false ) ? '1' : '0' ) ); }, -1 );",
    '}',
    '',
  ].join('\n');
}

/**
 * Writes the stand-in into the local test site's folder.
 *
 * @param {number} expires When it stops working, in seconds since 1970.
 * @param {boolean|number} withProbe Also write the file that reports whether a WooCommerce class exists: true for the usual lifetime, or its own expiry time.
 * @return {boolean} False when the site's folder is not on this machine.
 */
export function writeFakeStore(expires = Date.now() / 1000 + FAKE_STORE_LIFETIME, withProbe = false) {
  if (!fs.existsSync(path.join(MU_DIR, '..'))) {
    return false;
  }

  fs.mkdirSync(MU_DIR, { recursive: true });
  fs.writeFileSync(FAKE_STORE_FILE, fakeStoreSource(expires));

  if (withProbe) {
    const probeExpires = withProbe === true ? Date.now() / 1000 + FAKE_STORE_LIFETIME : withProbe;
    fs.writeFileSync(FAKE_STORE_PROBE_FILE, fakeStoreProbeSource(probeExpires));
  }

  return true;
}
