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

const chartSize = computed(() => props.size || 180);
const strokeWidth = 22;
const radius = computed(() => (chartSize.value - strokeWidth) / 2);
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
  <div class="flex flex-col sm:flex-row items-center gap-6">
    <!-- Donut SVG -->
    <div class="relative shrink-0 flex items-center justify-center" :style="{ width: `${chartSize}px`, height: `${chartSize}px` }">
      <svg
        :width="chartSize"
        :height="chartSize"
        class="transform -rotate-90"
        role="img"
        aria-label="Task status distribution chart"
      >
        <!-- Background track -->
        <circle
          :cx="chartSize / 2"
          :cy="chartSize / 2"
          :r="radius"
          fill="none"
          stroke="#f3f4f6"
          :stroke-width="strokeWidth"
        />

        <!-- Empty state circle if 0 tasks -->
        <circle
          v-if="counts.total === 0"
          :cx="chartSize / 2"
          :cy="chartSize / 2"
          :r="radius"
          fill="none"
          stroke="#e5e7eb"
          :stroke-width="strokeWidth"
          stroke-dasharray="4 4"
        />

        <!-- Segments -->
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

      <!-- Center text -->
      <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
        <span class="text-2xl font-black text-[#1e1e1e] font-display">
          {{ completionRate }}%
        </span>
        <span class="text-[11px] font-semibold text-[#6b7280] uppercase tracking-wider">
          Complete
        </span>
      </div>
    </div>

    <!-- Legend -->
    <div class="flex-1 grid grid-cols-2 gap-2.5 w-full">
      <div
        v-for="seg in segments"
        :key="seg.label"
        class="flex items-center justify-between p-2 rounded-xl bg-[#fbfbf9] border border-[#ebe8de]/70 text-xs"
      >
        <div class="flex items-center gap-2 truncate">
          <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: seg.color }"></span>
          <span class="font-medium text-[#4b5563] truncate">{{ seg.label }}</span>
        </div>
        <div class="flex items-center gap-1.5 font-bold shrink-0">
          <span class="text-[#1e1e1e]">{{ seg.count }}</span>
          <span class="text-[10px] text-[#9ca3af]">({{ seg.percent }}%)</span>
        </div>
      </div>
    </div>
  </div>
</template>

