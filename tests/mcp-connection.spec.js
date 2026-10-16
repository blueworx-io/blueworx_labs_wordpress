// `test` comes from helpers.js, not '@playwright/test': it carries the fixture
// that opts out of core's wp-admin view transitions.
import { createHash, randomBytes } from 'node:crypto';

import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, baseURL, login } from './helpers.js';

/**
 * Connect Claude: the whole journey an MCP client makes, end to end.
 *
 * Discovery, registration, the consent screen, the token swap, a real tool
 * call that changes content, and cutting the connection off again. The
 * browser plays the administrator; `request` plays Claude, carrying no
 * cookies, so nothing it does can lean on the admin's session.
 */

const SCREEN = '/wp-admin/admin.php?page=blueworx-mcp';
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

      const resource = await (await request.get(`${baseURL}/.well-known/oauth-protected-resource`)).json();
      expect(resource.resource).toBe(MCP);

      const server = await (await request.get(`${baseURL}/.well-known/oauth-authorization-server`)).json();
      expect(server.code_challenge_methods_supported).toEqual(['S256']);

      const registration = await request.post(server.registration_endpoint, {
        data: { client_name: 'Claude', redirect_uris: [REDIRECT], token_endpoint_auth_method: 'none' },
      });
      expect(registration.status()).toBe(201);
      const { client_id: clientId } = await registration.json();

      // The administrator approves on this site. Claude's own callback is not
      // reachable from the test, so the redirect is caught rather than followed.
      const verifier = b64url(randomBytes(40));
      const challenge = b64url(createHash('sha256').update(verifier).digest());
      const authorize = new URL(server.authorization_endpoint);
      Object.entries({
        response_type: 'code',
        client_id: clientId,
        redirect_uri: REDIRECT,
        code_challenge: challenge,
        code_challenge_method: 'S256',
        state: 'test-state',
        resource: MCP,
      }).forEach(([key, value]) => authorize.searchParams.set(key, value));

      let callback = '';
      page.on('request', (req) => {
        if (req.url().startsWith(REDIRECT)) {
          callback = req.url();
        }
      });

      await page.goto(authorize.toString());
      await expect(page.getByRole('heading', { name: /Connect Claude to this site/ })).toBeVisible();
      await page.locator('[data-testid="bw-mcp-allow"]').click();
      await expect.poll(() => callback).toContain('code=');

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
      const { access_token: token } = await tokenResponse.json();

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
});
