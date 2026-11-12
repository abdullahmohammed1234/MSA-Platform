<script setup lang="ts">
import { computed, ref } from 'vue';
import type { GanttTask } from '../../types/pm';

interface Props {
  task: GanttTask;
  timelineStart: string; // YYYY-MM-DD
  dayPitch: number;      // px per day
  rowIndex: number;
  rowHeight?: number;    // px, default 40
  canEdit?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  rowHeight: 40,
  canEdit: false,
});

const emit = defineEmits<{
  (e: 'click-task', task: GanttTask): void;
  (e: 'reschedule', payload: { task: GanttTask; newStartDate: string; newDueDate: string }): void;
}>();

// Helper: diff in days between two YYYY-MM-DD strings
function diffDays(a: string, b: string): number {
  const dateA = new Date(a + 'T00:00:00');
  const dateB = new Date(b + 'T00:00:00');
  return Math.round((dateA.getTime() - dateB.getTime()) / (1000 * 60 * 60 * 24));
}

function addDaysToDateStr(dateStr: string, days: number): string {
  const d = new Date(dateStr + 'T00:00:00');
  d.setDate(d.getDate() + days);
  return d.toISOString().split('T')[0];
}

const effectiveStartDate = computed<string>(() => {
  return props.task.start_date || props.task.due_date || props.timelineStart;
});

const effectiveDueDate = computed<string>(() => {
  return props.task.due_date || props.task.start_date || props.timelineStart;
});

const leftOffset = computed<number>(() => {
  const daysFromStart = diffDays(effectiveStartDate.value, props.timelineStart);
  return Math.max(0, daysFromStart * props.dayPitch);
});

const barWidth = computed<number>(() => {
  const duration = Math.max(1, diffDays(effectiveDueDate.value, effectiveStartDate.value) + 1);
  return Math.max(props.dayPitch, duration * props.dayPitch);
});

// Dragging state
const isDragging = ref(false);
const dragMode = ref<'move' | 'resize-left' | 'resize-right' | null>(null);
const dragStartX = ref(0);
const dragInitialStart = ref('');
const dragInitialDue = ref('');
const dragDeltaDays = ref(0);

function onPointerDown(e: PointerEvent, mode: 'move' | 'resize-left' | 'resize-right') {
  if (!props.canEdit) return;
  e.stopPropagation();

  isDragging.value = true;
  dragMode.value = mode;
  dragStartX.value = e.clientX;
  dragInitialStart.value = effectiveStartDate.value;
  dragInitialDue.value = effectiveDueDate.value;
  dragDeltaDays.value = 0;

  window.addEventListener('pointermove', onPointerMove);
  window.addEventListener('pointerup', onPointerUp);
}

function onPointerMove(e: PointerEvent) {
  if (!isDragging.value) return;
  const deltaPx = e.clientX - dragStartX.value;
  const snappedDays = Math.round(deltaPx / props.dayPitch);
  dragDeltaDays.value = snappedDays;
}

function onPointerUp() {
  if (!isDragging.value) return;
  window.removeEventListener('pointermove', onPointerMove);
  window.removeEventListener('pointerup', onPointerUp);

  const delta = dragDeltaDays.value;
  const mode = dragMode.value;

  isDragging.value = false;
  dragMode.value = null;

  if (delta === 0) return;

  let newStart = dragInitialStart.value;
  let newDue = dragInitialDue.value;

  if (mode === 'move') {
    newStart = addDaysToDateStr(dragInitialStart.value, delta);
    newDue = addDaysToDateStr(dragInitialDue.value, delta);
  } else if (mode === 'resize-left') {
    const candidateStart = addDaysToDateStr(dragInitialStart.value, delta);
    if (diffDays(candidateStart, newDue) <= 0) {
      newStart = candidateStart;
    }
  } else if (mode === 'resize-right') {
    const candidateDue = addDaysToDateStr(dragInitialDue.value, delta);
    if (diffDays(candidateDue, newStart) >= 0) {
      newDue = candidateDue;
    }
  }

  emit('reschedule', {
    task: props.task,
    newStartDate: newStart,
    newDueDate: newDue,
  });
}

// Status & Priority Styling
const statusColorClass = computed(() => {
  switch (props.task.status) {
    case 'completed':
      return 'bg-[#105232] border-[#0c4027] text-white shadow-2xs';
    case 'in_progress':
      return 'bg-[#640c0e] border-[#4e090b] text-white shadow-2xs';
    case 'review':
      return 'bg-[#d97706] border-[#b45309] text-white shadow-2xs';
    case 'todo':
    default:
      return 'bg-[#4b5563] border-[#374151] text-white shadow-2xs';
  }
});

const priorityBadge = computed(() => {
  if (props.task.priority === 'urgent') return 'bg-[#be123c]';
  if (props.task.priority === 'high') return 'bg-[#d97706]';
  return null;
});
</script>

<template>
  <div
    class="relative w-full border-b border-[#ebe8de]/60 hover:bg-[#fffbf4]/60 transition-colors"
    :style="{ height: `${rowHeight}px` }"
  >
    <!-- Task Bar -->
    <div
      class="absolute top-1.5 bottom-1.5 rounded-lg shadow-2xs border text-xs flex items-center px-2 cursor-pointer select-none group transition-all"
      :class="[
        statusColorClass,
        isDragging ? 'opacity-85 ring-2 ring-[#640c0e]/40 z-30' : 'hover:shadow-md z-10',
      ]"
      :style="{
        left: `${leftOffset}px`,
        width: `${barWidth}px`,
      }"
      @click="emit('click-task', task)"
      @pointerdown="onPointerDown($event, 'move')"
    >
      <!-- Resize handle left -->
      <div
        v-if="canEdit"
        class="absolute left-0 top-0 bottom-0 w-2 cursor-ew-resize hover:bg-white/30 rounded-l-lg"
        @pointerdown.stop="onPointerDown($event, 'resize-left')"
      />

      <!-- Priority indicator -->
      <span
        v-if="priorityBadge"
        class="w-1.5 h-1.5 rounded-full mr-1.5 shrink-0"
        :class="priorityBadge"
      />

      <!-- Title & Hours -->
      <span class="truncate font-medium flex-1 text-[11px] leading-tight">
        {{ task.title }}
      </span>

      <span
        v-if="task.estimated_hours > 0"
        class="ml-1.5 px-1 py-0.5 rounded bg-black/20 text-[9px] font-mono shrink-0 font-medium"
      >
        {{ task.estimated_hours }}h
      </span>

      <!-- Resize handle right -->
      <div
        v-if="canEdit"
        class="absolute right-0 top-0 bottom-0 w-2 cursor-ew-resize hover:bg-white/30 rounded-r-lg"
        @pointerdown.stop="onPointerDown($event, 'resize-right')"
      />
    </div>
  </div>
</template>

