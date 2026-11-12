<script setup lang="ts">
import { computed } from 'vue';
import type { Task, TaskStatus } from '../../types/pm';
import TaskCard from './TaskCard.vue';
import { Plus, CheckCircle2, Clock, Eye, AlertCircle } from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    tasks: Task[];
    canEdit?: boolean;
    selectable?: boolean;
    selectedUuids?: string[];
  }>(),
  {
    canEdit: false,
    selectable: false,
    selectedUuids: () => [],
  }
);

const emit = defineEmits<{
  (e: 'task-click', task: Task): void;
  (e: 'add-task', status: TaskStatus): void;
  (e: 'status-change', taskUuid: string, newStatus: TaskStatus): void;
  (e: 'toggle-select', task: Task): void;
}>();

const columns: { id: TaskStatus; label: string; icon: any; color: string }[] = [
  { id: 'todo', label: 'To Do', icon: Clock, color: 'text-gray-600 bg-gray-100' },
  { id: 'in_progress', label: 'In Progress', icon: AlertCircle, color: 'text-blue-600 bg-blue-100' },
  { id: 'review', label: 'In Review', icon: Eye, color: 'text-purple-600 bg-purple-100' },
  { id: 'completed', label: 'Completed', icon: CheckCircle2, color: 'text-emerald-600 bg-emerald-100' },
];

const tasksByStatus = computed(() => {
  const map: Record<TaskStatus, Task[]> = {
    todo: [],
    in_progress: [],
    review: [],
    completed: [],
    cancelled: [],
  };

  props.tasks.forEach((task) => {
    if (map[task.status]) {
      map[task.status].push(task);
    } else {
      map.todo.push(task);
    }
  });

  return map;
});

// HTML5 drag and drop support
const onDragStart = (e: DragEvent, task: Task) => {
  if (e.dataTransfer) {
    e.dataTransfer.setData('text/plain', task.uuid);
    e.dataTransfer.effectAllowed = 'move';
  }
};

const onDragOver = (e: DragEvent) => {
  e.preventDefault();
  if (e.dataTransfer) {
    e.dataTransfer.dropEffect = 'move';
  }
};

const onDrop = (e: DragEvent, targetStatus: TaskStatus) => {
  e.preventDefault();
  const taskUuid = e.dataTransfer?.getData('text/plain');
  if (taskUuid) {
    emit('status-change', taskUuid, targetStatus);
  }
};
</script>

<template>
  <div class="kanban-scroll" role="list" aria-label="Kanban board">
    <div
      v-for="col in columns"
      :key="col.id"
      class="kanban-col p-3 flex flex-col justify-between min-h-[420px]"
      role="listitem"
      @dragover="onDragOver"
      @drop="onDrop($event, col.id)"
    >
      <div>
        <!-- Column Header -->
        <div class="flex items-center justify-between pb-3 mb-3 border-b border-[#e7e4d8] px-1">
          <div class="flex items-center gap-2">
            <span class="w-6 h-6 rounded-lg flex items-center justify-center" :class="col.color">
              <component :is="col.icon" class="w-3.5 h-3.5" />
            </span>
            <span class="text-xs font-bold text-[#1e1e1e] font-display">{{ col.label }}</span>
            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-white text-[#6b7280] shadow-2xs border border-[#ebe8de]">
              {{ tasksByStatus[col.id].length }}
            </span>
          </div>

          <!-- Quick Add Button -->
          <button
            v-if="canEdit"
            type="button"
            @click="$emit('add-task', col.id)"
            class="p-1 rounded-lg text-[#6b7280] hover:text-[#640c0e] hover:bg-white transition-colors"
            :title="`Add task to ${col.label}`"
          >
            <Plus class="w-4 h-4" />
          </button>
        </div>

        <!-- Task Cards Container -->
        <div class="space-y-2.5 min-h-[300px]">
          <div
            v-for="task in tasksByStatus[col.id]"
            :key="task.uuid"
            draggable="true"
            @dragstart="onDragStart($event, task)"
          >
            <TaskCard
              :task="task"
              :selectable="selectable"
              :selected="selectedUuids?.includes(task.uuid)"
              @click="$emit('task-click', task)"
              @status-change="(s) => $emit('status-change', task.uuid, s)"
              @toggle-select="$emit('toggle-select', $event)"
            />
          </div>

          <div
            v-if="tasksByStatus[col.id].length === 0"
            class="py-12 text-center text-xs text-[#6b7280] border border-dashed border-[#ded9cb] rounded-xl bg-white/40"
          >
            No tasks in {{ col.label }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
