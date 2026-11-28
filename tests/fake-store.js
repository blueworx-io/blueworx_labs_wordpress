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

/** The header a test request sends to switch the stand-in on. */
export const FAKE_STORE_HEADER = 'X-Bw-Fake-Store';

/** How long a stand-in lives, in seconds. */
export const FAKE_STORE_LIFETIME = 600;

/**
 * Takes the stand-in away. Safe to call any number of times.
 */
export function removeFakeStore() {
  fs.rmSync(FAKE_STORE_FILE, { force: true });
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
  return [
    '<?php',
    `if ( time() > ${Math.floor(expires)} || ! isset( $_SERVER['HTTP_X_BW_FAKE_STORE'] ) ) { return; }`,
    'class WooCommerce {}',
    "add_filter( 'pre_option_woocommerce_enable_guest_checkout', function () { return 'no'; } );",
    "add_filter( 'pre_option_woocommerce_enable_signup_and_login_from_checkout', function () { return 'no'; } );",
    "add_action( 'init', function () { header( 'X-Test-Fake-Store: 1' ); do_action( 'before_woocommerce_init' ); }, 0 );",
    "add_action( 'init', function () {",
    "  if ( isset( $_GET['wc-ajax'] ) && 'get_refreshed_fragments' === $_GET['wc-ajax'] ) {",
    "    header( 'X-Test-Cart-Made: 1' );",
    "    wp_send_json( array( 'fragments' => array( 'a' => 'b' ), 'cart_hash' => 'made' ) );",
    '  }',
    '}, 5 );',
    '',
  ].join('\n');
}

/**
 * Writes the stand-in into the local test site's folder.
 *
 * @param {number} expires When it stops working, in seconds since 1970.
 * @return {boolean} False when the site's folder is not on this machine.
 */
export function writeFakeStore(expires = Date.now() / 1000 + FAKE_STORE_LIFETIME) {
  if (!fs.existsSync(path.join(MU_DIR, '..'))) {
    return false;
  }

  fs.mkdirSync(MU_DIR, { recursive: true });
  fs.writeFileSync(FAKE_STORE_FILE, fakeStoreSource(expires));
  return true;
}
