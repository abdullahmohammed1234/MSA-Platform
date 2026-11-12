<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { projectsApi } from '../../api/projects';
import type { CascadePreview, GanttDataset, GanttTask } from '../../types/pm';
import GanttDependencyOverlay from './GanttDependencyOverlay.vue';
import GanttTaskRow from './GanttTaskRow.vue';
import GanttTimelineHeader, { type GanttZoomLevel } from './GanttTimelineHeader.vue';
import GanttToolbar, { type GanttGrouping } from './GanttToolbar.vue';

interface Props {
  projectUuid: string;
  canEdit?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  canEdit: false,
});

const emit = defineEmits<{
  (e: 'open-task', taskUuid: string): void;
}>();

const loading = ref(true);
const error = ref<string | null>(null);
const ganttData = ref<GanttDataset | null>(null);

const zoomLevel = ref<GanttZoomLevel>('day');
const grouping = ref<GanttGrouping>('none');
const showDependencies = ref(true);
const showMilestones = ref(true);
const myTasksOnly = ref(false);

const ROW_HEIGHT = 40;

const dayPitch = computed<number>(() => {
  switch (zoomLevel.value) {
    case 'day':
      return 32;
    case 'week':
      return 14;
    case 'month':
      return 4;
    case 'quarter':
      return 1.5;
    default:
      return 32;
  }
});

const timelineStart = computed<string>(() => {
  return ganttData.value?.timeline_bounds.start_date || new Date().toISOString().split('T')[0];
});

const timelineEnd = computed<string>(() => {
  return ganttData.value?.timeline_bounds.end_date || new Date().toISOString().split('T')[0];
});

function diffDays(a: string, b: string): number {
  const dateA = new Date(a + 'T00:00:00');
  const dateB = new Date(b + 'T00:00:00');
  return Math.round((dateA.getTime() - dateB.getTime()) / (1000 * 60 * 60 * 24));
}

const totalTimelineDays = computed<number>(() => {
  return Math.max(1, diffDays(timelineEnd.value, timelineStart.value) + 1);
});

const timelineTotalWidth = computed<number>(() => {
  return totalTimelineDays.value * dayPitch.value;
});

// Load Gantt Data
const loadGanttData = async () => {
  loading.value = true;
  error.value = null;
  try {
    ganttData.value = await projectsApi.getProjectGantt(props.projectUuid);
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load Gantt chart data.';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadGanttData();
});

watch(() => props.projectUuid, () => {
  loadGanttData();
});

// Filter & Group tasks
const filteredTasks = computed<GanttTask[]>(() => {
  if (!ganttData.value) return [];
  let tasks = [...ganttData.value.tasks];

  if (myTasksOnly.value) {
    // Current user tasks
    const currentUserId = Number(localStorage.getItem('auth_user_id') || 0);
    if (currentUserId) {
      tasks = tasks.filter((t) => t.assigned_to === currentUserId || t.assignees.some((a) => a.id === currentUserId));
    }
  }

  return tasks;
});

interface GroupedTaskBlock {
  key: string;
  label: string;
  tasks: GanttTask[];
}

const groupedBlocks = computed<GroupedTaskBlock[]>(() => {
  const tasks = filteredTasks.value;

  if (grouping.value === 'none') {
    return [{ key: 'all', label: 'All Tasks', tasks }];
  }

  if (grouping.value === 'section') {
    const listMap = new Map<string, GanttTask[]>();
    tasks.forEach((t) => {
      const k = t.task_list_name || 'Uncategorized';
      if (!listMap.has(k)) listMap.set(k, []);
      listMap.get(k)!.push(t);
    });
    return Array.from(listMap.entries()).map(([k, tks]) => ({
      key: k,
      label: k,
      tasks: tks,
    }));
  }

  if (grouping.value === 'assignee') {
    const assigneeMap = new Map<string, GanttTask[]>();
    tasks.forEach((t) => {
      const k = t.assignees.length > 0 ? t.assignees.map((a) => a.name).join(', ') : 'Unassigned';
      if (!assigneeMap.has(k)) assigneeMap.set(k, []);
      assigneeMap.get(k)!.push(t);
    });
    return Array.from(assigneeMap.entries()).map(([k, tks]) => ({
      key: k,
      label: k,
      tasks: tks,
    }));
  }

  if (grouping.value === 'status') {
    const statusMap = new Map<string, GanttTask[]>();
    tasks.forEach((t) => {
      const k = t.status.replace('_', ' ').toUpperCase();
      if (!statusMap.has(k)) statusMap.set(k, []);
      statusMap.get(k)!.push(t);
    });
    return Array.from(statusMap.entries()).map(([k, tks]) => ({
      key: k,
      label: k,
      tasks: tks,
    }));
  }

  return [{ key: 'all', label: 'All Tasks', tasks }];
});

// Flat list of tasks in current rendered row order (for SVG overlay)
const renderedTasksFlat = computed<GanttTask[]>(() => {
  return groupedBlocks.value.flatMap((b) => b.tasks);
});

const timelineTotalHeight = computed<number>(() => {
  return renderedTasksFlat.value.length * ROW_HEIGHT;
});

// Scroll sync
const timelineScrollContainer = ref<HTMLDivElement | null>(null);

const scrollToToday = () => {
  if (!timelineScrollContainer.value) return;
  const todayStr = new Date().toISOString().split('T')[0];
  const daysFromStart = diffDays(todayStr, timelineStart.value);
  const targetPx = Math.max(0, daysFromStart * dayPitch.value - 200);
  timelineScrollContainer.value.scrollTo({ left: targetPx, behavior: 'smooth' });
};

// Reschedule & Cascade Handling
const pendingReschedule = ref<{
  task: GanttTask;
  newStartDate: string;
  newDueDate: string;
} | null>(null);

const cascadePreview = ref<CascadePreview | null>(null);
const isRescheduling = ref(false);

const handleTaskReschedule = async (payload: { task: GanttTask; newStartDate: string; newDueDate: string }) => {
  pendingReschedule.value = payload;

  try {
    const preview = await projectsApi.previewReschedule(payload.task.uuid, {
      start_date: payload.newStartDate,
      due_date: payload.newDueDate,
    });

    if (preview.total_affected_count > 0) {
      cascadePreview.value = preview;
    } else {
      await executeReschedule(false);
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to preview reschedule.');
    pendingReschedule.value = null;
  }
};

const executeReschedule = async (cascade: boolean) => {
  if (!pendingReschedule.value) return;
  isRescheduling.value = true;
  try {
    await projectsApi.rescheduleTask(pendingReschedule.value.task.uuid, {
      start_date: pendingReschedule.value.newStartDate,
      due_date: pendingReschedule.value.newDueDate,
      cascade,
    });
    cascadePreview.value = null;
    pendingReschedule.value = null;
    await loadGanttData();
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to commit task reschedule.');
  } finally {
    isRescheduling.value = false;
  }
};

const cancelReschedule = () => {
  cascadePreview.value = null;
  pendingReschedule.value = null;
};
</script>

<template>
  <div class="flex flex-col h-[750px] bg-white border border-[#ebe8de] rounded-2xl overflow-hidden shadow-xs">
    <!-- Toolbar -->
    <GanttToolbar
      v-model:zoomLevel="zoomLevel"
      v-model:grouping="grouping"
      v-model:showDependencies="showDependencies"
      v-model:showMilestones="showMilestones"
      v-model:myTasksOnly="myTasksOnly"
      @scroll-to-today="scrollToToday"
    />

    <!-- Main Viewport -->
    <div v-if="loading" class="flex-1 flex items-center justify-center text-[#6b7280]">
      <div class="animate-spin w-7 h-7 border-2 border-[#640c0e] border-t-transparent rounded-full mr-3" />
      <span class="text-xs font-medium">Loading Gantt Chart...</span>
    </div>

    <div v-else-if="error" class="flex-1 flex flex-col items-center justify-center p-6 text-center text-[#6b7280]">
      <p class="text-[#dc2626] font-medium mb-3 text-xs">{{ error }}</p>
      <button
        type="button"
        class="px-4 py-1.5 bg-[#640c0e] hover:bg-[#7e1012] text-white rounded-xl text-xs font-bold transition-colors shadow-2xs"
        @click="loadGanttData"
      >
        Retry
      </button>
    </div>

    <div v-else class="flex-1 flex overflow-hidden">
      <!-- Left Panel: Task Hierarchy Roster (320px) -->
      <div class="w-80 border-r border-[#ebe8de] bg-[#fbfbf9] flex flex-col shrink-0 select-none">
        <!-- Header -->
        <div class="h-[53px] px-3 border-b border-[#ebe8de] flex items-center justify-between text-xs font-bold text-[#640c0e] bg-[#fbfbf9]">
          <span>Task / Deliverable</span>
          <span class="text-[10px] text-[#6b7280] font-normal">Assignee</span>
        </div>

        <!-- Task Rows Roster -->
        <div class="flex-1 overflow-y-auto divide-y divide-[#ebe8de]/50">
          <div v-for="block in groupedBlocks" :key="block.key">
            <div
              v-if="grouping !== 'none'"
              class="px-3 py-1 bg-[#f4f2ea] text-[10px] font-bold text-[#640c0e] uppercase tracking-wider sticky top-0 z-10 border-b border-[#ebe8de]"
            >
              {{ block.label }} ({{ block.tasks.length }})
            </div>

            <div
              v-for="task in block.tasks"
              :key="task.id"
              class="h-10 px-3 flex items-center justify-between hover:bg-[#fffbf4] cursor-pointer text-xs transition-colors"
              @click="emit('open-task', task.uuid)"
            >
              <div class="flex items-center min-w-0 mr-2">
                <span
                  class="w-2 h-2 rounded-full mr-2 shrink-0"
                  :class="task.status === 'completed' ? 'bg-[#105232]' : task.status === 'in_progress' ? 'bg-[#640c0e]' : task.status === 'review' ? 'bg-[#d97706]' : 'bg-[#9ca3af]'"
                />
                <span class="truncate font-medium text-[#1e1e1e] text-[11px]">{{ task.title }}</span>
              </div>

              <div class="flex items-center gap-1.5 shrink-0">
                <span
                  v-if="task.assignees.length > 0"
                  class="w-5 h-5 rounded-full bg-white border border-[#ebe8de] flex items-center justify-center text-[9px] font-bold text-[#640c0e]"
                  :title="task.assignees.map(a => a.name).join(', ')"
                >
                  {{ task.assignees[0].name.charAt(0) }}
                </span>
                <span v-else class="text-[10px] text-[#9ca3af]">—</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Panel: Interactive Timeline with SVG Vector Overlay -->
      <div
        ref="timelineScrollContainer"
        class="flex-1 overflow-x-auto overflow-y-auto relative bg-white"
      >
        <div :style="{ width: `${timelineTotalWidth}px`, minWidth: '100%' }">
          <!-- Timeline Header -->
          <GanttTimelineHeader
            :start-date="timelineStart"
            :end-date="timelineEnd"
            :zoom-level="zoomLevel"
            :day-pitch="dayPitch"
          />

          <!-- Timeline Canvas with Rows & Overlay -->
          <div class="relative" :style="{ height: `${timelineTotalHeight}px` }">
            <!-- Background Day Alternation / Weekend Shading -->
            <div class="absolute inset-0 pointer-events-none flex">
              <div
                v-for="idx in totalTimelineDays"
                :key="idx"
                class="border-r border-[#ebe8de]/70 h-full shrink-0"
                :style="{ width: `${dayPitch}px` }"
              />
            </div>

            <!-- Task Rows -->
            <div v-for="block in groupedBlocks" :key="block.key">
              <div v-if="grouping !== 'none'" class="h-6 bg-[#fbfbf9]/80 border-b border-[#ebe8de]" />

              <GanttTaskRow
                v-for="(task, rIdx) in block.tasks"
                :key="task.id"
                :task="task"
                :timeline-start="timelineStart"
                :day-pitch="dayPitch"
                :row-index="rIdx"
                :row-height="ROW_HEIGHT"
                :can-edit="canEdit"
                @click-task="emit('open-task', task.uuid)"
                @reschedule="handleTaskReschedule"
              />
            </div>

            <!-- SVG Dependency Vector Overlay -->
            <GanttDependencyOverlay
              v-if="showDependencies && ganttData && ganttData.dependencies.length > 0"
              :dependencies="ganttData.dependencies"
              :tasks="renderedTasksFlat"
              :timeline-start="timelineStart"
              :day-pitch="dayPitch"
              :row-height="ROW_HEIGHT"
              :total-width="timelineTotalWidth"
              :total-height="timelineTotalHeight"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Cascade Reschedule Confirmation Modal -->
    <div
      v-if="cascadePreview"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4 animate-in fade-in"
    >
      <div class="bg-white border border-[#ebe8de] rounded-2xl shadow-elevated max-w-lg w-full p-6 text-[#1e1e1e]">
        <h3 class="text-base font-bold text-[#640c0e] mb-2 flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-[#d97706]" />
          Reschedule Cascade Confirmation
        </h3>

        <p class="text-xs text-[#6b7280] leading-relaxed mb-4">
          Rescheduling <strong class="text-[#640c0e]">{{ cascadePreview.root_task.title }}</strong> impacts
          <strong class="text-[#d97706]">{{ cascadePreview.total_affected_count }}</strong> dependent deliverable(s).
          Choose whether to reschedule this task only or cascade dates automatically down the dependency chain:
        </p>

        <div class="max-h-48 overflow-y-auto mb-5 rounded-xl border border-[#ebe8de] bg-[#fbfbf9] p-2.5 space-y-2 text-xs">
          <div
            v-for="aff in cascadePreview.affected_tasks"
            :key="aff.id"
            class="flex items-center justify-between p-2 rounded-lg bg-white border border-[#ebe8de]"
          >
            <span class="truncate font-medium text-[#1e1e1e] text-[11px]">{{ aff.title }}</span>
            <span class="text-[10px] text-[#b45309] font-mono shrink-0 ml-2 font-semibold">
              {{ aff.new_start_date }} → {{ aff.new_due_date }} (+{{ aff.shift_days }}d)
            </span>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2.5">
          <button
            type="button"
            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#fbfbf9] transition-colors"
            :disabled="isRescheduling"
            @click="cancelReschedule"
          >
            Cancel
          </button>

          <button
            type="button"
            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-[#fbfbf9] hover:bg-[#f4f2ea] text-[#1e1e1e] border border-[#ebe8de] transition-colors"
            :disabled="isRescheduling"
            @click="executeReschedule(false)"
          >
            This Task Only
          </button>

          <button
            type="button"
            class="px-4 py-1.5 rounded-xl text-xs font-bold bg-[#640c0e] hover:bg-[#7e1012] text-white shadow-2xs transition-colors"
            :disabled="isRescheduling"
            @click="executeReschedule(true)"
          >
            Cascade to Dependents ({{ cascadePreview.total_affected_count }})
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

