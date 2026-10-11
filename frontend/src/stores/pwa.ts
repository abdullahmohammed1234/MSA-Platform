import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>;
}

// Early capture to ensure beforeinstallprompt is never lost even if dispatched prior to Vue component mount
let earlyDeferredPrompt: BeforeInstallPromptEvent | null = null;

if (typeof window !== 'undefined') {
  window.addEventListener('beforeinstallprompt', (e: Event) => {
    e.preventDefault();
    earlyDeferredPrompt = e as BeforeInstallPromptEvent;
  });
}

export const usePwaStore = defineStore('pwa', () => {
  const deferredPrompt = ref<BeforeInstallPromptEvent | null>(earlyDeferredPrompt);
  const isInstallDismissed = ref<boolean>(
    typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('pwa_install_dismissed') === 'true' : false
  );
  const isInstalled = ref<boolean>(false);

  // Check if running in standalone mode on mobile/desktop
  const checkInstalled = () => {
    if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
      const isStandalone = 
        window.matchMedia('(display-mode: standalone)').matches ||
        (window.navigator as unknown as { standalone?: boolean }).standalone === true;
      isInstalled.value = isStandalone;
    }
  };

  const isIos = computed(() => {
    if (typeof window === 'undefined') return false;
    const ua = window.navigator.userAgent || '';
    const platform = (window.navigator as unknown as { platform?: string }).platform || '';
    const maxTouchPoints = window.navigator.maxTouchPoints || 0;
    return /iPad|iPhone|iPod/.test(ua) || (platform === 'MacIntel' && maxTouchPoints > 1);
  });

  const canInstall = computed(() => {
    return !!deferredPrompt.value && !isInstalled.value && !isInstallDismissed.value;
  });

  const canShowIosPrompt = computed(() => {
    return isIos.value && !isInstalled.value && !isInstallDismissed.value && !deferredPrompt.value;
  });

  const canShowPrompt = computed(() => {
    return (canInstall.value || canShowIosPrompt.value) && !isInstalled.value && !isInstallDismissed.value;
  });

  const initListeners = () => {
    if (typeof window === 'undefined') return;

    checkInstalled();

    if (earlyDeferredPrompt && !deferredPrompt.value) {
      deferredPrompt.value = earlyDeferredPrompt;
    }

    window.addEventListener('beforeinstallprompt', (e: Event) => {
      e.preventDefault();
      deferredPrompt.value = e as BeforeInstallPromptEvent;
      earlyDeferredPrompt = e as BeforeInstallPromptEvent;
    });

    window.addEventListener('appinstalled', () => {
      deferredPrompt.value = null;
      earlyDeferredPrompt = null;
      isInstalled.value = true;
      console.log('[PWA] SFU MSA Platform was successfully installed!');
    });
  };

  const promptInstall = async (): Promise<boolean> => {
    if (!deferredPrompt.value) {
      console.warn('[PWA] Install prompt unavailable on this device/browser');
      return false;
    }

    try {
      await deferredPrompt.value.prompt();
      const choiceResult = await deferredPrompt.value.userChoice;
      if (choiceResult.outcome === 'accepted') {
        deferredPrompt.value = null;
        earlyDeferredPrompt = null;
        isInstalled.value = true;
        return true;
      } else {
        dismissInstall();
        return false;
      }
    } catch (err) {
      console.error('[PWA] Failed to prompt install:', err);
      return false;
    }
  };

  const dismissInstall = () => {
    isInstallDismissed.value = true;
    if (typeof sessionStorage !== 'undefined') {
      sessionStorage.setItem('pwa_install_dismissed', 'true');
    }
  };

  return {
    canInstall,
    canShowIosPrompt,
    canShowPrompt,
    isIos,
    isInstalled,
    initListeners,
    promptInstall,
    dismissInstall,
    checkInstalled,
  };
});
