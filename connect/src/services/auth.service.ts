import apiClient from './api';
import { getApiOrigin } from '../config/api';
import type { LoginPayload, LoginResponse, MeResponse, LogoutResponse } from '@/types/auth';

export const authService = {
  /**
   * Initialize CSRF protection cookie for stateful authentication on the API origin.
   */
  async getCsrfCookie(): Promise<void> {
    try {
      await apiClient.get('/sanctum/csrf-cookie', {
        baseURL: getApiOrigin(),
      });
    } catch (e) {
      console.warn('CSRF cookie bootstrap ignored or failed:', e);
    }
  },

  /**
   * Authenticate with email and password via Laravel Sanctum endpoint.
   */
  async login(payload: LoginPayload): Promise<LoginResponse> {
    await this.getCsrfCookie();
    const response = await apiClient.post<LoginResponse>('/auth/login', payload);
    return response.data;
  },

  /**
   * Fetch authenticated user details to verify session authoritatively.
   */
  async me(): Promise<MeResponse> {
    const response = await apiClient.get<MeResponse>('/auth/me');
    return response.data;
  },

  /**
   * Invalidate the current Sanctum token on the server.
   */
  async logout(): Promise<LogoutResponse> {
    try {
      await this.getCsrfCookie();
      const response = await apiClient.post<LogoutResponse>('/auth/logout');
      return response.data;
    } catch (e: any) {
      // If the session was already expired on the server (419/401), client logout completes cleanly
      if (e?.response?.status === 419 || e?.response?.status === 401) {
        return { message: 'Logged out.' };
      }
      throw e;
    }
  },

  /**
   * Request password reset instructions via email.
   */
  async requestPasswordReset(email: string): Promise<{ message: string }> {
    await this.getCsrfCookie();
    const response = await apiClient.post<{ message: string }>('/auth/forgot-password', { email });
    return response.data;
  },
};

export default authService;
