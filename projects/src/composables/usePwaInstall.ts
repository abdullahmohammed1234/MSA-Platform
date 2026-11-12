import { ref, computed } from 'vue';

export interface BeforeInstallPromptEvent extends Event {
  readonly platforms: string[];
  readonly userChoice: Promise<{
    outcome: 'accepted' | 'dismissed';
    platform: string;
  }>;
  prompt(): Promise<void>;
}

const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null);
const isNativePromptAvailable = ref(false);
const isInstalledState = ref(false);
const isDismissedByUser = ref(false);
const isInitialized = ref(false);

const DISMISSAL_KEY = 'msa_pms_pwa_install_dismissed';
const DISMISSAL_EXPIRY_MS = 7 * 24 * 60 * 60 * 1000;

export function checkIsStandalone(): boolean {
  if (typeof window === 'undefined') return false;
  const isStandaloneMedia = window.matchMedia('(display-mode: standalone)').matches;
  const isIosStandalone = (window.navigator as unknown as { standalone?: boolean }).standalone === true;
  const isMinimalUi = window.matchMedia('(display-mode: minimal-ui)').matches;
  return isStandaloneMedia || isIosStandalone || isMinimalUi;
}

export function detectPlatform(customUa?: string): 'ios' | 'android' | 'desktop' | 'other' {
  const ua = (customUa || (typeof navigator !== 'undefined' ? navigator.userAgent : '')).toLowerCase();
  if (!ua) return 'other';
  if (/iphone|ipad|ipod/.test(ua)) return 'ios';
  if (/android/.test(ua)) return 'android';
  if (/windows|macintosh|mac os x|linux/.test(ua) && !/mobile/.test(ua)) return 'desktop';
  return 'other';
}

export function isDismissalActive(): boolean {
  if (typeof window === 'undefined' || typeof localStorage === 'undefined') return false;
  try {
    const raw = localStorage.getItem(DISMISSAL_KEY);
    if (!raw) return false;
    const timestamp = parseInt(raw, 10);
    if (Number.isNaN(timestamp)) return false;
    return Date.now() - timestamp < DISMISSAL_EXPIRY_MS;
  } catch {
    return false;
  }
}

export function setDismissal(): void {
  if (typeof window === 'undefined' || typeof localStorage === 'undefined') return;
  try {
    localStorage.setItem(DISMISSAL_KEY, String(Date.now()));
  } catch {
    // ignore
  }
  isDismissedByUser.value = true;
}

export function clearDismissal(): void {
  if (typeof window === 'undefined' || typeof localStorage === 'undefined') return;
  try {
    localStorage.removeItem(DISMISSAL_KEY);
  } catch {
    // ignore
  }
  isDismissedByUser.value = false;
}

export function initializePwaInstallListeners(): void {
  if (typeof window === 'undefined' || isInitialized.value) return;

  isInstalledState.value = checkIsStandalone();
  isDismissedByUser.value = isDismissalActive();

  window.addEventListener('beforeinstallprompt', (e: Event) => {
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

  try {
    const mediaQuery = window.matchMedia('(display-mode: standalone)');
    mediaQuery.addEventListener('change', (e) => {
      if (e.matches) isInstalledState.value = true;
    });
  } catch {
    // Safari <14
  }

  isInitialized.value = true;
}

export function usePwaInstall() {
  initializePwaInstallListeners();

  const platform = computed(() => detectPlatform());
  const isInstalled = computed(() => isInstalledState.value);
  const isInstallable = computed(() => !isInstalled.value && isNativePromptAvailable.value);
  const isIos = computed(() => platform.value === 'ios');
  const canShowIosPrompt = computed(
    () => isIos.value && !isInstalled.value && !isDismissedByUser.value && !isNativePromptAvailable.value
  );
  const canShowBanner = computed(
    () => (isInstallable.value || canShowIosPrompt.value) && !isInstalled.value && !isDismissedByUser.value
  );

  async function promptInstall(): Promise<'accepted' | 'dismissed' | 'unsupported'> {
    if (!deferredPrompt.value) return 'unsupported';
    try {
      await deferredPrompt.value.prompt();
      const choice = await deferredPrompt.value.userChoice;
      if (choice.outcome === 'accepted') {
        isInstalledState.value = true;
        deferredPrompt.value = null;
        isNativePromptAvailable.value = false;
        clearDismissal();
        return 'accepted';
      }
      setDismissal();
      return 'dismissed';
    } catch {
      return 'unsupported';
    } finally {
      deferredPrompt.value = null;
      isNativePromptAvailable.value = false;
    }
  }

  function dismissBanner() {
    setDismissal();
  }

  return {
    platform,
    isInstalled,
    isInstallable,
    canShowBanner,
    canShowIosPrompt,
    isIos,
    promptInstall,
    dismissBanner,
  };
}
