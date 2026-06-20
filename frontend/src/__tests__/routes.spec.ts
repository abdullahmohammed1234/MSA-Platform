import { describe, it, expect, vi, beforeEach } from 'vitest';
import { roleGuard } from '../router/guards/roleGuard';
import { authGuard } from '../router/guards/authGuard';
import { guestGuard } from '../router/guards/guestGuard';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '../stores/auth';

// Mock router imports
vi.mock('@/services/auth/auth.service', () => ({
  authService: {}
}));

describe('Vue Router Navigation Guards', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
  });

  describe('roleGuard', () => {
    it('should redirect non-admin to academy dashboard if route requiresAdmin', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Student', email: 'stu@sfu.ca', roles: ['volunteer'], permissions: [], is_verified: true } as any;

      const to: any = {
        matched: [{ meta: { requiresAdmin: true } }]
      };
      const next = vi.fn();

      roleGuard(to, {} as any, next);

      expect(next).toHaveBeenCalledWith({ name: 'academy-dashboard' });
    });

    it('should permit admin users to access admin-only routes', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Admin', email: 'admin@sfu.ca', roles: ['admin'], permissions: [], is_verified: true } as any;

      const to: any = {
        matched: [{ meta: { requiresAdmin: true } }]
      };
      const next = vi.fn();

      roleGuard(to, {} as any, next);

      expect(next).toHaveBeenCalledWith();
    });

    it('should redirect if user lacks required role', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Student', email: 'stu@sfu.ca', roles: ['volunteer'], permissions: [], is_verified: true } as any;

      const to: any = {
        matched: [{ meta: { roles: ['director'] } }]
      };
      const next = vi.fn();

      roleGuard(to, {} as any, next);

      expect(next).toHaveBeenCalledWith({ name: 'home' });
    });
  });

  describe('authGuard', () => {
    it('should redirect unauthenticated users to login if route requiresAuth', () => {
      const authStore = useAuthStore();
      authStore.token = null;
      authStore.user = null;

      const to: any = {
        matched: [{ meta: { requiresAuth: true } }]
      };
      const next = vi.fn();

      authGuard(to, {} as any, next);

      expect(next).toHaveBeenCalledWith({ name: 'login', query: { redirect: undefined } });
    });
  });

  describe('guestGuard', () => {
    it('should redirect authenticated users to dashboard if hitting guest-only routes', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Student', email: 'stu@sfu.ca', roles: ['volunteer'], permissions: [], is_verified: true } as any;

      const to: any = {
        matched: [{ meta: { guestOnly: true } }]
      };
      const next = vi.fn();

      guestGuard(to, {} as any, next);

      expect(next).toHaveBeenCalledWith({ name: 'academy-dashboard' });
    });
  it('should redirect authenticated members to home on guest-only routes', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Member', email: 'mem@sfu.ca', roles: ['member'], permissions: [], is_verified: true } as any;

      const to: any = {
        matched: [{ meta: { guestOnly: true } }]
      };
      const next = vi.fn();

      guestGuard(to, {} as any, next);

      expect(next).toHaveBeenCalledWith({ name: 'home' });
    });
  });
});
