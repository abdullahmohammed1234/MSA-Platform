import test from 'node:test';
import assert from 'node:assert/strict';
import {
  checkIsStandalone,
  detectPlatform,
  isDismissalActive,
  setDismissal,
  clearDismissal,
  setDeferredPrompt,
  usePwaInstall,
} from '../composables/usePwaInstall.ts';

test('MSA Connect PWA Installation Composable & Experience Verification', async (t) => {
  // Mock localStorage for Node test runner
  const storageMap = new Map<string, string>();
  const mockLocalStorage = {
    getItem: (key: string) => storageMap.get(key) ?? null,
    setItem: (key: string, value: string) => storageMap.set(key, String(value)),
    removeItem: (key: string) => storageMap.delete(key),
    clear: () => storageMap.clear(),
  };

  const originalWindow = (globalThis as any).window;
  const originalLocalStorage = (globalThis as any).localStorage;

  t.afterEach(() => {
    storageMap.clear();
    setDeferredPrompt(null);
  });

  t.after(() => {
    (globalThis as any).window = originalWindow;
    (globalThis as any).localStorage = originalLocalStorage;
  });

  await t.test('detectPlatform accurately identifies iOS, Android, Desktop, and fallback', () => {
    // iOS iPhone UA
    const iphoneUa = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';
    assert.equal(detectPlatform(iphoneUa), 'ios');

    // iOS iPad UA
    const ipadUa = 'Mozilla/5.0 (iPad; CPU OS 16_5 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';
    assert.equal(detectPlatform(ipadUa), 'ios');

    // Android UA
    const androidUa = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/122.0.0.0 Mobile Safari/537.36';
    assert.equal(detectPlatform(androidUa), 'android');

    // Desktop Windows UA
    const winUa = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0.0.0 Safari/537.36';
    assert.equal(detectPlatform(winUa), 'desktop');

    // Desktop macOS UA
    const macUa = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/122.0.0.0 Safari/537.36';
    assert.equal(detectPlatform(macUa), 'desktop');

    // Other / Empty
    assert.equal(detectPlatform(''), 'other');
    assert.equal(detectPlatform('CustomBot/1.0'), 'other');
  });

  await t.test('checkIsStandalone detects standalone display mode or iOS standalone flag', () => {
    // Neither standalone
    (globalThis as any).window = {
      matchMedia: () => ({
        matches: false,
        addEventListener: () => {},
      }),
      navigator: {},
    };
    assert.equal(checkIsStandalone(), false);

    // Standard display-mode: standalone
    (globalThis as any).window = {
      matchMedia: (query: string) => ({
        matches: query.includes('display-mode: standalone'),
        addEventListener: () => {},
      }),
      navigator: {},
    };
    assert.equal(checkIsStandalone(), true);

    // iOS navigator.standalone = true
    (globalThis as any).window = {
      matchMedia: () => ({ matches: false, addEventListener: () => {} }),
      navigator: { standalone: true },
    };
    assert.equal(checkIsStandalone(), true);
  });

  await t.test('Dismissal tracking enforces 7-day persistence and clean reset', () => {
    (globalThis as any).window = {};
    (globalThis as any).localStorage = mockLocalStorage;

    clearDismissal();
    assert.equal(isDismissalActive(), false);

    // Record dismissal
    setDismissal();
    assert.equal(isDismissalActive(), true);
    assert.ok(storageMap.has('connect_pwa_install_dismissed'));

    // Test expired dismissal (older than 7 days)
    const eightDaysAgo = Date.now() - 8 * 24 * 60 * 60 * 1000;
    storageMap.set('connect_pwa_install_dismissed', String(eightDaysAgo));
    assert.equal(isDismissalActive(), false);

    // Test fresh dismissal within 7 days
    const oneDayAgo = Date.now() - 1 * 24 * 60 * 60 * 1000;
    storageMap.set('connect_pwa_install_dismissed', String(oneDayAgo));
    assert.equal(isDismissalActive(), true);

    // Clear dismissal
    clearDismissal();
    assert.equal(isDismissalActive(), false);
  });

  await t.test('promptInstall safely handles accepted and dismissed user choices', async () => {
    (globalThis as any).window = {
      matchMedia: () => ({ matches: false, addEventListener: () => {} }),
      addEventListener: () => {},
      navigator: { userAgent: 'Chrome on Windows' },
    };
    (globalThis as any).localStorage = mockLocalStorage;

    const composable = usePwaInstall();

    // When no prompt is deferred, returns 'unsupported'
    setDeferredPrompt(null);
    const resultNoPrompt = await composable.promptInstall();
    assert.equal(resultNoPrompt, 'unsupported');

    // When prompt is accepted
    let promptCalled = false;
    const mockAcceptedPrompt: any = {
      prompt: async () => { promptCalled = true; },
      userChoice: Promise.resolve({ outcome: 'accepted', platform: 'web' }),
    };
    setDeferredPrompt(mockAcceptedPrompt);
    assert.equal(composable.isNativePromptAvailable.value, true);

    const resultAccepted = await composable.promptInstall();
    assert.equal(promptCalled, true);
    assert.equal(resultAccepted, 'accepted');
    assert.equal(composable.isNativePromptAvailable.value, false);

    // When prompt is dismissed
    const mockDismissedPrompt: any = {
      prompt: async () => {},
      userChoice: Promise.resolve({ outcome: 'dismissed', platform: 'web' }),
    };
    setDeferredPrompt(mockDismissedPrompt);
    const resultDismissed = await composable.promptInstall();
    assert.equal(resultDismissed, 'dismissed');
  });
});
