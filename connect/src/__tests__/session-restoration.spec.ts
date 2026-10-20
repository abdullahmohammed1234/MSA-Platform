import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { AUTH_TOKEN_KEY } from '../services/api.ts';

test('Connect Independent Bearer-Token Authentication & Session Restoration', async (t) => {
  await t.test('AUTH_TOKEN_KEY constant is isolated and correctly named', () => {
    assert.equal(AUTH_TOKEN_KEY, 'msa_connect_token');
  });

  await t.test('apiClient instance is configured for stateless Bearer auth without session cookies', () => {
    const apiSource = readFileSync(new URL('../services/api.ts', import.meta.url), 'utf8');

    // Verify session cookies and CSRF cookies are disabled for stateless API operations
    assert.match(apiSource, /withCredentials:\s*false/);
    assert.match(apiSource, /withXSRFToken:\s*false/);
  });

  await t.test('api.ts interceptor attaches Bearer token to all API requests without stripping /auth/me', () => {
    const apiSource = readFileSync(new URL('../services/api.ts', import.meta.url), 'utf8');

    // Must NOT strip Authorization on /auth/me
    assert.ok(
      !apiSource.includes("config.url?.includes('/auth/me')"),
      'API interceptor must not strip Authorization header from /auth/me'
    );

    // Must attach Bearer token to allowed API destination
    assert.match(
      apiSource,
      /config\.headers\.Authorization\s*=\s*`Bearer \$\{token\}`/,
      'API interceptor must attach Bearer token'
    );
  });

  await t.test('auth store setSession persists and clears msa_connect_token in localStorage', () => {
    const storeSource = readFileSync(new URL('../stores/auth.ts', import.meta.url), 'utf8');

    assert.match(
      storeSource,
      /localStorage\.setItem\(AUTH_TOKEN_KEY,\s*newToken\)/,
      'auth store setSession must persist Bearer token under AUTH_TOKEN_KEY'
    );
    assert.match(
      storeSource,
      /localStorage\.removeItem\(AUTH_TOKEN_KEY\)/,
      'auth store setSession must remove Bearer token when cleared'
    );
  });

  await t.test('restoreSession restores from storedToken and purges on invalid or expired token', () => {
    const storeSource = readFileSync(new URL('../stores/auth.ts', import.meta.url), 'utf8');

    // Checks storedToken before attempting network probe
    assert.match(
      storeSource,
      /const\s+storedToken\s*=\s*getStoredToken\(\)/,
      'restoreSession must check stored token on page load'
    );

    // If no token exists, immediately returns false without unnecessary requests
    assert.match(
      storeSource,
      /if\s*\(!storedToken\)\s*\{\s*isInitialized\.value\s*=\s*true;\s*user\.value\s*=\s*null;\s*token\.value\s*=\s*null;\s*return\s*false;\s*\}/,
      'restoreSession must return false when no token exists'
    );

    // Purges on failed profile fetch
    assert.match(
      storeSource,
      /catch\s*\{\s*\/\/[^\n]*\s*purgeAllUserData\(\);/,
      'restoreSession must invoke purgeAllUserData upon rejected /auth/me call'
    );
  });
});
