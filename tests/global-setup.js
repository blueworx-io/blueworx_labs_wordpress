/**
 * Fails a local run early, and in English, when the harness is not running.
 *
 * The suite now defaults to the local harness, so the commonest way to get
 * nothing useful is to run it before starting one. Without this the first spec
 * dies on ECONNREFUSED partway through a navigation, which reads like a bug in
 * the test rather than a machine that has nothing listening on the port.
 *
 * Scoped to localhost on purpose. A staging or CI URL that does not answer is a
 * real failure and is left to report itself as one.
 */

import { baseURL, isHarness } from './test-target.js';

export default async function globalSetup() {
  if (!isHarness) {
    return;
  }

  try {
    // Any answer at all is enough — a redirect or even a 500 proves something is
    // listening. Only a connection failure means "no harness".
    await fetch(baseURL, { method: 'HEAD' });
  } catch {
    throw new Error(
      [
        `No WordPress is answering at ${baseURL}.`,
        '',
        'The suite defaults to the local harness, which serves this working tree.',
        'Start it with:',
        '',
        '  node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up --plugin .',
        '',
        'To test a deployed site instead, set PLAYWRIGHT_BASE_URL (plus WP_ADMIN_USER,',
        'WP_ADMIN_PASS and WP_LOGIN_PATH) in .env or on the command line.',
      ].join('\n')
    );
  }
}
