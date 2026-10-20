import { describe, it, expect, beforeEach, vi } from 'vitest';
import { authService } from '@/services/auth/auth.service';
import api from '@/services/api';
import { getApiOrigin } from '@/config/api';

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

describe('Frontend authService Stateless Bearer Authentication & Resilience', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(console, 'warn').mockImplementation(() => {});
  });

  it('getCsrfCookie requests /sanctum/csrf-cookie when explicitly invoked', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: null });

    await authService.getCsrfCookie();

    expect(api.get).toHaveBeenCalledTimes(1);
    expect(api.get).toHaveBeenCalledWith('/sanctum/csrf-cookie', {
      baseURL: getApiOrigin(),
    });
  });

  it('login posts credentials directly to /auth/login statelessly without CSRF cookie bootstrap', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { token: 'mock-token', user: { id: 1, email: 'user@sfu.ca' } },
    });

    const result = await authService.login({ email: 'user@sfu.ca', password: 'Password123!' });

    // Login is purely stateless; it must not make an unnecessary GET /sanctum/csrf-cookie call
    expect(api.get).not.toHaveBeenCalled();
    expect(api.post).toHaveBeenCalledTimes(1);
    expect(api.post).toHaveBeenCalledWith('/auth/login', {
      email: 'user@sfu.ca',
      password: 'Password123!',
    });
    expect(result.token).toBe('mock-token');
  });

  it('getMe fetches user details via GET /auth/me', async () => {
    const mockUser = { id: 1, email: 'user@sfu.ca', name: 'Test User' };
    vi.mocked(api.get).mockResolvedValueOnce({
      data: { user: mockUser },
    });

    const result = await authService.getMe();

    expect(api.get).toHaveBeenCalledTimes(1);
    expect(api.get).toHaveBeenCalledWith('/auth/me');
    expect(result.user).toEqual(mockUser);
  });

  it('logout handles HTTP 419 gracefully when session is expired', async () => {
    vi.mocked(api.post).mockRejectedValueOnce({
      response: { status: 419, data: { message: 'CSRF token mismatch.' } },
    });

    const result = await authService.logout();

    expect(api.post).toHaveBeenCalledTimes(1);
    expect(api.post).toHaveBeenCalledWith('/auth/logout');
    expect(result).toEqual({ message: 'Logged out.' });
  });

  it('logout handles HTTP 401 gracefully when token is already expired or revoked', async () => {
    vi.mocked(api.post).mockRejectedValueOnce({
      response: { status: 401, data: { message: 'Unauthenticated.' } },
    });

    const result = await authService.logout();

    expect(result).toEqual({ message: 'Logged out.' });
  });
});
