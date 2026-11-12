<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { projectsApi } from '../api/projects';
import { useAuthStore } from '../stores/auth';
import { useDepartmentsStore } from '../stores/departments';
import type { Attachment, CalendarEvent, MemberWorkload, Milestone, Project, ProjectMember, Task, TaskList, TaskStatus, UserSummary } from '../types/pm';
import TaskKanbanBoard from '../components/tasks/TaskKanbanBoard.vue';
import TaskListBoard from '../components/tasks/TaskListBoard.vue';
import TaskCreateModal from '../components/tasks/TaskCreateModal.vue';
import TaskDetailModal from '../components/tasks/TaskDetailModal.vue';
import MilestoneList from '../components/milestones/MilestoneList.vue';
import ProjectMembersModal from '../components/projects/ProjectMembersModal.vue';
import AttachmentManager from '../components/attachments/AttachmentManager.vue';
import CalendarSubscribeModal from '../components/calendar/CalendarSubscribeModal.vue';
import GanttChart from '../components/gantt/GanttChart.vue';
import WorkloadHeatmap from '../components/workload/WorkloadHeatmap.vue';
import ResourceBalancingModal from '../components/workload/ResourceBalancingModal.vue';
import UserCapacityModal from '../components/workload/UserCapacityModal.vue';
import ProjectAnalyticsTab from '../components/analytics/ProjectAnalyticsTab.vue';
import ExportReportModal from '../components/reports/ExportReportModal.vue';
import {
  Lock,
  Globe,
  CheckSquare,
  Users,
  Flag,
  Settings,
  Plus,
  Loader2,
  ArrowLeft,
  Building2,
  X,
  Save,
  CheckCircle2,
  AlertCircle,
  Layers,
  Paperclip,
  CheckSquare2,
  Search,
  Calendar,
  ChevronLeft,
  ChevronRight,
  Rss,
  BarChart3,
  Download,
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const departmentsStore = useDepartmentsStore();

const uuid = computed(() => route.params.uuid as string);
const project = ref<Project | null>(null);
const tasks = ref<Task[]>([]);
const taskLists = ref<TaskList[]>([]);
const milestones = ref<Milestone[]>([]);
const members = ref<ProjectMember[]>([]);
const eligibleAssignees = ref<UserSummary[]>([]);
const projectAttachments = ref<Attachment[]>([]);

const activeTab = ref<'tasks' | 'milestones' | 'gantt' | 'workload' | 'attachments' | 'calendar' | 'analytics' | 'settings'>('tasks');
const taskListViewMode = ref<'status' | 'lists'>('status');
const isLoading = ref(true);
const errorMessage = ref<string | null>(null);

// Modals
const showTaskCreateModal = ref(false);
const taskCreateInitialStatus = ref<TaskStatus>('todo');
const taskCreateInitialListId = ref<number | null>(null);
const activeTaskUuid = ref<string | null>(null);
const showMembersModal = ref(false);
const showSubscribeModal = ref(false);
const showBalancingModal = ref(false);
const showCapacityModal = ref(false);
const showExportModal = ref(false);
const selectedMemberForCapacity = ref<MemberWorkload | null>(null);

// Project Workload State (Phase 13)
const projectWorkloadData = ref<MemberWorkload[]>([]);
const isWorkloadLoading = ref(false);
const workloadStartDate = ref(new Date().toISOString().split('T')[0]);
const workloadEndDate = ref(new Date(Date.now() + 28 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);

const loadProjectWorkload = async () => {
  if (!uuid.value) return;
  isWorkloadLoading.value = true;
  try {
    const res = await projectsApi.getProjectWorkload(uuid.value, workloadStartDate.value, workloadEndDate.value);
    projectWorkloadData.value = res.team_workload || [];
  } catch (err) {
    console.error('Failed to load project workload:', err);
    projectWorkloadData.value = [];
  } finally {
    isWorkloadLoading.value = false;
  }
};

const openCapacityConfig = (member: MemberWorkload) => {
  selectedMemberForCapacity.value = member;
  showCapacityModal.value = true;
};

// Project Calendar & Schedule State (Phase 12)
const projectCalendarEvents = ref<CalendarEvent[]>([]);
const isCalendarLoading = ref(false);
const calendarCurrentDate = ref(new Date());
const projectStartDate = ref('');
const projectDueDate = ref('');
const isSavingProjectSchedule = ref(false);
const scheduleFeedback = ref<{ type: 'success' | 'error'; message: string } | null>(null);

const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

const calendarMonthLabel = computed(() => {
  return `${monthNames[calendarCurrentDate.value.getMonth()]} ${calendarCurrentDate.value.getFullYear()}`;
});

const formatProjectDateStr = (d: Date): string => {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const loadProjectCalendar = async () => {
  if (!uuid.value) return;
  isCalendarLoading.value = true;
  try {
    const year = calendarCurrentDate.value.getFullYear();
    const month = calendarCurrentDate.value.getMonth();
    const startObj = new Date(year, month - 1, 1);
    const endObj = new Date(year, month + 2, 0);
    const start = formatProjectDateStr(startObj);
    const end = formatProjectDateStr(endObj);

    projectCalendarEvents.value = await projectsApi.getCalendarEvents({
      project_uuid: uuid.value,
      start,
      end,
    });
  } catch (err) {
    console.error('Failed to load project calendar:', err);
    projectCalendarEvents.value = [];
  } finally {
    isCalendarLoading.value = false;
  }
};

const calendarPrevMonth = () => {
  calendarCurrentDate.value = new Date(calendarCurrentDate.value.getFullYear(), calendarCurrentDate.value.getMonth() - 1, 1);
  loadProjectCalendar();
};

const calendarNextMonth = () => {
  calendarCurrentDate.value = new Date(calendarCurrentDate.value.getFullYear(), calendarCurrentDate.value.getMonth() + 1, 1);
  loadProjectCalendar();
};

const calendarToday = () => {
  calendarCurrentDate.value = new Date();
  loadProjectCalendar();
};

const isProjectEventOnDate = (event: CalendarEvent, dateStr: string): boolean => {
  const startRaw = event.start || event.start_date || event.due_date;
  const endRaw = event.end || event.due_date || startRaw;
  if (!startRaw || !endRaw) return false;
  let start = String(startRaw).substring(0, 10);
  let end = String(endRaw).substring(0, 10);
  if (start > end) {
    const tmp = start;
    start = end;
    end = tmp;
  }
  return dateStr >= start && dateStr <= end;
};

const projectCalendarDays = computed(() => {
  const year = calendarCurrentDate.value.getFullYear();
  const month = calendarCurrentDate.value.getMonth();

  const firstDayOfMonth = new Date(year, month, 1);
  const lastDayOfMonth = new Date(year, month + 1, 0);

  const daysInMonth = lastDayOfMonth.getDate();
  const startingDayOfWeek = firstDayOfMonth.getDay();

  const days: { dateStr: string; dayNumber: number; isCurrentMonth: boolean; events: CalendarEvent[] }[] = [];

  const prevMonthLastDay = new Date(year, month, 0).getDate();
  for (let i = startingDayOfWeek - 1; i >= 0; i--) {
    const d = prevMonthLastDay - i;
    const dateObj = new Date(year, month - 1, d);
    const dateStr = formatProjectDateStr(dateObj);
    days.push({
      dateStr,
      dayNumber: d,
      isCurrentMonth: false,
      events: projectCalendarEvents.value.filter((e) => isProjectEventOnDate(e, dateStr)),
    });
  }

  for (let d = 1; d <= daysInMonth; d++) {
    const dateObj = new Date(year, month, d);
    const dateStr = formatProjectDateStr(dateObj);
    days.push({
      dateStr,
      dayNumber: d,
      isCurrentMonth: true,
      events: projectCalendarEvents.value.filter((e) => isProjectEventOnDate(e, dateStr)),
    });
  }

  const remaining = (7 - (days.length % 7)) % 7;
  for (let d = 1; d <= remaining; d++) {
    const dateObj = new Date(year, month + 1, d);
    const dateStr = formatProjectDateStr(dateObj);
    days.push({
      dateStr,
      dayNumber: d,
      isCurrentMonth: false,
      events: projectCalendarEvents.value.filter((e) => isProjectEventOnDate(e, dateStr)),
    });
  }

  return days;
});

const onCalendarEventClick = (event: CalendarEvent) => {
  if (event.type === 'task' && event.uuid) {
    activeTaskUuid.value = event.uuid;
  } else if (event.type === 'milestone') {
    activeTab.value = 'milestones';
  } else if (event.type === 'project') {
    activeTab.value = 'settings';
  }
};

watch(activeTab, (newTab) => {
  if (newTab === 'calendar' && projectCalendarEvents.value.length === 0) {
    loadProjectCalendar();
  }
  if (newTab === 'workload' && projectWorkloadData.value.length === 0) {
    loadProjectWorkload();
  }
});

// Bulk Operations State
const isBulkMode = ref(false);
const selectedTaskUuids = ref<string[]>([]);
const bulkActionType = ref<'status' | 'task_list' | 'priority' | 'assign' | 'reschedule_shift' | 'set_dates' | 'rebalance_assignee'>('status');
const bulkStatusValue = ref<TaskStatus>('todo');
const bulkPriorityValue = ref<'low' | 'medium' | 'high' | 'urgent'>('medium');
const bulkTaskListValue = ref<string>('uncategorized');
const bulkAssigneeValue = ref<string>('');
const bulkShiftDays = ref<number>(1);
const bulkStartDate = ref<string>('');
const bulkDueDate = ref<string>('');
const bulkRebalanceSourceId = ref<string>('');
const bulkRebalanceTargetId = ref<string>('');
const isExecutingBulk = ref(false);
const bulkFeedbackMessage = ref<string | null>(null);

// Participating Departments Management State
const editableParticipatingIds = ref<number[]>([]);
const isSavingParticipating = ref(false);
const participatingFeedback = ref<{ type: 'success' | 'error'; message: string } | null>(null);
const addParticipantSearch = ref('');

const canEdit = computed(() => {
  if (!project.value || !authStore.user) return false;
  if (authStore.user.is_admin) return true;
  if (project.value.owner_id === authStore.user.id) return true;
  return members.value.some(
    (m) => m.user_id === authStore.user?.id && (m.project_role === 'owner' || m.project_role === 'manager')
  );
});

const canManageParticipating = computed(() => {
  return project.value?.can_manage_participating ?? canEdit.value;
});

const availableToAddDepartments = computed(() => {
  const owningId = project.value?.department_id;
  const currentIds = editableParticipatingIds.value;
  const query = addParticipantSearch.value.trim().toLowerCase();
  return departmentsStore.departments.filter((d) => {
    if (d.id === owningId) return false;
    if (currentIds.includes(d.id)) return false;
    if (!query) return true;
    return (
      d.name.toLowerCase().includes(query) ||
      (d.code && d.code.toLowerCase().includes(query)) ||
      (d.slug && d.slug.toLowerCase().includes(query))
    );
  });
});

const addParticipatingDept = (deptId: number) => {
  if (!editableParticipatingIds.value.includes(deptId)) {
    editableParticipatingIds.value.push(deptId);
  }
  addParticipantSearch.value = '';
};

const removeParticipatingDept = (deptId: number) => {
  editableParticipatingIds.value = editableParticipatingIds.value.filter((id) => id !== deptId);
};

const saveParticipatingDepartments = async () => {
  if (!project.value) return;
  isSavingParticipating.value = true;
  participatingFeedback.value = null;

  try {
    const updated = await projectsApi.updateProject(uuid.value, {
      participating_department_ids: editableParticipatingIds.value,
    });
    project.value = { ...project.value, ...updated.project };
    editableParticipatingIds.value = updated.project.participating_department_ids || [];
    participatingFeedback.value = {
      type: 'success',
      message: 'Participating departments updated successfully. Option B access updated.',
    };
    setTimeout(() => {
      if (participatingFeedback.value?.type === 'success') {
        participatingFeedback.value = null;
      }
    }, 4000);
  } catch (err: any) {
    participatingFeedback.value = {
      type: 'error',
      message: err.response?.data?.message || 'Failed to update participating departments.',
    };
  } finally {
    isSavingParticipating.value = false;
  }
};

const saveProjectSchedule = async () => {
  if (!project.value) return;
  isSavingProjectSchedule.value = true;
  scheduleFeedback.value = null;

  try {
    const updated = await projectsApi.updateProject(uuid.value, {
      start_date: projectStartDate.value || null,
      due_date: projectDueDate.value || null,
    });
    project.value = { ...project.value, ...updated.project };
    projectStartDate.value = updated.project.start_date ? String(updated.project.start_date).substring(0, 10) : '';
    projectDueDate.value = updated.project.due_date ? String(updated.project.due_date).substring(0, 10) : '';
    scheduleFeedback.value = {
      type: 'success',
      message: 'Project schedule & active duration period updated successfully.',
    };
    await loadProjectCalendar();
    setTimeout(() => {
      if (scheduleFeedback.value?.type === 'success') {
        scheduleFeedback.value = null;
      }
    }, 4000);
  } catch (err: any) {
    scheduleFeedback.value = {
      type: 'error',
      message: err.response?.data?.message || 'Failed to update project schedule.',
    };
  } finally {
    isSavingProjectSchedule.value = false;
  }
};

const loadProject = async () => {
  isLoading.value = true;
  errorMessage.value = null;
  try {
    project.value = await projectsApi.getProject(uuid.value);
    editableParticipatingIds.value = project.value.participating_department_ids || [];
    projectStartDate.value = project.value.start_date ? String(project.value.start_date).substring(0, 10) : '';
    projectDueDate.value = project.value.due_date ? String(project.value.due_date).substring(0, 10) : '';
    departmentsStore.loadDepartments().catch(() => {});
    await Promise.all([
      loadTasks(),
      loadTaskLists(),
      loadMilestones(),
      loadMembers(),
      loadEligibleAssignees(),
      loadProjectAttachments(),
    ]);
  } catch (err: any) {
    if (err.response?.status === 403) {
      errorMessage.value = 'Access Denied: This project is Department-Private. Only active department team members or invited collaborators may access it.';
    } else {
      errorMessage.value = err.response?.data?.message || 'Failed to load project.';
    }
  } finally {
    isLoading.value = false;
  }
};

const loadTasks = async () => {
  try {
    const data = await projectsApi.getTasks(uuid.value);
    tasks.value = data.tasks;
  } catch (err) {
    console.error('Failed to load tasks:', err);
  }
};

const loadTaskLists = async () => {
  try {
    const data = await projectsApi.getTaskLists(uuid.value);
    taskLists.value = data.lists;
  } catch (err) {
    console.error('Failed to load task lists:', err);
  }
};

const loadMilestones = async () => {
  try {
    milestones.value = await projectsApi.getMilestones(uuid.value);
  } catch (err) {
    console.error('Failed to load milestones:', err);
  }
};

const loadMembers = async () => {
  try {
    members.value = await projectsApi.getProjectMembers(uuid.value);
  } catch (err) {
    console.error('Failed to load members:', err);
  }
};

const loadEligibleAssignees = async () => {
  try {
    eligibleAssignees.value = await projectsApi.getEligibleAssignees(uuid.value);
  } catch (err) {
    console.error('Failed to load eligible assignees:', err);
  }
};

const loadProjectAttachments = async () => {
  try {
    projectAttachments.value = await projectsApi.getProjectAttachments(uuid.value);
  } catch (err) {
    console.error('Failed to load project attachments:', err);
  }
};

onMounted(() => {
  loadProject();
});

const openAddTask = (status: TaskStatus = 'todo') => {
  taskCreateInitialStatus.value = status;
  taskCreateInitialListId.value = null;
  showTaskCreateModal.value = true;
};

const handleAddTaskToList = (listId: number | null) => {
  taskCreateInitialStatus.value = 'todo';
  taskCreateInitialListId.value = listId;
  showTaskCreateModal.value = true;
};

const onTaskClick = (task: Task) => {
  if (isBulkMode.value) {
    handleToggleSelectTask(task);
    return;
  }
  activeTaskUuid.value = task.uuid;
};

const onTaskStatusChange = async (taskUuid: string, newStatus: TaskStatus) => {
  try {
    await projectsApi.updateTaskStatus(taskUuid, newStatus);
    await loadTasks();
  } catch (err) {
    console.error('Failed to update task status:', err);
  }
};

// Task List Management Handlers
const handleCreateList = async (name: string, color?: string) => {
  try {
    await projectsApi.createTaskList(uuid.value, { name, color });
    await loadTaskLists();
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to create section.');
  }
};

const handleUpdateList = async (listUuid: string, name: string, color?: string) => {
  try {
    await projectsApi.updateTaskList(listUuid, { name, color });
    await loadTaskLists();
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to rename section.');
  }
};

const handleDeleteList = async (listUuid: string) => {
  try {
    await projectsApi.deleteTaskList(listUuid);
    await Promise.all([loadTaskLists(), loadTasks()]);
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to delete section.');
  }
};

const handleReorderLists = async (listUuids: string[]) => {
  try {
    await projectsApi.reorderTaskLists(uuid.value, listUuids);
    await loadTaskLists();
  } catch (err: any) {
    console.error('Failed to reorder sections:', err);
  }
};

const handleMoveTaskToList = async (taskUuid: string, targetListId: number | null) => {
  try {
    await projectsApi.updateTask(taskUuid, { task_list_id: targetListId });
    await loadTasks();
  } catch (err: any) {
    console.error('Failed to move task to section:', err);
  }
};

// Bulk Operations Handlers
const handleToggleSelectTask = (task: Task) => {
  const index = selectedTaskUuids.value.indexOf(task.uuid);
  if (index === -1) {
    selectedTaskUuids.value.push(task.uuid);
  } else {
    selectedTaskUuids.value.splice(index, 1);
  }
};

const toggleSelectAll = () => {
  if (selectedTaskUuids.value.length === tasks.value.length) {
    selectedTaskUuids.value = [];
  } else {
    selectedTaskUuids.value = tasks.value.map((t) => t.uuid);
  }
};

const cancelBulkMode = () => {
  isBulkMode.value = false;
  selectedTaskUuids.value = [];
  bulkFeedbackMessage.value = null;
};

const executeBulkAction = async () => {
  if (selectedTaskUuids.value.length === 0 || isExecutingBulk.value) return;

  isExecutingBulk.value = true;
  bulkFeedbackMessage.value = null;

  let val: any = null;
  if (bulkActionType.value === 'status') {
    val = bulkStatusValue.value;
  } else if (bulkActionType.value === 'priority') {
    val = bulkPriorityValue.value;
  } else if (bulkActionType.value === 'task_list') {
    val = bulkTaskListValue.value === 'uncategorized' || bulkTaskListValue.value === '' ? null : Number(bulkTaskListValue.value);
  } else if (bulkActionType.value === 'assign') {
    val = bulkAssigneeValue.value ? Number(bulkAssigneeValue.value) : null;
  } else if (bulkActionType.value === 'reschedule_shift') {
    val = { shift_days: Number(bulkShiftDays.value) };
  } else if (bulkActionType.value === 'set_dates') {
    val = {
      start_date: bulkStartDate.value || null,
      due_date: bulkDueDate.value || null,
    };
  } else if (bulkActionType.value === 'rebalance_assignee') {
    val = {
      source_user_id: Number(bulkRebalanceSourceId.value),
      target_user_id: Number(bulkRebalanceTargetId.value),
    };
  }

  try {
    const res = await projectsApi.bulkUpdateTasks({
      project_uuid: uuid.value,
      task_uuids: selectedTaskUuids.value,
      action: bulkActionType.value,
      value: val,
    });
    bulkFeedbackMessage.value = res.message || `Successfully updated ${res.updated_count} tasks.`;
    selectedTaskUuids.value = [];
    await loadTasks();
    setTimeout(() => {
      bulkFeedbackMessage.value = null;
    }, 4000);
  } catch (err: any) {
    bulkFeedbackMessage.value = err.response?.data?.message || 'Bulk operation failed.';
  } finally {
    isExecutingBulk.value = false;
  }
};

const handleArchive = async () => {
  if (!confirm('Archive this project?')) return;
  try {
    await projectsApi.archiveProject(uuid.value);
    await loadProject();
  } catch (err) {
    console.error('Failed to archive project:', err);
  }
};

const handleDelete = async () => {
  if (!confirm('Are you sure you want to permanently delete this project and all its tasks?')) return;
  try {
    await projectsApi.deleteProject(uuid.value);
    router.push('/projects');
  } catch (err) {
    console.error('Failed to delete project:', err);
  }
};
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-200">
    <!-- Back to projects -->
    <div>
      <router-link
        to="/projects"
        class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#6b7280] hover:text-[#640c0e] transition-colors"
      >
        <ArrowLeft class="w-4 h-4" />
        <span>Back to Projects</span>
      </router-link>
    </div>

    <!-- Error Banner (403 Private boundary) -->
    <div
      v-if="errorMessage"
      class="p-6 rounded-2xl bg-white border border-[#ea2128]/20 shadow-elevated space-y-3 max-w-lg mx-auto text-center"
    >
      <div class="w-12 h-12 rounded-2xl bg-[#ea2128]/10 text-[#ea2128] flex items-center justify-center mx-auto">
        <Lock class="w-6 h-6" />
      </div>
      <h3 class="text-base font-bold text-[#1e1e1e] font-display">Private Project Boundary</h3>
      <p class="text-xs text-[#6b7280] leading-relaxed">{{ errorMessage }}</p>
      <router-link
        to="/projects"
        class="inline-block px-4 py-2 rounded-xl text-xs font-bold bg-[#640c0e] text-white hover:bg-[#7e1012]"
      >
        Return to My Projects
      </router-link>
    </div>

    <!-- Loading -->
    <div v-else-if="isLoading" class="py-24 flex items-center justify-center text-[#6b7280]">
      <Loader2 class="w-8 h-8 animate-spin text-[#640c0e]" />
    </div>

    <!-- Project Workspace Container -->
    <div v-else-if="project" class="space-y-6">
      <!-- Project Header Card -->
      <div class="bg-white rounded-2xl border border-[#ebe8de] p-6 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
          <div>
            <div class="flex items-center gap-2 mb-2 flex-wrap">
              <span
                v-if="project.visibility === 'department_private'"
                class="text-xs font-semibold flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#fbfbf9] text-[#640c0e] border border-[#ebe8de]"
              >
                <Lock class="w-3.5 h-3.5" /> Department Private
              </span>
              <span
                v-else
                class="text-xs font-semibold flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#fbfbf9] text-blue-600 border border-[#ebe8de]"
              >
                <Globe class="w-3.5 h-3.5" /> Shared MSA
              </span>

              <span class="text-xs font-bold capitalize px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-700">
                {{ project.status.replace('_', ' ') }}
              </span>

              <span class="text-xs font-bold capitalize px-2.5 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200">
                Priority: {{ project.priority }}
              </span>
            </div>

            <h1 class="text-2xl font-extrabold text-[#1e1e1e] font-display">
              {{ project.name }}
            </h1>
            <p class="text-xs text-[#6b7280] mt-1 max-w-2xl leading-relaxed">
              {{ project.description || 'No detailed scope provided.' }}
            </p>

            <!-- Department Hierarchy & Collaboration Display -->
            <div class="flex items-center gap-2 flex-wrap pt-3 text-xs">
              <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-[#640c0e]/10 border border-[#640c0e]/20 text-[#640c0e]">
                <Building2 class="w-3.5 h-3.5" />
                <span class="font-bold">Owning Dept:</span>
                <span class="font-semibold">{{ project.owning_department?.name || project.department?.name }}</span>
                <span class="text-[10px] font-mono px-1 py-0.5 bg-[#640c0e]/15 rounded">
                  {{ project.owning_department?.code || project.department?.code || 'DEPT' }}
                </span>
              </div>

              <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-gray-50 border border-gray-200 text-gray-700">
                <Users class="w-3.5 h-3.5 text-gray-500" />
                <span class="font-bold">Participating:</span>
                <template v-if="project.participating_departments && project.participating_departments.length > 0">
                  <div class="flex items-center gap-1 flex-wrap">
                    <span
                      v-for="pDept in project.participating_departments"
                      :key="pDept.id"
                      class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-white border border-gray-300 text-gray-800 text-[11px]"
                    >
                      <span>{{ pDept.name }}</span>
                      <span class="text-[9px] font-mono text-gray-500 font-bold">({{ pDept.code }})</span>
                    </span>
                  </div>
                </template>
                <span v-else class="text-gray-400 italic text-[11px]">
                  None (Single-department)
                </span>
              </div>

              <!-- Project Schedule / Active Duration Display -->
              <div v-if="project.start_date || project.due_date" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-[#640c0e]/5 border border-[#640c0e]/15 text-[#640c0e]">
                <Calendar class="w-3.5 h-3.5 text-[#640c0e]" />
                <span class="font-bold">Active Period:</span>
                <span>{{ project.start_date ? String(project.start_date).substring(0, 10) : 'Start' }} → {{ project.due_date ? String(project.due_date).substring(0, 10) : 'Ongoing' }}</span>
              </div>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="showExportModal = true"
              class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-[#fbfbf9] text-[#1e1e1e] border border-[#ebe8de] hover:border-[#640c0e]/30 transition-colors"
            >
              <Download class="w-4 h-4 text-[#640c0e]" />
              <span>Export</span>
            </button>

            <button
              type="button"
              @click="showMembersModal = true"
              class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-[#fbfbf9] text-[#1e1e1e] border border-[#ebe8de] hover:border-[#640c0e]/30 transition-colors"
            >
              <Users class="w-4 h-4 text-[#640c0e]" />
              <span>Team ({{ members.length }})</span>
            </button>

            <button
              v-if="canEdit"
              type="button"
              @click="openAddTask('todo')"
              class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-[#640c0e] text-white hover:bg-[#7e1012] shadow-xs transition-colors"
            >
              <Plus class="w-4 h-4" />
              <span>Add Task</span>
            </button>
          </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center justify-between gap-2 pt-3 border-t border-[#ebe8de] flex-wrap">
          <div class="flex items-center gap-2 flex-wrap">
            <button
              type="button"
              @click="activeTab = 'tasks'"
              class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
              :class="[
                activeTab === 'tasks'
                  ? 'bg-[#640c0e] text-white shadow-xs'
                  : 'text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#fbfbf9]',
              ]"
            >
              <CheckSquare class="w-3.5 h-3.5" />
              <span>Tasks ({{ tasks.length }})</span>
            </button>

            <button
              type="button"
              @click="activeTab = 'milestones'"
              class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
              :class="[
                activeTab === 'milestones'
                  ? 'bg-[#640c0e] text-white shadow-xs'
                  : 'text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#fbfbf9]',
              ]"
            >
              <Flag class="w-3.5 h-3.5" />
              <span>Milestones ({{ milestones.length }})</span>
            </button>

            <button
              type="button"
              @click="activeTab = 'gantt'"
              class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
              :class="[
                activeTab === 'gantt'
                  ? 'bg-[#640c0e] text-white shadow-xs'
                  : 'text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#fbfbf9]',
              ]"
            >
              <Layers class="w-3.5 h-3.5" />
              <span>Gantt Chart</span>
            </button>

            <button
              type="button"
              @click="activeTab = 'workload'"
              class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
              :class="[
                activeTab === 'workload'
                  ? 'bg-[#640c0e] text-white shadow-xs'
                  : 'text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#fbfbf9]',
              ]"
            >
              <Users class="w-3.5 h-3.5" />
              <span>Workload</span>
            </button>

            <button
              type="button"
              @click="activeTab = 'attachments'"
              class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
              :class="[
                activeTab === 'attachments'
                  ? 'bg-[#640c0e] text-white shadow-xs'
                  : 'text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#fbfbf9]',
              ]"
            >
              <Paperclip class="w-3.5 h-3.5" />
              <span>Project Files</span>
            </button>

            <button
              type="button"
              @click="activeTab = 'calendar'"
              class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
              :class="[
                activeTab === 'calendar'
                  ? 'bg-[#640c0e] text-white shadow-xs'
                  : 'text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#fbfbf9]',
              ]"
            >
              <Calendar class="w-3.5 h-3.5" />
              <span>Calendar</span>
            </button>

            <button
              type="button"
              @click="activeTab = 'analytics'"
              class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
              :class="[
                activeTab === 'analytics'
                  ? 'bg-[#640c0e] text-white shadow-xs'
                  : 'text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#fbfbf9]',
              ]"
            >
              <BarChart3 class="w-3.5 h-3.5" />
              <span>Analytics</span>
            </button>

            <button
              v-if="canEdit"
              type="button"
              @click="activeTab = 'settings'"
              class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
              :class="[
                activeTab === 'settings'
                  ? 'bg-[#640c0e] text-white shadow-xs'
                  : 'text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#fbfbf9]',
              ]"
            >
              <Settings class="w-3.5 h-3.5" />
              <span>Settings</span>
            </button>
          </div>

          <!-- Secondary View / Tooling Bar for Tasks Tab -->
          <div v-if="activeTab === 'tasks'" class="flex items-center gap-2">
            <!-- View Mode Selector: Status vs Task Lists (Custom Sections) -->
            <div class="inline-flex items-center p-0.5 rounded-xl bg-gray-100 border border-[#ebe8de] text-xs">
              <button
                type="button"
                @click="taskListViewMode = 'status'"
                class="px-2.5 py-1 rounded-lg font-bold transition-all"
                :class="[
                  taskListViewMode === 'status'
                    ? 'bg-white text-[#640c0e] shadow-2xs'
                    : 'text-gray-500 hover:text-gray-900',
                ]"
              >
                Status Board
              </button>
              <button
                type="button"
                @click="taskListViewMode = 'lists'"
                class="px-2.5 py-1 rounded-lg font-bold transition-all flex items-center gap-1"
                :class="[
                  taskListViewMode === 'lists'
                    ? 'bg-white text-[#640c0e] shadow-2xs'
                    : 'text-gray-500 hover:text-gray-900',
                ]"
              >
                <Layers class="w-3 h-3" />
                <span>Sections ({{ taskLists.length }})</span>
              </button>
            </div>

            <!-- Bulk Select Toggle -->
            <button
              v-if="canEdit"
              type="button"
              @click="isBulkMode = !isBulkMode"
              class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-bold transition-all border"
              :class="[
                isBulkMode
                  ? 'bg-[#640c0e]/10 border-[#640c0e] text-[#640c0e]'
                  : 'bg-white border-[#ebe8de] text-gray-700 hover:bg-gray-50',
              ]"
            >
              <CheckSquare2 class="w-3.5 h-3.5" />
              <span>{{ isBulkMode ? 'Done Selecting' : 'Select Tasks' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Bulk Operations Floating Bar -->
      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="transform translate-y-4 opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="transform translate-y-0 opacity-100"
        leave-to-class="transform translate-y-4 opacity-0"
      >
        <div
          v-if="isBulkMode"
          class="sticky bottom-4 z-30 p-3 sm:p-4 rounded-2xl bg-white border border-[#640c0e]/30 shadow-2xl flex flex-col sm:flex-row items-center justify-between gap-3 text-xs"
        >
          <div class="flex items-center gap-3">
            <span class="font-extrabold text-[#640c0e] font-display">
              {{ selectedTaskUuids.length }} tasks selected
            </span>
            <button
              type="button"
              @click="toggleSelectAll"
              class="underline font-semibold text-gray-600 hover:text-black"
            >
              {{ selectedTaskUuids.length === tasks.length ? 'Deselect All' : 'Select All' }}
            </button>
          </div>

          <!-- Bulk Action Controls -->
          <div class="flex items-center gap-2 flex-wrap">
            <select
              v-model="bulkActionType"
              class="px-2.5 py-1.5 rounded-xl border border-[#ebe8de] font-semibold bg-white text-xs outline-none"
            >
              <option value="status">Change Status</option>
              <option value="task_list">Move to Section</option>
              <option value="priority">Set Priority</option>
              <option value="assign">Assign Deliverable</option>
              <option value="reschedule_shift">Shift Schedule (+/- Days)</option>
              <option value="set_dates">Set Fixed Dates</option>
              <option value="rebalance_assignee">Transfer Assignee</option>
            </select>

            <!-- Status Choice -->
            <select
              v-if="bulkActionType === 'status'"
              v-model="bulkStatusValue"
              class="px-2.5 py-1.5 rounded-xl border border-[#ebe8de] font-semibold bg-white text-xs outline-none"
            >
              <option value="todo">To Do</option>
              <option value="in_progress">In Progress</option>
              <option value="review">Review</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>

            <!-- Priority Choice -->
            <select
              v-else-if="bulkActionType === 'priority'"
              v-model="bulkPriorityValue"
              class="px-2.5 py-1.5 rounded-xl border border-[#ebe8de] font-semibold bg-white text-xs outline-none"
            >
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>

            <!-- Section Choice -->
            <select
              v-else-if="bulkActionType === 'task_list'"
              v-model="bulkTaskListValue"
              class="px-2.5 py-1.5 rounded-xl border border-[#ebe8de] font-semibold bg-white text-xs outline-none"
            >
              <option value="uncategorized">Uncategorized</option>
              <option v-for="list in taskLists" :key="list.id" :value="list.id.toString()">
                {{ list.name }}
              </option>
            </select>

            <!-- Assignee Choice -->
            <select
              v-else-if="bulkActionType === 'assign'"
              v-model="bulkAssigneeValue"
              class="px-2.5 py-1.5 rounded-xl border border-[#ebe8de] font-semibold bg-white text-xs outline-none"
            >
              <option value="">Unassigned</option>
              <option v-for="user in eligibleAssignees" :key="user.id" :value="user.id.toString()">
                {{ user.name }} ({{ user.email }})
              </option>
            </select>

            <!-- Reschedule Shift -->
            <div v-else-if="bulkActionType === 'reschedule_shift'" class="flex items-center gap-1">
              <input
                v-model.number="bulkShiftDays"
                type="number"
                placeholder="Days"
                class="w-20 px-2.5 py-1.5 rounded-xl border border-[#ebe8de] font-semibold bg-white text-xs outline-none"
              />
              <span class="text-xs text-gray-500 font-medium">days</span>
            </div>

            <!-- Set Fixed Dates -->
            <div v-else-if="bulkActionType === 'set_dates'" class="flex items-center gap-1">
              <input
                v-model="bulkStartDate"
                type="date"
                class="px-2 py-1.5 rounded-xl border border-[#ebe8de] font-semibold bg-white text-xs outline-none"
              />
              <span class="text-xs text-gray-400">to</span>
              <input
                v-model="bulkDueDate"
                type="date"
                class="px-2 py-1.5 rounded-xl border border-[#ebe8de] font-semibold bg-white text-xs outline-none"
              />
            </div>

            <!-- Rebalance Assignee -->
            <div v-else-if="bulkActionType === 'rebalance_assignee'" class="flex items-center gap-1">
              <select
                v-model="bulkRebalanceSourceId"
                class="px-2 py-1.5 rounded-xl border border-[#ebe8de] font-semibold bg-white text-xs outline-none"
              >
                <option value="">From (Source)</option>
                <option v-for="user in eligibleAssignees" :key="user.id" :value="user.id.toString()">
                  {{ user.name }}
                </option>
              </select>
              <span class="text-xs text-gray-400">→</span>
              <select
                v-model="bulkRebalanceTargetId"
                class="px-2 py-1.5 rounded-xl border border-[#ebe8de] font-semibold bg-white text-xs outline-none"
              >
                <option value="">To (Target)</option>
                <option v-for="user in eligibleAssignees" :key="user.id" :value="user.id.toString()">
                  {{ user.name }}
                </option>
              </select>
            </div>

            <button
              type="button"
              @click="executeBulkAction"
              :disabled="selectedTaskUuids.length === 0 || isExecutingBulk"
              class="px-4 py-1.5 rounded-xl font-bold bg-[#640c0e] text-white hover:bg-[#7e1012] disabled:opacity-50 transition-colors flex items-center gap-1 shadow-xs"
            >
              <Loader2 v-if="isExecutingBulk" class="w-3.5 h-3.5 animate-spin" />
              <span>Apply</span>
            </button>

            <button
              type="button"
              @click="cancelBulkMode"
              class="px-3 py-1.5 rounded-xl font-semibold border border-gray-300 text-gray-700 hover:bg-gray-100 transition-colors"
            >
              Cancel
            </button>
          </div>

          <div v-if="bulkFeedbackMessage" class="w-full text-center text-xs font-semibold text-[#640c0e]">
            {{ bulkFeedbackMessage }}
          </div>
        </div>
      </transition>

      <!-- Tab Contents -->

      <!-- 1. Tasks Tab -->
      <div v-if="activeTab === 'tasks'">
        <!-- View Mode: Status Board (Kanban) -->
        <div v-if="taskListViewMode === 'status'">
          <TaskKanbanBoard
            :tasks="tasks"
            :can-edit="canEdit"
            :selectable="isBulkMode"
            :selected-uuids="selectedTaskUuids"
            @task-click="onTaskClick"
            @add-task="openAddTask"
            @status-change="onTaskStatusChange"
            @toggle-select="handleToggleSelectTask"
          />
        </div>

        <!-- View Mode: Task Lists / Custom Sections (Decision 2) -->
        <div v-else-if="taskListViewMode === 'lists'">
          <TaskListBoard
            :tasks="tasks"
            :lists="taskLists"
            :can-manage-lists="canEdit"
            :can-edit-tasks="canEdit"
            :selectable="isBulkMode"
            :selected-uuids="selectedTaskUuids"
            @task-click="onTaskClick"
            @add-task-to-list="handleAddTaskToList"
            @move-task-to-list="handleMoveTaskToList"
            @create-list="handleCreateList"
            @update-list="handleUpdateList"
            @delete-list="handleDeleteList"
            @reorder-lists="handleReorderLists"
            @toggle-select="handleToggleSelectTask"
          />
        </div>
      </div>

      <!-- 2. Milestones Roadmap -->
      <div v-else-if="activeTab === 'milestones'">
        <MilestoneList
          :project-uuid="project.uuid"
          :milestones="milestones"
          :can-edit="canEdit"
          @refresh="loadMilestones"
        />
      </div>

      <!-- 3. Gantt Chart View (Phase 13) -->
      <div v-else-if="activeTab === 'gantt'">
        <GanttChart
          :project-uuid="project.uuid"
          :can-edit="canEdit"
          @open-task="(taskUuid) => { activeTaskUuid = taskUuid; }"
        />
      </div>

      <!-- 4. Team Workload & Resource Balancing (Phase 13) -->
      <div v-else-if="activeTab === 'workload'" class="space-y-4">
        <div class="bg-white rounded-2xl border border-[#ebe8de] p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
          <div>
            <h3 class="text-sm font-bold text-[#1e1e1e] font-display flex items-center gap-2">
              <Users class="w-4 h-4 text-[#640c0e]" />
              <span>Project Resource Balancing</span>
            </h3>
            <p class="text-xs text-[#6b7280]">
              Track volunteer commitments and resolve over-allocated contributors across deliverable intervals.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-[#640c0e] text-white hover:bg-[#7e1012] transition-colors flex items-center gap-1.5 shadow-xs"
              @click="showBalancingModal = true"
            >
              <span>⚖️ Advisory Balancing</span>
            </button>
            <button
              type="button"
              class="px-3 py-1.5 rounded-xl text-xs font-semibold border border-[#ebe8de] text-gray-700 hover:bg-[#fbfbf9] transition-colors"
              @click="loadProjectWorkload"
            >
              Refresh
            </button>
          </div>
        </div>

        <div v-if="isWorkloadLoading" class="py-16 flex items-center justify-center text-[#6b7280]">
          <Loader2 class="w-6 h-6 animate-spin text-[#640c0e] mr-2" />
          <span class="text-xs">Computing team workload...</span>
        </div>

        <div v-else>
          <WorkloadHeatmap
            :team-workload="projectWorkloadData"
            :start-date="workloadStartDate"
            :end-date="workloadEndDate"
            @edit-capacity="openCapacityConfig"
          />
        </div>
      </div>

      <!-- 3. Project Attachments (Decision 3) -->
      <div v-else-if="activeTab === 'attachments'" class="bg-white rounded-2xl border border-[#ebe8de] p-6 space-y-4">
        <div>
          <h3 class="text-base font-bold text-[#1e1e1e] font-display flex items-center gap-2">
            <Paperclip class="w-4 h-4 text-[#640c0e]" />
            <span>Project Files & Documentation</span>
          </h3>
          <p class="text-xs text-[#6b7280] mt-0.5">
            Files uploaded here are stored in private storage and accessible only by authorized project members. Maximum size 25 MB per file.
          </p>
        </div>

        <AttachmentManager
          :project-uuid="uuid"
          :attachments="projectAttachments"
          :can-upload="canEdit"
          :current-user-id="authStore.user?.id"
          :is-project-manager-or-owner="canEdit"
          @attachment-uploaded="loadProjectAttachments"
          @attachment-deleted="loadProjectAttachments"
        />
      </div>

      <!-- 4. Project Calendar Tab (Phase 12) -->
      <div v-else-if="activeTab === 'calendar'" class="space-y-4">
        <!-- Calendar Toolbar -->
        <div class="bg-white rounded-2xl border border-[#ebe8de] p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
          <div class="flex items-center gap-3">
            <h3 class="text-sm font-bold text-[#1e1e1e] font-display flex items-center gap-2">
              <Calendar class="w-4 h-4 text-[#640c0e]" />
              <span>{{ calendarMonthLabel }}</span>
            </h3>

            <div class="flex items-center gap-1 border border-[#ebe8de] rounded-lg p-0.5 bg-[#fbfbf9]">
              <button
                type="button"
                @click="calendarPrevMonth"
                class="p-1 rounded text-[#6b7280] hover:text-[#1e1e1e] hover:bg-white"
                title="Previous Month"
              >
                <ChevronLeft class="w-4 h-4" />
              </button>
              <button
                type="button"
                @click="calendarToday"
                class="px-2 py-0.5 text-[11px] font-bold rounded text-[#6b7280] hover:text-[#1e1e1e] hover:bg-white"
              >
                Today
              </button>
              <button
                type="button"
                @click="calendarNextMonth"
                class="p-1 rounded text-[#6b7280] hover:text-[#1e1e1e] hover:bg-white"
                title="Next Month"
              >
                <ChevronRight class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="showSubscribeModal = true"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-[#640c0e]/10 border border-[#640c0e]/20 text-[#640c0e] hover:bg-[#640c0e]/15 transition-all"
            >
              <Rss class="w-3.5 h-3.5 text-[#640c0e]" />
              <span>Sync & Subscribe</span>
            </button>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="isCalendarLoading" class="py-24 flex items-center justify-center text-[#6b7280]">
          <Loader2 class="w-8 h-8 animate-spin text-[#640c0e]" />
        </div>

        <!-- Calendar Matrix -->
        <div v-else class="bg-white rounded-2xl border border-[#ebe8de] shadow-xs overflow-hidden">
          <!-- Day Headers -->
          <div class="grid grid-cols-7 border-b border-[#ebe8de] bg-[#fbfbf9] text-center text-xs font-bold text-[#6b7280] py-2.5">
            <div>Sun</div>
            <div>Mon</div>
            <div>Tue</div>
            <div>Wed</div>
            <div>Thu</div>
            <div>Fri</div>
            <div>Sat</div>
          </div>

          <!-- Days Grid -->
          <div class="grid grid-cols-7 auto-rows-fr divide-x divide-y divide-[#ebe8de]">
            <div
              v-for="(day, idx) in projectCalendarDays"
              :key="idx"
              class="min-h-[110px] p-2 transition-colors flex flex-col justify-between"
              :class="[
                day.isCurrentMonth ? 'bg-white' : 'bg-[#faf8f5]/60 text-[#a0a0a0]',
              ]"
            >
              <div class="flex items-center justify-between">
                <span
                  class="text-xs font-bold"
                  :class="day.isCurrentMonth ? 'text-[#1e1e1e]' : 'text-[#a0a0a0]'"
                >
                  {{ day.dayNumber }}
                </span>
                <span
                  v-if="day.events.length > 0"
                  class="text-[10px] font-semibold text-[#6b7280]"
                >
                  {{ day.events.length }}
                </span>
              </div>

              <!-- Event Chips -->
              <div class="space-y-1 mt-1 overflow-y-auto max-h-24">
                <button
                  v-for="ev in day.events"
                  :key="ev.id + '-' + day.dateStr"
                  type="button"
                  @click="onCalendarEventClick(ev)"
                  class="w-full text-left px-2 py-1 rounded-md text-[10px] font-semibold truncate block transition-all shadow-2xs group relative"
                  :class="[
                    ev.type === 'project'
                      ? 'border border-[#640c0e]/30 bg-[#640c0e]/10 text-[#640c0e] hover:bg-[#640c0e]/20 font-bold'
                      : 'hover:opacity-90',
                  ]"
                  :style="ev.type === 'project' ? {} : {
                    backgroundColor: ev.is_multi_day && day.dateStr !== (ev.start || ev.start_date) && day.dateStr !== (ev.end || ev.due_date)
                      ? `${ev.color || '#640c0e'}10`
                      : `${ev.color || '#640c0e'}18`,
                    color: ev.color || '#640c0e',
                    borderLeft: `3px solid ${ev.color || '#640c0e'}`,
                  }"
                  :title="ev.is_conflict ? `⚠️ CONFLICT: ${ev.conflict_reason}` : `${ev.title} (${ev.type}${ev.is_multi_day ? `: ${ev.start || ev.start_date} → ${ev.end || ev.due_date}` : ''})`"
                >
                  <div class="flex items-center justify-between gap-1">
                    <span class="truncate flex items-center gap-1 font-semibold">
                      <span v-if="ev.is_conflict" class="text-red-600 font-bold">⚠️</span>
                      <span v-else-if="ev.type === 'milestone'">🎯</span>
                      <span v-else-if="ev.type !== 'project'">📝</span>
                      <span class="truncate">{{ ev.title }}</span>
                    </span>
                    <span
                      v-if="ev.is_multi_day && day.dateStr === (ev.start || ev.start_date)"
                      class="text-[7.5px] font-mono px-1 py-0.2 rounded bg-black/5 opacity-80 shrink-0 font-bold uppercase"
                      title="Start of deliverable window"
                    >
                      Start
                    </span>
                    <span
                      v-else-if="ev.is_multi_day && day.dateStr === (ev.end || ev.due_date)"
                      class="text-[7.5px] font-mono px-1 py-0.2 rounded bg-black/5 opacity-80 shrink-0 font-bold uppercase"
                      title="Due / End of deliverable window"
                    >
                      Due
                    </span>
                    <span
                      v-else-if="ev.condition"
                      class="w-1.5 h-1.5 rounded-full shrink-0"
                      :class="[
                        ev.condition === 'completed' ? 'bg-emerald-500' :
                        ev.condition === 'on_track' ? 'bg-blue-500' :
                        ev.condition === 'at_risk' ? 'bg-amber-500' :
                        ev.condition === 'delayed' ? 'bg-red-500' : 'bg-gray-400'
                      ]"
                    ></span>
                  </div>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Analytics Tab (Phase 14) -->
      <div v-else-if="activeTab === 'analytics'" class="animate-in fade-in duration-200">
        <ProjectAnalyticsTab :project-uuid="uuid" />
      </div>

      <!-- 5. Settings & Project Controls -->
      <div v-else-if="activeTab === 'settings'" class="bg-white rounded-2xl border border-[#ebe8de] p-6 space-y-6">
        <h3 class="text-base font-bold text-[#640c0e] font-display">Project Lifecycle & Settings</h3>

        <!-- Project Schedule & Active Duration Period -->
        <div class="p-5 rounded-2xl border border-[#ebe8de] bg-[#fbfbf9]/60 space-y-4">
          <div>
            <h4 class="text-sm font-bold text-[#1e1e1e] font-display flex items-center gap-2">
              <Calendar class="w-4 h-4 text-[#640c0e]" />
              <span>Project Schedule & Active Duration Period</span>
            </h4>
            <p class="text-xs text-[#6b7280] mt-0.5">
              Set the project's overall start date and target completion deadline. This determines the project duration period rendered across the internal calendar.
            </p>
          </div>

          <div
            v-if="scheduleFeedback"
            class="p-3 rounded-xl text-xs flex items-center gap-2"
            :class="[
              scheduleFeedback.type === 'success'
                ? 'bg-emerald-50 border border-emerald-200 text-emerald-800'
                : 'bg-rose-50 border border-rose-200 text-rose-800',
            ]"
          >
            <CheckCircle2 v-if="scheduleFeedback.type === 'success'" class="w-4 h-4 shrink-0" />
            <AlertCircle v-else class="w-4 h-4 shrink-0" />
            <span>{{ scheduleFeedback.message }}</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-[#1e1e1e] mb-1">
                Project Start Date
              </label>
              <input
                v-model="projectStartDate"
                type="date"
                class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs bg-white focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none"
              />
            </div>
            <div>
              <label class="block text-xs font-bold text-[#1e1e1e] mb-1">
                Project Deadline / Target Due Date
              </label>
              <input
                v-model="projectDueDate"
                type="date"
                class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs bg-white focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none"
              />
            </div>
          </div>

          <div class="flex items-center justify-between pt-2">
            <span class="text-xs text-[#6b7280]">
              <span v-if="projectStartDate && projectDueDate" class="font-medium text-[#640c0e]">
                Active duration: {{ projectStartDate }} to {{ projectDueDate }}
              </span>
              <span v-else-if="projectDueDate">
                Deadline: {{ projectDueDate }}
              </span>
              <span v-else class="italic">
                No dates configured
              </span>
            </span>
            <button
              v-if="canEdit"
              type="button"
              @click="saveProjectSchedule"
              :disabled="isSavingProjectSchedule"
              class="px-4 py-2 rounded-xl text-xs font-bold bg-[#640c0e] text-white hover:bg-[#7e1012] transition-colors disabled:opacity-50 flex items-center gap-1.5"
            >
              <Loader2 v-if="isSavingProjectSchedule" class="w-3.5 h-3.5 animate-spin" />
              <span>Save Schedule</span>
            </button>
          </div>
        </div>

        <!-- Multi-Department Collaboration Management -->
        <div class="p-5 rounded-2xl border border-[#ebe8de] bg-[#fbfbf9]/60 space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="text-sm font-bold text-[#1e1e1e] font-display flex items-center gap-2">
                <Building2 class="w-4 h-4 text-[#640c0e]" />
                <span>Multi-Department Collaboration (Option B)</span>
              </h4>
              <p class="text-xs text-[#6b7280] mt-0.5">
                Manage participating departments. Active members of participating departments receive Option B read-only access.
              </p>
            </div>
          </div>

          <!-- Feedback notification -->
          <div
            v-if="participatingFeedback"
            class="p-3 rounded-xl text-xs flex items-center gap-2"
            :class="[
              participatingFeedback.type === 'success'
                ? 'bg-emerald-50 border border-emerald-200 text-emerald-800'
                : 'bg-rose-50 border border-rose-200 text-rose-800',
            ]"
          >
            <CheckCircle2 v-if="participatingFeedback.type === 'success'" class="w-4 h-4 shrink-0" />
            <AlertCircle v-else class="w-4 h-4 shrink-0" />
            <span>{{ participatingFeedback.message }}</span>
          </div>

          <!-- Owning Department info -->
          <div class="p-3.5 rounded-xl bg-white border border-[#ebe8de] flex items-center justify-between">
            <div>
              <span class="text-[11px] font-bold text-[#6b7280] block uppercase tracking-wider">Accountable Owning Department</span>
              <span class="text-xs font-extrabold text-[#640c0e] flex items-center gap-1.5 mt-0.5">
                <span>{{ project.owning_department?.name || project.department?.name }}</span>
                <span class="font-mono text-[10px] px-1 py-0.2 bg-[#640c0e]/10 rounded">
                  {{ project.owning_department?.code || project.department?.code }}
                </span>
              </span>
            </div>
            <span class="text-[11px] text-[#6b7280] bg-[#fbfbf9] px-2.5 py-1 rounded-lg border border-[#ebe8de]">
              Primary Owner
            </span>
          </div>

          <!-- Participating Departments List & Editor -->
          <div class="space-y-3">
            <label class="block text-xs font-bold text-[#1e1e1e]">
              Active Participating Departments ({{ editableParticipatingIds.length }})
            </label>

            <!-- Chips of current participating departments -->
            <div v-if="editableParticipatingIds.length > 0" class="flex flex-wrap gap-2">
              <span
                v-for="deptId in editableParticipatingIds"
                :key="deptId"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium bg-white border border-[#640c0e]/30 text-[#640c0e] shadow-2xs"
              >
                <span class="font-bold text-[10px] px-1 py-0.2 bg-[#640c0e]/10 rounded">
                  {{ departmentsStore.getDepartmentById(deptId)?.code || 'DEPT' }}
                </span>
                <span>{{ departmentsStore.getDepartmentById(deptId)?.name || `Dept #${deptId}` }}</span>
                <button
                  v-if="canManageParticipating"
                  type="button"
                  @click="removeParticipatingDept(deptId)"
                  class="text-[#640c0e]/60 hover:text-[#640c0e] transition-colors ml-1"
                  title="Remove department collaboration"
                >
                  <X class="w-3.5 h-3.5" />
                </button>
              </span>
            </div>
            <p v-else class="text-xs text-[#6b7280] italic">
              No participating departments. This project is currently limited to the owning department and explicit invitees.
            </p>

            <!-- Add new participating departments (only when user has management permission) -->
            <div v-if="canManageParticipating" class="space-y-2 pt-2">
              <div class="relative">
                <Search class="w-3.5 h-3.5 text-[#6b7280] absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  v-model="addParticipantSearch"
                  type="text"
                  placeholder="Search and add another collaborating department..."
                  class="w-full pl-8 pr-3 py-2 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none bg-white"
                />
              </div>

              <!-- Available departments pills -->
              <div
                v-if="availableToAddDepartments.length > 0"
                class="flex flex-wrap gap-1.5 p-1 rounded-xl bg-white border border-[#ebe8de]/70"
              >
                <button
                  v-for="dept in availableToAddDepartments"
                  :key="dept.id"
                  type="button"
                  @click="addParticipatingDept(dept.id)"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-medium bg-[#fbfbf9] text-[#1e1e1e] border border-[#ebe8de] hover:border-[#640c0e] hover:text-[#640c0e] hover:bg-[#640c0e]/5 transition-all"
                >
                  <Plus class="w-3 h-3 text-[#640c0e]" />
                  <span>{{ dept.name }}</span>
                  <span class="text-[9px] text-[#6b7280] font-mono">({{ dept.code }})</span>
                </button>
              </div>
            </div>

            <!-- Save button -->
            <div v-if="canManageParticipating" class="pt-2 flex justify-end">
              <button
                type="button"
                @click="saveParticipatingDepartments"
                :disabled="isSavingParticipating"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-[#640c0e] text-white hover:bg-[#7e1012] disabled:opacity-50 transition-colors shadow-xs"
              >
                <Loader2 v-if="isSavingParticipating" class="w-3.5 h-3.5 animate-spin" />
                <Save v-else class="w-3.5 h-3.5" />
                <span>Save Department Collaboration</span>
              </button>
            </div>
          </div>
        </div>

        <div class="p-4 rounded-xl border border-amber-200 bg-amber-50/50 space-y-2">
          <h4 class="text-xs font-bold text-amber-900">Archive Project</h4>
          <p class="text-xs text-amber-800">
            Archiving preserves all project data and historical activity logs while hiding it from active workflows.
          </p>
          <button
            type="button"
            @click="handleArchive"
            class="px-4 py-2 rounded-xl text-xs font-bold bg-amber-600 text-white hover:bg-amber-700 transition-colors"
          >
            Archive Project
          </button>
        </div>

        <div class="p-4 rounded-xl border border-rose-200 bg-rose-50/50 space-y-2">
          <h4 class="text-xs font-bold text-rose-900">Danger Zone: Delete Project</h4>
          <p class="text-xs text-rose-800">
            Permanently remove this project and its tasks. This action cannot be reversed.
          </p>
          <button
            type="button"
            @click="handleDelete"
            class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 text-white hover:bg-rose-700 transition-colors"
          >
            Delete Project
          </button>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <TaskCreateModal
      :is-open="showTaskCreateModal"
      :project-uuid="uuid"
      :initial-status="taskCreateInitialStatus"
      :initial-task-list-id="taskCreateInitialListId"
      :milestones="milestones"
      :members="members"
      :task-lists="taskLists"
      @close="showTaskCreateModal = false"
      @created="loadTasks"
    />

    <TaskDetailModal
      v-if="activeTaskUuid"
      :is-open="!!activeTaskUuid"
      :task-uuid="activeTaskUuid"
      :project-uuid="uuid"
      :can-edit="canEdit"
      :is-project-manager-or-owner="canEdit"
      :all-tasks="tasks"
      :task-lists="taskLists"
      @close="activeTaskUuid = null"
      @task-updated="() => { loadTasks(); loadTaskLists(); if (activeTab === 'calendar') loadProjectCalendar(); }"
      @task-deleted="() => { loadTasks(); loadTaskLists(); if (activeTab === 'calendar') loadProjectCalendar(); }"
    />

    <ProjectMembersModal
      :is-open="showMembersModal"
      :project-uuid="uuid"
      :members="members"
      :can-manage="canEdit"
      @close="showMembersModal = false"
      @refresh="loadMembers"
    />

    <CalendarSubscribeModal
      :is-open="showSubscribeModal"
      :project-uuid="uuid"
      :project-name="project?.name"
      @close="showSubscribeModal = false"
    />

    <!-- Advisory Balancing Modal (Phase 13) -->
    <ResourceBalancingModal
      v-if="project"
      :project-uuid="project.uuid"
      :is-open="showBalancingModal"
      @close="showBalancingModal = false"
      @reassigned="() => { loadTasks(); loadProjectWorkload(); }"
    />

    <!-- User Capacity Config Modal (Phase 13) -->
    <UserCapacityModal
      :user-id="selectedMemberForCapacity?.user.id || null"
      :user-name="selectedMemberForCapacity?.user.name || ''"
      :is-open="showCapacityModal"
      @close="showCapacityModal = false"
      @saved="loadProjectWorkload"
    />

    <!-- Project Report Export Modal (Phase 14) -->
    <ExportReportModal
      v-if="project"
      :is-open="showExportModal"
      :project-uuid="uuid"
      :project-name="project?.name"
      @close="showExportModal = false"
    />
  </div>
</template>
