import { defineStore } from 'pinia';
import client from '@/services/api/client';

export interface ProgressRecord {
  id: number;
  user: {
    id: number;
    name: string;
    email: string;
  };
  course: {
    id: number;
    title: string;
  };
  status: string;
  enrolled_at: string;
  completed_at?: string;
  progress_percentage: number;
  completed_lessons_count: number;
  total_lessons_count: number;
  quiz_scores: Array<{
    quiz_title: string;
    score: number;
    passed: boolean;
  }>;
  certificate_earned?: {
    code: string;
    issued_at: string;
  };
}

export const useAdminProgressStore = defineStore('adminProgress', {
  state: () => ({
    progressRecords: [] as ProgressRecord[],
    loading: false,
    error: null as string | null,
    pagination: {
      currentPage: 1,
      lastPage: 1,
      total: 0,
    },
  }),

  actions: {
    async fetchProgress(filters: Record<string, any> = {}, page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await client.get('/admin/academy/progress', {
          params: { ...filters, page, per_page: 10 },
        });
        if (response.data.success) {
          this.progressRecords = response.data.progress;
          this.pagination.currentPage = response.data.meta.current_page;
          this.pagination.lastPage = response.data.meta.last_page;
          this.pagination.total = response.data.meta.total;
        }
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Failed to fetch progress records.';
      } finally {
        this.loading = false;
      }
    },
  },
});
