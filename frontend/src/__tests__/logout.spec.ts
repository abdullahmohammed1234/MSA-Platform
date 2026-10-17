import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '@/stores/auth';
import { authService } from '@/services/auth/auth.service';

vi.mock('@/services/auth/auth.service', () => ({
  authService: {
    login: vi.fn().mockResolvedValue({
      token: 'test-token',
      user: { id: 1, name: 'Member', email: 'm@sfu.ca', roles: ['member'], permissions: [] },
    }),
    logout: vi.fn(),
  },
}));

describe('auth store logout', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
    vi.mocked(authService.logout).mockReset();
    vi.spyOn(console, 'error').mockImplementation(() => {});
  });

  it('reports server confirmation and clears local credentials', async () => {
    vi.mocked(authService.logout).mockResolvedValue({ message: 'Logged out' });
    const store = useAuthStore();
    await store.login({ email: 'm@sfu.ca', password: 'password' });
    expect(localStorage.getItem('auth_token')).not.toBeNull();

    const confirmed = await store.logout();

    expect(confirmed).toBe(true);
    expect(authService.logout).toHaveBeenCalledTimes(1);
    expect(store.token).toBeNull();
    expect(store.isAuthenticated).toBe(false);
    expect(localStorage.getItem('auth_token')).toBeNull();
    expect(localStorage.getItem('auth_user')).toBeNull();
  });

  it('still clears local credentials when the server call fails, without claiming revocation', async () => {
    vi.mocked(authService.logout).mockRejectedValue(new Error('Network Error'));
    const store = useAuthStore();
    await store.login({ email: 'm@sfu.ca', password: 'password' });

    const confirmed = await store.logout();

    expect(confirmed).toBe(false);
    expect(store.token).toBeNull();
    expect(store.isAuthenticated).toBe(false);
    expect(localStorage.getItem('auth_token')).toBeNull();
    expect(localStorage.getItem('auth_user')).toBeNull();
  });
});
