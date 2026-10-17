import apiClient from './api';
import type { LoginPayload, LoginResponse, MeResponse, LogoutResponse } from '@/types/auth';

export const authService = {
  /**
   * Initialize CSRF protection cookie for stateful authentication.
   */
  async getCsrfCookie(): Promise<void> {
    try {
      await apiClient.get('/sanctum/csrf-cookie', {
        baseURL: '', // Uses relative path to hit proxied /sanctum/csrf-cookie
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
    const response = await apiClient.post<LogoutResponse>('/auth/logout');
    return response.data;
  },

  /**
   * Request password reset instructions via email.
   */
  async requestPasswordReset(email: string): Promise<{ message: string }> {
    const response = await apiClient.post<{ message: string }>('/auth/forgot-password', { email });
    return response.data;
  },
};

export default authService;
