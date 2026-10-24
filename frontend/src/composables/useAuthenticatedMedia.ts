import { ref, watch, onUnmounted, getCurrentInstance, unref, type Ref } from 'vue';
import axios from 'axios';
import api from '@/services/api';
import { resolveCmsMediaUrl } from '@/constants/publicAssets';

interface CacheEntry {
  blobUrl: string;
  refCount: number;
  sessionGeneration: number;
}

interface InFlightEntry {
  promise: Promise<string>;
  abortController: AbortController;
  subscribers: Set<number>;
  sessionGeneration: number;
}

// In-memory session cache for authenticated media Blobs to prevent redundant network fetches
const blobCache = new Map<string, CacheEntry>();

// In-flight request deduplication map to prevent redundant concurrent fetches
const inFlightRequests = new Map<string, InFlightEntry>();

// Monotonically increasing session generation counter.
// Incremented on logout, session reset, or unauthorized event.
let currentSessionGeneration = 0;

// Monotonically increasing subscriber ID for composables
let nextSubscriberId = 1;

// Set of active composable instance cleanup callbacks to clear visible protected media on session invalidation
const activeInstanceResetCallbacks = new Set<() => void>();

/**
 * Determine if a URL requires Bearer token authentication.
 */
export function isProtectedMediaUrl(url: string | null | undefined): boolean {
  if (!url) return false;
  return (
    (url.includes('/api/v1/website/media/') && url.includes('/file')) ||
    (url.includes('/api/v1/store/products/') && url.includes('/images/'))
  );
}

/**
 * Explicitly revoke and clear all cached media object URLs and abort in-flight fetches.
 * Invoked on logout, 401 unauthorized, or account switching.
 */
export function clearAuthenticatedMediaCache(): void {
  currentSessionGeneration += 1;

  // 1. Abort all in-flight requests and clear registry
  for (const inFlight of inFlightRequests.values()) {
    try {
      inFlight.abortController.abort();
    } catch {
      // Ignore
    }
  }
  inFlightRequests.clear();

  // 2. Revoke all cached object URLs and clear blob cache
  for (const entry of blobCache.values()) {
    try {
      URL.revokeObjectURL(entry.blobUrl);
    } catch {
      // Ignore if already revoked
    }
  }
  blobCache.clear();

  // 3. Notify all active composable instances to clear visible protected media
  for (const resetCb of activeInstanceResetCallbacks) {
    try {
      resetCb();
    } catch {
      // Ignore
    }
  }
}

/**
 * Test & Diagnostics Inspection Helpers
 */
export function getAuthenticatedMediaCacheSize(): number {
  return blobCache.size;
}

export function getInFlightRequestsCount(): number {
  return inFlightRequests.size;
}

export function getSessionGeneration(): number {
  return currentSessionGeneration;
}

// Register global listeners for authentication teardown
if (typeof window !== 'undefined') {
  window.addEventListener('auth:logout', clearAuthenticatedMediaCache);
  window.addEventListener('auth:unauthorized', clearAuthenticatedMediaCache);
}

/**
 * Composable for securely fetching protected media assets using Sanctum Bearer tokens
 * without exposing tokens in query parameters or URL attributes.
 */
export function useAuthenticatedMedia(source: Ref<string | null | undefined> | string | null | undefined) {
  const subscriberId = nextSubscriberId++;
  const mediaSrc = ref<string>('');
  const isLoading = ref<boolean>(false);
  const isError = ref<boolean>(false);
  const isForbidden = ref<boolean>(false);

  let currentRequestId = 0;
  let activeCachedUrl: string | null = null;
  let isUnmounted = false;

  // Reset callback invoked if session is invalidated while this composable holds protected media
  const resetIfProtected = () => {
    if (activeCachedUrl) {
      activeCachedUrl = null;
      mediaSrc.value = '';
      isLoading.value = false;
      isError.value = false;
      isForbidden.value = false;
    }
  };
  activeInstanceResetCallbacks.add(resetIfProtected);

  const releaseCurrentCacheRef = () => {
    if (activeCachedUrl) {
      const urlToRelease = activeCachedUrl;
      activeCachedUrl = null;

      // Remove subscription from in-flight request if still downloading
      const inFlight = inFlightRequests.get(urlToRelease);
      if (inFlight) {
        inFlight.subscribers.delete(subscriberId);
        if (inFlight.subscribers.size === 0) {
          inFlight.abortController.abort();
          inFlightRequests.delete(urlToRelease);
        }
      }

      // Decrement reference count in blob cache if completed
      if (blobCache.has(urlToRelease)) {
        const entry = blobCache.get(urlToRelease)!;
        entry.refCount = Math.max(0, entry.refCount - 1);
        if (entry.refCount === 0) {
          try {
            URL.revokeObjectURL(entry.blobUrl);
          } catch {
            // Ignore
          }
          blobCache.delete(urlToRelease);
        }
      }
    }
  };

  const loadMedia = async (rawUrl: string | null | undefined) => {
    const requestId = ++currentRequestId;

    releaseCurrentCacheRef();

    if (!rawUrl) {
      mediaSrc.value = '';
      isLoading.value = false;
      isError.value = false;
      isForbidden.value = false;
      return;
    }

    const resolvedUrl = resolveCmsMediaUrl(rawUrl);

    if (!isProtectedMediaUrl(resolvedUrl)) {
      // Public asset (static image, public uploads, or external CDN)
      mediaSrc.value = resolvedUrl;
      isLoading.value = false;
      isError.value = false;
      isForbidden.value = false;
      return;
    }

    const sessionGen = currentSessionGeneration;

    // Check if we already have an active blob in current session for this exact protected URL
    if (blobCache.has(resolvedUrl)) {
      const entry = blobCache.get(resolvedUrl)!;
      if (entry.sessionGeneration === currentSessionGeneration) {
        entry.refCount += 1;
        activeCachedUrl = resolvedUrl;
        mediaSrc.value = entry.blobUrl;
        isLoading.value = false;
        isError.value = false;
        isForbidden.value = false;
        return;
      } else {
        // Stale session entry - revoke and remove
        try {
          URL.revokeObjectURL(entry.blobUrl);
        } catch {
          // Ignore
        }
        blobCache.delete(resolvedUrl);
      }
    }

    // Protected endpoint requires authenticated fetch with Authorization: Bearer
    isLoading.value = true;
    isError.value = false;
    isForbidden.value = false;
    activeCachedUrl = resolvedUrl;

    let inFlight = inFlightRequests.get(resolvedUrl);

    if (inFlight && inFlight.sessionGeneration === currentSessionGeneration) {
      inFlight.subscribers.add(subscriberId);
    } else {
      if (inFlight) {
        // Abort obsolete session in-flight
        try {
          inFlight.abortController.abort();
        } catch {
          // Ignore
        }
        inFlightRequests.delete(resolvedUrl);
      }

      const abortController = new AbortController();
      const subscribers = new Set<number>([subscriberId]);

      const promise = (async () => {
        try {
          const response = await api.get(resolvedUrl, {
            responseType: 'blob',
            signal: abortController.signal,
          });

          // If session was cleared/switched, request was aborted, or all subscribers left:
          if (
            abortController.signal.aborted ||
            sessionGen !== currentSessionGeneration ||
            subscribers.size === 0
          ) {
            throw new Error('Aborted');
          }

          const blob = response.data;
          const blobUrl = URL.createObjectURL(blob);

          blobCache.set(resolvedUrl, {
            blobUrl,
            refCount: 0, // Incremented by subscribers upon resolution
            sessionGeneration: sessionGen,
          });

          return blobUrl;
        } finally {
          inFlightRequests.delete(resolvedUrl);
        }
      })();

      inFlight = {
        promise,
        abortController,
        subscribers,
        sessionGeneration: sessionGen,
      };
      inFlightRequests.set(resolvedUrl, inFlight);
    }

    try {
      await inFlight.promise;

      // Ensure component is still mounted, source didn't change, and session didn't invalidate
      if (isUnmounted || requestId !== currentRequestId || sessionGen !== currentSessionGeneration) {
        if (activeCachedUrl === resolvedUrl) {
          activeCachedUrl = null;
        }
        return;
      }

      const cached = blobCache.get(resolvedUrl);
      if (cached && cached.sessionGeneration === currentSessionGeneration) {
        cached.refCount += 1;
        mediaSrc.value = cached.blobUrl;
        isLoading.value = false;
        isError.value = false;
        isForbidden.value = false;
      } else {
        activeCachedUrl = null;
        mediaSrc.value = '';
        isLoading.value = false;
      }
    } catch (err: any) {
      if (isUnmounted || requestId !== currentRequestId) {
        return;
      }

      if (activeCachedUrl === resolvedUrl) {
        activeCachedUrl = null;
      }

      if (
        axios.isCancel(err) ||
        err?.name === 'CanceledError' ||
        err?.name === 'AbortError' ||
        err?.message === 'Session invalidated'
      ) {
        return;
      }

      isLoading.value = false;
      isError.value = true;
      mediaSrc.value = '';

      if (err?.response?.status === 401 || err?.response?.status === 403) {
        isForbidden.value = true;
      }
    }
  };

  watch(
    () => unref(source),
    (newVal) => {
      loadMedia(newVal);
    },
    { immediate: true }
  );

  const dispose = () => {
    isUnmounted = true;
    activeInstanceResetCallbacks.delete(resetIfProtected);
    releaseCurrentCacheRef();
  };

  if (getCurrentInstance()) {
    onUnmounted(dispose);
  }

  return {
    mediaSrc,
    isLoading,
    isError,
    isForbidden,
    dispose,
  };
}
