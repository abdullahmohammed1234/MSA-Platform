import { defineStore } from 'pinia';
import client from '@/services/api';

export interface Assignment {
  id: number;
  mentor_id: number;
  student_id: number;
  assigned_by?: number;
  status: 'active' | 'inactive' | 'completed';
  notes?: string;
  created_at: string;
  mentor?: {
    id: number;
    name: string;
  };
  student?: {
    id: number;
    name: string;
  };
  assigned_by_user?: {
    id: number;
    name: string;
  };
}

export const useAdminAssignmentsStore = defineStore('adminAssignments', {
  state: () => ({
    assignments: [] as Assignment[],
    loading: false,
    error: null as string | null,
    pagination: {
      currentPage: 1,
      lastPage: 1,
      total: 0,
    },
  }),

  actions: {
    async fetchAssignments(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await client.get('/admin/academy/assignments', {
          params: { page, per_page: 10 },
        });
        if (response.data.success) {
          this.assignments = response.data.assignments;
          this.pagination.currentPage = response.data.meta.current_page;
          this.pagination.lastPage = response.data.meta.last_page;
          this.pagination.total = response.data.meta.total;
        }
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Failed to fetch assignments.';
      } finally {
        this.loading = false;
      }
    },

    async assignMentor(mentorId: number, studentId: number, notes?: string) {
      this.loading = true;
      try {
        const response = await client.post('/admin/academy/assignments', {
          mentor_id: mentorId,
          student_id: studentId,
          notes,
        });
        if (response.data.success) {
          this.assignments.unshift(response.data.assignment);
          return response.data.assignment;
        }
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to assign mentor.');
      } finally {
        this.loading = false;
      }
    },

    async bulkAssign(mentorId: number, studentIds: number[]) {
      this.loading = true;
      try {
        const response = await client.post('/admin/academy/assignments/bulk', {
          mentor_id: mentorId,
          student_ids: studentIds,
        });
        return response.data.results;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to complete bulk assignment.');
      } finally {
        this.loading = false;
      }
    },

    async removeAssignment(mentorId: number, studentId: number) {
      this.loading = true;
      try {
        const response = await client.delete(`/admin/academy/assignments/${mentorId}/${studentId}`);
        if (response.data.success) {
          this.assignments = this.assignments.filter(
            (a) => !(a.mentor_id === mentorId && a.student_id === studentId)
          );
        }
        return response.data.success;
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to remove assignment.');
      } finally {
        this.loading = false;
      }
    },
  },
});
