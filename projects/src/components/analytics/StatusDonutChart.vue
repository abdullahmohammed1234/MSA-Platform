<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  counts: {
    total: number;
    completed: number;
    in_progress: number;
    review?: number;
    todo: number;
    cancelled: number;
  };
  size?: number;
}>();

const chartSize = computed(() => props.size || 140);
const strokeWidth = computed(() => (chartSize.value < 130 ? 16 : 20));
const radius = computed(() => (chartSize.value - strokeWidth.value) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);

const segments = computed(() => {
  const total = props.counts.total || 0;
  if (total === 0) return [];

  const raw = [
    { label: 'Completed', count: props.counts.completed || 0, color: '#10b981' },
    { label: 'In Progress', count: props.counts.in_progress || 0, color: '#3b82f6' },
    { label: 'Review', count: props.counts.review || 0, color: '#8b5cf6' },
    { label: 'To Do', count: props.counts.todo || 0, color: '#f59e0b' },
    { label: 'Cancelled', count: props.counts.cancelled || 0, color: '#9ca3af' },
  ];

  let accumulatedPercent = 0;

  return raw.map((item) => {
    const percent = item.count / total;
    const strokeDasharray = `${percent * circumference.value} ${circumference.value}`;
    const strokeDashoffset = -accumulatedPercent * circumference.value;
    accumulatedPercent += percent;

    return {
      ...item,
      percent: Math.round(percent * 100),
      strokeDasharray,
      strokeDashoffset,
    };
  });
});

const completionRate = computed(() => {
  if (!props.counts.total) return 0;
  return Math.round((props.counts.completed / props.counts.total) * 100);
});
</script>

<template>
  <div v-if="counts.total === 0" class="pm-empty rounded-xl border border-dashed border-[#ebe8de] bg-[#fffbf4] min-h-[9rem] w-full">
    <p class="pm-empty-title">No tasks to chart</p>
    <p>Task status distribution appears once work is underway.</p>
  </div>

  <div v-else class="flex flex-col sm:flex-row items-center gap-4 sm:gap-5 w-full">
    <div class="relative shrink-0 flex items-center justify-center" :style="{ width: `${chartSize}px`, height: `${chartSize}px` }">
      <svg
        :width="chartSize"
        :height="chartSize"
        class="transform -rotate-90"
        role="img"
        aria-label="Task status distribution chart"
      >
        <circle
          :cx="chartSize / 2"
          :cy="chartSize / 2"
          :r="radius"
          fill="none"
          stroke="#f3f4f6"
          :stroke-width="strokeWidth"
        />
        <circle
          v-for="seg in segments"
          :key="seg.label"
          :cx="chartSize / 2"
          :cy="chartSize / 2"
          :r="radius"
          fill="none"
          :stroke="seg.color"
          :stroke-width="strokeWidth"
          :stroke-dasharray="seg.strokeDasharray"
          :stroke-dashoffset="seg.strokeDashoffset"
          class="transition-all duration-500 ease-out"
        />
      </svg>

      <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
        <span class="text-xl sm:text-2xl font-black text-[#1e1e1e] font-display leading-none">
          {{ completionRate }}%
        </span>
        <span class="text-[10px] font-semibold text-[#6b7280] uppercase tracking-wider mt-1">
          Complete
        </span>
      </div>
    </div>

    <div class="flex-1 grid grid-cols-2 gap-2 w-full min-w-0">
      <div
        v-for="seg in segments.filter((s) => s.count > 0)"
        :key="seg.label"
        class="flex items-center justify-between gap-2 p-2 rounded-lg bg-[#fffbf4] border border-[#ebe8de]/80 text-[11px] sm:text-xs min-w-0"
      >
        <div class="flex items-center gap-1.5 min-w-0">
          <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: seg.color }" />
          <span class="font-medium text-[#4b5563] truncate">{{ seg.label }}</span>
        </div>
        <div class="flex items-center gap-1 font-bold shrink-0">
          <span class="text-[#1e1e1e]">{{ seg.count }}</span>
          <span class="text-[10px] text-[#9ca3af]">{{ seg.percent }}%</span>
        </div>
      </div>
    </div>
  </div>
</template>

