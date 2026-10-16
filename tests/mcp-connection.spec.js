// `test` comes from helpers.js, not '@playwright/test': it carries the fixture
// that opts out of core's wp-admin view transitions.
import { createHash, randomBytes } from 'node:crypto';

import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  baseURL,
  login,
  LOGIN_PATH,
  setFeature,
  saveEnhancements,
} from './helpers.js';

/**
 * Connect Claude: the whole journey an MCP client makes, end to end.
 *
 * Discovery, registration, the consent screen, the token swap, a real tool
 * call that changes content, and cutting the connection off again. The
 * browser plays the administrator; `request` plays Claude, carrying no
 * cookies, so nothing it does can lean on the admin's session.
 */

const SCREEN = '/wp-admin/admin.php?page=blueworx-mcp';
const SETTINGS = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const MCP = `${baseURL}/blueworx-mcp`;
// A client address that resolves nowhere, so the test never leaves the machine.
const REDIRECT = 'https://mcp-client.invalid/callback';

const b64url = (buf) => buf.toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

async function setSwitch(page, on) {
  await page.goto(SCREEN);
  const box = page.locator('[data-testid="bw-mcp-feature"]');
  if ((await box.isChecked()) !== on) {
    await page.locator('label.bw-switch:has([data-testid="bw-mcp-feature"])').click();
    await page.locator('[data-testid="bw-mcp-save"]').click();
    await page.waitForURL(/page=blueworx-mcp/);
  }
}

async function rpc(request, token, method, params = {}) {
  const response = await request.post(MCP, {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json, text/event-stream' },
    data: { jsonrpc: '2.0', id: 1, method, params },
  });
  return { status: response.status(), body: response.status() === 200 ? await response.json() : null };
}

async function callTool(request, token, name, args) {
  const { body } = await rpc(request, token, 'tools/call', { name, arguments: args });
  return body.result;
}

/**
 * Fetches a discovery document, or null where the test server cannot serve it.
 *
 * PHP's built-in server, which the local and CI test sites run on, answers any
 * address under a dot-folder with its own 404 before WordPress sees it. Real
 * hosts pass these addresses through. Only that exact refusal is excused, so a
 * real 404 from the plugin still fails; tests/php/mcp-oauth-test.php checks the
 * documents' content either way.
 */
async function wellKnown(request, name) {
  const response = await request.get(`${baseURL}/.well-known/${name}`);
  if (!response.ok() && (await response.text()).includes('was not found on this server')) {
    return null;
  }
  expect(response.status()).toBe(200);
  return response.json();
}

/**
 * Registers once per run and reuses it. Registration is limited per address,
 * so a spec that registered in every test would lock out its own reruns.
 */
let registered = null;

async function register(request) {
  if (!registered) {
    const server = (await wellKnown(request, 'oauth-authorization-server')) || {
      authorization_endpoint: `${baseURL}/blueworx-oauth/authorize`,
      token_endpoint: `${baseURL}/blueworx-oauth/token`,
      registration_endpoint: `${baseURL}/blueworx-oauth/register`,
      code_challenge_methods_supported: ['S256'],
    };
    const registration = await request.post(server.registration_endpoint, {
      data: { client_name: 'Claude', redirect_uris: [REDIRECT], token_endpoint_auth_method: 'none' },
    });
    expect(registration.status()).toBe(201);
    registered = { server, clientId: (await registration.json()).client_id };
  }
  return registered;
}

function authorizeUrl(server, clientId, state) {
  const verifier = b64url(randomBytes(40));
  const challenge = b64url(createHash('sha256').update(verifier).digest());
  const url = new URL(server.authorization_endpoint);
  Object.entries({
    response_type: 'code',
    client_id: clientId,
    redirect_uri: REDIRECT,
    code_challenge: challenge,
    code_challenge_method: 'S256',
    state,
    resource: MCP,
  }).forEach(([key, value]) => url.searchParams.set(key, value));
  return { url: url.toString(), verifier, challenge };
}

/**
 * The administrator approves on this site, and Claude swaps the code. Claude's
 * own callback is not reachable from the test, so the redirect is caught
 * rather than followed.
 */
async function approve(page, request, server, clientId) {
  const { url, verifier } = authorizeUrl(server, clientId, 'test-state');

  let callback = '';
  const watch = (req) => {
    if (req.url().startsWith(REDIRECT)) {
      callback = req.url();
    }
  };
  page.on('request', watch);

  await page.goto(url);
  await expect(page.getByRole('heading', { name: /Connect Claude to this site/ })).toBeVisible();
  await page.locator('[data-testid="bw-mcp-allow"]').click();
  await expect.poll(() => callback).toContain('code=');
  page.off('request', watch);

  const returned = new URL(callback);
  expect(returned.searchParams.get('state')).toBe('test-state');

  const tokenResponse = await request.post(server.token_endpoint, {
    form: {
      grant_type: 'authorization_code',
      code: returned.searchParams.get('code'),
      redirect_uri: REDIRECT,
      client_id: clientId,
      code_verifier: verifier,
    },
  });
  expect(tokenResponse.status()).toBe(200);
  return tokenResponse.json();
}

test.describe('Connect Claude', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('is closed while switched off', async ({ page, request }) => {
    await login(page);
    await setSwitch(page, false);

    expect((await request.get(`${baseURL}/.well-known/oauth-protected-resource`)).status()).toBe(404);
    expect((await request.post(MCP, { data: {} })).status()).toBe(404);
  });

  test('an administrator connects Claude, and Claude edits content', async ({ page, request }) => {
    await login(page);
    await setSwitch(page, true);

    try {
      // The address on the screen is the one Claude is given.
      await expect(page.locator('[data-testid="bw-mcp-url"]')).toHaveValue(MCP);

      // Claude's first call has no token and is told where to sign in.
      const bare = await request.post(MCP, { data: { jsonrpc: '2.0', id: 1, method: 'initialize' } });
      expect(bare.status()).toBe(401);
      expect(bare.headers()['www-authenticate']).toContain('resource_metadata=');

      const resource = await wellKnown(request, 'oauth-protected-resource');
      if (resource) {
        expect(resource.resource).toBe(MCP);
      }

      const { server, clientId } = await register(request);
      expect(server.code_challenge_methods_supported).toEqual(['S256']);

      // An unknown client is told so in a way strict OAuth libraries accept.
      const stranger = await request.post(server.token_endpoint, {
        form: { grant_type: 'authorization_code', client_id: 'bwx_nobody', code: 'x' },
      });
      expect(stranger.status()).toBe(401);
      expect(stranger.headers()['www-authenticate']).toContain('Basic');

      const { access_token: token } = await approve(page, request, server, clientId);

      const init = await rpc(request, token, 'initialize', { protocolVersion: '2025-06-18', capabilities: {} });
      expect(init.body.result.serverInfo.name).toBe('blueworx-wordpress');

      const tools = await rpc(request, token, 'tools/list');
      const names = tools.body.result.tools.map((tool) => tool.name);
      expect(names).toEqual(expect.arrayContaining(['list_content', 'create_content', 'update_content', 'trash_content']));
      // Content, not files: nothing that uploads or deletes for good.
      expect(names.join(' ')).not.toMatch(/upload|media|delete|plugin|user/);

      const created = await callTool(request, token, 'create_content', { type: 'page', title: 'MCP test page' });
      expect(created.isError).toBeFalsy();
      expect(created.structuredContent.status).toBe('draft');
      const id = created.structuredContent.id;

      const updated = await callTool(request, token, 'update_content', { type: 'page', id, title: 'MCP test page, edited' });
      expect(updated.structuredContent.title).toBe('MCP test page, edited');

      const trashed = await callTool(request, token, 'trash_content', { type: 'page', id });
      expect(trashed.structuredContent.status).toBe('trash');

      // Media is files, and stays out of reach.
      const media = await callTool(request, token, 'list_content', { type: 'attachment' });
      expect(media.isError).toBe(true);

      // The screen shows the connection and what it changed, and can cut it off.
      await page.goto(SCREEN);
      await expect(page.locator('[data-testid="bw-mcp-grants"]')).toContainText('Claude');
      await expect(page.locator('[data-testid="bw-mcp-log"]')).toContainText('MCP test page, edited');

      await page.locator('[data-testid="bw-mcp-revoke-all"]').click();
      await page.waitForURL(/page=blueworx-mcp/);

      expect((await rpc(request, token, 'ping')).status).toBe(401);
    } finally {
      await setSwitch(page, false);
    }
  });

  test('switching off from Enhancements disconnects everyone too', async ({ page, request }) => {
    await login(page);
    await setSwitch(page, true);

    try {
      const { server, clientId } = await register(request);
      const { access_token: token } = await approve(page, request, server, clientId);
      expect((await rpc(request, token, 'ping')).status).toBe(200);

      await page.goto(SETTINGS);
      await setFeature(page, 'mcp', false);
      await saveEnhancements(page);

      // Back on again: the old connection must not come back with it.
      await setSwitch(page, true);
      expect((await rpc(request, token, 'ping')).status).toBe(401);
      await expect(page.getByText('Nothing connected yet')).toBeVisible();
    } finally {
      await setSwitch(page, false);
    }
  });

  test('a signed-out administrator signs in and lands back on the consent screen', async ({ page, request, browser }) => {
    await login(page);
    await setSwitch(page, true);

    // A browser with nobody signed in, as when Claude opens the site.
    const context = await browser.newContext();

    try {
      const { server, clientId } = await register(request);
      const { url, challenge } = authorizeUrl(server, clientId, 'signed-out-state');

      const fresh = await context.newPage();
      await fresh.goto(url);

      // The custom login screen, which must carry the whole consent address:
      // it used to keep only what came before the address's first "&".
      expect(fresh.url()).toContain(LOGIN_PATH);
      await fresh.fill('#user_login', ADMIN_USER);
      await fresh.fill('#user_pass', ADMIN_PASS);
      await fresh.click('#wp-submit');

      await expect(fresh.getByRole('heading', { name: /Connect Claude to this site/ })).toBeVisible();
      await expect(fresh.locator('input[name="state"]')).toHaveValue('signed-out-state');
      await expect(fresh.locator('input[name="code_challenge"]')).toHaveValue(challenge);
      await expect(fresh.locator('input[name="resource"]')).toHaveValue(MCP);
    } finally {
      await context.close();
      await setSwitch(page, false);
    }
  });
});
