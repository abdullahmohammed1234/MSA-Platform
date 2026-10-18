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

describe('Frontend authService CSRF Cookie Bootstrap & Resilience', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(console, 'warn').mockImplementation(() => {});
  });

  it('getCsrfCookie requests /sanctum/csrf-cookie with baseURL set to getApiOrigin()', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: null });

    await authService.getCsrfCookie();

    expect(api.get).toHaveBeenCalledTimes(1);
    expect(api.get).toHaveBeenCalledWith('/sanctum/csrf-cookie', {
      baseURL: getApiOrigin(),
    });
  });

  it('login bootstraps CSRF cookie before posting credentials', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: null });
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { token: 'mock-token', user: { id: 1, email: 'user@sfu.ca' } },
    });

    const result = await authService.login({ email: 'user@sfu.ca', password: 'Password123!' });

    expect(api.get).toHaveBeenCalledWith('/sanctum/csrf-cookie', {
      baseURL: getApiOrigin(),
    });
    expect(api.post).toHaveBeenCalledWith('/auth/login', {
      email: 'user@sfu.ca',
      password: 'Password123!',
    });
    expect(result.token).toBe('mock-token');
  });

  it('logout bootstraps CSRF cookie and handles HTTP 419 gracefully', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: null });
    vi.mocked(api.post).mockRejectedValueOnce({
      response: { status: 419, data: { message: 'CSRF token mismatch.' } },
    });

    const result = await authService.logout();

    expect(api.get).toHaveBeenCalledWith('/sanctum/csrf-cookie', {
      baseURL: getApiOrigin(),
    });
    expect(api.post).toHaveBeenCalledWith('/auth/logout');
    expect(result).toEqual({ message: 'Logged out.' });
  });

  it('logout handles HTTP 401 gracefully when session is already expired', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: null });
    vi.mocked(api.post).mockRejectedValueOnce({
      response: { status: 401, data: { message: 'Unauthenticated.' } },
    });

    const result = await authService.logout();

    expect(result).toEqual({ message: 'Logged out.' });
  });
});
