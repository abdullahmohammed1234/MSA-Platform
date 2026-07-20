import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { authGuard } from '../router/guards/authGuard';

describe('Academy feature flags', () => {
  beforeEach(() => {
    vi.resetModules();
  });

  afterEach(() => {
    vi.unstubAllEnvs();
  });

  describe('academyGuard', () => {
    it('redirects academy routes when academy is disabled', async () => {
      vi.stubEnv('VITE_ACADEMY_ENABLED', 'false');
      const { academyGuard: guard } = await import('../router/guards/academyGuard');

      const to: any = { path: '/academy/dashboard', matched: [] };
      expect(guard(to)).toEqual({ name: 'home' });
    });

    it('allows academy routes when academy is enabled', async () => {
      vi.stubEnv('VITE_ACADEMY_ENABLED', 'true');
      const { academyGuard: guard } = await import('../router/guards/academyGuard');

      const to: any = { path: '/academy/dashboard', matched: [] };
      expect(guard(to)).toBe(true);
    });
  });

  describe('publicAuthGuard', () => {
    it('redirects public auth routes when academy is disabled', async () => {
      vi.stubEnv('VITE_ACADEMY_ENABLED', 'false');
      const { publicAuthGuard: guard } = await import('../router/guards/publicAuthGuard');

      const to: any = { matched: [{ meta: { publicAuth: true } }] };
      expect(guard(to)).toEqual({ name: 'home' });
    });

    it('allows public auth routes when academy is enabled', async () => {
      vi.stubEnv('VITE_ACADEMY_ENABLED', 'true');
      const { publicAuthGuard: guard } = await import('../router/guards/publicAuthGuard');

      const to: any = { matched: [{ meta: { publicAuth: true } }] };
      expect(guard(to)).toBe(true);
    });
  });
});

describe('authGuard admin login redirect', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('redirects unauthenticated admin routes to /admin login', () => {
    const to: any = {
      matched: [{ meta: { requiresAuth: true, requiresAdmin: true } }],
      fullPath: '/admin/roles',
    };

    expect(authGuard(to)).toEqual({
      name: 'admin-dashboard',
      query: { redirect: '/admin/roles' },
    });
  });
});
