import client from '../api/client';
import type { 
  LoginPayload, 
  RegisterPayload, 
  ResetPasswordPayload,
  AuthResponse,
  User
} from '@/types/auth';

export const authService = {
  /**
   * Log user in and return user details and token.
   */
  async login(payload: LoginPayload): Promise<AuthResponse> {
    const response = await client.post<AuthResponse>('/auth/login', payload);
    return response.data;
  },

  /**
   * Register a new user account.
   */
  async register(payload: RegisterPayload): Promise<AuthResponse> {
    const response = await client.post<AuthResponse>('/auth/register', payload);
    return response.data;
  },

  /**
   * Log the active user out.
   */
  async logout(): Promise<{ message: string }> {
    const response = await client.post<{ message: string }>('/auth/logout');
    return response.data;
  },

  /**
   * Fetch current logged in user details.
   */
  async getMe(): Promise<{ user: User }> {
    const response = await client.get<{ user: User }>('/auth/me');
    return response.data;
  },

  /**
   * Send a password reset email request.
   */
  async forgotPassword(email: string): Promise<{ message: string }> {
    const response = await client.post<{ message: string }>('/auth/forgot-password', { email });
    return response.data;
  },

  /**
   * Reset the user password using a token.
   */
  async resetPassword(payload: ResetPasswordPayload): Promise<{ message: string }> {
    const response = await client.post<{ message: string }>('/auth/reset-password', payload);
    return response.data;
  },

  /**
   * Verify email address using a token.
   */
  async verifyEmail(token: string): Promise<{ message: string }> {
    const response = await client.post<{ message: string }>('/auth/verify-email', { token });
    return response.data;
  },

  /**
   * Resend verification email.
   */
  async resendVerification(): Promise<{ message: string }> {
    const response = await client.post<{ message: string }>('/auth/resend-verification');
    return response.data;
  }
};
