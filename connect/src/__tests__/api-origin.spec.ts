import test from 'node:test';
import assert from 'node:assert/strict';
import { validateApiUrl } from '../config/api.ts';
import { isAllowedApiDestination } from '../utils/security.ts';

test('API Base URL Validation — Security & Configuration Constraints', async (t) => {
  await t.test('accepts valid internal relative paths', () => {
    assert.doesNotThrow(() => validateApiUrl('/api/v1', false));
    assert.doesNotThrow(() => validateApiUrl('/api/v1', true));
  });

  await t.test('accepts valid HTTPS production and staging origins', () => {
    assert.doesNotThrow(() => validateApiUrl('https://api.sfumsa.ca/api/v1', true));
    assert.doesNotThrow(() => validateApiUrl('https://staging-api.sfumsa.ca/api/v1', false));
  });

  await t.test('strictly rejects unencrypted HTTP URLs in production mode', () => {
    assert.throws(
      () => validateApiUrl('http://api.sfumsa.ca/api/v1', true),
      /Production API base URL must use HTTPS/,
    );
  });

  await t.test('permits HTTP for localhost development/testing', () => {
    assert.doesNotThrow(() => validateApiUrl('http://localhost:8000/api/v1', false));
    assert.doesNotThrow(() => validateApiUrl('http://127.0.0.1:8000/api/v1', false));
  });

  await t.test('strictly rejects dangerous and non-HTTP protocols', () => {
    assert.throws(() => validateApiUrl('javascript:alert(1)', false), /Insecure API URL protocol rejected: javascript:/);
    assert.throws(() => validateApiUrl('file:///etc/passwd', false), /Insecure API URL protocol rejected: file:/);
    assert.throws(() => validateApiUrl('data:text/html,bad', false), /Insecure API URL protocol rejected: data:/);
    assert.throws(() => validateApiUrl('ftp://files.sfumsa.ca', false), /Insecure API URL protocol rejected: ftp:/);
    assert.throws(() => validateApiUrl('ws://stream.sfumsa.ca', false), /Insecure API URL protocol rejected: ws:/);
  });

  await t.test('strictly rejects protocol-relative API URLs', () => {
    assert.throws(() => validateApiUrl('//attacker.com/api', false), /Protocol-relative API base URLs are not allowed/);
  });

  await t.test('rejects empty or whitespace URLs', () => {
    assert.throws(() => validateApiUrl('', false), /API base URL cannot be empty/);
    assert.throws(() => validateApiUrl('   ', false), /API base URL cannot be empty/);
  });
});

test('API Origin Boundary & Credential Protection', async (t) => {
  const configuredProdBase = 'https://api.sfumsa.ca/api/v1';

  await t.test('permits relative API endpoints against configured origin', () => {
    assert.equal(isAllowedApiDestination('/api/v1/users/me', configuredProdBase), true);
    assert.equal(isAllowedApiDestination('/dashboard', configuredProdBase), true);
    assert.equal(isAllowedApiDestination('ems/public/events', configuredProdBase), true);
  });

  await t.test('permits exact origin match for absolute URLs', () => {
    assert.equal(
      isAllowedApiDestination('https://api.sfumsa.ca/api/v1/auth/me', configuredProdBase),
      true,
    );
    assert.equal(
      isAllowedApiDestination('https://api.sfumsa.ca/api/v1/notifications', configuredProdBase),
      true,
    );
  });

  await t.test('strictly rejects cross-origin URLs to prevent token leakage', () => {
    assert.equal(
      isAllowedApiDestination('https://attacker.com/leak-token', configuredProdBase),
      false,
    );
    assert.equal(
      isAllowedApiDestination('https://evil.api.sfumsa.ca/v1', configuredProdBase),
      false,
    );
    assert.equal(
      isAllowedApiDestination('https://api.sfumsa.ca.phishing.org/api/v1', configuredProdBase),
      false,
    );
  });

  await t.test('strictly rejects protocol-relative URLs', () => {
    assert.equal(
      isAllowedApiDestination('//attacker.com/steal', configuredProdBase),
      false,
    );
  });

  await t.test('permits empty/null request URL by safely defaulting to baseURL', () => {
    assert.equal(isAllowedApiDestination(null, configuredProdBase), true);
    assert.equal(isAllowedApiDestination(undefined, configuredProdBase), true);
    assert.equal(isAllowedApiDestination('', configuredProdBase), true);
  });

  await t.test('rejects malformed or dangerous protocols', () => {
    assert.equal(
      isAllowedApiDestination('javascript:alert(1)', configuredProdBase),
      false,
    );
    assert.equal(
      isAllowedApiDestination('data:text/plain,bad', configuredProdBase),
      false,
    );
  });
});
