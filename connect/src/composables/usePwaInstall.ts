import { ref, computed } from 'vue';

export interface BeforeInstallPromptEvent extends Event {
  readonly platforms: string[];
  readonly userChoice: Promise<{
    outcome: 'accepted' | 'dismissed';
    platform: string;
  }>;
  prompt(): Promise<void>;
}

// Module-level singletons to persist prompt across route changes
const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null);
const isNativePromptAvailable = ref<boolean>(false);
const isInstalledState = ref<boolean>(false);
const isDismissedByUser = ref<boolean>(false);
const isInitialized = ref<boolean>(false);

const DISMISSAL_KEY = 'connect_pwa_install_dismissed';
const DISMISSAL_EXPIRY_MS = 7 * 24 * 60 * 60 * 1000; // 7 days

/**
 * Check if the application is running in standalone PWA mode.
 */
export function checkIsStandalone(): boolean {
  if (typeof window === 'undefined') return false;

  // Standard matchMedia check
  const isStandaloneMedia = window.matchMedia('(display-mode: standalone)').matches;
  // iOS Safari fullscreen webapp check
  const isIosStandalone = (window.navigator as unknown as { standalone?: boolean }).standalone === true;
  // Android TWA / display-mode minimal-ui check
  const isMinimalUi = window.matchMedia('(display-mode: minimal-ui)').matches;

  return isStandaloneMedia || isIosStandalone || isMinimalUi;
}

/**
 * Detect client platform heuristics.
 */
export function detectPlatform(customUa?: string): 'ios' | 'android' | 'desktop' | 'other' {
  const ua = (customUa || (typeof navigator !== 'undefined' ? navigator.userAgent : '')).toLowerCase();
  if (!ua) return 'other';

  if (/iphone|ipad|ipod/.test(ua)) {
    if (typeof window !== 'undefined' && (window as unknown as { MSStream?: unknown }).MSStream) {
      return 'other';
    }
    return 'ios';
  }
  if (/android/.test(ua)) {
    return 'android';
  }
  if (/windows|macintosh|mac os x|linux/.test(ua) && !/mobile/.test(ua)) {
    return 'desktop';
  }
  return 'other';
}

/**
 * Check if the user previously dismissed the install prompt banner recently.
 */
export function isDismissalActive(): boolean {
  if (typeof window === 'undefined' || typeof localStorage === 'undefined') return false;
  try {
    const raw = localStorage.getItem(DISMISSAL_KEY);
    if (!raw) return false;
    const timestamp = parseInt(raw, 10);
    if (isNaN(timestamp)) return false;
    return Date.now() - timestamp < DISMISSAL_EXPIRY_MS;
  } catch {
    return false;
  }
}

/**
 * Record user dismissal timestamp.
 */
export function setDismissal(): void {
  if (typeof window === 'undefined' || typeof localStorage === 'undefined') return;
  try {
    localStorage.setItem(DISMISSAL_KEY, String(Date.now()));
    isDismissedByUser.value = true;
  } catch {
    isDismissedByUser.value = true;
  }
}

/**
 * Clear user dismissal (e.g. for user-initiated retry).
 */
export function clearDismissal(): void {
  if (typeof window === 'undefined' || typeof localStorage === 'undefined') return;
  try {
    localStorage.removeItem(DISMISSAL_KEY);
  } catch {
    // Ignore
  }
  isDismissedByUser.value = false;
}

/**
 * Set the deferred prompt event (useful for tests and listener).
 */
export function setDeferredPrompt(event: BeforeInstallPromptEvent | null): void {
  deferredPrompt.value = event;
  isNativePromptAvailable.value = !!event;
}

/**
 * Initialize global window listeners once.
 */
export function initializePwaInstallListeners(): void {
  if (typeof window === 'undefined' || isInitialized.value) return;

  isInstalledState.value = checkIsStandalone();
  isDismissedByUser.value = isDismissalActive();

  window.addEventListener('beforeinstallprompt', (e: Event) => {
    // Prevent the default mini-infobar from appearing automatically on mobile
    e.preventDefault();
    deferredPrompt.value = e as BeforeInstallPromptEvent;
    isNativePromptAvailable.value = true;
  });

  window.addEventListener('appinstalled', () => {
    deferredPrompt.value = null;
    isNativePromptAvailable.value = false;
    isInstalledState.value = true;
    clearDismissal();
  });

  // Re-check display mode on media query change
  try {
    const mediaQuery = window.matchMedia('(display-mode: standalone)');
    mediaQuery.addEventListener('change', (e) => {
      if (e.matches) {
        isInstalledState.value = true;
      }
    });
  } catch {
    // Safari <14 fallback
  }

  isInitialized.value = true;
}

/**
 * Vue composable for PWA installation experience.
 */
export function usePwaInstall() {
  initializePwaInstallListeners();

  const platform = computed(() => detectPlatform());
  const isInstalled = computed(() => isInstalledState.value);
  const isInstallable = computed(() => !isInstalled.value && isNativePromptAvailable.value);
  const isIos = computed(() => platform.value === 'ios');
  const canInstall = computed(() => isInstallable.value);
  const canShowIosPrompt = computed(() => isIos.value && !isInstalled.value && !isDismissedByUser.value && !isNativePromptAvailable.value);
  const canShowPrompt = computed(() => (canInstall.value || canShowIosPrompt.value) && !isInstalled.value && !isDismissedByUser.value);
  const canShowBanner = computed(() => canShowPrompt.value);

  /**
   * Trigger the native installation prompt.
   */
  async function promptInstall(): Promise<'accepted' | 'dismissed' | 'unsupported'> {
    if (!deferredPrompt.value) {
      return 'unsupported';
    }

    try {
      await deferredPrompt.value.prompt();
      const choice = await deferredPrompt.value.userChoice;
      if (choice.outcome === 'accepted') {
        isInstalledState.value = true;
        deferredPrompt.value = null;
        isNativePromptAvailable.value = false;
        clearDismissal();
        return 'accepted';
      } else {
        setDismissal();
        return 'dismissed';
      }
    } catch (err) {
      console.warn('Native PWA install prompt failed:', err);
      return 'unsupported';
    } finally {
      deferredPrompt.value = null;
      isNativePromptAvailable.value = false;
    }
  }

  /**
   * Dismiss the banner and record preference.
   */
  function dismissBanner() {
    setDismissal();
  }

  function dismissInstall() {
    setDismissal();
  }

  return {
    platform,
    isInstalled,
    isInstallable,
    canInstall,
    isNativePromptAvailable,
    canShowBanner,
    canShowPrompt,
    canShowIosPrompt,
    isIos,
    isDismissed: computed(() => isDismissedByUser.value),
    promptInstall,
    dismissBanner,
    dismissInstall,
    clearDismissal,
  };
}
