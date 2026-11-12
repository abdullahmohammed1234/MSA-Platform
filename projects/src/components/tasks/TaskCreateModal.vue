<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { projectsApi } from '../../api/projects';
import type { Milestone, ProjectMember, TaskList, TaskStatus, UserSummary } from '../../types/pm';
import { X, Loader2, AlertCircle, Check, Users } from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    isOpen: boolean;
    projectUuid: string;
    initialStatus?: TaskStatus;
    initialTaskListId?: number | null;
    milestones: Milestone[];
    members: ProjectMember[];
    taskLists?: TaskList[];
  }>(),
  {
    initialTaskListId: null,
    taskLists: () => [],
  }
);

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'created'): void;
}>();

const title = ref('');
const description = ref('');
const status = ref<TaskStatus>('todo');
const priority = ref<'low' | 'medium' | 'high' | 'urgent'>('medium');
const milestoneId = ref<number | null>(null);
const taskListId = ref<number | null>(null);
const selectedAssigneeIds = ref<number[]>([]);
const primaryAssigneeId = ref<number | null>(null);
const startDate = ref('');
const dueDate = ref('');
const isSubmitting = ref(false);
const isLoadingAssignees = ref(false);
const eligibleAssignees = ref<UserSummary[]>([]);
const errorMessage = ref<string | null>(null);

const loadEligibleAssignees = async () => {
  if (!props.projectUuid) return;
  isLoadingAssignees.value = true;
  try {
    eligibleAssignees.value = await projectsApi.getEligibleAssignees(props.projectUuid);
  } catch {
    // Fallback to active members with eligible roles
    eligibleAssignees.value = props.members
      .filter((m) => m.project_role !== 'viewer' && m.user)
      .map((m) => m.user as UserSummary);
  } finally {
    isLoadingAssignees.value = false;
  }
};

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      if (props.initialStatus) status.value = props.initialStatus;
      if (props.initialTaskListId !== undefined) taskListId.value = props.initialTaskListId;
      loadEligibleAssignees();
    }
  },
  { immediate: true }
);

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

const durationDays = computed(() => {
  if (!startDate.value || !dueDate.value) return null;
  const start = new Date(startDate.value).getTime();
  const due = new Date(dueDate.value).getTime();
  if (due < start) return null;
  return Math.round((due - start) / (1000 * 60 * 60 * 24)) + 1;
});

const handleSubmit = async () => {
  if (!title.value.trim() || isSubmitting.value) return;

  if (startDate.value && dueDate.value && dueDate.value < startDate.value) {
    errorMessage.value = 'Due date must be on or after start date.';
    return;
  }

  isSubmitting.value = true;
  errorMessage.value = null;

  try {
    await projectsApi.createTask(props.projectUuid, {
      title: title.value.trim(),
      description: description.value.trim() || undefined,
      status: status.value,
      priority: priority.value,
      milestone_id: milestoneId.value || undefined,
      task_list_id: taskListId.value !== null ? taskListId.value : undefined,
      start_date: startDate.value || undefined,
      due_date: dueDate.value || undefined,
      assigned_to: primaryAssigneeId.value || undefined,
      assignee_ids: selectedAssigneeIds.value.length > 0 ? selectedAssigneeIds.value : undefined,
    });

    title.value = '';
    description.value = '';
    startDate.value = '';
    dueDate.value = '';
    milestoneId.value = null;
    taskListId.value = null;
    selectedAssigneeIds.value = [];
    primaryAssigneeId.value = null;

    emit('created');
    emit('close');
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to create task.';
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs animate-in fade-in duration-150"
  >
    <div class="bg-white rounded-2xl shadow-elevated border border-[#ebe8de] w-full max-w-lg max-h-[90vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-150">
      <!-- Header -->
      <div class="px-6 py-4 border-b border-[#ebe8de] flex items-center justify-between shrink-0">
        <div>
          <h3 class="text-base font-bold text-[#640c0e] font-display">Add Task</h3>
          <p class="text-xs text-[#6b7280]">Assign deliverables, set dates, and organize by list.</p>
        </div>
        <button
          type="button"
          @click="$emit('close')"
          class="p-1.5 rounded-lg text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#fbfbf9] transition-colors"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Body -->
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4 overflow-y-auto flex-1">
        <div v-if="errorMessage" class="p-3 rounded-xl bg-[#ea2128]/10 border border-[#ea2128]/20 text-[#ea2128] text-xs flex items-center gap-2">
          <AlertCircle class="w-4 h-4 shrink-0" />
          <span>{{ errorMessage }}</span>
        </div>

        <!-- Task Title -->
        <div>
          <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Task Title *</label>
          <input
            v-model="title"
            type="text"
            required
            placeholder="e.g., Finalize catering contract"
            class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none"
          />
        </div>

        <!-- Status & Priority -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Status</label>
            <select
              v-model="status"
              class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none bg-white"
            >
              <option value="todo">To Do</option>
              <option value="in_progress">In Progress</option>
              <option value="review">In Review</option>
              <option value="completed">Completed</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Priority</label>
            <select
              v-model="priority"
              class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none bg-white"
            >
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
        </div>

        <!-- Section / List & Milestone -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Section / List</label>
            <select
              v-model="taskListId"
              class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none bg-white"
            >
              <option :value="null">Uncategorized</option>
              <option
                v-for="list in taskLists"
                :key="list.id"
                :value="list.id"
              >
                📂 {{ list.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Milestone</label>
            <select
              v-model="milestoneId"
              class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none bg-white"
            >
              <option :value="null">None</option>
              <option
                v-for="ms in milestones"
                :key="ms.id"
                :value="ms.id"
              >
                🎯 {{ ms.title }}
              </option>
            </select>
          </div>
        </div>

        <!-- Multiple Assignees Selector -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="text-xs font-bold text-[#1e1e1e] flex items-center gap-1.5">
              <Users class="w-3.5 h-3.5 text-[#640c0e]" />
              <span>Assignees (Contributors, Managers, Owners)</span>
            </label>
            <span v-if="isLoadingAssignees" class="text-[10px] text-[#6b7280] flex items-center gap-1">
              <Loader2 class="w-3 h-3 animate-spin" /> Loading roster...
            </span>
            <span v-else class="text-[10px] text-[#6b7280]">
              {{ selectedAssigneeIds.length }} selected
            </span>
          </div>

          <!-- Assignees chips / selector -->
          <div class="p-2 rounded-xl border border-[#ebe8de] bg-[#fbfbf9] max-h-36 overflow-y-auto space-y-1">
            <div
              v-for="user in eligibleAssignees"
              :key="user.id"
              @click="toggleAssignee(user.id)"
              class="flex items-center justify-between p-1.5 rounded-lg text-xs cursor-pointer transition-colors"
              :class="[
                selectedAssigneeIds.includes(user.id) ? 'bg-[#640c0e]/10 text-[#640c0e] font-bold' : 'hover:bg-white text-[#4b5563]'
              ]"
            >
              <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded-full bg-gradient-to-br from-[#640c0e] to-[#b02e32] text-white flex items-center justify-center text-[9px] font-bold">
                  {{ user.name.slice(0, 1) }}
                </div>
                <span>{{ user.name }}</span>
                <span v-if="primaryAssigneeId === user.id" class="px-1.5 py-0.2 rounded text-[8px] bg-[#640c0e] text-white font-extrabold uppercase">
                  Primary
                </span>
              </div>

              <div class="flex items-center gap-2">
                <button
                  v-if="selectedAssigneeIds.includes(user.id) && primaryAssigneeId !== user.id"
                  type="button"
                  @click.stop="primaryAssigneeId = user.id"
                  class="text-[9px] text-[#6b7280] hover:text-[#640c0e] underline"
                >
                  Make Primary
                </button>
                <div
                  class="w-4 h-4 rounded border flex items-center justify-center"
                  :class="[selectedAssigneeIds.includes(user.id) ? 'bg-[#640c0e] border-[#640c0e] text-white' : 'border-[#d0ccc0] bg-white']"
                >
                  <Check v-if="selectedAssigneeIds.includes(user.id)" class="w-3 h-3 stroke-[3]" />
                </div>
              </div>
            </div>

            <div v-if="eligibleAssignees.length === 0 && !isLoadingAssignees" class="text-center py-2 text-[11px] text-[#6b7280] italic">
              No eligible project contributors or managers found.
            </div>
          </div>
        </div>

        <!-- Dates: Start Date & Due Date -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-bold text-[#1e1e1e]">Schedule Timeline</span>
            <span v-if="durationDays !== null" class="text-[10px] font-bold text-[#640c0e]">
              Duration: {{ durationDays }} day{{ durationDays === 1 ? '' : 's' }}
            </span>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-semibold text-[#6b7280] mb-1">Start Date</label>
              <input
                v-model="startDate"
                type="date"
                class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none"
              />
            </div>

            <div>
              <label class="block text-[11px] font-semibold text-[#6b7280] mb-1">Due Date</label>
              <input
                v-model="dueDate"
                type="date"
                class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none"
              />
            </div>
          </div>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Description (Optional)</label>
          <textarea
            v-model="description"
            rows="3"
            placeholder="Details, checklist, links, or instructions..."
            class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none resize-none"
          ></textarea>
        </div>

        <!-- Actions -->
        <div class="pt-3 border-t border-[#ebe8de] flex items-center justify-end gap-2">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-[#6b7280] hover:bg-[#fbfbf9] transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="!title.trim() || isSubmitting"
            class="flex items-center gap-1.5 px-5 py-2 rounded-xl text-xs font-bold bg-[#640c0e] text-white hover:bg-[#7e1012] disabled:opacity-50 transition-all shadow-xs"
          >
            <Loader2 v-if="isSubmitting" class="w-3.5 h-3.5 animate-spin" />
            <span>Create Task</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
