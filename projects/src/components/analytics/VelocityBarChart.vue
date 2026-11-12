<script setup lang="ts">
import { computed } from 'vue';
import type { VelocityWeek } from '../../types/pm';

const props = defineProps<{
  weeks: VelocityWeek[];
}>();

const hasData = computed(() =>
  Array.isArray(props.weeks) &&
  props.weeks.some((w) => (w.tasks_created || 0) > 0 || (w.tasks_completed || 0) > 0)
);

const maxVal = computed(() => {
  if (!props.weeks || props.weeks.length === 0) return 5;
  const maxInDataset = Math.max(
    ...props.weeks.map((w) => Math.max(w.tasks_created, w.tasks_completed)),
    0
  );
  return maxInDataset > 0 ? Math.ceil(maxInDataset * 1.25) : 5;
});

const chartHeight = 120;
const chartWidth = 500;
const barGroupWidth = computed(() => chartWidth / (props.weeks.length || 8));
const singleBarWidth = 12;

const getY = (val: number) => chartHeight - (val / maxVal.value) * chartHeight;
const getBarHeight = (val: number) => (val / maxVal.value) * chartHeight;
</script>

<template>
  <div class="space-y-2.5 w-full">
    <div class="flex items-center justify-between gap-2 text-[11px] sm:text-xs">
      <span class="font-semibold text-[#6b7280]">Created vs completed</span>
      <div class="flex items-center gap-3">
        <div class="flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-sm bg-[#b02e32]" />
          <span class="text-[#6b7280] font-medium">Created</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-sm bg-[#10b981]" />
          <span class="text-[#6b7280] font-medium">Completed</span>
        </div>
      </div>
    </div>

    <div
      v-if="!hasData"
      class="pm-empty rounded-xl border border-dashed border-[#ebe8de] bg-[#fffbf4] min-h-[10rem]"
    >
      <p class="pm-empty-title">No velocity data yet</p>
      <p>Task creation and completion will appear here over time.</p>
    </div>

    <div
      v-else
      class="w-full rounded-xl border border-[#ebe8de] bg-[#fffbf4] px-2 py-2 overflow-x-auto"
    >
      <svg
        viewBox="0 0 500 155"
        class="w-full h-[10.5rem] sm:h-[12rem] lg:h-[13rem] overflow-visible"
        role="img"
        aria-label="Velocity trend chart"
      >
        <line x1="0" :y1="chartHeight" x2="500" :y2="chartHeight" stroke="#e5e7eb" stroke-width="1" />
        <line x1="0" :y1="chartHeight / 2" x2="500" :y2="chartHeight / 2" stroke="#e5e7eb" stroke-width="1" stroke-dasharray="3 3" />

        <g v-for="(week, index) in weeks" :key="week.week_number">
          <rect
            :x="index * barGroupWidth + (barGroupWidth / 2) - singleBarWidth - 2"
            :y="getY(week.tasks_created)"
            :width="singleBarWidth"
            :height="getBarHeight(week.tasks_created)"
            rx="3"
            fill="#b02e32"
            opacity="0.9"
          >
            <title>{{ week.week_label }}: {{ week.tasks_created }} created</title>
          </rect>

          <rect
            :x="index * barGroupWidth + (barGroupWidth / 2) + 2"
            :y="getY(week.tasks_completed)"
            :width="singleBarWidth"
            :height="getBarHeight(week.tasks_completed)"
            rx="3"
            fill="#10b981"
          >
            <title>{{ week.week_label }}: {{ week.tasks_completed }} completed</title>
          </rect>

          <text
            :x="index * barGroupWidth + (barGroupWidth / 2)"
            :y="chartHeight + 18"
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
