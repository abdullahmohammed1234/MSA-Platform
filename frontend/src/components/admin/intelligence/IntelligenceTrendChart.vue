<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  data: Array<{ date: string; label: string; [key: string]: any }>;
  dataKey: string;
  title: string;
  color?: 'emerald' | 'amber' | 'blue' | 'indigo' | 'rose';
  prefix?: string;
  suffix?: string;
}>();

const strokeColor = computed(() => {
  switch (props.color) {
    case 'amber': return '#f59e0b';
    case 'blue': return '#3b82f6';
    case 'indigo': return '#6366f1';
    case 'rose': return '#f43f5e';
    case 'emerald':
    default: return '#10b981';
  }
});

const fillColor = computed(() => {
  switch (props.color) {
    case 'amber': return 'rgba(245, 158, 11, 0.15)';
    case 'blue': return 'rgba(59, 130, 246, 0.15)';
    case 'indigo': return 'rgba(99, 102, 241, 0.15)';
    case 'rose': return 'rgba(244, 63, 94, 0.15)';
    case 'emerald':
    default: return 'rgba(16, 185, 129, 0.15)';
  }
});

const values = computed(() => props.data.map(d => Number(d[props.dataKey] || 0)));
const maxVal = computed(() => Math.max(...values.value, 1));
const minVal = computed(() => Math.min(...values.value, 0));

const points = computed(() => {
  if (props.data.length === 0) return '';
  const width = 500;
  const height = 120;
  const padding = 10;
  
  const range = maxVal.value - minVal.value || 1;
  const stepX = (width - padding * 2) / Math.max(1, props.data.length - 1);

  return props.data.map((d, i) => {
    const x = padding + i * stepX;
    const val = Number(d[props.dataKey] || 0);
    const y = height - padding - ((val - minVal.value) / range) * (height - padding * 2);
    return `${x.toFixed(1)},${y.toFixed(1)}`;
  }).join(' ');
});

const areaPath = computed(() => {
  if (!points.value) return '';
  const pts = points.value.split(' ');
  const firstX = pts[0].split(',')[0];
  const lastX = pts[pts.length - 1].split(',')[0];
  return `M ${firstX},120 L ${points.value} L ${lastX},120 Z`;
});
</script>

<template>
  <div class="bg-white border border-neutral-ivory rounded-2xl p-5 shadow-soft space-y-4">
    <div class="flex items-center justify-between">
      <h4 class="text-xs uppercase tracking-wider font-bold text-neutral-muted">{{ title }}</h4>
      <div class="text-sm font-display font-bold text-primary">
        {{ prefix || '' }}{{ values.reduce((a, b) => a + b, 0).toLocaleString() }}{{ suffix || '' }} Total
      </div>
    </div>

    <div v-if="data.length === 0" class="h-32 flex items-center justify-center text-xs text-neutral-muted">
      No data available for selected period.
    </div>

    <div v-else class="relative">
      <svg class="w-full h-32 overflow-visible" viewBox="0 0 500 120" preserveAspectRatio="none">
        <!-- Grid lines -->
        <line x1="0" y1="20" x2="500" y2="20" stroke="#ebe8de" stroke-dasharray="4 4" stroke-width="1" />
        <line x1="0" y1="60" x2="500" y2="60" stroke="#ebe8de" stroke-dasharray="4 4" stroke-width="1" />
        <line x1="0" y1="100" x2="500" y2="100" stroke="#ebe8de" stroke-dasharray="4 4" stroke-width="1" />

        <!-- Area Fill -->
        <path :d="areaPath" :fill="fillColor" />

        <!-- Line Curve -->
        <polyline
          fill="none"
          :stroke="strokeColor"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
          :points="points"
        />
      </svg>

      <!-- Date X-Axis Labels -->
      <div class="flex justify-between text-[10px] text-neutral-muted pt-2 font-mono">
        <span>{{ data[0]?.label || '' }}</span>
        <span>{{ data[Math.floor(data.length / 2)]?.label || '' }}</span>
        <span>{{ data[data.length - 1]?.label || '' }}</span>
      </div>
    </div>
  </div>
</template>
