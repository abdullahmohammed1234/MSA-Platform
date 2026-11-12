import { defineStore } from 'pinia';
import { projectsApi } from '../api/projects';
import type { DepartmentSummary } from '../types/pm';

export const useDepartmentsStore = defineStore('pmDepartments', {
  state: () => ({
    departments: [] as DepartmentSummary[],
    isLoading: false,
    isLoaded: false,
    error: null as string | null,
  }),

  getters: {
    allDepartments: (state) => state.departments,
    departmentCount: (state) => state.departments.length,
    getDepartmentById: (state) => (id: number) => state.departments.find((d) => d.id === id),
  },

  actions: {
    async loadDepartments(force = false): Promise<DepartmentSummary[]> {
      if (this.isLoaded && !force && this.departments.length > 0) {
        return this.departments;
      }

      this.isLoading = true;
      this.error = null;

      try {
        const list = await projectsApi.getDepartments();
        this.departments = list;
        this.isLoaded = true;
        return this.departments;
      } catch (err: any) {
        const msg = err.response?.data?.message || err.message || 'Failed to load departments from canonical registry.';
        this.error = msg;
        throw err;
      } finally {
        this.isLoading = false;
      }
    },
  },
});
