import { defineStore } from 'pinia';
import client from '@/services/api/client';

import type { Module, Quiz } from '@/types/academy';

export interface Course {
  id: number;
  uuid: string;
  title: string;
  slug: string;
  description: string | null;
  thumbnail: string | null;
  difficulty: 'beginner' | 'intermediate' | 'advanced';
  estimated_duration: number | null;
  status: 'draft' | 'published' | 'archived';
  published_at?: string | null;
  created_by?: number | null;
  created_at?: string;
  updated_at?: string;
  creator?: {
    id: number;
    name: string;
  };
  modules?: Module[];
  quizzes?: Quiz[];
}

export const useAdminCoursesStore = defineStore('adminCourses', {
  state: () => ({
    courses: [] as Course[],
    currentCourse: null as Course | null,
    loading: false,
    error: null as string | null,
    pagination: {
      currentPage: 1,
      lastPage: 1,
      total: 0,
    },
  }),

  actions: {
    async fetchCourses(filters: Record<string, any> = {}, page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await client.get('/admin/academy/courses', {
          params: { ...filters, page, per_page: 10 },
        });
        if (response.data.success) {
          this.courses = response.data.courses;
          this.pagination.currentPage = response.data.meta.current_page;
          this.pagination.lastPage = response.data.meta.last_page;
          this.pagination.total = response.data.meta.total;
        }
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Failed to fetch courses.';
      } finally {
        this.loading = false;
      }
    },

    async fetchCourseDetails(idOrSlug: string | number) {
      this.loading = true;
      try {
        const response = await client.get(`/academy/courses/${idOrSlug}`);
        if (response.data.success) {
          this.currentCourse = response.data.course;
        }
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Failed to fetch course details.';
      } finally {
        this.loading = false;
      }
    },

    async createCourse(data: Partial<Course>) {
      this.loading = true;
      try {
        const response = await client.post('/admin/academy/courses', data);
        if (response.data.success) {
          this.courses.unshift(response.data.course);
          return response.data.course;
        }
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to create course.');
      } finally {
        this.loading = false;
      }
    },

    async updateCourse(id: number, data: Partial<Course>) {
      this.loading = true;
      try {
        const response = await client.put(`/admin/academy/courses/${id}`, data);
        if (response.data.success) {
          const index = this.courses.findIndex((c) => c.id === id);
          if (index !== -1) {
            this.courses[index] = response.data.course;
          }
          if (this.currentCourse && this.currentCourse.id === id) {
            this.currentCourse = response.data.course;
          }
          return response.data.course;
        }
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to update course.');
      } finally {
        this.loading = false;
      }
    },

    async deleteCourse(id: number) {
      this.loading = true;
      try {
        const response = await client.delete(`/admin/academy/courses/${id}`);
        if (response.data.success) {
          this.courses = this.courses.filter((c) => c.id !== id);
          if (this.currentCourse && this.currentCourse.id === id) {
            this.currentCourse = null;
          }
        }
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to delete course.');
      } finally {
        this.loading = false;
      }
    },

    async reorderModules(courseId: number, moduleIds: number[]) {
      try {
        const response = await client.post(`/admin/academy/courses/${courseId}/modules/reorder`, {
          modules: moduleIds,
        });
        return response.data.success;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to reorder modules.');
      }
    },
  },
});
