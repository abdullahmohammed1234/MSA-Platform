import { defineStore } from 'pinia';
import client from '@/services/api/client';

export interface Mentor {
  id: number;
  uuid: string;
  name: string;
  email: string;
  avatar?: string;
  students_count: number;
}

export const useAdminMentorsStore = defineStore('adminMentors', {
  state: () => ({
    mentors: [] as Mentor[],
    currentMentor: null as any | null,
    loading: false,
    error: null as string | null,
    pagination: {
      currentPage: 1,
      lastPage: 1,
      total: 0,
    },
  }),

  actions: {
    async fetchMentors(filters: Record<string, any> = {}, page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await client.get('/admin/academy/mentors', {
          params: { ...filters, page, per_page: 10 },
        });
        if (response.data.success) {
          this.mentors = response.data.mentors;
          this.pagination.currentPage = response.data.meta.current_page;
          this.pagination.lastPage = response.data.meta.last_page;
          this.pagination.total = response.data.meta.total;
        }
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Failed to fetch mentors.';
      } finally {
        this.loading = false;
      }
    },

    async fetchMentorProfile(id: number) {
      this.loading = true;
      try {
        const response = await client.get(`/admin/academy/mentors/${id}`);
        if (response.data.success) {
          this.currentMentor = response.data.mentor;
        }
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Failed to fetch mentor profile.';
      } finally {
        this.loading = false;
      }
    },
  },
});
