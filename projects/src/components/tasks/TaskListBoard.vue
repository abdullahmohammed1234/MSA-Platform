<script setup lang="ts">
import { ref, computed } from 'vue';
import type { Task, TaskList } from '../../types/pm';
import TaskCard from './TaskCard.vue';
import { Plus, Folder, Trash2, Edit2, Check, X, ArrowLeft, ArrowRight, Layers } from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    tasks: Task[];
    lists: TaskList[];
    canManageLists?: boolean;
    canEditTasks?: boolean;
    selectable?: boolean;
    selectedUuids?: string[];
  }>(),
  {
    canManageLists: false,
    canEditTasks: false,
    selectable: false,
    selectedUuids: () => [],
  }
);

const emit = defineEmits<{
  (e: 'task-click', task: Task): void;
  (e: 'add-task-to-list', listId: number | null): void;
  (e: 'move-task-to-list', taskUuid: string, targetListId: number | null): void;
  (e: 'create-list', name: string, color?: string): void;
  (e: 'update-list', listUuid: string, name: string, color?: string): void;
  (e: 'delete-list', listUuid: string): void;
  (e: 'reorder-lists', listUuids: string[]): void;
  (e: 'toggle-select', task: Task): void;
}>();

// Inline list editing state
const editingListUuid = ref<string | null>(null);
const editingListName = ref('');

// Add list state
const isAddingList = ref(false);
const newListName = ref('');

const tasksByList = computed(() => {
  const map: Record<string, Task[]> = {
    uncategorized: [],
  };

  props.lists.forEach((list) => {
    map[list.id.toString()] = [];
  });

  props.tasks.forEach((task) => {
    if (task.task_list_id && map[task.task_list_id.toString()]) {
      map[task.task_list_id.toString()].push(task);
    } else {
      map.uncategorized.push(task);
    }
  });

  return map;
});

const startEditList = (list: TaskList) => {
  editingListUuid.value = list.uuid;
  editingListName.value = list.name;
};

const saveEditList = (list: TaskList) => {
  if (editingListName.value.trim() && editingListName.value.trim() !== list.name) {
    emit('update-list', list.uuid, editingListName.value.trim());
  }
  editingListUuid.value = null;
};

const cancelEditList = () => {
  editingListUuid.value = null;
};

const handleCreateList = () => {
  if (!newListName.value.trim()) return;
  emit('create-list', newListName.value.trim());
  newListName.value = '';
  isAddingList.value = false;
};

const moveListOrder = (index: number, direction: 'left' | 'right') => {
  const targetIndex = direction === 'left' ? index - 1 : index + 1;
  if (targetIndex < 0 || targetIndex >= props.lists.length) return;

  const currentUuids = props.lists.map((l) => l.uuid);
  const temp = currentUuids[index];
  currentUuids[index] = currentUuids[targetIndex];
  currentUuids[targetIndex] = temp;

  emit('reorder-lists', currentUuids);
};

// Drag and drop between lists
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

const onDrop = (e: DragEvent, targetListId: number | null) => {
  e.preventDefault();
  const taskUuid = e.dataTransfer?.getData('text/plain');
  if (taskUuid) {
    emit('move-task-to-list', taskUuid, targetListId);
  }
};
</script>

<template>
  <div class="space-y-4">
    <!-- Top toolbar for list management -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <Layers class="w-4 h-4 text-[#640c0e]" />
        <span class="text-xs font-bold text-[#1e1e1e]">Custom Sections & Task Lists</span>
        <span class="text-[11px] text-[#6b7280]">
          ({{ lists.length }} sections &bull; {{ tasksByList.uncategorized.length }} uncategorized)
        </span>
      </div>

      <div v-if="canManageLists" class="flex items-center gap-2">
        <div v-if="isAddingList" class="flex items-center gap-1.5 bg-white p-1 rounded-xl border border-[#ded9cb] shadow-xs">
          <input
            v-model="newListName"
            type="text"
            placeholder="Section title..."
            class="px-2.5 py-1 text-xs rounded-lg border border-[#e5e2d6] focus:outline-none focus:ring-1 focus:ring-[#640c0e] w-44"
            @keyup.enter="handleCreateList"
            @keyup.esc="isAddingList = false"
            autofocus
          />
          <button
            type="button"
            @click="handleCreateList"
            class="p-1 rounded-md bg-[#640c0e] text-white hover:bg-[#b02e32] transition-colors"
            title="Save Section"
          >
            <Check class="w-3.5 h-3.5" />
          </button>
          <button
            type="button"
            @click="isAddingList = false"
            class="p-1 rounded-md text-[#6b7280] hover:text-[#1e1e1e] transition-colors"
            title="Cancel"
          >
            <X class="w-3.5 h-3.5" />
          </button>
        </div>

        <button
          v-else
          type="button"
          @click="isAddingList = true"
          class="px-3 py-1.5 rounded-xl bg-white border border-[#ebe8de] text-xs font-semibold text-[#640c0e] hover:bg-[#640c0e]/5 transition-colors flex items-center gap-1.5 shadow-2xs"
        >
          <Plus class="w-3.5 h-3.5" />
          <span>Add Section</span>
        </button>
      </div>
    </div>

    <!-- Swimlanes / Columns Grid -->
    <div class="flex gap-4 overflow-x-auto pb-4 items-start min-h-[450px]">
      <!-- Custom Section Columns -->
      <div
        v-for="(list, index) in lists"
        :key="list.uuid"
        class="kanban-col w-72 shrink-0 p-3 flex flex-col justify-between rounded-2xl bg-[#f7f6f0] border border-[#e7e4d8]"
        @dragover="onDragOver"
        @drop="onDrop($event, list.id)"
      >
        <div>
          <!-- Column Header -->
          <div class="flex items-center justify-between pb-3 mb-3 border-b border-[#e7e4d8] px-1">
            <div class="flex items-center gap-2 flex-1 min-w-0 mr-2">
              <span class="w-6 h-6 rounded-lg flex items-center justify-center text-amber-700 bg-amber-100 shrink-0">
                <Folder class="w-3.5 h-3.5" />
              </span>

              <!-- Editable List Title -->
              <div v-if="editingListUuid === list.uuid" class="flex items-center gap-1 flex-1">
                <input
                  v-model="editingListName"
                  type="text"
                  class="w-full text-xs font-bold text-[#1e1e1e] px-1.5 py-0.5 rounded border border-[#640c0e] bg-white focus:outline-none"
                  @keyup.enter="saveEditList(list)"
                  @keyup.esc="cancelEditList"
                  autofocus
                />
                <button type="button" @click="saveEditList(list)" class="p-1 text-emerald-600 hover:text-emerald-700">
                  <Check class="w-3.5 h-3.5" />
                </button>
                <button type="button" @click="cancelEditList" class="p-1 text-[#6b7280] hover:text-[#1e1e1e]">
                  <X class="w-3.5 h-3.5" />
                </button>
              </div>

              <div v-else class="flex items-center gap-1.5 min-w-0">
                <span class="text-xs font-bold text-[#1e1e1e] font-display truncate" :title="list.name">
                  {{ list.name }}
                </span>
                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-white text-[#6b7280] shadow-2xs border border-[#ebe8de]">
                  {{ (tasksByList[list.id.toString()] || []).length }}
                </span>
              </div>
            </div>

            <!-- List Actions -->
            <div class="flex items-center gap-0.5">
              <button
                v-if="canEditTasks"
                type="button"
                @click="$emit('add-task-to-list', list.id)"
                class="p-1 rounded-lg text-[#6b7280] hover:text-[#640c0e] hover:bg-white transition-colors"
                :title="`Add task to ${list.name}`"
              >
                <Plus class="w-3.5 h-3.5" />
              </button>

              <template v-if="canManageLists && editingListUuid !== list.uuid">
                <button
                  type="button"
                  @click="startEditList(list)"
                  class="p-1 rounded-lg text-[#6b7280] hover:text-[#1e1e1e] hover:bg-white transition-colors"
                  title="Rename Section"
                >
                  <Edit2 class="w-3 h-3" />
                </button>
                <button
                  v-if="index > 0"
                  type="button"
                  @click="moveListOrder(index, 'left')"
                  class="p-1 rounded-lg text-[#6b7280] hover:text-[#1e1e1e] hover:bg-white transition-colors"
                  title="Move Left"
                >
                  <ArrowLeft class="w-3 h-3" />
                </button>
                <button
                  v-if="index < lists.length - 1"
                  type="button"
                  @click="moveListOrder(index, 'right')"
                  class="p-1 rounded-lg text-[#6b7280] hover:text-[#1e1e1e] hover:bg-white transition-colors"
                  title="Move Right"
                >
                  <ArrowRight class="w-3 h-3" />
                </button>
                <button
                  type="button"
                  @click="$emit('delete-list', list.uuid)"
                  class="p-1 rounded-lg text-[#6b7280] hover:text-red-600 hover:bg-white transition-colors"
                  title="Delete Section (Tasks move to Uncategorized)"
                >
                  <Trash2 class="w-3 h-3" />
                </button>
              </template>
            </div>
          </div>

          <!-- Task Cards in this List -->
          <div class="space-y-2.5 min-h-[300px]">
            <div
              v-for="task in (tasksByList[list.id.toString()] || [])"
              :key="task.uuid"
              draggable="true"
              @dragstart="onDragStart($event, task)"
            >
              <TaskCard
                :task="task"
                :selectable="selectable"
                :selected="selectedUuids?.includes(task.uuid)"
                @click="$emit('task-click', task)"
                @toggle-select="$emit('toggle-select', $event)"
              />
            </div>

            <div
              v-if="(tasksByList[list.id.toString()] || []).length === 0"
              class="py-12 text-center text-xs text-[#6b7280] border border-dashed border-[#ded9cb] rounded-xl bg-white/40"
            >
              No tasks in {{ list.name }}
            </div>
          </div>
        </div>
      </div>

      <!-- Uncategorized Column -->
      <div
        class="kanban-col w-72 shrink-0 p-3 flex flex-col justify-between rounded-2xl bg-[#f2efe6] border border-[#ded9cb] border-dashed"
        @dragover="onDragOver"
        @drop="onDrop($event, null)"
      >
        <div>
          <!-- Column Header -->
          <div class="flex items-center justify-between pb-3 mb-3 border-b border-[#ded9cb] px-1">
            <div class="flex items-center gap-2">
              <span class="w-6 h-6 rounded-lg flex items-center justify-center text-gray-600 bg-gray-200">
                <Layers class="w-3.5 h-3.5" />
              </span>
              <span class="text-xs font-bold text-[#1e1e1e] font-display">Uncategorized</span>
              <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-white text-[#6b7280] shadow-2xs border border-[#ebe8de]">
                {{ tasksByList.uncategorized.length }}
              </span>
            </div>

            <button
              v-if="canEditTasks"
              type="button"
              @click="$emit('add-task-to-list', null)"
              class="p-1 rounded-lg text-[#6b7280] hover:text-[#640c0e] hover:bg-white transition-colors"
              title="Add task to Uncategorized"
            >
              <Plus class="w-4 h-4" />
            </button>
          </div>

          <!-- Task Cards in Uncategorized -->
          <div class="space-y-2.5 min-h-[300px]">
            <div
              v-for="task in tasksByList.uncategorized"
              :key="task.uuid"
              draggable="true"
              @dragstart="onDragStart($event, task)"
            >
              <TaskCard
                :task="task"
                :selectable="selectable"
                :selected="selectedUuids?.includes(task.uuid)"
                @click="$emit('task-click', task)"
                @toggle-select="$emit('toggle-select', $event)"
              />
            </div>

            <div
              v-if="tasksByList.uncategorized.length === 0"
              class="py-12 text-center text-xs text-[#6b7280] border border-dashed border-[#ded9cb] rounded-xl bg-white/40"
            >
              No uncategorized tasks
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

