import { describe, it, expect, vi, beforeEach } from 'vitest';
import { roleGuard } from '../router/guards/roleGuard';
import { authGuard } from '../router/guards/authGuard';
import { guestGuard } from '../router/guards/guestGuard';
import { verificationGuard } from '../router/guards/verificationGuard';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '../stores/auth';

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

      expect(roleGuard(to)).toEqual({ name: 'academy-dashboard' });
    });

    it('should permit admin users to access admin-only routes', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Admin', email: 'admin@sfu.ca', roles: ['admin'], permissions: [], is_verified: true } as any;

      const to: any = {
        matched: [{ meta: { requiresAdmin: true } }]
      };

      expect(roleGuard(to)).toBe(true);
    });

    it('should redirect if user lacks required role', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Student', email: 'stu@sfu.ca', roles: ['volunteer'], permissions: [], is_verified: true } as any;

      const to: any = {
        matched: [{ meta: { roles: ['director'] } }]
      };

      expect(roleGuard(to)).toEqual({ name: 'home' });
    });
  });

  describe('authGuard', () => {
    it('should redirect unauthenticated users to login if route requiresAuth', () => {
      const authStore = useAuthStore();
      authStore.token = null;
      authStore.user = null;

      const to: any = {
        matched: [{ meta: { requiresAuth: true } }],
        fullPath: '/dashboard',
      };

      expect(authGuard(to)).toEqual({ name: 'login', query: { redirect: '/dashboard' } });
    });
  });

  describe('guestGuard', () => {
    it('should redirect unverified authenticated users to verify-email on guest-only routes', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Student', email: 'stu@sfu.ca', roles: ['volunteer'], permissions: [], is_verified: false } as any;

      const to: any = {
        matched: [{ meta: { guestOnly: true } }]
      };

      expect(guestGuard(to)).toEqual({ name: 'verify-email' });
    });

    it('should redirect authenticated users to dashboard if hitting guest-only routes', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Student', email: 'stu@sfu.ca', roles: ['volunteer'], permissions: [], is_verified: true } as any;

      const to: any = {
        matched: [{ meta: { guestOnly: true } }]
      };

      expect(guestGuard(to)).toEqual({ name: 'academy-dashboard' });
    });

    it('should redirect authenticated members to home on guest-only routes', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Member', email: 'mem@sfu.ca', roles: ['member'], permissions: [], is_verified: true } as any;

      const to: any = {
        matched: [{ meta: { guestOnly: true } }]
      };

      expect(guestGuard(to)).toEqual({ name: 'home' });
    });
  });

  describe('verificationGuard', () => {
    it('should redirect unverified users away from routes that require verification', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Student', email: 'stu@sfu.ca', roles: ['volunteer'], permissions: [], is_verified: false } as any;

      const to: any = {
        name: 'academy-dashboard',
        fullPath: '/academy/dashboard',
        matched: [{ meta: { requiresVerification: true } }]
      };

      expect(verificationGuard(to)).toEqual({
        name: 'verify-email',
        query: { redirect: '/academy/dashboard' }
      });
    });

    it('should allow unverified users to access the verify-email page', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Student', email: 'stu@sfu.ca', roles: ['volunteer'], permissions: [], is_verified: false } as any;

      const to: any = {
        name: 'verify-email',
        matched: [{ meta: { requiresAuth: true } }]
      };

      expect(verificationGuard(to)).toBe(true);
    });
  });
});
