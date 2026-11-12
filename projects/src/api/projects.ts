import apiClient from './client';
import type {
  Attachment,
  CalendarEvent,
  CalendarFeed,
  CascadePreview,
  Comment,
  CreateCalendarFeedPayload,
  DashboardData,
  DashboardAnalytics,
  DepartmentAnalytics,
  DepartmentSummary,
  GanttDataset,
  Milestone,
  MilestoneCondition,
  Project,
  ProjectAnalytics,
  ProjectMember,
  ProjectWorkloadResponse,
  ResourceBalancingSuggestion,
  SchedulingInfo,
  Task,
  TaskDependency,
  TaskList,
  TaskStatus,
  UserCapacity,
  UserSummary,
} from '../types/pm';

export const projectsApi = {
  // Dashboard Overview
  async getDashboard(): Promise<DashboardData> {
    const res = await apiClient.get<{ success: boolean; data: DashboardData }>('/pm/dashboard');
    return res.data.data;
  },

  // SSO Exchange (SEC-11-01)
  async exchangeSsoTicket(ticket: string): Promise<{
    success: boolean;
    message: string;
    token: string;
    user: UserSummary;
  }> {
    const res = await apiClient.post<{
      success: boolean;
      message: string;
      token: string;
      user: UserSummary;
    }>('/sso/exchange', { ticket, service: 'pms' });
    return res.data;
  },

  // Calendar
  async getCalendarEvents(params?: {
    start?: string;
    end?: string;
    department_id?: number;
    project_uuid?: string;
    type?: string;
    my_tasks_only?: boolean;
  }): Promise<CalendarEvent[]> {
    const res = await apiClient.get<{ success: boolean; data: CalendarEvent[] }>('/pm/calendar/events', { params });
    return res.data.data;
  },

  // My Tasks
  async getMyTasks(params?: { status?: string; page?: number }): Promise<{ tasks: Task[]; meta: any }> {
    const res = await apiClient.get<{ tasks: Task[]; meta: any }>('/pm/tasks/me', { params });
    return res.data;
  },

  // Invitations
  async getMyInvitations(): Promise<ProjectMember[]> {
    const res = await apiClient.get<{ success: boolean; data: ProjectMember[] }>('/pm/invitations/me');
    return res.data.data;
  },

  async acceptInvitation(memberId: number): Promise<{ message: string; project: any }> {
    const res = await apiClient.post<{ message: string; project: any }>(`/pm/invitations/${memberId}/accept`);
    return res.data;
  },

  async declineInvitation(memberId: number): Promise<{ message: string }> {
    const res = await apiClient.post<{ message: string }>(`/pm/invitations/${memberId}/decline`);
    return res.data;
  },

  // Projects CRUD
  async getProjects(params?: {
    search?: string;
    department_id?: number;
    visibility?: string;
    status?: string;
    priority?: string;
    page?: number;
  }): Promise<{ projects: Project[]; meta: any }> {
    const res = await apiClient.get<{ projects: Project[]; meta: any }>('/pm/projects', { params });
    return res.data;
  },

  async getProject(uuid: string): Promise<Project> {
    const res = await apiClient.get<{ project: Project }>(`/pm/projects/${uuid}`);
    return res.data.project;
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
  }): Promise<{ message: string; project: Project }> {
    const res = await apiClient.post<{ message: string; project: Project }>('/pm/projects', payload);
    return res.data;
  },

  async updateProject(
    uuid: string,
    payload: {
      name?: string;
      department_id?: number;
      owning_department_id?: number;
      participating_department_ids?: number[];
      visibility?: string;
      status?: string;
      priority?: string;
      description?: string | null;
      due_date?: string | null;
      start_date?: string | null;
    } & Record<string, any>
  ): Promise<{ message: string; project: Project }> {
    const res = await apiClient.put<{ message: string; project: Project }>(`/pm/projects/${uuid}`, payload);
    return res.data;
  },

  async deleteProject(uuid: string): Promise<{ message: string }> {
    const res = await apiClient.delete<{ message: string }>(`/pm/projects/${uuid}`);
    return res.data;
  },

  async archiveProject(uuid: string): Promise<{ message: string }> {
    const res = await apiClient.post<{ message: string }>(`/pm/projects/${uuid}/archive`);
    return res.data;
  },

  async restoreProject(uuid: string): Promise<{ message: string }> {
    const res = await apiClient.post<{ message: string }>(`/pm/projects/${uuid}/restore`);
    return res.data;
  },

  // Project Members
  async getProjectMembers(uuid: string): Promise<ProjectMember[]> {
    const res = await apiClient.get<{ members: ProjectMember[] }>(`/pm/projects/${uuid}/members`);
    return res.data.members;
  },

  async inviteMember(uuid: string, payload: { user_id: number; project_role: string }): Promise<{ message: string; member: any }> {
    const res = await apiClient.post<{ message: string; member: any }>(`/pm/projects/${uuid}/members/invite`, payload);
    return res.data;
  },

  async removeMember(uuid: string, memberId: number): Promise<{ message: string }> {
    const res = await apiClient.delete<{ message: string }>(`/pm/projects/${uuid}/members/${memberId}`);
    return res.data;
  },

  // Milestones
  async getMilestones(uuid: string): Promise<Milestone[]> {
    const res = await apiClient.get<{ milestones: Milestone[] }>(`/pm/projects/${uuid}/milestones`);
    return res.data.milestones;
  },

  async createMilestone(uuid: string, payload: {
    title: string;
    description?: string;
    due_date?: string;
    status?: string;
  }): Promise<{ message: string; milestone: Milestone }> {
    const res = await apiClient.post<{ message: string; milestone: Milestone }>(`/pm/projects/${uuid}/milestones`, payload);
    return res.data;
  },

  async updateMilestone(milestoneUuid: string, payload: Partial<Milestone>): Promise<{ message: string; milestone: Milestone }> {
    const res = await apiClient.put<{ message: string; milestone: Milestone }>(`/pm/milestones/${milestoneUuid}`, payload);
    return res.data;
  },

  async deleteMilestone(milestoneUuid: string): Promise<{ message: string }> {
    const res = await apiClient.delete<{ message: string }>(`/pm/milestones/${milestoneUuid}`);
    return res.data;
  },

  // Eligible Task Assignees
  async getEligibleAssignees(projectUuid: string): Promise<UserSummary[]> {
    const res = await apiClient.get<{ users: UserSummary[] }>(`/pm/projects/${projectUuid}/eligible-assignees`);
    return res.data.users;
  },

  // Task Lists
  async getTaskLists(projectUuid: string): Promise<{ lists: TaskList[]; uncategorized_count: number }> {
    const res = await apiClient.get<{ lists: TaskList[]; uncategorized_count: number }>(`/pm/projects/${projectUuid}/lists`);
    return res.data;
  },

  async createTaskList(projectUuid: string, payload: { name: string; color?: string }): Promise<{ message: string; list: TaskList }> {
    const res = await apiClient.post<{ message: string; list: TaskList }>(`/pm/projects/${projectUuid}/lists`, payload);
    return res.data;
  },

  async updateTaskList(listUuid: string, payload: { name?: string; color?: string }): Promise<{ message: string; list: TaskList }> {
    const res = await apiClient.put<{ message: string; list: TaskList }>(`/pm/lists/${listUuid}`, payload);
    return res.data;
  },

  async reorderTaskLists(projectUuid: string, listUuids: string[]): Promise<{ message: string }> {
    const res = await apiClient.post<{ message: string }>(`/pm/projects/${projectUuid}/lists/reorder`, { list_uuids: listUuids });
    return res.data;
  },

  async deleteTaskList(listUuid: string): Promise<{ message: string }> {
    const res = await apiClient.delete<{ message: string }>(`/pm/lists/${listUuid}`);
    return res.data;
  },

  // Task Dependencies
  async getTaskDependencies(taskUuid: string): Promise<{ task: any; dependencies: TaskDependency[]; dependents: TaskDependency[]; is_blocked: boolean }> {
    const res = await apiClient.get<{ task: any; dependencies: TaskDependency[]; dependents: TaskDependency[]; is_blocked: boolean }>(`/pm/tasks/${taskUuid}/dependencies`);
    return res.data;
  },

  async addTaskDependency(
    taskUuid: string,
    dependsOnTaskUuid: string,
    dependencyType?: string,
    lagDays?: number
  ): Promise<{ message: string; dependency: any }> {
    const res = await apiClient.post<{ message: string; dependency: any }>(`/pm/tasks/${taskUuid}/dependencies`, {
      depends_on_task_uuid: dependsOnTaskUuid,
      dependency_type: dependencyType || 'finish_to_start',
      lag_days: lagDays ?? 0,
    });
    return res.data;
  },

  async removeTaskDependency(taskUuid: string, dependsOnUuid: string): Promise<{ message: string }> {
    const res = await apiClient.delete<{ message: string }>(`/pm/tasks/${taskUuid}/dependencies/${dependsOnUuid}`);
    return res.data;
  },

  // Attachments
  async getProjectAttachments(projectUuid: string): Promise<Attachment[]> {
    const res = await apiClient.get<{ attachments: Attachment[] }>(`/pm/projects/${projectUuid}/attachments`);
    return res.data.attachments;
  },

  async getTaskAttachments(taskUuid: string): Promise<Attachment[]> {
    const res = await apiClient.get<{ attachments: Attachment[] }>(`/pm/tasks/${taskUuid}/attachments`);
    return res.data.attachments;
  },

  async uploadProjectAttachment(projectUuid: string, file: File): Promise<{ message: string; attachment: Attachment }> {
    const formData = new FormData();
    formData.append('file', file);
    const res = await apiClient.post<{ message: string; attachment: Attachment }>(
      `/pm/projects/${projectUuid}/attachments`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    );
    return res.data;
  },

  async uploadTaskAttachment(taskUuid: string, file: File): Promise<{ message: string; attachment: Attachment }> {
    const formData = new FormData();
    formData.append('file', file);
    const res = await apiClient.post<{ message: string; attachment: Attachment }>(
      `/pm/tasks/${taskUuid}/attachments`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    );
    return res.data;
  },

  getAttachmentDownloadUrl(attachmentUuid: string): string {
    return `/api/v1/pm/attachments/${attachmentUuid}/download`;
  },

  async deleteAttachment(attachmentUuid: string): Promise<{ message: string }> {
    const res = await apiClient.delete<{ message: string }>(`/pm/attachments/${attachmentUuid}`);
    return res.data;
  },

  async restoreAttachment(attachmentUuid: string): Promise<{ message: string; attachment: Attachment }> {
    const res = await apiClient.post<{ message: string; attachment: Attachment }>(`/pm/attachments/${attachmentUuid}/restore`);
    return res.data;
  },

  async forceDeleteAttachment(attachmentUuid: string): Promise<{ message: string }> {
    const res = await apiClient.delete<{ message: string }>(`/pm/attachments/${attachmentUuid}/force`);
    return res.data;
  },

  // Bulk Operations
  async bulkUpdateTasks(payload: {
    project_uuid: string;
    task_uuids: string[];
    action: 'status' | 'assign' | 'task_list' | 'priority' | 'milestone' | 'reschedule_shift' | 'set_dates' | 'rebalance_assignee';
    value: any;
  }): Promise<{ message: string; updated_count: number; task_uuids: string[] }> {
    const res = await apiClient.post<{ message: string; updated_count: number; task_uuids: string[] }>('/pm/tasks/bulk', payload);
    return res.data;
  },

  // Tasks
  async getTasks(uuid: string, params?: {
    milestone_id?: number;
    task_list_id?: number | string;
    status?: string;
    priority?: string;
    assigned_to?: number;
    search?: string;
  }): Promise<{ tasks: Task[]; meta: any }> {
    const res = await apiClient.get<{ tasks: Task[]; meta: any }>(`/pm/projects/${uuid}/tasks`, { params });
    return res.data;
  },

  async createTask(uuid: string, payload: {
    title: string;
    description?: string;
    milestone_id?: number;
    task_list_id?: number | null;
    priority?: string;
    status?: string;
    start_date?: string | null;
    due_date?: string | null;
    assigned_to?: number | null;
    assignee_ids?: number[];
  }): Promise<{ message: string; task: Task }> {
    const res = await apiClient.post<{ message: string; task: Task }>(`/pm/projects/${uuid}/tasks`, payload);
    return res.data;
  },

  async getTask(taskUuid: string): Promise<Task> {
    const res = await apiClient.get<{ task: Task }>(`/pm/tasks/${taskUuid}`);
    return res.data.task;
  },

  async updateTask(
    taskUuid: string,
    payload: Partial<Task> & { assignee_ids?: number[] }
  ): Promise<{ message: string; task: Task }> {
    const res = await apiClient.put<{ message: string; task: Task }>(`/pm/tasks/${taskUuid}`, payload);
    return res.data;
  },

  async updateTaskStatus(taskUuid: string, status: TaskStatus): Promise<{ message: string; task: Task }> {
    const res = await apiClient.patch<{ message: string; task: Task }>(`/pm/tasks/${taskUuid}/status`, { status });
    return res.data;
  },

  async deleteTask(taskUuid: string): Promise<{ message: string }> {
    const res = await apiClient.delete<{ message: string }>(`/pm/tasks/${taskUuid}`);
    return res.data;
  },

  // Comments
  async getComments(projectUuid: string, params?: { task_uuid?: string; task_id?: number }): Promise<Comment[]> {
    const res = await apiClient.get<{ success: boolean; data: Comment[] }>(`/pm/projects/${projectUuid}/comments`, { params });
    return res.data.data;
  },

  async createComment(projectUuid: string, payload: { body: string; task_uuid?: string; task_id?: number }): Promise<Comment> {
    const res = await apiClient.post<{ success: boolean; message: string; data: Comment }>(`/pm/projects/${projectUuid}/comments`, payload);
    return res.data.data;
  },

  async deleteComment(projectUuid: string, commentId: number): Promise<{ message: string }> {
    const res = await apiClient.delete<{ success: boolean; message: string }>(`/pm/projects/${projectUuid}/comments/${commentId}`);
    return res.data;
  },

  // Team & Department roster
  async getDepartments(): Promise<DepartmentSummary[]> {
    const res = await apiClient.get<any>('/pm/departments');
    if (Array.isArray(res.data?.departments)) {
      return res.data.departments;
    }
    if (Array.isArray(res.data?.data)) {
      return res.data.data;
    }
    if (Array.isArray(res.data?.data?.departments)) {
      return res.data.data.departments;
    }
    if (Array.isArray(res.data)) {
      return res.data;
    }
    return [];
  },

  // Scheduling & Workflow Engine (Phase 12)
  async getTaskScheduling(taskUuid: string): Promise<SchedulingInfo> {
    const res = await apiClient.get<{ success: boolean; data: SchedulingInfo }>(`/pm/tasks/${taskUuid}/scheduling`);
    return res.data.data;
  },

  async previewReschedule(
    taskUuid: string,
    payload: { start_date: string; due_date: string }
  ): Promise<CascadePreview> {
    const res = await apiClient.post<{ success: boolean; data: CascadePreview }>(
      `/pm/tasks/${taskUuid}/reschedule/preview`,
      payload
    );
    return res.data.data;
  },

  async rescheduleTask(
    taskUuid: string,
    payload: { start_date: string; due_date: string; cascade: boolean }
  ): Promise<{ message: string; task: Task; affected_count: number; rescheduled_tasks: any[] }> {
    const res = await apiClient.post<{
      success: boolean;
      message: string;
      task: Task;
      affected_count: number;
      rescheduled_tasks: any[];
    }>(`/pm/tasks/${taskUuid}/reschedule`, payload);
    return res.data;
  },

  async getMilestoneCondition(milestoneUuid: string): Promise<MilestoneCondition> {
    const res = await apiClient.get<{ success: boolean; data: MilestoneCondition }>(
      `/pm/milestones/${milestoneUuid}/condition`
    );
    return res.data.data;
  },

  // Calendar Subscriptions & Feeds (Phase 12)
  async getCalendarFeeds(): Promise<CalendarFeed[]> {
    const res = await apiClient.get<{ success: boolean; data: CalendarFeed[] }>('/pm/calendar/feeds');
    return res.data.data;
  },

  async createCalendarFeed(payload: CreateCalendarFeedPayload): Promise<CalendarFeed> {
    const res = await apiClient.post<{ success: boolean; data: CalendarFeed; message: string }>(
      '/pm/calendar/feeds',
      payload
    );
    return res.data.data;
  },

  async revokeCalendarFeed(feedUuid: string): Promise<{ success: boolean; message: string }> {
    const res = await apiClient.delete<{ success: boolean; message: string }>(`/pm/calendar/feeds/${feedUuid}`);
    return res.data;
  },

  getCalendarExportUrl(params?: {
    department_id?: number;
    project_uuid?: string;
    type?: string;
    start?: string;
    end?: string;
    my_tasks_only?: boolean;
  }): string {
    const baseURL = apiClient.defaults.baseURL || '/api/v1';
    const query = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, val]) => {
        if (val !== undefined && val !== null && val !== '') {
          query.set(key, String(val));
        }
      });
    }
    const qs = query.toString();
    return `${baseURL}/pm/calendar/export.ics${qs ? `?${qs}` : ''}`;
  },

  // Gantt Chart Engine (Phase 13)
  async getProjectGantt(uuid: string): Promise<GanttDataset> {
    const res = await apiClient.get<{ status: string; data: GanttDataset }>(`/pm/projects/${uuid}/gantt`);
    return res.data.data;
  },

  // Workload Visualization (Phase 13)
  async getProjectWorkload(uuid: string, startDate?: string, endDate?: string): Promise<ProjectWorkloadResponse> {
    const params = new URLSearchParams();
    if (startDate) params.set('start_date', startDate);
    if (endDate) params.set('end_date', endDate);
    const qs = params.toString();
    const res = await apiClient.get<{ status: string; data: ProjectWorkloadResponse }>(
      `/pm/projects/${uuid}/workload${qs ? `?${qs}` : ''}`
    );
    return res.data.data;
  },

  async getDepartmentWorkload(departmentId: number, startDate?: string, endDate?: string): Promise<any> {
    const params = new URLSearchParams();
    if (startDate) params.set('start_date', startDate);
    if (endDate) params.set('end_date', endDate);
    const qs = params.toString();
    const res = await apiClient.get<{ status: string; data: any }>(
      `/pm/departments/${departmentId}/workload${qs ? `?${qs}` : ''}`
    );
    return res.data.data;
  },

  // Capacity & Balancing (Phase 13)
  async getUserCapacities(params?: { department_id?: number; user_ids?: number[] }): Promise<UserCapacity[]> {
    const query = new URLSearchParams();
    if (params?.department_id) query.set('department_id', String(params.department_id));
    if (params?.user_ids) {
      params.user_ids.forEach((id) => query.append('user_ids[]', String(id)));
    }
    const qs = query.toString();
    const res = await apiClient.get<{ status: string; data: UserCapacity[] }>(
      `/pm/resources/capacities${qs ? `?${qs}` : ''}`
    );
    return res.data.data;
  },

  async getUserCapacity(userId: number): Promise<UserCapacity> {
    const res = await apiClient.get<{ status: string; data: UserCapacity }>(`/pm/resources/capacities/${userId}`);
    return res.data.data;
  },

  async updateUserCapacity(userId: number, payload: Partial<UserCapacity>): Promise<UserCapacity> {
    const res = await apiClient.put<{ status: string; data: UserCapacity }>(
      `/pm/resources/capacities/${userId}`,
      payload
    );
    return res.data.data;
  },

  async getBalancingSuggestions(projectUuid: string): Promise<ResourceBalancingSuggestion[]> {
    const res = await apiClient.get<{ status: string; data: ResourceBalancingSuggestion[] }>(
      `/pm/projects/${projectUuid}/balancing-suggestions`
    );
    return res.data.data;
  },

  async applyBalancingSuggestion(
    projectUuid: string,
    payload: { task_uuid: string; source_user_id: number; target_user_id: number }
  ): Promise<Task> {
    const res = await apiClient.post<{ status: string; data: Task }>(
      `/pm/projects/${projectUuid}/balancing-suggestions/apply`,
      payload
    );
    return res.data.data;
  },

  // Analytics Engine (Phase 14)
  async getProjectAnalytics(uuid: string): Promise<ProjectAnalytics> {
    const res = await apiClient.get<{ status: string; data: ProjectAnalytics }>(
      `/pm/analytics/projects/${uuid}`
    );
    return res.data.data;
  },

  async getDepartmentAnalytics(id: number): Promise<DepartmentAnalytics> {
    const res = await apiClient.get<{ status: string; data: DepartmentAnalytics }>(
      `/pm/analytics/departments/${id}`
    );
    return res.data.data;
  },

  async getDashboardAnalytics(): Promise<DashboardAnalytics> {
    const res = await apiClient.get<{ status: string; data: DashboardAnalytics }>(
      '/pm/analytics/dashboard'
    );
    return res.data.data;
  },

  // Reports & Export Engine (Phase 14)
  async exportProjectReport(
    uuid: string,
    params: {
      type?: 'progress' | 'tasks' | 'workload';
      format?: 'csv' | 'xlsx' | 'pdf';
      status?: string;
      priority?: string;
      start_date?: string;
      end_date?: string;
    }
  ): Promise<{ data: Blob; filename: string }> {
    const res = await apiClient.get(`/pm/projects/${uuid}/export`, {
      params,
      responseType: 'blob',
    });

    let filename = `pms-project-${uuid}-report.${params.format || 'csv'}`;
    const disposition = res.headers['content-disposition'];
    if (disposition) {
      const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
      if (match && match[1]) {
        filename = match[1].replace(/['"]/g, '');
      }
    }

    return { data: res.data, filename };
  },

  async exportTasksReport(
    params: {
      format?: 'csv' | 'xlsx' | 'pdf';
      project_uuid?: string;
      status?: string;
      priority?: string;
    }
  ): Promise<{ data: Blob; filename: string }> {
    const res = await apiClient.get('/pm/tasks/export', {
      params,
      responseType: 'blob',
    });

    let filename = `pms-tasks-export.${params.format || 'csv'}`;
    const disposition = res.headers['content-disposition'];
    if (disposition) {
      const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
      if (match && match[1]) {
        filename = match[1].replace(/['"]/g, '');
      }
    }

    return { data: res.data, filename };
  },
};
