import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '@/stores/auth';
import { authService } from '@/services/auth/auth.service';
import api from '@/services/api';

vi.mock('@/services/auth/auth.service', () => ({
  authService: {
    getMe: vi.fn(),
    login: vi.fn(),
    logout: vi.fn(),
  },
}));

describe('Frontend Auth Store Independent Bearer-Token Invariants', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
    vi.clearAllMocks();
  });

  it('restores session after a simulated page reload using persisted auth_token', async () => {
    const mockUser = {
      id: 42,
      email: 'student@sfu.ca',
      name: 'SFU Student',
      roles: ['member'],
      permissions: [],
      is_active: true,
      is_verified: true,
    };

    localStorage.setItem('auth_token', 'persisted-sanctum-token');
    vi.mocked(authService.getMe).mockResolvedValueOnce({ user: mockUser });

    const authStore = useAuthStore();
    const restoredUser = await authStore.restoreSession();

    expect(authService.getMe).toHaveBeenCalledTimes(1);
    expect(restoredUser).toEqual(mockUser);
    expect(authStore.isAuthenticated).toBe(true);
    expect(authStore.user?.email).toBe('student@sfu.ca');
    expect(localStorage.getItem('auth_token')).toBe('persisted-sanctum-token');
  });

  it('does not invoke getMe and resolves null when no auth_token exists in storage', async () => {
    const authStore = useAuthStore();
    const restoredUser = await authStore.restoreSession();

    expect(authService.getMe).not.toHaveBeenCalled();
    expect(restoredUser).toBeNull();
    expect(authStore.isAuthenticated).toBe(false);
  });

  it('clears authentication state and storage when token is invalid or revoked (401)', async () => {
    localStorage.setItem('auth_token', 'revoked-token');
    localStorage.setItem('auth_user', JSON.stringify({ id: 1, email: 'revoked@sfu.ca' }));
    vi.mocked(authService.getMe).mockRejectedValueOnce({
      response: { status: 401, data: { message: 'Unauthenticated.' } },
    });

    const authStore = useAuthStore();
    const restoredUser = await authStore.restoreSession();

    expect(restoredUser).toBeNull();
    expect(authStore.isAuthenticated).toBe(false);
    expect(authStore.user).toBeNull();
    expect(localStorage.getItem('auth_token')).toBeNull();
    expect(localStorage.getItem('auth_user')).toBeNull();
  });

  it('logout clears local auth_token and auth_user', async () => {
    localStorage.setItem('auth_token', 'valid-token');
    localStorage.setItem('auth_user', JSON.stringify({ id: 1, email: 'user@sfu.ca' }));
    vi.mocked(authService.logout).mockResolvedValueOnce({ message: 'Logged out.' });

    const authStore = useAuthStore();
    authStore.token = 'valid-token';
    authStore.user = { id: 1, email: 'user@sfu.ca', roles: ['member'], permissions: [], is_active: true, is_verified: true };

    const confirmed = await authStore.logout();

    expect(confirmed).toBe(true);
    expect(authStore.isAuthenticated).toBe(false);
    expect(authStore.token).toBeNull();
    expect(authStore.user).toBeNull();
    expect(localStorage.getItem('auth_token')).toBeNull();
    expect(localStorage.getItem('auth_user')).toBeNull();
  });
});
