import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '../stores/auth';
import { useNotificationsStore } from '../stores/notifications';
import { useCmsStore } from '../stores/cms';

// Mock Services
vi.mock('@/services/auth/auth.service', () => ({
  authService: {
    login: vi.fn().mockResolvedValue({ token: 'mock-token', user: { id: 1, name: 'Admin', email: 'a@sfu.ca', roles: ['admin'], permissions: ['manage_courses'] } }),
    logout: vi.fn().mockResolvedValue({}),
    getMe: vi.fn().mockResolvedValue({ user: { id: 1, name: 'Admin', email: 'a@sfu.ca', roles: ['admin'], permissions: ['manage_courses'] } })
  }
}));

vi.mock('@/services/notifications', () => ({
  notificationsService: {
    getNotifications: vi.fn().mockResolvedValue({ data: [], current_page: 1, last_page: 1, total: 0, per_page: 15 }),
    getUnread: vi.fn().mockResolvedValue({ unread_count: 5, latest_unread: [] }),
    markAsRead: vi.fn().mockResolvedValue({}),
    markAllAsRead: vi.fn().mockResolvedValue({}),
    deleteNotification: vi.fn().mockResolvedValue({}),
    getPreferences: vi.fn().mockResolvedValue({ channels: ['email', 'in_app'] }),
    updatePreferences: vi.fn().mockResolvedValue({ preferences: { channels: ['email'] } })
  }
}));

vi.mock('@/services/cms/cmsService', () => ({
  default: {
    getDashboard: vi.fn().mockResolvedValue({ stats: { pages: 10, images: 20 }, recentLogs: [] }),
    getHomepageSections: vi.fn().mockResolvedValue([]),
    updateHomepageSection: vi.fn().mockResolvedValue({}),
    getMedia: vi.fn().mockResolvedValue({ data: [] }),
    uploadMedia: vi.fn().mockResolvedValue({ uuid: '123' }),
    deleteMedia: vi.fn().mockResolvedValue({})
  }
}));

describe('Pinia Stores Unit Tests', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
  });

  describe('Auth Store', () => {
    it('should initialize with null token and user', () => {
      const store = useAuthStore();
      expect(store.token).toBeNull();
      expect(store.user).toBeNull();
      expect(store.isAuthenticated).toBe(false);
    });

    it('should set authenticated states on successful login', async () => {
      const store = useAuthStore();
      await store.login({ email: 'a@sfu.ca', password: 'password' });

      expect(store.token).toBe('mock-token');
      expect(store.isAuthenticated).toBe(true);
      expect(store.isAdmin).toBe(true);
      expect(store.canManageCourses).toBe(true);
    });

    it('should clear states on logout', async () => {
      const store = useAuthStore();
      await store.login({ email: 'a@sfu.ca', password: 'password' });
      expect(store.isAuthenticated).toBe(true);

      await store.logout();
      expect(store.token).toBeNull();
      expect(store.isAuthenticated).toBe(false);
    });
  });

  describe('Notifications Store', () => {
    it('should fetch unread notifications count', async () => {
      const store = useNotificationsStore();
      await store.fetchUnread();

      expect(store.unreadCount).toBe(5);
    });

    it('should add and remove toasts in UI feedback layer', () => {
      const store = useNotificationsStore();
      expect(store.toasts.length).toBe(0);

      store.addToast('Test message', 'success', 0);
      expect(store.toasts.length).toBe(1);
      expect(store.toasts[0].message).toBe('Test message');

      const id = store.toasts[0].id;
      store.removeToast(id);
      expect(store.toasts.length).toBe(0);
    });
  });

  describe('CMS Store', () => {
    it('should fetch dashboard statistics', async () => {
      const store = useCmsStore();
      await store.fetchDashboard(true);

      expect(store.stats).toEqual({ pages: 10, images: 20 });
    });
  });
});
