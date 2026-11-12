<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { projectsApi } from '../../api/projects';
import { useAuthStore } from '../../stores/auth';
import type {
  Attachment,
  CascadePreview,
  Comment,
  DependencyType,
  SchedulingInfo,
  Task,
  TaskDependency,
  TaskList,
  TaskStatus,
  UserSummary,
} from '../../types/pm';
import AttachmentManager from '../attachments/AttachmentManager.vue';
import {
  X,
  MessageSquare,
  Trash2,
  Send,
  Loader2,
  Link2,
  Plus,
  AlertTriangle,
  Check,
  Calendar,
} from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    isOpen: boolean;
    taskUuid: string;
    projectUuid: string;
    taskLists?: TaskList[];
    allTasks?: Task[];
    canEdit?: boolean;
    isProjectManagerOrOwner?: boolean;
  }>(),
  {
    taskLists: () => [],
    allTasks: () => [],
    canEdit: false,
    isProjectManagerOrOwner: false,
  }
);

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'task-updated'): void;
  (e: 'task-deleted'): void;
}>();

const authStore = useAuthStore();
const task = ref<Task | null>(null);
const comments = ref<Comment[]>([]);
const newComment = ref('');
const isLoading = ref(true);
const isPostingComment = ref(false);

// Dependencies state
const dependencies = ref<TaskDependency[]>([]);
const dependents = ref<TaskDependency[]>([]);
const isBlocked = ref(false);
const isAddingDependency = ref(false);
const selectedPrereqUuid = ref('');
const selectedDepType = ref<DependencyType>('finish_to_start');
const selectedLagDays = ref<number>(0);
const isSubmittingDependency = ref(false);
const dependencyError = ref<string | null>(null);

// Scheduling & Rescheduling state (Phase 12)
const schedulingInfo = ref<SchedulingInfo | null>(null);
const isEditingSchedule = ref(false);
const newStartDate = ref('');
const newDueDate = ref('');
const isRescheduling = ref(false);
const isPreviewingCascade = ref(false);
const cascadePreview = ref<CascadePreview | null>(null);
const showCascadeModal = ref(false);

// Assignees editing state
const isEditingAssignees = ref(false);
const eligibleAssignees = ref<UserSummary[]>([]);
const selectedAssigneeIds = ref<number[]>([]);
const primaryAssigneeId = ref<number | null>(null);
const isSavingAssignees = ref(false);

// Task attachments state
const taskAttachments = ref<Attachment[]>([]);

const loadTask = async () => {
  if (!props.taskUuid) return;
  isLoading.value = true;
  try {
    task.value = await projectsApi.getTask(props.taskUuid);
    await Promise.all([
      loadComments(),
      loadDependencies(),
      loadAttachments(),
      loadSchedulingInfo(),
    ]);
  } finally {
    isLoading.value = false;
  }
};

const loadSchedulingInfo = async () => {
  try {
    schedulingInfo.value = await projectsApi.getTaskScheduling(props.taskUuid);
  } catch {
    schedulingInfo.value = null;
  }
};

const loadComments = async () => {
  try {
    comments.value = await projectsApi.getComments(props.projectUuid, {
      task_uuid: props.taskUuid,
    });
  } catch {
    comments.value = [];
  }
};

const loadDependencies = async () => {
  try {
    const res = await projectsApi.getTaskDependencies(props.taskUuid);
    dependencies.value = res.dependencies;
    dependents.value = res.dependents;
    isBlocked.value = res.is_blocked;
  } catch {
    dependencies.value = [];
    dependents.value = [];
    isBlocked.value = false;
  }
};

const loadAttachments = async () => {
  try {
    taskAttachments.value = await projectsApi.getTaskAttachments(props.taskUuid);
  } catch {
    taskAttachments.value = [];
  }
};

const loadEligibleAssignees = async () => {
  try {
    eligibleAssignees.value = await projectsApi.getEligibleAssignees(props.projectUuid);
  } catch {
    eligibleAssignees.value = [];
  }
};

watch(
  () => props.taskUuid,
  () => {
    if (props.isOpen && props.taskUuid) {
      loadTask();
    }
  },
  { immediate: true }
);

const onStatusChange = async (newStatus: TaskStatus) => {
  if (!task.value) return;
  try {
    const res = await projectsApi.updateTaskStatus(task.value.uuid, newStatus);
    task.value.status = res.task.status;
    emit('task-updated');
  } catch (err) {
    console.error('Failed to update task status:', err);
  }
};

const onListChange = async (newListId: number | null) => {
  if (!task.value) return;
  try {
    const res = await projectsApi.updateTask(task.value.uuid, {
      task_list_id: newListId,
    });
    task.value.task_list_id = res.task.task_list_id;
    task.value.task_list = res.task.task_list;
    emit('task-updated');
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to move task between lists.');
  }
};

// Dependency Actions
const availablePrerequisiteTasks = computed(() => {
  const currentDepUuids = dependencies.value.map((d) => d.uuid);
  return props.allTasks.filter(
    (t) => t.uuid !== props.taskUuid && !currentDepUuids.includes(t.uuid)
  );
});

const handleAddDependency = async () => {
  if (!selectedPrereqUuid.value || isSubmittingDependency.value) return;
  isSubmittingDependency.value = true;
  dependencyError.value = null;

  try {
    await projectsApi.addTaskDependency(
      props.taskUuid,
      selectedPrereqUuid.value,
      selectedDepType.value,
      selectedLagDays.value
    );
    await Promise.all([loadDependencies(), loadSchedulingInfo()]);
    selectedPrereqUuid.value = '';
    selectedDepType.value = 'finish_to_start';
    selectedLagDays.value = 0;
    isAddingDependency.value = false;
    emit('task-updated');
  } catch (err: any) {
    dependencyError.value = err.response?.data?.message || 'Failed to add dependency.';
  } finally {
    isSubmittingDependency.value = false;
  }
};

const handleRemoveDependency = async (depUuid: string) => {
  try {
    await projectsApi.removeTaskDependency(props.taskUuid, depUuid);
    await Promise.all([loadDependencies(), loadSchedulingInfo()]);
    emit('task-updated');
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to remove dependency.');
  }
};

// Rescheduling & Cascade Actions
const taskDurationDays = computed(() => {
  if (!task.value?.start_date || !task.value?.due_date) return null;
  const start = new Date(task.value.start_date).getTime();
  const due = new Date(task.value.due_date).getTime();
  if (due < start) return null;
  return Math.round((due - start) / (1000 * 60 * 60 * 24)) + 1;
});

const editDurationDays = computed(() => {
  if (!newStartDate.value || !newDueDate.value) return null;
  const start = new Date(newStartDate.value).getTime();
  const due = new Date(newDueDate.value).getTime();
  if (due < start) return null;
  return Math.round((due - start) / (1000 * 60 * 60 * 24)) + 1;
});

const startEditingSchedule = () => {
  newStartDate.value = task.value?.start_date || '';
  newDueDate.value = task.value?.due_date || '';
  cascadePreview.value = null;
  showCascadeModal.value = false;
  isEditingSchedule.value = true;
};

const cancelEditingSchedule = () => {
  isEditingSchedule.value = false;
  cascadePreview.value = null;
  showCascadeModal.value = false;
};

const handleRequestReschedule = async () => {
  if (!newStartDate.value && !newDueDate.value) return;
  if (newStartDate.value && newDueDate.value && newDueDate.value < newStartDate.value) {
    alert('Due date must be on or after start date.');
    return;
  }

  // If task has active dependents, preview cascade first
  if (dependents.value.length > 0) {
    isPreviewingCascade.value = true;
    try {
      cascadePreview.value = await projectsApi.previewReschedule(props.taskUuid, {
        start_date: newStartDate.value,
        due_date: newDueDate.value,
      });
      showCascadeModal.value = true;
      return;
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to preview cascade reschedule.');
      return;
    } finally {
      isPreviewingCascade.value = false;
    }
  }

  // No dependents, execute directly
  await executeReschedule(false);
};

const executeReschedule = async (cascade: boolean) => {
  isRescheduling.value = true;
  try {
    const res = await projectsApi.rescheduleTask(props.taskUuid, {
      start_date: newStartDate.value,
      due_date: newDueDate.value,
      cascade,
    });
    task.value = res.task;
    isEditingSchedule.value = false;
    showCascadeModal.value = false;
    cascadePreview.value = null;
    await Promise.all([loadDependencies(), loadSchedulingInfo()]);
    emit('task-updated');
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to reschedule task.');
  } finally {
    isRescheduling.value = false;
  }
};

// Assignees Actions
const startEditingAssignees = async () => {
  if (eligibleAssignees.value.length === 0) {
    await loadEligibleAssignees();
  }
  selectedAssigneeIds.value = (task.value?.assignees || []).map((u) => u.id);
  primaryAssigneeId.value = task.value?.assigned_to || null;
  isEditingAssignees.value = true;
};

const toggleAssignee = (userId: number) => {
  const index = selectedAssigneeIds.value.indexOf(userId);
  if (index === -1) {
    selectedAssigneeIds.value.push(userId);
    if (!primaryAssigneeId.value) {
      primaryAssigneeId.value = userId;
    }
  } else {
    selectedAssigneeIds.value.splice(index, 1);
    if (primaryAssigneeId.value === userId) {
      primaryAssigneeId.value = selectedAssigneeIds.value.length > 0 ? selectedAssigneeIds.value[0] : null;
    }
  }
};

const saveAssignees = async () => {
  if (!task.value) return;
  isSavingAssignees.value = true;
  try {
    const res = await projectsApi.updateTask(task.value.uuid, {
      assignee_ids: selectedAssigneeIds.value,
      assigned_to: primaryAssigneeId.value,
    });
    task.value.assignees = res.task.assignees;
    task.value.assignee = res.task.assignee;
    task.value.assigned_to = res.task.assigned_to;
    isEditingAssignees.value = false;
    emit('task-updated');
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to update assignees.');
  } finally {
    isSavingAssignees.value = false;
  }
};

// Attachment callbacks
const onAttachmentUploaded = (newAtt: Attachment) => {
  taskAttachments.value.unshift(newAtt);
  emit('task-updated');
};

const onAttachmentDeleted = (deletedUuid: string) => {
  taskAttachments.value = taskAttachments.value.filter((a) => a.uuid !== deletedUuid);
  emit('task-updated');
};

// Comments
const handlePostComment = async () => {
  if (!newComment.value.trim() || isPostingComment.value) return;
  isPostingComment.value = true;
  try {
    const comment = await projectsApi.createComment(props.projectUuid, {
      body: newComment.value.trim(),
      task_uuid: props.taskUuid,
    });
    comments.value.push(comment);
    newComment.value = '';
    emit('task-updated');
  } catch (err) {
    console.error('Failed to post comment:', err);
  } finally {
    isPostingComment.value = false;
  }
};

const handleDeleteComment = async (commentId: number) => {
  try {
    await projectsApi.deleteComment(props.projectUuid, commentId);
    comments.value = comments.value.filter((c) => c.id !== commentId);
    emit('task-updated');
  } catch (err) {
    console.error('Failed to delete comment:', err);
  }
};

const handleDeleteTask = async () => {
  if (!confirm('Are you sure you want to delete this task?')) return;
  try {
    await projectsApi.deleteTask(props.taskUuid);
    emit('task-deleted');
    emit('close');
  } catch (err) {
    console.error('Failed to delete task:', err);
  }
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs animate-in fade-in duration-150"
  >
    <div class="bg-white rounded-2xl shadow-elevated border border-[#ebe8de] w-full max-w-2xl max-h-[92vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-150">
      <!-- Header -->
      <div class="px-6 py-4 border-b border-[#ebe8de] flex items-center justify-between shrink-0">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-xs font-bold text-[#640c0e] uppercase tracking-wider">Task Details</span>

          <span v-if="task?.task_list" class="text-xs px-2 py-0.5 rounded-full bg-[#f4f2ea] text-[#4b5563] border border-[#e5e2d6]">
            📂 {{ task.task_list.name }}
          </span>

          <span v-if="task?.milestone" class="text-xs px-2 py-0.5 rounded-full bg-[#fbfbf9] text-[#6b7280] border border-[#ebe8de]">
            🎯 {{ task.milestone.title }}
          </span>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="canEdit"
            type="button"
            @click="handleDeleteTask"
            class="p-1.5 rounded-lg text-[#6b7280] hover:text-[#ea2128] hover:bg-[#ea2128]/5 transition-colors"
            title="Delete Task"
          >
            <Trash2 class="w-4 h-4" />
          </button>
          <button
            type="button"
            @click="$emit('close')"
            class="p-1.5 rounded-lg text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#fbfbf9] transition-colors"
          >
            <X class="w-5 h-5" />
          </button>
        </div>
      </div>

      <!-- Content Scrollable Body -->
      <div v-if="isLoading" class="p-12 flex items-center justify-center text-[#6b7280]">
        <Loader2 class="w-6 h-6 animate-spin text-[#640c0e]" />
      </div>

      <div v-else-if="task" class="p-6 overflow-y-auto space-y-6 flex-1">
        <!-- Scheduling Conflict Warning Banner -->
        <div
          v-if="schedulingInfo?.has_conflicts"
          class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs space-y-2 animate-in fade-in"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 font-bold text-red-900">
              <AlertTriangle class="w-4 h-4 text-red-600 shrink-0 animate-pulse" />
              <span>Scheduling Conflict Detected</span>
            </div>
            <button
              v-if="canEdit"
              type="button"
              @click="startEditingSchedule"
              class="px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg text-[10px] transition-colors"
            >
              Align Schedule
            </button>
          </div>
          <ul class="list-disc pl-5 space-y-1 text-[11px] text-red-700">
            <li v-for="(c, idx) in schedulingInfo.conflicts" :key="idx">
              {{ c.conflict_reason }}
            </li>
          </ul>
        </div>

        <!-- Blocked Warning Banner -->
        <div
          v-if="isBlocked"
          class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center gap-2.5"
        >
          <AlertTriangle class="w-4 h-4 text-amber-600 shrink-0" />
          <div>
            <span class="font-bold">Task is currently blocked.</span>
            One or more prerequisite tasks must be marked completed first.
          </div>
        </div>

        <!-- Title & Status Selector -->
        <div>
          <div class="flex items-start justify-between gap-4 mb-2">
            <h2 class="text-lg font-bold text-[#1e1e1e] font-display">
              {{ task.title }}
            </h2>
            <select
              :value="task.status"
              @change="onStatusChange(($event.target as HTMLSelectElement).value as TaskStatus)"
              :disabled="!canEdit"
              class="px-3 py-1.5 rounded-xl border border-[#ebe8de] text-xs font-bold bg-[#fbfbf9] text-[#640c0e] focus:outline-none cursor-pointer"
            >
              <option value="todo">To Do</option>
              <option value="in_progress">In Progress</option>
              <option value="review">In Review</option>
              <option value="completed">Completed</option>
            </select>
          </div>

          <p class="text-xs text-[#6b7280] leading-relaxed whitespace-pre-wrap">
            {{ task.description || 'No description provided for this task.' }}
          </p>
        </div>

        <!-- Meta badges grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-[#fbfbf9] border border-[#ebe8de] text-xs">
          <!-- Assignees (with modal trigger) -->
          <div>
            <div class="flex items-center justify-between mb-0.5">
              <span class="text-[10px] font-bold uppercase tracking-wider text-[#6b7280]">Assignees</span>
              <button
                v-if="canEdit && !isEditingAssignees"
                type="button"
                @click="startEditingAssignees"
                class="text-[9px] text-[#640c0e] hover:underline font-semibold"
              >
                Edit
              </button>
            </div>

            <div v-if="task.assignees && task.assignees.length > 0" class="flex flex-col gap-0.5">
              <div
                v-for="u in task.assignees"
                :key="u.id"
                class="flex items-center gap-1 font-semibold text-[#1e1e1e] text-[11px]"
              >
                <div class="w-4 h-4 rounded-full bg-[#640c0e] text-white flex items-center justify-center text-[8px] font-bold">
                  {{ u.name.slice(0, 1) }}
                </div>
                <span class="truncate">{{ u.name }}</span>
                <span v-if="task.assigned_to === u.id" class="text-[8px] text-[#640c0e] font-bold">(Primary)</span>
              </div>
            </div>
            <div v-else class="text-[#9ca3af] italic">
              Unassigned
            </div>
          </div>

          <!-- Section / List Selector -->
          <div>
            <span class="block text-[10px] font-bold uppercase tracking-wider text-[#6b7280] mb-0.5">Section / List</span>
            <select
              :value="task.task_list_id"
              @change="onListChange(($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
              :disabled="!canEdit"
              class="w-full text-xs font-semibold text-[#1e1e1e] bg-transparent border-0 p-0 focus:ring-0 cursor-pointer"
            >
              <option :value="null">Uncategorized</option>
              <option v-for="l in taskLists" :key="l.id" :value="l.id">
                {{ l.name }}
              </option>
            </select>
          </div>

          <!-- Priority -->
          <div>
            <span class="block text-[10px] font-bold uppercase tracking-wider text-[#6b7280] mb-0.5">Priority</span>
            <span class="font-semibold capitalize text-[#640c0e]">
              {{ task.priority }}
            </span>
          </div>

          <!-- Timeline Dates -->
          <div>
            <div class="flex items-center justify-between mb-0.5">
              <span class="text-[10px] font-bold uppercase tracking-wider text-[#6b7280]">Timeline</span>
              <button
                v-if="canEdit && !isEditingSchedule"
                type="button"
                @click="startEditingSchedule"
                class="text-[9px] text-[#640c0e] hover:underline font-semibold"
              >
                Reschedule
              </button>
            </div>
            <span class="font-semibold text-[#1e1e1e] block truncate text-[11px]">
              {{ task.start_date ? `${task.start_date} → ${task.due_date || 'No due date'}` : (task.due_date || 'No deadline') }}
            </span>
            <span v-if="taskDurationDays !== null" class="text-[9px] font-bold text-[#640c0e] block">
              {{ taskDurationDays }} day{{ taskDurationDays === 1 ? '' : 's' }}
            </span>
          </div>
        </div>

        <!-- Inline Schedule Rescheduling Panel -->
        <div v-if="isEditingSchedule" class="p-3.5 rounded-xl border border-[#640c0e]/30 bg-[#fbfbf9] space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5 font-bold text-xs text-[#640c0e]">
              <Calendar class="w-4 h-4" />
              <span>Reschedule Task</span>
            </div>
            <span v-if="editDurationDays !== null" class="text-[10px] font-bold text-[#640c0e]">
              Duration: {{ editDurationDays }} day{{ editDurationDays === 1 ? '' : 's' }}
            </span>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-semibold text-[#6b7280] mb-1">Start Date</label>
              <input
                v-model="newStartDate"
                type="date"
                class="w-full px-3 py-1.5 rounded-lg border border-[#ebe8de] text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#640c0e]"
              />
            </div>
            <div>
              <label class="block text-[11px] font-semibold text-[#6b7280] mb-1">Due Date</label>
              <input
                v-model="newDueDate"
                type="date"
                class="w-full px-3 py-1.5 rounded-lg border border-[#ebe8de] text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#640c0e]"
              />
            </div>
          </div>

          <div class="flex items-center justify-between pt-1 flex-wrap gap-2">
            <span v-if="dependents.length > 0" class="text-[10px] text-amber-700 flex items-center gap-1">
              <AlertTriangle class="w-3 h-3 text-amber-600 shrink-0" />
              <span>Has {{ dependents.length }} dependent task(s). Cascade will be previewed.</span>
            </span>
            <span v-else class="text-[10px] text-[#6b7280]">
              No dependent tasks will be shifted.
            </span>

            <div class="flex items-center gap-2 ml-auto">
              <button
                type="button"
                @click="cancelEditingSchedule"
                class="px-2.5 py-1 text-xs text-[#6b7280] hover:text-[#1e1e1e]"
              >
                Cancel
              </button>
              <button
                type="button"
                @click="handleRequestReschedule"
                :disabled="isRescheduling || isPreviewingCascade"
                class="px-3.5 py-1.5 bg-[#640c0e] hover:bg-[#7e1012] text-white font-bold rounded-lg text-xs flex items-center gap-1 transition-colors disabled:opacity-50 shadow-2xs"
              >
                <Loader2 v-if="isRescheduling || isPreviewingCascade" class="w-3.5 h-3.5 animate-spin" />
                <span>{{ dependents.length > 0 ? 'Preview & Update' : 'Save Schedule' }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Inline Assignee Editor Panel -->
        <div v-if="isEditingAssignees" class="p-3.5 rounded-xl border border-[#640c0e]/30 bg-[#640c0e]/5 space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-[#640c0e]">Update Task Assignees</span>
            <button type="button" @click="isEditingAssignees = false" class="text-xs text-[#6b7280] hover:text-[#1e1e1e]">
              Cancel
            </button>
          </div>

          <div class="max-h-36 overflow-y-auto space-y-1">
            <div
              v-for="u in eligibleAssignees"
              :key="u.id"
              @click="toggleAssignee(u.id)"
              class="flex items-center justify-between p-1.5 rounded-lg bg-white border text-xs cursor-pointer"
              :class="[selectedAssigneeIds.includes(u.id) ? 'border-[#640c0e] font-bold text-[#640c0e]' : 'border-[#ebe8de] text-[#4b5563]']"
            >
              <div class="flex items-center gap-2">
                <div class="w-4 h-4 rounded-full bg-[#640c0e] text-white flex items-center justify-center text-[8px] font-bold">
                  {{ u.name.slice(0, 1) }}
                </div>
                <span>{{ u.name }}</span>
                <span v-if="primaryAssigneeId === u.id" class="px-1 py-0.2 rounded text-[7px] bg-[#640c0e] text-white uppercase font-bold">
                  Primary
                </span>
              </div>

              <div class="flex items-center gap-2">
                <button
                  v-if="selectedAssigneeIds.includes(u.id) && primaryAssigneeId !== u.id"
                  type="button"
                  @click.stop="primaryAssigneeId = u.id"
                  class="text-[9px] text-[#6b7280] hover:text-[#640c0e] underline"
                >
                  Make Primary
                </button>
                <div
                  class="w-4 h-4 rounded border flex items-center justify-center"
                  :class="[selectedAssigneeIds.includes(u.id) ? 'bg-[#640c0e] border-[#640c0e] text-white' : 'border-[#d0ccc0]']"
                >
                  <Check v-if="selectedAssigneeIds.includes(u.id)" class="w-3 h-3 stroke-[3]" />
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-1">
            <button
              type="button"
              @click="saveAssignees"
              :disabled="isSavingAssignees"
              class="px-3 py-1 rounded-lg text-xs font-bold bg-[#640c0e] text-white hover:bg-[#b02e32] transition-colors flex items-center gap-1 shadow-2xs"
            >
              <Loader2 v-if="isSavingAssignees" class="w-3 h-3 animate-spin" />
              <span>Save Assignments</span>
            </button>
          </div>
        </div>

        <!-- Task Dependencies Section -->
        <div class="space-y-3 pt-3 border-t border-[#ebe8de]">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-bold text-[#1e1e1e] uppercase tracking-wider flex items-center gap-1.5">
              <Link2 class="w-3.5 h-3.5 text-[#640c0e]" />
              <span>Dependencies</span>
            </h4>

            <button
              v-if="canEdit && !isAddingDependency"
              type="button"
              @click="isAddingDependency = true"
              class="text-xs text-[#640c0e] font-semibold hover:underline flex items-center gap-1"
            >
              <Plus class="w-3 h-3" />
              <span>Add Prerequisite</span>
            </button>
          </div>

          <div v-if="dependencyError" class="p-2 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs">
            {{ dependencyError }}
          </div>

          <!-- Add Dependency Selector -->
          <div v-if="isAddingDependency" class="p-3 rounded-xl bg-[#fbfbf9] border border-[#ebe8de] space-y-2">
            <label class="block text-xs font-bold text-[#1e1e1e]">This task depends on:</label>
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
              <select
                v-model="selectedPrereqUuid"
                class="sm:col-span-6 px-3 py-1.5 rounded-lg border border-[#ebe8de] text-xs bg-white focus:outline-none"
              >
                <option value="">Select a prerequisite task...</option>
                <option v-for="t in availablePrerequisiteTasks" :key="t.uuid" :value="t.uuid">
                  {{ t.title }} ({{ t.status }})
                </option>
              </select>

              <select
                v-model="selectedDepType"
                class="sm:col-span-3 px-2 py-1.5 rounded-lg border border-[#ebe8de] text-xs bg-white focus:outline-none"
                title="Dependency Type"
              >
                <option value="finish_to_start">Finish-to-Start (FS)</option>
                <option value="start_to_start">Start-to-Start (SS)</option>
                <option value="finish_to_finish">Finish-to-Finish (FF)</option>
              </select>

              <div class="sm:col-span-3 flex items-center gap-1">
                <input
                  v-model.number="selectedLagDays"
                  type="number"
                  min="0"
                  step="1"
                  placeholder="Lag"
                  title="Lag in days"
                  class="w-16 px-2 py-1.5 rounded-lg border border-[#ebe8de] text-xs bg-white text-center focus:outline-none"
                />
                <span class="text-[10px] text-[#6b7280]">days</span>
              </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-1">
              <button
                type="button"
                @click="isAddingDependency = false; dependencyError = null"
                class="px-2 py-1.5 text-xs text-[#6b7280] hover:text-[#1e1e1e]"
              >
                Cancel
              </button>
              <button
                type="button"
                :disabled="!selectedPrereqUuid || isSubmittingDependency"
                @click="handleAddDependency"
                class="px-3 py-1.5 rounded-lg text-xs font-bold bg-[#640c0e] text-white hover:bg-[#b02e32] disabled:opacity-50 transition-colors flex items-center gap-1 shadow-2xs"
              >
                <Loader2 v-if="isSubmittingDependency" class="w-3 h-3 animate-spin" />
                <span>Link Dependency</span>
              </button>
            </div>
          </div>

          <!-- Prerequisites List ("Blocked By") -->
          <div class="space-y-1.5">
            <span class="text-[10px] font-bold text-[#6b7280] uppercase tracking-wider block">Blocked By (Must be completed first):</span>
            <div v-if="dependencies.length > 0" class="space-y-1.5">
              <div
                v-for="dep in dependencies"
                :key="dep.uuid"
                class="flex items-center justify-between p-2 rounded-lg border text-xs"
                :class="[dep.status === 'completed' ? 'bg-emerald-50/40 border-emerald-200' : (dep.is_conflict ? 'bg-red-50/50 border-red-200' : 'bg-amber-50/40 border-amber-200')]"
              >
                <div class="flex items-center gap-2 min-w-0 mr-2 flex-wrap">
                  <span
                    class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase shrink-0"
                    :class="[dep.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800']"
                  >
                    {{ dep.status }}
                  </span>
                  <span class="font-semibold text-[#1e1e1e] truncate">{{ dep.title }}</span>

                  <span
                    v-if="dep.dependency_type"
                    class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-slate-100 text-slate-700 border border-slate-200 shrink-0"
                    :title="dep.dependency_type_label || dep.dependency_type"
                  >
                    {{ dep.dependency_type === 'finish_to_start' ? 'FS' : (dep.dependency_type === 'start_to_start' ? 'SS' : 'FF') }}
                    <span v-if="dep.lag_days && dep.lag_days > 0">+{{ dep.lag_days }}d</span>
                  </span>

                  <span
                    v-if="dep.is_conflict"
                    class="px-1.5 py-0.5 rounded text-[9px] font-bold text-red-700 bg-red-100 border border-red-200 flex items-center gap-1 shrink-0"
                    :title="dep.conflict_reason || 'Scheduling conflict detected'"
                  >
                    ⚠️ Conflict
                  </span>
                </div>

                <button
                  v-if="canEdit"
                  type="button"
                  @click="handleRemoveDependency(dep.uuid)"
                  class="p-1 text-[#6b7280] hover:text-red-600 transition-colors shrink-0"
                  title="Remove prerequisite"
                >
                  <Trash2 class="w-3 h-3" />
                </button>
              </div>
            </div>
            <div v-else class="text-[11px] text-[#6b7280] italic pl-1">
              No prerequisite tasks blocking this deliverable.
            </div>
          </div>

          <!-- Successors List ("Blocks") -->
          <div v-if="dependents.length > 0" class="space-y-1.5 pt-1">
            <span class="text-[10px] font-bold text-[#6b7280] uppercase tracking-wider block">Blocks (Cannot be completed until this is done):</span>
            <div class="space-y-1.5">
              <div
                v-for="succ in dependents"
                :key="succ.uuid"
                class="flex items-center justify-between p-2 rounded-lg bg-gray-50 border border-gray-200 text-xs"
              >
                <div class="flex items-center gap-2 min-w-0">
                  <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-gray-200 text-gray-800">
                    {{ succ.status }}
                  </span>
                  <span class="font-semibold text-[#1e1e1e] truncate">{{ succ.title }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Task Attachments Section -->
        <div class="pt-3 border-t border-[#ebe8de]">
          <AttachmentManager
            :attachments="taskAttachments"
            :project-uuid="projectUuid"
            :task-uuid="task.uuid"
            :can-upload="canEdit"
            :current-user-id="authStore.user?.id"
            :is-project-manager-or-owner="isProjectManagerOrOwner"
            @attachment-uploaded="onAttachmentUploaded"
            @attachment-deleted="onAttachmentDeleted"
          />
        </div>

        <!-- Comments & Discussion Section -->
        <div class="space-y-4 pt-4 border-t border-[#ebe8de]">
          <h4 class="text-xs font-bold text-[#1e1e1e] uppercase tracking-wider flex items-center gap-1.5">
            <MessageSquare class="w-3.5 h-3.5 text-[#640c0e]" />
            <span>Discussion ({{ comments.length }})</span>
          </h4>

          <!-- Comments Feed -->
          <div class="space-y-3">
            <div
              v-for="comment in comments"
              :key="comment.id"
              class="p-3.5 rounded-xl bg-white border border-[#ebe8de] space-y-1.5"
            >
              <div class="flex items-center justify-between text-xs">
                <span class="font-bold text-[#1e1e1e]">{{ comment.author?.name || 'MSA Member' }}</span>
                <div class="flex items-center gap-2 text-[11px] text-[#6b7280]">
                  <span>{{ new Date(comment.created_at).toLocaleString() }}</span>
                  <button
                    v-if="comment.user_id === authStore.user?.id || authStore.user?.is_admin"
                    type="button"
                    @click="handleDeleteComment(comment.id)"
                    class="hover:text-[#ea2128] transition-colors"
                    title="Delete Comment"
                  >
                    <Trash2 class="w-3 h-3" />
                  </button>
                </div>
              </div>
              <p class="text-xs text-[#1e1e1e] leading-relaxed whitespace-pre-wrap">
                {{ comment.body }}
              </p>
            </div>

            <div v-if="comments.length === 0" class="py-6 text-center text-xs text-[#6b7280]">
              No comments yet. Share an update or ask a question.
            </div>
          </div>

          <!-- Add Comment Box -->
          <div class="flex items-start gap-2 pt-2">
            <textarea
              v-model="newComment"
              rows="2"
              placeholder="Write a comment..."
              class="flex-1 px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none resize-none"
            ></textarea>
            <button
              type="button"
              :disabled="!newComment.trim() || isPostingComment"
              @click="handlePostComment"
              class="px-4 py-2.5 rounded-xl font-bold text-xs bg-[#640c0e] text-white hover:bg-[#7e1012] disabled:opacity-50 transition-all flex items-center gap-1.5 shrink-0"
            >
              <Loader2 v-if="isPostingComment" class="w-3.5 h-3.5 animate-spin" />
              <Send v-else class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Cascade Confirmation Modal Overlay -->
    <div
      v-if="showCascadeModal && cascadePreview"
      class="fixed inset-0 z-60 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in"
    >
      <div class="bg-white rounded-2xl shadow-elevated border border-[#ebe8de] w-full max-w-lg overflow-hidden flex flex-col max-h-[85vh] animate-in zoom-in-95">
        <div class="px-5 py-4 border-b border-[#ebe8de] flex items-center justify-between bg-[#fbfbf9]">
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center font-bold text-sm">
              ⚠️
            </div>
            <div>
              <h3 class="text-sm font-bold text-[#1e1e1e]">Cascade Rescheduling Preview</h3>
              <p class="text-[11px] text-[#6b7280]">
                {{ cascadePreview.total_affected_count }} downstream dependent task(s) affected across {{ cascadePreview.max_depth_reached }} level(s)
              </p>
            </div>
          </div>
          <button
            type="button"
            @click="showCascadeModal = false"
            class="p-1 rounded-lg text-[#6b7280] hover:text-[#1e1e1e]"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="p-5 overflow-y-auto space-y-4 text-xs flex-1">
          <p class="text-[#4b5563] leading-relaxed">
            Moving <strong class="text-[#1e1e1e]">{{ cascadePreview.root_task.title }}</strong> impacts the following prerequisite dependencies. Choose whether to reschedule only this task, or automatically cascade dates down the dependency chain:
          </p>

          <div class="border border-[#ebe8de] rounded-xl overflow-hidden divide-y divide-[#ebe8de] max-h-60 overflow-y-auto">
            <div
              v-for="aff in cascadePreview.affected_tasks"
              :key="aff.uuid"
              class="p-2.5 bg-white flex items-center justify-between gap-3 text-xs"
            >
              <div class="min-w-0">
                <div class="font-bold text-[#1e1e1e] truncate">{{ aff.title }}</div>
                <div class="text-[10px] text-[#6b7280]">
                  {{ aff.current_start_date || 'None' }} → {{ aff.new_start_date }}
                </div>
              </div>
              <div class="text-right shrink-0">
                <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">
                  +{{ aff.shift_days }} d shift
                </span>
                <div class="text-[10px] text-[#6b7280] mt-0.5">
                  Due: {{ aff.new_due_date }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="px-5 py-3.5 bg-[#fbfbf9] border-t border-[#ebe8de] flex items-center justify-between gap-2">
          <button
            type="button"
            @click="showCascadeModal = false"
            class="px-3 py-1.5 text-xs text-[#6b7280] hover:text-[#1e1e1e]"
          >
            Cancel
          </button>
          <div class="flex items-center gap-2">
            <button
              type="button"
              :disabled="isRescheduling"
              @click="executeReschedule(false)"
              class="px-3 py-1.5 rounded-lg border border-[#ebe8de] bg-white hover:bg-[#f4f2ea] text-[#1e1e1e] font-semibold text-xs transition-colors disabled:opacity-50"
            >
              This Task Only
            </button>
            <button
              type="button"
              :disabled="isRescheduling"
              @click="executeReschedule(true)"
              class="px-3.5 py-1.5 rounded-lg bg-[#640c0e] hover:bg-[#7e1012] text-white font-bold text-xs transition-colors flex items-center gap-1.5 disabled:opacity-50 shadow-2xs"
            >
              <Loader2 v-if="isRescheduling" class="w-3.5 h-3.5 animate-spin" />
              <span>Cascade to All ({{ cascadePreview.total_affected_count }})</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
