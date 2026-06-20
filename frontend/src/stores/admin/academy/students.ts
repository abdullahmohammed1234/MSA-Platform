import { defineStore } from 'pinia';
import client from '@/services/api/client';

export interface Student {
  id: number;
  uuid: string;
  name: string;
  email: string;
  avatar?: string;
  is_active: boolean;
  enrollments_count?: number;
  certificate_awards_count?: number;
}

export const useAdminStudentsStore = defineStore('adminStudents', {
  state: () => ({
    students: [] as Student[],
    currentStudent: null as any | null,
    loading: false,
    error: null as string | null,
    pagination: {
      currentPage: 1,
      lastPage: 1,
      total: 0,
    },
  }),

  actions: {
    async fetchStudents(filters: Record<string, any> = {}, page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await client.get('/admin/academy/students', {
          params: { ...filters, page, per_page: 10 },
        });
        if (response.data.success) {
          this.students = response.data.students;
          this.pagination.currentPage = response.data.meta.current_page;
          this.pagination.lastPage = response.data.meta.last_page;
          this.pagination.total = response.data.meta.total;
        }
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Failed to fetch students.';
      } finally {
        this.loading = false;
      }
    },

    async fetchStudentProfile(idOrUuid: string | number) {
      this.loading = true;
      try {
        const response = await client.get(`/admin/academy/students/${idOrUuid}`);
        if (response.data.success) {
          this.currentStudent = response.data.student;
        }
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Failed to fetch student profile.';
      } finally {
        this.loading = false;
      }
    },

    async suspendStudent(id: number) {
      try {
        const response = await client.post(`/admin/academy/students/${id}/suspend`);
        if (response.data.success) {
          const index = this.students.findIndex((s) => s.id === id);
          if (index !== -1) {
            this.students[index].is_active = false;
          }
          if (this.currentStudent && this.currentStudent.id === id) {
            this.currentStudent.is_active = false;
          }
        }
        return response.data.success;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to suspend student.');
      }
    },

    async reactivateStudent(id: number) {
      try {
        const response = await client.post(`/admin/academy/students/${id}/reactivate`);
        if (response.data.success) {
          const index = this.students.findIndex((s) => s.id === id);
          if (index !== -1) {
            this.students[index].is_active = true;
          }
          if (this.currentStudent && this.currentStudent.id === id) {
            this.currentStudent.is_active = true;
          }
        }
        return response.data.success;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to reactivate student.');
      }
    },
  },
});
