export type ProjectVisibility = 'department_private' | 'shared_msa';
export type ProjectStatus = 'planning' | 'in_progress' | 'on_hold' | 'completed' | 'cancelled';
export type ProjectPriority = 'low' | 'medium' | 'high' | 'urgent';
export type ProjectRole = 'owner' | 'manager' | 'contributor' | 'viewer';
export type InvitationStatus = 'pending' | 'accepted' | 'rejected' | 'revoked';
export type TaskStatus = 'todo' | 'in_progress' | 'review' | 'completed' | 'cancelled';
export type MilestoneStatus = 'pending' | 'reached' | 'delayed';
export type DepartmentRole = 'lead' | 'member';

export interface UserSummary {
  id: number;
  name: string;
  email: string;
  avatar?: string | null;
  is_admin?: boolean;
}

export interface DepartmentSummary {
  id: number;
  name: string;
  code?: string;
  slug?: string;
  icon?: string | null;
  color?: string | null;
  role?: DepartmentRole;
  is_active?: boolean;
  description?: string | null;
}

export interface Project {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  description?: string | null;
  department_id: number;
  department?: DepartmentSummary;
  owning_department?: DepartmentSummary;
  participating_departments?: DepartmentSummary[];
  participating_department_ids?: number[];
  can_manage_participating?: boolean;
  visibility: ProjectVisibility;
  status: ProjectStatus;
  priority: ProjectPriority;
  start_date?: string | null;
  due_date?: string | null;
  owner_id?: number | null;
  owner?: UserSummary;
  members?: ProjectMember[];
  milestones?: Milestone[];
  tasks?: Task[];
  tasks_count?: number;
  milestones_count?: number;
  project_members_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface TaskList {
  id: number;
  uuid: string;
  project_id: number;
  name: string;
  order_index: number;
  color?: string | null;
  tasks_count?: number;
  created_at?: string;
}

export type DependencyType = 'finish_to_start' | 'start_to_start' | 'finish_to_finish';

export interface TaskDependency {
  id: number;
  uuid: string;
  title: string;
  status: TaskStatus;
  status_label?: string;
  priority?: ProjectPriority;
  start_date?: string | null;
  due_date?: string | null;
  dependency_type?: DependencyType;
  dependency_type_label?: string;
  lag_days?: number;
  is_conflict?: boolean;
  conflict_reason?: string | null;
  assignee?: { id: number; name: string } | null;
}

export interface Attachment {
  id: number;
  uuid: string;
  project_id: number;
  task_id?: number | null;
  original_name: string;
  mime_type: string;
  file_size: number;
  uploader?: UserSummary | null;
  task?: { id: number; uuid: string; title: string } | null;
  created_at?: string;
}

export interface Task {
  id: number;
  uuid: string;
  project_id: number;
  milestone_id?: number | null;
  task_list_id?: number | null;
  parent_task_id?: number | null;
  title: string;
  description?: string | null;
  status: TaskStatus;
  status_label?: string;
  priority: ProjectPriority;
  priority_label?: string;
  start_date?: string | null;
  due_date?: string | null;
  estimated_hours?: number | null;
  effort_source?: 'manual' | 'inferred';
  completed_at?: string | null;
  assigned_to?: number | null;
  assignee?: UserSummary | null;
  assignees?: UserSummary[];
  task_list?: TaskList | null;
  milestone?: { id: number; uuid: string; title: string } | null;
  dependencies?: TaskDependency[];
  dependents?: TaskDependency[];
  attachments?: Attachment[];
  comments_count?: number;
  subtasks_count?: number;
  dependencies_count?: number;
  dependents_count?: number;
  attachments_count?: number;
  subtasks?: Subtask[];
  project?: {
    id?: number;
    uuid: string;
    name: string;
    department_name?: string;
    department?: DepartmentSummary;
    visibility?: ProjectVisibility;
  };
}

export interface Subtask {
  id: number;
  uuid: string;
  title: string;
  status: TaskStatus;
  assignee?: { id: number; name: string } | null;
}

export interface Milestone {
  id: number;
  uuid: string;
  project_id?: number;
  title: string;
  description?: string | null;
  status: MilestoneStatus;
  status_label?: string;
  due_date?: string | null;
  tasks_count?: number;
  completed_tasks_count?: number;
  completed_at?: string | null;
}

export interface ProjectMember {
  id: number;
  user_id?: number;
  user?: UserSummary;
  project_role: ProjectRole;
  project_role_label?: string;
  invitation_status: InvitationStatus;
  invited_by?: { id: number; name: string } | null;
  invited_at?: string | null;
  accepted_at?: string | null;
  project?: {
    uuid: string;
    name: string;
    department?: DepartmentSummary;
  };
}

export interface Comment {
  id: number;
  project_id: number;
  task_id?: number | null;
  user_id: number;
  body: string;
  author?: UserSummary;
  created_at: string;
}

export interface ActivityLog {
  id: number;
  project_id?: number | null;
  user_id?: number | null;
  action: string;
  entity_type: string;
  entity_id?: number | null;
  metadata?: Record<string, any>;
  created_at: string;
  user?: UserSummary;
  project?: {
    id: number;
    uuid: string;
    name: string;
    code?: string;
  };
}

export interface CalendarEvent {
  id: string;
  uuid?: string;
  title: string;
  start: string;
  end: string;
  start_date?: string | null;
  due_date?: string | null;
  allDay: boolean;
  is_multi_day?: boolean;
  type: 'project' | 'milestone' | 'task';
  status: string;
  priority?: string;
  visibility?: ProjectVisibility;
  condition?: string;
  is_conflict?: boolean;
  conflict_reason?: string | null;
  color?: string;
  project?: {
    id: number;
    uuid: string;
    name: string;
    code?: string;
    department?: DepartmentSummary;
  };
  assigned_to?: number | null;
  assignee?: UserSummary | null;
  assignees?: UserSummary[];
}

export interface SchedulingConflictItem {
  predecessor_id: number;
  predecessor_uuid: string;
  predecessor_title: string;
  dependency_type: DependencyType;
  dependency_type_label: string;
  lag_days: number;
  conflict_type: string;
  conflict_reason: string;
  required_date: string;
  actual_date: string;
}

export interface SchedulingUnresolvedItem {
  predecessor_id: number;
  predecessor_uuid: string;
  predecessor_title: string;
  dependency_type: DependencyType;
  lag_days: number;
  reason: string;
}

export interface SchedulingInfo {
  task_id: number;
  task_uuid: string;
  task_title: string;
  start_date: string | null;
  due_date: string | null;
  has_conflicts: boolean;
  has_unresolved: boolean;
  conflicts: SchedulingConflictItem[];
  unresolved: SchedulingUnresolvedItem[];
}

export interface CascadePreviewAffectedTask {
  id: number;
  uuid: string;
  title: string;
  current_start_date: string | null;
  current_due_date: string | null;
  new_start_date: string;
  new_due_date: string;
  duration_days: number;
  shift_days: number;
}

export interface CascadePreview {
  root_task: {
    id: number;
    uuid: string;
    title: string;
    new_start_date: string;
    new_due_date: string;
  };
  total_affected_count: number;
  max_depth_reached: number;
  affected_tasks: CascadePreviewAffectedTask[];
}

export interface MilestoneCondition {
  milestone_id: number;
  milestone_uuid: string;
  condition: 'on_track' | 'at_risk' | 'delayed' | 'completed' | 'unresolved';
  milestone_due_date: string | null;
  max_task_due_date: string | null;
  exceeding_tasks_count: number;
  active_tasks_count: number;
}

export interface CalendarFeed {
  id: number;
  uuid: string;
  name: string;
  project?: {
    id: number;
    uuid: string;
    name: string;
    code: string;
  } | null;
  include_tasks: boolean;
  include_milestones: boolean;
  include_project_deadlines: boolean;
  only_assigned_to_me: boolean;
  plain_token?: string;
  webcal_url?: string;
  https_url?: string;
  last_accessed_at?: string | null;
  created_at?: string;
}

export interface CreateCalendarFeedPayload {
  name: string;
  project_uuid?: string | null;
  include_tasks?: boolean;
  include_milestones?: boolean;
  include_project_deadlines?: boolean;
  only_assigned_to_me?: boolean;
}

export interface DashboardData {
  user: UserSummary;
  departments: DepartmentSummary[];
  kpi: {
    total_projects: number;
    active_projects: number;
    completed_projects: number;
    my_open_tasks: number;
    my_overdue_tasks: number;
  };
  my_tasks: Task[];
  recent_projects: Project[];
  activity_stream: ActivityLog[];
}

export interface UserCapacity {
  id: number;
  user_id: number;
  user?: UserSummary;
  weekly_capacity_hours: number;
  daily_capacity_hours: number;
  daily_availability?: Record<string, number> | null;
  max_concurrent_tasks: number;
  effective_from?: string | null;
  effective_to?: string | null;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface GanttTask {
  id: number;
  uuid: string;
  title: string;
  status: TaskStatus;
  priority: ProjectPriority;
  start_date: string | null;
  due_date: string | null;
  duration_days: number;
  estimated_hours: number;
  effort_source: 'manual' | 'inferred';
  task_list_id: number | null;
  task_list_name?: string | null;
  milestone_id: number | null;
  milestone_name?: string | null;
  assigned_to: number | null;
  assignees: Array<{ id: number; name: string; avatar?: string | null }>;
  completed_at?: string | null;
}

export interface GanttDependency {
  id: number;
  task_id: number;
  task_uuid?: string;
  depends_on_task_id: number;
  depends_on_task_uuid?: string;
  dependency_type: DependencyType;
  lag_days: number;
  is_conflict: boolean;
  conflict_reason?: string | null;
}

export interface GanttTimelineBounds {
  start_date: string;
  end_date: string;
  total_days: number;
}

export interface GanttDataset {
  project: {
    id: number;
    uuid: string;
    name: string;
    start_date?: string | null;
    due_date?: string | null;
  };
  timeline_bounds: GanttTimelineBounds;
  tasks: GanttTask[];
  dependencies: GanttDependency[];
  milestones: Array<{
    id: number;
    uuid: string;
    title: string;
    due_date: string | null;
    status: MilestoneStatus;
    is_overdue: boolean;
  }>;
  task_lists: Array<{
    id: number;
    uuid: string;
    name: string;
    order_index: number;
  }>;
}

export interface WorkloadDailyBreakdown {
  date: string;
  day_of_week: number;
  capacity_hours: number;
  allocated_hours: number;
  external_hours: number;
  remaining_hours: number;
  active_tasks_count: number;
  utilization_rate: number;
  status: 'available' | 'under_utilized' | 'optimal' | 'over_allocated';
}

export interface MemberWorkload {
  user: UserSummary;
  capacity: {
    weekly_hours: number;
    daily_hours: number;
    max_concurrent_tasks: number;
  };
  summary: {
    total_allocated_hours: number;
    active_tasks_count: number;
    over_allocated_days: number;
    max_daily_utilization: number;
    is_over_allocated: boolean;
  };
  tasks: Array<{
    id: number | null;
    uuid: string | null;
    title: string;
    project_id: number | null;
    project_uuid?: string | null;
    project_name?: string | null;
    status: string;
    priority?: string | null;
    start_date: string | null;
    due_date: string | null;
    allocated_hours: number;
    is_confidential: boolean;
  }>;
  daily_breakdown: Record<string, WorkloadDailyBreakdown>;
}

export interface ProjectWorkloadResponse {
  project: {
    id: number;
    uuid: string;
    name: string;
  };
  date_range: {
    start: string;
    end: string;
  };
  team_workload: MemberWorkload[];
}

export interface ResourceBalancingSuggestion {
  id: string;
  task_id: number;
  task_uuid: string;
  task_title: string;
  estimated_hours: number;
  start_date: string | null;
  due_date: string | null;
  source_user: {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    current_utilization: number;
    projected_utilization: number;
  };
  suggested_assignee: {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    current_utilization: number;
    projected_utilization: number;
  };
  reason: string;
}

export interface VelocityWeek {
  week_number: number;
  week_label: string;
  start_date: string;
  end_date: string;
  tasks_created: number;
  tasks_completed: number;
}

export interface ProjectAnalytics {
  project: {
    id: number;
    uuid: string;
    name: string;
    status: string;
    completion_rate: number;
  };
  task_counts: {
    total: number;
    completed: number;
    in_progress: number;
    review?: number;
    todo: number;
    cancelled: number;
    overdue: number;
  };
  effort_hours: {
    total: number;
    completed: number;
    remaining: number;
  };
  priority_distribution: {
    low: number;
    medium: number;
    high: number;
    urgent: number;
  };
  milestones: {
    total: number;
    completed: number;
    pending: number;
    overdue: number;
    on_time_rate: number;
    avg_delay_days: number;
  };
  cycle_time_metrics: {
    avg_lead_time_days: number | null;
    avg_cycle_time_days: number | null;
    sample_size: number;
    has_sufficient_history: boolean;
  };
  velocity_trend: VelocityWeek[];
  team_workload?: any;
}

export interface DepartmentAnalytics {
  department_id: number;
  accessible_projects_count: number;
  task_counts: {
    total: number;
    completed: number;
    in_progress: number;
    overdue: number;
  };
  completion_rate: number;
  effort_hours: {
    total: number;
    completed: number;
    remaining: number;
  };
  projects: Array<{
    id: number;
    uuid: string;
    name: string;
    status: string;
    total_tasks: number;
    completed_tasks: number;
    completion_rate: number;
  }>;
  velocity_trend: VelocityWeek[];
}

export interface DashboardAnalytics {
  accessible_projects_count: number;
  total_tasks: number;
  completed_tasks: number;
  in_progress_tasks: number;
  overdue_tasks: number;
  my_open_tasks: number;
  my_overdue_tasks: number;
  completion_rate: number;
  velocity_trend: VelocityWeek[];
}

