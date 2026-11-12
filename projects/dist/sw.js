/**
 * SFU MSA Projects (PMS) — Progressive Web App Service Worker
 *
 * Conservative offline strategy:
 * - Cache public application shell, static assets, icons, logos, offline page.
 * - Never cache authenticated API responses, tokens, or private attachments.
 * - API / cross-origin API host requests are network-only.
 */

/* eslint-disable no-restricted-globals */

const CACHE_VERSION = 'msa-pms-shell-v1';
const SHELL_URLS = [
  '/',
  '/index.html',
  '/offline.html',
  '/logo.webp',
  '/favicon.svg',
  '/manifest.webmanifest',
  '/manifest.json',
  '/icons/msa-pwa-192x192.png',
  '/icons/msa-pwa-512x512.png',
  '/icons/msa-pwa-maskable-512x512.png',
  '/icons/msa-apple-touch-icon.png',
];

const API_HOSTS = new Set(['api.sfumsa.ca']);

function isApiRequest(requestUrl) {
  try {
    const url = new URL(requestUrl);
    if (API_HOSTS.has(url.hostname)) return true;
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/sanctum/')) return true;
    return false;
  } catch {
    return false;
  }
}

function isStaticAsset(requestUrl) {
  try {
    const url = new URL(requestUrl);
    if (url.origin !== self.location.origin) return false;
    return (
      url.pathname.startsWith('/assets/') ||
      url.pathname.startsWith('/icons/') ||
      /\.(?:js|css|woff2?|svg|webp|png|jpg|jpeg|ico|webmanifest|json)$/i.test(url.pathname)
    );
  } catch {
    return false;
  }
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) =>
      Promise.all(
        SHELL_URLS.map((url) =>
          cache.add(url).catch(() => {
            // Individual shell entries may 404 during local preview; ignore.
          })
        )
      )
    )
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(
        keys
          .filter((key) => key.startsWith('msa-pms-') && key !== CACHE_VERSION)
          .map((key) => caches.delete(key))
      );
      await self.clients.claim();
    })()
  );
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') {
    return;
  }

  if (isApiRequest(request.url)) {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      (async () => {
        try {
          const networkResponse = await fetch(request);
          return networkResponse;
        } catch {
          const cache = await caches.open(CACHE_VERSION);
          return (
            (await cache.match('/offline.html')) ||
            (await cache.match('/index.html')) ||
            new Response('Offline', { status: 503, statusText: 'Offline' })
          );
        }
      })()
    );
    return;
  }

  if (isStaticAsset(request.url) || SHELL_URLS.includes(new URL(request.url).pathname)) {
    event.respondWith(
      (async () => {
        const cache = await caches.open(CACHE_VERSION);
        const cached = await cache.match(request);
        if (cached) {
          return cached;
        }
        try {
          const response = await fetch(request);
          if (response.ok && response.type === 'basic') {
            cache.put(request, response.clone());
          }
          return response;
        } catch {
          return (
            cached ||
            new Response('', { status: 504, statusText: 'Gateway Timeout' })
          );
        }
      })()
    );
  }
});
