/**
 * Application passwords sign REST requests in, for admins only.
 *
 * A request signed with an application password used to crash the site (#211):
 * the plugin's filter asked who was signed in while WordPress was still working
 * that out, and the two asked each other until PHP ran out of stack. These go
 * through WordPress for real, so a crash is a 500 rather than a passing stub.
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS } from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Sets up or removes the accounts and passwords. See tests/fixtures/app-password.php.
 *
 * @param {'create'|'delete'} command Fixture command.
 * @return {Record<string, {login: string, password: string}>} Credentials by role, for "create".
 */
function appPasswords(command) {
  const fixture = path.join(__dirname, 'fixtures', 'app-password.php');
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  const out = execFileSync('php', [fixture, wpLoad, command], { encoding: 'utf8' }).trim();
  const accounts = {};

  for (const line of out.split('\n').filter(Boolean)) {
    const [role, login, ...rest] = line.trim().split(' ');
    accounts[role] = { login, password: rest.join(' ') };
  }

  return accounts;
}

/**
 * The Authorization header for one account.
 *
 * @param {{login: string, password: string}} account Credentials.
 * @return {Record<string, string>} Request headers.
 */
function basicAuth(account) {
  const token = Buffer.from(`${account.login}:${account.password}`).toString('base64');
  return { Authorization: `Basic ${token}` };
}

test.describe('Application passwords', () => {
  test.skip(isPlaceholder || !ADMIN_USER || !ADMIN_PASS, 'No test site configured.');
  test.describe.configure({ mode: 'serial' });

  let accounts = {};

  test.beforeAll(() => {
    accounts = appPasswords('create');
  });

  test.afterAll(() => {
    appPasswords('delete');
  });

  test('an admin signed in with one gets a normal answer, not a crash', async ({ request }) => {
    const response = await request.get('/wp-json/wp/v2/settings', { headers: basicAuth(accounts.admin) });

    expect(response.status()).toBe(200);
    expect(await response.json()).toHaveProperty('title');
  });

  test('a non-admin cannot sign in with one', async ({ request }) => {
    const response = await request.get('/wp-json/wp/v2/settings', { headers: basicAuth(accounts.editor) });

    // 401 means the password was refused. A signed-in editor would get 403.
    expect(response.status()).toBe(401);
  });
});
