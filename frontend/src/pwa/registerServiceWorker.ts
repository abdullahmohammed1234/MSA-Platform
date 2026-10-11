/**
 * Narrowly unregisters ONLY legacy SFU MSA service worker registrations (/sw.js)
 * to ensure clean migration without affecting unrelated service workers on the origin.
 * Mobile PWA installation is powered by manifest.webmanifest and beforeinstallprompt without custom offline caching.
 */
export function unregisterLegacyServiceWorker(): void {
  if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
    return;
  }

  const runCleanup = async () => {
    try {
      if (typeof navigator.serviceWorker.getRegistrations !== 'function') {
        return;
      }
      const registrations = await navigator.serviceWorker.getRegistrations();
      for (const registration of registrations) {
        const scriptUrl =
          registration.active?.scriptURL ||
          registration.waiting?.scriptURL ||
          registration.installing?.scriptURL ||
          '';

        // Target ONLY the legacy MSA service worker script (/sw.js)
        const isMsaWorker = scriptUrl.endsWith('/sw.js') || /\/sw\.js(\?.*)?$/.test(scriptUrl);

        if (isMsaWorker) {
          const success = await registration.unregister();
          if (success) {
            console.log('[PWA] Unregistered legacy MSA service worker:', registration.scope, scriptUrl);
          }
        }
      }
    } catch (error) {
      console.warn('[PWA] Non-fatal error during legacy service worker cleanup:', error);
    }
  };

  if (document.readyState === 'complete') {
    runCleanup();
  } else {
    window.addEventListener('load', runCleanup, { once: true });
  }
}

// Retain alias export for backwards compatibility
export const registerServiceWorker = unregisterLegacyServiceWorker;
