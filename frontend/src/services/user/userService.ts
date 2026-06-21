import client from '@/services/api';
import type { User } from '@/types/auth';

export const userService = {
  async updateProfile(payload: { name?: string; email?: string }): Promise<User> {
    const response = await client.put('/users/profile', payload);
    const data = response.data;

    if (!data?.user) {
      throw new Error(data?.message || 'Failed to update profile.');
    }

    return data.user;
  },

  async completeAcademyOnboarding(): Promise<User> {
    const response = await client.post('/users/academy-onboarding/complete');
    const data = response.data;

    if (!data?.user) {
      throw new Error(data?.message || 'Failed to complete academy onboarding.');
    }

    return data.user;
  },
};
