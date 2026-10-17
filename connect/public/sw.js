/**
 * SFU MSA Connect — Web Push Service Worker
 *
 * Scope: connect.sfumsa.ca (narrow application root)
 * Privacy & Security Guarantees:
 * 1. Defensively parses push payloads; generic fallbacks when empty/malformed.
 * 2. Never caches authenticated API responses, private messages, support tickets, or credentials.
 * 3. Enforces strict navigation-target validation against allowed application routes.
 * 4. Never leaks subscription keys, authentication secrets, or tokens.
 */

/* eslint-disable no-restricted-globals */

self.addEventListener('install', (event) => {
  // Activate immediately without waiting for existing tabs to close
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  // Claim all active clients within Connect scope immediately
  event.waitUntil(self.clients.claim());
});

/**
 * Handle incoming Web Push notification event.
 */
self.addEventListener('push', (event) => {
  let data = {};

  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      // Fallback for non-JSON or text payloads
      try {
        data = { body: event.data.text() };
      } catch (ignored) {
        data = {};
      }
    }
  }

  const title = (typeof data.title === 'string' && data.title.trim())
    ? data.title.trim()
    : 'SFU MSA Connect';

  const body = (typeof data.body === 'string' && data.body.trim())
    ? data.body.trim()
    : 'You have a new notification.';

  // Validate navigation URL to prevent arbitrary open redirects
  let targetUrl = '/';
  if (typeof data.url === 'string' && data.url.startsWith('/') && !data.url.startsWith('//') && !data.url.startsWith('/\\')) {
    targetUrl = data.url;
  }

  const options = {
    body: body,
    icon: (typeof data.icon === 'string' && data.icon.startsWith('/')) ? data.icon : '/icons/pwa-192x192.png',
    badge: (typeof data.badge === 'string' && data.badge.startsWith('/')) ? data.badge : '/favicon.svg',
    tag: (typeof data.tag === 'string' && data.tag) ? data.tag : 'sfumsa-notification',
    renotify: true,
    data: {
      url: targetUrl,
      timestamp: Date.now(),
    },
  };

  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

/**
 * Handle notification click: focus existing window or open target route.
 */
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const notificationData = event.notification.data || {};
  let rawUrl = notificationData.url || '/';

  // Sanitize navigation target: must be same-origin application-relative path
  if (typeof rawUrl !== 'string' || !rawUrl.startsWith('/') || rawUrl.startsWith('//') || rawUrl.startsWith('/\\')) {
    rawUrl = '/';
  }

  const destinationUrl = new URL(rawUrl, self.location.origin).href;

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      // Check if a client window is already open under this origin
      for (let i = 0; i < clientList.length; i++) {
        const client = clientList[i];
        if (client.url && client.url.startsWith(self.location.origin) && 'focus' in client) {
          // If already on the same origin, navigate to destination and focus
          if ('navigate' in client) {
            client.navigate(destinationUrl);
          }
          return client.focus();
        }
      }

      // If no window is open, open a new window to destination URL
      if (self.clients.openWindow) {
        return self.clients.openWindow(destinationUrl);
      }
    })
  );
});
