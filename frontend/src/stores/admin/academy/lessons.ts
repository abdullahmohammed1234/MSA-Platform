import { defineStore } from 'pinia';
import client from '@/services/api';

export interface Lesson {
  id: number;
  module_id: number;
  title: string;
  slug: string;
  content?: string;
  video_url?: string;
  attachments?: any;
  order: number;
  estimated_duration: number;
  is_required: boolean;
}

export interface Module {
  id: number;
  course_id: number;
  title: string;
  description?: string;
  order: number;
  estimated_duration?: number;
  lessons?: Lesson[];
}

export const useAdminLessonsStore = defineStore('adminLessons', {
  state: () => ({
    loading: false,
    error: null as string | null,
  }),

  actions: {
    async createModule(data: Partial<Module>) {
      this.loading = true;
      try {
        const response = await client.post('/dams/modules', data);
        return response.data.module;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to create module.');
      } finally {
        this.loading = false;
      }
    },

    async updateModule(id: number, data: Partial<Module>) {
      this.loading = true;
      try {
        const response = await client.put(`/dams/modules/${id}`, data);
        return response.data.module;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to update module.');
      } finally {
        this.loading = false;
      }
    },

    async deleteModule(id: number) {
      this.loading = true;
      try {
        const response = await client.delete(`/dams/modules/${id}`);
        return response.data.success;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to delete module.');
      } finally {
        this.loading = false;
      }
    },

    async createLesson(data: Partial<Lesson>) {
      this.loading = true;
      try {
        const response = await client.post('/dams/lessons', data);
        return response.data.lesson;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to create lesson.');
      } finally {
        this.loading = false;
      }
    },

    async updateLesson(id: number, data: Partial<Lesson>) {
      this.loading = true;
      try {
        const response = await client.put(`/dams/lessons/${id}`, data);
        return response.data.lesson;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to update lesson.');
      } finally {
        this.loading = false;
      }
    },

    async deleteLesson(id: number) {
      this.loading = true;
      try {
        const response = await client.delete(`/dams/lessons/${id}`);
        return response.data.success;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to delete lesson.');
      } finally {
        this.loading = false;
      }
    },

    async reorderLessons(moduleId: number, lessonIds: number[]) {
      try {
        const response = await client.post(`/dams/modules/${moduleId}/lessons/reorder`, {
          lessons: lessonIds,
        });
        return response.data.success;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to reorder lessons.');
      }
    },
  },
});
