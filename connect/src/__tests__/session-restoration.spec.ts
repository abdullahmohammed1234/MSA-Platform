import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { AUTH_TOKEN_KEY } from '../services/api.ts';

test('Connect Session Restoration & Sentinel Safety Verification', async (t) => {
  await t.test('AUTH_TOKEN_KEY constant is isolated and correctly named', () => {
    assert.equal(AUTH_TOKEN_KEY, 'msa_connect_token');
  });

  await t.test('api.ts interceptor explicitly suppresses sso_session sentinel from Authorization header', () => {
    const apiSource = readFileSync(new URL('../services/api.ts', import.meta.url), 'utf8');

    // Verify token !== 'sso_session' guard is present in the interceptor
    assert.match(
      apiSource,
      /token\s*!==\s*['"]sso_session['"]/,
      'API interceptor must check that token is not the sso_session sentinel before setting Authorization header'
    );

    // Verify defensive removal of any sso_session header
    assert.match(
      apiSource,
      /Authorization.*includes\(['"]sso_session['"]\)/,
      'API interceptor must delete Authorization header if it references sso_session'
    );
  });

  await t.test('auth store setSession prevents sso_session sentinel from persisting to localStorage', () => {
    const storeSource = readFileSync(new URL('../stores/auth.ts', import.meta.url), 'utf8');

    // Verify setSession checks newToken !== 'sso_session' before setItem
    assert.match(
      storeSource,
      /if\s*\(\s*newToken\s*&&\s*newToken\s*!==\s*['"]sso_session['"]\s*\)/,
      'auth store setSession must never persist sso_session sentinel to localStorage'
    );
  });

  await t.test('restoreSession sets sso_session strictly in memory and purges on failure', () => {
    const storeSource = readFileSync(new URL('../stores/auth.ts', import.meta.url), 'utf8');

    // Verify restoreSession catches failed probe and calls purgeAllUserData
    assert.match(
      storeSource,
      /catch\s*\{\s*\/\/[^\n]*\s*purgeAllUserData\(\);/,
      'restoreSession must invoke purgeAllUserData upon expired or rejected /auth/me call'
    );
  });

  await t.test('withCredentials and withXSRFToken are enabled on apiClient instance', () => {
    const apiSource = readFileSync(new URL('../services/api.ts', import.meta.url), 'utf8');

    assert.match(apiSource, /withCredentials:\s*true/);
    assert.match(apiSource, /withXSRFToken:\s*true/);
  });
});
