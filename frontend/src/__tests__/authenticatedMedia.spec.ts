import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { ref, nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import {
  isProtectedMediaUrl,
  useAuthenticatedMedia,
  clearAuthenticatedMediaCache,
  getAuthenticatedMediaCacheSize,
  getInFlightRequestsCount,
} from '@/composables/useAuthenticatedMedia';
import { resolveCmsMediaUrl } from '@/constants/publicAssets';
import SecureMedia from '@/components/shared/SecureMedia.vue';
import api from '@/services/api';

describe('Authenticated Media Security & Lifecycle Audit (Phase 43.1)', () => {
  beforeEach(() => {
    localStorage.clear();
    clearAuthenticatedMediaCache();
    vi.restoreAllMocks();
  });

  afterEach(() => {
    clearAuthenticatedMediaCache();
    localStorage.clear();
  });

  describe('Gate A: Token Security & Public Resolution', () => {
    it('never appends token query parameters to protected media endpoints', () => {
      localStorage.setItem('auth_token', 'secret-sanctum-bearer-token');

      const protectedUrl = '/api/v1/website/media/123e4567-e89b-12d3-a456-426614174000/file';
      const resolved = resolveCmsMediaUrl(protectedUrl);

      expect(resolved).not.toContain('token=');
      expect(resolved).not.toContain('secret-sanctum-bearer-token');
      expect(resolved).toContain('/api/v1/website/media/123e4567-e89b-12d3-a456-426614174000/file');
    });

    it('resolves store product private image endpoint without tokens', () => {
      localStorage.setItem('auth_token', 'secret-sanctum-bearer-token');

      const productImg = '/api/v1/store/products/prod-uuid/images/img-uuid';
      const resolved = resolveCmsMediaUrl(productImg);

      expect(resolved).not.toContain('token=');
      expect(resolved).toContain('/api/v1/store/products/prod-uuid/images/img-uuid');
    });

    it('preserves public storage URLs without adding tokens', () => {
      localStorage.setItem('auth_token', 'secret-sanctum-bearer-token');

      const publicPath = '/storage/uploads/cms/open-campus.jpg';
      const resolved = resolveCmsMediaUrl(publicPath);

      expect(resolved).not.toContain('token=');
      expect(resolved).toContain('/storage/uploads/cms/open-campus.jpg');
    });

    it('accurately identifies protected endpoints vs public assets', () => {
      expect(isProtectedMediaUrl('/api/v1/website/media/test-uuid/file')).toBe(true);
      expect(isProtectedMediaUrl('http://localhost:8000/api/v1/website/media/test-uuid/file')).toBe(true);
      expect(isProtectedMediaUrl('/api/v1/store/products/prod-1/images/img-1')).toBe(true);

      expect(isProtectedMediaUrl('/storage/uploads/cms/photo.jpg')).toBe(false);
      expect(isProtectedMediaUrl('/Media/Events/halqa.webp')).toBe(false);
      expect(isProtectedMediaUrl(null)).toBe(false);
      expect(isProtectedMediaUrl('')).toBe(false);
    });
  });

  describe('Gate B: Request Concurrency & Cancellation', () => {
    it('Scenario 1: Rapid source changes ignore obsolete responses and do not overwrite current source', async () => {
      let resolveA: (val: any) => void = () => {};
      let resolveB: (val: any) => void = () => {};

      const promiseA = new Promise((res) => { resolveA = res; });
      const promiseB = new Promise((res) => { resolveB = res; });

      vi.spyOn(api, 'get').mockImplementation((url: string) => {
        if (url.includes('media-A')) return promiseA;
        if (url.includes('media-B')) return promiseB;
        return Promise.reject(new Error('Unknown URL'));
      });

      const mockCreateObjectURL = vi.fn().mockImplementation((blob: Blob) => {
        return (blob as any).name === 'blobB' ? 'blob:http://localhost/b' : 'blob:http://localhost/a';
      });
      vi.stubGlobal('URL', {
        createObjectURL: mockCreateObjectURL,
        revokeObjectURL: vi.fn(),
      });

      const source = ref('/api/v1/website/media/media-A/file');
      const { mediaSrc, isLoading } = useAuthenticatedMedia(source);

      expect(isLoading.value).toBe(true);

      // Rapidly switch source from A to B before A completes
      source.value = '/api/v1/website/media/media-B/file';
      await nextTick();

      // B finishes first
      const blobB = new Blob(['content-B'], { type: 'image/jpeg' });
      (blobB as any).name = 'blobB';
      resolveB({ data: blobB });

      await nextTick();
      await nextTick();

      expect(mediaSrc.value).toBe('blob:http://localhost/b');
      expect(isLoading.value).toBe(false);

      // Now A resolves late
      const blobA = new Blob(['content-A'], { type: 'image/jpeg' });
      (blobA as any).name = 'blobA';
      resolveA({ data: blobA });

      await nextTick();
      await nextTick();

      // Late response from A must NOT overwrite mediaSrc!
      expect(mediaSrc.value).toBe('blob:http://localhost/b');
    });

    it('Scenario 2: Unmounting or disposing during loading aborts and leaks no object URL', async () => {
      let resolveReq: (val: any) => void = () => {};
      const pendingPromise = new Promise((res) => { resolveReq = res; });

      let capturedSignal: AbortSignal | undefined;
      vi.spyOn(api, 'get').mockImplementation((_url: string, config: any) => {
        capturedSignal = config?.signal;
        return pendingPromise;
      });

      const mockRevoke = vi.fn();
      vi.stubGlobal('URL', {
        createObjectURL: vi.fn().mockReturnValue('blob:http://localhost/unmounted-blob'),
        revokeObjectURL: mockRevoke,
      });

      const source = ref('/api/v1/website/media/unmount-test/file');
      const instance = useAuthenticatedMedia(source);

      expect(getInFlightRequestsCount()).toBe(1);

      // Component unmounts
      instance.dispose();

      // Abort controller was triggered
      expect(capturedSignal?.aborted).toBe(true);
      expect(getInFlightRequestsCount()).toBe(0);

      // If network somehow resolves after unmount
      resolveReq({ data: new Blob(['data'], { type: 'image/jpeg' }) });
      await nextTick();
      await nextTick();

      expect(instance.mediaSrc.value).toBe('');
      expect(getAuthenticatedMediaCacheSize()).toBe(0);
    });

    it('Scenario 3: Concurrent consumers requesting same URL share the in-flight promise without duplicate fetch', async () => {
      let resolveShared: (val: any) => void = () => {};
      const sharedPromise = new Promise((res) => { resolveShared = res; });

      const getSpy = vi.spyOn(api, 'get').mockReturnValue(sharedPromise);

      vi.stubGlobal('URL', {
        createObjectURL: vi.fn().mockReturnValue('blob:http://localhost/shared-blob'),
        revokeObjectURL: vi.fn(),
      });

      const source1 = ref('/api/v1/website/media/shared-concurrent/file');
      const source2 = ref('/api/v1/website/media/shared-concurrent/file');

      // Both instances request the same URL concurrently
      const instance1 = useAuthenticatedMedia(source1);
      const instance2 = useAuthenticatedMedia(source2);

      // Exactly ONE network request was made
      expect(getSpy).toHaveBeenCalledTimes(1);
      expect(getInFlightRequestsCount()).toBe(1);

      // Now resolve the single network response
      resolveShared({ data: new Blob(['shared-bytes'], { type: 'image/jpeg' }) });

      await nextTick();
      await nextTick();

      // Both instances received the exact same Blob URL
      expect(instance1.mediaSrc.value).toBe('blob:http://localhost/shared-blob');
      expect(instance2.mediaSrc.value).toBe('blob:http://localhost/shared-blob');
      expect(getInFlightRequestsCount()).toBe(0);
      expect(getAuthenticatedMediaCacheSize()).toBe(1);

      instance1.dispose();
      instance2.dispose();
    });

    it('Scenario 3B: Safe reference counting prevents premature revocation between concurrent consumers', async () => {
      const fakeBlob = new Blob(['shared-bytes'], { type: 'image/jpeg' });
      vi.spyOn(api, 'get').mockResolvedValue({ data: fakeBlob });

      const mockRevoke = vi.fn();
      vi.stubGlobal('URL', {
        createObjectURL: vi.fn().mockReturnValue('blob:http://localhost/refcount-blob'),
        revokeObjectURL: mockRevoke,
      });

      const source1 = ref('/api/v1/website/media/refcount-test/file');
      const source2 = ref('/api/v1/website/media/refcount-test/file');

      const instance1 = useAuthenticatedMedia(source1);
      const instance2 = useAuthenticatedMedia(source2);

      await nextTick();
      await nextTick();

      expect(instance1.mediaSrc.value).toBe('blob:http://localhost/refcount-blob');
      expect(instance2.mediaSrc.value).toBe('blob:http://localhost/refcount-blob');

      // Instance 1 disposes (e.g. lightbox closes, or one card unmounts)
      instance1.dispose();

      // Object URL must NOT be revoked because instance2 is still using it!
      expect(mockRevoke).not.toHaveBeenCalled();
      expect(getAuthenticatedMediaCacheSize()).toBe(1);
      expect(instance2.mediaSrc.value).toBe('blob:http://localhost/refcount-blob');

      // Instance 2 disposes
      instance2.dispose();

      // Now that all consumers released it, Object URL must be revoked!
      expect(mockRevoke).toHaveBeenCalledWith('blob:http://localhost/refcount-blob');
      expect(getAuthenticatedMediaCacheSize()).toBe(0);
    });

    it('Scenario 4: Session invalidation / logout during in-flight fetch discards response and prevents cache pollution', async () => {
      let resolveInFlight: (val: any) => void = () => {};
      const inFlightPromise = new Promise((res) => { resolveInFlight = res; });

      vi.spyOn(api, 'get').mockReturnValue(inFlightPromise);

      const mockCreateObjectURL = vi.fn().mockReturnValue('blob:http://localhost/stale-session-blob');
      vi.stubGlobal('URL', {
        createObjectURL: mockCreateObjectURL,
        revokeObjectURL: vi.fn(),
      });

      const source = ref('/api/v1/website/media/session-race/file');
      const instance = useAuthenticatedMedia(source);

      expect(getInFlightRequestsCount()).toBe(1);

      // User logs out while request was in-flight!
      clearAuthenticatedMediaCache();

      expect(getInFlightRequestsCount()).toBe(0);

      // Late network response arrives from previous session
      resolveInFlight({ data: new Blob(['confidential'], { type: 'image/jpeg' }) });

      await nextTick();
      await nextTick();

      // Prior session response was discarded!
      expect(instance.mediaSrc.value).toBe('');
      expect(getAuthenticatedMediaCacheSize()).toBe(0);
    });
  });

  describe('Gate C & D: Authentication Lifecycle & Blob Revocation', () => {
    it('clears visible protected media immediately when auth:logout event fires', async () => {
      const fakeBlob = new Blob(['private-media'], { type: 'image/jpeg' });
      vi.spyOn(api, 'get').mockResolvedValueOnce({ data: fakeBlob });

      const mockRevoke = vi.fn();
      vi.stubGlobal('URL', {
        createObjectURL: vi.fn().mockReturnValue('blob:http://localhost/user1-blob'),
        revokeObjectURL: mockRevoke,
      });

      const source = ref('/api/v1/website/media/authed-session/file');
      const instance = useAuthenticatedMedia(source);

      await nextTick();
      await nextTick();

      expect(instance.mediaSrc.value).toBe('blob:http://localhost/user1-blob');

      // Dispatch global logout event
      window.dispatchEvent(new CustomEvent('auth:logout'));

      // Protected media is immediately cleared from composable state
      expect(instance.mediaSrc.value).toBe('');
      expect(mockRevoke).toHaveBeenCalledWith('blob:http://localhost/user1-blob');
      expect(getAuthenticatedMediaCacheSize()).toBe(0);
    });

    it('clears visible protected media immediately when auth:unauthorized event fires', async () => {
      const fakeBlob = new Blob(['private-media'], { type: 'image/jpeg' });
      vi.spyOn(api, 'get').mockResolvedValueOnce({ data: fakeBlob });

      vi.stubGlobal('URL', {
        createObjectURL: vi.fn().mockReturnValue('blob:http://localhost/unauth-blob'),
        revokeObjectURL: vi.fn(),
      });

      const source = ref('/api/v1/website/media/unauth-test/file');
      const instance = useAuthenticatedMedia(source);

      await nextTick();
      await nextTick();

      expect(instance.mediaSrc.value).toBe('blob:http://localhost/unauth-blob');

      // Dispatch 401 unauthorized event
      window.dispatchEvent(new CustomEvent('auth:unauthorized'));

      expect(instance.mediaSrc.value).toBe('');
      expect(getAuthenticatedMediaCacheSize()).toBe(0);
    });

    it('resets mediaSrc and flags when source changes to null or empty', async () => {
      const fakeBlob = new Blob(['data'], { type: 'image/jpeg' });
      vi.spyOn(api, 'get').mockResolvedValueOnce({ data: fakeBlob });

      vi.stubGlobal('URL', {
        createObjectURL: vi.fn().mockReturnValue('blob:http://localhost/temp-blob'),
        revokeObjectURL: vi.fn(),
      });

      const source = ref<string | null>('/api/v1/website/media/temp/file');
      const instance = useAuthenticatedMedia(source);

      await nextTick();
      await nextTick();

      expect(instance.mediaSrc.value).toBe('blob:http://localhost/temp-blob');

      // Change source to null
      source.value = null;
      await nextTick();

      expect(instance.mediaSrc.value).toBe('');
      expect(instance.isLoading.value).toBe(false);
      expect(instance.isError.value).toBe(false);
      expect(instance.isForbidden.value).toBe(false);
      expect(getAuthenticatedMediaCacheSize()).toBe(0);
    });

    it('failed requests with 401 or 403 do not poison the cache', async () => {
      vi.spyOn(api, 'get').mockRejectedValueOnce({
        response: { status: 403, data: { message: 'Forbidden' } },
      });

      const source = ref('/api/v1/website/media/error-test/file');
      const instance = useAuthenticatedMedia(source);

      await nextTick();
      await nextTick();

      expect(instance.isLoading.value).toBe(false);
      expect(instance.isError.value).toBe(true);
      expect(instance.isForbidden.value).toBe(true);
      expect(instance.mediaSrc.value).toBe('');

      // Cache remains completely clean (not poisoned)
      expect(getAuthenticatedMediaCacheSize()).toBe(0);

      // Subsequent request for same URL can retry cleanly
      const retryBlob = new Blob(['now-allowed'], { type: 'image/jpeg' });
      vi.spyOn(api, 'get').mockResolvedValueOnce({ data: retryBlob });
      vi.stubGlobal('URL', {
        createObjectURL: vi.fn().mockReturnValue('blob:http://localhost/retry-success'),
        revokeObjectURL: vi.fn(),
      });

      source.value = '';
      await nextTick();
      source.value = '/api/v1/website/media/error-test/file';
      await nextTick();
      await nextTick();

      expect(instance.mediaSrc.value).toBe('blob:http://localhost/retry-success');
      expect(instance.isForbidden.value).toBe(false);
      expect(instance.isError.value).toBe(false);
    });

    it('handles network failure cleanly without setting isForbidden', async () => {
      vi.spyOn(api, 'get').mockRejectedValueOnce(new Error('Network Error'));

      const source = ref('/api/v1/website/media/network-fail/file');
      const instance = useAuthenticatedMedia(source);

      await nextTick();
      await nextTick();

      expect(instance.isLoading.value).toBe(false);
      expect(instance.isError.value).toBe(true);
      expect(instance.isForbidden.value).toBe(false); // Network error is not 401/403
      expect(instance.mediaSrc.value).toBe('');
    });
  });

  describe('Gate F: SecureMedia Component Integration', () => {
    it('renders public image directly as an <img> element without authenticated blob fetch', () => {
      const getSpy = vi.spyOn(api, 'get');

      const wrapper = mount(SecureMedia, {
        props: {
          src: '/storage/uploads/cms/public-banner.jpg',
          alt: 'Public Banner',
        },
      });

      const img = wrapper.find('img');
      expect(img.exists()).toBe(true);
      expect(img.attributes('src')).toContain('/storage/uploads/cms/public-banner.jpg');
      expect(img.attributes('alt')).toBe('Public Banner');
      expect(getSpy).not.toHaveBeenCalled();
    });

    it('renders protected video as a <video> element with controls and blob URL', async () => {
      const fakeBlob = new Blob(['video-bytes'], { type: 'video/mp4' });
      vi.spyOn(api, 'get').mockResolvedValueOnce({ data: fakeBlob });

      vi.stubGlobal('URL', {
        createObjectURL: vi.fn().mockReturnValue('blob:http://localhost/video-stream-blob'),
        revokeObjectURL: vi.fn(),
      });

      const wrapper = mount(SecureMedia, {
        props: {
          src: '/api/v1/website/media/lecture-vid/file',
          isVideo: true,
          controls: true,
          autoplay: false,
        },
      });

      await nextTick();
      await nextTick();

      const video = wrapper.find('video');
      expect(video.exists()).toBe(true);
      expect(video.attributes('src')).toBe('blob:http://localhost/video-stream-blob');
      expect(video.attributes('controls')).toBeDefined();
    });

    it('renders restricted fallback when protected media returns 403 Forbidden', async () => {
      vi.spyOn(api, 'get').mockRejectedValueOnce({
        response: { status: 403, data: { message: 'Restricted' } },
      });

      const wrapper = mount(SecureMedia, {
        props: {
          src: '/api/v1/website/media/restricted-brothers-only/file',
        },
      });

      await nextTick();
      await nextTick();

      expect(wrapper.text()).toContain('Restricted media');
      expect(wrapper.find('img').exists()).toBe(false);
      expect(wrapper.find('video').exists()).toBe(false);
    });
  });
});
