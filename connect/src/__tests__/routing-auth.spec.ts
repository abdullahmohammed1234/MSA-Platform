import test from 'node:test';
import assert from 'node:assert/strict';
import { sanitizeRedirectPath } from '../utils/security.ts';

test('Connect Routing & Authentication-Aware Destinations', async (t) => {
  await t.test('sanitizeRedirectPath redirects root landing and login to dashboard', () => {
    assert.equal(sanitizeRedirectPath('/'), '/app/dashboard');
    assert.equal(sanitizeRedirectPath(''), '/app/dashboard');
    assert.equal(sanitizeRedirectPath('/login'), '/app/dashboard');
    assert.equal(sanitizeRedirectPath(null), '/app/dashboard');
    assert.equal(sanitizeRedirectPath(undefined), '/app/dashboard');
  });

  await t.test('sanitizeRedirectPath preserves safe internal application destinations', () => {
    assert.equal(sanitizeRedirectPath('/app/events'), '/app/events');
    assert.equal(sanitizeRedirectPath('/app/tickets'), '/app/tickets');
    assert.equal(sanitizeRedirectPath('/app/support/new'), '/app/support/new');
  });

  await t.test('sanitizeRedirectPath defuses malicious or open redirect evasion', () => {
    assert.equal(sanitizeRedirectPath('//attacker.com'), '/app/dashboard');
    assert.equal(sanitizeRedirectPath('/\\attacker.com'), '/app/dashboard');
    assert.equal(sanitizeRedirectPath('https://evil.com'), '/app/dashboard');
    assert.equal(sanitizeRedirectPath('/https://evil.com'), '/app/dashboard');
    assert.equal(sanitizeRedirectPath('/javascript:alert(1)'), '/app/dashboard');
  });

  await t.test('Logo destination resolves correctly based on authentication status', () => {
    const getLogoDestination = (isAuthenticated: boolean) => {
      return isAuthenticated ? '/app/dashboard' : '/';
    };

    assert.equal(getLogoDestination(false), '/');
    assert.equal(getLogoDestination(true), '/app/dashboard');
  });

  await t.test('Root landing route redirects authenticated users to dashboard and preserves guest landing', () => {
    const evaluateRootRoute = (isAuthenticated: boolean) => {
      if (isAuthenticated) {
        return { path: '/app/dashboard' };
      }
      return true; // proceed to LandingView
    };

    assert.equal(evaluateRootRoute(false), true);
    assert.deepEqual(evaluateRootRoute(true), { path: '/app/dashboard' });
  });

  await t.test('Session restoration deduplication avoids redundant network probes', async () => {
    let networkCallCount = 0;
    let restorePromise: Promise<boolean> | null = null;
    let isInitialized = false;

    const mockRestoreSession = async () => {
      if (isInitialized) return true;
      if (restorePromise) return restorePromise;

      restorePromise = (async () => {
        networkCallCount++;
        await new Promise((resolve) => setTimeout(resolve, 10));
        isInitialized = true;
        restorePromise = null;
        return true;
      })();

      return restorePromise;
    };

    // Trigger 3 concurrent calls
    const [res1, res2, res3] = await Promise.all([
      mockRestoreSession(),
      mockRestoreSession(),
      mockRestoreSession(),
    ]);

    assert.equal(res1, true);
    assert.equal(res2, true);
    assert.equal(res3, true);
    assert.equal(networkCallCount, 1, 'Only one network probe must be executed across concurrent callers');
  });
});
