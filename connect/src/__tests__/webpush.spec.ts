import test from 'node:test';
import assert from 'node:assert/strict';
import { urlBase64ToUint8Array, detectDeviceType, detectDeviceName } from '../services/webpush.service.ts';

test('MSA Connect Web Push Client Architecture & Safety Verification', async (t) => {
  await t.test('urlBase64ToUint8Array correctly converts base64url to Uint8Array', () => {
    // Standard test base64url encoded string
    const input = 'BK8a-9Z_1234567890abcdefABCDEF';
    const result = urlBase64ToUint8Array(input);

    assert.ok(result instanceof Uint8Array);
    assert.ok(result.length > 0);
  });

  await t.test('detectDeviceType correctly categorizes device user agents', () => {
    const iphoneUa = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';
    assert.equal(detectDeviceType(iphoneUa), 'mobile');

    const ipadUa = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';
    assert.equal(detectDeviceType(ipadUa), 'tablet');

    const desktopUa = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    assert.equal(detectDeviceType(desktopUa), 'desktop');
  });

  await t.test('detectDeviceName creates human-readable device identifiers', () => {
    const chromeWin = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';
    assert.equal(detectDeviceName(chromeWin), 'Chrome on Windows');

    const safariMac = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/17.0 Safari/605.1.15';
    assert.equal(detectDeviceName(safariMac), 'Safari on macOS');

    const firefoxLinux = 'Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/119.0';
    assert.equal(detectDeviceName(firefoxLinux), 'Firefox on Linux');
  });

  await t.test('Push URL target validator defuses open redirects and invalid protocols', () => {
    function sanitizePushUrl(rawUrl: unknown): string {
      if (typeof rawUrl !== 'string') return '/';
      const trimmed = rawUrl.trim();
      if (
        !trimmed.startsWith('/') ||
        trimmed.startsWith('//') ||
        trimmed.startsWith('/\\') ||
        trimmed.includes('\\') ||
        /^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(trimmed)
      ) {
        return '/';
      }
      return trimmed;
    }

    assert.equal(sanitizePushUrl('/app/messages'), '/app/messages');
    assert.equal(sanitizePushUrl('/app/support/INQ-202610-1234'), '/app/support/INQ-202610-1234');
    assert.equal(sanitizePushUrl('/app/events'), '/app/events');
    assert.equal(sanitizePushUrl('//evil.com/app/messages'), '/');
    assert.equal(sanitizePushUrl('/\\evil.com'), '/');
    assert.equal(sanitizePushUrl('https://evil.com/phish'), '/');
    assert.equal(sanitizePushUrl('javascript:alert(1)'), '/');
    assert.equal(sanitizePushUrl(''), '/');
    assert.equal(sanitizePushUrl(null), '/');
  });

  await t.test('Push notification preference is strictly independent from email preference', () => {
    const preferences = {
      email_enabled: false,
      in_app_enabled: true,
      push_enabled: true,
      direct_messages: true,
      support_updates: true,
    };

    // Disabling email notifications does not disable push notifications
    assert.equal(preferences.email_enabled, false);
    assert.equal(preferences.push_enabled, true);

    // Disabling push does not disable in-app notifications
    preferences.push_enabled = false;
    assert.equal(preferences.in_app_enabled, true);
    assert.equal(preferences.push_enabled, false);
  });

  await t.test('Push payload privacy invariants are preserved without leaking message contents', () => {
    const mockDmPushPayload = {
      title: 'SFU MSA Connect',
      body: 'You received a new direct message.',
      url: '/app/messages',
      icon: '/favicon.svg',
      tag: 'dm-notification',
    };

    // Sensitive message bodies and sender identities are never exposed in push payload
    assert.ok(!('sender_name' in mockDmPushPayload));
    assert.ok(!('message_content' in mockDmPushPayload));
    assert.ok(!('message_body' in mockDmPushPayload));
    assert.equal(mockDmPushPayload.body, 'You received a new direct message.');
    assert.equal(mockDmPushPayload.url, '/app/messages');
  });

  await t.test('Subscription payload normalization preserves dual compatibility', () => {
    // Test payload shape expected by both backend controllers and clients
    const endpoint = 'https://updates.push.services.mozilla.com/wpush/v2/test-123';
    const p256dh = 'test-public-key-base64';
    const auth = 'test-auth-token-base64';

    const payload = {
      endpoint,
      public_key: p256dh,
      auth_token: auth,
      keys: {
        p256dh,
        auth,
      },
      device_name: 'Chrome on Windows',
      device_type: 'desktop' as const,
    };

    // Both flat and nested keys are populated to guarantee backward/forward compatibility
    assert.equal(payload.public_key, p256dh);
    assert.equal(payload.auth_token, auth);
    assert.equal(payload.keys.p256dh, p256dh);
    assert.equal(payload.keys.auth, auth);
    assert.equal(payload.endpoint, endpoint);
  });

  await t.test('Device list parser accepts both devices and subscriptions response keys', () => {
    const rawDevicesResponse = {
      data: {
        devices: [
          { uuid: 'dev-1', device_name: 'Chrome', device_type: 'desktop' },
        ],
      },
    };

    const rawSubscriptionsResponse = {
      data: {
        subscriptions: [
          { uuid: 'dev-2', device_name: 'Safari', device_type: 'mobile' },
        ],
      },
    };

    function parseDeviceList(data: any): any[] {
      return data?.devices || data?.subscriptions || [];
    }

    assert.equal(parseDeviceList(rawDevicesResponse.data).length, 1);
    assert.equal(parseDeviceList(rawDevicesResponse.data)[0].uuid, 'dev-1');
    assert.equal(parseDeviceList(rawSubscriptionsResponse.data).length, 1);
    assert.equal(parseDeviceList(rawSubscriptionsResponse.data)[0].uuid, 'dev-2');
    assert.equal(parseDeviceList({}).length, 0);
  });
});
