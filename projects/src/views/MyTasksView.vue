<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { projectsApi } from '../api/projects';
import type { Task, TaskStatus } from '../types/pm';
import TaskDetailModal from '../components/tasks/TaskDetailModal.vue';
import {
  CheckSquare,
  Calendar,
  CheckCircle2,
  ArrowRight,
  Loader2,
  Paperclip,
  Link2,
  Layers,
} from 'lucide-vue-next';

const tasks = ref<Task[]>([]);
const isLoading = ref(true);
const statusFilter = ref<string>('');
const activeTaskUuid = ref<string | null>(null);
const activeProjectUuid = ref<string | null>(null);

const loadTasks = async () => {
  isLoading.value = true;
  try {
    const data = await projectsApi.getMyTasks({
      status: statusFilter.value || undefined,
    });
    tasks.value = data.tasks;
  } catch (err) {
    console.error('Failed to load my tasks:', err);
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  loadTasks();
});

const onFilterChange = () => {
  loadTasks();
};

const openTask = (task: Task) => {
  activeTaskUuid.value = task.uuid;
  activeProjectUuid.value = task.project?.uuid || '';
};

const onQuickStatusToggle = async (task: Task) => {
  const newStatus: TaskStatus = task.status === 'completed' ? 'todo' : 'completed';
  try {
    await projectsApi.updateTaskStatus(task.uuid, newStatus);
    await loadTasks();
  } catch (err) {
    console.error('Failed to toggle status:', err);
  }
};
</script>

<template>
  <div class="flex flex-col gap-[var(--spacing-section)]">
    <header class="pm-page-header">
      <div class="min-w-0">
        <h1 class="pm-page-title">My Assigned Deliverables</h1>
        <p class="pm-page-subtitle">
          Track and manage tasks assigned to you across all SFU MSA projects.
        </p>
      </div>

      <!-- Filter Controls -->
      <div class="flex items-center gap-2">
        <select
          v-model="statusFilter"
          @change="onFilterChange"
          class="px-3 py-2 rounded-xl border border-[#ebe8de] text-xs font-semibold bg-white focus:outline-none"
        >
          <option value="">All Open Tasks</option>
          <option value="todo">To Do</option>
          <option value="in_progress">In Progress</option>
          <option value="review">In Review</option>
          <option value="completed">Completed</option>
        </select>
      </div>
    </header>

    <!-- Loading -->
    <div v-if="isLoading" class="py-20 flex items-center justify-center text-[#6b7280]">
      <Loader2 class="w-7 h-7 animate-spin text-[#640c0e]" />
    </div>

    <!-- Tasks List -->
    <div v-else-if="tasks.length > 0" class="pm-panel divide-y divide-[#ebe8de]">
      <div
        v-for="task in tasks"
        :key="task.uuid"
        class="p-4 flex items-center justify-between hover:bg-[#fffbf4] transition-colors group cursor-pointer"
        @click="openTask(task)"
      >
        <div class="flex items-center gap-3 min-w-0">
          <button
            type="button"
            @click.stop="onQuickStatusToggle(task)"
            class="text-[#6b7280] hover:text-[#640c0e] transition-colors shrink-0"
            title="Toggle completed"
          >
            <CheckCircle2
              class="w-5 h-5"
              :class="task.status === 'completed' ? 'text-emerald-600 fill-emerald-100' : 'text-[#ded9cb]'"
            />
          </button>

          <div class="min-w-0">
            <h3
              class="text-xs font-bold text-[#1e1e1e] group-hover:text-[#640c0e] transition-colors truncate"
              :class="{ 'line-through text-[#6b7280]': task.status === 'completed' }"
            >
              {{ task.title }}
            </h3>

            <div class="flex items-center gap-2 mt-1 text-[11px] text-[#6b7280] flex-wrap">
              <span v-if="task.project" class="font-semibold text-[#640c0e]">
                {{ task.project.name }}
              </span>
              <span v-if="task.task_list" class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded bg-gray-100 text-gray-700 text-[10px]">
                <Layers class="w-3 h-3 text-gray-500" />
                <span>{{ task.task_list.name }}</span>
              </span>
              <span v-if="task.milestone" class="text-gray-500">
                &bull; 🎯 {{ task.milestone.title }}
              </span>
              <span v-if="task.dependencies_count" class="inline-flex items-center gap-0.5 text-amber-700 text-[10px]">
                <Link2 class="w-3 h-3" />
                <span>{{ task.dependencies_count }} deps</span>
              </span>
              <span v-if="task.attachments_count" class="inline-flex items-center gap-0.5 text-blue-700 text-[10px]">
                <Paperclip class="w-3 h-3" />
                <span>{{ task.attachments_count }}</span>
              </span>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-3 shrink-0 ml-3">
          <!-- Assignee Avatar Stack -->
          <div
            v-if="task.assignees && task.assignees.length > 0"
            class="flex items-center -space-x-1.5"
            :title="task.assignees.map((a) => a.name).join(', ')"
          >
            <template v-for="assignee in task.assignees.slice(0, 2)" :key="assignee.id">
              <img
                v-if="assignee.avatar"
                :src="assignee.avatar"
                :alt="assignee.name"
                class="w-5 h-5 rounded-full ring-2 ring-white object-cover"
              />
              <div
                v-else
                class="w-5 h-5 rounded-full bg-[#640c0e] text-white flex items-center justify-center text-[9px] font-bold ring-2 ring-white"
              >
                {{ assignee.name.charAt(0) }}
              </div>
            </template>
            <span
              v-if="task.assignees.length > 2"
              class="w-5 h-5 rounded-full bg-gray-200 text-gray-700 flex items-center justify-center text-[8px] font-bold ring-2 ring-white"
            >
              +{{ task.assignees.length - 2 }}
            </span>
          </div>

          <span
            v-if="task.due_date"
            class="text-[11px] font-medium text-[#6b7280] hidden sm:flex items-center gap-1"
          >
            <Calendar class="w-3.5 h-3.5" />
            <span>{{ task.due_date }}</span>
          </span>

          <span
            class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border"
            :class="[
              task.priority === 'urgent' ? 'bg-rose-50 text-rose-700 border-rose-200' :
              task.priority === 'high' ? 'bg-amber-50 text-amber-700 border-amber-200' :
              task.priority === 'medium' ? 'bg-blue-50 text-blue-700 border-blue-200' :
              'bg-gray-50 text-gray-700 border-gray-200'
            ]"
          >
            {{ task.priority }}
          </span>

          <router-link
            v-if="task.project?.uuid"
            :to="`/projects/${task.project.uuid}`"
            @click.stop
            class="p-1 rounded-lg text-[#6b7280] hover:text-[#640c0e] transition-colors hidden sm:block"
            title="Open project workspace"
          >
            <ArrowRight class="w-4 h-4" />
          </router-link>
        </div>
      </div>
    </div>

    <div
      v-else
      class="py-20 text-center bg-white rounded-2xl border border-dashed border-[#ded9cb] p-8 max-w-md mx-auto space-y-2"
    >
      <CheckSquare class="w-10 h-10 text-[#640c0e] mx-auto opacity-40" />
      <h3 class="text-sm font-bold text-[#1e1e1e] font-display">No tasks assigned</h3>
      <p class="text-xs text-[#6b7280]">
        You are all caught up! When project coordinators assign you deliverables, they will appear here.
      </p>
    </div>

    <!-- Modal -->
    <TaskDetailModal
      v-if="activeTaskUuid && activeProjectUuid"
      :is-open="!!activeTaskUuid"
      :task-uuid="activeTaskUuid"
      :project-uuid="activeProjectUuid"
      @close="activeTaskUuid = null"
      @task-updated="loadTasks"
      @task-deleted="loadTasks"
    />
  </div>
</template>
