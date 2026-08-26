import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '../stores/auth';
import { useCmsAccessStore } from '../stores/cms/cmsAccess';
import { useDamsAccessStore } from '../stores/dams/damsAccess';
import { cmsGuard } from '../router/guards/cmsGuard';
import { damsGuard } from '../router/guards/damsGuard';
import client from '@/services/api';
import type { RouteLocationNormalized } from 'vue-router';

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
  },
}));

describe('CMS and DAMS First-Class Applications Integration Tests', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  describe('CmsAccess Store', () => {
    it('should initialize as unresolved and empty', () => {
      const store = useCmsAccessStore();
      expect(store.isResolved).toBe(false);
      expect(store.profile).toBeNull();
      expect(store.hasCmsAccess).toBe(false);
    });

    it('should reset and return null if no token is present', async () => {
      const authStore = useAuthStore();
      authStore.token = null;

      const store = useCmsAccessStore();
      const result = await store.resolve();

      expect(result).toBeNull();
      expect(store.isResolved).toBe(true);
    });

    it('should resolve and grant access if backend returns positive has_cms_access status', async () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';

      const mockProfile = {
        id: 10,
        uuid: 'cms-uuid-123',
        name: 'CMS Editor',
        email: 'cms@sfu.ca',
        permissions: ['manage_homepage', 'manage_media'],
        roles: [{ slug: 'cms-editor', name: 'CMS Editor' }],
        has_cms_access: true,
      };

      vi.mocked(client.get).mockResolvedValueOnce({
        data: { data: mockProfile },
      });

      const store = useCmsAccessStore();
      const result = await store.resolve();

      expect(result).toEqual(mockProfile);
      expect(store.hasCmsAccess).toBe(true);
      expect(store.can('manage_homepage')).toBe(true);
      expect(store.can('manage_announcements')).toBe(false);
      expect(store.canAny(['manage_announcements', 'manage_media'])).toBe(true);
    });
  });

  describe('DamsAccess Store', () => {
    it('should resolve and grant access if backend returns positive has_dams_access status', async () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';

      const mockProfile = {
        id: 11,
        uuid: 'dams-uuid-123',
        name: 'DAMS Operator',
        email: 'dams@sfu.ca',
        permissions: ['manage_courses'],
        roles: [{ slug: 'dams-operator', name: 'DAMS Operator' }],
        has_dams_access: true,
      };

      vi.mocked(client.get).mockResolvedValueOnce({
        data: { data: mockProfile },
      });

      const store = useDamsAccessStore();
      const result = await store.resolve();

      expect(result).toEqual(mockProfile);
      expect(store.hasDamsAccess).toBe(true);
      expect(store.can('manage_courses')).toBe(true);
      expect(store.can('manage_lessons')).toBe(false);
    });
  });

  describe('cmsGuard Router Guard', () => {
    it('should bypass routes that do not start with /cms', async () => {
      const to = { path: '/admin/roles', matched: [], meta: {} } as unknown as RouteLocationNormalized;
      const result = await cmsGuard(to);
      expect(result).toBe(true);
    });

    it('should bypass public CMS routes', async () => {
      const to = { path: '/cms/unauthorized', matched: [], meta: { cmsPublic: true } } as unknown as RouteLocationNormalized;
      const result = await cmsGuard(to);
      expect(result).toBe(true);
    });

    it('should redirect to login if user is unauthenticated', async () => {
      const authStore = useAuthStore();
      authStore.token = null;

      const to = { path: '/cms/homepage', fullPath: '/cms/homepage', matched: [], meta: {} } as unknown as RouteLocationNormalized;
      const result = await cmsGuard(to);
      expect(result).toEqual({ name: 'login', query: { redirect: '/cms/homepage' } });
    });

    it('should redirect to unauthorized if user has no CMS access', async () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';

      vi.mocked(client.get).mockResolvedValueOnce({
        data: { data: { has_cms_access: false, permissions: [] } },
      });

      const to = { path: '/cms/homepage', fullPath: '/cms/homepage', matched: [], meta: {} } as unknown as RouteLocationNormalized;
      const result = await cmsGuard(to);
      expect(result).toEqual({ name: 'cms-unauthorized', query: { from: '/cms/homepage' } });
    });

    it('should redirect to unauthorized if user lacks required route permission', async () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';

      vi.mocked(client.get).mockResolvedValueOnce({
        data: { data: { has_cms_access: true, permissions: ['manage_homepage'] } },
      });

      const to = {
        path: '/cms/announcements',
        fullPath: '/cms/announcements',
        matched: [{ meta: { cmsPermissions: 'manage_announcements' } }],
        meta: { cmsPermissions: 'manage_announcements' },
      } as unknown as RouteLocationNormalized;

      const result = await cmsGuard(to);
      expect(result).toEqual({ name: 'cms-unauthorized', query: { from: '/cms/announcements' } });
    });

    it('should allow navigation if user has required route permission', async () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';

      vi.mocked(client.get).mockResolvedValueOnce({
        data: { data: { has_cms_access: true, permissions: ['manage_homepage'] } },
      });

      const to = {
        path: '/cms/homepage',
        fullPath: '/cms/homepage',
        matched: [{ meta: { cmsPermissions: 'manage_homepage' } }],
        meta: { cmsPermissions: 'manage_homepage' },
      } as unknown as RouteLocationNormalized;

      const result = await cmsGuard(to);
      expect(result).toBe(true);
    });
  });

  describe('damsGuard Router Guard', () => {
    it('should allow navigation if user has required DAMS permission', async () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';

      vi.mocked(client.get).mockResolvedValueOnce({
        data: { data: { has_dams_access: true, permissions: ['manage_courses'] } },
      });

      const to = {
        path: '/dams/courses',
        fullPath: '/dams/courses',
        matched: [{ meta: { damsPermissions: 'manage_courses' } }],
        meta: { damsPermissions: 'manage_courses' },
      } as unknown as RouteLocationNormalized;

      const result = await damsGuard(to);
      expect(result).toBe(true);
    });
  });
});
