import test from 'node:test';
import assert from 'node:assert/strict';
import { applyRenewedAccessToken } from '../utils/accessToken.ts';

test('password-change token renewal writes only a non-empty replacement', () => {
  const writes: string[] = [];
  const storage = {
    setItem(_key: string, value: string) {
      writes.push(value);
    },
  };

  assert.equal(applyRenewedAccessToken(storage, 'msa_connect_token', 'replacement-credential'), true);
  assert.deepEqual(writes, ['replacement-credential']);

  assert.equal(applyRenewedAccessToken(storage, 'msa_connect_token', ''), false);
  assert.equal(applyRenewedAccessToken(storage, 'msa_connect_token', null), false);
  assert.equal(applyRenewedAccessToken(storage, 'msa_connect_token', undefined), false);
  assert.deepEqual(writes, ['replacement-credential']);
});
