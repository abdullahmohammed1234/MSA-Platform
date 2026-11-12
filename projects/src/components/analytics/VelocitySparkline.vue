<script setup lang="ts">
import { computed } from 'vue';
import type { VelocityWeek } from '../../types/pm';

const props = defineProps<{
  weeks: VelocityWeek[];
  width?: number;
  height?: number;
  color?: string;
}>();

const w = computed(() => props.width || 120);
const h = computed(() => props.height || 36);
const strokeColor = computed(() => props.color || '#10b981');

const points = computed(() => {
  if (!props.weeks || props.weeks.length === 0) return '';
  const data = props.weeks.map((week) => week.tasks_completed);
  const max = Math.max(...data, 1);
  const step = w.value / Math.max(data.length - 1, 1);

  return data
    .map((val, idx) => {
      const x = idx * step;
      const y = h.value - (val / max) * (h.value - 6) - 3;
      return `${x},${y}`;
    })
    .join(' ');
});

const fillPath = computed(() => {
  if (!props.weeks || props.weeks.length === 0) return '';
  const data = props.weeks.map((week) => week.tasks_completed);
  const max = Math.max(...data, 1);
  const step = w.value / Math.max(data.length - 1, 1);

  const pts = data.map((val, idx) => {
    const x = idx * step;
    const y = h.value - (val / max) * (h.value - 6) - 3;
    return `${x},${y}`;
  });

  return `M 0,${h.value} L ${pts.join(' L ')} L ${w.value},${h.value} Z`;
});
</script>

<template>
  <div class="inline-flex items-center">
    <svg :width="w" :height="h" class="overflow-visible" role="img" aria-label="Velocity sparkline">
      <defs>
        <linearGradient :id="`spark-grad-${w}`" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" :stop-color="strokeColor" stop-opacity="0.25" />
          <stop offset="100%" :stop-color="strokeColor" stop-opacity="0.0" />
        </linearGradient>
      </defs>

      <!-- Gradient Area -->
      <path v-if="fillPath" :d="fillPath" :fill="`url(#spark-grad-${w})`" />

      <!-- Line -->
      <polyline
        v-if="points"
        fill="none"
        :stroke="strokeColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        :points="points"
      />
    </svg>
  </div>
</template>

