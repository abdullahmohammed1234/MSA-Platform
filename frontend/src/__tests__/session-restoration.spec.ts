import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '@/stores/auth';
import { authService } from '@/services/auth/auth.service';
import api from '@/services/api';

vi.mock('@/services/auth/auth.service', () => ({
  authService: {
    login: vi.fn(),
    logout: vi.fn(),
    getMe: vi.fn(),
  },
}));

describe('Frontend Session Restoration & sso_session Sentinel Handling', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
    vi.mocked(authService.getMe).mockReset();
    vi.mocked(authService.login).mockReset();
    vi.mocked(authService.logout).mockReset();
    vi.spyOn(console, 'warn').mockImplementation(() => {});
    vi.spyOn(console, 'error').mockImplementation(() => {});
  });

  it('restores session from persisted auth_token on page reload', async () => {
    localStorage.setItem('auth_token', 'persisted-token-123');
    vi.mocked(authService.getMe).mockResolvedValue({
      user: { id: 1, name: 'Test User', email: 'user@sfu.ca', roles: ['member'], permissions: [] },
    });

    const store = useAuthStore();
    await store.restoreSession();

    expect(store.isAuthenticated).toBe(true);
    expect(store.user?.email).toBe('user@sfu.ca');
    expect(store.token).toBe('persisted-token-123');
    expect(localStorage.getItem('auth_token')).toBe('persisted-token-123');
  });

  it('genuine bearer token is attached by API request interceptor to /auth/me and protected routes', async () => {
    localStorage.setItem('auth_token', 'genuine-sanctum-token-12345');

    const config = { headers: {} as Record<string, string>, url: '/api/v1/auth/me' };
    const requestHandlers = (api.interceptors.request as any).handlers;

    let modifiedConfig = config;
    for (const handler of requestHandlers) {
      if (handler && handler.fulfilled) {
        modifiedConfig = await handler.fulfilled(modifiedConfig);
      }
    }

    expect(modifiedConfig.headers.Authorization).toBe('Bearer genuine-sanctum-token-12345');
  });

  it('genuine bearer token is attached by API request interceptor', async () => {
    localStorage.setItem('auth_token', 'genuine-sanctum-token-12345');

    const config = { headers: {} as Record<string, string> };
    const requestHandlers = (api.interceptors.request as any).handlers;

    let modifiedConfig = config;
    for (const handler of requestHandlers) {
      if (handler && handler.fulfilled) {
        modifiedConfig = await handler.fulfilled(modifiedConfig);
      }
    }

    expect(modifiedConfig.headers.Authorization).toBe('Bearer genuine-sanctum-token-12345');
  });

  it('failed session restoration clears local authentication state and returns null', async () => {
    vi.mocked(authService.getMe).mockRejectedValue(new Error('Unauthenticated'));

    const store = useAuthStore();
    const result = await store.restoreSession();

    expect(result).toBeNull();
    expect(store.isAuthenticated).toBe(false);
    expect(store.user).toBeNull();
    expect(store.token).toBeNull();
    expect(localStorage.getItem('auth_user')).toBeNull();
    expect(localStorage.getItem('auth_token')).toBeNull();
  });
});
