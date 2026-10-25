/**
 * Runs the PHP check scripts as part of the normal suite.
 *
 * The scripts in tests/php cover the parts of single sign-on that must be right
 * before anything else matters — token forgery, replay, and who gets linked to
 * which account. They need no WordPress and no browser, but the shared CI
 * workflow has no step to call them, so without this they would never run on a
 * pull request and would quietly rot.
 *
 * They are skipped rather than failed where PHP is absent, so a machine without
 * it can still run the browser suite.
 */

import { execFileSync } from 'node:child_process';
import { test, expect } from './helpers.js';

const SCRIPTS = [
  'discovery-test.php',
  'jwt-test.php',
  'login-redirect-test.php',
  'users-test.php',
  'sso-flow-test.php',
  'rest-users-test.php',
  'guides-access-test.php',
  'view-as-access-test.php',
  'display-names-test.php',
  'auto-updates-test.php',
  'app-screens-test.php',
  'readonly-access-test.php',
  'external-access-test.php',
  'disable-comments-test.php',
  'mcp-oauth-test.php',
  'mcp-forge-test.php',
  'tracking-codes-test.php',
  'topbar-sites-test.php',
  'login-security-multisite-test.php',
  'multisite-accounts-test.php',
  'uninstall-with-theme-test.php',
  'admin-menu-badges-test.php',
  'robots-txt-test.php',
  'cache-purge-test.php',
  'network-menu-icons-test.php',
  'application-passwords-test.php',
  'feature-defaults-test.php',
];

let phpAvailable = true;

try {
  execFileSync('php', ['-v'], { stdio: 'ignore' });
} catch {
  phpAvailable = false;
}

test.describe('PHP checks', () => {
  test.skip(!phpAvailable, 'PHP is not on this machine.');

  for (const script of SCRIPTS) {
    test(script, () => {
      let output = '';
      let failed = false;

      try {
        output = execFileSync('php', [`tests/php/${script}`], { encoding: 'utf8' });
      } catch (error) {
        failed = true;
        output = `${error.stdout || ''}${error.stderr || ''}`;
      }

      // The output names every check, so a failure reads as the failing rule
      // rather than as a bare exit code.
      expect(output, output).toContain('All checks passed.');
      expect(failed, output).toBe(false);
    });
  }
});
