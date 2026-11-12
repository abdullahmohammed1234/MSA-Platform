<script setup lang="ts">
import { computed } from 'vue';
import type { VelocityWeek } from '../../types/pm';

const props = defineProps<{
  weeks: VelocityWeek[];
}>();

const maxVal = computed(() => {
  if (!props.weeks || props.weeks.length === 0) return 10;
  const maxInDataset = Math.max(
    ...props.weeks.map((w) => Math.max(w.tasks_created, w.tasks_completed)),
    0
  );
  return maxInDataset > 0 ? Math.ceil(maxInDataset * 1.25) : 5;
});

const chartHeight = 140;
const chartWidth = 500;
const barGroupWidth = computed(() => chartWidth / (props.weeks.length || 8));
const singleBarWidth = 14;

const getY = (val: number) => {
  return chartHeight - (val / maxVal.value) * chartHeight;
};

const getBarHeight = (val: number) => {
  return (val / maxVal.value) * chartHeight;
};
</script>

<template>
  <div class="space-y-3">
    <!-- Header Legend -->
    <div class="flex items-center justify-between text-xs">
      <span class="font-bold text-[#1e1e1e]">Weekly Velocity (Created vs Completed)</span>
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-1.5">
          <span class="w-2.5 h-2.5 rounded-sm bg-[#b02e32]"></span>
          <span class="text-[#6b7280] font-medium">Created</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-2.5 h-2.5 rounded-sm bg-[#10b981]"></span>
          <span class="text-[#6b7280] font-medium">Completed</span>
        </div>
      </div>
    </div>

    <!-- SVG Chart Container -->
    <div class="w-full bg-[#fbfbf9] rounded-2xl border border-[#ebe8de] p-3 overflow-x-auto">
      <svg
        viewBox="0 0 500 180"
        class="w-full h-44 overflow-visible"
        role="img"
        aria-label="Velocity Trend Chart"
      >
        <!-- Horizontal grid lines -->
        <line x1="0" :y1="chartHeight" x2="500" :y2="chartHeight" stroke="#e5e7eb" stroke-width="1" />
        <line x1="0" :y1="chartHeight / 2" x2="500" :y2="chartHeight / 2" stroke="#e5e7eb" stroke-width="1" stroke-dasharray="3 3" />
        <line x1="0" y1="0" x2="500" y2="0" stroke="#e5e7eb" stroke-width="1" stroke-dasharray="3 3" />

        <!-- Bars per week -->
        <g v-for="(week, index) in weeks" :key="week.week_number">
          <!-- X position of group center -->
          <!-- Created Bar -->
          <rect
            :x="index * barGroupWidth + (barGroupWidth / 2) - singleBarWidth - 2"
            :y="getY(week.tasks_created)"
            :width="singleBarWidth"
            :height="getBarHeight(week.tasks_created)"
            rx="3"
            fill="#b02e32"
            opacity="0.85"
            class="transition-all duration-300 hover:opacity-100"
          >
            <title>{{ week.week_label }}: {{ week.tasks_created }} created</title>
          </rect>

          <!-- Completed Bar -->
          <rect
            :x="index * barGroupWidth + (barGroupWidth / 2) + 2"
            :y="getY(week.tasks_completed)"
            :width="singleBarWidth"
            :height="getBarHeight(week.tasks_completed)"
            rx="3"
            fill="#10b981"
            class="transition-all duration-300 hover:opacity-100"
          >
            <title>{{ week.week_label }}: {{ week.tasks_completed }} completed</title>
          </rect>

          <!-- Value labels if > 0 -->
          <text
            v-if="week.tasks_completed > 0"
            :x="index * barGroupWidth + (barGroupWidth / 2) + 9"
            :y="getY(week.tasks_completed) - 4"
            text-anchor="middle"
            fill="#059669"
            font-size="10"
            font-weight="bold"
          >
            {{ week.tasks_completed }}
          </text>

          <!-- Week Label on X Axis -->
          <text
            :x="index * barGroupWidth + (barGroupWidth / 2)"
            :y="chartHeight + 20"
            text-anchor="middle"
            fill="#6b7280"
            font-size="10"
            font-weight="600"
          >
            {{ week.week_label }}
          </text>
        </g>
      </svg>
    </div>
  </div>
</template>

