import { defineStore } from 'pinia';
import client from '@/services/api/client';

export interface Question {
  id: number;
  quiz_id: number;
  type: 'multiple_choice' | 'multiple_select' | 'true_false' | 'short_answer';
  category?: string;
  difficulty?: string;
  tags?: string[];
  question: string;
  options?: string[];
  correct_answer: any;
  points: number;
  order: number;
  quiz?: {
    id: number;
    title: string;
    course?: {
      id: number;
      title: string;
    };
  };
}

export interface Quiz {
  id: number;
  course_id: number;
  title: string;
  description?: string;
  passing_score: number;
  time_limit?: number;
  attempt_limit?: number;
  status: 'draft' | 'published';
  questions?: Question[];
}

export const useAdminQuizzesStore = defineStore('adminQuizzes', {
  state: () => ({
    quizzes: [] as Quiz[],
    questions: [] as Question[],
    loading: false,
    error: null as string | null,
    pagination: {
      currentPage: 1,
      lastPage: 1,
      total: 0,
    },
  }),

  actions: {
    async fetchQuizzes(filters: Record<string, any> = {}, page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await client.get('/admin/academy/quizzes', {
          params: { ...filters, page, per_page: 50 },
        });
        if (response.data.success) {
          this.quizzes = response.data.quizzes;
          this.pagination.currentPage = response.data.meta.current_page;
          this.pagination.lastPage = response.data.meta.last_page;
          this.pagination.total = response.data.meta.total;
        }
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Failed to fetch quizzes.';
        throw new Error(this.error ?? 'Failed to fetch quizzes.');
      } finally {
        this.loading = false;
      }
    },

    async fetchQuestions(filters: Record<string, any> = {}, page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await client.get('/admin/academy/questions', {
          params: { ...filters, page, per_page: 15 },
        });
        if (response.data.success) {
          this.questions = response.data.questions;
          this.pagination.currentPage = response.data.meta.current_page;
          this.pagination.lastPage = response.data.meta.last_page;
          this.pagination.total = response.data.meta.total;
        }
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Failed to fetch questions.';
      } finally {
        this.loading = false;
      }
    },

    async createQuiz(data: Partial<Quiz>) {
      this.loading = true;
      try {
        const response = await client.post('/admin/academy/quizzes', data);
        return response.data.quiz;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to create quiz.');
      } finally {
        this.loading = false;
      }
    },

    async updateQuiz(id: number, data: Partial<Quiz>) {
      this.loading = true;
      try {
        const response = await client.put(`/admin/academy/quizzes/${id}`, data);
        return response.data.quiz;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to update quiz.');
      } finally {
        this.loading = false;
      }
    },

    async deleteQuiz(id: number) {
      this.loading = true;
      try {
        const response = await client.delete(`/admin/academy/quizzes/${id}`);
        return response.data.success;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to delete quiz.');
      } finally {
        this.loading = false;
      }
    },

    async createQuestion(data: Partial<Question>) {
      this.loading = true;
      try {
        const response = await client.post('/admin/academy/questions', data);
        return response.data.question;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to create question.');
      } finally {
        this.loading = false;
      }
    },

    async updateQuestion(id: number, data: Partial<Question>) {
      this.loading = true;
      try {
        const response = await client.put(`/admin/academy/questions/${id}`, data);
        return response.data.question;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to update question.');
      } finally {
        this.loading = false;
      }
    },

    async deleteQuestion(id: number) {
      this.loading = true;
      try {
        const response = await client.delete(`/admin/academy/questions/${id}`);
        return response.data.success;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to delete question.');
      } finally {
        this.loading = false;
      }
    },
  },
});
