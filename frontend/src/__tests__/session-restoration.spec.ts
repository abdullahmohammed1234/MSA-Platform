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

  it('cookie-authenticated session restoration does not write sso_session to localStorage', async () => {
    vi.mocked(authService.getMe).mockResolvedValue({
      user: { id: 1, name: 'SSO User', email: 'sso@sfu.ca', roles: ['member'], permissions: [] },
    });

    const store = useAuthStore();
    expect(store.token).toBeNull();

    await store.restoreSession();

    expect(store.isAuthenticated).toBe(true);
    expect(store.user?.email).toBe('sso@sfu.ca');
    expect(store.token).toBe('sso_session');
    // Critical: sso_session must NOT be persisted in localStorage as a bearer token
    expect(localStorage.getItem('auth_token')).toBeNull();
  });

  it('API request interceptor suppresses sso_session from Authorization header', async () => {
    // Simulate legacy or external setting of sso_session in localStorage
    localStorage.setItem('auth_token', 'sso_session');

    // Run axios request interceptors
    const config = { headers: {} as Record<string, string> };
    const requestHandlers = (api.interceptors.request as any).handlers;

    let modifiedConfig = config;
    for (const handler of requestHandlers) {
      if (handler && handler.fulfilled) {
        modifiedConfig = await handler.fulfilled(modifiedConfig);
      }
    }

    expect(modifiedConfig.headers.Authorization).toBeUndefined();
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
