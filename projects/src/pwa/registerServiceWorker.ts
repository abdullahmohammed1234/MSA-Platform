import { ref } from 'vue';

export const pwaUpdateAvailable = ref(false);

let registration: ServiceWorkerRegistration | null = null;
let refreshing = false;

export function getServiceWorkerRegistration(): ServiceWorkerRegistration | null {
  return registration;
}

export async function applyPwaUpdate(): Promise<void> {
  const waiting = registration?.waiting;
  if (!waiting) {
    window.location.reload();
    return;
  }
  waiting.postMessage({ type: 'SKIP_WAITING' });
}

export function registerServiceWorker(): void {
  if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
    return;
  }

  const isSecure =
    window.location.protocol === 'https:' ||
    window.location.hostname === 'localhost' ||
    window.location.hostname === '127.0.0.1';

  if (!isSecure) {
    return;
  }

  window.addEventListener('load', () => {
    navigator.serviceWorker
      .register('/sw.js', { scope: '/' })
      .then((reg) => {
        registration = reg;

        if (reg.waiting && navigator.serviceWorker.controller) {
          pwaUpdateAvailable.value = true;
        }

        reg.addEventListener('updatefound', () => {
          const installingWorker = reg.installing;
          if (!installingWorker) return;

          installingWorker.addEventListener('statechange', () => {
            if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
              pwaUpdateAvailable.value = true;
            }
          });
        });
      })
      .catch((err) => {
        console.debug('PMS service worker registration skipped:', err);
      });

    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (refreshing) return;
      refreshing = true;
      window.location.reload();
    });
  });
}
