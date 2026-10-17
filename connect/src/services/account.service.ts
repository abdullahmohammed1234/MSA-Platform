import apiClient from './api';
import type {
  AccountSummaryResponse,
  AccountSummaryData,
  UpdateProfilePayload,
  UpdateProfileResponse,
  UpdatePasswordPayload,
  UpdatePasswordResponse,
} from '@/types/account';

export const accountService = {
  /**
   * Fetch unified account summary for authenticated member.
   */
  async getAccountSummary(): Promise<AccountSummaryData> {
    const response = await apiClient.get<AccountSummaryResponse>('/account/summary');
    return response.data.data;
  },

  /**
   * Update member profile (name, email) with validation.
   */
  async updateProfile(payload: UpdateProfilePayload): Promise<UpdateProfileResponse> {
    const response = await apiClient.put<UpdateProfileResponse>('/users/profile', payload);
    return response.data;
  },

  /**
   * Update member account password securely.
   */
  async updatePassword(payload: UpdatePasswordPayload): Promise<UpdatePasswordResponse> {
    const response = await apiClient.put<UpdatePasswordResponse>('/account/password', payload);
    return response.data;
  },
};

export default accountService;
