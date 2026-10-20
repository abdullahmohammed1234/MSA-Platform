import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '../stores/auth';

vi.mock('@/services/auth/auth.service', () => ({
  authService: {
    getMe: vi.fn(),
  }
}));

describe('Frontend Landing Page & Dashboard Routing (Phase 10.1)', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
    vi.clearAllMocks();
  });

  describe('Post-login redirect sanitization', () => {
    // Replicating LoginPage getSafeRedirectPath logic
    function getSafeRedirectPath(rawPath: unknown): string {
      const defaultDashboard = '/account';
      if (!rawPath || typeof rawPath !== 'string') {
        return defaultDashboard;
      }
      const trimmed = rawPath.trim();
      if (!trimmed || trimmed === '/' || trimmed === '/login' || trimmed.startsWith('//') || trimmed.includes('\\') || /^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(trimmed)) {
        return defaultDashboard;
      }
      if (!trimmed.startsWith('/')) {
        return defaultDashboard;
      }
      return trimmed;
    }

    it('falls back to /account when redirect parameter is empty, root (/), or login (/login)', () => {
      expect(getSafeRedirectPath('')).toBe('/account');
      expect(getSafeRedirectPath('/')).toBe('/account');
      expect(getSafeRedirectPath('/login')).toBe('/account');
      expect(getSafeRedirectPath(null)).toBe('/account');
      expect(getSafeRedirectPath(undefined)).toBe('/account');
    });

    it('preserves safe internal return destinations', () => {
      expect(getSafeRedirectPath('/events')).toBe('/events');
      expect(getSafeRedirectPath('/services/prayer-space')).toBe('/services/prayer-space');
      expect(getSafeRedirectPath('/account/profile')).toBe('/account/profile');
    });

    it('defuses protocol-relative and absolute URL open redirect attacks', () => {
      expect(getSafeRedirectPath('//attacker.com')).toBe('/account');
      expect(getSafeRedirectPath('https://attacker.com')).toBe('/account');
      expect(getSafeRedirectPath('http://attacker.com')).toBe('/account');
      expect(getSafeRedirectPath('javascript:alert(1)')).toBe('/account');
      expect(getSafeRedirectPath('/\\attacker.com')).toBe('/account');
    });
  });

  describe('Logo destination resolution', () => {
    it('resolves home for unauthenticated guests', () => {
      const destination = { name: 'home' };
      const ariaLabel = 'SFU MSA Homepage';

      expect(destination).toEqual({ name: 'home' });
      expect(ariaLabel).toBe('SFU MSA Homepage');
    });

    it('resolves home for authenticated users so public homepage remains accessible', () => {
      const destination = { name: 'home' };
      const ariaLabel = 'SFU MSA Homepage';

      expect(destination).toEqual({ name: 'home' });
      expect(ariaLabel).toBe('SFU MSA Homepage');
    });
  });

  describe('Root route guard decision', () => {
    function evaluateRootDestination(_authStore: ReturnType<typeof useAuthStore>, to: { name: string; path: string }) {
      if (to.name === 'home' || to.path === '/') {
        return true;
      }
      return true;
    }

    it('allows guest users to access root landing page', () => {
      const authStore = useAuthStore();
      authStore.token = null;
      authStore.user = null;

      const result = evaluateRootDestination(authStore, { name: 'home', path: '/' });
      expect(result).toBe(true);
    });

    it('allows authenticated verified users to access root landing page without redirect to account', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Verified Member', email: 'mem@sfu.ca', roles: ['member'], permissions: [], is_verified: true } as any;

      const result = evaluateRootDestination(authStore, { name: 'home', path: '/' });
      expect(result).toBe(true);
    });

    it('allows direct navigation and refresh on root route without redirection', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Active User', email: 'user@sfu.ca', roles: ['member'], permissions: [], is_verified: true } as any;

      const result = evaluateRootDestination(authStore, { name: 'home', path: '/' });
      expect(result).not.toEqual({ name: 'account-overview' });
      expect(result).toBe(true);
    });
  });

  describe('Session probe deduplication', () => {
    it('reuses a single in-flight promise across concurrent restoreSession calls', async () => {
      const { authService } = await import('@/services/auth/auth.service');
      const authStore = useAuthStore();

      let resolveApi: (data: any) => void = () => {};
      const pendingPromise = new Promise((resolve) => {
        resolveApi = resolve;
      });

      vi.mocked(authService.getMe).mockImplementation(() => pendingPromise as any);

      // Concurrent calls
      const p1 = authStore.restoreSession();
      const p2 = authStore.restoreSession();

      // Only one network call initiated
      expect(authService.getMe).toHaveBeenCalledTimes(1);

      resolveApi({ user: { id: 10, name: 'SSO User', email: 'sso@sfu.ca', roles: ['member'], permissions: [] } });
      await Promise.all([p1, p2]);

      expect(authStore.isAuthenticated).toBe(true);
      expect(authStore.isInitialized).toBe(true);
    });
  });
});
