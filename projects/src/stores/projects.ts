import { defineStore } from 'pinia';
import { projectsApi } from '../api/projects';
import type { Project } from '../types/pm';

export const useProjectsStore = defineStore('pmProjects', {
  state: () => ({
    projects: [] as Project[],
    currentProject: null as Project | null,
    isLoading: false,
    filters: {
      search: '',
      department_id: null as number | null,
      visibility: '',
      status: '',
      priority: '',
    },
    pagination: {
      current_page: 1,
      last_page: 1,
      total: 0,
    },
  }),

  getters: {
    hasProjects: (state) => state.projects.length > 0,
  },

  actions: {
    async fetchProjects(page = 1) {
      this.isLoading = true;
      try {
        const params: any = { page };
        if (this.filters.search) params.search = this.filters.search;
        if (this.filters.department_id) params.department_id = this.filters.department_id;
        if (this.filters.visibility) params.visibility = this.filters.visibility;
        if (this.filters.status) params.status = this.filters.status;
        if (this.filters.priority) params.priority = this.filters.priority;

        const data = await projectsApi.getProjects(params);
        this.projects = data.projects;
        if (data.meta) {
          this.pagination = {
            current_page: data.meta.current_page || 1,
            last_page: data.meta.last_page || 1,
            total: data.meta.total || 0,
          };
        }
      } finally {
        this.isLoading = false;
      }
    },

    async fetchProject(uuid: string): Promise<Project> {
      this.isLoading = true;
      try {
        const project = await projectsApi.getProject(uuid);
        this.currentProject = project;
        return project;
      } finally {
        this.isLoading = false;
      }
    },

    async createProject(payload: {
      name: string;
      department_id?: number;
      owning_department_id?: number;
      participating_department_ids?: number[];
      visibility: string;
      description?: string;
      priority?: string;
      start_date?: string;
      due_date?: string;
    }): Promise<Project> {
      const res = await projectsApi.createProject(payload);
      await this.fetchProjects(1);
      return res.project;
    },

    async updateProject(uuid: string, payload: Partial<Project>): Promise<Project> {
      const res = await projectsApi.updateProject(uuid, payload);
      if (this.currentProject && this.currentProject.uuid === uuid) {
        this.currentProject = { ...this.currentProject, ...res.project };
      }
      return res.project;
    },

    async deleteProject(uuid: string) {
      await projectsApi.deleteProject(uuid);
      this.projects = this.projects.filter((p) => p.uuid !== uuid);
      if (this.currentProject?.uuid === uuid) {
        this.currentProject = null;
      }
    },

    resetFilters() {
      this.filters = {
        search: '',
        department_id: null,
        visibility: '',
        status: '',
        priority: '',
      };
      this.fetchProjects(1);
    },
  },
});
