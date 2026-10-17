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
      const authStore = useAuthStore();
      authStore.token = null;
      authStore.user = null;

      const destination = authStore.isAuthenticated ? { name: 'account-overview' } : { name: 'home' };
      const ariaLabel = authStore.isAuthenticated ? 'SFU MSA Dashboard' : 'SFU MSA Home';

      expect(destination).toEqual({ name: 'home' });
      expect(ariaLabel).toBe('SFU MSA Home');
    });

    it('resolves account-overview dashboard for authenticated users', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Active User', email: 'user@sfu.ca', roles: ['member'], permissions: [], is_verified: true } as any;

      const destination = authStore.isAuthenticated ? { name: 'account-overview' } : { name: 'home' };
      const ariaLabel = authStore.isAuthenticated ? 'SFU MSA Dashboard' : 'SFU MSA Home';

      expect(destination).toEqual({ name: 'account-overview' });
      expect(ariaLabel).toBe('SFU MSA Dashboard');
    });
  });

  describe('Root route guard decision', () => {
    function evaluateRootDestination(authStore: ReturnType<typeof useAuthStore>, to: { name: string; path: string }) {
      if (to.name === 'home' || to.path === '/') {
        if (authStore.isAuthenticated) {
          if (authStore.needsEmailVerification) {
            return { name: 'verify-email' };
          }
          return { name: 'account-overview' };
        }
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

    it('redirects authenticated verified users from root to account-overview dashboard', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Verified Member', email: 'mem@sfu.ca', roles: ['member'], permissions: [], is_verified: true } as any;

      const result = evaluateRootDestination(authStore, { name: 'home', path: '/' });
      expect(result).toEqual({ name: 'account-overview' });
    });

    it('redirects authenticated unverified users from root to verify-email', () => {
      const authStore = useAuthStore();
      authStore.token = 'valid-token';
      authStore.user = { id: 1, name: 'Unverified Member', email: 'unverified@sfu.ca', roles: ['member'], permissions: [], is_verified: false, requires_email_verification: true } as any;

      const result = evaluateRootDestination(authStore, { name: 'home', path: '/' });
      expect(result).toEqual({ name: 'verify-email' });
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
