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
 *
 * Once the harness answers, the store-page fixtures are seeded (see below).
 */

import { spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { baseURL, isHarness } from './test-target.js';

const REPO = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const WP_ROOT = join(REPO, '.wp-test', 'wp');
const WP_LOAD = join(WP_ROOT, 'wp-load.php');
const MU_PLUGINS = join(WP_ROOT, 'wp-content', 'mu-plugins');
const TEST_HOOKS = join(MU_PLUGINS, 'blueworx-store-test-hooks.php');

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

  if (!existsSync(WP_LOAD)) {
    return;
  }

  seedStoreFixtures();
}

/**
 * Seeds the pages the store specs drive, by running PHP against the harness.
 *
 * Stand-ins for the pages SureCart seeds. CI has no SureCart; the stored id IS
 * the contract, so a page the option names is the honest fixture. Also a member
 * who is not an administrator, for the dashboard specs, and the test-hooks
 * mu-plugin those specs rely on.
 */
function seedStoreFixtures() {
  const php = join(tmpdir(), 'blueworx-store-global-setup.php');
  writeFileSync(
    php,
    `<?php
require_once ${JSON.stringify(WP_LOAD)};

function bw_fixture_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing instanceof WP_Post ) {
		wp_update_post( array( 'ID' => $existing->ID, 'post_status' => 'publish' ) );
		return $existing->ID;
	}
	return (int) wp_insert_post( array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $content,
	) );
}

// Stand-ins for the pages SureCart seeds. CI has no SureCart; the stored id IS
// the contract, so a page the option names is the honest fixture. The option
// names are SureCart's own (surecart_<page>_page_id, hyphen and all).
update_option( 'surecart_checkout_page_id', bw_fixture_page( 'checkout-fixture', 'Checkout fixture', '<p id="shop-content">SHOP CONTENT</p>' ) );
update_option( 'surecart_order-confirmation_page_id', bw_fixture_page( 'thanks-fixture', 'Thank you fixture', '<p id="shop-content">THANKS CONTENT</p>' ) );
update_option( 'surecart_dashboard_page_id', bw_fixture_page( 'dashboard-fixture', 'Dashboard fixture', '<p id="foreign-content">FOREIGN</p>' ) );
bw_fixture_page( 'claimed-fixture', 'Claimed dashboard fixture', '<p id="claimed-content">CLAIMED</p>' );

// A member who is not an administrator, for the dashboard specs.
if ( ! get_user_by( 'login', 'member' ) ) {
	wp_insert_user( array( 'user_login' => 'member', 'user_pass' => 'wptest-member-pw', 'user_email' => 'member@example.com', 'display_name' => 'Pat Member', 'role' => 'subscriber' ) );
}

echo (int) get_option( 'surecart_checkout_page_id' ) > 0 ? 'seeded' : 'failed';
`
  );
  const res = spawnSync('php', [php], { encoding: 'utf8' });
  rmSync(php, { force: true });

  if (res.status !== 0 || res.stdout.trim() !== 'seeded') {
    // Fail loudly. Continuing would produce a wall of store-spec failures whose
    // cause is this, not the plugin.
    throw new Error(
      `global-setup: could not seed the store fixture pages (exit ${res.status}). ` +
        `stdout=${res.stdout?.trim()} stderr=${res.stderr?.trim()}`
    );
  }

  // The hooks the dashboard specs register from inside WordPress. Written on
  // every run so a harness rebuilt from scratch still has it.
  mkdirSync(MU_PLUGINS, { recursive: true });
  writeFileSync(TEST_HOOKS, '<?php\n');

  console.log('global-setup: store fixture pages seeded.');
}
