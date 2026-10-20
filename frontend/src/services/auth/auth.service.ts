import api from '@/services/api';
import { getApiOrigin } from '@/config/api';
import type { 
  LoginPayload, 
  RegisterPayload, 
  ResetPasswordPayload,
  AuthResponse,
  User
} from '@/types/auth';

export const authService = {
  /**
   * Initialize CSRF protection cookie for stateful authentication on the API origin.
   */
  async getCsrfCookie(): Promise<void> {
    try {
      await api.get('/sanctum/csrf-cookie', {
        baseURL: getApiOrigin(),
      });
    } catch (e) {
      console.warn('CSRF cookie bootstrap ignored or failed:', e);
    }
  },

  /**
   * Log user in and return user details and token.
   */
  async login(payload: LoginPayload): Promise<AuthResponse> {
    await this.getCsrfCookie();
    const response = await api.post<AuthResponse>('/auth/login', payload);
    return response.data;
  },

  /**
   * Register a new user account.
   */
  async register(payload: RegisterPayload): Promise<AuthResponse> {
    await this.getCsrfCookie();
    const response = await api.post<AuthResponse>('/auth/register', payload);
    return response.data;
  },

  /**
   * Log the active user out.
   */
  async logout(): Promise<{ message: string }> {
    try {
      const response = await api.post<{ message: string }>('/auth/logout');
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
   * Fetch current logged in user details.
   */
  async getMe(): Promise<{ user: User }> {
    const response = await api.get<{ user: User }>('/auth/me');
    return response.data;
  },

  /**
   * Send a password reset email request.
   */
  async forgotPassword(email: string): Promise<{ message: string }> {
    await this.getCsrfCookie();
    const response = await api.post<{ message: string }>('/auth/forgot-password', { email });
    return response.data;
  },

  /**
   * Reset the user password using a token.
   */
  async resetPassword(payload: ResetPasswordPayload): Promise<{ message: string }> {
    await this.getCsrfCookie();
    const response = await api.post<{ message: string }>('/auth/reset-password', payload);
    return response.data;
  },

  /**
   * Verify email address using a token.
   */
  async verifyEmail(token: string): Promise<{ message: string }> {
    const response = await api.post<{ message: string }>('/auth/verify-email', { token });
    return response.data;
  },

  /**
   * Resend verification email.
   */
  async resendVerification(): Promise<{ message: string }> {
    await this.getCsrfCookie();
    const response = await api.post<{ message: string }>('/auth/resend-verification');
    return response.data;
  }
};
