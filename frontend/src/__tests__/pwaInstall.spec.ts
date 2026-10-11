import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { mount } from '@vue/test-utils';
import { usePwaStore } from '@/stores/pwa';
import { unregisterLegacyServiceWorker } from '@/pwa/registerServiceWorker';
import PwaInstallPrompt from '@/components/common/PwaInstallPrompt.vue';

describe('PWA Mobile Installation & Service Worker Migration', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    sessionStorage.clear();
  });

  it('1. initializes PWA store with default installation states', () => {
    const store = usePwaStore();
    expect(store.canInstall).toBe(false);
    expect(store.isInstalled).toBe(false);
  });

  it('2. captures beforeinstallprompt event and enables canInstall', () => {
    const store = usePwaStore();
    store.initListeners();

    const mockPromptEvent = new Event('beforeinstallprompt') as any;
    mockPromptEvent.prompt = vi.fn().mockResolvedValue(undefined);
    mockPromptEvent.userChoice = Promise.resolve({ outcome: 'accepted', platform: 'web' });

    window.dispatchEvent(mockPromptEvent);

    expect(store.canInstall).toBe(true);
    expect(store.canShowPrompt).toBe(true);
  });

  it('3. handles promptInstall when user accepts installation', async () => {
    const store = usePwaStore();
    store.initListeners();

    const mockPromptEvent = new Event('beforeinstallprompt') as any;
    mockPromptEvent.prompt = vi.fn().mockResolvedValue(undefined);
    mockPromptEvent.userChoice = Promise.resolve({ outcome: 'accepted', platform: 'web' });

    window.dispatchEvent(mockPromptEvent);

    const result = await store.promptInstall();

    expect(mockPromptEvent.prompt).toHaveBeenCalled();
    expect(result).toBe(true);
    expect(store.isInstalled).toBe(true);
    expect(store.canInstall).toBe(false);
  });

  it('4. updates state correctly when user dismisses native prompt', async () => {
    const store = usePwaStore();
    store.initListeners();

    const mockPromptEvent = new Event('beforeinstallprompt') as any;
    mockPromptEvent.prompt = vi.fn().mockResolvedValue(undefined);
    mockPromptEvent.userChoice = Promise.resolve({ outcome: 'dismissed', platform: 'web' });

    window.dispatchEvent(mockPromptEvent);

    const result = await store.promptInstall();

    expect(result).toBe(false);
    expect(store.canInstall).toBe(false);
    expect(sessionStorage.getItem('pwa_install_dismissed')).toBe('true');
  });

  it('5. allows dismissing custom install prompt and persists dismiss preference', () => {
    const store = usePwaStore();
    store.initListeners();

    const mockPromptEvent = new Event('beforeinstallprompt') as any;
    window.dispatchEvent(mockPromptEvent);

    store.dismissInstall();

    expect(store.canInstall).toBe(false);
    expect(sessionStorage.getItem('pwa_install_dismissed')).toBe('true');
  });

  it('6. an already-installed app does not show install invitation', () => {
    const store = usePwaStore();
    store.initListeners();

    const mockPromptEvent = new Event('beforeinstallprompt') as any;
    window.dispatchEvent(mockPromptEvent);

    store.checkInstalled();
    store.$patch({ isInstalled: true });

    expect(store.canInstall).toBe(false);
    expect(store.canShowPrompt).toBe(false);
  });

  it('7. PwaInstallPrompt component mounts and renders when install is available', async () => {
    const store = usePwaStore();
    store.initListeners();

    const mockPromptEvent = new Event('beforeinstallprompt') as any;
    mockPromptEvent.prompt = vi.fn().mockResolvedValue(undefined);
    mockPromptEvent.userChoice = Promise.resolve({ outcome: 'accepted', platform: 'web' });

    window.dispatchEvent(mockPromptEvent);

    const wrapper = mount(PwaInstallPrompt);

    expect(wrapper.text()).toContain('Install SFU MSA App');
    expect(wrapper.find('button').exists()).toBe(true);

    const installBtn = wrapper.find('button.bg-primary');
    await installBtn.trigger('click');

    expect(mockPromptEvent.prompt).toHaveBeenCalled();
  });

  it('8. handles missing beforeinstallprompt gracefully without crashing', () => {
    const store = usePwaStore();
    expect(() => store.initListeners()).not.toThrow();

    const wrapper = mount(PwaInstallPrompt);
    expect(wrapper.find('div[role="dialog"]').exists()).toBe(false);
  });

  it('9. supports iOS-specific guidance independently when on iOS Safari', () => {
    const store = usePwaStore();
    store.$patch({ isInstalled: false });

    // Mock iOS UserAgent
    Object.defineProperty(navigator, 'userAgent', {
      value: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1',
      writable: true,
      configurable: true,
    });

    expect(store.isIos).toBe(true);
    expect(store.canShowIosPrompt).toBe(true);
    expect(store.canShowPrompt).toBe(true);

    const wrapper = mount(PwaInstallPrompt);
    expect(wrapper.text()).toContain('Add to Home Screen');
    expect(wrapper.text()).toContain('Got It');
  });

  describe('Legacy Service Worker Targeted Cleanup', () => {
    it('10. unregisters expected legacy MSA worker (/sw.js)', async () => {
      const mockUnregisterMsa = vi.fn().mockResolvedValue(true);
      const msaRegistration = {
        scope: 'http://localhost/',
        active: { scriptURL: 'http://localhost/sw.js' },
        unregister: mockUnregisterMsa,
      };

      Object.defineProperty(navigator, 'serviceWorker', {
        value: {
          getRegistrations: vi.fn().mockResolvedValue([msaRegistration]),
          register: vi.fn(),
        },
        writable: true,
        configurable: true,
      });

      unregisterLegacyServiceWorker();
      window.dispatchEvent(new Event('load'));
      await new Promise((resolve) => setTimeout(resolve, 50));

      expect(mockUnregisterMsa).toHaveBeenCalledTimes(1);
    });

    it('11. preserves unrelated service workers on the same origin', async () => {
      const mockUnregisterMsa = vi.fn().mockResolvedValue(true);
      const mockUnregisterUnrelated = vi.fn().mockResolvedValue(true);

      const msaRegistration = {
        scope: 'http://localhost/',
        active: { scriptURL: 'http://localhost/sw.js' },
        unregister: mockUnregisterMsa,
      };

      const unrelatedRegistration = {
        scope: 'http://localhost/analytics/',
        active: { scriptURL: 'http://localhost/analytics-worker.js' },
        unregister: mockUnregisterUnrelated,
      };

      Object.defineProperty(navigator, 'serviceWorker', {
        value: {
          getRegistrations: vi.fn().mockResolvedValue([msaRegistration, unrelatedRegistration]),
          register: vi.fn(),
        },
        writable: true,
        configurable: true,
      });

      unregisterLegacyServiceWorker();
      window.dispatchEvent(new Event('load'));
      await new Promise((resolve) => setTimeout(resolve, 50));

      expect(mockUnregisterMsa).toHaveBeenCalledTimes(1);
      expect(mockUnregisterUnrelated).not.toHaveBeenCalled();
    });

    it('12. does not crash in unsupported service-worker environments', () => {
      const originalServiceWorker = (navigator as any).serviceWorker;
      Object.defineProperty(navigator, 'serviceWorker', {
        value: undefined,
        writable: true,
        configurable: true,
      });

      expect(() => {
        unregisterLegacyServiceWorker();
        window.dispatchEvent(new Event('load'));
      }).not.toThrow();

      Object.defineProperty(navigator, 'serviceWorker', {
        value: originalServiceWorker,
        writable: true,
        configurable: true,
      });
    });

    it('13. handles cleanup errors safely without breaking application startup', async () => {
      Object.defineProperty(navigator, 'serviceWorker', {
        value: {
          getRegistrations: vi.fn().mockRejectedValue(new Error('SecurityError: Access denied')),
          register: vi.fn(),
        },
        writable: true,
        configurable: true,
      });

      expect(() => {
        unregisterLegacyServiceWorker();
        window.dispatchEvent(new Event('load'));
      }).not.toThrow();

      await new Promise((resolve) => setTimeout(resolve, 50));
    });

    it('14. cleanup does not register a new replacement service worker', async () => {
      const mockRegister = vi.fn();
      Object.defineProperty(navigator, 'serviceWorker', {
        value: {
          getRegistrations: vi.fn().mockResolvedValue([]),
          register: mockRegister,
        },
        writable: true,
        configurable: true,
      });

      unregisterLegacyServiceWorker();
      window.dispatchEvent(new Event('load'));
      await new Promise((resolve) => setTimeout(resolve, 50));

      expect(mockRegister).not.toHaveBeenCalled();
    });
  });
});
