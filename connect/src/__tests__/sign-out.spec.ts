import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { signOut } from '../utils/signOut.ts';

test('confirmed sign-out revokes on the server and clears local state', async () => {
  const calls: string[] = [];

  const confirmed = await signOut(
    async () => { calls.push('revoke'); },
    () => { calls.push('clear'); },
  );

  assert.equal(confirmed, true);
  assert.deepEqual(calls, ['revoke', 'clear']);
});

test('failed server call still clears local state and does not claim revocation', async () => {
  let cleared = false;

  const confirmed = await signOut(
    async () => { throw new Error('Network Error'); },
    () => { cleared = true; },
  );

  assert.equal(confirmed, false);
  assert.equal(cleared, true);
});

test('auth store logout uses signOut and purges every member store', () => {
  const source = readFileSync(new URL('../stores/auth.ts', import.meta.url), 'utf8');

  assert.match(source, /signOut\(\(\) => authService\.logout\(\), purgeAllUserData\)/);
  assert.match(source, /function purgeAllUserData\(\)\s*\{\s*setSession\(null, null\);/);
  assert.match(source, /localStorage\.removeItem\(AUTH_TOKEN_KEY\)/);
});
